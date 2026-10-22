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
 * **The taxonomy is hierarchical, and the hierarchy is the rule.** A child
 * term implies every ancestor: "Vegan" under "Vegetarian" under
 * "Pescetarian" means a vegan dish suits a vegetarian and a pescetarian, and
 * nothing else needs saying. What a bare term cannot hold — which question it
 * answers (axis), how strong it is (rank), its schema.org URL — lives in term
 * meta and is inherited down the tree where it makes sense (axis). types()
 * assembles all of it, so a diet is added by adding a term, with no code.
 *
 * **No diet is defined in code.** Vegan, vegetarian, gluten-free and
 * lactose-free used to be built in, recognised by their slug and winning over
 * their terms. Changing such a slug on the term screen made the term a second
 * diet of the same name beside the built-in, and everything assigned to it
 * quietly stopped counting as vegan. migrate_former_builtins() moved their
 * definitions onto the terms once; since then a term is a diet like any
 * other, and its slug and name are the editor's.
 *
 * The four former keys survive as aliases (former_terms()), resolved through
 * the term ID rather than the slug: legacy meta, REST clients and shared links
 * still say "vegetarian", and they keep reaching the term whatever it is
 * called today.
 *
 * A term with no relations implies nothing and answers a question of its own
 * (its axis defaults to its own slug), so an invented term cannot produce or
 * displace a "vegan" label; it can only say what its place in the tree says.
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
	 * Term meta: which question the diet answers. Empty = inherit from parent.
	 *
	 * @var string
	 */
	const META_AXIS = '_mmm_diet_axis';

	/**
	 * Term meta: strength within the axis. Empty = by depth in the tree.
	 *
	 * @var string
	 */
	const META_RANK = '_mmm_diet_rank';

	/**
	 * Term meta: schema.org RestrictedDiet URL.
	 *
	 * @var string
	 */
	const META_SCHEMA = '_mmm_diet_schema';

	/**
	 * Option gating the one-off move of the former built-ins onto their terms.
	 *
	 * @var string
	 */
	const MIGRATION_OPTION = 'mixmymeals_diet_terms_version';

	/**
	 * Option mapping each former built-in key to the term ID that took it over.
	 *
	 * @var string
	 */
	const FORMER_OPTION = 'mixmymeals_diet_former_terms';

	/**
	 * The diet choice of a meal whose recipe holds no claim on the `diet`
	 * axis, cooked as written. Not a diet and never one: resolve() maps it to
	 * '', so nothing that reads diets can mistake it for a claim. Only
	 * Mixmymeals_Recipe::get_diet_choices() hands it out, and only while a
	 * term is marked to name it (META_UNRESTRICTED).
	 */
	const NONE = '_none';

	/**
	 * Term meta marking the one term that names a dish without a diet — "Mit
	 * Fleisch", "Omnivor", whatever the site calls it. The code names nothing
	 * itself: what a dish *without* a stated diet is, is the site's word, and
	 * any word chosen here would be a claim about recipes nobody classified.
	 *
	 * Such a term is a name, not a diet. terms_as_types() leaves it out, so it
	 * implies nothing, is implied by nothing, and never appears as a filter, a
	 * chip or a classification an ingredient could hold.
	 */
	const META_UNRESTRICTED = '_mmm_diet_unrestricted';

	/**
	 * What the four former built-ins were, for migrate_former_builtins() only.
	 *
	 * Nothing reads this at run time; a diet is what its term says. Each entry
	 * carries what a bare term cannot:
	 *  - axis    Which question it answers. One axis holds one claim at a
	 *            time; a dish is vegan or vegetarian, never both, while
	 *            gluten-free is a separate question with its own answer.
	 *  - implies What follows from it. Vegan food suits a vegetarian, so a
	 *            vegan ingredient counts as vegetarian when a recipe is
	 *            reduced; the reverse does not hold.
	 *  - rank    Which wins when one axis holds several. Higher is stronger
	 *            and is what a label shows.
	 *
	 * 'source' is the untranslated label, which is what a term created before
	 * the translation loaded is called. 'parent' names the diet this one sits
	 * under in the tree.
	 *
	 * @return array<string,array{label:string,source:string,axis:string,implies:string[],rank:int,schema:string,parent?:string}>
	 */
	private static function former_builtins(): array {

		$types = array(
			'vegan'        => array(
				'label'   => __( 'Vegan', 'mixmymeals' ),
				'source'  => 'Vegan',
				'axis'    => 'diet',
				'implies' => array( 'vegetarian' ),
				'parent'  => 'vegetarian',
				'rank'    => 20,
				'schema'  => 'https://schema.org/VeganDiet',
			),
			'vegetarian'   => array(
				'label'   => __( 'Vegetarian', 'mixmymeals' ),
				'source'  => 'Vegetarian',
				'axis'    => 'diet',
				'implies' => array(),
				'rank'    => 10,
				'schema'  => 'https://schema.org/VegetarianDiet',
			),
			'gluten_free'  => array(
				'label'   => __( 'Gluten-free', 'mixmymeals' ),
				'source'  => 'Gluten-free',
				'axis'    => 'free_from',
				'implies' => array(),
				'rank'    => 10,
				'schema'  => 'https://schema.org/GlutenFreeDiet',
			),
			'lactose_free' => array(
				'label'   => __( 'Lactose-free', 'mixmymeals' ),
				'source'  => 'Lactose-free',
				'axis'    => 'lactose',
				'implies' => array(),
				'rank'    => 10,
				'schema'  => 'https://schema.org/LowLactoseDiet',
			),
		);

		return $types;
	}

	/**
	 * The diets this plugin knows how to reason about.
	 *
	 * Every term of the taxonomy (see terms_as_types()), then the filter,
	 * then normalised: implications made transitive and ranks made
	 * to agree with them. Memoised for the request; the term and term-meta
	 * hooks reset it, reset_types() does so by hand.
	 *
	 * @return array<string,array{label:string,axis:string,implies:string[],rank:int,schema:string,parent?:string,suitability?:string}>
	 */
	public static function types(): array {

		$memo = self::types_memo();

		if ( null !== $memo ) {
			return $memo;
		}

		$types = array();

		// Before init the taxonomy does not exist; answer from the filter alone
		// and do not remember it, or the terms would stay invisible all request.
		$ready = taxonomy_exists( self::TAXONOMY );

		if ( $ready ) {
			$types = self::terms_as_types( $types );
		}

		/**
		 * Filters the diets the plugin knows how to reason about.
		 *
		 * Terms are the normal way in; this remains for definitions that should
		 * not depend on the database. An entry may name a 'parent' slug.
		 *
		 * @param array $types Slug => definition.
		 */
		$types = self::normalise( (array) apply_filters( 'mixmymeals_diet_types', $types ) );

		if ( $ready ) {
			self::types_memo( $types );
		}

		return $types;
	}

	/**
	 * Forgets the assembled definitions.
	 *
	 * @return void
	 */
	public static function reset_types(): void {
		self::types_memo( null, true );
		self::unrestricted_memo( true );
	}

	/**
	 * The term marked as the name of a dish without a diet, or null.
	 *
	 * @return WP_Term|null
	 */
	public static function unrestricted_term(): ?WP_Term {
		return self::unrestricted_memo();
	}

	/**
	 * Per-request holder of unrestricted_term().
	 *
	 * @param bool $clear Forget what is held.
	 * @return WP_Term|null
	 */
	private static function unrestricted_memo( bool $clear = false ): ?WP_Term {

		static $memo = false;

		if ( $clear ) {
			$memo = false;
			return null;
		}

		if ( false !== $memo ) {
			return $memo;
		}

		if ( ! taxonomy_exists( self::TAXONOMY ) ) {
			return null;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
				'number'     => 1,
				'orderby'    => 'term_id',
				'meta_key'   => self::META_UNRESTRICTED, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- a handful of terms.
				'meta_value' => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		$memo = ( ! is_wp_error( $terms ) && $terms ) ? $terms[0] : null;

		return $memo;
	}

	/**
	 * Per-request holder of types().
	 *
	 * @param array|null $value Value to store.
	 * @param bool       $clear Drop what is held.
	 * @return array|null
	 */
	private static function types_memo( ?array $value = null, bool $clear = false ): ?array {

		static $memo = null;

		if ( $clear ) {
			$memo = null;
		} elseif ( null !== $value ) {
			$memo = $value;
		}

		return $memo;
	}

	/**
	 * Turns the terms into definitions.
	 *
	 * Label from the name, axis from its meta or the nearest ancestor that has
	 * one (else a question of its own), rank from its meta or its depth, and
	 * 'implies' from its ancestors.
	 *
	 * @param array $types Definitions so far.
	 * @return array
	 */
	private static function terms_as_types( array $types ): array {

		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return $types;
		}

		// The term naming a dish without a diet is a name, not a diet.
		$terms = array_filter(
			$terms,
			static fn( $term ) => '1' !== (string) get_term_meta( $term->term_id, self::META_UNRESTRICTED, true )
		);

		$by_id = array();

		foreach ( $terms as $term ) {
			$by_id[ (int) $term->term_id ] = $term;
		}

		// Slugs from the root down to the term's parent, nearest last.
		$chain = static function ( WP_Term $term ) use ( $by_id ): array {

			$out  = array();
			$seen = array( (int) $term->term_id => true );
			$cur  = $term;

			while ( $cur->parent && isset( $by_id[ (int) $cur->parent ] ) && ! isset( $seen[ (int) $cur->parent ] ) ) {
				$cur                        = $by_id[ (int) $cur->parent ];
				$seen[ (int) $cur->term_id ] = true;
				array_unshift( $out, $cur->slug );
			}

			return $out;
		};

		$ancestry = array();

		foreach ( $by_id as $id => $term ) {
			$ancestry[ $term->slug ] = $chain( $term );
		}

		// Axes stated on the term itself, before anything is inherited.
		$own_axis = array();

		foreach ( $by_id as $term ) {
			$own_axis[ $term->slug ] = (string) get_term_meta( $term->term_id, self::META_AXIS, true );
		}

		foreach ( $by_id as $term ) {

			$slug      = $term->slug;
			$ancestors = $ancestry[ $slug ];
			$axis      = $own_axis[ $slug ];

			if ( '' === $axis ) {
				// Nearest ancestor first: its term meta, or the axis a built-in of that slug has.
				foreach ( array_reverse( $ancestors ) as $up ) {
					$found = (string) ( $own_axis[ $up ] ?? '' );
					if ( '' !== $found ) {
						$axis = $found;
						break;
					}
				}
			}

			$rank_meta = get_term_meta( $term->term_id, self::META_RANK, true );

			/*
			 * A term nobody gave an axis, with a diet of a stated axis somewhere
			 * beneath it, answers that diet's question: "Pescetarian" above
			 * "Vegetarian" is plainly the same axis, and making the editor type
			 * "diet" to say so would be a field that exists to be forgotten.
			 * (This used to look for a built-in beneath; the built-ins now carry
			 * their axis as term meta, so a stated axis is the same test.)
			 */
			if ( '' === $axis ) {
				foreach ( $ancestry as $below => $above ) {
					if ( '' !== $own_axis[ $below ] && in_array( $slug, $above, true ) ) {
						$axis = $own_axis[ $below ];
						break;
					}
				}
			}

			$types[ $slug ] = array(
				'label'   => $term->name,
				'axis'    => '' !== $axis ? sanitize_key( $axis ) : $slug,
				'implies' => array_reverse( $ancestors ),
				'parent'  => $ancestors ? end( $ancestors ) : '',
				'rank'    => '' !== (string) $rank_meta ? (int) $rank_meta : ( count( $ancestors ) + 1 ) * 10,
				'schema'  => esc_url_raw( (string) get_term_meta( $term->term_id, self::META_SCHEMA, true ) ),
			);
		}

		return $types;
	}

	/**
	 * Makes a set of definitions consistent.
	 *
	 * Implications are closed transitively and stripped of unknown slugs and of
	 * the diet itself, because expand() looks only one level deep. Rank is then
	 * lifted so that a diet always outranks what it implies on its own axis —
	 * whatever numbers an editor typed, "vegan" must never lose to the
	 * "vegetarian" it contains, or strongest() would name the weaker claim.
	 *
	 * @param array $types Definitions.
	 * @return array
	 */
	private static function normalise( array $types ): array {

		foreach ( $types as $slug => &$type ) {

			$type = array_merge(
				array(
					'label'   => (string) $slug,
					'axis'    => (string) $slug,
					'implies' => array(),
					'rank'    => 10,
					'schema'  => '',
				),
				(array) $type
			);

			if ( ! empty( $type['parent'] ) ) {
				$type['implies'][] = (string) $type['parent'];
			}
		}
		unset( $type );

		$closed = array();

		foreach ( array_keys( $types ) as $slug ) {

			$seen  = array();
			$queue = (array) $types[ $slug ]['implies'];

			while ( $queue ) {

				$next = (string) array_shift( $queue );

				if ( $next === $slug || isset( $seen[ $next ] ) || ! isset( $types[ $next ] ) ) {
					continue;
				}

				$seen[ $next ] = true;

				foreach ( (array) $types[ $next ]['implies'] as $more ) {
					$queue[] = $more;
				}
			}

			$closed[ $slug ] = array_keys( $seen );
		}

		foreach ( $closed as $slug => $implies ) {
			$types[ $slug ]['implies'] = $implies;
		}

		// Fewest implications first: what a diet implies is settled before it.
		$order = array_keys( $types );
		usort(
			$order,
			static function ( $a, $b ) use ( $closed ) {
				return count( $closed[ $a ] ) <=> count( $closed[ $b ] );
			}
		);

		foreach ( $order as $slug ) {

			$rank = (int) $types[ $slug ]['rank'];

			foreach ( $closed[ $slug ] as $implied ) {
				if ( $types[ $implied ]['axis'] === $types[ $slug ]['axis'] ) {
					$rank = max( $rank, (int) $types[ $implied ]['rank'] + 1 );
				}
			}

			$types[ $slug ]['rank'] = $rank;
		}

		return $types;
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
	 * The diet a slug stands for: itself when it is a diet, the term that took
	 * over a former built-in key ("vegetarian") otherwise, else ''.
	 *
	 * Every place a slug arrives from outside — legacy meta, the REST API, an
	 * address — goes through this, so a key from before the migration reaches
	 * the same term the editor sees, whatever its slug is today.
	 *
	 * @param string $slug Slug or former key.
	 * @return string
	 */
	public static function resolve( string $slug ): string {

		if ( self::is_known( $slug ) ) {
			return $slug;
		}

		$former = self::former_terms()[ $slug ] ?? '';

		return '' !== $former && self::is_known( $former ) ? $former : '';
	}

	/**
	 * The diets of one axis, plus the former keys that reach one of them.
	 *
	 * What the REST API accepts for its single-axis `diet_type` and `diet`
	 * parameters: the slugs as they are today, and "vegan" / "vegetarian" for
	 * clients written before the migration.
	 *
	 * @param string $axis Axis.
	 * @return string[]
	 */
	public static function axis_slugs( string $axis ): array {

		$out = array();

		foreach ( self::types() as $slug => $type ) {
			if ( $type['axis'] === $axis ) {
				$out[] = (string) $slug;
			}
		}

		foreach ( self::former_terms() as $key => $slug ) {
			if ( in_array( $slug, $out, true ) && ! in_array( $key, $out, true ) ) {
				$out[] = (string) $key;
			}
		}

		return $out;
	}

	/**
	 * Former built-in key => the current slug of the term that took it over.
	 *
	 * Stored as term IDs, so renaming a slug afterwards cannot break it.
	 * Entries whose term is gone are left out.
	 *
	 * @return array<string,string>
	 */
	public static function former_terms(): array {

		$out = array();

		foreach ( (array) get_option( self::FORMER_OPTION, array() ) as $key => $term_id ) {

			$term = get_term( (int) $term_id, self::TAXONOMY );

			if ( $term instanceof WP_Term ) {
				$out[ (string) $key ] = $term->slug;
			}
		}

		return $out;
	}

	/**
	 * Which former built-in key a diet took over, or ''.
	 *
	 * For wording that is still keyed by those four — the theme's archive
	 * headings — and nothing else: what a diet *is* comes from its term.
	 *
	 * @param string $slug Diet slug.
	 * @return string
	 */
	public static function former_key( string $slug ): string {

		$key = array_search( $slug, self::former_terms(), true );

		return false === $key ? '' : (string) $key;
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
	 * The label of a meal's diet choice: the diet's own, or for NONE the name
	 * of the term marked with META_UNRESTRICTED.
	 *
	 * @param string $slug A slug from Mixmymeals_Recipe::get_diet_choices().
	 * @return string
	 */
	public static function choice_label( string $slug ): string {

		if ( self::NONE !== $slug ) {
			return self::label( $slug );
		}

		$term = self::unrestricted_term();

		return $term ? $term->name : '';
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

		return Mixmymeals_Shortcodes::chip_text( 'diet_capable', array( 'diet' => $label ) );
	}

	/**
	 * The chip wording of a diet that is in force.
	 *
	 * Through the same registry as the capable form, although the default is
	 * the bare name: a site that words one of the two differently will want to
	 * word the other to match, and having only half of the pair configurable is
	 * how the two drift apart.
	 *
	 * @param string $slug Term slug.
	 * @return string
	 */
	public static function chip_label( string $slug ): string {

		$label = self::label( $slug );

		return '' === $label
			? ''
			: Mixmymeals_Shortcodes::chip_text( 'diet', array( 'diet' => $label ) );
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

	/*
	---------------------------------------------------------------------
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
				'label'    => $definite ? self::chip_label( $slug ) : self::suitability_label( $slug ),
				'definite' => $definite,
			);
		}

		return $chips;
	}

	/*
	---------------------------------------------------------------------
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

		$memo = self::memo();

		if ( isset( $memo[ $post_id ] ) ) {
			return $memo[ $post_id ];
		}

		/*
		 * get_the_terms(), not wp_get_object_terms(): only the former reads the
		 * object term cache, which is what prime() fills. The latter queries
		 * every time and made priming pointless.
		 */
		$terms = get_the_terms( $post_id, self::TAXONOMY );
		$slugs = ( $terms && ! is_wp_error( $terms ) ) ? wp_list_pluck( $terms, 'slug' ) : array();

		if ( ! empty( $slugs ) ) {
			$result = array_values( array_filter( $slugs, array( __CLASS__, 'is_known' ) ) );
		} else {

			$meta = get_post_meta(
				$post_id,
				'mmm_product' === get_post_type( $post_id ) ? self::LEGACY_META_PRODUCT : self::LEGACY_META_INGREDIENT,
				true
			);

			$meta   = self::resolve( (string) $meta );
			$result = '' !== $meta ? array( $meta ) : array();
		}

		self::memo( $post_id, $result );

		return $result;
	}

	/**
	 * Per-request answers of for_post().
	 *
	 * A recipe asks every ingredient twice — once for what is in force, once
	 * for what is reachable — and a plan asks every recipe. Without this the
	 * same ingredient was read from the database a dozen times while one
	 * archive page rendered.
	 *
	 * @param int|null   $post_id Post to read or write, null to read the map.
	 * @param array|null $value   Value to store.
	 * @return array
	 */
	private static function memo( ?int $post_id = null, ?array $value = null ): array {

		static $memo = array();

		if ( null === $post_id ) {
			return $memo;
		}

		if ( null !== $value ) {
			$memo[ $post_id ] = $value;
		}

		return $memo;
	}

	/**
	 * Loads the diet terms of many posts in one query.
	 *
	 * wp_get_object_terms() asks per post; update_object_term_cache() asks once
	 * for a list and fills the cache the per-post calls then hit. Callers that
	 * know their whole set up front — a recipe with its ingredients and their
	 * products — should prime it before the loop.
	 *
	 * @param int[] $post_ids Ingredient and product IDs.
	 * @return void
	 */
	public static function prime( array $post_ids ): void {

		$memo    = self::memo();
		$missing = array();

		foreach ( $post_ids as $id ) {

			$id = (int) $id;

			if ( $id > 0 && ! isset( $memo[ $id ] ) ) {
				$missing[ $id ] = true;
			}
		}

		if ( empty( $missing ) ) {
			return;
		}

		/*
		 * Both post types in one call: the cache is keyed by object id and
		 * taxonomy, and asking per type would be two queries for no gain.
		 */
		update_object_term_cache( array_keys( $missing ), 'mmm_ingredient' );
	}

	/**
	 * Replaces the diets stated on one post.
	 *
	 * Unknown slugs are dropped rather than created: a term this plugin cannot
	 * reason about would sit on the ingredient and reach nothing, and writing
	 * one through the API would look like it had worked.
	 *
	 * @param int      $post_id      Ingredient or product ID.
	 * @param string[] $slugs        Diet slugs.
	 * @param bool     $keep_legacy  Leave the pre-taxonomy meta row in place.
	 *                               Only the migration does, because that row
	 *                               is its way back.
	 * @return void
	 */
	public static function set_for_post( int $post_id, array $slugs, bool $keep_legacy = false ): void {

		if ( $post_id <= 0 ) {
			return;
		}

		$known = array_values( array_unique( array_filter( array_map( array( __CLASS__, 'resolve' ), array_map( 'strval', $slugs ) ) ) ) );

		wp_set_object_terms( $post_id, $known, self::TAXONOMY, false );

		/*
		 * Die alte Meta-Zeile verschwindet mit dem Schreiben — und das ist der
		 * Kern einer Sache, die lange falsch war: for_post() fällt auf das Meta
		 * zurück, sobald keine Terme da sind. Eine Einstufung zu löschen war
		 * deshalb unmöglich. Man nahm die Terme weg, und beim nächsten Lesen
		 * stand wieder „vegan“ da, aus einer Zeile, die niemand mehr sah. Auf
		 * stopfnicht.de trugen vier Zutaten dadurch eine Vegan-Pille, deren
		 * Referenzprodukt Fleisch ist.
		 *
		 * Auch bei einer nicht leeren Liste: wer die Terme setzt, hat die
		 * Aussage gemacht; die Meta-Zeile daneben kann ihr danach nur noch
		 * widersprechen.
		 *
		 * $keep_legacy ist der eine Ausstieg, und er gehört der Migration.
		 * Sie kopiert Meta → Terme und lässt die Zeile absichtlich stehen, weil
		 * sie der einzige Weg zurück ist. Würde sie hier mitlöschen, zerstörte
		 * der Umzug genau das, was ihn umkehrbar macht.
		 */
		if ( ! $keep_legacy ) {
			delete_post_meta(
				$post_id,
				'mmm_product' === get_post_type( $post_id ) ? self::LEGACY_META_PRODUCT : self::LEGACY_META_INGREDIENT
			);
		}

		// The per-request memo would otherwise keep answering with what this
		// post said before the write.
		self::memo( $post_id, $known );
	}

	/*
	---------------------------------------------------------------------
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
				'hierarchical'      => true,
				'show_in_rest'      => true,
				'rewrite'           => false,
				'query_var'         => false,
			)
		);
	}

	/**
	 * Moves the four former built-in diets onto their terms. Once.
	 *
	 * Until 3.50.6 vegan, vegetarian, gluten-free and lactose-free were defined
	 * in code and recognised by their slug. This writes what the code said —
	 * group, strength, schema.org URL, Vegan below Vegetarian — onto the term
	 * that carries each of them, so every recipe, meal and plan reads exactly
	 * what it read before, and records which term took over which key.
	 *
	 * The term is found by slug, or by name where the slug was changed. A slug
	 * change used to make ensure_terms() create a second, empty term under the
	 * old slug; where the one found by name carries the assignments and the
	 * one under the old slug carries none, the empty one is that duplicate and
	 * is deleted. Nothing with an assignment is ever deleted.
	 *
	 * Meta already present on a term is kept: an editor who stated a group or
	 * a strength has said something the code never did.
	 *
	 * Nothing is created: an install without these terms simply has no such
	 * diets, which is the point.
	 *
	 * @return void
	 */
	public static function migrate_former_builtins(): void {

		if ( (int) get_option( self::MIGRATION_OPTION, 0 ) >= 1 || ! taxonomy_exists( self::TAXONOMY ) ) {
			return;
		}

		// The labels go onto terms, which every visitor sees in the site's
		// language — not in the language of whoever triggers this request.
		$switched = switch_to_locale( get_locale() );
		$former   = self::former_builtins();

		if ( $switched ) {
			restore_previous_locale();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
			)
		);

		$terms = is_wp_error( $terms ) ? array() : $terms;

		$used = static function ( WP_Term $term ): bool {
			$ids = get_objects_in_term( (int) $term->term_id, self::TAXONOMY );
			return ! is_wp_error( $ids ) && ! empty( $ids );
		};

		$map = array();

		foreach ( $former as $key => $def ) {

			$by_slug = null;
			$by_name = null;
			$names   = array( mb_strtolower( $def['label'] ), mb_strtolower( $def['source'] ) );

			foreach ( $terms as $term ) {
				if ( $term->slug === $key ) {
					$by_slug = $term;
				} elseif ( null === $by_name && in_array( mb_strtolower( $term->name ), $names, true ) ) {
					$by_name = $term;
				}
			}

			$term = $by_slug ?? $by_name;

			if ( $by_slug && $by_name && ! $used( $by_slug ) && $used( $by_name ) ) {
				wp_delete_term( (int) $by_slug->term_id, self::TAXONOMY );
				$term = $by_name;
			}

			if ( ! $term ) {
				continue;
			}

			$id          = (int) $term->term_id;
			$map[ $key ] = $id;

			foreach (
				array(
					self::META_AXIS   => (string) $def['axis'],
					self::META_RANK   => (string) $def['rank'],
					self::META_SCHEMA => (string) $def['schema'],
				) as $meta_key => $value
			) {
				if ( '' === (string) get_term_meta( $id, $meta_key, true ) ) {
					update_term_meta( $id, $meta_key, $value );
				}
			}

			// A term created before the translation loaded carries the English
			// source; the visitor used to see the translation in its place.
			if ( $term->name === $def['source'] && $def['label'] !== $def['source'] ) {
				wp_update_term( $id, self::TAXONOMY, array( 'name' => $def['label'] ) );
			}
		}

		// "Vegan implies vegetarian" was code; on terms it is the tree. Only a
		// vegan term still at the root is moved — any other place is a choice.
		if ( isset( $map['vegan'], $map['vegetarian'] ) ) {
			$vegan = get_term( $map['vegan'], self::TAXONOMY );
			if ( $vegan instanceof WP_Term && 0 === (int) $vegan->parent ) {
				wp_update_term( $map['vegan'], self::TAXONOMY, array( 'parent' => $map['vegetarian'] ) );
			}
		}

		update_option( self::FORMER_OPTION, $map );
		update_option( self::MIGRATION_OPTION, 1 );
		delete_option( 'mixmymeals_diet_hierarchy_version' );

		self::reset_types();
		Mixmymeals_Recipe::mark_all_indexes_dirty();
	}

	/*
	---------------------------------------------------------------------
	 * Term screen
	 * ------------------------------------------------------------------ */

	/**
	 * Hooks the term form and the cache resets. Once per request.
	 *
	 * @return void
	 */
	public static function register_term_admin(): void {

		static $done = false;

		if ( $done ) {
			return;
		}

		$done = true;

		add_action( self::TAXONOMY . '_add_form_fields', array( __CLASS__, 'render_add_fields' ) );
		add_action( self::TAXONOMY . '_edit_form_fields', array( __CLASS__, 'render_edit_fields' ) );
		add_filter( 'pre_insert_term', array( __CLASS__, 'validate_new_term' ), 10, 2 );
		add_action( 'edit_terms', array( __CLASS__, 'validate_edited_term' ), 10, 2 );
		add_action( 'created_' . self::TAXONOMY, array( __CLASS__, 'save_term_fields' ) );
		add_action( 'edited_' . self::TAXONOMY, array( __CLASS__, 'save_term_fields' ) );

		// A rename, a move in the tree or a meta write changes what every
		// ingredient implies; the assembled definitions are stale until then.
		foreach ( array( 'created_term', 'edited_term', 'delete_term' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'on_term_changed' ), 5, 3 );
		}
		foreach ( array( 'added_term_meta', 'updated_term_meta', 'deleted_term_meta' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'on_term_meta_changed' ), 10, 3 );
		}
	}

	/**
	 * A term of this taxonomy was created, edited or deleted.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy.
	 * @return void
	 */
	public static function on_term_changed( $term_id, $tt_id = 0, $taxonomy = '' ): void {

		if ( self::TAXONOMY === $taxonomy ) {
			self::reset_types();
		}
	}

	/**
	 * A term meta row was written; reset when it is one of ours.
	 *
	 * @param int    $meta_id  Meta ID.
	 * @param int    $term_id  Term ID.
	 * @param string $meta_key Meta key.
	 * @return void
	 */
	public static function on_term_meta_changed( $meta_id, $term_id, $meta_key ): void {

		if ( in_array( $meta_key, array( self::META_AXIS, self::META_RANK, self::META_SCHEMA, self::META_UNRESTRICTED ), true ) ) {
			self::reset_types();
			Mixmymeals_Recipe::mark_all_indexes_dirty();
		}
	}

	/**
	 * Fields on "Add New Diet".
	 *
	 * @return void
	 */
	public static function render_add_fields(): void {

		wp_nonce_field( 'mmm_diet_term', 'mmm_diet_term_nonce' );

		echo '<div class="form-field"><label for="mmm_diet_axis">' . esc_html__( 'Group', 'mixmymeals' ) . '</label>';
		echo '<input type="text" name="mmm_diet_axis" id="mmm_diet_axis" list="mmm_diet_axes" value="">';
		self::render_axes_datalist();
		echo '<p>' . esc_html( self::axis_help() ) . '</p></div>';

		echo '<div class="form-field"><label for="mmm_diet_rank">' . esc_html__( 'Strength', 'mixmymeals' ) . '</label>';
		echo '<input type="number" name="mmm_diet_rank" id="mmm_diet_rank" value="" min="0" step="1">';
		echo '<p>' . esc_html( self::rank_help() ) . '</p></div>';

		echo '<div class="form-field"><label for="mmm_diet_schema">' . esc_html__( 'schema.org diet URL', 'mixmymeals' ) . '</label>';
		echo '<input type="url" name="mmm_diet_schema" id="mmm_diet_schema" value="" placeholder="https://schema.org/…">';
		echo '<p>' . esc_html( self::schema_help() ) . '</p></div>';

		echo '<div class="form-field"><label><input type="checkbox" name="mmm_diet_unrestricted" value="1"> '
			. esc_html__( 'Name for dishes without a diet', 'mixmymeals' ) . '</label>';
		echo '<p>' . esc_html( self::unrestricted_help() ) . '</p></div>';

		echo '<p class="description">' . esc_html__( 'The parent says what this diet implies: a dish that suits a diet automatically suits all of its parents. Example: Vegan below Vegetarian.', 'mixmymeals' ) . '</p>';
	}

	/**
	 * What the "name for dishes without a diet" box does, for both forms.
	 *
	 * @return string
	 */
	private static function unrestricted_help(): string {
		return __( 'Marks this term as the name a meal plan uses for a recipe cooked as written when that recipe states no diet, e.g. "With meat". Only then can a meal choose between that and the diets the recipe can be cooked as (vegetarian, vegan). Such a term is not a diet itself: it is not offered to ingredients, filters or badges. Only one term can carry it.', 'mixmymeals' );
	}

	/**
	 * What the "Question it answers" field means, for both term forms.
	 *
	 * One sentence in one place: the add and the edit form used to risk
	 * wording the same field twice, and a translation then has to be kept in
	 * step with itself.
	 *
	 * @return string
	 */
	private static function axis_help(): string {
		return __( 'Diets that answer the same question form a group, and a dish holds only one answer per group: vegan and vegetarian share one (animal ingredients), gluten-free and lactose-free each have their own. Leave empty to join the parent\'s group, or to start a group of its own when there is no parent. Fill it in only to put this diet in a different group than its parent — for example "honey-free" beside "vegan".', 'mixmymeals' );
	}

	/**
	 * What the strength field means, for both term forms.
	 *
	 * @return string
	 */
	private static function rank_help(): string {
		return __( 'Decides which diet a badge shows when a dish holds several of the same group: the higher number wins. Leave empty to go by depth in the tree — a child is always stronger than its parent.', 'mixmymeals' );
	}

	/**
	 * What the schema.org field is for, for both term forms.
	 *
	 * @return string
	 */
	private static function schema_help(): string {
		return __( 'Optional. Search engines read it from the recipe\'s structured data (suitableForDiet).', 'mixmymeals' );
	}

	/**
	 * Fields on "Edit Diet".
	 *
	 * @param WP_Term $term Term being edited.
	 * @return void
	 */
	public static function render_edit_fields( $term ): void {

		wp_nonce_field( 'mmm_diet_term', 'mmm_diet_term_nonce' );

		$axis   = (string) get_term_meta( $term->term_id, self::META_AXIS, true );
		$rank   = (string) get_term_meta( $term->term_id, self::META_RANK, true );
		$schema = (string) get_term_meta( $term->term_id, self::META_SCHEMA, true );
		$now    = self::types()[ $term->slug ] ?? array();

		echo '<tr class="form-field"><th scope="row"><label for="mmm_diet_axis">' . esc_html__( 'Group', 'mixmymeals' ) . '</label></th><td>';
		echo '<input type="text" name="mmm_diet_axis" id="mmm_diet_axis" list="mmm_diet_axes" value="' . esc_attr( $axis ) . '">';
		self::render_axes_datalist();
		/* translators: %s: the question the diet currently answers. */
		echo '<p class="description">' . esc_html( sprintf( __( 'Currently: %s.', 'mixmymeals' ), (string) ( $now['axis'] ?? '' ) ) . ' ' . self::axis_help() ) . '</p></td></tr>';

		echo '<tr class="form-field"><th scope="row"><label for="mmm_diet_rank">' . esc_html__( 'Strength', 'mixmymeals' ) . '</label></th><td>';
		echo '<input type="number" name="mmm_diet_rank" id="mmm_diet_rank" value="' . esc_attr( $rank ) . '" min="0" step="1">';
		/* translators: %d: the strength currently in force. */
		echo '<p class="description">' . esc_html( sprintf( __( 'Currently: %d.', 'mixmymeals' ), (int) ( $now['rank'] ?? 0 ) ) . ' ' . self::rank_help() ) . '</p></td></tr>';

		echo '<tr class="form-field"><th scope="row"><label for="mmm_diet_schema">' . esc_html__( 'schema.org diet URL', 'mixmymeals' ) . '</label></th><td>';
		echo '<input type="url" name="mmm_diet_schema" id="mmm_diet_schema" value="' . esc_attr( $schema ) . '" placeholder="https://schema.org/…">';
		echo '<p class="description">' . esc_html( self::schema_help() ) . '</p></td></tr>';

		echo '<tr class="form-field"><th scope="row">' . esc_html__( 'Name for dishes without a diet', 'mixmymeals' ) . '</th><td>';
		echo '<label><input type="checkbox" name="mmm_diet_unrestricted" value="1"' . checked( '1', (string) get_term_meta( $term->term_id, self::META_UNRESTRICTED, true ), false ) . '> '
			. esc_html__( 'Name for dishes without a diet', 'mixmymeals' ) . '</label>';
		echo '<p class="description">' . esc_html( self::unrestricted_help() ) . '</p></td></tr>';
	}

	/**
	 * The axes in use, as suggestions for the axis field.
	 *
	 * @return void
	 */
	private static function render_axes_datalist(): void {

		echo '<datalist id="mmm_diet_axes">';

		foreach ( array_unique( wp_list_pluck( self::types(), 'axis' ) ) as $axis ) {
			echo '<option value="' . esc_attr( (string) $axis ) . '">';
		}

		echo '</datalist>';
	}

	/**
	 * Reads the three fields off the request and says what is wrong with them.
	 *
	 * Returns null when the request carries none of our form (REST, import, the
	 * the migration) — those paths set meta directly or not at all.
	 * Before this, a bad value was quietly dropped by the sanitisers and the
	 * term appeared to have been saved with the fields the editor typed; an
	 * error that names the field is the only honest answer.
	 *
	 * @return array{axis:string,rank:string,schema:string,unrestricted:string}|WP_Error|null
	 */
	private static function read_term_fields() {

		if ( ! isset( $_POST['mmm_diet_term_nonce'] ) ) {
			return null;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mmm_diet_term_nonce'] ) ), 'mmm_diet_term' ) ) {
			return new WP_Error( 'mmm_diet_nonce', __( 'The form has expired. Reload the page and try again.', 'mixmymeals' ) );
		}

		if ( ! current_user_can( 'manage_categories' ) ) {
			return new WP_Error( 'mmm_diet_cap', __( 'You are not allowed to change diets.', 'mixmymeals' ) );
		}

		$axis   = isset( $_POST['mmm_diet_axis'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['mmm_diet_axis'] ) ) ) : '';
		$rank   = isset( $_POST['mmm_diet_rank'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['mmm_diet_rank'] ) ) ) : '';
		$schema = isset( $_POST['mmm_diet_schema'] ) ? trim( esc_url_raw( wp_unslash( $_POST['mmm_diet_schema'] ) ) ) : '';
		$raw    = isset( $_POST['mmm_diet_schema'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['mmm_diet_schema'] ) ) ) : '';

		if ( '' !== $axis && ! preg_match( '/^[a-z0-9_-]+$/', $axis ) ) {
			return new WP_Error(
				'mmm_diet_axis',
				__( 'Group: use only lowercase letters, digits, hyphens and underscores, e.g. "diet" or "free_from". Leave it empty to take the parent\'s group.', 'mixmymeals' )
			);
		}

		if ( '' !== $rank && ! preg_match( '/^\d+$/', $rank ) ) {
			return new WP_Error( 'mmm_diet_rank', __( 'Strength: enter a whole number of 0 or more, or leave it empty.', 'mixmymeals' ) );
		}

		// esc_url_raw() empties what it rejects, so compare with what was typed.
		if ( '' !== $raw && '' === $schema ) {
			return new WP_Error( 'mmm_diet_schema', __( 'schema.org diet URL: this is not a valid web address (https://schema.org/…).', 'mixmymeals' ) );
		}

		return array(
			'axis'         => $axis,
			'rank'         => $rank,
			'schema'       => $schema,
			'unrestricted' => empty( $_POST['mmm_diet_unrestricted'] ) ? '' : '1',
		);
	}

	/**
	 * Refuses a new term whose fields are invalid, so the form shows why.
	 *
	 * @param string|WP_Error $term     Term name.
	 * @param string          $taxonomy Taxonomy.
	 * @return string|WP_Error
	 */
	public static function validate_new_term( $term, $taxonomy ) {

		if ( self::TAXONOMY !== $taxonomy || is_wp_error( $term ) ) {
			return $term;
		}

		$fields = self::read_term_fields();

		return is_wp_error( $fields ) ? $fields : $term;
	}

	/**
	 * Stops an edit whose fields are invalid, before the term is changed.
	 *
	 * wp_update_term() has no filter that can return an error, but this action
	 * runs before anything is written, so ending the request here leaves the
	 * term as it was.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy.
	 * @return void
	 */
	public static function validate_edited_term( $term_id, $taxonomy ): void {

		if ( self::TAXONOMY !== $taxonomy ) {
			return;
		}

		$fields = self::read_term_fields();

		if ( is_wp_error( $fields ) ) {
			wp_die(
				esc_html( $fields->get_error_message() ),
				esc_html__( 'The diet was not saved', 'mixmymeals' ),
				array(
					'response'  => 400,
					'back_link' => true,
				)
			);
		}
	}

	/**
	 * Stores the three fields.
	 *
	 * @param int $term_id Term ID.
	 * @return void
	 */
	public static function save_term_fields( $term_id ): void {

		// Validated before the term was written; a failure never gets here.
		$fields = self::read_term_fields();

		if ( ! is_array( $fields ) ) {
			return;
		}

		self::write_term_meta( (int) $term_id, self::META_AXIS, $fields['axis'] );
		self::write_term_meta( (int) $term_id, self::META_RANK, $fields['rank'] );
		self::write_term_meta( (int) $term_id, self::META_SCHEMA, $fields['schema'] );

		// One term names a dish without a diet; marking a second moves it.
		if ( '1' === $fields['unrestricted'] ) {
			foreach ( get_terms( array( 'taxonomy' => self::TAXONOMY, 'hide_empty' => false, 'fields' => 'ids' ) ) as $other ) {
				if ( (int) $other !== (int) $term_id ) {
					delete_term_meta( (int) $other, self::META_UNRESTRICTED );
				}
			}
		}

		self::write_term_meta( (int) $term_id, self::META_UNRESTRICTED, $fields['unrestricted'] );
	}

	/**
	 * Writes a term meta value, or removes the row for an empty one.
	 *
	 * An empty value is "inherit", not a value: storing it would make the
	 * difference between a field never filled in and one cleared invisible.
	 *
	 * @param int    $term_id Term ID.
	 * @param string $key     Meta key.
	 * @param string $value   Value.
	 * @return void
	 */
	private static function write_term_meta( int $term_id, string $key, string $value ): void {

		if ( '' === $value ) {
			delete_term_meta( $term_id, $key );
		} else {
			update_term_meta( $term_id, $key, $value );
		}
	}
}
