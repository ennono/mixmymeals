<?php
/**
 * Prüft die Wahl des Referenzprodukts auf dem Zutatenbildschirm.
 *
 * Jedes Produkt trägt ein Radio in der Gruppe mmm_products_reference. Ein
 * gewähltes Radio lässt sich im Browser nicht wieder abwählen, und bis 3.48
 * gab es keine Option mit dem Wert 0 — wer einmal ein Produkt als Referenz
 * gesetzt hatte, kam an die eigenen Werte der Zutat nicht mehr heran. Der
 * Rundlauf geht durch Rendern und save_ingredient_products(), so wie das
 * Formular es täte.
 *
 * wp --require=<dbhost.php> eval-file \
 *    wp-content/plugins/mixmymeals/tools/reference-product-test.php
 *
 * @package MixMyMeals
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Zaehler in $GLOBALS: eval-file laeuft in einem Funktionsgeltungsbereich.
$GLOBALS['MMM_REF_PASS'] = 0;
$GLOBALS['MMM_REF_FAIL'] = 0;
$GLOBALS['MMM_REF_META'] = new Mixmymeals_Post_Meta();

/**
 * Eine Zusicherung.
 *
 * @param bool   $ok    Ergebnis.
 * @param string $label Beschreibung.
 * @return void
 */
function mmm_ref_assert( bool $ok, string $label ): void {
	if ( $ok ) {
		++$GLOBALS['MMM_REF_PASS'];
		echo "ok   $label\n";
		return;
	}
	++$GLOBALS['MMM_REF_FAIL'];
	echo "FAIL $label\n";
}

/**
 * Rendert die Produkte-Box einer Zutat.
 *
 * @param int $ingredient_id Zutat.
 * @return string
 */
function mmm_ref_render( int $ingredient_id ): string {
	ob_start();
	$GLOBALS['MMM_REF_META']->render_ingredient_products_meta_box_content( get_post( $ingredient_id ) );
	return (string) ob_get_clean();
}

/**
 * Ob das Radio mit diesem Wert angehakt ist.
 *
 * @param string $html  Markup.
 * @param int    $value Wert des Radios.
 * @return bool
 */
function mmm_ref_checked( string $html, int $value ): bool {
	return (bool) preg_match(
		'/<input type="radio" name="mmm_products_reference" value="' . $value . '"\s+checked=/',
		$html
	);
}

/**
 * Speichert die Box so, wie das Formular sie absendet.
 *
 * @param int      $ingredient_id Zutat.
 * @param int|null $reference     Gewaehltes Radio, null = keines gesendet.
 * @return void
 */
function mmm_ref_save( int $ingredient_id, ?int $reference ): void {
	$_POST                                  = array();
	$_POST['mmm_ingredient_products_nonce'] = wp_create_nonce( 'mmm_ingredient_products_meta_box' );
	if ( null !== $reference ) {
		$_POST['mmm_products_reference'] = (string) $reference;
	}
	$GLOBALS['MMM_REF_META']->save_ingredient_products( $ingredient_id );
	$_POST = array();
}

wp_set_current_user( 1 );

$ing = wp_insert_post(
	array(
		'post_type'   => 'mmm_ingredient',
		'post_title'  => 'ZZ Referenztest',
		'post_status' => 'publish',
	)
);
$pkt = wp_insert_post(
	array(
		'post_type'   => 'mmm_product',
		'post_title'  => 'ZZ Paket',
		'post_status' => 'publish',
		'post_parent' => $ing,
	)
);
( new Mixmymeals_Product( $pkt ) )->set_brand( 'ZZ' );

echo "-- 1. Ohne Referenz ist die Option der Zutat gewaehlt\n";
$html = mmm_ref_render( $ing );
mmm_ref_assert( false !== strpos( $html, 'name="mmm_products_reference" value="0"' ), 'es gibt ein Radio mit dem Wert 0' );
mmm_ref_assert( mmm_ref_checked( $html, 0 ), 'und es ist angehakt' );
mmm_ref_assert( ! mmm_ref_checked( $html, $pkt ), 'das Produkt nicht' );

echo "\n-- 2. Produkt als Referenz waehlen\n";
mmm_ref_save( $ing, $pkt );
mmm_ref_assert( $pkt === Mixmymeals_Product::get_reference_id( $ing ), 'die Referenz ist gesetzt' );
$html = mmm_ref_render( $ing );
mmm_ref_assert( mmm_ref_checked( $html, $pkt ), 'das Produkt-Radio ist angehakt' );
mmm_ref_assert( ! mmm_ref_checked( $html, 0 ), 'das der Zutat nicht' );

echo "\n-- 3. Zurueck zu den eigenen Werten der Zutat\n";
mmm_ref_save( $ing, 0 );
mmm_ref_assert( 0 === Mixmymeals_Product::get_reference_id( $ing ), 'die Referenz ist geloescht' );
mmm_ref_assert( mmm_ref_checked( mmm_ref_render( $ing ), 0 ), 'und die Zutat ist wieder gewaehlt' );

wp_delete_post( $pkt, true );
wp_delete_post( $ing, true );

echo "\n=== Ergebnis ===\n";
printf( "  %d bestanden, %d fehlgeschlagen\n", $GLOBALS['MMM_REF_PASS'], $GLOBALS['MMM_REF_FAIL'] );
