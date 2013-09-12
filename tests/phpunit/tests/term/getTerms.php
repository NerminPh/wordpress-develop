<?php

/**
 * @group taxonomy
 */
class Tests_Term_getTerms extends WP_UnitTestCase {
	function setUp() {
		parent::setUp();

		wp_cache_delete( 'last_changed', 'terms' );
	}

	/**
	 * @ticket 23326
	 */
	function test_get_terms_cache() {
		global $wpdb;

		$posts = $this->factory->post->create_many( 15, array( 'post_type' => 'post' ) );
		foreach ( $posts as $post )
			wp_set_object_terms( $post, rand_str(), 'post_tag' );
		wp_cache_delete( 'last_changed', 'terms' );

		$this->assertFalse( wp_cache_get( 'last_changed', 'terms' ) );

		$num_queries = $wpdb->num_queries;

		// last_changed and num_queries should bump
		$terms = get_terms( 'post_tag' );
		$this->assertEquals( 15, count( $terms ) );
		$this->assertNotEmpty( $time1 = wp_cache_get( 'last_changed', 'terms' ) );
		$this->assertEquals( $num_queries + 1, $wpdb->num_queries );

		$num_queries = $wpdb->num_queries;

		// Again. last_changed and num_queries should remain the same.
		$terms = get_terms( 'post_tag' );
		$this->assertEquals( 15, count( $terms ) );
		$this->assertEquals( $time1, wp_cache_get( 'last_changed', 'terms' ) );
		$this->assertEquals( $num_queries, $wpdb->num_queries );

		$num_queries = $wpdb->num_queries;


		// Different query. num_queries should bump, last_changed should remain the same.
		$terms = get_terms( 'post_tag', array( 'number' => 10 ) );
		$this->assertEquals( 10, count( $terms ) );
		$this->assertEquals( $time1, wp_cache_get( 'last_changed', 'terms' ) );
		$this->assertEquals( $num_queries + 1, $wpdb->num_queries );

		$num_queries = $wpdb->num_queries;

		// Again. last_changed and num_queries should remain the same.
		$terms = get_terms( 'post_tag', array( 'number' => 10 ) );
		$this->assertEquals( 10, count( $terms ) );
		$this->assertEquals( $time1, wp_cache_get( 'last_changed', 'terms' ) );
		$this->assertEquals( $num_queries, $wpdb->num_queries );

		// Force last_changed to bump
		wp_delete_term( $terms[0]->term_id, 'post_tag' );

		$num_queries = $wpdb->num_queries;
		$this->assertNotEquals( $time1, $time2 = wp_cache_get( 'last_changed', 'terms' ) );

		// last_changed and num_queries should bump after a term is deleted
		$terms = get_terms( 'post_tag' );
		$this->assertEquals( 14, count( $terms ) );
		$this->assertEquals( $time2, wp_cache_get( 'last_changed', 'terms' ) );
		$this->assertEquals( $num_queries + 1, $wpdb->num_queries );

		$num_queries = $wpdb->num_queries;

		// Again. last_changed and num_queries should remain the same.
		$terms = get_terms( 'post_tag' );
		$this->assertEquals( 14, count( $terms ) );
		$this->assertEquals( $time2, wp_cache_get( 'last_changed', 'terms' ) );
		$this->assertEquals( $num_queries, $wpdb->num_queries );

		// @todo Repeat with term insert and update.
	}

	/**
	 * @ticket 23506
	 */
	function test_get_terms_should_allow_arbitrary_indexed_taxonomies_array() {
		$term_id = $this->factory->tag->create();
		$terms = get_terms( array( '111' => 'post_tag' ), array( 'hide_empty' => false ) );
		$this->assertEquals( $term_id, reset( $terms )->term_id );
	}

	/**
	 * @ticket 13661
	 */
	function test_get_terms_fields() {
		$term_id1 = $this->factory->tag->create( array( 'slug' => 'woo', 'name' => 'WOO!' ) );
		$term_id2 = $this->factory->tag->create( array( 'slug' => 'hoo', 'name' => 'HOO!', 'parent' => $term_id1 ) );

		$terms_id_parent = get_terms( 'post_tag', array( 'hide_empty' => false, 'fields' => 'id=>parent' ) );
		$this->assertEquals( array(
			$term_id1 => 0,
			$term_id2 => $term_id1
		), $terms_id_parent );

		$terms_ids = get_terms( 'post_tag', array( 'hide_empty' => false, 'fields' => 'ids' ) );
		$this->assertEqualSets( array( $term_id1, $term_id2 ), $terms_ids );

		$terms_name = get_terms( 'post_tag', array( 'hide_empty' => false, 'fields' => 'names' ) );
		$this->assertEqualSets( array( 'WOO!', 'HOO!' ), $terms_name );

		$terms_id_name = get_terms( 'post_tag', array( 'hide_empty' => false, 'fields' => 'id=>name' ) );
		$this->assertEquals( array(
			$term_id1 => 'WOO!',
			$term_id2 => 'HOO!',
		), $terms_id_name );

		$terms_id_slug = get_terms( 'post_tag', array( 'hide_empty' => false, 'fields' => 'id=>slug' ) );
		$this->assertEquals( array(
			$term_id1 => 'woo',
			$term_id2 => 'hoo'
		), $terms_id_slug );
	}

 	/**
	 * @ticket 11823
 	 */
	function test_get_terms_include_exclude() {
		global $wpdb;

		$term_id1 = $this->factory->tag->create();
		$term_id2 = $this->factory->tag->create();
		$inc_terms = get_terms( 'post_tag', array(
			'include' => array( $term_id1, $term_id2 ),
			'hide_empty' => false
		) );
		$this->assertEquals( array( $term_id1, $term_id2 ), wp_list_pluck( $inc_terms, 'term_id' ) );

		$exc_terms = get_terms( 'post_tag', array(
			'exclude' => array( $term_id1, $term_id2 ),
			'hide_empty' => false
		) );
		$this->assertEquals( array(), wp_list_pluck( $exc_terms, 'term_id' ) );

		// These should not generate query errors.
		get_terms( 'post_tag', array( 'exclude' => array( 0 ), 'hide_empty' => false ) );
		$this->assertEmpty( $wpdb->last_error );

		get_terms( 'post_tag', array( 'exclude' => array( 'unexpected-string' ), 'hide_empty' => false ) );
		$this->assertEmpty( $wpdb->last_error );

		get_terms( 'post_tag', array( 'include' => array( 'unexpected-string' ), 'hide_empty' => false ) );
		$this->assertEmpty( $wpdb->last_error );
	}

	/**
	 * @ticket 13992
	 */
	function test_get_terms_search() {
		$term_id1 = $this->factory->tag->create( array( 'slug' => 'burrito' ) );
		$term_id2 = $this->factory->tag->create( array( 'name' => 'Wilbur' ) );

		$terms = get_terms( 'post_tag', array( 'hide_empty' => false, 'search' => 'bur', 'fields' => 'ids' ) );
		$this->assertEqualSets( array( $term_id1, $term_id2 ), $terms );
	}

	function test_get_terms_like() {
		$term_id1 = $this->factory->tag->create( array( 'name' => 'burrito', 'description' => 'This is a burrito.' ) );
		$term_id2 = $this->factory->tag->create( array( 'name' => 'taco', 'description' => 'Burning man.' ) );

		$terms = get_terms( 'post_tag', array( 'hide_empty' => false, 'name__like' => 'bur', 'fields' => 'ids' ) );
		$this->assertEqualSets( array( $term_id1 ), $terms );

		$terms2 = get_terms( 'post_tag', array( 'hide_empty' => false, 'description__like' => 'bur', 'fields' => 'ids' ) );
		$this->assertEqualSets( array( $term_id1, $term_id2 ), $terms2 );

		$terms3 = get_terms( 'post_tag', array( 'hide_empty' => false, 'name__like' => 'Bur', 'fields' => 'ids' ) );
		$this->assertEqualSets( array( $term_id1 ), $terms3 );

		$terms4 = get_terms( 'post_tag', array( 'hide_empty' => false, 'description__like' => 'Bur', 'fields' => 'ids' ) );
		$this->assertEqualSets( array( $term_id1, $term_id2 ), $terms4 );

		$terms5 = get_terms( 'post_tag', array( 'hide_empty' => false, 'name__like' => 'ENCHILADA', 'fields' => 'ids' ) );
		$this->assertEmpty( $terms5 );

		$terms6 = get_terms( 'post_tag', array( 'hide_empty' => false, 'description__like' => 'ENCHILADA', 'fields' => 'ids' ) );
		$this->assertEmpty( $terms6 );

		$terms7 = get_terms( 'post_tag', array( 'hide_empty' => false, 'name__like' => 'o', 'fields' => 'ids' ) );
		$this->assertEqualSets( array( $term_id1, $term_id2 ), $terms7 );

		$terms8 = get_terms( 'post_tag', array( 'hide_empty' => false, 'description__like' => '.', 'fields' => 'ids' ) );
		$this->assertEqualSets( array( $term_id1, $term_id2 ), $terms8 );
	}
}
