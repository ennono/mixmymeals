<?php
/**
 * A plan's price per serving (HG-116): weighted by servings, read from the
 * rows, and a lower bound ("ab …") where prices are missing.
 *
 * Runs against a real install, creates its own posts and deletes them again.
 * Everything it creates is titled "T …" or "R<n>".
 *
 *   wp eval-file wp-content/plugins/mixmymeals/tools/plan-cost-test.php
 *
 * @package MixMyMeals
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$fails = 0;
$ok    = function ( $label, $cond, $detail = '' ) use ( &$fails ) {
	if ( ! $cond ) {
		++$fails;
	}
	echo ( $cond ? 'ok   ' : 'FAIL ' ) . $label . ( $cond || '' === $detail ? '' : ' — ' . $detail ) . PHP_EOL;
};
$near  = static fn( $a, $b ) => abs( (float) $a - (float) $b ) < 0.001;

switch_to_locale( 'de_DE' );
wp_set_current_user( 1 );

$trash     = array();
$make_post = function ( array $args ) use ( &$trash ): int {
	$id      = (int) wp_insert_post( $args + array( 'post_status' => 'publish' ) );
	$trash[] = $id;
	return $id;
};

// Ingredients: two priced, one with nutrition but no price (a gap), water.
$nutrition = array(
	'calories_100' => 100,
	'fat'          => 1,
	'carbs'        => 10,
	'protein'      => 5,
);
$ingredient = function ( string $title, ?float $price, bool $with_nutrition = true ) use ( $make_post, $nutrition ): int {
	$id  = $make_post( array( 'post_type' => 'mmm_ingredient', 'post_title' => $title ) );
	$obj = new Mixmymeals_Ingredient( $id );
	if ( $with_nutrition ) {
		$obj->set_nutrition( $nutrition );
	}
	if ( null !== $price ) {
		$obj->set_price_per_100g( $price );
	}
	return $id;
};
$rice   = $ingredient( 'T Reis', 0.50 );
$cheese = $ingredient( 'T Käse', 2.00 );
$saffron = $ingredient( 'T Safran', null );
$water  = $ingredient( 'T Wasser', null, false );

$recipe = function ( string $title, array $rows, int $servings ) use ( $make_post ): int {
	$id  = $make_post( array( 'post_type' => 'mmm_recipe', 'post_title' => $title ) );
	$obj = new Mixmymeals_Recipe( $id );
	$obj->set_servings( $servings );
	$obj->set_steps(
		array(
			array(
				'title'       => 'Eins',
				'description' => '',
				'ingredients' => $rows,
			),
		)
	);
	return $id;
};

// R1: 400 g rice for 2 = 1,00 € per serving. R2: 200 g cheese for 2 = 2,00 €.
// R3: rice and water — water has no price and no nutrition, still complete.
// R4: rice and saffron — saffron is a gap.
$r1 = $recipe( 'R1', array( array( 'id' => $rice, 'amount' => 400 ) ), 2 );
$r2 = $recipe( 'R2', array( array( 'id' => $cheese, 'amount' => 200 ) ), 2 );
$r3 = $recipe( 'R3', array( array( 'id' => $rice, 'amount' => 400 ), array( 'id' => $water, 'amount' => 500 ) ), 2 );
$r4 = $recipe( 'R4', array( array( 'id' => $rice, 'amount' => 400 ), array( 'id' => $saffron, 'amount' => 1 ) ), 2 );

$plan_with = function ( array $meals ) use ( $make_post, &$trash ): Mixmymeals_Meal_Plan {
	$plan_id = $make_post( array( 'post_type' => 'mmm_meal_plan', 'post_title' => 'T Plan' ) );
	update_post_meta( $plan_id, 'start_day', 1 );
	update_post_meta( $plan_id, 'end_day', 7 );
	foreach ( $meals as $i => $m ) {
		$meal = Mixmymeals_Meal::create( 'T Mahlzeit', $plan_id );
		$meal->set_recipe_id( $m[0] );
		$meal->set_servings( $m[1] );
		$meal->set_day( $i + 1 );
		$trash[] = $meal->get_id();
	}
	return new Mixmymeals_Meal_Plan( $plan_id );
};

echo "-- 1. Weighted by servings\n";
$plan = $plan_with( array( array( $r1, 6 ), array( $r2, 1 ) ) );
$cost = $plan->calc_cost_per_serving();
// (1,00 × 6 + 2,00 × 1) ÷ 7 = 1,142857…; unweighted would be 1,50.
$ok( 'six servings of R1 outweigh one of R2', $near( $cost['cost'], 8 / 7 ), (string) $cost['cost'] );
$ok( 'complete', true === $cost['complete'] );
$ok( 'calculate_avg_cost_per_serving() is the same figure', $near( $plan->calculate_avg_cost_per_serving(), 8 / 7 ) );

$texts = Mixmymeals_Shortcodes::meal_plan_stat_texts( $plan->get_id() );
$ok( 'chip without "ab"', isset( $texts['price'] ) && false !== strpos( $texts['price'], '1,14 €' ) && false === stripos( $texts['price'], 'ab ' ), $texts['price'] ?? '' );

echo "-- 2. Water is no gap\n";
$plan = $plan_with( array( array( $r3, 2 ) ) );
$cost = $plan->calc_cost_per_serving();
$ok( 'water costs nothing and the sum stays complete', $near( $cost['cost'], 1.0 ) && $cost['complete'], wp_json_encode( $cost ) );

echo "-- 3. A recipe with a missing price makes a lower bound\n";
$plan = $plan_with( array( array( $r1, 2 ), array( $r4, 2 ) ) );
$cost = $plan->calc_cost_per_serving();
$ok( 'the priced part still counts', $near( $cost['cost'], 1.0 ), (string) $cost['cost'] );
$ok( 'incomplete', false === $cost['complete'] );
$texts = Mixmymeals_Shortcodes::meal_plan_stat_texts( $plan->get_id() );
$ok( 'chip says "ab"', isset( $texts['price'] ) && false !== strpos( $texts['price'], 'ab 1,00 €' ), $texts['price'] ?? '' );

echo "-- 4. A recipe nobody priced is no longer left out silently\n";
$r5   = $recipe( 'R5', array( array( 'id' => $saffron, 'amount' => 2 ) ), 2 );
$plan = $plan_with( array( array( $r1, 2 ), array( $r5, 2 ) ) );
$cost = $plan->calc_cost_per_serving();
$ok( 'counts as nothing, averaged over all servings', $near( $cost['cost'], 0.5 ), (string) $cost['cost'] );
$ok( 'and the result is a lower bound', false === $cost['complete'] );

echo "-- 5. A plan made of rows (planner)\n";
$rows = array(
	array( 'recipe_id' => $r1, 'servings' => 6, 'day' => 1, 'type' => 'dinner' ),
	array( 'recipe_id' => $r2, 'servings' => 1, 'day' => 2, 'type' => 'dinner' ),
);
$from_rows = Mixmymeals_Meal_Plan::from_meal_rows( $rows );
$cost      = $from_rows->calc_cost_per_serving();
$ok( 'same price as the saved plan', $near( $cost['cost'], 8 / 7 ) && $cost['complete'], wp_json_encode( $cost ) );

echo "-- 6. Nothing priced: no price\n";
$plan = $plan_with( array( array( $r5, 2 ) ) );
$cost = $plan->calc_cost_per_serving();
$ok( 'cost 0, not complete', 0.0 === (float) $cost['cost'] && false === $cost['complete'] );
$ok( 'no chip', ! isset( Mixmymeals_Shortcodes::meal_plan_stat_texts( $plan->get_id() )['price'] ) );

foreach ( array_reverse( $trash ) as $id ) {
	wp_delete_post( $id, true );
}

echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
