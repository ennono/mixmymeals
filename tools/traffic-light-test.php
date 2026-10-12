<?php
/**
 * Prüft die Nährwert-Ampel: Schwellen, Richtung, und dass sie von Zutat und
 * Produkt bis in Rezept, Mahlzeit und Plan durchschlägt. Dazu die
 * Portionsregel, die Rundung, die gespeicherte Null als Lücke, die
 * Tagesbewertung im Plan und die Live-Nährwerte im Planer.
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

// Alle Optionen, die Ampel und Tagesbewertung lesen: gesichert, für den Lauf
// auf ihre Standardwerte gesetzt (also gelöscht), am Ende zurückgeschrieben.
$options = array( Mixmymeals_Ingredient::PORTION_SHARE_OPTION );

foreach ( $keys as $key ) {
	$options[] = Mixmymeals_Ingredient::TRAFFIC_LIGHT_OPTION_PREFIX . $key;
	$options[] = Mixmymeals_Meal_Plan::DAY_RATING_OPTION_PREFIX . $key;
}

foreach ( $options as $option ) {
	$backup[ $option ] = get_option( $option, false );
	delete_option( $option );
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

	// 20 g × 45 % = 9 g Salz auf 920 g: knapp 1 g pro 100 g, also mittel —
	// aber 4,5 g pro Portion sind 75 % der Referenzmenge: rot nach der
	// Portionsregel.
	mmm_tl_assert( 'amber' === mmm_tl_colour( 'salt', ( new Mixmymeals_Recipe( $recipe ) )->calculate_nutrition_per_100g()['salt'] ), 'Rezept: pro 100 g kommt das Salz des Produkts an (mittel)' );
	mmm_tl_assert( 'red' === mmm_tl_row_colour( $recipe, 'salt' ), 'Rezept: 4,5 g Salz pro Portion machen die Ampel rot' );

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

	mmm_tl_assert( 'red' === mmm_tl_row_colour( $meal, 'salt' ), 'Mahlzeit: dieselbe Ampel wie das Rezept' );
	mmm_tl_assert( 'amber' === mmm_tl_row_colour( $plan, 'salt' ), 'Plan: pro 100 g mittel, die Portionsregel gilt dort nicht' );

	$table = do_shortcode( '[mmm_nutrition_overview_for_a_single_meal_plan meal_plan_id="' . $plan . '"]' );
	mmm_tl_assert( false !== strpos( $table, 'mmm-traffic-light--amber' ), 'die Nährwerttabelle des Plans trägt sie in der Spalte pro 100 g' );

	$GLOBALS['post'] = get_post( $meal ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the meal table reads the current post.
	setup_postdata( $GLOBALS['post'] );
	$table = do_shortcode( '[mmm_meal_nutrition_table_for_a_single_meal]' );
	wp_reset_postdata();
	mmm_tl_assert( false !== strpos( $table, 'mmm-traffic-light--red' ), 'die Nährwerttabelle der Mahlzeit auch' );

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

	echo "\n=== 4. Portionsregel ===\n";

	mmm_tl_assert( 30.0 === Mixmymeals_Ingredient::traffic_light_portion_share(), 'ohne Einstellung: 30 % der Referenzmenge' );

	$light = Mixmymeals_Ingredient::traffic_light( 'saturates', 4.0, 17.2 );
	mmm_tl_assert( 'red' === $light['colour'] && 'serving' === $light['basis'], 'Kürbissuppe: 4 g pro 100 g, aber 17,2 g pro Portion (86 %) — rot' );
	mmm_tl_assert( in_array( Mixmymeals_Ingredient::traffic_light_label( 'high', 'serving' ), array( 'hoch pro Portion', 'high per serving' ), true ), 'und heißt „hoch pro Portion“' );
	mmm_tl_assert( 'amber' === mmm_tl_colour( 'salt', 0.8 ) && 'red' === Mixmymeals_Ingredient::traffic_light( 'salt', 0.8, 4.23 )['colour'], 'Bolognese: 0,8 g Salz pro 100 g ist mittel, 4,23 g pro Portion (71 %) rot' );
	mmm_tl_assert( 'amber' === Mixmymeals_Ingredient::traffic_light( 'salt', 0.8, 1.8 )['colour'], '1,8 g Salz pro Portion sind genau 30 %: nicht darüber, bleibt mittel' );
	mmm_tl_assert( 'red' === Mixmymeals_Ingredient::traffic_light( 'salt', 0.8, 1.84 )['colour'], '1,84 g sind 31 %: rot' );
	mmm_tl_assert( 'green' === Mixmymeals_Ingredient::traffic_light( 'salt', 0.2, 0.5 )['colour'], 'eine kleine Portion bleibt, was sie pro 100 g ist' );

	update_option( Mixmymeals_Ingredient::TRAFFIC_LIGHT_OPTION_PREFIX . 'fiber', array( 'low' => '3', 'high' => '6' ) );
	mmm_tl_assert( 'red' === Mixmymeals_Ingredient::traffic_light( 'fiber', 1.0, 20.0 )['colour'] && '100g' === Mixmymeals_Ingredient::traffic_light( 'fiber', 1.0, 20.0 )['basis'], 'Ballaststoffe sind kein Obergrenzen-Wert: die Portionsregel greift nicht' );
	delete_option( Mixmymeals_Ingredient::TRAFFIC_LIGHT_OPTION_PREFIX . 'fiber' );

	update_option( Mixmymeals_Ingredient::PORTION_SHARE_OPTION, '50' );
	mmm_tl_assert( 'amber' === Mixmymeals_Ingredient::traffic_light( 'salt', 0.8, 2.9 )['colour'], 'Grenze 50 %: 2,9 g Salz (48 %) bleiben mittel' );
	update_option( Mixmymeals_Ingredient::PORTION_SHARE_OPTION, Mixmymeals_Ingredient::sanitize_portion_share( '' ) );
	mmm_tl_assert( 'amber' === Mixmymeals_Ingredient::traffic_light( 'salt', 0.8, 5.0 )['colour'], 'leer gespeichert schaltet die Portionsregel ab' );
	delete_option( Mixmymeals_Ingredient::PORTION_SHARE_OPTION );

	$note = Mixmymeals_Ingredient::traffic_light_note( true );
	mmm_tl_assert( false !== strpos( $note, '30 %' ), 'die Fußnote nennt die Portionsgrenze' );
	mmm_tl_assert( false === strpos( Mixmymeals_Ingredient::traffic_light_note( false ), '30 %' ), 'ohne Portion (Zutat, Plan) nennt sie sie nicht' );

	// Ein Eintopf, vollständig angegeben: 900 g Gemüse, 9 g Salz, zwei
	// Portionen — 4,5 g Salz pro Portion bei knapp 1 g pro 100 g.
	$salt = mmm_tl_post( array( 'post_type' => 'mmm_ingredient', 'post_title' => 'ZZ Salz' ) );
	( new Mixmymeals_Ingredient( $salt ) )->set_nutrition(
		array( 'calories_100' => 0, 'fat' => 0, 'saturates' => 0, 'carbs' => 0, 'sugar' => 0, 'fiber' => 0, 'protein' => 0, 'salt' => 100 )
	);
	$stew = mmm_tl_post( array( 'post_type' => 'mmm_recipe', 'post_title' => 'ZZ Eintopf' ) );
	update_post_meta( $stew, '_mmm_servings', 2 );
	( new Mixmymeals_Recipe( $stew ) )->set_steps(
		array(
			array(
				'title'       => 'S',
				'description' => 'x',
				'ingredients' => array(
					array( 'id' => $veg, 'amount' => 900, 'amount_id' => 0 ),
					array( 'id' => $salt, 'amount' => 9, 'amount_id' => 0 ),
				),
			),
		)
	);
	mmm_tl_assert( 'red' === mmm_tl_row_colour( $stew, 'salt' ), 'Eintopf: rot nach der Portionsregel' );

	$table = do_shortcode( '[mmm_nutrition_table_for_a_single_recipe post_id="' . $stew . '"]' );
	mmm_tl_assert( false !== strpos( $table, 'data-basis="serving"' ), 'die Rezepttabelle zeigt die Portionsampel' );
	mmm_tl_assert( false !== strpos( $table, 'mmm-nutri-note--light' ), 'und darunter die Fußnote zur Ampel' );

	echo "\n=== 5. Rundung ===\n";

	mmm_tl_assert( 'green' === mmm_tl_colour( 'fat', 3.04 ), 'Fett 3,04 g wird als „3,0 g“ gezeigt und ist niedrig (bis 3)' );
	mmm_tl_assert( '3,0 g' === Mixmymeals_Meal_Plan::format_nutrition_value( 'fat', 3.04 ) || '3.0 g' === Mixmymeals_Meal_Plan::format_nutrition_value( 'fat', 3.04 ), 'und so gezeigt' );
	mmm_tl_assert( 'amber' === mmm_tl_colour( 'fat', 3.06 ), 'Fett 3,06 g wird „3,1 g“: mittel' );
	mmm_tl_assert( 'green' === mmm_tl_colour( 'saturates', 1.54 ), 'gesättigte Fettsäuren 1,54 g („1,5 g“): niedrig' );
	mmm_tl_assert( 'green' === mmm_tl_colour( 'salt', 0.304 ), 'Salz 0,304 g („0,30 g“): niedrig' );
	mmm_tl_assert( 'amber' === mmm_tl_colour( 'salt', 1.504 ), 'Salz 1,504 g („1,50 g“): noch mittel' );

	echo "\n=== 6. Gespeicherte Null als Lücke ===\n";

	$oil = mmm_tl_post( array( 'post_type' => 'mmm_ingredient', 'post_title' => 'ZZ Öl' ) );
	( new Mixmymeals_Ingredient( $oil ) )->set_nutrition(
		array( 'calories_100' => 884, 'fat' => 100, 'saturates' => 0, 'carbs' => 0, 'sugar' => 0, 'fiber' => 0, 'protein' => 0, 'salt' => 0 )
	);
	mmm_tl_assert( '' === mmm_tl_row_colour( $oil, 'saturates' ), 'Zutat: 0 g gesättigte bei 100 g Fett ist eine Lücke, keine grüne Ampel' );
	mmm_tl_assert( 'red' === mmm_tl_row_colour( $oil, 'fat' ), 'das Fett selbst bleibt bewertet' );

	$lean = mmm_tl_post( array( 'post_type' => 'mmm_ingredient', 'post_title' => 'ZZ Gurke' ) );
	( new Mixmymeals_Ingredient( $lean ) )->set_nutrition(
		array( 'calories_100' => 12, 'fat' => 0.1, 'saturates' => 0, 'carbs' => 2, 'sugar' => 1.5, 'fiber' => 0.5, 'protein' => 0.6, 'salt' => 0.01 )
	);
	mmm_tl_assert( 'green' === mmm_tl_row_colour( $lean, 'saturates' ), 'bei 0,1 g Fett ist 0 g gesättigte eine Angabe: grün' );

	$dressed = mmm_tl_post( array( 'post_type' => 'mmm_recipe', 'post_title' => 'ZZ Salat' ) );
	update_post_meta( $dressed, '_mmm_servings', 2 );
	( new Mixmymeals_Recipe( $dressed ) )->set_steps(
		array(
			array(
				'title'       => 'S',
				'description' => 'x',
				'ingredients' => array(
					array( 'id' => $lean, 'amount' => 400, 'amount_id' => 0 ),
					array( 'id' => $oil, 'amount' => 10, 'amount_id' => 0 ),
				),
			),
		)
	);
	mmm_tl_assert( ( new Mixmymeals_Recipe( $dressed ) )->has_saturates_gap(), 'das Rezept erkennt die Lücke' );
	mmm_tl_assert( '' === mmm_tl_row_colour( $dressed, 'saturates' ), 'Rezept mit dem Öl: keine Ampel für gesättigte Fettsäuren' );
	mmm_tl_assert( 'green' === mmm_tl_row_colour( $dressed, 'salt' ), 'die übrigen Werte bleiben bewertet' );
	mmm_tl_assert( in_array( 'saturates', Mixmymeals_Recipe::unreliable_nutrition_keys( array( $dressed ) ), true ), 'unreliable_nutrition_keys() nennt sie' );

	$plan2 = mmm_tl_post( array( 'post_type' => 'mmm_meal_plan', 'post_title' => 'ZZ Plan Salat' ) );
	$meal2 = mmm_tl_post( array( 'post_type' => 'mmm_meal', 'post_title' => 'ZZ Mahlzeit Salat', 'post_parent' => $plan2 ) );
	$m2    = new Mixmymeals_Meal( $meal2 );
	$m2->set_recipe_id( $dressed );
	$m2->set_servings( 2 );
	$m2->set_day( 1 );
	mmm_tl_assert( '' === mmm_tl_row_colour( $meal2, 'saturates' ), 'Mahlzeit: ebenso keine Ampel' );
	mmm_tl_assert( '' === mmm_tl_row_colour( $plan2, 'saturates' ), 'Plan: ebenso keine Ampel' );

	echo "\n=== 7. Tagesbewertung ===\n";

	$day = array( 'calories' => 2300, 'fat' => 50, 'saturates' => 21, 'carbs' => 250, 'sugar' => 40, 'fiber' => 20, 'protein' => 30, 'salt' => 7 );
	$r   = Mixmymeals_Meal_Plan::rate_day( $day, false );
	mmm_tl_assert( 'over' === ( $r['salt'] ?? '' ), 'Salz 7 g (117 %): zu viel' );
	mmm_tl_assert( 'over' === ( $r['saturates'] ?? '' ), 'gesättigte 21 g (105 %): zu viel' );
	mmm_tl_assert( 'over' === ( $r['calories'] ?? '' ), 'Energie 2 300 kcal (115 %): zu viel' );
	mmm_tl_assert( ! isset( $r['fat'] ), 'Fett 50 g (71 %): keine Bewertung' );
	mmm_tl_assert( ! isset( $r['protein'] ) && ! isset( $r['fiber'] ), 'ohne ganze Tage kein „zu wenig“' );

	$r = Mixmymeals_Meal_Plan::rate_day( $day, true );
	mmm_tl_assert( 'under' === ( $r['protein'] ?? '' ), 'mit ganzen Tagen: Eiweiß 30 g (60 %) zu wenig' );
	mmm_tl_assert( 'under' === ( $r['fiber'] ?? '' ), 'Ballaststoffe 20 g (67 %) zu wenig' );
	mmm_tl_assert( 'over' === ( $r['salt'] ?? '' ), '„zu viel“ steht weiterhin' );

	$r = Mixmymeals_Meal_Plan::rate_day( array( 'calories' => 1700 ) + $day, true );
	mmm_tl_assert( 'under' === ( $r['calories'] ?? '' ), 'Energie 1 700 kcal (85 %) bei ganzen Tagen: zu wenig' );
	$r = Mixmymeals_Meal_Plan::rate_day( array( 'calories' => 2200 ) + $day, true );
	mmm_tl_assert( ! isset( $r['calories'] ), 'Energie 2 200 kcal (110 %): genau an der Grenze, keine Bewertung' );

	$r = Mixmymeals_Meal_Plan::rate_day( $day, true, array( 'fiber' ) );
	mmm_tl_assert( ! isset( $r['fiber'] ), 'fehlen Daten für Ballaststoffe, steht kein „zu wenig“' );

	update_option( Mixmymeals_Meal_Plan::DAY_RATING_OPTION_PREFIX . 'salt', Mixmymeals_Meal_Plan::sanitize_day_rating( array( 'over' => '120' ) ) );
	mmm_tl_assert( ! isset( Mixmymeals_Meal_Plan::rate_day( $day, true )['salt'] ), 'Grenze 120 %: 117 % sind nicht zu viel' );
	update_option( Mixmymeals_Meal_Plan::DAY_RATING_OPTION_PREFIX . 'salt', Mixmymeals_Meal_Plan::sanitize_day_rating( array() ) );
	mmm_tl_assert( ! isset( Mixmymeals_Meal_Plan::day_rating_thresholds()['salt'] ), 'leer gespeichert schaltet die Bewertung ab' );
	delete_option( Mixmymeals_Meal_Plan::DAY_RATING_OPTION_PREFIX . 'salt' );
	mmm_tl_assert( array( 'over' ) === Mixmymeals_Meal_Plan::day_rating_ends( 'salt' ) && array( 'under' ) === Mixmymeals_Meal_Plan::day_rating_ends( 'fiber' ), 'die Richtung kommt aus nutrition_direction()' );

	// Ein Plan, ein Tag: nur Abendessen, dann der ganze Tag.
	$plan3 = mmm_tl_post( array( 'post_type' => 'mmm_meal_plan', 'post_title' => 'ZZ Plan Tag' ) );
	update_post_meta( $plan3, 'start_day', 1 );
	update_post_meta( $plan3, 'end_day', 2 );
	$add_meal = static function ( int $plan_id, int $recipe_id, string $type, int $day ) {
		$id = mmm_tl_post( array( 'post_type' => 'mmm_meal', 'post_title' => 'ZZ ' . $type, 'post_parent' => $plan_id ) );
		$m  = new Mixmymeals_Meal( $id );
		$m->set_recipe_id( $recipe_id );
		$m->set_servings( 2 );
		$m->set_type( $type );
		$m->set_day( $day );
		return $id;
	};
	$add_meal( $plan3, $stew, 'dinner', 1 );
	Mixmymeals_Meal_Plan::flush_meals_cache( $plan3 );
	$p3 = new Mixmymeals_Meal_Plan( $plan3 );
	mmm_tl_assert( ! $p3->covers_whole_days(), 'nur Abendessen: keine ganzen Tage' );
	$rating = $p3->calc_day_rating();
	mmm_tl_assert( ! in_array( 'under', $rating['ratings'], true ), 'also nirgends „zu wenig“' );

	$add_meal( $plan3, $stew, 'breakfast', 1 );
	$add_meal( $plan3, $stew, 'lunch', 1 );
	Mixmymeals_Meal_Plan::flush_meals_cache( $plan3 );
	$p3 = new Mixmymeals_Meal_Plan( $plan3 );
	mmm_tl_assert( $p3->covers_whole_days(), 'Frühstück, Mittag- und Abendessen: ganzer Tag' );
	$rating = $p3->calc_day_rating();
	mmm_tl_assert( 'under' === ( $rating['ratings']['protein'] ?? '' ), 'dreimal Eintopf: zu wenig Eiweiß' );
	mmm_tl_assert( 'over' === ( $rating['ratings']['salt'] ?? '' ), 'und dreimal 4,5 g Salz: zu viel' );

	$add_meal( $plan3, $stew, 'dinner', 2 );
	Mixmymeals_Meal_Plan::flush_meals_cache( $plan3 );
	mmm_tl_assert( ! ( new Mixmymeals_Meal_Plan( $plan3 ) )->covers_whole_days(), 'ein zweiter Tag mit nur Abendessen: keine ganzen Tage mehr' );

	$per_day = ( new Mixmymeals_Meal_Plan( $plan3 ) )->calc_day_ratings();
	mmm_tl_assert( array( 1, 2 ) === array_keys( $per_day ), 'Bewertung pro Tag: genau die Tage mit Mahlzeiten' );
	mmm_tl_assert( $per_day[1]['whole_day'] && 'under' === ( $per_day[1]['ratings']['protein'] ?? '' ), 'Tag 1 ist ganz: dort „zu wenig“ Eiweiß' );
	mmm_tl_assert( ! $per_day[2]['whole_day'] && ! in_array( 'under', $per_day[2]['ratings'], true ), 'Tag 2 nur Abendessen: kein „zu wenig“' );
	mmm_tl_assert( 'over' === ( $per_day[1]['ratings']['salt'] ?? '' ), 'Tag 1: dreimal 4,5 g Salz sind zu viel' );

	$note = Mixmymeals_Meal_Plan::day_rating_note();
	mmm_tl_assert( false !== strpos( $note, '100 %' ) && false !== strpos( $note, '110 %' ), 'die Fußnote nennt die Grenzen' );
	mmm_tl_assert( false === strpos( $note, Mixmymeals_Ingredient::nutrition_label( 'saturates' ) ), 'aber nur die Ausnahmen beim Namen, nicht jeden Wert' );

	$table = do_shortcode( '[mmm_nutrition_overview_for_a_single_meal_plan meal_plan_id="' . $plan3 . '"]' );
	mmm_tl_assert( false !== strpos( $table, 'mmm-day-rating--over' ), 'die Plantabelle zeigt „zu viel“ in der Spalte der Referenzmenge' );
	mmm_tl_assert( false !== strpos( $table, 'mmm-nutri-note--rating' ), 'mit der Fußnote zur Bewertung' );
	mmm_tl_assert( false === strpos( $table, 'mmm-day-rating--under' ), 'aber ohne ganze Tage kein „zu wenig“' );

	echo "\n=== 8. Planer: AJAX gleich der Plantabelle ===\n";

	$_COOKIE[ Mixmymeals_Cart::COOKIE_NAME ] = 'zz-traffic-light-test';
	$cart_items = array(
		array( 'recipe_id' => $stew, 'servings' => 2, 'type' => 'breakfast', 'day' => 1 ),
		array( 'recipe_id' => $dressed, 'servings' => 3, 'type' => 'lunch', 'day' => 1 ),
		array( 'recipe_id' => $stew, 'servings' => 1, 'type' => 'dinner', 'day' => 1 ),
		array( 'recipe_id' => $stew, 'servings' => 2, 'type' => 'dinner', 'day' => 3 ),
		array( 'recipe_id' => $stew, 'servings' => 2, 'type' => '', 'day' => null ),
	);
	( new Mixmymeals_Cart() )->save_items( $cart_items );

	// check_ajax_referer() liest $_REQUEST, nicht $_POST.
	$_POST    = array( 'nonce' => wp_create_nonce( 'mmm_frontend_nonce' ) );
	$_REQUEST = $_POST;
	$die   = static function () {
		return static function () {
			throw new RuntimeException( 'mmm_tl_die' );
		};
	};
	add_filter( 'wp_doing_ajax', '__return_true' );
	add_filter( 'wp_die_ajax_handler', $die );
	ob_start();
	try {
		do_action( 'wp_ajax_nopriv_mmm_planner_nutrition' );
	} catch ( RuntimeException $e ) {
		unset( $e );
	}
	$json = json_decode( (string) ob_get_clean(), true );
	remove_filter( 'wp_die_ajax_handler', $die );
	remove_filter( 'wp_doing_ajax', '__return_true' );
	$_POST    = array();
	$_REQUEST = array();

	mmm_tl_assert( ! empty( $json['success'] ), 'der Endpunkt antwortet' );

	// Derselbe Plan, gespeichert.
	$plan4 = mmm_tl_post( array( 'post_type' => 'mmm_meal_plan', 'post_title' => 'ZZ Plan aus Planer' ) );
	update_post_meta( $plan4, 'start_day', 1 );
	update_post_meta( $plan4, 'end_day', 3 );
	foreach ( $cart_items as $item ) {
		$id = mmm_tl_post( array( 'post_type' => 'mmm_meal', 'post_title' => 'ZZ P', 'post_parent' => $plan4 ) );
		$m  = new Mixmymeals_Meal( $id );
		$m->set_recipe_id( $item['recipe_id'] );
		$m->set_servings( $item['servings'] );
		$m->set_type( $item['type'] );
		if ( $item['day'] ) {
			$m->set_day( $item['day'] );
		}
	}
	Mixmymeals_Meal_Plan::flush_meals_cache( $plan4 );
	$saved = do_shortcode( '[mmm_nutrition_overview_for_a_single_meal_plan meal_plan_id="' . $plan4 . '"]' );

	mmm_tl_assert( '' !== (string) ( $json['data']['table'] ?? '' ) && false !== strpos( $saved, $json['data']['table'] ), 'die Tabelle des Planers ist Zeichen für Zeichen die des gespeicherten Plans' );
	mmm_tl_assert( false === strpos( (string) ( $json['data']['html'] ?? '' ), 'mmm-nutri-stats' ), 'aufgeklappt nur die Tabelle, ohne die Kacheln der Planseite' );
	mmm_tl_assert( false !== strpos( (string) ( $json['data']['html'] ?? '' ), 'Ø ' ), 'die Kacheln tragen das Durchschnittszeichen' );

	$days = (array) ( $json['data']['days'] ?? array() );
	mmm_tl_assert( false !== strpos( (string) ( $days[1] ?? '' ), 'mmm-plan-day-nutrition__list' ), 'Tag 1 bekommt seine eigene Nährwertzeile' );
	mmm_tl_assert( '' === (string) ( $days[2] ?? 'x' ), 'Tag 2 ohne Mahlzeiten: keine Zeile' );
	mmm_tl_assert( false !== strpos( (string) ( $days[1] ?? '' ), 'mmm-day-rating--over' ), 'Tag 1 (zweimal Eintopf): zu viel Salz an diesem Tag' );
	mmm_tl_assert( false === strpos( (string) ( $days[3] ?? '' ), 'mmm-day-rating--under' ), 'Tag 3 hat nur Abendessen: kein „zu wenig“' );

	$builder = do_shortcode( '[mmm_meal_plan_builder]' );
	mmm_tl_assert( 1 === preg_match( '/data-day-nutrition="1">\s*<ul class="mmm-plan-day-nutrition__list"/', $builder ), 'der Planer rendert die Tageszeile schon beim ersten Laden' );
	mmm_tl_assert( false !== strpos( $builder, 'data-day-nutrition="2" hidden' ), 'ein leerer Tag bleibt verborgen' );
	mmm_tl_assert( false !== strpos( (string) ( $json['data']['html'] ?? '' ), 'mmm-planner-nutrition__summary' ), 'dazu die kompakte Tagesübersicht' );
	mmm_tl_assert( false !== strpos( (string) ( $json['data']['html'] ?? '' ), 'mmm-day-rating--over' ), 'mit Bewertung' );
	mmm_tl_assert( empty( $json['data']['empty'] ), 'und nicht leer' );

	$virtual = Mixmymeals_Meal_Plan::from_meal_rows( $cart_items );
	$real    = new Mixmymeals_Meal_Plan( $plan4 );
	mmm_tl_assert( $virtual->calc_nutrition_per_day_person() == $real->calc_nutrition_per_day_person(), 'Tageswerte: identisch' );
	mmm_tl_assert( $virtual->covers_whole_days() === $real->covers_whole_days() && ! $real->covers_whole_days(), 'ganze Tage: identisch (Tag 3 hat nur Abendessen)' );

	// Balken wie im PDF: bei Höchstmengen der Rest bis zur Grenze.
	mmm_tl_assert( array( 'fill' => 31, 'over' => false, 'room' => 31 ) === Mixmymeals_Meal_Plan::nutrition_bar( 'salt', 69.0 ), 'Balken Salz 69 %: 31 % frei' );
	mmm_tl_assert( array( 'fill' => 100, 'over' => true, 'room' => null ) === Mixmymeals_Meal_Plan::nutrition_bar( 'salt', 130.0 ), 'Balken Salz 130 %: voll, über der Grenze' );
	mmm_tl_assert( array( 'fill' => 60, 'over' => false, 'room' => null ) === Mixmymeals_Meal_Plan::nutrition_bar( 'protein', 60.0 ), 'Balken Eiweiß 60 %: der Anteil selbst' );
	mmm_tl_assert( 100 === Mixmymeals_Meal_Plan::nutrition_bar( 'calories', 120.0 )['fill'], 'Balken Energie 120 %: gekappt bei 100' );
	mmm_tl_assert( false !== strpos( (string) ( $json['data']['html'] ?? '' ), 'mmm-nutri-bar__fill--over' ), 'Planer: Kachel mit vollem roten Balken' );
	mmm_tl_assert( false !== strpos( (string) ( $json['data']['html'] ?? '' ), 'mmm-nutri-note--bars' ), 'Planer: Hinweis zu den Balken' );

	$plan_page = do_shortcode( '[mmm_nutrition_overview_for_a_single_meal_plan meal_plan_id="' . $plan4 . '"]' );
	mmm_tl_assert( false !== strpos( $plan_page, 'mmm-planner-nutrition__summary' ) && false !== strpos( $plan_page, 'mmm-nutri-bar__fill' ), 'Planseite: dieselben Kacheln mit Balken' );
	mmm_tl_assert( false === strpos( $plan_page, 'mmm-nutri-stats' ), 'Planseite: die vier alten Kacheln sind weg' );

	( new Mixmymeals_Cart() )->save_items( array() );
	// check_ajax_referer() liest $_REQUEST, nicht $_POST.
	$_POST    = array( 'nonce' => wp_create_nonce( 'mmm_frontend_nonce' ) );
	$_REQUEST = $_POST;
	add_filter( 'wp_doing_ajax', '__return_true' );
	add_filter( 'wp_die_ajax_handler', $die );
	ob_start();
	try {
		do_action( 'wp_ajax_nopriv_mmm_planner_nutrition' );
	} catch ( RuntimeException $e ) {
		unset( $e );
	}
	$json = json_decode( (string) ob_get_clean(), true );
	remove_filter( 'wp_die_ajax_handler', $die );
	remove_filter( 'wp_doing_ajax', '__return_true' );
	$_POST    = array();
	$_REQUEST = array();
	mmm_tl_assert( ! empty( $json['data']['empty'] ) && '' === $json['data']['html'], 'leerer Planer: leere Antwort' );

	$_POST    = array( 'nonce' => 'falsch' );
	$_REQUEST = $_POST;
	add_filter( 'wp_doing_ajax', '__return_true' );
	add_filter( 'wp_die_ajax_handler', $die );
	ob_start();
	$refused = false;
	try {
		do_action( 'wp_ajax_nopriv_mmm_planner_nutrition' );
	} catch ( RuntimeException $e ) {
		$refused = '' === trim( (string) ob_get_contents() ) || false !== strpos( (string) ob_get_contents(), '-1' );
	}
	ob_end_clean();
	remove_filter( 'wp_die_ajax_handler', $die );
	remove_filter( 'wp_doing_ajax', '__return_true' );
	$_POST    = array();
	$_REQUEST = array();
	mmm_tl_assert( $refused, 'ohne gültige Nonce keine Antwort' );

	delete_transient( Mixmymeals_Cart::TRANSIENT_PREFIX . 'zz-traffic-light-test' );

} finally {

	foreach ( array_reverse( $GLOBALS['MMM_TL_TRASH'] ) as $trash_id ) {
		wp_delete_post( $trash_id, true );
	}

	foreach ( $backup as $option => $value ) {
		if ( false === $value ) {
			delete_option( $option );
		} else {
			update_option( $option, $value );
		}
	}
}

printf( "\n%d ok, %d fehlgeschlagen\n", $GLOBALS['MMM_TL_PASS'], $GLOBALS['MMM_TL_FAIL'] );
