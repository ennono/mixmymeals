<?php
/**
 * Ingredient list and step lists of recipe and meal pages (HG-101): the
 * amount only, summed per unit in the list — no weight in brackets beside it.
 *
 * Run: wp eval-file wp-content/plugins/mixmymeals/tools/ingredient-list-test.php
 *
 * @package mixmymeals
 */

$fails = 0;
$check = static function ( bool $ok, string $what ) use ( &$fails ): void {
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $what . "\n";
	$fails += $ok ? 0 : 1;
};

$lentils = wp_insert_post( array( 'post_type' => 'mmm_ingredient', 'post_title' => 'T Linsen', 'post_status' => 'publish' ) );
$oil     = wp_insert_post( array( 'post_type' => 'mmm_ingredient', 'post_title' => 'T Öl', 'post_status' => 'publish' ) );
$spoon   = wp_insert_post( array( 'post_type' => 'mmm_ingr_unit', 'post_title' => 'EL', 'post_status' => 'publish', 'post_parent' => $oil ) );
update_post_meta( $spoon, '_mmm_ingr_unit_weight', 10 );

$recipe_id = wp_insert_post( array( 'post_type' => 'mmm_recipe', 'post_title' => 'T Linsensuppe', 'post_status' => 'publish' ) );
$recipe = new Mixmymeals_Recipe( $recipe_id );
$recipe->set_servings( 2 );
$recipe->set_steps(
	array(
		array(
			'title'       => 'Eins',
			'ingredients' => array(
				array( 'id' => $lentils, 'amount' => 250 ),
				array( 'id' => $oil, 'amount' => 3, 'amount_id' => $spoon ),
			),
		),
		array(
			'title'       => 'Zwei',
			'ingredients' => array(
				array( 'id' => $oil, 'amount' => 20 ),
				array( 'id' => $oil, 'amount' => 1.5, 'amount_id' => $spoon ),
			),
		),
		array(
			'title'       => 'Drei',
			'ingredients' => array(
				array( 'id' => $lentils, 'amount' => 0 ),
			),
		),
	)
);

$html = do_shortcode( '[mmm_recipe_ingredients post_id="' . $recipe_id . '"]' );
$text = preg_replace( '/\s+/', ' ', wp_strip_all_tags( $html ) );

$check( false !== strpos( $text, 'T Linsen 250 g' ), 'gram amount printed once: ' . $text );
$check( false === strpos( $text, '(250 g)' ), 'no weight beside a gram amount' );
$check( false !== strpos( $text, '4,5 EL + 20 g' ), 'units summed apart' );
$check( false === strpos( $text, ' g)' ), 'no weight in brackets anywhere' );

// Steps of the recipe: each row as stored, its amount worded alike.
$steps = preg_replace( '/\s+/', ' ', wp_strip_all_tags( do_shortcode( '[mmm_recipe_steps_for_a_single_recipe post_id="' . $recipe_id . '"]' ) ) );
$check( false !== strpos( $steps, 'T Linsen 250 g T Öl 3 EL' ), 'recipe step 1: ' . $steps );
$check( false !== strpos( $steps, 'T Öl 20 g T Öl 1,5 EL' ), 'recipe step 2' );
$check( 1 === preg_match( '/Drei T Linsen\s*$/', $steps ), 'a row without amount is listed by name' );
$check( false === strpos( $steps, '≙' ) && false === strpos( $steps, ' à ' ), 'no weight beside a step amount' );

// Steps of a meal for four: doubled, through the same list.
$plan_id = wp_insert_post( array( 'post_type' => 'mmm_meal_plan', 'post_title' => 'T Plan', 'post_status' => 'publish' ) );
update_post_meta( $plan_id, 'start_day', 1 );
update_post_meta( $plan_id, 'end_day', 7 );
$meal = Mixmymeals_Meal::create( 'T Linsensuppe', $plan_id );
$meal->set_recipe_id( $recipe_id );
$meal->set_servings( 4 );
$meal->set_day( 1 );

$GLOBALS['post'] = get_post( $meal->get_id() );
setup_postdata( $GLOBALS['post'] );
$meal_steps = preg_replace( '/\s+/', ' ', wp_strip_all_tags( do_shortcode( '[mmm_scaled_recipe_steps_for_a_single_meal]' ) ) );
$meal_list  = preg_replace( '/\s+/', ' ', wp_strip_all_tags( do_shortcode( '[mmm_meal_ingredients servings_control="0"]' ) ) );
wp_reset_postdata();

$check( false !== strpos( $meal_steps, 'T Linsen 500 g T Öl 6 EL' ), 'meal step 1 doubled: ' . $meal_steps );
$check( false !== strpos( $meal_steps, 'T Öl 40 g T Öl 3 EL' ), 'meal step 2 doubled' );
$check( false !== strpos( $meal_list, 'T Linsen 500 g' ) && false !== strpos( $meal_list, '9 EL + 40 g' ), 'meal list doubled: ' . $meal_list );

foreach ( array( $meal->get_id(), $plan_id, $recipe_id, $spoon, $oil, $lentils ) as $id ) {
	wp_delete_post( $id, true );
}

echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
