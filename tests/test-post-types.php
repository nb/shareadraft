<?php
/**
 * Tests for post type support functionality
 */

class Test_ShareADraft_PostTypes extends ShareADraft_TestCase {

	/**
	 * Test that get_supported_post_types returns publicly queryable post types
	 */
	public function test_get_supported_post_types_returns_publicly_queryable() {
		$supported = $this->plugin->get_supported_post_types();

		// Should include built-in publicly queryable types
		$this->assertContains( 'post', $supported );
		$this->assertContains( 'page', $supported );
	}

	/**
	 * Test that FSE post types are not in supported types
	 */
	public function test_fse_post_types_not_supported() {
		$supported = $this->plugin->get_supported_post_types();

		// FSE types should not be supported (not publicly queryable)
		$this->assertNotContains( 'wp_template', $supported );
		$this->assertNotContains( 'wp_template_part', $supported );
		$this->assertNotContains( 'wp_navigation', $supported );
		$this->assertNotContains( 'wp_block', $supported );
	}

	/**
	 * Test that custom post types with publicly_queryable are supported
	 */
	public function test_custom_post_type_publicly_queryable_supported() {
		// Register a publicly queryable custom post type
		$this->register_test_post_type( 'event', array(
			'public' => true,
			'publicly_queryable' => true,
		) );

		$supported = $this->plugin->get_supported_post_types();
		$this->assertContains( 'event', $supported );
	}

	/**
	 * Test that custom post types without publicly_queryable are not supported
	 */
	public function test_custom_post_type_not_publicly_queryable_not_supported() {
		// Register a non-publicly queryable custom post type
		$this->register_test_post_type( 'internal_note', array(
			'public' => true,
			'publicly_queryable' => false,
		) );

		$supported = $this->plugin->get_supported_post_types();
		$this->assertNotContains( 'internal_note', $supported );
	}

	/**
	 * Test the shareadraft_supported_post_types filter hook
	 */
	public function test_supported_post_types_filter() {
		$filter_callback = function( $post_types ) {
			// Remove 'page' from supported types
			return array_diff( $post_types, array( 'page' ) );
		};

		add_filter( 'shareadraft_supported_post_types', $filter_callback );

		$supported = $this->plugin->get_supported_post_types();
		$this->assertNotContains( 'page', $supported );
		$this->assertContains( 'post', $supported );

		remove_filter( 'shareadraft_supported_post_types', $filter_callback );
	}

	/**
	 * Test that get_drafts only returns supported post types
	 */
	public function test_get_drafts_filters_by_supported_types() {
		// Create drafts of different types
		$post_id = $this->create_draft( 'post' );
		$page_id = $this->create_draft( 'page' );

		// Register and create a non-publicly-queryable type
		$this->register_test_post_type( 'internal', array(
			'public' => false,
			'publicly_queryable' => false,
		) );
		$internal_id = $this->create_draft( 'internal' );

		$draft_groups = $this->plugin->get_drafts();
		$all_drafts = array_merge( $draft_groups[0]['posts'], $draft_groups[1]['posts'] );
		$draft_ids = wp_list_pluck( $all_drafts, 'ID' );

		// Should include post and page
		$this->assertContains( $post_id, $draft_ids );
		$this->assertContains( $page_id, $draft_ids );

		// Should NOT include internal type
		$this->assertNotContains( $internal_id, $draft_ids );
	}
}
