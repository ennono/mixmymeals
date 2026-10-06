<?php
/**
 * Prüft die Aufräumarbeiten am Adminbereich: das Menü und die Reihenfolge
 * seiner Einträge, die eine Import-Seite, die Reiter der Einstellungen und
 * ihre Adressen.
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

/*
 * Mixmymeals::$admin wird nur unter is_admin() gesetzt, und das ist unter
 * wp-cli false. Ohne das hier scheitert do_settings_fields() an einem
 * Callback auf null — ein Artefakt dieses Harness, kein Fehler im Plugin.
 */
Mixmymeals::instance()->admin = new Mixmymeals_Admin_API();

/*
 * Das echte Menü, nicht ein nachgebautes: wp-admin/menu.php legt die
 * Beitragstypen und Taxonomien an, feuert admin_menu und sortiert danach —
 * genau das, wovon die Reihenfolge abhängt. Es braucht dafür nur die
 * Globals, die admin.php sonst setzt.
 */
global $menu, $submenu, $_wp_menu_nopriv, $_wp_submenu_nopriv, $_registered_pages, $_parent_pages, $admin_page_hooks, $_wp_real_parent_file, $pagenow, $self; // phpcs:ignore
$pagenow = 'index.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test harness.
$self    = 'index.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test harness.
set_current_screen( 'dashboard' );
require ABSPATH . 'wp-admin/menu.php';

/**
 * Die Slugs eines Untermenüs, `&amp;` aufgelöst.
 *
 * @param string $parent_slug Slug des Hauptpunkts.
 * @return string[]
 */
$subs_of = static function ( string $parent_slug ) use ( &$submenu ): array {
	return array_map(
		static fn( $item ) => str_replace( '&amp;', '&', (string) $item[2] ),
		array_values( (array) ( $submenu[ $parent_slug ] ?? array() ) )
	);
};

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

// --- Der Import ist ausgebaut ---
$check( ! class_exists( 'Mmm_Importer_UI' ), 'Import: die Klasse ist weg' );
$check( ! has_action( 'admin_post_mixmymeals_import_recipes' ), 'Import: kein Formular-Handler mehr' );
$check( ! isset( rest_get_server()->get_routes()['/mixmymeals/v1/import-reference'] ), 'Import: keine REST-Route mehr' );

// --- Einstellungsseite ---
$settings = Mixmymeals::instance()->settings;
$settings->register_settings();
ob_start();
$settings->settings_page();
$settings_html = ob_get_clean();

$check( false !== strpos( $settings_html, 'Sprache' ), 'Einstellungen: Tab Sprache' );
$check( false === strpos( $settings_html, 'tab=mixmymeals-visitors' ), 'Einstellungen: kein Besucher-Tab mehr' );
$check( false !== strpos( $settings_html, 'admin.php?page=mixmymeals_settings' ) || false !== strpos( $settings_html, 'page=mixmymeals_settings' ), 'Einstellungen: Reiterlinks' );
$check( false === strpos( $settings_html, 'options-general.php?page=mixmymeals' ), 'Einstellungen: keine alte URL mehr' );

// --- Der Helfer ---
$check( false !== strpos( Mixmymeals_Settings::settings_url(), 'admin.php' ), 'settings_url() zeigt auf admin.php' );
$check( false !== strpos( Mixmymeals_Settings::settings_url( 'mixmymeals-language' ), 'tab=mixmymeals-language' ), 'settings_url() nimmt einen Tab' );

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
foreach ( array( 'mixmymeals-visitors', 'mixmymeals-subscriptions', 'mixmymeals-chips' ) as $gone ) {
	$check( ! in_array( $gone, $tabs, true ), "Einstellungen: $gone gibt es nicht mehr" );
}

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
	'public_mode'           => 'mixmymeals-meal-plans',
	'notice_plan'           => 'mixmymeals-meal-plans',
	'photos_auto_approve'   => 'mixmymeals-meal-plans',
	'notice_photo'          => 'mixmymeals-meal-plans',
	'notice_rating'         => 'mixmymeals-recipes',
	'notice_subscribe'      => 'mixmymeals-email',
	'subscriptions_enabled' => 'mixmymeals-email',
	'mail_from_name'        => 'mixmymeals-email',
	'chip_text_servings'    => 'mixmymeals-language',
	'enable_cleanup'        => 'mixmymeals-general',
) as $field => $want ) {
	$check( ( $ids[ $field ][0] ?? '' ) === $want, "Einstellungen: $field liegt in $want" );
}

// --- Reihenfolge der Hauptpunkte ---
$tops = array_values( array_map( static fn( $item ) => (string) $item[2], $menu ) );
$at   = static fn( string $slug ) => array_search( $slug, $tops, true );

$check( $at( 'edit.php?post_type=mmm_recipe' ) < $at( 'edit.php?post_type=mmm_ingredient' ), 'Reihenfolge: Rezepte vor Zutaten' );
$check( $at( 'edit.php?post_type=mmm_ingredient' ) < $at( 'edit.php?post_type=mmm_meal_plan' ), 'Reihenfolge: Zutaten vor Essensplaenen' );
$check( $at( 'edit.php?post_type=mmm_meal_plan' ) < $at( 'mixmymeals_settings' ), 'Reihenfolge: MixMyMeals nach den Inhalten' );
$check( $at( 'mixmymeals_settings' ) < $at( 'themes.php' ), 'Reihenfolge: MixMyMeals vor Design' );

if ( false !== $at( 'mixmymeals-social' ) ) {
	$check(
		$at( 'edit.php?post_type=mmm_meal_plan' ) < $at( 'mixmymeals-social' ) && $at( 'mixmymeals-social' ) < $at( 'mixmymeals_settings' ),
		'Reihenfolge: Social Media zwischen Essensplaenen und MixMyMeals'
	);
	$check( $at( 'edit.php?post_type=mmm_recipe' ) - $at( 'edit-comments.php' ) === 1, 'Reihenfolge: nichts zwischen Kommentaren und Rezepten' );
} else {
	echo "--   Social-Add-on nicht aktiv, seine Position ungeprueft\n";
}

// --- Reihenfolge in den Untermenues ---
$check(
	$subs_of( 'edit.php?post_type=mmm_recipe' ) === array(
		'edit.php?post_type=mmm_recipe',
		'post-new.php?post_type=mmm_recipe',
		'edit-tags.php?taxonomy=mmm_recipe_category&post_type=mmm_recipe',
		'edit-tags.php?taxonomy=mmm_recipe_tag&post_type=mmm_recipe',
		'edit-tags.php?taxonomy=mmm_author&post_type=mmm_recipe',
		'edit.php?post_type=mmm_equipment',
		'edit-tags.php?taxonomy=mmm_equipment_category&post_type=mmm_equipment',
	),
	'Untermenue Rezepte: Liste, Neu, Kategorien, Tags, Autoren, Geraete, Geraetekategorien (' . implode( ' | ', $subs_of( 'edit.php?post_type=mmm_recipe' ) ) . ')'
);
$check(
	$subs_of( 'edit.php?post_type=mmm_ingredient' ) === array(
		'edit.php?post_type=mmm_ingredient',
		'post-new.php?post_type=mmm_ingredient',
		'edit-tags.php?taxonomy=mmm_ingredient_category&post_type=mmm_ingredient',
		'edit-tags.php?taxonomy=mmm_diet&post_type=mmm_ingredient',
	),
	'Untermenue Zutaten: Liste, Neu, Kategorien, Ernaehrungsformen'
);
$check(
	$subs_of( 'edit.php?post_type=mmm_meal_plan' ) === array(
		'edit.php?post_type=mmm_meal_plan',
		'post-new.php?post_type=mmm_meal_plan',
		'edit-tags.php?taxonomy=mmm_meal_plan_category&post_type=mmm_meal_plan',
		'edit-tags.php?taxonomy=mmm_meal_plan_tag&post_type=mmm_meal_plan',
		'mixmymeals_photos',
	),
	'Untermenue Essensplaene: Liste, Neu, Kategorien, Tags, Fotos (' . implode( ' | ', $subs_of( 'edit.php?post_type=mmm_meal_plan' ) ) . ')'
);
$check(
	$subs_of( 'mixmymeals_settings' ) === array(
		'mixmymeals_settings',
		'edit.php?post_type=mmm_subscription',
	),
	'Untermenue MixMyMeals: Einstellungen, Abos (' . implode( ' | ', $subs_of( 'mixmymeals_settings' ) ) . ')'
);

/*
 * Der Hauptpunkt verlinkt auf seinen ersten Unterpunkt. Stand dort Abos,
 * öffnete ein Klick auf „MixMyMeals“ die Abo-Liste.
 */
$check( 'mixmymeals_settings' === ( $subs_of( 'mixmymeals_settings' )[0] ?? '' ), 'MixMyMeals oeffnet die Einstellungen' );

$check( false === get_taxonomy( 'mmm_equipment_category' )->show_in_menu, 'Geraetekategorien: nur der eine Eintrag von Hand' );

// Die Taxonomie-Eintraege tragen &amp; wie die von core, sonst markiert das
// Menue den offenen Eintrag nicht.
foreach ( (array) $submenu['edit.php?post_type=mmm_recipe'] as $item ) {
	if ( 0 === strpos( (string) $item[2], 'edit-tags.php' ) ) {
		$check( false !== strpos( (string) $item[2], '&amp;' ), 'Untermenue-Slug kodiert: ' . $item[2] );
	}
}

// Auf dem Bildschirm der Geraetekategorien bleibt Rezepte offen.
$GLOBALS['current_screen']           = WP_Screen::get( 'edit-mmm_equipment_category' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test harness.
$GLOBALS['current_screen']->taxonomy = 'mmm_equipment_category';
$check( 'edit.php?post_type=mmm_recipe' === apply_filters( 'parent_file', 'edit.php?post_type=mmm_equipment' ), 'Geraetekategorien: Rezepte bleibt markiert' );
set_current_screen( 'dashboard' );

// --- Reiter: Reihenfolge und Zuordnung ---
$host_tabs = array(
	'mixmymeals-general',
	'mixmymeals-recipes',
	'mixmymeals-ingredients',
	'mixmymeals-meal-plans',
	'mixmymeals-email',
	'mixmymeals-language',
);
$check( array_slice( $tabs, 0, count( $host_tabs ) ) === $host_tabs, 'Reiter: Reihenfolge des Hosts (' . implode( ', ', $tabs ) . ')' );
$check( ! in_array( 'mixmymeals-equipment', $tabs, true ), 'Reiter: Kuechengeraete stehen unter Rezepte, kein eigener Reiter' );

foreach ( array(
	'author_slug'          => 'mixmymeals-recipes',
	'equipment_slug'       => 'mixmymeals-recipes',
	'affiliate_disclosure' => 'mixmymeals-recipes',
	'meal_slug'            => 'mixmymeals-meal-plans',
	'currency'             => 'mixmymeals-general',
) as $field => $want ) {
	$check( ( $ids[ $field ][0] ?? '' ) === $want, "Einstellungen: $field liegt in $want" );
}

$slug_in_general = array_filter(
	$settings->settings['mixmymeals-general']['fields'],
	static fn( $f ) => 'slug_callback' === ( $f['callback'][1] ?? '' )
);
$check( ! $slug_in_general, 'Einstellungen: unter Allgemein steht kein Slug mehr' );

// --- Reiter: Aufloesung ---
$check( 'mixmymeals-recipes' === $settings->resolve_tab( 'mixmymeals-equipment' ), 'Reiter: alter Schluessel Kuechengeraete fuehrt zu Rezepte' );
$check( 'mixmymeals-general' === $settings->resolve_tab( 'gibt-es-nicht' ), 'Reiter: unbekannter Schluessel fuehrt zum ersten Reiter' );
$check( 'mixmymeals-general' === $settings->resolve_tab( '' ), 'Reiter: ohne Schluessel der erste' );
$check( 'mixmymeals-language' === $settings->resolve_tab( 'mixmymeals-language' ), 'Reiter: bekannter Schluessel bleibt' );
$check( 'mixmymeals-meal-plans' === $settings->resolve_tab( 'mixmymeals-visitors' ), 'Reiter: Besucher fuehrt zu Essensplaene' );
$check( 'mixmymeals-email' === $settings->resolve_tab( 'mixmymeals-subscriptions' ), 'Reiter: Abos fuehrt zu E-Mail & Abos' );
$check( 'mixmymeals-language' === $settings->resolve_tab( 'mixmymeals-chips' ), 'Reiter: Chips fuehrt zu Sprache' );

// Chips sind eine Sektion im Reiter Sprache, eine Ebene ueber ihren Gruppen.
$lang_first = $settings->settings['mixmymeals-language']['fields'][0] ?? array();
$check( 'heading' === ( $lang_first['type'] ?? '' ) && 2 === ( $lang_first['level'] ?? 0 ), 'Sprache: beginnt mit der Sektion Chips' );

// Die Fotoseite leitet auf sich selbst unter Essensplaene zurueck.
$check( false !== strpos( Mixmymeals_Photos::admin_page_url(), 'post_type=mmm_meal_plan&page=mixmymeals_photos' ), 'Fotos: Ruecksprung unter Essensplaene' );

/*
 * Ein Link ohne Nonce — so wie der Unterpunkt „Einstellungen“ des Add-ons —
 * markiert den Reiter, den er öffnet, und rendert dessen Felder.
 */
$_GET['tab']                     = 'mixmymeals-equipment'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- test harness.
$GLOBALS['wp_settings_sections'] = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test harness.
$GLOBALS['wp_settings_fields']   = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test harness.
$settings->register_settings();
ob_start();
$settings->settings_page();
$old_tab_html = ob_get_clean();
unset( $_GET['tab'] );

$check( (bool) preg_match( '/tab=mixmymeals-recipes"[^>]*class="nav-tab nav-tab-active"/', $old_tab_html ), 'Reiter: alter Link markiert Rezepte' );
$check( 1 === substr_count( $old_tab_html, 'nav-tab-active' ), 'Reiter: genau einer markiert' );
$check( false !== strpos( $old_tab_html, 'mixmymeals_affiliate_disclosure' ), 'Reiter: und zeigt die Kuechengeraete-Felder' );
$check( false !== strpos( $old_tab_html, 'name="tab" value="mixmymeals-recipes"' ), 'Reiter: Speichern bleibt im aufgeloesten Reiter' );
$check( false === strpos( $old_tab_html, 'settings_nonce' ), 'Reiter: Links ohne Nonce' );

echo "\n$pass bestanden, $fails fehlgeschlagen\n";
