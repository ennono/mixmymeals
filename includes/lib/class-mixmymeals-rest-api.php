<?php
/**
 * REST API: meta registration and custom endpoints.
 *
 * @package MixMyMeals/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mixmymeals_Rest_Api {

	public function __construct() {
		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	// =========================================================
	// META REGISTRATION
	// =========================================================

	public function register_meta(): void {

		$can_edit = static fn() => current_user_can( 'edit_posts' );
		$to_float = static fn( $value ) => floatval( $value );

		// mmm_recipe
		register_post_meta( 'mmm_recipe', '_mmm_servings', array( 'type' => 'number', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => $to_float, 'auth_callback' => $can_edit ) );
		register_post_meta( 'mmm_recipe', '_mmm_preparation_time', array( 'type' => 'integer', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'absint', 'auth_callback' => $can_edit ) );
		register_post_meta( 'mmm_recipe', '_mmm_priority', array( 'type' => 'integer', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'absint', 'auth_callback' => $can_edit ) );
		register_post_meta( 'mmm_recipe', '_mmm_equipment', array( 'type' => 'array', 'single' => true, 'show_in_rest' => array( 'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ) ), 'auth_callback' => $can_edit ) );
		register_post_meta( 'mmm_recipe', '_rmm_steps', array(
			'type'         => 'array',
			'single'       => true,
			'show_in_rest' => array( 'schema' => array( 'type' => 'array', 'items' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'title'       => array( 'type' => 'string' ),
					'description' => array( 'type' => 'string' ),
					'image'       => array( 'type' => 'integer' ),
					'ingredients' => array( 'type' => 'array', 'items' => array(
						'type'                 => 'object',
						'additionalProperties' => true,
						'properties'           => array(
							'id'        => array( 'type' => 'integer' ),
							'amount'    => array( 'type' => 'number' ),
							'amount_id' => array( 'type' => 'integer' ),
						),
					) ),
				),
			) ) ),
			'auth_callback' => $can_edit,
		) );

		// mmm_ingredient
		foreach ( array( '_rmm_calories_100', '_rmm_fat', '_rmm_carbs', '_rmm_protein', '_rmm_sugar', '_rmm_fiber', '_rmm_salt', '_rmm_weight_item' ) as $field ) {
			register_post_meta( 'mmm_ingredient', $field, array( 'type' => 'number', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => $to_float, 'auth_callback' => $can_edit ) );
		}
		register_post_meta( 'mmm_ingredient', '_mmm_price_per_100g', array( 'type' => 'number', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => $to_float, 'auth_callback' => $can_edit ) );
		register_post_meta( 'mmm_ingredient', '_mmm_diet_type',      array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'sanitize_text_field', 'auth_callback' => $can_edit ) );
		register_post_meta( 'mmm_ingredient', '_mmm_default_unit',   array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'sanitize_text_field', 'auth_callback' => $can_edit ) );

		// mmm_ingr_unit
		register_post_meta( 'mmm_ingr_unit', '_mmm_ingr_unit_weight', array( 'type' => 'number',  'single' => true, 'show_in_rest' => true, 'sanitize_callback' => $to_float, 'auth_callback' => $can_edit ) );
		register_post_meta( 'mmm_ingr_unit', '_mmm_is_indivisible',   array( 'type' => 'boolean', 'single' => true, 'show_in_rest' => true, 'auth_callback' => $can_edit ) );

		// mmm_meal_plan
		register_post_meta( 'mmm_meal_plan', 'start_day', array( 'type' => 'integer', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'absint', 'auth_callback' => $can_edit ) );
		register_post_meta( 'mmm_meal_plan', 'end_day',   array( 'type' => 'integer', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'absint', 'auth_callback' => $can_edit ) );

		// mmm_meal
		register_post_meta( 'mmm_meal', 'recipe_id',     array( 'type' => 'integer', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'absint', 'auth_callback' => $can_edit ) );
		register_post_meta( 'mmm_meal', 'servings',      array( 'type' => 'integer', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'absint', 'auth_callback' => $can_edit ) );
		register_post_meta( 'mmm_meal', 'mmm_meal_type', array( 'type' => 'string',  'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'sanitize_text_field', 'auth_callback' => $can_edit ) );
		register_post_meta( 'mmm_meal', '_mmm_day',      array( 'type' => 'integer', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'absint', 'auth_callback' => $can_edit ) );
	}

	// =========================================================
	// ROUTE REGISTRATION
	// =========================================================

	public function register_routes(): void {

		$auth   = array( $this, 'check_edit_permission' );
		$id_arg = array( 'validate_callback' => static fn( $v ) => is_numeric( $v ), 'sanitize_callback' => 'absint' );

		// ── Recipes ───────────────────────────────────────────────────────
		register_rest_route( 'mixmymeals/v1', '/recipes', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_recipes' ), 'permission_callback' => '__return_true', 'args' => array( 'status' => array( 'required' => false, 'type' => 'string', 'enum' => array( 'publish', 'draft', 'any' ), 'default' => 'publish' ) ) ) );
		register_rest_route( 'mixmymeals/v1', '/recipes', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'create_recipe' ), 'permission_callback' => $auth, 'args' => array( 'title' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ), 'status' => array( 'required' => false, 'type' => 'string', 'default' => 'draft', 'enum' => array( 'publish', 'draft', 'pending', 'private' ) ), 'excerpt' => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field' ), 'servings' => array( 'required' => false, 'type' => 'number' ), 'preparation_time' => array( 'required' => false, 'type' => 'integer' ), 'steps' => array( 'required' => false, 'type' => 'array' ), 'equipment' => array( 'required' => false, 'type' => 'array' ), 'categories' => array( 'required' => false, 'type' => 'array', 'items' => array( 'type' => 'integer' ) ), 'tags' => array( 'required' => false, 'type' => 'array', 'items' => array( 'type' => 'integer' ) ) ) ) );
		register_rest_route( 'mixmymeals/v1', '/recipes/(?P<id>\d+)', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_recipe' ), 'permission_callback' => '__return_true', 'args' => array( 'id' => $id_arg ) ) );
		register_rest_route( 'mixmymeals/v1', '/recipes/(?P<id>\d+)', array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'update_recipe' ), 'permission_callback' => $auth, 'args' => array( 'id' => $id_arg, 'title' => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ), 'status' => array( 'required' => false, 'type' => 'string', 'enum' => array( 'publish', 'draft', 'pending', 'private' ) ), 'excerpt' => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field' ), 'servings' => array( 'required' => false, 'type' => 'number' ), 'preparation_time' => array( 'required' => false, 'type' => 'integer' ), 'steps' => array( 'required' => false, 'type' => 'array' ), 'equipment' => array( 'required' => false, 'type' => 'array' ), 'categories' => array( 'required' => false, 'type' => 'array', 'items' => array( 'type' => 'integer' ) ), 'tags' => array( 'required' => false, 'type' => 'array', 'items' => array( 'type' => 'integer' ) ) ) ) );
		register_rest_route( 'mixmymeals/v1', '/recipes/(?P<id>\d+)/nutrition',   array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_recipe_nutrition' ),   'permission_callback' => '__return_true', 'args' => array( 'id' => $id_arg ) ) );
		register_rest_route( 'mixmymeals/v1', '/recipes/(?P<id>\d+)/ingredients', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_recipe_ingredients' ), 'permission_callback' => '__return_true', 'args' => array( 'id' => $id_arg, 'scale' => array( 'required' => false, 'type' => 'number', 'default' => 1.0 ) ) ) );

		// ── Ingredients ───────────────────────────────────────────────────
		register_rest_route( 'mixmymeals/v1', '/ingredients', array( 'methods' => WP_REST_Server::READABLE,  'callback' => array( $this, 'get_ingredients' ),    'permission_callback' => '__return_true' ) );
		register_rest_route( 'mixmymeals/v1', '/ingredients', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'create_ingredient' ), 'permission_callback' => $auth, 'args' => array( 'title' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ), 'status' => array( 'required' => false, 'type' => 'string', 'default' => 'publish', 'enum' => array( 'publish', 'draft' ) ), 'diet_type' => array( 'required' => false, 'type' => 'string', 'enum' => array( 'vegan', 'vegetarian', '' ) ), 'default_unit' => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ), 'categories' => array( 'required' => false, 'type' => 'array', 'items' => array( 'type' => 'integer' ) ), 'nutrition' => array( 'required' => false, 'type' => 'object' ) ) ) );
		register_rest_route( 'mixmymeals/v1', '/ingredients/(?P<id>\d+)', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_ingredient' ),    'permission_callback' => '__return_true', 'args' => array( 'id' => $id_arg ) ) );
		register_rest_route( 'mixmymeals/v1', '/ingredients/(?P<id>\d+)', array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'update_ingredient' ), 'permission_callback' => $auth, 'args' => array( 'id' => $id_arg, 'title' => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ), 'diet_type' => array( 'required' => false, 'type' => 'string', 'enum' => array( 'vegan', 'vegetarian', '' ) ), 'default_unit' => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ), 'categories' => array( 'required' => false, 'type' => 'array', 'items' => array( 'type' => 'integer' ) ), 'nutrition' => array( 'required' => false, 'type' => 'object' ) ) ) );
		register_rest_route( 'mixmymeals/v1', '/ingredients/(?P<id>\d+)/nutrition', array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'update_ingredient_nutrition' ), 'permission_callback' => $auth, 'args' => array( 'id' => $id_arg, 'calories_100' => array( 'required' => false, 'type' => 'number' ), 'fat' => array( 'required' => false, 'type' => 'number' ), 'carbs' => array( 'required' => false, 'type' => 'number' ), 'protein' => array( 'required' => false, 'type' => 'number' ), 'sugar' => array( 'required' => false, 'type' => 'number' ), 'fiber' => array( 'required' => false, 'type' => 'number' ), 'salt' => array( 'required' => false, 'type' => 'number' ), 'weight_item' => array( 'required' => false, 'type' => 'number' ) ) ) );
		register_rest_route( 'mixmymeals/v1', '/ingredients/(?P<id>\d+)/price', array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'update_ingredient_price' ), 'permission_callback' => $auth, 'args' => array( 'id' => $id_arg, 'price_per_100g' => array( 'required' => true, 'type' => 'number' ) ) ) );
		register_rest_route( 'mixmymeals/v1', '/ingredients/(?P<id>\d+)/units', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_ingredient_units' ), 'permission_callback' => '__return_true', 'args' => array( 'id' => $id_arg ) ) );

		// ── Ingredient Units ──────────────────────────────────────────────
		register_rest_route( 'mixmymeals/v1', '/units', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'create_ingr_unit' ),
			'permission_callback' => $auth,
			'args'                => array(
				'ingredient_id'  => array( 'required' => true,  'type' => 'integer', 'sanitize_callback' => 'absint' ),
				'title'          => array( 'required' => true,  'type' => 'string',  'sanitize_callback' => 'sanitize_text_field' ),
				'weight_g'       => array( 'required' => true,  'type' => 'number' ),
				'is_indivisible' => array( 'required' => false, 'type' => 'boolean' ),
			),
		) );
		register_rest_route( 'mixmymeals/v1', '/units/(?P<id>\d+)', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_ingr_unit' ),
			'permission_callback' => '__return_true',
			'args'                => array( 'id' => $id_arg ),
		) );
		register_rest_route( 'mixmymeals/v1', '/units/(?P<id>\d+)', array(
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => array( $this, 'update_ingr_unit' ),
			'permission_callback' => $auth,
			'args'                => array(
				'id'             => $id_arg,
				'title'          => array( 'required' => false, 'type' => 'string',  'sanitize_callback' => 'sanitize_text_field' ),
				'weight_g'       => array( 'required' => false, 'type' => 'number' ),
				'is_indivisible' => array( 'required' => false, 'type' => 'boolean' ),
			),
		) );

		// ── Meal Plans ────────────────────────────────────────────────────
		register_rest_route( 'mixmymeals/v1', '/meal-plans', array( 'methods' => WP_REST_Server::READABLE,  'callback' => array( $this, 'get_meal_plans' ),   'permission_callback' => '__return_true' ) );
		register_rest_route( 'mixmymeals/v1', '/meal-plans', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'create_meal_plan' ), 'permission_callback' => $auth, 'args' => array( 'title' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ), 'status' => array( 'required' => false, 'type' => 'string', 'default' => 'draft', 'enum' => array( 'publish', 'draft', 'pending', 'private' ) ), 'content' => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'wp_kses_post' ), 'excerpt' => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'wp_kses_post' ), 'start_day' => array( 'required' => false, 'type' => 'integer', 'minimum' => 1, 'maximum' => 7 ), 'end_day' => array( 'required' => false, 'type' => 'integer', 'minimum' => 1, 'maximum' => 7 ), 'categories' => array( 'required' => false, 'type' => 'array', 'items' => array( 'type' => 'integer' ) ), 'tags' => array( 'required' => false, 'type' => 'array', 'items' => array( 'type' => 'integer' ) ) ) ) );
		register_rest_route( 'mixmymeals/v1', '/meal-plans/(?P<id>\d+)', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_meal_plan' ),    'permission_callback' => '__return_true', 'args' => array( 'id' => $id_arg ) ) );
		register_rest_route( 'mixmymeals/v1', '/meal-plans/(?P<id>\d+)', array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'update_meal_plan' ), 'permission_callback' => $auth, 'args' => array( 'id' => $id_arg, 'title' => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ), 'status' => array( 'required' => false, 'type' => 'string', 'enum' => array( 'publish', 'draft', 'pending', 'private' ) ), 'content' => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'wp_kses_post' ), 'excerpt' => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'wp_kses_post' ), 'start_day' => array( 'required' => false, 'type' => 'integer', 'minimum' => 1, 'maximum' => 7 ), 'end_day' => array( 'required' => false, 'type' => 'integer', 'minimum' => 1, 'maximum' => 7 ), 'categories' => array( 'required' => false, 'type' => 'array', 'items' => array( 'type' => 'integer' ) ), 'tags' => array( 'required' => false, 'type' => 'array', 'items' => array( 'type' => 'integer' ) ) ) ) );
		register_rest_route( 'mixmymeals/v1', '/meal-plans/(?P<id>\d+)/meals',         array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_plan_meals' ),    'permission_callback' => '__return_true', 'args' => array( 'id' => $id_arg ) ) );
		register_rest_route( 'mixmymeals/v1', '/meal-plans/(?P<id>\d+)/shopping-list', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_shopping_list' ), 'permission_callback' => '__return_true', 'args' => array( 'id' => $id_arg ) ) );

		// ── Equipment ─────────────────────────────────────────────────────
		register_rest_route( 'mixmymeals/v1', '/equipment', array( 'methods' => WP_REST_Server::READABLE,  'callback' => array( $this, 'get_equipment_items' ), 'permission_callback' => $auth ) );
		register_rest_route( 'mixmymeals/v1', '/equipment', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'create_equipment' ),    'permission_callback' => $auth, 'args' => array( 'title' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ), 'status' => array( 'required' => false, 'type' => 'string', 'default' => 'publish', 'enum' => array( 'publish', 'draft' ) ) ) ) );
		register_rest_route( 'mixmymeals/v1', '/equipment/(?P<id>\d+)', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_equipment_item' ),  'permission_callback' => $auth, 'args' => array( 'id' => $id_arg ) ) );
		register_rest_route( 'mixmymeals/v1', '/equipment/(?P<id>\d+)', array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'update_equipment' ),     'permission_callback' => $auth, 'args' => array( 'id' => $id_arg, 'title' => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ), 'status' => array( 'required' => false, 'type' => 'string', 'enum' => array( 'publish', 'draft' ) ) ) ) );

		// ── Meals ─────────────────────────────────────────────────────────
		register_rest_route( 'mixmymeals/v1', '/meals', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'create_meal' ), 'permission_callback' => $auth, 'args' => array( 'title' => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ), 'plan_id' => array( 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ), 'recipe_id' => array( 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ), 'servings' => array( 'required' => false, 'type' => 'integer', 'default' => 1 ), 'type' => array( 'required' => false, 'type' => 'string', 'enum' => array( 'breakfast', 'lunch', 'dinner', 'snack', 'other' ) ), 'day' => array( 'required' => false, 'type' => 'integer', 'minimum' => 1, 'maximum' => 7 ), 'status' => array( 'required' => false, 'type' => 'string', 'default' => 'publish', 'enum' => array( 'publish', 'draft' ) ) ) ) );
		register_rest_route( 'mixmymeals/v1', '/meals/(?P<id>\d+)', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_meal' ),    'permission_callback' => '__return_true', 'args' => array( 'id' => $id_arg ) ) );
		register_rest_route( 'mixmymeals/v1', '/meals/(?P<id>\d+)', array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'update_meal' ), 'permission_callback' => $auth, 'args' => array( 'id' => $id_arg, 'plan_id' => array( 'required' => false, 'type' => 'integer', 'sanitize_callback' => 'absint' ), 'recipe_id' => array( 'required' => false, 'type' => 'integer', 'sanitize_callback' => 'absint' ), 'servings' => array( 'required' => false, 'type' => 'integer' ), 'type' => array( 'required' => false, 'type' => 'string', 'enum' => array( 'breakfast', 'lunch', 'dinner', 'snack', 'other' ) ), 'day' => array( 'required' => false, 'type' => 'integer', 'minimum' => 1, 'maximum' => 7 ), 'status' => array( 'required' => false, 'type' => 'string', 'enum' => array( 'publish', 'draft' ) ) ) ) );
		register_rest_route( 'mixmymeals/v1', '/meals/(?P<id>\d+)/plan', array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'set_meal_plan' ), 'permission_callback' => $auth, 'args' => array( 'id' => $id_arg, 'plan_id' => array( 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ) ) ) );
	}

	// =========================================================
	// HELPERS
	// =========================================================

	public function check_edit_permission(): bool {
		return current_user_can( 'edit_posts' );
	}

	private function serialize_ingr_unit( int $id ): array {
		$unit = new Mixmymeals_Ingredient_Amount( $id );
		return array(
			'id'             => $id,
			'title'          => $unit->get_title(),
			'parent'         => $unit->get_parent_ingredient_id(),
			'weight_g'       => $unit->get_weight(),
			'is_indivisible' => $unit->is_indivisible(),
		);
	}

	private function serialize_recipe( int $post_id ): array {
		$recipe = new Mixmymeals_Recipe( $post_id );
		$post   = get_post( $post_id );
		return array(
			'id'               => $post_id,
			'title'            => get_the_title( $post_id ),
			'status'           => $post->post_status,
			'link'             => get_permalink( $post_id ),
			'excerpt'          => $post->post_excerpt,
			'servings'         => $recipe->get_servings(),
			'preparation_time' => $recipe->get_preparation_time(),
			'priority'         => $recipe->get_priority(),
			'steps'            => $recipe->get_steps(),
			'equipment'        => $recipe->get_equipment(),
			'diet_type'        => $recipe->get_diet_type(),
			'categories'       => array_map( fn( $t ) => $t->term_id, $recipe->get_categories() ),
			'tags'             => wp_get_post_terms( $post_id, 'mmm_recipe_tag', array( 'fields' => 'ids' ) ),
		);
	}

	private function serialize_ingredient( int $post_id ): array {
		$ing = new Mixmymeals_Ingredient( $post_id );
		return array(
			'id'             => $post_id,
			'title'          => get_the_title( $post_id ),
			'diet_type'      => $ing->get_diet_type(),
			'default_unit'   => $ing->get_default_unit(),
			'price_per_100g' => $ing->get_price_per_100g(),
			'nutrition'      => $ing->get_nutrition(),
			'categories'     => array_map( fn( $t ) => $t->term_id, $ing->get_categories() ),
			'units'          => array_map( fn( $u ) => $this->serialize_ingr_unit( $u->ID ), $ing->get_units() ),
		);
	}

	private function serialize_meal( int $post_id ): array {
		$meal = new Mixmymeals_Meal( $post_id );
		$post = get_post( $post_id );
		return array(
			'id'        => $post_id,
			'title'     => get_the_title( $post_id ),
			'status'    => $post->post_status,
			'plan_id'   => $meal->get_parent_plan_id(),
			'recipe_id' => $meal->get_recipe_id(),
			'servings'  => $meal->get_servings(),
			'type'      => $meal->get_type(),
			'day'       => $meal->get_day(),
		);
	}

	private function serialize_meal_plan( int $post_id ): array {
		$plan  = new Mixmymeals_Meal_Plan( $post_id );
		$post  = get_post( $post_id );
		$meals = array_map( fn( $m ) => $this->serialize_meal( $m->ID ), $plan->get_meals() );
		return array(
			'id'         => $post_id,
			'title'      => get_the_title( $post_id ),
			'status'     => $post->post_status,
			'link'       => get_permalink( $post_id ),
			'content'    => $post->post_content,
			'excerpt'    => $post->post_excerpt,
			'start_day'  => $plan->get_start_day(),
			'end_day'    => $plan->get_end_day(),
			'count_days' => $plan->count_days(),
			'diet_type'  => $plan->get_diet_type(),
			'meals'      => $meals,
		);
	}

	// =========================================================
	// RECIPE CALLBACKS
	// =========================================================

	public function get_recipes( WP_REST_Request $request ): WP_REST_Response {
		$status = $request->get_param( 'status' );
		if ( 'publish' !== $status && ! current_user_can( 'edit_posts' ) ) {
			$status = 'publish';
		}
		$posts = get_posts( array(
			'post_type'      => 'mmm_recipe',
			'posts_per_page' => -1,
			'post_status'    => $status,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );
		return new WP_REST_Response( array_map( fn( $p ) => $this->serialize_recipe( $p->ID ), $posts ), 200 );
	}

	public function create_recipe( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		try {
			$recipe = Mixmymeals_Recipe::create(
				$request->get_param( 'title' ),
				$request->get_param( 'status' ) ?? 'draft',
				$request->get_param( 'excerpt' ) ?? ''
			);
		} catch ( \Exception $e ) {
			return new WP_Error( 'create_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
		if ( $request->has_param( 'servings' ) )         $recipe->set_servings( floatval( $request->get_param( 'servings' ) ) );
		if ( $request->has_param( 'preparation_time' ) ) $recipe->set_preparation_time( absint( $request->get_param( 'preparation_time' ) ) );
		if ( $request->has_param( 'priority' ) )         $recipe->set_priority( absint( $request->get_param( 'priority' ) ) );
		if ( $request->has_param( 'steps' ) )            $recipe->set_steps( $request->get_param( 'steps' ) );
		if ( $request->has_param( 'equipment' ) )        $recipe->set_equipment( $request->get_param( 'equipment' ) );
		if ( $request->has_param( 'categories' ) )       wp_set_post_terms( $recipe->get_id(), $request->get_param( 'categories' ), 'mmm_recipe_category' );
		if ( $request->has_param( 'tags' ) )             wp_set_post_terms( $recipe->get_id(), $request->get_param( 'tags' ), 'mmm_recipe_tag' );
		return new WP_REST_Response( $this->serialize_recipe( $recipe->get_id() ), 201 );
	}

	public function get_recipe( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id = $request->get_param( 'id' );
		if ( ! Mixmymeals_Recipe::exists_by_id( $id ) ) return new WP_Error( 'not_found', 'Recipe not found.', array( 'status' => 404 ) );
		return new WP_REST_Response( $this->serialize_recipe( $id ), 200 );
	}

	public function update_recipe( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id = $request->get_param( 'id' );
		if ( ! Mixmymeals_Recipe::exists_by_id( $id ) ) return new WP_Error( 'not_found', 'Recipe not found.', array( 'status' => 404 ) );
		$update = array( 'ID' => $id );
		if ( $request->has_param( 'title' ) )   $update['post_title']   = $request->get_param( 'title' );
		if ( $request->has_param( 'status' ) )  $update['post_status']  = $request->get_param( 'status' );
		if ( $request->has_param( 'excerpt' ) ) $update['post_excerpt'] = $request->get_param( 'excerpt' );
		if ( count( $update ) > 1 )             wp_update_post( $update );
		$recipe = new Mixmymeals_Recipe( $id );
		if ( $request->has_param( 'servings' ) )         $recipe->set_servings( floatval( $request->get_param( 'servings' ) ) );
		if ( $request->has_param( 'preparation_time' ) ) $recipe->set_preparation_time( absint( $request->get_param( 'preparation_time' ) ) );
		if ( $request->has_param( 'priority' ) )         $recipe->set_priority( absint( $request->get_param( 'priority' ) ) );
		if ( $request->has_param( 'steps' ) )            $recipe->set_steps( $request->get_param( 'steps' ) );
		if ( $request->has_param( 'equipment' ) )        $recipe->set_equipment( $request->get_param( 'equipment' ) );
		if ( $request->has_param( 'categories' ) )       wp_set_post_terms( $id, $request->get_param( 'categories' ), 'mmm_recipe_category' );
		if ( $request->has_param( 'tags' ) )             wp_set_post_terms( $id, $request->get_param( 'tags' ), 'mmm_recipe_tag' );
		return new WP_REST_Response( $this->serialize_recipe( $id ), 200 );
	}

	public function get_recipe_nutrition( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id = $request->get_param( 'id' );
		if ( ! Mixmymeals_Recipe::exists_by_id( $id ) ) return new WP_Error( 'not_found', 'Recipe not found.', array( 'status' => 404 ) );
		$recipe = new Mixmymeals_Recipe( $id );
		return new WP_REST_Response( array(
			'per_serving'      => $recipe->calculate_nutrition_per_serving(),
			'per_100g'         => $recipe->calculate_nutrition_per_100g(),
			'cost_per_serving' => $recipe->calculate_cost_per_serving(),
		), 200 );
	}

	public function get_recipe_ingredients( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id = $request->get_param( 'id' );
		if ( ! Mixmymeals_Recipe::exists_by_id( $id ) ) return new WP_Error( 'not_found', 'Recipe not found.', array( 'status' => 404 ) );
		return new WP_REST_Response( ( new Mixmymeals_Recipe( $id ) )->get_ingredients_scaled( floatval( $request->get_param( 'scale' ) ) ), 200 );
	}

	// =========================================================
	// INGREDIENT CALLBACKS
	// =========================================================

	public function get_ingredients( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response(
			array_map( fn( $p ) => $this->serialize_ingredient( $p->ID ), Mixmymeals_Ingredient::get_all_ingredients() ),
			200
		);
	}

	public function get_ingredient( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id  = $request->get_param( 'id' );
		$ing = new Mixmymeals_Ingredient( $id );
		if ( ! $ing->is_valid() ) return new WP_Error( 'not_found', 'Ingredient not found.', array( 'status' => 404 ) );
		return new WP_REST_Response( $this->serialize_ingredient( $id ), 200 );
	}

	public function create_ingredient( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		try {
			$ing = Mixmymeals_Ingredient::create(
				$request->get_param( 'title' ),
				$request->get_param( 'status' ) ?? 'publish'
			);
		} catch ( \Exception $e ) {
			return new WP_Error( 'create_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
		if ( $request->has_param( 'diet_type' ) )    $ing->set_diet_type( $request->get_param( 'diet_type' ) );
		if ( $request->has_param( 'default_unit' ) ) $ing->set_default_unit( $request->get_param( 'default_unit' ) );
		if ( $request->has_param( 'nutrition' ) )    $ing->set_nutrition( (array) $request->get_param( 'nutrition' ) );
		if ( $request->has_param( 'categories' ) )   wp_set_post_terms( $ing->get_id(), $request->get_param( 'categories' ), 'mmm_ingredient_category' );
		return new WP_REST_Response( $this->serialize_ingredient( $ing->get_id() ), 201 );
	}

	public function update_ingredient( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id  = $request->get_param( 'id' );
		$ing = new Mixmymeals_Ingredient( $id );
		if ( ! $ing->is_valid() ) return new WP_Error( 'not_found', 'Ingredient not found.', array( 'status' => 404 ) );
		if ( $request->has_param( 'title' ) )        wp_update_post( array( 'ID' => $id, 'post_title' => $request->get_param( 'title' ) ) );
		if ( $request->has_param( 'diet_type' ) )    $ing->set_diet_type( $request->get_param( 'diet_type' ) );
		if ( $request->has_param( 'default_unit' ) ) $ing->set_default_unit( $request->get_param( 'default_unit' ) );
		if ( $request->has_param( 'nutrition' ) )    $ing->set_nutrition( (array) $request->get_param( 'nutrition' ) );
		if ( $request->has_param( 'categories' ) )   wp_set_post_terms( $id, $request->get_param( 'categories' ), 'mmm_ingredient_category' );
		return new WP_REST_Response( $this->serialize_ingredient( $id ), 200 );
	}

	public function update_ingredient_nutrition( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id  = $request->get_param( 'id' );
		$ing = new Mixmymeals_Ingredient( $id );
		if ( ! $ing->is_valid() ) return new WP_Error( 'not_found', 'Ingredient not found.', array( 'status' => 404 ) );
		$data = array();
		foreach ( array( 'calories_100', 'fat', 'carbs', 'protein', 'sugar', 'fiber', 'salt', 'weight_item' ) as $field ) {
			if ( $request->has_param( $field ) ) $data[ $field ] = floatval( $request->get_param( $field ) );
		}
		$ing->set_nutrition( $data );
		return new WP_REST_Response( array( 'id' => $id, 'nutrition' => $ing->get_nutrition() ), 200 );
	}

	public function update_ingredient_price( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id  = $request->get_param( 'id' );
		$ing = new Mixmymeals_Ingredient( $id );
		if ( ! $ing->is_valid() ) return new WP_Error( 'not_found', 'Ingredient not found.', array( 'status' => 404 ) );
		$ing->set_price_per_100g( floatval( $request->get_param( 'price_per_100g' ) ) );
		return new WP_REST_Response( array( 'id' => $id, 'price_per_100g' => $ing->get_price_per_100g() ), 200 );
	}

	public function get_ingredient_units( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id  = $request->get_param( 'id' );
		$ing = new Mixmymeals_Ingredient( $id );
		if ( ! $ing->is_valid() ) return new WP_Error( 'not_found', 'Ingredient not found.', array( 'status' => 404 ) );
		return new WP_REST_Response(
			array_map( fn( $u ) => $this->serialize_ingr_unit( $u->ID ), $ing->get_units() ),
			200
		);
	}

	// =========================================================
	// INGREDIENT UNIT CALLBACKS
	// =========================================================

	public function create_ingr_unit( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$ingredient_id = absint( $request->get_param( 'ingredient_id' ) );
		$parent        = new Mixmymeals_Ingredient( $ingredient_id );

		if ( ! $parent->is_valid() ) {
			return new WP_Error( 'not_found', 'Ingredient not found.', array( 'status' => 404 ) );
		}

		try {
			$unit = Mixmymeals_Ingredient_Amount::create( $ingredient_id, $request->get_param( 'title' ) );
		} catch ( \Exception $e ) {
			return new WP_Error( 'create_failed', $e->getMessage(), array( 'status' => 500 ) );
		}

		$unit->set_weight( floatval( $request->get_param( 'weight_g' ) ) );
		if ( $request->has_param( 'is_indivisible' ) ) $unit->set_indivisible( (bool) $request->get_param( 'is_indivisible' ) );

		return new WP_REST_Response( $this->serialize_ingr_unit( $unit->get_id() ), 201 );
	}

	public function get_ingr_unit( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id   = absint( $request->get_param( 'id' ) );
		$unit = new Mixmymeals_Ingredient_Amount( $id );

		if ( ! $unit->is_valid() ) {
			return new WP_Error( 'not_found', 'Unit not found.', array( 'status' => 404 ) );
		}

		return new WP_REST_Response( $this->serialize_ingr_unit( $id ), 200 );
	}

	public function update_ingr_unit( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id   = absint( $request->get_param( 'id' ) );
		$unit = new Mixmymeals_Ingredient_Amount( $id );

		if ( ! $unit->is_valid() ) {
			return new WP_Error( 'not_found', 'Unit not found.', array( 'status' => 404 ) );
		}

		if ( $request->has_param( 'title' ) )         $unit->set_title( $request->get_param( 'title' ) );
		if ( $request->has_param( 'weight_g' ) )       $unit->set_weight( floatval( $request->get_param( 'weight_g' ) ) );
		if ( $request->has_param( 'is_indivisible' ) ) $unit->set_indivisible( (bool) $request->get_param( 'is_indivisible' ) );

		return new WP_REST_Response( $this->serialize_ingr_unit( $id ), 200 );
	}

	// =========================================================
	// MEAL PLAN CALLBACKS
	// =========================================================

	public function get_meal_plans( WP_REST_Request $request ): WP_REST_Response {
		$plans = Mixmymeals_Meal_Plan::get_all_meal_plans();
		return new WP_REST_Response(
			array_map( fn( $p ) => $this->serialize_meal_plan( $p->ID ), $plans ),
			200
		);
	}

	public function create_meal_plan( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		try {
			$plan = Mixmymeals_Meal_Plan::create(
				$request->get_param( 'title' ),
				$request->get_param( 'status' ) ?? 'draft',
				$request->get_param( 'content' ) ?? ''
			);
		} catch ( \Exception $e ) {
			return new WP_Error( 'create_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
		if ( $request->has_param( 'excerpt' ) )    wp_update_post( array( 'ID' => $plan->get_id(), 'post_excerpt' => $request->get_param( 'excerpt' ) ) );
		if ( $request->has_param( 'start_day' ) )  $plan->set_start_day( absint( $request->get_param( 'start_day' ) ) );
		if ( $request->has_param( 'end_day' ) )    $plan->set_end_day( absint( $request->get_param( 'end_day' ) ) );
		if ( $request->has_param( 'categories' ) ) wp_set_post_terms( $plan->get_id(), $request->get_param( 'categories' ), 'mmm_meal_plan_category' );
		if ( $request->has_param( 'tags' ) )       wp_set_post_terms( $plan->get_id(), $request->get_param( 'tags' ), 'mmm_meal_plan_tag' );
		return new WP_REST_Response( $this->serialize_meal_plan( $plan->get_id() ), 201 );
	}

	public function get_meal_plan( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id   = $request->get_param( 'id' );
		$post = get_post( $id );
		if ( ! $post || $post->post_type !== 'mmm_meal_plan' ) return new WP_Error( 'not_found', 'Meal plan not found.', array( 'status' => 404 ) );
		if ( 'publish' !== $post->post_status && ! current_user_can( 'edit_post', $id ) ) return new WP_Error( 'forbidden', 'Access denied.', array( 'status' => 403 ) );
		return new WP_REST_Response( $this->serialize_meal_plan( $id ), 200 );
	}

	public function update_meal_plan( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id   = $request->get_param( 'id' );
		$post = get_post( $id );
		if ( ! $post || $post->post_type !== 'mmm_meal_plan' ) return new WP_Error( 'not_found', 'Meal plan not found.', array( 'status' => 404 ) );
		$update = array( 'ID' => $id );
		if ( $request->has_param( 'title' ) )   $update['post_title']   = $request->get_param( 'title' );
		if ( $request->has_param( 'status' ) )  $update['post_status']  = $request->get_param( 'status' );
		if ( $request->has_param( 'content' ) ) $update['post_content'] = $request->get_param( 'content' );
		if ( $request->has_param( 'excerpt' ) ) $update['post_excerpt'] = $request->get_param( 'excerpt' );
		if ( count( $update ) > 1 )             wp_update_post( $update );
		$plan = new Mixmymeals_Meal_Plan( $id );
		if ( $request->has_param( 'start_day' ) )  $plan->set_start_day( absint( $request->get_param( 'start_day' ) ) );
		if ( $request->has_param( 'end_day' ) )    $plan->set_end_day( absint( $request->get_param( 'end_day' ) ) );
		if ( $request->has_param( 'categories' ) ) wp_set_post_terms( $id, $request->get_param( 'categories' ), 'mmm_meal_plan_category' );
		if ( $request->has_param( 'tags' ) )       wp_set_post_terms( $id, $request->get_param( 'tags' ), 'mmm_meal_plan_tag' );
		return new WP_REST_Response( $this->serialize_meal_plan( $id ), 200 );
	}

	public function get_plan_meals( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id   = $request->get_param( 'id' );
		$post = get_post( $id );
		if ( ! $post || $post->post_type !== 'mmm_meal_plan' ) return new WP_Error( 'not_found', 'Meal plan not found.', array( 'status' => 404 ) );
		if ( 'publish' !== $post->post_status && ! current_user_can( 'edit_post', $id ) ) return new WP_Error( 'forbidden', 'Access denied.', array( 'status' => 403 ) );
		$plan = new Mixmymeals_Meal_Plan( $id );
		return new WP_REST_Response(
			array_map( fn( $m ) => $this->serialize_meal( $m->ID ), $plan->get_meals() ),
			200
		);
	}

	public function get_shopping_list( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$plan_id = $request->get_param( 'id' );
		$post    = get_post( $plan_id );
		if ( ! $post || $post->post_type !== 'mmm_meal_plan' ) return new WP_Error( 'not_found', 'Meal plan not found.', array( 'status' => 404 ) );
		if ( 'publish' !== $post->post_status && ! current_user_can( 'edit_post', $plan_id ) ) return new WP_Error( 'forbidden', 'Access denied.', array( 'status' => 403 ) );
		$plan   = new Mixmymeals_Meal_Plan( $plan_id );
		$raw    = $plan->calc_shopping_list();
		$result = array();
		foreach ( $raw as $category => $ingredients ) {
			$items = array();
			foreach ( $ingredients as $ing_id => $data ) {
				$items[] = array(
					'ingredient_id'   => $ing_id,
					'ingredient_name' => get_the_title( $ing_id ),
					'total_weight_g'  => round( (float) $data['total_weight'], 2 ),
					'details'         => array_map( static fn( $d ) => array(
						'recipe_name' => $d['recipe_name'],
						'amount'      => $d['amount'],
						'unit'        => $d['unit_title'],
						'weight_g'    => round( (float) $d['weight_g'], 2 ),
					), $data['details'] ),
				);
			}
			$result[] = array( 'category' => $category, 'items' => $items );
		}
		return new WP_REST_Response( $result, 200 );
	}

	// =========================================================
	// EQUIPMENT CALLBACKS
	// =========================================================

	private function serialize_equipment( int $post_id ): array {
		$equipment = new Mixmymeals_Equipment( $post_id );
		$post      = get_post( $post_id );
		return array(
			'id'     => $post_id,
			'title'  => $equipment->get_title(),
			'status' => $post->post_status,
		);
	}

	public function get_equipment_items( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response(
			array_map( fn( $p ) => $this->serialize_equipment( $p->ID ), Mixmymeals_Equipment::get_all() ),
			200
		);
	}

	public function get_equipment_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id = $request->get_param( 'id' );
		if ( ! Mixmymeals_Equipment::exists_by_id( $id ) ) {
			return new WP_Error( 'not_found', 'Equipment not found.', array( 'status' => 404 ) );
		}
		return new WP_REST_Response( $this->serialize_equipment( $id ), 200 );
	}

	public function create_equipment( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		try {
			$equipment = Mixmymeals_Equipment::create(
				$request->get_param( 'title' ),
				$request->get_param( 'status' ) ?? 'publish'
			);
		} catch ( \Exception $e ) {
			return new WP_Error( 'create_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
		return new WP_REST_Response( $this->serialize_equipment( $equipment->get_id() ), 201 );
	}

	public function update_equipment( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id        = $request->get_param( 'id' );
		$equipment = new Mixmymeals_Equipment( $id );
		if ( ! $equipment->is_valid() ) {
			return new WP_Error( 'not_found', 'Equipment not found.', array( 'status' => 404 ) );
		}
		if ( $request->has_param( 'title' ) )  $equipment->set_title( $request->get_param( 'title' ) );
		if ( $request->has_param( 'status' ) ) wp_update_post( array( 'ID' => $id, 'post_status' => $request->get_param( 'status' ) ) );
		return new WP_REST_Response( $this->serialize_equipment( $id ), 200 );
	}

	// =========================================================
	// MEAL CALLBACKS
	// =========================================================

	public function create_meal( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$plan_id   = $request->get_param( 'plan_id' );
		$recipe_id = $request->get_param( 'recipe_id' );
		$title     = $request->get_param( 'title' ) ?: get_the_title( $recipe_id );
		try {
			$meal = Mixmymeals_Meal::create( $title, $plan_id, $request->get_param( 'status' ) ?? 'publish' );
		} catch ( \Exception $e ) {
			return new WP_Error( 'create_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
		$meal->set_recipe_id( $recipe_id );
		if ( $request->has_param( 'servings' ) ) $meal->set_servings( absint( $request->get_param( 'servings' ) ) );
		if ( $request->has_param( 'type' ) )     $meal->set_type( $request->get_param( 'type' ) );
		if ( $request->has_param( 'day' ) )      $meal->set_day( absint( $request->get_param( 'day' ) ) );
		return new WP_REST_Response( $this->serialize_meal( $meal->get_id() ), 201 );
	}

	public function get_meal( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id   = $request->get_param( 'id' );
		$post = get_post( $id );
		if ( ! $post || $post->post_type !== 'mmm_meal' ) return new WP_Error( 'not_found', 'Meal not found.', array( 'status' => 404 ) );
		return new WP_REST_Response( $this->serialize_meal( $id ), 200 );
	}

	public function update_meal( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id   = $request->get_param( 'id' );
		$post = get_post( $id );
		if ( ! $post || $post->post_type !== 'mmm_meal' ) return new WP_Error( 'not_found', 'Meal not found.', array( 'status' => 404 ) );
		$meal = new Mixmymeals_Meal( $id );
		if ( $request->has_param( 'plan_id' ) )   $meal->set_parent_plan_id( absint( $request->get_param( 'plan_id' ) ) );
		if ( $request->has_param( 'recipe_id' ) ) $meal->set_recipe_id( absint( $request->get_param( 'recipe_id' ) ) );
		if ( $request->has_param( 'servings' ) )  $meal->set_servings( absint( $request->get_param( 'servings' ) ) );
		if ( $request->has_param( 'type' ) )      $meal->set_type( $request->get_param( 'type' ) );
		if ( $request->has_param( 'day' ) )       $meal->set_day( absint( $request->get_param( 'day' ) ) );
		if ( $request->has_param( 'status' ) )    wp_update_post( array( 'ID' => $id, 'post_status' => $request->get_param( 'status' ) ) );
		if ( $request->has_param( 'title' ) )     wp_update_post( array( 'ID' => $id, 'post_title'  => $request->get_param( 'title' ) ) );
		return new WP_REST_Response( $this->serialize_meal( $id ), 200 );
	}

	public function set_meal_plan( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$meal_id = $request->get_param( 'id' );
		$plan_id = $request->get_param( 'plan_id' );
		( new Mixmymeals_Meal( $meal_id ) )->set_parent_plan_id( $plan_id );
		return new WP_REST_Response( array( 'success' => true, 'meal_id' => $meal_id, 'plan_id' => $plan_id ), 200 );
	}
}
