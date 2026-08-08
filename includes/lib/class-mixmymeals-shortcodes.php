<?php
/**
 * Shortcodes and AJAX handlers for the MixMyMeals plugin.
 *
 * @package MixMyMeals/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers all frontend shortcodes and AJAX handlers.
 */
class Mixmymeals_Shortcodes {

	/**
	 * Registers all shortcodes and AJAX action hooks.
	 */
	public function __construct() {

		$this->register_archive_shortcodes();
		$this->register_content_type_shortcodes();
		$this->register_single_shortcodes();
		$this->register_planner_shortcodes();
		$this->register_query_hooks();
		$this->register_ajax();

	}

	/**
	 * Archive views: meal plans, archive search, recipes.
	 */
	private function register_archive_shortcodes(): void {

		// Meal Plan Archive:

		add_shortcode( 'mmm_weekday_overview_for_posts_in_meal_plan_archive',
			[
				$this,
				'mmm_weekday_overview_for_posts_in_meal_plan_archive_callback'
			] );

		add_shortcode( 'mmm_meal_type_overview_for_posts_in_meal_plan_archive',
			[ $this, 'mmm_meal_type_overview_for_posts_in_meal_plan_archive_callback' ] );

		add_shortcode( 'mmm_meta_for_posts_in_meal_plan_archive',
			[ $this, 'mmm_meta_for_posts_in_meal_plan_archive_callback' ] );

		add_shortcode( 'mmm_meal_plan_category_switcher',
			[ $this, 'mmm_meal_plan_category_switcher_callback' ] );

		add_shortcode( 'mmm_latest_meal_plans',
			[ $this, 'mmm_latest_meal_plans_callback' ] );

		add_shortcode( 'mmm_latest_plan_teaser',
			[ $this, 'mmm_latest_plan_teaser_callback' ] );

		add_shortcode( 'mmm_top_rated_recipes',
			[ $this, 'mmm_top_rated_recipes_callback' ] );

		add_shortcode( 'mmm_community_photo_wall',
			[ $this, 'mmm_community_photo_wall_callback' ] );

		add_shortcode( 'mmm_thumbnails_of_meals_in_a_single_meal_plan', [
			$this,
			'mmm_thumbnails_of_meals_in_a_single_meal_plan_callback'
		] );


		// Archive search + filter (place [mmm_archive_search] on the archive via
		// a theme hook). Form + query handling for recipe and meal plan archives.
		add_shortcode( 'mmm_archive_search',
			array( $this, 'mmm_archive_search_callback' ) );


		// Recipe Archive:

		add_shortcode( 'mmm_meta_for_posts_in_recipe_archive',
			[ $this, 'mmm_meta_for_posts_in_recipe_archive_callback' ] );

		add_shortcode( 'mmm_recipes_index',
			[ $this, 'mmm_recipes_index_callback' ] );

		add_shortcode( 'mmm_new_recipes',
			[ $this, 'mmm_new_recipes_callback' ] );

		add_shortcode( 'mmm_favorite_recipes',
			[ $this, 'mmm_favorite_recipes_callback' ] );

		add_shortcode( 'mmm_popular_recipes',
			[ $this, 'mmm_popular_recipes_callback' ] );

		add_shortcode( 'mmm_uncooked_recipes',
			[ $this, 'mmm_uncooked_recipes_callback' ] );
	}

	/**
	 * Ingredients and equipment as content.
	 */
	private function register_content_type_shortcodes(): void {

		// Ingredients as content (single hero + nutrition table + archive index).
		add_shortcode( 'mmm_ingredient_hero',
			array( $this, 'mmm_ingredient_hero_callback' ) );
		add_shortcode( 'mmm_nutrition_table_for_a_single_ingredient',
			array( $this, 'mmm_nutrition_table_for_a_single_ingredient_callback' ) );
		add_shortcode( 'mmm_recipes_for_a_single_ingredient',
			array( $this, 'mmm_recipes_for_a_single_ingredient_callback' ) );
		add_shortcode( 'mmm_ingredients_index',
			array( $this, 'mmm_ingredients_index_callback' ) );

		// Equipment as content (single hero + recipes + archive index).
		add_shortcode( 'mmm_equipment_hero',
			array( $this, 'mmm_equipment_hero_callback' ) );
		add_shortcode( 'mmm_recipes_for_a_single_equipment',
			array( $this, 'mmm_recipes_for_a_single_equipment_callback' ) );
		add_shortcode( 'mmm_equipment_affiliate_links',
			array( $this, 'mmm_equipment_affiliate_links_callback' ) );

		add_shortcode( 'mmm_equipment_index',
			array( $this, 'mmm_equipment_index_callback' ) );
	}

	/**
	 * Single meal, meal plan and recipe views.
	 */
	private function register_single_shortcodes(): void {

		// Single Meals:

		add_shortcode( 'mmm_meal_ingredients',
			[ $this, 'mmm_meal_ingredients_callback' ] );

		add_shortcode( 'mmm_equipment_required_for_a_single_meal',
			[ $this, 'mmm_equipment_required_for_a_single_meal_callback' ] );

		add_shortcode( 'mmm_scaled_recipe_steps_for_a_single_meal',
			[ $this, 'mmm_scaled_recipe_steps_for_a_single_meal_callback' ] );

		add_shortcode( 'mmm_cook_mode_button_for_a_single_meal',
			[ $this, 'mmm_cook_mode_button_for_a_single_meal_callback' ] );

		add_shortcode( 'mmm_meal_nutrition_table_for_a_single_meal',
			[ $this, 'mmm_meal_nutrition_table_for_a_single_meal_callback' ] );

		add_shortcode( 'mmm_back_to_meal_plan_link_for_single_meal',
			[ $this, 'mmm_back_to_meal_plan_link_for_single_meal_callback' ] );

		add_shortcode( 'mmm_meta_for_a_single_meal',
			[ $this, 'mmm_meta_for_a_single_meal_callback' ] );

		add_shortcode( 'mmm_disclaimer_for_a_single_meal',
			[ $this, 'mmm_disclaimer_for_a_single_meal_callback' ] );

		add_shortcode( 'mmm_feature_image_for_a_single_meal',
			[ $this, 'mmm_feature_image_for_a_single_meal_callback' ] );

		add_shortcode( 'mmm_meal_nutrition_calculator',
			[ $this, 'mmm_meal_nutrition_calculator_callback' ] );

		// Single Meal Plans:

		add_shortcode( 'mmm_meals_in_a_single_meal_plan',
			[ $this, 'mmm_meals_in_a_single_meal_plan_callback' ] );

		add_shortcode( 'mmm_shopping_list_for_a_single_meal_plan',
			[ $this, 'mmm_shopping_list_for_a_single_meal_plan_callback' ] );

		add_shortcode( 'mmm_equipment_required_for_a_single_meal_plan',
			[ $this, 'mmm_equipment_required_for_a_single_meal_plan_callback' ] );

		add_shortcode( 'mmm_meal_plan_actions',
			[ $this, 'mmm_meal_plan_actions_callback' ] );

		add_shortcode( 'mmm_breadcrumb',
			[ $this, 'mmm_breadcrumb_callback' ] );

		add_shortcode( 'mmm_meal_plan_hero',
			[ $this, 'mmm_meal_plan_hero_callback' ] );

		add_shortcode( 'mmm_recipe_hero',
			[ $this, 'mmm_recipe_hero_callback' ] );

		add_shortcode( 'mmm_recipe_author_box',
			[ $this, 'mmm_recipe_author_box_callback' ] );

		add_shortcode( 'mmm_meal_plan_author_box',
			[ $this, 'mmm_meal_plan_author_box_callback' ] );

		add_shortcode( 'mmm_image_credit',
			[ $this, 'mmm_image_credit_callback' ] );

		add_shortcode( 'mmm_disclosure_label',
			[ $this, 'mmm_disclosure_label_callback' ] );

		add_shortcode( 'mmm_meal_hero',
			[ $this, 'mmm_meal_hero_callback' ] );

		add_shortcode( 'mmm_meta_for_a_single_meal_plan',
			[ $this, 'mmm_meta_for_a_single_meal_plan_callback' ] );

		add_shortcode( 'mmm_nutrition_overview_for_a_single_meal_plan',
			[ $this, 'mmm_nutrition_overview_for_a_single_meal_plan_callback' ] );

		add_shortcode( 'mmm_nutrition_per_day_for_a_single_meal_plan',
			[ $this, 'mmm_nutrition_per_day_for_a_single_meal_plan_callback' ] );

		// Single Recipe:

		add_shortcode( 'mmm_recipe_ingredients',
			[ $this, 'mmm_recipe_ingredients_callback' ] );

		add_shortcode( 'mmm_equipment_required_for_a_single_recipe',
			[ $this, 'mmm_equipment_required_for_a_single_recipe_callback' ] );

		add_shortcode( 'mmm_recipe_related',
			[ $this, 'mmm_recipe_related_callback' ] );

		add_shortcode( 'mmm_recipe_related_reverse',
			[ $this, 'mmm_recipe_related_reverse_callback' ] );

		add_shortcode( 'mmm_meal_plans_for_a_single_recipe',
			[ $this, 'mmm_meal_plans_for_a_single_recipe_callback' ] );

		add_shortcode( 'mmm_admin_actions',
			[ $this, 'mmm_admin_actions_callback' ] );

		add_shortcode( 'mmm_recipe_steps_for_a_single_recipe',
			[ $this, 'mmm_recipe_steps_for_a_single_recipe_callback' ] );

		add_shortcode( 'mmm_nutrition_table_for_a_single_recipe',
			[ $this, 'mmm_nutrition_table_for_a_single_recipe_callback' ] );

		add_shortcode( 'mmm_recipe_meta',
			[ $this, 'mmm_recipe_meta_callback' ] );

		add_shortcode( 'mmm_recipe_actions',
			[ $this, 'mmm_recipe_actions_callback' ] );

		add_shortcode( 'mmm_add_to_planner',
			[ $this, 'mmm_add_to_planner_callback' ] );

		add_shortcode( 'mmm_disclaimer_for_a_single_recipe',
			[ $this, 'mmm_disclaimer_for_a_single_recipe_callback' ] );

		add_shortcode( 'mmm_feature_image_for_a_single_recipe',
			[ $this, 'mmm_feature_image_for_a_single_recipe_callback' ] );

		add_shortcode( 'mmm_recipe_edit_count',
			[ $this, 'mmm_recipe_edit_count_callback' ] );

		add_shortcode( 'mmm_recipe_rating',
			[ $this, 'mmm_recipe_rating_callback' ] );

		add_shortcode( 'mmm_recipe_reviews',
			[ $this, 'mmm_recipe_reviews_callback' ] );

		add_shortcode( 'mmm_recipe_rating_form',
			[ $this, 'mmm_recipe_rating_form_callback' ] );

		add_shortcode( 'mmm_photo_upload_form',
			[ $this, 'mmm_photo_upload_form_callback' ] );

		add_shortcode( 'mmm_recipe_photo_gallery',
			[ $this, 'mmm_recipe_photo_gallery_callback' ] );
	}

	/**
	 * Planner, latest plan, subscription form, site stats.
	 */
	private function register_planner_shortcodes(): void {

		// Meal plan subscription form (logic in Mixmymeals_Subscriptions):
		add_shortcode( 'mmm_meal_plan_subscribe',
			[ $this, 'mmm_meal_plan_subscribe_callback' ] );




		// Cart / Meal Plan Builder:

		add_shortcode( 'mmm_cart_badge',
			[ $this, 'mmm_cart_badge_callback' ] );

		add_shortcode( 'mmm_meal_plan_builder',
			[ $this, 'mmm_meal_plan_builder_callback' ] );

		add_shortcode( 'mmm_clear_cart',
			[ $this, 'mmm_clear_cart_callback' ] );

		add_shortcode( 'mmm_create_meal_plan_from_cart',
			[ $this, 'mmm_create_meal_plan_from_cart_callback' ] );

		// Latest Plan (for homepage e.g.):

		add_shortcode( 'mmm_latest_plan_title',
			[ $this, 'mmm_latest_plan_title_callback' ] );

		add_shortcode( 'mmm_random_recipe_title_from_latest_plan',
			[ $this, 'mmm_random_recipe_title_from_latest_plan_callback' ] );

		add_shortcode( 'mmm_random_recipe_image_from_latest_plan',
			[ $this, 'mmm_random_recipe_image_from_latest_plan_callback' ] );

		// Other Shortcodes:

		add_shortcode( 'mmm_recipe_category_terms',
			[ $this, 'mmm_recipe_category_terms_callback' ] );



		// Site stats:

		add_shortcode( 'mmm_published_recipes_count',
			[ $this, 'mmm_published_recipes_count_callback' ] );

		add_shortcode( 'mmm_published_meal_plans_count',
			[ $this, 'mmm_published_meal_plans_count_callback' ] );
	}

	/**
	 * Archive query and title filters.
	 *
	 * Order matters: two callbacks share pre_get_posts at the same priority.
	 */
	private function register_query_hooks(): void {

		add_action( 'pre_get_posts',
			array( $this, 'filter_meal_plan_archive_by_url_parameter' ) );

		add_filter( 'query_vars', function( $vars ) {
			$vars[] = 'mmm_uncategorized';
			return $vars;
		} );

		add_action( 'pre_get_posts',
			array( $this, 'apply_archive_search' ) );

		add_filter( 'posts_where',
			array( $this, 'archive_search_title_where' ), 10, 2 );

		add_filter( 'get_the_archive_title',
			[ $this, 'remove_recipe_category_archive_title_prefix' ], 20 );
	}

	/**
	 * AJAX endpoints that are not shortcodes. Cart and PDF endpoints
	 * live in their own classes.
	 */
	private function register_ajax(): void {

		// AJAX Handlers. NOT shortcode callbacks.
		// Die Warenkorb-Endpunkte liegen in Mixmymeals_Cart::register_ajax().






		add_action( 'wp_ajax_mmm_create_meal_plan',
			array( $this, 'mmm_create_meal_plan_ajax_callback' ) );
		add_action( 'wp_ajax_nopriv_mmm_create_meal_plan',
			array( $this, 'mmm_create_meal_plan_ajax_callback' ) );
	}


	/**
	 * Shortcode [mmm_published_recipes_count]: gibt die Anzahl veröffentlichter Rezepte aus.
	 *
	 * @return string
	 */
	public function mmm_published_recipes_count_callback(): string {
		$count = (int) wp_count_posts( 'mmm_recipe' )->publish;
		return esc_html( number_format_i18n( $count ) );
	}

	/**
	 * Shortcode [mmm_published_meal_plans_count]: gibt die Anzahl veröffentlichter Essenspläne aus.
	 *
	 * @return string
	 */
	public function mmm_published_meal_plans_count_callback(): string {
		$count = (int) wp_count_posts( 'mmm_meal_plan' )->publish;
		return esc_html( number_format_i18n( $count ) );
	}

	/**
	 * Shortcode [mmm_meal_plan_subscribe]: meal plan subscribe form.
	 * Delegates to Mixmymeals_Subscriptions::render_form().
	 *
	 * @param array|string $atts Shortcode attributes (plan_id).
	 * @return string
	 */
	public function mmm_meal_plan_subscribe_callback( $atts = [] ): string {
		return class_exists( 'Mixmymeals_Subscriptions' )
			? Mixmymeals_Subscriptions::render_form( $atts )
			: '';
	}

	/**
	 * Shortcode [mmm_thumbnails_of_meals_in_a_single_meal_plan].
	 * Returns a row of unique recipe thumbnails for the current meal plan.
	 *
	 * @return string
	 */
	public function mmm_thumbnails_of_meals_in_a_single_meal_plan_callback(): string {

		$post_id = get_the_ID();

		if ( ! $post_id ) {
			return '';
		}

		$plan = new Mixmymeals_Meal_Plan( $post_id );

		$meals = $plan->get_meals();

		if ( empty( $meals ) ) {
			return '';
		}

		// Group meals by day, keep only the recipe with the lowest priority per day.
		// Prefer recipes with a real featured image; fall back to any recipe if none has one.
		$best_recipe_per_day  = [];
		$fallback_per_day     = [];

		foreach ( $meals as $meal_post ) {
			$meal      = new Mixmymeals_Meal( $meal_post->ID );
			$recipe_id = (int) $meal->get_recipe_id();
			$day       = (int) $meal->get_day();

			if ( $recipe_id <= 0 || ! Mixmymeals_Recipe::exists_by_id( $recipe_id ) ) {
				continue;
			}

			$recipe   = new Mixmymeals_Recipe( $recipe_id );
			$priority = $recipe->get_priority();
			$has_real_image = (bool) get_post_thumbnail_id( $recipe_id );

			if ( $has_real_image ) {
				if (
					! isset( $best_recipe_per_day[ $day ] ) ||
					$priority < $best_recipe_per_day[ $day ]['priority']
				) {
					$best_recipe_per_day[ $day ] = [
						'recipe_id' => $recipe_id,
						'priority'  => $priority,
					];
				}
			}

			// Fallback: lowest priority regardless of image
			if (
				! isset( $fallback_per_day[ $day ] ) ||
				$priority < $fallback_per_day[ $day ]['priority']
			) {
				$fallback_per_day[ $day ] = [
					'recipe_id' => $recipe_id,
					'priority'  => $priority,
				];
			}
		}

		// Merge: use real-image winner per day, fall back if none found
		foreach ( $fallback_per_day as $day => $entry ) {
			if ( ! isset( $best_recipe_per_day[ $day ] ) ) {
				$best_recipe_per_day[ $day ] = $entry;
			}
		}

		$output = '<div class="mmm-mealplan-thumbs">';

		ksort( $best_recipe_per_day );

		foreach ( $best_recipe_per_day as $entry ) {
			$recipe_id = $entry['recipe_id'];

			if ( ! has_post_thumbnail( $recipe_id ) ) {
				continue;
			}

			$thumb = get_the_post_thumbnail(
				$recipe_id,
				'thumbnail',
				[
					'loading' => 'lazy',
					'alt'     => get_the_title( $recipe_id ),
				]
			);

			$output .= '<a href="' . esc_url( get_permalink( $post_id ) ) . '">' . $thumb . '</a>';
		}

		$output .= '</div>';

		return $output;
	}

	/**
	 * Shortcode [mmm_meta_for_posts_in_meal_plan_archive].
	 * Renders days, meal count, servings, and average cost for a meal plan archive card.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function mmm_meta_for_posts_in_meal_plan_archive_callback( $atts = [] ): string {

		$atts = shortcode_atts(
			[],
			$atts,
			'mmm_meta_for_posts_in_meal_plan_archive'
		);

		if ( ! is_post_type_archive( 'mmm_meal_plan' ) && ! is_tax( 'mmm_meal_plan_category' ) ) {
			return '';
		}

		$plan_id = get_the_ID();
		$plan    = new Mixmymeals_Meal_Plan( $plan_id );

		$num_of_days          = $plan->count_days();
		$num_of_meals         = $plan->count_meals();
		$serving_range_string = $plan->get_servings_range_string();

		ob_start();

		echo '<div class="mmm-meal-plan-meta">';
		if ( $num_of_days ) {
			echo '<span class="mmm-meta__item mmm-meta__item--days">'
			     . esc_html( str_replace( '%s', $num_of_days, _n( '%s Day', '%s Days', $num_of_days, 'mixmymeals' ) ) )
			     . '</span>';
		}
		if ( $num_of_meals ) {
			echo '<span class="mmm-meta__item mmm-meta__item--meals">'
			     . esc_html( sprintf( _n( '%s Recipe', '%s Recipes', $num_of_meals, 'mixmymeals' ), $num_of_meals ) )
			     . '</span>';
		}
		if ( $serving_range_string ) {
			echo '<span class="mmm-meta__item mmm-meta__item--servings">'
			     . esc_html( $serving_range_string ) . '</span>';
		}
		$avg_cost = $plan->calculate_avg_cost_per_serving();
		if ( $avg_cost > 0 ) {
			$currency = get_option( 'mixmymeals_currency', '€' );
			echo '<span class="mmm-meta__item mmm-meta__item--cost">'
			     . esc_html( number_format_i18n( $avg_cost, 2 ) . ' ' . $currency )
			     . '</span>';
		}
		$diet = $plan->get_diet_type();
		if ( $diet === 'vegan' ) {
			echo '<span class="mmm-meta__item mmm-meta__item--diet">'
			     . esc_html__( 'Vegan', 'mixmymeals' ) . '</span>';
		} elseif ( $diet === 'vegetarian' ) {
			echo '<span class="mmm-meta__item mmm-meta__item--diet">'
			     . esc_html__( 'Vegetarian', 'mixmymeals' ) . '</span>';
		}
		echo '</div>';

		return ob_get_clean();
	}


	/**
	 * Shortcode [mmm_weekday_overview_for_posts_in_meal_plan_archive].
	 * Renders a row of weekday indicators for a meal plan archive card.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function mmm_weekday_overview_for_posts_in_meal_plan_archive_callback(
		$atts = []
	): string {

		$atts = shortcode_atts(
			[],
			$atts,
			'mmm_weekday_overview_for_posts_in_meal_plan_archive'
		);

		if ( ! is_post_type_archive( 'mmm_meal_plan' ) && ! is_tax( 'mmm_meal_plan_category' ) ) {
			return '';
		}

		$plan_id = get_the_ID();
		$plan    = new Mixmymeals_Meal_Plan( $plan_id );

		$today_num  = intval( gmdate( 'N' ) );
		$active_day = $today_num;

		if ( isset( $_GET['mmm_meal_plan_day'], $_GET['mmm_meal_plan_day_nonce'] ) ) {
			$day = intval( wp_unslash( $_GET['mmm_meal_plan_day'] ) );
			$nonce
			     = sanitize_text_field( wp_unslash( $_GET['mmm_meal_plan_day_nonce'] ) );
			if ( wp_verify_nonce( $nonce, 'mmm_meal_plan_day_nonce' )
			     && $day >= 1
			     && $day <= 7 ) {
				$active_day = $day;
			}
		}

		$weekday_labels = [
			1 => __( 'Mon', 'mixmymeals' ),
			2 => __( 'Tue', 'mixmymeals' ),
			3 => __( 'Wed', 'mixmymeals' ),
			4 => __( 'Thu', 'mixmymeals' ),
			5 => __( 'Fri', 'mixmymeals' ),
			6 => __( 'Sat', 'mixmymeals' ),
			7 => __( 'Sun', 'mixmymeals' ),
		];

		ob_start();

		echo '<div class="mmm-weekdays">';

		for ( $day = 1; $day <= 7; $day ++ ) {

			if ( ! $plan->contains_weekday( $day ) ) {
				continue;
			}

			$classes = [ 'mmm-weekday', 'mmm-weekday-in-plan' ];

			if ( $day === $active_day ) {
				$classes[] = 'mmm-weekday-active';
			}

			echo '<span class="' . esc_attr( implode( ' ', $classes ) ) . '">';
			echo esc_html( $weekday_labels[ $day ] );
			echo '</span>';
		}

		echo '</div>';

		return ob_get_clean();
	}


	/**
	 * Shortcode [mmm_meal_type_overview_for_posts_in_meal_plan_archive].
	 * Renders a row of meal-type indicators for a meal plan archive card.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function mmm_meal_type_overview_for_posts_in_meal_plan_archive_callback(
		$atts = []
	): string {

		$atts = shortcode_atts(
			[],
			$atts,
			'mmm_meal_type_overview_for_posts_in_meal_plan_archive'
		);

		if ( ! is_post_type_archive( 'mmm_meal_plan' ) && ! is_tax( 'mmm_meal_plan_category' ) ) {
			return '';
		}

		$plan        = new Mixmymeals_Meal_Plan( get_the_ID() );
		$type_labels = Mixmymeals_Meal::get_plaintext_meal_type_map();

		ob_start();

		echo '<div class="mmm-mealtypes">';

		foreach ( $type_labels as $type => $label ) {
			if ( ! $plan->contains_meal_type( $type ) ) {
				continue;
			}
			$classes = [ 'mmm-mealtype', 'mmm-mealtype--' . $type, 'mmm-mealtype-in-plan' ];
			echo '<span class="' . esc_attr( implode( ' ', $classes ) ) . '">';
			echo esc_html( $label );
			echo '</span>';
		}

		echo '</div>';

		return ob_get_clean();
	}


	/**
	 * Shortcode [mmm_meta_for_posts_in_recipe_archive].
	 * Renders calories, cost, categories, tags, and add-to-cart for a recipe archive card.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function mmm_meta_for_posts_in_recipe_archive_callback( $atts = []
	): string {
		$atts = shortcode_atts( [], $atts,
			'mmm_meta_for_posts_in_recipe_archive' );
		// Run on both recipe archive and tax archive or die:
		if ( ! is_post_type_archive( 'mmm_recipe' )
		     && ! is_tax( 'mmm_recipe_category' ) ) {
			return '';
		}

		return $this->build_recipe_archive_meta_chips( (int) get_the_ID() );
	}

	/**
	 * Builds the meta-chip block (calories, cost, categories, tags, diet) for a
	 * single recipe. Shared by the per-card archive shortcode and the
	 * category-grouped recipe index so both render identical chips.
	 *
	 * @param int $post_id Recipe post ID.
	 * @return string
	 */
	private function build_recipe_archive_meta_chips( int $post_id ): string {
		if ( ! $post_id || ! class_exists( 'Mixmymeals_Recipe' ) ) {
			return '';
		}
		$recipe    = new Mixmymeals_Recipe( $post_id );
		$nutrition = $recipe->calculate_nutrition_per_serving();
		$items     = [];
		if ( ! empty( $nutrition['calories'] ) ) {
			$items[] = '<span class="mmm-meta__item mmm-meta__item--calories">'
			           . esc_html( round( $nutrition['calories'] ) )
			           . ' kcal</span>';
		}
		$cost = $recipe->calculate_cost_per_serving();
		if ( $cost > 0 ) {
			$currency = get_option( 'mixmymeals_currency', '€' );
			$items[]  = '<span class="mmm-meta__item mmm-meta__item--cost">'
			            . esc_html( number_format_i18n( $cost, 2 ) . ' ' . $currency )
			            . '</span>';
		}
		$terms = get_the_terms( $post_id, 'mmm_recipe_category' );
		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			usort( $terms, fn( $a, $b ) => strcasecmp( $a->name, $b->name ) );
			$found_diet = '';
			$diet_label = '';
			foreach ( $terms as $term ) {
				$name = strtolower( $term->name );
				if ( $name === 'vegan' ) {
					$found_diet = 'vegan';
					$diet_label = $term->name;
				} elseif ( in_array( $name, [ 'vegetarisch', 'vegetarian' ], true ) && $found_diet !== 'vegan' ) {
					$found_diet = 'vegetarian';
					$diet_label = $term->name;
				} else {
					$items[] = '<span class="mmm-meta__item mmm-meta__item--category">'
					           . esc_html( $term->name ) . '</span>';
				}
			}
			if ( $found_diet !== '' ) {
				$items[] = '<span class="mmm-meta__item mmm-meta__item--diet">'
				           . esc_html( $diet_label ) . '</span>';
			}
		}

		$tag_terms = get_the_terms( $post_id, 'mmm_recipe_tag' );
		if ( ! empty( $tag_terms ) && ! is_wp_error( $tag_terms ) ) {
			usort( $tag_terms,
				fn( $a, $b ) => strcasecmp( $a->name, $b->name ) );
			foreach ( $tag_terms as $term ) {
				$items[] = '<span class="mmm-meta__item mmm-meta__item--tag">'
				           . esc_html( $term->name )
				           . '</span>';
			}
		}

		$diet = $recipe->get_diet_type();
		if ( $diet === 'vegan' ) {
			$items[] = '<span class="mmm-meta__item mmm-meta__item--diet">'
			           . esc_html__( 'Vegan', 'mixmymeals' ) . '</span>';
		} elseif ( $diet === 'vegetarian' ) {
			$items[] = '<span class="mmm-meta__item mmm-meta__item--diet">'
			           . esc_html__( 'Vegetarian', 'mixmymeals' ) . '</span>';
		}

		if ( empty( $items ) ) {
			return '';
		}

		// The cart action is rendered separately below the description via the
		// theme's astra_entry_content_after hook (see the child theme), so the
		// meta block only carries informational chips here.
		return '<div class="mmm-recipe-archive-item-meta">' . implode( '',
				$items ) . '</div>';
	}

	/**
	 * Shortcode [mmm_recipes_index].
	 * Renders all published recipes grouped under their categories, each as a
	 * full archive-style card (image, title, meta chips, rating, cart action).
	 * A recipe assigned to several categories appears under each of them;
	 * recipes without a category are grouped under "Other".
	 *
	 * @param array|string $atts Shortcode attributes (none).
	 * @return string
	 */
	public function mmm_recipes_index_callback( $atts = array() ): string {

		$ids = get_posts(
			Mixmymeals_Post_Types::exclude_unlisted(
				array(
					'post_type'      => 'mmm_recipe',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'orderby'        => 'title',
					'order'          => 'ASC',
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			)
		);

		if ( empty( $ids ) ) {
			return '';
		}

		// Honour the archive title search ([mmm_archive_search], GET param
		// mmm_s). The grouped index runs its own query, so the main-query filter
		// apply_archive_search() / archive_search_title_where() does not reach
		// it — filter the titles here the same way (title-only, case-insensitive).
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only public GET filter.
		$search = isset( $_GET['mmm_s'] )
			? trim( sanitize_text_field( wp_unslash( $_GET['mmm_s'] ) ) )
			: '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( '' !== $search ) {
			$ids = array_values(
				array_filter(
					$ids,
					static function ( $id ) use ( $search ) {
						return false !== mb_stripos( get_the_title( $id ), $search );
					}
				)
			);

			if ( empty( $ids ) ) {
				return '<div class="mmm-recipes-index mmm-recipes-index--empty"><p>'
					. esc_html__( 'No recipes found.', 'mixmymeals' )
					. '</p></div>';
			}
		}

		// Group recipes by category. A recipe with several categories is listed
		// under each of them; recipes without a category fall into "Other".
		$groups        = array();
		$uncategorized = array();
		foreach ( $ids as $id ) {
			$terms = get_the_terms( $id, 'mmm_recipe_category' );
			if ( empty( $terms ) || is_wp_error( $terms ) ) {
				$uncategorized[] = (int) $id;
				continue;
			}
			foreach ( $terms as $term ) {
				$groups[ $term->name ][] = (int) $id;
			}
		}
		ksort( $groups, SORT_NATURAL | SORT_FLAG_CASE );
		if ( ! empty( $uncategorized ) ) {
			$groups[ __( 'Other', 'mixmymeals' ) ] = $uncategorized;
		}

		$has_rating  = shortcode_exists( 'mmm_recipe_rating' );
		$has_actions = shortcode_exists( 'mmm_recipe_actions' );

		ob_start();
		echo '<div class="mmm-recipes-index">';
		foreach ( $groups as $cat_name => $cat_ids ) {
			echo '<section class="mmm-recipes-index__group">';
			echo '<h2 class="mmm-recipes-index__cat">' . esc_html( (string) $cat_name ) . '</h2>';
			echo '<div class="mmm-recipes-index__list">';
			foreach ( $cat_ids as $id ) {
				// Set up the global post so the per-card shortcodes (rating,
				// cart action) and helpers resolve the right recipe, exactly as
				// they do inside the Astra archive loop.
				$GLOBALS['post'] = get_post( $id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				setup_postdata( $GLOBALS['post'] );

				$permalink = (string) get_permalink( $id );
				$thumb     = get_the_post_thumbnail(
					$id,
					'medium_large',
					array(
						'loading' => 'lazy',
						'alt'     => '',
					)
				);

				echo '<article class="mmm-recipes-index__card">';
				if ( $thumb ) {
					echo '<div class="ast-blog-featured-section post-thumb"><div class="post-thumb-img-content">'
						. '<a href="' . esc_url( $permalink ) . '">'
						. $thumb // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped markup.
						. '</a></div></div>';
				}
				echo '<div class="post-content">';
				echo '<h3 class="entry-title"><a href="' . esc_url( $permalink ) . '">'
					. esc_html( get_the_title( $id ) ) . '</a></h3>';
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode/helper output is already escaped.
				echo $this->build_recipe_archive_meta_chips( (int) $id );
				if ( $has_rating ) {
					echo do_shortcode( '[mmm_recipe_rating hide_empty=1]' );
				}
				if ( $has_actions ) {
					echo do_shortcode( '[mmm_recipe_actions]' );
				}
				echo '</div>';
				echo '</article>';
			}
			echo '</div>';
			echo '</section>';
		}
		echo '</div>';
		wp_reset_postdata();

		return (string) ob_get_clean();
	}

	/**
	 * Shortcode [mmm_new_recipes]: a compact, horizontally scrollable row of the
	 * most recently published recipes. For browsing the recipe archive.
	 *
	 * @param array|string $atts count, title.
	 * @return string
	 */
	public function mmm_new_recipes_callback( $atts = array() ): string {

		$atts = shortcode_atts(
			array(
				'count' => 8,
				'title' => '',
				'boxed' => '1',
			),
			$atts,
			'mmm_new_recipes'
		);

		$count = min( 24, max( 1, (int) $atts['count'] ) );

		$ids = get_posts(
			Mixmymeals_Post_Types::exclude_unlisted(
				array(
					'post_type'      => 'mmm_recipe',
					'post_status'    => 'publish',
					'posts_per_page' => $count,
					'orderby'        => 'date',
					'order'          => 'DESC',
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			)
		);

		return $this->render_recipe_browse_section(
			$ids,
			(string) $atts['title'],
			'new',
			filter_var( $atts['boxed'], FILTER_VALIDATE_BOOLEAN )
		);
	}

	/**
	 * Shortcode [mmm_favorite_recipes]: a compact, horizontally scrollable row of
	 * the recipes flagged as favorite (Details meta box → "Mark as favorite").
	 * Returns '' when nothing is flagged, so the section disappears cleanly.
	 *
	 * @param array|string $atts count, title.
	 * @return string
	 */
	public function mmm_favorite_recipes_callback( $atts = array() ): string {

		$atts = shortcode_atts(
			array(
				'count' => 8,
				'title' => '',
				'boxed' => '1',
			),
			$atts,
			'mmm_favorite_recipes'
		);

		$count = min( 24, max( 1, (int) $atts['count'] ) );

		$ids = get_posts(
			Mixmymeals_Post_Types::exclude_unlisted(
				array(
					'post_type'      => 'mmm_recipe',
					'post_status'    => 'publish',
					'posts_per_page' => $count,
					'orderby'        => 'date',
					'order'          => 'DESC',
					'fields'         => 'ids',
					'no_found_rows'  => true,
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'meta_query'     => array(
						array(
							'key'   => '_mmm_is_favorite',
							'value' => '1',
						),
					),
				)
			)
		);

		return $this->render_recipe_browse_section(
			$ids,
			(string) $atts['title'],
			'fav',
			filter_var( $atts['boxed'], FILTER_VALIDATE_BOOLEAN )
		);
	}

	/**
	 * Shortcode [mmm_popular_recipes]: a compact, horizontally scrollable row of
	 * the recipes that have been cooked most often, ranked by the cook counter
	 * maintained in Mixmymeals_Recipe. Returns '' when nothing has been
	 * cooked yet, so the section disappears cleanly.
	 *
	 * @param array|string $atts count.
	 * @return string
	 */
	public function mmm_popular_recipes_callback( $atts = array() ): string {

		$atts = shortcode_atts(
			array(
				'count' => 8,
				'title' => '',
				'boxed' => '1',
			),
			$atts,
			'mmm_popular_recipes'
		);

		$count = min( 24, max( 1, (int) $atts['count'] ) );

		// Recipes only carry the counter once they have been cooked, so joining
		// on the meta key already restricts this to a count of one or more.
		$ids = get_posts(
			Mixmymeals_Post_Types::exclude_unlisted(
				array(
					'post_type'      => 'mmm_recipe',
					'post_status'    => 'publish',
					'posts_per_page' => $count,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_key'       => Mixmymeals_Recipe::META_COOK_COUNT,
					'orderby'        => array(
						'meta_value_num' => 'DESC',
						'date'           => 'DESC',
					),
				)
			)
		);

		return $this->render_recipe_browse_section(
			array_map( 'intval', (array) $ids ),
			(string) $atts['title'],
			'popular',
			filter_var( $atts['boxed'], FILTER_VALIDATE_BOOLEAN )
		);
	}

	/**
	 * Shortcode [mmm_uncooked_recipes]: a compact, horizontally scrollable row of
	 * the recipes that have never been cooked — no meal in a published plan
	 * references them. The counterpart to [mmm_popular_recipes], useful for
	 * spotting recipes still waiting for their first plan. Returns '' when every
	 * recipe has been cooked, so the section disappears cleanly.
	 *
	 * @param array|string $atts count, title, boxed.
	 * @return string
	 */
	public function mmm_uncooked_recipes_callback( $atts = array() ): string {

		$atts = shortcode_atts(
			array(
				'count' => 8,
				'title' => '',
				'boxed' => '1',
			),
			$atts,
			'mmm_uncooked_recipes'
		);

		$count = min( 24, max( 1, (int) $atts['count'] ) );

		// A recipe carries no cook counter until it lands on a published plan,
		// so "never cooked" is simply the absence of the meta.
		$ids = get_posts(
			Mixmymeals_Post_Types::exclude_unlisted(
				array(
					'post_type'      => 'mmm_recipe',
					'post_status'    => 'publish',
					'posts_per_page' => $count,
					'orderby'        => 'date',
					'order'          => 'DESC',
					'fields'         => 'ids',
					'no_found_rows'  => true,
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'meta_query'     => array(
						array(
							'key'     => Mixmymeals_Recipe::META_COOK_COUNT,
							'compare' => 'NOT EXISTS',
						),
					),
				)
			)
		);

		return $this->render_recipe_browse_section(
			array_map( 'intval', (array) $ids ),
			(string) $atts['title'],
			'uncooked',
			filter_var( $atts['boxed'], FILTER_VALIDATE_BOOLEAN )
		);
	}

	/**
	 * Wraps the browse cards in a self-contained section so the new/favorite/
	 * popular/uncooked shortcodes render correctly on any page (not just the recipe
	 * archive): an optional boxed <section>, an optional heading and the
	 * horizontal scroll track. Returns '' when there are no cards.
	 *
	 * The heading text is the caller's responsibility (the theme passes the
	 * localized title); the plugin never invents a default heading.
	 *
	 * @param int[]  $ids     Recipe post IDs.
	 * @param string $title   Heading text; '' renders no heading.
	 * @param string $variant Modifier key (new|fav|popular) for CSS hooks.
	 * @param bool   $boxed   Whether to wrap the row in the boxed section.
	 * @return string
	 */
	private function render_recipe_browse_section( array $ids, string $title, string $variant, bool $boxed ): string {

		return $this->render_browse_section(
			$this->render_recipe_browse_cards( $ids ),
			$title,
			$variant,
			$boxed
		);
	}

	/**
	 * Wraps already-rendered browse cards in the shared self-contained section:
	 * an optional heading, then the boxed <section> holding the horizontal scroll
	 * track. Shared by the recipe and meal-plan browse shortcodes so they render
	 * identically on any page. Returns '' when there are no cards.
	 *
	 * In a boxed row the heading sits INSIDE the box, mirroring the ingredient
	 * archive where every category card carries its own heading. Unboxed rows
	 * keep the heading in front of the track, so a title-less row with an
	 * editor-supplied heading block above it (as on the front page) still looks
	 * the same.
	 *
	 * @param string $cards   Pre-rendered, already-escaped card markup ('' = none).
	 * @param string $title   Heading text; '' renders no heading.
	 * @param string $variant Modifier key (new|fav|popular|uncooked|latest) for CSS hooks.
	 * @param bool   $boxed   Whether to wrap the row in the boxed section.
	 * @return string
	 */
	private function render_browse_section( string $cards, string $title, string $variant, bool $boxed ): string {

		if ( '' === $cards ) {
			return '';
		}

		$variant     = sanitize_html_class( $variant );
		$row_classes = 'mmm-recipe-row' . ( '' !== $variant ? ' mmm-recipe-row--' . $variant : '' );

		$heading = '' !== $title
			? '<h2 class="mmm-recipe-row__title">' . esc_html( $title ) . '</h2>'
			: '';

		// $cards is markup already escaped by the card renderer.
		$row = '<div class="' . esc_attr( $row_classes ) . '">'
			. '<div class="mmm-recipe-row__track">' . $cards . '</div></div>';

		if ( ! $boxed ) {
			return $heading . $row;
		}

		$section_classes = 'mmm-recipe-browse' . ( '' !== $variant ? ' mmm-recipe-browse--' . $variant : '' );

		return '<section class="' . esc_attr( $section_classes ) . '">'
			. $heading . $row . '</section>';
	}

	/**
	 * Renders the recipe cards (image, title and rating) for a browse row. The
	 * surrounding section, heading and horizontal scroll track are added by
	 * render_recipe_browse_section(). Shared by the new/favorite/popular recipe
	 * shortcodes. Returns '' when there are no recipes.
	 *
	 * @param int[] $ids Recipe post IDs.
	 * @return string
	 */
	private function render_recipe_browse_cards( array $ids ): string {

		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		if ( empty( $ids ) ) {
			return '';
		}

		$has_rating  = shortcode_exists( 'mmm_recipe_rating' );
		$has_actions = shortcode_exists( 'mmm_recipe_actions' );

		ob_start();
		foreach ( $ids as $id ) {
			// Set up the global post so shortcodes that rely on the current post
			// (e.g. the "Add to Planner" action) resolve to this recipe.
			$GLOBALS['post'] = get_post( $id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			setup_postdata( $GLOBALS['post'] );

			$permalink = (string) get_permalink( $id );
			$thumb     = get_the_post_thumbnail(
				$id,
				'medium',
				array(
					'loading' => 'lazy',
					'alt'     => '',
				)
			);

			// Recipes without a photo render cookbook-style: no media block,
			// just the (nicely typeset) title — flagged via modifier class.
			echo '<article class="mmm-recipe-row__card' . ( $thumb ? '' : ' mmm-recipe-row__card--noimg' ) . '">';
			if ( $thumb ) {
				echo '<a class="mmm-recipe-row__media" href="' . esc_url( $permalink ) . '">'
					. $thumb // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped markup.
					. '</a>';
			}
			echo '<div class="mmm-recipe-row__body">';
			// Above the title, like in print: a disclosure that only appears
			// after the click does not do its job.
			echo self::disclosure_pill( $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
			echo '<h3 class="mmm-recipe-row__name"><a href="' . esc_url( $permalink ) . '">'
				. esc_html( get_the_title( $id ) ) . '</a></h3>';
			// These slim browse cards deliberately omit the meta pills; only the
			// rating (if any) and the "Add to Planner" action are shown.
			if ( $has_rating ) {
				echo do_shortcode( '[mmm_recipe_rating id="' . (int) $id . '" hide_empty=1]' );
			}
			if ( $has_actions ) {
				echo do_shortcode( '[mmm_recipe_actions]' );
			}
			echo '</div>';
			echo '</article>';
		}
		wp_reset_postdata();

		return (string) ob_get_clean();
	}


	/**
	 * Shortcode [mmm_latest_meal_plans]: a compact, horizontally scrollable row of
	 * the most recently published meal plans. The meal-plan counterpart to
	 * [mmm_new_recipes]; reuses the same self-contained browse-row markup so it can
	 * be dropped on any page. Returns '' when no plans are published.
	 *
	 * @param array|string $atts count, title, boxed.
	 * @return string
	 */
	public function mmm_latest_meal_plans_callback( $atts = array() ): string {

		$atts = shortcode_atts(
			array(
				'count' => 8,
				'title' => '',
				'boxed' => '1',
			),
			$atts,
			'mmm_latest_meal_plans'
		);

		$count = min( 24, max( 1, (int) $atts['count'] ) );

		$ids = get_posts(
			Mixmymeals_Post_Types::exclude_unlisted(
				array(
					'post_type'      => 'mmm_meal_plan',
					'post_status'    => 'publish',
					'posts_per_page' => $count,
					'orderby'        => 'date',
					'order'          => 'DESC',
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			)
		);

		return $this->render_browse_section(
			$this->render_meal_plan_browse_cards( array_map( 'intval', $ids ) ),
			(string) $atts['title'],
			'latest',
			filter_var( $atts['boxed'], FILTER_VALIDATE_BOOLEAN )
		);
	}

	/**
	 * Shortcode [mmm_latest_plan_teaser]: a large, self-contained teaser card
	 * for the newest published meal plan — image (the plan's own featured image,
	 * falling back to the best recipe photo), title, day/meal meta, excerpt and
	 * a call-to-action, all linking to the plan. Built for landing pages; the
	 * link always follows the newest plan automatically. Returns '' when no
	 * plan is published.
	 *
	 * @param array|string $atts title (optional section heading), cta_text.
	 * @return string
	 */
	public function mmm_latest_plan_teaser_callback( $atts = array() ): string {

		$atts = shortcode_atts(
			array(
				'title'    => '',
				'cta_text' => '',
			),
			$atts,
			'mmm_latest_plan_teaser'
		);

		$plans = get_posts(
			Mixmymeals_Post_Types::exclude_unlisted(
				array(
					'post_type'      => 'mmm_meal_plan',
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'orderby'        => 'date',
					'order'          => 'DESC',
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			)
		);

		if ( empty( $plans ) ) {
			return '';
		}

		$plan_id = (int) $plans[0];
		$url     = (string) get_permalink( $plan_id );
		$thumb   = $this->get_meal_plan_card_thumbnail( $plan_id, 'large' );
		$meta    = $this->render_meal_plan_card_meta( $plan_id );
		$cta     = '' !== trim( (string) $atts['cta_text'] )
			? (string) $atts['cta_text']
			: __( 'View plan', 'mixmymeals' );

		ob_start();
		?>
		<section class="mmm-plan-teaser<?php echo '' === $thumb ? ' mmm-plan-teaser--noimg' : ''; ?>">
			<?php if ( '' !== trim( (string) $atts['title'] ) ) : ?>
				<h2 class="mmm-recipe-row__title"><?php echo esc_html( (string) $atts['title'] ); ?></h2>
			<?php endif; ?>
			<a class="mmm-plan-teaser__card" href="<?php echo esc_url( $url ); ?>">
				<?php if ( '' !== $thumb ) : ?>
					<div class="mmm-plan-teaser__media">
						<?php echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- image markup from get_the_post_thumbnail(). ?>
					</div>
				<?php endif; ?>
				<div class="mmm-plan-teaser__body">
					<span class="mmm-plan-teaser__kicker"><?php esc_html_e( 'Latest meal plan', 'mixmymeals' ); ?></span>
					<h3 class="mmm-plan-teaser__heading"><?php echo esc_html( get_the_title( $plan_id ) ); ?></h3>
					<?php echo $meta; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
					<?php if ( has_excerpt( $plan_id ) ) : ?>
						<p class="mmm-plan-teaser__lead"><?php echo esc_html( get_the_excerpt( $plan_id ) ); ?></p>
					<?php endif; ?>
					<span class="mmm-btn mmm-btn--primary mmm-plan-teaser__cta"><?php echo esc_html( $cta ); ?></span>
				</div>
			</a>
		</section>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Shortcode [mmm_top_rated_recipes]: a browse row of the best-rated recipes
	 * (comment-based star ratings), ordered by average rating, then by number
	 * of ratings. Recipes with fewer than min_ratings ratings are skipped.
	 * Returns '' when nothing qualifies, so the section disappears cleanly.
	 *
	 * @param array|string $atts count, title, boxed, min_ratings.
	 * @return string
	 */
	public function mmm_top_rated_recipes_callback( $atts = array() ): string {

		$atts = shortcode_atts(
			array(
				'count'       => 8,
				'title'       => '',
				'boxed'       => '1',
				'min_ratings' => 1,
			),
			$atts,
			'mmm_top_rated_recipes'
		);

		$count = min( 24, max( 1, (int) $atts['count'] ) );
		$min   = max( 1, (int) $atts['min_ratings'] );

		$ids = get_posts(
			Mixmymeals_Post_Types::exclude_unlisted(
				array(
					'post_type'      => 'mmm_recipe',
					'post_status'    => 'publish',
					'posts_per_page' => $count,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'meta_query'     => array(
						'rating_avg'   => array(
							'key'  => '_mmm_rating_avg',
							'type' => 'DECIMAL(3,2)',
						),
						'rating_count' => array(
							'key'     => '_mmm_rating_count',
							'value'   => $min,
							'compare' => '>=',
							'type'    => 'NUMERIC',
						),
					),
					'orderby'        => array(
						'rating_avg'   => 'DESC',
						'rating_count' => 'DESC',
					),
				)
			)
		);

		return $this->render_recipe_browse_section(
			array_map( 'intval', $ids ),
			(string) $atts['title'],
			'toprated',
			filter_var( $atts['boxed'], FILTER_VALIDATE_BOOLEAN )
		);
	}

	/**
	 * Shortcode [mmm_community_photo_wall]: the newest approved visitor meal
	 * photos from all recipes as a mosaic; each tile links to the recipe the
	 * photo belongs to. Social proof for landing pages. Returns '' when no
	 * approved photos exist.
	 *
	 * @param array|string $atts count, title.
	 * @return string
	 */
	public function mmm_community_photo_wall_callback( $atts = array() ): string {

		$atts = shortcode_atts(
			array(
				'count' => 12,
				'title' => '',
			),
			$atts,
			'mmm_community_photo_wall'
		);

		if ( ! class_exists( 'Mixmymeals_Photos' ) ) {
			return '';
		}

		$count     = min( 24, max( 1, (int) $atts['count'] ) );
		$photo_ids = Mixmymeals_Photos::get_photos( 0, true, $count * 2 );

		$tiles = '';
		$used  = 0;
		foreach ( $photo_ids as $photo_id ) {
			$recipe_id = (int) wp_get_post_parent_id( $photo_id );
			if ( $recipe_id < 1 || 'publish' !== get_post_status( $recipe_id ) ) {
				continue;
			}
			$img = wp_get_attachment_image(
				(int) $photo_id,
				'medium_large',
				false,
				array(
					'loading' => 'lazy',
					'alt'     => get_the_title( $recipe_id ),
				)
			);
			if ( '' === $img ) {
				continue;
			}
			$tiles .= '<a class="mmm-image-wall__tile" href="' . esc_url( (string) get_permalink( $recipe_id ) ) . '">'
				. $img
				. '<span class="mmm-image-wall__label">' . esc_html( get_the_title( $recipe_id ) ) . '</span>'
				. '</a>';
			++$used;
			if ( $used >= $count ) {
				break;
			}
		}

		if ( '' === $tiles ) {
			return '';
		}

		$html = '<section class="mmm-image-wall mmm-image-wall--community">';
		if ( '' !== trim( (string) $atts['title'] ) ) {
			$html .= '<h2 class="mmm-recipe-row__title">' . esc_html( (string) $atts['title'] ) . '</h2>';
		}
		$html .= '<div class="mmm-image-wall__grid">' . $tiles . '</div></section>';

		return $html;
	}

	/**
	 * Renders the meal-plan cards (representative image, title and a slim day/
	 * recipe meta line) for the [mmm_latest_meal_plans] browse row, reusing the
	 * recipe browse-card CSS classes so it stays visually consistent. The
	 * surrounding section, heading and scroll track are added by
	 * render_browse_section(). Returns '' when there are no plans.
	 *
	 * @param int[] $ids Meal plan post IDs.
	 * @return string
	 */
	private function render_meal_plan_browse_cards( array $ids ): string {

		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		if ( empty( $ids ) ) {
			return '';
		}

		ob_start();
		foreach ( $ids as $id ) {

			$permalink = (string) get_permalink( $id );
			$thumb     = $this->get_meal_plan_card_thumbnail( $id );

			echo '<article class="mmm-recipe-row__card mmm-recipe-row__card--plan">';
			if ( '' !== $thumb ) {
				echo '<a class="mmm-recipe-row__media" href="' . esc_url( $permalink ) . '">'
					. $thumb // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped markup.
					. '</a>';
			}
			echo '<div class="mmm-recipe-row__body">';
			echo self::disclosure_pill( $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
			echo '<h3 class="mmm-recipe-row__name"><a href="' . esc_url( $permalink ) . '">'
				. esc_html( get_the_title( $id ) ) . '</a></h3>';
			echo $this->render_meal_plan_card_meta( $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
			echo '</div>';
			echo '</article>';
		}

		return (string) ob_get_clean();
	}

	/**
	 * Picks a single representative thumbnail for a meal-plan browse card: the
	 * plan's own featured image if it is a real image, otherwise the featured
	 * image of the lowest-priority recipe in the plan that has a real image. The
	 * placeholder image is never returned; when nothing suitable exists this
	 * returns '' so the card renders without media rather than a placeholder.
	 *
	 * @param int $plan_id Meal plan post ID.
	 * @return string Escaped <img> markup, or ''.
	 */
	private function get_meal_plan_card_thumbnail( int $plan_id, string $size = 'medium' ): string {

		// The plan's own featured image wins when set (thumbnail queries are
		// truthful — no placeholder is forced anywhere anymore).
		$plan_thumb_id = (int) get_post_thumbnail_id( $plan_id );
		if ( $plan_thumb_id ) {
			return (string) get_the_post_thumbnail(
				$plan_id,
				$size,
				array(
					'loading' => 'lazy',
					'alt'     => '',
				)
			);
		}

		$plan           = new Mixmymeals_Meal_Plan( $plan_id );
		$best_recipe_id = 0;
		$best_priority  = PHP_INT_MAX;

		foreach ( $plan->get_meals() as $meal_post ) {
			$meal      = new Mixmymeals_Meal( $meal_post->ID );
			$recipe_id = (int) $meal->get_recipe_id();

			if ( $recipe_id <= 0 || ! Mixmymeals_Recipe::exists_by_id( $recipe_id ) ) {
				continue;
			}

			$thumb_id = (int) get_post_thumbnail_id( $recipe_id );
			if ( ! $thumb_id ) {
				continue;
			}

			$priority = ( new Mixmymeals_Recipe( $recipe_id ) )->get_priority();
			if ( $priority < $best_priority ) {
				$best_priority  = $priority;
				$best_recipe_id = $recipe_id;
			}
		}

		if ( $best_recipe_id > 0 ) {
			return (string) get_the_post_thumbnail(
				$best_recipe_id,
				$size,
				array(
					'loading' => 'lazy',
					'alt'     => '',
				)
			);
		}

		return '';
	}

	/**
	 * Renders the slim day/recipe meta line shown under a meal-plan browse card,
	 * reusing the mmm-meal-plan-meta markup from the plan archive. Returns '' when
	 * the plan has neither days nor meals.
	 *
	 * @param int $plan_id Meal plan post ID.
	 * @return string
	 */
	private function render_meal_plan_card_meta( int $plan_id ): string {

		$plan  = new Mixmymeals_Meal_Plan( $plan_id );
		$days  = $plan->count_days();
		$meals = $plan->count_meals();

		if ( ! $days && ! $meals ) {
			return '';
		}

		$out = '<div class="mmm-meal-plan-meta">';
		if ( $days ) {
			/* translators: %s: number of days. */
			$out .= '<span class="mmm-meta__item mmm-meta__item--days">'
				. esc_html( sprintf( _n( '%s Day', '%s Days', $days, 'mixmymeals' ), $days ) )
				. '</span>';
		}
		if ( $meals ) {
			/* translators: %s: number of recipes. */
			$out .= '<span class="mmm-meta__item mmm-meta__item--meals">'
				. esc_html( sprintf( _n( '%s Recipe', '%s Recipes', $meals, 'mixmymeals' ), $meals ) )
				. '</span>';
		}
		$out .= '</div>';

		return $out;
	}


	/**
	 * Shortcode [mmm_equipment_required_for_a_single_meal].
	 * Renders a list of equipment required for the recipe linked to the current meal.
	 *
	 * @return string
	 */
	public function mmm_equipment_required_for_a_single_meal_callback(): string {

		$meal      = new Mixmymeals_Meal( get_the_ID() );
		$recipe_id = $meal->get_recipe_id();

		if ( ! $recipe_id ) {
			return '';
		}

		return $this->mmm_equipment_required_for_a_single_recipe_callback( [ 'post_id' => $recipe_id ] );

	}

	/**
	 * Shortcode [mmm_scaled_recipe_steps_for_a_single_meal].
	 * Renders the recipe steps for the current meal, with ingredient amounts scaled
	 * to the meal's serving count.
	 *
	 * @return string
	 */
	public function mmm_scaled_recipe_steps_for_a_single_meal_callback(): string {

		$post_id = get_the_ID();

		if ( get_post_type( $post_id ) !== 'mmm_meal' ) {
			return '<p>'
			       . esc_html__( 'This shortcode must be used inside a meal.',
					'mixmymeals' )
			       . '</p>';
		}

		$meal      = new Mixmymeals_Meal( $post_id );
		$recipe_id = $meal->get_recipe_id();

		if ( ! $recipe_id ) {
			return '<p>' . esc_html__( 'No recipe linked to this meal.',
					'mixmymeals' ) . '</p>';
		}

		$recipe = new Mixmymeals_Recipe( $recipe_id );

		$steps = $recipe->get_steps();

		if ( empty( $steps ) ) {
			return '<p>' . esc_html__( 'No steps defined for this recipe.',
					'mixmymeals' ) . '</p>';
		}

		$recipe_servings = max( 1.0, floatval( $recipe->get_servings() ) );
		$meal_servings   = max( 1.0, floatval( $meal->get_servings() ) );
		$scaling_factor  = $meal_servings / $recipe_servings;

		$ingredients_scaled = $recipe->get_ingredients_scaled( $scaling_factor );

		$ingredients_map = [];
		foreach ( $ingredients_scaled as $ing ) {
			$key = $ing['id'] . '_' . ( $ing['amount_id'] ?? 0 ) . '_' . ( $ing['step'] ?? 0 );
			$ingredients_map[ $key ] = $ing;
		}

		ob_start();
		?>
        <div class="mmm-recipe-steps scaled">
			<?php foreach ( $steps as $index => $step ) : ?>
                <div class="mmm-step">

                    <div class="mmm-step-header">
						<?php if ( ! empty( $step['title'] ) ) : ?>
                            <h3 class="mmm-step-title">
								<?php
								echo esc_html(
									sprintf( __( 'Step %d', 'mixmymeals' ),
										$index + 1 ) . ': ' . $step['title']
								);
								?>
                            </h3>
						<?php endif; ?>
                    </div>

					<?php if ( ! empty( $step['image'] ) ) : ?>
                        <div class="mmm-step-image">
							<?php echo wp_get_attachment_image(
								$step['image'],
								'large',
								false,
								[ 'class' => 'mmm-step-img' ]
							); ?>
                        </div>
					<?php endif; ?>

					<?php if ( ! empty( $step['description'] ) ) : ?>
                        <div class="mmm-step-description">
							<?php echo wp_kses_post( $step['description'] ); ?>
                        </div>
					<?php endif; ?>

					<?php if ( ! empty( $step['ingredients'] ) ) : ?>
                        <ul class="mmm-step-ingredients">
							<?php foreach ( $step['ingredients'] as $ing ) :

								if ( empty( $ing['id'] ) ) {
									continue;
								}

								$ingredient_obj = new Mixmymeals_Ingredient( $ing['id'] );
								if ( ! $ingredient_obj->is_valid() ) {
									continue;
								}

								$amount_id = ! empty( $ing['amount_id'] )
									? intval( $ing['amount_id'] ) : 0;

								$is_optional = ! empty( $ing['optional'] );

								$key = $ing['id'] . '_' . $amount_id . '_' . $index;

								// Werte aus skalierter Quelle holen
								$scaled_amount = $ingredients_map[ $key ]['amount'] ?? 0;
								$total_weight  = $ingredients_map[ $key ]['weight_g'] ?? 0;

								$unit_title      = '';
								$weight_per_unit = 0.0;

								if ( $amount_id ) {
									$amt_obj        = new Mixmymeals_Ingredient_Amount( $amount_id );
									$unit_title     = $amt_obj->get_title() ?: '';
									$weight_per_unit = floatval( $amt_obj->get_weight() );
								}

								if ( ! $amount_id ) {
									$nutrition = $ingredient_obj->get_nutrition();

									if ( ! empty( $nutrition['weight_item'] ) ) {
										$weight_per_unit = floatval( $nutrition['weight_item'] );
										if ( empty( $unit_title ) ) {
											$unit_title = 'g';
										}
									}
								}

								$display_amount = $scaled_amount > 0
									? rtrim( rtrim( number_format_i18n( $scaled_amount, 2 ), '0' ), ',' )
									: '';

								$line = '';
								if ( $display_amount ) {
									$line .= $display_amount . ' ' . ( $unit_title ?: 'g' );

									if ( $weight_per_unit > 0 ) {
										$line .= ' à ' . number_format_i18n( $weight_per_unit ) . ' g';
									}

									if ( $total_weight > 0 ) {
										$line .= ' (≙ ' . number_format_i18n( $total_weight ) . ' g)';
									}
								}
								?>
                                <li>
                                <span class="mmm-ingredient-name">
									<?php echo esc_html( get_the_title( $ing['id'] ) ); ?><?php if ( $is_optional ) : ?> (<?php echo esc_html__( 'optional', 'mixmymeals' ); ?>)<?php endif; ?>
                                </span>
									<?php if ( $line ) : ?>
                                        <span class="mmm-ingredient-amount">
										<?php echo esc_html( $line ); ?>
                                    </span>
									<?php endif; ?>
                                </li>
							<?php endforeach; ?>
                        </ul>
					<?php endif; ?>
                </div>
			<?php endforeach; ?>
        </div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Shortcode [mmm_meal_nutrition_table_for_a_single_meal].
	 * Renders a nutrition table for the current meal, scaled to the meal's serving count.
	 *
	 * @return string
	 */
	public function mmm_meal_nutrition_table_for_a_single_meal_callback(): string {

		$post_id = get_the_ID();

		if ( ! $post_id || get_post_type( $post_id ) !== 'mmm_meal' ) {
			return '<p>'
			       . esc_html__( 'This shortcode can only be used on meal posts.',
					'mixmymeals' )
			       . '</p>';
		}

		$meal      = new Mixmymeals_Meal( $post_id );
		$recipe_id = $meal->get_recipe_id();

		if ( ! $recipe_id ) {
			return '<p>' . esc_html__( 'No recipe assigned to this meal.',
					'mixmymeals' ) . '</p>';
		}

		$recipe = new Mixmymeals_Recipe( $recipe_id );

		$recipe_servings = max( 1.0, floatval( $recipe->get_servings() ) );
		$meal_servings   = max( 1.0, floatval( $meal->get_servings() ) );
		$factor          = $meal_servings / $recipe_servings;

		$ingredients = $recipe->get_ingredients_scaled( $factor );

		if ( empty( $ingredients ) ) {
			return '<p>'
			       . esc_html__( 'No ingredients found for this recipe.',
					'mixmymeals' )
			       . '</p>';
		}

		$totals = [
			'calories' => 0,
			'protein'  => 0,
			'carbs'    => 0,
			'fat'      => 0,
			'sugar'    => 0,
			'fiber'    => 0,
			'salt'     => 0,
			'weight'   => 0,
		];

		foreach ( $ingredients as $ing ) {

			if ( empty( $ing['id'] ) || empty( $ing['weight_g'] ) ) {
				continue;
			}

			$ingredient_obj = new Mixmymeals_Ingredient( $ing['id'] );

			if ( ! $ingredient_obj->is_valid() ) {
				continue;
			}

			$nutrition = $ingredient_obj->get_nutrition();
			$weight    = floatval( $ing['weight_g'] );

			if ( $weight <= 0 ) {
				continue;
			}

			$totals['weight'] += $weight;

			$totals['calories'] += (float) ( $nutrition['calories_100'] ?? 0 ) * $weight / 100;
			$totals['protein']  += (float) ( $nutrition['protein'] ?? 0 ) * $weight / 100;
			$totals['carbs']    += (float) ( $nutrition['carbs'] ?? 0 ) * $weight / 100;
			$totals['fat']      += (float) ( $nutrition['fat'] ?? 0 ) * $weight / 100;
			$totals['sugar']    += (float) ( $nutrition['sugar'] ?? 0 ) * $weight / 100;
			$totals['fiber']    += (float) ( $nutrition['fiber'] ?? 0 ) * $weight / 100;
			$totals['salt']     += (float) ( $nutrition['salt'] ?? 0 ) * $weight / 100;
		}

		$per_serving = [];
		foreach ( $totals as $key => $value ) {
			if ( $key === 'weight' ) {
				continue;
			}
			$per_serving[ $key ] = $meal_servings > 0 ? $value / $meal_servings
				: 0;
		}

		$weight_per_serving = $meal_servings > 0
			? $totals['weight'] / $meal_servings
			: 0;

		$per_100g = [];
		foreach ( $totals as $key => $value ) {
			if ( $key === 'weight' ) {
				continue;
			}
			$per_100g[ $key ] = $totals['weight'] > 0
				? ( $value / $totals['weight'] ) * 100
				: 0;
		}

		ob_start();
		?>
        <div class="mmm-meal-nutrition">
            <table class="mmm-nutrition-table">
                <thead>
                <tr>
                    <th><?php esc_html_e( 'Nutrition', 'mixmymeals' ); ?></th>
                    <th>
						<?php esc_html_e( 'per serving', 'mixmymeals' ); ?>
						<?php echo ' ('
						           . esc_html( round( $weight_per_serving ) )
						           . ' g)'; ?>
                    </th>
                    <th><?php esc_html_e( 'per 100 g', 'mixmymeals' ); ?></th>
                </tr>
                </thead>
                <tbody>

				<?php
				$rows = [
					'calories' => 'kcal',
					'protein'  => 'g',
					'carbs'    => 'g',
					'fat'      => 'g',
					'sugar'    => 'g',
					'fiber'    => 'g',
					'salt'     => 'g',
				];

				foreach ( $rows as $key => $unit ) :
					?>
                    <tr>
                        <td><?php echo esc_html( ucfirst( $key ) ); ?></td>
						<?php
						$is_calories = ( $key === 'calories' );

						$serving_value = $is_calories
							? round( $per_serving[ $key ] )
							: round( $per_serving[ $key ], 1 );

						$per100_value = $is_calories
							? round( $per_100g[ $key ] )
							: round( $per_100g[ $key ], 1 );
						?>

                        <td><?php echo esc_html( $serving_value ); ?><?php echo esc_html( $unit ); ?></td>
                        <td><?php echo esc_html( $per100_value ); ?><?php echo esc_html( $unit ); ?></td>
                    </tr>
				<?php endforeach; ?>

                </tbody>
            </table>
        </div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Shortcode [mmm_meals_in_a_single_meal_plan].
	 * Renders a grouped overview of all meals in a meal plan, grouped by day and meal type.
	 *
	 * @param array|string $atts Shortcode attributes: meal_plan_id, show_dates, empty_text.
	 * @return string
	 */
	public function mmm_meals_in_a_single_meal_plan_callback( $atts = [] ): string {

		$atts = shortcode_atts(
			[
				'meal_plan_id' => null,
				'show_dates'   => false,
				// Text für einen Plan ohne Mahlzeiten. Leerer Wert = gar nichts
				// ausgeben, damit der Abschnitt bei Bedarf ganz verschwinden kann.
				'empty_text'   => __( 'This meal plan does not contain any meals yet.', 'mixmymeals' ),
			],
			$atts,
			'mmm_meals_in_a_single_meal_plan'
		);

		$meal_plan_id = $atts['meal_plan_id'];
		$show_dates   = filter_var( $atts['show_dates'],
			FILTER_VALIDATE_BOOLEAN );

		if ( $meal_plan_id === 'current' ) {
			$meal_plan_id = Mixmymeals_Meal_Plan::get_current_plan_id();
		}

		if ( ! $meal_plan_id ) {
			$meal_plan_id = get_the_ID();
		}

		$meal_plan_id = (int) $meal_plan_id;

		if ( ! $meal_plan_id
		     || get_post_type( $meal_plan_id ) !== 'mmm_meal_plan' ) {
			return '<p>' . esc_html__( 'No valid meal plan found.',
					'mixmymeals' ) . '</p>';
		}

		$meal_plan = new Mixmymeals_Meal_Plan( $meal_plan_id );
		$meals     = $meal_plan->get_meals();

		/*
		 * Plan ohne Mahlzeiten: Der Tages-Rail wäre sonst eine leere Hülle, weil
		 * jeder Tag ohne Einträge übersprungen wird. Stattdessen ein Hinweis,
		 * dessen Wortlaut der Aufrufer bestimmt.
		 */
		if ( empty( $meals ) ) {
			$empty_text = trim( (string) $atts['empty_text'] );

			return '' === $empty_text
				? ''
				: '<p class="mmm-plan-empty-note">' . esc_html( $empty_text ) . '</p>';
		}

		$days = $meal_plan->get_day_range();

		$weekday_labels = [
			1 => __( 'Monday', 'mixmymeals' ),
			2 => __( 'Tuesday', 'mixmymeals' ),
			3 => __( 'Wednesday', 'mixmymeals' ),
			4 => __( 'Thursday', 'mixmymeals' ),
			5 => __( 'Friday', 'mixmymeals' ),
			6 => __( 'Saturday', 'mixmymeals' ),
			7 => __( 'Sunday', 'mixmymeals' ),
		];

		$meal_types = Mixmymeals_Meal::get_plaintext_meal_type_map();

		$grouped_meals = [];

		foreach ( $meals as $meal_post ) {
			$meal    = new Mixmymeals_Meal( $meal_post->ID );
			$weekday = (int) $meal->get_day();
			$type    = $meal->get_type() ?: 'other';

			if ( $weekday < 1 || $weekday > 7 ) {
				continue;
			}

			$grouped_meals[ $weekday ][ $type ][] = $meal;
		}

		$today = (int) current_time( 'N' );

		$currency = get_option( 'mixmymeals_currency', '€' );

		ob_start();
		?>

        <div class="mmm-plan-rail"
             data-meal-plan-id="<?php echo esc_attr( $meal_plan_id ); ?>">

			<?php foreach ( $days as $day_number ) : ?>

				<?php if ( empty( $grouped_meals[ $day_number ] ) ) {
					continue;
				} ?>

				<?php
				/*
				 * Mahlzeiten des Tages nach Typ gruppieren, in der Reihenfolge
				 * aus get_plaintext_meal_type_map(). Ein Typ ergibt genau eine
				 * Gruppe, auch wenn mehrere Mahlzeiten darauf entfallen — sonst
				 * stünde etwa „Abendessen“ zweimal untereinander.
				 *
				 * $day_meals bleibt zusätzlich flach: daran hängen die Prüfung
				 * auf den Ein-Mahlzeit-Tag und die Tagessumme im Kopf.
				 */
				$day_groups = [];
				$day_meals  = [];
				foreach ( $meal_types as $type_key => $type_label ) {

					$type_entries = [];
					foreach ( $grouped_meals[ $day_number ][ $type_key ] ?? [] as $day_meal ) {
						$entry          = [
							'meal'       => $day_meal,
							'type_label' => $type_label,
						];
						$type_entries[] = $entry;
						$day_meals[]    = $entry;
					}

					if ( ! empty( $type_entries ) ) {
						$day_groups[] = [
							'label'   => $type_label,
							'entries' => $type_entries,
						];
					}
				}
				$day_head_info = $this->get_meals_head_info( $day_meals );
				?>

                <div class="mmm-rail-day<?php echo $day_number === $today ? ' mmm-rail-day--today' : ''; ?>">
                    <span class="mmm-rail-day__dot"></span>

                    <div class="mmm-rail-day__head">
                        <span class="mmm-rail-day__name"><?php echo esc_html( $weekday_labels[ $day_number ] ); ?></span>
						<?php if ( $day_number === $today ) : ?>
                            <span class="mmm-badge"><?php esc_html_e( 'Today', 'mixmymeals' ); ?></span>
						<?php endif; ?>
						<?php if ( $day_head_info ) : ?>
                            <span class="mmm-rail-day__kcal mmm-rail-day__kcal--day"><?php echo esc_html( $day_head_info ); ?></span>
						<?php endif; ?>
                    </div>

					<?php foreach ( $day_groups as $day_group ) : ?>

						<?php
						/*
						 * Ein Kopf je Typ, immer unterhalb des Tages — auch wenn
						 * an dem Tag nur eine Mahlzeit geplant ist. Die kcal-
						 * Angabe summiert alle Mahlzeiten dieses Typs.
						 *
						 * Gibt es an dem Tag nur einen Typ, wäre diese Summe
						 * identisch mit der im Tageskopf — dann bleibt sie weg,
						 * statt dieselbe Zahl zweimal untereinander zu zeigen.
						 */
						$head_info = count( $day_groups ) > 1
							? $this->get_meals_head_info( $day_group['entries'] )
							: '';
						?>
                        <div class="mmm-rail-day__sub">
                            <span class="mmm-rail-day__meal"><?php echo esc_html( $day_group['label'] ); ?></span>
							<?php if ( $head_info ) : ?>
                                <span class="mmm-rail-day__kcal"><?php echo esc_html( $head_info ); ?></span>
							<?php endif; ?>
                        </div>

						<?php foreach ( $day_group['entries'] as $entry ) :
							$meal      = $entry['meal'];
							$recipe_id = (int) $meal->get_recipe_id();
							$recipe    = new Mixmymeals_Recipe( $recipe_id );
							?>

						<?php if ( ! $recipe->is_valid() ) : ?>
                            <div class="mmm-recipe-card mmm-recipe-card--missing">
                                <div class="mmm-recipe-card__media mmm-recipe-card__media--empty">
                                    <span><?php esc_html_e( 'No image yet', 'mixmymeals' ); ?></span>
                                </div>
                                <div class="mmm-recipe-card__body">
                                    <h3 class="mmm-recipe-card__title"><?php esc_html_e( 'This recipe does not exist anymore.', 'mixmymeals' ); ?></h3>
                                </div>
                            </div>
							<?php
							continue;
						endif;

						$prep_minutes     = $recipe->get_preparation_time();
						$prep_time_string = '';
						if ( $prep_minutes > 0 ) {
							if ( $prep_minutes < 60 ) {
								$prep_time_string = sprintf( '%s min',
									number_format_i18n( $prep_minutes ) );
							} else {
								$hours            = floor( $prep_minutes / 60 );
								$minutes          = $prep_minutes % 60;
								$prep_time_string = sprintf( '%s h',
									number_format_i18n( $hours ) );
								if ( $minutes > 0 ) {
									$prep_time_string .= ' ' . sprintf( '%s min',
										number_format_i18n( $minutes ) );
								}
							}
						}

						$meal_url   = get_permalink( $meal->get_id() );
						$title      = get_the_title( $recipe_id );
						$categories = $recipe->get_categories();
						$tags       = get_the_terms( $recipe_id, 'mmm_recipe_tag' );
						if ( is_wp_error( $tags ) ) {
							$tags = [];
						}

						// No placeholder fallback: photo-less recipes render
						// cookbook-style without a media box (title only).
						$thumb_id = (int) get_post_thumbnail_id( $recipe_id );

						$cost = $recipe->calculate_cost_per_serving();
						?>
                        <a class="mmm-recipe-card<?php echo $thumb_id ? '' : ' mmm-recipe-card--noimg'; ?>" href="<?php echo esc_url( $meal_url ); ?>">
							<?php if ( $thumb_id ) : ?>
                            <div class="mmm-recipe-card__media">
								<?php
								echo wp_get_attachment_image( $thumb_id, 'medium_large', false, [
									'alt'     => esc_attr( $title ),
									'loading' => 'lazy',
									'sizes'   => '(max-width: 640px) 84px, 124px',
								] );
								?>
                            </div>
							<?php endif; ?>

                            <div class="mmm-recipe-card__body">
                                <h3 class="mmm-recipe-card__title"><?php echo esc_html( $title ); ?></h3>

                                <div class="mmm-recipe-card__meta">
									<?php if ( $meal->get_servings() ) : ?>
                                        <span class="mmm-chip"><i class="mmm-icon fa-solid fa-users" aria-hidden="true"></i><?php
											printf(
												'%d %s',
												(int) $meal->get_servings(),
												$meal->get_servings() === 1
													? esc_html__( 'Serving', 'mixmymeals' )
													: esc_html__( 'Servings', 'mixmymeals' )
											);
											?></span>
									<?php endif; ?>
									<?php if ( $prep_time_string ) : ?>
                                        <span class="mmm-chip"><i class="mmm-icon fa-solid fa-clock" aria-hidden="true"></i><?php echo esc_html( $prep_time_string ); ?></span>
									<?php endif; ?>
									<?php if ( $cost > 0 ) : ?>
                                        <span class="mmm-chip mmm-chip--price"><i class="mmm-icon fa-solid fa-tag" aria-hidden="true"></i><?php echo esc_html( number_format_i18n( $cost, 2 ) . ' ' . $currency ); ?></span>
									<?php endif; ?>
                                </div>

                                <div class="mmm-recipe-card__tags">
									<?php
									foreach ( $categories ?: [] as $cat ) {
										printf( '<span class="mmm-tag">%s</span>', esc_html( $cat->name ) );
									}
									if ( ! empty( $tags ) ) {
										usort( $tags, fn( $a, $b ) => strcasecmp( $a->name, $b->name ) );
										foreach ( $tags as $tag ) {
											printf( '<span class="mmm-tag">%s</span>', esc_html( $tag->name ) );
										}
									}
									$diet = $recipe->get_diet_type();
									if ( 'vegan' === $diet ) {
										printf( '<span class="mmm-tag">%s</span>', esc_html__( 'Vegan', 'mixmymeals' ) );
									} elseif ( 'vegetarian' === $diet ) {
										printf( '<span class="mmm-tag">%s</span>', esc_html__( 'Vegetarian', 'mixmymeals' ) );
									}
									?>
                                </div>
                            </div>
                        </a>
						<?php endforeach; ?>
					<?php endforeach; ?>
                </div>

			<?php endforeach; ?>

        </div>

		<?php
		return ob_get_clean();
	}


	/**
	 * Shortcode [mmm_shopping_list_for_a_single_meal_plan].
	 * Renders a grouped shopping list for the current or specified meal plan.
	 *
	 * @param array|string $atts Shortcode attributes: show_details, sort_by_category, meal_plan_id.
	 * @return string
	 */
	public function mmm_shopping_list_for_a_single_meal_plan_callback( $atts = []
	): string {

		$atts = shortcode_atts(
			[
				'show_details'     => true,
				'sort_by_category' => true,
				'meal_plan_id'     => null,
			],
			$atts,
			'mmm_shopping_list_for_a_single_meal_plan'
		);

		$show_details     = filter_var( $atts['show_details'],
			FILTER_VALIDATE_BOOLEAN );
		$sort_by_category = filter_var( $atts['sort_by_category'],
			FILTER_VALIDATE_BOOLEAN );

		$meal_plan_id = $atts['meal_plan_id']
			? intval( $atts['meal_plan_id'] )
			: get_the_ID();

		if ( ! $meal_plan_id ) {
			return '<p>' . esc_html__( 'No meal plan found.', 'mixmymeals' )
			       . '</p>';
		}

		$meal_plan_obj = new Mixmymeals_Meal_Plan( $meal_plan_id );
		$ingredients_by_cat
		               = $meal_plan_obj->calc_shopping_list( $sort_by_category );

		if ( empty( $ingredients_by_cat ) ) {
			return '<p>' . esc_html__( 'No ingredients found.', 'mixmymeals' )
			       . '</p>';
		}

		ob_start();
		?>

        <div class="mmm-simple-shopping-list">
			<?php foreach ( $ingredients_by_cat as $cat_name => $ings ) : ?>

                <h4 class="mmm-shopping-category"><?php echo esc_html( $cat_name ); ?></h4>
                <ul class="mmm-shopping-list-items">

					<?php foreach ( $ings as $ing_id => $data ) :

						$ingredient_obj = new Mixmymeals_Ingredient( $ing_id );

						if ( ! $ingredient_obj->is_valid() ) : ?>
                            <li class="mmm-shopping-item mmm-shopping-item--missing">
								<?php echo esc_html__( 'Ingredient no longer available.',
									'mixmymeals' ); ?>
                            </li>
							<?php
							continue;
						endif;

						$total_weight = $data['total_weight'];

						// Bedarfsanzeige: was wird gebraucht — die
						// Kaufentscheidung trifft der Nutzer im Laden.
						$ingredient_name = get_the_title( $ing_id );
						$needed_amount   = Mixmymeals_Meal_Plan::build_needed_amount_string(
							$data['units'] ?? [],
							$total_weight
						);
						$is_optional     = ! empty( $data['optional'] );

						// Anzeige: Zutat und Menge getrennt auszeichnen. "(optional)"
						// steht direkt hinter dem Zutatennamen, ohne eigenen Span.
						$item_html = '<span class="mmm-shopping-item-name">'
						             . esc_html( $ingredient_name )
						             . ( $is_optional
								? ' (' . esc_html__( 'optional', 'mixmymeals' ) . ')'
								: '' )
						             . '</span> '
						             . '<span class="mmm-shopping-item-amount">'
						             . esc_html( $needed_amount )
						             . '</span>';
						?>

                        <li class="mmm-shopping-item">
							<?php if ( $show_details ) : ?>
                                <details>
                                    <summary><?php echo $item_html; //phpcs:ignore ?></summary>
                                    <ul>
										<?php foreach (
											$data['details'] as $detail
										) : ?>
                                            <li>
												<?php
												printf(
													/* translators: 1: amount, 2: unit name, 3: recipe name. */
													esc_html__( '%1$s × %2$s for %3$s', 'mixmymeals' ),
													esc_html( (string) round( $detail['amount'], 2 ) ),
													esc_html( $detail['unit_title'] ),
													esc_html( $detail['recipe_name'] )
												);
												?>
                                            </li>
										<?php endforeach; ?>
                                    </ul>
                                </details>
							<?php else : ?>
								<?php echo $item_html; //phpcs:ignore ?>
							<?php endif; ?>
                        </li>

					<?php endforeach; ?>

                </ul>
			<?php endforeach; ?>
        </div>

		<?php
		// Structured data for plans/recipes is emitted centrally by
		// Mixmymeals_Post_Types in wp_head — a shopping list is not a Recipe,
		// so the old inline JSON-LD here was removed. The Recipe view Bring!
		// imports lives at <plan permalink>?mmm_bring=1
		// (Mixmymeals_Meal_Plan::render_bring_page()).

		return ob_get_clean();
	}

	/**
	 * Shortcode [mmm_cook_mode_button_for_a_single_meal].
	 * Renders a button that starts the fullscreen cooking mode for the
	 * scaled steps of the current meal.
	 *
	 * @return string
	 */
	public function mmm_cook_mode_button_for_a_single_meal_callback(): string {

		$post_id = get_the_ID();

		if ( get_post_type( $post_id ) !== 'mmm_meal' ) {
			return '';
		}

		$meal      = new Mixmymeals_Meal( $post_id );
		$recipe_id = $meal->get_recipe_id();

		if ( ! $recipe_id ) {
			return '';
		}

		$recipe = new Mixmymeals_Recipe( $recipe_id );

		if ( ! $recipe->is_valid() || empty( $recipe->get_steps() ) ) {
			return '';
		}

		return '<button type="button" class="mmm-cook-mode-start">'
		       . esc_html__( 'Start cooking mode', 'mixmymeals' )
		       . '</button>';
	}

	/**
	 * Formats an ingredient's needed amount for display.
	 *
	 * @deprecated Moved to Mixmymeals_Meal_Plan::build_needed_amount_string().
	 *             Kept as a thin forwarder so existing callers keep working.
	 *
	 * @param array $units        Units of one ingredient from calc_shopping_list().
	 * @param float $total_weight Total weight in grams.
	 * @return string
	 */
	public static function build_needed_amount_string( array $units, float $total_weight ): string {
		return Mixmymeals_Meal_Plan::build_needed_amount_string( $units, $total_weight );
	}

	/**
	 * Shortcode [mmm_equipment_required_for_a_single_recipe].
	 * Renders a list of equipment required for the current or specified recipe.
	 *
	 * @param array|string $atts Shortcode attributes: post_id.
	 * @return string
	 */
	public function mmm_equipment_required_for_a_single_recipe_callback(
		$atts = []
	): string {

		$atts = shortcode_atts(
			[
				'post_id' => 0,
			],
			$atts,
			'mmm_equipment_required_for_a_single_recipe'
		);

		$post_id = absint( $atts['post_id'] );
		$post_id = $post_id ? $post_id : get_the_ID();

		if ( ! $post_id ) {
			return '';
		}

		$recipe = new Mixmymeals_Recipe( $post_id );
		if ( ! $recipe->is_valid() ) {
			return '';
		}

		$equipment_ids = $recipe->get_equipment();

		if ( empty( $equipment_ids ) ) {
			return '';
		}

		return $this->render_equipment_list( $equipment_ids );
	}

	/**
	 * Shortcode [mmm_recipe_related]. Recipes this one is paired with
	 * ("Dazu passt" / goes well with). Returns '' when there are none.
	 *
	 * @param array|string $atts Shortcode attributes: post_id.
	 * @return string
	 */
	public function mmm_recipe_related_callback( $atts = [] ): string {

		$atts    = shortcode_atts( [ 'post_id' => 0 ], $atts, 'mmm_recipe_related' );
		$post_id = absint( $atts['post_id'] ) ?: (int) get_the_ID();

		if ( ! $post_id ) {
			return '';
		}

		$recipe = new Mixmymeals_Recipe( $post_id );
		if ( ! $recipe->is_valid() ) {
			return '';
		}

		return $this->render_related_recipe_cards( $recipe->get_related_recipes() );
	}

	/**
	 * Shortcode [mmm_recipe_related_reverse]. Recipes that list this one as a
	 * pairing ("Passend zu" / fits with). Returns '' when there are none.
	 *
	 * @param array|string $atts Shortcode attributes: post_id.
	 * @return string
	 */
	public function mmm_recipe_related_reverse_callback( $atts = [] ): string {

		$atts    = shortcode_atts( [ 'post_id' => 0 ], $atts, 'mmm_recipe_related_reverse' );
		$post_id = absint( $atts['post_id'] ) ?: (int) get_the_ID();

		if ( ! $post_id ) {
			return '';
		}

		$recipe = new Mixmymeals_Recipe( $post_id );
		if ( ! $recipe->is_valid() ) {
			return '';
		}

		return $this->render_related_recipe_cards( $recipe->get_reverse_related_recipes() );
	}

	/**
	 * Shortcode [mmm_meal_plans_for_a_single_recipe]. Published meal plans that
	 * contain the current (or given) recipe, rendered as archive-style plan
	 * cards.
	 *
	 * Returns '' when the recipe is in no published plan, so the calling template
	 * can drop its whole section. Pass empty_text to output a note instead — the
	 * section then stays visible and explains the gap. The note is only used for
	 * the "no plans" case; an invalid or non-recipe post still yields '', because
	 * there the shortcode is simply in the wrong place.
	 *
	 * @param array|string $atts Shortcode attributes: post_id, empty_text.
	 * @return string
	 */
	public function mmm_meal_plans_for_a_single_recipe_callback( $atts = [] ): string {

		$atts = shortcode_atts(
			[
				'post_id'    => 0,
				'empty_text' => '',
			],
			$atts,
			'mmm_meal_plans_for_a_single_recipe'
		);

		$post_id = absint( $atts['post_id'] ) ?: (int) get_the_ID();

		if ( ! $post_id || get_post_type( $post_id ) !== 'mmm_recipe' ) {
			return '';
		}

		$recipe = new Mixmymeals_Recipe( $post_id );
		if ( ! $recipe->is_valid() ) {
			return '';
		}

		$cards = $this->render_meal_plan_browse_cards( $recipe->get_meal_plans() );
		if ( '' === trim( $cards ) ) {
			$empty_text = trim( (string) $atts['empty_text'] );

			return '' !== $empty_text
				? '<p class="mmm-recipe-plans-empty">' . esc_html( $empty_text ) . '</p>'
				: '';
		}

		return '<div class="mmm-recipe-row mmm-recipe-plans">'
			. '<div class="mmm-recipe-row__track">' . $cards . '</div>'
			. '</div>';
	}

	/**
	 * Shortcode [mmm_recipe_edit_count]. Renders the bare number of edits a
	 * recipe has seen since the counter exists — no wording, no markup. The
	 * surrounding sentence belongs to the theme.
	 *
	 * Attributes:
	 *  - id          Recipe post ID; defaults to the current post.
	 *  - hide_empty  Render '' instead of "0" when nothing was edited yet (default 1).
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function mmm_recipe_edit_count_callback( $atts = array() ): string {

		$atts = shortcode_atts(
			array(
				'id'         => 0,
				'hide_empty' => '1',
			),
			$atts,
			'mmm_recipe_edit_count'
		);

		$recipe_id = (int) $atts['id'] ?: (int) get_the_ID();

		if ( ! $recipe_id || get_post_type( $recipe_id ) !== 'mmm_recipe' ) {
			return '';
		}

		$count = ( new Mixmymeals_Recipe( $recipe_id ) )->get_edit_count();

		if ( 0 === $count && filter_var( $atts['hide_empty'], FILTER_VALIDATE_BOOLEAN ) ) {
			return '';
		}

		return esc_html( number_format_i18n( $count ) );
	}

	/**
	 * Shortcode [mmm_admin_actions]. A slim admin-only panel for the CURRENT
	 * plan/recipe single page: Instagram/Pinterest posting and scheduling plus
	 * WordPress status actions (draft/publish, trash). Renders '' for
	 * everyone who cannot manage options and outside plan/recipe singles.
	 *
	 * @return string
	 */
	public function mmm_admin_actions_callback(): string {

		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}

		$id = (int) get_the_ID();
		if ( ! $id || ! in_array( get_post_type( $id ), [ 'mmm_meal_plan', 'mmm_recipe' ], true ) ) {
			return '';
		}

		// Panel script (REST-driven; nonce for cookie-authenticated REST).
		$plugin  = mixmymeals();
		$rel     = 'js/admin-board' . $plugin->script_suffix . '.js';
		$abs     = trailingslashit( $plugin->assets_dir ) . $rel;
		$version = file_exists( $abs ) ? (string) filemtime( $abs ) : $plugin->_version;

		wp_enqueue_script(
			$plugin->_token . '-admin-board',
			esc_url( $plugin->assets_url ) . $rel,
			[],
			$version,
			[ 'in_footer' => true ]
		);
		wp_localize_script(
			$plugin->_token . '-admin-board',
			'mmmBoard',
			[
				'restRoot' => esc_url_raw( rest_url( 'mixmymeals/v1/' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
			]
		);

		$post_status   = get_post_status( $id );
		$status_labels = [
			'publish' => __( 'Published', 'mixmymeals' ),
			'draft'   => __( 'Draft', 'mixmymeals' ),
			'pending' => __( 'Pending', 'mixmymeals' ),
			'future'  => __( 'Scheduled', 'mixmymeals' ),
		];

		$has_pinterest = class_exists( 'Mixmymeals_Pinterest' ) && '' !== Mixmymeals_Pinterest::get_access_token();
		$min_when      = wp_date( 'Y-m-d\TH:i', time() + 10 * MINUTE_IN_SECONDS );
		$trash_url     = admin_url( 'edit.php?post_type=' . get_post_type( $id ) );

		$services = [ 'instagram' => [ 'Instagram', Mixmymeals_Instagram::META_SCHEDULED_AT, Mixmymeals_Instagram::META_LAST_RESULT, 'permalink' ] ];
		if ( $has_pinterest ) {
			$services['pinterest'] = [ 'Pinterest', Mixmymeals_Pinterest::META_SCHEDULED_AT, Mixmymeals_Pinterest::META_LAST_RESULT, 'pin_url' ];
		}

		ob_start();
		?>
		<div class="mmm-admin-actions" data-id="<?php echo esc_attr( $id ); ?>">
			<div class="mmm-admin-actions__head">
				<span class="mmm-admin-actions__label"><?php esc_html_e( 'Admin', 'mixmymeals' ); ?></span>
				<span class="mmm-admin-board__status mmm-admin-board__status--<?php echo esc_attr( $post_status ); ?>" data-status-badge><?php echo esc_html( $status_labels[ $post_status ] ?? $post_status ); ?></span>
				<a class="mmm-admin-actions__edit" href="<?php echo esc_url( (string) get_edit_post_link( $id ) ); ?>"><?php esc_html_e( 'Edit', 'mixmymeals' ); ?></a>
			</div>

			<div class="mmm-admin-actions__row">
				<?php foreach ( $services as $service => [ $label, $meta_at, $meta_result, $url_key ] ) :
					$scheduled = (int) get_post_meta( $id, $meta_at, true );
					$last      = get_post_meta( $id, $meta_result, true );
					?>
					<div class="mmm-admin-actions__service" data-service="<?php echo esc_attr( $service ); ?>">
						<span class="mmm-admin-actions__service-name"><?php echo esc_html( $label ); ?></span>
						<?php if ( $scheduled ) : ?>
							<span class="mmm-admin-board__scheduled">⏱ <?php echo esc_html( wp_date( 'd.m. H:i', $scheduled ) ); ?></span>
							<button type="button" class="mmm-board-btn" data-action="unschedule"><?php esc_html_e( 'Cancel', 'mixmymeals' ); ?></button>
						<?php else : ?>
							<button type="button" class="mmm-board-btn mmm-board-btn--primary" data-action="publish"><?php esc_html_e( 'Post now', 'mixmymeals' ); ?></button>
							<span class="mmm-admin-board__plan">
								<input type="datetime-local" min="<?php echo esc_attr( $min_when ); ?>" />
								<button type="button" class="mmm-board-btn" data-action="schedule"><?php esc_html_e( 'Schedule', 'mixmymeals' ); ?></button>
							</span>
						<?php endif; ?>
						<?php if ( is_array( $last ) && isset( $last['ok'] ) ) : ?>
							<span class="mmm-admin-board__result mmm-admin-board__result--<?php echo $last['ok'] ? 'ok' : 'fail'; ?>" title="<?php echo esc_attr( (string) ( $last['message'] ?? '' ) ); ?>">
								<?php if ( $last['ok'] && ! empty( $last[ $url_key ] ) ) : ?>
									<a href="<?php echo esc_url( (string) $last[ $url_key ] ); ?>" target="_blank" rel="noopener">✓ <?php echo esc_html( wp_date( 'd.m.', (int) $last['time'] ) ); ?></a>
								<?php else : ?>
									<?php echo $last['ok'] ? '✓' : '✗'; ?> <?php echo esc_html( wp_date( 'd.m.', (int) $last['time'] ) ); ?>
								<?php endif; ?>
							</span>
						<?php endif; ?>
						<span class="mmm-admin-board__msg"></span>
					</div>
				<?php endforeach; ?>

				<div class="mmm-admin-actions__service mmm-admin-actions__wp">
					<span class="mmm-admin-actions__service-name">WordPress</span>
					<?php if ( 'publish' === $post_status ) : ?>
						<button type="button" class="mmm-board-btn" data-action="wp-status" data-status="draft"><?php esc_html_e( 'Set to draft', 'mixmymeals' ); ?></button>
					<?php else : ?>
						<button type="button" class="mmm-board-btn" data-action="wp-status" data-status="publish"><?php esc_html_e( 'Publish', 'mixmymeals' ); ?></button>
					<?php endif; ?>
					<button type="button" class="mmm-board-btn mmm-board-btn--danger" data-action="wp-trash" data-redirect="<?php echo esc_url( $trash_url ); ?>"><?php esc_html_e( 'Trash', 'mixmymeals' ); ?></button>
					<span class="mmm-admin-board__msg"></span>
				</div>
			</div>

			<p class="mmm-admin-board__hint"><?php esc_html_e( 'Posting generates fresh images and can take a minute for plans. Captions/titles use the defaults — for custom text use the post’s edit screen.', 'mixmymeals' ); ?></p>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Renders a grid of linked recipe cards (thumbnail + title). Only published
	 * recipes are shown. Returns '' when nothing renders.
	 *
	 * @param int[] $recipe_ids Recipe post IDs.
	 * @return string
	 */
	private function render_related_recipe_cards( array $recipe_ids ): string {

		// Only show published recipes, then reuse the exact same card renderer
		// as the recipe archive's browse rows so related recipes look identical.
		$ids = [];
		foreach ( $recipe_ids as $rid ) {
			$rid = (int) $rid;
			if ( $rid > 0 && 'publish' === get_post_status( $rid ) ) {
				$ids[] = $rid;
			}
		}

		if ( empty( $ids ) ) {
			return '';
		}

		$cards = $this->render_recipe_browse_cards( $ids );
		if ( '' === trim( $cards ) ) {
			return '';
		}

		return '<div class="mmm-recipe-row mmm-related-recipes">'
			. '<div class="mmm-recipe-row__track">' . $cards . '</div>'
			. '</div>';
	}

	/**
	 * Shortcode [mmm_equipment_required_for_a_single_meal_plan].
	 * Renders the deduplicated equipment needed across all meals of the
	 * current or specified meal plan.
	 *
	 * @param array|string $atts Shortcode attributes: post_id.
	 * @return string
	 */
	public function mmm_equipment_required_for_a_single_meal_plan_callback(
		$atts = []
	): string {

		$atts = shortcode_atts(
			[
				'post_id' => 0,
			],
			$atts,
			'mmm_equipment_required_for_a_single_meal_plan'
		);

		$post_id = absint( $atts['post_id'] );
		$post_id = $post_id ? $post_id : get_the_ID();

		if ( ! $post_id || get_post_type( $post_id ) !== 'mmm_meal_plan' ) {
			return '';
		}

		$plan          = new Mixmymeals_Meal_Plan( $post_id );
		$equipment_ids = $plan->get_required_equipment();

		if ( empty( $equipment_ids ) ) {
			return '';
		}

		return $this->render_equipment_list( $equipment_ids );
	}

	/**
	 * Builds the equipment link list for a list of equipment post IDs, styled
	 * and structured like the meal ingredients list (collapsible toggle with
	 * a count). The section heading is supplied by the surrounding template,
	 * not the list itself.
	 *
	 * @param int[] $equipment_ids Equipment post IDs.
	 * @return string
	 */
	private function render_equipment_list( array $equipment_ids ): string {

		$items = array();
		foreach ( $equipment_ids as $eq_id ) {
			$title = get_the_title( $eq_id );
			if ( ! $title ) {
				continue;
			}
			$items[] = array(
				'id'    => $eq_id,
				'title' => $title,
			);
		}

		if ( empty( $items ) ) {
			return '';
		}

		ob_start();
		?>
		<div class="mmm-equipment mmm-collapsible-list">
			<details class="mmm-collapsible-toggle">
				<summary>
					<?php
					printf(
						/* translators: %d: number of pieces of equipment */
						esc_html__( 'Show all (%d)', 'mixmymeals' ),
						count( $items )
					);
					?>
				</summary>

				<ul>
					<?php foreach ( $items as $item ) : ?>
						<li>
							<a class="mmm-equipment-name" href="<?php echo esc_url( get_permalink( $item['id'] ) ); ?>">
								<?php echo esc_html( $item['title'] ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</details>
		</div>
		<?php
		return (string) ob_get_clean();
	}


	/**
	 * Shortcode [mmm_recipe_steps_for_a_single_recipe].
	 * Renders the step-by-step cooking instructions for the current or specified recipe.
	 *
	 * When the setting "Steps on Recipe Pages" (option
	 * mixmymeals_show_recipe_steps) is switched off, the steps are replaced by
	 * the configurable note (option mixmymeals_recipe_steps_notice) — the recipe
	 * then only points at the meal pages, where
	 * [mmm_scaled_recipe_steps_for_a_single_meal] still renders them in full.
	 * Recipes without steps stay silent either way: there is nothing to point at.
	 *
	 * @param array|string $atts Shortcode attributes: post_id.
	 * @return string
	 */
	public function mmm_recipe_steps_for_a_single_recipe_callback( $atts = []
	): string {

		$atts = shortcode_atts(
			[
				'post_id' => 0,
			],
			$atts,
			'mmm_recipe_steps_for_a_single_recipe'
		);

		$post_id = absint( $atts['post_id'] );
		$post_id = $post_id ? $post_id : get_the_ID();

		if ( ! $post_id ) {
			return '<p>' . esc_html__( 'No recipe specified.', 'mixmymeals' )
			       . '</p>';
		}

		$recipe = new Mixmymeals_Recipe( $post_id );

		if ( ! $recipe->is_valid() ) {
			return '<p>' . esc_html__( 'Recipe does not exist anymore.',
					'mixmymeals' ) . '</p>';
		}

		$steps = $recipe->get_steps();

		if ( empty( $steps ) ) {
			return '<p>' . esc_html__( 'No steps defined for this recipe.',
					'mixmymeals' ) . '</p>';
		}

		if ( 'on' !== get_option( 'mixmymeals_show_recipe_steps', 'on' ) ) {
			$notice = (string) get_option( 'mixmymeals_recipe_steps_notice', '' );

			if ( '' === trim( wp_strip_all_tags( $notice ) ) ) {
				return '';
			}

			// The note is stored through wp_kses_post() by the settings
			// sanitizer, so it only needs paragraphs and shortcode expansion
			// here — a second kses pass would strip the markup the embedded
			// shortcodes produce. wpautop before do_shortcode mirrors the
			// order the_content uses. The static guard keeps a note that
			// contains this very shortcode from recursing.
			static $rendering_notice = false;

			if ( $rendering_notice ) {
				return '';
			}

			$rendering_notice = true;
			$html             = '<div class="mmm-recipe-steps-notice">'
			                    . do_shortcode( wpautop( $notice ) )
			                    . '</div>';
			$rendering_notice = false;

			return $html;
		}

		ob_start();
		?>
        <div class="mmm-recipe-steps">
			<?php foreach ( $steps as $index => $step ) : ?>
                <div class="mmm-step">
                    <div class="mmm-step-header">
						<?php if ( ! empty( $step['title'] ) ) : ?>
                            <h3 class="mmm-step-title">
								<?php
								echo esc_html(
									sprintf( __( 'Step %d', 'mixmymeals' ),
										$index + 1 ) . ': ' . $step['title']
								);
								?>
                            </h3>
						<?php endif; ?>
                    </div>

					<?php if ( ! empty( $step['image'] ) ) : ?>
                        <div class="mmm-step-image">
							<?php
							echo wp_get_attachment_image(
								$step['image'],
								'large',
								false,
								[ 'class' => 'mmm-step-img' ]
							);
							?>
                        </div>
					<?php endif; ?>

					<?php if ( ! empty( $step['description'] ) ) : ?>
                        <div class="mmm-step-description">
							<?php echo wp_kses_post( $step['description'] ); ?>
                        </div>
					<?php endif; ?>

					<?php if ( ! empty( $step['ingredients'] ) ) : ?>
                        <ul class="mmm-step-ingredients">
							<?php foreach ( $step['ingredients'] as $ing ) :

								if ( empty( $ing['id'] ) ) {
									continue;
								}

								$ingredient_obj
									= new Mixmymeals_Ingredient( $ing['id'] );

										if ( ! $ingredient_obj->is_valid() ) {
									continue;
								}

								$amount          = floatval( $ing['amount'] ??
								                             0 );
								$unit_title      = '';
								$weight_per_unit = 0.0;

								$amount_id = ! empty( $ing['amount_id'] )
									? intval( $ing['amount_id'] )
									: 0;

								$is_optional = ! empty( $ing['optional'] );

								if ( $amount_id ) {
									$amt_obj
										        = new Mixmymeals_Ingredient_Amount( $amount_id );
									$unit_title = $amt_obj->get_title() ?: '';
									$weight_per_unit
										        = floatval( $amt_obj->get_weight() );
								}

								if ( ! $amount_id ) {
									$nutrition
										= $ingredient_obj->get_nutrition();
									$weight_per_unit
										= floatval( $nutrition['weight_item'] ??
										            0 );

									if ( $weight_per_unit > 0
									     && empty( $unit_title ) ) {
										$unit_title = __( 'Piece',
											'mixmymeals' );
									}
								}

								$decimals       = ( floor( $amount )
								                    != $amount ) ? 2 : 0;
								$display_amount = $amount > 0
									? number_format_i18n( $amount, $decimals )
									: '';

								$total_weight = ( $weight_per_unit > 0
								                  && $amount > 0 )
									? (float) round( $weight_per_unit * $amount,
										2 )
									: 0.0;

								$line = '';

								if ( $display_amount ) {
									$line .= $display_amount;

									if ( $unit_title ) {
										$line .= ' ' . $unit_title;
									} else {
										$line .= ' g';
									}

									if ( $weight_per_unit > 0 ) {
										$line .= ' à '
										         . number_format_i18n( $weight_per_unit )
										         . ' g';
									}

									if ( $total_weight > 0 ) {
										$line .= ' (≙ '
										         . number_format_i18n( $total_weight )
										         . ' g)';
									}
								}
								?>
                                <li>
                                <span class="mmm-ingredient-name">
                                    <?php echo esc_html( get_the_title( $ing['id'] ) ); ?><?php if ( $is_optional ) : ?> (<?php echo esc_html__( 'optional', 'mixmymeals' ); ?>)<?php endif; ?>
                                </span>

									<?php if ( $line ) : ?>
                                        <span class="mmm-ingredient-amount">
                                        <?php echo esc_html( $line ); ?>
                                    </span>
									<?php endif; ?>
                                </li>
							<?php endforeach; ?>
                        </ul>
					<?php endif; ?>
                </div>
			<?php endforeach; ?>
        </div>
		<?php

		return ob_get_clean();
	}


	/**
	 * Shortcode [mmm_nutrition_table_for_a_single_recipe].
	 * Renders a nutrition table per serving and per 100 g for the current or specified recipe.
	 *
	 * @param array|string $atts Shortcode attributes: post_id.
	 * @return string
	 */
	public function mmm_nutrition_table_for_a_single_recipe_callback( $atts = []
	): string {

		$atts = shortcode_atts(
			[
				'post_id' => 0,
			],
			$atts,
			'mmm_nutrition_table_for_a_single_recipe'
		);

		$post_id = absint( $atts['post_id'] );
		$post_id = $post_id ? $post_id : get_the_ID();

		if ( ! $post_id ) {
			return '<p>' . esc_html__( 'No recipe specified.', 'mixmymeals' )
			       . '</p>';
		}

		if ( get_post_type( $post_id ) !== 'mmm_recipe' ) {
			return '';
		}

		$recipe = new Mixmymeals_Recipe( $post_id );

		if ( ! $recipe->is_valid() ) {
			return '<p>' . esc_html__( 'Recipe does not exist anymore.',
					'mixmymeals' ) . '</p>';
		}

		$steps = $recipe->get_steps();

		if ( empty( $steps ) ) {
			return '<p>'
			       . esc_html__( 'No steps or ingredients found for this recipe.',
					'mixmymeals' ) . '</p>';
		}

		$nutrition_per_serving = $recipe->calculate_nutrition_per_serving();
		$nutrition_per_100g    = $recipe->calculate_nutrition_per_100g();

		ob_start();
		?>
        <div class="mmm-recipe-nutrition">
            <table class="mmm-nutrition-table">
                <thead>
                <tr>
                    <th><?php esc_html_e( 'Nutrition', 'mixmymeals' ); ?></th>
                    <th>
						<?php esc_html_e( 'per serving', 'mixmymeals' ); ?>
						<?php echo ' ('
						           . esc_html( $nutrition_per_serving['weight_per_serving'] )
						           . ' g)'; ?>
                    </th>
                    <th><?php esc_html_e( 'per 100 g', 'mixmymeals' ); ?></th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td><?php esc_html_e( 'Calories', 'mixmymeals' ); ?></td>
                    <td><?php echo esc_html( round( $nutrition_per_serving['calories'],
							1 ) ); ?> kcal
                    </td>
                    <td><?php echo esc_html( round( $nutrition_per_100g['calories'],
							1 ) ); ?> kcal
                    </td>
                </tr>
                <tr>
                    <td><?php esc_html_e( 'Protein', 'mixmymeals' ); ?></td>
                    <td><?php echo esc_html( round( $nutrition_per_serving['protein'],
							1 ) ); ?> g
                    </td>
                    <td><?php echo esc_html( round( $nutrition_per_100g['protein'],
							1 ) ); ?> g
                    </td>
                </tr>
                <tr>
                    <td><?php esc_html_e( 'Carbohydrates',
							'mixmymeals' ); ?></td>
                    <td><?php echo esc_html( round( $nutrition_per_serving['carbs'],
							1 ) ); ?> g
                    </td>
                    <td><?php echo esc_html( round( $nutrition_per_100g['carbs'],
							1 ) ); ?> g
                    </td>
                </tr>
                <tr>
                    <td><?php esc_html_e( 'Fat', 'mixmymeals' ); ?></td>
                    <td><?php echo esc_html( round( $nutrition_per_serving['fat'],
							1 ) ); ?> g
                    </td>
                    <td><?php echo esc_html( round( $nutrition_per_100g['fat'],
							1 ) ); ?> g
                    </td>
                </tr>
                <tr>
                    <td><?php esc_html_e( 'Sugar', 'mixmymeals' ); ?></td>
                    <td><?php echo esc_html( round( $nutrition_per_serving['sugar'],
							1 ) ); ?> g
                    </td>
                    <td><?php echo esc_html( round( $nutrition_per_100g['sugar'],
							1 ) ); ?> g
                    </td>
                </tr>
                <tr>
                    <td><?php esc_html_e( 'Fiber', 'mixmymeals' ); ?></td>
                    <td><?php echo esc_html( round( $nutrition_per_serving['fiber'],
							1 ) ); ?> g
                    </td>
                    <td><?php echo esc_html( round( $nutrition_per_100g['fiber'],
							1 ) ); ?> g
                    </td>
                </tr>
                <tr>
                    <td><?php esc_html_e( 'Salt', 'mixmymeals' ); ?></td>
                    <td><?php echo esc_html( round( $nutrition_per_serving['salt'],
							2 ) ); ?> g
                    </td>
                    <td><?php echo esc_html( round( $nutrition_per_100g['salt'],
							2 ) ); ?> g
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
		<?php

		return ob_get_clean();
	}



	/**
	 * Builds the "Add to Planner" control for a recipe — the single source of
	 * this button's markup. Everything that opens the planner dialog carries the
	 * class mmm-recipe-actions__item--cart plus data-recipe-id/data-servings;
	 * one delegated click handler in frontend.js binds to exactly that.
	 *
	 * The element and the extra classes are configurable because the button
	 * appears in two shapes: a slim <span> on recipe cards and a full <button>
	 * in the recipe hero.
	 *
	 * @param int   $recipe_id Recipe post ID.
	 * @param array $args      element (span|button), class, text, servings.
	 * @return string Empty string when the ID is not a recipe.
	 */
	private function render_add_to_planner_button( int $recipe_id, array $args = array() ): string {

		if ( $recipe_id <= 0 || get_post_type( $recipe_id ) !== 'mmm_recipe' ) {
			return '';
		}

		$element = ( isset( $args['element'] ) && 'button' === $args['element'] ) ? 'button' : 'span';

		$defaults = array(
			// Card default keeps the generic action-item look; the hero passes
			// its own button classes.
			'class'    => 'button' === $element ? 'mmm-btn mmm-btn--primary' : 'mmm-recipe-actions__item',
			'text'     => __( 'Add to Planner', 'mixmymeals' ),
			'servings' => 0,
		);

		$args = array_merge( $defaults, $args );

		// Servings default to the recipe's own; never below one, so the dialog
		// always opens on a usable number.
		$servings = (int) $args['servings'];
		if ( $servings <= 0 ) {
			$servings = (int) ( new Mixmymeals_Recipe( $recipe_id ) )->get_servings();
		}
		$servings = max( 1, $servings );

		$classes = trim( (string) $args['class'] . ' mmm-recipe-actions__item--cart' );

		return sprintf(
			'<%1$s%2$s class="%3$s" data-recipe-id="%4$d" data-servings="%5$d">%6$s</%1$s>',
			$element,
			'button' === $element ? ' type="button"' : '',
			esc_attr( $classes ),
			$recipe_id,
			$servings,
			esc_html( (string) $args['text'] )
		);
	}

	/**
	 * Shortcode [mmm_add_to_planner]. Renders the "Add to Planner" button on its
	 * own, so it can be placed anywhere — including outside a recipe post, by
	 * passing the recipe id.
	 *
	 * Attributes:
	 *  - id        Recipe post ID; defaults to the current post.
	 *  - servings  Preselected servings; defaults to the recipe's own.
	 *  - text      Button label.
	 *  - element   span (default) or button.
	 *  - class     Extra CSS classes; defaults per element.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function mmm_add_to_planner_callback( $atts = array() ): string {

		$atts = shortcode_atts(
			array(
				'id'       => 0,
				'servings' => 0,
				'text'     => '',
				'element'  => 'span',
				'class'    => '',
			),
			$atts,
			'mmm_add_to_planner'
		);

		$recipe_id = (int) $atts['id'] ?: (int) get_the_ID();

		$args = array(
			'element'  => $atts['element'],
			'servings' => (int) $atts['servings'],
		);

		// Only override the defaults when something was actually passed, so an
		// omitted attribute still yields the per-element default.
		if ( '' !== $atts['text'] ) {
			$args['text'] = $atts['text'];
		}
		if ( '' !== $atts['class'] ) {
			$args['class'] = $atts['class'];
		}

		return $this->render_add_to_planner_button( $recipe_id, $args );
	}

	/**
	 * Shortcode [mmm_recipe_actions].
	 * Renders action links (e.g. add-to-cart) for the current recipe.
	 *
	 * @return string
	 */
	public function mmm_recipe_actions_callback(): string {

		$button = $this->render_add_to_planner_button( (int) get_the_ID() );

		if ( '' === $button ) {
			return '';
		}

		return '<div class="mmm-recipe-actions">' . $button . '</div>';
	}

	/**
	 * Shortcode: [mmm_recipe_meta]
	 * Zeigt Meta-Informationen für ein einzelnes Rezept an (Calories, Prep Time, Servings, Categories).
	 */
	public function mmm_recipe_meta_callback( $atts = [] ): string {

		$html = '';

		$post_id = get_the_ID();

		$post_type = get_post_type( $post_id );
		if ( $post_type !== 'mmm_recipe' ) {
			return '';
		}

		if ( ! class_exists( 'Mixmymeals_Recipe' )
		     || ! class_exists( 'Mixmymeals_Meal' ) ) {
			return '';
		}

		// Holen des Meal-Objekts für Servings
		//$meal = new Mixmymeals_Meal( $post_id ); // falls Meal ID = Recipe ID
		//$servings = $meal->get_servings();

		$recipe       = new Mixmymeals_Recipe( $post_id );
		$nutrition    = $recipe->calculate_nutrition_per_serving();
		$prep_minutes = $recipe->get_preparation_time();
		$servings     = $recipe->get_servings();

		$items = [];

		// -------------------------
		// Servings
		// -------------------------
		if ( $servings ) {
			$items[] = '<span class="mmm-meta__item mmm-meta__item--servings">'
			           . intval( $servings ) . ' '
			           . esc_html( $servings === 1 ? __( 'Serving',
					'mixmymeals' ) : __( 'Servings', 'mixmymeals' ) )
			           . '</span>';
		}

		// -------------------------
		// Calories
		// -------------------------
		if ( ! empty( $nutrition['calories'] ) ) {
			$items[] = '<span class="mmm-meta__item mmm-meta__item--calories">'
			           . esc_html( round( $nutrition['calories'] ) )
			           . ' kcal</span>';
		}

		// -------------------------
		// Preparation Time
		// -------------------------
		if ( $prep_minutes > 0 ) {
			if ( $prep_minutes < 60 ) {
				$prep_string = sprintf( __( '%s min', 'mixmymeals' ),
					number_format_i18n( $prep_minutes ) );
			} else {
				$hours       = floor( $prep_minutes / 60 );
				$minutes     = $prep_minutes % 60;
				$prep_string = sprintf( __( '%s h', 'mixmymeals' ),
					number_format_i18n( $hours ) );
				if ( $minutes > 0 ) {
					$prep_string .= ' ' . sprintf( __( '%s min', 'mixmymeals' ),
							number_format_i18n( $minutes ) );
				}
			}
			$items[] = '<span class="mmm-meta__item mmm-meta__item--prep-time">'
			           . esc_html( $prep_string )
			           . '</span>';
		}

		// -------------------------
		// Cost per Serving
		// -------------------------
		$cost = $recipe->calculate_cost_per_serving();
		if ( $cost > 0 ) {
			$currency = get_option( 'mixmymeals_currency', '€' );
			$items[]  = '<span class="mmm-meta__item mmm-meta__item--cost">'
			            . esc_html( number_format_i18n( $cost, 2 ) . ' ' . $currency )
			            . '</span>';
		}

		// -------------------------
		// Recipe Categories
		// -------------------------
		$terms = get_the_terms( $post_id, 'mmm_recipe_category' );
		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			usort( $terms, fn( $a, $b ) => strcasecmp( $a->name, $b->name ) );
			$found_diet = '';
			$diet_label = '';
			foreach ( $terms as $term ) {
				$name = strtolower( $term->name );
				if ( $name === 'vegan' ) {
					$found_diet = 'vegan';
					$diet_label = $term->name;
				} elseif ( in_array( $name, [ 'vegetarisch', 'vegetarian' ], true ) && $found_diet !== 'vegan' ) {
					$found_diet = 'vegetarian';
					$diet_label = $term->name;
				} else {
					$items[] = '<span class="mmm-meta__item mmm-meta__item--category">'
					           . esc_html( $term->name )
					           . '</span>';
				}
			}
			if ( $found_diet !== '' ) {
				$items[] = '<span class="mmm-meta__item mmm-meta__item--diet">'
				           . esc_html( $diet_label ) . '</span>';
			}
		}

		// -------------------------
		// Recipe Tags (mmm_recipe_tag)
		// -------------------------
		$tag_terms = get_the_terms( $post_id, 'mmm_recipe_tag' );
		if ( ! empty( $tag_terms ) && ! is_wp_error( $tag_terms ) ) {
			usort( $tag_terms,
				fn( $a, $b ) => strcasecmp( $a->name, $b->name ) );

			foreach ( $tag_terms as $term ) {
				$items[] = '<span class="mmm-meta__item mmm-meta__item--tag">'
				           . esc_html( $term->name )
				           . '</span>';
			}
		}

		// -------------------------
		// Diet Type Badge
		// -------------------------
		$diet = $recipe->get_diet_type();
		if ( $diet === 'vegan' ) {
			$items[] = '<span class="mmm-meta__item mmm-meta__item--diet">'
			           . esc_html__( 'Vegan', 'mixmymeals' ) . '</span>';
		} elseif ( $diet === 'vegetarian' ) {
			$items[] = '<span class="mmm-meta__item mmm-meta__item--diet">'
			           . esc_html__( 'Vegetarian', 'mixmymeals' ) . '</span>';
		}

		if ( empty( $items ) ) {
			return '';
		}

		$html .= '<div class="mmm-meal-meta">' . implode( '', $items )
		         . '</div>';

		return $html;
	}

	/**
	 * Shows a badge with the number of recipes currently in the cart.
	 */
	/**
	 * Shortcode: [mmm_cart_badge]
	 *
	 * Outputs an AJAX-updating badge.
	 */
	public function mmm_cart_badge_callback( $atts ): string {

		$atts = shortcode_atts(
			array(
				'label_singular' => __( '', 'mixmymeals' ),
				'label_plural'   => __( '',
					'mixmymeals' ),
				'class'          => 'mmm-cart-badge',
			),
			$atts
		);

		// HTML-Gerüst, JS füllt später die Werte ein
		return sprintf(
			'<span class="%s" data-label-singular="%s" data-label-plural="%s">…</span>',
			esc_attr( $atts['class'] ),
			esc_attr( $atts['label_singular'] ),
			esc_attr( $atts['label_plural'] )
		);
	}


	/**
	 * Shortcode: [mmm_meal_plan_builder]
	 *
	 * Outputs an interactive meal plan builder UI.
	 */
	public function mmm_meal_plan_builder_callback(): string {


		$cart = new Mixmymeals_Cart();

		$items = $cart->get_items();

		// Liste der Tage (Mo–So)
		$days = array(
			1 => __( 'Monday', 'mixmymeals' ),
			2 => __( 'Tuesday', 'mixmymeals' ),
			3 => __( 'Wednesday', 'mixmymeals' ),
			4 => __( 'Thursday', 'mixmymeals' ),
			5 => __( 'Friday', 'mixmymeals' ),
			6 => __( 'Saturday', 'mixmymeals' ),
			7 => __( 'Sunday', 'mixmymeals' ),
		);

		// Meal Types
		$types = array(
			'breakfast' => __( 'Breakfast', 'mixmymeals' ),
			'lunch'     => __( 'Lunch', 'mixmymeals' ),
			'dinner'    => __( 'Dinner', 'mixmymeals' ),
			'snack'     => __( 'Snack', 'mixmymeals' ),
		);

		// Welche Tage schon belegt sind — nur für den Anfangszustand des
		// Ablage-Hinweises; ab dann übernimmt CSS (:has), damit Drag & Drop den
		// Hinweis ohne Reload richtig ein- und ausblendet.
		$days_with_items = array();
		foreach ( $items as $item ) {
			$days_with_items[ intval( $item['day'] ?? 0 ) ] = true;
		}

		ob_start();
		?>

        <div class="mmm-plan-builder" data-kcal-unit="<?php echo esc_attr__( 'kcal ps', 'mixmymeals' ); ?>">

            <!-- Unassigned Items -->
            <div class="mmm-plan-unassigned">
                <h3><?php echo esc_html__( 'Unassigned Recipes',
						'mixmymeals' ); ?></h3>

                <ul class="mmm-plan-day-list" data-day-list="0">

					<?php foreach ( $items as $index => $item ) :

						if ( ! empty( $item['day'] ) ) {
							continue;
						}

						$recipe_id = intval( $item['recipe_id'] );
						$title     = get_the_title( $recipe_id );
						$thumb     = get_the_post_thumbnail_url( $recipe_id,
							'thumbnail' );
						$type      = $item['type'] ?? '';
						$servings  = $item['servings'] ?? 2;
						?>

                        <li class="mmm-plan-item"
                            data-index="<?php echo esc_attr( $index ); ?>"
                            data-recipe-id="<?php echo esc_attr( $recipe_id ); ?>"
                            data-kcal="<?php echo esc_attr( $this->recipe_kcal_per_serving( $recipe_id ) ); ?>"
                            draggable="true">

                            <div class="mmm-plan-item-drag">☰</div>

							<?php if ( $thumb ) : ?>
                                <img class="mmm-plan-item-thumb"
                                     src="<?php echo esc_url( $thumb ); ?>"
                                     alt="">
							<?php endif; ?>

                            <div class="mmm-plan-item-content">

                                <div class="mmm-plan-item-title">
									<?php echo esc_html( $title ); ?>
                                </div>

                                <div class="mmm-plan-item-controls">

                                    <select class="mmm-plan-item-type"
                                            data-field="type">
										<?php foreach (
											$types as $key => $label
										) : ?>
                                            <option value="<?php echo esc_attr( $key ); ?>"
												<?php selected( $type,
													$key ); ?>>
												<?php echo esc_html( $label ); ?>
                                            </option>
										<?php endforeach; ?>
                                    </select>

                                    <input type="number"
                                           class="mmm-plan-item-servings"
                                           data-field="servings"
                                           min="1"
                                           value="<?php echo esc_attr( $servings ); ?>">
                                </div>

                            </div>

                            <button class="mmm-plan-item-remove"
                                    aria-label="<?php esc_attr_e( 'Remove item', 'mixmymeals' ); ?>"
                                    data-action="remove">×
                            </button>

                        </li>

					<?php endforeach; ?>

					<?php
					/*
					 * Leerer Planer: Einstieg ins Rezeptarchiv anbieten statt
					 * einer leeren Ablagefläche. Der Anfangszustand kommt aus
					 * PHP, das spätere Ein- und Ausblenden aus CSS (:has) —
					 * sonst bliebe der Hinweis stehen, wenn per Drag & Drop
					 * oder über das × etwas verändert wird.
					 */
					$recipe_archive = get_post_type_archive_link( 'mmm_recipe' );

					if ( $recipe_archive ) :
						?>
                        <li class="mmm-plan-empty"<?php echo empty( $items ) ? '' : ' hidden'; ?>>
							<?php esc_html_e( 'Your planner is still empty.', 'mixmymeals' ); ?>
                            <a class="mmm-plan-empty__link"
                               href="<?php echo esc_url( $recipe_archive ); ?>">
								<?php esc_html_e( 'Find recipes', 'mixmymeals' ); ?>
                            </a>
                        </li>
					<?php endif; ?>

                </ul>
            </div>

            <div class="mmm-plan-days">

				<?php foreach ( $days as $day_key => $day_label ) : ?>

                    <div class="mmm-plan-day"
                         data-day="<?php echo esc_attr( $day_key ); ?>">

                        <h3 class="mmm-plan-day-title">
							<?php echo esc_html( $day_label ); ?>
                        </h3>

                        <label class="mmm-plan-day-start">
                            <input type="radio" name="mmm-plan-startday" value="<?php echo esc_attr( $day_key ); ?>">
                            <span><?php esc_html_e( 'Start day', 'mixmymeals' ); ?></span>
                        </label>

                        <ul class="mmm-plan-day-list"
                            data-day-list="<?php echo esc_attr( $day_key ); ?>">

							<?php foreach ( $items as $index => $item ) :

								if ( intval( $item['day'] ) !== $day_key ) {
									continue;
								}

								$recipe_id = intval( $item['recipe_id'] );
								$title     = get_the_title( $recipe_id );
								$thumb
								           = get_the_post_thumbnail_url( $recipe_id,
									'thumbnail' );
								$type      = $item['type'] ?? '';
								$servings  = $item['servings'] ?? 2;
								?>

                                <li class="mmm-plan-item"
                                    data-index="<?php echo esc_attr( $index ); ?>"
                                    data-recipe-id="<?php echo esc_attr( $recipe_id ); ?>"
                                    data-kcal="<?php echo esc_attr( $this->recipe_kcal_per_serving( $recipe_id ) ); ?>"
                                    draggable="true">

                                    <div class="mmm-plan-item-drag">☰</div>

									<?php if ( $thumb ) : ?>
                                        <img class="mmm-plan-item-thumb"
                                             src="<?php echo esc_url( $thumb ); ?>"
                                             alt="">
									<?php endif; ?>

                                    <div class="mmm-plan-item-content">

                                        <div class="mmm-plan-item-title">
											<?php echo esc_html( $title ); ?>
                                        </div>

                                        <div class="mmm-plan-item-controls">

                                            <!-- Meal Type -->
                                            <select class="mmm-plan-item-type"
                                                    data-field="type">
												<?php foreach (
													$types as $key => $label
												) : ?>
                                                    <option value="<?php echo esc_attr( $key ); ?>"
														<?php selected( $type,
															$key ); ?>>
														<?php echo esc_html( $label ); ?>
                                                    </option>
												<?php endforeach; ?>
                                            </select>

                                            <!-- Servings -->
                                            <input type="number"
                                                   class="mmm-plan-item-servings"
                                                   data-field="servings"
                                                   min="1"
                                                   value="<?php echo esc_attr( $servings ); ?>">
                                        </div>

                                    </div>

                                    <button class="mmm-plan-item-remove"
                                            aria-label="<?php esc_attr_e( 'Remove item', 'mixmymeals' ); ?>"
                                            data-action="remove">
                                        ×
                                    </button>

                                </li>

							<?php endforeach; ?>

                            <?php
                            /*
                             * Ablage-Hinweis für diesen Tag. Steht immer im
                             * Markup; sichtbar ist er nur, solange der Tag leer
                             * ist. Anfangszustand aus PHP, danach CSS (:has) —
                             * sonst bliebe er nach Drag & Drop oder × stehen.
                             */
                            ?>
                            <li class="mmm-plan-day-hint"<?php echo empty( $days_with_items[ $day_key ] ) ? '' : ' hidden'; ?>>
                                <?php esc_html_e( 'Drag a recipe here to plan it for this day.', 'mixmymeals' ); ?>
                            </li>

                        </ul>

                        <div class="mmm-plan-day-total" hidden>
                            <span class="mmm-plan-day-total__label"><?php esc_html_e( 'Day total', 'mixmymeals' ); ?></span>
                            <span class="mmm-plan-day-total__value" data-day-total></span>
                        </div>

                    </div>

				<?php endforeach; ?>

            </div>

        </div>

		<?php

		return ob_get_clean();
	}


	/**
	 * Shortcode [mmm_clear_cart].
	 * Renders a link that clears the cart via AJAX when clicked.
	 *
	 * @param array|string $atts Shortcode attributes: text.
	 * @return string
	 */
	public function mmm_clear_cart_callback( $atts ): string {

		$atts = shortcode_atts( array(
			'text' => __( 'Clear Plan', 'mixmymeals' )
		), $atts, 'mmm_clear_cart' );

		ob_start();
		?>
        <a href="#"
           class="mmm-clear-cart-link"><?php echo esc_html( $atts['text'] ); ?></a>
        <span class="mmm-clear-cart-message" style="margin-left:10px;"></span>
		<?php
		return ob_get_clean();
	}


	/**
	 * Shortcode [mmm_create_meal_plan_from_cart].
	 * Renders a button that triggers meal plan creation from the current cart.
	 *
	 * @param array|string $atts Shortcode attributes: text, guest_text.
	 * @return string
	 */
	public function mmm_create_meal_plan_from_cart_callback( $atts ): string {
		$atts = shortcode_atts( [
			'text'       => __( 'Create Meal Plan', 'mixmymeals' ),
			'guest_text' => __( 'Please log in to create a meal plan.',
				'mixmymeals' ),
		], $atts, 'mmm_create_meal_plan' );

		ob_start();

		$public_mode = (string) get_option( 'mixmymeals_public_mode', '' )
		               === 'on';

		if ( $public_mode || is_user_logged_in() ) {
			?>
            <a href="#"
               class="mmm-create-meal-plan-link"><?php echo esc_html( $atts['text'] ); ?></a>
            <span class="mmm-create-meal-plan-message"
                  style="margin-left:10px;"></span>
			<?php
			echo self::input_notice( 'plan' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
		} else {
			echo '<span class="mmm-create-meal-plan-guest">'
			     . esc_html( $atts['guest_text'] ) . '</span>';
		}

		return ob_get_clean();
	}

	/**
	 * AJAX handler for wp_ajax_mmm_create_meal_plan.
	 * Creates a new meal plan post from the current cart contents.
	 *
	 * @return void
	 */
	public function mmm_create_meal_plan_ajax_callback(): void {

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'mmm_frontend_nonce' ) ) {
			wp_send_json_error(
				[
					'code'    => 'session_expired',
					'message' => __( 'Your session has expired. Please reload the page.', 'mixmymeals' ),
				],
				403
			);
		}

		$public_mode = (string) get_option( 'mixmymeals_public_mode', '' )
		               === 'on';

		if ( ! $public_mode && ! is_user_logged_in() ) {
			wp_send_json_error(
				[
					'code'    => 'login_required',
					'message' => __( 'Please log in to create a meal plan.',
						'mixmymeals' ),
				],
				403
			);
		}

		// Quota to limit meal plan posting per IP for non-logged-in visitors:
		$is_guest = ! is_user_logged_in();
		if ( $public_mode && $is_guest ) {

			$ip     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
			$ip_key = 'mmm_rl_create_plan_ip_' . md5( $ip );

			$limit
				    = (int) get_option( 'mixmymeals_published_meal_plans_per_hour_limit',
				0 );
			$window = HOUR_IN_SECONDS;

			if ( $limit > 0 ) {
				$count = (int) get_transient( $ip_key );

				if ( $count >= $limit ) {
					wp_send_json_error(
						[
							'code'    => 'rate_limit',
							'message' => __( 'Too many plans created. Please try again later.',
								'mixmymeals' ),
							'limit'   => $limit,
						],
						429
					);
				}

				set_transient( $ip_key, $count + 1, $window );
			}
		}

		$items = $_POST['items'] ?? [];

		if ( empty( $items ) || ! is_array( $items ) ) {
			wp_send_json_error(
				[
					'code'    => 'no_items',
					'message' => __( 'There are no items to create a meal plan from.',
						'mixmymeals' ),
				],
				400
			);
		}

		// Max-Items hart erzwingen (auch wenn jemand POST fälscht)
		$max = (int) get_option( 'mixmymeals_max_items_in_cart', 0 );
		if ( $max > 0 && count( $items ) > $max ) {
			wp_send_json_error(
				[
					'code'    => 'max_items',
					'message' => sprintf( __( 'Maximum %d recipes per plan.',
						'mixmymeals' ), $max ),
					'max'     => $max,
					'count'   => count( $items ),
				],
				400
			);
		}

		// Strikt säubern/validieren
		$clean_items = [];

		foreach ( $items as $item ) {

			$recipe_id = isset( $item['recipe_id'] ) ? (int) $item['recipe_id']
				: 0;
			if ( $recipe_id <= 0 ) {
				continue;
			}

			// Rezept muss existieren und richtiger Post-Type
			if ( get_post_type( $recipe_id ) !== 'mmm_recipe' ) {
				continue;
			}

			$day = isset( $item['day'] ) ? (int) $item['day'] : null;
			if ( $day !== null && ( $day < 1 || $day > 7 ) ) {
				$day = null;
			}

			$clean_items[] = [
				'recipe_id' => $recipe_id,
				'servings'  => max( 1, (int) ( $item['servings'] ?? 1 ) ),
				'type'      => sanitize_text_field( $item['type'] ?? '' ),
				//'note'      => sanitize_textarea_field( $item['note'] ?? '' ),
				'day'       => $day,
			];
		}

		if ( empty( $clean_items ) ) {
			wp_send_json_error(
				[
					'code'    => 'no_valid_items',
					'message' => __( 'No valid items.', 'mixmymeals' ),
				],
				400
			);
		}

		// ab hier nur noch $clean_items verwenden
		$items = $clean_items;

		// Start- und End-Tag berechnen
		$days            = array_filter( array_column( $items, 'day' ) );
		$start_day_param = isset( $_POST['start_day'] ) ? (int) $_POST['start_day'] : 0;

		if ( $start_day_param >= 1 && $start_day_param <= 7 ) {
			// User-chosen anchor; end day = furthest used day going forward
			// (wraps over Sunday).
			$start_day  = $start_day_param;
			$end_day    = $start_day;
			$max_offset = - 1;
			foreach ( $days as $d ) {
				$offset = ( $d - $start_day + 7 ) % 7;
				if ( $offset > $max_offset ) {
					$max_offset = $offset;
					$end_day    = $d;
				}
			}
		} else {
			// "Automatic": keep the previous behaviour.
			$start_day = $days ? min( $days ) : 1;
			$end_day   = $days ? max( $days ) : 1;
		}

		$save_visitor_plans_as_draft
			         = (string) get_option( 'mixmymeals_save_meal_plans_published_by_visitors_as_draft',
				'' ) === 'on';
		$post_status = 'publish';

		if ( ! is_user_logged_in() && $save_visitor_plans_as_draft ) {
			$post_status = 'draft';
		}

		try {
			$meal_plan = Mixmymeals_Meal_Plan::create(
				__( 'Meal Plan', 'mixmymeals' ) . ' ' . wp_date( 'd.m.Y' ),
				$post_status
			);
		} catch ( \Exception $e ) {
			wp_send_json_error(
				[
					'code'    => 'create_failed',
					'message' => __( 'Could not create meal plan.', 'mixmymeals' ),
				],
				500
			);
		}

		$meal_plan_id = $meal_plan->get_id();
		$meal_plan->set_start_day( $start_day );
		$meal_plan->set_end_day( $end_day );

		// Meals anlegen
		foreach ( $items as $item ) {

			if ( empty( $item['recipe_id'] ) ) {
				continue;
			}

			try {
				$meal = Mixmymeals_Meal::create(
					get_the_title( $item['recipe_id'] ),
					$meal_plan_id
				);
			} catch ( \Exception $e ) {
				continue;
			}

			$meal->set_recipe_id( $item['recipe_id'] );
			$meal->set_servings( ! empty( $item['servings'] )
				? (int) $item['servings'] : 1 );
			$meal->set_type( $item['type'] ?? '' );
			if ( isset( $item['day'] ) ) {
				$meal->set_day( (int) $item['day'] );
			}
		}

		// Find out where to redirect now:
		$redirect_url = get_permalink( $meal_plan_id );
		if ( $post_status === 'draft' ) {
			$page_id
				          = (int) get_option( 'mixmymeals_page_id_to_redirect_if_new_plan_is_draft',
				0 );
			$redirect_url = $page_id > 0 ? get_permalink( $page_id )
				: home_url( '/' );
		}

		// Plan created successfully — empty the cart so it doesn't carry over.
		( new Mixmymeals_Cart() )->clear();

		// Respond:
		wp_send_json_success( [
			'code'         => 'created',
			'message'      => __( 'Meal Plan created!', 'mixmymeals' ),
			'meal_plan_id' => $meal_plan_id,
			'redirect_url' => $redirect_url,
			'post_status'  => $post_status,
		] );
	}


	/**
	 * Shortcode [mmm_meal_ingredients].
	 * Renders a scaled ingredient list for the specified meal.
	 *
	 * @param array|string $atts Shortcode attributes: meal_id.
	 * @return string
	 */
	public function mmm_meal_ingredients_callback( $atts ): string {

		$atts = shortcode_atts(
			array(
				'meal_id' => 0,
			),
			$atts
		);

		$meal_id = intval( $atts['meal_id'] );

		if ( ! $meal_id ) {
			$meal_id = get_the_ID();
		}

		if ( ! $meal_id || get_post_type( $meal_id ) !== 'mmm_meal' ) {
			return '';
		}

		$meal = new Mixmymeals_Meal( $meal_id );

		$recipe_id = $meal->get_recipe_id();
		if ( ! $recipe_id ) {
			return '';
		}

		$recipe = new Mixmymeals_Recipe( $recipe_id );

		$meal_servings   = max( 1.0, (float) $meal->get_servings() );
		$recipe_servings = max( 1.0, (float) $recipe->get_servings() );
		$factor          = $meal_servings / $recipe_servings;

		$ingredients_raw = $recipe->get_ingredients_scaled( $factor );

		return $this->render_ingredients_list( $ingredients_raw );
	}

	/**
	 * Shortcode [mmm_recipe_ingredients].
	 * Renders the ingredient list for the specified (or current) recipe, at
	 * the recipe's own stored servings — the recipe-page equivalent of
	 * [mmm_meal_ingredients].
	 *
	 * @param array|string $atts Shortcode attributes: post_id.
	 * @return string
	 */
	public function mmm_recipe_ingredients_callback( $atts = [] ): string {

		$atts = shortcode_atts(
			[
				'post_id' => 0,
			],
			$atts,
			'mmm_recipe_ingredients'
		);

		$post_id = absint( $atts['post_id'] );
		$post_id = $post_id ? $post_id : get_the_ID();

		if ( ! $post_id || get_post_type( $post_id ) !== 'mmm_recipe' ) {
			return '';
		}

		$recipe = new Mixmymeals_Recipe( $post_id );
		if ( ! $recipe->is_valid() ) {
			return '';
		}

		$ingredients_raw = $recipe->get_ingredients_scaled( 1.0 );

		return $this->render_ingredients_list( $ingredients_raw );
	}

	/**
	 * Aggregates raw (already scaled) step ingredients by ingredient ID and
	 * renders them as the shared collapsible ingredients list used on both
	 * the meal and recipe pages.
	 *
	 * @param array $ingredients_raw Entries from Mixmymeals_Recipe::get_ingredients_scaled().
	 * @return string
	 */
	private function render_ingredients_list( array $ingredients_raw ): string {

		if ( empty( $ingredients_raw ) ) {
			return '';
		}

		$ingredients = [];

		foreach ( $ingredients_raw as $ing ) {

			$id = intval( $ing['id'] ?? 0 );

			if ( $id <= 0 ) {
				continue;
			}

			$ingredient_obj = new Mixmymeals_Ingredient( $id );
			if ( ! $ingredient_obj->is_valid() ) {
				continue;
			}

			if ( ! isset( $ingredients[ $id ] ) ) {
				$ingredients[ $id ] = [
					'id'       => $id,
					'name'     => $ing['name'] ?? get_the_title( $id ),
					'unit'     => $ing['unit'] ?? '',
					'amount'   => 0.0,
					'weight_g' => 0.0,
					// Optional only while every use is optional.
					'optional' => true,
				];
			}

			// Aggregation (skalierte Werte direkt verwenden)
			$ingredients[ $id ]['amount']   += (float) ( $ing['amount'] ?? 0 );
			$ingredients[ $id ]['weight_g'] += (float) ( $ing['weight_g'] ?? 0 );
			$ingredients[ $id ]['optional']  = $ingredients[ $id ]['optional'] && ! empty( $ing['optional'] );
		}

		if ( empty( $ingredients ) ) {
			return '';
		}

		ob_start();
		?>
        <div class="mmm-ingredients-list mmm-collapsible-list">
            <details class="mmm-collapsible-toggle">
                <summary>
					<?php
					printf(
						/* translators: %d: number of ingredients */
						esc_html__( 'Show all (%d)', 'mixmymeals' ),
						count( $ingredients )
					);
					?>
                </summary>

                <ul>
					<?php foreach ( $ingredients as $ingredient ) : ?>
                        <li>
						<span class="mmm-ingredient-name">
							<?php echo esc_html( $ingredient['name'] ); ?><?php if ( ! empty( $ingredient['optional'] ) ) : ?> (<?php echo esc_html__( 'optional', 'mixmymeals' ); ?>)<?php endif; ?>
						</span>

							<?php if ( ! empty( $ingredient['amount'] ) ) :

								$amount     = (float) $ingredient['amount'];
								$unit       = $ingredient['unit'] ?: '';
								$weight_g   = round( (float) $ingredient['weight_g'] );

								$amount_display = rtrim(
									rtrim(
										number_format( $amount, 2, ',', '.' ),
										'0'
									),
									','
								);
								?>
                                <span class="mmm-ingredient-amount">
								<?php echo esc_html( $amount_display ); ?>
									<?php if ( $unit ) echo ' ' . esc_html( $unit ); ?>
								(<?php echo esc_html( $weight_g ); ?> g)
							</span>
							<?php endif; ?>
                        </li>
					<?php endforeach; ?>
                </ul>
            </details>
        </div>
		<?php

		return (string) ob_get_clean();
	}


	/**
	 * Shortcode [mmm_meal_plan_actions].
	 * Renders an action bar (PDF download, load into cart) for the current meal plan.
	 *
	 * @param array|string $atts Shortcode attributes: cart_url.
	 * @return string
	 */
	public function mmm_meal_plan_actions_callback( $atts = [] ): string {

		$atts = shortcode_atts( array( 'cart_url' => '' ), $atts );

		$post_type = get_query_var( 'post_type' );
		$post_type = is_array( $post_type ) ? reset( $post_type ) : $post_type;

		if ( $post_type !== 'mmm_meal_plan' ) {
			return '';
		}

		$meal_plan_id = get_the_ID();

		if ( ! $meal_plan_id || ! is_numeric( $meal_plan_id ) ) {
			return '';
		}

		$title = get_the_title( $meal_plan_id );
		$url   = get_permalink( $meal_plan_id );

		// Mailto vorbereiten
		$mail_subject = sprintf( __( 'Meal Plan: %s', 'mixmymeals' ), $title );
		$mail_body    = sprintf( __( "My Meal Plan:\n\n%s", 'mixmymeals' ), $url );

		$mailto_link = sprintf(
			'mailto:?subject=%s&body=%s',
			rawurlencode( $mail_subject ),
			rawurlencode( $mail_body )
		);

		// Bring! Deep Link vorbereiten (zeigt auf die Import-Ansicht des Plans)
		$bring_link = Mixmymeals_Meal_Plan::bring_deeplink( $meal_plan_id );

		ob_start();
		?>

        <div class="mmm-meal-plan-actions">
        <span
                class="mmm-meal-plan-actions__item mmm-meal-plan-actions__item--print"
                data-meal-plan-id="<?php echo esc_attr( $meal_plan_id ); ?>">
            <?php esc_html_e( 'Print to PDF', 'mixmymeals' ); ?>
        </span>

            <a
                    href="<?php echo esc_url( $mailto_link ); ?>"
                    class="mmm-meal-plan-actions__item mmm-meal-plan-actions__item--mail">
				<?php esc_html_e( 'Send via Email', 'mixmymeals' ); ?>
            </a>

            <a
                    href="<?php echo esc_url( $bring_link ); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="mmm-meal-plan-actions__item mmm-meal-plan-actions__item--bring">
				<?php esc_html_e( 'Export to Bring!', 'mixmymeals' ); ?>
            </a>

            <span
                    class="mmm-meal-plan-actions__item mmm-meal-plan-actions__item--load-into-cart"
                    data-meal-plan-id="<?php echo esc_attr( $meal_plan_id ); ?>"
                    data-redirect-url="<?php echo esc_attr( $atts['cart_url'] ); ?>">
				<?php esc_html_e( 'Adjust', 'mixmymeals' ); ?>
            </span>
        </div>

		<?php
		return ob_get_clean();
	}

	/**
	 * Builds the summed "<kcal> kcal ps" info string for a list of meals, so a
	 * heading covering several meals still shows a per-person total. Used for a
	 * whole day and for one meal-type group within a day.
	 *
	 * @param array $entries List of entries, each with 'meal' (Mixmymeals_Meal).
	 * @return string Empty string when the meals have no calories.
	 */
	private function get_meals_head_info( array $entries ): string {

		$kcal = 0;
		foreach ( $entries as $entry ) {
			$recipe = new Mixmymeals_Recipe( (int) $entry['meal']->get_recipe_id() );
			if ( ! $recipe->is_valid() ) {
				continue;
			}
			$nutrition = $recipe->calculate_nutrition_per_serving();
			$kcal     += (int) round( $nutrition['calories'] ?? 0 );
		}

		if ( $kcal <= 0 ) {
			return '';
		}

		return number_format_i18n( $kcal ) . ' ' . __( 'kcal ps', 'mixmymeals' );
	}

	/**
	 * Per-serving (per-person) calories of a recipe, rounded. Used to feed the
	 * planner's live day totals via a data attribute.
	 *
	 * @param int $recipe_id Recipe post ID.
	 * @return int
	 */
	private function recipe_kcal_per_serving( int $recipe_id ): int {

		if ( $recipe_id <= 0 ) {
			return 0;
		}

		$recipe = new Mixmymeals_Recipe( $recipe_id );
		if ( ! $recipe->is_valid() ) {
			return 0;
		}

		$nutrition = $recipe->calculate_nutrition_per_serving();
		return (int) round( $nutrition['calories'] ?? 0 );
	}

	/**
	 * Shortcode [mmm_breadcrumb].
	 * Renders a context-aware breadcrumb trail for the current MixMyMeals
	 * single view (meal plan, recipe or meal). Returns an empty string in
	 * any other context. Markup is styled via the child theme
	 * (.mmm-breadcrumb).
	 *
	 * @return string
	 */
	public function mmm_breadcrumb_callback(): string {

		$post_id = get_the_ID();

		if ( ! $post_id ) {
			return '';
		}

		$post_type = get_post_type( $post_id );
		$title     = get_the_title( $post_id );

		// Middle trail item between Home and the current title.
		switch ( $post_type ) {
			case 'mmm_meal_plan':
			case 'mmm_recipe':
				$parent_url    = get_post_type_archive_link( $post_type );
				$post_type_obj = get_post_type_object( $post_type );
				$parent_label  = $post_type_obj ? $post_type_obj->labels->name : '';
				break;

			case 'mmm_meal':
				$plan_id      = (int) wp_get_post_parent_id( $post_id );
				$parent_url   = $plan_id ? get_permalink( $plan_id ) : '';
				$parent_label = $plan_id ? get_the_title( $plan_id ) : '';
				break;

			default:
				return '';
		}

		ob_start();
		?>
        <nav class="mmm-breadcrumb">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'mixmymeals' ); ?></a>
            <span class="mmm-breadcrumb__sep">/</span>
			<?php if ( $parent_url && $parent_label ) : ?>
                <a href="<?php echo esc_url( $parent_url ); ?>"><?php echo esc_html( $parent_label ); ?></a>
                <span class="mmm-breadcrumb__sep">/</span>
			<?php endif; ?>
            <span><?php echo esc_html( $title ); ?></span>
        </nav>
		<?php
		return ob_get_clean();
	}

	/**
	 * Shortcode [mmm_meal_plan_hero].
	 * Renders plan title, meta chips and action buttons for
	 * the current meal plan („Stopf nicht!"-Redesign).
	 *
	 * @param array|string $atts Shortcode attributes: cart_url.
	 * @return string
	 */
	public function mmm_meal_plan_hero_callback( $atts = [] ): string {

		$atts = shortcode_atts( array( 'cart_url' => '' ), $atts );

		$plan_id = get_the_ID();

		if ( ! $plan_id || get_post_type( $plan_id ) !== 'mmm_meal_plan' ) {
			return '';
		}

		$plan  = new Mixmymeals_Meal_Plan( $plan_id );
		$title = get_the_title( $plan_id );
		$url   = get_permalink( $plan_id );

		// Chip-Daten
		$num_of_days          = $plan->count_days();
		$num_of_meals         = $plan->count_meals();
		$serving_range_string = $plan->get_servings_range_string();
		$avg_cost             = $plan->calculate_avg_cost_per_serving();
		$currency             = get_option( 'mixmymeals_currency', '€' );

		// Aktionen (Logik identisch zu mmm_meal_plan_actions)
		$mail_subject = sprintf( __( 'Meal Plan: %s', 'mixmymeals' ), $title );
		$mail_body    = sprintf( __( "My Meal Plan:\n\n%s", 'mixmymeals' ), $url );
		$mailto_link  = sprintf(
			'mailto:?subject=%s&body=%s',
			rawurlencode( $mail_subject ),
			rawurlencode( $mail_body )
		);
		$bring_link   = Mixmymeals_Meal_Plan::bring_deeplink( $plan_id );

		ob_start();
		?>

        <section class="mmm-plan-hero">
            <div class="mmm-plan-hero__blob"></div>
            <h1><?php echo esc_html( $title ); ?></h1>

            <div class="mmm-plan-hero__meta">
				<?php if ( $num_of_days ) : ?>
                    <span class="mmm-chip mmm-chip--stat"><i class="mmm-icon fa-solid fa-calendar-days" aria-hidden="true"></i><?php
						/* translators: %s = number of days */
						echo esc_html( str_replace( '%s', number_format_i18n( $num_of_days ), _n( '%s Day', '%s Days', $num_of_days, 'mixmymeals' ) ) );
						?></span>
				<?php endif; ?>
				<?php if ( $num_of_meals ) : ?>
                    <span class="mmm-chip mmm-chip--stat"><i class="mmm-icon fa-solid fa-book" aria-hidden="true"></i><?php
						/* translators: %s = number of meals */
						echo esc_html( str_replace( '%s', number_format_i18n( $num_of_meals ), _n( '%s Recipe', '%s Recipes', $num_of_meals, 'mixmymeals' ) ) );
						?></span>
				<?php endif; ?>
				<?php if ( $serving_range_string ) : ?>
                    <span class="mmm-chip mmm-chip--stat"><i class="mmm-icon fa-solid fa-users" aria-hidden="true"></i><?php echo esc_html( $serving_range_string ); ?></span>
				<?php endif; ?>
				<?php if ( $avg_cost > 0 ) : ?>
                    <span class="mmm-chip mmm-chip--stat mmm-chip--price"><i class="mmm-icon fa-solid fa-tag" aria-hidden="true"></i><?php
						printf(
							/* translators: %s: formatted average cost incl. currency */
							esc_html__( '%s / serving', 'mixmymeals' ),
							esc_html( number_format_i18n( $avg_cost, 2 ) . ' ' . $currency )
						);
						?></span>
				<?php endif; ?>
            </div>

				<?php if ( has_excerpt( $plan_id ) ) : ?>
                <p class="mmm-plan-hero__lead"><?php echo esc_html( get_the_excerpt( $plan_id ) ); ?></p>
				<?php endif; ?>

            <div class="mmm-plan-hero__actions">
                <a href="#"
                   class="mmm-btn mmm-btn--ghost mmm-meal-plan-actions__item--print"
                   data-meal-plan-id="<?php echo esc_attr( $plan_id ); ?>">
                    <i class="mmm-icon fa-solid fa-print" aria-hidden="true"></i><?php esc_html_e( 'Print to PDF', 'mixmymeals' ); ?>
                </a>
                <a href="<?php echo esc_url( $mailto_link ); ?>"
                   class="mmm-btn mmm-btn--ghost">
                    <i class="mmm-icon fa-solid fa-envelope" aria-hidden="true"></i><?php esc_html_e( 'Send via Email', 'mixmymeals' ); ?>
                </a>
                <a href="<?php echo esc_url( $bring_link ); ?>" target="_blank" rel="noopener noreferrer"
                   class="mmm-btn mmm-btn--ghost">
                    <i class="mmm-icon fa-solid fa-paper-plane" aria-hidden="true"></i><?php esc_html_e( 'Export to Bring!', 'mixmymeals' ); ?>
                </a>
                <a href="#"
                   class="mmm-btn mmm-btn--ghost mmm-meal-plan-actions__item--load-into-cart"
                   data-meal-plan-id="<?php echo esc_attr( $plan_id ); ?>"
                   data-redirect-url="<?php echo esc_attr( $atts['cart_url'] ); ?>">
                    <i class="mmm-icon fa-solid fa-pen-to-square" aria-hidden="true"></i><?php esc_html_e( 'Adjust', 'mixmymeals' ); ?>
                </a>
            </div>
        </section>
		<?php

		return ob_get_clean();
	}

	/**
	 * Shortcode [mmm_recipe_hero].
	 * Renders title, meta chips, excerpt and the
	 * add-to-planner action for the current recipe.
	 *
	 * @return string
	 */
	public function mmm_recipe_hero_callback(): string {

		$recipe_id = get_the_ID();

		if ( ! $recipe_id || get_post_type( $recipe_id ) !== 'mmm_recipe' ) {
			return '';
		}

		$recipe = new Mixmymeals_Recipe( $recipe_id );

		if ( ! $recipe->is_valid() ) {
			return '';
		}

		$title    = get_the_title( $recipe_id );
		$servings = (int) $recipe->get_servings();
		$cost     = $recipe->calculate_cost_per_serving();
		$currency = get_option( 'mixmymeals_currency', '€' );

		$nutrition = $recipe->calculate_nutrition_per_serving();
		$kcal      = (int) round( $nutrition['calories'] ?? 0 );

		$prep_minutes     = $recipe->get_preparation_time();
		$prep_time_string = '';
		if ( $prep_minutes > 0 ) {
			if ( $prep_minutes < 60 ) {
				$prep_time_string = sprintf( '%s min', number_format_i18n( $prep_minutes ) );
			} else {
				$hours            = floor( $prep_minutes / 60 );
				$minutes          = $prep_minutes % 60;
				$prep_time_string = sprintf( '%s h', number_format_i18n( $hours ) );
				if ( $minutes > 0 ) {
					$prep_time_string .= ' ' . sprintf( '%s min', number_format_i18n( $minutes ) );
				}
			}
		}

		ob_start();
		?>

        <section class="mmm-plan-hero">
            <div class="mmm-plan-hero__blob"></div>
			<?php
			$rating_html = $this->mmm_recipe_rating_callback( array( 'hide_empty' => 1 ) );
			if ( '' !== $rating_html ) {
				echo '<div class="mmm-plan-hero__rating">' . $rating_html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			?>
            <h1><?php echo esc_html( $title ); ?></h1>

            <div class="mmm-plan-hero__meta">
				<?php if ( $servings ) : ?>
                    <span class="mmm-chip mmm-chip--stat"><i class="mmm-icon fa-solid fa-users" aria-hidden="true"></i><?php
						printf(
							'%d %s',
							$servings,
							1 === $servings
								? esc_html__( 'Serving', 'mixmymeals' )
								: esc_html__( 'Servings', 'mixmymeals' )
						);
						?></span>
				<?php endif; ?>
				<?php if ( $prep_time_string ) : ?>
                    <span class="mmm-chip mmm-chip--stat"><i class="mmm-icon fa-solid fa-clock" aria-hidden="true"></i><?php echo esc_html( $prep_time_string ); ?></span>
				<?php endif; ?>
				<?php if ( $kcal > 0 ) : ?>
                    <span class="mmm-chip mmm-chip--stat"><i class="mmm-icon fa-solid fa-fire" aria-hidden="true"></i><?php echo esc_html( number_format_i18n( $kcal ) . ' ' . __( 'kcal ps', 'mixmymeals' ) ); ?></span>
				<?php endif; ?>
				<?php if ( $cost > 0 ) : ?>
                    <span class="mmm-chip mmm-chip--stat mmm-chip--price"><i class="mmm-icon fa-solid fa-tag" aria-hidden="true"></i><?php
						printf(
							/* translators: %s: formatted average cost incl. currency */
							esc_html__( '%s / serving', 'mixmymeals' ),
							esc_html( number_format_i18n( $cost, 2 ) . ' ' . $currency )
						);
						?></span>
				<?php endif; ?>
            </div>

			<?php if ( has_excerpt( $recipe_id ) ) : ?>
                <p class="mmm-plan-hero__lead"><?php echo esc_html( get_the_excerpt( $recipe_id ) ); ?></p>
			<?php endif; ?>

            <div class="mmm-plan-hero__actions">
				<?php
				// Same renderer as the recipe cards, only as a full button —
				// so there is one place that knows this control's markup.
				echo $this->render_add_to_planner_button( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the renderer.
					(int) $recipe_id,
					array(
						'element'  => 'button',
						'servings' => $servings,
					)
				);
				?>
            </div>
        </section>
		<?php

		return ob_get_clean();
	}

	/**
	 * Shortcode [mmm_recipe_author_box].
	 * Renders an author card (avatar, display name and biography) for the
	 * recipe's author. Designed to sit just below the recipe hero.
	 *
	 * Also works on a meal: a meal is a planned serving of a recipe and has no
	 * author of its own, so it credits the recipe it was built from — same card,
	 * same "Recipe by" label. That way the meal page, which is where visitors
	 * actually cook, carries the credit too.
	 *
	 * Returns '' when used anywhere else, when the meal has no valid recipe, or
	 * when there is no author to name.
	 *
	 * @param array|string $atts Optional: id to force a specific recipe or meal.
	 * @return string
	 */
	public function mmm_recipe_author_box_callback( $atts = array() ): string {

		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'mmm_recipe_author_box' );

		$post_id = (int) $atts['id'] > 0 ? (int) $atts['id'] : (int) get_the_ID();
		if ( ! $post_id ) {
			return '';
		}

		$recipe_id = Mixmymeals_Meal::resolve_credit_source( $post_id );
		if ( ! $recipe_id || 'mmm_recipe' !== get_post_type( $recipe_id ) ) {
			return '';
		}

		return $this->render_author_box(
			$recipe_id,
			__( 'Recipe by', 'mixmymeals' )
		);
	}

	/**
	 * The author card itself, shared by [mmm_recipe_author_box] and
	 * [mmm_meal_plan_author_box] so both post types render identically instead
	 * of drifting apart.
	 *
	 * Two sources, in this order: the post's own author fields (name, text,
	 * link, picture) and — when no name is set there — the WordPress user who
	 * created the post, with avatar and profile bio. Both render the same
	 * markup, so an external author with a picture is indistinguishable from a
	 * WordPress user: same classes, same size, same rounding. An external
	 * author without a picture gets no media block at all.
	 *
	 * Returns '' when there is neither an external author nor a WordPress
	 * author to credit (e.g. plans created by anonymous visitors, which carry
	 * post_author = 0).
	 *
	 * @param int    $post_id Post ID.
	 * @param string $eyebrow Small label above the name ("Recipe by"/"Plan by").
	 * @return string
	 */
	private function render_author_box( int $post_id, string $eyebrow ): string {

		$size   = Mixmymeals_Post_Meta::AUTHOR_AVATAR_SIZE;
		$name   = Mixmymeals_Post_Meta::get_author_name( $post_id );
		$avatar = '';

		if ( '' !== $name ) {
			$bio = Mixmymeals_Post_Meta::get_author_text( $post_id );

			// Same markup contract as get_avatar() below — square source, the
			// avatar/photo classes some themes and microformat parsers key on,
			// plus the box's own class the theme sizes and rounds. Without a
			// picture the media block is simply omitted.
			$image_id = Mixmymeals_Post_Meta::get_author_image_id( $post_id );

			if ( $image_id ) {
				$avatar = wp_get_attachment_image(
					$image_id,
					'thumbnail',
					false,
					array(
						'class'  => 'avatar avatar-' . $size . ' photo mmm-author-box__avatar',
						'alt'    => $name,
						'width'  => $size,
						'height' => $size,
					)
				);
			}
		} else {
			$author_id = (int) get_post_field( 'post_author', $post_id );
			if ( $author_id <= 0 ) {
				return '';
			}

			$name = trim( (string) get_the_author_meta( 'display_name', $author_id ) );
			if ( '' === $name ) {
				return '';
			}

			$bio    = wpautop( esc_html( trim( (string) get_the_author_meta( 'description', $author_id ) ) ) );
			$avatar = get_avatar(
				$author_id,
				$size,
				'',
				$name,
				array( 'class' => 'mmm-author-box__avatar' )
			);
		}

		$url       = Mixmymeals_Post_Meta::get_author_url( $post_id );
		$link_html = '';

		if ( '' !== $url ) {
			$rel = Mixmymeals_Post_Meta::get_author_nofollow( $post_id )
				? 'nofollow noopener noreferrer'
				: 'noopener noreferrer';

			$link_html = '<a class="mmm-author-box__link" href="' . esc_url( $url ) . '"'
				. ' target="_blank" rel="' . esc_attr( $rel ) . '">'
				. esc_html( Mixmymeals_Post_Meta::get_author_link_text( $post_id ) )
				. '</a>';
		}

		ob_start();
		?>
		<aside class="mmm-author-box">
			<div class="mmm-author-box__blob" aria-hidden="true"></div>
			<?php if ( $avatar ) : ?>
				<div class="mmm-author-box__media">
					<?php echo $avatar; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_avatar()/wp_get_attachment_image() return escaped markup. ?>
				</div>
			<?php endif; ?>
			<div class="mmm-author-box__body">
				<span class="mmm-author-box__eyebrow"><?php echo esc_html( $eyebrow ); ?></span>
				<span class="mmm-author-box__name"><?php echo esc_html( $name ); ?></span>
				<?php if ( '' !== trim( wp_strip_all_tags( (string) $bio ) ) ) : ?>
					<div class="mmm-author-box__bio"><?php echo wp_kses_post( $bio ); ?></div>
				<?php endif; ?>
				<?php if ( '' !== $link_html ) : ?>
					<?php echo $link_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped parts above. ?>
				<?php endif; ?>
			</div>
		</aside>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Shortcode [mmm_image_credit].
	 * Renders the credit for the featured image, meant to sit directly below
	 * the picture it belongs to.
	 *
	 * Works on recipes, meal plans and meals. A meal shows the featured image
	 * of the recipe it plans, so it shows that recipe's credit — the same rule
	 * the author box follows.
	 *
	 * Returns '' when nothing is credited, so a template can print it
	 * unconditionally without leaving an empty line behind.
	 *
	 * @param array|string $atts id to force a post, label to change or hide
	 *                           the prefix.
	 * @return string
	 */
	public function mmm_image_credit_callback( $atts = array() ): string {

		$atts = shortcode_atts(
			array(
				'id'    => 0,
				'label' => __( 'Image source:', 'mixmymeals' ),
			),
			$atts,
			'mmm_image_credit'
		);

		$post_id = (int) $atts['id'] > 0 ? (int) $atts['id'] : (int) get_the_ID();
		if ( ! $post_id ) {
			return '';
		}

		$post_id = Mixmymeals_Meal::resolve_credit_source( $post_id );

		if ( ! $post_id || ! Mixmymeals_Post_Meta::has_image_credit( $post_id ) ) {
			return '';
		}

		$data  = Mixmymeals_Post_Meta::get_image_credit( $post_id );
		$name  = Mixmymeals_Post_Meta::get_image_credit_name( $post_id );
		$label = (string) $atts['label'];

		$html = '';

		if ( '' !== $label ) {
			$html .= '<span class="mmm-image-credit__label">' . esc_html( $label ) . '</span> ';
		}

		if ( '' !== $name ) {
			// Always nofollow, unlike the author box, which has a per-post
			// toggle: a credit link is a licence obligation pointing at a
			// stock library or someone else's site, not an endorsement, and
			// it is repeated on every post using that picture.
			$html .= '' !== $data['url']
				? '<a class="mmm-image-credit__link" href="' . esc_url( $data['url'] ) . '"'
					. ' target="_blank" rel="nofollow noopener noreferrer">' . esc_html( $name ) . '</a>'
				: '<span class="mmm-image-credit__name">' . esc_html( $name ) . '</span>';
		}

		if ( '' !== $data['license'] ) {
			$html .= ( '' !== $name ? ' ' : '' )
				. '<span class="mmm-image-credit__license">'
				. esc_html( '' !== $name ? '(' . $data['license'] . ')' : $data['license'] )
				. '</span>';
		}

		return '<p class="mmm-image-credit">' . $html . '</p>';
	}

	/**
	 * The default wording per disclosure type. "Anzeige" / "Werbung" are the
	 * terms German case law accepts; "Kooperation", "sponsored by" and English
	 * wording have repeatedly been held insufficient, so neither is used as a
	 * default here even though the internal type is called `cooperation`.
	 *
	 * @param string $type Disclosure type.
	 * @return string Empty when the type needs no label.
	 */
	public static function disclosure_label_text( string $type ): string {

		switch ( $type ) {
			case Mixmymeals_Post_Meta::DISCLOSURE_SPONSORED:
				$text = __( 'Advertisement', 'mixmymeals' );
				break;

			case Mixmymeals_Post_Meta::DISCLOSURE_COOPERATION:
				$text = __( 'Advertisement', 'mixmymeals' );
				break;

			default:
				return '';
		}

		/**
		 * Filters the disclosure label wording.
		 *
		 * Whatever this returns is what the page presents as its legal
		 * disclosure — returning '' or something evasive removes the label
		 * without removing the obligation.
		 *
		 * @param string $text The default wording.
		 * @param string $type Disclosure type.
		 */
		return (string) apply_filters( 'mixmymeals_disclosure_label_text', $text, $type );
	}

	/**
	 * The bare disclosure pill for listings: no paragraph wrapper, no sponsor
	 * name. Cards are tight and repetitive, so they carry the label only — the
	 * legally required part — while the sponsor stays on the single page.
	 *
	 * Returns '' for editorial content and unpaid guest contributions, so card
	 * renderers can call it unconditionally.
	 *
	 * @param int $post_id Recipe or plan ID. Meals resolve to their recipe.
	 * @return string
	 */
	public static function disclosure_pill( int $post_id ): string {

		$post_id = Mixmymeals_Meal::resolve_credit_source( $post_id );

		if ( ! $post_id || ! Mixmymeals_Post_Meta::requires_disclosure_label( $post_id ) ) {
			return '';
		}

		$type = Mixmymeals_Post_Meta::get_disclosure_type( $post_id );
		$text = self::disclosure_label_text( $type );

		if ( '' === trim( $text ) ) {
			return '';
		}

		return '<p class="mmm-disclosure mmm-disclosure--card mmm-disclosure--' . esc_attr( $type ) . '">'
			. '<span class="mmm-disclosure__label">' . esc_html( $text ) . '</span>'
			. '</p>';
	}

	/**
	 * Shortcode [mmm_disclosure_label].
	 * Renders the "Anzeige" pill for commercial content — paid placements and
	 * cooperations. Editorial and unpaid guest contributions render nothing:
	 * a guest recipe is credited through the author box and is not advertising.
	 *
	 * Resolves meals to the recipe they plan, like every other crediting
	 * feature, so a meal never drops the recipe's disclosure.
	 *
	 * Meant to sit above the content and inside listings — a label that only
	 * appears after a click, or below the fold, does not do its job.
	 *
	 * @param array|string $atts id to force a post.
	 * @return string
	 */
	public function mmm_disclosure_label_callback( $atts = array() ): string {

		$atts = shortcode_atts(
			array(
				'id' => 0,
			),
			$atts,
			'mmm_disclosure_label'
		);

		$post_id = (int) $atts['id'] > 0 ? (int) $atts['id'] : (int) get_the_ID();
		if ( ! $post_id ) {
			return '';
		}

		$post_id = Mixmymeals_Meal::resolve_credit_source( $post_id );

		if ( ! $post_id || ! Mixmymeals_Post_Meta::requires_disclosure_label( $post_id ) ) {
			return '';
		}

		$type = Mixmymeals_Post_Meta::get_disclosure_type( $post_id );
		$text = self::disclosure_label_text( $type );

		if ( '' === trim( $text ) ) {
			return '';
		}

		$sponsor = Mixmymeals_Post_Meta::get_disclosure_sponsor( $post_id );

		$html = '<span class="mmm-disclosure__label">' . esc_html( $text ) . '</span>';

		if ( '' !== $sponsor ) {
			$html .= '<span class="mmm-disclosure__sponsor">' . esc_html( $sponsor ) . '</span>';
		}

		return '<p class="mmm-disclosure mmm-disclosure--' . esc_attr( $type ) . '">'
			. $html
			. '</p>';
	}

	/**
	 * Shortcode [mmm_meal_plan_author_box]. The counterpart to
	 * [mmm_recipe_author_box] for meal plans, using the same markup and classes.
	 *
	 * Two sources, in order: the freely editable author fields on the plan
	 * (name, text, link, picture) and — when no name is set there — the
	 * WordPress user who created the plan, with avatar and profile bio, exactly
	 * like the recipe box. The optional link is rendered follow or nofollow per
	 * the plan's setting; an external author without a picture gets no media
	 * block (the recipe box handles a missing avatar the same way).
	 *
	 * @param array|string $atts Shortcode attributes: id.
	 * @return string
	 */
	public function mmm_meal_plan_author_box_callback( $atts = array() ): string {

		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'mmm_meal_plan_author_box' );

		$plan_id = (int) $atts['id'] > 0 ? (int) $atts['id'] : (int) get_the_ID();
		if ( ! $plan_id || get_post_type( $plan_id ) !== 'mmm_meal_plan' ) {
			return '';
		}

		return $this->render_author_box(
			$plan_id,
			__( 'Plan by', 'mixmymeals' )
		);
	}

	/**
	 * Shortcode [mmm_meal_hero].
	 * Renders title, meta chips and actions (cooking mode,
	 * back to plan) for the current meal.
	 *
	 * @return string
	 */
	public function mmm_meal_hero_callback(): string {

		$meal_id = get_the_ID();

		if ( ! $meal_id || get_post_type( $meal_id ) !== 'mmm_meal' ) {
			return '';
		}

		$meal      = new Mixmymeals_Meal( $meal_id );
		$title     = get_the_title( $meal_id );
		$recipe_id = (int) $meal->get_recipe_id();
		$recipe    = new Mixmymeals_Recipe( $recipe_id );

		$weekday_labels = [
			1 => __( 'Monday', 'mixmymeals' ),
			2 => __( 'Tuesday', 'mixmymeals' ),
			3 => __( 'Wednesday', 'mixmymeals' ),
			4 => __( 'Thursday', 'mixmymeals' ),
			5 => __( 'Friday', 'mixmymeals' ),
			6 => __( 'Saturday', 'mixmymeals' ),
			7 => __( 'Sunday', 'mixmymeals' ),
		];

		$day        = (int) $meal->get_day();
		$day_label  = $weekday_labels[ $day ] ?? '';
		$type_map   = Mixmymeals_Meal::get_plaintext_meal_type_map();
		$type_label = $type_map[ $meal->get_type() ] ?? '';
		$servings   = (int) $meal->get_servings();

		$kcal = 0;
		if ( $recipe->is_valid() ) {
			$nutrition = $recipe->calculate_nutrition_per_serving();
			$kcal      = (int) round( $nutrition['calories'] ?? 0 );
		}

		$has_steps = $recipe->is_valid() && ! empty( $recipe->get_steps() );

		$plan_id    = (int) wp_get_post_parent_id( $meal_id );
		$plan_url   = $plan_id ? get_permalink( $plan_id ) : '';
		$plan_title = $plan_id ? get_the_title( $plan_id ) : '';

		ob_start();
		?>

        <section class="mmm-plan-hero">
            <div class="mmm-plan-hero__blob"></div>
			<?php if ( $plan_url && $plan_title ) : ?>
                <a class="mmm-plan-hero__back" href="<?php echo esc_url( $plan_url ); ?>">
                    <i class="mmm-icon fa-solid fa-arrow-left" aria-hidden="true"></i><?php echo esc_html( __( 'Back to', 'mixmymeals' ) . ' ' . $plan_title ); ?>
                </a>
			<?php endif; ?>
            <h1><?php echo esc_html( $title ); ?></h1>

            <div class="mmm-plan-hero__meta">
				<?php if ( $day_label ) : ?>
                    <span class="mmm-chip mmm-chip--stat"><i class="mmm-icon fa-solid fa-calendar-days" aria-hidden="true"></i><?php echo esc_html( $day_label ); ?></span>
				<?php endif; ?>
				<?php if ( $type_label ) : ?>
                    <span class="mmm-chip mmm-chip--stat"><i class="mmm-icon fa-solid fa-book" aria-hidden="true"></i><?php echo esc_html( $type_label ); ?></span>
				<?php endif; ?>
				<?php if ( $servings ) : ?>
                    <span class="mmm-chip mmm-chip--stat"><i class="mmm-icon fa-solid fa-users" aria-hidden="true"></i><?php
						printf(
							'%d %s',
							$servings,
							1 === $servings
								? esc_html__( 'Serving', 'mixmymeals' )
								: esc_html__( 'Servings', 'mixmymeals' )
						);
						?></span>
				<?php endif; ?>
				<?php if ( $kcal > 0 ) : ?>
                    <span class="mmm-chip mmm-chip--stat"><i class="mmm-icon fa-solid fa-fire" aria-hidden="true"></i><?php echo esc_html( number_format_i18n( $kcal ) . ' ' . __( 'kcal ps', 'mixmymeals' ) ); ?></span>
				<?php endif; ?>
            </div>

            <div class="mmm-plan-hero__actions">
				<?php if ( $has_steps ) : ?>
                    <button type="button" class="mmm-btn mmm-btn--primary mmm-cook-mode-start">
                        <i class="mmm-icon fa-solid fa-fire" aria-hidden="true"></i><?php esc_html_e( 'Start cooking mode', 'mixmymeals' ); ?>
                    </button>
				<?php endif; ?>
            </div>
        </section>
		<?php

		return ob_get_clean();
	}








	/**
	 * Shortcode [mmm_back_to_meal_plan_link_for_single_meal].
	 * Renders a back-link to the parent meal plan from a single meal page.
	 *
	 * @return string
	 */
	public function mmm_back_to_meal_plan_link_for_single_meal_callback(): string {

		$html = '';

		$parent_plan_id = wp_get_post_parent_id();

		if ( ! is_numeric( $parent_plan_id ) && $parent_plan_id < 0 ) {
			$html .= __( 'You can only use this shortcode with a single meal.',
				'mixmymeals' );

			return $html;
		}

		if ( $parent_plan_id ) {
			$html .= '<a class="mmm-meal-back-to-meal-plan-link" href="'
			         . esc_urL( get_permalink( $parent_plan_id ) ) . '">'
			         . esc_html__( 'Back to', 'mixmymeals' ) . ' '
			         . esc_html( get_the_title( $parent_plan_id ) ) . '</a>';
		}

		return $html;

	}

	/**
	 * Shortcode [mmm_meta_for_a_single_meal].
	 * Renders title, date, servings, and nutrition for the current meal.
	 *
	 * @return string
	 */
	public function mmm_meta_for_a_single_meal_callback(): string {

		$html = '';

		$meal_ID = get_the_ID();

		if ( ! is_numeric( $meal_ID ) || $meal_ID < 0 ) {
			return esc_html__(
				'You can only use this shortcode with a single meal.',
				'mixmymeals'
			);
		}

		$meal_obj = new Mixmymeals_Meal( $meal_ID );
		$servings = $meal_obj->get_servings();

		$items = [];

		/* -------------------------
		 * Servings
		 * ------------------------- */
		if ( $servings ) {
			$items[] = '<span class="mmm-meta__item mmm-meta__item--servings">'
			           . intval( $servings ) . ' '
			           . esc_html(
				           $servings === 1 ? __( 'Serving', 'mixmymeals' )
					           : __( 'Servings', 'mixmymeals' )
			           )
			           . '</span>';
		}

		/* -------------------------
		 * Recipe related meta
		 * ------------------------- */
		$recipe_id = (int) $meal_obj->get_recipe_id();

		if ( $recipe_id > 0 && class_exists( 'Mixmymeals_Recipe' ) ) {

			$recipe = new Mixmymeals_Recipe( $recipe_id );

			/* Calories (per serving) */
			$nutrition = $recipe->calculate_nutrition_per_serving();

			if ( ! empty( $nutrition['calories'] ) ) {
				$items[]
					= '<span class="mmm-meta__item mmm-meta__item--calories">'
					  . esc_html(
						  number_format_i18n( round( $nutrition['calories'] ) )
					  )
					  . ' kcal</span>';
			}

			/* Preparation time */
			$prep_minutes = $recipe->get_preparation_time();

			if ( $prep_minutes > 0 ) {

				if ( $prep_minutes < 60 ) {
					$prep_string = sprintf(
						__( '%s min', 'mixmymeals' ),
						number_format_i18n( $prep_minutes )
					);
				} else {
					$hours   = floor( $prep_minutes / 60 );
					$minutes = $prep_minutes % 60;

					$prep_string = sprintf(
						__( '%s h', 'mixmymeals' ),
						number_format_i18n( $hours )
					);

					if ( $minutes > 0 ) {
						$prep_string .= ' ' . sprintf(
								__( '%s min', 'mixmymeals' ),
								number_format_i18n( $minutes )
							);
					}
				}

				$items[]
					= '<span class="mmm-meta__item mmm-meta__item--prep-time">'
					  . esc_html( $prep_string )
					  . '</span>';
			}

			/* -------------------------
			 * Cost per Serving
			 * ------------------------- */
			$cost = $recipe->calculate_cost_per_serving();
			if ( $cost > 0 ) {
				$currency = get_option( 'mixmymeals_currency', '€' );
				$items[]  = '<span class="mmm-meta__item mmm-meta__item--cost">'
				            . esc_html( number_format_i18n( $cost, 2 ) . ' ' . $currency )
				            . '</span>';
			}

			/* -------------------------
			 * Categories
			 * ------------------------- */
			$categories = $recipe->get_categories();
			if ( $categories ) {
				$found_diet = '';
				$diet_label = '';
				foreach ( $categories as $cat ) {
					$name = strtolower( $cat->name );
					if ( $name === 'vegan' ) {
						$found_diet = 'vegan';
						$diet_label = $cat->name;
					} elseif ( in_array( $name, [ 'vegetarisch', 'vegetarian' ], true ) && $found_diet !== 'vegan' ) {
						$found_diet = 'vegetarian';
						$diet_label = $cat->name;
					} else {
						$items[] = '<span class="mmm-meta__item mmm-meta__item--category">'
						           . esc_html( $cat->name )
						           . '</span>';
					}
				}
				if ( $found_diet !== '' ) {
					$items[] = '<span class="mmm-meta__item mmm-meta__item--diet">'
					           . esc_html( $diet_label ) . '</span>';
				}
			}

			/* -------------------------
             * Tags (mmm_recipe_tag)
             * ------------------------- */
			$tag_terms = get_the_terms( $recipe_id, 'mmm_recipe_tag' );
			if ( ! empty( $tag_terms ) && ! is_wp_error( $tag_terms ) ) {
				usort( $tag_terms,
					fn( $a, $b ) => strcasecmp( $a->name, $b->name ) );
				foreach ( $tag_terms as $term ) {
					$items[]
						= '<span class="mmm-meta__item mmm-meta__item--tag">'
						  . esc_html( $term->name )
						  . '</span>';
				}
			}

			/* -------------------------
			 * Diet Type Badge
			 * ------------------------- */
			$diet = $recipe->get_diet_type();
			if ( $diet === 'vegan' ) {
				$items[] = '<span class="mmm-meta__item mmm-meta__item--diet">'
				           . esc_html__( 'Vegan', 'mixmymeals' ) . '</span>';
			} elseif ( $diet === 'vegetarian' ) {
				$items[] = '<span class="mmm-meta__item mmm-meta__item--diet">'
				           . esc_html__( 'Vegetarian', 'mixmymeals' ) . '</span>';
			}

		}

		if ( empty( $items ) ) {
			return '';
		}

		$html .= '<div class="mmm-meal-meta">' . implode( '', $items )
		         . '</div>';

		return $html;
	}


	/**
	 * Shortcode [mmm_meta_for_a_single_meal_plan].
	 * Renders nutrition totals and meal count for the current meal plan.
	 *
	 * @return string
	 */
	public function mmm_meta_for_a_single_meal_plan_callback(): string {

		$post_type = get_query_var( 'post_type' );
		$post_type = is_array( $post_type ) ? reset( $post_type ) : $post_type;

		if ( $post_type !== 'mmm_meal_plan' ) {
			return '';
		}

		$plan_id = get_the_ID();

		if ( ! $plan_id ) {
			return '';
		}

		$plan = new Mixmymeals_Meal_Plan( $plan_id );

		$num_of_days          = $plan->count_days();
		$num_of_meals         = $plan->count_meals();
		$serving_range_string = $plan->get_servings_range_string();

		$items = [];

		/* -------------------------
		 * Days
		 * ------------------------- */
		if ( $num_of_days ) {
			$items[] = sprintf(
				'<span class="mmm-meta__item mmm-meta__item--days">%s</span>',
				/* translators: %s = number of days */
				str_replace( '%s', number_format_i18n( $num_of_days ), _n( '%s Day', '%s Days', $num_of_days, 'mixmymeals' ) )
			);
		}

		/* -------------------------
		 * Meals
		 * ------------------------- */
		if ( $num_of_meals ) {
			$items[] = sprintf(
				'<span class="mmm-meta__item mmm-meta__item--meals">%s</span>',
				/* translators: %s = number of meals */
				str_replace( '%s', number_format_i18n( $num_of_meals ), _n( '%s Recipe', '%s Recipes', $num_of_meals, 'mixmymeals' ) )
			);
		}

		/* -------------------------
		 * Serving range
		 * ------------------------- */
		if ( $serving_range_string ) {
			$items[] = sprintf(
				'<span class="mmm-meta__item mmm-meta__item--servings">%s</span>',
				esc_html( $serving_range_string )
			);
		}

		/* -------------------------
		 * Average cost per serving
		 * ------------------------- */
		$avg_cost = $plan->calculate_avg_cost_per_serving();
		if ( $avg_cost > 0 ) {
			$currency = get_option( 'mixmymeals_currency', '€' );
			$items[]  = '<span class="mmm-meta__item mmm-meta__item--cost">'
			            . esc_html( number_format_i18n( $avg_cost, 2 ) . ' ' . $currency )
			            . '</span>';
		}

		/* -------------------------
		 * Diet Type Badge
		 * ------------------------- */
		$diet = $plan->get_diet_type();
		if ( $diet === 'vegan' ) {
			$items[] = '<span class="mmm-meta__item mmm-meta__item--diet">'
			           . esc_html__( 'Vegan', 'mixmymeals' ) . '</span>';
		} elseif ( $diet === 'vegetarian' ) {
			$items[] = '<span class="mmm-meta__item mmm-meta__item--diet">'
			           . esc_html__( 'Vegetarian', 'mixmymeals' ) . '</span>';
		}

		if ( empty( $items ) ) {
			return '';
		}

		return '<div class="mmm-meal-plan-meta">' . implode( '', $items )
		       . '</div>';
	}

	/**
	 * Shortcode [mmm_nutrition_overview_for_a_single_meal_plan].
	 * Renders a nutrition summary table (totals and averages) for a meal plan.
	 *
	 * @param array|string $atts Shortcode attributes: meal_plan_id.
	 * @return string
	 */
	public function mmm_nutrition_overview_for_a_single_meal_plan_callback( $atts = [] ): string {

		$atts = shortcode_atts(
			[ 'meal_plan_id' => null ],
			$atts,
			'mmm_nutrition_overview_for_a_single_meal_plan'
		);

		$meal_plan_id = $atts['meal_plan_id']
			? intval( $atts['meal_plan_id'] )
			: get_the_ID();

		if ( ! $meal_plan_id || get_post_type( $meal_plan_id ) !== 'mmm_meal_plan' ) {
			return '<p>' . esc_html__( 'No valid meal plan found.', 'mixmymeals' ) . '</p>';
		}

		$plan  = new Mixmymeals_Meal_Plan( $meal_plan_id );
		$meals = $plan->get_meals();

		if ( empty( $meals ) ) {
			return '<p>' . esc_html__( 'No meals found.', 'mixmymeals' ) . '</p>';
		}

		$keys           = [ 'calories', 'fat', 'carbs', 'protein', 'sugar', 'fiber', 'salt' ];
		$totals         = array_fill_keys( $keys, 0.0 );
		$total_servings = 0.0;

		foreach ( $meals as $meal_post ) {
			$meal      = new Mixmymeals_Meal( $meal_post->ID );
			$recipe_id = $meal->get_recipe_id();
			if ( ! $recipe_id ) {
				continue;
			}
			$recipe = new Mixmymeals_Recipe( $recipe_id );
			if ( ! $recipe->is_valid() ) {
				continue;
			}
			$nutrition_per_serving = $recipe->calculate_nutrition_per_serving();
			$meal_servings         = max( 1.0, floatval( $meal->get_servings() ) );
			$total_servings       += $meal_servings;

			foreach ( $keys as $key ) {
				$totals[ $key ] += ( $nutrition_per_serving[ $key ] ?? 0.0 ) * $meal_servings;
			}
		}

		$num_days       = max( 1, $plan->count_days() );
		$num_meals      = max( 1, count( $meals ) );
		$total_servings = max( 1.0, $total_servings );
		$meals_per_day  = $num_meals / $num_days;

		// Servings-weighted per-person figures: average nutrition of one
		// portion, scaled by how many meals a single person eats per day / in
		// total.
		$person_total = [];
		$person_day   = [];
		$person_meal  = [];
		foreach ( $keys as $key ) {
			$portion              = $totals[ $key ] / $total_servings;
			$person_meal[ $key ]  = $portion;
			$person_day[ $key ]   = $portion * $meals_per_day;
			$person_total[ $key ] = $portion * $num_meals;
		}

		$labels = [
			'calories' => [ __( 'Calories', 'mixmymeals' ), 'kcal' ],
			'protein'  => [ __( 'Protein', 'mixmymeals' ), 'g' ],
			'carbs'    => [ __( 'Carbohydrates', 'mixmymeals' ), 'g' ],
			'fat'      => [ __( 'Fat', 'mixmymeals' ), 'g' ],
			'sugar'    => [ __( 'Sugar', 'mixmymeals' ), 'g' ],
			'fiber'    => [ __( 'Fiber', 'mixmymeals' ), 'g' ],
			'salt'     => [ __( 'Salt', 'mixmymeals' ), 'g' ],
		];

		$stat_cards = [
			'calories' => [ __( 'Ø Calories / day / person', 'mixmymeals' ), 'kcal', 0 ],
			'protein'  => [ __( 'Ø Protein / day / person', 'mixmymeals' ), 'g', 1 ],
			'carbs'    => [ __( 'Ø Carbohydrates / day / person', 'mixmymeals' ), 'g', 1 ],
			'fat'      => [ __( 'Ø Fat / day / person', 'mixmymeals' ), 'g', 1 ],
		];

		ob_start();
		?>
        <div class="mmm-meal-plan-nutrition">

            <div class="mmm-nutri-stats">
				<?php foreach ( $stat_cards as $stat_key => [ $stat_label, $stat_unit, $stat_decimals ] ) : ?>
                    <div class="mmm-nutri-stat">
                        <div class="mmm-nutri-stat__k"><?php echo esc_html( $stat_label ); ?></div>
                        <div class="mmm-nutri-stat__v"><?php echo esc_html( number_format_i18n( $person_day[ $stat_key ], $stat_decimals ) ); ?></div>
                        <div class="mmm-nutri-stat__u"><?php echo esc_html( $stat_unit ); ?></div>
                    </div>
				<?php endforeach; ?>
            </div>

            <table class="mmm-nutrition-table mmm-nutri-table">
                <thead>
                <tr>
                    <th><?php esc_html_e( 'Nutrition', 'mixmymeals' ); ?></th>
                    <th><?php esc_html_e( 'Total / person', 'mixmymeals' ); ?></th>
                    <th><?php esc_html_e( 'Avg. per day / person', 'mixmymeals' ); ?></th>
                    <th><?php esc_html_e( 'Avg. per meal / person', 'mixmymeals' ); ?></th>
                </tr>
                </thead>
                <tbody>
				<?php foreach ( $labels as $key => [ $label, $unit ] ) :
					$total    = $person_total[ $key ];
					$per_day  = $person_day[ $key ];
					$per_meal = $person_meal[ $key ];
					$decimals = ( $key === 'calories' ) ? 0 : 1;
					?>
                    <tr>
                        <td data-label="<?php echo esc_attr__( 'Nutrition', 'mixmymeals' ); ?>"><?php echo esc_html( $label ); ?></td>
                        <td data-label="<?php echo esc_attr__( 'Total / person', 'mixmymeals' ); ?>"><?php echo esc_html( number_format_i18n( $total, $decimals ) ); ?> <?php echo esc_html( $unit ); ?></td>
                        <td data-label="<?php echo esc_attr__( 'Avg. per day / person', 'mixmymeals' ); ?>"><?php echo esc_html( number_format_i18n( $per_day, $decimals ) ); ?> <?php echo esc_html( $unit ); ?></td>
                        <td data-label="<?php echo esc_attr__( 'Avg. per meal / person', 'mixmymeals' ); ?>"><?php echo esc_html( number_format_i18n( $per_meal, $decimals ) ); ?> <?php echo esc_html( $unit ); ?></td>
                    </tr>
				<?php endforeach; ?>
                </tbody>
            </table>
        </div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Shortcode [mmm_nutrition_per_day_for_a_single_meal_plan].
	 * Renders the plan's nutrition broken down by weekday — the distribution
	 * that the averages in [mmm_nutrition_overview_for_a_single_meal_plan]
	 * necessarily hide. Days without meals are listed with zeroes so gaps in a
	 * plan stay visible.
	 *
	 * @param array|string $atts meal_plan_id, per (person|total).
	 * @return string
	 */
	public function mmm_nutrition_per_day_for_a_single_meal_plan_callback( $atts = [] ): string {

		$atts = shortcode_atts(
			[
				'meal_plan_id' => null,
				'per'          => 'person',
			],
			$atts,
			'mmm_nutrition_per_day_for_a_single_meal_plan'
		);

		$meal_plan_id = $atts['meal_plan_id']
			? intval( $atts['meal_plan_id'] )
			: get_the_ID();

		if ( ! $meal_plan_id || get_post_type( $meal_plan_id ) !== 'mmm_meal_plan' ) {
			return '<p>' . esc_html__( 'No valid meal plan found.', 'mixmymeals' ) . '</p>';
		}

		// 'person' = one serving of each meal, 'total' = everything cooked.
		$bucket = 'total' === $atts['per'] ? 'totals' : 'per_person';

		$plan   = new Mixmymeals_Meal_Plan( $meal_plan_id );
		$by_day = $plan->calc_nutrition_by_day();

		if ( empty( $by_day ) ) {
			return '<p>' . esc_html__( 'No meals found.', 'mixmymeals' ) . '</p>';
		}

		$columns = [
			'calories' => [ __( 'Calories', 'mixmymeals' ), 'kcal', 0 ],
			'protein'  => [ __( 'Protein', 'mixmymeals' ), 'g', 1 ],
			'carbs'    => [ __( 'Carbohydrates', 'mixmymeals' ), 'g', 1 ],
			'fat'      => [ __( 'Fat', 'mixmymeals' ), 'g', 1 ],
		];

		$sums = array_fill_keys( array_keys( $columns ), 0.0 );

		ob_start();
		?>
        <div class="mmm-meal-plan-nutrition-days">
            <table class="mmm-nutri-days-table">
                <thead>
                <tr>
                    <th><?php esc_html_e( 'Day', 'mixmymeals' ); ?></th>
					<?php foreach ( $columns as [ $label, $unit ] ) : ?>
                        <th><?php echo esc_html( $label ); ?></th>
					<?php endforeach; ?>
                </tr>
                </thead>
                <tbody>
				<?php foreach ( $by_day as $day => $nutrition ) : ?>
                    <tr<?php echo 0 === $nutrition['meals'] ? ' class="mmm-nutri-days-table__empty"' : ''; ?>>
                        <th scope="row"><?php echo esc_html( Mixmymeals_Meal_Plan::weekday_name( (int) $day ) ); ?></th>
						<?php
						foreach ( $columns as $key => [ $label, $unit, $decimals ] ) :
							$value          = (float) ( $nutrition[ $bucket ][ $key ] ?? 0.0 );
							$sums[ $key ]  += $value;
							?>
                            <td data-label="<?php echo esc_attr( $label ); ?>">
								<?php echo esc_html( number_format_i18n( $value, $decimals ) ); ?>
								<?php echo esc_html( $unit ); ?>
                            </td>
						<?php endforeach; ?>
                    </tr>
				<?php endforeach; ?>
                </tbody>
                <tfoot>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Total', 'mixmymeals' ); ?></th>
					<?php foreach ( $columns as $key => [ $label, $unit, $decimals ] ) : ?>
                        <td data-label="<?php echo esc_attr( $label ); ?>">
							<?php echo esc_html( number_format_i18n( $sums[ $key ], $decimals ) ); ?>
							<?php echo esc_html( $unit ); ?>
                        </td>
					<?php endforeach; ?>
                </tr>
                </tfoot>
            </table>
        </div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Shortcode: [mmm_recipe_category_terms]
	 *
	 * Listet Terms der Taxonomie mmm_recipe_category auf.
	 *
	 * Attribute:
	 * - parent="0"                 (int) Parent-Term-ID. 0 = Top-Level
	 * - depth="0"                  (int) 0 = alle Ebenen, 1 = nur diese Ebene, 2 = inkl. Kinder, ...
	 * - hide_empty="0|1"           (bool) leere Terms ausblenden
	 * - show_count="0|1"           (bool) Post-Anzahl anzeigen
	 * - orderby="name|count|slug|term_id"
	 * - order="ASC|DESC"
	 * - heading="h2"               (h2/h3/h4...) optionaler Titel-Tag je Ebene (nur wenn du es willst)
	 * - show_heading="0|1"         (bool) ob Parent-Überschrift ausgegeben wird (wenn parent != 0)
	 * - top_level_link="0|1"       (bool) ob Level-1 Terms klickbar sind (Default: 1). Wenn 0, wird nur der Titel ausgegeben.
	 */
	public function mmm_recipe_category_terms_callback( $atts ): string {

		$atts = shortcode_atts(
			[
				'parent'         => 0,
				'depth'          => 0,
				'hide_empty'     => 0,
				'show_count'     => 0,
				'orderby'        => 'name',
				'order'          => 'ASC',
				'heading'        => 'h2',
				'show_heading'   => 0,
				'top_level_link' => 1,
			],
			$atts,
			'mmm_recipe_category_terms'
		);

		$taxonomy       = 'mmm_recipe_category';
		$parent         = (int) $atts['parent'];
		$depth          = (int) $atts['depth'];
		$hide_empty     = filter_var( $atts['hide_empty'],
			FILTER_VALIDATE_BOOLEAN );
		$show_count     = filter_var( $atts['show_count'],
			FILTER_VALIDATE_BOOLEAN );
		$show_heading   = filter_var( $atts['show_heading'],
			FILTER_VALIDATE_BOOLEAN );
		$top_level_link = filter_var( $atts['top_level_link'],
			FILTER_VALIDATE_BOOLEAN );

		$orderby = sanitize_key( $atts['orderby'] );
		$order   = strtoupper( $atts['order'] ) === 'DESC' ? 'DESC' : 'ASC';

		$heading_tag = strtolower( preg_replace( '/[^a-z0-9]/', '',
			$atts['heading'] ) );
		if ( ! preg_match( '/^h[1-6]$/', $heading_tag ) ) {
			$heading_tag = 'h2';
		}

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return '';
		}

		// Helper: rekursives Rendern
		$render_terms = function ( $parent_id, $level ) use (
			&$render_terms,
			$taxonomy,
			$hide_empty,
			$show_count,
			$orderby,
			$order,
			$depth,
			$top_level_link
		) {

			$args = [
				'taxonomy'   => $taxonomy,
				'parent'     => (int) $parent_id,
				'hide_empty' => $hide_empty,
				'orderby'    => $orderby,
				'order'      => $order,
			];

			$terms = get_terms( $args );

			if ( empty( $terms ) || is_wp_error( $terms ) ) {
				return '';
			}

			ob_start();

			echo '<ul class="mmm-recipe-category mmm-term-list mmm-term-list--level-'
			     . (int) $level . '">';

			foreach ( $terms as $term ) {

				$link = get_term_link( $term );
				if ( is_wp_error( $link ) ) {
					continue;
				}

				echo '<li class="mmm-term-list__item mmm-term-list__item--term-'
				     . (int) $term->term_id . '">';

				// Level-1 optional ohne Link
				if ( (int) $level === 1 && ! $top_level_link ) {

					echo '<span class="mmm-term-list__title">';
					echo esc_html( $term->name );

					if ( $show_count ) {
						echo ' <span class="mmm-term-list__count">('
						     . number_format_i18n( (int) $term->count )
						     . ')</span>';
					}

					echo '</span>';

				} else {

					echo '<a class="mmm-term-list__link" href="'
					     . esc_url( $link ) . '">';
					echo esc_html( $term->name );

					if ( $show_count ) {
						echo ' <span class="mmm-term-list__count">('
						     . number_format_i18n( (int) $term->count )
						     . ')</span>';
					}

					echo '</a>';
				}

				// Kinder rendern, wenn depth=0 (unbegrenzt) oder level < depth
				$can_go_deeper = ( $depth === 0 ) || ( $level < $depth );
				if ( $can_go_deeper ) {
					echo $render_terms( (int) $term->term_id, $level + 1 );
				}

				echo '</li>';
			}

			echo '</ul>';

			return ob_get_clean();
		};

		ob_start();

		// Optional: Parent-Überschrift ausgeben (wenn parent gesetzt)
		if ( $show_heading && $parent > 0 ) {
			$parent_term = get_term( $parent, $taxonomy );
			if ( $parent_term && ! is_wp_error( $parent_term ) ) {
				echo '<' . $heading_tag . ' class="mmm-term-list__heading">';
				echo esc_html( $parent_term->name );
				echo '</' . $heading_tag . '>';
			}
		}

		echo $render_terms( $parent, 1 );

		return ob_get_clean();
	}

	/**
	 * Astra: Archiv-Titel für mmm_recipe_category anpassen
	 * Entfernt z.B. "Kategorie: " / "Archives: " etc.
	 */
	public function remove_recipe_category_archive_title_prefix( string $title ): string {

		if ( is_tax( 'mmm_recipe_category' ) ) {
			// Nur den reinen Term-Namen ausgeben
			$title = single_term_title( '', false );
		}

		return $title;
	}

	/**
	 * Shortcode [mmm_latest_plan_title].
	 * Returns the title of the most recently published meal plan.
	 *
	 * @return string
	 */
	public function mmm_latest_plan_title_callback(): string {
		return esc_html( Mixmymeals_Meal_Plan::get_latest_plan_title() );
	}

	/**
	 * Shortcode [mmm_random_recipe_title_from_latest_plan].
	 * Returns a random recipe title from the latest meal plan.
	 *
	 * @return string
	 */
	public function mmm_random_recipe_title_from_latest_plan_callback(): string {
		return esc_html( Mixmymeals_Meal_Plan::get_random_recipe_title_from_latest_plan() );
	}

	/**
	 * Shortcode [mmm_random_recipe_image_from_latest_plan].
	 * Returns a random recipe image from the latest meal plan.
	 *
	 * @return string
	 */
	public function mmm_random_recipe_image_from_latest_plan_callback(): string {
		return wp_kses_post( Mixmymeals_Meal_Plan::get_random_recipe_image_from_latest_plan() );
	}

	/**
	 * Renders a configurable notice/disclaimer shown on a visitor-input form
	 * (ratings, photo uploads, subscription, plan creation). Returns '' when
	 * the corresponding option is empty, so nothing shows unless configured.
	 *
	 * @param string $key Notice key: rating|photo|subscribe|plan.
	 * @return string Escaped HTML, or ''.
	 */
	public static function input_notice( string $key ): string {

		$text = trim( (string) get_option( 'mixmymeals_notice_' . $key, '' ) );
		if ( '' === $text ) {
			return '';
		}

		return '<div class="mmm-input-notice">' . wpautop( esc_html( $text ) ) . '</div>';
	}

	/**
	 * Shortcode [mmm_disclaimer_for_a_single_meal].
	 * Renders the recipe disclaimer text for a single meal.
	 *
	 * @return string
	 */
	public function mmm_disclaimer_for_a_single_meal_callback(): string {
		return '<p class="mmm-recipe-disclaimer">'
		       . esc_html( get_option( 'mixmymeals_recipe_disclaimer_text' ) )
		       . '</p>';
	}

	/**
	 * Shortcode [mmm_disclaimer_for_a_single_recipe].
	 * Renders the recipe disclaimer text for a single recipe.
	 *
	 * @return string
	 */
	public function mmm_disclaimer_for_a_single_recipe_callback(): string {
		return '<p class="mmm-recipe-disclaimer">'
		       . esc_html( get_option( 'mixmymeals_recipe_disclaimer_text' ) )
		       . '</p>';
	}

	/**
	 * Shortcode [mmm_feature_image_for_a_single_recipe].
	 * Renders the featured image for the current recipe at the specified size.
	 *
	 * @param array|string $atts Shortcode attributes: size.
	 * @return string
	 */
	public function mmm_feature_image_for_a_single_recipe_callback( $atts ): string {

		$atts = shortcode_atts(
			[
				'size' => 'large',
			],
			$atts,
			'mmm_recipe_category_terms'
		);


		if ( is_singular( 'mmm_recipe' ) ) {

			// Allow these pre-defined values for size only:
			$allowed_sizes = array(
				'thumbnail',
				'medium',
				'medium_large',
				'large',
				'full'
			);
			$size          = 'large';
			if ( in_array( $atts['size'], $allowed_sizes ) ) {
				$size = $atts['size'];
			}

			if ( has_post_thumbnail() ) {
				ob_start();
				the_post_thumbnail( $size );

				return ob_get_clean();
			}

		}

		return '';

	}

	/**
	 * Shortcode [Ich Ich]: shows the average stars and rating count for
	 * the current (or given) recipe. The rating data and star markup are owned by
	 * Mixmymeals_Ratings; this shortcode only renders them.
	 *
	 * @param array|string $atts Optional: id (force a recipe), hide_empty (return
	 *                           nothing when the recipe has no ratings yet).
	 * @return string
	 */
	public function mmm_recipe_rating_callback( $atts = array() ): string {

		$atts = shortcode_atts(
			array(
				'id'         => 0,
				'hide_empty' => 0,
			),
			$atts,
			'mmm_recipe_rating'
		);

		$post_id = (int) $atts['id'] > 0 ? (int) $atts['id'] : (int) get_the_ID();

		if ( ! $post_id || get_post_type( $post_id ) !== Mixmymeals_Ratings::POST_TYPE ) {
			return '';
		}

		$count = (int) get_post_meta( $post_id, Mixmymeals_Ratings::META_COUNT, true );
		if ( $count < 1 ) {
			if ( ! empty( $atts['hide_empty'] ) && 'false' !== $atts['hide_empty'] && '0' !== (string) $atts['hide_empty'] ) {
				return '';
			}
			return '<div class="mmm-rating mmm-rating--empty">'
				. esc_html__( 'No ratings yet.', 'mixmymeals' ) . '</div>';
		}

		$avg   = (float) get_post_meta( $post_id, Mixmymeals_Ratings::META_AVG, true );
		$label = sprintf(
			/* translators: %s: number of ratings */
			_n( '%s rating', '%s ratings', $count, 'mixmymeals' ),
			number_format_i18n( $count )
		);
		$aria = sprintf(
			/* translators: %s: average rating (0–5) */
			esc_attr__( '%s out of 5 stars', 'mixmymeals' ),
			number_format_i18n( $avg, 1 )
		);

		return '<div class="mmm-rating" role="img" aria-label="' . esc_attr( $aria ) . '">'
			. Mixmymeals_Ratings::stars_html( $avg )
			. '<span class="mmm-rating__avg">' . esc_html( number_format_i18n( $avg, 1 ) ) . '</span>'
			. '<span class="mmm-rating__count">' . esc_html( $label ) . '</span>'
			. '</div>';
	}

	/**
	 * Shortcode [mmm_recipe_reviews]: a clean list of the published reviews for
	 * the current (or given) recipe — stars, author and date — rendered so it
	 * reads as a reviews list rather than a blog comment thread.
	 *
	 * @param array|string $atts Optional: id to force a specific recipe.
	 * @return string
	 */
	public function mmm_recipe_reviews_callback( $atts = array() ): string {

		$atts    = shortcode_atts( array( 'id' => 0 ), $atts, 'mmm_recipe_reviews' );
		$post_id = (int) $atts['id'] > 0 ? (int) $atts['id'] : (int) get_the_ID();

		if ( ! $post_id || get_post_type( $post_id ) !== Mixmymeals_Ratings::POST_TYPE ) {
			return '';
		}

		$comments = get_comments(
			array(
				'post_id'    => $post_id,
				'status'     => 'approve',
				'order'      => 'DESC',
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => Mixmymeals_Ratings::META_RATING,
						'compare' => 'EXISTS',
					),
				),
			)
		);

		if ( empty( $comments ) ) {
			return '';
		}

		ob_start();
		echo '<ol class="mmm-reviews">';
		foreach ( $comments as $comment ) {
			$rating = (int) get_comment_meta( $comment->comment_ID, Mixmymeals_Ratings::META_RATING, true );
			?>
			<li class="mmm-review">
				<div class="mmm-review__head">
					<?php if ( $rating >= 1 && $rating <= 5 ) : ?>
						<span class="mmm-rating"><?php echo Mixmymeals_Ratings::stars_html( (float) $rating ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<?php endif; ?>
					<span class="mmm-review__author"><?php echo esc_html( get_comment_author( $comment ) ); ?></span>
					<time class="mmm-review__date" datetime="<?php echo esc_attr( get_comment_date( 'c', $comment ) ); ?>"><?php echo esc_html( get_comment_date( '', $comment ) ); ?></time>
				</div>
				<div class="mmm-review__text"><?php echo wp_kses_post( wpautop( get_comment_text( $comment ) ) ); ?></div>
			</li>
			<?php
		}
		echo '</ol>';

		return (string) ob_get_clean();
	}

	/**
	 * Shortcode [mmm_recipe_rating_form]: lets an e-mail subscriber rate a recipe
	 * from their plan via a tokenised link (?token=…&recipe=…) AND upload a photo
	 * of the cooked meal in the same submit (the photo is optional). Place it on
	 * a normal page and point the "Recipe Rating Page" setting at it.
	 *
	 * One rating per recipe per subscription; photos up to the per-uploader
	 * limit. The rating is saved before the photo is processed so a failed
	 * upload never loses the rating. A subscriber who already rated (or who
	 * rated without a photo) gets a photo-only form as long as the photo limit
	 * is not reached.
	 *
	 * @param array|string $atts Unused.
	 * @return string
	 */
	public function mmm_recipe_rating_form_callback( $atts = array() ): string {

		if ( ! class_exists( 'Mixmymeals_Subscriptions' ) || ! class_exists( 'Mixmymeals_Ratings' ) ) {
			return '';
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- token-authenticated public link; the POST additionally carries a nonce.
		$token     = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		$recipe_id = isset( $_GET['recipe'] ) ? absint( wp_unslash( $_GET['recipe'] ) ) : 0;
		$is_post   = isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === strtoupper( (string) wp_unslash( $_SERVER['REQUEST_METHOD'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( '' === $token || ! $recipe_id ) {
			return $this->rating_form_notice( __( 'This link is invalid or has expired.', 'mixmymeals' ) );
		}

		$sub_id = Mixmymeals_Subscriptions::find_subscription_by_token( $token );
		if ( ! $sub_id || ! Mixmymeals_Subscriptions::subscription_is_active( $sub_id ) ) {
			return $this->rating_form_notice( __( 'This link is invalid or has expired.', 'mixmymeals' ) );
		}

		if ( get_post_type( $recipe_id ) !== 'mmm_recipe'
			|| ! Mixmymeals_Subscriptions::subscription_has_recipe( $sub_id, $recipe_id ) ) {
			return $this->rating_form_notice( __( 'This recipe is not part of your meal plan.', 'mixmymeals' ) );
		}

		$has_rated = Mixmymeals_Ratings::subscriber_has_rated( $recipe_id, $sub_id );
		$can_photo = class_exists( 'Mixmymeals_Photos' )
			&& Mixmymeals_Photos::count_by_uploader( $recipe_id, $sub_id, 0 ) < Mixmymeals_Photos::upload_limit();

		if ( $has_rated && ! $can_photo ) {
			return $this->rating_form_notice( __( 'You have already rated this recipe.', 'mixmymeals' ), true );
		}

		$notices     = '';
		$error       = '';
		$photo_saved = false;
		$photo_given = false;

		if ( $is_post && isset( $_POST['mmm_sub_rate_nonce'] ) ) {

			$nonce = sanitize_text_field( wp_unslash( $_POST['mmm_sub_rate_nonce'] ) );
			if ( ! wp_verify_nonce( $nonce, 'mmm_sub_rate' ) ) {
				return $this->rating_form_notice( __( 'Your session has expired. Please reload the page.', 'mixmymeals' ) );
			}

			// Rating first: it must never be lost to a failed photo upload.
			if ( ! $has_rated ) {
				$stars = isset( $_POST['mmm_rating'] ) ? absint( wp_unslash( $_POST['mmm_rating'] ) ) : 0;
				if ( $stars >= 1 && $stars <= 5 ) {
					list( $name, $email ) = Mixmymeals_Subscriptions::subscriber_identity( $sub_id );
					Mixmymeals_Ratings::add_subscriber_rating( $recipe_id, $sub_id, $stars, $name, $email );
					$has_rated = true;
					$notices  .= $this->rating_form_notice( __( 'Thank you! Your rating has been saved.', 'mixmymeals' ), true );
				} else {
					$error = __( 'Please select a rating.', 'mixmymeals' );
				}
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- nonce verified above; the file itself is validated in add_photo().
			$photo_given = $can_photo && ! empty( $_FILES['mmm_photo']['name'] );
			if ( $photo_given ) {

				list( $name, ) = Mixmymeals_Subscriptions::subscriber_identity( $sub_id );
				$result        = Mixmymeals_Photos::add_photo( $recipe_id, 'mmm_photo', $name, $sub_id, 0 );

				if ( ! is_wp_error( $result ) ) {
					$photo_saved = true;
					$notices    .= $this->rating_form_notice(
						Mixmymeals_Photos::is_auto_approved()
							? __( 'Thank you! Your photo is now in the recipe gallery.', 'mixmymeals' )
							: __( 'Thank you! Your photo will appear in the recipe gallery once it has been approved.', 'mixmymeals' ),
						true
					);
				} else {
					$error = trim( $error . ' ' . $result->get_error_message() );
				}
			}
		}

		// Everything collected (or nothing left to ask for): just say thanks.
		if ( $is_post && '' === $error && $has_rated && ( $photo_saved || ! $can_photo ) ) {
			return $notices;
		}

		// Rated without a photo: thank for the rating, gently ask for a photo.
		if ( $is_post && '' === $error && $has_rated && ! $photo_given ) {
			return $notices . $this->render_subscriber_rating_form(
				$token,
				$recipe_id,
				'',
				false,
				true,
				__( 'Would you like to add a photo of your meal?', 'mixmymeals' )
			);
		}

		$intro = '';
		if ( $has_rated ) {
			$intro = __( 'You have already rated this recipe — would you like to add a photo of your meal?', 'mixmymeals' );
		}

		return $notices . $this->render_subscriber_rating_form(
			$token,
			$recipe_id,
			$error,
			! $has_rated,
			$can_photo && ! $photo_saved,
			$intro
		);
	}

	/**
	 * Wraps a short status message for the rating-form and photo-upload
	 * shortcodes.
	 *
	 * @param string $message  Message text.
	 * @param bool   $ok       Whether it is a success message.
	 * @param bool   $is_html  Whether the message is pre-escaped HTML (links).
	 * @return string
	 */
	private function rating_form_notice( string $message, bool $ok = false, bool $is_html = false ): string {
		return '<div class="mmm-rating-notice ' . ( $ok ? 'mmm-rating-notice--ok' : 'mmm-rating-notice--error' ) . '">'
			. ( $is_html ? wp_kses( $message, array( 'a' => array( 'href' => array() ) ) ) : esc_html( $message ) ) . '</div>';
	}

	/**
	 * Builds the subscriber rating/photo form for one recipe (reuses the comment
	 * form's star-widget classes so the theme styles it). Depending on what is
	 * still missing it shows the stars, the photo field or both in one form —
	 * one submit collects everything.
	 *
	 * @param string $token       Subscription token (kept in the form action URL).
	 * @param int    $recipe_id   Recipe post ID.
	 * @param string $error       Optional error message.
	 * @param bool   $with_rating Whether to show the star input.
	 * @param bool   $with_photo  Whether to show the photo upload field.
	 * @param string $intro       Optional intro line replacing the default one.
	 * @return string
	 */
	private function render_subscriber_rating_form( string $token, int $recipe_id, string $error = '', bool $with_rating = true, bool $with_photo = false, string $intro = '' ): string {

		$action = esc_url(
			add_query_arg(
				array(
					'token'  => $token,
					'recipe' => $recipe_id,
				),
				get_permalink()
			)
		);

		if ( '' === $intro ) {
			$intro = $with_rating
				? __( 'How would you rate this recipe?', 'mixmymeals' )
				: __( 'Would you like to add a photo of your meal?', 'mixmymeals' );
		}

		ob_start();
		?>
		<form class="mmm-subscriber-rating" method="post"<?php echo $with_photo ? ' enctype="multipart/form-data"' : ''; ?> action="<?php echo $action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped via esc_url() above. ?>">
			<h3 class="mmm-subscriber-rating__title"><?php echo esc_html( get_the_title( $recipe_id ) ); ?></h3>
			<p><?php echo esc_html( $intro ); ?></p>
			<?php if ( '' !== $error ) : ?>
				<p class="mmm-rating-notice mmm-rating-notice--error"><?php echo esc_html( $error ); ?></p>
			<?php endif; ?>
			<?php if ( $with_rating ) : ?>
				<p class="comment-form-rating mmm-rating-field">
					<span class="mmm-rating-input">
						<?php
						for ( $i = 5; $i >= 1; $i-- ) {
							$field_id = 'mmm-sub-rate-' . $i;
							$label    = sprintf(
								/* translators: %s: number of stars */
								_n( '%s star', '%s stars', $i, 'mixmymeals' ),
								number_format_i18n( $i )
							);
							printf(
								'<input type="radio" name="mmm_rating" id="%1$s" value="%2$d" required><label for="%1$s" title="%3$s"><i class="mmm-icon fa-solid fa-star" aria-hidden="true"></i><span class="screen-reader-text">%3$s</span></label>',
								esc_attr( $field_id ),
								(int) $i,
								esc_attr( $label )
							);
						}
						?>
					</span>
				</p>
			<?php endif; ?>
			<?php if ( $with_photo ) : ?>
				<p class="mmm-photo-upload__field">
					<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo esc_attr( (string) Mixmymeals_Photos::max_bytes() ); ?>">
					<label class="mmm-photo-upload__label" for="mmm-photo">
						<i class="mmm-icon fa-solid fa-camera" aria-hidden="true"></i>
						<span>
							<?php
							echo esc_html(
								$with_rating
									? __( 'Your photo of this meal (optional)', 'mixmymeals' )
									: __( 'Choose photo or take one', 'mixmymeals' )
							);
							?>
						</span>
					</label>
					<input type="file" id="mmm-photo" name="mmm_photo" accept="image/jpeg,image/png,image/webp"<?php echo $with_rating ? '' : ' required'; ?>>
				</p>
				<p class="mmm-photo-upload__hint">
					<?php
					printf(
						/* translators: %s: maximum file size, e.g. "10 MB" */
						esc_html__( 'JPG, PNG or WebP, up to %s.', 'mixmymeals' ),
						esc_html( size_format( Mixmymeals_Photos::max_bytes() ) )
					);
					?>
				</p>
			<?php endif; ?>
			<?php
				if ( $with_rating ) {
					echo self::input_notice( 'rating' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
				}
				if ( $with_photo ) {
					echo self::input_notice( 'photo' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
				}
				wp_nonce_field( 'mmm_sub_rate', 'mmm_sub_rate_nonce' );
				?>
			<button type="submit" class="mmm-rating-submit">
				<?php echo esc_html( $with_rating ? __( 'Submit rating', 'mixmymeals' ) : __( 'Upload photo', 'mixmymeals' ) ); ?>
			</button>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Shortcode [mmm_photo_upload_form]: lets a visitor upload a photo of a
	 * cooked meal for one recipe. Place it on a normal page and point the
	 * "Photo Upload Page" setting at it. Two ways in:
	 *  - tokenised link from a subscription mail (?token=…&recipe=…), limited
	 *    to recipes from the subscriber's plan, or
	 *  - a logged-in user with just ?recipe=… (e.g. via the gallery button).
	 *
	 * Photos await admin approval unless the auto-approve option is on.
	 *
	 * @param array|string $atts Unused.
	 * @return string
	 */
	public function mmm_photo_upload_form_callback( $atts = array() ): string {

		if ( ! class_exists( 'Mixmymeals_Photos' ) ) {
			return '';
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- token-authenticated public link; the POST additionally carries a nonce.
		$token     = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		$recipe_id = isset( $_GET['recipe'] ) ? absint( wp_unslash( $_GET['recipe'] ) ) : 0;
		$is_post   = isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === strtoupper( (string) wp_unslash( $_SERVER['REQUEST_METHOD'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! $recipe_id || get_post_type( $recipe_id ) !== 'mmm_recipe' ) {
			return $this->rating_form_notice( __( 'This link is invalid or has expired.', 'mixmymeals' ) );
		}

		$sub_id  = 0;
		$user_id = 0;
		$by_name = '';

		if ( '' !== $token && class_exists( 'Mixmymeals_Subscriptions' ) ) {

			$sub_id = Mixmymeals_Subscriptions::find_subscription_by_token( $token );
			if ( ! $sub_id || ! Mixmymeals_Subscriptions::subscription_is_active( $sub_id ) ) {
				return $this->rating_form_notice( __( 'This link is invalid or has expired.', 'mixmymeals' ) );
			}

			if ( ! Mixmymeals_Subscriptions::subscription_has_recipe( $sub_id, $recipe_id ) ) {
				return $this->rating_form_notice( __( 'This recipe is not part of your meal plan.', 'mixmymeals' ) );
			}

			list( $by_name, ) = Mixmymeals_Subscriptions::subscriber_identity( $sub_id );

		} elseif ( is_user_logged_in() ) {

			$user_id = get_current_user_id();
			$by_name = wp_get_current_user()->display_name;

		} else {

			return $this->rating_form_notice(
				sprintf(
					wp_kses(
						/* translators: %s: log-in URL */
						__( 'You need to be <a href="%s">logged in</a> to upload a photo.', 'mixmymeals' ),
						array( 'a' => array( 'href' => array() ) )
					),
					esc_url( wp_login_url( (string) add_query_arg( 'recipe', $recipe_id, get_permalink() ) ) )
				),
				false,
				true
			);
		}

		if ( Mixmymeals_Photos::count_by_uploader( $recipe_id, $sub_id, $user_id ) >= Mixmymeals_Photos::upload_limit() ) {
			return $this->rating_form_notice( __( 'You have already uploaded the maximum number of photos for this recipe. Thank you!', 'mixmymeals' ), true );
		}

		$error = '';
		if ( $is_post && isset( $_FILES['mmm_photo'] ) ) {

			$nonce = isset( $_POST['mmm_photo_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['mmm_photo_nonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'mmm_photo_upload' ) ) {
				return $this->rating_form_notice( __( 'Your session has expired. Please reload the page.', 'mixmymeals' ) );
			}

			$result = Mixmymeals_Photos::add_photo( $recipe_id, 'mmm_photo', $by_name, $sub_id, $user_id );

			if ( ! is_wp_error( $result ) ) {
				return $this->rating_form_notice(
					Mixmymeals_Photos::is_auto_approved()
						? __( 'Thank you! Your photo is now in the recipe gallery.', 'mixmymeals' )
						: __( 'Thank you! Your photo will appear in the recipe gallery once it has been approved.', 'mixmymeals' ),
					true
				);
			}

			$error = $result->get_error_message();
		}

		return $this->render_photo_upload_form( $token, $recipe_id, $error );
	}

	/**
	 * Builds the photo upload form for one recipe.
	 *
	 * @param string $token     Subscription token ('' for logged-in uploads).
	 * @param int    $recipe_id Recipe post ID.
	 * @param string $error     Optional error message.
	 * @return string
	 */
	private function render_photo_upload_form( string $token, int $recipe_id, string $error = '' ): string {

		$args = array( 'recipe' => $recipe_id );
		if ( '' !== $token ) {
			$args['token'] = $token;
		}
		$action = esc_url( add_query_arg( $args, get_permalink() ) );

		$max_bytes = Mixmymeals_Photos::max_bytes();

		ob_start();
		?>
		<form class="mmm-photo-upload" method="post" enctype="multipart/form-data" action="<?php echo $action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped via esc_url() above. ?>">
			<h3 class="mmm-photo-upload__title"><?php echo esc_html( get_the_title( $recipe_id ) ); ?></h3>
			<?php if ( has_post_thumbnail( $recipe_id ) ) : ?>
				<div class="mmm-photo-upload__recipe-image"><?php echo get_the_post_thumbnail( $recipe_id, 'medium' ); ?></div>
			<?php endif; ?>
			<p><?php esc_html_e( 'You cooked this? Show us! Upload a photo of your meal.', 'mixmymeals' ); ?></p>
			<?php if ( '' !== $error ) : ?>
				<p class="mmm-rating-notice mmm-rating-notice--error"><?php echo esc_html( $error ); ?></p>
			<?php endif; ?>
			<p class="mmm-photo-upload__field">
				<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo esc_attr( (string) $max_bytes ); ?>">
				<label class="mmm-photo-upload__label" for="mmm-photo">
					<i class="mmm-icon fa-solid fa-camera" aria-hidden="true"></i>
					<span><?php esc_html_e( 'Choose photo or take one', 'mixmymeals' ); ?></span>
				</label>
				<input type="file" id="mmm-photo" name="mmm_photo" accept="image/jpeg,image/png,image/webp" required>
			</p>
			<p class="mmm-photo-upload__hint">
				<?php
				printf(
					/* translators: %s: maximum file size, e.g. "10 MB" */
					esc_html__( 'JPG, PNG or WebP, up to %s.', 'mixmymeals' ),
					esc_html( size_format( $max_bytes ) )
				);
				?>
			</p>
			<?php
			echo self::input_notice( 'photo' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
			wp_nonce_field( 'mmm_photo_upload', 'mmm_photo_nonce' );
			?>
			<button type="submit" class="mmm-rating-submit"><?php esc_html_e( 'Upload photo', 'mixmymeals' ); ?></button>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Shortcode [mmm_recipe_photo_gallery]: the approved visitor photos of a
	 * recipe as a thumbnail grid, plus an upload button when the "Photo Upload
	 * Page" setting is configured. Self-contained: uses the current recipe on
	 * a single recipe page, or an explicit recipe="123" attribute elsewhere.
	 *
	 * @param array|string $atts Shortcode attributes: recipe, size.
	 * @return string
	 */
	public function mmm_recipe_photo_gallery_callback( $atts = array() ): string {

		if ( ! class_exists( 'Mixmymeals_Photos' ) ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'recipe' => 0,
				'size'   => 'medium',
			),
			$atts,
			'mmm_recipe_photo_gallery'
		);

		$recipe_id = absint( $atts['recipe'] );
		if ( ! $recipe_id && is_singular( 'mmm_recipe' ) ) {
			$recipe_id = (int) get_the_ID();
		}

		if ( ! $recipe_id || get_post_type( $recipe_id ) !== 'mmm_recipe' ) {
			return '';
		}

		$allowed_sizes = array( 'thumbnail', 'medium', 'medium_large', 'large', 'full' );
		$size          = in_array( $atts['size'], $allowed_sizes, true ) ? $atts['size'] : 'medium';

		$photos = Mixmymeals_Photos::get_photos( $recipe_id, true );

		$upload_url = '';
		if ( class_exists( 'Mixmymeals_Subscriptions' ) ) {
			$upload_page = (int) get_option( Mixmymeals_Subscriptions::OPT_UPLOAD_PAGE, 0 );
			if ( $upload_page > 0 && 'publish' === get_post_status( $upload_page ) ) {
				$upload_url = (string) add_query_arg( 'recipe', $recipe_id, (string) get_permalink( $upload_page ) );
			}
		}

		if ( empty( $photos ) && '' === $upload_url ) {
			return '';
		}

		ob_start();
		?>
		<div class="mmm-photo-gallery">
			<?php if ( ! empty( $photos ) ) : ?>
				<ul class="mmm-photo-gallery__grid">
					<?php foreach ( $photos as $photo_id ) : ?>
						<li class="mmm-photo-gallery__item">
							<a href="<?php echo esc_url( (string) wp_get_attachment_url( $photo_id ) ); ?>" target="_blank" rel="noopener">
								<?php echo wp_get_attachment_image( $photo_id, $size, false, array( 'loading' => 'lazy' ) ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="mmm-photo-gallery__empty"><?php esc_html_e( 'No photos yet — be the first to show your version of this meal!', 'mixmymeals' ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $upload_url ) : ?>
				<p class="mmm-photo-gallery__upload">
					<a class="mmm-photo-gallery__upload-link" href="<?php echo esc_url( $upload_url ); ?>">
						<i class="mmm-icon fa-solid fa-camera" aria-hidden="true"></i>
						<?php esc_html_e( 'Upload your photo', 'mixmymeals' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Shortcode [mmm_feature_image_for_a_single_meal].
	 * Renders the featured image of the recipe linked to the current meal.
	 *
	 * @param array|string $atts Shortcode attributes: size.
	 * @return string
	 */
	public function mmm_feature_image_for_a_single_meal_callback( $atts ): string {

		$atts = shortcode_atts(
			[
				'size' => 'large',
			],
			$atts,
			'mmm_recipe_category_terms'
		);


		if ( is_singular( 'mmm_meal' ) ) {

			// Allow these pre-defined values for size only:
			$allowed_sizes = array(
				'thumbnail',
				'medium',
				'medium_large',
				'large',
				'full'
			);
			$size          = 'large';
			if ( in_array( $atts['size'], $allowed_sizes ) ) {
				$size = $atts['size'];
			}

			$meal_ID   = get_the_ID();
			$meal_obj  = new Mixmymeals_Meal( $meal_ID );
			$recipe_ID = $meal_obj->get_recipe_id();

			if ( has_post_thumbnail( $recipe_ID ) ) {
				ob_start();
				echo get_the_post_thumbnail( $recipe_ID, $size );

				return ob_get_clean();
			}

		}

		return '';

	}

	/**
	 * Shortcode [mmm_meal_plan_category_switcher].
	 * Renders a tab-based category filter for the meal plan archive.
	 *
	 * @return string
	 */
	public function mmm_meal_plan_category_switcher_callback(): string {

		if ( ! is_post_type_archive( 'mmm_meal_plan' )
		     && ! is_tax( 'mmm_meal_plan_category' ) ) {
			return '';
		}

		$terms = get_terms( [
			'taxonomy'   => 'mmm_meal_plan_category',
			'hide_empty' => true
		] );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return '';
		}

		$current = '';
		if ( is_tax( 'mmm_meal_plan_category' ) ) {
			$current = get_queried_object()->slug;
		} else {
			$current = get_query_var( 'mmm_meal_plan_category' );
		}

		$output = '<div class="meal-plan-switcher">';

		$archive_url      = get_post_type_archive_link( 'mmm_meal_plan' );
		$is_uncategorized = (bool) get_query_var( 'mmm_uncategorized' );
		$is_archive       = is_post_type_archive( 'mmm_meal_plan' );

		// Link: Alle (nur kategorisierte, default)
		$active  = ( $is_archive && ! is_tax( 'mmm_meal_plan_category' ) && ! $is_uncategorized ) ? 'active' : '';
		$output .= '<a class="' . esc_attr( $active ) . '" href="' . esc_url( $archive_url )
		           . '">' . esc_html__( 'All', 'mixmymeals' ) . '</a>';

		foreach ( $terms as $term ) {
			$url = get_term_link( $term );

			$active = ( $current === $term->slug ) ? 'active' : '';

			$output .= '<a class="' . $active . '" href="' . $url . '">'
			           . $term->name . '</a>';
		}

		$active           = ( $is_archive && $is_uncategorized ) ? 'active' : '';
		$individual_label = get_option( 'mixmymeals_individual_tab_label' )
			?: __( 'Individual', 'mixmymeals' );
		$output .= '<a class="meal-plan-switcher__individual ' . esc_attr( $active ) . '" href="' . esc_url( add_query_arg( 'mmm_uncategorized', '1', $archive_url ) )
		           . '">' . esc_html( $individual_label ) . '</a>';

		$output .= '</div>';

		return $output;
	}

	/**
	 * Modifies the meal plan archive query to filter by the mmm_uncategorized parameter
	 * or to show only plans without a category when that flag is set.
	 *
	 * @param WP_Query $query Current main query.
	 * @return void
	 */
	public function filter_meal_plan_archive_by_url_parameter( WP_Query $query ): void {

		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( ! is_post_type_archive( 'mmm_meal_plan' ) ) {
			return;
		}

		$term = get_query_var( 'mmm_meal_plan_category' );
		if ( $term ) {
			$query->set( 'tax_query', [
				[
					'taxonomy' => 'mmm_meal_plan_category',
					'field'    => 'slug',
					'terms'    => $term,
				]
			] );
		} elseif ( get_query_var( 'mmm_uncategorized' ) ) {
			$query->set( 'tax_query', [
				[
					'taxonomy' => 'mmm_meal_plan_category',
					'operator' => 'NOT EXISTS',
				]
			] );
		} else {
			$query->set( 'tax_query', [
				[
					'taxonomy' => 'mmm_meal_plan_category',
					'operator' => 'EXISTS',
				]
			] );
		}
	}

	/**
	 * Post types whose archives carry the search form.
	 *
	 * @return string[]
	 */
	private static function archive_search_post_types(): array {
		return array( 'mmm_recipe', 'mmm_meal_plan' );
	}

	/**
	 * Determines the searchable post type of the archive currently being viewed
	 * (post type archive or one of its taxonomy archives), or '' if none.
	 *
	 * @return string
	 */
	private function current_archive_post_type(): string {

		if ( is_post_type_archive( self::archive_search_post_types() ) ) {
			$pt = get_query_var( 'post_type' );
			return is_array( $pt ) ? (string) reset( $pt ) : (string) $pt;
		}

		if ( is_tax( array( 'mmm_recipe_category', 'mmm_recipe_tag' ) ) ) {
			return 'mmm_recipe';
		}

		if ( is_tax( array( 'mmm_meal_plan_category', 'mmm_meal_plan_tag' ) ) ) {
			return 'mmm_meal_plan';
		}

		return '';
	}

	/**
	 * Shortcode [mmm_archive_search]. Renders a GET title-search form for the
	 * current recipe or meal plan archive. The form submits to the same archive
	 * URL; results are scoped by apply_archive_search() / archive_search_title_where().
	 *
	 * @param array|string $atts Optional: post_type to force a specific archive.
	 * @return string
	 */
	public function mmm_archive_search_callback( $atts = array() ): string {

		$atts = shortcode_atts( array( 'post_type' => '' ), $atts, 'mmm_archive_search' );

		$post_type = '' !== $atts['post_type']
			? sanitize_key( $atts['post_type'] )
			: $this->current_archive_post_type();

		if ( ! in_array( $post_type, self::archive_search_post_types(), true ) ) {
			return '';
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only public GET filter.
		$cur_s = isset( $_GET['mmm_s'] ) ? sanitize_text_field( wp_unslash( $_GET['mmm_s'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$reset = is_post_type_archive()
			? get_post_type_archive_link( $post_type )
			: get_term_link( get_queried_object() );

		ob_start();
		?>
		<form class="mmm-archive-search" method="get" role="search">
			<input type="search" name="mmm_s" class="mmm-archive-search__text" value="<?php echo esc_attr( $cur_s ); ?>" placeholder="<?php esc_attr_e( 'Search by title…', 'mixmymeals' ); ?>">
			<button type="submit" class="mmm-archive-search__submit"><?php esc_html_e( 'Search', 'mixmymeals' ); ?></button>
			<?php if ( '' !== $cur_s && $reset && ! is_wp_error( $reset ) ) : ?>
				<a class="mmm-archive-search__reset" href="<?php echo esc_url( $reset ); ?>"><?php esc_html_e( 'Reset', 'mixmymeals' ); ?></a>
			<?php endif; ?>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Stashes the archive title search term for archive_search_title_where(),
	 * but only on the recipe / meal plan archives (and their taxonomy archives).
	 *
	 * @param WP_Query $query Query.
	 * @return void
	 */
	public function apply_archive_search( $query ): void {

		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$on_archive = $query->is_post_type_archive( self::archive_search_post_types() )
			|| $query->is_tax( array( 'mmm_recipe_category', 'mmm_recipe_tag', 'mmm_meal_plan_category', 'mmm_meal_plan_tag' ) );

		if ( ! $on_archive ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only public GET filter.
		$s = isset( $_GET['mmm_s'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['mmm_s'] ) ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( '' !== $s ) {
			$query->set( 'mmm_title_like', $s );
		}
	}

	/**
	 * Adds a title-only LIKE clause for the archive search, keeping the query a
	 * post type / taxonomy archive (unlike WP's "s", which switches templates).
	 *
	 * @param string   $where Current WHERE clause.
	 * @param WP_Query $query Query.
	 * @return string
	 */
	public function archive_search_title_where( $where, $query ): string {

		if ( ! $query instanceof WP_Query || ! $query->is_main_query() ) {
			return $where;
		}

		$term = $query->get( 'mmm_title_like' );
		if ( ! is_string( $term ) || '' === $term ) {
			return $where;
		}

		global $wpdb;
		$where .= $wpdb->prepare(
			" AND {$wpdb->posts}.post_title LIKE %s",
			'%' . $wpdb->esc_like( $term ) . '%'
		);

		return $where;
	}

	/**
	 * Shortcode [mmm_ingredient_hero]. Public single-ingredient content: title and
	 * category/diet/calorie/price chips. The nutrition table
	 * ([mmm_nutrition_table_for_a_single_ingredient]) and the recipes using this
	 * ingredient ([mmm_recipes_for_a_single_ingredient]) are separate shortcodes,
	 * matching recipes/meals.
	 *
	 * @param array|string $atts Optional: id to force a specific ingredient.
	 * @return string
	 */
	public function mmm_ingredient_hero_callback( $atts = array() ): string {

		$atts          = shortcode_atts( array( 'id' => 0 ), $atts, 'mmm_ingredient_hero' );
		$ingredient_id = (int) $atts['id'] > 0 ? (int) $atts['id'] : (int) get_the_ID();

		if ( ! $ingredient_id || get_post_type( $ingredient_id ) !== 'mmm_ingredient' ) {
			return '';
		}

		$ingredient = new Mixmymeals_Ingredient( $ingredient_id );
		if ( ! $ingredient->is_valid() ) {
			return '';
		}

		$title    = get_the_title( $ingredient_id );
		$currency = (string) get_option( 'mixmymeals_currency', '€' );
		$nutri    = $ingredient->get_nutrition();
		$price    = (float) $ingredient->get_price_per_100g();
		$diet     = $ingredient->get_diet_type();
		$cats     = $ingredient->get_categories();
		$calories = isset( $nutri['calories_100'] ) ? (float) $nutri['calories_100'] : 0.0;

		$diet_label = '';
		if ( 'vegan' === $diet ) {
			$diet_label = __( 'Vegan', 'mixmymeals' );
		} elseif ( 'vegetarian' === $diet ) {
			$diet_label = __( 'Vegetarian', 'mixmymeals' );
		}

		ob_start();
		?>
		<section class="mmm-plan-hero mmm-ingredient-hero">
			<div class="mmm-plan-hero__blob"></div>
			<h1><?php echo esc_html( $title ); ?></h1>

			<div class="mmm-plan-hero__meta">
				<?php foreach ( (array) $cats as $cat ) : ?>
					<span class="mmm-chip mmm-chip--stat"><i class="mmm-icon fa-solid fa-folder" aria-hidden="true"></i><?php echo esc_html( $cat->name ); ?></span>
				<?php endforeach; ?>
				<?php if ( '' !== $diet_label ) : ?>
					<span class="mmm-chip mmm-chip--stat"><i class="mmm-icon fa-solid fa-leaf" aria-hidden="true"></i><?php echo esc_html( $diet_label ); ?></span>
				<?php endif; ?>
				<?php if ( $calories > 0 ) : ?>
					<span class="mmm-chip mmm-chip--stat"><i class="mmm-icon fa-solid fa-fire" aria-hidden="true"></i><?php
						/* translators: %s: calories per 100 g */
						printf( esc_html__( '%s kcal / 100 g', 'mixmymeals' ), esc_html( number_format_i18n( round( $calories ) ) ) );
					?></span>
				<?php endif; ?>
				<?php if ( $price > 0 ) : ?>
					<span class="mmm-chip mmm-chip--stat mmm-chip--price"><i class="mmm-icon fa-solid fa-tag" aria-hidden="true"></i><?php
						/* translators: 1: price, 2: currency symbol */
						printf( esc_html__( '%1$s %2$s / 100 g', 'mixmymeals' ), esc_html( number_format_i18n( $price, 2 ) ), esc_html( $currency ) );
					?></span>
				<?php endif; ?>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Shortcode [mmm_recipes_for_a_single_ingredient]. Lists the published recipes
	 * that use the current (or given) ingredient as a simple list of links.
	 *
	 * @param array|string $atts Optional: id to force a specific ingredient.
	 * @return string
	 */
	public function mmm_recipes_for_a_single_ingredient_callback( $atts = array() ): string {

		$atts          = shortcode_atts( array( 'id' => 0 ), $atts, 'mmm_recipes_for_a_single_ingredient' );
		$ingredient_id = (int) $atts['id'] > 0 ? (int) $atts['id'] : (int) get_the_ID();

		if ( ! $ingredient_id || get_post_type( $ingredient_id ) !== 'mmm_ingredient' ) {
			return '';
		}

		$recipes = $this->recipes_using_ingredient( $ingredient_id );
		if ( empty( $recipes ) ) {
			return '';
		}

		ob_start();
		?>
		<ul class="mmm-ingredient-recipes">
			<?php foreach ( $recipes as $rid ) : ?>
				<li><a href="<?php echo esc_url( (string) get_permalink( $rid ) ); ?>"><?php echo esc_html( get_the_title( $rid ) ); ?></a></li>
			<?php endforeach; ?>
		</ul>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Shortcode [mmm_nutrition_table_for_a_single_ingredient]. Renders the
	 * ingredient's per-100 g nutrition table, mirroring the recipe/meal nutrition
	 * shortcodes.
	 *
	 * @param array|string $atts Optional: id to force a specific ingredient.
	 * @return string
	 */
	public function mmm_nutrition_table_for_a_single_ingredient_callback( $atts = array() ): string {

		$atts          = shortcode_atts( array( 'id' => 0 ), $atts, 'mmm_nutrition_table_for_a_single_ingredient' );
		$ingredient_id = (int) $atts['id'] > 0 ? (int) $atts['id'] : (int) get_the_ID();

		if ( ! $ingredient_id || get_post_type( $ingredient_id ) !== 'mmm_ingredient' ) {
			return '';
		}

		$ingredient = new Mixmymeals_Ingredient( $ingredient_id );
		if ( ! $ingredient->is_valid() ) {
			return '';
		}

		return $this->render_ingredient_nutrition_table( $ingredient->get_nutrition() );
	}

	/**
	 * Builds the per-100 g nutrition table for an ingredient, or '' when there
	 * are no values.
	 *
	 * @param array $nutri Nutrition data from Mixmymeals_Ingredient::get_nutrition().
	 * @return string
	 */
	private function render_ingredient_nutrition_table( array $nutri ): string {

		$rows = array(
			array( __( 'Calories', 'mixmymeals' ), 'calories_100', 'kcal', 1 ),
			array( __( 'Protein', 'mixmymeals' ), 'protein', 'g', 1 ),
			array( __( 'Carbohydrates', 'mixmymeals' ), 'carbs', 'g', 1 ),
			array( __( 'Fat', 'mixmymeals' ), 'fat', 'g', 1 ),
			array( __( 'Sugar', 'mixmymeals' ), 'sugar', 'g', 1 ),
			array( __( 'Fiber', 'mixmymeals' ), 'fiber', 'g', 1 ),
			array( __( 'Salt', 'mixmymeals' ), 'salt', 'g', 2 ),
		);

		$has = false;
		foreach ( $rows as $r ) {
			if ( isset( $nutri[ $r[1] ] ) && (float) $nutri[ $r[1] ] > 0 ) {
				$has = true;
				break;
			}
		}
		if ( ! $has ) {
			return '';
		}

		ob_start();
		?>
		<div class="mmm-recipe-nutrition mmm-ingredient-nutrition">
			<table class="mmm-nutrition-table">
				<thead>
				<tr>
					<th><?php esc_html_e( 'Nutrition', 'mixmymeals' ); ?></th>
					<th><?php esc_html_e( 'per 100 g', 'mixmymeals' ); ?></th>
				</tr>
				</thead>
				<tbody>
				<?php foreach ( $rows as $r ) : ?>
					<?php $val = isset( $nutri[ $r[1] ] ) ? (float) $nutri[ $r[1] ] : 0.0; ?>
					<tr>
						<td><?php echo esc_html( $r[0] ); ?></td>
						<td><?php echo esc_html( round( $val, $r[3] ) ); ?> <?php echo esc_html( $r[2] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Shortcode [mmm_ingredients_index]. Lists all published ingredients grouped
	 * by ingredient category as a simple, alphabetically sorted list of links.
	 *
	 * @param array|string $atts Unused.
	 * @return string
	 */
	public function mmm_ingredients_index_callback( $atts = array() ): string {

		$ids = get_posts(
			array(
				'post_type'      => 'mmm_ingredient',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		if ( empty( $ids ) ) {
			return '';
		}

		$groups        = array();
		$uncategorized = array();
		foreach ( $ids as $id ) {
			$terms = get_the_terms( $id, 'mmm_ingredient_category' );
			if ( empty( $terms ) || is_wp_error( $terms ) ) {
				$uncategorized[] = (int) $id;
				continue;
			}
			$groups[ $terms[0]->name ][] = (int) $id;
		}
		ksort( $groups, SORT_NATURAL | SORT_FLAG_CASE );
		if ( ! empty( $uncategorized ) ) {
			$groups[ __( 'Other', 'mixmymeals' ) ] = $uncategorized;
		}

		ob_start();
		echo '<div class="mmm-ingredients-index">';
		foreach ( $groups as $cat_name => $cat_ids ) {
			echo '<section class="mmm-ingredients-index__group">';
			echo '<h2 class="mmm-ingredients-index__cat">' . esc_html( (string) $cat_name ) . '</h2>';
			echo '<ul class="mmm-ingredients-index__list">';
			foreach ( $cat_ids as $id ) {
				echo '<li class="mmm-ingredients-index__item"><a href="' . esc_url( (string) get_permalink( $id ) ) . '">'
					. esc_html( get_the_title( $id ) ) . '</a></li>';
			}
			echo '</ul>';
			echo '</section>';
		}
		echo '</div>';

		return (string) ob_get_clean();
	}

	/**
	 * Returns published recipe IDs that use the given ingredient. Pre-filters via
	 * a serialized-token LIKE on _rmm_steps, then verifies in PHP. Cached per
	 * ingredient for an hour.
	 *
	 * @param int $ingredient_id Ingredient post ID.
	 * @param int $limit         Max recipes to return.
	 * @return int[]
	 */
	private function recipes_using_ingredient( int $ingredient_id, int $limit = 24 ): array {

		if ( $ingredient_id <= 0 ) {
			return array();
		}

		$cache_key = 'mmm_ing_recipes_' . $ingredient_id;
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$idn = (string) $ingredient_id;
		$ids = get_posts(
			Mixmymeals_Post_Types::exclude_unlisted(
				array(
						'post_type'      => 'mmm_recipe',
						'post_status'    => 'publish',
						'posts_per_page' => 100,
						'fields'         => 'ids',
						'orderby'        => 'title',
						'order'          => 'ASC',
						'no_found_rows'  => true,
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						'meta_query'     => array(
							'relation' => 'OR',
							array(
								'key'     => '_rmm_steps',
								'value'   => 's:2:"id";i:' . $idn . ';',
								'compare' => 'LIKE',
							),
							array(
								'key'     => '_rmm_steps',
								'value'   => 's:2:"id";s:' . strlen( $idn ) . ':"' . $idn . '";',
								'compare' => 'LIKE',
							),
						),
				)
			)
		);

		$result = array();
		foreach ( $ids as $rid ) {
			$recipe = new Mixmymeals_Recipe( (int) $rid );
			foreach ( $recipe->get_ingredients_normalized() as $ing ) {
				if ( (int) $ing['id'] === $ingredient_id ) {
					$result[] = (int) $rid;
					break;
				}
			}
			if ( count( $result ) >= $limit ) {
				break;
			}
		}

		set_transient( $cache_key, $result, HOUR_IN_SECONDS );

		return $result;
	}

	/**
	 * Shortcode [mmm_equipment_hero]. Renders the hero section for a single
	 * piece of equipment, mirroring the ingredient hero.
	 *
	 * @param array|string $atts Optional: id to force a specific piece of equipment.
	 * @return string
	 */
	public function mmm_equipment_hero_callback( $atts = array() ): string {

		$atts         = shortcode_atts( array( 'id' => 0 ), $atts, 'mmm_equipment_hero' );
		$equipment_id = (int) $atts['id'] > 0 ? (int) $atts['id'] : (int) get_the_ID();

		if ( ! $equipment_id || get_post_type( $equipment_id ) !== 'mmm_equipment' ) {
			return '';
		}

		$title        = get_the_title( $equipment_id );
		$recipe_count = count( $this->recipes_using_equipment( $equipment_id ) );

		ob_start();
		?>
		<section class="mmm-plan-hero mmm-equipment-hero">
			<div class="mmm-plan-hero__blob"></div>
			<h1><?php echo esc_html( $title ); ?></h1>

			<div class="mmm-plan-hero__meta">
				<?php if ( $recipe_count > 0 ) : ?>
					<span class="mmm-chip mmm-chip--stat"><i class="mmm-icon fa-solid fa-utensils" aria-hidden="true"></i><?php
						printf(
							/* translators: %s: number of recipes using this equipment */
							esc_html( _n( 'Used in %s recipe', 'Used in %s recipes', $recipe_count, 'mixmymeals' ) ),
							esc_html( number_format_i18n( $recipe_count ) )
						);
					?></span>
				<?php endif; ?>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Shortcode [mmm_recipes_for_a_single_equipment]. Lists the published
	 * recipes that require the current (or given) piece of equipment as a
	 * simple list of links.
	 *
	 * @param array|string $atts Optional: id to force a specific piece of equipment.
	 * @return string
	 */
	public function mmm_recipes_for_a_single_equipment_callback( $atts = array() ): string {

		$atts         = shortcode_atts( array( 'id' => 0 ), $atts, 'mmm_recipes_for_a_single_equipment' );
		$equipment_id = (int) $atts['id'] > 0 ? (int) $atts['id'] : (int) get_the_ID();

		if ( ! $equipment_id || get_post_type( $equipment_id ) !== 'mmm_equipment' ) {
			return '';
		}

		$recipes = $this->recipes_using_equipment( $equipment_id );
		if ( empty( $recipes ) ) {
			return '';
		}

		ob_start();
		?>
		<ul class="mmm-ingredient-recipes mmm-equipment-recipes">
			<?php foreach ( $recipes as $rid ) : ?>
				<li><a href="<?php echo esc_url( (string) get_permalink( $rid ) ); ?>"><?php echo esc_html( get_the_title( $rid ) ); ?></a></li>
			<?php endforeach; ?>
		</ul>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Shortcode [mmm_equipment_affiliate_links]. Renders the shop links of a
	 * piece of equipment plus the mandatory advertising disclosure.
	 *
	 * Returns an empty string when no links are stored, so the template can
	 * skip the whole section. Every link carries rel="sponsored nofollow
	 * noopener" and a star that ties it to the disclosure below.
	 *
	 * @param array|string $atts Shortcode attributes: equipment_id.
	 * @return string
	 */
	public function mmm_equipment_affiliate_links_callback( $atts = array() ): string {

		$atts = shortcode_atts(
			array(
				'equipment_id' => 0,
			),
			$atts,
			'mmm_equipment_affiliate_links'
		);

		$equipment_id = absint( $atts['equipment_id'] );

		if ( 0 === $equipment_id ) {
			$equipment_id = (int) get_the_ID();
		}

		if ( ! Mixmymeals_Equipment::exists_by_id( $equipment_id ) ) {
			return '';
		}

		$equipment = new Mixmymeals_Equipment( $equipment_id );
		$links     = $equipment->get_affiliate_links();

		if ( empty( $links ) ) {
			return '';
		}

		$disclosure = trim( (string) get_option( 'mixmymeals_affiliate_disclosure', '' ) );

		if ( '' === $disclosure ) {
			$disclosure = __( 'Links marked with * are affiliate links. If you buy through them we may earn a commission. The price stays the same for you.', 'mixmymeals' );
		}

		ob_start();
		?>
		<div class="mmm-affiliate">
			<ul class="mmm-affiliate__list">
				<?php foreach ( $links as $link ) : ?>
					<li class="mmm-affiliate__item">
						<a class="mmm-btn mmm-btn--primary mmm-affiliate__link"
							href="<?php echo esc_url( $link['url'] ); ?>"
							target="_blank"
							rel="sponsored nofollow noopener">
							<?php echo esc_html( $link['merchant'] ); ?><span class="mmm-affiliate__star" aria-hidden="true">&nbsp;*</span>
						</a>
						<?php if ( '' !== $link['note'] ) : ?>
							<span class="mmm-affiliate__note"><?php echo esc_html( $link['note'] ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>

			<p class="mmm-affiliate__disclosure"><?php echo esc_html( $disclosure ); ?></p>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Shortcode [mmm_equipment_index]. Lists all published equipment grouped by
	 * equipment category, mirroring the ingredients index.
	 *
	 * @param array|string $atts Unused.
	 * @return string
	 */
	public function mmm_equipment_index_callback( $atts = array() ): string {

		$ids = get_posts(
			array(
				'post_type'      => 'mmm_equipment',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		if ( empty( $ids ) ) {
			return '';
		}

		$groups        = array();
		$uncategorized = array();

		foreach ( $ids as $id ) {
			$terms = get_the_terms( $id, 'mmm_equipment_category' );

			if ( empty( $terms ) || is_wp_error( $terms ) ) {
				$uncategorized[] = (int) $id;
				continue;
			}

			$groups[ $terms[0]->name ][] = (int) $id;
		}

		ksort( $groups, SORT_NATURAL | SORT_FLAG_CASE );

		if ( ! empty( $uncategorized ) ) {
			$groups[ __( 'Other', 'mixmymeals' ) ] = $uncategorized;
		}

		ob_start();
		echo '<div class="mmm-ingredients-index mmm-equipment-index">';
		foreach ( $groups as $cat_name => $cat_ids ) {
			echo '<section class="mmm-ingredients-index__group">';
			echo '<h2 class="mmm-ingredients-index__cat">' . esc_html( (string) $cat_name ) . '</h2>';
			echo '<ul class="mmm-ingredients-index__list">';
			foreach ( $cat_ids as $id ) {
				echo '<li class="mmm-ingredients-index__item"><a href="' . esc_url( (string) get_permalink( $id ) ) . '">'
					. esc_html( get_the_title( $id ) ) . '</a></li>';
			}
			echo '</ul>';
			echo '</section>';
		}
		echo '</div>';

		return (string) ob_get_clean();
	}

	/**
	 * Returns published recipe IDs that require the given piece of equipment.
	 *
	 * Die Suche selbst liegt in Mixmymeals_Equipment — Daten und Logik gehören
	 * in die Feature-Klasse, der Shortcode rendert nur.
	 *
	 * @param int $equipment_id Equipment post ID.
	 * @param int $limit        Max recipes to return.
	 * @return int[]
	 */
	private function recipes_using_equipment( int $equipment_id, int $limit = 24 ): array {

		$equipment = new Mixmymeals_Equipment( $equipment_id );

		return $equipment->get_recipe_ids( $limit );
	}

	/**
	 * Shortcode [mmm_meal_nutrition_calculator].
	 * Renders an interactive nutrition calculator for the specified or current meal.
	 *
	 * @param array|string $atts Shortcode attributes: meal_id.
	 * @return string
	 */
	public function mmm_meal_nutrition_calculator_callback( $atts ): string {

		$atts = shortcode_atts( [
			'meal_id' => 0,
		], $atts );

		$meal_id = ! empty( $atts['meal_id'] )
			? intval( $atts['meal_id'] )
			: get_the_ID();

		if ( empty( $meal_id ) || get_post_type( $meal_id ) !== 'mmm_meal' ) {
			return '<p>No valid meal found.</p>';
		}

		$meal          = new Mixmymeals_Meal( $meal_id );
		$recipe_id     = $meal->get_recipe_id();
		$meal_servings = max( 1.0, floatval( $meal->get_servings() ) );

		if ( ! $recipe_id ) {
			return '<p>No recipe assigned.</p>';
		}

		$recipe = new Mixmymeals_Recipe( $recipe_id );

		if ( ! $recipe->is_valid() ) {
			return '<p>Invalid recipe.</p>';
		}

		$recipe_servings = max( 1.0, floatval( $recipe->get_servings() ) );
		$factor          = $meal_servings / $recipe_servings;

		// Berechnung auf Basis der Zutaten, skaliert
		$ingredients = $recipe->get_ingredients_scaled( $factor );

		$totals = [
			'calories' => 0,
			'fat'      => 0,
			'carbs'    => 0,
			'protein'  => 0,
			'sugar'    => 0,
			'fiber'    => 0,
			'salt'     => 0,
			'weight'   => 0,
		];

		foreach ( $ingredients as $ing ) {
			if ( empty( $ing['id'] ) || empty( $ing['weight_g'] ) ) {
				continue;
			}

			$ingredient_obj = new Mixmymeals_Ingredient( $ing['id'] );
			if ( ! $ingredient_obj->is_valid() ) {
				continue;
			}

			$nutrition = $ingredient_obj->get_nutrition();
			$weight    = floatval( $ing['weight_g'] );
			if ( $weight <= 0 ) {
				continue;
			}

			$totals['weight']   += $weight;
			$totals['calories'] += (float) ( $nutrition['calories_100'] ?? 0 ) * $weight / 100;
			$totals['protein']  += (float) ( $nutrition['protein'] ?? 0 ) * $weight / 100;
			$totals['carbs']    += (float) ( $nutrition['carbs'] ?? 0 ) * $weight / 100;
			$totals['fat']      += (float) ( $nutrition['fat'] ?? 0 ) * $weight / 100;
			$totals['sugar']    += (float) ( $nutrition['sugar'] ?? 0 ) * $weight / 100;
			$totals['fiber']    += (float) ( $nutrition['fiber'] ?? 0 ) * $weight / 100;
			$totals['salt']     += (float) ( $nutrition['salt'] ?? 0 ) * $weight / 100;
		}

		$nutrition = [];
		foreach ( $totals as $key => $value ) {
			if ( $key !== 'weight' ) {
				$nutrition[ $key ] = $meal_servings > 0 ? $value
				                                          / $meal_servings : 0;
			}
		}

		$nutrition['weight_per_serving'] = $meal_servings > 0
			? $totals['weight'] / $meal_servings
			: 0;

		ob_start();
		?>

        <div class="mmmm-meal-nutrition-calculator"
             data-calories="<?php echo esc_attr( $nutrition['calories'] ??
		                                         0 ); ?>"
             data-fat="<?php echo esc_attr( $nutrition['fat'] ?? 0 ); ?>"
             data-carbs="<?php echo esc_attr( $nutrition['carbs'] ?? 0 ); ?>"
             data-protein="<?php echo esc_attr( $nutrition['protein'] ??
		                                        0 ); ?>"
             data-sugar="<?php echo esc_attr( $nutrition['sugar'] ?? 0 ); ?>"
             data-fiber="<?php echo esc_attr( $nutrition['fiber'] ?? 0 ); ?>"
             data-total-weight="<?php echo esc_attr( $nutrition['weight_per_serving'] ); ?>">

            <label></label>
            <input type="number" class="mmmm-meal-nutrition-calculator-grams"
                   placeholder="e.g. 250">
            <input type="range"
                   class="mmmm-meal-nutrition-calculator-grams-slider"
                   min="0"
                   max="1000"
                   step="1"
                   value="0">

            <div class="mmmm-meal-nutrition-calculator-result">
                <p><?php esc_html_e( 'Calories', 'mixmymeals' ); ?>: <span
                            class="mmmm-meal-nutrition-calculator-cal">–</span>
                </p>
                <p><?php esc_html_e( 'Fat', 'mixmymeals' ); ?>: <span
                            class="mmmm-meal-nutrition-calculator-fat">–</span>
                </p>
                <p><?php esc_html_e( 'Carbs', 'mixmymeals' ); ?>: <span
                            class="mmmm-meal-nutrition-calculator-carbs">–</span>
                </p>
                <p><?php esc_html_e( 'Protein', 'mixmymeals' ); ?>: <span
                            class="mmmm-meal-nutrition-calculator-protein">–</span>
                </p>
                <p><?php esc_html_e( 'Sugar', 'mixmymeals' ); ?>: <span
                            class="mmmm-meal-nutrition-calculator-sugar">–</span>
                </p>
                <p><?php esc_html_e( 'Fiber', 'mixmymeals' ); ?>: <span
                            class="mmmm-meal-nutrition-calculator-fiber">–</span>
                </p>
            </div>

        </div>

		<?php
		return ob_get_clean();
	}

}

