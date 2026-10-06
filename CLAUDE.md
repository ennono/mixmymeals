# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

MixMyMeals is a WordPress plugin (v3.33.1, text domain `mixmymeals`) for meal planning. It manages recipes, ingredients, meals, and meal plans with a shopping list generator and a frontend cart for building custom plans. Instagram/Pinterest publishing lives in the companion plugin `mixmymeals-social` and reaches this one only through the extension points listed below — this plugin makes no external API calls itself.

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

There is no PHPUnit suite. `tools/diet-test.php` is a script of assertions
covering diets, pinned products and the per-request caches; it runs against a
real install because that is what it tests (terms, meta, products and the
caches between them), creates its own posts and deletes them again:

```bash
cd <site>/app/public
wp --exec="define('DB_HOST','localhost:<socket>');" \
   eval-file wp-content/plugins/mixmymeals/tools/diet-test.php
```

Everything it creates is titled `T …` or `R<n>` and carries the brand `ZZ`, so
a run cut short leaves something recognisable to clean up. PHP 8.0+ is required.

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

`/audit` returns `plugin_version`, and every group the running version knows is always present — empty ones carry a count of zero. So a group that is missing *entirely* means the deployed plugin is older than whatever named it, never a fault in the endpoint. This exists because MCP tool descriptions live on the client and update with the file, while the plugin updates only on deploy, and the two drifting apart has already been reported as a bug more than once.

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

**Equipment's public pages were switched off for years and this table was the only thing that still said otherwise.** `publicly_queryable`, `rewrite` and `query_var` were all `false` from `c3440b2` onward, so the archive, every equipment permalink and even `?post_type=mmm_equipment&p=<id>` answered 404 — while the child theme kept both templates, `[mmm_equipment_hero]`, `[mmm_equipment_index]` and the affiliate-link shortcode kept rendering, the archive-description setting kept saving, and the slug option's flush hooks kept firing for a slug nothing read. Every "Required equipment" link on a recipe pointed into nothing, and a design review of the live site is what finally surfaced it.

Two things to take from it. **`public => true` proves nothing**: it is only the default that `publicly_queryable`, `has_archive`, `rewrite` and `query_var` each override, and all four said no beneath it. And a post type whose front end nobody visits can lose it without a single error — the templates and shortcodes stayed valid the whole time.

Restoring the registration is not enough on an install that has been running: its rewrite rules were written while the type had none, so `Mixmymeals_Post_Types::maybe_flush_equipment_rules()` raises the plugin's own `mixmymeals_flush_needed` flag once (gated on `mixmymeals_equipment_rules_version`), hooked on `init` at 95 — after the registrations at 10, before `maybe_flush_rewrite_rules()` at 99, so the rules are right within the same request.

Slugs for public post types and their taxonomy archives are configurable in Settings and stored as WP options (`mixmymeals_recipe_slug`, `mixmymeals_meal_plan_slug`, `mixmymeals_meal_slug`, `mixmymeals_ingredient_slug`, `mixmymeals_equipment_slug`). After changing slugs, WordPress permalinks must be flushed (the ingredient/equipment slug options schedule a flush automatically).

### Taxonomies

- `mmm_recipe_category` (hierarchical), `mmm_recipe_tag` — for recipes
- `mmm_ingredient_category` — for ingredients (used to group shopping lists)
- `mmm_meal_plan_category`, `mmm_meal_plan_tag` — for meal plans

### Domain model and key meta keys

**Recipe** — steps stored as a serialized array in post meta `_rmm_steps`. Each step has `title`, `description`, `image`, and an `ingredients` array. Each ingredient entry has `id` (ingredient post ID), `amount` (float), `amount_id` (unit post ID) and `optional` (bool — printed as "(optional)" on the recipe, the shopping list, the PDF and the subscription mails. In the four frontend renderings it is wrapped in `span.mmm-ingredient-optional` so a theme can style it — the child theme sets it in italics; the PDF and the mails print it bare, since neither can reach a theme stylesheet; an unchecked box submits nothing and `set_steps()` reads it with `! empty()`, so absence means required).

**A recipe row may also rule products out**: `_rmm_steps[].ingredients[].excluded_products`, a list of ids of that ingredient's own products, validated on save exactly as the pin is — an id hanging off a different ingredient is dropped rather than stored.

**The diet badge is what this is for.** `Mixmymeals_Ingredient::get_diet_suitabilities()` merges the ingredient's diets with those of *every* product it has, so one vegan packet makes the ingredient vegan-capable — that is the point of "capable". But a sweetened soy yoghurt must not make a savoury dish vegan. The method therefore takes an optional `$without` list, passed in per row by `Mixmymeals_Recipe::get_diet_suitabilities()`; the ingredient knows nothing about recipes, so the recipe hands it the exclusion, the same shape as `get_nutrition_pinned()`.

**Stored per row, in force per recipe.** An ingredient can sit in two steps with different lists. `Mixmymeals_Diet::combine()` intersects the rows' sets, so the strictest wins: ruling a packet out in one step and forgetting the other still honours the exclusion. The product comparison reaches the same answer by a different route — `Mixmymeals_Product::add_row_weight()` collects the ids into `$info['excluded']`, a union over the rows, because that table shows one group per ingredient and needs a single answer. Both directions are the forgiving one: a slip never invents a claim nobody made.

`impact_groups()` filters the columns **before** counting them, so an ingredient left with one usable packet drops out entirely — the same rule as an ingredient that only ever had one.

`save_recipe_steps_meta_box()` **rebuilds the posted array key by key** rather than sanitising it in place, so anything it does not name is silently dropped before `set_steps()` sees it — not unsanitised, gone. It lost `optional` (3.25.3) and, for far longer, `image`: every recipe save cleared its step images with nothing to show for it. When a step or ingredient field is added, that callback is the second place to change, and the test is a round trip through the callback, not through `set_steps()` — which handles both keys perfectly well on its own.

The two ingredient markups in `Mixmymeals_Post_Meta` — the rendered row and the hidden `#mixmymeals-ingredient-template` — must carry the **same classes**. `admin.js` names the fields of a cloned row by selecting `ingredient-amount-input` and `ingredient-optional-input`, and the unit-select AJAX inserts after `input.small-text`. A class missing from the template produces a nameless input, which the browser silently omits from the POST: that is how newly added ingredients lost their amount until 3.25.2.

`Mixmymeals_Recipe` also owns the **cook counter** (`_mmm_cook_count`, const `META_COOK_COUNT`): how often the recipe sits on a published plan, measured as meals whose meal **and** parent plan are published. Never incremented — `recount_cook_count()` recomputes from the DB, batched to `shutdown` via `flush_cook_counts()`. Zero = no meta at all, so `[mmm_uncooked_recipes]` is a `NOT EXISTS` query and `[mmm_popular_recipes]` an ordered `meta_value_num` query. Editable in the Details meta box: `set_cook_total()` stores the signed difference to the measurement in `_mmm_cook_count_adjust`, so any value from 0 upwards is honoured exactly and real plannings keep counting on (result floored at 0). One-off backfill via option `mixmymeals_cook_count_version`; `recount_all_cook_counts()` doubles as a repair routine. Hooks are attached once per request via `Mixmymeals_Recipe::register_cook_count()` in `mixmymeals.php` — note they watch **meal and plan** events (`wp_after_insert_post`, `transition_post_status`, meta writes, `before_delete_post`), since the counter belongs to a recipe but every event that changes it happens elsewhere.

`Mixmymeals_Recipe` also owns the **edit counter** (`_mmm_edit_count`, const `META_EDIT_COUNT`): `get_edit_count()` / `set_edit_count()`, fed by the static `count_edit_on_update()` on `post_updated` (skips creation from auto-draft, autosaves and revisions). Hooked up once per request via `Mixmymeals_Recipe::register_edit_count()` in `mixmymeals.php`, same pattern as `Mixmymeals_Cart::register_ajax()`. Deliberately **not** built on post revisions: WP only versions title/content/excerpt (`_wp_post_revision_fields()`), so meta-only recipe edits — steps, ingredients, times — create no revision at all. Displayed via `[mmm_recipe_edit_count]`.

**Several read paths are memoised for the request, and every one of them needs a way back out.** `Mixmymeals_Recipe::calculate_nutrition_per_serving()` and `calculate_cost_per_serving()` keep per-recipe statics that `set_steps()` clears; `Mixmymeals_Meal_Plan::get_meals()` keeps one per plan that `register_meal_cache()` clears from `wp_after_insert_post` and the delete/trash hooks, because meals are created from the REST builder, the cart and wp-admin alike. `Mixmymeals_Diet::for_post()` keeps its own and `set_for_post()` updates it. The shape that breaks without this is ordinary rather than exotic: one request writes and then serialises what it wrote, which is exactly what `/meal-plans/build` and every REST update do — both bugs were found by `tools/diet-test.php`, not in production.

**Shelf life is two fields, not one.** `_mmm_shelf_life_days` exists on both `mmm_recipe` and `mmm_ingredient` (`META_SHELF_LIFE` on each class), and the two hold different quantities: how long the finished dish keeps, and how long the raw ingredient keeps. Cooking moves that number in both directions — a stew of week-old vegetables keeps three days once cooked, a salad of the same vegetables keeps one — so neither may ever fall back to or be derived from the other.

`get_shelf_life()` returns `?int`: **null when nobody has said, 0 when the answer is zero.** That distinction is the whole point of the field, since "eat it the day it is made" is exactly the case worth recording — and it is why the existing numeric fields were not copied: `set_price_per_100g()` deletes on 0, and `get_preparation_time()` maps both cases to 0 **on purpose** — a dish taking no time is not an answer anyone means, so there the absence of the row carries "not stated" (see *Findability*). The admin input prints no value for null, so an untouched field cannot save itself as a zero, and the save handler tests the raw string before `absint()`, which cannot tell `""` from `"0"`.

Over REST and MCP it is `shelf_life_days` on recipes and ingredients, typed `array( 'integer', 'null' )` with `minimum => 0`. It is **serialized as `null`, never omitted** — an absent key and a null one look the same to a careless client, and the distinction this field exists for would be lost on the wire. Sending `null` clears the value; `absint()` is applied only after the null check, since it maps null and 0 to the same thing.

**Storage is meta with a closed vocabulary, not a taxonomy.** `Mixmymeals_Post_Types::storage_types()` owns the four conditions (`room_temperature`, `cool_dry`, `refrigerated`, `frozen`) once for both post types; `_mmm_storage_type` holds the choice on each, with `get_storage_type()` / `set_storage_type()` and `is_storage_type()` guarding it. A taxonomy was rejected deliberately: this is advice about keeping food safe, and editors would grow "Kühlschrank", "kühl", "kalt" and "Kühlung" side by side — four spellings of one condition is worse than none. Nothing here is a browsing entry point either.

Validated on **read as well as write**, so a row written past the class cannot put an unknown word on a public page, and an unknown value clears the field rather than being stored — the rule `Mixmymeals_Product::set_diet_type()` keeps, for the same kind of claim. Over REST and MCP it is `storage_type`, enumerated from the same list.

**`POST /keeping/bulk` (MCP `mmm_set_keeping`) writes both fields for many posts at once**, recipes and ingredients mixed, resolved per id. It exists because the single-item routes answer with the item's whole serialization: one pass over a library of 372 ingredients and 144 recipes cost 516 calls at roughly 500 tokens each. The bulk answer is `id`, `type`, `ok` plus a separate error list — about 12 tokens per item, and 100 items write in under 20 ms.

**Omitting a key and sending null are different instructions**, and this is the trap the endpoint exists to avoid: an absent key leaves the stored value alone, `shelf_life_days: null` and `storage_type: ""` clear it. A caller filling in shelf lives would otherwise wipe every storage type it did not mention. An item naming neither field comes back as `no_fields` rather than a silent success, so a caller who mistypes both keys does not read 500 confirmations of nothing.

Failures are per item (`not_found`, `wrong_type`, `missing_id`, `no_fields`) and the batch continues — but an invalid `storage_type` **rejects the whole request** at schema level, before anything is written. That asymmetry is deliberate: a missing post is a data condition, an unknown enum value is a caller bug, and half-applying a batch built on a wrong mapping is worse than applying none of it.

**Shelf life and storage are rendered as a chip in the hero**, not as a line under the steps — `Mixmymeals_Shortcodes::keeping_chip( $post_id, $base )`, beside `disclosure_chip()` and following the same pattern: it takes the surrounding row's base class and joins `mmm-chip` in `mmm_recipe_hero` and `mmm_meal_hero`, last in the row because it describes what happens after the eating. The meal resolves through `Mixmymeals_Meal::resolve_credit_source()`; a plan has no shelf life and gets nothing. Returns '' when neither field is set.

**Both facts go into one chip, never two.** "4 Tage" beside a row of stats is a duration with no subject — a plan chip reads "7 Tage" and means the plan's length — and a condition without a duration is half an answer. The wording is per case and translated whole: "4 Tage im Kühlschrank", "4 Tage haltbar" (no storage), "Am selben Tag, im Kühlschrank", "Am selben Tag", and the bare noun ("Kühlschrank") when only storage is known — that last one takes the noun from `storage_types()` rather than the phrase, since there is no sentence around it to carry the preposition.

There was a `[mmm_recipe_keeping]` shortcode printing a sentence under the cooking instructions (3.31.0–3.32.0). It is gone: a full sentence sitting alone after the steps read as the beginning of a section that never arrived, whereas the hero already has a row of facts about the dish and this is one of them.

**A shelf life of 0 reads "Am selben Tag essen", never "0 Tage"**: a zero among numbers looks like a missing value, and that is the one case a reader must not misread.

It belongs to the shelf life beside it: "keeps 3 days" is not an answer without the condition it keeps under, which is also why storage is a **single** value — one shelf life cannot describe both a fridge and a freezer.

**Ingredient** — nutrition stored per-key in post meta: `_rmm_calories_100`, `_rmm_fat`, `_rmm_carbs`, `_rmm_protein`, `_rmm_sugar`, `_rmm_fiber`, `_rmm_salt`. Units are child posts (`mmm_ingr_unit`).

**`Mixmymeals_Ingredient::get_nutrition()` and its `_pinned()` sibling are the only places ingredient nutrition may be read.** All nineteen call sites go through it — recipe pages, plan pages, the PDF, subscription mails, the calculator, the shopping list — and `Mixmymeals_Recipe::_calculate_total_nutrition()` does too. The `_rmm_*` keys are touched directly only inside `Mixmymeals_Ingredient` itself and where the REST API registers them. Keep it that way: a caller reading the meta directly would bypass anything the getter later resolves through (a product override, a unit conversion) and put a second, disagreeing figure on one of those channels. The same rule holds for `Mixmymeals_Post_Meta::get_author_data()` and `Mixmymeals_Ingredient_Amount::get_step_divisor()`, and for the same reason: several of this codebase's worst bugs were two channels computing one number separately.


**A recipe row may name the product it is cooked with**, and that choice outranks the ingredient's reference wherever it applies. `_rmm_steps[].ingredients[].product_id`, edited in the step's ingredient row and exposed over REST and MCP as `product_id`. Three getters resolve it, all through one decision — `Mixmymeals_Ingredient::effective_product_id( $ingredient_id, $pinned )`, which prefers the pin, falls back to the reference, and treats a product hanging off a *different* ingredient as nothing pinned:

| Getter | Resolves | Called by |
|---|---|---|
| `get_nutrition_pinned()` | figures per 100 g | recipe/meal/plan nutrition, the PDF, the nutrition tables |
| `get_price_per_100g_pinned()` | price per 100 g | `calculate_cost_for_weight()`, and so every cost chip |
| `get_diets_pinned()` | diet claims | `Mixmymeals_Recipe::get_diets()` and everything downstream of it |

The three parameterless getters are now thin calls to these with 0, so a pinned and an unpinned row cannot resolve differently — the same reason `get_nutrition()` and `get_nutrition_with_product()` share one merge.

**The two levels answer different questions and only one of them is a statement about the dish.** The reference product is the site's default for an ingredient's figures; the pin is the recipe saying *this dish is made with that packet*. So the recipe's and the meal's ingredient lists print only a pin (`span.mmm-ingredient-product`), while the shopping list prints either — there the question is what to buy, and the reference has always answered it.

**The shopping list names a packet only where the whole plan agrees on one.** `calc_shopping_list()` collects the effective product of every row and puts `product_id` / `product` on the aggregated row; two recipes pinning different packets of one ingredient yield 0 and '', because naming either would send the shopper home with the wrong article for the other. The three renderings read that field instead of calling `Mixmymeals_Product::reference_label()` themselves.

**An ingredient the recipe has settled drops out of the product comparison.** That table asks which packet an ingredient should be, and a pinned row is the author's answer — leaving it in would invite the reader to redo a decision that is part of the dish, next to figures that already assume it. `impact_groups()` skips a group whose `open` grams are zero. A plan can be half decided, one dish naming a packet and another not; then the question is still live for the second and the group stays, anchored on the blend. The two questions are kept apart by two methods: `pinned_product_id()` answers *did the recipe decide* (the reference product does not count — it is the site's default for the ingredient, not a statement about this dish), `effective_product_id()` answers *which packet supplies the figures*.

**The product comparison anchors on what is actually in use.** `impact_groups()` takes an optional `products` breakdown (grams per product) per ingredient: one entry anchors the table on that packet, several mean no column carries absolutes and the differences are measured against `blend_nutrition()`, the gram-weighted mix the plan really contains. That keeps the "difference rather than re-run" arithmetic exact — the total moves by (product − mix) × grams whatever the mix was made of — and it is why the weights arrays grew that key rather than the callers picking one packet and being wrong about the other.

**Product** (`mmm_product`, `Mixmymeals_Product`) — a concrete, branded article an ingredient can be bought as. Child of the ingredient via `post_parent` and hidden like `mmm_ingr_unit`, because it is a detail of its parent rather than a page. Meta: the seven nutrition keys under `_mmm_product_*`, plus `_mmm_product_package_weight` (grams per packet), `_mmm_product_brand` (**required** — the admin field carries `required` for existing rows but never for the always-rendered blank one, which would make the ingredient unsaveable; that row is enforced on save instead, and `POST /products` requires it too so automation cannot create rows the admin screen then refuses. An update with an empty brand keeps the stored one: blanking is a loss, not an edit) and `_mmm_product_source`. Edited in the Products meta box on the ingredient screen (`render_ingredient_products_meta_box_content()` / `save_ingredient_products()`), which carries its own nonce and its own `save_post_mmm_ingredient` hook so a screen that does not render it cannot wipe the products by not posting them.

Over REST: `POST /products`, `GET|POST|DELETE /products/{id}`, `GET /ingredients/{id}/products` and `GET /product-impact?post_id=` (recipe → per serving, plan → per day and person, meal → resolved to its recipe). The serialized **ingredient** carries `reference_product`, `reference_product_label`, `product_count` and `own_nutrition` / `own_diet_type` beside the resolved `nutrition` / `diet_type` — without them a client reads 131 kcal with no way to tell whether the ingredient or a packet said so. `own_*` is a labelled view of the fallback layer, never "the" nutrition. `/audit` gained `ingredients_products_without_reference` and `products_without_source`.

MCP mirrors all of it, plus `mmm_audit`, which had no tool at all although `/audit` is the endpoint that finds exactly the gaps an agent is there to fill. `nutrition` is an object of the seven keys — only supplied keys are touched and `null` deletes one, so a client can withdraw a figure instead of only overwriting it, and the serialization keeps nulls for the same reason: null and 0 are different claims about somebody else's product. `is_reference` sets or clears the ingredient's pointer. MCP mirrors these as `mmm_get_ingredient_products`, `mmm_get_product`, `mmm_create_product`, `mmm_update_product`, `mmm_delete_product`, `mmm_product_impact` and `mmm_audit`.

**Which recipes chose a product is answered the way it is for units.** `Mixmymeals_Product::usage_map()` is one pass over every recipe, memoised per request, tallying `_rmm_steps[].ingredients[].product_id`; `get_usage()` and `count_usage()` read it, and the products box prints the recipes as shortened edit links in the detail panel beside the source. It sits there rather than in the identity row because a list of links is taller than an input and would drag the whole flex row down. The two maps cost little side by side despite both walking every recipe: `get_steps()` is a plain `get_post_meta`, so the second pass reads what the object cache already holds.

One renderer serves both, `Mixmymeals_Post_Meta::usage_list()` (formerly `unit_usage_list()`, with its `mmm-usage__*` classes) — a unit and a product are both rows an editor may remove from an ingredient, and the question before removing either is the same.

**It counts pins only.** The reference product feeds every recipe that uses the ingredient without pinning anything, but that is a fact about the *ingredient*, not about the packet — the same line the "used for the nutrition figures" chip draws. Folding it in would hand one product a number belonging to its parent, so the reference instead gets a sentence saying it supplies the figures wherever nothing was chosen. Where nothing is pinned the heading is dropped entirely: the list already says "not used", and "chosen in 0 recipe lines" above it is the same nothing twice.

`[mmm_ingredient_products]` lists them on the ingredient page, one column per product against the seven nutrition rows — the transposed shape, because seven figures as columns push protein and salt off the page, which are the two a reader compares yoghurts by. Deliberately **without** the `mmm-nutri-table` class every other nutrition table carries: the theme collapses that into one card per row below 544px and hides the table head to do it, and here the head carries the brand, the package size and the source. It scrolls sideways in its wrapper instead, with the label column pinned via `position: sticky`.

**The table is relative, not seven columns of absolutes.** One product anchors it and keeps its own figures; every other column states the difference (`+12,0 g`, `−30 kcal`, `±0` when it rounds away). `pick_products_baseline()` picks the reference product, or failing that the lowest-energy one — a stable choice, unlike "the first entered", which would silently re-anchor the whole table the day someone adds a product — and the anchor is sorted to the front. `format_nutrition_delta()` returns a dash unless **both** ends are stated: treating an absent value as zero would not merely invent a number, it would invent the largest difference in the table and attribute it to somebody else's product. Signed with U+2212, not a hyphen, which in a column of figures reads as the dash that means "not stated".

Only the reference carries a chip (`Used for the nutrition figures`), because that is a claim about the *ingredient* — its figures come from that packet. Which column anchors the arithmetic is not a property of the product, and the note under the table names it in a sentence; as a chip beside a brand name it read as a distinction the brand had earned. Differences carry **no colour** for the same reason: more protein and more salt would take the same green, and a reader would take that for a verdict on a brand.

The heading cells are built from `div`s, not `span`s styled into blocks: a site serving a stale `frontend.css` ran the name, the brand and the source together into one unreadable string, and a stack that basic must not depend on a stylesheet arriving. The source sits in the heading cell with the product it belongs to, as "Quelle: …", capped at `max-width: 22em` — a real one runs to a full sentence, and uncapped it sets the width of its whole column and squeezes the figures in every other one.

A value the product does not state renders as a dash, never `0,0 g` — the same null-is-not-zero rule the storage layer keeps, and the place it matters most, since this is a public claim about somebody else's product. The reference product is marked, in a muted chip rather than the green of `.mmm-badge`: green is the brand's positive tone and a quality mark beside a brand name is what this must not read as.

**One method owns "per day and person".** `Mixmymeals_Meal_Plan::calc_nutrition_per_day_person()` averages over the days that **carry meals**, never over the plan's length — a plan with two empty days would otherwise look far lighter than anything anyone eats. A day counts by `meals`, **not** by whether it has energy: counting by energy (as this did until 3.12.2) dropped days whose recipes have no nutrition data yet, so filling one in could *lower* the average, and it tied the divisor to the very figure a product choice changes — a day at zero kcal that a product lifts above zero joins the divisor and `calc_product_impact()` promises a number the plan never reaches. It returns the average, the totals it came from (the PDF prints those as its total row) and the day count (the subscription mail suppresses its weekly block below two days). The plan's nutrition overview, the mail, the PDF and the product comparison all call it. The rule had been written out four times, and the fourth used the wrong divisor: on a seven-day plan with meals on three days, one page stated 51 and 120 kcal for the same figure. Never recompute it locally.

**The comparison lives in one place and is asked twice.** `Mixmymeals_Product::impact_groups( $weights, $current, $divisor )` does the arithmetic; the caller supplies the grams one person eats per ingredient, the figures those grams currently produce, and what to divide by. `Mixmymeals_Meal_Plan::calc_product_impact()` passes the plan's days, `Mixmymeals_Recipe::calc_product_impact()` passes 1.0 for a single serving. One private renderer in `Mixmymeals_Shortcodes` draws both, parameterised on a single column heading — so `[mmm_meal_plan_product_impact]` and `[mmm_meal_product_impact]` differ in their unit and in nothing else. `[mmm_meal_product_impact]` resolves a meal to the recipe it plans and answers for a recipe directly.

Per serving on a dish is not a cosmetic choice: a plan spreads one meal over its whole length, so a difference of 28 kcal per 100 g on 150 g of an ingredient arrives as six kilocalories a day and reads as nothing. On the dish it is the full 42.

**One column anchors each table and carries absolutes; the rest carry differences to it.** The anchor is the reference product where there is one — the packet actually in use — and otherwise a `Typical values` column of the ingredient's own figures, which is what the dish is computed from when no product is chosen. Either way the anchor holds exactly the current figures, which is why there is no separate "as planned" column: it would repeat what the leading column already says, and the reference would sit beside it as a row of ±0.

**`[mmm_meal_plan_product_impact]` puts a price on the choice.** For every ingredient of a plan with more than one product, `Mixmymeals_Meal_Plan::calc_product_impact()` gives all seven figures per day and person: one column for the plan as it stands, one per product for the differences. The product currently supplying an ingredient's figures is marked with an asterisk carrying the class `mmm-product-impact__ref-mark`; what it means is a legend, and legends are page copy — the child theme prints one under the tables, and only when that class actually occurs, since an ingredient without a reference earns no asterisk and a legend for a marker that is nowhere on the page is worse than none. The child theme renders it under the shopping list, where the buying decision is, and owns the heading, the sentence introducing the tables and that legend — the shortcode emits tables only, so wording and translation sit together in `single-mmm_meal_plan.php` under the theme's own text domain.

All seven and not only energy, because two hams can carry the same kilocalories and twice the salt — weighing energy alone dropped that ingredient from the list and hid the one choice worth making. For the same reason the ranking is by the largest spread **relative to `reference_intake()`**: it is the only way to compare 80 kcal with 1,2 g of salt without one unit simply having bigger numbers. Whether a figure counts as moved is decided by `nutrition_value_is_printable()`, which compares rendered strings rather than an epsilon, since precision differs per nutrient and the only difference worth the name is one the reader can see.

It is computed as a **difference**, not by re-running the plan once per product. Nutrition is linear in weight, so `(product kcal − current kcal) × grams eaten ÷ 100 ÷ days` is exact — but only because `days` does not itself depend on the nutrition, which is why the divisor counts meals rather than energy (see above). Any future change that makes the day count depend on a figure a product can move breaks this method silently: the numbers stay plausible and stop being true — verified against actually writing each product to `_mmm_reference_product` and recomputing, to the second decimal. The alternative would have been a bypass parameter on `get_nutrition()` (the hole closed in 3.8.2) or writing to the database to ask a hypothetical.

Two supporting pieces carry the guarantee. `Mixmymeals_Ingredient::get_nutrition_with_product()` answers "what would this ingredient read with that packet", sharing one private `apply_product()` merge with `get_nutrition()` — if the two ever merged differently the prediction would stop predicting. Unlike the removed `$resolve_product`, it must name a product, so it cannot be mistaken for the current figure. `Mixmymeals_Meal_Plan::get_ingredient_portion_weights()` aggregates grams the way `calc_nutrition()` does (one portion per meal, `get_ingredients_scaled( 1.0 )` ÷ servings) and explicitly **not** the way `calc_shopping_list()` does (scaled by meal servings, rounded up to whole packs) — mixing them would count rounding-up as something somebody ate. Note that `get_ingredients_scaled()` returns **one row per step**: every row's grams are added, since that is what is in the dish, but the meal is counted once, or a recipe seasoning in three places reports itself as three meals.

**The comparison carries a `Diet` row**, above the figures, because "can I still call this vegan" is a different question from "how much fat" and the one people scan for. The leading column states what is **in force** (`Mixmymeals_Ingredient::get_diet_type()`, already resolved), every other column what that **packet itself** states — a product silent on the question gets a dash even though the ingredient's classification would stand, since printing it in that column would read as the brand claiming it. The row is omitted where nothing is classified anywhere in the group (`has_diet`), rather than printing dashes across.

**A figure the product does not state takes no part in this comparison** — dash in the cell, excluded from the spread that ranks the ingredients and from the test that decides whether a group is shown. No substitute is used anywhere.

The history is worth keeping because both sides of it are arguable. 3.11.0 dashed such figures; 3.12.0 replaced the dash with the *real* difference, on the ground that a product silent on fat still moves the plan's fat when the one it replaces stated some — the merge falls back to the ingredient's average and somebody would actually get that. 3.13.0 went back to the dash on the ground that the number, while true of the plan, is false of the packet, and this table is about packets: a product that says nothing about fat must not have a fat figure attributed to it. That is the settled position. The fallback itself is untouched in `get_nutrition()`, so recipes and plans still resolve a complete set of figures; only this table declines to speak for a silent packet.

The two levels answer different questions. An ingredient carries figures good enough to plan with and stays correct when a manufacturer changes a recipe; a product carries what one packet says. Worth maintaining only where products differ a lot — yoghurt at 0.1 % or 10 % fat, minced meat, cheese, bread. For red cabbage the gap between 25 and 41 kcal is smaller than the error from cooking losses and portion size.

**Writing a classification clears the legacy meta; only the migration keeps it.** `Mixmymeals_Diet::for_post()` falls back to `_mmm_diet_type` / `_mmm_product_diet_type` whenever a post carries no terms, which is what lets an un-migrated install keep working. The same fallback made a classification impossible to *delete*: removing the terms brought the old value back on the next read, from a row no screen shows. `set_for_post()` therefore deletes that row — on an empty write and on a non-empty one alike, since two sources can only disagree afterwards — and takes `$keep_legacy = true` for exactly one caller, `POST /diets/migrate`, whose whole point is to leave the way back intact.

Note what this means for `own_diet_type` in the serialized ingredient: it is the ingredient's *own* classification as opposed to the one resolved through its reference product, and it reads the taxonomy. It used to read the legacy meta directly, so after migration it answered `""` for every ingredient whose classification had become a term.

**What diets exist is the taxonomy `mmm_diet`, and it is hierarchical — the tree is the rule.** A child term implies every ancestor: "Vegan" under "Vegetarian" under "Pescetarian" says that a vegan dish suits a vegetarian and a pescetarian, and nothing else has to be configured. `Mixmymeals_Diet::types()` assembles the definitions from three layers — the four built-ins in code (`builtin_types()`), every term of the taxonomy (`terms_as_types()`), then the `mixmymeals_diet_types` filter — and `normalise()` makes the result consistent: `implies` is closed transitively (`expand()` looks one level deep and relies on it) and `rank` is lifted so a diet always outranks what it implies on its own axis, whatever numbers were typed. The result is memoised per request; `reset_types()` clears it and the term / term-meta hooks call it.

What a bare term cannot hold lives in term meta: `_mmm_diet_axis` (which question it answers; empty inherits from the nearest ancestor, and a root with a shipped diet beneath it takes that diet's axis, so "Pescetarian" above "Vegetarian" is on the `diet` axis without anyone typing it; otherwise the term's own slug, i.e. a question of its own), `_mmm_diet_rank` (empty = by depth) and `_mmm_diet_schema`. The fields are on the term form (`register_term_admin()`); empty means "inherit" and deletes the row.

**Built-ins win over their terms.** Label, axis, rank and schema URL of vegan / vegetarian / gluten_free / lactose_free come from code: they carry the translations and the structured data, and renaming "Vegan" on the taxonomy screen must not change what a public label means. Moving a built-in in the tree only *adds* implications. `ensure_terms()` creates them parents-first and seeds Vegan under Vegetarian once (`mixmymeals_diet_hierarchy_version`), only for a term still at the root.

**An invented term cannot produce or displace a "vegan" label.** It implies only what its place in the tree says and, unless told otherwise, answers a question of its own. This replaces `reject_unknown_term()` (and its `pre_insert_term` hook), which used to refuse every term `types()` had not heard of; with the terms *being* the registry there is nothing left to refuse. The cost: placing a term under "Vegan" is itself the statement that it implies vegan.

Changing the tree changes every recipe's answer, so `edited_term` and the term-meta hooks queue a reindex of all recipes (`Mixmymeals_Recipe::index_on_term_deleted()`, same path as deleting a term). `/rezepte/<slug>/` archive views are built from `types()` too, so a new diet gets a path — **check existing recipe slugs before adding one.** The REST `diet` search parameter still accepts only `vegan` / `vegetarian` (it compares the strongest diet of the `diet` axis); the bulk and write routes accept any term slug.

**Every reader of a diet label goes through `Diet::label()`, never a map of its own.** Two call sites briefly didn't — the ingredient hero chip and the product comparison's diet row each carried a small `array( 'vegan' => __( 'Vegan' ), 'vegetarian' => __( 'Vegetarian' ) )` that a diet added through the filter would not appear in, even though `get_diet_type()` already resolves to it. Fixed once in `Mixmymeals_Shortcodes`; a third such map is the bug to watch for.

**The diet classification resolves through the reference product too.** `_mmm_product_diet_type` (`Mixmymeals_Product::META_DIET_TYPE`, `get_diet_type()` / `set_diet_type()`) holds `vegan`, `vegetarian` or `''`, and `Mixmymeals_Ingredient::get_diet_type()` prefers it over the ingredient's own. A brand property, not an ingredient one: pasta is vegan until a manufacturer puts egg in it, cheese vegetarian until one uses animal rennet. It overrides in **both** directions — a product certified vegan where the ingredient was cautiously marked vegetarian is the more accurate statement, and refusing it would keep a correct label off the page. `set_diet_type()` discards anything that is not one of the two known values rather than storing it, because this field decides whether a public "vegan" label appears.

Resolved in `Mixmymeals_Ingredient::get_diet_type()` because that is the single place an ingredient's classification is read, so `Mixmymeals_Recipe::get_diet_type()`, the badges, `suitableForDiet` in the schema and the search filters all follow. **Note the consequence**: setting a reference product can change what a recipe is publicly labelled, including making it vegan. Unlike a kilocalorie, that is a claim people act on — the label is only as good as the product row behind it.

**The ingredient resolves through its reference product** (`_mmm_reference_product` on the ingredient, managed by `Mixmymeals_Product::get_reference_id()` / `set_reference_id()`). `Mixmymeals_Ingredient::get_nutrition()` merges the two **key by key**, product first: a packet often states energy and fat and nothing else, and discarding those two because the other five are missing would be the wrong trade. `weight_item` is never overridden — it describes the ingredient (one medium onion), not a packet. Because that getter is the single read path, every channel follows without knowing products exist.

`Mixmymeals_Product::label()` is how a product is named on a shopping list (`reference_label( $ingredient_id )` is the same thing looked up from an ingredient's reference) — brand first ("Barilla Penne Rigate"), falling back to the title alone for products from before the brand became required, and empty when nothing was chosen. All three renderings of the list print it, via the `product` field `calc_shopping_list()` puts on the row: the plan page, the PDF and `Mixmymeals_Subscriptions::render_ingredient_list()` (which also serves the daily list in mails). **Not** the Bring! export: those lines are parsed by Bring! itself and a brand would change what the app splits on.

**Products carry their own price, and the ingredient's price resolves through the reference product too.** `_mmm_product_price` (`Mixmymeals_Product::META_PRICE`) holds the price of **one package** — the number on the shelf label — and `get_price_per_100g()` derives the rest from `_mmm_product_package_weight`, returning null unless both are stated. `Mixmymeals_Ingredient::get_price_per_100g()` prefers that over the ingredient's own figure, and since `calculate_cost_for_weight()` is the only thing recipe costs read, every price chip follows. Edit screens and the JSON export read `get_own_price_per_100g()` instead: they write the field back, and showing the resolved figure would copy a product's price into the ingredient on the next save. Over REST and MCP the product carries `price` (null clears) and a read-only `price_per_100g`; the ingredient gains `own_price_per_100g` beside the resolved `price_per_100g`.

**The products box offers the ingredient itself as a reference choice.** Each product row carries a radio in the group `mmm_products_reference`; above the table sits one more with the value `0`, "Use the ingredient's own nutrition and price". Without it the ingredient's own figures were unreachable the moment any product had been picked — a native radio cannot be unticked — although `save_ingredient_products()` has always read an absent or zero value as "no reference". `tools/reference-product-test.php` covers the round trip.

`get_reference_id()` re-validates on every read and returns 0 when the stored product was deleted or now hangs off a different ingredient, and `set_reference_id()` refuses a product belonging elsewhere. `set_nutrition()` deletes a key given as null or '' rather than storing zero: "not stated on the packet" and "contains none" are different claims, and the second is a claim about somebody else's product. `_mmm_product_source` exists for the same reason — these figures go stale when a recipe changes, and without a note saying where a number came from nobody can tell a current one from a four-year-old one.

**Ingredient_Amount** (`mmm_ingr_unit`) — child post of an ingredient. Meta: `_mmm_ingr_unit_weight` (g per unit), `_mmm_unit_divisor` (how finely the unit may be split).

`_mmm_is_sale_unit` is **gone**. It once picked "the nearest fitting sales unit" for the shopping list (`5d14a86`); the needs-based rewrite (`c3440b2`) dropped that code and left the field behind, after which nothing read or wrote it for years while `tools/mcp-server.mjs` kept offering it and posting it into an endpoint that discarded it. Installs from that era may still carry orphan `_mmm_is_sale_unit` rows — inert, and deliberately not deleted. What the field was for is answered today by the unit itself: `unit_is_counted()` turns a unit with a weight into "1,5× Dose" in the shopping list.

**Divisibility is one number.** `_mmm_unit_divisor`: 1 = whole ones only, 2 = halves, 4 = quarters, absent = any amount. `get_divisor()` reads it and `get_step_divisor()` is the same value under the name calculations use. It used to be two settings — this divisor plus a `_mmm_is_indivisible` boolean — which gave "whole ones only" two representations, forced every writer to reconcile them, and made entering 1 mean the *opposite* of what it reads like, because the divisor only counted from 2 upwards. `is_indivisible()` survives as a derived accessor (`1 === get_divisor()`) and `set_indivisible()` as a wrapper, because both the REST API and MCP still expose the field; note that `set_indivisible( false )` clears the restriction entirely rather than only the "whole ones" part.

`get_divisor()` falls back to the old boolean for units the migration has not reached, so an install part-way through an upgrade never treats its eggs as divisible. `migrate_indivisible()` moves the rows once, gated on `mixmymeals_unit_divisor_version` and attached on `admin_init` from `mixmymeals.php`.

The two call sites round in opposite directions on purpose: `Mixmymeals_Recipe::get_ingredients_scaled()` rounds to nearest, because scaling four servings down to three must not silently add a tin, while `Mixmymeals_Meal_Plan::calc_shopping_list()` rounds **up**, once over the aggregated requirement — at the shop too little is worse than too much, but buying two tins for two half-tin recipes is the error that aggregation exists to prevent. The arithmetic runs on the integer multiple (`round( $amount * $d ) / $d`) so thirds do not drift into 0.33333.

**Deleting a unit that is in use corrupts recipes silently.** A step keeps its amount and loses its unit, and `get_ingredients_scaled()` reads an amount without a unit as grams — "1,5 Dosen" becomes 1,5 g, on the recipe, in every plan using it and in the shopping list. So `GET /units/{id}/usage` reports the recipes, the number of affected lines in each, whether it is the ingredient's default unit, `can_delete`, and the consequence in words; `DELETE /units/{id}` refuses with 409 while it is used and needs `force=true`. `POST /units/bulk-delete` does the same for a list — POST rather than DELETE because a request body on DELETE is poorly supported by too many clients — judging each id on its own so a batch is not lost to one unit that turned out to be in use, and offering `dry_run` alongside `force`. Its `affected_recipes` count is the one to read before forcing: it is how many recipes would silently start reading their amounts as grams. Feed it the ids from the audit's `units_unused`. Both read `Mixmymeals_Ingredient_Amount::get_usage()`, which `get_amount_usage_count()` (the admin box) now derives from as well — two scans of the same thing would have drifted. `get_usage()` is itself a lookup into `usage_map()`, one pass over every recipe cached for the request: asking each unit separately opens every recipe once per unit, which the audit's `units_unused` group would turn into a cross product. A unit counts as used even when only a **draft** recipe references it; the scan runs on `post_status => any`, because a draft is a recipe somebody is still writing.

Over REST a unit carries `divisor`, a read-only `step_divisor` and the older `is_indivisible`; MCP exposes `divisor` on `mmm_create_ingr_unit` / `mmm_update_ingr_unit` and keeps `is_indivisible` marked deprecated.

**Meal** — child post of a meal plan. Meta: `recipe_id`, `servings`, `mmm_meal_type` (breakfast/lunch/dinner/snack/other), `_mmm_day` (1–7).

**A meal carries its own short text**, in `post_excerpt` — `Mixmymeals_Meal::get_text()` / `set_text()`. The same dish is planned for different reasons ("cook double, freeze half", "the evening before the shopping trip"), and that belongs to the planning rather than to the dish. It **never falls back to the recipe's excerpt**: borrowing it would put the recipe's own line on every planning of it and read as if somebody had said it about this one.

`supports` deliberately still omits `excerpt`, although the field lives there. Listing it would add core's Excerpt box beside the plugin's own field on the meal screen — two controls writing one value, the race the diet meta box was removed over. Editable in the Meal Details box (`mmm_meal_text`), over REST as `text` on `GET|POST /meals`, `/meals/{id}` and inside the `meals[]` of `/meal-plans/build` and `/meals/bulk`, and through MCP on `mmm_update_meal`, `mmm_build_meal_plan` and `mmm_add_meals`.

**Visitors cannot set one.** The cart has carried a per-item `note` for years whose transfer to the meal is commented out, and it stays that way: the planner is for choosing dishes, not for writing on somebody else's plan. `tools/meal-text-test.php` asserts it against the *comment-stripped* source, since the line is in the file and a plain search would pass on a comment.

It replaces a `note` meta whose getter, setter, admin field and save branch were **all** commented out — one field short of existing, and invisible for as long as it had been there. `Mixmymeals_Meal::migrate_notes()` moves any leftover row into an empty text once, gated on `mixmymeals_meal_text_version`, and leaves the meta in place as the way back.

Rendered in two places and no more: `[mmm_meals_in_a_single_meal_plan]` puts it under the card title as `.mmm-recipe-card__note` (the one thing on that card that is not true of the recipe in general), and `[mmm_meal_hero]` prints it as `.mmm-plan-hero__lead` — the recipe hero's own place and class, because a meal page is a recipe page with a serving size and a reader arriving from the plan expects the same shape.

**Meals are edited in the plan's own screen**, in the Meals box (`render_meal_plan_overview_meta_box()` / `save_plan_meals()` in `Mixmymeals_Post_Meta`) — the same shape as the units table on an ingredient, and for the same reason: a meal is a detail of its plan, and moving one to another day should not mean opening a screen of its own. The meal keeps that screen for what the table leaves out. Its own nonce and its own `save_post_mmm_meal_plan` hook, per the products-box rule above.

The day select offers the **plan's own days, in the plan's own order** (`get_day_range()`, so a plan crossing Sunday reads 6, 7, 1, 2) — plus, for a meal already sitting outside that range, its own day, or saving the table would silently move it. Weekday wording comes from `Mixmymeals_Meal_Plan::weekday_name()`; there is no second vocabulary.

Two rules the handler keeps that the setters do not: an **empty recipe keeps the stored one** (blanking is a loss, not an edit — the way to remove a meal is the delete box in its row), and an unknown meal type is discarded, since `Mixmymeals_Meal::set_type()` stores whatever it is given. Deletion is `wp_delete_post( …, true )`, not the trash: a meal has no life outside its plan.

**The new row carries a one-shot token** (`mmm_plan_meals[new][token]`, spent through a day-long transient). The block editor posts the meta boxes without re-rendering them, so a second Save sends the still-filled new row again and plans the same meal twice — verified before the guard existed. Any table of this shape that creates rows needs one; the units box on the ingredient has the same gap.

Round trips through the callback are in `tools/plan-meals-test.php` (not distributed), the way the recipe-steps lesson above asks for.

**The meal has no entry in the admin menu** (`show_in_menu => false`): a list of every meal of every plan sorts by nothing that matters and each row means nothing without its plan, and the box is now the way in. `show_ui` stays **true**, because that box links each row to the meal's own screen — and `parent_file` is filtered (`highlight_meal_parent_menu()`) so Meal Plans stays lit while a meal is open, or the whole menu goes dark on the one screen whose only route in is a plan. `show_in_admin_bar` follows `show_in_menu`, so "+ New → Meal" goes with it. The list table at `edit.php?post_type=mmm_meal` is still reachable by address; it is only unlisted.

**Meal Plan** — meta: `start_day` and `end_day` (1=Mon … 7=Sun). Plans can span across Sunday (e.g. start=6, end=2).

**Equipment is picked, renamed and added on the recipe screen** — `render_equipment_meta_box_content()` / `save_equipment_metabox()`, full width rather than the sidebar since the names became editable. It is the third box of this shape after the units on an ingredient and the meals in a plan, and the one that differs: **equipment has no parent**. Every recipe needing a frying pan points at the same post, so a rename here reaches all of them, and the box therefore shows the recipe count beside each piece — the only thing on the screen that says how far the edit goes — and offers **no delete**, which would take a piece out of every other recipe with nothing on this screen naming them.

An empty name keeps the stored one, the same rule the product brand and the meal recipe keep. The empty row runs through `find_or_create_equipment()`: equipment has a natural key, so a name that already exists is reused rather than duplicated — the author-term rule, for the same reason.

**And therefore no one-shot token here**, unlike the meals box. The block editor's second Save carries the checkbox list the first one was rendered with, which cannot contain what the first save just created; since `set_equipment()` writes the list whole, a token that suppressed the second resolution silently unticked the new piece. Resolving the name on every save fixes it, and find-or-create is what stops the duplicate. The token helper is `spend_new_row_token( $scope, $token )`, shared by any box that creates a row with no natural key.

**Adding and renaming go over AJAX, not the post's save** — `ajax_add_equipment()` / `ajax_rename_equipment()` (nonce `mmm_equipment_ajax`, localised as `MixMyMealsEquipment`). Creating a piece used to cost a full post save, after which the box did not re-render and the new row simply never appeared: the one thing the box exists for gave no sign it had worked. Now Enter creates it and the row arrives ticked.

**Only the piece is created there; the tick is still recipe data** and travels with the recipe's own save. So a piece added and then abandoned stays behind with no recipes, the way a tag would — that is the trade for not saving the post.

`equipment_row()` builds the row for the renderer **and** for the AJAX answer, because the row nobody compares against its neighbours is exactly the one that arrives without a reload. The handler returns finished markup rather than data for the same reason the unit select does: the plural and the wording stay in PHP.

The posted path is untouched and still works without JavaScript, which is why the empty row's hint is worded for *that* route and the script swaps it (`.mmm-equipment__hint`) for the one that is true with it.

`Mixmymeals_Equipment::usage_counts()` is the one pass over the recipes that name any equipment, tallied in PHP and memoised per request; `count_recipes()` derives from it so the two cannot disagree, while `get_recipe_ids()` keeps its own transient because the front end needs the list. That transient was never cleared until `set_equipment()` started calling `flush_recipe_cache()` on both the pieces added and the ones dropped — tolerable while the number only sat on the equipment list, wrong the moment it sits beside the box that just changed it.

### Ingredient amounts and weight calculation

`Mixmymeals_Recipe::get_ingredients_scaled($scale)` is the canonical method for getting ingredient lists with correct weights. It:
1. Multiplies each ingredient's stored amount by `$scale`
2. Snaps the result to the unit's `get_step_divisor()` (min one step): whole
   ones at a divisor of 1, halves at 2, any amount when there is none
3. Multiplies by the unit's weight in grams to get `weight_g`
4. Falls back to treating the amount as grams directly if no unit is set

`Mixmymeals_Meal_Plan::calc_shopping_list()` uses this by computing `$scale = meal_servings / recipe_servings` for each meal and aggregating `weight_g` totals per ingredient, then grouping by `mmm_ingredient_category`.

### Cart

**A planner can be shared without being published.** `mmm_share_cart` stores a **copy** of the items under its own uuid4 (`mmm_cart_share_<token>`, 30 days) and the link carries that token; `mmm_load_shared_cart` copies it into the opener's own cart. The obvious shortcut — putting the cart's own UUID in the link — is the thing to avoid: that cookie value is the live key, so a link holder would keep reading the sharer's planner as it changed and anything writing through it would write into somebody else's. A snapshot can only say what was planned at that moment, it ages out, and `tools/cart-share-test.php` pins both properties down. Recipes are re-checked on load, since one may have been unpublished since. **This is a new store of personal data, so both privacy texts name it** — the copy, its 30-day window, and that no copy exists unless somebody shares.

**The week row above the planner counts meals, not calories.** `mmmUpdatePlannerWeek()` scales the bars against the fullest day but decides *whether* a day is planned by how many items it holds — the same rule `calc_nutrition_per_day_person()` keeps, and for the same reason: a dish whose recipe has no nutrition data yet would otherwise leave its day looking empty, and filling that data in later would make a bar appear where the plan never changed. It is `aria-hidden`, because the day headings and totals below already say it in words.

**The empty planner offers finished plans as templates**, through the control the plan pages already use (`.mmm-meal-plan-actions__item--load-into-cart`) with the same confirmation, because it replaces the planner. Only on the empty state: once something is in there, an offer to clear it is a trap.

**Which plans qualify is a setting** — `mixmymeals_planner_templates_require_category`, off by default, in the Meal Plans tab under *Planner*. Switched on, only plans carrying a `mmm_meal_plan_category` term are offered. That is deliberately the **same** definition `Mixmymeals_Cleanup::delete_uncategorized_meal_plans()` uses to remove visitor plans again (`operator => 'NOT EXISTS'`, inverted here to `EXISTS`): a site running both would otherwise offer as a template a plan the next cron run deletes. Two definitions of "uncategorized" would drift, so there is one.

The clause goes **into the query**, not into a filter over its result. Only three plans are fetched; sorting one out afterwards would leave it holding its slot and the list would quietly shrink to two. Note that it lands in `tax_query` while `Mixmymeals_Post_Types::exclude_unlisted()` writes `meta_query` — they do not touch, so an unlisted plan stays out whatever the setting says. `tools/planner-templates-test.php` pins all three properties.

**Adding a recipe says so.** `mmmAnnounce()` writes one sentence into `#mmm-status` (`role="status"`, `aria-live="polite"` — the site had no live region at all) and shows the same text as a toast, so what is read aloud and what is seen cannot drift. The wording comes from `planner_dialog.added` in three variants rather than one template with holes, because a recipe planned without a day or a type otherwise reads "X planned for , .". The count rides along on the `mmm_add_to_cart` answer instead of costing a second request.

**The bottom bar is built from the header's own planner link**, not from a setting: `[mmm_cart_badge]` is placed by the site owner, and a second place that had to know the planner's address would sooner or later know a different one. No link, no bar. It carries its own `.mmm-cart-badge`, so the existing updater fills it, and `body.mmm-cart-empty` — set in that same pass — is what hides it.

**The day of a planner item lives in the DOM, not on the item.** `[mmm_meal_plan_builder]` reads it from the containing `.mmm-plan-day-list[data-day-list]` when the plan is created — so moving the `<li>` *is* the change, and `mmmMoveItemToDay()` in `frontend.js` both moves and saves. Drag and drop and the day select call that one function; saving without moving would be lost on the next render, moving without saving forgotten on the next load.

**That select exists because dragging is unreachable on a phone.** `frontend.js` has `dragstart`/`dragover`/`drop` and no `touchstart`, `touchmove` or `pointerdown` at all, and HTML5 drag and drop does not fire from a touch on iOS Safari or Android Chrome. With the add-dialog defaulting to no day, every recipe landed in the unassigned list with no way out — the planner's core function was unusable on the device most people cook with. The drag handle is hidden below the phone breakpoint for the same reason: a handle that does nothing is worse than none.

`render_plan_item()` builds one item for both the unassigned list and the seven days; the two used to be copies of fifty lines apiece, already diverging in whitespace.

**The planner's footer is one row, Save Plan — Share planner — Clear Plan, in that order**, because Share belongs *between* the other two rather than wherever the three shortcodes happened to land in the markup. The three are still separate shortcodes (`[mmm_create_meal_plan_from_cart]`, `[mmm_share_planner]`, `[mmm_clear_cart]`) — Share used to be the last thing `[mmm_meal_plan_builder]` printed, inside the builder's own box, which put it nowhere near the other two. The child theme's `page-templates/planer.php` wraps the three in a `<div class="mmm-plan-actions">` so they can be styled as one row instead of three fragments in a text flow; the plugin renders pieces, the theme places them, same split as everywhere else. The button that used to say "Create Meal Plan" now says **"Save Plan"** — the AJAX action, the CSS classes and the JS selectors are unchanged, only the visible label moved.

**A plan can be subscribed to right when it is saved**, not only afterwards from its own page. `Mixmymeals_Subscriptions::create_pending()` is the part of double opt-in step 1 that is about the *data* — building the pending row and sending its confirmation mail — pulled out of `ajax_subscribe()` so the standalone `[mmm_meal_plan_subscribe]` form and the Save Plan offer below build a pending subscription the same way; the caller still owns what is about the *request* (nonce, rate limit, the explicit-consent checkbox). `Mixmymeals_Subscriptions::consent_text()` is pulled out the same way, so both forms promise the same sentence.

**Three settings, Meal Plans tab, "Subscribe while saving".** `mixmymeals_plan_subscribe_mode` is `off` (default), `optional` or `required`; `mixmymeals_plan_subscribe_notice` is the site's own text under the field (links allowed, empty = `consent_text()`); `mixmymeals_plan_subscribe_notice_checkbox` decides whether that text is a checkbox that must be ticked (default) or a plain note, in which case entering the address is the consent. Getters: `save_offer_mode()`, `save_offer_notice()`, `save_offer_notice_is_checkbox()` on `Mixmymeals_Subscriptions`. The checkbox option stores `'on'` or `''` once saved, so its default can only live in the `get_option()` fallback — `''` means "unticked", not "unset".

**One decision, asked twice.** `Mixmymeals_Shortcodes::plan_subscribe_offer_mode()` folds in `save_offer_mode_effective()` (subscriptions enabled, at least one send rule) and the guest-draft case (`create_pending()` only accepts a published plan), and both the renderer and `mmm_create_meal_plan_ajax_callback()` ask it. A `required` the server enforced but the page did not show would make the plan unsaveable; a field the page showed but the server ignored would collect addresses for nothing.

**The field is always there while the offer is on** — an e-mail input in the action row, right before Save Plan, no checkbox or radio in front of it. Two earlier shapes hid it behind a "subscribe?" toggle and were rejected for exactly that: entering the address is the point, and a step before that step only buries it. `.mmm-plan-subscribe` is `display: contents`, so the input and the notice are direct flex items of `.mmm-plan-actions`; the input takes the pill shape of the buttons (two-class selector, since Astra's `input[type="email"]` beats one class), and the notice drops under the whole row via `order: 1` and `flex-basis: 100%`. No heading, no card.

**`required` is enforced server-side, before anything is created or counted against the visitor's hourly quota**: no address → `subscribe_email_required`, a malformed one → `subscribe_email_invalid`, a checkbox notice left unticked → `subscribe_consent_required`, and no plan exists afterwards. In the browser the Save button stays dimmed (`mmmPlanSubscribeReady()`) until the field holds a plausible address and the box, if any, is ticked. `optional` gates nothing: empty means no subscription, a filled field gets the same address/consent checks. Double opt-in applies in both — nothing is sent before the address is confirmed.

**`required` was the site owner's call, made against a stated objection.** Making a subscription the condition for saving a plan is what GDPR Art. 7(4) weighs against "freely given" consent; that was raised twice and the requirement confirmed. The setting's description says so in one sentence, and the code does not second-guess it beyond that.

`hint` and `choice` are values of an earlier, never-committed shape of the setting that a site may still hold; `save_offer_mode()` reads both as `optional` instead of silently switching the offer off. `tools/plan-subscribe-test.php` drives the real AJAX handler through `do_action( 'wp_ajax_nopriv_mmm_create_meal_plan' )` in every mode, including the guest-draft case — that is where the requirement lives, so that is what it tests.

(`.mmm-plan-share[hidden]` needs an explicit `display: none`: the theme's own `display: flex` on the same class is an author rule, and the browser's `[hidden]` rule is a user-agent default that loses to any author rule of equal weight regardless of source order. "Share planner" stood on every empty planner because of it.)


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
- **Meals are reachable but not findable.** A meal is a planning of a recipe and shows that recipe's dish, scaled to the plan's servings — the page has to exist, because every meal card on a plan links to it (`class-mixmymeals-shortcodes.php`, `$meal_url`) and the child theme has a full `single-mmm_meal.php`, but it is the same dish once per planning, so a searcher would land on an arbitrary serving size instead of on the recipe. **Two flags, because they cover different routes in.** `NOINDEX_POST_TYPES` (`mmm_meal`) gets `noindex, follow` through `filter_robots()` on `wp_robots` and `filter_yoast_robots()` on `wpseo_robots_array`, both at priority 20 so they have the last word over core and Yoast (`wp_robots()` prints every key whose value is `true`, so `index` has to be unset rather than set false); `is_noindex_view()` is the single decision both ask, filter `mixmymeals_seo_noindex`. Beside them the post type carries `exclude_from_search => true`, which reaches the site's own search and `WP_Query`'s `post_type => 'any'` — nothing here uses the latter. Neither flag touches the core sitemap, which selects on `public`; that is why the robots filters are not redundant. `public` itself stays `true`: on `publicly_queryable => false` every meal card on every plan becomes a 404. Deliberately **not** a canonical to the recipe: the pages are near-identical rather than identical, and a canonical across that gap is a hint an engine may discard. `follow` is load-bearing — it is how a crawler reaching a meal walks on to the recipe. Note that the unlisted flag is no substitute: `UNLISTED_POST_TYPES` covers only recipes and plans, so an unlisted plan's meals keep their own public URLs.
- The three helpers all of the above share are public statics on `Mixmymeals_Post_Types`: `meta_description()` (excerpt → content, 300 chars), `social_image_id()` (featured image → `mixmymeals_placeholder_image_id`; the placeholder is used ONLY here and as Instagram slide fallback) and `schema_image_urls()`.

The **Bring! import view** lives entirely in `Mixmymeals_Meal_Plan`, registered once per request via `Mixmymeals_Meal_Plan::register_bring()` in `mixmymeals.php` (same pattern as `Mixmymeals_Recipe::register_cook_count()`). `<plan permalink>?mmm_bring=1` (`BRING_QUERY_VAR`) renders a minimal noindex page on `template_redirect` whose only content is the shopping list as a schema.org/Recipe — Bring! fetches that URL itself and imports what it finds, and a plan page is an ItemList, not a Recipe. `bring_deeplink()` builds the link the shortcodes render; `get_bring_shopping_list_lines()` formats "405 g Möhren" (amount, unit, name — the order Bring! splits on).

Amounts in all of these go through `Mixmymeals_Ingredient::format_amount()`: no thousands separator, since parsers read "1.000 g" as one gram. The human-facing counterparts are `Mixmymeals_Meal_Plan::build_needed_amount_string()` / `build_short_amount_string()`.

### Feature modules (includes/lib)

| Class | Purpose |
|---|---|
| `Mixmymeals_Privacy` | GDPR personal-data exporters/erasers (subscriptions, photos); the generated privacy-policy section (settings tab, `[mmm_privacy_policy]`, WordPress privacy guide) |
| `Mixmymeals_Subscriptions` | Meal-plan e-mail subscriptions: double opt-in, consent records, drip sending via WP-Cron, token endpoints |
| `Mixmymeals_Mailer` / `Mixmymeals_Mail_Log` | wp_mail wrapper with GDPR footer + send log |
| `Mixmymeals_Ratings` | Comment-based star ratings (logged-in users + subscribers via token), per-recipe aggregates in `_mmm_rating_avg/_count` |
| `Mixmymeals_Photos` | Visitor meal-photo uploads (MIME-whitelisted, EXIF-stripped), moderation page, gallery |
| `Mixmymeals_Rest_Api` | REST namespace `mixmymeals/v1`: CRUD for recipes/ingredients/units/plans/meals, recipe search, post status, AI-friendly `/meal-plans/build` bulk builder, author box and media upload (see below) |
| `Mixmymeals_Cleanup` | WP-Cron cleanup of orphaned meals and stale visitor plans; owns the cleanup report e-mail (see below) |
| `Mixmymeals_User_Roles` | Custom roles (meal mixer) |
| `Mixmymeals_Admin_API` | Settings-field rendering engine used by `Mixmymeals_Settings` |

A companion MCP server for AI automation lives in `tools/mcp-server.mjs` (excluded from distribution via `.distignore`).

**The privacy-policy section is generated, not kept by hand.** `Mixmymeals_Privacy::policy_section_html( $heading )` builds the MixMyMeals section of a privacy policy from what the site actually does, and it is the single source for three outputs: the read-only **Privacy Policy** settings tab (`mixmymeals-privacy`, preview + HTML to copy, no form), the shortcode `[mmm_privacy_policy heading="h2|h3|h4|none"]` for the live policy page, and WordPress's own guide under Settings → Privacy (`add_privacy_policy_content()`). Structure and wording follow the generator text stopfnicht.de uses (data types, persons, purposes, legal bases, then one list item per processing).

It replaced `datenschutzerklaerung-mixmymeals.txt`, a hand-kept German file that drifted the way such files do: it stated the subscription windows as fixed figures while the code read them from options, and it never mentioned that the send log also ages out after 90 days. Before that, both texts announced transfers to Meta/Pinterest for months after neither plugin made an outbound request any more.

**Every figure comes from the constant or option that decides it** — `Mixmymeals_Cart::COOKIE_NAME`, `EXPIRATION`, `SHARE_EXPIRATION`, `Mixmymeals_Subscriptions::get_pending_hours()` / `get_retention_days()`, `Mixmymeals_Mail_Log::get_max_entries()` / `get_retention_days()` — and **every conditional sentence appears only where its condition holds**: subscriptions at all (and with them the suppression list and "subscribers" among the persons, ratings and photos), the consent IP (`subscription_log_ip`, stored in full when on), the planner as a sign-up route and the required subscription (`plan_subscribe_mode`), photo review (`photos_auto_approve`), the hashed save counter (public mode plus an hourly limit), the send log. A sentence about a processing the site does not do is not a cautious statement, it is an untrue one. `tools/privacy-policy-test.php` flips each condition and checks the text follows.

**Reviewed against a data-protection check on 2026-10-06**, which is why it says what it says: ratings and photos stand on lit. b (neither form asks for consent, so naming consent would claim a declaration nobody made), only subscriptions on consent; legitimate interests name the interest beside each processing (Art. 13(1)(d)); saved plans are described as public pages, with the account name on plans of logged-in users (the plan's author box falls back to it); the planner cookie is a *pseudonymous* identifier, not one "with no personal reference"; ratings via the comment form carry WordPress's own IP and user agent, those via the subscriber link neither; the mails' content is named, since the rating and photo invitations are what makes them advertising under § 7 UWG; Art. 13(2)(e) is answered per planner mode; and retention says that only finished subscriptions are deleted, N days after they ended. `consent_text()` names the mails' content too and links the policy as information, never as something to "accept".

IP addresses (and the e-mail in the mailer's rate limit) are hashed with `wp_hash()`, not `md5()`: an unsalted md5 of an IPv4 address is reversed by trying all 2^32 of them, which makes "pseudonymised" in the text untrue.

Add-ons describe their own processing through the filter `mixmymeals_privacy_policy_items` (plain-text items, escaped on output) — the Social add-on contributes its pin images and their retention that way, so this plugin still knows nothing about it.

So: **anything that changes how personal data is handled changes `policy_section_html()`** — a new store, a new recipient, a changed retention window, a new cookie, a first outbound call — and, if it depends on a setting, as a condition on that setting rather than a fixed sentence. A site owner using the shortcode gets the change without touching the policy page; one who copied the HTML does not, which the tab says.

### Author box

The external author credit shared by recipes and meal plans is read through `Mixmymeals_Post_Meta` — `get_author_name/text/url/link_text/nofollow/social/image_id/image_url()`, `get_author_data()`, `save_author_data()` and `has_external_author()`, beside the meta box (`render_author_metabox()` / `save_author_meta()`) that edits them. An empty name falls the box back to the post's WordPress author.

**The data itself lives on a term, not on the post.** `Mixmymeals_Author` owns the taxonomy `mmm_author` (`Mixmymeals_Author::TAXONOMY`, registered — like every other taxonomy — in `Mixmymeals_Taxonomies::register_author_taxonomy()`, for `mmm_recipe` and `mmm_meal_plan`; the term-form fields and the migration hook attach once per request via `Mixmymeals_Author::register_term_admin()` in `mixmymeals.php`). The term name is the author's name, the term description their bio, and five term metas carry the rest under the *same* `_mmm_author_*` keys the post meta used. An author therefore exists once instead of being re-typed per contribution, where every typo created a second person.

**The public author archive is currently switched off** — `publicly_queryable`, `query_var` and `rewrite` are all `false`, so terms exist in wp-admin but have no front-end page. The slug plumbing is still there for when it is turned back on: `mixmymeals_author_slug`, defaulting to the **plural** `authors`, because WordPress core owns `/author/` through its `author_base` and registering a taxonomy on the same base makes one of the two unreachable. Re-enabling it is what would give a guest author a page to link to from their own site.

`get_author_data()` is the single place that decides where the fields come from — every other getter reads through it, so they cannot disagree. It prefers the term and falls back to the legacy per-post meta, so an install that has not run the migration, or a post restored from an older backup, still shows its credit. `save_author_data()` writes the term (find-or-create by name), assigns it, and clears that post's legacy meta so the two can never contradict each other. Note the consequence: editing the bio on one recipe changes it everywhere that author appears, and a typo in the name creates a second person rather than renaming the first.

The one-off move of legacy meta into terms is `Mixmymeals_Author::migrate()`, gated on `mixmymeals_author_taxonomy_version`. It deliberately leaves the old rows in place — they are inert once a term exists, and they are the only way back if two people who happened to share a name were merged.

The markup is `render_author_box()` in `Mixmymeals_Shortcodes`, shared by `[mmm_recipe_author_box]` and `[mmm_meal_plan_author_box]` so both post types render identically. A picture (`_mmm_author_image`, attachment ID) is rendered with the avatar/photo classes themes and microformat parsers key on, at `AUTHOR_AVATAR_SIZE`; without one the media block is omitted. **The box does not call `get_avatar()`** — it did, as the fallback for a post with no external author, and that put a Gravatar request on every recipe page: the visitor's IP and a hash of the author's e-mail to Automattic in the US, for a picture nobody on the site had chosen. A WordPress author therefore gets no picture at all; the way to have one is the author box's own field, served from this site. Note the consequence for local-avatar plugins: they work by filtering `get_avatar()`, so they no longer reach this box either.

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

### Chip wording

**Every chip's text is an option**, and the registry sits in `Mixmymeals_Shortcodes` — the class that renders the chips, so a wording cannot drift away from the markup it words. A chip is the shortest text on a page and the most read; the wording that suits one site ("4 Portionen") is wrong for the next ("für 4 Personen"), and before this the only way to change one was to edit a .po file and lose it on the next update.

`chip_texts()` is the registry: key → `label`, `default` (translated, so an untouched site gets what the translation says), `tokens` it may carry, `required` the ones it is useless without, `group` and `note` for the settings screen. Filter `mixmymeals_chip_texts`. `chip_text( $key, $tokens )` renders and returns plain text — escaping stays with the caller, as everywhere else in that class. Options are `mixmymeals_chip_text_<key>` (`CHIP_TEXT_OPTION_PREFIX`).

**Placeholders are named (`{count}`), not positional.** Word order differs per language and per taste — "{days} Tage {storage}" and "{storage}, {days} Tage" are both reasonable and `%1$s`/`%2$s` cannot express the choice. A placeholder the pattern leaves out is simply not printed; one it invents makes the pattern unusable.

**A pattern that drops a value the chip exists to state is refused** and the default takes over — a servings chip without `{count}` reads "Portionen" on every recipe, a diet chip without `{diet}` the same word beside every diet, and a chip that says the same thing everywhere says nothing. `sanitize_chip_text()` stores such a pattern as empty rather than keeping it, so the field never shows a value that has no effect. `{unit}` is never required: it is the already-inflected word for the count, and a site writing its own noun is deciding, not erring. That is how German says "In {count} Rezepten" where `_n()` can only supply the nominative "Rezepte".

**Plurals stay in the translation, not in the option.** The call site passes `{unit}` from `_n()`; the pattern only places it. One option per chip rather than two, and nobody has to know a language's plural rules to reword a chip.

The screen is the ordinary settings machinery: `Mixmymeals_Settings::chip_text_fields()` builds the tab from the registry (a closure per field carries the key into the sanitiser, since `register_setting()` hands a callback only the value), and `Mixmymeals_Admin_API::display_field()` renders it — its switch dispatches on a `$field_id` that collapses every `chip_text_*` id to one `chip_text` case, because the fields are registered per chip and cannot be listed by name. That case is a copy of the plain-text one at full width: a currency symbol is two characters, a chip pattern is a short sentence.

Note what this does **not** loosen: `disclosure_label_text()` still decides *whether* a label is required and the chip text only words it. A site can word a legal disclosure badly, exactly as it could by mistranslating it before — which is why the German defaults stay "Werbung" and "Gastautor" and the note under the field says so.

### Findability: facets and the recipe index

The archive can be narrowed by **diet**, by **preparation time** and by **the ingredients somebody has**. The data for all three already existed; what was missing was a queryable form of it.

**The index is meta on the recipe, and it answers queries — it never states anything.** `Mixmymeals_Recipe::META_DIET_INDEX` (one row per diet slug, the *capable* set expanded) and `META_INGREDIENT_INDEX` (one row per ingredient ID), both recomputed by `recount_indexes()` and written only on difference. Deliberately **not** `mmm_diet` terms on `mmm_recipe`: a term of that taxonomy means "somebody said so" everywhere else, and `Mixmymeals_Diet::for_post()` would start answering for recipes the moment the taxonomy knew them. Meta cannot be mistaken for a statement, and has no admin column, no metabox and no REST route anybody could set by hand.

The consequence is the guarantee: the chips, `get_schema()` and the REST output all keep **computing** through `get_diets()` / `get_diet_suitabilities()`. A stale index can leave a recipe out of a filtered list; it can never put "vegan" on a page. `tools/diet-test.php` writes a false row by hand and asserts that the pill and the schema stay clean — that assertion is what the whole design rests on.

**Kept current the way the cook counter is**, one class further away: `register_recipe_index()` watches the recipe (`wp_after_insert_post`, `_rmm_steps`, `_mmm_preparation_time`), the ingredient and product side (`set_object_terms` on `mmm_diet`, the two legacy diet metas, `Mixmymeals_Product::META_REFERENCE`, product saves, status changes and deletions) and `delete_term`. Everything queues via `mark_index_dirty()` and is recomputed once on `shutdown`. Two additions the counter does not need, both because the diet hangs three hops away:

- **A cap.** Changing an ingredient used in a hundred dishes queues a hundred recipes, each walking its ingredients through products and terms. Above `INDEX_FLUSH_MAX` the request reindexes **nothing** and hands the lot to a single cron event a minute later — half a batch would leave a set nobody can name half correct.
- **A daily repair run** in `Mixmymeals_Cleanup::run()`, reporting its deviation count through `record()`. That number belongs at zero; anything else is a missing hook, not a tolerance.

**`_mmm_preparation_time` follows the counter's convention, not the shelf life's**: `set_preparation_time( 0 )` deletes the row. "0 minutes" is not an answer, so silence and zero are one claim and the absence of the row carries it — which is what makes `compare => '<='` exclude untimed recipes by its join instead of promoting them to the fastest bucket. The core meta route registered in `Mixmymeals_REST_API` can still write a 0; `recount_indexes()` clears it on the next write to that post, and that gap is documented where it is registered.

**One registry owns every path under `/rezepte/`.** `archive_view_slugs()` maps slug → [kind, value] for the browse views, the four diets and the time steps, first claim winning, and `add_archive_view_rules()`, `reserve_browse_view_slugs()` and the flush fingerprint all read it. Each facet registers **two** slugs — the internal key and the translated word — so a shared link survives a change of wording. Reservation only protects going forward: a recipe that already holds such a slug becomes unreachable the day the rule appears, so **check the existing slugs before deploying new paths**.

**Diet and time are the same kind of choice and look it.** Both are a row of plain text links above the archive (`[mmm_archive_filters]`, one `render_filter_row()` for both), each with its counts and an "any" link, each carrying the other row's choice along. The duration was a `<select>` in the search form until that stopped making sense: choosing "vegan" and choosing "under 30 minutes" are the same act, and one of them being a form control while the other was a link made the pair read as two unrelated things. The rows are deliberately quieter than the green search button they sit under — two filled green pills above one another, only one of which is a button, is the confusion worth avoiding.

**A parameter beats a path** for the same facet, so a form submitted from `/rezepte/vegan/` can say something else. `Mixmymeals_Shortcodes::archive_filters()` reads the request once; `archive_filter_query_args()` is a pure function of it (testable without a request) and `apply_archive_filters()` **merges** its `meta_query`, never replaces it — the unlisted clause is already in there. `archive_primary_facet()` is the single answer to which facet owns the address, asked by the form's action, its hidden fields and the theme's heading alike.

**Ranking and pagination are reconciled before the query runs.** `rank_recipes_by_ingredients()` builds the whole ordered ID list — counted **per token, not per ingredient ID**, since "Paprika" naming three ingredients is still one answer — and the list goes in as `post__in` with `orderby => post__in`. No match yields `array( 0 )`, because an empty `post__in` is not a filter that matches nothing, it is no filter at all.

Single facets stay indexable; combinations and searches get `noindex` through `filter_archive_noindex()`, since those pages are one reader's question and have no address of their own.

**One builder makes every archive address.** `archive_url_for()` puts the primary facet in the path and the rest in the query, so one state has one address however it was reached; `archive_filters_without()` drops a single filter and `active_archive_filters()` pairs each one with the address that removes it. The diet pills, the form's action and the chip row all go through it — two builders would sooner or later produce two addresses for one view. It **rawurlencodes** what it appends, because `add_query_arg()` encodes the arguments a URL already carries and passes new ones through untouched; `esc_url()` hides that at print time, which is precisely why such a URL survives a visual check and breaks where it is used raw.

The chips live in the theme (`mmm_print_active_filter_chips()`), in the hero where the "All recipes" link used to be — that link is gone, because a row that names every filter and drops them one at a time does its job and more. They are links, not buttons: the address exists anyway, so it works without JavaScript and opens in a new tab. Deliberately quieter than the diet pills above them: those choose something, these show what is chosen, and one look for two meanings is the confusion worth avoiding.

**The ingredient page reads the index too.** `recipes_using_ingredient()` used an unanchored `LIKE` over serialized step meta, capped at 100 candidates and 24 results, behind a transient nothing cleared — that is why the list broke off mid-alphabet. `Mixmymeals_REST_API::ingredient_usage_map()` deliberately **stays** a scan: it counts drafts and decides whether an ingredient may be deleted, and an index that knows too little would wave that through.

**A category called "Vegan" is a category.** The archive chips used to lift such a term out and print it as a diet chip, which put an editorial claim beside the computed one. That branch is gone from all three chip builders. Consequence for the content: a recipe filed under such a term now shows a plain category chip, so the terms are better renamed ("Vegane Küche") — the slug can stay, and the links with it.

`[mmm_recipes_index]` knows `mmm_s` but none of the new filters. Deliberate, and noted here so it is not rediscovered as a bug.

**A plan in a carousel shows several of its dishes, a recipe one.** `render_meal_plan_browse_cards()` asks `get_meal_plan_card_images()` for up to three pictures — the plan's own featured image first where there is one, then its recipes by priority, each recipe once — and wraps them in `.mmm-recipe-row__mosaic`; the theme grids that into one large tile and two stacked. Below two pictures the card falls back to `get_meal_plan_card_thumbnail()` and the single-image shape, because a mosaic with one tile reads as a layout that failed.

**The tiles get their own wrapper rather than the media box becoming the grid**, and the images are placed explicitly by `nth-of-type`. Two things share that box: the disclosure pill, which is in normal flow and would take a tile — the ad label, of all things — and a `<noscript>` the image optimiser adds beside every thumbnail, twelve of them on the live front page. Auto-placement scatters the mosaic as soon as either appears.

Grid items take their content as their minimum size, so the 300px thumbnails pushed their `1fr` rows to 91.5px against a 140px box and hung out of the card; `min-height: 0` on the tiles is what holds the row height. Recipes are untouched throughout.

### The admin area

**One menu per subject, and one menu for the plugin itself.** `mmm_recipe` (30), `mmm_ingredient` (31) and `mmm_meal_plan` (32) are the content types an editor opens daily and stay top level; the first two shared position 30 for years, so which came first was decided by registration order. `mmm_equipment` hangs under Recipes (`show_in_menu => 'edit.php?post_type=mmm_recipe'`) — twenty rows nobody edits weekly, and the recipe's own box creates them anyway. The add-on's Social Media menu sits at 33 — write, then publish, then configure — and `mixmymeals_settings` is a top-level menu at 35 carrying Settings and Subscriptions. Meal Photos hang under Meal Plans beside their approval setting: the photos attach to recipes, but most arrive through the upload link in a plan's subscription mails. 34 is left free so a further add-on fits between without WordPress's collision offset placing it.

```
Rezepte        Alle Rezepte · Neues Rezept · Kategorien · Tags · Autoren · Küchengeräte · Geräte-Kategorien
Zutaten        Alle Zutaten · Neue Zutat · Kategorien · Ernährungsformen
Essenspläne    Alle Essenspläne · Neuer Essensplan · Kategorien · Tags · Mahlzeiten-Fotos
Social Media   Pinterest-Pins · Einstellungen (→ Pinterest tab)          [add-on]
MixMyMeals     Einstellungen · Abos
```

**The order inside a menu is declared, never left to registration.** `Mixmymeals_Settings::SUBMENU_ORDER` lists every entry of the four menus top to bottom and `order_submenus()` sorts `$submenu` by it at `admin_menu` priority `PHP_INT_MAX`; unlisted entries (another plugin's) keep their relative order below. A sort, not `$position` arguments: half the entries are added by core (a post type's own items and its taxonomies, before `admin_menu` even runs), so positions could only ever order the other half. Left alone, Subscriptions — a post type hung under the menu, which core adds early — stood first, and since a top-level item links to its **first** entry, clicking "MixMyMeals" opened the subscription list. A new screen in one of these menus needs a line in that constant, and `tools/admin-structure-test.php` asserts each list exactly. It builds the real menu by requiring `wp-admin/menu.php`, which works under wp-cli once `$pagenow`, `$self` and a current screen are set.

**A taxonomy of a nested post type gets no menu entry from core.** WordPress lists taxonomies only under post types that are top-level menus, so the equipment categories vanished the day equipment moved under Recipes — while their distinct `menu_name` kept naming an item nobody could see. `Mixmymeals_Taxonomies::add_submenus()` adds them by hand (with `show_in_menu => false`, so a core change cannot add a second), and `highlight_equipment_category_parent_menu()` keeps Recipes lit, since core points the screen at `edit.php?post_type=mmm_equipment`, which is an entry, not a menu. Hand-added term-screen slugs carry `&amp;` like core's: the menu marks the open entry by comparing the slug with the encoded `$submenu_file`, and with a bare `&` the Authors entry was never marked.

**`Mixmymeals_Settings::settings_url( $tab )` is the only place that knows where the settings screen lives.** It moved out of `options-general.php` into that menu, and three links in two plugins kept pointing at the old file — the add-on's among them, which is why the helper is public. The tab row builds its links from it too; it used to call `add_query_arg()` with no URL, which appends to `$_SERVER['REQUEST_URI']` and therefore worked by accident wherever the screen happened to be.

**A taxonomy on two post types appears under both menus.** `mmm_author` belongs to recipes and plans, so "Authors" was listed twice — one list of people, two ways in. It carries `show_in_menu => false` and `Mixmymeals_Taxonomies::add_submenus()` puts it under Recipes once. Equipment categories keep a distinct `menu_name` for the same reason: since the post type sits under Recipes, a second item called "Categories" would otherwise stand beside the recipe categories.

**There is no JSON import any more** (removed in 3.49.0). The recipe and ingredient importers, their screen, the `/import-reference` REST route and their CSS are gone; content arrives through the REST API and the MCP server, which never used the importer.

**Settings tabs are subjects, not leftovers, and they follow the menu.** General · Recipes · Ingredients · Meal Plans · E-Mail & Subscriptions · Privacy Policy · Language, then whatever add-ons append through `mixmymeals_settings_fields` (Pinterest). The three content tabs carry the names and the order of the three content menus, and what hangs under a menu is set in its tab: equipment (slug, archive text, affiliate disclosure) and the author slug under Recipes, the meal slug under Meal Plans. There used to be an Equipment tab of three fields, and General held the meal and author slugs because, in its own words, they "belonged to no tab". General is now what concerns the whole plugin: currency, placeholder image, cleanup, uninstall. What concerns visitors sits with the subject it is about, not in a tab of its own: plans built by visitors, photo approval and the plan and photo notices under Meal Plans, the rating notice under Recipes, the subscribe notice with the subscriptions. E-Mail and Subscriptions are one tab, because a subscription is a series of mails and sender, footer and retention apply to exactly those; the key stays `mixmymeals-email`, which the test-mail box and send log hang on. **Language** holds wording; its first section is Chips — a `heading` field with `'level' => 2`, rendered as `h2.mmm-settings-section-title` above the `h3` groups. Moving a field between tabs is safe: `register_setting()` uses one option group and the option name is `mixmymeals_<id>` whatever the tab — check that the set of field ids is unchanged before and after.

**A tab key is resolved in one place.** `Mixmymeals_Settings::resolve_tab()` maps retired keys through `TAB_ALIASES` (`mixmymeals-equipment` → recipes, `-visitors` → meal plans, `-subscriptions` → email, `-chips` → language) and unknown ones to the first tab; `register_settings()`, the highlighted tab, the hidden `tab` field and the E-Mail extras all ask it. Before, an unknown key registered no section and showed an empty form, and the highlight depended on a nonce in the tab row's own links — so the add-on's "Settings" entry showed the Pinterest fields under a highlighted "General". Selecting a tab changes nothing, so it carries no nonce. **Renaming or removing a tab means adding its old key to `TAB_ALIASES`** — the add-on and bookmarks address tabs by key.

**`assets/css/admin.css` is the only stylesheet this plugin loads in wp-admin**, on every screen, so selectors hang off its own classes. Section 1 holds the tokens — the WordPress admin palette, because a box here should look like the box next to it. Note the two class prefixes: the obvious unification onto `mmm-` is *not* available, because five of the seventeen short names are already taken on the frontend (`mmm-day`, `mmm-step-image`, `mmm-step-ingredients`, `mmm-recipe-meta`, `mmm-ingredient-optional`). Unifying means a prefix of its own (`mmm-admin-`) and 159 call sites across CSS, PHP, `admin.js` and `admin.min.js`.

### Settings

Settings are registered via `Mixmymeals_Settings` using the WordPress Settings API. All option names are prefixed `mixmymeals_`. Notable options: `mixmymeals_public_mode` (allow guest plan creation), `mixmymeals_published_meal_plans_per_hour_limit`, `mixmymeals_save_meal_plans_published_by_visitors_as_draft`, `mixmymeals_placeholder_image_id`.
