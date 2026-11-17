<?php
/**
 * Access codes: a sold PDF opens its password-protected plan through a code
 * in the address (Mixmymeals_Meal_Plan, access codes).
 */

$fails = 0;
$ok    = function ( $label, $cond ) use ( &$fails ) {
	if ( ! $cond ) {
		$fails++;
	}
	echo ( $cond ? 'ok   ' : 'FAIL ' ) . $label . PHP_EOL;
};

// Each check is memoised per request; a test that changes a row behind the
// API's back starts over.
$reset = static function () {
	foreach ( array( 'access_checks' => array(), 'access_plans' => array(), 'access_use_recorded' => false ) as $name => $value ) {
		$prop = new ReflectionProperty( 'Mixmymeals_Meal_Plan', $name );
		$prop->setAccessible( true );
		$prop->setValue( null, $value );
	}
};

$with_code = static function ( string $code, string $via = 'get' ) use ( $reset ) {
	unset( $_GET['mmm_key'], $_POST['mmm_key'] );
	if ( '' !== $code ) {
		if ( 'post' === $via ) {
			$_POST['mmm_key'] = $code;
		} else {
			$_GET['mmm_key'] = $code;
		}
	}
	$reset();
};

$rest = static function ( string $method, string $route, array $params = array() ) {
	$request = new WP_REST_Request( $method, '/mixmymeals/v1' . $route );
	if ( 'GET' === $method ) {
		$request->set_query_params( $params );
	} else {
		$request->set_body_params( $params );
	}

	return rest_get_server()->dispatch( $request );
};

global $wpdb;
$table = Mixmymeals_Meal_Plan::access_table();

wp_set_current_user( 1 );
delete_transient( 'mmm_rl_access_' . wp_hash( 'unknown' ) );

$recipe = wp_insert_post( array( 'post_type' => 'mmm_recipe', 'post_title' => 'T Zugang Rezept', 'post_status' => 'publish' ) );
$sold   = wp_insert_post( array( 'post_type' => 'mmm_meal_plan', 'post_title' => 'T Zugang Plan', 'post_status' => 'publish', 'post_password' => 'zz-geheim' ) );
$other  = wp_insert_post( array( 'post_type' => 'mmm_meal_plan', 'post_title' => 'T Zugang Anderer', 'post_status' => 'publish', 'post_password' => 'zz-anders' ) );
$meal   = Mixmymeals_Meal::create( 'T Zugang Mahl', $sold );
$meal->set_recipe_id( $recipe );
$meal_id = $meal->get_id();

echo '-- 1. Reading a code' . PHP_EOL;
Mixmymeals_Meal_Plan::maybe_install_access_table();
$ok( 'the table exists', $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) );

$key  = Mixmymeals_Meal_Plan::create_access_key( $sold, 'ZZ-1001' );
$code = is_array( $key ) ? $key['code'] : '';
$ok( 'a code is issued', is_array( $key ) && 1 === preg_match( '/^[0-9A-HJKMNP-TV-Z]{4}-[0-9A-HJKMNP-TV-Z]{4}-[0-9A-HJKMNP-TV-Z]{3}$/', $code ) );
$ok( 'its link opens the plan', is_array( $key ) && false !== strpos( $key['url'], 'mmm_key=' . $code ) );
$ok( 'only the hash and four characters are stored', ! (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$table} WHERE key_hash = %s OR key_hash = %s", $code, str_replace( '-', '', $code ) ) ) && substr( $code, 0, 4 ) === $key['hint'] );

$plain = str_replace( '-', '', $code );
$messy = strtolower( substr( $plain, 0, 4 ) . ' ' . substr( $plain, 4, 4 ) . '  ' . substr( $plain, 8 ) );
$messy = strtr( $messy, array( '0' => 'o', '1' => 'l' ) );
$ok( 'case, spaces, O and L do not matter', Mixmymeals_Meal_Plan::normalise_access_code( $messy ) === array( 'status' => 'ok', 'code' => $plain ) );

// The check character catches 31 slips in 32; the rest read as unknown.
$alphabet = Mixmymeals_Meal_Plan::ACCESS_ALPHABET;
$typos    = 0;
for ( $n = 0; $n < 320; $n++ ) {
	$i     = $n % 10;
	$wrong = $plain;
	$wrong[ $i ] = $alphabet[ ( strpos( $alphabet, $plain[ $i ] ) + 1 + intdiv( $n, 10 ) % 31 ) % 32 ];
	$typos += 'typo' === Mixmymeals_Meal_Plan::normalise_access_code( $wrong )['status'] ? 1 : 0;
}
$ok( 'a wrong character is told as a typo (' . $typos . '/320)', $typos >= 280 );
$ok( 'too short is no code', 'malformed' === Mixmymeals_Meal_Plan::normalise_access_code( 'K7F2-9QX' )['status'] );
$ok( 'nothing is empty', 'empty' === Mixmymeals_Meal_Plan::normalise_access_code( ' - ' )['status'] );

echo '-- 2. The lock' . PHP_EOL;
$with_code( '' );
$ok( 'without a code the plan is locked', Mixmymeals_Post_Types::is_locked( $sold ) );

$with_code( $code );
$ok( 'the code opens the plan', ! Mixmymeals_Post_Types::is_locked( $sold ) );
$ok( 'and its meal', ! Mixmymeals_Post_Types::is_locked( $meal_id ) );
$ok( 'not another plan', Mixmymeals_Post_Types::is_locked( $other ) );
$ok( 'has_password() still says protected', Mixmymeals_Post_Types::has_password( $sold ) );

$with_code( $messy );
$ok( 'typed sloppily it opens it too', ! Mixmymeals_Post_Types::is_locked( $sold ) );

$with_code( $code, 'post' );
$ok( 'posted (AJAX) it opens it too', ! Mixmymeals_Post_Types::is_locked( $sold ) );

$with_code( 'ZZZZ-ZZZZ-ZZZ' );
$status = Mixmymeals_Meal_Plan::requested_access()['status'];
$ok( 'a made-up code opens nothing (' . $status . ')', Mixmymeals_Post_Types::is_locked( $sold ) && in_array( $status, array( 'typo', 'unknown' ), true ) );

echo '-- 3. Links' . PHP_EOL;
$with_code( $code );
$ok( 'the plan link carries the code', false !== strpos( (string) get_permalink( $sold ), 'mmm_key=' ) );
$ok( 'the meal link carries the code', false !== strpos( (string) get_permalink( $meal_id ), 'mmm_key=' ) );
$ok( 'another plan\'s link does not', false === strpos( (string) get_permalink( $other ), 'mmm_key=' ) );
$ok( 'the canonical does not', false === strpos( (string) apply_filters( 'get_canonical_url', get_permalink( $sold ), get_post( $sold ) ), 'mmm_key=' ) );
$items = Mixmymeals_Shortcodes::meal_plan_action_items( $sold );
$ok( 'share and mail do not', false === strpos( $items['share']['url'], 'mmm_key' ) && false === strpos( $items['mail']['url'], 'mmm_key' ) );
$ok( 'Bring! does', false !== strpos( rawurldecode( $items['bring']['url'] ), 'mmm_key=' ) );
$with_code( '' );
$ok( 'without a code no link carries one', false === strpos( (string) get_permalink( $meal_id ), 'mmm_key=' ) );

echo '-- 4. The form' . PHP_EOL;
$with_code( '' );
$form = Mixmymeals_Post_Types::password_form( $meal_id );
$ok( 'a plan sold with codes asks for the code', false !== strpos( $form, 'data-access-plan="' . $sold . '"' ) && false !== strpos( $form, 'name="mmm_key"' ) );
$ok( 'the password stays behind a fold', false !== strpos( $form, 'mmm-access__password' ) && false !== strpos( $form, 'post_password' ) );
$ok( 'a plan without codes asks for the password only', false === strpos( Mixmymeals_Post_Types::password_form( $other ), 'mmm-access' ) );
$with_code( 'ZZZZ-ZZZZ-ZZZ' );
$form = Mixmymeals_Post_Types::password_form( $other );
$ok( 'a wrong code is answered with a reason', false !== strpos( $form, 'data-access-status=' ) && false !== strpos( $form, 'mmm-access__msg' ) );

echo '-- 5. Revoked, expired, unpublished' . PHP_EOL;
Mixmymeals_Meal_Plan::set_access_key_revoked( $key['id'], true );
$with_code( $code );
$ok( 'a revoked code opens nothing', Mixmymeals_Post_Types::is_locked( $sold ) && 'revoked' === Mixmymeals_Meal_Plan::requested_access()['status'] );
Mixmymeals_Meal_Plan::set_access_key_revoked( $key['id'], false );
$with_code( $code );
$ok( 'reactivated it works again', ! Mixmymeals_Post_Types::is_locked( $sold ) );

$wpdb->update( $table, array( 'expires' => gmdate( 'Y-m-d H:i:s', time() - 60 ) ), array( 'id' => $key['id'] ) );
$with_code( $code );
$ok( 'an expired code opens nothing', Mixmymeals_Post_Types::is_locked( $sold ) && 'expired' === Mixmymeals_Meal_Plan::requested_access()['status'] );
$wpdb->update( $table, array( 'expires' => null ), array( 'id' => $key['id'] ) );

wp_update_post( array( 'ID' => $sold, 'post_status' => 'draft' ) );
$with_code( $code );
$ok( 'nor does a code of an unpublished plan', 'unavailable' === Mixmymeals_Meal_Plan::requested_access()['status'] );
wp_update_post( array( 'ID' => $sold, 'post_status' => 'publish' ) );

echo '-- 6. Visits' . PHP_EOL;
$wpdb->update( $table, array( 'uses' => 0, 'last_used' => null ), array( 'id' => $key['id'] ) );
$with_code( $code );
Mixmymeals_Meal_Plan::requested_access();
Mixmymeals_Meal_Plan::requested_access();
$with_code( $code );
Mixmymeals_Meal_Plan::requested_access();
$ok( 'a visit is counted once per half hour', 1 === Mixmymeals_Meal_Plan::get_access_key( $key['id'] )['uses'] );

echo '-- 7. Guessing' . PHP_EOL;
add_filter( 'mixmymeals_access_code_attempts_per_hour', $limit = static fn() => 3 );
foreach ( array( 'AAAA-AAAA-AA1', 'BBBB-BBBB-BB2', 'CCCC-CCCC-CC3' ) as $guess ) {
	$with_code( $guess );
	Mixmymeals_Meal_Plan::requested_access();
}
$with_code( $code );
$ok( 'after the limit even a valid code waits', 'throttled' === Mixmymeals_Meal_Plan::requested_access()['status'] );
remove_filter( 'mixmymeals_access_code_attempts_per_hour', $limit );
delete_transient( 'mmm_rl_access_' . wp_hash( 'unknown' ) );
$with_code( $code );
$ok( 'and after the hour it works again', 'ok' === Mixmymeals_Meal_Plan::requested_access()['status'] );

echo '-- 8. REST' . PHP_EOL;
$with_code( '' );
$res = $rest( 'POST', '/access-keys', array( 'plan_id' => $sold, 'order_ref' => 'ZZ-1002', 'expires' => '2099-12-31' ) );
$new = $res->get_data();
$ok( 'POST /access-keys issues a code', 201 === $res->get_status() && ! empty( $new['code'] ) && array() === $new['warnings'] );
$ok( 'a bare date runs to the end of that day', is_string( $new['expires'] ) && 0 === strpos( wp_date( 'Y-m-d H:i', strtotime( $new['expires'] ) ), '2099-12-31 23:59' ) );
$res = $rest( 'GET', '/access-keys/lookup', array( 'code' => strtolower( $new['code'] ) ) );
$ok( 'lookup finds it as typed', 200 === $res->get_status() && $new['id'] === $res->get_data()['id'] && ! isset( $res->get_data()['code'] ) );
$res = $rest( 'POST', '/access-keys/' . $new['id'], array( 'revoked' => true ) );
$ok( 'it can be revoked', 200 === $res->get_status() && 'revoked' === $res->get_data()['status'] );
$res = $rest( 'GET', '/meal-plans/' . $sold . '/access-keys' );
$ok( 'the plan lists both', 200 === $res->get_status() && 2 === count( $res->get_data()['keys'] ) && true === $res->get_data()['protected'] );
$open = wp_insert_post( array( 'post_type' => 'mmm_meal_plan', 'post_title' => 'T Zugang Offen', 'post_status' => 'publish' ) );
$res  = $rest( 'POST', '/access-keys', array( 'plan_id' => $open ) );
$ok( 'a code for an open plan comes with a warning', 201 === $res->get_status() && in_array( 'plan_not_protected', $res->get_data()['warnings'], true ) );
wp_set_current_user( 0 );
$ok( 'a visitor may not issue codes', in_array( $rest( 'POST', '/access-keys', array( 'plan_id' => $sold ) )->get_status(), array( 401, 403 ), true ) );
wp_set_current_user( 1 );

echo '-- 9. The box on the plan screen (AJAX)' . PHP_EOL;
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

$meta = new Mixmymeals_Post_Meta();
$ajax = static function ( string $method, array $post ) use ( $meta ) {
	$_POST    = array_merge( array( 'nonce' => wp_create_nonce( 'mmm_access_keys_ajax' ) ), $post );
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

$answer = $ajax( 'ajax_create_access_key', array( 'plan_id' => $sold, 'order_ref' => 'ZZ-1003' ) );
$ok( 'the box creates a code and shows it once', ! empty( $answer['success'] ) && 'ok' === Mixmymeals_Meal_Plan::normalise_access_code( $answer['data']['code'] )['status'] && false !== strpos( $answer['data']['row'], 'ZZ-1003' ) );
$made = Mixmymeals_Meal_Plan::find_access_key( (string) ( $answer['data']['code'] ?? '' ) );
$answer = $ajax( 'ajax_revoke_access_key', array( 'plan_id' => $sold, 'key_id' => $made['id'] ?? 0, 'revoke' => 1 ) );
$ok( 'and revokes it', ! empty( $answer['success'] ) && 'revoked' === Mixmymeals_Meal_Plan::get_access_key( (int) $made['id'] )['status'] );
$answer = $ajax( 'ajax_revoke_access_key', array( 'plan_id' => $other, 'key_id' => $made['id'] ?? 0, 'revoke' => 0 ) );
$ok( 'not through another plan', empty( $answer['success'] ) );
wp_set_current_user( 0 );
$answer = $ajax( 'ajax_create_access_key', array( 'plan_id' => $sold ) );
$ok( 'a visitor cannot create one', empty( $answer['success'] ) );
wp_set_current_user( 1 );
remove_filter( 'wp_die_handler', $thrower );
remove_filter( 'wp_die_ajax_handler', $thrower );

echo '-- 10. Privacy and cleanup' . PHP_EOL;
$ok( 'the privacy text describes bought plans', Mixmymeals_Meal_Plan::access_codes_in_use() && false !== strpos( wp_strip_all_tags( Mixmymeals_Privacy::policy_section_html() ), rtrim( __( 'A code is kept as long as its plan.', 'mixmymeals' ), '.' ) ) );

$wpdb->update( $table, array( 'revoked' => gmdate( 'Y-m-d H:i:s', time() - 100 * DAY_IN_SECONDS ) ), array( 'id' => $new['id'] ) );
$ok( 'a code revoked long ago is pruned', 1 === Mixmymeals_Meal_Plan::prune_access_keys() && null === Mixmymeals_Meal_Plan::get_access_key( $new['id'] ) );

wp_delete_post( $sold, true );
$ok( 'deleting the plan deletes its codes', 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE plan_id = %d", $sold ) ) );

// Clean up.
$with_code( '' );
foreach ( array( $meal_id, $other, $open, $recipe ) as $post_id ) {
	wp_delete_post( $post_id, true );
}
delete_transient( 'mmm_rl_access_' . wp_hash( 'unknown' ) );

echo PHP_EOL . ( $fails ? "$fails FAILURES" : 'all assertions passed' ) . PHP_EOL;
