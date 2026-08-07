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
		add_action( 'init', array( $this, 'maybe_flush_rewrite' ), 99 );

		// Handle the "send test e-mail" button on the E-Mail tab.
		add_action( 'wp_ajax_mmm_send_test_email', array( $this, 'ajax_send_test_email' ) );

		// Handle the "clear send log" button on the E-Mail tab.
		add_action( 'wp_ajax_mmm_clear_mail_log', array( $this, 'ajax_clear_mail_log' ) );

		// Add settings page to menu.
		add_action( 'admin_menu', array( $this, 'add_menu_item' ) );

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

		$settings['mixmymeals-general'] = array(
			'title'       => __( 'General Settings', 'mixmymeals' ),
			'description' => __( 'Currency, placeholder image, meal slug, visitor plans, privacy and input notices. Slugs and archive texts for recipes, ingredients, equipment and plans live on their own tabs.',
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
					'id'    => 'general_group_slugs',
					'type'  => 'heading',
					'label' => __( 'Slugs & Permalinks', 'mixmymeals' ),
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
					'id'    => 'general_group_visitor',
					'type'  => 'heading',
					'label' => __( 'Visitor Plans', 'mixmymeals' ),
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
					'callback'    => array( $this, 'instagram_checkbox_callback' ),
				),
				array(
					'id'    => 'general_group_notices',
					'type'  => 'heading',
					'label' => __( 'Visitor Input Notices', 'mixmymeals' ),
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
					'id'          => 'notice_photo',
					'label'       => __( 'Photo upload notice', 'mixmymeals' ),
					'description' => __( 'Shown on the meal-photo upload form (e.g. that you grant usage rights and photos are reviewed). Leave empty to hide.', 'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array( $this, 'recipe_disclaimer_text_callback' ),
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
					'id'          => 'notice_plan',
					'label'       => __( 'Plan creation notice', 'mixmymeals' ),
					'description' => __( 'Shown next to the “Create meal plan” button (visitor plan creation). Leave empty to hide.', 'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array( $this, 'recipe_disclaimer_text_callback' ),
				),
			),
		);

		$settings['mixmymeals-recipes'] = array(
			'title'       => __( 'Recipes', 'mixmymeals' ),
			'description' => __( 'Recipe URL slugs, archive display and the recipe disclaimer.',
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
			),
		);

		$settings['mixmymeals-ingredients'] = array(
			'title'       => __( 'Ingredients', 'mixmymeals' ),
			'description' => __( 'Ingredient URL slug and archive display.', 'mixmymeals' ),
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
			),
		);

		$settings['mixmymeals-equipment'] = array(
			'title'       => __( 'Equipment', 'mixmymeals' ),
			'description' => __( 'Equipment URL slug, archive display and affiliate links.', 'mixmymeals' ),
			'fields'      => array(
				array(
					'id'    => 'equipment_group_slugs',
					'type'  => 'heading',
					'label' => __( 'URL Slugs', 'mixmymeals' ),
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
					'id'    => 'equipment_group_archive',
					'type'  => 'heading',
					'label' => __( 'Archive', 'mixmymeals' ),
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
					'id'    => 'equipment_group_affiliate',
					'type'  => 'heading',
					'label' => __( 'Affiliate links', 'mixmymeals' ),
				),
				array(
					'id'          => 'affiliate_disclosure',
					'label'       => __( 'Affiliate disclosure', 'mixmymeals' ),
					'description' => __( 'Shown below the shop links on equipment pages. Affiliate links must be labelled as advertising; leave empty to use the built-in wording.', 'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array( $this, 'recipe_disclaimer_text_callback' ),
				),
			),
		);

		$settings['mixmymeals-meal-plans'] = array(
			'title'       => __( 'Meal Plans', 'mixmymeals' ),
			'description' => __( 'Setup meal plans.',
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
					'id'    => 'mealplans_group_cleanup',
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
			),
		);

		$settings['mixmymeals-email'] = array(
			'title'       => __( 'E-Mail', 'mixmymeals' ),
			'description' => __( 'Sender identity and privacy options for e-mails sent by the plugin.',
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
					'id'    => 'email_group_content',
					'type'  => 'heading',
					'label' => __( 'Content & Privacy', 'mixmymeals' ),
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
			),
		);

		$settings['mixmymeals-subscriptions'] = array(
			'title'       => __( 'Subscriptions', 'mixmymeals' ),
			'description' => __( 'Let visitors subscribe to a meal plan and receive day e-mails (double opt-in).',
				'mixmymeals' ),
			'fields'      => array(
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
					'id'    => 'subs_group_photos',
					'type'  => 'heading',
					'label' => __( 'Meal Photos', 'mixmymeals' ),
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
					'id'    => 'subs_group_lifecycle',
					'type'  => 'heading',
					'label' => __( 'Lifecycle & Privacy', 'mixmymeals' ),
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
				array(
					'id'    => 'subs_group_content',
					'type'  => 'heading',
					'label' => __( 'E-Mail Content', 'mixmymeals' ),
				),
				array(
					'id'          => 'subscription_append_text',
					'label'       => __( 'Appended Text Block', 'mixmymeals' ),
					'description' => __( 'HTML or plain text. Each sending rule whose “Append” box is ticked adds this block to the end of its e-mail.', 'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array( $this, 'subscription_append_text_callback' ),
				),
			),
		);

		$settings['mixmymeals-instagram'] = array(
			'title'       => __( 'Instagram', 'mixmymeals' ),
			'description' => __( 'Configure the Instagram carousel images generated from meal plans (one overview slide plus one slide per day). Images are temporary files below the uploads directory and are cleaned up automatically.', 'mixmymeals' ),
			'fields'      => array(
				array(
					'id'    => 'instagram_group_fonts',
					'type'  => 'heading',
					'label' => __( 'Fonts & Logo', 'mixmymeals' ),
				),
				array(
					'id'          => 'instagram_title_font_id',
					'label'       => __( 'Title Font', 'mixmymeals' ),
					'description' => __( 'TTF or OTF file from the media library, used for headlines and badges. Required — the image renderer cannot use web fonts (woff2). Fallback: first font file in the child theme folder assets/fonts/.', 'mixmymeals' ),
					'default'     => 0,
					'placeholder' => '',
					'callback'    => array( $this, 'placeholder_image_id_callback' ),
				),
				array(
					'id'          => 'instagram_text_font_id',
					'label'       => __( 'Text Font', 'mixmymeals' ),
					'description' => __( 'Optional second font for meta lines and dish names. Falls back to the title font.', 'mixmymeals' ),
					'default'     => 0,
					'placeholder' => '',
					'callback'    => array( $this, 'placeholder_image_id_callback' ),
				),
				array(
					'id'          => 'instagram_logo_id',
					'label'       => __( 'Logo', 'mixmymeals' ),
					'description' => __( 'Optional PNG from the media library. Replaces the text wordmark on the slides.', 'mixmymeals' ),
					'default'     => 0,
					'placeholder' => '',
					'callback'    => array( $this, 'placeholder_image_id_callback' ),
				),
				array(
					'id'          => 'instagram_wordmark',
					'label'       => __( 'Wordmark', 'mixmymeals' ),
					'description' => __( 'Brand text on the slides, shown exactly as written here. Leave empty to use the site title.', 'mixmymeals' ),
					'default'     => '',
					'placeholder' => get_bloginfo( 'name' ),
					'callback'    => array( $this, 'text_callback' ),
				),
				array(
					'id'          => 'instagram_logo_position',
					'label'       => __( 'Logo Position', 'mixmymeals' ),
					'description' => __( 'Alignment of the logo / wordmark on the cover slide.', 'mixmymeals' ),
					'default'     => 'left',
					'placeholder' => '',
					'options'     => array(
						'left'   => __( 'Left', 'mixmymeals' ),
						'center' => __( 'Centered', 'mixmymeals' ),
					),
					'callback'    => array( $this, 'instagram_logo_position_callback' ),
				),
				array(
					'id'          => 'instagram_logo_size',
					'label'       => __( 'Logo Size (%)', 'mixmymeals' ),
					'description' => __( 'Size of the logo / wordmark in the header of the cover and overview slides, 50–200%.', 'mixmymeals' ),
					'default'     => 100,
					'placeholder' => '100',
					'min'         => 50,
					'max'         => 200,
					'callback'    => array( $this, 'instagram_logo_size_callback' ),
				),
				array(
					'id'    => 'instagram_group_design',
					'type'  => 'heading',
					'label' => __( 'Format & Colors', 'mixmymeals' ),
				),
				array(
					'id'          => 'instagram_style_preset',
					'label'       => __( 'Style Preset', 'mixmymeals' ),
					'description' => __( 'A ready-made colour theme. Choose “Custom” to use the individual colours below.', 'mixmymeals' ),
					'default'     => 'custom',
					'placeholder' => '',
					'options'     => array(
						'custom'    => __( 'Custom (use colours below)', 'mixmymeals' ),
						'frisch'    => __( 'Fresh (brand green)', 'mixmymeals' ),
						'editorial' => __( 'Editorial (warm, muted)', 'mixmymeals' ),
						'kraeftig'  => __( 'Bold (dark, vibrant)', 'mixmymeals' ),
						'minimal'   => __( 'Minimal (black & white)', 'mixmymeals' ),
					),
					'callback'    => array( $this, 'instagram_style_preset_callback' ),
				),
				array(
					'id'          => 'instagram_format',
					'label'       => __( 'Image Format', 'mixmymeals' ),
					'description' => __( 'Portrait (4:5) is recommended for the Instagram feed.', 'mixmymeals' ),
					'default'     => '4x5',
					'placeholder' => '',
					'options'     => array(
						'4x5'  => __( 'Portrait 1080 × 1350 (4:5)', 'mixmymeals' ),
						'1x1'  => __( 'Square 1080 × 1080 (1:1)', 'mixmymeals' ),
						'9x16' => __( 'Story / Reels 1080 × 1920 (9:16)', 'mixmymeals' ),
					),
					'callback'    => array( $this, 'instagram_format_callback' ),
				),
				array(
					'id'          => 'instagram_color_background',
					'label'       => __( 'Background Color', 'mixmymeals' ),
					'description' => __( 'Background of the overview slide.', 'mixmymeals' ),
					'default'     => '#FBF8F1',
					'placeholder' => '',
					'callback'    => array( $this, 'instagram_color_callback' ),
				),
				array(
					'id'          => 'instagram_color_primary',
					'label'       => __( 'Primary Color', 'mixmymeals' ),
					'description' => __( 'Day badges and the footer bar.', 'mixmymeals' ),
					'default'     => '#5C9E2E',
					'placeholder' => '',
					'callback'    => array( $this, 'instagram_color_callback' ),
				),
				array(
					'id'          => 'instagram_color_accent',
					'label'       => __( 'Accent Color', 'mixmymeals' ),
					'description' => __( 'Meal type labels and the wordmark dot.', 'mixmymeals' ),
					'default'     => '#E8743B',
					'placeholder' => '',
					'callback'    => array( $this, 'instagram_color_callback' ),
				),
				array(
					'id'    => 'instagram_group_layout',
					'type'  => 'heading',
					'label' => __( 'Day Slide Layout', 'mixmymeals' ),
				),
				array(
					'id'          => 'instagram_day_layout',
					'label'       => __( 'Layout', 'mixmymeals' ),
					'description' => __( 'How the day slides are composed.', 'mixmymeals' ),
					'default'     => 'full',
					'placeholder' => '',
					'options'     => array(
						'full'     => __( 'Full-bleed (photo edge to edge, text on top)', 'mixmymeals' ),
						'magazine' => __( 'Magazine (photo top, text below)', 'mixmymeals' ),
						'split'    => __( 'Split (photo and text half each)', 'mixmymeals' ),
						'card'     => __( 'Card (photo in a rounded card)', 'mixmymeals' ),
					),
					'callback'    => array( $this, 'instagram_day_layout_callback' ),
				),
				array(
					'id'          => 'instagram_overlay_style',
					'label'       => __( 'Text Overlay (full-bleed)', 'mixmymeals' ),
					'description' => __( 'How the text is set off from the photo in the full-bleed layout.', 'mixmymeals' ),
					'default'     => 'gradient',
					'placeholder' => '',
					'options'     => array(
						'gradient' => __( 'Gradient', 'mixmymeals' ),
						'plate'    => __( 'Text plate', 'mixmymeals' ),
						'minimal'  => __( 'Minimal', 'mixmymeals' ),
					),
					'callback'    => array( $this, 'instagram_overlay_style_callback' ),
				),
				array(
					'id'    => 'instagram_group_content',
					'type'  => 'heading',
					'label' => __( 'Content & Elements', 'mixmymeals' ),
				),
				array(
					'id'          => 'instagram_footer_text',
					'label'       => __( 'Footer Text', 'mixmymeals' ),
					'description' => __( 'Call-to-action in the footer bar of the overview slide. Leave empty for the default text.', 'mixmymeals' ),
					'default'     => '',
					'placeholder' => __( 'Full plan & shopping list: mixmymeals.de', 'mixmymeals' ),
					'callback'    => array( $this, 'text_callback' ),
				),
				array(
					'id'          => 'instagram_meal_types',
					'label'       => __( 'Meal Types', 'mixmymeals' ),
					'description' => __( 'Which meal types appear on the slides.', 'mixmymeals' ),
					'default'     => array( 'lunch', 'dinner' ),
					'placeholder' => '',
					'options'     => Mixmymeals_Meal::get_plaintext_meal_type_map(),
					'callback'    => array( $this, 'instagram_meal_types_callback' ),
				),
				array(
					'id'          => 'instagram_hero_meal_type',
					'label'       => __( 'Main Meal', 'mixmymeals' ),
					'description' => __( 'The recipe photo of this meal type fills the day slide.', 'mixmymeals' ),
					'default'     => 'dinner',
					'placeholder' => '',
					'options'     => Mixmymeals_Meal::get_plaintext_meal_type_map(),
					'callback'    => array( $this, 'instagram_hero_meal_type_callback' ),
				),
				array(
					'id'          => 'instagram_show_kind_pill',
					'label'       => __( 'Plan Type Badge', 'mixmymeals' ),
					'description' => __( 'Show the “Weekly plan” badge in the top right corner of the overview slide.', 'mixmymeals' ),
					'default'     => 'on',
					'placeholder' => '',
					'callback'    => array( $this, 'instagram_checkbox_callback' ),
				),
				array(
					'id'          => 'instagram_show_meta',
					'label'       => __( 'Meta Line', 'mixmymeals' ),
					'description' => __( 'Show days, servings and dish count below the plan title.', 'mixmymeals' ),
					'default'     => 'on',
					'placeholder' => '',
					'callback'    => array( $this, 'instagram_checkbox_callback' ),
				),
				array(
					'id'          => 'instagram_show_day_pill',
					'label'       => __( 'Day Badge', 'mixmymeals' ),
					'description' => __( 'Show day number and weekday in the top left corner of the day slides.', 'mixmymeals' ),
					'default'     => 'on',
					'placeholder' => '',
					'callback'    => array( $this, 'instagram_checkbox_callback' ),
				),
				array(
					'id'          => 'instagram_show_also',
					'label'       => __( 'Further Meals', 'mixmymeals' ),
					'description' => __( 'On days with several recipes, list every further recipe (with its meal time) below the title on day slides.', 'mixmymeals' ),
					'default'     => 'on',
					'placeholder' => '',
					'callback'    => array( $this, 'instagram_checkbox_callback' ),
				),
				array(
					'id'          => 'instagram_show_footer_bar',
					'label'       => __( 'Footer Bar', 'mixmymeals' ),
					'description' => __( 'Show the colored call-to-action bar at the bottom of the overview slide.', 'mixmymeals' ),
					'default'     => 'on',
					'placeholder' => '',
					'callback'    => array( $this, 'instagram_checkbox_callback' ),
				),
				array(
					'id'          => 'instagram_show_brand_footer',
					'label'       => __( 'Brand Footer', 'mixmymeals' ),
					'description' => __( 'Show wordmark and site address at the bottom of the day slides.', 'mixmymeals' ),
					'default'     => 'on',
					'placeholder' => '',
					'callback'    => array( $this, 'instagram_checkbox_callback' ),
				),
				array(
					'id'          => 'instagram_show_counter',
					'label'       => __( 'Slide Counter', 'mixmymeals' ),
					'description' => __( 'Show a “2/9” counter in the top right corner of each slide.', 'mixmymeals' ),
					'default'     => 'on',
					'placeholder' => '',
					'callback'    => array( $this, 'instagram_checkbox_callback' ),
				),
				array(
					'id'          => 'instagram_show_diet_tag',
					'label'       => __( 'Diet Tag', 'mixmymeals' ),
					'description' => __( 'Show a “vegan”/“vegetarian” tag next to the badges when the recipe has that category.', 'mixmymeals' ),
					'default'     => 'on',
					'placeholder' => '',
					'callback'    => array( $this, 'instagram_checkbox_callback' ),
				),
				array(
					'id'          => 'instagram_badges',
					'label'       => __( 'Nutrition Badges', 'mixmymeals' ),
					'description' => __( 'Facts shown as badges on the day slides (per serving of the main recipe). A badge is hidden automatically when its value is missing.', 'mixmymeals' ),
					'default'     => array( 'calories', 'protein', 'time' ),
					'placeholder' => '',
					'options'     => array(
						'calories' => __( 'Calories (kcal)', 'mixmymeals' ),
						'protein'  => __( 'Protein', 'mixmymeals' ),
						'carbs'    => __( 'Carbohydrates', 'mixmymeals' ),
						'fat'      => __( 'Fat', 'mixmymeals' ),
						'time'     => __( 'Preparation time', 'mixmymeals' ),
					),
					'callback'    => array( $this, 'instagram_badges_callback' ),
				),
				array(
					'id'    => 'instagram_group_carousel',
					'type'  => 'heading',
					'label' => __( 'Cover & Call-to-Action', 'mixmymeals' ),
				),
				array(
					'id'          => 'instagram_show_cover',
					'label'       => __( 'Cover Slide', 'mixmymeals' ),
					'description' => __( 'Add a cover slide as the first image: the week’s best recipe photo with the plan title and facts. The scroll-stopper.', 'mixmymeals' ),
					'default'     => 'on',
					'placeholder' => '',
					'callback'    => array( $this, 'instagram_checkbox_callback' ),
				),
				array(
					'id'          => 'instagram_show_cta',
					'label'       => __( 'Closing Slide', 'mixmymeals' ),
					'description' => __( 'Add a closing call-to-action slide as the last image (“save this plan”, link in bio, handle).', 'mixmymeals' ),
					'default'     => 'on',
					'placeholder' => '',
					'callback'    => array( $this, 'instagram_checkbox_callback' ),
				),
				array(
					'id'          => 'instagram_cover_image_id',
					'label'       => __( 'Cover Photo', 'mixmymeals' ),
					'description' => __( 'Optional image for the cover slide. Leave empty to use the week’s best recipe photo automatically.', 'mixmymeals' ),
					'default'     => 0,
					'placeholder' => '',
					'callback'    => array( $this, 'placeholder_image_id_callback' ),
				),
				array(
					'id'          => 'instagram_cover_title',
					'label'       => __( 'Cover Title', 'mixmymeals' ),
					'description' => __( 'Headline on the cover slide. Leave empty to use the plan title.', 'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array( $this, 'text_callback' ),
				),
				array(
					'id'          => 'instagram_cover_subtitle',
					'label'       => __( 'Cover Subtitle', 'mixmymeals' ),
					'description' => __( 'Facts line under the cover title. Leave empty to build it automatically (diet, servings, average calories).', 'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array( $this, 'text_callback' ),
				),
				array(
					'id'          => 'instagram_cta_text',
					'label'       => __( 'Closing Headline', 'mixmymeals' ),
					'description' => __( 'Headline on the closing slide.', 'mixmymeals' ),
					'default'     => '',
					'placeholder' => __( 'Save this plan', 'mixmymeals' ),
					'callback'    => array( $this, 'text_callback' ),
				),
				array(
					'id'          => 'instagram_cta_subtext',
					'label'       => __( 'Closing Subtext', 'mixmymeals' ),
					'description' => __( 'Smaller line below the closing headline.', 'mixmymeals' ),
					'default'     => '',
					'placeholder' => __( 'Full recipes & shopping list — link in bio', 'mixmymeals' ),
					'callback'    => array( $this, 'text_callback' ),
				),
				array(
					'id'          => 'instagram_handle',
					'label'       => __( 'Instagram Handle', 'mixmymeals' ),
					'description' => __( 'Shown on the day, cover and closing slides. Leave empty to use the connected account’s username.', 'mixmymeals' ),
					'default'     => '',
					'placeholder' => '@yourhandle',
					'callback'    => array( $this, 'instagram_handle_callback' ),
				),
				array(
					'id'    => 'instagram_group_finetune',
					'type'  => 'heading',
					'label' => __( 'Fine-Tuning', 'mixmymeals' ),
				),
				array(
					'id'          => 'instagram_font_scale',
					'label'       => __( 'Font Size (%)', 'mixmymeals' ),
					'description' => __( 'Scales all text on the slides, 50–150%.', 'mixmymeals' ),
					'default'     => 100,
					'placeholder' => '100',
					'min'         => 50,
					'max'         => 150,
					'callback'    => array( $this, 'instagram_font_scale_callback' ),
				),
				array(
					'id'          => 'instagram_scrim_opacity',
					'label'       => __( 'Photo Darkening (%)', 'mixmymeals' ),
					'description' => __( 'Strength of the dark gradient over the photo on day slides, 0–100%.', 'mixmymeals' ),
					'default'     => 60,
					'placeholder' => '60',
					'min'         => 0,
					'max'         => 100,
					'callback'    => array( $this, 'instagram_scrim_opacity_callback' ),
				),
				array(
					'id'          => 'instagram_corner_radius',
					'label'       => __( 'Corner Radius (px)', 'mixmymeals' ),
					'description' => __( 'Roundness of badges and bars, 0–60 pixels.', 'mixmymeals' ),
					'default'     => 24,
					'placeholder' => '24',
					'min'         => 0,
					'max'         => 60,
					'callback'    => array( $this, 'instagram_corner_radius_callback' ),
				),
				array(
					'id'    => 'instagram_group_posting',
					'type'  => 'heading',
					'label' => __( 'Publishing', 'mixmymeals' ),
				),
				array(
					'id'          => 'instagram_hashtags',
					'label'       => __( 'Hashtags', 'mixmymeals' ),
					'description' => __( 'Appended to the suggested caption in the meta box.', 'mixmymeals' ),
					'default'     => '',
					'placeholder' => '#mealprep #wochenplan',
					'callback'    => array( $this, 'instagram_hashtags_callback' ),
				),
				array(
					'id'    => 'instagram_group_cleanup',
					'type'  => 'heading',
					'label' => __( 'Cleanup', 'mixmymeals' ),
				),
				array(
					'id'          => 'instagram_retention_days',
					'label'       => __( 'Retention (days)', 'mixmymeals' ),
					'description' => __( 'Generated images are deleted automatically after this many days.', 'mixmymeals' ),
					'default'     => 7,
					'placeholder' => '7',
					'min'         => 1,
					'max'         => 365,
					'callback'    => array( $this, 'instagram_retention_days_callback' ),
				),
			),
		);

		return apply_filters(
			$this->parent->_token . '_settings_fields',
			$settings
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
					break;
				default:
					return;
			}

		}
	}

	/**
	 * Prepare default settings page arguments
	 *
	 * @return mixed|void
	 */
	private function menu_settings(): mixed {
		return apply_filters(
			$this->base . 'menu_settings',
			array(
				'location'    => 'options',
				// Possible settings: options, menu, submenu.
				'parent_slug' => 'options-general.php',
				'page_title'  => __( 'MixMyMeals', 'mixmymeals' ),
				'menu_title'  => __( 'MixMyMeals', 'mixmymeals' ),
				'capability'  => 'manage_options',
				'menu_slug'   => $this->parent->_token . '_settings',
				'function'    => array( $this, 'settings_page' ),
				'icon_url'    => '',
				'position'    => null,
			)
		);
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
	 * Add settings link to plugin list table
	 *
	 * @param   array $links  Existing links.
	 *
	 * @return array        Modified links.
	 */
	public function add_settings_link( array $links ): array {
		$settings_link = '<a href="options-general.php?page='
		                 . $this->parent->_token . '_settings">'
		                 . esc_html__( 'Settings', 'mixmymeals' ) . '</a>';
		$links[] = $settings_link;

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

			foreach ( $this->settings as $section => $data ) {

				if ( $current_section && $current_section !== $section ) {
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

				if ( ! $current_section ) {
					break;
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

		echo '<h3 class="mmm-settings-group-title">'
			. esc_html( (string) ( $field['label'] ?? '' ) )
			. '</h3>';

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

		// Show page tabs.
		if ( is_array( $this->settings ) && 1 < count( $this->settings ) ) {

			$html .= '<h2 class="nav-tab-wrapper">' . "\n";

			// Nonce check to meet WP Plugin Check requirements...
			if ( isset( $_GET['tab'] ) && isset( $_GET['settings_nonce'] )
			     && wp_verify_nonce(
				     sanitize_key( wp_unslash( $_GET['settings_nonce'] ) ),
				     'mixmymeals_settings_nonce_action'
			     )
			) {
				$current_tab
					= sanitize_text_field( wp_unslash( $_GET['tab'] ) );
			}

			$c = 0;
			foreach ( $this->settings as $section => $data ) {

				// Set tab class:
				$class = 'nav-tab';
				if ( ! isset( $current_tab ) ) {
					if ( 0 === $c ) {
						$class .= ' nav-tab-active';
					}
				} elseif ( isset( $current_tab )
				           && $section == $current_tab
				) {

					$class .= ' nav-tab-active';
				}

				// Set tab link.
				$settings_nonce
					            = wp_create_nonce( 'mixmymeals_settings_nonce_action' );
				$tab_link       = add_query_arg(
					array(
						'tab'            => $section,
						'settings_nonce' => $settings_nonce,
					)
				);
				if ( isset( $_GET['settings-updated'] ) ) { //phpcs:ignore
					$tab_link = remove_query_arg(
						'settings-updated',
						$tab_link
					);
				}

				// Output tab.
				$html .= '<a href="' . $tab_link . '" class="'
				         . esc_attr( $class ) . '">'
				         . esc_html( $data['title'] ) . '</a>' . "\n";

				++$c;

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

		// Mailer test box + send log: only on the E-Mail tab (first tab is default).
		$active_tab = '' !== $tab ? $tab : (string) array_key_first( $this->settings );
		if ( 'mixmymeals-email' === $active_tab ) {
			$html .= $this->render_test_email_box();
			$html .= $this->render_mail_log();
		}

		$html .= '</div>' . "\n";
		$html .= '</div>' . "\n";
		$html .= '</div>' . "\n";

		echo $html; //phpcs:ignore
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
	public function page_id_to_redirect_if_new_plan_is_draft_callback( mixed $input ): int {
		return absint( $input );
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

	/**
	 * Sanitizes the Instagram image format option.
	 *
	 * @param mixed $input Raw value.
	 * @return string Either '4x5' or '1x1'.
	 */
	public function instagram_format_callback( mixed $input ): string {
		return in_array( $input, array( '1x1', '9x16' ), true ) ? (string) $input : '4x5';
	}

	/**
	 * Sanitizes the Instagram style preset.
	 *
	 * @param mixed $input Raw value.
	 * @return string One of custom|frisch|editorial|kraeftig|minimal.
	 */
	public function instagram_style_preset_callback( mixed $input ): string {
		return in_array( $input, array( 'frisch', 'editorial', 'kraeftig', 'minimal' ), true ) ? (string) $input : 'custom';
	}

	/**
	 * Sanitizes the Instagram logo position.
	 *
	 * @param mixed $input Raw value.
	 * @return string Either left or center.
	 */
	public function instagram_logo_position_callback( mixed $input ): string {
		return 'center' === $input ? 'center' : 'left';
	}

	/**
	 * Sanitizes the Instagram logo size (percent).
	 *
	 * @param mixed $input Raw value.
	 * @return int Value between 50 and 200.
	 */
	public function instagram_logo_size_callback( mixed $input ): int {
		return max( 50, min( 200, absint( $input ) ?: 100 ) );
	}

	/**
	 * Sanitizes the Instagram day-slide layout.
	 *
	 * @param mixed $input Raw value.
	 * @return string One of full|magazine|split|card.
	 */
	public function instagram_day_layout_callback( mixed $input ): string {
		return in_array( $input, array( 'magazine', 'split', 'card' ), true ) ? (string) $input : 'full';
	}

	/**
	 * Sanitizes the Instagram full-bleed overlay style.
	 *
	 * @param mixed $input Raw value.
	 * @return string One of gradient|plate|minimal.
	 */
	public function instagram_overlay_style_callback( mixed $input ): string {
		return in_array( $input, array( 'plate', 'minimal' ), true ) ? (string) $input : 'gradient';
	}

	/**
	 * Sanitizes an Instagram color option.
	 *
	 * @param mixed $input Raw value.
	 * @return string Hex color or '' (renderer falls back to its default).
	 */
	public function instagram_color_callback( mixed $input ): string {
		return (string) sanitize_hex_color( (string) $input );
	}

	/**
	 * Sanitizes an Instagram element toggle.
	 *
	 * @param mixed $input Raw value.
	 * @return string Either 'on' or ''.
	 */
	public function instagram_checkbox_callback( mixed $input ): string {
		return $input ? 'on' : '';
	}

	/**
	 * Sanitizes the Instagram font scale (percent).
	 *
	 * @param mixed $input Raw value.
	 * @return int Value between 50 and 150.
	 */
	public function instagram_font_scale_callback( mixed $input ): int {
		return max( 50, min( 150, absint( $input ) ?: 100 ) );
	}

	/**
	 * Sanitizes the Instagram photo darkening strength (percent).
	 *
	 * @param mixed $input Raw value.
	 * @return int Value between 0 and 100.
	 */
	public function instagram_scrim_opacity_callback( mixed $input ): int {
		return min( 100, absint( $input ) );
	}

	/**
	 * Sanitizes the Instagram corner radius (pixels).
	 *
	 * @param mixed $input Raw value.
	 * @return int Value between 0 and 60.
	 */
	public function instagram_corner_radius_callback( mixed $input ): int {
		return min( 60, absint( $input ) );
	}

	/**
	 * Sanitizes the Instagram meal type selection.
	 *
	 * @param mixed $input Raw value.
	 * @return array Valid meal type keys.
	 */
	public function instagram_meal_types_callback( mixed $input ): array {

		if ( ! is_array( $input ) ) {
			return array();
		}

		$valid = array_keys( Mixmymeals_Meal::get_plaintext_meal_type_map() );

		return array_values( array_intersect( $valid, array_map( 'sanitize_key', $input ) ) );
	}

	/**
	 * Sanitizes the Instagram nutrition badge selection.
	 *
	 * @param mixed $input Raw value.
	 * @return array Valid badge keys, in a stable order.
	 */
	public function instagram_badges_callback( mixed $input ): array {

		if ( ! is_array( $input ) ) {
			return array();
		}

		$valid = array( 'calories', 'protein', 'carbs', 'fat', 'time' );

		return array_values( array_intersect( $valid, array_map( 'sanitize_key', $input ) ) );
	}

	/**
	 * Sanitizes the Instagram handle (stored without a leading '@').
	 *
	 * @param mixed $input Raw value.
	 * @return string
	 */
	public function instagram_handle_callback( mixed $input ): string {
		return ltrim( sanitize_text_field( (string) $input ), '@' );
	}

	/**
	 * Sanitizes the Instagram hero meal type.
	 *
	 * @param mixed $input Raw value.
	 * @return string A valid meal type key.
	 */
	public function instagram_hero_meal_type_callback( mixed $input ): string {

		$valid = array_keys( Mixmymeals_Meal::get_plaintext_meal_type_map() );

		return in_array( $input, $valid, true ) ? (string) $input : 'dinner';
	}

	/**
	 * Sanitizes the Instagram image retention period.
	 *
	 * @param mixed $input Raw value.
	 * @return int Days, at least 1.
	 */
	public function instagram_retention_days_callback( mixed $input ): int {
		return min( 365, max( 1, absint( $input ) ) );
	}




	/**
	 * Sanitizes the Instagram hashtag block.
	 *
	 * @param mixed $input Raw value.
	 * @return string
	 */
	public function instagram_hashtags_callback( mixed $input ): string {
		return sanitize_textarea_field( (string) $input );
	}

}
