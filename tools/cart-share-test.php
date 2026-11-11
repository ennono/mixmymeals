<?php
/**
 * Sharing the planner: the link must say what was planned when it was made,
 * and must never be a key to somebody else's living cart.
 */

$fails = 0;
$ok    = function ( $label, $cond ) use ( &$fails ) {
	if ( ! $cond ) {
		$fails++;
	}
	echo ( $cond ? 'ok   ' : 'FAIL ' ) . $label . PHP_EOL;
};

wp_set_current_user( 1 );
$limit_before = get_option( 'mixmymeals_max_items_in_cart' );
update_option( 'mixmymeals_max_items_in_cart', 20 );

$r1 = wp_insert_post( array( 'post_type' => 'mmm_recipe', 'post_title' => 'ZZ Share A', 'post_status' => 'publish' ) );
$r2 = wp_insert_post( array( 'post_type' => 'mmm_recipe', 'post_title' => 'ZZ Share B', 'post_status' => 'publish' ) );

/*
 * wp_send_json_* ends the process, so the die handler is swapped for one that
 * throws — the same trick the equipment test uses.
 */
$call = function ( string $method, array $post ) {
	$thrower = static function () {
		return static function () {
			throw new \RuntimeException( 'wp_die' );
		};
	};

	$_POST          = $post;
	$_POST['nonce'] = $post['nonce'] ?? wp_create_nonce( 'mmm_frontend_nonce' );
	$_REQUEST       = $_POST;

	add_filter( 'wp_doing_ajax', '__return_true' );
	add_filter( 'wp_die_ajax_handler', $thrower );
	add_filter( 'wp_die_handler', $thrower );
	ob_start();

	try {
		Mixmymeals_Cart::$method();
	} catch ( \Throwable $e ) {
		unset( $e );
	}

	$raw = ob_get_clean();
	remove_filter( 'wp_die_handler', $thrower );
	remove_filter( 'wp_die_ajax_handler', $thrower );
	remove_filter( 'wp_doing_ajax', '__return_true' );
	$_POST    = array();
	$_REQUEST = array();

	$out = json_decode( $raw, true );

	return is_array( $out ) ? $out : array( 'raw' => $raw );
};

$cart = new Mixmymeals_Cart();
$cart->clear();
$cart->save_items( array(
	array( 'recipe_id' => $r1, 'servings' => 4, 'type' => 'lunch', 'day' => 2 ),
	array( 'recipe_id' => $r2, 'servings' => 2, 'type' => '', 'day' => null ),
) );

echo '-- 1. Making a link' . PHP_EOL;
$res   = $call( 'mmm_share_cart_ajax_callback', array() );
$token = $res['data']['token'] ?? '';
$ok( 'answered with a token', ! empty( $token ) );
$ok( 'and it is a uuid4', (bool) wp_is_uuid( $token, 4 ) );
$ok( 'which is not the cart cookie', $token !== ( $_COOKIE[ Mixmymeals_Cart::COOKIE_NAME ] ?? '' ) );

echo '-- 2. The snapshot does not follow the sharer' . PHP_EOL;
$cart->save_items( array( array( 'recipe_id' => $r1, 'servings' => 9, 'type' => 'dinner', 'day' => 7 ) ) );
$snap = get_transient( Mixmymeals_Cart::SHARE_PREFIX . $token );
$ok( 'still holds what was shared', 2 === count( $snap['items'] ) );
$ok( 'with the servings of that moment', 4 === (int) $snap['items'][0]['servings'] );
$ok( 'while the sharer moved on', 1 === count( $cart->get_items() ) );

echo '-- 3. Loading copies it in' . PHP_EOL;
$res = $call( 'mmm_load_shared_cart_ajax_callback', array( 'token' => $token ) );
$ok( 'answered success', ! empty( $res['success'] ) );
$ok( 'two recipes are in the planner', 2 === count( $cart->get_items() ) );
$ok( 'and it reports the count', 2 === (int) ( $res['data']['count'] ?? 0 ) );
$ok( 'the link still works for the next person', is_array( get_transient( Mixmymeals_Cart::SHARE_PREFIX . $token ) ) );

echo '-- 4. What a bad link gets' . PHP_EOL;
$ok( 'a made-up token is refused', empty( $call( 'mmm_load_shared_cart_ajax_callback', array( 'token' => 'nicht-echt' ) )['success'] ) );
$ok( 'a well-formed unknown one too', empty( $call( 'mmm_load_shared_cart_ajax_callback', array( 'token' => wp_generate_uuid4() ) )['success'] ) );
$ok( 'an empty one too', empty( $call( 'mmm_load_shared_cart_ajax_callback', array( 'token' => '' ) )['success'] ) );
$ok( 'and without a nonce nothing happens', empty( $call( 'mmm_load_shared_cart_ajax_callback', array( 'token' => $token, 'nonce' => 'falsch' ) )['success'] ) );

echo '-- 5. A recipe gone since the link was made' . PHP_EOL;
wp_delete_post( $r2, true );
$call( 'mmm_load_shared_cart_ajax_callback', array( 'token' => $token ) );
$ok( 'is skipped, the rest arrives', 1 === count( $cart->get_items() ) );
$ok( 'and it is the one still there', (int) $cart->get_items()[0]['recipe_id'] === $r1 );

echo '-- 6. An empty planner has nothing to share' . PHP_EOL;
$cart->clear();
$ok( 'refused', empty( $call( 'mmm_share_cart_ajax_callback', array() )['success'] ) );

echo '-- 7. Only a published recipe can be planned' . PHP_EOL;
$draft = wp_insert_post( array( 'post_type' => 'mmm_recipe', 'post_title' => 'ZZ Share Entwurf', 'post_status' => 'draft' ) );
$ok( 'a draft is refused', empty( $call( 'mmm_add_recipe_to_cart_ajax_callback', array( 'recipe_id' => $draft ) )['success'] ) );
$ok( 'and nothing is in the planner', 0 === count( $cart->get_items() ) );
$ok( 'a published one is taken', ! empty( $call( 'mmm_add_recipe_to_cart_ajax_callback', array( 'recipe_id' => $r1, 'servings' => 999999, 'type' => 'erfunden', 'day' => 9 ) )['success'] ) );
$item = $cart->get_items()[0] ?? array();
$ok( 'servings stop at the cap', Mixmymeals_Meal_Plan::SERVINGS_MEAL_MAX === (int) ( $item['servings'] ?? 0 ) );
$ok( 'an unknown type is none', '' === ( $item['type'] ?? null ) );
$ok( 'a day outside the week is none', array_key_exists( 'day', $item ) && null === $item['day'] );

echo '-- 8. Changing an item is checked like adding one' . PHP_EOL;
$res  = $call( 'mmm_update_cart_item_ajax_callback', array( 'index' => 0, 'servings' => 777, 'type' => 'frei erfunden', 'day' => 42, 'note' => 'ZZ Notiz' ) );
$item = $cart->get_items()[0] ?? array();
$ok( 'answered success', ! empty( $res['success'] ) );
$ok( 'servings stop at the cap', Mixmymeals_Meal_Plan::SERVINGS_MEAL_MAX === (int) ( $item['servings'] ?? 0 ) );
$ok( 'an unknown type is none', '' === ( $item['type'] ?? null ) );
$ok( 'a day outside the week is none', array_key_exists( 'day', $item ) && null === $item['day'] );
$ok( 'no note is kept', ! array_key_exists( 'note', $item ) );
$call( 'mmm_update_cart_item_ajax_callback', array( 'index' => 0, 'servings' => 3, 'type' => 'lunch', 'day' => 2 ) );
$item = $cart->get_items()[0] ?? array();
$ok( 'valid values are stored', 3 === (int) $item['servings'] && 'lunch' === $item['type'] && 2 === $item['day'] );
$cart->save_items( array( array( 'recipe_id' => $r1, 'servings' => 80, 'type' => '', 'day' => null ) ) );
$call( 'mmm_update_cart_item_ajax_callback', array( 'index' => 0, 'servings' => 80, 'day' => 1 ) );
$ok( 'servings a loaded plan came with are kept', 80 === (int) $cart->get_items()[0]['servings'] );

echo '-- 9. One limit, read in one place' . PHP_EOL;
update_option( 'mixmymeals_max_items_in_cart', 0 );
$ok( 'zero is the default, not one and not unlimited', Mixmymeals_Cart::DEFAULT_MAX_ITEMS === Mixmymeals_Cart::max_items() );
delete_option( 'mixmymeals_max_items_in_cart' );
$ok( 'never saved is the default', Mixmymeals_Cart::DEFAULT_MAX_ITEMS === Mixmymeals_Cart::max_items() );
update_option( 'mixmymeals_max_items_in_cart', 100000 );
$ok( 'the field\'s maximum caps it', Mixmymeals_Cart::MAX_ITEMS_CEILING === Mixmymeals_Cart::max_items() );
update_option( 'mixmymeals_max_items_in_cart', 2 );
$cart->clear();
$cart->add( $r1 );
$cart->add( $r1 );
$ok( 'the planner stops at the limit', false === $cart->add( $r1 ) && 2 === count( $cart->get_items() ) );

echo '-- 10. Sharing is limited per address' . PHP_EOL;
$share_limit = static fn() => 2;
add_filter( 'mixmymeals_share_rate_limit_per_hour', $share_limit );
$share_key = 'mmm_rl_share_ip_' . wp_hash( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) ) );
delete_transient( $share_key );
$made = array();
foreach ( array( 1, 2, 3 ) as $n ) {
	$res = $call( 'mmm_share_cart_ajax_callback', array() );
	if ( ! empty( $res['data']['token'] ) ) {
		$made[] = $res['data']['token'];
	}
}
$ok( 'two copies are made, the third is refused', 2 === count( $made ) && 'rate_limit' === ( $res['data']['code'] ?? '' ) );
remove_filter( 'mixmymeals_share_rate_limit_per_hour', $share_limit );
delete_transient( $share_key );
foreach ( $made as $made_token ) {
	delete_transient( Mixmymeals_Cart::SHARE_PREFIX . $made_token );
}

// Clean up.
wp_delete_post( $draft, true );
delete_transient( Mixmymeals_Cart::SHARE_PREFIX . $token );
$cart->clear();
wp_delete_post( $r1, true );
update_option( 'mixmymeals_max_items_in_cart', $limit_before );

echo PHP_EOL . ( $fails ? "$fails FAILURES" : 'all assertions passed' ) . PHP_EOL;
