<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Mixmymeals_Shortcodes {

	public function __construct() {

		add_shortcode( 'mixmymeals_all_meals_in_a_single_meal_plan',
			[ $this, 'all_meals_in_a_plan_callback' ] );

		add_shortcode( 'mixmymeals_shopping_list_for_a_single_meal_plan',
			[ $this, 'shopping_list_for_a_single_meal_plan_callback' ] );

		add_shortcode( 'mixmymeals_simple_shopping_list_for_a_single_meal_plan',
			[ $this, 'simple_shopping_list_for_a_single_meal_plan_callback' ] );

		add_shortcode( 'mixmymeals_steps_for_a_single_recipe',
			[ $this, 'recipe_steps_callback' ] );

		add_shortcode( 'mixmymeals_scaled_steps_for_a_single_meal',
			[ $this, 'scaled_recipe_steps_callback' ] );

		add_shortcode( 'mixmymeals_meal_nutrition_table_for_a_single_meal',
			[ $this, 'meal_nutrition_table_for_a_single_meal_callback' ] );

		add_shortcode( 'mixmymeals_all_meal_plans',
			[ $this, 'all_meal_plans_callback' ] );

	}

	/**
	 * Gibt alle Meals eines Plans als Wochenplan aus.
	 */
	public function all_meals_in_a_plan_callback() {
		$meal_plan_id = get_the_ID();
		if ( ! $meal_plan_id ) {
			return '<p>' . esc_html__( 'No meal plan found.', 'mixmymeals' ) . '</p>';
		}

		$meal_plan = new Mixmymeals_Meal_Plan( $meal_plan_id );
		$start_date = $meal_plan->get_start_date();
		$end_date   = $meal_plan->get_end_date(); // Muss im Meal-Plan ergänzt werden

		if ( ! $start_date || ! $end_date ) {
			return '<p>' . esc_html__( 'Start or End date not set.', 'mixmymeals' ) . '</p>';
		}

		$meals = Mixmymeals_Meal::get_meals_for_plan( $meal_plan_id );
		if ( empty( $meals ) ) {
			return '<p>' . esc_html__( 'No meals found in this plan.', 'mixmymeals' ) . '</p>';
		}

		// Alle Tage zwischen Start und Enddatum erzeugen
		$period = new DatePeriod(
			new DateTime( $start_date ),
			new DateInterval('P1D'),
			( new DateTime( $end_date ) )->modify('+1 day') // Enddatum inklusiv
		);

		$days = [];
		foreach ( $period as $day ) {
			$days[ $day->format('Y-m-d') ] = ucfirst( $day->format('l') ); // Wochentag Name
		}

		// Meals nach Datum gruppieren (hier gehen wir davon aus, dass Meals ein Datum zugeordnet haben)
		$grouped_meals = [];
		foreach ( $meals as $meal_post ) {
			$meal = new Mixmymeals_Meal( $meal_post->ID );
			$meal_date = $meal->get_date(); // Muss im Meal-Objekt ergänzt werden
			if ( $meal_date && isset( $days[ $meal_date ] ) ) {
				$grouped_meals[ $meal_date ][] = $meal;
			}
		}

		ob_start(); ?>
        <table class="mmm-meal-plan-table">
            <thead>
            <tr>
				<?php foreach ( $days as $date => $label ) : ?>
                    <th><?php echo esc_html( $label ); ?><br><small><?php echo esc_html( date_i18n( get_option('date_format'), strtotime($date) ) ); ?></small></th>
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

		$recipes = [];
		$ingredient_matrix = [];
		$recipe_servings = [];

		// Durch alle Meals iterieren
		foreach ( $meals as $meal_post ) {
			$meal = new Mixmymeals_Meal( $meal_post->ID );
			$recipe_id = $meal->get_recipe_id();
			if ( ! $recipe_id ) continue;

			$recipes[ $recipe_id ] = get_the_title( $recipe_id );
			$recipe_servings[ $recipe_id ] = intval( $meal->get_servings() );

			$recipe = new Mixmymeals_Recipe( $recipe_id );
			$steps  = $recipe->get_steps();
			$scale  = max( 1, $meal->get_servings() / max( 1, $recipe->get_servings() ) );

			foreach ( $steps as $step ) {
				foreach ( $step['ingredients'] ?? [] as $ing ) {
					if ( empty( $ing['id'] ) || ! isset( $ing['amount'] ) ) continue;

					$ing_id    = intval( $ing['id'] );
					$amount    = floatval( $ing['amount'] ) * $scale;
					$amount_id = !empty($ing['amount_id']) ? intval($ing['amount_id']) : 0;

					$unit_weight = 1;
					$unit_title  = 'Gramm';

					if ( $amount_id ) {
						$amount_obj = new Mixmymeals_Ingredient_Amount( $amount_id );
						$unit_weight = max(0.0001, floatval($amount_obj->get_weight()));
						$unit_title  = $amount_obj->get_title() ?: 'g';
					}

					if ( ! isset( $ingredient_matrix[ $ing_id ] ) ) {
						$ingredient_matrix[ $ing_id ] = [
							'total_weight' => 0,
							'unit_title'   => $unit_title,
							'unit_weight'  => $unit_weight,
							'total_units'  => 0,
						];
					}

					// Rezept-spezifische Werte mit Einheit speichern
					if ( ! isset( $ingredient_matrix[ $ing_id ][ $recipe_id ] ) ) {
						$ingredient_matrix[ $ing_id ][ $recipe_id ] = [
							'amount' => 0,
							'unit_title' => $unit_title,
							'unit_weight' => $unit_weight,
						];
					}

					// Mengen summieren
					$ingredient_matrix[ $ing_id ][ $recipe_id ]['amount'] += $amount;
					$ingredient_matrix[ $ing_id ]['total_weight'] += $amount * $unit_weight;
					$ingredient_matrix[ $ing_id ]['total_units']  += $amount;
				}
			}
		}

		if ( empty( $ingredient_matrix ) ) {
			return '<p>' . esc_html__( 'No ingredients found.', 'mixmymeals' ) . '</p>';
		}

		ob_start(); ?>
        <table class="mmm-shopping-matrix">
            <thead>
            <tr>
                <th><?php esc_html_e( 'Zutat', 'mixmymeals' ); ?></th>
                <th class="mmm-total-column"><?php esc_html_e( 'Gesamtmenge', 'mixmymeals' ); ?></th>
				<?php foreach ( $recipes as $recipe_id => $recipe_title ) :
					$servings = $recipe_servings[$recipe_id] ?? '';
					?>
                    <th>
                        <?php echo esc_html__( 'For', 'mixmymeals' ); ?>
                        <a href="<?php echo esc_url( get_permalink( $recipe_id ) ); ?>">
							<?php
							echo esc_html( $recipe_title );
							if ( $servings ) {
								echo ' (' . intval( $servings ) . ' ' . esc_html__( 'Servings', 'mixmymeals' ) . ')';
							}
							?>
                        </a>
                    </th>
				<?php endforeach; ?>
            </tr>
            </thead>
            <tbody>
			<?php foreach ( $ingredient_matrix as $ing_id => $data ) :
				$total_weight = $data['total_weight'];
				$unit_title   = $data['unit_title'];
				$unit_weight  = $data['unit_weight'];
				$total_units  = max( 1, round( $data['total_units'], 2 ) );

				// Wenn Einheit "Gramm" ist, kein × anzeigen
				if ( strtolower( $unit_title ) === 'gramm' || strtolower( $unit_title ) === 'g' ) {
					$total_display = round( $total_weight, 2 ) . ' g';
				} else {
					$total_display = $total_units . ' × ' . $unit_title . ' (' . round( $total_weight / max(1, $total_units), 2 ) . ' g)';
				}
				?>
                <tr>
                    <td>
                        <a href="<?php echo esc_url( get_permalink( $ing_id ) ); ?>">
							<?php echo esc_html( get_the_title( $ing_id ) ); ?>
                        </a>
                    </td>
                    <td class="mmm-total-cell" style="font-weight:bold;">
						<?php echo esc_html( $total_display ); ?>
                    </td>
					<?php foreach ( $recipes as $recipe_id => $recipe_title ) :
						$entry = $data[ $recipe_id ] ?? null;
						if ( $entry && $entry['amount'] > 0 ) {
							$amount = round( $entry['amount'], 2 );
							$unit_t = $entry['unit_title'];
							$unit_w = $entry['unit_weight'];
							if ( strtolower( $unit_t ) === 'gramm' || strtolower( $unit_t ) === 'g' ) {
								$display = round( $amount * $unit_w, 2 ) . ' g';
							} else {
								$display = $amount . ' × ' . $unit_t;
							}
						} else {
							$display = '-';
						}
						?>
                        <td class="mmm-amount-cell"><?php echo esc_html( $display ); ?></td>
					<?php endforeach; ?>
                </tr>
			<?php endforeach; ?>
            </tbody>
        </table>
		<?php
		return ob_get_clean();
	}






	/*
	* Prints all steps of a recipe (linked ingredients etc.) — shows the exact amount entered in the step.
	*/
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
					<span class="mmm-step-number">
						<?php echo esc_html( sprintf( __( 'Step %d', 'mixmymeals' ), $index + 1 ) ); ?>
					</span>
						<?php if ( ! empty( $step['title'] ) ) : ?>
                            <h3 class="mmm-step-title"><?php echo esc_html( $step['title'] ); ?></h3>
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

		// Portionsskalierung berechnen
		$recipe_servings = max( 1, floatval( $recipe->get_servings() ) );
		$meal_servings   = max( 1, floatval( $meal->get_servings() ) );
		$scaling_factor  = $meal_servings / $recipe_servings;

		ob_start();
		?>
        <div class="mmm-recipe-steps scaled">
			<?php foreach ( $steps as $index => $step ) : ?>
                <div class="mmm-step">
                    <div class="mmm-step-header">
					<span class="mmm-step-number">
						<?php echo esc_html( sprintf( __( 'Step %d', 'mixmymeals' ), $index + 1 ) ); ?>
					</span>
						<?php if ( ! empty( $step['title'] ) ) : ?>
                            <h3 class="mmm-step-title"><?php echo esc_html( $step['title'] ); ?></h3>
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

								// Menge skalieren
								$amount = floatval( $ing['amount'] ?? 0 ) * $scaling_factor;

								// Gewichtseinheit abrufen
								$ingredient_obj = new Mixmymeals_Ingredient( $ing['id'] );
								$nutrition = $ingredient_obj->get_nutrition();
								$weight_item = floatval( $nutrition['weight_item'] ?? 0 );

								// Formatierung
								$amount_display = ( $amount > 0 )
									? number_format_i18n( $amount, ( fmod( $amount, 1 ) !== 0.0 ? 2 : 0 ) )
									: '';

								$weight_display = $weight_item > 0 ? sprintf( __( '%s g', 'mixmymeals' ), number_format_i18n( $weight_item, 0 ) ) : '';

								// z. B. "2 × 500 g" oder nur "3"
								$amount_text = trim( $amount_display . ( $weight_display ? ' × ' . $weight_display : '' ) );
								?>
                                <li>
                                    <a class="mmm-ingredient-name" href="<?php echo esc_url( get_permalink( $ing['id'] ) ); ?>">
										<?php echo esc_html( get_the_title( $ing['id'] ) ); ?>
                                    </a>
									<?php if ( $amount_text ) : ?>
                                        <span class="mmm-ingredient-amount">(<?php echo esc_html( $amount_text ); ?>)</span>
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
		return ob_get_clean();
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
			$scale  = max( 1, $meal->get_servings() / max( 1, $recipe->get_servings() ) );

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
            <h3><?php esc_html_e( 'Einkaufsliste', 'mixmymeals' ); ?></h3>
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
				$plan = new Mixmymeals_Meal_Plan( $plan_post->ID );
				$start = $plan->get_start_date();
				$end   = $plan->get_end_date();
				$link  = get_permalink( $plan_post->ID );
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

					<?php if ( has_excerpt( $plan_post->ID ) ) : ?>
                        <div class="mmm-plan-excerpt">
							<?php echo wpautop( get_the_excerpt( $plan_post->ID ) ); ?>
                        </div>
					<?php endif; ?>
                </div>
			<?php endforeach; ?>
        </div>
		<?php
		return ob_get_clean();
	}

}
