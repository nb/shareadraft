<?php
/**
 * Base test case for Share a Draft tests
 *
 * Provides common setup and utility methods for all test classes.
 */

abstract class ShareADraft_TestCase extends WP_UnitTestCase {

	/**
	 * Plugin instance
	 *
	 * @var Share_a_Draft
	 */
	protected $plugin;

	/**
	 * Test user ID
	 *
	 * @var int
	 */
	protected $user_id;

	/**
	 * Registered test post types.
	 *
	 * @var array
	 */
	protected $_test_post_types = array();


	public function setUp(): void {
		parent::setUp();
		global $__share_a_draft;

		// Ensure plugin is instantiated
		if ( ! $__share_a_draft ) {
			$__share_a_draft = new Share_a_Draft();
		}

		$this->plugin = $__share_a_draft;

		// Create and set test user with editor role
		$this->user_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $this->user_id );

		// Initialize plugin for current user
		$this->plugin->init();
	}

	/**
	 * Helper: Create a draft post
	 *
	 * @param string $post_type Post type to create (default: 'post')
	 * @param array  $args      Additional arguments
	 * @return int Post ID
	 */
	protected function create_draft( $post_type = 'post', $args = array() ) {
		$defaults = array(
			'post_status' => 'draft',
			'post_type'   => $post_type,
			'post_title'  => 'Test ' . ucfirst( $post_type ),
			'author'      => $this->user_id,
		);

		$args = wp_parse_args( $args, $defaults );

		return $this->factory->post->create( $args );
	}

	/**
	 * Helper: Create a share for a post
	 *
	 * @param int   $post_id Post ID to share
	 * @param array $params  Share parameters (expires, measure)
	 * @return array|null Share data or null on failure
	 */
	protected function create_share( $post_id, $params = array() ) {
		$defaults = array(
			'post_id' => $post_id,
			'expires' => 2,
			'measure' => 'w',
		);

		$params = wp_parse_args( $params, $defaults );

		$result = $this->plugin->process_new_share( $params );

		// Return null if error
		if ( $result ) {
			return null;
		}

		$shares = $this->plugin->get_shared();
		return end( $shares );
	}

	/**
	 * Helper: Get share key for a post
	 *
	 * @param int $post_id Post ID
	 * @return string|null Share key or null if not found
	 */
	protected function get_share_key( $post_id ) {
		$shares = $this->plugin->get_shared();

		foreach ( $shares as $share ) {
			if ( $share['id'] === $post_id ) {
				return $share['key'];
			}
		}

		return null;
	}

	/**
	 * Helper: Register a temporary post type for testing
	 *
	 * @param string $post_type Post type name
	 * @param array  $args      Post type arguments
	 * @return void
	 */
	protected function register_test_post_type( $post_type, $args = array() ) {
		register_post_type( $post_type, $args );

		// Store for cleanup in tearDown
		if ( ! isset( $this->_test_post_types ) ) {
			$this->_test_post_types = array();
		}
		$this->_test_post_types[] = $post_type;
	}

	/**
	 * Clean up registered post types
	 */
	protected function unregister_test_post_types() {
		if ( ! empty( $this->_test_post_types ) ) {
			foreach ( $this->_test_post_types as $post_type ) {
				unregister_post_type( $post_type );
			}
			$this->_test_post_types = array();
		}
	}

	/**
	 * Tear down test environment
	 */
	public function tearDown(): void {
		// Clean up global state
		unset( $_GET['shareadraft'] );

		// Clean up test post types
		$this->unregister_test_post_types();

		parent::tearDown();
	}
}
