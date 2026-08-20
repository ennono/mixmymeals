=== MixMyMeals ===
Contributors: frickemedia
Tags: meal planning, recipes, shopping list, nutrition, meal plan
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 3.7.1
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
