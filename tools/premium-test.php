<?php
/**
 * Premium plans: open with an access code only (Mixmymeals_Meal_Plan,
 * premium plans), and the Printer's [mmm_plan_pdf] for whoever they open to.
 */

$fails = 0;
$ok    = function ( $label, $cond ) use ( &$fails ) {
	if ( ! $cond ) {
		$fails++;
	}
	echo ( $cond ? 'ok   ' : 'FAIL ' ) . $label . PHP_EOL;
};

$reset = static function () {
	foreach ( array( 'access_checks' => array(), 'access_plans' => array(), 'access_use_recorded' => false ) as $name => $value ) {
		$prop = new ReflectionProperty( 'Mixmymeals_Meal_Plan', $name );
		$prop->setAccessible( true );
		$prop->setValue( null, $value );
	}
};

$with_code = static function ( string $code ) use ( $reset ) {
	unset( $_GET['mmm_key'], $_POST['mmm_key'] );
	if ( '' !== $code ) {
		$_GET['mmm_key'] = $code;
	}
	$reset();
};

$rest = static function ( string $method, string $route, array $params = array() ) {
	$request = new WP_REST_Request( $method, '/mixmymeals/v1' . $route );
	$request->set_body_params( $params );

	return rest_get_server()->dispatch( $request );
};

$password = static fn( int $id ): string => (string) get_post_field( 'post_password', $id, 'raw' );

wp_set_current_user( 1 );
Mixmymeals_Meal_Plan::maybe_install_access_table();

$plan  = wp_insert_post( array( 'post_type' => 'mmm_meal_plan', 'post_title' => 'T Premium Plan', 'post_status' => 'publish' ) );
$other = wp_insert_post( array( 'post_type' => 'mmm_meal_plan', 'post_title' => 'T Premium Anderer', 'post_status' => 'publish' ) );

echo '-- 1. The switch' . PHP_EOL;
$ok( 'a plan is not premium by default', ! Mixmymeals_Meal_Plan::is_premium( $plan ) );
Mixmymeals_Meal_Plan::set_premium( $plan, true );
$ok( 'switched on, it is premium', Mixmymeals_Meal_Plan::is_premium( $plan ) );
$ok( 'and carries a random password', strlen( $password( $plan ) ) === 32 );
$ok( 'so every password rule holds', Mixmymeals_Post_Types::has_password( $plan ) );

$before = $password( $plan );
wp_update_post( array( 'ID' => $plan, 'post_password' => '' ) );
$ok( 'a save sending no password keeps it', $password( $plan ) === $before );

Mixmymeals_Meal_Plan::set_premium( $plan, false );
$ok( 'switched off, the password is gone', '' === $password( $plan ) && ! Mixmymeals_Meal_Plan::is_premium( $plan ) );
wp_update_post( array( 'ID' => $plan, 'post_password' => '' ) );
$ok( 'an ordinary plan may stay public', '' === $password( $plan ) );
Mixmymeals_Meal_Plan::set_premium( $plan, true );

echo '-- 2. Who gets in' . PHP_EOL;
$key  = Mixmymeals_Meal_Plan::create_access_key( $plan, 'ZZ-PREM' );
$code = $key['code'];
$key2 = Mixmymeals_Meal_Plan::create_access_key( $other, 'ZZ-PREM2' );
Mixmymeals_Meal_Plan::set_premium( $other, true );

$ok( 'an editor passes without a code', ! Mixmymeals_Post_Types::is_locked( $plan ) );

wp_set_current_user( 0 );
$with_code( '' );
$ok( 'a visitor without a code is locked out', Mixmymeals_Post_Types::is_locked( $plan ) );

$form = get_the_password_form( get_post( $plan ) );
$ok( 'the page offers the code field', false !== strpos( $form, 'data-access-form' ) );
$ok( 'and no password field', false === strpos( $form, 'post_password' ) && false === strpos( $form, 'mmm-access__password' ) );

$with_code( $code );
$ok( 'its code opens it', ! Mixmymeals_Post_Types::is_locked( $plan ) );
$with_code( $key2['code'] );
$ok( 'another plan\'s code does not', Mixmymeals_Post_Types::is_locked( $plan ) );

echo '-- 3. REST' . PHP_EOL;
wp_set_current_user( 1 );
$with_code( '' );
$res = $rest( 'POST', '/meal-plans/' . $other, array( 'premium' => false ) );
$ok( 'premium is read and written', 200 === $res->get_status() && false === $res->get_data()['premium'] && '' === $password( $other ) );
$res = $rest( 'POST', '/meal-plans/' . $other, array( 'premium' => true ) );
$ok( 'and switched back on', true === $res->get_data()['premium'] && '' !== $password( $other ) );

echo '-- 4. The PDF link (Printer)' . PHP_EOL;
if ( ! class_exists( 'Mixmymeals_Printer_Plan_PDF' ) || ! shortcode_exists( 'mmm_plan_pdf' ) ) {
	$ok( 'the Printer registers [mmm_plan_pdf]', false );
} else {
	$link = static fn( int $id ): string => do_shortcode( '[mmm_plan_pdf post_id="' . $id . '"]' );

	$ok( 'an editor gets the link', false !== strpos( $link( $plan ), 'action=mmm_premium_pdf' ) );

	wp_set_current_user( 0 );
	$with_code( '' );
	$ok( 'a visitor without a code gets nothing', '' === $link( $plan ) );

	$with_code( $code );
	$html = $link( $plan );
	$ok( 'with the code the link carries it', false !== strpos( $html, 'mmm_key=' . rawurlencode( $code ) ) || false !== strpos( $html, 'mmm_key=' . $code ) );

	Mixmymeals_Meal_Plan::set_premium( $other, false );
	$with_code( '' );
	$ok( 'an ordinary plan gets nothing', '' === $link( $other ) );

	// The limit per code.
	$limit = new ReflectionMethod( 'Mixmymeals_Printer_Plan_PDF', 'premium_within_limit' );
	$limit->setAccessible( true );
	delete_transient( 'mmp_pdf_code_' . $key['id'] );
	$granted = 0;
	for ( $i = 0; $i < Mixmymeals_Printer_Plan_PDF::PREMIUM_PER_HOUR + 2; $i++ ) {
		$granted += $limit->invoke( null, (int) $key['id'] ) ? 1 : 0;
	}
	$ok( 'a code gets ' . Mixmymeals_Printer_Plan_PDF::PREMIUM_PER_HOUR . ' PDFs an hour', Mixmymeals_Printer_Plan_PDF::PREMIUM_PER_HOUR === $granted );
	delete_transient( 'mmp_pdf_code_' . $key['id'] );

	// The template of the buyers' PDF, chosen per plan in the PDF box.
	wp_set_current_user( 1 );
	$templates = array_keys( Mixmymeals_Printer_Templates::choices() );
	$other_tpl = '';
	foreach ( $templates as $id ) {
		if ( Mixmymeals_Printer_Templates::current() !== $id ) {
			$other_tpl = $id;
			break;
		}
	}
	$ok( 'without a choice the download uses the settings\' template', '' === Mixmymeals_Printer_Plan_PDF::premium_template( $plan ) );

	$save = static function ( string $value ) use ( $plan ) {
		$_POST['mmp_premium_template_nonce'] = wp_create_nonce( 'mmp_premium_template' );
		$_POST['mmp_premium_template']       = $value;
		Mixmymeals_Printer_Plan_PDF::save_premium_template( $plan );
		unset( $_POST['mmp_premium_template_nonce'], $_POST['mmp_premium_template'] );
	};

	$save( $other_tpl );
	$ok( 'a chosen template is stored', '' !== $other_tpl && Mixmymeals_Printer_Plan_PDF::premium_template( $plan ) === $other_tpl );

	list( , $settings ) = Mixmymeals_Printer_Plan_PDF::build( $plan, Mixmymeals_Printer_Plan_PDF::premium_template( $plan ) );
	$ok( 'and the download is built in it', $settings['template'] === $other_tpl );

	$save( 'zz-gibt-es-nicht' );
	$ok( 'an unknown template clears the choice', '' === Mixmymeals_Printer_Plan_PDF::premium_template( $plan ) && '' === get_post_meta( $plan, Mixmymeals_Printer_Plan_PDF::PREMIUM_TEMPLATE_META, true ) );

	update_post_meta( $plan, Mixmymeals_Printer_Plan_PDF::PREMIUM_TEMPLATE_META, 'zz-entfernt' );
	$ok( 'a template removed since reads as the settings\' one', '' === Mixmymeals_Printer_Plan_PDF::premium_template( $plan ) );

	ob_start();
	Mixmymeals_Printer_Plan_PDF::render_premium_template_field( get_post( $plan ) );
	$box = ob_get_clean();
	$ok( 'the PDF box offers the picker', false !== strpos( $box, 'name="mmp_premium_template"' ) && false !== strpos( $box, 'value="' . $other_tpl . '"' ) );
}

// Clean up.
wp_set_current_user( 1 );
$with_code( '' );
wp_delete_post( $plan, true );
wp_delete_post( $other, true );

echo PHP_EOL . ( $fails ? "$fails FAILED" : 'all passed' ) . PHP_EOL;
