<?php
/**
 * Yoast SEO replacement variables for MixMyMeals data.
 *
 * @package MixMyMeals Plugin/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mixmymeals_Yoast
 *
 * Registers MixMyMeals placeholders with Yoast SEO, so a title or meta
 * description template can say "%%title%% — %%mmm_servings%% Portionen,
 * %%mmm_calories%%" instead of being typed out per post.
 *
 * Inert without Yoast: the only entry point is the action
 * `wpseo_register_extra_replacements`, which nothing but Yoast fires.
 *
 * Every value comes from the domain getters — Mixmymeals_Recipe,
 * Mixmymeals_Meal_Plan — and never from post meta directly. A placeholder that
 * read the database on its own would be the second channel computing a figure
 * this plugin already computes, and the pair would drift; the shelf life is the
 * clearest case, where only the getter knows that "not stated" and "zero" are
 * different answers.
 *
 * Unknown values return '' rather than a dash or a zero: Yoast pastes the
 * result into a sentence somebody wrote, and "0 kcal" in a search result is a
 * claim, where nothing at all is just a shorter title. Worth knowing when
 * writing the template — put the separator inside the variable's sentence, not
 * between two variables that may both be empty.
 */
class Mixmymeals_Yoast {

	/**
	 * Prefix of every variable this class registers. Yoast reserves cf_ and
	 * ct_ for custom fields and taxonomies; everything else is free, and a
	 * prefix keeps these apart from another plugin's.
	 *
	 * @var string
	 */
	const PREFIX = 'mmm_';

	/**
	 * Attaches the registration. Called once per request from mixmymeals.php,
	 * the same way the cook counter and the browse views are wired up.
	 *
	 * @return void
	 */
	public static function register(): void {

		static $done = false;

		if ( $done ) {
			return;
		}

		$done = true;

		add_action( 'wpseo_register_extra_replacements', array( __CLASS__, 'register_replacements' ) );
	}

	/**
	 * The variables, in the order they appear in Yoast's help table.
	 *
	 * Post type in the key is deliberate: 'mmm_plan_days' reads as a plan
	 * figure at a glance, where a bare 'days' would not.
	 *
	 * @return array<string,array{0:string,1:string}> Variable name (without
	 *         prefix) => [ method, help text ].
	 */
	private static function variables(): array {

		return array(
			// Recipes — and meals, which answer for the recipe they plan.
			'servings'      => array( 'servings', __( 'Servings the recipe is written for', 'mixmymeals' ) ),
			'prep_time'     => array( 'prep_time', __( 'Preparation time of the recipe', 'mixmymeals' ) ),
			'calories'      => array( 'calories', __( 'Energy per serving', 'mixmymeals' ) ),
			'diet'          => array( 'diet', __( 'Diet classification (vegan / vegetarian)', 'mixmymeals' ) ),
			'rating'        => array( 'rating', __( 'Average star rating', 'mixmymeals' ) ),
			'rating_count'  => array( 'rating_count', __( 'Number of ratings', 'mixmymeals' ) ),
			'cook_count'    => array( 'cook_count', __( 'How often the recipe has been planned', 'mixmymeals' ) ),
			'shelf_life'    => array( 'shelf_life', __( 'How long the dish keeps', 'mixmymeals' ) ),
			'storage'       => array( 'storage', __( 'How the dish is stored', 'mixmymeals' ) ),

			// Meal plans.
			'plan_days'     => array( 'plan_days', __( 'Number of days in the meal plan', 'mixmymeals' ) ),
			'plan_meals'    => array( 'plan_meals', __( 'Number of dishes in the meal plan', 'mixmymeals' ) ),
			'plan_servings' => array( 'plan_servings', __( 'Servings the meal plan is written for', 'mixmymeals' ) ),
			'plan_calories' => array( 'plan_calories', __( 'Energy per day and person', 'mixmymeals' ) ),

			// Ingredients.
			'ingr_calories' => array( 'ingr_calories', __( 'Energy per 100 g of the ingredient', 'mixmymeals' ) ),
			'ingr_price'    => array( 'ingr_price', __( 'Price per 100 g of the ingredient', 'mixmymeals' ) ),

			// Meals. The recipe variables above already answer on a meal, so
			// these are only what a meal has of its own.
			'meal_type'     => array( 'meal_type', __( 'Meal type (breakfast, lunch, …)', 'mixmymeals' ) ),
			'meal_day'      => array( 'meal_day', __( 'Weekday the meal is planned for', 'mixmymeals' ) ),
			'meal_servings' => array( 'meal_servings', __( 'Servings planned for this meal', 'mixmymeals' ) ),
			'meal_plan'     => array( 'meal_plan', __( 'Title of the meal plan the meal belongs to', 'mixmymeals' ) ),

			// One line each, assembled from whatever is known.
			'recipe_facts'  => array( 'recipe_facts', __( 'Servings, time and energy of the recipe in one line', 'mixmymeals' ) ),
			'plan_facts'    => array( 'plan_facts', __( 'Days, dishes, servings and energy of the plan in one line', 'mixmymeals' ) ),
			'ingr_facts'    => array( 'ingr_facts', __( 'Energy, diet and storage of the ingredient in one line', 'mixmymeals' ) ),
			'meal_facts'    => array( 'meal_facts', __( 'Type, weekday, servings and energy of the meal in one line', 'mixmymeals' ) ),

			// Counts, for archive templates. Independent of the current post,
			// so they work where the ones above deliberately stay empty.
			'recipe_count'  => array( 'recipe_count', __( 'Number of published recipes', 'mixmymeals' ) ),
			'plan_count'    => array( 'plan_count', __( 'Number of published meal plans', 'mixmymeals' ) ),
			'ingr_count'    => array( 'ingr_count', __( 'Number of published ingredients', 'mixmymeals' ) ),
			'term_count'    => array( 'term_count', __( 'Number of entries in the current category or tag', 'mixmymeals' ) ),
		);
	}

	/**
	 * Hands the variables to Yoast.
	 *
	 * Registered as 'advanced', which is where Yoast files everything that is
	 * not one of its own basics — it only decides which table the variable is
	 * listed under, not what it does.
	 *
	 * @return void
	 */
	public static function register_replacements(): void {

		if ( ! function_exists( 'wpseo_register_var_replacement' ) ) {
			return;
		}

		foreach ( self::variables() as $name => $definition ) {

			list( $method, $help ) = $definition;

			wpseo_register_var_replacement(
				self::PREFIX . $name,
				array( __CLASS__, 'value_' . $method ),
				'advanced',
				$help
			);
		}
	}

	/* ---------------------------------------------------------------------
	 * Context
	 * ------------------------------------------------------------------ */

	/**
	 * The post a replacement is being rendered for.
	 *
	 * Yoast hands the callback its own argument object, which carries the post
	 * on a singular view and in the editor.
	 *
	 * The fallback is restricted to singular views and the admin on purpose.
	 * Yoast renders meta in wp_head, where on an archive the global $post is
	 * already the first entry of the list — get_the_ID() would answer happily
	 * and put one recipe's serving count into the meta description of the whole
	 * archive. A wrong number is worse than none, and nothing about the page
	 * would look broken.
	 *
	 * @param mixed $args Yoast's argument object.
	 * @return int Post ID, or 0.
	 */
	private static function post_id( $args ): int {

		if ( is_object( $args ) && ! empty( $args->ID ) ) {
			return (int) $args->ID;
		}

		if ( ! is_singular() && ! is_admin() ) {
			return 0;
		}

		return (int) get_the_ID();
	}

	/**
	 * The recipe behind a post: a recipe answers for itself, a meal for the
	 * recipe it plans, anything else for nothing.
	 *
	 * The same resolution Mixmymeals_Meal::resolve_credit_source() performs for
	 * the author box and the image credit — a meal is a planning of a dish, and
	 * every fact about the dish belongs to its recipe.
	 *
	 * @param mixed $args Yoast's argument object.
	 * @return Mixmymeals_Recipe|null
	 */
	private static function recipe( $args ): ?Mixmymeals_Recipe {

		$post_id = self::post_id( $args );

		if ( ! $post_id ) {
			return null;
		}

		$type = get_post_type( $post_id );

		if ( 'mmm_meal' === $type ) {
			$post_id = (int) ( new Mixmymeals_Meal( $post_id ) )->get_recipe_id();
			$type    = $post_id ? get_post_type( $post_id ) : '';
		}

		if ( 'mmm_recipe' !== $type ) {
			return null;
		}

		$recipe = new Mixmymeals_Recipe( $post_id );

		return $recipe->is_valid() ? $recipe : null;
	}

	/**
	 * The meal plan a post is, or null.
	 *
	 * A meal is not resolved to its plan here: a meal page is about the dish,
	 * and "7 days" on it would describe something the visitor is not looking
	 * at.
	 *
	 * @param mixed $args Yoast's argument object.
	 * @return Mixmymeals_Meal_Plan|null
	 */
	private static function plan( $args ): ?Mixmymeals_Meal_Plan {

		$post_id = self::post_id( $args );

		if ( ! $post_id || 'mmm_meal_plan' !== get_post_type( $post_id ) ) {
			return null;
		}

		return new Mixmymeals_Meal_Plan( $post_id );
	}

	/**
	 * The ingredient a post is, or null.
	 *
	 * @param mixed $args Yoast's argument object.
	 * @return Mixmymeals_Ingredient|null
	 */
	private static function ingredient( $args ): ?Mixmymeals_Ingredient {

		$post_id = self::post_id( $args );

		if ( ! $post_id || 'mmm_ingredient' !== get_post_type( $post_id ) ) {
			return null;
		}

		$ingredient = new Mixmymeals_Ingredient( $post_id );

		return $ingredient->is_valid() ? $ingredient : null;
	}

	/**
	 * The meal a post is, or null.
	 *
	 * @param mixed $args Yoast's argument object.
	 * @return Mixmymeals_Meal|null
	 */
	private static function meal( $args ): ?Mixmymeals_Meal {

		$post_id = self::post_id( $args );

		if ( ! $post_id || 'mmm_meal' !== get_post_type( $post_id ) ) {
			return null;
		}

		return new Mixmymeals_Meal( $post_id );
	}

	/**
	 * Joins the parts of a facts line, dropping whatever is empty.
	 *
	 * The reason the three *_facts variables exist at all. A Yoast template has
	 * no conditionals, so "%%mmm_servings%%, %%mmm_prep_time%%" on a recipe
	 * with no time stated leaves a separator hanging with nothing behind it,
	 * on every such page. A line that assembles itself is either complete or
	 * absent, and the template stays readable.
	 *
	 * @param string[] $parts Parts, empty ones included.
	 * @return string
	 */
	private static function join_facts( array $parts ): string {

		$parts = array_filter( array_map( 'trim', $parts ), static fn( $part ) => '' !== $part );

		return implode( ', ', $parts );
	}

	/* ---------------------------------------------------------------------
	 * Recipe values
	 * ------------------------------------------------------------------ */

	/**
	 * Servings the recipe is written for.
	 *
	 * @param string $variable Variable name.
	 * @param mixed  $args     Yoast's argument object.
	 * @return string
	 */
	public static function value_servings( $variable = '', $args = null ): string {

		$recipe = self::recipe( $args );

		if ( ! $recipe ) {
			return '';
		}

		$servings = (float) $recipe->get_servings();

		// A fractional serving count exists but is rare; printing 4 rather
		// than 4,0 is what a title wants.
		return $servings > 0
			? number_format_i18n( $servings, ( floor( $servings ) === $servings ) ? 0 : 1 )
			: '';
	}

	/**
	 * Preparation time, as "25 min" or "1 h 10 min".
	 *
	 * @param string $variable Variable name.
	 * @param mixed  $args     Yoast's argument object.
	 * @return string
	 */
	public static function value_prep_time( $variable = '', $args = null ): string {

		$recipe  = self::recipe( $args );
		$minutes = $recipe ? (int) $recipe->get_preparation_time() : 0;

		if ( $minutes <= 0 ) {
			return '';
		}

		if ( $minutes < 60 ) {
			/* translators: %s: a number of minutes. */
			return sprintf( __( '%s min', 'mixmymeals' ), number_format_i18n( $minutes ) );
		}

		$hours = (int) floor( $minutes / 60 );
		$rest  = $minutes % 60;

		/* translators: %s: a number of hours. */
		$out = sprintf( __( '%s h', 'mixmymeals' ), number_format_i18n( $hours ) );

		if ( $rest > 0 ) {
			/* translators: %s: a number of minutes. */
			$out .= ' ' . sprintf( __( '%s min', 'mixmymeals' ), number_format_i18n( $rest ) );
		}

		return $out;
	}

	/**
	 * Energy per serving, formatted the way every other page states it.
	 *
	 * @param string $variable Variable name.
	 * @param mixed  $args     Yoast's argument object.
	 * @return string
	 */
	public static function value_calories( $variable = '', $args = null ): string {

		$recipe = self::recipe( $args );

		if ( ! $recipe ) {
			return '';
		}

		$per_serving = $recipe->calculate_nutrition_per_serving();
		$calories    = (float) ( $per_serving['calories'] ?? 0 );

		return $calories > 0
			? Mixmymeals_Meal_Plan::format_nutrition_value( 'calories', $calories )
			: '';
	}

	/**
	 * Diet classification, resolved through the ingredients as everywhere else.
	 *
	 * @param string $variable Variable name.
	 * @param mixed  $args     Yoast's argument object.
	 * @return string
	 */
	public static function value_diet( $variable = '', $args = null ): string {

		$recipe = self::recipe( $args );

		return $recipe ? Mixmymeals_Recipe::diet_label( $recipe->get_diet_type() ) : '';
	}

	/**
	 * Average star rating. Empty until somebody has rated — a bare "0" beside
	 * stars in a search result reads as a bad recipe rather than a new one.
	 *
	 * @param string $variable Variable name.
	 * @param mixed  $args     Yoast's argument object.
	 * @return string
	 */
	public static function value_rating( $variable = '', $args = null ): string {

		$recipe = self::recipe( $args );

		if ( ! $recipe || ! class_exists( 'Mixmymeals_Ratings' ) ) {
			return '';
		}

		$average = (float) get_post_meta( $recipe->get_id(), Mixmymeals_Ratings::META_AVG, true );

		return $average > 0 ? number_format_i18n( $average, 1 ) : '';
	}

	/**
	 * Number of ratings.
	 *
	 * @param string $variable Variable name.
	 * @param mixed  $args     Yoast's argument object.
	 * @return string
	 */
	public static function value_rating_count( $variable = '', $args = null ): string {

		$recipe = self::recipe( $args );

		if ( ! $recipe || ! class_exists( 'Mixmymeals_Ratings' ) ) {
			return '';
		}

		$count = (int) get_post_meta( $recipe->get_id(), Mixmymeals_Ratings::META_COUNT, true );

		return $count > 0 ? number_format_i18n( $count ) : '';
	}

	/**
	 * How often the recipe has sat on a published plan.
	 *
	 * @param string $variable Variable name.
	 * @param mixed  $args     Yoast's argument object.
	 * @return string
	 */
	public static function value_cook_count( $variable = '', $args = null ): string {

		$recipe = self::recipe( $args );
		$count  = $recipe ? (int) $recipe->get_cook_count() : 0;

		return $count > 0 ? number_format_i18n( $count ) : '';
	}

	/**
	 * How long the finished dish keeps.
	 *
	 * Zero is a real answer here and says "eat it the same day", which is why
	 * the getter returns ?int and this does not treat 0 as missing — the one
	 * case a reader must not misread.
	 *
	 * @param string $variable Variable name.
	 * @param mixed  $args     Yoast's argument object.
	 * @return string
	 */
	public static function value_shelf_life( $variable = '', $args = null ): string {

		$recipe = self::recipe( $args );
		$days   = $recipe ? $recipe->get_shelf_life() : null;

		if ( null === $days ) {
			return '';
		}

		if ( 0 === $days ) {
			return __( 'Eat on the same day', 'mixmymeals' );
		}

		return sprintf(
			/* translators: %s: a number of days. */
			_n( '%s day', '%s days', $days, 'mixmymeals' ),
			number_format_i18n( $days )
		);
	}

	/**
	 * How the dish is stored, as the label of the stored condition.
	 *
	 * @param string $variable Variable name.
	 * @param mixed  $args     Yoast's argument object.
	 * @return string
	 */
	public static function value_storage( $variable = '', $args = null ): string {

		$recipe = self::recipe( $args );

		if ( ! $recipe ) {
			return '';
		}

		$types = Mixmymeals_Post_Types::storage_types();

		return (string) ( $types[ $recipe->get_storage_type() ] ?? '' );
	}

	/* ---------------------------------------------------------------------
	 * Meal plan values
	 * ------------------------------------------------------------------ */

	/**
	 * Number of days the plan spans.
	 *
	 * @param string $variable Variable name.
	 * @param mixed  $args     Yoast's argument object.
	 * @return string
	 */
	public static function value_plan_days( $variable = '', $args = null ): string {

		$plan = self::plan( $args );
		$days = $plan ? count( $plan->get_day_range() ) : 0;

		return $days > 0 ? number_format_i18n( $days ) : '';
	}

	/**
	 * Number of dishes on the plan.
	 *
	 * @param string $variable Variable name.
	 * @param mixed  $args     Yoast's argument object.
	 * @return string
	 */
	public static function value_plan_meals( $variable = '', $args = null ): string {

		$plan  = self::plan( $args );
		$meals = $plan ? (int) $plan->count_meals() : 0;

		return $meals > 0 ? number_format_i18n( $meals ) : '';
	}

	/**
	 * Servings the plan is written for, e.g. "2" or "2–4".
	 *
	 * The word "servings" comes with get_servings_range_string(), so this
	 * strips it back off: a template wants the bare number for its own
	 * sentence, and "für 2 Portionen Portionen" is what happens otherwise.
	 *
	 * @param string $variable Variable name.
	 * @param mixed  $args     Yoast's argument object.
	 * @return string
	 */
	public static function value_plan_servings( $variable = '', $args = null ): string {

		$plan = self::plan( $args );

		if ( ! $plan ) {
			return '';
		}

		$range = trim( $plan->get_servings_range_string() );

		if ( '' === $range ) {
			return '';
		}

		// Leading run of digits, separators and en dashes — the number part.
		return preg_match( '/^[0-9.,\x{2013}\x{2014}\s-]+/u', $range, $match )
			? trim( $match[0] )
			: $range;
	}

	/**
	 * Energy per day and person, from the one method that owns that average.
	 *
	 * @param string $variable Variable name.
	 * @param mixed  $args     Yoast's argument object.
	 * @return string
	 */
	public static function value_plan_calories( $variable = '', $args = null ): string {

		$plan = self::plan( $args );

		if ( ! $plan ) {
			return '';
		}

		$figures  = $plan->calc_nutrition_per_day_person();
		$calories = (float) ( $figures['average']['calories'] ?? 0 );

		return $calories > 0
			? Mixmymeals_Meal_Plan::format_nutrition_value( 'calories', $calories )
			: '';
	}

	/* ---------------------------------------------------------------------
	 * Ingredient values
	 * ------------------------------------------------------------------ */

	/**
	 * Energy per 100 g, resolved through the reference product like everywhere
	 * else — get_nutrition() is the only place ingredient nutrition is read.
	 *
	 * @param string $variable Variable name.
	 * @param mixed  $args     Yoast's argument object.
	 * @return string
	 */
	public static function value_ingr_calories( $variable = '', $args = null ): string {

		$ingredient = self::ingredient( $args );

		if ( ! $ingredient ) {
			return '';
		}

		$calories = (float) ( $ingredient->get_nutrition()['calories_100'] ?? 0 );

		return $calories > 0
			? Mixmymeals_Meal_Plan::format_nutrition_value( 'calories', $calories )
			: '';
	}

	/**
	 * Price per 100 g, in the configured currency.
	 *
	 * @param string $variable Variable name.
	 * @param mixed  $args     Yoast's argument object.
	 * @return string
	 */
	public static function value_ingr_price( $variable = '', $args = null ): string {

		$ingredient = self::ingredient( $args );
		$price      = $ingredient ? (float) $ingredient->get_price_per_100g() : 0.0;

		if ( $price <= 0 ) {
			return '';
		}

		return number_format_i18n( $price, 2 ) . ' ' . (string) get_option( 'mixmymeals_currency', '€' );
	}

	/* ---------------------------------------------------------------------
	 * Meal values
	 *
	 * Everything about the dish itself — servings the recipe is written for,
	 * time, energy, diet — comes from the recipe variables above, which resolve
	 * a meal on their own. What is left here is what belongs to the planning
	 * rather than to the dish.
	 * ------------------------------------------------------------------ */

	/**
	 * Which meal of the day this is.
	 *
	 * @param string $variable Variable name.
	 * @param mixed  $args     Yoast's argument object.
	 * @return string
	 */
	public static function value_meal_type( $variable = '', $args = null ): string {

		$meal = self::meal( $args );

		if ( ! $meal ) {
			return '';
		}

		$types = Mixmymeals_Meal::get_plaintext_meal_type_map();
		$type  = $meal->get_type();

		// 'other' is the catch-all the planner falls back to; printing "Other"
		// in a page title says less than saying nothing.
		return ( '' === $type || 'other' === $type ) ? '' : (string) ( $types[ $type ] ?? '' );
	}

	/**
	 * The weekday the meal is planned for.
	 *
	 * Taken from WP_Locale, so it follows the site language like every other
	 * date on the page rather than carrying a fourth copy of the weekday names.
	 *
	 * @param string $variable Variable name.
	 * @param mixed  $args     Yoast's argument object.
	 * @return string
	 */
	public static function value_meal_day( $variable = '', $args = null ): string {

		global $wp_locale;

		$meal = self::meal( $args );
		$day  = $meal ? (int) $meal->get_day() : 0;

		if ( $day < 1 || $day > 7 || ! $wp_locale instanceof WP_Locale ) {
			return '';
		}

		// get_weekday() counts from Sunday as 0, the plan from Monday as 1.
		return (string) $wp_locale->get_weekday( $day % 7 );
	}

	/**
	 * Servings planned for this meal.
	 *
	 * Deliberately its own variable: %%mmm_servings%% on a meal answers for the
	 * recipe — what the dish is written for — while this is how much of it was
	 * actually planned. The two are different numbers and the plan scales the
	 * recipe by their ratio.
	 *
	 * @param string $variable Variable name.
	 * @param mixed  $args     Yoast's argument object.
	 * @return string
	 */
	public static function value_meal_servings( $variable = '', $args = null ): string {

		$meal     = self::meal( $args );
		$servings = $meal ? (int) $meal->get_servings() : 0;

		return $servings > 0 ? number_format_i18n( $servings ) : '';
	}

	/**
	 * Title of the plan the meal sits on.
	 *
	 * @param string $variable Variable name.
	 * @param mixed  $args     Yoast's argument object.
	 * @return string
	 */
	public static function value_meal_plan( $variable = '', $args = null ): string {

		$meal    = self::meal( $args );
		$plan_id = $meal ? (int) $meal->get_parent_plan_id() : 0;

		if ( ! $plan_id || 'mmm_meal_plan' !== get_post_type( $plan_id ) ) {
			return '';
		}

		return wp_strip_all_tags( get_the_title( $plan_id ) );
	}

	/* ---------------------------------------------------------------------
	 * Assembled lines
	 * ------------------------------------------------------------------ */

	/**
	 * Servings, preparation time and energy of a recipe, in one line.
	 *
	 * @param string $variable Variable name.
	 * @param mixed  $args     Yoast's argument object.
	 * @return string
	 */
	public static function value_recipe_facts( $variable = '', $args = null ): string {

		$servings = self::value_servings( '', $args );
		$calories = self::value_calories( '', $args );

		return self::join_facts(
			array(
				'' === $servings ? '' : sprintf(
					/* translators: %s: number of servings. */
					_n( '%s serving', '%s servings', (int) self::plain_servings( $args ), 'mixmymeals' ),
					$servings
				),
				self::value_prep_time( '', $args ),
				'' === $calories ? '' : sprintf(
					/* translators: %s: energy, already formatted with its unit. */
					__( '%s per serving', 'mixmymeals' ),
					$calories
				),
			)
		);
	}

	/**
	 * The recipe's serving count as a plain number, for choosing a plural form.
	 *
	 * @param mixed $args Yoast's argument object.
	 * @return int
	 */
	private static function plain_servings( $args ): int {

		$recipe = self::recipe( $args );

		return $recipe ? (int) round( (float) $recipe->get_servings() ) : 0;
	}

	/**
	 * Days, dishes, servings and energy of a plan, in one line.
	 *
	 * @param string $variable Variable name.
	 * @param mixed  $args     Yoast's argument object.
	 * @return string
	 */
	public static function value_plan_facts( $variable = '', $args = null ): string {

		$plan = self::plan( $args );

		if ( ! $plan ) {
			return '';
		}

		$days     = count( $plan->get_day_range() );
		$meals    = (int) $plan->count_meals();
		$calories = self::value_plan_calories( '', $args );

		return self::join_facts(
			array(
				$days > 0 ? sprintf(
					/* translators: %s: number of days. */
					_n( '%s day', '%s days', $days, 'mixmymeals' ),
					number_format_i18n( $days )
				) : '',
				$meals > 0 ? sprintf(
					/* translators: %s: number of dishes. */
					_n( '%s dish', '%s dishes', $meals, 'mixmymeals' ),
					number_format_i18n( $meals )
				) : '',
				trim( $plan->get_servings_range_string() ),
				'' === $calories ? '' : sprintf(
					/* translators: %s: energy, already formatted with its unit. */
					__( '%s per day and person', 'mixmymeals' ),
					$calories
				),
			)
		);
	}

	/**
	 * Energy, diet and storage of an ingredient, in one line.
	 *
	 * @param string $variable Variable name.
	 * @param mixed  $args     Yoast's argument object.
	 * @return string
	 */
	public static function value_ingr_facts( $variable = '', $args = null ): string {

		$ingredient = self::ingredient( $args );

		if ( ! $ingredient ) {
			return '';
		}

		$calories = self::value_ingr_calories( '', $args );
		$types    = Mixmymeals_Post_Types::storage_types();

		return self::join_facts(
			array(
				'' === $calories ? '' : sprintf(
					/* translators: %s: energy, already formatted with its unit. */
					__( '%s per 100 g', 'mixmymeals' ),
					$calories
				),
				Mixmymeals_Recipe::diet_label( $ingredient->get_diet_type() ),
				(string) ( $types[ $ingredient->get_storage_type() ] ?? '' ),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Counts, for archives
	 * ------------------------------------------------------------------ */

	/**
	 * Number of published recipes.
	 *
	 * Takes no arguments: Yoast passes the variable name and its context to
	 * every replacement, and PHP discards what a function does not declare.
	 *
	 * @return string
	 */
	public static function value_recipe_count(): string {
		return self::published_count( 'mmm_recipe' );
	}

	/**
	 * Number of published meal plans.
	 *
	 * @return string
	 */
	public static function value_plan_count(): string {
		return self::published_count( 'mmm_meal_plan' );
	}

	/**
	 * Number of published ingredients.
	 *
	 * @return string
	 */
	public static function value_ingr_count(): string {
		return self::published_count( 'mmm_ingredient' );
	}

	/**
	 * How many published entries one post type has.
	 *
	 * @param string $post_type Post type.
	 * @return string
	 */
	private static function published_count( string $post_type ): string {

		$counts = wp_count_posts( $post_type );
		$count  = $counts ? (int) $counts->publish : 0;

		return $count > 0 ? number_format_i18n( $count ) : '';
	}

	/**
	 * How many entries the category or tag currently being viewed holds.
	 *
	 * Yoast has no variable for this, and "Suppen (14 Rezepte)" is most of what
	 * a term archive has to say about itself.
	 *
	 * @param string $variable Variable name.
	 * @param mixed  $args     Yoast's argument object.
	 * @return string
	 */
	public static function value_term_count( $variable = '', $args = null ): string {

		$term = null;

		if ( is_object( $args ) && ! empty( $args->term_id ) ) {
			$term = get_term( (int) $args->term_id );
		}

		if ( ! $term instanceof WP_Term && ! is_admin() && is_tax() ) {
			$term = get_queried_object();
		}

		if ( ! $term instanceof WP_Term || $term->count <= 0 ) {
			return '';
		}

		return number_format_i18n( (int) $term->count );
	}

	/**
	 * Type, weekday, servings and energy of a meal, in one line.
	 *
	 * The servings here are the meal\'s own, and the energy the recipe\'s per
	 * serving — the two figures a planned dish is actually described by.
	 *
	 * @param string $variable Variable name.
	 * @param mixed  $args     Yoast\'s argument object.
	 * @return string
	 */
	public static function value_meal_facts( $variable = '', $args = null ): string {

		$meal = self::meal( $args );

		if ( ! $meal ) {
			return '';
		}

		$servings = (int) $meal->get_servings();
		$calories = self::value_calories( '', $args );

		return self::join_facts(
			array(
				self::value_meal_type( '', $args ),
				self::value_meal_day( '', $args ),
				$servings > 0 ? sprintf(
					/* translators: %s: number of servings. */
					_n( '%s serving', '%s servings', $servings, 'mixmymeals' ),
					number_format_i18n( $servings )
				) : '',
				'' === $calories ? '' : sprintf(
					/* translators: %s: energy, already formatted with its unit. */
					__( '%s per serving', 'mixmymeals' ),
					$calories
				),
			)
		);
	}
}
