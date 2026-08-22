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
	 * differences to it. The anchor is the reference product where the
	 * ingredient has one — the packet actually in use — and otherwise a column
	 * of the ingredient's own typical values, which is what the dish is
	 * computed from when no product is chosen. Either way the anchor holds
	 * exactly $current, so it replaces the separate "as planned" column rather
	 * than sitting beside it as a row of ±0.
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
	 * @param array<int, array{grams:float, meals:int}> $weights Grams one person
	 *        eats per ingredient, and in how many dishes it occurs (0 to omit).
	 * @param array<string,float>                       $current The figures those
	 *        grams currently produce, in the caller's own unit.
	 * @param float                                     $divisor What the caller
	 *        divides by to reach that unit. 1.0 for a single serving.
	 * @return array<int, array{ingredient_id:int, ingredient:string, meals:int, current:array<string,float>, products:array}>
	 */
	public static function impact_groups( array $weights, array $current, float $divisor = 1.0 ): array {

		$keys    = Mixmymeals_Meal_Plan::NUTRITION_KEYS;
		$intake  = Mixmymeals_Meal_Plan::reference_intake();
		$divisor = max( 0.0000001, $divisor );

		$groups = array();

		foreach ( $weights as $ingredient_id => $info ) {

			$products = self::for_ingredient( (int) $ingredient_id );

			if ( count( $products ) < 2 ) {
				continue;
			}

			$ingredient = new Mixmymeals_Ingredient( (int) $ingredient_id );

			if ( ! $ingredient->is_valid() ) {
				continue;
			}

			$reference = self::get_reference_id( (int) $ingredient_id );
			$now       = $ingredient->get_nutrition();

			// What is in force right now — already resolved through whatever
			// product the ingredient points at, which is what the anchor column
			// states everywhere else in this table.
			$diet_now = $ingredient->get_diet_type();
			$factor    = (float) $info['grams'] / 100 / $divisor;

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

		if ( ! $this->is_valid() ) {
			return '';
		}

		$value = get_post_meta( $this->post_id, self::META_DIET_TYPE, true );

		return in_array( $value, array( 'vegan', 'vegetarian' ), true ) ? $value : '';
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

		if ( ! in_array( $type, array( 'vegan', 'vegetarian' ), true ) ) {
			delete_post_meta( $this->post_id, self::META_DIET_TYPE );
			return;
		}

		update_post_meta( $this->post_id, self::META_DIET_TYPE, $type );
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
