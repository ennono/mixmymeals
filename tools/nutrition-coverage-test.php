<?php
/**
 * Prüft, woran man ungepflegte Nährwerte erkennt — und was dann passiert.
 *
 * Drei Dinge, die zusammengehören und die vor 3.52.0 alle drei fehlten:
 *
 *  1. Ein leeres Feld bleibt leer. Der Admin-Block schrieb `floatval( '' )`,
 *     also eine harte 0, und damit war „nie angegeben" von „gemessen 0" nicht
 *     mehr zu unterscheiden.
 *  2. `Mixmymeals_Ingredient::has_nutrition()` ist die einzige Regel dafür.
 *     Zutatenliste, Rezeptspalte und /audit fragen sie, damit sie nicht drei
 *     verschiedene Zutaten nennen können.
 *  3. `Mixmymeals_Recipe::nutrition_coverage()` sagt, aus wieviel vom Gericht
 *     die Summe gebaut ist. Ein fehlender Wert zählt in der Summe als 0 — die
 *     Zahl sieht also fertig aus und ist zu niedrig.
 *
 * Läuft gegen eine echte Installation, legt eigene Posts an und löscht sie
 * wieder. Alles heißt „ZZ …".
 *
 * wp --require=<dbhost.php> eval-file \
 *    wp-content/plugins/mixmymeals/tools/nutrition-coverage-test.php
 *
 * @package MixMyMeals
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Zähler in $GLOBALS: wp-cli führt eval-file in einem Funktionsgeltungsbereich
 * aus, `global $x` greift dort ins Leere. Derselbe Stolperstein steht oben in
 * diet-test.php und excluded-products-test.php.
 */
$GLOBALS['MMM_NC_PASS']  = 0;
$GLOBALS['MMM_NC_FAIL']  = 0;
$GLOBALS['MMM_NC_TRASH'] = array();
$GLOBALS['MMM_NC_META']  = new Mixmymeals_Post_Meta();

/**
 * Eine Zusicherung.
 *
 * @param bool   $ok    Ergebnis.
 * @param string $label Beschreibung.
 * @return void
 */
function mmm_nc_assert( bool $ok, string $label ): void {
	if ( $ok ) {
		++$GLOBALS['MMM_NC_PASS'];
		echo "ok   $label\n";
		return;
	}
	++$GLOBALS['MMM_NC_FAIL'];
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
function mmm_nc_is( string $label, string $expected, string $actual ): void {
	mmm_nc_assert( $expected === $actual, $label . ( $expected === $actual ? '' : " (erwartet $expected, bekommen $actual)" ) );
}

/**
 * Merkt sich einen Post zum Aufräumen.
 *
 * @param int $id Post-ID.
 * @return int
 */
function mmm_nc_trash( int $id ): int {
	$GLOBALS['MMM_NC_TRASH'][] = $id;
	return $id;
}

/**
 * Legt eine Zutat an.
 *
 * @param string $title    Titel.
 * @param array  $nutri    Nährwerte, leer für keine.
 * @param string $status   Post-Status.
 * @return int
 */
function mmm_nc_ing( string $title, array $nutri = array(), string $status = 'publish' ): int {

	$id = mmm_nc_trash(
		(int) wp_insert_post(
			array(
				'post_type'   => 'mmm_ingredient',
				'post_title'  => $title,
				'post_status' => $status,
			)
		)
	);

	if ( $nutri ) {
		( new Mixmymeals_Ingredient( $id ) )->set_nutrition( $nutri );
	}

	return $id;
}

/**
 * Legt ein Rezept mit einem Schritt an.
 *
 * @param string  $title Titel.
 * @param array[] $rows  Zutatenzeilen.
 * @return int
 */
function mmm_nc_recipe( string $title, array $rows ): int {

	$id = mmm_nc_trash(
		(int) wp_insert_post(
			array(
				'post_type'   => 'mmm_recipe',
				'post_title'  => $title,
				'post_status' => 'publish',
			)
		)
	);

	update_post_meta( $id, '_mmm_servings', 2 );

	( new Mixmymeals_Recipe( $id ) )->set_steps(
		array(
			array(
				'title'       => 'S',
				'description' => 'x',
				'ingredients' => $rows,
			),
		)
	);

	return $id;
}

/**
 * Eine Zutatenzeile in Gramm.
 *
 * @param int   $id     Zutat.
 * @param float $amount Gramm.
 * @return array<string,mixed>
 */
function mmm_nc_row( int $id, float $amount = 100 ): array {
	return array(
		'id'        => $id,
		'amount'    => $amount,
		'amount_id' => 0,
	);
}

/*
 * Stimmig gewählt, nicht rund: 9·3 + 4·10 + 4·6 + 2·2 = 95 kcal, die
 * angegebenen 100 liegen innerhalb der Toleranz von nutrition_warnings().
 * Mit den ursprünglichen Werten (82 kcal aus den Makros) hat die
 * Plausibilitätsprüfung die eigene Testvorlage beanstandet — zu Recht.
 */
$full = array(
	'calories_100' => 100,
	'fat'          => 3,
	'saturates'    => 0.5,
	'carbs'        => 10,
	'protein'      => 6,
	'sugar'        => 1,
	'fiber'        => 2,
	'salt'         => 0.1,
);

echo "\n=== 1. Ein leeres Feld ist keine Null ===\n";

$blank = mmm_nc_ing( 'ZZ Ohne Angaben' );

mmm_nc_assert(
	null === ( new Mixmymeals_Ingredient( $blank ) )->get_nutrition()['calories_100'],
	'nie gesetzt liest sich als null, nicht als 0'
);

$cleared = mmm_nc_ing( 'ZZ Wieder geleert', $full );
( new Mixmymeals_Ingredient( $cleared ) )->set_nutrition( array( 'calories_100' => '', 'fat' => '' ) );
$after = ( new Mixmymeals_Ingredient( $cleared ) )->get_nutrition();

mmm_nc_assert( null === $after['calories_100'], 'leerer String löscht die Zeile, statt 0 zu schreiben' );
mmm_nc_assert( 10.0 === (float) $after['carbs'], 'und lässt die nicht genannten Felder stehen' );
mmm_nc_assert(
	'' === get_post_meta( $cleared, '_rmm_calories_100', true ),
	'die Meta-Zeile ist wirklich weg, nicht auf "0" gesetzt'
);

echo "\n=== 2. has_nutrition(): die eine Regel ===\n";

mmm_nc_assert( ! ( new Mixmymeals_Ingredient( $blank ) )->has_nutrition(), 'ohne jede Angabe: nein' );
mmm_nc_assert( ( new Mixmymeals_Ingredient( mmm_nc_ing( 'ZZ Voll', $full ) ) )->has_nutrition(), 'vollständig: ja' );

// Der Fall, den die alte Prüfung auf calories_100 falsch beantwortete.
$fat_only = mmm_nc_ing( 'ZZ Nur Fett', array( 'fat' => 90 ) );
mmm_nc_assert(
	( new Mixmymeals_Ingredient( $fat_only ) )->has_nutrition(),
	'nur Fett angegeben: ja (die kcal-Prüfung sagte hier nein)'
);

$zeros = mmm_nc_ing( 'ZZ Alles Null', array_fill_keys( array_keys( $full ), 0 ) );
mmm_nc_assert(
	! ( new Mixmymeals_Ingredient( $zeros ) )->has_nutrition(),
	'überall 0 gilt als ungepflegt (Altbestand aus floatval(""))'
);

$weight_only = mmm_nc_ing( 'ZZ Nur Stückgewicht', array( 'weight_item' => 60 ) );
mmm_nc_assert(
	! ( new Mixmymeals_Ingredient( $weight_only ) )->has_nutrition(),
	'Stückgewicht allein ist keine Nährwertangabe'
);

// Über das Referenzprodukt: die Zutat selbst sagt nichts.
$by_product = mmm_nc_ing( 'ZZ Leer mit Produkt' );
$packet     = mmm_nc_trash(
	(int) wp_insert_post(
		array(
			'post_type'   => 'mmm_product',
			'post_title'  => 'ZZ Paket',
			'post_status' => 'publish',
			'post_parent' => $by_product,
		)
	)
);
( new Mixmymeals_Product( $packet ) )->set_nutrition( array( 'calories_100' => 250 ) );
mmm_nc_assert(
	! ( new Mixmymeals_Ingredient( $by_product ) )->has_nutrition(),
	'Produkt angelegt, aber nicht als Referenz gewählt: zählt nicht'
);
Mixmymeals_Product::set_reference_id( $by_product, $packet );
mmm_nc_assert(
	( new Mixmymeals_Ingredient( $by_product ) )->has_nutrition(),
	'als Referenz gewählt: die Zutat gilt als gepflegt'
);

echo "\n=== 3. Die Zutatenliste findet den Rückstand ===\n";

$without = Mixmymeals_Ingredient::ids_without_nutrition();

mmm_nc_assert( in_array( $blank, $without, true ), 'die leere Zutat steht in ids_without_nutrition()' );
mmm_nc_assert( in_array( $zeros, $without, true ), 'die Alles-Null-Zutat auch' );
mmm_nc_assert( ! in_array( $fat_only, $without, true ), 'die mit Fett nicht' );
mmm_nc_assert( ! in_array( $by_product, $without, true ), 'die mit Referenzprodukt nicht' );

echo "\n=== 4. Deckung eines Rezepts ===\n";

$filled = mmm_nc_ing( 'ZZ Gepflegt', $full );

$r_all = mmm_nc_recipe( 'ZZ R-voll', array( mmm_nc_row( $filled, 200 ) ) );
$cov   = ( new Mixmymeals_Recipe( $r_all ) )->nutrition_coverage();
mmm_nc_is( 'alles gedeckt: share 1.0', '1', (string) (int) round( $cov['share'] ) );
mmm_nc_assert( $cov['complete'], 'und complete' );
mmm_nc_assert( array() === $cov['missing'], 'ohne Lückenliste' );
mmm_nc_is( 'kein schlechtester Wert', '', (string) $cov['worst'] );
mmm_nc_assert( 8 === count( $cov['keys'] ), 'alle acht Werte einzeln ausgewiesen' );

// 1 g in 201 g = 0,5 % — unter der Toleranz.
$pinch = mmm_nc_ing( 'ZZ Prise' );
$r_tol = mmm_nc_recipe( 'ZZ R-toleranz', array( mmm_nc_row( $filled, 200 ), mmm_nc_row( $pinch, 1 ) ) );
$cov   = ( new Mixmymeals_Recipe( $r_tol ) )->nutrition_coverage();
mmm_nc_assert( $cov['complete'], '1 g von 201 g ohne Angaben: gilt als vollständig (2 %-Toleranz)' );
mmm_nc_is( 'die Lücke wird trotzdem benannt', '1', (string) count( $cov['missing'] ) );

// 50 g in 250 g = 20 % — darüber.
$r_gap = mmm_nc_recipe( 'ZZ R-lücke', array( mmm_nc_row( $filled, 200 ), mmm_nc_row( $blank, 50 ) ) );
$cov   = ( new Mixmymeals_Recipe( $r_gap ) )->nutrition_coverage();
mmm_nc_assert( ! $cov['complete'], '50 g von 250 g ohne Angaben: nicht vollständig' );
mmm_nc_is( 'gedeckter Gewichtsanteil', '80', (string) (int) round( $cov['share'] * 100 ) );
mmm_nc_is( 'eine Zutat in der Lückenliste', '1', (string) count( $cov['missing'] ) );
mmm_nc_is( 'mit Namen', 'ZZ Ohne Angaben', (string) $cov['missing'][0]['name'] );

// Dieselbe Zutat in zwei Schritten ist eine Lücke, nicht zwei.
( new Mixmymeals_Recipe( $r_gap ) )->set_steps(
	array(
		array( 'title' => 'S1', 'description' => 'x', 'ingredients' => array( mmm_nc_row( $filled, 200 ), mmm_nc_row( $blank, 25 ) ) ),
		array( 'title' => 'S2', 'description' => 'x', 'ingredients' => array( mmm_nc_row( $blank, 25 ) ) ),
	)
);
$cov = ( new Mixmymeals_Recipe( $r_gap ) )->nutrition_coverage();
mmm_nc_is( 'zweimal dieselbe Zutat: ein Eintrag', '1', (string) count( $cov['missing'] ) );
mmm_nc_is( 'mit zusammengezähltem Gewicht', '50', (string) (int) round( $cov['missing'][0]['weight'] ) );
mmm_nc_is( 'und unveränderter Deckung', '80', (string) (int) round( $cov['share'] * 100 ) );

// Was die Spalte qualifiziert: die Summe sieht fertig aus und ist zu niedrig.
$serving = ( new Mixmymeals_Recipe( $r_gap ) )->calculate_nutrition_per_serving();
mmm_nc_is(
	'die Summe zählt die undokumentierte Zutat als 0 (200 g × 100 kcal / 2 Portionen)',
	'100',
	(string) (int) round( $serving['calories'] )
);

$r_empty = mmm_nc_recipe( 'ZZ R-ohne-Zutaten', array() );
$cov     = ( new Mixmymeals_Recipe( $r_empty ) )->nutrition_coverage();
mmm_nc_assert( $cov['complete'], 'Rezept ohne Zutaten: keine Lücke, nur nichts zu dokumentieren' );

echo "\n=== 5. Deckung je Nährwert ===\n";

/*
 * Der Fall, den die zutatenweise Regel nicht sah: die Zutat ist gepflegt
 * genug, um veröffentlicht zu sein, und ihr leeres Ballaststoff-Feld geht
 * trotzdem in jede Rezeptsumme als 0 ein.
 */
$no_fiber = mmm_nc_ing(
	'ZZ Ohne Ballaststoffe',
	array(
		'calories_100' => 100,
		'fat'          => 2,
		'saturates'    => 0.5,
		'carbs'        => 10,
		'protein'      => 5,
		'sugar'        => 1,
		'salt'         => 0.1,
	)
);

mmm_nc_assert(
	( new Mixmymeals_Ingredient( $no_fiber ) )->has_nutrition(),
	'teilweise gepflegt: has_nutrition() sagt weiterhin ja (der Gate greift nicht)'
);
mmm_nc_is(
	'unstated_nutrition_keys() nennt genau das leere Feld',
	'fiber',
	implode( ',', ( new Mixmymeals_Ingredient( $no_fiber ) )->unstated_nutrition_keys() )
);

$r_field = mmm_nc_recipe( 'ZZ R-feldweise', array( mmm_nc_row( $filled, 100 ), mmm_nc_row( $no_fiber, 100 ) ) );
$cov     = ( new Mixmymeals_Recipe( $r_field ) )->nutrition_coverage();

mmm_nc_assert( ! $cov['complete'], 'ein leeres Feld macht das Rezept unvollständig' );
mmm_nc_is( 'der schlechteste Wert ist der benannte', 'fiber', (string) $cov['worst'] );
mmm_nc_is( 'und seine Deckung', '50', (string) (int) round( $cov['share'] * 100 ) );
mmm_nc_is( 'die anderen Werte bleiben voll gedeckt', '100', (string) (int) round( $cov['keys']['calories_100']['share'] * 100 ) );
mmm_nc_is( 'die Zutat steht in der Lückenliste', '1', (string) count( $cov['missing'] ) );
mmm_nc_is( 'mit dem Feld, das ihr fehlt', 'fiber', implode( ',', $cov['missing'][0]['keys'] ) );

// Eine 0, die jemand eingetragen hat, ist eine Antwort und keine Lücke.
$zero_fiber = mmm_nc_ing(
	'ZZ Ballaststoffe null',
	array(
		'calories_100' => 100,
		'fat'          => 2,
		'saturates'    => 0.5,
		'carbs'        => 10,
		'protein'      => 5,
		'sugar'        => 1,
		'fiber'        => 0,
		'salt'         => 0.1,
	)
);
mmm_nc_assert(
	array() === ( new Mixmymeals_Ingredient( $zero_fiber ) )->unstated_nutrition_keys(),
	'eingetragene 0 gilt als angegeben, nicht als Lücke'
);

// Der schlechteste von mehreren: Gleichstand entscheidet die Etikettreihenfolge.
$no_sugar = mmm_nc_ing(
	'ZZ Ohne Zucker',
	array(
		'calories_100' => 100,
		'fat'          => 2,
		'saturates'    => 0.5,
		'carbs'        => 10,
		'protein'      => 5,
		'fiber'        => 2,
		'salt'         => 0.1,
	)
);
$r_two = mmm_nc_recipe(
	'ZZ R-zwei-Lücken',
	array( mmm_nc_row( $filled, 100 ), mmm_nc_row( $no_fiber, 100 ), mmm_nc_row( $no_sugar, 50 ) )
);
$cov = ( new Mixmymeals_Recipe( $r_two ) )->nutrition_coverage();
mmm_nc_is( 'zwei Lücken: der schlechtere Wert wird benannt', 'fiber', (string) $cov['worst'] );
mmm_nc_is( 'Ballaststoffe', '60', (string) (int) round( $cov['keys']['fiber']['share'] * 100 ) );
mmm_nc_is( 'Zucker', '80', (string) (int) round( $cov['keys']['sugar']['share'] * 100 ) );
mmm_nc_is( 'zwei Zutaten in der Lückenliste', '2', (string) count( $cov['missing'] ) );

// Die Toleranz gilt je Wert, nicht über alle.
$r_field_tol = mmm_nc_recipe( 'ZZ R-feld-toleranz', array( mmm_nc_row( $filled, 200 ), mmm_nc_row( $no_fiber, 1 ) ) );
$cov         = ( new Mixmymeals_Recipe( $r_field_tol ) )->nutrition_coverage();
mmm_nc_assert( $cov['complete'], '1 g ohne Ballaststoffangabe von 201 g: gilt als vollständig' );

echo "\n=== 6. Der Gate auf dem Zutaten-Screen ===\n";

/*
 * Durch wp_update_post() statt durch einen direkten Aufruf: der Gate hängt an
 * save_post, und ob er in der richtigen Reihenfolge und ohne sich selbst
 * wieder auszulösen läuft, ist die halbe Frage. Angelegt wird darum als
 * Entwurf und mit leerem $_POST — sonst feuert der Hook schon beim Anlegen,
 * bevor die Nährwerte geschrieben sind, und der Test prüfte seine eigene
 * Reihenfolge statt der des Admin-Screens.
 */
wp_set_current_user( 1 );

$_POST      = array();
$gate_blank = mmm_nc_ing( 'ZZ Gate ohne Angaben', array(), 'draft' );
$gate_full  = mmm_nc_ing( 'ZZ Gate mit Angaben', $full, 'draft' );
$gate_rest  = mmm_nc_ing( 'ZZ Gate ohne Nonce', array(), 'draft' );

$_POST = array(
	'mmm_ingredient_amounts_meta_nonce' => wp_create_nonce( 'mmm_ingredient_amounts_meta_box' ),
);

wp_update_post( array( 'ID' => $gate_full, 'post_status' => 'publish' ) );
mmm_nc_is( 'mit Nährwerten: wird veröffentlicht', 'publish', (string) get_post_status( $gate_full ) );

wp_update_post( array( 'ID' => $gate_blank, 'post_status' => 'publish' ) );
mmm_nc_is( 'ohne Nährwerte: fällt auf Entwurf zurück', 'draft', (string) get_post_status( $gate_blank ) );

// Und zweimal hintereinander ändert nichts weiter — der Wiedereintritts-
// schutz hält, sonst stünde hier eine Endlosschleife statt einer Zusicherung.
wp_update_post( array( 'ID' => $gate_blank, 'post_status' => 'publish' ) );
mmm_nc_is( 'noch einmal veröffentlicht: wieder Entwurf', 'draft', (string) get_post_status( $gate_blank ) );

// Ohne den Nonce des Screens — Quick Edit, Bulk Edit, REST, MCP.
$_POST = array();
wp_update_post( array( 'ID' => $gate_rest, 'post_status' => 'publish' ) );
mmm_nc_is( 'ohne den Nonce des Screens: unangetastet', 'publish', (string) get_post_status( $gate_rest ) );

echo "\n=== 7. Gesättigte Fettsäuren: der achte Wert ===\n";

$sat = mmm_nc_ing( 'ZZ Butter', $full );
mmm_nc_is(
	'die Zutat speichert und liest den Wert',
	'0.5',
	(string) ( new Mixmymeals_Ingredient( $sat ) )->get_nutrition()['saturates']
);
mmm_nc_assert(
	in_array( 'saturates', Mixmymeals_Meal_Plan::NUTRITION_KEYS, true ),
	'die Plansummen führen ihn mit'
);
mmm_nc_is( 'Referenzmenge 20 g (EU)', '20', (string) (int) Mixmymeals_Meal_Plan::reference_intake()['saturates'] );
mmm_nc_assert(
	isset( Mixmymeals_Meal_Plan::nutrition_labels()['saturates'] ),
	'und er hat eine Zeile in jeder Tabelle'
);

$r_sat = mmm_nc_recipe( 'ZZ R-gesättigt', array( mmm_nc_row( $sat, 200 ) ) );
mmm_nc_is(
	'das Rezept summiert ihn (200 g × 0,5 g / 2 Portionen)',
	'0.5',
	(string) round( ( new Mixmymeals_Recipe( $r_sat ) )->calculate_nutrition_per_serving()['saturates'], 2 )
);

$schema = ( new Mixmymeals_Recipe( $r_sat ) )->get_schema();
mmm_nc_is( 'und das Markup nennt ihn', '0.5 g', (string) ( $schema['nutrition']['saturatedFatContent'] ?? '' ) );

echo "\n=== 8. Salz ist nicht Natrium ===\n";

// 0,1 g Salz je 100 g, 200 g, 2 Portionen = 0,1 g Salz je Portion.
mmm_nc_is(
	'sodiumContent ist Natrium, also Salz / 2,5',
	'0 g',
	(string) ( $schema['nutrition']['sodiumContent'] ?? '' )
);

$salty = mmm_nc_ing( 'ZZ Salzig', array_merge( $full, array( 'salt' => 25 ) ) );
$r_salt = mmm_nc_recipe( 'ZZ R-salzig', array( mmm_nc_row( $salty, 200 ) ) );
$s_schema = ( new Mixmymeals_Recipe( $r_salt ) )->get_schema();
// 25 g/100 g × 200 g / 2 Portionen = 25 g Salz = 10 g Natrium.
mmm_nc_is( '25 g Salz je Portion werden 10 g Natrium', '10 g', (string) ( $s_schema['nutrition']['sodiumContent'] ?? '' ) );

echo "\n=== 9. Grenze oder Ziel ===\n";

mmm_nc_is( 'Salz ist ein Höchstwert', 'limit', Mixmymeals_Meal_Plan::nutrition_direction( 'salt' ) );
mmm_nc_is( 'gesättigte Fettsäuren auch', 'limit', Mixmymeals_Meal_Plan::nutrition_direction( 'saturates' ) );
mmm_nc_is( 'Ballaststoffe sind ein Mindestwert', 'target', Mixmymeals_Meal_Plan::nutrition_direction( 'fiber' ) );
mmm_nc_is( 'Energie ist keins von beidem', 'reference', Mixmymeals_Meal_Plan::nutrition_direction( 'calories' ) );
mmm_nc_is( 'ein unbekannter Schlüssel behauptet am wenigsten', 'reference', Mixmymeals_Meal_Plan::nutrition_direction( 'zzz' ) );

mmm_nc_assert(
	false !== strpos( Mixmymeals_Meal_Plan::format_nutrition_percentage( 'salt', 100.0 ), 'max' ),
	'100 % Salz wird als Höchstwert ausgewiesen'
);
mmm_nc_assert(
	false !== strpos( Mixmymeals_Meal_Plan::format_nutrition_percentage( 'fiber', 60.0 ), 'min' ),
	'60 % Ballaststoffe als Mindestwert'
);
mmm_nc_is(
	'Energie bleibt eine nackte Prozentzahl',
	'93 %',
	Mixmymeals_Meal_Plan::format_nutrition_percentage( 'calories', 93.0 )
);
mmm_nc_assert(
	false !== strpos( Mixmymeals_Meal_Plan::reference_intake_note(), 'max' ),
	'und die Fußnote erklärt, was die Markierungen bedeuten'
);

echo "\n=== 10. Plausibilität ===\n";

mmm_nc_is( 'stimmige Angaben: keine Warnung', '0', (string) count( Mixmymeals_Ingredient::nutrition_warnings( $full ) ) );

$warn = static fn( array $over ): array => Mixmymeals_Ingredient::nutrition_warnings( array_merge( $full, $over ) );

mmm_nc_is( 'gesättigte Fettsäuren über dem Gesamtfett', '1', (string) count( $warn( array( 'saturates' => 5 ) ) ) );
mmm_nc_is( 'Zucker über den Kohlenhydraten', '1', (string) count( $warn( array( 'sugar' => 20 ) ) ) );
mmm_nc_assert(
	count( $warn( array( 'carbs' => 95, 'fat' => 40 ) ) ) >= 1,
	'mehr als 100 g in 100 g'
);
mmm_nc_assert(
	count( $warn( array( 'calories_100' => 700 ) ) ) >= 1,
	'Energie passt nicht zu den Makros'
);
mmm_nc_assert(
	count( $warn( array( 'calories_100' => 1200 ) ) ) >= 1,
	'über 900 kcal je 100 g'
);
// 120 g Ballaststoffe in 100 g: der Einzelwert und die Gesamtmasse, zwei
// Sätze zu einem Tippfehler — beide stimmen, und beide sagen etwas anderes.
mmm_nc_is(
	'ein Einzelwert über 100 g (plus Gesamtmasse)',
	'2',
	(string) count( $warn( array( 'fiber' => 120, 'carbs' => 0, 'protein' => 0, 'fat' => 0, 'saturates' => 0, 'calories_100' => 240, 'sugar' => 0, 'salt' => 0 ) ) )
);
mmm_nc_is( 'leere Felder lösen nichts aus', '0', (string) count( Mixmymeals_Ingredient::nutrition_warnings( array() ) ) );

// Die Rundung einer echten Packung darf nicht anschlagen.
mmm_nc_is(
	'eine Packung, die sich auf 100,3 g rundet, bleibt unbeanstandet',
	'0',
	(string) count(
		Mixmymeals_Ingredient::nutrition_warnings(
			array( 'calories_100' => 380, 'fat' => 1.0, 'saturates' => 0.2, 'carbs' => 72.0, 'sugar' => 1.0, 'fiber' => 10.0, 'protein' => 13.0, 'salt' => 0.1 )
		)
	)
);

echo "\n=== 11. Disclaimer auf der Zutatenseite ===\n";

/*
 * Der Rezept-Disclaimer erreichte nur Rezept- und Mahlzeitenseiten. Die
 * Zutatenseite zeigt Nährwerttabelle und Ampel — also genau das, worüber so
 * ein Text etwas sagt.
 */
$mmm_nc_disclaimer_before = get_option( 'mixmymeals_recipe_disclaimer_text', '' );
$mmm_nc_switch_before     = get_option( 'mixmymeals_recipe_disclaimer_on_ingredients', '' );

update_option( 'mixmymeals_recipe_disclaimer_text', 'ZZ Hinweis: Verbindlich ist die Packung.' );
update_option( 'mixmymeals_recipe_disclaimer_on_ingredients', '' );

$disc_ing = mmm_nc_ing( 'ZZ Mit Nährwerten', $full );
$render   = static fn(): string => do_shortcode( '[mmm_nutrition_table_for_a_single_ingredient id="' . $disc_ing . '"]' );

mmm_nc_assert( false === strpos( $render(), 'ZZ Hinweis' ), 'ausgeschaltet: kein Hinweis unter der Tabelle' );

update_option( 'mixmymeals_recipe_disclaimer_on_ingredients', 'on' );
mmm_nc_assert( false !== strpos( $render(), 'ZZ Hinweis' ), 'eingeschaltet: der Hinweis steht da' );
mmm_nc_assert(
	false !== strpos( $render(), 'mmm-recipe-disclaimer' ),
	'und trägt dieselbe Klasse wie auf der Rezeptseite'
);
mmm_nc_assert(
	strpos( $render(), 'ZZ Hinweis' ) > strpos( $render(), '</table>' ),
	'unter der Tabelle, nicht darüber'
);

// Ein leerer Disclaimer darf keinen leeren Absatz erzeugen.
update_option( 'mixmymeals_recipe_disclaimer_text', '   ' );
mmm_nc_assert( false === strpos( $render(), 'mmm-recipe-disclaimer' ), 'ohne Text: kein leerer Absatz' );

// Eine Zutat ohne Nährwerte hat keine Tabelle — und bekommt auch keinen Hinweis.
update_option( 'mixmymeals_recipe_disclaimer_text', 'ZZ Hinweis: Verbindlich ist die Packung.' );
$disc_blank = mmm_nc_ing( 'ZZ Ohne alles' );
mmm_nc_is(
	'ohne Tabelle kein Hinweis',
	'',
	trim( do_shortcode( '[mmm_nutrition_table_for_a_single_ingredient id="' . $disc_blank . '"]' ) )
);

update_option( 'mixmymeals_recipe_disclaimer_text', $mmm_nc_disclaimer_before );
update_option( 'mixmymeals_recipe_disclaimer_on_ingredients', $mmm_nc_switch_before );

echo "\n=== Aufräumen ===\n";

foreach ( $GLOBALS['MMM_NC_TRASH'] as $trash_id ) {
	wp_delete_post( $trash_id, true );
}
echo 'entfernt: ' . count( $GLOBALS['MMM_NC_TRASH'] ) . " Posts\n";

echo "\n=== Ergebnis ===\n";
printf( "  %d bestanden, %d fehlgeschlagen\n", $GLOBALS['MMM_NC_PASS'], $GLOBALS['MMM_NC_FAIL'] );
