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
	 * @param   string $file     File constructor.
	 * @param   string $version  Plugin version.
	 */
	public function __construct( string $file = '', string $version = '0.0.1' ) {
		$this->_version = $version;
		$this->_token   = 'mixmymeals';

		// Load plugin environment variables.
		$this->file       = $file;
		$this->dir        = dirname( $this->file );
		$this->assets_dir = trailingslashit( $this->dir ) . 'assets';
		$this->assets_url = esc_url(
			trailingslashit(
				plugins_url(
					'/assets/',
					$this->file
				)
			)
		);

		$this->script_suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? ''
			: '.min';

		register_activation_hook( $this->file, array( $this, 'install' ) );
		register_deactivation_hook( $this->file, 'flush_rewrite_rules' );

		// Deferred one-time rewrite flush after activation (post types must
		// be registered first, hence late init priority).
		add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ), 99 );

		// Load frontend JS & CSS.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Load admin JS & CSS.
		add_action(
			'admin_enqueue_scripts',
			array( $this, 'admin_enqueue_scripts' )
		);
		add_action(
			'admin_enqueue_scripts',
			array( $this, 'admin_enqueue_styles' )
		);

		// Load API for generic admin functions.
		if ( is_admin() ) {
			$this->admin = new Mixmymeals_Admin_API();
		}

		// Handle localisation. Must wait for init: since WP 6.7 a textdomain
		// loaded earlier is refused with a _doing_it_wrong notice and the
		// strings silently stay English. This used to run straight from the
		// constructor, i.e. at plugin load time.
		add_action( 'init', array( $this, 'load_plugin_textdomain' ), 0 );
		add_action( 'init', array( $this, 'load_localisation' ), 0 );

		add_action( 'wp_login', function( $user_login, $user ) {
			$cart = new Mixmymeals_Cart();
			$cart->merge_guest_cart_into_user( $user->ID );
		}, 10, 2 );


	}

	/**
	 * Load plugin textdomain
	 *
	 * @access  public
	 * @return  void
	 * @since   1.0.0
	 */
	public function load_plugin_textdomain(): void {
		$domain = 'mixmymeals';

		// A core filter, not one this plugin invents — the prefix rule does not
		// apply to hooks WordPress itself defines.
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter.

		load_textdomain(
			$domain,
			WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale
			. '.mo'
		);
		/*
		 * Kept despite being discouraged: that advice assumes translations come
		 * from translate.wordpress.org, which core then loads on its own. This
		 * plugin ships its own catalogue in lang/, and without this call a
		 * German site falls back to the English source strings — verified, not
		 * assumed. Losing that would take the disclosure labels with it.
		 */
		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- plugin ships its own catalogue in lang/.
		load_plugin_textdomain(
			$domain,
			false,
			dirname( plugin_basename( $this->file ) ) . '/lang/'
		);
	}

	/**
	 * Main Mixmymeals Instance
	 *
	 * Ensures only one instance of Mixmymeals is loaded or can be loaded.
	 *
	 * @param   string $file     File instance.
	 * @param   string $version  Version parameter.
	 *
	 * @return Object Mixmymeals instance
	 * @static
	 */
	public static function instance(
		string $file = '',
		string $version = '0.0.1'
	): static {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $file, $version );
		}

		return self::$_instance;
	}

	/**
	 * Wrapper function to add post meta boxes.
	 *
	 * @return Mixmymeals_Post_Meta
	 */
	public function add_post_meta(): Mixmymeals_Post_Meta {
		return new Mixmymeals_Post_Meta();
	}

	/**
	 * Instantiates the REST API class (meta registration + custom routes).
	 *
	 * @return Mixmymeals_Rest_Api
	 */
	public function add_rest_api(): Mixmymeals_Rest_Api {
		return new Mixmymeals_Rest_Api();
	}

	/**
	 * Instantiates the cleanup class (WP-Cron based deletion of orphaned
	 * meals and old uncategorized meal plans).
	 *
	 * @return Mixmymeals_Cleanup
	 */
	public function add_cleanup(): Mixmymeals_Cleanup {
		return new Mixmymeals_Cleanup( $this->file );
	}

	/**
	 * Wires the mail log's own daily pruning cron, which enforces the age limit
	 * on the stored send metadata independently of the (optional) cleanup cron.
	 *
	 * @return void
	 */
	public function add_mail_log(): void {
		Mixmymeals_Mail_Log::register( $this->file );
	}

	/**
	 * Instantiates the meal plan subscriptions class (CPT, double opt-in,
	 * token endpoints and the drip-sending WP-Cron event).
	 *
	 * @return Mixmymeals_Subscriptions
	 */
	public function add_subscriptions(): Mixmymeals_Subscriptions {
		return new Mixmymeals_Subscriptions( $this->file );
	}

	/**
	 * Instantiates the recipe ratings class (comment-based star ratings for
	 * logged-in users plus the per-recipe aggregate and display shortcode).
	 *
	 * @return Mixmymeals_Ratings
	 */
	public function add_ratings(): Mixmymeals_Ratings {
		return new Mixmymeals_Ratings();
	}

	/**
	 * Instantiates the meal photos class (visitor photo uploads with admin
	 * approval, the moderation page and the gallery data helpers).
	 *
	 * @return Mixmymeals_Photos
	 */
	public function add_photos(): Mixmymeals_Photos {
		return new Mixmymeals_Photos();
	}

	/**
	 * Instantiates the privacy class (GDPR personal-data exporters/erasers
	 * and suggested privacy-policy content).
	 *
	 * @return Mixmymeals_Privacy
	 */
	public function add_privacy(): Mixmymeals_Privacy {
		return new Mixmymeals_Privacy();
	}

	/**
	 * Instantiates the post types class.
	 *
	 * @return Mixmymeals_Post_Types
	 */
	public function add_post_types(): Mixmymeals_Post_Types {
		return new Mixmymeals_Post_Types();
	}

	/**
	 * Instantiates the taxonomies class.
	 *
	 * @return Mixmymeals_Taxonomies
	 */
	public function add_taxonomies(): Mixmymeals_Taxonomies {
		return new Mixmymeals_Taxonomies();
	}

	/**
	 * Instantiates the shortcodes class.
	 *
	 * @return Mixmymeals_Shortcodes
	 */
	public function add_shortcodes(): Mixmymeals_Shortcodes {
		return new Mixmymeals_Shortcodes();
	}

	/**
	 * Load frontend CSS.
	 *
	 * @access  public
	 * @return void
	 * @since   1.0.0
	 */
	public function enqueue_styles(): void {

		wp_register_style(
			$this->_token . '-frontend',
			esc_url( $this->assets_url ) . 'css/frontend.css',
			array(),
			$this->_version
		);
		wp_enqueue_style( $this->_token . '-frontend' );
	}

	/**
	 * Load frontend Javascript.
	 *
	 * @access  public
	 * @return  void
	 * @since   1.0.0
	 */
	public function enqueue_scripts(): void {

		wp_register_script(
			$this->_token . '-frontend',
			esc_url( $this->assets_url ) . 'js/frontend' . $this->script_suffix
			. '.js',
			array( 'jquery' ),
			$this->_version,
			array( 'in_footer' => true )
		);
		wp_enqueue_script( $this->_token . '-frontend' );

		wp_localize_script( $this->_token . '-frontend', 'mixmymeals',
			array(
				'ajax_url'               => admin_url( 'admin-ajax.php' ),
				'nonce'                  => wp_create_nonce( 'mmm_frontend_nonce' ),
				'load_into_cart_dialog'  => array(
					'title'   => __( 'Adjust', 'mixmymeals' ),
					'message' => __( 'The planner will be cleared and replaced with this meal plan. Continue?', 'mixmymeals' ),
					'confirm' => __( 'Continue', 'mixmymeals' ),
					'cancel'  => __( 'Cancel', 'mixmymeals' ),
				),
				'planner_dialog'         => array(
					'title'    => __( 'Plan this recipe as a meal', 'mixmymeals' ),
					'servings' => __( 'Servings', 'mixmymeals' ),
					'diet'     => _x( 'Diet', 'planner dialog field', 'mixmymeals' ),
					'type'     => __( 'Meal type', 'mixmymeals' ),
					'day'      => __( 'Day', 'mixmymeals' ),
					'no_type'  => __( 'No type', 'mixmymeals' ),
					'no_day'   => __( 'No day', 'mixmymeals' ),
					'submit'   => __( 'Add to Planner', 'mixmymeals' ),
					'cancel'   => __( 'Cancel', 'mixmymeals' ),
					'types'    => Mixmymeals_Meal::get_plaintext_meal_type_map(),

					/*
					 * Wording for the confirmation after planning. The recipe
					 * name is always there; the day and the type are left out
					 * when nobody chose them, which is why there are three
					 * sentences instead of one with holes in it. The two
					 * plural forms are resolved here because the script
					 * cannot ask the catalogue — 1 and 2 stand in for the
					 * two cases.
					 */
					'added'    => array(
						/* translators: 1: recipe name, 2: weekday, 3: meal type. */
						'full'       => __( '%1$s planned for %2$s, %3$s.', 'mixmymeals' ),
						/* translators: 1: recipe name, 2: weekday. */
						'day'        => __( '%1$s planned for %2$s.', 'mixmymeals' ),
						/* translators: 1: recipe name, 2: meal type. */
						'type'       => __( '%1$s planned as %2$s.', 'mixmymeals' ),
						/* translators: %s: recipe name. */
						'plain'      => __( '%s added to the planner.', 'mixmymeals' ),
						/* translators: %s: number of recipes in the planner. */
						'count_one'  => sprintf( _n( '%s recipe in the planner.', '%s recipes in the planner.', 1, 'mixmymeals' ), '%s' ),
						/* translators: %s: number of recipes in the planner. */
						'count_many' => sprintf( _n( '%s recipe in the planner.', '%s recipes in the planner.', 2, 'mixmymeals' ), '%s' ),
						'to_plan'    => __( 'To the planner', 'mixmymeals' ),
					),
					'share'    => array(
						'title'     => __( 'My meal plan', 'mixmymeals' ),
						'text'      => __( 'Here is what I have planned for the week.', 'mixmymeals' ),
						'copied'    => __( 'Link copied.', 'mixmymeals' ),
						'failed'    => __( 'The link could not be created.', 'mixmymeals' ),
						'loading'   => __( 'Loading the shared planner…', 'mixmymeals' ),
						'ask'       => __( 'Someone shared a planner with you. Your own planner will be replaced by it. Continue?', 'mixmymeals' ),
						'ask_title' => __( 'Load shared planner', 'mixmymeals' ),
					),
					'days'     => array(
						1 => __( 'Monday', 'mixmymeals' ),
						2 => __( 'Tuesday', 'mixmymeals' ),
						3 => __( 'Wednesday', 'mixmymeals' ),
						4 => __( 'Thursday', 'mixmymeals' ),
						5 => __( 'Friday', 'mixmymeals' ),
						6 => __( 'Saturday', 'mixmymeals' ),
						7 => __( 'Sunday', 'mixmymeals' ),
					),
				),
				'cook_mode'              => array(
					/* translators: %1$s: current step number, %2$s: total number of steps */
					'step_of' => __( 'Step %1$s of %2$s', 'mixmymeals' ),
					'exit'    => __( 'Exit', 'mixmymeals' ),
					'prev'    => __( 'Back', 'mixmymeals' ),
					'next'    => __( 'Next', 'mixmymeals' ),
					'done'    => __( 'Done', 'mixmymeals' ),
				),
			)
		);

	}

	/**
	 * Admin enqueue style.
	 *
	 * @param   string $hook  Hook parameter.
	 *
	 * @return void
	 */
	public function admin_enqueue_styles( string $hook = '' ): void {

		$css_path = $this->dir . '/assets/css/admin.css';
		$version  = file_exists( $css_path ) ? (string) filemtime( $css_path ) : $this->_version;

		wp_register_style(
			$this->_token . '-admin',
			esc_url( $this->assets_url ) . 'css/admin.css',
			array(),
			$version
		);
		wp_enqueue_style( $this->_token . '-admin' );
	}

	/**
	 * Load admin Javascript.
	 *
	 * @access  public
	 *
	 * @param   string $hook  Hook parameter.
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function admin_enqueue_scripts( string $hook = '' ): void {
		wp_enqueue_media();
		global $post;

		// Load the TinyMCE / wp.editor JS API so recipe step descriptions and the
		// author box text can attach a slim rich-text editor (see admin.js).
		// Needed on the recipe and meal plan edit screens — the author box lives
		// on both.
		if ( ( 'post.php' === $hook || 'post-new.php' === $hook )
			&& isset( $post )
			&& in_array( get_post_type( $post ), array( 'mmm_recipe', 'mmm_meal_plan' ), true ) ) {
			wp_enqueue_editor();
		}

		wp_register_script(
			$this->_token . '-admin',
			esc_url( $this->assets_url ) . 'js/admin' . $this->script_suffix
			. '.js',
			array( 'jquery' ),
			$this->_version,
			array( 'in_footer' => true )
		);
		wp_enqueue_script( $this->_token . '-admin' );

		wp_localize_script(
			'mixmymeals-admin',
			'mixmymealsAdmin',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'mixmymeals_admin_nonce' ),
				// Die Beschriftung der Ausschluss-Kaestchen. Sie entsteht in
				// einer frisch geklonten Zeile im Browser, also muss der
				// Wortlaut dorthin — uebersetzt wie der gerenderte daneben.
				'excludeLabel' => __( 'Not an option here:', 'mixmymeals' ),
			)
		);

		wp_localize_script(
			'mixmymeals-admin',
			'mixmymealsRecipes',
			array_map(
				function ( $r ) {
					return array(
						'ID'         => $r->ID,
						'post_title' => $r->post_title,
					);
				},
				Mixmymeals_Recipe::get_all_recipes()
			)
		);

		/*
		 * Ingredient ID => its products, for the product select in a recipe
		 * step. Localised rather than fetched: only ingredients that actually
		 * have products appear, which is a short list, and the row a user just
		 * added needs its options immediately — an AJAX round trip per change
		 * of ingredient buys nothing here.
		 */
		wp_localize_script( 'mixmymeals-admin', 'MixMyMealsProducts', self::admin_product_map() );

		/*
		 * Equipment is created and renamed from the recipe screen without
		 * saving the post. The strings travel with the nonces because the
		 * answers are rendered in PHP and only the two failures below are
		 * worded by the script.
		 */
		wp_localize_script(
			'mixmymeals-admin',
			'MixMyMealsEquipment',
			array(
				'ajaxurl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'mmm_equipment_ajax' ),
				'saved'    => __( 'Saved', 'mixmymeals' ),
				'failed'   => __( 'Could not be saved.', 'mixmymeals' ),
				'existing' => __( 'That one is already in the list.', 'mixmymeals' ),
				// The rendered hint describes the save-and-reload route, which
				// is what happens without this script. With it, the piece is
				// created on Enter, so the box must say so instead.
				'hint'     => __( 'Type a name and press Enter.', 'mixmymeals' ),
			)
		);

		wp_localize_script(
			'mixmymeals-admin',
			'MixMyMealsAmounts',
			array(
				'ajaxurl'       => admin_url( 'admin-ajax.php' ),
				'addNonce'      => wp_create_nonce( 'mixmymeals_add_ingr_amount' ),
				'deleteNonce'   => wp_create_nonce( 'mixmymeals_delete_ingr_amount' ),
				'confirmDelete' => __( 'Delete?', 'mixmymeals' ),
			)
		);

	}

	/**
	 * Ingredient ID => [ { id, label } ] for every ingredient that has
	 * products at all.
	 *
	 * @return array<int, array<int, array{id:int,label:string}>>
	 */
	private static function admin_product_map(): array {

		$map = array();

		$products = get_posts(
			array(
				'post_type'      => 'mmm_product',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);

		foreach ( $products as $product_post ) {

			$parent = (int) $product_post->post_parent;

			if ( $parent <= 0 ) {
				continue;
			}

			$product = new Mixmymeals_Product( (int) $product_post->ID );

			$map[ $parent ][] = array(
				'id'    => (int) $product_post->ID,
				'label' => $product->label(),
			);
		}

		return $map;
	}

	/**
	 * Load plugin localisation
	 *
	 * @access  public
	 * @return  void
	 */
	public function load_localisation(): void {
		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- see load_plugin_textdomain() above.
		load_plugin_textdomain(
			'mixmymeals',
			false,
			dirname( plugin_basename( $this->file ) ) . '/lang/'
		);
	}

	/**
	 * Cloning is forbidden.
	 */
	public function __clone(): void {
		_doing_it_wrong(
			__FUNCTION__,
			esc_html(
				__(
					'Cloning of MixMyMeals is forbidden',
					'mixmymeals'
				)
			),
			esc_attr( $this->_version )
		);
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 */
	public function __wakeup(): void {
		_doing_it_wrong(
			__FUNCTION__,
			esc_html(
				__(
					'Unserializing instances of MixMyMeals is forbidden',
					'mixmymeals'
				)
			),
			esc_attr( $this->_version )
		);
	}

	/**
	 * Installation. Runs on activation.
	 *
	 * Post types are not registered yet at activation time, so the rewrite
	 * flush is deferred: a flag option is set here and consumed by
	 * maybe_flush_rewrite_rules() on the next init (after CPT registration).
	 *
	 * @access  public
	 * @return  void
	 * @since   1.0.0
	 */
	public function install(): void {
		$this->_log_version_number();

		// Defer the rewrite flush until post types are registered.
		update_option( 'mixmymeals_flush_needed', 1 );
	}

	/**
	 * Flushes rewrite rules once after activation, late on init so all
	 * MixMyMeals post types and taxonomies are registered first. Without
	 * this, fresh installs 404 on every plugin permalink.
	 *
	 * @access  public
	 * @return  void
	 */
	public function maybe_flush_rewrite_rules(): void {
		if ( get_option( 'mixmymeals_flush_needed' ) ) {
			flush_rewrite_rules();
			delete_option( 'mixmymeals_flush_needed' );
		}
	}

	/**
	 * Log the plugin version number.
	 *
	 * @access  public
	 * @return  void
	 */
	private function _log_version_number(): void { //phpcs:ignore
		update_option( $this->_token . '_version', $this->_version );
	}

	/**
	 * Hide admin bar
	 *
	 * @access  public
	 * @return  bool
	 */
	public function hide_admin_bar(): bool {

		// Hide admin bar for specific roles.
		// Native WordPress roles will still be shown the bar.
		$roles = array( 'mixmymeals_meal_mixer', 'mixmymeals_pending_meal_mixer' );

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
	public function hide_admin_dashboard(): void {

		if ( is_admin() && ! current_user_can( 'administrator' )
			// Calling admin page but being no admin?
			&& ! ( defined( 'DOING_AJAX' ) && DOING_AJAX )
			// Not doing AJAX stuff?
			&& isset( $_SERVER['PHP_SELF'] )
			&& ! str_contains(
				sanitize_urL( wp_unslash( $_SERVER['PHP_SELF'] ) ),
				'admin-post.php'
			)     // Not just using admin-post.php for form processing?
		) {

			// Then redirect to homepage. /wp-admin is not the right place for you.
			wp_safe_redirect( home_url() );
			exit;
		}

	}


}
