<?php
/**
 * Template: Single Recipe
 *
 * @package MixMyMeals/Templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	$recipe_id = get_the_ID();

	$prep_time = get_post_meta( $recipe_id, '_mmm_prep_time', true );
	$cook_time = get_post_meta( $recipe_id, '_mmm_cook_time', true );
	$servings  = get_post_meta( $recipe_id, '_mmm_servings', true );
	?>

    <main id="mmm-recipe" class="mmm-container">

        <header class="mmm-header">
            <h1 class="mmm-title"><?php the_title(); ?></h1>

            <div class="mmm-meta">
				<?php if ( $prep_time ) : ?>
                    <span class="mmm-meta-item">
						<strong><?php esc_html_e( 'Prep:', 'mixmymeals' ); ?></strong>
						<?php echo esc_html( $prep_time ); ?> min
					</span>
				<?php endif; ?>

				<?php if ( $cook_time ) : ?>
                    <span class="mmm-meta-item">
						<strong><?php esc_html_e( 'Cook:', 'mixmymeals' ); ?></strong>
						<?php echo esc_html( $cook_time ); ?> min
					</span>
				<?php endif; ?>

				<?php if ( $servings ) : ?>
                    <span class="mmm-meta-item">
						<strong><?php esc_html_e( 'Servings:', 'mixmymeals' ); ?></strong>
						<?php echo esc_html( $servings ); ?>
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

        <hr class="mmm-divider" />

        <section class="mmm-ingredients">
            <h2><?php esc_html_e( 'Ingredients', 'mixmymeals' ); ?></h2>
			<?php echo do_shortcode( '[mmm_recipe_ingredients id="' . intval( $recipe_id ) . '"]' ); ?>
        </section>

        <section class="mmm-instructions">
            <h2><?php esc_html_e( 'Instructions', 'mixmymeals' ); ?></h2>
			<?php echo do_shortcode( '[mmm_recipe_steps id="' . intval( $recipe_id ) . '"]' ); ?>
        </section>

        <section class="mmm-nutrition">
            <h2><?php esc_html_e( 'Nutritional Values', 'mixmymeals' ); ?></h2>
			<?php echo do_shortcode( '[mmm_recipe_nutrition id="' . intval( $recipe_id ) . '"]' ); ?>
        </section>

    </main>

<?php endwhile; ?>

<?php get_footer(); ?>
