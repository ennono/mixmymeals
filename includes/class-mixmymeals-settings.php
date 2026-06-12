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
			'description' => __( 'Setup general things.',
				'mixmymeals' ),
			'fields'      => array(
				array(
					'id'          => 'currency',
					'label'       => __( 'Currency', 'mixmymeals' ),
					'description' => __( 'Currency symbol used for price display.', 'mixmymeals' ),
					'default'     => '€',
					'placeholder' => '€',
					'callback'    => array( $this, 'text_callback' ),
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
					'label'       => __( 'Altervative Redirect', 'mixmymeals' ),
					'description' => __( 'Where to send vistors after new plan has been saved as draft.', 'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array(
						$this,
						'page_id_to_redirect_if_new_plan_is_draft_callback'
					),
				),
				array(
					'id'          => 'placeholder_image_id',
					'label'       => __( 'Placeholder Image', 'mixmymeals' ),
					'description' => __( 'Choose an image to show if there is no recipe image.',
						'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array(
						$this,
						'placeholder_image_id_callback'
					),
				),
			),
		);

		$settings['mixmymeals-recipes'] = array(
			'title'       => __( 'Recipes', 'mixmymeals' ),
			'description' => __( 'Setup recipes.',
				'mixmymeals' ),
			'fields'      => array(
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
			),
		);

		$settings['mixmymeals-ingredients'] = array(
			'title'       => __( 'Ingredients', 'mixmymeals' ),
			'description' => __( 'Setup ingredients.', 'mixmymeals' ),
			'fields'      => array(
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

		$settings['mixmymeals-meals'] = array(
			'title'       => __( 'Meals', 'mixmymeals' ),
			'description' => __( 'Setup meals.',
				'mixmymeals' ),
			'fields'      => array(
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
			),
		);

		$settings['mixmymeals-meal-plans'] = array(
			'title'       => __( 'Meal Plans', 'mixmymeals' ),
			'description' => __( 'Setup meal plans.',
				'mixmymeals' ),
			'fields'      => array(
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
		$html .= '</div>' . "\n";
		$html .= '</div>' . "\n";
		$html .= '</div>' . "\n";

		echo $html; //phpcs:ignore
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
	 * Sanitizes the enable-cleanup checkbox setting.
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
	 * Sanitizes the redirect page ID setting for draft-saved visitor plans.
	 *
	 * @param mixed $input Raw input value.
	 * @return int
	 */
	public function page_id_to_redirect_if_new_plan_is_draft_callback( mixed $input ): int {
		return absint( $input );
	}

}
