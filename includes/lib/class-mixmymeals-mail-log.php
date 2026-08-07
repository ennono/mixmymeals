<?php
/**
 * Mail log class file.
 *
 * @package MixMyMeals Plugin/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mixmymeals_Mail_Log
 *
 * A lightweight send log for Mixmymeals_Mailer. Stores a bounded ring buffer
 * (the most recent N entries) in a single option — no custom table.
 *
 * GDPR notes:
 *  - Only metadata is stored (recipient, subject, status, timestamp); never the
 *    message body.
 *  - Retention is bounded by entry count (filterable) and the whole log can be
 *    cleared from the admin.
 *  - Logging is opt-out: it can be disabled in the settings.
 */
class Mixmymeals_Mail_Log {

	/**
	 * Option storing the log entries (newest first).
	 *
	 * @var string
	 */
	const OPTION = 'mixmymeals_mail_log';

	/**
	 * Option toggling whether sends are logged.
	 *
	 * @var string
	 */
	const OPTION_ENABLED = 'mixmymeals_mail_log_enabled';

	/**
	 * Default number of entries kept.
	 *
	 * @var int
	 */
	const DEFAULT_MAX = 200;

	/**
	 * Default number of days an entry is kept.
	 *
	 * @var int
	 */
	const DEFAULT_RETENTION_DAYS = 90;

	/**
	 * Cron hook that prunes aged-out entries.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'mixmymeals_mail_log_prune';

	/**
	 * Not instantiable — stateless service.
	 */
	private function __construct() {
	}

	/**
	 * Wires the daily pruning cron. The entry-count cap alone would keep
	 * addresses indefinitely on a low-traffic site (the log simply never fills
	 * up), so the age limit has to hold even while no mail is sent. Deliberately
	 * independent of Mixmymeals_Cleanup, whose cron only runs when that
	 * (unrelated, off-by-default) setting is enabled.
	 *
	 * @param string $plugin_file Main plugin file, used for the deactivation hook.
	 * @return void
	 */
	public static function register( string $plugin_file = '' ): void {

		add_action( 'init', array( __CLASS__, 'maybe_schedule' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'prune' ) );

		if ( $plugin_file ) {
			register_deactivation_hook( $plugin_file, array( __CLASS__, 'unschedule' ) );
		}
	}

	/**
	 * Schedules the daily pruning event once.
	 *
	 * @return void
	 */
	public static function maybe_schedule(): void {

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Removes the scheduled pruning event.
	 *
	 * @return void
	 */
	public static function unschedule(): void {

		$timestamp = wp_next_scheduled( self::CRON_HOOK );

		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * Whether send logging is enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		return 'on' === get_option( self::OPTION_ENABLED, 'on' );
	}

	/**
	 * Maximum number of entries to retain.
	 *
	 * @return int
	 */
	public static function get_max_entries(): int {
		$max = (int) apply_filters( 'mixmymeals_mail_log_max_entries', self::DEFAULT_MAX );

		return $max > 0 ? $max : self::DEFAULT_MAX;
	}

	/**
	 * Number of days an entry is kept. 0 disables the age limit and leaves the
	 * entry-count cap as the only bound.
	 *
	 * @return int
	 */
	public static function get_retention_days(): int {
		$days = (int) apply_filters( 'mixmymeals_mail_log_retention_days', self::DEFAULT_RETENTION_DAYS );

		return $days > 0 ? $days : 0;
	}

	/**
	 * Drops entries older than the retention window. Runs daily via cron and on
	 * every write, so the age limit holds whether or not mail is flowing.
	 *
	 * @return int Number of entries removed.
	 */
	public static function prune(): int {

		$entries = self::get_entries();

		if ( empty( $entries ) ) {
			return 0;
		}

		$kept = self::drop_aged_out( $entries );

		if ( count( $kept ) === count( $entries ) ) {
			return 0;
		}

		update_option( self::OPTION, $kept, false );

		$removed = count( $entries ) - count( $kept );

		if ( class_exists( 'Mixmymeals_Cleanup' ) ) {
			Mixmymeals_Cleanup::record( 'mail_log_entries', $removed );
		}

		return $removed;
	}

	/**
	 * Filters out entries older than the retention window.
	 *
	 * @param array $entries Log entries.
	 * @return array
	 */
	private static function drop_aged_out( array $entries ): array {

		$days = self::get_retention_days();

		if ( $days <= 0 ) {
			return $entries;
		}

		$cutoff = time() - $days * DAY_IN_SECONDS;

		return array_values(
			array_filter(
				$entries,
				static function ( $entry ) use ( $cutoff ) {
					return isset( $entry['time'] ) && (int) $entry['time'] >= $cutoff;
				}
			)
		);
	}

	/**
	 * Records a send attempt (prepends it and trims to the retention limit).
	 *
	 * @param string $recipient     Recipient address.
	 * @param string $subject       Subject line.
	 * @param bool   $success       Whether wp_mail() reported success.
	 * @param bool   $transactional Whether the mail was transactional.
	 *
	 * @return void
	 */
	public static function record( string $recipient, string $subject, bool $success, bool $transactional = true ): void {

		if ( ! self::is_enabled() ) {
			return;
		}

		$recipient = strtolower( sanitize_email( $recipient ) );

		if ( '' === $recipient ) {
			return;
		}

		// The array is loaded and rewritten anyway, so applying the age limit
		// here is free and keeps the log clean between cron runs.
		$entries = self::drop_aged_out( self::get_entries() );

		array_unshift(
			$entries,
			array(
				'time'          => time(),
				'to'            => $recipient,
				'subject'       => sanitize_text_field( $subject ),
				'status'        => $success ? 'sent' : 'failed',
				'transactional' => $transactional,
			)
		);

		$max = self::get_max_entries();

		if ( count( $entries ) > $max ) {
			$entries = array_slice( $entries, 0, $max );
		}

		update_option( self::OPTION, $entries, false );
	}

	/**
	 * Returns the stored log entries (newest first).
	 *
	 * @return array[]
	 */
	public static function get_entries(): array {

		$entries = get_option( self::OPTION, array() );

		return is_array( $entries ) ? $entries : array();
	}

	/**
	 * Empties the log.
	 *
	 * @return void
	 */
	public static function clear(): void {
		update_option( self::OPTION, array(), false );
	}
}
