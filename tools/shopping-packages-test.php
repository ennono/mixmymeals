<?php
/**
 * What a shopping list is bought in (HG-115): product packages, units bought
 * whole, grams; mixed lines; the levels per place; the shopping costs.
 *
 * Runs against a real install, creates its own posts and deletes them again.
 * Everything it creates is titled "T …" or "R<n>", products carry the brand ZZ.
 * Options it touches are restored at the end.
 *
 *   wp eval-file wp-content/plugins/mixmymeals/tools/shopping-packages-test.php
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
$is    = function ( $label, $expected, $actual ) use ( $ok ) {
	$ok( $label, $expected === $actual, 'expected "' . $expected . '", got "' . $actual . '"' );
};
$near  = static fn( $a, $b ) => abs( (float) $a - (float) $b ) < 0.001;

switch_to_locale( 'de_DE' );
wp_set_current_user( 1 );

$amount  = 'Mixmymeals_Ingredient_Amount';
$options = array(
	$amount::MIXED_OPTION,
	Mixmymeals_Meal_Plan::SHOPPING_COST_BASIS_OPTION,
	$amount::LEVEL_OPTION_PREFIX . 'shopping_list',
	$amount::LEVEL_OPTION_PREFIX . 'ingredient_list',
	$amount::LEVEL_OPTION_PREFIX . 'teaser',
	$amount::LEVEL_OPTION_PREFIX . 'mail',
);
$saved   = array();
foreach ( $options as $option ) {
	$saved[ $option ] = get_option( $option, null );
	delete_option( $option );
}

$trash     = array();
$make_post = function ( array $args ) use ( &$trash ): int {
	$id      = (int) wp_insert_post( $args + array( 'post_status' => 'publish' ) );
	$trash[] = $id;
	return $id;
};
$nutrition = array(
	'calories_100' => 20,
	'carbs'        => 3,
	'protein'      => 1,
);
$ingredient = function ( string $title, ?float $price ) use ( $make_post, $nutrition ): int {
	$id  = $make_post( array( 'post_type' => 'mmm_ingredient', 'post_title' => $title ) );
	$obj = new Mixmymeals_Ingredient( $id );
	$obj->set_nutrition( $nutrition );
	if ( null !== $price ) {
		$obj->set_price_per_100g( $price );
	}
	return $id;
};
$product = function ( int $ingredient, string $title, float $package, ?float $price ) use ( $make_post ): int {
	$id  = $make_post( array( 'post_type' => 'mmm_product', 'post_title' => $title, 'post_parent' => $ingredient ) );
	$obj = new Mixmymeals_Product( $id );
	$obj->set_brand( 'ZZ' );
	$obj->set_package_weight( $package );
	$obj->set_price( $price );
	return $id;
};
$unit = function ( int $ingredient, string $title, float $weight, int $divisor, bool $package ) use ( &$trash ): int {
	$obj = Mixmymeals_Ingredient_Amount::create( $ingredient, $title );
	$obj->set_weight( $weight );
	$obj->set_divisor( $divisor );
	$obj->set_package( $package );
	$trash[] = $obj->get_id();
	return $obj->get_id();
};
$recipe = function ( string $title, array $rows ) use ( $make_post ): int {
	$id  = $make_post( array( 'post_type' => 'mmm_recipe', 'post_title' => $title ) );
	$obj = new Mixmymeals_Recipe( $id );
	$obj->set_servings( 2 );
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
$plan = function ( array $recipes ) use ( $make_post, &$trash ): Mixmymeals_Meal_Plan {
	$plan_id = $make_post( array( 'post_type' => 'mmm_meal_plan', 'post_title' => 'T Plan' ) );
	update_post_meta( $plan_id, 'start_day', 1 );
	update_post_meta( $plan_id, 'end_day', 7 );
	foreach ( $recipes as $i => $recipe_id ) {
		$meal = Mixmymeals_Meal::create( 'T Mahlzeit', $plan_id );
		$meal->set_recipe_id( $recipe_id );
		$meal->set_servings( 2 );
		$meal->set_day( $i + 1 );
		$trash[] = $meal->get_id();
	}
	return new Mixmymeals_Meal_Plan( $plan_id );
};
$line = static function ( Mixmymeals_Meal_Plan $plan, int $ingredient ): array {
	foreach ( $plan->calc_shopping_list( false ) as $lines ) {
		foreach ( $lines as $data ) {
			if ( $ingredient === (int) $data['ingredient_id'] ) {
				return $data;
			}
		}
	}
	return array();
};

echo "-- 1. Product with a package weight: its packages, rounded up once\n";
$tomato  = $ingredient( 'T Tomaten', 0.40 );
$mutti   = $product( $tomato, 'Tomaten', 400, 1.29 );
$cheap   = $product( $tomato, 'Billig', 0, null );
Mixmymeals_Product::set_reference_id( $tomato, $mutti );
$can     = $unit( $tomato, 'Dose', 400, 2, true );
$r1      = $recipe( 'R1', array( array( 'id' => $tomato, 'amount' => 300 ) ) );
$r2      = $recipe( 'R2', array( array( 'id' => $tomato, 'amount' => 0.5, 'amount_id' => $can ) ) );
$p1      = $plan( array( $r1, $r2 ) );
$l       = $line( $p1, $tomato );
$ok( 'kind product', 'product' === ( $l['package']['kind'] ?? '' ), wp_json_encode( $l['package'] ?? null ) );
$ok( '300 g + 200 g need two packages', 2 === ( $l['package']['count'] ?? 0 ) && $near( $l['total_weight'], 800 ) && $near( $l['needed_weight'], 500 ) );
$is( 'level 3', '2× Packung à 400 g (≈ 800 g)', $amount::format_line( $l, 'shopping_list' ) );
update_option( $amount::LEVEL_OPTION_PREFIX . 'shopping_list', '1' );
$is( 'level 1', '2× Packung', $amount::format_line( $l, 'shopping_list' ) );
update_option( $amount::LEVEL_OPTION_PREFIX . 'shopping_list', '2' );
$is( 'level 2', '2× Packung à 400 g', $amount::format_line( $l, 'shopping_list' ) );
update_option( $amount::LEVEL_OPTION_PREFIX . 'shopping_list', 'nonsense' );
$is( 'unknown level reads as the default', '2× Packung à 400 g (≈ 800 g)', $amount::format_line( $l, 'shopping_list' ) );
delete_option( $amount::LEVEL_OPTION_PREFIX . 'shopping_list' );
$ok( 'the reference is the line\'s product (packages, costs)', $mutti === (int) $l['product_id'] );
$ok( 'but no recipe chose it, so it is not named (4.18.2)', '' === $l['product'] && '' === Mixmymeals_Shortcodes::shopping_item_product_text( $l ) );
$ok( 'the amount does not name it a second time (4.18.1)', false === strpos( $amount::format_line( $l, 'shopping_list' ), 'ZZ' ) );

// One packet: "à 500 g" says the total, no "(≈ 500 g)" behind it (4.18.1).
$mince = $ingredient( 'T Hack', null );
$pack  = $product( $mince, 'Hackfleisch, gemischt', 500, null );
Mixmymeals_Product::set_reference_id( $mince, $pack );
$p0 = $plan( array( $recipe( 'R0', array( array( 'id' => $mince, 'amount' => 500 ) ) ) ) );
$is( 'one packet, level 3', '1× Packung à 500 g', $amount::format_line( $line( $p0, $mince ), 'shopping_list' ) );
$bring = $p1->get_bring_shopping_list_lines();
$ok( 'Bring!: the bought weight, no brand', in_array( '800 g T Tomaten', $bring, true ), implode( ' | ', $bring ) );
$ok( 'Bring!: the brand appears nowhere', false === strpos( implode( ' ', $bring ), 'ZZ' ) );

echo "-- 2. Pin outranks the reference; a product without package weight falls through\n";
$r3 = $recipe( 'R3', array( array( 'id' => $tomato, 'amount' => 300, 'product_id' => $cheap ) ) );
$p2 = $plan( array( $r3 ) );
$l  = $line( $p2, $tomato );
$ok( 'pinned product named on the line', $cheap === (int) $l['product_id'] && 'ZZ Billig' === $l['product'] );
$ok( 'no package weight → the unit bought whole', 'unit' === ( $l['package']['kind'] ?? '' ) && $can === (int) $l['package']['unit_id'], wp_json_encode( $l['package'] ?? null ) );
$is( 'plus mode: a gram line stays grams', '300 g', $amount::format_line( $l, 'shopping_list' ) );
update_option( $amount::MIXED_OPTION, $amount::MIXED_ROUND_UP );
$l = $line( $p2, $tomato );
$is( 'round-up mode: one tin', '1× Dose à 400 g', $amount::format_line( $l, 'shopping_list' ) );
delete_option( $amount::MIXED_OPTION );

echo "-- 3. Recipes disagree on the packet: no product package\n";
$r4 = $recipe( 'R4', array( array( 'id' => $tomato, 'amount' => 300, 'product_id' => $mutti ) ) );
$p3 = $plan( array( $r3, $r4 ) );
$l  = $line( $p3, $tomato );
$ok( 'no product on the line', 0 === (int) $l['product_id'] && '' === $l['product'] );
$ok( 'and no product package', 'product' !== $l['package']['kind'] && false === strpos( $amount::format_line( $l, 'shopping_list' ), 'ZZ' ), $amount::format_line( $l, 'shopping_list' ) );

echo "-- 4. Units bought whole, mixed lines\n";
$beans = $ingredient( 'T Bohnen', null );
$tin   = $unit( $beans, 'Dose', 400, 2, true );
$spoon = $unit( $beans, 'EL', 15, 0, false );
$r5    = $recipe( 'R5', array( array( 'id' => $beans, 'amount' => 1.5, 'amount_id' => $tin ) ) );
$r6    = $recipe( 'R6', array( array( 'id' => $beans, 'amount' => 100 ) ) );
$p4    = $plan( array( $r5, $r6 ) );
$l     = $line( $p4, $beans );
$is( 'plus: both, with "à" for the package', '1½× Dose à 400 g + 100 g (≈ 700 g)', $amount::format_line( $l, 'shopping_list' ) );
update_option( $amount::MIXED_OPTION, $amount::MIXED_ROUND_UP );
$l = $line( $p4, $beans );
$is( 'round up: whole tins', '2× Dose à 400 g (≈ 800 g)', $amount::format_line( $l, 'shopping_list' ) );
$ok( 'the purchase itself changed', $near( $l['total_weight'], 800 ) && $near( $l['needed_weight'], 700 ) );
$bring = $p4->get_bring_shopping_list_lines();
$ok( 'Bring! follows the rounded number', in_array( '2 Dose T Bohnen', $bring, true ), implode( ' | ', $bring ) );
delete_option( $amount::MIXED_OPTION );

$r7 = $recipe( 'R7', array( array( 'id' => $beans, 'amount' => 2, 'amount_id' => $spoon ) ) );
$p5 = $plan( array( $r7 ) );
$l  = $line( $p5, $beans );
$is( 'a measure never gets "à"', '30 g', $amount::format_line( $l, 'shopping_list' ) );

echo "-- 5. No package at all: grams\n";
$salt = $ingredient( 'T Salz', null );
$r8   = $recipe( 'R8', array( array( 'id' => $salt, 'amount' => 1500 ) ) );
$p6   = $plan( array( $r8 ) );
$l    = $line( $p6, $salt );
$ok( 'kind none', 'none' === $l['package']['kind'] );
foreach ( array( '1', '2', '3' ) as $level ) {
	update_option( $amount::LEVEL_OPTION_PREFIX . 'shopping_list', $level );
	$is( "level $level: only the weight", '1,5 kg', $amount::format_line( $l, 'shopping_list' ) );
}
delete_option( $amount::LEVEL_OPTION_PREFIX . 'shopping_list' );

echo "-- 6. Ingredient lists never round, levels apply\n";
$html = static fn( $id ) => preg_replace( '/\s+/', ' ', wp_strip_all_tags( do_shortcode( '[mmm_recipe_ingredients post_id="' . $id . '"]' ) ) );
$ok( 'level 1 (default): the amount', false !== strpos( $html( $r5 ), '1½× Dose' ) && false === strpos( $html( $r5 ), 'à' ), $html( $r5 ) );
update_option( $amount::LEVEL_OPTION_PREFIX . 'ingredient_list', '2' );
$ok( 'level 2: with the package weight', false !== strpos( $html( $r5 ), '1½× Dose à 400 g' ), $html( $r5 ) );
update_option( $amount::LEVEL_OPTION_PREFIX . 'ingredient_list', '3' );
$ok( 'level 3: and the weight', false !== strpos( $html( $r5 ), '1½× Dose à 400 g (≈ 600 g)' ), $html( $r5 ) );
$r9 = $recipe( 'R9', array( array( 'id' => $beans, 'amount' => 1, 'amount_id' => $tin ) ) );
$ok( 'level 3, one tin: no "(≈ …)" (4.18.1)', false !== strpos( $html( $r9 ), '1× Dose à 400 g' ) && false === strpos( $html( $r9 ), '≈' ), $html( $r9 ) );
$ok( 'a product never rounds an ingredient list', false !== strpos( $html( $r1 ), '300 g' ) && false === strpos( $html( $r1 ), '800' ), $html( $r1 ) );
delete_option( $amount::LEVEL_OPTION_PREFIX . 'ingredient_list' );

echo "-- 7. Teaser\n";
$l = $line( $p1, $tomato );
$is( 'short by default', '2× Packung', Mixmymeals_Meal_Plan::build_short_amount_string( $l['units'], $l['total_weight'] ) );
update_option( $amount::LEVEL_OPTION_PREFIX . 'teaser', '2' );
$is( 'or a level', '2× Packung à 400 g', Mixmymeals_Meal_Plan::build_short_amount_string( $l['units'], $l['total_weight'] ) );
delete_option( $amount::LEVEL_OPTION_PREFIX . 'teaser' );

echo "-- 8. Shopping costs\n";
// p1: tomatoes, 2 packages of ZZ Tomaten at 1,29 € = 2,58 € bought; needed
// 500 g at the product's 1,29 € / 400 g = 1,6125 €.
$cost = $p1->calc_shopping_cost();
$ok( 'bought (default): package price × count', $near( $cost['cost'], 2.58 ) && $cost['complete'] && 'bought' === $cost['basis'], wp_json_encode( $cost ) );
update_option( Mixmymeals_Meal_Plan::SHOPPING_COST_BASIS_OPTION, 'needed' );
$cost = $p1->calc_shopping_cost();
$ok( 'needed: what the recipes use', $near( $cost['cost'], 500 / 400 * 1.29 ) && $cost['complete'], wp_json_encode( $cost ) );
delete_option( Mixmymeals_Meal_Plan::SHOPPING_COST_BASIS_OPTION );

$cost = $p4->calc_shopping_cost();
$ok( 'beans have no price: a gap, nothing to sum', 0.0 === (float) $cost['cost'] && false === $cost['complete'], wp_json_encode( $cost ) );

$p7   = $plan( array( $r1, $r6 ) );
$cost = $p7->calc_shopping_cost();
$ok( 'one line priced, one not: a lower bound', $cost['cost'] > 0 && false === $cost['complete'], wp_json_encode( $cost ) );
$list = $p7->calc_shopping_list();
$out  = Mixmymeals_Shortcodes::shopping_cost_html( $p7, $list );
$ok( 'printed as "ab …"', false !== strpos( $out, 'Einkaufskosten: ab 1,29 €' ), $out );
$out = Mixmymeals_Shortcodes::shopping_cost_html( $p1, $p1->calc_shopping_list() );
$ok( 'complete: without "ab"', false !== strpos( $out, 'Einkaufskosten: 2,58 €' ) && false === strpos( $out, ' ab ' ), $out );
$ok( 'unpriced plan prints nothing', '' === Mixmymeals_Shortcodes::shopping_cost_html( $p4, $p4->calc_shopping_list() ) );

$offset = $p1->with_servings_offset( 2 )->calc_shopping_cost();
$ok( '?mmm_persons_to_add: more servings, more packages', $offset['cost'] > 2.58, wp_json_encode( $offset ) );
$rows = Mixmymeals_Meal_Plan::from_meal_rows(
	array(
		array( 'recipe_id' => $r1, 'servings' => 2, 'day' => 1, 'type' => 'dinner' ),
		array( 'recipe_id' => $r2, 'servings' => 2, 'day' => 2, 'type' => 'dinner' ),
	)
);
$ok( 'planner rows: the same costs', $near( $rows->calc_shopping_cost()['cost'], 2.58 ) );

echo "-- 9. The price per serving stays with the need\n";
$ok( 'calc_cost_per_serving() ignores packages', $near( $p1->calc_cost_per_serving()['cost'], 500 / 400 * 1.29 / 4 ), wp_json_encode( $p1->calc_cost_per_serving() ) );

foreach ( $saved as $option => $value ) {
	if ( null === $value ) {
		delete_option( $option );
	} else {
		update_option( $option, $value );
	}
}
foreach ( array_reverse( $trash ) as $id ) {
	wp_delete_post( $id, true );
}

echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
