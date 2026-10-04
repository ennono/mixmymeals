=== MixMyMeals ===
Contributors: frickemedia
Tags: meal planning, recipes, shopping list, nutrition, meal plan
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 3.46.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build recipes, plan weekly meals and generate shopping lists — with a frontend planner, nutrition data and PDF export.

== Description ==

MixMyMeals is a meal-planning toolkit for WordPress. It lets you manage a recipe
and ingredient database, assemble weekly meal plans, and automatically generate
categorised shopping lists.

Visitors can build their own plan in a frontend planner, review the combined
shopping list and nutrition totals, and export everything as a PDF.

= Features =

* **Recipes** with step-by-step instructions, per-step ingredients and images.
* **Ingredients** with nutrition values (energy, fat, carbohydrate, sugars,
  fibre, protein, salt), configurable units and an optional price per 100 g.
  A unit states how finely it may be split — one number: 1 for whole ones
  only, 2 for halves, 4 for quarters — so a recipe asks for half a tin
  instead of an arbitrary fraction of one.
* **Meals & meal plans** spanning any range of weekdays, grouped by meal type
  (breakfast, lunch, dinner, snack), with per-day calorie totals.
* **Shopping list generator** that scales ingredient amounts to the chosen
  servings and groups items by ingredient category. Quantities are rounded up
  once over the whole plan, so two recipes each needing half a tin buy one
  tin, not two.
* **Nutrition in context**: every figure can be shown against a reference
  intake for an average adult (2 000 kcal), laid out like the panel on a food
  packet — on recipes, on plans, in the PDF and in the subscription mails,
  all quoting the same numbers. The values are configurable.
* **Products**: the concrete, branded articles an ingredient can be bought as,
  each with the figures its packet states and a note saying where they came
  from. One of them can supply the ingredient's nutrition, and the ingredient
  page lists them all side by side.
* **Product choice**, on a plan and on a dish: for every ingredient that comes
  in more than one product, what picking each of them would do to all seven
  nutrition figures. Per day and person on a plan, under the shopping list
  where the decision is made; per serving on a meal or recipe, where the
  portion itself is the unit.
* **Frontend planner** for logged-in users and (optionally) guests, with a
  server-side cart, live day totals and configurable size limits.
* **PDF export** of a meal plan: overview, shopping list and all recipes.
* **Meal-plan e-mail subscriptions** with GDPR-friendly double opt-in, and
  placeholders for the day's nutrition, a breakdown per dish and the plan's
  daily average.
* **Frontend admin panel** on plan and recipe pages (admins only): set to
  draft or trash without leaving the page. Add-ons can contribute their own
  actions to it.
* **Social publishing** is available separately as the MixMyMeals Social
  add-on (Instagram and Pinterest). This plugin makes no external API calls
  of its own.
* **SEO built in**: schema.org Recipe markup for Google rich results (rating,
  nutrition, steps), ItemList for plans, Open Graph / Twitter Cards.
* **Related recipes** (“goes well with” / “fits with”) and a “featured in
  these plans” section on recipe pages.
* **Recipe ratings & reviews** (logged-in users and newsletter subscribers)
  with moderation.
* **Community meal photos**: visitors upload photos of their cooked meals,
  reviewed before publication; embedded EXIF/location data is stripped.
* **Configurable visitor-input notices** on all forms (ratings, photo upload,
  subscription, plan creation).
* **GDPR tooling**: personal-data exporters and erasers, suggested privacy
  policy text, consent records, clean uninstall with optional data removal.
* **REST API** covering recipes, ingredients, units, plans, meals, recipe
  search and publishing — automation-ready, including an AI-friendly
  one-call meal-plan builder.
* **JSON recipe importer** for administrators.

== Installation ==

1. Upload the `mixmymeals` folder to `/wp-content/plugins/`, or install the ZIP
   via Plugins → Add New → Upload Plugin.
2. Activate the plugin through the "Plugins" screen in WordPress. Permalinks
   are flushed automatically.
3. Open Settings → MixMyMeals and configure the basics on the General tab
   (slugs, public planner mode, limits).

== Frequently Asked Questions ==

= Does it require any external service? =

No. This plugin makes no external requests at all — everything runs against
your own WordPress database. Social publishing, which does talk to the Meta
and Pinterest APIs, lives in the separate MixMyMeals Social add-on and only
acts when a user connects their own account there.

= Can visitors create meal plans without an account? =

Yes, if you enable "public mode" in the settings. You can additionally save
visitor-created plans as drafts and rate-limit them per hour.

= Which third-party libraries are bundled? =

PDF generation uses dompdf (LGPL-2.1), bundled under `includes/lib/dompdf/`.

= Is it GDPR-compliant? =

The plugin ships double opt-in subscriptions with consent records, WordPress
personal-data exporters/erasers, a suggested privacy-policy text, EXIF
stripping for visitor photos and a clean uninstall. Review the suggested
privacy text under Settings → Privacy after installation.

== Changelog ==

= 3.46.3 =

Fixed:
* The recipe pictures in the subscription mails' meal list took the shape of
  their file: only the width was fixed, so on a site whose thumbnail size does
  not crop, a portrait photo came out as a narrow strip far taller than the
  text beside it. They are now a 64 px square, cropped where the mail client
  supports it.

Changed:
* The nutrition table in subscription mails sets the energy row in bold and
  the accent colour, so it can stand in for the separate day-total line
  above the meals instead of repeating it.
* Font sizes in subscription mails follow one scale: the reference-intake
  footnote under the nutrition table is 12 px instead of full body size, the
  packet name under a shopping-list item 14 px instead of 13, and the
  shopping list's category headings are h3 rather than a second h2 under
  the template's own.

= 3.46.2 =

Fixed:
* Two places built their own small map of diet labels instead of asking
  Mixmymeals_Diet::label() — the ingredient hero chip and the diet row of the
  product comparison. A diet added through the mixmymeals_diet_types filter
  resolved correctly everywhere else and simply had no name in these two
  spots.
* The taxonomy screen under Ingredients → Diets accepted any term typed
  into it, including ones the plugin has never heard of. They sat in the list
  producing no badge, no filter and no place in schema.org data — creating
  one looked like it had worked and quietly did nothing. Creating an unknown
  term is refused now, with a message pointing at the filter, which is the
  real way to add one.

= 3.46.1 =

Fixed:
* A diet classification could not be removed. Diets moved from a single meta
  field to terms, and the reader falls back to that old field whenever a post
  has no terms — so taking the terms away brought the old value straight back,
  out of a row nobody could see any more. Clearing one was impossible, whether
  through the bulk endpoint with an empty list or through the update route
  with `diet_type: ""`. On stopfnicht.de four ingredients carried a vegan
  badge although their reference product is meat.
* Writing a classification now clears that old row. Non-empty writes too: once
  the terms state it, the field beside them can only disagree. The migration
  is the one exception and keeps it, because it is the way back.
* `own_diet_type` read the old field directly, so after a migration it
  reported "" for every ingredient whose classification sat in a term — the
  opposite of what the field is for. It reads the taxonomy now.

= 3.46.0 =

Added:
* A recipe step can rule single products out for its dish. Each ingredient row
  lists the packets that ingredient has; ticking one takes it out of the
  product comparison and stops it carrying the diet badge.
* That badge is why this exists: an ingredient counts as "vegan possible" as
  soon as *any* of its packets is vegan, which is the point of "possible" —
  but a sweetened soy yoghurt has no business making a savoury dish vegan.
* Set per ingredient row, in force for the whole recipe. The same ingredient
  can appear in two steps; ruling a packet out in one of them is enough,
  because the diets of the rows are intersected. Ticking it in one step and
  forgetting the other therefore honours the exclusion rather than dropping
  it.
* Over REST and MCP as `excluded_products` on the step ingredient. Ids that
  belong to another ingredient are discarded on save, like a misplaced pin.

= 3.45.0 =

Changed:
* The admin area is one plugin again instead of four menus and a settings page
  filed somewhere else. Equipment moved under Recipes, Authors appears once
  instead of under both Recipes and Meal Plans, the two pages called "Import"
  became one with a tab each, and a MixMyMeals menu now holds the settings,
  the import, the meal photos and the subscriptions. Recipes, Ingredients and
  Meal Plans sit at fixed positions 30, 31 and 32; the first two shared 30 and
  pushed each other around depending on registration order.
* The settings screen gained a Visitors tab. What a guest may do was spread
  over three tabs: plans under General, photo approval under Subscriptions,
  and the four notices visitors read back under General. Scheduled cleanup
  moved from Meal Plans to General, since it prunes mail logs and
  subscriptions too. No option was renamed, so nothing stored is lost.
* The settings URL changed from options-general.php to admin.php. Links to it
  go through Mixmymeals_Settings::settings_url() now, including the ones in
  the social add-on, which pointed at the old address.

Fixed:
* assets/css/admin.css carried a 100-line block twice, verbatim, plus rules
  for a calendar that no longer exists. Colours and radii are tokens now
  (four greys did one border's job, three radii one card's), and the import
  screen's inline <style> moved in, so admin CSS has one home.

= 3.44.0 =

Added:
* A setting under Meal Plans → Planner: offer only plans that have a category
  as a template in the empty planner. A plan a visitor built carries no
  category, which is the same thing the cleanup cron goes by when it removes
  them again — so without this the planner could offer a plan that is about to
  be deleted. Off by default; nothing changes until it is switched on.

= 3.43.1 =

Fixed:
* The planner on a phone. The remove button lay over the right edge of the day
  field, the day marker of the timeline sat on the first letter of the day's
  name, and the three actions below the board shared one line of text, so
  "Clear plan" wrapped around the "Create meal plan" button and over it. The
  card is now a grid with a column of its own for the remove button, and each
  action gets its own full-width line.
* Every control in the planner and in the plan-a-recipe dialog is at least
  44px tall. The selects were 36px and the dialog's fields 40px, which is a
  mouse height.
* The hint on an empty day asked the visitor to drag a recipe there. A touch
  device cannot: HTML5 drag and drop does not fire from a touch. It now reads
  "Choose this day on a recipe to plan it here" wherever hovering is
  impossible, which is the way that actually works there.

= 3.43.0 =

Added:
* The week at a glance above the planner: seven bars, one per day, so which
  days are full and which are still empty can be seen without scrolling three
  screens. A day counts as planned by its meals, never by its calories.
* An empty planner offers the latest finished plans as a starting point. One
  tap instead of seven passes through the dialog.
* A planner can be shared without being published. The share button makes a
  copy under a token of its own and hands out a link; opening it offers to
  load that copy. The copy is frozen at the moment of sharing, gives no access
  to the planner it came from, and is deleted after 30 days.

= 3.42.0 =

Added:
* Planning a recipe now says so. A short confirmation names the dish, the day
  and the meal type, counts what is in the planner and carries a link to it —
  announced to screen readers through a live region the site did not have.
* A bar at the foot of the screen while the planner is not empty, on phones:
  the count and the way into the planner, where the thumb already is.

Fixed:
* A recipe added without a meal type showed "Breakfast" in the planner, a
  value nobody chose, and "Other" could not be set there at all — the planner
  kept its own four-entry list instead of the one the rest of the plugin uses.
* The "Add to Planner" button on recipe cards was a <span>: not focusable, no
  role, unreachable by keyboard. It is a button, and 44px tall like the other
  controls it sits beside.

= 3.41.0 =

Fixed:
* The planner could not be used on a phone. A recipe's day could only be set by
  dragging, and HTML5 drag and drop does not fire from a touch — so with the
  dialog defaulting to no day, everything piled up under "unassigned" with no
  way out. Every item now has a day select, which moves it exactly as a drop
  does. The drag handle is hidden where it cannot work.
* The planner card fell apart on a phone: the recipe name was left 77px of a
  386px screen and the controls ran 45px out of the card. It wraps into two
  rows now — 235px for the name, nothing overflowing.

= 3.40.0 =

Changed:
* A meal plan in a carousel shows several of its dishes instead of one, so a
  plan can be told from a recipe at a glance. Recipes are untouched. Plans with
  fewer than two pictures keep the single-image card.

= 3.39.0 =

Fixed:
* Kitchen equipment had no public pages at all. The archive, every equipment
  permalink and every "Required equipment" link on a recipe answered 404,
  although both templates, the hero, the index and the affiliate links had
  been there the whole time. The post type is reachable again and the
  permalinks are flushed once on update.

= 3.38.0 =

Added:
* A product on the ingredient screen says which recipes chose it, and how many
  lines each — the same list the units have had, linked and shortened, drafts
  marked. A reference product still counts nothing here, since supplying the
  figures wherever nothing was chosen is a property of the ingredient, not of
  the packet; it says so in a line of its own.

= 3.37.0 =

Added:
* A meal can carry its own short text — "cook double, freeze half", "the
  evening before the shopping trip". It belongs to that one planning, not to
  the recipe, and never borrows the recipe's own line. Shown under the dish on
  the plan and above the meal page. Editable on the meal screen and through
  the API; visitors planning for themselves are not offered it.

Fixed:
* Saving a meal could silently skip everything a second save carried: the
  recursion guard was raised before its own checks and never lowered again on
  the three paths that return early.

= 3.36.0 =

Added:
* A meal plan's meals are edited in the plan itself: one row per meal with its
  recipe, type, day and servings, a box to delete one and an empty row at the
  foot to add one. Same shape as the units on an ingredient.
* The day list offers the plan's own days in the plan's own order, so a plan
  running Saturday to Tuesday reads Sat, Sun, Mon, Tue. A meal already sitting
  outside that range keeps its day in the list rather than being moved by a
  save that never meant to.
* Ingredient units say which recipes use them — the titles, linked and
  shortened, instead of a bare count.

* Kitchen equipment is picked, renamed and added straight in the recipe, and
  adding one no longer costs a save: type the name, press Enter, and the row
  is there and ticked. Each piece shows how many recipes need it, because a
  rename here reaches all of them; nothing is deleted from this screen.

Changed:
* Meals are gone from the admin menu. They are planned in the plan they belong
  to; a list of every meal of every plan was a list nobody started from. Each
  meal keeps its own screen, linked from the plan's table.

Fixed:
* The product fields on the ingredient screen no longer squeeze each other.
  Six table columns fought over one width, so the brand, the pack size and the
  price were left with whatever the long "use this product" label did not take,
  labels were cut off and the diet checkboxes ran into their own words. Each
  product is a card now whose fields wrap.
* Two stray debug writes on every meal edit screen.

= 3.35.0 =

Added:
* Recipes can be narrowed down by diet, by preparation time and by the
  ingredients you happen to have. Each single filter has an address of its own
  — /rezepte/vegan/, /rezepte/unter-30-minuten/ — so it can be shared and
  bookmarked; combinations sit behind it as parameters.
* A field "I have these in": name a few ingredients and the recipes using most
  of them come first, with a chip on each card saying how many of them it uses.
* The diet filter collects what a dish **can** be cooked as, so a recipe whose
  pill reads "Vegan-capable" is in the list. The view says so in a line under
  its heading, because a filter that quietly means something else is worse than
  none.
* The filters in force are shown as a row of chips above the list, each of them
  removable on its own — with two or three filters running there was nowhere on
  the page that said what the list was narrowed by.
* The preparation time is now on the archive cards. It was the one figure the
  cards never carried, which made "something quick" unanswerable from a listing.

Changed:
* A recipe with no stated preparation time no longer counts as taking zero
  minutes: the field is now empty rather than 0, so "under 20 minutes" lists
  dishes somebody actually timed. The same correction applies to the REST recipe
  search, where an untimed recipe used to pass every time limit.
* A category that happens to be called "Vegan" is shown as a category. It used
  to be reprinted as a diet label, which put an editorial claim beside the one
  computed from the ingredients — two answers to a question people act on.

Fixed:
* The list of recipes on an ingredient page broke off after 24 entries, in the
  middle of the alphabet, and could stay an hour out of date. It is complete and
  current now.

= 3.34.0 =

Added:
* Diets are now a taxonomy on ingredients and products instead of a single
  field, so an ingredient can be vegan *and* gluten-free rather than only one
  of the two. Recipes and plans still work theirs out from their ingredients,
  so nothing has to be kept in step by hand.
* A recipe can name the concrete product an ingredient is cooked with. That
  choice now counts everywhere: its nutrition and its price go into the
  recipe, the meal and the plan, it is named in the ingredient list and on the
  shopping list, and it decides the recipe's diet — a dish whose ingredients
  are all settled reads "Vegan" instead of "Vegan-tauglich".
* The product comparison leaves out every ingredient the recipe has already
  settled: that table asks which packet to buy, and for those the author has
  answered. It anchors on the packet a dish actually uses, and where two
  recipes of one plan choose different ones for the same ingredient, no column
  claims to be the current one — the differences are then measured against
  what the plan really contains.

* The wording of every chip — diets, shelf life, the advertising label,
  servings, times, energy, prices, plan length, categories — is now a setting
  of its own under Settings → MixMyMeals → Chips. Patterns carry placeholders
  in curly braces, so "{count} Portionen" can become "für {count} Personen"
  without touching a translation file. A pattern that leaves out the value the
  chip exists to state is ignored, because it would read the same on every
  recipe.

Fixed:
* A meal plan read in the same request that added meals to it answered from
  the list it had loaded beforehand, and a recipe read straight after being
  saved returned the nutrition it had before the save. Both affected the REST
  API and the plan builder rather than ordinary page views.

= 3.33.1 =

Changed:
* Meals are no longer a search entry point. A meal is a planning of a recipe
  and shows that recipe's dish, scaled to the servings the plan asks for — so
  a visitor arriving from a search result landed on an arbitrary serving size
  instead of on the recipe, once per planning of it. Meal pages now carry
  "noindex, follow" and stay out of the site's own search. They remain fully
  reachable: every meal card on a plan links to one, and those links are
  unchanged.

= 3.33.0 =

Changed:
* Shelf life and storage now sit as a chip in the recipe and meal header,
  beside servings, time and energy, instead of as a sentence under the cooking
  instructions. A sentence alone after the last step read as the opening of a
  section that never came; the header already carries the facts about a dish
  and this is one of them.
* Both facts share one chip. "4 Tage" among stats is a duration with no
  subject — on a plan the same chip means the plan's length — so it reads
  "4 Tage im Kühlschrank", or "4 Tage haltbar" when no storage is recorded.

Removed:
* `[mmm_recipe_keeping]`. It existed only to print that sentence.

= 3.32.0 =

Changed:
* `[mmm_recipe_keeping]` now renders one plain sentence — "Hält sich im
  Kühlschrank 4 Tage." — instead of a labelled block. Two facts on two labelled
  lines announced a section; there is only ever one line of information here,
  and it belongs under the steps as a closing remark, not as a data table of
  size two.
* The sentences are translated whole, not assembled from a verb and a
  preposition at runtime. Word order and plural rules differ per language, and
  a translator handed the parts separately cannot fix a sentence that is put
  together after they are done.
* The child theme drops the heading above it for the same reason: a heading
  over a single sentence promises more than follows.

= 3.31.0 =

Added:
* `[mmm_recipe_keeping]` shows how the finished dish keeps — the shelf life and
  the storage condition together, since neither is an answer without the other.
  On a meal it resolves to the recipe it plans. Renders nothing until somebody
  has filled the fields in.
* A shelf life of 0 reads "Eat on the same day" rather than "0 days": a zero in
  a column of numbers looks like a missing value, and that is the one case that
  has to be unmistakable.

The bundled child theme places it under the cooking instructions on recipe and
meal pages, where the reader has just acquired the question.

= 3.30.0 =

Added:
* `POST /keeping/bulk` writes shelf life and storage for many recipes and
  ingredients in one call — mixed types, up to 500 entries, each judged on its
  own so one bad id does not lose the batch. MCP: `mmm_set_keeping`.
* The answer is deliberately thin: id, type and ok, with failures listed
  separately. Setting these fields one at a time returned each item's whole
  serialization — nutrition, every unit, every product — which is hundreds of
  times more data than the two fields being written.
* `dry_run` reports what would happen and writes nothing.

Omitting a key and sending null are different instructions: an absent key
leaves the stored value alone, `shelf_life_days: null` and `storage_type: ""`
clear it. Without that, filling in shelf lives would wipe every storage type
not mentioned in the same request.

= 3.29.0 =

Added:
* Storage conditions on recipes and ingredients — room temperature, cool and
  dry, refrigerated, frozen — beside the shelf life each already carries. Two
  separate fields again: flour lives in a cupboard, a flour-based sauce lives
  in the fridge.
* Available over REST and MCP as `storage_type`, with the same closed list.
  Anything outside it is rejected with 400, and a value written past the API
  is discarded on read as well.

Stored as post meta with a fixed vocabulary rather than as a taxonomy: this is
guidance about keeping food safe, and a taxonomy would let four spellings of
"refrigerated" grow side by side. There is also nothing to browse — nobody
arrives wanting every recipe that is stored frozen.

= 3.28.0 =

Added:
* `shelf_life_days` on recipes and ingredients over REST and MCP, readable and
  writable. It is serialised as `null` rather than omitted when nothing is
  specified, so a client can tell "nobody has said" from "keeps zero days" —
  which is the whole point of the field.
* Sending `null` clears it, `0` stores a zero, and negative or non-numeric
  values are rejected with 400.

= 3.27.0 =

Added:
* A shelf life in days on recipes and on ingredients — two separate fields
  holding two different quantities: how long the finished dish keeps, and how
  long the raw ingredient keeps. Neither is derived from the other.
* Both distinguish "not specified" from 0. An empty field stores nothing; a
  typed 0 stores a zero, because "has to be eaten the day it is made" is an
  answer and not the absence of one.

= 3.26.1 =

Changed:
* The "(optional)" note moved behind the amount. Wedged between the ingredient
  name and its quantity it split the two things a reader scans a list for.

= 3.26.0 =

Changed:
* The "(optional)" note now sits in its own `span.mmm-ingredient-optional`
  instead of running loose behind the ingredient name — on recipe steps, meal
  steps, both ingredient lists and the shopping list. Themes can style it; the
  bundled child theme sets it in italics.

The PDF and the subscription mails are untouched: neither can reach a theme
stylesheet, so their label stays as it was.

= 3.25.3 =

Fixed:
* The "optional" checkbox added in 3.25.2 did not save. The step save routine
  rebuilds the posted array field by field, and it only rebuilt id, amount and
  amount_id — so the flag was discarded before it reached storage.
* Step images were being cleared on every save, for the same reason: `image`
  was never rebuilt either. Anyone who saved a recipe lost its step images
  without any sign that it had happened.

= 3.25.2 =

Added:
* An "optional" checkbox per ingredient in the recipe step editor. The flag
  itself was already stored and already shown everywhere — on the recipe, in
  the shopping list, in the PDF and in the subscription mails — but nothing in
  the admin ever rendered the field, so it could only be set through the JSON
  importer.

Fixed:
* An ingredient added with "+ Add Ingredient" lost its amount on save. The
  template markup was missing the class the admin script uses to give a cloned
  row its field names, so the amount input went into the form without a name
  and the browser never submitted it.

= 3.25.1 =

Fixed:
* The recipe post type carried "Recipe Description" as its registered
  description, and WordPress hands that to get_the_archive_description() — so
  the placeholder was printed on the public recipe archive. It is empty now.
  The archive text editors maintain is the Recipe Archive Description setting.

= 3.25.0 =

Added:
* `POST /units/bulk-delete` removes several units in one call, each judged on
  its own: units in use are skipped and reported with the recipes holding
  them, the rest go. A batch does not fail as a whole because one unit turned
  out to be in use. MCP: `mmm_delete_ingr_units_bulk`.
* `dry_run` reports what would happen and deletes nothing, and the response
  counts `affected_recipes` — the recipes whose lines would lose their unit.
  With `force` that is the number to look at before agreeing, since those
  lines are read as grams afterwards.

The ids from the audit's `units_unused` group can be passed straight in.

= 3.24.1 =

Added:
* `/audit` reports `plugin_version`. Every group the running version knows is
  always in the response, empty ones with a count of zero — so a group missing
  entirely means the install is older than whatever named it, and that question
  now answers itself instead of looking like a broken endpoint.

= 3.24.0 =

Added:
* `units_unused` in the audit: every unit no recipe references, each entry
  naming the unit *and* its ingredient, since "Dose" on its own identifies
  nothing. Reachable over MCP through `mmm_audit`, and the listed units are
  exactly the ones `mmm_delete_ingr_unit` will remove without a fight.

Changed:
* Unit usage is now collected in a single pass over the recipes
  (`Mixmymeals_Ingredient_Amount::usage_map()`), which `get_usage()` and the
  audit both read. Asking each unit separately opened every recipe once per
  unit — bearable for the one unit an editor is looking at, a cross product for
  an audit over all of them.

= 3.23.0 =

Added:
* `GET /units/{id}/usage` says where a unit is used — which recipes, how many
  lines in each, their status — whether it is the ingredient's default unit,
  and whether it can be deleted. MCP: `mmm_unit_usage`.
* `DELETE /units/{id}`, which did not exist at all: units could be created and
  changed over the API but never removed. It refuses with 409 while recipes
  use the unit and names them; `force=true` deletes anyway. MCP:
  `mmm_delete_ingr_unit`.

Why the refusal matters: deleting a unit in use leaves those recipe lines with
an amount and no unit, and an amount without a unit is read as grams. "1.5
tins" silently becomes 1.5 g — on the recipe, in every plan using it and in the
shopping list.

Changed:
* `Mixmymeals_Ingredient_Amount::get_amount_usage_count()` is now derived from
  a new `get_usage()`, so the admin box and the API answer the same question
  from one scan instead of two.

= 3.22.0 =

Added:
* An ingredient now says over REST which product it resolves through:
  `reference_product`, `reference_product_label`, `product_count`, and
  `own_nutrition` / `own_diet_type` beside the resolved ones. A client could
  previously read 131 kcal without being able to tell whether that figure came
  from the ingredient or from a packet.
* `GET /product-impact?post_id=` returns the product comparison for a recipe
  (per serving), a plan (per day and person) or a meal, which resolves to the
  recipe it plans. MCP: `mmm_product_impact`.
* Two audit groups for product upkeep:
  `ingredients_products_without_reference` (products entered but none chosen,
  so they change nothing) and `products_without_source` (figures with no
  provenance, which go stale invisibly).
* `mmm_audit` in the MCP server. `/audit` had existed over REST for a long
  time but was not reachable from the tool that maintains the data.

= 3.21.0 =

Added:
* Where an ingredient has a reference product, the shopping list names it under
  the amount — brand first, since that is what a shelf is organised by. On the
  plan page, in the PDF and in the subscription mails, which also means it
  appears in the daily ingredient list.

Why: the figures on the page assume that packet. Somebody buying a different
one is not doing anything wrong, but they should be able to see which one the
numbers came from.

The Bring! export is deliberately untouched. Its lines are parsed by Bring!
itself, and a brand in them would change what the app splits on.

= 3.20.0 =

Added:
* The product comparison prints the brand under each product name. Two
  products of one ingredient are often told apart by the brand alone.

Changed:
* A brand is now required. On the ingredient screen the field is marked and
  enforced by the browser for existing products; a new row without one is not
  created, exactly as a row without a name is not.
* `POST /products` requires `brand`, and `mmm_create_product` with it. An
  update sending an empty brand no longer clears the stored one — blanking a
  brand is a loss, not an edit.

Note for API clients: creating a product without `brand` now returns 400. This
also closes a trap, since automation could otherwise create products the admin
screen would refuse to save until somebody filled the brand in by hand.

= 3.19.0 =

Added:
* The product comparison on plans and dishes carries a "Diet" row above the
  figures: what each product states, and what is in force in the leading
  column. It is left out entirely where nobody has classified anything.

Why above the numbers: "can I still call this vegan" is a different kind of
question from "how much fat", and it is the one people scan for.

Note: a product that states nothing gets a dash here, exactly as it does for
nutrition. The ingredient's own classification would stand in that case, but
printing it in the product's column would read as that brand claiming it — and
this is a label people act on.

= 3.18.1 =

Fixed:
* Removed an unused, unescaped parameter from the products meta box. No caller
  ever passed it, so nothing could reach the page through it, but it tripped
  the escaping check and would have been a hole the day somebody used it.

= 3.18.0 =

Added:
* Products carry their own vegan/vegetarian classification. Where one is set,
  it overrides the ingredient's — in both directions, since a product can be
  stricter (pasta with egg) or more precise (a margarine certified vegan where
  the ingredient was only marked vegetarian). Editable on the ingredient
  screen, and available over REST and MCP as `diet_type`.
* Left empty, the packet counts as unchecked and the ingredient's own
  classification stands.

Why: this varies by brand, not by ingredient. Pasta is vegan until a
manufacturer puts egg in it, cheese vegetarian until one uses animal rennet.

Note: because it resolves through the reference product, choosing one can
change what a recipe is labelled — including making it vegan. That label is
only as good as the product data behind it.

= 3.17.0 =

Changed:
* The product currently supplying an ingredient's figures is marked with an
  asterisk after its name instead of a "currently used" line under it. The
  heading row is one line again.
* Explaining the asterisk is the theme's job. The marker carries the class
  `mmm-product-impact__ref-mark`, so a caller can look for it and print a
  legend only when a table actually earned one — the bundled child theme does
  exactly that under both tables.

Note for anyone calling the shortcodes outside the bundled theme: the words
"currently used" are no longer in the output. Add a legend, or the asterisk
stands unexplained.

= 3.16.0 =

Changed:
* The product comparison has no separate "as planned" column any more. The
  reference product leads the table with its own absolute figures instead of a
  column of ±0, and where no reference is set a "Typical values" column of the
  ingredient's own figures takes that place. Every other column still states
  its difference to it.

Why: the extra column repeated what the leading one already said. Whichever
product is in use is what the dish or plan currently holds, so it can carry
those numbers itself.

= 3.15.0 =

Added:
* `[mmm_meal_product_impact]` brings the product comparison to a single meal,
  stated per serving. Placed under the nutrition table on the meal page. It
  answers for a recipe too, so a meal resolves to the recipe it plans.

Why per serving: a plan spreads one meal across its whole length, so a real
choice can arrive as a couple of kilocalories a day. On the dish itself there
is nothing to spread it over — 150 g of an ingredient and a product 28 kcal
richer per 100 g is the full 42 kcal, on the plate.

Changed:
* The comparison itself moved to `Mixmymeals_Product::impact_groups()` and the
  table markup to one renderer, so the plan view and the dish view differ in
  their divisor and two column headings and in nothing else.

= 3.14.2 =

Changed:
* `[mmm_meal_plan_product_impact]` renders the tables only. The sentence
  introducing them moved to the theme, next to the heading it belongs with, so
  both are worded and translated in one place.

Note for anyone calling the shortcode outside the bundled child theme: it no
longer emits that sentence. Add your own if the tables need introducing.

= 3.14.1 =

Removed:
* The per-meal figure added in 3.14.0. Two numbers stacked in every cell of a
  seven-row table made it hard to read, and a comparison nobody can scan is
  worth less than the information the second number added.

= 3.14.0 =

Added:
* A second, smaller figure under every difference: the same difference in the
  meal the ingredient appears in. Removed again in 3.14.1.

= 3.13.1 =

Fixed:
* The product comparison counted a recipe that uses an ingredient in several
  steps as several meals — "in 3 meals" for one dish that seasons in three
  places. The quantities were always right; only the count beside the
  ingredient name was wrong.

= 3.13.0 =

Changed:
* A figure a product does not state takes no part in the plan's product
  comparison at all. It shows a dash, and it is left out of the ranking and of
  the test that decides whether an ingredient is worth listing — no substitute
  value is used anywhere.

Why: 3.12.0 filled the gap with the ingredient's typical value and printed the
resulting difference. That figure is true of the plan and false of the packet,
and this table is about packets. A product that says nothing about fat should
not have a fat number attributed to it.

Note: where a product does state a figure, the comparison remains exact — that
is still verified against actually selecting the product and recomputing.

= 3.12.2 =

Fixed:
* The daily average counted a day by whether it had any energy, not by whether
  it had meals. Two consequences: filling in one recipe's nutrition could
  *lower* a plan's reported daily average, because the divisor grew by a whole
  day while the sum grew by a little; and the product comparison could promise
  a figure the plan would never reach, because a day at zero kcal that a
  product lifts above zero joins the divisor and the average stops being
  linear.

Note: on plans that contain meals whose recipes carry no nutrition figures yet,
the daily average shown on the plan page, in the subscription mail and in the
PDF will drop. Those days were being left out of the division; they are days
somebody eats on, and they now count.

= 3.12.1 =

Fixed:
* The "as planned" column of the product comparison averaged the plan over its
  full length, while the nutrition overview two sections above averaged over
  the days that carry meals. On a seven-day plan with meals on three days the
  same page stated 51 and 120 kcal for the same thing. The rule now lives in
  one method, `Mixmymeals_Meal_Plan::calc_nutrition_per_day_person()`, which
  the overview, the subscription mail, the PDF and the comparison all call.

= 3.12.0 =

Changed:
* The product comparison on a plan covers all seven nutrition figures, not only
  energy. One column holds the plan as it stands, one per product holds the
  differences.
* An ingredient now qualifies when any figure moves, not only when energy does.
  Two hams with identical calories and twice the salt used to be dropped from
  the list entirely — which hid exactly the choice worth making.

Fixed:
* A product silent on a figure showed no difference at all, even when the
  product it would replace stated one. Switching to it really does change the
  plan, because the ingredient's typical value carries over. The figure is now
  shown, faded, with a note saying where it comes from.

= 3.11.0 =

Added:
* `[mmm_meal_plan_product_impact]` answers what the choice of product is worth
  over a week. For each ingredient of the plan that has several products, it
  gives the resulting energy per day and person and the difference to what the
  plan currently states. Rendered under the shopping list.
* Ingredients whose products do not actually pull the figure apart are left
  out, and a product that states no energy of its own gets a dash rather than a
  zero difference — it cannot move the plan because nobody wrote its figure
  down, which is not the same as it matching.

Why: the ingredient page shows what a packet contains per 100 g. What somebody
standing in a shop can act on is what a week comes to, and that is arithmetic
nobody should have to do in their head.

Note: the figures are computed as differences, not by re-running the plan once
per product. Nutrition is linear in weight, so the two agree exactly; this was
verified against actually setting each product as the reference.

= 3.10.2 =

Changed:
* Sources are back in the heading cell of their product column, as "Source: …",
  instead of a footnote list under the table. They sit with the product they
  belong to. The line is width-capped so one wordy source cannot set the width
  of its column and squeeze the figures in all the others.

= 3.10.1 =

Removed:
* The "Comparison basis" chip on the anchor column of the product table. Which
  column the arithmetic starts from is not a property of the product, and the
  note under the table already says which one it is. As a chip beside a brand
  name it looked like a distinction the brand had earned.

= 3.10.0 =

Changed:
* The product table now states figures relative to one column instead of seven
  sets of absolutes. That column keeps its own numbers; the rest read +12,0 g
  or −30 kcal. The reference product anchors it, or failing that the
  lowest-energy product, and the anchor moves to the front.
* A difference needs a figure at both ends. If either the anchor or the product
  is silent on a value, the cell stays a dash rather than counting the silence
  as zero.

Why: seven columns of absolutes make the reader do the subtraction, which is
the one thing a comparison table exists to spare them. Differences are also
deliberately uncoloured — more protein and more salt would get the same green,
and a colour here reads as a verdict on a brand.

= 3.9.1 =

Fixed:
* The heading cells of the product table set the brand and the package size in
  type too small to read, and ran the lines together into one string wherever
  the plugin stylesheet had not arrived. They are block elements now and need
  no stylesheet to stack.
* Sources moved out of the heading cells into a footnote list below the table.
  A real one is a sentence, not a caption, and in the heading it either shrank
  to nothing or pushed the figures off the screen.

= 3.9.0 =

Added:
* `[mmm_ingredient_products]` lists an ingredient's products on its page: one
  column per product with the figures its packet states, the brand, the package
  size and the source of the numbers. The product the ingredient's own nutrition
  comes from is marked.
* A value the product does not state shows a dash, never a zero.

Why: an ingredient carries figures good enough to plan with, a product carries
what one packet says. Yoghurt at 0.1 % or 10 % fat is not a rounding difference,
and a reader deciding which one to buy needs to see the packets, not an average
of them.

= 3.8.2 =

Removed:
* `[mmm_nutrition_comparison]`, added in 3.8.0, and with it the parameter that
  let a caller read an ingredient's own figures past its reference product.
  Ingredient nutrition again has exactly one read path.

Why: putting two sets of figures next to each other asked the reader to
adjudicate between them, which is not something a recipe page can help with.
The nutrition table states one number, which is what the reference product is
for.

= 3.8.1 =

Fixed:
* The nutrition comparison sat flush against the recipe's own nutrition table,
  so its header row read as a second header inside that table rather than the
  start of a different one. It has a heading and space above it now.

= 3.8.0 =

Added:
* `[mmm_nutrition_comparison]` puts a recipe's two nutrition figures side by
  side: the typical values of its ingredients, and what the products they
  resolve through actually state. It names the products responsible and lists
  only the values that differ.
* It renders nothing unless a recipe has a reference product somewhere and the
  two figures really diverge, so an ordinary recipe page is unaffected.

Why: a single figure claims a precision it does not have. "480 kcal" reads as
a measurement when it is a plan built from average ingredients. Showing what
one specific yoghurt does to that number is more honest than either figure on
its own.

= 3.7.4 =

Fixed:
* The nutrition fields in the product table wrapped onto two lines and the
  grey stripe stopped a third of the way across, because the flex layout sat
  on the table cell itself — display:flex takes a <td> out of the table and it
  collapses to the width of the first column. The layout now sits in a
  container inside the cell and the row uses its full width.
* The nutrition block carries a caption, the two action controls share one
  cell, and two labels that were still English are translated.

= 3.7.3 =

Changed:
* The product table alternates white and grey like the units table above it,
  but per product rather than per row — a product spans two rows, and striping
  each row separately is what made it unreadable in the first place.
* Product name, brand and package weight now carry labels instead of relying
  on placeholder text, which disappears as soon as a field is filled and left
  the columns unidentifiable on a table with entries in it.

= 3.7.2 =

Fixed:
* The product table on the ingredient screen was hard to read. It used
  WordPress's striped table style, which alternates the background per row —
  and a product occupies two of them, so every product came out cut in half,
  its top row light and its bottom row dark, while neighbouring products ran
  together. Products are now separated by a rule instead, and the seven
  nutrition fields line up in even columns rather than drifting with the
  length of their labels.
* The styling moved from inline attributes into assets/css/admin.css, where
  it can be overridden.

= 3.7.1 =

Changed:
* The radio button on the ingredient's product table no longer says
  "Reference" but "Use this product's nutrition". That is all it does — it
  does not mark a product as the one to buy, and the old label invited the
  opposite reading. Package weight, brand and source are stored but not yet
  used by anything.
* The explanation above the table is shorter.

= 3.7.0 =

Added:
* Products over REST: `POST /products`, `GET|POST|DELETE /products/{id}` and
  `GET /ingredients/{id}/products`, all in the mixmymeals/v1 namespace. The
  nutrition object touches only the keys it carries, and `null` deletes one
  rather than storing a zero — the response keeps nulls for the same reason.
* The same five as MCP tools, so an assistant can record what a packet says
  and mark which product an ingredient resolves through.

= 3.6.0 =

Added:
* Products can now be edited on the ingredient screen, in a table below the
  units: name, brand, package weight, the seven nutrition values and a note
  on where those figures came from. One product per ingredient can be marked
  as the reference, and the ingredient then uses its values — value by value,
  so anything the packet does not state still comes from the ingredient.
* Nutrition fields left empty delete the stored value rather than saving a
  zero. "Not stated on the packet" and "contains none" are different claims,
  and the second is a claim about somebody else's product.

= 3.5.0 =

Added:
* Products: a concrete, branded article an ingredient can be bought as, with
  its own nutrition, package weight, brand and a note on where the figures
  came from. An ingredient can point at one of them as its reference, and its
  nutrition then resolves through it — key by key, so a packet stating only
  energy still leaves the rest to the ingredient.
* This is the data model only. Nothing changes until products are created,
  and there is no editing screen yet.

= 3.4.1 =

Changed:
* The mail template rate-recipes.html is now rate-recipe.html. Existing
  sending rules are redirected automatically when they are read, including
  ones in a subfolder, so nothing needs changing by hand — and a settings
  backup restored later is covered too. Without that a rule pointing at the
  old name would simply have stopped sending, with nothing but a line in the
  log to show for it.

= 3.4.0 =

Added:
* {{ plan_rating_links_html }} and {{ plan_upload_links_html }}: rating and
  photo-upload links for **every** recipe in the plan, not just one day's.
  after-end.html uses them, which is the point — a mail sent once the plan is
  over asks about everything that was cooked, and the existing per-day blocks
  would only have offered the last day's recipes.
* The per-day blocks {{ rating_links_html }} and {{ upload_links_html }} are
  unchanged.

= 3.3.0 =

Added:
* Two sending rules for the end of a plan: "On the last plan day" and "The
  day after the last plan day", with templates on-end.html and
  after-end.html. The first is a normal day mail that says it is the last
  one; the second looks back and asks for a rating or a photo.
* Note for anyone writing their own after-end template: the plan is over by
  then, so there is no "today" to talk about. Every day-scoped placeholder —
  the weekday, the meal list, the ingredients, all day_* values — resolves to
  the LAST plan day, not to the day the mail goes out.

= 3.2.3 =

Changed:
* The sending rule "The evening before each plan day" is now called "The day
  before each plan day". Every rule carries its own time, defaulting to 08:00,
  so the old label promised an evening the rule never set — out of the box it
  sent at eight in the morning.

Fixed:
* That rule and "The day before the plan starts" both produced a mail on the
  day before the plan begins, both about its first day, so a site running
  both sent two mails covering the same ground. The more specific one wins
  now and the per-day rule starts a day later. On its own it is unchanged and
  still covers the first day — otherwise nobody would hear about it in
  advance. Existing subscriptions drop the redundant slot at their next
  resync unless it has already gone out.

= 3.2.2 =

Fixed:
* The day summary in subscription mails, {{ day_summary_html }}, read "Day
  total per person: ~1 951 kcal · approx. 7,40 €". A price has no business
  under a heading that promises what you take in that day, least of all as
  the most prominent line above the meals. It now carries the day's
  nutrition. The price is unchanged as data and still available on its own
  through {{ day_cost }} and {{ currency }}.
* The summary and the nutrition line were assembled separately — one from the
  energy the meal list added up itself, the other from the plan's nutrition —
  so the same day could be described by two different numbers. Both are built
  from one source now.

= 3.2.1 =

Fixed:
* Paragraphs in subscription mails came out at different sizes and in
  different colours depending on whether the plugin or the template had
  written them. The body's size and colour lived only in the mail layout's
  <style> block, on a class — the least reliable place to put them, since
  Gmail drops that block in clipped and forwarded messages and Outlook
  handles class selectors poorly. When it went, paragraphs carrying no inline
  style of their own fell back to the client's defaults while the rest kept
  theirs. Size, line height and colour now sit inline on the body container,
  in both the plugin's layout and any theme override, and the paragraphs the
  plugin renders no longer carry their own.
* Tables, captions and the day summary keep their smaller, quieter styling on
  purpose: a nutrition table is tabular data laid out like a food label, not
  prose.

= 3.2.0 =

**How divisible a unit is, is now a single number on the unit.** 1 means
whole ones only, 2 that it can be halved, 4 quarters, and an empty field that
any amount will do. The "Indivisible" checkbox next to it is gone: it said
the same thing as a divisor of 1, which meant the state had two
representations, every save had to reconcile them, and entering 1 in the
field did the *opposite* of what it reads like, because the divisor only
counted from 2 upwards.

Existing units are converted automatically on the first visit to wp-admin
after the update — the flag becomes a divisor of 1. Nothing changes about how
a unit behaves at any point: until the conversion runs, the old flag is still
read, so a site whose admin nobody opens keeps working exactly as before. As
with any data migration, take a backup first.

Changed:
* `set_indivisible( false )` now clears the restriction entirely rather than
  only the "whole ones" part. False means "may be split", so leaving a
  divisor of 4 in place would contradict the caller.
* Over REST a unit carries `divisor` and a read-only `step_divisor`.
  `is_indivisible` still works, reads as `divisor === 1` and writes as 1 or
  0; prefer `divisor`, which wins when both are sent.
* The MCP server gained `divisor` on `mmm_create_ingr_unit` and
  `mmm_update_ingr_unit`; its `is_indivisible` parameter is marked deprecated.
* The readme and the code no longer name specific regulations for the
  nutrition reference intakes or the disclosure label. What a site has to
  show, and in what form, depends on its content and market — not on a
  plugin.

= 3.1.0 =

Added:
* Subscription mails can report the full nutrition picture, not just energy
  and the three macros. Sugars, fibre and salt were being calculated for
  every plan and then discarded. New placeholders cover a food-label style
  table with each figure's share of the reference intake, a breakdown per
  dish, and the plan's daily average — see templates/emails/example.html.
* Reference intakes for an average adult (2 000 kcal) put the numbers in
  context: "1 045 kcal" says little, "52 % of the reference intake" says what
  it means. The values and the accompanying note are filterable via
  `mixmymeals_reference_intake` and `mixmymeals_reference_intake_note`, so a
  site aimed at a specific audience can set its own.
* Ingredient units can state how finely they may be split. A tin that can be
  halved carries a divisor of 2, so a scaled recipe asks for half a tin
  rather than 0.37 of one, and the shopping list buys in halves too.

Fixed:
* The plan page computed its nutrition differently from the mail and the
  PDF — a servings-weighted average against one serving of each meal — so
  the same plan quoted different figures depending on where you read it. All
  three now average the days that actually carry meals.
* The shopping list treated any unit that was not marked indivisible as
  something to weigh, and printed counts as whole numbers. Half a tin came
  out as a whole one.

Changed:
* One set of nutrition labels everywhere. Recipes said "Calories" where
  plans said "Energy" and the calculator said "Carbs", for the identical
  figure. Tables now follow a food label's order and wording, with "of which
  sugars" as the sub-entry of carbohydrate.
* The PDF gained a table for an average day with all seven figures and their
  share of the reference intake, after the existing per-day breakdown.

= 3.0.0 =

**Instagram and Pinterest publishing has moved into a separate plugin,
MixMyMeals Social.** Install and activate it to keep posting; your connected
accounts, scheduled posts and settings carry over untouched, because the
option names, user meta and cron hooks are unchanged. Without it this plugin
keeps every recipe, plan and shopping-list feature but posts nowhere — and it
now makes no external API calls at all.

Added:
* Disclosure labelling for commercial and guest content. A recipe or plan can
  be marked as a paid placement, a cooperation or an unpaid guest
  contribution; the first two render a visible "Advertisement" label, guest
  contributions a quieter "Guest author" one. The label appears on single
  pages, archives, browse cards and inside meal plans, and a meal inherits it
  from the recipe it plans. The label is not meant as a display option: its
  structural CSS cannot be themed away, so a disclosure a site owner has
  chosen to show cannot be hidden again by accident.
* External authors are now entities rather than free text repeated on every
  post. An author exists once, is picked from a list on the recipe or plan,
  and is edited on their own screen — so a bio or link is corrected in one
  place instead of per contribution. Existing author fields are migrated
  automatically; the old values are left in the database untouched.
* A `mixmymeals_admin_board_services` filter so add-ons can contribute their
  own actions to the frontend admin panel, plus filters for settings tabs,
  settings fields and cleanup reporting.

Fixed:
* A fatal error that broke saving recipes entirely. Every meta box on the
  recipe screen — steps, details, equipment, image credit — silently failed
  to save.
* Translations were loaded too early for WordPress 6.7, which refused them
  and fell back to the English source strings.
* `[mmm_cart_badge]` printed the translation catalogue's header metadata into
  the page on translated sites, because its default labels were an empty
  translatable string.

Changed:
* The PDF stylesheet moved to `assets/css/pdf.css` and is now editable like
  any other stylesheet.
* `mmm_write_to_log()` is now `mixmymeals_write_to_log()`.
* The plugin passes WordPress's Plugin Check with no findings.

= 2.4.1 =
* Fixed a fatal error on the recipe list in wp-admin (and on any page showing
  recipe nutrition) when an ingredient had a nutrition field that was never
  filled in. PHP 8 rejects '' in arithmetic, where PHP 7 silently read it as 0.
  Mixmymeals_Ingredient::get_nutrition() now returns float|null instead of raw
  strings, and every nutrition calculation casts defensively.

= 2.4.0 =
* New Mixmymeals_Meal_Plan::build_short_amount_string() for teasers: one figure
  per ingredient instead of compounds like "3x EL + 400 g (~ 430 g)", switching
  to kg from 1000 g. The full breakdown stays on the meal plan page.
* Internal: the cart and PDF AJAX endpoints and the amount formatter moved out
  of Mixmymeals_Shortcodes into the classes they belong to. No behaviour change.

= 2.3.0 =
* Settings: own tabs for Ingredients and Equipment. Their URL slugs and archive
  texts moved out of the General tab, together with the affiliate disclosure.
  Option names are unchanged, so saved values are kept.
* Equipment REST: POST and PUT now accept content, categories, affiliate_links
  and featured_image, so a piece of equipment can be created complete in one
  call. Responses include content and featured_image.

= 2.2.0 =
* Equipment: affiliate links per piece of equipment (shop, URL, note) with an
  editor meta box, output on the equipment page as buttons carrying
  rel="sponsored nofollow noopener", and a configurable advertising disclosure
  under Settings -> MixMyMeals.
* Equipment REST responses now include categories, affiliate links and the
  recipe count; both can be written via PUT /equipment/(id).

= 2.1.0 =
* Equipment: new category taxonomy, the archive index is grouped by category,
  and the admin list shows category and recipe count per piece of equipment.
* The recipe lookup for a piece of equipment moved into Mixmymeals_Equipment
  and now caches the full result, so callers with different limits no longer
  share a mismatched cache entry.

= 2.0.1 =
* Meal plan builder: an empty planner now points to the recipe archive instead
  of showing an empty drop zone.

= 2.0.0 =
* Instagram publishing: branded carousels for meal plans (cover, overview, one
  slide per recipe, CTA), single-image recipe posts, per-slide captions,
  immediate and scheduled posting with fresh image rendering.
* Pinterest publishing: immediate and scheduled pins with backlinks.
* Per-user social accounts: users connect their own Instagram/Pinterest
  credentials on their profile; scheduled posts run with the scheduler's
  account. Scheduled-posts overview across channels and users.
* Frontend admin panel on plan/recipe pages (post, schedule, draft, trash).
* SEO: schema.org Recipe rich-results markup, plan ItemList, Open Graph and
  Twitter Card tags.
* Related recipes (“goes well with” / “fits with”) and “featured in these
  plans” sections on recipe pages.
* Community meal photos with moderation and EXIF stripping; ratings extended
  to newsletter subscribers.
* Cookbook-style rendering for recipes without a photo (placeholder image is
  no longer shown on the site).
* Per-day calorie totals in plans and the frontend planner.
* Configurable notices on all visitor-input forms.
* GDPR: personal-data exporters/erasers, suggested privacy-policy text, clean
  uninstall with optional data removal.
* REST API for recipes, ingredients, units, plans, meals, recipe search,
  post status and publishing; AI-friendly one-call meal-plan builder.
* Rewrite rules flush automatically on activation (no more 404s on fresh
  installs); SameSite cart cookie; large dead-code cleanup.

= 1.4.3 =
* Harden REST API authorisation (capability and per-object checks).
* Sanitise recipe step content on write.
* Various security and code-quality improvements.

== Upgrade Notice ==

= 3.35.0 =
Recipes can now be filtered by diet, time and the ingredients you have.

= 3.34.0 =
Diets became a taxonomy, a product chosen in a recipe now counts everywhere, and every chip's wording is configurable.

= 3.33.1 =
Meal pages are no longer indexed; the recipe answers for the dish.

= 3.33.0 =
How a dish keeps moved into the header as a chip.

= 3.32.0 =
How a dish keeps now reads as one sentence.

= 3.31.0 =
Recipe and meal pages can show how the dish keeps.

= 3.30.0 =
Shelf life and storage can be set in bulk.

= 3.29.0 =
Recipes and ingredients can each record how they are stored.

= 3.28.0 =
Shelf life is readable and writable over REST and MCP.

= 3.27.0 =
Recipes and ingredients can each carry a shelf life in days.

= 3.26.1 =
The "(optional)" note follows the amount instead of the name.

= 3.26.0 =
The "(optional)" note is now styleable via span.mmm-ingredient-optional.

= 3.25.3 =
Fixes the optional checkbox not saving, and step images being lost on save.

= 3.25.2 =
Ingredients can be marked optional again, and newly added ingredients keep their amount.

= 3.25.1 =
Removes a placeholder text that showed on the recipe archive.

= 3.25.0 =
Units can be deleted in bulk, with a dry run.

= 3.24.1 =
The audit says which plugin version answered.

= 3.24.0 =
The audit lists units no recipe uses.

= 3.23.0 =
Units can be checked for use and deleted over the API.

= 3.22.0 =
Products are fully reachable over REST and MCP, including the comparison and two audit checks.

= 3.21.0 =
Shopping lists name the chosen product.

= 3.20.0 =
Brands are shown in the comparison and are now required. API clients must send `brand` when creating a product.

= 3.19.0 =
The product comparison shows vegan/vegetarian per product.

= 3.18.1 =
Internal cleanup in the products meta box.

= 3.18.0 =
Products can carry their own vegan/vegetarian classification.

= 3.17.0 =
An asterisk replaces the "currently used" label; the theme supplies its legend.

= 3.16.0 =
The product comparison drops its separate "as planned" column.

= 3.15.0 =
Meals show the product comparison too, per serving.

= 3.14.2 =
The product comparison's intro sentence moved to the theme.

= 3.14.1 =
Reverts the per-meal figure; the comparison reads one number per cell again.

= 3.13.1 =
Fixes the meal count beside an ingredient in the product comparison.

= 3.13.0 =
Unstated product figures show a dash instead of a substituted value.

= 3.12.2 =
Daily averages count days by their meals. Plans with meals lacking nutrition data will show a lower average.

= 3.12.1 =
Fixes a daily average that disagreed with the plan's own nutrition overview.

= 3.12.0 =
The plan's product comparison covers all seven nutrition figures.

= 3.11.0 =
Meal plans show what the choice of product is worth over a week.

= 3.10.2 =
Sources move back into the product column heading.

= 3.10.1 =
Drops the "Comparison basis" chip.

= 3.10.0 =
The product table reads as differences to one column now.

= 3.9.1 =
Readable headings and footnoted sources in the product table.

= 3.9.0 =
Ingredient pages now list the products behind an ingredient.

= 3.8.2 =
The nutrition comparison from 3.8.0 is gone again.

= 3.8.1 =
Spacing and a heading for the nutrition comparison.

= 3.8.0 =
Adds a shortcode comparing a recipe's typical nutrition with the products
chosen for its ingredients. Place it where you want it; it stays silent
otherwise.

= 3.7.4 =
Layout fix for the product table on the ingredient screen.

= 3.7.3 =
Presentation only, on the ingredient's product table.

= 3.7.2 =
Readability fix for the product table on the ingredient screen.

= 3.7.1 =
Wording only, on the ingredient's product table. No behaviour changes.

= 3.7.0 =
Adds REST endpoints and MCP tools for products. Nothing changes for existing
installations.

= 3.6.0 =
Adds a Products table to the ingredient screen. Nothing changes until you
create products and mark one as a reference.

= 3.5.0 =
Adds the data model for products behind ingredients. Inert until you create
any; no action needed.

= 3.4.1 =
Renames one mail template; existing rules are redirected automatically. If
you keep your own copy of rate-recipes.html in a theme, rename it to
rate-recipe.html.

= 3.4.0 =
The end-of-plan mail now lets subscribers rate every recipe of the plan, not
only the last day's. No action needed.

= 3.3.0 =
Adds two sending rules for the end of a plan. Nothing changes unless you add
them under Settings → Subscriptions.

= 3.2.3 =
A sending rule was renamed and no longer duplicates the mail sent the day
before a plan starts. Check Settings → Subscriptions if you run both rules;
subscribers get one mail that morning instead of two.

= 3.2.2 =
The day summary in subscription mails shows nutrition instead of a price. If
your template uses {{ day_summary_html }} and you want the cost somewhere,
{{ day_cost }} still provides it.

= 3.2.1 =
Fixes uneven text size and colour in subscription mails. Worth updating if
you send them; nothing else changes.

= 3.2.0 =
A unit's divisibility is one number now (1 = whole ones, 2 = halves); the
Indivisible checkbox is gone. Existing units convert automatically on the
first wp-admin visit and behave unchanged until then. Take a backup, as with
any data migration.

= 3.1.0 =
Nutrition figures now agree across recipes, plans, the PDF and mails, and are
shown against a reference intake. Expect changed numbers and labels on
the plan page, which had been averaging differently. Units can now be split
in halves or quarters.

= 3.0.0 =
Instagram and Pinterest publishing has moved to the separate MixMyMeals Social
plugin. Install and activate it before updating if you post to either — your
accounts, scheduled posts and settings carry over. Everything else is
unaffected. Also fixes a fatal error that prevented recipes from saving.

= 2.0.0 =
Major release: social publishing (Instagram/Pinterest) with per-user accounts,
SEO rich results, GDPR tooling and a large cleanup. Users who post to social
media must store their credentials on their WordPress profile.

= 1.4.3 =
Security hardening for the REST API and recipe content. Update recommended.
