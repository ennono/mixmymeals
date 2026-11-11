<?php
/**
 * Password-protected plans: core protects the_content() only, so every other
 * way to a plan — its meals, the listings, REST — has to ask
 * Mixmymeals_Post_Types::is_locked() / has_password() itself.
 */

$fails = 0;
$ok    = function ( $label, $cond ) use ( &$fails ) {
	if ( ! $cond ) {
		$fails++;
	}
	echo ( $cond ? 'ok   ' : 'FAIL ' ) . $label . PHP_EOL;
};

$get = static function ( string $route, array $params = array() ) {
	$request = new WP_REST_Request( 'GET', '/mixmymeals/v1' . $route );
	$request->set_query_params( $params );

	return rest_get_server()->dispatch( $request );
};

wp_set_current_user( 1 );

$recipe = wp_insert_post( array( 'post_type' => 'mmm_recipe', 'post_title' => 'T Passwort Rezept', 'post_status' => 'publish' ) );
$locked = wp_insert_post( array( 'post_type' => 'mmm_meal_plan', 'post_title' => 'T Passwort Plan', 'post_status' => 'publish', 'post_password' => 'zz-geheim' ) );
$open   = wp_insert_post( array( 'post_type' => 'mmm_meal_plan', 'post_title' => 'T Offener Plan', 'post_status' => 'publish' ) );
$meals  = array();

foreach ( array( $locked, $open ) as $plan_id ) {
	$meal = Mixmymeals_Meal::create( 'T Passwort Mahl', $plan_id );
	$meal->set_recipe_id( $recipe );
	$meals[ $plan_id ] = $meal->get_id();
}

echo '-- 1. The one decision' . PHP_EOL;
$ok( 'a plan with a password has one', Mixmymeals_Post_Types::has_password( $locked ) );
$ok( 'its meal is protected by it', Mixmymeals_Post_Types::has_password( $meals[ $locked ] ) );
$ok( 'a plan without has none', ! Mixmymeals_Post_Types::has_password( $open ) );
$ok( 'nor has its meal', ! Mixmymeals_Post_Types::has_password( $meals[ $open ] ) );
$ok( 'without the cookie the plan is locked', Mixmymeals_Post_Types::is_locked( $locked ) );
$ok( 'and so is its meal', Mixmymeals_Post_Types::is_locked( $meals[ $locked ] ) );
$ok( 'the open plan is not', ! Mixmymeals_Post_Types::is_locked( $open ) );
$ok( 'the form is the plan\'s, also for the meal', false !== strpos( Mixmymeals_Post_Types::password_form( $meals[ $locked ] ), 'post_password' ) );

echo '-- 2. Listings' . PHP_EOL;
$args = Mixmymeals_Post_Types::exclude_unlisted( array( 'post_type' => 'mmm_meal_plan', 'post__in' => array( $locked, $open ), 'fields' => 'ids' ) );
$ids  = array_map( 'intval', get_posts( $args ) );
$ok( 'a protected plan is not listed', ! in_array( $locked, $ids, true ) && in_array( $open, $ids, true ) );
$ok( 'a caller who asks for them gets them', true === Mixmymeals_Post_Types::exclude_unlisted( array( 'has_password' => true ) )['has_password'] );
$ok( 'no description is made of it', '' === Mixmymeals_Post_Types::meta_description( $locked ) );

echo '-- 3. REST, as a visitor' . PHP_EOL;
wp_set_current_user( 0 );
$ok( 'the plan is refused', 403 === $get( '/meal-plans/' . $locked )->get_status() );
$ok( 'its meals too', 403 === $get( '/meal-plans/' . $locked . '/meals' )->get_status() );
$ok( 'its shopping list too', 403 === $get( '/meal-plans/' . $locked . '/shopping-list' )->get_status() );
$ok( 'a meal of it too', 403 === $get( '/meals/' . $meals[ $locked ] )->get_status() );
$ok( 'its product impact too', 403 === $get( '/product-impact', array( 'post_id' => $locked ) )->get_status() );
$ok( 'the open plan answers', 200 === $get( '/meal-plans/' . $open )->get_status() );
$listed = wp_list_pluck( (array) $get( '/meal-plans', array( 'per_page' => 100 ) )->get_data(), 'id' );
$ok( 'the listing leaves the protected plan out', ! in_array( $locked, $listed, true ) );

echo '-- 4. REST, as an editor' . PHP_EOL;
wp_set_current_user( 1 );
$ok( 'the plan answers', 200 === $get( '/meal-plans/' . $locked )->get_status() );

echo '-- 5. Subscribing' . PHP_EOL;
wp_set_current_user( 0 );
$sub = Mixmymeals_Subscriptions::create_pending( $locked, 'zz@example.invalid', '' );
$ok( 'nobody subscribes to a plan they are locked out of', is_wp_error( $sub ) );

// Clean up.
wp_set_current_user( 1 );
foreach ( array_merge( array_values( $meals ), array( $locked, $open, $recipe ) ) as $post_id ) {
	wp_delete_post( $post_id, true );
}

echo PHP_EOL . ( $fails ? "$fails FAILURES" : 'all assertions passed' ) . PHP_EOL;
