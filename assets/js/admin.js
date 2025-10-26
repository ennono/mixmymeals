jQuery(document).ready(function ($) {
    console.log("mixmymeals admin.js initialized");

    /* ===========================
       GLOBAL AJAX SETTINGS
    ============================ */
    const ajaxUrl = window.mixmymealsAdmin?.ajaxurl || window.ajaxurl || '/wp-admin/admin-ajax.php';
    const ajaxNonce = window.mixmymealsAdmin?.nonce || '';

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
                <input name="mixmymeals_recipe_steps[${index}][title]" class="regular-text">
            </p>
            <p>
                <label>Description</label><br>
                <textarea name="mixmymeals_recipe_steps[${index}][description]" class="large-text"></textarea>
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
            if (name) $(this).attr("name", name.replace(/\d+/, index));
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

        updateShoppingList();
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

        updateShoppingList();
    });

    /* ===========================
       INGREDIENT HANDLING
    ============================ */
    $(document).on("click", ".mixmymeals-add-ingredient", function () {
        const $step = $(this).closest(".mixmymeals-step-row");
        const $container = $step.find(".mixmymeals-step-ingredients");
        const $template = $("#mixmymeals-ingredient-template .mixmymeals-step-ingredient").clone();

        const stepIndex = $step.data("step");
        const ingredientIndex = $container.find(".mixmymeals-step-ingredient").length;

        $template.find("select").attr(
            "name",
            `mixmymeals_recipe_steps[${stepIndex}][ingredients][${ingredientIndex}][id]`
        );
        $template.find("input").attr(
            "name",
            `mixmymeals_recipe_steps[${stepIndex}][ingredients][${ingredientIndex}][amount]`
        );

        $container.append($template);
        updateShoppingList();
    });

    $(document).on("click", ".mixmymeals-remove-ingredient", function () {
        $(this).closest(".mixmymeals-step-ingredient").remove();
        updateShoppingList();
    });

    /* ===========================
       LIVE SHOPPING LIST UPDATE
    ============================ */
    const updateShoppingList = () => {
        const steps = [];

        $(".mixmymeals-step-row").each(function () {
            const $step = $(this);
            const stepData = {
                title: $step.find("input[name*='[title]']").val() || "",
                description: $step.find("textarea[name*='[description]']").val() || "",
                ingredients: [],
            };

            $step.find(".mixmymeals-step-ingredient").each(function () {
                const $ing = $(this);
                const id = $ing.find("select").val();
                const amount = $ing.find("input").val();
                if (id && amount) {
                    stepData.ingredients.push({ id, amount });
                }
            });

            if (
                stepData.title ||
                stepData.description ||
                stepData.ingredients.length > 0
            ) {
                steps.push(stepData);
            }
        });

        if (!steps.length) {
            $("#mixmymeals-shopping-list-container").html(
                "<p>No ingredients selected.</p>"
            );
            return;
        }

        $.ajax({
            url: ajaxUrl,
            method: "POST",
            data: {
                action: "mixmymeals_update_shopping_list",
                nonce: ajaxNonce,
                steps,
            },
            beforeSend: function () {
                $("#mixmymeals-shopping-list-container").html(
                    "<p>Updating shopping list...</p>"
                );
            },
            success: function (response) {
                if (response.success && response.data.html) {
                    $("#mixmymeals-shopping-list-container").html(response.data.html);
                } else {
                    console.error("Shopping list update failed:", response);
                    $("#mixmymeals-shopping-list-container").html(
                        "<p>Could not update shopping list.</p>"
                    );
                }
            },
            error: function (xhr, status, error) {
                console.error("AJAX error:", status, error, xhr.responseText);
                $("#mixmymeals-shopping-list-container").html(
                    "<p>Error loading shopping list.</p>"
                );
            },
        });
    };

    // Trigger Updates when ingredient values change
    $(document).on(
        "input change",
        ".mixmymeals-step-ingredient select, .mixmymeals-step-ingredient input",
        updateShoppingList
    );
});
