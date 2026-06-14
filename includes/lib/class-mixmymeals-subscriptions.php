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

		add_action( 'wp_ajax_mmm_subscribe', array( $this, 'ajax_subscribe' ) );
		add_action( 'wp_ajax_nopriv_mmm_subscribe', array( $this, 'ajax_subscribe' ) );

		add_action( 'template_redirect', array( $this, 'handle_endpoints' ) );

		// The [mmm_meal_plan_subscribe] shortcode is registered centrally in
		// Mixmymeals_Shortcodes and delegates to self::render_form().

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
	 * Returns the global schedule as a clean list of rows.
	 *
	 * @return array[] Each: array{ day:int(1-7), time:string"HH:MM", template:string }.
	 */
	public static function get_schedule(): array {

		$raw = get_option( self::OPT_SCHEDULE, array() );

		if ( ! is_array( $raw ) ) {
			return array();
		}

		$rows = array();

		foreach ( $raw as $row ) {

			$day = isset( $row['day'] ) ? (int) $row['day'] : 0;
			if ( $day < 1 || $day > 7 ) {
				continue;
			}

			$time = isset( $row['time'] ) ? trim( (string) $row['time'] ) : '';
			if ( ! preg_match( '/^([01]?\d|2[0-3]):[0-5]\d$/', $time ) ) {
				continue;
			}

			$template = isset( $row['template'] ) ? sanitize_text_field( $row['template'] ) : '';
			if ( '' === $template ) {
				continue;
			}

			$rows[] = array(
				'day'      => $day,
				'time'     => $time,
				'template' => $template,
			);
		}

		return $rows;
	}

	/**
	 * Built-in template ids mapped to their human label.
	 *
	 * @return array<string,string>
	 */
	public static function builtin_templates(): array {
		return array(
			'builtin:meals'    => __( 'Today\'s meals in the plan', 'mixmymeals' ),
			'builtin:shopping' => __( 'Shopping list for the plan', 'mixmymeals' ),
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
				'show_in_menu'    => 'edit.php?post_type=mmm_meal_plan',
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

		$atts    = shortcode_atts( array( 'plan_id' => 0 ), $atts, 'mmm_meal_plan_subscribe' );
		$plan_id = (int) $atts['plan_id'];

		if ( ! $plan_id && is_singular( 'mmm_meal_plan' ) ) {
			$plan_id = get_the_ID();
		}

		if ( ! $plan_id || get_post_type( $plan_id ) !== 'mmm_meal_plan' ) {
			return '';
		}

		if ( empty( self::get_schedule() ) ) {
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
			<div class="mmm-subscribe-consent">
				<label>
					<input type="checkbox" class="mmm-subscribe-agree" required>
					<span><?php echo wp_kses_post( $consent ); ?></span>
				</label>
			</div>
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
		$agree   = isset( $_POST['agree'] ) && '1' === (string) wp_unslash( $_POST['agree'] );

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

		if ( 'on' === get_option( self::OPT_LOG_IP, '' ) ) {
			update_post_meta( $sub_id, '_mmm_sub_consent_ip', $ip );
		}

		$this->send_confirmation_mail( $sub_id );

		wp_send_json_success(
			array(
				'message' => __( 'Almost done! Please check your inbox and confirm your subscription.', 'mixmymeals' ),
			)
		);
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
			$this->finish( __( 'Your subscription is already confirmed. Thank you!', 'mixmymeals' ) );
		}

		if ( 'pending' !== $status ) {
			$this->finish( __( 'This subscription can no longer be confirmed.', 'mixmymeals' ) );
		}

		$plan_id = (int) get_post_meta( $sub_id, '_mmm_sub_plan_id', true );

		if ( get_post_type( $plan_id ) !== 'mmm_meal_plan' ) {
			$this->finish( __( 'The meal plan is no longer available.', 'mixmymeals' ) );
		}

		$sends = $this->compute_sends( $plan_id );

		update_post_meta( $sub_id, '_mmm_sub_status', 'confirmed' );
		update_post_meta( $sub_id, '_mmm_sub_confirm_time', time() );
		update_post_meta( $sub_id, '_mmm_sub_sends', $sends );

		// Re-consent: lift any previous opt-out for this address.
		$email = (string) get_post_meta( $sub_id, '_mmm_sub_email', true );
		if ( $email && class_exists( 'Mixmymeals_Mailer' ) ) {
			Mixmymeals_Mailer::remove_suppression( $email );
		}

		$this->finish( __( 'Thank you! Your subscription is now confirmed.', 'mixmymeals' ), true );
	}

	/**
	 * Unsubscribes a subscription and suppresses the address.
	 *
	 * @param int $sub_id Subscription post ID.
	 * @return void
	 */
	private function unsubscribe( int $sub_id ): void {

		update_post_meta( $sub_id, '_mmm_sub_status', 'unsubscribed' );
		update_post_meta( $sub_id, '_mmm_sub_sends', array() );

		$email = (string) get_post_meta( $sub_id, '_mmm_sub_email', true );
		if ( $email && class_exists( 'Mixmymeals_Mailer' ) ) {
			Mixmymeals_Mailer::add_suppression( $email );
		}

		$this->finish( __( 'You have been unsubscribed. Sorry to see you go!', 'mixmymeals' ), true );
	}

	/**
	 * Finds a subscription by its token.
	 *
	 * @param string $token Token.
	 * @return int Subscription ID or 0.
	 */
	private function find_by_token( string $token ): int {

		if ( '' === $token ) {
			return 0;
		}

		$ids = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'publish',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'       => array(
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
	 * DRIP COMPUTATION
	 * ---------------------------------------------------------- */

	/**
	 * Builds the concrete one-time send list for a plan from the global schedule.
	 *
	 * Anchors the plan to the next occurrence of its start weekday and maps each
	 * schedule row whose weekday is part of the plan to a concrete timestamp.
	 *
	 * @param int $plan_id Meal plan ID.
	 * @return array[] Each: array{ ts:int, template:string, day:int, sent:bool }.
	 */
	private function compute_sends( int $plan_id ): array {

		$schedule = self::get_schedule();
		if ( empty( $schedule ) ) {
			return array();
		}

		$plan      = new Mixmymeals_Meal_Plan( $plan_id );
		$day_range = $plan->get_day_range();
		if ( empty( $day_range ) ) {
			return array();
		}

		$tz            = wp_timezone();
		$today         = new DateTimeImmutable( 'today', $tz );
		$start_weekday = max( 1, min( 7, (int) $plan->get_start_day() ) );
		$today_weekday = (int) $today->format( 'N' );
		$diff          = ( $start_weekday - $today_weekday + 7 ) % 7;
		$plan_start    = $today->modify( "+{$diff} days" );

		$sends = array();

		foreach ( $schedule as $row ) {

			$offset = array_search( $row['day'], $day_range, true );
			if ( false === $offset ) {
				continue; // Weekday not part of this plan.
			}

			list( $h, $m ) = array_map( 'intval', explode( ':', $row['time'] ) );

			$dt = $plan_start->modify( "+{$offset} days" )->setTime( $h, $m );

			$sends[] = array(
				'ts'       => $dt->getTimestamp(),
				'template' => $row['template'],
				'day'      => $row['day'],
				'sent'     => false,
			);
		}

		usort( $sends, static fn( $a, $b ) => $a['ts'] <=> $b['ts'] );

		return $sends;
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

		$sends = get_post_meta( $sub_id, '_mmm_sub_sends', true );
		if ( ! is_array( $sends ) || empty( $sends ) ) {
			update_post_meta( $sub_id, '_mmm_sub_status', 'completed' );
			return;
		}

		$plan_id = (int) get_post_meta( $sub_id, '_mmm_sub_plan_id', true );
		$email   = (string) get_post_meta( $sub_id, '_mmm_sub_email', true );

		if ( get_post_type( $plan_id ) !== 'mmm_meal_plan' || 'publish' !== get_post_status( $plan_id ) ) {
			return; // Plan gone/unpublished: hold off (do not lose the record).
		}

		$changed   = false;
		$remaining = 0;

		foreach ( $sends as $i => $send ) {

			if ( ! empty( $send['sent'] ) ) {
				continue;
			}

			if ( (int) $send['ts'] > $now ) {
				++$remaining;
				continue;
			}

			$ok = $this->send_drip_mail( $sub_id, $plan_id, $email, (int) $send['day'], (string) $send['template'] );

			if ( $ok ) {
				$sends[ $i ]['sent'] = true;
				$changed             = true;
			} else {
				++$remaining; // Retry next run.
			}
		}

		if ( $changed ) {
			update_post_meta( $sub_id, '_mmm_sub_sends', $sends );
		}

		if ( 0 === $remaining ) {
			update_post_meta( $sub_id, '_mmm_sub_status', 'completed' );
		}
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
		$body .= '<p style="color:#6B665B;font-size:14px;">'
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
	 * Sends one drip day e-mail (built-in content or custom template).
	 *
	 * @param int    $sub_id   Subscription ID.
	 * @param int    $plan_id  Plan ID.
	 * @param string $email    Recipient.
	 * @param int    $day      Weekday (1-7).
	 * @param string $template Template id.
	 * @return bool
	 */
	private function send_drip_mail( int $sub_id, int $plan_id, string $email, int $day, string $template ): bool {

		$token = (string) get_post_meta( $sub_id, '_mmm_sub_token', true );
		$plan  = new Mixmymeals_Meal_Plan( $plan_id );

		$unsubscribe_url = $this->endpoint_url( 'unsubscribe', $token );
		$plan_title      = get_the_title( $plan_id );

		$vars = array(
			'plan'            => $plan_title,
			'plan_url'        => get_permalink( $plan_id ),
			'unsubscribe_url' => $unsubscribe_url,
		);

		$args = array(
			'layout'          => 'layout.html',
			'transactional'   => true,
			'unsubscribe_url' => $unsubscribe_url,
		);

		if ( 'builtin:meals' === $template ) {
			$subject = sprintf(
				/* translators: %s: meal plan title. */
				__( 'Your meals today: %s', 'mixmymeals' ),
				$plan_title
			);
			$body         = $this->render_meals_html( $plan, $day );
			$args['inline'] = true;
		} elseif ( 'builtin:shopping' === $template ) {
			$subject = sprintf(
				/* translators: %s: meal plan title. */
				__( 'Shopping list: %s', 'mixmymeals' ),
				$plan_title
			);
			$body         = $this->render_shopping_html( $plan );
			$args['inline'] = true;
		} elseif ( str_starts_with( $template, 'file:' ) ) {
			$subject = sprintf(
				/* translators: %s: meal plan title. */
				__( 'Your meal plan: %s', 'mixmymeals' ),
				$plan_title
			);
			$body = substr( $template, 5 ); // template file name; send_template loads it.
		} else {
			return false;
		}

		return Mixmymeals_Mailer::send_template( $email, $subject, $body, $vars, $args );
	}

	/**
	 * Builds the "today's meals" HTML for a plan weekday.
	 *
	 * @param Mixmymeals_Meal_Plan $plan Plan.
	 * @param int                  $day  Weekday (1-7).
	 * @return string
	 */
	private function render_meals_html( Mixmymeals_Meal_Plan $plan, int $day ): string {

		$types = Mixmymeals_Meal::get_plaintext_meal_type_map();
		$items = array();

		foreach ( $plan->get_meals() as $meal_post ) {

			$meal = new Mixmymeals_Meal( $meal_post->ID );

			if ( (int) $meal->get_day() !== $day ) {
				continue;
			}

			$title    = get_the_title( $meal_post->ID );
			$link     = get_permalink( $meal_post->ID );
			$type     = $meal->get_type();
			$type_lbl = $types[ $type ] ?? '';
			$servings = $meal->get_servings();

			$items[] = '<li style="margin:0 0 10px;">'
				. '<a href="' . esc_url( $link ) . '" style="font-weight:700;color:#3F6F1E;text-decoration:none;">' . esc_html( $title ) . '</a>'
				. ( $type_lbl ? ' <span style="color:#6B665B;">· ' . esc_html( $type_lbl ) . '</span>' : '' )
				. ( $servings ? ' <span style="color:#6B665B;">· ' . esc_html( sprintf( /* translators: %d: number of servings. */ _n( '%d serving', '%d servings', $servings, 'mixmymeals' ), $servings ) ) . '</span>' : '' )
				. '</li>';
		}

		$heading = '<h1>' . esc_html__( 'Your meals today', 'mixmymeals' ) . '</h1>';

		if ( empty( $items ) ) {
			return $heading . '<p>' . esc_html__( 'No meals are planned for today.', 'mixmymeals' ) . '</p>';
		}

		return $heading
			. '<ul style="list-style:none;padding:0;margin:0 0 16px;">' . implode( '', $items ) . '</ul>'
			. '<p><a href="' . esc_url( get_permalink( $plan->get_id() ) ) . '">' . esc_html__( 'Open the full plan', 'mixmymeals' ) . '</a></p>';
	}

	/**
	 * Builds the shopping list HTML for a plan.
	 *
	 * @param Mixmymeals_Meal_Plan $plan Plan.
	 * @return string
	 */
	private function render_shopping_html( Mixmymeals_Meal_Plan $plan ): string {

		// Structure: [ category_name => [ ing_id => { total_weight, units, ... } ] ].
		$list    = $plan->calc_shopping_list();
		$heading = '<h1>' . esc_html__( 'Shopping list', 'mixmymeals' ) . '</h1>';

		if ( empty( $list ) ) {
			return $heading . '<p>' . esc_html__( 'The shopping list is empty.', 'mixmymeals' ) . '</p>';
		}

		$out = $heading;

		foreach ( $list as $cat_name => $ings ) {

			$out .= '<h2 style="font-size:16px;color:#3F6F1E;margin:16px 0 6px;">' . esc_html( (string) $cat_name ) . '</h2>';
			$out .= '<ul style="list-style:none;padding:0;margin:0;">';

			foreach ( (array) $ings as $ing_id => $data ) {

				$ingredient = new Mixmymeals_Ingredient( (int) $ing_id );
				if ( ! $ingredient->is_valid() ) {
					continue;
				}

				$name   = get_the_title( (int) $ing_id );
				$amount = Mixmymeals_Shortcodes::build_needed_amount_string(
					$data['units'] ?? array(),
					(float) ( $data['total_weight'] ?? 0 )
				);

				$out .= '<li style="padding:4px 0;border-bottom:1px solid #ECE6D9;">'
					. '<strong style="font-weight:600;">' . esc_html( $name ) . '</strong>'
					. ( '' !== $amount ? ' <span style="color:#6B665B;">' . esc_html( $amount ) . '</span>' : '' )
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
	 * @return void
	 */
	private function prune(): void {

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

		foreach ( $pending as $sub_id ) {
			$consent = (int) get_post_meta( (int) $sub_id, '_mmm_sub_consent_time', true );
			if ( $consent && $consent < $pending_before ) {
				wp_delete_post( (int) $sub_id, true );
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
			if ( $post && strtotime( $post->post_modified_gmt . ' UTC' ) < $retention_before ) {
				wp_delete_post( (int) $sub_id, true );
			}
		}
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
}
