<?php
/**
 * Eaters (3.70.0): a plan's people, who of them eats which meal, and the
 * figures "per day and person" asked per person — through for_eater(), the
 * one method, never a copy of the calculation.
 *
 * Checks that a plan without people computes exactly what it computed
 * before (against a frozen copy of the 3.69.0 rule), that a breakfast for
 * one person counts only for them, that each person is divided by their own
 * days, that portions per person (usual, per meal, the step setting) scale
 * that person's figures and nothing else, that "too little" needs a whole day of that person, that the
 * shopping list ignores eaters entirely, the round trips through the two
 * meta-box callbacks, the planner and the plan page printing one table per
 * person byte for byte, and that no name an editor gave reaches public
 * markup, the guest serialization, the schema, the planner or a shared link.
 *
 * Runs against a real install, creates its own posts and deletes them again.
 * Everything it creates is titled "T …", the product carries the brand ZZ.
 *
 *   wp --exec="define('DB_HOST','localhost:<socket>');" \
 *      eval-file wp-content/plugins/mixmymeals/tools/plan-eaters-test.php
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

$trash     = array();
$make_post = function ( array $args ) use ( &$trash ): int {
	$id      = (int) wp_insert_post( $args + array( 'post_status' => 'publish' ) );
	$trash[] = $id;
	return $id;
};

/**
 * calc_nutrition_per_day_person() as it stood in 3.69.0, reading the meal
 * posts directly: one serving of every meal, averaged over the days that
 * carry meals. Frozen as the oracle; do not update it along with the class.
 */
function mmm_pe_legacy_per_day_person( int $plan_id ): array {

	$plan = new Mixmymeals_Meal_Plan( $plan_id );
	$keys = Mixmymeals_Meal_Plan::NUTRITION_KEYS;
	$sum  = array_fill_keys( $keys, 0.0 );
	$days = 0;

	foreach ( $plan->get_day_range() as $day ) {

		$per_person = array_fill_keys( $keys, 0.0 );
		$meals      = 0;

		foreach ( $plan->get_meals() as $meal_post ) {

			$meal = new Mixmymeals_Meal( $meal_post->ID );

			if ( (int) $meal->get_day() !== (int) $day ) {
				continue;
			}

			$recipe = $meal->get_recipe();

			if ( ! $recipe->is_valid() ) {
				continue;
			}

			$per_serving = $recipe->calculate_nutrition_per_serving();
			++$meals;

			foreach ( $keys as $key ) {
				$per_person[ $key ] += (float) ( $per_serving[ $key ] ?? 0.0 );
			}
		}

		if ( ! $meals ) {
			continue;
		}

		++$days;

		foreach ( $keys as $key ) {
			$sum[ $key ] += $per_person[ $key ];
		}
	}

	$average = array_fill_keys( $keys, 0.0 );

	if ( $days > 0 ) {
		foreach ( $keys as $key ) {
			$average[ $key ] = $sum[ $key ] / $days;
		}
	}

	return array(
		'average' => $average,
		'sum'     => $sum,
		'days'    => $days,
	);
}

// The AJAX handlers as frontend.js calls them; check_ajax_referer() reads $_REQUEST.
$ajax = function ( string $action, array $post ): ?array {
	$_POST    = $post + array( 'nonce' => wp_create_nonce( 'mmm_frontend_nonce' ) );
	$_REQUEST = $_POST;
	$die      = static function () {
		return static function () {
			throw new RuntimeException( 'mmm_pe_die' );
		};
	};
	add_filter( 'wp_doing_ajax', '__return_true' );
	add_filter( 'wp_die_ajax_handler', $die );
	ob_start();
	try {
		do_action( 'wp_ajax_nopriv_' . $action );
	} catch ( RuntimeException $e ) {
		unset( $e );
	}
	$json = json_decode( (string) ob_get_clean(), true );
	remove_filter( 'wp_die_ajax_handler', $die );
	remove_filter( 'wp_doing_ajax', '__return_true' );
	$_POST    = array();
	$_REQUEST = array();
	return is_array( $json ) ? $json : null;
};

$cookie = 'zz-plan-eaters-test';
$names  = array( 'Zzmama', 'Zzkindname' );

// The planner must hold the whole plan; restored exactly, absent included.
$limit_before = get_option( 'mixmymeals_max_items_in_cart', null );
update_option( 'mixmymeals_max_items_in_cart', 30 );

try {

	echo '-- Setup' . PHP_EOL;

	wp_set_current_user( 1 );

	// Every figure stated, so no figure is "unreliable" and "too little"
	// may be said wherever a whole day allows it. Protein and fibre low on
	// purpose: a whole day of these is short of both.
	$ing = $make_post( array( 'post_type' => 'mmm_ingredient', 'post_title' => 'T Reis' ) );
	( new Mixmymeals_Ingredient( $ing ) )->set_nutrition(
		array(
			'calories_100' => 150,
			'fat'          => 1,
			'saturates'    => 0.2,
			'carbs'        => 30,
			'sugar'        => 1,
			'fiber'        => 0.5,
			'protein'      => 2,
			'salt'         => 0.1,
		)
	);

	$packet = $make_post( array( 'post_type' => 'mmm_product', 'post_title' => 'T Reispackung', 'post_parent' => $ing ) );
	( new Mixmymeals_Product( $packet ) )->set_brand( 'ZZ' );

	$make_recipe = function ( string $title, float $grams ) use ( $make_post, $ing ): int {
		$id     = $make_post( array( 'post_type' => 'mmm_recipe', 'post_title' => $title ) );
		$recipe = new Mixmymeals_Recipe( $id );
		$recipe->set_servings( 1 );
		$recipe->set_steps(
			array(
				array(
					'title'       => 'T',
					'description' => 'x',
					'ingredients' => array(
						array( 'id' => $ing, 'amount' => $grams, 'amount_id' => 0 ),
					),
				),
			)
		);
		return $id;
	};

	$rb = $make_recipe( 'T Frühstück', 100 ); // 150 kcal.
	$rl = $make_recipe( 'T Mittag', 200 );    // 300 kcal.
	$rd = $make_recipe( 'T Abend', 300 );     // 450 kcal.

	$plan_id = $make_post( array( 'post_type' => 'mmm_meal_plan', 'post_title' => 'T Familienplan' ) );
	update_post_meta( $plan_id, 'start_day', 1 );
	update_post_meta( $plan_id, 'end_day', 2 );

	$meal_of = array();
	foreach (
		array(
			'b1' => array( $rb, 1, 1, 'breakfast' ),
			'l1' => array( $rl, 2, 1, 'lunch' ),
			'd1' => array( $rd, 2, 1, 'dinner' ),
			'd2' => array( $rd, 1, 2, 'dinner' ),
		) as $key => $m
	) {
		$meal = Mixmymeals_Meal::create( 'T Mahlzeit', $plan_id );
		$meal->set_recipe_id( $m[0] );
		$meal->set_servings( $m[1] );
		$meal->set_day( $m[2] );
		$meal->set_type( $m[3] );
		$meal_of[ $key ] = $meal;
	}
	Mixmymeals_Meal_Plan::flush_meals_cache( $plan_id );

	$plan = new Mixmymeals_Meal_Plan( $plan_id );

	echo PHP_EOL . '-- 1. A plan without people is computed as before' . PHP_EOL;

	$ok( 'one implicit person', array( Mixmymeals_Meal_Plan::DEFAULT_EATER ) === $plan->eater_ids() && ! $plan->has_eaters() );
	$ok( 'for_eater() hands back the plan itself', $plan->for_eater( 'p1' ) === $plan && $plan->for_eater( 'zz' ) === $plan );
	$ok( 'per day and person: identical to 3.69.0 (serialize)', serialize( $plan->calc_nutrition_per_day_person() ) === serialize( mmm_pe_legacy_per_day_person( $plan_id ) ) );
	$ok( 'every row is eaten by the one person', array( array( 'p1' ) ) === array_values( array_unique( array_column( $plan->get_meal_rows(), 'eaters' ), SORT_REGULAR ) ) );

	$before = array(
		'per_day'  => serialize( $plan->calc_nutrition_per_day_person() ),
		'rating'   => serialize( $plan->calc_day_rating() ),
		'ratings'  => serialize( $plan->calc_day_ratings() ),
		'impact'   => serialize( $plan->calc_product_impact() ),
		'shopping' => serialize( $plan->calc_shopping_list() ),
		'per_100g' => serialize( $plan->calc_nutrition_per_100g() ),
	);

	$overview_plain = do_shortcode( '[mmm_nutrition_overview_for_a_single_meal_plan meal_plan_id="' . $plan_id . '"]' );
	$ok( 'no switch and no wrapper attributes on the page', false === strpos( $overview_plain, 'mmm-eater-switch' ) && false === strpos( $overview_plain, 'data-plan-nutrition' ) );
	$ok( 'the wrapper reads exactly as before', false !== strpos( $overview_plain, '<div class="mmm-meal-plan-nutrition">' ) );

	// A stray row naming people the plan does not list changes nothing.
	update_post_meta( $meal_of['b1']->get_id(), Mixmymeals_Meal::META_EATERS, array( 'p7' ) );
	$ok( 'unknown eater on a meal: read as everybody', array( 'p1' ) === $meal_of['b1']->get_eaters() );
	$ok( 'and the figures do not move', $before['per_day'] === serialize( $plan->calc_nutrition_per_day_person() ) );
	delete_post_meta( $meal_of['b1']->get_id(), Mixmymeals_Meal::META_EATERS );

	echo PHP_EOL . '-- 2. Two people, a breakfast for one' . PHP_EOL;

	$plan->set_eaters(
		array(
			array( 'name' => $names[0] ),
			array(
				'name'    => $names[1],
				'profile' => 'zz-unknown',
			),
		)
	);
	$plan = new Mixmymeals_Meal_Plan( $plan_id );

	$ok( 'two people, ids p1 and p2', array( 'p1', 'p2' ) === $plan->eater_ids() && $plan->has_eaters() );
	$ok( 'an unknown profile is the adult', 'adult' === $plan->eater_profile( 'p2' ) );
	$ok( 'public labels are numbers', array( Mixmymeals_Meal_Plan::eater_label( 1 ), Mixmymeals_Meal_Plan::eater_label( 2 ) ) === array_column( $plan->get_eaters(), 'label' ) );
	$ok( 'get_eaters() carries no names', ! array_key_exists( 'name', $plan->get_eaters()[0] ) );
	$ok( 'get_eater_names() does, for wp-admin', $names[0] === $plan->get_eater_names()['p1'] );

	$meal_of['b1']->set_eaters( array( 'p1' ) );
	$meal_of['d2']->set_eaters( array( 'p2', 'p9' ) ); // p9 is nobody.
	$meal_of['l1']->set_eaters( array( 'p1', 'p2' ) ); // Everybody: no row.

	$ok( 'set_eaters() drops an unknown id', array( 'p2' ) === $meal_of['d2']->get_eaters() );
	$ok( 'everybody is stored as no row', '' === get_post_meta( $meal_of['l1']->get_id(), Mixmymeals_Meal::META_EATERS, true ) && array( 'p1', 'p2' ) === $meal_of['l1']->get_eaters() );

	$meal_of['d1']->set_eaters( array( 'p9' ) );
	$ok( 'nobody left means everybody, not nobody', array( 'p1', 'p2' ) === $meal_of['d1']->get_eaters() );

	$p1 = $plan->for_eater( 'p1' );
	$p2 = $plan->for_eater( 'p2' );

	$cal_b = (float) ( new Mixmymeals_Recipe( $rb ) )->calculate_nutrition_per_serving()['calories'];
	$cal_l = (float) ( new Mixmymeals_Recipe( $rl ) )->calculate_nutrition_per_serving()['calories'];
	$cal_d = (float) ( new Mixmymeals_Recipe( $rd ) )->calculate_nutrition_per_serving()['calories'];

	$f1 = $p1->calc_nutrition_per_day_person();
	$f2 = $p2->calc_nutrition_per_day_person();

	$ok( 'person 1 eats breakfast, lunch, dinner on Monday', 3 === count( $p1->get_meal_rows() ) );
	$ok( 'person 2 eats lunch and dinner on Monday, dinner on Tuesday', 3 === count( $p2->get_meal_rows() ) );
	$ok( 'the breakfast counts for person 1 only', abs( $f1['sum']['calories'] - ( $cal_b + $cal_l + $cal_d ) ) < 0.001 && abs( $f2['sum']['calories'] - ( $cal_l + 2 * $cal_d ) ) < 0.001 );
	$ok( 'divisor per person: person 1 has one day', 1 === $f1['days'] );
	$ok( 'divisor per person: person 2 has two', 2 === $f2['days'] && abs( $f2['average']['calories'] - ( $cal_l + 2 * $cal_d ) / 2 ) < 0.001 );
	$ok( 'the plan as a whole is still the person who eats everything', $before['per_day'] === serialize( $plan->calc_nutrition_per_day_person() ) );

	echo PHP_EOL . '-- 3. "Too little" per person' . PHP_EOL;

	$r1 = $p1->calc_day_rating();
	$r2 = $p2->calc_day_rating();

	$ok( 'person 1 has a whole day', $p1->covers_whole_days() && $r1['whole_days'] );
	$ok( 'and is short of protein', 'under' === ( $r1['ratings']['protein'] ?? '' ) );
	$ok( 'person 2 has no whole day', ! $p2->covers_whole_days() && ! $r2['whole_days'] );
	$ok( 'so nothing is too little for person 2', ! in_array( 'under', $r2['ratings'], true ) );
	$ok( 'per weekday too: Monday is whole for person 1 only', ! empty( $p1->calc_day_ratings()[1]['whole_day'] ) && empty( $p2->calc_day_ratings()[1]['whole_day'] ) );

	echo PHP_EOL . '-- 4. The shopping list ignores eaters' . PHP_EOL;

	$ok( 'calc_shopping_list(): identical (serialize)', $before['shopping'] === serialize( $plan->calc_shopping_list() ) );
	$ok( 'and under a servings offset the same as without people', serialize( $plan->with_servings_offset( 2 )->calc_shopping_list() ) === serialize( Mixmymeals_Meal_Plan::from_meal_rows( array_map( static fn( $r ) => array_diff_key( $r, array( 'eaters' => 1 ) ), $plan->get_meal_rows() ) )->with_servings_offset( 2 )->calc_shopping_list() ) );
	$ok( 'with_servings_offset() keeps the people', array( 'p1', 'p2' ) === $plan->with_servings_offset( 1 )->eater_ids() && array( 'p1' ) === $plan->with_servings_offset( 1 )->get_meal_rows()[0]['eaters'] );
	$ok( 'new meals start with one serving per person', 2 === $plan->default_servings() );

	echo PHP_EOL . '-- 5. Round trip through the Meals box callback' . PHP_EOL;

	$meta     = new Mixmymeals_Post_Meta();
	$post_box = function ( array $rows ) use ( $plan_id, $meta ) {
		$_POST                         = array();
		$_POST['mmm_plan_meals_nonce'] = wp_create_nonce( 'mmm_plan_meals_meta_box' );
		$_POST['mmm_plan_meals']       = $rows;
		$meta->save_plan_meals( $plan_id );
		$_POST = array();
	};
	$b1_id = $meal_of['b1']->get_id();

	ob_start();
	$meta->render_meal_plan_overview_meta_box( get_post( $plan_id ) );
	$box = (string) ob_get_clean();
	$ok( 'the box has the "Eats along" column', false !== strpos( $box, 'mmm_plan_meals[' . $b1_id . '][eaters][]' ) && false !== strpos( $box, '[eaters_shown]' ) );
	$ok( 'naming people by the editor\'s names there', false !== strpos( $box, $names[0] ) );

	$post_box( array( $b1_id => array( 'eaters_shown' => '1', 'eaters' => array( 'p2' ) ) ) );
	$ok( 'ticking person 2 saves person 2', array( 'p2' ) === ( new Mixmymeals_Meal( $b1_id ) )->get_eaters() );

	$post_box( array( $b1_id => array( 'eaters_shown' => '1' ) ) );
	$ok( 'unticking everybody is everybody', array( 'p1', 'p2' ) === ( new Mixmymeals_Meal( $b1_id ) )->get_eaters() );

	$post_box( array( $b1_id => array( 'eaters_shown' => '1', 'eaters' => array( 'p1', 'zz' ) ) ) );
	$ok( 'an unknown id from the POST is dropped', array( 'p1' ) === ( new Mixmymeals_Meal( $b1_id ) )->get_eaters() );

	$post_box( array( $b1_id => array( 'servings' => '3' ) ) );
	$ok( 'a screen without the column leaves the eaters alone', array( 'p1' ) === ( new Mixmymeals_Meal( $b1_id ) )->get_eaters() && 3 === ( new Mixmymeals_Meal( $b1_id ) )->get_servings() );
	( new Mixmymeals_Meal( $b1_id ) )->set_servings( 1 );

	$post_box(
		array(
			'new' => array(
				'recipe_id'    => $rl,
				'type'         => 'snack',
				'day'          => '2',
				'servings'     => '',
				'eaters_shown' => '1',
				'eaters'       => array( 'p2' ),
				'token'        => wp_generate_uuid4(),
			),
		)
	);
	Mixmymeals_Meal_Plan::flush_meals_cache( $plan_id );
	$new_meal = null;
	foreach ( get_children( array( 'post_parent' => $plan_id, 'post_type' => 'mmm_meal', 'post_status' => 'any' ) ) as $child ) {
		if ( 'snack' === ( new Mixmymeals_Meal( $child->ID ) )->get_type() ) {
			$new_meal = new Mixmymeals_Meal( $child->ID );
		}
	}
	$ok( 'the new row: eaten by person 2, one serving (what its eaters eat)', $new_meal && array( 'p2' ) === $new_meal->get_eaters() && 1 === $new_meal->get_servings() );
	if ( $new_meal ) {
		wp_delete_post( $new_meal->get_id(), true );
		Mixmymeals_Meal_Plan::flush_meals_cache( $plan_id );
	}

	echo PHP_EOL . '-- 6. Round trip through the People box callback' . PHP_EOL;

	$post_people = function ( array $rows ) use ( $plan_id, $meta ) {
		$_POST                          = array();
		$_POST['mmm_plan_eaters_nonce'] = wp_create_nonce( 'mmm_plan_eaters_meta_box' );
		$_POST['mmm_plan_eaters']       = $rows;
		$meta->save_plan_eaters( $plan_id );
		$_POST = array();
	};

	$token = wp_generate_uuid4();
	$post_people(
		array(
			'p1'  => array(
				'name'    => 'Zzrenamed',
				'profile' => 'adult',
			),
			'new' => array(
				'add'   => '1',
				'name'  => 'Zzthird',
				'token' => $token,
			),
		)
	);
	$after = new Mixmymeals_Meal_Plan( $plan_id );
	$ok( 'a rename and a new person', array( 'p1', 'p2', 'p3' ) === $after->eater_ids() && 'Zzrenamed' === $after->get_eater_names()['p1'] && $names[1] === $after->get_eater_names()['p2'] );

	$post_people( array( 'new' => array( 'add' => '1', 'name' => 'Zzthird', 'token' => $token ) ) );
	$ok( 'the same new row posted again adds nobody', 3 === count( ( new Mixmymeals_Meal_Plan( $plan_id ) )->eater_ids() ) );

	$post_people( array( 'p3' => array( 'delete' => '1' ) ) );
	$after = new Mixmymeals_Meal_Plan( $plan_id );
	$ok( 'removing person 3, the others keep their ids', array( 'p1', 'p2' ) === $after->eater_ids() );

	$_POST = array( 'mmm_plan_eaters' => array( 'p2' => array( 'delete' => '1' ) ) );
	$meta->save_plan_eaters( $plan_id );
	$_POST = array();
	$ok( 'without the nonce nothing is written', array( 'p1', 'p2' ) === ( new Mixmymeals_Meal_Plan( $plan_id ) )->eater_ids() );

	$plan = new Mixmymeals_Meal_Plan( $plan_id );
	$plan->set_eaters(
		array(
			array(
				'id'   => 'p1',
				'name' => $names[0],
			),
			array(
				'id'   => 'p2',
				'name' => $names[1],
			),
		)
	);
	$plan = new Mixmymeals_Meal_Plan( $plan_id );

	echo PHP_EOL . '-- 7. Planner and plan page print one table per person' . PHP_EOL;

	$_COOKIE[ Mixmymeals_Cart::COOKIE_NAME ] = $cookie;
	wp_set_current_user( 0 );

	$loaded = $ajax( 'mmm_load_plan_into_cart', array( 'meal_plan_id' => $plan_id ) );
	$cart   = new Mixmymeals_Cart();
	$ok( 'the plan loads into the planner with its people', ! empty( $loaded['success'] ) && array( 'p1', 'p2' ) === $cart->eater_ids() );
	$ok( 'as numbers: the planner holds no names', array( '', '' ) === array_column( $cart->get_eaters(), 'name' ) );
	$ok( 'every item keeps who eats it', array( array( 'p1' ), array(), array(), array( 'p2' ) ) === array_map( static fn( $i ) => $i['eaters'] ?? null, $cart->get_items() ) );

	$reflect = new ReflectionMethod( Mixmymeals_Shortcodes::class, 'render_person_nutrition_table' );
	$reflect->setAccessible( true );
	$shortcodes = new Mixmymeals_Shortcodes();

	foreach ( array( 'p1', 'p2' ) as $who ) {
		$json  = $ajax( 'mmm_planner_nutrition', array( 'eater' => $who ) );
		$saved = (string) $reflect->invoke( $shortcodes, $plan, $who );
		$ok( "planner table = plan page table for $who, byte for byte", isset( $json['data']['table'] ) && '' !== $saved && $saved === $json['data']['table'] );
		$ok( "the planner answers for $who", $who === ( $json['data']['eater'] ?? '' ) );

		// The page the overview stands on, with a parameter of its own that
		// the switch's links must keep.
		$uri_before             = $_SERVER['REQUEST_URI'] ?? null;
		$_SERVER['REQUEST_URI'] = '/zz-plan/?mmm_persons_to_add=1&mmm_eater=' . $who;
		$_GET[ Mixmymeals_Meal_Plan::EATER_QUERY_VAR ] = $who;
		$page = do_shortcode( '[mmm_nutrition_overview_for_a_single_meal_plan meal_plan_id="' . $plan_id . '"]' );
		unset( $_GET[ Mixmymeals_Meal_Plan::EATER_QUERY_VAR ] );
		$ok( "?mmm_eater=$who: the page prints that table", false !== strpos( $page, $saved ) && false !== strpos( $page, 'data-eater="' . $who . '"' ) );
		$ok( 'the switch keeps the page and its other parameters', false !== strpos( $page, 'href="/zz-plan/?mmm_persons_to_add=1&#038;mmm_eater=p2#mmm-plan-nutrition"' ) && false !== strpos( $page, 'href="/zz-plan/?mmm_persons_to_add=1#mmm-plan-nutrition"' ) );

		$plan_json = $ajax( 'mmm_plan_nutrition', array( 'meal_plan_id' => $plan_id, 'eater' => $who, 'page_url' => $_SERVER['REQUEST_URI'] ) );
		$ok( "mmm_plan_nutrition for $who = the page", isset( $plan_json['data']['html'] ) && $page === $plan_json['data']['html'] );
		if ( null === $uri_before ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $uri_before;
		}
	}

	$json = $ajax( 'mmm_planner_nutrition', array( 'eater' => 'zz' ) );
	$ok( 'an unknown person is the first', 'p1' === ( $json['data']['eater'] ?? '' ) );

	$foreign = $ajax( 'mmm_plan_nutrition', array( 'meal_plan_id' => $plan_id, 'eater' => 'p2', 'page_url' => 'https://elsewhere.example/x/' ) );
	$ok( 'a foreign page address is not taken for the links', isset( $foreign['data']['html'] ) && false === strpos( $foreign['data']['html'], 'elsewhere.example' ) );

	$ok( 'the planner\'s switch is there', false !== strpos( (string) ( $json['data']['html'] ?? '' ), 'mmm-eater-switch' ) );

	// Cart: unknown ids on an item are dropped, nobody left is everybody.
	$cart->update_item_by_index( 0, array( 'eaters' => 'p2,zz' ) );
	$ok( 'cart item: unknown id dropped', array( 'p2' ) === $cart->get_items()[0]['eaters'] );
	$cart->update_item_by_index( 0, array( 'eaters' => 'zz' ) );
	$ok( 'cart item: nobody left is everybody', array() === $cart->get_items()[0]['eaters'] );

	$share = $ajax( 'mmm_share_cart', array() );
	$snap  = get_transient( Mixmymeals_Cart::SHARE_PREFIX . ( $share['data']['token'] ?? '' ) );
	$ok( 'a shared planner carries the people as numbers', is_array( $snap ) && array( 'p1', 'p2' ) === array_column( (array) ( $snap['eaters'] ?? array() ), 'id' ) && false === strpos( serialize( $snap ), $names[0] ) );
	delete_transient( Mixmymeals_Cart::SHARE_PREFIX . ( $share['data']['token'] ?? '' ) );

	$cart->clear();
	$ok( 'clearing the planner forgets its people', array( 'p1' ) === $cart->eater_ids() );

	echo PHP_EOL . '-- 8. No names in public' . PHP_EOL;

	$public = do_shortcode( '[mmm_nutrition_overview_for_a_single_meal_plan meal_plan_id="' . $plan_id . '"]' )
		. do_shortcode( '[mmm_meals_in_a_single_meal_plan meal_plan_id="' . $plan_id . '"]' )
		. do_shortcode( '[mmm_nutrition_per_day_for_a_single_meal_plan meal_plan_id="' . $plan_id . '"]' )
		. do_shortcode( '[mmm_meal_plan_product_impact id="' . $plan_id . '"]' )
		. do_shortcode( '[mmm_shopping_list_for_a_single_meal_plan meal_plan_id="' . $plan_id . '"]' )
		. wp_json_encode( $plan->get_schema() );

	$ok( 'the switch names Person 1 and Person 2', false !== strpos( $public, 'mmm-eater-switch' ) && 2 === substr_count( $public, 'class="mmm-eater-switch__item' ) );
	$ok( 'no name in the plan page\'s markup or schema', false === strpos( $public, $names[0] ) && false === strpos( $public, $names[1] ) );

	$rest_guest = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/mixmymeals/v1/meal-plans/' . $plan_id ) );
	$guest_body = wp_json_encode( $rest_guest->get_data() );
	$ok( 'REST for a guest: the people, without names', 200 === $rest_guest->get_status() && false !== strpos( $guest_body, '"eaters"' ) && false === strpos( $guest_body, $names[0] ) );

	wp_set_current_user( 1 );
	$rest_editor = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/mixmymeals/v1/meal-plans/' . $plan_id ) );
	$ok( 'REST for an editor: with names', false !== strpos( wp_json_encode( $rest_editor->get_data() ), $names[0] ) );

	$create = new WP_REST_Request( 'POST', '/mixmymeals/v1/meals' );
	$create->set_body_params(
		array(
			'plan_id'   => $plan_id,
			'recipe_id' => $rl,
			'day'       => 2,
			'type'      => 'lunch',
			'eaters'    => array( 'p1', 'zz' ),
		)
	);
	$created = rest_get_server()->dispatch( $create );
	$data    = (array) $created->get_data();
	$ok( 'REST /meals: servings default to what its eaters eat, unknown eater dropped', 201 === $created->get_status() && 1 === ( $data['servings'] ?? 0 ) && array( 'p1' ) === ( $data['eaters'] ?? null ) );
	if ( ! empty( $data['id'] ) ) {
		wp_delete_post( (int) $data['id'], true );
		Mixmymeals_Meal_Plan::flush_meals_cache( $plan_id );
	}

	$update = new WP_REST_Request( 'POST', '/mixmymeals/v1/meal-plans/' . $plan_id );
	$update->set_body_params( array( 'eaters' => array( array( 'id' => 'p2' ), array( 'id' => 'p1' ) ) ) );
	rest_get_server()->dispatch( $update );
	$reordered = new Mixmymeals_Meal_Plan( $plan_id );
	$ok( 'REST reorder keeps the names it did not mention', array( 'p2', 'p1' ) === $reordered->eater_ids() && $names[0] === $reordered->get_eater_names()['p1'] );

	echo PHP_EOL . '-- 9. Reference profile' . PHP_EOL;

	$ok( 'only the adult profile exists', array( 'adult' ) === array_keys( Mixmymeals_Meal_Plan::eater_profiles() ) );
	$ok( 'the adult\'s reference intake is reference_intake() as before', Mixmymeals_Meal_Plan::reference_intake() === Mixmymeals_Meal_Plan::reference_intake( 'adult' ) && Mixmymeals_Meal_Plan::reference_intake() === Mixmymeals_Meal_Plan::with_reference_profile( 'adult', static fn() => Mixmymeals_Meal_Plan::reference_intake() ) );

	$profile_filter = static fn( array $p ) => $p + array( 'zzteen' => 'ZZ Teen' );
	$intake_filter  = static function ( array $v, string $profile = '' ) {
		if ( 'zzteen' === $profile ) {
			$v['calories'] = 2400.0;
		}
		return $v;
	};
	add_filter( 'mixmymeals_eater_profiles', $profile_filter );
	add_filter( 'mixmymeals_reference_intake', $intake_filter, 10, 2 );
	$ok( 'a profile added by filter gets its own figures', 2400.0 === Mixmymeals_Meal_Plan::with_reference_profile( 'zzteen', static fn() => Mixmymeals_Meal_Plan::reference_intake()['calories'] ) );
	$ok( 'and the context is restored afterwards', abs( Mixmymeals_Meal_Plan::reference_intake()['calories'] - 2400.0 ) > 0.1 );
	remove_filter( 'mixmymeals_eater_profiles', $profile_filter );
	remove_filter( 'mixmymeals_reference_intake', $intake_filter, 10 );

	echo PHP_EOL . '-- 10. Portions per person' . PHP_EOL;

	wp_set_current_user( 1 );
	$step_before = get_option( Mixmymeals_Meal_Plan::PORTION_STEP_OPTION, null );
	delete_option( Mixmymeals_Meal_Plan::PORTION_STEP_OPTION );

	$pp_id = $make_post( array( 'post_type' => 'mmm_meal_plan', 'post_title' => 'T Portionenplan' ) );
	update_post_meta( $pp_id, 'start_day', 1 );
	update_post_meta( $pp_id, 'end_day', 1 );

	$pp = new Mixmymeals_Meal_Plan( $pp_id );
	$pp->set_eaters(
		array(
			array( 'id' => 'p1' ),
			array(
				'id'       => 'p2',
				'portions' => '0,5',
			),
		)
	);
	$pp = new Mixmymeals_Meal_Plan( $pp_id );

	$pp_meal = array();
	foreach ( array( 'b' => array( $rb, 'breakfast' ), 'd' => array( $rd, 'dinner' ) ) as $key => $m ) {
		$meal = Mixmymeals_Meal::create( 'T Mahlzeit', $pp_id );
		$meal->set_recipe_id( $m[0] );
		$meal->set_servings( 2 );
		$meal->set_day( 1 );
		$meal->set_type( $m[1] );
		$pp_meal[ $key ] = $meal;
	}
	Mixmymeals_Meal_Plan::flush_meals_cache( $pp_id );

	$pp_shopping = serialize( $pp->calc_shopping_list() );
	$kcal_of     = static fn( Mixmymeals_Meal_Plan $plan ): float => (float) $plan->calc_nutrition_per_day_person()['average']['calories'];

	$ok( 'a usual portion of "0,5" is stored as 0.5', 0.5 === array_column( $pp->get_eaters(), 'portions', 'id' )['p2'] );
	$ok( 'person 1 at one portion of each meal', abs( $kcal_of( $pp->for_eater( 'p1' ) ) - ( $cal_b + $cal_d ) ) < 0.001 );
	$ok( 'person 2 at half a portion eats half of every figure', abs( $kcal_of( $pp->for_eater( 'p2' ) ) - 0.5 * ( $cal_b + $cal_d ) ) < 0.001 );
	$ok( 'the plan as a whole still counts one portion each', abs( $kcal_of( $pp ) - ( $cal_b + $cal_d ) ) < 0.001 );

	$pp_meal['d']->set_eater_portions( array( 'p2' => 1.5, 'p1' => 1 ) );
	$ok( 'a meal of its own: only what differs from the usual is stored', array( 'p2' => 1.5 ) === get_post_meta( $pp_meal['d']->get_id(), Mixmymeals_Meal::META_EATER_PORTIONS, true ) );
	$ok( 'and read for everybody who eats it', array( 'p1' => 1.0, 'p2' => 1.5 ) === $pp_meal['d']->get_eater_portions() );
	$ok( 'person 2: half a breakfast, one and a half dinners', abs( $kcal_of( $pp->for_eater( 'p2' ) ) - ( 0.5 * $cal_b + 1.5 * $cal_d ) ) < 0.001 );

	echo PHP_EOL . '-- 10b. Never fewer servings than are eaten' . PHP_EOL;

	$ok( '1 + 1.5 portions do not fit two servings: the dinner is raised to three', 3 === $pp_meal['d']->get_servings() && 3 === (int) get_post_meta( $pp_meal['d']->get_id(), 'servings', true ) );
	$ok( 'and the plan reads three', 3 === (int) array_column( $pp->get_meal_rows(), 'servings', 'meal_id' )[ $pp_meal['d']->get_id() ] );
	$pp_meal['d']->set_servings( 1 );
	$ok( 'setting fewer keeps the floor', 3 === $pp_meal['d']->get_servings() );
	update_post_meta( $pp_meal['d']->get_id(), 'servings', 1 );
	$ok( 'a row written past the class is floored on read', 3 === $pp_meal['d']->get_servings() && 3 === (int) array_column( $pp->get_meal_rows(), 'servings', 'meal_id' )[ $pp_meal['d']->get_id() ] );
	$ok( 'the shopping list follows the floor, not the stored one', serialize( $pp->calc_shopping_list() ) !== $pp_shopping );
	$pp_meal['d']->set_servings( 3 );

	$pp_shopping = serialize( $pp->calc_shopping_list() );
	$pp_meal['d']->set_eater_portions( array( 'p2' => 2 ) );
	$ok( 'portions within the servings leave the shopping list alone (serialize)', $pp_shopping === serialize( $pp->calc_shopping_list() ) && 3 === $pp_meal['d']->get_servings() );
	$pp_meal['d']->set_eater_portions( array( 'p2' => 1.5 ) );

	$ok( 'no surplus at the floor', 0.0 === $pp_meal['d']->servings_surplus() && '' === Mixmymeals_Meal_Plan::servings_surplus_text( $pp_meal['d']->servings_surplus() ) );
	$pp_meal['d']->set_servings( 4 );
	$ok( 'four servings for 2.5 portions: 1.5 more than eaten', 1.5 === $pp_meal['d']->servings_surplus() && '' !== Mixmymeals_Meal_Plan::servings_surplus_text( 1.5 ) );
	$ok( 'the breakfast at 2 for 1.5 is the floor, no note', 0.0 === $pp_meal['b']->servings_surplus() );
	$ok( 'a plan without people never notes a surplus', 0.0 === ( new Mixmymeals_Meal_Plan( $plan_id ) )->servings_surplus( 9, array( 'p1' => 1.0 ) ) || ( new Mixmymeals_Meal_Plan( $plan_id ) )->has_eaters() );
	$pp_meal['d']->set_servings( 3 );

	$bounds = $pp->servings_offset_bounds();
	$ok( 'both meals at their floor: the plan page offers nothing to take away', 0 === $bounds['min'] );
	$pp_meal['d']->set_servings( 5 );
	$ok( 'the dinner at five: two to take away', -2 === $pp->servings_offset_bounds()['min'] );
	$pp_meal['d']->set_servings( 3 );
	$ok( 'and an offset of −5 stops there', array( 2, 3 ) === array_map( 'intval', array_column( $pp->with_servings_offset( -5 )->get_meal_rows(), 'servings' ) ) );

	$pp_meal['b']->set_eater_portions( array( 'p2' => 0, 'p1' => -1, 'zz' => 2 ) );
	$ok( 'zero, negatives and unknown people are not stored', '' === get_post_meta( $pp_meal['b']->get_id(), Mixmymeals_Meal::META_EATER_PORTIONS, true ) );
	$pp_meal['b']->set_eater_portions( array( 'p2' => 11 ) );
	$ok( 'nor more than PORTIONS_MAX', '' === get_post_meta( $pp_meal['b']->get_id(), Mixmymeals_Meal::META_EATER_PORTIONS, true ) );

	$weights = $pp->for_eater( 'p2' )->get_ingredient_portion_weights();
	$ok( 'the product comparison weighs what person 2 eats', abs( (float) $weights[ $ing ]['grams_person'] - ( 0.5 * 100 + 1.5 * 300 ) ) < 0.001 );

	echo PHP_EOL . '-- 11. The step is a setting' . PHP_EOL;

	$ok( 'quarters by default: 0.3 is 0.25', 0.25 === Mixmymeals_Meal_Plan::normalise_portion( '0.3' ) );
	update_option( Mixmymeals_Meal_Plan::PORTION_STEP_OPTION, '0.5' );
	$ok( 'halves: 0.7 is 0.5, 0.8 is 1', 0.5 === Mixmymeals_Meal_Plan::normalise_portion( '0.7' ) && 1.0 === Mixmymeals_Meal_Plan::normalise_portion( '0,8' ) );
	$ok( 'never below one step', 0.5 === Mixmymeals_Meal_Plan::normalise_portion( '0.1' ) );
	update_option( Mixmymeals_Meal_Plan::PORTION_STEP_OPTION, 'any' );
	$ok( 'any amount: 0.7 stays', 0.7 === Mixmymeals_Meal_Plan::normalise_portion( '0.7' ) );
	update_option( Mixmymeals_Meal_Plan::PORTION_STEP_OPTION, '1' );
	$ok( 'whole portions only: a stored 1.5 is read as stored', array( 'p1' => 1.0, 'p2' => 1.5 ) === $pp_meal['d']->get_eater_portions() );
	$ok( 'an unknown setting is the default', 'zz' !== Mixmymeals_Meal_Plan::sanitize_portion_step( 'zz' ) && Mixmymeals_Meal_Plan::PORTION_STEP_DEFAULT === Mixmymeals_Meal_Plan::sanitize_portion_step( 'zz' ) );
	delete_option( Mixmymeals_Meal_Plan::PORTION_STEP_OPTION );

	echo PHP_EOL . '-- 12. Servings start from the portions, then stay free' . PHP_EOL;

	$ok( 'one adult and a half: two servings', 2 === $pp->default_servings() );
	$ok( 'only person 2 at 1.5 of one meal: two servings', 2 === $pp->default_servings( array( 'p2' ), array( 'p2' => 1.5 ) ) );
	$ok( 'only person 2 at half: one serving', 1 === $pp->default_servings( array( 'p2' ) ) );

	$create = new WP_REST_Request( 'POST', '/mixmymeals/v1/meals' );
	$create->set_body_params(
		array(
			'plan_id'        => $pp_id,
			'recipe_id'      => $rl,
			'day'            => 1,
			'type'           => 'lunch',
			'eater_portions' => array( 'p2' => 2 ),
		)
	);
	$created = rest_get_server()->dispatch( $create );
	$data    = (array) $created->get_data();
	$ok( 'REST /meals: 1 + 2 portions start at three servings', 201 === $created->get_status() && 3 === ( $data['servings'] ?? 0 ) );
	$ok( 'and carries eater_portions', array( 'p1' => 1.0, 'p2' => 2.0 ) === ( $data['eater_portions'] ?? null ) );
	if ( ! empty( $data['id'] ) ) {
		wp_delete_post( (int) $data['id'], true );
		Mixmymeals_Meal_Plan::flush_meals_cache( $pp_id );
	}

	echo PHP_EOL . '-- 13. Round trips through the two boxes' . PHP_EOL;

	$post_pp = function ( array $rows ) use ( $pp_id, $meta ) {
		$_POST                         = array();
		$_POST['mmm_plan_meals_nonce'] = wp_create_nonce( 'mmm_plan_meals_meta_box' );
		$_POST['mmm_plan_meals']       = $rows;
		$meta->save_plan_meals( $pp_id );
		$_POST = array();
	};
	$d_id = $pp_meal['d']->get_id();

	ob_start();
	$meta->render_meal_plan_overview_meta_box( get_post( $pp_id ) );
	$box = (string) ob_get_clean();
	$ok( 'the Meals box has a portions field per person', false !== strpos( $box, 'mmm_plan_meals[' . $d_id . '][portions][p2]' ) && false !== strpos( $box, 'value="1.5"' ) );
	$ok( 'and the servings field its floor', false !== strpos( $box, 'min="3" step="1" name="mmm_plan_meals[' . $d_id . '][servings]"' ) );
	$pp_meal['d']->set_servings( 4 );
	ob_start();
	$meta->render_meal_plan_overview_meta_box( get_post( $pp_id ) );
	$ok( 'four servings: the box notes the surplus', false !== strpos( (string) ob_get_clean(), 'mmm-plan-meals__surplus' ) );
	$pp_meal['d']->set_servings( 3 );

	$post_pp( array( $d_id => array( 'eaters_shown' => '1', 'eaters' => array( 'p1', 'p2' ), 'portions' => array( 'p1' => '', 'p2' => '1,25' ) ) ) );
	$ok( 'Meals box: 1,25 for person 2 is saved', array( 'p1' => 1.0, 'p2' => 1.25 ) === ( new Mixmymeals_Meal( $d_id ) )->get_eater_portions() );
	$post_pp( array( $d_id => array( 'eaters_shown' => '1', 'eaters' => array( 'p1', 'p2' ), 'portions' => array( 'p2' => '' ) ) ) );
	$ok( 'an emptied field is the usual amount again', '' === get_post_meta( $d_id, Mixmymeals_Meal::META_EATER_PORTIONS, true ) );
	$post_pp( array( $d_id => array( 'eaters_shown' => '1', 'eaters' => array( 'p1' ), 'portions' => array( 'p2' => '2' ) ) ) );
	$ok( 'a figure for somebody not ticked is not kept', '' === get_post_meta( $d_id, Mixmymeals_Meal::META_EATER_PORTIONS, true ) );
	$post_pp( array( $d_id => array( 'eaters_shown' => '1', 'eaters' => array( 'p1', 'p2' ), 'portions' => array( 'p2' => '1.5' ) ) ) );

	$_POST                          = array();
	$_POST['mmm_plan_eaters_nonce'] = wp_create_nonce( 'mmm_plan_eaters_meta_box' );
	$_POST['mmm_plan_eaters']       = array( 'p2' => array( 'portions' => '0,75' ) );
	$meta->save_plan_eaters( $pp_id );
	$_POST = array();
	$ok( 'People box: person 2 usually eats 0.75', 0.75 === array_column( ( new Mixmymeals_Meal_Plan( $pp_id ) )->get_eaters(), 'portions', 'id' )['p2'] );
	$ok( 'the breakfast follows, the dinner keeps its own 1.5', array( 'p1' => 1.0, 'p2' => 0.75 ) === $pp_meal['b']->get_eater_portions() && array( 'p1' => 1.0, 'p2' => 1.5 ) === $pp_meal['d']->get_eater_portions() );

	echo PHP_EOL . '-- 14. Planner and plan page agree, portions included' . PHP_EOL;

	$pp = new Mixmymeals_Meal_Plan( $pp_id );
	wp_set_current_user( 0 );
	$ajax( 'mmm_load_plan_into_cart', array( 'meal_plan_id' => $pp_id ) );
	$cart = new Mixmymeals_Cart();
	$ok( 'the planner gets the usual portions', array( 'p1' => 1.0, 'p2' => 0.75 ) === array_column( $cart->get_eaters(), 'portions', 'id' ) );
	$ok( 'and the dinner\'s own figure, nothing else', array( array(), array( 'p2' => 1.5 ) ) === array_map( static fn( $i ) => $i['portions'] ?? null, $cart->get_items() ) );

	$json  = $ajax( 'mmm_planner_nutrition', array( 'eater' => 'p2' ) );
	$saved = (string) $reflect->invoke( $shortcodes, $pp, 'p2' );
	$ok( 'planner table = plan page table for person 2, byte for byte', '' !== $saved && $saved === ( $json['data']['table'] ?? null ) );

	$_POST = array( 'index' => 0, 'portions' => wp_slash( wp_json_encode( array( 'p2' => '2', 'zz' => '1' ) ) ) );
	$ajax( 'mmm_update_cart_item', $_POST );
	$ok( 'the planner writes an item\'s portions, unknown people dropped', array( 'p2' => 2.0 ) === ( ( new Mixmymeals_Cart() )->get_items()[0]['portions'] ?? null ) );

	$answer = $ajax( 'mmm_update_cart_item', array( 'index' => 0, 'servings' => 1 ) );
	// Item 0 is eaten by person 1 (1) and person 2 (2, set just above): three.
	$ok( 'planner: one serving for 1 + 2 portions is raised to three', 3 === (int) ( $answer['data']['item']['servings'] ?? 0 ) && 3 === ( $answer['data']['min_servings'] ?? 0 ) && '' === ( $answer['data']['surplus'] ?? null ) );
	$answer = $ajax( 'mmm_update_cart_item', array( 'index' => 0, 'servings' => 4 ) );
	$ok( 'planner: four servings say "1 serving more than eaten"', 4 === (int) ( $answer['data']['item']['servings'] ?? 0 ) && Mixmymeals_Meal_Plan::servings_surplus_text( 1.0 ) === ( $answer['data']['surplus'] ?? null ) );
	$answer = $ajax( 'mmm_update_cart_item', array( 'index' => 0, 'eaters' => 'p1' ) );
	$ok( 'unticking person 2 keeps the four and says "3 more"', 4 === (int) ( $answer['data']['item']['servings'] ?? 0 ) && 1 === ( $answer['data']['min_servings'] ?? 0 ) && Mixmymeals_Meal_Plan::servings_surplus_text( 3.0 ) === ( $answer['data']['surplus'] ?? null ) );
	$builder = do_shortcode( '[mmm_meal_plan_builder]' );
	$ok( 'the planner item carries the floor and the note', false !== strpos( $builder, 'min="1" value="4"' ) && false !== strpos( $builder, esc_html( Mixmymeals_Meal_Plan::servings_surplus_text( 3.0 ) ) ) );
	$answer = $ajax( 'mmm_update_cart_item', array( 'index' => 0, 'eaters' => 'p1,p2', 'portions' => wp_slash( wp_json_encode( array( 'p2' => '4' ) ) ) ) );
	$ok( 'ticking person 2 back at four portions raises the servings to five', 5 === (int) ( $answer['data']['item']['servings'] ?? 0 ) && '' === ( $answer['data']['surplus'] ?? null ) );

	$ajax( 'mmm_cart_eaters', array( 'op' => 'portions', 'id' => 'p1', 'portions' => '1,5' ) );
	$ok( 'and a person\'s usual portions', 1.5 === array_column( ( new Mixmymeals_Cart() )->get_eaters(), 'portions', 'id' )['p1'] );
	( new Mixmymeals_Cart() )->clear();

	echo PHP_EOL . '-- 15. One person at half portions' . PHP_EOL;

	wp_set_current_user( 1 );
	$pp->set_eaters(
		array(
			array(
				'id'       => 'p1',
				'portions' => 0.5,
			),
		)
	);
	$pp = new Mixmymeals_Meal_Plan( $pp_id );
	$ok( 'one person is still no switch', ! $pp->has_eaters() );
	$ok( 'but their portions count', abs( $kcal_of( $pp->for_eater( 'p1' ) ) - 0.5 * ( $cal_b + $cal_d ) ) < 0.001 );
	$page = do_shortcode( '[mmm_nutrition_overview_for_a_single_meal_plan meal_plan_id="' . $pp_id . '"]' );
	$ok( 'on the page too, without switch or wrapper attributes', false === strpos( $page, 'mmm-eater-switch' ) && false !== strpos( $page, '<div class="mmm-meal-plan-nutrition">' ) && false !== strpos( $page, Mixmymeals_Meal_Plan::format_nutrition_value( 'calories', 0.5 * ( $cal_b + $cal_d ) ) ) );

	if ( null === $step_before ) {
		delete_option( Mixmymeals_Meal_Plan::PORTION_STEP_OPTION );
	} else {
		update_option( Mixmymeals_Meal_Plan::PORTION_STEP_OPTION, $step_before );
	}

} finally {
	if ( null === $limit_before ) {
		delete_option( 'mixmymeals_max_items_in_cart' );
	} else {
		update_option( 'mixmymeals_max_items_in_cart', $limit_before );
	}
	unset( $_GET[ Mixmymeals_Meal_Plan::EATER_QUERY_VAR ] );
	$_COOKIE[ Mixmymeals_Cart::COOKIE_NAME ] = $cookie;
	wp_set_current_user( 0 );
	( new Mixmymeals_Cart() )->clear();
	wp_set_current_user( 1 );
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
