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
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'meta_key'       => '_mmm_sub_email',
			'meta_value'     => strtolower( $email ),
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
			'meta_key'       => '_mmm_meal_photo', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
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
	 * Adds a suggested privacy-policy section for the admin to review under
	 * Settings → Privacy.
	 *
	 * @return void
	 */
	public function add_privacy_policy_content(): void {

		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content =
			'<p>' . esc_html__( 'This site uses the MixMyMeals plugin, which processes the following personal data:', 'mixmymeals' ) . '</p>'
			. '<ul>'
			. '<li>' . esc_html__( 'Newsletter subscriptions: when you subscribe to a meal plan, we store your e-mail address, an optional name, the time of your consent and — if enabled — the IP address as proof of consent (double opt-in). You can unsubscribe at any time via the link in every e-mail.', 'mixmymeals' ) . '</li>'
			. '<li>' . esc_html__( 'Ratings and reviews are stored as comments together with the name and e-mail address you provide.', 'mixmymeals' ) . '</li>'
			. '<li>' . esc_html__( 'Meal photos: photos you upload are stored together with the name shown next to them. Photos are reviewed before they appear publicly.', 'mixmymeals' ) . '</li>'
			. '<li>' . esc_html__( 'A functional cookie stores a random identifier so your meal-plan cart works across page loads. It contains no personal data.', 'mixmymeals' ) . '</li>'
			. '<li>' . esc_html__( 'To prevent abuse, a shortened (hashed) form of your IP address is stored temporarily when subscribing, creating a plan or generating a PDF.', 'mixmymeals' ) . '</li>'
			. '</ul>'
			. '<p>' . esc_html__( 'When the site owner publishes a meal plan or recipe to Instagram or Pinterest, the generated image and the page link are transmitted to Meta/Pinterest. This only concerns the site’s own content, not visitor data.', 'mixmymeals' ) . '</p>'
			. '<p>' . esc_html__( 'You can request an export or deletion of your data via the WordPress tools under Tools → Export/Erase Personal Data.', 'mixmymeals' ) . '</p>';

		wp_add_privacy_policy_content( 'MixMyMeals', wp_kses_post( $content ) );
	}
}
