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
	 * Not instantiable — stateless service.
	 */
	private function __construct() {
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

		$entries = self::get_entries();

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
