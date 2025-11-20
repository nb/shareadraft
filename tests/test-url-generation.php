<?php
/**
 * Tests for URL generation for different post types
 */

class Test_ShareADraft_URLGeneration extends ShareADraft_TestCase {

	/**
	 * Helper to create a share and extract the generated URL from output
	 */
	private function get_share_url_from_output( $post_id ) {
		// Create a share
		$this->plugin->process_new_share( array(
			'post_id' => $post_id,
			'expires' => 2,
			'measure' => 'w',
		) );

		// Capture output
		ob_start();
		$this->plugin->output_existing_menu_sub_admin_page();
		$output = ob_get_clean();

		// Extract URL from output
		preg_match( '/href="([^"]*shareadraft=[^"]*)"/i', $output, $matches );
		return isset( $matches[1] ) ? html_entity_decode( $matches[1] ) : '';
	}

	/**
	 * Test that post URLs use ?p= parameter
	 */
	public function test_post_url_uses_p_parameter() {
		$post_id = $this->factory->post->create( array(
			'post_status' => 'draft',
			'post_type' => 'post',
			'post_title' => 'Test Post',
			'author' => $this->user_id,
		) );

		$url = $this->get_share_url_from_output( $post_id );

		$this->assertStringContainsString( '?p=' . $post_id, $url );
		$this->assertStringContainsString( '&shareadraft=', $url );
		$this->assertStringNotContainsString( 'page_id=', $url );
		$this->assertStringNotContainsString( 'post_type=', $url );
	}

	/**
	 * Test that page URLs use ?page_id= parameter
	 */
	public function test_page_url_uses_page_id_parameter() {
		$page_id = $this->factory->post->create( array(
			'post_status' => 'draft',
			'post_type' => 'page',
			'post_title' => 'Test Page',
			'author' => $this->user_id,
		) );

		$url = $this->get_share_url_from_output( $page_id );

		$this->assertStringContainsString( '?page_id=' . $page_id, $url );
		$this->assertStringContainsString( '&shareadraft=', $url );
		$this->assertStringNotContainsString( '?p=', $url );
	}

	/**
	 * Test that custom post type URLs include post_type parameter
	 */
	public function test_custom_post_type_url_includes_post_type() {
		$this->register_test_post_type( 'event', array(
			'public' => true,
			'publicly_queryable' => true,
		) );

		$event_id = $this->create_draft( 'event', array( 'post_title' => 'Test Event' ) );
		$url = $this->get_share_url_from_output( $event_id );

		$this->assertStringContainsString( '?post_type=event', $url );
		$this->assertStringContainsString( '&p=' . $event_id, $url );
		$this->assertStringContainsString( '&shareadraft=', $url );
	}

	/**
	 * Test that all share URLs include the shareadraft key
	 */
	public function test_all_urls_include_shareadraft_key() {
		$post_id = $this->factory->post->create( array(
			'post_status' => 'draft',
			'post_type' => 'post',
			'author' => $this->user_id,
		) );

		$url = $this->get_share_url_from_output( $post_id );

		$this->assertStringContainsString( 'shareadraft=', $url );

		// Verify the key format (should start with 'baba')
		preg_match( '/shareadraft=([^&"]+)/', $url, $matches );
		$this->assertNotEmpty( $matches[1] );
		$this->assertStringStartsWith( 'baba', $matches[1] );
	}

	/**
	 * Test that URL generation handles multiple post types correctly
	 */
	public function test_multiple_post_types_generate_correct_urls() {
		// Create multiple post types
		$post_id = $this->factory->post->create( array(
			'post_status' => 'draft',
			'post_type' => 'post',
			'post_title' => 'Test Post',
			'author' => $this->user_id,
		) );

		$page_id = $this->factory->post->create( array(
			'post_status' => 'draft',
			'post_type' => 'page',
			'post_title' => 'Test Page',
			'author' => $this->user_id,
		) );

		register_post_type( 'portfolio', array(
			'public' => true,
			'publicly_queryable' => true,
		) );

		$portfolio_id = $this->factory->post->create( array(
			'post_status' => 'draft',
			'post_type' => 'portfolio',
			'post_title' => 'Test Portfolio',
			'author' => $this->user_id,
		) );

		// Share all three
		$this->plugin->process_new_share( array(
			'post_id' => $post_id,
			'expires' => 2,
			'measure' => 'w',
		) );

		$this->plugin->process_new_share( array(
			'post_id' => $page_id,
			'expires' => 2,
			'measure' => 'w',
		) );

		$this->plugin->process_new_share( array(
			'post_id' => $portfolio_id,
			'expires' => 2,
			'measure' => 'w',
		) );

		// Capture output
		ob_start();
		$this->plugin->output_existing_menu_sub_admin_page();
		$output = ob_get_clean();

		// Verify all three have correct URL patterns
		$this->assertStringContainsString( '?p=' . $post_id . '&amp;shareadraft=', $output );
		$this->assertStringContainsString( '?page_id=' . $page_id . '&amp;shareadraft=', $output );
		$this->assertStringContainsString( '?post_type=portfolio&amp;p=' . $portfolio_id . '&amp;shareadraft=', $output );

		unregister_post_type( 'portfolio' );
	}

	/**
	 * Test that base URL is correctly included
	 */
	public function test_urls_include_site_base_url() {
		$post_id = $this->factory->post->create( array(
			'post_status' => 'draft',
			'post_type' => 'post',
			'author' => $this->user_id,
		) );

		$url = $this->get_share_url_from_output( $post_id );
		$site_url = get_bloginfo( 'url' );

		$this->assertStringStartsWith( $site_url, $url );
	}
}
