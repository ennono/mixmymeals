<?php
/**
 * Werbekennzeichnung außerhalb der Seiten: schema.org `sponsor`, REST
 * (Lesen, Zusammenführen, Ablehnen unbekannter Typen) und Abo-Mails.
 *
 * Läuft gegen eine echte Installation, legt Posts „T …“ an und löscht sie
 * wieder.
 *
 * wp --require=<dbhost.php> eval-file \
 *    wp-content/plugins/mixmymeals/tools/disclosure-test.php
 *
 * @package MixMyMeals
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$GLOBALS['MMM_DC_FAIL']  = 0;
$GLOBALS['MMM_DC_PASS']  = 0;
$GLOBALS['MMM_DC_TRASH'] = array();

$ok = static function ( string $label, bool $cond ): void {
	if ( $cond ) {
		++$GLOBALS['MMM_DC_PASS'];
	} else {
		++$GLOBALS['MMM_DC_FAIL'];
	}
	echo esc_html( ( $cond ? 'ok   ' : 'FAIL ' ) . $label ) . PHP_EOL;
};

echo '-- 0. Richtige Seite?' . PHP_EOL;
echo '     home_url() = ' . esc_url( home_url() ) . PHP_EOL;
if ( preg_match( '/(^|\.)(speisengeplant|stopfnicht)\.de$/', (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) ) {
	echo 'Abbruch: das ist keine lokale Seite.' . PHP_EOL;
	return;
}

/**
 * Ruft eine Route des Plugins auf.
 *
 * @param string $method Methode.
 * @param string $path   Pfad unter /mixmymeals/v1.
 * @param array  $body   JSON-Körper.
 * @return WP_REST_Response
 */
function mmm_dc_call( string $method, string $path, array $body = array() ): WP_REST_Response {
	$request = new WP_REST_Request( $method, '/mixmymeals/v1' . $path );
	if ( $body ) {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) );
	}
	return rest_get_server()->dispatch( $request );
}

$admins = get_users(
	array(
		'role'   => 'administrator',
		'fields' => 'ID',
		'number' => 1,
	)
);
wp_set_current_user( (int) ( $admins[0] ?? 1 ) );

echo '-- 1. REST: Rezept anlegen mit Kennzeichnung' . PHP_EOL;
$res  = mmm_dc_call(
	'POST',
	'/recipes',
	array(
		'title'      => 'T Gesponsertes Rezept',
		'status'     => 'publish',
		'disclosure' => array(
			'type'    => 'sponsored',
			'sponsor' => 'ZZ Marke',
		),
	)
);
$data = $res->get_data();
$rid  = (int) ( $data['id'] ?? 0 );
if ( $rid ) {
	$GLOBALS['MMM_DC_TRASH'][] = $rid;
}
$ok( 'Rezept angelegt', $rid > 0 );
$ok( 'Antwort nennt type und sponsor', 'sponsored' === ( $data['disclosure']['type'] ?? '' ) && 'ZZ Marke' === ( $data['disclosure']['sponsor'] ?? '' ) );
$ok( 'Antwort nennt das angezeigte Label', '' !== ( $data['disclosure']['label'] ?? '' ) && true === ( $data['disclosure']['requires_label'] ?? null ) );

echo '-- 2. REST: nur übergebene Felder ändern sich' . PHP_EOL;
$data = mmm_dc_call( 'POST', "/recipes/$rid", array( 'disclosure' => array( 'type' => 'cooperation' ) ) )->get_data();
$ok( 'Typ gewechselt, Sponsor bleibt', 'cooperation' === ( $data['disclosure']['type'] ?? '' ) && 'ZZ Marke' === ( $data['disclosure']['sponsor'] ?? '' ) );
$data = mmm_dc_call( 'POST', "/recipes/$rid", array( 'title' => 'T Gesponsertes Rezept 2' ) )->get_data();
$ok( 'ohne disclosure bleibt alles', 'cooperation' === ( $data['disclosure']['type'] ?? '' ) );
$res = mmm_dc_call( 'POST', "/recipes/$rid", array( 'disclosure' => array( 'type' => 'werbung' ) ) );
$ok( 'unbekannter Typ wird abgelehnt', 400 === $res->get_status() );
$ok( '… und nichts geschrieben', 'cooperation' === Mixmymeals_Post_Meta::get_disclosure_type( $rid ) );
$res = mmm_dc_call( 'POST', "/recipes/$rid", array( 'disclosure' => array( 'typo' => 'guest' ) ) );
$ok( 'unbekannter Schlüssel wird abgelehnt', 400 === $res->get_status() );

echo '-- 3. schema.org sponsor' . PHP_EOL;
$schema = ( new Mixmymeals_Recipe( $rid ) )->get_schema();
$ok(
	'Rezept-Schema nennt den Sponsor',
	array(
		'@type' => 'Organization',
		'name'  => 'ZZ Marke',
	) === ( $schema['sponsor'] ?? null )
);

mmm_dc_call( 'POST', "/recipes/$rid", array( 'disclosure' => array( 'type' => 'guest' ) ) );
$ok( 'Gastbeitrag: Sponsor gelöscht', '' === Mixmymeals_Post_Meta::get_disclosure_sponsor( $rid ) );
$schema = ( new Mixmymeals_Recipe( $rid ) )->get_schema();
$ok( 'Gastbeitrag: kein sponsor im Schema', ! isset( $schema['sponsor'] ) );

Mixmymeals_Post_Meta::save_disclosure( $rid, array( 'type' => 'sponsored' ) );
$schema = ( new Mixmymeals_Recipe( $rid ) )->get_schema();
$ok( 'Werbung ohne Namen: kein sponsor im Schema', ! isset( $schema['sponsor'] ) );

$data = mmm_dc_call( 'POST', "/recipes/$rid", array( 'disclosure' => array( 'type' => '' ) ) )->get_data();
$ok( '"" macht redaktionell', '' === ( $data['disclosure']['type'] ?? 'x' ) && '' === ( $data['disclosure']['label'] ?? 'x' ) && false === ( $data['disclosure']['requires_label'] ?? null ) );

echo '-- 4. REST: Plan' . PHP_EOL;
$data = mmm_dc_call(
	'POST',
	'/meal-plans',
	array(
		'title'      => 'T Gesponserter Plan',
		'status'     => 'publish',
		'disclosure' => array(
			'type'    => 'sponsored',
			'sponsor' => 'ZZ Marke',
		),
	)
)->get_data();
$pid  = (int) ( $data['id'] ?? 0 );
if ( $pid ) {
	$GLOBALS['MMM_DC_TRASH'][] = $pid;
}
$ok( 'Plan angelegt und gekennzeichnet', $pid > 0 && 'sponsored' === ( $data['disclosure']['type'] ?? '' ) );
$data = mmm_dc_call( 'GET', "/meal-plans/$pid" )->get_data();
$ok( 'GET nennt die Kennzeichnung', 'ZZ Marke' === ( $data['disclosure']['sponsor'] ?? '' ) );

echo '-- 5. Mails' . PHP_EOL;
$block = Mixmymeals_Subscriptions::disclosure_block_html( $pid );
$label = Mixmymeals_Shortcodes::disclosure_label_for( $pid );
$ok( 'der Block trägt Label und Sponsor', false !== strpos( $block, esc_html( $label['text'] ) ) && false !== strpos( $block, 'ZZ Marke' ) );
$ok( 'der Block übersteht wp_kses_post()', wp_kses_post( $block ) === $block );
$ok( 'redaktionell: kein Block', '' === Mixmymeals_Subscriptions::disclosure_block_html( $rid ) );

Mixmymeals_Post_Meta::save_disclosure( $rid, array( 'type' => 'guest' ) );
$guest = Mixmymeals_Subscriptions::disclosure_label_html( $rid );
$ad    = Mixmymeals_Subscriptions::disclosure_label_html( $pid );
$ok( 'Gastautor und Werbung sehen verschieden aus', '' !== $guest && '' !== $ad && preg_replace( '/>[^<]*</', '><', $guest ) !== preg_replace( '/>[^<]*</', '><', $ad ) );

$prepend = new ReflectionMethod( 'Mixmymeals_Subscriptions', 'disclosure_prepend' );
$prepend->setAccessible( true );
$ok( 'Vorlage ohne Platzhalter: Block wird vorangestellt', $block === $prepend->invoke( null, 'message.html', $block ) );
$ok( 'nichts zu kennzeichnen: nichts vorangestellt', '' === $prepend->invoke( null, 'message.html', '' ) );

$captured = null;
$catch    = static function ( $args ) use ( &$captured ) {
	$captured = $args;
	return $args;
};
$short    = static function () {
	return true;
};
add_filter( 'wp_mail', $catch );
add_filter( 'pre_wp_mail', $short );
Mixmymeals_Mailer::send_template(
	'zz@example.invalid',
	'T',
	'<p>Inhalt</p>',
	array(),
	array(
		'inline'       => true,
		'layout'       => 'layout.html',
		'prepend_html' => $block,
		'force'        => true,
	)
);
remove_filter( 'wp_mail', $catch );
remove_filter( 'pre_wp_mail', $short );
$body = (string) ( $captured['message'] ?? '' );
$ok( 'der Mailer setzt prepend_html vor den Inhalt, im Layout', '' !== $body && false !== strpos( $body, $block . '<p>Inhalt</p>' ) );

echo PHP_EOL . '=== Aufräumen ===' . PHP_EOL;
foreach ( array_reverse( $GLOBALS['MMM_DC_TRASH'] ) as $trash_id ) {
	wp_delete_post( $trash_id, true );
}
echo 'entfernt: ' . count( $GLOBALS['MMM_DC_TRASH'] ) . ' Posts' . PHP_EOL;

echo PHP_EOL . '=== Ergebnis ===' . PHP_EOL;
printf( "  %d bestanden, %d fehlgeschlagen\n", (int) $GLOBALS['MMM_DC_PASS'], (int) $GLOBALS['MMM_DC_FAIL'] );
