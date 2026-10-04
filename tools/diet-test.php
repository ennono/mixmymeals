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

echo "\n=== 11. Zubereitungszeit: nicht gesetzt ist nicht null ===\n";

$r_time = mk_recipe( 'R17', array( row( $vegan_ing ) ) );
$time   = new Mixmymeals_Recipe( $r_time );

$time->set_preparation_time( 45 );
t( '45 Minuten gespeichert', '45', (string) get_post_meta( $r_time, '_mmm_preparation_time', true ) );
t( 'Getter liest 45', '45', (string) $time->get_preparation_time() );
t( 'has_preparation_time', '1', (string) (int) $time->has_preparation_time() );

$time->set_preparation_time( 0 );
t( '0 loescht die Zeile', '', (string) get_post_meta( $r_time, '_mmm_preparation_time', true ) );
t( 'Getter liefert trotzdem 0', '0', (string) $time->get_preparation_time() );
t( 'has_preparation_time ist falsch', '0', (string) (int) $time->has_preparation_time() );

$time->set_preparation_time( 30 );
$time->set_preparation_time( '' );
t( 'leerer String loescht ebenso', '', (string) get_post_meta( $r_time, '_mmm_preparation_time', true ) );

t( 'Stufen aufsteigend und positiv', '20,30,60', implode( ',', Mixmymeals_Recipe::prep_time_steps() ) );
add_filter( 'mixmymeals_prep_time_steps', fn() => array( 90, 0, 15, 15, '-5' ) );
t( 'Filter: sortiert, entdoppelt, ohne Unsinn', '15,90', implode( ',', Mixmymeals_Recipe::prep_time_steps() ) );
remove_all_filters( 'mixmymeals_prep_time_steps' );

// Der zweite Weg hinein: der Metabox-Callback, nicht der Setter. Er prueft
// Nonce und Rechte, also braucht der Lauf einen Benutzer, der Rezepte bearbeiten
// darf — unter wp-cli ist sonst niemand angemeldet und der Callback kehrt
// wortlos um, was als bestandener Test durchginge.
$admins = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
);
wp_set_current_user( (int) ( $admins[0] ?? 1 ) );
t( 'Testlauf darf Rezepte bearbeiten', '1', (string) (int) current_user_can( 'edit_post', $r_time ) );

$_POST = array(
	'mmm_recipe_details_nonce_field' => wp_create_nonce( 'mmm_recipe_details_nonce' ),
	'mmm_preparation_time'           => '25',
);
( new Mixmymeals_Post_Meta() )->save_recipe_details_meta_box( $r_time );
t( 'Metabox schreibt 25', '25', (string) get_post_meta( $r_time, '_mmm_preparation_time', true ) );
$_POST['mmm_preparation_time'] = '';
( new Mixmymeals_Post_Meta() )->save_recipe_details_meta_box( $r_time );
t( 'Metabox mit leerem Feld loescht', '', (string) get_post_meta( $r_time, '_mmm_preparation_time', true ) );
$_POST = array();
wp_set_current_user( 0 );

echo "\n=== 12. Index am Rezept ===\n";

$idx_of = function ( $recipe ) {
	$rows = get_post_meta( $recipe, '_mmm_diet_index', false );
	sort( $rows );
	return implode( ',', $rows );
};

$idx_ing  = mk_ing( 'T Idx Hafer', array( 'vegan' ) );
$idx_milk = mk_ing( 'T Idx Milch', array( 'vegetarian' ) );
$r_idx    = mk_recipe( 'R18', array( row( $idx_ing ) ) );
Mixmymeals_Recipe::flush_recipe_indexes();

t( 'Index traegt die erreichbaren Diaeten', 'vegan,vegetarian', $idx_of( $r_idx ) );
t( 'Zutatenindex traegt die Zutat', (string) $idx_ing,
   implode( ',', get_post_meta( $r_idx, '_mmm_ingredient_index', false ) ) );
t( 'Rueckwaertssuche findet das Rezept', '1',
   (string) (int) in_array( $r_idx, Mixmymeals_Recipe::ids_using_ingredient( $idx_ing ), true ) );

// Die Zutat aendert sich — das Ereignis passiert nicht am Rezept.
( new Mixmymeals_Ingredient( $idx_ing ) )->set_diets( array( 'vegetarian' ) );
Mixmymeals_Recipe::flush_recipe_indexes();
t( 'Zutat geaendert: Index folgt', 'vegetarian', $idx_of( $r_idx ) );
( new Mixmymeals_Ingredient( $idx_ing ) )->set_diets( array( 'vegan' ) );
Mixmymeals_Recipe::flush_recipe_indexes();

// Ein Produkt an der Zutat: zwei Hops entfernt.
$idx_prod = mk_prod( $idx_milk, 'Idx Hafermilch', array( 'vegan' ) );
$r_idx2   = mk_recipe( 'R19', array( row( $idx_ing ), row( $idx_milk ) ) );
Mixmymeals_Recipe::flush_recipe_indexes();
t( 'Produkt macht das Rezept vegan-faehig', 'vegan,vegetarian', $idx_of( $r_idx2 ) );

// Schritte aendern sich: die Zutat faellt raus.
( new Mixmymeals_Recipe( $r_idx ) )->set_steps( array( array( 'title'=>'S','description'=>'x','ingredients'=>array( row( $idx_milk ) ) ) ) );
Mixmymeals_Recipe::flush_recipe_indexes();
t( 'Zutat entfernt: nicht mehr in der Rueckwaertssuche', '0',
   (string) (int) in_array( $r_idx, Mixmymeals_Recipe::ids_using_ingredient( $idx_ing ), true ) );

// Rezept ohne Zutaten traegt gar keine Zeile, nicht eine leere.
$r_empty = mk_recipe( 'R20', array() );
Mixmymeals_Recipe::flush_recipe_indexes();
t( 'ohne Zutaten: keine Diaet-Zeile', '0', (string) count( get_post_meta( $r_empty, '_mmm_diet_index', false ) ) );
t( 'ohne Zutaten: keine Zutat-Zeile', '0', (string) count( get_post_meta( $r_empty, '_mmm_ingredient_index', false ) ) );

// Die Null aus der Core-Meta-Route wird beim naechsten Durchlauf weggeraeumt.
update_post_meta( $r_empty, '_mmm_preparation_time', 0 );
( new Mixmymeals_Recipe( $r_empty ) )->recount_indexes();
t( 'Index raeumt eine 0-Zeit weg', '', (string) get_post_meta( $r_empty, '_mmm_preparation_time', true ) );

// Idempotenz: der zweite Durchlauf aendert nichts mehr.
Mixmymeals_Recipe::recount_all_indexes();
t( 'zweiter Durchlauf meldet keine Aenderung', '0', (string) Mixmymeals_Recipe::recount_all_indexes() );

// DIE INVARIANTE: der Index behauptet nichts.
add_post_meta( $r_idx2, '_mmm_diet_index', 'gluten_free' );
t( 'erfundener Index erzeugt keine Pille', '0',
   (string) (int) str_contains( Mixmymeals_Shortcodes::diet_chips( $r_idx2 ), 'gluten_free' ) );
$schema = wp_json_encode( ( new Mixmymeals_Recipe( $r_idx2 ) )->get_schema() );
t( 'erfundener Index erreicht das Schema nicht', '0',
   (string) (int) str_contains( (string) $schema, 'GlutenFreeDiet' ) );
Mixmymeals_Recipe::flush_recipe_indexes();

echo "\n=== 13. Facetten: Pfade, Abfrage, Rangfolge ===\n";

$map = Mixmymeals_Recipe::archive_view_slugs();
t( 'Slugs sind eindeutig', (string) count( $map ), (string) count( array_unique( array_keys( $map ) ) ) );
t( 'Diaet ueber den internen Schluessel', 'diet:gluten_free',
   ( $r = Mixmymeals_Recipe::resolve_archive_view( 'gluten_free' ) ) ? $r['kind'] . ':' . $r['value'] : '' );
t( 'Zeitstufe ueber den stabilen Schluessel', 'time:30',
   ( $r = Mixmymeals_Recipe::resolve_archive_view( 'under-30-minutes' ) ) ? $r['kind'] . ':' . $r['value'] : '' );
t( 'Stoeber-Ansicht bleibt erreichbar', 'view:fav',
   ( $r = Mixmymeals_Recipe::resolve_archive_view( 'fav' ) ) ? $r['kind'] . ':' . $r['value'] : '' );
t( 'Unsinn loest nichts auf', '', ( $r = Mixmymeals_Recipe::resolve_archive_view( 'quatsch' ) ) ? 'etwas' : '' );

// Ein Rezept darf keinen Facettenpfad besetzen.
$clash = trash( wp_insert_post( array( 'post_type' => 'mmm_recipe', 'post_title' => 'Vegan', 'post_status' => 'publish' ) ) );
t( 'Rezept "Vegan" bekommt nicht /vegan/', '1',
   (string) (int) ( 'vegan' !== get_post_field( 'post_name', $clash ) ) );

// Zeitfilter: das Rezept ohne Angabe darf nicht mitkommen.
$fast   = mk_recipe( 'R21', array( row( $vegan_ing ) ) );
$slow   = mk_recipe( 'R22', array( row( $vegan_ing ) ) );
$untold = mk_recipe( 'R23', array( row( $vegan_ing ) ) );
( new Mixmymeals_Recipe( $fast ) )->set_preparation_time( 10 );
( new Mixmymeals_Recipe( $slow ) )->set_preparation_time( 45 );
Mixmymeals_Recipe::flush_recipe_indexes();

$time_ids = get_posts( array_merge(
	array( 'post_type' => 'mmm_recipe', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true ),
	Mixmymeals_Shortcodes::archive_filter_query_args( array( 'time' => 30 ) )
) );
t( 'unter 30: das schnelle Rezept', '1', (string) (int) in_array( $fast, $time_ids, true ) );
t( 'unter 30: nicht das langsame', '0', (string) (int) in_array( $slow, $time_ids, true ) );
t( 'unter 30: nicht das ohne Angabe', '0', (string) (int) in_array( $untold, $time_ids, true ) );

// Diaetfilter ueber den Index.
$diet_ids = get_posts( array_merge(
	array( 'post_type' => 'mmm_recipe', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true ),
	Mixmymeals_Shortcodes::archive_filter_query_args( array( 'diet' => 'vegan' ) )
) );
t( 'vegan-Filter findet das vegane Rezept', '1', (string) (int) in_array( $fast, $diet_ids, true ) );

// Rangfolge: mehr Treffer zuerst, und gezaehlt wird je Token.
$tok_a = mk_ing( 'T Zucchini' );
$tok_b = mk_ing( 'T Feta' );
$tok_c = mk_ing( 'T Zucchinibluete' );
$r_two = mk_recipe( 'R24', array( row( $tok_a ), row( $tok_b ) ) );
$r_one = mk_recipe( 'R25', array( row( $tok_a ) ) );
$r_syn = mk_recipe( 'R26', array( row( $tok_a ), row( $tok_c ) ) );
Mixmymeals_Recipe::flush_recipe_indexes();

$ranked = Mixmymeals_Shortcodes::rank_recipes_by_ingredients( array( 'T Zucchini', 'T Feta' ) );
t( 'zwei Treffer vor einem', (string) $r_two, (string) ( array_key_first( $ranked ) ?: 0 ) );
t( 'ein Treffer ist dabei', '1', (string) (int) isset( $ranked[ $r_one ] ) );
t( 'ein Token, das zwei Zutaten trifft, zaehlt einmal', '1', (string) ( $ranked[ $r_syn ] ?? 0 ) );

$args = Mixmymeals_Shortcodes::archive_filter_query_args( array( 'ingredients' => array( 'T Feta' ) ) );
t( 'Rangfolge geht als post__in in die Abfrage', 'post__in', (string) ( $args['orderby'] ?? '' ) );
$none = Mixmymeals_Shortcodes::archive_filter_query_args( array( 'ingredients' => array( 'Schinkn' ) ) );
t( 'kein Treffer heisst wirklich keiner', '0', (string) ( $none['post__in'][0] ?? -1 ) );
t( 'unbekanntes Token wird gemeldet', 'Schinkn',
   implode( ',', Mixmymeals_Shortcodes::resolve_ingredient_tokens( array( 'Schinkn' ) )['unknown'] ) );

echo "\n=== 14. Karte: Zeit rein, Kategorie-Diaet raus ===\n";

t( 'Zeit unter einer Stunde', '45 min', Mixmymeals_Shortcodes::format_prep_time( 45 ) );
t( 'volle Stunde', '1 h', Mixmymeals_Shortcodes::format_prep_time( 60 ) );
t( 'Stunde und Rest', '1 h 30 min', Mixmymeals_Shortcodes::format_prep_time( 90 ) );
t( 'keine Angabe bleibt leer', '', Mixmymeals_Shortcodes::format_prep_time( 0 ) );

$r_chip = mk_recipe( 'R27', array( row( $vegan_ing ) ) );
( new Mixmymeals_Recipe( $r_chip ) )->set_preparation_time( 45 );
t( 'Zeit-Pille traegt die Zeit', '1',
   (string) (int) str_contains( Mixmymeals_Shortcodes::prep_time_chip( $r_chip ), '45 min' ) );
( new Mixmymeals_Recipe( $r_chip ) )->set_preparation_time( 0 );
t( 'ohne Zeit keine Pille', '', Mixmymeals_Shortcodes::prep_time_chip( $r_chip ) );

// Eine Kategorie namens "Vegan" darf keine Diaet-Pille mehr erzeugen.
$cat = wp_insert_term( 'ZZ Vegan', 'mmm_recipe_category' );
if ( ! is_wp_error( $cat ) ) {
	// Bewusst eine Zutat ohne Einordnung: dann kann eine Diaet-Pille nur noch
	// aus dem Kategorienamen stammen, und genau das ist der Befund.
	$r_cat = mk_recipe( 'R28', array( row( $leer ) ) );
	wp_set_object_terms( $r_cat, array( (int) $cat['term_id'] ), 'mmm_recipe_category' );
	// Der Shortcode liest get_the_ID(), nimmt also kein post_id-Attribut —
	// ohne den globalen Post prueft der Test nur eine leere Zeichenkette.
	$GLOBALS['post'] = get_post( $r_cat );
	setup_postdata( $GLOBALS['post'] );
	$chips = do_shortcode( '[mmm_recipe_meta]' );
	wp_reset_postdata();
	t( 'Kategorie erscheint als Kategorie', '1',
	   (string) (int) str_contains( $chips, 'mmm-meta__item--category' ) );
	t( 'Kategoriename erzeugt keine Diaet-Pille', '0',
	   (string) (int) str_contains( $chips, 'mmm-meta__item--diet' ) );
	wp_delete_term( (int) $cat['term_id'], 'mmm_recipe_category' );
}

echo "\n=== 15. Zutatenseite liest den Index ===\n";

$many = mk_ing( 'T Vielgenutzt' );
$made = array();
for ( $n = 1; $n <= 30; $n++ ) {
	$made[] = mk_recipe( 'R30-' . $n, array( row( $many ) ) );
}
$draft = mk_recipe( 'R30-entwurf', array( row( $many ) ) );
wp_update_post( array( 'ID' => $draft, 'post_status' => 'draft' ) );
Mixmymeals_Recipe::flush_recipe_indexes();

t( 'alle 30 Rezepte, kein Abbruch bei 24', '30',
   (string) count( Mixmymeals_Recipe::ids_using_ingredient( $many ) ) );
t( 'der Entwurf steht nicht in der oeffentlichen Liste', '0',
   (string) (int) in_array( $draft, Mixmymeals_Recipe::ids_using_ingredient( $many ), true ) );
t( 'mit post_status any ist er dabei', '1',
   (string) (int) in_array( $draft, Mixmymeals_Recipe::ids_using_ingredient( $many, array( 'post_status' => 'any' ) ), true ) );

$GLOBALS['post'] = get_post( $many );
setup_postdata( $GLOBALS['post'] );
$list = do_shortcode( '[mmm_recipes_for_a_single_ingredient]' );
wp_reset_postdata();
t( 'die Seite listet auch alle 30', '30', (string) substr_count( $list, '<li' ) );

echo "\n=== 16. Angewendete Filter: Adressen zum Entfernen ===\n";

$base = get_post_type_archive_link( 'mmm_recipe' );
$strip = fn( $url ) => str_replace( $base, '/', (string) $url );

$set = array(
	'view'        => '',
	'diet'        => 'vegan',
	'time'        => 30,
	'ingredients' => array( 'Zucchini', 'Feta' ),
	'title'       => '',
);

t( 'volle Adresse: Diaet als Pfad, Rest als Parameter',
   '/vegan/?zutat=Zucchini%2C%20Feta&zeit=30',
   $strip( Mixmymeals_Shortcodes::archive_url_for( $set ) ) );

t( 'ohne Diaet uebernimmt die Zeit den Pfad',
   '/unter-30-minuten/?zutat=Zucchini%2C%20Feta',
   $strip( Mixmymeals_Shortcodes::archive_url_for(
	   Mixmymeals_Shortcodes::archive_filters_without( $set, 'diet' ) ) ) );

t( 'ohne Zeit bleibt die Diaet der Pfad',
   '/vegan/?zutat=Zucchini%2C%20Feta',
   $strip( Mixmymeals_Shortcodes::archive_url_for(
	   Mixmymeals_Shortcodes::archive_filters_without( $set, 'time' ) ) ) );

t( 'eine einzelne Zutat faellt weg, die andere bleibt',
   '/vegan/?zutat=Feta&zeit=30',
   $strip( Mixmymeals_Shortcodes::archive_url_for(
	   Mixmymeals_Shortcodes::archive_filters_without( $set, 'ingredient', 'Zucchini' ) ) ) );

$leer_set = array( 'view' => '', 'diet' => '', 'time' => 0, 'ingredients' => array(), 'title' => '' );
t( 'ohne jeden Filter das nackte Archiv', '/',
   $strip( Mixmymeals_Shortcodes::archive_url_for( $leer_set ) ) );

echo "\n=== 17. Zeitstufen als Facettenzeile ===\n";

// Gegen einen Ausgangswert gemessen: die Suite hat vorher schon Rezepte mit
// Zeiten angelegt, und ein Test, der eine leere Datenbank unterstellt, prueft
// in Wahrheit nur, dass niemand vor ihm etwas angelegt hat.
delete_transient( 'mmm_archive_time_counts' );
$vorher = Mixmymeals_Shortcodes::archive_time_counts();

$t20 = mk_recipe( 'R31', array( row( $vegan_ing ) ) );
$t45 = mk_recipe( 'R32', array( row( $vegan_ing ) ) );
$tno = mk_recipe( 'R33', array( row( $vegan_ing ) ) );
( new Mixmymeals_Recipe( $t20 ) )->set_preparation_time( 20 );
( new Mixmymeals_Recipe( $t45 ) )->set_preparation_time( 45 );
Mixmymeals_Recipe::flush_recipe_indexes();

delete_transient( 'mmm_archive_time_counts' );
$counts = Mixmymeals_Shortcodes::archive_time_counts();
$delta  = fn( $step ) => (string) ( ( $counts[ $step ] ?? 0 ) - ( $vorher[ $step ] ?? 0 ) );

t( 'unter 20 zaehlt das 20-Minuten-Rezept', '1', $delta( 20 ) );
t( 'unter 30 zaehlt es weiterhin mit', '1', $delta( 30 ) );
t( 'unter 60 zaehlt beide', '2', $delta( 60 ) );
t( 'das Rezept ohne Zeitangabe zaehlt nirgends', '2', (string) max( array_map( $delta, array( 20, 30, 60 ) ) ) );

// Die Zeile traegt die andere Facette mit.
$mixed = array( 'view' => '', 'diet' => 'vegan', 'time' => 30, 'ingredients' => array(), 'title' => '' );
$strip = fn( $u ) => str_replace( get_post_type_archive_link( 'mmm_recipe' ), '/', (string) $u );
t( 'Zeit wechseln behaelt die Diaet', '/vegan/?zeit=20',
   $strip( Mixmymeals_Shortcodes::archive_url_for( array_merge( $mixed, array( 'time' => 20 ) ) ) ) );
t( 'Zeit loeschen laesst die Diaet den Pfad', '/vegan/',
   $strip( Mixmymeals_Shortcodes::archive_url_for( Mixmymeals_Shortcodes::archive_filters_without( $mixed, 'time' ) ) ) );
t( 'Diaet loeschen macht die Zeit zum Pfad', '/unter-30-minuten/',
   $strip( Mixmymeals_Shortcodes::archive_url_for( Mixmymeals_Shortcodes::archive_filters_without( $mixed, 'diet' ) ) ) );

delete_transient( 'mmm_archive_time_counts' );

echo "\n=== 18. Eine Einstufung laesst sich loeschen ===\n";

/*
 * Der Fehler, den dieser Abschnitt festnagelt: for_post() faellt auf die alte
 * Meta-Zeile zurueck, sobald keine Terme da sind. Wer die Terme wegnahm, bekam
 * beim naechsten Lesen wieder "vegan" aus einer Zeile, die niemand mehr sah.
 * Loeschen war damit unmoeglich. Auf stopfnicht.de trugen vier Zutaten deshalb
 * eine Vegan-Pille, deren Referenzprodukt Fleisch ist.
 *
 * Die Pruefung muss den Memo-Cache umgehen: for_post() merkt sich die Antwort
 * je Request, und ein Test laeuft in einem einzigen. Gelesen wird darum der
 * Rohzustand — Terme und Meta — und das ist ohnehin die haertere Aussage.
 */
$del_meta = static fn( int $id ): string => (string) get_post_meta( $id, Mixmymeals_Diet::LEGACY_META_INGREDIENT, true );
$del_terms = static function ( int $id ): array {
	$t = wp_get_object_terms( $id, Mixmymeals_Diet::TAXONOMY, array( 'fields' => 'slugs' ) );
	return ( $t && ! is_wp_error( $t ) ) ? sorted( $t ) : array();
};

// Eine Zutat, wie sie nach Jahren ohne Migration dasteht: Meta, keine Terme.
$legacy_ing = trash( wp_insert_post( array( 'post_type' => 'mmm_ingredient', 'post_title' => 'ZZ Alt-Einstufung', 'post_status' => 'publish' ) ) );
update_post_meta( $legacy_ing, Mixmymeals_Diet::LEGACY_META_INGREDIENT, 'vegan' );

t( 'Ausgangslage: Meta traegt die Einstufung', 'vegan', $del_meta( $legacy_ing ) );
t( 'und for_post faellt darauf zurueck', 'vegan', implode( ',', Mixmymeals_Diet::for_post( $legacy_ing ) ) );

// Leer schreiben — der Weg, den diets/bulk und diet_type: "" beide nehmen.
Mixmymeals_Diet::set_for_post( $legacy_ing, array() );

t( 'nach dem Leerschreiben: keine Terme', '', implode( ',', $del_terms( $legacy_ing ) ) );
t( 'und die alte Zeile ist weg', '', $del_meta( $legacy_ing ) );
t( 'kein Rueckfall mehr', '', implode( ',', Mixmymeals_Diet::for_post( $legacy_ing ) ) );

// Auch eine nicht leere Aussage raeumt die Zeile ab: zwei Quellen koennen
// einander danach nur noch widersprechen.
update_post_meta( $legacy_ing, Mixmymeals_Diet::LEGACY_META_INGREDIENT, 'vegan' );
Mixmymeals_Diet::set_for_post( $legacy_ing, array( 'vegetarian' ) );
t( 'eine neue Einstufung raeumt die alte Zeile ebenfalls ab', '', $del_meta( $legacy_ing ) );
t( 'und die Terme sagen das Neue', 'vegetarian', implode( ',', $del_terms( $legacy_ing ) ) );

// Der eine Ausstieg: die Migration braucht die Zeile als Rueckweg.
$mig_ing = trash( wp_insert_post( array( 'post_type' => 'mmm_ingredient', 'post_title' => 'ZZ Migrationsfall', 'post_status' => 'publish' ) ) );
update_post_meta( $mig_ing, Mixmymeals_Diet::LEGACY_META_INGREDIENT, 'vegan' );
Mixmymeals_Diet::set_for_post( $mig_ing, array( 'vegan' ), true );
t( 'die Migration laesst die Zeile stehen', 'vegan', $del_meta( $mig_ing ) );
t( 'und schreibt den Term daneben', 'vegan', implode( ',', $del_terms( $mig_ing ) ) );

// Dasselbe am Produkt, das seine eigene Meta-Zeile hat.
$del_ing  = trash( wp_insert_post( array( 'post_type' => 'mmm_ingredient', 'post_title' => 'ZZ Traeger', 'post_status' => 'publish' ) ) );
$del_prod = trash( wp_insert_post( array( 'post_type' => 'mmm_product', 'post_title' => 'ZZ Paket', 'post_status' => 'publish', 'post_parent' => $del_ing ) ) );
update_post_meta( $del_prod, Mixmymeals_Diet::LEGACY_META_PRODUCT, 'vegan' );

t( 'Produkt: Rueckfall auf die eigene Zeile', 'vegan', implode( ',', Mixmymeals_Diet::for_post( $del_prod ) ) );
Mixmymeals_Diet::set_for_post( $del_prod, array() );
t( 'Produkt: Zeile weg', '', (string) get_post_meta( $del_prod, Mixmymeals_Diet::LEGACY_META_PRODUCT, true ) );
t( 'Produkt: kein Rueckfall mehr', '', implode( ',', Mixmymeals_Diet::for_post( $del_prod ) ) );

// Und die Folge, um die es eigentlich geht: das Rezept behauptet nichts mehr.
$del_recipe = trash( wp_insert_post( array( 'post_type' => 'mmm_recipe', 'post_title' => 'ZZ Gericht ohne Einstufung', 'post_status' => 'publish' ) ) );
( new Mixmymeals_Recipe( $del_recipe ) )->set_steps( array(
	array( 'title' => 'S', 'description' => '', 'ingredients' => array( array( 'id' => $legacy_ing, 'amount' => 100 ) ) ),
) );
Mixmymeals_Diet::set_for_post( $legacy_ing, array() );
t( 'das Rezept traegt keine Pille mehr', '', ( new Mixmymeals_Recipe( $del_recipe ) )->get_diet_type() );

echo "\n=== 19. Ernaehrungsformen sind per Term erweiterbar ===\n";

/*
 * Frueher wies pre_insert_term jeden Begriff ab, den types() nicht kannte. Jetzt
 * ist die Taxonomie das Verzeichnis: die Hierarchie ist die Regel (ein Kind
 * impliziert seine Vorfahren), Frage und Staerke stehen als Term-Meta. Der Fehler,
 * den dieser Abschnitt weiterhin festnagelt: ein Term darf nichts bewirken, was
 * seine Stellung im Baum nicht sagt.
 */
$mmm_cleanup_terms = array();
$mk = function ( string $name, string $slug, int $parent = 0 ) use ( &$mmm_cleanup_terms ) {
	$r = wp_insert_term( $name, Mixmymeals_Diet::TAXONOMY, array( 'slug' => $slug, 'parent' => $parent ) );
	if ( is_wp_error( $r ) ) { return 0; }
	$mmm_cleanup_terms[] = (int) $r['term_id'];
	return (int) $r['term_id'];
};

Mixmymeals_Diet::ensure_terms();
$veg = term_exists( 'vegetarian', Mixmymeals_Diet::TAXONOMY );
$vgn = term_exists( 'vegan', Mixmymeals_Diet::TAXONOMY );
t( 'Vegan haengt unter Vegetarisch', (int) $veg['term_id'], (int) get_term( (int) $vgn['term_id'], Mixmymeals_Diet::TAXONOMY )->parent );

// Ein frei angelegter Term wird erkannt, ohne Code und ohne Filter.
$pesc = $mk( 'ZZ Pescetarian', 'zz_pescetarian' );
t( 'ein frei angelegter Term wird angenommen', true, $pesc > 0 );
t( 'is_known() kennt ihn', true, Mixmymeals_Diet::is_known( 'zz_pescetarian' ) );
t( 'Diet::label() nennt ihn beim Namen', 'ZZ Pescetarian', Mixmymeals_Diet::label( 'zz_pescetarian' ) );
t( 'ohne Beziehungen impliziert er nichts', array(), Mixmymeals_Diet::types()['zz_pescetarian']['implies'] );
t( 'und beantwortet eine eigene Frage', 'zz_pescetarian', Mixmymeals_Diet::types()['zz_pescetarian']['axis'] );

// Die Hierarchie: Vegetarisch unter Pescetarisch -> vegan impliziert beides.
wp_update_term( (int) $veg['term_id'], Mixmymeals_Diet::TAXONOMY, array( 'parent' => $pesc ) );
$types = Mixmymeals_Diet::types();
t( 'Vegetarisch impliziert nun Pescetarisch', true, in_array( 'zz_pescetarian', $types['vegetarian']['implies'], true ) );
t( 'Vegan impliziert transitiv beides', array( 'vegetarian', 'zz_pescetarian' ), (function () use ( $types ) { $i = $types['vegan']['implies']; sort( $i ); return $i; })() );
t( 'expand() folgt dem Baum', true, in_array( 'zz_pescetarian', Mixmymeals_Diet::expand( array( 'vegan' ) ), true ) );
t( 'die Wurzel uebernimmt die Achse der Eingebauten darunter', 'diet', $types['zz_pescetarian']['axis'] );
t( 'Rang: Vegetarisch schlaegt Pescetarisch', true, $types['vegetarian']['rank'] > $types['zz_pescetarian']['rank'] );
t( 'strongest() nennt das Staerkste', 'vegan', Mixmymeals_Diet::strongest( array( 'zz_pescetarian', 'vegan', 'vegetarian' ) ) );

// Ein Kind eines frei angelegten Terms erbt die Achse und ist staerker.
$raw = $mk( 'ZZ Raw Vegan', 'zz_raw_vegan', (int) $vgn['term_id'] );
$types = Mixmymeals_Diet::types();
t( 'ein Kind erbt die Achse', 'diet', $types['zz_raw_vegan']['axis'] );
t( 'und impliziert alle Vorfahren', 3, count( $types['zz_raw_vegan']['implies'] ) );
t( 'und ist staerker als sein Elternteil', true, $types['zz_raw_vegan']['rank'] > $types['vegan']['rank'] );

// Term-Meta: Frage, Staerke und Schema-URL.
$side = $mk( 'ZZ Side', 'zz_side' );
update_term_meta( $side, Mixmymeals_Diet::META_AXIS, 'zz_other' );
update_term_meta( $side, Mixmymeals_Diet::META_RANK, 77 );
update_term_meta( $side, Mixmymeals_Diet::META_SCHEMA, 'https://schema.org/KosherDiet' );
$types = Mixmymeals_Diet::types();
t( 'Term-Meta: Frage', 'zz_other', $types['zz_side']['axis'] );
t( 'Term-Meta: Staerke', 77, $types['zz_side']['rank'] );
t( 'Term-Meta: schema.org', 'https://schema.org/KosherDiet', Mixmymeals_Diet::schema_url( 'zz_side' ) );

// Eingebaute bleiben von Umbenennung unberuehrt.
wp_update_term( (int) $vgn['term_id'], Mixmymeals_Diet::TAXONOMY, array( 'name' => 'ZZ Umbenannt' ) );
t( 'eine eingebaute Diaet behaelt ihr Etikett', Mixmymeals_Diet::label( 'vegan' ), __( 'Vegan', 'mixmymeals' ) );
wp_update_term( (int) $vgn['term_id'], Mixmymeals_Diet::TAXONOMY, array( 'name' => 'Vegan' ) );

// Eine Zutat mit dem Kindterm gilt im Rezept auch als vegetarisch.
$zz_ing = trash( wp_insert_post( array( 'post_type' => 'mmm_ingredient', 'post_title' => 'ZZ Roh', 'post_status' => 'publish' ) ) );
Mixmymeals_Diet::set_for_post( $zz_ing, array( 'zz_raw_vegan' ) );
t( 'eine Zutat kann den Term fuehren', array( 'zz_raw_vegan' ), Mixmymeals_Diet::for_post( $zz_ing ) );
t( 'combine() kennt die Implikation', true, in_array( 'vegetarian', Mixmymeals_Diet::combine( array( array( 'zz_raw_vegan' ) ) ), true ) );

// Zyklus ueber den Filter darf nichts aufhaengen.
add_filter( 'mixmymeals_diet_types', function ( array $t ): array {
	$t['zz_a'] = array( 'label' => 'A', 'implies' => array( 'zz_b' ) );
	$t['zz_b'] = array( 'label' => 'B', 'implies' => array( 'zz_a' ) );
	return $t;
} );
Mixmymeals_Diet::reset_types();
t( 'ein Zyklus ueber den Filter terminiert', true, is_array( Mixmymeals_Diet::types() ) );

// Keine eigene Label-Map mehr: die Stelle, die bei einer neuen Diaet sonst
// stumm bliebe, obwohl get_diet_type() sie laengst liefert.
$mmm_shortcodes_src = file_get_contents( dirname( __DIR__ ) . '/includes/lib/class-mixmymeals-shortcodes.php' );
t( 'kein eigenes $mmm_diet_labels mehr im Code', false, false !== strpos( (string) $mmm_shortcodes_src, '$mmm_diet_labels' ) );

foreach ( $mmm_cleanup_terms as $mmm_tid ) { wp_delete_term( $mmm_tid, Mixmymeals_Diet::TAXONOMY ); }

echo "\n=== Ergebnis ===\n";
printf( "  %d bestanden, %d fehlgeschlagen\n", $GLOBALS['PASS'], $GLOBALS['FAIL'] );

foreach ( array_reverse( $GLOBALS['TRASH'] ) as $id ) { wp_delete_post( $id, true ); }
printf( "  aufgeraeumt: %d Posts\n", count( $GLOBALS['TRASH'] ) );
