<?php
/**
 * Prüft die Nährwert-Ampel: Schwellen, Richtung, und dass sie von Zutat und
 * Produkt bis in Rezept, Mahlzeit und Plan durchschlägt.
 *
 * Läuft gegen eine echte Installation, legt eigene Posts an und löscht sie
 * wieder. Alles heißt „ZZ …". Die Ampel-Optionen werden gesichert und am Ende
 * zurückgeschrieben.
 *
 * wp --require=<dbhost.php> eval-file \
 *    wp-content/plugins/mixmymeals/tools/traffic-light-test.php
 *
 * @package MixMyMeals
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// $GLOBALS statt global: eval-file läuft in einem Funktionsgeltungsbereich.
$GLOBALS['MMM_TL_PASS']  = 0;
$GLOBALS['MMM_TL_FAIL']  = 0;
$GLOBALS['MMM_TL_TRASH'] = array();

/**
 * Eine Zusicherung.
 *
 * @param bool   $ok    Ergebnis.
 * @param string $label Beschreibung.
 * @return void
 */
function mmm_tl_assert( bool $ok, string $label ): void {
	++$GLOBALS[ $ok ? 'MMM_TL_PASS' : 'MMM_TL_FAIL' ];
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
}

/**
 * Legt einen Post an und merkt ihn zum Aufräumen.
 *
 * @param array $args wp_insert_post()-Argumente.
 * @return int
 */
function mmm_tl_post( array $args ): int {
	$id = (int) wp_insert_post( $args + array( 'post_status' => 'publish' ) );

	$GLOBALS['MMM_TL_TRASH'][] = $id;

	return $id;
}

/**
 * Die Farbe eines Werts, oder '' ohne Ampel.
 *
 * @param string     $key   Summenschlüssel.
 * @param float|null $value Wert pro 100 g.
 * @return string
 */
function mmm_tl_colour( string $key, ?float $value ): string {
	return (string) ( Mixmymeals_Ingredient::traffic_light( $key, $value )['colour'] ?? '' );
}

/**
 * Die Farbe, die die Ampelzeile eines Posts für einen Wert zeigt.
 *
 * @param int    $post_id Post.
 * @param string $key     Summenschlüssel.
 * @return string
 */
function mmm_tl_row_colour( int $post_id, string $key ): string {

	$html  = do_shortcode( '[mmm_nutrition_traffic_lights post_id="' . $post_id . '"]' );
	$label = preg_quote( esc_html( Mixmymeals_Ingredient::nutrition_label( $key ) ), '/' );

	return preg_match( '/' . $label . '<\/span>.*?mmm-traffic-light--(\w+)/', $html, $m ) ? $m[1] : '';
}

$keys   = Mixmymeals_Meal_Plan::NUTRITION_KEYS;
$backup = array();

foreach ( $keys as $key ) {
	$backup[ $key ] = get_option( Mixmymeals_Ingredient::TRAFFIC_LIGHT_OPTION_PREFIX . $key, false );
	delete_option( Mixmymeals_Ingredient::TRAFFIC_LIGHT_OPTION_PREFIX . $key );
}

try {

	echo "\n=== 1. Schwellen und Richtung ===\n";

	$t = Mixmymeals_Ingredient::traffic_light_thresholds();
	mmm_tl_assert( array( 'fat', 'saturates', 'sugar', 'salt' ) === array_keys( $t ), 'ohne Einstellung: die vier FSA-Werte, sonst nichts' );

	mmm_tl_assert( 'green' === mmm_tl_colour( 'salt', 0.3 ), 'Salz 0,3 g ist noch niedrig (bis einschließlich)' );
	mmm_tl_assert( 'amber' === mmm_tl_colour( 'salt', 0.31 ), 'Salz 0,31 g ist mittel' );
	mmm_tl_assert( 'amber' === mmm_tl_colour( 'salt', 1.5 ), 'Salz 1,5 g ist noch mittel' );
	mmm_tl_assert( 'red' === mmm_tl_colour( 'salt', 1.51 ), 'Salz 1,51 g ist hoch' );
	mmm_tl_assert( '' === mmm_tl_colour( 'salt', null ), 'ein nicht angegebener Wert bekommt keine Ampel' );
	mmm_tl_assert( '' === mmm_tl_colour( 'fiber', 9.0 ), 'Ballaststoffe haben ohne Einstellung keine Ampel' );

	update_option( Mixmymeals_Ingredient::TRAFFIC_LIGHT_OPTION_PREFIX . 'fiber', array( 'low' => '3', 'high' => '6' ) );
	mmm_tl_assert( 'green' === mmm_tl_colour( 'fiber', 7.0 ), 'Ballaststoffe sind ein Mindestwert: viel ist grün' );
	mmm_tl_assert( 'red' === mmm_tl_colour( 'fiber', 1.0 ), 'und wenig rot' );
	mmm_tl_assert( 'high' === Mixmymeals_Ingredient::traffic_light( 'fiber', 7.0 )['amount'], 'die Menge heißt trotzdem „hoch“' );

	update_option( Mixmymeals_Ingredient::TRAFFIC_LIGHT_OPTION_PREFIX . 'salt', array( 'low' => '', 'high' => '' ) );
	mmm_tl_assert( '' === mmm_tl_colour( 'salt', 5.0 ), 'leer gespeichert schaltet die Ampel ab, statt den Standard zurückzuholen' );

	update_option( Mixmymeals_Ingredient::TRAFFIC_LIGHT_OPTION_PREFIX . 'salt', array( 'low' => '2', 'high' => '1' ) );
	mmm_tl_assert( '' === mmm_tl_colour( 'salt', 5.0 ), 'vertauschte Schwellen: aus statt falsch' );
	delete_option( Mixmymeals_Ingredient::TRAFFIC_LIGHT_OPTION_PREFIX . 'salt' );

	$s = Mixmymeals_Ingredient::sanitize_traffic_light( array( 'low' => '0,3', 'high' => 'abc' ) );
	mmm_tl_assert( array( 'low' => '0.3', 'high' => '' ) === $s, 'Komma wird Punkt, Unsinn wird leer' );

	echo "\n=== 2. Zutat und Produkt ===\n";

	$salty = mmm_tl_post( array( 'post_type' => 'mmm_ingredient', 'post_title' => 'ZZ Brühe' ) );
	( new Mixmymeals_Ingredient( $salty ) )->set_nutrition( array( 'calories_100' => 5, 'fat' => 0.2, 'salt' => 0.9, 'fiber' => 0.1 ) );
	mmm_tl_assert( 'amber' === mmm_tl_row_colour( $salty, 'salt' ), 'Zutat: 0,9 g Salz ist mittel' );

	$powder = mmm_tl_post( array( 'post_type' => 'mmm_product', 'post_title' => 'ZZ Pulver', 'post_parent' => $salty ) );
	( new Mixmymeals_Product( $powder ) )->set_brand( 'ZZ' );
	( new Mixmymeals_Product( $powder ) )->set_nutrition( array( 'salt' => 45 ) );
	Mixmymeals_Product::set_reference_id( $salty, $powder );
	mmm_tl_assert( 'red' === mmm_tl_row_colour( $salty, 'salt' ), 'mit Referenzprodukt schlägt dessen Salz durch: rot' );

	$table = do_shortcode( '[mmm_nutrition_table_for_a_single_ingredient id="' . $salty . '"]' );
	mmm_tl_assert( false !== strpos( $table, 'mmm-traffic-light--red' ), 'und die Nährwerttabelle der Zutat zeigt sie auch' );

	echo "\n=== 3. Rezept, Mahlzeit, Plan ===\n";

	$veg = mmm_tl_post( array( 'post_type' => 'mmm_ingredient', 'post_title' => 'ZZ Gemüse' ) );
	( new Mixmymeals_Ingredient( $veg ) )->set_nutrition(
		array( 'calories_100' => 30, 'fat' => 0.2, 'saturates' => 0, 'carbs' => 5, 'sugar' => 3, 'fiber' => 2, 'protein' => 1, 'salt' => 0.02 )
	);

	$recipe = mmm_tl_post( array( 'post_type' => 'mmm_recipe', 'post_title' => 'ZZ Suppe' ) );
	update_post_meta( $recipe, '_mmm_servings', 2 );
	( new Mixmymeals_Recipe( $recipe ) )->set_steps(
		array(
			array(
				'title'       => 'S',
				'description' => 'x',
				'ingredients' => array(
					array( 'id' => $veg, 'amount' => 900, 'amount_id' => 0 ),
					array( 'id' => $salty, 'amount' => 20, 'amount_id' => 0 ),
				),
			),
		)
	);

	// 20 g × 45 % = 9 g Salz auf 920 g: knapp 1 g pro 100 g, also mittel.
	mmm_tl_assert( 'amber' === mmm_tl_row_colour( $recipe, 'salt' ), 'Rezept: das Salz des Produkts kommt an (mittel)' );

	$table = do_shortcode( '[mmm_nutrition_table_for_a_single_recipe post_id="' . $recipe . '"]' );
	mmm_tl_assert( 1 <= substr_count( $table, 'mmm-traffic-light--' ), 'die Rezepttabelle trägt Ampeln' );

	/*
	 * Das Produkt nennt nur Salz, die Zutat darunter aber Fett — fehlen darf
	 * also nur, was weder Produkt noch Zutat angeben: gesättigte Fettsäuren
	 * und Zucker der Brühe. Für die gibt es keine Ampel, nicht eine grüne.
	 */
	mmm_tl_assert( '' === mmm_tl_row_colour( $recipe, 'saturates' ), 'Lücke bei gesättigten Fettsäuren: keine Ampel statt grün' );
	mmm_tl_assert( 'green' === mmm_tl_row_colour( $recipe, 'fat' ), 'vollständig angegebenes Fett: grün' );

	$plan = mmm_tl_post( array( 'post_type' => 'mmm_meal_plan', 'post_title' => 'ZZ Plan' ) );
	update_post_meta( $plan, 'start_day', 1 );
	update_post_meta( $plan, 'end_day', 2 );
	$meal = mmm_tl_post( array( 'post_type' => 'mmm_meal', 'post_title' => 'ZZ Mahlzeit', 'post_parent' => $plan ) );
	$m    = new Mixmymeals_Meal( $meal );
	$m->set_recipe_id( $recipe );
	$m->set_servings( 3 );
	$m->set_day( 1 );

	mmm_tl_assert( 'amber' === mmm_tl_row_colour( $meal, 'salt' ), 'Mahlzeit: dieselbe Ampel wie das Rezept' );
	mmm_tl_assert( 'amber' === mmm_tl_row_colour( $plan, 'salt' ), 'Plan: dieselbe Ampel' );

	$table = do_shortcode( '[mmm_nutrition_overview_for_a_single_meal_plan meal_plan_id="' . $plan . '"]' );
	mmm_tl_assert( false !== strpos( $table, 'mmm-traffic-light--amber' ), 'die Nährwerttabelle des Plans trägt sie in der Spalte pro 100 g' );

	$GLOBALS['post'] = get_post( $meal ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the meal table reads the current post.
	setup_postdata( $GLOBALS['post'] );
	$table = do_shortcode( '[mmm_meal_nutrition_table_for_a_single_meal]' );
	wp_reset_postdata();
	mmm_tl_assert( false !== strpos( $table, 'mmm-traffic-light--amber' ), 'die Nährwerttabelle der Mahlzeit auch' );

	Mixmymeals_Product::set_reference_id( $salty, 0 );
	/*
	 * Die Nährwerte pro Portion sind für den Request gemerkt, und eine
	 * geänderte Referenz leert diesen Speicher nicht — im Betrieb ändert sie
	 * sich nie mitten in einem Seitenaufruf. Neu speichern leert ihn, wie
	 * ein neuer Request.
	 */
	$recipe_obj = new Mixmymeals_Recipe( $recipe );
	$recipe_obj->set_steps( $recipe_obj->get_steps() );
	mmm_tl_assert( 'green' === mmm_tl_row_colour( $plan, 'salt' ), 'ohne Pulver ist der Plan grün: die Zutat schlägt bis in den Plan durch' );

	$per_100g = ( new Mixmymeals_Meal_Plan( $plan ) )->calc_nutrition_per_100g();
	$recipe_100g = ( new Mixmymeals_Recipe( $recipe ) )->calculate_nutrition_per_100g();
	mmm_tl_assert( abs( $per_100g['salt'] - $recipe_100g['salt'] ) < 0.001, 'ein Plan aus einem Gericht hat dessen Werte pro 100 g' );

} finally {

	foreach ( array_reverse( $GLOBALS['MMM_TL_TRASH'] ) as $trash_id ) {
		wp_delete_post( $trash_id, true );
	}

	foreach ( $backup as $key => $value ) {
		if ( false === $value ) {
			delete_option( Mixmymeals_Ingredient::TRAFFIC_LIGHT_OPTION_PREFIX . $key );
		} else {
			update_option( Mixmymeals_Ingredient::TRAFFIC_LIGHT_OPTION_PREFIX . $key, $value );
		}
	}
}

printf( "\n%d ok, %d fehlgeschlagen\n", $GLOBALS['MMM_TL_PASS'], $GLOBALS['MMM_TL_FAIL'] );
