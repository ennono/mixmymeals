<?php
/**
 * Round trip through save_plan_meals(), the way the units box is tested:
 * through the callback, not through the setters.
 */

$fails = 0;
$ok    = function ( $label, $cond ) use ( &$fails ) {
	if ( ! $cond ) {
		$fails++;
	}
	echo ( $cond ? 'ok   ' : 'FAIL ' ) . $label . PHP_EOL;
};

wp_set_current_user( 1 );

// Fixtures.
$recipe_a = wp_insert_post( array( 'post_type' => 'mmm_recipe', 'post_title' => 'ZZTest Recipe A', 'post_status' => 'publish' ) );
$recipe_b = wp_insert_post( array( 'post_type' => 'mmm_recipe', 'post_title' => 'ZZTest Recipe B', 'post_status' => 'publish' ) );
$plan     = wp_insert_post( array( 'post_type' => 'mmm_meal_plan', 'post_title' => 'ZZTest Plan', 'post_status' => 'publish' ) );
update_post_meta( $plan, 'start_day', 6 ); // Sat → Tue, crossing Sunday.
update_post_meta( $plan, 'end_day', 2 );

$meta = new Mixmymeals_Post_Meta();

// A fresh token per post, the way a fresh render supplies one.
$post_rows = function ( array $rows ) use ( $plan, $meta ) {
	if ( isset( $rows['new'] ) && ! isset( $rows['new']['token'] ) ) {
		$rows['new']['token'] = wp_generate_uuid4();
	}

	$_POST                         = array();
	$_POST['mmm_plan_meals_nonce'] = wp_create_nonce( 'mmm_plan_meals_meta_box' );
	$_POST['mmm_plan_meals']       = $rows;
	$meta->save_plan_meals( $plan );
	$_POST = array();
};

$meals_of = function () use ( $plan ) {
	return get_children( array( 'post_parent' => $plan, 'post_type' => 'mmm_meal', 'post_status' => 'any', 'fields' => 'ids' ) );
};

echo "-- 1. The new row creates a meal" . PHP_EOL;
$post_rows( array( 'new' => array( 'recipe_id' => $recipe_a, 'type' => 'lunch', 'day' => 7, 'servings' => 3 ) ) );
$ids = array_values( $meals_of() );
$ok( 'one meal exists', 1 === count( $ids ) );
$m = new Mixmymeals_Meal( $ids[0] );
$ok( 'recipe stored', (int) $m->get_recipe_id() === (int) $recipe_a );
$ok( 'type stored', 'lunch' === $m->get_type() );
$ok( 'day stored', 7 === $m->get_day() );
$ok( 'servings stored', 3 === $m->get_servings() );
$ok( 'titled after the recipe', 'ZZTest Recipe A' === get_post( $ids[0] )->post_title );

echo "-- 2. Editing a row in place" . PHP_EOL;
$post_rows( array( $ids[0] => array( 'recipe_id' => $recipe_b, 'type' => 'dinner', 'day' => 1, 'servings' => 5 ) ) );
$m = new Mixmymeals_Meal( $ids[0] );
$ok( 'recipe changed', (int) $m->get_recipe_id() === (int) $recipe_b );
$ok( 'type changed', 'dinner' === $m->get_type() );
$ok( 'day changed', 1 === $m->get_day() );
$ok( 'servings changed', 5 === $m->get_servings() );
$ok( 'still one meal', 1 === count( $meals_of() ) );

echo "-- 3. An empty recipe keeps the stored one" . PHP_EOL;
$post_rows( array( $ids[0] => array( 'recipe_id' => '', 'type' => 'dinner', 'day' => 1, 'servings' => 5 ) ) );
$ok( 'recipe kept', (int) ( new Mixmymeals_Meal( $ids[0] ) )->get_recipe_id() === (int) $recipe_b );

echo "-- 4. An unknown type is refused, the rest of the row still applies" . PHP_EOL;
$post_rows( array( $ids[0] => array( 'type' => 'brunchish', 'servings' => 4 ) ) );
$m = new Mixmymeals_Meal( $ids[0] );
$ok( 'type untouched', 'dinner' === $m->get_type() );
$ok( 'servings applied', 4 === $m->get_servings() );

echo "-- 5. The empty day clears it" . PHP_EOL;
$post_rows( array( $ids[0] => array( 'day' => '' ) ) );
$ok( 'day cleared', null === ( new Mixmymeals_Meal( $ids[0] ) )->get_day() );
$ok( 'no meta row left', '' === get_post_meta( $ids[0], '_mmm_day', true ) );

echo "-- 6. A foreign meal is not touched" . PHP_EOL;
$other_plan = wp_insert_post( array( 'post_type' => 'mmm_meal_plan', 'post_title' => 'ZZTest Other Plan', 'post_status' => 'publish' ) );
$foreign    = Mixmymeals_Meal::create( 'ZZTest Foreign', $other_plan );
$foreign->set_servings( 9 );
$post_rows( array( $foreign->get_id() => array( 'servings' => 1, 'delete' => 1 ) ) );
$ok( 'foreign meal survives', null !== get_post( $foreign->get_id() ) );
$ok( 'foreign servings untouched', 9 === ( new Mixmymeals_Meal( $foreign->get_id() ) )->get_servings() );

echo "-- 7. Without the nonce nothing is written" . PHP_EOL;
$_POST                   = array( 'mmm_plan_meals' => array( $ids[0] => array( 'servings' => 12 ) ) );
$meta->save_plan_meals( $plan );
$_POST = array();
$ok( 'unsigned post ignored', 4 === ( new Mixmymeals_Meal( $ids[0] ) )->get_servings() );

echo "-- 8. Delete removes the meal for good" . PHP_EOL;
$post_rows( array( 'new' => array( 'recipe_id' => $recipe_a, 'type' => 'breakfast', 'day' => 6, 'servings' => 2 ) ) );
$ids = array_values( $meals_of() );
$ok( 'two meals', 2 === count( $ids ) );
$post_rows( array( $ids[0] => array( 'delete' => 1 ) ) );
$ok( 'one left', 1 === count( $meals_of() ) );
$ok( 'not in the trash', null === get_post( $ids[0] ) );

echo "-- 9. A row naming no recipe adds nothing" . PHP_EOL;
$before = count( $meals_of() );
$post_rows( array( 'new' => array( 'recipe_id' => '', 'type' => 'lunch', 'day' => 6, 'servings' => 2 ) ) );
$ok( 'nothing added', $before === count( $meals_of() ) );

echo "-- 10. The new row refuses a post that is not a recipe" . PHP_EOL;
$post_rows( array( 'new' => array( 'recipe_id' => $plan, 'type' => 'lunch' ) ) );
$ok( 'plan not planned as a recipe', $before === count( $meals_of() ) );

echo "-- 11. The same new row twice plans one meal, not two" . PHP_EOL;
$before = count( $meals_of() );
$row    = array( 'recipe_id' => $recipe_a, 'type' => 'snack', 'day' => 2, 'servings' => 2, 'token' => wp_generate_uuid4() );
$post_rows( array( 'new' => $row ) );
$post_rows( array( 'new' => $row ) );
$ok( 'exactly one added', $before + 1 === count( $meals_of() ) );
$ok( 'a row without a token adds nothing', ( function () use ( $post_rows, $meals_of, $recipe_a ) {
	$n = count( $meals_of() );
	$post_rows( array( 'new' => array( 'recipe_id' => $recipe_a, 'token' => '' ) ) );
	return $n === count( $meals_of() );
} )() );

echo "-- 12. The box renders the plan's own day order" . PHP_EOL;
ob_start();
$meta->render_meal_plan_overview_meta_box( get_post( $plan ) );
$html = ob_get_clean();
preg_match( '/name="mmm_plan_meals\[new\]\[day\]"[^>]*>(.*?)<\/select>/s', $html, $sel );
preg_match_all( '/value="(\d)"/', $sel[1] ?? '', $opts );
$ok( "the plan's own day order, crossing Sunday", array( '6', '7', '1', '2' ) === $opts[1] );
$ok( 'a day outside the plan is not offered', ! in_array( '3', $opts[1], true ) );
$ok( 'carries its own nonce', false !== strpos( $html, 'mmm_plan_meals_nonce' ) );
$ok( 'carries a new row', false !== strpos( $html, 'mmm_plan_meals[new][recipe_id]' ) );
$ok( 'carries a one-shot token', false !== strpos( $html, 'mmm_plan_meals[new][token]' ) );

echo "-- 13. A day outside the plan keeps its place in the list" . PHP_EOL;
$ids = array_values( $meals_of() );
update_post_meta( $ids[0], '_mmm_day', 4 ); // Thursday, outside Sat–Tue.
ob_start();
$meta->render_meal_plan_overview_meta_box( get_post( $plan ) );
$html = ob_get_clean();
$ok( 'Thursday offered and selected', (bool) preg_match( '/value="4"\s+selected/', $html ) );
$post_rows( array( $ids[0] => array( 'servings' => 2 ) ) );
$ok( 'saving does not move it', 4 === ( new Mixmymeals_Meal( $ids[0] ) )->get_day() );

echo "-- 14. The same table through AJAX: one row written" . PHP_EOL;

/*
 * wp_send_json ends in a bare `die` unless WordPress thinks it is doing AJAX;
 * with that it goes through wp_die, which a filter can take over. Without
 * both, the first handler called would end the script.
 */
if ( ! defined( 'DOING_AJAX' ) ) {
	define( 'DOING_AJAX', true );
}

$thrower = static function () {
	return static function () {
		throw new \RuntimeException( 'wp_die' );
	};
};
add_filter( 'wp_die_handler', $thrower );
add_filter( 'wp_die_ajax_handler', $thrower );

// One handler call: the row's fields under the names the box renders them
// with, which is exactly what the script posts.
$ajax = function ( string $method, $meal_id, array $row = array(), $nonce = null ) use ( $plan, $meta ) {
	$_POST = array(
		'nonce'          => null === $nonce ? wp_create_nonce( 'mmm_plan_meals_ajax' ) : $nonce,
		'plan_id'        => $plan,
		'meal_id'        => (string) $meal_id,
		'mmm_plan_meals' => array( (string) $meal_id => $row ),
	);
	$_REQUEST = $_POST;

	ob_start();
	try {
		$meta->$method();
	} catch ( \RuntimeException $e ) {
		unset( $e );
	}
	$raw   = ob_get_clean();
	$_POST = array();

	return json_decode( $raw, true );
};

// People on the plan, so the eaters column and the surplus note are in play.
( new Mixmymeals_Meal_Plan( $plan ) )->set_eaters( array(
	array( 'name' => 'ZZTest One', 'portions' => 1 ),
	array( 'name' => 'ZZTest Two', 'portions' => 0.5 ),
) );

$ids    = array_values( $meals_of() );
$held   = count( $ids );
$answer = $ajax(
	'ajax_save_plan_meal',
	$ids[0],
	array(
		'recipe_id'    => $recipe_b,
		'type'         => 'breakfast',
		'day'          => 7,
		'servings'     => 5,
		'eaters_shown' => 1,
		'eaters'       => array( 'p1' ),
		'portions'     => array( 'p1' => '' ),
	)
);

$ok( 'the write is accepted', ! empty( $answer['success'] ) );
$m = new Mixmymeals_Meal( $ids[0] );
$ok( 'recipe written', (int) $m->get_recipe_id() === (int) $recipe_b );
$ok( 'type written', 'breakfast' === $m->get_type() );
$ok( 'day written', 7 === $m->get_day() );
$ok( 'servings written', 5 === $m->get_servings() );
$ok( 'eaters written', array( 'p1' ) === $m->get_eaters() );
$ok( 'writing a row creates none', $held === count( $meals_of() ) );

// The answer is the row re-rendered, which is the whole point: a changed
// recipe brings its own diet options and changed eaters their own minimum.
$row_html = (string) ( $answer['data']['html'] ?? '' );
$ok( 'the answer is that row', false !== strpos( $row_html, 'data-id="' . $ids[0] . '"' ) );
$ok( 'the answer carries the written servings', false !== strpos( $row_html, 'value="5"' ) );
$ok( 'the answer carries the eaters column', false !== strpos( $row_html, 'mmm-plan-meals__eater' ) );
$ok( 'the answer notes the surplus', false !== strpos( $row_html, 'mmm-plan-meals__surplus' ) );
$ok( 'the answer carries a delete button', false !== strpos( $row_html, 'mmm-plan-meals__delete"' ) );

echo "-- 15. Adding through AJAX, once per press" . PHP_EOL;
$before = count( $meals_of() );
$new    = array(
	'recipe_id'    => $recipe_a,
	'type'         => 'lunch',
	'day'          => 6,
	'servings'     => '',
	'eaters_shown' => 1,
	'eaters'       => array( 'p1', 'p2' ),
	'portions'     => array( 'p1' => '', 'p2' => '' ),
	'token'        => wp_generate_uuid4(),
);

$first = $ajax( 'ajax_save_plan_meal', 'new', $new );
$ok( 'the add is accepted', ! empty( $first['success'] ) );
$ok( 'one meal more', $before + 1 === count( $meals_of() ) );

$added = new Mixmymeals_Meal( (int) $first['data']['id'] );
$ok( 'the new meal has the picked recipe', (int) $added->get_recipe_id() === (int) $recipe_a );
$ok( 'the new meal has the picked day', 6 === $added->get_day() );
// One portion and a half, rounded up.
$ok( 'an empty servings field becomes what its eaters eat', 2 === $added->get_servings() );

/*
 * The one-shot token is deliberately not spent here. It guards against the
 * block editor posting the meta boxes twice on one save of the post; a button
 * pressed twice means two meals, and spending it would swallow the second.
 */
$second = $ajax( 'ajax_save_plan_meal', 'new', $new );
$ok( 'the same row twice plans two meals', ! empty( $second['success'] ) );
$ok( 'and the second is a meal of its own', (int) $second['data']['id'] !== (int) $first['data']['id'] );
$ok( 'two meals more than before', $before + 2 === count( $meals_of() ) );

echo "-- 16. What AJAX refuses" . PHP_EOL;
$no_recipe = $ajax( 'ajax_save_plan_meal', 'new', array( 'recipe_id' => '', 'type' => 'lunch' ) );
$ok( 'no recipe is refused', isset( $no_recipe['success'] ) && false === $no_recipe['success'] );

$not_recipe = $ajax( 'ajax_save_plan_meal', 'new', array( 'recipe_id' => $plan ) );
$ok( 'a plan is not planned as a recipe', isset( $not_recipe['success'] ) && false === $not_recipe['success'] );

$outsider = $ajax( 'ajax_save_plan_meal', $foreign->get_id(), array( 'servings' => 1 ) );
$ok( "a meal of another plan is refused", isset( $outsider['success'] ) && false === $outsider['success'] );
$ok( 'and untouched', 9 === ( new Mixmymeals_Meal( $foreign->get_id() ) )->get_servings() );

$unsigned = $ajax( 'ajax_save_plan_meal', $ids[0], array( 'servings' => 12 ), 'not-the-nonce' );
$ok( 'a wrong nonce is refused', null === $unsigned || empty( $unsigned['success'] ) );
$ok( 'and writes nothing', 5 === ( new Mixmymeals_Meal( $ids[0] ) )->get_servings() );

echo "-- 17. Deleting through AJAX" . PHP_EOL;
$doomed = (int) $second['data']['id'];
$count  = count( $meals_of() );
$gone   = $ajax( 'ajax_delete_plan_meal', $doomed );
$ok( 'the delete is accepted', ! empty( $gone['success'] ) );
$ok( 'one meal fewer', $count - 1 === count( $meals_of() ) );
$ok( 'gone for good, not in the trash', null === get_post( $doomed ) );

$twice = $ajax( 'ajax_delete_plan_meal', $doomed );
$ok( 'deleting it again is refused', isset( $twice['success'] ) && false === $twice['success'] );

$outsider = $ajax( 'ajax_delete_plan_meal', $foreign->get_id() );
$ok( 'a meal of another plan cannot be deleted from here', isset( $outsider['success'] ) && false === $outsider['success'] );
$ok( 'and survives', null !== get_post( $foreign->get_id() ) );

// Clean up.
foreach ( $meals_of() as $id ) {
	wp_delete_post( $id, true );
}
wp_delete_post( $foreign->get_id(), true );
foreach ( array( $plan, $other_plan, $recipe_a, $recipe_b ) as $id ) {
	wp_delete_post( $id, true );
}

echo PHP_EOL . ( $fails ? "$fails FAILURES" : 'all assertions passed' ) . PHP_EOL;
