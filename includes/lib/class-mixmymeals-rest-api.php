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

	/** Präfix der Transients, die Idempotenz-Ergebnisse halten. */
	const IDEMPOTENCY_PREFIX = 'mmm_idem_';

	/** Wie lange ein Idempotenz-Schlüssel gilt. */
	const IDEMPOTENCY_TTL = DAY_IN_SECONDS;

	public function __construct() {
		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );

		/*
		 * Idempotenz für alle Schreiboperationen dieses Namespaces. Bewusst als
		 * Filterpaar statt in jedem Callback: MCP-Timeouts sind häufig, nach
		 * einem "fetch failed" weiß der Client nicht, ob der Write durchkam, und
		 * ein Retry würde Duplikate anlegen. Ein Aufruf mit demselben
		 * idempotency_key liefert das gespeicherte Ergebnis zurück, ohne erneut
		 * zu schreiben.
		 */
		add_filter( 'rest_pre_dispatch', array( $this, 'idempotency_replay' ), 10, 3 );
		add_filter( 'rest_post_dispatch', array( $this, 'idempotency_store' ), 10, 3 );
	}

	// =========================================================
	// IDEMPOTENZ
	// =========================================================

	/**
	 * Der Idempotenz-Schlüssel einer Anfrage, sofern sie überhaupt dafür in
	 * Frage kommt: eigener Namespace, schreibende Methode, Schlüssel gesetzt.
	 *
	 * @param WP_REST_Request $request Anfrage.
	 * @return string Leerer String, wenn nicht anwendbar.
	 */
	private function idempotency_key( WP_REST_Request $request ): string {

		if ( 0 !== strpos( (string) $request->get_route(), '/mixmymeals/v1' ) ) {
			return '';
		}

		if ( in_array( strtoupper( $request->get_method() ), array( 'GET', 'HEAD', 'OPTIONS' ), true ) ) {
			return '';
		}

		return trim( (string) $request->get_param( 'idempotency_key' ) );
	}

	/**
	 * Gibt ein gespeichertes Ergebnis zurück, statt die Operation erneut
	 * auszuführen. Kommt derselbe Schlüssel für eine andere Route, ist das ein
	 * Client-Fehler und wird als solcher gemeldet – sonst bekäme der Aufrufer
	 * stillschweigend das Ergebnis einer fremden Operation.
	 *
	 * @param mixed           $result  Bisheriges Ergebnis (null = weitermachen).
	 * @param WP_REST_Server  $server  Server.
	 * @param WP_REST_Request $request Anfrage.
	 * @return mixed
	 */
	public function idempotency_replay( $result, $server, $request ) {

		if ( null !== $result || ! $request instanceof WP_REST_Request ) {
			return $result;
		}

		$key = $this->idempotency_key( $request );
		if ( '' === $key ) {
			return $result;
		}

		$stored = get_transient( self::IDEMPOTENCY_PREFIX . md5( $key ) );
		if ( ! is_array( $stored ) ) {
			return $result;
		}

		if ( $stored['route'] !== $request->get_route() || $stored['method'] !== $request->get_method() ) {
			return new WP_Error(
				'idempotency_key_reused',
				sprintf(
					'Der Idempotenz-Schlüssel wurde bereits für %s %s verwendet. Für eine andere Operation einen neuen Schlüssel wählen.',
					$stored['method'],
					$stored['route']
				),
				array( 'status' => 409 )
			);
		}

		$data = $stored['data'];
		if ( is_array( $data ) ) {
			$data['idempotent_replay'] = true;
		}

		return new WP_REST_Response( $data, $stored['status'] );
	}

	/**
	 * Legt das Ergebnis einer geschriebenen Operation unter dem Schlüssel ab.
	 *
	 * Nur Erfolge werden gespeichert: einen Fehlversuch festzuschreiben würde
	 * einen berechtigten Retry mit demselben Schlüssel dauerhaft blockieren.
	 *
	 * @param WP_REST_Response $response Antwort.
	 * @param WP_REST_Server   $server   Server.
	 * @param WP_REST_Request  $request  Anfrage.
	 * @return WP_REST_Response
	 */
	public function idempotency_store( $response, $server, $request ) {

		if ( ! $response instanceof WP_REST_Response || ! $request instanceof WP_REST_Request ) {
			return $response;
		}

		$key = $this->idempotency_key( $request );
		if ( '' === $key ) {
			return $response;
		}

		$status = $response->get_status();
		if ( $status < 200 || $status >= 300 ) {
			return $response;
		}

		$data = $response->get_data();

		// Wiedergabe nicht erneut ablegen – sonst verlängert jeder Replay die TTL.
		if ( is_array( $data ) && ! empty( $data['idempotent_replay'] ) ) {
			return $response;
		}

		set_transient(
			self::IDEMPOTENCY_PREFIX . md5( $key ),
			array(
				'route'  => $request->get_route(),
				'method' => $request->get_method(),
				'status' => $status,
				'data'   => $data,
			),
			self::IDEMPOTENCY_TTL
		);

		return $response;
	}

	// =========================================================
	// META REGISTRATION
	// =========================================================

	public function register_meta(): void {

		$can_edit = static fn() => current_user_can( 'edit_others_posts' );
		$to_float = static fn( $value ) => floatval( $value );

		// mmm_recipe
		register_post_meta( 'mmm_recipe', '_mmm_servings', array( 'type' => 'number', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => $to_float, 'auth_callback' => $can_edit ) );

		/*
		 * Note the gap: this core meta route sanitises with absint() and will
		 * happily store a 0, which Mixmymeals_Recipe::set_preparation_time()
		 * no longer does — a 0 written this way reads as "takes no time" and
		 * would put the recipe into the archive's fastest bucket. The recipe's
		 * index recount normalises it away on the next write to that post; the
		 * namespace's own /recipes routes go through the setter and never
		 * produce it.
		 */
		register_post_meta( 'mmm_recipe', '_mmm_preparation_time', array( 'type' => 'integer', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'absint', 'auth_callback' => $can_edit ) );
		register_post_meta( 'mmm_recipe', '_mmm_priority', array( 'type' => 'integer', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'absint', 'auth_callback' => $can_edit ) );
		register_post_meta( 'mmm_recipe', '_mmm_equipment', array( 'type' => 'array', 'single' => true, 'show_in_rest' => array( 'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ) ), 'auth_callback' => $can_edit ) );
		register_post_meta( 'mmm_recipe', '_mmm_related_recipes', array( 'type' => 'array', 'single' => true, 'show_in_rest' => array( 'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ) ), 'auth_callback' => $can_edit ) );
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
					// Preparation ahead: both set (amount >= 1) or the step is
					// an ordinary one. See Mixmymeals_Recipe::step_prep().
					'prep_amount' => array( 'type' => 'integer', 'minimum' => 0 ),
					'prep_unit'   => array( 'type' => 'string', 'enum' => array( '', 'days', 'hours' ) ),
					'ingredients' => array( 'type' => 'array', 'items' => array(
						'type'                 => 'object',
						'additionalProperties' => true,
						'properties'           => array(
							'id'                => array( 'type' => 'integer' ),
							'amount'            => array( 'type' => 'number' ),
							'amount_id'         => array( 'type' => 'integer' ),
							'product_id'        => array( 'type' => 'integer' ),
							'optional'          => array( 'type' => 'boolean' ),
							'excluded_products' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
						),
					) ),
				),
			) ) ),
			'auth_callback' => $can_edit,
		) );

		// mmm_ingredient
		foreach ( array( '_rmm_calories_100', '_rmm_fat', '_mmm_saturates', '_rmm_carbs', '_rmm_protein', '_rmm_sugar', '_rmm_fiber', '_rmm_salt', '_rmm_weight_item' ) as $field ) {
			register_post_meta( 'mmm_ingredient', $field, array( 'type' => 'number', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => $to_float, 'auth_callback' => $can_edit ) );
		}
		register_post_meta( 'mmm_ingredient', '_mmm_price_per_100g', array( 'type' => 'number', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => $to_float, 'auth_callback' => $can_edit ) );
		register_post_meta( 'mmm_ingredient', '_mmm_diet_type',      array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'sanitize_text_field', 'auth_callback' => $can_edit ) );
		register_post_meta( 'mmm_ingredient', '_mmm_default_unit',   array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'sanitize_text_field', 'auth_callback' => $can_edit ) );

		// mmm_ingr_unit
		register_post_meta( 'mmm_ingr_unit', '_mmm_ingr_unit_weight', array( 'type' => 'number',  'single' => true, 'show_in_rest' => true, 'sanitize_callback' => $to_float, 'auth_callback' => $can_edit ) );
		register_post_meta( 'mmm_ingr_unit', '_mmm_unit_divisor',     array( 'type' => 'integer', 'single' => true, 'show_in_rest' => true, 'auth_callback' => $can_edit ) );

		// mmm_product
		foreach ( Mixmymeals_Product::NUTRITION_FIELDS as $field ) {
			register_post_meta( Mixmymeals_Product::POST_TYPE, $field, array( 'type' => 'number', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => $to_float, 'auth_callback' => $can_edit ) );
		}
		register_post_meta( Mixmymeals_Product::POST_TYPE, Mixmymeals_Product::META_PACKAGE_WEIGHT, array( 'type' => 'number', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => $to_float, 'auth_callback' => $can_edit ) );
		register_post_meta( Mixmymeals_Product::POST_TYPE, Mixmymeals_Product::META_BRAND,  array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'sanitize_text_field', 'auth_callback' => $can_edit ) );
		register_post_meta( Mixmymeals_Product::POST_TYPE, Mixmymeals_Product::META_SOURCE, array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'sanitize_text_field', 'auth_callback' => $can_edit ) );

		// mmm_meal_plan
		register_post_meta( 'mmm_meal_plan', 'start_day', array( 'type' => 'integer', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'absint', 'auth_callback' => $can_edit ) );
		register_post_meta( 'mmm_meal_plan', 'end_day',   array( 'type' => 'integer', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'absint', 'auth_callback' => $can_edit ) );

		// Author box — identical on both post types, see Mixmymeals_Post_Meta.
		foreach ( array( 'mmm_recipe', 'mmm_meal_plan' ) as $author_type ) {
			register_post_meta( $author_type, Mixmymeals_Post_Meta::AUTHOR_NAME,      array( 'type' => 'string',  'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'sanitize_text_field', 'auth_callback' => $can_edit ) );
			register_post_meta( $author_type, Mixmymeals_Post_Meta::AUTHOR_TEXT,      array( 'type' => 'string',  'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'wp_kses_post',        'auth_callback' => $can_edit ) );
			register_post_meta( $author_type, Mixmymeals_Post_Meta::AUTHOR_URL,       array( 'type' => 'string',  'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'esc_url_raw',         'auth_callback' => $can_edit ) );
			register_post_meta( $author_type, Mixmymeals_Post_Meta::AUTHOR_LINK_TEXT, array( 'type' => 'string',  'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'sanitize_text_field', 'auth_callback' => $can_edit ) );
			register_post_meta( $author_type, Mixmymeals_Post_Meta::AUTHOR_NOFOLLOW,  array( 'type' => 'string',  'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'sanitize_text_field', 'auth_callback' => $can_edit ) );
			register_post_meta( $author_type, Mixmymeals_Post_Meta::AUTHOR_SOCIAL,    array( 'type' => 'array',   'single' => true, 'show_in_rest' => array( 'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ) ), 'auth_callback' => $can_edit ) );
			register_post_meta( $author_type, Mixmymeals_Post_Meta::AUTHOR_IMAGE,     array( 'type' => 'integer', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'absint',              'auth_callback' => $can_edit ) );

			// Image credit — same four fields on both types.
			foreach ( Mixmymeals_Post_Meta::IMAGE_CREDIT_FIELDS as $credit_field => $credit_key ) {
				register_post_meta( $author_type, $credit_key, array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'url' === $credit_field ? 'esc_url_raw' : 'sanitize_text_field', 'auth_callback' => $can_edit ) );
			}
		}

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

		// Featured image of a recipe/plan. 0 removes it; upload new images via
		// POST /media, which returns the attachment ID to put here.
		$featured_arg = array(
			'required'    => false,
			'type'        => 'integer',
			'description' => 'Attachment ID of the featured image, 0 removes it.',
		);

		// Credit for the featured image. See Mixmymeals_Post_Meta::IMAGE_CREDIT_FIELDS.
		$image_credit_arg = array(
			'required'    => false,
			'type'        => 'object',
			'description' => 'Credit for the featured image. Only supplied keys change; an empty string clears a field.',
			'properties'  => array(
				'creator' => array( 'type' => 'string', 'description' => 'Who took or made the picture.' ),
				'source'  => array( 'type' => 'string', 'description' => 'Where it came from, e.g. the stock library.' ),
				'license' => array( 'type' => 'string', 'description' => 'Licence, e.g. "CC BY 4.0".' ),
				'url'     => array( 'type' => 'string', 'description' => 'Optional link for creator and source.' ),
			),
		);

		// The external-author box, embeddable in every recipe/plan write and
		// editable on its own under …/author. See Mixmymeals_Post_Meta.
		$author_arg = array(
			'required'    => false,
			'type'        => 'object',
			'description' => 'External author box. Only supplied keys change; an empty string clears a field. With no name the post falls back to its WordPress author.',
			'properties'  => self::author_schema_properties(),
		);

		// A figure sent as null is withdrawn, not zeroed: "not stated" and
		// "contains none" are different claims, and only the first lets a
		// reference product's value show through.
		$nullable_number = array( 'required' => false, 'type' => array( 'number', 'null' ) );

		// ── Recipes ───────────────────────────────────────────────────────
		register_rest_route( 'mixmymeals/v1', '/recipes', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_recipes' ), 'permission_callback' => '__return_true', 'args' => array( 'status' => array( 'required' => false, 'type' => 'string', 'enum' => array( 'publish', 'draft', 'any' ), 'default' => 'publish' ) ) ) );
		register_rest_route( 'mixmymeals/v1', '/recipes', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'create_recipe' ), 'permission_callback' => $auth, 'args' => array( 'title' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ), 'status' => array( 'required' => false, 'type' => 'string', 'default' => 'draft', 'enum' => array( 'publish', 'draft', 'pending', 'private' ) ), 'excerpt' => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field' ), 'servings' => array( 'required' => false, 'type' => 'number' ), 'preparation_time' => array( 'required' => false, 'type' => 'integer' ), 'shelf_life_days' => array( 'required' => false, 'type' => array( 'integer', 'null' ), 'minimum' => 0 ), 'storage_type' => array( 'required' => false, 'type' => 'string', 'enum' => array_merge( array_keys( Mixmymeals_Post_Types::storage_types() ), array( '' ) ) ), 'steps' => array( 'required' => false, 'type' => 'array' ), 'equipment' => array( 'required' => false, 'type' => 'array' ), 'related_recipes' => array( 'required' => false, 'type' => 'array', 'items' => array( 'type' => 'integer' ) ), 'categories' => array( 'required' => false, 'type' => 'array', 'items' => array( 'type' => 'integer' ) ), 'tags' => array( 'required' => false, 'type' => 'array', 'items' => array( 'type' => 'integer' ) ), 'author' => $author_arg, 'featured_image' => $featured_arg, 'image_credit' => $image_credit_arg ) ) );
		register_rest_route( 'mixmymeals/v1', '/recipes/(?P<id>\d+)', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_recipe' ), 'permission_callback' => '__return_true', 'args' => array( 'id' => $id_arg ) ) );
		register_rest_route( 'mixmymeals/v1', '/recipes/(?P<id>\d+)', array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'update_recipe' ), 'permission_callback' => $auth, 'args' => array( 'id' => $id_arg, 'title' => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ), 'status' => array( 'required' => false, 'type' => 'string', 'enum' => array( 'publish', 'draft', 'pending', 'private' ) ), 'excerpt' => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field' ), 'servings' => array( 'required' => false, 'type' => 'number' ), 'preparation_time' => array( 'required' => false, 'type' => 'integer' ), 'shelf_life_days' => array( 'required' => false, 'type' => array( 'integer', 'null' ), 'minimum' => 0 ), 'storage_type' => array( 'required' => false, 'type' => 'string', 'enum' => array_merge( array_keys( Mixmymeals_Post_Types::storage_types() ), array( '' ) ) ), 'steps' => array( 'required' => false, 'type' => 'array' ), 'equipment' => array( 'required' => false, 'type' => 'array' ), 'related_recipes' => array( 'required' => false, 'type' => 'array', 'items' => array( 'type' => 'integer' ) ), 'categories' => array( 'required' => false, 'type' => 'array', 'items' => array( 'type' => 'integer' ) ), 'tags' => array( 'required' => false, 'type' => 'array', 'items' => array( 'type' => 'integer' ) ), 'author' => $author_arg, 'featured_image' => $featured_arg, 'image_credit' => $image_credit_arg ) ) );
		register_rest_route( 'mixmymeals/v1', '/recipes/(?P<id>\d+)/nutrition',   array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_recipe_nutrition' ),   'permission_callback' => '__return_true', 'args' => array( 'id' => $id_arg ) ) );
		register_rest_route( 'mixmymeals/v1', '/recipes/(?P<id>\d+)/ingredients', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_recipe_ingredients' ), 'permission_callback' => '__return_true', 'args' => array( 'id' => $id_arg, 'scale' => array( 'required' => false, 'type' => 'number', 'default' => 1.0 ) ) ) );
		register_rest_route( 'mixmymeals/v1', '/recipes/(?P<id>\d+)/equipment',   array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_recipe_equipment' ),   'permission_callback' => '__return_true', 'args' => array( 'id' => $id_arg ) ) );
		$this->register_author_routes( 'recipes', $auth, $id_arg );

		// ── Ingredients ───────────────────────────────────────────────────
		register_rest_route( 'mixmymeals/v1', '/ingredients', array( 'methods' => WP_REST_Server::READABLE,  'callback' => array( $this, 'get_ingredients' ),    'permission_callback' => '__return_true' ) );
		register_rest_route( 'mixmymeals/v1', '/ingredients', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'create_ingredient' ), 'permission_callback' => $auth, 'args' => array( 'title' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ), 'status' => array( 'required' => false, 'type' => 'string', 'default' => 'publish', 'enum' => array( 'publish', 'draft' ) ), 'diet_type' => array( 'required' => false, 'type' => 'string', 'enum' => array_merge( Mixmymeals_Diet::axis_slugs( 'diet' ), array( '' ) ) ), 'shelf_life_days' => array( 'required' => false, 'type' => array( 'integer', 'null' ), 'minimum' => 0 ), 'own_cooking_factor' => array( 'required' => false, 'type' => array( 'number', 'null' ), 'minimum' => 0, 'exclusiveMinimum' => true, 'maximum' => Mixmymeals_Ingredient::COOKING_FACTOR_MAX ), 'storage_type' => array( 'required' => false, 'type' => 'string', 'enum' => array_merge( array_keys( Mixmymeals_Post_Types::storage_types() ), array( '' ) ) ), 'default_unit' => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ), 'categories' => array( 'required' => false, 'type' => 'array', 'items' => array( 'type' => 'integer' ) ), 'nutrition' => array( 'required' => false, 'type' => 'object' ) ) ) );
		register_rest_route( 'mixmymeals/v1', '/ingredients/(?P<id>\d+)', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_ingredient' ),    'permission_callback' => '__return_true', 'args' => array( 'id' => $id_arg ) ) );
		register_rest_route( 'mixmymeals/v1', '/ingredients/(?P<id>\d+)', array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'update_ingredient' ), 'permission_callback' => $auth, 'args' => array( 'id' => $id_arg, 'title' => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ), 'diet_type' => array( 'required' => false, 'type' => 'string', 'enum' => array_merge( Mixmymeals_Diet::axis_slugs( 'diet' ), array( '' ) ) ), 'shelf_life_days' => array( 'required' => false, 'type' => array( 'integer', 'null' ), 'minimum' => 0 ), 'own_cooking_factor' => array( 'required' => false, 'type' => array( 'number', 'null' ), 'minimum' => 0, 'exclusiveMinimum' => true, 'maximum' => Mixmymeals_Ingredient::COOKING_FACTOR_MAX ), 'storage_type' => array( 'required' => false, 'type' => 'string', 'enum' => array_merge( array_keys( Mixmymeals_Post_Types::storage_types() ), array( '' ) ) ), 'default_unit' => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ), 'categories' => array( 'required' => false, 'type' => 'array', 'items' => array( 'type' => 'integer' ) ), 'nutrition' => array( 'required' => false, 'type' => 'object' ), 'own_price_per_100g' => array( 'required' => false, 'type' => array( 'number', 'null' ), 'minimum' => 0 ) ) ) );
		register_rest_route( 'mixmymeals/v1', '/ingredients/(?P<id>\d+)/nutrition', array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'update_ingredient_nutrition' ), 'permission_callback' => $auth, 'args' => array( 'id' => $id_arg, 'calories_100' => $nullable_number, 'fat' => $nullable_number, 'saturates' => $nullable_number, 'carbs' => $nullable_number, 'protein' => $nullable_number, 'sugar' => $nullable_number, 'fiber' => $nullable_number, 'salt' => $nullable_number, 'weight_item' => $nullable_number ) ) );
		register_rest_route( 'mixmymeals/v1', '/ingredients/(?P<id>\d+)/price', array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'update_ingredient_price' ), 'permission_callback' => $auth, 'args' => array( 'id' => $id_arg, 'price_per_100g' => array( 'required' => true, 'type' => array( 'number', 'null' ), 'minimum' => 0 ) ) ) );
		register_rest_route( 'mixmymeals/v1', '/ingredients/(?P<id>\d+)/units', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_ingredient_units' ), 'permission_callback' => '__return_true', 'args' => array( 'id' => $id_arg ) ) );
		register_rest_route( 'mixmymeals/v1', '/ingredients/search', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'search_ingredients' ), 'permission_callback' => '__return_true', 'args' => array( 'query' => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ), 'diet_type' => array( 'required' => false, 'type' => 'string', 'enum' => Mixmymeals_Diet::axis_slugs( 'diet' ) ), 'limit' => array( 'required' => false, 'type' => 'integer', 'default' => 20 ) ) ) );

		// ── Audit ─────────────────────────────────────────────────────────
		register_rest_route( 'mixmymeals/v1', '/diets/migrate', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'migrate_diets' ), 'permission_callback' => $auth, 'args' => array( 'dry_run' => array( 'required' => false, 'type' => 'boolean', 'default' => true ), 'limit' => array( 'required' => false, 'type' => 'integer', 'default' => 200, 'minimum' => 1, 'maximum' => 1000 ) ) ) );
		register_rest_route( 'mixmymeals/v1', '/audit', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'audit' ), 'permission_callback' => $auth, 'args' => array( 'scope' => array( 'required' => false, 'type' => 'string', 'default' => 'all', 'enum' => array( 'recipes', 'plans', 'ingredients', 'all' ) ) ) ) );

		// ── Zutaten zusammenführen ────────────────────────────────────────
		register_rest_route( 'mixmymeals/v1', '/ingredients/(?P<id>\d+)/merge', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'merge_ingredients' ), 'permission_callback' => $auth, 'args' => array( 'id' => $id_arg, 'to_id' => array( 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ), 'dry_run' => array( 'required' => false, 'type' => 'boolean', 'default' => false ) ) ) );

		// ── Löschen (mit Referenz-Prüfung) ────────────────────────────────
		register_rest_route( 'mixmymeals/v1', '/recipes/(?P<id>\d+)', array( 'methods' => WP_REST_Server::DELETABLE, 'callback' => array( $this, 'delete_recipe' ), 'permission_callback' => $auth, 'args' => array( 'id' => $id_arg, 'force' => array( 'required' => false, 'type' => 'boolean', 'default' => false ) ) ) );
		register_rest_route( 'mixmymeals/v1', '/ingredients/(?P<id>\d+)', array( 'methods' => WP_REST_Server::DELETABLE, 'callback' => array( $this, 'delete_ingredient' ), 'permission_callback' => $auth, 'args' => array( 'id' => $id_arg, 'force' => array( 'required' => false, 'type' => 'boolean', 'default' => false ) ) ) );

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
				'divisor'        => array( 'required' => false, 'type' => 'integer', 'minimum' => 0 ),
			),
		) );
		register_rest_route( 'mixmymeals/v1', '/units/(?P<id>\d+)', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_ingr_unit' ),
			'permission_callback' => '__return_true',
			'args'                => array( 'id' => $id_arg ),
		) );
		/*
		 * POST rather than DELETE: this carries a list and two switches, and a
		 * request body on DELETE is poorly supported by enough clients to be
		 * not worth the purity.
		 */
		/*
		 * Bulk keeping data. Setting this one item at a time meant 516 calls
		 * for one pass over the library, each answering with the item's whole
		 * serialization — nutrition, every unit, every product — which is
		 * hundreds of times more data than the two fields being written.
		 */
		register_rest_route( 'mixmymeals/v1', '/diets/bulk', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'bulk_set_diets' ),
			'permission_callback' => $auth,
			'args'                => array(
				'items' => array(
					'required' => true,
					'type'     => 'array',
					'maxItems' => 500,
					'items'    => array(
						'type'       => 'object',
						'properties' => array(
							'id'    => array( 'type' => 'integer' ),
							'diets' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
						),
					),
				),
			),
		) );

		register_rest_route( 'mixmymeals/v1', '/keeping/bulk', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'bulk_set_keeping' ),
			'permission_callback' => $auth,
			'args'                => array(
				'items'   => array(
					'required' => true,
					'type'     => 'array',
					'maxItems' => 500,
					'items'    => array(
						'type'       => 'object',
						'properties' => array(
							'id'              => array( 'type' => 'integer' ),
							'shelf_life_days' => array( 'type' => array( 'integer', 'null' ), 'minimum' => 0 ),
							'storage_type'    => array( 'type' => 'string', 'enum' => array_merge( array_keys( Mixmymeals_Post_Types::storage_types() ), array( '' ) ) ),
						),
					),
				),
				'dry_run' => array( 'required' => false, 'type' => 'boolean', 'default' => false ),
			),
		) );
		/*
		 * Nutrition, price and diet for many ingredients and products at once —
		 * the data-correction counterpart of /keeping/bulk, and thin for the
		 * same reason: the single routes answer with whole serializations.
		 *
		 * Unknown keys and unknown diets reject the whole request before
		 * anything is written (additionalProperties false): a mistyped
		 * "saturated" in a batch of a hundred is a caller bug, and silently
		 * dropping it would read as a hundred successes.
		 */
		register_rest_route( 'mixmymeals/v1', '/bulk-update', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'bulk_update' ),
			'permission_callback' => $auth,
			'args'                => array(
				'items'   => array(
					'required' => true,
					'type'     => 'array',
					'maxItems' => 100,
					'items'    => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'properties'           => array(
							'type'           => array( 'type' => 'string', 'enum' => array( 'ingredient', 'product' ) ),
							'id'             => array( 'type' => 'integer' ),
							'nutrition'      => array(
								'type'                 => 'object',
								'additionalProperties' => false,
								'properties'           => array_fill_keys( array_merge( Mixmymeals_Ingredient::NUTRITION_FIGURE_KEYS, array( 'weight_item' ) ), array( 'type' => array( 'number', 'null' ), 'minimum' => 0 ) ),
							),
							'price_per_100g' => array( 'type' => array( 'number', 'null' ), 'minimum' => 0 ),
							'diet_type'      => array( 'type' => 'string', 'enum' => array_merge( Mixmymeals_Diet::axis_slugs( 'diet' ), array( '' ) ) ),
							// Out of range rejects the whole request, like an unknown
							// diet: a factor of 240 is a caller bug.
							'cooking_factor' => array( 'type' => array( 'number', 'null' ), 'minimum' => 0, 'exclusiveMinimum' => true, 'maximum' => Mixmymeals_Ingredient::COOKING_FACTOR_MAX ),
						),
					),
				),
				'dry_run' => array( 'required' => false, 'type' => 'boolean', 'default' => false ),
			),
		) );
		register_rest_route( 'mixmymeals/v1', '/missing', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'list_missing' ),
			'permission_callback' => $auth,
			'args'                => array(
				'field'           => array( 'required' => true, 'type' => 'string', 'enum' => array_merge( Mixmymeals_Ingredient::NUTRITION_FIGURE_KEYS, array( 'price_per_100g', 'diet_type', 'cooking_factor' ) ) ),
				'type'            => array( 'required' => false, 'type' => 'string', 'default' => 'ingredient', 'enum' => array( 'ingredient', 'product' ) ),
				'mode'            => array( 'required' => false, 'type' => 'string', 'default' => 'effective', 'enum' => array( 'effective', 'own' ) ),
				'zero_is_missing' => array( 'required' => false, 'type' => 'boolean', 'default' => true ),
				'page'            => array( 'required' => false, 'type' => 'integer', 'default' => 1, 'minimum' => 1 ),
				'per_page'        => array( 'required' => false, 'type' => 'integer', 'default' => 50, 'minimum' => 1, 'maximum' => 200 ),
			),
		) );
		register_rest_route( 'mixmymeals/v1', '/units/bulk-delete', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'bulk_delete_ingr_units' ),
			'permission_callback' => $auth,
			'args'                => array(
				'ids'     => array(
					'required' => true,
					'type'     => 'array',
					'items'    => array( 'type' => 'integer' ),
				),
				'force'   => array( 'required' => false, 'type' => 'boolean', 'default' => false ),
				'dry_run' => array( 'required' => false, 'type' => 'boolean', 'default' => false ),
			),
		) );
		register_rest_route( 'mixmymeals/v1', '/units/(?P<id>\d+)/usage', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_ingr_unit_usage' ),
			'permission_callback' => $auth,
			'args'                => array( 'id' => $id_arg ),
		) );
		register_rest_route( 'mixmymeals/v1', '/units/(?P<id>\d+)', array(
			'methods'             => WP_REST_Server::DELETABLE,
			'callback'            => array( $this, 'delete_ingr_unit' ),
			'permission_callback' => $auth,
			'args'                => array(
				'id'    => $id_arg,
				'force' => array( 'required' => false, 'type' => 'boolean', 'default' => false ),
			),
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
				'divisor'        => array( 'required' => false, 'type' => 'integer', 'minimum' => 0 ),
			),
		) );

		// ── Products ──────────────────────────────────────────────────────
		register_rest_route( 'mixmymeals/v1', '/products', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'create_product' ),
			'permission_callback' => $auth,
			'args'                => array(
				'ingredient_id'  => array( 'required' => true,  'type' => 'integer', 'sanitize_callback' => 'absint' ),
				'title'          => array( 'required' => true,  'type' => 'string',  'sanitize_callback' => 'sanitize_text_field' ),
				// Required here too, or automation could create products the
				// admin screen then refuses to save until somebody fills the
				// brand in by hand.
				'brand'          => array( 'required' => true,  'type' => 'string',  'sanitize_callback' => 'sanitize_text_field' ),
				'package_weight' => array( 'required' => false, 'type' => 'number' ),
				'price'          => array( 'required' => false, 'type' => array( 'number', 'null' ), 'minimum' => 0 ),
				'cooking_factor' => array( 'required' => false, 'type' => array( 'number', 'null' ), 'minimum' => 0, 'exclusiveMinimum' => true, 'maximum' => Mixmymeals_Ingredient::COOKING_FACTOR_MAX ),
				'source'         => array( 'required' => false, 'type' => 'string',  'sanitize_callback' => 'sanitize_text_field' ),
				'diet_type'      => array( 'required' => false, 'type' => 'string',  'enum' => array_merge( Mixmymeals_Diet::axis_slugs( 'diet' ), array( '' ) ) ),
				'nutrition'      => array( 'required' => false, 'type' => 'object' ),
				'is_reference'   => array( 'required' => false, 'type' => 'boolean' ),
			),
		) );
		register_rest_route( 'mixmymeals/v1', '/ingredients/(?P<id>\d+)/products', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_ingredient_products' ),
			'permission_callback' => '__return_true',
			'args'                => array( 'id' => $id_arg ),
		) );
		register_rest_route( 'mixmymeals/v1', '/products/(?P<id>\d+)', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_product' ),
			'permission_callback' => '__return_true',
			'args'                => array( 'id' => $id_arg ),
		) );
		register_rest_route( 'mixmymeals/v1', '/products/(?P<id>\d+)', array(
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => array( $this, 'update_product' ),
			'permission_callback' => $auth,
			'args'                => array(
				'id'             => $id_arg,
				'title'          => array( 'required' => false, 'type' => 'string',  'sanitize_callback' => 'sanitize_text_field' ),
				'brand'          => array( 'required' => false, 'type' => 'string',  'sanitize_callback' => 'sanitize_text_field' ),
				'package_weight' => array( 'required' => false, 'type' => 'number' ),
				'price'          => array( 'required' => false, 'type' => array( 'number', 'null' ), 'minimum' => 0 ),
				'cooking_factor' => array( 'required' => false, 'type' => array( 'number', 'null' ), 'minimum' => 0, 'exclusiveMinimum' => true, 'maximum' => Mixmymeals_Ingredient::COOKING_FACTOR_MAX ),
				'source'         => array( 'required' => false, 'type' => 'string',  'sanitize_callback' => 'sanitize_text_field' ),
				'diet_type'      => array( 'required' => false, 'type' => 'string',  'enum' => array_merge( Mixmymeals_Diet::axis_slugs( 'diet' ), array( '' ) ) ),
				'nutrition'      => array( 'required' => false, 'type' => 'object' ),
				'is_reference'   => array( 'required' => false, 'type' => 'boolean' ),
			),
		) );
		/*
		 * One route for both frames. A recipe answers per serving, a plan per
		 * day and person, and a meal resolves to the recipe it plans — the
		 * same resolution the shortcode does, so an agent does not have to
		 * know which kind of post it is holding.
		 */
		register_rest_route( 'mixmymeals/v1', '/product-impact', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_product_impact' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'post_id' => array( 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ),
			),
		) );
		register_rest_route( 'mixmymeals/v1', '/products/(?P<id>\d+)', array(
			'methods'             => WP_REST_Server::DELETABLE,
			'callback'            => array( $this, 'delete_product' ),
			'permission_callback' => $auth,
			'args'                => array( 'id' => $id_arg ),
		) );

		// ── Meal Plans ────────────────────────────────────────────────────
		register_rest_route( 'mixmymeals/v1', '/meal-plans', array( 'methods' => WP_REST_Server::READABLE,  'callback' => array( $this, 'get_meal_plans' ),   'permission_callback' => '__return_true' ) );
		register_rest_route( 'mixmymeals/v1', '/meal-plans', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'create_meal_plan' ), 'permission_callback' => $auth, 'args' => array( 'title' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ), 'status' => array( 'required' => false, 'type' => 'string', 'default' => 'draft', 'enum' => array( 'publish', 'draft', 'pending', 'private' ) ), 'content' => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'wp_kses_post' ), 'excerpt' => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'wp_kses_post' ), 'start_day' => array( 'required' => false, 'type' => 'integer', 'minimum' => 1, 'maximum' => 7 ), 'end_day' => array( 'required' => false, 'type' => 'integer', 'minimum' => 1, 'maximum' => 7 ), 'categories' => array( 'required' => false, 'type' => 'array', 'items' => array( 'type' => 'integer' ) ), 'tags' => array( 'required' => false, 'type' => 'array', 'items' => array( 'type' => 'integer' ) ), 'author' => $author_arg, 'featured_image' => $featured_arg, 'image_credit' => $image_credit_arg ) ) );
		register_rest_route( 'mixmymeals/v1', '/meal-plans/(?P<id>\d+)', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_meal_plan' ),    'permission_callback' => '__return_true', 'args' => array( 'id' => $id_arg ) ) );
		register_rest_route( 'mixmymeals/v1', '/meal-plans/(?P<id>\d+)', array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'update_meal_plan' ), 'permission_callback' => $auth, 'args' => array( 'id' => $id_arg, 'title' => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ), 'status' => array( 'required' => false, 'type' => 'string', 'enum' => array( 'publish', 'draft', 'pending', 'private' ) ), 'content' => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'wp_kses_post' ), 'excerpt' => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'wp_kses_post' ), 'start_day' => array( 'required' => false, 'type' => 'integer', 'minimum' => 1, 'maximum' => 7 ), 'end_day' => array( 'required' => false, 'type' => 'integer', 'minimum' => 1, 'maximum' => 7 ), 'categories' => array( 'required' => false, 'type' => 'array', 'items' => array( 'type' => 'integer' ) ), 'tags' => array( 'required' => false, 'type' => 'array', 'items' => array( 'type' => 'integer' ) ), 'author' => $author_arg, 'featured_image' => $featured_arg, 'image_credit' => $image_credit_arg ) ) );
		register_rest_route( 'mixmymeals/v1', '/meal-plans/(?P<id>\d+)/meals',         array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_plan_meals' ),    'permission_callback' => '__return_true', 'args' => array( 'id' => $id_arg ) ) );
		register_rest_route( 'mixmymeals/v1', '/meal-plans/(?P<id>\d+)/shopping-list', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_shopping_list' ), 'permission_callback' => '__return_true', 'args' => array( 'id' => $id_arg ) ) );
		register_rest_route( 'mixmymeals/v1', '/meal-plans/(?P<id>\d+)/equipment',     array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_meal_plan_equipment' ), 'permission_callback' => '__return_true', 'args' => array( 'id' => $id_arg ) ) );
		$this->register_author_routes( 'meal-plans', $auth, $id_arg );

		// ── Equipment ─────────────────────────────────────────────────────
		register_rest_route( 'mixmymeals/v1', '/equipment', array( 'methods' => WP_REST_Server::READABLE,  'callback' => array( $this, 'get_equipment_items' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( 'mixmymeals/v1', '/equipment', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'create_equipment' ),    'permission_callback' => $auth, 'args' => array( 'title' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ), 'status' => array( 'required' => false, 'type' => 'string', 'default' => 'publish', 'enum' => array( 'publish', 'draft' ) ), 'content' => array( 'required' => false, 'type' => 'string' ), 'categories' => array( 'required' => false, 'type' => 'array', 'items' => array( 'type' => 'integer' ) ), 'affiliate_links' => array( 'required' => false, 'type' => 'array', 'items' => array( 'type' => 'object' ) ), 'featured_image' => array( 'required' => false, 'type' => 'integer' ) ) ) );
		register_rest_route( 'mixmymeals/v1', '/equipment/(?P<id>\d+)', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_equipment_item' ),  'permission_callback' => '__return_true', 'args' => array( 'id' => $id_arg ) ) );
		register_rest_route( 'mixmymeals/v1', '/equipment/(?P<id>\d+)', array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'update_equipment' ),     'permission_callback' => $auth, 'args' => array( 'id' => $id_arg, 'title' => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ), 'status' => array( 'required' => false, 'type' => 'string', 'enum' => array( 'publish', 'draft' ) ), 'categories' => array( 'required' => false, 'type' => 'array', 'items' => array( 'type' => 'integer' ) ), 'affiliate_links' => array( 'required' => false, 'type' => 'array', 'items' => array( 'type' => 'object' ) ), 'content' => array( 'required' => false, 'type' => 'string' ), 'featured_image' => array( 'required' => false, 'type' => 'integer' ) ) ) );

		// ── Meals ─────────────────────────────────────────────────────────
		register_rest_route( 'mixmymeals/v1', '/meals', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'create_meal' ), 'permission_callback' => $auth, 'args' => array( 'title' => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ), 'plan_id' => array( 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ), 'recipe_id' => array( 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ), 'servings' => array( 'required' => false, 'type' => 'integer', 'default' => 1 ), 'type' => array( 'required' => false, 'type' => 'string', 'enum' => array( 'breakfast', 'lunch', 'dinner', 'snack', 'other' ) ), 'text' => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field' ), 'diet' => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_key', 'description' => 'How the recipe is cooked: one of the meal\'s diet_options ("_none" = the original recipe, as written). Only where the recipe offers a choice, and then always settled — anything else, or nothing, stores the first option.' ), 'day' => array( 'required' => false, 'type' => 'integer', 'minimum' => 1, 'maximum' => 7 ), 'status' => array( 'required' => false, 'type' => 'string', 'default' => 'publish', 'enum' => array( 'publish', 'draft' ) ) ) ) );
		register_rest_route( 'mixmymeals/v1', '/meals/(?P<id>\d+)', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_meal' ),    'permission_callback' => '__return_true', 'args' => array( 'id' => $id_arg ) ) );
		register_rest_route( 'mixmymeals/v1', '/meals/(?P<id>\d+)', array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'update_meal' ), 'permission_callback' => $auth, 'args' => array( 'id' => $id_arg, 'plan_id' => array( 'required' => false, 'type' => 'integer', 'sanitize_callback' => 'absint' ), 'recipe_id' => array( 'required' => false, 'type' => 'integer', 'sanitize_callback' => 'absint' ), 'servings' => array( 'required' => false, 'type' => 'integer' ), 'type' => array( 'required' => false, 'type' => 'string', 'enum' => array( 'breakfast', 'lunch', 'dinner', 'snack', 'other' ) ), 'text' => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field' ), 'diet' => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_key', 'description' => 'How the recipe is cooked: one of the meal\'s diet_options ("_none" = the original recipe, as written). Only where the recipe offers a choice, and then always settled — anything else, or nothing, stores the first option.' ), 'day' => array( 'required' => false, 'type' => 'integer', 'minimum' => 1, 'maximum' => 7 ), 'status' => array( 'required' => false, 'type' => 'string', 'enum' => array( 'publish', 'draft' ) ) ) ) );
		register_rest_route( 'mixmymeals/v1', '/meals/(?P<id>\d+)/plan', array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'set_meal_plan' ), 'permission_callback' => $auth, 'args' => array( 'id' => $id_arg, 'plan_id' => array( 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ) ) ) );

		// ── AI convenience: build a whole plan in one call, bulk-add meals and
		// search recipes by planning-relevant criteria. ────────────────────────
		register_rest_route( 'mixmymeals/v1', '/meal-plans/build', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'build_meal_plan' ),
			'permission_callback' => $auth,
			'args'                => array(
				'title'      => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
				'status'     => array( 'required' => false, 'type' => 'string', 'default' => 'draft', 'enum' => array( 'publish', 'draft', 'pending', 'private' ) ),
				'content'    => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'wp_kses_post' ),
				'excerpt'    => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'wp_kses_post' ),
				'start_day'  => array( 'required' => false, 'type' => 'integer', 'minimum' => 1, 'maximum' => 7 ),
				'end_day'    => array( 'required' => false, 'type' => 'integer', 'minimum' => 1, 'maximum' => 7 ),
				'categories' => array( 'required' => false, 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
				'tags'       => array( 'required' => false, 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
				'meals'      => array( 'required' => false, 'type' => 'array' ),
				'author'     => $author_arg,
				'featured_image' => $featured_arg,
				'image_credit'   => $image_credit_arg,
			),
		) );
		register_rest_route( 'mixmymeals/v1', '/meal-plans/(?P<id>\d+)/meals/bulk', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'add_meals_bulk' ),
			'permission_callback' => $auth,
			'args'                => array(
				'id'    => $id_arg,
				'meals' => array( 'required' => true, 'type' => 'array' ),
			),
		) );
		register_rest_route( 'mixmymeals/v1', '/posts/(?P<id>\d+)/status', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'set_post_status' ),
			'permission_callback' => static function ( $request ) {
				$id = (int) $request['id'];
				return $id
					&& current_user_can( 'edit_post', $id )
					&& in_array( get_post_type( $id ), array( 'mmm_meal_plan', 'mmm_recipe' ), true );
			},
			'args'                => array(
				'id'     => $id_arg,
				'status' => array( 'required' => true, 'type' => 'string', 'enum' => array( 'draft', 'publish', 'trash' ) ),
			),
		) );
		// ── Media ─────────────────────────────────────────────────────────
		register_rest_route( 'mixmymeals/v1', '/media', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'upload_media' ),
			'permission_callback' => $auth,
			'args'                => array(
				'url'           => array( 'required' => false, 'type' => 'string', 'description' => 'Image URL fetched server-side. Alternative to a multipart "file" field.' ),
				'attachment_id' => array( 'required' => false, 'type' => 'integer', 'description' => 'Use an image already in the library instead of uploading one.' ),
				'post_id'       => array( 'required' => false, 'type' => 'integer', 'description' => 'Recipe, plan or equipment the image belongs to; becomes the attachment’s parent.' ),
				'featured'      => array( 'required' => false, 'type' => 'boolean', 'default' => false, 'description' => 'Set the image as the featured image of post_id.' ),
				'title'         => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
				'alt'           => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
			),
		) );

		register_rest_route( 'mixmymeals/v1', '/recipes/search', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'search_recipes' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'search'       => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
				'diet'         => array( 'required' => false, 'type' => 'string', 'enum' => Mixmymeals_Diet::axis_slugs( 'diet' ) ),
				'category'     => array( 'required' => false, 'type' => 'integer', 'sanitize_callback' => 'absint' ),
				'tag'          => array( 'required' => false, 'type' => 'integer', 'sanitize_callback' => 'absint' ),
				'min_calories' => array( 'required' => false, 'type' => 'number' ),
				'max_calories' => array( 'required' => false, 'type' => 'number' ),
				'max_prep'     => array( 'required' => false, 'type' => 'integer' ),
				'per_page'     => array( 'required' => false, 'type' => 'integer', 'default' => 50 ),
			),
		) );
	}

	// =========================================================
	// HELPERS
	// =========================================================

	public function check_edit_permission(): bool {
		return current_user_can( 'edit_others_posts' );
	}

	/**
	 * Object-level write guard: the current user must be able to edit THIS post.
	 *
	 * @param int $id Post ID.
	 * @return WP_Error|null WP_Error (403) when not allowed, otherwise null.
	 */
	private function require_can_edit( int $id ): ?WP_Error {
		if ( ! current_user_can( 'edit_post', $id ) ) {
			return new WP_Error( 'forbidden', 'Access denied.', array( 'status' => 403 ) );
		}
		return null;
	}

	/**
	 * Publish guard: moving a post to a public status ('publish'/'private')
	 * requires the publish_posts capability.
	 *
	 * @param string|null $status Requested post status.
	 * @return WP_Error|null WP_Error (403) when not allowed, otherwise null.
	 */
	private function require_can_publish( ?string $status ): ?WP_Error {
		if ( in_array( $status, array( 'publish', 'private' ), true ) && ! current_user_can( 'publish_posts' ) ) {
			return new WP_Error( 'forbidden', 'You are not allowed to publish content.', array( 'status' => 403 ) );
		}
		return null;
	}

	private function serialize_product( int $id ): array {
		$product    = new Mixmymeals_Product( $id );
		$ingredient = $product->get_ingredient_id();

		return array(
			'id'             => $id,
			'title'          => $product->get_title(),
			'parent'         => $ingredient,
			'brand'          => $product->get_brand(),
			'package_weight' => $product->get_package_weight(),
			// Per package, null when unrecorded. price_per_100g is derived and
			// stays null until a package weight is stated as well.
			'price'          => $product->get_price(),
			'price_per_100g' => $product->get_price_per_100g(),
			// null: the packet says nothing and the ingredient's factor stands.
			'cooking_factor' => $product->get_cooking_factor(),
			'source'         => $product->get_source(),
			// '' means the packet was not checked and the ingredient's own
			// classification stands, which a client must be able to tell from
			// a positive "not vegan".
			'diet_type'      => $product->get_diet_type(),
			'diets'          => $product->get_diets(),
			/*
			 * Raw, with nulls kept: null means the packet does not state the
			 * value, which is a different claim from zero. A client merging
			 * this into the ingredient has to be able to tell them apart.
			 */
			'nutrition'      => $product->get_nutrition(),
			'is_reference'   => Mixmymeals_Product::get_reference_id( $ingredient ) === $id,
		);
	}

	/**
	 * Applies the writable product fields a request carries.
	 *
	 * Shared by create and update so both treat a supplied null the same way:
	 * as "delete this value", not as "ignore it".
	 *
	 * @param Mixmymeals_Product $product Product.
	 * @param WP_REST_Request    $request Request.
	 * @return void
	 */
	private function apply_product_fields( Mixmymeals_Product $product, WP_REST_Request $request ): void {

		// Blanking a brand is a loss, not an edit; the field is required.
		if ( $request->has_param( 'brand' ) && '' !== trim( (string) $request->get_param( 'brand' ) ) ) {
			$product->set_brand( trim( (string) $request->get_param( 'brand' ) ) );
		}
		if ( $request->has_param( 'source' ) )         $product->set_source( (string) $request->get_param( 'source' ) );
		if ( $request->has_param( 'package_weight' ) ) $product->set_package_weight( (float) $request->get_param( 'package_weight' ) );
		// null clears; the cast only after the null check, or it would read 0.
		if ( $request->has_param( 'price' ) ) {
			$price = $request->get_param( 'price' );
			$product->set_price( null === $price ? null : (float) $price );
		}
		if ( $request->has_param( 'cooking_factor' ) ) {
			$factor = $request->get_param( 'cooking_factor' );
			$product->set_cooking_factor( null === $factor ? null : (float) $factor );
		}
		if ( $request->has_param( 'diet_type' ) )      $product->set_diet_type( (string) $request->get_param( 'diet_type' ) );
		if ( $request->has_param( 'diets' ) )          $product->set_diets( (array) $request->get_param( 'diets' ) );

		if ( $request->has_param( 'nutrition' ) ) {
			$nutrition = (array) $request->get_param( 'nutrition' );
			$clean     = array();

			foreach ( array_keys( Mixmymeals_Product::NUTRITION_FIELDS ) as $key ) {
				if ( array_key_exists( $key, $nutrition ) ) {
					$clean[ $key ] = $nutrition[ $key ];
				}
			}

			$product->set_nutrition( $clean );
		}

		if ( $request->has_param( 'is_reference' ) ) {
			Mixmymeals_Product::set_reference_id(
				$product->get_ingredient_id(),
				$request->get_param( 'is_reference' ) ? $product->get_id() : 0
			);
		}
	}

	/**
	 * What choosing a different product would do, for a recipe, a meal or a
	 * whole plan.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_product_impact( WP_REST_Request $request ): WP_REST_Response|WP_Error {

		$post_id = (int) $request->get_param( 'post_id' );
		$type    = get_post_type( $post_id );
		$recipe  = null;

		if ( 'mmm_meal' === $type ) {
			// As the meal cooks it, the way [mmm_meal_product_impact] reads it.
			$recipe  = ( new Mixmymeals_Meal( $post_id ) )->get_recipe();
			$post_id = $recipe->get_id();
			$type    = get_post_type( $post_id );
		}

		if ( 'mmm_recipe' === $type ) {
			$recipe = $recipe ?? new Mixmymeals_Recipe( $post_id );

			if ( ! $recipe->is_valid() ) {
				return new WP_Error( 'mmm_not_found', __( 'Recipe not found.', 'mixmymeals' ), array( 'status' => 404 ) );
			}

			return new WP_REST_Response(
				array(
					'post_id' => $post_id,
					'unit'    => 'per_serving',
					'groups'  => $recipe->calc_product_impact(),
				),
				200
			);
		}

		if ( 'mmm_meal_plan' === $type ) {
			$plan = new Mixmymeals_Meal_Plan( $post_id );

			return new WP_REST_Response(
				array(
					'post_id' => $post_id,
					'unit'    => 'per_day_person',
					'groups'  => $plan->calc_product_impact(),
				),
				200
			);
		}

		return new WP_Error(
			'mmm_bad_type',
			__( 'Expected a recipe, a meal or a meal plan.', 'mixmymeals' ),
			array( 'status' => 400 )
		);
	}

	public function create_product( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$ingredient_id = absint( $request->get_param( 'ingredient_id' ) );

		if ( ! ( new Mixmymeals_Ingredient( $ingredient_id ) )->is_valid() ) {
			return new WP_Error( 'not_found', 'Ingredient not found.', array( 'status' => 404 ) );
		}

		$guard = $this->require_can_edit( $ingredient_id );
		if ( $guard ) return $guard;

		try {
			$product = Mixmymeals_Product::create( $ingredient_id, $request->get_param( 'title' ) );
		} catch ( \Exception $e ) {
			return new WP_Error( 'create_failed', $e->getMessage(), array( 'status' => 500 ) );
		}

		$this->apply_product_fields( $product, $request );

		return new WP_REST_Response( $this->serialize_product( $product->get_id() ), 201 );
	}

	public function get_product( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id      = absint( $request->get_param( 'id' ) );
		$product = new Mixmymeals_Product( $id );

		if ( ! $product->is_valid() ) {
			return new WP_Error( 'not_found', 'Product not found.', array( 'status' => 404 ) );
		}

		return new WP_REST_Response( $this->serialize_product( $id ), 200 );
	}

	public function get_ingredient_products( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$ingredient_id = absint( $request->get_param( 'id' ) );

		if ( ! ( new Mixmymeals_Ingredient( $ingredient_id ) )->is_valid() ) {
			return new WP_Error( 'not_found', 'Ingredient not found.', array( 'status' => 404 ) );
		}

		$out = array();

		foreach ( Mixmymeals_Product::for_ingredient( $ingredient_id ) as $product ) {
			$out[] = $this->serialize_product( $product->get_id() );
		}

		return new WP_REST_Response( $out, 200 );
	}

	public function update_product( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id      = absint( $request->get_param( 'id' ) );
		$product = new Mixmymeals_Product( $id );

		if ( ! $product->is_valid() ) {
			return new WP_Error( 'not_found', 'Product not found.', array( 'status' => 404 ) );
		}

		$guard = $this->require_can_edit( $product->get_ingredient_id() );
		if ( $guard ) return $guard;

		if ( $request->has_param( 'title' ) ) {
			$product->set_title( (string) $request->get_param( 'title' ) );
		}

		$this->apply_product_fields( $product, $request );

		return new WP_REST_Response( $this->serialize_product( $id ), 200 );
	}

	public function delete_product( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id      = absint( $request->get_param( 'id' ) );
		$product = new Mixmymeals_Product( $id );

		if ( ! $product->is_valid() ) {
			return new WP_Error( 'not_found', 'Product not found.', array( 'status' => 404 ) );
		}

		$guard = $this->require_can_edit( $product->get_ingredient_id() );
		if ( $guard ) return $guard;

		// Clears the ingredient's reference too, if it pointed here.
		$product->delete();

		return new WP_REST_Response( array( 'deleted' => true, 'id' => $id ), 200 );
	}

	private function serialize_ingr_unit( int $id ): array {
		$unit = new Mixmymeals_Ingredient_Amount( $id );
		return array(
			'id'             => $id,
			'title'          => $unit->get_title(),
			'parent'         => $unit->get_parent_ingredient_id(),
			'weight_g'       => $unit->get_weight(),
			'is_indivisible' => $unit->is_indivisible(),
			'divisor'        => $unit->get_divisor(),
			'step_divisor'   => $unit->get_step_divisor(),
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
			/*
			 * null means nobody has said, 0 means zero days. Kept apart here
			 * too — a client that receives 0 for both cannot tell a dish that
			 * must be eaten at once from one nobody has assessed.
			 */
			'shelf_life_days'  => $recipe->get_shelf_life(),
			// '' = not specified. Paired with the shelf life above: how long it
			// keeps is only an answer under a stated condition.
			'storage_type'     => $recipe->get_storage_type(),
			'priority'         => $recipe->get_priority(),
			'steps'            => $recipe->get_steps(),
			'equipment'        => $recipe->get_equipment(),
			'related_recipes'  => $recipe->get_related_recipes(),
			'related_recipes_reverse' => $recipe->get_reverse_related_recipes(),
			'meal_plans'       => $recipe->get_meal_plans(),
			'diet_type'        => $recipe->get_diet_type(),
			'diets'            => $recipe->get_diets(),
			'diet_suitability' => $recipe->get_diet_suitabilities(),
			'categories'       => array_map( fn( $t ) => $t->term_id, $recipe->get_categories() ),
			'tags'             => wp_get_post_terms( $post_id, 'mmm_recipe_tag', array( 'fields' => 'ids' ) ),
			'author'           => $this->serialize_author( $post_id ),
			'featured_image'     => $this->featured_image_id( $post_id ),
			'featured_image_url' => $this->featured_image_url( $post_id ),
			'image_credit'       => $this->serialize_image_credit( $post_id ),
		);
	}

	private function serialize_ingredient( int $post_id ): array {
		$ing = new Mixmymeals_Ingredient( $post_id );
		return array(
			'id'             => $post_id,
			'title'          => get_the_title( $post_id ),
			/*
			 * Resolved values — already through the reference product where
			 * there is one. own_* below is what the ingredient itself holds,
			 * so a client can see which layer a figure came from instead of
			 * guessing. It is a read-only view of the fallback, never "the"
			 * nutrition; that is always the resolved one above.
			 */
			'diet_type'      => $ing->get_diet_type(),
			'diets'          => $ing->get_diets(),
			'diet_suitability' => $ing->get_diet_suitabilities(),
			// null = not specified, 0 = keeps zero days. Not the same as the
			// recipe's shelf life, which is a different quantity entirely.
			'shelf_life_days' => $ing->get_shelf_life(),
			'storage_type'    => $ing->get_storage_type(),
			'default_unit'   => $ing->get_default_unit(),
			'price_per_100g' => $ing->get_price_per_100g(),
			'nutrition'      => $ing->get_nutrition(),
			'categories'     => array_map( fn( $t ) => $t->term_id, $ing->get_categories() ),
			'units'          => array_map( fn( $u ) => $this->serialize_ingr_unit( $u->ID ), $ing->get_units() ),
			'reference_product'       => Mixmymeals_Product::get_reference_id( $post_id ),
			'reference_product_label' => Mixmymeals_Product::reference_label( $post_id ),
			'product_count'           => count( Mixmymeals_Product::for_ingredient( $post_id ) ),
			'own_nutrition'           => $ing->get_nutrition_with_product( 0 ),
			'own_price_per_100g'      => $ing->get_own_price_per_100g(),
			// Resolved through the reference product like the price; own_* is
			// what the ingredient itself states, and the only one writable.
			// null: no correction.
			'cooking_factor'          => $ing->get_cooking_factor(),
			'own_cooking_factor'      => $ing->get_own_cooking_factor(),
			/*
			 * Was die Zutat selbst sagt, im Gegensatz zu `diet_type`, das über
			 * das Referenzprodukt auflöst. Gelesen über die Taxonomie, nicht
			 * mehr aus `_mmm_diet_type`: seit der Umstellung auf Terme stand
			 * hier bei jeder migrierten Zutat '' — die Einstufung lag im Term,
			 * das Feld las die alte Zeile. Es behauptete damit ausgerechnet
			 * das Gegenteil dessen, wofür es da ist. Der Rueckfall auf das Meta
			 * steckt in Mixmymeals_Diet::for_post() und gilt weiter für alles,
			 * was nie angefasst wurde.
			 */
			'own_diet_type'           => Mixmymeals_Diet::strongest( Mixmymeals_Diet::for_post( $post_id ), 'diet' ),
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
			// The planning's own note. Never the recipe's excerpt: an empty
			// one means nobody said anything about this planning.
			'text'      => $meal->get_text(),
			// How the recipe is cooked for this planning: one of diet_options
			// ("_none" = the original recipe, as written), always set where there are options, ''
			// where the recipe offers none. diet_applied is the diet whose
			// packets were bought, '' for the recipe as written.
			'diet'         => $meal->get_diet(),
			'diet_applied' => $meal->get_recipe()->get_implemented_diet(),
			'diet_options' => ( new Mixmymeals_Recipe( $meal->get_recipe_id() ) )->get_diet_choices(),
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
			'diets'      => $plan->get_diets(),
			'diet_suitability' => $plan->get_diets( true ),
			'author'     => $this->serialize_author( $post_id ),
			'featured_image'     => $this->featured_image_id( $post_id ),
			'featured_image_url' => $this->featured_image_url( $post_id ),
			'image_credit'       => $this->serialize_image_credit( $post_id ),
			'meals'      => $meals,
		);
	}

	// =========================================================
	// AUTHOR BOX
	//
	// The freely editable author credit that recipes and meal plans share (see
	// Mixmymeals_Post_Meta). Reachable two ways: as an 'author' object on any
	// recipe/plan write, and as a sub-resource under …/author for editing the
	// credit of an existing post without touching the rest of it.
	//
	// Writes MERGE: only supplied keys change, an empty string clears a single
	// field, DELETE clears the whole box so the post falls back to its
	// WordPress author.
	// =========================================================

	/**
	 * Field schema of the author box, shared by the embedded 'author' object
	 * and the …/author sub-resource so both accept exactly the same input.
	 *
	 * No 'format' => 'uri' on the URL: '' has to stay valid, that is how a
	 * client clears the link.
	 *
	 * @return array
	 */
	private static function author_schema_properties(): array {
		return array(
			'name'      => array( 'type' => 'string', 'description' => 'Name of the external author. Empty falls back to the WordPress author.' ),
			'text'      => array( 'type' => 'string', 'description' => 'Free-form bio, limited HTML allowed.' ),
			'url'       => array( 'type' => 'string', 'description' => 'Link target of the author box.' ),
			'link_text' => array( 'type' => 'string', 'description' => 'Link label. Empty falls back to the host of the URL.' ),
			'nofollow'  => array( 'type' => 'boolean', 'description' => 'Whether the link carries rel="nofollow".' ),
			'social'    => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'URLs and e-mail addresses; the icon is derived from each entry.' ),
			'image'     => array( 'type' => 'integer', 'description' => 'Attachment ID of the profile picture, 0 removes it. Rendered like a WordPress author’s avatar; only used together with a name.' ),
		);
	}

	/**
	 * Registers GET/POST/PUT/PATCH/DELETE …/author for one post type.
	 *
	 * @param string   $base      Route base, 'recipes' or 'meal-plans'.
	 * @param callable $auth      Permission callback for writes.
	 * @param array    $id_arg    Shared id argument definition.
	 * @return void
	 */
	private function register_author_routes( string $base, $auth, array $id_arg ): void {

		$post_type = 'recipes' === $base ? 'mmm_recipe' : 'mmm_meal_plan';

		$write_args = array( 'id' => $id_arg );
		foreach ( self::author_schema_properties() as $key => $schema ) {
			$write_args[ $key ] = array( 'required' => false ) + $schema;
		}

		register_rest_route( 'mixmymeals/v1', '/' . $base . '/(?P<id>\d+)/author', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => fn( WP_REST_Request $request ) => $this->get_author( $request, $post_type ),
				'permission_callback' => '__return_true',
				'args'                => array( 'id' => $id_arg ),
			),
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => fn( WP_REST_Request $request ) => $this->update_author( $request, $post_type ),
				'permission_callback' => $auth,
				'args'                => $write_args,
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => fn( WP_REST_Request $request ) => $this->delete_author( $request, $post_type ),
				'permission_callback' => $auth,
				'args'                => array( 'id' => $id_arg ),
			),
		) );
	}

	/**
	 * Author box of a post as returned by the API.
	 *
	 * 'link_text' is the stored value, 'link_text_display' the one actually
	 * rendered (host of the URL when no label is set) — a client that only saw
	 * the resolved value would write the host back as if it were typed in.
	 * 'image' is the writable attachment ID, 'image_url' its read-only preview.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	private function serialize_author( int $post_id ): array {

		$data = Mixmymeals_Post_Meta::get_author_data( $post_id );

		return array(
			'name'              => $data['name'],
			'text'              => $data['text'],
			'url'               => $data['url'],
			'link_text'         => $data['link_text'],
			'link_text_display' => Mixmymeals_Post_Meta::get_author_link_text( $post_id ),
			'nofollow'          => $data['nofollow'],
			'social'            => $data['social'],
			'image'             => $data['image'],
			'image_url'         => Mixmymeals_Post_Meta::get_author_image_url( $post_id ),
			'is_external'       => Mixmymeals_Post_Meta::has_external_author( $post_id ),
		);
	}

	/**
	 * Merges supplied fields into the stored author box. Keys that are absent
	 * keep their value — save_author_data() itself is destructive.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $fields  Subset of the author schema.
	 * @return void
	 */
	private function apply_author( int $post_id, array $fields ): void {

		$data = Mixmymeals_Post_Meta::get_author_data( $post_id );

		foreach ( array_keys( self::author_schema_properties() ) as $key ) {
			if ( array_key_exists( $key, $fields ) ) {
				$data[ $key ] = $fields[ $key ];
			}
		}

		Mixmymeals_Post_Meta::save_author_data( $post_id, $data );
	}

	/**
	 * Resolves and guards the post behind an …/author request.
	 *
	 * @param int    $id        Post ID.
	 * @param string $post_type Expected post type.
	 * @param bool   $write     True for write access, false for read.
	 * @return WP_Post|WP_Error
	 */
	private function author_post( int $id, string $post_type, bool $write ): WP_Post|WP_Error {

		$post = get_post( $id );

		if ( ! $post || $post_type !== $post->post_type ) {
			return new WP_Error( 'not_found', 'Post not found.', array( 'status' => 404 ) );
		}

		if ( $write ) {
			return $this->require_can_edit( $id ) ?? $post;
		}

		if ( 'publish' !== $post->post_status && ! current_user_can( 'edit_post', $id ) ) {
			return new WP_Error( 'forbidden', 'Access denied.', array( 'status' => 403 ) );
		}

		return $post;
	}

	/**
	 * GET …/author
	 *
	 * @param WP_REST_Request $request   Request with the post 'id'.
	 * @param string          $post_type Expected post type.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_author( WP_REST_Request $request, string $post_type ): WP_REST_Response|WP_Error {
		$id   = (int) $request->get_param( 'id' );
		$post = $this->author_post( $id, $post_type, false );
		if ( is_wp_error( $post ) ) return $post;
		return new WP_REST_Response( $this->serialize_author( $id ), 200 );
	}

	/**
	 * POST/PUT/PATCH …/author — merges the supplied fields.
	 *
	 * @param WP_REST_Request $request   Request with the post 'id' and author fields.
	 * @param string          $post_type Expected post type.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_author( WP_REST_Request $request, string $post_type ): WP_REST_Response|WP_Error {

		$id   = (int) $request->get_param( 'id' );
		$post = $this->author_post( $id, $post_type, true );
		if ( is_wp_error( $post ) ) return $post;

		$fields = array();
		foreach ( array_keys( self::author_schema_properties() ) as $key ) {
			if ( $request->has_param( $key ) ) {
				$fields[ $key ] = $request->get_param( $key );
			}
		}

		$this->apply_author( $id, $fields );

		return new WP_REST_Response( $this->serialize_author( $id ), 200 );
	}

	/**
	 * DELETE …/author — clears the box, so the post credits its WordPress
	 * author again.
	 *
	 * @param WP_REST_Request $request   Request with the post 'id'.
	 * @param string          $post_type Expected post type.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_author( WP_REST_Request $request, string $post_type ): WP_REST_Response|WP_Error {

		$id   = (int) $request->get_param( 'id' );
		$post = $this->author_post( $id, $post_type, true );
		if ( is_wp_error( $post ) ) return $post;

		Mixmymeals_Post_Meta::save_author_data( $id, array() );

		return new WP_REST_Response( $this->serialize_author( $id ), 200 );
	}

	// =========================================================
	// MEDIA
	//
	// Getting an image into WordPress from a client that has no browser: a
	// multipart upload, a URL the server fetches, or an attachment that is
	// already in the library. All three end in the media library through core
	// (MIME check, intermediate sizes, metadata) — this only routes to it and
	// can hang the result on a recipe or plan as its featured image.
	// =========================================================

	/**
	 * Post types whose featured image this API manages.
	 *
	 * @var string[]
	 */
	private const IMAGE_POST_TYPES = array( 'mmm_recipe', 'mmm_meal_plan', 'mmm_equipment' );

	/**
	 * Sets or clears a post's featured image.
	 *
	 * 0 removes it. An ID that is not an attachment is ignored rather than
	 * stored, so a typo cannot leave a post pointing at a dangling thumbnail.
	 *
	 * @param int   $post_id Post ID.
	 * @param mixed $value   Attachment ID, or 0 to remove.
	 * @return void
	 */
	private function apply_featured_image( int $post_id, $value ): void {

		$thumb_id = absint( $value );

		if ( 0 === $thumb_id ) {
			delete_post_thumbnail( $post_id );
			return;
		}

		if ( 'attachment' === get_post_type( $thumb_id ) ) {
			set_post_thumbnail( $post_id, $thumb_id );
		}
	}

	/**
	 * Credit for a post's featured image, including the assembled line the
	 * frontend prints, so a client does not have to rebuild the format.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	private function serialize_image_credit( int $post_id ): array {

		$data = Mixmymeals_Post_Meta::get_image_credit( $post_id );

		$data['text']       = Mixmymeals_Post_Meta::get_image_credit_text( $post_id );
		$data['has_credit'] = Mixmymeals_Post_Meta::has_image_credit( $post_id );

		return $data;
	}

	/**
	 * Merges supplied fields into the stored image credit. Keys that are
	 * absent keep their value — save_image_credit() itself is
	 * destructive, exactly like the author box.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $fields  Subset of creator, source, license, url.
	 * @return void
	 */
	private function apply_image_credit( int $post_id, array $fields ): void {

		$data = Mixmymeals_Post_Meta::get_image_credit( $post_id );

		foreach ( array_keys( Mixmymeals_Post_Meta::IMAGE_CREDIT_FIELDS ) as $key ) {
			if ( array_key_exists( $key, $fields ) ) {
				$data[ $key ] = $fields[ $key ];
			}
		}

		Mixmymeals_Post_Meta::save_image_credit( $post_id, $data );
	}

	/**
	 * Featured image of a post, or null when it has none.
	 *
	 * @param int $post_id Post ID.
	 * @return int|null
	 */
	private function featured_image_id( int $post_id ): ?int {
		$thumb_id = (int) get_post_thumbnail_id( $post_id );
		return $thumb_id > 0 ? $thumb_id : null;
	}

	/**
	 * URL of the featured image at full size, or null when there is none.
	 *
	 * @param int $post_id Post ID.
	 * @return string|null
	 */
	private function featured_image_url( int $post_id ): ?string {
		$thumb_id = $this->featured_image_id( $post_id );
		if ( null === $thumb_id ) {
			return null;
		}
		$url = wp_get_attachment_url( $thumb_id );
		return $url ? $url : null;
	}

	/**
	 * Attachment as returned by the API.
	 *
	 * @param int $id Attachment ID.
	 * @return array
	 */
	private function serialize_attachment( int $id ): array {

		$meta = wp_get_attachment_metadata( $id );

		return array(
			'id'     => $id,
			'url'    => (string) wp_get_attachment_url( $id ),
			'title'  => get_the_title( $id ),
			'alt'    => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
			'mime'   => (string) get_post_mime_type( $id ),
			'width'  => isset( $meta['width'] ) ? (int) $meta['width'] : null,
			'height' => isset( $meta['height'] ) ? (int) $meta['height'] : null,
		);
	}

	/**
	 * POST /media — puts an image into the library and optionally makes it the
	 * featured image of a recipe or plan.
	 *
	 * Exactly one source: 'file' (multipart), 'url' (fetched server-side) or
	 * 'attachment_id' (already in the library).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function upload_media( WP_REST_Request $request ): WP_REST_Response|WP_Error {

		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error( 'forbidden', 'You are not allowed to upload files.', array( 'status' => 403 ) );
		}

		// Target post, if the image is meant to land on one.
		$post_id = absint( $request->get_param( 'post_id' ) );

		if ( $post_id ) {
			$post = get_post( $post_id );

			if ( ! $post || ! in_array( $post->post_type, self::IMAGE_POST_TYPES, true ) ) {
				return new WP_Error( 'not_found', 'Post not found.', array( 'status' => 404 ) );
			}

			$guard = $this->require_can_edit( $post_id );
			if ( $guard ) {
				return $guard;
			}
		}

		// media_handle_upload()/media_sideload_image() live in wp-admin.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$files         = $request->get_file_params();
		$url           = trim( (string) $request->get_param( 'url' ) );
		$attachment_id = absint( $request->get_param( 'attachment_id' ) );
		$title         = (string) $request->get_param( 'title' );

		if ( ! empty( $files['file'] ) ) {
			$new_id = media_handle_upload( 'file', $post_id );
		} elseif ( '' !== $url ) {
			$new_id = media_sideload_image( $url, $post_id, '' !== $title ? $title : null, 'id' );
		} elseif ( $attachment_id ) {
			if ( 'attachment' !== get_post_type( $attachment_id ) ) {
				return new WP_Error( 'not_found', 'Attachment not found.', array( 'status' => 404 ) );
			}
			$new_id = $attachment_id;
		} else {
			return new WP_Error(
				'no_image',
				'Provide one of: file (multipart upload), url, attachment_id.',
				array( 'status' => 400 )
			);
		}

		if ( is_wp_error( $new_id ) ) {
			// Rejected uploads (wrong MIME, too large, unreachable URL) are the
			// client's problem, not a server fault.
			return new WP_Error( 'upload_failed', $new_id->get_error_message(), array( 'status' => 400 ) );
		}

		$new_id = (int) $new_id;

		if ( '' !== $title ) {
			wp_update_post( array( 'ID' => $new_id, 'post_title' => sanitize_text_field( $title ) ) );
		}

		if ( $request->has_param( 'alt' ) ) {
			update_post_meta( $new_id, '_wp_attachment_image_alt', sanitize_text_field( (string) $request->get_param( 'alt' ) ) );
		}

		$response             = $this->serialize_attachment( $new_id );
		$response['featured_for'] = null;

		if ( $post_id && $request->get_param( 'featured' ) ) {
			set_post_thumbnail( $post_id, $new_id );
			$response['featured_for'] = $post_id;
		}

		return new WP_REST_Response( $response, 201 );
	}

	// =========================================================
	// RECIPE CALLBACKS
	// =========================================================

	public function get_recipes( WP_REST_Request $request ): WP_REST_Response {
		$status = $request->get_param( 'status' );
		if ( 'publish' !== $status && ! current_user_can( 'edit_others_posts' ) ) {
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
		$guard = $this->require_can_publish( $request->get_param( 'status' ) ?? 'draft' );
		if ( $guard ) return $guard;
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
		// null clears the field; 0 is a value. absint() would collapse both.
		if ( $request->has_param( 'shelf_life_days' ) ) {
			$mmm_shelf = $request->get_param( 'shelf_life_days' );
			$recipe->set_shelf_life( null === $mmm_shelf ? null : absint( $mmm_shelf ) );
		}
		if ( $request->has_param( 'storage_type' ) ) $recipe->set_storage_type( (string) $request->get_param( 'storage_type' ) );
		if ( $request->has_param( 'priority' ) )         $recipe->set_priority( absint( $request->get_param( 'priority' ) ) );
		if ( $request->has_param( 'steps' ) )            $recipe->set_steps( $request->get_param( 'steps' ) );
		if ( $request->has_param( 'equipment' ) )        $recipe->set_equipment( $request->get_param( 'equipment' ) );
		if ( $request->has_param( 'related_recipes' ) )  $recipe->set_related_recipes( $request->get_param( 'related_recipes' ) );
		if ( $request->has_param( 'categories' ) )       wp_set_post_terms( $recipe->get_id(), $request->get_param( 'categories' ), 'mmm_recipe_category' );
		if ( $request->has_param( 'tags' ) )             wp_set_post_terms( $recipe->get_id(), $request->get_param( 'tags' ), 'mmm_recipe_tag' );
		if ( $request->has_param( 'author' ) )           $this->apply_author( $recipe->get_id(), (array) $request->get_param( 'author' ) );
		if ( $request->has_param( 'featured_image' ) )   $this->apply_featured_image( $recipe->get_id(), $request->get_param( 'featured_image' ) );
		if ( $request->has_param( 'image_credit' ) )    $this->apply_image_credit( $recipe->get_id(), (array) $request->get_param( 'image_credit' ) );
		return new WP_REST_Response( $this->serialize_recipe( $recipe->get_id() ), 201 );
	}

	public function get_recipe( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id   = $request->get_param( 'id' );
		$post = get_post( $id );
		if ( ! $post || 'mmm_recipe' !== $post->post_type ) return new WP_Error( 'not_found', 'Recipe not found.', array( 'status' => 404 ) );
		if ( 'publish' !== $post->post_status && ! current_user_can( 'edit_post', $id ) ) return new WP_Error( 'forbidden', 'Access denied.', array( 'status' => 403 ) );
		return new WP_REST_Response( $this->serialize_recipe( $id ), 200 );
	}

	public function update_recipe( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id = $request->get_param( 'id' );
		if ( ! Mixmymeals_Recipe::exists_by_id( $id ) ) return new WP_Error( 'not_found', 'Recipe not found.', array( 'status' => 404 ) );
		$guard = $this->require_can_edit( $id ) ?? $this->require_can_publish( $request->get_param( 'status' ) );
		if ( $guard ) return $guard;
		$update = array( 'ID' => $id );
		if ( $request->has_param( 'title' ) )   $update['post_title']   = $request->get_param( 'title' );
		if ( $request->has_param( 'status' ) )  $update['post_status']  = $request->get_param( 'status' );
		if ( $request->has_param( 'excerpt' ) ) $update['post_excerpt'] = $request->get_param( 'excerpt' );
		if ( count( $update ) > 1 )             wp_update_post( $update );
		$recipe = new Mixmymeals_Recipe( $id );
		if ( $request->has_param( 'servings' ) )         $recipe->set_servings( floatval( $request->get_param( 'servings' ) ) );
		if ( $request->has_param( 'preparation_time' ) ) $recipe->set_preparation_time( absint( $request->get_param( 'preparation_time' ) ) );
		// null clears the field; 0 is a value. absint() would collapse both.
		if ( $request->has_param( 'shelf_life_days' ) ) {
			$mmm_shelf = $request->get_param( 'shelf_life_days' );
			$recipe->set_shelf_life( null === $mmm_shelf ? null : absint( $mmm_shelf ) );
		}
		if ( $request->has_param( 'storage_type' ) ) $recipe->set_storage_type( (string) $request->get_param( 'storage_type' ) );
		if ( $request->has_param( 'priority' ) )         $recipe->set_priority( absint( $request->get_param( 'priority' ) ) );
		if ( $request->has_param( 'steps' ) )            $recipe->set_steps( $request->get_param( 'steps' ) );
		if ( $request->has_param( 'equipment' ) )        $recipe->set_equipment( $request->get_param( 'equipment' ) );
		if ( $request->has_param( 'related_recipes' ) )  $recipe->set_related_recipes( $request->get_param( 'related_recipes' ) );
		if ( $request->has_param( 'categories' ) )       wp_set_post_terms( $id, $request->get_param( 'categories' ), 'mmm_recipe_category' );
		if ( $request->has_param( 'tags' ) )             wp_set_post_terms( $id, $request->get_param( 'tags' ), 'mmm_recipe_tag' );
		if ( $request->has_param( 'author' ) )           $this->apply_author( $id, (array) $request->get_param( 'author' ) );
		if ( $request->has_param( 'featured_image' ) )   $this->apply_featured_image( $id, $request->get_param( 'featured_image' ) );
		if ( $request->has_param( 'image_credit' ) )    $this->apply_image_credit( $id, (array) $request->get_param( 'image_credit' ) );
		return new WP_REST_Response( $this->serialize_recipe( $id ), 200 );
	}

	public function get_recipe_nutrition( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id   = $request->get_param( 'id' );
		$post = get_post( $id );
		if ( ! $post || 'mmm_recipe' !== $post->post_type ) return new WP_Error( 'not_found', 'Recipe not found.', array( 'status' => 404 ) );
		if ( 'publish' !== $post->post_status && ! current_user_can( 'edit_post', $id ) ) return new WP_Error( 'forbidden', 'Access denied.', array( 'status' => 403 ) );
		$recipe = new Mixmymeals_Recipe( $id );
		return new WP_REST_Response( array(
			'per_serving'      => $recipe->calculate_nutrition_per_serving(),
			'per_100g'         => $recipe->calculate_nutrition_per_100g(),
			'cost_per_serving' => $recipe->calculate_cost_per_serving(),
		), 200 );
	}

	public function get_recipe_ingredients( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id   = $request->get_param( 'id' );
		$post = get_post( $id );
		if ( ! $post || 'mmm_recipe' !== $post->post_type ) return new WP_Error( 'not_found', 'Recipe not found.', array( 'status' => 404 ) );
		if ( 'publish' !== $post->post_status && ! current_user_can( 'edit_post', $id ) ) return new WP_Error( 'forbidden', 'Access denied.', array( 'status' => 403 ) );
		return new WP_REST_Response( ( new Mixmymeals_Recipe( $id ) )->get_ingredients_scaled( floatval( $request->get_param( 'scale' ) ) ), 200 );
	}

	/**
	 * Resolves a recipe's required equipment (stored as raw IDs on the
	 * recipe) to full equipment objects, mirroring /recipes/(id)/ingredients.
	 *
	 * @param WP_REST_Request $request Request with the recipe 'id'.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_recipe_equipment( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id   = $request->get_param( 'id' );
		$post = get_post( $id );
		if ( ! $post || 'mmm_recipe' !== $post->post_type ) return new WP_Error( 'not_found', 'Recipe not found.', array( 'status' => 404 ) );
		if ( 'publish' !== $post->post_status && ! current_user_can( 'edit_post', $id ) ) return new WP_Error( 'forbidden', 'Access denied.', array( 'status' => 403 ) );
		$equipment_ids = ( new Mixmymeals_Recipe( $id ) )->get_equipment();
		$items = array_filter( array_map(
			fn( $eq_id ) => Mixmymeals_Equipment::exists_by_id( $eq_id ) ? $this->serialize_equipment( $eq_id ) : null,
			$equipment_ids
		) );
		return new WP_REST_Response( array_values( $items ), 200 );
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
		$guard = $this->require_can_publish( $request->get_param( 'status' ) ?? 'publish' );
		if ( $guard ) return $guard;
		try {
			$ing = Mixmymeals_Ingredient::create(
				$request->get_param( 'title' ),
				$request->get_param( 'status' ) ?? 'publish'
			);
		} catch ( \Exception $e ) {
			return new WP_Error( 'create_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
		if ( $request->has_param( 'diet_type' ) )    $ing->set_diet_type( $request->get_param( 'diet_type' ) );
		if ( $request->has_param( 'diets' ) )        $ing->set_diets( (array) $request->get_param( 'diets' ) );
		// null clears the field; 0 is a value.
		if ( $request->has_param( 'shelf_life_days' ) ) {
			$mmm_shelf = $request->get_param( 'shelf_life_days' );
			$ing->set_shelf_life( null === $mmm_shelf ? null : absint( $mmm_shelf ) );
		}
		if ( $request->has_param( 'storage_type' ) ) $ing->set_storage_type( (string) $request->get_param( 'storage_type' ) );
		// The ingredient's own factor; null clears it. Named own_* like the
		// price, so a client cannot write the resolved product factor back.
		if ( $request->has_param( 'own_cooking_factor' ) ) {
			$mmm_factor = $request->get_param( 'own_cooking_factor' );
			$ing->set_cooking_factor( null === $mmm_factor ? null : (float) $mmm_factor );
		}
		if ( $request->has_param( 'default_unit' ) ) $ing->set_default_unit( $request->get_param( 'default_unit' ) );
		if ( $request->has_param( 'nutrition' ) )    $ing->set_nutrition( (array) $request->get_param( 'nutrition' ) );
		if ( $request->has_param( 'categories' ) )   wp_set_post_terms( $ing->get_id(), $request->get_param( 'categories' ), 'mmm_ingredient_category' );
		return new WP_REST_Response( $this->serialize_ingredient( $ing->get_id() ), 201 );
	}

	public function update_ingredient( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id  = $request->get_param( 'id' );
		$ing = new Mixmymeals_Ingredient( $id );
		if ( ! $ing->is_valid() ) return new WP_Error( 'not_found', 'Ingredient not found.', array( 'status' => 404 ) );
		$guard = $this->require_can_edit( $id );
		if ( $guard ) return $guard;
		if ( $request->has_param( 'title' ) )        wp_update_post( array( 'ID' => $id, 'post_title' => $request->get_param( 'title' ) ) );
		if ( $request->has_param( 'diet_type' ) )    $ing->set_diet_type( $request->get_param( 'diet_type' ) );
		if ( $request->has_param( 'diets' ) )        $ing->set_diets( (array) $request->get_param( 'diets' ) );
		// null clears the field; 0 is a value.
		if ( $request->has_param( 'shelf_life_days' ) ) {
			$mmm_shelf = $request->get_param( 'shelf_life_days' );
			$ing->set_shelf_life( null === $mmm_shelf ? null : absint( $mmm_shelf ) );
		}
		if ( $request->has_param( 'storage_type' ) ) $ing->set_storage_type( (string) $request->get_param( 'storage_type' ) );
		// The ingredient's own factor; null clears it. Named own_* like the
		// price, so a client cannot write the resolved product factor back.
		if ( $request->has_param( 'own_cooking_factor' ) ) {
			$mmm_factor = $request->get_param( 'own_cooking_factor' );
			$ing->set_cooking_factor( null === $mmm_factor ? null : (float) $mmm_factor );
		}
		if ( $request->has_param( 'default_unit' ) ) $ing->set_default_unit( $request->get_param( 'default_unit' ) );
		if ( $request->has_param( 'nutrition' ) )    $ing->set_nutrition( (array) $request->get_param( 'nutrition' ) );
		// The ingredient's own figure; null or 0 clears it. A reference product
		// with price and package weight still wins over it.
		if ( $request->has_param( 'own_price_per_100g' ) ) $ing->set_price_per_100g( floatval( $request->get_param( 'own_price_per_100g' ) ?? 0 ) );
		if ( $request->has_param( 'categories' ) )   wp_set_post_terms( $id, $request->get_param( 'categories' ), 'mmm_ingredient_category' );
		return new WP_REST_Response( $this->serialize_ingredient( $id ), 200 );
	}

	public function update_ingredient_nutrition( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id  = $request->get_param( 'id' );
		$ing = new Mixmymeals_Ingredient( $id );
		if ( ! $ing->is_valid() ) return new WP_Error( 'not_found', 'Ingredient not found.', array( 'status' => 404 ) );
		$guard = $this->require_can_edit( $id );
		if ( $guard ) return $guard;
		$data = array();
		// weight_item beside the figures: it is stored with them, even though
		// it is not one of them.
		foreach ( array_merge( Mixmymeals_Ingredient::NUTRITION_FIGURE_KEYS, array( 'weight_item' ) ) as $field ) {
			// null passes through and deletes the row; floatval() would have
			// turned it into a stated zero.
			if ( $request->has_param( $field ) ) {
				$value          = $request->get_param( $field );
				$data[ $field ] = null === $value ? null : floatval( $value );
			}
		}
		$ing->set_nutrition( $data );
		return new WP_REST_Response( array( 'id' => $id, 'nutrition' => $ing->get_nutrition() ), 200 );
	}

	public function update_ingredient_price( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id  = $request->get_param( 'id' );
		$ing = new Mixmymeals_Ingredient( $id );
		if ( ! $ing->is_valid() ) return new WP_Error( 'not_found', 'Ingredient not found.', array( 'status' => 404 ) );
		$guard = $this->require_can_edit( $id );
		if ( $guard ) return $guard;
		// null or 0 clears the own price, so the reference product's shows.
		$ing->set_price_per_100g( floatval( $request->get_param( 'price_per_100g' ) ?? 0 ) );
		return new WP_REST_Response( array( 'id' => $id, 'price_per_100g' => $ing->get_price_per_100g(), 'own_price_per_100g' => $ing->get_own_price_per_100g() ), 200 );
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

		$guard = $this->require_can_edit( $ingredient_id );
		if ( $guard ) return $guard;

		try {
			$unit = Mixmymeals_Ingredient_Amount::create( $ingredient_id, $request->get_param( 'title' ) );
		} catch ( \Exception $e ) {
			return new WP_Error( 'create_failed', $e->getMessage(), array( 'status' => 500 ) );
		}

		$unit->set_weight( floatval( $request->get_param( 'weight_g' ) ) );
		if ( $request->has_param( 'is_indivisible' ) ) $unit->set_indivisible( (bool) $request->get_param( 'is_indivisible' ) );
		if ( $request->has_param( 'divisor' ) ) $unit->set_divisor( (int) $request->get_param( 'divisor' ) );

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

		$guard = $this->require_can_edit( $id );
		if ( $guard ) return $guard;

		if ( $request->has_param( 'title' ) )         $unit->set_title( $request->get_param( 'title' ) );
		if ( $request->has_param( 'weight_g' ) )       $unit->set_weight( floatval( $request->get_param( 'weight_g' ) ) );
		if ( $request->has_param( 'is_indivisible' ) ) $unit->set_indivisible( (bool) $request->get_param( 'is_indivisible' ) );
		if ( $request->has_param( 'divisor' ) ) $unit->set_divisor( (int) $request->get_param( 'divisor' ) );

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
		$guard = $this->require_can_publish( $request->get_param( 'status' ) ?? 'draft' );
		if ( $guard ) return $guard;
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
		if ( $request->has_param( 'author' ) )     $this->apply_author( $plan->get_id(), (array) $request->get_param( 'author' ) );
		if ( $request->has_param( 'featured_image' ) ) $this->apply_featured_image( $plan->get_id(), $request->get_param( 'featured_image' ) );
		if ( $request->has_param( 'image_credit' ) ) $this->apply_image_credit( $plan->get_id(), (array) $request->get_param( 'image_credit' ) );
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
		$guard = $this->require_can_edit( $id ) ?? $this->require_can_publish( $request->get_param( 'status' ) );
		if ( $guard ) return $guard;
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
		if ( $request->has_param( 'author' ) )     $this->apply_author( $id, (array) $request->get_param( 'author' ) );
		if ( $request->has_param( 'featured_image' ) ) $this->apply_featured_image( $id, $request->get_param( 'featured_image' ) );
		if ( $request->has_param( 'image_credit' ) ) $this->apply_image_credit( $id, (array) $request->get_param( 'image_credit' ) );
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
			foreach ( $ingredients as $key => $data ) {
				$ing_id  = (int) ( $data['ingredient_id'] ?? $key );
				$items[] = array(
					'ingredient_id'   => $ing_id,
					'ingredient_name' => get_the_title( $ing_id ),
					// The diet a meal cooks this line for, where its packet was
					// chosen by that diet; '' for the recipes as written. One
					// ingredient can then appear once per packet.
					'diet'            => (string) ( $data['diet'] ?? '' ),
					'total_weight_g'  => round( (float) $data['total_weight'], 2 ),
					'optional'        => ! empty( $data['optional'] ),
					// The article this plan's rows agree on — a recipe's pinned
					// product first, then the ingredient's reference. 0 and ''
					// where they disagree, because no single one is right then.
					'product_id'      => (int) ( $data['product_id'] ?? 0 ),
					'product'         => (string) ( $data['product'] ?? '' ),
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

	/**
	 * Returns the deduplicated equipment required across all meals of a plan,
	 * mirroring /meal-plans/(id)/shopping-list for ingredients.
	 *
	 * @param WP_REST_Request $request Request with the meal plan 'id'.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_meal_plan_equipment( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$plan_id = $request->get_param( 'id' );
		$post    = get_post( $plan_id );
		if ( ! $post || $post->post_type !== 'mmm_meal_plan' ) return new WP_Error( 'not_found', 'Meal plan not found.', array( 'status' => 404 ) );
		if ( 'publish' !== $post->post_status && ! current_user_can( 'edit_post', $plan_id ) ) return new WP_Error( 'forbidden', 'Access denied.', array( 'status' => 403 ) );
		$equipment_ids = ( new Mixmymeals_Meal_Plan( $plan_id ) )->get_required_equipment();
		return new WP_REST_Response( array_map( fn( $id ) => $this->serialize_equipment( $id ), $equipment_ids ), 200 );
	}

	// =========================================================
	// EQUIPMENT CALLBACKS
	// =========================================================

	private function serialize_equipment( int $post_id ): array {
		$equipment = new Mixmymeals_Equipment( $post_id );
		$post      = get_post( $post_id );
		$thumb_id = (int) get_post_thumbnail_id( $post_id );

		return array(
			'id'              => $post_id,
			'title'           => $equipment->get_title(),
			'status'          => $post->post_status,
			'link'            => get_permalink( $post_id ),
			'content'         => $post->post_content,
			'featured_image'  => $thumb_id > 0 ? $thumb_id : null,
			'categories'      => array_map(
				static fn( $term ) => array(
					'id'   => (int) $term->term_id,
					'name' => $term->name,
				),
				$equipment->get_categories()
			),
			'affiliate_links' => $equipment->get_affiliate_links(),
			'recipe_count'    => $equipment->count_recipes(),
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
		$guard = $this->require_can_publish( $request->get_param( 'status' ) ?? 'publish' );
		if ( $guard ) return $guard;
		try {
			$equipment = Mixmymeals_Equipment::create(
				$request->get_param( 'title' ),
				$request->get_param( 'status' ) ?? 'publish'
			);
		} catch ( \Exception $e ) {
			return new WP_Error( 'create_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
		$this->apply_equipment_fields( $equipment, $request );
		return new WP_REST_Response( $this->serialize_equipment( $equipment->get_id() ), 201 );
	}

	/**
	 * Applies the optional equipment fields shared by create and update, so a
	 * piece of equipment can be built in a single call.
	 */
	private function apply_equipment_fields( Mixmymeals_Equipment $equipment, WP_REST_Request $request ): void {

		$id = $equipment->get_id();

		if ( $request->has_param( 'content' ) ) {
			wp_update_post(
				array(
					'ID'           => $id,
					'post_content' => wp_kses_post( (string) $request->get_param( 'content' ) ),
				)
			);
		}

		if ( $request->has_param( 'categories' ) ) {
			wp_set_post_terms(
				$id,
				array_map( 'intval', (array) $request->get_param( 'categories' ) ),
				'mmm_equipment_category'
			);
		}

		if ( $request->has_param( 'affiliate_links' ) ) {
			$equipment->set_affiliate_links( (array) $request->get_param( 'affiliate_links' ) );
		}

		if ( $request->has_param( 'featured_image' ) ) {
			$this->apply_featured_image( $id, $request->get_param( 'featured_image' ) );
		}
	}

	public function update_equipment( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id        = $request->get_param( 'id' );
		$equipment = new Mixmymeals_Equipment( $id );
		if ( ! $equipment->is_valid() ) {
			return new WP_Error( 'not_found', 'Equipment not found.', array( 'status' => 404 ) );
		}
		$guard = $this->require_can_edit( $id ) ?? $this->require_can_publish( $request->get_param( 'status' ) );
		if ( $guard ) return $guard;
		if ( $request->has_param( 'title' ) )  $equipment->set_title( $request->get_param( 'title' ) );
		if ( $request->has_param( 'status' ) ) wp_update_post( array( 'ID' => $id, 'post_status' => $request->get_param( 'status' ) ) );
		$this->apply_equipment_fields( $equipment, $request );
		return new WP_REST_Response( $this->serialize_equipment( $id ), 200 );
	}

	// =========================================================
	// MEAL CALLBACKS
	// =========================================================

	public function create_meal( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$plan_id   = $request->get_param( 'plan_id' );
		$recipe_id = $request->get_param( 'recipe_id' );
		$title     = $request->get_param( 'title' ) ?: get_the_title( $recipe_id );
		$guard = $this->require_can_edit( $plan_id ) ?? $this->require_can_publish( $request->get_param( 'status' ) ?? 'publish' );
		if ( $guard ) return $guard;
		try {
			$meal = Mixmymeals_Meal::create( $title, $plan_id, $request->get_param( 'status' ) ?? 'publish' );
		} catch ( \Exception $e ) {
			return new WP_Error( 'create_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
		$meal->set_recipe_id( $recipe_id );
		if ( $request->has_param( 'servings' ) ) $meal->set_servings( absint( $request->get_param( 'servings' ) ) );
		if ( $request->has_param( 'type' ) )     $meal->set_type( $request->get_param( 'type' ) );
		if ( $request->has_param( 'day' ) )      $meal->set_day( absint( $request->get_param( 'day' ) ) );
		if ( $request->has_param( 'text' ) )     $meal->set_text( (string) $request->get_param( 'text' ) );
		$meal->set_diet( (string) ( $request->get_param( 'diet' ) ?? '' ) );
		return new WP_REST_Response( $this->serialize_meal( $meal->get_id() ), 201 );
	}

	public function get_meal( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id   = $request->get_param( 'id' );
		$post = get_post( $id );
		if ( ! $post || $post->post_type !== 'mmm_meal' ) return new WP_Error( 'not_found', 'Meal not found.', array( 'status' => 404 ) );
		$plan_id   = ( new Mixmymeals_Meal( $id ) )->get_parent_plan_id();
		$plan_post = $plan_id ? get_post( $plan_id ) : null;
		if ( ! $plan_post || ( 'publish' !== $plan_post->post_status && ! current_user_can( 'edit_post', $plan_id ) ) ) return new WP_Error( 'forbidden', 'Access denied.', array( 'status' => 403 ) );
		return new WP_REST_Response( $this->serialize_meal( $id ), 200 );
	}

	public function update_meal( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id   = $request->get_param( 'id' );
		$post = get_post( $id );
		if ( ! $post || $post->post_type !== 'mmm_meal' ) return new WP_Error( 'not_found', 'Meal not found.', array( 'status' => 404 ) );
		$guard = $this->require_can_edit( $id ) ?? $this->require_can_publish( $request->get_param( 'status' ) );
		if ( $guard ) return $guard;
		$meal = new Mixmymeals_Meal( $id );
		if ( $request->has_param( 'plan_id' ) )   $meal->set_parent_plan_id( absint( $request->get_param( 'plan_id' ) ) );
		if ( $request->has_param( 'recipe_id' ) ) $meal->set_recipe_id( absint( $request->get_param( 'recipe_id' ) ) );
		if ( $request->has_param( 'servings' ) )  $meal->set_servings( absint( $request->get_param( 'servings' ) ) );
		if ( $request->has_param( 'type' ) )      $meal->set_type( $request->get_param( 'type' ) );
		if ( $request->has_param( 'day' ) )       $meal->set_day( absint( $request->get_param( 'day' ) ) );
		if ( $request->has_param( 'text' ) )      $meal->set_text( (string) $request->get_param( 'text' ) );
		// After recipe_id: set_diet() checks the diet against the recipe, and
		// a new recipe is settled on its first choice unless one is given.
		if ( $request->has_param( 'diet' ) )      $meal->set_diet( (string) $request->get_param( 'diet' ) );
		elseif ( $request->has_param( 'recipe_id' ) ) $meal->set_diet( $meal->get_diet() );
		if ( $request->has_param( 'status' ) )    wp_update_post( array( 'ID' => $id, 'post_status' => $request->get_param( 'status' ) ) );
		if ( $request->has_param( 'title' ) )     wp_update_post( array( 'ID' => $id, 'post_title'  => $request->get_param( 'title' ) ) );
		return new WP_REST_Response( $this->serialize_meal( $id ), 200 );
	}

	public function set_meal_plan( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$meal_id = $request->get_param( 'id' );
		$plan_id = $request->get_param( 'plan_id' );
		$guard = $this->require_can_edit( $meal_id ) ?? $this->require_can_edit( $plan_id );
		if ( $guard ) return $guard;
		( new Mixmymeals_Meal( $meal_id ) )->set_parent_plan_id( $plan_id );
		return new WP_REST_Response( array( 'success' => true, 'meal_id' => $meal_id, 'plan_id' => $plan_id ), 200 );
	}

	/**
	 * Sets the WordPress status of a plan/recipe (draft, publish, trash).
	 * Used by the frontend admin board.
	 */
	public function set_post_status( WP_REST_Request $request ): WP_REST_Response|WP_Error {

		$id     = (int) $request['id'];
		$status = (string) $request->get_param( 'status' );

		if ( 'trash' === $status ) {
			if ( ! current_user_can( 'delete_post', $id ) ) {
				return new WP_Error( 'forbidden', 'You are not allowed to trash this post.', array( 'status' => 403 ) );
			}
			wp_trash_post( $id );
		} else {
			$guard = $this->require_can_publish( $status );
			if ( $guard ) return $guard;
			wp_update_post( array( 'ID' => $id, 'post_status' => $status ) );
		}

		return new WP_REST_Response( array(
			'id'     => $id,
			'status' => get_post_status( $id ),
		), 200 );
	}

	// =========================================================
	// AI CONVENIENCE
	// =========================================================

	/**
	 * Creates a meal plan and all of its meals in a single request. Recipes may
	 * be referenced by ID ('recipe_id') or by name/slug ('recipe'). When
	 * start_day/end_day are omitted they are derived from the meals' days.
	 * Unknown recipes are skipped and reported in 'warnings' rather than failing
	 * the whole plan, which keeps it forgiving for automated callers.
	 */
	public function build_meal_plan( WP_REST_Request $request ): WP_REST_Response|WP_Error {

		$guard = $this->require_can_publish( $request->get_param( 'status' ) ?? 'draft' );
		if ( $guard ) return $guard;

		try {
			$plan = Mixmymeals_Meal_Plan::create(
				$request->get_param( 'title' ),
				$request->get_param( 'status' ) ?? 'draft',
				$request->get_param( 'content' ) ?? ''
			);
		} catch ( \Exception $e ) {
			return new WP_Error( 'create_failed', $e->getMessage(), array( 'status' => 500 ) );
		}

		$plan_id = $plan->get_id();

		if ( $request->has_param( 'excerpt' ) )    wp_update_post( array( 'ID' => $plan_id, 'post_excerpt' => $request->get_param( 'excerpt' ) ) );
		if ( $request->has_param( 'categories' ) ) wp_set_post_terms( $plan_id, $request->get_param( 'categories' ), 'mmm_meal_plan_category' );
		if ( $request->has_param( 'tags' ) )       wp_set_post_terms( $plan_id, $request->get_param( 'tags' ), 'mmm_meal_plan_tag' );
		if ( $request->has_param( 'author' ) )     $this->apply_author( $plan_id, (array) $request->get_param( 'author' ) );
		if ( $request->has_param( 'featured_image' ) ) $this->apply_featured_image( $plan_id, $request->get_param( 'featured_image' ) );
		if ( $request->has_param( 'image_credit' ) ) $this->apply_image_credit( $plan_id, (array) $request->get_param( 'image_credit' ) );

		$meals  = $request->get_param( 'meals' );
		$result = is_array( $meals ) ? $this->apply_meals_to_plan( $plan_id, $meals ) : array( 'created' => array(), 'warnings' => array() );

		// Day range: explicit values win; otherwise derive from the meals.
		$start = $request->has_param( 'start_day' ) ? absint( $request->get_param( 'start_day' ) ) : 0;
		$end   = $request->has_param( 'end_day' ) ? absint( $request->get_param( 'end_day' ) ) : 0;
		if ( ! $start || ! $end ) {
			$days = array();
			foreach ( $result['created'] as $mid ) {
				$d = (int) ( new Mixmymeals_Meal( $mid ) )->get_day();
				if ( $d >= 1 && $d <= 7 ) {
					$days[] = $d;
				}
			}
			if ( $days ) {
				if ( ! $start ) $start = min( $days );
				if ( ! $end )   $end   = max( $days );
			}
		}
		if ( $start ) $plan->set_start_day( $start );
		if ( $end )   $plan->set_end_day( $end );

		$response                  = $this->serialize_meal_plan( $plan_id );
		$response['created_meals'] = count( $result['created'] );
		$response['warnings']      = $result['warnings'];
		return new WP_REST_Response( $response, 201 );
	}

	/**
	 * Adds several meals to an existing plan in one call (same 'meals' payload
	 * as build_meal_plan). Returns the updated plan plus any warnings.
	 */
	public function add_meals_bulk( WP_REST_Request $request ): WP_REST_Response|WP_Error {

		$plan_id = (int) $request->get_param( 'id' );
		$post    = get_post( $plan_id );
		if ( ! $post || 'mmm_meal_plan' !== $post->post_type ) {
			return new WP_Error( 'not_found', 'Meal plan not found.', array( 'status' => 404 ) );
		}
		$guard = $this->require_can_edit( $plan_id );
		if ( $guard ) return $guard;

		$meals = $request->get_param( 'meals' );
		if ( ! is_array( $meals ) ) {
			return new WP_Error( 'invalid', 'meals must be an array.', array( 'status' => 400 ) );
		}

		$result                    = $this->apply_meals_to_plan( $plan_id, $meals );
		$response                  = $this->serialize_meal_plan( $plan_id );
		$response['created_meals'] = count( $result['created'] );
		$response['warnings']      = $result['warnings'];
		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * Shared meal-creation loop for build_meal_plan()/add_meals_bulk().
	 *
	 * @param int     $plan_id Meal plan post ID.
	 * @param array[] $meals   Meal specs: recipe_id|recipe, servings, type, day, status, title.
	 * @return array{created:int[], warnings:string[]}
	 */
	private function apply_meals_to_plan( int $plan_id, array $meals ): array {

		$created  = array();
		$warnings = array();

		// Title → ID lookup so recipes can be referenced by name.
		$catalog = array();
		foreach ( get_posts( array(
			'post_type'      => 'mmm_recipe',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		) ) as $rid ) {
			$catalog[ mb_strtolower( get_the_title( $rid ) ) ] = (int) $rid;
		}

		$types = array( 'breakfast', 'lunch', 'dinner', 'snack', 'other' );

		foreach ( $meals as $i => $m ) {
			if ( ! is_array( $m ) ) {
				$warnings[] = sprintf( 'Meal %d: invalid format.', (int) $i + 1 );
				continue;
			}

			$recipe_id = 0;
			if ( ! empty( $m['recipe_id'] ) && get_post_type( (int) $m['recipe_id'] ) === 'mmm_recipe' ) {
				$recipe_id = (int) $m['recipe_id'];
			} elseif ( ! empty( $m['recipe'] ) ) {
				$recipe_id = $this->resolve_recipe_name( (string) $m['recipe'], $catalog );
			}

			if ( ! $recipe_id ) {
				$ref        = $m['recipe'] ?? ( $m['recipe_id'] ?? '?' );
				$warnings[] = sprintf( 'Meal %d: recipe not found (%s).', (int) $i + 1, (string) $ref );
				continue;
			}

			$type     = ( isset( $m['type'] ) && in_array( $m['type'], $types, true ) ) ? $m['type'] : 'other';
			$day      = isset( $m['day'] ) ? max( 1, min( 7, (int) $m['day'] ) ) : 0;
			$servings = isset( $m['servings'] ) ? max( 1, (int) $m['servings'] ) : 1;
			$status   = ( isset( $m['status'] ) && in_array( $m['status'], array( 'publish', 'draft' ), true ) ) ? $m['status'] : 'publish';
			$title    = ! empty( $m['title'] ) ? sanitize_text_field( $m['title'] ) : get_the_title( $recipe_id );

			try {
				$meal = Mixmymeals_Meal::create( $title, $plan_id, $status );
			} catch ( \Exception $e ) {
				$warnings[] = sprintf( 'Meal %d: %s', (int) $i + 1, $e->getMessage() );
				continue;
			}

			$meal->set_recipe_id( $recipe_id );
			$meal->set_servings( $servings );
			$meal->set_type( $type );
			if ( $day ) {
				$meal->set_day( $day );
			}
			// One loop serves /meal-plans/build and /meals/bulk alike, so the
			// planning's note is writable wherever a meal is made.
			if ( ! empty( $m['text'] ) ) {
				$meal->set_text( sanitize_textarea_field( (string) $m['text'] ) );
			}
			// Always: a recipe offering a choice is settled, on its first
			// option where none (or an impossible one) was given.
			if ( ! $meal->set_diet( (string) ( $m['diet'] ?? '' ) ) && ! empty( $m['diet'] ) ) {
				$warnings[] = sprintf( 'Meal %d: "%s" is not one of the recipe\'s diet options; set to "%s".', (int) $i + 1, sanitize_key( (string) $m['diet'] ), $meal->get_diet() );
			}
			$created[] = $meal->get_id();
		}

		return array( 'created' => $created, 'warnings' => $warnings );
	}

	/**
	 * Resolves a recipe reference (exact title, then slug, then partial title).
	 *
	 * @param string $name    Recipe name or slug.
	 * @param array  $catalog Prebuilt lower-title => id map.
	 * @return int Recipe ID, or 0 when nothing matches.
	 */
	private function resolve_recipe_name( string $name, array $catalog ): int {

		$name = trim( $name );
		if ( '' === $name ) {
			return 0;
		}

		$lower = mb_strtolower( $name );
		if ( isset( $catalog[ $lower ] ) ) {
			return $catalog[ $lower ];
		}

		$by_slug = get_page_by_path( sanitize_title( $name ), OBJECT, 'mmm_recipe' );
		if ( $by_slug ) {
			return (int) $by_slug->ID;
		}

		foreach ( $catalog as $title => $id ) {
			if ( false !== mb_stripos( $title, $lower ) ) {
				return $id;
			}
		}

		return 0;
	}

	/**
	 * Searches published recipes by planning-relevant criteria and returns a
	 * compact list (nutrition per serving, diet, prep time, categories) so an
	 * AI can pick recipes without fetching each one individually.
	 */
	public function search_recipes( WP_REST_Request $request ): WP_REST_Response {

		$args = array(
			'post_type'      => 'mmm_recipe',
			'post_status'    => 'publish',
			'posts_per_page' => 300,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
			'fields'         => 'ids',
		);

		if ( $request->get_param( 'search' ) ) {
			$args['s'] = $request->get_param( 'search' );
		}

		$tax = array();
		if ( $request->get_param( 'category' ) ) {
			$tax[] = array( 'taxonomy' => 'mmm_recipe_category', 'field' => 'term_id', 'terms' => (int) $request->get_param( 'category' ) );
		}
		if ( $request->get_param( 'tag' ) ) {
			$tax[] = array( 'taxonomy' => 'mmm_recipe_tag', 'field' => 'term_id', 'terms' => (int) $request->get_param( 'tag' ) );
		}
		if ( $tax ) {
			$tax['relation']  = 'AND';
			$args['tax_query'] = $tax; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		$diet     = Mixmymeals_Diet::resolve( (string) $request->get_param( 'diet' ) );
		$min_cal  = $request->has_param( 'min_calories' ) ? (float) $request->get_param( 'min_calories' ) : null;
		$max_cal  = $request->has_param( 'max_calories' ) ? (float) $request->get_param( 'max_calories' ) : null;
		$max_prep = $request->has_param( 'max_prep' ) ? (int) $request->get_param( 'max_prep' ) : null;
		$per_page = max( 1, min( 100, (int) ( $request->get_param( 'per_page' ) ?: 50 ) ) );

		$items = array();
		foreach ( get_posts( $args ) as $rid ) {
			$recipe = new Mixmymeals_Recipe( $rid );
			if ( ! $recipe->is_valid() ) {
				continue;
			}

			$nutrition = $recipe->calculate_nutrition_per_serving();
			$calories  = isset( $nutrition['calories'] ) ? (float) $nutrition['calories'] : 0.0;
			$diet_type = $recipe->get_diet_type();
			$prep      = (int) $recipe->get_preparation_time();


			if ( $diet && $diet_type !== $diet ) {
				continue;
			}
			if ( null !== $min_cal && $calories < $min_cal ) {
				continue;
			}
			if ( null !== $max_cal && $calories > $max_cal ) {
				continue;
			}

			/*
			 * A recipe nobody has timed reads 0 and must not pass as the
			 * fastest dish on the site. Asking for "at most 20 minutes" is
			 * asking for a stated time — the same rule the archive's time
			 * filter keeps, where the meta_query's join enforces it.
			 */
			if ( null !== $max_prep && ( $prep <= 0 || $prep > $max_prep ) ) {
				continue;
			}

			$items[] = array(
				'id'                   => (int) $rid,
				'title'                => get_the_title( $rid ),
				'diet_type'            => $diet_type,
				'servings'             => $recipe->get_servings(),
				'preparation_time'     => $prep,
				'calories_per_serving' => (int) round( $calories ),
				'protein_per_serving'  => isset( $nutrition['protein'] ) ? round( (float) $nutrition['protein'], 1 ) : 0,
				'categories'           => array_map( fn( $t ) => $t->term_id, $recipe->get_categories() ),
				'link'                 => get_permalink( $rid ),
			);

			if ( count( $items ) >= $per_page ) {
				break;
			}
		}

		return new WP_REST_Response( array(
			'count'   => count( $items ),
			'recipes' => $items,
		), 200 );
	}

	// =========================================================
	// USAGE INDEX
	// =========================================================

	/**
	 * Which recipes use which ingredient: [ ingredient_id => [ recipe_id, … ] ].
	 *
	 * A recipe references its ingredients inside the serialized _rmm_steps meta,
	 * so there is no meta key to query — the map is built by scanning every
	 * recipe once and then reused by search, audit, delete and merge. Cached per
	 * request; all of these run in a single request.
	 *
	 * Drafts count as usage: an ingredient still in an unpublished recipe must
	 * not be deleted silently.
	 *
	 * @return array<int, int[]>
	 */
	/**
	 * Where a unit is used, and whether it can go.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_ingr_unit_usage( WP_REST_Request $request ): WP_REST_Response|WP_Error {

		$id   = (int) $request->get_param( 'id' );
		$unit = new Mixmymeals_Ingredient_Amount( $id );

		if ( ! $unit->is_valid() ) {
			return new WP_Error( 'not_found', __( 'Unit not found.', 'mixmymeals' ), array( 'status' => 404 ) );
		}

		$usage         = $unit->get_usage();
		$ingredient_id = (int) $unit->get_parent_ingredient_id();
		$occurrences   = array_sum( array_column( $usage, 'occurrences' ) );

		/*
		 * The default unit is stored as a title, not an ID, so deleting the
		 * post does not dangle a pointer — but the ingredient would go on
		 * naming a unit that no longer exists. Worth reporting, not worth
		 * blocking.
		 */
		$is_default = $ingredient_id > 0
			&& (string) ( new Mixmymeals_Ingredient( $ingredient_id ) )->get_default_unit() === $unit->get_title();

		return new WP_REST_Response(
			array(
				'unit'            => $this->serialize_ingr_unit( $id ),
				'ingredient_id'   => $ingredient_id,
				'in_use'          => ! empty( $usage ),
				'recipe_count'    => count( $usage ),
				'occurrences'     => $occurrences,
				'recipes'         => $usage,
				'is_default_unit' => $is_default,
				'can_delete'      => empty( $usage ),
				/*
				 * Said plainly because the failure is silent: a step keeps its
				 * amount and loses its unit, and get_ingredients_scaled() then
				 * reads that amount as grams. "1,5 tins" quietly becomes 1.5 g,
				 * on the recipe, in every plan using it, and in the shopping
				 * list.
				 */
				'consequence'     => empty( $usage )
					? ''
					: __( 'Deleting this unit leaves those recipe lines with an amount and no unit, and an amount without a unit is read as grams. "1.5 tins" would become 1.5 g.', 'mixmymeals' ),
			),
			200
		);
	}

	/**
	 * Writes shelf life and storage for many recipes and ingredients at once.
	 *
	 * Mixed types on purpose: the caller works through a library, not through
	 * one post type, and splitting the batch by type would only move the
	 * bookkeeping to them. Each item is resolved by its own post type and
	 * judged on its own — one bad id does not lose the batch.
	 *
	 * **Omitting a key and sending null are different instructions.** A key
	 * that is absent leaves the stored value alone; `shelf_life_days: null`
	 * and `storage_type: ""` clear it. Without that distinction a caller
	 * filling in shelf lives would silently wipe every storage type it did not
	 * mention.
	 *
	 * The answer is deliberately thin — id, type and ok, with failures listed
	 * separately. Returning each item's serialization is what made the
	 * single-item route unusable in bulk.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function bulk_set_keeping( WP_REST_Request $request ): WP_REST_Response {

		$items   = (array) $request->get_param( 'items' );
		$dry_run = (bool) $request->get_param( 'dry_run' );

		$results = array();
		$errors  = array();
		$updated = 0;

		foreach ( $items as $item ) {

			$item = (array) $item;
			$id   = isset( $item['id'] ) ? absint( $item['id'] ) : 0;

			if ( $id <= 0 ) {
				$errors[] = array( 'id' => $id, 'error' => 'missing_id' );
				continue;
			}

			$type = get_post_type( $id );

			if ( 'mmm_recipe' === $type ) {
				$target = new Mixmymeals_Recipe( $id );
			} elseif ( 'mmm_ingredient' === $type ) {
				$target = new Mixmymeals_Ingredient( $id );
			} else {
				$errors[] = array(
					'id'    => $id,
					'error' => $type ? 'wrong_type' : 'not_found',
					'type'  => $type ?: null,
				);
				continue;
			}

			if ( ! $target->is_valid() ) {
				$errors[] = array( 'id' => $id, 'error' => 'not_found' );
				continue;
			}

			// Nothing to do is not an error, but it is worth saying: a caller
			// that mistypes both key names would otherwise read 500 successes.
			$touches = array_intersect( array( 'shelf_life_days', 'storage_type' ), array_keys( $item ) );

			if ( ! $touches ) {
				$errors[] = array( 'id' => $id, 'error' => 'no_fields' );
				continue;
			}

			if ( ! $dry_run ) {

				if ( array_key_exists( 'shelf_life_days', $item ) ) {
					$days = $item['shelf_life_days'];
					$target->set_shelf_life( null === $days ? null : absint( $days ) );
				}

				if ( array_key_exists( 'storage_type', $item ) ) {
					$target->set_storage_type( (string) $item['storage_type'] );
				}
			}

			++$updated;

			$results[] = array(
				'id'   => $id,
				'type' => $type,
				'ok'   => true,
			);
		}

		return new WP_REST_Response(
			array(
				'plugin_version' => Mixmymeals::instance()->_version,
				'dry_run'        => $dry_run,
				'requested'      => count( $items ),
				'updated'        => $updated,
				'failed'         => count( $errors ),
				'results'        => $results,
				'errors'         => $errors,
			),
			200
		);
	}

	/**
	 * Writes nutrition, price, diet and cooking factor for many ingredients
	 * and products.
	 *
	 * Writes the ingredient's **own** layer only. A figure the reference
	 * product states still wins over it, so the answer names those keys under
	 * `shadowed` — otherwise a caller would read a successful write and see
	 * nothing change on the page.
	 *
	 * `null` withdraws a value, so the ingredient falls back to its reference
	 * product again; a key left out is not touched. On a product,
	 * `price_per_100g` is turned into the package price it stores and needs a
	 * package weight to do so.
	 *
	 * The answer lists only what changed, as [old, new], per item — never the
	 * entity. dry_run computes the same diff and writes nothing.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function bulk_update( WP_REST_Request $request ): WP_REST_Response {

		$items   = (array) $request->get_param( 'items' );
		$dry_run = (bool) $request->get_param( 'dry_run' );

		$results = array();
		$errors  = array();

		foreach ( $items as $item ) {

			$item = (array) $item;
			$id   = isset( $item['id'] ) ? absint( $item['id'] ) : 0;
			$kind = (string) ( $item['type'] ?? '' );

			if ( $id <= 0 ) {
				$errors[] = array( 'id' => $id, 'error' => 'missing_id' );
				continue;
			}

			$post_type = 'product' === $kind ? Mixmymeals_Product::POST_TYPE : 'mmm_ingredient';

			if ( get_post_type( $id ) !== $post_type ) {
				$errors[] = array( 'id' => $id, 'error' => get_post_type( $id ) ? 'wrong_type' : 'not_found' );
				continue;
			}

			$product    = 'product' === $kind ? new Mixmymeals_Product( $id ) : null;
			$ingredient = new Mixmymeals_Ingredient( $product ? $product->get_ingredient_id() : $id );

			if ( ( $product && ! $product->is_valid() ) || ( ! $product && ! $ingredient->is_valid() ) ) {
				$errors[] = array( 'id' => $id, 'error' => 'not_found' );
				continue;
			}

			// Products are edited through their ingredient, as everywhere else.
			if ( $this->require_can_edit( $ingredient->get_id() ) ) {
				$errors[] = array( 'id' => $id, 'error' => 'forbidden' );
				continue;
			}

			if ( ! array_intersect( array( 'nutrition', 'price_per_100g', 'diet_type', 'cooking_factor' ), array_keys( $item ) ) ) {
				$errors[] = array( 'id' => $id, 'error' => 'no_fields' );
				continue;
			}

			$changes = array();
			$writes  = array();

			if ( isset( $item['nutrition'] ) ) {
				$nutrition = (array) $item['nutrition'];
				$own       = $product ? $product->get_nutrition() : $ingredient->get_nutrition_with_product( 0 );

				// weight_item describes the ingredient, never a packet.
				if ( $product && array_key_exists( 'weight_item', $nutrition ) ) {
					$errors[] = array( 'id' => $id, 'error' => 'weight_item_not_on_product' );
					continue;
				}

				$clean = array();

				foreach ( $nutrition as $key => $value ) {
					$value = ( null === $value || '' === $value ) ? null : (float) $value;
					$old   = $own[ $key ] ?? null;

					if ( $old === $value ) {
						continue;
					}

					$clean[ $key ]   = $value;
					$changes[ $key ] = array( $old, $value );
				}

				if ( $clean ) {
					$writes[] = fn() => $product ? $product->set_nutrition( $clean ) : $ingredient->set_nutrition( $clean );
				}
			}

			if ( array_key_exists( 'price_per_100g', $item ) ) {
				$price = null === $item['price_per_100g'] ? null : (float) $item['price_per_100g'];
				$price = $price > 0 ? $price : null;

				if ( $product ) {
					$old    = $product->get_price_per_100g();
					$weight = $product->get_package_weight();

					if ( null !== $price && $weight <= 0 ) {
						$errors[] = array( 'id' => $id, 'error' => 'no_package_weight' );
						continue;
					}
					if ( $old !== $price ) {
						$changes['price_per_100g'] = array( $old, $price );
						$writes[]                  = fn() => $product->set_price( null === $price ? null : round( $price * $weight / 100, 2 ) );
					}
				} else {
					$old = $ingredient->get_own_price_per_100g() ?: null;

					if ( $old !== $price ) {
						$changes['price_per_100g'] = array( $old, $price );
						$writes[]                  = fn() => $ingredient->set_price_per_100g( (float) $price );
					}
				}
			}

			if ( array_key_exists( 'diet_type', $item ) ) {
				$diet = Mixmymeals_Diet::resolve( (string) $item['diet_type'] );
				$old  = $product
					? $product->get_diet_type()
					: Mixmymeals_Diet::strongest( Mixmymeals_Diet::for_post( $id ), 'diet' );

				if ( $old !== $diet ) {
					$changes['diet_type'] = array( $old, $diet );
					$writes[]             = fn() => $product ? $product->set_diet_type( $diet ) : $ingredient->set_diet_type( $diet );
				}
			}

			// The own factor on both: the ingredient's is what a product
			// without one falls back to, and null clears it.
			if ( array_key_exists( 'cooking_factor', $item ) ) {
				$factor = Mixmymeals_Ingredient::normalise_cooking_factor( $item['cooking_factor'] );
				$old    = $product ? $product->get_cooking_factor() : $ingredient->get_own_cooking_factor();

				if ( $old !== $factor ) {
					$changes['cooking_factor'] = array( $old, $factor );
					$writes[]                  = fn() => $product ? $product->set_cooking_factor( $factor ) : $ingredient->set_cooking_factor( $factor );
				}
			}

			if ( ! $dry_run ) {
				foreach ( $writes as $write ) {
					$write();
				}
			}

			$row = array(
				'id'      => $id,
				'title'   => $product ? $product->label() : get_the_title( $id ),
				'changes' => (object) $changes,
			);

			// Own values that change nothing on the page, because the
			// reference product states the same key.
			if ( ! $product && $changes ) {
				$shadowed = $this->shadowed_by_reference( $id, array_keys( $changes ) );

				if ( $shadowed ) {
					$row['shadowed'] = $shadowed;
				}
			}

			$results[] = $row;
		}

		return new WP_REST_Response(
			array(
				'plugin_version' => Mixmymeals::instance()->_version,
				'dry_run'        => $dry_run,
				'requested'      => count( $items ),
				'changed'        => count( array_filter( $results, fn( $r ) => (array) $r['changes'] ) ),
				'failed'         => count( $errors ),
				'results'        => $results,
				'errors'         => $errors,
			),
			200
		);
	}

	/**
	 * Which of the given keys an ingredient's reference product states itself.
	 *
	 * Same precedence as Mixmymeals_Ingredient::apply_product() and the price
	 * and diet getters: a stated product value wins over the ingredient's own.
	 *
	 * @param int      $ingredient_id Ingredient.
	 * @param string[] $keys          Field keys.
	 * @return string[]
	 */
	private function shadowed_by_reference( int $ingredient_id, array $keys ): array {

		$ref = Mixmymeals_Product::get_reference_id( $ingredient_id );

		if ( ! $ref ) {
			return array();
		}

		$product   = new Mixmymeals_Product( $ref );
		$nutrition = $product->get_nutrition();
		$out       = array();

		foreach ( $keys as $key ) {
			$stated = match ( $key ) {
				'price_per_100g' => null !== $product->get_price_per_100g(),
				'diet_type'      => '' !== $product->get_diet_type(),
				'cooking_factor' => null !== $product->get_cooking_factor(),
				default          => null !== ( $nutrition[ $key ] ?? null ),
			};

			if ( $stated ) {
				$out[] = $key;
			}
		}

		return $out;
	}

	/**
	 * Ingredients or products lacking one field, a page at a time.
	 *
	 * Deliberately thin: id, title, own value, the value inherited from the
	 * other layer and the reference product — no units, no categories. The
	 * single-item serialization costs hundreds of tokens per ingredient, and a
	 * gap list is read for its ids.
	 *
	 * For an ingredient, `effective` mode asks what the site actually lacks
	 * (the resolved value, reference product included); `own` mode asks what
	 * the ingredient itself does not state, which is how leftover zeros under
	 * a reference product are found. A product has no inheritance: its own
	 * value is all there is, and `inherited_value` is the ingredient's figure
	 * the site falls back to while that product is the reference.
	 *
	 * zero_is_missing defaults to true because installs older than 3.52.0
	 * stored a blank field as 0 — the same reasoning as has_nutrition().
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function list_missing( WP_REST_Request $request ): WP_REST_Response {

		$field    = (string) $request->get_param( 'field' );
		$kind     = (string) $request->get_param( 'type' );
		$mode     = (string) $request->get_param( 'mode' );
		$zero     = (bool) $request->get_param( 'zero_is_missing' );
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = max( 1, min( 200, (int) $request->get_param( 'per_page' ) ) );

		$is_missing = function ( $value ) use ( $zero, $field ): bool {
			if ( 'diet_type' === $field ) {
				return '' === (string) $value;
			}
			return null === $value || ( $zero && 0.0 === (float) $value );
		};

		$product_value = function ( Mixmymeals_Product $product ) use ( $field ) {
			return match ( $field ) {
				'price_per_100g' => $product->get_price_per_100g(),
				'diet_type'      => $product->get_diet_type(),
				'cooking_factor' => $product->get_cooking_factor(),
				default          => $product->get_nutrition()[ $field ] ?? null,
			};
		};

		$ingredient_value = function ( Mixmymeals_Ingredient $ingredient ) use ( $field ) {
			return match ( $field ) {
				'price_per_100g' => $ingredient->get_own_price_per_100g() ?: null,
				'diet_type'      => Mixmymeals_Diet::strongest( Mixmymeals_Diet::for_post( $ingredient->get_id() ), 'diet' ),
				'cooking_factor' => $ingredient->get_own_cooking_factor(),
				default          => $ingredient->get_nutrition_with_product( 0 )[ $field ] ?? null,
			};
		};

		$ingredient_ids = get_posts(
			array(
				'post_type'      => 'mmm_ingredient',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);
		update_meta_cache( 'post', $ingredient_ids );
		Mixmymeals_Product::prime_for_ingredients( $ingredient_ids );

		// One query for every product rather than one per ingredient, and
		// past the per-request cache, which a product created in the same
		// request would not be in.
		$products_by_ingredient = array();

		if ( 'product' === $kind ) {
			foreach ( get_posts( array( 'post_type' => Mixmymeals_Product::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ) as $product_post ) {
				$products_by_ingredient[ (int) $product_post->post_parent ][] = (int) $product_post->ID;
			}
		}

		$rows = array();

		foreach ( $ingredient_ids as $ingredient_id ) {

			$ingredient = new Mixmymeals_Ingredient( (int) $ingredient_id );
			$ref        = Mixmymeals_Product::get_reference_id( (int) $ingredient_id );
			$own_ing    = $ingredient_value( $ingredient );

			if ( 'product' === $kind ) {
				foreach ( $products_by_ingredient[ (int) $ingredient_id ] ?? array() as $product_id ) {
					$product = new Mixmymeals_Product( (int) $product_id );
					$own     = $product_value( $product );

					if ( $is_missing( $own ) ) {
						$rows[] = array(
							'id'                => $product->get_id(),
							'title'             => $product->label(),
							'ingredient_id'     => (int) $ingredient_id,
							'own_value'         => $own,
							'inherited_value'   => $own_ing,
							'reference_product' => $ref,
						);
					}
				}
				continue;
			}

			$inherited = $ref ? $product_value( new Mixmymeals_Product( $ref ) ) : null;
			$inherited = 'diet_type' === $field ? (string) $inherited : $inherited;

			// Product first, own second — the merge every getter applies.
			$effective = ( 'diet_type' === $field ? '' !== $inherited : null !== $inherited ) ? $inherited : $own_ing;

			if ( $is_missing( 'own' === $mode ? $own_ing : $effective ) ) {
				$rows[] = array(
					'id'                => (int) $ingredient_id,
					'title'             => get_the_title( (int) $ingredient_id ),
					'own_value'         => $own_ing,
					'inherited_value'   => $ref ? $inherited : null,
					'reference_product' => $ref,
				);
			}
		}

		$total = count( $rows );

		return new WP_REST_Response(
			array(
				'plugin_version' => Mixmymeals::instance()->_version,
				'field'          => $field,
				'type'           => $kind,
				'mode'           => 'product' === $kind ? 'own' : $mode,
				'total'          => $total,
				'page'           => $page,
				'per_page'       => $per_page,
				'total_pages'    => (int) ceil( $total / $per_page ),
				'items'          => array_slice( $rows, ( $page - 1 ) * $per_page, $per_page ),
			),
			200
		);
	}

	/**
	 * Deletes several units at once, each judged on its own.
	 *
	 * Partial by design: a batch is normally "clean up what is safe", and
	 * failing the whole call because one unit turned out to be in use would
	 * make the caller pick the list apart by hand. Every id gets its own
	 * verdict and the summary says what happened.
	 *
	 * dry_run exists because forcing this is the most destructive thing in the
	 * API: every deleted unit leaves recipe lines with an amount and no unit,
	 * and those are then read as grams. Being able to see the list of affected
	 * recipes before agreeing to that is the difference between a cleanup and
	 * an accident.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function bulk_delete_ingr_units( WP_REST_Request $request ): WP_REST_Response {

		$ids     = array_values( array_unique( array_map( 'absint', (array) $request->get_param( 'ids' ) ) ) );
		$force   = (bool) $request->get_param( 'force' );
		$dry_run = (bool) $request->get_param( 'dry_run' );

		$results          = array();
		$deleted          = 0;
		$skipped          = 0;
		$not_found        = 0;
		$affected_recipes = array();

		foreach ( $ids as $id ) {

			if ( $id <= 0 ) {
				continue;
			}

			$unit = new Mixmymeals_Ingredient_Amount( $id );

			if ( ! $unit->is_valid() ) {
				++$not_found;
				$results[] = array(
					'id'     => $id,
					'title'  => '',
					'status' => 'not_found',
				);
				continue;
			}

			/*
			 * Read before anything is deleted, and safe to keep reading after:
			 * removing a unit post does not change any recipe, so the cached
			 * map stays true for the ids still to come.
			 */
			$usage = $unit->get_usage();
			$entry = array(
				'id'            => $id,
				'title'         => $unit->get_title(),
				'ingredient_id' => $unit->get_parent_ingredient_id(),
				'recipe_count'  => count( $usage ),
				'occurrences'   => (int) array_sum( array_column( $usage, 'occurrences' ) ),
				'recipes'       => $usage,
			);

			if ( $usage && ! $force ) {
				++$skipped;
				$entry['status'] = 'skipped_in_use';
				$results[]       = $entry;
				continue;
			}

			foreach ( $usage as $row ) {
				$affected_recipes[ (int) $row['recipe_id'] ] = true;
			}

			if ( $dry_run ) {
				++$deleted;
				$entry['status'] = 'would_delete';
				$results[]       = $entry;
				continue;
			}

			if ( ! wp_delete_post( $id, true ) ) {
				++$skipped;
				$entry['status'] = 'delete_failed';
				$results[]       = $entry;
				continue;
			}

			++$deleted;
			$entry['status'] = 'deleted';
			$results[]       = $entry;
		}

		return new WP_REST_Response(
			array(
				'dry_run'          => $dry_run,
				'forced'           => $force,
				'plugin_version'   => Mixmymeals::instance()->_version,
				'requested'        => count( $ids ),
				'deleted'          => $deleted,
				'skipped'          => $skipped,
				'not_found'        => $not_found,
				// Distinct recipes whose lines lose their unit — the number
				// that matters when force is on, and zero when it is not.
				'affected_recipes' => count( $affected_recipes ),
				'results'          => $results,
			),
			200
		);
	}

	/**
	 * Deletes a unit, refusing while it is in use unless forced.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_ingr_unit( WP_REST_Request $request ): WP_REST_Response|WP_Error {

		$id   = (int) $request->get_param( 'id' );
		$unit = new Mixmymeals_Ingredient_Amount( $id );

		if ( ! $unit->is_valid() ) {
			return new WP_Error( 'not_found', __( 'Unit not found.', 'mixmymeals' ), array( 'status' => 404 ) );
		}

		$usage = $unit->get_usage();

		if ( $usage && ! $request->get_param( 'force' ) ) {
			return new WP_REST_Response(
				array(
					'deleted'      => false,
					'reason'       => 'in_use',
					'recipe_count' => count( $usage ),
					'occurrences'  => array_sum( array_column( $usage, 'occurrences' ) ),
					'recipes'      => $usage,
					'message'      => sprintf(
						/* translators: %d: number of recipes using this unit. */
						__( 'Unit is still used by %d recipe(s). Those lines would be read as grams afterwards. Pass force=true to delete anyway.', 'mixmymeals' ),
						count( $usage )
					),
				),
				409
			);
		}

		$title = $unit->get_title();

		if ( ! wp_delete_post( $id, true ) ) {
			return new WP_Error( 'delete_failed', __( 'Could not delete the unit.', 'mixmymeals' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response(
			array(
				'deleted'      => true,
				'id'           => $id,
				'title'        => $title,
				'recipe_count' => count( $usage ),
				'forced'       => (bool) $request->get_param( 'force' ) && ! empty( $usage ),
			),
			200
		);
	}

	private function ingredient_usage_map(): array {

		static $map = null;

		if ( null !== $map ) {
			return $map;
		}

		$map = array();

		$recipe_ids = get_posts( array(
			'post_type'      => 'mmm_recipe',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		) );

		foreach ( $recipe_ids as $recipe_id ) {

			$steps = get_post_meta( (int) $recipe_id, '_rmm_steps', true );
			if ( ! is_array( $steps ) ) {
				continue;
			}

			// Eine Zutat kann in mehreren Schritten stehen — pro Rezept nur einmal zählen.
			$seen = array();
			foreach ( $steps as $step ) {
				foreach ( (array) ( $step['ingredients'] ?? array() ) as $entry ) {
					$ingredient_id = (int) ( $entry['id'] ?? 0 );
					if ( $ingredient_id <= 0 || isset( $seen[ $ingredient_id ] ) ) {
						continue;
					}
					$seen[ $ingredient_id ]  = true;
					$map[ $ingredient_id ][] = (int) $recipe_id;
				}
			}
		}

		return $map;
	}

	/**
	 * Which meals reference a recipe: [ meal_id, … ], with their parent plan.
	 * Meals of any status count — a recipe used by a draft plan is still in use.
	 *
	 * @param int $recipe_id Recipe post ID.
	 * @return array<int, array{meal_id:int, plan_id:int, plan_title:string}>
	 */
	private function recipe_usage( int $recipe_id ): array {

		$meal_ids = get_posts( array(
			'post_type'      => 'mmm_meal',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_key'       => 'recipe_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- targeted lookup on an indexed key, not a scan.
			'meta_value'     => (string) $recipe_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- exact-match lookup; the alternative would be fetching everything and filtering in PHP.
		) );

		$out = array();
		foreach ( $meal_ids as $meal_id ) {
			$plan_id = (int) wp_get_post_parent_id( (int) $meal_id );
			$out[]   = array(
				'meal_id'    => (int) $meal_id,
				'plan_id'    => $plan_id,
				'plan_title' => $plan_id ? get_the_title( $plan_id ) : '',
			);
		}

		return $out;
	}

	// =========================================================
	// INGREDIENT SEARCH
	// =========================================================

	/**
	 * Compact ingredient search. The full /ingredients list returns all ~350
	 * ingredients with nutrition and units, which is far too much for a tool
	 * response — this returns only what is needed to pick one.
	 */
	public function search_ingredients( WP_REST_Request $request ): WP_REST_Response {

		$query = trim( (string) $request->get_param( 'query' ) );
		$diet  = Mixmymeals_Diet::resolve( (string) $request->get_param( 'diet_type' ) );
		$limit = max( 1, min( 100, (int) ( $request->get_param( 'limit' ) ?: 20 ) ) );

		$args = array(
			'post_type'      => 'mmm_ingredient',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
			'fields'         => 'ids',
		);

		$usage = $this->ingredient_usage_map();
		$items = array();

		foreach ( get_posts( $args ) as $ingredient_id ) {

			/*
			 * Titel-Teilstring statt WP-Volltextsuche: "s" durchsucht auch den
			 * Beschreibungstext, den 301 der 341 Zutaten haben — eine Suche nach
			 * "Mehl" liefert damit auch Blumenkohl und Backpulver. Markennamen
			 * stehen im Titel, also reicht der Titel und die Treffer bleiben
			 * brauchbar. mb_stripos: Teilstring, case-insensitive, umlautfest.
			 */
			if ( '' !== $query && false === mb_stripos( get_the_title( (int) $ingredient_id ), $query ) ) {
				continue;
			}

			$ingredient = new Mixmymeals_Ingredient( (int) $ingredient_id );
			if ( ! $ingredient->is_valid() ) {
				continue;
			}

			// Diät-Filter nachgelagert, wie in search_recipes().
			if ( $diet && $ingredient->get_diet_type() !== $diet ) {
				continue;
			}

			$units = array();
			foreach ( $ingredient->get_units() as $unit_post ) {
				$unit    = new Mixmymeals_Ingredient_Amount( (int) $unit_post->ID );
				$units[] = array(
					'id'       => (int) $unit_post->ID,
					'title'    => $unit->get_title(),
					'weight_g' => $unit->get_weight(),
				);
			}

			$items[] = array(
				'id'              => (int) $ingredient_id,
				'title'           => get_the_title( (int) $ingredient_id ),
				'diet_type'       => $ingredient->get_diet_type(),
				'units'           => $units,
				'used_in_recipes' => count( $usage[ (int) $ingredient_id ] ?? array() ),
			);

			if ( count( $items ) >= $limit ) {
				break;
			}
		}

		return new WP_REST_Response( array(
			'count'       => count( $items ),
			'ingredients' => $items,
		), 200 );
	}

	// =========================================================
	// MERGE
	// =========================================================

	/**
	 * Moves every recipe reference from one ingredient to another and deletes
	 * the source afterwards.
	 *
	 * Einheiten werden über den Titel zugeordnet (case-insensitive): "EL" der
	 * Quelle wird zum "EL" des Ziels. Fehlt beim Ziel eine Einheit, die in einem
	 * Rezept tatsächlich benutzt wird, bricht der Vorgang ab und meldet den
	 * Konflikt — lieber gar nicht zusammenführen als mit falschen Mengen.
	 * Einheiten der Quelle, die in keinem Rezept vorkommen, brauchen keine
	 * Entsprechung; sie verschwinden mit der Quelle.
	 *
	 * Mengen in Gramm (amount_id = 0) laufen ohne Zuordnung durch.
	 */
	public function merge_ingredients( WP_REST_Request $request ): WP_REST_Response|WP_Error {

		$from_id = (int) $request->get_param( 'id' );
		$to_id   = (int) $request->get_param( 'to_id' );
		$dry_run = (bool) $request->get_param( 'dry_run' );

		if ( get_post_type( $from_id ) !== 'mmm_ingredient' ) {
			return new WP_Error( 'not_found', 'Source ingredient not found.', array( 'status' => 404 ) );
		}
		if ( get_post_type( $to_id ) !== 'mmm_ingredient' ) {
			return new WP_Error( 'not_found', 'Target ingredient not found.', array( 'status' => 404 ) );
		}
		if ( $from_id === $to_id ) {
			return new WP_Error( 'invalid_merge', 'Source and target must differ.', array( 'status' => 400 ) );
		}
		// The target is edited, the source deleted — the same rights the two
		// single routes ask for.
		$guard = $this->require_can_edit( $to_id );
		if ( $guard ) return $guard;
		if ( ! current_user_can( 'delete_post', $from_id ) ) {
			return new WP_Error( 'forbidden', 'You are not allowed to delete the source ingredient.', array( 'status' => 403 ) );
		}

		// Zieleinheiten nach Titel indizieren.
		$target_units = array();
		foreach ( ( new Mixmymeals_Ingredient( $to_id ) )->get_units() as $unit_post ) {
			$unit = new Mixmymeals_Ingredient_Amount( (int) $unit_post->ID );
			$target_units[ mb_strtolower( trim( $unit->get_title() ) ) ] = array(
				'id'       => (int) $unit_post->ID,
				'title'    => $unit->get_title(),
				'weight_g' => $unit->get_weight(),
			);
		}

		$usage       = $this->ingredient_usage_map();
		$recipe_ids  = $usage[ $from_id ] ?? array();
		$mapping     = array();
		$conflicts   = array();
		$affected    = array();
		$new_steps   = array();

		foreach ( $recipe_ids as $recipe_id ) {

			$recipe_id = (int) $recipe_id;
			$steps     = get_post_meta( $recipe_id, '_rmm_steps', true );
			if ( ! is_array( $steps ) ) {
				continue;
			}

			$hits = 0;
			foreach ( $steps as $step_index => $step ) {
				foreach ( (array) ( $step['ingredients'] ?? array() ) as $entry_index => $entry ) {

					if ( (int) ( $entry['id'] ?? 0 ) !== $from_id ) {
						continue;
					}
					++$hits;

					$amount_id = (int) ( $entry['amount_id'] ?? 0 );

					// Grammangabe ohne Einheit: nichts zuzuordnen.
					if ( $amount_id <= 0 ) {
						$steps[ $step_index ]['ingredients'][ $entry_index ]['id'] = $to_id;
						continue;
					}

					$source_unit  = new Mixmymeals_Ingredient_Amount( $amount_id );
					$source_title = trim( $source_unit->get_title() );
					$key          = mb_strtolower( $source_title );

					if ( ! isset( $target_units[ $key ] ) ) {
						$conflicts[ $key ] = array(
							'recipe_id'         => $recipe_id,
							'recipe_title'      => get_the_title( $recipe_id ),
							'source_unit_id'    => $amount_id,
							'source_unit_title' => $source_title,
							'reason'            => 'Zieleinheit mit diesem Titel fehlt.',
						);
						continue;
					}

					$mapping[ $key ] = array(
						'from_unit_id'    => $amount_id,
						'from_unit_title' => $source_title,
						'to_unit_id'      => $target_units[ $key ]['id'],
						'to_unit_title'   => $target_units[ $key ]['title'],
						'weight_changes'  => $source_unit->get_weight() !== $target_units[ $key ]['weight_g']
							? sprintf( '%s g → %s g', $source_unit->get_weight(), $target_units[ $key ]['weight_g'] )
							: null,
					);

					$steps[ $step_index ]['ingredients'][ $entry_index ]['id']        = $to_id;
					$steps[ $step_index ]['ingredients'][ $entry_index ]['amount_id'] = $target_units[ $key ]['id'];
				}
			}

			if ( $hits > 0 ) {
				$affected[]              = array( 'id' => $recipe_id, 'title' => get_the_title( $recipe_id ), 'entries' => $hits );
				$new_steps[ $recipe_id ] = $steps;
			}
		}

		/*
		 * Die Produkte der Quelle ziehen mit um. Sie sind Kindposts und blieben
		 * sonst an einer gelöschten Zutat hängen — und jede Rezeptzeile, die
		 * eins davon festlegt (product_id) oder ausschließt, verlöre es still,
		 * weil pinned_product_id() ein Produkt einer fremden Zutat als „nichts
		 * gewählt" liest. Die Referenz der Quelle wandert nicht mit: welches
		 * Produkt das Ziel liefert, entscheidet das Ziel.
		 */
		$products = array_map(
			fn( $p ) => array( 'id' => $p->get_id(), 'title' => $p->label() ),
			Mixmymeals_Product::for_ingredient( $from_id )
		);

		$result = array(
			'dry_run'          => $dry_run,
			'from'             => array( 'id' => $from_id, 'title' => get_the_title( $from_id ) ),
			'to'               => array( 'id' => $to_id,   'title' => get_the_title( $to_id ) ),
			'affected_recipes' => $affected,
			'unit_mapping'     => array_values( $mapping ),
			'products_moved'   => $products,
			'conflicts'        => array_values( $conflicts ),
		);

		if ( $conflicts ) {
			$result['merged']  = false;
			$result['message'] = sprintf(
				'Abgebrochen: %d Einheit(en) der Quelle haben beim Ziel keine Entsprechung. Erst passende Einheiten bei "%s" anlegen.',
				count( $conflicts ),
				get_the_title( $to_id )
			);
			return new WP_REST_Response( $result, 200 );
		}

		if ( $dry_run ) {
			$result['merged']  = false;
			$result['message'] = sprintf(
				'Trockenlauf: %d Rezept(e) würden umgehängt, danach würde "%s" gelöscht.',
				count( $affected ),
				get_the_title( $from_id )
			);
			return new WP_REST_Response( $result, 200 );
		}

		foreach ( $new_steps as $recipe_id => $steps ) {
			update_post_meta( (int) $recipe_id, '_rmm_steps', $steps );
		}

		foreach ( $products as $product ) {
			wp_update_post( array( 'ID' => $product['id'], 'post_parent' => $to_id ) );
		}

		// Quelle samt ihrer Einheiten entfernen – wie in delete_ingredient().
		$source = new Mixmymeals_Ingredient( $from_id );
		$units  = $source->is_valid() ? $source->get_units() : array();
		foreach ( $units as $unit_post ) {
			wp_delete_post( (int) $unit_post->ID, true );
		}
		wp_delete_post( $from_id, true );

		$result['merged']        = true;
		$result['deleted_from']  = true;
		$result['units_deleted'] = count( $units );
		$result['message']       = sprintf(
			'%d Rezept(e) umgehängt, "%s" gelöscht.',
			count( $affected ),
			$result['from']['title']
		);

		return new WP_REST_Response( $result, 200 );
	}

	// =========================================================
	// DELETE (mit Referenz-Prüfung)
	// =========================================================

	/**
	 * Trashes a recipe, but only when no meal references it.
	 *
	 * Ein blockierter Löschversuch ist kein Fehler, sondern ein Ergebnis:
	 * Antwort 200 mit deleted=false und den Fundstellen. So bekommt der Client
	 * verwertbare Daten statt einer Fehlermeldung, die er erst parsen müsste.
	 */
	public function delete_recipe( WP_REST_Request $request ): WP_REST_Response|WP_Error {

		$id = (int) $request->get_param( 'id' );

		if ( get_post_type( $id ) !== 'mmm_recipe' ) {
			return new WP_Error( 'not_found', 'Recipe not found.', array( 'status' => 404 ) );
		}
		if ( ! current_user_can( 'delete_post', $id ) ) {
			return new WP_Error( 'forbidden', 'You are not allowed to delete this recipe.', array( 'status' => 403 ) );
		}

		$title = get_the_title( $id );
		$used  = $this->recipe_usage( $id );

		if ( $used && ! $request->get_param( 'force' ) ) {
			return new WP_REST_Response( array(
				'deleted' => false,
				'id'      => $id,
				'title'   => $title,
				'reason'  => 'in_use',
				'message' => sprintf(
					'Rezept wird noch von %d Mahlzeit(en) verwendet. Mit force=true trotzdem löschen.',
					count( $used )
				),
				'used_by' => $used,
			), 200 );
		}

		// Rezepte in den Papierkorb – wiederherstellbar.
		$trashed = wp_trash_post( $id );

		if ( ! $trashed ) {
			return new WP_Error( 'delete_failed', 'Recipe could not be trashed.', array( 'status' => 500 ) );
		}

		return new WP_REST_Response( array(
			'deleted'        => true,
			'id'             => $id,
			'title'          => $title,
			'mode'           => 'trash',
			'forced'         => (bool) $request->get_param( 'force' ),
			'was_used_by'    => count( $used ),
		), 200 );
	}

	/**
	 * Deletes an ingredient permanently, but only when no recipe uses it.
	 * Antwortverhalten wie bei delete_recipe().
	 */
	public function delete_ingredient( WP_REST_Request $request ): WP_REST_Response|WP_Error {

		$id = (int) $request->get_param( 'id' );

		if ( get_post_type( $id ) !== 'mmm_ingredient' ) {
			return new WP_Error( 'not_found', 'Ingredient not found.', array( 'status' => 404 ) );
		}
		if ( ! current_user_can( 'delete_post', $id ) ) {
			return new WP_Error( 'forbidden', 'You are not allowed to delete this ingredient.', array( 'status' => 403 ) );
		}

		$title = get_the_title( $id );
		$usage = $this->ingredient_usage_map();
		$used  = array_map(
			fn( $rid ) => array( 'recipe_id' => (int) $rid, 'recipe_title' => get_the_title( (int) $rid ) ),
			$usage[ $id ] ?? array()
		);

		if ( $used && ! $request->get_param( 'force' ) ) {
			return new WP_REST_Response( array(
				'deleted' => false,
				'id'      => $id,
				'title'   => $title,
				'reason'  => 'in_use',
				'message' => sprintf(
					'Zutat wird noch in %d Rezept(en) verwendet. Mit force=true trotzdem löschen.',
					count( $used )
				),
				'used_by' => $used,
			), 200 );
		}

		/*
		 * Zutaten hart löschen. Die Einheiten sind Kindposts und würden sonst
		 * verwaist zurückbleiben, also vorher mit weg.
		 */
		$ingredient = new Mixmymeals_Ingredient( $id );
		$units      = $ingredient->is_valid() ? $ingredient->get_units() : array();
		foreach ( $units as $unit_post ) {
			wp_delete_post( (int) $unit_post->ID, true );
		}

		if ( ! wp_delete_post( $id, true ) ) {
			return new WP_Error( 'delete_failed', 'Ingredient could not be deleted.', array( 'status' => 500 ) );
		}

		return new WP_REST_Response( array(
			'deleted'       => true,
			'id'            => $id,
			'title'         => $title,
			'mode'          => 'permanent',
			'forced'        => (bool) $request->get_param( 'force' ),
			'units_deleted' => count( $units ),
			'was_used_by'   => count( $used ),
		), 200 );
	}

	// =========================================================
	// AUDIT
	// =========================================================

	/**
	 * Content quality report. Returns defects grouped by problem type, each item
	 * only as { id, title } — the point is a worklist, not a data dump.
	 */
	public function audit( WP_REST_Request $request ): WP_REST_Response {

		$scope  = (string) ( $request->get_param( 'scope' ) ?: 'all' );
		$groups = array();

		if ( in_array( $scope, array( 'recipes', 'all' ), true ) ) {
			$groups += $this->audit_recipes();
		}
		if ( in_array( $scope, array( 'ingredients', 'all' ), true ) ) {
			$groups += $this->audit_ingredients();
		}
		if ( in_array( $scope, array( 'plans', 'all' ), true ) ) {
			$groups += $this->audit_plans();
		}

		$total = 0;
		$counts = array();
		foreach ( $groups as $key => $items ) {
			$counts[ $key ] = count( $items );
			$total         += count( $items );
		}

		/*
		 * The version that answered. A client's tool descriptions live on the
		 * client and update the moment the file does, while the plugin updates
		 * only when somebody deploys it — so a group named in a description and
		 * missing from the response looks like a bug in the endpoint and is
		 * usually an old install. Reported here so that question answers
		 * itself: every group this version knows is always present, empty ones
		 * included, so a missing key means old code and nothing else.
		 */
		return new WP_REST_Response( array(
			'scope'          => $scope,
			'plugin_version' => Mixmymeals::instance()->_version,
			'total'          => $total,
			'counts'         => $counts,
			'groups'         => $groups,
		), 200 );
	}

	/** Kurzform für die Mängelliste: nur ID und Titel. */
	private function audit_entry( int $post_id ): array {
		return array( 'id' => $post_id, 'title' => get_the_title( $post_id ) );
	}

	/** Die als Platzhalter konfigurierte Media-ID (Einstellungen → Platzhalterbild). */
	private function placeholder_image_id(): int {
		return (int) get_option( 'mixmymeals_placeholder_image_id', 0 );
	}

	/**
	 * @return array<string, array<int, array{id:int,title:string}>>
	 */
	private function audit_recipes(): array {

		$placeholder = $this->placeholder_image_id();

		$groups = array(
			'recipes_placeholder_image' => array(),
			'recipes_without_image'     => array(),
			'recipes_without_nutrition' => array(),

			/*
			 * Recipes whose figures are built from only part of the dish. They
			 * are the dangerous ones: a recipe with no figures at all prints
			 * nothing and nobody is misled, while this one prints a kcal
			 * number that looks finished and is too low, because a missing
			 * value counts as 0 in the sum. Each entry carries the share of
			 * the weight that is covered and the ingredients that are not.
			 */
			'recipes_partial_nutrition' => array(),
			'recipes_without_category'  => array(),
			'recipes_without_excerpt'   => array(),
		);

		$recipe_ids = get_posts( array(
			'post_type'      => 'mmm_recipe',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'fields'         => 'ids',
			'no_found_rows'  => true,
		) );

		foreach ( $recipe_ids as $recipe_id ) {

			$recipe_id = (int) $recipe_id;
			$recipe    = new Mixmymeals_Recipe( $recipe_id );
			if ( ! $recipe->is_valid() ) {
				continue;
			}

			$thumb = (int) get_post_thumbnail_id( $recipe_id );
			if ( $placeholder > 0 && $thumb === $placeholder ) {
				$groups['recipes_placeholder_image'][] = $this->audit_entry( $recipe_id );
			} elseif ( ! $thumb ) {
				$groups['recipes_without_image'][] = $this->audit_entry( $recipe_id );
			}

			// Ohne Kalorien pro Portion fehlen entweder Zutaten oder deren Nährwerte.
			$nutrition = $recipe->calculate_nutrition_per_serving();
			$coverage  = $recipe->nutrition_coverage();

			if ( empty( $nutrition['calories'] ) ) {
				$groups['recipes_without_nutrition'][] = $this->audit_entry( $recipe_id );
			} elseif ( ! $coverage['complete'] ) {
				$groups['recipes_partial_nutrition'][] = array(
					'id'      => $recipe_id,
					'title'   => get_the_title( $recipe_id ),
					// The worst of the seven and its share, not an average:
					// see Mixmymeals_Recipe::nutrition_coverage().
					'figure'  => $coverage['worst'],
					'covered' => (int) round( $coverage['share'] * 100 ),
					'missing' => array_map(
						static fn( array $row ): array => array(
							'id'      => $row['id'],
							'title'   => $row['name'],
							'figures' => $row['keys'],
						),
						$coverage['missing']
					),
				);
			}

			$terms = get_the_terms( $recipe_id, 'mmm_recipe_category' );
			if ( empty( $terms ) || is_wp_error( $terms ) ) {
				$groups['recipes_without_category'][] = $this->audit_entry( $recipe_id );
			}

			if ( '' === trim( (string) get_post_field( 'post_excerpt', $recipe_id ) ) ) {
				$groups['recipes_without_excerpt'][] = $this->audit_entry( $recipe_id );
			}
		}

		return $groups;
	}

	/**
	 * @return array<string, array<int, array{id:int,title:string}>>
	 */
	private function audit_ingredients(): array {

		$usage = $this->ingredient_usage_map();

		$groups = array(
			'ingredients_without_nutrition' => array(),

			/*
			 * Ingredients stating some of the seven figures and not others.
			 * They pass the publish gate on purpose — a packet that states
			 * energy and fat and stops is a real packet — and every empty
			 * field still enters each recipe's total for that figure as a 0.
			 * This is the list that decides which rows of a nutrition table
			 * can be trusted at all, and it is the long one: on an install
			 * older than 3.52.0 a blank field was saved as a zero and counts
			 * as stated here, so what shows up is what has been entered since.
			 */
			'ingredients_partial_nutrition' => array(),

			/*
			 * Figures that contradict each other — saturates above total fat,
			 * energy that does not follow from the macros, a hundred grams
			 * holding more than a hundred grams. A typo here is copied into
			 * every recipe, plan, mail and PDF that uses the ingredient, and
			 * nothing downstream can tell it from a dense food. Products are
			 * listed in the same group with the ingredient they hang off,
			 * because the figure has to be corrected where it was typed.
			 */
			'nutrition_implausible'         => array(),
			'ingredients_without_unit'      => array(),
			'ingredients_without_price'     => array(),
			'ingredients_unused'            => array(),
			// Products entered but none chosen: the rows exist and change
			// nothing, which looks like the data is in use when it is not.
			'ingredients_products_without_reference' => array(),
			// Figures with no provenance. They go stale when a manufacturer
			// changes a recipe, and without a source nobody can tell a current
			// one from a four-year-old one.
			'products_without_source'       => array(),
			// Units nothing references. Harmless, but they clutter every
			// ingredient form and make the real ones harder to pick.
			'units_unused'                  => array(),
			/*
			 * Ingredients nothing says a diet about — not the ingredient, not
			 * any of its products. One of these in a recipe takes the diet
			 * claim off the whole dish, so this is the list that decides how
			 * many recipes can carry a "suitable for vegan" chip at all.
			 * Entries carry the category and the recipe count, which is what
			 * it takes to classify one without opening it.
			 */
			'ingredients_without_diet'      => array(),
		);

		$ingredient_ids = get_posts( array(
			'post_type'      => 'mmm_ingredient',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'fields'         => 'ids',
			'no_found_rows'  => true,
		) );

		foreach ( $ingredient_ids as $ingredient_id ) {

			$ingredient_id = (int) $ingredient_id;
			$ingredient    = new Mixmymeals_Ingredient( $ingredient_id );
			if ( ! $ingredient->is_valid() ) {
				continue;
			}

			/*
			 * has_nutrition() rather than `empty( $nutrition['calories_100'] )`:
			 * the same rule the ingredient list's filter and the publish gate
			 * on the edit screen use, so the three cannot name different
			 * ingredients. It also answers for an ingredient that states
			 * nothing but fat — which the kcal test called undocumented and
			 * the recipe sum happily adds up.
			 */
			if ( ! $ingredient->has_nutrition() ) {
				$groups['ingredients_without_nutrition'][] = $this->audit_entry( $ingredient_id );
			} else {

				$unstated = $ingredient->unstated_nutrition_keys();

				$implausible = Mixmymeals_Ingredient::nutrition_warnings( $ingredient->get_nutrition() );

				if ( $implausible ) {
					$groups['nutrition_implausible'][] = array(
						'id'       => $ingredient_id,
						'title'    => get_the_title( $ingredient_id ),
						'kind'     => 'ingredient',
						'warnings' => $implausible,
					);
				}

				if ( $unstated ) {
					$groups['ingredients_partial_nutrition'][] = array(
						'id'      => $ingredient_id,
						'title'   => get_the_title( $ingredient_id ),
						'figures' => $unstated,
						'recipes' => count( $usage[ $ingredient_id ] ?? array() ),
					);
				}
			}

			if ( empty( $ingredient->get_diet_suitabilities() ) ) {

				$terms    = get_the_terms( $ingredient_id, 'mmm_ingredient_category' );
				$category = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : '';

				$groups['ingredients_without_diet'][] = array(
					'id'       => $ingredient_id,
					'title'    => get_the_title( $ingredient_id ),
					'category' => $category,
					'recipes'  => count( $usage[ $ingredient_id ] ?? array() ),
				);
			}

			$products = Mixmymeals_Product::for_ingredient( $ingredient_id );

			if ( $products && ! Mixmymeals_Product::get_reference_id( $ingredient_id ) ) {
				$groups['ingredients_products_without_reference'][] = $this->audit_entry( $ingredient_id );
			}

			foreach ( $products as $product ) {

				if ( '' === $product->get_source() ) {
					$groups['products_without_source'][] = $this->audit_entry( $product->get_id() );
				}

				$implausible = Mixmymeals_Ingredient::nutrition_warnings( $product->get_nutrition() );

				if ( $implausible ) {
					$groups['nutrition_implausible'][] = array(
						'id'               => $product->get_id(),
						'title'            => get_the_title( $product->get_id() ),
						'kind'             => 'product',
						'ingredient_id'    => $ingredient_id,
						'ingredient_title' => get_the_title( $ingredient_id ),
						'warnings'         => $implausible,
					);
				}
			}

			/*
			 * One map for the whole run — asking each unit on its own would
			 * open every recipe once per unit.
			 *
			 * The entry names the ingredient too: "Dose" on its own identifies
			 * nothing, and a list of forty of them would be unusable.
			 */
			foreach ( $ingredient->get_units() as $unit_post ) {

				$unit_id = (int) $unit_post->ID;

				if ( isset( Mixmymeals_Ingredient_Amount::usage_map()[ $unit_id ] ) ) {
					continue;
				}

				$groups['units_unused'][] = array(
					'id'                => $unit_id,
					'title'             => get_the_title( $unit_id ),
					'ingredient_id'     => $ingredient_id,
					'ingredient_title'  => get_the_title( $ingredient_id ),
				);
			}

			if ( ! $ingredient->get_units() ) {
				$groups['ingredients_without_unit'][] = $this->audit_entry( $ingredient_id );
			}

			if ( ! $ingredient->get_price_per_100g() ) {
				$groups['ingredients_without_price'][] = $this->audit_entry( $ingredient_id );
			}

			if ( empty( $usage[ $ingredient_id ] ) ) {
				$groups['ingredients_unused'][] = $this->audit_entry( $ingredient_id );
			}
		}

		return $groups;
	}

	/**
	 * @return array<string, array<int, array{id:int,title:string}>>
	 */
	private function audit_plans(): array {

		$placeholder = $this->placeholder_image_id();

		$groups = array(
			'plans_days_below_500_kcal'      => array(),
			'plans_with_placeholder_recipes' => array(),
		);

		$plan_ids = get_posts( array(
			'post_type'      => 'mmm_meal_plan',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'fields'         => 'ids',
			'no_found_rows'  => true,
		) );

		foreach ( $plan_ids as $plan_id ) {

			$plan_id = (int) $plan_id;
			$plan    = new Mixmymeals_Meal_Plan( $plan_id );

			/*
			 * Tage ohne Mahlzeiten bleiben außen vor: ein leerer Tag ist eine
			 * Lücke im Plan, aber keine "Mahlzeit unter 500 kcal".
			 */
			$thin_days = array();
			foreach ( $plan->calc_nutrition_by_day() as $day => $nutrition ) {
				if ( $nutrition['meals'] > 0 && $nutrition['per_person']['calories'] < 500 ) {
					$thin_days[] = Mixmymeals_Meal_Plan::weekday_name( (int) $day );
				}
			}
			if ( $thin_days ) {
				$entry          = $this->audit_entry( $plan_id );
				$entry['days']  = $thin_days;
				$groups['plans_days_below_500_kcal'][] = $entry;
			}

			if ( $placeholder <= 0 ) {
				continue;
			}

			$affected = array();
			foreach ( $plan->get_meals() as $meal_post ) {
				$recipe_id = (int) ( new Mixmymeals_Meal( $meal_post->ID ) )->get_recipe_id();
				if ( $recipe_id && (int) get_post_thumbnail_id( $recipe_id ) === $placeholder ) {
					$affected[ $recipe_id ] = get_the_title( $recipe_id );
				}
			}
			if ( $affected ) {
				$entry            = $this->audit_entry( $plan_id );
				$entry['recipes'] = array_map(
					static fn( $rid, $title ) => array( 'id' => (int) $rid, 'title' => $title ),
					array_keys( $affected ),
					$affected
				);
				$groups['plans_with_placeholder_recipes'][] = $entry;
			}
		}

		return $groups;
	}

	/**
	 * Moves the legacy single-value diet meta of ingredients and products onto
	 * terms of mmm_diet.
	 *
	 * Deliberately not an option-gated migration that runs itself on upgrade:
	 * this rewrites a classification that decides whether a public "vegan"
	 * label appears, and it should happen when somebody is watching. Hence a
	 * route — dry run by default, batched, and safe to run twice.
	 *
	 * A post that already carries any term is skipped: somebody classified it
	 * since, and the meta beside it is the older answer.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function migrate_diets( WP_REST_Request $request ): WP_REST_Response {

		$dry_run = (bool) $request->get_param( 'dry_run' );
		$limit   = (int) $request->get_param( 'limit' );

		$done      = array();
		$skipped   = 0;
		$unknown   = array();
		$remaining = 0;

		foreach ( array( 'mmm_ingredient' => Mixmymeals_Diet::LEGACY_META_INGREDIENT, 'mmm_product' => Mixmymeals_Diet::LEGACY_META_PRODUCT ) as $post_type => $meta_key ) {

			$ids = get_posts(
				array(
					'post_type'      => $post_type,
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'meta_query'     => array(
						array(
							'key'     => $meta_key,
							'compare' => 'EXISTS',
						),
					),
				)
			);

			foreach ( $ids as $post_id ) {

				$post_id = (int) $post_id;
				$value   = (string) get_post_meta( $post_id, $meta_key, true );

				$terms = wp_get_object_terms( $post_id, Mixmymeals_Diet::TAXONOMY, array( 'fields' => 'ids' ) );

				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					++$skipped;
					continue;
				}

				if ( ! Mixmymeals_Diet::is_known( $value ) ) {
					$unknown[] = array( 'id' => $post_id, 'value' => $value );
					continue;
				}

				if ( count( $done ) >= $limit ) {
					++$remaining;
					continue;
				}

				if ( ! $dry_run ) {
					/*
					 * true = die alte Meta-Zeile bleibt stehen. Sie ist der
					 * Rueckweg dieses Umzugs; set_for_post() raeumt sie sonst
					 * ueberall sonst mit weg.
					 */
					Mixmymeals_Diet::set_for_post( $post_id, array( $value ), true );
				}

				$done[] = array( 'id' => $post_id, 'type' => $post_type, 'diet' => $value );
			}
		}

		return new WP_REST_Response(
			array(
				'dry_run'         => $dry_run,
				'migrated'        => count( $done ),
				'items'           => $done,
				'skipped_has_terms' => $skipped,
				'unknown_values'  => $unknown,
				'remaining'       => $remaining,
			),
			200
		);
	}

	/**
	 * Sets the diets of many ingredients and products at once.
	 *
	 * The counterpart to the audit group ingredients_without_diet, and built
	 * for the same reason /keeping/bulk exists: the single-item routes answer
	 * with the item's whole serialization, so classifying a library of 372
	 * ingredients would cost 372 calls at roughly 500 tokens each. The answer
	 * here is `id` and `ok` plus a separate error list — a handful of tokens
	 * per item.
	 *
	 * `diets` replaces everything stated on that post; an empty array clears
	 * it. There is no merge mode, because the list is short enough to send
	 * whole and a merge would make "remove vegetarian" impossible to express.
	 *
	 * Failures are per item (`not_found`, `wrong_type`, `missing_id`) and the
	 * batch continues — but an unknown diet slug **rejects the whole request**
	 * before anything is written. Same asymmetry as /keeping/bulk: a missing
	 * post is a data condition, an unknown slug is a caller bug, and half a
	 * batch built on a wrong vocabulary is worse than none of it.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function bulk_set_diets( WP_REST_Request $request ) {

		$items  = (array) $request->get_param( 'items' );
		$known  = array_keys( Mixmymeals_Diet::types() );
		$result = array();
		$errors = array();

		// Vocabulary first, over the whole batch, before a single write.
		foreach ( $items as $index => $item ) {
			foreach ( (array) ( $item['diets'] ?? array() ) as $slug ) {
				if ( ! Mixmymeals_Diet::is_known( (string) $slug ) ) {
					return new WP_Error(
						'mmm_unknown_diet',
						sprintf(
							/* translators: 1: the unknown value, 2: the list of known ones. */
							__( 'Unknown diet "%1$s". Known: %2$s', 'mixmymeals' ),
							(string) $slug,
							implode( ', ', $known )
						),
						array( 'status' => 400, 'index' => $index )
					);
				}
			}
		}

		foreach ( $items as $item ) {

			$id = (int) ( $item['id'] ?? 0 );

			if ( $id <= 0 ) {
				$errors[] = array( 'id' => $id, 'error' => 'missing_id' );
				continue;
			}

			$type = get_post_type( $id );

			if ( ! $type ) {
				$errors[] = array( 'id' => $id, 'error' => 'not_found' );
				continue;
			}

			if ( ! in_array( $type, array( 'mmm_ingredient', 'mmm_product' ), true ) ) {
				$errors[] = array( 'id' => $id, 'error' => 'wrong_type' );
				continue;
			}

			Mixmymeals_Diet::set_for_post( $id, (array) ( $item['diets'] ?? array() ) );

			$result[] = array( 'id' => $id, 'ok' => true );
		}

		return new WP_REST_Response(
			array(
				'written' => count( $result ),
				'items'   => $result,
				'errors'  => $errors,
			),
			200
		);
	}
}
