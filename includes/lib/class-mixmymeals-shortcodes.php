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

		add_shortcode( 'mmm_thumbnails_of_meals_in_a_single_meal_plan', [
			$this,
			'mmm_thumbnails_of_meals_in_a_single_meal_plan_callback'
		] );

		add_action( 'pre_get_posts',
			array( $this, 'filter_meal_plan_archive_by_url_parameter' ) );

		add_filter( 'query_vars', function( $vars ) {
			$vars[] = 'mmm_uncategorized';
			return $vars;
		} );

		// Recipe Archive:

		add_shortcode( 'mmm_meta_for_posts_in_recipe_archive',
			[ $this, 'mmm_meta_for_posts_in_recipe_archive_callback' ] );

		add_filter( 'get_the_archive_title',
			[ $this, 'remove_recipe_category_archive_title_prefix' ], 20 );

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

		add_shortcode( 'mmm_meal_plan_actions',
			[ $this, 'mmm_meal_plan_actions_callback' ] );

		add_shortcode( 'mmm_meal_plan_hero',
			[ $this, 'mmm_meal_plan_hero_callback' ] );

		add_shortcode( 'mmm_recipe_hero',
			[ $this, 'mmm_recipe_hero_callback' ] );

		add_shortcode( 'mmm_meal_hero',
			[ $this, 'mmm_meal_hero_callback' ] );

		add_shortcode( 'mmm_meta_for_a_single_meal_plan',
			[ $this, 'mmm_meta_for_a_single_meal_plan_callback' ] );

		add_shortcode( 'mmm_nutrition_overview_for_a_single_meal_plan',
			[ $this, 'mmm_nutrition_overview_for_a_single_meal_plan_callback' ] );

		// Single Recipe:

		add_shortcode( 'mmm_equipment_required_for_a_single_recipe',
			[ $this, 'mmm_equipment_required_for_a_single_recipe_callback' ] );

		add_shortcode( 'mmm_recipe_steps_for_a_single_recipe',
			[ $this, 'mmm_recipe_steps_for_a_single_recipe_callback' ] );

		add_shortcode( 'mmm_nutrition_table_for_a_single_recipe',
			[ $this, 'mmm_nutrition_table_for_a_single_recipe_callback' ] );

		add_shortcode( 'mmm_recipe_meta',
			[ $this, 'mmm_recipe_meta_callback' ] );

		add_shortcode( 'mmm_recipe_actions',
			[ $this, 'mmm_recipe_actions_callback' ] );

		add_shortcode( 'mmm_disclaimer_for_a_single_recipe',
			[ $this, 'mmm_disclaimer_for_a_single_recipe_callback' ] );

		add_shortcode( 'mmm_feature_image_for_a_single_recipe',
			[ $this, 'mmm_feature_image_for_a_single_recipe_callback' ] );

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


		// AJAX Handlers. NOT shortcode callbacks:

		add_action( 'wp_ajax_mmm_add_to_cart', array(
			$this,
			'mmm_add_recipe_to_cart_ajax_callback'
		) );
		add_action( 'wp_ajax_nopriv_mmm_add_to_cart', array(
			$this,
			'mmm_add_recipe_to_cart_ajax_callback'
		) );

		add_action( 'wp_ajax_mmm_get_cart_count', array(
			$this,
			'mmm_get_cart_count_ajax_callback'
		) );
		add_action( 'wp_ajax_nopriv_mmm_get_cart_count', array(
			$this,
			'mmm_get_cart_count_ajax_callback'
		) );

		add_action( 'wp_ajax_mmm_remove_cart_item', array(
			$this,
			'mmm_remove_cart_item_ajax_callback'
		) );
		add_action( 'wp_ajax_nopriv_mmm_remove_cart_item', array(
			$this,
			'mmm_remove_cart_item_ajax_callback'
		) );

		add_action( 'wp_ajax_mmm_update_cart_item', array(
			$this,
			'mmm_update_cart_item_ajax_callback'
		) );
		add_action( 'wp_ajax_nopriv_mmm_update_cart_item', array(
			$this,
			'mmm_update_cart_item_ajax_callback'
		) );

		add_action( 'wp_ajax_mmm_clear_cart',
			array( $this, 'mmm_clear_cart_ajax_callback' ) );
		add_action( 'wp_ajax_nopriv_mmm_clear_cart',
			array( $this, 'mmm_clear_cart_ajax_callback' ) );

		add_action( 'wp_ajax_mmm_create_meal_plan',
			array( $this, 'mmm_create_meal_plan_ajax_callback' ) );
		add_action( 'wp_ajax_nopriv_mmm_create_meal_plan',
			array( $this, 'mmm_create_meal_plan_ajax_callback' ) );

		add_action( 'wp_ajax_mmm_generate_pdf', array(
			$this,
			'mmm_generate_pdf_ajax_callback'
		) );
		add_action( 'wp_ajax_nopriv_mmm_generate_pdf', array(
			$this,
			'mmm_generate_pdf_ajax_callback'
		) );

		add_action( 'wp_ajax_mmm_load_plan_into_cart', array( $this, 'mmm_load_plan_into_cart_ajax_callback' ) );
		add_action( 'wp_ajax_nopriv_mmm_load_plan_into_cart', array( $this, 'mmm_load_plan_into_cart_ajax_callback' ) );

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
		$placeholder_id       = (int) get_option( 'mixmymeals_placeholder_image_id' );

		foreach ( $meals as $meal_post ) {
			$meal      = new Mixmymeals_Meal( $meal_post->ID );
			$recipe_id = (int) $meal->get_recipe_id();
			$day       = (int) $meal->get_day();

			if ( $recipe_id <= 0 || ! Mixmymeals_Recipe::exists_by_id( $recipe_id ) ) {
				continue;
			}

			$recipe   = new Mixmymeals_Recipe( $recipe_id );
			$priority = $recipe->get_priority();
			$thumb_id = (int) get_post_thumbnail_id( $recipe_id );
			$has_real_image = $thumb_id && ( ! $placeholder_id || $thumb_id !== $placeholder_id );

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

		$output = '<div class="mmm-mealplan-thumbs" style="display:flex; gap:6px; flex-wrap:wrap;">';

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
					'style'   => 'width:60px;height:60px;object-fit:cover;border-radius:6px;',
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
		$atts      = shortcode_atts( [], $atts,
			'mmm_meta_for_posts_in_recipe_archive' );
		$post_id   = get_the_ID();
		$post_type = get_query_var( 'post_type' );
		$post_type = is_array( $post_type ) ? reset( $post_type ) : $post_type;
		// Run on both recipe archive and tax archive or die:
		if ( ! is_post_type_archive( 'mmm_recipe' )
		     && ! is_tax( 'mmm_recipe_category' ) ) {
			return '';
		}
		if ( ! class_exists( 'Mixmymeals_Recipe' ) ) {
			return '';
		}
		$recipe    = new Mixmymeals_Recipe( $post_id );
		$nutrition = $recipe->calculate_nutrition_per_serving();
		$items     = [];
		ob_start();
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
		$recipe_id = get_the_ID();
		$items[] = sprintf(
			'<span class="mmm-meta__item mmm-recipe-actions__item--cart" data-recipe-id="%d" data-servings="%d">%s</span>',
			esc_attr( $recipe_id ),
			1,
			esc_html__( 'Add to Planner', 'mixmymeals' )
		);
		echo '<div class="mmm-recipe-archive-item-meta">' . implode( '',
				$items ) . '</div>';

		return ob_get_clean();
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
		$steps  = $recipe->get_steps();

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
							<?php echo esc_html( $step['description'] ); ?>
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

			$totals['calories'] += $nutrition['calories_100'] * $weight / 100;
			$totals['protein']  += $nutrition['protein'] * $weight / 100;
			$totals['carbs']    += $nutrition['carbs'] * $weight / 100;
			$totals['fat']      += $nutrition['fat'] * $weight / 100;
			$totals['sugar']    += $nutrition['sugar'] * $weight / 100;
			$totals['fiber']    += $nutrition['fiber'] * $weight / 100;
			$totals['salt']     += $nutrition['salt'] * $weight / 100;
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
	 * @param array|string $atts Shortcode attributes: meal_plan_id, show_dates.
	 * @return string
	 */
	public function mmm_meals_in_a_single_meal_plan_callback( $atts = [] ): string {

		$atts = shortcode_atts(
			[
				'meal_plan_id' => null,
				'show_dates'   => false,
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
		$days      = $meal_plan->get_day_range();

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

		foreach ( $meal_plan->get_meals() as $meal_post ) {
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

		$this->render_icon_sprite();
		?>

        <div class="mmm-plan-rail"
             data-meal-plan-id="<?php echo esc_attr( $meal_plan_id ); ?>">

			<?php foreach ( $days as $day_number ) : ?>

				<?php if ( empty( $grouped_meals[ $day_number ] ) ) {
					continue;
				} ?>

				<?php
				// Mahlzeiten des Tages flach einsammeln (in Typ-Reihenfolge).
				$day_meals = [];
				foreach ( $meal_types as $type_key => $type_label ) {
					foreach ( $grouped_meals[ $day_number ][ $type_key ] ?? [] as $day_meal ) {
						$day_meals[] = [
							'meal'       => $day_meal,
							'type_label' => $type_label,
						];
					}
				}
				$single_meal = ( count( $day_meals ) === 1 );
				?>

                <div class="mmm-rail-day<?php echo $day_number === $today ? ' mmm-rail-day--today' : ''; ?>">
                    <span class="mmm-rail-day__dot"></span>

                    <div class="mmm-rail-day__head">
                        <span class="mmm-rail-day__name"><?php echo esc_html( $weekday_labels[ $day_number ] ); ?></span>
						<?php if ( $day_number === $today ) : ?>
                            <span class="mmm-badge"><?php esc_html_e( 'Today', 'mixmymeals' ); ?></span>
						<?php endif; ?>
						<?php if ( $single_meal ) :
							$head_info = $this->get_meal_head_info( $day_meals[0] );
							?>
                            <span class="mmm-rail-day__meal"><?php echo esc_html( $day_meals[0]['type_label'] ); ?></span>
							<?php if ( $head_info ) : ?>
                                <span class="mmm-rail-day__kcal"><?php echo esc_html( $head_info ); ?></span>
							<?php endif; ?>
						<?php endif; ?>
                    </div>

					<?php foreach ( $day_meals as $entry ) :
						$meal      = $entry['meal'];
						$recipe_id = (int) $meal->get_recipe_id();
						$recipe    = new Mixmymeals_Recipe( $recipe_id );

						if ( ! $single_meal ) :
							$head_info = $this->get_meal_head_info( $entry );
							?>
                            <div class="mmm-rail-day__sub">
                                <span class="mmm-rail-day__meal"><?php echo esc_html( $entry['type_label'] ); ?></span>
								<?php if ( $head_info ) : ?>
                                    <span class="mmm-rail-day__kcal"><?php echo esc_html( $head_info ); ?></span>
								<?php endif; ?>
                            </div>
						<?php endif; ?>

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

						$thumb_id = get_post_thumbnail_id( $recipe_id )
							?: (int) get_option( 'mixmymeals_placeholder_image_id' );

						$cost = $recipe->calculate_cost_per_serving();
						?>
                        <a class="mmm-recipe-card" href="<?php echo esc_url( $meal_url ); ?>">
                            <div class="mmm-recipe-card__media<?php echo $thumb_id ? '' : ' mmm-recipe-card__media--empty'; ?>">
								<?php if ( $thumb_id ) :
									echo wp_get_attachment_image( $thumb_id, 'medium_large', false, [
										'alt'     => esc_attr( $title ),
										'loading' => 'lazy',
										'sizes'   => '(max-width: 640px) 84px, 124px',
									] );
								else : ?>
                                    <span><?php esc_html_e( 'No image yet', 'mixmymeals' ); ?></span>
								<?php endif; ?>
                            </div>

                            <div class="mmm-recipe-card__body">
                                <h3 class="mmm-recipe-card__title"><?php echo esc_html( $title ); ?></h3>

                                <div class="mmm-recipe-card__meta">
									<?php if ( $meal->get_servings() ) : ?>
                                        <span class="mmm-chip"><svg><use href="#mmm-i-users"/></svg><?php
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
                                        <span class="mmm-chip"><svg><use href="#mmm-i-clock"/></svg><?php echo esc_html( $prep_time_string ); ?></span>
									<?php endif; ?>
									<?php if ( $cost > 0 ) : ?>
                                        <span class="mmm-chip mmm-chip--price"><svg><use href="#mmm-i-price"/></svg><?php echo esc_html( number_format_i18n( $cost, 2 ) . ' ' . $currency ); ?></span>
									<?php endif; ?>
                                </div>

                                <div class="mmm-recipe-card__tags">
									<?php
									$first = true;
									foreach ( $categories ?: [] as $cat ) {
										printf(
											'<span class="mmm-tag%s">%s</span>',
											$first ? '' : ' mmm-tag--neutral',
											esc_html( $cat->name )
										);
										$first = false;
									}
									if ( ! empty( $tags ) ) {
										usort( $tags, fn( $a, $b ) => strcasecmp( $a->name, $b->name ) );
										foreach ( $tags as $tag ) {
											printf(
												'<span class="mmm-tag mmm-tag--neutral">%s</span>',
												esc_html( $tag->name )
											);
										}
									}
									?>
                                </div>
                            </div>
                        </a>
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

		// Array für JSON-LD Zutaten vorbereiten
		$json_ingredients = [];

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
						$needed_amount   = $this->build_needed_amount_string(
							$data['units'] ?? [],
							$total_weight
						);

						// Zutat für JSON-LD sammeln
						$json_ingredients[] = $ingredient_name . ', '
						                      . $needed_amount;

						// Anzeige: Zutat und Menge getrennt auszeichnen.
						$item_html = '<span class="mmm-shopping-item-name">'
						             . esc_html( $ingredient_name )
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
													'%s × %s für %s',
													round( $detail['amount'],
														2 ),
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
		// JSON-LD Output
		$schema_data = [
			'@context'         => 'https://schema.org',
			'@type'            => 'Recipe',
			'name'             => get_the_title( $meal_plan_id ),
			'recipeIngredient' => $json_ingredients,
		];
		echo '<script type="application/ld+json">'
		     . wp_json_encode( $schema_data ) . '</script>';

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
	 * Builds the human-readable needed-amount string for one shopping
	 * list ingredient from its per-unit totals.
	 *
	 * Indivisible units are listed as counts ("3× Stück"), everything
	 * else is collapsed into grams. A gram equivalent is appended when
	 * piece counts are shown.
	 *
	 * @param array $units        Per-unit totals from calc_shopping_list().
	 * @param float $total_weight Total weight in grams.
	 * @return string E.g. "3× Stück + 150 g (≈ 405 g)".
	 */
	private function build_needed_amount_string( array $units, float $total_weight ): string {

		$piece_parts = [];
		$loose_grams = 0.0;

		foreach ( $units as $unit ) {
			if ( ! empty( $unit['is_indivisible'] ) ) {
				$piece_parts[] = trim( sprintf(
					'%d× %s',
					(int) ( $unit['amount'] ?? 0 ),
					$unit['unit_title'] ?? ''
				) );
			} else {
				$loose_grams += (float) ( $unit['weight_g'] ?? 0 );
			}
		}

		$parts = $piece_parts;

		if ( $loose_grams > 0 || empty( $parts ) ) {
			$parts[] = number_format_i18n( round( $loose_grams ) ) . ' g';
		}

		$result = implode( ' + ', $parts );

		if ( ! empty( $piece_parts ) && $total_weight > 0 ) {
			$result .= sprintf(
				' (≈ %s g)',
				number_format_i18n( round( $total_weight ) )
			);
		}

		return $result;
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
			return '<p>' . esc_html__( 'No recipe specified.', 'mixmymeals' )
			       . '</p>';
		}

		$equipment_ids = get_post_meta( $post_id, '_mmm_equipment', true );

		if ( empty( $equipment_ids ) || ! is_array( $equipment_ids ) ) {
			return '<p>'
			       . esc_html__( 'No special equipment required for this recipe.',
					'mixmymeals' ) . '</p>';
		}

		ob_start();

		echo '<div class="mmm-equipment">';
		echo '<div class="mmm-equipment-header">';
		echo '<h3>' . esc_html__( 'Required Equipment', 'mixmymeals' )
		     . '</h3>';
		echo '</div>';
		echo '<div class="mmm-equipment-description">';
		echo '<ul class="mmm-equipment-list">';

		foreach ( $equipment_ids as $eq_id ) {
			$title = get_the_title( $eq_id );
			if ( ! $title ) {
				continue;
			}

			printf(
				'<li><a class="mmm-equipment-name" href="%s">%s</a></li>',
				esc_url( get_permalink( $eq_id ) ),
				esc_html( $title )
			);
		}

		echo '</ul>';
		echo '</div>';
		echo '</div>';

		return ob_get_clean();
	}


	/**
	 * Shortcode [mmm_recipe_steps_for_a_single_recipe].
	 * Renders the step-by-step cooking instructions for the current or specified recipe.
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
							<?php echo esc_html( $step['description'] ); ?>
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

	/*
	function mmm_add_recipe_to_cart_button_callback( $atts ) {

		$atts = shortcode_atts(
			array(
				'recipe_id' => 0,
				'label'     => __( 'Add to meal plan', 'mixmymeals' ),
			),
			$atts
		);

		$recipe_id = intval( $atts['recipe_id'] );

		// Falls keine ID übergeben wurde → aktuelle Abfrage
		if ( $recipe_id <= 0 ) {
			$recipe_id = get_the_ID();
		}

		if ( $recipe_id <= 0 ) {
			return '<p style="color:red;">Invalid recipe ID.</p>';
		}

		// Button-HTML
		ob_start();
		?>
		<button
				class="mmm-add-to-cart-btn"
				data-recipe-id="<?php echo esc_attr( $recipe_id ); ?>">
			<?php echo esc_html( $atts['label'] ); ?>
		</button>

		<div class="mmm-add-to-cart-message" style="margin-top:6px;"></div>
		<?php
		return ob_get_clean();
	}
	*/

	/**
	 * AJAX handler for wp_ajax_mmm_add_to_cart.
	 * Adds a recipe to the current user's or guest's cart.
	 *
	 * @return void
	 */
	public function mmm_add_recipe_to_cart_ajax_callback(): void {

		check_ajax_referer( 'mmm_frontend_nonce', 'nonce' );

		$recipe_id = isset( $_POST['recipe_id'] )
			? intval( $_POST['recipe_id'] ) : 0;

		if ( $recipe_id <= 0 ) {
			wp_send_json_error( [
				'code'    => 'invalid_recipe_id',
				'message' => __( 'Invalid recipe ID.', 'mixmymeals' ),
			], 400 );
		}


		/*
		$cart = new Mixmymeals_Cart();
		$cart->add( $recipe_id );

		wp_send_json_success( array( 'message' => 'Recipe added to meal plan.' ) );
		*/

		$servings = isset( $_POST['servings'] )
			? max( 1, intval( $_POST['servings'] ) )
			: 1;

		$type = isset( $_POST['type'] )
			? sanitize_text_field( wp_unslash( $_POST['type'] ) )
			: '';
		if ( ! array_key_exists( $type, Mixmymeals_Meal::get_plaintext_meal_type_map() ) ) {
			$type = '';
		}

		$day = isset( $_POST['day'] ) ? intval( $_POST['day'] ) : 0;
		$day = ( $day >= 1 && $day <= 7 ) ? $day : null;

		$cart = new Mixmymeals_Cart();
		$ok   = $cart->add( $recipe_id, $servings, $type, $day );

		if ( ! $ok ) {
			wp_send_json_error( [
				'code'    => 'cart_limit_reached',
				'message' => __( 'Your planner is full.', 'mixmymeals' ),
			], 409 );
		}


		wp_send_json_success( [
			'message' => __( 'Recipe added to meal plan.', 'mixmymeals' ),
		] );

	}

	/**
	 * Shortcode [mmm_recipe_actions].
	 * Renders action links (e.g. add-to-cart) for the current recipe.
	 *
	 * @return string
	 */
	public function mmm_recipe_actions_callback(): string {

		$recipe_id = get_the_ID();

		if ( ! $recipe_id || get_post_type( $recipe_id ) !== 'mmm_recipe' ) {
			return '';
		}

		$cart = new Mixmymeals_Cart();

		// HTML Wrapper für Actions
		$html = '<div class="mmm-recipe-actions">';

		// Add-To-Cart-Link
		/*
		$html .= sprintf(
			'<span class="mmm-recipe-actions__item mmm-recipe-actions__item--cart" data-recipe-id="%d">%s</span>',
			esc_attr( $recipe_id ),
			esc_html__( 'Add to Planner', 'mixmymeals' )
		);
		*/
		$recipe   = new Mixmymeals_Recipe( $recipe_id );
		$servings = $recipe->get_servings();
		$html     .= sprintf(
			'<span class="mmm-recipe-actions__item mmm-recipe-actions__item--cart" data-recipe-id="%d" data-servings="%d">%s</span>',
			esc_attr( $recipe_id ),
			esc_attr( $servings ),
			esc_html__( 'Add to Planner', 'mixmymeals' )
		);

		$html .= '</div>';

		return $html;
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
	/*
	function mmm_cart_badge_callback( $atts ) {

		$atts = shortcode_atts(
			array(
				'label_singular' => __( 'Recipe added to plan.', 'mixmymeals' ),
				'label_plural'   => __( 'Recipes added to plan.', 'mixmymeals' ),
				'class'          => 'mmm-cart-badge',
			),
			$atts
		);

		$cart  = new Mixmymeals_Cart();
		$count = count( $cart->get_items() );

		// Text abhängig von Singular/Plural
		$label = $count === 1 ? $atts['label_singular'] : $atts['label_plural'];

		// HTML ausgeben
		return sprintf(
			'<span class="%s">%d %s</span>',
			esc_attr( $atts['class'] ),
			$count,
			esc_html( $label )
		);
	}
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
	 * AJAX: Get cart count
	 */
	public function mmm_get_cart_count_ajax_callback(): void {

		check_ajax_referer( 'mmm_frontend_nonce', 'nonce' );

		$cart  = new Mixmymeals_Cart();
		$count = count( $cart->get_items() );

		wp_send_json_success(
			array(
				'count' => $count,
			)
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

		ob_start();
		?>

        <div class="mmm-plan-builder">

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
                                    data-action="remove">×
                            </button>

                        </li>

					<?php endforeach; ?>

                </ul>
            </div>

            <div class="mmm-plan-days">

				<?php foreach ( $days as $day_key => $day_label ) : ?>

                    <div class="mmm-plan-day"
                         data-day="<?php echo esc_attr( $day_key ); ?>">

                        <h3 class="mmm-plan-day-title">
							<?php echo esc_html( $day_label ); ?>
                        </h3>

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
                                            data-action="remove">
                                        ×
                                    </button>

                                </li>

							<?php endforeach; ?>

                        </ul>

                    </div>

				<?php endforeach; ?>

            </div>

        </div>

		<?php

		return ob_get_clean();
	}

	/**
	 * AJAX handler for wp_ajax_mmm_remove_cart_item.
	 * Removes a recipe from the cart by its index.
	 *
	 * @return void
	 */
	public function mmm_remove_cart_item_ajax_callback(): void {

		check_ajax_referer( 'mmm_frontend_nonce', 'nonce' );

		$index = isset( $_POST['index'] ) ? intval( $_POST['index'] ) : - 1;

		if ( $index < 0 ) {
			wp_send_json_error( [
				'code'    => 'invalid_index',
				'message' => __( 'Invalid index.', 'mixmymeals' ),
			], 400 );
		}

		$cart    = new Mixmymeals_Cart();
		$success = $cart->remove_by_index( $index );

		if ( $success ) {
			wp_send_json_success( [
				'code'    => 'removed',
				'message' => __( 'Recipe removed.', 'mixmymeals' ),
			] );
		} else {
			wp_send_json_error( [
				'code'    => 'remove_failed',
				'message' => __( 'Could not remove recipe.', 'mixmymeals' ),
			], 500 );
		}

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
	 * AJAX handler for wp_ajax_mmm_clear_cart.
	 * Clears all items from the cart.
	 *
	 * @return void
	 */
	public function mmm_clear_cart_ajax_callback(): void {
		check_ajax_referer( 'mmm_frontend_nonce', 'nonce' );

		$cart = new Mixmymeals_Cart();
		$cart->clear();

		wp_send_json_success( array( 'message' => __( 'Planner cleared.', 'mixmymeals' ) ) );
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

		check_ajax_referer( 'mmm_frontend_nonce', 'nonce' );

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

			$ip     = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
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
		$days      = array_filter( array_column( $items, 'day' ) );
		$start_day = $days ? min( $days ) : 1;
		$end_day   = $days ? max( $days ) : 1;

		$save_visitor_plans_as_draft
			         = (string) get_option( 'mixmymeals_save_meal_plans_published_by_visitors_as_draft',
				'' ) === 'on';
		$post_status = 'publish';

		if ( ! is_user_logged_in() && $save_visitor_plans_as_draft ) {
			$post_status = 'draft';
		}

		try {
			$meal_plan = Mixmymeals_Meal_Plan::create(
				__( 'Meal Plan', 'mixmymeals' ) . ' ' . date( 'd.m.Y' ),
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
			$meal->set_day( isset( $item['day'] ) ? (int) $item['day'] : null );
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
	 * AJAX handler for wp_ajax_mmm_update_cart_item.
	 * Updates the servings, type, or day of a cart item by its index.
	 *
	 * @return void
	 */
	public function mmm_update_cart_item_ajax_callback(): void {

		check_ajax_referer( 'mmm_frontend_nonce', 'nonce' );

		$index = isset( $_POST['index'] ) ? intval( $_POST['index'] ) : - 1;
		if ( $index < 0 ) {
			wp_send_json_error( [
				'code'    => 'invalid_index',
				'message' => __( 'Invalid index.', 'mixmymeals' ),
			], 400 );
		}

		$cart  = new Mixmymeals_Cart();
		$items = $cart->get_items();

		if ( ! isset( $items[ $index ] ) ) {
			wp_send_json_error( [
				'code'    => 'item_not_found',
				'message' => __( 'Item not existing.', 'mixmymeals' ),
			], 404 );
		}

		$item             = $items[ $index ];
		$item['servings'] = isset( $_POST['servings'] )
			? intval( $_POST['servings'] ) : $item['servings'] ?? 1;
		$item['type']     = isset( $_POST['type'] )
			? sanitize_text_field( $_POST['type'] ) : $item['type'] ?? '';
		$item['note']     = isset( $_POST['note'] )
			? sanitize_textarea_field( $_POST['note'] ) : $item['note'] ?? '';
		$item['day']      = isset( $_POST['day'] ) ? intval( $_POST['day'] )
			: $item['day'] ?? null;

		// Item zurück in den Warenkorb setzen
		$items[ $index ] = $item;
		$cart->save_items( $items );

		wp_send_json_success( [
			'code'    => 'updated',
			'message' => __( 'Item updated.', 'mixmymeals' ),
			'item'    => $item,
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

		if ( empty( $ingredients_raw ) ) {
			return '';
		}

		/*
		----------------------------------------------------------
		INGREDIENTS AGGREGATION
		----------------------------------------------------------
		*/

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
				];
			}

			// Aggregation (skalierte Werte direkt verwenden)
			$ingredients[ $id ]['amount']   += (float) ( $ing['amount'] ?? 0 );
			$ingredients[ $id ]['weight_g'] += (float) ( $ing['weight_g'] ?? 0 );
		}

		/*
		----------------------------------------------------------
		OUTPUT
		----------------------------------------------------------
		*/

		ob_start();
		?>
        <div class="mmm-meal-ingredients">
            <details class="mmm-meal-ingredients-toggle">
                <summary>
					<?php esc_html_e( 'Ingredients', 'mixmymeals' ); ?>
                    <span class="mmm-meal-ingredients-count">
					(<?php echo count( $ingredients ); ?>)
				</span>
                </summary>

                <ul>
					<?php foreach ( $ingredients as $ingredient ) : ?>
                        <li>
						<span class="mmm-ingredient-name">
							<?php echo esc_html( $ingredient['name'] ); ?>
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

		return ob_get_clean();
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

		// Bring! Deep Link vorbereiten
		$bring_link = sprintf(
			'https://api.getbring.com/rest/bringrecipes/deeplink?url=%s&source=web&baseQuantity=1&requestedQuantity=1',
			rawurlencode( $url )
		);

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
	 * Outputs the inline SVG icon sprite for the meal plan redesign —
	 * once per page, no matter how often it is requested.
	 *
	 * @return void
	 */
	private function render_icon_sprite(): void {

		static $rendered = false;

		if ( $rendered ) {
			return;
		}
		$rendered = true;
		?>
        <svg width="0" height="0" style="position:absolute" aria-hidden="true">
            <symbol id="mmm-i-calendar" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 2v4M16 2v4"/></symbol>
            <symbol id="mmm-i-book" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5a2 2 0 0 1 2-2h13v18H6a2 2 0 0 1-2-2z"/><path d="M8 7h8M8 11h8"/></symbol>
            <symbol id="mmm-i-users" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.2"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="M16 5.5a3 3 0 0 1 0 5.5M21 20a6 6 0 0 0-4-5.6"/></symbol>
            <symbol id="mmm-i-clock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></symbol>
            <symbol id="mmm-i-price" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0l-7.2-7.2A2 2 0 0 1 2.8 12V4.8A2 2 0 0 1 4.8 2.8H12a2 2 0 0 1 1.4.6l7.2 7.2a2 2 0 0 1 0 2.8Z"/><circle cx="7.5" cy="7.5" r="1.3"/></symbol>
            <symbol id="mmm-i-printer" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V3h12v6"/><rect x="6" y="13" width="12" height="8"/><path d="M6 17H3v-5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v5h-3"/></symbol>
            <symbol id="mmm-i-mail" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></symbol>
            <symbol id="mmm-i-send" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21 3-9 9"/><path d="M21 3 14 21l-3-7-7-3z"/></symbol>
            <symbol id="mmm-i-derive" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="6" r="2.5"/><circle cx="18" cy="18" r="2.5"/><path d="M6 8.5V14a4 4 0 0 0 4 4h5.5"/></symbol>
            <symbol id="mmm-i-flame" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3c2 3 5 5 5 9a5 5 0 0 1-10 0c0-1.6.7-2.8 1.5-3.6C9 9.5 10 8 9.5 6 11 6.5 12 4.5 12 3Z"/></symbol>
            <symbol id="mmm-i-plus" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></symbol>
        </svg>
		<?php
	}

	/**
	 * Builds the "<kcal> kcal ps" info string for one day-rail meal entry.
	 *
	 * @param array $entry Entry with 'meal' (Mixmymeals_Meal) and 'type_label'.
	 * @return string Empty string if no calories are available.
	 */
	private function get_meal_head_info( array $entry ): string {

		$recipe = new Mixmymeals_Recipe( (int) $entry['meal']->get_recipe_id() );

		if ( ! $recipe->is_valid() ) {
			return '';
		}

		$nutrition = $recipe->calculate_nutrition_per_serving();
		$kcal      = (int) round( $nutrition['calories'] ?? 0 );

		if ( $kcal <= 0 ) {
			return '';
		}

		return number_format_i18n( $kcal ) . ' ' . __( 'kcal ps', 'mixmymeals' );
	}

	/**
	 * Shortcode [mmm_meal_plan_hero].
	 * Renders breadcrumb, plan title, meta chips and action buttons for
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
		$bring_link   = sprintf(
			'https://api.getbring.com/rest/bringrecipes/deeplink?url=%s&source=web&baseQuantity=1&requestedQuantity=1',
			rawurlencode( $url )
		);

		$archive_url   = get_post_type_archive_link( 'mmm_meal_plan' );
		$post_type_obj = get_post_type_object( 'mmm_meal_plan' );
		$archive_label = $post_type_obj ? $post_type_obj->labels->name : '';

		ob_start();

		$this->render_icon_sprite();
		?>
        <nav class="mmm-breadcrumb">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'mixmymeals' ); ?></a>
            <span class="mmm-breadcrumb__sep">/</span>
			<?php if ( $archive_url && $archive_label ) : ?>
                <a href="<?php echo esc_url( $archive_url ); ?>"><?php echo esc_html( $archive_label ); ?></a>
                <span class="mmm-breadcrumb__sep">/</span>
			<?php endif; ?>
            <span><?php echo esc_html( $title ); ?></span>
        </nav>

        <section class="mmm-plan-hero">
            <div class="mmm-plan-hero__blob"></div>
            <h1><?php echo esc_html( $title ); ?></h1>

            <div class="mmm-plan-hero__meta">
				<?php if ( $num_of_days ) : ?>
                    <span class="mmm-chip mmm-chip--stat"><svg><use href="#mmm-i-calendar"/></svg><?php
						/* translators: %s = number of days */
						echo esc_html( str_replace( '%s', number_format_i18n( $num_of_days ), _n( '%s Day', '%s Days', $num_of_days, 'mixmymeals' ) ) );
						?></span>
				<?php endif; ?>
				<?php if ( $num_of_meals ) : ?>
                    <span class="mmm-chip mmm-chip--stat"><svg><use href="#mmm-i-book"/></svg><?php
						/* translators: %s = number of meals */
						echo esc_html( str_replace( '%s', number_format_i18n( $num_of_meals ), _n( '%s Recipe', '%s Recipes', $num_of_meals, 'mixmymeals' ) ) );
						?></span>
				<?php endif; ?>
				<?php if ( $serving_range_string ) : ?>
                    <span class="mmm-chip mmm-chip--stat"><svg><use href="#mmm-i-users"/></svg><?php echo esc_html( $serving_range_string ); ?></span>
				<?php endif; ?>
				<?php if ( $avg_cost > 0 ) : ?>
                    <span class="mmm-chip mmm-chip--stat mmm-chip--price"><svg><use href="#mmm-i-price"/></svg><?php
						printf(
							/* translators: %s: formatted average cost incl. currency */
							esc_html__( 'Ø %s / serving', 'mixmymeals' ),
							esc_html( number_format_i18n( $avg_cost, 2 ) . ' ' . $currency )
						);
						?></span>
				<?php endif; ?>
            </div>

            <div class="mmm-plan-hero__actions">
                <button type="button"
                        class="mmm-btn mmm-btn--primary mmm-meal-plan-actions__item--print"
                        data-meal-plan-id="<?php echo esc_attr( $plan_id ); ?>">
                    <svg><use href="#mmm-i-printer"/></svg><?php esc_html_e( 'Print to PDF', 'mixmymeals' ); ?>
                </button>
                <a href="<?php echo esc_url( $mailto_link ); ?>"
                   class="mmm-btn mmm-btn--ghost">
                    <svg><use href="#mmm-i-mail"/></svg><?php esc_html_e( 'Send via Email', 'mixmymeals' ); ?>
                </a>
                <a href="<?php echo esc_url( $bring_link ); ?>" target="_blank" rel="noopener noreferrer"
                   class="mmm-btn mmm-btn--ghost">
                    <svg><use href="#mmm-i-send"/></svg><?php esc_html_e( 'Export to Bring!', 'mixmymeals' ); ?>
                </a>
                <button type="button"
                        class="mmm-btn mmm-btn--ghost mmm-meal-plan-actions__item--load-into-cart"
                        data-meal-plan-id="<?php echo esc_attr( $plan_id ); ?>"
                        data-redirect-url="<?php echo esc_attr( $atts['cart_url'] ); ?>">
                    <svg><use href="#mmm-i-derive"/></svg><?php esc_html_e( 'Adjust', 'mixmymeals' ); ?>
                </button>
            </div>
        </section>
		<?php

		return ob_get_clean();
	}

	/**
	 * Shortcode [mmm_recipe_hero].
	 * Renders breadcrumb, title, meta chips, excerpt and the
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

		$archive_url   = get_post_type_archive_link( 'mmm_recipe' );
		$post_type_obj = get_post_type_object( 'mmm_recipe' );
		$archive_label = $post_type_obj ? $post_type_obj->labels->name : '';

		ob_start();

		$this->render_icon_sprite();
		?>
        <nav class="mmm-breadcrumb">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'mixmymeals' ); ?></a>
            <span class="mmm-breadcrumb__sep">/</span>
			<?php if ( $archive_url && $archive_label ) : ?>
                <a href="<?php echo esc_url( $archive_url ); ?>"><?php echo esc_html( $archive_label ); ?></a>
                <span class="mmm-breadcrumb__sep">/</span>
			<?php endif; ?>
            <span><?php echo esc_html( $title ); ?></span>
        </nav>

        <section class="mmm-plan-hero">
            <div class="mmm-plan-hero__blob"></div>
            <h1><?php echo esc_html( $title ); ?></h1>

            <div class="mmm-plan-hero__meta">
				<?php if ( $servings ) : ?>
                    <span class="mmm-chip mmm-chip--stat"><svg><use href="#mmm-i-users"/></svg><?php
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
                    <span class="mmm-chip mmm-chip--stat"><svg><use href="#mmm-i-clock"/></svg><?php echo esc_html( $prep_time_string ); ?></span>
				<?php endif; ?>
				<?php if ( $kcal > 0 ) : ?>
                    <span class="mmm-chip mmm-chip--stat"><svg><use href="#mmm-i-flame"/></svg><?php echo esc_html( number_format_i18n( $kcal ) . ' ' . __( 'kcal ps', 'mixmymeals' ) ); ?></span>
				<?php endif; ?>
				<?php if ( $cost > 0 ) : ?>
                    <span class="mmm-chip mmm-chip--stat mmm-chip--price"><svg><use href="#mmm-i-price"/></svg><?php
						printf(
							/* translators: %s: formatted average cost incl. currency */
							esc_html__( 'Ø %s / serving', 'mixmymeals' ),
							esc_html( number_format_i18n( $cost, 2 ) . ' ' . $currency )
						);
						?></span>
				<?php endif; ?>
            </div>

			<?php if ( has_excerpt( $recipe_id ) ) : ?>
                <p class="mmm-plan-hero__lead"><?php echo esc_html( get_the_excerpt( $recipe_id ) ); ?></p>
			<?php endif; ?>

            <div class="mmm-plan-hero__actions">
                <button type="button"
                        class="mmm-btn mmm-btn--primary mmm-recipe-actions__item--cart"
                        data-recipe-id="<?php echo esc_attr( $recipe_id ); ?>"
                        data-servings="<?php echo esc_attr( max( 1, $servings ) ); ?>">
                    <svg><use href="#mmm-i-plus"/></svg><?php esc_html_e( 'Add to Planner', 'mixmymeals' ); ?>
                </button>
            </div>
        </section>
		<?php

		return ob_get_clean();
	}

	/**
	 * Shortcode [mmm_meal_hero].
	 * Renders breadcrumb, title, meta chips and actions (cooking mode,
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

		$this->render_icon_sprite();
		?>
        <nav class="mmm-breadcrumb">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'mixmymeals' ); ?></a>
            <span class="mmm-breadcrumb__sep">/</span>
			<?php if ( $plan_url && $plan_title ) : ?>
                <a href="<?php echo esc_url( $plan_url ); ?>"><?php echo esc_html( $plan_title ); ?></a>
                <span class="mmm-breadcrumb__sep">/</span>
			<?php endif; ?>
            <span><?php echo esc_html( $title ); ?></span>
        </nav>

        <section class="mmm-plan-hero">
            <div class="mmm-plan-hero__blob"></div>
            <h1><?php echo esc_html( $title ); ?></h1>

            <div class="mmm-plan-hero__meta">
				<?php if ( $day_label ) : ?>
                    <span class="mmm-chip mmm-chip--stat"><svg><use href="#mmm-i-calendar"/></svg><?php echo esc_html( $day_label ); ?></span>
				<?php endif; ?>
				<?php if ( $type_label ) : ?>
                    <span class="mmm-chip mmm-chip--stat"><svg><use href="#mmm-i-book"/></svg><?php echo esc_html( $type_label ); ?></span>
				<?php endif; ?>
				<?php if ( $servings ) : ?>
                    <span class="mmm-chip mmm-chip--stat"><svg><use href="#mmm-i-users"/></svg><?php
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
                    <span class="mmm-chip mmm-chip--stat"><svg><use href="#mmm-i-flame"/></svg><?php echo esc_html( number_format_i18n( $kcal ) . ' ' . __( 'kcal ps', 'mixmymeals' ) ); ?></span>
				<?php endif; ?>
            </div>

            <div class="mmm-plan-hero__actions">
				<?php if ( $has_steps ) : ?>
                    <button type="button" class="mmm-btn mmm-btn--primary mmm-cook-mode-start">
                        <svg><use href="#mmm-i-flame"/></svg><?php esc_html_e( 'Start cooking mode', 'mixmymeals' ); ?>
                    </button>
				<?php endif; ?>
				<?php if ( $plan_url && $plan_title ) : ?>
                    <a class="mmm-btn mmm-btn--ghost" href="<?php echo esc_url( $plan_url ); ?>">
                        <svg><use href="#mmm-i-calendar"/></svg><?php echo esc_html( __( 'Back to', 'mixmymeals' ) . ' ' . $plan_title ); ?>
                    </a>
				<?php endif; ?>
            </div>
        </section>
		<?php

		return ob_get_clean();
	}

	/**
	 * AJAX handler for wp_ajax_mmm_load_plan_into_cart.
	 * Clears the current cart and loads all meals from the specified meal plan.
	 *
	 * @return void
	 */
	public function mmm_load_plan_into_cart_ajax_callback(): void {

		check_ajax_referer( 'mmm_frontend_nonce', 'nonce' );

		$meal_plan_id = intval( $_POST['meal_plan_id'] ?? 0 );

		$post = get_post( $meal_plan_id );
		if ( ! $post || $post->post_type !== 'mmm_meal_plan' || $post->post_status !== 'publish' ) {
			wp_send_json_error( array( 'message' => __( 'Meal Plan not found.', 'mixmymeals' ) ) );
		}

		$plan  = new Mixmymeals_Meal_Plan( $meal_plan_id );
		$meals = $plan->get_meals();

		$cart = new Mixmymeals_Cart();
		$cart->clear();

		foreach ( $meals as $meal_post ) {
			$meal = new Mixmymeals_Meal( $meal_post->ID );
			$cart->add(
				$meal->get_recipe_id(),
				$meal->get_servings(),
				$meal->get_type(),
				$meal->get_day()
			);
		}

		wp_send_json_success( array(
			'message' => __( 'Meal plan loaded into the planner.', 'mixmymeals' ),
		) );
	}

	/**
	 * AJAX handler for wp_ajax_mmm_generate_pdf.
	 * Generates and streams a PDF for the specified meal plan.
	 * Rate-limited to 5 requests per IP per minute.
	 *
	 * @return void
	 */
	public function mmm_generate_pdf_ajax_callback(): void {

		if ( empty( $_GET['meal_plan_id'] ) ) {
			wp_die( esc_html__( 'Meal Plan ID missing.', 'mixmymeals' ) );
		}

		$meal_plan_id = intval( $_GET['meal_plan_id'] );

		$post = get_post( $meal_plan_id );
		if ( ! $post || $post->post_type !== 'mmm_meal_plan' || $post->post_status !== 'publish' ) {
			wp_die( esc_html__( 'Meal Plan not found.', 'mixmymeals' ), '', array( 'response' => 404 ) );
		}

		$ip       = md5( $_SERVER['REMOTE_ADDR'] ?? '' );
		$rate_key = 'mmm_pdf_rate_' . $ip;
		$count    = (int) get_transient( $rate_key );
		if ( $count >= 5 ) {
			wp_die( esc_html__( 'Too many requests. Please wait a minute.', 'mixmymeals' ), '', array( 'response' => 429 ) );
		}
		set_transient( $rate_key, $count + 1, 60 );

		$meals_html
			= $this->render_meal_plan_overview_html_for_pdf( $meal_plan_id );
		$shopping_list_html
			= $this->render_shopping_list_html_for_pdf( $meal_plan_id );
		$recipes_html
			= $this->render_recipes_contained_in_a_meal_plan_html_for_pdf( $meal_plan_id,
			true, true );


		$pdf_builder = new Mixmymeals_PDF_Builder( [
			'logo_url'    => '',
			'header_html' => '<h1>' . esc_html( get_the_title( $meal_plan_id ) )
			                 . '</h1>',
			'footer_html' => esc_html( __( 'Planned on', 'mixmymeals' ) . ' '
			                           . get_bloginfo( 'name' ) . ' '
			                           . __( 'using MixMyMeals',
					'mixmymeals' ) ),
		] );

		$pdf_builder->add_page( $meals_html );
		$pdf_builder->add_page( $shopping_list_html );
		foreach ( $recipes_html as $recipe_html ) {
			$pdf_builder->add_page( $recipe_html );
		}

		$pdf_builder->render( 'meal-plan.pdf', false );
	}


	/**
	 * Renders the meals overview section of a meal plan PDF.
	 *
	 * @param int $meal_plan_id Meal plan post ID.
	 * @return string
	 */
	private function render_meal_plan_overview_html_for_pdf( int $meal_plan_id
	): string {

		$meal_plan = new Mixmymeals_Meal_Plan( $meal_plan_id );
		$days      = $meal_plan->get_day_range();
		$meals     = $meal_plan->get_meals();

		if ( empty( $meals ) ) {
			return '<p>' . esc_html__( 'No meals found in this plan.',
					'mixmymeals' ) . '</p>';
		}

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

		$weekdays = [
			1 => _x( 'Monday', 'weekday', 'mixmymeals' ),
			2 => _x( 'Tuesday', 'weekday', 'mixmymeals' ),
			3 => _x( 'Wednesday', 'weekday', 'mixmymeals' ),
			4 => _x( 'Thursday', 'weekday', 'mixmymeals' ),
			5 => _x( 'Friday', 'weekday', 'mixmymeals' ),
			6 => _x( 'Saturday', 'weekday', 'mixmymeals' ),
			7 => _x( 'Sunday', 'weekday', 'mixmymeals' ),
		];

		$meal_types = Mixmymeals_Meal::get_plaintext_meal_type_map();

		ob_start();
		?>
        <h2><?php esc_html_e( 'Meals', 'mixmymeals' ); ?></h2>

		<?php foreach ( $days as $day_number ) : ?>

			<?php if ( empty( $grouped_meals[ $day_number ] ) ) {
				continue;
			} ?>

            <h3>
				<?php echo esc_html( $weekdays[ $day_number ] ??
				                     sprintf( __( 'Day %d', 'mixmymeals' ),
					                     $day_number ) ); ?>
            </h3>

            <table class="mmm-meal-plan-overview-table" width="100%">
                <colgroup>
                    <col style="width:20%">
                    <col style="width:70%">
                    <col style="width:10%">
                </colgroup>
                <thead>
                <tr>
                    <th class="align-left"><?php esc_html_e( 'Type',
							'mixmymeals' ); ?></th>
                    <th class="align-left"><?php esc_html_e( 'Meal',
							'mixmymeals' ); ?></th>
                    <th class="align-right"><?php esc_html_e( 'Servings',
							'mixmymeals' ); ?></th>
                </tr>
                </thead>
                <tbody>

				<?php foreach ( $meal_types as $type_code => $type_name ) : ?>

					<?php if ( empty( $grouped_meals[ $day_number ][ $type_code ] ) ) {
						continue;
					} ?>

					<?php
					$cell_meals   = $grouped_meals[ $day_number ][ $type_code ];
					$titles       = [];
					$servings     = [];
					$calories_sum = 0;
					$weight_sum   = 0;

					foreach ( $cell_meals as $meal ) {

						$recipe
							= new Mixmymeals_Recipe( $meal->get_recipe_id() );

						$titles[]
							        = esc_html( get_the_title( $meal->get_id() ) );
						$servings[] = esc_html( $meal->get_servings() );

							$recipe_servings = max( 1.0,
							floatval( $recipe->get_servings() ) );
						$meal_servings   = max( 1.0,
							floatval( $meal->get_servings() ) );
						$factor          = $meal_servings / $recipe_servings;

						$ingredients
							= $recipe->get_ingredients_scaled( $factor );

						$calories = 0;
						$weight   = 0;

						foreach ( $ingredients as $ing ) {

							if ( empty( $ing['id'] )
							     || empty( $ing['weight_g'] ) ) {
								continue;
							}

							$ingredient_obj
								= new Mixmymeals_Ingredient( $ing['id'] );

							if ( ! $ingredient_obj->is_valid() ) {
								continue;
							}

							$nutrition = $ingredient_obj->get_nutrition();
							$w         = floatval( $ing['weight_g'] );

							if ( $w <= 0 ) {
								continue;
							}

							$weight   += $w;
							$calories += $nutrition['calories_100'] * $w / 100;
						}

							if ( $meal_servings > 0 ) {
							$calories /= $meal_servings;
							$weight   /= $meal_servings;
						}

						$calories_sum += $calories;
						$weight_sum   += $weight;
					}
					?>

                    <tr>
                        <td class="align-left"><?php echo esc_html( $type_name ); ?></td>

                        <td class="align-left">
							<?php
							echo implode( '<br>', $titles );
							echo '<br><small>';
							echo esc_html__( 'Per serving', 'mixmymeals' )
							     . ': '
							     . absint( $weight_sum ) . ' g, '
							     . absint( $calories_sum ) . ' '
							     . esc_html__( 'kcal', 'mixmymeals' );
							echo '</small>';
							?>
                        </td>

                        <td class="align-right">
							<?php echo implode( '<br>', $servings ); ?>
                        </td>
                    </tr>

				<?php endforeach; ?>

                </tbody>
            </table>

		<?php endforeach;

		return ob_get_clean();
	}


	/**
	 * Renders the shopping list section of a meal plan PDF.
	 *
	 * @param int  $meal_plan_id    Meal plan post ID.
	 * @param bool $sort_by_category Whether to group ingredients by category.
	 * @return string
	 */
	private function render_shopping_list_html_for_pdf(
		int $meal_plan_id, bool $sort_by_category = true
	): string {

		$meal_plan = new Mixmymeals_Meal_Plan( $meal_plan_id );
		$ingredients_by_cat
		           = $meal_plan->calc_shopping_list( $sort_by_category );

		if ( empty( $ingredients_by_cat ) ) {
			return '<p>' . esc_html__( 'No ingredients found.', 'mixmymeals' )
			       . '</p>';
		}

		ob_start();
		?>
        <h2><?php esc_html_e( 'Shopping List', 'mixmymeals' ); ?></h2>

		<?php foreach ( $ingredients_by_cat as $cat_name => $ings ) : ?>
            <h3><?php echo esc_html( $cat_name ); ?></h3>
            <table class="mmm-meal-plan-shopping-list-table" border="1"
                   cellspacing="0" cellpadding="4" width="100%">
                <thead>
                <tr>
                    <th>✔</th>
                    <th><?php esc_html_e( 'Ingredient', 'mixmymeals' ); ?></th>
                    <th><?php esc_html_e( 'Amount', 'mixmymeals' ); ?></th>
                </tr>
                </thead>
                <tbody>
				<?php foreach ( $ings as $ing_id => $data ) :

					$total_weight = $data['total_weight'];

					// Bedarfsanzeige analog zur Frontend-Liste.
					$main_line = get_the_title( $ing_id ) . ', '
					             . $this->build_needed_amount_string(
						             $data['units'] ?? [],
						             $total_weight
					             );
					?>
                    <tr>
                        <td style="text-align:center;">☐</td>
                        <td><?php echo esc_html( $main_line ); ?></td>
                        <td><?php echo esc_html( number_format_i18n( $total_weight,
								2 ) ); ?> g
                        </td>
                    </tr>
				<?php endforeach; ?>
                </tbody>
            </table>
		<?php endforeach;

		return ob_get_clean();
	}

	/**
	 * Renders one PDF page per recipe contained in the meal plan, with scaled ingredients.
	 *
	 * @param int  $meal_plan_id          Meal plan post ID.
	 * @param bool $show_ingredients_top  Whether to include an ingredient summary at the top.
	 * @param bool $show_step_ingredients Whether to list ingredients per step.
	 * @return string[]
	 */
	private function render_recipes_contained_in_a_meal_plan_html_for_pdf(
		int $meal_plan_id,
		bool $show_ingredients_top = true,
		bool $show_step_ingredients = false
	): array {

		$meal_plan = new Mixmymeals_Meal_Plan( $meal_plan_id );
		$meals     = $meal_plan->get_meals();

		if ( empty( $meals ) ) {
			return [];
		}

		$pages = [];

		foreach ( $meals as $meal_post ) {

			$meal       = new Mixmymeals_Meal( $meal_post->ID );
			$recipe_id  = $meal->get_recipe_id();

			if ( ! $recipe_id ) {
				continue;
			}

			$recipe = new Mixmymeals_Recipe( $recipe_id );
			$steps  = $recipe->get_steps();

			if ( empty( $steps ) ) {
				continue;
			}

			$recipe_servings = max( 1.0, (float) $recipe->get_servings() );
			$meal_servings   = max( 1.0, (float) $meal->get_servings() );
			$factor          = $meal_servings / $recipe_servings;

				$ingredients = $recipe->get_ingredients_scaled( $factor );

				$ingredients_map = [];
			foreach ( $ingredients as $ing ) {
				$id = (int) ( $ing['id'] ?? 0 );
				if ( $id > 0 ) {
					$ingredients_map[ $id ] = $ing;
				}
			}

			ob_start();
			?>
            <div class="mmm-pdf-page">

                <h2><?php echo esc_html( get_the_title( $recipe_id ) ); ?></h2>

                <div class="mmm-recipe-meta">
                    <p>
                        <strong><?php esc_html_e( 'Servings', 'mixmymeals' ); ?>:</strong>
						<?php echo esc_html( number_format_i18n( $meal_servings ) ); ?>
                    </p>
                </div>

				<?php if ( $show_ingredients_top && ! empty( $ingredients ) ) : ?>
                    <div class="mmm-meal-ingredients-overview">
                        <h3><?php esc_html_e( 'Ingredients Overview', 'mixmymeals' ); ?></h3>
                        <ul>
							<?php foreach ( $ingredients as $ingredient ) :

								$amount    = (float) ( $ingredient['amount'] ?? 0 );
								$unit      = $ingredient['unit'] ?: 'g';
								$weight_g  = round( (float) ( $ingredient['weight_g'] ?? 0 ) );

								$display_amount = $amount > 0
									? rtrim( rtrim( number_format_i18n( $amount, 1 ), '0' ), ',' )
									: '';
								?>
                                <li>
									<?php echo esc_html( $ingredient['name'] ); ?>
									<?php if ( $display_amount ) : ?>
                                        – <?php echo esc_html( $display_amount . ' ' . $unit ); ?>
                                        (<?php echo esc_html( $weight_g ); ?> g)
									<?php endif; ?>
                                </li>
							<?php endforeach; ?>
                        </ul>
                    </div>
				<?php endif; ?>

                <div class="mmm-recipe-steps scaled">

					<?php foreach ( $steps as $index => $step ) : ?>
                        <div class="mmm-step">

							<?php if ( ! empty( $step['title'] ) ) : ?>
                                <h3>
									<?php echo esc_html(
										sprintf( __( 'Step %d', 'mixmymeals' ), $index + 1 )
										. ': ' . $step['title']
									); ?>
                                </h3>
							<?php endif; ?>

							<?php if ( ! empty( $step['description'] ) ) : ?>
                                <p><?php echo esc_html( $step['description'] ); ?></p>
							<?php endif; ?>

							<?php if ( $show_step_ingredients && ! empty( $step['ingredients'] ) ) : ?>
                                <ul>
									<?php foreach ( $step['ingredients'] as $ing ) :

										$id = (int) ( $ing['id'] ?? 0 );
										if ( $id <= 0 ) {
											continue;
										}

										$scaled = $ingredients_map[ $id ] ?? null;

										if ( ! $scaled ) {
											continue;
										}

										$amount   = (float) ( $scaled['amount'] ?? 0 );
										$unit     = $scaled['unit'] ?: 'g';
										$weight_g = round( (float) ( $scaled['weight_g'] ?? 0 ) );

										$display_amount = $amount > 0
											? rtrim( rtrim( number_format_i18n( $amount, 2 ), '0' ), ',' )
											: '';
										?>
                                        <li>
											<?php echo esc_html( get_the_title( $id ) ); ?>
											<?php if ( $display_amount ) : ?>
                                                – <?php echo esc_html( $display_amount . ' ' . $unit ); ?>
                                                (<?php echo esc_html( $weight_g ); ?> g)
											<?php endif; ?>
                                        </li>
									<?php endforeach; ?>
                                </ul>
							<?php endif; ?>

                        </div>
					<?php endforeach; ?>

                </div>

            </div>
			<?php

			$pages[] = ob_get_clean();
		}

		return $pages;
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

		$keys   = [ 'calories', 'fat', 'carbs', 'protein', 'sugar', 'fiber', 'salt' ];
		$totals = array_fill_keys( $keys, 0.0 );

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

			foreach ( $keys as $key ) {
				$totals[ $key ] += ( $nutrition_per_serving[ $key ] ?? 0.0 ) * $meal_servings;
			}
		}

		$num_days  = max( 1, $plan->count_days() );
		$num_meals = max( 1, count( $meals ) );

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
			'calories' => [ __( 'Ø Calories / day', 'mixmymeals' ), 'kcal', 0 ],
			'protein'  => [ __( 'Ø Protein / day', 'mixmymeals' ), 'g', 1 ],
			'carbs'    => [ __( 'Ø Carbohydrates / day', 'mixmymeals' ), 'g', 1 ],
			'fat'      => [ __( 'Ø Fat / day', 'mixmymeals' ), 'g', 1 ],
		];

		ob_start();
		?>
        <div class="mmm-meal-plan-nutrition">

            <div class="mmm-nutri-stats">
				<?php foreach ( $stat_cards as $stat_key => [ $stat_label, $stat_unit, $stat_decimals ] ) : ?>
                    <div class="mmm-nutri-stat">
                        <div class="mmm-nutri-stat__k"><?php echo esc_html( $stat_label ); ?></div>
                        <div class="mmm-nutri-stat__v"><?php echo esc_html( number_format_i18n( $totals[ $stat_key ] / $num_days, $stat_decimals ) ); ?></div>
                        <div class="mmm-nutri-stat__u"><?php echo esc_html( $stat_unit ); ?></div>
                    </div>
				<?php endforeach; ?>
            </div>

            <table class="mmm-nutrition-table mmm-nutri-table">
                <thead>
                <tr>
                    <th><?php esc_html_e( 'Nutrition', 'mixmymeals' ); ?></th>
                    <th><?php esc_html_e( 'Total', 'mixmymeals' ); ?></th>
                    <th><?php esc_html_e( 'Avg. per day', 'mixmymeals' ); ?></th>
                    <th><?php esc_html_e( 'Avg. per meal', 'mixmymeals' ); ?></th>
                </tr>
                </thead>
                <tbody>
				<?php foreach ( $labels as $key => [ $label, $unit ] ) :
					$total    = $totals[ $key ];
					$per_day  = $total / $num_days;
					$per_meal = $total / $num_meals;
					$decimals = ( $key === 'calories' ) ? 0 : 1;
					?>
                    <tr>
                        <td><?php echo esc_html( $label ); ?></td>
                        <td><?php echo esc_html( number_format_i18n( $total, $decimals ) ); ?> <?php echo esc_html( $unit ); ?></td>
                        <td><?php echo esc_html( number_format_i18n( $per_day, $decimals ) ); ?> <?php echo esc_html( $unit ); ?></td>
                        <td><?php echo esc_html( number_format_i18n( $per_meal, $decimals ) ); ?> <?php echo esc_html( $unit ); ?></td>
                    </tr>
				<?php endforeach; ?>
                </tbody>
            </table>
        </div>
		<?php

		return ob_get_clean();
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
			$totals['calories'] += $nutrition['calories_100'] * $weight / 100;
			$totals['protein']  += $nutrition['protein'] * $weight / 100;
			$totals['carbs']    += $nutrition['carbs'] * $weight / 100;
			$totals['fat']      += $nutrition['fat'] * $weight / 100;
			$totals['sugar']    += $nutrition['sugar'] * $weight / 100;
			$totals['fiber']    += $nutrition['fiber'] * $weight / 100;
			$totals['salt']     += $nutrition['salt'] * $weight / 100;
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

