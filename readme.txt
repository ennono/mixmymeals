=== MixMyMeals ===
Contributors: frickemedia
Tags: meal planning, recipes, shopping list, nutrition, meal plan
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 2.4.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build recipes, plan weekly meals and generate shopping lists — with a frontend planner, nutrition data, PDF export and social publishing.

== Description ==

MixMyMeals is a meal-planning toolkit for WordPress. It lets you manage a recipe
and ingredient database, assemble weekly meal plans, and automatically generate
categorised shopping lists.

Visitors can build their own plan in a frontend planner, review the combined
shopping list and nutrition totals, and export everything as a PDF. Publish
your plans and recipes to Instagram and Pinterest — immediately or on a
schedule — with automatically generated, branded images.

= Features =

* **Recipes** with step-by-step instructions, per-step ingredients and images.
* **Ingredients** with nutrition values (calories, fat, carbs, protein, sugar,
  fibre, salt), configurable units and an optional price per 100 g.
* **Meals & meal plans** spanning any range of weekdays, grouped by meal type
  (breakfast, lunch, dinner, snack), with per-day calorie totals.
* **Shopping list generator** that scales ingredient amounts to the chosen
  servings and groups items by ingredient category.
* **Frontend planner** for logged-in users and (optionally) guests, with a
  server-side cart, live day totals and configurable size limits.
* **PDF export** of a meal plan: overview, shopping list and all recipes.
* **Meal-plan e-mail subscriptions** with GDPR-friendly double opt-in.
* **Instagram publishing**: branded carousel images generated from meal plans
  (cover, overview, one slide per recipe, call-to-action) or single recipe
  images — post immediately or schedule; images render fresh at publish time.
* **Pinterest publishing**: pin plans and recipes with a backlink to your
  site, immediately or scheduled.
* **Per-user social accounts**: every user connects their own
  Instagram/Pinterest credentials on their profile and posts to their own
  channels; a scheduled-posts overview covers all channels and users.
* **Frontend admin panel** on plan and recipe pages (admins only): post,
  schedule, set to draft or trash without leaving the page.
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
   (slugs, public planner mode, limits, social image fonts).
4. To publish to Instagram/Pinterest, each user stores their own access token
   on their WordPress profile (Users → Profile → MixMyMeals: Social accounts).

== Frequently Asked Questions ==

= Does it require any external service? =

No — all core features work with your WordPress database alone. Only the
optional Instagram/Pinterest publishing talks to the Meta/Pinterest APIs, and
only when a user connects their own account.

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

= 2.0.0 =
Major release: social publishing (Instagram/Pinterest) with per-user accounts,
SEO rich results, GDPR tooling and a large cleanup. Users who post to social
media must store their credentials on their WordPress profile.

= 1.4.3 =
Security hardening for the REST API and recipe content. Update recommended.
