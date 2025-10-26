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

			// Leere Schritte ignorieren
			if ( empty( $title ) && empty( $desc )
			     && empty( $step['ingredients'] ) ) {
				continue;
			}

			$new_step = [
				'title'       => $title,
				'description' => $desc,
				'image'       => intval( $step['image'] ?? 0 ),
				'ingredients' => [],
			];

			// Zutaten übernehmen (mehrere!)
			if ( ! empty( $step['ingredients'] )
			     && is_array( $step['ingredients'] ) ) {
				foreach ( $step['ingredients'] as $ing ) {
					if ( empty( $ing['id'] ) ) {
						continue;
					}

					$new_step['ingredients'][] = [
						'id'     => intval( $ing['id'] ),
						'amount' => floatval( $ing['amount'] ?? 0 ),
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

	/*
	public function calculate_nutrition_totals( $servings = 1 ) {

		write_to_log( 'Läuft!' );

		$steps = $this->get_steps();

		$totals = array_fill_keys( ['calories','fat','carbs','protein','sugar','fiber','salt'], 0 );

		foreach ( $steps as $step ) {
			foreach ( $step['ingredients'] ?? [] as $ing ) {
				if ( empty( $ing['id'] ) || empty( $ing['amount'] ) ) continue;

				$ing_obj = new Mixmymeals_Ingredient( $ing['id'] );
				$nutrition = $ing_obj->get_nutrition();
				$weight_item  = floatval( $nutrition['weight_item'] ?? 0 );
				$factor = floatval( $ing['amount'] ) * $servings * $weight_item / 100;

				$totals['calories'] += $factor * floatval( $nutrition['calories_100'] ?? 0 );
				$totals['fat']      += $factor * floatval( $nutrition['fat'] ?? 0 );
				$totals['carbs']    += $factor * floatval( $nutrition['carbs'] ?? 0 );
				$totals['protein']  += $factor * floatval( $nutrition['protein'] ?? 0 );
				$totals['sugar']    += $factor * floatval( $nutrition['sugar'] ?? 0 );
				$totals['fiber']    += $factor * floatval( $nutrition['fiber'] ?? 0 );
				$totals['salt']     += $factor * floatval( $nutrition['salt'] ?? 0 );
			}
		}

		return $totals;
	}
	*/
	public function calculate_nutrition_totals( $servings = 1 ): array {

		write_to_log( 'läuft.' );
		$steps = $this->get_steps();
		$totals = array_fill_keys( ['calories','fat','carbs','protein','sugar','fiber','salt'], 0 );

		foreach ( $steps as $step ) {
			foreach ( $step['ingredients'] ?? [] as $ing ) {
				if ( empty( $ing['id'] ) || empty( $ing['amount'] ) ) continue;

				$ing_obj = new Mixmymeals_Ingredient( $ing['id'] );
				$nutrition = $ing_obj->get_nutrition();
				$weight_item  = floatval( $nutrition['weight_item'] ?? 0 );
				$factor = floatval( $ing['amount'] ) * $servings * $weight_item / 100;

				foreach ( $totals as $key => $val ) {
					if ( isset( $nutrition[$key] ) )
						$totals[$key] += $factor * floatval( $nutrition[$key] );
				}

				// kcal-Sonderfall
				$totals['calories'] += $factor * floatval( $nutrition['calories_100'] ?? 0 );
			}
		}

		return $totals;
	}


}