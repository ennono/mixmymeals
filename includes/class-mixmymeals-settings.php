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
	 * @param   object  $parent  Parent object.
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
		add_filter( $this->base . 'menu_settings',
			array( $this, 'configure_settings' ) );
	}

	/**
	 * Main Mixmymeals_Settings Instance
	 *
	 * Ensures only one instance of Mixmymeals_Settings is loaded or can be loaded.
	 *
	 * @param   object  $parent  Object instance.
	 *
	 * @return object Mixmymeals_Settings instance
	 * @static
	 */
	public static function instance( object $parent ) {
		
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
	public function init_settings() {
		$this->settings = $this->settings_fields();
	}

	/**
	 * Build settings fields
	 *
	 * @return array Fields to be displayed on settings page
	 */
	private function settings_fields() {

		$settings['mixmymeals-recipe-calendar'] = array(
			'title'       => __( 'Daily Recipes', 'mixmymeals' ),
			'description' => __( 'Select recipes for each day.',
				'mixmymeals' ),
			'fields'      => array(
				array(
					'id'          => 'daily_recipes',
					'label'       => __( 'Daily Recipes', 'mixmymeals' ),
					'description' => __( 'Recipes for every day.',
						'mixmymeals' ),
					'default'     => '10',
					'placeholder' => '',
					'callback'    => array(
						$this,
						// ATTENTION:
						// IF YOU RENAME THIS YOU WILL HAVE TO RENAME
						// THE CORRESPONDING ADMIN AJAX LOCALIZATION IN
						// "class-mixmymeals.php"!
						'daily_recipes_callback'
					),
				),
			),
		);

		$settings['mixmymeals-advanced'] = array(
			'title'       => __( 'Members', 'mixmymeals' ),
			'description' => __( 'Control how the plugin handles user accounts.',
				'mixmymeals' ),
			'fields'      => array(
				/*
				array(
					'id'          => 'membership_billing_items',
					'label'       => __( 'Flat rate billing items',
						'mixmymeals' ),
					'description' => __( 'Define billing items for memberships.',
						'mixmymeals' ),
					'default'     => '',
					'placeholder' => '',
					'callback'    => array(
						$this,
						'billing_items_callback'
					),
				),
				*/
			),
		);

		return apply_filters( $this->parent->_token . '_settings_fields',
			$settings );
	}

	/**
	 * Add settings page to admin menu
	 *
	 * @return void
	 */
	public function add_menu_item() {

		$args = $this->menu_settings();

		// Do nothing if wrong location key is set.
		if ( is_array( $args ) && isset( $args['location'] )
		     && function_exists( 'add_' . $args['location'] . '_page' )
		) {
			switch ( $args['location'] ) {
				case 'options':
				case 'submenu':
					$page = add_submenu_page( $args['parent_slug'],
						$args['page_title'], $args['menu_title'],
						$args['capability'], $args['menu_slug'],
						$args['function'] );
					break;
				case 'menu':
					$page = add_menu_page( $args['page_title'],
						$args['menu_title'], $args['capability'],
						$args['menu_slug'], $args['function'],
						$args['icon_url'], $args['position'] );
					break;
				default:
					return;
			}

			/*
			add_action( 'admin_print_styles-' . $page,
				array( $this, 'settings_assets' ) );
			*/
		}
	}

	/**
	 * Prepare default settings page arguments
	 *
	 * @return mixed|void
	 */
	private function menu_settings() {
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
	 * @param   array  $settings  Settings array.
	 *
	 * @return array
	 */
	public function configure_settings( array $settings = array() ) {
		return $settings;
	}

	/**
	 * Load settings JS & CSS
	 *
	 * @return void
	 */
	public function settings_assets() {

		// We're including the farbtastic script & styles here because they're needed for the colour picker
		// If you're not including a colour picker field then you can leave these calls out as well as the farbtastic dependency for the wpt-admin-js script below.
		//wp_enqueue_style( 'farbtastic' );
		//wp_enqueue_script( 'farbtastic' );

		// We're including the WP media scripts here because they're needed for the image upload field.
		// If you're not including an image upload then you can leave this function call out.
		//wp_enqueue_media();

		//wp_register_script( $this->parent->_token . '-settings-js', $this->parent->assets_url . 'js/settings' . $this->parent->script_suffix . '.js', array( 'farbtastic', 'jquery' ), '1.0.0', true );
		//wp_enqueue_script( $this->parent->_token . '-settings-js' );
	}

	/**
	 * Add settings link to plugin list table
	 *
	 * @param   array  $links  Existing links.
	 *
	 * @return array        Modified links.
	 */
	public function add_settings_link( array $links ) {
		$settings_link = '<a href="options-general.php?page='
		                 . $this->parent->_token . '_settings">'
		                 . esc_html__( 'Settings', 'mixmymeals' ) . '</a>';
		//array_push( $links, $settings_link ); // Original
		$links[] = $settings_link;             // PHPStrom's proposal

		return $links;
	}

	/**
	 * Register plugin settings
	 *
	 * @return void
	 */
	public function register_settings() {
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
				add_settings_section( $section, $data['title'],
					array( $this, 'settings_section' ),
					$this->parent->_token . '_settings' );

				foreach ( $data['fields'] as $field ) {

					// Validation callback for field.
					/*
					$validation = '';
					if ( isset( $field['callback'] ) ) {
						$validation = $field['callback'];
					}
					*/

					$args = array();
					if ( isset( $field['callback'] ) ) {
						$args['sanitize_callback'] = $field['callback'];
					}
					if ( isset( $field['default'] ) ) {
						$args['default'] = $field['default'];
					}

					// Register field.
					$option_name = $this->base . $field['id'];
					//register_setting( $this->parent->_token . '_settings',
					//	$option_name, $validation );
					register_setting( $this->parent->_token . '_settings',
						$option_name, $args );

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
	 * @param   array  $section  Array of section ids.
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
	public function settings_page() {

		// Build page HTML.
		$html = '<div class="wrap" id="' . $this->parent->_token . '_settings">'
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
			if ( isset( $_GET['tab'] ) && isset ( $_GET['settings_nonce'] )
			     && wp_verify_nonce( sanitize_key( wp_unslash( $_GET['settings_nonce'] ) ),
					'mixmymeals_settings_nonce_action' )
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
				} else {
					if ( isset( $current_tab )
					     && $section == $current_tab
					) {
						$class .= ' nav-tab-active';
					}
				}


				// Set tab link.
				$settings_nonce
					      = wp_create_nonce( 'mixmymeals_settings_nonce_action' );
				$tab_link = add_query_arg( array(
						'tab'            => $section,
						'settings_nonce' => $settings_nonce,
					)
				);
				if ( isset( $_GET['settings-updated'] ) ) { //phpcs:ignore
					$tab_link = remove_query_arg( 'settings-updated',
						$tab_link );
				}

				// Output tab.
				$html .= '<a href="' . $tab_link . '" class="'
				         . esc_attr( $class ) . '">'
				         . esc_html( $data['title'] ) . '</a>' . "\n";

				++ $c;

			}

			$html .= '</h2>' . "\n";
		}


		//echo $this->parent->_token . '_settings' . '-options';
		$html .= '<form method="post" action="options.php" enctype="multipart/form-data">'
		         . "\n";

		// Get settings fields.
		ob_start();
		settings_fields( $this->parent->_token . '_settings' );
		do_settings_sections( $this->parent->_token . '_settings' );
		$html .= ob_get_clean();

		$html .= '<p class="submit">' . "\n";
		//$html .= wp_nonce_field( 'mixmymeals_settings_nonce_action', 'mixmymeals_settings_nonce', false, false );
		$html .= '<input type="hidden" name="tab" value="' . esc_attr( $tab )
		         . '" />' . "\n";
		$html .= '<input name="Submit" type="submit" class="button-primary" value="'
		         . esc_attr__( 'Save Settings', 'mixmymeals' ) . '" />'
		         . "\n";
		$html .= '</p>' . "\n";
		$html .= '</form>' . "\n";
		$html .= '</div>' . "\n";

		/*
		$html .= '<div class="mixmymeals-admin-sidebar">';
		$html .= '<a target="_blank" href="https://www.wp-Mixmymeals.de">';
		$html .= '<img src=' . esc_url( plugin_dir_url( __DIR__ ) )
		         . 'assets/Mixmymeals_Logo_200px.png>';
		$html .= '</a>';
		$html .= '</div>' . "\n";
		*/

		$html .= '</div>' . "\n";
		$html .= '</div>' . "\n";

		echo $html; //phpcs:ignore
	}

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__,
			esc_html( __( 'Cloning of Mixmymeals_API is forbidden.',
				'mixmymeals' ) ), esc_attr( $this->parent->_version ) );
	} // End __clone()

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__,
			esc_html( __( 'Unserializing instances of Mixmymeals_API is forbidden.',
				'mixmymeals' ) ), esc_attr( $this->parent->_version ) );
	} // End __wakeup()

}
