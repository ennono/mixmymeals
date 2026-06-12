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
		$deleted_plans = $this->delete_uncategorized_meal_plans();
		$deleted_meals = $this->delete_orphaned_meals();

		if ( $deleted_plans || $deleted_meals ) {
			write_to_log(
				sprintf(
					'MixMyMeals cleanup: deleted %d meal plan(s) and %d orphaned meal(s).',
					$deleted_plans,
					$deleted_meals
				)
			);
		}
	}

	/**
	 * Deletes meals whose parent meal plan no longer exists.
	 *
	 * A meal counts as orphaned if its post_parent is 0, points to a
	 * deleted post, or points to a post that is no longer a meal plan.
	 * Plans in the trash still exist, so their meals are not orphaned.
	 *
	 * @return int Number of deleted meals.
	 */
	public function delete_orphaned_meals(): int {

		global $wpdb;

		$threshold = gmdate(
			'Y-m-d H:i:s',
			time() - self::get_min_age_days() * DAY_IN_SECONDS
		);

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
				self::get_batch_limit()
			)
		);

		$deleted = 0;

		foreach ( $orphan_ids as $meal_id ) {
			$meal_id = (int) $meal_id;

			// Defensive Doppelprüfung vor dem endgültigen Löschen.
			if ( get_post_type( $meal_id ) !== 'mmm_meal' ) {
				continue;
			}

			if ( wp_delete_post( $meal_id, true ) ) {
				++$deleted;
			}
		}

		return $deleted;
	}

	/**
	 * Deletes old meal plans that have no meal plan category, including
	 * their child meals.
	 *
	 * @return int Number of deleted meal plans.
	 */
	public function delete_uncategorized_meal_plans(): int {

		$plan_ids = get_posts(
			array(
				'post_type'        => 'mmm_meal_plan',
				'post_status'      => 'any',
				'posts_per_page'   => self::get_batch_limit(),
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

		$deleted = 0;

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
				wp_delete_post( (int) $meal_id, true );
			}

			if ( wp_delete_post( $plan_id, true ) ) {
				++$deleted;
			}
		}

		return $deleted;
	}
}
