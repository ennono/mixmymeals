<?php
/**
 * Prüft die Routen für Datenkorrektur in Mengen: /bulk-update, /missing,
 * das Löschen per null auf /ingredients/{id}/nutrition, own_price_per_100g
 * und das Zusammenführen samt Produkten.
 *
 * Läuft über rest_do_request(), also durch Schema-Validierung und
 * Rechteprüfung wie ein echter Aufruf des MCP-Servers. Alles, was angelegt
 * wird, heißt „T …" und trägt die Marke ZZ.
 *
 * wp --require=<dbhost.php> eval-file \
 *    wp-content/plugins/mixmymeals/tools/bulk-update-test.php
 *
 * @package MixMyMeals
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$GLOBALS['MMM_BU_PASS'] = 0;
$GLOBALS['MMM_BU_FAIL'] = 0;

function mmm_bu_assert( bool $ok, string $label ): void {
	if ( $ok ) {
		++$GLOBALS['MMM_BU_PASS'];
		echo "ok   $label\n";
		return;
	}
	++$GLOBALS['MMM_BU_FAIL'];
	echo "FAIL $label\n";
}

function mmm_bu_call( string $method, string $path, array $body = array(), array $query = array() ): WP_REST_Response {
	$request = new WP_REST_Request( $method, '/mixmymeals/v1' . $path );
	if ( $body ) {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) );
	}
	if ( $query ) {
		$request->set_query_params( $query );
	}
	return rest_get_server()->dispatch( $request );
}

function mmm_bu_product( int $ingredient, string $title ): Mixmymeals_Product {
	$product = Mixmymeals_Product::create( $ingredient, $title );
	$product->set_brand( 'ZZ' );
	return $product;
}

$admins = get_users( array( 'role' => 'administrator', 'fields' => 'ID', 'number' => 1 ) );
wp_set_current_user( (int) ( $admins[0] ?? 1 ) );

$milk  = Mixmymeals_Ingredient::create( 'T Milch' );
$cheese = Mixmymeals_Ingredient::create( 'T Ziegenkäse' );
$milk->set_nutrition( array( 'calories_100' => 64, 'fat' => 3.6, 'saturates' => 0 ) );
$pkt = mmm_bu_product( $milk->get_id(), 'T Vollmilch' );
$pkt->set_nutrition( array( 'calories_100' => 64, 'saturates' => 2.3 ) );
$pkt->set_package_weight( 1000 );
Mixmymeals_Product::set_reference_id( $milk->get_id(), $pkt->get_id() );

echo "-- 1. null auf /nutrition löscht den Eigenwert statt 0 zu schreiben\n";
$r = mmm_bu_call( 'POST', '/ingredients/' . $milk->get_id() . '/nutrition', array( 'saturates' => null ) );
mmm_bu_assert( 200 === $r->get_status(), 'Status 200' );
mmm_bu_assert( '' === get_post_meta( $milk->get_id(), '_mmm_saturates', true ) && ! metadata_exists( 'post', $milk->get_id(), '_mmm_saturates' ), 'Meta-Zeile ist weg' );
mmm_bu_assert( 2.3 === $milk->get_nutrition()['saturates'], 'aufgelöst gilt das Referenzprodukt (2,3)' );

echo "\n-- 2. own_price_per_100g per Update\n";
$r = mmm_bu_call( 'POST', '/ingredients/' . $cheese->get_id(), array( 'own_price_per_100g' => 2.5 ) );
mmm_bu_assert( 2.5 === $r->get_data()['own_price_per_100g'], 'gesetzt' );
$r = mmm_bu_call( 'POST', '/ingredients/' . $cheese->get_id(), array( 'own_price_per_100g' => null ) );
mmm_bu_assert( 0.0 === $r->get_data()['own_price_per_100g'], 'null löscht' );

echo "\n-- 3. Bulk: dry_run liefert Diffs und schreibt nichts\n";
$items = array(
	array( 'type' => 'ingredient', 'id' => $milk->get_id(), 'nutrition' => array( 'fat' => 3.5, 'saturates' => 2.0, 'calories_100' => 64 ) ),
	array( 'type' => 'ingredient', 'id' => $cheese->get_id(), 'diet_type' => 'vegetarian', 'price_per_100g' => 2.9 ),
	array( 'type' => 'product', 'id' => $pkt->get_id(), 'price_per_100g' => 0.11 ),
	array( 'type' => 'ingredient', 'id' => 99999999, 'nutrition' => array( 'fat' => 1 ) ),
	array( 'type' => 'product', 'id' => $milk->get_id(), 'diet_type' => '' ),
	array( 'type' => 'ingredient', 'id' => $cheese->get_id() ),
);
$r    = mmm_bu_call( 'POST', '/bulk-update', array( 'items' => $items, 'dry_run' => true ) );
$data = $r->get_data();
mmm_bu_assert( 200 === $r->get_status(), 'Status 200' );
$first = (array) $data['results'][0]['changes'];
mmm_bu_assert( array( 3.6, 3.5 ) === $first['fat'] && array( null, 2.0 ) === $first['saturates'], 'fat 3,6→3,5 und saturates null→2' );
mmm_bu_assert( ! isset( $first['calories_100'] ), 'unveränderte Werte erscheinen nicht' );
mmm_bu_assert( array( 'calories_100' ) !== ( $data['results'][0]['shadowed'] ?? array() ) && in_array( 'saturates', $data['results'][0]['shadowed'] ?? array(), true ), 'saturates als shadowed gemeldet' );
mmm_bu_assert( ! in_array( 'fat', $data['results'][0]['shadowed'] ?? array(), true ), 'fat nicht (Produkt nennt es nicht)' );
mmm_bu_assert( 3.6 === $milk->get_nutrition_with_product( 0 )['fat'], 'nichts geschrieben' );
$errors = wp_list_pluck( $data['errors'], 'error', 'id' );
mmm_bu_assert( 'not_found' === ( $errors[99999999] ?? '' ), 'fehlende ID: not_found' );
mmm_bu_assert( 'wrong_type' === ( $errors[ $milk->get_id() ] ?? '' ), 'Zutat als Produkt: wrong_type' );
mmm_bu_assert( 3 === $data['failed'] && 'no_fields' === end( $data['errors'] )['error'], 'leerer Eintrag: no_fields' );
mmm_bu_assert( ! isset( $data['results'][0]['units'] ) && ! isset( $data['results'][0]['nutrition'] ), 'keine vollen Entitäten' );

echo "\n-- 4. Bulk: echtes Schreiben\n";
$r    = mmm_bu_call( 'POST', '/bulk-update', array( 'items' => array_slice( $items, 0, 3 ) ) );
$data = $r->get_data();
mmm_bu_assert( 3.5 === $milk->get_nutrition_with_product( 0 )['fat'], 'fat geschrieben' );
mmm_bu_assert( '' !== Mixmymeals_Diet::strongest( Mixmymeals_Diet::for_post( $cheese->get_id() ), 'diet' ), 'Ziegenkäse hat eine Ernährungsform' );
mmm_bu_assert( 2.9 === $cheese->get_own_price_per_100g(), 'Preis der Zutat gesetzt' );
mmm_bu_assert( abs( 1.1 - (float) $pkt->get_price() ) < 0.001, 'Produkt: 0,11 €/100 g × 1000 g = 1,10 € Packungspreis' );
$r = mmm_bu_call( 'POST', '/bulk-update', array( 'items' => array( array( 'type' => 'ingredient', 'id' => $milk->get_id(), 'nutrition' => array( 'fat' => 3.5 ) ) ) ) );
mmm_bu_assert( 0 === $r->get_data()['changed'], 'gleicher Wert: changed 0' );

echo "\n-- 5. Bulk: Tippfehler oder unbekannte Diät lehnen den ganzen Aufruf ab\n";
$r = mmm_bu_call( 'POST', '/bulk-update', array( 'items' => array( array( 'type' => 'ingredient', 'id' => $milk->get_id(), 'nutrition' => array( 'saturated' => 1 ) ) ) ) );
mmm_bu_assert( 400 === $r->get_status(), 'nutrition.saturated → 400' );
$r = mmm_bu_call( 'POST', '/bulk-update', array( 'items' => array( array( 'type' => 'ingredient', 'id' => $milk->get_id(), 'diet_type' => 'carnivore' ) ) ) );
mmm_bu_assert( 400 === $r->get_status(), 'diet_type carnivore → 400' );
$r = mmm_bu_call( 'POST', '/bulk-update', array( 'items' => array_fill( 0, 101, array( 'type' => 'ingredient', 'id' => 1 ) ) ) );
mmm_bu_assert( 400 === $r->get_status(), '101 Einträge → 400' );
$bare = mmm_bu_product( $cheese->get_id(), 'T Ohne Gewicht' );
$r    = mmm_bu_call( 'POST', '/bulk-update', array( 'items' => array( array( 'type' => 'product', 'id' => $bare->get_id(), 'price_per_100g' => 1 ) ) ) );
mmm_bu_assert( 'no_package_weight' === $r->get_data()['errors'][0]['error'], 'Produkt ohne Packungsgewicht: no_package_weight' );

echo "\n-- 6. /missing\n";
$r   = mmm_bu_call( 'GET', '/missing', array(), array( 'field' => 'saturates', 'per_page' => 200 ) );
$ids = wp_list_pluck( $r->get_data()['items'], 'id' );
mmm_bu_assert( in_array( $cheese->get_id(), $ids, true ), 'effective: Ziegenkäse ohne saturates gelistet' );
$milk->set_nutrition( array( 'saturates' => 0 ) );
$r   = mmm_bu_call( 'GET', '/missing', array(), array( 'field' => 'saturates', 'per_page' => 200 ) );
mmm_bu_assert( ! in_array( $milk->get_id(), wp_list_pluck( $r->get_data()['items'], 'id' ), true ), 'effective: Milch nicht (Referenz liefert 2,3)' );
$r    = mmm_bu_call( 'GET', '/missing', array(), array( 'field' => 'saturates', 'mode' => 'own', 'per_page' => 200 ) );
$rows = array_column( $r->get_data()['items'], null, 'id' );
mmm_bu_assert( isset( $rows[ $milk->get_id() ] ) && 0.0 === $rows[ $milk->get_id() ]['own_value'] && 2.3 === $rows[ $milk->get_id() ]['inherited_value'], 'own: Milch mit own 0 / inherited 2,3' );
mmm_bu_assert( array( 'id', 'title', 'own_value', 'inherited_value', 'reference_product' ) === array_keys( $rows[ $milk->get_id() ] ), 'nur die fünf Felder' );
$r = mmm_bu_call( 'GET', '/missing', array(), array( 'field' => 'saturates', 'type' => 'product', 'per_page' => 200 ) );
mmm_bu_assert( in_array( $bare->get_id(), wp_list_pluck( $r->get_data()['items'], 'id' ), true ), 'Produkte: ohne saturates gelistet' );
$r = mmm_bu_call( 'GET', '/missing', array(), array( 'field' => 'saturates', 'mode' => 'own', 'per_page' => 1 ) );
mmm_bu_assert( 1 === count( $r->get_data()['items'] ) && $r->get_data()['total_pages'] >= 2, 'paginiert' );

echo "\n-- 7. Zusammenführen: Produkte ziehen mit, Festlegungen bleiben gültig\n";
$dupe   = Mixmymeals_Ingredient::create( 'T Ziegenkäse, Rolle' );
$roll   = mmm_bu_product( $dupe->get_id(), 'T Rolle' );
$recipe = Mixmymeals_Recipe::create( 'T Rezept' );
$recipe->set_steps( array( array( 'title' => 'a', 'description' => '', 'ingredients' => array( array( 'id' => $dupe->get_id(), 'amount' => 100, 'amount_id' => 0, 'product_id' => $roll->get_id() ) ) ) ) );
$r = mmm_bu_call( 'POST', '/ingredients/' . $dupe->get_id() . '/merge', array( 'to_id' => $cheese->get_id(), 'dry_run' => true ) );
mmm_bu_assert( array( $roll->get_id() ) === wp_list_pluck( $r->get_data()['products_moved'], 'id' ) && get_post( $dupe->get_id() ), 'dry_run nennt das Produkt und ändert nichts' );
$r     = mmm_bu_call( 'POST', '/ingredients/' . $dupe->get_id() . '/merge', array( 'to_id' => $cheese->get_id() ) );
$steps = get_post_meta( $recipe->get_id(), '_rmm_steps', true );
mmm_bu_assert( true === $r->get_data()['merged'] && null === get_post( $dupe->get_id() ), 'Quelle gelöscht' );
mmm_bu_assert( $cheese->get_id() === (int) $steps[0]['ingredients'][0]['id'], 'Rezeptzeile umgehängt' );
mmm_bu_assert( $cheese->get_id() === (int) wp_get_post_parent_id( $roll->get_id() ), 'Produkt hängt am Ziel' );
mmm_bu_assert( $roll->get_id() === Mixmymeals_Ingredient::pinned_product_id( $cheese->get_id(), (int) $steps[0]['ingredients'][0]['product_id'] ), 'Festlegung im Rezept bleibt gültig' );

echo "\n-- 8. Rechte\n";
wp_set_current_user( 0 );
$r = mmm_bu_call( 'POST', '/bulk-update', array( 'items' => array( array( 'type' => 'ingredient', 'id' => $milk->get_id(), 'nutrition' => array( 'fat' => 1 ) ) ) ) );
mmm_bu_assert( in_array( $r->get_status(), array( 401, 403 ), true ), 'anonym: bulk-update abgewiesen' );
$r = mmm_bu_call( 'GET', '/missing', array(), array( 'field' => 'fat' ) );
mmm_bu_assert( in_array( $r->get_status(), array( 401, 403 ), true ), 'anonym: missing abgewiesen' );
wp_set_current_user( (int) ( $admins[0] ?? 1 ) );

wp_delete_post( $recipe->get_id(), true );
foreach ( array( $pkt, $bare, $roll ) as $p ) {
	wp_delete_post( $p->get_id(), true );
}
wp_delete_post( $milk->get_id(), true );
wp_delete_post( $cheese->get_id(), true );

echo "\n=== Ergebnis ===\n";
printf( "  %d bestanden, %d fehlgeschlagen\n", $GLOBALS['MMM_BU_PASS'], $GLOBALS['MMM_BU_FAIL'] );
