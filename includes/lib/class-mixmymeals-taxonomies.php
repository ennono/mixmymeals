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

	/**
	 * Taxonomy functions constructor.
	 */
	public function __construct() {

		add_action( 'init', array( $this, 'register_recipe_category_taxonomy' ) );
		add_action( 'init', array( $this, 'register_recipe_tag_taxonomy' ) );
		add_action(
			'init',
			array( $this, 'register_ingredient_category_taxonomy' )
		);
		add_action( 'init', array( $this, 'register_meal_plan_category_taxonomy' ) );
		add_action( 'init', array( $this, 'register_meal_plan_tag_taxonomy' ) );
	}

	/**
	 * Registers hierarchical taxonomy "Recipe Categories" for recipes.
	 */
	public function register_recipe_category_taxonomy(): void {

		$labels = array(
			'name'              => _x( 'Recipe Categories', 'taxonomy general name', 'mixmymeals' ),
			'singular_name'     => _x( 'Recipe Category', 'taxonomy singular name', 'mixmymeals' ),
			'search_items'      => __( 'Search Recipe Categories', 'mixmymeals' ),
			'all_items'         => __( 'All Recipe Categories', 'mixmymeals' ),
			'parent_item'       => __( 'Parent Recipe Category', 'mixmymeals' ),
			'parent_item_colon' => __( 'Parent Recipe Category:', 'mixmymeals' ),
			'edit_item'         => __( 'Edit Recipe Category', 'mixmymeals' ),
			'update_item'       => __( 'Update Recipe Category', 'mixmymeals' ),
			'add_new_item'      => __( 'Add New Recipe Category', 'mixmymeals' ),
			'new_item_name'     => __( 'New Recipe Category Name', 'mixmymeals' ),
			'menu_name'         => __( 'Recipe Categories', 'mixmymeals' ),
		);

		$args = array(
			'labels'            => $labels,
			'public'            => true,
			'publicly_queryable'=> true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'hierarchical'      => true,
			'show_in_rest'      => true,
			'query_var'         => true,
			'rewrite'           => array(
				'slug' => get_option( 'mixmymeals_recipe_category_slug' ),
				'with_front'   => false,
				'hierarchical' => true,
			),
		);

		register_taxonomy(
			'mmm_recipe_category',
			array( 'mmm_recipe' ),
			$args
		);
	}

	/**
	 * Registers non-hierarchical taxonomy "tag" for recipes:
	 */
	public function register_recipe_tag_taxonomy(): void {
		$labels = array(
			'name'                       => _x(
				'Tags',
				'taxonomy general name',
				'mixmymeals'
			),
			'singular_name'              => _x(
				'Tag',
				'taxonomy singular name',
				'mixmymeals'
			),
			'search_items'               => __( 'Search Tags', 'mixmymeals' ),
			'popular_items'              => __( 'Popular Tags', 'mixmymeals' ),
			'all_items'                  => __( 'All Tags', 'mixmymeals' ),
			'edit_item'                  => __( 'Edit Tag', 'mixmymeals' ),
			'update_item'                => __( 'Update Tag', 'mixmymeals' ),
			'add_new_item'               => __( 'Add New Tag', 'mixmymeals' ),
			'new_item_name'              => __( 'New Meal Tag', 'mixmymeals' ),
			'separate_items_with_commas' => __(
				'Separate tags with commas',
				'mixmymeals'
			),
			'add_or_remove_items'        => __(
				'Add or remove tags',
				'mixmymeals'
			),
			'choose_from_most_used'      => __(
				'Choose from the most used',
				'mixmymeals'
			),
			'menu_name'                  => __( 'Tags', 'mixmymeals' ),
		);

		$args = array(
			'labels'       => $labels,
			'public'       => false,
			'show_ui'      => true,
			'hierarchical' => false,
			'show_in_rest' => true,
			'rewrite'      => array(
				'slug'       => __( 'recipe-by-tag', 'mixmymeals' ),
				'with_front' => false,
				'feeds'      => false,
				'pages'      => false,
			),
			'query_var'    => false,
		);

		register_taxonomy( 'mmm_recipe_tag', array( 'mmm_recipe' ), $args );
	}

	/**
	 * Registers hierarchical taxonomy "category" for ingredients:
	 */
	public function register_ingredient_category_taxonomy(): void {
		$labels = array(
			'name'              => _x(
				'Categories',
				'taxonomy general name',
				'mixmymeals'
			),
			'singular_name'     => _x(
				'Category',
				'taxonomy singular name',
				'mixmymeals'
			),
			'search_items'      => __( 'Search Categories', 'mixmymeals' ),
			'all_items'         => __( 'All Categories', 'mixmymeals' ),
			'parent_item'       => __( 'Parent Category', 'mixmymeals' ),
			'parent_item_colon' => __( 'Parent Category:', 'mixmymeals' ),
			'edit_item'         => __( 'Edit Category', 'mixmymeals' ),
			'update_item'       => __( 'Update Category', 'mixmymeals' ),
			'add_new_item'      => __( 'Add New Category', 'mixmymeals' ),
			'new_item_name'     => __( 'New Category Name', 'mixmymeals' ),
			'menu_name'         => __( 'Categories', 'mixmymeals' ),
		);

		$args = array(
			'labels'       => $labels,
			'public'       => false,
			'show_ui'      => true,
			'hierarchical' => false,
			'show_in_rest' => true,
			'rewrite'      => false,
			'query_var'    => false,
		);

		register_taxonomy(
			'mmm_ingredient_category',
			array( 'mmm_ingredient' ),
			$args
		);
	}

	/**
	 * Registers hierarchical taxonomy "category" for meal plans:
	 */
	public function register_meal_plan_category_taxonomy(): void {
		$labels = array(
			'name'              => _x(
				'Categories',
				'taxonomy general name',
				'mixmymeals'
			),
			'singular_name'     => _x(
				'Category',
				'taxonomy singular name',
				'mixmymeals'
			),
			'search_items'      => __( 'Search Categories', 'mixmymeals' ),
			'all_items'         => __( 'All Categories', 'mixmymeals' ),
			'parent_item'       => __( 'Parent Category', 'mixmymeals' ),
			'parent_item_colon' => __( 'Parent Category:', 'mixmymeals' ),
			'edit_item'         => __( 'Edit Category', 'mixmymeals' ),
			'update_item'       => __( 'Update Category', 'mixmymeals' ),
			'add_new_item'      => __( 'Add New Category', 'mixmymeals' ),
			'new_item_name'     => __( 'New Category Name', 'mixmymeals' ),
			'menu_name'         => __( 'Categories', 'mixmymeals' ),
		);

		$args = array(
			'labels'       => $labels,
			'public'       => true,
			'show_ui'      => true,
			'hierarchical' => true,
			'show_in_rest' => true,
			'rewrite'           => array(
				'slug' => get_option( 'mixmymeals_meal_plan_category_slug' ),
				'with_front'   => false,
				'hierarchical' => true,
			),
			'query_var'    => true,
		);

		register_taxonomy(
			'mmm_meal_plan_category',
			array( 'mmm_meal_plan' ),
			$args
		);
	}

	/**
	 * Registers non-hierarchical taxonomy "tag" for meal plans:
	 */
	public function register_meal_plan_tag_taxonomy(): void {
		$labels = array(
			'name'                       => _x(
				'Tags',
				'taxonomy general name',
				'mixmymeals'
			),
			'singular_name'              => _x(
				'Tag',
				'taxonomy singular name',
				'mixmymeals'
			),
			'search_items'               => __( 'Search Tags', 'mixmymeals' ),
			'popular_items'              => __( 'Popular Tags', 'mixmymeals' ),
			'all_items'                  => __( 'All Tags', 'mixmymeals' ),
			'edit_item'                  => __( 'Edit Tag', 'mixmymeals' ),
			'update_item'                => __( 'Update Tag', 'mixmymeals' ),
			'add_new_item'               => __( 'Add New Tag', 'mixmymeals' ),
			'new_item_name'              => __( 'New Meal Tag', 'mixmymeals' ),
			'separate_items_with_commas' => __(
				'Separate tags with commas',
				'mixmymeals'
			),
			'add_or_remove_items'        => __(
				'Add or remove tags',
				'mixmymeals'
			),
			'choose_from_most_used'      => __(
				'Choose from the most used',
				'mixmymeals'
			),
			'menu_name'                  => __( 'Tags', 'mixmymeals' ),
		);

		$args = array(
			'labels'       => $labels,
			'public'       => false,
			'show_ui'      => true,
			'hierarchical' => false,
			'show_in_rest' => true,
			'rewrite'      => array(
				'slug'       => __( 'recipe-by-tag', 'mixmymeals' ),
				'with_front' => false,
				'feeds'      => false,
				'pages'      => false,
			),
			'query_var'    => false,
		);

		register_taxonomy( 'mmm_meal_plan_tag', array( 'mmm_meal_plan' ), $args );
	}

}
