<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mixmymeals_Meal_Plan
 *
 * Handles meal plan data including start and end days,
 * meal counting, servings range, and helper methods.
 */
class Mixmymeals_Meal_Plan {

	/**
	 * Post ID of the meal plan.
	 *
	 * @var int
	 */
	private int $post_id;

	/**
	 * Cache for "random recipe from latest plan" so multiple static calls return the same recipe.
	 *
	 * @var int|null
	 */
	private static $cached_random_recipe_id = null;

	/**
	 * Constructor.
	 *
	 * Initializes the meal plan object with a given post ID.
	 *
	 * @param int $post_id The post ID of the meal plan.
	 */
	public function __construct( int $post_id ) {
		$this->post_id = $post_id;
	}

	public function get_id(): int {
		return $this->post_id;
	}

	/**
	 * @throws \Exception On post creation failure.
	 */
	public static function create( string $title, string $status = 'draft', string $content = '' ): self {
		$post_id = wp_insert_post( array(
			'post_type'    => 'mmm_meal_plan',
			'post_title'   => sanitize_text_field( $title ),
			'post_status'  => $status,
			'post_content' => wp_kses_post( $content ),
		), true );

		if ( is_wp_error( $post_id ) ) {
			throw new \Exception( esc_html( $post_id->get_error_message() ) );
		}

		return new self( $post_id );
	}

	// ---------------------------
	// New Getter / Setter for Start & End Day (1–7)
	// ---------------------------

	/**
	 * Retrieves all meal plans.
	 *
	 * @return WP_Post[] Array of meal plan posts.
	 */
	/** @return WP_Post[] */
	public static function get_all_meal_plans(): array {
		return get_posts(
			array(
				'post_type'      => 'mmm_meal_plan',
				'posts_per_page' => -1,
				'nopaging'       => true,
				'post_status'    => 'publish',
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
	}

	/**
	 * Returns the meal plan that is active today (based on weekday).
	 *
	 * 1 = Monday, ... 7 = Sunday
	 *
	 * @return int The ID of the current plan or 0 if none is active.
	 */
	public static function get_current_plan_id(): int {

		$today_num = intval( gmdate( 'N' ) ); // 1=Mon … 7=Sun

		$plans = get_posts(
			array(
				'post_type'      => 'mmm_meal_plan',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $plans as $plan_id ) {

			$plan = new self( $plan_id );

			$start = $plan->get_start_day();
			$end   = $plan->get_end_day();

			// Normal case: e.g. 2–5
			if ( $start <= $end ) {
				if ( $today_num >= $start && $today_num <= $end ) {
					return $plan_id;
				}
			}

			// Across midnight: e.g. 6–2 (Sat–Tue)
			if ( $start > $end ) {
				if ( $today_num >= $start || $today_num <= $end ) {
					return $plan_id;
				}
			}
		}

		return 0;
	}

	/**
	 * Returns the start day of the plan.
	 *
	 * 1 = Monday … 7 = Sunday
	 *
	 * @return int The start day.
	 */
	public function get_start_day(): int {
		$day = get_post_meta( $this->post_id, 'start_day', true );

		return $day ? absint( $day ) : 1; // Default: Monday
	}

	/**
	 * Returns the end day of the plan.
	 *
	 * 1 = Monday … 7 = Sunday
	 *
	 * @return int The end day.
	 */
	public function get_end_day(): int {
		$day = get_post_meta( $this->post_id, 'end_day', true );

		return $day ? absint( $day ) : 7; // Default: Sunday
	}

	// ---------------------------
	// Static Helpers
	// ---------------------------

	/**
	 * Sets the start day of the plan.
	 *
	 * @param int $day The start day (1–7).
	 *
	 * @return void
	 */
	public function set_start_day( int $day ): void {
		update_post_meta(
			$this->post_id,
			'start_day',
			max( 1, min( 7, intval( $day ) ) )
		);
	}
	// ---------------------------
	// External author (author box)
	// ---------------------------

	/**
	 * Author box accessors. Thin delegates to Mixmymeals_Post_Meta, which
	 * holds the storage and admin fields shared with recipes; the card itself
	 * is rendered by the author-box shortcodes.
	 *
	 * @return string
	 */
	public function get_author_name(): string {
		return Mixmymeals_Post_Meta::get_author_name( $this->post_id );
	}

	/**
	 * Free-form description shown in the author box (limited HTML allowed).
	 *
	 * @return string
	 */
	public function get_author_text(): string {
		return Mixmymeals_Post_Meta::get_author_text( $this->post_id );
	}

	/**
	 * Link target of the author box, or '' when none is set.
	 *
	 * @return string
	 */
	public function get_author_url(): string {
		return Mixmymeals_Post_Meta::get_author_url( $this->post_id );
	}

	/**
	 * Label for the author link, defaulting to the link's host.
	 *
	 * @return string
	 */
	public function get_author_link_text(): string {
		return Mixmymeals_Post_Meta::get_author_link_text( $this->post_id );
	}

	/**
	 * Whether the author link should carry rel="nofollow".
	 *
	 * @return bool
	 */
	public function get_author_nofollow(): bool {
		return Mixmymeals_Post_Meta::get_author_nofollow( $this->post_id );
	}

	/**
	 * Social/contact addresses of the external author.
	 *
	 * @return string[]
	 */
	public function get_author_social(): array {
		return Mixmymeals_Post_Meta::get_author_social( $this->post_id );
	}

	/**
	 * Attachment ID of the external author's profile picture, 0 when none.
	 *
	 * @return int
	 */
	public function get_author_image_id(): int {
		return Mixmymeals_Post_Meta::get_author_image_id( $this->post_id );
	}

	/**
	 * URL of the external author's profile picture, '' when none.
	 *
	 * @return string
	 */
	public function get_author_image_url(): string {
		return Mixmymeals_Post_Meta::get_author_image_url( $this->post_id );
	}

	/**
	 * All stored author-box fields, raw as saved.
	 *
	 * @return array
	 */
	public function get_author_box(): array {
		return Mixmymeals_Post_Meta::get_author_data( $this->post_id );
	}

	/**
	 * Stores the author box fields.
	 *
	 * @param array $data name, text, url, link_text, nofollow, social.
	 * @return void
	 */
	public function set_author_box( array $data ): void {
		Mixmymeals_Post_Meta::save_author_data( $this->post_id, $data );
	}

	// ---------------------------
	// Meal Handling
	// ---------------------------

	/**
	 * Sets the end day of the plan.
	 *
	 * @param int $day The end day (1–7).
	 *
	 * @return void
	 */
	public function set_end_day( int $day ): void {
		update_post_meta(
			$this->post_id,
			'end_day',
			max( 1, min( 7, intval( $day ) ) )
		);
	}

	/**
	 * Counts the number of meals in the plan.
	 *
	 * @return int The number of meals.
	 */
	public function count_meals(): int {
		$meal_count = get_posts(
			array(
				'post_type'      => 'mmm_meal',
				'post_parent'    => $this->post_id,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => -1,
			)
		);

		return is_array( $meal_count ) ? count( $meal_count ) : 0;
	}

	/**
	 * Sets the meal plan status to draft.
	 *
	 * @return int|WP_Error Post ID on success, WP_Error on failure.
	 */
	/** @return int|WP_Error Post ID on success. */
	public function set_to_draft(): int|WP_Error {
		return wp_update_post(
			array(
				'ID'          => $this->post_id,
				'post_status' => 'draft',
			)
		);
	}

	/**
	 * Returns the number of days in the meal plan
	 * based on start day (1–7) and end day (1–7).
	 *
	 * Examples:
	 * start=1, end=5 → 5 days
	 * start=5, end=2 → 5 days (Fri–Tue)
	 *
	 * @return int Number of days in the plan.
	 */
	public function count_days(): int {

		$start = $this->get_start_day();
		$end   = $this->get_end_day();

		// Normal case: e.g. 1–5
		if ( $start <= $end ) {
			return ( $end - $start ) + 1;
		}

		// Overflow case: e.g. 5–2 (Fri → Sat → Sun → Mon → Tue)
		return ( 7 - $start + 1 ) + $end;
	}

	/**
	 * Returns the range of days in the plan.
	 *
	 * @return int[] Array of day numbers (1–7).
	 */
	public function get_day_range(): array {
		$start = absint( $this->get_start_day() );
		$end   = absint( $this->get_end_day() );

		$days = array();

		if ( $start <= $end ) {
			// Example: 3–6 → 3,4,5,6
			for ( $d = $start; $d <= $end; $d++ ) {
				$days[] = $d;
			}
		} else {
			// Example: 5–2 → 5,6,7,1,2
			for ( $d = $start; $d <= 7; $d++ ) {
				$days[] = $d;
			}
			for ( $d = 1; $d <= $end; $d++ ) {
				$days[] = $d;
			}
		}

		return $days;
	}

	/**
	 * Checks if the meal plan contains a specific weekday.
	 *
	 * @param int $weekday The day to check (1–7).
	 *
	 * @return bool True if the plan contains the weekday, false otherwise.
	 */
	public function contains_weekday( int $weekday ): bool {

		$start = absint( $this->get_start_day() );
		$end   = absint( $this->get_end_day() );

		// Prevent invalid input
		if ( $weekday < 1 || $weekday > 7 ) {
			return false;
		}

		// Plan does not cross Sunday
		// Example: Start=2, End=5 → valid: 2,3,4,5
		if ( $start <= $end ) {
			return ( $weekday >= $start && $weekday <= $end );
		}

		// Plan crosses Sunday
		// Example: Start=6, End=2 → valid: 6,7,1,2
		return ( $weekday >= $start || $weekday <= $end );
	}

	/**
	 * Returns true if the plan contains at least one meal of the given type.
	 *
	 * @param string $type One of: breakfast, lunch, dinner, snack, other.
	 * @return bool
	 */
	public function contains_meal_type( string $type ): bool {
		foreach ( $this->get_meals() as $meal_post ) {
			$meal = new Mixmymeals_Meal( $meal_post->ID );
			if ( $meal->get_type() === $type ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Returns the servings range string for the plan.
	 * Example: "2–4 Servings" or "2 Servings".
	 *
	 * @return string The servings range string.
	 */
	public function get_servings_range_string(): string {

		$meals = $this->get_meals();
		if ( empty( $meals ) ) {
			return '';
		}

		$servings = array();

		foreach ( $meals as $meal_post ) {
			$meal = new Mixmymeals_Meal( $meal_post->ID );
			$val  = absint( $meal->get_servings() );
			if ( $val > 0 ) {
				$servings[] = $val;
			}
		}

		if ( empty( $servings ) ) {
			return '';
		}

		$min = min( $servings );
		$max = max( $servings );

		if ( $min === $max ) {
			if ( $min === 1 ) {
				return sprintf(
					'%d %s',
					$min,
					esc_html__( 'Serving', 'mixmymeals' )
				);
			}

			return sprintf(
				'%d %s',
				$min,
				esc_html__( 'Servings', 'mixmymeals' )
			);
		}

		return sprintf(
			'%d–%d %s',
			$min,
			$max,
			esc_html__( 'Servings', 'mixmymeals' )
		);
	}

	/**
	 * Retrieves all meals belonging to this meal plan.
	 *
	 * @return WP_Post[] Array of meal posts.
	 */
	/** @return WP_Post[] */
	public function get_meals(): array {
		return get_posts(
			array(
				'post_type'      => 'mmm_meal',
				'post_parent'    => $this->post_id,
				'post_status'    => 'publish',
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
				'posts_per_page' => -1,
			)
		);
	}

	/**
	 * How the plan's thumbnail strip is filled. Read through thumbs_mode(),
	 * which validates against this list rather than trusting the stored value
	 * — an unknown mode would otherwise decide what a public page shows.
	 *
	 * @var string
	 */
	const THUMBS_MODE_OPTION = 'mixmymeals_plan_thumbs_mode';

	/**
	 * How many thumbnails at most; 0 means every one the mode produces.
	 *
	 * @var string
	 */
	const THUMBS_MAX_OPTION = 'mixmymeals_plan_thumbs_max';

	const THUMBS_MODE_DEFAULT = 'one_per_day';
	const THUMBS_MAX_DEFAULT  = 7;

	/**
	 * The modes and their labels, in the order the setting offers them.
	 *
	 * @return array<string,string>
	 */
	public static function thumbs_modes(): array {

		return array(
			'one_per_day' => __( 'One picture per plan day', 'mixmymeals' ),
			'all'         => __( 'Every recipe of the plan', 'mixmymeals' ),
			'off'         => __( 'No thumbnails', 'mixmymeals' ),
		);
	}

	/**
	 * The configured mode, or the default when nothing valid is stored.
	 *
	 * @return string
	 */
	public static function thumbs_mode(): string {

		$mode = (string) get_option( self::THUMBS_MODE_OPTION, self::THUMBS_MODE_DEFAULT );

		return isset( self::thumbs_modes()[ $mode ] ) ? $mode : self::THUMBS_MODE_DEFAULT;
	}

	/**
	 * The configured maximum. Zero is a real answer here — "show them all" —
	 * and not the absence of one, which is why it is not mapped to a default.
	 *
	 * @return int
	 */
	public static function thumbs_max(): int {
		return absint( get_option( self::THUMBS_MAX_OPTION, self::THUMBS_MAX_DEFAULT ) );
	}

	/**
	 * Whether meals with no weekday get a group of their own in the plan rail.
	 *
	 * @var string
	 */
	const UNDATED_SHOW_OPTION = 'mixmymeals_plan_undated_meals';

	/**
	 * What that group is called.
	 *
	 * @var string
	 */
	const UNDATED_LABEL_OPTION = 'mixmymeals_plan_undated_meals_label';

	/**
	 * Whether the rail shows meals that carry no weekday.
	 *
	 * On by default. A meal without a day is a meal somebody planned, and the
	 * rail used to drop it on the floor: it appeared in no day, in no total on
	 * the page, and nowhere else either — while still counting in the shopping
	 * list and the nutrition figures, which is the combination that makes a
	 * plan look wrong without saying why.
	 *
	 * @return bool
	 */
	public static function undated_meals_shown(): bool {
		return 'on' === (string) get_option( self::UNDATED_SHOW_OPTION, 'on' );
	}

	/**
	 * The heading of that group. Free text, because what it means depends on
	 * the plan: a standing breakfast, something cooked ahead, a spare meal.
	 *
	 * @return string
	 */
	public static function undated_meals_label(): string {

		$label = trim( (string) get_option( self::UNDATED_LABEL_OPTION, '' ) );

		return '' !== $label ? $label : __( 'No fixed day', 'mixmymeals' );
	}

	/**
	 * The recipes whose featured images make up this plan's thumbnail strip,
	 * in the order they are to be shown.
	 *
	 * Only recipes that actually carry a featured image take part. A strip is
	 * pictures, and a recipe without one has nothing to put in it — which is
	 * why there is no fallback to "the best recipe of that day regardless of
	 * image": the one that used to stand here could never reach the page, as
	 * the renderer dropped imageless recipes again a few lines further down.
	 *
	 * Days are walked in the plan's own order rather than by weekday number:
	 * a plan running Fri–Tue starts on Friday, where sorting the day keys put
	 * Monday first.
	 *
	 * A recipe appears at most once. In one_per_day mode a day whose best
	 * recipe is already on the strip moves on to its next one, so a dish
	 * cooked twice does not cost that second day its picture.
	 *
	 * @param string|null $mode 'one_per_day', 'all' or 'off'; the setting when null.
	 * @param int|null    $max  Maximum number of pictures, 0 for all; the setting when null.
	 * @return int[] Recipe post IDs.
	 */
	public function get_thumbnail_recipe_ids( ?string $mode = null, ?int $max = null ): array {

		$mode = null === $mode ? self::thumbs_mode() : $mode;
		$max  = null === $max ? self::thumbs_max() : max( 0, $max );

		if ( 'off' === $mode ) {
			return array();
		}

		$candidates = array();
		$order      = 0;

		foreach ( $this->get_meals() as $meal_post ) {

			$meal      = new Mixmymeals_Meal( $meal_post->ID );
			$recipe_id = (int) $meal->get_recipe_id();

			if ( $recipe_id <= 0 || ! Mixmymeals_Recipe::exists_by_id( $recipe_id ) ) {
				continue;
			}

			if ( ! get_post_thumbnail_id( $recipe_id ) ) {
				continue;
			}

			$recipe = new Mixmymeals_Recipe( $recipe_id );

			$candidates[ (int) $meal->get_day() ][] = array(
				'recipe_id' => $recipe_id,
				'priority'  => $recipe->get_priority(),
				'order'     => $order++,
			);
		}

		if ( ! $candidates ) {
			return array();
		}

		$days = self::order_days( array_keys( $candidates ), $this->get_day_range() );

		foreach ( $days as $day ) {
			// Lowest priority wins, ties go to the meal that comes first in
			// the plan, so the strip does not reshuffle between page loads.
			usort(
				$candidates[ $day ],
				static function ( array $a, array $b ): int {
					return array( $a['priority'], $a['order'] ) <=> array( $b['priority'], $b['order'] );
				}
			);
		}

		$picked = array();

		foreach ( $days as $day ) {
			foreach ( $candidates[ $day ] as $entry ) {

				if ( isset( $picked[ $entry['recipe_id'] ] ) ) {
					continue;
				}

				$picked[ $entry['recipe_id'] ] = true;

				if ( 'all' !== $mode ) {
					break;
				}
			}
		}

		$ids = array_keys( $picked );

		return $max > 0 ? array_slice( $ids, 0, $max ) : $ids;
	}

	/**
	 * Sorts the days meals were found on into the plan's own day sequence.
	 *
	 * A day outside the plan's range — a meal left behind after the range was
	 * narrowed, or one never assigned a day — keeps its place at the end
	 * instead of disappearing.
	 *
	 * @param int[] $present Days that carry meals.
	 * @param int[] $range   The plan's day range, in plan order.
	 * @return int[]
	 */
	private static function order_days( array $present, array $range ): array {

		$present = array_map( 'intval', $present );
		$ordered = array();

		foreach ( $range as $day ) {
			if ( in_array( (int) $day, $present, true ) ) {
				$ordered[] = (int) $day;
			}
		}

		foreach ( $present as $day ) {
			if ( ! in_array( $day, $ordered, true ) ) {
				$ordered[] = $day;
			}
		}

		return $ordered;
	}

	/**
	 * The nutrition keys every calculation in this plugin works with.
	 *
	 * @var string[]
	 */
	const NUTRITION_KEYS = array( 'calories', 'fat', 'carbs', 'protein', 'sugar', 'fiber', 'salt' );

	/**
	 * Reference intakes for an average adult, used to put a figure in context:
	 * "1 850 kcal" says little on its own, "93 % of the reference intake" says
	 * what it means.
	 *
	 * The figures are the customary reference values for an average adult, the
	 * same ones a supermarket label prints, so a recipe and a packet can be
	 * compared directly.
	 *
	 * They describe an average adult on 2 000 kcal, not the person reading
	 * them: actual needs vary with age, sex, weight and activity. Anything
	 * rendering them has to say so — see
	 * Mixmymeals_Meal_Plan::reference_intake_note(). Sites with a specific
	 * audience should set their own via the filter below.
	 *
	 * @var array<string,float>
	 */
	const REFERENCE_INTAKE = array(
		'calories' => 2000.0,
		'fat'      => 70.0,
		'carbs'    => 260.0,
		'protein'  => 50.0,
		'sugar'    => 90.0,
		'fiber'    => 30.0,
		'salt'     => 6.0,
	);

	/**
	 * The reference intakes, filterable so a site aimed at a specific audience
	 * (sports nutrition, children's catering) can set its own.
	 *
	 * @return array<string,float>
	 */
	public static function reference_intake(): array {

		$values = (array) apply_filters( 'mixmymeals_reference_intake', self::REFERENCE_INTAKE );

		// A filter returning nonsense must not turn into a division by zero
		// wherever a percentage is worked out.
		foreach ( self::NUTRITION_KEYS as $key ) {
			$value = (float) ( $values[ $key ] ?? 0.0 );

			$values[ $key ] = $value > 0 ? $value : self::REFERENCE_INTAKE[ $key ];
		}

		return $values;
	}

	/**
	 * The wording that has to accompany any percentage of the reference intake,
	 * so a number never reads as personal dietary advice.
	 *
	 * @return string
	 */
	public static function reference_intake_note(): string {

		return (string) apply_filters(
			'mixmymeals_reference_intake_note',
			__( 'Percentages refer to the reference intake of an average adult (2 000 kcal). Your own needs may differ.', 'mixmymeals' )
		);
	}

	/**
	 * Expresses nutrition figures as a percentage of the reference intake.
	 * Keys without a figure come back as 0.0, so callers can always index all
	 * seven.
	 *
	 * @param array<string,float> $nutrition Absolute figures (kcal and grams).
	 * @return array<string,float> Percentages, rounded to whole numbers.
	 */
	public static function nutrition_percentages( array $nutrition ): array {

		$reference = self::reference_intake();
		$out       = array();

		foreach ( self::NUTRITION_KEYS as $key ) {
			$value       = (float) ( $nutrition[ $key ] ?? 0.0 );
			$out[ $key ] = round( $value / $reference[ $key ] * 100 );
		}

		return $out;
	}

	/**
	 * Localised labels for the seven nutrition keys, in the order a food label
	 * lists them.
	 *
	 * @return array<string,string>
	 */
	public static function nutrition_labels(): array {

		return array(
			'calories' => __( 'Energy', 'mixmymeals' ),
			'fat'      => __( 'Fat', 'mixmymeals' ),
			'carbs'    => __( 'Carbohydrate', 'mixmymeals' ),
			'sugar'    => __( 'of which sugars', 'mixmymeals' ),
			'fiber'    => __( 'Fibre', 'mixmymeals' ),
			'protein'  => __( 'Protein', 'mixmymeals' ),
			'salt'     => __( 'Salt', 'mixmymeals' ),
		);
	}

	/**
	 * Formats one nutrition value with its unit: kcal for energy, grams for
	 * everything else. Salt gets two decimals because a meaningful salt figure
	 * lives in tenths of a gram, where 0 or 1 would be useless.
	 *
	 * @param string $key   Nutrition key.
	 * @param float  $value Absolute value.
	 * @return string
	 */
	public static function format_nutrition_value( string $key, float $value ): string {

		if ( 'calories' === $key ) {
			return sprintf(
				/* translators: %s: kilocalories. */
				__( '%s kcal', 'mixmymeals' ),
				number_format_i18n( round( $value ) )
			);
		}

		return sprintf(
			/* translators: %s: an amount in grams. */
			__( '%s g', 'mixmymeals' ),
			number_format_i18n( $value, 'salt' === $key ? 2 : 1 )
		);
	}

	/**
	 * Aggregates the plan's nutrition, optionally for a single plan weekday.
	 * The counterpart to calc_shopping_list() and deliberately built on the same
	 * per-serving figures the recipe pages and the PDF already use, so all views
	 * agree to the last kcal.
	 *
	 * Two figures are returned because they answer different questions:
	 *  - 'totals'     everything cooked, i.e. per-serving values times the meal's
	 *                 servings — what comes out of the kitchen.
	 *  - 'per_person' one serving of each meal — what a single person eats.
	 *
	 * @param int|null $day When set (1-7), only include meals on that plan
	 *                      weekday; otherwise the whole plan.
	 * @return array{totals:array<string,float>, per_person:array<string,float>, servings:float, meals:int}
	 */
	public function calc_nutrition( ?int $day = null ): array {

		$result = array(
			'totals'     => array_fill_keys( self::NUTRITION_KEYS, 0.0 ),
			'per_person' => array_fill_keys( self::NUTRITION_KEYS, 0.0 ),
			'servings'   => 0.0,
			'meals'      => 0,
		);

		foreach ( $this->get_meals() as $meal_post ) {

			$meal = new Mixmymeals_Meal( $meal_post->ID );

			if ( null !== $day && (int) $meal->get_day() !== $day ) {
				continue;
			}

			$recipe = new Mixmymeals_Recipe( $meal->get_recipe_id() );

			if ( ! $recipe->is_valid() ) {
				continue;
			}

			$per_serving   = $recipe->calculate_nutrition_per_serving();
			$meal_servings = max( 0.01, (float) $meal->get_servings() );

			$result['servings'] += $meal_servings;
			++$result['meals'];

			foreach ( self::NUTRITION_KEYS as $key ) {
				$value = (float) ( $per_serving[ $key ] ?? 0.0 );

				$result['totals'][ $key ]     += $value * $meal_servings;
				$result['per_person'][ $key ] += $value;
			}
		}

		return $result;
	}

	/**
	 * The plan's nutrition broken down by weekday, keyed by day number (1-7) in
	 * the plan's own order — so a plan running Friday to Tuesday starts at 5 and
	 * wraps around. Days without meals are present with zeroes, which keeps gaps
	 * in a plan visible instead of silently dropping them.
	 *
	 * @return array<int, array{totals:array<string,float>, per_person:array<string,float>, servings:float, meals:int}>
	 */
	public function calc_nutrition_by_day(): array {

		$by_day = array();

		foreach ( $this->get_day_range() as $day ) {
			$by_day[ (int) $day ] = $this->calc_nutrition( (int) $day );
		}

		return $by_day;
	}

	/**
	 * What one person eats on an average day of this plan.
	 *
	 * Averaged over the days that **carry meals**, not over the plan's length:
	 * a plan with two empty days would otherwise look far lighter than anything
	 * anyone eats.
	 *
	 * A day counts by its meals, not by its energy. Counting by energy — which
	 * this did until 3.12.2 — silently excluded days whose recipes carry no
	 * nutrition data yet, and that had two bad consequences. Filling in one
	 * recipe's figures could *lower* the reported daily average, because the
	 * divisor grew by a whole day while the sum grew by a little. And it made
	 * the divisor depend on the very number a product choice changes, so
	 * calc_product_impact() could promise a figure the plan would never reach:
	 * a day at zero kcal that a product lifts above zero joins the divisor, and
	 * the average moves non-linearly.
	 *
	 * The single implementation of that rule. It was written out four times —
	 * the plan's nutrition overview, the subscription mail, the PDF, and (with
	 * the wrong divisor) the product comparison — which is how the last of them
	 * came to print a different daily figure than the table two sections above
	 * it on the same page.
	 *
	 * @return array{average:array<string,float>, sum:array<string,float>, days:int}
	 *         The average per key, the totals it was divided from, and how many
	 *         days carried meals. Callers that need a minimum span before the
	 *         average says anything check `days`; the PDF prints `sum` as its
	 *         total row, which is why it is returned rather than recomputed.
	 */
	public function calc_nutrition_per_day_person(): array {

		$sum  = array_fill_keys( self::NUTRITION_KEYS, 0.0 );
		$days = 0;

		foreach ( $this->calc_nutrition_by_day() as $figures ) {

			if ( empty( $figures['meals'] ) ) {
				continue;
			}

			++$days;

			foreach ( self::NUTRITION_KEYS as $key ) {
				$sum[ $key ] += (float) ( $figures['per_person'][ $key ] ?? 0.0 );
			}
		}

		$average = array_fill_keys( self::NUTRITION_KEYS, 0.0 );

		if ( $days > 0 ) {
			foreach ( self::NUTRITION_KEYS as $key ) {
				$average[ $key ] = $sum[ $key ] / $days;
			}
		}

		return array(
			'average' => $average,
			'sum'     => $sum,
			'days'    => $days,
		);
	}

	/**
	 * How much of each ingredient one person eats over the whole plan, in grams.
	 *
	 * Built the way calc_nutrition() builds its per_person figures — one
	 * portion per meal, from get_ingredients_scaled( 1.0 ) divided by the
	 * recipe's servings — and not the way calc_shopping_list() builds its
	 * totals, which are scaled by the meal's servings and rounded up to whole
	 * packs. Both are correct for their own question; mixing them would put a
	 * shopping quantity into a nutrition calculation and count rounding-up as
	 * something somebody ate.
	 *
	 * @return array<int, array{grams_person:float, meals:int}> Keyed by ingredient ID.
	 */
	public function get_ingredient_portion_weights(): array {

		$weights = array();

		foreach ( $this->get_meals() as $meal_post ) {

			$meal   = new Mixmymeals_Meal( $meal_post->ID );
			$recipe = new Mixmymeals_Recipe( $meal->get_recipe_id() );

			if ( ! $recipe->is_valid() ) {
				continue;
			}

			$servings = max( 1.0, (float) $recipe->get_servings() );

			/*
			 * get_ingredients_scaled() returns one row per step, so an
			 * ingredient used in three steps of one recipe arrives three times.
			 * The grams must all be added — that is how much is in the dish —
			 * but the meal must be counted once, or a recipe that seasons in
			 * two places claims to be three meals.
			 */
			$counted = array();

			foreach ( $recipe->get_ingredients_scaled( 1.0 ) as $ing ) {

				$id     = (int) ( $ing['id'] ?? 0 );
				$weight = (float) ( $ing['weight_g'] ?? 0 );

				if ( $id <= 0 || $weight <= 0 ) {
					continue;
				}

				if ( ! isset( $weights[ $id ] ) ) {
					$weights[ $id ] = array(
						'grams_person' => 0.0,
						'meals'        => 0,
					);
				}

				$weights[ $id ]['grams_person'] += $weight / $servings;

				if ( ! isset( $counted[ $id ] ) ) {
					++$weights[ $id ]['meals'];
					$counted[ $id ] = true;
				}
			}
		}

		return $weights;
	}

	/**
	 * What choosing a different product would do to the plan's daily nutrition.
	 *
	 * All seven figures, not only energy: two hams can carry the same
	 * kilocalories and twice the salt, and a comparison that only weighed
	 * energy would drop that ingredient from the list entirely — hiding
	 * precisely the choice worth making.
	 *
	 * Only ingredients with more than one product, and only where at least one
	 * figure actually moves far enough to be printed. A page full of ±0 answers
	 * no question.
	 *
	 * Computed as differences rather than by re-running the plan once per
	 * product: nutrition is linear in weight, so a product stating x more per
	 * 100 g moves the plan by x times the grams eaten, divided by the days.
	 * Re-running would mean either a bypass parameter on get_nutrition() — the
	 * hole closed in 3.8.2 — or writing a reference to the database to ask a
	 * hypothetical question. The arithmetic is exact either way.
	 *
	 * @return array<int, array{ingredient_id:int, ingredient:string, meals:int, current:array<string,float>, products:array}>
	 */
	public function calc_product_impact(): array {

		$weights = $this->get_ingredient_portion_weights();

		if ( empty( $weights ) ) {
			return array();
		}

		$per_day = $this->calc_nutrition_per_day_person();

		/*
		 * The same divisor the average used, so a difference lands on the
		 * figure it is a difference to. Days without meals are excluded from
		 * both — dividing a change by the plan's full length while the figure
		 * it modifies was divided by the days that carry meals would put two
		 * disagreeing numbers in one table.
		 */
		$days = max( 1, $per_day['days'] );

		$per_ingredient = array();

		foreach ( $weights as $ingredient_id => $info ) {
			$per_ingredient[ $ingredient_id ] = array(
				'grams' => $info['grams_person'],
				'meals' => $info['meals'],
			);
		}

		return Mixmymeals_Product::impact_groups( $per_ingredient, $per_day['average'], (float) $days );
	}

	/**
	 * Whether a figure survives being formatted, i.e. is not rounded away.
	 *
	 * Compared as rendered strings rather than against an epsilon, because the
	 * precision differs per nutrient — salt carries two decimals, energy none —
	 * and the only difference worth calling a difference is one the reader can
	 * actually see.
	 *
	 * @param string $key   Nutrition key.
	 * @param float  $value The figure.
	 * @return bool
	 */
	public static function nutrition_value_is_printable( string $key, float $value ): bool {

		return self::format_nutrition_value( $key, abs( $value ) )
			!== self::format_nutrition_value( $key, 0.0 );
	}

	/**
	 * The localized name of a plan weekday.
	 *
	 * @param int  $day    Day number, 1 = Monday … 7 = Sunday.
	 * @param bool $abbrev Return the abbreviation (e.g. "Mon") instead.
	 * @return string
	 */
	public static function weekday_name( int $day, bool $abbrev = false ): string {

		global $wp_locale;

		if ( ! $wp_locale instanceof WP_Locale || $day < 1 || $day > 7 ) {
			return '';
		}

		$name = (string) $wp_locale->get_weekday( $day % 7 ); // get_weekday: 0 = Sunday.

		return $abbrev ? (string) $wp_locale->get_weekday_abbrev( $name ) : $name;
	}

	/**
	 * Aggregates the plan's ingredient needs, grouped by category.
	 *
	 * @param bool     $sort_by_category Group the result by ingredient category.
	 * @param int|null $day              When set (1-7), only include meals on that
	 *                                   plan weekday; otherwise the whole plan.
	 * @return array
	 */
	public function calc_shopping_list( bool $sort_by_category = true, ?int $day = null ): array {

		$meals = $this->get_meals();

		if ( empty( $meals ) ) {
			return [];
		}

		$ingredient_totals = [];

		foreach ( $meals as $meal_post ) {

			$meal = new Mixmymeals_Meal( $meal_post->ID );

			if ( null !== $day && (int) $meal->get_day() !== $day ) {
				continue;
			}

			$recipe_id = $meal->get_recipe_id();

			if ( ! $recipe_id ) {
				continue;
			}

			$recipe = new Mixmymeals_Recipe( $recipe_id );

			$recipe_servings = max( 1.0, floatval( $recipe->get_servings() ) );
			$meal_servings   = max( 0.01, floatval( $meal->get_servings() ) );
			$scale           = $meal_servings / $recipe_servings;

			// 🔥 ZENTRALE LOGIK
			$ingredients = $recipe->get_ingredients_scaled( $scale );

			foreach ( $ingredients as $ing ) {

				if ( empty( $ing['id'] ) ) {
					continue;
				}

				$ing_id = intval( $ing['id'] );

				$ingredient_obj = new Mixmymeals_Ingredient( $ing_id );
				if ( ! $ingredient_obj->is_valid() ) {
					continue;
				}

				$amount_id       = intval( $ing['amount_id'] ?? 0 );
				$raw_amount      = floatval( $ing['raw_amount'] ?? $ing['amount'] ?? 0 );
				$weight_per_unit = floatval( $ing['weight_per_unit'] ?? 0 );
				$unit            = $ing['unit'] ?? '';

				if ( ! isset( $ingredient_totals[ $ing_id ] ) ) {
					$ingredient_totals[ $ing_id ] = [
						'details'      => [],
						'units'        => [],
						'total_weight' => 0,
						// Optional stays true only while every use of this
						// ingredient across the plan is optional; a single
						// required use flips it back to a normal purchase.
						'optional'     => true,
					];
				}

				$ingredient_totals[ $ing_id ]['optional'] =
					$ingredient_totals[ $ing_id ]['optional'] && ! empty( $ing['optional'] );

				// Rohbedarf je Einheit sammeln; unteilbare Einheiten werden
				// erst nach der Aggregation über alle Mahlzeiten gerundet.
				if ( ! isset( $ingredient_totals[ $ing_id ]['units'][ $amount_id ] ) ) {
					$ingredient_totals[ $ing_id ]['units'][ $amount_id ] = [
						'raw_amount'      => 0.0,
						'weight_per_unit' => $weight_per_unit,
						'is_indivisible'  => ! empty( $ing['is_indivisible'] ),
						'step_divisor'    => (int) ( $ing['step_divisor'] ?? 0 ),
						'unit_title'      => $unit,
					];
				}
				$ingredient_totals[ $ing_id ]['units'][ $amount_id ]['raw_amount'] += $raw_amount;

				$ingredient_totals[ $ing_id ]['details'][] = [
					'amount'      => $raw_amount,
					'unit_title'  => $unit,
					'weight_g'    => $weight_per_unit > 0
						? $raw_amount * $weight_per_unit
						: $raw_amount,
					'recipe_name' => get_the_title( $recipe_id ),
				];
			}
		}

		if ( empty( $ingredient_totals ) ) {
			return [];
		}

		/*
		 * Round up once over the whole requirement, not per meal — buying two
		 * tins for two half-tin recipes when one would do is exactly the error
		 * a shopping list must not make. Rounded up rather than to nearest,
		 * because at the shop too little is worse than too much.
		 *
		 * The step is what the unit can be split into: whole ones when it is
		 * indivisible, halves at a divisor of 2. So half a tin needed becomes
		 * half a tin bought, not a whole one.
		 */
		foreach ( $ingredient_totals as &$data ) {
			$total = 0.0;
			foreach ( $data['units'] as &$unit_data ) {
				$amount  = $unit_data['raw_amount'];
				$divisor = (int) ( $unit_data['step_divisor'] ?? 0 );

				if ( $divisor > 0 ) {
					$amount = max( 1.0, ceil( $amount * $divisor ) ) / $divisor;
				}

				$weight = $unit_data['weight_per_unit'] > 0
					? $amount * $unit_data['weight_per_unit']
					: $amount;

				$unit_data['amount']   = $amount;
				$unit_data['weight_g'] = $weight;

				$total += $weight;
			}
			unset( $unit_data );
			$data['total_weight'] = $total;
		}
		unset( $data );

		// 👉 Gruppieren nach Kategorie
		$ingredients_by_cat = [];

		if ( $sort_by_category ) {

			foreach ( $ingredient_totals as $ing_id => $data ) {

				$terms = wp_get_post_terms( $ing_id, 'mmm_ingredient_category' );
				if ( is_wp_error( $terms ) ) {
					$terms = [];
				}

				$cat_name = ! empty( $terms )
					? $terms[0]->name
					: __( 'Other', 'mixmymeals' );

				$ingredients_by_cat[ $cat_name ][ $ing_id ] = $data;
			}

			ksort( $ingredients_by_cat, SORT_NATURAL | SORT_FLAG_CASE );

		} else {
			$ingredients_by_cat[ __( 'All Ingredients', 'mixmymeals' ) ] = $ingredient_totals;
		}

		return $ingredients_by_cat;
	}

	/**
	 * Returns the deduplicated equipment post IDs required by any meal's
	 * recipe in this plan, sorted alphabetically by title.
	 *
	 * @return int[]
	 */
	public function get_required_equipment(): array {

		$equipment_ids = [];

		foreach ( $this->get_meals() as $meal_post ) {

			$meal      = new Mixmymeals_Meal( $meal_post->ID );
			$recipe_id = $meal->get_recipe_id();

			if ( ! $recipe_id ) {
				continue;
			}

			$recipe = new Mixmymeals_Recipe( $recipe_id );
			if ( ! $recipe->is_valid() ) {
				continue;
			}

			foreach ( $recipe->get_equipment() as $eq_id ) {
				$equipment_ids[ (int) $eq_id ] = (int) $eq_id;
			}
		}

		if ( empty( $equipment_ids ) ) {
			return [];
		}

		$equipment_ids = array_values( $equipment_ids );
		usort( $equipment_ids, fn( $a, $b ) => strnatcasecmp( get_the_title( $a ), get_the_title( $b ) ) );

		return $equipment_ids;
	}

	/**
	 * Returns the title of the most recent (newest) published meal plan.
	 *
	 * @return string The title or empty string if none found.
	 */
	public static function get_latest_plan_title(): string {

		$plans = get_posts(
			array(
				'post_type'      => 'mmm_meal_plan',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'fields'         => 'ids',
			)
		);

		if ( empty( $plans ) ) {
			return '';
		}

		$title = get_the_title( $plans[0] );

		return is_string( $title ) ? $title : '';
	}
	/**
	 * Internal helper: returns a random recipe ID from the newest meal plan
	 * (cached per request). Prefers recipes with a real featured image so the
	 * image shortcode has something to show; when no recipe of the plan has a
	 * photo, falls back to any valid recipe (the title shortcode still works,
	 * the image shortcode then cleanly renders nothing).
	 *
	 * @return int Recipe ID or 0 if none found.
	 */
	private static function get_random_recipe_id_from_latest_plan_cached(): int {

		if ( null !== self::$cached_random_recipe_id ) {
			return absint( self::$cached_random_recipe_id );
		}

		// 1) Latest plan
		$plans = get_posts(
			array(
				'post_type'      => 'mmm_meal_plan',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'fields'         => 'ids',
			)
		);

		if ( empty( $plans ) ) {
			self::$cached_random_recipe_id = 0;
			return 0;
		}

		$latest_plan_id = absint( $plans[0] );

		// 2) Meals in latest plan
		$meal_ids = get_posts(
			array(
				'post_type'      => 'mmm_meal',
				'post_parent'    => $latest_plan_id,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		if ( empty( $meal_ids ) ) {
			self::$cached_random_recipe_id = 0;
			return 0;
		}

		shuffle( $meal_ids );

		$fallback_id = 0;

		foreach ( $meal_ids as $meal_id ) {
			$meal = new Mixmymeals_Meal( absint( $meal_id ) );

			if ( ! method_exists( $meal, 'get_recipe_id' ) ) {
				continue;
			}

			$recipe_id = absint( $meal->get_recipe_id() );

			if ( $recipe_id <= 0 ) {
				continue;
			}

			if ( ! $fallback_id ) {
				$fallback_id = $recipe_id;
			}

			// Prefer a recipe with a real featured image.
			if ( ! get_post_thumbnail_id( $recipe_id ) ) {
				continue;
			}

			self::$cached_random_recipe_id = $recipe_id;
			return $recipe_id;
		}

		self::$cached_random_recipe_id = $fallback_id;
		return $fallback_id;
	}

	/**
	 * Returns the recipe title behind a random meal from the newest meal plan.
	 *
	 * @return string Recipe title or empty string if none found.
	 */
	public static function get_random_recipe_title_from_latest_plan(): string {

		$recipe_id = self::get_random_recipe_id_from_latest_plan_cached();
		if ( ! $recipe_id ) {
			return '';
		}

		$title = get_the_title( $recipe_id );

		return is_string( $title ) ? $title : '';
	}

	/**
	 * Returns the featured image (thumbnail) of a random recipe from the newest meal plan as <img>.
	 *
	 * @param string $size Image size (e.g. 'thumbnail', 'medium', 'large', 'full').
	 * @param array  $attr Optional img attributes (e.g. ['class' => 'my-class', 'loading' => 'lazy']).
	 *
	 * @return string HTML <img> tag or empty string if none found.
	 */
	public static function get_random_recipe_image_from_latest_plan( string $size = 'medium', array $attr = [] ): string {

		$recipe_id = self::get_random_recipe_id_from_latest_plan_cached();
		if ( ! $recipe_id ) {
			return '';
		}

		if ( ! has_post_thumbnail( $recipe_id ) ) {
			return '';
		}

		$defaults = array(
			'class'   => 'mmm-random-recipe-image',
			'loading' => 'lazy',
			'alt'     => get_the_title( $recipe_id ),
		);

		$attr = array_merge( $defaults, $attr );

		return get_the_post_thumbnail( $recipe_id, $size, $attr );
	}

	public static function get_random_recipe_id_from_latest_plan(): int {
		return self::get_random_recipe_id_from_latest_plan_cached();
	}

	public function calculate_avg_cost_per_serving(): float {
		$meals = $this->get_meals();
		if ( empty( $meals ) ) {
			return 0.0;
		}
		$total = 0.0;
		$count = 0;
		foreach ( $meals as $meal_post ) {
			$meal   = new Mixmymeals_Meal( $meal_post->ID );
			$recipe = new Mixmymeals_Recipe( $meal->get_recipe_id() );
			if ( ! $recipe->is_valid() ) {
				continue;
			}
			$cost = $recipe->calculate_cost_per_serving();
			if ( $cost > 0 ) {
				$total += $cost;
				$count++;
			}
		}
		return $count > 0 ? $total / $count : 0.0;
	}

	/**
	 * Derives the diet type of the meal plan from all its recipes.
	 *
	 * Returns 'vegan' if every recipe is vegan, 'vegetarian' if every recipe
	 * is vegan or vegetarian, and '' otherwise or if the plan has no meals.
	 * A single invalid recipe causes an early '' return.
	 *
	 * The per-recipe diet comes from Mixmymeals_Recipe::get_diet_type(), i.e.
	 * from the ingredients' _mmm_diet_type meta — the same source the recipe
	 * and meal badges use, so a plan can never disagree with its recipes.
	 *
	 * @return string 'vegan', 'vegetarian', or ''.
	 */
	public function get_diet_type(): string {
		return Mixmymeals_Diet::strongest( $this->collect_diets( false ), 'diet' );
	}

	/**
	 * What diet this plan can be cooked for, if every ingredient of every dish
	 * is bought accordingly.
	 *
	 * The counterpart to get_diet_type(), one level up: it asks each recipe
	 * what it is *suitable* for rather than what its reference products make
	 * of it. A dish whose recipe no longer exists leaves the question open for
	 * the whole plan, as it does there.
	 *
	 * @return string 'vegan', 'vegetarian', or ''.
	 */
	public function get_diet_suitability(): string {
		return Mixmymeals_Diet::strongest( $this->collect_diets( true ), 'diet' );
	}

	/**
	 * Every diet the plan holds, across its dishes.
	 *
	 * @param bool $suitability Ask each recipe what it is suitable for rather
	 *                          than what its chosen products make of it.
	 * @return string[] Known diet slugs.
	 */
	public function get_diets( bool $suitability = false ): array {
		return $this->collect_diets( $suitability );
	}

	/**
	 * Reduces the plan's dishes to the diets they share.
	 *
	 * A dish whose recipe no longer exists leaves the question open for the
	 * whole plan — the same answer an unclassified ingredient gives a recipe,
	 * and for the same reason: what is gone might have been anything.
	 *
	 * @param bool $suitability Suitability rather than what is in force.
	 * @return string[]
	 */
	private function collect_diets( bool $suitability ): array {

		$sets = array();

		foreach ( $this->get_meals() as $meal_post ) {

			$recipe = new Mixmymeals_Recipe( ( new Mixmymeals_Meal( $meal_post->ID ) )->get_recipe_id() );

			if ( ! $recipe->is_valid() ) {
				return array();
			}

			$sets[] = $suitability ? $recipe->get_diet_suitabilities() : $recipe->get_diets();
		}

		return Mixmymeals_Diet::combine( $sets );
	}
	/**
	 * Builds the human-readable needed-amount string for one shopping
	 * list ingredient from its per-unit totals.
	 *
	 * Indivisible units are listed as counts ("3× Stück"), everything
	 * else is collapsed into grams. A gram equivalent is appended when
	 * piece counts are shown.
	 *
	 * Moved here from Mixmymeals_Shortcodes: it formats the output of
	 * calc_shopping_list(), which lives in this class.
	 *
	 * @param array $units        Per-unit totals from calc_shopping_list().
	 * @param float $total_weight Total weight in grams.
	 * @return string E.g. "3× Stück + 150 g (≈ 405 g)".
	 */
	/**
	 * A countable quantity for human eyes: locale separators, and only as many
	 * decimals as the value actually needs — "1", "0,5", "0,75".
	 *
	 * Deliberately not Mixmymeals_Ingredient::format_amount(), which is the
	 * parser-facing format: it prints a decimal point on purpose, because
	 * Bring! reads "0,5" as something else entirely. That one belongs in the
	 * Bring! lines, this one on the page.
	 *
	 * @param float $value Quantity in units.
	 * @return string
	 */
	private static function format_count_i18n( float $value ): string {

		$decimals = 0;

		if ( abs( $value - round( $value ) ) > 0.001 ) {
			$decimals = abs( $value * 10 - round( $value * 10 ) ) < 0.001 ? 1 : 2;
		}

		return number_format_i18n( $value, $decimals );
	}

	/**
	 * Whether a shopping-list unit is something you count rather than weigh.
	 *
	 * True for anything that can only be taken in fixed steps — whole ones
	 * when indivisible, halves at a divisor of 2. Those belong in the list as
	 * "1,5× Dose", because that is what goes in the basket; a unit without a
	 * step is better expressed as a weight.
	 *
	 * @param array $unit One entry of an ingredient's 'units' array.
	 * @return bool
	 */
	private static function unit_is_counted( array $unit ): bool {

		return ! empty( $unit['is_indivisible'] ) || (int) ( $unit['step_divisor'] ?? 0 ) > 0;
	}

	public static function build_needed_amount_string( array $units, float $total_weight ): string {

		$piece_parts = [];
		$loose_grams = 0.0;

		foreach ( $units as $unit ) {
			/*
			 * Counted, not weighed, whenever the unit has a step at all — a
			 * divisor of 2 makes "1,5× Dose" the honest answer, and turning it
			 * into grams would hide what to put in the basket. format_amount()
			 * rather than %d for exactly that reason: %d turned half a tin
			 * into a whole one.
			 */
			if ( self::unit_is_counted( $unit ) ) {
				$piece_parts[] = trim( sprintf(
					'%s× %s',
					self::format_count_i18n( (float) ( $unit['amount'] ?? 0 ) ),
					$unit['unit_title'] ?? ''
				) );
			} else {
				$loose_grams += (float) ( $unit['weight_g'] ?? 0 );
			}
		}

		$parts = $piece_parts;

		if ( $loose_grams > 0 || empty( $parts ) ) {
			$parts[] = number_format_i18n( round( $loose_grams ) ) . ' g';
		}

		$result = implode( ' + ', $parts );

		if ( ! empty( $piece_parts ) && $total_weight > 0 ) {
			$result .= sprintf(
				' (≈ %s g)',
				number_format_i18n( round( $total_weight ) )
			);
		}

		return $result;
	}

	/**
	 * Short amount for teasers — one figure, no compounds.
	 *
	 * build_needed_amount_string() is built for the full shopping list, where
	 * "3× EL + 400 g (≈ 430 g)" is the honest answer. On a teaser that has room
	 * for one line per item it only produces noise, so this variant commits to a
	 * single figure:
	 *
	 * - every unit indivisible → count and unit, e.g. "4 EL", "2 Stück"
	 * - otherwise             → total weight, switching to kg from 1000 g
	 *
	 * The full breakdown stays available on the meal plan page.
	 *
	 * @param array $units        Units of one ingredient from calc_shopping_list().
	 * @param float $total_weight Total weight in grams.
	 * @return string
	 */
	public static function build_short_amount_string( array $units, float $total_weight ): string {

		$divisible = array_filter(
			$units,
			static fn( $unit ) => ! self::unit_is_counted( $unit )
		);

		// Nur Stückgut: Anzahl und Einheit sagen mehr als ein Gewicht.
		if ( ! empty( $units ) && empty( $divisible ) ) {

			$count = 0;
			$label = '';

			foreach ( $units as $unit ) {
				$count += (float) ( $unit['amount'] ?? 0 );
				$label  = (string) ( $unit['unit_title'] ?? '' );
			}

			return trim( self::format_count_i18n( $count ) . ' ' . $label );
		}

		$grams = (int) round( $total_weight );

		if ( $grams >= 1000 ) {
			$kilos = $grams / 1000;

			return number_format_i18n( $kilos, 0 === $grams % 1000 ? 0 : 1 ) . ' kg';
		}

		return number_format_i18n( $grams ) . ' g';
	}

	/* ---------------------------------------------------------------------
	 * Structured data
	 * ------------------------------------------------------------------ */

	/**
	 * An ItemList of the plan's recipes (deduplicated, day order) — what a
	 * search engine reads off a plan page. Printed by
	 * Mixmymeals_Post_Types::output_schema().
	 *
	 * @return array Empty array when the plan holds no published recipe.
	 */
	public function get_schema(): array {

		$seen  = array();
		$items = array();

		foreach ( $this->get_meals() as $meal_post ) {
			$meal      = new Mixmymeals_Meal( $meal_post->ID );
			$recipe_id = (int) $meal->get_recipe_id();

			if ( ! $recipe_id || isset( $seen[ $recipe_id ] ) || 'publish' !== get_post_status( $recipe_id ) ) {
				continue;
			}
			$seen[ $recipe_id ] = true;

			$items[] = array(
				'@type'    => 'ListItem',
				'position' => count( $items ) + 1,
				'url'      => get_permalink( $recipe_id ),
				'name'     => get_the_title( $recipe_id ),
			);
		}

		if ( ! $items ) {
			return array();
		}

		$plan_id = $this->post_id;

		$schema = array(
			'@context'        => 'https://schema.org',
			'@type'           => 'ItemList',
			'name'            => get_the_title( $plan_id ),
			'url'             => get_permalink( $plan_id ),
			'numberOfItems'   => count( $items ),
			'itemListElement' => $items,
		);

		/**
		 * Filters the finished meal plan ItemList schema before output.
		 *
		 * @param array $schema  Schema.org ItemList structure.
		 * @param int   $plan_id Meal plan post ID.
		 */
		return apply_filters( 'mixmymeals_meal_plan_schema', $schema, $plan_id );
	}

	/* ---------------------------------------------------------------------
	 * Bring! import view
	 * ------------------------------------------------------------------ */

	/**
	 * Query var that switches a meal plan permalink to the Bring! import view.
	 *
	 * @var string
	 */
	const BRING_QUERY_VAR = 'mmm_bring';

	/**
	 * Registers the Bring! import view. Called once per request from
	 * mixmymeals.php, same pattern as Mixmymeals_Recipe::register_cook_count().
	 *
	 * @return void
	 */
	public static function register_bring(): void {

		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		add_filter( 'query_vars', array( __CLASS__, 'register_bring_query_var' ) );
		add_action( 'template_redirect', array( __CLASS__, 'render_bring_page' ) );
	}

	/**
	 * Registers the Bring! query var so ?mmm_bring=1 survives the parse.
	 *
	 * @param string[] $vars Public query vars.
	 * @return string[]
	 */
	public static function register_bring_query_var( array $vars ): array {

		$vars[] = self::BRING_QUERY_VAR;

		return $vars;
	}

	/**
	 * The Bring! deep link for a meal plan.
	 *
	 * Bring! fetches the passed URL itself and imports the schema.org/Recipe
	 * it finds there, so the link points at the plan's Bring! import view
	 * rather than at the plan page (which is an ItemList, not a Recipe).
	 *
	 * @param int $plan_id Meal plan post ID.
	 * @return string Empty string when the plan has no permalink.
	 */
	public static function bring_deeplink( int $plan_id ): string {

		$permalink = get_permalink( $plan_id );
		if ( ! $permalink ) {
			return '';
		}

		return sprintf(
			'https://api.getbring.com/rest/bringrecipes/deeplink?url=%s&source=web&baseQuantity=1&requestedQuantity=1',
			rawurlencode( add_query_arg( self::BRING_QUERY_VAR, '1', $permalink ) )
		);
	}

	/**
	 * Renders the stripped-down import page Bring! scrapes and exits.
	 *
	 * Kept out of the theme on purpose: the page carries nothing but the
	 * shopping list as a Recipe, is marked noindex and never becomes part of
	 * the site's own structured data.
	 *
	 * @return void
	 */
	public static function render_bring_page(): void {

		if ( ! is_singular( 'mmm_meal_plan' ) || ! get_query_var( self::BRING_QUERY_VAR ) ) {
			return;
		}

		$plan_id = (int) get_queried_object_id();
		$schema  = ( new self( $plan_id ) )->get_bring_recipe_schema();

		// Nothing to import — let the normal plan page render instead.
		if ( empty( $schema['recipeIngredient'] ) ) {
			return;
		}

		$title     = get_the_title( $plan_id );
		$permalink = get_permalink( $plan_id );

		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Robots-Tag: noindex, follow', true );

		?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="robots" content="noindex, follow">
	<title><?php echo esc_html( $title ); ?></title>
	<link rel="canonical" href="<?php echo esc_url( $permalink ); ?>">
	<script type="application/ld+json"><?php
		echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	?></script>
</head>
<body>
	<h1><?php echo esc_html( $title ); ?></h1>
	<ul>
		<?php foreach ( $schema['recipeIngredient'] as $line ) : ?>
			<li><?php echo esc_html( $line ); ?></li>
		<?php endforeach; ?>
	</ul>
	<p><a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a></p>
</body>
</html>
		<?php

		exit;
	}

	/**
	 * Builds a schema.org/Recipe whose ingredients are the plan's shopping
	 * list — the shape Bring! expects from an import URL.
	 *
	 * @return array Empty array when the plan has no ingredients.
	 */
	public function get_bring_recipe_schema(): array {

		$lines = $this->get_bring_shopping_list_lines();
		if ( ! $lines ) {
			return array();
		}

		$plan_id = $this->post_id;

		$schema = array(
			'@context'         => 'https://schema.org',
			'@type'            => 'Recipe',
			'name'             => get_the_title( $plan_id ),
			'url'              => get_permalink( $plan_id ),
			'recipeIngredient' => $lines,
		);

		$description = Mixmymeals_Post_Types::meta_description( $plan_id );
		if ( '' !== $description ) {
			$schema['description'] = $description;
		}

		$images = Mixmymeals_Post_Types::schema_image_urls( $plan_id );
		if ( $images ) {
			$schema['image'] = $images;
		}

		/**
		 * Filters the Recipe schema handed to Bring! for a meal plan.
		 *
		 * @param array $schema  Schema.org Recipe structure.
		 * @param int   $plan_id Meal plan post ID.
		 */
		return apply_filters( 'mixmymeals_bring_recipe_schema', $schema, $plan_id );
	}

	/**
	 * Shopping list as Bring!-parsable lines ("405 g Möhren", "3 Stück Eier").
	 *
	 * Amount first, then unit, then name: that is the order Bring! splits on.
	 * Indivisible units keep their unit, everything else is collapsed into
	 * grams, mirroring what the shopping list shortcode displays.
	 *
	 * @return string[]
	 */
	public function get_bring_shopping_list_lines(): array {

		$lines = array();

		// Grouping by category would only add headings we cannot use here.
		foreach ( $this->calc_shopping_list( false ) as $ingredients ) {
			foreach ( $ingredients as $ing_id => $data ) {

				$ingredient = new Mixmymeals_Ingredient( (int) $ing_id );
				if ( ! $ingredient->is_valid() ) {
					continue;
				}

				$name = trim( wp_strip_all_tags( get_the_title( (int) $ing_id ) ) );
				if ( '' === $name ) {
					continue;
				}

				$loose_grams = 0.0;
				$added       = false;

				foreach ( (array) ( $data['units'] ?? array() ) as $unit ) {
					if ( self::unit_is_counted( $unit ) ) {
						$lines[] = trim( sprintf(
							'%s %s %s',
							Mixmymeals_Ingredient::format_amount( (float) ( $unit['amount'] ?? 0 ) ),
							trim( (string) ( $unit['unit_title'] ?? '' ) ),
							$name
						) );
						$added   = true;
					} else {
						$loose_grams += (float) ( $unit['weight_g'] ?? 0 );
					}
				}

				if ( $loose_grams > 0 ) {
					$lines[] = Mixmymeals_Ingredient::format_amount( round( $loose_grams ) ) . ' g ' . $name;
					$added   = true;
				}

				// Ingredient without any usable unit: hand over the bare name
				// so it still lands on the list.
				if ( ! $added ) {
					$total   = (float) ( $data['total_weight'] ?? 0 );
					$lines[] = $total > 0
						? Mixmymeals_Ingredient::format_amount( round( $total ) ) . ' g ' . $name
						: $name;
				}
			}
		}

		return $lines;
	}



}
