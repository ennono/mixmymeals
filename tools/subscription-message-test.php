<?php
/**
 * Prüft individuelle Nachrichten an Abonnenten:
 * Mixmymeals_Subscriptions::send_message() (wer darf sie bekommen, was steht
 * in der Mail, was wird protokolliert), den Speichern-Pfad des Kastens
 * „Nachrichten“ (Rundweg über save_message_box()), den Datenexport und den
 * Satz im Datenschutzabschnitt.
 *
 * wp_mail() wird über pre_wp_mail abgefangen; es geht keine Mail hinaus.
 * Legt eigene Posts an („ZZ T …“) und löscht sie wieder.
 *
 * wp --require=<dbhost.php> eval-file \
 *    wp-content/plugins/mixmymeals/tools/subscription-message-test.php
 *
 * @package MixMyMeals
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$GLOBALS['MMM_SMT_PASS'] = 0;
$GLOBALS['MMM_SMT_FAIL'] = 0;
$GLOBALS['MMM_SMT_MAIL'] = array();

/**
 * Eine Zusicherung.
 *
 * @param bool   $ok    Ergebnis.
 * @param string $label Beschreibung.
 * @return void
 */
function mmm_smt_assert( bool $ok, string $label ): void {
	if ( $ok ) {
		++$GLOBALS['MMM_SMT_PASS'];
		echo "ok   $label\n";
		return;
	}
	++$GLOBALS['MMM_SMT_FAIL'];
	echo "FAIL $label\n";
}

add_filter(
	'pre_wp_mail',
	static function ( $short, $atts ) {
		if ( null !== $short ) {
			return $short; // Ein Testfall lässt das Senden scheitern.
		}
		$GLOBALS['MMM_SMT_MAIL'][] = $atts;
		return true;
	},
	10,
	2
);

$email   = 'zz-message-test@example.com';
$saved   = array(
	'mixmymeals_subscriptions_enabled' => get_option( 'mixmymeals_subscriptions_enabled', '' ),
	'mixmymeals_mail_log_enabled'      => get_option( 'mixmymeals_mail_log_enabled', '' ),
);
$created = array();

update_option( 'mixmymeals_subscriptions_enabled', 'on' );
Mixmymeals_Mailer::remove_suppression( $email );

$admins = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
);
wp_set_current_user( (int) ( $admins[0] ?? 0 ) );

$plan_id   = wp_insert_post(
	array(
		'post_type'   => 'mmm_meal_plan',
		'post_status' => 'publish',
		'post_title'  => 'ZZ T Nachrichtenplan',
	)
);
$created[] = $plan_id;

$make_sub = static function ( string $status ) use ( $email, $plan_id, &$created ): int {
	$id = wp_insert_post(
		array(
			'post_type'   => Mixmymeals_Subscriptions::POST_TYPE,
			'post_status' => 'publish',
			'post_title'  => 'ZZ T Abo ' . $status,
		)
	);
	update_post_meta( $id, '_mmm_sub_status', $status );
	update_post_meta( $id, '_mmm_sub_email', $email );
	update_post_meta( $id, '_mmm_sub_name', 'Zora' );
	update_post_meta( $id, '_mmm_sub_plan_id', $plan_id );
	update_post_meta( $id, '_mmm_sub_token', wp_generate_password( 32, false ) );
	$created[] = $id;
	return $id;
};

$sub     = $make_sub( 'confirmed' );
$pending = $make_sub( 'pending' );
$done    = $make_sub( 'completed' );

echo "-- 1. Wer eine Nachricht bekommen darf\n";

mmm_smt_assert( Mixmymeals_Subscriptions::may_receive_message( $sub ), 'laufendes Abo: ja' );
mmm_smt_assert( ! Mixmymeals_Subscriptions::may_receive_message( $pending ), 'unbestätigt: nein' );
mmm_smt_assert( ! Mixmymeals_Subscriptions::may_receive_message( $done ), 'beendet: nein (nur Nachweis)' );
mmm_smt_assert( ! Mixmymeals_Subscriptions::may_receive_message( $plan_id ), 'kein Abo-Post: nein' );

update_option( 'mixmymeals_subscriptions_enabled', '' );
mmm_smt_assert( ! Mixmymeals_Subscriptions::may_receive_message( $sub ), 'Abos ausgeschaltet: nein' );
update_option( 'mixmymeals_subscriptions_enabled', 'on' );

Mixmymeals_Mailer::add_suppression( $email );
mmm_smt_assert( ! Mixmymeals_Subscriptions::may_receive_message( $sub ), 'Adresse auf der Sperrliste: nein' );
mmm_smt_assert( 'not_active' === Mixmymeals_Subscriptions::send_message( $sub, 'Betreff', 'Text' ), 'Sperrliste: not_active, nichts gesendet' );
Mixmymeals_Mailer::remove_suppression( $email );

mmm_smt_assert( 'not_active' === Mixmymeals_Subscriptions::send_message( $pending, 'Betreff', 'Text' ), 'unbestätigt: not_active' );
mmm_smt_assert( 'empty' === Mixmymeals_Subscriptions::send_message( $sub, '', 'Text' ), 'ohne Betreff: empty' );
mmm_smt_assert( 'empty' === Mixmymeals_Subscriptions::send_message( $sub, 'Betreff', '  ' ), 'ohne Text: empty' );
mmm_smt_assert( array() === $GLOBALS['MMM_SMT_MAIL'], 'bis hier keine Mail' );
mmm_smt_assert( array() === Mixmymeals_Subscriptions::get_messages( $sub ), 'bis hier kein Protokolleintrag' );

echo "-- 2. Senden\n";

$result = Mixmymeals_Subscriptions::send_message( $sub, 'Korrektur zu Tag 3', "Am Mittwoch fehlt eine Zutat.\n\nSorry! <script>alert(1)</script>" );
$mail   = $GLOBALS['MMM_SMT_MAIL'][0] ?? array();
$body   = (string) ( $mail['message'] ?? '' );

mmm_smt_assert( 'sent' === $result, 'Ergebnis sent' );
mmm_smt_assert( 1 === count( $GLOBALS['MMM_SMT_MAIL'] ), 'genau eine Mail' );
mmm_smt_assert( $email === ( $mail['to'] ?? '' ), 'an die Adresse des Abos' );
mmm_smt_assert( 'Korrektur zu Tag 3' === ( $mail['subject'] ?? '' ), 'Betreff wie eingegeben' );
mmm_smt_assert( false !== strpos( $body, 'Zora' ), 'Anrede mit Namen' );
mmm_smt_assert( false !== strpos( $body, '<p>Am Mittwoch fehlt eine Zutat.</p>' ), 'Text mit Absätzen' );
mmm_smt_assert( false === strpos( $body, '<script' ), 'kein Skript im Text' );
mmm_smt_assert( false !== strpos( $body, 'mmm_sub=unsubscribe' ), 'Abmelde-Link im Text' );
mmm_smt_assert( false !== strpos( $body, 'ZZ T Nachrichtenplan' ), 'Plan genannt' );
mmm_smt_assert( false === strpos( $body, '{{' ), 'keine offenen Platzhalter' );
mmm_smt_assert( false !== strpos( implode( "\n", (array) ( $mail['headers'] ?? array() ) ), 'List-Unsubscribe' ), 'List-Unsubscribe-Kopfzeile' );

$log = Mixmymeals_Subscriptions::get_messages( $sub );
mmm_smt_assert( 1 === count( $log ), 'ein Protokolleintrag am Abo' );
mmm_smt_assert( 'Korrektur zu Tag 3' === ( $log[0]['subject'] ?? '' ) && ! empty( $log[0]['sent'] ), 'Eintrag: Betreff, gesendet' );
mmm_smt_assert( get_current_user_id() === ( $log[0]['user'] ?? -1 ), 'Eintrag: Absender' );
mmm_smt_assert( false === strpos( $log[0]['message'] ?? '', '<script' ), 'Eintrag: bereinigter Text' );

echo "-- 3. Fehlende Vorlage\n";

$missing = static function () {
	return 'zz-gibt-es-nicht.html';
};
add_filter( 'mixmymeals_subscription_message_template', $missing );
$result = Mixmymeals_Subscriptions::send_message( $sub, 'Zweite', 'Text' );
remove_filter( 'mixmymeals_subscription_message_template', $missing );
$log = Mixmymeals_Subscriptions::get_messages( $sub );

mmm_smt_assert( 'failed' === $result, 'Ergebnis failed' );
mmm_smt_assert( 1 === count( $GLOBALS['MMM_SMT_MAIL'] ), 'keine weitere Mail' );
mmm_smt_assert( 2 === count( $log ) && empty( $log[1]['sent'] ), 'als fehlgeschlagen protokolliert' );

mmm_smt_assert( false !== strpos( $log[1]['error'] ?? '', 'zz-gibt-es-nicht.html' ), 'Grund am Abo: Vorlage genannt' );

echo "-- 3b. Grund auch ohne Sende-Log\n";

update_option( 'mixmymeals_mail_log_enabled', '' );

$refuse = static function ( $short ) {
	do_action( 'wp_mail_failed', new WP_Error( 'wp_mail_failed', 'SMTP Error: Could not authenticate.' ) );
	return false;
};
add_filter( 'pre_wp_mail', $refuse, 5 );
$result = Mixmymeals_Subscriptions::send_message( $sub, 'Dritte', 'Text' );
remove_filter( 'pre_wp_mail', $refuse, 5 );
$log = Mixmymeals_Subscriptions::get_messages( $sub );
mmm_smt_assert( 'failed' === $result, 'Mailserver lehnt ab: failed' );
mmm_smt_assert( false !== strpos( end( $log )['error'], 'Could not authenticate' ), 'Grund am Abo: Meldung des Mailsystems' );

$limit = static function () {
	return 1;
};
add_filter( 'mixmymeals_mail_rate_limit_per_hour', $limit );
set_transient( 'mmm_mail_rl_' . wp_hash( $email ), 1, HOUR_IN_SECONDS );
$before = count( $GLOBALS['MMM_SMT_MAIL'] );
$result = Mixmymeals_Subscriptions::send_message( $sub, 'Vierte', 'Text' );
remove_filter( 'mixmymeals_mail_rate_limit_per_hour', $limit );
delete_transient( 'mmm_mail_rl_' . wp_hash( $email ) );
$log = Mixmymeals_Subscriptions::get_messages( $sub );
mmm_smt_assert( 'failed' === $result && count( $GLOBALS['MMM_SMT_MAIL'] ) === $before, 'Stundenlimit: failed, keine Mail' );
mmm_smt_assert( false !== strpos( end( $log )['error'], '1' ) && '' !== end( $log )['error'], 'Grund am Abo: Stundenlimit' );

update_option( 'mixmymeals_mail_log_enabled', 'on' );
add_filter( 'pre_wp_mail', $refuse, 5 );
Mixmymeals_Subscriptions::send_message( $sub, 'Fünfte', 'Text' );
remove_filter( 'pre_wp_mail', $refuse, 5 );
$entry = Mixmymeals_Mail_Log::get_entries()[0] ?? array();
mmm_smt_assert( 'failed' === ( $entry['status'] ?? '' ) && false !== strpos( Mixmymeals_Mail_Log::reason_text( $entry ), 'Could not authenticate' ), 'Sende-Log: Fehlschlag mit Grund' );

echo "-- 4. Obergrenze des Protokolls\n";

update_post_meta(
	$sub,
	Mixmymeals_Subscriptions::META_MESSAGES,
	array_fill(
		0,
		Mixmymeals_Subscriptions::MESSAGES_MAX,
		array(
			'time'    => 1,
			'subject' => 'alt',
			'message' => 'alt',
			'sent'    => true,
		)
	)
);
Mixmymeals_Subscriptions::send_message( $sub, 'Neueste', 'Text' );
$log = Mixmymeals_Subscriptions::get_messages( $sub );
mmm_smt_assert( Mixmymeals_Subscriptions::MESSAGES_MAX === count( $log ), 'höchstens MESSAGES_MAX Einträge' );
mmm_smt_assert( 'Neueste' === end( $log )['subject'], 'der neueste bleibt, der älteste geht' );
delete_post_meta( $sub, Mixmymeals_Subscriptions::META_MESSAGES );

echo "-- 5. Kasten: Rundweg über save_message_box()\n";

$instance = new ReflectionClass( 'Mixmymeals_Subscriptions' );
$box      = $instance->newInstanceWithoutConstructor();
$sent     = count( $GLOBALS['MMM_SMT_MAIL'] );

$_POST = array(
	'mmm_sub_message_nonce'   => wp_create_nonce( 'mmm_sub_message_' . $sub ),
	'mmm_sub_message_subject' => 'Aus dem Kasten',
	'mmm_sub_message_body'    => 'Hallo aus wp-admin',
);
$box->save_message_box( $sub );
mmm_smt_assert( count( $GLOBALS['MMM_SMT_MAIL'] ) === $sent, 'ohne den eigenen Knopf (nur „Aktualisieren“): nichts gesendet' );

$_POST['mmm_sub_message_send'] = 'Nachricht senden';
$box->save_message_box( $sub );
$log = Mixmymeals_Subscriptions::get_messages( $sub );
mmm_smt_assert( count( $GLOBALS['MMM_SMT_MAIL'] ) === $sent + 1, 'mit dem Knopf: gesendet' );
mmm_smt_assert( 'Aus dem Kasten' === ( $log[0]['subject'] ?? '' ), 'protokolliert' );

$location = $box->message_redirect_location( admin_url( 'post.php?post=' . $sub . '&action=edit&message=1' ), $sub );
mmm_smt_assert( false !== strpos( $location, 'mmm_sub_done=message_sent' ) && false === strpos( $location, 'message=1' ), 'Weiterleitung trägt das Ergebnis' );

$_POST['mmm_sub_message_nonce'] = 'falsch';
$box->save_message_box( $sub );
mmm_smt_assert( count( $GLOBALS['MMM_SMT_MAIL'] ) === $sent + 1, 'falsche Nonce: nichts gesendet' );
$_POST = array();

echo "-- 6. Datenexport und Datenschutztext\n";

$exporters = apply_filters( 'wp_privacy_personal_data_exporters', array() );
$export    = call_user_func( $exporters['mixmymeals-subscriptions']['callback'], $email, 1 );
$found     = false;
foreach ( $export['data'] as $item ) {
	foreach ( $item['data'] as $row ) {
		if ( false !== strpos( (string) $row['value'], 'Aus dem Kasten' ) && false !== strpos( (string) $row['value'], 'Hallo aus wp-admin' ) ) {
			$found = true;
		}
	}
}
mmm_smt_assert( $found, 'Export enthält Betreff und Text der Nachricht' );

$policy = Mixmymeals_Privacy::policy_section_html( '' );
mmm_smt_assert( false !== strpos( $policy, __( 'While a subscription runs, we may also write to subscribers individually about the subscribed plan, for example with a correction or an answer to a question. Such a message is stored with the subscription (time, subject and text) and deleted with it.', 'mixmymeals' ) ), 'Datenschutztext nennt die Nachrichten' );

update_option( 'mixmymeals_subscriptions_enabled', '' );
$policy = Mixmymeals_Privacy::policy_section_html( '' );
mmm_smt_assert( false === strpos( $policy, __( 'Such a message is stored with the subscription (time, subject and text) and deleted with it.', 'mixmymeals' ) ), 'ohne Abos: kein Satz dazu' );

// Aufräumen.
foreach ( $created as $id ) {
	wp_delete_post( $id, true );
}
Mixmymeals_Mailer::remove_suppression( $email );
foreach ( $saved as $key => $value ) {
	update_option( $key, $value );
}

printf( "\n%d ok, %d failed\n", $GLOBALS['MMM_SMT_PASS'], $GLOBALS['MMM_SMT_FAIL'] );
