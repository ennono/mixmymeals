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
                <textarea name="mmm_recipe_steps[${index}][description]" class="large-text"></textarea>
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

        const $clone = $rows.length === 0
            ? createEmptyStep(0)
            : $rows.last().clone();

        if ($rows.length > 0) resetStepFields($clone, newIndex);
        $container.append($clone);

        showShoppingListSaveNotice();
    });

    $(document).on("click", ".mixmymeals-delete-step", function (e) {
        e.preventDefault();
        $(this).closest(".mixmymeals-step-row").remove();

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
            update: function () {
                reindexSteps();
                showShoppingListSaveNotice();
            }
        });
    }

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

        $template.find("select")
            .attr("name", `mmm_recipe_steps[${stepIndex}][ingredients][${ingredientIndex}][id]`)
            .addClass("ingredient-select");

        $template.find("input")
            .attr("name", `mmm_recipe_steps[${stepIndex}][ingredients][${ingredientIndex}][amount]`);

        $container.append($template);
        showShoppingListSaveNotice();
    });

    $(document).on("click", ".mixmymeals-remove-ingredient", function () {
        $(this).closest(".mixmymeals-step-ingredient").remove();
        showShoppingListSaveNotice();
    });

    /* ===========================
       DYNAMIC UNIT SELECT (AMOUNTS)
    ============================ */
    $(document).on("change", ".mixmymeals-step-ingredient select.ingredient-select", function () {
        const $ingredientSelect = $(this);
        const ingredientId = $ingredientSelect.val();
        const $container = $ingredientSelect.closest(".mixmymeals-step-ingredient");

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

});
