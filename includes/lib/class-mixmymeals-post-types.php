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
		add_action( 'init', array( $this, 'register_equipment_post_type' ) );
		add_action(
			'init',
			array( $this, 'register_ingredient_amount_post_type' )
		);

		add_filter(
			'manage_mmm_ingredient_posts_columns',
			array( $this, 'ingredients_columns_callback' )
		);
		add_action(
			'manage_mmm_ingredient_posts_custom_column',
			array( $this, 'ingredient_custom_column_callback' ),
			10,
			2
		);
		add_action(
			'restrict_manage_posts',
			array( $this, 'filter_ingredients_by_category' )
		);
		add_action(
			'pre_get_posts',
			array( $this, 'filter_ingredient_query_by_category' )
		);

		add_filter(
			'manage_mmm_recipe_posts_columns',
			array( $this, 'recipe_columns_callback' )
		);
		add_action(
			'manage_mmm_recipe_posts_custom_column',
			array( $this, 'recipe_custom_column_callback' ),
			10,
			2
		);
		add_action(
			'pre_get_posts',
			array( $this, 'add_recipe_column_ordering' )
		);

		add_filter(
			'manage_mmm_meal_plan_posts_columns',
			array( $this, 'mealplan_columns_callback' )
		);
		add_action(
			'manage_mmm_meal_plan_posts_custom_column',
			array( $this, 'mealplan_custom_column_callback' ),
			10,
			2
		);
		add_action(
			'pre_get_posts',
			array( $this, 'add_mealplan_column_ordering' )
		);
	}

	/**
	 * Register recipe post type
	 *
	 * @return void
	 */
	public function register_recipe_post_type(): void {

		$recipe_labels = array(
			'name'                  => _x(
				'Recipes',
				'Post type general name',
				'mixmymeals'
			),
			'singular_name'         => _x(
				'Recipe',
				'Post type singular name',
				'mixmymeals'
			),
			'menu_name'             => _x(
				'Recipes',
				'Admin Menu text',
				'mixmymeals'
			),
			'name_admin_bar'        => _x(
				'Recipe',
				'Add New on Toolbar',
				'mixmymeals'
			),
			'add_new'               => __( 'Add New', 'mixmymeals' ),
			'add_new_item'          => __( 'Add New Recipe', 'mixmymeals' ),
			'new_item'              => __( 'New Recipe', 'mixmymeals' ),
			'edit_item'             => __( 'Edit Recipe', 'mixmymeals' ),
			'view_item'             => __( 'View Recipe', 'mixmymeals' ),
			'all_items'             => __( 'Recipes', 'mixmymeals' ),
			'search_items'          => __( 'Search Recipes', 'mixmymeals' ),
			'parent_item_colon'     => __( 'Parent Recipes:', 'mixmymeals' ),
			'not_found'             => __( 'No recipes found.', 'mixmymeals' ),
			'not_found_in_trash'    => __(
				'No recipes found in Trash.',
				'mixmymeals'
			),
			'featured_image'        => _x(
				'Recipe Cover Image',
				'Overrides the “Featured Image” phrase.',
				'mixmymeals'
			),
			'set_featured_image'    => _x(
				'Set recipe image',
				'Overrides the “Set featured image” phrase.',
				'mixmymeals'
			),
			'remove_featured_image' => _x(
				'Remove recipe image',
				'Overrides the “Remove featured image” phrase.',
				'mixmymeals'
			),
			'use_featured_image'    => _x(
				'Use as recipe image',
				'Overrides the “Use as featured image” phrase.',
				'mixmymeals'
			),
			'archives'              => _x(
				'Recipe archives',
				'The post type archive label.',
				'mixmymeals'
			),
			'insert_into_item'      => _x(
				'Insert into recipe',
				'Overrides “Insert into post/page”.',
				'mixmymeals'
			),
			'uploaded_to_this_item' => _x(
				'Uploaded to this recipe',
				'Overrides “Uploaded to this post/page”.',
				'mixmymeals'
			),
			'filter_items_list'     => _x(
				'Filter recipes list',
				'Screen reader text.',
				'mixmymeals'
			),
			'items_list_navigation' => _x(
				'Recipes list navigation',
				'Screen reader text.',
				'mixmymeals'
			),
			'items_list'            => _x(
				'Recipes list',
				'Screen reader text.',
				'mixmymeals'
			),
		);

		$recipe_args = array(
			'labels'              => $recipe_labels,
			'description'         => __( 'Recipe Description', 'mixmymeals' ),
			'public'              => true,
			'publicly_queryable'  => true,
			'exclude_from_search' => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => true,
			'query_var'           => true,
			'can_export'          => false,
			'rewrite'             => array(
				'slug' => get_option( 'mixmymeals_recipe_slug' ),
				'with_front' => false,
				'feeds'      => false,
				'pages'      => true,
			),
			'capability_type'     => 'post',
			'has_archive'         => true,
			'hierarchical'        => false,
			'show_in_rest'        => true,  // Important for loading Gutenberg which prevents issues with dropping recipe steps on saving without title.
			'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'comments' ),
			'menu_position'       => 30,
			'menu_icon'           => 'dashicons-clipboard',
		);

		register_post_type( 'mmm_recipe', $recipe_args );
	}

	/**
	 * Register ingredient post type
	 *
	 * @return void
	 */
	public function register_ingredient_post_type(): void {

		$ingredient_labels = array(
			'name'                  => _x(
				'Ingredients',
				'Post type general name',
				'mixmymeals'
			),
			'singular_name'         => _x(
				'Ingredient',
				'Post type singular name',
				'mixmymeals'
			),
			'menu_name'             => _x(
				'Ingredients',
				'Admin Menu text',
				'mixmymeals'
			),
			'name_admin_bar'        => _x(
				'Ingredient',
				'Add New on Toolbar',
				'mixmymeals'
			),
			'add_new'               => __( 'Add New', 'mixmymeals' ),
			'add_new_item'          => __( 'Add New Ingredient', 'mixmymeals' ),
			'new_item'              => __( 'New Ingredient', 'mixmymeals' ),
			'edit_item'             => __( 'Edit Ingredient', 'mixmymeals' ),
			'view_item'             => __( 'View Ingredient', 'mixmymeals' ),
			'all_items'             => __( 'Ingredients', 'mixmymeals' ),
			'search_items'          => __( 'Search Ingredients', 'mixmymeals' ),
			'parent_item_colon'     => __(
				'Parent Ingredients:',
				'mixmymeals'
			),
			'not_found'             => __(
				'No ingredients found.',
				'mixmymeals'
			),
			'not_found_in_trash'    => __(
				'No ingredients found in Trash.',
				'mixmymeals'
			),
			'featured_image'        => __( 'Ingredient Image', 'mixmymeals' ),
			'set_featured_image'    => __(
				'Set ingredient image',
				'mixmymeals'
			),
			'remove_featured_image' => __(
				'Remove ingredient image',
				'mixmymeals'
			),
			'use_featured_image'    => __(
				'Use as ingredient image',
				'mixmymeals'
			),
			'archives'              => __(
				'Ingredient archives',
				'mixmymeals'
			),
			'insert_into_item'      => __(
				'Insert into ingredient',
				'mixmymeals'
			),
			'uploaded_to_this_item' => __(
				'Uploaded to this ingredient',
				'mixmymeals'
			),
			'filter_items_list'     => __(
				'Filter ingredients list',
				'mixmymeals'
			),
			'items_list_navigation' => __(
				'Ingredients list navigation',
				'mixmymeals'
			),
			'items_list'            => __( 'Ingredients list', 'mixmymeals' ),
		);

		$ingredient_args = array(
			'labels'              => $ingredient_labels,
			'public'              => true,
			'publicly_queryable'  => true,
			'exclude_from_search' => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => true,
			'query_var'           => true,
			'can_export'          => false,
			'rewrite'             => array(
				'slug'       => get_option( 'mixmymeals_ingredient_slug', 'ingredients' ),
				'with_front' => false,
				'feeds'      => false,
			),
			'capability_type'     => 'post',
			'has_archive'         => true,
			'hierarchical'        => false,
			'show_in_rest'        => true,
			'supports'            => array( 'title', 'editor' ),
			'menu_position'       => 30,
			'menu_icon'           => 'dashicons-carrot',
		);

		register_post_type( 'mmm_ingredient', $ingredient_args );
	}

	/**
	 * Register meal plan post type (for calendar/week plans)
	 */
	public function register_meal_plan_post_type(): void {
		$labels = array(
			'name'          => __( 'Meal Plans', 'mixmymeals' ),
			'singular_name' => __( 'Meal Plan', 'mixmymeals' ),
			'add_new_item'  => __( 'Add New Meal Plan', 'mixmymeals' ),
			'edit_item'     => __( 'Edit Meal Plan', 'mixmymeals' ),
			'view_item'     => __( 'View Meal Plan', 'mixmymeals' ),
			'all_items'     => __( 'Meal Plans', 'mixmymeals' ),
		);

		$args = array(
			'labels'       => $labels,
			'public'       => true,
			'show_ui'      => true,
			'show_in_menu' => true,
			'show_in_rest' => true,
			'menu_icon'    => 'dashicons-calendar-alt',
			'supports'     => array( 'title', 'editor', 'excerpt' ),
			'has_archive'  => true,
			'hierarchical' => false,
			'rewrite'      => array(
				'slug' => get_option( 'mixmymeals_meal_plan_slug' ),
				'with_front' => false,
				'feeds'      => false,
				'pages'      => true,
			),
		);

		register_post_type( 'mmm_meal_plan', $args );
	}

	/**
	 * Register meal post type (represents a single meal belonging to a plan)
	 */
	public function register_meal_post_type(): void {
		$labels = array(
			'name'          => __( 'Meals', 'mixmymeals' ),
			'singular_name' => __( 'Meal', 'mixmymeals' ),
			'add_new_item'  => __( 'Add New Meal', 'mixmymeals' ),
			'edit_item'     => __( 'Edit Meal', 'mixmymeals' ),
			'view_item'     => __( 'View Meal', 'mixmymeals' ),
			'all_items'     => __( 'Meals', 'mixmymeals' ),
		);

		$args = array(
			'labels'          => $labels,
			'public'          => true,
			'show_ui'         => true,
			'show_in_menu'    => 'edit.php?post_type=mmm_meal_plan',
			'show_in_rest'    => true,
			'supports'        => array( 'title' ),
			'has_archive'     => false,
			'hierarchical'    => false,
			'capability_type' => 'post',
			'map_meta_cap'    => true,
			'rewrite'         => array(
				'slug' => get_option( 'mixmymeals_meal_slug' ),
				'with_front' => false,
				'feeds'      => false,
				'pages'      => false,
			),
		);

		register_post_type( 'mmm_meal', $args );
	}

	/**
	 * Register ingredient amount post type (child of ingredient)
	 */
	public function register_ingredient_amount_post_type(): void {
		$labels = array(
			'name'          => __( 'Ingredient Amounts', 'mixmymeals' ),
			'singular_name' => __( 'Ingredient Amount', 'mixmymeals' ),
			'add_new_item'  => __( 'Add New Amount', 'mixmymeals' ),
			'edit_item'     => __( 'Edit Amount', 'mixmymeals' ),
			'all_items'     => __( 'Ingredient Amounts', 'mixmymeals' ),
		);

		$args = array(
			'labels'              => $labels,
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => false,
			'show_in_menu'        => false,
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => false,
			'show_in_rest'        => true,
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'supports'            => array( 'title' ),
			/*
			 * Hierarchical set to true to make post_parent available in REST API so that AI can work with it.
			 */
			'hierarchical'        => true,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
		);

		register_post_type( 'mmm_ingr_unit', $args );
	}

	/**
	 * Register equipment post type
	 */
	public function register_equipment_post_type(): void {

		$labels = array(
			'name'          => __( 'Equipment', 'mixmymeals' ),
			'singular_name' => __( 'Equipment', 'mixmymeals' ),
			'add_new_item'  => __( 'Add New Piece Of Equipment', 'mixmymeals' ),
			'edit_item'     => __( 'Edit Piece Of Equipment', 'mixmymeals' ),
			'all_items'     => __( 'Equipment', 'mixmymeals' ),
		);

		$args = array(
			'labels'              => $labels,
			'public'              => true,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => false,
			'show_in_rest'        => true,
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'supports'            => array( 'title', 'editor', 'thumbnail' ),
			'hierarchical'        => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'menu_icon'           => 'dashicons-hammer',
		);

		register_post_type( 'mmm_equipment', $args );
	}

	/**
	 * Adds nutrition-per-100g columns to the ingredient list table.
	 *
	 * @param array $columns Existing column definitions.
	 * @return array
	 */
	public function ingredients_columns_callback( array $columns ): array {

		$new = array();

		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;

			if ( $key === 'title' ) {
				$new['kcal_100']    = 'kcal / 100g';
				$new['fat_100']     = 'Fat (g)';
				$new['carbs_100']   = 'Carbs (g)';
				$new['protein_100'] = 'Protein (g)';
				$new['sugar_100']   = 'Sugar (g)';
				$new['fiber_100']   = 'Fiber (g)';
				$new['salt_100']    = 'Salt (g)';
			}
		}

		return $new;
	}

	/**
	 * Renders the content for a custom ingredient list table column.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Ingredient post ID.
	 * @return void
	 */
	public function ingredient_custom_column_callback( string $column, int $post_id ): void {

		$ingredient = new Mixmymeals_Ingredient( $post_id );
		$nutri      = $ingredient->get_nutrition();

		switch ( $column ) {

			case 'kcal_100':
				echo esc_html( $nutri['calories_100'] ?? '-' );
				break;

			case 'fat_100':
				echo esc_html( $nutri['fat'] ?? '-' );
				break;

			case 'carbs_100':
				echo esc_html( $nutri['carbs'] ?? '-' );
				break;

			case 'protein_100':
				echo esc_html( $nutri['protein'] ?? '-' );
				break;

			case 'sugar_100':
				echo esc_html( $nutri['sugar'] ?? '-' );
				break;

			case 'fiber_100':
				echo esc_html( $nutri['fiber'] ?? '-' );
				break;

			case 'salt_100':
				echo esc_html( $nutri['salt'] ?? '-' );
				break;
		}
	}

	/**
	 * Adds nutrition and cost columns to the recipe list table.
	 *
	 * @param array $columns Existing column definitions.
	 * @return array
	 */
	public function recipe_columns_callback( array $columns ): array {

		$new = array();

		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;

			if ( $key === 'title' ) {
				$new['servings']    = __( 'Servings', 'mixmymeals' );
				$new['cost_serv']   = sprintf( __( 'Cost / Serving', 'mixmymeals' ) . ' (%s)', esc_html( get_option( 'mixmymeals_currency', '€' ) ) );
				$new['kcal_serv']   = __( 'kcal / Serving', 'mixmymeals' );
				$new['kcal_100']    = __( 'kcal / 100g', 'mixmymeals' );
				$new['protein_100'] = __( 'Protein / 100g', 'mixmymeals' );
				$new['carbs_100']   = __( 'Carbs / 100g', 'mixmymeals' );
				$new['fat_100']     = __( 'Fat / 100g', 'mixmymeals' );
			}
		}

		return $new;
	}


	/**
	 * Renders the content for a custom recipe list table column.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Recipe post ID.
	 * @return void
	 */
	public function recipe_custom_column_callback( string $column, int $post_id ): void {

		$recipe = new Mixmymeals_Recipe( $post_id );

		static $cache = [];

		if ( ! isset( $cache[ $post_id ] ) ) {
			$cache[ $post_id ] = [
				'serving' => $recipe->calculate_nutrition_per_serving(),
				'100g'    => $recipe->calculate_nutrition_per_100g(),
				'cost'    => $recipe->calculate_cost_per_serving(),
			];
		}

		$nut_serv = $cache[ $post_id ]['serving'];
		$nut_100  = $cache[ $post_id ]['100g'];
		$cost     = $cache[ $post_id ]['cost'];

		switch ( $column ) {

			case 'servings':
				echo esc_html( $recipe->get_servings() );
				break;

			case 'cost_serv':
				echo $cost > 0
					? esc_html( number_format( $cost, 2, ',', '.' ) )
					: '–';
				break;

			case 'kcal_serv':
				echo esc_html( round( $nut_serv['calories'] ?? 0, 2 ) );
				break;

			case 'kcal_100':
				echo esc_html( round( $nut_100['calories'] ?? 0, 2 ) );
				break;

			case 'protein_100':
				echo esc_html( round( $nut_100['protein'] ?? 0, 2 ) );
				break;

			case 'carbs_100':
				echo esc_html( round( $nut_100['carbs'] ?? 0, 2 ) );
				break;

			case 'fat_100':
				echo esc_html( round( $nut_100['fat'] ?? 0, 2 ) );
				break;
		}
	}


	/**
	 * Enables numeric ordering for custom recipe list table columns.
	 *
	 * @param WP_Query $query Current admin query.
	 * @return void
	 */
	public function add_recipe_column_ordering( WP_Query $query ): void {

		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( $query->get( 'orderby' ) === 'servings' ) {
			$query->set( 'meta_key', '_mixmymeals_servings' );
			$query->set( 'orderby', 'meta_value_num' );
		}

		if ( $query->get( 'orderby' ) === 'kcal_serv' ) {
			$query->set( 'meta_key', '_mixmymeals_kcal_serving' );
			$query->set( 'orderby', 'meta_value_num' );
		}

		if ( $query->get( 'orderby' ) === 'kcal_100' ) {
			$query->set( 'meta_key', '_mixmymeals_kcal_100' );
			$query->set( 'orderby', 'meta_value_num' );
		}
	}

	/**
	 * Adds days and meal-count columns to the meal plan list table.
	 *
	 * @param array $columns Existing column definitions.
	 * @return array
	 */
	public function mealplan_columns_callback( array $columns ): array {

		$new = array();

		foreach ( $columns as $key => $label ) {

			$new[ $key ] = $label;

			if ( $key === 'title' ) {
				$new['days']        = __( 'Days', 'mixmymeals' );
				$new['meals_count'] = __( 'Meals', 'mixmymeals' );
			}
		}

		return $new;
	}

	/**
	 * Renders the content for a custom meal plan list table column.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Meal plan post ID.
	 * @return void
	 */
	public function mealplan_custom_column_callback( string $column, int $post_id ): void {

		$meal_plan_obj = new Mixmymeals_Meal_Plan( $post_id );

		$days  = absint( $meal_plan_obj->count_days() );
		$meals = absint( $meal_plan_obj->count_meals() );

		switch ( $column ) {

			case 'days':
				echo esc_html( $days ?: '-' );
				break;

			case 'meals_count':
				echo esc_html( $meals ?: '-' );
				break;
		}
	}

	/**
	 * Enables numeric ordering for custom meal plan list table columns.
	 *
	 * @param WP_Query $query Current admin query.
	 * @return void
	 */
	public function add_mealplan_column_ordering( WP_Query $query ): void {

		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$orderby = $query->get( 'orderby' );

		if ( $orderby === 'days' ) {
			$query->set( 'meta_key', '_mmm_plan_days' );
			$query->set( 'orderby', 'meta_value_num' );
		}

		if ( $orderby === 'meals_count' ) {
			$query->set( 'meta_key', '_mmm_plan_meals_count' );
			$query->set( 'orderby', 'meta_value_num' );
		}
	}

	/**
	 * Renders a category filter dropdown above the ingredient list table.
	 *
	 * @return void
	 */
	public function filter_ingredients_by_category(): void {
		global $typenow;

		if ( $typenow === 'mmm_ingredient' ) {
			$taxonomy = 'mmm_ingredient_category';

			wp_nonce_field(
				'mmm_filter_ingredients_action',
				'mmm_filter_ingredients_nonce'
			);

			$selected = '';
			if ( isset( $_GET['mmm_filter_ingredients_nonce'], $_GET[ $taxonomy ] ) ) {
				$nonce
					= sanitize_text_field( wp_unslash( $_GET['mmm_filter_ingredients_nonce'] ) );
				if ( wp_verify_nonce(
					$nonce,
					'mmm_filter_ingredients_action'
				) ) {
					$selected
						= sanitize_text_field( wp_unslash( $_GET[ $taxonomy ] ) );
				}
			}

			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
				)
			);

			echo '<select name="' . esc_attr( $taxonomy ) . '">';
			echo '<option value="">' . esc_html__(
				'All Categories',
				'mixmymeals'
			) . '</option>';
			echo '<option value="0"' . selected( $selected, '0', false ) . '>'
				. esc_html__( 'No Category', 'mixmymeals' ) . '</option>';

			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					echo '<option value="' . esc_attr( $term->term_id ) . '" '
						. selected( $selected, $term->term_id, false ) . '>'
						. esc_html( $term->name ) . '</option>';
				}
			}

			echo '</select>';
		}
	}


	/**
	 * Modifies the ingredient list table query to filter by the selected category.
	 *
	 * @param WP_Query $query Current admin query.
	 * @return void
	 */
	public function filter_ingredient_query_by_category( WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( $query->get( 'post_type' ) !== 'mmm_ingredient' ) {
			return;
		}

		$category = '';

		if ( isset( $_GET['mmm_filter_ingredients_nonce'], $_GET['mmm_ingredient_category'] ) ) {
			$nonce
				= sanitize_text_field( wp_unslash( $_GET['mmm_filter_ingredients_nonce'] ) );
			if ( wp_verify_nonce( $nonce, 'mmm_filter_ingredients_action' ) ) {
				$category
					= sanitize_text_field( wp_unslash( $_GET['mmm_ingredient_category'] ) );
			}
		}

		if ( $category === '0' ) {
			$query->set(
				'tax_query',
				array(
					array(
						'taxonomy' => 'mmm_ingredient_category',
						'operator' => 'NOT EXISTS',
					),
				)
			);
		} elseif ( $category ) {
			$query->set(
				'tax_query',
				array(
					array(
						'taxonomy' => 'mmm_ingredient_category',
						'field'    => 'term_id',
						'terms'    => $category,
					),
				)
			);
		}
	}
}
