<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mixmymeals_Ingredient
 *
 * Handles ingredient nutrition data, including default values,
 * nutrition fields, units, and categories.
 */
class Mixmymeals_Ingredient {

	/**
	 * Post ID of the ingredient.
	 *
	 * @var int
	 */
	private int $post_id;

	/**
	 * Constructor.
	 *
	 * Initializes the ingredient object with a given post ID.
	 *
	 * @param int $post_id The post ID of the ingredient.
	 */
	/*
	public function __construct( $post_id ) {
		$this->post_id = intval( $post_id );
	}
	*/
	public function __construct( int $post_id ) {
		if ( ! self::exists_by_id( $post_id ) ) {
			$this->post_id = 0;
			return;
		}

		$this->post_id = $post_id;
	}

	public function is_valid(): bool {
		return self::exists_by_id( $this->post_id );
	}

	public function get_id(): int {
		return $this->post_id;
	}

	/**
	 * @throws \Exception On post creation failure.
	 */
	public static function create( string $title, string $status = 'publish' ): self {
		$post_id = wp_insert_post( array(
			'post_type'   => 'mmm_ingredient',
			'post_title'  => sanitize_text_field( $title ),
			'post_status' => $status,
		), true );

		if ( is_wp_error( $post_id ) ) {
			throw new \Exception( esc_html( $post_id->get_error_message() ) );
		}

		return new self( $post_id );
	}

	public static function exists_by_id( int $post_id ): bool {

		return $post_id
		       && get_post_type( $post_id ) === 'mmm_ingredient'
		       /*&& get_post_status( $post_id ) === 'publish'*/;
	}

	/**
	 * Retrieves all ingredient posts.
	 *
	 * Uses caching to avoid repeated queries.
	 *
	 * @return WP_Post[] Array of ingredient posts.
	 */
	/** @return WP_Post[] */
	public static function get_all_ingredients(): array {
		static $cache = null;

		if ( $cache === null ) {
			$cache = get_posts(
				array(
					'post_type'      => 'mmm_ingredient',
					'posts_per_page' => -1,
					'nopaging'       => true,
					'post_status'    => 'publish',
					'orderby'        => 'title',
					'order'          => 'ASC',
				)
			);
		}

		return $cache;
	}

	/**
	 * Returns the default unit of the ingredient.
	 *
	 * @return string The default unit.
	 */
	public function get_default_unit(): string {
		return $this->get_meta_value( '_mmm_default_unit' );
	}

	/**
	 * Helper: Returns a single meta value safely.
	 *
	 * @param string $meta_key The meta key to retrieve.
	 *
	 * @return string The sanitized meta value.
	 */
	private function get_meta_value( string $meta_key ): string {

		/*
		if ( ! $this->post_id ) {
			return '';
		}
		*/
		if ( ! $this->is_valid() ) {
			return '';
		}

		$value = get_post_meta( $this->post_id, $meta_key, true );

		if ( is_array( $value ) ) {
			$value = implode( ', ', $value );
		}

		return sanitize_text_field( $value );
	}

	/**
	 * Returns the default weight of the ingredient.
	 *
	 * @return string The default weight.
	 */
	/*
	public function get_default_weight(): string {
		return $this->get_meta_value( '_mmm_default_weight' );
	}
	*/

	/**
	 * Sets nutrition data for the ingredient.
	 *
	 * @param array $data Associative array of nutrition data.
	 *
	 * @return void
	 */
	public function set_nutrition( array $data ): void {

		if ( ! $this->is_valid() ) {
			return;
		}

		$fields = array(
			'calories_100' => '_rmm_calories_100',
			'weight_item'  => '_rmm_weight_item',
			'fat'          => '_rmm_fat',
			'carbs'        => '_rmm_carbs',
			'protein'      => '_rmm_protein',
			'sugar'        => '_rmm_sugar',
			'fiber'        => '_rmm_fiber',
			'salt'         => '_rmm_salt',
			// 'unit'       => '_rmm_unit',
		);

		foreach ( $fields as $key => $meta_key ) {
			if ( isset( $data[ $key ] ) ) {
				update_post_meta(
					$this->post_id,
					$meta_key,
					sanitize_text_field( $data[ $key ] )
				);
			}
		}
	}

	/**
	 * The seven nutrition keys and the meta they live in.
	 *
	 * @var array<string,string>
	 */
	private const NUTRITION_META = array(
		'calories_100' => '_rmm_calories_100',
		'weight_item'  => '_rmm_weight_item',
		'fat'          => '_rmm_fat',
		'carbs'        => '_rmm_carbs',
		'protein'      => '_rmm_protein',
		'sugar'        => '_rmm_sugar',
		'fiber'        => '_rmm_fiber',
		'salt'         => '_rmm_salt',
	);

	/**
	 * Gets all nutrition data for the ingredient.
	 *
	 * @return array Associative array of nutrition values.
	 */
	/** @return array<string, string> */
	public function get_nutrition(): array {

		if ( ! $this->is_valid() ) {
			return array();
		}

		/*
		 * Resolved here on purpose: this method is the single place ingredient
		 * nutrition is read, so recipe pages, plan pages, the PDF, the mails
		 * and the calculator all follow without knowing products exist.
		 */
		return self::apply_product(
			$this->read_own_nutrition(),
			Mixmymeals_Product::get_reference_id( $this->post_id )
		);
	}

	/**
	 * What this ingredient's nutrition would read if the given product supplied
	 * it — the question "what does buying that one instead do".
	 *
	 * **This is a hypothetical and must never be printed as the ingredient's
	 * nutrition.** Everything that states what a recipe, a plan, a mail or a
	 * PDF contains goes through get_nutrition(), which resolves the reference
	 * the site has actually chosen. Note the difference to the $resolve_product
	 * parameter this class carried until 3.8.2: that one let a caller quietly
	 * read *past* the reference and call the result "the" nutrition. This one
	 * has to name a product, so it cannot be mistaken for the current figure.
	 *
	 * @param int $product_id Product to resolve through. 0 gives the
	 *                        ingredient's own figures.
	 * @return array<string,float|null>
	 */
	public function get_nutrition_with_product( int $product_id ): array {

		if ( ! $this->is_valid() ) {
			return array();
		}

		return self::apply_product( $this->read_own_nutrition(), $product_id );
	}

	/**
	 * The ingredient's own stored figures, before any product is considered.
	 *
	 * @return array<string,float|null>
	 */
	private function read_own_nutrition(): array {

		$data = array();

		/*
		 * Typisiert zurückgeben, nicht roh: bis PHP 7 wurde ein nie ausgefülltes
		 * Feld ('') in Rechnungen still zu 0, seit PHP 8 wirft '' * 1.0 einen
		 * TypeError und reißt die ganze Seite mit — zuletzt die Rezeptliste im
		 * Adminbereich.
		 *
		 * null statt 0.0 für Ungesetztes: in Rechnungen verhält es sich wie 0,
		 * aber Anzeigen mit `?? '-'` erkennen weiterhin „nicht gesetzt" und
		 * zeigen keinen erfundenen Nullwert.
		 */
		foreach ( self::NUTRITION_META as $key => $meta_key ) {
			$value        = $this->get_meta_value( $meta_key );
			$data[ $key ] = is_numeric( $value ) ? (float) $value : null;
		}

		return $data;
	}

	/**
	 * Lays a product's figures over an ingredient's, key by key.
	 *
	 * Per key rather than wholesale because a packet often states energy and
	 * fat and stops there — dropping two precise numbers because the other five
	 * are missing would be the wrong trade. weight_item is never overridden: it
	 * describes the ingredient (one medium onion), not a packet.
	 *
	 * The single implementation of that merge on purpose. get_nutrition() and
	 * get_nutrition_with_product() differ only in which product they name, and
	 * if they ever merged differently the hypothetical would stop predicting
	 * what actually happens when you pick it.
	 *
	 * @param array<string,float|null> $data       The ingredient's own figures.
	 * @param int                      $product_id Product to lay over them, 0 for none.
	 * @return array<string,float|null>
	 */
	private static function apply_product( array $data, int $product_id ): array {

		if ( $product_id <= 0 ) {
			return $data;
		}

		foreach ( ( new Mixmymeals_Product( $product_id ) )->get_nutrition() as $key => $value ) {
			if ( null !== $value && array_key_exists( $key, $data ) ) {
				$data[ $key ] = $value;
			}
		}

		return $data;
	}

	/**
	 * Deletes all nutrition data for the ingredient.
	 *
	 * @return void
	 */
	public function purge_nutrition(): void {

		if ( ! $this->is_valid() ) {
			return;
		}

		$fields = array(
			'_rmm_calories_100',
			'_rmm_weight_item',
			'_rmm_fat',
			'_rmm_carbs',
			'_rmm_protein',
			'_rmm_sugar',
			'_rmm_fiber',
			'_rmm_salt',
			// '_rmm_unit',
		);

		foreach ( $fields as $meta_key ) {
			delete_post_meta( $this->post_id, $meta_key );
		}
	}

	/**
	 * Returns all units (mmm_ingr_unit posts) for the ingredient.
	 *
	 * @param array $args Optional additional WP_Query arguments.
	 *
	 * @return WP_Post[] Array of WP_Post objects representing units.
	 */
	public function get_units( array $args = array() ): array {
		/*
		if ( ! $this->post_id ) {
			return array();
		}
		*/
		if ( ! $this->is_valid() ) {
			return array();
		}

		$defaults = array(
			'post_type'   => 'mmm_ingr_unit',
			'post_status' => 'any',
			'post_parent' => $this->post_id,
			'numberposts' => -1,
			'orderby'     => 'title',
			'order'       => 'ASC',
		);

		$query_args = wp_parse_args( $args, $defaults );

		return get_posts( $query_args );
	}

	public function calculate_cost_for_weight( float $grams ): float {
		if ( $grams <= 0 ) {
			return 0.0;
		}
		return ( $grams / 100.0 ) * $this->get_price_per_100g();
	}

	/**
	 * How the **raw ingredient** is kept is stored.
	 *
	 * A separate field from the storage of a dish made from it, exactly like the shelf life beside it,
	 * and for the same reason: cooking changes what a thing needs. The two are never derived from one
	 * another.
	 *
	 * @var string
	 */
	const META_STORAGE = '_mmm_storage_type';

	/**
	 * The storage condition, or '' when not specified.
	 *
	 * Validated on read as well as on write, so a row written by something
	 * that bypassed the setter cannot put an unknown word on the page.
	 *
	 * @return string One of Mixmymeals_Post_Types::storage_types(), or ''.
	 */
	public function get_storage_type(): string {

		if ( ! $this->is_valid() ) {
			return '';
		}

		$value = (string) get_post_meta( $this->post_id, self::META_STORAGE, true );

		return Mixmymeals_Post_Types::is_storage_type( $value ) ? $value : '';
	}

	/**
	 * Stores the storage condition, or clears it.
	 *
	 * Anything outside the known list clears the field rather than being
	 * stored. This is advice about keeping food safe, and a value nobody can
	 * interpret must not survive long enough to be printed — the same rule
	 * Mixmymeals_Product::set_diet_type() keeps, for the same kind of claim.
	 *
	 * @param string $type One of Mixmymeals_Post_Types::storage_types(), or '' to clear.
	 * @return void
	 */
	public function set_storage_type( string $type ): void {

		if ( ! $this->is_valid() ) {
			return;
		}

		if ( ! Mixmymeals_Post_Types::is_storage_type( $type ) ) {
			delete_post_meta( $this->post_id, self::META_STORAGE );
			return;
		}

		update_post_meta( $this->post_id, self::META_STORAGE, $type );
	}

	/**
	 * Days the **raw ingredient** keeps.
	 *
	 * A separate field from the recipe's, holding a separate quantity: this is
	 * how long the ingredient itself lasts before it is used. Cooking changes
	 * that number in both directions, so the two must never be conflated or
	 * derived from one another.
	 *
	 * @var string
	 */
	const META_SHELF_LIFE = '_mmm_shelf_life_days';

	/**
	 * How many days the ingredient keeps.
	 *
	 * null when not specified, which is not the same as 0 — fresh fish that
	 * has to be used the day it is bought is a 0, and that is worth knowing.
	 *
	 * @return int|null Days (0 or more), or null when not specified.
	 */
	public function get_shelf_life(): ?int {

		if ( ! $this->is_valid() ) {
			return null;
		}

		$value = get_post_meta( $this->post_id, self::META_SHELF_LIFE, true );

		return '' === $value || null === $value ? null : max( 0, (int) $value );
	}

	/**
	 * Stores the shelf life, or clears it.
	 *
	 * @param int|null $days Days (0 or more), or null to clear.
	 * @return void
	 */
	public function set_shelf_life( ?int $days ): void {

		if ( ! $this->is_valid() ) {
			return;
		}

		if ( null === $days ) {
			delete_post_meta( $this->post_id, self::META_SHELF_LIFE );
			return;
		}

		update_post_meta( $this->post_id, self::META_SHELF_LIFE, max( 0, $days ) );
	}

	/**
	 * Price per 100 g, resolved through the reference product.
	 *
	 * The single read path for what an ingredient costs, exactly as
	 * get_nutrition() is for what it contains: recipe costs go through
	 * calculate_cost_for_weight(), which reads this. A reference product with a
	 * price and a package weight wins; otherwise the ingredient's own figure.
	 *
	 * @return float 0 when neither states a price.
	 */
	public function get_price_per_100g(): float {
		if ( ! $this->is_valid() ) {
			return 0.0;
		}

		$reference = Mixmymeals_Product::get_reference_id( $this->post_id );

		if ( $reference ) {
			$product_price = ( new Mixmymeals_Product( $reference ) )->get_price_per_100g();

			if ( null !== $product_price ) {
				return $product_price;
			}
		}

		return $this->get_own_price_per_100g();
	}

	/**
	 * The price the ingredient itself holds, before any product is considered.
	 *
	 * For editing and exporting only. A form that showed the resolved price
	 * would write the product's figure into the ingredient on the next save.
	 *
	 * @return float 0 when unset.
	 */
	public function get_own_price_per_100g(): float {
		if ( ! $this->is_valid() ) {
			return 0.0;
		}
		return floatval( get_post_meta( $this->post_id, '_mmm_price_per_100g', true ) );
	}

	public function set_price_per_100g( float $price ): void {
		if ( ! $this->is_valid() ) {
			return;
		}
		if ( $price > 0 ) {
			update_post_meta( $this->post_id, '_mmm_price_per_100g', $price );
		} else {
			delete_post_meta( $this->post_id, '_mmm_price_per_100g' );
		}
	}

	/**
	 * Returns all categories (terms) of the ingredient.
	 *
	 * @return WP_Term[] Array of WP_Term objects for ingredient categories.
	 */
	public function get_categories(): array {

		/*
		if ( ! $this->post_id ) {
			return array();
		}
		*/
		if ( ! $this->is_valid() ) {
			return array();
		}

		$terms = wp_get_post_terms( $this->post_id, 'mmm_ingredient_category' );

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		return $terms;
	}

	/**
	 * The diet the ingredient is in force as, on the vegan/vegetarian axis.
	 *
	 * Kept as a single string because that is what the schema, the search
	 * filters and the whole REST surface ask for. get_diets() is the full
	 * answer, which since diets became terms can hold more than one claim.
	 *
	 * @return string 'vegan', 'vegetarian', or '' if not set.
	 */
	public function get_diet_type(): string {
		return Mixmymeals_Diet::strongest( $this->get_diets(), 'diet' );
	}

	/**
	 * Everything that is in force for this ingredient — its own statements
	 * with the reference product's applied over them.
	 *
	 * The product overrides one axis at a time. It is the concrete article and
	 * the ingredient only what is usually true, so where a packet speaks it
	 * decides — but a packet certified vegan says nothing about gluten, and
	 * reading its silence as a denial would strip a correct claim.
	 *
	 * This is the single place an ingredient's diets are read, which is why
	 * Mixmymeals_Recipe, the badges, the schema and the filters all follow
	 * without knowing products exist.
	 *
	 * @return string[] Known diet slugs.
	 */
	public function get_diets(): array {

		if ( ! $this->is_valid() ) {
			return array();
		}

		$own       = Mixmymeals_Diet::for_post( $this->post_id );
		$reference = Mixmymeals_Product::get_reference_id( $this->post_id );

		if ( ! $reference ) {
			return $own;
		}

		return Mixmymeals_Diet::apply_product(
			$own,
			( new Mixmymeals_Product( $reference ) )->get_diets()
		);
	}

	/**
	 * Replaces the ingredient's own statements about diets.
	 *
	 * @param string[] $slugs Diet slugs; unknown ones are dropped.
	 * @return void
	 */
	public function set_diets( array $slugs ): void {

		if ( ! $this->is_valid() ) {
			return;
		}

		Mixmymeals_Diet::set_for_post( $this->post_id, $slugs );
	}

	public function get_diet_suitability(): string {
		return Mixmymeals_Diet::strongest( $this->get_diet_suitabilities(), 'diet' );
	}

	/**
	 * Everything this ingredient can be bought as, if you pick the right
	 * product — not what the chosen one happens to say.
	 *
	 * Best wins, across the ingredient's own statements and every product it
	 * has: a product silent on a question contributes nothing rather than
	 * pulling the answer down, and the ingredient's own classification is the
	 * floor, not a fallback used only where there are no products. It states
	 * what is normally true, and a silent packet does not contradict it.
	 *
	 * Only an ingredient with nothing stated anywhere leaves the question
	 * open — and one of those is enough to take the claim off a whole dish.
	 *
	 * @return string[] Known diet slugs.
	 */
	public function get_diet_suitabilities(): array {

		if ( ! $this->is_valid() ) {
			return array();
		}

		$sets = array( Mixmymeals_Diet::for_post( $this->post_id ) );

		foreach ( Mixmymeals_Product::for_ingredient( $this->post_id ) as $product ) {
			$sets[] = $product->get_diets();
		}

		return Mixmymeals_Diet::merge( $sets );
	}

	public function set_diet_type( string $type ): void {

		if ( ! $this->is_valid() ) {
			return;
		}

		// One axis only — see Mixmymeals_Product::set_diet_type().
		$keep = array();

		foreach ( Mixmymeals_Diet::for_post( $this->post_id ) as $slug ) {
			if ( 'diet' !== ( Mixmymeals_Diet::types()[ $slug ]['axis'] ?? '' ) ) {
				$keep[] = $slug;
			}
		}

		if ( Mixmymeals_Diet::is_known( $type ) ) {
			$keep[] = $type;
		}

		$this->set_diets( $keep );
	}

	public function set_default_unit( string $unit ): void {
		if ( ! $this->is_valid() ) {
			return;
		}
		update_post_meta( $this->post_id, '_mmm_default_unit', sanitize_text_field( $unit ) );
	}

	/**
	 * Amount for machine-readable ingredient lines: whole numbers stay whole,
	 * decimals lose trailing zeros, never any thousands separator (parsers
	 * read "1.000 g" as one gram).
	 *
	 * Used by the structured data of recipes and by the Bring! shopping list,
	 * both of which are read by software rather than by people — the
	 * human-facing counterparts are Mixmymeals_Meal_Plan::build_*_string().
	 *
	 * @param float $amount Amount.
	 * @return string
	 */
	public static function format_amount( float $amount ): string {

		if ( abs( $amount - round( $amount ) ) < 0.01 ) {
			return (string) (int) round( $amount );
		}

		return rtrim( rtrim( number_format( $amount, 2, '.', '' ), '0' ), '.' );
	}
}
