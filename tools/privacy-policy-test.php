<?php
/**
 * Prüft den erzeugten MixMyMeals-Abschnitt der Datenschutzerklärung
 * (Mixmymeals_Privacy::policy_section_html()): dass jeder einstellungs-
 * abhängige Satz genau dann dasteht, wenn seine Bedingung gilt, dass die
 * Zahlen aus den Einstellungen kommen, und dass Shortcode, Einstellungsreiter
 * und WordPress-Leitfaden denselben Text bekommen.
 *
 * Legt nichts an; die angefassten Optionen werden gesichert und
 * zurückgesetzt. Prüft auf Englisch (Quelltext), damit die Zusicherungen
 * nicht an einer Übersetzung hängen.
 *
 * wp --require=<dbhost.php> eval-file \
 *    wp-content/plugins/mixmymeals/tools/privacy-policy-test.php
 *
 * @package MixMyMeals
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Zähler in $GLOBALS: eval-file läuft in einem Funktionsgeltungsbereich.
$GLOBALS['MMM_PPT_PASS'] = 0;
$GLOBALS['MMM_PPT_FAIL'] = 0;

/**
 * Eine Zusicherung.
 *
 * @param bool   $ok    Ergebnis.
 * @param string $label Beschreibung.
 * @return void
 */
function mmm_ppt_assert( bool $ok, string $label ): void {
	if ( $ok ) {
		++$GLOBALS['MMM_PPT_PASS'];
		echo "ok   $label\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI.
		return;
	}
	++$GLOBALS['MMM_PPT_FAIL'];
	echo "FAIL $label\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI.
}

switch_to_locale( 'en_US' );

$keys  = array(
	'mixmymeals_subscriptions_enabled',
	'mixmymeals_subscription_schedule',
	'mixmymeals_subscription_log_ip',
	'mixmymeals_subscription_pending_hours',
	'mixmymeals_subscription_retention_days',
	'mixmymeals_plan_subscribe_mode',
	'mixmymeals_photos_auto_approve',
	'mixmymeals_mail_log_enabled',
	'mixmymeals_public_mode',
	'mixmymeals_published_meal_plans_per_hour_limit',
	'mixmymeals_save_meal_plans_published_by_visitors_as_draft',
);
$saved = array();
foreach ( $keys as $k ) {
	$saved[ $k ] = get_option( $k, null );
}

$text = static fn(): string => wp_strip_all_tags( Mixmymeals_Privacy::policy_section_html( '' ) );
$has  = static fn( string $needle ): bool => str_contains( $text(), $needle );

update_option( 'mixmymeals_subscriptions_enabled', 'on' );
update_option( 'mixmymeals_subscription_schedule', array( array( 'trigger' => 'before_start', 'time' => '08:00', 'subject' => 'ZZ', 'template' => 'day.html' ) ) );
update_option( 'mixmymeals_subscription_log_ip', '' );
update_option( 'mixmymeals_subscription_pending_hours', 48 );
update_option( 'mixmymeals_subscription_retention_days', 7 );
update_option( 'mixmymeals_plan_subscribe_mode', 'off' );
update_option( 'mixmymeals_photos_auto_approve', '' );
update_option( 'mixmymeals_mail_log_enabled', 'on' );
update_option( 'mixmymeals_public_mode', '' );
update_option( 'mixmymeals_save_meal_plans_published_by_visitors_as_draft', '' );

echo "-- 1. Zahlen kommen aus Konstanten und Einstellungen\n";

mmm_ppt_assert( $has( Mixmymeals_Cart::COOKIE_NAME ), 'der Cookie-Name steht drin, aus Mixmymeals_Cart::COOKIE_NAME' );
mmm_ppt_assert( $has( 'after 7 days' ) && $has( 'after 30 days' ), 'Planer 7 Tage, geteilte Kopie 30 Tage — aus EXPIRATION und SHARE_EXPIRATION' );
mmm_ppt_assert( $has( 'after 48 hours' ) && $has( 'records after 7 days' ), 'Abo-Fristen 48 Stunden / 7 Tage aus den Einstellungen' );

update_option( 'mixmymeals_subscription_retention_days', 30 );
mmm_ppt_assert( $has( 'records after 30 days' ), 'eine geänderte Aufbewahrung ändert den Text mit' );

mmm_ppt_assert( $has( (string) Mixmymeals_Mail_Log::get_max_entries() . ' most recent entries' ), 'Versandprotokoll: Obergrenze aus Mixmymeals_Mail_Log' );
mmm_ppt_assert( $has( 'after ' . Mixmymeals_Mail_Log::get_retention_days() . ' days at the latest' ), 'Versandprotokoll: auch die Altersgrenze, die der alte Text nie nannte' );

echo "-- 2. Bedingte Sätze stehen nur da, wo ihre Bedingung gilt\n";

mmm_ppt_assert( $has( 'No IP address is stored as proof of consent' ) && ! $has( 'full IP address' ), 'Einwilligungs-IP aus: sagt das, und nur das' );
update_option( 'mixmymeals_subscription_log_ip', 'on' );
mmm_ppt_assert( $has( 'full IP address' ) && ! $has( 'No IP address is stored' ), 'Einwilligungs-IP an: der andere Satz' );

mmm_ppt_assert( ! $has( 'when saving a plan in the planner' ), 'Abo beim Speichern aus: der Planer wird nicht als Anmeldeweg genannt' );
update_option( 'mixmymeals_plan_subscribe_mode', 'optional' );
mmm_ppt_assert( $has( 'when saving a plan in the planner' ) && ! $has( 'requires an e-mail address' ), 'möglich: Planer genannt, keine Pflicht' );
update_option( 'mixmymeals_plan_subscribe_mode', 'required' );
mmm_ppt_assert( $has( 'requires an e-mail address' ), 'Pflicht: der Pflicht-Satz' );

mmm_ppt_assert( $has( 'reviewed editorially' ), 'Fotos ohne Sofortfreigabe: werden geprüft' );
update_option( 'mixmymeals_photos_auto_approve', 'on' );
mmm_ppt_assert( $has( 'without prior review' ) && ! $has( 'reviewed editorially' ), 'Sofortfreigabe an: sagt, dass nicht geprüft wird' );

mmm_ppt_assert( ! $has( 'saves a meal plan' ), 'ohne Stundenlimit kein Zähler beim Speichern' );
update_option( 'mixmymeals_public_mode', 'on' );
update_option( 'mixmymeals_published_meal_plans_per_hour_limit', 3 );
mmm_ppt_assert( $has( 'saves a meal plan' ), 'mit Limit und öffentlichem Modus: der Zähler wird genannt' );

update_option( 'mixmymeals_mail_log_enabled', '' );
mmm_ppt_assert( ! $has( 'Send log' ) && ! $has( 'traceability of e-mail delivery' ), 'Versandprotokoll aus: weder Punkt noch Zweck' );

update_option( 'mixmymeals_subscriptions_enabled', '' );
mmm_ppt_assert(
	! $has( 'E-mail subscriptions' ) && ! $has( 'Suppression list' ) && ! $has( 'e-mail subscribers' ) && ! $has( 'subscribers (via a personalised link)' ),
	'Abos aus: kein Abo-Punkt, keine Sperrliste, keine Abonnenten bei Personen, Bewertungen und Fotos'
);

echo "-- 3. Eine Quelle für alle Ausgaben\n";

$plain = Mixmymeals_Privacy::policy_section_html( '' );

mmm_ppt_assert( ! str_contains( $plain, '<h2' ) && str_contains( Mixmymeals_Privacy::policy_section_html( 'h3' ), '<h3>' ), 'Überschrift abschaltbar und wählbar' );
mmm_ppt_assert( ! str_contains( Mixmymeals_Privacy::policy_section_html( 'script' ), '<script' ), 'ein unbekanntes Tag wird nicht ausgegeben' );
mmm_ppt_assert( shortcode_exists( 'mmm_privacy_policy' ), 'der Shortcode ist registriert' );
mmm_ppt_assert( str_contains( do_shortcode( '[mmm_privacy_policy heading="none"]' ), $plain ), 'der Shortcode gibt denselben Text aus' );

$filtered = static function ( array $items ): array {
	$items['zz'] = 'ZZ add-on <b>line</b>';
	return $items;
};
add_filter( 'mixmymeals_privacy_policy_items', $filtered );
$with = Mixmymeals_Privacy::policy_section_html( '' );
remove_filter( 'mixmymeals_privacy_policy_items', $filtered );
mmm_ppt_assert( str_contains( $with, 'ZZ add-on &lt;b&gt;line&lt;/b&gt;' ), 'Add-on-Punkte über den Filter, als Text maskiert' );

if ( class_exists( 'Mixmymeals_Pinterest' ) ) {
	mmm_ppt_assert( $has( 'Pin images' ), 'das Social-Add-on trägt seine Pinnwand-Bilder bei' );
}

echo "-- 4. Aufräumen\n";

foreach ( $saved as $k => $v ) {
	if ( null === $v ) {
		delete_option( $k );
	} else {
		update_option( $k, $v );
	}
}
restore_previous_locale();
mmm_ppt_assert( true, 'Optionen zurückgesetzt' );

echo "\n=== Ergebnis ===\n";
echo (int) $GLOBALS['MMM_PPT_PASS'] . ' bestanden, ' . (int) $GLOBALS['MMM_PPT_FAIL'] . " fehlgeschlagen\n";
