<?php
/**
 * Diets, pinned products and the per-request caches, end to end.
 *
 * Run against a real install, because most of what it checks is the wiring
 * between terms, meta, products and the caches — none of which a unit test
 * without WordPress would exercise:
 *
 *   cd <site>/app/public
 *   wp --exec="define('DB_HOST','localhost:<Local mysqld.sock>');" \
 *      eval-file wp-content/plugins/mixmymeals/tools/diet-test.php
 *
 * It creates its own posts and deletes them again at the end; everything it
 * makes is titled "T …" or "R<n>" and carries the brand ZZ, so a run cut short
 * leaves something recognisable behind. Not distributed (.distignore: /tools).
 *
 * @package Mixmymeals
 */

/*
 * Counters and the cleanup list live in $GLOBALS, not at file scope: under
 * `wp eval-file` this file runs inside a function, so a `global $PASS` in a
 * helper binds to a *different* variable than a bare $PASS out here — which is
 * why the first run reported "0 bestanden, 0 fehlgeschlagen" and deleted 6 of
 * its 30 test posts.
 */
$GLOBALS['PASS'] = 0; $GLOBALS['FAIL'] = 0; $GLOBALS['TRASH'] = array();

function trash( $id ) { $GLOBALS['TRASH'][] = $id; return $id; }

function t( $label, $expected, $actual ) {
	$e = is_array( $expected ) ? implode( ',', $expected ) : (string) $expected;
	$a = is_array( $actual ) ? implode( ',', $actual ) : (string) $actual;
	if ( $e === $a ) { $GLOBALS['PASS']++; printf( "  ok   %-52s %s\n", $label, $a === '' ? '(leer)' : $a ); }
	else { $GLOBALS['FAIL']++; printf( "  FAIL %-52s erwartet [%s], bekam [%s]\n", $label, $e, $a ); }
}
function sorted( array $a ): array { sort( $a ); return $a; }

function mk_ing( $title, $diets = array(), $legacy = null ) {
	$id = wp_insert_post( array( 'post_type' => 'mmm_ingredient', 'post_title' => $title, 'post_status' => 'publish' ) );
	trash( $id );
	if ( $diets ) { ( new Mixmymeals_Ingredient( $id ) )->set_diets( $diets ); }
	if ( null !== $legacy ) { update_post_meta( $id, '_mmm_diet_type', $legacy ); }
	return $id;
}
function mk_prod( $parent, $title, $diets = array() ) {
	$id = wp_insert_post( array( 'post_type' => 'mmm_product', 'post_title' => $title, 'post_status' => 'publish', 'post_parent' => $parent ) );
	trash( $id );
	$p = new Mixmymeals_Product( $id ); $p->set_brand( 'ZZ' );
	if ( $diets ) { $p->set_diets( $diets ); }
	return $id;
}
function mk_recipe( $title, array $rows ) {
	$id = wp_insert_post( array( 'post_type' => 'mmm_recipe', 'post_title' => $title, 'post_status' => 'publish' ) );
	trash( $id );
	update_post_meta( $id, '_mmm_servings', 2 );
	( new Mixmymeals_Recipe( $id ) )->set_steps( array( array( 'title' => 'S', 'description' => 'x', 'ingredients' => $rows ) ) );
	return $id;
}
function row( $id, $opts = array() ) {
	return array_merge( array( 'id' => $id, 'amount' => 100, 'amount_id' => 0 ), $opts );
}

echo "\n=== 1. Algebra (ohne Datenbank) ===\n";
t( 'expand: vegan impliziert vegetarian', 'vegan,vegetarian', Mixmymeals_Diet::expand( array( 'vegan' ) ) );
t( 'expand: unbekannt faellt weg', 'vegan,vegetarian', Mixmymeals_Diet::expand( array( 'vegan', 'paleo' ) ) );
t( 'combine: alle vegan', 'vegan,vegetarian', Mixmymeals_Diet::combine( array( array('vegan'), array('vegan') ) ) );
t( 'combine: vegan + vegetarisch', 'vegetarian', Mixmymeals_Diet::combine( array( array('vegan'), array('vegetarian') ) ) );
t( 'combine: eine Zutat ohne Aussage', '', Mixmymeals_Diet::combine( array( array('vegan'), array() ) ) );
t( 'combine: leere Liste', '', Mixmymeals_Diet::combine( array() ) );
t( 'combine: Achsen schneiden korrekt', 'vegan,vegetarian',
   Mixmymeals_Diet::combine( array( array('vegan','gluten_free'), array('vegan') ) ) );
t( 'combine: beide glutenfrei', 'vegan,vegetarian,gluten_free',
   Mixmymeals_Diet::combine( array( array('vegan','gluten_free'), array('vegan','gluten_free') ) ) );
t( 'strongest diet-Achse', 'vegan', Mixmymeals_Diet::strongest( array('vegetarian','vegan'), 'diet' ) );
t( 'strongest andere Achse', 'gluten_free', Mixmymeals_Diet::strongest( array('vegan','gluten_free'), 'free_from' ) );
t( 'headline: eine je Achse', 'vegan,gluten_free', Mixmymeals_Diet::headline( array('vegan','vegetarian','gluten_free') ) );
t( 'apply_product: ersetzt nur die diet-Achse', 'gluten_free,vegan',
   Mixmymeals_Diet::apply_product( array('vegetarian','gluten_free'), array('vegan') ) );
t( 'apply_product: leeres Produkt aendert nichts', 'vegetarian,gluten_free',
   Mixmymeals_Diet::apply_product( array('vegetarian','gluten_free'), array() ) );

echo "\n=== 2. Zutat ===\n";
$vegan_ing = mk_ing( 'T Hafer', array( 'vegan' ) );
$multi     = mk_ing( 'T Reis', array( 'vegan', 'gluten_free' ) );
$leer      = mk_ing( 'T Unbekannt' );
$legacy    = mk_ing( 'T Alt', array(), 'vegetarian' );
t( 'eigene Angabe', 'vegan', ( new Mixmymeals_Ingredient( $vegan_ing ) )->get_diet_type() );
t( 'mehrwertig', 'gluten_free,vegan', sorted( ( new Mixmymeals_Ingredient( $multi ) )->get_diets() ) );
t( 'ohne Angabe', '', ( new Mixmymeals_Ingredient( $leer ) )->get_diets() );
t( 'Legacy-Meta ohne Terme', 'vegetarian', ( new Mixmymeals_Ingredient( $legacy ) )->get_diets() );
t( 'unbekannter Slug wird verworfen', 'vegan', (function() {
	$i = mk_ing( 'T Filter' ); ( new Mixmymeals_Ingredient( $i ) )->set_diets( array( 'vegan', 'paleo' ) );
	return ( new Mixmymeals_Ingredient( $i ) )->get_diets();
})() );

echo "\n=== 3. Produkte an der Zutat ===\n";
$soja = mk_ing( 'T Joghurt', array( 'vegetarian' ) );
$p_v  = mk_prod( $soja, 'Soja', array( 'vegan' ) );
$p_m  = mk_prod( $soja, 'Kuh', array( 'vegetarian' ) );
$p_s  = mk_prod( $soja, 'Stumm' );
t( 'ohne Referenz: eigene Angabe', 'vegetarian', ( new Mixmymeals_Ingredient( $soja ) )->get_diets() );
t( 'Eignung: bestes Produkt zaehlt', 'vegan', ( new Mixmymeals_Ingredient( $soja ) )->get_diet_suitability() );
Mixmymeals_Product::set_reference_id( $soja, $p_v );
t( 'Referenz vegan ueberschreibt', 'vegan', ( new Mixmymeals_Ingredient( $soja ) )->get_diet_type() );
Mixmymeals_Product::set_reference_id( $soja, $p_s );
t( 'stummes Referenzprodukt laesst Zutat stehen', 'vegetarian', ( new Mixmymeals_Ingredient( $soja ) )->get_diet_type() );
$gf = mk_ing( 'T Mehl', array( 'vegetarian', 'gluten_free' ) );
$gp = mk_prod( $gf, 'Veganes Mehl', array( 'vegan' ) );
Mixmymeals_Product::set_reference_id( $gf, $gp );
t( 'Referenz ersetzt nur ihre Achse', 'gluten_free,vegan', sorted( ( new Mixmymeals_Ingredient( $gf ) )->get_diets() ) );

echo "\n=== 4. Rezept: Schnittmenge und Optionalitaet ===\n";
$milch = mk_ing( 'T Milch', array( 'vegetarian' ) );
t( 'alle vegan',                 'vegan', ( new Mixmymeals_Recipe( mk_recipe( 'R1', array( row($vegan_ing), row($multi) ) ) ) )->get_diet_type() );
$r_pflicht = mk_recipe( 'R2', array( row($vegan_ing), row($milch) ) );
t( 'Milch Pflicht: in force',    'vegetarian', ( new Mixmymeals_Recipe( $r_pflicht ) )->get_diet_type() );
t( 'Milch Pflicht: erreichbar',  'vegetarian', ( new Mixmymeals_Recipe( $r_pflicht ) )->get_diet_suitability() );
$r_opt = mk_recipe( 'R3', array( row($vegan_ing), row($milch, array('optional'=>1)) ) );
t( 'Milch optional: in force',   'vegetarian', ( new Mixmymeals_Recipe( $r_opt ) )->get_diet_type() );
t( 'Milch optional: erreichbar', 'vegan',      ( new Mixmymeals_Recipe( $r_opt ) )->get_diet_suitability() );
$r_opt_unk = mk_recipe( 'R4', array( row($vegan_ing), row($leer, array('optional'=>1)) ) );
t( 'unbekannt optional: erreichbar', 'vegan', ( new Mixmymeals_Recipe( $r_opt_unk ) )->get_diet_suitability() );
$r_unk = mk_recipe( 'R5', array( row($vegan_ing), row($leer) ) );
t( 'unbekannt Pflicht blockiert', '', ( new Mixmymeals_Recipe( $r_unk ) )->get_diet_suitability() );
$r_all_opt = mk_recipe( 'R6', array( row($vegan_ing, array('optional'=>1)) ) );
t( 'nur Optionale: Rueckfall auf alle', 'vegan', ( new Mixmymeals_Recipe( $r_all_opt ) )->get_diet_suitability() );
t( 'Rezept ohne Zutaten', '', ( new Mixmymeals_Recipe( mk_recipe( 'R7', array() ) ) )->get_diet_suitability() );

echo "\n=== 5. Im Rezept festgelegtes Produkt ===\n";
$r_pin = mk_recipe( 'R8', array( row($vegan_ing), row($soja, array('product_id'=>$p_v)) ) );
t( 'Pin vegan macht das Rezept vegan', 'vegan', ( new Mixmymeals_Recipe( $r_pin ) )->get_diet_type() );
$r_pin_m = mk_recipe( 'R9', array( row($vegan_ing), row($soja, array('product_id'=>$p_m)) ) );
t( 'Pin Kuhmilch: in force vegetarisch', 'vegetarian', ( new Mixmymeals_Recipe( $r_pin_m ) )->get_diet_type() );
t( 'Pin Kuhmilch: vegan bleibt erreichbar', 'vegan', ( new Mixmymeals_Recipe( $r_pin_m ) )->get_diet_suitability() );
$r_bad = mk_recipe( 'R10', array( row($vegan_ing, array('product_id'=>$p_v)) ) );
$st = ( new Mixmymeals_Recipe( $r_bad ) )->get_steps();
t( 'fremdes Produkt wird verworfen', '0', (string) $st[0]['ingredients'][0]['product_id'] );

echo "\n=== 6. Pillen ===\n";
$chips = function( $id ) { return wp_strip_all_tags( Mixmymeals_Shortcodes::diet_chips( $id ) ); };
t( 'sicher vegan -> "Vegan"',        'Vegan',          $chips( $r_pin ) );
t( 'nur moeglich -> "Vegan tauglich"','Vegan tauglich', $chips( $r_opt ) );
t( 'nichts -> keine Pille',          '',               $chips( $r_unk ) );
$html = Mixmymeals_Shortcodes::diet_chips( $r_pin );
t( 'feste Pille traegt --diet-definite', '1', (string) (int) str_contains( $html, 'mmm-chip--diet-definite' ) );
t( 'mogliche Pille ohne --definite',     '0', (string) (int) str_contains( Mixmymeals_Shortcodes::diet_chips( $r_opt ), '--diet-definite' ) );
t( 'Basisklasse wird uebernommen', '1', (string) (int) str_contains( Mixmymeals_Shortcodes::diet_chips( $r_pin, 'mmm-meta__item' ), 'mmm-meta__item--diet' ) );
t( 'Pille auf einem Nicht-Rezept', '', Mixmymeals_Shortcodes::diet_chips( $vegan_ing ) );

echo "\n=== 7. Mahlzeit und Plan ===\n";
$plan = wp_insert_post( array( 'post_type' => 'mmm_meal_plan', 'post_title' => 'T Plan', 'post_status' => 'publish' ) );
trash( $plan );
update_post_meta( $plan, 'start_day', 1 ); update_post_meta( $plan, 'end_day', 2 );
$mk_meal = function( $plan, $recipe, $day )  {
	$m = wp_insert_post( array( 'post_type' => 'mmm_meal', 'post_title' => 'T M', 'post_status' => 'publish', 'post_parent' => $plan ) );
	trash( $m );
	update_post_meta( $m, 'recipe_id', $recipe ); update_post_meta( $m, 'servings', 2 );
	update_post_meta( $m, 'mmm_meal_type', 'dinner' ); update_post_meta( $m, '_mmm_day', $day );
	return $m;
};
$meal_pin = $mk_meal( $plan, $r_pin, 1 );
$mk_meal( $plan, $r_opt, 2 );
t( 'Mahlzeit loest aufs Rezept auf', 'Vegan', wp_strip_all_tags( Mixmymeals_Shortcodes::diet_chips( $meal_pin ) ) );
t( 'Plan in force: schwaechstes Gericht', 'vegetarian', ( new Mixmymeals_Meal_Plan( $plan ) )->get_diet_type() );
t( 'Plan erreichbar: beide vegan-faehig',  'vegan',      ( new Mixmymeals_Meal_Plan( $plan ) )->get_diet_suitability() );
$plan2 = wp_insert_post( array( 'post_type' => 'mmm_meal_plan', 'post_title' => 'T Plan 2', 'post_status' => 'publish' ) );
trash( $plan2 );
update_post_meta( $plan2, 'start_day', 1 ); update_post_meta( $plan2, 'end_day', 1 );
$mk_meal( $plan2, $r_unk, 1 );
t( 'Plan mit unklarem Gericht', '', ( new Mixmymeals_Meal_Plan( $plan2 ) )->get_diet_suitability() );
t( 'Plan ohne Mahlzeiten', '', ( new Mixmymeals_Meal_Plan( trash( wp_insert_post( array( 'post_type'=>'mmm_meal_plan','post_title'=>'T Leer','post_status'=>'publish' ) ) ) ) )->get_diet_suitability() );

echo "\n=== 8. Caches: liefern sie nach einem Schreibvorgang frische Werte? ===\n";
// Zutat aendern, danach im selben Request erneut lesen
$fresh = mk_ing( 'T Frisch', array( 'vegetarian' ) );
$r_fresh = mk_recipe( 'R11', array( row($fresh) ) );
t( 'vor der Aenderung', 'vegetarian', ( new Mixmymeals_Recipe( $r_fresh ) )->get_diet_type() );
( new Mixmymeals_Ingredient( $fresh ) )->set_diets( array( 'vegan' ) );
t( 'nach set_diets() im selben Request', 'vegan', ( new Mixmymeals_Recipe( $r_fresh ) )->get_diet_type() );

// Neues Produkt anlegen, danach Eignung erneut lesen
$prod_new = mk_prod( $fresh, 'Neu vegan', array( 'vegan' ) );
t( 'neues Produkt taucht in for_ingredient auf', '1',
   (string) count( array_filter( Mixmymeals_Product::for_ingredient( $fresh ), fn( $p ) => $p->get_id() === $prod_new ) ) );

// Rezept umschreiben, danach Naehrwerte erneut lesen
$nutri_ing = mk_ing( 'T Naehrstoff' );
( new Mixmymeals_Ingredient( $nutri_ing ) )->set_nutrition( array( 'calories_100' => 100, 'fat'=>1,'carbs'=>1,'protein'=>1,'sugar'=>1,'fiber'=>1,'salt'=>0.1 ) );
$r_nutri = mk_recipe( 'R12', array( row($nutri_ing) ) );
$before = ( new Mixmymeals_Recipe( $r_nutri ) )->calculate_nutrition_per_serving();
t( 'Naehrwerte vorher (100 g bei 2 Portionen)', '50', (string) (int) round( $before['calories'] ) );
( new Mixmymeals_Recipe( $r_nutri ) )->set_steps( array( array( 'title'=>'S','description'=>'x','ingredients'=>array( row($nutri_ing, array('amount'=>400)) ) ) ) );
$after = ( new Mixmymeals_Recipe( $r_nutri ) )->calculate_nutrition_per_serving();
t( 'Naehrwerte nach set_steps() im selben Request', '200', (string) (int) round( $after['calories'] ) );

// Mahlzeit ergaenzen, danach Plan erneut lesen
$cnt_before = ( new Mixmymeals_Meal_Plan( $plan2 ) )->count_meals();
$mk_meal( $plan2, $r_fresh, 1 );
t( 'Plan sieht die neue Mahlzeit', (string) ( $cnt_before + 1 ), (string) ( new Mixmymeals_Meal_Plan( $plan2 ) )->count_meals() );

echo "\n=== 9. Gesetztes Produkt wirkt sich aus ===\n";

// Zutat mit eigenen Werten, zwei Produkte mit abweichenden.
$joghurt = mk_ing( 'T Joghurt gross', array( 'vegetarian' ) );
$j_ing   = new Mixmymeals_Ingredient( $joghurt );
$j_ing->set_nutrition( array( 'calories_100' => 100, 'fat' => 3, 'carbs' => 5, 'protein' => 4, 'sugar' => 5, 'fiber' => 0, 'salt' => 0.1 ) );
$j_ing->set_price_per_100g( 1.00 );

$mager = mk_prod( $joghurt, 'Mager', array( 'vegetarian' ) );
$sahne = mk_prod( $joghurt, 'Sahne', array( 'vegetarian' ) );
( new Mixmymeals_Product( $mager ) )->set_nutrition( array( 'calories_100' => 60, 'fat' => 0.2 ) );
$sahne_obj = new Mixmymeals_Product( $sahne );
$sahne_obj->set_nutrition( array( 'calories_100' => 200, 'fat' => 15 ) );
$sahne_obj->set_package_weight( 200 );
$sahne_obj->set_price( 4.00 );   // 2,00 je 100 g
Mixmymeals_Product::set_reference_id( $joghurt, $mager );

// 100 g bei 2 Portionen -> 50 g je Portion.
$r_ref  = mk_recipe( 'R13', array( row( $joghurt ) ) );
$r_pin2 = mk_recipe( 'R14', array( row( $joghurt, array( 'product_id' => $sahne ) ) ) );

t( 'ohne Pin: Referenzprodukt rechnet', '30',
   (string) (int) round( ( new Mixmymeals_Recipe( $r_ref ) )->calculate_nutrition_per_serving()['calories'] ) );
t( 'mit Pin: das Produkt rechnet', '100',
   (string) (int) round( ( new Mixmymeals_Recipe( $r_pin2 ) )->calculate_nutrition_per_serving()['calories'] ) );
t( 'Pin schlaegt Referenz beim Fett', '7.5',
   (string) round( ( new Mixmymeals_Recipe( $r_pin2 ) )->calculate_nutrition_per_serving()['fat'], 2 ) );
t( 'stumme Werte fallen auf die Zutat zurueck', '2',
   (string) round( ( new Mixmymeals_Recipe( $r_pin2 ) )->calculate_nutrition_per_serving()['protein'], 2 ) );

t( 'ohne Pin: Preis der Zutat', '0.5',
   (string) round( ( new Mixmymeals_Recipe( $r_ref ) )->calculate_cost_per_serving(), 2 ) );
t( 'mit Pin: Preis des Produkts', '1',
   (string) round( ( new Mixmymeals_Recipe( $r_pin2 ) )->calculate_cost_per_serving(), 2 ) );

// Ein Produkt einer anderen Zutat darf nichts bewirken.
t( 'fremdes Produkt aendert die Naehrwerte nicht', '30',
   (string) (int) round( ( new Mixmymeals_Recipe( mk_recipe( 'R15', array( row( $joghurt, array( 'product_id' => $p_v ) ) ) ) ) )->calculate_nutrition_per_serving()['calories'] ) );
t( 'effective_product_id: Pin gewinnt', (string) $sahne,
   (string) Mixmymeals_Ingredient::effective_product_id( $joghurt, $sahne ) );
t( 'effective_product_id: ohne Pin die Referenz', (string) $mager,
   (string) Mixmymeals_Ingredient::effective_product_id( $joghurt, 0 ) );
t( 'effective_product_id: fremdes Produkt zaehlt nicht', (string) $mager,
   (string) Mixmymeals_Ingredient::effective_product_id( $joghurt, $p_v ) );

// Einkaufsliste
$plan3 = trash( wp_insert_post( array( 'post_type' => 'mmm_meal_plan', 'post_title' => 'T Plan 3', 'post_status' => 'publish' ) ) );
update_post_meta( $plan3, 'start_day', 1 ); update_post_meta( $plan3, 'end_day', 2 );
$mk_meal( $plan3, $r_pin2, 1 );
$row_of = function ( $plan, $ing ) {
	foreach ( ( new Mixmymeals_Meal_Plan( $plan ) )->calc_shopping_list() as $items ) {
		if ( isset( $items[ $ing ] ) ) { return $items[ $ing ]; }
	}
	return array();
};
t( 'Einkaufsliste nennt das gesetzte Produkt', 'ZZ Sahne', $row_of( $plan3, $joghurt )['product'] ?? '' );

$mk_meal( $plan3, $r_ref, 2 );
t( 'widerspruechliche Wahl nennt keins', '', $row_of( $plan3, $joghurt )['product'] ?? '' );

$plan4 = trash( wp_insert_post( array( 'post_type' => 'mmm_meal_plan', 'post_title' => 'T Plan 4', 'post_status' => 'publish' ) ) );
update_post_meta( $plan4, 'start_day', 1 ); update_post_meta( $plan4, 'end_day', 1 );
$mk_meal( $plan4, $r_ref, 1 );
t( 'ohne Pin nennt sie die Referenz', 'ZZ Mager', $row_of( $plan4, $joghurt )['product'] ?? '' );

// Ausgabe: steht der Artikel wirklich auf der Seite?
$list_html = do_shortcode( '[mmm_recipe_ingredients post_id=' . $r_pin2 . ']' );
t( 'Zutatenliste nennt das gesetzte Produkt', '1',
   (string) (int) str_contains( $list_html, 'mmm-ingredient-product">ZZ Sahne' ) );
t( 'Zutatenliste ohne Pin nennt nichts', '0',
   (string) (int) str_contains( do_shortcode( '[mmm_recipe_ingredients post_id=' . $r_ref . ']' ), 'mmm-ingredient-product' ) );

$GLOBALS['post'] = get_post( $plan4 );
setup_postdata( $GLOBALS['post'] );
$shop_html = do_shortcode( '[mmm_shopping_list_for_a_single_meal_plan]' );
wp_reset_postdata();
t( 'Einkaufsliste rendert das Produkt', '1',
   (string) (int) str_contains( $shop_html, 'ZZ Mager' ) );

// Produktvergleich: offene Zutat ja, festgelegte nein.
t( 'festgelegte Zutat faellt aus dem Vergleich', '0',
   (string) count( ( new Mixmymeals_Recipe( $r_pin2 ) )->calc_product_impact() ) );

$impact = ( new Mixmymeals_Recipe( $r_ref ) )->calc_product_impact();
t( 'offene Zutat bleibt im Vergleich', '1', (string) count( $impact ) );
$group  = $impact[0] ?? array( 'products' => array() );
$anchor = array_values( array_filter( $group['products'], fn( $r ) => $r['is_anchor'] ) );
t( 'Vergleich ankert auf dem Referenzprodukt', (string) $mager, (string) ( $anchor[0]['id'] ?? 0 ) );
t( 'Ankerspalte traegt Absolutwerte', '30',
   (string) (int) round( (float) ( $anchor[0]['values']['calories'] ?? 0 ) ) );
$other = array_values( array_filter( $group['products'], fn( $r ) => ! $r['is_anchor'] ) );
t( 'Differenz zum anderen Produkt', '70',
   (string) (int) round( (float) ( $other[0]['deltas']['calories'] ?? 0 ) ) );

// Halb entschiedener Plan: bleibt drin, aber ohne Produktanker — die
// Differenzen gehen gegen die gewichtete Mischung beider Packungen.
$mixed   = ( new Mixmymeals_Meal_Plan( $plan3 ) )->calc_product_impact();
t( 'halb entschiedener Plan bleibt im Vergleich', '1', (string) count( $mixed ) );
$mgroup  = $mixed[0] ?? array( 'products' => array() );
$manchor = array_values( array_filter( $mgroup['products'], fn( $r ) => $r['is_anchor'] ) );
t( 'halb entschieden: Typische-Werte-Spalte statt Anker', '0', (string) ( $manchor[0]['id'] ?? 0 ) );

// Ganz entschiedener Plan: gar keine Gruppe, und der Shortcode bleibt leer.
$plan5 = trash( wp_insert_post( array( 'post_type' => 'mmm_meal_plan', 'post_title' => 'T Plan 5', 'post_status' => 'publish' ) ) );
update_post_meta( $plan5, 'start_day', 1 ); update_post_meta( $plan5, 'end_day', 1 );
$mk_meal( $plan5, $r_pin2, 1 );
t( 'ganz entschiedener Plan zeigt nichts', '0',
   (string) count( ( new Mixmymeals_Meal_Plan( $plan5 ) )->calc_product_impact() ) );
t( 'Shortcode liefert dann leer', '',
   trim( do_shortcode( '[mmm_recipe_product_impact post_id=' . $r_pin2 . ']' ) ) );

echo "\n=== 10. Wortlaut der Chips ===\n";

// Standard: was ohne jede Einstellung herauskommt.
t( 'Standard Portionen', '4 Portionen',
   Mixmymeals_Shortcodes::chip_text( 'servings', array( 'count' => '4', 'unit' => 'Portionen' ) ) );
t( 'Standard "tauglich"', 'Vegan tauglich',
   Mixmymeals_Shortcodes::chip_text( 'diet_capable', array( 'diet' => 'Vegan' ) ) );
t( 'Standard Haltbarkeit', '4 Tage im Kuehlschrank',
   Mixmymeals_Shortcodes::chip_text( 'keeping_days_storage', array( 'days' => '4', 'unit' => 'Tage', 'storage' => 'im Kuehlschrank' ) ) );
t( 'Chip ohne Platzhalter', 'Werbung', Mixmymeals_Shortcodes::chip_text( 'disclosure_commercial' ) );
t( 'unbekannter Schluessel', '', Mixmymeals_Shortcodes::chip_text( 'gibt_es_nicht', array() ) );

// Nicht gefuellte Platzhalter hinterlassen keine Klammern.
t( 'unbenutzter Platzhalter faellt weg', '4 Tage',
   Mixmymeals_Shortcodes::chip_text( 'keeping_days_storage', array( 'days' => '4', 'unit' => 'Tage' ) ) );

// Eigene Muster.
update_option( 'mixmymeals_chip_text_diet_capable', 'Geeignet fuer {diet}' );
update_option( 'mixmymeals_chip_text_servings', 'fuer {count} Personen' );
t( 'eigenes Muster wird benutzt', 'Geeignet fuer Vegan',
   Mixmymeals_Shortcodes::chip_text( 'diet_capable', array( 'diet' => 'Vegan' ) ) );
t( 'eigenes Muster ignoriert {unit}', 'fuer 4 Personen',
   Mixmymeals_Shortcodes::chip_text( 'servings', array( 'count' => '4', 'unit' => 'Portionen' ) ) );

// Und es kommt wirklich an der Pille an.
$r_wording = mk_recipe( 'R16', array( row( $vegan_ing ), row( $milch, array( 'optional' => 1 ) ) ) );
t( 'Pille nimmt das eigene Muster', 'Geeignet fuer Vegan',
   wp_strip_all_tags( Mixmymeals_Shortcodes::diet_chips( $r_wording ) ) );

// Abgelehnte Muster: gespeichert wird nichts, gerendert der Standard.
t( 'Pflicht-Platzhalter fehlt: nicht gespeichert', '',
   Mixmymeals_Shortcodes::sanitize_chip_text( 'servings', 'Portionen' ) );
t( 'erfundener Platzhalter: nicht gespeichert', '',
   Mixmymeals_Shortcodes::sanitize_chip_text( 'servings', '{count} {erfunden}' ) );
t( 'gueltiges Muster: gespeichert', 'fuer {count} Personen',
   Mixmymeals_Shortcodes::sanitize_chip_text( 'servings', 'fuer {count} Personen' ) );
update_option( 'mixmymeals_chip_text_servings', 'Portionen' );
t( 'unbrauchbarer Wert wird beim Rendern uebergangen', '4 Portionen',
   Mixmymeals_Shortcodes::chip_text( 'servings', array( 'count' => '4', 'unit' => 'Portionen' ) ) );

delete_option( 'mixmymeals_chip_text_diet_capable' );
delete_option( 'mixmymeals_chip_text_servings' );
t( 'nach dem Zuruecksetzen wieder Standard', 'Vegan tauglich',
   Mixmymeals_Shortcodes::chip_text( 'diet_capable', array( 'diet' => 'Vegan' ) ) );

// Einstellungsseite: je Text ein Feld, je Gruppe eine Ueberschrift.
$mmm      = Mixmymeals::instance( dirname( __DIR__ ) . '/mixmymeals.php', '0' );
$settings = $mmm->settings instanceof Mixmymeals_Settings ? $mmm->settings : Mixmymeals_Settings::instance( $mmm );
$settings->init_settings();
$tab       = $settings->settings['mixmymeals-chips'] ?? array( 'fields' => array() );
$field_ids = array_column( $tab['fields'], 'id' );
$inputs    = array_filter( $tab['fields'], fn( $f ) => empty( $f['type'] ) );
t( 'jeder Text hat ein Feld', (string) count( Mixmymeals_Shortcodes::chip_texts() ), (string) count( $inputs ) );
t( 'je Gruppe eine Ueberschrift', (string) count( Mixmymeals_Shortcodes::chip_text_groups() ),
   (string) count( array_filter( $tab['fields'], fn( $f ) => 'heading' === ( $f['type'] ?? '' ) ) ) );
t( 'Feld-Id folgt dem Schluessel', '1',
   (string) (int) in_array( 'chip_text_diet_capable', $field_ids, true ) );
$field = array_values( array_filter( $tab['fields'], fn( $f ) => ( $f['id'] ?? '' ) === 'chip_text_servings' ) )[0];
t( 'Feld zeigt den Standard als Platzhaltertext', '{count} {unit}', (string) $field['placeholder'] );
t( 'Feld sanitisiert ueber den Schluessel', '',
   (string) call_user_func( $field['callback'], 'Portionen' ) );
$markup = ( new Mixmymeals_Admin_API() )->display_field(
	array( 'field' => $field, 'prefix' => 'mixmymeals_' ),
	null,
	false
);
t( 'Admin-API rendert ein Eingabefeld', '1',
   (string) (int) str_contains( (string) $markup, 'name="mixmymeals_chip_text_servings"' ) );

echo "\n=== Ergebnis ===\n";
printf( "  %d bestanden, %d fehlgeschlagen\n", $GLOBALS['PASS'], $GLOBALS['FAIL'] );

foreach ( array_reverse( $GLOBALS['TRASH'] ) as $id ) { wp_delete_post( $id, true ); }
printf( "  aufgeraeumt: %d Posts\n", count( $GLOBALS['TRASH'] ) );
