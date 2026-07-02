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
	 * Calculates the shopping list data for this meal plan.
	 *
	 * @param bool $sort_by_category
	 * @return array
	 */
	/*
	public function calc_shopping_list( bool $sort_by_category = true ): array {

		$meals = $this->get_meals();

		if ( empty( $meals ) ) {
			return [];
		}

		$ingredient_totals = [];

		foreach ( $meals as $meal_post ) {

			$meal      = new Mixmymeals_Meal( $meal_post->ID );
			$recipe_id = $meal->get_recipe_id();

			if ( ! $recipe_id ) {
				continue;
			}

			$recipe = new Mixmymeals_Recipe( $recipe_id );
			$steps  = $recipe->get_steps();

			$recipe_servings = max( 1, floatval( $recipe->get_servings() ) );
			$meal_servings   = max( 0.01, floatval( $meal->get_servings() ) );
			$scale           = $meal_servings / $recipe_servings;

			foreach ( $steps as $step ) {
				foreach ( $step['ingredients'] ?? [] as $ing ) {

					if ( empty( $ing['id'] ) || ! isset( $ing['amount'] ) ) {
						continue;
					}

					$ing_id = intval( $ing['id'] );

					// Ignoriere invalide Zutaten
					$ingredient_obj = new Mixmymeals_Ingredient( $ing_id );
					if ( ! $ingredient_obj->is_valid() ) {
						continue;
					}

					$amount    = floatval( $ing['amount'] ) * $scale;
					$amount_id = ! empty( $ing['amount_id'] )
						? intval( $ing['amount_id'] )
						: 0;

					$unit_weight = 1;
					$unit_title  = 'g';

					if ( $amount_id ) {
						$amount_obj  = new Mixmymeals_Ingredient_Amount( $amount_id );
						$unit_weight = max( 0.0001, floatval( $amount_obj->get_weight() ) );
						$unit_title  = $amount_obj->get_title() ?: 'g';
					}

					if ( ! isset( $ingredient_totals[ $ing_id ] ) ) {
						$ingredient_totals[ $ing_id ] = [
							'details'      => [],
							'total_weight' => 0,
						];
					}

					$ingredient_totals[ $ing_id ]['details'][] = [
						'amount'      => $amount,
						'unit_title'  => $unit_title,
						'unit_weight' => $unit_weight,
						'recipe_name' => get_the_title( $recipe_id ),
					];

					$ingredient_totals[ $ing_id ]['total_weight'] += $amount * $unit_weight;
				}
			}
		}

		if ( empty( $ingredient_totals ) ) {
			return [];
		}

		// Gruppieren nach Kategorie
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
	*/

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
					];
				}

				// Rohbedarf je Einheit sammeln; unteilbare Einheiten werden
				// erst nach der Aggregation über alle Mahlzeiten gerundet.
				if ( ! isset( $ingredient_totals[ $ing_id ]['units'][ $amount_id ] ) ) {
					$ingredient_totals[ $ing_id ]['units'][ $amount_id ] = [
						'raw_amount'      => 0.0,
						'weight_per_unit' => $weight_per_unit,
						'is_indivisible'  => ! empty( $ing['is_indivisible'] ),
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

		// Unteilbare Einheiten einmal über den Gesamtbedarf aufrunden
		// (beim Einkaufen ist zu wenig schlimmer als zu viel),
		// dann Bedarf und Gewicht je Einheit festhalten und summieren.
		foreach ( $ingredient_totals as &$data ) {
			$total = 0.0;
			foreach ( $data['units'] as &$unit_data ) {
				$amount = $unit_data['raw_amount'];
				if ( $unit_data['is_indivisible'] ) {
					$amount = max( 1.0, ceil( $amount ) );
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
	 * Internal helper: returns a random recipe ID from the newest meal plan (cached per request).
	 *
	 * @return int Recipe ID or 0 if none found.
	 */
	/*
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

		// Robust: some meals might not have a recipe -> shuffle and pick first valid
		shuffle( $meal_ids );

		foreach ( $meal_ids as $meal_id ) {
			$meal = new Mixmymeals_Meal( absint( $meal_id ) );

			if ( ! method_exists( $meal, 'get_recipe_id' ) ) {
				continue;
			}

			$recipe_id = absint( $meal->get_recipe_id() );
			if ( $recipe_id > 0 ) {
				self::$cached_random_recipe_id = $recipe_id;
				return $recipe_id;
			}
		}

		self::$cached_random_recipe_id = 0;
		return 0;
	}
	*/
	// This variant does return recipes that use the placeholder image as
	// featured image:
	private static function get_random_recipe_id_from_latest_plan_cached(): int {

		if ( null !== self::$cached_random_recipe_id ) {
			return absint( self::$cached_random_recipe_id );
		}

		// Placeholder-ID einmal abrufen
		$placeholder_id = (int) get_option( 'mixmymeals_placeholder_image_id' );

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

		foreach ( $meal_ids as $meal_id ) {
			$meal = new Mixmymeals_Meal( absint( $meal_id ) );

			if ( ! method_exists( $meal, 'get_recipe_id' ) ) {
				continue;
			}

			$recipe_id = absint( $meal->get_recipe_id() );

			if ( $recipe_id <= 0 ) {
				continue;
			}

			// Prüfen: hat das Rezept ein Featured Image?
			$thumb_id = get_post_thumbnail_id( $recipe_id );
			if ( ! $thumb_id || $thumb_id === $placeholder_id ) {
				continue; // Skip, kein echtes Bild
			}

			self::$cached_random_recipe_id = $recipe_id;
			return $recipe_id;
		}

		self::$cached_random_recipe_id = 0;
		return 0;
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
	 * @return string 'vegan', 'vegetarian', or ''.
	 */
	public function get_diet_type(): string {
		$meals = $this->get_meals();
		if ( empty( $meals ) ) {
			return '';
		}

		$all_vegan      = true;
		$all_vegetarian = true;
		$diet_names     = [ 'vegan', 'vegetarisch', 'vegetarian' ];

		foreach ( $meals as $meal_post ) {
			$meal   = new Mixmymeals_Meal( $meal_post->ID );
			$recipe = new Mixmymeals_Recipe( $meal->get_recipe_id() );

			if ( ! $recipe->is_valid() ) {
				return '';
			}

			$recipe_diet = '';
			foreach ( $recipe->get_categories() as $cat ) {
				$name = strtolower( $cat->name );
				if ( $name === 'vegan' ) {
					$recipe_diet = 'vegan';
					break;
				}
				if ( in_array( $name, [ 'vegetarisch', 'vegetarian' ], true ) ) {
					$recipe_diet = 'vegetarian';
				}
			}

			if ( $recipe_diet !== 'vegan' ) {
				$all_vegan = false;
			}
			if ( $recipe_diet !== 'vegan' && $recipe_diet !== 'vegetarian' ) {
				$all_vegetarian = false;
				break;
			}
		}

		if ( $all_vegan ) {
			return 'vegan';
		}
		if ( $all_vegetarian ) {
			return 'vegetarian';
		}
		return '';
	}


}
