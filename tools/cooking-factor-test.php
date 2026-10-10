<?php
/**
 * Prüft den Garfaktor: Auflösung Produkt → Zutat → keiner, und dass er nur
 * Gewicht und Werte pro 100 g bewegt, nie die Nährwerte pro Portion.
 *
 * Läuft gegen eine echte Installation, legt eigene Posts an und löscht sie
 * wieder. Alles heißt „ZZ …".
 *
 * wp --require=<dbhost.php> eval-file \
 *    wp-content/plugins/mixmymeals/tools/cooking-factor-test.php
 *
 * @package MixMyMeals
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$GLOBALS['MMM_CF_PASS']  = 0;
$GLOBALS['MMM_CF_FAIL']  = 0;
$GLOBALS['MMM_CF_TRASH'] = array();

/**
 * Eine Zusicherung.
 *
 * @param bool   $ok    Ergebnis.
 * @param string $label Beschreibung.
 * @return void
 */
function mmm_cf_assert( bool $ok, string $label ): void {
	++$GLOBALS[ $ok ? 'MMM_CF_PASS' : 'MMM_CF_FAIL' ];
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
}

/**
 * Legt einen Post an und merkt ihn zum Aufräumen.
 *
 * @param array $args wp_insert_post()-Argumente.
 * @return int
 */
function mmm_cf_post( array $args ): int {
	$id = (int) wp_insert_post( $args + array( 'post_status' => 'publish' ) );

	$GLOBALS['MMM_CF_TRASH'][] = $id;

	return $id;
}

/**
 * Ungefähr gleich.
 *
 * @param float $a Wert.
 * @param float $b Wert.
 * @return bool
 */
function mmm_cf_near( float $a, float $b ): bool {
	return abs( $a - $b ) < 0.01;
}

/**
 * Ein REST-Aufruf an den eigenen Namensraum.
 *
 * @param string $method Methode.
 * @param string $path   Pfad unter /mixmymeals/v1.
 * @param array  $body   JSON-Körper.
 * @param array  $query  Query-Parameter.
 * @return WP_REST_Response
 */
function mmm_cf_call( string $method, string $path, array $body = array(), array $query = array() ): WP_REST_Response {
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

$mmm_cf_admins = get_users( array( 'role' => 'administrator', 'fields' => 'ID', 'number' => 1 ) );
wp_set_current_user( (int) ( $mmm_cf_admins[0] ?? 1 ) );

try {

	echo "\n=== 1. Auflösung ===\n";

	$pasta = mmm_cf_post( array( 'post_type' => 'mmm_ingredient', 'post_title' => 'ZZ Nudeln' ) );
	$ing   = new Mixmymeals_Ingredient( $pasta );
	$ing->set_nutrition( array( 'calories_100' => 360, 'fat' => 1.5, 'carbs' => 72, 'salt' => 0.01 ) );

	mmm_cf_assert( 100.0 === $ing->cooked_weight( 100.0 ), 'ohne Faktor: keine Korrektur' );

	$ing->set_cooking_factor( 2.5 );
	mmm_cf_assert( mmm_cf_near( 250.0, $ing->cooked_weight( 100.0 ) ), 'Faktor der Zutat' );

	$fresh = mmm_cf_post( array( 'post_type' => 'mmm_product', 'post_title' => 'ZZ Frische Nudeln', 'post_parent' => $pasta ) );
	( new Mixmymeals_Product( $fresh ) )->set_brand( 'ZZ' );
	( new Mixmymeals_Product( $fresh ) )->set_cooking_factor( 1.2 );

	$plain = mmm_cf_post( array( 'post_type' => 'mmm_product', 'post_title' => 'ZZ Hausmarke', 'post_parent' => $pasta ) );
	( new Mixmymeals_Product( $plain ) )->set_brand( 'ZZ' );

	mmm_cf_assert( mmm_cf_near( 120.0, $ing->cooked_weight( 100.0, $fresh ) ), 'festgelegtes Produkt mit Faktor geht vor' );
	mmm_cf_assert( mmm_cf_near( 250.0, $ing->cooked_weight( 100.0, $plain ) ), 'Produkt ohne Faktor: der der Zutat gilt' );

	Mixmymeals_Product::set_reference_id( $pasta, $fresh );
	mmm_cf_assert( mmm_cf_near( 120.0, $ing->cooked_weight( 100.0 ) ), 'Referenzprodukt mit Faktor geht vor' );
	mmm_cf_assert( 2.5 === $ing->get_own_cooking_factor(), 'der eigene Faktor bleibt der eigene' );
	Mixmymeals_Product::set_reference_id( $pasta, 0 );

	$ing->set_cooking_factor( null );
	mmm_cf_assert( 100.0 === $ing->cooked_weight( 100.0, $plain ), 'weder Produkt noch Zutat: keine Korrektur' );

	mmm_cf_assert( null === Mixmymeals_Ingredient::normalise_cooking_factor( '0' ), '0 ist kein Faktor' );
	mmm_cf_assert( 2.4 === Mixmymeals_Ingredient::normalise_cooking_factor( '2,4' ), 'Komma wird gelesen' );

	echo "\n=== 2. Rezept ===\n";

	$sauce = mmm_cf_post( array( 'post_type' => 'mmm_ingredient', 'post_title' => 'ZZ Sauce' ) );
	( new Mixmymeals_Ingredient( $sauce ) )->set_nutrition( array( 'calories_100' => 50, 'fat' => 2, 'carbs' => 6, 'salt' => 0.8 ) );

	$recipe = mmm_cf_post( array( 'post_type' => 'mmm_recipe', 'post_title' => 'ZZ Spaghetti' ) );
	update_post_meta( $recipe, '_mmm_servings', 2 );
	$r = new Mixmymeals_Recipe( $recipe );
	$r->set_steps(
		array(
			array(
				'title'       => 'S',
				'description' => 'x',
				'ingredients' => array(
					array( 'id' => $pasta, 'amount' => 200, 'amount_id' => 0 ),
					array( 'id' => $sauce, 'amount' => 400, 'amount_id' => 0 ),
				),
			),
		)
	);

	$raw_serving = $r->calculate_nutrition_per_serving();
	$raw_100g    = $r->calculate_nutrition_per_100g();
	mmm_cf_assert( mmm_cf_near( 300.0, $raw_serving['weight_per_serving'] ), 'ohne Faktor: Portion wiegt roh 300 g' );

	$ing->set_cooking_factor( 2.5 );
	$r->set_steps( $r->get_steps() ); // Leert den Speicher für den Request.

	$cooked_serving = $r->calculate_nutrition_per_serving();
	$cooked_100g    = $r->calculate_nutrition_per_100g();

	// 200 g × 2,5 + 400 g = 900 g, zwei Portionen.
	mmm_cf_assert( mmm_cf_near( 450.0, $cooked_serving['weight_per_serving'] ), 'mit Faktor: Portion wiegt gegart 450 g' );
	mmm_cf_assert( mmm_cf_near( $raw_serving['calories'], $cooked_serving['calories'] ), 'Energie pro Portion bleibt gleich' );
	mmm_cf_assert( mmm_cf_near( $raw_serving['salt'], $cooked_serving['salt'] ), 'Salz pro Portion bleibt gleich' );
	mmm_cf_assert( mmm_cf_near( $raw_100g['calories'] * 600 / 900, $cooked_100g['calories'] ), 'pro 100 g sinkt im Verhältnis roh : gegart' );

	echo "\n=== 3. Mahlzeit und Plan ===\n";

	$plan = mmm_cf_post( array( 'post_type' => 'mmm_meal_plan', 'post_title' => 'ZZ Plan' ) );
	update_post_meta( $plan, 'start_day', 1 );
	update_post_meta( $plan, 'end_day', 1 );
	$meal = mmm_cf_post( array( 'post_type' => 'mmm_meal', 'post_title' => 'ZZ Mahlzeit', 'post_parent' => $plan ) );
	$m    = new Mixmymeals_Meal( $meal );
	$m->set_recipe_id( $recipe );
	$m->set_servings( 2 );
	$m->set_day( 1 );

	$plan_100g = ( new Mixmymeals_Meal_Plan( $plan ) )->calc_nutrition_per_100g();
	mmm_cf_assert( mmm_cf_near( $cooked_100g['calories'], (float) $plan_100g['calories'] ), 'Plan aus einem Gericht: dessen gegarte Werte pro 100 g' );

	$GLOBALS['post'] = get_post( $meal ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the meal table reads the current post.
	setup_postdata( $GLOBALS['post'] );
	$table = do_shortcode( '[mmm_meal_nutrition_table_for_a_single_meal]' );
	wp_reset_postdata();
	mmm_cf_assert( 1 === preg_match( '/\(450 g \S+\)/u', $table ), 'Mahlzeit nennt das gegarte Portionsgewicht, gekennzeichnet' );
	mmm_cf_assert( false !== strpos( $table, 'mmm-nutri-note--cooked' ) && false !== strpos( $table, 'ZZ Nudeln' ), 'Mahlzeit: Hinweis unter der Tabelle nennt die Zutat' );

	$table = do_shortcode( '[mmm_nutrition_table_for_a_single_recipe post_id="' . $recipe . '"]' );
	mmm_cf_assert( false !== strpos( $table, 'mmm-nutri-note--cooked' ), 'Rezept: Hinweis vorhanden' );
	mmm_cf_assert( array( 'ZZ Nudeln' ) === $r->get_cooked_ingredients(), 'Rezept: genau die Zutat mit Faktor, nicht die Sauce' );

	$table = do_shortcode( '[mmm_nutrition_overview_for_a_single_meal_plan meal_plan_id="' . $plan . '"]' );
	mmm_cf_assert( false !== strpos( $table, 'mmm-nutri-note--cooked' ), 'Plan: Hinweis vorhanden' );

	$mmm_cf_option = get_option( Mixmymeals_Nutrition_Table::OPTION_PREFIX . 'recipe', false );
	update_option( Mixmymeals_Nutrition_Table::OPTION_PREFIX . 'recipe', Mixmymeals_Nutrition_Table::sanitize( 'recipe', array( 'columns' => array( 'serving' => array( 'show' => '1' ) ) ) ) );
	$table = do_shortcode( '[mmm_nutrition_table_for_a_single_recipe post_id="' . $recipe . '"]' );
	mmm_cf_assert( false === strpos( $table, 'mmm-nutri-note--cooked' ) && false !== strpos( $table, '(450 g)' ), 'Schalter aus: kein Hinweis, keine Kennzeichnung' );
	if ( false === $mmm_cf_option ) {
		delete_option( Mixmymeals_Nutrition_Table::OPTION_PREFIX . 'recipe' );
	} else {
		update_option( Mixmymeals_Nutrition_Table::OPTION_PREFIX . 'recipe', $mmm_cf_option );
	}

	$ing->set_cooking_factor( 1.0 );
	$r->set_steps( $r->get_steps() );
	mmm_cf_assert( array() === $r->get_cooked_ingredients(), 'Faktor 1,0 bewegt nichts und wird nicht genannt' );
	$ing->set_cooking_factor( 2.5 );
	$r->set_steps( $r->get_steps() );

	echo "\n=== 4. Bulk und Lückenliste ===\n";

	$res = mmm_cf_call( 'POST', '/bulk-update', array( 'items' => array(
		array( 'type' => 'ingredient', 'id' => $sauce, 'cooking_factor' => 0.8 ),
		array( 'type' => 'product', 'id' => $plain, 'cooking_factor' => 2.2 ),
	) ) )->get_data();
	mmm_cf_assert( 2 === (int) $res['changed'], 'Bulk: zwei Einträge geändert' );
	mmm_cf_assert( array( null, 0.8 ) === (array) $res['results'][0]['changes']->cooking_factor, 'Bulk: Diff [alt, neu] für die Zutat' );
	mmm_cf_assert( 0.8 === ( new Mixmymeals_Ingredient( $sauce ) )->get_own_cooking_factor(), 'Bulk: Zutat geschrieben' );
	mmm_cf_assert( 2.2 === ( new Mixmymeals_Product( $plain ) )->get_cooking_factor(), 'Bulk: Produkt geschrieben' );

	$res = mmm_cf_call( 'POST', '/bulk-update', array( 'items' => array( array( 'type' => 'ingredient', 'id' => $sauce, 'nutrition' => array( 'fat' => 3 ) ) ) ) )->get_data();
	mmm_cf_assert( ! isset( ( (array) $res['results'][0]['changes'] )['cooking_factor'] ), 'Bulk: weggelassener Schlüssel bleibt unangetastet' );
	mmm_cf_assert( 0.8 === ( new Mixmymeals_Ingredient( $sauce ) )->get_own_cooking_factor(), 'Bulk: Faktor steht noch' );

	$res = mmm_cf_call( 'POST', '/bulk-update', array( 'items' => array( array( 'type' => 'ingredient', 'id' => $sauce, 'cooking_factor' => null ) ) ) )->get_data();
	mmm_cf_assert( null === ( new Mixmymeals_Ingredient( $sauce ) )->get_own_cooking_factor(), 'Bulk: null löscht' );

	$res = mmm_cf_call( 'POST', '/bulk-update', array( 'items' => array( array( 'type' => 'ingredient', 'id' => $sauce, 'cooking_factor' => 50 ) ) ) );
	mmm_cf_assert( 400 === $res->get_status(), 'Bulk: Faktor über 20 weist den Aufruf ab' );

	Mixmymeals_Product::set_reference_id( $pasta, $fresh );
	$res = mmm_cf_call( 'POST', '/bulk-update', array( 'items' => array( array( 'type' => 'ingredient', 'id' => $pasta, 'cooking_factor' => 2.6 ) ) ) )->get_data();
	mmm_cf_assert( in_array( 'cooking_factor', (array) ( $res['results'][0]['shadowed'] ?? array() ), true ), 'Bulk: vom Referenzprodukt verdeckt → shadowed' );
	Mixmymeals_Product::set_reference_id( $pasta, 0 );

	$ids = static function ( array $data ): array {
		return array_map( static fn( $row ) => (int) $row['id'], (array) $data['items'] );
	};

	$data = mmm_cf_call( 'GET', '/missing', array(), array( 'field' => 'cooking_factor', 'per_page' => 200 ) )->get_data();
	mmm_cf_assert( in_array( $sauce, $ids( $data ), true ), 'Lücke: Sauce ohne Faktor wird gelistet' );
	mmm_cf_assert( ! in_array( $pasta, $ids( $data ), true ), 'Lücke: Nudeln mit Faktor nicht' );

	$data = mmm_cf_call( 'GET', '/missing', array(), array( 'field' => 'cooking_factor', 'type' => 'product', 'per_page' => 200 ) )->get_data();
	mmm_cf_assert( ! in_array( $plain, $ids( $data ), true ) && ! in_array( $fresh, $ids( $data ), true ), 'Lücke: Produkte mit Faktor nicht gelistet' );

} finally {

	foreach ( array_reverse( $GLOBALS['MMM_CF_TRASH'] ) as $trash_id ) {
		wp_delete_post( $trash_id, true );
	}
}

printf( "\n%d ok, %d fehlgeschlagen\n", $GLOBALS['MMM_CF_PASS'], $GLOBALS['MMM_CF_FAIL'] );
