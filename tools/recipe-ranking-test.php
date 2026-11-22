<?php
/**
 * Prüft [mmm_recipe_ranking] und Mixmymeals_Recipe::ranking().
 *
 *  1. Reihenfolge nach der Kennzahl, Standardrichtung je Kennzahl.
 *  2. Wer nicht verlässlich ist, fällt heraus: lückenhafte Nährwerte,
 *     unvollständige Preise, keine Zeit, keine Haltbarkeit.
 *  3. Wasser (kein Preis, keine Nährwerte) kostet nichts und ist keine Lücke.
 *     max_cost lässt nur Rezepte mit vollständigen Preisen durch.
 *  4. Der Zwischenspeicher wird von einer Änderung verworfen.
 *  5. Ausgabe: nummerierte Liste mit der Kennzahl, Fehlkonfiguration nur für
 *     Redakteure sichtbar.
 *
 * Isoliert über eine eigene Rezeptkategorie, damit vorhandene Rezepte der
 * Installation nicht mitzählen. Alles heißt „ZZ …“.
 *
 * wp --require=<dbhost.php> eval-file \
 *    wp-content/plugins/mixmymeals/tools/recipe-ranking-test.php
 *
 * @package MixMyMeals
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$GLOBALS['MMM_RR_PASS']  = 0;
$GLOBALS['MMM_RR_FAIL']  = 0;
$GLOBALS['MMM_RR_TRASH'] = array();

/**
 * Eine Zusicherung.
 *
 * @param bool   $ok    Ergebnis.
 * @param string $label Beschreibung.
 * @return void
 */
function mmm_rr_assert( bool $ok, string $label ): void {
	if ( $ok ) {
		++$GLOBALS['MMM_RR_PASS'];
		echo "ok   $label\n";
		return;
	}
	++$GLOBALS['MMM_RR_FAIL'];
	echo "FAIL $label\n";
}

/**
 * Vergleicht zwei Werte und zeigt bei Abweichung beide.
 *
 * @param string $label    Beschreibung.
 * @param string $expected Erwartet.
 * @param string $actual   Bekommen.
 * @return void
 */
function mmm_rr_is( string $label, string $expected, string $actual ): void {
	mmm_rr_assert( $expected === $actual, $label . ( $expected === $actual ? '' : " (erwartet $expected, bekommen $actual)" ) );
}

/**
 * Legt eine Zutat an.
 *
 * @param string     $title Titel.
 * @param array      $nutri Nährwerte, leer für keine.
 * @param float|null $price Preis pro 100 g, null für keinen.
 * @return int
 */
function mmm_rr_ing( string $title, array $nutri, ?float $price ): int {

	$id = (int) wp_insert_post(
		array(
			'post_type'   => 'mmm_ingredient',
			'post_title'  => $title,
			'post_status' => 'publish',
		)
	);

	$GLOBALS['MMM_RR_TRASH'][] = $id;
	$ingredient                = new Mixmymeals_Ingredient( $id );

	if ( $nutri ) {
		$ingredient->set_nutrition( $nutri );
	}
	if ( null !== $price ) {
		$ingredient->set_price_per_100g( $price );
	}

	return $id;
}

/**
 * Legt ein Rezept in der Testkategorie an.
 *
 * @param string   $title   Titel.
 * @param array[]  $rows    Zeilen: [ingredient id, grams].
 * @param int      $term_id Kategorie.
 * @param int      $minutes Zubereitungszeit, 0 = keine.
 * @param int|null $days    Haltbarkeit.
 * @return int
 */
function mmm_rr_recipe( string $title, array $rows, int $term_id, int $minutes = 0, ?int $days = null ): int {

	$id = (int) wp_insert_post(
		array(
			'post_type'   => 'mmm_recipe',
			'post_title'  => $title,
			'post_status' => 'publish',
		)
	);

	$GLOBALS['MMM_RR_TRASH'][] = $id;

	update_post_meta( $id, '_mmm_servings', 1 );
	wp_set_object_terms( $id, array( $term_id ), 'mmm_recipe_category' );

	$recipe = new Mixmymeals_Recipe( $id );
	$recipe->set_steps(
		array(
			array(
				'title'       => 'S',
				'description' => 'x',
				'ingredients' => array_map(
					static fn( $row ) => array(
						'id'        => $row[0],
						'amount'    => $row[1],
						'amount_id' => 0,
					),
					$rows
				),
			),
		)
	);

	if ( $minutes ) {
		$recipe->set_preparation_time( $minutes );
	}
	if ( null !== $days ) {
		$recipe->set_shelf_life( $days );
	}

	return $id;
}

/**
 * Vollständige Nährwerte, Eiweiß und Energie nach Wunsch, stimmig zueinander.
 *
 * @param float $protein Eiweiß pro 100 g.
 * @return array<string,float>
 */
function mmm_rr_nutri( float $protein ): array {
	return array(
		'calories_100' => 4 * $protein + 4 * 10 + 9 * 2,
		'fat'          => 2,
		'saturates'    => 0.5,
		'carbs'        => 10,
		'protein'      => $protein,
		'sugar'        => 1,
		'fiber'        => 2,
		'salt'         => 0.1,
	);
}

$rr_term = wp_insert_term( 'ZZ Ranking ' . wp_generate_password( 6, false ), 'mmm_recipe_category' );
$term_id = (int) $rr_term['term_id'];

$tofu  = mmm_rr_ing( 'ZZ Tofu', mmm_rr_nutri( 15 ), 0.5 );
$quark = mmm_rr_ing( 'ZZ Quark', mmm_rr_nutri( 12 ), 0.3 );
$reis  = mmm_rr_ing( 'ZZ Reis', mmm_rr_nutri( 3 ), 0.2 );
$kaese = mmm_rr_ing( 'ZZ Käse ohne Preis', mmm_rr_nutri( 25 ), null );
// Wie live: ausdrücklich lauter Nullen, kein Preis.
$wasser = mmm_rr_ing( 'ZZ Wasser', array_fill_keys( array( 'calories_100', 'fat', 'saturates', 'carbs', 'protein', 'sugar', 'fiber', 'salt' ), 0 ), null );
$halb   = mmm_rr_ing(
	'ZZ Ohne Eiweiß-Angabe',
	array(
		'calories_100' => 300,
		'fat'          => 10,
	),
	0.1
);

$r_tofu  = mmm_rr_recipe( 'ZZ R1 Tofu', array( array( $tofu, 200 ) ), $term_id, 20, 3 );
$r_quark = mmm_rr_recipe( 'ZZ R2 Quark', array( array( $quark, 200 ), array( $wasser, 300 ) ), $term_id, 10, 2 );
$r_reis  = mmm_rr_recipe( 'ZZ R3 Reis', array( array( $reis, 200 ) ), $term_id, 40 );
$r_kaese = mmm_rr_recipe( 'ZZ R4 Käse', array( array( $kaese, 200 ) ), $term_id );
$r_halb  = mmm_rr_recipe( 'ZZ R5 Halb', array( array( $tofu, 100 ), array( $halb, 100 ) ), $term_id );

echo "\n=== 1. Reihenfolge ===\n";

$protein = Mixmymeals_Recipe::ranking( 'protein', 'desc', array(), 0, $term_id );
mmm_rr_is( 'Eiweiß absteigend', implode( ',', array( $r_kaese, $r_tofu, $r_quark, $r_reis ) ), implode( ',', array_keys( $protein ) ) );
mmm_rr_is( 'Wert ist pro Portion', '30', (string) round( $protein[ $r_tofu ] ) );
mmm_rr_is( 'Standard für Eiweiß: absteigend', 'desc', Mixmymeals_Recipe::ranking_default_order( 'protein' ) );
mmm_rr_is( 'Standard für Salz: aufsteigend', 'asc', Mixmymeals_Recipe::ranking_default_order( 'salt' ) );
mmm_rr_is( 'Standard für Haltbarkeit: absteigend', 'desc', Mixmymeals_Recipe::ranking_default_order( 'shelf_life' ) );

echo "\n=== 2. Wer nicht verlässlich ist, fällt heraus ===\n";

mmm_rr_assert( ! isset( $protein[ $r_halb ] ), 'lückenhafte Nährwerte: nicht in der Eiweißliste' );

$cost = Mixmymeals_Recipe::ranking( 'cost', 'asc', array(), 0, $term_id );
mmm_rr_assert( ! isset( $cost[ $r_kaese ] ), 'Zutat ohne Preis: nicht in der Kostenliste' );
// Reis 0,40 €, Quark 0,60 €, Halb 0,50 + 0,10 = 0,60 € (Titel entscheidet), Tofu 1,00 €.
mmm_rr_is( 'Kosten aufsteigend', implode( ',', array( $r_reis, $r_quark, $r_halb, $r_tofu ) ), implode( ',', array_keys( $cost ) ) );

$time = Mixmymeals_Recipe::ranking( 'time', 'asc', array(), 0, $term_id );
mmm_rr_is( 'nur gestoppte Rezepte, schnellstes zuerst', implode( ',', array( $r_quark, $r_tofu, $r_reis ) ), implode( ',', array_keys( $time ) ) );

$time30 = Mixmymeals_Recipe::ranking( 'time', 'asc', array(), 30, $term_id );
mmm_rr_assert( ! isset( $time30[ $r_reis ] ), 'max_time schneidet ab' );

// max_cost: Tofu (1,00 €) zu teuer, Käse ohne Preis fällt heraus, statt als billig durchzugehen.
$cheap = Mixmymeals_Recipe::ranking( 'protein', 'desc', array(), 0, $term_id, 0.60 );
mmm_rr_is( 'max_cost: Eiweiß unter 0,60 €', implode( ',', array( $r_quark, $r_reis ) ), implode( ',', array_keys( $cheap ) ) );
$cheap = Mixmymeals_Recipe::ranking( 'cost', 'asc', array(), 0, $term_id, 0.5 );
mmm_rr_is( 'max_cost: Kosten bis 0,50 €', (string) $r_reis, implode( ',', array_keys( $cheap ) ) );
$cheap = Mixmymeals_Recipe::ranking( 'protein', 'desc', array(), 0, $term_id, 0.0 );
mmm_rr_is( 'max_cost 0: kein Filter', implode( ',', array_keys( $protein ) ), implode( ',', array_keys( $cheap ) ) );

$shelf = Mixmymeals_Recipe::ranking( 'shelf_life', 'desc', array(), 0, $term_id );
mmm_rr_is( 'nur mit Haltbarkeit, längste zuerst', implode( ',', array( $r_tofu, $r_quark ) ), implode( ',', array_keys( $shelf ) ) );

echo "\n=== 3. Wasser ===\n";

mmm_rr_assert( ( new Mixmymeals_Recipe( $r_quark ) )->has_complete_prices(), 'Wasser ohne Preis ist keine Lücke' );
mmm_rr_assert( ! ( new Mixmymeals_Recipe( $r_kaese ) )->has_complete_prices(), 'Käse ohne Preis ist eine' );

echo "\n=== 4. Zwischenspeicher ===\n";

$before = (string) get_option( Mixmymeals_Recipe::RANKING_GENERATION_OPTION, '' );
update_post_meta( $kaese, '_mmm_price_per_100g_dummy', 1 );
do_action( 'shutdown' );
$after = (string) get_option( Mixmymeals_Recipe::RANKING_GENERATION_OPTION, '' );
mmm_rr_assert( $before !== $after, 'eine Änderung an einer Zutat erneuert die Generation' );

echo "\n=== 5. Ausgabe ===\n";

$slug = get_term( $term_id )->slug;
$html = do_shortcode( '[mmm_recipe_ranking by="protein" category="' . $slug . '" limit="2" title="Viel Eiweiß"]' );

mmm_rr_assert( false !== strpos( $html, '<ol class="mmm-ranking__list">' ), 'nummerierte Liste' );
mmm_rr_is( 'limit greift', '2', (string) substr_count( $html, 'class="mmm-ranking__item' ) );
mmm_rr_assert( false !== strpos( $html, 'ZZ R4 Käse' ) && false === strpos( $html, 'ZZ R2 Quark' ), 'die ersten zwei' );
mmm_rr_assert( (bool) preg_match( '#mmm-ranking__figure"><strong class="mmm-ranking__value">50,0 g</strong> [^<]+</p>#u', $html ), 'Kennzahl auf dem Eintrag: ' . wp_strip_all_tags( (string) preg_replace( '#.*?mmm-ranking__figure">([^<]+).*#s', '$1', $html ) ) );
mmm_rr_assert( false !== strpos( $html, '<h2 class="mmm-ranking__title">Viel Eiweiß</h2>' ), 'Überschrift' );

$kcal = do_shortcode( '[mmm_recipe_ranking by="kcal" category="' . $slug . '"]' );
mmm_rr_assert( false !== strpos( $kcal, 'mmm-ranking--calories' ), 'Alias kcal → calories' );

$capped = do_shortcode( '[mmm_recipe_ranking by="protein" category="' . $slug . '" max_cost="0,60"]' );
mmm_rr_assert( false !== strpos( $capped, 'ZZ R2 Quark' ) && false === strpos( $capped, 'ZZ R1 Tofu' ) && false === strpos( $capped, 'ZZ R4 Käse' ), 'max_cost="0,60" mit Komma' );

wp_set_current_user( 0 );
mmm_rr_is( 'ungültiger Preis: für Besucher nichts', '', do_shortcode( '[mmm_recipe_ranking category="' . $slug . '" max_cost="zwei Euro"]' ) );
mmm_rr_is( 'unbekannte Kennzahl: für Besucher nichts', '', do_shortcode( '[mmm_recipe_ranking by="karma"]' ) );
mmm_rr_is( 'unbekannte Kategorie: für Besucher nichts', '', do_shortcode( '[mmm_recipe_ranking category="gibt-es-nicht-zz"]' ) );

$admins = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
);
if ( $admins ) {
	wp_set_current_user( (int) $admins[0] );
	mmm_rr_assert( false !== strpos( do_shortcode( '[mmm_recipe_ranking by="karma"]' ), 'mmm-embed--missing' ), 'unbekannte Kennzahl: Redakteure sehen den Grund' );
	mmm_rr_assert( false !== strpos( do_shortcode( '[mmm_recipe_ranking max_cost="zwei Euro"]' ), 'mmm-embed--missing' ), 'ungültiger Preis: Redakteure sehen den Grund' );
	wp_set_current_user( 0 );
}

echo "\n=== Aufräumen ===\n";

foreach ( $GLOBALS['MMM_RR_TRASH'] as $trash_id ) {
	wp_delete_post( $trash_id, true );
}
wp_delete_term( $term_id, 'mmm_recipe_category' );
echo 'entfernt: ' . count( $GLOBALS['MMM_RR_TRASH'] ) . " Posts\n";

echo "\n=== Ergebnis ===\n";
printf( "  %d bestanden, %d fehlgeschlagen\n", $GLOBALS['MMM_RR_PASS'], $GLOBALS['MMM_RR_FAIL'] );
