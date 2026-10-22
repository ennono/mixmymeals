<?php
/**
 * Prüft die Ernährungsform einer Mahlzeit: ein Rezept, das über seine
 * Produkte vegan gekocht werden kann, wird in einer Mahlzeit „vegan“ auch so
 * gerechnet — Produkte, Nährwerte, Kosten, Ernährungsform, Einkaufsliste.
 *
 * Läuft gegen eine echte Installation, legt eigene Posts und zwei Diät-Terme
 * an und löscht alles wieder. Alles heißt „ZZ …“.
 *
 * wp --require=<dbhost.php> eval-file \
 *    wp-content/plugins/mixmymeals/tools/meal-diet-test.php
 *
 * @package MixMyMeals
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// In $GLOBALS: wp-cli führt eval-file in einem Funktionsgeltungsbereich aus.
$GLOBALS['MMM_MD_PASS']  = 0;
$GLOBALS['MMM_MD_FAIL']  = 0;
$GLOBALS['MMM_MD_TRASH'] = array();
$GLOBALS['MMM_MD_TERMS'] = array();

/**
 * Eine Zusicherung.
 *
 * @param bool   $ok    Ergebnis.
 * @param string $label Beschreibung.
 * @return void
 */
function mmm_md_assert( bool $ok, string $label ): void {
	if ( $ok ) {
		++$GLOBALS['MMM_MD_PASS'];
		echo "ok   $label\n";
		return;
	}
	++$GLOBALS['MMM_MD_FAIL'];
	echo "FAIL $label\n";
}

/**
 * Legt einen Post an und merkt ihn zum Aufräumen.
 *
 * @param string $type   Post-Typ.
 * @param string $title  Titel.
 * @param int    $parent Eltern-Post.
 * @return int
 */
function mmm_md_post( string $type, string $title, int $parent = 0 ): int {
	$id = (int) wp_insert_post(
		array(
			'post_type'   => $type,
			'post_title'  => $title,
			'post_status' => 'publish',
			'post_parent' => $parent,
		)
	);

	$GLOBALS['MMM_MD_TRASH'][] = $id;

	return $id;
}

/**
 * Ein Produkt mit Marke, Energie und Ernährungsform.
 *
 * @param int    $ingredient Zutat.
 * @param string $title      Titel.
 * @param float  $kcal       kcal je 100 g.
 * @param string $diet       Diät-Slug oder ''.
 * @return int
 */
function mmm_md_product( int $ingredient, string $title, float $kcal, string $diet ): int {
	$id  = mmm_md_post( 'mmm_product', $title, $ingredient );
	$obj = new Mixmymeals_Product( $id );
	$obj->set_brand( 'ZZ' );
	$obj->set_nutrition( array( 'calories_100' => $kcal ) );
	if ( '' !== $diet ) {
		Mixmymeals_Diet::set_for_post( $id, array( $diet ) );
	}
	return $id;
}

/**
 * Eine Zutat mit eigener Energie und eigener Ernährungsform.
 *
 * @param string $title Titel.
 * @param float  $kcal  kcal je 100 g.
 * @param string $diet  Diät-Slug oder ''.
 * @return int
 */
function mmm_md_ingredient( string $title, float $kcal, string $diet ): int {
	$id = mmm_md_post( 'mmm_ingredient', $title );
	( new Mixmymeals_Ingredient( $id ) )->set_nutrition( array( 'calories_100' => $kcal ) );
	Mixmymeals_Diet::set_for_post( $id, '' !== $diet ? array( $diet ) : array() );
	return $id;
}

wp_set_current_user( 1 );

echo "-- 0. Diäten als Terme (Vegan unter Vegetarisch)\n";

$vegetarian = get_term_by( 'slug', 'zz-vegetarisch', 'mmm_diet' );
if ( ! $vegetarian ) {
	$vegetarian                = (object) wp_insert_term( 'ZZ Vegetarisch', 'mmm_diet', array( 'slug' => 'zz-vegetarisch' ) );
	$vegetarian->term_id       = (int) $vegetarian->term_id;
	$GLOBALS['MMM_MD_TERMS'][] = $vegetarian->term_id;
}
$vegan = get_term_by( 'slug', 'zz-vegan', 'mmm_diet' );
if ( ! $vegan ) {
	$vegan                     = (object) wp_insert_term( 'ZZ Vegan', 'mmm_diet', array( 'slug' => 'zz-vegan', 'parent' => (int) $vegetarian->term_id ) );
	$GLOBALS['MMM_MD_TERMS'][] = (int) $vegan->term_id;
}
update_term_meta( (int) $vegetarian->term_id, '_mmm_diet_axis', 'diet' );
Mixmymeals_Diet::reset_types();

$VEGAN = 'zz-vegan';
$VEGGI = 'zz-vegetarisch';

mmm_md_assert( in_array( $VEGGI, Mixmymeals_Diet::expand( array( $VEGAN ) ), true ), 'vegan schließt vegetarisch ein' );

echo "\n-- 1. Ein Chili, das vegan gekocht werden kann\n";

$hack   = mmm_md_ingredient( 'ZZ Hackfleisch', 250, '' );
$rind   = mmm_md_product( $hack, 'ZZ Rinderhack', 250, '' );
$soja   = mmm_md_product( $hack, 'ZZ Sojahack', 180, $VEGAN );
Mixmymeals_Product::set_reference_id( $hack, $rind );

$tomate = mmm_md_ingredient( 'ZZ Tomaten', 20, $VEGAN );

// Eigene Angabe vegan, das Referenzpaket nur vegetarisch: vegan geht nur
// über die eigenen Werte der Zutat — PIN_OWN.
$bruehe = mmm_md_ingredient( 'ZZ Bruehe', 5, $VEGAN );
$eibr   = mmm_md_product( $bruehe, 'ZZ Bruehe mit Ei', 10, $VEGGI );
Mixmymeals_Product::set_reference_id( $bruehe, $eibr );

// Optional und nur vegetarisch: fällt in der veganen Umsetzung weg.
$kaese = mmm_md_ingredient( 'ZZ Parmesan', 400, $VEGGI );

$recipe_id = mmm_md_post( 'mmm_recipe', 'ZZ Chili' );
$recipe    = new Mixmymeals_Recipe( $recipe_id );
$recipe->set_servings( 2 );
$recipe->set_steps(
	array(
		array(
			'title'       => 'Kochen',
			'description' => 'Alles in einen Topf.',
			'ingredients' => array(
				array( 'id' => $hack, 'amount' => 400 ),
				array( 'id' => $tomate, 'amount' => 400 ),
				array( 'id' => $bruehe, 'amount' => 200 ),
				array( 'id' => $kaese, 'amount' => 40, 'optional' => true ),
			),
		),
	)
);
$recipe = new Mixmymeals_Recipe( $recipe_id );

mmm_md_assert( array() === array_intersect( array( $VEGAN, $VEGGI ), $recipe->get_diets() ), 'wie geschrieben ist das Chili weder vegan noch vegetarisch' );
mmm_md_assert( $recipe->can_be_cooked_as( $VEGAN ), 'es kann vegan gekocht werden' );
mmm_md_assert( array( $VEGGI, $VEGAN ) === array_values( array_intersect( array( $VEGGI, $VEGAN ), $recipe->get_diet_options() ) ), 'zur Wahl stehen vegetarisch und vegan' );

$as_vegan = $recipe->for_diet( $VEGAN );
mmm_md_assert( $VEGAN === $as_vegan->get_implemented_diet(), 'for_diet() setzt die Umsetzung' );
mmm_md_assert( '' === $recipe->get_implemented_diet(), 'das Original bleibt wie geschrieben' );

$rows = array();
foreach ( $as_vegan->get_ingredients_scaled( 1.0 ) as $row ) {
	$rows[ (int) $row['id'] ] = $row;
}
mmm_md_assert( $soja === (int) $rows[ $hack ]['product_id'], 'vegan: Hack aus dem Sojapaket' );
mmm_md_assert( $VEGAN === $rows[ $hack ]['implemented_for'], 'die Zeile ist als vegan umgesetzt markiert' );
mmm_md_assert( '' === $rows[ $tomate ]['implemented_for'], 'Tomaten bleiben unberührt' );
mmm_md_assert( Mixmymeals_Ingredient::PIN_OWN === (int) $rows[ $bruehe ]['product_id'], 'Brühe aus den eigenen Werten statt des Referenzpakets' );
mmm_md_assert( ! isset( $rows[ $kaese ] ), 'der optionale Parmesan fällt weg' );
mmm_md_assert( in_array( $VEGAN, $as_vegan->get_diets(), true ), 'umgesetzt ist das Gericht vegan' );

$kcal_written = (float) $recipe->calculate_nutrition_per_serving()['calories'];
$kcal_vegan   = (float) $as_vegan->calculate_nutrition_per_serving()['calories'];
mmm_md_assert( abs( $kcal_written - 630 ) < 0.01, "wie geschrieben 630 kcal/Portion ($kcal_written)" );
mmm_md_assert( abs( $kcal_vegan - 405 ) < 0.01, "vegan 405 kcal/Portion ($kcal_vegan)" );
mmm_md_assert( abs( (float) ( new Mixmymeals_Recipe( $recipe_id ) )->calculate_nutrition_per_serving()['calories'] - 630 ) < 0.01, 'der Cache hält beide Umsetzungen auseinander' );

$as_veggi = $recipe->for_diet( $VEGGI );
$rows     = array();
foreach ( $as_veggi->get_ingredients_scaled( 1.0 ) as $row ) {
	$rows[ (int) $row['id'] ] = $row;
}
mmm_md_assert( $soja === (int) $rows[ $hack ]['product_id'], 'vegetarisch: ebenfalls Sojahack' );
mmm_md_assert( 0 === (int) $rows[ $bruehe ]['product_id'], 'vegetarisch: die Brühe mit Ei bleibt' );
mmm_md_assert( isset( $rows[ $kaese ] ), 'vegetarisch: der Parmesan bleibt' );

mmm_md_assert( '' === $recipe->for_diet( 'zz-gibtsnicht' )->get_implemented_diet(), 'eine unbekannte Diät gibt das Rezept wie geschrieben' );

echo "\n-- 2. Die Mahlzeit speichert die Umsetzung\n";

$plan_id = mmm_md_post( 'mmm_meal_plan', 'ZZ Woche' );
update_post_meta( $plan_id, 'start_day', 1 );
update_post_meta( $plan_id, 'end_day', 7 );

$meal_meat  = Mixmymeals_Meal::create( 'ZZ Chili Mo', $plan_id );
$meal_vegan = Mixmymeals_Meal::create( 'ZZ Chili Di', $plan_id );
$GLOBALS['MMM_MD_TRASH'][] = $meal_meat->get_id();
$GLOBALS['MMM_MD_TRASH'][] = $meal_vegan->get_id();

foreach ( array( array( $meal_meat, 1 ), array( $meal_vegan, 2 ) ) as $pair ) {
	$pair[0]->set_recipe_id( $recipe_id );
	$pair[0]->set_servings( 2 );
	$pair[0]->set_type( 'dinner' );
	$pair[0]->set_day( $pair[1] );
}

mmm_md_assert( $meal_vegan->set_diet( $VEGAN ), 'vegan wird gespeichert' );
mmm_md_assert( $VEGAN === $meal_vegan->get_diet(), 'und gelesen' );
mmm_md_assert( $VEGAN === $meal_vegan->get_recipe()->get_implemented_diet(), 'get_recipe() kocht vegan' );
mmm_md_assert( ! $meal_meat->set_diet( 'zz-gibtsnicht' ) && '' === $meal_meat->get_diet(), 'eine unbekannte Diät wird nicht gespeichert' );

$plain = mmm_md_post( 'mmm_recipe', 'ZZ Steak' );
( new Mixmymeals_Recipe( $plain ) )->set_steps( array( array( 'title' => 'x', 'ingredients' => array( array( 'id' => $rind ? $hack : 0, 'amount' => 200, 'excluded_products' => array( $soja ) ) ) ) ) );
$probe = Mixmymeals_Meal::create( 'ZZ Probe', $plan_id );
$GLOBALS['MMM_MD_TRASH'][] = $probe->get_id();
$probe->set_recipe_id( $plain );
mmm_md_assert( ! $probe->set_diet( $VEGAN ), 'ein Rezept, das Soja ausschließt, kann nicht vegan gekocht werden' );
wp_delete_post( $probe->get_id(), true );

echo "\n-- 3. Plan und Einkaufsliste\n";

$plan = new Mixmymeals_Meal_Plan( $plan_id );
$list = $plan->calc_shopping_list( false );
$list = reset( $list );

$lines = array();
foreach ( $list as $key => $data ) {
	$lines[ (string) $key ] = $data;
}

mmm_md_assert( isset( $lines[ $hack . '|' . $soja ], $lines[ (string) $hack ] ), 'Hackfleisch steht zweimal auf der Liste: einmal wie geschrieben, einmal vegan' );
mmm_md_assert( 400.0 === (float) $lines[ (string) $hack ]['total_weight'] && $rind === (int) $lines[ (string) $hack ]['product_id'], '400 g Rinderhack für Montag' );
mmm_md_assert( 400.0 === (float) $lines[ $hack . '|' . $soja ]['total_weight'] && $soja === (int) $lines[ $hack . '|' . $soja ]['product_id'], '400 g Sojahack für Dienstag' );
mmm_md_assert( $VEGAN === $lines[ $hack . '|' . $soja ]['diet'] && $hack === (int) $lines[ $hack . '|' . $soja ]['ingredient_id'], 'die vegane Zeile trägt Diät und Zutat' );
mmm_md_assert( isset( $lines[ (string) $tomate ] ) && 800.0 === (float) $lines[ (string) $tomate ]['total_weight'] && '' === $lines[ (string) $tomate ]['diet'], 'Tomaten bleiben eine Zeile über beide Mahlzeiten' );
mmm_md_assert( isset( $lines[ $bruehe . '|0' ] ) && '' === $lines[ $bruehe . '|0' ]['product'], 'vegane Brühe als eigene Zeile, ohne Paket' );
mmm_md_assert( 40.0 === (float) $lines[ (string) $kaese ]['total_weight'], 'Parmesan nur für Montag' );

$keys = array_keys( $list );
mmm_md_assert( array_search( $hack . '|' . $soja, $keys, true ) === array_search( $hack, $keys, true ) + 1, 'beide Hack-Zeilen stehen nebeneinander' );

mmm_md_assert( '(ZZ Vegan)' === Mixmymeals_Shortcodes::shopping_item_diet_text( $lines[ $hack . '|' . $soja ] ), 'die Zeile nennt die Diät' );
mmm_md_assert( '' === Mixmymeals_Shortcodes::shopping_item_diet_text( $lines[ (string) $hack ] ), 'die andere nicht' );

$bring = implode( "\n", $plan->get_bring_shopping_list_lines() );
mmm_md_assert( 2 === substr_count( $bring, 'ZZ Hackfleisch' ), 'Bring! bekommt beide Hack-Zeilen' );

$by_day = $plan->calc_nutrition_by_day();
mmm_md_assert( abs( (float) $by_day[1]['per_person']['calories'] - 630 ) < 0.01 && abs( (float) $by_day[2]['per_person']['calories'] - 405 ) < 0.01, 'die Nährwerte je Tag folgen der Umsetzung' );

mmm_md_assert( in_array( $VEGAN, ( new Mixmymeals_Meal_Plan( $plan_id ) )->get_diets(), true ) === false, 'der Plan ist nicht vegan, solange Montag mit Fleisch ist' );

$meal_meat->set_diet( $VEGAN );
mmm_md_assert( in_array( $VEGAN, ( new Mixmymeals_Meal_Plan( $plan_id ) )->get_diets(), true ), 'mit beiden Mahlzeiten vegan ist der Plan vegan' );
$all_vegan = ( new Mixmymeals_Meal_Plan( $plan_id ) )->calc_shopping_list( false );
$all_vegan = reset( $all_vegan );
mmm_md_assert( isset( $all_vegan[ $hack ] ) && ! isset( $all_vegan[ $hack . '|' . $soja ] ) && 800.0 === (float) $all_vegan[ $hack ]['total_weight'], 'eine Zutat, die nur noch vegan gekauft wird, ist wieder eine Zeile' );
mmm_md_assert( $VEGAN === $all_vegan[ $hack ]['diet'], 'und sagt, wofür' );
$meal_meat->set_diet( '' );

echo "\n-- 4. Planer: dieselbe Rechnung aus dem Warenkorb\n";

$rows = array(
	array( 'recipe_id' => $recipe_id, 'servings' => 2, 'type' => 'dinner', 'day' => 1 ),
	array( 'recipe_id' => $recipe_id, 'servings' => 2, 'type' => 'dinner', 'day' => 2, 'diet' => $VEGAN ),
);
$from_rows = Mixmymeals_Meal_Plan::from_meal_rows( $rows )->calc_shopping_list( false );
$saved     = ( new Mixmymeals_Meal_Plan( $plan_id ) )->calc_shopping_list( false );
mmm_md_assert( serialize( reset( $from_rows ) ) === serialize( reset( $saved ) ), 'Planer und gespeicherter Plan geben dieselbe Liste' );
mmm_md_assert( $VEGAN === Mixmymeals_Cart::clean_diet( $recipe_id, $VEGAN ) && '' === Mixmymeals_Cart::clean_diet( $plain, $VEGAN ), 'der Warenkorb prüft die Diät wie die Mahlzeit' );

$offset = ( new Mixmymeals_Meal_Plan( $plan_id ) )->with_servings_offset( 1 );
mmm_md_assert( array( '', $VEGAN ) === array_column( $offset->get_meal_rows(), 'diet' ), '?mmm_persons_to_add behält die Umsetzung' );

echo "\n-- 5. Admin-Tabelle und REST\n";

$_POST = array(
	'mmm_plan_meals_nonce' => wp_create_nonce( 'mmm_plan_meals_meta_box' ),
	'mmm_plan_meals'       => array(
		$meal_meat->get_id() => array( 'diet' => $VEGGI ),
	),
);
( new Mixmymeals_Post_Meta() )->save_plan_meals( $plan_id );
$_POST = array();
mmm_md_assert( $VEGGI === ( new Mixmymeals_Meal( $meal_meat->get_id() ) )->get_diet(), 'die Plan-Tabelle speichert die Diät' );

ob_start();
( new Mixmymeals_Post_Meta() )->render_meal_plan_overview_meta_box( get_post( $plan_id ) );
$box = (string) ob_get_clean();
mmm_md_assert( 1 === preg_match( '/<option value="' . $VEGGI . '"\s+selected/', $box ), 'und zeigt sie wieder an' );

$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/mixmymeals/v1/meals/' . $meal_vegan->get_id() ) );
$data     = $response->get_data();
mmm_md_assert( $VEGAN === ( $data['diet'] ?? null ) && $VEGAN === ( $data['diet_applied'] ?? null ), 'REST liefert diet und diet_applied' );

$request = new WP_REST_Request( 'POST', '/mixmymeals/v1/meals/' . $meal_vegan->get_id() );
$request->set_param( 'diet', '' );
rest_get_server()->dispatch( $request );
mmm_md_assert( '' === ( new Mixmymeals_Meal( $meal_vegan->get_id() ) )->get_diet(), 'REST setzt die Diät zurück' );

echo "\n-- 6. Speichern aus dem Planer\n";

$_POST = array(
	'nonce' => wp_create_nonce( 'mmm_frontend_nonce' ),
	'items' => array(
		array( 'recipe_id' => $recipe_id, 'servings' => 2, 'type' => 'dinner', 'day' => 1, 'diet' => '' ),
		array( 'recipe_id' => $recipe_id, 'servings' => 2, 'type' => 'dinner', 'day' => 2, 'diet' => $VEGAN ),
	),
);

$die = static function () {
	return static function () {
		throw new RuntimeException( 'mmm_md_die' );
	};
};

$before = (int) max( array( 0 ) + get_posts( array( 'post_type' => 'mmm_meal_plan', 'post_status' => 'any', 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'DESC', 'posts_per_page' => 1 ) ) );

add_filter( 'wp_doing_ajax', '__return_true' );
add_filter( 'wp_die_ajax_handler', $die );
ob_start();
try {
	do_action( 'wp_ajax_mmm_create_meal_plan' );
} catch ( RuntimeException $e ) {
	unset( $e );
}
ob_end_clean();
remove_filter( 'wp_die_ajax_handler', $die );
remove_filter( 'wp_doing_ajax', '__return_true' );
$_POST = array();

$created = get_posts( array( 'post_type' => 'mmm_meal_plan', 'post_status' => 'any', 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'DESC', 'posts_per_page' => 1 ) );
$created = (int) ( $created[0] ?? 0 );

if ( $created > $before ) {
	$GLOBALS['MMM_MD_TRASH'][] = $created;
	$diets                     = array();
	foreach ( ( new Mixmymeals_Meal_Plan( $created ) )->get_meal_rows() as $row ) {
		$GLOBALS['MMM_MD_TRASH'][] = (int) $row['meal_id'];
		$diets[ (int) $row['day'] ] = $row['diet'];
	}
	ksort( $diets );
	mmm_md_assert( array( 1 => '', 2 => $VEGAN ) === $diets, 'der gespeicherte Plan übernimmt die Diät je Mahlzeit' );
} else {
	mmm_md_assert( false, 'der Planer hat einen Plan gespeichert' );
}

echo "\n=== Aufräumen ===\n";

foreach ( array_reverse( $GLOBALS['MMM_MD_TRASH'] ) as $trash_id ) {
	wp_delete_post( $trash_id, true );
}
foreach ( array_reverse( $GLOBALS['MMM_MD_TERMS'] ) as $term_id ) {
	wp_delete_term( $term_id, 'mmm_diet' );
}
echo 'entfernt: ' . count( $GLOBALS['MMM_MD_TRASH'] ) . ' Posts, ' . count( $GLOBALS['MMM_MD_TERMS'] ) . " Terme\n";

echo "\n=== Ergebnis ===\n";
printf( "  %d bestanden, %d fehlgeschlagen\n", $GLOBALS['MMM_MD_PASS'], $GLOBALS['MMM_MD_FAIL'] );
