=== MixMyMeals ===
Contributors: frickemedia
Tags: meal planning, recipes, shopping list, nutrition, meal plan
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 3.1.0
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
  A unit can state how finely it may be split — whole ones only, or in
  halves, quarters and so on, so a recipe asks for half a tin instead of an
  arbitrary fraction of one.
* **Meals & meal plans** spanning any range of weekdays, grouped by meal type
  (breakfast, lunch, dinner, snack), with per-day calorie totals.
* **Shopping list generator** that scales ingredient amounts to the chosen
  servings and groups items by ingredient category. Quantities are rounded up
  once over the whole plan, so two recipes each needing half a tin buy one
  tin, not two.
* **Nutrition in context**: every figure can be shown against the reference
  intake of an average adult (EU Regulation 1169/2011), laid out like the
  panel on a food packet — on recipes, on plans, in the PDF and in the
  subscription mails, all quoting the same numbers.
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

= 3.1.0 =

Added:
* Subscription mails can report the full nutrition picture, not just energy
  and the three macros. Sugars, fibre and salt were being calculated for
  every plan and then discarded. New placeholders cover a food-label style
  table with each figure's share of the reference intake, a breakdown per
  dish, and the plan's daily average — see templates/emails/example.html.
* Reference intakes for an average adult (EU Regulation 1169/2011, fibre per
  DGE) put the numbers in context: "1 045 kcal" says little, "52 % of the
  reference intake" says what it means. Values and the accompanying note are
  filterable via `mixmymeals_reference_intake` and
  `mixmymeals_reference_intake_note`.
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
  from the recipe it plans. In German-speaking markets this is a legal
  requirement (§ 5a UWG, § 22 MStV), not a display option, which is why its
  structural CSS cannot be themed away.
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

= 3.1.0 =
Nutrition figures now agree across recipes, plans, the PDF and mails, and are
shown against the EU reference intake. Expect changed numbers and labels on
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
