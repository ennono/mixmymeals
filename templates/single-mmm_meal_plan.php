<?php
/**
 * Template: Single Meal Plan
 *
 * @package MixMyMeals/Templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	$plan_id = get_the_ID();

	$start_date = get_post_meta( $plan_id, '_mmm_plan_start_date', true );
	$end_date   = get_post_meta( $plan_id, '_mmm_plan_end_date', true );
	$description = get_the_content();
	?>

    <main id="mmm-meal-plan" class="mmm-container">

        <header class="mmm-header">
            <h1 class="mmm-title"><?php the_title(); ?></h1>

			<?php if ( $start_date || $end_date ) : ?>
                <p class="mmm-date-range">
					<?php
					if ( $start_date ) {
						echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $start_date ) ) );
					}
					if ( $end_date ) {
						echo ' – ' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $end_date ) ) );
					}
					?>
                </p>
			<?php endif; ?>
        </header>

        <section class="mmm-meals">
            <h2><?php esc_html_e( 'Meals in this plan', 'mixmymeals' ); ?></h2>

			<?php
			// Zeigt alle Meals in diesem Plan (per Shortcode)
			echo do_shortcode( '[mixmymeals_all_meals_in_a_single_meal_plan]' );
			?>
        </section>

        <section class="mmm-shopping-list">
            <h2><?php esc_html_e( 'Shopping list for this plan', 'mixmymeals' ); ?></h2>

			<?php
			// Zeigt eine Einkaufsliste für den gesamten Plan
			echo do_shortcode( '[mixmymeals_shopping_list_for_a_single_meal_plan]' );
			?>
        </section>

	    <?php if ( ! empty( $description ) ) : ?>
            <section class="mmm-description">
			    <?php the_content(); ?>
            </section>
	    <?php endif; ?>

    </main>

<?php endwhile; ?>

<?php get_footer(); ?>
