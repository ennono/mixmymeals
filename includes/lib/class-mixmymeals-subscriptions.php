<?php
/**
 * Meal plan subscriptions class file.
 *
 * @package MixMyMeals Plugin/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mixmymeals_Subscriptions
 *
 * Lets visitors subscribe to a meal plan with their e-mail (and optional name)
 * and receive a one-time drip of day e-mails. The schedule (weekday + time +
 * template) is global; only the content varies by subscribed plan.
 *
 * GDPR: double opt-in, explicit consent with proof, one-click unsubscribe via
 * the shared suppression list and List-Unsubscribe header, data minimization,
 * automatic deletion of unconfirmed and expired records.
 *
 * Subscriptions are stored as the private CPT mmm_subscription. Sending runs on
 * a polling WP-Cron event (mirrors Mixmymeals_Cleanup).
 */
class Mixmymeals_Subscriptions {

	const POST_TYPE = 'mmm_subscription';
	const CRON_HOOK = 'mmm_subscription_dispatch';
	const CRON_INTERVAL = 'mmm_fifteen_minutes';

	const OPT_ENABLED       = 'mixmymeals_subscriptions_enabled';
	const OPT_SCHEDULE      = 'mixmymeals_subscription_schedule';
	const OPT_PRIVACY_URL   = 'mixmymeals_subscription_privacy_url';
	const OPT_PENDING_HOURS = 'mixmymeals_subscription_pending_hours';
	const OPT_RETENTION     = 'mixmymeals_subscription_retention_days';
	const OPT_LOG_IP        = 'mixmymeals_subscription_log_ip';

	const OPT_SUBSCRIBE_PAGE   = 'mixmymeals_subscription_subscribe_page';
	const OPT_CONFIRM_PAGE     = 'mixmymeals_subscription_confirm_page';
	const OPT_UNSUBSCRIBE_PAGE = 'mixmymeals_subscription_unsubscribe_page';
	const OPT_RATE_PAGE        = 'mixmymeals_subscription_rate_page';
	const OPT_UPLOAD_PAGE      = 'mixmymeals_subscription_upload_page';

	const OPT_APPEND_TEXT = 'mixmymeals_subscription_append_text';

	const DEFAULT_PENDING_HOURS = 48;
	const DEFAULT_RETENTION     = 365;
	const DISPATCH_BATCH        = 100;

	/**
	 * Constructor: registers the CPT, cron, endpoints, AJAX and the shortcode.
	 *
	 * @param string $plugin_file Main plugin file (for the deactivation hook).
	 */
	public function __construct( string $plugin_file = '' ) {

		add_action( 'init', array( $this, 'register_post_type' ) );

		add_filter( 'cron_schedules', array( $this, 'add_cron_interval' ) );
		add_action( 'init', array( $this, 'maybe_schedule' ) );
		add_action( self::CRON_HOOK, array( $this, 'dispatch' ) );

		// Apply rule changes to active subscriptions as soon as they are saved
		// (fires only when the schedule option actually changed). Sending still
		// happens on the cron run.
		add_action( 'update_option_' . self::OPT_SCHEDULE, array( $this, 'resync_all' ) );
		add_action( 'add_option_' . self::OPT_SCHEDULE, array( $this, 'resync_all' ) );

		add_action( 'wp_ajax_mmm_subscribe', array( $this, 'ajax_subscribe' ) );
		add_action( 'wp_ajax_nopriv_mmm_subscribe', array( $this, 'ajax_subscribe' ) );

		add_action( 'template_redirect', array( $this, 'handle_endpoints' ) );

		// The [mmm_meal_plan_subscribe] shortcode is registered centrally in
		// Mixmymeals_Shortcodes and delegates to self::render_form().

		// Admin management UI for subscription records.
		if ( is_admin() ) {
			add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'admin_columns' ) );
			add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'admin_render_column' ), 10, 2 );
			add_filter( 'manage_edit-' . self::POST_TYPE . '_sortable_columns', array( $this, 'admin_sortable_columns' ) );
			add_action( 'restrict_manage_posts', array( $this, 'admin_status_filter' ) );
			add_action( 'pre_get_posts', array( $this, 'admin_filter_query' ) );
			add_action( 'add_meta_boxes', array( $this, 'admin_meta_boxes' ) );
			add_filter( 'post_row_actions', array( $this, 'admin_row_actions' ), 10, 2 );
			add_action( 'admin_post_mmm_sub_unsubscribe', array( $this, 'admin_action_unsubscribe' ) );
			add_action( 'admin_post_mmm_sub_resend', array( $this, 'admin_action_resend' ) );
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );
			add_action( 'wp_ajax_mmm_sub_test_rule', array( $this, 'ajax_test_rule' ) );
		}

		if ( $plugin_file ) {
			register_deactivation_hook( $plugin_file, array( __CLASS__, 'unschedule' ) );
		}
	}

	/* ----------------------------------------------------------
	 * SETTINGS HELPERS
	 * ---------------------------------------------------------- */

	/**
	 * Whether subscriptions are globally enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		return 'on' === get_option( self::OPT_ENABLED, '' );
	}

	/**
	 * Built-in trigger ids mapped to their human label.
	 *
	 * @return array<string,string>
	 */
	/**
	 * The sending rules a site can configure, keyed by trigger.
	 *
	 * The wording matters more than it looks. Each rule carries its own time,
	 * defaulting to 08:00, so a label must not promise a time of day the site
	 * has not chosen — "the evening before" did, and fired at eight in the
	 * morning unless someone changed it.
	 *
	 * 'before_start' and 'evening_before' would both produce a mail on the day
	 * before the plan starts, both about its first day. When both are
	 * configured, compute_sends() drops the second one's first slot so that
	 * morning carries one mail, not two — see the note there.
	 *
	 * @return array<string,string>
	 */
	public static function trigger_labels(): array {
		return array(
			'each_day'       => __( 'Each day in the plan', 'mixmymeals' ),
			'on_start'       => __( 'On the first plan day', 'mixmymeals' ),
			'before_start'   => __( 'The day before the plan starts', 'mixmymeals' ),
			'evening_before' => __( 'The day before each plan day', 'mixmymeals' ),
			'on_end'         => __( 'On the last plan day', 'mixmymeals' ),
			'after_end'      => __( 'The day after the last plan day', 'mixmymeals' ),
			'on_confirm'     => __( 'On confirmation (immediately)', 'mixmymeals' ),
		);
	}

	/**
	 * Returns the configured sending rules as a clean list of triggers, with
	 * legacy rows migrated to the content + template split on the fly.
	 *
	 * @return array[] Each: array{ trigger:string, time:string"HH:MM"|'', subject:string, template:string }.
	 */
	public static function get_triggers(): array {

		$raw = get_option( self::OPT_SCHEDULE, array() );

		if ( ! is_array( $raw ) ) {
			return array();
		}

		$rows = array();

		foreach ( $raw as $row ) {

			if ( ! is_array( $row ) ) {
				continue;
			}

			$clean = self::normalize_rule( $row );
			if ( null !== $clean ) {
				$rows[] = $clean;
			}
		}

		return $rows;
	}

	/**
	 * Default subject for a rule whose subject field is empty (e.g. a migrated
	 * legacy rule). Picks a sensible line from the chosen template name.
	 *
	 * @param string $template Template file name.
	 * @return string Subject (may contain placeholders like {{ plan }}).
	 */
	public static function default_subject( string $template ): string {
		$subjects = array(
			'each-day.html'       => __( 'Your meals today: {{ plan }}', 'mixmymeals' ),
			'on-start.html'       => __( 'Your plan starts today: {{ plan }}', 'mixmymeals' ),
			'before-start.html'   => __( 'Your plan starts tomorrow: {{ plan }}', 'mixmymeals' ),
			'evening-before.html' => __( 'Tomorrow’s meals: {{ plan }}', 'mixmymeals' ),
			'on-end.html'         => __( 'Last day of your plan: {{ plan }}', 'mixmymeals' ),
			'after-end.html'      => __( 'That was {{ plan }}', 'mixmymeals' ),
			'on-confirm.html'     => __( 'Welcome to {{ plan }}', 'mixmymeals' ),
			'rate-recipe.html'    => __( 'How did you like the recipes? {{ plan }}', 'mixmymeals' ),
			'upload-photos.html'  => __( 'Show us your meals! {{ plan }}', 'mixmymeals' ),
		);

		return $subjects[ basename( $template ) ] ?? __( '{{ plan }}', 'mixmymeals' );
	}

	/**
	 * Whether a template value is a safe (possibly sub-foldered) .html path.
	 *
	 * Allows subfolders ("summer/each-day.html") but rejects path traversal.
	 *
	 * @param string $template Template path.
	 * @return bool
	 */
	private static function is_valid_template_name( string $template ): bool {
		return '' !== $template
			&& ! str_contains( $template, '..' )
			&& (bool) preg_match( '#^(?:[A-Za-z0-9._-]+/)*[A-Za-z0-9._-]+\.html$#', $template );
	}

	/**
	 * Validates and normalizes one raw sending-rule row into the canonical
	 * { trigger, time, subject, template } shape, migrating legacy rows
	 * (builtin:* / file:* / the earlier content+template split) to a plain
	 * template file name on the fly.
	 *
	 * Shared by get_triggers() (read) and the settings sanitizer (save) so both
	 * apply identical validation and migration.
	 *
	 * @param array $row Raw row.
	 * @return array{trigger:string,time:string,subject:string,template:string,append:string}|null
	 */
	public static function normalize_rule( array $row ): ?array {

		$trigger = isset( $row['trigger'] ) ? sanitize_key( $row['trigger'] ) : '';
		if ( ! array_key_exists( $trigger, self::trigger_labels() ) ) {
			return null;
		}

		$template = isset( $row['template'] ) ? sanitize_text_field( (string) $row['template'] ) : '';

		// A custom file override ("file:NAME.html") keeps its file name.
		if ( str_starts_with( $template, 'file:' ) ) {
			$template = substr( $template, 5 );
		}

		/*
		 * Templates that have been renamed. A stored rule still pointing at the
		 * old name would not fall back to anything — send_template() logs "not
		 * found" and returns false, so the mail simply stops going out, quietly.
		 * Mapped on read rather than migrated once, so a site restoring an older
		 * settings backup is covered too.
		 */
		$renamed = array(
			'rate-recipes.html' => 'rate-recipe.html',
		);

		$base = basename( $template );

		if ( isset( $renamed[ $base ] ) ) {
			$template = substr( $template, 0, -strlen( $base ) ) . $renamed[ $base ];
		}

		// Anything that is not a plain .html file name (legacy builtin:* values,
		// the earlier content+template split, or an empty value) falls back to the
		// template that matches this trigger.
		if ( ! self::is_valid_template_name( $template ) || 'layout.html' === basename( $template ) ) {
			$template = str_replace( '_', '-', $trigger ) . '.html';
		}

		$subject = isset( $row['subject'] ) ? sanitize_text_field( (string) $row['subject'] ) : '';
		if ( '' === $subject ) {
			$subject = self::default_subject( $template );
		}

		$time = isset( $row['time'] ) ? trim( (string) $row['time'] ) : '';

		if ( 'on_confirm' === $trigger ) {
			$time = '';
		} elseif ( ! preg_match( '/^([01]?\d|2[0-3]):[0-5]\d$/', $time ) ) {
			return null;
		}

		// Per-rule toggle: append the global text block to this mail.
		$append = empty( $row['append'] ) ? '' : 'on';

		return array(
			'trigger'  => $trigger,
			'time'     => $time,
			'subject'  => $subject,
			'template' => $template,
			'append'   => $append,
		);
	}

	/**
	 * Hours after which an unconfirmed subscription is deleted.
	 *
	 * @return int
	 */
	public static function get_pending_hours(): int {
		$h = absint( get_option( self::OPT_PENDING_HOURS, self::DEFAULT_PENDING_HOURS ) );
		return $h > 0 ? $h : self::DEFAULT_PENDING_HOURS;
	}

	/**
	 * Days after which completed/unsubscribed records are deleted.
	 *
	 * @return int
	 */
	public static function get_retention_days(): int {
		$d = absint( get_option( self::OPT_RETENTION, self::DEFAULT_RETENTION ) );
		return $d > 0 ? $d : self::DEFAULT_RETENTION;
	}

	/**
	 * Returns the admin-defined text block (rendered as ready-to-embed HTML) that
	 * rules can opt to append to their mail, or '' when no block is configured.
	 * Whether a given mail uses it is decided per rule (the "append" flag).
	 *
	 * Plain text is auto-paragraphed (line breaks kept); HTML is preserved and
	 * sanitized via wp_kses_post(). May use the standard mail placeholders.
	 *
	 * @return string
	 */
	public static function append_block(): string {

		$block = trim( (string) get_option( self::OPT_APPEND_TEXT, '' ) );
		if ( '' === $block ) {
			return '';
		}

		$html = ( wp_strip_all_tags( $block ) === $block )
			? wpautop( esc_html( $block ) ) // Plain text: keep line breaks.
			: wp_kses_post( $block );        // HTML: keep safe markup.

		return '<div class="mmm-mail-append">' . $html . '</div>';
	}

	/**
	 * Resolves a configured redirect target (page ID option) to a permalink.
	 *
	 * @param string $option Option name holding a page ID.
	 * @return string Permalink, or '' if unset/unavailable.
	 */
	private static function redirect_url( string $option ): string {

		$page_id = absint( get_option( $option, 0 ) );

		if ( $page_id > 0 && 'publish' === get_post_status( $page_id ) ) {
			$url = get_permalink( $page_id );
			if ( $url ) {
				return (string) $url;
			}
		}

		return '';
	}

	/* ----------------------------------------------------------
	 * CPT
	 * ---------------------------------------------------------- */

	/**
	 * Registers the private subscription post type.
	 *
	 * @return void
	 */
	public function register_post_type(): void {

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'          => __( 'Subscriptions', 'mixmymeals' ),
					'singular_name' => __( 'Subscription', 'mixmymeals' ),
					'menu_name'     => __( 'Subscriptions', 'mixmymeals' ),
				),
				'public'          => false,
				'show_ui'         => true,
				// Unter MixMyMeals: ein Abo hängt an keinem einzelnen Plan,
				// es ist eine laufende Zustellung. Im Planmenü las es sich wie
				// eine Eigenschaft der Pläne.
				'show_in_menu'    => 'mixmymeals_settings',
				'capability_type' => 'post',
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'    => true,
				'supports'        => array( 'title' ),
				'has_archive'     => false,
				'rewrite'         => false,
				'show_in_rest'    => false,
			)
		);
	}

	/* ----------------------------------------------------------
	 * SHORTCODE + FORM
	 * ---------------------------------------------------------- */

	/**
	 * Renders the subscribe form. Registered as the [mmm_meal_plan_subscribe]
	 * shortcode from Mixmymeals_Shortcodes.
	 *
	 * @param array|string $atts Shortcode attributes (plan_id).
	 * @return string
	 */
	public static function render_form( $atts = array() ): string {

		if ( ! self::is_enabled() ) {
			return '';
		}

		$atts    = shortcode_atts(
			array(
				'plan_id'    => 0,
				// Opt-in: when truthy, the subscriber may choose which upcoming
				// start-weekday occurrence the drip begins on. Off by default to
				// preserve the original "next occurrence after confirmation" flow.
				'pick_start' => 0,
			),
			$atts,
			'mmm_meal_plan_subscribe'
		);
		$plan_id    = (int) $atts['plan_id'];
		$pick_start = filter_var( $atts['pick_start'], FILTER_VALIDATE_BOOLEAN );

		if ( ! $plan_id && is_singular( 'mmm_meal_plan' ) ) {
			$plan_id = get_the_ID();
		}

		if ( ! $plan_id || get_post_type( $plan_id ) !== 'mmm_meal_plan' ) {
			return '';
		}

		if ( empty( self::get_triggers() ) ) {
			return '';
		}

		$privacy_url = esc_url( (string) get_option( self::OPT_PRIVACY_URL, '' ) );
		$consent     = '' !== $privacy_url
			? sprintf(
				/* translators: %s: privacy policy URL. */
				__( 'I agree to receive these e-mails and accept the <a href="%s" target="_blank" rel="noopener">privacy policy</a>. I can unsubscribe at any time.', 'mixmymeals' ),
				$privacy_url
			)
			: __( 'I agree to receive these e-mails. I can unsubscribe at any time.', 'mixmymeals' );

		ob_start();
		?>
		<form class="mmm-subscribe-form" data-plan-id="<?php echo esc_attr( $plan_id ); ?>">
			<div class="mmm-subscribe-field">
				<label><?php esc_html_e( 'Your name (optional)', 'mixmymeals' ); ?>
					<input type="text" class="mmm-subscribe-name" autocomplete="name">
				</label>
			</div>
			<div class="mmm-subscribe-field">
				<label><?php esc_html_e( 'Your e-mail', 'mixmymeals' ); ?>
					<input type="email" class="mmm-subscribe-email" required autocomplete="email">
				</label>
			</div>
			<?php
			if ( $pick_start ) :
				$mmm_starts = self::upcoming_start_dates( new Mixmymeals_Meal_Plan( $plan_id ) );
				if ( count( $mmm_starts ) > 1 ) :
					$mmm_date_fmt = (string) get_option( 'date_format' );
					$mmm_today    = ( new DateTimeImmutable( 'now' ) )->setTimezone( wp_timezone() )->setTime( 0, 0 );
					?>
					<div class="mmm-subscribe-field">
						<label><?php esc_html_e( 'Start date', 'mixmymeals' ); ?>
							<select class="mmm-subscribe-start">
								<?php foreach ( $mmm_starts as $mmm_d ) : ?>
									<option value="<?php echo esc_attr( $mmm_d->format( 'Y-m-d' ) ); ?>">
										<?php
										if ( $mmm_d < $mmm_today ) {
											printf(
												/* translators: %s: start date of the already-running week */
												esc_html__( 'This week (started %s) — skips past days', 'mixmymeals' ),
												esc_html( wp_date( $mmm_date_fmt, $mmm_d->getTimestamp() ) )
											);
										} else {
											echo esc_html( wp_date( $mmm_date_fmt, $mmm_d->getTimestamp() ) );
										}
										?>
									</option>
								<?php endforeach; ?>
							</select>
						</label>
					</div>
					<?php
				endif;
			endif;
			?>
			<div class="mmm-subscribe-consent">
				<label>
					<input type="checkbox" class="mmm-subscribe-agree" required>
					<span><?php echo wp_kses_post( $consent ); ?></span>
				</label>
			</div>
			<?php
			if ( class_exists( 'Mixmymeals_Shortcodes' ) ) {
				echo Mixmymeals_Shortcodes::input_notice( 'subscribe' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
			}
			?>
			<button type="submit" class="mmm-subscribe-submit" data-nonce="<?php echo esc_attr( wp_create_nonce( 'mmm_subscribe' ) ); ?>">
				<?php esc_html_e( 'Subscribe', 'mixmymeals' ); ?>
			</button>
			<p class="mmm-subscribe-message" aria-live="polite"></p>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/* ----------------------------------------------------------
	 * AJAX: SUBSCRIBE (double opt-in step 1)
	 * ---------------------------------------------------------- */

	/**
	 * Handles the subscribe form submission: creates a pending subscription and
	 * sends the double opt-in confirmation e-mail.
	 *
	 * @return void
	 */
	public function ajax_subscribe(): void {

		check_ajax_referer( 'mmm_subscribe', 'nonce' );

		if ( ! self::is_enabled() ) {
			wp_send_json_error( array( 'message' => __( 'Subscriptions are currently disabled.', 'mixmymeals' ) ), 403 );
		}

		$plan_id = isset( $_POST['plan_id'] ) ? absint( wp_unslash( $_POST['plan_id'] ) ) : 0;
		$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$agree   = isset( $_POST['agree'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['agree'] ) );
		$start   = isset( $_POST['start'] ) ? sanitize_text_field( wp_unslash( $_POST['start'] ) ) : '';

		if ( get_post_type( $plan_id ) !== 'mmm_meal_plan' || 'publish' !== get_post_status( $plan_id ) ) {
			wp_send_json_error( array( 'message' => __( 'This meal plan is not available.', 'mixmymeals' ) ), 400 );
		}

		if ( '' === $email || ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid e-mail address.', 'mixmymeals' ) ), 400 );
		}

		if ( ! $agree ) {
			wp_send_json_error( array( 'message' => __( 'Please confirm your consent to continue.', 'mixmymeals' ) ), 400 );
		}

		// Rate limit per IP.
		$ip     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$rl_key = 'mmm_sub_rl_' . md5( $ip );
		$count  = (int) get_transient( $rl_key );
		if ( $count >= (int) apply_filters( 'mixmymeals_subscribe_rate_limit_per_hour', 5 ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Please try again later.', 'mixmymeals' ) ), 429 );
		}
		set_transient( $rl_key, $count + 1, HOUR_IN_SECONDS );

		$token = wp_generate_password( 40, false, false );

		$sub_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $email . ' → ' . get_the_title( $plan_id ),
			),
			true
		);

		if ( is_wp_error( $sub_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not create the subscription. Please try again.', 'mixmymeals' ) ), 500 );
		}

		update_post_meta( $sub_id, '_mmm_sub_plan_id', $plan_id );
		update_post_meta( $sub_id, '_mmm_sub_email', strtolower( $email ) );
		update_post_meta( $sub_id, '_mmm_sub_name', $name );
		update_post_meta( $sub_id, '_mmm_sub_status', 'pending' );
		update_post_meta( $sub_id, '_mmm_sub_token', $token );
		update_post_meta( $sub_id, '_mmm_sub_consent_time', time() );

		// Optional subscriber-chosen start day (only stored when valid; the drip
		// otherwise falls back to the next start weekday after confirmation).
		$start_anchor = self::validated_start_anchor( $plan_id, $start );
		if ( $start_anchor > 0 ) {
			update_post_meta( $sub_id, '_mmm_sub_start_anchor', $start_anchor );
		}

		if ( 'on' === get_option( self::OPT_LOG_IP, '' ) ) {
			update_post_meta( $sub_id, '_mmm_sub_consent_ip', $ip );
		}

		$this->send_confirmation_mail( $sub_id );

		$response = array(
			'message' => __( 'Almost done! Please check your inbox and confirm your subscription.', 'mixmymeals' ),
		);

		$redirect = self::redirect_url( self::OPT_SUBSCRIBE_PAGE );
		if ( '' !== $redirect ) {
			$response['redirect'] = $redirect;
		}

		wp_send_json_success( $response );
	}

	/* ----------------------------------------------------------
	 * ENDPOINTS: CONFIRM + UNSUBSCRIBE
	 * ---------------------------------------------------------- */

	/**
	 * Handles the token-based confirm/unsubscribe links.
	 *
	 * @return void
	 */
	public function handle_endpoints(): void {

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- token-authenticated public links.
		if ( ! isset( $_GET['mmm_sub'] ) || ! isset( $_GET['token'] ) ) {
			return;
		}

		$action = sanitize_key( wp_unslash( $_GET['mmm_sub'] ) );
		$token  = sanitize_text_field( wp_unslash( $_GET['token'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$sub_id = $this->find_by_token( $token );

		if ( ! $sub_id ) {
			$this->finish( __( 'This link is invalid or has expired.', 'mixmymeals' ) );
		}

		if ( 'confirm' === $action ) {
			$this->confirm( $sub_id );
		} elseif ( 'unsubscribe' === $action ) {
			$this->unsubscribe( $sub_id );
		}

		$this->finish( __( 'Nothing to do.', 'mixmymeals' ) );
	}

	/**
	 * Confirms a pending subscription and computes its drip schedule.
	 *
	 * @param int $sub_id Subscription post ID.
	 * @return void
	 */
	private function confirm( int $sub_id ): void {

		$status = (string) get_post_meta( $sub_id, '_mmm_sub_status', true );

		if ( 'confirmed' === $status || 'completed' === $status ) {
			$this->finish_confirmed( __( 'Your subscription is already confirmed. Thank you!', 'mixmymeals' ) );
		}

		if ( 'pending' !== $status ) {
			$this->finish( __( 'This subscription can no longer be confirmed.', 'mixmymeals' ) );
		}

		$plan_id = (int) get_post_meta( $sub_id, '_mmm_sub_plan_id', true );

		if ( get_post_type( $plan_id ) !== 'mmm_meal_plan' ) {
			$this->finish( __( 'The meal plan is no longer available.', 'mixmymeals' ) );
		}

		$anchor    = time();
		$plan      = new Mixmymeals_Meal_Plan( $plan_id );
		$day_range = $plan->get_day_range();
		$first_day = ! empty( $day_range ) ? (int) $day_range[0] : 1;

		// Use the subscriber-chosen start day as the drip anchor when one was
		// set. A past anchor is kept as long as its plan window still covers
		// today — that is the "start in the running week" case, where the
		// future-only queueing below simply skips the already-past days. Only
		// a stale choice (window fully over) or no choice falls back to the
		// confirmation time (original behaviour). plan_start_date() snaps any
		// anchor onto the start weekday, so a valid chosen day maps to itself.
		$start_anchor = (int) get_post_meta( $sub_id, '_mmm_sub_start_anchor', true );
		if ( $start_anchor > 0 && $start_anchor < $anchor ) {
			$window_end = $this->plan_start_date( $plan, $start_anchor )
				->modify( '+' . ( max( 1, count( $day_range ) ) - 1 ) . ' days' )
				->setTime( 23, 59, 59 )->getTimestamp();
			if ( $anchor > $window_end ) {
				$start_anchor = $anchor;
			}
		} elseif ( $start_anchor <= 0 ) {
			$start_anchor = $anchor;
		}

		// Future-only initial schedule (drops slots whose time already passed).
		$desired = $this->compute_sends( $plan_id, $start_anchor );
		$sends   = $this->sync_sends( array(), $desired, $anchor );

		update_post_meta( $sub_id, '_mmm_sub_status', 'confirmed' );
		update_post_meta( $sub_id, '_mmm_sub_confirm_time', $anchor );
		update_post_meta( $sub_id, '_mmm_sub_start_anchor', $start_anchor );
		update_post_meta( $sub_id, '_mmm_sub_sends', $sends );

		// Re-consent: lift any previous opt-out for this address.
		$email = (string) get_post_meta( $sub_id, '_mmm_sub_email', true );
		if ( $email && class_exists( 'Mixmymeals_Mailer' ) ) {
			Mixmymeals_Mailer::remove_suppression( $email );

			// Fire the immediate "on confirmation" mails now and record them as
			// sent; the day-anchored sends are handled by the cron dispatcher.
			$this->send_on_confirm_mails( $sub_id, $plan_id, $email, $first_day );
			$this->dispatch_subscription( $sub_id, $anchor );
		}

		$this->finish_confirmed( __( 'Thank you! Your subscription is now confirmed.', 'mixmymeals' ) );
	}

	/**
	 * Ends a successful confirmation: redirects to the configured page if set,
	 * otherwise shows the built-in confirmation page.
	 *
	 * @param string $message Fallback message for the built-in page.
	 * @return void
	 */
	private function finish_confirmed( string $message ): void {

		$redirect = self::redirect_url( self::OPT_CONFIRM_PAGE );

		if ( '' !== $redirect ) {
			wp_safe_redirect( $redirect );
			exit;
		}

		$this->finish( $message, true );
	}

	/**
	 * Unsubscribes a subscription and suppresses the address.
	 *
	 * @param int $sub_id Subscription post ID.
	 * @return void
	 */
	private function unsubscribe( int $sub_id ): void {

		$this->mark_unsubscribed( $sub_id );

		$this->finish_unsubscribed( __( 'You have been unsubscribed. Sorry to see you go!', 'mixmymeals' ) );
	}

	/**
	 * Ends an unsubscribe: redirects to the configured page if set, otherwise
	 * shows the built-in confirmation page.
	 *
	 * @param string $message Fallback message for the built-in page.
	 * @return void
	 */
	private function finish_unsubscribed( string $message ): void {

		$redirect = self::redirect_url( self::OPT_UNSUBSCRIBE_PAGE );

		if ( '' !== $redirect ) {
			wp_safe_redirect( $redirect );
			exit;
		}

		$this->finish( $message, true );
	}

	/**
	 * Marks a subscription as unsubscribed and suppresses its address.
	 *
	 * Shared by the public token endpoint and the admin row action.
	 *
	 * @param int $sub_id Subscription post ID.
	 * @return void
	 */
	private function mark_unsubscribed( int $sub_id ): void {

		update_post_meta( $sub_id, '_mmm_sub_status', 'unsubscribed' );
		update_post_meta( $sub_id, '_mmm_sub_sends', array() );

		$email = (string) get_post_meta( $sub_id, '_mmm_sub_email', true );
		if ( $email && class_exists( 'Mixmymeals_Mailer' ) ) {
			Mixmymeals_Mailer::add_suppression( $email );
		}
	}

	/**
	 * Finds a subscription by its token.
	 *
	 * @param string $token Token.
	 * @return int Subscription ID or 0.
	 */
	private function find_by_token( string $token ): int {
		return self::find_subscription_by_token( $token );
	}

	/**
	 * Outputs a simple, themed end page and exits.
	 *
	 * @param string $message  Message to show.
	 * @param bool   $success  Whether this is a success message.
	 * @return void
	 */
	private function finish( string $message, bool $success = false ): void {

		$title = $success
			? __( 'Subscription', 'mixmymeals' )
			: __( 'Subscription', 'mixmymeals' );

		wp_die(
			esc_html( $message ),
			esc_html( $title ),
			array(
				'response'  => 200,
				'back_link' => true,
			)
		);
	}

	/* ----------------------------------------------------------
	 * SUBSCRIBER RATINGS
	 * ---------------------------------------------------------- */

	/**
	 * Resolves a subscription token to its post ID. Public so the rating-form
	 * shortcode (Mixmymeals_Shortcodes) can authenticate the link.
	 *
	 * @param string $token Token.
	 * @return int Subscription ID or 0.
	 */
	public static function find_subscription_by_token( string $token ): int {

		if ( '' === $token ) {
			return 0;
		}

		$ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'     => array(
					array(
						'key'   => '_mmm_sub_token',
						'value' => $token,
					),
				),
			)
		);

		return ! empty( $ids ) ? (int) $ids[0] : 0;
	}

	/**
	 * Whether a subscription may submit ratings (confirmed or completed).
	 *
	 * @param int $sub_id Subscription post ID.
	 * @return bool
	 */
	public static function subscription_is_active( int $sub_id ): bool {
		$status = (string) get_post_meta( $sub_id, '_mmm_sub_status', true );
		return in_array( $status, array( 'confirmed', 'completed' ), true );
	}

	/**
	 * Whether a recipe is part of the subscription's plan (the recipes the
	 * subscriber was told to cook).
	 *
	 * @param int $sub_id    Subscription post ID.
	 * @param int $recipe_id Recipe post ID.
	 * @return bool
	 */
	public static function subscription_has_recipe( int $sub_id, int $recipe_id ): bool {

		$plan_id = (int) get_post_meta( $sub_id, '_mmm_sub_plan_id', true );
		if ( get_post_type( $plan_id ) !== 'mmm_meal_plan' ) {
			return false;
		}

		$plan = new Mixmymeals_Meal_Plan( $plan_id );
		foreach ( $plan->get_meals() as $meal_post ) {
			$meal = new Mixmymeals_Meal( $meal_post->ID );
			if ( (int) $meal->get_recipe_id() === $recipe_id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns the subscriber's display name and e-mail for attributing a rating.
	 *
	 * @param int $sub_id Subscription post ID.
	 * @return array{0:string,1:string} [name, email]
	 */
	public static function subscriber_identity( int $sub_id ): array {
		return array(
			(string) get_post_meta( $sub_id, '_mmm_sub_name', true ),
			(string) get_post_meta( $sub_id, '_mmm_sub_email', true ),
		);
	}

	/* ----------------------------------------------------------
	 * DRIP COMPUTATION
	 * ---------------------------------------------------------- */

	/**
	 * Lists the selectable start dates (site time): the running week when its
	 * plan window still covers today, plus the next $count occurrences of the
	 * plan's start weekday on or after today. Used to let the subscriber choose
	 * which week to begin (opt-in via the shortcode's pick_start attribute).
	 *
	 * Choosing the running week starts the drip mid-week: the future-only
	 * queueing in sync_sends() silently skips the mails of the already-past
	 * plan days, so no mail is ever scheduled retroactively.
	 *
	 * @param Mixmymeals_Meal_Plan $plan  Plan.
	 * @param int                  $count How many upcoming dates to offer.
	 * @return DateTimeImmutable[] Midnight (site time) of each occurrence.
	 */
	private static function upcoming_start_dates( Mixmymeals_Meal_Plan $plan, int $count = 3 ): array {

		$tz            = wp_timezone();
		$today         = ( new DateTimeImmutable( 'now' ) )->setTimezone( $tz )->setTime( 0, 0 );
		$start_weekday = max( 1, min( 7, (int) $plan->get_start_day() ) );
		$diff          = ( $start_weekday - (int) $today->format( 'N' ) + 7 ) % 7;
		$first         = $today->modify( "+{$diff} days" );

		$dates = array();

		// The running week: its start weekday already passed ($diff > 0 means
		// $first is next week's start), but the plan window still covers today.
		if ( $diff > 0 ) {
			$current  = $first->modify( '-7 days' );
			$last_day = $current->modify( '+' . ( max( 1, count( $plan->get_day_range() ) ) - 1 ) . ' days' );
			if ( $last_day >= $today ) {
				$dates[] = $current;
			}
		}

		for ( $i = 0; $i < max( 1, $count ); $i++ ) {
			$dates[] = $first->modify( '+' . ( $i * 7 ) . ' days' );
		}

		return $dates;
	}

	/**
	 * Validates a submitted start date against the offered occurrences.
	 *
	 * @param int    $plan_id Meal plan ID.
	 * @param string $ymd     Submitted date in Y-m-d (site time).
	 * @return int Midnight timestamp (site time) or 0 if invalid / not offered.
	 */
	private static function validated_start_anchor( int $plan_id, string $ymd ): int {

		if ( '' === $ymd || get_post_type( $plan_id ) !== 'mmm_meal_plan' ) {
			return 0;
		}

		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $ymd, wp_timezone() );
		if ( ! $date ) {
			return 0;
		}

		$plan = new Mixmymeals_Meal_Plan( $plan_id );
		foreach ( self::upcoming_start_dates( $plan ) as $opt ) {
			if ( $opt->format( 'Y-m-d' ) === $date->format( 'Y-m-d' ) ) {
				return $date->getTimestamp();
			}
		}

		return 0;
	}

	/**
	 * Resolves the plan's first calendar day for a given anchor date.
	 *
	 * The plan is anchored to the next occurrence of its start weekday on or
	 * after the anchor date, so the result stays stable across recomputations as
	 * long as the same anchor (the confirmation time) is passed.
	 *
	 * @param Mixmymeals_Meal_Plan $plan   Plan.
	 * @param int                  $anchor Anchor timestamp (e.g. confirmation time).
	 * @return DateTimeImmutable Midnight of the plan's first day in site time.
	 */
	private function plan_start_date( Mixmymeals_Meal_Plan $plan, int $anchor ): DateTimeImmutable {

		$tz    = wp_timezone();
		$today = ( new DateTimeImmutable( '@' . $anchor ) )->setTimezone( $tz )->setTime( 0, 0 );

		$start_weekday = max( 1, min( 7, (int) $plan->get_start_day() ) );
		$diff          = ( $start_weekday - (int) $today->format( 'N' ) + 7 ) % 7;

		return $today->modify( "+{$diff} days" );
	}

	/**
	 * Builds one send entry from a trigger row.
	 *
	 * @param array $trigger Trigger row.
	 * @param int   $ts      Send timestamp.
	 * @param int   $day     Plan weekday the content is about (1-7).
	 * @return array{ ts:int, subject:string, template:string, trigger:string, day:int, sent:bool }
	 */
	private function make_send( array $trigger, int $ts, int $day ): array {
		return array(
			'ts'       => $ts,
			'subject'  => $trigger['subject'],
			'template' => $trigger['template'],
			'trigger'  => $trigger['trigger'],
			'time'     => $trigger['time'] ?? '',
			'day'      => $day,
			'append'   => $trigger['append'] ?? '',
			'sent'     => false,
		);
	}

	/**
	 * Stable identity of a send slot. Must be unique per *rule* and day, otherwise
	 * two rules sharing a trigger (e.g. a meals mail and a rating mail both on
	 * "each day in the plan") would collide on the same key — their stored "sent"
	 * state would overwrite each other in sync_sends() and the mail would be
	 * resent on every cron run. Hence trigger + template + time + day.
	 *
	 * @param array $send Send entry.
	 * @return string
	 */
	private static function send_key( array $send ): string {
		return ( $send['trigger'] ?? '' )
			. '|' . ( $send['template'] ?? '' )
			. '|' . ( $send['time'] ?? '' )
			. '|' . (int) ( $send['day'] ?? 0 );
	}

	/**
	 * Builds the ideal, time-anchored send list for a plan from the *current*
	 * triggers, relative to the given anchor date.
	 *
	 * This is the "what the rules say" set; whether a slot is actually queued or
	 * skipped (future-only) and whether it was already delivered is decided in
	 * sync_sends(). The immediate on_confirm trigger fires only at confirmation
	 * (see send_on_confirm_mails()) and is intentionally not produced here.
	 *
	 * @param int      $plan_id     Meal plan ID.
	 * @param int|null $anchor_time Anchor timestamp; defaults to now.
	 * @return array[] Each: array{ ts:int, subject:string, template:string, trigger:string, day:int, sent:bool }.
	 */
	private function compute_sends( int $plan_id, ?int $anchor_time = null ): array {

		$triggers = self::get_triggers();
		if ( empty( $triggers ) ) {
			return array();
		}

		$plan      = new Mixmymeals_Meal_Plan( $plan_id );
		$day_range = $plan->get_day_range();
		if ( empty( $day_range ) ) {
			return array();
		}

		$plan_start = $this->plan_start_date( $plan, $anchor_time ?? time() );

		$has_before_start = (bool) array_filter(
			$triggers,
			static fn( $t ) => 'before_start' === ( $t['trigger'] ?? '' )
		);

		$sends = array();

		foreach ( $triggers as $trigger ) {

			switch ( $trigger['trigger'] ) {

				case 'each_day':
					list( $h, $m ) = array_map( 'intval', explode( ':', $trigger['time'] ) );
					foreach ( $day_range as $i => $weekday ) {
						$dt      = $plan_start->modify( "+{$i} days" )->setTime( $h, $m );
						$sends[] = $this->make_send( $trigger, $dt->getTimestamp(), (int) $weekday );
					}
					break;

				case 'on_start':
					list( $h, $m ) = array_map( 'intval', explode( ':', $trigger['time'] ) );
					$dt            = $plan_start->setTime( $h, $m );
					$sends[]       = $this->make_send( $trigger, $dt->getTimestamp(), (int) $day_range[0] );
					break;

				case 'before_start':
					list( $h, $m ) = array_map( 'intval', explode( ':', $trigger['time'] ) );
					$dt            = $plan_start->modify( '-1 day' )->setTime( $h, $m );
					$sends[]       = $this->make_send( $trigger, $dt->getTimestamp(), (int) $day_range[0] );
					break;

				case 'on_end':
					list( $h, $m ) = array_map( 'intval', explode( ':', $trigger['time'] ) );
					$last          = count( $day_range ) - 1;
					$dt            = $plan_start->modify( "+{$last} days" )->setTime( $h, $m );
					$sends[]       = $this->make_send( $trigger, $dt->getTimestamp(), (int) $day_range[ $last ] );
					break;

				case 'after_end':
					list( $h, $m ) = array_map( 'intval', explode( ':', $trigger['time'] ) );
					$after         = count( $day_range );

					/*
					 * Carries the last plan day, not the day it is sent on:
					 * the plan is over by then, so there is no "today" to talk
					 * about. What the placeholders resolve to is the day the
					 * subscriber last cooked — which is what a mail asking for
					 * a rating or a photo needs.
					 */
					$dt      = $plan_start->modify( "+{$after} days" )->setTime( $h, $m );
					$sends[] = $this->make_send( $trigger, $dt->getTimestamp(), (int) $day_range[ $after - 1 ] );
					break;

				case 'evening_before':
					list( $h, $m ) = array_map( 'intval', explode( ':', $trigger['time'] ) );
					foreach ( $day_range as $i => $weekday ) {

						/*
						 * The slot for the first plan day falls on the day
						 * before the plan starts — exactly where before_start
						 * already sits, and about the same day. A site running
						 * both sent two mails that morning covering the same
						 * ground, so the more specific one wins and this rule
						 * starts a day later.
						 *
						 * Only when before_start is actually configured: on its
						 * own, this rule has to keep its first slot, or nobody
						 * hears about day one before it arrives.
						 */
						if ( 0 === $i && $has_before_start ) {
							continue;
						}

						$dt      = $plan_start->modify( "+{$i} days" )->modify( '-1 day' )->setTime( $h, $m );
						$sends[] = $this->make_send( $trigger, $dt->getTimestamp(), (int) $weekday );
					}
					break;
			}
		}

		usort( $sends, static fn( $a, $b ) => $a['ts'] <=> $b['ts'] );

		return $sends;
	}

	/**
	 * Merges a freshly computed ideal schedule with what was already stored so
	 * that rule changes and newly added mails are picked up without ever
	 * resending a slot that has already gone out.
	 *
	 * Rules:
	 *  - Already-sent slot: kept untouched, never resent (later edits ignored).
	 *  - Known, unsent slot: time/template updated to the current rule.
	 *  - Brand-new slot: queued only when its moment is still in the future
	 *    (future-only; no retroactive bursts).
	 *  - Removed rule: its unsent slots disappear; sent ones are kept as history.
	 *
	 * @param array $stored  Previously stored send entries.
	 * @param array $desired Freshly computed ideal send entries.
	 * @param int   $now     Current timestamp.
	 * @return array[] Merged send entries.
	 */
	private function sync_sends( array $stored, array $desired, int $now ): array {

		$stored_by_key = array();
		foreach ( $stored as $s ) {
			if ( is_array( $s ) ) {
				$stored_by_key[ self::send_key( $s ) ] = $s;
			}
		}

		$merged       = array();
		$desired_keys = array();

		foreach ( $desired as $d ) {

			$key                  = self::send_key( $d );
			$desired_keys[ $key ] = true;

			if ( isset( $stored_by_key[ $key ] ) ) {

				$prev = $stored_by_key[ $key ];

				if ( ! empty( $prev['sent'] ) ) {
					$merged[] = $prev; // Delivered already: keep, never resend.
				} else {
					$merged[] = $d; // Known but pending: apply current time/template.
				}
				continue;
			}

			if ( (int) $d['ts'] > $now ) {
				$merged[] = $d; // Brand-new and still upcoming: queue it.
			}
			// Brand-new but already past: skipped (future-only).
		}

		// Keep delivered slots whose rule was removed, as history.
		foreach ( $stored_by_key as $key => $s ) {
			if ( ! isset( $desired_keys[ $key ] ) && ! empty( $s['sent'] ) ) {
				$merged[] = $s;
			}
		}

		usort( $merged, static fn( $a, $b ) => ( (int) $a['ts'] ) <=> ( (int) $b['ts'] ) );

		return $merged;
	}

	/* ----------------------------------------------------------
	 * CRON DISPATCH
	 * ---------------------------------------------------------- */

	/**
	 * Adds the 15-minute cron interval.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public function add_cron_interval( array $schedules ): array {
		$schedules[ self::CRON_INTERVAL ] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 15 minutes (MixMyMeals)', 'mixmymeals' ),
		);
		return $schedules;
	}

	/**
	 * Schedules the dispatch event when enabled.
	 *
	 * @return void
	 */
	public function maybe_schedule(): void {

		$scheduled = wp_next_scheduled( self::CRON_HOOK );

		if ( self::is_enabled() && ! $scheduled ) {
			wp_schedule_event( time(), self::CRON_INTERVAL, self::CRON_HOOK );
		} elseif ( ! self::is_enabled() && $scheduled ) {
			wp_unschedule_event( $scheduled, self::CRON_HOOK );
		}
	}

	/**
	 * Clears the cron event (deactivation).
	 *
	 * @return void
	 */
	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Cron callback: sends due drip e-mails and prunes old/expired records.
	 *
	 * @return void
	 */
	public function dispatch(): void {

		$this->prune();

		if ( ! self::is_enabled() || ! class_exists( 'Mixmymeals_Mailer' ) ) {
			return;
		}

		$now = time();

		$ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => self::DISPATCH_BATCH,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'     => array(
					array(
						'key'   => '_mmm_sub_status',
						'value' => 'confirmed',
					),
				),
			)
		);

		foreach ( $ids as $sub_id ) {
			$this->dispatch_subscription( (int) $sub_id, $now );
		}
	}

	/**
	 * Sends all due, unsent e-mails for one subscription.
	 *
	 * @param int $sub_id Subscription ID.
	 * @param int $now    Current timestamp.
	 * @return void
	 */
	private function dispatch_subscription( int $sub_id, int $now ): void {

		$plan_id = (int) get_post_meta( $sub_id, '_mmm_sub_plan_id', true );
		$email   = (string) get_post_meta( $sub_id, '_mmm_sub_email', true );

		if ( get_post_type( $plan_id ) !== 'mmm_meal_plan' || 'publish' !== get_post_status( $plan_id ) ) {
			return; // Plan gone/unpublished: hold off (do not lose the record).
		}

		$plan      = new Mixmymeals_Meal_Plan( $plan_id );
		$day_range = $plan->get_day_range();
		if ( empty( $day_range ) ) {
			return;
		}

		// Anchor on the subscriber-chosen start day when set, else the
		// confirmation time, else now — must match confirm()/resync so the
		// recompute does not shift the plan back to the next start weekday.
		$anchor = (int) get_post_meta( $sub_id, '_mmm_sub_start_anchor', true );
		if ( $anchor <= 0 ) {
			$anchor = (int) get_post_meta( $sub_id, '_mmm_sub_confirm_time', true );
		}
		if ( $anchor <= 0 ) {
			$anchor = $now;
		}
		$plan_start = $this->plan_start_date( $plan, $anchor );
		$plan_end   = $plan_start->modify( '+' . ( count( $day_range ) - 1 ) . ' days' )
			->setTime( 23, 59, 59 )->getTimestamp();

		// Recompute the ideal schedule from the current rules and merge in the
		// stored "already sent" state, so edits and added mails take effect
		// without ever resending a delivered mail.
		$stored  = get_post_meta( $sub_id, '_mmm_sub_sends', true );
		$stored  = is_array( $stored ) ? $stored : array();
		$desired = $this->compute_sends( $plan_id, $anchor );
		$sends   = $this->sync_sends( $stored, $desired, $now );

		$remaining = 0;

		foreach ( $sends as $i => $send ) {

			if ( ! empty( $send['sent'] ) ) {
				continue;
			}

			if ( (int) $send['ts'] > $now ) {
				++$remaining;
				continue;
			}

			$ok = $this->send_drip_mail(
				$sub_id,
				$plan_id,
				$email,
				(int) $send['day'],
				(string) ( $send['subject'] ?? '' ),
				(string) ( $send['template'] ?? '' ),
				'on' === ( $send['append'] ?? '' )
			);

			if ( $ok ) {
				$sends[ $i ]['sent'] = true;
			} else {
				++$remaining; // Retry next run.
			}
		}

		update_post_meta( $sub_id, '_mmm_sub_sends', $sends );

		// Complete only once the plan window is fully over and nothing is
		// pending, so rule changes keep reaching the subscription while it runs.
		if ( 0 === $remaining && $now > $plan_end ) {
			update_post_meta( $sub_id, '_mmm_sub_status', 'completed' );
		}
	}

	/**
	 * Sends the immediate "on confirmation" mails once and records them as sent.
	 *
	 * These fire only at confirmation time, never retroactively, so they are
	 * kept out of the recomputed drip schedule and added here as history.
	 *
	 * @param int    $sub_id  Subscription ID.
	 * @param int    $plan_id Plan ID.
	 * @param string $email   Recipient.
	 * @param int    $day     Plan weekday used for the content (first plan day).
	 * @return void
	 */
	private function send_on_confirm_mails( int $sub_id, int $plan_id, string $email, int $day ): void {

		$sends = get_post_meta( $sub_id, '_mmm_sub_sends', true );
		$sends = is_array( $sends ) ? $sends : array();

		foreach ( self::get_triggers() as $trigger ) {

			if ( 'on_confirm' !== $trigger['trigger'] ) {
				continue;
			}

			$ok = $this->send_drip_mail( $sub_id, $plan_id, $email, $day, (string) $trigger['subject'], (string) $trigger['template'], 'on' === ( $trigger['append'] ?? '' ) );

			if ( $ok ) {
				$sends[] = array(
					'ts'       => time(),
					'subject'  => $trigger['subject'],
					'template' => $trigger['template'],
					'trigger'  => 'on_confirm',
					'day'      => $day,
					'append'   => $trigger['append'] ?? '',
					'sent'     => true,
				);
			}
		}

		update_post_meta( $sub_id, '_mmm_sub_sends', $sends );
	}

	/**
	 * Applies the current sending rules to all active subscriptions at once.
	 *
	 * Hooked to the schedule option update so edits and newly added mails take
	 * effect immediately; the cron dispatcher still performs the actual sending.
	 *
	 * @return void
	 */
	public function resync_all(): void {

		$ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'     => array(
					array(
						'key'   => '_mmm_sub_status',
						'value' => 'confirmed',
					),
				),
			)
		);

		foreach ( $ids as $sub_id ) {
			$this->resync_subscription( (int) $sub_id );
		}
	}

	/**
	 * Recomputes one subscription's schedule from the current rules and stores
	 * it, without sending anything. Delivery still happens on the cron run.
	 *
	 * @param int $sub_id Subscription ID.
	 * @return void
	 */
	private function resync_subscription( int $sub_id ): void {

		$plan_id = (int) get_post_meta( $sub_id, '_mmm_sub_plan_id', true );

		if ( get_post_type( $plan_id ) !== 'mmm_meal_plan' || 'publish' !== get_post_status( $plan_id ) ) {
			return;
		}

		$anchor = (int) get_post_meta( $sub_id, '_mmm_sub_confirm_time', true );
		$anchor = $anchor > 0 ? $anchor : time();

		// Keep the subscriber-chosen start day (if any) stable across rule edits;
		// fall back to the confirmation anchor for older/unchosen subscriptions.
		$start_anchor = (int) get_post_meta( $sub_id, '_mmm_sub_start_anchor', true );
		$start_anchor = $start_anchor > 0 ? $start_anchor : $anchor;

		$stored  = get_post_meta( $sub_id, '_mmm_sub_sends', true );
		$stored  = is_array( $stored ) ? $stored : array();
		$desired = $this->compute_sends( $plan_id, $start_anchor );
		$sends   = $this->sync_sends( $stored, $desired, time() );

		update_post_meta( $sub_id, '_mmm_sub_sends', $sends );
	}

	/* ----------------------------------------------------------
	 * MAIL SENDING
	 * ---------------------------------------------------------- */

	/**
	 * Sends the double opt-in confirmation e-mail.
	 *
	 * @param int $sub_id Subscription ID.
	 * @return void
	 */
	private function send_confirmation_mail( int $sub_id ): void {

		if ( ! class_exists( 'Mixmymeals_Mailer' ) ) {
			return;
		}

		$email   = (string) get_post_meta( $sub_id, '_mmm_sub_email', true );
		$name    = (string) get_post_meta( $sub_id, '_mmm_sub_name', true );
		$token   = (string) get_post_meta( $sub_id, '_mmm_sub_token', true );
		$plan_id = (int) get_post_meta( $sub_id, '_mmm_sub_plan_id', true );

		$confirm_url = $this->endpoint_url( 'confirm', $token );
		$greeting    = '' !== $name ? $name : __( 'Hello', 'mixmymeals' );
		$plan_title  = get_the_title( $plan_id );

		$body  = '<h1>' . esc_html(
			sprintf(
				/* translators: %s: subscriber name. */
				__( '%s, please confirm your subscription', 'mixmymeals' ),
				$greeting
			)
		) . '</h1>';
		$body .= '<p>' . esc_html(
			sprintf(
				/* translators: %s: meal plan title. */
				__( 'You asked to receive e-mails for the meal plan “%s”. Please confirm your e-mail address to start receiving them.', 'mixmymeals' ),
				$plan_title
			)
		) . '</p>';
		$body .= '<p><a href="' . esc_url( $confirm_url )
			. '" style="display:inline-block;padding:11px 18px;border-radius:999px;background:#5C9E2E;color:#ffffff;font-weight:800;text-decoration:none;">'
			. esc_html__( 'Confirm subscription', 'mixmymeals' ) . '</a></p>';
		$body .= '<p>'
			. esc_html__( 'If you did not request this, simply ignore this e-mail — no e-mails will be sent without your confirmation.', 'mixmymeals' )
			. '</p>';

		Mixmymeals_Mailer::send_template(
			$email,
			sprintf(
				/* translators: %s: site name. */
				__( '[%s] Please confirm your subscription', 'mixmymeals' ),
				wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES )
			),
			$body,
			array(),
			array(
				'inline'        => true,
				'layout'        => 'layout.html',
				'transactional' => true,
				'force'         => true, // User just opted in; must reach them.
			)
		);
	}

	/**
	 * Sends one drip day e-mail: the rule's subject + template, filled with the
	 * shared set of plan placeholders.
	 *
	 * @param int    $sub_id   Subscription ID.
	 * @param int    $plan_id  Plan ID.
	 * @param string $email    Recipient.
	 * @param int    $day      Weekday (1-7) the mail is about.
	 * @param string $subject  Subject line (may contain placeholders).
	 * @param string $template Template file name.
	 * @param bool   $append   Whether to append the global text block to this mail.
	 * @return bool
	 */
	private function send_drip_mail( int $sub_id, int $plan_id, string $email, int $day, string $subject, string $template, bool $append = false ): bool {

		if ( '' === $template ) {
			return false;
		}

		$token = (string) get_post_meta( $sub_id, '_mmm_sub_token', true );
		$plan  = new Mixmymeals_Meal_Plan( $plan_id );
		$name  = (string) get_post_meta( $sub_id, '_mmm_sub_name', true );

		$unsubscribe_url = $this->endpoint_url( 'unsubscribe', $token );

		$vars = $this->mail_vars( $plan, $day, $name, $unsubscribe_url, $token );

		$args = array(
			'layout'          => 'layout.html',
			'transactional'   => true,
			'unsubscribe_url' => $unsubscribe_url,
			'append_html'     => $append ? self::append_block() : '',
		);

		return Mixmymeals_Mailer::send_template( $email, $subject, $template, $vars, $args );
	}

	/**
	 * Builds the full set of placeholders available to every subscription
	 * template, for a given plan and weekday. There is no per-content provider:
	 * every placeholder is always present and the chosen template simply decides
	 * which ones it uses.
	 *
	 * Available placeholders:
	 *  - {{ plan }} {{ plan_url }} {{ unsubscribe_url }} {{ weekday }} {{ greeting }}
	 *  - {{ meal_list_html }}        The day's meals (thumbnail + "cook now" link),
	 *                                or a "no meals" notice.
	 *  - {{ meal_images_html }}      A row of the day's meal images (linked), or empty.
	 *  - {{ rating_links_html }}     Per-recipe "rate this recipe" links for the day
	 *                                (empty unless a rating page is configured and a
	 *                                token is available, i.e. a real drip send).
	 *  - {{ upload_links_html }}     Per-recipe "upload your photo" links for the day
	 *                                (empty unless a photo upload page is configured
	 *                                and a token is available).
	 *  - {{ plan_rating_links_html }} The same, but for every recipe in the whole
	 *  - {{ plan_upload_links_html }} plan rather than one day. What a mail sent
	 *                                after the plan has ended needs: by then the
	 *                                subscriber has cooked all of them, and the
	 *                                per-day block would only offer the last day's.
	 *  - {{ day_summary_html }}      The day's figures as one highlighted line:
	 *                                the same numbers as day_nutrition_html,
	 *                                with a label and in the accent colour.
	 *  - {{ day_kcal }} {{ day_cost }} {{ currency }}
	 *  - {{ day_nutrition_html }}    The day's figures as one line, per person;
	 *                                empty when the day has no data. Fibre and
	 *                                salt join it only when recorded.
	 *  - {{ nutrition_table_html }}  All seven figures with their share of the
	 *                                reference intake, laid out like a food
	 *                                label — including the note that the
	 *                                percentages are a labelling reference and
	 *                                not personal advice.
	 *  - {{ meal_nutrition_html }}   Energy and macros of one serving of each of
	 *                                the day's dishes, so the day's total can be
	 *                                traced to what caused it.
	 *  - {{ week_nutrition_html }}   The plan's daily average per person, over
	 *                                the days that carry meals. Empty for a plan
	 *                                of a single day.
	 *  - {{ day_protein }} {{ day_carbs }} {{ day_fat }}  Grams, per person.
	 *  - {{ day_sugar }} {{ day_fiber }} {{ day_salt }}   Grams, per person.
	 *  - {{ day_kcal_percent }}      The day's energy as a percentage of the
	 *                                reference intake (a bare number).
	 *  - {{ day_ingredients_html }}  Ingredients needed for this day's meals.
	 *  - {{ shopping_list_html }}    The whole plan's shopping list.
	 *  - {{ bring_url }}             Deep link that imports the plan's shopping
	 *                                list into Bring!. A mail carrying a
	 *                                shopping list is exactly where someone
	 *                                wants to send it to their phone.
	 *
	 * Shared by the live drip (send_drip_mail) and the per-rule test send.
	 *
	 * @param Mixmymeals_Meal_Plan $plan            Plan.
	 * @param int                  $day             Weekday (1-7) the mail is about.
	 * @param string               $name            Subscriber name (may be empty).
	 * @param string               $unsubscribe_url Unsubscribe URL.
	 * @param string               $rate_token      Subscription token for rating links.
	 * @return array<string,string>
	 */
	private function mail_vars( Mixmymeals_Meal_Plan $plan, int $day, string $name, string $unsubscribe_url, string $rate_token = '' ): array {

		$meals = $this->render_meal_list( $plan, $day );

		// The day's nutrition comes from the plan so the mail, the plan page and
		// the PDF quote the same figures. {{ day_kcal }} keeps coming from
		// render_meal_list(), which computes the identical value alongside the
		// cost — left untouched so existing templates cannot shift.
		$nutrition = $plan->calc_nutrition( $day )['per_person'];

		return array(
			'plan'                 => get_the_title( $plan->get_id() ),
			'plan_url'             => (string) get_permalink( $plan->get_id() ),
			'unsubscribe_url'      => $unsubscribe_url,
			'weekday'              => $this->weekday_name( $day ),
			'greeting'             => $this->greeting( $name ),
			'meal_list_html'       => $meals['list_html'],
			'meal_images_html'     => $this->render_meal_images( $plan, $day ),
			'rating_links_html'    => $this->render_rating_links( $plan, $day, $rate_token ),
			'upload_links_html'    => $this->render_upload_links( $plan, $day, $rate_token ),
			'plan_rating_links_html' => $this->render_rating_links( $plan, null, $rate_token ),
			'plan_upload_links_html' => $this->render_upload_links( $plan, null, $rate_token ),
			'day_summary_html'     => $this->render_day_summary( $nutrition ),
			'day_kcal'             => $meals['day_kcal'],
			'day_cost'             => $meals['day_cost'],
			'day_protein'          => number_format_i18n( $nutrition['protein'], 1 ),
			'day_carbs'            => number_format_i18n( $nutrition['carbs'], 1 ),
			'day_fat'              => number_format_i18n( $nutrition['fat'], 1 ),
			'day_sugar'            => number_format_i18n( $nutrition['sugar'], 1 ),
			'day_fiber'            => number_format_i18n( $nutrition['fiber'], 1 ),
			'day_salt'             => number_format_i18n( $nutrition['salt'], 2 ),
			'day_kcal_percent'     => (string) (int) Mixmymeals_Meal_Plan::nutrition_percentages( $nutrition )['calories'],
			'day_nutrition_html'   => $this->render_day_nutrition( $nutrition ),
			'nutrition_table_html' => $this->render_nutrition_table( $nutrition ),
			'meal_nutrition_html'  => $this->render_meal_nutrition( $plan, $day ),
			'week_nutrition_html'  => $this->render_week_nutrition( $plan ),
			'currency'             => (string) get_option( 'mixmymeals_currency', '€' ),
			'day_ingredients_html' => $this->render_ingredient_list( $plan, $day, __( 'No ingredients needed for this day.', 'mixmymeals' ) ),
			'shopping_list_html'   => $this->render_ingredient_list( $plan, null, __( 'The shopping list is empty.', 'mixmymeals' ) ),
			'bring_url'            => Mixmymeals_Meal_Plan::bring_deeplink( $plan->get_id() ),
		);
	}

	/**
	 * Renders the day's nutrition as one compact, mail-safe line. Returns '' when
	 * the day carries no calories, so a day without meals (or without nutrition
	 * data) does not print an empty strip of zeroes.
	 *
	 * @param array<string,float> $nutrition Per-person figures for the day.
	 * @return string
	 */
	/**
	 * The day's headline: the same figures as the nutrition line, under a label
	 * and set in the accent colour, for templates that want one prominent line
	 * above the meals instead of a quiet one below them.
	 *
	 * Built from the plan's nutrition like every other figure in the mail, so a
	 * template using both cannot show two different numbers.
	 *
	 * @param array<string,float> $nutrition Per-person figures for the day.
	 * @return string
	 */
	private function render_day_summary( array $nutrition ): string {

		$parts = $this->nutrition_parts( $nutrition );

		if ( empty( $parts ) ) {
			return '';
		}

		return '<p style="font-weight:700;color:#3F6F1E;">'
			. esc_html(
				sprintf(
					/* translators: %s: the day's nutrition figures, already formatted. */
					__( 'Day total per person: %s', 'mixmymeals' ),
					implode( ' · ', $parts )
				)
			)
			. '</p>';
	}

	private function render_day_nutrition( array $nutrition ): string {

		$parts = $this->nutrition_parts( $nutrition );

		if ( empty( $parts ) ) {
			return '';
		}

		return '<p style="margin:0 0 8px;">'
			. esc_html( implode( ' · ', $parts ) )
			. '</p>';
	}

	/**
	 * The day's figures as a list of ready-made strings — "~1.045 kcal",
	 * "82,4 g Eiweiß" and so on.
	 *
	 * Split out so the nutrition line and the day summary above the meals are
	 * built from one source. They used to disagree: the summary carried energy
	 * and a price, the line carried energy and the macros.
	 *
	 * @param array<string,float> $nutrition Per-person figures for the day.
	 * @return string[] Empty when the day carries no energy at all.
	 */
	private function nutrition_parts( array $nutrition ): array {

		if ( empty( $nutrition['calories'] ) ) {
			return array();
		}

		$parts = array(
			sprintf(
				/* translators: %s: kilocalories for the day. */
				__( '~%s kcal', 'mixmymeals' ),
				number_format_i18n( round( (float) $nutrition['calories'] ) )
			),
			sprintf(
				/* translators: %s: grams of protein. */
				__( '%s g protein', 'mixmymeals' ),
				number_format_i18n( (float) $nutrition['protein'], 1 )
			),
			sprintf(
				/* translators: %s: grams of carbohydrates. */
				__( '%s g carbs', 'mixmymeals' ),
				number_format_i18n( (float) $nutrition['carbs'], 1 )
			),
			sprintf(
				/* translators: %s: grams of fat. */
				__( '%s g fat', 'mixmymeals' ),
				number_format_i18n( (float) $nutrition['fat'], 1 )
			),
		);

		/*
		 * Fibre and salt only when there is something to say. Both are computed
		 * for every plan but are the two an ingredient database most often
		 * leaves empty, and a flat "0 g salt" on a day of ready meals would be
		 * worse than saying nothing. Sugar stays out of this line and lives in
		 * the table, where it can sit under carbohydrate as a label does it.
		 */
		if ( ! empty( $nutrition['fiber'] ) ) {
			$parts[] = sprintf(
				/* translators: %s: grams of fibre. */
				__( '%s g fibre', 'mixmymeals' ),
				number_format_i18n( (float) $nutrition['fiber'], 1 )
			);
		}

		if ( ! empty( $nutrition['salt'] ) ) {
			$parts[] = sprintf(
				/* translators: %s: grams of salt. */
				__( '%s g salt', 'mixmymeals' ),
				number_format_i18n( (float) $nutrition['salt'], 2 )
			);
		}

		return $parts;
	}

	/**
	 * The day's nutrition as a full table: all seven figures with their share of
	 * the reference intake, laid out like the panel on a food packet so the
	 * numbers are comparable to one.
	 *
	 * Table-based and inline-styled on purpose — mail clients have no flexbox
	 * and strip <style> blocks.
	 *
	 * @param array<string,float> $nutrition Per-person figures for the day.
	 * @return string
	 */
	private function render_nutrition_table( array $nutrition ): string {

		if ( empty( $nutrition['calories'] ) ) {
			return '';
		}

		$labels      = Mixmymeals_Meal_Plan::nutrition_labels();
		$percentages = Mixmymeals_Meal_Plan::nutrition_percentages( $nutrition );

		$rows = '';

		foreach ( $labels as $key => $label ) {

			$value = (float) ( $nutrition[ $key ] ?? 0.0 );

			// A nutrient nobody recorded is left out rather than printed as
			// zero: "0 g fibre" reads as a measurement, not as a gap.
			if ( empty( $value ) ) {
				continue;
			}

			// "of which sugars" is a sub-entry of carbohydrate on every label,
			// so it is indented rather than listed as an equal.
			$indent = 'sugar' === $key ? 'padding-left:16px;' : '';

			// Energy is the day's headline: set in the accent colour, it does
			// what day_summary_html did above the meals, so a template showing
			// the table needs no second line repeating its figures.
			$lead = 'calories' === $key ? 'font-weight:700;color:#3F6F1E;' : '';

			$rows .= '<tr>'
				. '<td style="' . esc_attr( $indent ) . 'padding:4px 0;color:#6B665B;font-size:14px;' . $lead . '">'
				. esc_html( $label ) . '</td>'
				. '<td style="padding:4px 0;text-align:right;color:#2C2A24;font-size:14px;white-space:nowrap;' . $lead . '">'
				. esc_html( Mixmymeals_Meal_Plan::format_nutrition_value( $key, $value ) ) . '</td>'
				. '<td style="padding:4px 0 4px 12px;text-align:right;color:#6B665B;font-size:14px;white-space:nowrap;">'
				. esc_html( sprintf( '%d %%', (int) $percentages[ $key ] ) ) . '</td>'
				. '</tr>';
		}

		if ( '' === $rows ) {
			return '';
		}

		return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 8px;border-collapse:collapse;">'
			. '<tr>'
			. '<th align="left" style="padding:0 0 4px;color:#6B665B;font-size:12px;font-weight:600;text-transform:uppercase;">'
			. esc_html__( 'Per person, this day', 'mixmymeals' ) . '</th>'
			. '<th></th>'
			. '<th align="right" style="padding:0 0 4px 12px;color:#6B665B;font-size:12px;font-weight:600;text-transform:uppercase;white-space:nowrap;">'
			. esc_html__( 'of RI*', 'mixmymeals' ) . '</th>'
			. '</tr>'
			. $rows
			. '</table>'
			. '<p style="margin:0 0 8px;line-height:1.4;color:#6B665B;font-size:12px;">'
			. esc_html( '* ' . Mixmymeals_Meal_Plan::reference_intake_note() )
			. '</p>';
	}

	/**
	 * The day's meals with the energy and macros of one serving each, so the
	 * day's total can be traced to the dish it came from — "which of these was
	 * the heavy one" is the question a single day figure cannot answer.
	 *
	 * @param Mixmymeals_Meal_Plan $plan Plan.
	 * @param int                  $day  Weekday (1-7).
	 * @return string
	 */
	private function render_meal_nutrition( Mixmymeals_Meal_Plan $plan, int $day ): string {

		$types = Mixmymeals_Meal::get_plaintext_meal_type_map();
		$rows  = '';

		foreach ( $plan->get_meals() as $meal_post ) {

			$meal = new Mixmymeals_Meal( $meal_post->ID );

			if ( (int) $meal->get_day() !== $day ) {
				continue;
			}

			$recipe = new Mixmymeals_Recipe( $meal->get_recipe_id() );

			if ( ! $recipe->is_valid() ) {
				continue;
			}

			$per_serving = $recipe->calculate_nutrition_per_serving();

			if ( empty( $per_serving['calories'] ) ) {
				continue;
			}

			$type = $meal->get_type() ?: 'other';

			$macros = sprintf(
				/* translators: 1: grams of protein, 2: grams of carbohydrates, 3: grams of fat. */
				__( '%1$s g P · %2$s g C · %3$s g F', 'mixmymeals' ),
				number_format_i18n( (float) ( $per_serving['protein'] ?? 0 ), 1 ),
				number_format_i18n( (float) ( $per_serving['carbs'] ?? 0 ), 1 ),
				number_format_i18n( (float) ( $per_serving['fat'] ?? 0 ), 1 )
			);

			$rows .= '<tr>'
				. '<td style="padding:6px 0;color:#2C2A24;font-size:14px;">'
				. esc_html( get_the_title( $recipe->get_id() ) )
				. '<br><span style="color:#8A8478;font-size:12px;">'
				. esc_html( $types[ $type ] ?? '' ) . '</span></td>'
				. '<td style="padding:6px 0 6px 12px;text-align:right;color:#2C2A24;font-size:14px;white-space:nowrap;">'
				. esc_html( Mixmymeals_Meal_Plan::format_nutrition_value( 'calories', (float) $per_serving['calories'] ) )
				. '<br><span style="color:#8A8478;font-size:12px;">' . esc_html( $macros ) . '</span></td>'
				. '</tr>';
		}

		if ( '' === $rows ) {
			return '';
		}

		return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 8px;border-collapse:collapse;">'
			. '<tr><th align="left" colspan="2" style="padding:0 0 4px;color:#6B665B;font-size:12px;font-weight:600;text-transform:uppercase;">'
			. esc_html__( 'Per serving, by dish', 'mixmymeals' )
			. '</th></tr>'
			. $rows
			. '</table>';
	}

	/**
	 * The whole plan at a glance: the daily average per person plus the number
	 * of days it covers. A single day says nothing about whether the week
	 * balances out — this does.
	 *
	 * Averaged over the days that actually carry meals, not over the plan's
	 * length: a plan with two empty days would otherwise look far lighter than
	 * anything anyone eats.
	 *
	 * @param Mixmymeals_Meal_Plan $plan Plan.
	 * @return string
	 */
	private function render_week_nutrition( Mixmymeals_Meal_Plan $plan ): string {

		$per_day = $plan->calc_nutrition_per_day_person();

		// A single day is not a week, and an average over one is just that
		// day's figures under a heading that promises more.
		if ( $per_day['days'] < 2 ) {
			return '';
		}

		$average = $per_day['average'];

		$percentages = Mixmymeals_Meal_Plan::nutrition_percentages( $average );

		// Each figure carries its label: "670 kcal · 46,4 g · 47,2 g" leaves the
		// reader guessing which gram is which.
		$labels = Mixmymeals_Meal_Plan::nutrition_labels();
		$parts  = array();

		foreach ( array( 'calories', 'protein', 'carbs', 'fat' ) as $key ) {

			$value = Mixmymeals_Meal_Plan::format_nutrition_value( $key, $average[ $key ] );

			$parts[] = 'calories' === $key
				? $value
				: sprintf(
					/* translators: 1: an amount with unit, 2: the nutrient's name. */
					__( '%1$s %2$s', 'mixmymeals' ),
					$value,
					$labels[ $key ]
				);
		}

		return '<p style="margin:0 0 8px;">'
			. esc_html(
				sprintf(
					/* translators: 1: number of days with meals, 2: the averaged nutrition figures, 3: percentage of the reference intake. */
					__( 'Across the %1$d days of this plan, on average per day: %2$s (%3$d %% of the reference intake).', 'mixmymeals' ),
					$per_day['days'],
					implode( ' · ', $parts ),
					(int) $percentages['calories']
				)
			)
			. '</p>';
	}

	/**
	 * Builds the "rate this recipe" links block for a plan weekday: one link per
	 * (distinct) recipe cooked that day, pointing at the configured rating page.
	 * Returns '' when no rating page is set, no token is available (e.g. the admin
	 * test preview) or the day has no recipes.
	 *
	 * @param Mixmymeals_Meal_Plan $plan       Plan.
	 * @param int                  $day        Weekday (1-7).
	 * @param string               $rate_token Subscription token.
	 * @return string
	 */
	private function render_rating_links( Mixmymeals_Meal_Plan $plan, ?int $day, string $rate_token ): string {

		if ( '' === $rate_token ) {
			return '';
		}

		$items = array();
		$seen  = array();

		foreach ( $plan->get_meals() as $meal_post ) {
			$meal = new Mixmymeals_Meal( $meal_post->ID );

			// null = the whole plan, for a mail sent after it has ended.
			if ( null !== $day && (int) $meal->get_day() !== $day ) {
				continue;
			}

			$recipe_id = (int) $meal->get_recipe_id();
			if ( ! $recipe_id || isset( $seen[ $recipe_id ] ) || get_post_type( $recipe_id ) !== 'mmm_recipe' ) {
				continue;
			}
			$seen[ $recipe_id ] = true;

			$href = $this->rate_url( $rate_token, $recipe_id );
			if ( '' === $href ) {
				return '';
			}

			$items[] = '<li style="margin:0 0 8px;"><a href="' . esc_url( $href )
				. '" style="color:#E8A33D;font-weight:700;text-decoration:none;">'
				. esc_html( get_the_title( $recipe_id ) ) . ' &#9733;</a></li>';
		}

		if ( empty( $items ) ) {
			return '';
		}

		return '<div class="mmm-mail-rating-links">'
			. '<p style="margin:0 0 8px;font-weight:700;">'
			. esc_html(
				null === $day
					? __( 'Rate the recipes from this plan:', 'mixmymeals' )
					: __( 'Rate the recipes you cooked and share a photo:', 'mixmymeals' )
			)
			. '</p>'
			. '<ul style="list-style:none;margin:0;padding:0;">' . implode( '', $items ) . '</ul>'
			. '</div>';
	}

	/**
	 * Builds the "upload your photo" links block for a plan weekday: one link
	 * per (distinct) recipe cooked that day, pointing at the configured photo
	 * upload page. Returns '' when no upload page is set, no token is available
	 * (e.g. the admin test preview) or the day has no recipes.
	 *
	 * @param Mixmymeals_Meal_Plan $plan       Plan.
	 * @param int                  $day        Weekday (1-7).
	 * @param string               $rate_token Subscription token.
	 * @return string
	 */
	private function render_upload_links( Mixmymeals_Meal_Plan $plan, ?int $day, string $rate_token ): string {

		if ( '' === $rate_token ) {
			return '';
		}

		$items = array();
		$seen  = array();

		foreach ( $plan->get_meals() as $meal_post ) {
			$meal = new Mixmymeals_Meal( $meal_post->ID );
			// null = the whole plan, see render_rating_links().
			if ( null !== $day && (int) $meal->get_day() !== $day ) {
				continue;
			}

			$recipe_id = (int) $meal->get_recipe_id();
			if ( ! $recipe_id || isset( $seen[ $recipe_id ] ) || get_post_type( $recipe_id ) !== 'mmm_recipe' ) {
				continue;
			}
			$seen[ $recipe_id ] = true;

			$href = $this->upload_url( $rate_token, $recipe_id );
			if ( '' === $href ) {
				return '';
			}

			$items[] = '<li style="margin:0 0 8px;"><a href="' . esc_url( $href )
				. '" style="color:#E8A33D;font-weight:700;text-decoration:none;">'
				. esc_html( get_the_title( $recipe_id ) ) . ' &#128247;</a></li>';
		}

		if ( empty( $items ) ) {
			return '';
		}

		return '<div class="mmm-mail-upload-links">'
			. '<p style="margin:0 0 8px;font-weight:700;">'
			. esc_html(
				null === $day
					? __( 'Upload a photo of any meal from this plan:', 'mixmymeals' )
					: __( 'Upload a photo of the meals you cooked:', 'mixmymeals' )
			)
			. '</p>'
			. '<ul style="list-style:none;margin:0;padding:0;">' . implode( '', $items ) . '</ul>'
			. '</div>';
	}

	/**
	 * AJAX: sends a test of one sending rule (subject + template) to an address.
	 *
	 * Renders the template against the latest published meal plan that has meals,
	 * prefixes the subject with [TEST] and force-sends it.
	 *
	 * @return void
	 */
	public function ajax_test_rule(): void {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'mixmymeals' ) ), 403 );
		}

		check_ajax_referer( 'mmm_sub_test_rule', 'nonce' );

		if ( ! class_exists( 'Mixmymeals_Mailer' ) ) {
			wp_send_json_error( array( 'message' => __( 'The mailer is not available.', 'mixmymeals' ) ), 500 );
		}

		$template = isset( $_POST['template'] ) ? sanitize_text_field( wp_unslash( $_POST['template'] ) ) : '';
		$subject  = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$to       = isset( $_POST['to'] ) ? sanitize_email( wp_unslash( $_POST['to'] ) ) : ''; //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$append   = ! empty( $_POST['append'] ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated

		if ( '' === $to || ! is_email( $to ) ) {
			$to = (string) get_option( 'admin_email' );
		}

		if ( ! self::is_valid_template_name( $template ) || 'layout.html' === basename( $template ) ) {
			wp_send_json_error( array( 'message' => __( 'Please choose a valid template first.', 'mixmymeals' ) ), 400 );
		}

		if ( '' === $subject ) {
			$subject = self::default_subject( $template );
		}

		$plan_id = $this->latest_plan_with_meals();
		if ( ! $plan_id ) {
			wp_send_json_error( array( 'message' => __( 'No published meal plan with meals is available to preview.', 'mixmymeals' ) ), 400 );
		}

		$plan = new Mixmymeals_Meal_Plan( $plan_id );
		$day  = $this->first_day_with_meals( $plan );
		// Use a preview token so {{ rating_links_html }} renders in the test mail
		// (the links point at the rating page but are not clickable-valid). They
		// still stay empty when no rating page is configured, which signals that.
		$vars = $this->mail_vars( $plan, $day, '', home_url( '/' ), 'preview' );

		/* translators: %s: composed subject line. */
		$subject = sprintf( __( '[TEST] %s', 'mixmymeals' ), $subject );

		$args = array(
			'layout'          => 'layout.html',
			'transactional'   => true,
			'unsubscribe_url' => home_url( '/' ),
			'force'           => true,
			'append_html'     => $append ? self::append_block() : '',
		);

		$sent = Mixmymeals_Mailer::send_template( $to, $subject, $template, $vars, $args );

		if ( $sent ) {
			wp_send_json_success(
				array(
					/* translators: 1: recipient address, 2: meal plan title used for the preview. */
					'message' => sprintf( __( 'Test sent to %1$s (using plan “%2$s”).', 'mixmymeals' ), $to, get_the_title( $plan_id ) ),
				)
			);
		}

		wp_send_json_error( array( 'message' => __( 'The test e-mail could not be sent.', 'mixmymeals' ) ), 500 );
	}

	/**
	 * Returns the most recent published meal plan that has meals, or 0.
	 *
	 * @return int
	 */
	private function latest_plan_with_meals(): int {

		$ids = get_posts(
			array(
				'post_type'      => 'mmm_meal_plan',
				'post_status'    => 'publish',
				'posts_per_page' => 10,
				'fields'         => 'ids',
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		foreach ( $ids as $id ) {
			$plan = new Mixmymeals_Meal_Plan( (int) $id );
			if ( ! empty( $plan->get_meals() ) ) {
				return (int) $id;
			}
		}

		return ! empty( $ids ) ? (int) $ids[0] : 0;
	}

	/**
	 * Returns the first plan weekday that actually has meals (for previews).
	 *
	 * @param Mixmymeals_Meal_Plan $plan Plan.
	 * @return int Weekday (1-7).
	 */
	private function first_day_with_meals( Mixmymeals_Meal_Plan $plan ): int {

		$range     = $plan->get_day_range();
		$has_meals = array();

		foreach ( $plan->get_meals() as $meal_post ) {
			$meal                                = new Mixmymeals_Meal( $meal_post->ID );
			$has_meals[ (int) $meal->get_day() ] = true;
		}

		foreach ( $range as $d ) {
			if ( ! empty( $has_meals[ (int) $d ] ) ) {
				return (int) $d;
			}
		}

		return ! empty( $range ) ? (int) $range[0] : 1;
	}

	/**
	 * Builds a personalised greeting line.
	 *
	 * @param string $name Subscriber name (may be empty).
	 * @return string Escaped greeting (no markup).
	 */
	private function greeting( string $name ): string {
		return '' !== $name
			? sprintf( /* translators: %s: subscriber name. */ __( 'Hi %s,', 'mixmymeals' ), $name )
			: __( 'Hi there,', 'mixmymeals' );
	}

	/**
	 * Localised full weekday name for a plan weekday (1=Mon … 7=Sun).
	 *
	 * @param int $day Weekday (1-7).
	 * @return string Weekday name, or '' if the locale is unavailable.
	 */
	private function weekday_name( int $day ): string {
		global $wp_locale;
		if ( $wp_locale instanceof WP_Locale && $day >= 1 && $day <= 7 ) {
			return (string) $wp_locale->get_weekday( $day % 7 ); // get_weekday: 0=Sunday.
		}
		return '';
	}

	/**
	 * Renders the meal list for a plan weekday as a ready HTML block, plus the
	 * day's calorie/cost figures. Fills the meal-related mail placeholders.
	 *
	 * @param Mixmymeals_Meal_Plan $plan Plan.
	 * @param int                  $day  Weekday (1-7).
	 * @return array{list_html:string, summary_html:string, day_kcal:string, day_cost:string}
	 */
	private function render_meal_list( Mixmymeals_Meal_Plan $plan, int $day ): array {

		$types    = Mixmymeals_Meal::get_plaintext_meal_type_map();

		$items      = array();
		$total_cal  = 0.0;
		$total_cost = 0.0;

		foreach ( $plan->get_meals() as $meal_post ) {

			$meal = new Mixmymeals_Meal( $meal_post->ID );

			if ( (int) $meal->get_day() !== $day ) {
				continue;
			}

			$title    = get_the_title( $meal_post->ID );
			$link     = get_permalink( $meal_post->ID );
			$type     = $meal->get_type();
			$type_lbl = $types[ $type ] ?? '';
			$servings = max( 1, (int) $meal->get_servings() );

			$meta = array();
			if ( $type_lbl ) {
				$meta[] = $type_lbl;
			}
			$meta[] = sprintf( /* translators: %d: number of servings. */ _n( '%d serving', '%d servings', $servings, 'mixmymeals' ), $servings );

			// Per-person calories and cost (one serving) from the linked recipe.
			$cal_line = '';
			$recipe   = new Mixmymeals_Recipe( $meal->get_recipe_id() );
			if ( $recipe->is_valid() ) {

				$nutrition = $recipe->calculate_nutrition_per_serving();
				$cal_per   = isset( $nutrition['calories'] ) ? (float) $nutrition['calories'] : 0.0;
				$cost_per  = (float) $recipe->calculate_cost_per_serving();

				if ( $cal_per > 0 ) {
					$total_cal += $cal_per; // Per person: one serving per meal.
					$cal_line   = sprintf(
						/* translators: %s: kcal per serving. */
						__( '~%s kcal/serving', 'mixmymeals' ),
						number_format_i18n( round( $cal_per ) )
					);
				}

				if ( $cost_per > 0 ) {
					$total_cost += $cost_per; // Per person.
				}
			}

			$content = '<a href="' . esc_url( $link ) . '" style="font-weight:700;color:#3F6F1E;text-decoration:none;">' . esc_html( $title ) . '</a>'
				. ' <span style="color:#6B665B;">· ' . esc_html( implode( ' · ', $meta ) ) . '</span>'
				. ( '' !== $cal_line ? '<br><span style="color:#9A9485;font-size:14px;">' . esc_html( $cal_line ) . '</span>' : '' )
				. '<br><a href="' . esc_url( $link ) . '" style="display:inline-block;margin-top:4px;color:#5C9E2E;font-weight:700;text-decoration:none;">'
				. esc_html__( 'Cook now', 'mixmymeals' ) . ' →</a>';

			$thumb_id  = (int) $meal->get_recipe_id();
			$thumb_url = $thumb_id ? (string) get_the_post_thumbnail_url( $thumb_id, 'thumbnail' ) : '';

			// A fixed square, not width plus height:auto: the latter takes the
			// file's own ratio, and a site whose thumbnail size does not crop
			// handed the mail a strip far taller than the text beside it.
			// object-fit crops where the client supports it; where it does not,
			// the 'thumbnail' size is a square crop on a default install.

			if ( '' !== $thumb_url ) {
				$row = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;"><tr>'
					. '<td style="vertical-align:top;width:64px;padding-right:12px;">'
					. '<a href="' . esc_url( $link ) . '"><img src="' . esc_url( $thumb_url ) . '" alt="" width="64" height="64" style="width:64px;height:64px;object-fit:cover;border-radius:8px;display:block;border:0;"></a>'
					. '</td>'
					. '<td style="vertical-align:top;">' . $content . '</td>'
					. '</tr></table>';
			} else {
				$row = $content;
			}

			$items[] = '<li style="margin:0 0 14px;">' . $row . '</li>';
		}

		if ( empty( $items ) ) {
			return array(
				'list_html'    => '<p>' . esc_html__( 'There are no meals planned for this day.', 'mixmymeals' ) . '</p>',
				'summary_html' => '',
				'day_kcal'     => '',
				'day_cost'     => '',
			);
		}

		/*
		 * The summary line is composed in mail_vars() from the plan's nutrition,
		 * not here: this method only knows the energy it added up itself, and
		 * the line used to read "Day total per person: ~1 951 kcal · approx.
		 * 7,40 €" — a money figure under a heading that promises the day's
		 * intake. The price is still available on its own as {{ day_cost }}.
		 */
		$summary_html = '';

		return array(
			'list_html'    => '<ul style="list-style-type:none;padding:0;margin:0 0 16px;">' . implode( '', $items ) . '</ul>',
			'summary_html' => $summary_html,
			'day_kcal'     => $total_cal > 0 ? number_format_i18n( round( $total_cal ) ) : '',
			'day_cost'     => $total_cost > 0 ? number_format_i18n( $total_cost, 2 ) : '',
		);
	}

	/**
	 * Builds a row of the day's meal images (each linked to its meal) for the
	 * {{ meal_images_html }} placeholder, or '' when no meal has an image.
	 *
	 * The image is the linked recipe's featured image (with the theme's
	 * placeholder fallback). Width-based sizing keeps it e-mail- and
	 * wp_kses_post-safe.
	 *
	 * @param Mixmymeals_Meal_Plan $plan Plan.
	 * @param int                  $day  Weekday (1-7).
	 * @return string
	 */
	private function render_meal_images( Mixmymeals_Meal_Plan $plan, int $day ): string {

		$imgs = array();

		foreach ( $plan->get_meals() as $meal_post ) {

			$meal = new Mixmymeals_Meal( $meal_post->ID );
			if ( (int) $meal->get_day() !== $day ) {
				continue;
			}

			$recipe_id = (int) $meal->get_recipe_id();
			$url       = $recipe_id ? (string) get_the_post_thumbnail_url( $recipe_id, 'medium' ) : '';
			if ( '' === $url ) {
				continue;
			}

			$imgs[] = '<a href="' . esc_url( get_permalink( $meal_post->ID ) ) . '" style="display:inline-block;margin:0 8px 8px 0;text-decoration:none;">'
				. '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( get_the_title( $meal_post->ID ) ) . '" width="140" style="width:140px;height:auto;border-radius:10px;display:block;border:0;">'
				. '</a>';
		}

		if ( empty( $imgs ) ) {
			return '';
		}

		return '<div style="margin:0 0 16px;">' . implode( '', $imgs ) . '</div>';
	}

	/**
	 * Renders an ingredient list (whole plan or a single day), grouped by
	 * ingredient category, as a ready HTML block. Fills the
	 * {{ shopping_list_html }} and {{ day_ingredients_html }} placeholders.
	 *
	 * @param Mixmymeals_Meal_Plan $plan       Plan.
	 * @param int|null             $day        Weekday (1-7) to limit to, or null for the whole plan.
	 * @param string               $empty_text Notice shown when the list is empty.
	 * @return string
	 */
	private function render_ingredient_list( Mixmymeals_Meal_Plan $plan, ?int $day, string $empty_text ): string {

		// Structure: [ category_name => [ ing_id => { total_weight, units, ... } ] ].
		$list = $plan->calc_shopping_list( true, $day );

		if ( empty( $list ) ) {
			return '<p>' . esc_html( $empty_text ) . '</p>';
		}

		$out = '';
		foreach ( $list as $cat_name => $ings ) {

			// h3: the list sits under a template's own h2 ("Zutaten"), and
			// the categories are a level below it, not beside it.
			$out .= '<h3 style="font-size:16px;line-height:1.3;color:#3F6F1E;margin:16px 0 6px;">' . esc_html( (string) $cat_name ) . '</h3>';
			$out .= '<ul style="list-style-type:none;padding:0;margin:0;">';

			foreach ( (array) $ings as $ing_id => $data ) {

				$ingredient = new Mixmymeals_Ingredient( (int) $ing_id );
				if ( ! $ingredient->is_valid() ) {
					continue;
				}

				$ing_name = get_the_title( (int) $ing_id );
				$amount   = Mixmymeals_Shortcodes::build_needed_amount_string(
					$data['units'] ?? array(),
					(float) ( $data['total_weight'] ?? 0 )
				);

				$optional_label = ! empty( $data['optional'] )
					? ' <span style="color:#6B665B;">(' . esc_html__( 'optional', 'mixmymeals' ) . ')</span>'
					: '';

				// The packet the figures assume, where the plan agrees on one.
				$reference_label = (string) ( $data['product'] ?? '' );

				$out .= '<li style="padding:4px 0;border-bottom:1px solid #ECE6D9;">'
					. '<strong style="font-weight:600;">' . esc_html( $ing_name ) . '</strong>'
					. ( '' !== $amount ? ' <span style="color:#6B665B;">' . esc_html( $amount ) . '</span>' : '' )
					. $optional_label
					. ( '' !== $reference_label
						? '<br><span style="color:#6B665B;font-size:14px;">' . esc_html( $reference_label ) . '</span>'
						: '' )
					. '</li>';
			}

			$out .= '</ul>';
		}

		return $out;
	}

	/* ----------------------------------------------------------
	 * PRUNING (GDPR retention)
	 * ---------------------------------------------------------- */

	/**
	 * Deletes unconfirmed expired and old finished subscriptions.
	 *
	 * Reports both counts to Mixmymeals_Cleanup so they appear in the cleanup
	 * report — this removes personal data and should not happen silently.
	 *
	 * @return array{unconfirmed:int,retention:int} Number of deleted records.
	 */
	private function prune(): array {

		$pending_before   = time() - self::get_pending_hours() * HOUR_IN_SECONDS;
		$retention_before = time() - self::get_retention_days() * DAY_IN_SECONDS;

		// Unconfirmed, past the confirmation window.
		$pending = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => self::DISPATCH_BATCH,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'     => array(
					array(
						'key'   => '_mmm_sub_status',
						'value' => 'pending',
					),
				),
			)
		);

		$deleted_pending  = 0;
		$deleted_finished = 0;

		foreach ( $pending as $sub_id ) {
			$consent = (int) get_post_meta( (int) $sub_id, '_mmm_sub_consent_time', true );
			if ( $consent && $consent < $pending_before && wp_delete_post( (int) $sub_id, true ) ) {
				++$deleted_pending;
			}
		}

		// Finished records past retention.
		$finished = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => self::DISPATCH_BATCH,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'     => '_mmm_sub_status',
						'value'   => array( 'completed', 'unsubscribed' ),
						'compare' => 'IN',
					),
				),
			)
		);

		foreach ( $finished as $sub_id ) {
			$post = get_post( (int) $sub_id );
			if ( $post && strtotime( $post->post_modified_gmt . ' UTC' ) < $retention_before
				&& wp_delete_post( (int) $sub_id, true ) ) {
				++$deleted_finished;
			}
		}

		if ( class_exists( 'Mixmymeals_Cleanup' ) ) {
			Mixmymeals_Cleanup::record( 'subscriptions_unconfirmed', $deleted_pending );
			Mixmymeals_Cleanup::record( 'subscriptions_retention', $deleted_finished );
		}

		return array(
			'unconfirmed' => $deleted_pending,
			'retention'   => $deleted_finished,
		);
	}

	/* ----------------------------------------------------------
	 * HELPERS
	 * ---------------------------------------------------------- */

	/**
	 * Builds a token endpoint URL.
	 *
	 * @param string $action confirm|unsubscribe.
	 * @param string $token  Token.
	 * @return string
	 */
	private function endpoint_url( string $action, string $token ): string {
		return add_query_arg(
			array(
				'mmm_sub' => $action,
				'token'   => $token,
			),
			home_url( '/' )
		);
	}

	/**
	 * Builds the tokenised "rate this recipe" URL pointing at the configured
	 * rating page (which carries the [mmm_recipe_rating_form] shortcode). Returns
	 * '' when no rating page is set.
	 *
	 * @param string $token     Subscription token.
	 * @param int    $recipe_id Recipe post ID.
	 * @return string
	 */
	private function rate_url( string $token, int $recipe_id ): string {

		$base = self::redirect_url( self::OPT_RATE_PAGE );
		if ( '' === $base ) {
			return '';
		}

		return add_query_arg(
			array(
				'token'  => $token,
				'recipe' => $recipe_id,
			),
			$base
		);
	}

	/**
	 * Builds the tokenised "upload your photo" URL pointing at the configured
	 * photo upload page (which carries the [mmm_photo_upload_form] shortcode).
	 * Returns '' when no upload page is set.
	 *
	 * @param string $token     Subscription token.
	 * @param int    $recipe_id Recipe post ID.
	 * @return string
	 */
	private function upload_url( string $token, int $recipe_id ): string {

		$base = self::redirect_url( self::OPT_UPLOAD_PAGE );
		if ( '' === $base ) {
			return '';
		}

		return add_query_arg(
			array(
				'token'  => $token,
				'recipe' => $recipe_id,
			),
			$base
		);
	}

	/* ----------------------------------------------------------
	 * ADMIN: LIST TABLE, DETAILS, ACTIONS
	 * ---------------------------------------------------------- */

	/**
	 * Human-readable labels for the subscription statuses.
	 *
	 * @return array<string,string>
	 */
	public static function status_labels(): array {
		return array(
			'pending'      => __( 'Pending', 'mixmymeals' ),
			'confirmed'    => __( 'Confirmed', 'mixmymeals' ),
			'completed'    => __( 'Completed', 'mixmymeals' ),
			'unsubscribed' => __( 'Unsubscribed', 'mixmymeals' ),
		);
	}

	/**
	 * Returns a small coloured status badge.
	 *
	 * @param string $status Status key.
	 * @return string Safe HTML.
	 */
	private function status_badge( string $status ): string {

		$styles = array(
			'pending'      => 'color:#8a6d00;background:#fcf3cd',
			'confirmed'    => 'color:#1d6e1d;background:#d7f0d7',
			'completed'    => 'color:#2c5d8a;background:#d6e7f5',
			'unsubscribed' => 'color:#7a2a2a;background:#f3dada',
		);

		$labels = self::status_labels();
		$label  = $labels[ $status ] ?? ucfirst( $status );
		$style  = $styles[ $status ] ?? 'color:#555;background:#eee';

		return '<span style="display:inline-block;padding:2px 8px;border-radius:999px;'
			. 'font-size:11px;font-weight:600;' . esc_attr( $style ) . ';">'
			. esc_html( $label ) . '</span>';
	}

	/**
	 * Returns a "sent / total" delivery progress label.
	 *
	 * @param int $sub_id Subscription ID.
	 * @return string
	 */
	private function progress_label( int $sub_id ): string {

		$status = (string) get_post_meta( $sub_id, '_mmm_sub_status', true );

		if ( 'pending' === $status ) {
			return '—';
		}

		$sends = get_post_meta( $sub_id, '_mmm_sub_sends', true );

		if ( ! is_array( $sends ) || empty( $sends ) ) {
			return 'completed' === $status ? __( 'done', 'mixmymeals' ) : '—';
		}

		$sent = 0;
		foreach ( $sends as $send ) {
			if ( ! empty( $send['sent'] ) ) {
				++$sent;
			}
		}

		return sprintf( '%d / %d', $sent, count( $sends ) );
	}

	/**
	 * Defines the list-table columns.
	 *
	 * @param array $columns Default columns.
	 * @return array
	 */
	public function admin_columns( array $columns ): array {
		return array(
			'cb'             => $columns['cb'] ?? '<input type="checkbox" />',
			'mmm_status'     => __( 'Status', 'mixmymeals' ),
			'mmm_email'      => __( 'E-mail', 'mixmymeals' ),
			'mmm_name'       => __( 'Name', 'mixmymeals' ),
			'mmm_plan'       => __( 'Meal plan', 'mixmymeals' ),
			'mmm_progress'   => __( 'Sent', 'mixmymeals' ),
			'mmm_subscribed' => __( 'Subscribed', 'mixmymeals' ),
		);
	}

	/**
	 * Marks columns as sortable.
	 *
	 * @param array $columns Sortable columns.
	 * @return array
	 */
	public function admin_sortable_columns( array $columns ): array {
		$columns['mmm_status']     = 'mmm_status';
		$columns['mmm_subscribed'] = 'mmm_subscribed';
		return $columns;
	}

	/**
	 * Renders a single custom column cell.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Subscription ID.
	 * @return void
	 */
	public function admin_render_column( string $column, int $post_id ): void {

		switch ( $column ) {

			case 'mmm_status':
				echo wp_kses_post( $this->status_badge( (string) get_post_meta( $post_id, '_mmm_sub_status', true ) ) );
				break;

			case 'mmm_email':
				$email = (string) get_post_meta( $post_id, '_mmm_sub_email', true );
				$edit  = get_edit_post_link( $post_id );
				echo $edit
					? '<a href="' . esc_url( $edit ) . '">' . esc_html( $email ) . '</a>'
					: esc_html( $email );
				break;

			case 'mmm_name':
				$name = (string) get_post_meta( $post_id, '_mmm_sub_name', true );
				echo esc_html( '' !== $name ? $name : '—' );
				break;

			case 'mmm_plan':
				$plan_id = (int) get_post_meta( $post_id, '_mmm_sub_plan_id', true );
				if ( $plan_id && get_post_type( $plan_id ) === 'mmm_meal_plan' ) {
					echo '<a href="' . esc_url( (string) get_edit_post_link( $plan_id ) ) . '">'
						. esc_html( get_the_title( $plan_id ) ) . '</a>';
				} else {
					echo '—';
				}
				break;

			case 'mmm_progress':
				echo esc_html( $this->progress_label( $post_id ) );
				break;

			case 'mmm_subscribed':
				$ts = (int) get_post_meta( $post_id, '_mmm_sub_consent_time', true );
				echo $ts
					? esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts ) )
					: '—';
				break;
		}
	}

	/**
	 * Prints the status filter dropdown above the list table.
	 *
	 * @return void
	 */
	public function admin_status_filter(): void {

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || self::POST_TYPE !== $screen->post_type ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current = isset( $_GET['mmm_sub_status'] ) ? sanitize_key( wp_unslash( $_GET['mmm_sub_status'] ) ) : '';

		echo '<select name="mmm_sub_status">';
		echo '<option value="">' . esc_html__( 'All statuses', 'mixmymeals' ) . '</option>';
		foreach ( self::status_labels() as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $current, $key, false ) . '>'
				. esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	/**
	 * Applies the status filter and the custom sort orders.
	 *
	 * @param WP_Query $query Main query.
	 * @return void
	 */
	public function admin_filter_query( WP_Query $query ): void {

		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( self::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status = isset( $_GET['mmm_sub_status'] ) ? sanitize_key( wp_unslash( $_GET['mmm_sub_status'] ) ) : '';
		if ( '' !== $status && isset( self::status_labels()[ $status ] ) ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			$query->set(
				'meta_query',
				array(
					array(
						'key'   => '_mmm_sub_status',
						'value' => $status,
					),
				)
			);
		}

		$orderby = $query->get( 'orderby' );
		if ( 'mmm_status' === $orderby ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$query->set( 'meta_key', '_mmm_sub_status' );
			$query->set( 'orderby', 'meta_value' );
		} elseif ( 'mmm_subscribed' === $orderby ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$query->set( 'meta_key', '_mmm_sub_consent_time' );
			$query->set( 'orderby', 'meta_value_num' );
		}
	}

	/**
	 * Registers the read-only details meta box.
	 *
	 * @return void
	 */
	public function admin_meta_boxes(): void {
		add_meta_box(
			'mmm_sub_details',
			__( 'Subscription details', 'mixmymeals' ),
			array( $this, 'render_details_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Renders the read-only details meta box.
	 *
	 * @param WP_Post $post Subscription post.
	 * @return void
	 */
	public function render_details_box( WP_Post $post ): void {

		$sub_id  = $post->ID;
		$fmt     = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		$status  = (string) get_post_meta( $sub_id, '_mmm_sub_status', true );
		$email   = (string) get_post_meta( $sub_id, '_mmm_sub_email', true );
		$name    = (string) get_post_meta( $sub_id, '_mmm_sub_name', true );
		$plan_id = (int) get_post_meta( $sub_id, '_mmm_sub_plan_id', true );
		$c_time  = (int) get_post_meta( $sub_id, '_mmm_sub_consent_time', true );
		$c_ip    = (string) get_post_meta( $sub_id, '_mmm_sub_consent_ip', true );
		$cf_time = (int) get_post_meta( $sub_id, '_mmm_sub_confirm_time', true );
		$token   = (string) get_post_meta( $sub_id, '_mmm_sub_token', true );

		$plan_html = ( $plan_id && get_post_type( $plan_id ) === 'mmm_meal_plan' )
			? '<a href="' . esc_url( (string) get_edit_post_link( $plan_id ) ) . '">' . esc_html( get_the_title( $plan_id ) ) . '</a>'
			: '—';

		$consent_html = $c_time
			? esc_html( wp_date( $fmt, $c_time ) ) . ( '' !== $c_ip ? ' · ' . esc_html( $c_ip ) : '' )
			: '—';

		$rows = array(
			__( 'Status', 'mixmymeals' )        => $this->status_badge( $status ),
			__( 'E-mail', 'mixmymeals' )        => esc_html( $email ),
			__( 'Name', 'mixmymeals' )          => esc_html( '' !== $name ? $name : '—' ),
			__( 'Meal plan', 'mixmymeals' )     => $plan_html,
			__( 'Consent given', 'mixmymeals' ) => $consent_html,
			__( 'Confirmed', 'mixmymeals' )     => $cf_time ? esc_html( wp_date( $fmt, $cf_time ) ) : '—',
			__( 'Token', 'mixmymeals' )         => '' !== $token ? '<code>' . esc_html( $token ) . '</code>' : '—',
		);

		echo '<table class="widefat striped" style="margin-top:8px;"><tbody>';
		foreach ( $rows as $label => $value_html ) {
			echo '<tr><th scope="row" style="width:170px;text-align:left;">' . esc_html( $label ) . '</th>'
				. '<td>' . wp_kses_post( $value_html ) . '</td></tr>';
		}
		echo '</tbody></table>';

		$sends = get_post_meta( $sub_id, '_mmm_sub_sends', true );

		echo '<h3 style="margin:18px 0 6px;">' . esc_html__( 'Delivery schedule', 'mixmymeals' ) . '</h3>';

		if ( ! is_array( $sends ) || empty( $sends ) ) {
			echo '<p>' . esc_html__( 'No deliveries scheduled.', 'mixmymeals' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'When', 'mixmymeals' ) . '</th>';
		echo '<th>' . esc_html__( 'Subject', 'mixmymeals' ) . '</th>';
		echo '<th>' . esc_html__( 'State', 'mixmymeals' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $sends as $send ) {
			$when = isset( $send['ts'] ) ? wp_date( $fmt, (int) $send['ts'] ) : '—';

			// Prefer the subject; fall back to the template, then the legacy
			// content/template values from history slots recorded earlier.
			$label = (string) ( $send['subject'] ?? '' );
			if ( '' === $label ) {
				$label = (string) ( $send['template'] ?? ( $send['content'] ?? '' ) );
			}
			if ( '' === $label ) {
				$label = '—';
			}

			$state = ! empty( $send['sent'] ) ? __( 'sent', 'mixmymeals' ) : __( 'pending', 'mixmymeals' );

			echo '<tr><td>' . esc_html( $when ) . '</td><td>' . esc_html( $label )
				. '</td><td>' . esc_html( $state ) . '</td></tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Adds the unsubscribe / resend row actions to the list table.
	 *
	 * @param array   $actions Existing row actions.
	 * @param WP_Post $post    Post.
	 * @return array
	 */
	public function admin_row_actions( array $actions, WP_Post $post ): array {

		if ( self::POST_TYPE !== $post->post_type ) {
			return $actions;
		}

		$status = (string) get_post_meta( $post->ID, '_mmm_sub_status', true );

		if ( 'pending' === $status ) {
			$actions['mmm_resend'] = '<a href="' . esc_url( $this->action_url( 'mmm_sub_resend', $post->ID ) ) . '">'
				. esc_html__( 'Resend confirmation', 'mixmymeals' ) . '</a>';
		}

		if ( in_array( $status, array( 'pending', 'confirmed' ), true ) ) {
			$actions['mmm_unsubscribe'] = '<a href="' . esc_url( $this->action_url( 'mmm_sub_unsubscribe', $post->ID ) ) . '">'
				. esc_html__( 'Unsubscribe', 'mixmymeals' ) . '</a>';
		}

		return $actions;
	}

	/**
	 * Builds a nonce-protected admin-post action URL.
	 *
	 * @param string $action Action name.
	 * @param int    $sub_id Subscription ID.
	 * @return string
	 */
	private function action_url( string $action, int $sub_id ): string {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=' . $action . '&sub=' . $sub_id ),
			$action . '_' . $sub_id
		);
	}

	/**
	 * Verifies a row-action request and returns the subscription ID.
	 *
	 * @param string $action Action name.
	 * @return int
	 */
	private function verify_action( string $action ): int {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$sub_id = isset( $_GET['sub'] ) ? absint( wp_unslash( $_GET['sub'] ) ) : 0;

		check_admin_referer( $action . '_' . $sub_id );

		if ( ! $sub_id || get_post_type( $sub_id ) !== self::POST_TYPE || ! current_user_can( 'edit_post', $sub_id ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'mixmymeals' ) );
		}

		return $sub_id;
	}

	/**
	 * Redirects back to the list with a result notice flag.
	 *
	 * @param string $result Result key.
	 * @return void
	 */
	private function redirect_back( string $result ): void {

		$url = wp_get_referer();
		if ( ! $url ) {
			$url = admin_url( 'edit.php?post_type=' . self::POST_TYPE );
		}

		wp_safe_redirect( add_query_arg( 'mmm_sub_done', $result, remove_query_arg( 'mmm_sub_done', $url ) ) );
		exit;
	}

	/**
	 * Admin row action: unsubscribe a subscription.
	 *
	 * @return void
	 */
	public function admin_action_unsubscribe(): void {
		$sub_id = $this->verify_action( 'mmm_sub_unsubscribe' );
		$this->mark_unsubscribed( $sub_id );
		$this->redirect_back( 'unsubscribed' );
	}

	/**
	 * Admin row action: re-send the confirmation e-mail for a pending record.
	 *
	 * @return void
	 */
	public function admin_action_resend(): void {

		$sub_id = $this->verify_action( 'mmm_sub_resend' );

		if ( 'pending' === (string) get_post_meta( $sub_id, '_mmm_sub_status', true ) ) {
			$this->send_confirmation_mail( $sub_id );
			$this->redirect_back( 'resent' );
		}

		$this->redirect_back( 'noop' );
	}

	/**
	 * Shows the success notice after a row action.
	 *
	 * @return void
	 */
	public function admin_notices(): void {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['mmm_sub_done'] ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || self::POST_TYPE !== $screen->post_type ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$result = sanitize_key( wp_unslash( $_GET['mmm_sub_done'] ) );

		$messages = array(
			'unsubscribed' => __( 'Subscription unsubscribed and the address added to the suppression list.', 'mixmymeals' ),
			'resent'       => __( 'Confirmation e-mail re-sent.', 'mixmymeals' ),
			'noop'         => __( 'Nothing to do for this subscription.', 'mixmymeals' ),
		);

		if ( isset( $messages[ $result ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $messages[ $result ] ) . '</p></div>';
		}
	}
}
