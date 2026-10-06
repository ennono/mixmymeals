<?php
/**
 * Prüft das Abo-Angebot beim Speichern eines Plans: die drei Modi (aus,
 * möglich, Pflicht), den Hinweistext als Checkbox oder Text, den Speichern-
 * Handler selbst in jedem Modus und Mixmymeals_Subscriptions::
 * create_pending(), das auch das eigenständige Abo-Formular trägt.
 *
 * Läuft gegen eine echte Installation (Einstellungen, ein echter Plan-Post,
 * ein echter Subscription-Post). Legt eigene Posts an und löscht sie wieder;
 * alles heißt "ZZ …".
 *
 * wp --require=<dbhost.php> eval-file \
 *    wp-content/plugins/mixmymeals/tools/plan-subscribe-test.php
 *
 * @package MixMyMeals
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Zähler in $GLOBALS: eval-file läuft in einem Funktionsgeltungsbereich.
$GLOBALS['MMM_PST_PASS'] = 0;
$GLOBALS['MMM_PST_FAIL'] = 0;

/**
 * Eine Zusicherung.
 *
 * @param bool   $ok    Ergebnis.
 * @param string $label Beschreibung.
 * @return void
 */
function mmm_pst_assert( bool $ok, string $label ): void {
	if ( $ok ) {
		++$GLOBALS['MMM_PST_PASS'];
		echo "ok   $label\n";
		return;
	}
	++$GLOBALS['MMM_PST_FAIL'];
	echo "FAIL $label\n";
}

$created = array();

// Gesicherter Ausgangszustand der angefassten Optionen.
$saved = array(
	'mixmymeals_subscriptions_enabled' => get_option( 'mixmymeals_subscriptions_enabled', '' ),
	'mixmymeals_plan_subscribe_mode'   => get_option( 'mixmymeals_plan_subscribe_mode', '' ),
	'mixmymeals_subscription_schedule' => get_option( 'mixmymeals_subscription_schedule', array() ),
	'mixmymeals_public_mode'           => get_option( 'mixmymeals_public_mode', '' ),
	'mixmymeals_save_meal_plans_published_by_visitors_as_draft' => get_option( 'mixmymeals_save_meal_plans_published_by_visitors_as_draft', '' ),
	'mixmymeals_published_meal_plans_per_hour_limit'            => get_option( 'mixmymeals_published_meal_plans_per_hour_limit', 0 ),
	'mixmymeals_plan_subscribe_notice'                          => get_option( 'mixmymeals_plan_subscribe_notice', null ),
	'mixmymeals_plan_subscribe_notice_checkbox'                 => get_option( 'mixmymeals_plan_subscribe_notice_checkbox', null ),
);

echo "-- 1. Die drei Modi\n";

mmm_pst_assert(
	array_keys( Mixmymeals_Subscriptions::save_offer_modes() ) === array( 'off', 'optional', 'required' ),
	'save_offer_modes() kennt genau off/optional/required'
);

$settings = Mixmymeals::instance()->settings;

mmm_pst_assert(
	$settings instanceof Mixmymeals_Settings
	&& method_exists( $settings, 'plan_subscribe_mode_callback' ),
	'der Sanitizer existiert'
);

mmm_pst_assert(
	'required' === $settings->plan_subscribe_mode_callback( 'required' )
	&& 'optional' === $settings->plan_subscribe_mode_callback( 'optional' )
	&& 'off' === $settings->plan_subscribe_mode_callback( 'unsinn' )
	&& 'off' === $settings->plan_subscribe_mode_callback( null ),
	'er lässt nur die drei bekannten Werte durch, sonst "off"'
);

foreach ( array( 'hint', 'choice' ) as $legacy ) {
	update_option( 'mixmymeals_plan_subscribe_mode', $legacy );
	mmm_pst_assert(
		Mixmymeals_Subscriptions::SAVE_OFFER_OPTIONAL === Mixmymeals_Subscriptions::save_offer_mode(),
		"ein gespeichertes \"$legacy\" aus der Vorversion liest sich als \"optional\""
	);
}

echo "-- 2. save_offer_mode_effective() faltet die Vorbedingungen ein\n";

update_option( 'mixmymeals_subscriptions_enabled', '' );
update_option( 'mixmymeals_plan_subscribe_mode', 'required' );

mmm_pst_assert(
	Mixmymeals_Subscriptions::SAVE_OFFER_OFF === Mixmymeals_Subscriptions::save_offer_mode_effective(),
	'aus, solange Abos insgesamt aus sind — obwohl die Option "required" sagt'
);

update_option( 'mixmymeals_subscriptions_enabled', 'on' );
update_option( 'mixmymeals_subscription_schedule', array() );

mmm_pst_assert(
	Mixmymeals_Subscriptions::SAVE_OFFER_OFF === Mixmymeals_Subscriptions::save_offer_mode_effective(),
	'aus, solange keine Sende-Regel existiert'
);

update_option(
	'mixmymeals_subscription_schedule',
	array(
		array(
			'trigger'  => 'before_start',
			'time'     => '08:00',
			'subject'  => 'ZZ Test',
			'template' => 'day.html',
		),
	)
);

mmm_pst_assert(
	Mixmymeals_Subscriptions::SAVE_OFFER_REQUIRED === Mixmymeals_Subscriptions::save_offer_mode_effective(),
	'folgt der Option, sobald beide Vorbedingungen erfüllt sind'
);

echo "-- 2b. Der Hinweistext\n";

delete_option( 'mixmymeals_plan_subscribe_notice' );
delete_option( 'mixmymeals_plan_subscribe_notice_checkbox' );

mmm_pst_assert(
	Mixmymeals_Subscriptions::consent_text() === Mixmymeals_Subscriptions::save_offer_notice(),
	'ohne eigenen Text steht der Einwilligungssatz des Abo-Formulars da'
);
mmm_pst_assert(
	Mixmymeals_Subscriptions::save_offer_notice_is_checkbox(),
	'ungespeichert ist der Hinweis eine Checkbox'
);

update_option( 'mixmymeals_plan_subscribe_notice', 'ZZ Hinweis <a href="https://example.invalid/">Link</a>' );
update_option( 'mixmymeals_plan_subscribe_notice_checkbox', '' );

mmm_pst_assert(
	str_starts_with( Mixmymeals_Subscriptions::save_offer_notice(), 'ZZ Hinweis' ),
	'ein eigener Text ersetzt ihn'
);
mmm_pst_assert(
	! Mixmymeals_Subscriptions::save_offer_notice_is_checkbox(),
	'ein abgehakt gespeichertes Kästchen ("") macht ihn zum reinen Hinweis'
);

update_option( 'mixmymeals_plan_subscribe_notice_checkbox', 'on' );

echo "-- 3. create_pending() lehnt ab, was kein veröffentlichter Plan ist\n";

$draft     = Mixmymeals_Meal_Plan::create( 'ZZ Subscribe Draft', 'draft' );
$created[] = $draft->get_id();

$err = Mixmymeals_Subscriptions::create_pending( $draft->get_id(), 'zz-test@example.invalid', '' );
mmm_pst_assert( is_wp_error( $err ), 'ein Entwurf wird abgelehnt' );

$err2 = Mixmymeals_Subscriptions::create_pending( 999999999, 'zz-test@example.invalid', '' );
mmm_pst_assert( is_wp_error( $err2 ), 'eine nicht existierende ID wird abgelehnt' );

echo "-- 4. create_pending() prüft die E-Mail, bevor es etwas anlegt\n";

$plan      = Mixmymeals_Meal_Plan::create( 'ZZ Subscribe Plan', 'publish' );
$created[] = $plan->get_id();

$before = wp_count_posts( 'mmm_subscription' )->publish;

$bad = Mixmymeals_Subscriptions::create_pending( $plan->get_id(), 'keine-email', '' );
mmm_pst_assert( is_wp_error( $bad ), 'eine ungültige E-Mail wird abgelehnt' );
mmm_pst_assert(
	(int) wp_count_posts( 'mmm_subscription' )->publish === $before,
	'und legt dabei keinen Post an'
);

echo "-- 5. create_pending() legt die Reihe an, die ajax_subscribe() auch angelegt hätte\n";

$sub_id = Mixmymeals_Subscriptions::create_pending( $plan->get_id(), 'ZZ-Test@Example.invalid', 'ZZ Tester', '' );

mmm_pst_assert( is_int( $sub_id ) && $sub_id > 0, 'liefert eine Post-ID' );

if ( is_int( $sub_id ) && $sub_id > 0 ) {
	$created[] = $sub_id;

	mmm_pst_assert(
		'mmm_subscription' === get_post_type( $sub_id ) && 'publish' === get_post_status( $sub_id ),
		'als veröffentlichter mmm_subscription-Post'
	);
	mmm_pst_assert(
		(int) get_post_meta( $sub_id, '_mmm_sub_plan_id', true ) === $plan->get_id(),
		'mit dem richtigen Plan'
	);
	mmm_pst_assert(
		'zz-test@example.invalid' === get_post_meta( $sub_id, '_mmm_sub_email', true ),
		'die E-Mail klein geschrieben (strtolower, wie ajax_subscribe())'
	);
	mmm_pst_assert(
		'pending' === get_post_meta( $sub_id, '_mmm_sub_status', true ),
		'Status pending, wartet auf die Bestätigung'
	);
	mmm_pst_assert(
		'' !== (string) get_post_meta( $sub_id, '_mmm_sub_token', true ),
		'mit einem Bestätigungs-Token'
	);
}

echo "-- 6. Der Speichern-Handler, als Gast, in jedem Modus\n";

/**
 * Ruft den echten AJAX-Handler fürs Speichern auf und liefert seine Antwort.
 *
 * Über do_action(), also die registrierte Instanz — eine zweite würde ihre
 * Hooks doppelt anmelden. wp_die() wirft hier, statt zu beenden.
 *
 * @param array $subscribe E-Mail und Zustimmung, wie frontend.js sie schickt.
 * @param int   $recipe_id Ein veröffentlichtes Rezept.
 * @return array Die dekodierte JSON-Antwort.
 */
function mmm_pst_save( array $subscribe, int $recipe_id ): array {

	$_POST = array(
		'nonce'     => wp_create_nonce( 'mmm_frontend_nonce' ),
		'items'     => array(
			array(
				'recipe_id' => $recipe_id,
				'servings'  => 2,
				'type'      => '',
				'day'       => 1,
			),
		),
		'subscribe' => $subscribe,
	);

	$die = static function () {
		return static function () {
			throw new RuntimeException( 'mmm_pst_die' );
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

	return is_array( $json ) ? $json : array();
}

/**
 * Wie viele Essenspläne es gerade gibt, gleich welcher Status.
 *
 * @return int
 */
function mmm_pst_plan_count(): int {
	return count( get_posts( array( 'post_type' => 'mmm_meal_plan', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) );
}

wp_set_current_user( 0 );
update_option( 'mixmymeals_public_mode', 'on' );
update_option( 'mixmymeals_save_meal_plans_published_by_visitors_as_draft', '' );
update_option( 'mixmymeals_published_meal_plans_per_hour_limit', 0 );

$recipe_id = (int) wp_insert_post(
	array(
		'post_type'   => 'mmm_recipe',
		'post_title'  => 'ZZ Subscribe Recipe',
		'post_status' => 'publish',
	)
);
$created[] = $recipe_id;

/**
 * Merkt sich einen angelegten Plan samt Mahlzeiten und Abos fürs Aufräumen.
 *
 * @param array $res      Handler-Antwort.
 * @param array $created  Liste der zu löschenden IDs (by reference).
 * @return int Plan-ID oder 0.
 */
function mmm_pst_track( array $res, array &$created ): int {
	$plan_id = (int) ( $res['data']['meal_plan_id'] ?? 0 );
	if ( $plan_id <= 0 ) {
		return 0;
	}
	foreach ( get_children( array( 'post_parent' => $plan_id, 'post_type' => 'mmm_meal', 'fields' => 'ids' ) ) as $meal_id ) {
		$created[] = (int) $meal_id;
	}
	foreach ( get_posts( array( 'post_type' => 'mmm_subscription', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => '_mmm_sub_plan_id', 'meta_value' => $plan_id ) ) as $sub ) { // phpcs:ignore WordPress.DB.SlowDBQuery
		$created[] = (int) $sub;
	}
	$created[] = $plan_id;
	return $plan_id;
}

update_option( 'mixmymeals_plan_subscribe_mode', 'required' );

$before = mmm_pst_plan_count();
$res    = mmm_pst_save( array( 'email' => '', 'agree' => '0' ), $recipe_id );
mmm_pst_assert(
	false === ( $res['success'] ?? null ) && 'subscribe_email_required' === ( $res['data']['code'] ?? '' ),
	'Pflicht: ohne Adresse wird nicht gespeichert'
);
mmm_pst_assert( mmm_pst_plan_count() === $before, 'und es entsteht auch kein halber Plan' );

$res = mmm_pst_save( array( 'email' => 'keine-email', 'agree' => '1' ), $recipe_id );
mmm_pst_assert( 'subscribe_email_invalid' === ( $res['data']['code'] ?? '' ), 'Pflicht: eine ungültige Adresse wird abgelehnt' );

$res = mmm_pst_save( array( 'email' => 'zz-pflicht@example.invalid', 'agree' => '0' ), $recipe_id );
mmm_pst_assert( 'subscribe_consent_required' === ( $res['data']['code'] ?? '' ), 'Pflicht mit Checkbox: ohne Haken wird nicht gespeichert' );

$res     = mmm_pst_save( array( 'email' => 'zz-pflicht@example.invalid', 'agree' => '1' ), $recipe_id );
$plan_id = mmm_pst_track( $res, $created );
mmm_pst_assert( true === ( $res['success'] ?? null ) && true === ( $res['data']['subscribed'] ?? null ), 'Pflicht: mit Adresse und Haken gespeichert und abonniert' );
mmm_pst_assert(
	1 === count( get_posts( array( 'post_type' => 'mmm_subscription', 'post_status' => 'any', 'fields' => 'ids', 'meta_key' => '_mmm_sub_plan_id', 'meta_value' => $plan_id ) ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
	'genau ein ausstehendes Abo für genau diesen Plan'
);

update_option( 'mixmymeals_plan_subscribe_mode', 'optional' );

$res = mmm_pst_save( array( 'email' => '', 'agree' => '0' ), $recipe_id );
mmm_pst_track( $res, $created );
mmm_pst_assert( true === ( $res['success'] ?? null ) && false === ( $res['data']['subscribed'] ?? null ), 'Möglich: ohne Adresse gespeichert, ohne Abo' );

update_option( 'mixmymeals_plan_subscribe_notice_checkbox', '' );

$res = mmm_pst_save( array( 'email' => 'zz-hinweis@example.invalid', 'agree' => '0' ), $recipe_id );
mmm_pst_track( $res, $created );
mmm_pst_assert( true === ( $res['data']['subscribed'] ?? null ), 'Hinweis statt Checkbox: die Adresse allein genügt' );

update_option( 'mixmymeals_plan_subscribe_notice_checkbox', 'on' );
update_option( 'mixmymeals_plan_subscribe_mode', 'required' );
update_option( 'mixmymeals_save_meal_plans_published_by_visitors_as_draft', 'on' );

$res = mmm_pst_save( array( 'email' => '', 'agree' => '0' ), $recipe_id );
mmm_pst_track( $res, $created );
mmm_pst_assert(
	true === ( $res['success'] ?? null ) && 'draft' === ( $res['data']['post_status'] ?? '' ),
	'Pflicht gilt nicht, wo der Gast-Plan ein Entwurf wird — dort gibt es kein Feld'
);

echo "-- 7. Aufräumen\n";

foreach ( array_reverse( $created ) as $created_id ) {
	wp_delete_post( $created_id, true );
}

update_option( 'mixmymeals_subscriptions_enabled', $saved['mixmymeals_subscriptions_enabled'] );
update_option( 'mixmymeals_plan_subscribe_mode', $saved['mixmymeals_plan_subscribe_mode'] );
update_option( 'mixmymeals_subscription_schedule', $saved['mixmymeals_subscription_schedule'] );
update_option( 'mixmymeals_public_mode', $saved['mixmymeals_public_mode'] );
update_option( 'mixmymeals_save_meal_plans_published_by_visitors_as_draft', $saved['mixmymeals_save_meal_plans_published_by_visitors_as_draft'] );
update_option( 'mixmymeals_published_meal_plans_per_hour_limit', $saved['mixmymeals_published_meal_plans_per_hour_limit'] );
foreach ( array( 'mixmymeals_plan_subscribe_notice', 'mixmymeals_plan_subscribe_notice_checkbox' ) as $opt ) {
	if ( null === $saved[ $opt ] ) {
		delete_option( $opt );
	} else {
		update_option( $opt, $saved[ $opt ] );
	}
}

mmm_pst_assert( true, 'Testdaten entfernt, Optionen zurückgesetzt' );

echo "\n=== Ergebnis ===\n";
echo $GLOBALS['MMM_PST_PASS'] . ' bestanden, ' . $GLOBALS['MMM_PST_FAIL'] . " fehlgeschlagen\n";
