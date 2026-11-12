# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

MixMyMeals is a WordPress plugin (v4.0.0, text domain `mixmymeals`, PHP 8.0+) for meal planning: recipes, ingredients, products, meals and meal plans, with a shopping list generator and a frontend planner (the "cart") for building custom plans. Two companion plugins depend on it but are unknown to it: `mixmymeals-social` (Instagram/Pinterest) and `mixmymeals-printer` (PDF export). They reach this plugin only through the extension points listed under *Add-ons*. This plugin makes no external API calls.

## Development commands

```bash
# Lint against WordPress coding standards (binary is not on PATH)
./vendor/bin/phpcs --standard=phpcs.xml <file>
./vendor/bin/phpcbf --standard=phpcs.xml <file>

# WordPress.org's checks. Install once: wp plugin install plugin-check
wp plugin check mixmymeals --format=json

# Rebuild a minified script after editing its source (also admin.js, settings.js)
npx terser@5.51.2 assets/js/frontend.js -c -m -o assets/js/frontend.min.js

# Translations: regenerate the template, translate new entries in the .po by hand, then compile both
wp i18n make-pot . lang/mixmymeals.pot --domain=mixmymeals --exclude=vendor,tools,lang,node_modules --skip-js
wp i18n make-mo lang/mixmymeals-de_DE.po lang
wp i18n make-php lang/mixmymeals-de_DE.po lang
```

- **Translations**: the `.mo` and `.l10n.php` of 3.76.3 are byte for byte what `make-mo` / `make-php` produce from the `.po`. Every `.po` entry needs a blank line before the next one's comments — one without it was silently dropped from both compiled files from 3.52.0 to 4.0.0.

- **Minifying**: terser, compress + mangle, no options beyond `-c -m`, no header comment. Pinned to 5.51.2 because that version reproduces the `.min.js` files of 3.76.3 byte for byte — a rebuild of an unchanged source must leave the file unchanged, so a diff of a `.min.js` shows only what its source changed. Check with `node --check` afterwards. Never hand-edit a `.min.js`.

- `phpcs.xml` **aborts** on some files: the bundled PHPCompatibility sniff triggers a PHP deprecation and stops checking, which looks like a clean result. Then run `--standard=WordPress` for real output.
- **Reformatting**: `./vendor/bin/phpcbf --standard=WordPress --exclude=Squiz.PHP.EmbeddedPhp <file>`. Never without the exclusion: that sniff moves `<?php` / `?>` onto lines of their own and so puts line breaks into the HTML between a tag and its text (inside chips, attributes). The whole plugin was formatted this way in November 2026; what `phpcs` still reports is not auto-fixable (doc comments, Yoda conditions, short ternaries, comment punctuation). An embedded call that spans lines on a line that begins with HTML makes `phpcbf` loop ("FAILED TO FIX") — assign it to a variable in a `<?php ?>` block above instead.
- Plugin Check flags `.DS_Store`, `.idea/`, `.claude/`, `CLAUDE.md` etc. `.distignore` keeps all of them out of the package. Judge the package, not the checkout.

### Tests

There is no PHPUnit suite. `tools/*-test.php` are assertion scripts that run against a real install (terms, meta, products and the caches between them are what they test), create their own posts and delete them again:

```bash
cd <site>/app/public
wp --exec="define('DB_HOST','localhost:<socket>');" \
   eval-file wp-content/plugins/mixmymeals/tools/diet-test.php
```

Test posts are titled `T …` or `R<n>` and carry the brand `ZZ`, so a run cut short leaves something recognisable to clean up. One script per subject — `diet`, `meal-diet`, `excluded-products`, `reference-product`, `product-usage`, `bulk-update`, `cooking-factor`, `nutrition-coverage`, `traffic-light`, `reference-intake`, `prep-steps`, `meal-text`, `password`, `plan-meals`, `plan-number`, `plan-servings`, `plan-eaters`, `plan-subscribe`, `planner-shopping`, `planner-templates`, `planner-tools`, `cart-share`, `equipment`, `privacy-policy`, `admin-structure`, `author-image`. Run the one for the subject you touched.

Conventions the scripts rely on:
- Save callbacks are tested by a **round trip through the callback**, not through the setter behind it (see *Recipes*).
- AJAX handlers need `define( 'DOING_AJAX', true )` and a filtered `wp_die_handler`, or `wp_send_json()` ends the script at the first call.
- `admin-structure-test.php` builds the real admin menu by requiring `wp-admin/menu.php`; that works under wp-cli once `$pagenow`, `$self` and a current screen are set.

## Environment

Development runs under **Local (by Flywheel)**: site `mixmymeals.local`, rooted at `/Users/timo/Local Sites/mixmymeals/app/public/`. MySQL listens on a private socket, so wp-cli needs `DB_HOST` predefined — a one-line `--require` file pointing at `~/Library/Application Support/Local/run/<id>/mysql/mysqld.sock`. The id changes when Local restarts and several sites may run side by side, so **check `home_url()` before writing anything** — the wrong socket silently belongs to another site.

Two things about this install mislead:
- The active theme is **Twenty Twenty-Five**, not `mixmymeals-astra-child`. Loading a page proves nothing about the child theme's templates, and `get_stylesheet_directory()` does not point at it. Read those files by path.
- It holds almost no content. Plans, nutrition and shopping lists need test data built first — and removed afterwards.

Production is **speisengeplant.de** (until October 2026 stopfnicht.de, which no longer exists; older code comments still name it). Pushing to GitHub does not deploy. The `?ver=` in the page source tells what is live, which is why each user-visible fix gets its own patch release.

`/audit` returns `plugin_version`, and every group the running version knows is always present (empty ones with a count of zero). A group missing *entirely* means the deployed plugin is older than whatever named it — not a fault in the endpoint. MCP tool descriptions update with the client file, the plugin only on deploy; that drift has been reported as a bug more than once.

JavaScript ships as `assets/js/*.js` (debug) and `assets/js/*.min.js` (production), chosen by `SCRIPT_DEBUG`. **Keep both in sync.**

**EWWW Image Optimizer on the live host**: its Lazy Load draws PNG placeholders with GD, which kills the PHP process there (bare 500 on the front end, no log line, wp-admin unaffected). `mixmymeals.php` therefore sets `add_filter( 'eio_use_piip', '__return_false' )` at file scope, before any `require_once` — keep it there, not in a class. Social sets the same filter; until 3.75.1 it was the only place, so deactivating Social took the site down. The plugin-independent switch is `EWWW_IMAGE_OPTIMIZER_USE_PIIP` = false in `wp-config.php`. Locally (macOS) EWWW causes a different crash: its bundled binaries fork the PHP-FPM worker, which macOS aborts (`objc … fork()` in `logs/php/php-fpm.log`).

## Rules that hold everywhere

These are the lessons behind most of the bugs this codebase has had. Each section below names its own instances.

- **One number, one method.** Several of the worst bugs were two channels computing one figure separately. Where a canonical reader exists, call it — never recompute locally. The important ones: `Mixmymeals_Ingredient::get_nutrition()` / `get_nutrition_pinned()`, `has_nutrition()`, `effective_product_id()`, `Mixmymeals_Recipe::get_ingredients_scaled()`, `Mixmymeals_Meal_Plan::calc_nutrition_per_day_person()`, `get_meal_rows()`, `row_recipe()`, `with_servings_offset()`, `for_eater()`, `calc_prep_by_day()`, `Mixmymeals_Meal::get_recipe()`, `resolve_credit_source()`, `Mixmymeals_Post_Meta::get_author_data()`, `Mixmymeals_Ingredient_Amount::get_step_divisor()`, `Mixmymeals_Diet::label()`, `Mixmymeals_Settings::settings_url()`.
- **Null is not zero.** "Not stated" and "contains none / is zero" are different claims, especially about somebody else's product. Setters delete the row for null/''; serialization keeps `null` rather than omitting the key; tables print a dash, never `0,0 g`. Exceptions are named where they occur (preparation time).
- **Omitting a key and sending null are different instructions** on every merge-style write: absent leaves the value alone, null/`""` clears it.
- **Per-request caches need a way back out.** Every memoised read path has an invalidation (`set_steps()`, `register_meal_cache()`, `set_for_post()` …). One request that writes and then serialises what it wrote (`/meal-plans/build`, every REST update) is the ordinary case that breaks without it.
- **Save callbacks rebuild posted arrays key by key**, so a new field must be added to the callback too, or it is silently dropped.
- **A meta box that creates rows with no natural key needs a one-shot token** (`spend_new_row_token( $scope, $token )`): the block editor posts meta boxes again on a second Save without re-rendering them. Rows with a natural key use find-or-create instead.
- **Each meta box has its own nonce and its own `save_post_*` hook**, so a screen that does not render it cannot wipe its data by not posting it. Blanking a required field (brand, recipe, name) keeps the stored value — blanking is a loss, not an edit.
- **Validate closed vocabularies on read as well as write**; an unknown stored value reads as empty/default, never passes through.
- **Bulk endpoints: per-item failures continue, a caller bug (unknown enum, unknown key) rejects the whole request** before anything is written.
- **Plugin computes, theme presents** (see *Plugin vs. theme*).
- **Anything touching personal data changes `Mixmymeals_Privacy::policy_section_html()`**; **anything that deletes data calls `Mixmymeals_Cleanup::record()`**.
- **Core protects `the_content()` and nothing else.** A password on a plan, recipe or meal is honoured only where code asks: `Mixmymeals_Post_Types::is_locked( $id )` for a visitor who may have entered it (pages, AJAX, "load into planner", planning a recipe via `recipe_is_plannable()`, subscribing), `has_password( $id )` for channels with nobody to enter it (REST via `may_read()`, the Bring! page, schema, OG tags, `meta_description()`, noindex), `password_form( $id )` for what to print instead. A meal is protected by its plan's password. `exclude_unlisted()` and the archive main query leave protected posts out (`has_password => false`). A new way to read a plan needs one of these; the child theme's singles call `mmm_render_locked_single()`. The Printer's PDF endpoint asks the same two methods (Printer 2.4.1).
- **Public names (slugs, query vars, action names, CSS classes used by the theme or add-ons) are API.** Before adding a path under `/rezepte/`, check existing recipe slugs. Before renaming a tab, add the old key to `TAB_ALIASES`. Before changing a method the Printer calls, see *Add-ons*. The planner's names from 4.0.0 (actions, `data-*` hooks, classes the Hello child theme styles) are listed under *Planner tools*.

## Architecture

### Bootstrap

`mixmymeals.php` requires all class files and instantiates `Mixmymeals::instance(__FILE__, VERSION)` at the bottom. The singleton wires assets, localisation and cart merging on login. Settings, post types, taxonomies, shortcodes and meta boxes are instantiated via wrapper methods on the main class. Several features attach their hooks once per request from `mixmymeals.php` via a static `register_*()` (`Mixmymeals_Recipe::register_cook_count()`, `register_edit_count()`, `register_recipe_index()`, `Mixmymeals_Meal_Plan::register_bring()`, `Mixmymeals_Cart::register_ajax()`, `Mixmymeals_Planner_Tools::register()`, `Mixmymeals_Author::register_term_admin()` …).

New features go into the existing class that owns the subject; `includes/lib` holds the feature modules:

| Class | Purpose |
|---|---|
| `Mixmymeals_Search` | Grouped site search the child theme's search page prints |
| `Mixmymeals_Planner_Tools` | Ways into the planner that do not start from a recipe page: suggested plan, recipe picker per day, week at a glance (see *Planner tools*) |
| `Mixmymeals_Privacy` | GDPR exporters/erasers; generated privacy-policy section |
| `Mixmymeals_Subscriptions` | Plan e-mail subscriptions: double opt-in, consent records, drip sending via WP-Cron, token endpoints |
| `Mixmymeals_Mailer` / `Mixmymeals_Mail_Log` | `wp_mail` wrapper with GDPR footer + send log |
| `Mixmymeals_Ratings` | Comment-based star ratings, aggregates in `_mmm_rating_avg/_count` |
| `Mixmymeals_Photos` | Visitor meal-photo uploads (MIME whitelist, EXIF stripped), moderation, gallery |
| `Mixmymeals_Rest_Api` | REST namespace `mixmymeals/v1` |
| `Mixmymeals_Cleanup` | WP-Cron cleanup and its report mail |
| `Mixmymeals_User_Roles` | Custom roles (meal mixer) |
| `Mixmymeals_Admin_API` | Settings-field rendering used by `Mixmymeals_Settings` |

`tools/mcp-server.mjs` is the companion MCP server for automation (not distributed).

### Post types and taxonomies

All prefixed `mmm_`:

| Post type | Class | Notes |
|---|---|---|
| `mmm_recipe` | `Mixmymeals_Recipe` | Public, archive, slug option |
| `mmm_ingredient` | `Mixmymeals_Ingredient` | Public, archive, slug option |
| `mmm_ingr_unit` | `Mixmymeals_Ingredient_Amount` | Hidden, child of an ingredient |
| `mmm_product` | `Mixmymeals_Product` | Hidden, child of an ingredient |
| `mmm_meal_plan` | `Mixmymeals_Meal_Plan` | Public, archive |
| `mmm_meal` | `Mixmymeals_Meal` | Child of a plan; public but noindex, not in menu |
| `mmm_equipment` | `Mixmymeals_Equipment` | Public, archive, slug option; under the Recipes menu |

Slug options: `mixmymeals_recipe_slug`, `_meal_plan_slug`, `_meal_slug`, `_ingredient_slug`, `_equipment_slug`, `_author_slug`. After changing slugs, permalinks must be flushed (ingredient/equipment slug options schedule a flush).

**`public => true` proves nothing**: `publicly_queryable`, `has_archive`, `rewrite` and `query_var` each override it. Equipment had all four `false` for years while its templates and shortcodes kept rendering and every "Required equipment" link 404'd — a post type whose front end nobody visits can lose it without an error. On running installs `Mixmymeals_Post_Types::maybe_flush_equipment_rules()` (`init` 95, gated on `mixmymeals_equipment_rules_version`) raises `mixmymeals_flush_needed` once, before `maybe_flush_rewrite_rules()` at 99.

Taxonomies: `mmm_recipe_category` (hierarchical), `mmm_recipe_tag`, `mmm_ingredient_category` (groups the shopping list), `mmm_meal_plan_category`, `mmm_meal_plan_tag`, `mmm_diet` (see *Diets*), `mmm_author` (see *Author box*), plus the equipment categories.

### Recipes

**Steps** are a serialized array in `_rmm_steps`. Each step: `title`, `description`, `image`, `ingredients`, optionally `prep_amount` / `prep_unit`. Each ingredient row:

| Key | Meaning |
|---|---|
| `id` | ingredient post ID |
| `amount` | float |
| `amount_id` | unit post ID; none = amount in grams |
| `optional` | bool; absent = required. Printed "(optional)" — wrapped in `span.mmm-ingredient-optional` on the four frontend renderings, bare in PDF and mails |
| `product_id` | the packet this dish is cooked with (the *pin*), see *Products* |
| `excluded_products` | ids of that ingredient's products ruled out for this dish, see *Products* |

**`save_recipe_steps_meta_box()` rebuilds the posted array key by key**, so anything it does not name is dropped before `set_steps()` sees it. It has lost `optional` and, for much longer, `image`. A new step or row field needs a line there, tested by a round trip through the callback.

**The two ingredient markups in `Mixmymeals_Post_Meta` — the rendered row and the hidden `#mixmymeals-ingredient-template` — must carry the same classes.** `admin.js` names cloned fields by selecting `ingredient-amount-input` / `ingredient-optional-input`, and the unit-select AJAX inserts after `input.small-text`. A missing class yields a nameless input the browser omits from the POST.

**Memoised per request**: `calculate_nutrition_per_serving()`, `calculate_cost_per_serving()`, `get_cooked_ingredients()`, keyed `[ post_id ][ diet ]`, cleared by `set_steps()`.

**Cook counter** `_mmm_cook_count` (`META_COOK_COUNT`): how often the recipe sits on a published plan (meal **and** parent plan published). Never incremented — `recount_cook_count()` recomputes from the DB, batched to `shutdown` via `flush_cook_counts()`. Zero = no meta, so `[mmm_uncooked_recipes]` is a `NOT EXISTS` query and `[mmm_popular_recipes]` orders by `meta_value_num`. Editable in Details: `set_cook_total()` stores the signed difference in `_mmm_cook_count_adjust` (result floored at 0). Hooks watch **meal and plan** events (`wp_after_insert_post`, `transition_post_status`, meta writes, `before_delete_post`). Backfill via `mixmymeals_cook_count_version`; `recount_all_cook_counts()` doubles as repair.

**Edit counter** `_mmm_edit_count` (`META_EDIT_COUNT`), fed by `count_edit_on_update()` on `post_updated` (skips auto-draft creation, autosaves, revisions), shown via `[mmm_recipe_edit_count]`. Not built on revisions: WP versions only title/content/excerpt, so meta-only edits create none.

**Preparation time** `_mmm_preparation_time`: `set_preparation_time( 0 )` deletes the row — "0 minutes" is not an answer, so the absence of the row means "not stated" and `compare => '<='` excludes untimed recipes. The core meta route can still write 0; `recount_indexes()` clears it on the next write.

**Preparation steps.** `prep_amount` (int ≥ 1) + `prep_unit` (`days` | `hours`) mark work done ahead (soaking, marinating). `step_prep()` / `normalise_step_prep()` is the rule (anything short of both is an ordinary step), `prep_label()` the wording ("2 Tage zuvor"). Preparation always sorts first, longest lead first, stable otherwise: `sort_steps()` runs in `set_steps()` **and** `get_steps()`, because the core meta route bypasses `set_steps()`. Editor: checkbox `[prep]` (`.mixmymeals-step-prep-toggle`) enables number and unit; the callback reads timing only under a ticked box, so unticking makes a step ordinary. `mmmSortPrepSteps()` reorders live. On recipe and meal pages each `.mmm-step` has `id="mmm-step-<n>"` (`step_anchor()`); prep steps add `mmm-step--prep` and the `step_prep_badge()` line, but are **not coloured** (site owner's decision). For how they land on plan days see *Preparation on the plan*.

**Shelf life and storage** — two fields on both recipe and ingredient, meaning different things (the cooked dish vs. the raw ingredient). **Never fall back from one to the other.**
- `_mmm_shelf_life_days` (`META_SHELF_LIFE`), `get_shelf_life(): ?int` — null = not stated, 0 = eat the same day. The admin input prints nothing for null; the save handler tests the raw string before `absint()`. REST/MCP `shelf_life_days`, type `[integer, null]`, `minimum 0`, serialized as `null` never omitted; null clears.
- `_mmm_storage_type`: closed vocabulary from `Mixmymeals_Post_Types::storage_types()` (`room_temperature`, `cool_dry`, `refrigerated`, `frozen`), `get/set_storage_type()`, `is_storage_type()`. Meta, not a taxonomy, so editors cannot grow four spellings of "fridge". Validated on read and write; unknown clears. REST/MCP `storage_type`, enumerated. Single value, because one shelf life cannot describe fridge and freezer.
- Rendered as **one** chip, `Mixmymeals_Shortcodes::keeping_chip( $post_id, $base )`, last in the hero row of `mmm_recipe_hero` / `mmm_meal_hero` (meal resolves via `resolve_credit_source()`; plans get none; '' when neither is set). Wording translated whole per case: "4 Tage im Kühlschrank", "4 Tage haltbar", "Am selben Tag, im Kühlschrank", "Am selben Tag", or the bare storage noun. A 0 reads "Am selben Tag", never "0 Tage".
- `POST /keeping/bulk` (MCP `mmm_set_keeping`) writes both for many recipes/ingredients at once, answering `id`, `type`, `ok` plus an error list (~12 tokens/item instead of ~500). Per-item errors `not_found`, `wrong_type`, `missing_id`, `no_fields`; an invalid `storage_type` rejects the request.

**Equipment** is linked via `_mmm_equipment` (array of equipment IDs) and edited in a full-width box on the recipe (`render_equipment_meta_box_content()` / `save_equipment_metabox()`). Equipment has no parent, so a rename here reaches every recipe using it: the box shows each piece's recipe count and offers **no delete**. Adding/renaming goes over AJAX (`ajax_add_equipment()` / `ajax_rename_equipment()`, nonce `mmm_equipment_ajax`, localised as `MixMyMealsEquipment`), answering with `equipment_row()` markup; the tick itself is still saved with the recipe. The empty row resolves via `find_or_create_equipment()` on every save — **no one-shot token**, which would untick a piece created by the first of two block-editor saves. The no-JS path still works; `.mmm-equipment__hint` is swapped by script. `Mixmymeals_Equipment::usage_counts()` (memoised) feeds `count_recipes()`; `get_recipe_ids()` keeps a transient that `set_equipment()` clears via `flush_recipe_cache()` for pieces added and dropped.

### Ingredients and nutrition

**Eight figures per 100 g**, the EU mandatory set: `_rmm_calories_100`, `_rmm_fat`, `_mmm_saturates` (added last, hence the newer prefix), `_rmm_carbs`, `_rmm_protein`, `_rmm_sugar`, `_rmm_fiber`, `_rmm_salt`. Units and products are child posts. A ninth figure means editing these lists and nothing else: `Mixmymeals_Ingredient::NUTRITION_META`, `NUTRITION_FIGURE_KEYS`, `NUTRITION_KEY_MAP`, `Mixmymeals_Product::NUTRITION_FIELDS`, `Mixmymeals_Meal_Plan::NUTRITION_KEYS`, `REFERENCE_INTAKE`, `NUTRITION_DIRECTIONS`, `nutrition_labels()`.

**`get_nutrition()` / `get_nutrition_pinned()` are the only places ingredient nutrition is read.** The `_rmm_*` keys are touched directly only inside `Mixmymeals_Ingredient` and where REST registers them. Everything — recipe, plan, PDF, mails, calculator, shopping list, `_calculate_total_nutrition()` — goes through them, because they resolve the product (and anything added later).

**Two questions, two rules:**
- `has_nutrition()` — *may it be published?* Value-based: any figure above zero counts, resolved through the product. Value-based because pre-3.52.0 saves stored `floatval('')` = 0 in every blank field, so a null test would call the whole backlog filled in. Feeds the "Ohne Nährwerte" list view (`ids_without_nutrition()`, primed), the publish gate and `/audit`'s `ingredients_without_nutrition`. Lists say "no nutrition data", not "error" (water is genuinely zero).
- `unstated_nutrition_keys()` — *what are the sums missing?* Only `null` is a gap (a typed 0 is an answer). On old installs blanks are stored zeros and read as stated; clearing them is a data decision, not a migration.

`set_nutrition()` deletes the row for an empty value. **`null` withdraws a figure on every write path**, including `/ingredients/{id}/nutrition`.

**Publish gate**: `Mixmymeals_Post_Meta::require_ingredient_nutrition()` (`save_post_mmm_ingredient`, priority 20) sets an ingredient without nutrition back to draft — pickers query `publish`, while `is_valid()` ignores status so existing recipes keep working. Guarded by the ingredient screen's own nonce, so quick/bulk edit and REST are out on purpose (MCP creates first and POSTs nutrition second; `/audit` reports those). The message sits **inside the nutrition meta box**, because the block editor drops admin notices.

**Plausibility**: `nutrition_warnings()` is the only check that can catch a typo, since a misread decimal looks like a dense food everywhere downstream. Five rules: "of which" rows ≤ their parent; mass ≤ 100 g (fibre added separately); energy follows the macros at 9/4/4/2 kcal/g with 20 % slack; nothing above 900 kcal or 100 g. **Warnings, never a block.** Shown in the nutrition box (resolved figures) and under each product's fields; `/audit` `nutrition_implausible`.

**Coverage**: `Mixmymeals_Recipe::nutrition_coverage()` says per figure how much of the dish's weight its figures describe, over the same rows as `_calculate_total_nutrition()` (which must read missing as 0). Returns per-key shares, `worst` (ties to the figure a label lists first), `share` (= the worst, never an average), `missing` (ingredient → keys) and `complete`, which tolerates gaps up to `NUTRITION_TOLERANCE` (2 % of weight). Shown beside `kcal_serv` in the recipe list; `/audit` `recipes_partial_nutrition`. Gap labels from `Mixmymeals_Ingredient::nutrition_label()`.

**Reference intakes** are settings: `Mixmymeals_Meal_Plan::reference_intake( $profile = adult )` starts from `REFERENCE_INTAKE` (EU adult), overrides with options `mixmymeals_reference_intake_<key>` (profiles: `mixmymeals_reference_intake_profile_<profile>_<key>`, `reference_intake_profile_option()`), then runs filter `mixmymeals_reference_intake` ($figures, $profile). Settings: Meal Plans tab, `reference_intake_fields()`, `sanitize_reference_intake()` (positive number or empty), prefix `REFERENCE_INTAKE_OPTION_PREFIX`. Zero/negative/text fall back to the EU value. `reference_intake_note()` and `day_rating_note()` say "an average adult (2 000 kcal)" only while `reference_intake_is_default()`.

**Directions**: `NUTRITION_DIRECTIONS` / `nutrition_direction()` (filter `mixmymeals_nutrition_directions`): `limit` (fat, saturates, sugars, salt), `target` (protein, fibre), `reference` (energy, carbohydrate). `format_nutrition_percentage()` prints the direction and the figure in the cell — "75 % von max. 70 g", "60 % von Ziel 30 g", "93 % von 2.000 kcal" (via `format_reference_value()`). Printing a percentage without a direction made "100 % salt" read as an achievement.

**Rounding**: `round_nutrition_value()` (`nutrition_decimals()`: 0 energy, 2 salt, 1 otherwise) is shared by `format_nutrition_value()` and the traffic light, so a value is rated as printed. Whether two figures differ is `nutrition_value_is_printable()`, comparing rendered strings.

**Traffic light**: `Mixmymeals_Ingredient::traffic_light( $key, ?$per_100g, ?$per_serving )` → `amount` (low/medium/high), `colour` (green is high for a `target`, low otherwise), `basis` (`100g`/`serving`). Thresholds per figure in option `mixmymeals_traffic_light_<key>` (`low`/`high`, Ingredients tab, `traffic_light_fields()`); never saved → `TRAFFIC_LIGHT_DEFAULTS` (FSA, for `TRAFFIC_LIGHT_KEYS` fat/saturates/sugars/salt); saved empty → off (`false`); inverted → off. Filter `mixmymeals_traffic_light_thresholds`.
- **Portion rule** (recipes and meals only): a `limit` figure whose serving exceeds `traffic_light_portion_share()` % of the reference intake (option `mixmymeals_light_portion_share`, default `PORTION_SHARE_DEFAULT` 30, empty = off, filter `mixmymeals_traffic_light_portion_share`) is red with `basis => serving`, "hoch pro Portion" (`traffic_light_label()`). Compared as whole per cent.
- **Saturates gap**: a stored 0 g saturates beside > 3 g fat (`saturates_zero_is_gap()`, `SATURATES_GAP_FAT`) is a pre-3.52.0 blank; no saturates light on that ingredient or anything containing it (`Mixmymeals_Recipe::has_saturates_gap()`). Not part of `nutrition_coverage()`.
- `Mixmymeals_Shortcodes::traffic_light_figures( $post_id )` is the one source per subject (ingredient resolved; recipe `calculate_nutrition_per_100g()`; meal `meal_nutrition_figures()`; plan `plan_traffic_light_figures()`). `Mixmymeals_Recipe::unreliable_nutrition_keys( $recipe_ids )` (incomplete coverage + saturates gap) is the one answer to "may this sum be judged" — for the light and the plan's "too little". Null is never green. `traffic_lights( $post_id )` rates; every renderer reads it.
- Markup `span.mmm-traffic-light.mmm-traffic-light--{green|amber|red}[data-amount][data-basis]` after the per-100 g figure in the four tables; `[mmm_nutrition_traffic_lights post_id=""]` prints `ul.mmm-traffic-lights`. Explanation `traffic_light_note( $portion )` as footnote `mmm-nutri-note--light`. Not in the product comparison, PDF, mails or REST.

**Day rating (plans)**: `Mixmymeals_Meal_Plan::rate_day( $per_day, $whole_days, $unreliable )` compares whole-per-cent shares with `day_rating_thresholds()` (options `mixmymeals_day_rating_<key>` `over`/`under`, defaults `DAY_RATING_DEFAULTS`, filter `mixmymeals_day_rating_thresholds`; settings `day_rating_fields()` on the Meal Plans tab). Which ends exist follows `nutrition_direction()` (`day_rating_ends()`). "Too much" always; **"too little" only where the plan covers whole days** (`covers_whole_days()`: every day with meals has breakfast, lunch and dinner, `WHOLE_DAY_MEAL_TYPES`) and the figure is reliable. `calc_day_rating()` for the plan, `calc_day_ratings()` per day (`is_whole_day()`); with several people both ask `for_eater()`. Printed in the `ri` column as `span.mmm-day-rating--{over|under}`; `day_rating_note()` (footnote `mmm-nutri-note--rating`) is deliberately short and **replaces** the reference-intake note where a column has ratings.

**Cooking factor** corrects weight, never nutrients. `_mmm_cooking_factor` (ingredient), `_mmm_product_cooking_factor` (product): cooked weight per raw gram; null = not stated, 1.0 is kept as a statement. `get_cooking_factor_pinned( $pinned )` resolves product → ingredient → null; `cooked_weight( $raw, $pinned )` is the one conversion. Applied only to weights something is **divided by** (recipe total weight in `_calculate_total_nutrition()`, `meal_nutrition_figures()`, the calculator), so only per 100 g, the light and the printed serving weight move. Shopping list, costs and product impact stay raw. Edit screens show `get_own_cooking_factor()`; `normalise_cooking_factor()` accepts a decimal comma and drops ≤ 0 and > `COOKING_FACTOR_MAX` (20). REST: ingredients read `cooking_factor` + `own_cooking_factor`, write only the latter; products read/write `cooking_factor`. Tables name applied factors (`get_cooked_ingredients()`, `{name, factor}` per ingredient **and** factor, only where `abs( cooked − raw ) > 0`) via the `{cooked}` token and `Mixmymeals_Nutrition_Table::cooked_note()` (`p.mmm-nutri-note--cooked`, up to six, semicolon-separated, `_n()`), switch `cooked_note` per table.

**Nutrition tables**: `Mixmymeals_Nutrition_Table` owns what each subject (`ingredient`, `recipe`, `meal`, `plan`) may show (`available_columns()`, filter `mixmymeals_nutrition_table_columns`) and what the site chose (`config()`, option `mixmymeals_nutrition_table_<context>`: `label`, `light`, `columns[key] = show/title`). `Mixmymeals_Shortcodes::render_nutrition_table()` prints; callers only supply formatted figures. Never saved → everything on except the recipe/meal `ri` column; empty heading → default; `{weight}` in a serving heading is the grams. At least one column stays on. Switches like `cooked_note` and `ri_note` are **read by key**: an option saved before the switch existed must read as on. Every table carries `mmm-nutrition-table mmm-nutri-table mmm-nutri-table--<context>`, `data-label` per cell and `mmm-nutri-table__col--<column>`; the product comparison (`--products`) and the per-weekday table (`--days`) share the skeleton but are exempt from the phone card collapse. A table whose columns would be empty is not printed.

**Footnotes**: `Mixmymeals_Nutrition_Table::footnotes( $config, $cooked )` decides notes and marks together (`*`, `**` in print order, only for notes that print). Kinds: reference intake (`ri` shown + `ri_note`), cooking (factor moved weight + `cooked_note` + `serving` or `per_100g` shown), rating, light, `open` (see *Diets*). `config()` strips trailing `*` from stored headings.

**Disclaimer on ingredient pages**: option `mixmymeals_recipe_disclaimer_on_ingredients` makes `[mmm_disclaimer_for_a_single_ingredient]` return the recipe disclaimer's text (class `mmm-recipe-disclaimer`) — one wording, no second setting. Returns '' when off or empty, so the theme (`single-mmm_ingredient.php`, Note section like `mmm_recipe_section_note()`) can omit its heading.

**schema.org `sodiumContent` is sodium; this plugin stores salt**: `get_schema()` divides by 2.5.

### Products

`mmm_product` (`Mixmymeals_Product`): a branded article an ingredient can be bought as, child via `post_parent`. Meta: the nutrition keys under `_mmm_product_*`, `_mmm_product_package_weight` (g), `_mmm_product_brand` (**required**: the admin field is `required` on existing rows only — never the blank row, which is enforced on save; `POST /products` requires it; an update with empty brand keeps the stored one), `_mmm_product_source` (where the figures came from, since they go stale), `_mmm_product_price` (`META_PRICE`, price of **one package**), `_mmm_product_diet_type` (legacy), `_mmm_product_cooking_factor`. Edited in the Products box on the ingredient (`render_ingredient_products_meta_box_content()` / `save_ingredient_products()`), own nonce and hook. Product `set_nutrition()` deletes on null/''. `label()` is "Brand Title", falling back to the title; `reference_label( $ingredient_id )` the same from an ingredient.

**Reference product** (`_mmm_reference_product` on the ingredient, `get_reference_id()` / `set_reference_id()`; re-validated on every read, refuses a product of another ingredient): the site's default packet for the ingredient. `get_nutrition()` merges **key by key**, product first (one shared private `apply_product()` with `get_nutrition_with_product()`); `weight_item` is never overridden. Price resolves the same way: product `get_price_per_100g()` = price / package weight (null unless both stated), preferred by `Mixmymeals_Ingredient::get_price_per_100g()`; `calculate_cost_for_weight()` is the only cost reader. **Edit screens show the own layer** (`get_own_price_per_100g()`, `get_own_cooking_factor()`) — showing the resolved value would copy the product's figure into the ingredient on save. The products box has a radio `0` (group `mmm_products_reference`) "Use the ingredient's own nutrition and price" above the rows, or the own figures become unreachable once a product is picked.

**Pin** (`product_id` on a recipe row): *this dish is made with that packet*; outranks the reference. One decision, `Mixmymeals_Ingredient::effective_product_id( $ingredient_id, $pinned )`: pin, else reference, and a product of a different ingredient counts as nothing. Three getters use it: `get_nutrition_pinned()`, `get_price_per_100g_pinned()`, `get_diets_pinned()`; the parameterless getters call them with 0. `pinned_product_id()` answers the other question — *did the recipe decide* (the reference does not count).
- The recipe's and meal's ingredient lists print only a pin (`span.mmm-ingredient-product`); the shopping list prints the effective product, **only where the whole plan agrees on one** (`calc_shopping_list()` puts `product_id` / `product` on the row; disagreement → 0 / ''). Not in the Bring! export (it would change what Bring! parses).

**Exclusions** (`excluded_products` on a row): validated like the pin (another ingredient's id is dropped); **the pinned product is dropped from the list** (`set_steps()` enforces; the editor disables its box, `syncPinnedExclude()`, `label.is-pinned`). Purpose: `Mixmymeals_Ingredient::get_diet_suitabilities( $without )` merges the ingredient's diets with every product's, so one vegan packet makes it vegan-capable — exclusions keep a sweetened soy yoghurt out of a savoury dish. Stored per row, in force per recipe: `Mixmymeals_Diet::combine()` intersects rows (strictest wins); `Mixmymeals_Product::add_row_weight()` collects a union into `$info['excluded']`.

**Usage**: `Mixmymeals_Product::usage_map()` (one pass over all recipes, memoised) tallies pins; `get_usage()` / `count_usage()` read it; the box lists them via `Mixmymeals_Post_Meta::usage_list()` (shared with units, `mmm-usage__*`) in the detail panel. **Counts pins only**; the reference gets a sentence instead. No heading when nothing is pinned.

**Product comparison** (`[mmm_ingredient_products]` on the ingredient page): transposed — one column per product, eight figure rows plus a `Diet` row (in-force classification in the anchor column, each packet's *own* claim elsewhere, dash where silent; omitted when nothing is classified). One anchor column carries absolutes, the others differences (`format_nutrition_delta()`: U+2212, `±0`, dash unless **both** ends are stated). Anchor `pick_products_baseline()`: reference, else lowest energy (stable). Only the reference gets a muted chip ("Used for the nutrition figures"); differences carry **no colour** (more protein and more salt would share a green, reading as a verdict on a brand). Heading cells use `div`s (robust without CSS); source as "Quelle: …", `max-width: 22em`. Exempt from the card collapse via `:not(.mmm-nutri-table--products):not(.mmm-nutri-table--days)` in **both** of the theme's phone blocks; scrolls sideways with a sticky label column.

**Product impact** (`[mmm_meal_plan_product_impact]` per day and person, `[mmm_meal_product_impact]` per serving; REST `GET /product-impact?post_id=&eater=`): `Mixmymeals_Product::impact_groups( $weights, $current, $divisor )` does the arithmetic; `Mixmymeals_Meal_Plan::calc_product_impact()` passes the plan's days, `Mixmymeals_Recipe::calc_product_impact()` 1.0. One private renderer, differing only in the unit heading.
- Computed as a **difference**, `(product − current) × grams ÷ 100 ÷ divisor` — exact only because the day count does not depend on nutrition (see *Per day and person*). Anything making the divisor depend on a figure a product moves breaks this silently.
- Weights from `get_ingredient_portion_weights()`: one portion per meal like `calc_nutrition()`, **not** like the shopping list (no pack rounding); every step row's grams count, the meal once. A `products` breakdown (grams per product) anchors on the one packet in use, or on `blend_nutrition()` when several.
- Anchor: the reference, else a `Typical values` column of the ingredient's own figures. All seven figures are compared; ranked by largest spread relative to `reference_intake()`. A figure a product does not state takes **no part** (dash, excluded from ranking and from the shown/not-shown test) — the settled position after flip-flopping; `get_nutrition()`'s fallback is untouched.
- An ingredient the recipe has settled (pinned, `open` grams zero) drops out; a group left with one usable packet after exclusions drops out (`impact_groups()` filters before counting).
- The in-force product is marked `*` with `mmm-product-impact__ref-mark`; heading, intro and legend are the child theme's (legend only when the class occurs).

**REST/MCP**: `POST /products`, `GET|POST|DELETE /products/{id}`, `GET /ingredients/{id}/products`, `/product-impact`. Serialized ingredients carry `reference_product`, `reference_product_label`, `product_count`, `own_nutrition`, `own_diet_type`, `own_price_per_100g`, `own_cooking_factor` beside the resolved values. Product `nutrition` is an object; only supplied keys change, `null` deletes; `is_reference` sets/clears the pointer; `price` (null clears) and read-only `price_per_100g`. `/audit`: `ingredients_products_without_reference`, `products_without_source`. MCP: `mmm_get_ingredient_products`, `mmm_get_product`, `mmm_create_product`, `mmm_update_product`, `mmm_delete_product`, `mmm_product_impact`, `mmm_audit`.

**Merging ingredients moves the source's products to the target** (not its reference).

### Bulk data endpoints

- `POST /bulk-update` (MCP `mmm_bulk_update`): nutrition, price, diet, `cooking_factor` for up to 100 ingredients/products. Answers per item with `changes` as `[old, new]` for fields that moved. On an ingredient it writes the **own** layer; keys the reference product states itself are listed under `shadowed` (written, but the page will not change). On a product `price_per_100g` becomes a package price and needs a package weight (`no_package_weight`). Unknown keys (`additionalProperties: false`), unknown diets and an out-of-range cooking factor reject the whole request.
- `GET /missing` (MCP `mmm_list_missing`): id, title, `own_value`, `inherited_value`, `reference_product`, paginated. `mode=effective` (after the reference) or `own`; `zero_is_missing` defaults true; `field=cooking_factor` supported.
- `POST /keeping/bulk` — see *Recipes*.
- Covered by `tools/bulk-update-test.php` via `rest_get_server()->dispatch()`.

### Units

`mmm_ingr_unit` (`Mixmymeals_Ingredient_Amount`), child of an ingredient. Meta `_mmm_ingr_unit_weight` (g per unit), `_mmm_unit_divisor`: 1 = whole only, 2 = halves, 4 = quarters, absent = any amount. `get_divisor()` reads it (falling back to the old `_mmm_is_indivisible` for units `migrate_indivisible()` — `admin_init`, `mixmymeals_unit_divisor_version` — has not reached); `get_step_divisor()` is the same value for calculations. `is_indivisible()` / `set_indivisible()` survive as derived wrappers for REST/MCP (`set_indivisible( false )` clears the restriction entirely). REST: `divisor`, read-only `step_divisor`, deprecated `is_indivisible`; MCP `mmm_create_ingr_unit` / `mmm_update_ingr_unit`. `_mmm_is_sale_unit` is dead; orphan rows are inert and left alone — `unit_is_counted()` covers its purpose ("1,5× Dose").

**Rounding goes opposite ways on purpose**: `get_ingredients_scaled()` rounds to nearest (scaling down must not add a tin); `calc_shopping_list()` rounds **up**, once over the aggregated requirement (two half tins = one tin). Arithmetic on the integer multiple, `round( $amount * $d ) / $d`.

**Deleting a used unit corrupts recipes silently**: the amount stays, the unit goes, and it reads as grams. `GET /units/{id}/usage` reports recipes, lines, default-unit status, `can_delete`; `DELETE /units/{id}` refuses with 409 unless `force=true`; `POST /units/bulk-delete` judges each id, supports `dry_run` and `force` — read `affected_recipes` before forcing; feed it `/audit`'s `units_unused`. All read `get_usage()` → `usage_map()` (one pass, memoised; `get_amount_usage_count()` derives from it). Drafts count as usage (`post_status => any`).

Known gap: the units table on the ingredient creates rows without a one-shot token, so a second block-editor Save can add a unit twice.

### Amounts and weights

`Mixmymeals_Recipe::get_ingredients_scaled( $scale )` is the canonical ingredient list: amount × scale, snapped to the unit's step divisor (min one step), × unit weight = `weight_g`; no unit = grams. It returns **one row per step**. Rows pass through `implement_row()` (see *Diets*), as do those of `get_ingredients_normalized()`.

Amounts in machine-read output (schema, Bring!) go through `Mixmymeals_Ingredient::format_amount()` — no thousands separator, since "1.000 g" parses as one gram. Human-facing: `Mixmymeals_Meal_Plan::build_needed_amount_string()` / `build_short_amount_string()`.

### Diets

**The taxonomy `mmm_diet` is the registry, and it is hierarchical: the tree is the rule.** A child implies every ancestor (Vegan under Vegetarian under Pescetarian). `Mixmymeals_Diet::types()` = all terms (`terms_as_types()`) + filter `mixmymeals_diet_types`, then `normalise()` closes `implies` transitively and lifts `rank` above what it implies. Memoised; `reset_types()` on term/term-meta hooks. Term meta (empty = inherit, deletes the row; fields via `register_term_admin()`): `_mmm_diet_axis` (which question it answers; inherited from the nearest ancestor, a term above a diet of stated axis takes that axis, else its own slug), `_mmm_diet_rank` (empty = depth), `_mmm_diet_schema`.

**No diet is defined in code.** The four former built-ins (vegan, vegetarian, gluten_free, lactose_free) live on as aliases by term ID (`mixmymeals_diet_former_terms`, `former_terms()`): `resolve()` maps an old key to the term's current slug, `former_key()` the reverse (the theme's archive headings use it). Everything receiving a slug from outside goes through `resolve()` — legacy-meta fallback, `set_for_post()`, both `set_diet_type()`, REST `diet`/`diet_type` (enum `axis_slugs( 'diet' )` + aliases), `archive_view_slugs()`. So slugs may be changed freely. (`migrate_former_builtins()`, `init` 15, `mixmymeals_diet_terms_version`, did the one-off move.)

An invented term implies only what its place in the tree says and, by default, answers its own question — placing a term under "Vegan" is the statement that it implies vegan. Changing the tree reindexes all recipes (`edited_term`, term-meta hooks → `index_on_term_deleted()` path). New diets get `/rezepte/<slug>/` views — **check recipe slugs first**.

**Every label goes through `Diet::label()`.** A local `array( 'vegan' => … )` map is the bug to watch for.

**Reading and writing**: `Mixmymeals_Diet::for_post()` (memoised; falls back to legacy `_mmm_diet_type` / `_mmm_product_diet_type` when a post has no terms). `set_for_post()` updates the cache and **deletes the legacy meta** on every write, or a removed classification would come back from it; `$keep_legacy = true` is for `POST /diets/migrate` only. `own_diet_type` in the serialized ingredient reads the taxonomy. A product's classification overrides the ingredient's in **both** directions (a brand property: pasta is vegan until a maker adds egg); `set_diet_type()` stores only known values. Note: setting a reference product can change what a recipe is publicly labelled.

**Capable vs. settled.** `Mixmymeals_Recipe::get_diets()` / `get_diet_suitabilities()` compute what a recipe *could* be cooked as (every required ingredient has a qualifying product). A **meal** stores what it *is*: `_mmm_diet` (`Mixmymeals_Meal::META_DIET`, `get_diet()` / `set_diet()`). Choices `Mixmymeals_Recipe::get_diet_choices()` (`diet` axis only): first the recipe as written — its strongest diet or `Mixmymeals_Diet::NONE` (`_none`, labelled `Diet::choice_label()` → `none_label()`: option `mixmymeals_term_diet_none` (Language → Terms), else "Original recipe"/„Originalrezept") — then every stronger diet, weakest first; fewer than two = no choice = `[]`. **Where there is a choice the meal is always settled** (no "as in the recipe"): `get_diet()` returns the stored choice if valid, else the first; `set_diet()` always stores something and returns whether it got what it asked. Same rule in `Mixmymeals_Cart::clean_diet()` and `Mixmymeals_Meal_Plan::row_recipe()`. `NONE` is not a term (`resolve()` → '').
- `for_diet( $slug )` returns the recipe as cooked for that diet; `$fixed` is set for `NONE` and any `diet`-axis diet, and a fixed instance drops `diet`-axis suitabilities not in force (no "Vegan-capable" chip on a meal settled as written). Other axes untouched.
- **Read a meal's dish via `Mixmymeals_Meal::get_recipe()`**, in plan calculations via `row_recipe( $row )` — never `new Mixmymeals_Recipe( get_recipe_id() )`, which puts the beef on the shopping list of a vegan meal.
- **Row rule** `Mixmymeals_Ingredient::diet_product( $ingredient, $pinned, $diet, $excluded )` (`product_for_diet()` = id only). A diet is a **filter, not a choice of brand**: a row holding the diet as written is kept; otherwise products holding it, minus exclusions, are candidates — one is taken; several leave the row **open** (`open => true`) with the figures of `worst_product()`; none, and the ingredient's own classification holding it gives `PIN_OWN` (-1, "no packet", never stored). The reference has no precedence. Changed rows get `implemented_for` and `product_open`; an optional row that cannot hold the diet is dropped.
- `worst_product()` picks **one whole product** (never a per-figure composite, which no comparison column matches): highest sum of `limit` figures as shares of `reference_intake()`, scored on `get_nutrition_with_product()`, ties to lower ID.
- **An open row names no packet where it could read as a recommendation**: shopping list and meal ingredient list print the diet only ("Joghurt (Vegan)", `row_product_markup()`, `span.mmm-ingredient-product--open`). The packet is named only in the footnote under meal/plan nutrition tables (`get_open_products()` → `Mixmymeals_Nutrition_Table::open_note()`, kind `open`, mark on the label heading). In the product comparison an open row stays a question (`add_row_weight( …, $open_diet )`).
- **Shopping list splits only what a diet decided**: rows with `implemented_for` group by ingredient + effective product (open rows by ingredient + diet), others by ingredient. Lines carry `ingredient_id` and `diet`; keys are the plain ID for a single line, `"12|34"` / `"12|vegan"` when split. **Read `ingredient_id`, not the key.** Wording: `shopping_item_diet_text()`, `shopping_item_product_text()`.
- Chosen in the plan's Meals box (`meal_diet_select()`), the meal screen (`mmm_meal_diet`), the planner (`.mmm-plan-item-diet`) and the add-to-planner dialog (`render_add_to_planner_button()`, `data-diets`, `.mmm-planner-dialog-diet`, posts `diet` to `mmm_add_to_cart`); REST `diet` on `/meals`, `/meals/{id}`, `/meal-plans/build`, `/meals/bulk` (invalid = first + warning), serialized with `diet_applied`, `diet_options`.

### Meals

Child of a plan. Meta: `recipe_id`, `servings`, `mmm_meal_type` (breakfast/lunch/dinner/snack/other), `_mmm_day` (1–7), `_mmm_diet`, `_mmm_eaters`, `_mmm_eater_portions`.

**Short text** in `post_excerpt` (`get_text()` / `set_text()`): why this planning ("cook double, freeze half"). **Never falls back to the recipe's excerpt.** `supports` omits `excerpt` on purpose (core's box would be a second control for one value). Edited in Meal Details (`mmm_meal_text`), REST `text` on `/meals`, `/meals/{id}`, `meals[]` of `/meal-plans/build` and `/meals/bulk`; MCP `mmm_update_meal`, `mmm_build_meal_plan`, `mmm_add_meals`. **Visitors cannot set one**: the cart's per-item `note` transfer stays commented out (`tools/meal-text-test.php` checks the comment-stripped source). `migrate_notes()` (`mixmymeals_meal_text_version`) moved the old `note` meta, leaving it as a way back. Rendered as `.mmm-recipe-card__note` in `[mmm_meals_in_a_single_meal_plan]` and `.mmm-plan-hero__lead` in `[mmm_meal_hero]`.

**Credit**: a meal has no author, image credit or disclosure of its own — every crediting feature (author box, `[mmm_image_credit]`, disclosure, keeping chip, the theme's `mmm_inject_author_social_links()`) resolves through `Mixmymeals_Meal::resolve_credit_source()` (meal → recipe).

**Edited in the plan's Meals box** (`render_meal_plan_overview_meta_box()` / `save_plan_meals()` / `apply_meal_row()`, own nonce and hook), a row per meal:
- Day select offers the plan's own days in its order (`get_day_range()`, so 6, 7, 1, 2), plus a meal's current day if outside; wording from `weekday_name()`.
- An empty recipe keeps the stored one; an unknown meal type is discarded (`set_type()` would store it). Delete is `wp_delete_post( …, true )`.
- The new row carries a one-shot token (`mmm_plan_meals[new][token]`, day-long transient).
- **AJAX without saving the plan**: `wp_ajax_mmm_plan_meal_save` / `wp_ajax_mmm_plan_meal_delete` (nonce `mmm_plan_meals_ajax`). The script posts the row's own fields under their rendered names → same `apply_meal_row()`; the answer is the row re-rendered by `meal_row_html()` (cells depend on each other: diets, `min_servings()`, surplus). The token is **not** spent on the AJAX path; the script clears the new row's recipe so a later post save does not re-add it. Both add/delete variants are rendered and CSS picks via `body.mmm-plan-meals-js`; handlers bind to `document`, because the block editor re-fetches the meta-box area after every save. Without the script the box works as before.

**No admin menu entry** (`show_in_menu => false`; also drops it from the admin bar); `show_ui` stays true because the box links to each meal's screen, and `highlight_meal_parent_menu()` keeps Meal Plans lit there. `public` stays true (plan cards link to meal pages; `publicly_queryable => false` would 404 them).

### Meal plans

Meta `start_day`, `end_day` (1 = Mon … 7 = Sun; may cross Sunday). `get_meals()` is memoised per plan, cleared by `register_meal_cache()` on `wp_after_insert_post` and delete/trash hooks.

**Plan number = post ID**, `number_label()` → `#4812` (no thousands separator). Planner-saved plans (`mmm_create_meal_plan_ajax_callback()`) are renamed by `apply_number_title()` to "Essensplan #4812" (published ones get the slug at once). Printed on **every** plan (also beside such a title — site owner's decision) by `plan_number_html()` as `p.mmm-plan-number`: in `[mmm_meal_plan_hero]` (`mmm-plan-hero__number` in `div.mmm-plan-hero__title-row`) and `[mmm_meal_plan_number post_id=""]` (`mmm-plan-number--card`, the theme's archive card head). Mails: `{{ plan_number }}` / `{{ plan_number_html }}` (`Mixmymeals_Subscriptions::plan_number_html()` via `mail_vars()`, inline styles from mail colours; survives `wp_kses_post()`). The theme's `welcome.html` (trigger `on_confirm`, subject `default_subject()`) sets it large via its placeholders `plan_number_hero_line_style` / `plan_number_hero_style`. Numbers have gaps (shared ID counter). `/meal-plans/build` and wp-admin keep given titles.

**Rows, not posts.** `get_meal_rows()` is what every plan calculation reads (`calc_nutrition()`, shopping list, portion weights, costs, day ratings, diets, lights, prep): from meal posts for a saved plan, else from rows. `from_meal_rows( $rows, $eaters )` builds a postless plan from planner/cart rows (span Mon–Sun unless the plan's ID is kept; days without meals count nowhere; keeps `meal_id`); `get_meals()` returns nothing for it (`post_parent = 0` would be every orphan meal). Rows carry `recipe`, `servings`, `min_servings`, `day`, `type`, `diet`, `eaters`, `portions`, `meal_id`.

**Per day and person: `calc_nutrition_per_day_person()`, one method.** Averages over the days that **carry meals** (counted by meals, never by energy — or filling in data could lower the average, and the product-impact difference would break). Returns the average, the totals and the day count (the mail hides its weekly block below two days). Plan overview, mail, PDF and product comparison call it. **Never recompute it locally.**

**Shopping list**: `calc_shopping_list()` scales each row by `servings / recipe servings` via `get_ingredients_scaled()`, aggregates `weight_g` per ingredient, rounds up to units once, groups by `mmm_ingredient_category`. Three renderings print it: the plan page (`render_shopping_list()`), the PDF and the mails (`Mixmymeals_Subscriptions::render_ingredient_list()`). `tools/plan-servings-test.php` holds a frozen copy of the 3.65.1 method and compares `serialize()`.

### Eaters

A plan carries an ordered list of people; each meal says who eats it and how much. **Servings stay a separate field** (portions cooked — "cook double", guests); everything that buys or costs (shopping list, packs, costs, Bring!, `with_servings_offset()`) reads only servings.

**Storage**: plan meta `_mmm_plan_eaters` (`EATERS_META`): list of `id` (`p1`, `p2` …, `is_eater_id()`, assigned by `sanitize_eaters()`, never reused), `name`, `profile`, `portions`; max `EATERS_MAX` (12). **No row = one adult `DEFAULT_EATER` who eats everything**; `set_eaters()` deletes the row for that list. Meal meta `_mmm_eaters` (`META_EATERS`): ids; **no row = everybody** (a list naming everybody is stored as no row, so people added later eat along). Validation on read and write: `clean_eaters( $value, $ids )` — unknown ids dropped, plan order, nobody → everybody.

**Portions** are two numbers (site owner's decision): the person's usual (`portions`, default `DEFAULT_PORTION` 1.0, max `PORTIONS_MAX` 10) and a meal's own figure in `_mmm_eater_portions` (`META_EATER_PORTIONS`, id → portions). `resolve_portions( $given, $eaters )` reads (meal's figure else usual); `clean_portion_overrides()` writes (only eaters of the meal, snapped, **a figure equal to the usual is not stored**). Not eating is unticking, never zero; `normalise_portion()` turns 0/negative/text/too large into "not stated". Step: option `mixmymeals_eater_portion_step` (quarters default, halves, whole, any; `portion_steps()`, `portion_step()`) snaps writes and steps inputs; reads leave stored values alone.

**Servings floor**: a meal never has fewer servings than its eaters eat. `min_servings( $eaters, $portions )` = portions summed, rounded up (1 without people). `Mixmymeals_Meal::get_servings()` floors on read (`get_stored_servings()` is raw), `set_servings()` on write, `set_eaters()` / `set_eater_portions()` raise (never lower) stored servings; rows carry `min_servings` and floored `servings`; planner items via `Mixmymeals_Cart::item_servings()`; `with_servings_offset()` stops at the floor (`servings_offset_bounds()`). New meals start at `default_servings()` (Meals box new row, REST when servings are omitted, the planner dialog via `.mmm-plan-builder[data-default-servings]`). Surplus: `servings_surplus()` (0 at the floor and always 0 without several people), worded by `servings_surplus_text()` ("1 Portion mehr als gegessen") in the planner (`span.mmm-plan-item-surplus[data-servings-surplus]`, from the `surplus` / `min_servings` keys of `mmm_update_cart_item`) and the Meals box (`span.mmm-plan-meals__surplus`); REST `min_servings`, `servings_surplus`. Not on public pages.

**`for_eater( $id )` is the only per-person calculation, and it computes nothing**: a `from_meal_rows()` plan (ID kept) of that person's meals with `portion` on each row; `calc_nutrition()` and `get_ingredient_portion_weights()` multiply by it — the only two places. A plan whose one person eats one portion of everything returns **itself** (tested against a frozen 3.69.0 copy). Unknown id → first person (`resolve_eater()`). Called on the plan itself, figures stay "a person who eats everything" (Printer, mail, Yoast description, `[mmm_nutrition_traffic_lights]`).

**Names are wp-admin only.** Public output names people by position (`eater_label()`, "Person 2"); `get_eaters()` carries no name; `get_eater_names()` is read only by the People box, the "Eats along" column and REST for clients that may edit (`serialize_eaters()`). The planner never asks for names (`Mixmymeals_Cart::get_eaters()` / `save_eaters()`, `strip_eater_names()`). The privacy text says so for planner, share and saved plan.

**Profiles**: `eater_profiles()` (filter `mixmymeals_eater_profiles`), only `adult` so far — **no other profile's figures are invented**; they are to come from the site owner. Renderers read `reference_intake()` without arguments; a person's figures are rendered inside `with_reference_profile( $profile, $callback )`.

**Editing**: wp-admin People box (`render_meal_plan_eaters_meta_box()` / `save_plan_eaters()`, `eater_portions_input()`, own nonce, `save_post_mmm_meal_plan` at **priority 9** so meals are checked against the new list, touches only posted rows, one-shot token) and the "Eats along" column (`meal_eaters_checkboxes()`, portions `[portions][pX]`; hidden `[eaters_shown]` tells "all unticked" (= everybody) from "column not shown"; `apply_meal_row()` writes eaters, portions, then default servings). Planner: `div.mmm-plan-eaters[data-plan-eaters]` (− count +, `data-eater-add`/`-remove`, usual portions `input.mmm-plan-eaters__portions[data-eater]`; AJAX `mmm_cart_eaters`, `op=add|remove|portions`, reload) and per item `fieldset.mmm-plan-item-eaters[data-field="eaters"]` with `input.mmm-plan-item-eater__input` / `__portions` (written via `mmm_update_cart_item`, `eaters=p1,p3`, `portions={"p2":1.5}`, `clean_item_eaters()`, `clean_item_portions()`). Cart people live in `EATERS_META_KEY` / transient `<cart key>_eaters`, cleared by `clear()`. REST `eaters` on `/meal-plans` (entries without `name`/`portions` keep stored ones, `merge_eater_names()`), `/meal-plans/build`, `meals[]`, `/meals`, `/meals/{id}`; `eater_portions`; MCP `EATERS_SCHEMA`, `MEAL_EATERS_SCHEMA`, `MEAL_PORTIONS_SCHEMA`. The add-to-planner dialog offers nothing (reading the cart would set the cookie on every recipe page).

**Display**: nothing changes while a plan has one person. With several, `render_eater_switch()` prints `nav.mmm-eater-switch` › `a|button.mmm-eater-switch__item[data-eater][.is-current]`. Plan page: overview `render_plan_nutrition_overview( $plan, $eater, $page_url )` in `div.mmm-meal-plan-nutrition[data-plan-nutrition][data-eater]`, links `?mmm_eater=p2` (`EATER_QUERY_VAR`, read only by `requested_eater()`, noindex), swapped by AJAX `mmm_plan_nutrition` together with `div.mmm-plan-eater-part[data-plan-eater-part="days|impact"]`. A person who eats nothing gets a hint. A plan of **one** person at other portions shows no switch but reads `for_eater()`.

**Not done**: Printer and mail still print the person who eats everything.

### Servings on the plan page (`?mmm_persons_to_add`)

**Servings are worded as servings, people as people** — `format_servings()` / the `servings` / `plan_servings` chips vs. `format_people()` (eaters only). The parameter keeps its old name because shared links carry it.

**One method applies the difference: `with_servings_offset( int $offset )`** — adds the offset to every row (floor per meal; additions stop at `SERVINGS_MEAL_MAX` 50 unless already above), returns `from_meal_rows()` with the plan's ID kept. Always a **difference, never a count or a factor** ("one more person" = one more serving per meal). Read through the same methods as a saved plan, so packs round up once over the new sum.

**One reader, one check**: `requested_servings_raw()` alone reads `$_GET['mmm_persons_to_add']` (untrimmed, so "+1" arriving as " 1" parses); `parse_servings_value( $raw )` alone validates (page, AJAX and `mmm_load_plan_into_cart` all hand it their raw value) (optional sign incl. U+2212, ≤ 3 digits, within `servings_offset_bounds()`, max `SERVINGS_OFFSET_MAX` 20; else null = plan as stored); `requested_servings_offset()` combines them. Addresses via `add_servings_arg()` / `servings_query_value()` (no "+", which a query string reads as space).

**Plan page**:
- Mixed meals: each day head shows `span.mmm-rail-day__servings[data-plan-servings-day]` ("2–4 Portionen", `servings_by_day()`, `format_servings()`); uniform: the hero chip says it once (`.mmm-chip--servings`, `meal_plan_stat_chips()`, `get_servings_range_string()`).
- The field, printed by `[mmm_shopping_list_for_a_single_meal_plan]` (`servings_control` default on, `cart_url`) from `render_servings_control()`, is one quiet line: "Für − 5 + Portionen" (uniform) or "Portionen: − +1 + bei jeder Mahlzeit" (mixed, `output.mmm-plan-servings__value`). − / + are links (`a.mmm-plan-servings__step--down|--up`, `span…is-disabled` at a bound). Beside it `a.mmm-plan-servings__planner-link` ("Einzelne Tage ändern? Im Planer anpassen") = the `load-into-cart` action posting `persons_to_add`. Wrapper `div.mmm-plan-shopping[data-plan-shopping]` › `.mmm-plan-servings` › `__line` + `__planner`, then `div.mmm-plan-shopping__list[data-plan-shopping-list]`.
- Other servings keep the stored number and append the change, `span.mmm-servings-delta` (`servings_delta_html()`, always present, `hidden` while empty) — the **actual** change per meal/day/plan (`servings_changes( $offset )`, ranges "−5 … −2" via `format_servings_delta()`), not the offset.
- With JS, AJAX `mmm_plan_servings` (nonce `mmm_frontend_nonce`, guests, published only) swaps field and list, `history.replaceState()`s the address, and updates Bring! links, deltas, day heads and card `href`s (`data-meal-id`) from one answer. − / + count from the field's `data-offset`; amounts are never computed in the browser.

**Meal page** (`[mmm_meal_ingredients]`, `servings_control`, `render_meal_servings_control()`, `mmm-plan-servings--meal`): always "Für − 3 + Portionen" with the meal's count, writing the **same** `?mmm_persons_to_add` (a difference to the whole plan). Each step is the offset that actually yields the next count (`Mixmymeals_Meal::servings_with_offset()`, also behind `get_shown_servings()`), offered only where valid and effective. Published plans only. Links reload (everything on the page is server-rendered); the plan page's script leaves it alone. The back link carries the parameter (`plan_url_with_servings()`).

**Follows the number**: shopping list, Printer's PDF (`requested_servings_offset()` + `with_servings_offset()`), `bring_deeplink( $plan_id, $offset )` / `render_bring_page()`, meal links and the meal page (`get_shown_servings()` asks the plan). **Does not**: per day and person, per 100 g, lights, day rating (tested). Nothing is written. Any value of the parameter makes the page `noindex, follow` (`has_servings_param()` → `is_noindex_view()`).

### Preparation on the plan

`Mixmymeals_Meal_Plan::calc_prep_tasks()` counts back from each dated meal: days keep the time of day, hours count from the meal type's assumed hour (`Mixmymeals_Recipe::meal_type_hours()`, filter `mixmymeals_meal_type_hours`: 8/12/15/18). Each task has `day`. **`calc_prep_by_day()` is the one grouping** (weekday → tasks; days before the plan first, then the plan's in order, each by due hour) — plan page, planner and Printer read it. **A note stands on the weekday it is due, never in a section of its own** (a "Vor Planbeginn" section and a box above the week were rejected). The planner is a week of weekdays; on the plan page a weekday the plan lacks is added before the week (`mmm-rail-day--prep-only`). Plan page: heading "Vorbereitungen" built like the meal-type heads (`.mmm-rail-day__sub--prep` › `.mmm-rail-day__meal`), ahead of the meal types. `render_prep_notes()` → `ul.mmm-prep-notes` › `li.mmm-prep-note` › `span.__label` ("Vorarbeiten für <meal>:") + `a.__task` (only the step title links, to the meal's step anchor; in the planner to the recipe). Quiet styling. Planner boxes `.mmm-plan-day-prep[data-day-prep="1-7"]`, refreshed from the `prep` key of `mmm_planner_nutrition`. Undated meals give nothing.

### Planner (cart)

`Mixmymeals_Cart` stores items (`recipe_id`, servings, type, day, diet, eaters, portions) in user meta `mixmymeals_cart` (logged in) or a transient keyed by the UUID in cookie `mixmymeals_cart_uuid` (guests). The guest cart merges on login. The cookie's value is used as a key only when it is a UUID.

**What a visitor may put in is decided in one place each**, for `mmm_add_to_cart`, `mmm_update_cart_item`, the shared copy and `mmm_create_meal_plan` (which reads its meals from the POST, not from the cart): `Mixmymeals_Cart::recipe_is_plannable()` (published only — a draft planned by its ID would be printed in full on a saved plan — and not `is_locked()`), `max_items()` (the only reader of `mixmymeals_max_items_in_cart`; empty/0 = `DEFAULT_MAX_ITEMS` 30, capped at `MAX_ITEMS_CEILING` 100), `clean_servings()` (1 … `SERVINGS_MEAL_MAX`, or what the item already has), `clean_type()`, `clean_day()`. A new visitor-facing write path calls these rather than checking again. Sign-ups are counted once per address for every way to sign up (`Mixmymeals_Subscriptions::signup_rate_limited()` / `count_signup()`), shared copies by `share_within_rate_limit()` (`SHARE_RATE_LIMIT` 20/h, filter `mixmymeals_share_rate_limit_per_hour`).

**Live figures from the same calculation.** `[mmm_planner_nutrition]` (server-rendered first) and AJAX `mmm_planner_nutrition` (nonce `mmm_frontend_nonce`, guests too) answer with:
- `html`: tiles "Ø …" per day and person (`render_nutrition_tiles()`, shared with the plan page), the table in a `details` (the plan page prints the same table via `[mmm_nutrition_overview_for_a_single_meal_plan]`);
- `table`: **byte for byte** the plan page's table for the same meals saved (both `render_plan_nutrition_table()`; per person `render_person_nutrition_table()`);
- `days`: one row per weekday (`render_planner_day_nutrition()`, `.mmm-plan-day-nutrition[data-day-nutrition]` titled "Tagessumme:", inside `.mmm-plan-day-box` with the day list);
- `prep`, `eater` (with several people: `html`/`table`/`days` are that person's);
- `shopping` when asked (`shopping=1` + flags; else `null`): `[mmm_planner_shopping_list]` (`show_details`, `sort_by_category`) prints `section.mmm-planner-shopping[data-planner-shopping]` with `render_shopping_list()` on `from_meal_rows()` — byte for byte the plan page's list. Hidden while empty; opened lines stay open across refreshes (matched by ingredient name + diet).
Tiles carry bars from `nutrition_bar( $key, $percent )` (for a `limit` the room left, stated as `.mmm-planner-nutrition__room` "noch 30,0 g frei", full and `--over` past it; otherwise share capped at 100) with `nutrition_bar_note()` (`p.mmm-nutri-note--bars`). The Printer still has its own copy of the bar rule. `frontend.js` refreshes (debounced, after the cart write returns) on add, remove, clear, `mmmMoveItemToDay()` and servings/type/diet/eater changes — all of which are **written to the cart as they change**.

**The day of an item lives in the DOM**: `[mmm_meal_plan_builder]` reads it from the containing `.mmm-plan-day-list[data-day-list]` on save, so `mmmMoveItemToDay()` both moves and saves; drag-and-drop and the day select (needed because HTML5 drag-and-drop does not fire on touch; the handle is hidden on phones) call it. `render_plan_item()` builds every item.

**Footer**: Save Plan — Share planner — Clear Plan, three shortcodes (`[mmm_create_meal_plan_from_cart]`, `[mmm_share_planner]`, `[mmm_clear_cart]`) the theme wraps in `div.mmm-plan-actions`. "Save Plan" is the label; action and classes keep the old "create" names.

**Save bar**: `mmmEnsureSaveBar()` builds `div.mmm-save-bar` › `a.mmm-save-bar__link--save` (+ `--clear` where `[mmm_clear_cart]` exists) on planner pages, shown by an `IntersectionObserver` while the closest `[data-plan-save]` (theme: `section.mmm-plan-save#mmm-plan-save`) or the button's parent is out of view **and** there is an item; toggles `body.mmm-save-bar-visible`. Clearing always asks (`mmmOpenConfirmDialog()`, `clear_cart_dialog`). It must be called after the two `let`s it reads — an earlier call throws and kills the whole script.

**Cart bar** (bottom): built from the header's own planner link (`[mmm_cart_badge]`), no setting; hidden via `body.mmm-cart-empty`.

**Adding a recipe** announces via `mmmAnnounce()` into `#mmm-status` (`role="status"`) plus a toast, wording `planner_dialog.added` in three variants (no day/type gaps); the count rides on the `mmm_add_to_cart` answer.

**Sharing the planner**: `mmm_share_cart` stores a **copy** under its own uuid4 (`mmm_cart_share_<token>`, 30 days, `SHARE_EXPIRATION`); `mmm_load_shared_cart` copies it into the opener's cart, re-checking recipes. Never put the cart's own UUID in a link (it is the live key). Named in the privacy text. `.mmm-plan-share[hidden]` needs an explicit `display: none` (the theme's `display: flex` beats the UA `[hidden]` rule).

**Templates in the empty planner**: three random finished plans (`orderby => rand`, `Mixmymeals_Shortcodes::planner_template_args()`) via the `load-into-cart` control, only while empty. Option `mixmymeals_planner_templates_require_category` (off by default) limits them to plans with a `mmm_meal_plan_category` term — the **same** definition `Mixmymeals_Cleanup::delete_uncategorized_meal_plans()` deletes by — added **in the query** (`tax_query` `EXISTS`), not filtered afterwards.

**Subscribe while saving** (Meal Plans tab): `mixmymeals_plan_subscribe_mode` `off` (default) / `optional` / `required` (legacy `hint`/`choice` read as `optional`); `_notice` (own text, links allowed, empty = `consent_text()`); `_notice_checkbox` (`'on'`/`''`; default only via the `get_option()` fallback). Getters `save_offer_mode()`, `save_offer_notice()`, `save_offer_notice_is_checkbox()`. `Mixmymeals_Shortcodes::plan_subscribe_offer_mode()` is the one decision (folds in `save_offer_mode_effective()` and the guest-draft case) for renderer, handler and the theme's heading. Fields (first name `subscribe[name]` → `_mmm_sub_name`, never required; e-mail) are always shown while on — no toggle in front. `.mmm-plan-subscribe` takes `flex-basis: 100%`; width cap on `__fields` and the notice. `required` is enforced server-side before anything is created or counted (`subscribe_email_required`, `subscribe_email_invalid`, `subscribe_consent_required`); the button stays dimmed client-side (`mmmPlanSubscribeReady()`). Double opt-in either way via `Mixmymeals_Subscriptions::create_pending()` (shared with `[mmm_meal_plan_subscribe]`). `required` is the site owner's call, made against a stated GDPR Art. 7(4) objection; the setting's description says so and the code does not second-guess it.

### Planner tools

`Mixmymeals_Planner_Tools` (`includes/lib/class-mixmymeals-planner-tools.php`, static, `register()` from `mixmymeals.php`): the ways into the planner that do not start from a recipe page. All three AJAX actions are open to guests, check nonce `mmm_frontend_nonce` and work on the visitor's cart; whatever they put in or show goes through `Mixmymeals_Cart::recipe_is_plannable()`.

- **`mmm_suggest_plan`** (`suggest_plan_ajax()`; `people` 1–12, `days` 1–7, `diets[]`): **replaces** the planner — `clear()`, then the people, then the recipes. One recipe per day as `dinner`, from today (`wp_date( 'N' )`) on, `servings` = people. **A suggestion sets the planner's eaters**: `save_eaters()` with as many default adults as people asked, *before* the recipes are added (so the items are read against that list); one person is no list. A failure (`no_recipes`, HTTP 404) leaves the planner untouched — nothing is cleared before there is something to put in.
  - Candidates: `candidate_ids( $diets, $main_only )` — published, through `exclude_unlisted()`, main dishes first (recipe categories from filter `mixmymeals_suggest_categories`, default `hauptgerichte`; unknown slugs ignored, none left = every recipe), then the rest tops up. Shuffled; at most `SUGGEST_CHECK_MAX` (60) are checked per request.
  - Exclusions: `exclusions()` maps the four questions (meat and fish, anything animal, gluten, lactose) to the **former built-in keys** via `Mixmymeals_Diet::resolve()` — no diet slug in code; a question whose diet the site lacks is not asked. `clean_diets()` resolves what arrives (slug or former key), drops unknowns and any diet another asked one implies (the tree, not a special case for vegan).
  - The index says *capable*; `planned_diet()` settles each meal on `Diet::strongest( $diets, 'diet' )` through `Mixmymeals_Cart::clean_diet()` and keeps the recipe only if `row_recipe()` of that row holds **every** asked diet. Without this a vegetarian-capable chili was planned as written, with the beef on the shopping list; diets of other axes (gluten) cannot be chosen per meal and must hold as cooked.
- **`mmm_recipe_picker`** (`recipe_picker_ajax()`, `term`): with a term (≤ `PICKER_TERM_MAX` 100 characters) `Mixmymeals_Search::ids( 'recipe', … )`; without, the newest through `exclude_unlisted()`. At most `PICKER_LIMIT` (24) rows of `recipe_row()`: `id`, `title`, `url`, `thumb`, `servings`, `time`.
- **`mmm_mini_plan`** (`mini_plan()` / `mini_plan_ajax()`): seven days from today as a **list** (`day`, `label`, `today`, `items`; an object would lose the order) plus `unassigned` and `count`. Data only; `frontend.js` draws it into every `[data-mini-plan]` (`h2.mmm-mini-plan__title`, `ol.__days` › `li.__day` (`.is-today`, `.is-free`, `.is-unassigned`) › `__label`, `__items` › `a.__item`; `__empty`, `a.__open`, `.is-empty` on the box) and redraws on `mmm:plan-changed`.
- **`[mmm_plan_start]`** (`people`, `days` = the defaults shown): `form.mmm-plan-start[data-plan-start]` › `.mmm-plan-start__field` (`--wide`; people: `__stepper`, `button.__step[data-step]`, `input.__people`; days 3/5/7 and the exclusions `diets[]` as `label.__chip`), `__actions` › `button.__submit` + `__note`, `div.mmm-msg.__msg`. A filled planner is asked about first (`planner_dialog.start`); off the planner page the answer leads to the planner (the header's `.mmm-cart-badge` link, else the form's `data-planner-url`).

**Planning asks only for the day.** `.mmm-recipe-actions__item--cart` opens the day sheet (`mmmOpenDaySheet()`): `div.mmm-day-sheet-backdrop` (`.is-open`) › `div.mmm-day-sheet[role=dialog]` › `__recipe`, `__title`, `__days` of `button.mmm-day-chip[data-day]` (`--today`, `__short`, `__today`), `__msg`, `__foot` › `__noday`, `__more`. A chip posts `mmm_add_to_cart` with the planner's `data-default-servings`, else the recipe's; `__more` opens the former dialog (`mmmOpenPlannerDialog()`) for servings, type and diet. The button gets `.is-planned`. Wording `planner_dialog.sheet`.

**Recipe per day**: `button.mmm-plan-day-add[data-day][data-day-label]` (`__label`) on each day of `[mmm_meal_plan_builder]` opens `div.mmm-picker-backdrop` › `div.mmm-day-sheet.mmm-picker` (`__head`, `__close`, `input.__search` — debounced 300 ms, two characters or none — `__hint`, `ul.__list` › `button.__item[data-recipe-id]` with `__thumb` (`--none`), `__text`, `__name`, `__meta`, `__plus`; `li.__empty`, `__msg`); `body.mmm-sheet-open` while open. Wording `planner_dialog.picker`.

**Planner cards** (`render_plan_item()`): picture, `.mmm-plan-item-title`, `div.mmm-plan-item-quick` › `label.__servings` (the servings input and surplus moved here, `__unit`); day, type, diet and eaters behind `details.mmm-plan-item-more[data-keep]` › `summary.__toggle` ("Adjust"). **One start day**: `label.mmm-plan-startday` › `__label`, `select.__select[data-plan-startday]` (0 = Monday) checks one of the seven `input[name=mmm-plan-startday]` radios, which stay in the markup because Save Plan reads the checked one; remembered per tab in `sessionStorage` `mmm_startday` (a suggestion sets today).

**Without reloading**: adding (sheet, picker, dialog), people and loading a plan call `mmmSoftReloadPlanner()` — GET the same page, swap `.mmm-plan-builder`, `[data-plan-eaters]`, `.mmm-planer-suggestions` (the theme's), restore open `details[data-keep]`, then badge, nutrition, save bar and the jQuery event `mmm:planner-refreshed`; anything unexpected reloads as before. A change to the plan is announced as `mmm:plan-changed`.

**Nutrition fold**: with at least one day of figures `[mmm_planner_nutrition]` wraps the tiles in `details.mmm-planner-nutrition__fold` › `summary.__fold-summary` (the heading plus `span.__fold-value`, the day's average energy via `format_nutrition_value()`), closed by default; the AJAX refresh keeps it open. Without figures the hint stands as before, unfolded.

**Shopping list ticks** (`frontend.js` only): inside `[data-shopping-check]` each `.mmm-shopping-item` gets `button.mmm-shop-check[role=checkbox]` and `button.mmm-shop-have`, state `.is-done` / `.is-have`, a `div.mmm-shop-bar` (`__text`, `__track`, `__fill`, `__reset`) before the list, `.is-complete` on the scope. Kept in `localStorage` `mmm_shopping_marks_v1`, keyed by ingredient name + diet — never sent, needs no account. Wording `mixmymeals.shopping_check`.

**Saved from the planner = unlisted**: `mmm_create_meal_plan` sets `Mixmymeals_Post_Types::set_unlisted()` on a visitor's plan (filter `mixmymeals_unlist_planner_plans` ( $unlist, $plan_id ), default: not for `edit_others_posts`). It keeps its address; an editor lists it on its edit screen.

Test: `tools/planner-tools-test.php`.

### Plugin vs. theme

**The plugin answers what and computes; the theme (`mixmymeals-astra-child`; from 4.0.0 `mixmymeals-hello-child`, which styles the planner of *Planner tools*) decides how it looks and where it stands.** Shortcodes are components: data, markup with `mmm-*` classes, the plugin's wording for what a component *is*. Page structure (sections, order, headings above a shortcode, intros, legends) and every colour, radius, shadow and font size are the theme's.

- `assets/css/frontend.css` is structure only — display, position, layout, `[hidden]`, z-index. Components that must be opaque use `Canvas` / `CanvasText`. It must not read theme custom properties (`--mmm-primary`, `--ast-*`). Exception: the disclosure label (see *Disclosure*).
- Site search is `Mixmymeals_Search` (`groups()`, `ids( $group, $term )`, `archive_link()`): title, then substance (recipes via the ingredient index, plans via their meals), then full text, each through `exclude_unlisted()`. That merges into an **existing** `meta_query`, so add clauses before calling it (`base_args()`). The theme's `includes/search.php` only names the groups.
- The front-page teaser reads `Mixmymeals_Meal_Plan::latest_plan_spotlight()` (chips from `Mixmymeals_Shortcodes::spotlight_meta_chips()`); the theme's `[mmm_home_masthead]` builds the section.
- Mail colours are roles: `Mixmymeals_Subscriptions::MAIL_COLORS` via `mail_color( $role )` after filter `mixmymeals_mail_colors` (non-hex falls back). No brand colour in this plugin; the theme's `mmm_email_colors()` is the palette.
- Headings inside planner boxes: `Mixmymeals_Shortcodes::planner_heading( $key, $class )`, filter `mixmymeals_planner_heading` (empty = none) — a filter because the boxes re-render over AJAX.
- Lists the plugin owns are read from it (e.g. the theme loops `Mixmymeals_Recipe::prep_time_steps()`, filter `mixmymeals_prep_time_steps`).
- The planner's entry points are the plugin's components, their places the theme's: it prints `[mmm_plan_start]` where it asks the question, puts an empty `[data-mini-plan]` where the week at a glance stands (the plugin's script fills it) and `[data-shopping-check]` around a shopping list that may be ticked off. Sheets and the picker are built by `frontend.js`; every visual decision is the theme's. Which recipe categories count as main dishes is site content (`mixmymeals_suggest_categories`), not a constant.

### Frontend

All shortcodes and frontend AJAX handlers live in `Mixmymeals_Shortcodes`, prefixed `mmm_`. Frontend JS is localized as `mixmymeals` (ajaxurl, nonce); admin JS as `mixmymealsAdmin`, `mixmymealsRecipes` (recipe list) and `MixMyMealsAmounts` (unit CRUD nonces).

**Plan actions** (`mixmymeals_meal_plan_actions`, rendered by `meal_plan_action_items()` into `[mmm_meal_plan_actions]` and the hero): host's own `share` (first), `mail`, `bring`, `load-into-cart`, `subscribe`; Printer adds `print` first. **Share** is a link with `data-share`: `navigator.share` or clipboard + `mmmAnnounce()` ("Link kopiert."), `servings => true`. Recipes have no share button (site owner's decision).

**Embeds**: `[mmm_recipe_embed id=""]`, `[mmm_meal_plan_embed id=""]` (`id` = ID or slug, `embed_target()`; published, not password-protected, unlisted allowed). A miss prints nothing for visitors and `p.mmm-embed--missing` for editors (`embed_missing()`). Chips come from the heroes' own `recipe_stat_chips()` / `meal_plan_stat_chips()`, so figures and the disclosure chip cannot differ from the page. Pictures follow the browse-card rule; plans list dishes once (`render_embed_dishes()`); `render_embed_card()`. Markup `aside.mmm-embed.mmm-embed--{recipe|meal-plan}[.mmm-embed--noimg]` › `a.mmm-embed__media`, `.mmm-embed__body` › `__kicker`, `__title`, `__rating`, `__meta`, `__lead`, `ul.__dishes`, `__actions`. No schema.org (would duplicate the recipe page's).

**Plan cards**: `render_meal_plan_browse_cards()` shows up to three pictures from `get_meal_plan_card_images()` (plan's featured image, then recipes by priority, each once) in `.mmm-recipe-row__mosaic`, falling back to `get_meal_plan_card_thumbnail()` below two. The tiles have their own wrapper and are placed by `nth-of-type` (the disclosure pill and the optimiser's `<noscript>` share the media box); tiles need `min-height: 0`.

**Chip wording**: every chip text is an option. Registry `Mixmymeals_Shortcodes::chip_texts()` (key → `label`, translated `default`, `tokens`, `required`, `group`, `note`; filter `mixmymeals_chip_texts`), rendered by `chip_text( $key, $tokens )` (plain text; caller escapes), options `mixmymeals_chip_text_<key>` (`CHIP_TEXT_OPTION_PREFIX`). Named placeholders (`{count}`), not positional. A pattern missing a `required` token is stored empty by `sanitize_chip_text()` and the default applies. `{unit}` (from `_n()`) is never required — plurals stay in the translation. Settings via `Mixmymeals_Settings::chip_text_fields()` (a closure per field carries the key) and the `chip_text` case in `display_field()`. Chip text never decides *whether* a disclosure is shown.

### Findability: facets and the recipe index

The archive narrows by **diet**, **preparation time** and **ingredients on hand**.

**The index answers queries; it never states anything.** `Mixmymeals_Recipe::META_DIET_INDEX` (one row per capable diet, expanded) and `META_INGREDIENT_INDEX` (one row per ingredient ID), recomputed by `recount_indexes()`, written only on difference. Deliberately **not** `mmm_diet` terms on recipes (those would mean "somebody said so" and `for_post()` would answer from them). Chips, `get_schema()` and REST keep computing via `get_diets()` — a stale index can hide a recipe from a list but never put "vegan" on a page (`tools/diet-test.php` asserts this with a forged row).

Kept current by `register_recipe_index()`: recipe (`wp_after_insert_post`, `_rmm_steps`, `_mmm_preparation_time`), ingredient/product side (`set_object_terms` on `mmm_diet`, legacy diet metas, `Mixmymeals_Product::META_REFERENCE`, product saves/status/deletes), `delete_term`. Queued via `mark_index_dirty()`, flushed on `shutdown`. Above `INDEX_FLUSH_MAX` the request reindexes **nothing** and schedules one cron event (half a batch is worse). A daily repair in `Mixmymeals_Cleanup::run()` reports deviations via `record()` — anything above zero is a missing hook.

**One registry owns every path under `/rezepte/`**: `archive_view_slugs()` (slug → [kind, value]; browse views, diets, time steps; first claim wins), read by `add_archive_view_rules()`, `reserve_browse_view_slugs()` and the flush fingerprint. Each facet registers its internal key and its translated word. Reservation only protects future slugs — **check existing recipe slugs before deploying a new path**.

**Filters**: `[mmm_archive_filters]` prints two rows of plain text links via `render_filter_row()` (diet, time), with counts over the whole archive and an "any" link, each carrying the other row's choice; quieter than the search button. **Diets combine** (toggle; one `meta_query` clause each, AND; a diet on the same axis replaces; theme marks `.mmm-archive-filters--multi` with ticks), **times do not**. `archive_filters()` has `diets` (the truth) and `diet` (that list when it holds exactly one); `archive_filters_with_diets()` alone normalises (unknown out, one per axis, vocabulary order) and sets `diet`; `archive_diets()` reads a set. Several diets travel as `?diaet=glutenfrei,vegan`, and a present `diaet` **replaces** the path's diet. A parameter beats a path for the same facet. `archive_filter_query_args()` is pure; `apply_archive_filters()` **merges** its `meta_query` (the unlisted clause is already there). `archive_primary_facet()` decides which facet owns the path.

**Addresses**: `archive_url_for()` is the one builder (primary facet in the path, rest in the query, **rawurlencoded** — `add_query_arg()` does not encode new args and `esc_url()` hides that); `archive_filters_without()`, `active_archive_filters()`. The theme's chips (`mmm_print_active_filter_chips()`) are links.

**Ingredient search**: `rank_recipes_by_ingredients()` counts **per token**, feeds `post__in` + `orderby => post__in`; no match → `array( 0 )` (empty `post__in` is no filter).

**Search field** (`mmm_s`, `apply_archive_search()`): on recipe archives it searches titles **and** ingredients through `Mixmymeals_Search::ids( 'recipe', … )` and sets `post__in` in that ranking (filter `mixmymeals_archive_search_combined`, default true; false = title only via `mmm_title_like`, as plan archives always do). Placeholder "Search recipes or ingredients…"; the second field `zutat` stays unless filter `mixmymeals_archive_search_ingredient_field` returns false. Both set `post__in`, so `apply_archive_filters()` (later on `pre_get_posts`) **intersects** with what the search left, in the ingredient ranking's order — replacing it dropped the search (fixed in 4.0.0).

Single facets are indexable; combinations and searches get `noindex` (`filter_archive_noindex()`). A category named "Vegan" is just a category chip. `[mmm_recipes_index]` knows only `mmm_s` — deliberate.

The ingredient page's recipe list (`recipes_using_ingredient()`) reads the index; `Mixmymeals_REST_API::ingredient_usage_map()` deliberately stays a full scan (counts drafts, decides deletability).

### Structured data, social tags, Bring!

No SEO class — each post type builds its structure, printing is central:
- `Mixmymeals_Recipe::get_schema()`: schema.org/Recipe (ingredients, `HowToStep`, nutrition per serving with salt ÷ 2.5 as sodium, `aggregateRating`, `suitableForDiet`), filter `mixmymeals_recipe_schema`. `Mixmymeals_Meal_Plan::get_schema()`: `ItemList` of published recipes, filter `mixmymeals_meal_plan_schema`.
- `Mixmymeals_Post_Types` prints schema in `wp_head` (`output_schema()`, prio 8) and OG/Twitter tags for `OG_POST_TYPES` (`output_og_tags()`, prio 6), skipped when Yoast/Rank Math/AIOSEO/Slim SEO is active. Filters `mixmymeals_seo_schema_enabled`, `mixmymeals_seo_og_enabled`. Helpers `meta_description()`, `social_image_id()` (featured → `mixmymeals_placeholder_image_id`; the placeholder is used only here and as Instagram fallback), `schema_image_urls()`.
- **Meals are reachable but not findable**: `NOINDEX_POST_TYPES` → `noindex, follow` via `filter_robots()` (`wp_robots`) and `filter_yoast_robots()` (`wpseo_robots_array`), both prio 20 (`index` must be unset); `is_noindex_view()` is the one decision (filter `mixmymeals_seo_noindex`; also servings and eater parameters). Plus `exclude_from_search => true`. The core sitemap selects on `public`, so the robots filters are not redundant. No canonical to the recipe; `follow` is what leads crawlers there. The unlisted flag (`UNLISTED_POST_TYPES`, recipes and plans; `exclude_unlisted()`) does not cover meals.
- **Bring!**: `<plan permalink>?mmm_bring=1` (`BRING_QUERY_VAR`, `register_bring()`) renders a minimal noindex page on `template_redirect` holding the shopping list as a schema.org/Recipe, which Bring! fetches itself. `bring_deeplink()`, `get_bring_shopping_list_lines()` ("405 g Möhren" — amount, unit, name; no brand).

### REST API and MCP

Namespace `mixmymeals/v1` (`Mixmymeals_Rest_Api`), base capability `edit_others_posts`: CRUD for recipes, ingredients, units, products, plans, meals; recipe search; post status; `/meal-plans/build` (bulk builder); `/meals/bulk`; `/audit`; the bulk endpoints above. MCP (`tools/mcp-server.mjs`) mirrors them. The read routes are public: each answers only for a published post unless the caller may edit it (`require_can_read()`; units and products follow their ingredient), and the listings `/recipes`, `/ingredients`, `/meal-plans` go through `paged_response()` — paged for the public (`per_page` ≤ 100, default 50), whole for editors unless they ask for pages. The Social add-on registers into the same namespace on purpose (MCP addresses those paths).

**Images**: `POST /media` takes exactly one of multipart `file`, `url` or `attachment_id`, routed through core (`media_handle_upload()` / `media_sideload_image()`); `post_id` sets the parent, `featured: true` the featured image; needs `upload_files`. Recipes/plans carry `featured_image` (0 removes) and read-only `featured_image_url` (`apply_featured_image()`). MCP `mmm_upload_image` posts local files as multipart.

### Author box

External author credit for recipes and plans, read via `Mixmymeals_Post_Meta` — `get_author_name/text/url/link_text/nofollow/social/image_id/image_url()`, `has_external_author()`, all through **`get_author_data()`**, the one decider. Edited by `render_author_metabox()` / `save_author_meta()`. Empty name → the post's WordPress author.

**Data lives on a term** of `mmm_author` (`Mixmymeals_Author::TAXONOMY`, registered in `Mixmymeals_Taxonomies::register_author_taxonomy()`): name = author, description = bio, five term metas under the `_mmm_author_*` keys. `get_author_data()` prefers the term, falls back to legacy post meta. `save_author_data()` find-or-creates by name, assigns, clears the post's legacy meta. Consequence: editing the bio changes it everywhere; a typo in the name creates a second person. `Mixmymeals_Author::migrate()` (`mixmymeals_author_taxonomy_version`) left the old rows as the way back.

**Public author archive is off** (`publicly_queryable`, `query_var`, `rewrite` false). The slug option `mixmymeals_author_slug` defaults to plural `authors`, because core owns `/author/`.

Markup `render_author_box()` for `[mmm_recipe_author_box]` and `[mmm_meal_plan_author_box]`. Picture `_mmm_author_image` at `AUTHOR_AVATAR_SIZE`, else no media block. **Never `get_avatar()`** — a Gravatar request on every page sends visitor IPs to Automattic; local-avatar plugins therefore do not reach the box.

**WordPress authors** (no external name) get their picture from their own profile: user meta under the same key `_mmm_author_image`, `Mixmymeals_Author::get_user_image_id()` / `set_user_image_id()`, field on profile/user-edit via `register_user_admin()` (own nonce `mmm_author_user_image`, same `.mmm-author-image-field` markup, so `admin.js`'s picker drives it). Only for accounts with `upload_files` (`user_may_have_image()`), checked on read **and** write: visitor accounts' saved plans fall back to their account, and the privacy text promises only the display name there. A non-image or deleted attachment reads as none. An external author never falls back to the account's picture.

REST: `author` object in recipe/plan serialization and writes (incl. `/meal-plans/build`), plus `GET|POST|PUT|PATCH|DELETE /recipes/{id}/author` and `/meal-plans/{id}/author`. Writes **merge** (`""` / `0` clears a field, `DELETE` clears the box). `save_author_data()` is destructive, so read-modify-write via `get_author_data()`, which returns the **raw** `link_text` (`get_author_link_text()` falls back to the URL host — writing that back would freeze it). Response: `link_text` and `link_text_display`; `image` (0 removes) and read-only `image_url`.

### Image credit

Featured-image attribution in `Mixmymeals_Post_Meta`: `IMAGE_CREDIT_FIELDS` (creator, source, licence, URL → `_mmm_image_credit_*`), `get_image_credit()`, `save_image_credit()`, `has_image_credit()`, `get_image_credit_text()`, `get_image_credit_name()`. `[mmm_image_credit]` reads "Creator / Source (Licence)"; the link wraps creator and source only, always `rel="nofollow"`. REST `image_credit` (merge, read-only `text`, `has_credit`). Templates place it **outside** `.mmm-*-feature-image` (fixed aspect ratio + `overflow: hidden` would clip it).

### Disclosure

How a recipe or plan came about commercially: `DISCLOSURE_TYPE` (`_mmm_disclosure_type`), `DISCLOSURE_SPONSOR` (`_mmm_disclosure_sponsor`), `get_disclosure()`, `save_disclosure()`, `get_disclosure_type/sponsor()`, `is_disclosed()`, `requires_disclosure_label()`; box `render_disclosure_metabox()` / `save_disclosure_meta()`.

| Value | Meaning | Label |
|---|---|---|
| *(no meta)* | editorial | — |
| `guest` | unpaid guest contribution | "Gastautor" (transparency, not legal) |
| `cooperation` | goods/services, no money | "Werbung" |
| `sponsored` | paid | "Werbung" |

**Not the same axis as the author box**: `has_external_author()` = who wrote it, `requires_disclosure_label()` = must it be labelled. Never substitute one for the other. Unknown stored types read as editorial; `save_disclosure()` clears the sponsor unless the type is in `DISCLOSURE_LABELLED_TYPES`. Source strings `Advertisement` / `Guest author` — the German translations are load-bearing ("Kooperation", "sponsored by" are too weak).

**Every public rendering of a recipe or plan must honour it**, visible without scrolling and in listings. `disclosure_label_for()` is the one resolver (meals via `resolve_credit_source()`); wording `disclosure_label_text()` (filter `mixmymeals_disclosure_label_text`). Renderings: `[mmm_disclosure_label]` (theme's `mmm_render_disclosure_label()` right after the hero on all three singles); `disclosure_chip( $post_id, $base )` as the **first** item of hero rows (`mmm-chip`) and in `build_recipe_archive_meta_chips()` (`mmm-meta__item`), with `{$base}--disclosure-{type}`; `disclosure_pill()` above browse-card titles (`render_recipe_browse_cards()`, `render_meal_plan_browse_cards()`, `render_related_recipe_cards()`); the Printer's PDF. "Werbung" and "Gastautor" must never look alike, and neither may use the green of `.mmm-badge`. `frontend.css` pins its `display`/`visibility` with `!important` so it cannot be themed away. Missing: schema.org `sponsor`, REST/MCP, mails, social.

### Privacy

**The privacy-policy section is generated**: `Mixmymeals_Privacy::policy_section_html( $heading )` is the single source for the Privacy Policy settings tab (preview + HTML), `[mmm_privacy_policy heading="h2|h3|h4|none"]` and WordPress's guide (`add_privacy_policy_content()`). Structure: data types, persons, purposes, legal bases, one item per processing.
- **Every figure comes from the constant or option that decides it** (`Mixmymeals_Cart::COOKIE_NAME`, `EXPIRATION`, `SHARE_EXPIRATION`, `Mixmymeals_Subscriptions::get_pending_hours()` / `get_retention_days()`, `Mixmymeals_Mail_Log::get_max_entries()` / `get_retention_days()`).
- **Every conditional sentence appears only where its condition holds** (subscriptions, consent IP `subscription_log_ip`, planner sign-up and `plan_subscribe_mode`, `photos_auto_approve`, the hashed save counter, the send log). `tools/privacy-policy-test.php` flips each condition.
- Settled positions from a data-protection review (2026-10-06): ratings and photos on Art. 6(1)(b), only subscriptions on consent; legitimate interests name the interest; saved plans are public pages (with the account name for logged-in users); the planner cookie is a *pseudonymous* identifier; comment-form ratings carry WP's IP and UA, subscriber-link ratings neither; mail content is named (rating/photo invitations make them advertising under § 7 UWG); Art. 13(2)(e) per planner mode; only finished subscriptions are deleted, N days after they ended. `consent_text()` links the policy as information, never as something to "accept".
- Add-ons contribute via `mixmymeals_privacy_policy_items` (plain text, escaped).
- A site owner who copied the HTML instead of using the shortcode does not get changes — the tab says so.

IPs (and the e-mail in the mailer's rate limit) are hashed with `wp_hash()`, never `md5()` (unsalted md5 of IPv4 is reversible). Option `mixmymeals_disable_wp_emoji` (`OPT_DISABLE_EMOJI`, `maybe_disable_emoji()`) unhooks WordPress's emoji script and `wp_staticize_emoji_for_email()` — the site's only third-party request — on `init` (front) and `admin_init` (admin).

### Cleanup

Deletions are permanent (`wp_delete_post( …, true )`), so the daily report is the only trace and must be **complete**: `delete_uncategorized_meal_plans()` and `delete_orphaned_meals()` return deleted items, child meals removed with plans, failures and whether the batch limit cut the run short — all in the mail. Other routines (`Mixmymeals_Mail_Log::prune()`, `Mixmymeals_Subscriptions::prune()`, the Social image cleanup, the index repair) report via `Mixmymeals_Cleanup::record( $key, $count )` into option `mixmymeals_cleanup_tally`, drained when the mail goes out; a no-op unless cleanup **and** report are on. Labels in `tally_line()`, add-ons via `mixmymeals_cleanup_tally_line`. **Every routine that deletes data must call `record()`.**

### Add-ons

Social (`mixmymeals-social`) and Printer (`mixmymeals-printer`, `ennono/mixmymeals-printer`) depend on this plugin; it knows neither. Anything else they need should become a hook here, not a reference back.

| Hook | Purpose |
|---|---|
| `mixmymeals_settings_fields` | Adds a settings tab (options keep the `mixmymeals_` prefix) |
| `mixmymeals_render_settings_field` | Renders a field `Mixmymeals_Admin_API::display_field()` does not know; return non-null to take over |
| `mixmymeals_cleanup_tally_line` | Wording for a `record()` key |
| `mixmymeals_privacy_policy_items` | Plain-text item for the privacy section |
| `mixmymeals_meal_plan_actions` | Plan actions (`label`, `icon`, `url`, `new_tab`, `data`, `servings`); `servings => true` appends the request's `?mmm_persons_to_add` and marks `data-servings-link`, rewritten by `frontend.js` |

**PDF export is the Printer's** (action `mmm_generate_pdf`, nonce-free, published plans only, 5/IP/min, button class `mmm-meal-plan-actions__item--print`). It reads plans through public methods, which are therefore **an add-on's API** — renaming or narrowing one breaks the PDF with no error here: `calc_nutrition_per_day_person()`, `calc_nutrition_by_day()`, `calc_shopping_list()`, `get_ingredients_scaled()`, `get_nutrition_pinned()`, `format_nutrition_value()`, `format_nutrition_percentage()`, `reference_intake_note()`, `build_needed_amount_string()`, `weekday_name()`, `calc_prep_by_day()`, `Mixmymeals_Recipe::step_prep()`, `prep_label()`, `requested_servings_offset()`, `with_servings_offset()`, `disclosure_label_for()`. Its context class is the one place there that reads this plugin. Without the add-on the button is simply absent.

The frontend admin panel (`[mmm_admin_actions]`) and the JSON importer are gone; a theme still printing that shortcode shows its literal text.

### Admin area

**Menus**: `mmm_recipe` (30), `mmm_ingredient` (31), `mmm_meal_plan` (32) top level; Social add-on at 33; 34 kept free; `mixmymeals_settings` at 35 (Settings, Subscriptions). Equipment hangs under Recipes; Meal Photos under Meal Plans.

```
Rezepte        Alle Rezepte · Neues Rezept · Kategorien · Tags · Autoren · Küchengeräte · Geräte-Kategorien
Zutaten        Alle Zutaten · Neue Zutat · Kategorien · Ernährungsformen
Essenspläne    Alle Essenspläne · Neuer Essensplan · Kategorien · Tags · Mahlzeiten-Fotos
Social Media   Pinterest-Pins · Einstellungen (→ Pinterest tab)          [add-on]
MixMyMeals     Einstellungen · Abos
```

- **Order is declared**: `Mixmymeals_Settings::SUBMENU_ORDER`, applied by `order_submenus()` at `admin_menu` `PHP_INT_MAX` (unlisted entries keep their order below). A new screen in these menus needs a line there; `tools/admin-structure-test.php` asserts each list exactly. A top-level item links to its first entry.
- Taxonomies of a nested post type get no menu from core: `Mixmymeals_Taxonomies::add_submenus()` adds equipment categories and `mmm_author` (once, under Recipes; both `show_in_menu => false`); `highlight_equipment_category_parent_menu()` keeps Recipes lit. Hand-added term-screen slugs use `&amp;` like core's. Equipment categories keep a distinct `menu_name`.
- `Mixmymeals_Settings::settings_url( $tab )` is the only place that knows where settings live (public; the add-on uses it).

**Settings tabs follow the menus**: General (currency, placeholder, cleanup, uninstall) · Recipes (incl. equipment, author slug, rating notice) · Ingredients (incl. traffic light) · Meal Plans (meal slug, visitor plans, photo approval, notices, planner, subscribe-while-saving, reference intake, plan rating, nutrition tables for meal/plan) · E-Mail & Subscriptions (key `mixmymeals-email`) · Privacy Policy · Language (Chips, Terms — `heading` fields with `'level' => 2`, `h2.mmm-settings-section-title`) · add-on tabs. Settings API, one option group, options `mixmymeals_<id>` regardless of tab — so moving a field between tabs is safe (check the set of field ids is unchanged). `Mixmymeals_Settings::resolve_tab()` maps retired keys via `TAB_ALIASES` (`-equipment`, `-visitors`, `-subscriptions`, `-chips`) and unknown ones to the first tab; tab links carry no nonce. **Renaming a tab means adding its old key to `TAB_ALIASES`.** Notable options: `mixmymeals_public_mode`, `mixmymeals_published_meal_plans_per_hour_limit`, `mixmymeals_save_meal_plans_published_by_visitors_as_draft`, `mixmymeals_placeholder_image_id`.

**`assets/css/admin.css`** is the only admin stylesheet, loaded on every screen, so selectors hang off the plugin's own classes; section 1 holds tokens (WP admin palette). Unifying the admin class prefixes onto `mmm-` is not possible — five short names collide with frontend classes; it would need `mmm-admin-` across ~159 call sites in CSS, PHP, `admin.js` and `admin.min.js`.
