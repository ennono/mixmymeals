# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

MixMyMeals is a WordPress plugin (v1.4.3, text domain `mixmymeals`) for meal planning. It manages recipes, ingredients, meals, and meal plans with a shopping list generator and a frontend cart for building custom plans.

## Development commands

```bash
# Lint a file against WordPress coding standards
phpcs --standard=phpcs.xml <file>

# Auto-fix coding standard violations
phpcbf --standard=phpcs.xml <file>
```

There is no automated test suite. PHP 8.0+ is required. The codebase runs inside XAMPP at `/Applications/XAMPP/xamppfiles/htdocs/mixmymeals/working-wp/`.

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

**Ingredient_Amount** (`mmm_ingr_unit`) — child post of an ingredient. Meta: `_mmm_ingr_unit_weight` (g per unit), `_mmm_is_sale_unit` (preferred for shopping lists), `_mmm_is_indivisible` (quantity rounds to whole number).

**Meal** — child post of a meal plan. Meta: `recipe_id`, `servings`, `mmm_meal_type` (breakfast/lunch/dinner/snack/other), `_mmm_day` (1–7).

**Meal Plan** — meta: `start_day` and `end_day` (1=Mon … 7=Sun). Plans can span across Sunday (e.g. start=6, end=2).

### Ingredient amounts and weight calculation

`Mixmymeals_Recipe::get_ingredients_scaled($scale)` is the canonical method for getting ingredient lists with correct weights. It:
1. Multiplies each ingredient's stored amount by `$scale`
2. If the unit is indivisible, rounds to nearest whole number (min 1)
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

### Author box

The external author credit shared by recipes and meal plans lives in `Mixmymeals_Post_Meta` next to the image credit and follows the same split: the `AUTHOR_*` constants map the seven `_mmm_author_*` meta keys, with `get_author_name/text/url/link_text/nofollow/social/image_id/image_url()`, `get_author_data()`, `save_author_data()` and `has_external_author()` beside the meta box (`render_author_metabox()` / `save_author_meta()`) that edits them. An empty name falls the box back to the post's WordPress author.

The markup is `render_author_box()` in `Mixmymeals_Shortcodes`, shared by `[mmm_recipe_author_box]` and `[mmm_meal_plan_author_box]` so both post types render identically. A picture (`_mmm_author_image`, attachment ID) is rendered with the same classes and size (`AUTHOR_AVATAR_SIZE`) as `get_avatar()`, so both sources look identical; without one the media block is omitted.

`[mmm_recipe_author_box]` also renders on a meal: a meal has no author of its own and credits the recipe it plans. **Every feature that credits a post must resolve through `Mixmymeals_Meal::resolve_credit_source()`** (meal → its recipe, everything else → itself) — the author box, `[mmm_image_credit]`, and the child theme's `mmm_inject_author_social_links()` filter all do. Reading the meal directly instead attributes the recipe's picture and author to whoever happened to create the meal.

Over REST, recipes and meal plans expose the credit two ways: as an `author` object in the serialized post (and accepted on every create/update, including `/meal-plans/build`), and as a sub-resource `GET|POST|PUT|PATCH|DELETE /recipes/{id}/author` resp. `/meal-plans/{id}/author`.

### Image credit

Attribution for the featured image of recipes and plans lives in `Mixmymeals_Post_Meta`: `IMAGE_CREDIT_FIELDS` maps the four field keys (creator, source, licence, URL) to their `_mmm_image_credit_*` meta keys, with `get_image_credit()`, `save_image_credit()`, `has_image_credit()`, `get_image_credit_text()` and `get_image_credit_name()` beside the meta box that edits them. Structured rather than free text because CC-style licences ask for the parts separately.

The markup lives in `[mmm_image_credit]` in `Mixmymeals_Shortcodes` and reads "Creator / Source (Licence)"; the link wraps creator and source only, since a licence is not a place an image came from, and always carries `rel="nofollow"` — unlike the author box, which has a per-post toggle. REST exposes it as `image_credit` (merge semantics, plus a read-only `text` and `has_credit`), MCP as `image_credit` on the recipe/plan tools.

It sits in the templates **outside** the `.mmm-*-feature-image` container, never inside it: those frames have a fixed `aspect-ratio` plus `overflow: hidden`, so a line within them is clipped away invisibly. A negative top margin in the child theme pulls it back up to the picture.

The profile picture travels as `image` (writable attachment ID, `0` removes it) plus a read-only `image_url`.

Writes **merge** — only supplied keys change, `""` (or `0` for `image`) clears a single field, `DELETE` clears the whole box so the post credits its WordPress author again. `Mixmymeals_Post_Meta::save_author_data()` itself is destructive, so always read-modify-write via `get_author_data()`, which returns the *raw* `link_text` (unlike `get_author_link_text()`, which falls back to the URL's host — writing that resolved value back would silently freeze the host into the database). The response carries both: `link_text` (stored) and `link_text_display` (rendered).

### Images over REST

`POST /mixmymeals/v1/media` takes an image from exactly one of three sources — a multipart `file` field, a `url` the server fetches, or an `attachment_id` already in the library — and routes it through core (`media_handle_upload()` / `media_sideload_image()`), so MIME checks, intermediate sizes and metadata behave exactly as in wp-admin. With `post_id` the attachment gets that parent, and `featured: true` makes it the post's featured image in the same call. Requires `upload_files` on top of the namespace's usual `edit_others_posts`.

Recipes and plans carry `featured_image` (attachment ID, `0` removes it) on create/update and in their serialization, alongside a read-only `featured_image_url` — the same `apply_featured_image()` helper equipment already used. The returned attachment ID also works wherever else images are referenced (`steps[].image`, `author.image`).

On the MCP side `mmm_upload_image` reads a local file and posts it as multipart; `url`/`attachment_id` go out as plain JSON.

### Settings

Settings are registered via `Mixmymeals_Settings` using the WordPress Settings API. All option names are prefixed `mixmymeals_`. Notable options: `mixmymeals_public_mode` (allow guest plan creation), `mixmymeals_published_meal_plans_per_hour_limit`, `mixmymeals_save_meal_plans_published_by_visitors_as_draft`, `mixmymeals_placeholder_image_id`.