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
		add_action(
			'init',
			array( $this, 'register_equipment_category_taxonomy' )
		);
		add_action( 'init', array( $this, 'register_meal_plan_category_taxonomy' ) );
		add_action( 'init', array( $this, 'register_meal_plan_tag_taxonomy' ) );
		add_action( 'init', array( $this, 'register_author_taxonomy' ) );

		// Diäten leben in ihrer eigenen Klasse, weil dort auch die Regeln
		// stehen, nach denen sie sich zu Rezepten verdichten.
		add_action( 'init', array( 'Mixmymeals_Diet', 'register_taxonomy' ) );
		add_action( 'admin_init', array( 'Mixmymeals_Diet', 'ensure_terms' ) );
	}

	/**
	 * Registers the non-hierarchical taxonomy "Authors", shared by recipes and
	 * meal plans.
	 *
	 * An author used to be seven free-text meta fields repeated on every post,
	 * which meant the same person was re-typed for every contribution and had
	 * nowhere to point to. As a term they exist once and get an archive of
	 * everything they contributed — which is the thing a guest author can link
	 * to from their own site, and the reason this taxonomy is public at all.
	 *
	 * The slug deliberately defaults to the plural: WordPress core owns
	 * "/author/" through its author_base, and registering a taxonomy on the
	 * same base makes one of the two unreachable. The author archive is
     * disable at present.
	 */
	public function register_author_taxonomy(): void {

		$labels = array(
			'name'                       => _x( 'Authors', 'taxonomy general name', 'mixmymeals' ),
			'singular_name'              => _x( 'Author', 'taxonomy singular name', 'mixmymeals' ),
			'search_items'               => __( 'Search Authors', 'mixmymeals' ),
			'popular_items'              => __( 'Frequent Authors', 'mixmymeals' ),
			'all_items'                  => __( 'All Authors', 'mixmymeals' ),
			'edit_item'                  => __( 'Edit Author', 'mixmymeals' ),
			'update_item'                => __( 'Update Author', 'mixmymeals' ),
			'add_new_item'               => __( 'Add New Author', 'mixmymeals' ),
			'new_item_name'              => __( 'New Author Name', 'mixmymeals' ),
			'separate_items_with_commas' => __( 'Separate authors with commas', 'mixmymeals' ),
			'add_or_remove_items'        => __( 'Add or remove authors', 'mixmymeals' ),
			'choose_from_most_used'      => __( 'Choose from the most used', 'mixmymeals' ),
			'not_found'                  => __( 'No authors found.', 'mixmymeals' ),
			'back_to_items'              => __( '← Back to authors', 'mixmymeals' ),
			'menu_name'                  => __( 'Authors', 'mixmymeals' ),
		);

		$slug = trim( (string) get_option( 'mixmymeals_author_slug' ) );

		if ( '' === $slug ) {
			$slug = _x( 'authors', 'author archive URL slug', 'mixmymeals' );
		}

		$args = array(
			'labels'             => $labels,
			//'public'             => true,
			//'publicly_queryable' => true,
			'show_ui'            => true,
			'show_admin_column'  => true,
			'hierarchical'       => false,
			'show_in_rest'       => true,
			//'query_var'          => true,
            'public'                     => true,  // Bleibt im Admin-Bereich sichtbar
            'publicly_queryable'         => false, // DEAKTIVIERT die Frontend-Archiv-URLs
            'query_var'                  => false, // Verhindert URL-Abfragen
            'rewrite'                    => false, // Schaltet URL-Rewrites für die Taxonomie ab
            /*
			'rewrite'            => array(
				'slug'       => $slug,
				'with_front' => false,
				'feeds'      => false,
			),
            */
		);

		register_taxonomy(
			Mixmymeals_Author::TAXONOMY,
			array( 'mmm_recipe', 'mmm_meal_plan' ),
			$args
		);
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
	 * Registers the "category" taxonomy for equipment.
	 *
	 * Mirrors the ingredient category: a grouping taxonomy for the admin and
	 * the archive index, not a public archive of its own. Unlike the
	 * ingredient one this sets show_admin_column, which gives the list table a
	 * clickable, filtering column for free.
	 */
	public function register_equipment_category_taxonomy(): void {
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
			'labels'            => $labels,
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'hierarchical'      => false,
			'show_in_rest'      => true,
			'rewrite'           => false,
			'query_var'         => false,
		);

		register_taxonomy(
			'mmm_equipment_category',
			array( 'mmm_equipment' ),
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
