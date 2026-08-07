<?php
/**
 * Instagram image generator class file.
 *
 * @package MixMyMeals Plugin/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mixmymeals_Instagram
 *
 * Renders Instagram carousel images (one overview slide plus one slide per
 * day) for a meal plan, using GD only (no Imagick, no external services —
 * works on shared hosting). Images are plain temporary files below
 * uploads/mixmymeals-instagram/{plan_id}/ — they are never added to the
 * media library and a daily cron deletes them after a configurable
 * retention period (or when their plan is gone).
 *
 * Fonts and the optional logo are NOT bundled with the plugin: they come
 * from the media library (the class whitelists TTF/OTF uploads for
 * administrators) or from the child theme's assets/fonts/ directory as a
 * fallback. All look & feel knobs (format, colors, visible elements,
 * fine-tuning) live in the "Instagram" settings tab, option prefix
 * mixmymeals_instagram_.
 *
 * Rendering happens per image via AJAX from a meta box on the meal plan
 * edit screen, so a full carousel never runs into shared-hosting timeouts.
 * Everything is drawn at 2x and downsampled once for smooth edges.
 */
class Mixmymeals_Instagram {

	const POST_TYPE = 'mmm_meal_plan';

	// Recipes get a single-image post instead of a carousel.
	const RECIPE_POST_TYPE = 'mmm_recipe';

	// Directory below uploads/ that holds all generated images.
	const DIR_NAME = 'mixmymeals-instagram';

	// Daily cron that removes expired image directories.
	const CRON_HOOK = 'mixmymeals_instagram_cleanup';

	// Single-event cron that publishes a scheduled post.
	const PUBLISH_HOOK = 'mixmymeals_instagram_scheduled_publish';

	// Post meta for scheduled publishing.
	const META_SCHEDULED_AT = '_mmm_ig_scheduled_at';
	const META_SCHEDULED_CAPTION = '_mmm_ig_scheduled_caption';
	const META_SCHEDULED_USER = '_mmm_ig_scheduled_user';
	const META_ATTEMPT = '_mmm_ig_publish_attempt';
	const META_LAST_RESULT = '_mmm_ig_last_result';

	// Retry pacing when Instagram is still processing at publish time.
	const PUBLISH_MAX_ATTEMPTS = 3;
	const PUBLISH_RETRY_DELAY = 300;

	// Admin overview page of scheduled posts.
	const ADMIN_SLUG = 'mmm_instagram_scheduled';

	const NONCE_ACTION = 'mmm_instagram';

	const OPTION_PREFIX = 'mixmymeals_instagram_';

	const DEFAULT_RETENTION_DAYS = 7;

	// Instagram API with Instagram Login ("business login") endpoints.
	const API_BASE    = 'https://graph.instagram.com/v21.0';
	const REFRESH_URL = 'https://graph.instagram.com/refresh_access_token';

	// Instagram caption limit in characters.
	const CAPTION_MAX = 2200;

	// Supersampling factor: draw at 2x, downsample once.
	const SS = 2;

	/**
	 * Constructor. Wires admin UI, AJAX, upload whitelisting and cron.
	 *
	 * @param string $plugin_file Main plugin file, used for the deactivation hook.
	 */
	/**
	 * First constructed instance (the bootstrap creates exactly one); lets
	 * other modules (e.g. Pinterest) reuse the image renderer without
	 * re-registering every hook through a second construction.
	 *
	 * @var Mixmymeals_Instagram|null
	 */
	private static ?Mixmymeals_Instagram $shared = null;

	/**
	 * Returns the shared instance created by the plugin bootstrap.
	 *
	 * @return Mixmymeals_Instagram
	 */
	public static function instance(): Mixmymeals_Instagram {
		return self::$shared ?? new self();
	}

	public function __construct( string $plugin_file = '' ) {

		if ( null === self::$shared ) {
			self::$shared = $this;
		}

		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( $this, 'add_meta_box' ) );
		add_action( 'add_meta_boxes_' . self::RECIPE_POST_TYPE, array( $this, 'add_meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		add_action( 'wp_ajax_mmm_instagram_render', array( $this, 'ajax_render' ) );
		add_action( 'wp_ajax_mmm_instagram_delete', array( $this, 'ajax_delete' ) );
		add_action( 'wp_ajax_mmm_instagram_post_start', array( $this, 'ajax_post_start' ) );
		add_action( 'wp_ajax_mmm_instagram_post_publish', array( $this, 'ajax_post_publish' ) );
		add_action( 'wp_ajax_mmm_instagram_schedule', array( $this, 'ajax_schedule' ) );
		add_action( 'wp_ajax_mmm_instagram_unschedule', array( $this, 'ajax_unschedule' ) );

		add_action( self::PUBLISH_HOOK, array( $this, 'run_scheduled_publish' ) );

		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
		add_action( 'admin_post_mmm_ig_cancel_schedule', array( $this, 'handle_cancel_action' ) );

		add_filter( 'upload_mimes', array( $this, 'allow_font_uploads' ) );
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'fix_font_filetype' ), 10, 4 );

		add_action( 'init', array( $this, 'maybe_schedule' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'cleanup_expired' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'maybe_refresh_token' ) );

		if ( $plugin_file ) {
			register_deactivation_hook( $plugin_file, array( __CLASS__, 'unschedule' ) );
		}
	}

	/* ---------------------------------------------------------------------
	 * Options
	 * ------------------------------------------------------------------ */

	/**
	 * Returns a plugin option with the Instagram prefix.
	 *
	 * @param string $key     Option key without prefix.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	private static function opt( string $key, mixed $default = '' ): mixed {
		$value = get_option( self::OPTION_PREFIX . $key, null );

		// Saving the settings tab stores '' / array() for untouched fields —
		// treat those as "not configured" so built-in defaults keep working.
		if ( null === $value || false === $value || '' === $value || array() === $value ) {
			return $default;
		}

		return $value;
	}

	/**
	 * Whether a show/hide element toggle is on. Toggles default to on until
	 * the settings tab has been saved with the box unchecked.
	 *
	 * @param string $key Toggle key, e.g. 'show_meta'.
	 * @return bool
	 */
	private static function is_on( string $key ): bool {
		$value = get_option( self::OPTION_PREFIX . $key, 'on' );

		return 'on' === $value;
	}

	/**
	 * Canvas size in design pixels, depending on the format option.
	 *
	 * @return int[] Array of width and height.
	 */
	private static function canvas_size(): array {
		switch ( self::opt( 'format', '4x5' ) ) {
			case '1x1':
				return array( 1080, 1080 );
			case '9x16':
				return array( 1080, 1920 );
			default:
				return array( 1080, 1350 );
		}
	}

	/**
	 * Font scale factor from the fine-tuning option (percent).
	 *
	 * @return float
	 */
	private static function font_scale(): float {
		$percent = (int) self::opt( 'font_scale', 100 );

		return max( 50, min( 150, $percent ? $percent : 100 ) ) / 100;
	}

	/**
	 * Scale factor for the brand mark (logo image or wordmark) in the header
	 * of the cover and overview slides, from the logo-size option (percent).
	 *
	 * @return float
	 */
	private static function logo_scale(): float {
		$percent = (int) self::opt( 'logo_size', 100 );

		return max( 50, min( 200, $percent ? $percent : 100 ) ) / 100;
	}

	/**
	 * Effective colour palette: a named style preset when one is chosen,
	 * otherwise the individual colour options.
	 *
	 * @return array{bg:string,primary:string,accent:string}
	 */
	private static function palette(): array {

		$presets = array(
			'frisch'    => array( 'bg' => '#FBF8F1', 'primary' => '#5C9E2E', 'accent' => '#E8743B' ),
			'editorial' => array( 'bg' => '#F3EFE7', 'primary' => '#3E4A3A', 'accent' => '#B5714B' ),
			'kraeftig'  => array( 'bg' => '#1E1C17', 'primary' => '#6FB33C', 'accent' => '#F0A03C' ),
			'minimal'   => array( 'bg' => '#FFFFFF', 'primary' => '#1A1A1A', 'accent' => '#E8743B' ),
		);

		$preset = (string) self::opt( 'style_preset', 'custom' );

		if ( isset( $presets[ $preset ] ) ) {
			return $presets[ $preset ];
		}

		// NOTE: read the raw colour options here — never c_bg()/c_primary()/
		// c_accent(), which call back into palette() and would recurse.
		return array(
			'bg'      => (string) self::opt( 'color_background', '#FBF8F1' ),
			'primary' => (string) self::opt( 'color_primary', '#5C9E2E' ),
			'accent'  => (string) self::opt( 'color_accent', '#E8743B' ),
		);
	}

	/**
	 * Background colour (preset or option).
	 *
	 * @return string
	 */
	private static function c_bg(): string {
		return self::palette()['bg'];
	}

	/**
	 * Primary colour (preset or option).
	 *
	 * @return string
	 */
	private static function c_primary(): string {
		return self::palette()['primary'];
	}

	/**
	 * Accent colour (preset or option).
	 *
	 * @return string
	 */
	private static function c_accent(): string {
		return self::palette()['accent'];
	}

	/**
	 * Readable ink colour for text on a given background, chosen by the
	 * background's luminance so dark presets get light text automatically.
	 *
	 * @param string $bg        Background hex colour.
	 * @param bool   $secondary Return the secondary (muted) ink instead.
	 * @return string
	 */
	private static function contrast_ink( string $bg, bool $secondary = false ): string {

		list( $r, $g, $b ) = self::hex_to_rgb( $bg );
		$lum = ( 0.299 * $r + 0.587 * $g + 0.114 * $b ) / 255;

		if ( $lum < 0.5 ) {
			return $secondary ? '#B7B0A0' : '#F3EFE7';
		}

		return $secondary ? '#6B665B' : '#2C2A24';
	}

	/**
	 * Meal types that appear on the slides, in display order.
	 *
	 * @return string[]
	 */
	private static function allowed_meal_types(): array {
		$saved = self::opt( 'meal_types', array( 'lunch', 'dinner' ) );

		if ( ! is_array( $saved ) || ! $saved ) {
			$saved = array( 'lunch', 'dinner' );
		}

		// Keep the canonical order from the type map.
		$ordered = array();
		foreach ( array_keys( Mixmymeals_Meal::get_plaintext_meal_type_map() ) as $type ) {
			if ( in_array( $type, $saved, true ) ) {
				$ordered[] = $type;
			}
		}

		return $ordered;
	}

	/**
	 * Retention period for generated images in days.
	 *
	 * @return int
	 */
	public static function get_retention_days(): int {
		$days = absint( self::opt( 'retention_days', self::DEFAULT_RETENTION_DAYS ) );

		return $days > 0 ? $days : self::DEFAULT_RETENTION_DAYS;
	}

	/* ---------------------------------------------------------------------
	 * Fonts (media library first, child theme second, filterable)
	 * ------------------------------------------------------------------ */

	/**
	 * Resolves the font for a role and reports how it was resolved.
	 *
	 * A font that is explicitly configured but unusable is an ERROR — the
	 * renderer must not silently fall back to another file, otherwise the
	 * generated images use a different font than configured and nobody
	 * knows why. Fallbacks only apply when nothing is configured: first
	 * font file in the child theme's assets/fonts/ directory, and for the
	 * text role the (resolved) title font.
	 *
	 * @param string $role Either 'title' or 'text'.
	 * @return array Keys: 'path' (string, '' on failure), 'source' (one of
	 *               'media', 'theme', 'title', 'filter', 'none') and
	 *               'error' (string, '' when usable).
	 */
	public static function font_status( string $role = 'title' ): array {

		$status = array(
			'path'   => '',
			'source' => 'none',
			'error'  => '',
		);

		$attachment_id = absint( self::opt( 'text' === $role ? 'text_font_id' : 'title_font_id', 0 ) );

		if ( $attachment_id ) {
			$file  = (string) get_attached_file( $attachment_id );
			$error = self::check_font_file( $file );

			if ( '' === $error ) {
				$status['path']   = $file;
				$status['source'] = 'media';
			} else {
				$status['error'] = sprintf(
					'text' === $role
						/* translators: %s: reason why the font file is unusable. */
						? __( 'The selected text font cannot be used: %s', 'mixmymeals' )
						/* translators: %s: reason why the font file is unusable. */
						: __( 'The selected title font cannot be used: %s', 'mixmymeals' ),
					$error . ( $file ? ' (' . basename( $file ) . ')' : '' )
				);
			}
		} else {
			// Nothing configured: child theme fallback, then title font.
			$theme_dir  = trailingslashit( get_stylesheet_directory() ) . 'assets/fonts';
			$candidates = is_dir( $theme_dir )
				? array_merge(
					glob( $theme_dir . '/*.ttf' ) ?: array(),
					glob( $theme_dir . '/*.otf' ) ?: array()
				)
				: array();
			sort( $candidates );

			foreach ( $candidates as $candidate ) {
				if ( '' === self::check_font_file( $candidate ) ) {
					$status['path']   = $candidate;
					$status['source'] = 'theme';
					break;
				}
			}

			if ( '' === $status['path'] && 'text' === $role ) {
				$title = self::font_status( 'title' );
				if ( '' !== $title['path'] ) {
					$status['path']   = $title['path'];
					$status['source'] = 'title';
				}
			}
		}

		/**
		 * Filters the font file used for rendering Instagram images.
		 *
		 * @param string $path Absolute path to a TTF/OTF file ('' when none found).
		 * @param string $role Font role: 'title' or 'text'.
		 */
		$filtered = (string) apply_filters( 'mixmymeals_instagram_font', $status['path'], $role );

		if ( $filtered !== $status['path'] ) {
			$error = self::check_font_file( $filtered );
			if ( '' === $error ) {
				$status['path']   = $filtered;
				$status['source'] = 'filter';
				$status['error']  = '';
			} else {
				$status['path']  = '';
				$status['error'] = sprintf(
					/* translators: %s: reason why the font file is unusable. */
					__( 'The font provided by the mixmymeals_instagram_font filter cannot be used: %s', 'mixmymeals' ),
					$error . ( $filtered ? ' (' . basename( $filtered ) . ')' : '' )
				);
			}
		}

		return $status;
	}

	/**
	 * Backwards-compatible path accessor for a font role.
	 *
	 * @param string $role Either 'title' or 'text'.
	 * @return string Absolute file path, or '' when nothing usable exists.
	 */
	public static function get_font_path( string $role = 'title' ): string {
		$status = self::font_status( $role );

		return $status['path'];
	}

	/**
	 * Checks whether a file is a font GD can actually render.
	 *
	 * @param string $file Absolute path.
	 * @return string '' when usable, otherwise a translated reason.
	 */
	private static function check_font_file( string $file ): string {

		if ( '' === $file || ! file_exists( $file ) ) {
			return __( 'file not found', 'mixmymeals' );
		}

		$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'ttf', 'otf' ), true ) ) {
			return __( 'not a TTF/OTF file', 'mixmymeals' );
		}

		if ( ! is_readable( $file ) ) {
			return __( 'file is not readable', 'mixmymeals' );
		}

		// Probe render: catches corrupt files and formats FreeType rejects
		// (e.g. a woff2 renamed to .ttf).
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! function_exists( 'imagettfbbox' ) || false === @imagettfbbox( 12, 0, $file, 'Ag' ) ) {
			return __( 'the image renderer cannot read this font file', 'mixmymeals' );
		}

		return '';
	}

	/**
	 * Whether rendering can work at all (GD with FreeType + a title font).
	 *
	 * @return bool
	 */
	public static function is_ready(): bool {
		if ( ! function_exists( 'imagettftext' ) ) {
			return false;
		}

		$status = self::font_status( 'title' );

		return '' !== $status['path'];
	}

	/**
	 * Allows administrators to upload TTF/OTF files to the media library.
	 *
	 * @param array $mimes Allowed mime types.
	 * @return array
	 */
	public function allow_font_uploads( array $mimes ): array {

		if ( current_user_can( 'manage_options' ) ) {
			$mimes['ttf'] = 'font/ttf';
			$mimes['otf'] = 'font/otf';
		}

		return $mimes;
	}

	/**
	 * Aligns the detected mime type for font uploads with the whitelist.
	 * PHP's fileinfo reports fonts as font/sfnt or application/x-font-ttf
	 * depending on version, which would make WordPress reject the upload.
	 *
	 * @param array       $data     Values for ext, type, proper_filename.
	 * @param string      $file     Full path to the uploaded file.
	 * @param string      $filename Name of the uploaded file.
	 * @param array|null  $mimes    Allowed mime types.
	 * @return array
	 */
	public function fix_font_filetype( $data, $file, $filename, $mimes ): array {

		$data = (array) $data;

		if ( ! current_user_can( 'manage_options' ) ) {
			return $data;
		}

		$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		if ( ! in_array( $ext, array( 'ttf', 'otf' ), true ) || ! empty( $data['type'] ) ) {
			return $data;
		}

		$real = function_exists( 'mime_content_type' ) && is_readable( $file )
			? (string) mime_content_type( $file )
			: '';

		// Only step in when the file really is a font container.
		if ( '' === $real || str_contains( $real, 'font' ) || str_contains( $real, 'sfnt' ) ) {
			$data['ext']  = $ext;
			$data['type'] = 'ttf' === $ext ? 'font/ttf' : 'font/otf';
		}

		return $data;
	}

	/* ---------------------------------------------------------------------
	 * Storage (plain files, no attachments)
	 * ------------------------------------------------------------------ */

	/**
	 * Base directory for all generated images.
	 *
	 * @return string
	 */
	public static function get_base_dir(): string {
		$uploads = wp_upload_dir();

		return trailingslashit( $uploads['basedir'] ) . self::DIR_NAME;
	}

	/**
	 * Directory for one plan's images.
	 *
	 * @param int $plan_id Meal plan post ID.
	 * @return string
	 */
	public static function get_plan_dir( int $plan_id ): string {
		return trailingslashit( self::get_base_dir() ) . $plan_id;
	}

	/**
	 * Public URL for one plan's image directory.
	 *
	 * @param int $plan_id Meal plan post ID.
	 * @return string
	 */
	public static function get_plan_url( int $plan_id ): string {
		$uploads = wp_upload_dir();

		return trailingslashit( $uploads['baseurl'] ) . self::DIR_NAME . '/' . $plan_id;
	}

	/**
	 * The render jobs for a plan: the overview plus one job per day.
	 *
	 * @param Mixmymeals_Meal_Plan $plan Plan object.
	 * @return array[] Arrays with 'job', 'file' and 'label'.
	 */
	public static function get_jobs( Mixmymeals_Meal_Plan $plan ): array {

		$jobs = array();

		if ( self::is_on( 'show_cover' ) ) {
			$jobs[] = array(
				'job'   => 'cover',
				'file'  => 'cover.jpg',
				'label' => __( 'Cover', 'mixmymeals' ),
			);
		}

		$jobs[] = array(
			'job'   => 'overview',
			'file'  => 'uebersicht.jpg',
			'label' => __( 'Overview', 'mixmymeals' ),
		);

		$position = 0;
		$by_day   = self::meals_by_day( $plan );
		foreach ( $plan->get_day_range() as $day ) {
			++$position;

			$meals     = $by_day[ $day ] ?? array();
			$count     = max( 1, count( $meals ) );
			$day_label = sprintf(
				/* translators: %d: position of the day within the plan. */
				__( 'Day %d', 'mixmymeals' ),
				$position
			) . ' – ' . self::weekday_name( $day );

			// One slide per recipe of the day (all share the same "Day N"
			// label), so a day with several recipes produces several day slides.
			for ( $idx = 0; $idx < $count; $idx++ ) {
				$label = $day_label;
				if ( $count > 1 && isset( $meals[ $idx ]['title'] ) ) {
					$label .= ' · ' . $meals[ $idx ]['title'];
				}
				$jobs[] = array(
					'job'   => 'day-' . $day . '-' . $idx,
					'file'  => 'tag-' . $day . '-' . $idx . '.jpg',
					'label' => $label,
				);
			}
		}

		if ( self::is_on( 'show_cta' ) ) {
			$jobs[] = array(
				'job'   => 'cta',
				'file'  => 'cta.jpg',
				'label' => __( 'Call to action', 'mixmymeals' ),
			);
		}

		// Instagram allows at most 10 slides per carousel. On very long plans
		// drop the optional slides (CTA first, then the cover) to fit.
		foreach ( array( 'cta', 'cover' ) as $drop ) {
			if ( count( $jobs ) <= 10 ) {
				break;
			}
			foreach ( $jobs as $k => $job ) {
				if ( $job['job'] === $drop ) {
					unset( $jobs[ $k ] );
					$jobs = array_values( $jobs );
					break;
				}
			}
		}

		return array_slice( $jobs, 0, 10 );
	}

	/**
	 * The render jobs for any supported post: a carousel for meal plans, a
	 * single image for recipes.
	 *
	 * @param int $post_id Meal plan or recipe post ID.
	 * @return array[] Arrays with 'job', 'file' and 'label'.
	 */
	public static function get_jobs_for_post( int $post_id ): array {

		if ( get_post_type( $post_id ) === self::RECIPE_POST_TYPE ) {
			return array(
				array(
					'job'   => 'recipe',
					'file'  => 'rezept.jpg',
					'label' => __( 'Recipe', 'mixmymeals' ),
				),
			);
		}

		if ( get_post_type( $post_id ) === self::POST_TYPE ) {
			return self::get_jobs( new Mixmymeals_Meal_Plan( $post_id ) );
		}

		return array();
	}

	/**
	 * Lists the existing images of a plan or recipe in carousel order.
	 *
	 * @param int $plan_id Meal plan or recipe post ID.
	 * @return array[] Arrays with 'file', 'url', 'label' and 'mtime'.
	 */
	public static function list_images( int $plan_id ): array {

		$dir    = self::get_plan_dir( $plan_id );
		$url    = self::get_plan_url( $plan_id );
		$images = array();

		foreach ( self::get_jobs_for_post( $plan_id ) as $job ) {
			$file = trailingslashit( $dir ) . $job['file'];
			if ( is_file( $file ) ) {
				$mtime    = (int) filemtime( $file );
				$images[] = array(
					'file'  => $job['file'],
					'url'   => trailingslashit( $url ) . $job['file'] . '?v=' . $mtime,
					'label' => $job['label'],
					'mtime' => $mtime,
				);
			}
		}

		return $images;
	}

	/**
	 * Deletes all generated images (and the directory) of one plan.
	 *
	 * @param int $plan_id Meal plan post ID.
	 * @return bool True when the directory is gone afterwards.
	 */
	public static function delete_plan_images( int $plan_id ): bool {
		return self::remove_dir( self::get_plan_dir( $plan_id ) );
	}

	/**
	 * Removes one flat image directory. Only touches .jpg files, so a
	 * directory containing anything unexpected is left alone.
	 *
	 * @param string $dir Absolute directory path.
	 * @return bool
	 */
	private static function remove_dir( string $dir ): bool {

		if ( ! is_dir( $dir ) ) {
			return true;
		}

		foreach ( glob( trailingslashit( $dir ) . '*.jpg' ) ?: array() as $file ) {
			wp_delete_file( $file );
		}

		// rmdir fails silently when non-jpg leftovers exist — intentional.
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return @rmdir( $dir );
	}

	/* ---------------------------------------------------------------------
	 * Cleanup cron (independent from the plan cleanup, runs always)
	 * ------------------------------------------------------------------ */

	/**
	 * Schedules the daily cleanup event.
	 *
	 * @return void
	 */
	public function maybe_schedule(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Removes the cron event. Used on plugin deactivation.
	 *
	 * @return void
	 */
	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Deletes image directories whose newest file is older than the
	 * retention period, or whose meal plan no longer exists.
	 *
	 * @return int Number of deleted directories.
	 */
	public static function cleanup_expired(): int {

		$base = self::get_base_dir();

		if ( ! is_dir( $base ) ) {
			return 0;
		}

		$threshold = time() - self::get_retention_days() * DAY_IN_SECONDS;
		$deleted   = 0;

		foreach ( glob( trailingslashit( $base ) . '*', GLOB_ONLYDIR ) ?: array() as $dir ) {

			$plan_id = absint( basename( $dir ) );

			$newest = 0;
			foreach ( glob( trailingslashit( $dir ) . '*.jpg' ) ?: array() as $file ) {
				$newest = max( $newest, (int) filemtime( $file ) );
			}

			$plan_gone = ! $plan_id
				|| ! in_array( get_post_type( $plan_id ), array( self::POST_TYPE, self::RECIPE_POST_TYPE ), true );

			if ( $plan_gone || $newest < $threshold ) {
				if ( self::remove_dir( $dir ) ) {
					++$deleted;
				}
			}
		}

		if ( $deleted && function_exists( 'mmm_write_to_log' ) ) {
			mmm_write_to_log(
				sprintf( 'MixMyMeals Instagram cleanup: deleted %d image folder(s).', $deleted )
			);
		}

		if ( class_exists( 'Mixmymeals_Cleanup' ) ) {
			Mixmymeals_Cleanup::record( 'instagram_folders', $deleted );
		}

		return $deleted;
	}

	/* ---------------------------------------------------------------------
	 * Meta box + assets
	 * ------------------------------------------------------------------ */

	/**
	 * Registers the meta box on the meal plan edit screen.
	 *
	 * @return void
	 */
	public function add_meta_box(): void {
		add_meta_box(
			'mmm_instagram_images',
			__( 'Instagram Images', 'mixmymeals' ),
			array( $this, 'render_meta_box' ),
			array( self::POST_TYPE, self::RECIPE_POST_TYPE ),
			'normal',
			'low'
		);
	}

	/**
	 * Enqueues the admin script on the plan edit screen and the settings
	 * page (media picker for font files).
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {

		$is_plan_screen = in_array( $hook, array( 'post.php', 'post-new.php' ), true )
			&& get_current_screen()
			&& in_array( get_current_screen()->post_type, array( self::POST_TYPE, self::RECIPE_POST_TYPE ), true );

		$is_settings_page = str_ends_with( $hook, '_mixmymeals_settings' );

		if ( ! $is_plan_screen && ! $is_settings_page ) {
			return;
		}

		if ( $is_settings_page ) {
			wp_enqueue_media();
		}

		$plugin   = mixmymeals();
		$rel      = 'js/instagram-admin' . $plugin->script_suffix . '.js';
		$abs      = trailingslashit( $plugin->assets_dir ) . $rel;
		$version  = file_exists( $abs ) ? (string) filemtime( $abs ) : $plugin->_version;

		wp_enqueue_script(
			$plugin->_token . '-instagram-admin',
			esc_url( $plugin->assets_url ) . $rel,
			array( 'jquery' ),
			$version,
			array( 'in_footer' => true )
		);
	}

	/**
	 * Renders the meta box: setup notice or generate/delete UI plus the
	 * grid of already generated images.
	 *
	 * @param WP_Post $post Current meal plan post.
	 * @return void
	 */
	public function render_meta_box( WP_Post $post ): void {

		$title_status = self::font_status( 'title' );
		$text_status  = self::font_status( 'text' );

		$font_problem = '';
		if ( ! function_exists( 'imagettftext' ) ) {
			$font_problem = __( 'The GD extension with FreeType support is not available on this server.', 'mixmymeals' );
		} elseif ( '' === $title_status['path'] ) {
			$font_problem = '' !== $title_status['error']
				? $title_status['error']
				: __( 'Please choose a title font (TTF/OTF from the media library) in the settings first — GD cannot render text without one.', 'mixmymeals' );
		} elseif ( '' === $text_status['path'] ) {
			$font_problem = $text_status['error'];
		}

		if ( '' !== $font_problem ) {
			printf(
				'<div class="notice notice-error inline"><p>%s</p></div><p><a class="button" href="%s">%s</a></p>',
				esc_html( $font_problem ),
				esc_url( admin_url( 'options-general.php?page=mixmymeals_settings&tab=mixmymeals-instagram' ) ),
				esc_html__( 'Open settings', 'mixmymeals' )
			);
			return;
		}

		$is_recipe = self::RECIPE_POST_TYPE === $post->post_type;
		$plan      = $is_recipe ? null : new Mixmymeals_Meal_Plan( $post->ID );

		if ( ! $is_recipe && ! $plan->get_meals() ) {
			printf(
				'<p>%s</p>',
				esc_html__( 'This plan has no meals yet — add meals before generating images.', 'mixmymeals' )
			);
			return;
		}

		$jobs   = self::get_jobs_for_post( $post->ID );
		$images = self::list_images( $post->ID );

		printf(
			'<div id="mmm-instagram-box" data-plan="%d" data-nonce="%s" data-jobs="%s">',
			(int) $post->ID,
			esc_attr( wp_create_nonce( self::NONCE_ACTION ) ),
			esc_attr( wp_json_encode( wp_list_pluck( $jobs, 'job' ) ) )
		);

		printf(
			'<p><button type="button" class="button button-primary" id="mmm-instagram-generate">%s</button> ',
			$images
				? esc_html__( 'Regenerate images', 'mixmymeals' )
				: esc_html__( 'Generate images', 'mixmymeals' )
		);

		printf(
			'<button type="button" class="button" id="mmm-instagram-delete" %s>%s</button> <span id="mmm-instagram-status"></span></p>',
			$images ? '' : 'disabled',
			esc_html__( 'Delete images', 'mixmymeals' )
		);

		echo '<div id="mmm-instagram-grid" style="display:flex;flex-wrap:wrap;gap:12px;">';

		foreach ( $images as $image ) {
			printf(
				'<figure style="margin:0;width:150px;text-align:center;">
					<a href="%1$s" download><img src="%1$s" alt="" style="width:100%%;height:auto;border-radius:6px;display:block;" /></a>
					<figcaption style="font-size:11px;padding-top:4px;">%2$s<br>%3$s</figcaption>
				</figure>',
				esc_url( $image['url'] ),
				esc_html( $image['label'] ),
				esc_html( wp_date( get_option( 'date_format' ) . ' H:i', $image['mtime'] ) )
			);
		}

		echo '</div>';

		printf(
			'<p class="description">%s</p>',
			esc_html(
				sprintf(
					/* translators: %d: retention period in days. */
					_n(
						'Images are temporary and will be deleted automatically after %d day.',
						'Images are temporary and will be deleted automatically after %d days.',
						self::get_retention_days(),
						'mixmymeals'
					),
					self::get_retention_days()
				)
			)
		);

		$source_labels = array(
			'media'  => __( 'media library', 'mixmymeals' ),
			'theme'  => __( 'child theme', 'mixmymeals' ),
			'title'  => __( 'same as title font', 'mixmymeals' ),
			'filter' => __( 'via filter', 'mixmymeals' ),
		);

		printf(
			'<p class="description">%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: title font file name, 2: its source, 3: text font file name, 4: its source. */
					__( 'Title font: %1$s (%2$s) · Text font: %3$s (%4$s)', 'mixmymeals' ),
					basename( $title_status['path'] ),
					$source_labels[ $title_status['source'] ] ?? $title_status['source'],
					basename( $text_status['path'] ),
					$source_labels[ $text_status['source'] ] ?? $text_status['source']
				)
			)
		);

		// Posting section.
		echo '<hr />';
		printf( '<h4 style="margin-bottom:6px;">%s</h4>', esc_html__( 'Post to Instagram', 'mixmymeals' ) );

		if ( '' === self::get_access_token() ) {
			printf(
				'<p class="description">%s <a href="%s">%s</a></p>',
				esc_html__( 'To post directly, add your Instagram access token on your profile.', 'mixmymeals' ),
				esc_url( get_edit_profile_url() ),
				esc_html__( 'Open profile', 'mixmymeals' )
			);
		} else {
			$token_status = self::token_status();

			if ( ! $token_status['ok'] ) {
				printf(
					'<div class="notice notice-error inline"><p>%s</p></div>',
					esc_html(
						sprintf(
							/* translators: %s: error message. */
							__( 'Instagram connection error: %s', 'mixmymeals' ),
							$token_status['error']
						)
					)
				);
			} else {
				printf(
					'<p class="description">%s</p>',
					esc_html(
						sprintf(
							/* translators: %s: Instagram user name. */
							__( 'Connected as @%s.', 'mixmymeals' ),
							$token_status['username']
						)
					)
				);
				printf(
					'<textarea id="mmm-instagram-caption" rows="6" maxlength="%d" style="width:100%%;">%s</textarea>',
					(int) self::CAPTION_MAX,
					esc_textarea( $is_recipe ? self::recipe_caption( $post->ID ) : self::default_caption( $plan ) )
				);
				printf(
					'<p><button type="button" class="button button-primary" id="mmm-instagram-post" %s>%s</button> <span id="mmm-instagram-post-status"></span></p>',
					$images ? '' : 'disabled',
					esc_html__( 'Post to Instagram', 'mixmymeals' )
				);

				// Scheduled publishing.
				printf( '<h4 style="margin:18px 0 6px;">%s</h4>', esc_html__( 'Schedule post', 'mixmymeals' ) );

				$scheduled_at = (int) get_post_meta( $post->ID, self::META_SCHEDULED_AT, true );

				if ( $scheduled_at ) {
					printf(
						'<p>%s <button type="button" class="button" id="mmm-instagram-unschedule">%s</button> <span id="mmm-instagram-schedule-status"></span></p>',
						esc_html(
							sprintf(
								/* translators: %s: formatted date and time. */
								__( 'Scheduled for %s — the images will be regenerated right before publishing.', 'mixmymeals' ),
								wp_date( get_option( 'date_format' ) . ' H:i', $scheduled_at )
							)
						),
						esc_html__( 'Cancel schedule', 'mixmymeals' )
					);
				} else {
					printf(
						'<p><input type="datetime-local" id="mmm-instagram-when" min="%s" /> <button type="button" class="button" id="mmm-instagram-schedule">%s</button> <span id="mmm-instagram-schedule-status"></span></p><p class="description">%s</p>',
						esc_attr( wp_date( 'Y-m-d\TH:i', time() + 10 * MINUTE_IN_SECONDS ) ),
						esc_html__( 'Schedule', 'mixmymeals' ),
						esc_html__( 'At the scheduled time the images are regenerated and the post is published with the caption above. Publishing runs via WP-Cron, so the exact minute can shift slightly.', 'mixmymeals' )
					);
				}

				$last_result = get_post_meta( $post->ID, self::META_LAST_RESULT, true );

				if ( is_array( $last_result ) && isset( $last_result['ok'] ) ) {
					if ( $last_result['ok'] ) {
						printf(
							'<p class="description" style="color:#008a20;">%s%s</p>',
							esc_html(
								sprintf(
									/* translators: %s: formatted date and time. */
									__( 'Published as scheduled on %s.', 'mixmymeals' ),
									wp_date( get_option( 'date_format' ) . ' H:i', (int) $last_result['time'] )
								)
							),
							'' !== (string) $last_result['permalink']
								? ' <a href="' . esc_url( $last_result['permalink'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'View post', 'mixmymeals' ) . '</a>'
								: ''
						);
					} else {
						printf(
							'<p class="description" style="color:#d63638;">%s</p>',
							esc_html(
								sprintf(
									/* translators: 1: formatted date and time, 2: error message. */
									__( 'Last scheduled publish failed on %1$s: %2$s', 'mixmymeals' ),
									wp_date( get_option( 'date_format' ) . ' H:i', (int) $last_result['time'] ),
									(string) $last_result['message']
								)
							)
						);
					}
				}
			}
		}

		// Per-slide captions (Instagram's multiple-captions feature): recipe
		// details for each day slide, ready to copy into the app. They are
		// also sent with the API request in case Meta enables them there.
		// Recipes are single-image posts, so there is nothing to list.
		if ( $is_recipe ) {
			echo '</div>';
			return;
		}

		printf(
			'<details style="margin-top:14px;"><summary style="cursor:pointer;font-weight:600;">%s</summary><p class="description">%s</p>',
			esc_html__( 'Captions per slide', 'mixmymeals' ),
			esc_html__( 'Ingredients, preparation steps and nutrition facts for each slide. Instagram supports one caption per slide since June 2026 — but only when creating a post in the app: the publishing API does not accept per-slide captions yet, and published posts cannot be switched to them afterwards. To use them today, post manually in the app (download the images above) and paste these texts per slide. They are already sent along via the API, so they will work automatically once Meta enables them there. Slide 1 uses the main caption above.', 'mixmymeals' )
		);

		$slide_no = 0;
		foreach ( self::get_jobs( $plan ) as $job_data ) {
			++$slide_no;
			$slide_cap = self::slide_caption( $plan, $job_data['job'] );
			if ( '' === $slide_cap ) {
				continue;
			}
			printf(
				'<p style="margin:12px 0 4px;"><strong>%d · %s</strong> <button type="button" class="button button-small mmm-copy-caption" data-target="mmm-slide-cap-%d">%s</button></p>
				<textarea id="mmm-slide-cap-%d" readonly rows="4" style="width:100%%;font-size:12px;">%s</textarea>',
				$slide_no,
				esc_html( $job_data['label'] ),
				$slide_no,
				esc_html__( 'Copy', 'mixmymeals' ),
				$slide_no,
				esc_textarea( $slide_cap )
			);
		}

		echo '</details>';

		echo '</div>';
	}

	/* ---------------------------------------------------------------------
	 * AJAX
	 * ------------------------------------------------------------------ */

	/**
	 * Renders one job for a plan or recipe (shared by the AJAX handler and
	 * the scheduled-publish cron).
	 *
	 * @param int    $post_id Meal plan or recipe post ID.
	 * @param string $job     Job key (cover/overview/day-N/cta/recipe).
	 * @return string|WP_Error Absolute path of the JPEG, or error.
	 */
	private function render_job( int $post_id, string $job ): string|WP_Error {

		if ( get_post_type( $post_id ) === self::RECIPE_POST_TYPE ) {
			return 'recipe' === $job
				? $this->render_recipe( $post_id )
				: new WP_Error( 'mmm_instagram_job', __( 'Unknown render job.', 'mixmymeals' ) );
		}

		if ( 'cover' === $job ) {
			return $this->render_cover( $post_id );
		}
		if ( 'overview' === $job ) {
			return $this->render_overview( $post_id );
		}
		if ( 'cta' === $job ) {
			return $this->render_cta( $post_id );
		}
		if ( preg_match( '/^day-([1-7])(?:-(\d+))?$/', $job, $m ) ) {
			return $this->render_day( $post_id, (int) $m[1], isset( $m[2] ) ? (int) $m[2] : 0 );
		}

		return new WP_Error( 'mmm_instagram_job', __( 'Unknown render job.', 'mixmymeals' ) );
	}

	/**
	 * Renders every image of a plan or recipe in one go (used by the
	 * scheduled publish, where no browser drives the per-image queue).
	 *
	 * @param int $post_id Meal plan or recipe post ID.
	 * @return true|WP_Error
	 */
	public function render_all_images( int $post_id ): bool|WP_Error {

		foreach ( self::get_jobs_for_post( $post_id ) as $job ) {
			$result = $this->render_job( $post_id, $job['job'] );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return true;
	}

	/**
	 * AJAX: renders one image (a plan slide or the single recipe image).
	 *
	 * @return void
	 */
	public function ajax_render(): void {

		$plan_id   = isset( $_POST['plan_id'] ) ? absint( $_POST['plan_id'] ) : 0;
		$job       = isset( $_POST['job'] ) ? sanitize_key( wp_unslash( $_POST['job'] ) ) : '';
		$post_type = $plan_id ? get_post_type( $plan_id ) : '';

		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false )
			|| ! $plan_id
			|| ! current_user_can( 'edit_post', $plan_id )
			|| ! in_array( $post_type, array( self::POST_TYPE, self::RECIPE_POST_TYPE ), true )
		) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'mixmymeals' ) ), 403 );
		}

		$result = $this->render_job( $plan_id, $job );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
		}

		$file  = basename( $result );
		$mtime = (int) filemtime( $result );
		$label = '';

		foreach ( self::get_jobs_for_post( $plan_id ) as $job_data ) {
			if ( $job_data['file'] === $file ) {
				$label = $job_data['label'];
				break;
			}
		}

		wp_send_json_success(
			array(
				'url'   => trailingslashit( self::get_plan_url( $plan_id ) ) . $file . '?v=' . $mtime,
				'label' => $label,
				'date'  => wp_date( get_option( 'date_format' ) . ' H:i', $mtime ),
			)
		);
	}

	/**
	 * AJAX: deletes all generated images of a plan.
	 *
	 * @return void
	 */
	public function ajax_delete(): void {

		$plan_id = isset( $_POST['plan_id'] ) ? absint( $_POST['plan_id'] ) : 0;

		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false )
			|| ! $plan_id
			|| ! current_user_can( 'edit_post', $plan_id )
			|| ! in_array( get_post_type( $plan_id ), array( self::POST_TYPE, self::RECIPE_POST_TYPE ), true )
		) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'mixmymeals' ) ), 403 );
		}

		self::delete_plan_images( $plan_id );

		wp_send_json_success();
	}

	/* ---------------------------------------------------------------------
	 * Credential context: per-user accounts with a site-wide fallback.
	 *
	 * Every WordPress user stores their own Instagram credentials as user
	 * meta (mmm_instagram_*). Whoever acts (posts, schedules) uses their
	 * own credential set — there is deliberately no site-wide fallback.
	 * Cron jobs restore the scheduling user's context via set_acting_user().
	 * ------------------------------------------------------------------ */

	/**
	 * User-meta prefix of the per-user credential set.
	 */
	const USER_META_PREFIX = 'mmm_instagram_';

	/**
	 * Explicit acting user for credential resolution. null = the current
	 * WordPress user; 0 = force the site-wide credential set.
	 *
	 * @var int|null
	 */
	private static ?int $acting_user = null;

	/**
	 * Sets (or resets, with null) the acting user for credential resolution.
	 *
	 * @param int|null $user_id User ID, 0 for the site account, null to reset.
	 * @return void
	 */
	public static function set_acting_user( ?int $user_id ): void {
		self::$acting_user = $user_id;
	}

	/**
	 * The user whose credentials apply right now.
	 *
	 * @return int User ID, or 0 (site account context).
	 */
	private static function acting_user_id(): int {
		return null !== self::$acting_user ? self::$acting_user : (int) get_current_user_id();
	}

	/**
	 * Reads one credential/bookkeeping value from the acting credential set.
	 *
	 * @param string $key Key without prefix (e.g. 'access_token').
	 * @return string
	 */
	private static function cred_get( string $key ): string {
		$uid = self::acting_user_id();
		if ( $uid <= 0 ) {
			return '';
		}
		return trim( (string) get_user_meta( $uid, self::USER_META_PREFIX . $key, true ) );
	}

	/**
	 * Writes one credential/bookkeeping value into the acting credential set.
	 *
	 * @param string $key   Key without prefix.
	 * @param mixed  $value Value.
	 * @return void
	 */
	private static function cred_update( string $key, $value ): void {
		$uid = self::acting_user_id();
		if ( $uid <= 0 ) {
			return;
		}
		update_user_meta( $uid, self::USER_META_PREFIX . $key, $value );
	}

	/**
	 * Deletes one credential/bookkeeping value from the acting credential set.
	 *
	 * @param string $key Key without prefix.
	 * @return void
	 */
	private static function cred_delete( string $key ): void {
		$uid = self::acting_user_id();
		if ( $uid <= 0 ) {
			return;
		}
		delete_user_meta( $uid, self::USER_META_PREFIX . $key );
	}

	/* ---------------------------------------------------------------------
	 * Instagram API (Instagram Login variant, graph.instagram.com)
	 * ------------------------------------------------------------------ */

	/**
	 * The acting user's long-lived access token.
	 *
	 * @return string
	 */
	public static function get_access_token(): string {
		return self::cred_get( 'access_token' );
	}

	/**
	 * GET request against the Instagram API.
	 *
	 * @param string $path Path starting with '/'.
	 * @param array  $args Query arguments (access token is added).
	 * @return array|WP_Error Decoded response.
	 */
	private static function api_get( string $path, array $args = array() ): array|WP_Error {

		$args['access_token'] = self::get_access_token();

		return self::api_response(
			wp_remote_get(
				self::API_BASE . $path . '?' . http_build_query( $args ),
				array( 'timeout' => 20 )
			)
		);
	}

	/**
	 * POST request against the Instagram API.
	 *
	 * @param string $path Path starting with '/'.
	 * @param array  $body Body arguments (access token is added).
	 * @return array|WP_Error Decoded response.
	 */
	private static function api_post( string $path, array $body = array() ): array|WP_Error {

		$body['access_token'] = self::get_access_token();

		return self::api_response(
			wp_remote_post(
				self::API_BASE . $path,
				array(
					'timeout' => 30,
					'body'    => $body,
				)
			)
		);
	}

	/**
	 * Normalizes an Instagram API response; error details are attached as
	 * error data so callers can inspect codes/subcodes.
	 *
	 * @param array|WP_Error $response wp_remote_* response.
	 * @return array|WP_Error
	 */
	private static function api_response( array|WP_Error $response ): array|WP_Error {

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = (string) wp_remote_retrieve_body( $response );
		$data   = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			/*
			 * Kein JSON: meist eine HTML-Fehlerseite oder ein Proxy dazwischen.
			 * Den Anfang zeigen, sonst bleibt nur "unerwartete Antwort". Tags
			 * durch Leerzeichen ersetzen, sonst klebt "<title>502</title>Bad
			 * Gateway" zu "502Bad Gateway" zusammen.
			 */
			$raw = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( str_replace( '<', ' <', $body ) ) ) );

			return new WP_Error(
				'mmm_ig_api',
				sprintf(
					/* translators: 1: HTTP status code, 2: beginning of the response body. */
					__( 'Instagram API: HTTP %1$d, unreadable response – %2$s', 'mixmymeals' ),
					$status,
					'' !== $raw ? mb_substr( $raw, 0, 200 ) : __( '(empty body)', 'mixmymeals' )
				),
				array( 'status' => $status )
			);
		}

		if ( isset( $data['error'] ) ) {
			$error = (array) $data['error'];

			/*
			 * error_user_msg ist die für Menschen gedachte Variante, message die
			 * technische — beide mitnehmen, wenn sie sich unterscheiden. Metas
			 * numerische Codes sind der eigentliche Schlüssel zur Ursache und
			 * fehlten bisher komplett.
			 */
			$message = (string) ( $error['error_user_msg'] ?? $error['message'] ?? __( 'Unknown error', 'mixmymeals' ) );

			if ( ! empty( $error['error_user_msg'] )
				&& ! empty( $error['message'] )
				&& $error['error_user_msg'] !== $error['message'] ) {
				$message .= ' (' . $error['message'] . ')';
			}

			$codes = array();
			if ( isset( $error['code'] ) ) {
				$codes[] = 'Code ' . $error['code'];
			}
			if ( isset( $error['error_subcode'] ) && $error['error_subcode'] ) {
				$codes[] = 'Subcode ' . $error['error_subcode'];
			}

			$full = sprintf(
				/* translators: 1: HTTP status code, 2: error message from the API. */
				__( 'Instagram API: HTTP %1$d – %2$s', 'mixmymeals' ),
				$status,
				rtrim( trim( $message ), '.' ) . '.'
			);

			if ( $codes ) {
				$full .= ' [' . implode( ', ', $codes ) . ']';
			}

			$hint = self::api_error_hint( (int) ( $error['code'] ?? 0 ), (int) ( $error['error_subcode'] ?? 0 ) );
			if ( '' !== $hint ) {
				$full .= ' ' . $hint;
			}

			return new WP_Error( 'mmm_ig_api', $full, $error );
		}

		return $data;
	}

	/**
	 * Plain-language hint for the Meta error codes that actually show up when
	 * posting, so the message says what to do rather than just what went wrong.
	 *
	 * @param int $code    Meta error code.
	 * @param int $subcode Meta error subcode.
	 * @return string Empty when there is nothing useful to add.
	 */
	private static function api_error_hint( int $code, int $subcode ): string {

		// Subcode zuerst: er ist spezifischer als der Sammelcode darüber.
		if ( 458 === $subcode || 460 === $subcode ) {
			return __( 'The user has revoked access or changed their password — store a new access token in your profile under “MixMyMeals: Social accounts”.', 'mixmymeals' );
		}

		$hints = array(
			190  => __( 'Access token expired or invalid — store a new one in your profile under “MixMyMeals: Social accounts”.', 'mixmymeals' ),
			10   => __( 'Permission missing — the app lacks the required permissions (instagram_business_content_publish).', 'mixmymeals' ),
			200  => __( 'Permission missing — check the app permissions and that the account is a business/creator account.', 'mixmymeals' ),
			4    => __( 'Rate limit reached — Meta is throttling. Try again later.', 'mixmymeals' ),
			17   => __( 'Rate limit reached — Meta is throttling. Try again later.', 'mixmymeals' ),
			32   => __( 'Rate limit reached — Meta is throttling. Try again later.', 'mixmymeals' ),
			613  => __( 'Rate limit reached — the publishing quota (25 posts per 24 h) is exhausted.', 'mixmymeals' ),
			9004 => __( 'The image could not be fetched — the URL must be publicly reachable (localhost will not work).', 'mixmymeals' ),
			2207003 => __( 'The image could not be downloaded — check that the URL is publicly reachable.', 'mixmymeals' ),
			2207026 => __( 'Unsupported image format — Instagram expects JPEG in a supported aspect ratio.', 'mixmymeals' ),
			2207032 => __( 'Creating the post failed on Meta’s side — try again in a few minutes.', 'mixmymeals' ),
		);

		return $hints[ $code ] ?? '';
	}

	/**
	 * Checks the token by fetching the connected account. Result is cached
	 * for an hour (keyed by token, so a new token gets a fresh check).
	 *
	 * @param bool $force Skip the cache.
	 * @return array Keys: 'ok' (bool), 'username' (string), 'error' (string).
	 */
	public static function token_status( bool $force = false ): array {

		$token = self::get_access_token();

		if ( '' === $token ) {
			return array(
				'ok'       => false,
				'username' => '',
				'error'    => __( 'No access token stored. Instagram credentials are per user, in your own profile under “MixMyMeals: Social accounts”.', 'mixmymeals' ),
			);
		}

		$cache_key = 'mmm_ig_status_' . substr( md5( $token ), 0, 12 );

		if ( ! $force ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$response = self::api_get( '/me', array( 'fields' => 'user_id,username' ) );

		if ( is_wp_error( $response ) ) {
			$status = array(
				'ok'       => false,
				'username' => '',
				'error'    => $response->get_error_message(),
			);
		} else {
			$user_id  = (string) ( $response['user_id'] ?? $response['id'] ?? '' );
			$username = (string) ( $response['username'] ?? '' );
			if ( '' !== $user_id ) {
				self::cred_update( 'ig_user_id', $user_id );
			}
			// Persist the handle so slide rendering can use it without an API call.
			self::cred_update( 'ig_username', $username );
			$status = array(
				'ok'       => true,
				'username' => $username,
				'error'    => '',
			);
		}

		set_transient( $cache_key, $status, HOUR_IN_SECONDS );

		return $status;
	}

	/**
	 * The Instagram user ID the token belongs to (cached via token_status).
	 *
	 * @return string
	 */
	private static function get_ig_user_id(): string {

		$user_id = self::cred_get( 'ig_user_id' );

		if ( '' === $user_id ) {
			self::token_status( true );
			$user_id = self::cred_get( 'ig_user_id' );
		}

		return $user_id;
	}

	/**
	 * Cron: refreshes the long-lived token once a week. Long-lived tokens
	 * expire after 60 days; refreshing resets that window. Failures are
	 * stored for display in the settings.
	 *
	 * @return void
	 */
	public static function maybe_refresh_token(): void {

		// Refresh every user's own token.
		$user_ids = get_users(
			array(
				'fields'       => 'ID',
				'meta_key'     => self::USER_META_PREFIX . 'access_token', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_compare' => 'EXISTS',
			)
		);
		foreach ( $user_ids as $uid ) {
			self::set_acting_user( (int) $uid );
			self::maybe_refresh_acting_token();
		}

		self::set_acting_user( null );
	}

	/**
	 * Refreshes the ACTING credential set's token when it is due.
	 *
	 * @return void
	 */
	private static function maybe_refresh_acting_token(): void {

		$token = self::get_access_token();

		if ( '' === $token ) {
			return;
		}

		$last = (int) self::cred_get( 'token_refreshed' );

		if ( $last > time() - 7 * DAY_IN_SECONDS ) {
			return;
		}

		$response = wp_remote_get(
			self::REFRESH_URL . '?' . http_build_query(
				array(
					'grant_type'   => 'ig_refresh_token',
					'access_token' => $token,
				)
			),
			array( 'timeout' => 20 )
		);

		$data = is_wp_error( $response )
			? array()
			: (array) json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $data['access_token'] ) ) {
			self::cred_update( 'access_token', (string) $data['access_token'] );
			self::cred_update( 'token_refreshed', time() );
			self::cred_update(
				'token_expires',
				time() + (int) ( $data['expires_in'] ?? 60 * DAY_IN_SECONDS )
			);
			self::cred_delete( 'token_error' );
			return;
		}

		$message = is_wp_error( $response )
			? $response->get_error_message()
			: (string) ( $data['error']['message'] ?? __( 'Unknown error', 'mixmymeals' ) );

		self::cred_update( 'token_error', $message );

		if ( function_exists( 'mmm_write_to_log' ) ) {
			mmm_write_to_log( 'MixMyMeals Instagram token refresh failed: ' . $message );
		}
	}

	/**
	 * Suggested caption for a plan: title, one line per day, hashtags.
	 * Shown as an editable field in the meta box before posting.
	 *
	 * @param Mixmymeals_Meal_Plan $plan Plan object.
	 * @return string
	 */
	public static function default_caption( Mixmymeals_Meal_Plan $plan ): string {

		$blocks   = array( self::clean_text( get_the_title( $plan->get_id() ) ) );
		$by_day   = self::meals_by_day( $plan );
		$day_rows = array();

		foreach ( $plan->get_day_range() as $day ) {
			$meals = $by_day[ $day ] ?? array();
			if ( ! $meals ) {
				continue;
			}
			$day_rows[] = self::weekday_name( $day, true ) . ' '
				. implode( ' · ', wp_list_pluck( $meals, 'title' ) );
		}

		if ( $day_rows ) {
			$blocks[] = implode( "\n", $day_rows );
		}

		$hashtags = trim( (string) self::opt( 'hashtags', '' ) );
		if ( '' !== $hashtags ) {
			$blocks[] = $hashtags;
		}

		return mb_substr( implode( "\n\n", $blocks ), 0, self::CAPTION_MAX );
	}

	/**
	 * Suggested caption for one slide of the carousel (Instagram's multiple
	 * captions feature): the day slides get the pictured recipe's ingredients,
	 * preparation steps and nutrition facts. '' means "use the main caption".
	 *
	 * @param Mixmymeals_Meal_Plan $plan Plan object.
	 * @param string               $job  Job key (cover/overview/day-N/cta).
	 * @return string
	 */
	public static function slide_caption( Mixmymeals_Meal_Plan $plan, string $job ): string {

		if ( preg_match( '/^day-([1-7])(?:-(\d+))?$/', $job, $m ) ) {
			$by_day = self::meals_by_day( $plan );
			$meals  = $by_day[ (int) $m[1] ] ?? array();
			$index  = isset( $m[2] ) ? (int) $m[2] : 0;
			$meal   = $meals[ $index ] ?? null;

			// One recipe per day slide → the caption is that recipe's block.
			return null !== $meal ? self::recipe_slide_caption( $meal, array() ) : '';
		}

		if ( 'overview' === $job ) {
			$by_day = self::meals_by_day( $plan );
			$lines  = array( self::clean_text( get_the_title( $plan->get_id() ) ) );
			foreach ( $plan->get_day_range() as $day ) {
				$meals = $by_day[ $day ] ?? array();
				if ( ! $meals ) {
					continue;
				}
				$lines[] = self::weekday_name( $day, true ) . ' '
					. implode( ' · ', wp_list_pluck( $meals, 'title' ) );
			}

			return mb_substr( implode( "\n", $lines ), 0, self::CAPTION_MAX );
		}

		if ( 'cta' === $job ) {
			$parts   = array();
			$parts[] = self::clean_text( (string) self::opt( 'cta_text', __( 'Save this plan', 'mixmymeals' ) ) );
			$parts[] = self::clean_text( (string) self::opt( 'cta_subtext', __( 'Full recipes & shopping list — link in bio', 'mixmymeals' ) ) );

			$hashtags = trim( (string) self::opt( 'hashtags', '' ) );
			if ( '' !== $hashtags ) {
				$parts[] = $hashtags;
			}

			return mb_substr( implode( "\n\n", array_filter( $parts ) ), 0, self::CAPTION_MAX );
		}

		// Cover: the main (editable) caption is used as the first caption.
		return '';
	}

	/**
	 * Builds the recipe caption for a day slide: nutrition per serving,
	 * ingredients scaled to the meal's servings and the preparation steps.
	 * Falls back to step titles (and finally hard truncation) so the result
	 * stays within Instagram's caption limit.
	 *
	 * @param array $hero_meal Hero meal entry (see meals_by_day()).
	 * @param array $meals     All meals of the day.
	 * @return string
	 */
	private static function recipe_slide_caption( array $hero_meal, array $meals ): string {

		// Order: the hero recipe first, then the remaining meals of the day in
		// their original order — so a day/meal-time with several recipes names
		// (and details) every single one of them.
		$ordered = array( $hero_meal );
		foreach ( $meals as $meal ) {
			if ( $meal !== $hero_meal ) {
				$ordered[] = $meal;
			}
		}

		// Progressive detail levels applied uniformly to all recipes until the
		// whole caption fits Instagram's limit. Every recipe always keeps at
		// least its name, meal time and nutrition, so none is dropped.
		$caption = '';
		foreach ( array( 'full', 'step_titles', 'no_steps', 'minimal' ) as $level ) {
			$blocks = array();
			foreach ( $ordered as $meal ) {
				$block = self::meal_caption_block( $meal, $level );
				if ( '' !== $block ) {
					$blocks[] = $block;
				}
			}
			$caption = implode( "\n\n———\n\n", $blocks );
			if ( mb_strlen( $caption ) <= self::CAPTION_MAX ) {
				return $caption;
			}
		}

		return mb_substr( $caption, 0, self::CAPTION_MAX );
	}

	/**
	 * Builds one recipe's caption block for a day slide: name and meal time,
	 * nutrition per serving, ingredients scaled to the meal and (depending on
	 * the detail level) the preparation steps.
	 *
	 * @param array  $meal  Meal entry (see meals_by_day()).
	 * @param string $level full | step_titles | no_steps | minimal.
	 * @return string Empty string when the recipe is missing/invalid.
	 */
	private static function meal_caption_block( array $meal, string $level ): string {

		$recipe_id = (int) ( $meal['recipe_id'] ?? 0 );
		$recipe    = new Mixmymeals_Recipe( $recipe_id );

		if ( ! $recipe_id || ! $recipe->is_valid() ) {
			return '';
		}

		$servings = max( 1, (int) ( $meal['servings'] ?? 1 ) );
		$scale    = $recipe->get_servings() > 0 ? $servings / $recipe->get_servings() : 1.0;

		$blocks = array();

		// Header: recipe name and its meal time.
		$header = self::clean_text( (string) ( $meal['title'] ?? '' ) );
		if ( ! empty( $meal['type_label'] ) ) {
			$header .= ' (' . $meal['type_label'] . ')';
		}
		$blocks[] = $header;

		// Nutrition per serving.
		$nutrition = $recipe->calculate_nutrition_per_serving();
		$facts     = array();
		if ( ! empty( $nutrition['calories'] ) ) {
			$facts[] = (int) round( $nutrition['calories'] ) . ' ' . __( 'kcal', 'mixmymeals' );
		}
		if ( ! empty( $nutrition['protein'] ) ) {
			$facts[] = (int) round( $nutrition['protein'] ) . ' g ' . __( 'Protein', 'mixmymeals' );
		}
		if ( ! empty( $nutrition['carbs'] ) ) {
			$facts[] = (int) round( $nutrition['carbs'] ) . ' g ' . __( 'Carbs', 'mixmymeals' );
		}
		if ( ! empty( $nutrition['fat'] ) ) {
			$facts[] = (int) round( $nutrition['fat'] ) . ' g ' . __( 'Fat', 'mixmymeals' );
		}
		if ( $facts ) {
			$blocks[] = implode( ' · ', $facts ) . ' (' . __( 'per serving', 'mixmymeals' ) . ')';
		}

		// Ingredients, aggregated across steps and scaled to the meal.
		if ( 'minimal' !== $level ) {
			$totals = array();
			foreach ( $recipe->get_ingredients_scaled( $scale ) as $ingredient ) {
				$key = $ingredient['id'] . '|' . $ingredient['unit'];
				if ( ! isset( $totals[ $key ] ) ) {
					$totals[ $key ] = array(
						'name'   => self::clean_text( (string) $ingredient['name'] ),
						'unit'   => (string) $ingredient['unit'],
						'amount' => 0.0,
					);
				}
				$totals[ $key ]['amount'] += (float) $ingredient['amount'];
			}

			if ( $totals ) {
				$lines = array(
					sprintf(
						/* translators: %d: number of servings. */
						__( 'Ingredients (for %d servings):', 'mixmymeals' ),
						$servings
					),
				);
				foreach ( $totals as $ingredient ) {
					$lines[] = '– ' . self::format_amount( $ingredient['amount'] )
						. ' ' . $ingredient['unit'] . ' ' . $ingredient['name'];
				}
				$blocks[] = implode( "\n", $lines );
			}
		}

		// Preparation steps: full descriptions, then titles only.
		if ( 'full' === $level || 'step_titles' === $level ) {
			$steps = $recipe->get_steps();
			if ( $steps ) {
				$step_block = self::steps_block( $steps, 'step_titles' === $level );
				if ( '' !== $step_block ) {
					$blocks[] = $step_block;
				}
			}
		}

		return implode( "\n\n", array_filter( $blocks ) );
	}

	/**
	 * Renders the numbered preparation block from the recipe steps.
	 *
	 * @param array $steps       Steps (title/description arrays).
	 * @param bool  $titles_only Use only the step titles (compact variant).
	 * @return string
	 */
	private static function steps_block( array $steps, bool $titles_only ): string {

		$lines = array( __( 'Preparation:', 'mixmymeals' ) );
		$n     = 0;

		foreach ( $steps as $step ) {
			$title = self::clean_text( wp_strip_all_tags( (string) ( $step['title'] ?? '' ) ) );
			$text  = self::clean_text( wp_strip_all_tags( (string) ( $step['description'] ?? '' ) ) );
			$text  = trim( preg_replace( '/\s+/u', ' ', $text ) );

			$line = $titles_only
				? ( '' !== $title ? $title : mb_substr( $text, 0, 80 ) )
				: trim( $title . ( '' !== $title && '' !== $text ? ': ' : '' ) . $text );

			if ( '' === $line ) {
				continue;
			}

			++$n;
			$lines[] = $n . '. ' . $line;
		}

		return count( $lines ) > 1 ? implode( "\n", $lines ) : '';
	}

	/**
	 * Human-friendly amount: whole numbers without decimals, otherwise up to
	 * two locale-formatted decimals.
	 *
	 * @param float $amount Amount.
	 * @return string
	 */
	private static function format_amount( float $amount ): string {

		if ( abs( $amount - round( $amount ) ) < 0.01 ) {
			return number_format_i18n( (int) round( $amount ) );
		}

		return rtrim( rtrim( number_format_i18n( $amount, 2 ), '0' ), ',.' );
	}

	/**
	 * Polls a media container until Instagram reports FINISHED.
	 *
	 * @param string $container_id Container ID.
	 * @param int    $deadline     Unix timestamp to stop waiting at.
	 * @return true|WP_Error True when finished; 'mmm_ig_pending' error when
	 *                       the deadline passed (caller may retry later).
	 */
	private static function wait_for_container( string $container_id, int $deadline ): bool|WP_Error {

		do {
			$status = self::api_get( '/' . rawurlencode( $container_id ), array( 'fields' => 'status_code' ) );

			if ( is_wp_error( $status ) ) {
				return $status;
			}

			$code = (string) ( $status['status_code'] ?? '' );

			if ( 'FINISHED' === $code || 'PUBLISHED' === $code ) {
				return true;
			}

			if ( 'ERROR' === $code || 'EXPIRED' === $code ) {
				return new WP_Error(
					'mmm_ig_container',
					__( 'Instagram reported an error while processing the images. Check that the site is publicly reachable and the images are valid JPEGs.', 'mixmymeals' )
				);
			}

			if ( time() >= $deadline ) {
				return new WP_Error( 'mmm_ig_pending', __( 'Instagram is still processing the images.', 'mixmymeals' ) );
			}

			sleep( 2 );
		} while ( true );
	}

	/**
	 * Shared permission/nonce check for the posting AJAX handlers.
	 *
	 * @return int Plan ID (sends a JSON error and exits when not allowed).
	 */
	private function ajax_post_guard(): int {

		$plan_id = isset( $_POST['plan_id'] ) ? absint( $_POST['plan_id'] ) : 0;

		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false )
			|| ! $plan_id
			|| ! current_user_can( 'edit_post', $plan_id )
			|| ! in_array( get_post_type( $plan_id ), array( self::POST_TYPE, self::RECIPE_POST_TYPE ), true )
		) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'mixmymeals' ) ), 403 );
		}

		if ( '' === self::get_access_token() ) {
			wp_send_json_error( array( 'message' => __( 'No access token stored. Instagram credentials are per user, in your own profile under “MixMyMeals: Social accounts”.', 'mixmymeals' ) ), 400 );
		}

		return $plan_id;
	}

	/**
	 * AJAX step 1: creates one media container per generated image and
	 * returns their IDs. Instagram fetches the images asynchronously from
	 * their public URLs, so this step returns quickly.
	 *
	 * @return void
	 */
	public function ajax_post_start(): void {

		$plan_id = $this->ajax_post_guard();

		$caption = isset( $_POST['caption'] )
			? mb_substr( sanitize_textarea_field( wp_unslash( $_POST['caption'] ) ), 0, self::CAPTION_MAX )
			: '';

		$images = self::list_images( $plan_id );

		if ( ! $images ) {
			wp_send_json_error(
				array( 'message' => __( 'No generated images found — generate them first.', 'mixmymeals' ) ),
				400
			);
		}

		$user_id = self::get_ig_user_id();

		if ( '' === $user_id ) {
			$status = self::token_status( true );
			wp_send_json_error(
				array(
					'message' => '' !== $status['error']
						? $status['error']
						: __( 'Unexpected response from the Instagram API.', 'mixmymeals' ),
				),
				400
			);
		}

		$result = self::create_containers( $plan_id, $caption, $user_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Creates the media containers for a post: one per image, with the
	 * per-slide captions attached opportunistically (the publishing API does
	 * not officially honour captions on carousel children yet, so a failed
	 * container is retried once without its caption). Shared by the AJAX
	 * flow and the scheduled publish.
	 *
	 * @param int    $post_id Meal plan or recipe post ID.
	 * @param string $caption Main caption.
	 * @param string $user_id Instagram user ID.
	 * @return array|WP_Error Array with 'children' (IDs) and 'single' (bool).
	 */
	private static function create_containers( int $post_id, string $caption, string $user_id ): array|WP_Error {

		$images = self::list_images( $post_id );

		if ( ! $images ) {
			return new WP_Error(
				'mmm_ig_images',
				__( 'No generated images found — generate them first.', 'mixmymeals' )
			);
		}

		$single   = 1 === count( $images );
		$children = array();

		// Recipes always publish as a single image and skip the slide captions.
		$plan = get_post_type( $post_id ) === self::POST_TYPE
			? new Mixmymeals_Meal_Plan( $post_id )
			: null;

		$file_to_job = array();
		foreach ( self::get_jobs_for_post( $post_id ) as $job ) {
			$file_to_job[ $job['file'] ] = $job['job'];
		}

		foreach ( $images as $image ) {
			$body = array(
				// Public URL without the cache-busting query string.
				'image_url' => trailingslashit( self::get_plan_url( $post_id ) ) . $image['file'],
			);

			if ( $single ) {
				$body['caption'] = $caption;
			} else {
				$body['is_carousel_item'] = 'true';

				$job       = (string) ( $file_to_job[ $image['file'] ] ?? '' );
				$slide_cap = 'cover' === $job || '' === $job || null === $plan
					? $caption
					: self::slide_caption( $plan, $job );
				if ( '' !== $slide_cap ) {
					$body['caption'] = $slide_cap;
				}
			}

			$container = self::api_post( '/' . rawurlencode( $user_id ) . '/media', $body );

			// Safety net: if the caption on a carousel child is what broke the
			// request, create the container without it rather than failing.
			if ( ( is_wp_error( $container ) || empty( $container['id'] ) ) && ! $single && isset( $body['caption'] ) ) {
				unset( $body['caption'] );
				$container = self::api_post( '/' . rawurlencode( $user_id ) . '/media', $body );
			}

			if ( is_wp_error( $container ) || empty( $container['id'] ) ) {
				return new WP_Error(
					'mmm_ig_container',
					sprintf(
						/* translators: 1: image file name, 2: error message. */
						__( 'Could not hand image %1$s to Instagram: %2$s', 'mixmymeals' ),
						$image['file'],
						is_wp_error( $container )
							? $container->get_error_message()
							: __( 'Unknown error', 'mixmymeals' )
					)
				);
			}

			$children[] = (string) $container['id'];
		}

		return array(
			'children' => $children,
			'single'   => $single,
		);
	}

	/**
	 * Publishes a plan or recipe to Instagram in one server-side pass:
	 * containers → processing wait → carousel (for plans) → publish. Used by
	 * the scheduled publish, where no browser drives the retry loop.
	 *
	 * @param int    $post_id Meal plan or recipe post ID.
	 * @param string $caption Main caption.
	 * @param int    $budget  Seconds to wait for Instagram's processing.
	 * @return string|WP_Error Permalink ('' when unavailable), or error.
	 */
	public function publish_now( int $post_id, string $caption, int $budget = 120 ): string|WP_Error {

		if ( '' === self::get_access_token() ) {
			return new WP_Error( 'mmm_ig_token', __( 'No access token stored. Instagram credentials are per user, in your own profile under “MixMyMeals: Social accounts”.', 'mixmymeals' ) );
		}

		$user_id = self::get_ig_user_id();
		if ( '' === $user_id ) {
			$status = self::token_status( true );
			return new WP_Error(
				'mmm_ig_token',
				'' !== $status['error'] ? $status['error'] : __( 'Unexpected response from the Instagram API.', 'mixmymeals' )
			);
		}

		$containers = self::create_containers( $post_id, $caption, $user_id );
		if ( is_wp_error( $containers ) ) {
			return $containers;
		}

		$deadline = time() + max( 30, $budget );

		foreach ( $containers['children'] as $child ) {
			$done = self::wait_for_container( $child, $deadline );
			if ( is_wp_error( $done ) ) {
				return $done;
			}
		}

		if ( $containers['single'] ) {
			$creation_id = $containers['children'][0];
		} else {
			$carousel = self::api_post(
				'/' . rawurlencode( $user_id ) . '/media',
				array(
					'media_type' => 'CAROUSEL',
					'children'   => implode( ',', $containers['children'] ),
					'caption'    => $caption,
				)
			);

			if ( is_wp_error( $carousel ) || empty( $carousel['id'] ) ) {
				return is_wp_error( $carousel )
					? $carousel
					: new WP_Error( 'mmm_ig_api', __( 'Unexpected response from the Instagram API.', 'mixmymeals' ) );
			}

			$creation_id = (string) $carousel['id'];

			$done = self::wait_for_container( $creation_id, $deadline );
			if ( is_wp_error( $done ) ) {
				return $done;
			}
		}

		$published = self::api_post(
			'/' . rawurlencode( $user_id ) . '/media_publish',
			array( 'creation_id' => $creation_id )
		);

		if ( is_wp_error( $published ) || empty( $published['id'] ) ) {
			return is_wp_error( $published )
				? $published
				: new WP_Error( 'mmm_ig_api', __( 'Unexpected response from the Instagram API.', 'mixmymeals' ) );
		}

		$permalink = '';
		$media     = self::api_get( '/' . rawurlencode( (string) $published['id'] ), array( 'fields' => 'permalink' ) );
		if ( ! is_wp_error( $media ) && ! empty( $media['permalink'] ) ) {
			$permalink = (string) $media['permalink'];
		}

		return $permalink;
	}

	/* ---------------------------------------------------------------------
	 * Scheduled publishing
	 * ------------------------------------------------------------------ */

	/**
	 * AJAX: schedules a publish for a plan or recipe. The images are
	 * regenerated freshly at publish time; the caption is snapshotted now.
	 *
	 * @return void
	 */
	public function ajax_schedule(): void {

		$post_id = $this->ajax_post_guard();

		$when = isset( $_POST['when'] ) ? sanitize_text_field( wp_unslash( $_POST['when'] ) ) : '';

		$caption = isset( $_POST['caption'] )
			? mb_substr( sanitize_textarea_field( wp_unslash( $_POST['caption'] ) ), 0, self::CAPTION_MAX )
			: '';

		$dt = $when ? date_create_immutable( $when, wp_timezone() ) : false;

		if ( ! $dt ) {
			wp_send_json_error( array( 'message' => __( 'Please pick a valid date and time.', 'mixmymeals' ) ), 400 );
		}

		$timestamp = $dt->getTimestamp();

		if ( $timestamp < time() + MINUTE_IN_SECONDS ) {
			wp_send_json_error( array( 'message' => __( 'The scheduled time must be in the future.', 'mixmymeals' ) ), 400 );
		}

		self::unschedule_publish( $post_id );

		update_post_meta( $post_id, self::META_SCHEDULED_AT, $timestamp );
		update_post_meta( $post_id, self::META_SCHEDULED_CAPTION, $caption );
		update_post_meta( $post_id, self::META_SCHEDULED_USER, get_current_user_id() );
		update_post_meta( $post_id, self::META_ATTEMPT, 0 );

		wp_schedule_single_event( $timestamp, self::PUBLISH_HOOK, array( $post_id ) );

		wp_send_json_success(
			array(
				'scheduled' => wp_date( get_option( 'date_format' ) . ' H:i', $timestamp ),
			)
		);
	}

	/**
	 * AJAX: cancels a scheduled publish.
	 *
	 * @return void
	 */
	public function ajax_unschedule(): void {

		$post_id = isset( $_POST['plan_id'] ) ? absint( $_POST['plan_id'] ) : 0;

		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false )
			|| ! $post_id
			|| ! current_user_can( 'edit_post', $post_id )
			|| ! in_array( get_post_type( $post_id ), array( self::POST_TYPE, self::RECIPE_POST_TYPE ), true )
		) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'mixmymeals' ) ), 403 );
		}

		self::unschedule_publish( $post_id );
		delete_post_meta( $post_id, self::META_SCHEDULED_AT );
		delete_post_meta( $post_id, self::META_SCHEDULED_CAPTION );
		delete_post_meta( $post_id, self::META_SCHEDULED_USER );
		delete_post_meta( $post_id, self::META_ATTEMPT );

		wp_send_json_success();
	}

	/**
	 * Removes every pending publish event for a post.
	 *
	 * @param int $post_id Meal plan or recipe post ID.
	 * @return void
	 */
	private static function unschedule_publish( int $post_id ): void {

		$timestamp = wp_next_scheduled( self::PUBLISH_HOOK, array( $post_id ) );

		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::PUBLISH_HOOK, array( $post_id ) );
			$timestamp = wp_next_scheduled( self::PUBLISH_HOOK, array( $post_id ) );
		}
	}

	/**
	 * Cron: regenerates the images and publishes the post. When Instagram is
	 * still processing at the deadline, the run is retried a few minutes
	 * later (up to PUBLISH_MAX_ATTEMPTS). The outcome lands in post meta so
	 * the meta box can show it.
	 *
	 * @param int $post_id Meal plan or recipe post ID.
	 * @return void
	 */
	public function run_scheduled_publish( int $post_id ): void {

		$post_type = get_post_type( $post_id );

		if ( ! in_array( $post_type, array( self::POST_TYPE, self::RECIPE_POST_TYPE ), true ) ) {
			return;
		}

		// Publish with the credentials of whoever scheduled this post (their
		// own account when they stored one, the site account otherwise).
		$scheduled_by = (int) get_post_meta( $post_id, self::META_SCHEDULED_USER, true );
		self::set_acting_user( $scheduled_by > 0 ? $scheduled_by : 0 );

		try {
			$caption = (string) get_post_meta( $post_id, self::META_SCHEDULED_CAPTION, true );
			if ( '' === $caption ) {
				$caption = self::RECIPE_POST_TYPE === $post_type
					? self::recipe_caption( $post_id )
					: self::default_caption( new Mixmymeals_Meal_Plan( $post_id ) );
			}

			// Fresh images first, then the whole publish pass.
			$result = $this->render_all_images( $post_id );
			if ( ! is_wp_error( $result ) ) {
				$result = $this->publish_now( $post_id, $caption );
			}

			if ( is_wp_error( $result ) ) {
				$attempt = (int) get_post_meta( $post_id, self::META_ATTEMPT, true ) + 1;
				update_post_meta( $post_id, self::META_ATTEMPT, $attempt );

				if ( 'mmm_ig_pending' === $result->get_error_code() && $attempt < self::PUBLISH_MAX_ATTEMPTS ) {
					wp_schedule_single_event( time() + self::PUBLISH_RETRY_DELAY, self::PUBLISH_HOOK, array( $post_id ) );
					self::store_publish_result(
						$post_id,
						false,
						sprintf(
							/* translators: %d: minutes until the retry. */
							__( 'Instagram is still processing — retrying in %d minutes.', 'mixmymeals' ),
							(int) ( self::PUBLISH_RETRY_DELAY / MINUTE_IN_SECONDS )
						),
						''
					);
					return;
				}

				self::store_publish_result( $post_id, false, $result->get_error_message(), '' );
				delete_post_meta( $post_id, self::META_SCHEDULED_AT );
				delete_post_meta( $post_id, self::META_SCHEDULED_CAPTION );
				delete_post_meta( $post_id, self::META_SCHEDULED_USER );
				delete_post_meta( $post_id, self::META_ATTEMPT );

				if ( function_exists( 'mmm_write_to_log' ) ) {
					mmm_write_to_log(
						sprintf(
							'MixMyMeals Instagram: scheduled publish for post %d failed: %s',
							$post_id,
							$result->get_error_message()
						)
					);
				}

				return;
			}

			self::store_publish_result( $post_id, true, '', (string) $result );
			delete_post_meta( $post_id, self::META_SCHEDULED_AT );
			delete_post_meta( $post_id, self::META_SCHEDULED_CAPTION );
			delete_post_meta( $post_id, self::META_SCHEDULED_USER );
			delete_post_meta( $post_id, self::META_ATTEMPT );
		} finally {
			self::set_acting_user( null );
		}
	}

	/**
	 * Stores the outcome of a scheduled publish for display in the meta box.
	 *
	 * @param int    $post_id   Post ID.
	 * @param bool   $ok        Whether the publish succeeded.
	 * @param string $message   Error message ('' on success).
	 * @param string $permalink Permalink of the published post ('' when unknown).
	 * @return void
	 */
	private static function store_publish_result( int $post_id, bool $ok, string $message, string $permalink ): void {
		update_post_meta(
			$post_id,
			self::META_LAST_RESULT,
			array(
				'ok'        => $ok,
				'time'      => time(),
				'message'   => $message,
				'permalink' => $permalink,
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * REST API (mixmymeals/v1/instagram/…) — lets the MCP server / automation
	 * generate, publish and schedule Instagram posts with an Application
	 * Password, reusing the exact same code paths as the admin UI and cron.
	 * ------------------------------------------------------------------ */

	/**
	 * Registers the Instagram REST routes.
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {

		$ns   = 'mixmymeals/v1';
		$perm = function ( $request ) {
			$id = (int) $request['id'];
			return $id
				&& current_user_can( 'edit_post', $id )
				&& in_array( get_post_type( $id ), array( self::POST_TYPE, self::RECIPE_POST_TYPE ), true );
		};
		$id_arg = array( 'validate_callback' => static fn( $v ) => is_numeric( $v ), 'sanitize_callback' => 'absint' );

		register_rest_route( $ns, '/instagram/(?P<id>\d+)', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'rest_status' ),
			'permission_callback' => $perm,
			'args'                => array( 'id' => $id_arg ),
		) );

		register_rest_route( $ns, '/instagram/(?P<id>\d+)/generate', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'rest_generate' ),
			'permission_callback' => $perm,
			'args'                => array( 'id' => $id_arg ),
		) );

		register_rest_route( $ns, '/instagram/(?P<id>\d+)/publish', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'rest_publish' ),
			'permission_callback' => $perm,
			'args'                => array(
				'id'         => $id_arg,
				'caption'    => array( 'required' => false, 'type' => 'string' ),
				'regenerate' => array( 'required' => false, 'type' => 'boolean', 'default' => true ),
				'budget'     => array( 'required' => false, 'type' => 'integer', 'default' => 120 ),
			),
		) );

		register_rest_route( $ns, '/instagram/(?P<id>\d+)/schedule', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'rest_schedule' ),
			'permission_callback' => $perm,
			'args'                => array(
				'id'      => $id_arg,
				'when'    => array( 'required' => true, 'type' => 'string' ),
				'caption' => array( 'required' => false, 'type' => 'string' ),
			),
		) );

		register_rest_route( $ns, '/instagram/(?P<id>\d+)/unschedule', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'rest_unschedule' ),
			'permission_callback' => $perm,
			'args'                => array( 'id' => $id_arg ),
		) );
	}

	/**
	 * Resolves the default caption for a plan or recipe.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function rest_default_caption( int $post_id ): string {
		if ( self::RECIPE_POST_TYPE === get_post_type( $post_id ) ) {
			return self::recipe_caption( $post_id );
		}
		return self::default_caption( new Mixmymeals_Meal_Plan( $post_id ) );
	}

	/**
	 * GET /instagram/{id} — connection, images, schedule and last result.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function rest_status( WP_REST_Request $request ): WP_REST_Response {

		$id        = (int) $request['id'];
		$token     = self::token_status();
		$scheduled = (int) get_post_meta( $id, self::META_SCHEDULED_AT, true );
		$last      = get_post_meta( $id, self::META_LAST_RESULT, true );

		return new WP_REST_Response( array(
			'id'           => $id,
			'post_type'    => get_post_type( $id ),
			'title'        => get_the_title( $id ),
			'connection'   => array(
				'ok'       => (bool) $token['ok'],
				'username' => $token['username'],
				'error'    => $token['error'],
			),
			'slides'       => count( self::get_jobs_for_post( $id ) ),
			'images'       => self::list_images( $id ),
			'scheduled_at' => $scheduled ? wp_date( 'c', $scheduled ) : null,
			'last_result'  => is_array( $last ) && isset( $last['ok'] ) ? array(
				'ok'        => (bool) $last['ok'],
				'time'      => isset( $last['time'] ) ? wp_date( 'c', (int) $last['time'] ) : null,
				'message'   => (string) ( $last['message'] ?? '' ),
				'permalink' => (string) ( $last['permalink'] ?? '' ),
			) : null,
			'caption'      => $this->rest_default_caption( $id ),
		), 200 );
	}

	/**
	 * POST /instagram/{id}/generate — (re)generates the images.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_generate( WP_REST_Request $request ): WP_REST_Response|WP_Error {

		$id     = (int) $request['id'];
		$result = $this->render_all_images( $id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( array(
			'id'     => $id,
			'images' => self::list_images( $id ),
		), 200 );
	}

	/**
	 * POST /instagram/{id}/publish — publishes now. By default the images are
	 * regenerated first so the post reflects the current data.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_publish( WP_REST_Request $request ): WP_REST_Response|WP_Error {

		$id = (int) $request['id'];

		if ( $request->get_param( 'regenerate' ) ) {
			$rendered = $this->render_all_images( $id );
			if ( is_wp_error( $rendered ) ) {
				return $rendered;
			}
		}

		$caption = $request->has_param( 'caption' )
			? sanitize_textarea_field( (string) $request->get_param( 'caption' ) )
			: $this->rest_default_caption( $id );

		$budget    = max( 30, (int) $request->get_param( 'budget' ) );
		$permalink = $this->publish_now( $id, $caption, $budget );
		if ( is_wp_error( $permalink ) ) {
			return $permalink;
		}

		return new WP_REST_Response( array(
			'id'        => $id,
			'permalink' => (string) $permalink,
		), 200 );
	}

	/**
	 * POST /instagram/{id}/schedule — schedules a publish. Images are
	 * regenerated automatically at the scheduled time.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_schedule( WP_REST_Request $request ): WP_REST_Response|WP_Error {

		$id   = (int) $request['id'];
		$when = (string) $request->get_param( 'when' );
		$dt   = $when ? date_create_immutable( $when, wp_timezone() ) : false;

		if ( ! $dt ) {
			return new WP_Error( 'mmm_ig_when', __( 'Please pick a valid date and time.', 'mixmymeals' ), array( 'status' => 400 ) );
		}

		$timestamp = $dt->getTimestamp();
		if ( $timestamp < time() + MINUTE_IN_SECONDS ) {
			return new WP_Error( 'mmm_ig_when', __( 'The scheduled time must be in the future.', 'mixmymeals' ), array( 'status' => 400 ) );
		}

		$caption = $request->has_param( 'caption' )
			? sanitize_textarea_field( (string) $request->get_param( 'caption' ) )
			: $this->rest_default_caption( $id );

		self::unschedule_publish( $id );

		update_post_meta( $id, self::META_SCHEDULED_AT, $timestamp );
		update_post_meta( $id, self::META_SCHEDULED_CAPTION, mb_substr( $caption, 0, self::CAPTION_MAX ) );
		update_post_meta( $id, self::META_SCHEDULED_USER, get_current_user_id() );
		update_post_meta( $id, self::META_ATTEMPT, 0 );

		wp_schedule_single_event( $timestamp, self::PUBLISH_HOOK, array( $id ) );

		return new WP_REST_Response( array(
			'id'           => $id,
			'scheduled_at' => wp_date( 'c', $timestamp ),
		), 200 );
	}

	/**
	 * POST /instagram/{id}/unschedule — cancels a pending scheduled publish.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function rest_unschedule( WP_REST_Request $request ): WP_REST_Response {

		$id = (int) $request['id'];

		self::unschedule_publish( $id );
		delete_post_meta( $id, self::META_SCHEDULED_AT );
		delete_post_meta( $id, self::META_SCHEDULED_CAPTION );
		delete_post_meta( $id, self::META_SCHEDULED_USER );
		delete_post_meta( $id, self::META_ATTEMPT );

		return new WP_REST_Response( array(
			'id'           => $id,
			'scheduled_at' => null,
		), 200 );
	}

	/* ---------------------------------------------------------------------
	 * Scheduled-posts overview page
	 * ------------------------------------------------------------------ */

	/**
	 * IDs of posts (plans and recipes) that have a pending scheduled publish,
	 * soonest first.
	 *
	 * @return int[]
	 */
	private static function query_scheduled_ids( string $meta_key = self::META_SCHEDULED_AT ): array {
		return get_posts(
			array(
				'post_type'      => array( self::POST_TYPE, self::RECIPE_POST_TYPE ),
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_key'       => $meta_key,
				'orderby'        => 'meta_value_num',
				'order'          => 'ASC',
			)
		);
	}

	/**
	 * All scheduled social posts (Instagram + Pinterest) as sortable rows.
	 *
	 * @return array[] Rows with channel, post_id, ts, text, user_id, attempt.
	 */
	private static function scheduled_rows(): array {

		$rows = array();

		foreach ( self::query_scheduled_ids() as $post_id ) {
			$rows[] = array(
				'channel' => 'instagram',
				'post_id' => (int) $post_id,
				'ts'      => (int) get_post_meta( $post_id, self::META_SCHEDULED_AT, true ),
				'text'    => (string) get_post_meta( $post_id, self::META_SCHEDULED_CAPTION, true ),
				'user_id' => (int) get_post_meta( $post_id, self::META_SCHEDULED_USER, true ),
				'attempt' => (int) get_post_meta( $post_id, self::META_ATTEMPT, true ),
			);
		}

		if ( class_exists( 'Mixmymeals_Pinterest' ) ) {
			foreach ( self::query_scheduled_ids( Mixmymeals_Pinterest::META_SCHEDULED_AT ) as $post_id ) {
				$rows[] = array(
					'channel' => 'pinterest',
					'post_id' => (int) $post_id,
					'ts'      => (int) get_post_meta( $post_id, Mixmymeals_Pinterest::META_SCHEDULED_AT, true ),
					'text'    => (string) get_post_meta( $post_id, Mixmymeals_Pinterest::META_SCHEDULED_TITLE, true ),
					'user_id' => (int) get_post_meta( $post_id, Mixmymeals_Pinterest::META_SCHEDULED_USER, true ),
					'attempt' => 0,
				);
			}
		}

		usort( $rows, static fn( array $a, array $b ): int => $a['ts'] <=> $b['ts'] );

		return $rows;
	}

	/**
	 * Registers the "Scheduled Instagram Posts" overview under Meal Plans.
	 *
	 * @return void
	 */
	public function add_admin_page(): void {

		$count = count( self::scheduled_rows() );
		$label = __( 'Scheduled Posts', 'mixmymeals' );

		if ( $count > 0 ) {
			$label .= sprintf(
				' <span class="awaiting-mod count-%1$d"><span class="pending-count">%1$d</span></span>',
				$count
			);
		}

		add_submenu_page(
			'edit.php?post_type=' . self::POST_TYPE,
			__( 'Scheduled Social Posts', 'mixmymeals' ),
			$label,
			'edit_others_posts',
			self::ADMIN_SLUG,
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * URL of the overview page.
	 *
	 * @return string
	 */
	private static function admin_page_url(): string {
		return admin_url( 'edit.php?post_type=' . self::POST_TYPE . '&page=' . self::ADMIN_SLUG );
	}

	/**
	 * admin-post handler: cancels one scheduled publish from the overview.
	 *
	 * @return void
	 */
	public function handle_cancel_action(): void {

		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mixmymeals' ) );
		}

		$channel = isset( $_GET['channel'] ) && 'pinterest' === $_GET['channel'] ? 'pinterest' : 'instagram';

		check_admin_referer( 'mmm_ig_cancel_' . $channel . '_' . $post_id );

		if ( in_array( get_post_type( $post_id ), array( self::POST_TYPE, self::RECIPE_POST_TYPE ), true ) ) {
			if ( 'pinterest' === $channel && class_exists( 'Mixmymeals_Pinterest' ) ) {
				Mixmymeals_Pinterest::unschedule_publish( $post_id );
				delete_post_meta( $post_id, Mixmymeals_Pinterest::META_SCHEDULED_AT );
				delete_post_meta( $post_id, Mixmymeals_Pinterest::META_SCHEDULED_TITLE );
				delete_post_meta( $post_id, Mixmymeals_Pinterest::META_SCHEDULED_DESC );
				delete_post_meta( $post_id, Mixmymeals_Pinterest::META_SCHEDULED_USER );
			} else {
				self::unschedule_publish( $post_id );
				delete_post_meta( $post_id, self::META_SCHEDULED_AT );
				delete_post_meta( $post_id, self::META_SCHEDULED_CAPTION );
				delete_post_meta( $post_id, self::META_SCHEDULED_USER );
				delete_post_meta( $post_id, self::META_ATTEMPT );
			}
		}

		wp_safe_redirect( add_query_arg( 'canceled', '1', self::admin_page_url() ) );
		exit;
	}

	/**
	 * Renders the scheduled-posts overview: upcoming schedules and the most
	 * recent publish results.
	 *
	 * @return void
	 */
	public function render_admin_page(): void {

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Scheduled Social Posts', 'mixmymeals' ) . '</h1>';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['canceled'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>'
				. esc_html__( 'Schedule cancelled.', 'mixmymeals' ) . '</p></div>';
		}

		$rows = self::scheduled_rows();

		echo '<h2>' . esc_html__( 'Upcoming', 'mixmymeals' ) . '</h2>';

		if ( ! $rows ) {
			echo '<p>' . esc_html__( 'No posts are scheduled. Open a meal plan or recipe and use the posting sections there.', 'mixmymeals' ) . '</p>';
		} else {
			echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
			printf( '<th>%s</th>', esc_html__( 'Channel', 'mixmymeals' ) );
			printf( '<th>%s</th>', esc_html__( 'Post', 'mixmymeals' ) );
			printf( '<th>%s</th>', esc_html__( 'Type', 'mixmymeals' ) );
			printf( '<th>%s</th>', esc_html__( 'User', 'mixmymeals' ) );
			printf( '<th>%s</th>', esc_html__( 'Scheduled time', 'mixmymeals' ) );
			printf( '<th>%s</th>', esc_html__( 'Caption', 'mixmymeals' ) );
			printf( '<th>%s</th>', esc_html__( 'Actions', 'mixmymeals' ) );
			echo '</tr></thead><tbody>';

			foreach ( $rows as $row ) {
				$post_id   = $row['post_id'];
				$is_recipe = get_post_type( $post_id ) === self::RECIPE_POST_TYPE;

				$scheduler = $row['user_id'] > 0 ? get_userdata( $row['user_id'] ) : null;
				$user_name = $scheduler ? $scheduler->display_name : __( 'Unknown', 'mixmymeals' );

				$cancel_url = wp_nonce_url(
					admin_url( 'admin-post.php?action=mmm_ig_cancel_schedule&post=' . $post_id . '&channel=' . $row['channel'] ),
					'mmm_ig_cancel_' . $row['channel'] . '_' . $post_id
				);

				echo '<tr>';
				printf( '<td>%s</td>', 'pinterest' === $row['channel'] ? 'Pinterest' : 'Instagram' );
				printf(
					'<td><a href="%s"><strong>%s</strong></a></td>',
					esc_url( (string) get_edit_post_link( $post_id ) ),
					esc_html( get_the_title( $post_id ) )
				);
				printf(
					'<td>%s</td>',
					esc_html( $is_recipe ? __( 'Recipe', 'mixmymeals' ) : __( 'Meal plan', 'mixmymeals' ) )
				);
				printf( '<td>%s</td>', esc_html( $user_name ) );
				printf(
					'<td>%s%s</td>',
					esc_html( wp_date( get_option( 'date_format' ) . ' H:i', $row['ts'] ) ),
					$row['attempt'] > 0
						? '<br><span style="color:#b26200;">' . esc_html(
							sprintf(
								/* translators: %d: number of retry attempts so far. */
								__( 'retrying (attempt %d)', 'mixmymeals' ),
								$row['attempt']
							)
						) . '</span>'
						: ''
				);
				printf(
					'<td><span title="%s">%s</span></td>',
					esc_attr( $row['text'] ),
					esc_html( mb_strimwidth( str_replace( "\n", ' ', $row['text'] ), 0, 90, '…' ) )
				);
				printf(
					'<td><a href="%s">%s</a> · <a href="%s" onclick="return confirm(\'%s\');">%s</a></td>',
					esc_url( (string) get_edit_post_link( $post_id ) ),
					esc_html__( 'Edit', 'mixmymeals' ),
					esc_url( $cancel_url ),
					esc_js( __( 'Cancel this scheduled post?', 'mixmymeals' ) ),
					esc_html__( 'Cancel', 'mixmymeals' )
				);
				echo '</tr>';
			}

			echo '</tbody></table>';
		}

		$this->render_recent_results();

		echo '</div>';
	}

	/**
	 * Renders the "recent results" table below the upcoming schedules.
	 *
	 * @return void
	 */
	private function render_recent_results(): void {

		$posts = get_posts(
			array(
				'post_type'      => array( self::POST_TYPE, self::RECIPE_POST_TYPE ),
				'post_status'    => 'any',
				'posts_per_page' => 30,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_key'       => self::META_LAST_RESULT,
			)
		);

		$rows = array();
		foreach ( $posts as $post_id ) {
			$result = get_post_meta( $post_id, self::META_LAST_RESULT, true );
			if ( is_array( $result ) && isset( $result['time'] ) ) {
				$rows[] = array( 'id' => $post_id, 'result' => $result );
			}
		}

		if ( ! $rows ) {
			return;
		}

		usort(
			$rows,
			static fn( array $a, array $b ): int => (int) $b['result']['time'] <=> (int) $a['result']['time']
		);

		echo '<h2 style="margin-top:32px;">' . esc_html__( 'Recent results', 'mixmymeals' ) . '</h2>';
		echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
		printf( '<th>%s</th>', esc_html__( 'Post', 'mixmymeals' ) );
		printf( '<th>%s</th>', esc_html__( 'When', 'mixmymeals' ) );
		printf( '<th>%s</th>', esc_html__( 'Result', 'mixmymeals' ) );
		echo '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			$result = $row['result'];

			if ( $result['ok'] ) {
				$status = '<span style="color:#008a20;">' . esc_html__( 'Published', 'mixmymeals' ) . '</span>';
				if ( '' !== (string) $result['permalink'] ) {
					$status .= ' · <a href="' . esc_url( $result['permalink'] ) . '" target="_blank" rel="noopener">'
						. esc_html__( 'View post', 'mixmymeals' ) . '</a>';
				}
			} else {
				$status = '<span style="color:#d63638;">' . esc_html__( 'Failed', 'mixmymeals' ) . '</span> · '
					. esc_html( (string) $result['message'] );
			}

			echo '<tr>';
			printf(
				'<td><a href="%s">%s</a></td>',
				esc_url( (string) get_edit_post_link( $row['id'] ) ),
				esc_html( get_the_title( $row['id'] ) )
			);
			printf( '<td>%s</td>', esc_html( wp_date( get_option( 'date_format' ) . ' H:i', (int) $result['time'] ) ) );
			echo '<td>' . wp_kses_post( $status ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * AJAX step 2: waits for the containers, creates the carousel container
	 * and publishes it. When Instagram is still processing, the response
	 * carries retry=true (plus any carousel_id already created) and the
	 * client calls this step again after a short delay.
	 *
	 * @return void
	 */
	public function ajax_post_publish(): void {

		$plan_id = $this->ajax_post_guard();

		$caption = isset( $_POST['caption'] )
			? mb_substr( sanitize_textarea_field( wp_unslash( $_POST['caption'] ) ), 0, self::CAPTION_MAX )
			: '';

		$children = isset( $_POST['children'] ) && is_array( $_POST['children'] )
			? array_filter( array_map( 'sanitize_text_field', wp_unslash( $_POST['children'] ) ) )
			: array();

		$carousel_id = isset( $_POST['carousel_id'] )
			? sanitize_text_field( wp_unslash( $_POST['carousel_id'] ) )
			: '';

		if ( ! $children && '' === $carousel_id ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'mixmymeals' ) ), 400 );
		}

		$user_id  = self::get_ig_user_id();
		$deadline = time() + 22;

		// 1. Wait for the per-image containers, then create the carousel.
		if ( '' === $carousel_id ) {

			foreach ( $children as $child ) {
				$done = self::wait_for_container( $child, $deadline );

				if ( is_wp_error( $done ) ) {
					if ( 'mmm_ig_pending' === $done->get_error_code() ) {
						wp_send_json_success(
							array(
								'retry'       => true,
								'carousel_id' => '',
							)
						);
					}
					wp_send_json_error( array( 'message' => $done->get_error_message() ), 500 );
				}
			}

			if ( count( $children ) > 1 ) {
				$carousel = self::api_post(
					'/' . rawurlencode( $user_id ) . '/media',
					array(
						'media_type' => 'CAROUSEL',
						'children'   => implode( ',', $children ),
						'caption'    => $caption,
					)
				);

				if ( is_wp_error( $carousel ) || empty( $carousel['id'] ) ) {
					wp_send_json_error(
						array(
							'message' => is_wp_error( $carousel )
								? $carousel->get_error_message()
								: __( 'Unexpected response from the Instagram API.', 'mixmymeals' ),
						),
						500
					);
				}

				$carousel_id = (string) $carousel['id'];
			} else {
				// Single image: the item container is published directly.
				$carousel_id = $children[0];
			}
		}

		// 2. Wait for the container to finish, then publish.
		$done = self::wait_for_container( $carousel_id, $deadline );

		if ( is_wp_error( $done ) ) {
			if ( 'mmm_ig_pending' === $done->get_error_code() ) {
				wp_send_json_success(
					array(
						'retry'       => true,
						'carousel_id' => $carousel_id,
					)
				);
			}
			wp_send_json_error( array( 'message' => $done->get_error_message() ), 500 );
		}

		$published = self::api_post(
			'/' . rawurlencode( $user_id ) . '/media_publish',
			array( 'creation_id' => $carousel_id )
		);

		if ( is_wp_error( $published ) || empty( $published['id'] ) ) {

			// "Media not ready" is retryable, everything else is fatal.
			$error_data = is_wp_error( $published ) ? (array) $published->get_error_data() : array();
			if ( isset( $error_data['error_subcode'] ) && 2207027 === (int) $error_data['error_subcode'] ) {
				wp_send_json_success(
					array(
						'retry'       => true,
						'carousel_id' => $carousel_id,
					)
				);
			}

			wp_send_json_error(
				array(
					'message' => is_wp_error( $published )
						? $published->get_error_message()
						: __( 'Unexpected response from the Instagram API.', 'mixmymeals' ),
				),
				500
			);
		}

		// Permalink is nice to have — ignore failures.
		$permalink = '';
		$media     = self::api_get( '/' . rawurlencode( (string) $published['id'] ), array( 'fields' => 'permalink' ) );
		if ( ! is_wp_error( $media ) && ! empty( $media['permalink'] ) ) {
			$permalink = (string) $media['permalink'];
		}

		wp_send_json_success(
			array(
				'retry'     => false,
				'permalink' => $permalink,
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Data assembly
	 * ------------------------------------------------------------------ */

	/**
	 * Decodes HTML entities that WordPress puts into titles (&#8217; etc.).
	 * GD happens to decode numeric entities itself, but captions sent to
	 * Instagram would show them literally — so all titles pass through here.
	 *
	 * @param string $text Text with possible entities.
	 * @return string
	 */
	private static function clean_text( string $text ): string {
		return html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/**
	 * Localized weekday name for a plan day number.
	 *
	 * @param int  $day    Day number, 1 = Monday … 7 = Sunday.
	 * @param bool $abbrev Return the abbreviation (e.g. "Mo") instead.
	 * @return string
	 */
	private static function weekday_name( int $day, bool $abbrev = false ): string {
		global $wp_locale;

		if ( ! $wp_locale instanceof WP_Locale || $day < 1 || $day > 7 ) {
			return '';
		}

		$name = (string) $wp_locale->get_weekday( $day % 7 ); // get_weekday: 0=Sunday.

		return $abbrev ? (string) $wp_locale->get_weekday_abbrev( $name ) : $name;
	}

	/**
	 * Collects the meals of a plan grouped by day and filtered/ordered by
	 * the allowed meal types.
	 *
	 * @param Mixmymeals_Meal_Plan $plan Plan object.
	 * @return array Day number => list of arrays with 'type', 'type_label',
	 *               'title', 'recipe_id'.
	 */
	private static function meals_by_day( Mixmymeals_Meal_Plan $plan ): array {

		$types      = self::allowed_meal_types();
		$type_map   = Mixmymeals_Meal::get_plaintext_meal_type_map();
		$by_day     = array_fill_keys( $plan->get_day_range(), array() );

		foreach ( $plan->get_meals() as $meal_post ) {
			$meal = new Mixmymeals_Meal( $meal_post->ID );
			$day  = (int) $meal->get_day();
			$type = $meal->get_type();

			if ( ! isset( $by_day[ $day ] ) || ! in_array( $type, $types, true ) ) {
				continue;
			}

			$recipe_id = $meal->get_recipe_id();

			$by_day[ $day ][] = array(
				'type'       => $type,
				'type_label' => (string) ( $type_map[ $type ] ?? $type ),
				'title'      => self::clean_text( $recipe_id ? get_the_title( $recipe_id ) : get_the_title( $meal_post ) ),
				'recipe_id'  => $recipe_id,
				'servings'   => max( 1, (int) $meal->get_servings() ),
			);
		}

		// Order each day's meals by the canonical type order.
		$order = array_flip( $types );
		foreach ( $by_day as &$meals ) {
			usort(
				$meals,
				static fn( array $a, array $b ): int => ( $order[ $a['type'] ] ?? 99 ) <=> ( $order[ $b['type'] ] ?? 99 )
			);
		}
		unset( $meals );

		return $by_day;
	}

	/**
	 * Picks the hero meal of a day: the first meal of the preferred type,
	 * falling back to the first meal at all. Title and photo of the day
	 * slide are both derived from this meal so they always match.
	 *
	 * @param array $meals Meals of the day (see meals_by_day()).
	 * @return array|null
	 */
	private static function pick_hero_meal( array $meals ): ?array {

		$hero_type = (string) self::opt( 'hero_meal_type', 'dinner' );

		foreach ( $meals as $meal ) {
			if ( $meal['type'] === $hero_type ) {
				return $meal;
			}
		}

		return $meals ? $meals[0] : null;
	}

	/**
	 * Picks the hero image attachment for one day: the hero meal's recipe
	 * photo, then any other recipe photo of that day, then the global
	 * placeholder image.
	 *
	 * @param array      $meals     Meals of the day (see meals_by_day()).
	 * @param array|null $hero_meal The day's hero meal (see pick_hero_meal()).
	 * @return int Attachment ID or 0.
	 */
	private static function pick_hero_attachment( array $meals, ?array $hero_meal ): int {

		$candidates = array();
		if ( null !== $hero_meal ) {
			$candidates[] = $hero_meal;
		}
		foreach ( $meals as $meal ) {
			if ( $meal !== $hero_meal ) {
				$candidates[] = $meal;
			}
		}

		foreach ( $candidates as $meal ) {
			if ( $meal['recipe_id'] ) {
				$thumb = (int) get_post_thumbnail_id( $meal['recipe_id'] );
				if ( $thumb ) {
					return $thumb;
				}
			}
		}

		return absint( get_option( 'mixmymeals_placeholder_image_id', 0 ) );
	}

	/* ---------------------------------------------------------------------
	 * Rendering: slides
	 * ------------------------------------------------------------------ */

	/**
	 * Renders the overview slide for a plan.
	 *
	 * @param int $plan_id Meal plan post ID.
	 * @return string|WP_Error Absolute path of the JPEG, or error.
	 */
	public function render_overview( int $plan_id ): string|WP_Error {

		$check = $this->pre_render_check( $plan_id );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$plan = new Mixmymeals_Meal_Plan( $plan_id );

		list( $w, $h ) = self::canvas_size();
		$fs            = self::font_scale();
		$radius        = (int) self::opt( 'corner_radius', 24 );

		$bg      = (string) self::c_bg();
		$primary = (string) self::c_primary();
		$accent  = (string) self::c_accent();
		$ink     = self::contrast_ink( $bg );
		$ink2    = self::contrast_ink( $bg, true );
		$tint    = self::mix( $primary, '#FFFFFF', 0.78 ); // chip background
		$deep    = self::mix( $primary, '#000000', 0.35 ); // chip text

		$title_font = self::get_font_path( 'title' );
		$text_font  = self::get_font_path( 'text' );

		$im = $this->canvas( $w, $h, $bg );

		$x0 = 72;
		$x1 = $w - 72;
		$y  = 64;

		// Header: wordmark / logo left, kind pill right.
		$header_bottom = $y;

		$logo_id = absint( self::opt( 'logo_id', 0 ) );
		if ( $logo_id ) {
			$logo_h = (int) round( 52 * $fs * self::logo_scale() );
			$this->draw_logo( $im, $logo_id, $x0, $y, $logo_h );
			$header_bottom = max( $header_bottom, $y + $logo_h );
		} else {
			$size = 46 * $fs * self::logo_scale();
			$this->draw_wordmark( $im, $x0, $y, $size, $ink, $accent );
			$header_bottom = max( $header_bottom, $y + (int) round( $size * 1.25 ) );
		}

		// Slide counter at the far right; the kind pill moves left of it so the
		// carousel numbering stays visible on every slide.
		$counter   = self::counter_text( $plan, 'overview' );
		$counter_w = 0;
		if ( '' !== $counter ) {
			$c_size    = 30 * $fs;
			$counter_w = $this->text_width( $counter, $c_size, $title_font, 1 ) + (int) round( 52 * $fs );
			$this->draw_counter_at( $im, $x1, $y, $counter, false );
			$header_bottom = max( $header_bottom, $y + (int) round( $c_size * 2.0 ) );
		}

		if ( self::is_on( 'show_kind_pill' ) ) {
			$pill_text  = mb_strtoupper( __( 'Weekly plan', 'mixmymeals' ) );
			$pill_size  = 27 * $fs;
			$pill_w     = $this->text_width( $pill_text, $pill_size, $title_font, 3 ) + 60;
			$pill_h     = (int) round( $pill_size * 2.1 );
			$pill_right = $x1 - ( $counter_w > 0 ? $counter_w + (int) round( 16 * $fs ) : 0 );
			$this->pill( $im, $pill_right - $pill_w, $y, $pill_w, $pill_h, $pill_h / 2, $tint );
			$this->text_centered( $im, $pill_text, $pill_size, $pill_right - $pill_w, $y, $pill_w, $pill_h, $deep, $title_font, 3 );
			$header_bottom = max( $header_bottom, $y + $pill_h );
		}

		$y = $header_bottom + (int) round( 40 * ( $h / 1350 ) );

		// Plan title, up to two lines.
		$title_size = ( '1x1' === self::opt( 'format', '4x5' ) ? 64 : 76 ) * $fs;
		$lines      = $this->wrap_text( self::clean_text( get_the_title( $plan_id ) ), $title_size, $title_font, $x1 - $x0, 2 );
		foreach ( $lines as $line ) {
			$this->text( $im, $line, $title_size, $x0, $y, $ink, $title_font );
			$y += (int) round( $title_size * 1.14 );
		}
		$y += (int) round( 6 * $fs );

		// Meta line.
		if ( self::is_on( 'show_meta' ) ) {
			$meta = $this->overview_meta_line( $plan );
			$this->text( $im, $meta, 34 * $fs, $x0, $y, $ink2, $text_font );
			$y += (int) round( 34 * $fs * 1.5 );
		}

		$y += 18;

		// Footer bar (reserves space at the bottom).
		$bottom = $h - 64;
		if ( self::is_on( 'show_footer_bar' ) ) {
			$bar_h = (int) round( 94 * $fs );
			$this->pill( $im, $x0, $h - 64 - $bar_h, $x1 - $x0, $bar_h, min( $radius, $bar_h / 2 ), $primary );
			$this->text_centered(
				$im,
				(string) self::opt( 'footer_text', $this->default_footer_text() ),
				33 * $fs,
				$x0,
				$h - 64 - $bar_h,
				$x1 - $x0,
				$bar_h,
				'#FFFFFF',
				$title_font
			);
			$bottom = $h - 64 - $bar_h - 34;
		}

		// Day rows fill the remaining space evenly. Every meal of every day is
		// listed; the line height is sized so even the busiest day's recipes
		// fit into one row, so no recipe is ever dropped.
		$days   = $plan->get_day_range();
		$by_day = self::meals_by_day( $plan );
		$n      = max( 1, count( $days ) );
		$gap    = 14;
		$row_h  = (int) floor( ( $bottom - $y - ( $n - 1 ) * $gap ) / $n );

		$max_meals = 1;
		foreach ( $by_day as $day_meals ) {
			$max_meals = max( $max_meals, count( $day_meals ) );
		}

		$meal_line_h = min( (int) floor( ( $row_h - 8 ) / $max_meals ), (int) round( 33 * $fs * 1.42 ) );
		$meal_size   = min( 33 * $fs, $meal_line_h / 1.42 );
		$chip        = min( (int) round( 92 * $fs ), $row_h - 4 );
		$col_x       = $x0 + $chip + 28;
		$max_w       = $x1 - $col_x;

		foreach ( $days as $day ) {
			$meals = $by_day[ $day ] ?? array();

			// Weekday badge vertically centered in the row, aligned with the
			// meal block (so short/one-day plans don't leave it floating high).
			$chip_y = $y + (int) round( ( $row_h - $chip ) / 2 );
			$this->pill( $im, $x0, $chip_y, $chip, $chip, min( $radius, $chip / 2 ), $tint );
			$this->text_centered( $im, self::weekday_name( $day, true ), $chip * 0.42, $x0, $chip_y, $chip, $chip, $deep, $title_font );

			// Shared label column so titles align, and the meal-time label is
			// printed only once per run of identical labels (no repeated
			// "Dinner" when a day has two dinners).
			$label_col_w = 0;
			foreach ( $meals as $meal ) {
				$label_col_w = max( $label_col_w, $this->text_width( $meal['type_label'], $meal_size, $title_font ) );
			}
			$label_col_w += (int) round( 18 * $fs );
			$title_x      = $col_x + $label_col_w;

			$text_y     = $y + (int) round( ( $row_h - count( $meals ) * $meal_line_h ) / 2 );
			$prev_label = null;
			foreach ( $meals as $meal ) {
				if ( $meal['type_label'] !== $prev_label ) {
					$this->text( $im, $meal['type_label'], $meal_size, $col_x, $text_y, $accent, $title_font );
				}
				$this->text(
					$im,
					$this->truncate( $meal['title'], $meal_size, $text_font, $x1 - $title_x ),
					$meal_size,
					$title_x,
					$text_y,
					$ink,
					$text_font
				);
				$prev_label = $meal['type_label'];
				$text_y    += $meal_line_h;
			}

			$y += $row_h + $gap;
		}

		return $this->save( $im, $plan_id, 'uebersicht.jpg' );
	}

	/**
	 * Renders one day slide, dispatching to the configured layout.
	 *
	 * @param int $plan_id Meal plan post ID.
	 * @param int $day     Day number, 1–7.
	 * @return string|WP_Error Absolute path of the JPEG, or error.
	 */
	public function render_day( int $plan_id, int $day, int $index = 0 ): string|WP_Error {

		$layout = (string) self::opt( 'day_layout', 'full' );

		if ( in_array( $layout, array( 'magazine', 'split', 'card' ), true ) ) {
			return $this->render_day_framed( $plan_id, $day, $layout, $index );
		}

		return $this->render_day_fullbleed( $plan_id, $day, $index );
	}

	/**
	 * Renders a day slide in the full-bleed layout: photo edge to edge, a
	 * dark scrim and the text anchored to the bottom.
	 *
	 * @param int $plan_id Meal plan post ID.
	 * @param int $day     Day number, 1–7.
	 * @return string|WP_Error Absolute path of the JPEG, or error.
	 */
	public function render_day_fullbleed( int $plan_id, int $day, int $index = 0 ): string|WP_Error {

		$check = $this->pre_render_check( $plan_id );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$plan = new Mixmymeals_Meal_Plan( $plan_id );
		$days = $plan->get_day_range();

		$position = array_search( $day, $days, true );
		if ( false === $position ) {
			return new WP_Error(
				'mmm_instagram_day',
				__( 'This day is not part of the plan.', 'mixmymeals' )
			);
		}

		$by_day = self::meals_by_day( $plan );
		$meals  = $by_day[ $day ] ?? array();

		// This slide shows a single recipe of the day (the one at $index); a day
		// with several recipes produces one slide per recipe.
		if ( isset( $meals[ $index ] ) ) {
			$meals = array( $meals[ $index ] );
		}

		list( $w, $h ) = self::canvas_size();
		$fs            = self::font_scale();

		$accent = (string) self::c_accent();
		$accent_light = self::mix( $accent, '#FFFFFF', 0.35 );

		$title_font = self::get_font_path( 'title' );
		$text_font  = self::get_font_path( 'text' );

		$hero_meal = self::pick_hero_meal( $meals );

		// Background: hero photo or dark primary fallback.
		$im      = $this->canvas( $w, $h, self::mix( (string) self::c_primary(), '#000000', 0.55 ) );
		$hero_id = self::pick_hero_attachment( $meals, $hero_meal );

		if ( $hero_id ) {
			$this->draw_cover( $im, $hero_id, $w, $h );
		}

		// Bottom overlay for text legibility. The style controls how the text
		// area is darkened: a gradient (default), a lighter gradient, or a
		// contained plate drawn later behind the text block.
		$overlay  = (string) self::opt( 'overlay_style', 'gradient' );
		$strength = max( 0, min( 100, (int) self::opt( 'scrim_opacity', 60 ) ) ) / 100;
		if ( 'gradient' === $overlay ) {
			$this->scrim( $im, (int) round( $h * 0.38 ), $h, $strength );
		} elseif ( 'minimal' === $overlay ) {
			$this->scrim( $im, (int) round( $h * 0.55 ), $h, $strength * 0.6 );
		}

		// Day pill top left.
		if ( self::is_on( 'show_day_pill' ) ) {
			// En dash instead of a middle dot: the dot glyph is missing in
			// several display fonts and would render as a tofu box.
			$pill_text = mb_strtoupper(
				sprintf(
					/* translators: %d: position of the day within the plan. */
					__( 'Day %d', 'mixmymeals' ),
					(int) $position + 1
				) . ' – ' . self::weekday_name( $day )
			);
			$pill_size = 30 * $fs;
			$pill_w    = $this->text_width( $pill_text, $pill_size, $title_font, 2 ) + 68;
			$pill_h    = (int) round( $pill_size * 2.2 );
			$this->pill( $im, 64, 56, $pill_w, $pill_h, $pill_h / 2, '#FBF8F1', 0.95 );
			$this->text_centered( $im, $pill_text, $pill_size, 64, 56, $pill_w, $pill_h, '#2C2A24', $title_font, 2 );
		}

		// Slide counter top right.
		$this->draw_counter( $im, $w, self::counter_text( $plan, 'day-' . $day . '-' . $index ), true );

		// Content block, anchored to the bottom.
		$x0 = 72;
		$x1 = $w - 72;

		$title_text = $hero_meal
			? $hero_meal['title']
			: self::clean_text( get_the_title( $plan_id ) );

		$title_size  = ( '1x1' === self::opt( 'format', '4x5' ) ? 72 : 84 ) * $fs;
		$title_lines = $this->wrap_text( $title_text, $title_size, $title_font, $x1 - $x0, 2 );
		if ( count( $title_lines ) > 1 ) {
			// Long titles: one size down for breathing room.
			$title_size  = $title_size * 0.88;
			$title_lines = $this->wrap_text( $title_text, $title_size, $title_font, $x1 - $x0, 2 );
		}

		// Measure the block bottom-up.
		$line_h   = (int) round( $title_size * 1.1 );
		$block_h  = 0;
		$has_type = null !== $hero_meal;

		// Every further recipe of the day, each on its own line ("MEALTIME —
		// Name"), so days/meal-times with several recipes name them all.
		$also_lines = array();
		if ( self::is_on( 'show_also' ) && count( $meals ) > 1 ) {
			foreach ( $meals as $meal ) {
				if ( $meal === $hero_meal ) {
					continue;
				}
				$also_lines[] = $this->truncate(
					mb_strtoupper( (string) $meal['type_label'] ) . ' — ' . $meal['title'],
					32 * $fs,
					$text_font,
					$x1 - $x0
				);
			}
		}
		$also_line_h = (int) round( 32 * $fs * 1.42 );

		$badges    = self::recipe_badges( $hero_meal['recipe_id'] ?? 0 );
		$badge_h   = $badges ? (int) round( 106 * $fs ) : 0;
		$badge_gap = $badges ? (int) round( 20 * $fs ) : 0;

		$footer_h = self::is_on( 'show_brand_footer' ) ? (int) round( 96 * $fs ) : 0;
		$also_h   = $also_lines ? count( $also_lines ) * $also_line_h + (int) round( 8 * $fs ) : 0;
		$type_h   = $has_type ? (int) round( 30 * $fs * 1.7 ) : 0;
		$block_h  = $type_h + count( $title_lines ) * $line_h + $badge_gap + $badge_h + $also_h + $footer_h;

		$y = $h - 60 - $block_h;

		// "Plate" overlay: a contained dark panel behind the text block instead
		// of a full-width gradient.
		if ( 'plate' === $overlay ) {
			$pad = (int) round( 40 * $fs );
			$this->pill(
				$im,
				$x0 - $pad,
				$y - $pad,
				( $x1 - $x0 ) + 2 * $pad,
				( $h - 40 ) - ( $y - $pad ),
				(int) round( 28 * $fs ),
				'#18140E',
				0.58
			);
		}

		if ( $has_type ) {
			$this->text( $im, mb_strtoupper( $hero_meal['type_label'] ), 30 * $fs, $x0, $y, $accent_light, $title_font, 5 );
			$y += $type_h;
		}

		foreach ( $title_lines as $line ) {
			$this->text( $im, $line, $title_size, $x0, $y, '#FFFFFF', $title_font );
			$y += $line_h;
		}

		if ( $badges ) {
			$y   += $badge_gap;
			$bx   = $x0;
			$vsize = 42 * $fs;
			$lsize = 22 * $fs;
			foreach ( $badges as $badge ) {
				$vw = $this->text_width( $badge['value'], $vsize, $title_font );
				$lw = $this->text_width( $badge['label'], $lsize, $text_font );
				$bw = max( $vw, $lw ) + (int) round( 48 * $fs );
				$this->badge( $im, $bx, $y, $bw, $badge_h, $badge['value'], $badge['label'], $fs );
				$bx += $bw + (int) round( 16 * $fs );
			}
			$this->draw_diet_pill( $im, $hero_meal['recipe_id'] ?? 0, $bx, $y, $badge_h, $fs );
			$y += $badge_h;
		}

		if ( $also_lines ) {
			$y += (int) round( 8 * $fs );
			foreach ( $also_lines as $also_line ) {
				$this->text( $im, $also_line, 32 * $fs, $x0, $y, '#FFFFFF', $text_font, 0, 0.9 );
				$y += $also_line_h;
			}
		}

		if ( $footer_h ) {
			$y += (int) round( 26 * $fs );
			$this->hline( $im, $x0, $x1, $y, '#FFFFFF', 0.28 );
			$y += (int) round( 24 * $fs );

			$wm_size = 36 * $fs;
			$this->draw_wordmark( $im, $x0, $y, $wm_size, '#FFFFFF', $accent_light );

			// Prefer the Instagram handle; fall back to the site host.
			$right = $this->get_handle();
			if ( '' === $right ) {
				$right = $this->site_host();
			}
			$url_size = 30 * $fs;
			$url_w    = $this->text_width( $right, $url_size, $text_font );
			$this->text( $im, $right, $url_size, $x1 - $url_w, $y + (int) round( ( $wm_size - $url_size ) * 0.8 ), '#FFFFFF', $text_font, 0, 0.8 );
		}

		return $this->save( $im, $plan_id, 'tag-' . $day . '-' . $index . '.jpg' );
	}

	/**
	 * Renders a day slide in a framed layout (photo in a region, text on a
	 * light ground below): "magazine" (photo top 60%), "split" (50/50) or
	 * "card" (photo in a rounded card on a brand-tinted ground).
	 *
	 * @param int    $plan_id Meal plan post ID.
	 * @param int    $day     Day number, 1–7.
	 * @param string $variant One of magazine|split|card.
	 * @return string|WP_Error Absolute path of the JPEG, or error.
	 */
	public function render_day_framed( int $plan_id, int $day, string $variant, int $index = 0 ): string|WP_Error {

		$check = $this->pre_render_check( $plan_id );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$plan = new Mixmymeals_Meal_Plan( $plan_id );
		$days = $plan->get_day_range();

		$position = array_search( $day, $days, true );
		if ( false === $position ) {
			return new WP_Error( 'mmm_instagram_day', __( 'This day is not part of the plan.', 'mixmymeals' ) );
		}

		$by_day    = self::meals_by_day( $plan );
		$meals     = $by_day[ $day ] ?? array();

		// One recipe per slide (the one at $index).
		if ( isset( $meals[ $index ] ) ) {
			$meals = array( $meals[ $index ] );
		}

		$hero_meal = self::pick_hero_meal( $meals );
		$hero_id   = self::pick_hero_attachment( $meals, $hero_meal );
		$badges    = self::recipe_badges( $hero_meal['recipe_id'] ?? 0 );

		list( $w, $h ) = self::canvas_size();
		$fs            = self::font_scale();

		$primary = (string) self::c_primary();
		$accent  = (string) self::c_accent();

		$title_font = self::get_font_path( 'title' );
		$text_font  = self::get_font_path( 'text' );

		$is_card = 'card' === $variant;
		$margin  = $is_card ? 56 : 0;
		$ratio   = 'split' === $variant ? 0.50 : 0.60;

		$bg = $is_card ? self::mix( $primary, '#FFFFFF', 0.90 ) : (string) self::c_bg();
		$im = $this->canvas( $w, $h, $bg );

		// Ink chosen for the ground so dark presets get light text.
		$ink  = self::contrast_ink( $bg );
		$ink2 = self::contrast_ink( $bg, true );

		// Photo region.
		$photo_x = $margin;
		$photo_y = $margin;
		$photo_w = $w - 2 * $margin;
		$photo_h = (int) round( $h * $ratio ) - $margin;

		if ( $is_card ) {
			$rad = max( 28, (int) self::opt( 'corner_radius', 24 ) );
			$pad = (int) round( 22 * $fs );
			$this->pill( $im, $photo_x, $photo_y, $photo_w, $photo_h, $rad, '#FFFFFF' );
			if ( $hero_id ) {
				$this->draw_cover_region( $im, $hero_id, $photo_x + $pad, $photo_y + $pad, $photo_w - 2 * $pad, $photo_h - 2 * $pad );
			}
			$pill_x        = $photo_x + $pad + (int) round( 20 * $fs );
			$pill_y        = $photo_y + $pad + (int) round( 20 * $fs );
			$counter_right = $photo_x + $photo_w - $pad - (int) round( 20 * $fs );
			$text_top      = $photo_y + $photo_h + (int) round( 44 * $fs );
		} else {
			// Fallback ground under the photo when there is none.
			$this->pill( $im, $photo_x, $photo_y, $photo_w, $photo_h, 0, self::mix( $primary, '#000000', 0.5 ) );
			if ( $hero_id ) {
				$this->draw_cover_region( $im, $hero_id, $photo_x, $photo_y, $photo_w, $photo_h );
			}
			$pill_x        = 64;
			$pill_y        = 56;
			$counter_right = $w - 60;
			$text_top      = $photo_h + (int) round( 52 * $fs );
		}

		// Day pill on the photo.
		if ( self::is_on( 'show_day_pill' ) ) {
			$pill_text = mb_strtoupper(
				sprintf(
					/* translators: %d: position of the day within the plan. */
					__( 'Day %d', 'mixmymeals' ),
					(int) $position + 1
				) . ' – ' . self::weekday_name( $day )
			);
			$pill_size = 30 * $fs;
			$pill_w    = $this->text_width( $pill_text, $pill_size, $title_font, 2 ) + 68;
			$pill_h    = (int) round( $pill_size * 2.2 );
			$this->pill( $im, $pill_x, $pill_y, $pill_w, $pill_h, $pill_h / 2, '#FBF8F1', 0.95 );
			$this->text_centered( $im, $pill_text, $pill_size, $pill_x, $pill_y, $pill_w, $pill_h, '#2C2A24', $title_font, 2 );
		}

		// Slide counter on the photo, top right.
		$this->draw_counter_at( $im, $counter_right, $pill_y, self::counter_text( $plan, 'day-' . $day . '-' . $index ), true );

		// Text block, top down on the light ground.
		$tx0 = $is_card ? $margin : 72;
		$tx1 = $w - ( $is_card ? $margin : 72 );
		$y   = $text_top;

		if ( null !== $hero_meal ) {
			$this->text( $im, mb_strtoupper( $hero_meal['type_label'] ), 30 * $fs, $tx0, $y, $accent, $title_font, 5 );
			$y += (int) round( 30 * $fs * 1.7 );
		}

		$title_text  = $hero_meal ? $hero_meal['title'] : self::clean_text( get_the_title( $plan_id ) );
		$title_size  = ( '1x1' === self::opt( 'format', '4x5' ) ? 62 : 70 ) * $fs;
		$title_lines = $this->wrap_text( $title_text, $title_size, $title_font, $tx1 - $tx0, 2 );
		if ( count( $title_lines ) > 1 ) {
			$title_size *= 0.9;
			$title_lines = $this->wrap_text( $title_text, $title_size, $title_font, $tx1 - $tx0, 2 );
		}
		$line_h = (int) round( $title_size * 1.1 );
		foreach ( $title_lines as $line ) {
			$this->text( $im, $line, $title_size, $tx0, $y, $ink, $title_font );
			$y += $line_h;
		}

		// On days with several recipes the hero-only nutrition badges would be
		// misleading and leave no room for the recipe list, so they are dropped
		// in favour of naming every recipe (the caption keeps full nutrition).
		$list_others = self::is_on( 'show_also' ) && count( $meals ) > 1;

		if ( $badges && ! $list_others ) {
			$y    += (int) round( 22 * $fs );
			$bx    = $tx0;
			$vsize = 42 * $fs;
			$lsize = 22 * $fs;
			$bh    = (int) round( 108 * $fs );
			foreach ( $badges as $badge ) {
				$vw = $this->text_width( $badge['value'], $vsize, $title_font );
				$lw = $this->text_width( $badge['label'], $lsize, $text_font );
				$bw = max( $vw, $lw ) + (int) round( 48 * $fs );
				$this->badge( $im, $bx, $y, $bw, $bh, $badge['value'], $badge['label'], $fs, true );
				$bx += $bw + (int) round( 16 * $fs );
			}
			$this->draw_diet_pill( $im, $hero_meal['recipe_id'] ?? 0, $bx, $y, $bh, $fs );
			$y += $bh;
		}

		// Footer sits at the very bottom; the "also" line only appears when it
		// fits in the gap above it, so the two never overlap.
		$has_footer = self::is_on( 'show_brand_footer' );
		$fy         = $h - 60 - (int) round( 44 * $fs );
		$footer_top = $has_footer ? $fy - (int) round( 24 * $fs ) : $h - 40;

		// Every further recipe of the day, one per line ("MEALTIME — Name").
		// Only lines that fit above the footer are drawn, so nothing overlaps;
		// the per-slide caption always names every recipe in full.
		if ( $list_others ) {
			$also_line_h = (int) round( 32 * $fs * 1.42 );
			$y          += (int) round( 22 * $fs );
			foreach ( $meals as $meal ) {
				if ( $meal === $hero_meal ) {
					continue;
				}
				if ( $y + $also_line_h > $footer_top - (int) round( 12 * $fs ) ) {
					break;
				}
				$also = $this->truncate(
					mb_strtoupper( (string) $meal['type_label'] ) . ' — ' . $meal['title'],
					32 * $fs,
					$text_font,
					$tx1 - $tx0
				);
				$this->text( $im, $also, 32 * $fs, $tx0, $y, $ink2, $text_font );
				$y += $also_line_h;
			}
		}

		// Brand footer at the bottom.
		if ( $has_footer ) {
			$this->hline( $im, $tx0, $tx1, $fy - (int) round( 24 * $fs ), $ink, 0.12 );

			$wm_size = 36 * $fs;
			$this->draw_wordmark( $im, $tx0, $fy, $wm_size, $ink, $accent );

			$right = $this->get_handle();
			if ( '' === $right ) {
				$right = $this->site_host();
			}
			$r_size = 30 * $fs;
			$r_w    = $this->text_width( $right, $r_size, $text_font );
			$this->text( $im, $right, $r_size, $tx1 - $r_w, $fy + (int) round( ( $wm_size - $r_size ) * 0.8 ), $ink2, $text_font );
		}

		return $this->save( $im, $plan_id, 'tag-' . $day . '-' . $index . '.jpg' );
	}

	/**
	 * Renders the cover / hook slide: the week's best recipe photo full-bleed
	 * with an eyebrow, the plan title and a facts subtitle. This is the first
	 * slide of the carousel and exists to stop the scroll.
	 *
	 * @param int $plan_id Meal plan post ID.
	 * @return string|WP_Error Absolute path of the JPEG, or error.
	 */
	public function render_cover( int $plan_id ): string|WP_Error {

		$check = $this->pre_render_check( $plan_id );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$plan = new Mixmymeals_Meal_Plan( $plan_id );

		list( $w, $h ) = self::canvas_size();
		$fs            = self::font_scale();

		$accent       = (string) self::c_accent();
		$accent_light = self::mix( $accent, '#FFFFFF', 0.35 );
		$title_font   = self::get_font_path( 'title' );
		$text_font    = self::get_font_path( 'text' );

		$im = $this->canvas( $w, $h, self::mix( (string) self::c_primary(), '#000000', 0.5 ) );

		// Cover photo: a manually chosen image, else the week's best photo.
		$hero_id = absint( self::opt( 'cover_image_id', 0 ) );
		if ( ! $hero_id ) {
			$hero_id = self::pick_cover_attachment( $plan );
		}
		if ( $hero_id ) {
			$this->draw_cover( $im, $hero_id, $w, $h );
		}

		// Full-height scrim: darker at the bottom for the title, a light wash
		// at the top so the wordmark and counter stay legible.
		$this->scrim_full( $im, $w, $h );

		$x0 = 72;
		$x1 = $w - 72;

		// Brandmark (logo image or wordmark) at the configured alignment.
		$logo_id  = absint( self::opt( 'logo_id', 0 ) );
		$centered = 'center' === self::opt( 'logo_position', 'left' );
		if ( $logo_id ) {
			$logo_h = (int) round( 52 * $fs * self::logo_scale() );
			$mark_w = $this->logo_width( $logo_id, $logo_h );
			$mark_x = $centered ? ( $w - $mark_w ) / 2 : $x0;
			$this->draw_logo( $im, $logo_id, $mark_x, 56, $logo_h );
		} else {
			$wm_size = 40 * $fs * self::logo_scale();
			$mark_w  = $this->wordmark_width( $wm_size );
			$mark_x  = $centered ? ( $w - $mark_w ) / 2 : $x0;
			$this->draw_wordmark( $im, $mark_x, 60, $wm_size, '#FFFFFF', $accent_light );
		}

		// Slide counter top right.
		$this->draw_counter( $im, $w, self::counter_text( $plan, 'cover' ), true );

		// Content anchored near the bottom.
		$days_n  = max( 1, count( $plan->get_day_range() ) );
		$eyebrow = mb_strtoupper(
			sprintf(
				/* translators: %d: number of days in the plan. */
				_n( 'Weekly plan – %d day', 'Weekly plan – %d days', $days_n, 'mixmymeals' ),
				$days_n
			)
		);
		$title_opt = trim( (string) self::opt( 'cover_title', '' ) );
		$title     = self::clean_text( '' !== $title_opt ? $title_opt : get_the_title( $plan_id ) );

		$sub_opt  = trim( (string) self::opt( 'cover_subtitle', '' ) );
		$subtitle = '' !== $sub_opt ? self::clean_text( $sub_opt ) : self::cover_subtitle( $plan );

		$title_size  = ( '1x1' === self::opt( 'format', '4x5' ) ? 84 : 100 ) * $fs;
		$title_lines = $this->wrap_text( $title, $title_size, $title_font, $x1 - $x0, 3 );
		if ( count( $title_lines ) >= 3 ) {
			$title_size *= 0.82;
			$title_lines = $this->wrap_text( $title, $title_size, $title_font, $x1 - $x0, 3 );
		}
		$line_h = (int) round( $title_size * 1.05 );

		$eb_h    = (int) round( 31 * $fs * 1.8 );
		$sub_h   = '' !== $subtitle ? (int) round( 38 * $fs * 1.5 ) : 0;
		$block_h = $eb_h + count( $title_lines ) * $line_h + $sub_h;

		$y = $h - 150 - $block_h;

		$this->text( $im, $eyebrow, 31 * $fs, $x0, $y, $accent_light, $title_font, 4 );
		$y += $eb_h;
		foreach ( $title_lines as $line ) {
			$this->text( $im, $line, $title_size, $x0, $y, '#FFFFFF', $title_font );
			$y += $line_h;
		}
		if ( '' !== $subtitle ) {
			$y += (int) round( 8 * $fs );
			$this->text( $im, $subtitle, 38 * $fs, $x0, $y, '#FFFFFF', $text_font, 0, 0.9 );
		}

		return $this->save( $im, $plan_id, 'cover.jpg' );
	}

	/**
	 * Renders the closing call-to-action slide (solid brand color, "save this
	 * plan", link-in-bio note and the handle). The last slide of the carousel.
	 *
	 * @param int $plan_id Meal plan post ID.
	 * @return string|WP_Error Absolute path of the JPEG, or error.
	 */
	public function render_cta( int $plan_id ): string|WP_Error {

		$check = $this->pre_render_check( $plan_id );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$plan = new Mixmymeals_Meal_Plan( $plan_id );

		list( $w, $h ) = self::canvas_size();
		$fs            = self::font_scale();

		$primary      = (string) self::c_primary();
		$accent_light = self::mix( (string) self::c_accent(), '#FFFFFF', 0.55 );
		$title_font   = self::get_font_path( 'title' );
		$text_font    = self::get_font_path( 'text' );

		// Text colour follows the primary background's luminance.
		$fg = self::contrast_ink( $primary );

		$im = $this->canvas( $w, $h, $primary );

		$x0 = 90;
		$x1 = $w - 90;

		$this->draw_counter( $im, $w, self::counter_text( $plan, 'cta' ), true );

		$title   = self::clean_text( (string) self::opt( 'cta_text', __( 'Save this plan', 'mixmymeals' ) ) );
		$subtext = self::clean_text( (string) self::opt( 'cta_subtext', __( 'Full recipes & shopping list — link in bio', 'mixmymeals' ) ) );
		$handle  = $this->get_handle();

		$title_size  = 88 * $fs;
		$title_lines = $this->wrap_text( $title, $title_size, $title_font, $x1 - $x0, 3 );
		$line_h      = (int) round( $title_size * 1.08 );

		$sub_lines = '' !== $subtext ? $this->wrap_text( $subtext, 40 * $fs, $text_font, $x1 - $x0, 3 ) : array();
		$sub_lh    = (int) round( 40 * $fs * 1.4 );

		$bar_h    = (int) round( 12 * $fs );
		$handle_h = '' !== $handle ? (int) round( 48 * $fs * 1.9 ) : 0;

		$block_h = $bar_h + (int) round( 44 * $fs )
			+ count( $title_lines ) * $line_h + (int) round( 24 * $fs )
			+ count( $sub_lines ) * $sub_lh + $handle_h;

		$y = (int) round( ( $h - $block_h ) / 2 );

		// Small accent bar as a quiet flourish above the headline.
		$bar_w = (int) round( 120 * $fs );
		$this->pill( $im, ( $w - $bar_w ) / 2, $y, $bar_w, $bar_h, $bar_h / 2, $accent_light );
		$y += $bar_h + (int) round( 44 * $fs );

		foreach ( $title_lines as $line ) {
			$lw = $this->text_width( $line, $title_size, $title_font );
			$this->text( $im, $line, $title_size, ( $w - $lw ) / 2, $y, $fg, $title_font );
			$y += $line_h;
		}

		$y += (int) round( 24 * $fs );
		foreach ( $sub_lines as $line ) {
			$lw = $this->text_width( $line, 40 * $fs, $text_font );
			$this->text( $im, $line, 40 * $fs, ( $w - $lw ) / 2, $y, $fg, $text_font, 0, 0.92 );
			$y += $sub_lh;
		}

		if ( '' !== $handle ) {
			$y += (int) round( 26 * $fs );
			$hsize = 48 * $fs;
			$hw    = $this->text_width( $handle, $hsize, $title_font );
			$this->text( $im, $handle, $hsize, ( $w - $hw ) / 2, $y, $fg, $title_font );
		}

		return $this->save( $im, $plan_id, 'cta.jpg' );
	}

	/**
	 * Renders the single image for a recipe post: the recipe photo full-bleed
	 * with the first category as eyebrow, the title, nutrition badges and the
	 * brand footer. Published as a plain image post, not a carousel.
	 *
	 * @param int $recipe_id Recipe post ID.
	 * @return string|WP_Error Absolute path of the JPEG, or error.
	 */
	public function render_recipe( int $recipe_id ): string|WP_Error {

		$check = $this->pre_render_check( $recipe_id );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$recipe = new Mixmymeals_Recipe( $recipe_id );
		if ( get_post_type( $recipe_id ) !== self::RECIPE_POST_TYPE || ! $recipe->is_valid() ) {
			return new WP_Error( 'mmm_instagram_recipe', __( 'Not a recipe.', 'mixmymeals' ) );
		}

		list( $w, $h ) = self::canvas_size();
		$fs            = self::font_scale();

		$accent       = (string) self::c_accent();
		$accent_light = self::mix( $accent, '#FFFFFF', 0.35 );
		$title_font   = self::get_font_path( 'title' );
		$text_font    = self::get_font_path( 'text' );

		// Background: recipe photo, placeholder, or dark primary fallback.
		$im      = $this->canvas( $w, $h, self::mix( (string) self::c_primary(), '#000000', 0.55 ) );
		$hero_id = (int) get_post_thumbnail_id( $recipe_id );
		if ( ! $hero_id ) {
			$hero_id = absint( get_option( 'mixmymeals_placeholder_image_id', 0 ) );
		}
		if ( $hero_id ) {
			$this->draw_cover( $im, $hero_id, $w, $h );
		}

		// Overlay for text legibility, same styles as the day slides.
		$overlay  = (string) self::opt( 'overlay_style', 'gradient' );
		$strength = max( 0, min( 100, (int) self::opt( 'scrim_opacity', 60 ) ) ) / 100;
		if ( 'gradient' === $overlay ) {
			$this->scrim( $im, (int) round( $h * 0.38 ), $h, $strength );
		} elseif ( 'minimal' === $overlay ) {
			$this->scrim( $im, (int) round( $h * 0.55 ), $h, $strength * 0.6 );
		}

		$x0 = 72;
		$x1 = $w - 72;

		// Eyebrow: the recipe's first category, falling back to "Recipe".
		$categories = $recipe->get_categories();
		$eyebrow    = $categories
			? self::clean_text( (string) $categories[0]->name )
			: __( 'Recipe', 'mixmymeals' );

		$title_text  = self::clean_text( get_the_title( $recipe_id ) );
		$title_size  = ( '1x1' === self::opt( 'format', '4x5' ) ? 72 : 84 ) * $fs;
		$title_lines = $this->wrap_text( $title_text, $title_size, $title_font, $x1 - $x0, 2 );
		if ( count( $title_lines ) > 1 ) {
			$title_size  = $title_size * 0.88;
			$title_lines = $this->wrap_text( $title_text, $title_size, $title_font, $x1 - $x0, 2 );
		}
		$line_h = (int) round( $title_size * 1.1 );

		$badges    = self::recipe_badges( $recipe_id );
		$badge_h   = $badges ? (int) round( 106 * $fs ) : 0;
		$badge_gap = $badges ? (int) round( 20 * $fs ) : 0;

		$footer_h = self::is_on( 'show_brand_footer' ) ? (int) round( 96 * $fs ) : 0;
		$type_h   = (int) round( 30 * $fs * 1.7 );
		$block_h  = $type_h + count( $title_lines ) * $line_h + $badge_gap + $badge_h + $footer_h;

		$y = $h - 60 - $block_h;

		if ( 'plate' === $overlay ) {
			$pad = (int) round( 40 * $fs );
			$this->pill(
				$im,
				$x0 - $pad,
				$y - $pad,
				( $x1 - $x0 ) + 2 * $pad,
				( $h - 40 ) - ( $y - $pad ),
				(int) round( 28 * $fs ),
				'#18140E',
				0.58
			);
		}

		$this->text( $im, mb_strtoupper( $eyebrow ), 30 * $fs, $x0, $y, $accent_light, $title_font, 5 );
		$y += $type_h;

		foreach ( $title_lines as $line ) {
			$this->text( $im, $line, $title_size, $x0, $y, '#FFFFFF', $title_font );
			$y += $line_h;
		}

		if ( $badges ) {
			$y    += $badge_gap;
			$bx    = $x0;
			$vsize = 42 * $fs;
			$lsize = 22 * $fs;
			foreach ( $badges as $badge ) {
				$vw = $this->text_width( $badge['value'], $vsize, $title_font );
				$lw = $this->text_width( $badge['label'], $lsize, $text_font );
				$bw = max( $vw, $lw ) + (int) round( 48 * $fs );
				$this->badge( $im, $bx, $y, $bw, $badge_h, $badge['value'], $badge['label'], $fs );
				$bx += $bw + (int) round( 16 * $fs );
			}
			$this->draw_diet_pill( $im, $recipe_id, $bx, $y, $badge_h, $fs );
			$y += $badge_h;
		}

		if ( $footer_h ) {
			$y += (int) round( 26 * $fs );
			$this->hline( $im, $x0, $x1, $y, '#FFFFFF', 0.28 );
			$y += (int) round( 24 * $fs );

			$wm_size = 36 * $fs;
			$this->draw_wordmark( $im, $x0, $y, $wm_size, '#FFFFFF', $accent_light );

			$right = $this->get_handle();
			if ( '' === $right ) {
				$right = $this->site_host();
			}
			$r_size = 30 * $fs;
			$r_w    = $this->text_width( $right, $r_size, $text_font );
			$this->text( $im, $right, $r_size, $x1 - $r_w, $y + (int) round( ( $wm_size - $r_size ) * 0.8 ), '#FFFFFF', $text_font, 0, 0.8 );
		}

		return $this->save( $im, $recipe_id, 'rezept.jpg' );
	}

	/**
	 * Suggested caption for a recipe post: ingredients, preparation steps,
	 * nutrition facts and the recipe's post content, plus the hashtag block.
	 *
	 * @param int $recipe_id Recipe post ID.
	 * @return string
	 */
	public static function recipe_caption( int $recipe_id ): string {

		$recipe = new Mixmymeals_Recipe( $recipe_id );
		if ( ! $recipe->is_valid() ) {
			return '';
		}

		$servings = max( 1, (int) round( $recipe->get_servings() ) );

		// Reuse the day-slide caption builder with the recipe as its own hero.
		$caption = self::recipe_slide_caption(
			array(
				'recipe_id' => $recipe_id,
				'title'     => self::clean_text( get_the_title( $recipe_id ) ),
				'servings'  => $servings,
			),
			array()
		);

		// Post content and hashtags round off the caption.
		$extras = array();

		$post    = get_post( $recipe_id );
		$content = $post
			? trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) ) ) )
			: '';
		if ( '' !== $content ) {
			$extras[] = self::clean_text( $content );
		}

		$hashtags = trim( (string) self::opt( 'hashtags', '' ) );
		if ( '' !== $hashtags ) {
			$extras[] = $hashtags;
		}

		if ( $extras ) {
			$caption .= "\n\n" . implode( "\n\n", $extras );
		}

		return mb_substr( $caption, 0, self::CAPTION_MAX );
	}

	/**
	 * Picks the best recipe photo across the whole plan for the cover: the
	 * first day's hero recipe photo, then any recipe photo, then the global
	 * placeholder image.
	 *
	 * @param Mixmymeals_Meal_Plan $plan Plan object.
	 * @return int Attachment ID or 0.
	 */
	private static function pick_cover_attachment( Mixmymeals_Meal_Plan $plan ): int {

		foreach ( self::meals_by_day( $plan ) as $meals ) {
			$hero    = self::pick_hero_meal( $meals );
			$ordered = array();
			if ( null !== $hero ) {
				$ordered[] = $hero;
			}
			foreach ( $meals as $meal ) {
				if ( $meal !== $hero ) {
					$ordered[] = $meal;
				}
			}
			foreach ( $ordered as $meal ) {
				if ( $meal['recipe_id'] ) {
					$thumb = (int) get_post_thumbnail_id( $meal['recipe_id'] );
					if ( $thumb ) {
						return $thumb;
					}
				}
			}
		}

		return absint( get_option( 'mixmymeals_placeholder_image_id', 0 ) );
	}

	/**
	 * Builds the facts subtitle for the cover: diet, servings, average kcal.
	 *
	 * @param Mixmymeals_Meal_Plan $plan Plan object.
	 * @return string
	 */
	private static function cover_subtitle( Mixmymeals_Meal_Plan $plan ): string {

		$parts = array();

		$diet = $plan->get_diet_type();
		if ( 'vegan' === $diet ) {
			$parts[] = __( 'vegan', 'mixmymeals' );
		} elseif ( 'vegetarian' === $diet || 'vegetarisch' === $diet ) {
			$parts[] = __( 'vegetarian', 'mixmymeals' );
		}

		$servings = $plan->get_servings_range_string();
		if ( '' !== $servings ) {
			$parts[] = $servings;
		}

		$kcal = self::avg_calories( $plan );
		if ( $kcal > 0 ) {
			$parts[] = sprintf(
				/* translators: %d: average calories per serving. */
				__( 'approx. %d kcal', 'mixmymeals' ),
				$kcal
			);
		}

		return implode( '  ·  ', $parts );
	}

	/**
	 * Average calories per serving across the plan's meals (0 when unknown).
	 *
	 * @param Mixmymeals_Meal_Plan $plan Plan object.
	 * @return int
	 */
	private static function avg_calories( Mixmymeals_Meal_Plan $plan ): int {

		$sum = 0.0;
		$n   = 0;

		foreach ( $plan->get_meals() as $meal_post ) {
			$meal      = new Mixmymeals_Meal( $meal_post->ID );
			$recipe_id = $meal->get_recipe_id();
			if ( ! $recipe_id ) {
				continue;
			}
			$recipe = new Mixmymeals_Recipe( $recipe_id );
			if ( ! $recipe->is_valid() ) {
				continue;
			}
			$nutrition = $recipe->calculate_nutrition_per_serving();
			if ( ! empty( $nutrition['calories'] ) ) {
				$sum += (float) $nutrition['calories'];
				++$n;
			}
		}

		return $n ? (int) round( $sum / $n ) : 0;
	}

	/**
	 * Builds the badge list for a recipe from the enabled badge option,
	 * skipping any value that is missing or zero.
	 *
	 * @param int $recipe_id Recipe post ID.
	 * @return array[] Arrays with 'value' and 'label'.
	 */
	private static function recipe_badges( int $recipe_id ): array {

		$enabled = self::opt( 'badges', array( 'calories', 'protein', 'time' ) );
		if ( ! is_array( $enabled ) || ! $enabled || ! $recipe_id ) {
			return array();
		}

		$recipe = new Mixmymeals_Recipe( $recipe_id );
		if ( ! $recipe->is_valid() ) {
			return array();
		}

		$nutrition = $recipe->calculate_nutrition_per_serving();
		$badges    = array();

		foreach ( $enabled as $key ) {
			switch ( $key ) {
				case 'calories':
					if ( ! empty( $nutrition['calories'] ) ) {
						$badges[] = array(
							'value' => (string) (int) round( $nutrition['calories'] ),
							'label' => __( 'kcal', 'mixmymeals' ),
						);
					}
					break;
				case 'protein':
					if ( ! empty( $nutrition['protein'] ) ) {
						$badges[] = array(
							'value' => (int) round( $nutrition['protein'] ) . ' g',
							'label' => __( 'Protein', 'mixmymeals' ),
						);
					}
					break;
				case 'carbs':
					if ( ! empty( $nutrition['carbs'] ) ) {
						$badges[] = array(
							'value' => (int) round( $nutrition['carbs'] ) . ' g',
							'label' => __( 'Carbs', 'mixmymeals' ),
						);
					}
					break;
				case 'fat':
					if ( ! empty( $nutrition['fat'] ) ) {
						$badges[] = array(
							'value' => (int) round( $nutrition['fat'] ) . ' g',
							'label' => __( 'Fat', 'mixmymeals' ),
						);
					}
					break;
				case 'time':
					$time = $recipe->get_preparation_time();
					if ( $time > 0 ) {
						$badges[] = array(
							'value' => (string) $time,
							'label' => __( 'Min', 'mixmymeals' ),
						);
					}
					break;
			}
		}

		// Four badges is the most that fits a row cleanly.
		return array_slice( $badges, 0, 4 );
	}

	/**
	 * The diet label for a recipe from its categories (vegan/vegetarian), or
	 * '' when the diet tag is disabled or no diet category is set.
	 *
	 * @param int $recipe_id Recipe post ID.
	 * @return string Localized label, or ''.
	 */
	private static function recipe_diet( int $recipe_id ): string {

		if ( ! $recipe_id || ! self::is_on( 'show_diet_tag' ) ) {
			return '';
		}

		$recipe = new Mixmymeals_Recipe( $recipe_id );
		if ( ! $recipe->is_valid() ) {
			return '';
		}

		$diet = '';
		foreach ( $recipe->get_categories() as $cat ) {
			$name = strtolower( $cat->name );
			if ( 'vegan' === $name ) {
				return __( 'vegan', 'mixmymeals' );
			}
			if ( in_array( $name, array( 'vegetarisch', 'vegetarian' ), true ) ) {
				$diet = __( 'vegetarian', 'mixmymeals' );
			}
		}

		return $diet;
	}

	/**
	 * Draws the diet pill (e.g. "VEGAN") at the end of the badges row, in the
	 * brand color, vertically centered against the badge height.
	 *
	 * @param GdImage $im        Image.
	 * @param int     $recipe_id Recipe post ID.
	 * @param float   $x         Left, design px.
	 * @param float   $row_y     Badge row top, design px.
	 * @param float   $row_h     Badge row height, design px.
	 * @param float   $fs        Font scale.
	 * @return void
	 */
	private function draw_diet_pill( GdImage $im, int $recipe_id, float $x, float $row_y, float $row_h, float $fs ): void {

		$diet = self::recipe_diet( $recipe_id );
		if ( '' === $diet ) {
			return;
		}

		$title_font = self::get_font_path( 'title' );
		$text       = mb_strtoupper( $diet );
		$size       = 27 * $fs;
		$pill_h     = (int) round( $size * 2.0 );
		$pill_w     = $this->text_width( $text, $size, $title_font, 2 ) + (int) round( 48 * $fs );
		$y          = $row_y + ( $row_h - $pill_h ) / 2;

		$this->pill( $im, $x, $y, $pill_w, $pill_h, $pill_h / 2, (string) self::c_primary() );
		$this->text_centered( $im, $text, $size, $x, $y, $pill_w, $pill_h, '#FFFFFF', $title_font, 2 );
	}

	/**
	 * The Instagram handle to show on the slides: the manual option, else the
	 * connected account's username, else empty. Never makes an API call.
	 *
	 * @return string Handle with a leading '@', or ''.
	 */
	private function get_handle(): string {

		$manual = trim( (string) self::opt( 'handle', '' ) );
		if ( '' !== $manual ) {
			return '@' . ltrim( $manual, '@' );
		}

		// Falls back to the acting account's handle (per-user when the
		// acting user posts with their own credentials).
		$stored = self::cred_get( 'ig_username' );

		return '' !== $stored ? '@' . $stored : '';
	}

	/**
	 * The "n/m" slide counter text for a job, or '' when the counter is off.
	 *
	 * @param Mixmymeals_Meal_Plan $plan     Plan object.
	 * @param string               $job_key  Job key (cover/overview/day-N/cta).
	 * @return string
	 */
	private static function counter_text( Mixmymeals_Meal_Plan $plan, string $job_key ): string {

		if ( ! self::is_on( 'show_counter' ) ) {
			return '';
		}

		$jobs  = self::get_jobs( $plan );
		$total = count( $jobs );

		foreach ( $jobs as $i => $job ) {
			if ( $job['job'] === $job_key ) {
				return ( $i + 1 ) . '/' . $total;
			}
		}

		return '';
	}

	/* ---------------------------------------------------------------------
	 * Rendering: GD primitives (all coordinates in design pixels)
	 * ------------------------------------------------------------------ */

	/**
	 * Draws the slide counter pill in the top-right corner.
	 *
	 * @param GdImage $im      Image.
	 * @param int     $w       Canvas width in design pixels.
	 * @param string  $text    Counter text (empty = draw nothing).
	 * @param bool    $on_dark Whether the background is dark/photo (cream pill)
	 *                         or light (brand-tint pill).
	 * @return void
	 */
	private function draw_counter( GdImage $im, int $w, string $text, bool $on_dark ): void {
		$this->draw_counter_at( $im, $w - 60, 56, $text, $on_dark );
	}

	/**
	 * Draws the slide counter pill with its right edge at ($right, $top).
	 *
	 * @param GdImage $im      Image.
	 * @param float   $right   Right edge X in design pixels.
	 * @param float   $top     Top Y in design pixels.
	 * @param string  $text    Counter text (empty = draw nothing).
	 * @param bool    $on_dark Dark/photo background (cream pill) or light (tint pill).
	 * @return void
	 */
	private function draw_counter_at( GdImage $im, float $right, float $top, string $text, bool $on_dark ): void {

		if ( '' === $text ) {
			return;
		}

		$fs     = self::font_scale();
		$font   = self::get_font_path( 'title' );
		$size   = 30 * $fs;
		$pill_h = (int) round( $size * 2.0 );
		$pill_w = $this->text_width( $text, $size, $font, 1 ) + (int) round( 52 * $fs );
		$x      = $right - $pill_w;
		$y      = $top;

		if ( $on_dark ) {
			$this->pill( $im, $x, $y, $pill_w, $pill_h, $pill_h / 2, '#FBF8F1', 0.92 );
			$this->text_centered( $im, $text, $size, $x, $y, $pill_w, $pill_h, '#2C2A24', $font, 1 );
		} else {
			$tint = self::mix( (string) self::c_primary(), '#FFFFFF', 0.78 );
			$deep = self::mix( (string) self::c_primary(), '#000000', 0.35 );
			$this->pill( $im, $x, $y, $pill_w, $pill_h, $pill_h / 2, $tint );
			$this->text_centered( $im, $text, $size, $x, $y, $pill_w, $pill_h, $deep, $font, 1 );
		}
	}

	/**
	 * Draws one nutrition/time badge: a translucent plate with a big value
	 * and a small label. Used on the day slides over the photo.
	 *
	 * @param GdImage $im    Image.
	 * @param float   $x     Left, design px.
	 * @param float   $y     Top, design px.
	 * @param float   $bw    Width, design px.
	 * @param float   $bh    Height, design px.
	 * @param string  $value Big value text.
	 * @param string  $label Small label text.
	 * @param float   $fs    Font scale.
	 * @return void
	 */
	private function badge( GdImage $im, float $x, float $y, float $bw, float $bh, string $value, string $label, float $fs, bool $on_light = false ): void {

		$title_font = self::get_font_path( 'title' );
		$text_font  = self::get_font_path( 'text' );
		$vsize      = 42 * $fs;
		$lsize      = 22 * $fs;

		if ( $on_light ) {
			// Solid brand-tint plate with brand-colored value on light layouts.
			$plate = self::mix( (string) self::c_primary(), '#FFFFFF', 0.82 );
			$vcol  = self::mix( (string) self::c_primary(), '#000000', 0.30 );
			$lcol  = '#6B665B';
			$this->pill( $im, $x, $y, $bw, $bh, (int) round( 20 * $fs ), $plate );
		} else {
			// Translucent white plate over the photo.
			$vcol = '#FFFFFF';
			$lcol = '#FFFFFF';
			$this->pill( $im, $x, $y, $bw, $bh, (int) round( 20 * $fs ), '#FFFFFF', 0.18 );
		}

		$vw = $this->text_width( $value, $vsize, $title_font );
		$this->text( $im, $value, $vsize, $x + ( $bw - $vw ) / 2, $y + 15 * $fs, $vcol, $title_font );

		$lw = $this->text_width( $label, $lsize, $text_font );
		$this->text( $im, $label, $lsize, $x + ( $bw - $lw ) / 2, $y + 15 * $fs + $vsize * 1.05, $lcol, $text_font, 0, $on_light ? 1.0 : 0.85 );
	}

	/**
	 * Draws a full-height vertical darkening gradient (light at the top,
	 * strong at the bottom) for legible text over a bright cover photo.
	 *
	 * @param GdImage $im     Image.
	 * @param int     $w      Canvas width in design pixels.
	 * @param int     $h      Canvas height in design pixels.
	 * @param float   $top    Opacity at the top edge.
	 * @param float   $bottom Opacity at the bottom edge.
	 * @return void
	 */
	private function scrim_full( GdImage $im, int $w, int $h, float $top = 0.18, float $bottom = 0.85 ): void {

		$rows = $h * self::SS;
		$max  = imagesx( $im );

		for ( $yy = 0; $yy < $rows; $yy++ ) {
			$t  = $yy / max( 1, $rows - 1 );
			$op = $top + ( $bottom - $top ) * ( $t * $t );
			imageline( $im, 0, $yy, $max, $yy, $this->color( $im, '#18140E', $op ) );
		}
	}

	/**
	 * Common pre-flight checks for both render methods.
	 *
	 * @param int $plan_id Meal plan post ID.
	 * @return true|WP_Error
	 */
	private function pre_render_check( int $plan_id ): bool|WP_Error {

		if ( ! function_exists( 'imagettftext' ) ) {
			return new WP_Error(
				'mmm_instagram_gd',
				__( 'The GD extension with FreeType support is not available on this server.', 'mixmymeals' )
			);
		}

		foreach ( array( 'title', 'text' ) as $role ) {
			$status = self::font_status( $role );
			if ( '' === $status['path'] ) {
				return new WP_Error(
					'mmm_instagram_font',
					'' !== $status['error']
						? $status['error']
						: __( 'Please choose a title font (TTF/OTF from the media library) in the settings first — GD cannot render text without one.', 'mixmymeals' )
				);
			}
		}

		if ( ! in_array( get_post_type( $plan_id ), array( self::POST_TYPE, self::RECIPE_POST_TYPE ), true ) ) {
			return new WP_Error(
				'mmm_instagram_plan',
				__( 'Not a meal plan.', 'mixmymeals' )
			);
		}

		return true;
	}

	/**
	 * The wordmark text: the configurable option, falling back to the site
	 * title exactly as written (no case transformation).
	 *
	 * @return string
	 */
	private function wordmark_text(): string {

		$custom = trim( (string) self::opt( 'wordmark', '' ) );

		return self::clean_text( '' !== $custom ? $custom : (string) get_option( 'blogname' ) );
	}

	/**
	 * Whether the accent dot is appended to the wordmark. Skipped when the
	 * wordmark already ends in punctuation ("Stopf nicht!." looks broken).
	 *
	 * @return bool
	 */
	private function wordmark_has_dot(): bool {
		return ! preg_match( '/[.!?…]$/u', $this->wordmark_text() );
	}

	/**
	 * Display width of the wordmark including the accent dot, design pixels.
	 *
	 * @param float $size Design pixel font size.
	 * @return float
	 */
	private function wordmark_width( float $size ): float {

		$font  = self::get_font_path( 'title' );
		$width = $this->text_width( $this->wordmark_text(), $size, $font );

		if ( $this->wordmark_has_dot() ) {
			$width += $this->text_width( '.', $size, $font );
		}

		return $width;
	}

	/**
	 * Draws the wordmark (with its accent dot when appropriate).
	 *
	 * @param GdImage $im     Image.
	 * @param float   $x      Left, design px.
	 * @param float   $y      Top, design px.
	 * @param float   $size   Design pixel font size.
	 * @param string  $color  Text colour.
	 * @param string  $accent Accent colour for the dot.
	 * @return void
	 */
	private function draw_wordmark( GdImage $im, float $x, float $y, float $size, string $color, string $accent ): void {

		$wm   = $this->wordmark_text();
		$font = self::get_font_path( 'title' );

		$this->text( $im, $wm, $size, $x, $y, $color, $font );

		if ( $this->wordmark_has_dot() ) {
			$this->text( $im, '.', $size, $x + $this->text_width( $wm, $size, $font ), $y, $accent, $font );
		}
	}

	/**
	 * Host name of the site, used as the URL line on day slides.
	 *
	 * @return string
	 */
	private function site_host(): string {
		return (string) wp_parse_url( home_url(), PHP_URL_HOST );
	}

	/**
	 * Default footer text for the overview slide.
	 *
	 * @return string
	 */
	private function default_footer_text(): string {
		return sprintf(
			/* translators: %s: site host name. */
			__( 'Full plan & shopping list: %s', 'mixmymeals' ),
			$this->site_host()
		);
	}

	/**
	 * Builds the overview meta line: day range, servings, meal count.
	 *
	 * @param Mixmymeals_Meal_Plan $plan Plan object.
	 * @return string
	 */
	private function overview_meta_line( Mixmymeals_Meal_Plan $plan ): string {

		$days  = $plan->get_day_range();
		$parts = array();

		if ( $days ) {
			$parts[] = count( $days ) > 1
				? self::weekday_name( (int) $days[0], true ) . ' – ' . self::weekday_name( (int) end( $days ), true )
				: self::weekday_name( (int) $days[0] );
		}

		$servings = $plan->get_servings_range_string();
		if ( '' !== $servings ) {
			$parts[] = $servings;
		}

		$count = $plan->count_meals();
		if ( $count ) {
			$parts[] = sprintf(
				/* translators: %d: number of meals in the plan. */
				_n( '%d dish', '%d dishes', $count, 'mixmymeals' ),
				$count
			);
		}

		return implode( '  ·  ', $parts );
	}

	/* ---------------------------------------------------------------------
	 * Rendering: GD primitives (all coordinates in design pixels, drawn
	 * at SS-times resolution and downsampled once in save())
	 * ------------------------------------------------------------------ */

	/**
	 * Creates the supersampled canvas filled with a background color.
	 *
	 * @param int    $w  Design width.
	 * @param int    $h  Design height.
	 * @param string $bg Background hex color.
	 * @return GdImage
	 */
	private function canvas( int $w, int $h, string $bg ): GdImage {

		$im = imagecreatetruecolor( $w * self::SS, $h * self::SS );
		imagefill( $im, 0, 0, $this->color( $im, $bg ) );

		return $im;
	}

	/**
	 * Allocates a (possibly translucent) color.
	 *
	 * @param GdImage $im      Image resource.
	 * @param string  $hex     Hex color like #AABBCC.
	 * @param float   $opacity 0 (invisible) to 1 (opaque).
	 * @return int
	 */
	private function color( GdImage $im, string $hex, float $opacity = 1.0 ): int {

		list( $r, $g, $b ) = self::hex_to_rgb( $hex );

		$alpha = (int) round( 127 * ( 1 - max( 0, min( 1, $opacity ) ) ) );

		return (int) imagecolorallocatealpha( $im, $r, $g, $b, $alpha );
	}

	/**
	 * Hex to RGB triple.
	 *
	 * @param string $hex Hex color.
	 * @return int[]
	 */
	private static function hex_to_rgb( string $hex ): array {

		$hex = ltrim( trim( $hex ), '#' );

		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
			return array( 0, 0, 0 );
		}

		return array(
			(int) hexdec( substr( $hex, 0, 2 ) ),
			(int) hexdec( substr( $hex, 2, 2 ) ),
			(int) hexdec( substr( $hex, 4, 2 ) ),
		);
	}

	/**
	 * Mixes two hex colors.
	 *
	 * @param string $hex_a First color.
	 * @param string $hex_b Second color.
	 * @param float  $ratio Share of the second color, 0–1.
	 * @return string Hex color.
	 */
	private static function mix( string $hex_a, string $hex_b, float $ratio ): string {

		$a = self::hex_to_rgb( $hex_a );
		$b = self::hex_to_rgb( $hex_b );

		$out = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$out[] = (int) round( $a[ $i ] * ( 1 - $ratio ) + $b[ $i ] * $ratio );
		}

		return sprintf( '#%02X%02X%02X', $out[0], $out[1], $out[2] );
	}

	/**
	 * Converts a design pixel font size to GD points at supersample scale.
	 *
	 * @param float $px Design pixel size.
	 * @return float
	 */
	private function pt( float $px ): float {
		return $px * self::SS * 0.75;
	}

	/**
	 * Measured pixel width of a string in design pixels.
	 *
	 * @param string $text     Text.
	 * @param float  $size     Design pixel font size.
	 * @param string $font     Font file.
	 * @param float  $tracking Extra letter spacing in design pixels.
	 * @return int
	 */
	private function text_width( string $text, float $size, string $font, float $tracking = 0 ): int {

		if ( $tracking > 0 ) {
			$width = 0;
			foreach ( self::mb_chars( $text ) as $char ) {
				$box    = imagettfbbox( $this->pt( $size ), 0, $font, $char );
				$width += ( $box[2] - $box[0] ) + $tracking * self::SS;
			}
			return (int) round( $width / self::SS );
		}

		$box = imagettfbbox( $this->pt( $size ), 0, $font, $text );

		return (int) round( ( $box[2] - $box[0] ) / self::SS );
	}

	/**
	 * Draws text with its top-left corner at ($x, $y) in design pixels.
	 * Uses reference metrics so different strings share a stable baseline.
	 *
	 * @param GdImage $im       Image.
	 * @param string  $text     Text.
	 * @param float   $size     Design pixel font size.
	 * @param float   $x        Left, design px.
	 * @param float   $y        Top, design px.
	 * @param string  $hex      Text color.
	 * @param string  $font     Font file.
	 * @param float   $tracking Extra letter spacing in design pixels.
	 * @param float   $opacity  0–1.
	 * @return void
	 */
	private function text(
		GdImage $im, string $text, float $size, float $x, float $y,
		string $hex, string $font, float $tracking = 0, float $opacity = 1.0
	): void {

		$ref      = imagettfbbox( $this->pt( $size ), 0, $font, 'Ágjq' );
		$baseline = (int) round( $y * self::SS - $ref[7] );
		$color    = $this->color( $im, $hex, $opacity );

		if ( $tracking > 0 ) {
			$cursor = $x * self::SS;
			foreach ( self::mb_chars( $text ) as $char ) {
				imagettftext( $im, $this->pt( $size ), 0, (int) round( $cursor ), $baseline, $color, $font, $char );
				$box     = imagettfbbox( $this->pt( $size ), 0, $font, $char );
				$cursor += ( $box[2] - $box[0] ) + $tracking * self::SS;
			}
			return;
		}

		imagettftext( $im, $this->pt( $size ), 0, (int) round( $x * self::SS ), $baseline, $color, $font, $text );
	}

	/**
	 * Draws text centered inside a box (both axes), design pixels.
	 *
	 * @param GdImage $im       Image.
	 * @param string  $text     Text.
	 * @param float   $size     Design pixel font size.
	 * @param float   $x        Box left.
	 * @param float   $y        Box top.
	 * @param float   $w        Box width.
	 * @param float   $h        Box height.
	 * @param string  $hex      Color.
	 * @param string  $font     Font file.
	 * @param float   $tracking Letter spacing.
	 * @return void
	 */
	private function text_centered(
		GdImage $im, string $text, float $size, float $x, float $y,
		float $w, float $h, string $hex, string $font, float $tracking = 0
	): void {

		$ref    = imagettfbbox( $this->pt( $size ), 0, $font, 'Ágjq' );
		$text_h = ( -$ref[7] + $ref[1] ) / self::SS;
		$text_w = $this->text_width( $text, $size, $font, $tracking );

		$this->text(
			$im,
			$text,
			$size,
			$x + ( $w - $text_w ) / 2,
			$y + ( $h - $text_h ) / 2,
			$hex,
			$font,
			$tracking
		);
	}

	/**
	 * Splits a UTF-8 string into characters.
	 *
	 * @param string $text Text.
	 * @return string[]
	 */
	private static function mb_chars( string $text ): array {
		return preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY ) ?: array();
	}

	/**
	 * Truncates text with an ellipsis so it fits a maximum width.
	 *
	 * @param string $text  Text.
	 * @param float  $size  Design pixel font size.
	 * @param string $font  Font file.
	 * @param float  $max_w Maximum width in design pixels.
	 * @return string
	 */
	private function truncate( string $text, float $size, string $font, float $max_w ): string {

		if ( $this->text_width( $text, $size, $font ) <= $max_w ) {
			return $text;
		}

		while ( mb_strlen( $text ) > 1 ) {
			$text = mb_substr( $text, 0, -1 );
			if ( $this->text_width( rtrim( $text ) . '…', $size, $font ) <= $max_w ) {
				return rtrim( $text ) . '…';
			}
		}

		return '…';
	}

	/**
	 * Wraps text into at most $max_lines lines of $max_w design pixels;
	 * the last line is truncated with an ellipsis when needed.
	 *
	 * @param string $text      Text.
	 * @param float  $size      Design pixel font size.
	 * @param string $font      Font file.
	 * @param float  $max_w     Line width in design pixels.
	 * @param int    $max_lines Maximum number of lines.
	 * @return string[]
	 */
	private function wrap_text( string $text, float $size, string $font, float $max_w, int $max_lines ): array {

		$words = preg_split( '/\s+/u', trim( $text ) ) ?: array();
		$lines = array();
		$line  = '';

		foreach ( $words as $word ) {
			$probe = '' === $line ? $word : $line . ' ' . $word;

			if ( $this->text_width( $probe, $size, $font ) <= $max_w ) {
				$line = $probe;
				continue;
			}

			if ( '' !== $line ) {
				$lines[] = $line;
			}
			$line = $word;

			if ( count( $lines ) === $max_lines - 1 ) {
				// Last line: everything that is left, truncated.
				$rest    = implode( ' ', array_slice( $words, array_search( $word, $words, true ) ) );
				$lines[] = $this->truncate( $rest, $size, $font, $max_w );
				return $lines;
			}
		}

		if ( '' !== $line ) {
			$lines[] = $line;
		}

		return $lines ?: array( '' );
	}

	/**
	 * Draws a filled rounded rectangle (pill when radius is height/2).
	 *
	 * @param GdImage $im      Image.
	 * @param float   $x       Left, design px.
	 * @param float   $y       Top, design px.
	 * @param float   $w       Width.
	 * @param float   $h       Height.
	 * @param float   $radius  Corner radius.
	 * @param string  $hex     Fill color.
	 * @param float   $opacity 0–1.
	 * @return void
	 */
	private function pill(
		GdImage $im, float $x, float $y, float $w, float $h,
		float $radius, string $hex, float $opacity = 1.0
	): void {

		$s = self::SS;
		$x = $x * $s;
		$y = $y * $s;
		$w = $w * $s;
		$h = $h * $s;
		$r = min( $radius * $s, $h / 2, $w / 2 );

		$color = $this->color( $im, $hex, $opacity );

		imagefilledrectangle( $im, (int) ( $x + $r ), (int) $y, (int) ( $x + $w - $r ), (int) ( $y + $h ), $color );
		imagefilledrectangle( $im, (int) $x, (int) ( $y + $r ), (int) ( $x + $w ), (int) ( $y + $h - $r ), $color );

		$d = (int) ( 2 * $r );
		imagefilledellipse( $im, (int) ( $x + $r ), (int) ( $y + $r ), $d, $d, $color );
		imagefilledellipse( $im, (int) ( $x + $w - $r ), (int) ( $y + $r ), $d, $d, $color );
		imagefilledellipse( $im, (int) ( $x + $r ), (int) ( $y + $h - $r ), $d, $d, $color );
		imagefilledellipse( $im, (int) ( $x + $w - $r ), (int) ( $y + $h - $r ), $d, $d, $color );
	}

	/**
	 * Draws a thin horizontal line.
	 *
	 * @param GdImage $im      Image.
	 * @param float   $x0      Left, design px.
	 * @param float   $x1      Right, design px.
	 * @param float   $y       Top, design px.
	 * @param string  $hex     Color.
	 * @param float   $opacity 0–1.
	 * @return void
	 */
	private function hline( GdImage $im, float $x0, float $x1, float $y, string $hex, float $opacity = 1.0 ): void {
		imagefilledrectangle(
			$im,
			(int) ( $x0 * self::SS ),
			(int) ( $y * self::SS ),
			(int) ( $x1 * self::SS ),
			(int) ( $y * self::SS + self::SS ),
			$this->color( $im, $hex, $opacity )
		);
	}

	/**
	 * Draws a vertical dark gradient from $from_y to $to_y (design px).
	 *
	 * @param GdImage $im       Image.
	 * @param int     $from_y   Gradient start (fully transparent).
	 * @param int     $to_y     Gradient end (strongest).
	 * @param float   $strength Maximum opacity 0–1.
	 * @return void
	 */
	private function scrim( GdImage $im, int $from_y, int $to_y, float $strength ): void {

		$from = $from_y * self::SS;
		$to   = $to_y * self::SS;
		$span = max( 1, $to - $from );

		for ( $y = $from; $y < $to; $y++ ) {
			// Ease-in curve for a soft upper edge.
			$t       = ( $y - $from ) / $span;
			$opacity = $strength * $t * $t;
			imageline( $im, 0, $y, imagesx( $im ), $y, $this->color( $im, '#18140E', $opacity ) );
		}
	}

	/**
	 * Draws an attachment cover-cropped over a region of the canvas.
	 * Picks the smallest intermediate size that still covers the target
	 * to keep memory usage low on shared hosting.
	 *
	 * @param GdImage $im            Image.
	 * @param int     $attachment_id Attachment ID.
	 * @param int     $w             Region width in design pixels.
	 * @param int     $h             Region height in design pixels.
	 * @return bool Whether the photo was drawn.
	 */
	private function draw_cover( GdImage $im, int $attachment_id, int $w, int $h ): bool {
		return $this->draw_cover_region( $im, $attachment_id, 0, 0, $w, $h );
	}

	/**
	 * Draws an attachment cover-cropped into a region of the canvas (design
	 * pixels). Picks the smallest intermediate size that still covers the
	 * target to keep memory usage low on shared hosting.
	 *
	 * @param GdImage $im            Image.
	 * @param int     $attachment_id Attachment ID.
	 * @param float   $dx            Region left, design px.
	 * @param float   $dy            Region top, design px.
	 * @param float   $dw            Region width, design px.
	 * @param float   $dh            Region height, design px.
	 * @return bool Whether the photo was drawn.
	 */
	private function draw_cover_region( GdImage $im, int $attachment_id, float $dx, float $dy, float $dw, float $dh ): bool {

		$dst_w = (int) round( $dw * self::SS );
		$dst_h = (int) round( $dh * self::SS );

		$file = $this->attachment_file_for_size( $attachment_id, $dst_w, $dst_h );

		if ( '' === $file || ! is_readable( $file ) ) {
			return false;
		}

		$contents = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $contents ) {
			return false;
		}

		$src = imagecreatefromstring( $contents );
		unset( $contents );

		if ( ! $src instanceof GdImage ) {
			return false;
		}

		$src_w = imagesx( $src );
		$src_h = imagesy( $src );

		// Cover crop: scale so the shorter relative side fills, crop the rest.
		$scale  = max( $dst_w / $src_w, $dst_h / $src_h );
		$crop_w = (int) round( $dst_w / $scale );
		$crop_h = (int) round( $dst_h / $scale );
		$crop_x = (int) floor( ( $src_w - $crop_w ) / 2 );
		$crop_y = (int) floor( ( $src_h - $crop_h ) / 2 );

		imagecopyresampled(
			$im,
			$src,
			(int) round( $dx * self::SS ),
			(int) round( $dy * self::SS ),
			$crop_x,
			$crop_y,
			$dst_w,
			$dst_h,
			$crop_w,
			$crop_h
		);
		imagedestroy( $src );

		return true;
	}

	/**
	 * Draws the logo attachment left-aligned with a fixed height.
	 *
	 * @param GdImage $im            Image.
	 * @param int     $attachment_id Logo attachment.
	 * @param float   $x             Left, design px.
	 * @param float   $y             Top, design px.
	 * @param int     $height        Target height in design px.
	 * @return void
	 */
	private function draw_logo( GdImage $im, int $attachment_id, float $x, float $y, int $height ): void {

		$file = get_attached_file( $attachment_id );
		if ( ! $file || ! is_readable( $file ) ) {
			return;
		}

		$contents = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$src      = $contents ? imagecreatefromstring( $contents ) : false;

		if ( ! $src instanceof GdImage ) {
			return;
		}

		imagealphablending( $im, true );

		$src_w = imagesx( $src );
		$src_h = imagesy( $src );
		$dst_h = $height * self::SS;
		$dst_w = (int) round( $src_w * $dst_h / $src_h );

		imagecopyresampled( $im, $src, (int) ( $x * self::SS ), (int) ( $y * self::SS ), 0, 0, $dst_w, $dst_h, $src_w, $src_h );
		imagedestroy( $src );
	}

	/**
	 * Display width in design pixels of the logo attachment at a target
	 * height (from the attachment metadata, so no image is decoded).
	 *
	 * @param int $attachment_id Logo attachment.
	 * @param int $height        Target height in design pixels.
	 * @return int
	 */
	private function logo_width( int $attachment_id, int $height ): int {

		$meta = wp_get_attachment_metadata( $attachment_id );

		if ( ! is_array( $meta ) || empty( $meta['width'] ) || empty( $meta['height'] ) ) {
			return $height * 3;
		}

		return (int) round( $meta['width'] * $height / $meta['height'] );
	}

	/**
	 * Finds the smallest registered image size of an attachment that still
	 * covers the requested pixel dimensions; falls back to the original.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @param int $min_w         Minimum width in real pixels.
	 * @param int $min_h         Minimum height in real pixels.
	 * @return string Absolute file path or ''.
	 */
	private function attachment_file_for_size( int $attachment_id, int $min_w, int $min_h ): string {

		$original = (string) get_attached_file( $attachment_id );
		if ( '' === $original ) {
			return '';
		}

		$meta = wp_get_attachment_metadata( $attachment_id );
		if ( ! is_array( $meta ) || empty( $meta['sizes'] ) ) {
			return $original;
		}

		$dir  = trailingslashit( dirname( $original ) );
		$best = '';
		$best_area = PHP_INT_MAX;

		foreach ( $meta['sizes'] as $size ) {
			if ( empty( $size['file'] ) || empty( $size['width'] ) || empty( $size['height'] ) ) {
				continue;
			}
			if ( $size['width'] >= $min_w && $size['height'] >= $min_h ) {
				$area = $size['width'] * $size['height'];
				if ( $area < $best_area && is_readable( $dir . $size['file'] ) ) {
					$best      = $dir . $size['file'];
					$best_area = $area;
				}
			}
		}

		return '' !== $best ? $best : $original;
	}

	/**
	 * Downsamples the supersampled canvas and writes the final JPEG.
	 *
	 * @param GdImage $im       Supersampled image (consumed).
	 * @param int     $plan_id  Meal plan post ID.
	 * @param string  $filename Target file name.
	 * @return string|WP_Error Absolute path or error.
	 */
	private function save( GdImage $im, int $plan_id, string $filename ): string|WP_Error {

		list( $w, $h ) = self::canvas_size();

		$final = imagecreatetruecolor( $w, $h );
		imagecopyresampled( $final, $im, 0, 0, 0, 0, $w, $h, $w * self::SS, $h * self::SS );
		imagedestroy( $im );

		$dir = self::get_plan_dir( $plan_id );

		if ( ! wp_mkdir_p( $dir ) ) {
			imagedestroy( $final );
			return new WP_Error(
				'mmm_instagram_dir',
				__( 'Could not create the image directory below uploads.', 'mixmymeals' )
			);
		}

		$path = trailingslashit( $dir ) . $filename;

		$ok = imagejpeg( $final, $path, 82 );
		imagedestroy( $final );

		if ( ! $ok ) {
			return new WP_Error(
				'mmm_instagram_write',
				__( 'Could not write the image file.', 'mixmymeals' )
			);
		}

		return $path;
	}
}
