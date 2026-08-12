<?php
/**
 * Meal plan PDF.
 *
 * Owns the public PDF endpoint and the HTML for its pages. Uses
 * Mixmymeals_PDF_Builder as the engine — that class stays a generic dompdf
 * wrapper and knows nothing about meal plans.
 *
 * Moved here from Mixmymeals_Shortcodes: rendering a PDF is not a shortcode.
 * Behaviour is unchanged, including the deliberate absence of a nonce (PDFs of
 * published plans must be shareable by anonymous visitors) and the per-IP rate
 * limit that protects it.
 *
 * @package MixMyMeals/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mixmymeals_Meal_Plan_PDF
 */
class Mixmymeals_Meal_Plan_PDF {

	/**
	 * Registers the PDF endpoint.
	 *
	 * Static and called once from the bootstrap, like the cart endpoints.
	 */
	public static function register_ajax(): void {
		add_action( 'wp_ajax_mmm_generate_pdf', array( __CLASS__, 'mmm_generate_pdf_ajax_callback' ) );
		add_action( 'wp_ajax_nopriv_mmm_generate_pdf', array( __CLASS__, 'mmm_generate_pdf_ajax_callback' ) );
	}

	/**
	 * AJAX handler for wp_ajax_mmm_generate_pdf.
	 * Generates and streams a PDF for the specified meal plan.
	 * Rate-limited to 5 requests per IP per minute.
	 *
	 * @return void
	 */
	public static function mmm_generate_pdf_ajax_callback(): void {

		/*
		 * Deliberately nonce-free: a PDF of a published plan has to be
		 * shareable and cacheable by anonymous visitors, who have no nonce. The
		 * guard is that only published plans are served, plus the per-IP rate
		 * limit further down.
		 */
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- public, cacheable endpoint; see comment above.
		if ( empty( $_GET['meal_plan_id'] ) ) {
			wp_die( esc_html__( 'Meal Plan ID missing.', 'mixmymeals' ) );
		}

		$meal_plan_id = intval( $_GET['meal_plan_id'] );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$post = get_post( $meal_plan_id );
		if ( ! $post || $post->post_type !== 'mmm_meal_plan' || $post->post_status !== 'publish' ) {
			wp_die( esc_html__( 'Meal Plan not found.', 'mixmymeals' ), '', array( 'response' => 404 ) );
		}

		$ip       = md5( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
		$rate_key = 'mmm_pdf_rate_' . $ip;
		$count    = (int) get_transient( $rate_key );
		if ( $count >= 5 ) {
			wp_die( esc_html__( 'Too many requests. Please wait a minute.', 'mixmymeals' ), '', array( 'response' => 429 ) );
		}
		set_transient( $rate_key, $count + 1, 60 );

		$meals_html
			= self::render_meal_plan_overview_html_for_pdf( $meal_plan_id );
		$nutrition_html
			= self::render_nutrition_html_for_pdf( $meal_plan_id );
		$shopping_list_html
			= self::render_shopping_list_html_for_pdf( $meal_plan_id );
		$recipes_html
			= self::render_recipes_contained_in_a_meal_plan_html_for_pdf( $meal_plan_id,
			true, true );


		$pdf_builder = new Mixmymeals_PDF_Builder( [
			'logo_url'    => '',
			'header_html' => '<h1>' . esc_html( get_the_title( $meal_plan_id ) )
			                 . '</h1>',
			'footer_html' => esc_html( __( 'Planned on', 'mixmymeals' ) . ' '
			                           . get_bloginfo( 'name' ) . ' '
			                           . __( 'using MixMyMeals',
					'mixmymeals' ) ),
		] );

		$pdf_builder->add_page( $meals_html );
		if ( '' !== $nutrition_html ) {
			$pdf_builder->add_page( $nutrition_html );
		}
		$pdf_builder->add_page( $shopping_list_html );
		foreach ( $recipes_html as $recipe_html ) {
			$pdf_builder->add_page( $recipe_html );
		}

		$pdf_builder->render( 'meal-plan.pdf', false );
	}

	/**
	 * Renders the meals overview section of a meal plan PDF.
	 *
	 * @param int $meal_plan_id Meal plan post ID.
	 * @return string
	 */
	private static function render_meal_plan_overview_html_for_pdf( int $meal_plan_id
	): string {

		$meal_plan = new Mixmymeals_Meal_Plan( $meal_plan_id );
		$days      = $meal_plan->get_day_range();
		$meals     = $meal_plan->get_meals();

		if ( empty( $meals ) ) {
			return '<p>' . esc_html__( 'No meals found in this plan.',
					'mixmymeals' ) . '</p>';
		}

		$grouped_meals = [];
		foreach ( $meals as $meal_post ) {
			$meal    = new Mixmymeals_Meal( $meal_post->ID );
			$weekday = (int) $meal->get_day();
			$type    = $meal->get_type() ?: 'other';

			if ( $weekday < 1 || $weekday > 7 ) {
				continue;
			}

			$grouped_meals[ $weekday ][ $type ][] = $meal;
		}

		$weekdays = [
			1 => _x( 'Monday', 'weekday', 'mixmymeals' ),
			2 => _x( 'Tuesday', 'weekday', 'mixmymeals' ),
			3 => _x( 'Wednesday', 'weekday', 'mixmymeals' ),
			4 => _x( 'Thursday', 'weekday', 'mixmymeals' ),
			5 => _x( 'Friday', 'weekday', 'mixmymeals' ),
			6 => _x( 'Saturday', 'weekday', 'mixmymeals' ),
			7 => _x( 'Sunday', 'weekday', 'mixmymeals' ),
		];

		$meal_types = Mixmymeals_Meal::get_plaintext_meal_type_map();

		ob_start();
		?>
        <h2><?php esc_html_e( 'Meals', 'mixmymeals' ); ?></h2>

		<?php foreach ( $days as $day_number ) : ?>

			<?php if ( empty( $grouped_meals[ $day_number ] ) ) {
				continue;
			} ?>

            <h3>
				<?php echo esc_html( $weekdays[ $day_number ] ??
				                     /* translators: %d: weekday number, used when the name is unavailable. */
				                     sprintf( __( 'Day %d', 'mixmymeals' ),
					                     $day_number ) ); ?>
            </h3>

            <table class="mmm-meal-plan-overview-table" width="100%">
                <colgroup>
                    <col style="width:20%">
                    <col style="width:70%">
                    <col style="width:10%">
                </colgroup>
                <thead>
                <tr>
                    <th class="align-left"><?php esc_html_e( 'Type',
							'mixmymeals' ); ?></th>
                    <th class="align-left"><?php esc_html_e( 'Meal',
							'mixmymeals' ); ?></th>
                    <th class="align-right"><?php esc_html_e( 'Servings',
							'mixmymeals' ); ?></th>
                </tr>
                </thead>
                <tbody>

				<?php foreach ( $meal_types as $type_code => $type_name ) : ?>

					<?php if ( empty( $grouped_meals[ $day_number ][ $type_code ] ) ) {
						continue;
					} ?>

					<?php
					$cell_meals   = $grouped_meals[ $day_number ][ $type_code ];
					$titles       = [];
					$servings     = [];
					$calories_sum = 0;
					$weight_sum   = 0;

					foreach ( $cell_meals as $meal ) {

						$recipe
							= new Mixmymeals_Recipe( $meal->get_recipe_id() );

						$titles[]
							        = esc_html( get_the_title( $meal->get_id() ) );
						$servings[] = esc_html( $meal->get_servings() );

							$recipe_servings = max( 1.0,
							floatval( $recipe->get_servings() ) );
						$meal_servings   = max( 1.0,
							floatval( $meal->get_servings() ) );
						$factor          = $meal_servings / $recipe_servings;

						$ingredients
							= $recipe->get_ingredients_scaled( $factor );

						$calories = 0;
						$weight   = 0;

						foreach ( $ingredients as $ing ) {

							if ( empty( $ing['id'] )
							     || empty( $ing['weight_g'] ) ) {
								continue;
							}

							$ingredient_obj
								= new Mixmymeals_Ingredient( $ing['id'] );

							if ( ! $ingredient_obj->is_valid() ) {
								continue;
							}

							$nutrition = $ingredient_obj->get_nutrition();
							$w         = floatval( $ing['weight_g'] );

							if ( $w <= 0 ) {
								continue;
							}

							$weight   += $w;
							$calories += (float) ( $nutrition['calories_100'] ?? 0 ) * $w / 100;
						}

							if ( $meal_servings > 0 ) {
							$calories /= $meal_servings;
							$weight   /= $meal_servings;
						}

						$calories_sum += $calories;
						$weight_sum   += $weight;
					}
					?>

                    <tr>
                        <td class="align-left"><?php echo esc_html( $type_name ); ?></td>

                        <td class="align-left">
							<?php
							// Both arrays are escaped where they are filled
							// (see above); the <br> is the separator we add.
							echo implode( '<br>', $titles ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- elements escaped at source.
							echo '<br><small>';
							echo esc_html__( 'Per serving', 'mixmymeals' )
							     . ': '
							     . absint( $weight_sum ) . ' g, '
							     . absint( $calories_sum ) . ' '
							     . esc_html__( 'kcal', 'mixmymeals' );
							echo '</small>';
							?>
                        </td>

                        <td class="align-right">
							<?php echo implode( '<br>', $servings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- elements escaped at source. ?>
                        </td>
                    </tr>

				<?php endforeach; ?>

                </tbody>
            </table>

		<?php endforeach;

		return ob_get_clean();
	}

	/**
	 * Renders the per-day nutrition section of a meal plan PDF: one row per plan
	 * weekday, showing what a single person eats that day, plus the plan total.
	 * Returns '' when the plan has no meals, so no empty page is added.
	 *
	 * @param int $meal_plan_id Meal plan post ID.
	 * @return string
	 */
	private static function render_nutrition_html_for_pdf( int $meal_plan_id ): string {

		$meal_plan = new Mixmymeals_Meal_Plan( $meal_plan_id );
		$by_day    = $meal_plan->calc_nutrition_by_day();

		if ( empty( $by_day ) ) {
			return '';
		}

		/*
		 * Labels and formatting come from Mixmymeals_Meal_Plan, the same source
		 * the plan page and the subscription mail use — this table used to say
		 * "Calories" and "Carbohydrates" where the mail said "Energy" and
		 * "Carbohydrate" for the identical figure.
		 *
		 * The per-day table keeps the four macros: seven columns times seven
		 * weekdays does not fit an A4 page. The full label-style breakdown,
		 * including fibre, sugar, salt and the reference intakes, follows it as
		 * its own table.
		 */
		$labels  = Mixmymeals_Meal_Plan::nutrition_labels();
		$columns = array( 'calories', 'protein', 'carbs', 'fat' );

		$sums            = array_fill_keys( Mixmymeals_Meal_Plan::NUTRITION_KEYS, 0.0 );
		$days_with_meals = 0;

		ob_start();
		?>
        <h2><?php esc_html_e( 'Nutrition per day', 'mixmymeals' ); ?></h2>
        <p><?php esc_html_e( 'Per person, based on one serving of each meal.', 'mixmymeals' ); ?></p>

        <table class="mmm-meal-plan-nutrition-table" border="1"
               cellspacing="0" cellpadding="4" width="100%">
            <thead>
            <tr>
                <th><?php esc_html_e( 'Day', 'mixmymeals' ); ?></th>
				<?php foreach ( $columns as $key ) : ?>
                    <th><?php echo esc_html( $labels[ $key ] ); ?></th>
				<?php endforeach; ?>
            </tr>
            </thead>
            <tbody>
			<?php foreach ( $by_day as $day => $nutrition ) : ?>
                <tr>
                    <td><?php echo esc_html( Mixmymeals_Meal_Plan::weekday_name( (int) $day ) ); ?></td>
					<?php
					// Every key is summed, not just the four on show, so the
					// breakdown below can be averaged from the same pass.
					if ( ! empty( $nutrition['per_person']['calories'] ) ) {
						++$days_with_meals;

						foreach ( Mixmymeals_Meal_Plan::NUTRITION_KEYS as $sum_key ) {
							$sums[ $sum_key ] += (float) ( $nutrition['per_person'][ $sum_key ] ?? 0.0 );
						}
					}

					foreach ( $columns as $key ) :
						?>
                        <td class="align-right">
							<?php echo esc_html( Mixmymeals_Meal_Plan::format_nutrition_value( $key, (float) ( $nutrition['per_person'][ $key ] ?? 0.0 ) ) ); ?>
                        </td>
					<?php endforeach; ?>
                </tr>
			<?php endforeach; ?>
            <tr>
                <td><strong><?php esc_html_e( 'Total', 'mixmymeals' ); ?></strong></td>
				<?php foreach ( $columns as $key ) : ?>
                    <td class="align-right">
                        <strong><?php echo esc_html( Mixmymeals_Meal_Plan::format_nutrition_value( $key, $sums[ $key ] ) ); ?></strong>
                    </td>
				<?php endforeach; ?>
            </tr>
            </tbody>
        </table>

		<?php if ( $days_with_meals ) : ?>
			<?php
			$average = array();

			foreach ( Mixmymeals_Meal_Plan::NUTRITION_KEYS as $key ) {
				$average[ $key ] = $sums[ $key ] / $days_with_meals;
			}

			$percentages = Mixmymeals_Meal_Plan::nutrition_percentages( $average );
			?>
            <h2><?php esc_html_e( 'Average day', 'mixmymeals' ); ?></h2>
            <p><?php
				printf(
					/* translators: %d: number of days of the plan that carry meals. */
					esc_html__( 'Per person, averaged over the %d days of this plan that carry meals.', 'mixmymeals' ),
					(int) $days_with_meals
				);
				?></p>

            <table class="mmm-meal-plan-nutrition-table" border="1"
                   cellspacing="0" cellpadding="4" width="100%">
                <thead>
                <tr>
                    <th><?php esc_html_e( 'Nutrition', 'mixmymeals' ); ?></th>
                    <th><?php esc_html_e( 'Avg. per day / person', 'mixmymeals' ); ?></th>
                    <th><?php esc_html_e( 'of RI*', 'mixmymeals' ); ?></th>
                </tr>
                </thead>
                <tbody>
				<?php foreach ( $labels as $key => $label ) : ?>
                    <tr>
                        <td><?php echo esc_html( $label ); ?></td>
                        <td class="align-right"><?php echo esc_html( Mixmymeals_Meal_Plan::format_nutrition_value( $key, $average[ $key ] ) ); ?></td>
                        <td class="align-right"><?php echo esc_html( sprintf( '%d %%', (int) $percentages[ $key ] ) ); ?></td>
                    </tr>
				<?php endforeach; ?>
                </tbody>
            </table>
            <p><?php echo esc_html( '* ' . Mixmymeals_Meal_Plan::reference_intake_note() ); ?></p>
		<?php endif; ?>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Renders the shopping list section of a meal plan PDF.
	 *
	 * @param int  $meal_plan_id     Meal plan post ID.
	 * @param bool $sort_by_category Whether to group ingredients by category.
	 * @return string
	 */
	private static function render_shopping_list_html_for_pdf(
		int $meal_plan_id, bool $sort_by_category = true
	): string {

		$meal_plan = new Mixmymeals_Meal_Plan( $meal_plan_id );
		$ingredients_by_cat
		           = $meal_plan->calc_shopping_list( $sort_by_category );

		if ( empty( $ingredients_by_cat ) ) {
			return '<p>' . esc_html__( 'No ingredients found.', 'mixmymeals' )
			       . '</p>';
		}

		ob_start();
		?>
        <h2><?php esc_html_e( 'Shopping List', 'mixmymeals' ); ?></h2>

		<?php foreach ( $ingredients_by_cat as $cat_name => $ings ) : ?>
            <h3><?php echo esc_html( $cat_name ); ?></h3>
            <table class="mmm-meal-plan-shopping-list-table" border="1"
                   cellspacing="0" cellpadding="4" width="100%">
                <thead>
                <tr>
                    <th>✔</th>
                    <th><?php esc_html_e( 'Ingredient', 'mixmymeals' ); ?></th>
                    <th><?php esc_html_e( 'Amount', 'mixmymeals' ); ?></th>
                </tr>
                </thead>
                <tbody>
				<?php foreach ( $ings as $ing_id => $data ) :

					$total_weight = $data['total_weight'];
					$is_optional  = ! empty( $data['optional'] );

					// Bedarfsanzeige analog zur Frontend-Liste.
					$main_line = get_the_title( $ing_id ) . ', '
					             . Mixmymeals_Meal_Plan::build_needed_amount_string(
						             $data['units'] ?? [],
						             $total_weight
					             );
					if ( $is_optional ) {
						$main_line .= ' (' . __( 'optional', 'mixmymeals' ) . ')';
					}
					?>
                    <tr>
                        <td style="text-align:center;">☐</td>
                        <td><?php echo esc_html( $main_line ); ?></td>
                        <td><?php echo esc_html( number_format_i18n( $total_weight,
								2 ) ); ?> g
                        </td>
                    </tr>
				<?php endforeach; ?>
                </tbody>
            </table>
		<?php endforeach;

		return ob_get_clean();
	}

	/**
	 * Renders one PDF page per recipe contained in the meal plan, with scaled ingredients.
	 *
	 * @param int  $meal_plan_id          Meal plan post ID.
	 * @param bool $show_ingredients_top  Whether to include an ingredient summary at the top.
	 * @param bool $show_step_ingredients Whether to list ingredients per step.
	 * @return string[]
	 */
	private static function render_recipes_contained_in_a_meal_plan_html_for_pdf(
		int $meal_plan_id,
		bool $show_ingredients_top = true,
		bool $show_step_ingredients = false
	): array {

		$meal_plan = new Mixmymeals_Meal_Plan( $meal_plan_id );
		$meals     = $meal_plan->get_meals();

		if ( empty( $meals ) ) {
			return [];
		}

		$pages = [];

		foreach ( $meals as $meal_post ) {

			$meal       = new Mixmymeals_Meal( $meal_post->ID );
			$recipe_id  = $meal->get_recipe_id();

			if ( ! $recipe_id ) {
				continue;
			}

			$recipe = new Mixmymeals_Recipe( $recipe_id );
			$steps  = $recipe->get_steps();

			if ( empty( $steps ) ) {
				continue;
			}

			$recipe_servings = max( 1.0, (float) $recipe->get_servings() );
			$meal_servings   = max( 1.0, (float) $meal->get_servings() );
			$factor          = $meal_servings / $recipe_servings;

				$ingredients = $recipe->get_ingredients_scaled( $factor );

				$ingredients_map = [];
			foreach ( $ingredients as $ing ) {
				$id = (int) ( $ing['id'] ?? 0 );
				if ( $id > 0 ) {
					$ingredients_map[ $id ] = $ing;
				}
			}

			ob_start();
			?>
            <div class="mmm-pdf-page">

                <h2><?php echo esc_html( get_the_title( $recipe_id ) ); ?></h2>

                <div class="mmm-recipe-meta">
                    <p>
                        <strong><?php esc_html_e( 'Servings', 'mixmymeals' ); ?>:</strong>
						<?php echo esc_html( number_format_i18n( $meal_servings ) ); ?>
                    </p>
                </div>

				<?php if ( $show_ingredients_top && ! empty( $ingredients ) ) : ?>
                    <div class="mmm-meal-ingredients-overview">
                        <h3><?php esc_html_e( 'Ingredients Overview', 'mixmymeals' ); ?></h3>
                        <ul>
							<?php foreach ( $ingredients as $ingredient ) :

								$amount    = (float) ( $ingredient['amount'] ?? 0 );
								$unit      = $ingredient['unit'] ?: 'g';
								$weight_g  = round( (float) ( $ingredient['weight_g'] ?? 0 ) );

								$display_amount = $amount > 0
									? rtrim( rtrim( number_format_i18n( $amount, 1 ), '0' ), ',' )
									: '';
								?>
                                <li>
									<?php echo esc_html( $ingredient['name'] ); ?>
									<?php if ( $display_amount ) : ?>
                                        – <?php echo esc_html( $display_amount . ' ' . $unit ); ?>
                                        (<?php echo esc_html( $weight_g ); ?> g)
									<?php endif; ?>
									<?php if ( ! empty( $ingredient['optional'] ) ) : ?>
                                        (<?php echo esc_html__( 'optional', 'mixmymeals' ); ?>)
									<?php endif; ?>
                                </li>
							<?php endforeach; ?>
                        </ul>
                    </div>
				<?php endif; ?>

                <div class="mmm-recipe-steps scaled">

					<?php foreach ( $steps as $index => $step ) : ?>
                        <div class="mmm-step">

							<?php if ( ! empty( $step['title'] ) ) : ?>
                                <h3>
									<?php echo esc_html(
										/* translators: %d: position of the step, starting at 1. */
										sprintf( __( 'Step %d', 'mixmymeals' ), $index + 1 )
										. ': ' . $step['title']
									); ?>
                                </h3>
							<?php endif; ?>

							<?php if ( ! empty( $step['description'] ) ) : ?>
                                <div class="mmm-step-description"><?php echo wp_kses_post( $step['description'] ); ?></div>
							<?php endif; ?>

							<?php if ( $show_step_ingredients && ! empty( $step['ingredients'] ) ) : ?>
                                <ul>
									<?php foreach ( $step['ingredients'] as $ing ) :

										$id = (int) ( $ing['id'] ?? 0 );
										if ( $id <= 0 ) {
											continue;
										}

										$scaled = $ingredients_map[ $id ] ?? null;

										if ( ! $scaled ) {
											continue;
										}

										$amount   = (float) ( $scaled['amount'] ?? 0 );
										$unit     = $scaled['unit'] ?: 'g';
										$weight_g = round( (float) ( $scaled['weight_g'] ?? 0 ) );

										$display_amount = $amount > 0
											? rtrim( rtrim( number_format_i18n( $amount, 2 ), '0' ), ',' )
											: '';
										?>
                                        <li>
											<?php echo esc_html( get_the_title( $id ) ); ?>
											<?php if ( $display_amount ) : ?>
                                                – <?php echo esc_html( $display_amount . ' ' . $unit ); ?>
                                                (<?php echo esc_html( $weight_g ); ?> g)
											<?php endif; ?>
											<?php if ( ! empty( $ing['optional'] ) ) : ?>
                                                (<?php echo esc_html__( 'optional', 'mixmymeals' ); ?>)
											<?php endif; ?>
                                        </li>
									<?php endforeach; ?>
                                </ul>
							<?php endif; ?>

                        </div>
					<?php endforeach; ?>

                </div>

            </div>
			<?php

			$pages[] = ob_get_clean();
		}

		return $pages;
	}
}
