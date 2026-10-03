jQuery(function ($) {

    /* ==========================================================
       AJAX HELPER
       ========================================================== */
    function mmmPost(action, data) {
        return $.ajax({
            url: mixmymeals.ajax_url,
            method: 'POST',
            dataType: 'json',
            data: Object.assign({
                action: action,
                nonce: mixmymeals.nonce
            }, data || {})
        });
    }

    /* ==========================================================
       UI HELPERS
       ========================================================== */
    function mmmFlashText($el, text, ms) {
        if (!$el || !$el.length) return;
        const old = $el.text();
        $el.text(text);
        setTimeout(function () {
            // nur zurücksetzen, wenn sich der Text nicht zwischenzeitlich geändert hat
            if ($el.text() === text) $el.text(old);
        }, ms || 1200);
    }

    function mmmShowInlineMessage($el, text, type) {
        if (!$el || !$el.length) return;

        $el.text(text || '');
        $el.removeClass('mmm-msg--ok mmm-msg--error');

        if (type === 'ok') $el.addClass('mmm-msg--ok');
        if (type === 'error') $el.addClass('mmm-msg--error');
    }

    function mmmShowCartBadgeError() {
        const badges = $('.mmm-cart-badge');
        if (!badges.length) return;

        badges.each(function () {
            const badge = $(this);
            const oldText = badge.text();

            badge.text('✕');
            badge.addClass('mmm-cart-badge--error');

            setTimeout(function () {
                badge.text(oldText);
                badge.removeClass('mmm-cart-badge--error');
            }, 900);
        });
    }

    /**
     * Zentrale Fehlerbehandlung:
     * - nutzt response.data.code
     * - zeigt je nach code UI (Badge X / Inline Message)
     * - alert() nur als Fallback
     */
    function mmmHandleAjaxError(response, opts) {
        opts = opts || {};
        const data = (response && response.data) ? response.data : {};
        const code = data.code || '';
        const message = data.message || opts.fallbackMessage || 'Error.';

        // 1) Spezifische Codes -> spezifische UI
        if (code === 'cart_limit_reached') {
            mmmShowCartBadgeError();
            if (opts.inlineEl) mmmShowInlineMessage(opts.inlineEl, message, 'error');
            return;
        }

        if (code === 'rate_limit' || code === 'too_many_requests') {
            // Rate limit: lieber inline als alert
            if (opts.inlineEl) {
                mmmShowInlineMessage(opts.inlineEl, message, 'error');
            } else {
                mmmShowCartBadgeError(); // wenigstens ein Signal
            }
            return;
        }

        if (code === 'login_required') {
            if (opts.inlineEl) {
                mmmShowInlineMessage(opts.inlineEl, message, 'error');
            } else {
                // kein hard alert, aber fallback wenn nix da ist:
                alert(message);
            }
            return;
        }

        if (code === 'invalid_items' || code === 'validation_failed') {
            if (opts.inlineEl) {
                mmmShowInlineMessage(opts.inlineEl, message, 'error');
                return;
            }
        }

        // 2) Wenn wir eine Inline-Box haben, nutze die
        if (opts.inlineEl) {
            mmmShowInlineMessage(opts.inlineEl, message, 'error');
            return;
        }

        // 3) Fallback (nur wenn wirklich nichts Besseres möglich ist)
        alert(message);
    }

    function mmmHandleRequestFail(xhr, opts) {
        opts = opts || {};
        let json = null;
        try { json = xhr.responseJSON; } catch (e) {}

        // Wenn WP json error payload liefert, normal behandeln
        if (json && typeof json === 'object') {
            mmmHandleAjaxError(json, opts);
            return;
        }

        // echter Netzwerk-/Serverfehler ohne JSON
        const msg = opts.failMessage || 'Request failed.';
        if (opts.inlineEl) {
            mmmShowInlineMessage(opts.inlineEl, msg, 'error');
            return;
        }
        // fallback
        alert(msg);
    }

    /* ==========================================================
       CART BADGE
       ========================================================== */
    // The bar first, so its own badge is in the DOM when the count arrives
    // and both are filled by the same pass.
    mmmEnsureCartBar();
    mmmUpdateCartBadge();

    /* ==========================================================
       ADD TO CART (Einplanen-Dialog)
       ========================================================== */
    function mmmClosePlannerDialog() {
        const backdrop = $('.mmm-planner-dialog-backdrop');
        if (backdrop.length) {
            $(document).off('keydown.mmmPlanner');
            backdrop.remove();
        }
    }

    function mmmOpenPlannerDialog(recipeId, defaultServings, recipeTitle) {
        const labels = mixmymeals.planner_dialog || {};
        const types = labels.types || {};
        const days = labels.days || {};

        mmmClosePlannerDialog();

        let typeOptions = '<option value="">' + (labels.no_type || '—') + '</option>';
        Object.keys(types).forEach(function (key) {
            typeOptions += '<option value="' + key + '">' + types[key] + '</option>';
        });

        let dayOptions = '<option value="">' + (labels.no_day || '—') + '</option>';
        Object.keys(days).forEach(function (key) {
            dayOptions += '<option value="' + key + '">' + days[key] + '</option>';
        });

        const backdrop = $(
            '<div class="mmm-planner-dialog-backdrop">' +
                '<div class="mmm-planner-dialog" role="dialog" aria-modal="true">' +
                    '<h4>' + (labels.title || '') + '</h4>' +
                    '<label>' + (labels.servings || '') +
                        '<input type="number" min="1" class="mmm-planner-dialog-servings" value="' + defaultServings + '">' +
                    '</label>' +
                    '<label>' + (labels.type || '') +
                        '<select class="mmm-planner-dialog-type">' + typeOptions + '</select>' +
                    '</label>' +
                    '<label>' + (labels.day || '') +
                        '<select class="mmm-planner-dialog-day">' + dayOptions + '</select>' +
                    '</label>' +
                    '<div class="mmm-msg mmm-planner-dialog-msg"></div>' +
                    '<div class="mmm-planner-dialog-actions">' +
                        '<button type="button" class="mmm-planner-dialog-cancel">' + (labels.cancel || '') + '</button>' +
                        '<button type="button" class="mmm-planner-dialog-submit">' + (labels.submit || '') + '</button>' +
                    '</div>' +
                '</div>' +
            '</div>'
        );

        $('body').append(backdrop);
        backdrop.find('.mmm-planner-dialog-servings').trigger('focus');

        backdrop.on('click', function (e) {
            if (e.target === this) mmmClosePlannerDialog();
        });
        backdrop.find('.mmm-planner-dialog-cancel').on('click', mmmClosePlannerDialog);
        $(document).on('keydown.mmmPlanner', function (e) {
            if (e.key === 'Escape') mmmClosePlannerDialog();
        });

        backdrop.find('.mmm-planner-dialog-submit').on('click', function () {
            const msgBox = backdrop.find('.mmm-planner-dialog-msg');
            const $submit = $(this);
            mmmSetBusy($submit, true);

            mmmPost('mmm_add_to_cart', {
                recipe_id: recipeId,
                servings: parseInt(backdrop.find('.mmm-planner-dialog-servings').val(), 10) || 1,
                type: backdrop.find('.mmm-planner-dialog-type').val() || '',
                day: backdrop.find('.mmm-planner-dialog-day').val() || null
            })
                .done(function (response) {
                    if (response && response.success) {
                        const cfg = (window.mixmymeals && mixmymeals.planner_dialog) || {};
                        const added = cfg.added || {};
                        const count = response.data && response.data.count;
                        const countText = (typeof count === 'number')
                            ? mmmFormat(count === 1 ? added.count_one : added.count_many, String(count))
                            : '';

                        mmmAnnounce(
                            mmmAddedText(
                                recipeTitle,
                                backdrop.find('.mmm-planner-dialog-day').val() || '',
                                backdrop.find('.mmm-planner-dialog-type').val() || ''
                            ),
                            countText
                        );

                        mmmUpdateCartBadge();
                        mmmClosePlannerDialog();
                        // On the planner page, reload so the new item shows
                        // up in the plan builder immediately.
                        if ($('.mmm-plan-builder').length) {
                            window.location.reload();
                        }
                        return;
                    }
                    mmmSetBusy($submit, false);
                    mmmHandleAjaxError(response, { inlineEl: msgBox, fallbackMessage: 'Error.' });
                })
                .fail(function (xhr) {
                    mmmSetBusy($submit, false);
                    mmmHandleRequestFail(xhr, { inlineEl: msgBox, failMessage: 'Request failed.' });
                });
        });
    }

    /* ==========================================================
       BOTTOM BAR
       ========================================================== */

    /**
     * A bar at the foot of the screen while the planner is not empty.
     *
     * The count and the only way into the planner sit in the header, at the
     * very top of pages that run to twenty thousand pixels — the worst place
     * a thumb can reach. This repeats both where the hand already is.
     *
     * Built from the header's own planner link rather than from a setting:
     * that link is wherever the site owner put the [mmm_cart_badge], and a
     * second place that has to know the address would sooner or later know a
     * different one. No link, no bar.
     */
    function mmmEnsureCartBar() {
        if ($('.mmm-cart-bar').length) return;

        // On the planner itself the bar would link to the page you are on.
        if ($('.mmm-plan-builder').length) return;

        const headerBadge = $('.mmm-cart-badge').first();
        const link = headerBadge.closest('a');
        if (!link.length || !link.attr('href')) return;

        const bar = $('<div class="mmm-cart-bar"></div>');
        $('<span class="mmm-cart-badge mmm-cart-bar__count"></span>')
            .attr('data-label-singular', headerBadge.data('label-singular') || '')
            .attr('data-label-plural', headerBadge.data('label-plural') || '')
            .text(headerBadge.text())
            .appendTo(bar);

        const labels = (window.mixmymeals && mixmymeals.planner_dialog && mixmymeals.planner_dialog.added) || {};

        $('<a class="mmm-cart-bar__link"></a>')
            .attr('href', link.attr('href'))
            .text(labels.to_plan || link.text().trim())
            .appendTo(bar);

        $('body').append(bar);
    }

    /* ==========================================================
       SAYING WHAT HAPPENED
       ========================================================== */

    /**
     * The one live region on the page, made on first use.
     *
     * Adding a recipe changed nothing a reader could see: the dialog closed
     * and the only trace was a badge in a header that is far above the fold
     * on a long archive. A screen reader heard nothing at all — there was no
     * aria-live anywhere on the site.
     */
    function mmmStatusRegion() {
        let region = $('#mmm-status');
        if (!region.length) {
            region = $('<div id="mmm-status" class="screen-reader-text" role="status" aria-live="polite"></div>');
            $('body').append(region);
        }
        return region;
    }

    /**
     * Announces something and shows it briefly.
     *
     * One source for both, so what is read aloud and what is shown cannot
     * drift apart. The toast carries a link to the planner where there is one
     * — on a long page that is also the shortest way back to it.
     *
     * @param {string} text  What happened.
     * @param {string} [extra] A second, quieter line.
     */
    function mmmAnnounce(text, extra) {
        if (!text) return;

        mmmStatusRegion().text(extra ? text + ' ' + extra : text);

        $('.mmm-toast').remove();

        const toast = $('<div class="mmm-toast" aria-hidden="true"></div>');
        $('<span class="mmm-toast__text"></span>').text(text).appendTo(toast);

        if (extra) {
            $('<span class="mmm-toast__extra"></span>').text(extra).appendTo(toast);
        }

        // The planner link is wherever the header already points; no second
        // place that has to know the URL.
        const plannerHref = $('.mmm-cart-badge').closest('a').attr('href');
        const toPlan = (window.mixmymeals && mixmymeals.planner_dialog && mixmymeals.planner_dialog.added)
            ? mixmymeals.planner_dialog.added.to_plan
            : '';

        if (plannerHref && toPlan) {
            $('<a class="mmm-toast__link"></a>').attr('href', plannerHref).text(toPlan).appendTo(toast);
        }

        $('body').append(toast);

        // Two frames, so the transition has a state to come from.
        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(function () {
                toast.addClass('is-visible');
            });
        });

        window.setTimeout(function () {
            toast.removeClass('is-visible');
            window.setTimeout(function () { toast.remove(); }, 400);
        }, 5000);
    }

    /**
     * Wording for "this was planned", assembled from what was actually chosen.
     *
     * Three sentences rather than one with holes in it: "X planned for , ."
     * is what a single template produces when nobody picked a day or a type.
     *
     * @param {string} recipe Recipe name.
     * @param {string} day    Day key, '' or null for none.
     * @param {string} type   Type key, '' for none.
     * @returns {string}
     */
    function mmmAddedText(recipe, day, type) {
        const cfg = (window.mixmymeals && mixmymeals.planner_dialog) || {};
        const t = cfg.added || {};
        const dayLabel = (cfg.days && day) ? cfg.days[day] : '';
        const typeLabel = (cfg.types && type) ? cfg.types[type] : '';

        if (dayLabel && typeLabel) return mmmFormat(t.full, recipe, dayLabel, typeLabel);
        if (dayLabel) return mmmFormat(t.day, recipe, dayLabel);
        if (typeLabel) return mmmFormat(t.type, recipe, typeLabel);
        return mmmFormat(t.plain, recipe);
    }

    /** Fills %1$s/%2$s/%3$s or a single %s, the way the PHP side writes them. */
    function mmmFormat(template, ...args) {
        if (!template) return args[0] || '';
        let i = 0;
        return String(template)
            .replace(/%(\d)\$s/g, (m, n) => args[parseInt(n, 10) - 1] || '')
            .replace(/%s/g, () => args[i++] || '');
    }

    /* Unified busy state for AJAX-triggering buttons/links: disables the
       control, exposes aria-busy for assistive tech and adds .is-busy for
       the spinner/opacity styling. */
    function mmmSetBusy($el, busy) {
        $el.prop('disabled', !!busy)
            .toggleClass('is-busy', !!busy);
        if (busy) {
            $el.attr('aria-busy', 'true');
        } else {
            $el.removeAttr('aria-busy');
        }
    }

    $(document).on('click', '.mmm-recipe-actions__item--cart', function (e) {
        e.preventDefault();
        const btn = $(this);
        mmmOpenPlannerDialog(
            btn.data('recipe-id'),
            parseInt(btn.data('servings'), 10) || 1,
            btn.data('recipe-title') || ''
        );
    });

    /* A confirmation popup that reuses the planner-dialog markup/styling, so it
       looks exactly like the "Einplanen" dialog. onConfirm receives a context
       with an inline message box and a close() helper, so the caller can keep
       the dialog open and show errors, or close it on success. */
    function mmmOpenConfirmDialog(opts) {
        opts = opts || {};
        mmmClosePlannerDialog();

        const backdrop = $(
            '<div class="mmm-planner-dialog-backdrop">' +
                '<div class="mmm-planner-dialog mmm-planner-dialog--confirm" role="dialog" aria-modal="true">' +
                    '<h4></h4>' +
                    '<p class="mmm-planner-dialog-text"></p>' +
                    '<div class="mmm-msg mmm-planner-dialog-msg"></div>' +
                    '<div class="mmm-planner-dialog-actions">' +
                        '<button type="button" class="mmm-planner-dialog-cancel"></button>' +
                        '<button type="button" class="mmm-planner-dialog-submit"></button>' +
                    '</div>' +
                '</div>' +
            '</div>'
        );

        // Use .text() so labels/messages can never inject markup.
        backdrop.find('h4').text(opts.title || '');
        backdrop.find('.mmm-planner-dialog-text').text(opts.message || '');
        backdrop.find('.mmm-planner-dialog-cancel').text(opts.cancel || '');
        backdrop.find('.mmm-planner-dialog-submit').text(opts.confirm || '');

        $('body').append(backdrop);
        backdrop.find('.mmm-planner-dialog-submit').trigger('focus');

        backdrop.on('click', function (e) {
            if (e.target === this) mmmClosePlannerDialog();
        });
        backdrop.find('.mmm-planner-dialog-cancel').on('click', function () {
            if (typeof opts.onCancel === 'function') {
                opts.onCancel();
            }
            mmmClosePlannerDialog();
        });
        $(document).on('keydown.mmmPlanner', function (e) {
            if (e.key === 'Escape') mmmClosePlannerDialog();
        });

        backdrop.find('.mmm-planner-dialog-submit').on('click', function () {
            if (typeof opts.onConfirm === 'function') {
                opts.onConfirm({
                    msgBox: backdrop.find('.mmm-planner-dialog-msg'),
                    close: mmmClosePlannerDialog
                });
                return;
            }
            mmmClosePlannerDialog();
        });
    }

    /* ==========================================================
       COOK MODE (Kochmodus)
       ========================================================== */
    let mmmWakeLock = null;

    function mmmReleaseWakeLock() {
        if (mmmWakeLock) {
            try { mmmWakeLock.release(); } catch (err) {}
            mmmWakeLock = null;
        }
    }

    function mmmCloseCookMode() {
        const overlay = $('.mmm-cook-mode');
        if (overlay.length) {
            $(document).off('keydown.mmmCookMode');
            overlay.remove();
            $('body').removeClass('mmm-cook-mode-open');
            mmmReleaseWakeLock();
        }
    }

    function mmmOpenCookMode() {
        const steps = $('.mmm-recipe-steps .mmm-step');
        if (!steps.length) return;

        const labels = mixmymeals.cook_mode || {};
        let current = 0;

        mmmCloseCookMode();

        const overlay = $(
            '<div class="mmm-cook-mode">' +
                '<div class="mmm-cook-mode-bar">' +
                    '<span class="mmm-cook-mode-progress"></span>' +
                    '<button type="button" class="mmm-cook-mode-exit" aria-label="Kochmodus beenden">' + (labels.exit || '✕') + '</button>' +
                '</div>' +
                '<div class="mmm-cook-mode-step"></div>' +
                '<div class="mmm-cook-mode-nav">' +
                    '<button type="button" class="mmm-cook-mode-prev" aria-label="Vorheriger Schritt">' + (labels.prev || '\u2039') + '</button>' +
                    '<button type="button" class="mmm-cook-mode-next" aria-label="N\u00e4chster Schritt">' + (labels.next || '\u203a') + '</button>' +
                '</div>' +
            '</div>'
        );

        function renderStep() {
            const total = steps.length;
            const progress = (labels.step_of || '%1$s / %2$s')
                .replace('%1$s', current + 1)
                .replace('%2$s', total);
            overlay.find('.mmm-cook-mode-progress').text(progress);
            overlay.find('.mmm-cook-mode-step').empty().append($(steps[current]).clone());
            overlay.find('.mmm-cook-mode-prev').prop('disabled', current === 0);
            overlay.find('.mmm-cook-mode-next').text(
                current === total - 1 ? (labels.done || '') : (labels.next || '')
            );
        }

        overlay.find('.mmm-cook-mode-exit').on('click', mmmCloseCookMode);
        overlay.find('.mmm-cook-mode-prev').on('click', function () {
            if (current > 0) { current--; renderStep(); }
        });
        overlay.find('.mmm-cook-mode-next').on('click', function () {
            if (current < steps.length - 1) {
                current++;
                renderStep();
            } else {
                mmmCloseCookMode();
            }
        });
        $(document).on('keydown.mmmCookMode', function (e) {
            if (e.key === 'Escape') mmmCloseCookMode();
            if (e.key === 'ArrowRight') overlay.find('.mmm-cook-mode-next').trigger('click');
            if (e.key === 'ArrowLeft') overlay.find('.mmm-cook-mode-prev').trigger('click');
        });

        $('body').addClass('mmm-cook-mode-open').append(overlay);
        renderStep();

        // Display anlassen, solange gekocht wird (wo unterstützt)
        if (navigator.wakeLock && navigator.wakeLock.request) {
            navigator.wakeLock.request('screen').then(function (lock) {
                mmmWakeLock = lock;
            }).catch(function () {});
        }
    }

    $(document).on('click', '.mmm-cook-mode-start', function (e) {
        e.preventDefault();
        mmmOpenCookMode();
    });

    /* ==========================================================
       START DAY SELECTION (reorders the day rail)
       ========================================================== */
    function mmmApplyStartDay(start) {
        const $days = $('.mmm-plan-days');
        if (!$days.length) return;
        const els = $days.children('.mmm-plan-day').get();
        els.sort(function (a, b) {
            const da = parseInt(a.getAttribute('data-day'), 10);
            const db = parseInt(b.getAttribute('data-day'), 10);
            if (!start) return da - db;             // auto: natural Mon→Sun
            return ((da - start + 7) % 7) - ((db - start + 7) % 7);
        });
        // Reattach in new order; keeps :last-child connector line correct.
        els.forEach(function (el) { $days.append(el); });
    }

    $(document).on('change', 'input[name="mmm-plan-startday"]', function () {
        mmmApplyStartDay(parseInt(this.value, 10) || 0);
    });

    /* ==========================================================
       PLANNER DAY TOTALS (summed kcal per person)
       ========================================================== */
    function mmmUpdatePlannerTotals() {
        const $builder = $('.mmm-plan-builder');
        if (!$builder.length) return;

        const sums = {};
        const counts = {};

        // The whole pattern, not just the unit: where the number goes is part
        // of the wording the site configured.
        const pattern = String($builder.data('kcal-pattern') || '{kcal} kcal');

        $builder.find('.mmm-plan-day').each(function () {
            const $day = $(this);
            let sum = 0;
            $day.find('.mmm-plan-item').each(function () {
                sum += parseInt(this.getAttribute('data-kcal'), 10) || 0;
            });

            const $total = $day.find('.mmm-plan-day-total');
            const $value = $total.find('[data-day-total]');

            if (sum > 0) {
                $value.text('≈ ' + pattern.replace('{kcal}', sum.toLocaleString()));
                $total.prop('hidden', false);
            } else {
                $value.text('');
                $total.prop('hidden', true);
            }

            const key = parseInt($day.find('.mmm-plan-day-list').attr('data-day-list'), 10);
            sums[key] = sum;
            counts[key] = $day.find('.mmm-plan-item').length;
        });

        mmmUpdatePlannerWeek($builder, sums, counts);
    }

    /**
     * The seven bars above the planner.
     *
     * Scaled against the fullest day rather than against an absolute: the
     * question the row answers is which days are heavy and which are still
     * empty, and a fixed ceiling would flatten every ordinary week into
     * nothing.
     *
     * **A day counts as planned by its meals, never by its energy** — the same
     * rule calc_nutrition_per_day_person() keeps. A dish whose recipe has no
     * nutrition data yet would otherwise leave its day looking empty, and
     * filling that data in later would make a bar appear where the plan had
     * not changed at all. So a planned day always shows a stub.
     *
     * @param {jQuery} $builder The planner.
     * @param {Object} sums     Day number to kcal.
     * @param {Object} counts   Day number to number of meals.
     */
    function mmmUpdatePlannerWeek($builder, sums, counts) {
        const $week = $builder.find('.mmm-plan-week');
        if (!$week.length) return;

        const max = Math.max.apply(null, Object.keys(sums).map(k => sums[k] || 0).concat([0]));
        const planned = Object.keys(counts).reduce((n, k) => n + (counts[k] || 0), 0);
        const startDay = parseInt($builder.find('input[name="mmm-plan-startday"]:checked').val(), 10) || 0;

        $week.find('.mmm-plan-week__day').each(function () {
            const $day = $(this);
            const key = parseInt($day.attr('data-week-day'), 10);
            const sum = sums[key] || 0;
            const has = (counts[key] || 0) > 0;
            const share = has ? Math.max(8, max > 0 ? Math.round((sum / max) * 100) : 8) : 0;

            $day.find('.mmm-plan-week__bar').css('height', share + '%');
            $day.toggleClass('is-filled', has);
            $day.toggleClass('is-start', key === startDay);
        });

        // Nothing planned at all: seven empty tracks say less than no row.
        $week.toggleClass('is-empty', planned === 0);
    }

    mmmUpdatePlannerTotals();

    // The start day marks a bar, so changing it has to repaint the row.
    $(document).on('change', 'input[name="mmm-plan-startday"]', mmmUpdatePlannerTotals);

    /* ==========================================================
       DRAG & DROP
       ========================================================== */
    let draggedItem = null;

    $(document).on('dragstart', '.mmm-plan-item', function (e) {
        draggedItem = this;
        $(this).addClass('dragging');
        e.originalEvent.dataTransfer.setData('text/plain', 'dummy');
        e.originalEvent.dataTransfer.effectAllowed = 'move';
    });

    $(document).on('dragend', '.mmm-plan-item', function () {
        $(this).removeClass('dragging');
        draggedItem = null;
    });

    $(document).on('dragover', '.mmm-plan-day-list, .mmm-plan-item', function (e) {
        if (!draggedItem) return;
        e.preventDefault();
        $(this).closest('.mmm-plan-day-list').addClass('drop-target');
    });

    $(document).on('dragleave', '.mmm-plan-day-list', function () {
        $(this).removeClass('drop-target');
    });

    /**
     * Moves one planner item into the list of a day.
     *
     * Shared by the drop handler and the day select, because the two must do
     * the same thing: the day of an item is read from its containing list when
     * the plan is created, so moving the element IS the change — saving it
     * without moving it would be lost, and moving without saving would be
     * forgotten on the next load.
     *
     * @param {jQuery} item   The .mmm-plan-item.
     * @param {number} newDay 0 for unassigned, 1-7 for a weekday.
     */
    function mmmMoveItemToDay(item, newDay) {
        const targetList = $('.mmm-plan-day-list[data-day-list="' + newDay + '"]').first();
        if (!targetList.length) return;

        targetList.append(item);
        item.attr('data-day', newDay);

        // Keep the select in step when the move came from a drag.
        item.find('.mmm-plan-item-day').val(String(newDay));

        mmmSavePlanChange({
            index: item.data('index'),
            field: 'day',
            value: newDay
        });

        mmmUpdatePlannerTotals();
    }

    $(document).on('drop', '.mmm-plan-day-list, .mmm-plan-item', function (e) {
        e.preventDefault();
        if (!draggedItem) return;

        const targetList = $(this).hasClass('mmm-plan-day-list')
            ? this
            : $(this).closest('.mmm-plan-day-list')[0];

        $(targetList).removeClass('drop-target');
        mmmMoveItemToDay($(draggedItem), $(targetList).data('day-list'));
    });

    /* The same move without a mouse. Dragging is unreachable on a phone —
       HTML5 drag and drop does not fire from a touch — and this is the only
       other way to give a recipe its day. */
    $(document).on('change', '.mmm-plan-item-day', function () {
        const item = $(this).closest('.mmm-plan-item');
        mmmMoveItemToDay(item, parseInt($(this).val(), 10) || 0);
    });

    /* ==========================================================
       REMOVE CART ITEM
       ========================================================== */
    $(document).on('click', '.mmm-plan-item-remove', function (e) {
        e.preventDefault();

        const btn = $(this);
        const item = btn.closest('.mmm-plan-item');
        const index = item.data('index');

        mmmSetBusy(btn, true);
        item.css('opacity', 0.5);

        mmmPost('mmm_remove_cart_item', { index: index })
            .done(function (response) {
                if (response && response.success) {
                    item.slideUp(200, function () {
                        item.remove();
                        mmmUpdateCartBadge();
                        mmmUpdatePlannerTotals();
                    });
                    return;
                }

                // hier lieber leise/inline? du hast keine Box => fallback alert
                item.css('opacity', 1);
                mmmSetBusy(btn, false);
                mmmHandleAjaxError(response, { fallbackMessage: 'Error while removing item.' });
            })
            .fail(function (xhr) {
                item.css('opacity', 1);
                mmmSetBusy(btn, false);
                mmmHandleRequestFail(xhr, { failMessage: 'Request failed.' });
            });
    });

    /* ==========================================================
       CLEAR CART
       ========================================================== */
    $(document).on('click', '.mmm-clear-cart-link', function (e) {
        e.preventDefault();

        const link = $(this);
        const msgBox = link.next('.mmm-clear-cart-message');
        mmmSetBusy(link, true);
        mmmShowInlineMessage(msgBox, 'Clearing...', 'ok');

        mmmPost('mmm_clear_cart', {})
            .done(function (response) {
                if (response && response.success) {
                    mmmSetBusy(link, false);
                    mmmShowInlineMessage(msgBox, (response.data && response.data.message) || 'Planner cleared.', 'ok');
                    mmmUpdateCartBadge();
                    $('.mmm-plan-item').slideUp(200, function () {
                        $(this).remove();
                    }).promise().done(mmmUpdatePlannerTotals);
                    return;
                }

                mmmSetBusy(link, false);
                mmmHandleAjaxError(response, { inlineEl: msgBox, fallbackMessage: 'Error while clearing the planner.' });
            })
            .fail(function (xhr) {
                mmmSetBusy(link, false);
                mmmHandleRequestFail(xhr, { inlineEl: msgBox, failMessage: 'Request failed.' });
            });
    });

    /* ==========================================================
       CREATE MEAL PLAN (Read directly from DOM)
       ========================================================== */
    $('.mmm-create-meal-plan-link').on('click', function (e) {
        e.preventDefault();

        const msgBox = $('.mmm-create-meal-plan-message');
        mmmShowInlineMessage(msgBox, '', '');

        const items = [];
        $('.mmm-plan-item').each(function () {
            const el = $(this);
            // Read the day from the item's current container, not from a
            // data-day attribute on the <li> (which is only present after a
            // drag). data-day-list is 0 for the unassigned list, 1–7 for days.
            const dayList = parseInt(el.closest('.mmm-plan-day-list').attr('data-day-list'), 10);
            items.push({
                recipe_id: el.data('recipe-id'),
                servings: el.find('.mmm-plan-item-servings').val(),
                type: el.find('.mmm-plan-item-type').val(),
                note: el.find('.mmm-plan-item-note').val(),
                day: (dayList >= 1 && dayList <= 7) ? dayList : null
            });
        });

        const $create = $(this);
        mmmSetBusy($create, true);
        mmmShowInlineMessage(msgBox, 'Creating...', 'ok');

        const startDay = parseInt($('input[name="mmm-plan-startday"]:checked').val(), 10) || 0;

        mmmPost('mmm_create_meal_plan', { items: items, start_day: startDay })
            .done(function (response) {
                if (response && response.success) {
                    mmmShowInlineMessage(msgBox, (response.data && response.data.message) || 'Meal Plan created!', 'ok');

                    if (response.data && response.data.redirect_url) {
                        window.location.href = response.data.redirect_url;
                    }
                    return;
                }

                // hier wollen wir praktisch NIE alert -> inline
                mmmSetBusy($create, false);
                mmmHandleAjaxError(response, { inlineEl: msgBox, fallbackMessage: 'Error while creating meal plan.' });
            })
            .fail(function (xhr) {
                mmmHandleRequestFail(xhr, { inlineEl: msgBox, failMessage: 'Request failed.' });
            });
    });

    /* ==========================================================
       UPDATE CART ITEM (silent)
       ========================================================== */
    $(document).on('change', '.mmm-cart-item input, .mmm-cart-item select', function () {
        const itemEl = $(this).closest('.mmm-cart-item');
        const index = itemEl.data('index');
        const servings = itemEl.find('.mmm-servings').val() || 1;
        const type = itemEl.find('.mmm-type').val() || '';
        const note = itemEl.find('.mmm-note').val() || '';
        const day = itemEl.find('.mmm-day').val() || null;

        mmmPost('mmm_update_cart_item', {
            index: index,
            servings: servings,
            type: type,
            note: note,
            day: day
        })
            .done(function (response) {
                if (!response || !response.success) {
                    // silent, aber im dev console sichtbar
                    console.warn('Update failed', response);
                }
            })
            .fail(function () {
                console.warn('Request failed (update_cart_item)');
            });
    });

    /* ==========================================================
       SHARE THE PLANNER
       ========================================================== */

    /**
     * Makes a link to what the planner holds right now.
     *
     * The server stores a copy under its own token rather than handing out
     * the cart's key, so the link says what was planned at that moment and
     * nothing the sharer does afterwards travels with it.
     */
    $(document).on('click', '.mmm-plan-share__button', function (e) {
        e.preventDefault();

        const $btn = $(this);
        const $msg = $btn.siblings('.mmm-plan-share__message');
        const labels = (window.mixmymeals && mixmymeals.planner_dialog && mixmymeals.planner_dialog.share) || {};

        mmmSetBusy($btn, true);
        $msg.text('');

        mmmPost('mmm_share_cart', {})
            .done(function (response) {
                if (!response || !response.success || !response.data.token) {
                    $msg.text(mmmMessageOf(response, labels.failed));
                    return;
                }

                const url = window.location.origin + window.location.pathname
                    + '?mmm_plan=' + encodeURIComponent(response.data.token);

                // The sheet the phone already has, where there is one; the
                // clipboard otherwise. Both end in the same place.
                if (navigator.share) {
                    navigator.share({ title: labels.title, text: labels.text, url: url })
                        .catch(function () { /* dismissed; nothing to report */ });
                    return;
                }

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(url).then(
                        function () { $msg.text(labels.copied || ''); },
                        function () { $msg.text(url); }
                    );
                    return;
                }

                $msg.text(url);
            })
            .fail(function (xhr) {
                $msg.text(mmmMessageOf(xhr && xhr.responseJSON, labels.failed));
            })
            .always(function () {
                mmmSetBusy($btn, false);
            });
    });

    /** The server's own wording where there is one, ours otherwise. */
    function mmmMessageOf(response, fallback) {
        return (response && response.data && response.data.message) ? response.data.message : (fallback || '');
    }

    /**
     * Opening a shared link: asks first, because it replaces the planner.
     *
     * The token is taken out of the address once it has been dealt with, so a
     * reload does not ask again and the link does not sit in the bar as if it
     * were this visitor's own planner.
     */
    (function mmmMaybeLoadSharedPlanner() {
        const params = new URLSearchParams(window.location.search);
        const token = params.get('mmm_plan');
        if (!token || !$('.mmm-plan-builder').length) return;

        const labels = (window.mixmymeals && mixmymeals.planner_dialog && mixmymeals.planner_dialog.share) || {};
        const dialog = (window.mixmymeals && mixmymeals.load_into_cart_dialog) || {};

        const clean = function () {
            params.delete('mmm_plan');
            const rest = params.toString();
            window.history.replaceState({}, '', window.location.pathname + (rest ? '?' + rest : ''));
        };

        mmmOpenConfirmDialog({
            title: labels.ask_title,
            message: labels.ask,
            confirm: dialog.confirm,
            cancel: dialog.cancel,
            onConfirm: function (ctx) {
                mmmPost('mmm_load_shared_cart', { token: token })
                    .done(function (response) {
                        if (response && response.success) {
                            clean();
                            window.location.reload();
                            return;
                        }
                        mmmHandleAjaxError(response, { inlineEl: ctx.msgBox, fallbackMessage: labels.failed });
                    })
                    .fail(function (xhr) {
                        mmmHandleRequestFail(xhr, { inlineEl: ctx.msgBox, failMessage: labels.failed });
                    });
            },
            onCancel: clean
        });
    })();

    /* ==========================================================
       LOAD PLAN INTO CART
       ========================================================== */
    $(document).on('click', '.mmm-meal-plan-actions__item--load-into-cart', function (e) {
        e.preventDefault();

        const btn = $(this);
        const mealPlanId = btn.data('meal-plan-id');
        const redirectUrl = btn.data('redirect-url');
        const labels = mixmymeals.load_into_cart_dialog || {};

        mmmOpenConfirmDialog({
            title: labels.title,
            message: labels.message,
            confirm: labels.confirm,
            cancel: labels.cancel,
            onConfirm: function (ctx) {
                mmmPost('mmm_load_plan_into_cart', { meal_plan_id: mealPlanId })
                    .done(function (response) {
                        if (response && response.success) {
                            mmmUpdateCartBadge();
                            ctx.close();
                            if (redirectUrl) {
                                window.location.href = redirectUrl;
                                return;
                            }
                            // On the planner itself the plan has to appear;
                            // the badge alone would say it worked while the
                            // page still showed the empty state.
                            if ($('.mmm-plan-builder').length) {
                                window.location.reload();
                            }
                            return;
                        }
                        mmmHandleAjaxError(response, { inlineEl: ctx.msgBox, fallbackMessage: 'Error loading plan into the planner.' });
                    })
                    .fail(function (xhr) {
                        mmmHandleRequestFail(xhr, { inlineEl: ctx.msgBox, failMessage: 'Request failed.' });
                    });
            }
        });
    });

    /* ==========================================================
       PDF BUTTON
       ========================================================== */
    $(document).on('click', '.mmm-meal-plan-actions__item--print', function (e) {
        e.preventDefault();

        const mealPlanId = $(this).data('meal-plan-id');
        if (!mealPlanId) return;

        const url = mixmymeals.ajax_url + '?action=mmm_generate_pdf&meal_plan_id=' + mealPlanId;
        window.open(url, '_blank');
    });

    /* ==========================================================
       HELPER FUNCTIONS
       ========================================================== */
    function mmmSavePlanChange(change) {
        const payload = { index: change.index };
        payload[change.field] = change.value;

        mmmPost('mmm_update_cart_item', payload)
            .done(function (response) {
                if (!response || !response.success) {
                    console.warn('Failed to save cart change', response);
                }
            })
            .fail(function () {
                console.warn('Request failed while saving cart change');
            });
    }

    function mmmUpdateCartBadge() {

        const badges = $('.mmm-cart-badge');
        if (!badges.length) return;

        // HARD RESET
        badges.text('…');

        mmmPost('mmm_get_cart_count', {})
            .done(function (response) {
                if (!response || !response.success) return;

                const count = response.data.count;

                badges.each(function () {
                    const badge = $(this);
                    const singular = badge.data('label-singular') || '';
                    const plural = badge.data('label-plural') || '';
                    badge.text(count + (count === 1 ? singular : plural));
                });

                // One class for "there is something in the planner", so the
                // bottom bar can appear and disappear in CSS instead of being
                // toggled from three places.
                $('body').toggleClass('mmm-cart-empty', count === 0);
            })
            .fail(function () {
                // optional
            });
    }

    /* ==========================================================
   MEAL NUTRITION CALCULATOR
   ========================================================== */

    function mmmMealCalcUpdate(container, grams) {
        const totalWeight = parseFloat(container.dataset.totalWeight || 1);
        const factor = grams / totalWeight;

        const calc = (val) => (val * factor).toFixed(1);

        container.querySelector(".mmmm-meal-nutrition-calculator-cal").textContent = calc(container.dataset.calories);
        container.querySelector(".mmmm-meal-nutrition-calculator-fat").textContent = calc(container.dataset.fat);
        container.querySelector(".mmmm-meal-nutrition-calculator-carbs").textContent = calc(container.dataset.carbs);
        container.querySelector(".mmmm-meal-nutrition-calculator-protein").textContent = calc(container.dataset.protein);
        container.querySelector(".mmmm-meal-nutrition-calculator-sugar").textContent = calc(container.dataset.sugar);
        container.querySelector(".mmmm-meal-nutrition-calculator-fiber").textContent = calc(container.dataset.fiber);
    }

    document.addEventListener("input", function (e) {

        if (e.target.classList.contains("mmmm-meal-nutrition-calculator-grams")) {
            const container = e.target.closest(".mmmm-meal-nutrition-calculator");
            if (!container) return;

            const grams = parseFloat(e.target.value) || 0;

            const slider = container.querySelector(".mmmm-meal-nutrition-calculator-grams-slider");
            if (slider) slider.value = grams;

            mmmMealCalcUpdate(container, grams);
        }

        if (e.target.classList.contains("mmmm-meal-nutrition-calculator-grams-slider")) {
            const container = e.target.closest(".mmmm-meal-nutrition-calculator");
            if (!container) return;

            const grams = parseFloat(e.target.value) || 0;

            const input = container.querySelector(".mmmm-meal-nutrition-calculator-grams");
            if (input) input.value = grams;

            mmmMealCalcUpdate(container, grams);
        }
    });

    /* ==========================================================
       MEAL PLAN SUBSCRIBE
       ========================================================== */
    $(document).on('submit', '.mmm-subscribe-form', function (e) {
        e.preventDefault();

        const $form = $(this);
        const $msg = $form.find('.mmm-subscribe-message');
        const $btn = $form.find('.mmm-subscribe-submit');

        if (!$form.find('.mmm-subscribe-agree').is(':checked')) {
            return;
        }

        $btn.prop('disabled', true);

        mmmPost('mmm_subscribe', {
            nonce: $btn.data('nonce'),
            plan_id: $form.data('plan-id'),
            email: $form.find('.mmm-subscribe-email').val(),
            name: $form.find('.mmm-subscribe-name').val(),
            start: $form.find('.mmm-subscribe-start').val() || '',
            agree: '1'
        })
            .done(function (response) {
                if (response && response.success) {
                    if (response.data && response.data.redirect) {
                        window.location.assign(response.data.redirect);
                        return;
                    }
                    mmmShowInlineMessage($msg, (response.data && response.data.message) || '', 'ok');
                    const el = $form.get(0);
                    if (el) el.reset();
                    return;
                }
                mmmHandleAjaxError(response, { inlineEl: $msg, fallbackMessage: 'Could not subscribe.' });
            })
            .fail(function (xhr) {
                mmmHandleRequestFail(xhr, { inlineEl: $msg, failMessage: 'Request failed.' });
            })
            .always(function () {
                $btn.prop('disabled', false);
            });
    });

});
