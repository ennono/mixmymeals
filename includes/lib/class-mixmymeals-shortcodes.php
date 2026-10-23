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

		// Die Diaet-Pillen ueber dem Rezeptarchiv; die Zeitstufe sitzt als
		// Auswahlfeld im Suchformular daneben.
		add_shortcode( 'mmm_archive_filters',
			array( $this, 'mmm_archive_filters_callback' ) );

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
		add_shortcode( 'mmm_ingredient_products',
			[ $this, 'mmm_ingredient_products_callback' ] );
		// Works on ingredients, recipes, meals and plans alike.
		add_shortcode( 'mmm_nutrition_traffic_lights',
			array( $this, 'mmm_nutrition_traffic_lights_callback' ) );
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

		// Embeds: one recipe or one plan as a card inside a blog post.
		add_shortcode( 'mmm_recipe_embed',
			[ $this, 'mmm_recipe_embed_callback' ] );

		add_shortcode( 'mmm_meal_plan_embed',
			[ $this, 'mmm_meal_plan_embed_callback' ] );

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
		add_shortcode( 'mmm_meal_plan_product_impact',
			[ $this, 'mmm_meal_plan_product_impact_callback' ] );
		add_shortcode( 'mmm_meal_product_impact',
			[ $this, 'mmm_meal_product_impact_callback' ] );
		// Same callback, honest name: the comparison answers for a recipe
		// directly, and a recipe template calling a shortcode named after
		// meals is the kind of thing that reads like a mistake later.
		add_shortcode( 'mmm_recipe_product_impact',
			[ $this, 'mmm_meal_product_impact_callback' ] );

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
		add_shortcode( 'mmm_disclaimer_for_a_single_ingredient',
			[ $this, 'mmm_disclaimer_for_a_single_ingredient_callback' ] );

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

		// The MixMyMeals section of the privacy policy (text in Mixmymeals_Privacy):
		add_shortcode( 'mmm_privacy_policy',
			[ $this, 'mmm_privacy_policy_callback' ] );




		// Cart / Meal Plan Builder:

		add_shortcode( 'mmm_cart_badge',
			[ $this, 'mmm_cart_badge_callback' ] );

		add_shortcode( 'mmm_meal_plan_builder',
			[ $this, 'mmm_meal_plan_builder_callback' ] );
		add_shortcode( 'mmm_planner_nutrition',
			[ $this, 'mmm_planner_nutrition_callback' ] );

		add_shortcode( 'mmm_clear_cart',
			[ $this, 'mmm_clear_cart_callback' ] );

		add_shortcode( 'mmm_create_meal_plan_from_cart',
			[ $this, 'mmm_create_meal_plan_from_cart_callback' ] );

		add_shortcode( 'mmm_share_planner',
			[ $this, 'mmm_share_planner_callback' ] );

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

		add_action( 'pre_get_posts',
			array( $this, 'filter_recipe_archive_by_view' ) );

		// After the view: the facets merge their clauses into whatever is
		// already in the query, and the view's own meta_query has to be there
		// by then.
		add_action( 'pre_get_posts',
			array( __CLASS__, 'apply_archive_filters' ) );

		add_filter( 'posts_where',
			array( $this, 'archive_search_title_where' ), 10, 2 );

		// Kombinationen und Suchen gehoeren nicht in den Index, einzelne
		// Facetten schon — siehe filter_archive_noindex().
		add_filter( 'mixmymeals_seo_noindex',
			array( __CLASS__, 'filter_archive_noindex' ) );

		add_filter( 'get_the_archive_title',
			[ $this, 'remove_recipe_category_archive_title_prefix' ], 20 );
	}

	/**
	 * AJAX endpoints that are not shortcodes. The cart endpoints live in
	 * their own class.
	 */
	private function register_ajax(): void {

		// AJAX Handlers. NOT shortcode callbacks.
		// Die Warenkorb-Endpunkte liegen in Mixmymeals_Cart::register_ajax().






		// The planner's live nutrition: read-only, for guests as much as for
		// anybody, from the visitor's own cart.
		add_action( 'wp_ajax_mmm_planner_nutrition',
			[ $this, 'mmm_planner_nutrition_ajax_callback' ] );
		add_action( 'wp_ajax_nopriv_mmm_planner_nutrition',
			[ $this, 'mmm_planner_nutrition_ajax_callback' ] );
		// The plan page's servings field: read-only, for guests too.
		add_action( 'wp_ajax_mmm_plan_servings',
			array( $this, 'mmm_plan_servings_ajax_callback' ) );
		add_action( 'wp_ajax_nopriv_mmm_plan_servings',
			array( $this, 'mmm_plan_servings_ajax_callback' ) );
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
	 * Shortcode [mmm_privacy_policy]: the MixMyMeals section of the privacy
	 * policy, generated from this site's settings — so the published policy
	 * follows a changed retention period or a switched-on option without
	 * anyone having to remember to edit it. Delegates to
	 * Mixmymeals_Privacy::policy_section_html().
	 *
	 * Attribute `heading`: h2 (default), h3, h4 or none — the policy page
	 * decides how deep the section sits.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function mmm_privacy_policy_callback( $atts = [] ): string {

		$atts = shortcode_atts( [ 'heading' => 'h2' ], $atts, 'mmm_privacy_policy' );

		if ( ! class_exists( 'Mixmymeals_Privacy' ) ) {
			return '';
		}

		$heading = 'none' === $atts['heading'] ? '' : sanitize_key( $atts['heading'] );

		return '<div class="mmm-privacy-policy">' . Mixmymeals_Privacy::policy_section_html( $heading ) . '</div>';
	}

	/**
	 * Shortcode [mmm_thumbnails_of_meals_in_a_single_meal_plan].
	 * Renders the current meal plan's recipe pictures as a strip.
	 *
	 * What goes into it is the plan's decision, not this method's: which
	 * recipes and in what order comes from
	 * Mixmymeals_Meal_Plan::get_thumbnail_recipe_ids(), which honours the
	 * "Recipe Thumbnails" settings. Every id it returns has a featured image,
	 * so there is nothing left to filter out here.
	 *
	 * @return string
	 */
	public function mmm_thumbnails_of_meals_in_a_single_meal_plan_callback(): string {

		$post_id = get_the_ID();

		if ( ! $post_id ) {
			return '';
		}

		$plan       = new Mixmymeals_Meal_Plan( $post_id );
		$recipe_ids = $plan->get_thumbnail_recipe_ids();

		if ( empty( $recipe_ids ) ) {
			return '';
		}

		$permalink = get_permalink( $post_id );
		$output    = '<div class="mmm-mealplan-thumbs">';

		foreach ( $recipe_ids as $recipe_id ) {

			$thumb = get_the_post_thumbnail(
				$recipe_id,
				'thumbnail',
				[
					'loading' => 'lazy',
					'alt'     => get_the_title( $recipe_id ),
				]
			);

			$output .= '<a href="' . esc_url( $permalink ) . '">' . $thumb . '</a>';
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

		// Leads the row, same as in the recipe archive: the plan list is where
		// a paid plan has to be recognisable without opening it first.
		echo self::disclosure_chip( (int) $plan_id, 'mmm-meta__item' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.

		if ( $num_of_days ) {
			echo '<span class="mmm-meta__item mmm-meta__item--days">'
			     /* translators: %s: number of days in the plan. */
			     . esc_html( str_replace( '%s', $num_of_days, _n( '%s Day', '%s Days', $num_of_days, 'mixmymeals' ) ) )
			     . '</span>';
		}
		if ( $num_of_meals ) {
			echo '<span class="mmm-meta__item mmm-meta__item--meals">'
			     /* translators: %s: number of recipes in the plan. */
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
		echo self::diet_chips( (int) $plan->get_id(), 'mmm-meta__item' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
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

		// Disclosure leads the row, in the row's own vocabulary — a label that
		// sits apart from the meta reads as decoration rather than as a notice.
		$disclosure = self::disclosure_chip( $post_id, 'mmm-meta__item' );
		if ( '' !== $disclosure ) {
			$items[] = $disclosure;
		}

		if ( ! empty( $nutrition['calories'] ) ) {
			$energy_text = self::chip_text(
				'archive_energy',
				array( 'kcal' => number_format_i18n( round( $nutrition['calories'] ) ) )
			);

			$items[] = '<span class="mmm-meta__item mmm-meta__item--calories">'
			           . esc_html( $energy_text ) . '</span>';
		}
		$cost = $recipe->calculate_cost_per_serving();
		if ( $cost > 0 ) {
			$currency = get_option( 'mixmymeals_currency', '€' );
			$price_text = self::chip_text(
				'archive_price',
				array( 'price' => number_format_i18n( $cost, 2 ) . ' ' . $currency )
			);

			$items[]  = '<span class="mmm-meta__item mmm-meta__item--cost">'
			            . esc_html( $price_text )
			            . '</span>';
		}

		/*
		 * Every category is a category. A term that happens to be called
		 * "vegan" used to be lifted out and printed as a diet chip, which put a
		 * second, editorial source next to the computed one — and a public
		 * "vegan" on whatever an editor had filed there, whether or not the
		 * ingredients agreed. The diet chips below answer that question from
		 * the recipe itself.
		 */

		/*
		 * How long it takes, right after what it costs. It was the one figure
		 * the card never carried, which made "something quick" unanswerable
		 * from a listing even once the filter existed.
		 */
		$prep = self::prep_time_chip( $post_id, 'mmm-meta__item' );

		if ( '' !== $prep ) {
			$items[] = $prep;
		}

		// Only while somebody is searching by ingredients, and then first,
		// because it is the answer to what they asked.
		$match = self::ingredient_match_chip( $post_id, 'mmm-meta__item' );

		if ( '' !== $match ) {
			array_unshift( $items, $match );
		}

		$terms = get_the_terms( $post_id, 'mmm_recipe_category' );
		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			usort( $terms, fn( $a, $b ) => strcasecmp( $a->name, $b->name ) );
			foreach ( $terms as $term ) {
				$items[] = '<span class="mmm-meta__item mmm-meta__item--category">'
				           . esc_html( self::chip_text( 'category', array( 'category' => $term->name ) ) )
				           . '</span>';
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

		$mmm_diet = self::diet_chips( (int) $recipe->get_id(), 'mmm-meta__item' );
		if ( '' !== $mmm_diet ) {
			$items[] = $mmm_diet;
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

		/*
		 * Queried with fields => 'ids', which skips WP's own priming — so each
		 * card below would fetch its post, its meta and its terms one at a
		 * time. On the recipe archive the four browse rows made that a hundred
		 * queries the page did not carry before they existed.
		 */
		if ( function_exists( '_prime_post_caches' ) ) {
			_prime_post_caches( $ids, true, true );
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
	 * @param array|string $atts count, title, note, boxed, link.
	 * @return string
	 */
	public function mmm_new_recipes_callback( $atts = array() ): string {
		return $this->render_recipe_view_row( 'new', $atts, 'mmm_new_recipes' );
	}

	/**
	 * Shortcode [mmm_favorite_recipes]: the recipes flagged as favorite (Details
	 * meta box → "Mark as favorite"). Returns '' when nothing is flagged, so the
	 * section disappears cleanly.
	 *
	 * @param array|string $atts count, title, note, boxed, link.
	 * @return string
	 */
	public function mmm_favorite_recipes_callback( $atts = array() ): string {
		return $this->render_recipe_view_row( 'fav', $atts, 'mmm_favorite_recipes' );
	}

	/**
	 * Shortcode [mmm_popular_recipes]: the recipes that have been cooked most
	 * often, ranked by the cook counter maintained in Mixmymeals_Recipe. Returns
	 * '' when nothing has been cooked yet.
	 *
	 * @param array|string $atts count, title, note, boxed, link.
	 * @return string
	 */
	public function mmm_popular_recipes_callback( $atts = array() ): string {
		return $this->render_recipe_view_row( 'popular', $atts, 'mmm_popular_recipes' );
	}

	/**
	 * Shortcode [mmm_uncooked_recipes]: the recipes no meal in a published plan
	 * references. The counterpart to [mmm_popular_recipes], useful for spotting
	 * recipes still waiting for their first plan. Returns '' when every recipe
	 * has been cooked.
	 *
	 * @param array|string $atts count, title, note, boxed, link.
	 * @return string
	 */
	public function mmm_uncooked_recipes_callback( $atts = array() ): string {
		return $this->render_recipe_view_row( 'uncooked', $atts, 'mmm_uncooked_recipes' );
	}

	/**
	 * One browse row for a recipe view: the view's own recipes, rendered as the
	 * shared boxed section and linked to the archive showing the same view.
	 *
	 * What each view selects comes from Mixmymeals_Recipe::browse_view_args(),
	 * which the archive filter reads too — so a row and its "show all" page can
	 * never disagree about what "never cooked" means.
	 *
	 * The link is dropped when the row already shows everything there is: a
	 * page repeating the eight cards above it is not worth the click.
	 *
	 * @param string       $view View slug (new|fav|popular|uncooked).
	 * @param array|string $atts Shortcode attributes.
	 * @param string       $tag  Shortcode tag, for shortcode_atts filtering.
	 * @return string
	 */
	private function render_recipe_view_row( string $view, $atts, string $tag ): string {

		$atts = shortcode_atts(
			array(
				'count' => 8,
				'title' => '',
				'note'  => '',
				'boxed' => '1',
				'link'  => '1',
			),
			$atts,
			$tag
		);

		$count = min( 24, max( 1, (int) $atts['count'] ) );
		$link  = filter_var( $atts['link'], FILTER_VALIDATE_BOOLEAN );

		$query = new WP_Query(
			Mixmymeals_Post_Types::exclude_unlisted(
				array_merge(
					Mixmymeals_Recipe::browse_view_args( $view ),
					array(
						'post_type'      => 'mmm_recipe',
						'post_status'    => 'publish',
						'posts_per_page' => $count,
						'fields'         => 'ids',
						// The total is only counted when a link needs it:
						// without one this stays the cheap query it was.
						'no_found_rows'  => ! $link,
					)
				)
			)
		);

		$ids   = array_map( 'intval', (array) $query->posts );
		$total = $link ? (int) $query->found_posts : 0;

		return $this->render_browse_section(
			$this->render_recipe_browse_cards( $ids ),
			(string) $atts['title'],
			$view,
			filter_var( $atts['boxed'], FILTER_VALIDATE_BOOLEAN ),
			(string) $atts['note'],
			$total > count( $ids ) ? Mixmymeals_Recipe::browse_view_url( $view ) : '',
			$total
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
	 * The heading and the "show all" link point at the same place and are one
	 * decision, not two: a heading that is a link but looks like text is found
	 * by nobody, and a link with no heading beside it has nothing to name. The
	 * note sits below both, which is why it is a parameter here rather than
	 * being spliced into the finished markup by the caller — the row's header
	 * is a layout of its own now, and a string search for </h2> would drop the
	 * note into the middle of it.
	 *
	 * @param string $cards    Pre-rendered, already-escaped card markup ('' = none).
	 * @param string $title    Heading text; '' renders no heading.
	 * @param string $variant  Modifier key (new|fav|popular|uncooked|latest) for CSS hooks.
	 * @param bool   $boxed    Whether to wrap the row in the boxed section.
	 * @param string $note     One-line explanation below the heading; '' renders none.
	 * @param string $link_url Target of the heading and the "show all" link; '' renders neither.
	 * @param int    $total    How many items the link leads to.
	 * @return string
	 */
	private function render_browse_section(
		string $cards,
		string $title,
		string $variant,
		bool $boxed,
		string $note = '',
		string $link_url = '',
		int $total = 0
	): string {

		if ( '' === $cards ) {
			return '';
		}

		$variant     = sanitize_html_class( $variant );
		$row_classes = 'mmm-recipe-row' . ( '' !== $variant ? ' mmm-recipe-row--' . $variant : '' );

		$heading = '';
		if ( '' !== $title ) {
			$label   = '' !== $link_url
				? '<a href="' . esc_url( $link_url ) . '">' . esc_html( $title ) . '</a>'
				: esc_html( $title );
			$heading = '<h2 class="mmm-recipe-row__title">' . $label . '</h2>';
		}

		$more = '';
		if ( '' !== $link_url ) {
			$more = '<a class="mmm-recipe-row__more" href="' . esc_url( $link_url ) . '">'
				. esc_html(
					sprintf(
						/* translators: %s: number of recipes, already formatted. */
						_n( 'Show all %s', 'Show all %s', $total, 'mixmymeals' ),
						number_format_i18n( $total )
					)
				)
				. '</a>';
		}

		$header = ( '' !== $heading || '' !== $more )
			? '<div class="mmm-recipe-row__header">' . $heading . $more . '</div>'
			: '';

		$note_html = '' !== trim( $note )
			? '<p class="mmm-browse-note">' . esc_html( $note ) . '</p>'
			: '';

		// $cards is markup already escaped by the card renderer.
		$row = '<div class="' . esc_attr( $row_classes ) . '">'
			. '<div class="mmm-recipe-row__track">' . $cards . '</div></div>';

		if ( ! $boxed ) {
			return $header . $note_html . $row;
		}

		$section_classes = 'mmm-recipe-browse' . ( '' !== $variant ? ' mmm-recipe-browse--' . $variant : '' );

		return '<section class="' . esc_attr( $section_classes ) . '">'
			. $header . $note_html . $row . '</section>';
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

		/*
		 * Queried with fields => 'ids', which skips WP's own priming — so each
		 * card below would fetch its post, its meta and its terms one at a
		 * time. On the recipe archive the four browse rows made that a hundred
		 * queries the page did not carry before they existed.
		 */
		if ( function_exists( '_prime_post_caches' ) ) {
			_prime_post_caches( $ids, true, true );
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
			// Over the picture rather than above the title: these browse rows are
			// dense, and a line of its own between image and heading both shifted
			// the layout and read as part of the heading. Cards without a photo
			// have nothing to overlay, so there it keeps the old place.
			$disclosure = self::disclosure_pill( $id );

			echo '<article class="mmm-recipe-row__card' . ( $thumb ? '' : ' mmm-recipe-row__card--noimg' ) . '">';
			if ( $thumb ) {
				echo '<a class="mmm-recipe-row__media" href="' . esc_url( $permalink ) . '">'
					. $thumb // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped markup.
					. $disclosure // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
					. '</a>';
			}
			echo '<div class="mmm-recipe-row__body">';
			if ( ! $thumb ) {
				echo $disclosure; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
			}
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
				<?php
				// Same placement rule as the browse cards: over the picture when
				// there is one, in the body when there is not.
				$mmm_disclosure = self::disclosure_pill( (int) $plan_id );
				?>
				<?php if ( '' !== $thumb ) : ?>
					<div class="mmm-plan-teaser__media">
						<?php echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- image markup from get_the_post_thumbnail(). ?>
						<?php echo $mmm_disclosure; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
					</div>
				<?php endif; ?>
				<div class="mmm-plan-teaser__body">
					<?php if ( '' === $thumb ) : ?>
						<?php echo $mmm_disclosure; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
					<?php endif; ?>
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
			$images    = $this->get_meal_plan_card_images( $id );

			/*
			 * Below two pictures there is no mosaic to build, so the card keeps
			 * the single-image shape the recipes use — one tile in a grid meant
			 * for three looks like a layout that failed, not like a plan.
			 */
			$mosaic = count( $images ) >= 2;
			$thumb  = $mosaic ? implode( '', $images ) : $this->get_meal_plan_card_thumbnail( $id );

			echo '<article class="mmm-recipe-row__card mmm-recipe-row__card--plan">';
			// Same placement as the recipe cards: over the picture when there is
			// one, above the title when there is not.
			$disclosure = self::disclosure_pill( $id );

			if ( '' !== $thumb ) {
				printf(
					'<a class="mmm-recipe-row__media" href="%s">',
					esc_url( $permalink )
				);

				/*
				 * The tiles get a wrapper of their own rather than turning the
				 * media box into the grid. Two things live in that box beside
				 * the pictures: the disclosure pill, which is in normal flow
				 * and would take a tile, and a <noscript> that the image
				 * optimiser adds next to every thumbnail — a dozen of them on
				 * the live front page. Either would push the mosaic apart.
				 */
				if ( $mosaic ) {
					printf(
						'<span class="mmm-recipe-row__mosaic mmm-recipe-row__mosaic--%d">',
						count( $images )
					);
				}

				echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped markup.

				if ( $mosaic ) {
					echo '</span>';
				}

				echo $disclosure // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
					. '</a>';
			}
			echo '<div class="mmm-recipe-row__body">';
			if ( '' === $thumb ) {
				echo $disclosure; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
			}
			echo '<h3 class="mmm-recipe-row__name"><a href="' . esc_url( $permalink ) . '">'
				. esc_html( get_the_title( $id ) ) . '</a></h3>';
			echo $this->render_meal_plan_card_meta( $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
			echo '</div>';
			echo '</article>';
		}

		return (string) ob_get_clean();
	}

	/**
	 * The pictures a plan card shows: several dishes, not one.
	 *
	 * A plan and a recipe used to arrive in the same carousel as the same
	 * object — one picture, one title — and nothing said that one of them is a
	 * week of cooking. So a plan shows what it is made of: the plan's own
	 * featured image leads where there is one, otherwise the strongest recipe,
	 * and further recipes follow.
	 *
	 * Ordered by recipe priority like the single thumbnail already was, each
	 * recipe used once — a plan that cooks one dish three times must not show
	 * it three times. Returns fewer than asked for rather than repeating, and
	 * the caller falls back to the single-image card below two: a mosaic with
	 * one tile is a broken mosaic, not a plan.
	 *
	 * @param int    $plan_id Meal plan post ID.
	 * @param int    $max     How many pictures at most.
	 * @param string $size    Image size for the tiles.
	 * @return string[] Escaped <img> markup, in display order.
	 */
	private function get_meal_plan_card_images( int $plan_id, int $max = 3, string $size = 'medium' ): array {

		$images = array();
		$seen   = array();
		$attr   = array(
			'loading' => 'lazy',
			'alt'     => '',
		);

		$plan_thumb_id = (int) get_post_thumbnail_id( $plan_id );

		if ( $plan_thumb_id ) {
			$images[] = (string) get_the_post_thumbnail( $plan_id, $size, $attr );
		}

		$plan   = new Mixmymeals_Meal_Plan( $plan_id );
		$ranked = array();

		foreach ( $plan->get_meals() as $meal_post ) {

			$recipe_id = (int) ( new Mixmymeals_Meal( $meal_post->ID ) )->get_recipe_id();

			if ( $recipe_id <= 0 || isset( $seen[ $recipe_id ] ) ) {
				continue;
			}

			if ( ! Mixmymeals_Recipe::exists_by_id( $recipe_id ) || ! get_post_thumbnail_id( $recipe_id ) ) {
				continue;
			}

			$seen[ $recipe_id ] = true;
			$ranked[]           = array(
				'id'       => $recipe_id,
				'priority' => ( new Mixmymeals_Recipe( $recipe_id ) )->get_priority(),
			);
		}

		usort( $ranked, static fn( $a, $b ) => $a['priority'] <=> $b['priority'] );

		foreach ( $ranked as $row ) {

			if ( count( $images ) >= $max ) {
				break;
			}

			$images[] = (string) get_the_post_thumbnail( $row['id'], $size, $attr );
		}

		return $images;
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
				/* translators: %s: number of days in the plan. */
				. esc_html( sprintf( _n( '%s Day', '%s Days', $days, 'mixmymeals' ), $days ) )
				. '</span>';
		}
		if ( $meals ) {
			/* translators: %s: number of recipes. */
			$out .= '<span class="mmm-meta__item mmm-meta__item--meals">'
				/* translators: %s: number of recipes in the plan. */
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

		$recipe = $meal->get_recipe();

		$steps = $recipe->get_steps();

		if ( empty( $steps ) ) {
			return '<p>' . esc_html__( 'No steps defined for this recipe.',
					'mixmymeals' ) . '</p>';
		}

		$recipe_servings = max( 1.0, floatval( $recipe->get_servings() ) );
		$meal_servings   = max( 1.0, floatval( $meal->get_shown_servings() ) );
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
				<?php $mmm_prep = Mixmymeals_Recipe::step_prep( (array) $step ); ?>
                <div class="mmm-step<?php echo $mmm_prep ? ' mmm-step--prep' : ''; ?>" id="<?php echo esc_attr( self::step_anchor( (int) $index ) ); ?>">

                    <div class="mmm-step-header">
						<?php echo self::step_prep_badge( $mmm_prep ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
						<?php if ( ! empty( $step['title'] ) ) : ?>
                            <h3 class="mmm-step-title">
								<?php
								echo esc_html(
									/* translators: %d: position of the step, starting at 1. */
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
									<?php echo esc_html( get_the_title( $ing['id'] ) ); ?>
                                </span>
									<?php if ( $line ) : ?>
                                        <span class="mmm-ingredient-amount">
										<?php echo esc_html( $line ); ?>
                                    </span>
									<?php endif; ?>
									<?php if ( $is_optional ) : ?><span class="mmm-ingredient-optional">(<?php echo esc_html__( 'optional', 'mixmymeals' ); ?>)</span><?php endif; ?>
									<?php
									echo $this->row_product_markup( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built escaped.
										(int) $ing['id'],
										(int) ( $ing['product_id'] ?? 0 ),
										! empty( $ing['product_open'] ) ? (string) ( $ing['implemented_for'] ?? '' ) : ''
									);
									?>
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
	 * A meal's figures, scaled to its own servings: per serving, per 100 g and
	 * the weight of one serving. Shared by the meal's nutrition table and its
	 * traffic light, so the two cannot rate different numbers.
	 *
	 * @param Mixmymeals_Meal $meal The meal.
	 * @return array{per_serving:array<string,float>, per_100g:array<string,float>, weight_per_serving:float, cooked:array<int, array{name:string, factor:float}>}|null
	 *         Null when the recipe has no ingredients.
	 */
	private function meal_nutrition_figures( Mixmymeals_Meal $meal ): ?array {

		$recipe_id = $meal->get_recipe_id();
		$recipe    = $meal->get_recipe();

		$recipe_servings = max( 1.0, floatval( $recipe->get_servings() ) );
		$meal_servings   = max( 1.0, floatval( $meal->get_shown_servings() ) );
		$factor          = $meal_servings / $recipe_servings;

		$ingredients = $recipe->get_ingredients_scaled( $factor );

		if ( empty( $ingredients ) ) {
			return null;
		}

		$totals           = array_fill_keys( Mixmymeals_Meal_Plan::NUTRITION_KEYS, 0 );
		$totals['weight'] = 0;
		$cooked           = array();

		foreach ( $ingredients as $ing ) {

			if ( empty( $ing['id'] ) || empty( $ing['weight_g'] ) ) {
				continue;
			}

			$ingredient_obj = new Mixmymeals_Ingredient( $ing['id'] );

			if ( ! $ingredient_obj->is_valid() ) {
				continue;
			}

			// The packet this row is made of, where it names one.
			$pinned    = (int) ( $ing['product_id'] ?? 0 );
			$nutrition = $ingredient_obj->get_nutrition_pinned( $pinned );
			$weight    = floatval( $ing['weight_g'] );

			if ( $weight <= 0 ) {
				continue;
			}

			// Cooked weight for what the portion weighs, raw for its nutrients.
			$cooked_weight     = $ingredient_obj->cooked_weight( $weight, $pinned );
			$totals['weight'] += $cooked_weight;

			if ( abs( $cooked_weight - $weight ) > 0.0001 ) {
				$mmm_factor = (float) $ingredient_obj->get_cooking_factor_pinned( $pinned );

				// By ingredient and factor: one ingredient pinned to two
				// packets in two steps can carry two factors, and each is named.
				$cooked[ (int) $ing['id'] . '|' . $mmm_factor ] = array(
					'name'   => get_the_title( (int) $ing['id'] ),
					'factor' => $mmm_factor,
				);
			}

			foreach ( Mixmymeals_Ingredient::NUTRITION_KEY_MAP as $sum_key => $own_key ) {
				$totals[ $sum_key ] += (float) ( $nutrition[ $own_key ] ?? 0 ) * $weight / 100;
			}
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

		return array(
			'per_serving'        => $per_serving,
			'per_100g'           => $per_100g,
			'weight_per_serving' => $weight_per_serving,
			// Ingredients whose cooking factor moved the weight, with the factor.
			'cooked'             => array_values( $cooked ),
		);
	}

	/**
	 * The figures per 100 g the traffic light rates, for an ingredient, a
	 * recipe, a meal or a plan — the one place that decides which number a
	 * light is drawn from, so a table and the [mmm_nutrition_traffic_lights]
	 * row cannot disagree.
	 *
	 * Ingredients resolve through their product like every other read; a
	 * recipe, a meal and a plan sum those. A figure comes back **null**, and
	 * gets no light, when part of the dish has no value for it
	 * (Mixmymeals_Recipe::unreliable_nutrition_keys()): a missing salt figure
	 * lowers the sum silently, and a green light on a sum that is short of its
	 * salt is the most misleading thing this could print. A stored 0 g of
	 * saturates beside more than 3 g fat counts as missing too — on the
	 * ingredient itself and in every dish that uses it.
	 *
	 * @param int $post_id Ingredient, recipe, meal or plan.
	 * @return array<string,float|null> Keyed by Mixmymeals_Meal_Plan::NUTRITION_KEYS;
	 *                                  empty for anything else.
	 */
	private function traffic_light_figures( int $post_id ): array {

		switch ( get_post_type( $post_id ) ) {

			case 'mmm_ingredient':
				$nutrition = ( new Mixmymeals_Ingredient( $post_id ) )->get_nutrition();
				$out       = array();

				foreach ( Mixmymeals_Ingredient::NUTRITION_KEY_MAP as $sum_key => $own_key ) {
					$value           = $nutrition[ $own_key ] ?? null;
					$out[ $sum_key ] = is_numeric( $value ) ? (float) $value : null;
				}

				if ( Mixmymeals_Ingredient::saturates_zero_is_gap( $nutrition ) ) {
					$out['saturates'] = null;
				}

				return $out;

			case 'mmm_recipe':
				$recipes = array( $post_id );
				$recipe  = new Mixmymeals_Recipe( $post_id );
				$figures = $recipe->is_valid() && $recipe->calculate_nutrition_per_serving()['weight_per_serving'] > 0
					? $recipe->calculate_nutrition_per_100g()
					: array();
				break;

			case 'mmm_meal':
				$meal    = new Mixmymeals_Meal( $post_id );
				$recipes = array( $meal->get_recipe() );
				$figures = (array) ( $this->meal_nutrition_figures( $meal )['per_100g'] ?? array() );

				// Weightless means nothing was summed; 0 there is not a figure.
				if ( ! array_filter( $figures ) ) {
					$figures = array();
				}
				break;

			case 'mmm_meal_plan':
				return $this->plan_traffic_light_figures( new Mixmymeals_Meal_Plan( $post_id ) );

			default:
				return array();
		}

		return $this->reliable_figures( $figures, $recipes );
	}

	/**
	 * The figures traffic_light_figures() rates, for a plan object — which may
	 * exist only as the planner's list of meals
	 * (Mixmymeals_Meal_Plan::from_meal_rows()).
	 *
	 * @param Mixmymeals_Meal_Plan $plan Plan.
	 * @return array<string,float|null>
	 */
	private function plan_traffic_light_figures( Mixmymeals_Meal_Plan $plan ): array {

		return $this->reliable_figures(
			$plan->calc_nutrition_per_100g(),
			array_map( array( 'Mixmymeals_Meal_Plan', 'row_recipe' ), $plan->get_meal_rows() )
		);
	}

	/**
	 * Figures with every key nulled that some of the recipes behind them
	 * cannot be trusted for.
	 *
	 * @param array $figures Sum key => figure.
	 * @param array<int,int|Mixmymeals_Recipe> $recipes Recipes (or IDs) the figures were summed from.
	 * @return array<string,float|null>
	 */
	private function reliable_figures( array $figures, array $recipes ): array {

		$keys = Mixmymeals_Meal_Plan::NUTRITION_KEYS;
		$out  = array_fill_keys( $keys, null );

		if ( empty( $figures ) ) {
			return $out;
		}

		foreach ( $keys as $key ) {
			$out[ $key ] = isset( $figures[ $key ] ) ? (float) $figures[ $key ] : null;
		}

		foreach ( Mixmymeals_Recipe::unreliable_nutrition_keys( $recipes ) as $key ) {
			$out[ $key ] = null;
		}

		return $out;
	}

	/**
	 * The lights themselves, per figure: traffic_light_figures() rated, with
	 * the portion rule wherever there is a serving — a recipe's, or a meal's
	 * at its own number of servings.
	 *
	 * @param int $post_id Ingredient, recipe, meal or plan.
	 * @return array<string, array{amount:string, colour:string, basis:string}>
	 *         Only figures that have a light.
	 */
	private function traffic_lights( int $post_id ): array {

		$serving = array();

		switch ( get_post_type( $post_id ) ) {

			case 'mmm_recipe':
				$recipe  = new Mixmymeals_Recipe( $post_id );
				$serving = $recipe->is_valid() ? $recipe->calculate_nutrition_per_serving() : array();
				break;

			case 'mmm_meal':
				$serving = (array) ( $this->meal_nutrition_figures( new Mixmymeals_Meal( $post_id ) )['per_serving'] ?? array() );
				break;
		}

		return $this->rate_lights( $this->traffic_light_figures( $post_id ), $serving );
	}

	/**
	 * Rates figures per 100 g, with the portion rule where a serving is given.
	 *
	 * @param array<string,float|null> $per_100g From traffic_light_figures().
	 * @param array<string,float>      $serving  Figures per serving, or empty.
	 * @return array<string, array{amount:string, colour:string, basis:string}>
	 */
	private function rate_lights( array $per_100g, array $serving = array() ): array {

		$out = array();

		foreach ( $per_100g as $key => $value ) {

			$light = Mixmymeals_Ingredient::traffic_light(
				$key,
				$value,
				isset( $serving[ $key ] ) ? (float) $serving[ $key ] : null
			);

			if ( $light ) {
				$out[ $key ] = $light;
			}
		}

		return $out;
	}

	/**
	 * One traffic light, as plain markup for the theme to draw.
	 *
	 * The word is the content and the colour a modifier class: the plugin says
	 * "high", the theme decides what high looks like. `data-amount` carries the
	 * same fact unlocalised, for a theme that wants to select on it, and
	 * `data-basis` whether the portion rule decided it.
	 *
	 * @param array $light From Mixmymeals_Ingredient::traffic_light(); empty for none.
	 * @return string '' when there is no light.
	 */
	private function traffic_light_markup( array $light ): string {

		if ( ! $light ) {
			return '';
		}

		$basis = (string) ( $light['basis'] ?? '100g' );

		return sprintf(
			'<span class="mmm-traffic-light mmm-traffic-light--%1$s" data-amount="%2$s" data-basis="%3$s">%4$s</span>',
			esc_attr( $light['colour'] ),
			esc_attr( $light['amount'] ),
			esc_attr( $basis ),
			esc_html( Mixmymeals_Ingredient::traffic_light_label( $light['amount'], $basis ) )
		);
	}

	/**
	 * One day rating, as plain markup in the traffic light's shape: the word,
	 * and the verdict as a modifier for the theme.
	 *
	 * @param string $verdict over, under or ''.
	 * @return string '' for none.
	 */
	private function day_rating_markup( string $verdict ): string {

		$label = Mixmymeals_Meal_Plan::day_rating_label( $verdict );

		if ( '' === $label ) {
			return '';
		}

		return sprintf(
			'<span class="mmm-day-rating mmm-day-rating--%1$s" data-rating="%1$s">%2$s</span>',
			esc_attr( $verdict ),
			esc_html( $label )
		);
	}

	/**
	 * The one nutrition table all four subjects print — ingredient, recipe,
	 * meal and plan — in the markup the plan's table always had
	 * (`mmm-nutrition-table mmm-nutri-table`, `data-label` on every cell), so a
	 * theme styles one table, not four.
	 *
	 * Which columns exist and what they are called is
	 * Mixmymeals_Nutrition_Table's decision; the caller supplies the figures,
	 * already formatted. A column the site switched off is simply not looked up.
	 *
	 * @param string                                  $context ingredient, recipe, meal or plan.
	 * @param array<string,array<string,string|null>> $data    Column → nutrient key → printed
	 *                                                         figure; null prints a dash.
	 * @param array<string,mixed>                     $args    `tokens` for headings, `lights`
	 *                                                         from traffic_lights(),
	 *                                                         `ratings` from
	 *                                                         Mixmymeals_Meal_Plan::calc_day_rating()
	 *                                                         (plan only; set even when
	 *                                                         empty, since the note then
	 *                                                         says why nothing is rated),
	 *                                                         `cooked`: the ingredients
	 *                                                         whose cooking factor moved
	 *                                                         the weight, as name and factor,
	 *                                                         `open`: the packets a diet
	 *                                                         fell back on
	 *                                                         (Mixmymeals_Recipe::get_open_products()).
	 * @return string '' when the site has switched every column off.
	 */
	private function render_nutrition_table( string $context, array $data, array $args = array() ): string {

		$cooked = array_values( (array) ( $args['cooked'] ?? array() ) );
		$tokens = (array) ( $args['tokens'] ?? array() );

		/* translators: Marks a serving weight as the weight once cooked, e.g. "450 g cooked". */
		$tokens['cooked'] = $cooked ? _x( 'cooked', 'serving weight', 'mixmymeals' ) : '';

		$config = Mixmymeals_Nutrition_Table::config( $context, $tokens );

		if ( empty( $config['columns'] ) ) {
			return '';
		}

		$lights = ( $config['light'] && ! empty( $args['lights'] ) ) ? (array) $args['lights'] : array();

		// The plan's verdict per day, beside the share it is a verdict on.
		$ratings = isset( $args['ratings'] ) && isset( $config['columns']['ri'] ) ? (array) $args['ratings'] : null;

		// The notes explaining the light and the rating, only where either
		// actually shows: a light note under a table without a light would
		// explain nothing.
		$explain = array();

		if ( $lights && isset( $config['columns']['per_100g'] ) ) {
			$explain['light'] = Mixmymeals_Ingredient::traffic_light_note( in_array( $context, array( 'recipe', 'meal' ), true ) );
		}

		if ( null !== $ratings ) {
			$explain['rating'] = Mixmymeals_Meal_Plan::day_rating_note();
		}

		if ( ! empty( $args['open'] ) ) {
			$explain['open'] = Mixmymeals_Nutrition_Table::open_note( (array) $args['open'] );
		}

		// The marks go onto the headings here, once, so the cell labels the
		// phone layout prints from data-label carry them too.
		$footnotes = Mixmymeals_Nutrition_Table::footnotes( $config, $cooked, $explain );

		foreach ( $footnotes['marks'] as $mmm_col => $mmm_mark ) {
			if ( 'label' === $mmm_col ) {
				$config['label'] .= $mmm_mark;
			} else {
				$config['columns'][ $mmm_col ] .= $mmm_mark;
			}
		}

		ob_start();
		?>
		<table class="mmm-nutrition-table mmm-nutri-table mmm-nutri-table--<?php echo esc_attr( $context ); ?>">
			<thead>
			<tr>
				<th class="mmm-nutri-table__col mmm-nutri-table__col--label"><?php echo esc_html( $config['label'] ); ?></th>
				<?php foreach ( $config['columns'] as $col => $title ) : ?>
					<th class="mmm-nutri-table__col mmm-nutri-table__col--<?php echo esc_attr( $col ); ?>"><?php echo esc_html( $title ); ?></th>
				<?php endforeach; ?>
			</tr>
			</thead>
			<tbody>
			<?php foreach ( Mixmymeals_Meal_Plan::nutrition_labels() as $key => $label ) : ?>
				<tr<?php echo in_array( $key, array( 'sugar', 'saturates' ), true ) ? ' class="mmm-nutri-table__sub mmm-nutrition-table__sub"' : ''; ?>>
					<td data-label="<?php echo esc_attr( $config['label'] ); ?>"><?php echo esc_html( $label ); ?></td>
					<?php foreach ( $config['columns'] as $col => $title ) : ?>
						<td class="mmm-nutri-table__col--<?php echo esc_attr( $col ); ?>" data-label="<?php echo esc_attr( $title ); ?>"><?php
							$printed = $data[ $col ][ $key ] ?? null;
							echo esc_html( null === $printed ? '–' : $printed );
							if ( 'per_100g' === $col && isset( $lights[ $key ] ) ) {
								echo ' ' . $this->traffic_light_markup( $lights[ $key ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built escaped.
							}
							if ( 'ri' === $col && ! empty( $ratings[ $key ] ) ) {
								echo ' ' . $this->day_rating_markup( (string) $ratings[ $key ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built escaped.
							}
						?></td>
					<?php endforeach; ?>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		/*
		 * The cooking note prints only where a factor actually moved the
		 * weight. Without it the same recipe would show other figures per
		 * 100 g the day somebody enters a factor, and nothing on the page
		 * would say why.
		 */
		foreach ( $footnotes['notes'] as $mmm_note ) :
			?>
			<p class="mmm-nutri-note mmm-nutri-note--<?php echo esc_attr( $mmm_note['kind'] ); ?>">
				<?php echo esc_html( $mmm_note['mark'] . ' ' . $mmm_note['text'] ); ?>
			</p>
		<?php endforeach; ?>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Printed figures of one column: every nutrient key through the shared
	 * formatter, null where the source has no value.
	 *
	 * @param array<string,float|null> $figures Nutrient key → figure.
	 * @return array<string,string|null>
	 */
	private function format_nutrition_column( array $figures ): array {

		$out = array();

		foreach ( array_keys( Mixmymeals_Meal_Plan::nutrition_labels() ) as $key ) {
			$out[ $key ] = isset( $figures[ $key ] )
				? Mixmymeals_Meal_Plan::format_nutrition_value( $key, (float) $figures[ $key ] )
				: null;
		}

		return $out;
	}

	/**
	 * The reference-intake column: every figure as a share, with the
	 * "max."/"min." direction the shared formatter adds.
	 *
	 * @param array<string,float|null> $figures Nutrient key → figure.
	 * @return array<string,string|null>
	 */
	private function format_nutrition_ri_column( array $figures ): array {

		$out         = array();
		$percentages = Mixmymeals_Meal_Plan::nutrition_percentages( $figures );

		foreach ( array_keys( Mixmymeals_Meal_Plan::nutrition_labels() ) as $key ) {
			$out[ $key ] = Mixmymeals_Meal_Plan::format_nutrition_percentage( $key, (float) $percentages[ $key ] );
		}

		return $out;
	}

	/**
	 * Shortcode [mmm_nutrition_traffic_lights].
	 *
	 * The lights on their own, one list item per figure that has one — the
	 * front-of-pack row, for a theme that wants it above the table rather than
	 * in it. Nothing when no figure has a light.
	 *
	 * @param array|string $atts post_id: ingredient, recipe, meal or plan;
	 *                           defaults to the current post.
	 * @return string
	 */
	public function mmm_nutrition_traffic_lights_callback( $atts = array() ): string {

		$atts    = shortcode_atts( array( 'post_id' => 0 ), $atts, 'mmm_nutrition_traffic_lights' );
		$post_id = absint( $atts['post_id'] );
		$post_id = $post_id ? $post_id : (int) get_the_ID();

		// The switch under Settings is per subject and governs the lights
		// wherever they are printed, not only inside the table.
		$context = $post_id ? Mixmymeals_Nutrition_Table::context_for_post( $post_id ) : '';

		if ( '' === $context || ! Mixmymeals_Nutrition_Table::light_enabled( $context ) ) {
			return '';
		}

		$figures = $this->traffic_light_figures( $post_id );
		$lights  = $this->traffic_lights( $post_id );
		$items   = '';

		foreach ( $figures as $key => $value ) {

			$light = $this->traffic_light_markup( $lights[ $key ] ?? array() );

			if ( '' === $light ) {
				continue;
			}

			$items .= sprintf(
				'<li class="mmm-traffic-lights__item"><span class="mmm-traffic-lights__label">%1$s</span> <span class="mmm-traffic-lights__value">%2$s</span> %3$s</li>',
				esc_html( Mixmymeals_Ingredient::nutrition_label( $key ) ),
				esc_html( Mixmymeals_Meal_Plan::format_nutrition_value( $key, (float) $value ) ),
				$light
			);
		}

		if ( '' === $items ) {
			return '';
		}

		$note = Mixmymeals_Ingredient::traffic_light_note( in_array( $context, array( 'recipe', 'meal' ), true ) );

		return '<ul class="mmm-traffic-lights" aria-label="'
		       . esc_attr__( 'Traffic light per 100 g', 'mixmymeals' ) . '">'
		       . $items . '</ul>'
		       . ( '' === $note ? '' : '<p class="mmm-nutri-note mmm-nutri-note--light">' . esc_html( $note ) . '</p>' );
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

		$figures = $this->meal_nutrition_figures( $meal );

		if ( null === $figures ) {
			return '<p>'
			       . esc_html__( 'No ingredients found for this recipe.',
					'mixmymeals' )
			       . '</p>';
		}

		$per_serving        = $figures['per_serving'];
		$per_100g           = $figures['per_100g'];
		$weight_per_serving = $figures['weight_per_serving'];
		$lights             = $this->traffic_lights( $post_id );

		/*
		 * Labels, order and formatting are the shared ones: this table printed
		 * "Fiber" and "Salt" in English on a German site for as long as it
		 * existed, and then its own rounding ("12.5g") beside the recipe's
		 * "12,5 g".
		 */
		$table = $this->render_nutrition_table(
			'meal',
			array(
				'serving'  => $this->format_nutrition_column( $per_serving ),
				'ri'       => $this->format_nutrition_ri_column( $per_serving ),
				'per_100g' => $this->format_nutrition_column( $per_100g ),
			),
			array(
				'tokens' => array( 'weight' => (string) round( $weight_per_serving ) ),
				'lights' => $lights,
				'cooked' => $figures['cooked'] ?? array(),
				'open'   => $meal->get_recipe()->get_open_products(),
			)
		);

		return '' === $table ? '' : '<div class="mmm-meal-nutrition">' . $table . '</div>';
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
				// Mahlzeiten ohne Wochentag: Gruppe zeigen und wie benennen.
				// Leer = die Einstellung entscheidet, damit derselbe Shortcode
				// auf einer Seite auch anders gesetzt werden kann.
				'show_undated'   => '',
				'undated_label'  => '',
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
		 * Under ?mmm_persons_to_add the cards keep the servings the plan
		 * stores and append what the difference does to them ("3 Portionen
		 * +1"), and link to meal pages scaled the same way. The servings per
		 * weekday only where the meals differ: on a plan feeding four
		 * throughout, the hero's single "4" says it once for every day.
		 */
		$mmm_offset     = $meal_plan->requested_servings_offset();
		$mmm_changes    = $meal_plan->servings_changes( $mmm_offset );
		$mmm_day_people = 0 === $meal_plan->servings_base() ? $meal_plan->servings_by_day() : array();

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

			/*
			 * Alles außerhalb von 1–7 landet unter 0: eine Mahlzeit ohne
			 * Wochentag ist geplant worden und zählt in Einkaufsliste und
			 * Nährwerten mit. Sie hier zu überspringen hieß, sie auf der
			 * einen Seite zu verschweigen, auf der sie stehen müsste.
			 */
			if ( $weekday < 1 || $weekday > 7 ) {
				$weekday = 0;
			}

			$grouped_meals[ $weekday ][ $type ][] = $meal;
		}

		$today = (int) current_time( 'N' );

		$currency = get_option( 'mixmymeals_currency', '€' );

		/*
		 * Der Rail läuft über Abschnitte, nicht über Wochentage: ein Tag ist
		 * einer davon, die Gruppe ohne Tag ist einer mehr. So bleibt die
		 * Kartendarstellung darunter ein einziges Stück Markup — zwei Kopien
		 * hätten sich über kurz oder lang unterschiedlich entwickelt.
		 */
		$sections = array();

		foreach ( $days as $day_number ) {
			$sections[] = array(
				'key'      => $day_number,
				'label'    => $weekday_labels[ $day_number ] ?? '',
				'is_today' => $day_number === $today,
			);
		}

		/*
		 * Preparation, on the weekday it is due rather than on the day of the
		 * meal it is for: soaking on Saturday for a Monday dinner stands
		 * under Saturday. Where that weekday is one of the plan's days the
		 * note joins it; where it is not — only work due before the plan's
		 * first day can land there — the weekday gets a section of its own
		 * ahead of the week, as an ordinary day carrying only that note.
		 */
		$prep_by_section = $meal_plan->calc_prep_by_day();
		$extra_days      = array_diff( array_keys( $prep_by_section ), $days );

		$mmm_extra_sections = array();
		foreach ( $extra_days as $mmm_day ) {
			$mmm_extra_sections[] = array(
				'key'      => $mmm_day,
				'label'    => $weekday_labels[ $mmm_day ] ?? '',
				'is_today' => $mmm_day === $today,
			);
		}
		$sections = array_merge( $mmm_extra_sections, $sections );

		$show_undated = '' === (string) $atts['show_undated']
			? Mixmymeals_Meal_Plan::undated_meals_shown()
			: filter_var( $atts['show_undated'], FILTER_VALIDATE_BOOLEAN );

		$undated_label = trim( (string) $atts['undated_label'] );

		/*
		 * Ans Ende, wie überall sonst, wo ein Tag außerhalb der Planwoche
		 * auftaucht (vgl. Mixmymeals_Meal_Plan::order_days()): die Woche liest
		 * sich von ihrem ersten Tag an, und eine Gruppe ohne Tag davor würde
		 * genau das verstellen.
		 */
		if ( $show_undated && ! empty( $grouped_meals[0] ) ) {
			$sections[] = array(
				'key'      => 0,
				'label'    => '' !== $undated_label ? $undated_label : Mixmymeals_Meal_Plan::undated_meals_label(),
				'is_today' => false,
			);
		}

		ob_start();
		?>

        <div class="mmm-plan-rail"
             data-meal-plan-id="<?php echo esc_attr( $meal_plan_id ); ?>">

			<?php foreach ( $sections as $mmm_section ) : ?>

				<?php
				$day_number = (int) $mmm_section['key'];
				$is_today   = ! empty( $mmm_section['is_today'] );

				if ( empty( $grouped_meals[ $day_number ] ) && empty( $prep_by_section[ $day_number ] ) ) {
					continue;
				}
				?>

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

                <div class="mmm-rail-day<?php echo $is_today ? ' mmm-rail-day--today' : ''; ?><?php echo 0 === $day_number ? ' mmm-rail-day--undated' : ''; ?><?php echo empty( $grouped_meals[ $day_number ] ) ? ' mmm-rail-day--prep-only' : ''; ?>">
                    <span class="mmm-rail-day__dot"></span>

                    <div class="mmm-rail-day__head">
                        <span class="mmm-rail-day__name"><?php echo esc_html( $mmm_section['label'] ); ?></span>
						<?php if ( $is_today ) : ?>
                            <span class="mmm-badge"><?php esc_html_e( 'Today', 'mixmymeals' ); ?></span>
						<?php endif; ?>
						<?php if ( isset( $mmm_day_people[ $day_number ] ) ) : ?>
                            <span class="mmm-rail-day__servings" data-plan-servings-day="<?php echo esc_attr( (string) $day_number ); ?>"><?php echo esc_html( Mixmymeals_Meal_Plan::format_people( $mmm_day_people[ $day_number ]['min'], $mmm_day_people[ $day_number ]['max'] ) ); ?><?php echo self::servings_delta_html( $mmm_changes['days'][ $day_number ] ?? null ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></span>
						<?php endif; ?>
						<?php if ( $day_head_info ) : ?>
                            <span class="mmm-rail-day__kcal mmm-rail-day__kcal--day"><?php echo esc_html( $day_head_info ); ?></span>
						<?php endif; ?>
                    </div>

					<?php if ( ! empty( $prep_by_section[ $day_number ] ) ) : ?>
						<?php
						/*
						 * A group of its own under the day, headed like the
						 * meal types below it: what is to be done that day
						 * ahead of other meals reads as one more kind of entry
						 * of the day, not as a note about the first meal.
						 */
						?>
                        <div class="mmm-rail-day__sub mmm-rail-day__sub--prep">
                            <span class="mmm-rail-day__meal"><?php esc_html_e( 'Preparations', 'mixmymeals' ); ?></span>
                        </div>
						<?php echo $this->render_prep_notes( $prep_by_section[ $day_number ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
					<?php endif; ?>

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
							$recipe    = $meal->get_recipe();
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
						$prep_time_string = self::format_prep_time( $prep_minutes );

						$meal_url   = $meal_plan->add_servings_arg( (string) get_permalink( $meal->get_id() ), $mmm_offset );
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

						/*
						 * Per meal, not per plan: an editorial plan may well
						 * contain a single sponsored recipe, and that is exactly
						 * the case the plan's own label would not cover. Resolved
						 * through the meal, which credits its recipe.
						 *
						 * Joins the card's existing chip row instead of standing
						 * on its own: the card already has a pill vocabulary, and
						 * the browse cards' overlay is no option here — this
						 * thumbnail is 124px square, 84px on phones.
						 */
						$meal_disclosure = self::disclosure_chip( (int) $meal->get_id() );

						$mmm_card_delta = (int) ( $mmm_changes['meals'][ (int) $meal->get_id() ] ?? 0 );
						?>
                        <a class="mmm-recipe-card<?php echo $thumb_id ? '' : ' mmm-recipe-card--noimg'; ?>" href="<?php echo esc_url( $meal_url ); ?>" data-meal-id="<?php echo esc_attr( (string) $meal->get_id() ); ?>">
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

								<?php
								/*
								 * Directly under the title, above the chips: it
								 * is the one thing on this card that is not
								 * true of the recipe in general, and it is why
								 * this meal is on this day.
								 */
								$mmm_meal_text = $meal->get_text();
								?>
								<?php if ( '' !== $mmm_meal_text ) : ?>
                                    <p class="mmm-recipe-card__note"><?php echo esc_html( $mmm_meal_text ); ?></p>
								<?php endif; ?>

                                <div class="mmm-recipe-card__meta">
									<?php echo $meal_disclosure; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
									<?php if ( $meal->get_servings() ) : ?>
                                        <span class="mmm-chip mmm-chip--servings"><i class="mmm-icon fa-solid fa-users" aria-hidden="true"></i><?php echo esc_html( self::meal_servings_chip_text( (int) $meal->get_servings() ) ); ?><?php echo self::servings_delta_html( array( 'min' => $mmm_card_delta, 'max' => $mmm_card_delta ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></span>
									<?php endif; ?>
									<?php if ( $prep_time_string ) : ?>
                                        <span class="mmm-chip"><i class="mmm-icon fa-solid fa-clock" aria-hidden="true"></i><?php echo esc_html( self::chip_text( 'prep_time', array( 'time' => $prep_time_string ) ) ); ?></span>
									<?php endif; ?>
									<?php if ( $cost > 0 ) : ?>
                                        <span class="mmm-chip mmm-chip--price"><i class="mmm-icon fa-solid fa-tag" aria-hidden="true"></i><?php echo esc_html( self::chip_text( 'archive_price', array( 'price' => number_format_i18n( $cost, 2 ) . ' ' . $currency ) ) ); ?></span>
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
									// The meal, not its recipe: a dish planned vegan says "Vegan".
									echo self::diet_chips( (int) $meal->get_id(), 'mmm-tag' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
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
	 * Renders a grouped shopping list for the current or specified meal plan,
	 * with the servings field above it (see render_servings_control()).
	 *
	 * The list is for the plan under ?mmm_persons_to_add — the stored plan when the
	 * address carries none — whether or not the field is shown, so a link
	 * somebody shared shows the list it was shared for.
	 *
	 * @param array|string $atts Shortcode attributes: show_details,
	 *                           sort_by_category, meal_plan_id,
	 *                           servings_control (default on), cart_url
	 *                           (where "change single days" leads).
	 * @return string
	 */
	public function mmm_shopping_list_for_a_single_meal_plan_callback( $atts = []
	): string {

		$atts = shortcode_atts(
			[
				'show_details'     => true,
				'sort_by_category' => true,
				'meal_plan_id'     => null,
				'servings_control' => true,
				'cart_url'         => '',
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
		$offset        = $meal_plan_obj->requested_servings_offset();
		$list          = $this->render_shopping_list(
			$meal_plan_obj->with_servings_offset( $offset ),
			$show_details,
			$sort_by_category
		);

		$with_control = filter_var( $atts['servings_control'], FILTER_VALIDATE_BOOLEAN )
			&& 'mmm_meal_plan' === get_post_type( $meal_plan_id )
			&& $meal_plan_obj->get_meal_rows();

		if ( ! $with_control ) {
			return $list;
		}

		/*
		 * The field and the list in one wrapper, which frontend.js finds and
		 * refills from the `mmm_plan_servings` answer. The first answer is
		 * this one, rendered here, so the page works without the script.
		 */
		return '<div class="mmm-plan-shopping" data-plan-shopping="' . esc_attr( (string) $meal_plan_id ) . '"'
			. ' data-show-details="' . ( $show_details ? '1' : '0' ) . '"'
			. ' data-sort-by-category="' . ( $sort_by_category ? '1' : '0' ) . '">'
			. $this->render_servings_control( $meal_plan_obj, $offset, (string) $atts['cart_url'] )
			. '<div class="mmm-plan-shopping__list" data-plan-shopping-list>' . $list . '</div>'
			. '</div>';
	}

	/**
	 * The shopping list of a plan, as [mmm_shopping_list_for_a_single_meal_plan]
	 * and the `mmm_plan_servings` answer print it.
	 *
	 * @param Mixmymeals_Meal_Plan $meal_plan_obj    The plan — saved, or derived by with_servings_offset().
	 * @param bool                 $show_details     Each line opens to the recipes it comes from.
	 * @param bool                 $sort_by_category Grouped by ingredient category.
	 * @return string
	 */
	private function render_shopping_list( Mixmymeals_Meal_Plan $meal_plan_obj, bool $show_details, bool $sort_by_category ): string {

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

					<?php foreach ( $ings as $key => $data ) :

						$ing_id         = (int) ( $data['ingredient_id'] ?? $key );
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
						             . '</span> '
						             . '<span class="mmm-shopping-item-amount">'
						             . esc_html( $needed_amount )
						             . '</span>'
						             /*
						              * Behind the amount, not behind the name:
						              * the note qualifies the whole line, and
						              * wedged between name and quantity it
						              * split the two things a reader scans for.
						              */
						             . ( $is_optional
								? ' <span class="mmm-ingredient-optional">('
									. esc_html__( 'optional', 'mixmymeals' ) . ')</span>'
								: '' );

						/*
						 * The packet the plan is made of, where the whole plan
						 * agrees on one — a recipe's own choice first, then the
						 * ingredient's reference. A suggestion, not an
						 * instruction, but the figures on this page assume it,
						 * so somebody buying something else should at least
						 * know which packet the numbers came from.
						 */
						$reference_label = (string) ( $data['product'] ?? '' );

						if ( '' !== $reference_label ) {
							$item_html .= ' <span class="mmm-shopping-item-product">'
								. esc_html( $reference_label ) . '</span>';
						}

						/*
						 * A line of its own for the meals cooked as a diet —
						 * "Hackfleisch … für vegan" beside the plain one. Named
						 * even where the packet is, because the packet's name
						 * does not always say why it is on the list.
						 */
						$diet_text = self::shopping_item_diet_text( $data );

						if ( '' !== $diet_text ) {
							$item_html .= ' <span class="mmm-shopping-item-diet">'
								. esc_html( $diet_text ) . '</span>';
						}
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
	 * The servings field above a plan's shopping list.
	 *
	 * Adds the same number of people to every meal — a difference, never a
	 * factor (see Mixmymeals_Meal_Plan::with_servings_offset()). One quiet
	 * line rather than a panel: it is a setting of the list below it, not a
	 * section of its own. A plan whose meals all agree reads "For − 5 +
	 * people", the number being the resulting count; a mixed one reads
	 * "People: − +1 + at every meal", because there no single count exists.
	 * What the difference does per meal and per day is appended to the
	 * servings the rail already prints ("3 Portionen +1"), so the field does
	 * not repeat it.
	 *
	 * Built to work without JavaScript: − and + are links to the address of
	 * the next value. frontend.js takes them over and swaps field and list
	 * from the `mmm_plan_servings` answer.
	 *
	 * @param Mixmymeals_Meal_Plan $plan     The saved plan.
	 * @param int                  $offset   The difference in force.
	 * @param string               $cart_url Where the planner link leads after loading.
	 * @return string
	 */
	private function render_servings_control( Mixmymeals_Meal_Plan $plan, int $offset, string $cart_url ): string {

		$plan_id = $plan->get_id();
		$base    = $plan->servings_base();
		$bounds  = $plan->servings_offset_bounds();
		$page    = (string) get_permalink( $plan_id );
		$anchor  = 'mmm-plan-servings-' . $plan_id;

		$step = static function ( int $to, string $dir ) use ( $plan, $bounds, $page, $anchor ): string {

			$class = 'mmm-plan-servings__step mmm-plan-servings__step--' . $dir;
			$glyph = 'down' === $dir ? "\u{2212}" : '+';

			if ( $to < $bounds['min'] || $to > $bounds['max'] ) {
				return '<span class="' . esc_attr( $class ) . ' is-disabled" aria-hidden="true">' . esc_html( $glyph ) . '</span>';
			}

			$label = 'down' === $dir
				? __( 'One person fewer at every meal', 'mixmymeals' )
				: __( 'One person more at every meal', 'mixmymeals' );

			return '<a class="' . esc_attr( $class ) . '" rel="nofollow"'
				. ' href="' . esc_url( $plan->add_servings_arg( $page, $to ) . '#' . $anchor ) . '"'
				. ' data-persons="' . esc_attr( $plan->servings_query_value( $to ) ) . '"'
				. ' aria-label="' . esc_attr( $label ) . '">' . esc_html( $glyph ) . '</a>';
		};

		if ( $base > 0 ) {
			$count    = max( 1, $base + $offset );
			$value    = number_format_i18n( $count );
			/* translators: %s: − the number of people +. Keep the %s. */
			$sentence = _n( 'For %s person', 'For %s people', $count, 'mixmymeals' );
		} else {
			$value    = 0 === $offset ? '±0' : Mixmymeals_Meal_Plan::format_servings_delta( $offset, $offset );
			/* translators: %s: − the change +. Keep the %s. */
			$sentence = __( 'People: %s at every meal', 'mixmymeals' );
		}

		$controls = $step( $offset - 1, 'down' )
			. '<output class="mmm-plan-servings__value">' . esc_html( $value ) . '</output>'
			. $step( $offset + 1, 'up' );

		$parts = explode( '%s', $sentence, 2 );
		$line  = ( '' !== trim( $parts[0] ) ? '<span class="mmm-plan-servings__text">' . esc_html( trim( $parts[0] ) ) . '</span>' : '' )
			. '<span class="mmm-plan-servings__controls" role="group" aria-label="' . esc_attr__( 'Number of people', 'mixmymeals' ) . '">' . $controls . '</span>'
			. ( isset( $parts[1] ) && '' !== trim( $parts[1] ) ? '<span class="mmm-plan-servings__text">' . esc_html( trim( $parts[1] ) ) . '</span>' : '' );

		// Changing single days is the planner's job; this hands the plan
		// over — with the difference applied — through the existing action
		// and its confirmation, since it replaces the visitor's planner.
		$planner = '<span class="mmm-plan-servings__planner">'
			. esc_html__( 'Change single days?', 'mixmymeals' ) . ' '
			. '<a href="#" class="mmm-meal-plan-actions__item--load-into-cart mmm-plan-servings__planner-link"'
			. ' data-meal-plan-id="' . esc_attr( (string) $plan_id ) . '"'
			. ' data-redirect-url="' . esc_attr( esc_url_raw( $cart_url ) ) . '"'
			. ' data-persons="' . esc_attr( $plan->servings_query_value( $offset ) ) . '">'
			. esc_html__( 'Adjust in the planner', 'mixmymeals' ) . '</a></span>';

		return '<div class="mmm-plan-servings mmm-plan-servings--' . ( $base > 0 ? 'uniform' : 'mixed' ) . '"'
			. ' id="' . esc_attr( $anchor ) . '" data-offset="' . esc_attr( (string) $offset ) . '"'
			. ' data-base="' . esc_attr( (string) $base ) . '"'
			. ' data-min="' . esc_attr( (string) $bounds['min'] ) . '" data-max="' . esc_attr( (string) $bounds['max'] ) . '">'
			. '<span class="mmm-plan-servings__line">' . $line . '</span>'
			. $planner
			. '</div>';
	}

	/**
	 * The change a difference makes, appended to a number of servings:
	 * "<span class="mmm-servings-delta">+1</span>". Always printed where the
	 * plan page may need it, hidden while there is nothing to say, so
	 * frontend.js only has to set its text.
	 *
	 * @param array{min:int, max:int}|null $change Lowest and highest change, or null for none.
	 * @return string
	 */
	private static function servings_delta_html( ?array $change ): string {

		$text = $change ? Mixmymeals_Meal_Plan::format_servings_delta( $change['min'], $change['max'] ) : '';

		return ' <span class="mmm-servings-delta"' . ( '' === $text ? ' hidden' : '' ) . '>' . esc_html( $text ) . '</span>';
	}

	/**
	 * AJAX: a plan's shopping list for a different number of people.
	 *
	 * The page's first answer is rendered by the shortcode; this is every
	 * later one. `persons_to_add` is checked by the plan exactly as the
	 * address is (Mixmymeals_Meal_Plan::parse_servings_value()), and
	 * everything that shows an amount comes back with it: the field, the
	 * list, the address, the Bring! link, and the change appended to the
	 * hero's servings chip, to each day's servings and to each meal card,
	 * with the card's link. The per-person figures — nutrition per
	 * day and person, the lights, the day rating — do not depend on the
	 * number of people and are not part of the answer. Read-only, for
	 * guests too; only published plans answer.
	 *
	 * @return void
	 */
	public function mmm_plan_servings_ajax_callback(): void {

		check_ajax_referer( 'mmm_frontend_nonce', 'nonce' );

		$plan_id = isset( $_POST['meal_plan_id'] ) ? absint( $_POST['meal_plan_id'] ) : 0;
		$post    = get_post( $plan_id );

		if ( ! $post || 'mmm_meal_plan' !== $post->post_type || 'publish' !== $post->post_status || post_password_required( $post ) ) {
			wp_send_json_error( array( 'message' => __( 'Meal Plan not found.', 'mixmymeals' ) ), 404 );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated by parse_servings_value().
		$raw  = isset( $_POST['persons_to_add'] ) ? wp_unslash( $_POST['persons_to_add'] ) : '';
		$plan = new Mixmymeals_Meal_Plan( $plan_id );

		$offset  = (int) $plan->parse_servings_value( is_string( $raw ) ? substr( $raw, 0, 8 ) : '' );
		$shown   = $plan->with_servings_offset( $offset );
		$changes = $plan->servings_changes( $offset );

		$cart_url = isset( $_POST['cart_url'] ) ? esc_url_raw( wp_unslash( $_POST['cart_url'] ) ) : '';

		$meals = array();
		foreach ( $changes['meals'] as $meal_id => $delta ) {
			$meals[ $meal_id ] = array(
				'url'   => $plan->add_servings_arg( (string) get_permalink( $meal_id ), $offset ),
				'delta' => Mixmymeals_Meal_Plan::format_servings_delta( $delta, $delta ),
			);
		}

		$days = array();
		foreach ( $changes['days'] as $day => $range ) {
			$days[ $day ] = Mixmymeals_Meal_Plan::format_servings_delta( $range['min'], $range['max'] );
		}

		wp_send_json_success(
			array(
				'offset'        => $offset,
				'param'         => Mixmymeals_Meal_Plan::SERVINGS_QUERY_VAR,
				'value'         => $plan->servings_query_value( $offset ),
				'url'           => $plan->add_servings_arg( (string) get_permalink( $plan_id ), $offset ),
				'control'       => $this->render_servings_control( $plan, $offset, $cart_url ),
				'list'          => $this->render_shopping_list(
					$shown,
					! empty( $_POST['show_details'] ),
					! empty( $_POST['sort_by_category'] )
				),
				'bring'         => Mixmymeals_Meal_Plan::bring_deeplink( $plan_id, $offset ),
				'plan_delta'    => Mixmymeals_Meal_Plan::format_servings_delta( $changes['plan']['min'], $changes['plan']['max'] ),
				'days'          => $days,
				'meals'         => $meals,
			)
		);
	}

	/**
	 * A plan's address with the request's ?mmm_persons_to_add kept — for links from a
	 * meal page back to its plan, so the reader returns to the list they
	 * left. Invalid values are not carried.
	 *
	 * @param int $plan_id Meal plan post ID.
	 * @return string
	 */
	private static function plan_url_with_servings( int $plan_id ): string {

		$plan = new Mixmymeals_Meal_Plan( $plan_id );

		return $plan->add_servings_arg( (string) get_permalink( $plan_id ), $plan->requested_servings_offset() );
	}

	/**
	 * The servings chip of one meal: "4 Portionen", in the site's wording.
	 *
	 * @param int $servings Servings.
	 * @return string Plain text.
	 */
	private static function meal_servings_chip_text( int $servings ): string {

		return self::chip_text(
			'servings',
			array(
				'count' => number_format_i18n( $servings ),
				/* translators: the word after a number of servings. */
				'unit'  => _n( 'Serving', 'Servings', $servings, 'mixmymeals' ),
			)
		);
	}

	/**
	 * The servings chip of a plan: one number or a range, '' for no meals.
	 *
	 * @param Mixmymeals_Meal_Plan $plan The plan as stored; a difference is appended, not counted in.
	 * @return string Plain text.
	 */
	private static function plan_servings_chip_text( Mixmymeals_Meal_Plan $plan ): string {

		$range = $plan->get_servings_range_string();

		return '' === $range ? '' : self::chip_text( 'plan_servings', array( 'range' => $range ) );
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

		$recipe = $meal->get_recipe();

		if ( ! $recipe->is_valid() || empty( $recipe->get_steps() ) ) {
			return '';
		}

		return '<button type="button" class="mmm-cook-mode-start">'
		       . esc_html__( 'Start cooking mode', 'mixmymeals' )
		       . '</button>';
	}

	/**
	 * The diet a shopping-list line is bought for, as printed beside it —
	 * "(Vegan)" — or '' for a line of the recipes as written.
	 *
	 * Mixmymeals_Meal_Plan::calc_shopping_list() gives such a line only where
	 * a meal's diet chose its packet, and every rendering of the list (page,
	 * mail, PDF) words it through here.
	 *
	 * @param array $data One line of calc_shopping_list().
	 * @return string Plain text; escaping is the caller's.
	 */
	public static function shopping_item_diet_text( array $data ): string {

		$label = Mixmymeals_Diet::label( (string) ( $data['diet'] ?? '' ) );

		if ( '' === $label ) {
			return '';
		}

		/* translators: %s: diet name, e.g. "Vegan" — the meals a shopping-list line is bought for. */
		return sprintf( _x( '(%s)', 'shopping list line bought for a diet', 'mixmymeals' ), $label );
	}

	/**
	 * Packet and diet of a shopping-list line in one string, for renderings
	 * that have a single slot under the name (the mails): "Like Meat (Vegan)".
	 *
	 * @param array $data One line of calc_shopping_list().
	 * @return string Plain text.
	 */
	public static function shopping_item_product_text( array $data ): string {

		return trim( (string) ( $data['product'] ?? '' ) . ' ' . self::shopping_item_diet_text( $data ) );
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
				<?php $mmm_prep = Mixmymeals_Recipe::step_prep( (array) $step ); ?>
                <div class="mmm-step<?php echo $mmm_prep ? ' mmm-step--prep' : ''; ?>" id="<?php echo esc_attr( self::step_anchor( (int) $index ) ); ?>">

                    <div class="mmm-step-header">
						<?php echo self::step_prep_badge( $mmm_prep ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
						<?php if ( ! empty( $step['title'] ) ) : ?>
                            <h3 class="mmm-step-title">
								<?php
								echo esc_html(
									/* translators: %d: position of the step, starting at 1. */
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
                                    <?php echo esc_html( get_the_title( $ing['id'] ) ); ?>
                                </span>

									<?php if ( $line ) : ?>
                                        <span class="mmm-ingredient-amount">
                                        <?php echo esc_html( $line ); ?>
                                    </span>
									<?php endif; ?>
									<?php if ( $is_optional ) : ?><span class="mmm-ingredient-optional">(<?php echo esc_html__( 'optional', 'mixmymeals' ); ?>)</span><?php endif; ?>
									<?php
									echo $this->row_product_markup( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built escaped.
										(int) $ing['id'],
										(int) ( $ing['product_id'] ?? 0 ),
										! empty( $ing['product_open'] ) ? (string) ( $ing['implemented_for'] ?? '' ) : ''
									);
									?>
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
		$lights                = $this->traffic_lights( $post_id );

		/*
		 * Labels, order and formatting come from Mixmymeals_Meal_Plan, the same
		 * source the plan page, the PDF and the subscription mail use. The order
		 * is a food label's — energy, fat, carbohydrate, of which sugars, fibre,
		 * protein, salt — so a recipe reads like the panel on a packet rather
		 * than in its own sequence.
		 */
		$table = $this->render_nutrition_table(
			'recipe',
			array(
				'serving'  => $this->format_nutrition_column( $nutrition_per_serving ),
				'ri'       => $this->format_nutrition_ri_column( $nutrition_per_serving ),
				'per_100g' => $this->format_nutrition_column( $nutrition_per_100g ),
			),
			array(
				'tokens' => array( 'weight' => (string) round( (float) $nutrition_per_serving['weight_per_serving'] ) ),
				'lights' => $lights,
				'cooked' => $recipe->get_cooked_ingredients(),
			)
		);

		return '' === $table ? '' : '<div class="mmm-recipe-nutrition">' . $table . '</div>';
	}



	/**
	 * Builds the "Add to Planner" control for a recipe — the single source of
	 * this button's markup. Everything that opens the planner dialog carries the
	 * class mmm-recipe-actions__item--cart plus data-recipe-id/data-servings
	 * (and data-diets where the recipe offers a choice of diet);
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

		/*
		 * Always a button. The card variant was a <span> with a click handler:
		 * not focusable, no role, nothing a keyboard or a screen reader could
		 * reach — the primary action of the whole archive. The argument stays
		 * because it still picks the class set, and the card keeps its look:
		 * the theme sets this control's background, border and display with
		 * !important either way.
		 */
		$element = 'button';
		$shape   = ( isset( $args['element'] ) && 'button' === $args['element'] ) ? 'button' : 'span';

		$defaults = array(
			// Card default keeps the generic action-item look; the hero passes
			// its own button classes.
			'class'    => 'button' === $shape ? 'mmm-btn mmm-btn--primary' : 'mmm-recipe-actions__item',
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

		/*
		 * The name travels with the button so the confirmation after planning
		 * can say which dish it was. Reading it back out of the card would tie
		 * the script to a markup it does not own.
		 */
		/*
		 * The diets the dish can be planned as, so the dialog settles the
		 * meal right away — the choices the planner and the meal offer
		 * (Mixmymeals_Recipe::get_diet_choices()), first one preselected,
		 * none where the recipe offers no choice.
		 */
		$diets = array();

		foreach ( ( new Mixmymeals_Recipe( $recipe_id ) )->get_diet_choices() as $slug ) {
			$diets[] = array( $slug, Mixmymeals_Diet::choice_label( $slug ) );
		}

		return sprintf(
			'<%1$s%2$s class="%3$s" data-recipe-id="%4$d" data-servings="%5$d" data-recipe-title="%6$s"%7$s>%8$s</%1$s>',
			$element,
			' type="button"',
			esc_attr( $classes ),
			$recipe_id,
			$servings,
			esc_attr( get_the_title( $recipe_id ) ),
			$diets ? ' data-diets="' . esc_attr( (string) wp_json_encode( $diets ) ) . '"' : '',
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
			$energy_text = self::chip_text(
				'archive_energy',
				array( 'kcal' => number_format_i18n( round( $nutrition['calories'] ) ) )
			);

			$items[] = '<span class="mmm-meta__item mmm-meta__item--calories">'
			           . esc_html( $energy_text ) . '</span>';
		}

		// -------------------------
		// Preparation Time
		// -------------------------
		if ( $prep_minutes > 0 ) {
			$prep_string = self::format_prep_time( $prep_minutes );

			$items[] = '<span class="mmm-meta__item mmm-meta__item--prep-time">'
			           . esc_html( self::chip_text( 'prep_time', array( 'time' => $prep_string ) ) )
			           . '</span>';
		}

		// -------------------------
		// Cost per Serving
		// -------------------------
		$cost = $recipe->calculate_cost_per_serving();
		if ( $cost > 0 ) {
			$currency = get_option( 'mixmymeals_currency', '€' );
			$price_text = self::chip_text(
				'archive_price',
				array( 'price' => number_format_i18n( $cost, 2 ) . ' ' . $currency )
			);

			$items[]  = '<span class="mmm-meta__item mmm-meta__item--cost">'
			            . esc_html( $price_text )
			            . '</span>';
		}

		// -------------------------
		// Recipe Categories
		// -------------------------
		// A category is a category — see build_recipe_archive_meta_chips() for
		// why one called "vegan" no longer becomes a diet chip.
		$terms = get_the_terms( $post_id, 'mmm_recipe_category' );
		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			usort( $terms, fn( $a, $b ) => strcasecmp( $a->name, $b->name ) );
			foreach ( $terms as $term ) {
				$items[] = '<span class="mmm-meta__item mmm-meta__item--category">'
				           . esc_html( self::chip_text( 'category', array( 'category' => $term->name ) ) )
				           . '</span>';
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
		$mmm_diet = self::diet_chips( (int) $recipe->get_id(), 'mmm-meta__item' );
		if ( '' !== $mmm_diet ) {
			$items[] = $mmm_diet;
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
				// Deliberately untranslated empty defaults: the wording comes
				// from the shortcode attributes. Wrapping "" in __() does not
				// return "" — an empty msgid is the catalogue header, so a
				// translated site printed the .po metadata into the badge.
				'label_singular' => '',
				'label_plural'   => '',
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
	 * One recipe in the planner.
	 *
	 * Both lists — the unassigned one and the seven days — rendered this the
	 * same way, as two copies of fifty lines that had already drifted apart in
	 * nothing but indentation. One builder, so the day select below exists
	 * once rather than twice.
	 *
	 * @param int                  $index    Cart index.
	 * @param array<string, mixed> $item     Cart row.
	 * @param array<string,string> $types    Meal types, key => label.
	 * @param array<int,string>    $days     Weekdays, 1-7 => label.
	 * @param int                  $day      The list this item sits in, 0 = unassigned.
	 * @return string Escaped markup.
	 */
	private function render_plan_item( int $index, array $item, array $types, array $days, int $day ): string {

		$recipe_id = intval( $item['recipe_id'] ?? 0 );
		$title     = get_the_title( $recipe_id );
		$thumb     = get_the_post_thumbnail_url( $recipe_id, 'thumbnail' );
		$type      = (string) ( $item['type'] ?? '' );
		$servings  = (int) ( $item['servings'] ?? 2 );

		ob_start();
		?>
		<li class="mmm-plan-item"
			data-index="<?php echo esc_attr( (string) $index ); ?>"
			data-recipe-id="<?php echo esc_attr( (string) $recipe_id ); ?>"
			draggable="true">

			<div class="mmm-plan-item-drag" aria-hidden="true">&#9776;</div>

			<?php if ( $thumb ) : ?>
				<img class="mmm-plan-item-thumb" src="<?php echo esc_url( $thumb ); ?>" alt="">
			<?php endif; ?>

			<div class="mmm-plan-item-content">

				<div class="mmm-plan-item-title"><?php echo esc_html( $title ); ?></div>

				<div class="mmm-plan-item-controls">

					<?php
					/*
					 * The day, as a control rather than only as a place to drop
					 * into. Dragging is the only way to assign one today, and
					 * HTML5 drag and drop does not fire from a touch — so on a
					 * phone the day could not be set at all, while the dialog
					 * that adds a recipe defaults to none. That put every
					 * recipe in the unassigned list with no way out.
					 *
					 * It moves the item between the lists exactly as a drop
					 * does, because the day is read from the containing list
					 * when the plan is created, not from the item.
					 */
					?>
					<select class="mmm-plan-item-day" data-field="day"
						aria-label="<?php esc_attr_e( 'Day', 'mixmymeals' ); ?>">
						<option value="0"<?php selected( $day, 0 ); ?>>
							<?php esc_html_e( 'No day', 'mixmymeals' ); ?>
						</option>
						<?php foreach ( $days as $day_key => $day_label ) : ?>
							<option value="<?php echo esc_attr( (string) $day_key ); ?>"<?php selected( $day, $day_key ); ?>>
								<?php echo esc_html( $day_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>

					<select class="mmm-plan-item-type" data-field="type"
						aria-label="<?php esc_attr_e( 'Meal type', 'mixmymeals' ); ?>">
						<?php
						/*
						 * The dialog offers "no type"; without the same option
						 * here the select had nothing to show for an item that
						 * has none, so it fell back to the first entry.
						 */
						?>
						<option value=""<?php selected( $type, '' ); ?>>
							<?php esc_html_e( 'No type', 'mixmymeals' ); ?>
						</option>
						<?php foreach ( $types as $key => $label ) : ?>
							<option value="<?php echo esc_attr( (string) $key ); ?>"<?php selected( $type, $key ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>

					<input type="number" class="mmm-plan-item-servings" data-field="servings"
						min="1" value="<?php echo esc_attr( (string) $servings ); ?>"
						aria-label="<?php esc_attr_e( 'Servings', 'mixmymeals' ); ?>">

					<?php
					/*
					 * How the dish is cooked, only where there is a choice: a
					 * recipe that can be cooked vegan with other packets than
					 * the ones it names. Then always one of them — there is no
					 * "as in the recipe", which is the state that leaves the
					 * shopping open. The choice belongs to the planning, like
					 * the servings.
					 */
					$diet_choices = ( new Mixmymeals_Recipe( $recipe_id ) )->get_diet_choices();
					$diet         = Mixmymeals_Cart::clean_diet( $recipe_id, (string) ( $item['diet'] ?? '' ) );

					if ( $diet_choices ) :
						?>
					<select class="mmm-plan-item-diet" data-field="diet"
						aria-label="<?php esc_attr_e( 'Diet', 'mixmymeals' ); ?>">
						<?php foreach ( $diet_choices as $slug ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>"<?php selected( $diet, $slug ); ?>>
								<?php echo esc_html( Mixmymeals_Diet::choice_label( $slug ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<?php endif; ?>
				</div>

			</div>

			<button class="mmm-plan-item-remove" type="button"
				aria-label="<?php esc_attr_e( 'Remove item', 'mixmymeals' ); ?>"
				data-action="remove">&times;</button>

		</li>
		<?php
		return (string) ob_get_clean();
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

		/*
		 * The one vocabulary, not a second copy of it. This list had four
		 * entries against the five the rest of the plugin knows, and no empty
		 * one at all — so a recipe added without a type showed "Breakfast"
		 * here, the first option of a select that had nothing else to fall
		 * back to, and "Other" could not be set at all. A value nobody chose
		 * went into the plan on the next save.
		 */
		$types = Mixmymeals_Meal::get_plaintext_meal_type_map();

		// Welche Tage schon belegt sind — nur für den Anfangszustand des
		// Ablage-Hinweises; ab dann übernimmt CSS (:has), damit Drag & Drop den
		// Hinweis ohne Reload richtig ein- und ausblendet.
		$days_with_items = array();
		foreach ( $items as $item ) {
			$days_with_items[ intval( $item['day'] ?? 0 ) ] = true;
		}

		// The cart as a plan, for the figures under each day.
		$mmm_day_ratings = Mixmymeals_Meal_Plan::from_meal_rows( $items )->calc_day_ratings();

		// …and for the preparation due on each weekday.
		$mmm_prep = $this->planner_prep_notes( $items );

		ob_start();
		?>

        <div class="mmm-plan-builder">

            <!-- Unassigned Items -->
            <div class="mmm-plan-unassigned">
                <h3><?php echo esc_html__( 'Unassigned Recipes',
						'mixmymeals' ); ?></h3>

                <ul class="mmm-plan-day-list" data-day-list="0">

					<?php
					foreach ( $items as $index => $item ) :

						if ( ! empty( $item['day'] ) ) {
							continue;
						}

						echo $this->render_plan_item( (int) $index, $item, $types, $days, 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the builder.

					endforeach;
					?>

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
                            <p class="mmm-plan-empty__line">
								<?php esc_html_e( 'Your planner is still empty.', 'mixmymeals' ); ?>
                                <a class="mmm-plan-empty__link"
                                    href="<?php echo esc_url( $recipe_archive ); ?>">
									<?php esc_html_e( 'Find recipes', 'mixmymeals' ); ?>
                                </a>
                            </p>

							<?php
							/*
							 * A finished plan as a starting point. Building a
							 * week from nothing is seven passes through the
							 * dialog; taking one that exists is a tap, and the
							 * machinery for it has been there all along — the
							 * same control the plan pages use, with the same
							 * confirmation, because it replaces the planner.
							 *
							 * Only on the empty state: once something is in
							 * the planner, an offer to clear it is a trap.
							 */
							$mmm_template_args = array(
								'post_type'      => 'mmm_meal_plan',
								'post_status'    => 'publish',
								'posts_per_page' => 3,
								'orderby'        => 'date',
								'order'          => 'DESC',
								'no_found_rows'  => true,
							);

							/*
							 * Optional: nur Pläne mit Kategorie anbieten. Ein
							 * Besucherplan trägt keine — das ist dieselbe
							 * Definition, nach der
							 * Mixmymeals_Cleanup::delete_uncategorized_meal_plans()
							 * sie wieder abräumt, und es wäre eine seltsame
							 * Vorlage, einen Plan anzubieten, den der nächste
							 * Cron-Lauf löscht.
							 *
							 * Die Klausel geht in die Abfrage und nicht als
							 * Filter hinterher: bei drei Posts würde ein
							 * nachträglich aussortierter Plan seinen Platz
							 * behalten und die Liste auf zwei schrumpfen
							 * lassen.
							 */
							if ( 'on' === (string) get_option( 'mixmymeals_planner_templates_require_category', '' ) ) {
								// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
								$mmm_template_args['tax_query'] = array(
									array(
										'taxonomy' => 'mmm_meal_plan_category',
										'operator' => 'EXISTS',
									),
								);
							}

							$mmm_templates = get_posts(
								Mixmymeals_Post_Types::exclude_unlisted( $mmm_template_args )
							);

							if ( $mmm_templates ) :
								?>
                                <div class="mmm-plan-templates">
                                    <span class="mmm-plan-templates__lead">
										<?php esc_html_e( 'Or start from a finished plan:', 'mixmymeals' ); ?>
                                    </span>
                                    <ul class="mmm-plan-templates__list">
										<?php foreach ( $mmm_templates as $mmm_template ) : ?>
                                            <li class="mmm-plan-templates__item">
                                                <span class="mmm-plan-templates__name">
													<?php echo esc_html( get_the_title( $mmm_template->ID ) ); ?>
                                                </span>
												<?php
												echo $this->render_meal_plan_card_meta( (int) $mmm_template->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
												?>
                                                <button type="button"
                                                    class="mmm-meal-plan-actions__item mmm-meal-plan-actions__item--load-into-cart mmm-plan-templates__use"
                                                    data-meal-plan-id="<?php echo esc_attr( (string) $mmm_template->ID ); ?>"
                                                    data-redirect-url="">
													<?php esc_html_e( 'Use as a template', 'mixmymeals' ); ?>
                                                </button>
                                            </li>
										<?php endforeach; ?>
                                    </ul>
                                </div>
							<?php endif; ?>
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

                        <?php
                        /*
                         * One frame around the day's recipes and its figures:
                         * the row under the list belongs to that day, and
                         * standing outside the list's frame it read as a note
                         * about the whole planner. The list stays the drop
                         * target; the box only draws the frame.
                         */
                        ?>
                        <div class="mmm-plan-day-box">

                        <div class="mmm-plan-day-prep" data-day-prep="<?php echo esc_attr( (string) $day_key ); ?>"<?php echo '' === $mmm_prep[ $day_key ] ? ' hidden' : ''; ?>>
                            <?php echo $mmm_prep[ $day_key ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built escaped. ?>
                        </div>

                        <ul class="mmm-plan-day-list"
                            data-day-list="<?php echo esc_attr( $day_key ); ?>">

							<?php
							foreach ( $items as $index => $item ) :

								if ( intval( $item['day'] ?? 0 ) !== $day_key ) {
									continue;
								}

								echo $this->render_plan_item( (int) $index, $item, $types, $days, $day_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the builder.

							endforeach;
							?>

                            <?php
                            /*
                             * Ablage-Hinweis für diesen Tag. Steht immer im
                             * Markup; sichtbar ist er nur, solange der Tag leer
                             * ist. Anfangszustand aus PHP, danach CSS (:has) —
                             * sonst bliebe er nach Drag & Drop oder × stehen.
                             *
                             * Zwei Wortlaute, weil es zwei Bedienwege gibt: Am
                             * Telefon feuert HTML5-Drag-and-Drop nicht aus einer
                             * Berührung, dort führt nur das Tag-Feld auf der
                             * Karte hierher. Ein Hinweis, der zum Ziehen
                             * auffordert, nennt dem Besucher also genau den Weg,
                             * den sein Gerät nicht kann. Welcher Satz steht,
                             * entscheidet das Theme über (hover:none) — das ist
                             * eine Frage des Eingabegeräts, nicht der Breite.
                             */
                            ?>
                            <li class="mmm-plan-day-hint"<?php echo empty( $days_with_items[ $day_key ] ) ? '' : ' hidden'; ?>>
                                <span class="mmm-plan-day-hint__drag"><?php esc_html_e( 'Drag a recipe here to plan it for this day.', 'mixmymeals' ); ?></span>
                                <span class="mmm-plan-day-hint__tap"><?php esc_html_e( 'Choose this day on a recipe to plan it here.', 'mixmymeals' ); ?></span>
                            </li>

                        </ul>

                        <?php
                        /*
                         * The day's figures per person, with its rating.
                         * Rendered here for the first paint and replaced by
                         * frontend.js from the `mmm_planner_nutrition`
                         * answer whenever the planner changes. It replaces
                         * the kcal total the script used to add up itself:
                         * that was the energy row of this, computed twice.
                         */
                        $mmm_day_row = $this->render_planner_day_nutrition( $mmm_day_ratings, (int) $day_key );
                        ?>
                        <div class="mmm-plan-day-nutrition" data-day-nutrition="<?php echo esc_attr( (string) $day_key ); ?>"<?php echo '' === $mmm_day_row ? ' hidden' : ''; ?>>
                            <?php echo $mmm_day_row; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built escaped. ?>
                        </div>

                        </div>

                    </div>

				<?php endforeach; ?>

            </div>

        </div>

		<?php

		return ob_get_clean();
	}


	/**
	 * Shortcode [mmm_share_planner].
	 * Renders the "share what's in the planner right now" button, standalone so
	 * the theme can place it in the same row as Save Plan and Clear Plan. It
	 * used to be the last thing mmm_meal_plan_builder_callback() printed, which
	 * put it inside the builder's own box instead of beside the other two
	 * actions.
	 *
	 * Sharing without publishing. A finished plan becomes a post on the site;
	 * this is the other case — "look what I have for the week" as a message,
	 * with nothing created and no account asked for. Hidden while the planner
	 * is empty, because an empty planner is nothing to send.
	 *
	 * @return string
	 */
	public function mmm_share_planner_callback(): string {

		$items = ( new Mixmymeals_Cart() )->get_items();

		ob_start();
		?>
        <div class="mmm-plan-share"<?php echo empty( $items ) ? ' hidden' : ''; ?>>
            <button type="button" class="mmm-plan-share__button">
				<?php esc_html_e( 'Share planner', 'mixmymeals' ); ?>
            </button>
            <span class="mmm-plan-share__message" role="status" aria-live="polite"></span>
        </div>
		<?php

		return (string) ob_get_clean();
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
			'text'       => __( 'Save Plan', 'mixmymeals' ),
			'guest_text' => __( 'Please log in to create a meal plan.',
				'mixmymeals' ),
		], $atts, 'mmm_create_meal_plan' );

		ob_start();

		$public_mode = (string) get_option( 'mixmymeals_public_mode', '' )
		               === 'on';

		if ( $public_mode || is_user_logged_in() ) {
			self::render_plan_subscribe_offer();
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
	 * Whether the planner offers a subscription when saving, and how — the
	 * one decision both the renderer and mmm_create_meal_plan_ajax_callback()
	 * ask, so the field a visitor sees and the rule the server enforces can
	 * never disagree (a `required` the server checks but the page does not
	 * show would make the plan unsaveable).
	 *
	 * Off when subscriptions themselves are off or have no send rule
	 * (save_offer_mode_effective()), and off for a guest whose plan will be
	 * saved as a draft: create_pending() only accepts a published plan, so
	 * the address would be asked for and then go nowhere.
	 *
	 * Public since the child theme words the heading above the Save row by
	 * it ("Plan speichern und abonnieren" only where the fields are there):
	 * a third place deciding on its own would sooner or later promise a
	 * subscription the row does not offer.
	 *
	 * @return string One of the Mixmymeals_Subscriptions::SAVE_OFFER_* values.
	 */
	public static function plan_subscribe_offer_mode(): string {

		if ( ! class_exists( 'Mixmymeals_Subscriptions' ) ) {
			return 'off';
		}

		$will_draft = ! is_user_logged_in()
			&& 'on' === (string) get_option( 'mixmymeals_save_meal_plans_published_by_visitors_as_draft', '' );

		return $will_draft
			? Mixmymeals_Subscriptions::SAVE_OFFER_OFF
			: Mixmymeals_Subscriptions::save_offer_mode_effective();
	}

	/**
	 * The "subscribe while saving" offer: a first-name and an e-mail field
	 * on their own line above Save Plan whenever the offer is on — always
	 * visible, no checkbox or radio in front of them, since entering the
	 * address is the whole point and a step before that step only hid it.
	 * The name is the same one the [mmm_meal_plan_subscribe] form asks for
	 * and is always optional.
	 *
	 * Below the row sits the site's notice (Mixmymeals_Subscriptions::
	 * save_offer_notice()), either as a checkbox that has to be ticked or as
	 * plain text, per setting. In `required` mode the field is `required`
	 * and the Save button stays disabled until it holds an address (and the
	 * box is ticked, where there is one); in `optional` mode an empty field
	 * simply means no subscription.
	 *
	 * @return void
	 */
	private static function render_plan_subscribe_offer(): void {

		$mode = self::plan_subscribe_offer_mode();

		if ( Mixmymeals_Subscriptions::SAVE_OFFER_OFF === $mode ) {
			return;
		}

		$required    = Mixmymeals_Subscriptions::SAVE_OFFER_REQUIRED === $mode;
		$placeholder = $required
			? __( 'Your e-mail', 'mixmymeals' )
			: __( 'Your e-mail (optional)', 'mixmymeals' );
		// Never required, whatever the mode: the name only greets in the mails.
		$name_label  = __( 'Your first name (optional)', 'mixmymeals' );
		$notice      = Mixmymeals_Subscriptions::save_offer_notice();
		?>
        <div class="mmm-plan-subscribe" data-subscribe-mode="<?php echo esc_attr( $mode ); ?>">
            <div class="mmm-plan-subscribe__fields">
                <input type="text"
                       class="mmm-plan-subscribe-name"
                       autocomplete="given-name"
                       maxlength="100"
                       placeholder="<?php echo esc_attr( $name_label ); ?>"
                       aria-label="<?php echo esc_attr( $name_label ); ?>">
                <input type="email"
                       class="mmm-plan-subscribe-email"
                       autocomplete="email"
                       placeholder="<?php echo esc_attr( $placeholder ); ?>"
                       aria-label="<?php echo esc_attr( $placeholder ); ?>"<?php echo $required ? ' required' : ''; ?>>
            </div>
			<?php if ( Mixmymeals_Subscriptions::save_offer_notice_is_checkbox() ) : ?>
                <label class="mmm-plan-subscribe__notice mmm-plan-subscribe__consent">
                    <input type="checkbox" class="mmm-plan-subscribe-agree">
                    <span><?php echo wp_kses_post( $notice ); ?></span>
                </label>
			<?php else : ?>
                <p class="mmm-plan-subscribe__notice"><?php echo wp_kses_post( $notice ); ?></p>
			<?php endif; ?>
        </div>
		<?php
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

		/*
		 * The subscribe-while-saving offer, checked before anything is
		 * created or counted against the visitor's hourly quota — a plan that
		 * cannot be saved without an address must not be half-saved, and a
		 * typo in the address must not cost a plan from the limit. The mode
		 * is recomputed here rather than trusted from the client: the
		 * setting may have changed since the page loaded.
		 */
		$sub_mode  = self::plan_subscribe_offer_mode();
		$sub_email = '';
		$sub_name  = '';
		$sub_agree = false;

		if ( Mixmymeals_Subscriptions::SAVE_OFFER_OFF !== $sub_mode ) {

			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per field below.
			$subscribe_post = isset( $_POST['subscribe'] ) && is_array( $_POST['subscribe'] ) ? wp_unslash( $_POST['subscribe'] ) : array();
			$sub_email      = isset( $subscribe_post['email'] ) ? sanitize_email( trim( (string) $subscribe_post['email'] ) ) : '';
			$sub_raw_email  = isset( $subscribe_post['email'] ) ? trim( (string) $subscribe_post['email'] ) : '';
			$sub_name       = isset( $subscribe_post['name'] ) ? sanitize_text_field( (string) $subscribe_post['name'] ) : '';

			// A plain notice asks for no tick: entering the address is the act.
			$sub_agree = ! Mixmymeals_Subscriptions::save_offer_notice_is_checkbox()
				|| ( isset( $subscribe_post['agree'] ) && '1' === sanitize_text_field( (string) $subscribe_post['agree'] ) );

			$sub_required = Mixmymeals_Subscriptions::SAVE_OFFER_REQUIRED === $sub_mode;

			if ( '' === $sub_raw_email && $sub_required ) {
				wp_send_json_error(
					[
						'code'    => 'subscribe_email_required',
						'message' => __( 'Please enter your e-mail address to save the plan.', 'mixmymeals' ),
					],
					400
				);
			}

			if ( '' !== $sub_raw_email && ( '' === $sub_email || ! is_email( $sub_email ) ) ) {
				wp_send_json_error(
					[
						'code'    => 'subscribe_email_invalid',
						'message' => __( 'Please enter a valid e-mail address.', 'mixmymeals' ),
					],
					400
				);
			}

			if ( '' !== $sub_email && ! $sub_agree ) {
				wp_send_json_error(
					[
						'code'    => 'subscribe_consent_required',
						'message' => __( 'Please confirm your consent to continue.', 'mixmymeals' ),
					],
					400
				);
			}
		}

		// Quota to limit meal plan posting per IP for non-logged-in visitors:
		$is_guest = ! is_user_logged_in();
		if ( $public_mode && $is_guest ) {

			$ip     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
			$ip_key = 'mmm_rl_create_plan_ip_' . wp_hash( $ip ); // Keyed, so the counter cannot be reversed to the IP.

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

		// Sanitised element-wise into $clean_items further down — recipe IDs
		// through absint, servings through (int), type through
		// sanitize_text_field. The sniff only sees the array as it arrives.
		$items = isset( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per element below.

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
					/* translators: %d: highest number of recipes a plan may hold. */
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
				// Checked again by set_diet() against the recipe.
				'diet'      => sanitize_key( (string) ( $item['diet'] ?? '' ) ),
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
			// Always: a recipe offering a choice is settled on save.
			$meal->set_diet( $item['diet'] );
		}

		// Find out where to redirect now:
		$redirect_url = get_permalink( $meal_plan_id );

		if ( $post_status === 'draft' ) {
			$page_id
				          = (int) get_option( 'mixmymeals_page_id_to_redirect_if_new_plan_is_draft',
				0 );
			$redirect_url = $page_id > 0 ? get_permalink( $page_id )
				: home_url( '/' );
		} else {
			/*
			 * A success page, when one is configured. The plan's ID travels
			 * with it as ?mmm_plan=: a page that congratulates somebody on a
			 * plan it cannot link to is a dead end, and the visitor has no
			 * other way back to what they just built.
			 *
			 * Drafts do not get this. A draft is not publicly readable, so the
			 * link would 404 for exactly the anonymous visitor it is meant for.
			 */
			$success_page = (int) get_option( 'mixmymeals_page_id_to_redirect_after_plan_created', 0 );

			if ( $success_page > 0 ) {
				$success_url = get_permalink( $success_page );

				if ( $success_url ) {
					$redirect_url = add_query_arg( 'mmm_plan', $meal_plan_id, $success_url );
				}
			}
		}

		// Plan created successfully — empty the cart so it doesn't carry over.
		( new Mixmymeals_Cart() )->clear();

		/*
		 * The offer, redeemed — with what was validated above. Only a
		 * published plan can be subscribed to; plan_subscribe_offer_mode()
		 * already keeps a guest draft from being offered the field at all.
		 */
		$subscribed = false;

		if ( '' !== $sub_email && $sub_agree && 'publish' === $post_status ) {
			$sub_id     = Mixmymeals_Subscriptions::create_pending( $meal_plan_id, $sub_email, $sub_name );
			$subscribed = ! is_wp_error( $sub_id );
		}

		// Respond:
		wp_send_json_success( [
			'code'         => 'created',
			'message'      => $subscribed
				? __( 'Meal Plan created! Please check your inbox to confirm the subscription.', 'mixmymeals' )
				: __( 'Meal Plan created!', 'mixmymeals' ),
			'meal_plan_id' => $meal_plan_id,
			'redirect_url' => $redirect_url,
			'post_status'  => $post_status,
			'subscribed'   => $subscribed,
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

		$recipe = $meal->get_recipe();

		$meal_servings   = max( 1.0, (float) $meal->get_shown_servings() );
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
	/**
	 * The article a recipe row names, ready to print beside the ingredient.
	 *
	 * Only what the row itself pins — deliberately not the ingredient's
	 * reference product. The reference is the site's default for figures; the
	 * pin is the recipe saying "this dish is made with that packet", and only
	 * the second is a statement about the dish worth putting in its ingredient
	 * list. The shopping list shows both, because there the question is what to
	 * buy. Validated through Mixmymeals_Ingredient::pinned_product_id(), which
	 * is the same "did the recipe decide" the product comparison asks before
	 * dropping an ingredient.
	 *
	 * @param int $ingredient_id The ingredient of the row.
	 * @param int $pinned        The row's product_id.
	 * @return string '' when the row pins nothing usable.
	 */
	private function pinned_product_label( int $ingredient_id, int $pinned ): string {

		$product_id = Mixmymeals_Ingredient::pinned_product_id( $ingredient_id, $pinned );

		return $product_id > 0 ? ( new Mixmymeals_Product( $product_id ) )->label() : '';
	}

	/**
	 * What an ingredient list prints after a row's name about its packet.
	 *
	 * The pinned product where the row names one (pinned_product_label()).
	 * A row a meal's diet left open — several packets hold the diet, and the
	 * figures are taken from the least favourable — names no packet but the
	 * diet, "(Vegan)", the shopping list's wording: the reader is told what
	 * to look for, not which brand to buy.
	 *
	 * @param int    $ingredient_id The ingredient of the row.
	 * @param int    $pinned        The row's product_id.
	 * @param string $open_diet     The diet of an open row, '' otherwise.
	 * @return string Markup, '' when there is nothing to say.
	 */
	private function row_product_markup( int $ingredient_id, int $pinned, string $open_diet ): string {

		if ( '' !== $open_diet ) {
			$text = self::shopping_item_diet_text( array( 'diet' => $open_diet ) );

			return '' === $text ? '' : '<span class="mmm-ingredient-product mmm-ingredient-product--open">' . esc_html( $text ) . '</span>';
		}

		$label = $this->pinned_product_label( $ingredient_id, $pinned );

		return '' === $label ? '' : '<span class="mmm-ingredient-product">' . esc_html( $label ) . '</span>';
	}

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
					// Named only while every step of the dish names the same
					// one; two steps pinning different packets of one
					// ingredient is not something a single line can state.
					'pinned'   => null,
				];
			}

			// An open row is marked by its diet, a decided one by its packet.
			$pinned = ! empty( $ing['product_open'] )
				? 'open:' . (string) ( $ing['implemented_for'] ?? '' )
				: (string) (int) ( $ing['product_id'] ?? 0 );

			$ingredients[ $id ]['pinned'] = null === $ingredients[ $id ]['pinned'] || $ingredients[ $id ]['pinned'] === $pinned
				? $pinned
				: '0';

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
							<?php echo esc_html( $ingredient['name'] ); ?>
						</span>
							<?php
							$mmm_pinned = (string) ( $ingredient['pinned'] ?? '0' );
							echo $this->row_product_markup( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built escaped.
								(int) $ingredient['id'],
								(int) $mmm_pinned,
								0 === strpos( $mmm_pinned, 'open:' ) ? substr( $mmm_pinned, 5 ) : ''
							);
							?>

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
							<?php if ( ! empty( $ingredient['optional'] ) ) : ?><span class="mmm-ingredient-optional">(<?php echo esc_html__( 'optional', 'mixmymeals' ); ?>)</span><?php endif; ?>
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
	 * Renders an action bar (e-mail, Bring!, load into cart, plus whatever
	 * add-ons contribute) for the current meal plan.
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

		$html = '';
		foreach ( self::meal_plan_action_items( (int) $meal_plan_id, (string) $atts['cart_url'] ) as $key => $item ) {

			$class = 'mmm-meal-plan-actions__item mmm-meal-plan-actions__item--' . $key;

			// An action without an address is a script's job, as "Adjust"
			// always was: a span, so nothing navigates before the script runs.
			if ( '' === $item['url'] ) {
				$html .= '<span class="' . esc_attr( $class ) . '"' . $item['attrs'] . '>'
					. esc_html( $item['label'] ) . '</span>';
				continue;
			}

			$html .= '<a href="' . esc_url( $item['url'] ) . '" class="' . esc_attr( $class ) . '"'
				. ( $item['new_tab'] ? ' target="_blank" rel="noopener noreferrer"' : '' )
				. $item['attrs'] . '>' . esc_html( $item['label'] ) . '</a>';
		}

		return '<div class="mmm-meal-plan-actions">' . $html . '</div>';
	}

	/**
	 * The actions offered for one meal plan, as both action rows print them —
	 * [mmm_meal_plan_actions] and the buttons in [mmm_meal_plan_hero].
	 *
	 * One list for both, so an action an add-on contributes appears in either
	 * place the theme uses. The PDF download moved into the MixMyMeals
	 * Printer add-on (3.61.0) and comes back through the filter below.
	 *
	 * @param int    $plan_id  Meal plan post ID.
	 * @param string $cart_url Where "Adjust" leads after loading the plan.
	 * @return array<string,array{label:string,icon:string,url:string,new_tab:bool,attrs:string}>
	 *         Keyed by action; `attrs` is a ready, escaped attribute string.
	 */
	public static function meal_plan_action_items( int $plan_id, string $cart_url = '' ): array {

		$title = get_the_title( $plan_id );
		$url   = get_permalink( $plan_id );

		// Bring! and the planner get the list the reader is looking at.
		$mmm_plan   = new Mixmymeals_Meal_Plan( $plan_id );
		$mmm_offset = $mmm_plan->requested_servings_offset();

		/* translators: %s: title of the meal plan. */
		$mail_subject = sprintf( __( 'Meal Plan: %s', 'mixmymeals' ), $title );
		/* translators: %s: public URL of the meal plan. */
		$mail_body = sprintf( __( "My Meal Plan:\n\n%s", 'mixmymeals' ), $url );

		$items = array(
			'mail'           => array(
				'label' => __( 'Send via Email', 'mixmymeals' ),
				'icon'  => 'envelope',
				'url'   => sprintf( 'mailto:?subject=%s&body=%s', rawurlencode( $mail_subject ), rawurlencode( $mail_body ) ),
			),
			// Points at the plan's import view, see Mixmymeals_Meal_Plan::bring_deeplink().
			'bring'          => array(
				'label'   => __( 'Export to Bring!', 'mixmymeals' ),
				'icon'    => 'paper-plane',
				'url'     => Mixmymeals_Meal_Plan::bring_deeplink( $plan_id, $mmm_offset ),
				'new_tab' => true,
			),
			'load-into-cart' => array(
				'label' => __( 'Adjust', 'mixmymeals' ),
				'icon'  => 'pen-to-square',
				'data'  => array(
					'meal-plan-id' => (string) $plan_id,
					'redirect-url' => $cart_url,
					'persons'      => $mmm_plan->servings_query_value( $mmm_offset ),
				),
			),
		);

		/**
		 * Filters the actions offered for a meal plan.
		 *
		 * Each item is keyed by a short name (it becomes the modifier class
		 * `mmm-meal-plan-actions__item--<key>`) and carries `label`, and
		 * optionally `icon` (a Font Awesome solid icon name without `fa-`),
		 * `url` (empty for an action a script handles), `new_tab`, `data`
		 * (data attributes without the `data-` prefix) and `servings` (true:
		 * the address follows ?mmm_persons_to_add — the host appends the
		 * request's difference and frontend.js keeps it current, so the
		 * action gets what the reader is looking at; MixMyMeals Printer sets
		 * it for the PDF). Order is print order.
		 *
		 * @param array $items   Actions so far.
		 * @param int   $plan_id Meal plan post ID.
		 */
		$items = (array) apply_filters( 'mixmymeals_meal_plan_actions', $items, $plan_id );

		$clean = array();
		foreach ( $items as $key => $item ) {

			$key = sanitize_html_class( (string) $key );
			if ( '' === $key || ! is_array( $item ) || '' === (string) ( $item['label'] ?? '' ) ) {
				continue;
			}

			$attrs = '';
			foreach ( (array) ( $item['data'] ?? array() ) as $name => $value ) {
				$name = sanitize_key( (string) $name );
				if ( '' !== $name ) {
					$attrs .= ' data-' . $name . '="' . esc_attr( (string) $value ) . '"';
				}
			}

			$url = (string) ( $item['url'] ?? '' );

			if ( '' !== $url && ! empty( $item['servings'] ) ) {
				$url    = $mmm_plan->add_servings_arg( $url, $mmm_offset );
				$attrs .= ' data-servings-link';
			}

			$clean[ $key ] = array(
				'label'   => (string) $item['label'],
				'icon'    => sanitize_html_class( (string) ( $item['icon'] ?? '' ) ),
				'url'     => $url,
				'new_tab' => ! empty( $item['new_tab'] ),
				'attrs'   => $attrs,
			);
		}

		return $clean;
	}

	/**
	 * The anchor a recipe step carries on the recipe and the meal page —
	 * what a preparation note links to.
	 *
	 * @param int $index Position in Mixmymeals_Recipe::get_steps(), from 0.
	 * @return string
	 */
	public static function step_anchor( int $index ): string {
		return 'mmm-step-' . ( $index + 1 );
	}

	/**
	 * "Preparation · 2 days before", in a step's header.
	 *
	 * @param array|null $prep From Mixmymeals_Recipe::step_prep().
	 * @return string '' for an ordinary step.
	 */
	public static function step_prep_badge( ?array $prep ): string {

		if ( ! $prep ) {
			return '';
		}

		return '<span class="mmm-step-prep"><i class="mmm-icon fa-solid fa-hourglass-half" aria-hidden="true"></i>'
		       . esc_html(
				   sprintf(
					   /* translators: %s: lead time, e.g. "2 days before". */
					   __( 'Preparation · %s', 'mixmymeals' ),
					   Mixmymeals_Recipe::prep_label( $prep )
				   )
			   )
		       . '</span>';
	}

	/**
	 * The preparation due on one day of a plan or of the planner:
	 * "Vorarbeiten für <meal>: <step>", one line per step.
	 *
	 * Each note links to the step it names: on the meal's page where the
	 * plan has one, on the recipe's page in the planner, where no meal
	 * exists yet.
	 *
	 * @param array $tasks Tasks due that day.
	 * @return string '' without tasks.
	 */
	private function render_prep_notes( array $tasks ): string {

		if ( ! $tasks ) {
			return '';
		}

		$html = '<ul class="mmm-prep-notes">';

		foreach ( $tasks as $task ) {

			$url = $task['meal_id'] > 0 ? get_permalink( $task['meal_id'] ) : get_permalink( $task['recipe_id'] );
			$url = $url ? $url . '#' . self::step_anchor( (int) $task['step_index'] ) : '';

			$step_title = '' !== $task['step_title']
				? $task['step_title']
				/* translators: %d: position of the step, starting at 1. */
				: sprintf( __( 'Step %d', 'mixmymeals' ), (int) $task['step_index'] + 1 );

			$meal_title = get_the_title( $task['meal_id'] > 0 ? $task['meal_id'] : $task['recipe_id'] );

			$step_html = $url
				? '<a class="mmm-prep-note__task" href="' . esc_url( $url ) . '">' . esc_html( $step_title ) . '</a>'
				: '<span class="mmm-prep-note__task">' . esc_html( $step_title ) . '</span>';

			// Only the step is the link: it is what the link leads to, and a
			// whole line of link read louder than a note should.
			$html .= '<li class="mmm-prep-note">'
			         . '<i class="mmm-icon fa-solid fa-hourglass-half" aria-hidden="true"></i> '
			         . '<span class="mmm-prep-note__label">'
			         /* translators: %s: title of the meal the preparation is for. */
			         . esc_html( sprintf( __( 'Preparation for %s:', 'mixmymeals' ), $meal_title ) )
			         . '</span> ' . $step_html
			         . '</li>';
		}

		return $html . '</ul>';
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
			$recipe = $entry['meal']->get_recipe();
			if ( ! $recipe->is_valid() ) {
				continue;
			}
			$nutrition = $recipe->calculate_nutrition_per_serving();
			$kcal     += (int) round( $nutrition['calories'] ?? 0 );
		}

		if ( $kcal <= 0 ) {
			return '';
		}

		// Through the energy chip's wording: it states the same fact, and two
		// wordings for one fact on one page is what this registry removes.
		return self::chip_text( 'energy', array( 'kcal' => number_format_i18n( $kcal ) ) );
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
	 * The chip row of a meal plan: disclosure, diets, days, recipes, servings
	 * and price, in that order. Shared by [mmm_meal_plan_hero] and
	 * [mmm_meal_plan_embed], so a plan teased in a blog post states the same
	 * figures as the plan page it links to.
	 *
	 * The servings chip carries `mmm-chip--servings` so frontend.js can
	 * restate it when the plan page's servings field changes.
	 *
	 * @param int      $plan_id Meal plan post ID.
	 * @param int|null $offset  People added to every meal (?mmm_persons_to_add):
	 *                          the hero passes the request's and the chip
	 *                          appends the change ("3–9 Portionen +1"); an
	 *                          embed passes nothing and gets the plan as stored.
	 * @return string Escaped chips, without a wrapper.
	 */
	private static function meal_plan_stat_chips( int $plan_id, ?int $offset = null ): string {

		$plan                 = new Mixmymeals_Meal_Plan( $plan_id );
		$num_of_days          = $plan->count_days();
		$num_of_meals         = $plan->count_meals();
		$servings_text = self::plan_servings_chip_text( $plan );
		$avg_cost             = $plan->calculate_avg_cost_per_serving();
		$currency             = get_option( 'mixmymeals_currency', '€' );

		// Kennzeichnung zuerst: sie reiht sich in die Chip-Zeile ein, steht
		// darin aber vorn, damit sie nicht zwischen Kennzahlen untergeht.
		$out  = self::disclosure_chip( $plan_id );
		$out .= self::diet_chips( $plan_id );

		if ( $num_of_days ) {
			$out .= self::stat_chip(
				'calendar-days',
				self::chip_text(
					'plan_days',
					array(
						'count' => number_format_i18n( $num_of_days ),
						/* translators: the word after a number of days a plan lasts. */
						'unit'  => _n( 'Day', 'Days', $num_of_days, 'mixmymeals' ),
					)
				)
			);
		}

		if ( $num_of_meals ) {
			$out .= self::stat_chip(
				'book',
				self::chip_text(
					'plan_recipes',
					array(
						'count' => number_format_i18n( $num_of_meals ),
						/* translators: the word after a number of recipes on a plan. */
						'unit'  => _n( 'Recipe', 'Recipes', $num_of_meals, 'mixmymeals' ),
					)
				)
			);
		}

		if ( '' !== $servings_text ) {
			$out .= self::stat_chip(
				'users',
				$servings_text,
				'mmm-chip--servings',
				null === $offset ? '' : self::servings_delta_html( $plan->servings_changes( $offset )['plan'] )
			);
		}

		if ( $avg_cost > 0 ) {
			$out .= self::stat_chip(
				'euro-sign',
				self::chip_text( 'price', array( 'price' => number_format_i18n( $avg_cost, 2 ) . ' ' . $currency ) ),
				'mmm-chip--price'
			);
		}

		return $out;
	}

	/**
	 * The chip row of a recipe: disclosure, diets, servings, time, energy,
	 * price and keeping. Shared by [mmm_recipe_hero] and [mmm_recipe_embed].
	 *
	 * @param int $recipe_id Recipe post ID.
	 * @return string Escaped chips, without a wrapper.
	 */
	private static function recipe_stat_chips( int $recipe_id ): string {

		$recipe   = new Mixmymeals_Recipe( $recipe_id );
		$servings = (int) $recipe->get_servings();
		$cost     = $recipe->calculate_cost_per_serving();
		$currency = get_option( 'mixmymeals_currency', '€' );

		$nutrition = $recipe->calculate_nutrition_per_serving();
		$kcal      = (int) round( $nutrition['calories'] ?? 0 );

		$prep_time_string = self::format_prep_time( $recipe->get_preparation_time() );

		// Kennzeichnung zuerst: sie reiht sich in die Chip-Zeile ein, steht
		// darin aber vorn, damit sie nicht zwischen Kennzahlen untergeht.
		$out  = self::disclosure_chip( $recipe_id );
		$out .= self::diet_chips( $recipe_id );

		if ( $servings ) {
			$out .= self::stat_chip(
				'users',
				self::chip_text(
					'servings',
					array(
						'count' => number_format_i18n( absint( $servings ) ),
						/* translators: the word after a number of servings. */
						'unit'  => _n( 'Serving', 'Servings', $servings, 'mixmymeals' ),
					)
				)
			);
		}

		if ( $prep_time_string ) {
			$out .= self::stat_chip( 'clock', self::chip_text( 'prep_time', array( 'time' => $prep_time_string ) ) );
		}

		if ( $kcal > 0 ) {
			$out .= self::stat_chip( 'fire', self::chip_text( 'energy', array( 'kcal' => number_format_i18n( $kcal ) ) ) );
		}

		if ( $cost > 0 ) {
			$out .= self::stat_chip(
				'euro-sign',
				self::chip_text( 'price', array( 'price' => number_format_i18n( $cost, 2 ) . ' ' . $currency ) ),
				'mmm-chip--price'
			);
		}

		// Haltbarkeit zuletzt: sie beschreibt, was nach dem Essen mit dem
		// Gericht ist, und gehoert damit hinter die Kennzahlen des Essens.
		$out .= self::keeping_chip( $recipe_id );

		return $out;
	}

	/**
	 * One figure chip of a hero row.
	 *
	 * @param string $icon     Font Awesome icon name, without the fa- prefix.
	 * @param string $text     Plain chip text; escaped here.
	 * @param string $modifier Extra class, e.g. mmm-chip--price.
	 * @param string $after    Ready, escaped markup after the text (servings_delta_html()).
	 * @return string
	 */
	private static function stat_chip( string $icon, string $text, string $modifier = '', string $after = '' ): string {

		return '<span class="' . esc_attr( trim( 'mmm-chip mmm-chip--stat ' . $modifier ) ) . '">'
			. '<i class="mmm-icon fa-solid fa-' . esc_attr( $icon ) . '" aria-hidden="true"></i>'
			. esc_html( $text )
			. $after
			. '</span>';
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

		$title = get_the_title( $plan_id );

		// The same actions as [mmm_meal_plan_actions], add-ons' included.
		$actions = self::meal_plan_action_items( $plan_id, (string) $atts['cart_url'] );

		ob_start();
		?>

        <section class="mmm-plan-hero">
            <div class="mmm-plan-hero__blob"></div>
            <h1><?php echo esc_html( $title ); ?></h1>

            <div class="mmm-plan-hero__meta">
				<?php echo self::meal_plan_stat_chips( (int) $plan_id, ( new Mixmymeals_Meal_Plan( (int) $plan_id ) )->requested_servings_offset() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
            </div>

				<?php if ( has_excerpt( $plan_id ) ) : ?>
                <p class="mmm-plan-hero__lead"><?php echo esc_html( get_the_excerpt( $plan_id ) ); ?></p>
				<?php endif; ?>

            <div class="mmm-plan-hero__actions">
				<?php
				foreach ( $actions as $key => $action ) :
					/*
					 * Own modifier, not the action bar's. The child theme puts a
					 * Font Awesome glyph before `.mmm-meal-plan-actions__item--mail`
					 * and `--bring` through ::before, meant for the bar, which has
					 * no icons of its own; on these buttons, which have an <i>,
					 * it drew a second one as an empty box (3.61.0). Only an
					 * action a script handles keeps the shared class, since that
					 * is what the script finds it by — "Adjust" carried it here
					 * before 3.61.0 too.
					 */
					$class = 'mmm-btn mmm-btn--ghost mmm-plan-hero__action mmm-plan-hero__action--' . $key;
					if ( '' === $action['url'] ) {
						$class .= ' mmm-meal-plan-actions__item--' . $key;
					}
					?>
                <a href="<?php echo esc_url( '' !== $action['url'] ? $action['url'] : '#' ); ?>"
                   class="<?php echo esc_attr( $class ); ?>"<?php
					echo $action['new_tab'] ? ' target="_blank" rel="noopener noreferrer"' : '';
					echo $action['attrs']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in meal_plan_action_items().
					?>>
					<?php if ( '' !== $action['icon'] ) : ?><i class="mmm-icon fa-solid fa-<?php echo esc_attr( $action['icon'] ); ?>" aria-hidden="true"></i><?php endif; ?><?php echo esc_html( $action['label'] ); ?>
                </a>
				<?php endforeach; ?>
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
				<?php echo self::recipe_stat_chips( (int) $recipe_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
            </div>

			<?php if ( has_excerpt( $recipe_id ) ) : ?>
                <p class="mmm-plan-hero__lead"><?php echo esc_html( get_the_excerpt( $recipe_id ) ); ?></p>
			<?php endif; ?>

            <div class="mmm-plan-hero__actions">
				<?php
				// Same renderer as the recipe cards, only as a full button —
				// so there is one place that knows this control's markup.
				$mmm_planner_button = $this->render_add_to_planner_button(
					(int) $recipe_id,
					array(
						'element'  => 'button',
						'servings' => $servings,
					)
				);

				echo $mmm_planner_button; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the renderer.
				?>
            </div>
        </section>
		<?php

		return ob_get_clean();
	}

	/**
	 * Shortcode [mmm_recipe_embed id=""]: one recipe as a card inside any
	 * other content — a blog post, a page. Picture, title, the hero's chip
	 * row, the recipe's excerpt and a link to it, plus "Add to Planner".
	 *
	 * Attributes: id (post ID or slug, required), image, excerpt, planner
	 * (1/0, default 1), heading (h2–h4, default h3), button (link label).
	 *
	 * Structure only; the look is the theme's (.mmm-embed).
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function mmm_recipe_embed_callback( $atts = array() ): string {

		$atts = shortcode_atts(
			array(
				'id'      => '',
				'image'   => '1',
				'excerpt' => '1',
				'planner' => '1',
				'heading' => 'h3',
				'button'  => '',
			),
			$atts,
			'mmm_recipe_embed'
		);

		$recipe_id = self::embed_target( (string) $atts['id'], 'mmm_recipe' );

		if ( ! $recipe_id || ! ( new Mixmymeals_Recipe( $recipe_id ) )->is_valid() ) {
			return self::embed_missing( __( 'Recipe not found or not published.', 'mixmymeals' ) );
		}

		$media = '';
		if ( filter_var( $atts['image'], FILTER_VALIDATE_BOOLEAN ) ) {
			$media = (string) get_the_post_thumbnail(
				$recipe_id,
				'medium_large',
				array(
					'loading' => 'lazy',
					'alt'     => '',
				)
			);
		}

		$actions = '';
		if ( filter_var( $atts['planner'], FILTER_VALIDATE_BOOLEAN ) ) {
			$actions = $this->render_add_to_planner_button(
				$recipe_id,
				array(
					'element' => 'button',
					'class'   => 'mmm-btn mmm-btn--ghost mmm-embed__planner',
				)
			);
		}

		return $this->render_embed_card(
			array(
				'post_id' => $recipe_id,
				'type'    => 'recipe',
				'kicker'  => _x( 'Recipe', 'embed kicker', 'mixmymeals' ),
				'media'   => $media,
				'mosaic'  => 0,
				'rating'  => $this->mmm_recipe_rating_callback(
					array(
						'id'         => $recipe_id,
						'hide_empty' => 1,
					)
				),
				'chips'   => self::recipe_stat_chips( $recipe_id ),
				'excerpt' => filter_var( $atts['excerpt'], FILTER_VALIDATE_BOOLEAN ),
				'extra'   => '',
				'heading' => (string) $atts['heading'],
				'button'  => '' !== $atts['button'] ? (string) $atts['button'] : __( 'View recipe', 'mixmymeals' ),
				'actions' => $actions,
			)
		);
	}

	/**
	 * Shortcode [mmm_meal_plan_embed id=""]: one meal plan as a card inside
	 * any other content. Picture or mosaic of its dishes, title, the hero's
	 * chip row, the excerpt, the dishes by name and a link to the plan.
	 *
	 * Attributes: id (post ID or slug, required), image, excerpt (1/0),
	 * recipes (how many dish names to list, 0 = none, default 6),
	 * heading (h2–h4, default h3), button (link label).
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function mmm_meal_plan_embed_callback( $atts = array() ): string {

		$atts = shortcode_atts(
			array(
				'id'      => '',
				'image'   => '1',
				'excerpt' => '1',
				'recipes' => '6',
				'heading' => 'h3',
				'button'  => '',
			),
			$atts,
			'mmm_meal_plan_embed'
		);

		$plan_id = self::embed_target( (string) $atts['id'], 'mmm_meal_plan' );

		if ( ! $plan_id ) {
			return self::embed_missing( __( 'Meal plan not found or not published.', 'mixmymeals' ) );
		}

		$media  = '';
		$mosaic = 0;
		if ( filter_var( $atts['image'], FILTER_VALIDATE_BOOLEAN ) ) {
			// The browse cards' rule: a mosaic from two pictures up, below that
			// the single image — one tile in a grid for three looks broken.
			$images = $this->get_meal_plan_card_images( $plan_id, 3, 'medium_large' );
			if ( count( $images ) >= 2 ) {
				$mosaic = count( $images );
				$media  = implode( '', $images );
			} else {
				$media = $this->get_meal_plan_card_thumbnail( $plan_id, 'medium_large' );
			}
		}

		return $this->render_embed_card(
			array(
				'post_id' => $plan_id,
				'type'    => 'meal-plan',
				'kicker'  => __( 'Meal Plan', 'mixmymeals' ),
				'media'   => $media,
				'mosaic'  => $mosaic,
				'rating'  => '',
				'chips'   => self::meal_plan_stat_chips( $plan_id ),
				'excerpt' => filter_var( $atts['excerpt'], FILTER_VALIDATE_BOOLEAN ),
				'extra'   => $this->render_embed_dishes( $plan_id, max( 0, (int) $atts['recipes'] ) ),
				'heading' => (string) $atts['heading'],
				'button'  => '' !== $atts['button'] ? (string) $atts['button'] : __( 'View meal plan', 'mixmymeals' ),
				'actions' => '',
			)
		);
	}

	/**
	 * Resolves the id attribute of an embed: a post ID or a slug, of the given
	 * type, published and not password protected. Unlisted posts are allowed —
	 * whoever names one in a post has chosen to show it.
	 *
	 * @param string $value     The attribute as written.
	 * @param string $post_type mmm_recipe or mmm_meal_plan.
	 * @return int Post ID, or 0.
	 */
	private static function embed_target( string $value, string $post_type ): int {

		$value = trim( $value );
		if ( '' === $value ) {
			return 0;
		}

		if ( ctype_digit( $value ) ) {
			$post = get_post( (int) $value );
		} else {
			$post = get_page_by_path( sanitize_title( $value ), OBJECT, $post_type );
		}

		if ( ! $post instanceof WP_Post
			|| $post->post_type !== $post_type
			|| 'publish' !== $post->post_status
			|| post_password_required( $post ) ) {
			return 0;
		}

		return (int) $post->ID;
	}

	/**
	 * What an embed prints when its target is gone: nothing for visitors, a
	 * line for whoever can edit the post, so a broken embed is found in the
	 * preview rather than by a reader wondering about a gap.
	 *
	 * @param string $message Plain text.
	 * @return string
	 */
	private static function embed_missing( string $message ): string {

		if ( ! current_user_can( 'edit_posts' ) ) {
			return '';
		}

		return '<p class="mmm-embed mmm-embed--missing">' . esc_html( $message ) . '</p>';
	}

	/**
	 * The dish names of a plan for its embed: each recipe once, in plan order,
	 * at most $max, and "and N more" for the rest.
	 *
	 * @param int $plan_id Meal plan post ID.
	 * @param int $max     How many names to list; 0 lists none.
	 * @return string
	 */
	private function render_embed_dishes( int $plan_id, int $max ): string {

		if ( $max <= 0 ) {
			return '';
		}

		$titles = array();
		foreach ( ( new Mixmymeals_Meal_Plan( $plan_id ) )->get_meals() as $meal_post ) {
			$recipe_id = (int) ( new Mixmymeals_Meal( $meal_post->ID ) )->get_recipe_id();
			if ( $recipe_id > 0 && ! isset( $titles[ $recipe_id ] ) && 'publish' === get_post_status( $recipe_id ) ) {
				$titles[ $recipe_id ] = get_the_title( $recipe_id );
			}
		}

		if ( empty( $titles ) ) {
			return '';
		}

		$shown = array_slice( $titles, 0, $max );
		$rest  = count( $titles ) - count( $shown );

		$out = '<ul class="mmm-embed__dishes">';
		foreach ( $shown as $title ) {
			$out .= '<li class="mmm-embed__dish">' . esc_html( $title ) . '</li>';
		}
		if ( $rest > 0 ) {
			$out .= '<li class="mmm-embed__dish mmm-embed__dish--more">'
				/* translators: %s: number of further recipes on the plan. */
				. esc_html( sprintf( _n( 'and %s more recipe', 'and %s more recipes', $rest, 'mixmymeals' ), number_format_i18n( $rest ) ) )
				. '</li>';
		}
		$out .= '</ul>';

		return $out;
	}

	/**
	 * The card both embeds share.
	 *
	 * @param array $card post_id, type, kicker, media, mosaic, rating, chips,
	 *                    excerpt (bool), extra, heading, button, actions.
	 * @return string
	 */
	private function render_embed_card( array $card ): string {

		$post_id   = (int) $card['post_id'];
		$permalink = (string) get_permalink( $post_id );
		$heading   = in_array( $card['heading'], array( 'h2', 'h3', 'h4' ), true ) ? $card['heading'] : 'h3';
		$media     = (string) $card['media'];

		$classes = 'mmm-embed mmm-embed--' . $card['type'] . ( '' === $media ? ' mmm-embed--noimg' : '' );

		$out = '<aside class="' . esc_attr( $classes ) . '">';

		if ( '' !== $media ) {
			// Out of the tab order and hidden from screen readers: the title
			// carries the same link, and one link per card is enough to hear.
			$out .= '<a class="mmm-embed__media" href="' . esc_url( $permalink ) . '" tabindex="-1" aria-hidden="true">';
			if ( $card['mosaic'] ) {
				$out .= '<span class="mmm-embed__mosaic mmm-embed__mosaic--' . (int) $card['mosaic'] . '">' . $media . '</span>';
			} else {
				$out .= $media;
			}
			$out .= '</a>';
		}

		$out .= '<div class="mmm-embed__body">';
		$out .= '<p class="mmm-embed__kicker">' . esc_html( $card['kicker'] ) . '</p>';
		$out .= '<' . $heading . ' class="mmm-embed__title"><a href="' . esc_url( $permalink ) . '">'
			. esc_html( get_the_title( $post_id ) ) . '</a></' . $heading . '>';

		if ( '' !== $card['rating'] ) {
			$out .= '<div class="mmm-embed__rating">' . $card['rating'] . '</div>';
		}

		if ( '' !== $card['chips'] ) {
			$out .= '<div class="mmm-embed__meta">' . $card['chips'] . '</div>';
		}

		if ( $card['excerpt'] && has_excerpt( $post_id ) ) {
			$out .= '<p class="mmm-embed__lead">' . esc_html( get_the_excerpt( $post_id ) ) . '</p>';
		}

		$out .= $card['extra'];

		$out .= '<div class="mmm-embed__actions">'
			. '<a class="mmm-btn mmm-btn--primary mmm-embed__link" href="' . esc_url( $permalink ) . '">'
			. esc_html( $card['button'] ) . '</a>'
			. $card['actions']
			. '</div>';

		$out .= '</div></aside>';

		return $out;
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

			// Square source, the avatar/photo classes some themes and
			// microformat parsers key on, plus the box's own class the theme
			// sizes and rounds. Without a picture the media block is simply
			// omitted — which is now the only thing that happens, since the
			// box no longer falls back to a remote avatar service.
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

			$bio = wpautop( esc_html( trim( (string) get_the_author_meta( 'description', $author_id ) ) ) );

			/*
			 * No picture for a WordPress author, and deliberately no
			 * get_avatar() either. That call is served by Gravatar unless a
			 * plugin intercepts it, which means every visitor's browser
			 * requests an image from Automattic in the US and hands over its
			 * IP address plus a hash of the author's e-mail — a third-party
			 * transfer on every recipe page, to show a picture nobody on this
			 * site chose. An author who wants one uses the author box's own
			 * picture field, which is served from this site.
			 */
			$avatar = '';
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
					<?php echo $avatar; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() returns escaped markup. ?>
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
			// Both commercial types share one wording. What the law asks is
			// that the reader sees it is advertising — not which internal
			// arrangement produced it.
			case Mixmymeals_Post_Meta::DISCLOSURE_SPONSORED:
			case Mixmymeals_Post_Meta::DISCLOSURE_COOPERATION:
				$text = self::chip_text( 'disclosure_commercial' );
				break;

			// Editorial transparency, not a legal disclosure: this one says who
			// wrote it, not that someone paid for it. Styled differently on
			// purpose so it can never be mistaken for the advertising label —
			// see requires_disclosure_label() for the legal subset.
			case Mixmymeals_Post_Meta::DISCLOSURE_GUEST:
				$text = self::chip_text( 'disclosure_guest' );
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
	 * Type and wording of a post's disclosure, or an empty array for editorial
	 * content. The single place that resolves meals to their recipe and decides
	 * whether there is anything to label at all — every rendering variant below
	 * builds on this, so they can never disagree about what gets a label.
	 *
	 * @param int $post_id Recipe or plan ID. Meals resolve to their recipe.
	 * @return array{type:string,text:string} Empty when nothing is to be shown.
	 */
	public static function disclosure_label_for( int $post_id ): array {

		$post_id = Mixmymeals_Meal::resolve_credit_source( $post_id );

		if ( ! $post_id || ! Mixmymeals_Post_Meta::is_disclosed( $post_id ) ) {
			return array();
		}

		$type = Mixmymeals_Post_Meta::get_disclosure_type( $post_id );
		$text = trim( self::disclosure_label_text( $type ) );

		return '' === $text ? array() : array(
			'type' => $type,
			'text' => $text,
		);
	}

	/**
	 * How chip wordings are stored: this plus the registry key. The settings
	 * field id is the same without the plugin's own prefix, which
	 * Mixmymeals_Settings prepends.
	 *
	 * @var string
	 */
	const CHIP_TEXT_OPTION_PREFIX = 'mixmymeals_chip_text_';

	/**
	 * The settings field id of a chip wording, before the plugin prefix.
	 *
	 * @var string
	 */
	const CHIP_TEXT_FIELD_PREFIX = 'chip_text_';

	/**
	 * The registry, built once per request.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private static $chip_texts = null;

	/**
	 * Every chip wording a site can change, and what each may contain.
	 *
	 * A chip is the shortest text on a page and the most read — a row of them
	 * is scanned rather than read — and the wording that suits one site ("4
	 * Portionen") is wrong for the next ("für 4 Personen"). Until this the only
	 * way to change one was to edit a .po file, and the change was lost on the
	 * next update.
	 *
	 * It lives in this class because this is where chips are rendered: all but
	 * two of the call sites are methods below, and a wording that sits next to
	 * the markup it words cannot drift away from it.
	 *
	 * Keyed by chip. `default` is the translated wording, so a site that
	 * changes nothing gets what the translation says; `tokens` are the
	 * placeholders that chip can carry and `required` those it is useless
	 * without; `group` only sorts the settings screen, `note` explains the case
	 * there. Filter `mixmymeals_chip_texts` so an add-on can register a chip of
	 * its own — it then gets a settings field and the sanitising for free.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function chip_texts(): array {

		if ( null !== self::$chip_texts ) {
			return self::$chip_texts;
		}

		$diet_token    = array( 'diet' => __( 'the diet, e.g. "Vegan"', 'mixmymeals' ) );
		$storage_token = array( 'storage' => __( 'how it is kept, e.g. "in the fridge"', 'mixmymeals' ) );
		$unit_token    = array( 'unit' => __( 'the matching word for the number, already in singular or plural', 'mixmymeals' ) );

		$texts = array(

			// --- Diets -------------------------------------------------
			'diet'                     => array(
				'group'    => 'diet',
				'label'    => __( 'Diet, settled', 'mixmymeals' ),
				/* translators: chip wording. {diet} is the name of a diet, e.g. "Vegan". */
				'default'  => __( '{diet}', 'mixmymeals' ),
				'tokens'   => $diet_token,
				'required' => array( 'diet' ),
			),
			'diet_capable'             => array(
				'group'    => 'diet',
				'label'    => __( 'Diet, only possible', 'mixmymeals' ),
				'note'     => __( 'Shown when the dish could be cooked this way but the products chosen do not yet say so.', 'mixmymeals' ),
				/* translators: chip wording. {diet} is the name of a diet, e.g. "Vegan". */
				'default'  => __( '{diet}-capable', 'mixmymeals' ),
				'tokens'   => $diet_token,
				'required' => array( 'diet' ),
			),

			// --- Shelf life --------------------------------------------
			'keeping_days_storage'     => array(
				'group'    => 'keeping',
				'label'    => __( 'Keeps, with storage', 'mixmymeals' ),
				/* translators: chip wording. {days} a number, {unit} day/days, {storage} e.g. "in the fridge". */
				'default'  => __( '{days} {unit} {storage}', 'mixmymeals' ),
				'tokens'   => array_merge(
					array( 'days' => __( 'the number of days', 'mixmymeals' ) ),
					$unit_token,
					$storage_token
				),
				'required' => array( 'days' ),
			),
			'keeping_days'             => array(
				'group'    => 'keeping',
				'label'    => __( 'Keeps, storage unknown', 'mixmymeals' ),
				/* translators: chip wording. {days} a number, {unit} day/days. */
				'default'  => __( 'Keeps {days} {unit}', 'mixmymeals' ),
				'tokens'   => array_merge(
					array( 'days' => __( 'the number of days', 'mixmymeals' ) ),
					$unit_token
				),
				'required' => array( 'days' ),
			),
			'keeping_same_day_storage' => array(
				'group'    => 'keeping',
				'label'    => __( 'Eat the same day, with storage', 'mixmymeals' ),
				'note'     => __( 'A shelf life of zero. Never worded as "0 days" — a zero among figures reads as a missing value.', 'mixmymeals' ),
				/* translators: chip wording. {storage} e.g. "in the fridge". */
				'default'  => __( 'Same day, {storage}', 'mixmymeals' ),
				'tokens'   => $storage_token,
				'required' => array( 'storage' ),
			),
			'keeping_same_day'         => array(
				'group'    => 'keeping',
				'label'    => __( 'Eat the same day', 'mixmymeals' ),
				'default'  => __( 'Same day', 'mixmymeals' ),
				'tokens'   => array(),
				'required' => array(),
			),
			'keeping_storage'          => array(
				'group'    => 'keeping',
				'label'    => __( 'Storage alone', 'mixmymeals' ),
				'note'     => __( 'Nobody has stated a shelf life. {storage} is the bare noun here ("Fridge"), since there is no sentence around it to carry a preposition.', 'mixmymeals' ),
				/* translators: chip wording. {storage} is the bare noun, e.g. "Fridge". */
				'default'  => __( '{storage}', 'mixmymeals' ),
				'tokens'   => $storage_token,
				'required' => array( 'storage' ),
			),

			// --- Disclosure --------------------------------------------
			'disclosure_commercial'    => array(
				'group'    => 'disclosure',
				'label'    => __( 'Paid or sponsored content', 'mixmymeals' ),
				'note'     => __( 'A legal disclosure. Softer wordings such as "in cooperation with" are widely held to be too weak.', 'mixmymeals' ),
				'default'  => __( 'Advertisement', 'mixmymeals' ),
				'tokens'   => array(),
				'required' => array(),
			),
			'disclosure_guest'         => array(
				'group'    => 'disclosure',
				'label'    => __( 'Guest contribution', 'mixmymeals' ),
				'note'     => __( 'Transparency, not a legal disclosure — and it must not look like one.', 'mixmymeals' ),
				'default'  => __( 'Guest author', 'mixmymeals' ),
				'tokens'   => array(),
				'required' => array(),
			),

			// --- Recipes and meals -------------------------------------
			'servings'                 => array(
				'group'    => 'stats',
				'label'    => __( 'Servings', 'mixmymeals' ),
				/* translators: chip wording. {count} a number, {unit} serving/servings. */
				'default'  => __( '{count} {unit}', 'mixmymeals' ),
				'tokens'   => array_merge(
					array( 'count' => __( 'the number of servings', 'mixmymeals' ) ),
					$unit_token
				),
				'required' => array( 'count' ),
			),
			'prep_time'                => array(
				'group'    => 'stats',
				'label'    => __( 'Preparation time', 'mixmymeals' ),
				/* translators: chip wording. {time} is the already formatted duration, e.g. "45 min". */
				'default'  => __( '{time}', 'mixmymeals' ),
				'tokens'   => array( 'time' => __( 'the duration, already formatted, e.g. "45 min"', 'mixmymeals' ) ),
				'required' => array( 'time' ),
			),
			'energy'                   => array(
				'group'    => 'stats',
				'label'    => __( 'Energy', 'mixmymeals' ),
				/* translators: chip wording. {kcal} is the energy per serving. */
				'default'  => __( '{kcal} kcal ps', 'mixmymeals' ),
				'tokens'   => array( 'kcal' => __( 'kilocalories per serving', 'mixmymeals' ) ),
				'required' => array( 'kcal' ),
			),
			'price'                    => array(
				'group'    => 'stats',
				'label'    => __( 'Price', 'mixmymeals' ),
				/* translators: chip wording. {price} includes the currency. */
				'default'  => __( '{price} / serving', 'mixmymeals' ),
				'tokens'   => array( 'price' => __( 'the price including the currency', 'mixmymeals' ) ),
				'required' => array( 'price' ),
			),
			'meal_day'                 => array(
				'group'    => 'stats',
				'label'    => __( 'Day of a meal', 'mixmymeals' ),
				/* translators: chip wording. {day} is a weekday, e.g. "Monday". */
				'default'  => __( '{day}', 'mixmymeals' ),
				'tokens'   => array( 'day' => __( 'the day, e.g. "Monday"', 'mixmymeals' ) ),
				'required' => array( 'day' ),
			),
			'meal_type'                => array(
				'group'    => 'stats',
				'label'    => __( 'Kind of meal', 'mixmymeals' ),
				/* translators: chip wording. {type} is breakfast, lunch and so on. */
				'default'  => __( '{type}', 'mixmymeals' ),
				'tokens'   => array( 'type' => __( 'the kind of meal, e.g. "Dinner"', 'mixmymeals' ) ),
				'required' => array( 'type' ),
			),

			// --- Meal plans --------------------------------------------
			'plan_days'                => array(
				'group'    => 'plan',
				'label'    => __( 'Length of a plan', 'mixmymeals' ),
				/* translators: chip wording. {count} a number, {unit} day/days. */
				'default'  => __( '{count} {unit}', 'mixmymeals' ),
				'tokens'   => array_merge(
					array( 'count' => __( 'the number of days', 'mixmymeals' ) ),
					$unit_token
				),
				'required' => array( 'count' ),
			),
			'plan_recipes'             => array(
				'group'    => 'plan',
				'label'    => __( 'Number of recipes', 'mixmymeals' ),
				/* translators: chip wording. {count} a number, {unit} recipe/recipes. */
				'default'  => __( '{count} {unit}', 'mixmymeals' ),
				'tokens'   => array_merge(
					array( 'count' => __( 'the number of recipes', 'mixmymeals' ) ),
					$unit_token
				),
				'required' => array( 'count' ),
			),
			'plan_servings'            => array(
				'group'    => 'plan',
				'label'    => __( 'Servings across a plan', 'mixmymeals' ),
				'note'     => __( 'A range when the meals differ, a single number when they do not.', 'mixmymeals' ),
				/* translators: chip wording. {range} is e.g. "2–4 servings". */
				'default'  => __( '{range}', 'mixmymeals' ),
				'tokens'   => array( 'range' => __( 'the servings, already formatted, e.g. "2–4 servings"', 'mixmymeals' ) ),
				'required' => array( 'range' ),
			),

			// --- Ingredients and equipment -----------------------------
			'ingredient_energy'        => array(
				'group'    => 'ingredient',
				'label'    => __( 'Energy of an ingredient', 'mixmymeals' ),
				/* translators: chip wording. {kcal} is the energy per 100 g. */
				'default'  => __( '{kcal} kcal / 100 g', 'mixmymeals' ),
				'tokens'   => array( 'kcal' => __( 'kilocalories per 100 g', 'mixmymeals' ) ),
				'required' => array( 'kcal' ),
			),
			'ingredient_price'         => array(
				'group'    => 'ingredient',
				'label'    => __( 'Price of an ingredient', 'mixmymeals' ),
				/* translators: chip wording. {price} includes the currency. */
				'default'  => __( '{price} / 100 g', 'mixmymeals' ),
				'tokens'   => array( 'price' => __( 'the price including the currency', 'mixmymeals' ) ),
				'required' => array( 'price' ),
			),
			'equipment_recipes'        => array(
				'group'    => 'ingredient',
				'label'    => __( 'Recipes using a piece of equipment', 'mixmymeals' ),
				/* translators: chip wording. {count} a number, {unit} recipe/recipes. */
				'default'  => __( 'Used in {count} {unit}', 'mixmymeals' ),
				'tokens'   => array_merge(
					array( 'count' => __( 'the number of recipes', 'mixmymeals' ) ),
					$unit_token
				),
				'required' => array( 'count' ),
			),

			// --- Cards and archives ------------------------------------
			'category'                 => array(
				'group'    => 'archive',
				'label'    => __( 'Category', 'mixmymeals' ),
				'note'     => __( 'One chip per category, on cards and on the ingredient header.', 'mixmymeals' ),
				/* translators: chip wording. {category} is the category name. */
				'default'  => __( '{category}', 'mixmymeals' ),
				'tokens'   => array( 'category' => __( 'the name of the category', 'mixmymeals' ) ),
				'required' => array( 'category' ),
			),
			'archive_ingredient_match' => array(
				'group'    => 'archive',
				'label'    => __( 'Ingredient match on a card', 'mixmymeals' ),
				'note'     => __( 'Only while somebody is searching by the ingredients they have.', 'mixmymeals' ),
				/* translators: chip wording. {hits} of {total} searched ingredients are in this dish. */
				'default'  => __( '{hits} of {total} ingredients', 'mixmymeals' ),
				'tokens'   => array(
					'hits'  => __( 'how many of the ingredients are in this dish', 'mixmymeals' ),
					'total' => __( 'how many were searched for', 'mixmymeals' ),
				),
				'required' => array( 'hits' ),
			),
			'archive_energy'           => array(
				'group'    => 'archive',
				'label'    => __( 'Energy on a card', 'mixmymeals' ),
				/* translators: chip wording. {kcal} is the energy per serving. */
				'default'  => __( '{kcal} kcal', 'mixmymeals' ),
				'tokens'   => array( 'kcal' => __( 'kilocalories per serving', 'mixmymeals' ) ),
				'required' => array( 'kcal' ),
			),
			'archive_price'            => array(
				'group'    => 'archive',
				'label'    => __( 'Price on a card', 'mixmymeals' ),
				'note'     => __( 'The bare amount, as cards have no room for a unit. The per-serving wording is under "Recipes and meals".', 'mixmymeals' ),
				/* translators: chip wording. {price} includes the currency. */
				'default'  => __( '{price}', 'mixmymeals' ),
				'tokens'   => array( 'price' => __( 'the price including the currency', 'mixmymeals' ) ),
				'required' => array( 'price' ),
			),
		);

		self::$chip_texts = (array) apply_filters( 'mixmymeals_chip_texts', $texts );

		return self::$chip_texts;
	}

	/**
	 * The groups the settings screen prints as headings, in order.
	 *
	 * The order is the order of the admin menu: first the chips of a recipe
	 * page — its figures, then diet, shelf life and the disclosure, which sit
	 * in the same row — then ingredients and equipment, then plans, and the
	 * cards last because they shorten what the pages above already say.
	 * The servings and time chips are the ones a site rewords first, and
	 * they used to sit fourth, below three groups most sites never touch.
	 *
	 * @return array<string, string>
	 */
	public static function chip_text_groups(): array {

		return array(
			'stats'      => __( 'Recipes and meals', 'mixmymeals' ),
			'diet'       => __( 'Diets', 'mixmymeals' ),
			'keeping'    => __( 'Shelf life and storage', 'mixmymeals' ),
			'disclosure' => __( 'Disclosure', 'mixmymeals' ),
			'ingredient' => __( 'Ingredients and equipment', 'mixmymeals' ),
			'plan'       => __( 'Meal plans', 'mixmymeals' ),
			'archive'    => __( 'Cards and archives', 'mixmymeals' ),
		);
	}

	/**
	 * The pattern in force for a chip: what the site stored, or the default.
	 *
	 * @param string $key Registry key.
	 * @return string '' for a key nobody registered.
	 */
	public static function chip_text_pattern( string $key ): string {

		$entry = self::chip_texts()[ $key ] ?? null;

		if ( null === $entry ) {
			return '';
		}

		$stored = trim( (string) get_option( self::CHIP_TEXT_OPTION_PREFIX . $key, '' ) );

		return '' !== $stored && self::chip_text_is_usable( $stored, $entry )
			? $stored
			: (string) $entry['default'];
	}

	/**
	 * Whether a pattern can be used for a chip.
	 *
	 * It must carry every value the chip exists to state — a servings chip
	 * without {count} reads "Portionen" on every recipe, a diet chip without
	 * {diet} the same word beside every diet, and a chip that says the same
	 * thing everywhere says nothing. And it may not carry a placeholder nothing
	 * will fill, which would print its own braces on a public page: the sort of
	 * thing nobody notices on the settings screen and everybody notices on the
	 * site.
	 *
	 * {unit} is never among the required ones. It is the already-inflected word
	 * for the count, so a site writing its own noun instead is deciding, not
	 * erring — which is how German says "In {count} Rezepten" where _n() can
	 * only supply the nominative.
	 *
	 * @param string               $pattern The pattern.
	 * @param array<string, mixed> $entry   Its registry entry.
	 * @return bool
	 */
	private static function chip_text_is_usable( string $pattern, array $entry ): bool {

		foreach ( (array) ( $entry['required'] ?? array() ) as $token ) {
			if ( ! str_contains( $pattern, '{' . $token . '}' ) ) {
				return false;
			}
		}

		$known = array_keys( (array) ( $entry['tokens'] ?? array() ) );

		preg_match_all( '/\{([a-z_]+)\}/', $pattern, $found );

		foreach ( $found[1] as $token ) {
			if ( ! in_array( $token, $known, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * The finished text of one chip.
	 *
	 * Placeholders are named rather than positional: word order differs per
	 * language and per taste — "{days} Tage {storage}" and "{storage}, {days}
	 * Tage" are both reasonable — and %1$s/%2$s cannot express that choice.
	 *
	 * Returns plain text; escaping belongs to the caller, as it does for every
	 * other string this class renders.
	 *
	 * @param string                $key    Registry key.
	 * @param array<string, string> $tokens Values for the placeholders.
	 * @return string '' when the pattern renders to nothing, so a caller can
	 *                simply skip the chip.
	 */
	public static function chip_text( string $key, array $tokens = array() ): string {

		$text = self::chip_text_pattern( $key );

		if ( '' === $text ) {
			return '';
		}

		$search  = array();
		$replace = array();

		foreach ( $tokens as $name => $value ) {
			$search[]  = '{' . $name . '}';
			$replace[] = (string) $value;
		}

		$text = str_replace( $search, $replace, $text );

		/*
		 * A placeholder nobody filled leaves no trace. chip_text_is_usable()
		 * keeps unknown ones out of a stored pattern, but a default may
		 * legitimately offer one the caller has nothing for — {storage} on a
		 * dish where only the shelf life is known — and braces must never reach
		 * a page.
		 */
		$text = (string) preg_replace( '/\{[a-z_]+\}/', '', $text );

		// Removing one leaves the space that went with it.
		$text = (string) preg_replace( '/\s{2,}/', ' ', $text );

		return trim( $text );
	}

	/**
	 * Stores a chip pattern, or nothing.
	 *
	 * A pattern that cannot be used is stored as empty rather than kept: it
	 * would be ignored on render anyway, and a field showing a value that has
	 * no effect is worse than an empty one. Called from the per-field sanitise
	 * callbacks Mixmymeals_Settings registers.
	 *
	 * @param string $key   Registry key.
	 * @param mixed  $input Raw input.
	 * @return string
	 */
	public static function sanitize_chip_text( string $key, mixed $input ): string {

		$entry = self::chip_texts()[ $key ] ?? null;
		$value = trim( sanitize_text_field( (string) $input ) );

		if ( null === $entry || '' === $value ) {
			return '';
		}

		return self::chip_text_is_usable( $value, $entry ) ? $value : '';
	}

	/**
	 * The help line under a chip field: what it may contain, and any note.
	 *
	 * @param array<string, mixed> $entry Registry entry.
	 * @return string
	 */
	public static function chip_text_description( array $entry ): string {

		$parts = array();

		foreach ( (array) ( $entry['tokens'] ?? array() ) as $token => $meaning ) {

			$required = in_array( $token, (array) ( $entry['required'] ?? array() ), true );

			$parts[] = sprintf(
				/* translators: 1: placeholder, e.g. {count}. 2: what it stands for. */
				__( '%1$s — %2$s', 'mixmymeals' ),
				'{' . $token . '}',
				$required
					/* translators: %s: what a placeholder stands for. */
					? sprintf( __( '%s (required)', 'mixmymeals' ), $meaning )
					: $meaning
			);
		}

		$text = empty( $parts )
			? __( 'No placeholders; this chip states one thing.', 'mixmymeals' )
			: implode( ' · ', $parts );

		if ( ! empty( $entry['note'] ) ) {
			$text = $entry['note'] . ' ' . $text;
		}

		return $text;
	}

	/**
	 * The disclosure as one item of an existing pill row, in that row's own
	 * vocabulary: `mmm-chip` in the heroes, `mmm-meta__item` in the archive.
	 *
	 * Takes the base class rather than inventing a third look, so the label
	 * lines up with the chips already there instead of floating above them —
	 * while the modifier keeps it colour-coded as a label, not a statistic.
	 *
	 * @param int    $post_id Recipe, plan or meal ID.
	 * @param string $base    Base class of the surrounding row.
	 * @return string
	 */
	/**
	 * The diet chips of a recipe, meal or plan, ready for a chip row.
	 *
	 * Takes the surrounding row's base class, like disclosure_chip() and
	 * keeping_chip(): `mmm-chip` in the three heroes, `mmm-meta__item` in the
	 * archive rows. One helper, because the six places that showed these chips
	 * each decided for themselves what to ask and how to label it, and the
	 * heroes — which is what a single page actually renders — asked nothing at
	 * all.
	 *
	 * A meal resolves to the recipe it plans, as every other credit does. A
	 * plan answers for itself, since its diets are the intersection of its
	 * dishes rather than one dish's.
	 *
	 * Returns '' when nothing is classified, so a row stays clean.
	 *
	 * @param int    $post_id Recipe, meal or plan ID.
	 * @param string $base    Base class of the surrounding row.
	 * @return string
	 */
	public static function diet_chips( int $post_id, string $base = 'mmm-chip' ): string {

		if ( ! $post_id ) {
			return '';
		}

		$type = get_post_type( $post_id );

		if ( 'mmm_meal_plan' === $type ) {
			$plan     = new Mixmymeals_Meal_Plan( $post_id );
			$in_force = $plan->get_diets();
			$capable  = $plan->get_diets( true );
		} else {

			/*
			 * A meal answers as it is cooked: a vegan-capable chili planned
			 * vegan is vegan, and says so without the "capable".
			 */
			$recipe = 'mmm_meal' === $type
				? ( new Mixmymeals_Meal( $post_id ) )->get_recipe()
				: new Mixmymeals_Recipe( 'mmm_recipe' === $type ? $post_id : 0 );

			if ( ! $recipe->is_valid() ) {
				return '';
			}

			$in_force = $recipe->get_diets();
			$capable  = $recipe->get_diet_suitabilities();
		}

		$base = sanitize_html_class( $base );
		$out  = '';

		foreach ( Mixmymeals_Diet::chips( $in_force, $capable ) as $chip ) {

			$classes = $base . ' ' . $base . '--diet ' . $base . '--diet-' . sanitize_html_class( $chip['slug'] );

			if ( $chip['definite'] ) {
				$classes .= ' ' . $base . '--diet-definite';
			}

			/*
			 * No icon here. The chip carries the classes a theme needs to put
			 * one on either row — design is the theme's, and hard-coding a
			 * Font Awesome glyph in the plugin would decide it for every site.
			 */
			$out .= '<span class="' . esc_attr( $classes ) . '">' . esc_html( $chip['label'] ) . '</span>';
		}

		return $out;
	}


	/**
	 * How long a dish takes, as a finished string.
	 *
	 * The minutes-or-hours formatting stood inline in four places, worded the
	 * same way by hand each time. One of them is now enough — the archive card
	 * would have been the fifth.
	 *
	 * @param int $minutes Minutes; 0 means nobody has said.
	 * @return string '' when there is nothing to state.
	 */
	public static function format_prep_time( int $minutes ): string {

		if ( $minutes <= 0 ) {
			return '';
		}

		if ( $minutes < 60 ) {
			return sprintf(
				/* translators: %s: preparation time in minutes. */
				__( '%s min', 'mixmymeals' ),
				number_format_i18n( $minutes )
			);
		}

		$hours = (int) floor( $minutes / 60 );
		$rest  = $minutes % 60;

		$out = sprintf(
			/* translators: %s: preparation time in full hours. */
			__( '%s h', 'mixmymeals' ),
			number_format_i18n( $hours )
		);

		if ( $rest > 0 ) {
			$out .= ' ' . sprintf(
				/* translators: %s: remaining minutes on top of the full hours. */
				__( '%s min', 'mixmymeals' ),
				number_format_i18n( $rest )
			);
		}

		return $out;
	}

	/**
	 * One chip stating how long a dish takes.
	 *
	 * The sister of diet_chips() and keeping_chip(), taking the surrounding
	 * row's base class for the same reason. Empty when nobody has timed the
	 * dish — the archive filter treats that as "not stated", and a chip reading
	 * "0 min" would contradict it.
	 *
	 * @param int    $post_id Recipe or meal ID.
	 * @param string $base    Base class of the surrounding row.
	 * @return string
	 */
	public static function prep_time_chip( int $post_id, string $base = 'mmm-chip' ): string {

		$post_id = Mixmymeals_Meal::resolve_credit_source( $post_id );

		if ( ! $post_id || 'mmm_recipe' !== get_post_type( $post_id ) ) {
			return '';
		}

		$text = self::format_prep_time( ( new Mixmymeals_Recipe( $post_id ) )->get_preparation_time() );

		if ( '' === $text ) {
			return '';
		}

		$text = self::chip_text( 'prep_time', array( 'time' => $text ) );

		if ( '' === $text ) {
			return '';
		}

		$base = sanitize_html_class( $base );

		return sprintf(
			'<span class="%1$s %1$s--prep-time">%2$s</span>',
			esc_attr( $base ),
			esc_html( $text )
		);
	}

	/**
	 * One chip stating how much of what somebody has is in this dish.
	 *
	 * Only while an ingredient search is running, and read from the ranking the
	 * query already built — a count per card would be a query per card.
	 *
	 * @param int    $post_id Recipe ID.
	 * @param string $base    Base class of the surrounding row.
	 * @return string
	 */
	public static function ingredient_match_chip( int $post_id, string $base = 'mmm-chip' ): string {

		$tokens = self::archive_filters()['ingredients'];

		if ( empty( $tokens ) ) {
			return '';
		}

		$hits = self::rank_recipes_by_ingredients( $tokens )[ $post_id ] ?? 0;

		if ( $hits < 1 ) {
			return '';
		}

		$text = self::chip_text(
			'archive_ingredient_match',
			array(
				'hits'  => number_format_i18n( $hits ),
				'total' => number_format_i18n( count( $tokens ) ),
			)
		);

		if ( '' === $text ) {
			return '';
		}

		$base = sanitize_html_class( $base );

		return sprintf(
			'<span class="%1$s %1$s--match">%2$s</span>',
			esc_attr( $base ),
			esc_html( $text )
		);
	}

	/**
	 * One chip stating how the finished dish keeps.
	 *
	 * Shelf life and storage go into a single chip, never two: "4 Tage" beside
	 * a row of stats is a duration with no subject — a plan chip reads "7 Tage"
	 * and means something else entirely — and a condition without a duration is
	 * half an answer. Together they are one fact, and the chip row is a row of
	 * facts.
	 *
	 * Resolves a meal to the recipe it plans, like every other feature that
	 * describes a dish. Returns '' when neither field is set, which is the
	 * ordinary case for a library nobody has been through yet.
	 *
	 * @param int    $post_id Recipe or meal id.
	 * @param string $base    Base class of the surrounding chip row.
	 * @return string
	 */
	public static function keeping_chip( int $post_id, string $base = 'mmm-chip' ): string {

		$post_id = Mixmymeals_Meal::resolve_credit_source( $post_id );

		if ( ! $post_id || 'mmm_recipe' !== get_post_type( $post_id ) ) {
			return '';
		}

		$recipe = new Mixmymeals_Recipe( $post_id );

		if ( ! $recipe->is_valid() ) {
			return '';
		}

		$days    = $recipe->get_shelf_life();
		$storage = $recipe->get_storage_type();

		if ( null === $days && '' === $storage ) {
			return '';
		}

		$phrase = '' === $storage
			? ''
			: ( Mixmymeals_Post_Types::storage_phrases()[ $storage ] ?? '' );

		/*
		 * One wording per case rather than a number and a preposition joined at
		 * runtime. Word order differs per language and per site, which is why
		 * each of the four is its own configurable pattern; the storage phrase
		 * is only ever interpolated mid-string, so a pattern must not open with
		 * it in a language that capitalises the first word.
		 *
		 * Zero days keeps its own wording. A "0" among chips that read "4
		 * Portionen" and "520 kcal" looks like a missing value, and this is the
		 * one case a reader must not misread.
		 */
		if ( null === $days ) {

			// Storage alone takes the noun the select boxes use, since there is
			// no sentence around it to carry the preposition.
			$text = self::chip_text(
				'keeping_storage',
				array( 'storage' => Mixmymeals_Post_Types::storage_types()[ $storage ] ?? '' )
			);

		} elseif ( 0 === $days ) {

			$text = '' === $phrase
				? self::chip_text( 'keeping_same_day' )
				: self::chip_text( 'keeping_same_day_storage', array( 'storage' => $phrase ) );

		} elseif ( '' === $phrase ) {

			$text = self::chip_text(
				'keeping_days',
				array(
					'days' => number_format_i18n( $days ),
					/* translators: the word after the number of days a dish keeps. */
					'unit' => _n( 'day', 'days', $days, 'mixmymeals' ),
				)
			);

		} else {

			$text = self::chip_text(
				'keeping_days_storage',
				array(
					'days'    => number_format_i18n( $days ),
					/* translators: the word after the number of days a dish keeps. */
					'unit'    => _n( 'day', 'days', $days, 'mixmymeals' ),
					'storage' => $phrase,
				)
			);
		}

		if ( '' === $text ) {
			return '';
		}

		return sprintf(
			'<span class="%1$s %1$s--stat %1$s--keeping"><i class="mmm-icon fa-solid %2$s" aria-hidden="true"></i>%3$s</span>',
			esc_attr( $base ),
			esc_attr( self::keeping_icon( $storage ) ),
			esc_html( $text )
		);
	}

	/**
	 * Font Awesome class for a storage condition.
	 *
	 * A duration with no condition gets the hourglass, because that is all it
	 * is. The rest are the picture of the place, which is what makes the chip
	 * readable at a glance — the text alone would do, but this row is scanned,
	 * not read.
	 *
	 * @param string $storage Storage type key, '' when none is set.
	 * @return string
	 */
	private static function keeping_icon( string $storage ): string {

		switch ( $storage ) {
			case 'frozen':
				return 'fa-snowflake';
			case 'refrigerated':
				return 'fa-temperature-low';
			case 'cool_dry':
				return 'fa-jar';
			case 'room_temperature':
				return 'fa-box-archive';
			default:
				return 'fa-hourglass-half';
		}
	}

	public static function disclosure_chip( int $post_id, string $base = 'mmm-chip' ): string {

		$label = self::disclosure_label_for( $post_id );

		if ( empty( $label ) ) {
			return '';
		}

		$base = sanitize_html_class( $base );

		return sprintf(
			'<span class="%1$s %1$s--disclosure %1$s--disclosure-%2$s">%3$s</span>',
			esc_attr( $base ),
			esc_attr( $label['type'] ),
			esc_html( $label['text'] )
		);
	}

	/**
	 * The bare disclosure pill for listings: no paragraph wrapper, no sponsor
	 * name. Cards are tight and repetitive, so they carry the label only, while
	 * the sponsor stays on the single page.
	 *
	 * Returns '' for editorial content, so card renderers can call it
	 * unconditionally.
	 *
	 * @param int $post_id Recipe or plan ID. Meals resolve to their recipe.
	 * @return string
	 */
	public static function disclosure_pill( int $post_id ): string {

		$label = self::disclosure_label_for( $post_id );

		if ( empty( $label ) ) {
			return '';
		}

		return '<p class="mmm-disclosure mmm-disclosure--card mmm-disclosure--' . esc_attr( $label['type'] ) . '">'
			. '<span class="mmm-disclosure__label">' . esc_html( $label['text'] ) . '</span>'
			. '</p>';
	}

	/**
	 * Shortcode [mmm_disclosure_label].
	 * Renders the origin pill: "Werbung" for paid placements and cooperations,
	 * "Gastautor" for unpaid guest contributions. Editorial content renders
	 * nothing.
	 *
	 * The two are one mechanism but not one statement — the first is a legal
	 * disclosure, the second is editorial transparency. They are styled apart
	 * so the advertising label stays unmistakable; see disclosure_label_text().
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

		if ( ! $post_id || ! Mixmymeals_Post_Meta::is_disclosed( $post_id ) ) {
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
		$recipe    = $meal->get_recipe();

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
		// As on the plan's cards: the stored servings, and what
		// ?mmm_persons_to_add does to them appended ("3 Portionen +1").
		$servings       = (int) $meal->get_servings();
		$servings_delta = (int) $meal->get_shown_servings() - $servings;

		$kcal = 0;
		if ( $recipe->is_valid() ) {
			$nutrition = $recipe->calculate_nutrition_per_serving();
			$kcal      = (int) round( $nutrition['calories'] ?? 0 );
		}

		$has_steps = $recipe->is_valid() && ! empty( $recipe->get_steps() );

		$plan_id    = (int) wp_get_post_parent_id( $meal_id );
		$plan_url   = $plan_id ? self::plan_url_with_servings( $plan_id ) : '';
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
				<?php
				// Kennzeichnung zuerst: sie reiht sich in die Chip-Zeile ein, steht
				// darin aber vorn, damit sie nicht zwischen Kennzahlen untergeht.
				echo self::disclosure_chip( (int) get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
				echo self::diet_chips( (int) get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
				?>
				<?php if ( $day_label ) : ?>
                    <span class="mmm-chip mmm-chip--stat"><i class="mmm-icon fa-solid fa-calendar-days" aria-hidden="true"></i><?php echo esc_html( self::chip_text( 'meal_day', array( 'day' => $day_label ) ) ); ?></span>
				<?php endif; ?>
				<?php if ( $type_label ) : ?>
                    <span class="mmm-chip mmm-chip--stat"><i class="mmm-icon fa-solid fa-book" aria-hidden="true"></i><?php echo esc_html( self::chip_text( 'meal_type', array( 'type' => $type_label ) ) ); ?></span>
				<?php endif; ?>
				<?php if ( $servings ) : ?>
                    <span class="mmm-chip mmm-chip--stat"><i class="mmm-icon fa-solid fa-users" aria-hidden="true"></i><?php
						echo esc_html(
							self::chip_text(
								'servings',
								array(
									'count' => number_format_i18n( absint( $servings ) ),
									/* translators: the word after a number of servings. */
									'unit'  => _n( 'Serving', 'Servings', (int) $servings, 'mixmymeals' ),
								)
							)
						);
						echo self::servings_delta_html( array( 'min' => $servings_delta, 'max' => $servings_delta ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
						?></span>
				<?php endif; ?>
				<?php if ( $kcal > 0 ) : ?>
                    <span class="mmm-chip mmm-chip--stat"><i class="mmm-icon fa-solid fa-fire" aria-hidden="true"></i><?php echo esc_html( self::chip_text( 'energy', array( 'kcal' => number_format_i18n( $kcal ) ) ) ); ?></span>
				<?php endif; ?>
				<?php
				// Haltbarkeit zuletzt: sie beschreibt, was nach dem Essen mit dem
				// Gericht ist, und gehoert damit hinter die Kennzahlen des Essens.
				echo self::keeping_chip( (int) get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
				?>
            </div>

			<?php
			/*
			 * The planning's own note, in the recipe hero's place and markup —
			 * a meal page is a recipe page with a serving size, and a reader
			 * arriving from the plan expects the same shape. Never the
			 * recipe's excerpt: that is the dish's line and it already shows
			 * on the recipe, so repeating it here would push what somebody
			 * actually said about this planning out of the way.
			 */
			$mmm_meal_text = $meal->get_text();
			?>
			<?php if ( '' !== $mmm_meal_text ) : ?>
                <p class="mmm-plan-hero__lead"><?php echo esc_html( $mmm_meal_text ); ?></p>
			<?php endif; ?>

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
			         . esc_url( self::plan_url_with_servings( (int) $parent_plan_id ) ) . '">'
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
		$servings       = $meal_obj->get_servings();
		$servings_delta = $meal_obj->get_shown_servings() - $servings;

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
			           . ( 0 !== $servings_delta ? self::servings_delta_html( array( 'min' => $servings_delta, 'max' => $servings_delta ) ) : '' )
			           . '</span>';
		}

		/* -------------------------
		 * Recipe related meta
		 * ------------------------- */
		$recipe_id = (int) $meal_obj->get_recipe_id();

		if ( $recipe_id > 0 && class_exists( 'Mixmymeals_Recipe' ) ) {

			$recipe = $meal_obj->get_recipe();

			/* Calories (per serving) */
			$nutrition = $recipe->calculate_nutrition_per_serving();

			if ( ! empty( $nutrition['calories'] ) ) {
				$energy_text = self::chip_text(
					'archive_energy',
					array( 'kcal' => number_format_i18n( round( $nutrition['calories'] ) ) )
				);

				$items[]
					= '<span class="mmm-meta__item mmm-meta__item--calories">'
					  . esc_html( $energy_text ) . '</span>';
			}

			/* Preparation time */
			$prep_minutes = $recipe->get_preparation_time();

			if ( $prep_minutes > 0 ) {

				$prep_string = self::format_prep_time( $prep_minutes );

				$items[]
					= '<span class="mmm-meta__item mmm-meta__item--prep-time">'
					  . esc_html( self::chip_text( 'prep_time', array( 'time' => $prep_string ) ) )
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
			// As above: the diet comes from the recipe, not from what a
			// category happens to be called.
			$categories = $recipe->get_categories();
			if ( $categories ) {
				foreach ( $categories as $cat ) {
					$items[] = '<span class="mmm-meta__item mmm-meta__item--category">'
					           . esc_html( self::chip_text( 'category', array( 'category' => $cat->name ) ) )
					           . '</span>';
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
			$mmm_diet = self::diet_chips( (int) $meal_obj->get_id(), 'mmm-meta__item' );
			if ( '' !== $mmm_diet ) {
				$items[] = $mmm_diet;
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
		$mmm_diet = self::diet_chips( (int) $plan->get_id(), 'mmm-meta__item' );
		if ( '' !== $mmm_diet ) {
			$items[] = $mmm_diet;
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

		$plan = new Mixmymeals_Meal_Plan( $meal_plan_id );

		if ( ! $plan->get_meal_rows() ) {
			return '<p>' . esc_html__( 'No meals found.', 'mixmymeals' ) . '</p>';
		}

		return $this->render_plan_nutrition_overview( $plan );
	}

	/**
	 * The plan's nutrition table, for a saved plan and for the planner's live
	 * view alike (Mixmymeals_Meal_Plan::from_meal_rows()). One renderer, so
	 * what the planner shows is what the saved plan will.
	 *
	 * @param Mixmymeals_Meal_Plan $plan Plan, saved or not.
	 * @return string
	 */
	private function render_plan_nutrition_table( Mixmymeals_Meal_Plan $plan ): string {

		$keys = Mixmymeals_Meal_Plan::NUTRITION_KEYS;
		$rows = $plan->get_meal_rows();

		/*
		 * Built on calc_nutrition() rather than on a servings-weighted average
		 * of its own. This block used to divide the plan's totals by the number
		 * of servings and scale by meals-per-day, which gave a different answer
		 * from the subscription mail and the PDF as soon as the days were
		 * unevenly filled — the same plan quoting two sets of figures depending
		 * on where you read it. One method, one number, everywhere.
		 */
		$person_total = $plan->calc_nutrition()['per_person'];

		// Averaged over the days that carry meals, not over the plan's length:
		// two empty days would otherwise make the plan look lighter than anyone
		// eats. Shared with the mail, the PDF and the product comparison, so
		// the four cannot drift apart.
		$person_day = $plan->calc_nutrition_per_day_person()['average'];

		$num_meals   = max( 1, count( $rows ) );
		$person_meal = [];

		foreach ( $keys as $key ) {
			$person_meal[ $key ] = $person_total[ $key ] / $num_meals;
		}

		// Per 100 g of what is eaten: the only column a traffic light can be
		// read against, since a day's total depends on how much is eaten.
		$per_100g = $plan->calc_nutrition_per_100g();
		$lights   = $this->rate_lights( $this->plan_traffic_light_figures( $plan ) );

		// Every dish on the plan whose weight a cooking factor moved: the
		// per-100 g column is a mix of their cooked weights.
		$mmm_plan_cooked = array();
		$mmm_plan_open   = array();

		foreach ( $rows as $mmm_row ) {
			$mmm_recipe = Mixmymeals_Meal_Plan::row_recipe( $mmm_row );

			if ( $mmm_recipe->is_valid() ) {
				$mmm_plan_cooked = array_merge( $mmm_plan_cooked, $mmm_recipe->get_cooked_ingredients() );
				$mmm_plan_open   = array_merge( $mmm_plan_open, $mmm_recipe->get_open_products() );
			}
		}

		/*
		 * Which columns show, and under what heading, is the site's choice
		 * (Settings → Meal Plans). "Total / person" is not on offer any more:
		 * summed over the plan's whole length it is a number nobody eats.
		 */
		return $this->render_nutrition_table(
			'plan',
			array(
				'day'      => $this->format_nutrition_column( $person_day ),
				'ri'       => $this->format_nutrition_ri_column( $person_day ),
				'meal'     => $this->format_nutrition_column( $person_meal ),
				'per_100g' => $this->format_nutrition_column( $per_100g ),
			),
			array(
				'lights'  => $lights,
				'ratings' => $plan->calc_day_rating()['ratings'],
				'cooked'  => $mmm_plan_cooked,
				'open'    => $mmm_plan_open,
			)
		);
	}

	/**
	 * The plan page's nutrition overview: the planner's tiles above the table.
	 *
	 * @param Mixmymeals_Meal_Plan $plan Plan.
	 * @return string
	 */
	private function render_plan_nutrition_overview( Mixmymeals_Meal_Plan $plan ): string {

		$per_day = $plan->calc_nutrition_per_day_person();

		ob_start();
		?>
        <div class="mmm-meal-plan-nutrition">

			<?php
			// The planner's tiles, so the saved plan looks the way it did
			// while it was planned. Its rating note is the table's footnote.
			if ( $per_day['days'] >= 1 ) {
				echo $this->render_nutrition_tiles( $per_day['average'], (array) $plan->calc_day_rating()['ratings'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built escaped.
			}
			?>

			<?php echo $this->render_plan_nutrition_table( $plan ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built escaped. ?>
        </div>
		<?php

		return ob_get_clean();
	}

	/**
	 * The eight figures per day and person as tiles: value, share of the
	 * reference intake, a bar and the rating. Shared by the planner and the
	 * saved plan's page, so the two show one picture of one plan.
	 *
	 * The bar follows Mixmymeals_Meal_Plan::nutrition_bar(): for a maximum
	 * it draws the room left, not the share used, and the note under the
	 * tiles says so. It is aria-hidden — the text beside it says the same.
	 *
	 * @param array $average Per day and person, from calc_nutrition_per_day_person().
	 * @param array $ratings Verdict per key, from calc_day_rating().
	 * @return string
	 */
	private function render_nutrition_tiles( array $average, array $ratings ): string {

		$percentages = Mixmymeals_Meal_Plan::nutrition_percentages( $average );
		$has_room    = false;

		ob_start();
		?>
		<ul class="mmm-planner-nutrition__summary">
			<?php foreach ( Mixmymeals_Meal_Plan::nutrition_labels() as $key => $label ) : ?>
				<?php
				$mmm_verdict = (string) ( $ratings[ $key ] ?? '' );
				$mmm_bar     = Mixmymeals_Meal_Plan::nutrition_bar( $key, (float) $percentages[ $key ] );
				$mmm_tone    = $mmm_bar['over'] ? 'over' : $mmm_verdict;
				$mmm_share   = Mixmymeals_Meal_Plan::format_nutrition_percentage( $key, (float) $percentages[ $key ] );

				if ( 'limit' === Mixmymeals_Meal_Plan::nutrition_direction( $key ) ) {
					$has_room = true;
				}

				// What the bar draws for a maximum, in the figure's own unit.
				$mmm_room = '';
				if ( null !== $mmm_bar['room'] ) {
					$mmm_left = max( 0.0, (float) Mixmymeals_Meal_Plan::reference_intake()[ $key ] - (float) $average[ $key ] );
					/* translators: %s: amount still unused below a daily maximum, e.g. "53,6 g". */
					$mmm_room = sprintf( __( '%s left', 'mixmymeals' ), Mixmymeals_Meal_Plan::format_nutrition_value( $key, $mmm_left ) );
				}
				?>
				<li class="mmm-planner-nutrition__item<?php echo '' === $mmm_verdict ? '' : ' mmm-planner-nutrition__item--' . esc_attr( $mmm_verdict ); ?>" data-key="<?php echo esc_attr( $key ); ?>">
					<span class="mmm-planner-nutrition__label"><?php
						/* translators: %s: nutrition figure, e.g. "Energy". Ø marks an average. */
						echo esc_html( sprintf( _x( 'Ø %s', 'average', 'mixmymeals' ), Mixmymeals_Ingredient::nutrition_label( $key ) ) );
					?></span>
					<span class="mmm-planner-nutrition__value"><?php echo esc_html( Mixmymeals_Meal_Plan::format_nutrition_value( $key, (float) $average[ $key ] ) ); ?></span>
					<span class="mmm-nutri-bar mmm-nutri-bar--<?php echo esc_attr( Mixmymeals_Meal_Plan::nutrition_direction( $key ) ); ?>" aria-hidden="true"><span class="mmm-nutri-bar__fill<?php echo '' === $mmm_tone ? '' : ' mmm-nutri-bar__fill--' . esc_attr( $mmm_tone ); ?>" style="width:<?php echo esc_attr( (string) $mmm_bar['fill'] ); ?>%"></span></span>
					<span class="mmm-planner-nutrition__share"><?php echo esc_html( $mmm_share ); ?></span>
					<?php if ( '' !== $mmm_room ) : ?>
						<span class="mmm-planner-nutrition__room"><?php echo esc_html( $mmm_room ); ?></span>
					<?php endif; ?>
					<?php echo $this->day_rating_markup( $mmm_verdict ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built escaped. ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php if ( $has_room ) : ?>
			<p class="mmm-nutri-note mmm-nutri-note--bars"><?php echo esc_html( Mixmymeals_Meal_Plan::nutrition_bar_note() ); ?></p>
		<?php endif; ?>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Shortcode [mmm_planner_nutrition].
	 *
	 * The place the planner's live nutrition goes: an empty container the
	 * theme puts where it wants it, filled by frontend.js from the
	 * `mmm_planner_nutrition` AJAX answer whenever the planner changes. The
	 * builder itself stays as it was, and a page without this shortcode
	 * simply does not ask.
	 *
	 * @return string
	 */
	public function mmm_planner_nutrition_callback(): string {

		$items = ( new Mixmymeals_Cart() )->get_items();

		// The first answer is rendered here, so the figures stand on first
		// paint rather than after a round trip; frontend.js refreshes them.
		return '<section class="mmm-planner-nutrition" data-planner-nutrition aria-label="'
		       . esc_attr__( 'Nutrition of this plan', 'mixmymeals' ) . '"'
		       . ( $items ? '' : ' hidden' ) . '>'
		       . $this->render_planner_nutrition( Mixmymeals_Meal_Plan::from_meal_rows( $items ) )
		       . '</section>';
	}

	/**
	 * AJAX: the planner's nutrition as it stands, for frontend.js.
	 *
	 * Computed from the cart — recipe, servings, day and type per item — by
	 * the same methods the saved plan uses, through
	 * Mixmymeals_Meal_Plan::from_meal_rows(). `table` is exactly what the
	 * plan page will print once the planner is saved; `html` is the compact
	 * view around it, `days` the row under each day of the builder.
	 *
	 * @return void
	 */
	public function mmm_planner_nutrition_ajax_callback(): void {

		check_ajax_referer( 'mmm_frontend_nonce', 'nonce' );

		$items = ( new Mixmymeals_Cart() )->get_items();
		$plan  = Mixmymeals_Meal_Plan::from_meal_rows( $items );

		$days    = array();
		$ratings = $plan->calc_day_ratings();

		foreach ( range( 1, 7 ) as $day ) {
			$days[ $day ] = $this->render_planner_day_nutrition( $ratings, $day );
		}

		wp_send_json_success(
			array(
				'empty' => ! $plan->get_meal_rows(),
				'html'  => $this->render_planner_nutrition( $plan ),
				'table' => $plan->get_meal_rows() ? $this->render_plan_nutrition_table( $plan ) : '',
				'days'  => $days,
				'prep'  => $this->planner_prep_notes( $items ),
			)
		);
	}

	/**
	 * The planner's preparation notes, one block per weekday (1-7), '' where
	 * nothing is due. The planner is a week of weekdays, so work due before
	 * the meal's day simply lands on the weekday it falls on — Saturday for
	 * a Monday meal two days ahead — computed from the cart by the method
	 * the saved plan uses.
	 *
	 * @param array $items Cart items.
	 * @return array<int, string>
	 */
	private function planner_prep_notes( array $items ): array {

		$grouped = Mixmymeals_Meal_Plan::from_meal_rows( $items )->calc_prep_by_day();
		$out     = array();

		foreach ( range( 1, 7 ) as $day ) {
			$out[ $day ] = $this->render_prep_notes( $grouped[ $day ] ?? array() );
		}

		return $out;
	}

	/**
	 * The planner's compact nutrition view: the eight figures per day and
	 * person, each with its share of the reference intake and its rating,
	 * and the full plan table behind a disclosure.
	 *
	 * @param Mixmymeals_Meal_Plan $plan Plan built from the cart.
	 * @return string '' when the planner is empty.
	 */
	private function render_planner_nutrition( Mixmymeals_Meal_Plan $plan ): string {

		if ( ! $plan->get_meal_rows() ) {
			return '';
		}

		$per_day = $plan->calc_nutrition_per_day_person();
		$rating  = $plan->calc_day_rating();

		ob_start();
		?>
		<h3 class="mmm-planner-nutrition__title"><?php esc_html_e( 'Average nutrition per day and person', 'mixmymeals' ); ?></h3>
		<?php if ( $per_day['days'] < 1 ) : ?>
			<p class="mmm-planner-nutrition__hint"><?php esc_html_e( 'Give a recipe a day to see what a day of this plan provides.', 'mixmymeals' ); ?></p>
		<?php else : ?>
			<?php echo $this->render_nutrition_tiles( $per_day['average'], (array) $rating['ratings'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built escaped. ?>
			<?php
			$mmm_rating_note = Mixmymeals_Meal_Plan::day_rating_note();
			if ( '' !== $mmm_rating_note ) :
				?>
				<p class="mmm-nutri-note mmm-nutri-note--rating"><?php echo esc_html( $mmm_rating_note ); ?></p>
			<?php endif; ?>
		<?php endif; ?>
		<details class="mmm-planner-nutrition__details">
			<summary><?php esc_html_e( 'Show nutrition table', 'mixmymeals' ); ?></summary>
			<?php
			// The table alone: the plan page prints these same tiles above it,
			// and here they already stand above the disclosure.
			echo $this->render_plan_nutrition_table( $plan ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built escaped.
			?>
		</details>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * One day's figures per person, small and in one row, for the builder's
	 * day column — with that day's own rating
	 * (Mixmymeals_Meal_Plan::calc_day_ratings()), which is where a single
	 * salty day shows that the plan's average hides.
	 *
	 * @param array $ratings From Mixmymeals_Meal_Plan::calc_day_ratings(),
	 *                       computed once for all seven days.
	 * @param int   $day     Weekday, 1-7.
	 * @return string '' for a day without meals.
	 */
	private function render_planner_day_nutrition( array $ratings, int $day ): string {

		$info = $ratings[ $day ] ?? null;

		if ( ! $info ) {
			return '';
		}

		$items = '';

		foreach ( Mixmymeals_Meal_Plan::nutrition_labels() as $key => $label ) {

			$verdict = (string) ( $info['ratings'][ $key ] ?? '' );

			$items .= sprintf(
				'<li class="mmm-plan-day-nutrition__item%1$s" data-key="%2$s"><span class="mmm-plan-day-nutrition__label">%3$s</span> <span class="mmm-plan-day-nutrition__value">%4$s</span>%5$s</li>',
				'' === $verdict ? '' : ' mmm-plan-day-nutrition__item--' . esc_attr( $verdict ),
				esc_attr( $key ),
				esc_html( Mixmymeals_Ingredient::nutrition_label( $key ) ),
				esc_html( Mixmymeals_Meal_Plan::format_nutrition_value( $key, (float) ( $info['figures'][ $key ] ?? 0.0 ) ) ),
				'' === $verdict ? '' : ' ' . $this->day_rating_markup( $verdict )
			);
		}

		// The row's own title, so it is not read as another average: the
		// tiles above are the plan's mean, this is what the one day adds up to.
		$title = '<li class="mmm-plan-day-nutrition__title">'
		         . esc_html__( 'Day total:', 'mixmymeals' ) . '</li>';

		return '<ul class="mmm-plan-day-nutrition__list" aria-label="'
		       . esc_attr__( 'Nutrition of this day per person', 'mixmymeals' ) . '">'
		       . $title . $items . '</ul>';
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

		/*
		 * Labels and formatting from Mixmymeals_Meal_Plan, like the overview,
		 * the PDF and the subscription mail — the figures here were always
		 * right, but this table called them "Calories" and "Carbohydrates"
		 * where the other three said "Energy" and "Carbohydrate".
		 *
		 * Four macros, not all eight: this table already spends a column per
		 * nutrient across every weekday, and the full breakdown belongs to
		 * [mmm_nutrition_overview_for_a_single_meal_plan].
		 */
		$labels  = Mixmymeals_Meal_Plan::nutrition_labels();
		$columns = [ 'calories', 'protein', 'carbs', 'fat' ];

		$sums = array_fill_keys( $columns, 0.0 );

		ob_start();
		?>
        <div class="mmm-meal-plan-nutrition-days">
            <?php /* Shared skeleton, and no card collapse: a weekday row is
                      four figures wide, which fits a phone as a table. */ ?>
            <table class="mmm-nutrition-table mmm-nutri-table mmm-nutri-table--days mmm-nutri-days-table">
                <thead>
                <tr>
                    <th><?php esc_html_e( 'Day', 'mixmymeals' ); ?></th>
					<?php foreach ( $columns as $key ) : ?>
                        <th><?php echo esc_html( $labels[ $key ] ); ?></th>
					<?php endforeach; ?>
                </tr>
                </thead>
                <tbody>
				<?php foreach ( $by_day as $day => $nutrition ) : ?>
                    <tr<?php echo 0 === $nutrition['meals'] ? ' class="mmm-nutri-days-table__empty"' : ''; ?>>
                        <th scope="row"><?php echo esc_html( Mixmymeals_Meal_Plan::weekday_name( (int) $day ) ); ?></th>
						<?php
						foreach ( $columns as $key ) :
							$value         = (float) ( $nutrition[ $bucket ][ $key ] ?? 0.0 );
							$sums[ $key ] += $value;
							?>
                            <td data-label="<?php echo esc_attr( $labels[ $key ] ); ?>">
								<?php echo esc_html( Mixmymeals_Meal_Plan::format_nutrition_value( $key, $value ) ); ?>
                            </td>
						<?php endforeach; ?>
                    </tr>
				<?php endforeach; ?>
                </tbody>
                <tfoot>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Total', 'mixmymeals' ); ?></th>
					<?php foreach ( $columns as $key ) : ?>
                        <td data-label="<?php echo esc_attr( $labels[ $key ] ); ?>">
							<?php echo esc_html( Mixmymeals_Meal_Plan::format_nutrition_value( $key, $sums[ $key ] ) ); ?>
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
						     . esc_html( number_format_i18n( (int) $term->count ) )
						     . ')</span>';
					}

					echo '</span>';

				} else {

					echo '<a class="mmm-term-list__link" href="'
					     . esc_url( $link ) . '">';
					echo esc_html( $term->name );

					if ( $show_count ) {
						echo ' <span class="mmm-term-list__count">('
						     . esc_html( number_format_i18n( (int) $term->count ) )
						     . ')</span>';
					}

					echo '</a>';
				}

				// Kinder rendern, wenn depth=0 (unbegrenzt) oder level < depth
				$can_go_deeper = ( $depth === 0 ) || ( $level < $depth );
				if ( $can_go_deeper ) {
					// The closure escapes every value it prints; it returns
					// finished markup, which cannot be escaped again.
					echo $render_terms( (int) $term->term_id, $level + 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup assembled with escaping inside the closure.
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
				echo '<' . tag_escape( $heading_tag ) . ' class="mmm-term-list__heading">';
				echo esc_html( $parent_term->name );
				echo '</' . tag_escape( $heading_tag ) . '>';
			}
		}

		echo $render_terms( $parent, 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup assembled with escaping inside the closure.

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
	 * Shortcode [mmm_disclaimer_for_a_single_ingredient].
	 *
	 * The same text as on a recipe, and only where the site has asked for it
	 * (Settings → Recipes → "Also show on ingredient pages"). One wording
	 * rather than a second setting: what such a text says about figures — that
	 * they are estimates, that the traffic light is not an official labelling,
	 * that the packet in the basket is what counts — holds for an ingredient
	 * page as much as for a recipe, and two fields would drift apart the day
	 * either is edited.
	 *
	 * Unlike its two siblings this one returns '' when switched off or empty,
	 * so the theme can hide the whole section instead of printing a heading
	 * over nothing.
	 *
	 * @return string
	 */
	public function mmm_disclaimer_for_a_single_ingredient_callback(): string {

		if ( 'on' !== get_option( 'mixmymeals_recipe_disclaimer_on_ingredients', '' ) ) {
			return '';
		}

		$text = trim( (string) get_option( 'mixmymeals_recipe_disclaimer_text', '' ) );

		return '' === $text
			? ''
			: '<p class="mmm-recipe-disclaimer">' . esc_html( $text ) . '</p>';
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
		$is_post   = isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) );
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
		$is_post   = isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) );
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

		// Recipes carry the extra fields; the plan archive shares this form and
		// has neither a time nor an ingredient index behind it.
		$recipes = 'mmm_recipe' === $post_type;
		$filters = $recipes ? self::archive_filters() : array();
		$has_any = $recipes && ( '' !== $cur_s || ! empty( $filters['diets'] ) || ! empty( $filters['time'] )
			|| ! empty( $filters['ingredients'] ) || '' !== $filters['view'] );

		ob_start();
		?>
		<form class="mmm-archive-search" method="get" role="search" action="<?php echo esc_url( self::archive_form_action() ); ?>">
			<input type="search" name="mmm_s" class="mmm-archive-search__text" value="<?php echo esc_attr( $cur_s ); ?>" placeholder="<?php esc_attr_e( 'Search by title…', 'mixmymeals' ); ?>">
			<?php if ( $recipes ) : ?>
				<input
					type="search"
					name="zutat"
					class="mmm-archive-search__text mmm-archive-search__text--ingredients"
					value="<?php echo esc_attr( implode( ', ', (array) $filters['ingredients'] ) ); ?>"
					aria-label="<?php esc_attr_e( 'Recipes with these ingredients', 'mixmymeals' ); ?>"
					placeholder="<?php esc_attr_e( 'I have these in: courgette, feta…', 'mixmymeals' ); ?>">
				<?php
				/*
				 * The filters the path carries but no field shows. Without
				 * these a search from /rezepte/vegan/ silently drops the diet,
				 * which is the sort of thing a visitor reads as "the filter
				 * does not work" rather than "the form forgot".
				 */
				foreach ( self::archive_form_hidden_fields( $filters ) as $name => $value ) :
					?>
					<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>">
					<?php
				endforeach;
				?>
			<?php endif; ?>
			<button type="submit" class="mmm-archive-search__submit"><?php esc_html_e( 'Search', 'mixmymeals' ); ?></button>
			<?php if ( ( $has_any || '' !== $cur_s ) && $reset && ! is_wp_error( $reset ) ) : ?>
				<a class="mmm-archive-search__reset" href="<?php echo esc_url( $reset ); ?>"><?php esc_html_e( 'Reset', 'mixmymeals' ); ?></a>
			<?php endif; ?>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Where the archive form posts to: the current path, without its page.
	 *
	 * An empty action would inherit /page/3/ from the URL, and a new search
	 * would start on page three of a result set that no longer exists.
	 *
	 * @return string
	 */
	private static function archive_form_action(): string {

		$post_type = (string) get_query_var( 'post_type' );
		$post_type = '' !== $post_type ? $post_type : 'mmm_recipe';

		$base = is_post_type_archive()
			? get_post_type_archive_link( $post_type )
			: get_term_link( get_queried_object() );

		if ( ! $base || is_wp_error( $base ) ) {
			return '';
		}

		$filters = self::archive_filters();
		$primary = self::archive_primary_facet( $filters );

		if ( '' === $primary ) {
			return (string) $base;
		}

		// The path keeps the facet that owns it, so submitting the form does
		// not quietly leave the view the visitor is looking at.
		$url = Mixmymeals_Recipe::archive_view_url( $primary, $filters[ $primary ] );

		return '' !== $url ? $url : (string) $base;
	}

	/**
	 * Which facet owns the address of the current view.
	 *
	 * One order, asked in one place: the form's action, the hidden fields and
	 * the heading all have to agree about which filter the path stands for, and
	 * three separate answers would disagree on the day somebody adds a facet.
	 *
	 * A diet owns the path only while it is the only one: a path holds one
	 * slug, and several diets travel together as a parameter instead.
	 *
	 * @param array<string,mixed> $filters As archive_filters() returns them.
	 * @return string 'diet', 'time', 'view' or ''.
	 */
	public static function archive_primary_facet( array $filters ): string {

		if ( 1 === count( self::archive_diets( $filters ) ) ) {
			return 'diet';
		}

		foreach ( array( 'time', 'view' ) as $kind ) {
			if ( ! empty( $filters[ $kind ] ) ) {
				return $kind;
			}
		}

		return '';
	}

	/**
	 * The active filters that no visible field carries.
	 *
	 * @param array<string,mixed> $filters As archive_filters() returns them.
	 * @return array<string,string>
	 */
	private static function archive_form_hidden_fields( array $filters ): array {

		$hidden  = array();
		$primary = self::archive_primary_facet( $filters );

		/*
		 * Everything except the facet the action's path already carries —
		 * repeating that one would produce /rezepte/vegan/?diaet=vegan on every
		 * submit. The time needs no hidden field either way: the select posts
		 * it.
		 */
		$diets = self::archive_diets( $filters );

		if ( $diets && 'diet' !== $primary ) {
			$hidden['diaet'] = self::archive_diet_param( $diets );
		}

		if ( ! empty( $filters['time'] ) && 'time' !== $primary ) {
			$hidden['zeit'] = (string) $filters['time'];
		}

		if ( ! empty( $filters['view'] ) && 'view' !== $primary ) {
			$hidden[ Mixmymeals_Recipe::BROWSE_VIEW_VAR ] = (string) $filters['view'];
		}

		return $hidden;
	}

	/**
	 * Narrows the recipe archive to one browse view when ?mmm_view= asks for it.
	 *
	 * The view's conditions come from Mixmymeals_Recipe::browse_view_args() —
	 * the same method the browse rows query with, so "Frequently cooked" means
	 * one thing on the site rather than two.
	 *
	 * meta_query is merged, never replaced: the unlisted clause is added to the
	 * main query by a filter of its own, and whichever of the two runs second
	 * would otherwise throw the first one's conditions away. An unlisted recipe
	 * reappearing in a listing is exactly the kind of quiet failure this order
	 * dependency produces.
	 *
	 * @param WP_Query $query Query about to run.
	 * @return void
	 */
	public function filter_recipe_archive_by_view( $query ): void {

		if ( is_admin() || ! $query instanceof WP_Query || ! $query->is_main_query() ) {
			return;
		}

		if ( ! $query->is_post_type_archive( 'mmm_recipe' ) ) {
			return;
		}

		$view = Mixmymeals_Recipe::current_browse_view();

		if ( '' === $view ) {
			return;
		}

		foreach ( Mixmymeals_Recipe::browse_view_args( $view ) as $key => $value ) {

			if ( 'meta_query' === $key ) {

				$existing = $query->get( 'meta_query' );
				$existing = is_array( $existing ) ? $existing : array();

				$query->set( 'meta_query', array_merge( $existing, (array) $value ) );
				continue;
			}

			$query->set( $key, $value );
		}
	}



	/**
	 * Shortcode [mmm_archive_filters]: the diet and time rows above the recipe
	 * archive.
	 *
	 * Links, not a form: one state is one address, and a link is what a visitor
	 * can share, bookmark and open in a new tab.
	 *
	 * **Diets combine, times do not.** A diet link toggles: it adds that diet
	 * to the ones in force, or drops it when it is already on, and the chosen
	 * diets all have to hold ("gluten-free and lactose-free" is one person's
	 * question, not two). A diet on the same axis as one already chosen
	 * replaces it instead — vegan and vegetarian together is just vegan, and a
	 * row showing both lit would claim a choice nobody made. The time steps
	 * stay single: they are one scale, and "under 20 and under 60" is under 20.
	 *
	 * The counts are per diet over the whole archive, not narrowed by what is
	 * already chosen — the same rule as between the two rows.
	 *
	 * A diet nothing is classified under is left out rather than shown empty:
	 * the counts come from one grouped query over the index, so this costs one
	 * query no matter how many diets exist.
	 *
	 * @param array|string $atts Unused.
	 * @return string
	 */
	public function mmm_archive_filters_callback( $atts = array() ): string {

		unset( $atts );

		if ( ! is_post_type_archive( 'mmm_recipe' ) && ! is_tax( array( 'mmm_recipe_category', 'mmm_recipe_tag' ) ) ) {
			return '';
		}

		$filters = self::archive_filters();
		$diets   = self::archive_diet_counts();
		$times   = self::archive_time_counts();

		$rows = '';

		if ( $diets ) {

			$links = array();

			$chosen = self::archive_diets( $filters );

			foreach ( $diets as $slug => $count ) {

				$active = in_array( $slug, $chosen, true );

				$links[] = array(
					'label'  => Mixmymeals_Diet::label( $slug ),
					'count'  => $count,
					'active' => $active,
					'url'    => self::archive_url_for(
						$active
							? self::archive_filters_without( $filters, 'diet', $slug )
							: self::archive_filters_with_diets( $filters, array_merge( $chosen, array( $slug ) ) )
					),
				);
			}

			$rows .= self::render_filter_row(
				__( 'Diet', 'mixmymeals' ),
				__( 'Any', 'mixmymeals' ),
				self::archive_url_for( self::archive_filters_without( $filters, 'diet' ) ),
				empty( $chosen ),
				$links,
				true
			);
		}

		if ( $times ) {

			$links = array();

			foreach ( $times as $minutes => $count ) {
				$links[] = array(
					'label'  => sprintf(
						/* translators: %d: number of minutes. */
						__( 'Under %d min', 'mixmymeals' ),
						(int) $minutes
					),
					'count'  => $count,
					'active' => (int) $minutes === (int) $filters['time'],
					'url'    => self::archive_url_for( array_merge( $filters, array( 'time' => (int) $minutes ) ) ),
				);
			}

			$rows .= self::render_filter_row(
				__( 'Time', 'mixmymeals' ),
				__( 'Any', 'mixmymeals' ),
				self::archive_url_for( self::archive_filters_without( $filters, 'time' ) ),
				0 === (int) $filters['time'],
				$links
			);
		}

		return $rows;
	}

	/**
	 * One row of facet links: a label, an "any" link and the choices.
	 *
	 * Both rows go through here, because two rows that mean the same kind of
	 * thing have to look and behave the same — and because the second one was
	 * a select in the form until somebody noticed that choosing a diet and
	 * choosing a duration are the same act.
	 *
	 * The label is not decoration: with two rows, two links reading "Any" need
	 * to say what they are any of.
	 *
	 * @param string                                                           $label      What this row filters by.
	 * @param string                                                           $any_label  Wording of the unrestricted choice.
	 * @param string                                                           $any_url    Where it leads.
	 * @param bool                                                             $any_active Whether nothing is chosen here.
	 * @param array<int, array{label:string,count:int,active:bool,url:string}> $links      The choices.
	 * @param bool                                                             $multi      Whether several choices combine
	 *                                                                                     (the theme marks those as ticks).
	 * @return string
	 */
	private static function render_filter_row( string $label, string $any_label, string $any_url, bool $any_active, array $links, bool $multi = false ): string {

		ob_start();
		?>
		<div class="mmm-archive-filters<?php echo $multi ? ' mmm-archive-filters--multi' : ''; ?>">
			<span class="mmm-archive-filters__label"><?php echo esc_html( $label ); ?></span>
			<?php if ( '' !== $any_url ) : ?>
				<a class="<?php echo esc_attr( $any_active ? 'active' : '' ); ?>" href="<?php echo esc_url( $any_url ); ?>">
					<?php echo esc_html( $any_label ); ?>
				</a>
			<?php endif; ?>
			<?php foreach ( $links as $link ) : ?>
				<?php if ( '' !== $link['url'] ) : ?>
					<a class="<?php echo esc_attr( $link['active'] ? 'active' : '' ); ?>" href="<?php echo esc_url( $link['url'] ); ?>"<?php echo $link['active'] ? ' aria-current="true"' : ''; ?>>
						<?php echo esc_html( $link['label'] ); ?>
						<span class="mmm-archive-filters__count"><?php echo esc_html( number_format_i18n( $link['count'] ) ); ?></span>
					</a>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * How many published recipes fall under each time step.
	 *
	 * One query for the whole scale rather than one per step, and counted with
	 * conditional sums so the steps stay comparable: every step counts the
	 * recipes at or below it, which is what "under 30 minutes" means and what
	 * the filter does.
	 *
	 * A recipe nobody has timed carries no row and is therefore in no step —
	 * the same rule the filter keeps, so the number beside a step is the number
	 * the step produces.
	 *
	 * @return array<int,int> Minutes => count, steps with no recipes left out.
	 */
	public static function archive_time_counts(): array {
		/*
		 * No static memo beside the transient: get_transient() is served from
		 * the object cache within a request anyway, and a second layer that
		 * nothing can clear meant the row kept its old numbers after the index
		 * had already been rebuilt in the same request.
		 */
		$cached = get_transient( 'mmm_archive_time_counts' );

		if ( is_array( $cached ) ) {
			return $cached;
		}
		$steps = Mixmymeals_Recipe::prep_time_steps();

		if ( empty( $steps ) ) {
			return array();
		}

		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- cached in the transient below.
			$wpdb->prepare(
				"SELECT CAST( pm.meta_value AS UNSIGNED ) AS minutes, COUNT( DISTINCT p.ID ) AS total
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = %s
				   AND p.post_type = %s
				   AND p.post_status = 'publish'
				 GROUP BY minutes",
				Mixmymeals_Recipe::META_PREP_TIME,
				'mmm_recipe'
			)
		);

		$counts = array();

		foreach ( $steps as $step ) {

			$total = 0;

			foreach ( (array) $rows as $row ) {
				if ( (int) $row->minutes > 0 && (int) $row->minutes <= $step ) {
					$total += (int) $row->total;
				}
			}

			if ( $total > 0 ) {
				$counts[ $step ] = $total;
			}
		}

		set_transient( 'mmm_archive_time_counts', $counts, 15 * MINUTE_IN_SECONDS );

		return $counts;
	}

	/**
	 * How many published recipes each diet can be cooked for.
	 *
	 * One grouped query over the index rather than one per diet — the pill row
	 * is rendered on every archive page, and four counting queries there is how
	 * an archive gets slow without anybody noticing which feature did it.
	 *
	 * Held for the request and behind a transient that
	 * Mixmymeals_Recipe::flush_recipe_indexes() clears — a cached count of an
	 * index, with nothing to invalidate it, is exactly the mistake the
	 * ingredient page's own transient made for years.
	 *
	 * @return array<string,int> Diet slug => count, known diets only, in the
	 *                           vocabulary's own order.
	 */
	public static function archive_diet_counts(): array {
		/*
		 * No static memo beside the transient: get_transient() is served from
		 * the object cache within a request anyway, and a second layer that
		 * nothing can clear meant the row kept its old numbers after the index
		 * had already been rebuilt in the same request.
		 */
		$cached = get_transient( 'mmm_archive_diet_counts' );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- cached in the transient below.
			$wpdb->prepare(
				"SELECT pm.meta_value AS diet, COUNT(DISTINCT p.ID) AS total
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = %s
				   AND p.post_type = %s
				   AND p.post_status = 'publish'
				 GROUP BY pm.meta_value",
				Mixmymeals_Recipe::META_DIET_INDEX,
				'mmm_recipe'
			)
		);

		$found = array();

		foreach ( (array) $rows as $row ) {
			$found[ (string) $row->diet ] = (int) $row->total;
		}

		// Ordered by the vocabulary, not by the database: the row reads the
		// same on every page, and vegan stands before vegetarian because that
		// is how the two relate.
		$counts = array();

		foreach ( array_keys( Mixmymeals_Diet::types() ) as $slug ) {
			if ( ! empty( $found[ $slug ] ) ) {
				$counts[ $slug ] = $found[ $slug ];
			}
		}

		set_transient( 'mmm_archive_diet_counts', $counts, 15 * MINUTE_IN_SECONDS );

		return $counts;
	}

	/**
	 * Whether the current archive view should stay out of the index.
	 *
	 * A single facet is a page worth finding: "vegan recipes" and "recipes
	 * under 30 minutes" are things people search for, and each has one address.
	 * A combination is not — it is one reader's question, it has no canonical
	 * address of its own, and every further filter multiplies the number of
	 * near-identical pages a crawler could walk into. Same for a search: the
	 * results belong to whoever typed it.
	 *
	 * Only ever tightens, like every other caller of this filter.
	 *
	 * @param bool $noindex Decision so far.
	 * @return bool
	 */
	public static function filter_archive_noindex( $noindex ): bool {

		if ( $noindex || is_admin() ) {
			return (bool) $noindex;
		}

		if ( ! is_post_type_archive( 'mmm_recipe' ) && ! is_tax( array( 'mmm_recipe_category', 'mmm_recipe_tag' ) ) ) {
			return (bool) $noindex;
		}

		$filters = self::archive_filters();

		if ( ! empty( $filters['ingredients'] ) || '' !== $filters['title'] ) {
			return true;
		}

		// Every diet counts on its own: "vegan and gluten-free" is a
		// combination like any other.
		$facets = count( self::archive_diets( $filters ) );

		foreach ( array( 'view', 'time' ) as $kind ) {
			if ( ! empty( $filters[ $kind ] ) ) {
				++$facets;
			}
		}

		return $facets > 1;
	}

	/**
	 * What the current request asks the recipe archive to show.
	 *
	 * One place reads the request, so the query, the heading, the pills, the
	 * form and the empty state cannot disagree about what is filtered.
	 *
	 * Three sources: the query variables a rewrite rule filled, the public
	 * parameters, and mmm_s. **A parameter beats a path** for the same facet —
	 * a form submitted from /rezepte/vegan/ has to be able to say "vegetarian"
	 * without the path contradicting it.
	 *
	 * The parameters are read straight from $_GET and validated here rather
	 * than registered as query variables: short German names like `zeit` are
	 * only safe to use because nothing else in WordPress ever sees them.
	 *
	 * Several diets travel as one parameter, `?diaet=vegan,glutenfrei`; when it
	 * is there it replaces the path's diet rather than adding to it, which is
	 * what keeps "a parameter beats a path" true with more than one value.
	 * `diets` is the answer; `diet` is that list when it holds exactly one —
	 * the only case a path can carry, and what archive_primary_facet() and the
	 * theme's heading read.
	 *
	 * @return array{view:string, diets:string[], diet:string, time:int, ingredients:string[], title:string}
	 */
	public static function archive_filters(): array {

		static $filters = null;

		if ( null !== $filters ) {
			return $filters;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only public GET filters, like mmm_s.
		$diet = isset( $_GET['diaet'] ) ? sanitize_text_field( wp_unslash( $_GET['diaet'] ) ) : '';
		$time = isset( $_GET['zeit'] ) ? absint( wp_unslash( $_GET['zeit'] ) ) : 0;
		$raw  = isset( $_GET['zutat'] ) ? sanitize_text_field( wp_unslash( $_GET['zutat'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( '' === $diet ) {
			$diet = sanitize_title( (string) get_query_var( Mixmymeals_Recipe::DIET_VIEW_VAR, '' ) );
		}

		$diets = array_map( 'sanitize_title', explode( ',', (string) $diet ) );

		if ( 0 === $time ) {
			$time = absint( get_query_var( Mixmymeals_Recipe::TIME_VIEW_VAR, 0 ) );
		}

		// A diet decides what a public page claims to be cookable, and a time
		// step decides which path exists: neither is passed through unchecked.
		$known = array();

		foreach ( $diets as $candidate ) {
			$resolved = Mixmymeals_Recipe::resolve_archive_view( $candidate );

			if ( $resolved && 'diet' === $resolved['kind'] ) {
				$known[] = (string) $resolved['value'];
			}
		}

		if ( ! in_array( $time, Mixmymeals_Recipe::prep_time_steps(), true ) ) {
			$time = 0;
		}

		$filters = self::archive_filters_with_diets(
			array(
				'view'        => Mixmymeals_Recipe::current_browse_view(),
				'time'        => $time,
				'ingredients' => self::parse_ingredient_tokens( $raw ),
				'title'       => self::archive_search_term(),
			),
			$known
		);

		return $filters;
	}

	/**
	 * The diets of a filter set.
	 *
	 * Reads `diets`, and falls back to `diet` for a set built by hand with only
	 * that key — the shape every filter set had before diets could combine.
	 *
	 * @param array<string,mixed> $filters As archive_filters() returns them.
	 * @return string[]
	 */
	public static function archive_diets( array $filters ): array {

		if ( isset( $filters['diets'] ) && is_array( $filters['diets'] ) ) {
			return array_values( array_map( 'strval', $filters['diets'] ) );
		}

		return ! empty( $filters['diet'] ) ? array( (string) $filters['diet'] ) : array();
	}

	/**
	 * Several diets as the `diaet` parameter, in the words the paths use.
	 *
	 * The last slug registered for a diet is the translated one — the same
	 * choice archive_view_url() makes for a path — so the address reads
	 * `?diaet=glutenfrei,vegan` rather than the internal keys.
	 *
	 * @param string[] $diets Diet slugs.
	 * @return string
	 */
	private static function archive_diet_param( array $diets ): string {

		$words = array();

		foreach ( Mixmymeals_Recipe::archive_view_slugs() as $slug => $entry ) {
			if ( 'diet' === $entry[0] && in_array( (string) $entry[1], $diets, true ) ) {
				$words[ (string) $entry[1] ] = (string) $slug;
			}
		}

		return implode( ',', array_map( static fn( $diet ) => $words[ $diet ] ?? $diet, $diets ) );
	}

	/**
	 * The same filter set with these diets in force.
	 *
	 * The one place a diet list is normalised, so every address built from it
	 * is the same address: one diet per axis (the later one wins, which is how
	 * a click on "vegetarian" replaces "vegan"), unknown slugs dropped, and the
	 * vocabulary's own order rather than the order of the clicks. `diet` is
	 * kept in step with `diets` here and nowhere else.
	 *
	 * @param array<string,mixed> $filters Filter set.
	 * @param string[]            $diets   Diet slugs, in the order chosen.
	 * @return array<string,mixed>
	 */
	public static function archive_filters_with_diets( array $filters, array $diets ): array {

		$types   = Mixmymeals_Diet::types();
		$by_axis = array();

		foreach ( $diets as $slug ) {

			$slug = (string) $slug;

			if ( ! isset( $types[ $slug ] ) ) {
				continue;
			}

			$axis = (string) ( $types[ $slug ]['axis'] ?? $slug );

			unset( $by_axis[ $axis ] );
			$by_axis[ $axis ] = $slug;
		}

		$ordered = array_values( array_intersect( array_keys( $types ), $by_axis ) );

		$filters['diets'] = $ordered;
		$filters['diet']  = 1 === count( $ordered ) ? $ordered[0] : '';

		return $filters;
	}

	/**
	 * Splits the "what I have in" field into tokens.
	 *
	 * Capped in both directions: six tokens, forty characters each. The cap is
	 * not about abuse but about the query behind it — one lookup per token,
	 * and a ranking that stops meaning anything once half the kitchen is in it.
	 *
	 * @param string $raw Raw field value.
	 * @return string[]
	 */
	private static function parse_ingredient_tokens( string $raw ): array {

		if ( '' === trim( $raw ) ) {
			return array();
		}

		$tokens = preg_split( '/[,;\r\n]+/', $raw );
		$out    = array();

		foreach ( (array) $tokens as $token ) {

			$token = trim( (string) $token );

			if ( '' === $token ) {
				continue;
			}

			$out[ mb_strtolower( $token ) ] = mb_substr( $token, 0, 40 );

			if ( count( $out ) >= 6 ) {
				break;
			}
		}

		return array_values( $out );
	}

	/**
	 * The search term of the current archive request.
	 *
	 * @return string
	 */
	public static function archive_search_term(): string {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only public GET filter.
		return isset( $_GET['mmm_s'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['mmm_s'] ) ) ) : '';
	}

	/**
	 * The query arguments a set of filters produces.
	 *
	 * A pure function of its input, so the whole filter behaviour is testable
	 * without a request: hand it an array, read the clauses.
	 *
	 * @param array<string,mixed> $filters As archive_filters() returns them.
	 * @return array<string,mixed> Query arguments; empty when nothing filters.
	 */
	public static function archive_filter_query_args( array $filters ): array {

		$args = array();
		$meta = array();

		// One clause per diet, and meta_query joins clauses with AND: a recipe
		// has to be cookable for every diet chosen.
		foreach ( self::archive_diets( $filters ) as $diet ) {
			$meta[] = array(
				'key'   => Mixmymeals_Recipe::META_DIET_INDEX,
				'value' => $diet,
			);
		}

		if ( ! empty( $filters['time'] ) ) {
			/*
			 * The join does the work that matters here: a recipe nobody has
			 * timed carries no row at all, so it drops out instead of passing
			 * as the fastest dish on the site. That is the whole reason
			 * set_preparation_time() stopped storing zeroes.
			 */
			$meta[] = array(
				'key'     => Mixmymeals_Recipe::META_PREP_TIME,
				'value'   => (int) $filters['time'],
				'compare' => '<=',
				'type'    => 'NUMERIC',
			);
		}

		if ( $meta ) {
			$args['meta_query'] = $meta; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		if ( ! empty( $filters['ingredients'] ) ) {

			$ranked = self::rank_recipes_by_ingredients( (array) $filters['ingredients'] );

			/*
			 * An empty post__in is not a filter that matches nothing, it is no
			 * filter at all — WordPress drops it and shows everything. A list
			 * holding only 0 is the one that means "nothing matched".
			 */
			$args['post__in'] = $ranked ? array_keys( $ranked ) : array( 0 );
			$args['orderby']  = 'post__in';
		}

		return $args;
	}

	/**
	 * Applies the archive filters to the main query.
	 *
	 * @param WP_Query $query The query.
	 * @return void
	 */
	public static function apply_archive_filters( $query ): void {

		if ( is_admin() || ! $query instanceof WP_Query || ! $query->is_main_query() ) {
			return;
		}

		if (
			! $query->is_post_type_archive( 'mmm_recipe' )
			&& ! $query->is_tax( array( 'mmm_recipe_category', 'mmm_recipe_tag' ) )
		) {
			return;
		}

		$args = self::archive_filter_query_args( self::archive_filters() );

		if ( empty( $args ) ) {
			return;
		}

		if ( ! empty( $args['meta_query'] ) ) {
			/*
			 * Merged, never replaced — the same rule and the same reason as in
			 * filter_recipe_archive_by_view(): the unlisted clause from
			 * Mixmymeals_Post_Types::filter_unlisted_from_main_query() is
			 * already in there, and replacing it would put unlisted recipes
			 * back on a public page.
			 */
			$existing = $query->get( 'meta_query' );
			$existing = is_array( $existing ) ? $existing : array();

			$query->set( 'meta_query', array_merge( $existing, $args['meta_query'] ) );
		}

		if ( isset( $args['post__in'] ) ) {
			$query->set( 'post__in', $args['post__in'] );

			// The ingredient ranking outranks a browse view's own ordering:
			// "most of what I have" is the question that was asked last.
			$query->set( 'orderby', $args['orderby'] );
		}
	}


	/**
	 * The normalised address of a filter set.
	 *
	 * The primary facet becomes the path, everything else a parameter — so the
	 * same state always has the same address, whichever way somebody arrived at
	 * it. This is what the remove links in the filter row are built from, and
	 * why removing the last filter lands on the plain archive rather than on a
	 * path with an empty query string.
	 *
	 * @param array<string,mixed> $filters As archive_filters() returns them.
	 * @return string
	 */
	public static function archive_url_for( array $filters ): string {

		$params = array();

		if ( ! empty( $filters['ingredients'] ) ) {
			$params['zutat'] = implode( ', ', (array) $filters['ingredients'] );
		}

		if ( ! empty( $filters['title'] ) ) {
			$params['mmm_s'] = (string) $filters['title'];
		}

		$primary = self::archive_primary_facet( $filters );

		/*
		 * Whatever the path does not carry travels as a parameter beside it —
		 * including several diets with no other facet, where there is no path
		 * at all and the plain archive carries them.
		 */
		$diets = self::archive_diets( $filters );

		if ( $diets && 'diet' !== $primary ) {
			$params['diaet'] = self::archive_diet_param( $diets );
		}

		foreach ( array( 'time', 'view' ) as $kind ) {

			if ( $kind === $primary || empty( $filters[ $kind ] ) ) {
				continue;
			}

			$params[ 'time' === $kind ? 'zeit' : Mixmymeals_Recipe::BROWSE_VIEW_VAR ] = (string) $filters[ $kind ];
		}

		if ( '' === $primary ) {
			$base = get_post_type_archive_link( 'mmm_recipe' );

			if ( ! $base ) {
				return '';
			}

			// Encoded here for the same reason archive_view_url() encodes.
			return $params
				? add_query_arg( array_map( 'rawurlencode', $params ), $base )
				: $base;
		}

		$value = 'diet' === $primary ? $diets[0] : $filters[ $primary ];

		return Mixmymeals_Recipe::archive_view_url( $primary, $value, $params );
	}

	/**
	 * The same filter set with one filter taken out.
	 *
	 * @param array<string,mixed> $filters As archive_filters() returns them.
	 * @param string              $kind    'diet', 'time', 'view', 'title' or 'ingredient'.
	 * @param string              $value   For 'ingredient' and 'diet': which one to drop
	 *                                     ('' drops every diet).
	 * @return array<string,mixed>
	 */
	public static function archive_filters_without( array $filters, string $kind, string $value = '' ): array {

		switch ( $kind ) {

			case 'ingredient':
				$filters['ingredients'] = array_values(
					array_filter(
						(array) $filters['ingredients'],
						static fn( $token ) => $token !== $value
					)
				);
				break;

			case 'title':
				$filters['title'] = '';
				break;

			case 'time':
				$filters['time'] = 0;
				break;

			case 'diet':
				// A value drops that one diet, none drops them all.
				$filters = self::archive_filters_with_diets(
					$filters,
					'' === $value ? array() : array_diff( self::archive_diets( $filters ), array( $value ) )
				);
				break;

			default:
				$filters[ $kind ] = '';
				break;
		}

		return $filters;
	}

	/**
	 * Every filter in force, each with the address that drops it.
	 *
	 * The plugin answers what is filtered and where each chip links; the words
	 * are the theme's, as they are for the browse views — which is why this
	 * returns a value and a kind rather than a finished label.
	 *
	 * @return array<int, array{kind:string, value:string, url:string}>
	 */
	public static function active_archive_filters(): array {

		$filters = self::archive_filters();
		$out     = array();

		foreach ( self::archive_diets( $filters ) as $diet ) {
			$out[] = array(
				'kind'  => 'diet',
				'value' => $diet,
				'url'   => self::archive_url_for( self::archive_filters_without( $filters, 'diet', $diet ) ),
			);
		}

		foreach ( array( 'time', 'view' ) as $kind ) {

			if ( empty( $filters[ $kind ] ) ) {
				continue;
			}

			$out[] = array(
				'kind'  => $kind,
				'value' => (string) $filters[ $kind ],
				'url'   => self::archive_url_for( self::archive_filters_without( $filters, $kind ) ),
			);
		}

		foreach ( (array) $filters['ingredients'] as $token ) {
			$out[] = array(
				'kind'  => 'ingredient',
				'value' => (string) $token,
				'url'   => self::archive_url_for( self::archive_filters_without( $filters, 'ingredient', (string) $token ) ),
			);
		}

		if ( '' !== $filters['title'] ) {
			$out[] = array(
				'kind'  => 'title',
				'value' => (string) $filters['title'],
				'url'   => self::archive_url_for( self::archive_filters_without( $filters, 'title' ) ),
			);
		}

		return $out;
	}

	/**
	 * Which ingredients a set of tokens names.
	 *
	 * An exact title first, then a title that contains the token — "Zwiebel"
	 * finding "Frühlingszwiebel" is usually what somebody standing in front of
	 * an open fridge means.
	 *
	 * @param string[] $tokens Raw tokens.
	 * @return array{matched: array<string, int[]>, unknown: string[]}
	 */
	public static function resolve_ingredient_tokens( array $tokens ): array {

		$matched = array();
		$unknown = array();

		foreach ( $tokens as $token ) {

			$ids = get_posts(
				array(
					'post_type'      => 'mmm_ingredient',
					'post_status'    => 'publish',
					'posts_per_page' => 20,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					's'              => $token,
					'search_columns' => array( 'post_title' ),
				)
			);

			$ids = array_map( 'intval', (array) $ids );

			if ( $ids ) {
				$matched[ $token ] = $ids;
			} else {
				$unknown[] = $token;
			}
		}

		return array(
			'matched' => $matched,
			'unknown' => $unknown,
		);
	}

	/**
	 * Recipes that contain any of the named ingredients, best match first.
	 *
	 * Counted per **token**, not per ingredient ID: "Paprika" names three
	 * ingredients, and a recipe using one of them has answered the question
	 * once, not three times.
	 *
	 * The whole ordered list is built here rather than in the main query,
	 * because ordering by a count and paginating are otherwise irreconcilable:
	 * WordPress can page through a list of IDs perfectly well, it just cannot
	 * sort by something no column holds. At this library's size the list is a
	 * few hundred integers.
	 *
	 * @param string[] $tokens Raw tokens.
	 * @return array<int,int> Recipe ID => number of tokens matched, ordered.
	 */
	public static function rank_recipes_by_ingredients( array $tokens ): array {

		static $memo = array();

		$key = md5( (string) wp_json_encode( $tokens ) );

		if ( isset( $memo[ $key ] ) ) {
			return $memo[ $key ];
		}

		$hits = array();

		foreach ( self::resolve_ingredient_tokens( $tokens )['matched'] as $ids ) {

			$recipe_ids = get_posts(
				Mixmymeals_Post_Types::exclude_unlisted(
					array(
						'post_type'      => 'mmm_recipe',
						'post_status'    => 'publish',
						'posts_per_page' => -1,
						'fields'         => 'ids',
						'no_found_rows'  => true,
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						'meta_query'     => array(
							array(
								'key'     => Mixmymeals_Recipe::META_INGREDIENT_INDEX,
								'value'   => array_map( 'strval', $ids ),
								'compare' => 'IN',
							),
						),
					)
				)
			);

			foreach ( (array) $recipe_ids as $recipe_id ) {
				$recipe_id          = (int) $recipe_id;
				$hits[ $recipe_id ] = ( $hits[ $recipe_id ] ?? 0 ) + 1;
			}
		}

		arsort( $hits );

		$memo[ $key ] = $hits;

		return $hits;
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

		/*
		 * Über Diet::label(), nicht über eine eigene Zuordnung: eine Form, die
		 * nur über den Filter mixmymeals_diet_types dazukommt, hätte hier sonst
		 * keinen Namen und bliebe als Chip stumm, obwohl get_diet_type() sie
		 * liefert.
		 */
		$diet_label = Mixmymeals_Diet::label( $diet );

		ob_start();
		?>
		<section class="mmm-plan-hero mmm-ingredient-hero">
			<div class="mmm-plan-hero__blob"></div>
			<h1><?php echo esc_html( $title ); ?></h1>

			<div class="mmm-plan-hero__meta">
				<?php foreach ( (array) $cats as $cat ) : ?>
					<span class="mmm-chip mmm-chip--stat"><i class="mmm-icon fa-solid fa-folder" aria-hidden="true"></i><?php echo esc_html( self::chip_text( 'category', array( 'category' => $cat->name ) ) ); ?></span>
				<?php endforeach; ?>
				<?php if ( '' !== $diet_label ) : ?>
					<span class="mmm-chip mmm-chip--stat"><i class="mmm-icon fa-solid fa-leaf" aria-hidden="true"></i><?php echo esc_html( self::chip_text( 'diet', array( 'diet' => $diet_label ) ) ); ?></span>
				<?php endif; ?>
				<?php if ( $calories > 0 ) : ?>
					<span class="mmm-chip mmm-chip--stat"><i class="mmm-icon fa-solid fa-fire" aria-hidden="true"></i><?php
						echo esc_html(
							self::chip_text(
								'ingredient_energy',
								array( 'kcal' => number_format_i18n( round( $calories ) ) )
							)
						);
					?></span>
				<?php endif; ?>
				<?php if ( $price > 0 ) : ?>
					<span class="mmm-chip mmm-chip--stat mmm-chip--price"><i class="mmm-icon fa-solid fa-euro-sign/" aria-hidden="true"></i><?php
						echo esc_html(
							self::chip_text(
								'ingredient_price',
								array( 'price' => number_format_i18n( $price, 2 ) . ' ' . $currency )
							)
						);
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

		return $this->render_ingredient_nutrition_table( $ingredient->get_nutrition(), $this->traffic_lights( $ingredient_id ) );
	}

	/**
	 * Builds the per-100 g nutrition table for an ingredient, or '' when there
	 * are no values.
	 *
	 * @param array $nutri  Nutrition data from Mixmymeals_Ingredient::get_nutrition().
	 * @param array $lights The traffic lights, from traffic_lights().
	 * @return string
	 */
	private function render_ingredient_nutrition_table( array $nutri, array $lights = array() ): string {

		// Same labels and order as everywhere else; only the energy key differs,
		// because an ingredient stores its calories per 100 g.
		$labels = Mixmymeals_Meal_Plan::nutrition_labels();

		// The value key differs from the nutrition key only for energy, which an
		// ingredient stores per 100 g — the pairing itself lives in one place.
		$rows = array();

		foreach ( Mixmymeals_Ingredient::NUTRITION_KEY_MAP as $label_key => $value_key ) {
			$rows[] = array( $label_key, $value_key );
		}

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

		$figures = array();

		foreach ( $rows as list( $mmm_key, $mmm_field ) ) {
			$figures[ $mmm_key ] = isset( $nutri[ $mmm_field ] ) ? (float) $nutri[ $mmm_field ] : 0.0;
		}

		$table = $this->render_nutrition_table(
			'ingredient',
			array( 'per_100g' => $this->format_nutrition_column( $figures ) ),
			array( 'lights' => $lights )
		);

		return '' === $table ? '' : '<div class="mmm-recipe-nutrition mmm-ingredient-nutrition">' . $table . '</div>';
	}

	/**
	 * Shortcode [mmm_ingredient_products].
	 *
	 * Lists the concrete, branded articles this ingredient can be bought as,
	 * each with the figures its packet states.
	 *
	 * Renders nothing when there are no products, which is the ordinary case:
	 * products are worth maintaining only where they differ a lot — yoghurt at
	 * 0.1 % or 10 % fat, minced meat, cheese — so most ingredients never get
	 * one and their page must not grow an empty section.
	 *
	 * @param array|string $atts Shortcode attributes: id.
	 * @return string
	 */
	public function mmm_ingredient_products_callback( $atts = array() ): string {

		$atts          = shortcode_atts( array( 'id' => 0 ), $atts, 'mmm_ingredient_products' );
		$ingredient_id = (int) $atts['id'] > 0 ? (int) $atts['id'] : (int) get_the_ID();

		if ( ! $ingredient_id || 'mmm_ingredient' !== get_post_type( $ingredient_id ) ) {
			return '';
		}

		$products = Mixmymeals_Product::for_ingredient( $ingredient_id );

		if ( empty( $products ) ) {
			return '';
		}

		$reference = Mixmymeals_Product::get_reference_id( $ingredient_id );

		// Same labels and the same order as every other nutrition table on the
		// site; only the energy key differs, because a product stores its
		// calories per 100 g.
		$labels = Mixmymeals_Meal_Plan::nutrition_labels();
		$cols   = Mixmymeals_Ingredient::NUTRITION_KEY_MAP;

		/*
		 * One column is the anchor and keeps its absolute figures; the rest are
		 * stated as the difference to it. The reference product is that anchor
		 * when there is one — it is already the packet the ingredient's own
		 * nutrition comes from, so the whole page agrees on one starting point.
		 */
		$baseline = $this->pick_products_baseline( $products, $reference );

		// The anchor goes first. "Difference to the column three across" is not
		// something a reader should have to hold in their head.
		usort(
			$products,
			static fn( $a, $b ) => ( $a->get_id() === $baseline->get_id() ? 0 : 1 )
				<=> ( $b->get_id() === $baseline->get_id() ? 0 : 1 )
		);

		// Read once per product rather than once per cell: eight rows times
		// however many products is a lot of meta lookups for one table.
		$nutrition = array();

		foreach ( $products as $product ) {
			$nutrition[ $product->get_id() ] = $product->get_nutrition();
		}

		$base_values = $nutrition[ $baseline->get_id() ];

		$head = __( 'Nutrition', 'mixmymeals' );

		/*
		 * Products across, nutrition down — the same shape as every other
		 * nutrition table on the site, and the only one that fits: eight
		 * figures as columns push Protein and Salt off the page, which are
		 * exactly the two a reader compares yoghurts by.
		 *
		 * It carries the shared skeleton (mmm-nutrition-table mmm-nutri-table)
		 * like every other nutrition table, so it is the same object to a
		 * reader. What it does **not** do is collapse into one card per row on
		 * a phone: the theme hides the table head to do that, and here the head
		 * carries the brand, the package size and the source — exactly what
		 * says whose figures these are. The modifier is what the theme hangs
		 * that exception on; it scrolls sideways in its wrapper instead, like
		 * the ingredient's own nutrition table above it.
		 */
		ob_start();
		?>
		<div class="mmm-ingredient-products">
			<div class="mmm-ingredient-products__scroll">
			<table class="mmm-nutrition-table mmm-nutri-table mmm-nutri-table--products">
				<thead>
				<tr>
					<th><?php echo esc_html( $head ); ?></th>
					<?php foreach ( $products as $mmm_product ) : ?>
						<th>
							<?php
							// Already escaped piece by piece in the helper.
							echo $this->render_product_identity( $mmm_product, $reference ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							?>
						</th>
					<?php endforeach; ?>
				</tr>
				</thead>
				<tbody>
				<?php foreach ( $cols as $mmm_key => $mmm_field ) : ?>
					<?php /* Dieselben Klassen wie die übrigen Nährwerttabellen, sonst
					         bleiben die „davon"-Zeilen hier als einzige
					         uneingerückt — und gesättigte Fettsäuren fehlten
					         seit 3.52.0 ganz. */ ?>
					<tr<?php echo in_array( $mmm_key, array( 'sugar', 'saturates' ), true ) ? ' class="mmm-nutri-table__sub mmm-nutrition-table__sub"' : ''; ?>>
						<td data-label="<?php echo esc_attr( $head ); ?>"><?php echo esc_html( $labels[ $mmm_key ] ); ?></td>
						<?php foreach ( $products as $mmm_product ) : ?>
							<?php
							$mmm_value    = $nutrition[ $mmm_product->get_id() ][ $mmm_field ] ?? null;
							$mmm_is_base  = $mmm_product->get_id() === $baseline->get_id();
							$mmm_base_val = $base_values[ $mmm_field ] ?? null;
							?>
							<td class="<?php echo esc_attr( $mmm_is_base ? 'mmm-ingredient-products__base' : 'mmm-ingredient-products__delta' ); ?>"
								data-label="<?php echo esc_attr( $mmm_product->get_title() ); ?>">
								<?php
								/*
								 * A dash, never a zero. The whole product layer
								 * rests on "not stated on the packet" and
								 * "contains none" being different claims, and
								 * printing 0,0 g would turn the first into the
								 * second — in public, about somebody else's
								 * product. A difference needs the figure on both
								 * sides, so one missing end gives a dash too:
								 * treating an absent value as zero would invent
								 * the largest difference in the table.
								 */
								if ( $mmm_is_base ) {
									echo esc_html(
										null === $mmm_value
											? '—'
											: Mixmymeals_Meal_Plan::format_nutrition_value( $mmm_key, (float) $mmm_value )
									);
								} else {
									echo esc_html( $this->format_nutrition_delta( $mmm_key, $mmm_base_val, $mmm_value ) );
								}
								?>
							</td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			</div>

			<p class="mmm-ingredient-products__note">
				<?php esc_html_e( 'Values per 100 g as stated by the manufacturer. Listed for orientation, not as a recommendation.', 'mixmymeals' ); ?>
				<?php if ( count( $products ) > 1 ) : ?>
					<?php
					printf(
						/* translators: %s: name of the product the other columns are compared against. */
						esc_html__( 'The other columns state the difference to %s.', 'mixmymeals' ),
						esc_html( $baseline->get_title() )
					);
					?>
				<?php endif; ?>
			</p>

		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * The column everything else is measured against.
	 *
	 * The reference product when the ingredient has one, so the comparison
	 * starts from the same packet the ingredient's own nutrition does. Failing
	 * that the lowest-energy product, which is the one people reach for this
	 * table to move away from — and a stable choice, unlike "the first one
	 * entered", which would silently re-anchor the whole table the day someone
	 * adds a product.
	 *
	 * Products stating no energy at all cannot win that vote; if none states
	 * any, the first one anchors it, because a table with no anchor has nothing
	 * to show.
	 *
	 * @param Mixmymeals_Product[] $products  Products of the ingredient, at least one.
	 * @param int                  $reference Reference product ID, 0 if none.
	 * @return Mixmymeals_Product
	 */
	private function pick_products_baseline( array $products, int $reference ): Mixmymeals_Product {

		if ( $reference > 0 ) {
			foreach ( $products as $product ) {
				if ( $reference === $product->get_id() ) {
					return $product;
				}
			}
		}

		$lowest = null;
		$fewest = null;

		foreach ( $products as $product ) {

			$calories = $product->get_nutrition()['calories_100'] ?? null;

			if ( null === $calories ) {
				continue;
			}

			if ( null === $fewest || $calories < $fewest ) {
				$lowest = $product;
				$fewest = $calories;
			}
		}

		return $lowest ?? $products[0];
	}

	/**
	 * One figure stated as its difference to the baseline.
	 *
	 * Returns a dash unless both ends are stated. A missing value is not zero
	 * here any more than anywhere else, and treating it as one would not just
	 * invent a number — it would invent the largest difference in the table and
	 * attribute it to somebody else's product.
	 *
	 * Signed with a real minus (U+2212) rather than a hyphen, since these sit
	 * in a column of figures where a hyphen reads as a dash — which is exactly
	 * what this method returns to mean "not stated".
	 *
	 * @param string     $key   Nutrition key, for units and precision.
	 * @param float|null $base  The baseline's figure.
	 * @param float|null $value This product's figure.
	 * @return string
	 */
	private function format_nutrition_delta( string $key, ?float $base, ?float $value ): string {

		if ( null === $base || null === $value ) {
			return '—';
		}

		$delta    = (float) $value - (float) $base;
		$absolute = Mixmymeals_Meal_Plan::format_nutrition_value( $key, abs( $delta ) );

		/*
		 * Zero after rounding is still zero to the reader: 0.04 g of salt shown
		 * as "+0,04" would be a difference the column cannot actually display.
		 * Compared against the rendered string rather than the raw float, so
		 * what is written always matches what is claimed.
		 */
		if ( $absolute === Mixmymeals_Meal_Plan::format_nutrition_value( $key, 0.0 ) ) {
			return '±' . $absolute;
		}

		return ( $delta > 0 ? '+' : "\u{2212}" ) . $absolute;
	}

	/**
	 * The heading cell of a product column: what the article is, and what role
	 * it plays in the comparison.
	 *
	 * Built from divs rather than spans styled into blocks. The lines are
	 * separate lines in any browser that way, with no stylesheet involved — a
	 * site serving a stale frontend.css ran the name, the brand and the source
	 * together into one unreadable string, and a stack that basic has no
	 * business depending on CSS arriving.
	 *
	 * @param Mixmymeals_Product $product   The product.
	 * @param int                $reference ID of the ingredient's reference product, 0 if none.
	 * @return string Escaped markup.
	 */
	private function render_product_identity( Mixmymeals_Product $product, int $reference ): string {

		$out = '<div class="mmm-ingredient-products__identity">';

		/*
		 * Only the reference gets a chip, and only because it is a claim about
		 * the ingredient: its nutrition comes from this packet. Which column
		 * anchors the arithmetic is not a property of the product, and the note
		 * under the table already names it — as a chip it sat next to a brand
		 * looking like a distinction the brand had earned.
		 *
		 * Above the name, not under it: it qualifies the whole column, and in
		 * the markup rather than by CSS order, so what a screen reader
		 * announces is what the column reads as — the claim, then the packet
		 * it is about.
		 */
		$flag = esc_html__( 'Used for the nutrition figures', 'mixmymeals' );

		if ( $reference > 0 && $reference === $product->get_id() ) {
			$out .= '<div class="mmm-ingredient-products__flag">' . $flag . '</div>';
		} else {
			/*
			 * The same chip, invisible, in every other column: without it the
			 * product names sit at different heights across the row — one
			 * column starts a chip lower than the rest — and the row of names
			 * is what a reader scans across.
			 *
			 * A hidden copy rather than a margin calculated from the chip's
			 * font size, padding and border: those are four numbers in the
			 * theme that would have to be kept in step with this one by hand,
			 * and the first change to the chip's size would silently
			 * misalign the names again. visibility:hidden keeps the box,
			 * aria-hidden keeps it out of what is read aloud.
			 */
			$out .= '<div class="mmm-ingredient-products__flag mmm-ingredient-products__flag--placeholder" aria-hidden="true">'
				. $flag . '</div>';
		}

		$out .= '<div class="mmm-ingredient-products__title">'
			. esc_html( $product->get_title() ) . '</div>';

		$meta  = array();
		$brand = $product->get_brand();

		if ( '' !== $brand ) {
			$meta[] = esc_html( $brand );
		}

		$weight = $product->get_package_weight();

		if ( $weight > 0 ) {
			$meta[] = esc_html(
				sprintf(
					/* translators: %s: a package weight in grams. */
					__( '%s g package', 'mixmymeals' ),
					number_format_i18n( $weight )
				)
			);
		}

		if ( $meta ) {
			$out .= '<div class="mmm-ingredient-products__meta">'
				. implode( ' &middot; ', $meta ) . '</div>';
		}

		$source = $product->get_source();

		if ( '' !== $source ) {
			$out .= '<div class="mmm-ingredient-products__source">'
				. sprintf(
					/* translators: %s: where the figures were taken from. */
					esc_html__( 'Source: %s', 'mixmymeals' ),
					$this->render_product_source( $source )
				)
				. '</div>';
		}

		return $out . '</div>';
	}

	/**
	 * Shortcode [mmm_meal_plan_product_impact].
	 *
	 * What the choice of product does to this plan's daily nutrition per person.
	 * The ingredient page shows what a packet holds per 100 g; this shows what
	 * picking one of them is worth over a week, which is the figure somebody
	 * standing in a shop can act on.
	 *
	 * Tables only. The heading and the sentence that introduces them belong to
	 * whoever places this on a page — in the child theme both sit in
	 * single-mmm_meal_plan.php, next to each other and in one text domain.
	 *
	 * Renders nothing unless the plan has an ingredient with several products
	 * that actually pull a figure apart — the ordinary plan is unaffected.
	 *
	 * @param array|string $atts Shortcode attributes: id.
	 * @return string
	 */
	public function mmm_meal_plan_product_impact_callback( $atts = array() ): string {

		$atts    = shortcode_atts( array( 'id' => 0 ), $atts, 'mmm_meal_plan_product_impact' );
		$plan_id = (int) $atts['id'] > 0 ? (int) $atts['id'] : (int) get_the_ID();

		if ( ! $plan_id || 'mmm_meal_plan' !== get_post_type( $plan_id ) ) {
			return '';
		}

		$plan = new Mixmymeals_Meal_Plan( $plan_id );

		// Meal_Plan has no is_valid(); an empty meal list is what the other
		// plan shortcodes check, and it is the same guard this needs.
		if ( empty( $plan->get_meals() ) ) {
			return '';
		}

		return $this->render_product_impact(
			$plan->calc_product_impact(),
			__( 'Per day and person', 'mixmymeals' ),
			__( 'Nutrition per day and person over the whole plan, depending on which product is used for this ingredient.', 'mixmymeals' )
		);
	}

	/**
	 * Shortcode [mmm_meal_product_impact], also registered as
	 * [mmm_recipe_product_impact].
	 *
	 * The same comparison for a single dish, stated per serving. On a meal it
	 * resolves to the recipe the meal plans; on a recipe it answers for itself.
	 * One callback for both names, because a meal and the recipe it plans are
	 * the same dish and must not be able to state different figures for it.
	 *
	 * Per serving rather than per plan day because this page *is* the dish: a
	 * plan spreads one meal over its whole length and can dilute a real choice
	 * into a couple of kilocalories, and here there is nothing to spread it
	 * over.
	 *
	 * @param array|string $atts Shortcode attributes: id.
	 * @return string
	 */
	public function mmm_meal_product_impact_callback( $atts = array() ): string {

		$atts    = shortcode_atts( array( 'id' => 0 ), $atts, 'mmm_meal_product_impact' );
		$post_id = (int) $atts['id'] > 0 ? (int) $atts['id'] : (int) get_the_ID();

		if ( ! $post_id ) {
			return '';
		}

		$type = get_post_type( $post_id );

		if ( 'mmm_meal' === $type ) {
			// As the meal cooks it: rows its diet decided drop out of the
			// comparison, as a recipe's own pins do.
			$recipe = ( new Mixmymeals_Meal( $post_id ) )->get_recipe();
		} elseif ( 'mmm_recipe' !== $type ) {
			return '';
		} else {
			$recipe = new Mixmymeals_Recipe( (int) $post_id );
		}

		if ( ! $recipe->is_valid() ) {
			return '';
		}

		return $this->render_product_impact(
			$recipe->calc_product_impact(),
			__( 'Per serving', 'mixmymeals' ),
			__( 'Nutrition of one serving of this dish, depending on which product is used for this ingredient.', 'mixmymeals' )
		);
	}

	/**
	 * Draws the product comparison: one table per ingredient, nutrition down,
	 * the current figures in the first column and one column of differences per
	 * product.
	 *
	 * Shared by the plan and the dish, which differ only in what their figures
	 * are per — and therefore in one column heading. Writing the markup twice
	 * would have meant two tables that drift apart in everything but the
	 * arithmetic.
	 *
	 * The product currently supplying an ingredient's figures is marked with an
	 * asterisk carrying the class mmm-product-impact__ref-mark. Explaining it is
	 * the caller's job: a legend is page copy, and a caller can look for that
	 * class to find out whether any table here earned one.
	 *
	 * Three things the table has to say about itself, because the numbers in
	 * it belong to none of the words around them: the figures are the *dish's*
	 * (or the plan's), the heading above them names the *ingredient* whose
	 * product is in question, and the columns are headed by *products*. A
	 * reader who takes "108 kcal" under "Vollmilchjoghurt" for the yoghurt's
	 * energy has read exactly what the table showed them.
	 *
	 * So: a caption states what the figures are of, the anchor column says it
	 * holds the current calculation, and every other column says it holds a
	 * difference.
	 *
	 * @param array  $groups     From Mixmymeals_Product::impact_groups().
	 * @param string $unit_label Heading of the nutrition column, e.g. "Per serving".
	 * @param string $caption    One sentence saying what the figures are of.
	 * @return string
	 */
	private function render_product_impact( array $groups, string $unit_label, string $caption = '' ): string {

		if ( empty( $groups ) ) {
			return '';
		}

		$labels = Mixmymeals_Meal_Plan::nutrition_labels();

		ob_start();
		?>
		<div class="mmm-product-impact">

			<?php foreach ( $groups as $mmm_group ) : ?>
				<div class="mmm-product-impact__group">

					<h3 class="mmm-product-impact__ingredient">
						<?php echo esc_html( $mmm_group['ingredient'] ); ?>
						<?php if ( $mmm_group['meals'] > 0 ) : ?>
							<span class="mmm-product-impact__meals">
								<?php
								printf(
									/* translators: %s: number of meals in the plan that use this ingredient. */
									esc_html( _n( 'in %s meal', 'in %s meals', $mmm_group['meals'], 'mixmymeals' ) ),
									esc_html( number_format_i18n( $mmm_group['meals'] ) )
								);
								?>
							</span>
						<?php endif; ?>
					</h3>

					<?php
					/*
					 * Outside the scroll wrapper, not a <caption>: a caption
					 * belongs to the table and scrolls sideways with it, so on
					 * a phone the one sentence explaining the figures is the
					 * first thing to leave the screen. aria-describedby keeps
					 * the association a caption would have given.
					 */
					$mmm_caption_id = 'mmm-impact-caption-' . (int) $mmm_group['ingredient_id'];
					?>
					<?php if ( '' !== $caption ) : ?>
						<p class="mmm-product-impact__caption" id="<?php echo esc_attr( $mmm_caption_id ); ?>">
							<?php echo esc_html( $caption ); ?>
						</p>
					<?php endif; ?>

					<div class="mmm-product-impact__scroll">
						<table class="mmm-nutrition-table mmm-nutri-table mmm-nutri-table--impact"<?php echo '' !== $caption ? ' aria-describedby="' . esc_attr( $mmm_caption_id ) . '"' : ''; ?>>
							<thead>
							<tr>
								<th class="mmm-nutri-table__col mmm-nutri-table__col--label"><?php echo esc_html( $unit_label ); ?></th>
								<?php foreach ( $mmm_group['products'] as $mmm_row ) : ?>
									<th class="mmm-nutri-table__col mmm-nutri-table__col--product<?php echo $mmm_row['is_anchor'] ? ' mmm-nutri-table__col--base' : ''; ?>">
										<div class="mmm-product-impact__product">
											<?php echo esc_html( $mmm_row['title'] ); ?>
											<?php if ( $mmm_row['is_reference'] ) : ?>
												<?php
												/*
												 * A marker, not a sentence. What
												 * it means is a legend under the
												 * tables, which belongs to
												 * whoever places this on a page —
												 * the class is how they can tell
												 * whether any table earned one.
												 */
												?>
												<span class="mmm-product-impact__ref-mark">*</span>
											<?php endif; ?>
										</div>
										<?php if ( '' !== ( $mmm_row['brand'] ?? '' ) ) : ?>
											<?php
											/*
											 * Under the name, because two
											 * products of one ingredient are
											 * often told apart by the brand
											 * alone — "Naturjoghurt 3,8 %" is
											 * not a name that identifies
											 * anything on a shelf.
											 */
											?>
											<div class="mmm-product-impact__brand"><?php echo esc_html( $mmm_row['brand'] ); ?></div>
										<?php endif; ?>
										<?php
										/*
										 * What kind of number stands below.
										 * Without it the anchor's absolutes
										 * read as a property of that packet,
										 * and the signed figures beside them
										 * as a second, smaller packet.
										 */
										?>
										<div class="mmm-product-impact__kind">
											<?php
											echo esc_html(
												$mmm_row['is_anchor']
													? __( 'as calculated now', 'mixmymeals' )
													: __( 'difference', 'mixmymeals' )
											);
											?>
										</div>
									</th>
								<?php endforeach; ?>
							</tr>
							</thead>
							<tbody>
							<?php if ( ! empty( $mmm_group['has_diet'] ) ) : ?>
								<?php
								/*
								 * First, above the figures: "can I still call
								 * this vegan" is a different kind of question
								 * from "how much fat", and it is the one
								 * somebody scans for. Nobody should have to
								 * read past seven numbers to find it.
								 *
								 * The labels come from Diet::label() below,
								 * not from a map of their own — a diet added
								 * through the mixmymeals_diet_types filter
								 * would otherwise show no name here, although
								 * this row already knows to print it.
								 */
								?>
								<tr class="mmm-product-impact__diet-row">
									<td data-label="<?php echo esc_attr( $unit_label ); ?>"><?php esc_html_e( 'Diet', 'mixmymeals' ); ?></td>
									<?php foreach ( $mmm_group['products'] as $mmm_row ) : ?>
										<td class="<?php echo esc_attr( $mmm_row['is_anchor'] ? 'mmm-product-impact__base' : 'mmm-product-impact__delta' ); ?>"
											data-label="<?php echo esc_attr( trim( $mmm_row['title'] . ' ' . ( '' !== ( $mmm_row['brand'] ?? '' ) ? '(' . $mmm_row['brand'] . ')' : '' ) ) ); ?>">
											<?php
											/*
											 * A dash where the packet says
											 * nothing. The ingredient's own
											 * classification would stand in
											 * that case, but printing it here
											 * reads as this product claiming
											 * it — and "vegan" is a label
											 * people act on.
											 */
											echo esc_html( '' !== $mmm_row['diet'] ? Mixmymeals_Diet::label( $mmm_row['diet'] ) : '—' );
											?>
										</td>
									<?php endforeach; ?>
								</tr>
							<?php endif; ?>
							<?php foreach ( $labels as $mmm_key => $mmm_label ) : ?>
								<tr<?php echo in_array( $mmm_key, array( 'sugar', 'saturates' ), true ) ? ' class="mmm-nutri-table__sub mmm-nutrition-table__sub"' : ''; ?>>
									<td data-label="<?php echo esc_attr( $unit_label ); ?>"><?php echo esc_html( $mmm_label ); ?></td>
									<?php foreach ( $mmm_group['products'] as $mmm_row ) : ?>
										<?php
										/*
										 * The anchor states what the dish
										 * holds; every other column states the
										 * difference to it. A dash where the
										 * product says nothing at all: it takes
										 * no part in the comparison, the same
										 * rule the product table on the
										 * ingredient page follows, and for the
										 * same reason — this is a claim about
										 * somebody else's packet and the packet
										 * is silent.
										 */
										if ( $mmm_row['is_anchor'] ) {
											$mmm_text  = Mixmymeals_Meal_Plan::format_nutrition_value( $mmm_key, (float) ( $mmm_row['values'][ $mmm_key ] ?? 0 ) );
											$mmm_class = 'mmm-product-impact__base';
										} else {
											$mmm_delta = $mmm_row['deltas'][ $mmm_key ] ?? null;
											$mmm_text  = null === $mmm_delta
												? '—'
												: $this->format_nutrition_delta( $mmm_key, 0.0, (float) $mmm_delta );
											$mmm_class = 'mmm-product-impact__delta';
										}
										?>
										<td class="<?php echo esc_attr( $mmm_class ); ?>"
											data-label="<?php echo esc_attr( trim( $mmm_row['title'] . ' ' . ( '' !== ( $mmm_row['brand'] ?? '' ) ? '(' . $mmm_row['brand'] . ')' : '' ) ) ); ?>">
											<?php echo esc_html( $mmm_text ); ?>
										</td>
									<?php endforeach; ?>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>

				</div>
			<?php endforeach; ?>

		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * One source note, for the heading cell of its product column.
	 *
	 * A URL is shown as its host and linked; anything else stands as written
	 * ("packet, 08/2026"). Linked with nofollow, because naming a manufacturer
	 * to say where a figure came from is not an endorsement of it.
	 *
	 * @param string $source Raw source as stored on the product.
	 * @return string Escaped markup.
	 */
	private function render_product_source( string $source ): string {

		$url = filter_var( $source, FILTER_VALIDATE_URL ) ? $source : '';

		if ( '' === $url ) {
			return esc_html( $source );
		}

		return '<a href="' . esc_url( $url ) . '" rel="nofollow noopener" target="_blank">'
			. esc_html( (string) wp_parse_url( $url, PHP_URL_HOST ) ) . '</a>';
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

		/*
		 * fields => 'ids' above skips WP's priming, and the loop below asks
		 * each ingredient for its terms, its permalink and its title — three
		 * lookups per row. With the library past three hundred ingredients that
		 * was the single most expensive page on the site.
		 */
		if ( function_exists( '_prime_post_caches' ) ) {
			_prime_post_caches( $ids, true, true );
		}

		update_object_term_cache( $ids, 'mmm_ingredient' );

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
	 * The published recipes that use an ingredient.
	 *
	 * Reads the recipe index rather than scanning: this used to be an
	 * unanchored LIKE over the serialized step meta, capped at a hundred
	 * candidates sorted by title and twenty-four results, behind a transient
	 * nothing ever cleared. The cap is why the ingredient page broke off in the
	 * middle of the alphabet, and the transient is why it stayed broken off for
	 * an hour after somebody fixed the recipe.
	 *
	 * Drafts are deliberately not here. Deciding whether an ingredient may be
	 * deleted is a different question with a different answer, and it still
	 * scans — see Mixmymeals_REST_API::ingredient_usage_map().
	 *
	 * @param int $ingredient_id Ingredient ID.
	 * @param int $limit         0 for all of them.
	 * @return int[] Recipe IDs, alphabetical.
	 */
	private function recipes_using_ingredient( int $ingredient_id, int $limit = 0 ): array {

		$ids = Mixmymeals_Recipe::ids_using_ingredient( $ingredient_id );

		return $limit > 0 ? array_slice( $ids, 0, $limit ) : $ids;
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
						echo esc_html(
							self::chip_text(
								'equipment_recipes',
								array(
									'count' => number_format_i18n( $recipe_count ),
									/* translators: the word after a number of recipes. */
									'unit'  => _n( 'recipe', 'recipes', $recipe_count, 'mixmymeals' ),
								)
							)
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
		$meal_servings = max( 1.0, floatval( $meal->get_shown_servings() ) );

		if ( ! $recipe_id ) {
			return '<p>No recipe assigned.</p>';
		}

		$recipe = $meal->get_recipe();

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

			// The packet this row is made of, where it names one.
			$pinned    = (int) ( $ing['product_id'] ?? 0 );
			$nutrition = $ingredient_obj->get_nutrition_pinned( $pinned );
			$weight    = floatval( $ing['weight_g'] );
			if ( $weight <= 0 ) {
				continue;
			}

			// The grams on the plate are the cooked ones; the nutrients stay raw.
			$totals['weight']   += $ingredient_obj->cooked_weight( $weight, $pinned );
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
				<?php
				// Same labels as every other nutrition output; the CSS class names
				// stay as they are, because the calculator's JS addresses them.
				$mmm_labels = Mixmymeals_Meal_Plan::nutrition_labels();

				$mmm_slots = array(
					'calories' => 'cal',
					'fat'      => 'fat',
					'carbs'    => 'carbs',
					'sugar'    => 'sugar',
					'fiber'    => 'fiber',
					'protein'  => 'protein',
				);

				foreach ( $mmm_slots as $mmm_key => $mmm_slot ) :
					?>
                <p><?php echo esc_html( $mmm_labels[ $mmm_key ] ); ?>: <span
                            class="mmmm-meal-nutrition-calculator-<?php echo esc_attr( $mmm_slot ); ?>">–</span>
                </p>
				<?php endforeach; ?>
            </div>

        </div>

		<?php
		return ob_get_clean();
	}

}

