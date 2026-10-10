<?php
/**
 * Settings class file.
 *
 * @package MixMyMeals Plugin/Settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings class.
 */
class Mixmymeals_Settings {

	/**
	 * The single instance of Mixmymeals_Settings.
	 *
	 * @var     object
	 * @access  private
	 */
	private static $_instance = null; //phpcs:ignore

	/**
	 * The main plugin object.
	 *
	 * @var     object
	 * @access  public
	 */
	public $parent = null;

	/**
	 * Prefix for plugin settings.
	 *
	 * @var     string
	 * @access  public
	 */
	public $base = '';

	/**
	 * Available settings for plugin.
	 *
	 * @var     array
	 * @access  public
	 */
	public $settings = array();

	/**
	 * Tab keys that no longer exist, mapped to the tab their fields went to.
	 *
	 * A bookmark or a link from an older add-on still carries the old key.
	 * Without this it opened a settings screen with no fields at all, since
	 * register_settings() registers only the tab it is asked for.
	 *
	 * @var array<string, string>
	 */
	const TAB_ALIASES = array(
		'mixmymeals-equipment'     => 'mixmymeals-recipes',
		'mixmymeals-visitors'      => 'mixmymeals-meal-plans',
		'mixmymeals-subscriptions' => 'mixmymeals-email',
		'mixmymeals-chips'         => 'mixmymeals-language',
	);

	/**
	 * The order of the entries in each of the plugin's menus, top to bottom.
	 *
	 * Nothing else decides it. WordPress files the entries in the order they
	 * happen to be registered: core adds a post type's own items and its
	 * taxonomies before `admin_menu` runs, a post type hung under another
	 * menu arrives early in that hook, plugin pages after it. That is how
	 * Subscriptions came to stand above Settings in the MixMyMeals menu —
	 * and since a top-level item links to its *first* entry, clicking
	 * "MixMyMeals" opened the subscription list instead of the settings.
	 *
	 * Per menu: the screens you work in first (list, add new), then what
	 * sorts them (categories, tags, diets), then the smaller things that
	 * belong to the subject — meal photos under meal plans, where their
	 * approval setting lives too. In the plugin's own menu, Settings first
	 * because the menu is named after it, then Subscriptions.
	 *
	 * Slugs are compared with `&amp;` decoded, because core registers the
	 * taxonomy entries encoded and add_submenu_page() takes them as given.
	 * An entry not listed here — another plugin's, say — keeps its place
	 * relative to the other unlisted ones, below the listed.
	 *
	 * @var array<string, string[]>
	 */
	const SUBMENU_ORDER = array(
		'edit.php?post_type=mmm_recipe'    => array(
			'edit.php?post_type=mmm_recipe',
			'post-new.php?post_type=mmm_recipe',
			'edit-tags.php?taxonomy=mmm_recipe_category&post_type=mmm_recipe',
			'edit-tags.php?taxonomy=mmm_recipe_tag&post_type=mmm_recipe',
			'edit-tags.php?taxonomy=mmm_author&post_type=mmm_recipe',
			'edit.php?post_type=mmm_equipment',
			'edit-tags.php?taxonomy=mmm_equipment_category&post_type=mmm_equipment',
		),
		'edit.php?post_type=mmm_ingredient' => array(
			'edit.php?post_type=mmm_ingredient',
			'post-new.php?post_type=mmm_ingredient',
			'edit-tags.php?taxonomy=mmm_ingredient_category&post_type=mmm_ingredient',
			'edit-tags.php?taxonomy=mmm_diet&post_type=mmm_ingredient',
		),
		'edit.php?post_type=mmm_meal_plan' => array(
			'edit.php?post_type=mmm_meal_plan',
			'post-new.php?post_type=mmm_meal_plan',
			'edit-tags.php?taxonomy=mmm_meal_plan_category&post_type=mmm_meal_plan',
			'edit-tags.php?taxonomy=mmm_meal_plan_tag&post_type=mmm_meal_plan',
			'mixmymeals_photos',
		),
		'mixmymeals_settings'              => array(
			'mixmymeals_settings',
			'edit.php?post_type=mmm_subscription',
		),
	);

	/**
	 * Constructor function.
	 *
	 * @param   object $parent  Parent object.
	 */
	public function __construct( object $parent ) {
		$this->parent = $parent;

		$this->base = 'mixmymeals_';

		// Initialise settings.
		add_action( 'init', array( $this, 'init_settings' ), 11 );

		// Register plugin settings.
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Flush rewrite rules after the ingredient slug changes, so the new
		// archive/single URLs work without manually re-saving permalinks.
		add_action( 'update_option_mixmymeals_ingredient_slug', array( $this, 'schedule_rewrite_flush' ) );
		add_action( 'add_option_mixmymeals_ingredient_slug', array( $this, 'schedule_rewrite_flush' ) );
		add_action( 'update_option_mixmymeals_equipment_slug', array( $this, 'schedule_rewrite_flush' ) );
		add_action( 'add_option_mixmymeals_equipment_slug', array( $this, 'schedule_rewrite_flush' ) );
		add_action( 'update_option_mixmymeals_author_slug', array( $this, 'schedule_rewrite_flush' ) );
		add_action( 'add_option_mixmymeals_author_slug', array( $this, 'schedule_rewrite_flush' ) );
		add_action( 'init', array( $this, 'maybe_flush_rewrite' ), 99 );

		// Handle the "send test e-mail" button on the E-Mail tab.
		add_action( 'wp_ajax_mmm_send_test_email', array( $this, 'ajax_send_test_email' ) );

		// Handle the "clear send log" button on the E-Mail tab.
		add_action( 'wp_ajax_mmm_clear_mail_log', array( $this, 'ajax_clear_mail_log' ) );

		// Add settings page to menu.
		add_action( 'admin_menu', array( $this, 'add_menu_item' ) );

		// Put every entry of the plugin's menus where SUBMENU_ORDER says,
		// after everything (post types, taxonomies, other plugins) has added
		// its own.
		add_action( 'admin_menu', array( $this, 'order_submenus' ), PHP_INT_MAX );

		// Add settings link to plugins page.
		add_filter(
			'plugin_action_links_' . plugin_basename( $this->parent->file ),
			array(
				$this,
				'add_settings_link',
			)
		);

		// Configure placement of plugin settings page. See readme for implementation.
		add_filter(
			$this->base . 'menu_settings',
			array( $this, 'configure_settings' )
		);
	}

	/**
	 * Flags that rewrite rules need flushing on the next init (after the post
	 * types have been registered with the new slug).
	 *
	 * @return void
	 */
	public function schedule_rewrite_flush(): void {
		update_option( 'mixmymeals_flush_rewrite_rules', 1 );
	}

	/**
	 * Flushes rewrite rules once when flagged. Runs late on init so the post
	 * types (and their current slugs) are already registered.
	 *
	 * @return void
	 */
	public function maybe_flush_rewrite(): void {
		if ( get_option( 'mixmymeals_flush_rewrite_rules' ) ) {
			flush_rewrite_rules( false );
			delete_option( 'mixmymeals_flush_rewrite_rules' );
		}
	}

	/**
	 * Main Mixmymeals_Settings Instance
	 *
	 * Ensures only one instance of Mixmymeals_Settings is loaded or can be loaded.
	 *
	 * @param   object $parent  Object instance.
	 *
	 * @return object Mixmymeals_Settings instance
	 * @static
	 */
	public static function instance( object $parent ): static {

		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $parent );
		}

		return self::$_instance;
	}

	/**
	 * Initialise settings
	 *
	 * @return void
	 */
	public function init_settings(): void {
		$this->settings = $this->settings_fields();
	}

	/**
	 * Build settings fields
	 *
	 * @return array Fields to be displayed on settings page
	 */
	private function settings_fields(): array {

		/*
		 * Die Reiter folgen dem Menü, und ihre Reihenfolge ist die
		 * Reihenfolge dieser Zuweisungen.
		 *
		 * Zuerst Allgemein, dann je ein Reiter für Rezepte, Zutaten und
		 * Essenspläne — dieselben drei Namen, dieselbe Reihenfolge wie die
		 * Hauptpunkte in der Seitenleiste, und was im Menü unter einem von
		 * ihnen hängt, wird auch in dessen Reiter eingestellt: Küchengeräte
		 * und Autoren unter Rezepte, Mahlzeiten unter Essenspläne. Danach die
		 * Chip-Texte, die von allen dreien handeln, dann was Besucher
		 * betrifft (Besucher, Abos), zuletzt der Versand (E-Mail). Add-ons
		 * hängen sich über den Filter am Ende an.
		 *
		 * Vorher gab es einen eigenen Reiter Küchengeräte mit drei Feldern,
		 * obwohl die Geräte im Menü unter Rezepte stehen, und unter Allgemein
		 * die Slugs für Mahlzeiten und Autoren — Felder, die laut eigener
		 * Beschreibung „zu keinem Reiter gehörten“. Sie gehören zu dem
		 * Menüpunkt, unter dem man ihre Inhalte findet. Eine Option bleibt
		 * dabei, wo sie war: register_setting() nutzt eine Gruppe, und der
		 * Name ist `mixmymeals_<id>`, egal in welchem Reiter das Feld steht.
		 */
		$settings['mixmymeals-general'] = array(
			'title'       => __( 'General', 'mixmymeals' ),
			'description' => __( 'Currency, placeholder image, scheduled cleanup and what happens on uninstall. Recipes, ingredients, meal plans, visitors and e-mail each have their own tab.',
				'mixmymeals' ),
			'fields'      => array(
				array(
					'id'    => 'general_group_display',
					'type'  => 'heading',
					'label' => __( 'Display', 'mixmymeals' ),
				),
				array(
					'id'          => 'currency',
					'label'       => __( 'Currency', 'mixmymeals' ),
					'description' => __( 'Currency symbol used for price display.', 'mixmymeals' ),
					'default'     => '€',
					'placeholder' => '€',
					'callback'    => array( $this, 'text_callback' ),
				),
				array(
					'id'          => 'placeholder_image_id',
					'label'       => __( 'Placeholder Image', 'mixmymeals' ),
					'description' => __( 'Used only as a fallback for social link previews and Instagram slide backgrounds. On the website, recipes without a photo are shown cookbook-style with their title only.',
						'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array(
						$this,
						'placeholder_image_id_callback'
					),
				),
				array(
					'id'    => 'general_group_cleanup',
					'type'  => 'heading',
					'label' => __( 'Cleanup', 'mixmymeals' ),
				),
				array(
					'id'          => 'enable_cleanup',
					'label'       => __( 'Enable Cleanup', 'mixmymeals' ),
					'description' => __( 'Automatically delete orphaned meals and old meal plans without a category once per day.',
						'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array(
						$this,
						'enable_cleanup_callback'
					),
				),
				array(
					'id'          => 'cleanup_min_age_days',
					'label'       => __( 'Cleanup Minimum Age', 'mixmymeals' ),
					'description' => __( 'Only delete items older than this many days.',
						'mixmymeals' ),
					'default'     => 30,
					'placeholder' => '30',
					'callback'    => array(
						$this,
						'cleanup_min_age_days_callback'
					),
				),
				array(
					'id'          => 'cleanup_report_enabled',
					'label'       => __( 'Cleanup Report', 'mixmymeals' ),
					'description' => __( 'E-mail a summary after each cleanup run, including deletions made by the mail log, the Instagram image cleanup and the subscription retention (only when something was deleted).',
						'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array(
						$this,
						'cleanup_report_enabled_callback'
					),
				),
				array(
					'id'          => 'cleanup_report_recipient',
					'label'       => __( 'Report Recipient', 'mixmymeals' ),
					'description' => __( 'Address the cleanup report is sent to. Leave empty to use the site admin e-mail.',
						'mixmymeals' ),
					'default'     => '',
					'placeholder' => get_option( 'admin_email' ),
					'callback'    => array(
						$this,
						'cleanup_report_recipient_callback'
					),
				),
				array(
					'id'    => 'general_group_privacy',
					'type'  => 'heading',
					'label' => __( 'Privacy', 'mixmymeals' ),
				),
				array(
					'id'          => 'delete_data_on_uninstall',
					'label'       => __( 'Delete data on uninstall', 'mixmymeals' ),
					'description' => __( 'When the plugin is deleted, also remove personal data (newsletter subscriptions, ratings and visitor photos). Recipes and plans are always kept. Off by default so an accidental deletion does not destroy records.', 'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array( $this, 'checkbox_callback' ),
				),
			),
		);

		$settings['mixmymeals-recipes'] = array(
			'title'       => __( 'Recipes', 'mixmymeals' ),
			'description' => __( 'Everything listed under Recipes in the menu: recipe addresses and archive, the cooking steps, authors and equipment.',
				'mixmymeals' ),
			'fields'      => array(
				array(
					'id'    => 'recipes_group_slugs',
					'type'  => 'heading',
					'label' => __( 'URL Slugs', 'mixmymeals' ),
				),
				array(
					'id'          => 'recipe_slug',
					'label'       => __( 'Recipe Slug', 'mixmymeals' ),
					'description' => __( 'Set the prefix for recipes in the URL.',
						'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array(
						$this,
						'slug_callback'
					),
				),
				array(
					'id'          => 'recipe_category_slug',
					'label'       => __( 'Recipe Category Slug', 'mixmymeals' ),
					'description' => __( 'Set the prefix for recipe categories in the URL.',
						'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array(
						$this,
						'slug_callback'
					),
				),
				array(
					'id'          => 'author_slug',
					'label'       => __( 'Author Slug', 'mixmymeals' ),
					'description' => __( 'Set the prefix for author archives in the URL. Must not be "author" — WordPress uses that for its own user archives. Authors currently have no public pages, so this takes effect only once they do.',
						'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array(
						$this,
						'slug_callback'
					),
				),
				array(
					'id'          => 'equipment_slug',
					'label'       => __( 'Equipment Slug', 'mixmymeals' ),
					'description' => __( 'Set the prefix for equipment in the URL. Changing it flushes the permalinks.',
						'mixmymeals' ),
					'default'     => 'equipment',
					'placeholder' => 'equipment',
					'callback'    => array(
						$this,
						'slug_callback'
					),
				),
				array(
					'id'    => 'recipes_group_archive',
					'type'  => 'heading',
					'label' => __( 'Archive & Display', 'mixmymeals' ),
				),
				array(
					'id'          => 'recipe_disclaimer_text',
					'label'       => __( 'Recipe Disclaimer', 'mixmymeals' ),
					'description' => __( 'Write a disclaimer for your recipes.',
						'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array(
						$this,
						'recipe_disclaimer_text_callback'
					),
				),
				array(
					'id'          => 'recipe_disclaimer_on_ingredients',
					'label'       => __( 'Also show on ingredient pages', 'mixmymeals' ),
					'description' => __( 'Same text, in a Note section as on recipe pages.', 'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array(
						$this,
						'recipe_disclaimer_on_ingredients_callback',
					),
				),
				array(
					'id'          => 'all_recipes_description',
					'label'       => __( 'Recipe Archive Description', 'mixmymeals' ),
					'description' => __( 'This description will be used by the corresponding shortcode.',
						'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array(
						$this,
						'archive_description_callback'
					),
				),
				array(
					'id'    => 'recipes_group_steps',
					'type'  => 'heading',
					'label' => __( 'Cooking Steps', 'mixmymeals' ),
				),
				array(
					'id'          => 'show_recipe_steps',
					'label'       => __( 'Steps on Recipe Pages', 'mixmymeals' ),
					'description' => __( 'Show the cooking steps on the recipe page. When switched off, only the note below is shown there and the steps remain available on the meal pages.',
						'mixmymeals' ),
					'default'     => 'on',
					'placeholder' => '',
					'callback'    => array(
						$this,
						'checkbox_callback'
					),
				),
				array(
					'id'          => 'recipe_steps_notice',
					'label'       => __( 'Note Instead of Steps', 'mixmymeals' ),
					'description' => __( 'Shown on the recipe page in place of the steps while “Steps on Recipe Pages” is switched off. Leave empty to output nothing at all.',
						'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array(
						$this,
						'recipe_steps_notice_callback'
					),
				),
				array(
					'id'    => 'recipes_group_ratings',
					'type'  => 'heading',
					'label' => __( 'Ratings', 'mixmymeals' ),
				),
				array(
					'id'          => 'notice_rating',
					'label'       => __( 'Rating / review notice', 'mixmymeals' ),
					'description' => __( 'Shown on the rating and review form on recipe pages (e.g. a note that reviews are moderated). Leave empty to hide.', 'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array( $this, 'recipe_disclaimer_text_callback' ),
				),
				array(
					'id'    => 'recipes_group_equipment',
					'type'  => 'heading',
					'label' => __( 'Equipment', 'mixmymeals' ),
				),
				array(
					'id'          => 'all_equipment_description',
					'label'       => __( 'Equipment Archive Description', 'mixmymeals' ),
					'description' => __( 'This description will be used by the corresponding shortcode.',
						'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array(
						$this,
						'archive_description_callback'
					),
				),
				array(
					'id'          => 'affiliate_disclosure',
					'label'       => __( 'Affiliate disclosure', 'mixmymeals' ),
					'description' => __( 'Shown below the shop links on equipment pages. Affiliate links must be labelled as advertising; leave empty to use the built-in wording.', 'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array( $this, 'recipe_disclaimer_text_callback' ),
				),
				...$this->nutrition_table_fields( array( 'recipe' ) ),
			),
		);

		$settings['mixmymeals-ingredients'] = array(
			'title'       => __( 'Ingredients', 'mixmymeals' ),
			'description' => __( 'Ingredient URL slug, archive display and the nutrition traffic light.', 'mixmymeals' ),
			'fields'      => array(
				array(
					'id'    => 'ingredients_group_slugs',
					'type'  => 'heading',
					'label' => __( 'URL Slugs', 'mixmymeals' ),
				),
				array(
					'id'          => 'ingredient_slug',
					'label'       => __( 'Ingredient Slug', 'mixmymeals' ),
					'description' => __( 'Set the prefix for ingredients in the URL.',
						'mixmymeals' ),
					'default'     => 'ingredients',
					'placeholder' => 'ingredients',
					'callback'    => array(
						$this,
						'slug_callback'
					),
				),
				array(
					'id'    => 'ingredients_group_archive',
					'type'  => 'heading',
					'label' => __( 'Archive', 'mixmymeals' ),
				),
				array(
					'id'          => 'all_ingredients_description',
					'label'       => __( 'Ingredient Archive Description', 'mixmymeals' ),
					'description' => __( 'This description will be used by the corresponding shortcode.',
						'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array(
						$this,
						'archive_description_callback'
					),
				),
				...$this->nutrition_table_fields( array( 'ingredient' ) ),
				...$this->traffic_light_fields(),
			),
		);

		$settings['mixmymeals-meal-plans'] = array(
			'title'       => __( 'Meal Plans', 'mixmymeals' ),
			'description' => __( 'Plan and meal addresses, the planner visitors build their own plans in, and what a plan page shows.',
				'mixmymeals' ),
			'fields'      => array(
				array(
					'id'    => 'mealplans_group_slugs',
					'type'  => 'heading',
					'label' => __( 'URL Slugs', 'mixmymeals' ),
				),
				array(
					'id'          => 'meal_plan_slug',
					'label'       => __( 'Meal Plan Slug', 'mixmymeals' ),
					'description' => __( 'Set the prefix for meal plans in the URL.',
						'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array(
						$this,
						'slug_callback'
					),
				),
				array(
					'id'          => 'meal_plan_category_slug',
					'label'       => __( 'Meal Plan Category Slug', 'mixmymeals' ),
					'description' => __( 'Set the prefix for meal plan categories in the URL.',
						'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array(
						$this,
						'slug_callback'
					),
				),
				array(
					'id'          => 'meal_slug',
					'label'       => __( 'Meal Slug', 'mixmymeals' ),
					'description' => __( 'Set the prefix for meals in the URL.',
						'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array(
						$this,
						'slug_callback'
					),
				),
				array(
					'id'    => 'mealplans_group_planner',
					'type'  => 'heading',
					'label' => __( 'Planner', 'mixmymeals' ),
				),
				array(
					'id'          => 'max_items_in_cart',
					'label'       => __( 'Max. meals in planner', 'mixmymeals' ),
					'description' => __( 'How many meals should be allowed in the planner?',
						'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array(
						$this,
						'max_items_in_cart_callback'
					),
				),
				array(
					'id'          => 'planner_templates_require_category',
					'label'       => __( 'Plans offered as templates', 'mixmymeals' ),
					'description' => __( 'Only offer plans that have a category. Plans a visitor built carry none, so this keeps them out.',
						'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array(
						$this,
						'planner_templates_require_category_callback'
					),
				),
				array(
					'id'          => 'individual_tab_label',
					'label'       => __( 'Individual Tab Label', 'mixmymeals' ),
					'description' => __( 'Label for the "Individual" tab in the meal plan switcher.',
						'mixmymeals' ),
					'default'     => '',
					'placeholder' => __( 'Individual', 'mixmymeals' ),
					'callback'    => array(
						$this,
						'text_callback'
					),
				),
				array(
					'id'    => 'mealplans_group_archive',
					'type'  => 'heading',
					'label' => __( 'Archive Descriptions', 'mixmymeals' ),
				),
				array(
					'id'          => 'all_meal_plans_description',
					'label'       => __( 'Meal Plan Archive Description', 'mixmymeals' ),
					'description' => __( 'This description will be used by the corresponding shortcode.',
						'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array(
						$this,
						'archive_description_callback'
					),
				),
				array(
					'id'          => 'individual_meal_plans_description',
					'label'       => __( 'Individual Meal Plans Description', 'mixmymeals' ),
					'description' => __( 'This description will be used by the corresponding shortcode.',
						'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array(
						$this,
						'archive_description_callback'
					),
				),
				array(
					'id'    => 'mealplans_group_plan_page',
					'type'  => 'heading',
					'label' => __( 'Plan Page', 'mixmymeals' ),
				),
				array(
					'id'          => 'plan_undated_meals',
					'label'       => __( 'Meals Without a Day', 'mixmymeals' ),
					'description' => __( 'Show meals that have no weekday assigned in a group of their own, below the days. Without this they appear nowhere on the plan page, although they still count towards the shopping list and the nutrition figures.',
						'mixmymeals' ),
					'default'     => 'on',
					'placeholder' => '',
					'callback'    => array(
						$this,
						'plan_undated_meals_callback'
					),
				),
				array(
					'id'          => 'plan_undated_meals_label',
					'label'       => __( 'Heading for Those Meals', 'mixmymeals' ),
					'description' => __( 'What that group is called. Leave empty for the default.',
						'mixmymeals' ),
					'default'     => '',
					'placeholder' => __( 'No fixed day', 'mixmymeals' ),
					'callback'    => array(
						$this,
						'text_callback'
					),
				),
				array(
					'id'    => 'mealplans_group_thumbs',
					'type'  => 'heading',
					'label' => __( 'Recipe Thumbnails', 'mixmymeals' ),
				),
				array(
					'id'          => 'plan_thumbs_mode',
					'label'       => __( 'Thumbnail Selection', 'mixmymeals' ),
					'description' => __( 'Which recipe pictures the thumbnail strip of a meal plan shows. Only recipes with a featured image are used, and no recipe appears twice.',
						'mixmymeals' ),
					'default'     => Mixmymeals_Meal_Plan::THUMBS_MODE_DEFAULT,
					'placeholder' => '',
					'callback'    => array(
						$this,
						'plan_thumbs_mode_callback'
					),
				),
				array(
					'id'          => 'plan_thumbs_max',
					'label'       => __( 'Max. Thumbnails', 'mixmymeals' ),
					'description' => __( 'How many pictures the strip shows at most. 0 shows all of them.',
						'mixmymeals' ),
					'default'     => Mixmymeals_Meal_Plan::THUMBS_MAX_DEFAULT,
					'placeholder' => (string) Mixmymeals_Meal_Plan::THUMBS_MAX_DEFAULT,
					'callback'    => array(
						$this,
						'plan_thumbs_max_callback'
					),
				),
				array(
					'id'    => 'mealplans_group_visitor_plans',
					'type'  => 'heading',
					'label' => __( 'Plans built by visitors', 'mixmymeals' ),
				),
				array(
					'id'          => 'public_mode',
					'label'       => __( 'Public Mode', 'mixmymeals' ),
					'description' => __( 'Allow every visitor to publish plans.', 'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array(
						$this,
						'public_mode_callback'
					),
				),
				array(
					'id'          => 'published_meal_plans_per_hour_limit',
					'label'       => __( 'Meal Plans per Hour', 'mixmymeals' ),
					'description' => __( 'How many plans should a visitor be allowed to publish per hour?', 'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array(
						$this,
						'published_meal_plans_per_hour_limit_callback'
					),
				),
				array(
					'id'          => 'save_meal_plans_published_by_visitors_as_draft',
					'label'       => __( 'Set visitor plans to draft', 'mixmymeals' ),
					'description' => __( 'Save visitor plans as drafts.', 'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array(
						$this,
						'save_meal_plans_published_by_visitors_as_draft_callback'
					),
				),
				array(
					'id'          => 'page_id_to_redirect_after_plan_created',
					'label'       => __( 'Success Page', 'mixmymeals' ),
					'description' => __( 'Where to send visitors after their new plan has been published. The plan\'s ID is appended as ?mmm_plan=, so the page can link to it. Leave unset to open the plan itself.',
						'mixmymeals' ),
					'default'     => '',
					'placeholder' => __( '— The new plan —', 'mixmymeals' ),
					'callback'    => array(
						$this,
						'page_id_to_redirect_after_plan_created_callback'
					),
				),
				array(
					'id'          => 'page_id_to_redirect_if_new_plan_is_draft',
					'label'       => __( 'Alternative Redirect', 'mixmymeals' ),
					'description' => __( 'Where to send visitors after their new plan has been saved as a draft.', 'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array(
						$this,
						'page_id_to_redirect_if_new_plan_is_draft_callback'
					),
				),
				array(
					'id'          => 'notice_plan',
					'label'       => __( 'Plan creation notice', 'mixmymeals' ),
					'description' => __( 'Shown next to the “Save Plan” button (visitor plan creation). Leave empty to hide.', 'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array( $this, 'recipe_disclaimer_text_callback' ),
				),
				array(
					'id'    => 'mealplans_group_subscribe_on_save',
					'type'  => 'heading',
					'label' => __( 'Subscribe while saving', 'mixmymeals' ),
				),
				array(
					'id'          => 'plan_subscribe_mode',
					'label'       => __( 'Subscription when saving', 'mixmymeals' ),
					'description' => __( 'Puts an e-mail field beside the Save Plan button. “Possible”: the plan saves with or without an address. “Required”: the plan only saves with one — note that consent made a condition of saving can count as not freely given under GDPR Art. 7(4). Double opt-in applies either way. Has no effect while subscriptions are off or have no sending rule (E-Mail & Subscriptions tab).', 'mixmymeals' ),
					'default'     => Mixmymeals_Subscriptions::SAVE_OFFER_OFF,
					'placeholder' => '',
					'callback'    => array(
						$this,
						'plan_subscribe_mode_callback'
					),
				),
				array(
					'id'          => 'plan_subscribe_notice',
					'label'       => __( 'Notice text', 'mixmymeals' ),
					'description' => __( 'Shown under the e-mail field. Links are allowed (<a href="…">). Leave empty for the standard consent sentence, which links the privacy page set in the E-Mail & Subscriptions tab.', 'mixmymeals' ),
					'default'     => '',
					'placeholder' => wp_strip_all_tags( Mixmymeals_Subscriptions::consent_text() ),
					'callback'    => array(
						$this,
						'archive_description_callback'
					),
				),
				array(
					'id'          => 'plan_subscribe_notice_checkbox',
					'label'       => __( 'Notice as checkbox', 'mixmymeals' ),
					'description' => __( 'Show the notice as a checkbox that must be ticked whenever an address is entered. Unticked, it is a plain note and entering the address is consent enough.', 'mixmymeals' ),
					'default'     => 'on',
					'placeholder' => '',
					'callback'    => array(
						$this,
						'public_mode_callback'
					),
				),
				array(
					'id'    => 'mealplans_group_photos',
					'type'  => 'heading',
					'label' => __( 'Meal photos', 'mixmymeals' ),
				),
				array(
					'id'          => 'photos_auto_approve',
					'label'       => __( 'Auto-Approve Meal Photos', 'mixmymeals' ),
					'description' => __(
						'Publish uploaded meal photos immediately, without an admin approving them first.',
						'mixmymeals'
					),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array( $this, 'photos_auto_approve_callback' ),
				),
				array(
					'id'          => 'notice_photo',
					'label'       => __( 'Photo upload notice', 'mixmymeals' ),
					'description' => __( 'Shown on the meal-photo upload form (e.g. that you grant usage rights and photos are reviewed). Leave empty to hide.', 'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array( $this, 'recipe_disclaimer_text_callback' ),
				),
				...$this->nutrition_table_fields( array( 'meal', 'plan' ) ),
			),
		);

		/*
		 * E-Mail und Abos in einem Reiter: Ein Abo ist nichts anderes als eine Folge
		 * von Mails, und der Absender, die Fußzeile und die Aufbewahrung gelten
		 * für genau diese Mails. Getrennt musste man für eine Frage — was
		 * bekommt ein Abonnent, und wie lange bleiben seine Daten — zwei Reiter
		 * öffnen. Der Schlüssel bleibt mixmymeals-email, weil der Testmail-Kasten
		 * und das Sendeprotokoll daran hängen; mixmymeals-subscriptions leitet
		 * TAB_ALIASES hierher um.
		 */
		$settings['mixmymeals-email'] = array(
			'title'       => __( 'E-Mail & Subscriptions', 'mixmymeals' ),
			'description' => __( 'Who the plugin\'s e-mails come from, the meal-plan subscriptions they deliver (double opt-in) and how long their data is kept.',
				'mixmymeals' ),
			'fields'      => array(
				array(
					'id'    => 'email_group_sender',
					'type'  => 'heading',
					'label' => __( 'Sender Identity', 'mixmymeals' ),
				),
				array(
					'id'          => 'mail_from_name',
					'label'       => __( 'Sender Name', 'mixmymeals' ),
					'description' => __( 'Name shown as the e-mail sender. Defaults to the site title.',
						'mixmymeals' ),
					'default'     => '',
					'placeholder' => get_bloginfo( 'name' ),
					'callback'    => array( $this, 'text_callback' ),
				),
				array(
					'id'          => 'mail_from_email',
					'label'       => __( 'Sender Address', 'mixmymeals' ),
					'description' => __( 'From address for outgoing e-mails. Leave empty to use no-reply@your-domain.',
						'mixmymeals' ),
					'default'     => '',
					'placeholder' => 'no-reply@example.com',
					'callback'    => array(
						$this,
						'mail_from_email_callback'
					),
				),
				array(
					'id'    => 'subs_group_activation',
					'type'  => 'heading',
					'label' => __( 'Activation', 'mixmymeals' ),
				),
				array(
					'id'          => 'subscriptions_enabled',
					'label'       => __( 'Enable Subscriptions', 'mixmymeals' ),
					'description' => __( 'Allow visitors to subscribe to meal plans and run the sending schedule.',
						'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array( $this, 'subscriptions_enabled_callback' ),
				),
				array(
					'id'          => 'subscription_schedule',
					'label'       => __( 'Sending Rules', 'mixmymeals' ),
					'description' => __( 'Each rule combines a trigger (when to send), a subject and an e-mail template. Triggers are relative to the subscribed plan; templates share the same placeholders (e.g. {{ greeting }}, {{ plan_url }}, {{ meal_list_html }}, {{ shopping_list_html }}).', 'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array( $this, 'subscription_schedule_callback' ),
				),
				array(
					'id'          => 'notice_subscribe',
					'label'       => __( 'Subscription notice', 'mixmymeals' ),
					'description' => __( 'Shown on the meal-plan subscription form, below the consent checkbox. Leave empty to hide.', 'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array( $this, 'recipe_disclaimer_text_callback' ),
				),
				array(
					'id'    => 'subs_group_pages',
					'type'  => 'heading',
					'label' => __( 'Pages & Redirects', 'mixmymeals' ),
				),
				array(
					'id'          => 'subscription_privacy_url',
					'label'       => __( 'Privacy Policy URL', 'mixmymeals' ),
					'description' => __( 'Linked from the consent checkbox on the subscribe form.',
						'mixmymeals' ),
					'default'     => '',
					'placeholder' => 'https://…',
					'callback'    => array( $this, 'subscription_privacy_url_callback' ),
				),
				array(
					'id'          => 'subscription_subscribe_page',
					'label'       => __( 'Redirect After Subscribing', 'mixmymeals' ),
					'description' => __(
						'Page to redirect to after the subscribe form is submitted. Leave empty to show an inline confirmation message instead.',
						'mixmymeals'
					),
					'default'     => 0,
					'placeholder' => '',
					'callback'    => array( $this, 'subscription_subscribe_page_callback' ),
				),
				array(
					'id'          => 'subscription_confirm_page',
					'label'       => __( 'Redirect After Confirmation', 'mixmymeals' ),
					'description' => __(
						'Page to redirect to after a subscriber confirms via the e-mail link. Leave empty to show the built-in confirmation page.',
						'mixmymeals'
					),
					'default'     => 0,
					'placeholder' => '',
					'callback'    => array( $this, 'subscription_confirm_page_callback' ),
				),
				array(
					'id'          => 'subscription_unsubscribe_page',
					'label'       => __( 'Redirect After Unsubscribing', 'mixmymeals' ),
					'description' => __(
						'Page to redirect to after a subscriber unsubscribes via the e-mail link. Leave empty to show the built-in confirmation page.',
						'mixmymeals'
					),
					'default'     => 0,
					'placeholder' => '',
					'callback'    => array( $this, 'subscription_unsubscribe_page_callback' ),
				),
				array(
					'id'          => 'subscription_rate_page',
					'label'       => __( 'Recipe Rating Page', 'mixmymeals' ),
					'description' => __(
						'Page that contains the [mmm_recipe_rating_form] shortcode. The "rate this recipe" links in the e-mails point here. Leave empty to omit those links.',
						'mixmymeals'
					),
					'default'     => 0,
					'placeholder' => '',
					'callback'    => array( $this, 'subscription_rate_page_callback' ),
				),
				array(
					'id'          => 'subscription_upload_page',
					'label'       => __( 'Photo Upload Page', 'mixmymeals' ),
					'description' => __(
						'Page that contains the [mmm_photo_upload_form] shortcode. The "upload your photo" links in the e-mails and the gallery upload button point here. Leave empty to omit those links.',
						'mixmymeals'
					),
					'default'     => 0,
					'placeholder' => '',
					'callback'    => array( $this, 'subscription_upload_page_callback' ),
				),
				array(
					'id'    => 'subs_group_content',
					'type'  => 'heading',
					'label' => __( 'E-Mail Content', 'mixmymeals' ),
				),
				array(
					'id'          => 'mail_footer',
					'label'       => __( 'E-Mail Footer', 'mixmymeals' ),
					'description' => __( 'Footer added to e-mails (e.g. imprint, data controller, opt-out note). HTML allowed.',
						'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array(
						$this,
						'archive_description_callback'
					),
				),
				array(
					'id'          => 'subscription_append_text',
					'label'       => __( 'Appended Text Block', 'mixmymeals' ),
					'description' => __( 'HTML or plain text. Each sending rule whose “Append” box is ticked adds this block to the end of its e-mail.', 'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array( $this, 'subscription_append_text_callback' ),
				),
				array(
					'id'    => 'email_group_privacy',
					'type'  => 'heading',
					'label' => __( 'Privacy & retention', 'mixmymeals' ),
				),
				array(
					'id'          => 'mail_suppression_list',
					'label'       => __( 'Suppression List', 'mixmymeals' ),
					'description' => __( 'Opted-out addresses, one per line. These never receive non-transactional e-mails.',
						'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array(
						$this,
						'mail_suppression_list_callback'
					),
				),
				array(
					'id'          => 'mail_log_enabled',
					'label'       => __( 'Send Log', 'mixmymeals' ),
					'description' => __( 'Keep a log of sent e-mails (recipient, subject, status — no message content).',
						'mixmymeals' ),
					'default'     => 'on',
					'placeholder' => '',
					'callback'    => array(
						$this,
						'mail_log_enabled_callback'
					),
				),
				array(
					'id'          => 'subscription_pending_hours',
					'label'       => __( 'Confirmation Window (hours)', 'mixmymeals' ),
					'description' => __( 'Unconfirmed subscriptions are deleted after this many hours.',
						'mixmymeals' ),
					'default'     => 48,
					'placeholder' => '48',
					'callback'    => array( $this, 'subscription_pending_hours_callback' ),
				),
				array(
					'id'          => 'subscription_retention_days',
					'label'       => __( 'Retention (days)', 'mixmymeals' ),
					'description' => __( 'Completed/unsubscribed records are deleted after this many days.',
						'mixmymeals' ),
					'default'     => 365,
					'placeholder' => '365',
					'callback'    => array( $this, 'subscription_retention_days_callback' ),
				),
				array(
					'id'          => 'subscription_log_ip',
					'label'       => __( 'Store Consent IP', 'mixmymeals' ),
					'description' => __( 'Also store the IP address as part of the consent record (optional).',
						'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array( $this, 'subscription_log_ip_callback' ),
				),
			),
		);

		/*
		 * Kein Formular, nur Text: der Vorschlag für den MixMyMeals-Abschnitt
		 * der Datenschutzerklärung, erzeugt aus den Einstellungen der anderen
		 * Reiter (Mixmymeals_Privacy::policy_section_html()). Er ersetzt die
		 * von Hand gepflegte datenschutzerklaerung-mixmymeals.txt, die bei
		 * jeder geänderten Frist hinterherlief. Steht hinter E-Mail & Abos,
		 * weil er überwiegend beschreibt, was dort eingestellt ist.
		 */
		$settings['mixmymeals-privacy'] = array(
			'title'       => __( 'Privacy Policy', 'mixmymeals' ),
			'description' => __( 'A suggestion for the MixMyMeals section of your privacy policy, generated from the settings in the other tabs. It changes by itself when they do — check your published policy after changing a retention period or switching a feature on or off.', 'mixmymeals' ),
			'fields'      => array(
				array(
					'id'          => 'disable_wp_emoji',
					'label'       => __( 'WordPress emoji', 'mixmymeals' ),
					'description' => __( 'Do not load WordPress\'s emoji script and emoji images from s.w.org (WordPress Foundation, USA). Browsers show emoji themselves; switched off, the site makes no request to a third party for them and the privacy policy needs no section on it.', 'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array( $this, 'public_mode_callback' ),
				),
			),
		);

		$settings['mixmymeals-language'] = $this->chip_text_fields();


		// _token is 'mixmymeals', so the hook is mixmymeals_settings_fields —
		// the sniff simply cannot resolve the property.
		// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- resolves to mixmymeals_settings_fields.
		return apply_filters(
			$this->parent->_token . '_settings_fields',
			$settings
		);
		// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
	}

	/**
	 * The nutrition-table fields of one tab: a heading and one field per
	 * subject, each choosing the columns, their headings and the traffic light.
	 *
	 * The tab follows the menu the subject sits under — ingredients, recipes,
	 * and meals and plans under Meal Plans — so the setting is where the table
	 * is looked for.
	 *
	 * @param string[] $contexts Subjects, keys of Mixmymeals_Nutrition_Table::contexts().
	 * @return array[]
	 */
	private function nutrition_table_fields( array $contexts ): array {

		$labels = Mixmymeals_Nutrition_Table::contexts();
		$fields = array(
			array(
				'id'          => 'nutrition_table_group_' . implode( '_', $contexts ),
				'type'        => 'heading',
				'label'       => __( 'Nutrition tables', 'mixmymeals' ),
				'description' => __( 'Choose which columns the table shows and what they are called. Leave a heading empty for the default wording. Style the table in your child theme through .mmm-nutri-table.', 'mixmymeals' ),
			),
		);

		foreach ( $contexts as $context ) {

			$fields[] = array(
				'id'          => Mixmymeals_Nutrition_Table::FIELD_PREFIX . $context,
				'label'       => $labels[ $context ],
				'description' => '',
				'context'     => $context,
				'callback'    => static fn( $input ) => Mixmymeals_Nutrition_Table::sanitize( $context, $input ),
			);
		}

		return $fields;
	}

	/**
	 * The nutrition traffic light: one field per figure, each a pair of
	 * thresholds per 100 g.
	 *
	 * On the Ingredients tab because the light rates a figure per 100 g, which
	 * is what an ingredient states; recipes, meals and plans are rated the
	 * same way once their figures are brought to 100 g.
	 *
	 * @return array[]
	 */
	private function traffic_light_fields(): array {

		$fields = array(
			array(
				'id'          => 'ingredients_group_traffic_light',
				'type'        => 'heading',
				'label'       => __( 'Nutrition traffic light', 'mixmymeals' ),
				'description' => __( 'Rates each figure per 100 g as low, medium or high — on ingredients, recipes, meals and plans alike. Up to "low" counts as low, above "high" as high. Leave both fields of a figure empty to show no light for it. The defaults are the UK Food Standards Agency\'s values for foods.', 'mixmymeals' ),
			),
		);

		foreach ( Mixmymeals_Meal_Plan::NUTRITION_KEYS as $key ) {

			$default = Mixmymeals_Ingredient::TRAFFIC_LIGHT_DEFAULTS[ $key ] ?? array( '', '' );

			$fields[] = array(
				'id'          => 'traffic_light_' . $key,
				'label'       => Mixmymeals_Ingredient::nutrition_label( $key ),
				'description' => 'target' === Mixmymeals_Meal_Plan::nutrition_direction( $key )
					? __( 'Lower guide: a high amount is green, a low one red.', 'mixmymeals' )
					: __( 'A low amount is green, a high one red.', 'mixmymeals' ),
				'unit'        => 'calories' === $key ? __( 'kcal', 'mixmymeals' ) : __( 'g', 'mixmymeals' ),
				'default'     => array(
					'low'  => '' === $default[0] ? '' : (string) $default[0],
					'high' => '' === $default[1] ? '' : (string) $default[1],
				),
				'callback'    => array( Mixmymeals_Ingredient::class, 'sanitize_traffic_light' ),
			);
		}

		return $fields;
	}

	/**
	 * The "Chip texts" tab: one field per chip wording.
	 *
	 * Built from Mixmymeals_Shortcodes::chip_texts() rather than written out
	 * here, because the chips themselves are rendered there and the two would
	 * otherwise have to be kept in step by hand — a field for a chip that no
	 * longer exists, or a chip nobody can reword, is exactly what that costs.
	 *
	 * The sanitiser is a closure per field: register_setting() hands a callback
	 * only the value, so the key has to be carried in.
	 *
	 * @return array{title:string, description:string, fields:array<int, array<string, mixed>>}
	 */
	private function chip_text_fields(): array {

		/*
		 * Der Reiter heisst „Sprache“ und die Chips sind eine Sektion darin:
		 * Es geht hier um Wortlaut, nicht um eine Funktion, und die Chips sind
		 * nur der erste Satz an Texten, den eine Seite anders sagen will. Die
		 * Sektion ist eine Ebene über den Gruppen (level 2), damit
		 * „Rezepte und Mahlzeiten“ usw. als ihre Unterpunkte lesbar bleiben.
		 */
		$fields = array(
			array(
				'id'          => Mixmymeals_Shortcodes::CHIP_TEXT_FIELD_PREFIX . 'section',
				'type'        => 'heading',
				'level'       => 2,
				'label'       => __( 'Chips', 'mixmymeals' ),
				'description' => __( 'The small labels on recipes, meals, plans and archive cards. Leave a field empty for the wording of the site language. Placeholders in curly braces are filled in; a pattern that drops the value the chip exists to state is ignored.', 'mixmymeals' ),
			),
		);

		foreach ( Mixmymeals_Shortcodes::chip_text_groups() as $group => $heading ) {

			$in_group = array_filter(
				Mixmymeals_Shortcodes::chip_texts(),
				static fn( $entry ) => ( $entry['group'] ?? '' ) === $group
			);

			if ( empty( $in_group ) ) {
				continue;
			}

			$fields[] = array(
				'id'    => Mixmymeals_Shortcodes::CHIP_TEXT_FIELD_PREFIX . 'group_' . $group,
				'type'  => 'heading',
				'label' => $heading,
			);

			foreach ( $in_group as $key => $entry ) {
				$fields[] = array(
					'id'          => Mixmymeals_Shortcodes::CHIP_TEXT_FIELD_PREFIX . $key,
					'label'       => $entry['label'],
					'description' => Mixmymeals_Shortcodes::chip_text_description( $entry ),
					'default'     => '',
					// The default wording, so an empty field shows what it
					// would produce rather than looking unset.
					'placeholder' => (string) $entry['default'],
					'callback'    => static fn( $input ) => Mixmymeals_Shortcodes::sanitize_chip_text( $key, $input ),
				);
			}
		}

		return array(
			'title'       => __( 'Language', 'mixmymeals' ),
			'description' => __( 'How the site words things. Each text here replaces the wording that comes with the site language.', 'mixmymeals' ),
			'fields'      => $fields,
		);
	}

	/**
	 * Add settings page to admin menu
	 *
	 * @return void
	 */
	public function add_menu_item(): void {

		$args = $this->menu_settings();

		// Do nothing if wrong location key is set.
		if ( is_array( $args ) && isset( $args['location'] )
		     && function_exists( 'add_' . $args['location'] . '_page' )
		) {
			switch ( $args['location'] ) {
				case 'options':
				case 'submenu':
					$page = add_submenu_page(
						$args['parent_slug'],
						$args['page_title'],
						$args['menu_title'],
						$args['capability'],
						$args['menu_slug'],
						$args['function']
					);
					break;
				case 'menu':
					$page = add_menu_page(
						$args['page_title'],
						$args['menu_title'],
						$args['capability'],
						$args['menu_slug'],
						$args['function'],
						$args['icon_url'],
						$args['position']
					);

					/*
					 * WordPress legt zu jedem Hauptpunkt selbst einen ersten
					 * Unterpunkt an und gibt ihm den Menütitel — hier also
					 * „MixMyMeals › MixMyMeals“. Derselbe Aufruf mit demselben
					 * Slug überschreibt dessen Beschriftung, und darunter
					 * hängen sich die Abos ein.
					 */
					add_submenu_page(
						$args['menu_slug'],
						$args['page_title'],
						__( 'Settings', 'mixmymeals' ),
						$args['capability'],
						$args['menu_slug'],
						$args['function']
					);
					break;
				default:
					return;
			}

		}
	}

	/**
	 * Sorts the entries of the plugin's menus by SUBMENU_ORDER.
	 *
	 * Runs last on `admin_menu`, when every entry exists. A sort rather than
	 * `$position` on each add_submenu_page(): half of these entries are not
	 * added by a call this plugin makes — core adds the post type and
	 * taxonomy items itself — so positions could only ever order the other
	 * half, and the list would still depend on who registered first.
	 *
	 * @return void
	 */
	public function order_submenus(): void {

		global $submenu;

		foreach ( self::SUBMENU_ORDER as $parent => $order ) {

			if ( empty( $submenu[ $parent ] ) || ! is_array( $submenu[ $parent ] ) ) {
				continue;
			}

			$rank  = array_flip( $order );
			$items = array();

			foreach ( array_values( $submenu[ $parent ] ) as $index => $item ) {
				$slug    = str_replace( '&amp;', '&', (string) ( $item[2] ?? '' ) );
				$items[] = array( $rank[ $slug ] ?? count( $order ), $index, $item );
			}

			// The original index breaks ties, so unlisted entries stay in
			// the order they arrived in.
			usort( $items, static fn( $a, $b ) => array( $a[0], $a[1] ) <=> array( $b[0], $b[1] ) );

			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- reordering the menu is the point.
			$submenu[ $parent ] = array_column( $items, 2 );
		}
	}

	/**
	 * The tab a request asks for, resolved.
	 *
	 * An old key becomes the tab its fields moved to, and a key nobody knows
	 * becomes the first tab — the same screen a request without `tab` gets —
	 * instead of a form with nothing in it. register_settings() and the page
	 * both ask this, so the fields registered and the tab shown as active
	 * cannot disagree.
	 *
	 * @param string $requested The raw `tab` parameter, '' for none.
	 * @return string
	 */
	public function resolve_tab( string $requested ): string {

		$requested = self::TAB_ALIASES[ $requested ] ?? $requested;

		if ( '' !== $requested && isset( $this->settings[ $requested ] ) ) {
			return $requested;
		}

		return (string) array_key_first( (array) $this->settings );
	}

	/**
	 * Prepare default settings page arguments
	 *
	 * @return mixed|void
	 */
	private function menu_settings(): mixed {
		// base is 'mixmymeals_', so this is mixmymeals_menu_settings.
		// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- resolves to mixmymeals_menu_settings.
		return apply_filters(
			$this->base . 'menu_settings',
			array(

				/*
				 * Eigener Hauptpunkt statt Einstellungen → MixMyMeals.
				 *
				 * Die Einstellungen lagen als einziger Teil dieses Plugins
				 * ausserhalb seiner eigenen Menüs, während daneben vier
				 * Hauptpunkte standen. Wer eine Option suchte, suchte sie
				 * zuerst dort, wo die Inhalte sind — und fand sie nicht. Jetzt
				 * trägt dieser Punkt alles, was zu keinem Beitragstyp gehört:
				 * Einstellungen, Import, Fotos, Abos.
				 *
				 * Der Slug bleibt `mixmymeals_settings`, damit der
				 * `page`-Parameter derselbe ist wie bisher; nur die Datei
				 * davor wechselt von options-general.php zu admin.php.
				 *
				 * Position 35: die Inhaltsmenüs stehen auf 30, 31 und 32,
				 * das Social-Add-on mit dem, was aus ihnen veröffentlicht
				 * wird, auf 33 — erst schreiben, dann verbreiten, dann
				 * einstellen. Dieser Punkt schliesst die Gruppe ab; 34 bleibt
				 * frei, damit ein weiteres Add-on dazwischen passt, ohne dass
				 * WordPress es per Kollisionsausgleich irgendwohin schiebt.
				 * Mögliche Werte für `location`: options, menu, submenu.
				 */
				'location'    => 'menu',
				'parent_slug' => '',
				'page_title'  => __( 'MixMyMeals', 'mixmymeals' ),
				'menu_title'  => __( 'MixMyMeals', 'mixmymeals' ),
				'capability'  => 'manage_options',
				'menu_slug'   => $this->parent->_token . '_settings',
				'function'    => array( $this, 'settings_page' ),
				'icon_url'    => 'dashicons-admin-settings',
				'position'    => 35,
			)
		);
		// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
	}

	/**
	 * Container for settings page arguments
	 *
	 * @param   array $settings  Settings array.
	 *
	 * @return array
	 */
	public function configure_settings( array $settings = array() ): array {
		return $settings;
	}

	/**
	 * Load settings JS & CSS
	 *
	 * @return void
	 */
	public function settings_assets(): void {
	}

	/**
	 * The admin URL of the settings screen, optionally of one tab.
	 *
	 * The one place that knows where this screen lives. It moved out of
	 * Settings → MixMyMeals into the plugin's own menu, and three links in two
	 * plugins kept pointing at options-general.php afterwards — the add-on's
	 * among them, which is exactly why this is public and not a private
	 * detail.
	 *
	 * @param string $tab Optional tab key, e.g. 'mixmymeals-recipes'.
	 * @return string
	 */
	public static function settings_url( string $tab = '' ): string {

		$args = array( 'page' => 'mixmymeals_settings' );

		if ( '' !== $tab ) {
			$args['tab'] = $tab;
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Add settings link to plugin list table
	 *
	 * @param   array $links  Existing links.
	 *
	 * @return array        Modified links.
	 */
	public function add_settings_link( array $links ): array {
		$links[] = '<a href="' . esc_url( self::settings_url() ) . '">'
		           . esc_html__( 'Settings', 'mixmymeals' ) . '</a>';

		return $links;
	}

	/**
	 * Register plugin settings
	 *
	 * @return void
	 */
	public function register_settings(): void {
		if ( is_array( $this->settings ) ) {

			// Check posted/selected tab.
			//phpcs:disable
			$current_section = '';
			if ( isset( $_POST['tab'] ) && $_POST['tab'] ) {
				$current_section = sanitize_text_field( $_POST['tab'] );
			} else {
				if ( isset( $_GET['tab'] ) && $_GET['tab'] ) {
					$current_section = sanitize_text_field( $_GET['tab'] );
				}
			}
			//phpcs:enable

			$current_section = $this->resolve_tab( $current_section );

			foreach ( $this->settings as $section => $data ) {

				if ( $current_section !== $section ) {
					continue;
				}

				// Add section to page.
				add_settings_section(
					$section,
					$data['title'],
					array( $this, 'settings_section' ),
					$this->parent->_token . '_settings'
				);

				foreach ( $data['fields'] as $field ) {

					// Group headings are display-only rows without an option.
					if ( isset( $field['type'] ) && 'heading' === $field['type'] ) {
						add_settings_field(
							$field['id'],
							'',
							array( $this, 'settings_group_heading' ),
							$this->parent->_token . '_settings',
							$section,
							array(
								'field' => $field,
								'class' => 'mmm-settings-group',
							)
						);
						continue;
					}

					$args = array();
					if ( isset( $field['callback'] ) ) {
						$args['sanitize_callback'] = $field['callback'];
					}
					if ( isset( $field['default'] ) ) {
						$args['default'] = $field['default'];
					}

					// Register field.
					$option_name = $this->base . $field['id'];
					register_setting(
						$this->parent->_token . '_settings',
						$option_name,
						$args
					);

					// Add field to page.
					add_settings_field(
						$field['id'],
						$field['label'],
						array( $this->parent->admin, 'display_field' ),
						$this->parent->_token . '_settings',
						$section,
						array(
							'field'  => $field,
							'prefix' => $this->base,
						)
					);
				}
			}
		}
	}

	/**
	 * Renders a visual group heading between settings rows. Registered for
	 * fields with 'type' => 'heading'; the row carries the CSS class
	 * mmm-settings-group (styled in admin.css).
	 *
	 * @param array $args Field arguments ('field' key).
	 *
	 * @return void
	 */
	public function settings_group_heading( array $args ): void {

		$field = $args['field'] ?? array();

		/*
		 * Zwei Ebenen: level 2 ist eine Sektion, die eigene Gruppen trägt
		 * („Chips“ im Reiter Sprache), alles andere eine Gruppe.
		 */
		$is_section = 2 === (int) ( $field['level'] ?? 3 );

		echo $is_section
			? '<h2 class="mmm-settings-section-title">' . esc_html( (string) ( $field['label'] ?? '' ) ) . '</h2>'
			: '<h3 class="mmm-settings-group-title">' . esc_html( (string) ( $field['label'] ?? '' ) ) . '</h3>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in both branches.

		if ( ! empty( $field['description'] ) ) {
			echo '<p class="mmm-settings-group-desc">'
				. esc_html( (string) $field['description'] )
				. '</p>';
		}
	}

	/**
	 * Settings section.
	 *
	 * @param   array $section  Array of section ids.
	 *
	 * @return void
	 */
	public function settings_section( array $section ) {
		$html = '<p> '
		        . esc_html( $this->settings[ $section['id'] ]['description'] )
		        . '</p>' . "\n";
		echo $html; //phpcs:ignore
	}

	/**
	 * Load settings page content.
	 *
	 * @return void
	 */
	public function settings_page(): void {

		// Build page HTML.
		$html  = '<div class="wrap" id="' . $this->parent->_token . '_settings">'
		         . "\n";
		$html .= '<div class="mixmymeals-admin-wrapper">' . "\n";
		$html .= '<div class="mixmymeals-admin-main-section">';
		$html .= '<h1>' . esc_html__( 'Settings', 'mixmymeals' )
		         . '&nbsp;›&nbsp;MixMyMeals</h1>' . "\n";

		$tab = '';
		//phpcs:disable
		if ( isset( $_GET['tab'] ) && $_GET['tab'] ) {
			$tab .= sanitize_text_field( $_GET['tab'] );
		}
		//phpcs:enable

		/*
		 * Der markierte Reiter ist der, dessen Felder angezeigt werden.
		 * Vorher hing die Markierung an einer Nonce im Link: ein Link ohne
		 * sie — der Unterpunkt „Einstellungen“ des Social-Add-ons, jedes
		 * Lesezeichen — zeigte die Pinterest-Felder unter einem markierten
		 * „Allgemein“. Eine Nonce schützt hier ohnehin nichts: einen Reiter
		 * auszuwählen ändert keine Daten, und die Felder wurden schon immer
		 * ohne sie gewählt.
		 */
		$tab = $this->resolve_tab( $tab );

		// Show page tabs.
		if ( is_array( $this->settings ) && 1 < count( $this->settings ) ) {

			$html .= '<h2 class="nav-tab-wrapper">' . "\n";

			foreach ( $this->settings as $section => $data ) {

				// Set tab class:
				$class = 'nav-tab';
				if ( $section === $tab ) {
					$class .= ' nav-tab-active';
				}

				/*
				 * Die Adresse wird gebaut, nicht aus der laufenden Anfrage
				 * fortgeschrieben. add_query_arg() ohne URL hängt an
				 * $_SERVER['REQUEST_URI'] an — das ging gut, solange diese
				 * Seite nur unter einer Adresse erreichbar war, und war der
				 * Grund, warum beim Umzug von options-general.php nach
				 * admin.php niemandem auffiel, dass hier überhaupt eine
				 * Adresse entsteht. settings_url() ist dieselbe Quelle, aus
				 * der auch das Add-on und der Plugin-Link schoepfen.
				 */
				$tab_link = self::settings_url( $section );

				// Output tab.
				$html .= '<a href="' . esc_url( $tab_link ) . '" class="'
				         . esc_attr( $class ) . '">'
				         . esc_html( $data['title'] ) . '</a>' . "\n";
			}

			$html .= '</h2>' . "\n";
		}

		$html .= '<form method="post" action="options.php" enctype="multipart/form-data">'
		         . "\n";

		// Get settings fields.
		ob_start();
		settings_fields( $this->parent->_token . '_settings' );
		do_settings_sections( $this->parent->_token . '_settings' );
		$html .= ob_get_clean();

		$html .= '<p class="submit">' . "\n";
		$html .= '<input type="hidden" name="tab" value="' . esc_attr( $tab )
		         . '" />' . "\n";
		$html .= '<input name="Submit" type="submit" class="button-primary" value="'
		         . esc_attr__( 'Save Settings', 'mixmymeals' ) . '" />'
		         . "\n";
		$html .= '</p>' . "\n";
		$html .= '</form>' . "\n";

		// The generated policy text sits under the tab's own setting.
		if ( 'mixmymeals-privacy' === $tab ) {
			$html .= $this->render_privacy_policy_tab();
		}

		// Mailer test box + send log: only on the E-Mail tab.
		if ( 'mixmymeals-email' === $tab ) {
			$html .= $this->render_test_email_box();
			$html .= $this->render_mail_log();
		}

		$html .= '</div>' . "\n";
		$html .= '</div>' . "\n";
		$html .= '</div>' . "\n";

		echo $html; //phpcs:ignore
	}

	/**
	 * The "Privacy Policy" tab below its one setting (WordPress emoji): the
	 * generated section as it reads, the same markup to copy into a policy
	 * page, and the shortcode that embeds it live instead. The text itself is
	 * read-only — it describes what the other tabs configure, so the place to
	 * change it is there.
	 *
	 * @return string
	 */
	private function render_privacy_policy_tab(): string {

		$policy = class_exists( 'Mixmymeals_Privacy' ) ? Mixmymeals_Privacy::policy_section_html( 'h2' ) : '';

		// Title and description come with the settings section above.
		$html  = '<h3>' . esc_html__( 'Embed it', 'mixmymeals' ) . '</h3>';
		$html .= '<p>' . wp_kses_post(
			sprintf(
				/* translators: 1: shortcode, 2: shortcode with attribute. */
				__( 'Put %1$s on your privacy policy page where the section belongs; it always shows the current text. %2$s leaves out the heading (or use h3/h4).', 'mixmymeals' ),
				'<code>[mmm_privacy_policy]</code>',
				'<code>[mmm_privacy_policy heading="none"]</code>'
			)
		) . '</p>';

		$html .= '<h3>' . esc_html__( 'Preview', 'mixmymeals' ) . '</h3>';
		$html .= '<div class="mmm-privacy-preview">' . $policy . '</div>';

		$html .= '<h3>' . esc_html__( 'Copy as HTML', 'mixmymeals' ) . '</h3>';
		$html .= '<p class="description">' . esc_html__( 'For a policy page that is not edited in WordPress, or to freeze the current wording. A copy does not follow later changes — the shortcode does.', 'mixmymeals' ) . '</p>';
		$html .= '<textarea class="large-text code" rows="12" readonly onclick="this.select()">' . esc_textarea( $policy ) . '</textarea>';

		$html .= '<p class="description">' . esc_html__( 'This is an inventory of what the plugin does on this site, not legal advice.', 'mixmymeals' ) . '</p>';

		return $html;
	}

	/**
	 * Builds the "send a test e-mail" box shown on the E-Mail settings tab.
	 *
	 * The button triggers an AJAX request handled by ajax_send_test_email();
	 * it is intentionally outside the options.php form.
	 *
	 * @return string
	 */
	private function render_test_email_box(): string {

		$admin_email = (string) get_option( 'admin_email' );

		$html  = '<hr />' . "\n";
		$html .= '<h3>' . esc_html__( 'Send a test e-mail', 'mixmymeals' ) . '</h3>' . "\n";
		$html .= '<p class="description">'
		         . esc_html__( 'Sends a test message with the current sender settings. Save your changes first.', 'mixmymeals' )
		         . '</p>' . "\n";
		$html .= '<p>';
		$html .= '<input type="email" id="mmm-test-email-to" class="regular-text" value="'
		         . esc_attr( $admin_email ) . '" placeholder="' . esc_attr( $admin_email ) . '" /> ';
		$html .= '<button type="button" class="button" id="mmm-send-test-email" data-nonce="'
		         . esc_attr( wp_create_nonce( 'mmm_send_test_email' ) ) . '">'
		         . esc_html__( 'Send test e-mail', 'mixmymeals' ) . '</button>';
		$html .= '</p>' . "\n";
		$html .= '<p id="mmm-test-email-result" aria-live="polite"></p>' . "\n";

		return $html;
	}

	/**
	 * AJAX handler for the "send test e-mail" button.
	 *
	 * Capability- and nonce-checked. Sends a forced, transactional test message
	 * through Mixmymeals_Mailer and reports the result as JSON.
	 *
	 * @return void
	 */
	public function ajax_send_test_email(): void {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You are not allowed to do this.', 'mixmymeals' ) ),
				403
			);
		}

		check_ajax_referer( 'mmm_send_test_email', 'nonce' );

		if ( ! class_exists( 'Mixmymeals_Mailer' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'The mailer is not available.', 'mixmymeals' ) ),
				500
			);
		}

		$to = isset( $_POST['to'] )
			? sanitize_email( wp_unslash( $_POST['to'] ) ) //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			: '';

		if ( '' === $to || ! is_email( $to ) ) {
			$to = (string) get_option( 'admin_email' );
		}

		$site_name = wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES );
		$when      = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );

		$body  = '<h1>' . esc_html__( 'Test e-mail', 'mixmymeals' ) . '</h1>';
		$body .= '<p>' . esc_html(
			sprintf(
				/* translators: %s: site name. */
				__( 'This is a test message from %s.', 'mixmymeals' ),
				$site_name
			)
		) . '</p>';
		$body .= '<p>' . esc_html(
			sprintf(
				/* translators: %s: date and time the test was sent. */
				__( 'Sent on %s. If you can read this, the mailer is working.', 'mixmymeals' ),
				$when
			)
		) . '</p>';

		$subject = sprintf(
			/* translators: %s: site name. */
			__( '[%s] Test e-mail', 'mixmymeals' ),
			$site_name
		);

		$sent = Mixmymeals_Mailer::send_template(
			$to,
			$subject,
			$body,
			array(),
			array(
				'inline'        => true,
				'layout'        => 'layout.html',
				'transactional' => true,
				'force'         => true,
			)
		);

		if ( $sent ) {
			wp_send_json_success(
				array(
					/* translators: %s: recipient e-mail address. */
					'message' => sprintf( __( 'Test e-mail sent to %s.', 'mixmymeals' ), $to ),
				)
			);
		}

		wp_send_json_error(
			array(
				'message' => __( 'wp_mail() reported a failure. Please check your server / SMTP configuration.', 'mixmymeals' ),
			),
			500
		);
	}

	/**
	 * Builds the send-log table shown on the E-Mail settings tab.
	 *
	 * @return string
	 */
	private function render_mail_log(): string {

		if ( ! class_exists( 'Mixmymeals_Mail_Log' ) ) {
			return '';
		}

		$entries = Mixmymeals_Mail_Log::get_entries();

		$html  = '<hr />' . "\n";
		$html .= '<h3>' . esc_html__( 'Send log', 'mixmymeals' ) . '</h3>' . "\n";

		if ( ! Mixmymeals_Mail_Log::is_enabled() ) {
			$html .= '<p class="description">'
			         . esc_html__( 'Logging is currently disabled. Enable “Send Log” above and save to start recording.', 'mixmymeals' )
			         . '</p>' . "\n";
		}

		if ( empty( $entries ) ) {
			$html .= '<p class="description">'
			         . esc_html__( 'No e-mails logged yet.', 'mixmymeals' )
			         . '</p>' . "\n";

			return $html;
		}

		$date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		$html .= '<table class="widefat striped" id="mmm-mail-log-table" style="max-width:880px;margin-top:8px;">' . "\n";
		$html .= '<thead><tr>'
		         . '<th>' . esc_html__( 'Date', 'mixmymeals' ) . '</th>'
		         . '<th>' . esc_html__( 'Recipient', 'mixmymeals' ) . '</th>'
		         . '<th>' . esc_html__( 'Subject', 'mixmymeals' ) . '</th>'
		         . '<th>' . esc_html__( 'Status', 'mixmymeals' ) . '</th>'
		         . '</tr></thead><tbody>' . "\n";

		foreach ( $entries as $entry ) {

			$time    = isset( $entry['time'] ) ? (int) $entry['time'] : 0;
			$when    = $time ? wp_date( $date_format, $time ) : '—';
			$to      = isset( $entry['to'] ) ? (string) $entry['to'] : '';
			$subject = isset( $entry['subject'] ) ? (string) $entry['subject'] : '';
			$is_sent = isset( $entry['status'] ) && 'sent' === $entry['status'];

			$badge = $is_sent
				? '<span style="color:#3F6F1E;font-weight:600;">' . esc_html__( 'Sent', 'mixmymeals' ) . '</span>'
				: '<span style="color:#D2622C;font-weight:600;">' . esc_html__( 'Failed', 'mixmymeals' ) . '</span>';

			$html .= '<tr>'
			         . '<td>' . esc_html( $when ) . '</td>'
			         . '<td>' . esc_html( $to ) . '</td>'
			         . '<td>' . esc_html( $subject ) . '</td>'
			         . '<td>' . $badge . '</td>'
			         . '</tr>' . "\n";
		}

		$html .= '</tbody></table>' . "\n";

		$html .= '<p>';
		$html .= '<button type="button" class="button" id="mmm-clear-mail-log" data-nonce="'
		         . esc_attr( wp_create_nonce( 'mmm_clear_mail_log' ) ) . '">'
		         . esc_html__( 'Clear log', 'mixmymeals' ) . '</button> ';
		$html .= '<span id="mmm-clear-mail-log-result" aria-live="polite"></span>';
		$html .= '</p>' . "\n";

		return $html;
	}

	/**
	 * AJAX handler for the "clear send log" button.
	 *
	 * @return void
	 */
	public function ajax_clear_mail_log(): void {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You are not allowed to do this.', 'mixmymeals' ) ),
				403
			);
		}

		check_ajax_referer( 'mmm_clear_mail_log', 'nonce' );

		if ( class_exists( 'Mixmymeals_Mail_Log' ) ) {
			Mixmymeals_Mail_Log::clear();
		}

		wp_send_json_success( array( 'message' => __( 'Send log cleared.', 'mixmymeals' ) ) );
	}

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __clone(): void {
		_doing_it_wrong(
			__FUNCTION__,
			esc_html(
				__(
					'Cloning of Mixmymeals_API is forbidden.',
					'mixmymeals'
				)
			),
			esc_attr( $this->parent->_version )
		);
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup(): void {
		_doing_it_wrong(
			__FUNCTION__,
			esc_html(
				__(
					'Unserializing instances of Mixmymeals_API is forbidden.',
					'mixmymeals'
				)
			),
			esc_attr( $this->parent->_version )
		);
	}

	/**
	 * Callback for placeholder image setting for sanitization purpose. Returns
	 * 0 if input is not an attachment ID.
	 *
	 */
	public function placeholder_image_id_callback( mixed $input ): int {

		$id = absint( $input );

		return ( $id && get_post_type( $id ) === 'attachment' )
			? $id
			: 0;

	}

	/**
	 * Callback for meal plan archive description for sanitization purpose.
	 *
	 */
	public function archive_description_callback( mixed $input ): string {
		return wp_kses_post( $input );
	}

	/**
	 * Sanitizes a plain text setting value.
	 *
	 * @param mixed $input Raw input value.
	 * @return string
	 */
	public function text_callback( mixed $input ): string {
		return sanitize_text_field( $input );
	}

	/**
	 * Sanitizes a URL slug setting value.
	 *
	 * @param mixed $input Raw input value.
	 * @return string
	 */
	public function slug_callback( mixed $input ): string {
		return sanitize_title( $input );
	}

	/**
	 * Sanitizes the recipe disclaimer textarea setting value.
	 *
	 * @param mixed $input Raw input value.
	 * @return string
	 */
	public function recipe_disclaimer_text_callback( mixed $input ): string {
		return sanitize_textarea_field( $input );
	}

	/**
	 * Sanitizes the "also show on ingredient pages" checkbox.
	 *
	 * @param mixed $input Raw input value.
	 * @return string 'on' or ''
	 */
	public function recipe_disclaimer_on_ingredients_callback( mixed $input ): string {
		return ( 'on' === $input ) ? 'on' : '';
	}

	/**
	 * Sanitizes a plain on/off checkbox setting. An unchecked box is not
	 * submitted at all, so anything falsy becomes ''.
	 *
	 * @param mixed $input Raw input value.
	 * @return string 'on' or ''
	 */
	public function checkbox_callback( mixed $input ): string {
		return $input ? 'on' : '';
	}

	/**
	 * Sanitizes the rich-text note shown in place of the recipe steps.
	 *
	 * @param mixed $input Raw input value.
	 * @return string
	 */
	public function recipe_steps_notice_callback( mixed $input ): string {
		return wp_kses_post( $input );
	}

	/**
	 * Sanitizes the maximum cart items setting value.
	 *
	 * @param mixed $input Raw input value.
	 * @return int
	 */
	public function max_items_in_cart_callback( mixed $input ): int {
		return absint( $input );
	}

	/**
	 * Sanitizes the thumbnail selection mode.
	 *
	 * Anything the class does not know falls back to the default rather than
	 * being stored: the mode decides what a public archive card shows.
	 *
	 * @param mixed $input Raw input value.
	 * @return string
	 */
	/**
	 * Sanitizes the "show meals without a day" checkbox.
	 *
	 * @param mixed $input Raw input value.
	 * @return string 'on' or ''
	 */
	public function plan_undated_meals_callback( mixed $input ): string {
		return ( 'on' === $input ) ? 'on' : '';
	}

	public function plan_thumbs_mode_callback( mixed $input ): string {

		$mode = sanitize_key( (string) $input );

		return isset( Mixmymeals_Meal_Plan::thumbs_modes()[ $mode ] )
			? $mode
			: Mixmymeals_Meal_Plan::THUMBS_MODE_DEFAULT;
	}

	/**
	 * Sanitizes the maximum number of thumbnails. 0 is kept — it means "all".
	 *
	 * @param mixed $input Raw input value.
	 * @return int
	 */
	public function plan_thumbs_max_callback( mixed $input ): int {
		return min( 50, absint( $input ) );
	}

	/**
	 * Sanitizes the public mode checkbox setting.
	 *
	 * @param mixed $input Raw input value.
	 * @return string 'on' or ''
	 */
	public function public_mode_callback( mixed $input ): string {
		return ( 'on' === $input ) ? 'on' : '';
	}

	/**
	 * Sanitizes the meal plan publishing rate limit setting value.
	 *
	 * @param mixed $input Raw input value.
	 * @return int
	 */
	public function published_meal_plans_per_hour_limit_callback( mixed $input ): int {
		return absint( $input );
	}

	/**
	 * Sanitizes the save-as-draft checkbox setting for visitor-published plans.
	 *
	 * @param mixed $input Raw input value.
	 * @return string 'on' or ''
	 */
	public function save_meal_plans_published_by_visitors_as_draft_callback( mixed $input ): string {
		return ( 'on' === $input ) ? 'on' : '';
	}

	/**
	 * Sanitizes the templates-need-a-category checkbox.
	 *
	 * @param mixed $input Raw input value.
	 * @return string 'on' or ''
	 */
	public function planner_templates_require_category_callback( mixed $input ): string {
		return ( 'on' === $input ) ? 'on' : '';
	}

	/**
	 * Sanitizes the enable-cleanup checkbox seButtting.
	 *
	 * @param mixed $input Raw input value.
	 * @return string 'on' or ''
	 */
	public function enable_cleanup_callback( mixed $input ): string {
		return ( 'on' === $input ) ? 'on' : '';
	}

	/**
	 * Sanitizes the cleanup minimum age setting (days).
	 *
	 * @param mixed $input Raw input value.
	 * @return int
	 */
	public function cleanup_min_age_days_callback( mixed $input ): int {
		$days = absint( $input );
		return $days > 0 ? $days : 30;
	}

	/**
	 * Sanitizes the cleanup-report checkbox setting.
	 *
	 * @param mixed $input Raw input value.
	 * @return string 'on' or ''
	 */
	public function cleanup_report_enabled_callback( mixed $input ): string {
		return ( 'on' === $input ) ? 'on' : '';
	}

	/**
	 * Sanitizes the cleanup-report recipient address.
	 *
	 * Returns '' for empty/invalid input so the cleanup falls back to the
	 * site admin e-mail.
	 *
	 * @param mixed $input Raw input value.
	 * @return string A valid e-mail address or ''.
	 */
	public function cleanup_report_recipient_callback( mixed $input ): string {
		$email = sanitize_email( (string) $input );

		return ( '' !== $email && is_email( $email ) ) ? $email : '';
	}

	/**
	 * Sanitizes the redirect page ID setting for draft-saved visitor plans.
	 *
	 * @param mixed $input Raw input value.
	 * @return int
	 */
	/**
	 * Sanitizes the success page setting. 0 means "the plan itself".
	 *
	 * @param mixed $input Raw input value.
	 * @return int
	 */
	public function page_id_to_redirect_after_plan_created_callback( mixed $input ): int {
		return absint( $input );
	}

	public function page_id_to_redirect_if_new_plan_is_draft_callback( mixed $input ): int {
		return absint( $input );
	}

	/**
	 * Sanitizes the subscribe-while-saving mode setting.
	 *
	 * @param mixed $input Raw input value.
	 * @return string One of Mixmymeals_Subscriptions::save_offer_modes().
	 */
	public function plan_subscribe_mode_callback( mixed $input ): string {

		$mode = sanitize_key( (string) $input );

		return isset( Mixmymeals_Subscriptions::save_offer_modes()[ $mode ] )
			? $mode
			: Mixmymeals_Subscriptions::SAVE_OFFER_OFF;
	}

	/**
	 * Sanitizes the e-mail sender address setting.
	 *
	 * Returns '' for empty/invalid input so Mixmymeals_Mailer falls back to
	 * its domain-based default.
	 *
	 * @param mixed $input Raw input value.
	 * @return string A valid e-mail address or ''.
	 */
	public function mail_from_email_callback( mixed $input ): string {
		$email = sanitize_email( (string) $input );

		return ( '' !== $email && is_email( $email ) ) ? $email : '';
	}

	/**
	 * Sanitizes the send-log checkbox setting.
	 *
	 * @param mixed $input Raw input value.
	 * @return string 'on' or ''
	 */
	public function mail_log_enabled_callback( mixed $input ): string {
		return ( 'on' === $input ) ? 'on' : '';
	}

	/**
	 * Sanitizes the suppression (opt-out) list textarea into an array of
	 * unique, valid, lower-cased e-mail addresses.
	 *
	 * @param mixed $input Raw input value (newline/comma/semicolon separated).
	 * @return string[] Clean address list.
	 */
	public function mail_suppression_list_callback( mixed $input ): array {
		$lines = preg_split( '/[\r\n,;]+/', (string) $input );
		$clean = array();

		foreach ( (array) $lines as $line ) {
			$email = strtolower( sanitize_email( trim( (string) $line ) ) );

			if ( '' !== $email && is_email( $email ) ) {
				$clean[ $email ] = $email;
			}
		}

		return array_values( $clean );
	}

	/**
	 * Sanitizes the subscriptions enable checkbox.
	 *
	 * @param mixed $input Raw input value.
	 * @return string 'on' or ''
	 */
	public function subscriptions_enabled_callback( mixed $input ): string {
		return ( 'on' === $input ) ? 'on' : '';
	}

	/**
	 * Sanitizes the store-consent-IP checkbox.
	 *
	 * @param mixed $input Raw input value.
	 * @return string 'on' or ''
	 */
	public function subscription_log_ip_callback( mixed $input ): string {
		return ( 'on' === $input ) ? 'on' : '';
	}

	/**
	 * Sanitizes the appended text block (HTML allowed via wp_kses_post).
	 *
	 * @param mixed $input Raw input value.
	 * @return string
	 */
	public function subscription_append_text_callback( mixed $input ): string {
		return wp_kses_post( (string) $input );
	}

	/**
	 * Sanitizes the privacy policy URL.
	 *
	 * @param mixed $input Raw input value.
	 * @return string
	 */
	public function subscription_privacy_url_callback( mixed $input ): string {
		return esc_url_raw( trim( (string) $input ) );
	}

	/**
	 * Sanitizes the "redirect after subscribing" page ID.
	 *
	 * @param mixed $input Raw input value.
	 * @return int
	 */
	public function subscription_subscribe_page_callback( mixed $input ): int {
		return absint( $input );
	}

	/**
	 * Sanitizes the "redirect after confirmation" page ID.
	 *
	 * @param mixed $input Raw input value.
	 * @return int
	 */
	public function subscription_confirm_page_callback( mixed $input ): int {
		return absint( $input );
	}

	/**
	 * Sanitizes the "redirect after unsubscribing" page ID.
	 *
	 * @param mixed $input Raw input value.
	 * @return int
	 */
	public function subscription_unsubscribe_page_callback( mixed $input ): int {
		return absint( $input );
	}

	/**
	 * Sanitizes the recipe rating page ID.
	 *
	 * @param mixed $input Raw input value.
	 * @return int
	 */
	public function subscription_rate_page_callback( mixed $input ): int {
		return absint( $input );
	}

	/**
	 * Sanitizes the photo upload page ID.
	 *
	 * @param mixed $input Raw input value.
	 * @return int
	 */
	public function subscription_upload_page_callback( mixed $input ): int {
		return absint( $input );
	}

	/**
	 * Sanitizes the auto-approve meal photos checkbox.
	 *
	 * @param mixed $input Raw input value.
	 * @return string 'on' or ''
	 */
	public function photos_auto_approve_callback( mixed $input ): string {
		return ( 'on' === $input ) ? 'on' : '';
	}

	/**
	 * Sanitizes the confirmation window (hours).
	 *
	 * @param mixed $input Raw input value.
	 * @return int
	 */
	public function subscription_pending_hours_callback( mixed $input ): int {
		$h = absint( $input );
		return $h > 0 ? $h : 48;
	}

	/**
	 * Sanitizes the retention (days).
	 *
	 * @param mixed $input Raw input value.
	 * @return int
	 */
	public function subscription_retention_days_callback( mixed $input ): int {
		$d = absint( $input );
		return $d > 0 ? $d : 365;
	}

	/**
	 * Sanitizes the sending rules into a clean list of triggers.
	 *
	 * @param mixed $input Raw posted rows (array of trigger/time/subject/template).
	 * @return array[] array{ trigger:string, time:string, subject:string, template:string }
	 */
	public function subscription_schedule_callback( mixed $input ): array {

		if ( ! is_array( $input ) ) {
			return array();
		}

		$clean = array();

		foreach ( $input as $row ) {

			if ( ! is_array( $row ) ) {
				continue;
			}

			// The subscriptions class owns validation + legacy migration so save
			// and read stay in lockstep.
			if ( class_exists( 'Mixmymeals_Subscriptions' ) ) {
				$normalized = Mixmymeals_Subscriptions::normalize_rule( $row );
				if ( null !== $normalized ) {
					$clean[] = $normalized;
				}
				continue;
			}

			// Fallback when the subscriptions class is unavailable.
			$trigger  = isset( $row['trigger'] ) ? sanitize_key( $row['trigger'] ) : '';
			$template = isset( $row['template'] ) ? sanitize_text_field( $row['template'] ) : '';
			if ( '' === $trigger || ! preg_match( '/^[A-Za-z0-9._-]+\.html$/', $template ) || 'layout.html' === $template ) {
				continue;
			}

			$subject = isset( $row['subject'] ) ? sanitize_text_field( $row['subject'] ) : '';

			$time = isset( $row['time'] ) ? trim( (string) $row['time'] ) : '';

			if ( 'on_confirm' === $trigger ) {
				$time = '';
			} elseif ( ! preg_match( '/^([01]?\d|2[0-3]):[0-5]\d$/', $time ) ) {
				continue;
			}

			$clean[] = array(
				'trigger'  => $trigger,
				'time'     => $time,
				'subject'  => $subject,
				'template' => $template,
			);
		}

		return $clean;
	}


}
