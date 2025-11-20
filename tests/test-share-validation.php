<?php
/**
 * Tests for share creation validation
 */

class Test_ShareADraft_Validation extends ShareADraft_TestCase {

	/**
	 * Test that sharing a post draft succeeds
	 */
	public function test_can_share_post_draft() {
		$post_id = $this->create_draft( 'post' );
		$share = $this->create_share( $post_id );

		$this->assertNotNull( $share );
		$this->assertEquals( $post_id, $share['id'] );
	}

	/**
	 * Test that sharing a page draft succeeds
	 */
	public function test_can_share_page_draft() {
		$page_id = $this->create_draft( 'page' );
		$share = $this->create_share( $page_id );

		$this->assertNotNull( $share );
		$this->assertEquals( $page_id, $share['id'] );
	}

	/**
	 * Test that sharing an unsupported post type fails
	 */
	public function test_cannot_share_unsupported_post_type() {
		// Register a non-publicly-queryable type
		$this->register_test_post_type( 'internal', array(
			'public' => false,
			'publicly_queryable' => false,
		) );

		$post_id = $this->create_draft( 'internal' );
		$share = $this->create_share( $post_id );

		// Should return null (failed to create share)
		$this->assertNull( $share );
	}

	/**
	 * Test that sharing a published post fails
	 */
	public function test_cannot_share_published_post() {
		$post_id = $this->factory->post->create( array(
			'post_status' => 'publish',
			'post_type' => 'post',
			'author' => $this->user_id,
		) );

		$result = $this->plugin->process_new_share( array(
			'post_id' => $post_id,
			'expires' => 2,
			'measure' => 'w',
		) );

		$this->assertNotEmpty( $result );
		$this->assertStringContainsString( 'published', $result );

		$shares = $this->plugin->get_shared();
		$this->assertEmpty( $shares );
	}

	/**
	 * Test that sharing a non-existent post fails
	 */
	public function test_cannot_share_nonexistent_post() {
		$result = $this->plugin->process_new_share( array(
			'post_id' => 999999,
			'expires' => 2,
			'measure' => 'w',
		) );

		$this->assertNotEmpty( $result );
		$this->assertStringContainsString( 'no such post', $result );
	}

	/**
	 * Test that user cannot share post they cannot edit
	 */
	public function test_cannot_share_post_without_permission() {
		// Create a post by another user
		$other_user = $this->factory->user->create( array( 'role' => 'author' ) );
		$post_id = $this->factory->post->create( array(
			'post_status' => 'draft',
			'post_type' => 'post',
			'author' => $other_user,
		) );

		// Switch to a subscriber who can't edit others' posts
		$subscriber = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber );

		$result = $this->plugin->process_new_share( array(
			'post_id' => $post_id,
			'expires' => 2,
			'measure' => 'w',
		) );

		$this->assertNotEmpty( $result );
		$this->assertStringContainsString( 'not allowed', $result );
	}

	/**
	 * Test that sharing custom publicly queryable post type succeeds
	 */
	public function test_can_share_custom_publicly_queryable_type() {
		$this->register_test_post_type( 'event', array(
			'public' => true,
			'publicly_queryable' => true,
		) );

		$post_id = $this->create_draft( 'event' );
		$share = $this->create_share( $post_id );

		$this->assertNotNull( $share );
		$this->assertEquals( $post_id, $share['id'] );
	}
}
