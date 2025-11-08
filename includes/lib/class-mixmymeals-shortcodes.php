<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Mixmymeals_Shortcodes {

	public function __construct() {

        // Single Meal Plan:

		add_shortcode( 'mixmymeals_all_meals_in_a_single_meal_plan',
			[ $this, 'all_meals_in_a_plan_callback' ] );

		add_shortcode( 'mixmymeals_shopping_list_for_a_single_meal_plan',
			[ $this, 'shopping_list_for_a_single_meal_plan_callback' ] );

		add_shortcode( 'mixmymeals_simple_shopping_list_for_a_single_meal_plan',
			[ $this, 'simple_shopping_list_for_a_single_meal_plan_callback' ] );

        // Single Recipe:

		add_shortcode( 'mixmymeals_steps_for_a_single_recipe',
			[ $this, 'recipe_steps_callback' ] );

		add_shortcode( 'mixmymeals_meal_nutrition_table_for_a_single_recipe',
			[ $this, 'recipe_nutrition_table_for_a_single_recipe_callback' ] );

        // Single Meal:

		add_shortcode( 'mixmymeals_scaled_steps_for_a_single_meal',
			[ $this, 'scaled_recipe_steps_callback' ] );

		add_shortcode( 'mixmymeals_meal_nutrition_table_for_a_single_meal',
			[ $this, 'meal_nutrition_table_for_a_single_meal_callback' ] );

        // Other:

		add_shortcode( 'mixmymeals_all_meal_plans',
			[ $this, 'all_meal_plans_callback' ] );

	}

	/**
	 * Gibt alle Meals eines Meal Plans tabellarisch nach Datum aus.
	 */
	public function all_meals_in_a_plan_callback( $atts = [] ) {

		// 🔹 Shortcode-Attribute definieren
		$atts = shortcode_atts( [
			'id' => 0, // optional: spezifische Meal Plan ID oder "current"
		], $atts, 'mixmymeals_all_meals_in_a_plan' );

		// 🔹 ID behandeln
		$id_attr = strtolower( trim( $atts['id'] ) );

		if ( $id_attr === 'current' ) {
			// → Aktuell gültigen oder nächsten Plan ermitteln
			$meal_plan_id = Mixmymeals_Meal_Plan::get_current_plan_id();
			if ( ! $meal_plan_id ) {
				return '<p>' . esc_html__( 'No current or upcoming meal plan found.', 'mixmymeals' ) . '</p>';
			}
		} else {
			// → Feste ID oder aktuelle Seite
			$meal_plan_id = intval( $atts['id'] ) ?: get_the_ID();
		}

        write_to_log( $meal_plan_id );

		// 🔹 Prüfung: gültiger Meal Plan?
		if ( ! $meal_plan_id || get_post_type( $meal_plan_id ) !== 'mmm_meal_plan' ) {
			return '<p>' . esc_html__( 'No valid meal plan found.', 'mixmymeals' ) . '</p>';
		}

		$meal_plan  = new Mixmymeals_Meal_Plan( $meal_plan_id );
		$start_date = $meal_plan->get_start_date();
		$end_date   = $meal_plan->get_end_date();

		if ( ! $start_date || ! $end_date ) {
			return '<p>' . esc_html__( 'Start or End date not set.', 'mixmymeals' ) . '</p>';
		}

		// 🔹 Meals des Plans laden
		$meals = Mixmymeals_Meal::get_meals_for_plan( $meal_plan_id );
		if ( empty( $meals ) ) {
			return '<p>' . esc_html__( 'No meals found in this plan.', 'mixmymeals' ) . '</p>';
		}

		// 🔹 Tage zwischen Start und Ende erzeugen
		$period = new DatePeriod(
			new DateTime( $start_date ),
			new DateInterval( 'P1D' ),
			( new DateTime( $end_date ) )->modify( '+1 day' ) // Enddatum inklusiv
		);

		$days = [];
		foreach ( $period as $day ) {
			$days[ $day->format( 'Y-m-d' ) ] = ucfirst( $day->format( 'l' ) );
		}

		// 🔹 Meals nach Datum gruppieren
		$grouped_meals = [];
		foreach ( $meals as $meal_post ) {
			$meal      = new Mixmymeals_Meal( $meal_post->ID );
			$meal_date = $meal->get_date();

			if ( ! empty( $meal_date ) && isset( $days[ $meal_date ] ) ) {
				$grouped_meals[ $meal_date ][] = $meal;
			}
		}

		ob_start(); ?>
        <div class="mmm-meal-plan-wrapper" data-meal-plan-id="<?php echo esc_attr( $meal_plan_id ); ?>">
            <table class="mmm-meal-plan-table">
                <thead>
                <tr>
					<?php foreach ( $days as $date => $label ) : ?>
                        <th>
							<?php echo esc_html( $label ); ?><br>
                            <small><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $date ) ) ); ?></small>
                        </th>
					<?php endforeach; ?>
                </tr>
                </thead>
                <tbody>
                <tr>
					<?php foreach ( $days as $date => $label ) : ?>
                        <td class="mmm-meal-day">
							<?php if ( ! empty( $grouped_meals[ $date ] ) ) : ?>
								<?php foreach ( $grouped_meals[ $date ] as $meal ) :
									$recipe_id    = $meal->get_recipe_id();
									$recipe_title = get_the_title( $recipe_id );
									$meal_link    = get_permalink( $meal->get_id() );
									?>
                                    <div class="mmm-meal-item">
                                        <strong class="mmm-meal-title">
                                            <a href="<?php echo esc_url( $meal_link ); ?>">
												<?php echo esc_html( $recipe_title ); ?>
                                            </a>
                                        </strong>
										<?php if ( $meal->get_servings() ) : ?>
                                            <div class="mmm-meal-servings">
												<?php echo esc_html__( 'Servings:', 'mixmymeals' ) . ' ' . intval( $meal->get_servings() ); ?>
                                            </div>
										<?php endif; ?>
										<?php if ( $meal->get_note() ) : ?>
                                            <div class="mmm-meal-note"><?php echo esc_html( $meal->get_note() ); ?></div>
										<?php endif; ?>
                                    </div>
								<?php endforeach; ?>
							<?php else : ?>
                                <em><?php esc_html_e( 'No meals', 'mixmymeals' ); ?></em>
							<?php endif; ?>
                        </td>
					<?php endforeach; ?>
                </tr>
                </tbody>
            </table>
        </div>
		<?php

		return ob_get_clean();
	}



	/**
	 * Einkaufsliste als Matrix (Rezepte × Zutaten)
	 */
	public function shopping_list_for_a_single_meal_plan_callback( $atts ) {
		$meal_plan_id = get_the_ID();
		$meals = Mixmymeals_Meal::get_meals_for_plan( $meal_plan_id );

		if ( empty( $meals ) ) {
			return '<p>' . esc_html__( 'No meals found in this plan.', 'mixmymeals' ) . '</p>';
		}

		$ingredient_matrix = [];
		$meals_for_display = [];

		// Alle Mahlzeiten durchgehen
		foreach ( $meals as $meal_post ) {
			$meal = new Mixmymeals_Meal( $meal_post->ID );
			$recipe_id = $meal->get_recipe_id();
			if ( ! $recipe_id ) continue;

			$meal_id = $meal_post->ID;
			$meals_for_display[$meal_id] = [
				'recipe_id' => $recipe_id,
				'title'     => get_the_title($recipe_id),
				'servings'  => floatval($meal->get_servings()),
			];

			$recipe = new Mixmymeals_Recipe($recipe_id);
			$ingredients = $recipe->get_ingredients_normalized();

			$recipe_servings = max(1, floatval($recipe->get_servings()));
			$meal_servings   = max(0.01, floatval($meal->get_servings()));
			$scale = $meal_servings / $recipe_servings;

			foreach ( $ingredients as $ing ) {
				$ing_id = intval($ing['id'] ?? 0);
				if ( ! $ing_id ) continue;

				$amount = floatval($ing['amount'] ?? 0) * $scale;
				$unit_title = $ing['unit'] ?? 'g';
				$unit_weight = floatval($ing['weight_per_unit'] ?? ($ing['weight_g'] / max(1, $ing['amount'])));

				if ( ! isset( $ingredient_matrix[$ing_id] ) ) {
					$ingredient_matrix[$ing_id] = [
						'total_weight' => 0,
						'unit_title'   => $unit_title,
						'unit_weight'  => $unit_weight,
						'total_units'  => 0,
					];
				}

				if ( ! isset( $ingredient_matrix[$ing_id][$meal_id] ) ) {
					$ingredient_matrix[$ing_id][$meal_id] = [
						'amount'      => 0,
						'unit_title'  => $unit_title,
						'unit_weight' => $unit_weight,
					];
				}

				$ingredient_matrix[$ing_id][$meal_id]['amount'] += $amount;
				$ingredient_matrix[$ing_id]['total_weight'] += $amount * $unit_weight;
				$ingredient_matrix[$ing_id]['total_units']  += $amount;
			}
		}

		if ( empty( $ingredient_matrix ) ) {
			return '<p>' . esc_html__( 'No ingredients found.', 'mixmymeals' ) . '</p>';
		}

		ob_start(); ?>
        <div class="mmm-shopping-list-grid">

            <div class="mmm-shopping-meals">
				<?php foreach ( $meals_for_display as $meal_id => $meal_data ) : ?>
                    <div class="mmm-shopping-meal-card">
                        <h4>
                            <a href="<?php echo esc_url( get_permalink( $meal_id ) ); ?>">
								<?php echo esc_html( $meal_data['title'] ); ?>
                            </a>
							<?php if ( $meal_data['servings'] ) : ?>
                                <small>(<?php echo round($meal_data['servings'], 2); ?> <?php esc_html_e('Servings', 'mixmymeals'); ?>)</small>
							<?php endif; ?>
                        </h4>

                        <ul>
							<?php foreach ( $ingredient_matrix as $ing_id => $data ) :
								$entry = $data[$meal_id] ?? null;
								if ( ! $entry || $entry['amount'] <= 0 ) continue;

								$amount = round( $entry['amount'], 2 );
								$unit_t = $entry['unit_title'];
								$unit_w = $entry['unit_weight'];
								if ( strtolower( $unit_t ) === 'gramm' || strtolower( $unit_t ) === 'g' ) {
									$display = round( $amount * $unit_w, 2 ) . ' g';
								} else {
									$display = $amount . ' × ' . $unit_t;
								}
								?>
                                <li>
                                    <a href="<?php echo esc_url( get_permalink( $ing_id ) ); ?>">
										<?php echo esc_html( get_the_title( $ing_id ) ); ?>
                                    </a>: <?php echo esc_html( $display ); ?>
                                </li>
							<?php endforeach; ?>
                        </ul>
                    </div>
				<?php endforeach; ?>
            </div>
        </div>

        <style>
        </style>
		<?php
		return ob_get_clean();
	}

	/*
     * Prints all steps of a recipe (linked ingredients etc.) — shows the entered amount + weight per unit.
     */
	public function recipe_steps_callback( $atts ) {
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return '<p>' . esc_html__( 'No recipe found.', 'mixmymeals' ) . '</p>';
		}

		$recipe = new Mixmymeals_Recipe( $post_id );
		$steps  = $recipe->get_steps();

		if ( empty( $steps ) ) {
			return '<p>' . esc_html__( 'No steps defined for this recipe.', 'mixmymeals' ) . '</p>';
		}

		ob_start();
		?>
        <div class="mmm-recipe-steps">
			<?php foreach ( $steps as $index => $step ) : ?>
                <div class="mmm-step">
                    <div class="mmm-step-header">
						<?php if ( ! empty( $step['title'] ) ) : ?>
                            <h3 class="mmm-step-title"><?php echo esc_html( sprintf( __( 'Step %d', 'mixmymeals' ), $index + 1 ) ) . ': ' . esc_html( $step['title'] ); ?></h3>
						<?php endif; ?>
                    </div>

					<?php if ( ! empty( $step['image'] ) ) : ?>
                        <div class="mmm-step-image">
							<?php echo wp_get_attachment_image( $step['image'], 'large', false, [ 'class' => 'mmm-step-img' ] ); ?>
                        </div>
					<?php endif; ?>

					<?php if ( ! empty( $step['description'] ) ) : ?>
                        <div class="mmm-step-description">
							<?php echo wpautop( esc_html( $step['description'] ) ); ?>
                        </div>
					<?php endif; ?>

					<?php if ( ! empty( $step['ingredients'] ) ) : ?>
                        <ul class="mmm-step-ingredients">
							<?php foreach ( $step['ingredients'] as $ing ) :
								if ( empty( $ing['id'] ) ) continue;

								$amount = floatval( $ing['amount'] ?? 0 );
								$ingredient_obj = new Mixmymeals_Ingredient( $ing['id'] );
								$nutrition = $ingredient_obj->get_nutrition();
								$weight_item = floatval( $nutrition['weight_item'] ?? 0 );

								// Anzeigeformatierung
								$amount_display = ( $amount > 0 )
									? number_format_i18n( $amount, ( fmod( $amount, 1 ) !== 0.0 ? 2 : 0 ) )
									: '';

								$weight_display = $weight_item > 0 ? sprintf( __( '%s g', 'mixmymeals' ), number_format_i18n( $weight_item, 0 ) ) : '';

								// Kombinierte Ausgabe (z. B. "2 × 500 g" oder nur "1")
								$amount_text = trim( $amount_display . ( $weight_display ? ' × ' . $weight_display : '' ) );
								?>
                                <li>
                                    <a class="mmm-ingredient-name" href="<?php echo esc_url( get_permalink( $ing['id'] ) ); ?>">
										<?php echo esc_html( get_the_title( $ing['id'] ) ); ?>
                                    </a>
									<?php if ( $amount_text ) : ?>
                                        <span class="mmm-ingredient-amount">
										(<?php echo esc_html( $amount_text ); ?>)
									</span>
									<?php endif; ?>
                                </li>
							<?php endforeach; ?>
                        </ul>
					<?php endif; ?>
                </div>
			<?php endforeach; ?>
        </div>
		<?php
		return ob_get_clean();
	}


	public function scaled_recipe_steps_callback( $atts ) {
		$post_id = get_the_ID();

		// Prüfen, ob es sich um ein Meal handelt
		if ( get_post_type( $post_id ) !== 'mmm_meal' ) {
			return '<p>' . esc_html__( 'This shortcode must be used inside a meal.', 'mixmymeals' ) . '</p>';
		}

		$meal = new Mixmymeals_Meal( $post_id );
		$recipe_id = $meal->get_recipe_id();

		if ( ! $recipe_id ) {
			return '<p>' . esc_html__( 'No recipe linked to this meal.', 'mixmymeals' ) . '</p>';
		}

		$recipe = new Mixmymeals_Recipe( $recipe_id );
		$steps  = $recipe->get_steps();

		if ( empty( $steps ) ) {
			return '<p>' . esc_html__( 'No steps defined for this recipe.', 'mixmymeals' ) . '</p>';
		}

		// Portionsskalierung (erlaubt < 1)
		$recipe_servings = floatval( $recipe->get_servings() );
		$meal_servings   = floatval( $meal->get_servings() );

		if ( $recipe_servings <= 0 ) $recipe_servings = 1.0;
		if ( $meal_servings <= 0 ) $meal_servings = 1.0;

		$scaling_factor = $meal_servings / $recipe_servings;

		ob_start();
		?>
        <div class="mmm-recipe-steps scaled">
			<?php foreach ( $steps as $index => $step ) : ?>
                <div class="mmm-step">
                    <div class="mmm-step-header">
						<?php if ( ! empty( $step['title'] ) ) : ?>
                            <h3 class="mmm-step-title">
								<?php echo esc_html( sprintf( __( 'Step %d', 'mixmymeals' ), $index + 1 ) . ': ' . $step['title'] ); ?>
                            </h3>
						<?php endif; ?>
                    </div>

					<?php if ( ! empty( $step['image'] ) ) : ?>
                        <div class="mmm-step-image">
							<?php echo wp_get_attachment_image( $step['image'], 'large', false, [ 'class' => 'mmm-step-img' ] ); ?>
                        </div>
					<?php endif; ?>

					<?php if ( ! empty( $step['description'] ) ) : ?>
                        <div class="mmm-step-description">
							<?php echo wpautop( esc_html( $step['description'] ) ); ?>
                        </div>
					<?php endif; ?>

					<?php if ( ! empty( $step['ingredients'] ) ) : ?>
                        <ul class="mmm-step-ingredients">
							<?php foreach ( $step['ingredients'] as $ing ) :
								if ( empty( $ing['id'] ) ) continue;

								// Ursprungliche Menge und skalierte Menge
								$original_amount = floatval( $ing['amount'] ?? 0 );
								$scaled_amount = $original_amount * $scaling_factor;

								// kleine Floating-Point-Abweichungen bereinigen
								$scaled_amount = ( abs( $scaled_amount - round( $scaled_amount, 3 ) ) < 0.0005 )
									? (float) round( $scaled_amount, 3 )
									: $scaled_amount;

								// Einheit / Gewicht pro Einheit ermitteln:
								$unit_title = '';
								$weight_per_unit = 0.0; // in g

								// Falls in Schritt eine amount_id gesetzt ist, ziehe Details davon
								$amount_id = ! empty( $ing['amount_id'] ) ? intval( $ing['amount_id'] ) : 0;
								if ( $amount_id ) {
									$amt_obj = new Mixmymeals_Ingredient_Amount( $amount_id );
									$unit_title = $amt_obj->get_title() ?: '';
									$weight_per_unit = floatval( $amt_obj->get_weight() ); // g pro Einheit
								}

								// Falls keine amount_id vorhanden: versuche weight_item aus Ingredient-Nutrition
								if ( ! $amount_id ) {
									$ingredient_obj = new Mixmymeals_Ingredient( $ing['id'] );
									$nutrition = $ingredient_obj->get_nutrition();
									// weight_item = Gewicht einer Einheit in g (z. B. 1 Knoblauchzehe = 5 g)
									$weight_per_unit = floatval( $nutrition['weight_item'] ?? 0 );
									// Wenn weight_item vorhanden, setzen wir unit_title auf eine generische "Stk."-Bezeichnung
									if ( $weight_per_unit > 0 && empty( $unit_title ) ) {
										$unit_title = __( 'unit', 'mixmymeals' ); // kannst du anpassen
									}
								}

								// Anzeigeformatierung: Dezimalstellen nur wenn nötig
								$decimals = ( floor( $scaled_amount ) != $scaled_amount ) ? 2 : 0;
								// Rundung für Anzeige
								$display_amount = $scaled_amount > 0 ? number_format_i18n( $scaled_amount, $decimals ) : '';

								// Gesamtgewicht berechnen (falls möglich)
								$total_weight = ( $weight_per_unit > 0 && $scaled_amount > 0 )
									? (float) round( $weight_per_unit * $scaled_amount, 2 )
									: 0.0;

								// Erzeuge lesbaren Unit-Teil:
								// Wenn wir eine benannte Einheit haben (z.B. "Zehe", "EL") zeigen wir "3.5 × Zehe (5 g)"
								// Wenn wir nur weight_per_unit kennen aber keine title, zeigen wir "3.5 × 5 g" (selten)
								$unit_part = '';
								if ( $unit_title ) {
									// Beautify: wenn unit_title numeric like 'g' etc. unlikely — we assume textual unit
									$unit_part = $unit_title;
									if ( $weight_per_unit > 0 ) {
										$unit_part .= ' = ' . number_format_i18n( $weight_per_unit, 0 ) . ' g';
									}
								} elseif ( $weight_per_unit > 0 ) {
									// keine unit title, aber weight known -> show weight per unit only
									$unit_part = number_format_i18n( $weight_per_unit, 0 ) . ' g';
								}

								?>
                                <li>
                                    <a class="mmm-ingredient-name" href="<?php echo esc_url( get_permalink( $ing['id'] ) ); ?>">
										<?php echo esc_html( get_the_title( $ing['id'] ) ); ?>
                                    </a>

									<?php if ( $display_amount ) : ?>
                                        <span class="mmm-ingredient-amount">
                                        (<?php
											// Zusammensetzen:
											if ( $unit_part ) {
												// z.B. "3.5 × Zehe = 5 g — 17.50 g total"
												echo esc_html( $display_amount . ' × ' . $unit_part );
												if ( $total_weight > 0 ) {
													echo ' — ' . esc_html( number_format_i18n( $total_weight, 2 ) . ' g' );
												}
											} else {
												// Nur Menge, z.B. "3.5"
												echo esc_html( $display_amount );
											}
											?>)
                                    </span>
									<?php endif; ?>
                                </li>
							<?php endforeach; ?>
                        </ul>
					<?php endif; ?>
                </div>
			<?php endforeach; ?>
        </div>
		<?php

		return ob_get_clean();
	}



	/**
	 * Shortcode: Gibt eine Nährwerttabelle für ein Meal aus (pro Portion).
	 */
	public function meal_nutrition_table_for_a_single_meal_callback( $atts ) {

        $post_id = get_the_ID();

		if ( ! $post_id || get_post_type( $post_id ) !== 'mmm_meal' ) {
			return '<p>' . esc_html__( 'This shortcode can only be used on meal posts.', 'mixmymeals' ) . '</p>';
		}

		$meal = new Mixmymeals_Meal( $post_id );
		$recipe_id = $meal->get_recipe_id();

		if ( ! $recipe_id ) {
			return '<p>' . esc_html__( 'No recipe assigned to this meal.', 'mixmymeals' ) . '</p>';
		}

		$recipe = new Mixmymeals_Recipe( $recipe_id );
		$steps  = $recipe->get_steps();

		if ( empty( $steps ) ) {
			return '<p>' . esc_html__( 'No steps or ingredients found for this recipe.', 'mixmymeals' ) . '</p>';
		}

		// Nährwerte berechnen
		$nutrition_per_serving = $recipe->calculate_nutrition_per_serving();
		$nutrition_per_100g    = $recipe->calculate_nutrition_per_100g();

		ob_start();
		?>
        <div class="mmm-meal-nutrition">
            <table class="mmm-nutrition-table">
                <thead>
                <tr>
                    <th><?php esc_html_e( 'Nutrition', 'mixmymeals' ); ?></th>
                    <th><?php esc_html_e( 'per serving', 'mixmymeals' ); ?></th>
                    <th><?php esc_html_e( 'per 100 g', 'mixmymeals' ); ?></th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td><?php esc_html_e( 'Calories', 'mixmymeals' ); ?></td>
                    <td><?php echo esc_html( round( $nutrition_per_serving['calories'], 1 ) ); ?> kcal</td>
                    <td><?php echo esc_html( round( $nutrition_per_100g['calories'], 1 ) ); ?> kcal</td>
                </tr>
                <tr>
                    <td><?php esc_html_e( 'Protein', 'mixmymeals' ); ?></td>
                    <td><?php echo esc_html( round( $nutrition_per_serving['protein'], 1 ) ); ?> g</td>
                    <td><?php echo esc_html( round( $nutrition_per_100g['protein'], 1 ) ); ?> g</td>
                </tr>
                <tr>
                    <td><?php esc_html_e( 'Carbohydrates', 'mixmymeals' ); ?></td>
                    <td><?php echo esc_html( round( $nutrition_per_serving['carbs'], 1 ) ); ?> g</td>
                    <td><?php echo esc_html( round( $nutrition_per_100g['carbs'], 1 ) ); ?> g</td>
                </tr>
                <tr>
                    <td><?php esc_html_e( 'Fat', 'mixmymeals' ); ?></td>
                    <td><?php echo esc_html( round( $nutrition_per_serving['fat'], 1 ) ); ?> g</td>
                    <td><?php echo esc_html( round( $nutrition_per_100g['fat'], 1 ) ); ?> g</td>
                </tr>
                <tr>
                    <td><?php esc_html_e( 'Sugar', 'mixmymeals' ); ?></td>
                    <td><?php echo esc_html( round( $nutrition_per_serving['sugar'], 1 ) ); ?> g</td>
                    <td><?php echo esc_html( round( $nutrition_per_100g['sugar'], 1 ) ); ?> g</td>
                </tr>
                <tr>
                    <td><?php esc_html_e( 'Fiber', 'mixmymeals' ); ?></td>
                    <td><?php echo esc_html( round( $nutrition_per_serving['fiber'], 1 ) ); ?> g</td>
                    <td><?php echo esc_html( round( $nutrition_per_100g['fiber'], 1 ) ); ?> g</td>
                </tr>
                <tr>
                    <td><?php esc_html_e( 'Salt', 'mixmymeals' ); ?></td>
                    <td><?php echo esc_html( round( $nutrition_per_serving['salt'], 2 ) ); ?> g</td>
                    <td><?php echo esc_html( round( $nutrition_per_100g['salt'], 2 ) ); ?> g</td>
                </tr>
                </tbody>
            </table>
        </div>
		<?php

        $html = ob_get_clean();

		write_to_log( $html );

		return $html;
	}



	/*
 * Simple shopping list: Ingredients + rounded number of units to buy.
 */
	public function simple_shopping_list_for_a_single_meal_plan_callback( $atts ) {
		$meal_plan_id = get_the_ID();
		$meals = Mixmymeals_Meal::get_meals_for_plan( $meal_plan_id );

		if ( empty( $meals ) ) {
			return '<p>' . esc_html__( 'No meals found in this plan.', 'mixmymeals' ) . '</p>';
		}

		$ingredient_totals = [];

		// Alle Meals durchgehen
		foreach ( $meals as $meal_post ) {
			$meal = new Mixmymeals_Meal( $meal_post->ID );
			$recipe_id = $meal->get_recipe_id();
			if ( ! $recipe_id ) continue;

			$recipe = new Mixmymeals_Recipe( $recipe_id );
			$steps  = $recipe->get_steps();
			//$scale  = max( 1, $meal->get_servings() / max( 1, $recipe->get_servings() ) );

            $recipe_servings = max( 1, floatval( $recipe->get_servings() ) );
			$meal_servings   = max( 0.01, floatval( $meal->get_servings() ) );
			$scale = $meal_servings / $recipe_servings;


			foreach ( $steps as $step ) {
				foreach ( $step['ingredients'] ?? [] as $ing ) {
					if ( empty( $ing['id'] ) || ! isset( $ing['amount'] ) ) continue;

					$ing_id    = intval( $ing['id'] );
					$amount    = floatval( $ing['amount'] ) * $scale;
					$amount_id = !empty($ing['amount_id']) ? intval($ing['amount_id']) : 0;

					$unit_weight = 1;
					$unit_title  = 'Gramm';

					// Wenn eine Einheit gewählt wurde, ihr Gewicht und Titel abrufen
					if ( $amount_id ) {
						$amount_obj = new Mixmymeals_Ingredient_Amount( $amount_id );
						$unit_weight = max(0.0001, floatval($amount_obj->get_weight()));
						$unit_title  = $amount_obj->get_title() ?: 'g';
					}

					if ( ! isset( $ingredient_totals[ $ing_id ] ) ) {
						$ingredient_totals[ $ing_id ] = [
							'total_weight' => 0,
							'total_units'  => 0,
							'unit_weight'  => $unit_weight,
							'unit_title'   => $unit_title,
						];
					}

					$ingredient_totals[ $ing_id ]['total_weight'] += $amount * $unit_weight;
					$ingredient_totals[ $ing_id ]['total_units']  += $amount;
				}
			}
		}

		if ( empty( $ingredient_totals ) ) {
			return '<p>' . esc_html__( 'No ingredients found.', 'mixmymeals' ) . '</p>';
		}

		ob_start(); ?>
        <div class="mmm-simple-shopping-list">
            <ul class="mmm-shopping-list-items">
				<?php foreach ( $ingredient_totals as $ing_id => $data ) :
					$unit_weight = $data['unit_weight'];
					$unit_title  = $data['unit_title'];
					$total_weight = $data['total_weight'];
					$total_units  = max(1, round($data['total_units'], 2));

					// Wenn Einheit "Gramm" ist → nur Gewicht ausgeben
					if ( strtolower($unit_title) === 'gramm' || strtolower($unit_title) === 'g' ) {
						$display = round($total_weight, 2) . ' g';
					} else {
						$display = $total_units . ' × ' . $unit_title . ' (' . round($total_weight / max(1, $total_units), 2) . ' g)';
					}
					?>
                    <li class="mmm-shopping-item">
                        <a href="<?php echo esc_url( get_permalink( $ing_id ) ); ?>" class="mmm-ingredient-link">
							<?php echo esc_html( get_the_title( $ing_id ) ); ?>
                        </a>:&nbsp;
                        <span class="mmm-ingredient-details"><?php echo esc_html( $display ); ?></span>
                    </li>
				<?php endforeach; ?>
            </ul>
        </div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Gibt eine Übersicht aller Meal Plans aus (Titel, Zeitraum, Link)
	 */
	public function all_meal_plans_callback( $atts ) {

		$atts = shortcode_atts( [
			'show_dates' => true,
			'order'      => 'DESC',
			'orderby'    => 'date',
			'limit'      => -1,
		], $atts, 'mixmymeals_all_meal_plans' );

		$query_args = [
			'post_type'      => 'mmm_meal_plan',
			'posts_per_page' => intval( $atts['limit'] ),
			'post_status'    => 'publish',
			'orderby'        => sanitize_text_field( $atts['orderby'] ),
			'order'          => sanitize_text_field( $atts['order'] ),
		];

		$meal_plans = get_posts( $query_args );

		if ( empty( $meal_plans ) ) {
			return '<p>' . esc_html__( 'No meal plans found.', 'mixmymeals' ) . '</p>';
		}

		ob_start();
		?>
        <div class="mmm-meal-plan-overview">
			<?php foreach ( $meal_plans as $plan_post ) :
				$plan   = new Mixmymeals_Meal_Plan( $plan_post->ID );
				$start  = $plan->get_start_date();
				$end    = $plan->get_end_date();
				$link   = get_permalink( $plan_post->ID );

				// Meals laden
				$meals = Mixmymeals_Meal::get_meals_for_plan( $plan_post->ID );
				?>
                <div class="mmm-meal-plan-card">
                    <h3 class="mmm-plan-title">
                        <a href="<?php echo esc_url( $link ); ?>">
							<?php echo esc_html( get_the_title( $plan_post->ID ) ); ?>
                        </a>
                    </h3>

					<?php if ( $atts['show_dates'] && ( $start || $end ) ) : ?>
                        <div class="mmm-plan-dates">
							<?php
							echo esc_html( date_i18n( get_option('date_format'), strtotime( $start ) ) );
							if ( $end ) {
								echo ' – ' . esc_html( date_i18n( get_option('date_format'), strtotime( $end ) ) );
							}
							?>
                        </div>
					<?php endif; ?>

					<?php if ( ! empty( $meals ) ) : ?>
                        <div class="mmm-plan-meals">
                            <ul class="mmm-meal-list">
								<?php foreach ( $meals as $meal_post ) :
									$meal = new Mixmymeals_Meal( $meal_post->ID );
									$recipe_id = $meal->get_recipe_id();
									if ( ! $recipe_id ) {
										continue;
									}
									$recipe_title = get_the_title( $recipe_id );
									$recipe_link  = get_permalink( $recipe_id );
									?>
                                    <li class="mmm-meal-item">
                                        <a href="<?php echo esc_url( $recipe_link ); ?>">
											<?php echo esc_html( $recipe_title ); ?>
                                        </a>
										<?php if ( $meal->get_servings() ) : ?>
                                            <small>(<?php echo intval( $meal->get_servings() ); ?> <?php esc_html_e( 'servings', 'mixmymeals' ); ?>)</small>
										<?php endif; ?>
                                    </li>
								<?php endforeach; ?>
                            </ul>
                        </div>
					<?php else : ?>
                        <p><em><?php esc_html_e( 'No meals assigned to this plan.', 'mixmymeals' ); ?></em></p>
					<?php endif; ?>
                </div>
			<?php endforeach; ?>
        </div>
		<?php
		return ob_get_clean();
	}


	/**
	 * Shortcode: Gibt eine Nährwerttabelle für ein Rezept aus (pro Portion & pro 100 g).
	 */
	public function recipe_nutrition_table_for_a_single_recipe_callback( $atts ) {
		$post_id = get_the_ID();

		if ( ! $post_id || get_post_type( $post_id ) !== 'mmm_recipe' ) {
			return '<p>' . esc_html__( 'This shortcode can only be used on recipe posts.', 'mixmymeals' ) . '</p>';
		}

		$recipe = new Mixmymeals_Recipe( $post_id );
		$steps  = $recipe->get_steps();

		if ( empty( $steps ) ) {
			return '<p>' . esc_html__( 'No steps or ingredients found for this recipe.', 'mixmymeals' ) . '</p>';
		}

		// Nährwerte berechnen
		$nutrition_per_serving = $recipe->calculate_nutrition_per_serving();
		$nutrition_per_100g    = $recipe->calculate_nutrition_per_100g();

		ob_start(); ?>
        <div class="mmm-recipe-nutrition">
            <table class="mmm-nutrition-table">
                <thead>
                <tr>
                    <th><?php esc_html_e( 'Nutrition', 'mixmymeals' ); ?></th>
                    <th><?php esc_html_e( 'per serving', 'mixmymeals' ); ?></th>
                    <th><?php esc_html_e( 'per 100 g', 'mixmymeals' ); ?></th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td><?php esc_html_e( 'Calories', 'mixmymeals' ); ?></td>
                    <td><?php echo esc_html( round( $nutrition_per_serving['calories'], 1 ) ); ?> kcal</td>
                    <td><?php echo esc_html( round( $nutrition_per_100g['calories'], 1 ) ); ?> kcal</td>
                </tr>
                <tr>
                    <td><?php esc_html_e( 'Protein', 'mixmymeals' ); ?></td>
                    <td><?php echo esc_html( round( $nutrition_per_serving['protein'], 1 ) ); ?> g</td>
                    <td><?php echo esc_html( round( $nutrition_per_100g['protein'], 1 ) ); ?> g</td>
                </tr>
                <tr>
                    <td><?php esc_html_e( 'Carbohydrates', 'mixmymeals' ); ?></td>
                    <td><?php echo esc_html( round( $nutrition_per_serving['carbs'], 1 ) ); ?> g</td>
                    <td><?php echo esc_html( round( $nutrition_per_100g['carbs'], 1 ) ); ?> g</td>
                </tr>
                <tr>
                    <td><?php esc_html_e( 'Fat', 'mixmymeals' ); ?></td>
                    <td><?php echo esc_html( round( $nutrition_per_serving['fat'], 1 ) ); ?> g</td>
                    <td><?php echo esc_html( round( $nutrition_per_100g['fat'], 1 ) ); ?> g</td>
                </tr>
                <tr>
                    <td><?php esc_html_e( 'Sugar', 'mixmymeals' ); ?></td>
                    <td><?php echo esc_html( round( $nutrition_per_serving['sugar'], 1 ) ); ?> g</td>
                    <td><?php echo esc_html( round( $nutrition_per_100g['sugar'], 1 ) ); ?> g</td>
                </tr>
                <tr>
                    <td><?php esc_html_e( 'Fiber', 'mixmymeals' ); ?></td>
                    <td><?php echo esc_html( round( $nutrition_per_serving['fiber'], 1 ) ); ?> g</td>
                    <td><?php echo esc_html( round( $nutrition_per_100g['fiber'], 1 ) ); ?> g</td>
                </tr>
                <tr>
                    <td><?php esc_html_e( 'Salt', 'mixmymeals' ); ?></td>
                    <td><?php echo esc_html( round( $nutrition_per_serving['salt'], 2 ) ); ?> g</td>
                    <td><?php echo esc_html( round( $nutrition_per_100g['salt'], 2 ) ); ?> g</td>
                </tr>
                </tbody>
            </table>
        </div>
		<?php
		return ob_get_clean();
	}


}
