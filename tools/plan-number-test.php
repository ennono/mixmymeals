<?php
/**
 * Prüft die Plannummer: Pläne, die aus dem Planer gespeichert werden, heißen
 * nach ihrer Post-ID ("Essensplan #4812", veröffentlicht mit passendem Slug,
 * als Entwurf mit dem Slug erst beim Veröffentlichen), und der Hero zeigt
 * die Nummer über dem Titel — außer der Titel nennt sie schon.
 *
 * Läuft gegen eine echte Installation (der Speichern-Handler, echte Posts).
 * Legt eigene Posts an und löscht sie wieder; alles heißt "ZZ …" oder trägt
 * die Nummer im Titel.
 *
 * wp --require=<dbhost.php> eval-file \
 *    wp-content/plugins/mixmymeals/tools/plan-number-test.php
 *
 * @package MixMyMeals
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Zähler in $GLOBALS: eval-file läuft in einem Funktionsgeltungsbereich.
$GLOBALS['MMM_PNT_PASS'] = 0;
$GLOBALS['MMM_PNT_FAIL'] = 0;

/**
 * Eine Zusicherung.
 *
 * @param bool   $ok    Ergebnis.
 * @param string $label Beschreibung.
 * @return void
 */
function mmm_pnt_assert( bool $ok, string $label ): void {
	if ( $ok ) {
		++$GLOBALS['MMM_PNT_PASS'];
		echo "ok   $label\n";
		return;
	}
	++$GLOBALS['MMM_PNT_FAIL'];
	echo "FAIL $label\n";
}

/**
 * Speichert einen Plan, wie der Planer es tut: über den AJAX-Handler.
 *
 * @param int $recipe_id Ein veröffentlichtes Rezept.
 * @return int Plan-ID oder 0.
 */
function mmm_pnt_save( int $recipe_id ): int {

	$_POST = array(
		'nonce' => wp_create_nonce( 'mmm_frontend_nonce' ),
		'items' => array(
			array(
				'recipe_id' => $recipe_id,
				'servings'  => 2,
				'type'      => '',
				'day'       => 1,
			),
		),
	);

	$die = static function () {
		return static function () {
			throw new RuntimeException( 'mmm_pnt_die' );
		};
	};

	add_filter( 'wp_doing_ajax', '__return_true' );
	add_filter( 'wp_die_ajax_handler', $die );
	ob_start();
	try {
		do_action( 'wp_ajax_nopriv_mmm_create_meal_plan' );
	} catch ( RuntimeException $e ) {
		unset( $e );
	}
	$out = (string) ob_get_clean();
	remove_filter( 'wp_die_ajax_handler', $die );
	remove_filter( 'wp_doing_ajax', '__return_true' );
	$_POST = array();

	$json = json_decode( $out, true );

	return (int) ( $json['data']['meal_plan_id'] ?? 0 );
}

/**
 * Der Hero eines Plans, wie die Einzelseite ihn ausgibt.
 *
 * @param int $plan_id Meal plan post ID.
 * @return string
 */
function mmm_pnt_hero( int $plan_id ): string {
	$GLOBALS['post'] = get_post( $plan_id );
	setup_postdata( $GLOBALS['post'] );
	$html = do_shortcode( '[mmm_meal_plan_hero]' );
	wp_reset_postdata();
	return $html;
}

/**
 * Merkt sich einen Plan samt Mahlzeiten fürs Aufräumen.
 *
 * @param int   $plan_id Plan-ID.
 * @param array $created Liste der zu löschenden IDs (by reference).
 * @return void
 */
function mmm_pnt_track( int $plan_id, array &$created ): void {
	foreach ( get_children( array( 'post_parent' => $plan_id, 'post_type' => 'mmm_meal', 'fields' => 'ids' ) ) as $meal_id ) {
		$created[] = (int) $meal_id;
	}
	$created[] = $plan_id;
}

$created = array();
$saved   = array(
	'mixmymeals_public_mode' => get_option( 'mixmymeals_public_mode', '' ),
	'mixmymeals_save_meal_plans_published_by_visitors_as_draft' => get_option( 'mixmymeals_save_meal_plans_published_by_visitors_as_draft', '' ),
	'mixmymeals_published_meal_plans_per_hour_limit'            => get_option( 'mixmymeals_published_meal_plans_per_hour_limit', 0 ),
	'mixmymeals_plan_subscribe_mode'                            => get_option( 'mixmymeals_plan_subscribe_mode', '' ),
);

wp_set_current_user( 0 );
update_option( 'mixmymeals_public_mode', 'on' );
update_option( 'mixmymeals_save_meal_plans_published_by_visitors_as_draft', '' );
update_option( 'mixmymeals_published_meal_plans_per_hour_limit', 0 );
update_option( 'mixmymeals_plan_subscribe_mode', 'off' );

$recipe_id = (int) wp_insert_post(
	array(
		'post_type'   => 'mmm_recipe',
		'post_title'  => 'ZZ Number Recipe',
		'post_status' => 'publish',
	)
);
$created[] = $recipe_id;

echo "-- 1. Die Nummer\n";

mmm_pnt_assert( '#4812' === Mixmymeals_Meal_Plan::number_label( 4812 ), 'Nummer: "#" und die ID, ohne Tausenderpunkt' );
mmm_pnt_assert( Mixmymeals_Meal_Plan::title_states_number( 4812, 'Essensplan #4812' ), 'Titel mit der Nummer wird erkannt' );
mmm_pnt_assert( ! Mixmymeals_Meal_Plan::title_states_number( 4812, 'Essensplan #48120' ), '"#48120" ist nicht "#4812"' );
mmm_pnt_assert( ! Mixmymeals_Meal_Plan::title_states_number( 4812, 'Vegane Woche 4812' ), 'Ohne "#" ist es keine Nummer' );

echo "-- 2. Gespeichert aus dem Planer, veröffentlicht\n";

$plan_id = mmm_pnt_save( $recipe_id );
mmm_pnt_track( $plan_id, $created );
/* translators: test only. */
$expected = sprintf( __( 'Meal Plan %s', 'mixmymeals' ), '#' . $plan_id );

mmm_pnt_assert( $plan_id > 0 && 'publish' === get_post_status( $plan_id ), 'Plan veröffentlicht angelegt' );
mmm_pnt_assert( get_the_title( $plan_id ) === $expected, "Titel ist \"$expected\"" );
mmm_pnt_assert( get_post_field( 'post_name', $plan_id ) === sanitize_title( $expected ), 'Slug folgt dem Titel: ' . get_post_field( 'post_name', $plan_id ) );

$hero = mmm_pnt_hero( $plan_id );
mmm_pnt_assert( false !== strpos( $hero, esc_html( $expected ) ), 'Hero zeigt den Titel mit der Nummer' );
mmm_pnt_assert( false === strpos( $hero, 'mmm-plan-hero__number' ), 'Keine zweite Nummer über einem Titel, der sie nennt' );

echo "-- 3. Umbenannt: die Nummer steht über dem Titel\n";

wp_update_post( array( 'ID' => $plan_id, 'post_title' => 'ZZ Vegane Woche' ) );
$hero = mmm_pnt_hero( $plan_id );
mmm_pnt_assert(
	1 === preg_match( '#<p class="mmm-plan-hero__number"><span class="screen-reader-text">[^<]+</span>\#' . $plan_id . '</p>\s*<h1>#', $hero ),
	'Hero: "#ID" direkt über der Überschrift, mit Text für Screenreader'
);

echo "-- 4. Gespeichert als Entwurf\n";

update_option( 'mixmymeals_save_meal_plans_published_by_visitors_as_draft', 'on' );
$draft_id = mmm_pnt_save( $recipe_id );
mmm_pnt_track( $draft_id, $created );
$expected = sprintf( __( 'Meal Plan %s', 'mixmymeals' ), '#' . $draft_id );

mmm_pnt_assert( $draft_id > 0 && 'draft' === get_post_status( $draft_id ), 'Plan als Entwurf angelegt' );
mmm_pnt_assert( get_the_title( $draft_id ) === $expected, "Titel ist \"$expected\"" );
mmm_pnt_assert( '' === get_post_field( 'post_name', $draft_id ), 'Entwurf ohne Slug' );

wp_update_post( array( 'ID' => $draft_id, 'post_status' => 'publish' ) );
mmm_pnt_assert( get_post_field( 'post_name', $draft_id ) === sanitize_title( $expected ), 'Beim Veröffentlichen: Slug aus dem Titel — ' . get_post_field( 'post_name', $draft_id ) );

echo "-- 5. Aufräumen\n";

foreach ( array_reverse( $created ) as $created_id ) {
	wp_delete_post( $created_id, true );
}
foreach ( $saved as $opt => $value ) {
	update_option( $opt, $value );
}

mmm_pnt_assert( true, 'Testdaten entfernt, Optionen zurückgesetzt' );

echo "\n=== Ergebnis ===\n";
echo $GLOBALS['MMM_PNT_PASS'] . ' bestanden, ' . $GLOBALS['MMM_PNT_FAIL'] . " fehlgeschlagen\n";
