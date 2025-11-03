<?php
/**
 * Template: Single Meal
 *
 * @package MixMyMeals/Templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	$meal_id = get_the_ID();
	?>

    <main id="mmm-meal" class="mmm-container">

        <header class="mmm-header">
            <h1 class="mmm-title"><?php the_title(); ?></h1>

            <div class="mmm-meta">
				<?php
				$servings = get_post_meta( $meal_id, '_mmm_servings', true );
				$duration = get_post_meta( $meal_id, '_mmm_duration', true );

				if ( $servings ) :
					?>
                    <span class="mmm-meta-item">
						<strong><?php esc_html_e( 'Servings:', 'mixmymeals' ); ?></strong>
						<?php echo esc_html( $servings ); ?>
					</span>
				<?php endif; ?>

				<?php if ( $duration ) : ?>
                    <span class="mmm-meta-item">
						<strong><?php esc_html_e( 'Duration:', 'mixmymeals' ); ?></strong>
						<?php echo esc_html( $duration ); ?> min
					</span>
				<?php endif; ?>
            </div>
        </header>

		<?php if ( has_post_thumbnail() ) : ?>
            <figure class="mmm-image">
				<?php the_post_thumbnail( 'large' ); ?>
            </figure>
		<?php endif; ?>

        <section class="mmm-description">
			<?php the_content(); ?>
        </section>

        <section class="mmm-recipe">
            <h2><?php esc_html_e( 'Recipe', 'mixmymeals' ); ?></h2>
			<?php echo do_shortcode( '[mixmymeals_scaled_recipe_steps]' ); ?>
        </section>

        <section class="mmm-nutrition">
            <h2><?php esc_html_e( 'Nutritional Information', 'mixmymeals' ); ?></h2>
			<?php echo do_shortcode( '[meal_nutrition_table_for_a_single_meal]' ); ?>
        </section>

    </main>

<?php endwhile; ?>

<?php get_footer(); ?>
