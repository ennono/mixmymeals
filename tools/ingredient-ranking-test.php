<?php
/**
 * Prüft [mmm_ingredient_ranking] und Mixmymeals_Ingredient::ranking().
 *
 *  1. Reihenfolge nach der Kennzahl pro 100 g, Standardrichtung je Kennzahl.
 *  2. Ohne Nährwerte, ohne die Kennzahl oder mit Passwort fällt heraus.
 *  3. Die Referenzpackung zählt wie auf der Zutatenseite.
 *  4. Ausschlüsse: einzelne Zutaten (ID und Slug), Kategorien; `category`
 *     beschränkt.
 *  5. Ausgabe: Tabelle mit Link und Wert; Fehlkonfiguration nur für
 *     Redakteure sichtbar.
 *
 * Isoliert über zwei eigene Zutatenkategorien. Alles heißt „ZZ …“.
 *
 * wp --require=<dbhost.php> eval-file \
 *    wp-content/plugins/mixmymeals/tools/ingredient-ranking-test.php
 *
 * @package MixMyMeals
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$GLOBALS['MMM_IR'] = array(
	'pass'  => 0,
	'fail'  => 0,
	'posts' => array(),
	'terms' => array(),
);

/**
 * Eine Zusicherung.
 *
 * @param bool   $ok    Ergebnis.
 * @param string $label Beschreibung.
 * @return void
 */
function mmm_ir_assert( bool $ok, string $label ): void {
	++$GLOBALS['MMM_IR'][ $ok ? 'pass' : 'fail' ];
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
}

/**
 * Vergleicht zwei Werte.
 *
 * @param string $label    Beschreibung.
 * @param mixed  $expected Erwartet.
 * @param mixed  $actual   Bekommen.
 * @return void
 */
function mmm_ir_is( string $label, $expected, $actual ): void {
	$e = wp_json_encode( $expected );
	$a = wp_json_encode( $actual );
	mmm_ir_assert( $e === $a, $label . ( $e === $a ? '' : " (erwartet $e, bekommen $a)" ) );
}

/**
 * Legt eine Zutat in einer Kategorie an.
 *
 * @param string $title Titel.
 * @param array  $nutri Nährwerte.
 * @param int    $term  Kategorie.
 * @return int
 */
function mmm_ir_ing( string $title, array $nutri, int $term ): int {
	$id                           = (int) wp_insert_post(
		array(
			'post_type'   => 'mmm_ingredient',
			'post_title'  => $title,
			'post_status' => 'publish',
		)
	);
	$GLOBALS['MMM_IR']['posts'][] = $id;
	if ( $nutri ) {
		( new Mixmymeals_Ingredient( $id ) )->set_nutrition( $nutri );
	}
	wp_set_object_terms( $id, array( $term ), 'mmm_ingredient_category' );
	return $id;
}

/**
 * Die Rangliste der Testkategorie als Titel => Wert.
 *
 * @param string $by      Kennzahl.
 * @param string $order   Richtung.
 * @param array  $cats    Kategorien.
 * @param array  $ex_cats Ausgeschlossene Kategorien.
 * @param array  $ex_ids  Ausgeschlossene Zutaten.
 * @return array
 */
function mmm_ir_rank( string $by, string $order, array $cats, array $ex_cats = array(), array $ex_ids = array() ): array {
	$out = array();
	foreach ( Mixmymeals_Ingredient::ranking( $by, $order, $cats, $ex_cats, $ex_ids ) as $id => $value ) {
		$out[ get_the_title( $id ) ] = $value;
	}
	return $out;
}

try {

	$top                        = wp_insert_term( 'ZZ Rangliste', 'mmm_ingredient_category' );
	$top                        = (int) $top['term_id'];
	$sub                        = wp_insert_term( 'ZZ Rangliste Zwei', 'mmm_ingredient_category' );
	$sub                        = (int) $sub['term_id'];
	$GLOBALS['MMM_IR']['terms'] = array( $sub, $top );

	$a = mmm_ir_ing(
		'ZZ A',
		array(
			'calories_100' => 300,
			'protein'      => 30,
			'salt'         => 1,
		),
		$top
	);
	$b = mmm_ir_ing(
		'ZZ B',
		array(
			'calories_100' => 100,
			'protein'      => 10,
			'salt'         => 0.1,
		),
		$top
	);
	$c = mmm_ir_ing(
		'ZZ C',
		array(
			'calories_100' => 200,
			'protein'      => 20,
		),
		$sub
	);
	$d = mmm_ir_ing( 'ZZ D ohne Werte', array(), $top );
	$e = mmm_ir_ing(
		'ZZ E Passwort',
		array(
			'calories_100' => 500,
			'protein'      => 50,
		),
		$top
	);
	wp_update_post(
		array(
			'ID'            => $e,
			'post_password' => 'geheim',
		)
	);
	$f = mmm_ir_ing(
		'ZZ F Packung',
		array(
			'calories_100' => 50,
			'protein'      => 5,
		),
		$top
	);

	// Referenzpackung mit 40 g Eiweiß.
	$product                      = (int) wp_insert_post(
		array(
			'post_type'   => Mixmymeals_Product::POST_TYPE,
			'post_title'  => 'ZZ Packung',
			'post_status' => 'publish',
			'post_parent' => $f,
		)
	);
	$GLOBALS['MMM_IR']['posts'][] = $product;
	update_post_meta( $product, '_mmm_product_brand', 'ZZ' );
	( new Mixmymeals_Product( $product ) )->set_nutrition( array( 'protein' => 40 ) );
	Mixmymeals_Product::set_reference_id( $f, $product );
	update_option( Mixmymeals_Recipe::RANKING_GENERATION_OPTION, uniqid( 'ir', true ) );

	mmm_ir_is(
		'1+2+3: Eiweiß absteigend, ohne Werte und Passwort draußen, Packung zählt',
		array(
			'ZZ F Packung' => 40.0,
			'ZZ A'         => 30.0,
			'ZZ C'         => 20.0,
			'ZZ B'         => 10.0,
		),
		mmm_ir_rank( 'protein', 'desc', array( $top, $sub ) )
	);
	mmm_ir_is(
		'1: Salz aufsteigend, C (Salz nicht angegeben) fehlt',
		array(
			'ZZ B' => 0.1,
			'ZZ A' => 1.0,
		),
		mmm_ir_rank( 'salt', 'asc', array( $top, $sub ) )
	);
	mmm_ir_is( '4: Kategorie ausgeschlossen', array( 'ZZ F Packung', 'ZZ A', 'ZZ B' ), array_keys( mmm_ir_rank( 'protein', 'desc', array( $top, $sub ), array( $sub ) ) ) );
	mmm_ir_is( '4: Zutat ausgeschlossen', array( 'ZZ F Packung', 'ZZ C', 'ZZ B' ), array_keys( mmm_ir_rank( 'protein', 'desc', array( $top, $sub ), array(), array( $a ) ) ) );
	mmm_ir_is( '4: nur eine Kategorie', array( 'ZZ C' ), array_keys( mmm_ir_rank( 'protein', 'desc', array( $sub ) ) ) );

	$sc     = new Mixmymeals_Shortcodes();
	$slug_a = get_post_field( 'post_name', $a );
	$html   = $sc->mmm_ingredient_ranking_callback(
		array(
			'category' => 'zz-rangliste,' . $sub,
			'exclude'  => $slug_a . ',' . $b,
			'caption'  => 'Quelle',
		)
	);
	mmm_ir_assert( false !== strpos( $html, '<table class="mmm-ingredient-ranking__table">' ), '5: Tabelle' );
	mmm_ir_assert( false === strpos( $html, 'ZZ A' ) && false === strpos( $html, 'ZZ B' ), '5: exclude per Slug und ID' );
	mmm_ir_assert( strpos( $html, 'ZZ F Packung' ) < strpos( $html, 'ZZ C' ), '5: Reihenfolge in der Tabelle' );
	mmm_ir_assert( false !== strpos( $html, 'href="' . esc_url( get_permalink( $c ) ) . '"' ), '5: Link zur Zutat' );
	mmm_ir_assert( false !== strpos( $html, '<figcaption class="mmm-ingredient-ranking__caption">Quelle</figcaption>' ), '5: Bildunterschrift' );
	mmm_ir_assert( false !== strpos( $html, '>' . esc_html( Mixmymeals_Meal_Plan::format_nutrition_value( 'protein', 40 ) ) . '<' ), '5: Wert formatiert' );

	wp_set_current_user( 0 );
	mmm_ir_is( '5: unbekannte Kategorie, Besucher', '', $sc->mmm_ingredient_ranking_callback( array( 'exclude_category' => 'zz-gibt-es-nicht' ) ) );
	$admins = get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
			'fields' => 'ID',
		)
	);
	wp_set_current_user( (int) $admins[0] );
	mmm_ir_assert( false !== strpos( $sc->mmm_ingredient_ranking_callback( array( 'exclude' => 'zz-gibt-es-nicht' ) ), 'mmm-embed--missing' ), '5: unbekannte Zutat, Redakteur' );
	mmm_ir_assert( false !== strpos( $sc->mmm_ingredient_ranking_callback( array( 'by' => 'cost' ) ), 'mmm-embed--missing' ), '5: unbekannte Kennzahl, Redakteur' );

} finally {
	foreach ( $GLOBALS['MMM_IR']['posts'] as $id ) {
		wp_delete_post( $id, true );
	}
	foreach ( $GLOBALS['MMM_IR']['terms'] as $term ) {
		wp_delete_term( $term, 'mmm_ingredient_category' );
	}
	echo "\n{$GLOBALS['MMM_IR']['pass']} ok, {$GLOBALS['MMM_IR']['fail']} FAIL\n";
}
