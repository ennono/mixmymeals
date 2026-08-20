<?php
/**
 * A concrete product an ingredient can be bought as.
 *
 * @package MixMyMeals/Product
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One specific, branded article behind an abstract ingredient.
 *
 * The two levels answer different questions. An ingredient carries values good
 * enough to plan with — that is what every recipe, plan and mail computes from,
 * and it stays correct when a manufacturer changes a recipe. A product carries
 * what one packet actually says.
 *
 * Worth the effort only where products differ a lot: yoghurt at 0.1 % or 10 %
 * fat, minced meat, cheese, bread. For red cabbage the gap between 25 and
 * 41 kcal is smaller than the error from cooking losses and portion size, and
 * maintaining it would be maintaining noise.
 *
 * Nutrition here is optional **per value**. A packet may list energy and fat
 * and nothing else, and discarding the two precise figures because the other
 * five are missing would be the wrong trade — so Mixmymeals_Ingredient
 * resolves key by key, product first, ingredient behind it.
 */
class Mixmymeals_Product {

	/**
	 * Post type key.
	 *
	 * @var string
	 */
	const POST_TYPE = 'mmm_product';

	/**
	 * Nutrition keys, identical to the ingredient's so the two can be merged
	 * without translating between them.
	 *
	 * @var array<string,string>
	 */
	const NUTRITION_FIELDS = array(
		'calories_100' => '_mmm_product_calories_100',
		'fat'          => '_mmm_product_fat',
		'carbs'        => '_mmm_product_carbs',
		'protein'      => '_mmm_product_protein',
		'sugar'        => '_mmm_product_sugar',
		'fiber'        => '_mmm_product_fiber',
		'salt'         => '_mmm_product_salt',
	);

	/**
	 * Weight of one package in grams — the number the shopping list needs to
	 * say what is left over after a recipe has taken its share.
	 *
	 * @var string
	 */
	const META_PACKAGE_WEIGHT = '_mmm_product_package_weight';

	/**
	 * Brand or manufacturer.
	 *
	 * @var string
	 */
	const META_BRAND = '_mmm_product_brand';

	/**
	 * Where the figures were taken from — "packet, 2026-08", a URL, whatever
	 * lets someone check them later.
	 *
	 * Not decoration: these are statements about somebody else's product, they
	 * go stale when a recipe changes, and without a note saying where a number
	 * came from nobody can tell a current figure from a four-year-old one.
	 *
	 * @var string
	 */
	const META_SOURCE = '_mmm_product_source';

	/**
	 * Post ID.
	 *
	 * @var int
	 */
	private int $post_id;

	/**
	 * Constructor.
	 *
	 * @param int $post_id Product post ID.
	 */
	public function __construct( int $post_id ) {
		$this->post_id = $post_id;
	}

	/**
	 * Product post ID.
	 *
	 * @return int
	 */
	public function get_id(): int {
		return $this->post_id;
	}

	/**
	 * Whether this is a real product hanging off a real ingredient.
	 *
	 * @return bool
	 */
	public function is_valid(): bool {

		if ( $this->post_id <= 0 || get_post_type( $this->post_id ) !== self::POST_TYPE ) {
			return false;
		}

		$parent = (int) wp_get_post_parent_id( $this->post_id );

		return $parent > 0 && ( new Mixmymeals_Ingredient( $parent ) )->is_valid();
	}

	/**
	 * Creates a product below an ingredient.
	 *
	 * @param int    $ingredient_id Parent ingredient ID.
	 * @param string $title         Product name.
	 * @return self
	 * @throws \Exception When the post cannot be created.
	 */
	public static function create( int $ingredient_id, string $title ): self {

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => sanitize_text_field( $title ),
				'post_parent' => $ingredient_id,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			throw new \Exception( esc_html( $post_id->get_error_message() ) );
		}

		return new self( $post_id );
	}

	/**
	 * Parent ingredient ID.
	 *
	 * @return int
	 */
	public function get_ingredient_id(): int {
		return (int) wp_get_post_parent_id( $this->post_id );
	}

	/**
	 * Product name.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return $this->is_valid() ? get_the_title( $this->post_id ) : '';
	}

	/**
	 * Renames the product.
	 *
	 * @param string $title Product name.
	 * @return void
	 */
	public function set_title( string $title ): void {

		if ( ! $this->is_valid() ) {
			return;
		}

		wp_update_post(
			array(
				'ID'         => $this->post_id,
				'post_title' => sanitize_text_field( $title ),
			)
		);
	}

	/**
	 * The product's nutrition per 100 g.
	 *
	 * Every key is present; a value is null when this product does not state
	 * it. Same shape and same keys as Mixmymeals_Ingredient::get_nutrition(),
	 * minus weight_item, which describes the ingredient rather than a packet.
	 *
	 * @return array<string,float|null>
	 */
	public function get_nutrition(): array {

		$out = array_fill_keys( array_keys( self::NUTRITION_FIELDS ), null );

		if ( ! $this->is_valid() ) {
			return $out;
		}

		foreach ( self::NUTRITION_FIELDS as $key => $meta_key ) {
			$value        = get_post_meta( $this->post_id, $meta_key, true );
			$out[ $key ] = is_numeric( $value ) ? (float) $value : null;
		}

		return $out;
	}

	/**
	 * Stores nutrition values.
	 *
	 * Only supplied keys are touched, and a key given as null or '' is deleted
	 * rather than stored as zero: "not stated on the packet" and "contains
	 * none" are different claims, and the second one is a claim about somebody
	 * else's product.
	 *
	 * @param array<string,float|string|null> $data Nutrition values per 100 g.
	 * @return void
	 */
	public function set_nutrition( array $data ): void {

		if ( ! $this->is_valid() ) {
			return;
		}

		foreach ( self::NUTRITION_FIELDS as $key => $meta_key ) {

			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}

			$value = $data[ $key ];

			if ( null === $value || '' === $value || ! is_numeric( $value ) ) {
				delete_post_meta( $this->post_id, $meta_key );
				continue;
			}

			update_post_meta( $this->post_id, $meta_key, (float) $value );
		}
	}

	/**
	 * Package weight in grams, 0 when unknown.
	 *
	 * @return float
	 */
	public function get_package_weight(): float {
		return $this->is_valid()
			? (float) get_post_meta( $this->post_id, self::META_PACKAGE_WEIGHT, true )
			: 0.0;
	}

	/**
	 * Sets the package weight. 0 or less removes it.
	 *
	 * @param float $grams Weight of one package in grams.
	 * @return void
	 */
	public function set_package_weight( float $grams ): void {

		if ( ! $this->is_valid() ) {
			return;
		}

		if ( $grams > 0 ) {
			update_post_meta( $this->post_id, self::META_PACKAGE_WEIGHT, $grams );
		} else {
			delete_post_meta( $this->post_id, self::META_PACKAGE_WEIGHT );
		}
	}

	/**
	 * Brand, or '' when none is recorded.
	 *
	 * @return string
	 */
	public function get_brand(): string {
		return $this->is_valid()
			? trim( (string) get_post_meta( $this->post_id, self::META_BRAND, true ) )
			: '';
	}

	/**
	 * Sets the brand; an empty value removes it.
	 *
	 * @param string $brand Brand or manufacturer.
	 * @return void
	 */
	public function set_brand( string $brand ): void {
		$this->set_text_meta( self::META_BRAND, $brand );
	}

	/**
	 * Where the figures came from, or '' when unrecorded.
	 *
	 * @return string
	 */
	public function get_source(): string {
		return $this->is_valid()
			? trim( (string) get_post_meta( $this->post_id, self::META_SOURCE, true ) )
			: '';
	}

	/**
	 * Sets the source note; an empty value removes it.
	 *
	 * @param string $source Free text, e.g. "packet, 2026-08".
	 * @return void
	 */
	public function set_source( string $source ): void {
		$this->set_text_meta( self::META_SOURCE, $source );
	}

	/**
	 * Deletes the product.
	 *
	 * @return void
	 */
	public function delete(): void {

		if ( ! $this->is_valid() ) {
			return;
		}

		// Never leave the ingredient pointing at something that is gone.
		if ( self::get_reference_id( $this->get_ingredient_id() ) === $this->post_id ) {
			self::set_reference_id( $this->get_ingredient_id(), 0 );
		}

		wp_delete_post( $this->post_id, true );
	}

	/**
	 * All products of one ingredient, oldest first.
	 *
	 * @param int $ingredient_id Ingredient ID.
	 * @return self[]
	 */
	public static function for_ingredient( int $ingredient_id ): array {

		if ( $ingredient_id <= 0 ) {
			return array();
		}

		$ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_parent'    => $ingredient_id,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);

		return array_map( static fn( $id ) => new self( (int) $id ), $ids );
	}

	/* ---------------------------------------------------------------------
	 * The reference product
	 * ------------------------------------------------------------------ */

	/**
	 * Meta on the *ingredient* naming the product its figures resolve through.
	 *
	 * @var string
	 */
	const META_REFERENCE = '_mmm_reference_product';

	/**
	 * The ingredient's reference product ID, 0 when it has none or the stored
	 * one no longer exists.
	 *
	 * @param int $ingredient_id Ingredient ID.
	 * @return int
	 */
	public static function get_reference_id( int $ingredient_id ): int {

		$product_id = (int) get_post_meta( $ingredient_id, self::META_REFERENCE, true );

		if ( ! $product_id ) {
			return 0;
		}

		$product = new self( $product_id );

		// A deleted or re-parented product must not keep steering an
		// ingredient's figures from beyond the grave.
		return ( $product->is_valid() && $product->get_ingredient_id() === $ingredient_id )
			? $product_id
			: 0;
	}

	/**
	 * Points an ingredient at one of its products, or at none with 0.
	 *
	 * @param int $ingredient_id Ingredient ID.
	 * @param int $product_id    Product ID, or 0 to clear.
	 * @return void
	 */
	public static function set_reference_id( int $ingredient_id, int $product_id ): void {

		if ( ! $product_id ) {
			delete_post_meta( $ingredient_id, self::META_REFERENCE );
			return;
		}

		$product = new self( $product_id );

		if ( ! $product->is_valid() || $product->get_ingredient_id() !== $ingredient_id ) {
			return;
		}

		update_post_meta( $ingredient_id, self::META_REFERENCE, $product_id );
	}

	/**
	 * Writes or deletes a trimmed text meta.
	 *
	 * @param string $meta_key Meta key.
	 * @param string $value    Value; '' deletes.
	 * @return void
	 */
	private function set_text_meta( string $meta_key, string $value ): void {

		if ( ! $this->is_valid() ) {
			return;
		}

		$value = trim( sanitize_text_field( $value ) );

		if ( '' === $value ) {
			delete_post_meta( $this->post_id, $meta_key );
			return;
		}

		update_post_meta( $this->post_id, $meta_key, $value );
	}
}
