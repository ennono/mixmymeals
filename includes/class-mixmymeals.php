<?php
/**
 * Main plugin class file.
 *
 * @package MixMyMeals Plugin/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class.
 */
class Mixmymeals {

	/**
	 * The single instance of Mixmymeals.
	 *
	 * @var     object
	 * @access  private
	 */
	private static $_instance = null; //phpcs:ignore

	/**
	 * Local instance of Mixmymeals_Admin_API
	 *
	 * @var Mixmymeals_Admin_API|null
	 */
	public $admin = null;

	/**
	 * Settings class object
	 *
	 * @var     object
	 * @access  public
	 */
	public $settings = null;

	/**
	 * Admin pages class object
	 *
	 * @var     object
	 * @access  public
	 */
	//public $admin_pages = null;

	/**
	 * The version number.
	 *
	 * @var     string
	 * @access  public
	 */
	public $_version; //phpcs:ignore

	/**
	 * The token.
	 *
	 * @var     string
	 * @access  public
	 */
	public $_token; //phpcs:ignore

	/**
	 * The main plugin file.
	 *
	 * @var     string
	 * @access  public
	 */
	public $file;

	/**
	 * The main plugin directory.
	 *
	 * @var     string
	 * @access  public
	 */
	public $dir;

	/**
	 * The plugin assets directory.
	 *
	 * @var     string
	 * @access  public
	 */
	public $assets_dir;

	/**
	 * The plugin assets URL.
	 *
	 * @var     string
	 * @access  public
	 */
	public $assets_url;

	/**
	 * Suffix for JavaScripts.
	 *
	 * @var     string
	 * @access  public
	 */
	public $script_suffix;

	/**
	 * Constructor funtion.
	 *
	 * @param   string  $file     File constructor.
	 * @param   string  $version  Plugin version.
	 */
	public function __construct( string $file = '', string $version = '0.0.1'
	) {
		$this->_version = $version;
		$this->_token   = 'mixmymeals';

		// Load plugin environment variables.
		$this->file       = $file;
		$this->dir        = dirname( $this->file );
		$this->assets_dir = trailingslashit( $this->dir ) . 'assets';
		$this->assets_url = esc_url( trailingslashit( plugins_url( '/assets/',
			$this->file ) ) );

		$this->script_suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? ''
			: '.min';

		register_activation_hook( $this->file, array( $this, 'install' ) );

		// Load frontend JS & CSS.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Load admin JS & CSS.
		add_action( 'admin_enqueue_scripts',
			array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts',
			array( $this, 'admin_enqueue_styles' ) );

		// Remove "Private" prefix
		add_filter( 'private_title_format',
			array( $this, 'change_private_title_prefix' ) );

		// Disable comments for vistors who are not logged in
		// WARNING: This assumes that the theme uses comments_open/pings_open functions!!
		// Otherwise, it probably won't work properly.
        /*
		add_filter( 'comments_open', array( $this, 'disable_comments' ), 20,
			2 );
		add_filter( 'pings_open', array( $this, 'disable_comments' ), 20, 2 );
		add_filter( 'get_comment_author',
			array( $this, 'extend_comment_author' ), 20, 3 );
        */

		// Auto logout pending users immediatly after login
        /*
		add_action( 'wp_login', array( $this, 'logout_pending_gardeners' ), 10,
			2 );
        */

        // Hide Admin Bar:
		add_filter( 'show_admin_bar', array( $this, 'hide_admin_bar' ) );
		add_action( 'admin_init', array( $this, 'hide_admin_dashboard' ) );

		//add_action ('save_post', array ($this, 'send_new_post_nofification'), 10, 3);
        /*
		add_action( 'transition_post_status',
			array( $this, 'send_new_post_nofification' ), 10, 3 );
        */

        /*
		add_filter( 'the_content',
			array( $this, 'add_likes_shortcode_to_selected_posttypes' ) );

		add_action( 'wp_footer', array( $this, 'printer_footer_credits' ),
			100 );

		add_action( 'template_redirect',
			array( $this, 'redirect_private_post_404_to_login_page' ) );

		add_action( 'wp_trash_post',
			array( 'Mixmymeals_Plot', 'remove_gardener_assignments' ) );
		add_action( 'delete_post',
			array( 'Mixmymeals_Plot', 'remove_gardener_assignments' ) );
        */

		// Load API for generic admin functions.
		if ( is_admin() ) {
			$this->admin = new Mixmymeals_Admin_API();
		}

		// Handle localisation.
		$this->load_plugin_textdomain();
		add_action( 'init', array( $this, 'load_localisation' ), 0 );

	}

	/**
	 * Load plugin textdomain
	 *
	 * @access  public
	 * @return  void
	 * @since   1.0.0
	 */
	public function load_plugin_textdomain() {
		$domain = 'mixmymeals';

		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

		load_textdomain( $domain,
			WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale
			. '.mo' );
		load_plugin_textdomain( $domain, false,
			dirname( plugin_basename( $this->file ) ) . '/lang/' );
	}

	/**
	 * Main Mixmymeals Instance
	 *
	 * Ensures only one instance of Mixmymeals is loaded or can be loaded.
	 *
	 * @param   string  $file     File instance.
	 * @param   string  $version  Version parameter.
	 *
	 * @return Object Mixmymeals instance
	 * @static
	 */
	public static function instance(
		string $file = '', string $version = '0.0.1'
	) {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $file, $version );
		}

		return self::$_instance;
	}

	/**
	 * Wrapper function to add custom user fields.
	 *
	 * @return Mixmymeals_Userfields
	 */
    /*
	public function add_userfields() {
		return new Mixmymeals_Userfields ();
	}
    */

	/**
	 * Wrapper function to add post meta boxes.
	 *
	 * @return Mixmymeals_Post_Meta
	 */
	public function add_post_meta() {
		return new Mixmymeals_Post_Meta ();
	}

	/**
	 * Wrapper function to add custom user roles.
	 *
	 * @return Mixmymeals_User_Roles
	 */
	public function add_user_roles() {
		return new Mixmymeals_User_Roles ();
	}


	public function add_post_types() {
		return new Mixmymeals_Post_Types();
	}

	public function add_taxonomies() {
		return new Mixmymeals_Taxonomies();
	}

	public function add_shortcodes() {
		return new Mixmymeals_Shortcodes();
	}

	/**
	 * Load frontend CSS.
	 *
	 * @access  public
	 * @return void
	 * @since   1.0.0
	 */
	public function enqueue_styles() {

		wp_register_style( $this->_token . '-frontend',
			esc_url( $this->assets_url ) . 'css/frontend.css', array(),
			$this->_version );
		wp_enqueue_style( $this->_token . '-frontend' );

	}

	/**
	 * Load frontend Javascript.
	 *
	 * @access  public
	 * @return  void
	 * @since   1.0.0
	 */
	public function enqueue_scripts() {
		wp_register_script( $this->_token . '-frontend',
			esc_url( $this->assets_url ) . 'js/frontend' . $this->script_suffix
			. '.js', array( 'jquery' ), $this->_version, true );
		wp_enqueue_script( $this->_token . '-frontend' );

		/*
		wp_localize_script( $this->_token . '-frontend', 'mixmymeals',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'mixmymeals-likes-ajax-nonce' ),
			)
		);
		*/

	}

	/**
	 * Admin enqueue style.
	 *
	 * @param   string  $hook  Hook parameter.
	 *
	 * @return void
	 */
	public function admin_enqueue_styles( string $hook = '' ) {

		//wp_enqueue_style( 'wp-color-picker' );

		wp_register_style( $this->_token . '-admin',
			esc_url( $this->assets_url ) . 'css/admin.css', array(),
			$this->_version );
		wp_enqueue_style( $this->_token . '-admin' );

	}

	/**
	 * Load admin Javascript.
	 *
	 * @access  public
	 *
	 * @param   string  $hook  Hook parameter.
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function admin_enqueue_scripts( string $hook = '' ) {
		//wp_enqueue_media();
		global $post;

		wp_register_script( $this->_token . '-admin',
			esc_url( $this->assets_url ) . 'js/admin' . $this->script_suffix
			. '.js', array( 'jquery' ), $this->_version, true );
		wp_enqueue_script( $this->_token . '-admin' );

        /*
		wp_localize_script( $this->_token . '-admin', 'kleingarten_admin',
			array(
				'ajaxurl'                         => admin_url( 'admin-ajax.php' ),
				'nonce'                           => wp_create_nonce( 'kleingarten-admin-ajax-nonce' ),
				'trans_active'                    => esc_html( __( 'Active',
					'mixmymeals' ) ),
				'trans_deactivate'                => esc_html( __( 'Deactivate',
					'mixmymeals' ) ),
				'trans_delete'                    => esc_html( __( 'Delete',
					'mixmymeals' ) ),
				'trans_token'                     => esc_html( __( 'Token',
					'mixmymeals' ) ),
				'trans_status'                    => esc_html( __( 'Status',
					'mixmymeals' ) ),
				'trans_action'                    => esc_html( __( 'Action',
					'mixmymeals' ) ),
				'trans_remove'                    => esc_html( __( 'Remove',
					'mixmymeals' ) ),
				'trans_eg_water'                  => esc_html( __( 'e.g. Water',
					'mixmymeals' ) ),
				'trans_eg_l'                      => esc_html( __( 'e.g. l',
					'mixmymeals' ) ),
				'trans_eg_245'                    => esc_html( __( 'e.g. 2.45',
					'mixmymeals' ) ),
				'trans_eg_at_least_one_row'       => esc_html( __( 'There must be at least one row.',
					'mixmymeals' ) ),
				'trans_all_plots'                 => esc_html( __( 'All Plots',
					'mixmymeals' ) ),
				'trans_individual_plots'          => esc_html( __( 'Individual Plots',
					'mixmymeals' ) ),
				'trans_all_gardeners'             => esc_html( __( 'All gardeners',
					'mixmymeals' ) ),
				'trans_individual_gardeners'      => esc_html( __( 'Individual members',
					'mixmymeals' ) ),
				'trans_eg_m3'                     => esc_html( __( 'e.g. m3',
					'mixmymeals' ) ),
				'trans_eg_150'                    => esc_html( __( 'e.g. 150',
					'mixmymeals' ) ),
				'trans_eg_123456'                 => esc_html( __( 'e.g. 123456',
					'mixmymeals' ) ),
				'trans_error_on_loading_item_row' => esc_html( __( 'Could not load items. Reloading page might help.',
					'mixmymeals' ) ),
			)
		);
        */

        /*
		wp_enqueue_script( $this->_token . '-admin-color-picker',
			esc_url( $this->assets_url ) . 'js/' . 'colorpicker.min.js',
			array( 'wp-color-picker' ), $this->_version, true );
        */

		wp_localize_script(
			'mixmymeals-admin',
			'mixmymealsAdmin',
			array(
				'ajaxurl' => admin_url('admin-ajax.php'),
				'nonce'   => wp_create_nonce('mixmymeals_admin_nonce'),
			)
		);

		wp_localize_script( 'mixmymeals-admin', 'mixmymealsRecipes', array_map( function( $r ) {
			return array( 'ID' => $r->ID, 'post_title' => $r->post_title );
		}, Mixmymeals_Recipe::get_all_recipes() ) );

		// ATTENTION: DO NOT RENAME "mixmymeals_daily_recipes" UNLESS YOU RENAMED THE OPTION in class-mixmymeals-admin-api.php!!!
		wp_localize_script( 'mixmymeals-admin', 'mixmymeals_recipe_selection_option_name', 'mixmymeals_daily_recipes' );

	}

	/**
	 * Load plugin localisation
	 *
	 * @access  public
	 * @return  void
	 */
	public function load_localisation() {
		load_plugin_textdomain( 'mixmymeals', false,
			dirname( plugin_basename( $this->file ) ) . '/lang/' );
	}

	/**
	 * Cloning is forbidden.
	 *
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__,
			esc_html( __( 'Cloning of MixMyMeals is forbidden',
				'mixmymeals' ) ), esc_attr( $this->_version ) );

	}

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__,
			esc_html( __( 'Unserializing instances of MixMyMeals is forbidden',
				'mixmymeals' ) ), esc_attr( $this->_version ) );
	}

	/**
	 * Installation. Runs on activation.
	 *
	 * @access  public
	 * @return  void
	 * @since   1.0.0
	 */
	public function install() {
		$this->_log_version_number();
	}

	/**
	 * Log the plugin version number.
	 *
	 * @access  public
	 * @return  void
	 */
	private function _log_version_number() { //phpcs:ignore
		update_option( $this->_token . '_version', $this->_version );
	}

	/**
	 * Change "Private" prefix
	 *
	 * @access  public
	 * @return  string Custom prefix
	 */
	public function change_private_title_prefix() {
		// translators: Post title
		return __( 'For members only: %s', 'mixmymeals' );
	}

	/**
	 * Hide admin bar
	 *
	 * @access  public
	 * @return  bool
	 */
	public function hide_admin_bar() {

		// Hide admin bar for specific roles.
		// Native WordPress roles will still be shown the bar.
		$roles = [ 'kleingarten_pending', 'kleingarten_allotment_gardener' ];

		$response = false;

		if ( is_user_logged_in() ) {

			$user             = wp_get_current_user();
			$currentUserRoles = $user->roles;
			$isMatching       = array_intersect( $currentUserRoles, $roles );
			if ( ! $isMatching ) {
				$response = true;
			}

		}

		return $response;
	}

	/**
	 * Hide admin dashboard from visitors.
	 *
	 * @access  public
	 * @return  void
	 * @since   1.0.0
	 */
	public function hide_admin_dashboard() {

		if ( is_admin() && ! current_user_can( 'administrator' )
		     // Calling admin page but being no admin?
		     && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX )
		     // Not doing AJAX stuff?
		     && isset( $_SERVER['PHP_SELF'] )
		     && ! str_contains( sanitize_urL( wp_unslash( $_SERVER['PHP_SELF'] ) ),
				'admin-post.php' )     // Not just using admin-post.php for form processing?
		) {

			// Then redirect to homepage. /wp-admin is not the right place for you.
			wp_safe_redirect( home_url() );
			exit;
		}

	}

}
