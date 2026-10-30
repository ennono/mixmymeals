<?php
/**
 * GDPR / privacy integration: WordPress personal-data exporters and erasers
 * for the personal data the plugin stores (newsletter subscriptions and
 * visitor meal-photo uploads), plus a suggested privacy-policy text.
 *
 * Recipe/plan ratings are stored as WordPress comments (with the rater's
 * name/e-mail), so WordPress core's own comment exporter and eraser already
 * cover them — they are intentionally not duplicated here.
 *
 * @package MixMyMeals/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers privacy exporters, erasers and suggested policy content.
 */
class Mixmymeals_Privacy {

	const SUBSCRIPTION_POST_TYPE = 'mmm_subscription';

	/**
	 * Option: stop WordPress loading its emoji script and images from s.w.org.
	 */
	const OPT_DISABLE_EMOJI = 'mixmymeals_disable_wp_emoji';

	public function __construct() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_erasers' ) );
		add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );

		// Front-end hooks exist by init; wp-admin's own (admin-filters.php)
		// are only added after it, so the admin side waits for admin_init.
		add_action( 'init', array( __CLASS__, 'maybe_disable_emoji' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_disable_emoji' ) );
	}

	/**
	 * Whether WordPress's emoji support is switched off (Privacy Policy tab).
	 *
	 * @return bool
	 */
	public static function emoji_disabled(): bool {
		return 'on' === (string) get_option( self::OPT_DISABLE_EMOJI, '' );
	}

	/**
	 * Unhooks WordPress's emoji support when the option says so.
	 *
	 * Core prints a detection script on every page; where the browser lacks
	 * an emoji, it replaces it with an image from s.w.org — a server of the
	 * WordPress Foundation in the USA, which then receives the visitor's IP
	 * address. It is the one third-party request the site makes, and the only
	 * reason a privacy policy for it needs a section on embedded content.
	 * Browsers have drawn emoji themselves for years, so nothing visible is
	 * lost. Mails are covered too: wp_staticize_emoji_for_email() turns an
	 * emoji in any wp_mail() into an <img> on s.w.org, which the recipient's
	 * mail client would load.
	 *
	 * @return void
	 */
	public static function maybe_disable_emoji(): void {

		if ( ! self::emoji_disabled() ) {
			return;
		}

		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'embed_head', 'print_emoji_detection_script' );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
		remove_action( 'enqueue_embed_scripts', 'wp_enqueue_emoji_styles' );
		remove_action( 'admin_enqueue_scripts', 'wp_enqueue_emoji_styles' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

		// The classic editor's emoji plugin loads the same images.
		add_filter(
			'tiny_mce_plugins',
			static fn( $plugins ) => is_array( $plugins ) ? array_values( array_diff( $plugins, array( 'wpemoji' ) ) ) : $plugins
		);
	}

	/* ---------------------------------------------------------------------
	 * Registration
	 * ------------------------------------------------------------------ */

	/**
	 * Registers the personal-data exporters.
	 *
	 * @param array $exporters Existing exporters.
	 * @return array
	 */
	public function register_exporters( array $exporters ): array {
		$exporters['mixmymeals-subscriptions'] = array(
			'exporter_friendly_name' => __( 'MixMyMeals newsletter subscriptions', 'mixmymeals' ),
			'callback'               => array( $this, 'export_subscriptions' ),
		);
		$exporters['mixmymeals-photos'] = array(
			'exporter_friendly_name' => __( 'MixMyMeals meal photos', 'mixmymeals' ),
			'callback'               => array( $this, 'export_photos' ),
		);
		return $exporters;
	}

	/**
	 * Registers the personal-data erasers.
	 *
	 * @param array $erasers Existing erasers.
	 * @return array
	 */
	public function register_erasers( array $erasers ): array {
		$erasers['mixmymeals-subscriptions'] = array(
			'eraser_friendly_name' => __( 'MixMyMeals newsletter subscriptions', 'mixmymeals' ),
			'callback'             => array( $this, 'erase_subscriptions' ),
		);
		$erasers['mixmymeals-photos'] = array(
			'eraser_friendly_name' => __( 'MixMyMeals meal photos', 'mixmymeals' ),
			'callback'             => array( $this, 'erase_photos' ),
		);
		return $erasers;
	}

	/* ---------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Subscription post IDs for one e-mail address.
	 *
	 * @param string $email E-mail address.
	 * @return int[]
	 */
	private function subscription_ids_for( string $email ): array {
		return get_posts( array(
			'post_type'      => self::SUBSCRIPTION_POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => 500,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_key'       => '_mmm_sub_email', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- targeted lookup on an indexed key, not a scan.
			'meta_value'     => strtolower( $email ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- exact-match lookup; the alternative would be fetching everything and filtering in PHP.
		) );
	}

	/* ---------------------------------------------------------------------
	 * Subscriptions
	 * ------------------------------------------------------------------ */

	/**
	 * Exports newsletter subscription data for one e-mail address.
	 *
	 * @param string $email E-mail address.
	 * @param int    $page  Page (unused; a person has few subscriptions).
	 * @return array
	 */
	public function export_subscriptions( string $email, int $page = 1 ): array {

		$export = array();

		foreach ( $this->subscription_ids_for( $email ) as $sub_id ) {

			$consent_time = (int) get_post_meta( $sub_id, '_mmm_sub_consent_time', true );
			$confirm_time = (int) get_post_meta( $sub_id, '_mmm_sub_confirm_time', true );
			$plan_id      = (int) get_post_meta( $sub_id, '_mmm_sub_plan_id', true );

			$data = array(
				array( 'name' => __( 'E-mail', 'mixmymeals' ), 'value' => get_post_meta( $sub_id, '_mmm_sub_email', true ) ),
				array( 'name' => __( 'Name', 'mixmymeals' ), 'value' => get_post_meta( $sub_id, '_mmm_sub_name', true ) ),
				array( 'name' => __( 'Status', 'mixmymeals' ), 'value' => get_post_meta( $sub_id, '_mmm_sub_status', true ) ),
				array( 'name' => __( 'Subscribed plan', 'mixmymeals' ), 'value' => $plan_id ? get_the_title( $plan_id ) : '' ),
			);
			if ( $consent_time ) {
				$data[] = array( 'name' => __( 'Consent given at', 'mixmymeals' ), 'value' => wp_date( 'c', $consent_time ) );
			}
			if ( $confirm_time ) {
				$data[] = array( 'name' => __( 'Confirmed at', 'mixmymeals' ), 'value' => wp_date( 'c', $confirm_time ) );
			}
			$consent_ip = (string) get_post_meta( $sub_id, '_mmm_sub_consent_ip', true );
			if ( '' !== $consent_ip ) {
				$data[] = array( 'name' => __( 'Consent IP address', 'mixmymeals' ), 'value' => $consent_ip );
			}

			$export[] = array(
				'group_id'    => 'mixmymeals-subscriptions',
				'group_label' => __( 'MixMyMeals newsletter subscriptions', 'mixmymeals' ),
				'item_id'     => 'mmm-subscription-' . $sub_id,
				'data'        => $data,
			);
		}

		return array( 'data' => $export, 'done' => true );
	}

	/**
	 * Erases newsletter subscriptions for one e-mail address.
	 *
	 * @param string $email E-mail address.
	 * @param int    $page  Page (unused).
	 * @return array
	 */
	public function erase_subscriptions( string $email, int $page = 1 ): array {

		$removed = false;

		foreach ( $this->subscription_ids_for( $email ) as $sub_id ) {
			// Anonymise this subscription's photos before deleting it — the
			// link (_mmm_photo_sub) would otherwise be lost and the photo
			// eraser (which may run afterwards) could no longer find them.
			$this->anonymize_photos( $this->photo_ids_by_sub( array( $sub_id ) ) );

			wp_delete_post( $sub_id, true );
			$removed = true;
		}

		return array(
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/* ---------------------------------------------------------------------
	 * Meal photos (visitor uploads)
	 * ------------------------------------------------------------------ */

	/**
	 * Attachment IDs of meal photos uploaded by one e-mail address (linked
	 * either via the subscription that uploaded them, or via a matching user).
	 *
	 * @param string $email E-mail address.
	 * @return int[]
	 */
	private function photo_ids_for( string $email ): array {

		$ids = $this->photo_ids_by_sub( $this->subscription_ids_for( $email ) );

		$user = get_user_by( 'email', $email );
		if ( $user ) {
			$ids = array_merge( $ids, $this->photo_ids_by_author( (int) $user->ID ) );
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Meal-photo attachment IDs linked to the given subscription IDs.
	 *
	 * @param int[] $sub_ids Subscription post IDs.
	 * @return int[]
	 */
	private function photo_ids_by_sub( array $sub_ids ): array {

		if ( ! $sub_ids ) {
			return array();
		}

		return get_posts( array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 500,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array( 'key' => '_mmm_meal_photo', 'compare' => 'EXISTS' ),
				array( 'key' => '_mmm_photo_sub', 'value' => array_map( 'intval', $sub_ids ), 'compare' => 'IN' ),
			),
		) );
	}

	/**
	 * Meal-photo attachment IDs uploaded by a logged-in user.
	 *
	 * @param int $user_id User ID.
	 * @return int[]
	 */
	private function photo_ids_by_author( int $user_id ): array {

		if ( ! $user_id ) {
			return array();
		}

		return get_posts( array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 500,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'author'         => $user_id,
			'meta_key'       => '_mmm_meal_photo', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- targeted lookup on an indexed key, not a scan.
		) );
	}

	/**
	 * Removes the personal parts (displayed name + person link) of the given
	 * meal photos, keeping the image as anonymous gallery content.
	 *
	 * @param int[] $att_ids Attachment IDs.
	 * @return bool Whether anything was anonymised.
	 */
	private function anonymize_photos( array $att_ids ): bool {

		$done = false;
		foreach ( $att_ids as $att_id ) {
			delete_post_meta( (int) $att_id, '_mmm_photo_by' );
			delete_post_meta( (int) $att_id, '_mmm_photo_sub' );
			$done = true;
		}

		return $done;
	}

	/**
	 * Exports meal-photo data (uploader name + URL) for one e-mail address.
	 *
	 * @param string $email E-mail address.
	 * @param int    $page  Page (unused).
	 * @return array
	 */
	public function export_photos( string $email, int $page = 1 ): array {

		$export = array();

		foreach ( $this->photo_ids_for( $email ) as $att_id ) {
			$export[] = array(
				'group_id'    => 'mixmymeals-photos',
				'group_label' => __( 'MixMyMeals meal photos', 'mixmymeals' ),
				'item_id'     => 'mmm-photo-' . $att_id,
				'data'        => array(
					array( 'name' => __( 'Displayed name', 'mixmymeals' ), 'value' => get_post_meta( $att_id, '_mmm_photo_by', true ) ),
					array( 'name' => __( 'Photo', 'mixmymeals' ), 'value' => wp_get_attachment_url( $att_id ) ),
				),
			);
		}

		return array( 'data' => $export, 'done' => true );
	}

	/**
	 * Erases the personal parts of meal photos (the displayed name and the
	 * link to the person). The image itself is kept as anonymous gallery
	 * content.
	 *
	 * @param string $email E-mail address.
	 * @param int    $page  Page (unused).
	 * @return array
	 */
	public function erase_photos( string $email, int $page = 1 ): array {

		// Subscription-linked photos are anonymised by the subscription eraser
		// (before the link is deleted); here we handle photos uploaded by a
		// logged-in user, plus any subscription-linked ones still present.
		$ids = $this->photo_ids_for( $email );

		$removed = $this->anonymize_photos( $ids );

		return array(
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => $removed
				? array( __( 'Meal photos were anonymised (name and link to the person removed).', 'mixmymeals' ) )
				: array(),
			'done'           => true,
		);
	}

	/* ---------------------------------------------------------------------
	 * Suggested privacy-policy content
	 * ------------------------------------------------------------------ */

	/**
	 * The MixMyMeals section of a privacy policy, built from what this site
	 * actually does — the single source for the "Privacy policy" settings tab,
	 * the [mmm_privacy_policy] shortcode and WordPress's own policy guide.
	 *
	 * It replaces a hand-kept text file. That file had to be edited whenever a
	 * setting changed a fact in it, and drifted: it stated a 48-hour window
	 * and a 7-day retention as figures while the code read both from options,
	 * and it never mentioned that the send log also ages out after 90 days.
	 * Here every figure is read from the constant or option that decides it,
	 * and every conditional sentence is printed only where its condition holds
	 * — the consent IP, the required subscription, photo approval, the send
	 * log, subscriptions at all. A sentence about a processing this site does
	 * not do is not a cautious statement, it is an untrue one.
	 *
	 * Wording follows the structure of the generator text stopfnicht.de uses
	 * (data types, persons, purposes, legal bases, then the processings), so
	 * the section reads like the rest of such a policy. It says "within this
	 * website", never "on our own server": almost every site is on rented
	 * hosting, and the host is a processor the policy names elsewhere.
	 *
	 * Add-ons contribute their own list items through the
	 * `mixmymeals_privacy_policy_items` filter (the Social add-on describes its
	 * Pinterest images that way); the host knows nothing about them.
	 *
	 * Not legal advice — an inventory of what the code does, in policy form.
	 *
	 * @param string $heading Heading tag for the section title ('h2', 'h3', …),
	 *                        or '' for none (the WordPress guide adds its own).
	 * @return string Markup, already escaped.
	 */
	public static function policy_section_html( string $heading = 'h2' ): string {

		$subs_on   = class_exists( 'Mixmymeals_Subscriptions' ) && Mixmymeals_Subscriptions::is_enabled();
		$save_mode = $subs_on ? Mixmymeals_Subscriptions::save_offer_mode_effective() : 'off';
		$log_on    = class_exists( 'Mixmymeals_Mail_Log' ) && Mixmymeals_Mail_Log::is_enabled();

		$p = static fn( string $text ): string => '<p>' . esc_html( $text ) . '</p>';

		$html = '';

		if ( in_array( $heading, array( 'h2', 'h3', 'h4' ), true ) ) {
			$html .= '<' . $heading . '>' . esc_html__( 'Recipes, meal planner and e-mail notifications (MixMyMeals)', 'mixmymeals' ) . '</' . $heading . '>';
		}

		$html .= $p( __( 'This website uses the self-hosted software “MixMyMeals” for its recipe and planning features. All processing described below takes place within this website, that is, on the infrastructure of the hosting provider named in the section on providing the online service and web hosting.', 'mixmymeals' ) );

		// The overview paragraphs, assembled from items so each one is only
		// there while the feature behind it is.
		$types = array(
			__( 'inventory data (e.g. names)', 'mixmymeals' ),
			__( 'contact data (e-mail addresses)', 'mixmymeals' ),
			__( 'content data (recipe selection, ratings, reviews, uploaded photos)', 'mixmymeals' ),
			__( 'usage and metadata (timestamps, IP addresses in hashed form)', 'mixmymeals' ),
		);
		if ( $log_on ) {
			$types[] = __( 'sending metadata of e-mails', 'mixmymeals' );
		}

		$persons = array( __( 'users of the website', 'mixmymeals' ) );
		if ( $subs_on ) {
			$persons[] = __( 'e-mail subscribers', 'mixmymeals' );
		}

		$purposes = array(
			__( 'providing the planning features', 'mixmymeals' ),
			__( 'community features (ratings, photos)', 'mixmymeals' ),
			__( 'protection against abuse and overload', 'mixmymeals' ),
		);
		if ( $subs_on ) {
			$purposes[] = __( 'sending subscribed meal plans', 'mixmymeals' );
		}
		if ( $log_on ) {
			$purposes[] = __( 'traceability of e-mail delivery', 'mixmymeals' );
		}

		/* translators: %s: list of data types, separated by semicolons. */
		$html .= $p( sprintf( __( 'Types of data processed: %s.', 'mixmymeals' ), implode( '; ', $types ) ) );
		/* translators: %s: list of groups of persons, separated by semicolons. */
		$html .= $p( sprintf( __( 'Data subjects: %s.', 'mixmymeals' ), implode( '; ', $persons ) ) );
		/* translators: %s: list of purposes, separated by semicolons. */
		$html .= $p( sprintf( __( 'Purposes of processing: %s.', 'mixmymeals' ), implode( '; ', $purposes ) ) );

		/*
		 * Legal bases, one clause per basis. Ratings and photos are not
		 * consent: neither form asks for one, so naming consent would claim
		 * a declaration nobody made — they are the feature the visitor uses
		 * (lit. b). Legitimate interests name the interest itself (Art. 13(1)(d)
		 * GDPR), each beside the processing it justifies.
		 */
		$interests = array(
			__( 'the meal planner (our interest in a planner that works without an account)', 'mixmymeals' ),
			__( 'abuse protection (our interest in protection against automated abuse and overload)', 'mixmymeals' ),
		);
		if ( $log_on ) {
			$interests[] = __( 'the send log (our interest in tracing and proving the delivery of e-mails)', 'mixmymeals' );
		}
		if ( $subs_on ) {
			$interests[] = __( 'keeping finished subscriptions and the suppression list (our interest in being able to prove consents and in honouring objections)', 'mixmymeals' );
		}

		$bases = array();
		if ( $subs_on ) {
			$bases[] = __( 'consent (Art. 6(1)(a) GDPR) for e-mail subscriptions', 'mixmymeals' );
		}
		$bases[] = __( 'provision of the feature used (Art. 6(1)(b) GDPR) for saving plans, ratings and photo uploads', 'mixmymeals' );
		/* translators: %s: processings with the interest behind each, separated by semicolons. */
		$bases[] = sprintf( __( 'legitimate interests (Art. 6(1)(f) GDPR) for %s', 'mixmymeals' ), implode( '; ', $interests ) );

		/* translators: %s: legal bases, separated by semicolons. */
		$html .= $p( sprintf( __( 'Legal bases: %s.', 'mixmymeals' ), implode( '; ', $bases ) ) );
		$html .= $p( __( 'The planner cookie is technically necessary for the function the user requested (§ 25(2) no. 2 TDDDG).', 'mixmymeals' ) );

		$html .= $p( __( 'Further information on processing operations, procedures and services:', 'mixmymeals' ) );

		$items = array();

		$cart_days  = (int) round( Mixmymeals_Cart::EXPIRATION / DAY_IN_SECONDS );
		$share_days = (int) round( Mixmymeals_Cart::SHARE_EXPIRATION / DAY_IN_SECONDS );

		$items['planner'] = sprintf(
			/* translators: 1: cookie name, 2: number of days. */
			_n(
				'Meal planner: for visitors who are not logged in, a cookie named “%1$s” stores a random, pseudonymous identifier that links the recipe selection to the browser; the selection itself is stored on the server and deleted automatically after %2$d day.',
				'Meal planner: for visitors who are not logged in, a cookie named “%1$s” stores a random, pseudonymous identifier that links the recipe selection to the browser; the selection itself is stored on the server and deleted automatically after %2$d days.',
				$cart_days,
				'mixmymeals'
			),
			Mixmymeals_Cart::COOKIE_NAME,
			$cart_days
		);

		/*
		 * Since 3.70.0 the selection may say who eats what. Only by number —
		 * the planner asks for no names (Mixmymeals_Cart::get_eaters()) — so
		 * this is a fact about the selection, not a new kind of data, and it
		 * is said wherever the selection is described.
		 */
		$items['planner'] .= ' ' . __( 'Where the planner lists several people, it also stores how many there are, which of them eat each recipe and how many portions each of them eats; they are numbered only (“Person 1”, “Person 2”), no names are asked for.', 'mixmymeals' );

		$items['share'] = sprintf(
			/* translators: %d: number of days. */
			_n(
				'Sharing the planner: using the share button deliberately creates a copy of the recipe selection. It is stored on the server under a random identifier of its own, can be opened by anyone holding the generated link, and is deleted automatically after %d day. The copy is frozen: later changes to one\'s own planner do not appear in it, and the link gives no access to the planner itself. Only recipe IDs, servings, meal type, weekday and which of the numbered people eat along, with their portions, are stored — no identifier of the person sharing. No copy exists unless someone shares.',
				'Sharing the planner: using the share button deliberately creates a copy of the recipe selection. It is stored on the server under a random identifier of its own, can be opened by anyone holding the generated link, and is deleted automatically after %d days. The copy is frozen: later changes to one\'s own planner do not appear in it, and the link gives no access to the planner itself. Only recipe IDs, servings, meal type, weekday and which of the numbered people eat along, with their portions, are stored — no identifier of the person sharing. No copy exists unless someone shares.',
				$share_days,
				'mixmymeals'
			),
			$share_days
		);

		/*
		 * Saving a plan publishes it — the public is a recipient (Art. 13(1)(e)
		 * GDPR) and was not named. Each sentence follows what decides it: who
		 * may save (public mode), whether a guest's plan waits as a draft, the
		 * author name on plans of logged-in users (the plan's author box falls
		 * back to the WordPress account), and the cleanup of plans nobody
		 * categorised.
		 */
		$public_mode = 'on' === (string) get_option( 'mixmymeals_public_mode', '' );

		$plans = __( 'Saving plans: a plan saved in the planner becomes a publicly accessible page showing its recipes, servings and weekdays.', 'mixmymeals' );
		// Who eats what goes public with the plan, by number only. Names our
		// editors may note in the administration stay there (3.70.0).
		$plans .= ' ' . __( 'Where a plan lists several people, which of them eat each meal and how many portions each eats is saved with it and the page shows nutrition figures for each of them, naming them by number only (“Person 1”, “Person 2”); names our editors may note for them in the administration are not published.', 'mixmymeals' );
		if ( $public_mode ) {
			$plans .= ' ' . ( 'on' === (string) get_option( 'mixmymeals_save_meal_plans_published_by_visitors_as_draft', '' )
				? __( 'Plans saved by visitors who are not logged in are first stored as drafts and only appear publicly once we publish them; no name is stored with them.', 'mixmymeals' )
				: __( 'Visitors who are not logged in can save plans too; no name is stored with them.', 'mixmymeals' ) );
		}
		$plans .= ' ' . __( 'For logged-in users, the display name of their account is shown as the author.', 'mixmymeals' );
		if ( class_exists( 'Mixmymeals_Cleanup' ) && Mixmymeals_Cleanup::is_enabled() ) {
			$min_age = Mixmymeals_Cleanup::get_min_age_days();
			$plans  .= ' ' . sprintf(
				/* translators: %d: number of days. */
				_n(
					'Plans we do not assign to a category are deleted automatically %d day after they were saved.',
					'Plans we do not assign to a category are deleted automatically %d days after they were saved.',
					$min_age,
					'mixmymeals'
				),
				$min_age
			);
		} else {
			// Art. 13(2)(a) GDPR wants a storage period either way; without the
			// cleanup there is none but deletion on request, and that is said.
			$plans .= ' ' . __( 'Saved plans are kept until their deletion is requested.', 'mixmymeals' );
		}
		$items['plans'] = $plans;

		// The save counter only exists where visitors may save plans and an
		// hourly limit is set (mmm_create_meal_plan_ajax_callback()). The PDF
		// download and its counter moved into the MixMyMeals Printer add-on
		// (3.61.0), which describes them through the filter below.
		$save_counter = $public_mode
			&& (int) get_option( 'mixmymeals_published_meal_plans_per_hour_limit', 0 ) > 0;

		if ( $save_counter ) {
			$items['abuse'] = __( 'Abuse protection: when a visitor saves a meal plan, the IP address is stored in hashed (pseudonymised) form as a short-lived counter and deleted automatically after an hour.', 'mixmymeals' );
		}

		/*
		 * Two routes, two different records: the comment form of logged-in
		 * users runs through wp_new_comment(), which stores IP address and
		 * user agent; the subscriber link goes through wp_insert_comment()
		 * (Mixmymeals_Ratings::add_subscriber_rating()), which stores neither
		 * and approves at once.
		 */
		$items['ratings'] = ( $subs_on
				? __( 'Recipe ratings: logged-in users and e-mail subscribers (via a personalised link) can leave star ratings and reviews. These are published as a comment with the name given and stored together with the e-mail address, until the author objects or requests deletion. Ratings via the subscriber link appear immediately and are stored without an IP address.', 'mixmymeals' )
				: __( 'Recipe ratings: logged-in users can leave star ratings and reviews. These are published as a comment with the name given and stored together with the e-mail address, until the author objects or requests deletion.', 'mixmymeals' ) )
			. ' ' . __( 'Ratings submitted through the comment form are processed by WordPress\'s comment function, which also stores the IP address and the browser identification (user agent).', 'mixmymeals' );

		$photo_review = class_exists( 'Mixmymeals_Photos' ) && Mixmymeals_Photos::is_auto_approved()
			? __( 'Photos appear publicly without prior review.', 'mixmymeals' )
			: __( 'Photos are reviewed editorially before they appear publicly.', 'mixmymeals' );

		$items['photos'] = ( $subs_on
				? __( 'Meal photos: logged-in users and subscribers (via a personalised link) can upload photos of dishes they cooked. The photo and the display name given are stored; embedded metadata of the image file (EXIF, including GPS location data) is removed automatically on upload.', 'mixmymeals' )
				: __( 'Meal photos: logged-in users can upload photos of dishes they cooked. The photo and the display name given are stored; embedded metadata of the image file (EXIF, including GPS location data) is removed automatically on upload.', 'mixmymeals' ) )
			. ' ' . $photo_review . ' '
			. __( 'They are kept until consent is withdrawn or deletion is requested.', 'mixmymeals' );

		if ( $subs_on ) {

			$sub = 'off' !== $save_mode
				? __( 'E-mail subscriptions: sign-ups use the double opt-in procedure, either on the page of a meal plan or when saving a plan in the planner.', 'mixmymeals' )
				: __( 'E-mail subscriptions: sign-ups use the double opt-in procedure, on the page of a meal plan.', 'mixmymeals' );

			// What the mails contain — the consent covers exactly this, and the
			// rating and photo invitations are what makes them advertising in
			// the sense of § 7 UWG, so they are named rather than implied.
			$sub .= ' ' . ( '' !== trim( (string) get_option( Mixmymeals_Subscriptions::OPT_APPEND_TEXT, '' ) )
				? __( 'The e-mails contain the meals and the shopping list of the subscribed plan, links for rating its recipes and sharing photos, and a short additional text from us.', 'mixmymeals' )
				: __( 'The e-mails contain the meals and the shopping list of the subscribed plan and links for rating its recipes and sharing photos.', 'mixmymeals' ) );

			// Art. 13(2)(e) GDPR: whether the address is required, and what
			// follows if it is not given or not confirmed.
			if ( Mixmymeals_Subscriptions::SAVE_OFFER_REQUIRED === $save_mode ) {
				$sub .= ' ' . sprintf(
					/* translators: %s: confirmation window, e.g. "48 hours". */
					__( 'Providing an e-mail address is required to save a plan in the planner; without it the plan cannot be saved. If the sign-up is not confirmed, the plan stays saved, the address is deleted after %s and no further e-mails are sent.', 'mixmymeals' ),
					/* translators: %d: number of hours. */
					sprintf( _n( '%d hour', '%d hours', Mixmymeals_Subscriptions::get_pending_hours(), 'mixmymeals' ), Mixmymeals_Subscriptions::get_pending_hours() )
				);
			} elseif ( 'off' !== $save_mode ) {
				$sub .= ' ' . __( 'Giving an e-mail address when saving a plan is voluntary; the plan is saved without it.', 'mixmymeals' );
			}

			$sub .= ' ' . __( 'Stored are the e-mail address, optionally a name, the subscribed meal plan, the times of sign-up and confirmation, and an access token that the links in the e-mails rely on. To limit abuse, the IP address is additionally stored in hashed form for at most one hour when signing up on a plan\'s page.', 'mixmymeals' );

			$sub .= ' ' . ( 'on' === get_option( Mixmymeals_Subscriptions::OPT_LOG_IP, '' )
				? __( 'As proof of consent, the full IP address used to sign up is also stored with the subscription record and deleted with it.', 'mixmymeals' )
				: __( 'No IP address is stored as proof of consent.', 'mixmymeals' ) );

			$pending   = Mixmymeals_Subscriptions::get_pending_hours();
			$retention = Mixmymeals_Subscriptions::get_retention_days();

			$sub .= ' ' . sprintf(
				/* translators: 1: number of hours, 2: number of days. */
				__( 'Deletion: unconfirmed sign-ups are deleted automatically after %1$s. A running subscription is kept while it runs; finished or cancelled subscriptions are deleted %2$s after they ended and are kept until then as proof of consent (Art. 7(1), Art. 6(1)(f) GDPR).', 'mixmymeals' ),
				/* translators: %d: number of hours. */
				sprintf( _n( '%d hour', '%d hours', $pending, 'mixmymeals' ), $pending ),
				/* translators: %d: number of days. */
				sprintf( _n( '%d day', '%d days', $retention, 'mixmymeals' ), $retention )
			);

			$items['subscriptions'] = $sub;

			$items['suppression'] = __( 'Suppression list: unsubscribed e-mail addresses are kept in an internal suppression list so that the objection is honoured permanently (Art. 6(1)(f) GDPR in conjunction with Art. 21(3) GDPR).', 'mixmymeals' );
		}

		if ( $log_on ) {

			$max  = Mixmymeals_Mail_Log::get_max_entries();
			$days = Mixmymeals_Mail_Log::get_retention_days();

			$log = sprintf(
				/* translators: %d: number of entries. */
				__( 'Send log: to trace delivery problems and prove delivery, a limited log of sent e-mails is kept. It records metadata only — recipient address, subject, delivery status and time — never the content of the messages. The log is limited to the %d most recent entries; older ones are dropped automatically.', 'mixmymeals' ),
				$max
			);

			if ( $days > 0 ) {
				$log .= ' ' . sprintf(
					/* translators: %d: number of days. */
					_n( 'Entries are also deleted after %d day at the latest.', 'Entries are also deleted after %d days at the latest.', $days, 'mixmymeals' ),
					$days
				);
			}

			$items['send_log'] = $log;
		}

		/**
		 * Lets add-ons describe their own processing in the MixMyMeals section.
		 *
		 * Items are plain text (escaped on output), keyed so a later filter can
		 * replace or remove one.
		 *
		 * @param array<string,string> $items Plain-text list items.
		 */
		$items = (array) apply_filters( 'mixmymeals_privacy_policy_items', $items );

		$html .= '<ul>';
		foreach ( $items as $item ) {
			$html .= '<li>' . esc_html( (string) $item ) . '</li>';
		}
		$html .= '</ul>';

		$html .= $p( __( 'Access to, export and deletion of the stored data are possible at any time via the channels named in the section on the rights of data subjects.', 'mixmymeals' ) );

		return $html;
	}

	/**
	 * Hands the generated section to WordPress's privacy policy guide
	 * (Settings → Privacy), so a site that builds its policy there gets the
	 * same text as the settings tab and the shortcode.
	 *
	 * @return void
	 */
	public function add_privacy_policy_content(): void {

		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		wp_add_privacy_policy_content( 'MixMyMeals', self::policy_section_html( '' ) );
	}
}
