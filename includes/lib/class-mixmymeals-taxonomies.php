<?php
/**
 * Taxonomies class file.
 *
 * @package MixMyMeals/Taxonomies
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mixmymeals Taxonomies class.
 */
class Mixmymeals_Taxonomies {

	public function __construct() {
		add_action( 'init', [ $this, 'register_day_taxonomy' ] );
		add_action( 'init', [ $this, 'register_meal_taxonomy' ] );
	}

	/**
	 * Registers hierarchical taxonomy for scheduling days (e.g., Monday, Tuesday...).
	 */
	public function register_day_taxonomy() {
		$labels = [
			'name'              => _x( 'Days', 'taxonomy general name', 'mixmymeals' ),
			'singular_name'     => _x( 'Day', 'taxonomy singular name', 'mixmymeals' ),
			'search_items'      => __( 'Search Days', 'mixmymeals' ),
			'all_items'         => __( 'All Days', 'mixmymeals' ),
			'parent_item'       => __( 'Parent Day', 'mixmymeals' ),
			'parent_item_colon' => __( 'Parent Day:', 'mixmymeals' ),
			'edit_item'         => __( 'Edit Day', 'mixmymeals' ),
			'update_item'       => __( 'Update Day', 'mixmymeals' ),
			'add_new_item'      => __( 'Add New Day', 'mixmymeals' ),
			'new_item_name'     => __( 'New Day Name', 'mixmymeals' ),
			'menu_name'         => __( 'Days', 'mixmymeals' ),
		];

		$args = [
			'labels'            => $labels,
			'public'            => false,
			'show_ui'           => true,
			'hierarchical'      => true, // z. B. Jahr > Woche > Tag
			'show_in_rest'      => false,
			'rewrite'           => false,
			'query_var'         => false,
		];

		register_taxonomy( 'mmm_day', [ 'mmm_entry' ], $args );
	}

	/**
	 * Registers flat taxonomy for meal types (e.g., breakfast, lunch, dinner).
	 */
	public function register_meal_taxonomy() {
		$labels = [
			'name'                       => _x( 'Meals', 'taxonomy general name', 'mixmymeals' ),
			'singular_name'              => _x( 'Meal', 'taxonomy singular name', 'mixmymeals' ),
			'search_items'               => __( 'Search Meals', 'mixmymeals' ),
			'popular_items'              => __( 'Popular Meals', 'mixmymeals' ),
			'all_items'                  => __( 'All Meals', 'mixmymeals' ),
			'edit_item'                  => __( 'Edit Meal', 'mixmymeals' ),
			'update_item'                => __( 'Update Meal', 'mixmymeals' ),
			'add_new_item'               => __( 'Add New Meal', 'mixmymeals' ),
			'new_item_name'              => __( 'New Meal Name', 'mixmymeals' ),
			'separate_items_with_commas' => __( 'Separate meals with commas', 'mixmymeals' ),
			'add_or_remove_items'        => __( 'Add or remove meals', 'mixmymeals' ),
			'choose_from_most_used'      => __( 'Choose from the most used', 'mixmymeals' ),
			'menu_name'                  => __( 'Meals', 'mixmymeals' ),
		];

		$args = [
			'labels'            => $labels,
			'public'            => false,
			'show_ui'           => true,
			'hierarchical'      => false,
			'show_in_rest'      => false,
			'rewrite'           => false,
			'query_var'         => false,
		];

		register_taxonomy( 'mmm_meal', [ 'mmm_entry' ], $args );
	}
}
