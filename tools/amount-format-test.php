<?php
/**
 * The one wording of amounts for people (HG-114):
 * Mixmymeals_Ingredient_Amount::format_text() / format_units(), and that
 * machine-read output (Bring!) is untouched by it.
 *
 * wp eval-file wp-content/plugins/mixmymeals/tools/amount-format-test.php
 */

$fails = 0;
$ok    = function ( $label, $cond, $detail = '' ) use ( &$fails ) {
	if ( ! $cond ) {
		$fails++;
	}
	echo ( $cond ? 'ok   ' : 'FAIL ' ) . $label . ( $cond || '' === $detail ? '' : ' — ' . $detail ) . PHP_EOL;
};
$is = function ( $label, $expected, $actual ) use ( $ok ) {
	$ok( $label, $expected === $actual, 'expected "' . $expected . '", got "' . $actual . '"' );
};

switch_to_locale( 'de_DE' );
wp_set_current_user( 1 );

$a = 'Mixmymeals_Ingredient_Amount';

echo "-- 1. Grams and the kilogram boundary\n";
$is( 'whole grams', '200 g', $a::format_text( 200, '' ) );
$is( '"g" is no unit', '200 g', $a::format_text( 200, 'g' ) );
$is( 'weight wins for a gram row', '250 g', $a::format_text( 3, 'g', 250 ) );
$is( 'rounded below 1000', '999 g', $a::format_text( 999.4, '' ) );
$is( 'kg from 1000 g', '1 kg', $a::format_text( 1000, '' ) );
$is( 'kg with one decimal', '1,2 kg', $a::format_text( 1234, '' ) );
$is( 'kg rounds to one decimal', '2,5 kg', $a::format_text( 2468, '' ) );
$is( 'small weights keep a decimal', '2,5 g', $a::format_text( 2.5, '' ) );
$is( 'nothing above zero is ""', '', $a::format_text( 0, 'EL' ) );

echo "-- 2. Counts\n";
$is( 'whole unit', '2× Dose', $a::format_text( 2, 'Dose' ) );
$is( 'half', '½× Dose', $a::format_text( 0.5, 'Dose' ) );
$is( 'quarter', '¼× Dose', $a::format_text( 0.25, 'Dose' ) );
$is( 'three quarters', '¾× Dose', $a::format_text( 0.75, 'Dose' ) );
$is( 'one and a half', '1½× Dose', $a::format_text( 1.5, 'Dose' ) );
$is( 'other decimals: one place, comma', '1,3× EL', $a::format_text( 1.33, 'EL' ) );
$is( 'no plural of the unit', '3× Zehe', $a::format_text( 3, 'Zehe' ) );
$is( 'metric unit: a quantity, not a count', '250 ml', $a::format_text( 250, 'ml' ) );
$is( 'metric unit with a decimal', '1,5 l', $a::format_text( 1.5, 'l' ) );
$is( 'tiny count never reads 0', '0,1× Prise', $a::format_text( 0.01, 'Prise' ) );

echo "-- 3. Shopping-list lines\n";
$tin   = array(
	'amount'         => 2.0,
	'weight_g'       => 800.0,
	'unit_title'     => 'Dose',
	'step_divisor'   => 1,
	'is_indivisible' => true,
);
$loose = array(
	'amount'       => 100.0,
	'weight_g'     => 100.0,
	'unit_title'   => '',
	'step_divisor' => 0,
);
$spoon = array(
	'amount'       => 3.0,
	'weight_g'     => 30.0,
	'unit_title'   => 'EL',
	'step_divisor' => 0,
);
$is( 'mixed line', '2× Dose + 100 g (≈ 900 g)', $a::format_units( array( $tin, $loose ), 900 ) );
$is( 'mixed line above 1 kg', '2× Dose + 400 g (≈ 1,2 kg)', $a::format_units( array( $tin, array_merge( $loose, array( 'weight_g' => 400.0 ) ) ), 1200 ) );
$is( 'unit without a step goes into grams', '30 g', $a::format_units( array( $spoon ), 30 ) );
$is( 'half tin', '1½× Dose (≈ 600 g)', $a::format_units( array( array_merge( $tin, array( 'amount' => 1.5, 'step_divisor' => 2, 'is_indivisible' => false ) ) ), 600 ) );
$is( 'short: counts only', '2× Dose', $a::format_units( array( $tin ), 800, 'short' ) );
$is( 'short: otherwise the weight', '1,2 kg', $a::format_units( array( $tin, $loose ), 1200, 'short' ) );
$is( 'forwarder: build_needed_amount_string()', '2× Dose + 100 g (≈ 900 g)', Mixmymeals_Meal_Plan::build_needed_amount_string( array( $tin, $loose ), 900 ) );
$is( 'forwarder: build_short_amount_string()', '900 g', Mixmymeals_Meal_Plan::build_short_amount_string( array( $tin, $loose ), 900 ) );
$is( 'forwarder: Shortcodes', '2× Dose', Mixmymeals_Shortcodes::build_needed_amount_string( array( $tin ), 0 ) );
$is( 'breakdown: unit', '2× EL', Mixmymeals_Meal_Plan::detail_amount_string( array( 'amount' => 2, 'unit_title' => 'EL' ) ) );
$is( 'breakdown: grams, never "200× g"', '200 g', Mixmymeals_Meal_Plan::detail_amount_string( array( 'amount' => 200, 'unit_title' => 'g', 'weight_g' => 200 ) ) );

echo "-- 4. A plan: page list worded, Bring! lines unchanged\n";
$created = array();
$tomato  = wp_insert_post( array( 'post_type' => 'mmm_ingredient', 'post_title' => 'T Tomaten', 'post_status' => 'publish' ) );
$flour   = wp_insert_post( array( 'post_type' => 'mmm_ingredient', 'post_title' => 'T Mehl', 'post_status' => 'publish' ) );
$created = array_merge( $created, array( $tomato, $flour ) );
$can     = Mixmymeals_Ingredient_Amount::create( $tomato, 'Dose' );
$can->set_weight( 400 );
$can->set_divisor( 2 );
$created[] = $can->get_id();

$recipe_id = wp_insert_post( array( 'post_type' => 'mmm_recipe', 'post_title' => 'R1', 'post_status' => 'publish' ) );
$created[] = $recipe_id;
$recipe    = new Mixmymeals_Recipe( $recipe_id );
$recipe->set_servings( 2 );
$recipe->set_steps(
	array(
		array(
			'title'       => 'Eins',
			'description' => '',
			'ingredients' => array(
				array( 'id' => $tomato, 'amount' => 1.5, 'amount_id' => $can->get_id() ),
				array( 'id' => $flour, 'amount' => 600 ),
			),
		),
	)
);

$plan_id   = wp_insert_post( array( 'post_type' => 'mmm_meal_plan', 'post_title' => 'T Plan', 'post_status' => 'publish' ) );
$created[] = $plan_id;
update_post_meta( $plan_id, 'start_day', 1 );
update_post_meta( $plan_id, 'end_day', 7 );
$meal = Mixmymeals_Meal::create( 'T Mahlzeit', $plan_id );
$meal->set_recipe_id( $recipe_id );
$meal->set_servings( 4 );
$meal->set_day( 1 );
$created[] = $meal->get_id();

$plan  = new Mixmymeals_Meal_Plan( $plan_id );
$lines = array();
foreach ( $plan->calc_shopping_list( false ) as $items ) {
	foreach ( $items as $data ) {
		$lines[ (int) $data['ingredient_id'] ] = Mixmymeals_Meal_Plan::build_needed_amount_string( $data['units'], (float) $data['total_weight'] );
	}
}
$is( 'tins counted with the total', '3× Dose (≈ 1,2 kg)', $lines[ $tomato ] ?? '' );
$is( 'flour in kg', '1,2 kg', $lines[ $flour ] ?? '' );

$bring = $plan->get_bring_shopping_list_lines();
$ok( 'Bring!: count without fraction or ×', in_array( '3 Dose T Tomaten', $bring, true ), implode( ' | ', $bring ) );
$ok( 'Bring!: grams without separator or kg', in_array( '1200 g T Mehl', $bring, true ), implode( ' | ', $bring ) );

$schema = $recipe->get_schema();
$ok( 'schema.org keeps the parser format', in_array( '1.5 Dose T Tomaten', (array) ( $schema['recipeIngredient'] ?? array() ), true ), implode( ' | ', (array) ( $schema['recipeIngredient'] ?? array() ) ) );

foreach ( array_reverse( $created ) as $id ) {
	wp_delete_post( $id, true );
}

echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
