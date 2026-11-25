<?php
/**
 * Prüft die Matomo-Kampagnenparameter an Links in Abo-Mails:
 * Mixmymeals_Shortcodes::mail_campaign_args() / mail_campaign_url(), den
 * Link-Filter des Mailers an einer echten Nachricht (send_message()) und
 * den Absatz im Datenschutzabschnitt.
 *
 * wp_mail() wird über pre_wp_mail abgefangen; es geht keine Mail hinaus.
 * Legt eigene Posts an („ZZ T …“) und löscht sie wieder.
 *
 * wp --require=<dbhost.php> eval-file \
 *    wp-content/plugins/mixmymeals/tools/mail-campaign-test.php
 *
 * @package MixMyMeals
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$GLOBALS['MMM_MCT_PASS'] = 0;
$GLOBALS['MMM_MCT_FAIL'] = 0;
$GLOBALS['MMM_MCT_MAIL'] = array();

/**
 * Eine Zusicherung.
 *
 * @param bool   $ok    Ergebnis.
 * @param string $label Beschreibung.
 * @return void
 */
function mmm_mct_assert( bool $ok, string $label ): void {
	if ( $ok ) {
		++$GLOBALS['MMM_MCT_PASS'];
		echo "ok   $label\n";
		return;
	}
	++$GLOBALS['MMM_MCT_FAIL'];
	echo "FAIL $label\n";
}

add_filter(
	'pre_wp_mail',
	static function ( $short, $atts ) {
		if ( null !== $short ) {
			return $short;
		}
		$GLOBALS['MMM_MCT_MAIL'][] = $atts;
		return true;
	},
	10,
	2
);

$opt   = Mixmymeals_Shortcodes::MAIL_CAMPAIGN_OPTION;
$saved = array();
foreach ( array( $opt, $opt . '_name', $opt . '_keyword', 'mixmymeals_subscriptions_enabled' ) as $key ) {
	$saved[ $key ] = get_option( $key, '' );
}
$created = array();
$home    = home_url( '/' );

echo "-- 1. Parameter\n";

update_option( $opt, '' );
mmm_mct_assert( array() === Mixmymeals_Shortcodes::mail_campaign_args( 'each-day', 12 ), 'aus: keine Parameter' );

update_option( $opt, 'on' );
update_option( $opt . '_name', '' );
update_option( $opt . '_keyword', '' );
$args = Mixmymeals_Shortcodes::mail_campaign_args( 'each-day', 12 );
mmm_mct_assert( Mixmymeals_Shortcodes::mail_campaign_default_name() === ( $args['mtm_campaign'] ?? '' ), 'leerer Name: Vorgabe' );
mmm_mct_assert( 'each-day' === ( $args['mtm_kwd'] ?? '' ), 'leeres Stichwort: {mail}' );
mmm_mct_assert( 'email' === ( $args['mtm_medium'] ?? '' ), 'Medium email' );

update_option( $opt . '_name', 'Abo-Mails' );
update_option( $opt . '_keyword', '{mail} {plan_id}' );
$args = Mixmymeals_Shortcodes::mail_campaign_args( 'message', 4812 );
mmm_mct_assert( 'Abo-Mails' === $args['mtm_campaign'] && 'message 4812' === $args['mtm_kwd'], 'eigener Name, Stichwort mit Plannummer' );

echo "-- 2. Welche Links\n";

$args = array(
	'mtm_campaign' => 'abo',
	'mtm_kwd'      => 'each day',
);
$plan = $home . 'essensplaene/zz/';

$tagged = Mixmymeals_Shortcodes::mail_campaign_url( $plan, $args );
mmm_mct_assert( false !== strpos( $tagged, 'mtm_campaign=abo' ) && false !== strpos( $tagged, 'mtm_kwd=each%20day' ), 'eigener Link: markiert, kodiert' );
mmm_mct_assert( str_ends_with( Mixmymeals_Shortcodes::mail_campaign_url( $plan . '#mmm-step-2', $args ), '#mmm-step-2' ), 'Anker bleibt am Ende' );
mmm_mct_assert( $plan . '?token=abc&recipe=3' === Mixmymeals_Shortcodes::mail_campaign_url( $plan . '?token=abc&recipe=3', $args ), 'Link mit Token (Bewerten): unverändert' );
mmm_mct_assert( $home . '?mmm_sub=unsubscribe&token=x' === Mixmymeals_Shortcodes::mail_campaign_url( $home . '?mmm_sub=unsubscribe&token=x', $args ), 'Abmelde-Link: unverändert' );
mmm_mct_assert( 'https://api.getbring.com/x' === Mixmymeals_Shortcodes::mail_campaign_url( 'https://api.getbring.com/x', $args ), 'fremde Seite: unverändert' );
mmm_mct_assert( 'mailto:a@example.com' === Mixmymeals_Shortcodes::mail_campaign_url( 'mailto:a@example.com', $args ), 'mailto: unverändert' );
mmm_mct_assert( $plan . '?mtm_campaign=x' === Mixmymeals_Shortcodes::mail_campaign_url( $plan . '?mtm_campaign=x', $args ), 'schon markiert: unverändert' );
mmm_mct_assert( $plan === Mixmymeals_Shortcodes::mail_campaign_url( $plan, array() ), 'ohne Parameter: unverändert' );

echo "-- 3. In der Mail (send_message)\n";

update_option( 'mixmymeals_subscriptions_enabled', 'on' );
update_option( $opt . '_name', '' );
update_option( $opt . '_keyword', '' );

$plan_id   = wp_insert_post(
	array(
		'post_type'   => 'mmm_meal_plan',
		'post_status' => 'publish',
		'post_title'  => 'ZZ T Kampagnenplan',
	)
);
$created[] = $plan_id;
$sub       = wp_insert_post(
	array(
		'post_type'   => Mixmymeals_Subscriptions::POST_TYPE,
		'post_status' => 'publish',
		'post_title'  => 'ZZ T Kampagnenabo',
	)
);
$created[] = $sub;
update_post_meta( $sub, '_mmm_sub_status', 'confirmed' );
update_post_meta( $sub, '_mmm_sub_email', 'zz-campaign-test@example.com' );
update_post_meta( $sub, '_mmm_sub_plan_id', $plan_id );
update_post_meta( $sub, '_mmm_sub_token', 'zztoken123' );
Mixmymeals_Mailer::remove_suppression( 'zz-campaign-test@example.com' );

Mixmymeals_Subscriptions::send_message( $sub, 'Kampagne', 'Siehe <a href="' . esc_url( $plan . '?a=1&b=2' ) . '">hier</a>.' );
$body = (string) ( end( $GLOBALS['MMM_MCT_MAIL'] )['message'] ?? '' );

preg_match_all( '/href="([^"]*)"/', $body, $m );
$hrefs = array_map( static fn( $h ) => html_entity_decode( $h ), $m[1] );

$plan_links  = array_filter( $hrefs, static fn( $h ) => str_starts_with( $h, (string) get_permalink( $plan_id ) ) );
$unsub_links = array_filter( $hrefs, static fn( $h ) => str_contains( $h, 'mmm_sub=unsubscribe' ) );
$own_links   = array_filter( $hrefs, static fn( $h ) => str_contains( $h, '?a=1&b=2' ) );

mmm_mct_assert( ! empty( $plan_links ) && array_reduce( $plan_links, static fn( $c, $h ) => $c && str_contains( $h, 'mtm_kwd=message' ), true ), 'Plan-Link: markiert mit mtm_kwd=message' );
mmm_mct_assert( ! empty( $own_links ) && str_contains( reset( $own_links ), 'mtm_campaign=' ), 'Link im Text: markiert, bestehende Parameter bleiben' );
mmm_mct_assert( ! empty( $unsub_links ) && ! array_filter( $unsub_links, static fn( $h ) => str_contains( $h, 'mtm_' ) ), 'Abmelde-Link: nicht markiert' );
mmm_mct_assert( false === strpos( $body, 'mtm_kwd=zztoken123' ) && 1 !== preg_match( '/mtm_[a-z]+=[^"&]*zztoken123/', $body ), 'Token steckt in keinem Parameter' );

update_option( $opt, '' );
Mixmymeals_Subscriptions::send_message( $sub, 'Ohne', 'Text' );
$body = (string) ( end( $GLOBALS['MMM_MCT_MAIL'] )['message'] ?? '' );
mmm_mct_assert( false === strpos( $body, 'mtm_' ), 'aus: kein Link markiert' );

echo "-- 4. Datenschutztext\n";

$marker = __( 'The marker contains neither the e-mail address nor anything else that would tie a visit to a subscriber; links with a personal access token (rating, photos, unsubscribe) carry none. The visits are processed only as that section describes.', 'mixmymeals' );
mmm_mct_assert( false === strpos( Mixmymeals_Privacy::policy_section_html( '' ), $marker ), 'aus: kein Absatz' );
update_option( $opt, 'on' );
mmm_mct_assert( false !== strpos( Mixmymeals_Privacy::policy_section_html( '' ), $marker ), 'an: Absatz da' );
update_option( $opt . '_keyword', '{mail}-{plan_id}' );
mmm_mct_assert( false !== strpos( Mixmymeals_Privacy::policy_section_html( '' ), __( 'Links in subscription e-mails: links to this website carry a campaign marker naming the kind of e-mail and the number of the plan, so that the web analytics (Matomo) described in this privacy policy can count visits that come from the e-mails.', 'mixmymeals' ) ), 'mit {plan_id}: Plannummer genannt' );

// Aufräumen.
foreach ( $created as $id ) {
	wp_delete_post( $id, true );
}
foreach ( $saved as $key => $value ) {
	update_option( $key, $value );
}

printf( "\n%d ok, %d failed\n", $GLOBALS['MMM_MCT_PASS'], $GLOBALS['MMM_MCT_FAIL'] );
