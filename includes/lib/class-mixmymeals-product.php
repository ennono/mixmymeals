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
	 * Whether this particular article is vegan or vegetarian.
	 *
	 * Its own field because this is a brand property, not an ingredient one:
	 * pasta is vegan until a manufacturer puts egg in it, cheese is vegetarian
	 * until one uses animal rennet, yoghurt until one sets it with gelatine.
	 * The ingredient can only carry what is usually true.
	 *
	 * Empty means the packet was not checked, and the ingredient's own
	 * classification stands — the same "silence is not a claim" rule the
	 * nutrition fields keep.
	 *
	 * @var string
	 */
	const META_DIET_TYPE = '_mmm_product_diet_type';

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
			$value       = get_post_meta( $this->post_id, $meta_key, true );
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
	 * Price of one package, in the site currency.
	 *
	 * Per package rather than per 100 g because that is the number on the shelf
	 * label and the one somebody copies off a receipt; the price per 100 g is
	 * derived from it and the package weight, so the two cannot disagree.
	 *
	 * @var string
	 */
	const META_PRICE = '_mmm_product_price';

	/**
	 * Price of one package, or null when not recorded.
	 *
	 * @return float|null
	 */
	public function get_price(): ?float {

		if ( ! $this->is_valid() ) {
			return null;
		}

		$value = get_post_meta( $this->post_id, self::META_PRICE, true );

		return is_numeric( $value ) && (float) $value > 0 ? (float) $value : null;
	}

	/**
	 * Sets the package price. null, 0 or less removes it — nothing on a shelf
	 * costs nothing, so a zero can only be a field left blank.
	 *
	 * @param float|null $price Price of one package.
	 * @return void
	 */
	public function set_price( ?float $price ): void {

		if ( ! $this->is_valid() ) {
			return;
		}

		if ( null !== $price && $price > 0 ) {
			update_post_meta( $this->post_id, self::META_PRICE, $price );
		} else {
			delete_post_meta( $this->post_id, self::META_PRICE );
		}
	}

	/**
	 * Price per 100 g, derived from the package price and weight.
	 *
	 * Null unless **both** are recorded: a price without a weight says nothing
	 * about what a recipe's 150 g cost, and guessing would put an invented
	 * figure on every recipe using the ingredient.
	 *
	 * @return float|null
	 */
	public function get_price_per_100g(): ?float {

		$price  = $this->get_price();
		$weight = $this->get_package_weight();

		return null !== $price && $weight > 0 ? $price / $weight * 100.0 : null;
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
	 * Loads the products of many ingredients in one query.
	 *
	 * Asked per ingredient, for_ingredient() runs once per ingredient, and a
	 * recipe asks for every one of its own — a plan for every ingredient of every dish. On a recipe archive
	 * that was one query per ingredient per card before anything had been read
	 * from them.
	 *
	 * Also warms the post and meta caches of the products themselves, since
	 * the very next thing asked of them is their diets and their brand.
	 *
	 * @param int[] $ingredient_ids Ingredient IDs.
	 * @return void
	 */
	public static function prime_for_ingredients( array $ingredient_ids ): void {

		$cache   = &self::ingredient_cache();
		$missing = array();

		foreach ( $ingredient_ids as $id ) {

			$id = (int) $id;

			if ( $id > 0 && ! isset( $cache[ $id ] ) ) {
				$missing[ $id ] = true;
			}
		}

		if ( empty( $missing ) ) {
			return;
		}

		$posts = get_posts(
			array(
				'post_type'       => self::POST_TYPE,
				'post_parent__in' => array_keys( $missing ),
				'post_status'     => 'publish',
				'posts_per_page'  => -1,
				'orderby'         => 'ID',
				'order'           => 'ASC',
				'no_found_rows'   => true,
			)
		);

		// Every id asked for gets an entry, so an ingredient without products
		// is not looked up again one query at a time.
		foreach ( array_keys( $missing ) as $id ) {
			$cache[ $id ] = array();
		}

		foreach ( $posts as $post ) {
			$cache[ (int) $post->post_parent ][] = new self( (int) $post->ID );
		}
	}

	/**
	 * The per-request product list per ingredient, by reference so both
	 * for_ingredient() and prime_for_ingredients() share one store.
	 *
	 * @return array<int, self[]>
	 */
	private static function &ingredient_cache(): array {

		static $cache = array();

		return $cache;
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

		/*
		 * One query per ingredient, and a recipe asks for every one of them —
		 * a plan for every ingredient of every dish. Held for the request,
		 * which is as long as the answer can change without a write.
		 * prime_for_ingredients() fills the same store for a whole page.
		 */
		$cache = &self::ingredient_cache();

		if ( isset( $cache[ $ingredient_id ] ) ) {
			return $cache[ $ingredient_id ];
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

		$cache[ $ingredient_id ] = array_map( static fn( $id ) => new self( (int) $id ), $ids );

		return $cache[ $ingredient_id ];
	}

	/**
	 * Books one recipe row's grams into the weights array impact_groups() reads.
	 *
	 * The three things that array records about a row are one decision, not
	 * three: which packet the grams are (the row's pin, else the ingredient's
	 * reference), how many grams that packet supplies, and whether the recipe
	 * decided at all. Written out per caller they drifted apart at the first
	 * change to any of the three — and the recipe's and the plan's comparison
	 * would then disagree about the same dish.
	 *
	 * Takes the one ingredient's entry by reference; the callers keep their own
	 * initialisation, because a plan also counts meals and a recipe does not.
	 *
	 * @param array<string, mixed> $info          That ingredient's entry, modified in place.
	 * @param int                  $ingredient_id The ingredient.
	 * @param int                  $pinned        The row's product_id, 0 for none.
	 * @param float                $grams         Grams one person eats from this row.
	 * @param string               $grams_key     Where the total goes: 'grams' or 'grams_person'.
	 * @return void
	 */
	public static function add_row_weight( array &$info, int $ingredient_id, int $pinned, float $grams, string $grams_key = 'grams' ): void {

		$pin = Mixmymeals_Ingredient::pinned_product_id( $ingredient_id, $pinned );

		// Which packet these grams are, so the table anchors on what the recipe
		// names rather than on the ingredient's reference.
		$product = $pin > 0 ? $pin : self::get_reference_id( $ingredient_id );

		$info[ $grams_key ]           = (float) ( $info[ $grams_key ] ?? 0.0 ) + $grams;
		$info['products'][ $product ] = (float) ( $info['products'][ $product ] ?? 0.0 ) + $grams;

		// Only a pin counts as decided. A reference product is the site's
		// default for the ingredient, not a statement about this dish.
		if ( 0 === $pin ) {
			$info['open'] = (float) ( $info['open'] ?? 0.0 ) + $grams;
		}
	}

	/**
	 * The figures a set of grams currently carries, per 100 g.
	 *
	 * One product supplies all of them in the ordinary case and this is simply
	 * its merge with the ingredient. Where a plan pins different packets for
	 * one ingredient it is their gram-weighted mix, which is what the plan
	 * really contains — and the only figure a difference can be measured from
	 * without overstating it for one of the recipes.
	 *
	 * A key nobody states stays null: null is not zero anywhere else in this
	 * class either, and a mix of silences is still silence.
	 *
	 * @param Mixmymeals_Ingredient $ingredient The ingredient.
	 * @param array<int,float>      $by_product Grams per product ID (0 = no product).
	 * @return array<string,float|null>
	 */
	private static function blend_nutrition( Mixmymeals_Ingredient $ingredient, array $by_product ): array {

		if ( 1 === count( $by_product ) ) {
			return $ingredient->get_nutrition_with_product( (int) array_key_first( $by_product ) );
		}

		$sums   = array();
		$stated = array();
		$total  = array_sum( $by_product );

		if ( $total <= 0 ) {
			return $ingredient->get_nutrition();
		}

		foreach ( $by_product as $product_id => $grams ) {

			foreach ( $ingredient->get_nutrition_with_product( (int) $product_id ) as $key => $value ) {

				if ( ! isset( $sums[ $key ] ) ) {
					$sums[ $key ]   = 0.0;
					$stated[ $key ] = false;
				}

				if ( null === $value ) {
					continue;
				}

				/*
				 * Over *all* the grams, not only over those that stated the
				 * key. Grams whose packet is silent contribute nothing to the
				 * total, exactly as a null does in the sums downstream, and
				 * averaging over the stating grams alone would spread one
				 * product's figure across the whole ingredient.
				 */
				$sums[ $key ] += $value * (float) $grams;

				$stated[ $key ] = true;
			}
		}

		$blend = array();

		foreach ( $sums as $key => $sum ) {
			$blend[ $key ] = $stated[ $key ] ? $sum / $total : null;
		}

		return $blend;
	}

	/**
	 * What choosing a different product would do, for every ingredient of a
	 * dish or a plan that comes in more than one.
	 *
	 * The arithmetic is the same wherever it is asked; only the reference frame
	 * differs. A plan divides by its days and states "per day and person"; a
	 * recipe divides by nothing and states "per serving". Both pass the grams
	 * one person eats and the figures those grams already produce, and get the
	 * same shape back — which is why one renderer can draw either.
	 *
	 * One column anchors each table and carries absolute figures; the rest carry
	 * differences to it. The anchor is the packet actually in use — the product
	 * the recipe rows pin, else the ingredient's reference — and otherwise a
	 * column of the ingredient's own typical values, which is what the dish is
	 * computed from when no product is chosen. Either way the anchor holds
	 * exactly $current, so it replaces the separate "as planned" column rather
	 * than sitting beside it as a row of ±0.
	 *
	 * Where the rows disagree — two recipes of one plan pinning different
	 * packets of the same ingredient — no column can carry absolutes, and the
	 * figures the differences are measured from are the gram-weighted mix the
	 * plan actually contains. That keeps the arithmetic exact rather than
	 * picking one of the two and being wrong about the other: the total moves
	 * by (product − mix) × grams, whatever the mix was made of.
	 *
	 * Only ingredients with several products, and only where at least one
	 * figure moves far enough to be printed. A page of ±0 answers no question.
	 *
	 * Computed as differences rather than by resolving each product in turn:
	 * nutrition is linear in weight, so a product stating x more per 100 g
	 * moves the result by x times the grams, over the divisor. Note that this
	 * only holds while the divisor is independent of nutrition — see the note
	 * on Mixmymeals_Meal_Plan::calc_nutrition_per_day_person().
	 *
	 * An ingredient whose every gram the recipe has already assigned a product
	 * to is left out entirely: the choice this table exists to inform has been
	 * made by whoever wrote the dish.
	 *
	 * Per ingredient, $weights carries the grams one person eats, in how many
	 * dishes it occurs (0 to omit), optionally those grams broken down by the
	 * product in force for them, and optionally how many of them the recipe
	 * left open — add_row_weight() fills all three. Without the breakdown the
	 * ingredient's reference product is assumed for all of them, as before;
	 * without 'open' nothing counts as decided.
	 *
	 * @param array<int, array<string, mixed>> $weights Grams and products per ingredient.
	 * @param array<string, float>             $current What those grams currently produce.
	 * @param float                            $divisor What to divide by for the caller's unit.
	 * @return array<int, array{ingredient_id:int, ingredient:string, meals:int, current:array<string,float>, products:array}>
	 */
	public static function impact_groups( array $weights, array $current, float $divisor = 1.0 ): array {

		$keys    = Mixmymeals_Meal_Plan::NUTRITION_KEYS;
		$intake  = Mixmymeals_Meal_Plan::reference_intake();
		$divisor = max( 0.0000001, $divisor );

		$groups = array();

		foreach ( $weights as $ingredient_id => $info ) {
			/*
			 * Nothing left open means the recipe has already named the packet
			 * for every gram of this ingredient, and the question this table
			 * asks — which one should it be — has an author's answer. Showing
			 * it anyway would invite the reader to redo a decision that is part
			 * of the dish, and the figures beside it already assume that
			 * choice.
			 *
			 * A plan can be half decided, one dish naming a packet and another
			 * not; then the question is still open for the second and the
			 * group stays. 'open' absent (a caller that supplies no breakdown)
			 * means nothing is decided, which is how it was before.
			 */
			if ( isset( $info['open'] ) && (float) $info['open'] <= 0 ) {
				continue;
			}

			$products = self::for_ingredient( (int) $ingredient_id );

			if ( count( $products ) < 2 ) {
				continue;
			}

			$ingredient = new Mixmymeals_Ingredient( (int) $ingredient_id );

			if ( ! $ingredient->is_valid() ) {
				continue;
			}

			/*
			 * Which packet these grams are actually made of. A recipe row may
			 * pin one, and then it — not the ingredient's reference — is what
			 * the dish contains and what the anchor column has to state.
			 */
			$by_product = $info['products'] ?? array();

			if ( empty( $by_product ) ) {
				$by_product = array( self::get_reference_id( (int) $ingredient_id ) => (float) $info['grams'] );
			}

			$reference = 1 === count( $by_product ) ? (int) array_key_first( $by_product ) : 0;
			$now       = self::blend_nutrition( $ingredient, $by_product );

			// What is in force right now — resolved through whatever packet
			// supplies these grams, which is what the anchor column states
			// everywhere else in this table.
			$diet_now = Mixmymeals_Diet::strongest( $ingredient->get_diets_pinned( $reference ), 'diet' );
			$factor   = (float) $info['grams'] / 100 / $divisor;

			$rows    = array();
			$spreads = array_fill_keys( $keys, array() );
			$visible = false;

			foreach ( $products as $product ) {

				$with   = $ingredient->get_nutrition_with_product( $product->get_id() );
				$stated = $product->get_nutrition();

				$deltas = array();
				$known  = array();

				foreach ( $keys as $key ) {

					$field = 'calories' === $key ? 'calories_100' : $key;

					/*
					 * A figure the product does not state stays null and takes
					 * no part in any of this — not in the cell, not in the
					 * spread that ranks the ingredients, not in the test that
					 * decides whether the group is worth showing.
					 *
					 * Substituting the ingredient's average and printing the
					 * resulting difference, as this did in 3.12.0, put a number
					 * against a product that the product never claimed. It is
					 * true of the dish and false of the packet, and this table
					 * is about packets.
					 */
					$known[ $key ] = null !== ( $stated[ $field ] ?? null );

					if ( ! $known[ $key ] ) {
						$deltas[ $key ] = null;
						continue;
					}

					$deltas[ $key ] = ( (float) ( $with[ $field ] ?? 0 ) - (float) ( $now[ $field ] ?? 0 ) ) * $factor;

					$spreads[ $key ][] = $deltas[ $key ];

					if ( Mixmymeals_Meal_Plan::nutrition_value_is_printable( $key, $deltas[ $key ] ) ) {
						$visible = true;
					}
				}

				$is_reference = $reference > 0 && $reference === $product->get_id();

				$rows[] = array(
					'id'           => $product->get_id(),
					'title'        => $product->get_title(),
					'brand'        => $product->get_brand(),
					'deltas'       => $deltas,
					'stated'       => $known,
					/*
					 * The packet's own classification, not the one that would
					 * result. Printing "vegan" against a product that never
					 * claimed it — because the ingredient happens to be — would
					 * put a label people act on next to a brand that did not
					 * earn it. Silence gets a dash, as it does for nutrition.
					 */
					'diet'         => $product->get_diet_type(),
					'is_reference' => $is_reference,
					'is_anchor'    => $is_reference,
					// Absolute figures for the anchor only. Its deltas are all
					// zero by construction, and a column of ±0 tells nobody
					// what they currently have.
					'values'       => $is_reference ? $current : null,
				);
			}

			if ( ! $visible ) {
				continue;
			}

			/*
			 * By energy, with products that do not state theirs last: null
			 * sorts before every number in PHP, which would put the least
			 * informative columns first.
			 */
			usort(
				$rows,
				static function ( $a, $b ) {
					$rank = static fn( $r ) => $r['is_anchor'] ? -1 : ( null === $r['deltas']['calories'] ? 1 : 0 );

					return array( $rank( $a ), (float) $a['deltas']['calories'] )
						<=> array( $rank( $b ), (float) $b['deltas']['calories'] );
				}
			);

			/*
			 * With no reference product the differences are measured against
			 * the ingredient's own figures, and nothing on screen says what
			 * those are. A column for them, first, so the table still starts
			 * from something concrete.
			 */
			if ( 0 === $reference ) {
				array_unshift(
					$rows,
					array(
						'id'           => 0,
						'title'        => __( 'Typical values', 'mixmymeals' ),
						// No brand: this column is the ingredient, not an article.
						'brand'        => '',
						'deltas'       => array_fill_keys( $keys, 0.0 ),
						'stated'       => array_fill_keys( $keys, true ),
						'diet'         => $diet_now,
						'is_reference' => false,
						'is_anchor'    => true,
						'values'       => $current,
					)
				);
			}

			/*
			 * Ranked by the biggest spread measured against the reference
			 * intake, which is the only way to compare 80 kcal with 1,2 g of
			 * salt without one unit simply having larger numbers than another.
			 */
			$weight_of_choice = 0.0;

			foreach ( $keys as $key ) {
				$reference_value = (float) ( $intake[ $key ] ?? 0 );

				// Fewer than two stated figures is not a spread, it is one
				// product's number with nothing to compare it against.
				if ( $reference_value <= 0 || count( $spreads[ $key ] ) < 2 ) {
					continue;
				}

				$weight_of_choice = max(
					$weight_of_choice,
					( max( $spreads[ $key ] ) - min( $spreads[ $key ] ) ) / $reference_value
				);
			}

			/*
			 * The anchor states what is in force, whatever its own packet says:
			 * a reference product silent on the question still leaves the
			 * ingredient's classification standing, and that is what the column
			 * is for.
			 */
			foreach ( $rows as $index => $row ) {
				if ( $row['is_anchor'] ) {
					$rows[ $index ]['diet'] = $diet_now;
				}
			}

			$groups[] = array(
				'ingredient_id' => (int) $ingredient_id,
				'ingredient'    => get_the_title( (int) $ingredient_id ),
				'meals'         => (int) ( $info['meals'] ?? 0 ),
				'current'       => $current,
				'products'      => $rows,
				'weight'        => $weight_of_choice,
				// A row of dashes answers nothing; skipped unless somebody has
				// classified something here.
				'has_diet'      => (bool) array_filter( array_column( $rows, 'diet' ) ),
			);
		}

		// The ingredient whose choice matters most first: it is the one
		// decision on this page worth thinking about.
		usort( $groups, static fn( $a, $b ) => $b['weight'] <=> $a['weight'] );

		return $groups;
	}

	/**
	 * This product's diet classification.
	 *
	 * @return string 'vegan', 'vegetarian', or '' when the packet was not checked.
	 */
	public function get_diet_type(): string {
		return Mixmymeals_Diet::strongest( $this->get_diets(), 'diet' );
	}

	/**
	 * Everything this product states about diets.
	 *
	 * Terms, with the old single-value meta still answering where nothing has
	 * been migrated — see Mixmymeals_Diet::for_post().
	 *
	 * @return string[] Known diet slugs.
	 */
	public function get_diets(): array {

		if ( ! $this->is_valid() ) {
			return array();
		}

		return Mixmymeals_Diet::for_post( $this->post_id );
	}

	/**
	 * Replaces everything this product states about diets.
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

	/**
	 * Stores the diet classification, or clears it.
	 *
	 * Anything that is not one of the two known values clears the field rather
	 * than being stored: this decides whether a public "vegan" label appears,
	 * and a value nobody can interpret must not be able to produce one.
	 *
	 * @param string $type 'vegan', 'vegetarian', or '' to clear.
	 * @return void
	 */
	public function set_diet_type( string $type ): void {

		if ( ! $this->is_valid() ) {
			return;
		}

		/*
		 * Writes the 'diet' axis only and leaves every other statement alone:
		 * this setter speaks for one axis, and clearing gluten-free because
		 * somebody set "vegetarian" would lose a fact nobody touched.
		 */
		$keep = array();

		foreach ( $this->get_diets() as $slug ) {
			if ( 'diet' !== ( Mixmymeals_Diet::types()[ $slug ]['axis'] ?? '' ) ) {
				$keep[] = $slug;
			}
		}

		if ( Mixmymeals_Diet::is_known( $type ) ) {
			$keep[] = $type;
		}

		$this->set_diets( $keep );
	}

	/*
	---------------------------------------------------------------------
	 * The reference product
	 * ------------------------------------------------------------------ */

	/**
	 * How to name the reference product of an ingredient on a shopping list.
	 *
	 * Brand first, because that is what a shelf is organised by: "Barilla Penne
	 * Rigate" is findable, "Penne Rigate" is a shape. Falls back to the plain
	 * title for products from before the brand became required.
	 *
	 * Empty when the ingredient has no reference — nothing was chosen, so
	 * nothing should be suggested.
	 *
	 * @param int $ingredient_id Ingredient ID.
	 * @return string Plain text, unescaped.
	 */
	public static function reference_label( int $ingredient_id ): string {

		$reference = self::get_reference_id( $ingredient_id );

		return $reference ? ( new self( $reference ) )->label() : '';
	}

	/**
	 * How to name *this* product — brand first, same rule as above.
	 *
	 * The static reference_label() resolves an ingredient to its chosen product
	 * and then asks this; anything that already holds a product asks it directly. The
	 * two used to be one static method, which read like an instance method at
	 * a glance and was called as one twice.
	 *
	 * @return string Plain text, unescaped. Empty when the product has no title.
	 */
	public function label(): string {

		if ( ! $this->is_valid() ) {
			return '';
		}

		$brand = $this->get_brand();
		$title = $this->get_title();

		if ( '' === $title ) {
			return '';
		}

		return '' === $brand ? $title : $brand . ' ' . $title;
	}

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
