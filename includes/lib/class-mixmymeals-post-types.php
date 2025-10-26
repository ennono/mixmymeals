<?php
/**
 * Post Types file.
 *
 * @package MixMyMeals/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mixmymeals Post Types class.
 */
class Mixmymeals_Post_Types {

	/**
	 * Post types constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_recipe_post_type' ) );
		add_action( 'init', array( $this, 'register_ingredient_post_type' ) );
		add_action( 'init', array( $this, 'register_meal_plan_post_type' ) );
		add_action( 'init', array( $this, 'register_meal_post_type' ) );
	}

	/**
	 * Register recipe post type
	 *
	 * @return void
	 */
	public function register_recipe_post_type() {

		$recipe_labels = array(
			'name'                  => _x( 'Recipes', 'Post type general name', 'mixmymeals' ),
			'singular_name'         => _x( 'Recipe', 'Post type singular name', 'mixmymeals' ),
			'menu_name'             => _x( 'Recipes', 'Admin Menu text', 'mixmymeals' ),
			'name_admin_bar'        => _x( 'Recipe', 'Add New on Toolbar', 'mixmymeals' ),
			'add_new'               => __( 'Add New', 'mixmymeals' ),
			'add_new_item'          => __( 'Add New Recipe', 'mixmymeals' ),
			'new_item'              => __( 'New Recipe', 'mixmymeals' ),
			'edit_item'             => __( 'Edit Recipe', 'mixmymeals' ),
			'view_item'             => __( 'View Recipe', 'mixmymeals' ),
			'all_items'             => __( 'Recipes', 'mixmymeals' ),
			'search_items'          => __( 'Search Recipes', 'mixmymeals' ),
			'parent_item_colon'     => __( 'Parent Recipes:', 'mixmymeals' ),
			'not_found'             => __( 'No recipes found.', 'mixmymeals' ),
			'not_found_in_trash'    => __( 'No recipes found in Trash.', 'mixmymeals' ),
			'featured_image'        => _x( 'Recipe Cover Image', 'Overrides the “Featured Image” phrase.', 'mixmymeals' ),
			'set_featured_image'    => _x( 'Set recipe image', 'Overrides the “Set featured image” phrase.', 'mixmymeals' ),
			'remove_featured_image' => _x( 'Remove recipe image', 'Overrides the “Remove featured image” phrase.', 'mixmymeals' ),
			'use_featured_image'    => _x( 'Use as recipe image', 'Overrides the “Use as featured image” phrase.', 'mixmymeals' ),
			'archives'              => _x( 'Recipe archives', 'The post type archive label.', 'mixmymeals' ),
			'insert_into_item'      => _x( 'Insert into recipe', 'Overrides “Insert into post/page”.', 'mixmymeals' ),
			'uploaded_to_this_item' => _x( 'Uploaded to this recipe', 'Overrides “Uploaded to this post/page”.', 'mixmymeals' ),
			'filter_items_list'     => _x( 'Filter recipes list', 'Screen reader text.', 'mixmymeals' ),
			'items_list_navigation' => _x( 'Recipes list navigation', 'Screen reader text.', 'mixmymeals' ),
			'items_list'            => _x( 'Recipes list', 'Screen reader text.', 'mixmymeals' ),
		);

		$recipe_args = array(
			'labels'              => $recipe_labels,
			'description'         => __( 'Recipe Description', 'mixmymeals' ),
			'public'              => false,
			'publicly_queryable'  => true,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => true,
			'query_var'           => true,
			'can_export'          => false,
			'rewrite'             => false,
			'capability_type'     => 'post',
			'has_archive'         => true,
			'hierarchical'        => false,
			'show_in_rest'        => false,
			'supports'            => array( 'title', 'editor' ),
			'menu_position'       => 30,
			'menu_icon'           => 'dashicons-portfolio',
		);

		register_post_type( 'mixmymeals_recipe', $recipe_args );
	}

	/**
	 * Register ingredient post type
	 *
	 * @return void
	 */
	public function register_ingredient_post_type() {

		$ingredient_labels = array(
			'name'                  => _x( 'Ingredients', 'Post type general name', 'mixmymeals' ),
			'singular_name'         => _x( 'Ingredient', 'Post type singular name', 'mixmymeals' ),
			'menu_name'             => _x( 'Ingredients', 'Admin Menu text', 'mixmymeals' ),
			'name_admin_bar'        => _x( 'Ingredient', 'Add New on Toolbar', 'mixmymeals' ),
			'add_new'               => __( 'Add New', 'mixmymeals' ),
			'add_new_item'          => __( 'Add New Ingredient', 'mixmymeals' ),
			'new_item'              => __( 'New Ingredient', 'mixmymeals' ),
			'edit_item'             => __( 'Edit Ingredient', 'mixmymeals' ),
			'view_item'             => __( 'View Ingredient', 'mixmymeals' ),
			'all_items'             => __( 'Ingredients', 'mixmymeals' ),
			'search_items'          => __( 'Search Ingredients', 'mixmymeals' ),
			'parent_item_colon'     => __( 'Parent Ingredients:', 'mixmymeals' ),
			'not_found'             => __( 'No ingredients found.', 'mixmymeals' ),
			'not_found_in_trash'    => __( 'No ingredients found in Trash.', 'mixmymeals' ),
			'featured_image'        => _x( 'Ingredient Image', 'mixmymeals' ),
			'set_featured_image'    => _x( 'Set ingredient image', 'mixmymeals' ),
			'remove_featured_image' => _x( 'Remove ingredient image', 'mixmymeals' ),
			'use_featured_image'    => _x( 'Use as ingredient image', 'mixmymeals' ),
			'archives'              => _x( 'Ingredient archives', 'mixmymeals' ),
			'insert_into_item'      => _x( 'Insert into ingredient', 'mixmymeals' ),
			'uploaded_to_this_item' => _x( 'Uploaded to this ingredient', 'mixmymeals' ),
			'filter_items_list'     => _x( 'Filter ingredients list', 'mixmymeals' ),
			'items_list_navigation' => _x( 'Ingredients list navigation', 'mixmymeals' ),
			'items_list'            => _x( 'Ingredients list', 'mixmymeals' ),
		);

		$ingredient_args = array(
			'labels'              => $ingredient_labels,
			'description'         => __( 'Ingredient Description', 'mixmymeals' ),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => 'edit.php?post_type=mixmymeals_recipe',
			'show_in_nav_menus'   => false,
			'query_var'           => true,
			'can_export'          => false,
			'rewrite'             => false,
			'capability_type'     => 'post',
			'has_archive'         => false,
			'hierarchical'        => false,
			'show_in_rest'        => false,
			'supports'            => array( 'title', 'editor' ),
			'menu_position'       => 31,
			'menu_icon'           => 'dashicons-carrot',
		);

		register_post_type( 'mmm_ingredient', $ingredient_args );

	}

	/**
	 * Register meal plan post type (for calendar/week plans)
	 */
	public function register_meal_plan_post_type() {
		$labels = [
			'name'          => __( 'Meal Plans', 'mixmymeals' ),
			'singular_name' => __( 'Meal Plan', 'mixmymeals' ),
			'add_new_item'  => __( 'Add New Meal Plan', 'mixmymeals' ),
			'edit_item'     => __( 'Edit Meal Plan', 'mixmymeals' ),
			'view_item'     => __( 'View Meal Plan', 'mixmymeals' ),
			'all_items'     => __( 'Meal Plans', 'mixmymeals' ),
		];

		$args = [
			'labels'       => $labels,
			'public'       => true,
			'show_ui'      => true,
			'show_in_menu' => true,
			'menu_icon'    => 'dashicons-calendar-alt',
			'supports'     => [ 'title', 'editor' ],
			'has_archive'  => false,
			'hierarchical' => false,
		];

		register_post_type( 'mmm_meal_plan', $args );
	}

	/**
	 * Register meal post type (represents a single meal belonging to a plan)
	 */
	public function register_meal_post_type() {
		$labels = [
			'name'          => __( 'Meals', 'mixmymeals' ),
			'singular_name' => __( 'Meal', 'mixmymeals' ),
			'add_new_item'  => __( 'Add New Meal', 'mixmymeals' ),
			'edit_item'     => __( 'Edit Meal', 'mixmymeals' ),
			'view_item'     => __( 'View Meal', 'mixmymeals' ),
			'all_items'     => __( 'Meals', 'mixmymeals' ),
		];

		$args = [
			'labels'              => $labels,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => 'edit.php?post_type=mmm_meal_plan',
			'supports'            => [ 'title' ],
			'has_archive'         => false,
			'hierarchical'        => false,
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'rewrite'             => false,
		];

		register_post_type( 'mmm_meal', $args );
	}

}
