<?php
/**
 * The planner's live shopping list ([mmm_planner_shopping_list]): computed
 * from the cart, the same list the plan page prints once the planner is
 * saved, and refreshed from the `shopping` key of the `mmm_planner_nutrition`
 * answer — which carries it only when asked.
 *
 * Runs against a real install, creates its own posts and deletes them again.
 * Everything it creates is titled "T …", the product carries the brand ZZ.
 *
 *   wp --exec="define('DB_HOST','localhost:<socket>');" \
 *      eval-file wp-content/plugins/mixmymeals/tools/planner-shopping-test.php
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

// The AJAX handler as frontend.js calls it; check_ajax_referer() reads $_REQUEST.
$ask = function ( array $post ): ?array {
	$_POST    = $post + array( 'nonce' => wp_create_nonce( 'mmm_frontend_nonce' ) );
	$_REQUEST = $_POST;
	$die      = static function () {
		return static function () {
			throw new RuntimeException( 'mmm_ps_die' );
		};
	};
	add_filter( 'wp_doing_ajax', '__return_true' );
	add_filter( 'wp_die_ajax_handler', $die );
	ob_start();
	try {
		do_action( 'wp_ajax_nopriv_mmm_planner_nutrition' );
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

$cookie = 'zz-planner-shopping-test';

try {

	echo '-- Setup' . PHP_EOL;

	$carrot = $make_post( array( 'post_type' => 'mmm_ingredient', 'post_title' => 'T Möhre' ) );
	( new Mixmymeals_Ingredient( $carrot ) )->set_nutrition( array( 'calories_100' => 36 ) );

	$tomato = $make_post( array( 'post_type' => 'mmm_ingredient', 'post_title' => 'T Tomaten' ) );
	( new Mixmymeals_Ingredient( $tomato ) )->set_nutrition( array( 'calories_100' => 20 ) );

	$tin = Mixmymeals_Ingredient_Amount::create( $tomato, 'T Dose' );
	$trash[] = $tin->get_id();
	$tin->set_weight( 400.0 );
	$tin->set_divisor( 2 ); // Half tins.

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
						array( 'id' => $tomato, 'amount' => 0.5, 'amount_id' => $tin->get_id() ),
						array( 'id' => $carrot, 'amount' => $carrot_g, 'amount_id' => 0 ),
					),
				),
			)
		);
		return $id;
	};

	$r1 = $make_recipe( 'T Eintopf', 400 );
	$r2 = $make_recipe( 'T Suppe', 200 );

	$_COOKIE[ Mixmymeals_Cart::COOKIE_NAME ] = $cookie;
	$cart = new Mixmymeals_Cart();

	echo PHP_EOL . '-- Empty planner' . PHP_EOL;

	$cart->save_items( array() );
	$html = do_shortcode( '[mmm_planner_shopping_list]' );
	$ok( 'the box is there, hidden', false !== strpos( $html, 'data-planner-shopping' ) && false !== strpos( $html, ' hidden>' ) );
	$ok( 'and holds no list', false === strpos( $html, 'mmm-simple-shopping-list' ) );

	$json = $ask( array( 'shopping' => 1 ) );
	$ok( 'the answer carries an empty list', isset( $json['data']['shopping'] ) && '' === $json['data']['shopping'] );

	echo PHP_EOL . '-- Planner with meals' . PHP_EOL;

	$items = array(
		array( 'recipe_id' => $r1, 'servings' => 4, 'type' => 'dinner', 'day' => 1 ),
		array( 'recipe_id' => $r2, 'servings' => 4, 'type' => 'lunch', 'day' => 2 ),
		array( 'recipe_id' => $r2, 'servings' => 2, 'type' => '', 'day' => null ),
	);
	$cart->save_items( $items );

	$html = do_shortcode( '[mmm_planner_shopping_list]' );
	$ok( 'first paint renders the list', false !== strpos( $html, 'mmm-simple-shopping-list' ) && false === strpos( $html, ' hidden>' ) );
	$ok( 'details and categories on by default', false !== strpos( $html, 'data-show-details="1"' ) && false !== strpos( $html, 'data-sort-by-category="1"' ) );
	$ok( 'with its heading', false !== strpos( $html, 'mmm-planner-shopping__title' ) );

	// Half a tin three times (4 + 4 + 2 servings of 4): 1.25 tins, rounded
	// up once over the sum to 1.5 — never 0.5 + 0.5 + 0.5 rounded apiece.
	$list = Mixmymeals_Meal_Plan::from_meal_rows( $items )->calc_shopping_list( false );
	$line = $list[ array_key_first( $list ) ][ $tomato ] ?? array();
	$ok( 'tins rounded up once over the sum', 1.5 === (float) ( $line['units'][ $tin->get_id() ]['amount'] ?? 0 ) );
	$ok( 'the packet is named', false !== strpos( $html, 'ZZ T Dosentomaten' ) );

	$json = $ask( array( 'shopping' => 1, 'show_details' => 1, 'sort_by_category' => 1 ) );
	$ok( 'the answer carries the list', is_string( $json['data']['shopping'] ?? null ) && '' !== $json['data']['shopping'] );
	$ok( 'the same list the first paint shows', false !== strpos( $html, (string) ( $json['data']['shopping'] ?? 'x' ) ) );

	$json = $ask( array() );
	$ok( 'not asked, not computed', array_key_exists( 'shopping', (array) ( $json['data'] ?? array() ) ) && null === $json['data']['shopping'] );
	$ok( 'the nutrition answer is unchanged', ! empty( $json['data']['html'] ) );

	$json = $ask( array( 'shopping' => 1, 'show_details' => 0, 'sort_by_category' => 0 ) );
	$ok( 'without details: no disclosure per line', false === strpos( (string) $json['data']['shopping'], '<details>' ) );

	echo PHP_EOL . '-- The list the saved plan will print' . PHP_EOL;

	$plan_id = $make_post( array( 'post_type' => 'mmm_meal_plan', 'post_title' => 'T Plan' ) );
	update_post_meta( $plan_id, 'start_day', 1 );
	update_post_meta( $plan_id, 'end_day', 2 );
	foreach ( $items as $item ) {
		$meal    = Mixmymeals_Meal::create( 'T Mahlzeit', $plan_id );
		$trash[] = $meal->get_id();
		$meal->set_recipe_id( $item['recipe_id'] );
		$meal->set_servings( $item['servings'] );
		$meal->set_type( $item['type'] );
		if ( $item['day'] ) {
			$meal->set_day( $item['day'] );
		}
	}
	Mixmymeals_Meal_Plan::flush_meals_cache( $plan_id );

	$saved = do_shortcode( '[mmm_shopping_list_for_a_single_meal_plan meal_plan_id="' . $plan_id . '" servings_control="0"]' );
	$json  = $ask( array( 'shopping' => 1, 'show_details' => 1, 'sort_by_category' => 1 ) );
	$ok( 'byte for byte the plan page\'s list', '' !== $saved && false !== strpos( (string) $json['data']['shopping'], $saved ) );

	echo PHP_EOL . '-- Live: servings change, a recipe goes' . PHP_EOL;

	$before = (string) $json['data']['shopping'];
	$cart->update_item_by_index( 0, array( 'servings' => 8 ) );
	$json = $ask( array( 'shopping' => 1, 'show_details' => 1, 'sort_by_category' => 1 ) );
	$ok( 'more servings, another list', $before !== (string) $json['data']['shopping'] );
	// 1 + 0.5 + 0.25 tins: 1.75, rounded up to two.
	$ok( 'tins follow: 2× instead of 1.5×', false !== strpos( (string) $json['data']['shopping'], '2× T Dose' ) );

	$cart->remove_by_index( 0 );
	$json = $ask( array( 'shopping' => 1, 'show_details' => 1, 'sort_by_category' => 1 ) );
	$ok( 'the removed recipe is gone from the list', false === strpos( (string) $json['data']['shopping'], 'T Eintopf' ) );
	$ok( 'the remaining one stays', false !== strpos( (string) $json['data']['shopping'], 'T Suppe' ) );

} finally {

	( new Mixmymeals_Cart() )->save_items( array() );
	delete_transient( Mixmymeals_Cart::TRANSIENT_PREFIX . $cookie );

	foreach ( array_reverse( $trash ) as $trash_id ) {
		wp_delete_post( $trash_id, true );
	}
}

echo PHP_EOL . ( $fails ? "$fails FAILED" : 'all passed' ) . PHP_EOL;
