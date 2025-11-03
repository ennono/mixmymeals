<?php
/**
 * Template: Single Ingredient
 *
 * @package MixMyMeals/Templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	$ingredient_id = get_the_ID();

	$unit        = get_post_meta( $ingredient_id, '_mmm_unit', true );
	$category    = get_post_meta( $ingredient_id, '_mmm_category', true );
	$nutrition   = get_post_meta( $ingredient_id, '_mmm_nutrition', true ); // JSON or array
	?>

    <main id="mmm-ingredient" class="mmm-container">

        <header class="mmm-header">
            <h1 class="mmm-title"><?php the_title(); ?></h1>

            <div class="mmm-meta">
				<?php if ( $category ) : ?>
                    <span class="mmm-meta-item">
						<strong><?php esc_html_e( 'Category:', 'mixmymeals' ); ?></strong>
						<?php echo esc_html( $category ); ?>
					</span>
				<?php endif; ?>

				<?php if ( $unit ) : ?>
                    <span class="mmm-meta-item">
						<strong><?php esc_html_e( 'Unit:', 'mixmymeals' ); ?></strong>
						<?php echo esc_html( $unit ); ?>
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

    </main>

<?php endwhile; ?>

<?php get_footer(); ?>
