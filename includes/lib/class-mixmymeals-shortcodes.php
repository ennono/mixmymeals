<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Mixmymeals_Shortcodes {

	public function __construct() {

		add_shortcode( 'mixmymeals_all_meals_in_a_single_meal_plan',
			[ $this, 'all_meals_in_a_plan_callback' ] );

		add_shortcode( 'mixmymeals_shopping_list_for_a_single_meal_plan',
			[ $this, 'shopping_list_for_a_single_meal_plan_callback' ] );

		add_shortcode( 'mixmymeals_recipes_in_a_single_meal_plan',
			[ $this, 'recipes_in_a_single_meal_plan_callback' ] );

		add_shortcode( 'mixmymeals_recipe_steps',
			[ $this, 'recipe_steps_callback' ] );

		add_shortcode( 'mixmymeals_scaled_recipe_steps',
			array( $this, 'scaled_recipe_steps_callback' ) );

		add_shortcode( 'meal_nutrition_table_for_a_sinle_meal',
			array( $this, 'meal_nutrition_table_for_a_sinle_meal_callback' ) );



	}

	/*
	 * Prints all meals in a meal plan as a weekly table.
	 */
	public function all_meals_in_a_plan_callback() {

		$meal_plan_id  = get_the_ID();
		if ( ! $meal_plan_id ) {
			return '<p>' . esc_html__( 'No meal plan found.', 'mixmymeals' ) . '</p>';
		}

		$meals = Mixmymeals_Meal::get_meals_for_plan( $meal_plan_id );
		if ( empty( $meals ) ) {
			return '<p class="mmm-no-meals">' . esc_html__( 'No meals found in this plan.', 'mixmymeals' ) . '</p>';
		}

		$weekdays = [
			'monday'    => __( 'Monday', 'mixmymeals' ),
			'tuesday'   => __( 'Tuesday', 'mixmymeals' ),
			'wednesday' => __( 'Wednesday', 'mixmymeals' ),
			'thursday'  => __( 'Thursday', 'mixmymeals' ),
			'friday'    => __( 'Friday', 'mixmymeals' ),
			'saturday'  => __( 'Saturday', 'mixmymeals' ),
			'sunday'    => __( 'Sunday', 'mixmymeals' ),
			''          => __( 'No Day assigned', 'mixmymeals' ),
		];

		// Meals nach Wochentag gruppieren
		$grouped_meals = [];
		foreach ( $meals as $meal_post ) {
			$meal    = new Mixmymeals_Meal( $meal_post->ID );
			$weekday = $meal->get_weekday();
			$grouped_meals[ $weekday ][] = $meal;
		}

		ob_start();
		?>
        <table class="mmm-meal-plan-table">
            <thead>
            <tr>
				<?php foreach ( $weekdays as $weekday_label ) : ?>
                    <th><?php echo esc_html( $weekday_label ); ?></th>
				<?php endforeach; ?>
            </tr>
            </thead>
            <tbody>
            <tr>
				<?php foreach ( $weekdays as $weekday_key => $weekday_label ) : ?>
                    <td class="mmm-meal-day">
						<?php if ( ! empty( $grouped_meals[ $weekday_key ] ) ) : ?>
							<?php foreach ( $grouped_meals[ $weekday_key ] as $meal ) : ?>
								<?php
								$recipe_id = $meal->get_recipe_id();
								$recipe_title = get_the_title( $recipe_id );
								$recipe_link  = get_permalink( $recipe_id );
								?>
                                <div class="mmm-meal-item">
                                    <strong class="mmm-meal-title">
                                        <a href="<?php echo esc_url( $recipe_link ); ?>">
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

                                    <a class="mmm-meal-link" href="<?php echo esc_url( get_permalink( $meal->get_id() ) ); ?>">
										<?php esc_html_e( 'View Meal', 'mixmymeals' ); ?>
                                    </a>
                                </div>
							<?php endforeach; ?>
						<?php else : ?>
                            <em class="mmm-no-meal"><?php echo esc_html__( 'No meals', 'mixmymeals' ); ?></em>
						<?php endif; ?>
                    </td>
				<?php endforeach; ?>
            </tr>
            </tbody>
        </table>
		<?php
		return ob_get_clean();
	}

	/*
	 * Shopping matrix: Rows = Ingredients, Columns = Recipes, Cells = amount
	 */
	public function shopping_list_for_a_single_meal_plan_callback( $atts ) {
		$meal_plan_id = get_the_ID();
		$meals = Mixmymeals_Meal::get_meals_for_plan( $meal_plan_id );
		if ( empty( $meals ) ) {
			return '<p>' . esc_html__( 'No meals found in this plan.', 'mixmymeals' ) . '</p>';
		}

		$recipes = [];
		$ingredient_matrix = [];

		foreach ( $meals as $meal_post ) {
			$meal = new Mixmymeals_Meal( $meal_post->ID );
			$recipe_id = $meal->get_recipe_id();
			if ( ! $recipe_id ) continue;

			$recipes[ $recipe_id ] = get_the_title( $recipe_id );
			$recipe = new Mixmymeals_Recipe( $recipe_id );
			$steps  = $recipe->get_steps();

			foreach ( $steps as $step ) {
				foreach ( $step['ingredients'] ?? [] as $ing ) {
					if ( empty( $ing['id'] ) || empty( $ing['amount'] ) ) continue;
					$ing_id = intval( $ing['id'] );
					$amount = floatval( $ing['amount'] );

					if ( ! isset( $ingredient_matrix[ $ing_id ] ) ) {
						$ingredient_matrix[ $ing_id ] = [];
					}

					$ingredient_matrix[ $ing_id ][ $recipe_id ] = ( $ingredient_matrix[ $ing_id ][ $recipe_id ] ?? 0 ) + $amount;
				}
			}
		}

		if ( empty( $ingredient_matrix ) ) {
			return '<p>' . esc_html__( 'No ingredients found.', 'mixmymeals' ) . '</p>';
		}

		ob_start();
		?>
        <table class="mmm-shopping-matrix">
            <thead>
            <tr>
                <th><?php esc_html_e( 'Ingredient', 'mixmymeals' ); ?></th>
				<?php foreach ( $recipes as $recipe_id => $recipe_title ) : ?>
                    <th>
                        <a href="<?php echo esc_url( get_permalink( $recipe_id ) ); ?>">
							<?php echo esc_html( $recipe_title ); ?>
                        </a>
                    </th>
				<?php endforeach; ?>
            </tr>
            </thead>
            <tbody>
			<?php foreach ( $ingredient_matrix as $ing_id => $recipe_amounts ) : ?>
                <tr>
                    <td>
                        <a href="<?php echo esc_url( get_permalink( $ing_id ) ); ?>">
							<?php echo esc_html( get_the_title( $ing_id ) ); ?>
                        </a>
                    </td>
					<?php foreach ( $recipes as $recipe_id => $recipe_title ) : ?>
                        <td class="mmm-amount-cell">
							<?php
							$val = $recipe_amounts[ $recipe_id ] ?? 0;
							echo $val > 0 ? esc_html( round( $val, 2 ) ) : '-';
							?>
                        </td>
					<?php endforeach; ?>
                </tr>
			<?php endforeach; ?>
            </tbody>
        </table>
		<?php
		return ob_get_clean();
	}

	/*
	 * Lists all recipes (linked) in a meal plan.
	 */
	public function recipes_in_a_single_meal_plan_callback( $atts ) {
		$meal_plan_id = get_the_ID();
		$meals = Mixmymeals_Meal::get_meals_for_plan( $meal_plan_id );
		if ( empty( $meals ) ) {
			return '<p>' . esc_html__( 'No recipes found in this plan.', 'mixmymeals' ) . '</p>';
		}

		ob_start();
		echo '<ul class="mmm-recipe-title-list">';
		foreach ( $meals as $meal_post ) {
			$meal = new Mixmymeals_Meal( $meal_post->ID );
			$recipe_id = $meal->get_recipe_id();
			if ( ! $recipe_id ) continue;

			printf(
				'<li><a href="%s">%s</a></li>',
				esc_url( get_permalink( $recipe_id ) ),
				esc_html( get_the_title( $recipe_id ) )
			);
		}
		echo '</ul>';
		return ob_get_clean();
	}

	/*
	 * Prints all steps of a recipe (linked ingredients etc.)
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
								?>
                                <li>
                                    <a class="mmm-ingredient-name" href="<?php echo esc_url( get_permalink( $ing['id'] ) ); ?>">
										<?php echo esc_html( get_the_title( $ing['id'] ) ); ?>
                                    </a>
									<?php if ( $amount > 0 ) : ?>
                                        <span class="mmm-ingredient-amount"><?php echo esc_html( $amount ); ?></span>
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
								$amount = floatval( $ing['amount'] ?? 0 ) * $scaling_factor;
								?>
                                <li>
                                    <a class="mmm-ingredient-name" href="<?php echo esc_url( get_permalink( $ing['id'] ) ); ?>">
										<?php echo esc_html( get_the_title( $ing['id'] ) ); ?>
                                    </a>
									<?php if ( $amount > 0 ) : ?>
                                        <span class="mmm-ingredient-amount">
										<?php echo esc_html( round( $amount, 2 ) ); ?>
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
	 * Shortcode: Gibt eine Nährwerttabelle für ein Meal aus
	 * mit Spalten "pro 100 g" und "pro Portion".
	 */
	public function meal_nutrition_table_for_a_sinle_meal_callback( $atts ) {
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

		$servings = max( 1, $meal->get_servings() );
		$base_servings = max( 1, $recipe->get_servings() );
		$scale_factor = $servings / $base_servings;

		$rows = [];

		// Ernährungssummen für das gesamte Meal
		$totals = array_fill_keys( ['calories','fat','carbs','protein','sugar','fiber','salt'], 0 );

		foreach ( $steps as $step ) {
			foreach ( $step['ingredients'] ?? [] as $ing ) {
				if ( empty( $ing['id'] ) || empty( $ing['amount'] ) ) continue;

				$ing_obj   = new Mixmymeals_Ingredient( $ing['id'] );
				$nutrition = $ing_obj->get_nutrition();
				if ( empty( $nutrition ) ) continue;

				$weight_item = floatval( $nutrition['weight_item'] ?? 100 );
				if ( $weight_item <= 0 ) $weight_item = 100;

				$amount_scaled = floatval( $ing['amount'] ) * $scale_factor * $weight_item / 100;

				$row = [
					'id'        => $ing['id'],
					'name'      => get_the_title( $ing['id'] ),
					'cal_100'   => floatval( $nutrition['calories_100'] ?? 0 ),
					'fat_100'   => floatval( $nutrition['fat'] ?? 0 ),
					'carbs_100' => floatval( $nutrition['carbs'] ?? 0 ),
					'protein_100' => floatval( $nutrition['protein'] ?? 0 ),
					'sugar_100' => floatval( $nutrition['sugar'] ?? 0 ),
					'fiber_100' => floatval( $nutrition['fiber'] ?? 0 ),
					'salt_100'  => floatval( $nutrition['salt'] ?? 0 ),

					// Skaliert nach Portion
					'cal_port'   => floatval( $nutrition['calories_100'] ?? 0 ) * $amount_scaled / 100,
					'fat_port'   => floatval( $nutrition['fat'] ?? 0 ) * $amount_scaled / 100,
					'carbs_port' => floatval( $nutrition['carbs'] ?? 0 ) * $amount_scaled / 100,
					'protein_port' => floatval( $nutrition['protein'] ?? 0 ) * $amount_scaled / 100,
					'sugar_port' => floatval( $nutrition['sugar'] ?? 0 ) * $amount_scaled / 100,
					'fiber_port' => floatval( $nutrition['fiber'] ?? 0 ) * $amount_scaled / 100,
					'salt_port'  => floatval( $nutrition['salt'] ?? 0 ) * $amount_scaled / 100,
				];

				foreach ( ['calories'=>'cal_port','fat'=>'fat_port','carbs'=>'carbs_port','protein'=>'protein_port','sugar'=>'sugar_port','fiber'=>'fiber_port','salt'=>'salt_port'] as $key => $col ) {
					$totals[$key] += $row[$col];
				}

				$rows[] = $row;
			}
		}

		ob_start();
		?>
        <div class="mmm-meal-nutrition">

            <table class="mmm-nutrition-table">
                <thead>
                <tr>
                    <th><?php esc_html_e( 'Ingredient', 'mixmymeals' ); ?></th>
                    <th><?php esc_html_e( 'Calories (100 g)', 'mixmymeals' ); ?></th>
                    <th><?php esc_html_e( 'Calories (per serving)', 'mixmymeals' ); ?></th>
                    <th><?php esc_html_e( 'Protein (g/serving)', 'mixmymeals' ); ?></th>
                    <th><?php esc_html_e( 'Carbs (g/serving)', 'mixmymeals' ); ?></th>
                    <th><?php esc_html_e( 'Fat (g/serving)', 'mixmymeals' ); ?></th>
                    <th><?php esc_html_e( 'Sugar (g/serving)', 'mixmymeals' ); ?></th>
                    <th><?php esc_html_e( 'Fiber (g/serving)', 'mixmymeals' ); ?></th>
                    <th><?php esc_html_e( 'Salt (g/serving)', 'mixmymeals' ); ?></th>
                </tr>
                </thead>
                <tbody>
				<?php foreach ( $rows as $r ) : ?>
                    <tr>
                        <td>
                            <a href="<?php echo esc_url( get_permalink( $r['id'] ) ); ?>">
								<?php echo esc_html( $r['name'] ); ?>
                            </a>
                        </td>
                        <td><?php echo esc_html( round( $r['cal_100'], 1 ) ); ?></td>
                        <td><?php echo esc_html( round( $r['cal_port'], 1 ) ); ?></td>
                        <td><?php echo esc_html( round( $r['protein_port'], 1 ) ); ?></td>
                        <td><?php echo esc_html( round( $r['carbs_port'], 1 ) ); ?></td>
                        <td><?php echo esc_html( round( $r['fat_port'], 1 ) ); ?></td>
                        <td><?php echo esc_html( round( $r['sugar_port'], 1 ) ); ?></td>
                        <td><?php echo esc_html( round( $r['fiber_port'], 1 ) ); ?></td>
                        <td><?php echo esc_html( round( $r['salt_port'], 1 ) ); ?></td>
                    </tr>
				<?php endforeach; ?>
                </tbody>
                <tfoot>
                <tr class="mmm-nutrition-total">
                    <th><?php esc_html_e( 'Total per serving', 'mixmymeals' ); ?></th>
                    <th>–</th>
                    <th><?php echo esc_html( round( $totals['calories'], 1 ) ); ?></th>
                    <th><?php echo esc_html( round( $totals['protein'], 1 ) ); ?></th>
                    <th><?php echo esc_html( round( $totals['carbs'], 1 ) ); ?></th>
                    <th><?php echo esc_html( round( $totals['fat'], 1 ) ); ?></th>
                    <th><?php echo esc_html( round( $totals['sugar'], 1 ) ); ?></th>
                    <th><?php echo esc_html( round( $totals['fiber'], 1 ) ); ?></th>
                    <th><?php echo esc_html( round( $totals['salt'], 1 ) ); ?></th>
                </tr>
                </tfoot>
            </table>
        </div>
		<?php
		return ob_get_clean();
	}


}
