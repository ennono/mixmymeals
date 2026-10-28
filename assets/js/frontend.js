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

    function mmmOpenPlannerDialog(recipeId, defaultServings, recipeTitle, diets) {
        const labels = mixmymeals.planner_dialog || {};
        const types = labels.types || {};
        const days = labels.days || {};

        mmmClosePlannerDialog();

        // On the planner itself, a new meal starts with what its people
        // usually eat together; the field stays free.
        const plannerServings = parseInt($('.mmm-plan-builder').attr('data-default-servings'), 10) || 0;
        if (plannerServings > 0) {
            defaultServings = plannerServings;
        }

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
                    '<label class="mmm-planner-dialog-diet-field">' + (labels.diet || '') +
                        '<select class="mmm-planner-dialog-diet"></select>' +
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

        /* How the dish is cooked, only where the recipe offers a choice —
           the same choices as in the planner, the first preselected, so the
           meal is settled the moment it is planned. Built with .text(), since
           the labels are term names. */
        if (Array.isArray(diets) && diets.length > 1) {
            const $diet = backdrop.find('.mmm-planner-dialog-diet');
            diets.forEach(function (choice) {
                $diet.append($('<option>').val(choice[0]).text(choice[1]));
            });
        } else {
            backdrop.find('.mmm-planner-dialog-diet-field').remove();
        }

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
                diet: backdrop.find('.mmm-planner-dialog-diet').val() || '',
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
       SAVE BAR (planner)
       ========================================================== */

    /**
     * "Save plan" at the foot of the screen while the save form is out of
     * sight and the planner holds something to save.
     *
     * The form sits below the board, the nutrition and the shopping list,
     * so on a full week it is thousands of pixels away from where the plan
     * is being built. The bar follows the cart bar's split: this builds it
     * and decides when it shows, the theme decides how it looks.
     *
     * Its target is the save button's closest [data-plan-save] — the
     * section the theme wraps the form in — or, without one, the block the
     * button sits in. A click scrolls there and puts the focus into the
     * first field (name or address where the plan can be subscribed to,
     * else the button), so the next keystroke lands in the form.
     */
    let mmmSaveBar = null;
    let mmmSaveTargetVisible = true;

    function mmmEnsureSaveBar() {
        const button = $('.mmm-plan-builder').length ? $('.mmm-create-meal-plan-link').first() : $();
        if (!button.length || !('IntersectionObserver' in window)) return;

        let target = button.closest('[data-plan-save]');
        if (!target.length) target = button.parent();

        const labels = (window.mixmymeals && mixmymeals.planner_dialog) || {};

        mmmSaveBar = $('<div class="mmm-save-bar" hidden></div>');
        $('<a class="mmm-save-bar__link"></a>')
            .attr('href', target.attr('id') ? '#' + target.attr('id') : '#')
            .text(labels.save_plan || button.text().trim())
            .on('click', function (e) {
                e.preventDefault();
                const reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                target.get(0).scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
                const field = target.find('.mmm-plan-subscribe input:visible').first();
                (field.length ? field : button).get(0).focus({ preventScroll: true });
            })
            .appendTo(mmmSaveBar);

        $('body').append(mmmSaveBar);

        new IntersectionObserver(function (entries) {
            mmmSaveTargetVisible = entries[entries.length - 1].isIntersecting;
            mmmUpdateSaveBar();
        }).observe(target.get(0));
    }

    // Shown while the form is out of sight and the planner is not empty.
    // Called by the observer and after every removal from the planner.
    function mmmUpdateSaveBar() {
        if (!mmmSaveBar) return;
        const show = !mmmSaveTargetVisible && $('.mmm-plan-item').length > 0;
        mmmSaveBar.prop('hidden', !show);
        $('body').toggleClass('mmm-save-bar-visible', show);
    }

    // Here rather than with the cart bar above: the two lets this reads are
    // declared just before, and calling it earlier throws.
    mmmEnsureSaveBar();

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
            btn.data('recipe-title') || '',
            btn.data('diets') || []
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
            overlay.find('.mmm-cook-mode-step').empty().append($(steps[current]).clone().removeAttr('id'));
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
       PLANNER NUTRITION (live, from the cart)

       [mmm_planner_nutrition] renders the first answer; from then on
       every change to the planner asks the server again. The figures
       are computed there, from the cart, by the same methods a saved
       plan uses — a second calculation here would disagree with the
       plan the moment it is saved. Debounced, because a drag and a
       servings spinner fire several changes in quick succession.
       [mmm_planner_shopping_list] is refreshed from the same answer.
       ========================================================== */
    let mmmNutritionTimer = null;
    let mmmNutritionRequest = null;
    // Whose figures the planner shows, where it lists several people; the
    // server answers with the person it used, the first for anything else.
    let mmmPlannerEater = '';

    function mmmRefreshPlannerNutrition() {
        const $box = $('[data-planner-nutrition]');
        const $days = $('[data-day-nutrition]');
        const $prep = $('[data-day-prep]');
        const $shop = $('[data-planner-shopping]').first();
        if (!$box.length && !$days.length && !$prep.length && !$shop.length) return;

        clearTimeout(mmmNutritionTimer);
        mmmNutritionTimer = setTimeout(function () {

            // Only the latest answer counts; an older one arriving late
            // would show a plan that no longer exists.
            if (mmmNutritionRequest && mmmNutritionRequest.abort) {
                mmmNutritionRequest.abort();
            }

            $box.add($days).add($prep).add($shop).attr('aria-busy', 'true');

            // The shopping list rides on the same answer, computed from
            // the same cart — asked for only where the page shows one.
            const extra = $shop.length ? {
                shopping: 1,
                show_details: $shop.attr('data-show-details') === '1' ? 1 : 0,
                sort_by_category: $shop.attr('data-sort-by-category') === '1' ? 1 : 0
            } : {};
            extra.eater = mmmPlannerEater;

            mmmNutritionRequest = mmmPost('mmm_planner_nutrition', extra)
                .done(function (response) {
                    if (!response || !response.success || !response.data) return;

                    mmmPlannerEater = response.data.eater || '';

                    // Keep the table open across refreshes if it was open.
                    const open = $box.find('.mmm-planner-nutrition__details').prop('open');

                    $box.html(response.data.html || '');
                    $box.prop('hidden', !!response.data.empty);

                    if (open) {
                        $box.find('.mmm-planner-nutrition__details').prop('open', true);
                    }

                    // The row under each day: that day's own figures.
                    const days = response.data.days || {};
                    $days.each(function () {
                        const html = days[this.getAttribute('data-day-nutrition')] || '';
                        $(this).html(html).prop('hidden', !html);
                    });

                    // Preparation due on each weekday.
                    const prep = response.data.prep || {};
                    $prep.each(function () {
                        const html = prep[this.getAttribute('data-day-prep')] || '';
                        $(this).html(html).prop('hidden', !html);
                    });

                    // The shopping list, keeping open the lines that were open.
                    if ($shop.length && typeof response.data.shopping === 'string') {
                        // A line is its ingredient plus, where a diet split
                        // it, that diet; the amount is what just changed.
                        const lineKey = function (el) {
                            return $(el).find('.mmm-shopping-item-name').text() + '|'
                                + $(el).find('.mmm-shopping-item-diet').text();
                        };
                        const openLines = [];
                        $shop.find('.mmm-shopping-item details[open]').each(function () {
                            openLines.push(lineKey(this));
                        });

                        $shop.html(response.data.shopping);
                        $shop.prop('hidden', !response.data.shopping);

                        $shop.find('.mmm-shopping-item details').each(function () {
                            if (openLines.indexOf(lineKey(this)) !== -1) {
                                this.open = true;
                            }
                        });
                    }
                })
                .always(function () {
                    $box.add($days).add($prep).add($shop).removeAttr('aria-busy');
                });
        }, 400);
    }

    /* The switch between the planner's people: the figures, the table and
       the row under each day become that person's. */
    $(document).on('click', '[data-planner-nutrition] .mmm-eater-switch__item', function (e) {
        e.preventDefault();
        mmmPlannerEater = this.getAttribute('data-eater') || '';
        mmmRefreshPlannerNutrition();
    });

    /* Adding or removing a person changes every item's checkboxes, which
       are the server's to render: reload once the cart has the list. */
    /* What one of the planner's people usually eats: every recipe that does
       not say otherwise follows, so the page is drawn again. */
    $(document).on('change', '[data-plan-eaters] .mmm-plan-eaters__portions', function () {
        const $field = $(this);
        mmmSetBusy($field, true);

        mmmPost('mmm_cart_eaters', { op: 'portions', id: $field.attr('data-eater'), portions: $field.val() })
            .done(function (response) {
                if (response && response.success) {
                    window.location.reload();
                    return;
                }
                mmmSetBusy($field, false);
                mmmHandleAjaxError(response, { fallbackMessage: 'Error.' });
            })
            .fail(function (xhr) {
                mmmSetBusy($field, false);
                mmmHandleRequestFail(xhr, { failMessage: 'Request failed.' });
            });
    });

    $(document).on('click', '[data-plan-eaters] [data-eater-add], [data-plan-eaters] [data-eater-remove]', function (e) {
        e.preventDefault();
        const $btn = $(this);
        const remove = $btn.is('[data-eater-remove]');
        mmmSetBusy($btn, true);

        mmmPost('mmm_cart_eaters', remove ? { op: 'remove', id: $btn.attr('data-eater-remove') } : { op: 'add' })
            .done(function (response) {
                if (response && response.success) {
                    window.location.reload();
                    return;
                }
                mmmSetBusy($btn, false);
                mmmHandleAjaxError(response, { fallbackMessage: 'Error.' });
            })
            .fail(function (xhr) {
                mmmSetBusy($btn, false);
                mmmHandleRequestFail(xhr, { failMessage: 'Request failed.' });
            });
    });

    /* Who eats a planner item, saved as it changes like the servings.
       Nobody ticked is everybody, so the boxes say so at once. */
    $(document).on('change', '.mmm-plan-item-eater__input', function () {
        const $set = $(this).closest('.mmm-plan-item-eaters');
        const item = $(this).closest('.mmm-plan-item');

        if (!$set.find('.mmm-plan-item-eater__input:checked').length) {
            $set.find('.mmm-plan-item-eater__input').prop('checked', true);
        }

        // A person's portions only count while they eat along.
        $set.find('.mmm-plan-item-eater__input').each(function () {
            $(this).closest('.mmm-plan-item-eater-group')
                .find('.mmm-plan-item-eater__portions').prop('disabled', !this.checked);
        });

        mmmSavePlanChange({
            index: item.data('index'),
            field: 'eaters',
            value: mmmItemEaters(item).join(',')
        }).always(mmmRefreshPlannerNutrition);
    });

    /* How much one person eats of a planner item; empty is their usual. */
    $(document).on('change', '.mmm-plan-item-eater__portions', function () {
        const item = $(this).closest('.mmm-plan-item');

        mmmSavePlanChange({
            index: item.data('index'),
            field: 'portions',
            value: JSON.stringify(mmmItemPortions(item))
        }).always(mmmRefreshPlannerNutrition);
    });

    /**
     * The portions entered on a planner item for the people who eat it,
     * {id: portions}; an empty field is that person's usual amount.
     *
     * @param {jQuery} item The .mmm-plan-item.
     * @return {Object}
     */
    function mmmItemPortions(item) {
        const out = {};
        item.find('.mmm-plan-item-eater__portions:enabled').each(function () {
            const value = (this.value || '').trim();
            if (value !== '') {
                out[this.getAttribute('data-eater')] = value;
            }
        });
        return out;
    }

    /**
     * The ids ticked on a planner item; [] where it has no boxes, which is
     * everybody.
     *
     * @param {jQuery} item The .mmm-plan-item.
     * @return {string[]}
     */
    function mmmItemEaters(item) {
        return item.find('.mmm-plan-item-eater__input:checked').map(function () {
            return this.value;
        }).get();
    }

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

        // The figures follow once the cart has the new day, not before.
        mmmSavePlanChange({
            index: item.data('index'),
            field: 'day',
            value: newDay
        }).always(mmmRefreshPlannerNutrition);

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

    /* Servings, meal type and diet of a planner item. Saved as they
       change, so the cart — which the live nutrition is computed from —
       knows them before the plan is saved; the plan itself still reads
       them from the item when it is created. */
    $(document).on('change', '.mmm-plan-item-servings, .mmm-plan-item-type, .mmm-plan-item-diet', function () {
        const $field = $(this);
        const item = $field.closest('.mmm-plan-item');
        let value = $field.val();

        if ($field.hasClass('mmm-plan-item-servings')) {
            // Not below what its eaters eat (the field's min, kept by the server too).
            value = Math.max(parseInt($field.attr('min'), 10) || 1, parseInt(value, 10) || 1);
            $field.val(value);
        }

        mmmSavePlanChange({
            index: item.data('index'),
            field: $field.data('field'),
            value: value
        }).always(mmmRefreshPlannerNutrition);
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
                    // The server closes the gap (array_values), so every item
                    // after this one now sits one index lower. Without this,
                    // the next change to any of them — servings, day, another
                    // removal — went to its neighbour or to nothing, and the
                    // live nutrition read a cart the page did not show.
                    $('.mmm-plan-item').not(item).each(function () {
                        const i = parseInt($(this).attr('data-index'), 10);
                        if (i > index) {
                            $(this).attr('data-index', i - 1).data('index', i - 1);
                        }
                    });
                    item.slideUp(200, function () {
                        item.remove();
                        mmmUpdateCartBadge();
                        mmmUpdateSaveBar();
                    });
                    mmmRefreshPlannerNutrition();
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
                        mmmUpdateSaveBar();
                    });
                    mmmRefreshPlannerNutrition();
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
       SUBSCRIBE WHILE SAVING THE PLAN

       A first-name and an e-mail field above the action row
       (render_plan_subscribe_offer() in the plugin), always visible while
       the offer is on. The name is never required. In "required" mode
       the Save button stays disabled until it holds a plausible address —
       and, where the notice is a checkbox, until that is ticked. In
       "optional" mode nothing is gated: an empty field means no
       subscription, and a half-filled one is answered by the server.
       ========================================================== */
    function mmmPlanSubscribeReady($box) {
        if (!$box || !$box.length || $box.data('subscribe-mode') !== 'required') {
            return true;
        }
        const field = $box.find('.mmm-plan-subscribe-email').get(0);
        const $agree = $box.find('.mmm-plan-subscribe-agree');
        const filled = !!field && field.value.trim() !== '' && field.checkValidity();
        return filled && (!$agree.length || $agree.is(':checked'));
    }

    function mmmUpdatePlanSubscribeGate() {
        const ready = mmmPlanSubscribeReady($('.mmm-plan-subscribe[data-subscribe-mode]'));
        $('.mmm-create-meal-plan-link')
            .toggleClass('mmm-is-disabled', !ready)
            .attr('aria-disabled', ready ? null : 'true');
    }

    $(document).on('input change', '.mmm-plan-subscribe-email, .mmm-plan-subscribe-agree', mmmUpdatePlanSubscribeGate);

    // Starts disabled when the page loads with a required, still empty field
    // — matches .mmm-create-meal-plan-link.mmm-is-disabled in style.css.
    mmmUpdatePlanSubscribeGate();

    /* ==========================================================
       CREATE MEAL PLAN (Read directly from DOM)
       ========================================================== */
    $('.mmm-create-meal-plan-link').on('click', function (e) {
        e.preventDefault();

        if ($(this).hasClass('mmm-is-disabled')) {
            return;
        }

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
                diet: el.find('.mmm-plan-item-diet').val() || '',
                eaters: mmmItemEaters(el).join(','),
                portions: JSON.stringify(mmmItemPortions(el)),
                note: el.find('.mmm-plan-item-note').val(),
                day: (dayList >= 1 && dayList <= 7) ? dayList : null
            });
        });

        // Subscribe-while-saving: whatever the field holds goes along; the
        // server decides what it means (empty in "optional" = no
        // subscription). Without a consent checkbox the notice is plain
        // text, and the server does not ask for a tick at all.
        let subscribe = null;
        const $sub = $('.mmm-plan-subscribe[data-subscribe-mode]');
        if ($sub.length) {
            const $agree = $sub.find('.mmm-plan-subscribe-agree');
            subscribe = {
                email: ($sub.find('.mmm-plan-subscribe-email').val() || '').trim(),
                name: ($sub.find('.mmm-plan-subscribe-name').val() || '').trim(),
                agree: (!$agree.length || $agree.is(':checked')) ? '1' : '0'
            };
        }

        const $create = $(this);
        mmmSetBusy($create, true);
        mmmShowInlineMessage(msgBox, 'Creating...', 'ok');

        const startDay = parseInt($('input[name="mmm-plan-startday"]:checked').val(), 10) || 0;

        const payload = { items: items, start_day: startDay };
        if (subscribe) {
            payload.subscribe = subscribe;
        }

        mmmPost('mmm_create_meal_plan', payload)
            .done(function (response) {
                if (response && response.success) {
                    mmmShowInlineMessage(msgBox, (response.data && response.data.message) || 'Meal Plan created!', 'ok');

                    if (response.data && response.data.redirect_url) {
                        // A moment longer when there is a subscribe notice to
                        // read — otherwise the redirect cuts it off unread.
                        const delay = (response.data && response.data.subscribed) ? 1400 : 0;
                        window.setTimeout(function () {
                            window.location.href = response.data.redirect_url;
                        }, delay);
                        return;
                    }

                    mmmSetBusy($create, false);
                    return;
                }

                // hier wollen wir praktisch NIE alert -> inline
                mmmSetBusy($create, false);
                mmmHandleAjaxError(response, { inlineEl: msgBox, fallbackMessage: 'Error while creating meal plan.' });
            })
            .fail(function (xhr) {
                // Every refusal from the handler arrives here, not in .done():
                // wp_send_json_error() answers with a 4xx status (missing
                // consent, invalid address, rate limit, expired session). The
                // button has to come back, or the visitor who just fixed the
                // one thing the message named cannot save at all.
                mmmSetBusy($create, false);
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
       PLAN SERVINGS (plan page, ?mmm_persons_to_add)

       The field above a plan's shopping list adds the same number of
       people to every meal. The arithmetic stays on the server — packs
       and unit divisors are rounded once over the new total there, by
       the calculation every shopping list goes through — so this only
       asks, and swaps in what comes back: the field, the list, the
       address, the Bring! link and every action marked
       data-servings-link (the PDF), and the change appended to the hero's,
       each day's and each meal card's servings (.mmm-servings-delta,
       "+1"). Without JavaScript the − / + links do the same by
       reloading the page.
       ========================================================== */
    let mmmServingsRequest = null;

    $('[data-plan-shopping]').addClass('is-live');

    // The "+1" behind a number of servings; hidden while there is none.
    function mmmSetServingsDelta($scope, text) {
        $scope.find('.mmm-servings-delta').text(text || '').prop('hidden', !text);
    }

    function mmmRequestPlanServings($wrap, value, focusSelector) {
        const planId = $wrap.attr('data-plan-shopping');
        const $list = $wrap.find('[data-plan-shopping-list]');

        // Only the latest answer counts.
        if (mmmServingsRequest && mmmServingsRequest.abort) {
            mmmServingsRequest.abort();
        }

        $list.attr('aria-busy', 'true');

        mmmServingsRequest = mmmPost('mmm_plan_servings', {
            meal_plan_id: planId,
            persons_to_add: value || '',
            show_details: $wrap.attr('data-show-details') === '1' ? 1 : 0,
            sort_by_category: $wrap.attr('data-sort-by-category') === '1' ? 1 : 0,
            cart_url: $wrap.find('.mmm-plan-servings__planner-link').attr('data-redirect-url') || ''
        })
            .done(function (response) {
                if (!response || !response.success || !response.data) return;
                const data = response.data;

                $wrap.find('.mmm-plan-servings').replaceWith(data.control || '');
                $list.html(data.list || '');

                // Back where the reader was: the same control, or the
                // value when a step link has reached its end.
                if (focusSelector) {
                    let $focus = $wrap.find(focusSelector);
                    if (!$focus.length) $focus = $wrap.find('.mmm-plan-servings__value').first().attr('tabindex', '-1');
                    $focus.trigger('focus');
                }

                if (data.url && window.history && window.history.replaceState) {
                    window.history.replaceState(null, '', data.url);
                }

                if (data.bring) {
                    $('a.mmm-plan-hero__action--bring, a.mmm-meal-plan-actions__item--bring').attr('href', data.bring);
                }

                $('.mmm-meal-plan-actions__item--load-into-cart[data-meal-plan-id="' + planId + '"]')
                    .attr('data-persons', data.value || '');

                // Actions whose address follows the number (the PDF, from
                // MixMyMeals Printer): set or drop the parameter.
                $('a[data-servings-link]').each(function () {
                    try {
                        const url = new URL(this.href, window.location.href);
                        if (data.value) {
                            url.searchParams.set(data.param, data.value);
                        } else {
                            url.searchParams.delete(data.param);
                        }
                        this.href = url.toString();
                    } catch (err) { /* leave an unparsable address alone */ }
                });

                mmmSetServingsDelta($('.mmm-plan-hero__meta .mmm-chip--servings'), data.plan_delta);

                const $rail = $('.mmm-plan-rail[data-meal-plan-id="' + planId + '"]');
                const days = data.days || {};
                $rail.find('[data-plan-servings-day]').each(function () {
                    mmmSetServingsDelta($(this), days[this.getAttribute('data-plan-servings-day')]);
                });

                const meals = data.meals || {};
                $rail.find('.mmm-recipe-card[data-meal-id]').each(function () {
                    const meal = meals[this.getAttribute('data-meal-id')];
                    if (!meal) return;
                    $(this).attr('href', meal.url);
                    mmmSetServingsDelta($(this).find('.mmm-chip--servings'), meal.delta);
                });
            })
            .always(function () {
                $list.removeAttr('aria-busy');
            });
    }

    /*
     * − and + count on from the field's own state rather than from the
     * link's address: three quick clicks must mean three people, not three
     * times the value the link held before the first answer arrived. Only
     * the difference is counted here; what it does to the amounts is the
     * server's answer.
     */
    /* ==========================================================
       PLAN PAGE: WHOSE NUTRITION

       A plan listing several people shows one person's figures, with a
       switch of links (?mmm_eater=p2) above them. The links work on their
       own; with the script the overview, and the per-weekday table and
       product comparison where the page has them, are swapped from the
       `mmm_plan_nutrition` answer, computed on the server like the page.
       ========================================================== */
    let mmmPlanNutritionRequest = null;

    $(document).on('click', '[data-plan-nutrition] a.mmm-eater-switch__item', function (e) {
        const $box = $(this).closest('[data-plan-nutrition]');
        const href = this.getAttribute('href') || '';

        e.preventDefault();

        if (mmmPlanNutritionRequest && mmmPlanNutritionRequest.abort) {
            mmmPlanNutritionRequest.abort();
        }

        $box.attr('aria-busy', 'true');

        mmmPlanNutritionRequest = mmmPost('mmm_plan_nutrition', {
            meal_plan_id: $box.attr('data-plan-nutrition'),
            eater: this.getAttribute('data-eater') || '',
            page_url: window.location.href.split('#')[0]
        })
            .done(function (response) {
                if (!response || !response.success || !response.data || !response.data.html) {
                    window.location.href = href;
                    return;
                }

                const parts = response.data.parts || {};
                $('[data-plan-eater-part]').each(function () {
                    const html = parts[this.getAttribute('data-plan-eater-part')];
                    if (typeof html === 'string') {
                        $(this).html(html);
                    }
                });

                const $new = $(response.data.html);
                $box.replaceWith($new);
                $new.find('.mmm-eater-switch__item.is-current').trigger('focus');

                if (window.history && window.history.replaceState && href) {
                    window.history.replaceState(null, '', href.split('#')[0]);
                }
            })
            .fail(function (xhr) {
                if (xhr && xhr.statusText === 'abort') return;
                window.location.href = href;
            })
            .always(function () {
                $box.removeAttr('aria-busy');
            });
    });

    $(document).on('click', '[data-plan-shopping] a.mmm-plan-servings__step', function (e) {
        e.preventDefault();
        const $link = $(this);
        const $ctrl = $link.closest('.mmm-plan-servings');
        const down = $link.hasClass('mmm-plan-servings__step--down');
        const base = parseInt($ctrl.attr('data-base'), 10) || 0;
        const min = parseInt($ctrl.attr('data-min'), 10) || 0;
        const max = parseInt($ctrl.attr('data-max'), 10) || 0;
        const offset = Math.max(min, Math.min(max, (parseInt($ctrl.attr('data-offset'), 10) || 0) + (down ? -1 : 1)));

        $ctrl.attr('data-offset', offset);
        $ctrl.find('.mmm-plan-servings__value').text(
            base > 0
                ? String(base + offset)
                : (offset > 0 ? '+' + offset : (offset < 0 ? '−' + (-offset) : '±0'))
        );

        mmmRequestPlanServings(
            $link.closest('[data-plan-shopping]'),
            offset ? String(offset) : '',
            down ? 'a.mmm-plan-servings__step--down' : 'a.mmm-plan-servings__step--up'
        );
    });

    /* ==========================================================
       LOAD PLAN INTO CART
       ========================================================== */
    $(document).on('click', '.mmm-meal-plan-actions__item--load-into-cart', function (e) {
        e.preventDefault();

        const btn = $(this);
        const mealPlanId = btn.data('meal-plan-id');
        // The servings field's link has nowhere else to go: without an
        // address of its own it leads to the header's planner link.
        let redirectUrl = btn.attr('data-redirect-url') || '';
        if (!redirectUrl && btn.hasClass('mmm-plan-servings__planner-link')) {
            redirectUrl = $('.mmm-cart-badge').first().closest('a').attr('href') || '';
        }
        // Read fresh, not through .data(): the servings field rewrites it.
        const persons = btn.attr('data-persons') || '';
        const labels = mixmymeals.load_into_cart_dialog || {};

        mmmOpenConfirmDialog({
            title: labels.title,
            message: labels.message,
            confirm: labels.confirm,
            cancel: labels.cancel,
            onConfirm: function (ctx) {
                mmmPost('mmm_load_plan_into_cart', { meal_plan_id: mealPlanId, persons_to_add: persons })
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
       HELPER FUNCTIONS
       ========================================================== */
    function mmmSavePlanChange(change) {
        const payload = { index: change.index };
        payload[change.field] = change.value;

        return mmmPost('mmm_update_cart_item', payload)
            .done(function (response) {
                if (!response || !response.success) {
                    console.warn('Failed to save cart change', response);
                    return;
                }

                // The servings never fall below what the eaters eat; the
                // server raised them where needed and says whether more is
                // planned than eaten. Both are taken as they come.
                const data = response.data || {};
                const item = $('.mmm-plan-item[data-index="' + change.index + '"]');
                if (data.item && typeof data.min_servings === 'number') {
                    item.find('.mmm-plan-item-servings')
                        .attr('min', data.min_servings)
                        .val(data.item.servings);
                }
                if (typeof data.surplus === 'string') {
                    item.find('[data-servings-surplus]').text(data.surplus).prop('hidden', !data.surplus);
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
