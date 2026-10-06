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

	public function __construct() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_erasers' ) );
		add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );
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

		$html .= $p(
			$subs_on
				? __( 'Legal bases: consent (Art. 6(1)(a) GDPR) for e-mail subscriptions, ratings and photo uploads; legitimate interests (Art. 6(1)(f) GDPR) for the meal planner, abuse protection, the send log and keeping records. The planner cookie is technically necessary for the function the user requested (§ 25(2) no. 2 TDDDG).', 'mixmymeals' )
				: __( 'Legal bases: consent (Art. 6(1)(a) GDPR) for ratings and photo uploads; legitimate interests (Art. 6(1)(f) GDPR) for the meal planner and abuse protection. The planner cookie is technically necessary for the function the user requested (§ 25(2) no. 2 TDDDG).', 'mixmymeals' )
		);

		$html .= $p( __( 'Further information on processing operations, procedures and services:', 'mixmymeals' ) );

		$items = array();

		$cart_days  = (int) round( Mixmymeals_Cart::EXPIRATION / DAY_IN_SECONDS );
		$share_days = (int) round( Mixmymeals_Cart::SHARE_EXPIRATION / DAY_IN_SECONDS );

		$items['planner'] = sprintf(
			/* translators: 1: cookie name, 2: number of days. */
			_n(
				'Meal planner: for visitors who are not logged in, a cookie named “%1$s” stores a random identifier with no personal reference; the recipe selection itself is stored on the server and deleted automatically after %2$d day.',
				'Meal planner: for visitors who are not logged in, a cookie named “%1$s” stores a random identifier with no personal reference; the recipe selection itself is stored on the server and deleted automatically after %2$d days.',
				$cart_days,
				'mixmymeals'
			),
			Mixmymeals_Cart::COOKIE_NAME,
			$cart_days
		);

		$items['share'] = sprintf(
			/* translators: %d: number of days. */
			_n(
				'Sharing the planner: using the share button deliberately creates a copy of the recipe selection. It is stored on the server under a random identifier of its own, can be opened by anyone holding the generated link, and is deleted automatically after %d day. The copy is frozen: later changes to one\'s own planner do not appear in it, and the link gives no access to the planner itself. Only recipe IDs, servings, meal type and weekday are stored — no identifier of the person sharing. No copy exists unless someone shares.',
				'Sharing the planner: using the share button deliberately creates a copy of the recipe selection. It is stored on the server under a random identifier of its own, can be opened by anyone holding the generated link, and is deleted automatically after %d days. The copy is frozen: later changes to one\'s own planner do not appear in it, and the link gives no access to the planner itself. Only recipe IDs, servings, meal type and weekday are stored — no identifier of the person sharing. No copy exists unless someone shares.',
				$share_days,
				'mixmymeals'
			),
			$share_days
		);

		// The save counter only exists where visitors may save plans and an
		// hourly limit is set (mmm_create_meal_plan_ajax_callback()); the PDF
		// counter always does.
		$save_counter = 'on' === (string) get_option( 'mixmymeals_public_mode', '' )
			&& (int) get_option( 'mixmymeals_published_meal_plans_per_hour_limit', 0 ) > 0;

		$items['abuse'] = $save_counter
			? __( 'Abuse protection: when a visitor saves a meal plan or downloads a plan as a PDF, the IP address is stored in hashed (pseudonymised) form as a short-lived counter — about a minute for PDFs, an hour when saving — and then deleted automatically.', 'mixmymeals' )
			: __( 'PDF export: to limit abuse, the IP address is stored in hashed (pseudonymised) form as a short-lived counter when a plan PDF is downloaded, and deleted automatically after about a minute.', 'mixmymeals' );

		$items['ratings'] = $subs_on
			? __( 'Recipe ratings: logged-in users and e-mail subscribers (via a personalised link) can leave star ratings and reviews. These are stored as a comment together with the name and e-mail address given, until the author objects or requests deletion.', 'mixmymeals' )
			: __( 'Recipe ratings: logged-in users can leave star ratings and reviews. These are stored as a comment together with the name and e-mail address given, until the author objects or requests deletion.', 'mixmymeals' );

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

			if ( Mixmymeals_Subscriptions::SAVE_OFFER_REQUIRED === $save_mode ) {
				$sub .= ' ' . __( 'Saving a plan in the planner requires an e-mail address; the subscription only starts once it is confirmed via the link in the confirmation e-mail.', 'mixmymeals' );
			}

			$sub .= ' ' . __( 'Stored are the e-mail address, optionally a name, the subscribed meal plan, the times of sign-up and confirmation, and an access token that the links in the e-mails rely on. To limit abuse, the IP address is additionally stored in hashed form for at most one hour when signing up on a plan\'s page.', 'mixmymeals' );

			$sub .= ' ' . ( 'on' === get_option( Mixmymeals_Subscriptions::OPT_LOG_IP, '' )
				? __( 'As proof of consent, the full IP address used to sign up is also stored with the subscription record and deleted with it.', 'mixmymeals' )
				: __( 'No IP address is stored as proof of consent.', 'mixmymeals' ) );

			$pending   = Mixmymeals_Subscriptions::get_pending_hours();
			$retention = Mixmymeals_Subscriptions::get_retention_days();

			$sub .= ' ' . sprintf(
				/* translators: 1: number of hours, 2: number of days. */
				__( 'Deletion: unconfirmed sign-ups are deleted automatically after %1$s; confirmed, finished or cancelled records after %2$s.', 'mixmymeals' ),
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
