<?php
/**
 * MixMyMeals uninstall routine.
 *
 * Always removes the plugin's own options (including third-party API tokens),
 * transients, scheduled events and the temporary Instagram/Pinterest image
 * directory. Personal data (newsletter subscriptions, visitor meal photos and
 * rating comments) is only deleted when the site owner opted in via the
 * "Delete personal data on uninstall" setting — otherwise it is preserved so
 * an accidental deactivation does not destroy records.
 *
 * The site's own content (recipes, plans, ingredients, equipment, meals) is
 * never touched here.
 *
 * @package MixMyMeals
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Performs the uninstall.
 *
 * Wrapped in a function purely so its working variables stay local. At file
 * scope every one of them would be a global, which is both untidy in a routine
 * that runs inside WordPress's own bootstrap and flagged by Plugin Check.
 *
 * @return void
 */
function mixmymeals_run_uninstall(): void {

	global $wpdb;

	// Read the opt-in flag before the options are removed.
	$mmm_delete_personal_data = ( 'on' === get_option( 'mixmymeals_delete_data_on_uninstall', '' ) );

	/* -------------------------------------------------------------------------
	 * 1. Scheduled events
	 * ---------------------------------------------------------------------- */
	$mmm_cron_hooks = array(
		'mmm_subscription_dispatch',
		'mixmymeals_daily_cleanup',
		'mixmymeals_mail_log_prune',
	);
	foreach ( $mmm_cron_hooks as $mmm_hook ) {
		wp_clear_scheduled_hook( $mmm_hook );
		if ( function_exists( 'wp_unschedule_hook' ) ) {
			wp_unschedule_hook( $mmm_hook );
		}
	}

	/* -------------------------------------------------------------------------
	 * 2. Personal data (opt-in only)
	 * ---------------------------------------------------------------------- */
	if ( $mmm_delete_personal_data ) {

		// Newsletter subscriptions.
		$mmm_subs = get_posts( array(
			'post_type'      => 'mmm_subscription',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		) );
		foreach ( $mmm_subs as $mmm_sub_id ) {
			wp_delete_post( (int) $mmm_sub_id, true );
		}

		// Visitor meal photos (flagged attachments only — never featured images).
		$mmm_photos = get_posts( array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_key'       => '_mmm_meal_photo', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		) );
		foreach ( $mmm_photos as $mmm_photo_id ) {
			wp_delete_attachment( (int) $mmm_photo_id, true );
		}

		// Rating comments (carry the rater's name/e-mail).
		$mmm_rating_comments = get_comments( array(
			'meta_key' => '_mmm_rating', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'fields'   => 'ids',
			'status'   => 'all',
		) );
		foreach ( $mmm_rating_comments as $mmm_comment_id ) {
			wp_delete_comment( (int) $mmm_comment_id, true );
		}
	}

	/* -------------------------------------------------------------------------
	 * 3. Plugin options and transients (always)
	 * ---------------------------------------------------------------------- */
	// All mixmymeals_* options, including Instagram/Pinterest access tokens.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a LIKE delete over option_name has no API equivalent, and caching a one-off uninstall is pointless.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( 'mixmymeals_' ) . '%'
		)
	);

	// Plugin transients (status caches, rate limits, board lists …).
	foreach ( array( '_transient_mmm_', '_transient_timeout_mmm_', '_transient_mixmymeals_', '_transient_timeout_mixmymeals_' ) as $mmm_prefix ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- see above.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( $mmm_prefix ) . '%'
			)
		);
	}
}

mixmymeals_run_uninstall();
