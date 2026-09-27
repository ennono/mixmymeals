jQuery(document).ready(function ($) {
    /* ===========================
       GLOBAL AJAX SETTINGS
    ============================ */
    const ajaxUrl = window.mixmymealsAdmin?.ajaxurl || window.ajaxurl || '/wp-admin/admin-ajax.php';
    const ajaxNonce = window.mixmymealsAdmin?.nonce || '';

    /* ===========================
       DYNAMIC MEAL DATE SELECTION
       — dependent on selected Meal Plan
    ============================ */
    const $mealPlanSelect = $("#mmm_parent_id");
    const $mealDateInput = $("#meal_date");

    const updateMealDateLimits = () => {
        const $selected = $mealPlanSelect.find("option:selected");
        const start = $selected.data("start");
        const end = $selected.data("end");

        if (start && end) {
            $mealDateInput.attr("min", start).attr("max", end);

            // Aktuelles Datum validieren
            const currentDate = $mealDateInput.val();
            if (currentDate < start || currentDate > end) {
                $mealDateInput.val(start);
            }
        } else {
            $mealDateInput.removeAttr("min").removeAttr("max");
        }
    };

    // Initial setzen
    updateMealDateLimits();
    // Bei Änderung des Meal Plans
    $mealPlanSelect.on("change", updateMealDateLimits);

    /* ===========================
       CALORIE CALCULATION
    ============================ */
    const updateCaloriesPerItem = () => {
        const cal100 = parseFloat($("input[name='rmm_calories_100']").val()) || 0;
        const weight = parseFloat($("input[name='rmm_weight_item']").val()) || 0;
        const caloriesPerItem = (cal100 / 100) * weight;

        $(".rmm_calories_item").text(
            isNaN(caloriesPerItem) ? "—" : Math.round(caloriesPerItem)
        );
    };

    $(document).on(
        "input",
        "input[name='rmm_calories_100'], input[name='rmm_weight_item']",
        updateCaloriesPerItem
    );
    updateCaloriesPerItem();

    /* ===========================
       IMAGE HANDLING
    ============================ */
    let frame;

    $(document).on("click", ".mixmymeals-select-image", function (e) {
        e.preventDefault();
        const $row = $(this).closest(".mixmymeals-step-row");

        if (frame) frame.off("select");

        frame = wp.media({
            title: "Select or Upload Image",
            button: { text: "Use this image" },
            multiple: false,
        });

        frame.on("select", function () {
            const attachment = frame.state().get("selection").first().toJSON();
            $row.find(".mixmymeals-image-id").val(attachment.id);
            $row.find(".mixmymeals-image-preview").attr("src", attachment.url).show();
            $row.find(".mixmymeals-remove-image").show();
        });

        frame.open();
    });

    $(document).on("click", ".mixmymeals-remove-image", function (e) {
        e.preventDefault();
        const $row = $(this).closest(".mixmymeals-step-row");

        $row.find(".mixmymeals-image-id").val("");
        $row.find(".mixmymeals-image-preview").hide().attr("src", "");
        $(this).hide();
    });

    /* ===========================
       AUTHOR BOX PROFILE PICTURE
    ============================ */
    // Same pattern as the step images. Delegated on document, because in the
    // block editor classic meta boxes are moved after load.
    let authorImageFrame;

    $(document).on("click", ".mmm-author-image-select", function (e) {
        e.preventDefault();
        const $field = $(this).closest(".mmm-author-image-field");

        if (authorImageFrame) authorImageFrame.off("select");

        authorImageFrame = wp.media({
            title: "Profilbild auswählen",
            button: { text: "Übernehmen" },
            library: { type: "image" },
            multiple: false,
        });

        authorImageFrame.on("select", function () {
            const attachment = authorImageFrame.state().get("selection").first().toJSON();
            // Thumbnail is square, matching how the picture is rendered on the
            // frontend; fall back to the full size for images without one.
            const url =
                (attachment.sizes && attachment.sizes.thumbnail && attachment.sizes.thumbnail.url) ||
                attachment.url;

            $field.find(".mmm-author-image-id").val(attachment.id);
            $field.find(".mmm-author-image-preview").attr("src", url).show();
            $field.find(".mmm-author-image-remove").show();
        });

        authorImageFrame.open();
    });

    $(document).on("click", ".mmm-author-image-remove", function (e) {
        e.preventDefault();
        const $field = $(this).closest(".mmm-author-image-field");

        $field.find(".mmm-author-image-id").val("");
        $field.find(".mmm-author-image-preview").hide().attr("src", "");
        $(this).hide();
    });

    /* ===========================
       STEP DESCRIPTION EDITOR (slim TinyMCE via wp.editor)
    ============================ */
    let mmmStepEditorUid = 0;

    const mmmStepEditorSettings = {
        tinymce: {
            wpautop: true,
            // The Visual tab needs its plugins loaded, otherwise buttons like
            // bullist/numlist (lists) and link (wplink) do nothing.
            plugins: "lists paste tabfocus wordpress wpautoresize wpeditimage wpemoji wplink wptextpattern",
            toolbar1: "bold italic bullist numlist link unlink",
            menubar: false,
            statusbar: false,
            branding: false,
        },
        quicktags: { buttons: "strong,em,ul,ol,li,link" },
        mediaButtons: false,
    };

    // Attach a slim editor to a step description <textarea>. Assigns a unique
    // id when the textarea has none (e.g. a freshly cloned row).
    function mmmInitStepEditor(el) {
        const $ta = $(el);
        if (!$ta.length || !window.wp || !wp.editor || !wp.editor.initialize) return;
        let id = $ta.attr("id");
        if (!id) {
            id = "mmm_step_desc_new_" + (mmmStepEditorUid++);
            $ta.attr("id", id);
        }
        wp.editor.initialize(id, mmmStepEditorSettings);
    }

    // Tear an editor down (writes its content back to the textarea first).
    function mmmRemoveStepEditor(el) {
        const id = $(el).attr("id");
        if (id && window.wp && wp.editor && wp.editor.remove) {
            wp.editor.remove(id);
        }
    }

    /* ===========================
       STEP HANDLING
    ============================ */
    const createEmptyStep = (index) => $(`
        <div class="mixmymeals-step-row" data-step="${index}">
            <h3>Step ${index + 1}</h3>
            <p>
                <label>Title</label><br>
                <input name="mmm_recipe_steps[${index}][title]" class="regular-text">
            </p>
            <p>
                <label>Description</label><br>
                <textarea name="mmm_recipe_steps[${index}][description]" class="large-text mmm-step-description"></textarea>
            </p>
            <div class="mixmymeals-step-ingredients-wrapper">
                <div class="mixmymeals-step-ingredients"></div>
                <button type="button" class="button mixmymeals-add-ingredient">+ Add Ingredient</button>
            </div>
            <hr>
        </div>
    `);

    const resetStepFields = ($clone, index) => {
        $clone.attr("data-step", index);
        $clone.find("h3").text(`Step ${index + 1}`);
        $clone.find("input, textarea").val("");
        $clone.find(".mixmymeals-step-ingredients").empty();

        $clone.find("input, textarea, select").each(function () {
            const name = $(this).attr("name");
            //if (name) $(this).attr("name", name.replace(/\d+/, index));
            if (name) $(this).attr("name", name.replace(/mmm_recipe_steps\[\d+\]/, `mmm_recipe_steps[${index}]`));

        });
    };

    $("#mixmymeals-add-step").on("click", function () {
        const $container = $(".mixmymeals-recipe-steps");
        const $rows = $container.find(".mixmymeals-step-row");
        const newIndex = $rows.length;

        if ($rows.length === 0) {
            const $clone = createEmptyStep(0);
            $container.append($clone);
            mmmInitStepEditor($clone.find(".mmm-step-description"));
        } else {
            const $last = $rows.last();
            // Tear the editor down before cloning so we clone a clean textarea,
            // not a broken duplicate TinyMCE iframe.
            mmmRemoveStepEditor($last.find(".mmm-step-description"));

            const $clone = $last.clone();
            resetStepFields($clone, newIndex);
            // The clone inherited the source's editor id; drop it so it gets a
            // fresh, unique one on init.
            $clone.find(".mmm-step-description").removeAttr("id");
            $container.append($clone);

            // Rebuild the source editor and initialize the new one.
            mmmInitStepEditor($last.find(".mmm-step-description"));
            mmmInitStepEditor($clone.find(".mmm-step-description"));
        }

        showShoppingListSaveNotice();
    });

    $(document).on("click", ".mixmymeals-delete-step", function (e) {
        e.preventDefault();
        const $row = $(this).closest(".mixmymeals-step-row");
        // Remove the editor instance before dropping the row so TinyMCE does not
        // leak an orphaned instance. Remaining rows keep their (stable) editor
        // ids; only the field names below are reindexed.
        mmmRemoveStepEditor($row.find(".mmm-step-description"));
        $row.remove();

        $(".mixmymeals-step-row").each(function (i) {
            $(this).attr("data-step", i);
            $(this).find("h3").text(`Step ${i + 1}`);
            $(this).find("input, textarea, select").each(function () {
                const name = $(this).attr("name");
                if (name) $(this).attr("name", name.replace(/\d+/, i));
            });
        });

        showShoppingListSaveNotice();
    });

    /* ===========================
       INGREDIENT HANDLING
    ============================ */
    /* ===========================
   DRAG & DROP SORTING (STEPS)
=========================== */

    const $stepsContainer = $(".mixmymeals-recipe-steps");

    if ($stepsContainer.length) {
        $stepsContainer.sortable({
            handle: "h3",
            placeholder: "mixmymeals-step-placeholder",
            items: "> .mixmymeals-step-row",
            tolerance: "pointer",
            start: function () {
                // Moving a row's DOM node breaks its TinyMCE iframe, so tear all
                // step editors down for the drag and rebuild them on stop.
                $stepsContainer.find(".mmm-step-description").each(function () {
                    mmmRemoveStepEditor(this);
                });
            },
            update: function () {
                reindexSteps();
                showShoppingListSaveNotice();
            },
            stop: function () {
                $stepsContainer.find(".mmm-step-description").each(function () {
                    mmmInitStepEditor(this);
                });
            }
        });
    }

    function mmmInitAllStepEditors() {
        $(".mixmymeals-recipe-steps .mmm-step-description").each(function () {
            mmmInitStepEditor(this);
        });
    }

    // The author box text field (recipes and meal plans) uses the same slim
    // editor and the same deferred boot: rendered as a plain textarea by PHP so
    // TinyMCE is only created once the meta box has reached its final place.
    function mmmInitAuthorEditors() {
        $("textarea.mmm-author-editor").each(function () {
            mmmInitStepEditor(this);
        });
    }

    function mmmInitDeferredEditors() {
        mmmInitAllStepEditors();
        mmmInitAuthorEditors();
    }

    // Initialize the editors for the step rows rendered on page load.
    //
    // In the block editor, classic meta boxes are relocated into the Gutenberg
    // meta-box area *after* the page loads. Initializing TinyMCE before that
    // move leaves the iframe with a null selection: it can be focused but you
    // cannot type into it. So wait until the meta boxes report as initialized
    // (their DOM has reached its final location) before creating the editors.
    //
    // In the classic editor there is no such store, so fall back to waiting for
    // window load, by which time window.tinymce is defined.
    function mmmBootStepEditors() {
        const editPost = window.wp && wp.data && wp.data.select("core/edit-post");

        if (editPost && typeof editPost.areMetaBoxesInitialized === "function") {
            if (editPost.areMetaBoxesInitialized()) {
                mmmInitDeferredEditors();
                return;
            }
            const unsubscribe = wp.data.subscribe(function () {
                if (wp.data.select("core/edit-post").areMetaBoxesInitialized()) {
                    unsubscribe();
                    mmmInitDeferredEditors();
                }
            });
            return;
        }

        if (window.tinymce) {
            mmmInitDeferredEditors();
        } else {
            $(window).on("load", mmmInitDeferredEditors);
        }
    }

    mmmBootStepEditors();

    $(document).on("submit", "form#post", function () {
        if (window.tinymce) {
            window.tinymce.triggerSave();
        }
    });

    /* Reindex nach Sortierung */
    function reindexSteps() {
        $stepsContainer.find(".mixmymeals-step-row").each(function (stepIndex) {

            const $step = $(this);

            // Step Nummer im Titel
            $step.find("h3").text("Step " + (stepIndex + 1));

            // data-step setzen
            $step.attr("data-step", stepIndex);

            // ALLE Felder updaten (inkl. Ingredients!)
            $step.find("input, textarea, select").each(function () {
                let name = $(this).attr("name");
                if (!name) return;

                // Nur den ersten Index ersetzen (Step Index)
                name = name.replace(/mmm_recipe_steps\[\d+\]/, `mmm_recipe_steps[${stepIndex}]`);

                $(this).attr("name", name);
            });

            // Ingredients neu indexieren (wichtig!)
            $step.find(".mixmymeals-step-ingredient").each(function (ingredientIndex) {
                $(this).find("input, select").each(function () {
                    let name = $(this).attr("name");
                    if (!name) return;

                    name = name.replace(/\[ingredients\]\[\d+\]/, `[ingredients][${ingredientIndex}]`);
                    $(this).attr("name", name);
                });
            });

        });
    }

    $(document).on("click", ".mixmymeals-add-ingredient", function () {
        const $step = $(this).closest(".mixmymeals-step-row");
        const $container = $step.find(".mixmymeals-step-ingredients");
        const $template = $("#mixmymeals-ingredient-template .mixmymeals-step-ingredient").clone();

        const stepIndex = $step.data("step");
        const ingredientIndex = $container.find(".mixmymeals-step-ingredient").length;

        // The row holds two selects now, so each is named by its own class.
        $template.find("select.ingredient-select")
            .attr("name", `mmm_recipe_steps[${stepIndex}][ingredients][${ingredientIndex}][id]`);

        $template.find("select.ingredient-product-select")
            .attr("name", `mmm_recipe_steps[${stepIndex}][ingredients][${ingredientIndex}][product_id]`);

        $template.find("input.ingredient-amount-input")
            .attr("name", `mmm_recipe_steps[${stepIndex}][ingredients][${ingredientIndex}][amount]`);

        $template.find("input.ingredient-optional-input")
            .attr("name", `mmm_recipe_steps[${stepIndex}][ingredients][${ingredientIndex}][optional]`);

        $container.append($template);
        showShoppingListSaveNotice();
    });

    $(document).on("click", ".mixmymeals-remove-ingredient", function () {
        $(this).closest(".mixmymeals-step-ingredient").remove();
        showShoppingListSaveNotice();
    });

    /* ===========================
       PRODUCT SELECT PER STEP INGREDIENT
    ============================ */
    /**
     * Refills the product select of one ingredient row from the localised map.
     *
     * No AJAX: only ingredients that have products appear in MixMyMealsProducts,
     * which is a short list, and a row somebody just added needs its options
     * now rather than after a round trip. The current pin is kept when the same
     * ingredient is re-selected, and dropped when the ingredient changes —
     * a product of the old ingredient would be refused on save anyway.
     */
    function fillProductSelect($container, ingredientId) {
        const $select = $container.find("select.ingredient-product-select");

        if (!$select.length) {
            return;
        }

        const previous = $select.val();
        const products = (window.MixMyMealsProducts || {})[ingredientId] || [];

        // Keep the translated placeholder that PHP rendered into the row.
        const anyLabel = $select.find('option[value="0"]').first().text() || "\u2014";

        $select.empty().append($("<option>").val("0").text(anyLabel));

        products.forEach(function (product) {
            $select.append($("<option>").val(product.id).text(product.label));
        });

        if (previous && $select.find('option[value="' + previous + '"]').length) {
            $select.val(previous);
        }
    }

    /* ===========================
       DYNAMIC UNIT SELECT (AMOUNTS)
    ============================ */
    $(document).on("change", ".mixmymeals-step-ingredient select.ingredient-select", function () {
        const $ingredientSelect = $(this);
        const ingredientId = $ingredientSelect.val();
        const $container = $ingredientSelect.closest(".mixmymeals-step-ingredient");

        fillProductSelect($container, ingredientId);

        if (!ingredientId) {
            $container.find(".ingredient-unit-select").remove();
            return;
        }

        $.ajax({
            url: ajaxUrl,
            method: "POST",
            data: {
                action: "mixmymeals_get_ingredient_units",
                nonce: ajaxNonce,
                ingredient_id: ingredientId
            },
            success: function (response) {
                if (response.success && response.data.html) {
                    $container.find(".ingredient-unit-select").remove();
                    const $unitSelect = $(response.data.html).insertAfter($container.find("input.small-text"));

                    const $stepRow = $container.closest(".mixmymeals-step-row");
                    const stepIndex = $stepRow.data("step");
                    const ingredientIndex = $container.index();
                    $unitSelect.attr("name", `mmm_recipe_steps[${stepIndex}][ingredients][${ingredientIndex}][amount_id]`)
                        .addClass("ingredient-unit-select");
                } else {
                    console.warn("No units found for ingredient", ingredientId);
                }
            },
            error: function (xhr, status, error) {
                console.error("Error loading units:", error, xhr.responseText);
            }
        });
    });

    /* ===========================
       SHOPPING LIST NOTICE
    ============================ */
    const showShoppingListSaveNotice = () => {
        const $container = $("#mixmymeals-shopping-list-container");
        $container.html('<p><em>Bitte speichern, um die Einkaufsliste zu aktualisieren.</em></p>');
    };

    $(document).on(
        "input change",
        ".mixmymeals-step-ingredient select, .mixmymeals-step-ingredient input, #_mmm_servings",
        showShoppingListSaveNotice
    );

    /* ===========================
       DAILY RECIPES – FLEXIBLE MEALS PER DAY
    ============================ */
    $(document).on("click", ".mixmymeals-add-meal", function (e) {
        e.preventDefault();
        const $day = $(this).closest(".mixmymeals-day");
        const $mealsContainer = $day.find(".mixmymeals-meals");

        const year = $day.data("year");
        const month = $day.data("month");
        const day = $day.data("day");
        const mealIndex = $mealsContainer.find(".mixmymeals-meal").length;

        const $mealRow = $(`
            <div class="mixmymeals-meal">
                <select class="mixmymeals-recipe-select" name="${window.mixmymeals_option_name}[${year}][${month}][${day}][${mealIndex}][recipe_id]">
                    <option value="">— Select Recipe —</option>
                </select>
                <input type="number" min="1" step="1" placeholder="Portions" class="mixmymeals-portions-input"
                    name="${window.mixmymeals_option_name}[${year}][${month}][${day}][${mealIndex}][portions]" />
                <button type="button" class="button mixmymeals-remove-meal">×</button>
            </div>
        `);

        if (window.mixmymealsRecipes && Array.isArray(window.mixmymealsRecipes)) {
            window.mixmymealsRecipes.forEach(r => {
                $mealRow.find("select").append($("<option>", { value: r.ID, text: r.post_title }));
            });
        }

        $mealsContainer.append($mealRow);
    });

    $(document).on("click", ".mixmymeals-remove-meal", function (e) {
        e.preventDefault();
        $(this).closest(".mixmymeals-meal").remove();
    });

    /* ===========================
    SHOPPING LIST HANDLING
    — ausgeblendet bei Änderungen
    ============================ */
    const $shoppingListContainer = $("#mixmymeals-shopping-list-container");

    // Funktion zum Verstecken & Hinweis anzeigen
    const hideShoppingListOnChange = () => {
        if ($shoppingListContainer.length) {
            $shoppingListContainer
                .html('<p><em>Bitte speichern, um die Einkaufsliste zu aktualisieren.</em></p>')
                .css({
                    opacity: 0.7,
                    fontStyle: "italic"
                });
        }
    };

    // Auf Änderungen an Zutaten, Einheiten, Mengen oder Portionen reagieren
    $(document).on(
        "input change",
        ".mixmymeals-step-ingredient select, .mixmymeals-step-ingredient input, #mmm_servings",
        hideShoppingListOnChange
    );

    /* ===========================
    MEDIA SELECTOR HANDLING
    ============================ */
    $('.select-media-button').on('click', function (e) {
        e.preventDefault();

        const targetField = $('#' + $(this).data('target'));

        // Create a new frame for each click
        const mediaFrame = wp.media({
            title: 'Bild auswählen',
            button: { text: 'Übernehmen' },
            multiple: false
        });

        mediaFrame.on('select', function () {
            const attachment = mediaFrame.state().get('selection').first().toJSON();

            // Write ID into hidden field
            targetField.val(attachment.id);

            // Replace preview
            const wrapper = targetField.closest('.media-field-wrapper');
            wrapper.find('.media-preview').remove();

            wrapper.prepend(
                '<img src="' + attachment.sizes.thumbnail.url + '" class="media-preview" style="max-width:120px; display:block; margin-bottom:10px;" />'
            );
        });

        mediaFrame.open();
    });

    /* ===========================
       MAILER TEST BUTTON (E-Mail tab)
    ============================ */
    $(document).on('click', '#mmm-send-test-email', function () {
        const $btn = $(this);
        const $result = $('#mmm-test-email-result');
        const to = $('#mmm-test-email-to').val();

        $btn.prop('disabled', true);
        $result.css('color', '').text('…');

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'mmm_send_test_email',
                nonce: $btn.data('nonce'),
                to: to
            }
        }).done(function (response) {
            const msg = (response && response.data && response.data.message) ? response.data.message : '';
            if (response && response.success) {
                $result.css('color', '#3F6F1E').text(msg || 'OK');
            } else {
                $result.css('color', '#D2622C').text(msg || 'Error.');
            }
        }).fail(function (xhr) {
            let msg = 'Request failed.';
            if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                msg = xhr.responseJSON.data.message;
            }
            $result.css('color', '#D2622C').text(msg);
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    /* ===========================
       MAILER SEND LOG — CLEAR
    ============================ */
    $(document).on('click', '#mmm-clear-mail-log', function () {
        const $btn = $(this);
        const $result = $('#mmm-clear-mail-log-result');

        $btn.prop('disabled', true);
        $result.css('color', '').text('…');

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'mmm_clear_mail_log',
                nonce: $btn.data('nonce')
            }
        }).done(function (response) {
            const msg = (response && response.data && response.data.message) ? response.data.message : '';
            $result.css('color', '#3F6F1E').text(msg || 'OK');
            $('#mmm-mail-log-table tbody').empty();
        }).fail(function () {
            $result.css('color', '#D2622C').text('Request failed.');
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    /* ===========================
       SUBSCRIPTION SCHEDULE ROWS
    ============================ */
    let mmmScheduleIndex = $('#mmm-schedule-table tbody tr').length;

    $(document).on('click', '#mmm-schedule-add', function () {
        const tpl = $('#mmm-schedule-row-tpl').html();
        if (!tpl) return;
        const html = tpl.replace(/__INDEX__/g, mmmScheduleIndex++);
        $('#mmm-schedule-table tbody').append(html);
    });

    $(document).on('click', '.mmm-schedule-remove', function () {
        $(this).closest('tr').remove();
    });

    $(document).on('click', '.mmm-schedule-test', function () {
        const $btn = $(this);
        const $row = $btn.closest('tr');
        const $result = $('#mmm-schedule-test-result');

        $btn.prop('disabled', true);
        $result.css('color', '').text('…');

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'mmm_sub_test_rule',
                nonce: $btn.data('nonce'),
                subject: $row.find('input[name$="[subject]"]').val(),
                template: $row.find('select[name$="[template]"]').val(),
                append: $row.find('input[name$="[append]"]').is(':checked') ? 'on' : '',
                to: $('#mmm-schedule-test-to').val()
            }
        }).done(function (response) {
            const msg = (response && response.data && response.data.message) ? response.data.message : '';
            if (response && response.success) {
                $result.css('color', '#3F6F1E').text(msg || 'OK');
            } else {
                $result.css('color', '#D2622C').text(msg || 'Error.');
            }
        }).fail(function (xhr) {
            let msg = 'Request failed.';
            if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                msg = xhr.responseJSON.data.message;
            }
            $result.css('color', '#D2622C').text(msg);
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });


    /* ===========================
       EQUIPMENT ON THE RECIPE SCREEN
       — create and rename without saving the post
    ============================ */
    const $equipmentTable = $('.mmm-equipment');

    if ($equipmentTable.length && window.MixMyMealsEquipment) {

        const eq = window.MixMyMealsEquipment;

        // The status line lives beside the table, not in the row: a message
        // inside a cell shifts the column widths of every row while it shows.
        const $eqStatus = $('<p class="mmm-equipment__status" role="status" aria-live="polite"></p>')
            .insertAfter($equipmentTable);

        // Without this script the piece is created by the post's own save, and
        // that is what the rendered hint says. Here it is created on Enter.
        if (eq.hint) {
            $equipmentTable.find('.mmm-equipment__hint').text(eq.hint);
        }

        let eqStatusTimer = null;

        const eqSay = (text, ok) => {
            window.clearTimeout(eqStatusTimer);
            $eqStatus.text(text).toggleClass('is-error', !ok);

            if (ok) {
                eqStatusTimer = window.setTimeout(() => $eqStatus.text(''), 4000);
            }
        };

        const eqPost = (action, data) => $.ajax({
            url: eq.ajaxurl,
            method: 'POST',
            data: $.extend({ action: action, nonce: eq.nonce }, data)
        });

        const eqMessage = (response, fallback) =>
            (response && response.data && response.data.message) ? response.data.message : fallback;

        /* --- Adding --- */

        const $eqNew = $equipmentTable.find('input[name="mmm_equipment_new"]');

        const addEquipment = () => {
            const title = $.trim($eqNew.val());

            if (!title || $eqNew.prop('disabled')) {
                return;
            }

            $eqNew.prop('disabled', true);

            eqPost('mmm_add_equipment', { title: title }).done(function (response) {
                if (!response || !response.success) {
                    eqSay(eqMessage(response, eq.failed), false);
                    return;
                }

                // find-or-create may hand back one the table already shows —
                // then tick that row rather than listing it twice.
                const $existing = $equipmentTable
                    .find('input[name="mmm_equipment[]"][value="' + response.data.id + '"]');

                if ($existing.length) {
                    $existing.prop('checked', true);
                    eqSay(eq.existing, true);
                } else {
                    $(response.data.html).insertBefore($equipmentTable.find('.mmm-equipment__new'));
                    eqSay(eq.saved, true);
                }

                $eqNew.val('');
            }).fail(function (xhr) {
                eqSay(eqMessage(xhr && xhr.responseJSON, eq.failed), false);
            }).always(function () {
                // Focus back in the field: adding one piece of equipment
                // usually means adding the next.
                $eqNew.prop('disabled', false).trigger('focus');
            });
        };

        $equipmentTable.on('keydown', 'input[name="mmm_equipment_new"]', function (event) {
            if (event.key === 'Enter') {
                // Or the block editor takes the Return as a submit.
                event.preventDefault();
                addEquipment();
            }
        });

        $equipmentTable.on('blur', 'input[name="mmm_equipment_new"]', addEquipment);

        /* --- Renaming --- */

        $equipmentTable.on('change', '.mmm-equipment__title', function () {
            const $input = $(this);
            const id = ($input.attr('name') || '').replace(/\D+/g, '');
            const title = $.trim($input.val());

            if (!id || title === $input.data('mmm-title')) {
                return;
            }

            $input.prop('disabled', true);

            eqPost('mmm_rename_equipment', { id: id, title: title }).done(function (response) {
                if (response && response.success) {
                    $input.val(response.data.title).data('mmm-title', response.data.title);
                    eqSay(eq.saved, true);
                    return;
                }

                // An empty name keeps the stored one, and the field has to say
                // so — otherwise it sits there blank and looks saved.
                const kept = response && response.data && response.data.title;

                if (kept) {
                    $input.val(kept);
                }

                eqSay(eqMessage(response, eq.failed), false);
            }).fail(function (xhr) {
                const kept = xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.title;

                if (kept) {
                    $input.val(kept);
                }

                eqSay(eqMessage(xhr && xhr.responseJSON, eq.failed), false);
            }).always(function () {
                $input.prop('disabled', false);
            });
        });
    }

});
