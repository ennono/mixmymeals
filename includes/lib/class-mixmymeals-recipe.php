<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Mixmymeals_Recipe {

	private $post_id;

	public function __construct( $post_id ) {
		$this->post_id = $post_id;
	}

	/* ---------------------------------------------------------- */
	/* STEPS HANDLING                                             */
	/* ---------------------------------------------------------- */

	public function get_steps() {
		$steps = get_post_meta( $this->post_id, '_rmm_steps', true );

		return is_array( $steps ) ? $steps : [];
	}

	public function set_steps( $steps ) {
		if ( ! is_array( $steps ) ) {
			return;
		}

		$cleaned = [];

		foreach ( $steps as $step ) {
			$title = trim( $step['title'] ?? '' );
			$desc  = trim( $step['description'] ?? '' );
			$image = intval( $step['image'] ?? 0 );

			// Leere Schritte überspringen
			if ( empty( $title ) && empty( $desc ) && empty( $step['ingredients'] ) ) {
				continue;
			}

			$new_step = [
				'title'       => $title,
				'description' => $desc,
				'image'       => $image,
				'ingredients' => [],
			];

			// Zutaten übernehmen
			if ( ! empty( $step['ingredients'] ) && is_array( $step['ingredients'] ) ) {
				foreach ( $step['ingredients'] as $ing ) {
					$ing_id     = intval( $ing['id'] ?? 0 );
					$amount     = floatval( $ing['amount'] ?? 0 );
					$amount_id  = intval( $ing['amount_id'] ?? 0 );

					// Nur Zutaten mit gültiger ID übernehmen
					if ( $ing_id <= 0 ) {
						continue;
					}

					$new_step['ingredients'][] = [
						'id'        => $ing_id,
						'amount'    => $amount,
						'amount_id' => $amount_id, // ✅ Einheit speichern
					];
				}
			}

			$cleaned[] = $new_step;
		}

		update_post_meta( $this->post_id, '_rmm_steps', $cleaned );
	}


	public function purge_steps() {
		delete_post_meta( $this->post_id, '_rmm_steps' );
	}

	/**
	 * Get all published recipes (cached).
	 *
	 * @return WP_Post[] Array of recipe posts.
	 */
	public static function get_all_recipes() {
		static $cache = null;

		if ( $cache === null ) {
			$cache = get_posts( array(
				'post_type'      => 'mixmymeals_recipe',
				'posts_per_page' => - 1,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			) );
		}

		return $cache;
	}

	/**
	 * Calculate nutrition values per serving for this recipe.
	 *
	 * @return array Nutrition values per serving (keys: calories, fat, carbs, protein, sugar, fiber, salt)
	 */
	public function calculate_nutrition_per_serving(): array {
		[$totals, $total_weight] = $this->_calculate_total_nutrition();

		$base_servings = max(1.0, floatval($this->get_servings()));

		foreach ($totals as $key => $val) {
			$totals[$key] = round($val / $base_servings, 2);
		}

		return $totals;
	}

	/**
	 * Calculate nutrition values per 100 g for this recipe.
	 *
	 * @return array Nutrition values per 100 g (keys: calories, fat, carbs, protein, sugar, fiber, salt)
	 */
	public function calculate_nutrition_per_100g(): array {
		[$totals, $total_weight] = $this->_calculate_total_nutrition();

		if ($total_weight > 0) {
			foreach ($totals as $key => $val) {
				$totals[$key] = round($val / $total_weight * 100, 2);
			}
		}

		return $totals;
	}

	/**
	 * Helper: Calculate total nutrition and total weight for all ingredients.
	 *
	 * @return array [totals, total_weight]
	 */
	private function _calculate_total_nutrition(): array {

		$steps = $this->get_steps();
		$totals = array_fill_keys(['calories','fat','carbs','protein','sugar','fiber','salt'], 0.0);
		$total_weight = 0.0;

		foreach ($steps as $step) {
			foreach ($step['ingredients'] ?? [] as $ing) {
				$ingredient_id = intval($ing['id'] ?? 0);
				$amount = floatval($ing['amount'] ?? 0);
				$amount_id = intval($ing['amount_id'] ?? 0);

				if ($ingredient_id <= 0 || $amount <= 0) {
					continue;
				}

				$ing_obj = new Mixmymeals_Ingredient($ingredient_id);
				$nutrition = $ing_obj->get_nutrition();

				// Einheit in g bestimmen
				$weight_per_unit = 0.0;
				if ($amount_id > 0) {
					$unit_obj = new Mixmymeals_Ingredient_Amount($amount_id);
					$weight_per_unit = floatval($unit_obj->get_weight());
				}

				$ingredient_weight = $weight_per_unit > 0 ? $amount * $weight_per_unit : $amount;

				if ($ingredient_weight <= 0) {
					continue;
				}

				$total_weight += $ingredient_weight;

				// Summiere Nährwerte (jeweils pro 100 g)
				$totals['calories'] += floatval($nutrition['calories_100'] ?? 0) * $ingredient_weight / 100.0;
				$totals['fat']      += floatval($nutrition['fat'] ?? 0) * $ingredient_weight / 100.0;
				$totals['carbs']    += floatval($nutrition['carbs'] ?? 0) * $ingredient_weight / 100.0;
				$totals['protein']  += floatval($nutrition['protein'] ?? 0) * $ingredient_weight / 100.0;
				$totals['sugar']    += floatval($nutrition['sugar'] ?? 0) * $ingredient_weight / 100.0;
				$totals['fiber']    += floatval($nutrition['fiber'] ?? 0) * $ingredient_weight / 100.0;
				$totals['salt']     += floatval($nutrition['salt'] ?? 0) * $ingredient_weight / 100.0;
			}
		}

		return [$totals, $total_weight];
	}



	/* ---------------------------------------------------------- */
	/* SERVINGS HANDLING                                          */
	/* ---------------------------------------------------------- */

	public function get_servings() {
		$servings = get_post_meta( $this->post_id, '_mmm_servings', true );
		return $servings !== '' ? floatval( $servings ) : 1;
	}

	public function set_servings( $servings ) {
		$servings = max( 1, abs( $servings ) ); // Mindestens 1 Portion
		update_post_meta( $this->post_id, '_mmm_servings', $servings );
	}

	/**
	 * Get normalized ingredient list (per serving) for this recipe.
	 *
	 * @return array Each item: [id, name, weight_g, amount, unit]
	 */
	public function get_ingredients_normalized(): array {

		$steps = $this->get_steps();
		$servings = max(1, floatval($this->get_servings()));
		$ingredients = [];

		foreach ($steps as $step) {
			foreach (($step['ingredients'] ?? []) as $ing) {
				$id = intval($ing['id'] ?? 0);
				$amount = floatval($ing['amount'] ?? 0);
				$amount_id = intval($ing['amount_id'] ?? 0);
				if ($id <= 0 || $amount <= 0) continue;

				$weight_per_unit = 0.0;
				$unit_title = 'g';

				if ($amount_id > 0) {
					$unit_obj = new Mixmymeals_Ingredient_Amount($amount_id);
					$weight_per_unit = floatval($unit_obj->get_weight());
					$unit_title = $unit_obj->get_title() ?: 'g';
				}

				$total_weight = $weight_per_unit > 0 ? $amount * $weight_per_unit : $amount;

				$ingredients[] = [
					'id'     => $id,
					'name'   => get_the_title($id),
					'amount' => $amount,
					'unit'   => $unit_title,
					'weight_g' => round($total_weight / $servings, 1),
				];
			}
		}

		return $ingredients;
	}



}