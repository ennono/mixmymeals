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

// Clean up.
delete_transient( Mixmymeals_Cart::SHARE_PREFIX . $token );
$cart->clear();
wp_delete_post( $r1, true );
update_option( 'mixmymeals_max_items_in_cart', $limit_before );

echo PHP_EOL . ( $fails ? "$fails FAILURES" : 'all assertions passed' ) . PHP_EOL;
