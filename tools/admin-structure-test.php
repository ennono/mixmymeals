<?php
/**
 * Prüft die Aufräumarbeiten am Adminbereich: das Menü, die eine Import-Seite
 * und die Adressen der Einstellungs-Reiter.
 *
 * Läuft gegen eine echte Installation, weil genau das geprüft wird — was
 * WordPress aus den Registrierungen macht. Legt nichts an und löscht nichts.
 *
 * wp --require=<dbhost.php> eval-file \
 *    wp-content/plugins/mixmymeals/tools/admin-structure-test.php
 *
 * @package MixMyMeals
 */

require_once ABSPATH . 'wp-admin/includes/admin.php';
$u = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
	)
);
if ( $u ) {
	wp_set_current_user( $u[0]->ID ); }

do_action( 'init' );
do_action( 'admin_menu' );

/*
 * Mixmymeals::$admin wird nur unter is_admin() gesetzt, und das ist unter
 * wp-cli false. Ohne das hier scheitert do_settings_fields() an einem
 * Callback auf null — ein Artefakt dieses Harness, kein Fehler im Plugin.
 */
Mixmymeals::instance()->admin = new Mixmymeals_Admin_API();

$fails = 0;
$pass  = 0;
$check = static function ( bool $ok, string $label ) use ( &$fails, &$pass ) {
	if ( $ok ) {
		++$pass;
		echo "ok   $label\n";
		return; }
	++$fails;
	echo "FAIL $label\n";
};

// --- Importseite ---
ob_start();
Mmm_Importer_UI::render_page();
$import = ob_get_clean();

$check( false !== strpos( $import, 'nav-tab-wrapper' ), 'Import: Reiterleiste vorhanden' );
$check( substr_count( $import, 'nav-tab' ) >= 3, 'Import: zwei Reiter' );
$check( false !== strpos( $import, 'tab=recipes' ), 'Import: Reiter Rezepte' );
$check( false !== strpos( $import, 'tab=ingredients' ), 'Import: Reiter Zutaten' );
$check( false !== strpos( $import, 'mixmymeals_import_recipes' ), 'Import: Rezept-Formular ist der Vorgabereiter' );
$check( false === strpos( $import, 'mixmymeals_import_ingredients' ), 'Import: der andere Reiter rendert nicht mit' );
$check( 1 === substr_count( $import, '<h1>' ), 'Import: genau eine Ueberschrift' );
$check( substr_count( $import, '<div' ) === substr_count( $import, '</div>' ), 'Import: div-Bilanz (' . substr_count( $import, '<div' ) . ' auf / ' . substr_count( $import, '</div>' ) . ' zu)' );

// Zweiter Reiter
$_GET['tab'] = 'ingredients'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- test harness.
ob_start();
Mmm_Importer_UI::render_page();
$import2 = ob_get_clean();
$check( false !== strpos( $import2, 'mixmymeals_import_ingredients' ), 'Import: Zutaten-Reiter rendert sein Formular' );
unset( $_GET['tab'] );

// --- Einstellungsseite ---
$settings = Mixmymeals::instance()->settings;
$settings->register_settings();
ob_start();
$settings->settings_page();
$settings_html = ob_get_clean();

$check( false !== strpos( $settings_html, 'Besucher' ), 'Einstellungen: Tab Besucher' );
$check( false !== strpos( $settings_html, 'mixmymeals-visitors' ), 'Einstellungen: dessen Schluessel' );
$check( false !== strpos( $settings_html, 'admin.php?page=mixmymeals_settings' ) || false !== strpos( $settings_html, 'page=mixmymeals_settings' ), 'Einstellungen: Reiterlinks' );
$check( false === strpos( $settings_html, 'options-general.php?page=mixmymeals' ), 'Einstellungen: keine alte URL mehr' );

// --- Der Helfer ---
$check( false !== strpos( Mixmymeals_Settings::settings_url(), 'admin.php' ), 'settings_url() zeigt auf admin.php' );
$check( false !== strpos( Mixmymeals_Settings::settings_url( 'mixmymeals-chips' ), 'tab=mixmymeals-chips' ), 'settings_url() nimmt einen Tab' );

// --- Das Menue ---
$equipment = get_post_type_object( 'mmm_equipment' );
$check( 'edit.php?post_type=mmm_recipe' === $equipment->show_in_menu, 'Menue: Kuechengeraete haengen unter Rezepte' );

$author = get_taxonomy( 'mmm_author' );
$check( false === $author->show_in_menu, 'Menue: Autoren stehen nicht zweimal' );
$check( true === $author->show_ui, 'Menue: ihr Bildschirm bleibt erreichbar' );
$check( in_array( 'mmm_meal_plan', $author->object_type, true ), 'Menue: Plaene behalten die Zuordnung' );

$subs = get_post_type_object( 'mmm_subscription' );
$check( 'mixmymeals_settings' === $subs->show_in_menu, 'Menue: Abos haengen unter MixMyMeals' );

$positions = array();
foreach ( array( 'mmm_recipe', 'mmm_ingredient', 'mmm_meal_plan' ) as $pt ) {
	$positions[ $pt ] = get_post_type_object( $pt )->menu_position;
}
$check( count( array_unique( $positions ) ) === 3, 'Menue: drei verschiedene Positionen (' . implode( ',', $positions ) . ')' );

$eq_cat = get_taxonomy( 'mmm_equipment_category' );
$rc_cat = get_taxonomy( 'mmm_recipe_category' );
$check( $eq_cat->labels->menu_name !== $rc_cat->labels->menu_name, 'Menue: zwei Kategorien unter Rezepte heissen verschieden' );

// --- Die Einstellungen ---
$tabs = array_keys( $settings->settings );
$check( in_array( 'mixmymeals-visitors', $tabs, true ), 'Einstellungen: der Besucher-Tab existiert' );

$ids = array();
foreach ( $settings->settings as $key => $tab ) {
	foreach ( $tab['fields'] as $f ) {
		if ( 'heading' === ( $f['type'] ?? '' ) ) {
			continue;
		}
		$ids[ $f['id'] ][] = $key;
	}
}
$doppelt = array_filter( $ids, static fn( $v ) => count( $v ) > 1 );
$check( ! $doppelt, 'Einstellungen: kein Feld steht in zwei Tabs' . ( $doppelt ? ' (' . implode( ',', array_keys( $doppelt ) ) . ')' : '' ) );

foreach ( array(
	'public_mode'         => 'mixmymeals-visitors',
	'notice_rating'       => 'mixmymeals-visitors',
	'photos_auto_approve' => 'mixmymeals-visitors',
	'enable_cleanup'      => 'mixmymeals-general',
) as $field => $want ) {
	$check( ( $ids[ $field ][0] ?? '' ) === $want, "Einstellungen: $field liegt in $want" );
}

echo "\n$pass bestanden, $fails fehlgeschlagen\n";
