<?php
/**
 * Round trip through save_ingredient_meta() for the units table on the
 * ingredient: through the callback, not through the setters.
 *
 * The block editor posts meta boxes again on a second Save without
 * re-rendering them, so the filled-in "new" row arrives twice. Only the
 * first may create the unit (HG-105).
 */

$fails = 0;
$ok    = function ( $label, $cond ) use ( &$fails ) {
	if ( ! $cond ) {
		$fails++;
	}
	echo ( $cond ? 'ok   ' : 'FAIL ' ) . $label . PHP_EOL;
};

wp_set_current_user( 1 );

$ingredient = wp_insert_post( array( 'post_type' => 'mmm_ingredient', 'post_title' => 'T Einheiten', 'post_status' => 'draft' ) );
$meta       = new Mixmymeals_Post_Meta();

$post_rows = function ( array $rows ) use ( $ingredient, $meta ) {
	$_POST = array(
		'mmm_ingredient_amounts_meta_nonce' => wp_create_nonce( 'mmm_ingredient_amounts_meta_box' ),
		'mmm_ingredient_amounts'            => $rows,
	);
	$meta->save_ingredient_meta( $ingredient );
	$_POST = array();
};

$units_of = function () use ( $ingredient ) {
	return array_values(
		get_children( array( 'post_parent' => $ingredient, 'post_type' => 'mmm_ingr_unit', 'post_status' => 'any', 'fields' => 'ids' ) )
	);
};

echo '-- 1. The rendered box carries a token' . PHP_EOL;
ob_start();
$meta->render_ingredient_amounts_meta_box_content( get_post( $ingredient ) );
$html = ob_get_clean();
$ok( 'hidden token in the new row', 1 === preg_match( '/name="mmm_ingredient_amounts\[new\]\[token\]" value="[0-9a-f-]{36}"/', $html ) );

echo '-- 2. The new row creates a unit once' . PHP_EOL;
$new = array( 'title' => 'Dose', 'weight' => '400', 'divisor' => '2', 'token' => wp_generate_uuid4() );
$post_rows( array( 'new' => $new ) );
$ids = $units_of();
$ok( 'one unit after the first save', 1 === count( $ids ) );
$unit = new Mixmymeals_Ingredient_Amount( $ids[0] );
$ok( 'title stored', 'Dose' === get_the_title( $ids[0] ) );
$ok( 'weight stored', 400.0 === (float) $unit->get_weight() );
$ok( 'divisor stored', 2 === (int) $unit->get_divisor() );

// Second Save: the same form, unrendered, posted again.
$post_rows( array( 'new' => $new ) );
$ok( 'still one unit after the second save', 1 === count( $units_of() ) );

echo '-- 3. A fresh render adds the next one' . PHP_EOL;
$post_rows( array( 'new' => array( 'title' => 'EL', 'weight' => '15', 'token' => wp_generate_uuid4() ) ) );
$ok( 'two units', 2 === count( $units_of() ) );

echo '-- 4. Without a token nothing is created' . PHP_EOL;
$post_rows( array( 'new' => array( 'title' => 'Prise', 'weight' => '1' ) ) );
$post_rows( array( 'new' => array( 'title' => 'Prise', 'weight' => '1', 'token' => '' ) ) );
$ok( 'still two units', 2 === count( $units_of() ) );

echo '-- 5. Existing rows are still edited on every save' . PHP_EOL;
$post_rows( array( $ids[0] => array( 'title' => 'Dose', 'weight' => '425', 'divisor' => '1' ) ) );
$unit = new Mixmymeals_Ingredient_Amount( $ids[0] );
$ok( 'weight changed', 425.0 === (float) $unit->get_weight() );
$ok( 'divisor changed', 1 === (int) $unit->get_divisor() );

echo '-- 6. "Bought whole" (HG-115)' . PHP_EOL;
ob_start();
$meta->render_ingredient_amounts_meta_box_content( get_post( $ingredient ) );
$html = ob_get_clean();
$ok( 'checkbox and marker per row', false !== strpos( $html, 'name="mmm_ingredient_amounts[' . $ids[0] . '][package]"' ) && false !== strpos( $html, 'name="mmm_ingredient_amounts[' . $ids[0] . '][package_shown]"' ) );
$ok( 'checkbox in the new row', false !== strpos( $html, 'name="mmm_ingredient_amounts[new][package]"' ) );

$post_rows( array( $ids[0] => array( 'title' => 'Dose', 'weight' => '425', 'divisor' => '1', 'package_shown' => '1', 'package' => '1' ) ) );
$ok( 'ticked: stored', ( new Mixmymeals_Ingredient_Amount( $ids[0] ) )->is_package() );

$post_rows( array( $ids[0] => array( 'title' => 'Dose', 'weight' => '425', 'divisor' => '1' ) ) );
$ok( 'column not posted: kept', ( new Mixmymeals_Ingredient_Amount( $ids[0] ) )->is_package() );

$post_rows( array( $ids[0] => array( 'title' => 'Dose', 'weight' => '425', 'divisor' => '1', 'package_shown' => '1' ) ) );
$ok( 'unticked: cleared, no row left', ! ( new Mixmymeals_Ingredient_Amount( $ids[0] ) )->is_package() && '' === get_post_meta( $ids[0], Mixmymeals_Ingredient_Amount::META_PACKAGE, true ) );

$post_rows( array( 'new' => array( 'title' => 'Glas', 'weight' => '350', 'package' => '1', 'token' => wp_generate_uuid4() ) ) );
$glas = array_values( array_diff( $units_of(), $ids ) );
$glas = array_values( array_filter( $glas, static fn( $id ) => 'Glas' === get_the_title( $id ) ) );
$ok( 'new row: ticked on creation', $glas && ( new Mixmymeals_Ingredient_Amount( $glas[0] ) )->is_package() );

$request = new WP_REST_Request( 'POST', '/mixmymeals/v1/units/' . $ids[0] );
$request->set_param( 'is_package', true );
$response = rest_get_server()->dispatch( $request );
$data     = $response->get_data();
$ok( 'REST: writes and reads is_package', 200 === $response->get_status() && true === ( $data['is_package'] ?? null ), wp_json_encode( $data ) );

// Cleanup.
foreach ( $units_of() as $id ) {
	wp_delete_post( $id, true );
}
wp_delete_post( $ingredient, true );

echo PHP_EOL . ( $fails ? "$fails FAILED" : 'all ok' ) . PHP_EOL;
