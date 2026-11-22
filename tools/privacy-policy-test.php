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
	'mixmymeals_enable_cleanup',
	'mixmymeals_disable_wp_emoji',
	'mixmymeals_privacy_section_stamp',
	'wp_page_for_privacy_policy',
	'mixmymeals_matomo_plan_event',
	'mixmymeals_matomo_plan_event_name',
	'mixmymeals_matomo_cook_mode_event',
	'mixmymeals_matomo_cook_mode_event_name',
	'mixmymeals_matomo_add_recipe_event',
	'mixmymeals_matomo_subscribe_event',
	'mixmymeals_matomo_confirm_event',
	'mixmymeals_matomo_confirm_event_name',
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
mmm_ppt_assert( $has( 'after 48 hours' ) && $has( 'deleted 7 days after they ended' ), 'Abo-Fristen 48 Stunden / 7 Tage aus den Einstellungen' );

update_option( 'mixmymeals_subscription_retention_days', 30 );
mmm_ppt_assert( $has( 'deleted 30 days after they ended' ), 'eine geänderte Aufbewahrung ändert den Text mit' );
mmm_ppt_assert( $has( 'A running subscription is kept while it runs' ), 'laufende Abos werden nicht als gelöscht beschrieben (prune() löscht nur beendete)' );

mmm_ppt_assert( $has( (string) Mixmymeals_Mail_Log::get_max_entries() . ' most recent entries' ), 'Versandprotokoll: Obergrenze aus Mixmymeals_Mail_Log' );
mmm_ppt_assert( $has( 'after ' . Mixmymeals_Mail_Log::get_retention_days() . ' days at the latest' ), 'Versandprotokoll: auch die Altersgrenze, die der alte Text nie nannte' );

echo "-- 1b. Befunde der Datenschutzprüfung\n";

mmm_ppt_assert( $has( 'consent (Art. 6(1)(a) GDPR) for e-mail subscriptions;' ) && ! $has( 'consent (Art. 6(1)(a) GDPR) for e-mail subscriptions, ratings' ), 'Einwilligung nur für Abos, nicht für Bewertungen und Fotos' );
mmm_ppt_assert( $has( '(Art. 6(1)(b) GDPR) for saving plans, ratings and photo uploads' ), 'Bewertungen, Fotos, Pläne: Bereitstellung der Funktion' );
mmm_ppt_assert( $has( 'our interest in protection against automated abuse' ), 'berechtigte Interessen werden benannt' );
mmm_ppt_assert( $has( 'becomes a publicly accessible page' ) && $has( 'display name of their account' ), 'gespeicherte Pläne werden öffentlich, mit Kontoname bei Angemeldeten' );
update_option( 'mixmymeals_enable_cleanup', '' );
mmm_ppt_assert( $has( 'kept until their deletion is requested' ), 'Aufräumen aus: Pläne bleiben bis zum Löschverlangen — gesagt statt verschwiegen' );
$mmm_ppt_js = (string) file_get_contents( WP_PLUGIN_DIR . '/mixmymeals/assets/js/frontend.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
mmm_ppt_assert(
	$has( 'local storage (“mmm_shopping_marks_v2”)' ) && $has( 'session storage (“mmm_startday”)' )
		&& str_contains( $mmm_ppt_js, "MMM_SHOP_KEY = 'mmm_shopping_marks_v2'" ) && str_contains( $mmm_ppt_js, "sessionStorage.setItem('mmm_startday'" ),
	'Browserspeicher: Abhak-Liste und Starttag genannt, mit den Schlüsseln, die frontend.js wirklich benutzt'
);
mmm_ppt_assert( $has( 'pseudonymous identifier' ) && ! $has( 'no personal reference' ), 'die Planer-Kennung heißt pseudonym' );
mmm_ppt_assert( $has( 'user agent' ), 'Kommentarformular: IP und User-Agent genannt' );
mmm_ppt_assert( $has( 'links for rating its recipes and sharing photos' ), 'der Mail-Inhalt wird genannt' );
mmm_ppt_assert( ! str_contains( Mixmymeals_Subscriptions::consent_text(), 'accept' ), 'der Einwilligungssatz lässt die Erklärung nicht mehr „akzeptieren“' );

echo "-- 1c. Esser (3.70.0): nur Nummern, keine Namen\n";

mmm_ppt_assert( $has( 'which of them eat each recipe and how many portions' ) && $has( 'no names are asked for' ), 'Planer: wer was isst wird gespeichert, nummeriert, ohne Namen' );
mmm_ppt_assert( $has( 'which of the numbered people eat along, with their portions, are stored' ), 'geteilte Kopie: die nummerierten Esser stehen in der Liste des Gespeicherten' );
mmm_ppt_assert( $has( 'naming them by number only' ) && $has( 'are not published' ), 'gespeicherter Plan: Personen nur als Nummer, Namen aus der Verwaltung nicht öffentlich' );
mmm_ppt_assert( array() === array_diff_key( Mixmymeals_Cart::strip_eater_names( array( array( 'id' => 'p1', 'name' => 'X', 'profile' => 'adult' ) ) )[0], array( 'id' => 1, 'profile' => 1, 'name' => 1 ) ) && '' === Mixmymeals_Cart::strip_eater_names( array( array( 'id' => 'p1', 'name' => 'X', 'profile' => 'adult' ) ) )[0]['name'], 'was der Text zusagt, hält der Code: der Planer verwirft Namen' );

echo "-- 2. Bedingte Sätze stehen nur da, wo ihre Bedingung gilt\n";

mmm_ppt_assert( $has( 'No IP address is stored as proof of consent' ) && ! $has( 'full IP address' ), 'Einwilligungs-IP aus: sagt das, und nur das' );
update_option( 'mixmymeals_subscription_log_ip', 'on' );
mmm_ppt_assert( $has( 'full IP address' ) && ! $has( 'No IP address is stored' ), 'Einwilligungs-IP an: der andere Satz' );

mmm_ppt_assert( ! $has( 'when saving a plan in the planner' ), 'Abo beim Speichern aus: der Planer wird nicht als Anmeldeweg genannt' );
update_option( 'mixmymeals_plan_subscribe_mode', 'optional' );
mmm_ppt_assert( $has( 'when saving a plan in the planner' ) && $has( 'is voluntary; the plan is saved without it' ) && ! $has( 'is required to save a plan' ), 'möglich: Planer genannt, Freiwilligkeit gesagt' );
update_option( 'mixmymeals_plan_subscribe_mode', 'required' );
mmm_ppt_assert( $has( 'is required to save a plan' ) && $has( 'the plan stays saved, the address is deleted after 48 hours' ), 'Pflicht: Art. 13 Abs. 2 lit. e — Pflicht und Folge der Nichtbestätigung' );

mmm_ppt_assert( $has( 'reviewed editorially' ), 'Fotos ohne Sofortfreigabe: werden geprüft' );
update_option( 'mixmymeals_photos_auto_approve', 'on' );
mmm_ppt_assert( $has( 'without prior review' ) && ! $has( 'reviewed editorially' ), 'Sofortfreigabe an: sagt, dass nicht geprüft wird' );

mmm_ppt_assert( ! $has( 'saves a meal plan' ), 'ohne Stundenlimit kein Zähler beim Speichern' );
mmm_ppt_assert( class_exists( 'Mixmymeals_Printer' ) || ! $has( 'PDF' ), 'ohne Printer-Add-on kein PDF im Text — der Export liegt seit 3.61.0 dort' );
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

echo "-- 3a. Matomo-Ereignisse\n";

foreach ( array( 'plan', 'cook_mode' ) as $mmm_ev ) {
	delete_option( "mixmymeals_matomo_{$mmm_ev}_event" );
	delete_option( "mixmymeals_matomo_{$mmm_ev}_event_name" );
}
mmm_ppt_assert( ! $has( 'Usage statistics' ), 'Matomo aus: kein Punkt Nutzungsstatistik' );

update_option( 'mixmymeals_matomo_plan_event', 'on' );
mmm_ppt_assert( $has( 'saving a plan in the planner' ) && ! $has( 'number of the plan' ) && ! $has( 'cooking mode' ), 'Plan-Ereignis an: nur das Speichern, ohne Plannummer' );
update_option( 'mixmymeals_matomo_plan_event_name', '#{plan_id}' );
mmm_ppt_assert( $has( 'and the number of the plan' ), 'Name mit {plan_id}: Plannummer genannt' );

update_option( 'mixmymeals_matomo_plan_event', '' );
update_option( 'mixmymeals_matomo_cook_mode_event', 'on' );
mmm_ppt_assert( $has( 'opening the cooking mode, with the recipe and the number of its steps.' ) && ! $has( 'saving a plan' ), 'Kochmodus an: nur der Kochmodus, ohne Mahlzeit' );
update_option( 'mixmymeals_matomo_cook_mode_event_name', '{recipe} ({meal_id})' );
mmm_ppt_assert( $has( 'number of the meal and its plan' ), 'Name mit {meal_id}: Mahlzeit und Plan genannt' );
update_option( 'mixmymeals_matomo_plan_event', 'on' );
mmm_ppt_assert( $has( 'number of the plan; opening the cooking mode' ), 'beide an: ein Punkt, beide Aktionen' );
update_option( 'mixmymeals_matomo_add_recipe_event', 'on' );
mmm_ppt_assert( $has( 'number of the plan; adding a recipe to the planner, with the recipe, the day and servings chosen and the number of meals in the planner; opening the cooking mode' ), 'Einplanen an: steht zwischen Plan und Kochmodus' );
delete_option( 'mixmymeals_matomo_add_recipe_event' );
update_option( 'mixmymeals_matomo_subscribe_event', 'on' );
update_option( 'mixmymeals_matomo_confirm_event', 'on' );
mmm_ppt_assert( $has( 'signing up for a plan by e-mail (without the address); confirming a subscription;' ), 'Abo-Ereignisse an: Anmelden und Bestätigen, ohne Plannummer' );
update_option( 'mixmymeals_matomo_confirm_event_name', 'P{plan_id}' );
mmm_ppt_assert( $has( 'confirming a subscription, with the number of the plan' ), 'Bestätigen mit {plan_id}: Plannummer genannt' );
delete_option( 'mixmymeals_matomo_subscribe_event' );
delete_option( 'mixmymeals_matomo_confirm_event' );
delete_option( 'mixmymeals_matomo_confirm_event_name' );

echo "-- 3b. WordPress-Emoji abschaltbar\n";

update_option( 'mixmymeals_disable_wp_emoji', '' );
Mixmymeals_Privacy::maybe_disable_emoji();
mmm_ppt_assert( false !== has_action( 'wp_head', 'print_emoji_detection_script' ), 'Option aus: das Emoji-Skript bleibt (Standard)' );

update_option( 'mixmymeals_disable_wp_emoji', 'on' );
Mixmymeals_Privacy::maybe_disable_emoji();
mmm_ppt_assert(
	false === has_action( 'wp_head', 'print_emoji_detection_script' )
	&& false === has_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' )
	&& false === has_filter( 'wp_mail', 'wp_staticize_emoji_for_email' ),
	'Option an: Skript, Styles und die s.w.org-Bilder in Mails sind ausgehängt'
);
mmm_ppt_assert( ! in_array( 'wpemoji', (array) apply_filters( 'tiny_mce_plugins', array( 'wpemoji', 'lists' ) ), true ), 'auch das Emoji-Plugin des klassischen Editors' );

echo "-- 3c. Datum der letzten Änderung\n";

delete_option( Mixmymeals_Privacy::OPT_SECTION_STAMP );
$mmm_t0 = time();
mmm_ppt_assert( Mixmymeals_Privacy::section_changed() >= $mmm_t0, 'erster Blick: zählt als Änderung, Fingerabdruck gespeichert' );

$mmm_stamp         = get_option( Mixmymeals_Privacy::OPT_SECTION_STAMP );
$mmm_stamp['time'] = 1000;
update_option( Mixmymeals_Privacy::OPT_SECTION_STAMP, $mmm_stamp );
mmm_ppt_assert( 1000 === Mixmymeals_Privacy::section_changed(), 'gleicher Text: das Datum bleibt' );
switch_to_locale( 'de_DE' );
$mmm_de = Mixmymeals_Privacy::section_changed();
restore_previous_locale();
mmm_ppt_assert( 1000 === $mmm_de && 1000 === Mixmymeals_Privacy::section_changed(), 'eine andere Sprache der Anfrage zählt nicht als Änderung' );

update_option( 'mixmymeals_mail_log_enabled', 'on' === get_option( 'mixmymeals_mail_log_enabled' ) ? '' : 'on' );
mmm_ppt_assert( Mixmymeals_Privacy::section_changed() >= $mmm_t0, 'geänderte Einstellung, geänderter Text: neues Datum' );

$mmm_page = wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_status' => 'draft',
		'post_title'  => 'T Datenschutz',
	)
);
$mmm_stamp['time'] = 1000;
$mmm_stamp['hash'] = get_option( Mixmymeals_Privacy::OPT_SECTION_STAMP )['hash'];
update_option( Mixmymeals_Privacy::OPT_SECTION_STAMP, $mmm_stamp );
mmm_ppt_assert( get_post_datetime( $mmm_page, 'modified' )->getTimestamp() === Mixmymeals_Privacy::policy_updated( $mmm_page ), 'Seite jünger als der Abschnitt: das Datum der Seite' );
$mmm_stamp['time'] = time() + DAY_IN_SECONDS;
update_option( Mixmymeals_Privacy::OPT_SECTION_STAMP, $mmm_stamp );
mmm_ppt_assert( $mmm_stamp['time'] === Mixmymeals_Privacy::policy_updated( $mmm_page ), 'Abschnitt jünger als die Seite: das Datum des Abschnitts' );
update_option( 'wp_page_for_privacy_policy', $mmm_page );
mmm_ppt_assert( $mmm_stamp['time'] === Mixmymeals_Privacy::policy_updated(), 'ohne Seite: die Datenschutzseite aus den Einstellungen' );

mmm_ppt_assert( shortcode_exists( 'mmm_privacy_policy_date' ), 'der Datums-Shortcode ist registriert' );
$mmm_out = do_shortcode( '[mmm_privacy_policy_date post_id="' . $mmm_page . '" format="Y-m-d"]' );
mmm_ppt_assert( wp_date( 'Y-m-d', $mmm_stamp['time'] ) === $mmm_out, 'Shortcode: das Datum im gewünschten Format, nur Text' );
mmm_ppt_assert( wp_date( (string) get_option( 'date_format' ), $mmm_stamp['time'] ) === do_shortcode( '[mmm_privacy_policy_date]' ), 'ohne Format: das Datumsformat der Website' );
wp_delete_post( $mmm_page, true );

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
