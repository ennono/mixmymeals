/**
 * MixMyMeals – admin actions panel on plan/recipe singles.
 *
 * Drives the [mmm_admin_actions] shortcode: Instagram/Pinterest posting and
 * scheduling plus WordPress status actions, all via the plugin's REST API
 * (cookie auth + wp_rest nonce from the localized mmmBoard object).
 */
(function () {
    'use strict';

    const panel = document.querySelector('.mmm-admin-actions');
    if (!panel || typeof window.mmmBoard === 'undefined') {
        return;
    }

    const postId = panel.getAttribute('data-id');

    function api(path, body) {
        return fetch(window.mmmBoard.restRoot + path, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': window.mmmBoard.nonce
            },
            body: JSON.stringify(body || {})
        }).then(function (res) {
            return res.json().then(function (data) {
                if (!res.ok) {
                    throw new Error((data && data.message) || ('HTTP ' + res.status));
                }
                return data;
            });
        });
    }

    function msgEl(scope) {
        return scope.querySelector('.mmm-admin-board__msg');
    }

    function say(scope, text, ok) {
        const el = msgEl(scope);
        if (el) {
            el.textContent = text;
            el.className = 'mmm-admin-board__msg' + (ok === true ? ' is-ok' : ok === false ? ' is-fail' : '');
        }
    }

    function setBusy(scope, busy) {
        scope.querySelectorAll('button').forEach(function (btn) {
            btn.disabled = busy;
        });
    }

    panel.addEventListener('click', function (event) {
        const btn = event.target.closest('button[data-action]');
        if (!btn) {
            return;
        }

        const scope = btn.closest('.mmm-admin-actions__service') || panel;
        const serviceEl = btn.closest('[data-service]');
        const service = serviceEl ? serviceEl.getAttribute('data-service') : null;
        const action = btn.getAttribute('data-action');

        // ── Instagram / Pinterest ────────────────────────────────────────
        if ('publish' === action && service) {
            if (!window.confirm('Wirklich jetzt auf ' + (service === 'instagram' ? 'Instagram' : 'Pinterest') + ' posten?')) {
                return;
            }
            setBusy(scope, true);
            say(scope, 'Poste … (kann bei Plänen eine Minute dauern)');
            api(service + '/' + postId + '/publish').then(function (data) {
                const url = data.permalink || data.pin_url || '';
                say(scope, 'Veröffentlicht!', true);
                if (url) {
                    const a = document.createElement('a');
                    a.href = url;
                    a.target = '_blank';
                    a.rel = 'noopener';
                    a.textContent = ' Ansehen';
                    msgEl(scope).appendChild(a);
                }
                setBusy(scope, false);
            }).catch(function (err) {
                say(scope, 'Fehler: ' + err.message, false);
                setBusy(scope, false);
            });
            return;
        }

        if ('schedule' === action && service) {
            const input = scope.querySelector('input[type="datetime-local"]');
            if (!input || !input.value) {
                say(scope, 'Bitte Datum und Uhrzeit wählen.', false);
                return;
            }
            setBusy(scope, true);
            say(scope, 'Plane …');
            api(service + '/' + postId + '/schedule', { when: input.value }).then(function () {
                window.location.reload();
            }).catch(function (err) {
                say(scope, 'Fehler: ' + err.message, false);
                setBusy(scope, false);
            });
            return;
        }

        if ('unschedule' === action && service) {
            setBusy(scope, true);
            api(service + '/' + postId + '/unschedule').then(function () {
                window.location.reload();
            }).catch(function (err) {
                say(scope, 'Fehler: ' + err.message, false);
                setBusy(scope, false);
            });
            return;
        }

        // ── WordPress status ─────────────────────────────────────────────
        if ('wp-status' === action) {
            const status = btn.getAttribute('data-status');
            setBusy(scope, true);
            api('posts/' + postId + '/status', { status: status }).then(function () {
                // Update the badge and flip the button in place: a page reload
                // would 404 for drafts (pretty permalinks resolve publish only).
                const badge = panel.querySelector('[data-status-badge]');
                if (badge) {
                    badge.textContent = status === 'draft' ? 'Entwurf' : 'Veröffentlicht';
                    badge.className = 'mmm-admin-board__status mmm-admin-board__status--' + status;
                }
                btn.setAttribute('data-status', status === 'draft' ? 'publish' : 'draft');
                btn.textContent = status === 'draft' ? 'Veröffentlichen' : 'Auf Entwurf setzen';
                say(scope, status === 'draft' ? 'Auf Entwurf gesetzt — Seite ist nicht mehr öffentlich.' : 'Veröffentlicht.', true);
                setBusy(scope, false);
            }).catch(function (err) {
                say(scope, 'Fehler: ' + err.message, false);
                setBusy(scope, false);
            });
            return;
        }

        if ('wp-trash' === action) {
            if (!window.confirm('Wirklich in den Papierkorb verschieben?')) {
                return;
            }
            setBusy(scope, true);
            api('posts/' + postId + '/status', { status: 'trash' }).then(function () {
                window.location.href = btn.getAttribute('data-redirect') || '/';
            }).catch(function (err) {
                say(scope, 'Fehler: ' + err.message, false);
                setBusy(scope, false);
            });
        }
    });
})();
