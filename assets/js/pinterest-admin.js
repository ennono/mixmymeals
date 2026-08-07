/**
 * MixMyMeals – Pinterest admin script.
 *
 * Drives the Pinterest meta box on the meal plan / recipe edit screens:
 * immediate pinning and scheduling (both via AJAX).
 */
jQuery(function ($) {
    'use strict';

    const $box = $('#mmm-pinterest-box');
    if (!$box.length) {
        return;
    }

    const postId = $box.data('post');
    const nonce = $box.data('nonce');

    const $status = $('#mmm-pinterest-status');
    const $scheduleStatus = $('#mmm-pinterest-schedule-status');

    function fields() {
        return {
            title: $('#mmm-pinterest-title').val() || '',
            description: $('#mmm-pinterest-desc').val() || ''
        };
    }

    /**
     * Holt die Klartext-Ursache aus einer fehlgeschlagenen AJAX-Antwort.
     *
     * Wichtig: wp_send_json_error() setzt den HTTP-Status (403/400/500), deshalb
     * landet der Aufruf in .fail() und NICHT in .done() – die Meldung aus PHP
     * steckt dann in jqXHR.responseJSON. Ohne diese Auswertung ginge genau die
     * Information verloren, die erklärt, was schiefging.
     *
     * @param {Object} jqXHR       jQuery-XHR-Objekt.
     * @param {string} textStatus  'timeout', 'error', 'abort', 'parsererror'.
     * @param {string} errorThrown HTTP-Statustext.
     * @param {string} fallback    Text, wenn sich gar nichts ermitteln lässt.
     * @return {string}
     */
    function failureReason(jqXHR, textStatus, errorThrown, fallback) {
        const fromJson = jqXHR && jqXHR.responseJSON
            && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message;

        if (fromJson) {
            return fromJson;
        }

        if (textStatus === 'timeout') {
            return 'Zeitüberschreitung. Das Erzeugen des Bildes kann länger dauern – '
                 + 'prüfe im Pinterest-Kasten nach dem Neuladen, ob der Pin doch angelegt wurde.';
        }

        if (jqXHR && jqXHR.status === 0) {
            return 'Keine Verbindung zum Server (Netzwerk oder Seite neu geladen?).';
        }

        if (jqXHR && jqXHR.status) {
            // Kein JSON – meist ein PHP-Fatal, das als HTML zurückkommt.
            const raw = (jqXHR.responseText || '').replace(/<[^>]*>/g, ' ')
                .replace(/\s+/g, ' ').trim();
            const detail = raw ? ' – ' + raw.slice(0, 200) : '';
            return 'HTTP ' + jqXHR.status + ' ' + (errorThrown || '') + detail;
        }

        return fallback;
    }

    $box.on('click', '#mmm-pinterest-pin', function () {
        if (!window.confirm('Wirklich auf Pinterest pinnen?')) {
            return;
        }

        const $btn = $(this);
        $btn.prop('disabled', true);
        $status.text('Erzeuge Bild und pinne …');

        $.post(ajaxurl, Object.assign({
            action: 'mmm_pinterest_pin',
            post_id: postId,
            nonce: nonce
        }, fields())).done(function (res) {
            if (res && res.success) {
                $status.empty().append(document.createTextNode('Gepinnt! '));
                if (res.data.pin_url) {
                    $status.append($('<a>', {
                        href: res.data.pin_url,
                        target: '_blank',
                        rel: 'noopener',
                        text: 'Pin ansehen'
                    }));
                }
            } else {
                $status.text('Pinnen fehlgeschlagen: '
                    + ((res && res.data && res.data.message) || 'Unbekannte Ursache.'));
            }
            $btn.prop('disabled', false);
        }).fail(function (jqXHR, textStatus, errorThrown) {
            $status.text('Pinnen fehlgeschlagen: '
                + failureReason(jqXHR, textStatus, errorThrown, 'Unbekannte Ursache.'));
            $btn.prop('disabled', false);
        });
    });

    $box.on('click', '#mmm-pinterest-schedule', function () {
        const when = $('#mmm-pinterest-when').val();
        if (!when) {
            $scheduleStatus.text('Bitte Datum und Uhrzeit wählen.');
            return;
        }

        $scheduleStatus.text('Plane …');

        $.post(ajaxurl, Object.assign({
            action: 'mmm_pinterest_schedule',
            post_id: postId,
            nonce: nonce,
            when: when
        }, fields())).done(function (res) {
            if (res && res.success) {
                $scheduleStatus.text('Geplant für ' + res.data.scheduled + ' – Seite lädt neu …');
                window.location.reload();
            } else {
                $scheduleStatus.text('Planen fehlgeschlagen: '
                    + ((res && res.data && res.data.message) || 'Unbekannte Ursache.'));
            }
        }).fail(function (jqXHR, textStatus, errorThrown) {
            $scheduleStatus.text('Planen fehlgeschlagen: '
                + failureReason(jqXHR, textStatus, errorThrown, 'Unbekannte Ursache.'));
        });
    });

    $box.on('click', '#mmm-pinterest-unschedule', function () {
        $scheduleStatus.text('Breche ab …');

        $.post(ajaxurl, {
            action: 'mmm_pinterest_unschedule',
            post_id: postId,
            nonce: nonce
        }).done(function (res) {
            if (res && res.success) {
                window.location.reload();
            } else {
                $scheduleStatus.text('Abbrechen fehlgeschlagen: '
                    + ((res && res.data && res.data.message) || 'Unbekannte Ursache.'));
            }
        }).fail(function (jqXHR, textStatus, errorThrown) {
            $scheduleStatus.text('Abbrechen fehlgeschlagen: '
                + failureReason(jqXHR, textStatus, errorThrown, 'Unbekannte Ursache.'));
        });
    });
});
