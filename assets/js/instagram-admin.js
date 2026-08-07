/**
 * MixMyMeals – Instagram image generation admin script.
 *
 * Used on two screens:
 * 1. Settings page: media-library picker for font files (TTF/OTF) and logo.
 * 2. Meal plan edit screen: sequential AJAX generation of the carousel
 *    images (one request per image, so shared hosting never times out).
 */
jQuery(function ($) {
    'use strict';

    /* ===========================
       FILE PICKER (settings page)
       ============================ */
    $(document).on('click', '.mmm-select-file-button', function (e) {
        e.preventDefault();

        const $btn = $(this);
        const $target = $('#' + $btn.data('target'));

        const frame = wp.media({
            title: $btn.data('title') || 'Datei auswählen',
            button: { text: 'Übernehmen' },
            multiple: false
        });

        frame.on('select', function () {
            const attachment = frame.state().get('selection').first().toJSON();
            $target.val(attachment.id);
            $btn.closest('.mmm-file-field')
                .find('.mmm-file-name')
                .text(attachment.filename || attachment.title);
        });

        frame.open();
    });

    $(document).on('click', '.mmm-clear-file-button', function (e) {
        e.preventDefault();

        const $btn = $(this);
        $('#' + $btn.data('target')).val('');
        $btn.closest('.mmm-file-field').find('.mmm-file-name').text('—');
    });

    /* ===========================
       META BOX (meal plan screen)
       ============================ */
    const $box = $('#mmm-instagram-box');
    if (!$box.length) {
        return;
    }

    /* Copy a per-slide caption to the clipboard. */
    $box.on('click', '.mmm-copy-caption', function () {
        const $btn = $(this);
        const field = document.getElementById($btn.data('target'));
        if (!field) {
            return;
        }

        const done = function () {
            const label = $btn.text();
            $btn.text('Kopiert!');
            setTimeout(function () { $btn.text(label); }, 1500);
        };

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(field.value).then(done);
        } else {
            field.select();
            document.execCommand('copy');
            done();
        }
    });

    const planId = $box.data('plan');
    const nonce = $box.data('nonce');
    const jobs = $box.data('jobs') || [];

    const $generate = $('#mmm-instagram-generate');
    const $delete = $('#mmm-instagram-delete');
    const $status = $('#mmm-instagram-status');
    const $grid = $('#mmm-instagram-grid');

    function addFigure(data) {
        const $figure = $('<figure>')
            .css({ margin: 0, width: '150px', textAlign: 'center' });

        const $img = $('<img>', { src: data.url, alt: '' })
            .css({ width: '100%', height: 'auto', borderRadius: '6px', display: 'block' });

        $figure.append($('<a>', { href: data.url, download: '' }).append($img));
        $figure.append(
            $('<figcaption>')
                .css({ fontSize: '11px', paddingTop: '4px' })
                .append(document.createTextNode(data.label))
                .append($('<br>'))
                .append(document.createTextNode(data.date))
        );

        $grid.append($figure);
    }

    function setBusy(busy) {
        $generate.prop('disabled', busy);
        $delete.prop('disabled', busy);
    }

    /**
     * Holt die Klartext-Ursache aus einer fehlgeschlagenen AJAX-Antwort.
     *
     * Wichtig: wp_send_json_error() setzt den HTTP-Status (400/403/500), deshalb
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
            return 'Zeitüberschreitung. Instagram verarbeitet Bilder asynchron – '
                 + 'prüfe nach dem Neuladen, ob der Beitrag doch angelegt wurde.';
        }

        if (jqXHR && jqXHR.status === 0) {
            return 'Keine Verbindung zum Server (Netzwerk oder Seite neu geladen?).';
        }

        if (jqXHR && jqXHR.status) {
            // Kein JSON – meist ein PHP-Fatal oder ein Speicherlimit beim
            // Rendern, das als HTML zurückkommt.
            const raw = (jqXHR.responseText || '').replace(/<[^>]*>/g, ' ')
                .replace(/\s+/g, ' ').trim();
            const detail = raw ? ' – ' + raw.slice(0, 200) : '';
            return 'HTTP ' + jqXHR.status + ' ' + (errorThrown || '') + detail;
        }

        return fallback;
    }

    $generate.on('click', function () {
        setBusy(true);
        $grid.empty();

        let i = 0;

        function fail(message) {
            $status.text('Bild konnte nicht erzeugt werden: '
                + (message || 'Unbekannte Ursache.'));
            setBusy(false);
        }

        function next() {
            if (i >= jobs.length) {
                $status.text('Fertig – ' + jobs.length + ' Bilder erzeugt.');
                $generate.text('Bilder neu generieren');
                setBusy(false);
                // Images now exist, so posting is possible (if the token is set,
                // the button is present). Enable it without a page reload.
                $('#mmm-instagram-post').prop('disabled', false);
                return;
            }

            $status.text('Erzeuge Bild ' + (i + 1) + ' von ' + jobs.length + ' …');

            $.post(ajaxurl, {
                action: 'mmm_instagram_render',
                plan_id: planId,
                nonce: nonce,
                job: jobs[i]
            }).done(function (res) {
                if (res && res.success) {
                    addFigure(res.data);
                    i++;
                    next();
                } else {
                    fail(res && res.data && res.data.message);
                }
            }).fail(function (jqXHR, textStatus, errorThrown) {
                fail(failureReason(jqXHR, textStatus, errorThrown, 'Unbekannte Ursache.'));
            });
        }

        next();
    });

    $delete.on('click', function () {
        setBusy(true);
        $status.text('Lösche Bilder …');

        $.post(ajaxurl, {
            action: 'mmm_instagram_delete',
            plan_id: planId,
            nonce: nonce
        }).done(function (res) {
            if (res && res.success) {
                $grid.empty();
                $status.text('Bilder gelöscht.');
                $generate.text('Bilder generieren').prop('disabled', false);
                $delete.prop('disabled', true);
                $('#mmm-instagram-post').prop('disabled', true);
            } else {
                $status.text('Löschen fehlgeschlagen: '
                    + ((res && res.data && res.data.message) || 'Unbekannte Ursache.'));
                setBusy(false);
            }
        }).fail(function (jqXHR, textStatus, errorThrown) {
            $status.text('Löschen fehlgeschlagen: '
                + failureReason(jqXHR, textStatus, errorThrown, 'Unbekannte Ursache.'));
            setBusy(false);
        });
    });

    /* ===========================
       POST TO INSTAGRAM
       ============================ */
    const $post = $('#mmm-instagram-post');
    const $postStatus = $('#mmm-instagram-post-status');

    function postFail(message) {
        $postStatus.text('Posten fehlgeschlagen: ' + (message || 'Unbekannte Ursache.'));
        $post.prop('disabled', false);
    }

    function publishStep(children, caption, carouselId, attempt) {
        if (attempt > 0) {
            $postStatus.text('Instagram verarbeitet die Bilder … (Versuch ' + (attempt + 1) + ')');
        } else {
            $postStatus.text('Veröffentliche …');
        }

        $.post(ajaxurl, {
            action: 'mmm_instagram_post_publish',
            plan_id: planId,
            nonce: nonce,
            caption: caption,
            children: children,
            carousel_id: carouselId
        }).done(function (res) {
            if (!res || !res.success) {
                postFail(res && res.data && res.data.message);
                return;
            }

            if (res.data.retry) {
                if (attempt >= 10) {
                    postFail('Instagram braucht ungewöhnlich lange – bitte in ein paar Minuten erneut versuchen.');
                    return;
                }
                setTimeout(function () {
                    publishStep(children, caption, res.data.carousel_id || carouselId, attempt + 1);
                }, 5000);
                return;
            }

            $postStatus.empty().append(document.createTextNode('Veröffentlicht! '));
            if (res.data.permalink) {
                $postStatus.append($('<a>', {
                    href: res.data.permalink,
                    target: '_blank',
                    rel: 'noopener',
                    text: 'Post ansehen'
                }));
            }
            $post.prop('disabled', false);
        }).fail(function (jqXHR, textStatus, errorThrown) {
            postFail(failureReason(jqXHR, textStatus, errorThrown, 'Unbekannte Ursache.'));
        });
    }

    /* ===========================
       SCHEDULED PUBLISHING
       ============================ */
    const $scheduleStatus = $('#mmm-instagram-schedule-status');

    $box.on('click', '#mmm-instagram-schedule', function () {
        const when = $('#mmm-instagram-when').val();
        if (!when) {
            $scheduleStatus.text('Bitte Datum und Uhrzeit wählen.');
            return;
        }

        $scheduleStatus.text('Plane …');

        $.post(ajaxurl, {
            action: 'mmm_instagram_schedule',
            plan_id: planId,
            nonce: nonce,
            when: when,
            caption: $('#mmm-instagram-caption').val() || ''
        }).done(function (res) {
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

    $box.on('click', '#mmm-instagram-unschedule', function () {
        $scheduleStatus.text('Breche ab …');

        $.post(ajaxurl, {
            action: 'mmm_instagram_unschedule',
            plan_id: planId,
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

    $post.on('click', function () {
        if (!window.confirm('Wirklich auf Instagram veröffentlichen?')) {
            return;
        }

        const caption = $('#mmm-instagram-caption').val() || '';

        $post.prop('disabled', true);
        $postStatus.text('Übergebe Bilder an Instagram …');

        $.post(ajaxurl, {
            action: 'mmm_instagram_post_start',
            plan_id: planId,
            nonce: nonce,
            caption: caption
        }).done(function (res) {
            if (res && res.success) {
                publishStep(res.data.children, caption, '', 0);
            } else {
                postFail(res && res.data && res.data.message);
            }
        }).fail(function (jqXHR, textStatus, errorThrown) {
            postFail(failureReason(jqXHR, textStatus, errorThrown, 'Unbekannte Ursache.'));
        });
    });
});
