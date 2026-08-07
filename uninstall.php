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

global $wpdb;

// Read the opt-in flag before the options are removed.
$mmm_delete_personal_data = ( 'on' === get_option( 'mixmymeals_delete_data_on_uninstall', '' ) );

/* -------------------------------------------------------------------------
 * 1. Scheduled events
 * ---------------------------------------------------------------------- */
$mmm_cron_hooks = array(
	'mixmymeals_instagram_cleanup',
	'mixmymeals_instagram_scheduled_publish',
	'mixmymeals_pinterest_scheduled_publish',
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
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( 'mixmymeals_' ) . '%'
	)
);

// Per-user social credentials (Instagram/Pinterest tokens are secrets).
foreach ( array( 'mmm_instagram_', 'mmm_pinterest_' ) as $mmm_um_prefix ) {
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
			$wpdb->esc_like( $mmm_um_prefix ) . '%'
		)
	);
}

// Plugin transients (status caches, rate limits, board lists …).
foreach ( array( '_transient_mmm_', '_transient_timeout_mmm_', '_transient_mixmymeals_', '_transient_timeout_mixmymeals_' ) as $mmm_prefix ) {
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( $mmm_prefix ) . '%'
		)
	);
}

/* -------------------------------------------------------------------------
 * 4. Temporary generated images
 * ---------------------------------------------------------------------- */
$mmm_uploads = wp_get_upload_dir();
if ( empty( $mmm_uploads['error'] ) && ! empty( $mmm_uploads['basedir'] ) ) {
	$mmm_ig_dir = trailingslashit( $mmm_uploads['basedir'] ) . 'mixmymeals-instagram';

	// Only remove a directory that sits inside the uploads base directory.
	if ( is_dir( $mmm_ig_dir ) && 0 === strpos( $mmm_ig_dir, trailingslashit( $mmm_uploads['basedir'] ) ) ) {
		$mmm_it = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $mmm_ig_dir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $mmm_it as $mmm_file ) {
			if ( $mmm_file->isDir() ) {
				@rmdir( $mmm_file->getPathname() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
			} else {
				@unlink( $mmm_file->getPathname() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
			}
		}
		@rmdir( $mmm_ig_dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}
}
