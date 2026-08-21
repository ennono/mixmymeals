# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

MixMyMeals is a WordPress plugin (v3.10.1, text domain `mixmymeals`) for meal planning. It manages recipes, ingredients, meals, and meal plans with a shopping list generator and a frontend cart for building custom plans. Instagram/Pinterest publishing lives in the companion plugin `mixmymeals-social` and reaches this one only through the extension points listed below — this plugin makes no external API calls itself.

## Development commands

```bash
# Lint a file against WordPress coding standards (binary is not on PATH)
./vendor/bin/phpcs --standard=phpcs.xml <file>

# Auto-fix coding standard violations
./vendor/bin/phpcbf --standard=phpcs.xml <file>

# WordPress.org's own checks. Install once: wp plugin install plugin-check
wp plugin check mixmymeals --format=json
```

`phpcs.xml` currently **aborts** on some files: the bundled PHPCompatibility
sniff triggers a PHP deprecation of its own and stops checking the file, which
looks like a clean result. When that happens, run `--standard=WordPress` to get
real output.

Plugin Check reports findings for `.DS_Store`, `.idea/`, `.claude/`, `CLAUDE.md`
and the like. Those are working-directory noise — `.distignore` keeps all of
them out of the package, and a build made from it comes back with none. Judge
the package, not the checkout.

There is no automated test suite. PHP 8.0+ is required.

## Environment

Development runs under **Local (by Flywheel)**: the site is `mixmymeals.local`,
rooted at `/Users/timo/Local Sites/mixmymeals/app/public/`. MySQL listens on a
private socket, so wp-cli needs `DB_HOST` predefined — a one-line `--require`
file pointing at
`~/Library/Application Support/Local/run/<id>/mysql/mysqld.sock` does it. The
directory id changes when Local restarts, and more than one site may be
running, so **check `home_url()` before writing anything**: the sockets sit
side by side and the wrong one silently belongs to a different site.

Two things about this install regularly mislead:

- The active theme is **Twenty Twenty-Five**, not `mixmymeals-astra-child`.
  Loading a page therefore proves nothing about the child theme's templates,
  and `get_stylesheet_directory()` does not point at it. Read those files by
  path.
- It holds almost no content. Anything to do with plans, nutrition or shopping
  lists needs test data built first — and removed afterwards.

The production site is **stopfnicht.de**. Pushing to GitHub does not deploy it;
the plugin version in the page source (`?ver=`) tells you what is actually
live, which is the reason each user-visible fix gets its own patch release.

JavaScript is served in two flavours: `assets/js/*.js` (debug) and `assets/js/*.min.js` (production). The main class picks between them based on `SCRIPT_DEBUG`. When editing JS, keep both in sync.

## Architecture

### Bootstrap flow

`mixmymeals.php` → requires all class files → instantiates `Mixmymeals::instance(__FILE__, VERSION)` at the bottom of `mixmymeals.php`. The singleton wires up hooks for assets, localisation, and cart merging on login. Settings, post types, taxonomies, shortcodes, and post meta boxes are instantiated via wrapper methods on the main class (called from `mixmymeals.php`).

### Custom post types

All types are prefixed `mmm_`:

| Post type | Class | Notes |
|---|---|---|
| `mmm_recipe` | `Mixmymeals_Recipe` | Public, has archive, slug from settings option |
| `mmm_ingredient` | `Mixmymeals_Ingredient` | Public, has archive, slug from settings option |
| `mmm_ingr_unit` | `Mixmymeals_Ingredient_Amount` | Hidden, child posts of `mmm_ingredient` |
| `mmm_meal_plan` | `Mixmymeals_Meal_Plan` | Public, has archive |
| `mmm_meal` | `Mixmymeals_Meal` | Child post of `mmm_meal_plan` via `post_parent` |
| `mmm_equipment` | `Mixmymeals_Equipment` | Public, has archive, slug from settings option; linked from recipes via `_mmm_equipment` meta (array of equipment post IDs) |

Slugs for public post types and their taxonomy archives are configurable in Settings and stored as WP options (`mixmymeals_recipe_slug`, `mixmymeals_meal_plan_slug`, `mixmymeals_meal_slug`, `mixmymeals_ingredient_slug`, `mixmymeals_equipment_slug`). After changing slugs, WordPress permalinks must be flushed (the ingredient/equipment slug options schedule a flush automatically).

### Taxonomies

- `mmm_recipe_category` (hierarchical), `mmm_recipe_tag` — for recipes
- `mmm_ingredient_category` — for ingredients (used to group shopping lists)
- `mmm_meal_plan_category`, `mmm_meal_plan_tag` — for meal plans

### Domain model and key meta keys

**Recipe** — steps stored as a serialized array in post meta `_rmm_steps`. Each step has `title`, `description`, `image`, and an `ingredients` array. Each ingredient entry has `id` (ingredient post ID), `amount` (float), and `amount_id` (unit post ID).

`Mixmymeals_Recipe` also owns the **cook counter** (`_mmm_cook_count`, const `META_COOK_COUNT`): how often the recipe sits on a published plan, measured as meals whose meal **and** parent plan are published. Never incremented — `recount_cook_count()` recomputes from the DB, batched to `shutdown` via `flush_cook_counts()`. Zero = no meta at all, so `[mmm_uncooked_recipes]` is a `NOT EXISTS` query and `[mmm_popular_recipes]` an ordered `meta_value_num` query. Editable in the Details meta box: `set_cook_total()` stores the signed difference to the measurement in `_mmm_cook_count_adjust`, so any value from 0 upwards is honoured exactly and real plannings keep counting on (result floored at 0). One-off backfill via option `mixmymeals_cook_count_version`; `recount_all_cook_counts()` doubles as a repair routine. Hooks are attached once per request via `Mixmymeals_Recipe::register_cook_count()` in `mixmymeals.php` — note they watch **meal and plan** events (`wp_after_insert_post`, `transition_post_status`, meta writes, `before_delete_post`), since the counter belongs to a recipe but every event that changes it happens elsewhere.

`Mixmymeals_Recipe` also owns the **edit counter** (`_mmm_edit_count`, const `META_EDIT_COUNT`): `get_edit_count()` / `set_edit_count()`, fed by the static `count_edit_on_update()` on `post_updated` (skips creation from auto-draft, autosaves and revisions). Hooked up once per request via `Mixmymeals_Recipe::register_edit_count()` in `mixmymeals.php`, same pattern as `Mixmymeals_Cart::register_ajax()`. Deliberately **not** built on post revisions: WP only versions title/content/excerpt (`_wp_post_revision_fields()`), so meta-only recipe edits — steps, ingredients, times — create no revision at all. Displayed via `[mmm_recipe_edit_count]`.

**Ingredient** — nutrition stored per-key in post meta: `_rmm_calories_100`, `_rmm_fat`, `_rmm_carbs`, `_rmm_protein`, `_rmm_sugar`, `_rmm_fiber`, `_rmm_salt`. Units are child posts (`mmm_ingr_unit`).

**`Mixmymeals_Ingredient::get_nutrition()` is the only place ingredient nutrition may be read.** All nineteen call sites go through it — recipe pages, plan pages, the PDF, subscription mails, the calculator, the shopping list — and `Mixmymeals_Recipe::_calculate_total_nutrition()` does too. The `_rmm_*` keys are touched directly only inside `Mixmymeals_Ingredient` itself and where the REST API registers them. Keep it that way: a caller reading the meta directly would bypass anything the getter later resolves through (a product override, a unit conversion) and put a second, disagreeing figure on one of those channels. The same rule holds for `Mixmymeals_Post_Meta::get_author_data()` and `Mixmymeals_Ingredient_Amount::get_step_divisor()`, and for the same reason: several of this codebase's worst bugs were two channels computing one number separately.

**Product** (`mmm_product`, `Mixmymeals_Product`) — a concrete, branded article an ingredient can be bought as. Child of the ingredient via `post_parent` and hidden like `mmm_ingr_unit`, because it is a detail of its parent rather than a page. Meta: the seven nutrition keys under `_mmm_product_*`, plus `_mmm_product_package_weight` (grams per packet), `_mmm_product_brand` and `_mmm_product_source`. Edited in the Products meta box on the ingredient screen (`render_ingredient_products_meta_box_content()` / `save_ingredient_products()`), which carries its own nonce and its own `save_post_mmm_ingredient` hook so a screen that does not render it cannot wipe the products by not posting them.

Over REST: `POST /products`, `GET|POST|DELETE /products/{id}` and `GET /ingredients/{id}/products`. `nutrition` is an object of the seven keys — only supplied keys are touched and `null` deletes one, so a client can withdraw a figure instead of only overwriting it, and the serialization keeps nulls for the same reason: null and 0 are different claims about somebody else's product. `is_reference` sets or clears the ingredient's pointer. MCP mirrors all five as `mmm_get_ingredient_products`, `mmm_get_product`, `mmm_create_product`, `mmm_update_product` and `mmm_delete_product`.

`[mmm_ingredient_products]` lists them on the ingredient page, one column per product against the seven nutrition rows — the transposed shape, because seven figures as columns push protein and salt off the page, which are the two a reader compares yoghurts by. Deliberately **without** the `mmm-nutri-table` class every other nutrition table carries: the theme collapses that into one card per row below 544px and hides the table head to do it, and here the head carries the brand, the package size and the source. It scrolls sideways in its wrapper instead, with the label column pinned via `position: sticky`.

**The table is relative, not seven columns of absolutes.** One product anchors it and keeps its own figures; every other column states the difference (`+12,0 g`, `−30 kcal`, `±0` when it rounds away). `pick_products_baseline()` picks the reference product, or failing that the lowest-energy one — a stable choice, unlike "the first entered", which would silently re-anchor the whole table the day someone adds a product — and the anchor is sorted to the front. `format_nutrition_delta()` returns a dash unless **both** ends are stated: treating an absent value as zero would not merely invent a number, it would invent the largest difference in the table and attribute it to somebody else's product. Signed with U+2212, not a hyphen, which in a column of figures reads as the dash that means "not stated".

Only the reference carries a chip (`Used for the nutrition figures`), because that is a claim about the *ingredient* — its figures come from that packet. Which column anchors the arithmetic is not a property of the product, and the note under the table names it in a sentence; as a chip beside a brand name it read as a distinction the brand had earned. Differences carry **no colour** for the same reason: more protein and more salt would take the same green, and a reader would take that for a verdict on a brand.

The heading cells are built from `div`s, not `span`s styled into blocks: a site serving a stale `frontend.css` ran the name, the brand and the source together into one unreadable string, and a stack that basic must not depend on a stylesheet arriving. Sources sit in a footnote list under the table rather than in the heading — a real one reads like a sentence, and in the head it either shrinks past legibility or pushes the figures off the screen.

A value the product does not state renders as a dash, never `0,0 g` — the same null-is-not-zero rule the storage layer keeps, and the place it matters most, since this is a public claim about somebody else's product. The reference product is marked, in a muted chip rather than the green of `.mmm-badge`: green is the brand's positive tone and a quality mark beside a brand name is what this must not read as.

The two levels answer different questions. An ingredient carries figures good enough to plan with and stays correct when a manufacturer changes a recipe; a product carries what one packet says. Worth maintaining only where products differ a lot — yoghurt at 0.1 % or 10 % fat, minced meat, cheese, bread. For red cabbage the gap between 25 and 41 kcal is smaller than the error from cooking losses and portion size.

**The ingredient resolves through its reference product** (`_mmm_reference_product` on the ingredient, managed by `Mixmymeals_Product::get_reference_id()` / `set_reference_id()`). `Mixmymeals_Ingredient::get_nutrition()` merges the two **key by key**, product first: a packet often states energy and fat and nothing else, and discarding those two because the other five are missing would be the wrong trade. `weight_item` is never overridden — it describes the ingredient (one medium onion), not a packet. Because that getter is the single read path, every channel follows without knowing products exist.

`get_reference_id()` re-validates on every read and returns 0 when the stored product was deleted or now hangs off a different ingredient, and `set_reference_id()` refuses a product belonging elsewhere. `set_nutrition()` deletes a key given as null or '' rather than storing zero: "not stated on the packet" and "contains none" are different claims, and the second is a claim about somebody else's product. `_mmm_product_source` exists for the same reason — these figures go stale when a recipe changes, and without a note saying where a number came from nobody can tell a current one from a four-year-old one.

**Ingredient_Amount** (`mmm_ingr_unit`) — child post of an ingredient. Meta: `_mmm_ingr_unit_weight` (g per unit), `_mmm_is_sale_unit` (**inert** — nothing reads or writes it: no PHP, no REST parameter, no admin field. Only `tools/mcp-server.mjs` sends it, into an endpoint that discards it. Either implement it or drop it; do not rely on it), `_mmm_unit_divisor` (how finely the unit may be split).

**Divisibility is one number.** `_mmm_unit_divisor`: 1 = whole ones only, 2 = halves, 4 = quarters, absent = any amount. `get_divisor()` reads it and `get_step_divisor()` is the same value under the name calculations use. It used to be two settings — this divisor plus a `_mmm_is_indivisible` boolean — which gave "whole ones only" two representations, forced every writer to reconcile them, and made entering 1 mean the *opposite* of what it reads like, because the divisor only counted from 2 upwards. `is_indivisible()` survives as a derived accessor (`1 === get_divisor()`) and `set_indivisible()` as a wrapper, because both the REST API and MCP still expose the field; note that `set_indivisible( false )` clears the restriction entirely rather than only the "whole ones" part.

`get_divisor()` falls back to the old boolean for units the migration has not reached, so an install part-way through an upgrade never treats its eggs as divisible. `migrate_indivisible()` moves the rows once, gated on `mixmymeals_unit_divisor_version` and attached on `admin_init` from `mixmymeals.php`.

The two call sites round in opposite directions on purpose: `Mixmymeals_Recipe::get_ingredients_scaled()` rounds to nearest, because scaling four servings down to three must not silently add a tin, while `Mixmymeals_Meal_Plan::calc_shopping_list()` rounds **up**, once over the aggregated requirement — at the shop too little is worse than too much, but buying two tins for two half-tin recipes is the error that aggregation exists to prevent. The arithmetic runs on the integer multiple (`round( $amount * $d ) / $d`) so thirds do not drift into 0.33333.

Over REST a unit carries `divisor`, a read-only `step_divisor` and the older `is_indivisible`; MCP exposes `divisor` on `mmm_create_ingr_unit` / `mmm_update_ingr_unit` and keeps `is_indivisible` marked deprecated.

**Meal** — child post of a meal plan. Meta: `recipe_id`, `servings`, `mmm_meal_type` (breakfast/lunch/dinner/snack/other), `_mmm_day` (1–7).

**Meal Plan** — meta: `start_day` and `end_day` (1=Mon … 7=Sun). Plans can span across Sunday (e.g. start=6, end=2).

### Ingredient amounts and weight calculation

`Mixmymeals_Recipe::get_ingredients_scaled($scale)` is the canonical method for getting ingredient lists with correct weights. It:
1. Multiplies each ingredient's stored amount by `$scale`
2. Snaps the result to the unit's `get_step_divisor()` (min one step): whole
   ones at a divisor of 1, halves at 2, any amount when there is none
3. Multiplies by the unit's weight in grams to get `weight_g`
4. Falls back to treating the amount as grams directly if no unit is set

`Mixmymeals_Meal_Plan::calc_shopping_list()` uses this by computing `$scale = meal_servings / recipe_servings` for each meal and aggregating `weight_g` totals per ingredient, then grouping by `mmm_ingredient_category`.

### Cart

`Mixmymeals_Cart` stores recipe selections (recipe_id, servings, type, day) in:
- **Logged-in users**: `mixmymeals_cart` user meta
- **Anonymous users**: WP transient keyed by UUID stored in cookie `mixmymeals_cart_uuid`

On login, the guest cart merges into the user's cart. The max cart size is set via settings option `mixmymeals_max_items_in_cart`.

### Frontend (Shortcodes + AJAX)

All shortcodes live in `Mixmymeals_Shortcodes` and are prefixed `mmm_`. AJAX handlers are also registered there (not as shortcodes). Frontend JS is localized as `mixmymeals` (ajaxurl, nonce). Admin JS is localized as `mixmymealsAdmin` (ajaxurl, nonce), `mixmymealsRecipes` (full recipe list), and `MixMyMealsAmounts` (nonces for ingredient unit CRUD).

### PDF export

`Mixmymeals_PDF_Builder` wraps dompdf (vendored at `includes/lib/dompdf/3.1.4/`). Build a PDF by adding HTML pages with `add_page()` and calling `render()`.

The public PDF download endpoint (`mmm_generate_pdf`, admin-ajax) is **intentionally nonce-free**: PDFs of published plans must be shareable/cacheable by anonymous visitors. Protection: only published plans are served, plus a per-IP rate limit (5/min).

### Structured data, social tags and Bring!

There is no SEO class — each structure is built by the post type that owns it, and only the *printing* is central:

- `Mixmymeals_Recipe::get_schema()` returns the schema.org/Recipe for one recipe (ingredients, `HowToStep` instructions, nutrition per serving, `aggregateRating` from `_mmm_rating_avg/_count`, `suitableForDiet`), filter `mixmymeals_recipe_schema`.
- `Mixmymeals_Meal_Plan::get_schema()` returns the plan's `ItemList` of published recipes, filter `mixmymeals_meal_plan_schema`.
- `Mixmymeals_Post_Types` prints both in `wp_head` (`output_schema()`, priority 8, switch on `is_singular()`) plus Open Graph/Twitter tags for `OG_POST_TYPES` (`output_og_tags()`, priority 6). OG is skipped when Yoast/Rank Math/AIOSEO/Slim SEO is active — never double up. Filters: `mixmymeals_seo_schema_enabled`, `mixmymeals_seo_og_enabled`.
- The three helpers all of the above share are public statics on `Mixmymeals_Post_Types`: `meta_description()` (excerpt → content, 300 chars), `social_image_id()` (featured image → `mixmymeals_placeholder_image_id`; the placeholder is used ONLY here and as Instagram slide fallback) and `schema_image_urls()`.

The **Bring! import view** lives entirely in `Mixmymeals_Meal_Plan`, registered once per request via `Mixmymeals_Meal_Plan::register_bring()` in `mixmymeals.php` (same pattern as `Mixmymeals_Recipe::register_cook_count()`). `<plan permalink>?mmm_bring=1` (`BRING_QUERY_VAR`) renders a minimal noindex page on `template_redirect` whose only content is the shopping list as a schema.org/Recipe — Bring! fetches that URL itself and imports what it finds, and a plan page is an ItemList, not a Recipe. `bring_deeplink()` builds the link the shortcodes render; `get_bring_shopping_list_lines()` formats "405 g Möhren" (amount, unit, name — the order Bring! splits on).

Amounts in all of these go through `Mixmymeals_Ingredient::format_amount()`: no thousands separator, since parsers read "1.000 g" as one gram. The human-facing counterparts are `Mixmymeals_Meal_Plan::build_needed_amount_string()` / `build_short_amount_string()`.

### Feature modules (includes/lib)

| Class | Purpose |
|---|---|
| `Mixmymeals_Privacy` | GDPR personal-data exporters/erasers (subscriptions, photos), suggested privacy-policy text |
| `Mixmymeals_Subscriptions` | Meal-plan e-mail subscriptions: double opt-in, consent records, drip sending via WP-Cron, token endpoints |
| `Mixmymeals_Mailer` / `Mixmymeals_Mail_Log` | wp_mail wrapper with GDPR footer + send log |
| `Mixmymeals_Ratings` | Comment-based star ratings (logged-in users + subscribers via token), per-recipe aggregates in `_mmm_rating_avg/_count` |
| `Mixmymeals_Photos` | Visitor meal-photo uploads (MIME-whitelisted, EXIF-stripped), moderation page, gallery |
| `Mixmymeals_Rest_Api` | REST namespace `mixmymeals/v1`: CRUD for recipes/ingredients/units/plans/meals, recipe search, post status, AI-friendly `/meal-plans/build` bulk builder, author box and media upload (see below) |
| `Mixmymeals_Cleanup` | WP-Cron cleanup of orphaned meals and stale visitor plans; owns the cleanup report e-mail (see below) |
| `Mixmymeals_Ingredient_Importer` / `Mixmymeals_Recipe_Importer` | Admin JSON importers |
| `Mixmymeals_User_Roles` | Custom roles (meal mixer) |
| `Mixmymeals_Admin_API` | Settings-field rendering engine used by `Mixmymeals_Settings` |

A companion MCP server for AI automation lives in `tools/mcp-server.mjs` (excluded from distribution via `.distignore`).

### Author box

The external author credit shared by recipes and meal plans is read through `Mixmymeals_Post_Meta` — `get_author_name/text/url/link_text/nofollow/social/image_id/image_url()`, `get_author_data()`, `save_author_data()` and `has_external_author()`, beside the meta box (`render_author_metabox()` / `save_author_meta()`) that edits them. An empty name falls the box back to the post's WordPress author.

**The data itself lives on a term, not on the post.** `Mixmymeals_Author` owns the taxonomy `mmm_author` (`Mixmymeals_Author::TAXONOMY`, registered — like every other taxonomy — in `Mixmymeals_Taxonomies::register_author_taxonomy()`, for `mmm_recipe` and `mmm_meal_plan`; the term-form fields and the migration hook attach once per request via `Mixmymeals_Author::register_term_admin()` in `mixmymeals.php`). The term name is the author's name, the term description their bio, and five term metas carry the rest under the *same* `_mmm_author_*` keys the post meta used. An author therefore exists once instead of being re-typed per contribution, where every typo created a second person.

**The public author archive is currently switched off** — `publicly_queryable`, `query_var` and `rewrite` are all `false`, so terms exist in wp-admin but have no front-end page. The slug plumbing is still there for when it is turned back on: `mixmymeals_author_slug`, defaulting to the **plural** `authors`, because WordPress core owns `/author/` through its `author_base` and registering a taxonomy on the same base makes one of the two unreachable. Re-enabling it is what would give a guest author a page to link to from their own site.

`get_author_data()` is the single place that decides where the fields come from — every other getter reads through it, so they cannot disagree. It prefers the term and falls back to the legacy per-post meta, so an install that has not run the migration, or a post restored from an older backup, still shows its credit. `save_author_data()` writes the term (find-or-create by name), assigns it, and clears that post's legacy meta so the two can never contradict each other. Note the consequence: editing the bio on one recipe changes it everywhere that author appears, and a typo in the name creates a second person rather than renaming the first.

The one-off move of legacy meta into terms is `Mixmymeals_Author::migrate()`, gated on `mixmymeals_author_taxonomy_version`. It deliberately leaves the old rows in place — they are inert once a term exists, and they are the only way back if two people who happened to share a name were merged.

The markup is `render_author_box()` in `Mixmymeals_Shortcodes`, shared by `[mmm_recipe_author_box]` and `[mmm_meal_plan_author_box]` so both post types render identically. A picture (`_mmm_author_image`, attachment ID) is rendered with the same classes and size (`AUTHOR_AVATAR_SIZE`) as `get_avatar()`, so both sources look identical; without one the media block is omitted.

`[mmm_recipe_author_box]` also renders on a meal: a meal has no author of its own and credits the recipe it plans. **Every feature that credits a post must resolve through `Mixmymeals_Meal::resolve_credit_source()`** (meal → its recipe, everything else → itself) — the author box, `[mmm_image_credit]`, and the child theme's `mmm_inject_author_social_links()` filter all do. Reading the meal directly instead attributes the recipe's picture and author to whoever happened to create the meal.

Over REST, recipes and meal plans expose the credit two ways: as an `author` object in the serialized post (and accepted on every create/update, including `/meal-plans/build`), and as a sub-resource `GET|POST|PUT|PATCH|DELETE /recipes/{id}/author` resp. `/meal-plans/{id}/author`.

### Image credit

Attribution for the featured image of recipes and plans lives in `Mixmymeals_Post_Meta`: `IMAGE_CREDIT_FIELDS` maps the four field keys (creator, source, licence, URL) to their `_mmm_image_credit_*` meta keys, with `get_image_credit()`, `save_image_credit()`, `has_image_credit()`, `get_image_credit_text()` and `get_image_credit_name()` beside the meta box that edits them. Structured rather than free text because CC-style licences ask for the parts separately.

The markup lives in `[mmm_image_credit]` in `Mixmymeals_Shortcodes` and reads "Creator / Source (Licence)"; the link wraps creator and source only, since a licence is not a place an image came from, and always carries `rel="nofollow"` — unlike the author box, which has a per-post toggle. REST exposes it as `image_credit` (merge semantics, plus a read-only `text` and `has_credit`), MCP as `image_credit` on the recipe/plan tools.

It sits in the templates **outside** the `.mmm-*-feature-image` container, never inside it: those frames have a fixed `aspect-ratio` plus `overflow: hidden`, so a line within them is clipped away invisibly. A negative top margin in the child theme pulls it back up to the picture.

The profile picture travels as `image` (writable attachment ID, `0` removes it) plus a read-only `image_url`.

Writes **merge** — only supplied keys change, `""` (or `0` for `image`) clears a single field, `DELETE` clears the whole box so the post credits its WordPress author again. `Mixmymeals_Post_Meta::save_author_data()` itself is destructive, so always read-modify-write via `get_author_data()`, which returns the *raw* `link_text` (unlike `get_author_link_text()`, which falls back to the URL's host — writing that resolved value back would silently freeze the host into the database). The response carries both: `link_text` (stored) and `link_text_display` (rendered).

### Disclosure (sponsored / external content)

How a recipe or plan came about **commercially**, in `Mixmymeals_Post_Meta` alongside the author box and image credit: `DISCLOSURE_TYPE` (`_mmm_disclosure_type`) and `DISCLOSURE_SPONSOR` (`_mmm_disclosure_sponsor`), with `get_disclosure()`, `save_disclosure()`, `get_disclosure_type/sponsor()`, `is_disclosed()` and `requires_disclosure_label()` beside the meta box (`render_disclosure_metabox()` / `save_disclosure_meta()`) that edits them.

Four states, of which editorial is the absence of any meta (same convention as the unlisted flag — no migration, cheap `NOT EXISTS`):

| Value | Meaning | Visible label |
|---|---|---|
| *(no meta)* | editorial, our own | no |
| `guest` | unpaid guest contribution | "Gastautor" — transparency, not a legal disclosure |
| `cooperation` | goods/services provided, no money | **yes** |
| `sponsored` | paid placement | **yes** |

**This is deliberately not the same axis as the author box.** A guest recipe has an external author but is not advertising; a sponsored recipe may well be written in-house. Collapsing both into one "external" badge is exactly what makes a disclosure legally worthless — `has_external_author()` answers *who wrote it*, `requires_disclosure_label()` answers *must this be labelled*. Never substitute one for the other.

**Every feature that renders a recipe or plan publicly must honour `requires_disclosure_label()`** — for commercial content the label is the whole point of the setting, not a display preference, and it has to be visible without scrolling and in listings too, not only on the single page. The German wording is "Anzeige"/"Werbung"; softer phrasings like "Kooperation" or "sponsored by" are widely regarded as too weak, which is why the source strings are `Advertisement` and `Guest author` and their German translations are load-bearing.

`get_disclosure_type()` maps any unknown stored value back to editorial rather than trusting it through, since the type decides whether that label is printed. `save_disclosure()` clears the sponsor whenever the type is not one of `DISCLOSURE_LABELLED_TYPES`, so a name from an earlier classification can never surface in a label or in structured data.

The markup is `[mmm_disclosure_label]` in `Mixmymeals_Shortcodes`, wording from `disclosure_label_text()` (filter `mixmymeals_disclosure_label_text`). Two pills out of one mechanism: **"Werbung"** for both commercial types, **"Gastautor"** for guest contributions. The source strings are `Advertisement` and `Guest author`, so those German translations are load-bearing, not cosmetic.

They render from one code path but must never look alike — the theme gives "Werbung" the accent colour in caps and "Gastautor" a muted, bordered chip in normal case. If both pills shout equally, "Werbung" loses the signal value that is its entire purpose. Only the commercial pair is a legal disclosure; `requires_disclosure_label()` remains the code-level answer to *must this be labelled*, while rendering keys off the broader `is_disclosed()`.

Like every crediting feature it resolves through `Mixmymeals_Meal::resolve_credit_source()`, so a meal inherits the disclosure of the recipe it plans. The child theme calls it via `mmm_render_disclosure_label()` directly after the hero on all three singles — above the content, because a label below the fold does not discharge the obligation.

The structural CSS in `assets/css/frontend.css` pins `display` and `visibility` with `!important`, unlike every other block in that file: design belongs to the theme, but a disclosure the site owner has switched on must not be themable into invisibility. The theme styles it as a pill in the accent colour, deliberately not the green of `.mmm-badge` — green is the brand/positive tone and an ad label must not read as a quality mark.

`disclosure_label_for()` is the single place that resolves the meal and decides whether there is anything to show; all three renderings build on it, so they cannot disagree.

Where a pill row already exists, the label joins it rather than floating above it: `disclosure_chip( $post_id, $base )` takes the surrounding row's base class and emits `{$base} {$base}--disclosure {$base}--disclosure-{type}`. That is `mmm-chip` in the three heroes (`mmm_recipe_hero`, `mmm_meal_plan_hero`, `mmm_meal_hero`, always as the first item) and `mmm-meta__item` in `build_recipe_archive_meta_chips()`, which the recipe archive already renders. The theme keeps its own colour on the modifier — the label sits in the row but must not read as another statistic.

Browse cards have no pill row, so they keep `disclosure_pill()`: the bare label above the card title, without the sponsor name. It is wired into `render_recipe_browse_cards()` and `render_meal_plan_browse_cards()` (which `render_related_recipe_cards()` reuses).

Still missing: schema.org `sponsor`, REST/MCP exposure, and the PDF/e-mail/social channels.

### Images over REST

`POST /mixmymeals/v1/media` takes an image from exactly one of three sources — a multipart `file` field, a `url` the server fetches, or an `attachment_id` already in the library — and routes it through core (`media_handle_upload()` / `media_sideload_image()`), so MIME checks, intermediate sizes and metadata behave exactly as in wp-admin. With `post_id` the attachment gets that parent, and `featured: true` makes it the post's featured image in the same call. Requires `upload_files` on top of the namespace's usual `edit_others_posts`.

Recipes and plans carry `featured_image` (attachment ID, `0` removes it) on create/update and in their serialization, alongside a read-only `featured_image_url` — the same `apply_featured_image()` helper equipment already used. The returned attachment ID also works wherever else images are referenced (`steps[].image`, `author.image`).

On the MCP side `mmm_upload_image` reads a local file and posts it as multipart; `url`/`attachment_id` go out as plain JSON.

### Cleanup report

Deletions are permanent (`wp_delete_post( …, true )`, no trash), so the report is the only trace they leave. It must therefore stay **complete**: `delete_uncategorized_meal_plans()` and `delete_orphaned_meals()` return a stats array — deleted items, meals removed alongside their plan, failures, and whether the batch limit cut the run short — and every one of those numbers appears in the mail. Counting only the plans hides the far larger number of child meals that go with them.

Cleanup does not only happen here, though: `Mixmymeals_Mail_Log::prune()` and `Mixmymeals_Subscriptions::prune()` run on their own crons, as does the Social add-on's image cleanup. They report their counts via `Mixmymeals_Cleanup::record( $key, $count )`, which accumulates into the option `mixmymeals_cleanup_tally`; the daily report lists them under "Other cleanup" and empties the option once the mail actually goes out. `record()` is a no-op unless cleanup **and** report are enabled — otherwise no run exists that would ever drain the tally. Labels for new keys go into `tally_line()`; a plugin reporting its own key supplies the wording through the `mixmymeals_cleanup_tally_line` filter instead.

**Every routine that deletes data must call `record()`** — the subscription pruning in particular removes personal data under a GDPR retention rule and must not disappear silently.

### Add-on extension points

Instagram and Pinterest publishing lives in a separate plugin (`mixmymeals-social`), which depends on this one but is not known to it. Four hooks carry everything it contributes — anything else an add-on needs should become a fifth rather than a reference back into the host:

| Hook | Purpose |
|---|---|
| `mixmymeals_settings_fields` | Adds a settings tab. Options keep the host's `mixmymeals_` prefix, so a tab can move between plugins without touching stored values. |
| `mixmymeals_render_settings_field` | Renders a field the host's id-switch in `Mixmymeals_Admin_API::display_field()` does not know. Return non-null to take over; that switch cannot be extended otherwise. |
| `mixmymeals_admin_board_services` | Contributes a publishing target to `[mmm_admin_actions]`. The board itself only knows WordPress status and trash. |
| `mixmymeals_cleanup_tally_line` | Supplies the wording for a key reported via `Mixmymeals_Cleanup::record()`. |

The add-on registers its REST routes into this plugin's `mixmymeals/v1` namespace on purpose: the MCP server and `admin-board.js` address those paths, and a separate namespace would break both for no gain.

### Settings

Settings are registered via `Mixmymeals_Settings` using the WordPress Settings API. All option names are prefixed `mixmymeals_`. Notable options: `mixmymeals_public_mode` (allow guest plan creation), `mixmymeals_published_meal_plans_per_hour_limit`, `mixmymeals_save_meal_plans_published_by_visitors_as_draft`, `mixmymeals_placeholder_image_id`.
