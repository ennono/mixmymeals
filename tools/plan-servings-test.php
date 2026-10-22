<?php
/**
 * Servings on the plan page (?mmm_persons_to_add): the shopping list computed from the
 * plan's rows, the one difference applied to every meal, rounding over the
 * sum, the per-person figures untouched, and the parameter checked in one
 * place.
 *
 * Runs against a real install, creates its own posts and deletes them again.
 * Everything it creates is titled "T …", the product carries the brand ZZ.
 *
 *   wp --exec="define('DB_HOST','localhost:<socket>');" \
 *      eval-file wp-content/plugins/mixmymeals/tools/plan-servings-test.php
 *
 * @package MixMyMeals
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$fails = 0;
$ok    = function ( $label, $cond ) use ( &$fails ) {
	if ( ! $cond ) {
		$fails++;
	}
	echo ( $cond ? 'ok   ' : 'FAIL ' ) . $label . PHP_EOL;
};

$trash = array();
$make_post = function ( array $args ) use ( &$trash ): int {
	$id      = (int) wp_insert_post( $args + array( 'post_status' => 'publish' ) );
	$trash[] = $id;
	return $id;
};

/**
 * calc_shopping_list() as it stood in 3.65.1, reading the meal posts
 * directly. Frozen here as the oracle the rewrite onto get_meal_rows() is
 * measured against; do not update it along with the class.
 */
function mmm_ps_legacy_shopping_list( int $plan_id, bool $sort_by_category = true, ?int $day = null ): array {

	$meals = ( new Mixmymeals_Meal_Plan( $plan_id ) )->get_meals();

	if ( empty( $meals ) ) {
		return [];
	}

	$ingredient_totals = [];

	foreach ( $meals as $meal_post ) {

		$meal = new Mixmymeals_Meal( $meal_post->ID );

		if ( null !== $day && (int) $meal->get_day() !== $day ) {
			continue;
		}

		$recipe_id = $meal->get_recipe_id();

		if ( ! $recipe_id ) {
			continue;
		}

		$recipe = new Mixmymeals_Recipe( $recipe_id );

		$recipe_servings = max( 1.0, floatval( $recipe->get_servings() ) );
		$meal_servings   = max( 0.01, floatval( $meal->get_servings() ) );
		$scale           = $meal_servings / $recipe_servings;

		// 🔥 ZENTRALE LOGIK
		$ingredients = $recipe->get_ingredients_scaled( $scale );

		foreach ( $ingredients as $ing ) {

			if ( empty( $ing['id'] ) ) {
				continue;
			}

			$ing_id = intval( $ing['id'] );

			$ingredient_obj = new Mixmymeals_Ingredient( $ing_id );
			if ( ! $ingredient_obj->is_valid() ) {
				continue;
			}

			$amount_id       = intval( $ing['amount_id'] ?? 0 );
			$raw_amount      = floatval( $ing['raw_amount'] ?? $ing['amount'] ?? 0 );
			$weight_per_unit = floatval( $ing['weight_per_unit'] ?? 0 );
			$unit            = $ing['unit'] ?? '';

			if ( ! isset( $ingredient_totals[ $ing_id ] ) ) {
				$ingredient_totals[ $ing_id ] = [
					'details'      => [],
					'units'        => [],
					'total_weight' => 0,
					// Optional stays true only while every use of this
					// ingredient across the plan is optional; a single
					// required use flips it back to a normal purchase.
					'optional'     => true,
					// Every packet this plan's rows resolve to. One entry
					// means the list can name it; several mean the recipes
					// want different ones and naming either would send the
					// shopper home with the wrong article for one of them.
					'products'     => [],
					'product_id'   => 0,
					'product'      => '',
				];
			}

			$row_product = Mixmymeals_Ingredient::effective_product_id(
				$ing_id,
				(int) ( $ing['product_id'] ?? 0 )
			);

			$ingredient_totals[ $ing_id ]['products'][ $row_product ] = true;

			$ingredient_totals[ $ing_id ]['optional'] =
				$ingredient_totals[ $ing_id ]['optional'] && ! empty( $ing['optional'] );

			// Rohbedarf je Einheit sammeln; unteilbare Einheiten werden
			// erst nach der Aggregation über alle Mahlzeiten gerundet.
			if ( ! isset( $ingredient_totals[ $ing_id ]['units'][ $amount_id ] ) ) {
				$ingredient_totals[ $ing_id ]['units'][ $amount_id ] = [
					'raw_amount'      => 0.0,
					'weight_per_unit' => $weight_per_unit,
					'is_indivisible'  => ! empty( $ing['is_indivisible'] ),
					'step_divisor'    => (int) ( $ing['step_divisor'] ?? 0 ),
					'unit_title'      => $unit,
				];
			}
			$ingredient_totals[ $ing_id ]['units'][ $amount_id ]['raw_amount'] += $raw_amount;

			$ingredient_totals[ $ing_id ]['details'][] = [
				'amount'      => $raw_amount,
				'unit_title'  => $unit,
				'weight_g'    => $weight_per_unit > 0
					? $raw_amount * $weight_per_unit
					: $raw_amount,
				'recipe_name' => get_the_title( $recipe_id ),
			];
		}
	}

	if ( empty( $ingredient_totals ) ) {
		return [];
	}

	/*
	 * Round up once over the whole requirement, not per meal — buying two
	 * tins for two half-tin recipes when one would do is exactly the error
	 * a shopping list must not make. Rounded up rather than to nearest,
	 * because at the shop too little is worse than too much.
	 *
	 * The step is what the unit can be split into: whole ones when it is
	 * indivisible, halves at a divisor of 2. So half a tin needed becomes
	 * half a tin bought, not a whole one.
	 */
	foreach ( $ingredient_totals as &$data ) {
		$total = 0.0;
		foreach ( $data['units'] as &$unit_data ) {
			$amount  = $unit_data['raw_amount'];
			$divisor = (int) ( $unit_data['step_divisor'] ?? 0 );

			if ( $divisor > 0 ) {
				$amount = max( 1.0, ceil( $amount * $divisor ) ) / $divisor;
			}

			$weight = $unit_data['weight_per_unit'] > 0
				? $amount * $unit_data['weight_per_unit']
				: $amount;

			$unit_data['amount']   = $amount;
			$unit_data['weight_g'] = $weight;

			$total += $weight;
		}
		unset( $unit_data );
		$data['total_weight'] = $total;

		/*
		 * Name the article only where the whole plan agrees on it. With
		 * nothing pinned anywhere that is simply the ingredient's reference
		 * product, which is what this list has always shown; with a recipe
		 * pinning one it is that packet, because that is what the dish is
		 * made of. Disagreement prints nothing rather than one of the two.
		 */
		$products           = array_keys( $data['products'] );
		$data['product_id'] = 1 === count( $products ) ? (int) $products[0] : 0;
		$data['product']    = $data['product_id'] > 0
			? ( new Mixmymeals_Product( $data['product_id'] ) )->label()
			: '';

		unset( $data['products'] );
	}
	unset( $data );

	// 👉 Gruppieren nach Kategorie
	$ingredients_by_cat = [];

	if ( $sort_by_category ) {

		foreach ( $ingredient_totals as $ing_id => $data ) {

			$terms = wp_get_post_terms( $ing_id, 'mmm_ingredient_category' );
			if ( is_wp_error( $terms ) ) {
				$terms = [];
			}

			$cat_name = ! empty( $terms )
				? $terms[0]->name
				: __( 'Other', 'mixmymeals' );

			$ingredients_by_cat[ $cat_name ][ $ing_id ] = $data;
		}

		ksort( $ingredients_by_cat, SORT_NATURAL | SORT_FLAG_CASE );

	} else {
		$ingredients_by_cat[ __( 'All Ingredients', 'mixmymeals' ) ] = $ingredient_totals;
	}

	return $ingredients_by_cat;
}

/**
 * Grams and amounts of one ingredient on a list, wherever it is grouped.
 */
$row_of = function ( array $list, int $ingredient_id ): array {
	foreach ( $list as $rows ) {
		if ( isset( $rows[ $ingredient_id ] ) ) {
			return $rows[ $ingredient_id ];
		}
	}
	return array();
};

/**
 * Servings per meal ID of a plan.
 */
$servings_of = function ( Mixmymeals_Meal_Plan $plan ): array {
	return array_map( 'intval', array_column( $plan->get_meal_rows(), 'servings', 'meal_id' ) );
};

wp_set_current_user( 1 );

try {

	echo '-- Setup' . PHP_EOL;

	$carrot = $make_post( array( 'post_type' => 'mmm_ingredient', 'post_title' => 'T Möhre' ) );
	( new Mixmymeals_Ingredient( $carrot ) )->set_nutrition( array( 'calories_100' => 36, 'fat' => 0.2, 'carbs' => 7, 'protein' => 0.8, 'salt' => 0.1 ) );

	$tomato = $make_post( array( 'post_type' => 'mmm_ingredient', 'post_title' => 'T Tomaten' ) );
	( new Mixmymeals_Ingredient( $tomato ) )->set_nutrition( array( 'calories_100' => 20, 'fat' => 0.2, 'carbs' => 3, 'protein' => 1, 'salt' => 0.3 ) );

	$tin = Mixmymeals_Ingredient_Amount::create( $tomato, 'T Dose' );
	$trash[] = $tin->get_id();
	$tin->set_weight( 400.0 );
	$tin->set_divisor( 1 ); // Whole tins only.

	$packet = $make_post( array( 'post_type' => 'mmm_product', 'post_title' => 'T Dosentomaten', 'post_parent' => $tomato ) );
	( new Mixmymeals_Product( $packet ) )->set_brand( 'ZZ' );
	Mixmymeals_Product::set_reference_id( $tomato, $packet );

	$make_recipe = function ( string $title, float $carrot_g ) use ( $make_post, $carrot, $tomato, $tin ): int {
		$id     = $make_post( array( 'post_type' => 'mmm_recipe', 'post_title' => $title ) );
		$recipe = new Mixmymeals_Recipe( $id );
		$recipe->set_servings( 4 );
		$recipe->set_steps(
			array(
				array(
					'title'       => 'T',
					'description' => 'x',
					'ingredients' => array(
						array( 'id' => $tomato, 'amount' => 1, 'amount_id' => $tin->get_id() ),
						array( 'id' => $carrot, 'amount' => $carrot_g, 'amount_id' => 0 ),
					),
				),
			)
		);
		return $id;
	};

	$r1 = $make_recipe( 'T Eintopf', 400 );
	$r2 = $make_recipe( 'T Suppe', 200 );

	$make_plan = function ( string $title, array $meals ) use ( $make_post ): int {
		$plan_id = $make_post( array( 'post_type' => 'mmm_meal_plan', 'post_title' => $title ) );
		update_post_meta( $plan_id, 'start_day', 1 );
		update_post_meta( $plan_id, 'end_day', 3 );
		foreach ( $meals as $m ) {
			$meal = Mixmymeals_Meal::create( 'T Mahlzeit', $plan_id );
			$meal->set_recipe_id( $m[0] );
			$meal->set_servings( $m[1] );
			$meal->set_day( $m[2] );
			$meal->set_type( $m[3] );
		}
		Mixmymeals_Meal_Plan::flush_meals_cache( $plan_id );
		return $plan_id;
	};

	// Mixed: Monday 3, Tuesday 4, Wednesday 9.
	$mixed_id = $make_plan(
		'T Gemischter Plan',
		array(
			array( $r1, 3, 1, 'dinner' ),
			array( $r2, 4, 2, 'dinner' ),
			array( $r1, 9, 3, 'dinner' ),
		)
	);
	// Uniform: one person at two meals on Monday, each recipe one tin for four.
	$tins_id = $make_plan(
		'T Dosenplan',
		array(
			array( $r1, 1, 1, 'lunch' ),
			array( $r2, 1, 1, 'dinner' ),
		)
	);

	$mixed = new Mixmymeals_Meal_Plan( $mixed_id );
	$tins  = new Mixmymeals_Meal_Plan( $tins_id );

	echo '-- 1. The list from rows equals the list from posts' . PHP_EOL;
	foreach ( array( $mixed_id, $tins_id ) as $plan_id ) {
		$plan = new Mixmymeals_Meal_Plan( $plan_id );
		foreach ( array( true, false ) as $sort ) {
			$ok(
				"plan $plan_id, " . ( $sort ? 'by category' : 'flat' ) . ': same as 3.65.1, byte for byte',
				serialize( mmm_ps_legacy_shopping_list( $plan_id, $sort ) ) === serialize( $plan->calc_shopping_list( $sort ) )
			);
		}
		$ok( "plan $plan_id, one day: same as 3.65.1", serialize( mmm_ps_legacy_shopping_list( $plan_id, true, 1 ) ) === serialize( $plan->calc_shopping_list( true, 1 ) ) );
	}
	$ok( 'the packet is still named on the line', 'ZZ T Dosentomaten' === ( $row_of( $mixed->calc_shopping_list(), $tomato )['product'] ?? '' ) );

	echo '-- 2. Offset 0 is the stored plan' . PHP_EOL;
	$ok( 'mixed: list unchanged', serialize( $mixed->calc_shopping_list() ) === serialize( $mixed->with_servings_offset( 0 )->calc_shopping_list() ) );
	$ok( 'tins: list unchanged', serialize( $tins->calc_shopping_list() ) === serialize( $tins->with_servings_offset( 0 )->calc_shopping_list() ) );
	$ok( 'the derived plan keeps the plan\'s ID', $mixed_id === $mixed->with_servings_offset( 0 )->get_id() );
	$ok( 'and its span', $mixed->get_day_range() === $mixed->with_servings_offset( 2 )->get_day_range() );

	echo '-- 3. The same number at every meal' . PHP_EOL;
	$ok( 'stored 3/4/9', array( 3, 4, 9 ) === array_values( $servings_of( $mixed ) ) );
	$ok( '+1 gives 4/5/10', array( 4, 5, 10 ) === array_values( $servings_of( $mixed->with_servings_offset( 1 ) ) ) );
	$ok( '−5 gives 1/1/4: never below one', array( 1, 1, 4 ) === array_values( $servings_of( $mixed->with_servings_offset( -5 ) ) ) );
	$ok( 'meal IDs survive the derivation', array_keys( $servings_of( $mixed ) ) === array_keys( $servings_of( $mixed->with_servings_offset( 1 ) ) ) );
	$ok( 'an addition stops at ' . Mixmymeals_Meal_Plan::SERVINGS_MEAL_MAX . ' per meal', Mixmymeals_Meal_Plan::SERVINGS_MEAL_MAX === max( $servings_of( $mixed->with_servings_offset( 200 ) ) ) );
	$ok( 'nothing written', array( 3, 4, 9 ) === array_values( $servings_of( new Mixmymeals_Meal_Plan( $mixed_id ) ) ) );

	// Carrots in grams: (400·4 + 200·5 + 400·10) / 4 = 1650 g at +1.
	$ok( 'grams scale with the servings', abs( 1650.0 - (float) ( $row_of( $mixed->with_servings_offset( 1 )->calc_shopping_list(), $carrot )['total_weight'] ?? 0 ) ) < 0.001 );

	$by_day = $mixed->with_servings_offset( 1 )->servings_by_day();
	$ok( 'per weekday, in plan order', array( 1, 2, 3 ) === array_keys( $by_day ) && 10 === $by_day[3]['max'] );
	$ok( 'mixed plan has no base', 0 === $mixed->servings_base() );
	$ok( 'uniform plan has one', 1 === $tins->servings_base() );
	$ok( 'a day of 2 and 4 reads as a range', Mixmymeals_Meal_Plan::format_people( 2, 4 ) === sprintf( __( '%1$s–%2$s people', 'mixmymeals' ), '2', '4' ) );

	echo '-- 4. Packs are rounded over the sum, not per meal' . PHP_EOL;
	$tin_amount = function ( Mixmymeals_Meal_Plan $plan ) use ( $row_of, $tomato ): float {
		$units = $row_of( $plan->calc_shopping_list(), $tomato )['units'] ?? array();
		return (float) ( reset( $units )['amount'] ?? 0 );
	};
	$ok( 'stored: ¼ + ¼ tin → 1 tin', 1.0 === $tin_amount( $tins ) );
	$ok( '+1: ½ + ½ tin → still 1 tin, not 2', 1.0 === $tin_amount( $tins->with_servings_offset( 1 ) ) );
	$ok( '+2: ¾ + ¾ tin → 2 tins', 2.0 === $tin_amount( $tins->with_servings_offset( 2 ) ) );

	echo '-- 5. Per-person figures do not depend on the number of people' . PHP_EOL;
	foreach ( array( 1, 5, -5 ) as $offset ) {
		$shown = $mixed->with_servings_offset( $offset );
		$ok( "per day and person at $offset", $mixed->calc_nutrition_per_day_person() == $shown->calc_nutrition_per_day_person() );
		$ok( "per 100 g at $offset", $mixed->calc_nutrition_per_100g() == $shown->calc_nutrition_per_100g() );
		$ok( "day rating at $offset", $mixed->calc_day_rating() == $shown->calc_day_rating() );
	}
	$ok( 'the totals do move', $mixed->calc_nutrition()['totals']['calories'] < $mixed->with_servings_offset( 1 )->calc_nutrition()['totals']['calories'] );

	echo '-- 6. ?mmm_persons_to_add is checked in one place' . PHP_EOL;
	$ok( 'the parameter carries the plugin prefix', 'mmm_persons_to_add' === Mixmymeals_Meal_Plan::SERVINGS_QUERY_VAR );
	$ok( 'text is ignored', null === $mixed->parse_servings_value( 'viele' ) );
	$ok( 'a plain number is a difference, not a count', 4 === $mixed->parse_servings_value( '4' ) && 2 === $tins->parse_servings_value( '2' ) );
	$ok( '+1 is one more', 1 === $mixed->parse_servings_value( '+1' ) );
	$ok( 'a "+" decoded to a space is still one more', 1 === $mixed->parse_servings_value( ' 1' ) );
	$ok( '-5 is five fewer', -5 === $mixed->parse_servings_value( '-5' ) );
	$ok( 'U+2212 is a minus', -2 === $mixed->parse_servings_value( "\u{2212}2" ) );
	$ok( '0 is nothing', 0 === $mixed->parse_servings_value( '0' ) );
	$ok( 'below the point where everything is at one: ignored', null === $mixed->parse_servings_value( '-9' ) );
	$ok( 'above the cap: ignored', null === $mixed->parse_servings_value( '+' . ( Mixmymeals_Meal_Plan::SERVINGS_OFFSET_MAX + 1 ) ) );
	$ok( 'a uniform plan cannot drop below one either', null === $tins->parse_servings_value( '-1' ) );
	$ok( 'the address carries the difference, without a "+"', '1' === $mixed->servings_query_value( 1 ) && '2' === $tins->servings_query_value( 2 ) && '-2' === $mixed->servings_query_value( -2 ) );
	$ok( 'in an address', false !== strpos( $mixed->add_servings_arg( 'https://example.org/plan/', 1 ), 'mmm_persons_to_add=1' ) );
	$ok( 'offset 0 removes the parameter', false === strpos( $mixed->add_servings_arg( 'https://example.org/plan/?mmm_persons_to_add=1', 0 ), 'mmm_persons_to_add' ) );

	$_GET[ Mixmymeals_Meal_Plan::SERVINGS_QUERY_VAR ] = 'viele';
	$ok( 'request: text gives 0', 0 === $mixed->requested_servings_offset() );
	$ok( 'but the page still carries the parameter (noindex)', Mixmymeals_Meal_Plan::has_servings_param() );
	$_GET[ Mixmymeals_Meal_Plan::SERVINGS_QUERY_VAR ] = '-30';
	$ok( 'request: out of bounds gives 0', 0 === $mixed->requested_servings_offset() );
	$_GET[ Mixmymeals_Meal_Plan::SERVINGS_QUERY_VAR ] = ' 1'; // "+1" as PHP decodes it.
	$ok( 'request: +1', 1 === $mixed->requested_servings_offset() );

	echo '-- 6b. The change is appended, per meal, per day, per plan' . PHP_EOL;
	$changes = $mixed->servings_changes( -5 );
	$ok( '−5 on 3/4/9 changes the meals by −2/−3/−5', array( -2, -3, -5 ) === array_values( $changes['meals'] ) );
	$ok( 'the plan by −5 … −2', array( 'min' => -5, 'max' => -2 ) === $changes['plan'] );
	$ok( 'written with U+2212 and a range', "\u{2212}5 … \u{2212}2" === Mixmymeals_Meal_Plan::format_servings_delta( -5, -2 ) );
	$ok( '+1 reads "+1"', '+1' === Mixmymeals_Meal_Plan::format_servings_delta( 1, 1 ) );
	$ok( 'no change, nothing appended', '' === Mixmymeals_Meal_Plan::format_servings_delta( 0, 0 ) && array( 0, 0, 0 ) === array_values( $mixed->servings_changes( 0 )['meals'] ) );

	echo '-- 7. What the page shows under ?mmm_persons_to_add=1' . PHP_EOL;
	$shortcodes = new Mixmymeals_Shortcodes();

	$html = $shortcodes->mmm_shopping_list_for_a_single_meal_plan_callback( array( 'meal_plan_id' => $mixed_id ) );
	$ok( 'the field sits above the list', false !== strpos( $html, 'mmm-plan-servings--mixed' ) && strpos( $html, 'mmm-plan-servings' ) < strpos( $html, 'mmm-simple-shopping-list' ) );
	$ok( 'one quiet line: no form, no field, no list of days', false === strpos( $html, '<form' ) && false === strpos( $html, '<input' ) && false === strpos( $html, 'mmm-plan-servings__days' ) );
	$ok( 'the value is the difference', false !== strpos( $html, '<output class="mmm-plan-servings__value">+1</output>' ) );
	$ok( 'and the link into the planner', false !== strpos( $html, 'mmm-meal-plan-actions__item--load-into-cart mmm-plan-servings__planner-link' ) );
	$ok( 'the list is the +1 list', false !== strpos( $html, esc_html( Mixmymeals_Meal_Plan::build_needed_amount_string( $row_of( $mixed->with_servings_offset( 1 )->calc_shopping_list(), $carrot )['units'], 1650.0 ) ) ) );

	$rail = $shortcodes->mmm_meals_in_a_single_meal_plan_callback( array( 'meal_plan_id' => $mixed_id ) );
	$ok( 'meal cards link with the difference', false !== strpos( $rail, 'mmm_persons_to_add=1' ) );
	$ok( 'each day says its people', 3 === substr_count( $rail, 'mmm-rail-day__servings' ) );
	$ok( 'cards and days keep the stored number and append "+1"', 6 === substr_count( $rail, '<span class="mmm-servings-delta">+1</span>' ) );
	$ok( 'the stored number stays', false !== strpos( $rail, '>' . esc_html( Mixmymeals_Meal_Plan::format_people( 3, 3 ) ) . ' <span class="mmm-servings-delta">+1</span>' ) );

	$ok( 'Bring! is handed the parameter', false !== strpos( Mixmymeals_Meal_Plan::bring_deeplink( $mixed_id, 1 ), rawurlencode( 'mmm_persons_to_add=1' ) ) );

	$meals = $mixed->get_meal_rows();
	$ok( 'a meal page shows its share: 3 → 4', 4 === ( new Mixmymeals_Meal( $meals[0]['meal_id'] ) )->get_shown_servings() );
	$_GET[ Mixmymeals_Meal_Plan::SERVINGS_QUERY_VAR ] = '-5';
	$ok( 'and −5 keeps it at one', 1 === ( new Mixmymeals_Meal( $meals[0]['meal_id'] ) )->get_shown_servings() );
	$_GET[ Mixmymeals_Meal_Plan::SERVINGS_QUERY_VAR ] = 'viele';
	$ok( 'an invalid value shows the stored servings', 3 === ( new Mixmymeals_Meal( $meals[0]['meal_id'] ) )->get_shown_servings() );

	unset( $_GET[ Mixmymeals_Meal_Plan::SERVINGS_QUERY_VAR ] );

	$uniform = $shortcodes->mmm_shopping_list_for_a_single_meal_plan_callback( array( 'meal_plan_id' => $tins_id ) );
	$ok( 'a uniform plan shows the count', false !== strpos( $uniform, 'mmm-plan-servings--uniform' ) && false !== strpos( $uniform, '<output class="mmm-plan-servings__value">1</output>' ) );
	$ok( 'and nothing appended without a difference', false === strpos( $shortcodes->mmm_meals_in_a_single_meal_plan_callback( array( 'meal_plan_id' => $tins_id ) ), '<span class="mmm-servings-delta">' ) );
	$ok( 'and no day labels on its rail', false === strpos( $shortcodes->mmm_meals_in_a_single_meal_plan_callback( array( 'meal_plan_id' => $tins_id ) ), 'mmm-rail-day__servings' ) );

} finally {
	unset( $_GET[ Mixmymeals_Meal_Plan::SERVINGS_QUERY_VAR ] );
	foreach ( $trash as $id ) {
		foreach ( get_children( array( 'post_parent' => $id, 'post_type' => 'mmm_meal', 'post_status' => 'any' ) ) as $child ) {
			wp_delete_post( $child->ID, true );
		}
	}
	foreach ( array_reverse( $trash ) as $id ) {
		wp_delete_post( $id, true );
	}
}

echo PHP_EOL . ( $fails ? "$fails FAILED" : 'all passed' ) . PHP_EOL;
