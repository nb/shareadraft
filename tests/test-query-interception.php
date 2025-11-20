<?php
/**
 * Tests for query interception and FSE edge cases
 */

class Test_ShareADraft_QueryInterception extends ShareADraft_TestCase {

	/**
	 * Test that posts_results_intercept only processes supported post types
	 */
	public function test_posts_results_intercept_filters_by_post_type() {
		// Create a draft post
		$post_id = $this->create_draft( 'post' );
		$post = get_post( $post_id );
		$query = new WP_Query();

		// Create a real share to get a valid key
		$share = $this->create_share( $post_id );
		$_GET['shareadraft'] = $share['key'];

		// Should intercept supported post type
		$result = $this->plugin->posts_results_intercept( array( $post ), $query );
		$this->assertNotNull( $this->plugin->shared_post );

		// Reset
		$this->plugin->shared_post = null;

		// Create an unsupported post type
		$this->register_test_post_type( 'internal', array(
			'public' => false,
			'publicly_queryable' => false,
		) );

		$internal_post = $this->create_draft( 'internal' );
		$internal_post_obj = get_post( $internal_post );

		// Should NOT intercept unsupported post type
		$result = $this->plugin->posts_results_intercept( array( $internal_post_obj ), $query );
		$this->assertNull( $this->plugin->shared_post );
	}

	/**
	 * Test that the_posts_intercept handles 'any' post_type query
	 */
	public function test_the_posts_intercept_allows_any_post_type() {
		$post_id = $this->create_draft( 'post' );
		$post = get_post( $post_id );
		$this->plugin->shared_post = $post;

		$query = new WP_Query();
		$query->query_vars['post_type'] = 'any';

		// Should inject post when post_type is 'any'
		$result = $this->plugin->the_posts_intercept( array(), $query );
		$this->assertCount( 1, $result );
		$this->assertEquals( $post_id, $result[0]->ID );
	}

	/**
	 * Test that the_posts_intercept allows injection when post_type is empty
	 */
	public function test_the_posts_intercept_allows_empty_post_type() {
		$post_id = $this->factory->post->create( array(
			'post_status' => 'draft',
			'post_type' => 'post',
			'author' => $this->user_id,
		) );

		$post = get_post( $post_id );
		$this->plugin->shared_post = $post;

		$query = new WP_Query();
		$query->query_vars['post_type'] = '';

		// Should inject post when post_type is empty
		$result = $this->plugin->the_posts_intercept( array(), $query );
		$this->assertCount( 1, $result );
		$this->assertEquals( $post_id, $result[0]->ID );
	}

	/**
	 * Test that the_posts_intercept blocks injection for FSE template queries
	 */
	public function test_the_posts_intercept_blocks_fse_template_injection() {
		$post_id = $this->factory->post->create( array(
			'post_status' => 'draft',
			'post_type' => 'post',
			'author' => $this->user_id,
		) );

		$post = get_post( $post_id );
		$this->plugin->shared_post = $post;

		$query = new WP_Query();
		$query->query_vars['post_type'] = 'wp_template_part';

		// Should NOT inject blog post into template query
		$result = $this->plugin->the_posts_intercept( array(), $query );
		$this->assertEmpty( $result );
	}

	/**
	 * Test that the_posts_intercept blocks injection for wp_template queries
	 */
	public function test_the_posts_intercept_blocks_wp_template_injection() {
		$post_id = $this->factory->post->create( array(
			'post_status' => 'draft',
			'post_type' => 'post',
			'author' => $this->user_id,
		) );

		$post = get_post( $post_id );
		$this->plugin->shared_post = $post;

		$query = new WP_Query();
		$query->query_vars['post_type'] = 'wp_template';

		// Should NOT inject blog post into template query
		$result = $this->plugin->the_posts_intercept( array(), $query );
		$this->assertEmpty( $result );
	}

	/**
	 * Test that the_posts_intercept allows injection for supported post types
	 */
	public function test_the_posts_intercept_allows_supported_post_types() {
		$post_id = $this->factory->post->create( array(
			'post_status' => 'draft',
			'post_type' => 'post',
			'author' => $this->user_id,
		) );

		$post = get_post( $post_id );
		$this->plugin->shared_post = $post;

		$query = new WP_Query();
		$query->query_vars['post_type'] = 'post';

		// Should inject when querying for 'post'
		$result = $this->plugin->the_posts_intercept( array(), $query );
		$this->assertCount( 1, $result );
		$this->assertEquals( $post_id, $result[0]->ID );
	}

	/**
	 * Test that the_posts_intercept handles array of post types
	 */
	public function test_the_posts_intercept_handles_post_type_array() {
		$post_id = $this->factory->post->create( array(
			'post_status' => 'draft',
			'post_type' => 'post',
			'author' => $this->user_id,
		) );

		$post = get_post( $post_id );
		$this->plugin->shared_post = $post;

		$query = new WP_Query();
		$query->query_vars['post_type'] = array( 'post', 'page' );

		// Should inject when 'post' is in the array
		$result = $this->plugin->the_posts_intercept( array(), $query );
		$this->assertCount( 1, $result );
		$this->assertEquals( $post_id, $result[0]->ID );
	}

	/**
	 * Test that the_posts_intercept blocks when array contains only unsupported types
	 */
	public function test_the_posts_intercept_blocks_unsupported_array() {
		$post_id = $this->factory->post->create( array(
			'post_status' => 'draft',
			'post_type' => 'post',
			'author' => $this->user_id,
		) );

		$post = get_post( $post_id );
		$this->plugin->shared_post = $post;

		$query = new WP_Query();
		$query->query_vars['post_type'] = array( 'wp_template', 'wp_template_part' );

		// Should NOT inject when array contains only unsupported types
		$result = $this->plugin->the_posts_intercept( array(), $query );
		$this->assertEmpty( $result );
	}

	/**
	 * Test that shared_post is reset when posts are returned
	 */
	public function test_the_posts_intercept_resets_shared_post() {
		$post_id = $this->factory->post->create( array(
			'post_status' => 'publish',
			'post_type' => 'post',
		) );

		$post = get_post( $post_id );
		$this->plugin->shared_post = get_post( $post_id );

		$query = new WP_Query();

		// When posts are returned, shared_post should be reset
		$result = $this->plugin->the_posts_intercept( array( $post ), $query );
		$this->assertNull( $this->plugin->shared_post );
	}
}
