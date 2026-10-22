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
	 * A row's product_id meaning "the ingredient's own figures, no packet" —
	 * never stored, only set where a meal's diet resolves a row to the
	 * ingredient itself (product_for_diet()). effective_product_id() reads it
	 * as no product, past the reference.
	 */
	public const PIN_OWN = -1;

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

		foreach ( self::NUTRITION_META as $key => $meta_key ) {

			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}

			$value = $data[ $key ];

			/*
			 * An empty value deletes the row instead of writing a 0. The
			 * product panel has always worked that way and says so on screen
			 * ("an empty field is not the same as zero"); this setter wrote
			 * `floatval( '' )` for every field the admin box left blank and so
			 * turned silence into a stated zero. That is what made missing
			 * data indistinguishable from a real 0 — and what let a recipe
			 * total count an undocumented ingredient as if it were water.
			 *
			 * The field list is NUTRITION_META rather than a second copy of
			 * it: the getter and the setter disagreeing about which keys exist
			 * is the kind of drift the pair below exists to prevent.
			 */
			if ( null === $value || '' === trim( (string) $value ) ) {
				delete_post_meta( $this->post_id, $meta_key );
				continue;
			}

			update_post_meta(
				$this->post_id,
				$meta_key,
				sanitize_text_field( (string) $value )
			);
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

		/*
		 * The eighth figure, added in 3.52.0, and the only one of the EU's
		 * mandatory seven this plugin did not store. `_mmm_` rather than
		 * `_rmm_`: the old prefix is a leftover from the plugin this one grew
		 * out of, and new meta has not used it for years. Nothing migrates —
		 * the key simply has no value on any ingredient until somebody enters
		 * one, and an unstated figure is exactly what the lists built on
		 * unstated_nutrition_keys() are for.
		 */
		'saturates'    => '_mmm_saturates',
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
		return $this->get_nutrition_pinned( 0 );
	}

	/**
	 * The nutrition of this ingredient as one recipe row actually cooks it.
	 *
	 * A recipe row may name a concrete product; that choice outranks the
	 * ingredient's reference, because it says which packet goes into *this*
	 * dish. Nothing pinned, or a product belonging to a different ingredient,
	 * falls back to the reference — so this is the same answer get_nutrition()
	 * gives, which is why that method is now a call to this one rather than a
	 * second implementation of the merge.
	 *
	 * @param int $pinned Product ID from the recipe row, 0 when none.
	 * @return array<string,float|null>
	 */
	public function get_nutrition_pinned( int $pinned ): array {

		if ( ! $this->is_valid() ) {
			return array();
		}

		return self::apply_product(
			$this->read_own_nutrition(),
			self::effective_product_id( $this->post_id, $pinned )
		);
	}

	/**
	 * Which product supplies this ingredient's figures for a given recipe row.
	 *
	 * The single place that decides it, so nutrition, price, diet, the shopping
	 * list and the product comparison cannot disagree about which packet is in
	 * use. A pinned product wins over the ingredient's reference; a pinned
	 * product that hangs off a different ingredient counts as nothing pinned,
	 * exactly as Mixmymeals_Product::get_reference_id() re-validates its own
	 * stored pointer on every read.
	 *
	 * @param int $ingredient_id The ingredient.
	 * @param int $pinned        Product ID from the recipe row, 0 when none.
	 * @return int Product ID, or 0 when neither the row nor the ingredient names one.
	 */
	public static function effective_product_id( int $ingredient_id, int $pinned = 0 ): int {

		if ( self::PIN_OWN === $pinned ) {
			return 0;
		}

		$pin = self::pinned_product_id( $ingredient_id, $pinned );

		return $pin > 0 ? $pin : Mixmymeals_Product::get_reference_id( $ingredient_id );
	}

	/**
	 * The product a recipe row names, once validated — and nothing else.
	 *
	 * Separate from effective_product_id() because the two answer different
	 * questions. That one asks which packet supplies the figures, and the
	 * ingredient's reference is a perfectly good answer. This one asks whether
	 * *the recipe* decided, which the reference is not: it is the site's
	 * default for the ingredient, not a statement about this dish. The product
	 * comparison turns on exactly that difference, and so does whether a
	 * recipe's ingredient list names an article at all.
	 *
	 * @param int $ingredient_id The ingredient of the row.
	 * @param int $pinned        The row's product_id.
	 * @return int The product ID, or 0 when the row names none or names one
	 *             belonging to a different ingredient.
	 */
	public static function pinned_product_id( int $ingredient_id, int $pinned ): int {

		if ( $pinned <= 0 ) {
			return 0;
		}

		$product = new Mixmymeals_Product( $pinned );

		return $product->is_valid() && $product->get_ingredient_id() === $ingredient_id ? $pinned : 0;
	}

	/**
	 * The packet a row has to be bought as for a dish to hold a diet.
	 *
	 * The concrete side of get_diet_suitabilities(): that one says an
	 * ingredient *can* be vegan, this one says *which* packet makes it so. The
	 * candidates are the same — the ingredient's own classification and every
	 * product the recipe has not ruled out — so a recipe "vegan-capable" by
	 * that rule always resolves here.
	 *
	 * The row's own choice is kept wherever it already holds the diet: a
	 * recipe pinning a vegan packet is not second-guessed. Otherwise the
	 * reference comes first, then the products in their stable order (by ID),
	 * and last the ingredient's own figures (PIN_OWN), which are what the
	 * classification "normally vegan" describes.
	 *
	 * @param int      $ingredient_id The ingredient of the row.
	 * @param int      $pinned        The row's product_id.
	 * @param string   $diet          Diet slug the dish is cooked as.
	 * @param int[]    $excluded      The row's excluded_products.
	 * @return int|null The product_id to cook the row with (possibly the row's
	 *                  own, or PIN_OWN), null when nothing available holds it.
	 */
	public static function product_for_diet( int $ingredient_id, int $pinned, string $diet, array $excluded = array() ): ?int {

		$ingredient = new self( $ingredient_id );

		if ( ! $ingredient->is_valid() || '' === $diet ) {
			return null;
		}

		$holds = static fn( array $slugs ): bool => in_array( $diet, Mixmymeals_Diet::expand( $slugs ), true );

		if ( $holds( $ingredient->get_diets_pinned( $pinned ) ) ) {
			return $pinned;
		}

		$own       = Mixmymeals_Diet::for_post( $ingredient_id );
		$reference = Mixmymeals_Product::get_reference_id( $ingredient_id );
		$products  = Mixmymeals_Product::for_ingredient( $ingredient_id );

		// The reference first: it is the packet the site already plans with.
		usort(
			$products,
			static fn( $a, $b ) => (int) ( $b->get_id() === $reference ) <=> (int) ( $a->get_id() === $reference )
		);

		foreach ( $products as $product ) {

			$id = $product->get_id();

			if ( in_array( $id, $excluded, true ) ) {
				continue;
			}

			if ( $holds( Mixmymeals_Diet::apply_product( $own, $product->get_diets() ) ) ) {
				return $id;
			}
		}

		return $holds( $own ) ? self::PIN_OWN : null;
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
	 * Whether anybody has stated this ingredient's nutrition at all.
	 *
	 * The one rule for that question. The recipe list's coverage column, the
	 * publish gate on the ingredient screen and `/audit` all ask it, so they
	 * cannot disagree about which ingredients are still blank — the audit used
	 * to ask `empty( $nutrition['calories_100'] )` in a line of its own and
	 * nothing else asked anywhere.
	 *
	 * **Value-based, not null-based**, although read_own_nutrition() carefully
	 * distinguishes the two. Until 3.52.0 the admin box saved `floatval( '' )`
	 * — a hard 0 — into every field left empty, so on an install that has been
	 * running, an unfilled ingredient carries seven zeros and not seven absent
	 * rows. A rule turning on `null` would declare all of them filled in and
	 * find nothing to fix. The single false positive is an ingredient that
	 * really is zero throughout, water being the only one anyone enters; it
	 * reads as undocumented, which is why every list built on this says "no
	 * nutrition data" rather than accusing anybody of an error.
	 *
	 * Resolved through the product like every other read, so an ingredient
	 * whose reference product carries the figures counts as documented — it
	 * does have nutrition, it just does not store it itself.
	 *
	 * @param int $pinned Product ID from a recipe row, 0 when none.
	 * @return bool
	 */
	public function has_nutrition( int $pinned = 0 ): bool {

		foreach ( $this->get_nutrition_pinned( $pinned ) as $key => $value ) {
			/*
			 * The weight of one piece is not nutrition: an onion that knows it
			 * weighs 60 g and nothing else is exactly the case being looked
			 * for here.
			 */
			if ( 'weight_item' === $key ) {
				continue;
			}

			if ( null !== $value && (float) $value > 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The nutrition keys of an ingredient, in the order a food label lists
	 * them and without `weight_item`, which is not nutrition.
	 *
	 * @var string[]
	 */
	const NUTRITION_FIGURE_KEYS = array( 'calories_100', 'fat', 'saturates', 'carbs', 'sugar', 'fiber', 'protein', 'salt' );

	/**
	 * The sum key each ingredient figure adds up into, in label order.
	 *
	 * Only energy differs between the two spellings — an ingredient states it
	 * per 100 g — but every table that lists an ingredient's or a product's
	 * figures needs the pairing, and three of them each carried their own copy
	 * of it until 3.52.0. One copy, so an eighth figure is one line and not a
	 * hunt.
	 *
	 * @var array<string,string>
	 */
	const NUTRITION_KEY_MAP = array(
		'calories'  => 'calories_100',
		'fat'       => 'fat',
		'saturates' => 'saturates',
		'carbs'     => 'carbs',
		'sugar'     => 'sugar',
		'fiber'     => 'fiber',
		'protein'   => 'protein',
		'salt'      => 'salt',
	);

	/**
	 * Option prefix of the traffic-light thresholds, one option per figure:
	 * `mixmymeals_traffic_light_fat` and so on, each holding `low` and `high`.
	 */
	const TRAFFIC_LIGHT_OPTION_PREFIX = 'mixmymeals_traffic_light_';

	/**
	 * Thresholds per 100 g a site starts with, as array( low, high ).
	 *
	 * The UK Food Standards Agency's front-of-pack scheme for foods — the only
	 * published set that is widely recognised — and therefore only the four
	 * figures it covers. Energy, carbohydrate, protein and fibre start without
	 * a light: no scheme people know rates them per 100 g, and a light nobody
	 * has defined is an opinion dressed as a standard.
	 *
	 * @var array<string,float[]>
	 */
	const TRAFFIC_LIGHT_DEFAULTS = array(
		'fat'       => array( 3.0, 17.5 ),
		'saturates' => array( 1.5, 5.0 ),
		'sugar'     => array( 5.0, 22.5 ),
		'salt'      => array( 0.3, 1.5 ),
	);

	/**
	 * The four figures the light is meant for. The FSA's scheme rates these
	 * and nothing else; energy, carbohydrate, protein and fibre can still be
	 * given thresholds, but per 100 g they say little — a dense food is not a
	 * large portion, and fibre or protein worth having depends on the day.
	 *
	 * @var string[]
	 */
	const TRAFFIC_LIGHT_KEYS = array( 'fat', 'saturates', 'sugar', 'salt' );

	/**
	 * Option holding the portion rule: a serving providing more than this
	 * share of the reference intake of an upper-guide figure is rated high,
	 * whatever its density. Never saved means PORTION_SHARE_DEFAULT; saved
	 * empty or 0 switches the rule off.
	 */
	const PORTION_SHARE_OPTION = 'mixmymeals_light_portion_share';

	/**
	 * The FSA's own portion rule for front-of-pack labels: red above 30 % of
	 * the reference intake per portion. Without it the light rates density
	 * only, and a 600 g bowl of soup at "medium" per 100 g can carry most of a
	 * day's saturates — the case a nutritionist found on the live site.
	 */
	const PORTION_SHARE_DEFAULT = 30.0;

	/**
	 * Above this much fat per 100 g, a stated 0 g of saturates is read as a
	 * field nobody filled in rather than as a fact: the pre-3.52.0 save wrote
	 * 0 into every blank field, and saturates was added to an existing
	 * catalogue in 3.52.0. Fat without any saturates exists — but not in
	 * amounts above 3 g, where it would earn a green light it never earned.
	 */
	const SATURATES_GAP_FAT = 3.0;

	/**
	 * Whether a set of figures states 0 g saturates beside more than 3 g fat —
	 * a gap that looks like a value. See SATURATES_GAP_FAT.
	 *
	 * @param array $nutrition Ingredient figures per 100 g (own keys).
	 * @return bool
	 */
	public static function saturates_zero_is_gap( array $nutrition ): bool {

		$saturates = $nutrition['saturates'] ?? null;
		$fat       = $nutrition['fat'] ?? null;

		return is_numeric( $saturates ) && 0.0 === (float) $saturates
			&& is_numeric( $fat ) && (float) $fat > self::SATURATES_GAP_FAT;
	}

	/**
	 * The portion rule's share of the reference intake, in per cent; 0 when
	 * the site switched it off.
	 *
	 * @return float
	 */
	public static function traffic_light_portion_share(): float {

		$stored = get_option( self::PORTION_SHARE_OPTION, false );

		if ( false === $stored ) {
			$share = self::PORTION_SHARE_DEFAULT;
		} else {
			$share = is_numeric( $stored ) ? (float) $stored : 0.0;
		}

		return max( 0.0, (float) apply_filters( 'mixmymeals_traffic_light_portion_share', $share ) );
	}

	/**
	 * Sanitiser for the portion share: a percentage from 1 to 100, or '' for
	 * off. Stored as a string so an emptied field survives as "off" rather
	 * than as `false`, which would bring the default back.
	 *
	 * @param mixed $input Posted value.
	 * @return string
	 */
	public static function sanitize_portion_share( $input ): string {

		$raw = str_replace( ',', '.', trim( (string) $input ) );

		if ( '' === $raw || ! is_numeric( $raw ) || (float) $raw <= 0 ) {
			return '';
		}

		return (string) min( 100.0, (float) $raw );
	}

	/**
	 * The thresholds in force, keyed by sum key (`calories`, not
	 * `calories_100`). A figure is missing from the result when it has no
	 * light — no thresholds, or thresholds that contradict each other.
	 *
	 * An option that was never saved falls back to TRAFFIC_LIGHT_DEFAULTS; one
	 * saved empty means the site switched that light off, which is why the
	 * two are told apart by `false` rather than by emptiness.
	 *
	 * @return array<string, array{low:float, high:float}>
	 */
	public static function traffic_light_thresholds(): array {

		$out = array();

		foreach ( Mixmymeals_Meal_Plan::NUTRITION_KEYS as $key ) {

			$stored = get_option( self::TRAFFIC_LIGHT_OPTION_PREFIX . $key, false );

			if ( false === $stored ) {
				$pair = self::TRAFFIC_LIGHT_DEFAULTS[ $key ] ?? null;
			} else {
				$pair = is_array( $stored ) ? array( $stored['low'] ?? '', $stored['high'] ?? '' ) : null;
			}

			if ( ! $pair || ! is_numeric( $pair[0] ) || ! is_numeric( $pair[1] ) ) {
				continue;
			}

			$low  = (float) $pair[0];
			$high = (float) $pair[1];

			// Upside-down thresholds would make "medium" an empty band and turn
			// every reading into one of the two ends. Off rather than wrong.
			if ( $low < 0 || $high < $low ) {
				continue;
			}

			$out[ $key ] = array(
				'low'  => $low,
				'high' => $high,
			);
		}

		return (array) apply_filters( 'mixmymeals_traffic_light_thresholds', $out );
	}

	/**
	 * Where one figure per 100 g sits, and what colour that is.
	 *
	 * The amount (`low` up to the low threshold, `high` above the high one,
	 * `medium` between) is a fact; the colour is a judgement and follows
	 * Mixmymeals_Meal_Plan::nutrition_direction(): for a target figure —
	 * fibre, protein — a high amount is green, for everything else red. That
	 * includes energy and carbohydrate, whose direction is neutral: a site
	 * that gives them thresholds rates density, where less is the lighter food.
	 *
	 * **Rated on the figure as printed.** The value is rounded the way
	 * Mixmymeals_Meal_Plan::format_nutrition_value() rounds it before it is
	 * compared, so "3,0 g" beside a threshold of "up to 3 g" reads low, and
	 * never "medium" on the strength of a digit nobody can see.
	 *
	 * **The portion rule.** Given what one serving carries, an upper-guide
	 * figure (`limit`) whose serving provides more than
	 * traffic_light_portion_share() of the reference intake is high and red
	 * whatever its density — `basis` then says `serving`. Only upward: a small
	 * portion of a salty food stays as salty as it is.
	 *
	 * Null is not zero. A figure nobody stated gets no light, never a green
	 * one — the light would otherwise be most reassuring exactly where least
	 * is known.
	 *
	 * @param string     $key         Sum key, as in Mixmymeals_Meal_Plan::NUTRITION_KEYS.
	 * @param float|null $per_100g    The figure per 100 g.
	 * @param float|null $per_serving The figure per serving, for the portion
	 *                                rule; null where there is no serving
	 *                                (an ingredient, a plan's mix).
	 * @return array{amount:string, colour:string, basis:string}|array{} Empty when there is no light.
	 */
	public static function traffic_light( string $key, ?float $per_100g, ?float $per_serving = null ): array {

		if ( null === $per_100g ) {
			return array();
		}

		$thresholds = self::traffic_light_thresholds()[ $key ] ?? null;

		if ( ! $thresholds ) {
			return array();
		}

		$value = Mixmymeals_Meal_Plan::round_nutrition_value( $key, $per_100g );

		if ( $value <= $thresholds['low'] ) {
			$amount = 'low';
		} elseif ( $value > $thresholds['high'] ) {
			$amount = 'high';
		} else {
			$amount = 'medium';
		}

		$basis = '100g';
		$share = self::traffic_light_portion_share();

		if (
			'high' !== $amount
			&& null !== $per_serving
			&& $share > 0
			&& 'limit' === Mixmymeals_Meal_Plan::nutrition_direction( $key )
		) {
			// The share as the reference-intake column prints it: whole per cent.
			$percent = Mixmymeals_Meal_Plan::nutrition_percentages( array( $key => $per_serving ) )[ $key ];

			if ( $percent > $share ) {
				$amount = 'high';
				$basis  = 'serving';
			}
		}

		$colour = 'amber';

		if ( 'medium' !== $amount ) {
			$good   = 'target' === Mixmymeals_Meal_Plan::nutrition_direction( $key ) ? 'high' : 'low';
			$colour = $amount === $good ? 'green' : 'red';
		}

		return array(
			'amount' => $amount,
			'colour' => $colour,
			'basis'  => $basis,
		);
	}

	/**
	 * The word for an amount, which is what the light says in text: colour
	 * alone is lost on a screen reader, a printout and one reader in twelve.
	 *
	 * @param string $amount low, medium or high.
	 * @param string $basis  `serving` when the portion rule made it high:
	 *                       the per-100 g figure beside it may well read
	 *                       medium, and the word has to say why it does not.
	 * @return string
	 */
	public static function traffic_light_label( string $amount, string $basis = '100g' ): string {

		if ( 'high' === $amount && 'serving' === $basis ) {
			/* translators: Nutrition traffic light: one serving provides a large share of the reference intake. */
			return _x( 'high per serving', 'traffic light', 'mixmymeals' );
		}

		$labels = array(
			/* translators: Nutrition traffic light: the amount per 100 g is low. */
			'low'    => _x( 'low', 'traffic light', 'mixmymeals' ),
			/* translators: Nutrition traffic light: the amount per 100 g is medium. */
			'medium' => _x( 'medium', 'traffic light', 'mixmymeals' ),
			/* translators: Nutrition traffic light: the amount per 100 g is high. */
			'high'   => _x( 'high', 'traffic light', 'mixmymeals' ),
		);

		return $labels[ $amount ] ?? '';
	}

	/**
	 * The footnote that explains the light, built like
	 * Mixmymeals_Meal_Plan::reference_intake_note(): every figure it names
	 * comes from the setting in force, so the sentence cannot promise a
	 * threshold the light does not use.
	 *
	 * Says "FSA" only while the thresholds are the FSA's; a site that set its
	 * own is told its own back, unattributed.
	 *
	 * @param bool $portion Whether the light beside it can follow the
	 *                      portion rule (recipe and meal, not ingredient or
	 *                      plan).
	 * @return string '' when no figure has a light.
	 */
	public static function traffic_light_note( bool $portion = false ): string {

		$thresholds = self::traffic_light_thresholds();

		if ( ! $thresholds ) {
			return '';
		}

		$parts  = array();
		$is_fsa = true;

		foreach ( Mixmymeals_Meal_Plan::NUTRITION_KEYS as $key ) {

			if ( ! isset( $thresholds[ $key ] ) ) {
				if ( isset( self::TRAFFIC_LIGHT_DEFAULTS[ $key ] ) ) {
					$is_fsa = false;
				}
				continue;
			}

			$default = self::TRAFFIC_LIGHT_DEFAULTS[ $key ] ?? null;
			$is_fsa  = $is_fsa && $default
				&& abs( $default[0] - $thresholds[ $key ]['low'] ) < 0.0001
				&& abs( $default[1] - $thresholds[ $key ]['high'] ) < 0.0001;

			$parts[] = sprintf(
				/* translators: 1: nutrition figure, e.g. "Salt", 2: low threshold, e.g. "0,3 g", 3: high threshold, e.g. "1,5 g". */
				__( '%1$s up to %2$s / above %3$s', 'mixmymeals' ),
				self::nutrition_label( $key ),
				Mixmymeals_Meal_Plan::format_nutrition_value( $key, $thresholds[ $key ]['low'] ),
				Mixmymeals_Meal_Plan::format_nutrition_value( $key, $thresholds[ $key ]['high'] )
			);
		}

		$note = sprintf(
			$is_fsa
				/* translators: %s: semicolon-separated thresholds, e.g. "Fat up to 3,0 g / above 17,5 g; Salt up to 0,30 g / above 1,50 g". */
				? __( 'Traffic light: rated per 100 g as low, medium or high against the thresholds of the UK Food Standards Agency (%s).', 'mixmymeals' )
				/* translators: %s: semicolon-separated thresholds, e.g. "Fat up to 3,0 g / above 17,5 g; Salt up to 0,30 g / above 1,50 g". */
				: __( 'Traffic light: rated per 100 g as low, medium or high against these thresholds: %s.', 'mixmymeals' ),
			implode( '; ', $parts )
		);

		$share = self::traffic_light_portion_share();

		if ( $portion && $share > 0 ) {

			$limits = array();

			foreach ( array_keys( $thresholds ) as $key ) {
				if ( 'limit' === Mixmymeals_Meal_Plan::nutrition_direction( $key ) ) {
					$limits[] = self::nutrition_label( $key );
				}
			}

			if ( $limits ) {
				$note .= ' ' . sprintf(
					/* translators: 1: percentage, e.g. "30 %", 2: comma-separated nutrition figures. */
					__( 'Where one serving provides more than %1$s of the reference intake, the light reads "high per serving" regardless (%2$s).', 'mixmymeals' ),
					sprintf( '%s %%', number_format_i18n( $share ) ),
					implode( ', ', $limits )
				);
			}
		}

		return (string) apply_filters( 'mixmymeals_traffic_light_note', $note, $portion );
	}

	/**
	 * Sanitiser for one traffic-light option.
	 *
	 * Stores both thresholds as strings, empty when not given: an empty pair
	 * is how a site switches a light off, and it has to survive the save as
	 * something other than `false`, which would bring the default back.
	 *
	 * @param mixed $input Posted value.
	 * @return array{low:string, high:string}
	 */
	public static function sanitize_traffic_light( $input ): array {

		$input = is_array( $input ) ? $input : array();
		$out   = array();

		foreach ( array( 'low', 'high' ) as $end ) {
			$raw         = str_replace( ',', '.', trim( (string) ( $input[ $end ] ?? '' ) ) );
			$out[ $end ] = ( '' !== $raw && is_numeric( $raw ) && (float) $raw >= 0 ) ? (string) (float) $raw : '';
		}

		// An inverted pair is kept as typed, so the field still shows it, and
		// traffic_light_thresholds() leaves that light off until it is fixed.
		if ( '' !== $out['low'] && '' !== $out['high'] && (float) $out['high'] < (float) $out['low'] ) {
			add_settings_error(
				'mixmymeals_traffic_light',
				'mixmymeals_traffic_light_order',
				__( 'A traffic-light threshold for "high" is below the one for "low"; that light stays off until it is corrected.', 'mixmymeals' )
			);
		}

		return $out;
	}

	/**
	 * What the eight figures cannot all be true at once.
	 *
	 * A typo in a nutrition field is copied into every recipe that uses the
	 * ingredient, into every plan holding such a recipe, into the mails, the
	 * PDF and the schema.org markup, and nothing downstream can notice: a
	 * misread decimal point looks exactly like a dense food. These are the
	 * contradictions arithmetic can see without knowing what the food is.
	 *
	 * **Warnings, never a block.** The figures come off a packet, packets
	 * round their own numbers, and a rule that refused the save would make a
	 * legitimate label unenterable. Each message names both figures it
	 * compares, because "implausible" on its own leaves the editor hunting.
	 *
	 * @param array<string,float|null> $nutrition Figures per 100 g, keyed as
	 *                                            Mixmymeals_Ingredient::get_nutrition()
	 *                                            returns them.
	 * @return string[] Translated sentences, empty when nothing contradicts.
	 */
	public static function nutrition_warnings( array $nutrition ): array {

		$value = static function ( string $key ) use ( $nutrition ): ?float {
			return isset( $nutrition[ $key ] ) && is_numeric( $nutrition[ $key ] )
				? (float) $nutrition[ $key ]
				: null;
		};

		$grams = static function ( float $number ): string {
			return number_format_i18n( $number, 1 );
		};

		$warnings = array();

		$kcal      = $value( 'calories_100' );
		$fat       = $value( 'fat' );
		$saturates = $value( 'saturates' );
		$carbs     = $value( 'carbs' );
		$sugar     = $value( 'sugar' );
		$fiber     = $value( 'fiber' );
		$protein   = $value( 'protein' );
		$salt      = $value( 'salt' );

		/*
		 * The two "of which" rows cannot exceed what they are part of. These
		 * catch the most common slip of all: a figure typed into the row below
		 * the one it belongs to.
		 */
		if ( null !== $saturates && null !== $fat && $saturates > $fat ) {
			$warnings[] = sprintf(
				/* translators: 1: grams of saturates, 2: grams of total fat. */
				__( 'Saturates (%1$s g) are above total fat (%2$s g).', 'mixmymeals' ),
				$grams( $saturates ),
				$grams( $fat )
			);
		}

		if ( null !== $sugar && null !== $carbs && $sugar > $carbs ) {
			$warnings[] = sprintf(
				/* translators: 1: grams of sugars, 2: grams of total carbohydrate. */
				__( 'Sugars (%1$s g) are above total carbohydrate (%2$s g).', 'mixmymeals' ),
				$grams( $sugar ),
				$grams( $carbs )
			);
		}

		/*
		 * 100 g of a food cannot hold more than 100 g of anything. Fibre is
		 * added separately because EU labelling states carbohydrate *without*
		 * it. Half a gram of slack, so a label whose own rounding adds up to
		 * 100.3 is left alone.
		 */
		$mass = 0.0;

		foreach ( array( $fat, $carbs, $fiber, $protein, $salt ) as $part ) {
			$mass += null === $part ? 0.0 : $part;
		}

		if ( $mass > 100.5 ) {
			$warnings[] = sprintf(
				/* translators: %s: a weight in grams. */
				__( 'Fat, carbohydrate, fibre, protein and salt add up to %s g per 100 g.', 'mixmymeals' ),
				$grams( $mass )
			);
		}

		/*
		 * The energy a label states has to follow from its macros: 9 kcal per
		 * gram of fat, 4 for carbohydrate and protein, 2 for fibre — the EU's
		 * own conversion factors. A fifth is allowed either way, which is wide
		 * enough for polyols, organic acids, alcohol and a label's rounding,
		 * and narrow enough to catch a decimal point in the wrong place.
		 */
		$macros = 9.0 * (float) $fat + 4.0 * (float) $carbs + 4.0 * (float) $protein + 2.0 * (float) $fiber;

		if ( null !== $kcal && $kcal > 0 && $macros > 0 && ( $kcal > $macros * 1.2 || $kcal < $macros * 0.8 ) ) {
			$warnings[] = sprintf(
				/* translators: 1: stated kilocalories, 2: kilocalories the macros work out to. */
				__( 'Energy (%1$s kcal) does not match the macros, which work out to about %2$s kcal per 100 g.', 'mixmymeals' ),
				number_format_i18n( round( $kcal ) ),
				number_format_i18n( round( $macros ) )
			);
		}

		// Pure fat is about 900 kcal per 100 g; nothing edible is above it.
		if ( null !== $kcal && $kcal > 900 ) {
			$warnings[] = sprintf(
				/* translators: %s: stated kilocalories per 100 g. */
				__( 'Energy of %s kcal per 100 g is above what any food reaches (about 900 kcal, for pure fat).', 'mixmymeals' ),
				number_format_i18n( round( $kcal ) )
			);
		}

		foreach ( self::NUTRITION_FIGURE_KEYS as $key ) {

			if ( 'calories_100' === $key ) {
				continue;
			}

			$figure = $value( $key );

			if ( null !== $figure && $figure > 100 ) {
				$warnings[] = sprintf(
					/* translators: 1: name of a nutrition figure, 2: the stated amount in grams. */
					__( '%1$s: %2$s g per 100 g is more than 100 g.', 'mixmymeals' ),
					self::nutrition_label( $key ),
					$grams( $figure )
				);
			}
		}

		return $warnings;
	}

	/**
	 * Which of the seven figures nobody has stated.
	 *
	 * The finer-grained question beside has_nutrition(), and the reason both
	 * exist: has_nutrition() answers whether an ingredient may be published —
	 * one figure is enough there, because a packet that states energy and fat
	 * and stops is a real packet and refusing it would make the field
	 * unfillable. This answers what the recipe sums are actually missing, and
	 * there every empty field counts: a blank fibre row goes into every
	 * recipe's fibre total as 0, exactly as a blank ingredient went into the
	 * energy total before 3.52.0.
	 *
	 * **A stated 0 is an answer**, so only `null` is a gap. Note what that
	 * means on an install older than 3.52.0: the admin box wrote
	 * `floatval( '' )` into every blank field, so its blanks are stored as
	 * zeros and read as stated here. The figures entered from now on are
	 * exact, and the old rows can be cleared by hand — but this method will
	 * not invent a gap it cannot see, because a warning nobody can act on is
	 * worse than none.
	 *
	 * @param int $pinned Product ID from a recipe row, 0 when none.
	 * @return string[] The unstated keys, in label order. Empty when all seven
	 *                  are stated.
	 */
	public function unstated_nutrition_keys( int $pinned = 0 ): array {

		$nutrition = $this->get_nutrition_pinned( $pinned );
		$unstated  = array();

		foreach ( self::NUTRITION_FIGURE_KEYS as $key ) {

			if ( ! array_key_exists( $key, $nutrition ) || null === $nutrition[ $key ] ) {
				$unstated[] = $key;
			}
		}

		return $unstated;
	}

	/**
	 * The label of one nutrition key.
	 *
	 * From Mixmymeals_Meal_Plan::nutrition_labels(), the wording every
	 * nutrition table on the site already uses, so a gap is named exactly as
	 * the row it belongs to. Only the energy key differs between the two
	 * spellings — `calories_100` on an ingredient, `calories` in a sum.
	 *
	 * @param string $key An ingredient nutrition key.
	 * @return string The label, or the key itself when it is none of the seven.
	 */
	public static function nutrition_label( string $key ): string {

		$labels = Mixmymeals_Meal_Plan::nutrition_labels();
		$key    = 'calories_100' === $key ? 'calories' : $key;

		/*
		 * The two indented rows are the exception, and the reason is the
		 * wording's own: in a nutrition table they stand under the figure they
		 * belong to and read "of which sugars", "of which saturates", which is
		 * only a sentence while the row above is there. Named on their own —
		 * in a list of empty fields, beside a percentage in the recipe list,
		 * in the note under a plan's table — they have to be nouns.
		 */
		$standalone = array(
			'sugar'     => __( 'Sugars', 'mixmymeals' ),
			'saturates' => __( 'Saturates', 'mixmymeals' ),
		);

		if ( isset( $standalone[ $key ] ) ) {
			return $standalone[ $key ];
		}

		return $labels[ $key ] ?? $key;
	}

	/**
	 * Every ingredient nobody has stated nutrition for, newest data model
	 * included: the backlog, in one list.
	 *
	 * Drafts and private ones count. The question is which rows need filling
	 * in, and a draft is where require_ingredient_nutrition() puts exactly
	 * these — leaving them out would hide the ones the gate has already acted
	 * on.
	 *
	 * Primed rather than walked: has_nutrition() resolves through the
	 * reference product, so an unprimed pass costs a query per ingredient that
	 * has one, and the count under the ingredient list pays it on every view.
	 * Three queries now — the ids, their meta, the products their meta names —
	 * and the loop runs entirely out of the object cache. Held for the request
	 * because the list's filter link and its query both ask.
	 *
	 * @return int[] Ingredient IDs, ordered by title.
	 */
	public static function ids_without_nutrition(): array {

		static $cache = null;

		if ( null !== $cache ) {
			return $cache;
		}

		$ids = array_map(
			'intval',
			(array) get_posts(
				array(
					'post_type'      => 'mmm_ingredient',
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'orderby'        => 'title',
					'order'          => 'ASC',
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			)
		);

		if ( ! $ids ) {
			return $cache = array();
		}

		update_meta_cache( 'post', $ids );

		$products = array();

		foreach ( $ids as $id ) {

			$product_id = (int) get_post_meta( $id, Mixmymeals_Product::META_REFERENCE, true );

			if ( $product_id > 0 ) {
				$products[] = $product_id;
			}
		}

		if ( $products ) {
			// Through WP_Query rather than _prime_post_caches(): same priming
			// of the post and meta caches, without reaching for a function
			// core marks private.
			get_posts(
				array(
					'post_type'      => Mixmymeals_Product::POST_TYPE,
					'post_status'    => 'any',
					'post__in'       => $products,
					'posts_per_page' => count( $products ),
					'no_found_rows'  => true,
				)
			);
		}

		$without = array();

		foreach ( $ids as $id ) {

			if ( ! ( new self( $id ) )->has_nutrition() ) {
				$without[] = $id;
			}
		}

		return $cache = $without;
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

		// From NUTRITION_META, not from a list of its own: a key missing here
		// would survive a purge and reappear on the next read.
		foreach ( self::NUTRITION_META as $meta_key ) {
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

	/**
	 * What a given weight of this ingredient costs.
	 *
	 * @param float $grams  Weight in grams.
	 * @param int   $pinned Product the recipe row names, 0 for none.
	 * @return float 0 when nobody states a price.
	 */
	public function calculate_cost_for_weight( float $grams, int $pinned = 0 ): float {
		if ( $grams <= 0 ) {
			return 0.0;
		}
		return ( $grams / 100.0 ) * $this->get_price_per_100g_pinned( $pinned );
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
	 * Cooking factor: what 1 g of this ingredient weighs once cooked.
	 *
	 * Dry pasta takes up water (about 2.4), minced meat loses it (about 0.75),
	 * spinach collapses (about 0.3). Water carries no nutrients, so the factor
	 * never changes how much energy, fat or salt a dish holds — only what it
	 * weighs on the plate, and therefore every figure *per 100 g* and the
	 * traffic light drawn from it. Summing raw weights put a pasta dish a
	 * traffic-light step too high, because the water the pasta absorbs was
	 * nowhere in the sum.
	 *
	 * Null when nobody has said, which means "no correction" — not 1.0 written
	 * down, which is a statement (the weight does not change) and is kept as one.
	 *
	 * @var string
	 */
	const META_COOKING_FACTOR = '_mmm_cooking_factor';

	/**
	 * Upper bound for a cooking factor. Dried mushrooms and seaweed reach about
	 * 8–10; anything far beyond that is a typo, and a typo here would multiply
	 * a dish's weight without a single warning.
	 */
	const COOKING_FACTOR_MAX = 20.0;

	/**
	 * Reads a cooking factor from a form or a stored row: a decimal comma is
	 * accepted, empty, zero, negative and absurd values become null.
	 *
	 * @param mixed $raw Value.
	 * @return float|null
	 */
	public static function normalise_cooking_factor( $raw ): ?float {

		if ( null === $raw || is_array( $raw ) ) {
			return null;
		}

		$raw = str_replace( ',', '.', trim( (string) $raw ) );

		if ( '' === $raw || ! is_numeric( $raw ) ) {
			return null;
		}

		$factor = (float) $raw;

		return $factor > 0 && $factor <= self::COOKING_FACTOR_MAX ? round( $factor, 3 ) : null;
	}

	/**
	 * The factor the ingredient itself states, before any product.
	 *
	 * @return float|null
	 */
	public function get_own_cooking_factor(): ?float {

		return $this->is_valid()
			? self::normalise_cooking_factor( get_post_meta( $this->post_id, self::META_COOKING_FACTOR, true ) )
			: null;
	}

	/**
	 * Stores the ingredient's own factor; null (or anything invalid) clears it.
	 *
	 * @param float|null $factor Cooked weight per gram raw.
	 * @return void
	 */
	public function set_cooking_factor( ?float $factor ): void {

		if ( ! $this->is_valid() ) {
			return;
		}

		$factor = self::normalise_cooking_factor( $factor );

		if ( null === $factor ) {
			delete_post_meta( $this->post_id, self::META_COOKING_FACTOR );
			return;
		}

		update_post_meta( $this->post_id, self::META_COOKING_FACTOR, $factor );
	}

	/**
	 * The cooking factor in force for one recipe row.
	 *
	 * The product that supplies the row's figures (pinned, else the reference —
	 * effective_product_id(), the same decision as nutrition and price) wins
	 * where it states a factor; a product that does not falls through to the
	 * ingredient's own, and an ingredient without one means no correction.
	 *
	 * @param int $pinned Product ID from the recipe row, 0 when none.
	 * @return float|null
	 */
	public function get_cooking_factor_pinned( int $pinned ): ?float {

		if ( ! $this->is_valid() ) {
			return null;
		}

		$product_id = self::effective_product_id( $this->post_id, $pinned );

		if ( $product_id ) {
			$factor = ( new Mixmymeals_Product( $product_id ) )->get_cooking_factor();

			if ( null !== $factor ) {
				return $factor;
			}
		}

		return $this->get_own_cooking_factor();
	}

	/**
	 * The factor in force without a recipe row, i.e. through the reference.
	 *
	 * @return float|null
	 */
	public function get_cooking_factor(): ?float {
		return $this->get_cooking_factor_pinned( 0 );
	}

	/**
	 * What a raw amount of this ingredient weighs once cooked.
	 *
	 * The one place a raw weight becomes a cooked one, so the recipe, the meal
	 * and the plan cannot disagree about how heavy a portion is. Only weights
	 * that something is divided *by* go through here; the nutrients are still
	 * computed from the raw weight, because the figures per 100 g describe
	 * the ingredient as bought.
	 *
	 * @param float $raw    Raw weight in grams.
	 * @param int   $pinned Product ID from the recipe row, 0 when none.
	 * @return float
	 */
	public function cooked_weight( float $raw, int $pinned = 0 ): float {

		$factor = $this->get_cooking_factor_pinned( $pinned );

		return null === $factor ? $raw : $raw * $factor;
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
		return $this->get_price_per_100g_pinned( 0 );
	}

	/**
	 * Price per 100 g as one recipe row buys it.
	 *
	 * Same rule as get_nutrition_pinned(): the product the row names wins, then
	 * the ingredient's reference, then the ingredient's own figure. A pinned
	 * product without a price falls through rather than costing nothing.
	 *
	 * @param int $pinned Product ID from the recipe row, 0 when none.
	 * @return float 0 when nobody states a price.
	 */
	public function get_price_per_100g_pinned( int $pinned ): float {
		if ( ! $this->is_valid() ) {
			return 0.0;
		}

		$product_id = self::effective_product_id( $this->post_id, $pinned );

		if ( $product_id ) {
			$product_price = ( new Mixmymeals_Product( $product_id ) )->get_price_per_100g();

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

		return $this->get_diets_pinned( 0 );
	}

	/**
	 * Everything in force for this ingredient as one recipe row cooks it.
	 *
	 * The third of the trio beside get_nutrition_pinned() and
	 * get_price_per_100g_pinned(), resolving through the same
	 * effective_product_id(): a pinned product decides for that row, and a
	 * product belonging elsewhere is ignored rather than trusted.
	 *
	 * @param int $pinned Product ID from the recipe row, 0 when none.
	 * @return string[] Known diet slugs.
	 */
	public function get_diets_pinned( int $pinned ): array {

		if ( ! $this->is_valid() ) {
			return array();
		}

		$own        = Mixmymeals_Diet::for_post( $this->post_id );
		$product_id = self::effective_product_id( $this->post_id, $pinned );

		if ( ! $product_id ) {
			return $own;
		}

		return Mixmymeals_Diet::apply_product(
			$own,
			( new Mixmymeals_Product( $product_id ) )->get_diets()
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
	public function get_diet_suitabilities( array $without = array() ): array {

		if ( ! $this->is_valid() ) {
			return array();
		}

		$sets = array( Mixmymeals_Diet::for_post( $this->post_id ) );

		/*
		 * $without sind die Pakete, die ein Rezept für sein Gericht
		 * ausgeschlossen hat. Die Zutat selbst kennt keine Rezepte, also wird
		 * die Liste hereingereicht — derselbe Weg wie bei
		 * get_nutrition_pinned(), und aus demselben Grund: eine Aussage über
		 * ein Gericht darf nicht an der Zutat hängen bleiben.
		 *
		 * Ohne Liste ist die Antwort die alte: eine Zutat ist „vegan
		 * möglich“, sobald irgendeines ihrer Pakete vegan ist.
		 */
		foreach ( Mixmymeals_Product::for_ingredient( $this->post_id ) as $product ) {

			if ( $without && in_array( $product->get_id(), $without, true ) ) {
				continue;
			}

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

		// A former key ("vegetarian") reaches the term that took it over.
		$type = Mixmymeals_Diet::resolve( $type );

		if ( '' !== $type ) {
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
