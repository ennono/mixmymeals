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
	 * @param string $reason        Why it failed before reaching wp_mail(), one
	 *                              of reasons() ('' = wp_mail() itself answered).
	 * @param string $detail        What the reason is about (e.g. the template name).
	 *
	 * @return void
	 */
	public static function record( string $recipient, string $subject, bool $success, bool $transactional = true, string $reason = '', string $detail = '' ): void {

		if ( ! self::is_enabled() ) {
			return;
		}

		$recipient = strtolower( sanitize_email( $recipient ) );

		if ( '' === $recipient ) {
			return;
		}

		$subject = sanitize_text_field( $subject );
		$reason  = array_key_exists( $reason, self::reasons() ) ? $reason : '';
		$detail  = sanitize_text_field( $detail );

		// The array is loaded and rewritten anyway, so applying the age limit
		// here is free and keeps the log clean between cron runs.
		$entries = self::drop_aged_out( self::get_entries() );

		// A failure with a reason is retried by the drip on every cron run
		// (every 15 minutes): one row that counts the attempts, not a log
		// that pushes everything else out within a day.
		if ( '' !== $reason ) {
			foreach ( $entries as $i => $entry ) {
				if ( ( $entry['reason'] ?? '' ) === $reason
					&& ( $entry['detail'] ?? '' ) === $detail
					&& ( $entry['to'] ?? '' ) === $recipient
					&& ( $entry['subject'] ?? '' ) === $subject ) {
					$entry['time']     = time();
					$entry['attempts'] = max( 1, (int) ( $entry['attempts'] ?? 1 ) ) + 1;
					unset( $entries[ $i ] );
					array_unshift( $entries, $entry );
					update_option( self::OPTION, array_values( $entries ), false );
					return;
				}
			}
		}

		$entry = array(
			'time'          => time(),
			'to'            => $recipient,
			'subject'       => $subject,
			'status'        => $success ? 'sent' : 'failed',
			'transactional' => $transactional,
		);

		if ( '' !== $reason ) {
			$entry['reason']   = $reason;
			$entry['detail']   = $detail;
			$entry['attempts'] = 1;
		}

		array_unshift( $entries, $entry );

		$max = self::get_max_entries();

		if ( count( $entries ) > $max ) {
			$entries = array_slice( $entries, 0, $max );
		}

		update_option( self::OPTION, $entries, false );
	}

	/**
	 * Reasons a mail can fail before it reaches wp_mail(), worded for the log.
	 * Each wording takes the entry's detail as %s.
	 *
	 * @return array<string,string> Reason key => translated wording.
	 */
	public static function reasons(): array {
		return array(
			/* translators: %s: template file name, e.g. "each-day.html". */
			'template_missing' => __( 'Template not found: %s', 'mixmymeals' ),
		);
	}

	/**
	 * Explains a failed entry: its reason with the detail filled in, plus the
	 * number of attempts when it was retried. '' for entries without a reason.
	 *
	 * @param array $entry Log entry.
	 * @return string Plain text; escape on output.
	 */
	public static function reason_text( array $entry ): string {

		$reasons = self::reasons();
		$reason  = (string) ( $entry['reason'] ?? '' );

		if ( ! isset( $reasons[ $reason ] ) ) {
			return '';
		}

		$text     = sprintf( $reasons[ $reason ], (string) ( $entry['detail'] ?? '' ) );
		$attempts = (int) ( $entry['attempts'] ?? 1 );

		if ( $attempts > 1 ) {
			$text .= ' · ' . sprintf(
				/* translators: %s: number of attempts. */
				_n( '%s attempt', '%s attempts', $attempts, 'mixmymeals' ),
				number_format_i18n( $attempts )
			);
		}

		return $text;
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
