<?php
/**
 * Prüft den Ausschluss einzelner Produkte an einer Schritt-Zutat.
 *
 * Der Rundlauf geht durch save_recipe_steps_meta_box(), nicht durch
 * set_steps(): dieser Callback baut das Zutaten-Array Schlüssel für Schlüssel
 * neu auf, und was er nicht nennt, ist weg, bevor set_steps() es sieht. Genau
 * so sind schon `image` und `optional` verlorengegangen — ein Test über
 * set_steps() allein hätte beide Male bestanden.
 *
 * Läuft gegen eine echte Installation, legt eigene Posts an und löscht sie
 * wieder. Alles heißt „ZZ …“.
 *
 * wp --require=<dbhost.php> eval-file \
 *    wp-content/plugins/mixmymeals/tools/excluded-products-test.php
 *
 * @package MixMyMeals
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Zähler und das Metabox-Objekt in $GLOBALS: wp-cli führt eval-file in einem
 * Funktionsgeltungsbereich aus, `global $x` greift dort ins Leere. Derselbe
 * Stolperstein steht oben in diet-test.php.
 */
$GLOBALS['MMM_XP_PASS']  = 0;
$GLOBALS['MMM_XP_FAIL']  = 0;
$GLOBALS['MMM_XP_TRASH'] = array();
$GLOBALS['MMM_XP_META']  = new Mixmymeals_Post_Meta();

/**
 * Eine Zusicherung.
 *
 * @param bool   $ok    Ergebnis.
 * @param string $label Beschreibung.
 * @return void
 */
function mmm_xp_assert( bool $ok, string $label ): void {
	if ( $ok ) {
		++$GLOBALS['MMM_XP_PASS'];
		echo "ok   $label\n";
		return;
	}
	++$GLOBALS['MMM_XP_FAIL'];
	echo "FAIL $label\n";
}

/**
 * Merkt sich einen Post zum Aufräumen.
 *
 * @param int $id Post-ID.
 * @return int
 */
function mmm_xp_trash( int $id ): int {
	$GLOBALS['MMM_XP_TRASH'][] = $id;
	return $id;
}

/**
 * Schickt Schritte durch den Metabox-Callback, so wie der Browser es täte.
 *
 * @param int     $recipe_id Rezept.
 * @param array[] $steps     Rohe Schritte, wie sie im Formular stünden.
 * @return void
 */
function mmm_xp_post_steps( int $recipe_id, array $steps ): void {

	$_POST                                    = array();
	$_POST['mmm_recipe_steps_meta_box_nonce'] = wp_create_nonce( 'mmm_recipe_steps_meta_box_action' );
	$_POST['mmm_recipe_steps']                = $steps;
	$GLOBALS['MMM_XP_META']->save_recipe_steps_meta_box( $recipe_id );
	$_POST = array();
}

wp_set_current_user( 1 );

echo "-- 1. Eine Zutat, die nur über eines ihrer Pakete vegan wird\n";

$joghurt = mmm_xp_trash(
	wp_insert_post(
		array(
			'post_type'   => 'mmm_ingredient',
			'post_title'  => 'ZZ Sojajoghurt',
			'post_status' => 'publish',
		)
	)
);
Mixmymeals_Diet::set_for_post( $joghurt, array() );

$vegan_packet = mmm_xp_trash(
	wp_insert_post(
		array(
			'post_type'   => 'mmm_product',
			'post_title'  => 'ZZ Vanille gesuesst',
			'post_status' => 'publish',
			'post_parent' => $joghurt,
		)
	)
);
$plain_packet = mmm_xp_trash(
	wp_insert_post(
		array(
			'post_type'   => 'mmm_product',
			'post_title'  => 'ZZ Natur',
			'post_status' => 'publish',
			'post_parent' => $joghurt,
		)
	)
);

foreach ( array(
	$vegan_packet => 120,
	$plain_packet => 60,
) as $pid => $kcal ) {
	$obj = new Mixmymeals_Product( $pid );
	$obj->set_brand( 'ZZ' );
	$obj->set_nutrition( array( 'calories_100' => $kcal ) );
}

// Nur das eine Paket ist vegan — es allein hebt die Zutat auf „vegan möglich“.
( new Mixmymeals_Product( $vegan_packet ) )->set_diet_type( 'vegan' );

$ingredient = new Mixmymeals_Ingredient( $joghurt );
mmm_xp_assert(
	in_array( 'vegan', $ingredient->get_diet_suitabilities(), true ),
	'ohne Ausschluss gilt die Zutat als vegan möglich'
);
mmm_xp_assert(
	! in_array( 'vegan', $ingredient->get_diet_suitabilities( array( $vegan_packet ) ), true ),
	'mit ausgeschlossenem Paket nicht mehr'
);
mmm_xp_assert(
	in_array( 'vegan', $ingredient->get_diet_suitabilities( array( $plain_packet ) ), true ),
	'ein anderes Paket auszuschließen ändert nichts'
);

echo "\n-- 2. Rundlauf durch den Metabox-Callback\n";

$recipe_id = mmm_xp_trash(
	wp_insert_post(
		array(
			'post_type'   => 'mmm_recipe',
			'post_title'  => 'ZZ Herzhaftes Gericht',
			'post_status' => 'publish',
		)
	)
);

$steps = array(
	array(
		'title'       => 'Schritt',
		'description' => 'Text',
		'ingredients' => array(
			array(
				'id'                => $joghurt,
				'amount'            => 200,
				'excluded_products' => array( $vegan_packet ),
			),
		),
	),
);

mmm_xp_post_steps( $recipe_id, $steps );

$recipe = new Mixmymeals_Recipe( $recipe_id );
$row    = $recipe->get_steps()[0]['ingredients'][0] ?? array();

mmm_xp_assert(
	array( $vegan_packet ) === array_map( 'intval', $row['excluded_products'] ?? array() ),
	'der Ausschluss hat den Callback überlebt'
);

echo "\n-- 3. Fremde Produkte werden verworfen\n";

$fremd         = mmm_xp_trash(
	wp_insert_post(
		array(
			'post_type'   => 'mmm_ingredient',
			'post_title'  => 'ZZ Andere Zutat',
			'post_status' => 'publish',
		)
	)
);
$fremdes_paket = mmm_xp_trash(
	wp_insert_post(
		array(
			'post_type'   => 'mmm_product',
			'post_title'  => 'ZZ Fremd',
			'post_status' => 'publish',
			'post_parent' => $fremd,
		)
	)
);

$steps[0]['ingredients'][0]['excluded_products'] = array( $vegan_packet, $fremdes_paket, 0 );
mmm_xp_post_steps( $recipe_id, $steps );

$recipe = new Mixmymeals_Recipe( $recipe_id );
$row    = $recipe->get_steps()[0]['ingredients'][0] ?? array();

mmm_xp_assert(
	array( $vegan_packet ) === array_map( 'intval', $row['excluded_products'] ?? array() ),
	'das Paket einer anderen Zutat und die 0 sind raus'
);

echo "\n-- 4. Die Pille zur Ernährungsform folgt\n";

mmm_xp_assert(
	! in_array( 'vegan', $recipe->get_diet_suitabilities(), true ),
	'das Rezept gilt nicht als vegan möglich'
);

$steps[0]['ingredients'][0]['excluded_products'] = array();
mmm_xp_post_steps( $recipe_id, $steps );
$recipe = new Mixmymeals_Recipe( $recipe_id );

mmm_xp_assert(
	in_array( 'vegan', $recipe->get_diet_suitabilities(), true ),
	'ohne Ausschluss wieder schon'
);

echo "\n-- 5. Der Produktvergleich lässt die Spalte weg\n";

$in_table = static function ( Mixmymeals_Recipe $r, int $product_id ): bool {
	foreach ( $r->calc_product_impact() as $group ) {
		foreach ( (array) ( $group['products'] ?? array() ) as $col ) {
			if ( (int) ( $col['id'] ?? 0 ) === $product_id ) {
				return true;
			}
		}
	}
	return false;
};

mmm_xp_assert( $in_table( $recipe, $vegan_packet ), 'ohne Ausschluss steht das Paket als Spalte' );

$steps[0]['ingredients'][0]['excluded_products'] = array( $vegan_packet );
mmm_xp_post_steps( $recipe_id, $steps );
$recipe = new Mixmymeals_Recipe( $recipe_id );

mmm_xp_assert( ! $in_table( $recipe, $vegan_packet ), 'mit Ausschluss nicht mehr' );

/*
 * Bleibt nach dem Filter nur ein Paket uebrig, gibt es nichts zu vergleichen
 * — dann faellt die ganze Gruppe weg, wie bei einer Zutat mit nur einem
 * Produkt.
 */
mmm_xp_assert( array() === $recipe->calc_product_impact(), 'und die Gruppe entfällt, weil nur eines übrig bleibt' );

echo "\n-- 6. Zwei Zeilen derselben Zutat: der strengere Stand gewinnt\n";

/*
 * Die Entscheidung, die hier festgenagelt wird: wer ein Paket in einem
 * Schritt ausschliesst und es im anderen vergisst, meinte den Ausschluss.
 * Mixmymeals_Diet::combine() schneidet die Mengen, also setzt er sich durch.
 */
$zwei = array(
	array(
		'title'       => 'Schritt 1',
		'description' => 'Text',
		'ingredients' => array(
			array(
				'id'                => $joghurt,
				'amount'            => 200,
				'excluded_products' => array( $vegan_packet ),
			),
		),
	),
	array(
		'title'       => 'Schritt 2',
		'description' => 'Text',
		'ingredients' => array(
			array(
				'id'     => $joghurt,
				'amount' => 50,
			),
		),
	),
);

mmm_xp_post_steps( $recipe_id, $zwei );
$recipe = new Mixmymeals_Recipe( $recipe_id );

mmm_xp_assert(
	! in_array( 'vegan', $recipe->get_diet_suitabilities(), true ),
	'in einer Zeile ausgeschlossen reicht für das ganze Rezept'
);

echo "\n-- 7. Die Zutat bleibt im Rezept unangetastet\n";

$scaled = $recipe->get_ingredients_scaled( 1.0 );
mmm_xp_assert( 2 === count( $scaled ), 'beide Zeilen stehen weiter in der Zutatenliste' );
mmm_xp_assert(
	array( $vegan_packet ) === array_map( 'intval', $scaled[0]['excluded_products'] ?? array() ),
	'und tragen ihre Ausschlussliste mit sich'
);

echo "\n=== Aufräumen ===\n";

foreach ( $GLOBALS['MMM_XP_TRASH'] as $trash_id ) {
	wp_delete_post( $trash_id, true );
}
echo 'entfernt: ' . count( $GLOBALS['MMM_XP_TRASH'] ) . " Posts\n";

echo "\n=== Ergebnis ===\n";
printf( "  %d bestanden, %d fehlgeschlagen\n", $GLOBALS['MMM_XP_PASS'], $GLOBALS['MMM_XP_FAIL'] );
