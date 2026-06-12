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
| `mmm_ingredient` | `Mixmymeals_Ingredient` | Not public, admin only |
| `mmm_ingr_unit` | `Mixmymeals_Ingredient_Amount` | Hidden, child posts of `mmm_ingredient` |
| `mmm_meal_plan` | `Mixmymeals_Meal_Plan` | Public, has archive |
| `mmm_meal` | `Mixmymeals_Meal` | Child post of `mmm_meal_plan` via `post_parent` |
| `mmm_equipment` | `Mixmymeals_Equipment` | Admin only |

Slugs for public post types and their taxonomy archives are configurable in Settings and stored as WP options (`mixmymeals_recipe_slug`, `mixmymeals_meal_plan_slug`, `mixmymeals_meal_slug`). After changing slugs, WordPress permalinks must be flushed.

### Taxonomies

- `mmm_recipe_category` (hierarchical), `mmm_recipe_tag` — for recipes
- `mmm_ingredient_category` — for ingredients (used to group shopping lists)
- `mmm_meal_plan_category`, `mmm_meal_plan_tag` — for meal plans

### Domain model and key meta keys

**Recipe** — steps stored as a serialized array in post meta `_rmm_steps`. Each step has `title`, `description`, `image`, and an `ingredients` array. Each ingredient entry has `id` (ingredient post ID), `amount` (float), and `amount_id` (unit post ID).

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

### Settings

Settings are registered via `Mixmymeals_Settings` using the WordPress Settings API. All option names are prefixed `mixmymeals_`. Notable options: `mixmymeals_public_mode` (allow guest plan creation), `mixmymeals_published_meal_plans_per_hour_limit`, `mixmymeals_save_meal_plans_published_by_visitors_as_draft`, `mixmymeals_placeholder_image_id`.