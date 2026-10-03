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
		add_action( 'admin_menu', array( $this, 'add_author_submenu' ) );

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

		/*
		 * Das öffentliche Autorenarchiv ist abgeschaltet: `public` bleibt true,
		 * damit die Begriffe in wp-admin erscheinen, aber `publicly_queryable`,
		 * `query_var` und `rewrite` nehmen ihnen die Frontend-Adresse. Die
		 * Option `mixmymeals_author_slug` wird hier deshalb gar nicht gelesen;
		 * sie bleibt bestehen und ist der erste Griff, wenn das Archiv wieder
		 * angehen soll (siehe CLAUDE.md, Abschnitt Autorenbox). Vorher stand
		 * der Slug als berechnete, aber nirgends benutzte Variable da.
		 *
		 * `show_in_menu` ist false, weil die Taxonomie an *zwei* Beitragstypen
		 * hängt und WordPress sie dann unter beiden Menüs auflistet: dieselbe
		 * Begriffsliste einmal unter Rezepte und einmal unter Essenspläne, mit
		 * demselben Inhalt und zwei Wegen dorthin. Der eine Eintrag, den es
		 * geben soll, wird in add_author_submenu() von Hand gesetzt.
		 */
		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => false,
			'query_var'          => false,
			'rewrite'            => false,
			'show_ui'            => true,
			'show_in_menu'       => false,
			'show_admin_column'  => true,
			'hierarchical'       => false,
			'show_in_rest'       => true,
		);

		register_taxonomy(
			Mixmymeals_Author::TAXONOMY,
			array( 'mmm_recipe', 'mmm_meal_plan' ),
			$args
		);
	}

	/**
	 * Puts Authors in the menu exactly once, under Recipes.
	 *
	 * The taxonomy belongs to recipes and to meal plans, so WordPress would
	 * list it under both — one list of people, reachable two ways, which reads
	 * as two different lists until you open them. Recipes is the one that gets
	 * it: that is where guest contributions are written.
	 *
	 * The screen itself is unchanged; this only adds the link. Its
	 * `post_type=mmm_recipe` is what keeps the Recipes menu highlighted while
	 * the screen is open.
	 *
	 * @return void
	 */
	public function add_author_submenu(): void {

		$taxonomy = get_taxonomy( Mixmymeals_Author::TAXONOMY );

		if ( ! $taxonomy ) {
			return;
		}

		add_submenu_page(
			'edit.php?post_type=mmm_recipe',
			$taxonomy->labels->name,
			$taxonomy->labels->menu_name,
			$taxonomy->cap->manage_terms,
			'edit-tags.php?taxonomy=' . Mixmymeals_Author::TAXONOMY . '&post_type=mmm_recipe',
			'',
			20
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
			/*
			 * Nicht „Kategorien“ wie überall sonst: seit die Küchengeräte im
			 * Rezeptmenü hängen, stünde der Eintrag dort neben den
			 * Rezeptkategorien, und zwei gleich benannte Punkte in einem Menü
			 * sind keine Benennung mehr. Der Menüname trägt den Unterschied,
			 * die übrigen Beschriftungen bleiben, wo sie unmissverständlich
			 * sind — auf dem Bearbeitungsbildschirm der Taxonomie selbst.
			 */
			'menu_name'         => __( 'Equipment categories', 'mixmymeals' ),
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
