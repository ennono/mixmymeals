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
		add_action( 'init', [ $this, 'register_recipe_category_taxonomy' ] );
		add_action( 'init', [ $this, 'register_recipe_tag_taxonomy' ] );
		add_action( 'init', [ $this, 'register_ingredient_category_taxonomy' ] );


	}

	/**
	 * Registers hierarchical taxonomy "category" for recipes:
	 */
	public function register_recipe_category_taxonomy() {
		$labels = [
			'name'              => _x( 'Categories', 'taxonomy general name', 'mixmymeals' ),
			'singular_name'     => _x( 'Category', 'taxonomy singular name', 'mixmymeals' ),
			'search_items'      => __( 'Search Categories', 'mixmymeals' ),
			'all_items'         => __( 'All Categories', 'mixmymeals' ),
			'parent_item'       => __( 'Parent Category', 'mixmymeals' ),
			'parent_item_colon' => __( 'Parent Category:', 'mixmymeals' ),
			'edit_item'         => __( 'Edit Category', 'mixmymeals' ),
			'update_item'       => __( 'Update Category', 'mixmymeals' ),
			'add_new_item'      => __( 'Add New Category', 'mixmymeals' ),
			'new_item_name'     => __( 'New Category Name', 'mixmymeals' ),
			'menu_name'         => __( 'Categories', 'mixmymeals' ),
		];

		$args = [
			'labels'            => $labels,
			'public'            => false,
			'show_ui'           => true,
			'hierarchical'      => true,
			'show_in_rest'      => false,
			'rewrite'           => false,
			'query_var'         => false,
		];

		register_taxonomy( 'mmm_recipe_category', [ 'mmm_recipe' ], $args );
	}

	/**
	 * Registers non-hierarchical taxonomy "tag" for recipes:
	 */
	public function register_recipe_tag_taxonomy() {
		$labels = [
			'name'                       => _x( 'Tags', 'taxonomy general name', 'mixmymeals' ),
			'singular_name'              => _x( 'Tag', 'taxonomy singular name', 'mixmymeals' ),
			'search_items'               => __( 'Search Tags', 'mixmymeals' ),
			'popular_items'              => __( 'Popular Tags', 'mixmymeals' ),
			'all_items'                  => __( 'All Tags', 'mixmymeals' ),
			'edit_item'                  => __( 'Edit Tag', 'mixmymeals' ),
			'update_item'                => __( 'Update Tag', 'mixmymeals' ),
			'add_new_item'               => __( 'Add New Tag', 'mixmymeals' ),
			'new_item_name'              => __( 'New Meal Tag', 'mixmymeals' ),
			'separate_items_with_commas' => __( 'Separate tags with commas', 'mixmymeals' ),
			'add_or_remove_items'        => __( 'Add or remove tags', 'mixmymeals' ),
			'choose_from_most_used'      => __( 'Choose from the most used', 'mixmymeals' ),
			'menu_name'                  => __( 'Tags', 'mixmymeals' ),
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

		register_taxonomy( 'mmm_recipe_tag', [ 'mmm_recipe' ], $args );
	}

	/**
	 * Registers hierarchical taxonomy "category" for ingredients:
	 */
	public function register_ingredient_category_taxonomy() {
		$labels = [
			'name'              => _x( 'Categories', 'taxonomy general name', 'mixmymeals' ),
			'singular_name'     => _x( 'Category', 'taxonomy singular name', 'mixmymeals' ),
			'search_items'      => __( 'Search Categories', 'mixmymeals' ),
			'all_items'         => __( 'All Categories', 'mixmymeals' ),
			'parent_item'       => __( 'Parent Category', 'mixmymeals' ),
			'parent_item_colon' => __( 'Parent Category:', 'mixmymeals' ),
			'edit_item'         => __( 'Edit Category', 'mixmymeals' ),
			'update_item'       => __( 'Update Category', 'mixmymeals' ),
			'add_new_item'      => __( 'Add New Category', 'mixmymeals' ),
			'new_item_name'     => __( 'New Category Name', 'mixmymeals' ),
			'menu_name'         => __( 'Categories', 'mixmymeals' ),
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

		register_taxonomy( 'mmm_recipe_tag', [ 'mmm_ingredient' ], $args );
	}

}
