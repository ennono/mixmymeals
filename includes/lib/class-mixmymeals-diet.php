<?php
/**
 * Diet classification: taxonomy, vocabulary and the rule that carries it up.
 *
 * @package MixMyMeals Plugin/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mixmymeals_Diet
 *
 * Where a diet is *stated* — on an ingredient, on a product — it is a term of
 * the taxonomy `mmm_diet`. Where it is *derived* — a recipe, a meal, a plan —
 * it stays a calculation performed on read, so nothing has to be kept in sync
 * when an ingredient changes.
 *
 * Terms replaced a single meta field for one reason: `_mmm_diet_type` held one
 * value, so an ingredient could be vegan or gluten-free but never both. No
 * amount of vocabulary fixes that; a multi-valued store does.
 *
 * **The terms carry no meaning of their own.** A term says "this ingredient is
 * X"; it cannot say how X reaches a recipe, and diets do not agree on that:
 * vegan and vegetarian form a chain, gluten-free is an axis beside it, and a
 * diet like pescetarian would break the chain altogether. types() therefore
 * keeps the rule per known slug, and a term nobody registered is inert — it
 * sits on the ingredient and reaches no recipe. That is deliberate: this
 * classification decides whether a public "vegan" label appears, and an
 * invented term must not be able to produce one.
 */
class Mixmymeals_Diet {

	/**
	 * The taxonomy holding stated diets, shared by ingredients and products.
	 *
	 * @var string
	 */
	const TAXONOMY = 'mmm_diet';

	/**
	 * Legacy single-value meta, still read where nothing has been migrated.
	 *
	 * @var string
	 */
	const LEGACY_META_INGREDIENT = '_mmm_diet_type';

	/**
	 * Legacy single-value meta on products.
	 *
	 * @var string
	 */
	const LEGACY_META_PRODUCT = '_mmm_product_diet_type';

	/**
	 * How a diet a dish is only *capable* of is worded. One pattern for all of
	 * them, with %s standing for the diet's own name — so it carries a suffix
	 * ("%s tauglich"), a prefix ("Geeignet für %s") or anything else a site
	 * prefers, without four strings having to agree with each other.
	 */
	const CAPABLE_FORMAT_OPTION = 'mixmymeals_diet_capable_format';

	/**
	 * The diets this plugin knows how to reason about.
	 *
	 * Each entry carries what a term cannot:
	 *  - axis    Which question it answers. One axis holds one claim at a
	 *            time; a dish is vegan or vegetarian, never both, while
	 *            gluten-free is a separate question with its own answer.
	 *  - implies What follows from it. Vegan food suits a vegetarian, so a
	 *            vegan ingredient counts as vegetarian when a recipe is
	 *            reduced; the reverse does not hold.
	 *  - rank    Which wins when one axis holds several. Higher is stronger
	 *            and is what a label shows.
	 *
	 * An entry may add 'suitability' to word the capable form itself; without
	 * one it is composed from the label and the site's pattern, which is the
	 * normal case and what the setting governs.
	 *
	 * Filterable, so an add-on can teach the plugin a diet without touching
	 * this file. Registering the term alone is not enough and is not meant to
	 * be — see the class comment.
	 *
	 * @return array<string,array{label:string,axis:string,implies:string[],rank:int,schema:string,suitability?:string}>
	 */
	public static function types(): array {

		$types = array(
			'vegan'        => array(
				'label'       => __( 'Vegan', 'mixmymeals' ),
				'axis'        => 'diet',
				'implies'     => array( 'vegetarian' ),
				'rank'        => 20,
				'schema'      => 'https://schema.org/VeganDiet',
			),
			'vegetarian'   => array(
				'label'       => __( 'Vegetarian', 'mixmymeals' ),
				'axis'        => 'diet',
				'implies'     => array(),
				'rank'        => 10,
				'schema'      => 'https://schema.org/VegetarianDiet',
			),
			'gluten_free'  => array(
				'label'       => __( 'Gluten-free', 'mixmymeals' ),
				'axis'        => 'free_from',
				'implies'     => array(),
				'rank'        => 10,
				'schema'      => 'https://schema.org/GlutenFreeDiet',
			),
			'lactose_free' => array(
				'label'       => __( 'Lactose-free', 'mixmymeals' ),
				'axis'        => 'lactose',
				'implies'     => array(),
				'rank'        => 10,
				'schema'      => 'https://schema.org/LowLactoseDiet',
			),
		);

		/**
		 * Filters the diets the plugin knows how to reason about.
		 *
		 * @param array $types Slug => definition.
		 */
		return (array) apply_filters( 'mixmymeals_diet_types', $types );
	}

	/**
	 * Whether a slug is a diet the plugin can reason about.
	 *
	 * @param string $slug Term slug.
	 * @return bool
	 */
	public static function is_known( string $slug ): bool {
		return isset( self::types()[ $slug ] );
	}

	/**
	 * The label of a diet — a statement of fact about a thing.
	 *
	 * @param string $slug Term slug.
	 * @return string
	 */
	public static function label( string $slug ): string {
		return (string) ( self::types()[ $slug ]['label'] ?? '' );
	}

	/**
	 * The label of a diet a dish is *suitable* for.
	 *
	 * Whether a finished dish is vegan is settled at the shop, by which product
	 * lands in the basket. Where a recipe pins no product it can only be
	 * suitable to be cooked that way, and says so — "Vegan-capable" beside the
	 * bare "Vegan" that label() gives once the pins settle the question.
	 *
	 * @param string $slug Term slug.
	 * @return string
	 */
	public static function suitability_label( string $slug ): string {

		$label = self::label( $slug );

		if ( '' === $label ) {
			return '';
		}

		// An add-on may word its own; everything else is composed.
		$own = (string) ( self::types()[ $slug ]['suitability'] ?? '' );

		if ( '' !== $own ) {
			return $own;
		}

		return sprintf( self::capable_format(), $label );
	}

	/**
	 * The pattern the capable form is built with, %s standing for the diet.
	 *
	 * A stored pattern without %s is ignored rather than printed: it would
	 * render the same word for every diet, and a chip reading "tauglich" beside
	 * another reading "tauglich" says nothing at all.
	 *
	 * @return string
	 */
	public static function capable_format(): string {

		/* translators: %s: the name of a diet, e.g. "Vegan". */
		$default = __( '%s-capable', 'mixmymeals' );
		$stored  = trim( (string) get_option( self::CAPABLE_FORMAT_OPTION, '' ) );

		return ( '' !== $stored && str_contains( $stored, '%s' ) ) ? $stored : $default;
	}

	/**
	 * The schema.org RestrictedDiet URL of a diet, or ''.
	 *
	 * @param string $slug Term slug.
	 * @return string
	 */
	public static function schema_url( string $slug ): string {
		return (string) ( self::types()[ $slug ]['schema'] ?? '' );
	}

	/* ---------------------------------------------------------------------
	 * The algebra
	 * ------------------------------------------------------------------ */

	/**
	 * Adds what follows from a set of diets.
	 *
	 * @param string[] $slugs Stated diets.
	 * @return string[] Stated diets plus their implications, known ones only.
	 */
	public static function expand( array $slugs ): array {

		$types = self::types();
		$out   = array();

		foreach ( $slugs as $slug ) {

			if ( ! isset( $types[ $slug ] ) ) {
				continue;
			}

			$out[ $slug ] = true;

			foreach ( (array) $types[ $slug ]['implies'] as $implied ) {
				if ( isset( $types[ $implied ] ) ) {
					$out[ $implied ] = true;
				}
			}
		}

		return array_keys( $out );
	}

	/**
	 * Reduces the diets of the parts to the diets of the whole.
	 *
	 * A dish is X when every part of it is X — so the answer is the
	 * intersection of the parts, each expanded by what it implies. One part
	 * that states nothing empties the intersection, and the dish makes no
	 * claim at all. That is not a gap in the rule, it is the rule: an
	 * ingredient nobody has classified might be anything.
	 *
	 * Note what the intersection does with several axes on its own. Two vegan
	 * ingredients, one of them also gluten-free, intersect to vegan — the
	 * gluten-free claim falls away because the other ingredient never made it.
	 * No per-axis special case is needed for that.
	 *
	 * @param array<int,string[]> $sets Diets of each part.
	 * @return string[] Diets of the whole, expanded.
	 */
	public static function combine( array $sets ): array {

		if ( empty( $sets ) ) {
			return array();
		}

		$result = null;

		foreach ( $sets as $set ) {

			$expanded = self::expand( (array) $set );

			if ( empty( $expanded ) ) {
				return array();
			}

			$result = null === $result
				? $expanded
				: array_values( array_intersect( $result, $expanded ) );

			if ( empty( $result ) ) {
				return array();
			}
		}

		return (array) $result;
	}

	/**
	 * The union of several sets — what is reachable if each part is bought
	 * accordingly, rather than what every part already states.
	 *
	 * @param array<int,string[]> $sets Diets of each part.
	 * @return string[]
	 */
	public static function merge( array $sets ): array {

		$out = array();

		foreach ( $sets as $set ) {
			foreach ( self::expand( (array) $set ) as $slug ) {
				$out[ $slug ] = true;
			}
		}

		return array_keys( $out );
	}

	/**
	 * Lets a product's statement replace the ingredient's, one axis at a time.
	 *
	 * The reference product overrides in both directions, as it does for
	 * nutrition and for the same reason: it is the concrete article, the
	 * ingredient only what is usually true. Per axis, because a product that
	 * says "vegan" says nothing about gluten — taking its silence for a denial
	 * would strip a correct claim off the ingredient.
	 *
	 * @param string[] $own     The ingredient's own diets.
	 * @param string[] $product The product's diets.
	 * @return string[]
	 */
	public static function apply_product( array $own, array $product ): array {

		if ( empty( $product ) ) {
			return $own;
		}

		$types = self::types();
		$axes  = array();

		foreach ( $product as $slug ) {
			if ( isset( $types[ $slug ] ) ) {
				$axes[ $types[ $slug ]['axis'] ] = true;
			}
		}

		$kept = array();

		foreach ( $own as $slug ) {
			if ( isset( $types[ $slug ] ) && ! isset( $axes[ $types[ $slug ]['axis'] ] ) ) {
				$kept[] = $slug;
			}
		}

		return array_values( array_unique( array_merge( $kept, $product ) ) );
	}

	/**
	 * The strongest diet of one axis in a set, or '' when the axis is silent.
	 *
	 * @param string[] $slugs Diets.
	 * @param string   $axis  Axis to read.
	 * @return string
	 */
	public static function strongest( array $slugs, string $axis = 'diet' ): string {

		$types = self::types();
		$best  = '';
		$rank  = -1;

		foreach ( $slugs as $slug ) {

			if ( ! isset( $types[ $slug ] ) || $types[ $slug ]['axis'] !== $axis ) {
				continue;
			}

			if ( (int) $types[ $slug ]['rank'] > $rank ) {
				$rank = (int) $types[ $slug ]['rank'];
				$best = $slug;
			}
		}

		return $best;
	}

	/**
	 * A set reduced to one entry per axis, strongest first — what a row of
	 * chips shows.
	 *
	 * @param string[] $slugs Diets.
	 * @return string[]
	 */
	public static function headline( array $slugs ): array {

		$types = self::types();
		$axes  = array();

		foreach ( $slugs as $slug ) {
			if ( isset( $types[ $slug ] ) ) {
				$axes[ $types[ $slug ]['axis'] ] = true;
			}
		}

		$out = array();

		foreach ( array_keys( $axes ) as $axis ) {
			$best = self::strongest( $slugs, $axis );
			if ( '' !== $best ) {
				$out[] = $best;
			}
		}

		return $out;
	}

	/**
	 * The chips a dish shows: one per axis, each saying whether the claim is
	 * settled or only reachable.
	 *
	 * The strongest *reachable* diet of an axis is what gets named — a dish
	 * that could be vegan is worth saying so about. Whether it reads "Vegan"
	 * or "Vegan-capable" then depends on one thing: is that same level already
	 * in force? It is once every ingredient's question is settled, either by
	 * the ingredient itself or by a product the recipe pins.
	 *
	 * So a dish that is vegetarian today and could be vegan shows exactly one
	 * chip, "Vegan-capable" — not both. The weaker certainty is the honest
	 * headline, and two chips on one axis read as two different diets.
	 *
	 * @param string[] $in_force Diets the dish holds as it stands.
	 * @param string[] $capable  Diets it can be cooked for.
	 * @return array<int,array{slug:string,label:string,definite:bool}>
	 */
	public static function chips( array $in_force, array $capable ): array {

		$settled = self::expand( $in_force );
		$chips   = array();

		foreach ( self::headline( $capable ) as $slug ) {

			$definite = in_array( $slug, $settled, true );

			$chips[] = array(
				'slug'     => $slug,
				'label'    => $definite ? self::label( $slug ) : self::suitability_label( $slug ),
				'definite' => $definite,
			);
		}

		return $chips;
	}

	/* ---------------------------------------------------------------------
	 * Storage
	 * ------------------------------------------------------------------ */

	/**
	 * The diets stated on one post.
	 *
	 * Terms first, and once a post has any term at all they are the whole
	 * answer — even if none of them is a diet the plugin knows. Somebody
	 * classified that post; the legacy meta beside it is then a leftover, and
	 * reading it would resurrect a value that was deliberately replaced.
	 *
	 * With no terms the old single-value meta still answers, so an install
	 * that has not been migrated keeps working exactly as before.
	 *
	 * @param int $post_id Ingredient or product ID.
	 * @return string[] Known diet slugs.
	 */
	public static function for_post( int $post_id ): array {

		if ( $post_id <= 0 ) {
			return array();
		}

		$slugs = wp_get_object_terms( $post_id, self::TAXONOMY, array( 'fields' => 'slugs' ) );

		if ( is_wp_error( $slugs ) ) {
			$slugs = array();
		}

		if ( ! empty( $slugs ) ) {
			return array_values( array_filter( $slugs, array( __CLASS__, 'is_known' ) ) );
		}

		$meta = get_post_meta(
			$post_id,
			'mmm_product' === get_post_type( $post_id ) ? self::LEGACY_META_PRODUCT : self::LEGACY_META_INGREDIENT,
			true
		);

		return self::is_known( (string) $meta ) ? array( (string) $meta ) : array();
	}

	/**
	 * Replaces the diets stated on one post.
	 *
	 * Unknown slugs are dropped rather than created: a term this plugin cannot
	 * reason about would sit on the ingredient and reach nothing, and writing
	 * one through the API would look like it had worked.
	 *
	 * @param int      $post_id Ingredient or product ID.
	 * @param string[] $slugs   Diet slugs.
	 * @return void
	 */
	public static function set_for_post( int $post_id, array $slugs ): void {

		if ( $post_id <= 0 ) {
			return;
		}

		$known = array_values( array_unique( array_filter( array_map( 'strval', $slugs ), array( __CLASS__, 'is_known' ) ) ) );

		wp_set_object_terms( $post_id, $known, self::TAXONOMY, false );
	}

	/* ---------------------------------------------------------------------
	 * Registration
	 * ------------------------------------------------------------------ */

	/**
	 * Registers the taxonomy and makes sure the known diets exist as terms.
	 *
	 * Called from Mixmymeals_Taxonomies with the other taxonomies.
	 *
	 * @return void
	 */
	public static function register_taxonomy(): void {

		$labels = array(
			'name'          => _x( 'Diets', 'taxonomy general name', 'mixmymeals' ),
			'singular_name' => _x( 'Diet', 'taxonomy singular name', 'mixmymeals' ),
			'all_items'     => __( 'All Diets', 'mixmymeals' ),
			'edit_item'     => __( 'Edit Diet', 'mixmymeals' ),
			'update_item'   => __( 'Update Diet', 'mixmymeals' ),
			'add_new_item'  => __( 'Add New Diet', 'mixmymeals' ),
			'new_item_name' => __( 'New Diet Name', 'mixmymeals' ),
			'search_items'  => __( 'Search Diets', 'mixmymeals' ),
			'menu_name'     => __( 'Diets', 'mixmymeals' ),
		);

		register_taxonomy(
			self::TAXONOMY,
			array( 'mmm_ingredient', 'mmm_product' ),
			array(
				'labels'            => $labels,
				'public'            => false,
				'show_ui'           => true,
				'show_admin_column' => true,
				'hierarchical'      => false,
				'show_in_rest'      => true,
				'rewrite'           => false,
				'query_var'         => false,
			)
		);
	}

	/**
	 * Creates the terms for the known diets if they are missing.
	 *
	 * Runs on admin_init rather than on every request: assigning a diet needs
	 * the term to exist, and an editor should not have to type "vegan" to get
	 * the one the plugin reasons about. Terms an add-on adds through the
	 * filter are created the same way.
	 *
	 * @return void
	 */
	public static function ensure_terms(): void {

		if ( ! taxonomy_exists( self::TAXONOMY ) ) {
			return;
		}

		foreach ( self::types() as $slug => $type ) {

			if ( term_exists( $slug, self::TAXONOMY ) ) {
				continue;
			}

			wp_insert_term( $type['label'], self::TAXONOMY, array( 'slug' => $slug ) );
		}
	}
}
