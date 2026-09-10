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
	 * "Unlisted" flag for single recipes and meal plans. The post keeps its own
	 * public URL and stays indexable, it just disappears from every listing —
	 * archives, taxonomy archives, the search and the browse shortcodes.
	 *
	 * Listed = no meta at all (same idea as the cook counter), so the exclusion
	 * is a cheap NOT EXISTS and existing posts need no migration. The editor UI
	 * lives in Mixmymeals_Post_Meta.
	 *
	 * @var string
	 */
	const META_UNLISTED = '_mmm_unlisted';

	/**
	 * Post types offering the unlisted flag.
	 *
	 * @var string[]
	 */
	const UNLISTED_POST_TYPES = array( 'mmm_recipe', 'mmm_meal_plan' );

	/**
	 * Public post types whose singles receive Open Graph / Twitter tags.
	 *
	 * @var string[]
	 */
	const OG_POST_TYPES = array( 'mmm_recipe', 'mmm_meal_plan', 'mmm_ingredient', 'mmm_equipment' );

	/**
	 * Whether the post is hidden from listings.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_unlisted( int $post_id ): bool {
		return '1' === (string) get_post_meta( $post_id, self::META_UNLISTED, true );
	}

	/**
	 * Sets or clears the flag. Listed posts carry no meta at all.
	 *
	 * @param int  $post_id  Post ID.
	 * @param bool $unlisted Whether to hide the post from listings.
	 * @return void
	 */
	public static function set_unlisted( int $post_id, bool $unlisted ): void {

		if ( $unlisted ) {
			update_post_meta( $post_id, self::META_UNLISTED, '1' );
			return;
		}

		delete_post_meta( $post_id, self::META_UNLISTED );
	}

	/**
	 * The meta_query clause that keeps unlisted posts out of a query.
	 *
	 * @return array
	 */
	public static function unlisted_clause(): array {
		return array(
			'key'     => self::META_UNLISTED,
			'compare' => 'NOT EXISTS',
		);
	}

	/**
	 * Adds the exclusion to a get_posts()/WP_Query argument array, preserving
	 * any meta_query the caller already set. Used by the listing shortcodes,
	 * which run their own queries past the main query.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public static function exclude_unlisted( array $args ): array {

		$existing = isset( $args['meta_query'] ) && is_array( $args['meta_query'] )
			? $args['meta_query']
			: array();

		if ( empty( $existing ) ) {
			$args['meta_query'] = array( self::unlisted_clause() ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			return $args;
		}

		// Wrap the caller's clauses so their own relation keeps working.
		$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'relation' => 'AND',
			$existing,
			self::unlisted_clause(),
		);

		return $args;
	}

	/**
	 * Keeps unlisted posts out of the public listings driven by the main query:
	 * post type archives, taxonomy archives and the site search.
	 *
	 * Singular views are untouched on purpose — an unlisted post stays reachable
	 * under its own URL. Admin and feeds are untouched as well, so the editor
	 * still sees everything.
	 *
	 * @param WP_Query $query Query about to run.
	 * @return void
	 */
	public function filter_unlisted_from_main_query( $query ): void {

		if ( is_admin() || ! $query instanceof WP_Query || ! $query->is_main_query() ) {
			return;
		}

		if ( $query->is_singular() ) {
			return;
		}

		$relevant = $query->is_search()
			|| $query->is_post_type_archive( self::UNLISTED_POST_TYPES )
			|| $query->is_tax(
				array(
					'mmm_recipe_category',
					'mmm_recipe_tag',
					'mmm_meal_plan_category',
					'mmm_meal_plan_tag',
				)
			);

		if ( ! $relevant ) {
			return;
		}

		$meta_query = $query->get( 'meta_query' );
		$meta_query = is_array( $meta_query ) ? $meta_query : array();

		$meta_query[] = self::unlisted_clause();

		$query->set( 'meta_query', $meta_query );
	}

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
		add_action( 'init', array( $this, 'register_product_post_type' ) );

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
		add_filter(
			'manage_mmm_equipment_posts_columns',
			array( $this, 'equipment_columns_callback' )
		);
		add_action(
			'manage_mmm_equipment_posts_custom_column',
			array( $this, 'equipment_custom_column_callback' ),
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

		add_action( 'wp_head', array( $this, 'output_og_tags' ), 6 );
		add_action( 'wp_head', array( $this, 'output_schema' ), 8 );
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
			/*
			 * No description on purpose. WordPress hands this to
			 * get_the_archive_description(), so a placeholder here is printed
			 * on the public archive — "Recipe Description" was appearing under
			 * the heading as "Rezeptbeschreibung". The archive text editors
			 * actually maintain is the all_recipes_description setting.
			 */
			'description'         => '',
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
			'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
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
	 * Registers the hidden post type "Product": a concrete, branded article an
	 * ingredient can be bought as.
	 *
	 * An ingredient is the abstraction — "yoghurt", with values good enough to
	 * plan with. A product is one specific yoghurt on one specific shelf. Both
	 * are needed because the difference is sometimes noise (red cabbage: 25
	 * against 41 kcal, swamped by cooking losses and portion size) and
	 * sometimes the whole story (yoghurt at 0.1% or 10% fat).
	 *
	 * Child of the ingredient via post_parent, like mmm_ingr_unit, and hidden
	 * for the same reason: it is a detail of its parent, not a page.
	 *
	 * @return void
	 */
	public function register_product_post_type(): void {

		$labels = array(
			'name'          => __( 'Products', 'mixmymeals' ),
			'singular_name' => __( 'Product', 'mixmymeals' ),
			'add_new_item'  => __( 'Add New Product', 'mixmymeals' ),
			'edit_item'     => __( 'Edit Product', 'mixmymeals' ),
			'all_items'     => __( 'Products', 'mixmymeals' ),
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
			// Hierarchical so post_parent travels over REST, as for units.
			'hierarchical'        => true,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
		);

		register_post_type( Mixmymeals_Product::POST_TYPE, $args );
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
	 * Adds a recipe-count column to the equipment list table. The category
	 * column comes from the taxonomy itself (show_admin_column).
	 *
	 * @param array $columns Existing column definitions.
	 * @return array
	 */
	public function equipment_columns_callback( array $columns ): array {

		$new = array();

		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;

			if ( 'title' === $key ) {
				$new['mmm_recipes'] = __( 'Recipes', 'mixmymeals' );
			}
		}

		return $new;
	}

	/**
	 * Renders the content for a custom equipment list table column.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Equipment post ID.
	 * @return void
	 */
	public function equipment_custom_column_callback( string $column, int $post_id ): void {

		if ( 'mmm_recipes' !== $column ) {
			return;
		}

		$equipment = new Mixmymeals_Equipment( $post_id );
		$count     = $equipment->count_recipes();

		if ( 0 === $count ) {
			echo '—';
			return;
		}

		echo esc_html( number_format_i18n( $count ) );
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
	/* ---------------------------------------------------------------------
	 * Head output: structured data and social meta tags
	 *
	 * The structures themselves are built by the post types that own them
	 * (Mixmymeals_Recipe::get_schema(), Mixmymeals_Meal_Plan::get_schema());
	 * printing them is a per-post-type frontend concern and lives here.
	 * ------------------------------------------------------------------ */

	/**
	 * Prints the JSON-LD block for the current single view: a full
	 * schema.org/Recipe on recipes, an ItemList of its recipes on meal plans.
	 *
	 * @return void
	 */
	public function output_schema(): void {

		/**
		 * Allows disabling the MixMyMeals structured data output.
		 *
		 * @param bool $enabled Default true.
		 */
		if ( ! apply_filters( 'mixmymeals_seo_schema_enabled', true ) ) {
			return;
		}

		if ( is_singular( 'mmm_recipe' ) ) {
			$schema = ( new Mixmymeals_Recipe( (int) get_queried_object_id() ) )->get_schema();
		} elseif ( is_singular( 'mmm_meal_plan' ) ) {
			$schema = ( new Mixmymeals_Meal_Plan( (int) get_queried_object_id() ) )->get_schema();
		} else {
			return;
		}

		if ( empty( $schema ) ) {
			return;
		}

		echo '<script type="application/ld+json">'
			. wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
			. '</script>' . "\n";
	}

	/**
	 * Prints OG + Twitter meta tags on MixMyMeals singles. Skipped when a
	 * dedicated SEO plugin is active (those handle social tags themselves).
	 *
	 * @return void
	 */
	public function output_og_tags(): void {

		if ( ! is_singular( self::OG_POST_TYPES ) ) {
			return;
		}

		// A dedicated SEO plugin outputs its own social tags — never double up.
		$seo_plugin_active = defined( 'WPSEO_VERSION' )       // Yoast.
			|| defined( 'RANK_MATH_VERSION' )                 // Rank Math.
			|| defined( 'AIOSEO_VERSION' )                    // All in One SEO.
			|| defined( 'SLIM_SEO_VER' );                     // Slim SEO.

		/**
		 * Allows forcing the OG output on or off.
		 *
		 * @param bool $enabled Default: only when no SEO plugin is active.
		 */
		if ( ! apply_filters( 'mixmymeals_seo_og_enabled', ! $seo_plugin_active ) ) {
			return;
		}

		$post_id     = (int) get_queried_object_id();
		$title       = get_the_title( $post_id );
		$description = self::meta_description( $post_id );
		$url         = get_permalink( $post_id );

		printf( '<meta property="og:locale" content="%s" />' . "\n", esc_attr( get_locale() ) );
		printf( '<meta property="og:type" content="article" />' . "\n" );
		printf( '<meta property="og:site_name" content="%s" />' . "\n", esc_attr( get_bloginfo( 'name' ) ) );
		printf( '<meta property="og:title" content="%s" />' . "\n", esc_attr( $title ) );
		if ( '' !== $description ) {
			printf( '<meta property="og:description" content="%s" />' . "\n", esc_attr( $description ) );
		}
		printf( '<meta property="og:url" content="%s" />' . "\n", esc_url( $url ) );

		$image_id = self::social_image_id( $post_id );
		if ( $image_id ) {
			$src = wp_get_attachment_image_src( $image_id, 'large' );
			if ( $src ) {
				printf( '<meta property="og:image" content="%s" />' . "\n", esc_url( $src[0] ) );
				printf( '<meta property="og:image:width" content="%d" />' . "\n", (int) $src[1] );
				printf( '<meta property="og:image:height" content="%d" />' . "\n", (int) $src[2] );
				$alt = (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true );
				if ( '' !== $alt ) {
					printf( '<meta property="og:image:alt" content="%s" />' . "\n", esc_attr( $alt ) );
				}
			}
		}

		printf( '<meta name="twitter:card" content="%s" />' . "\n", $image_id ? 'summary_large_image' : 'summary' );
		printf( '<meta name="twitter:title" content="%s" />' . "\n", esc_attr( $title ) );
		if ( '' !== $description ) {
			printf( '<meta name="twitter:description" content="%s" />' . "\n", esc_attr( $description ) );
		}
	}

	/**
	 * Plain-text description of a post: excerpt first, then trimmed content.
	 * Feeds the OG/Twitter tags and the schema descriptions.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	/**
	 * The storage conditions an ingredient or a dish can be kept under.
	 *
	 * A closed list on purpose, and deliberately **not** a taxonomy. A
	 * taxonomy would let editors invent "Kühlschrank", "kühl", "kalt" and
	 * "Kühlung" side by side, and this is advice about keeping food safe —
	 * four spellings of one condition is worse than none. There is also
	 * nothing to browse here: nobody arrives wanting every recipe that is
	 * stored frozen.
	 *
	 * Defined once and shared, because recipes and ingredients answer the same
	 * question from the same vocabulary even though they store separate
	 * answers. Two copies would drift the day somebody adds a fifth condition.
	 *
	 * @return array<string,string> Value => translated label.
	 */
	public static function storage_types(): array {

		return array(
			'room_temperature' => __( 'Room temperature', 'mixmymeals' ),
			'cool_dry'         => __( 'Cool and dry', 'mixmymeals' ),
			'refrigerated'     => __( 'Refrigerated', 'mixmymeals' ),
			'frozen'           => __( 'Frozen', 'mixmymeals' ),
		);
	}

	/**
	 * The same conditions as a phrase that can stand inside a sentence.
	 *
	 * A second list rather than a clever transformation of the first, because
	 * the two are grammatically different words: a select needs the noun
	 * ("Kühlschrank"), a sentence needs the prepositional phrase ("im
	 * Kühlschrank"), and no rule turns one into the other across languages —
	 * "cool and dry" takes no preposition at all, "room temperature" takes a
	 * different one from "fridge".
	 *
	 * Every phrase is written to sit **mid-sentence**, never at the start.
	 * German capitalises the first word of a sentence, so a template that
	 * opened with the phrase would print "im Kühlschrank aufbewahren." with a
	 * small i, and no amount of CSS fixes a wrong letter.
	 *
	 * @return array<string,string> Value => translated phrase.
	 */
	public static function storage_phrases(): array {

		return array(
			'room_temperature' => __( 'at room temperature', 'mixmymeals' ),
			'cool_dry'         => __( 'in a cool, dry place', 'mixmymeals' ),
			'refrigerated'     => __( 'in the fridge', 'mixmymeals' ),
			'frozen'           => __( 'in the freezer', 'mixmymeals' ),
		);
	}

	/**
	 * Whether a value is one of the known storage conditions.
	 *
	 * @param string $value Candidate.
	 * @return bool
	 */
	public static function is_storage_type( string $value ): bool {

		return array_key_exists( $value, self::storage_types() );
	}

	public static function meta_description( int $post_id ): string {

		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}

		$text = trim( wp_strip_all_tags( strip_shortcodes( $post->post_excerpt ) ) );
		if ( '' === $text ) {
			$text = trim( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ) );
		}
		$text = preg_replace( '/\s+/u', ' ', $text );

		return wp_html_excerpt( (string) $text, 300, '…' );
	}

	/**
	 * The post's featured image ID, falling back to the configured
	 * placeholder image.
	 *
	 * @param int $post_id Post ID.
	 * @return int Attachment ID, or 0.
	 */
	public static function social_image_id( int $post_id ): int {

		$thumb = (int) get_post_thumbnail_id( $post_id );
		if ( $thumb ) {
			return $thumb;
		}

		return absint( get_option( 'mixmymeals_placeholder_image_id', 0 ) );
	}

	/**
	 * Full-size and large image URLs for the schema image property.
	 *
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	public static function schema_image_urls( int $post_id ): array {

		$image_id = self::social_image_id( $post_id );
		if ( ! $image_id ) {
			return array();
		}

		$urls = array();
		foreach ( array( 'full', 'large', 'medium_large' ) as $size ) {
			$src = wp_get_attachment_image_src( $image_id, $size );
			if ( $src && ! in_array( $src[0], $urls, true ) ) {
				$urls[] = $src[0];
			}
		}

		return $urls;
	}
}
