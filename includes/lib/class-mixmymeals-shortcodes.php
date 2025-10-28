<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Mixmymeals_Shortcodes {

	public function __construct() {

		add_shortcode( 'mixmymeals_all_meals_in_a_plan',
			array( $this, 'all_meals_in_a_plan_callback' ) );

	}

	/*
 * Prints all meals in a meal plan as a weekly table (clean version, no inline CSS).
 */
	public function all_meals_in_a_plan_callback() {

		$meal_plan_id  = get_the_ID();
		$meal_plan_obj = new Mixmymeals_Meal_Plan( $meal_plan_id );
		$meals         = $meal_plan_obj->get_meals();

		if ( empty( $meals ) ) {
			return '<p class="mmm-no-meals">' . esc_html__( 'No meals found in this plan.', 'mixmymeals' ) . '</p>';
		}

		// Definierte Wochentage in Reihenfolge
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
			$weekday = get_post_meta( $meal_post->ID, '_mmm_weekday', true );
			if ( ! isset( $grouped_meals[ $weekday ] ) ) {
				$grouped_meals[ $weekday ] = [];
			}
			$grouped_meals[ $weekday ][] = $meal_post;
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
							<?php foreach ( $grouped_meals[ $weekday_key ] as $meal ) :
								$recipe_id = get_post_meta( $meal->ID, 'recipe_id', true );
								$servings  = get_post_meta( $meal->ID, 'servings', true );
								$note      = get_post_meta( $meal->ID, 'note', true );
								?>
								<div class="mmm-meal-item">
									<strong class="mmm-meal-title"><?php echo esc_html( $meal->post_title ); ?></strong>
									<?php if ( $servings ) : ?>
										<div class="mmm-meal-servings">
											<?php echo esc_html__( 'Servings:', 'mixmymeals' ) . ' ' . intval( $servings ); ?>
										</div>
									<?php endif; ?>
									<?php if ( $note ) : ?>
										<div class="mmm-meal-note"><?php echo esc_html( $note ); ?></div>
									<?php endif; ?>
									<?php if ( $recipe_id ) : ?>
										<div class="mmm-meal-recipe-id">
											<small><?php echo esc_html__( 'Recipe ID:', 'mixmymeals' ) . ' ' . intval( $recipe_id ); ?></small>
										</div>
									<?php endif; ?>
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
	 * Renders a loop item to be used with the all meals in a plan query.
	 */
	public function all_meals_in_a_plan_loop_item_callback() {

		$html = '';

		ob_start();

		echo '';

		return ob_get_clean();

	}

}
