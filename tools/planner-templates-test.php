<?php
/**
 * Prüft die Option "Nur Pläne mit Kategorie als Vorlage anbieten".
 *
 * Läuft gegen eine echte Installation, weil genau das getestet wird: eine
 * Taxonomie-Abfrage über echte Terme. Legt eigene Pläne an und löscht sie
 * wieder; alles heißt "ZZ …".
 *
 * wp --require=<dbhost.php> eval-file \
 *    wp-content/plugins/mixmymeals/tools/planner-templates-test.php
 *
 * @package MixMyMeals
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Zähler in $GLOBALS und nicht auf Dateiebene: wp-cli führt eval-file in
 * einem Funktionsgeltungsbereich aus, `global $passed` greift dort also ins
 * Leere. Derselbe Stolperstein steht oben in diet-test.php — und ich bin
 * beim ersten Lauf trotzdem hineingelaufen: jede Zusicherung meldete "ok",
 * die Schlusszeile "0 bestanden, 0 fehlgeschlagen".
 */
$GLOBALS['MMM_TPL_PASS'] = 0;
$GLOBALS['MMM_TPL_FAIL'] = 0;

$created = array();

/**
 * Eine Zusicherung.
 *
 * @param bool   $ok    Ergebnis.
 * @param string $label Beschreibung.
 * @return void
 */
function mmm_tpl_assert( bool $ok, string $label ): void {
	if ( $ok ) {
		++$GLOBALS['MMM_TPL_PASS'];
		echo "ok   $label\n";
		return;
	}
	++$GLOBALS['MMM_TPL_FAIL'];
	echo "FAIL $label\n";
}

/**
 * Die Vorlagenliste mit genau den Argumenten des leeren Planers
 * (Mixmymeals_Shortcodes::planner_template_args()) — bis auf die Reihenfolge,
 * wenn $all gesetzt ist: dann alle Treffer statt drei zufälliger, damit „wird
 * angeboten“ nicht davon abhängt, was der Zufall gerade gezogen hat.
 *
 * @param bool $all Alle Treffer statt der drei angebotenen.
 * @return int[]
 */
function mmm_tpl_query( bool $all = false ): array {

	$args           = Mixmymeals_Shortcodes::planner_template_args();
	$args['fields'] = 'ids';

	if ( $all ) {
		$args['posts_per_page'] = -1;
	}

	return array_map( 'intval', get_posts( $args ) );
}

$saved_option = get_option( 'mixmymeals_planner_templates_require_category', '' );

echo "-- 1. Der Schalter ist ein Kontrollkästchen\n";

/*
 * Die laufende Instanz, nicht eine neue: der Konstruktor erwartet das
 * Hauptobjekt, und zwei Settings-Objekte würden ihre Hooks doppelt anmelden.
 */
$settings = Mixmymeals::instance()->settings;

mmm_tpl_assert(
	$settings instanceof Mixmymeals_Settings
	&& method_exists( $settings, 'planner_templates_require_category_callback' ),
	'der Sanitizer existiert'
);
mmm_tpl_assert(
	'on' === $settings->planner_templates_require_category_callback( 'on' )
	&& '' === $settings->planner_templates_require_category_callback( 'ja' )
	&& '' === $settings->planner_templates_require_category_callback( null ),
	'er lässt nur "on" durch'
);

echo "\n-- 2. Das Feld steht im Planer-Block des Plan-Tabs\n";

/*
 * Serverseitig statt im Browser: der Einstellungsbildschirm prüft nur, ob
 * das Feld registriert ist und als Kontrollkästchen herauskommt, und beides
 * steht hier im Code. Die Ausgabe hängt an einer `switch` auf die Feld-ID —
 * eine ID, die dort fehlt, fällt in den Vorgabezweig und erscheint als
 * Textfeld, ohne dass irgendetwas bricht.
 */
$fields = $settings->settings['mixmymeals-meal-plans']['fields'] ?? array();
$ids    = array_column( $fields, 'id' );
$field  = null;

foreach ( $fields as $candidate ) {
	if ( 'planner_templates_require_category' === ( $candidate['id'] ?? '' ) ) {
		$field = $candidate;
	}
}

mmm_tpl_assert( null !== $field, 'das Feld ist registriert' );
mmm_tpl_assert(
	array_search( 'planner_templates_require_category', $ids, true )
	> array_search( 'mealplans_group_planner', $ids, true ),
	'und steht unter der Überschrift „Planner“'
);

$api  = new Mixmymeals_Admin_API();
$html = (string) $api->display_field(
	array(
		'field'  => $field,
		'prefix' => 'mixmymeals_',
	),
	null,
	false
);

mmm_tpl_assert(
	false !== strpos( $html, 'type="checkbox"' ),
	'und wird als Kontrollkästchen ausgegeben'
);
mmm_tpl_assert(
	false !== strpos( $html, 'name="mixmymeals_planner_templates_require_category"' ),
	'unter dem Optionsnamen mit Präfix'
);

echo "\n-- 3. Drei Pläne, einer davon ohne Kategorie\n";

$new_term = wp_insert_term( 'ZZ Vorlagenkategorie', 'mmm_meal_plan_category' );
$term_id  = is_wp_error( $new_term ) ? 0 : (int) $new_term['term_id'];
mmm_tpl_assert( $term_id > 0, 'Kategorie angelegt' );

foreach ( array( 'ZZ Plan mit Kategorie A', 'ZZ Plan ohne Kategorie', 'ZZ Plan mit Kategorie B' ) as $plan_title ) {
	$plan_id   = wp_insert_post(
		array(
			'post_type'   => 'mmm_meal_plan',
			'post_status' => 'publish',
			'post_title'  => $plan_title,
		)
	);
	$created[] = (int) $plan_id;

	if ( false !== strpos( $plan_title, 'mit Kategorie' ) ) {
		wp_set_object_terms( (int) $plan_id, array( $term_id ), 'mmm_meal_plan_category' );
	}
}

$with_cat    = array( $created[0], $created[2] );
$without_cat = $created[1];

echo "\n-- 4. Schalter aus: der Plan ohne Kategorie ist dabei\n";

update_option( 'mixmymeals_planner_templates_require_category', '' );
$off = mmm_tpl_query( true );

mmm_tpl_assert( in_array( $without_cat, $off, true ), 'er wird angeboten' );

echo "\n-- 5. Schalter an: er fällt raus\n";

update_option( 'mixmymeals_planner_templates_require_category', 'on' );
$on = mmm_tpl_query( true );

mmm_tpl_assert( ! in_array( $without_cat, $on, true ), 'er wird nicht mehr angeboten' );
mmm_tpl_assert(
	in_array( $with_cat[0], $on, true ) && in_array( $with_cat[1], $on, true ),
	'die beiden mit Kategorie bleiben'
);

echo "\n-- 6. Der aussortierte Plan belegt keinen der drei Plätze\n";

/*
 * Der Kern der Entscheidung, die Klausel in die Abfrage zu legen statt
 * hinterher zu filtern: mit drei weiteren kategorisierten Plänen muss die
 * Liste drei lang bleiben und darf nicht auf zwei schrumpfen, weil der
 * neueste Plan keine Kategorie hat.
 */
foreach ( array( 'ZZ Plan mit Kategorie C', 'ZZ Plan mit Kategorie D' ) as $plan_title ) {
	$plan_id   = wp_insert_post(
		array(
			'post_type'   => 'mmm_meal_plan',
			'post_status' => 'publish',
			'post_title'  => $plan_title,
		)
	);
	$created[] = (int) $plan_id;
	wp_set_object_terms( (int) $plan_id, array( $term_id ), 'mmm_meal_plan_category' );
}

$full = mmm_tpl_query();
$all  = mmm_tpl_query( true );
mmm_tpl_assert( 3 === count( $full ), 'drei Vorlagen, nicht zwei (' . count( $full ) . ')' );
mmm_tpl_assert( ! in_array( $without_cat, $all, true ), 'und keine davon ohne Kategorie' );

echo "\n-- 7. Nicht gelistete Pläne bleiben draußen\n";

/*
 * Die Option darf die Unlisted-Klausel nicht verdrängen: exclude_unlisted()
 * schreibt in meta_query, die Option in tax_query — wenn eine die andere
 * überschriebe, träte ein zurückgezogener Plan wieder auf.
 */
$unlisted  = wp_insert_post(
	array(
		'post_type'   => 'mmm_meal_plan',
		'post_status' => 'publish',
		'post_title'  => 'ZZ Plan nicht gelistet',
	)
);
$created[] = (int) $unlisted;
wp_set_object_terms( (int) $unlisted, array( $term_id ), 'mmm_meal_plan_category' );
update_post_meta( (int) $unlisted, Mixmymeals_Post_Types::META_UNLISTED, '1' );

$with_unlisted = mmm_tpl_query( true );
mmm_tpl_assert(
	! in_array( (int) $unlisted, $with_unlisted, true ),
	'der zurückgezogene Plan fehlt trotz Kategorie'
);

echo "\n-- 8. Zufällig gewählt, nicht die neuesten drei\n";

$args = Mixmymeals_Shortcodes::planner_template_args();
mmm_tpl_assert( 'rand' === ( $args['orderby'] ?? '' ) && 3 === ( $args['posts_per_page'] ?? 0 ), 'drei Pläne in zufälliger Reihenfolge' );

/*
 * Vier gelistete Pläne mit Kategorie (der fünfte ist zurückgezogen), drei
 * angeboten: in 40 Abfragen müssen mehr als drei verschiedene vorkommen. Bei
 * „die neuesten drei“ wären es immer dieselben. Dass der Zufall 40-mal
 * dieselben drei aus vier zieht, hat die Wahrscheinlichkeit (1/4)^39.
 */
$seen = array();
for ( $i = 0; $i < 40; $i++ ) {
	foreach ( mmm_tpl_query() as $id ) {
		$seen[ $id ] = true;
	}
}
mmm_tpl_assert( count( $seen ) > 3, 'über mehrere Aufrufe kommen mehr als drei Pläne vor (' . count( $seen ) . ')' );

echo "\n=== Aufräumen ===\n";

foreach ( $created as $created_id ) {
	wp_delete_post( $created_id, true );
}
if ( $term_id ) {
	wp_delete_term( $term_id, 'mmm_meal_plan_category' );
}
update_option( 'mixmymeals_planner_templates_require_category', $saved_option );
echo 'entfernt: ' . count( $created ) . " Pläne, 1 Kategorie; Option zurückgesetzt\n";

echo "\n=== Ergebnis ===\n";
printf( "  %d bestanden, %d fehlgeschlagen\n", $GLOBALS['MMM_TPL_PASS'], $GLOBALS['MMM_TPL_FAIL'] );
