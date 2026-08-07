<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mixmymeals_Cleanup
 *
 * Deletes orphaned meals (parent meal plan no longer exists) and old
 * meal plans without a category via a daily WP-Cron event. Disabled by
 * default; controlled by the settings options mixmymeals_enable_cleanup
 * and mixmymeals_cleanup_min_age_days.
 */
class Mixmymeals_Cleanup {

	/**
	 * Cron hook name.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'mixmymeals_daily_cleanup';

	/**
	 * Fallback minimum age in days.
	 *
	 * @var int
	 */
	const DEFAULT_MIN_AGE_DAYS = 30;

	/**
	 * Option collecting deletions made by other routines between two reports.
	 *
	 * @var string
	 */
	const TALLY_OPTION = 'mixmymeals_cleanup_tally';

	/**
	 * Constructor. Wires cron scheduling and the deactivation hook.
	 *
	 * @param string $plugin_file Main plugin file, used for the deactivation hook.
	 */
	public function __construct( string $plugin_file = '' ) {

		add_action( 'init', array( $this, 'maybe_schedule' ) );
		add_action( self::CRON_HOOK, array( $this, 'run' ) );

		if ( $plugin_file ) {
			register_deactivation_hook(
				$plugin_file,
				array( __CLASS__, 'unschedule' )
			);
		}
	}

	/**
	 * Whether the cleanup is enabled in the settings.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		return 'on' === get_option( 'mixmymeals_enable_cleanup', '' );
	}

	/**
	 * Minimum age in days an item must have before it may be deleted.
	 *
	 * @return int
	 */
	public static function get_min_age_days(): int {
		$days = absint(
			get_option(
				'mixmymeals_cleanup_min_age_days',
				self::DEFAULT_MIN_AGE_DAYS
			)
		);

		return $days > 0 ? $days : self::DEFAULT_MIN_AGE_DAYS;
	}

	/**
	 * Whether the cleanup report e-mail is enabled in the settings.
	 *
	 * @return bool
	 */
	public static function is_report_enabled(): bool {
		return 'on' === get_option( 'mixmymeals_cleanup_report_enabled', '' );
	}

	/**
	 * Recipient address for the cleanup report. Falls back to the site admin
	 * e-mail when no valid address is configured.
	 *
	 * @return string
	 */
	public static function get_report_recipient(): string {

		$email = sanitize_email( (string) get_option( 'mixmymeals_cleanup_report_recipient', '' ) );

		if ( '' !== $email && is_email( $email ) ) {
			return $email;
		}

		return (string) get_option( 'admin_email' );
	}

	/**
	 * Maximum number of deletions per post type and cron run.
	 *
	 * @return int
	 */
	public static function get_batch_limit(): int {
		return max( 1, (int) apply_filters( 'mixmymeals_cleanup_batch_limit', 50 ) );
	}

	/**
	 * Schedules or unschedules the daily event depending on the setting.
	 *
	 * @return void
	 */
	public function maybe_schedule(): void {

		$scheduled = wp_next_scheduled( self::CRON_HOOK );

		if ( self::is_enabled() && ! $scheduled ) {
			wp_schedule_event( time(), 'daily', self::CRON_HOOK );
		} elseif ( ! self::is_enabled() && $scheduled ) {
			wp_unschedule_event( $scheduled, self::CRON_HOOK );
		}
	}

	/**
	 * Removes the cron event. Used on plugin deactivation.
	 *
	 * @return void
	 */
	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Cron callback: runs both cleanup passes.
	 *
	 * @return void
	 */
	public function run(): void {

		if ( ! self::is_enabled() ) {
			return;
		}

		// Erst Pläne (löscht deren Meals mit), dann übrig gebliebene Waisen.
		$plans   = $this->delete_uncategorized_meal_plans();
		$orphans = $this->delete_orphaned_meals();

		$stats = array(
			'plans'        => $plans['plans'],
			'plan_meals'   => $plans['meals'],
			'orphan_meals' => $orphans['meals'],
			'failed'       => $plans['failed'] + $plans['meals_failed'] + $orphans['failed'],
			'capped'       => $plans['capped'] || $orphans['capped'],
		);

		if ( $stats['plans'] || $stats['plan_meals'] || $stats['orphan_meals'] || $stats['failed'] ) {
			mmm_write_to_log(
				sprintf(
					'MixMyMeals cleanup: deleted %1$d meal plan(s), %2$d meal(s) belonging to them and %3$d orphaned meal(s); %4$d deletion(s) failed.',
					$stats['plans'],
					$stats['plan_meals'],
					$stats['orphan_meals'],
					$stats['failed']
				)
			);
		}

		$this->maybe_send_report( $stats );
	}

	/**
	 * Records deletions made by another routine so the daily report can list
	 * them alongside its own.
	 *
	 * A no-op unless cleanup *and* report are enabled: only then does a run
	 * exist that empties the tally again, so it cannot grow unbounded on sites
	 * that never send a report. Cron scheduling stays independent — this only
	 * passes numbers along.
	 *
	 * @param string $key   Identifier, see tally_line().
	 * @param int    $count Number of deleted items.
	 *
	 * @return void
	 */
	public static function record( string $key, int $count ): void {

		if ( '' === $key || $count < 1 ) {
			return;
		}

		if ( ! self::is_enabled() || ! self::is_report_enabled() ) {
			return;
		}

		$tally         = self::get_tally();
		$tally[ $key ] = ( $tally[ $key ] ?? 0 ) + $count;

		update_option( self::TALLY_OPTION, $tally, false );
	}

	/**
	 * Reads the tally of deletions reported by other routines.
	 *
	 * @return array<string, int>
	 */
	private static function get_tally(): array {

		$tally = get_option( self::TALLY_OPTION, array() );

		return is_array( $tally ) ? array_map( 'absint', $tally ) : array();
	}

	/**
	 * Sends the cleanup summary as an e-mail when the report is enabled.
	 *
	 * By default the report is skipped when nothing was deleted (filterable via
	 * 'mixmymeals_cleanup_report_when_empty'). It is sent through
	 * Mixmymeals_Mailer as a forced, transactional admin/ops message (bypasses
	 * the suppression list) wrapped in the branded layout.
	 *
	 * @param array $stats Run statistics as assembled by run().
	 *
	 * @return void
	 */
	private function maybe_send_report( array $stats ): void {

		if ( ! self::is_report_enabled() ) {
			return;
		}

		$tally = self::get_tally();

		$total = $stats['plans'] + $stats['plan_meals'] + $stats['orphan_meals']
			+ $stats['failed'] + array_sum( $tally );

		/**
		 * Filters whether the report is also sent when nothing was deleted.
		 *
		 * @param bool $send_when_empty Default false (skip empty reports).
		 */
		if ( $total < 1 && ! apply_filters( 'mixmymeals_cleanup_report_when_empty', false ) ) {
			return;
		}

		if ( ! class_exists( 'Mixmymeals_Mailer' ) ) {
			return;
		}

		$recipient = self::get_report_recipient();

		if ( '' === $recipient ) {
			return;
		}

		$site_name = wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES );
		$when      = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
		$min_age   = self::get_min_age_days();

		$body  = '<h1>' . esc_html__( 'Cleanup report', 'mixmymeals' ) . '</h1>';
		$body .= '<p>' . esc_html(
			sprintf(
				/* translators: %s: date and time of the cleanup run. */
				__( 'Cleanup run on %s.', 'mixmymeals' ),
				$when
			)
		) . '</p><ul>';
		$body .= self::report_line(
			/* translators: %d: number of deleted meal plans. */
			_n( '%d meal plan deleted', '%d meal plans deleted', $stats['plans'], 'mixmymeals' ),
			$stats['plans']
		);
		$body .= self::report_line(
			/* translators: %d: number of meals deleted together with their plan. */
			_n(
				'%d meal belonging to those plans deleted',
				'%d meals belonging to those plans deleted',
				$stats['plan_meals'],
				'mixmymeals'
			),
			$stats['plan_meals']
		);
		$body .= self::report_line(
			/* translators: %d: number of deleted orphaned meals. */
			_n( '%d orphaned meal deleted', '%d orphaned meals deleted', $stats['orphan_meals'], 'mixmymeals' ),
			$stats['orphan_meals']
		);
		$body .= '</ul>';

		if ( $stats['failed'] > 0 ) {
			$body .= '<p><strong>' . esc_html(
				sprintf(
					/* translators: %d: number of failed deletions. */
					_n(
						'%d deletion failed and will be retried on the next run.',
						'%d deletions failed and will be retried on the next run.',
						$stats['failed'],
						'mixmymeals'
					),
					$stats['failed']
				)
			) . '</strong></p>';
		}

		if ( $stats['capped'] ) {
			$body .= '<p>' . esc_html(
				sprintf(
					/* translators: %d: maximum number of deletions per post type and run. */
					__(
						'The limit of %d deletions per post type and run was reached, so items are left over for the next run.',
						'mixmymeals'
					),
					self::get_batch_limit()
				)
			) . '</p>';
		}

		if ( ! empty( $tally ) ) {
			$body .= '<h2>' . esc_html__( 'Other cleanup since the last report', 'mixmymeals' ) . '</h2><ul>';

			foreach ( $tally as $key => $count ) {
				$body .= self::tally_line( (string) $key, (int) $count );
			}

			$body .= '</ul>';
		}

		$body .= '<p>' . esc_html(
			sprintf(
				/* translators: %d: minimum age in days. */
				_n(
					'Meal plans without a category and orphaned meals are removed once they are older than %d day.',
					'Meal plans without a category and orphaned meals are removed once they are older than %d days.',
					$min_age,
					'mixmymeals'
				),
				$min_age
			)
		) . '</p>';

		$subject = sprintf(
			/* translators: %s: site name. */
			__( '[%s] Cleanup report', 'mixmymeals' ),
			$site_name
		);

		// Erst leeren, wenn der Bericht wirklich rausgeht.
		delete_option( self::TALLY_OPTION );

		Mixmymeals_Mailer::send_template(
			$recipient,
			$subject,
			$body,
			array(),
			array(
				'inline'        => true,
				'layout'        => 'layout.html',
				'transactional' => true,
				'force'         => true,
			)
		);
	}

	/**
	 * Renders one list item of the report.
	 *
	 * @param string $text  Already pluralised text with a single %d placeholder.
	 * @param int    $count Number to fill in.
	 *
	 * @return string
	 */
	private static function report_line( string $text, int $count ): string {
		return '<li>' . esc_html( sprintf( $text, $count ) ) . '</li>';
	}

	/**
	 * Renders one list item for a deletion reported via record().
	 *
	 * @param string $key   Identifier passed to record().
	 * @param int    $count Number of deleted items.
	 *
	 * @return string
	 */
	private static function tally_line( string $key, int $count ): string {

		switch ( $key ) {
			case 'instagram_folders':
				/* translators: %d: number of deleted image folders. */
				$text = _n(
					'%d Instagram image folder deleted',
					'%d Instagram image folders deleted',
					$count,
					'mixmymeals'
				);
				break;

			case 'mail_log_entries':
				/* translators: %d: number of deleted mail log entries. */
				$text = _n(
					'%d mail log entry deleted',
					'%d mail log entries deleted',
					$count,
					'mixmymeals'
				);
				break;

			case 'subscriptions_unconfirmed':
				/* translators: %d: number of deleted unconfirmed subscriptions. */
				$text = _n(
					'%d unconfirmed subscription deleted',
					'%d unconfirmed subscriptions deleted',
					$count,
					'mixmymeals'
				);
				break;

			case 'subscriptions_retention':
				/* translators: %d: number of subscriptions deleted after the retention period. */
				$text = _n(
					'%d finished subscription deleted after the retention period',
					'%d finished subscriptions deleted after the retention period',
					$count,
					'mixmymeals'
				);
				break;

			default:
				return '<li>' . esc_html( sprintf( '%1$s: %2$d', $key, $count ) ) . '</li>';
		}

		return self::report_line( $text, $count );
	}

	/**
	 * Deletes meals whose parent meal plan no longer exists.
	 *
	 * A meal counts as orphaned if its post_parent is 0, points to a
	 * deleted post, or points to a post that is no longer a meal plan.
	 * Plans in the trash still exist, so their meals are not orphaned.
	 *
	 * @return array{meals:int,failed:int,capped:bool} Deleted, failed, and
	 *               whether the batch limit cut the run short.
	 */
	public function delete_orphaned_meals(): array {

		global $wpdb;

		$threshold = gmdate(
			'Y-m-d H:i:s',
			time() - self::get_min_age_days() * DAY_IN_SECONDS
		);

		$limit = self::get_batch_limit();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$orphan_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT meal.ID
				FROM {$wpdb->posts} AS meal
				LEFT JOIN {$wpdb->posts} AS plan
					ON meal.post_parent = plan.ID
					AND plan.post_type = 'mmm_meal_plan'
				WHERE meal.post_type = 'mmm_meal'
					AND plan.ID IS NULL
					AND meal.post_date_gmt < %s
				LIMIT %d",
				$threshold,
				$limit
			)
		);

		$deleted = 0;
		$failed  = 0;

		foreach ( $orphan_ids as $meal_id ) {
			$meal_id = (int) $meal_id;

			// Defensive Doppelprüfung vor dem endgültigen Löschen.
			if ( get_post_type( $meal_id ) !== 'mmm_meal' ) {
				continue;
			}

			if ( wp_delete_post( $meal_id, true ) ) {
				++$deleted;
			} else {
				++$failed;
			}
		}

		return array(
			'meals'  => $deleted,
			'failed' => $failed,
			'capped' => count( $orphan_ids ) >= $limit,
		);
	}

	/**
	 * Deletes old meal plans that have no meal plan category, including
	 * their child meals.
	 *
	 * @return array{plans:int,meals:int,failed:int,meals_failed:int,capped:bool}
	 *               Deleted plans, meals removed with them, failures per kind,
	 *               and whether the batch limit cut the run short.
	 */
	public function delete_uncategorized_meal_plans(): array {

		$limit = self::get_batch_limit();

		$plan_ids = get_posts(
			array(
				'post_type'        => 'mmm_meal_plan',
				'post_status'      => 'any',
				'posts_per_page'   => $limit,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'date_query'       => array(
					array(
						'column' => 'post_date_gmt',
						'before' => self::get_min_age_days() . ' days ago',
					),
				),
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				'tax_query'        => array(
					array(
						'taxonomy' => 'mmm_meal_plan_category',
						'operator' => 'NOT EXISTS',
					),
				),
			)
		);

		$deleted      = 0;
		$failed       = 0;
		$meals        = 0;
		$meals_failed = 0;

		foreach ( $plan_ids as $plan_id ) {
			$plan_id = (int) $plan_id;

			if ( get_post_type( $plan_id ) !== 'mmm_meal_plan' ) {
				continue;
			}

			// Zugehörige Meals zuerst löschen, damit keine Waisen entstehen.
			$meal_ids = get_posts(
				array(
					'post_type'      => 'mmm_meal',
					'post_status'    => 'any',
					'post_parent'    => $plan_id,
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);

			foreach ( $meal_ids as $meal_id ) {
				if ( wp_delete_post( (int) $meal_id, true ) ) {
					++$meals;
				} else {
					++$meals_failed;
				}
			}

			if ( wp_delete_post( $plan_id, true ) ) {
				++$deleted;
			} else {
				++$failed;
			}
		}

		return array(
			'plans'        => $deleted,
			'meals'        => $meals,
			'failed'       => $failed,
			'meals_failed' => $meals_failed,
			'capped'       => count( $plan_ids ) >= $limit,
		);
	}
}
