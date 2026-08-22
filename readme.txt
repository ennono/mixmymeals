=== MixMyMeals ===
Contributors: frickemedia
Tags: meal planning, recipes, shopping list, nutrition, meal plan
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 3.21.0
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
