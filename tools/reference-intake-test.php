<?php
/**
 * Prüft die einstellbaren Referenzmengen: Optionen vor dem Filter, leer und
 * Unsinn fallen auf den EU-Wert zurück, die Prozente und Balken folgen, und
 * die Fußnoten nennen den durchschnittlichen Erwachsenen nur, solange die
 * Werte seine sind.
 *
 * Legt keine Posts an. Die Optionen werden gesichert und am Ende
 * zurückgeschrieben.
 *
 * wp --require=<dbhost.php> eval-file \
 *    wp-content/plugins/mixmymeals/tools/reference-intake-test.php
 *
 * @package MixMyMeals
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$GLOBALS['MMM_RI_PASS'] = 0;
$GLOBALS['MMM_RI_FAIL'] = 0;

/**
 * Eine Zusicherung.
 *
 * @param bool   $ok    Ergebnis.
 * @param string $label Beschreibung.
 * @return void
 */
function mmm_ri_assert( bool $ok, string $label ): void {
	++$GLOBALS[ $ok ? 'MMM_RI_PASS' : 'MMM_RI_FAIL' ];
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
}

$mmm_ri_backup = array();
foreach ( Mixmymeals_Meal_Plan::NUTRITION_KEYS as $mmm_key ) {
	$mmm_ri_backup[ $mmm_key ] = get_option( Mixmymeals_Meal_Plan::REFERENCE_INTAKE_OPTION_PREFIX . $mmm_key, null );
	delete_option( Mixmymeals_Meal_Plan::REFERENCE_INTAKE_OPTION_PREFIX . $mmm_key );
}

try {
	echo "=== Standard ===\n";
	mmm_ri_assert( Mixmymeals_Meal_Plan::REFERENCE_INTAKE == Mixmymeals_Meal_Plan::reference_intake(), 'ohne Optionen: die EU-Werte' );
	mmm_ri_assert( Mixmymeals_Meal_Plan::reference_intake_is_default(), 'und als Standard erkannt' );
	mmm_ri_assert( false !== strpos( Mixmymeals_Meal_Plan::reference_intake_note(), '2 000' ), 'Fußnote nennt 2 000 kcal' );

	echo "\n=== Eigene Werte ===\n";
	update_option( 'mixmymeals_reference_intake_protein', '100' );
	update_option( 'mixmymeals_reference_intake_calories', '2500' );
	update_option( 'mixmymeals_reference_intake_salt', '' );
	$ri = Mixmymeals_Meal_Plan::reference_intake();
	mmm_ri_assert( 100.0 === $ri['protein'] && 2500.0 === $ri['calories'], 'gesetzte Werte gelten' );
	mmm_ri_assert( 6.0 === $ri['salt'], 'leeres Feld: EU-Wert' );
	mmm_ri_assert( ! Mixmymeals_Meal_Plan::reference_intake_is_default(), 'nicht mehr Standard' );

	$pct = Mixmymeals_Meal_Plan::nutrition_percentages( array( 'protein' => 60.0, 'calories' => 1250.0, 'salt' => 3.0 ) );
	mmm_ri_assert( 60 === (int) round( $pct['protein'] ) && 50 === (int) round( $pct['calories'] ) && 50 === (int) round( $pct['salt'] ), 'Prozente rechnen mit den eigenen Werten' );
	mmm_ri_assert( 60 === Mixmymeals_Meal_Plan::nutrition_bar( 'protein', $pct['protein'] )['fill'], 'Balken folgt' );

	$note = Mixmymeals_Meal_Plan::reference_intake_note();
	mmm_ri_assert( false === strpos( $note, '2 000' ) && false !== strpos( $note, number_format_i18n( 2500 ) ), 'Fußnote nennt die eigene Energie: ' . $note );
	$rating_note = Mixmymeals_Meal_Plan::day_rating_note();
	mmm_ri_assert( '' === $rating_note || false === strpos( $rating_note, '2 000' ), 'Bewertungsnotiz ohne „durchschnittlichen Erwachsenen (2 000 kcal)“' );

	add_filter( 'mixmymeals_reference_intake', $mmm_f = static function ( $v ) { $v['fiber'] = 40; return $v; } );
	$ri = Mixmymeals_Meal_Plan::reference_intake();
	mmm_ri_assert( 40.0 === (float) $ri['fiber'] && 100.0 === $ri['protein'], 'Filter sieht die Einstellungen und kann ergänzen' );
	remove_filter( 'mixmymeals_reference_intake', $mmm_f );

	echo "\n=== Bereinigung ===\n";
	mmm_ri_assert( '2.5' === Mixmymeals_Meal_Plan::sanitize_reference_intake( '2,5' ), 'Dezimalkomma' );
	mmm_ri_assert( '' === Mixmymeals_Meal_Plan::sanitize_reference_intake( '0' ), '0 → leer' );
	mmm_ri_assert( '' === Mixmymeals_Meal_Plan::sanitize_reference_intake( '-5' ), 'negativ → leer' );
	mmm_ri_assert( '' === Mixmymeals_Meal_Plan::sanitize_reference_intake( 'abc' ), 'Text → leer' );

	update_option( 'mixmymeals_reference_intake_fat', '-3' );
	mmm_ri_assert( 70.0 === Mixmymeals_Meal_Plan::reference_intake()['fat'], 'Unsinn in der Option: EU-Wert' );
} finally {
	foreach ( $mmm_ri_backup as $mmm_key => $mmm_value ) {
		if ( null === $mmm_value ) {
			delete_option( Mixmymeals_Meal_Plan::REFERENCE_INTAKE_OPTION_PREFIX . $mmm_key );
		} else {
			update_option( Mixmymeals_Meal_Plan::REFERENCE_INTAKE_OPTION_PREFIX . $mmm_key, $mmm_value );
		}
	}
}

echo "\n" . $GLOBALS['MMM_RI_PASS'] . ' ok, ' . $GLOBALS['MMM_RI_FAIL'] . " fehlgeschlagen\n";
