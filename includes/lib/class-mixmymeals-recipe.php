<?php
/**
 * Recipe domain object.
 *
 * @package MixMyMeals/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Represents a single recipe post and provides methods for reading and writing its data.
 */
class Mixmymeals_Recipe {

	private int $post_id;

	/**
	 * @param int $post_id Recipe post ID.
	 */
	public function __construct( int $post_id ) {
		if ( ! self::exists_by_id( $post_id ) ) {
			$this->post_id = 0;
			return;
		}

		$this->post_id = $post_id;
	}

	public function is_valid(): bool {
		return self::exists_by_id( $this->post_id );
	}

	public function get_id(): int {
		return $this->post_id;
	}

	/**
	 * @throws \Exception On post creation failure.
	 */
	public static function create( string $title, string $status = 'draft', string $excerpt = '' ): self {
		$post_id = wp_insert_post( array(
			'post_type'    => 'mmm_recipe',
			'post_title'   => sanitize_text_field( $title ),
			'post_status'  => $status,
			'post_excerpt' => sanitize_textarea_field( $excerpt ),
		), true );

		if ( is_wp_error( $post_id ) ) {
			throw new \Exception( esc_html( $post_id->get_error_message() ) );
		}

		return new self( $post_id );
	}

	public static function exists_by_id( int $post_id ): bool {

		return $post_id
		       && get_post_type( $post_id ) === 'mmm_recipe'
		       /*&& get_post_status( $post_id ) === 'publish'*/; // Checking for status published causes bugs with posts in draft status!
	}

	/*
	public function exists(): bool {
		return get_post_status( $this->post_id ) === 'publish';
	}
	*/

	/*
	---------------------------------------------------------- */
	/*
	STEPS HANDLING                                             */
	/* ---------------------------------------------------------- */

	/**
	 * Get all published recipes (cached).
	 *
	 * @return WP_Post[] Array of recipe posts.
	 */
	/** @return WP_Post[] */
	public static function get_all_recipes(): array {
		static $cache = null;

		if ( $cache === null ) {
			$cache = get_posts(
				array(
					'post_type'      => 'mmm_recipe',
					'posts_per_page' => -1,
					'nopaging'       => true,
					'post_status'    => 'publish',
					'orderby'        => 'title',
					'order'          => 'ASC',
				)
			);
		}

		return $cache;
	}

	/**
	 * Liefert alle Terms der Rezept-Taxonomie.
	 *
	 * @param   array $args  Optional. Zusätzliche Argumente für get_terms.
	 *
	 * @return WP_Term[]|WP_Error
	 */
	/** @return WP_Term[]|WP_Error */
	public static function get_all_recipe_categories( array $args = array() ): array|WP_Error {
		$defaults = array(
			'taxonomy'   => 'mmm_recipe_category',
			'hide_empty' => true,
			// false, falls du auch leere Kategorien zeigen möchtest
			'orderby'    => 'name',
			'order'      => 'ASC',
		);
		$args     = wp_parse_args( $args, $defaults );

		return get_terms( $args );
	}

	/**
	 * Liefert Rezepte für einen Term (Term-ID oder Term-Objekt akzeptiert).
	 *
	 * @param   int|WP_Term $term        Term-ID oder Term-Objekt.
	 * @param   array       $query_args  Zusätzliche WP_Query-Argumente (z.B. posts_per_page, order, orderby).
	 *
	 * @return WP_Post[] Array von Post-Objekten.
	 */
	/**
	 * @param int|WP_Term $term
	 * @return WP_Post[]
	 */
	public static function get_recipes_by_term( int|WP_Term $term, array $query_args = array() ): array {
		if ( is_object( $term ) && isset( $term->term_id ) ) {
			$term_id = absint( $term->term_id );
		} else {
			$term_id = absint( $term );
		}

		if ( ! $term_id ) {
			return array();
		}

		$defaults = array(
			'post_type'      => 'mmm_recipe',
			'posts_per_page' => - 1,
			'post_status'    => 'publish',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'tax_query'      => array(
				array(
					'taxonomy' => 'mmm_recipe_category',
					'field'    => 'term_id',
					'terms'    => $term_id,
				),
			),
		);

		$args = wp_parse_args( $query_args, $defaults );

		return get_posts( $args );
	}

	/**
	 * Liefert Rezepte ohne zugeordnete Term (ohne Kategorie).
	 *
	 * @param   array $query_args  Zusätzliche Query-Argumente.
	 *
	 * @return WP_Post[]
	 */
	/** @return WP_Post[] */
	public static function get_recipes_without_category( array $query_args = array() ): array {
		$defaults = array(
			'post_type'      => 'mmm_recipe',
			'posts_per_page' => - 1,
			'post_status'    => 'publish',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'tax_query'      => array(
				array(
					'taxonomy' => 'mmm_recipe_category',
					'operator' => 'NOT EXISTS',
				),
			),
		);

		$args = wp_parse_args( $query_args, $defaults );

		return get_posts( $args );
	}

	public function set_steps( array $steps ): bool {

		if ( ! $this->is_valid() ) {
			return false;
		}

		$cleaned = array();

		foreach ( $steps as $step ) {

			$title = trim( $step['title'] ?? '' );
			$desc  = trim( $step['description'] ?? '' );
			$image = intval( $step['image'] ?? 0 );

			// Leere Schritte überspringen
			if ( empty( $title ) && empty( $desc )
			     && empty( $step['ingredients'] ) ) {
				continue;
			}

			$new_step = array(
				'title'       => $title,
				'description' => $desc,
				'image'       => $image,
				'ingredients' => array(),
			);

			// Zutaten übernehmen
			if ( ! empty( $step['ingredients'] )
			     && is_array( $step['ingredients'] ) ) {

				foreach ( $step['ingredients'] as $ing ) {

					$ing_id    = intval( $ing['id'] ?? 0 );
					$amount    = floatval( $ing['amount'] ?? 0 );
					$amount_id = intval( $ing['amount_id'] ?? 0 );

					// Nur Zutaten mit gültiger ID übernehmen
					if ( $ing_id <= 0 ) {
						continue;
					}

					$new_step['ingredients'][] = array(
						'id'        => $ing_id,
						'amount'    => $amount,
						'amount_id' => $amount_id,
					);
				}
			}

			$cleaned[] = $new_step;
		}

		update_post_meta( $this->post_id, '_rmm_steps', $cleaned );

		return true;
	}

	public function purge_steps(): bool {

		if ( ! $this->is_valid() ) {
			return false;
		}

		delete_post_meta( $this->post_id, '_rmm_steps' );

		return true;
	}

	/**
	 * Calculate nutrition values per serving for this recipe.
	 *
	 * @return array Nutrition values per serving (keys: calories, fat, carbs, protein, sugar, fiber, salt)
	 */
	public function calculate_nutrition_per_serving(): array {

		if ( ! $this->is_valid() ) {
			return [];
		}

		[ $totals, $total_weight ] = $this->_calculate_total_nutrition();

		$servings = max(1.0, floatval($this->get_servings()));

		foreach ($totals as $key => $val) {
			$totals[$key] = $val / $servings;
		}

		$totals['weight_per_serving'] = $total_weight / $servings;

		return $totals;
	}

	private function _calculate_total_nutrition(float $scale = 1.0): array {

		$ingredients = $this->get_ingredients_scaled($scale);

		$totals = array_fill_keys(
			['calories','fat','carbs','protein','sugar','fiber','salt'],
			0.0
		);

		$total_weight = 0.0;

		foreach ($ingredients as $ing) {

			$ingredient_obj = new Mixmymeals_Ingredient($ing['id']);
			if (!$ingredient_obj->is_valid()) continue;

			$nutrition = $ingredient_obj->get_nutrition();
			$weight = floatval($ing['weight_g']);

			if ($weight <= 0) continue;

			$total_weight += $weight;

			$totals['calories'] += $nutrition['calories_100'] * $weight / 100;
			$totals['fat']      += $nutrition['fat'] * $weight / 100;
			$totals['carbs']    += $nutrition['carbs'] * $weight / 100;
			$totals['protein']  += $nutrition['protein'] * $weight / 100;
			$totals['sugar']    += $nutrition['sugar'] * $weight / 100;
			$totals['fiber']    += $nutrition['fiber'] * $weight / 100;
			$totals['salt']     += $nutrition['salt'] * $weight / 100;
		}

		return [$totals, $total_weight];
	}

	/** @return array<int, array<string, mixed>> */
	public function get_steps(): array {

		if ( ! $this->is_valid() ) {
			return [];
		}

		$steps = get_post_meta( $this->post_id, '_rmm_steps', true );

		return is_array( $steps ) ? $steps : array();
	}

	public function get_servings(): float {

		if ( ! $this->is_valid() ) {
			return 1;
		}

		$servings = get_post_meta( $this->post_id, '_mmm_servings', true );

		return $servings !== '' ? floatval( $servings ) : 1;
	}

	/**
	 * Calculate nutrition values per 100 g for this recipe.
	 *
	 * @return array Nutrition values per 100 g (keys: calories, fat, carbs, protein, sugar, fiber, salt)
	 */
	public function calculate_nutrition_per_100g(): array {

		if ( ! $this->is_valid() ) {
			return [];
		}

		[ $totals, $total_weight ] = $this->_calculate_total_nutrition();

		if ($total_weight > 0) {
			foreach ($totals as $key => $val) {
				$totals[$key] = ($val / $total_weight) * 100;
			}
		}

		return $totals;
	}

	public function calculate_cost_per_serving(): float {
		if ( ! $this->is_valid() ) {
			return 0.0;
		}

		$servings    = $this->get_servings();
		$scale       = $servings > 0 ? 1.0 / $servings : 1.0;
		$ingredients = $this->get_ingredients_scaled( $scale );
		$total_cost  = 0.0;

		foreach ( $ingredients as $ing ) {
			$ingredient_obj = new Mixmymeals_Ingredient( $ing['id'] );
			if ( ! $ingredient_obj->is_valid() ) {
				continue;
			}

			$total_cost += $ingredient_obj->calculate_cost_for_weight( $ing['weight_g'] );
		}

		return $total_cost;
	}

	public function set_servings( float $servings ): bool {
		if ( ! $this->is_valid() ) {
			return false;
		}
		$servings = max( 1, abs( $servings ) ); // Mindestens 1 Portion
		update_post_meta( $this->post_id, '_mmm_servings', $servings );

		return true;
	}

	/**
	 * Get a scaled and normalized list of ingredients for this recipe.
	 * (To substitute get_ingredients_normalized).
	 *
	 * This method applies a scaling factor to all ingredient amounts and
	 * returns a unified structure that can be used for display, nutrition
	 * calculation, and shopping lists.
	 *
	 * Special handling:
	 * - If an ingredient unit is marked as indivisible, the scaled amount
	 *   will be rounded to the nearest whole number (minimum 1).
	 * - The resulting weight (in grams) is adjusted accordingly to ensure
	 *   correct nutrition calculations.
	 *
	 * Each returned item contains:
	 * - id (int)            Ingredient post ID
	 * - name (string)       Ingredient name
	 * - amount (float)      Scaled amount (rounded if indivisible)
	 * - unit (string)       Unit label (e.g. "g", "pcs", "can")
	 * - weight_g (float)    Total weight in grams after scaling
	 * - amount_id (int)     Unit/amount post ID (if applicable)
	 *
	 * @param float $scale Scaling factor (e.g. 0.5 for half, 2 for double).
	 *
	 * @return array<int, array{
	 *     id: int,
	 *     name: string,
	 *     amount: float,
	 *     unit: string,
	 *     weight_g: float,
	 *     amount_id: int
	 * }>
	 */
	public function get_ingredients_normalized(): array {

		if ( ! $this->is_valid() ) {
			return [];
		}

		$steps       = $this->get_steps();
		$ingredients = array();

		foreach ( $steps as $step ) {
			foreach ( ( $step['ingredients'] ?? [] ) as $ing ) {

				$id        = intval( $ing['id'] ?? 0 );
				$amount    = floatval( $ing['amount'] ?? 0 );
				$amount_id = intval( $ing['amount_id'] ?? 0 );

				if ( $id <= 0 || $amount <= 0 ) {
					continue;
				}

				// Validierung
				$ingredient_obj = new Mixmymeals_Ingredient( $id );
				if ( ! $ingredient_obj->is_valid() ) {
					continue;
				}

				$unit_title      = '';
				$weight_per_unit = 0.0;

				// Einheit existiert → nutze sie
				if ( $amount_id > 0 ) {
					$unit_obj        = new Mixmymeals_Ingredient_Amount( $amount_id );
					$unit_title      = $unit_obj->get_title();
					$weight_per_unit = floatval( $unit_obj->get_weight() );
				}

				// Gewicht berechnen
				if ( $weight_per_unit > 0 ) {
					$total_weight = $amount * $weight_per_unit;
				} else {
					// Keine Einheit → amount ist vermutlich selbst in Gramm
					$unit_title   = 'g';
					$total_weight = $amount;
				}

				$ingredients[] = array(
					'id'       => $id,
					'name'     => get_the_title( $id ), // bewusst WP-Funktion
					'amount'   => round( $amount, 1 ),
					'unit'     => $unit_title,
					'weight_g' => round( $total_weight ),
				);
			}
		}

		return $ingredients;
	}

	/*
	 *
	 */
	public function get_ingredients_scaled( float $scale ): array {

		if ( ! $this->is_valid() ) {
			return [];
		}

		$steps       = $this->get_steps();
		$ingredients = array();

		foreach ( $steps as $step_index => $step) {
			foreach ( ( $step['ingredients'] ?? [] ) as $ing ) {

				$id        = intval( $ing['id'] ?? 0 );
				$amount    = floatval( $ing['amount'] ?? 0 );
				$amount_id = intval( $ing['amount_id'] ?? 0 );

				if ( $id <= 0 || $amount <= 0 ) {
					continue;
				}

				$ingredient_obj = new Mixmymeals_Ingredient( $id );
				if ( ! $ingredient_obj->is_valid() ) {
					continue;
				}

				// Skalieren
				$amount *= $scale;
				$raw_amount = $amount; // Bedarf vor Unteilbarkeits-Rundung

				$unit_title      = '';
				$weight_per_unit = 0.0;
				$is_indivisible  = false;

				if ( $amount_id > 0 ) {
					$unit_obj        = new Mixmymeals_Ingredient_Amount( $amount_id );
					$unit_title      = $unit_obj->get_title();
					$weight_per_unit = floatval( $unit_obj->get_weight() );
					$is_indivisible  = $unit_obj->is_indivisible();
				}

				// 💥 HIER: Unteilbarkeit anwenden
				if ( $is_indivisible ) {
					$amount = max( 1, round( $amount ) );
				}

				// Gewicht berechnen
				if ( $weight_per_unit > 0 ) {
					$total_weight = $amount * $weight_per_unit;
				} else {
					$unit_title   = 'g';
					$total_weight = $amount;
				}

				$ingredients[] = array(
					'id'              => $id,
					'name'            => get_the_title( $id ),
					'amount'          => $amount,
					'raw_amount'      => $raw_amount,
					'unit'            => $unit_title,
					'weight_g'        => $total_weight,
					'weight_per_unit' => $weight_per_unit,
					'is_indivisible'  => $is_indivisible,
					'amount_id'       => $amount_id,
					'step'            => $step_index,
				);
			}
		}

		return $ingredients;
	}

	/**
	 * Liefert alle Kategorien (Terms) für dieses Rezept.
	 *
	 * @param   array $args  Zusätzliche Argumente für get_the_terms (optional).
	 *
	 * @return WP_Term[] Array von Term-Objekten oder leeres Array.
	 */
	/** @return WP_Term[] */
	public function get_categories( array $args = array() ): array {

		if ( ! $this->is_valid() ) {
			return [];
		}

		$terms = get_the_terms( $this->post_id, 'mmm_recipe_category' );


		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		// Optional: sortieren nach Name, falls gewünscht
		if ( ! empty( $args['orderby'] ) && $args['orderby'] === 'name' ) {
			usort(
				$terms,
				function ( $a, $b ) {
					return strcasecmp( $a->name, $b->name );
				}
			);
		}

		return $terms;
	}

	/** @return int[] */
	public function get_equipment(): array {

		if ( ! $this->is_valid() ) {
			return [];
		}

		$ids = get_post_meta( $this->post_id, '_mmm_equipment', true );
		return is_array( $ids ) ? $ids : [];
	}

	/** @param int[] $ids */
	public function set_equipment( array $ids ): bool {

		if ( ! $this->is_valid() ) {
			return false;
		}

		$clean = array_filter( array_map( 'intval', $ids ), fn( $id ) => $id > 0 );

		update_post_meta( $this->post_id, '_mmm_equipment', $clean );

		return true;
	}

	public function set_preparation_time( $minutes ): void {

		if ( ! $this->is_valid() ) {
			return;
		}

		$minutes = max( 0, absint( $minutes ) );
		update_post_meta( $this->post_id, '_mmm_preparation_time', $minutes );
	}

	public function get_preparation_time(): int {

		if ( ! $this->is_valid() ) {
			return 0;
		}

		$time = get_post_meta( $this->post_id, '_mmm_preparation_time', true );
		return $time !== '' ? max( 0, intval( $time ) ) : 0;
	}

	public function get_priority(): int {
		if ( ! $this->is_valid() ) {
			return 0;
		}
		return (int) get_post_meta( $this->post_id, '_mmm_priority', true );
	}

	public function set_priority( int $priority ): void {
		if ( ! $this->is_valid() ) {
			return;
		}
		update_post_meta( $this->post_id, '_mmm_priority', max( 0, $priority ) );
	}

	/**
	 * Derives the diet type of the recipe from its ingredients.
	 *
	 * Returns 'vegan' if all ingredients are vegan, 'vegetarian' if all are
	 * vegan or vegetarian, and '' if any ingredient has no diet restriction
	 * or the recipe has no ingredients.
	 *
	 * @return string 'vegan', 'vegetarian', or ''.
	 */
	public function get_diet_type(): string {

		if ( ! $this->is_valid() ) {
			return '';
		}

		$ingredients = $this->get_ingredients_normalized();

		if ( empty( $ingredients ) ) {
			return '';
		}

		$all_vegan      = true;
		$all_vegetarian = true;

		foreach ( $ingredients as $ing ) {
			$ingredient = new Mixmymeals_Ingredient( intval( $ing['id'] ) );
			$diet       = $ingredient->get_diet_type();

			if ( $diet !== 'vegan' ) {
				$all_vegan = false;
			}
			if ( $diet !== 'vegan' && $diet !== 'vegetarian' ) {
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
