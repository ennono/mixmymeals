<?php
/**
 * Recipe domain object.
 *
 * @package MixMyMeals/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Represents a single recipe post and provides methods for reading and writing its data.
 */
class Mixmymeals_Recipe {

	/** Post meta holding how often the recipe was edited (absent until the first edit). */
	const META_EDIT_COUNT = '_mmm_edit_count';

	/** Post meta holding the cook count (absent when zero). */
	const META_COOK_COUNT = '_mmm_cook_count';

	/** Post meta flagging a recipe as a favorite (absent unless flagged). */
	const META_IS_FAVORITE = '_mmm_is_favorite';

	/** The URL parameter that narrows the recipe archive to one browse view. */
	const BROWSE_VIEW_VAR = 'mmm_view';

	/**
	 * Remembers which browse-view rewrite rules were last written. A changed
	 * archive slug or a changed translation has to reach the rewrite table, and
	 * nobody re-saves the permalink settings after a deploy.
	 */
	const BROWSE_RULES_OPTION = 'mixmymeals_browse_view_rules';

	/**
	 * The browse views: the four rows on the recipe archive, each of which is
	 * also a view of the archive itself.
	 *
	 * Slugs only, no labels. The wording belongs to whoever renders the row —
	 * the child theme passes it into the shortcode as `title` today — and a
	 * second set of strings here would be a second thing to translate and a
	 * second thing to disagree with the first.
	 *
	 * These are the internal keys, which double as the CSS modifier the rows
	 * already carried, so there is one vocabulary rather than two. What appears
	 * in the URL is browse_view_slug(), which may be translated; the keys here
	 * never change and stay valid in a URL forever.
	 *
	 * @return string[]
	 */
	public static function browse_views(): array {
		return array( 'new', 'fav', 'popular', 'uncooked' );
	}

	/**
	 * Whether a string names a browse view.
	 *
	 * @param string $view View slug.
	 * @return bool
	 */
	public static function is_browse_view( string $view ): bool {
		return in_array( $view, self::browse_views(), true );
	}

	/**
	 * The query arguments that select one view.
	 *
	 * This is the single place that decides what "our favorites" or "never
	 * cooked" means. The browse row on the archive and the archive itself both
	 * build on it, so a row can never list recipes its own "show all" page
	 * leaves out — which is exactly what two separately written queries would
	 * eventually do.
	 *
	 * Note what is NOT here: posts_per_page, post_status, the unlisted clause.
	 * Those belong to the caller, because a row of eight and a paginated
	 * archive want different answers.
	 *
	 * @param string $view View slug; an unknown one is treated as 'new'.
	 * @return array<string,mixed>
	 */
	public static function browse_view_args( string $view ): array {

		switch ( $view ) {

			case 'fav':
				return array(
					'orderby'    => 'date',
					'order'      => 'DESC',
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'meta_query' => array(
						array(
							'key'   => self::META_IS_FAVORITE,
							'value' => '1',
						),
					),
				);

			case 'popular':
				// A recipe carries the counter only once it has been cooked,
				// so joining on the key already restricts this to one or more.
				return array(
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_key' => self::META_COOK_COUNT,
					'orderby'  => array(
						'meta_value_num' => 'DESC',
						'date'           => 'DESC',
					),
				);

			case 'uncooked':
				// Never cooked is simply the absence of that meta.
				return array(
					'orderby'    => 'date',
					'order'      => 'DESC',
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'meta_query' => array(
						array(
							'key'     => self::META_COOK_COUNT,
							'compare' => 'NOT EXISTS',
						),
					),
				);

			case 'new':
			default:
				return array(
					'orderby' => 'date',
					'order'   => 'DESC',
				);
		}
	}

	/**
	 * The archive URL showing one view, or '' when there is no such view or no
	 * recipe archive.
	 *
	 * A query parameter on the existing archive rather than a URL of its own:
	 * the page stays a post type archive, so the template, the loop, the
	 * pagination and the search box keep working untouched — the same trick
	 * the archive search plays with mmm_s. paginate_links() carries query
	 * parameters into the page links by itself, so page two keeps the view.
	 *
	 * @param string $view View slug.
	 * @return string
	 */
	public static function browse_view_url( string $view ): string {

		if ( ! self::is_browse_view( $view ) ) {
			return '';
		}

		$base = get_post_type_archive_link( 'mmm_recipe' );

		if ( ! $base ) {
			return '';
		}

		$slug = self::browse_view_slug( $view );

		// With pretty permalinks the rewrite rule below answers this path;
		// without them there is no rule to answer it and the parameter is all
		// there is. Both arrive at the same query.
		if ( '' !== $slug && get_option( 'permalink_structure' ) ) {
			return user_trailingslashit( trailingslashit( $base ) . $slug );
		}

		return add_query_arg( self::BROWSE_VIEW_VAR, $slug, $base );
	}

	/**
	 * Attaches the browse-view URLs. Called once per request from
	 * mixmymeals.php, the same way the cook counter is wired up.
	 *
	 * @return void
	 */
	public static function register_browse_views(): void {

		static $done = false;

		if ( $done ) {
			return;
		}

		$done = true;

		// After the post type is registered (init 10), before the plugin's own
		// flush runs (init 99).
		add_action( 'init', array( __CLASS__, 'add_browse_view_rules' ), 20 );
		add_filter( 'query_vars', array( __CLASS__, 'add_browse_view_query_var' ) );
		add_filter( 'wp_unique_post_slug', array( __CLASS__, 'reserve_browse_view_slugs' ), 10, 4 );
	}

	/**
	 * The rewrite rules that give every view a path of its own:
	 * /recipes/frequently-cooked/ and its page/2/.
	 *
	 * Registered at the top, so a view wins over the post type's own
	 * /recipes/{name}/ rule. That is a reservation, not a coincidence — the
	 * four slugs cannot also be recipe slugs, which is what
	 * reserve_browse_view_slugs() makes sure of on the way in.
	 *
	 * The rules are flushed whenever the archive slug or a translated view
	 * slug changes, because neither is something anyone re-saves permalinks
	 * for afterwards.
	 *
	 * @return void
	 */
	public static function add_browse_view_rules(): void {

		$base = self::archive_rewrite_base();

		if ( '' === $base ) {
			return;
		}

		$slugs = self::browse_view_slugs();

		foreach ( $slugs as $view => $slug ) {

			if ( '' === $slug ) {
				continue;
			}

			$target = 'index.php?post_type=mmm_recipe&' . self::BROWSE_VIEW_VAR . '=' . rawurlencode( $view );

			add_rewrite_rule( '^' . $base . '/' . $slug . '/?$', $target, 'top' );
			add_rewrite_rule(
				'^' . $base . '/' . $slug . '/page/([0-9]{1,})/?$',
				$target . '&paged=$matches[1]',
				'top'
			);
		}

		$fingerprint = md5( $base . '|' . (string) wp_json_encode( $slugs ) );

		if ( (string) get_option( self::BROWSE_RULES_OPTION ) !== $fingerprint ) {
			update_option( self::BROWSE_RULES_OPTION, $fingerprint );
			update_option( 'mixmymeals_flush_needed', 1 );
		}
	}

	/**
	 * The path the recipe archive lives under, without slashes.
	 *
	 * @return string
	 */
	private static function archive_rewrite_base(): string {

		$object = get_post_type_object( 'mmm_recipe' );

		if ( ! $object instanceof WP_Post_Type ) {
			return '';
		}

		$base = 'mmm_recipe';

		if ( is_array( $object->rewrite ) && ! empty( $object->rewrite['slug'] ) ) {
			$base = (string) $object->rewrite['slug'];
		}

		// has_archive may name a path of its own instead of reusing the slug.
		if ( is_string( $object->has_archive ) && '' !== $object->has_archive ) {
			$base = $object->has_archive;
		}

		return trim( $base, '/' );
	}

	/**
	 * Makes mmm_view a query variable, so the rewrite rule can hand the view
	 * to WP_Query instead of it being dropped as unknown.
	 *
	 * @param string[] $vars Registered query variables.
	 * @return string[]
	 */
	public static function add_browse_view_query_var( $vars ) {

		$vars[] = self::BROWSE_VIEW_VAR;

		return $vars;
	}

	/**
	 * Keeps recipes out of the four paths the views occupy.
	 *
	 * Without this a recipe called "Frequently cooked" would take the slug
	 * haeufig-gekocht, and its page would be unreachable ever after — the view
	 * rule sits above the post type's own and answers first. Renaming the
	 * recipe on the way in is the lesser surprise: it happens while somebody is
	 * looking at the editor, rather than silently months later.
	 *
	 * Both vocabularies are reserved, the internal keys as well as the
	 * translated slugs, because both resolve to a view.
	 *
	 * @param string $slug      The unique slug WordPress settled on.
	 * @param int    $post_id   Post ID.
	 * @param string $post_status Post status.
	 * @param string $post_type Post type.
	 * @return string
	 */
	public static function reserve_browse_view_slugs( $slug, $post_id, $post_status, $post_type ) {

		if ( 'mmm_recipe' !== $post_type ) {
			return $slug;
		}

		$taken = array_merge( self::browse_views(), array_values( self::browse_view_slugs() ) );

		if ( ! in_array( (string) $slug, $taken, true ) ) {
			return $slug;
		}

		$suffix = 2;

		while ( in_array( $slug . '-' . $suffix, $taken, true ) ) {
			++$suffix;
		}

		return $slug . '-' . $suffix;
	}

	/**
	 * The word one view wears in the URL.
	 *
	 * Translatable, so a German site can link to ?mmm_view=favoriten instead of
	 * the internal key — and with a filter beside it, so a site can pin its own
	 * wording without owning a translation file.
	 *
	 * Normalised through sanitize_title() on both sides, the same function that
	 * normalises the incoming parameter, so an accent or a capital letter in a
	 * translation cannot make a slug that never matches itself. A translation
	 * that ends up empty falls back to the key.
	 *
	 * @param string $view Internal view key.
	 * @return string
	 */
	public static function browse_view_slug( string $view ): string {

		$slugs = self::browse_view_slugs();

		return $slugs[ $view ] ?? '';
	}

	/**
	 * All views as key => URL slug.
	 *
	 * @return array<string,string>
	 */
	public static function browse_view_slugs(): array {

		$slugs = array();

		foreach ( self::browse_views() as $view ) {

			$slug = sanitize_title( self::translated_view_slug( $view ) );

			$slugs[ $view ] = '' !== $slug ? $slug : $view;
		}

		/**
		 * Filters the URL slugs of the recipe browse views.
		 *
		 * @param array<string,string> $slugs View key => slug.
		 */
		$slugs = (array) apply_filters( 'mixmymeals_browse_view_slugs', $slugs );

		return $slugs;
	}

	/**
	 * The translatable slug of one view.
	 *
	 * A switch of literal _x() calls rather than a loop over a built string:
	 * the string extractor reads the source, and a slug assembled at runtime
	 * would never reach the .pot file.
	 *
	 * @param string $view Internal view key.
	 * @return string
	 */
	private static function translated_view_slug( string $view ): string {

		switch ( $view ) {

			case 'fav':
				return _x( 'fav', 'browse view URL slug', 'mixmymeals' );

			case 'popular':
				return _x( 'popular', 'browse view URL slug', 'mixmymeals' );

			case 'uncooked':
				return _x( 'uncooked', 'browse view URL slug', 'mixmymeals' );

			case 'new':
				return _x( 'new', 'browse view URL slug', 'mixmymeals' );

			default:
				return '';
		}
	}

	/**
	 * The view a URL slug names, or '' when it names none.
	 *
	 * Both vocabularies are accepted, and the internal keys win: a link shared
	 * before a slug was translated — or before the site changed language — has
	 * to keep working, and that guarantee is worth more than the freedom to
	 * translate a slug into another view's key. Which is also the one way a
	 * translation can go wrong here, and it fails towards the older meaning.
	 *
	 * @param string $slug Raw slug from the URL.
	 * @return string
	 */
	public static function resolve_browse_view( string $slug ): string {

		$slug = sanitize_title( $slug );

		if ( '' === $slug ) {
			return '';
		}

		if ( self::is_browse_view( $slug ) ) {
			return $slug;
		}

		$view = array_search( $slug, self::browse_view_slugs(), true );

		return is_string( $view ) ? $view : '';
	}

	/**
	 * The view the current request asks for, or '' for the plain archive.
	 *
	 * Validated against the known list rather than passed through: the value
	 * picks a database query and names a heading on a public page.
	 *
	 * @return string
	 */
	public static function current_browse_view(): string {

		// The rewrite rule fills the query variable; the parameter is what is
		// left on an install without pretty permalinks, and what older links
		// carry. sanitize_title() runs here as well as in resolve_browse_view()
		// — it is idempotent, and neither a reader nor the sniff should have to
		// follow a call to see that the input is sanitised.
		$raw = (string) get_query_var( self::BROWSE_VIEW_VAR, '' );

		if ( '' === $raw ) {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only public GET filter, like mmm_s.
			$raw = isset( $_GET[ self::BROWSE_VIEW_VAR ] )
				? sanitize_title( wp_unslash( $_GET[ self::BROWSE_VIEW_VAR ] ) )
				: '';
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
		}

		return self::resolve_browse_view( $raw );
	}

	/**
	 * Post meta holding the hand-set correction to the measured cook count
	 * (absent when zero, and deliberately signed). The counter is always this
	 * plus the measured plannings, clamped at zero, so editing it never stops
	 * the recipe from counting on: set 12, cook it again, get 13.
	 *
	 * A negative value is what lets the counter be set below — or to zero
	 * despite — what was actually planned. Only the result is floored at zero;
	 * the correction keeps its magnitude so later plannings still count.
	 */
	const META_COOK_ADJUST = '_mmm_cook_count_adjust';

	/** Post types the cook counter observes, and the meal meta linking to a recipe. */
	const MEAL_TYPE        = 'mmm_meal';
	const PLAN_TYPE        = 'mmm_meal_plan';
	const MEAL_RECIPE_META = 'recipe_id';

	/** Option storing the cook-count backfill version already applied. */
	const COOK_BACKFILL_OPTION = 'mixmymeals_cook_count_version';

	/** Bump to force a full cook-count recount on the next admin request. */
	const COOK_BACKFILL_VERSION = '1';

	/**
	 * Recipe IDs whose cook counter needs recomputing, collected during the
	 * request and flushed once on shutdown. Publishing a plan with 21 meals
	 * therefore costs one recount per distinct recipe instead of one per meal.
	 *
	 * @var array<int, true>
	 */
	private static $cook_dirty = array();

	/**
	 * Whether the shutdown flush for cook counts is already registered.
	 *
	 * @var bool
	 */
	private static $cook_flush_hooked = false;

	private int $post_id;

	/**
	 * @param int $post_id Recipe post ID.
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
	public static function create( string $title, string $status = 'draft', string $excerpt = '' ): self {
		$post_id = wp_insert_post( array(
			'post_type'    => 'mmm_recipe',
			'post_title'   => sanitize_text_field( $title ),
			'post_status'  => $status,
			'post_excerpt' => sanitize_textarea_field( $excerpt ),
		), true );

		if ( is_wp_error( $post_id ) ) {
			throw new \Exception( esc_html( $post_id->get_error_message() ) );
		}

		return new self( $post_id );
	}

	public static function exists_by_id( int $post_id ): bool {

		return $post_id
		       && get_post_type( $post_id ) === 'mmm_recipe'
		       /*&& get_post_status( $post_id ) === 'publish'*/; // Checking for status published causes bugs with posts in draft status!
	}

	/*
	public function exists(): bool {
		return get_post_status( $this->post_id ) === 'publish';
	}
	*/

	/*
	---------------------------------------------------------- */
	/*
	STEPS HANDLING                                             */
	/* ---------------------------------------------------------- */

	/**
	 * Get all published recipes (cached).
	 *
	 * @return WP_Post[] Array of recipe posts.
	 */
	/** @return WP_Post[] */
	public static function get_all_recipes(): array {
		static $cache = null;

		if ( $cache === null ) {
			$cache = get_posts(
				array(
					'post_type'      => 'mmm_recipe',
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
	 * Liefert alle Terms der Rezept-Taxonomie.
	 *
	 * @param   array $args  Optional. Zusätzliche Argumente für get_terms.
	 *
	 * @return WP_Term[]|WP_Error
	 */
	/** @return WP_Term[]|WP_Error */
	public static function get_all_recipe_categories( array $args = array() ): array|WP_Error {
		$defaults = array(
			'taxonomy'   => 'mmm_recipe_category',
			'hide_empty' => true,
			// false, falls du auch leere Kategorien zeigen möchtest
			'orderby'    => 'name',
			'order'      => 'ASC',
		);
		$args     = wp_parse_args( $args, $defaults );

		return get_terms( $args );
	}

	/**
	 * Liefert Rezepte für einen Term (Term-ID oder Term-Objekt akzeptiert).
	 *
	 * @param   int|WP_Term $term        Term-ID oder Term-Objekt.
	 * @param   array       $query_args  Zusätzliche WP_Query-Argumente (z.B. posts_per_page, order, orderby).
	 *
	 * @return WP_Post[] Array von Post-Objekten.
	 */
	/**
	 * @param int|WP_Term $term
	 * @return WP_Post[]
	 */
	public static function get_recipes_by_term( int|WP_Term $term, array $query_args = array() ): array {
		if ( is_object( $term ) && isset( $term->term_id ) ) {
			$term_id = absint( $term->term_id );
		} else {
			$term_id = absint( $term );
		}

		if ( ! $term_id ) {
			return array();
		}

		$defaults = array(
			'post_type'      => 'mmm_recipe',
			'posts_per_page' => - 1,
			'post_status'    => 'publish',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- term filter on an indexed taxonomy; no API alternative.
				array(
					'taxonomy' => 'mmm_recipe_category',
					'field'    => 'term_id',
					'terms'    => $term_id,
				),
			),
		);

		$args = wp_parse_args( $query_args, $defaults );

		return get_posts( $args );
	}

	/**
	 * Liefert Rezepte ohne zugeordnete Term (ohne Kategorie).
	 *
	 * @param   array $query_args  Zusätzliche Query-Argumente.
	 *
	 * @return WP_Post[]
	 */
	/** @return WP_Post[] */
	public static function get_recipes_without_category( array $query_args = array() ): array {
		$defaults = array(
			'post_type'      => 'mmm_recipe',
			'posts_per_page' => - 1,
			'post_status'    => 'publish',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- term filter on an indexed taxonomy; no API alternative.
				array(
					'taxonomy' => 'mmm_recipe_category',
					'operator' => 'NOT EXISTS',
				),
			),
		);

		$args = wp_parse_args( $query_args, $defaults );

		return get_posts( $args );
	}

	public function set_steps( array $steps ): bool {

		if ( ! $this->is_valid() ) {
			return false;
		}

		$cleaned = array();

		foreach ( $steps as $step ) {

			// HTML zentral säubern, damit REST/Importer/Admin denselben
			// Schutz erhalten: Titel ohne Markup, Beschreibung nur mit
			// erlaubtem Post-HTML (kein <script> etc.).
			$title = sanitize_text_field( $step['title'] ?? '' );
			$desc  = wp_kses_post( trim( (string) ( $step['description'] ?? '' ) ) );
			$image = intval( $step['image'] ?? 0 );

			// Leere Schritte überspringen
			if ( empty( $title ) && empty( $desc )
			     && empty( $step['ingredients'] ) ) {
				continue;
			}

			$new_step = array(
				'title'       => $title,
				'description' => $desc,
				'image'       => $image,
				'ingredients' => array(),
			);

			// Zutaten übernehmen
			if ( ! empty( $step['ingredients'] )
			     && is_array( $step['ingredients'] ) ) {

				foreach ( $step['ingredients'] as $ing ) {

					$ing_id    = intval( $ing['id'] ?? 0 );
					$amount    = floatval( $ing['amount'] ?? 0 );
					$amount_id = intval( $ing['amount_id'] ?? 0 );
					$product   = intval( $ing['product_id'] ?? 0 );

					/*
					 * A pin only counts where the product belongs to the
					 * ingredient it was pinned under. Ingredients get swapped
					 * in a step long after a product was chosen, and a pin
					 * left pointing at somebody else's article would quietly
					 * decide this recipe's diet.
					 */
					if ( $product > 0 && (int) wp_get_post_parent_id( $product ) !== $ing_id ) {
						$product = 0;
					}

					// Nur Zutaten mit gültiger ID übernehmen
					if ( $ing_id <= 0 ) {
						continue;
					}

					$new_step['ingredients'][] = array(
						'id'         => $ing_id,
						'amount'     => $amount,
						'amount_id'  => $amount_id,
						'optional'   => ! empty( $ing['optional'] ),
						'product_id' => $product,
					);
				}
			}

			$cleaned[] = $new_step;
		}

		update_post_meta( $this->post_id, '_rmm_steps', $cleaned );

		return true;
	}

	public function purge_steps(): bool {

		if ( ! $this->is_valid() ) {
			return false;
		}

		delete_post_meta( $this->post_id, '_rmm_steps' );

		return true;
	}

	/**
	 * Calculate nutrition values per serving for this recipe.
	 *
	 * @return array Nutrition values per serving (keys: calories, fat, carbs, protein, sugar, fiber, salt)
	 */
	public function calculate_nutrition_per_serving(): array {

		if ( ! $this->is_valid() ) {
			return [];
		}

		[ $totals, $total_weight ] = $this->_calculate_total_nutrition( 1.0 );

		$servings = max(1.0, floatval($this->get_servings()));

		foreach ($totals as $key => $val) {
			$totals[$key] = $val / $servings;
		}

		$totals['weight_per_serving'] = $total_weight / $servings;

		return $totals;
	}

	/**
	 * What choosing a different product would do to one serving of this recipe.
	 *
	 * The same comparison the plan page shows, in the unit a dish is eaten in.
	 * Per serving and not per plan day on purpose: a plan spreads one meal
	 * across its whole length and can dilute a real choice into a couple of
	 * kilocalories, while here the figure is the portion in front of somebody.
	 *
	 * @return array<int, array{ingredient_id:int, ingredient:string, meals:int, current:array<string,float>, products:array}>
	 */
	public function calc_product_impact(): array {

		if ( ! $this->is_valid() ) {
			return array();
		}

		$servings = max( 1.0, (float) $this->get_servings() );
		$weights  = array();

		/*
		 * get_ingredients_scaled() returns one row per step, so an ingredient
		 * used in three steps arrives three times. All of it is in the dish, so
		 * all of it is added.
		 */
		foreach ( $this->get_ingredients_scaled( 1.0 ) as $ing ) {

			$id     = (int) ( $ing['id'] ?? 0 );
			$weight = (float) ( $ing['weight_g'] ?? 0 );

			if ( $id <= 0 || $weight <= 0 ) {
				continue;
			}

			if ( ! isset( $weights[ $id ] ) ) {
				// meals stays 0: "in 3 meals" belongs to a plan. A recipe is
				// one dish and saying so would be filler.
				$weights[ $id ] = array(
					'grams' => 0.0,
					'meals' => 0,
				);
			}

			$weights[ $id ]['grams'] += $weight / $servings;
		}

		if ( empty( $weights ) ) {
			return array();
		}

		$current = array();

		foreach ( $this->calculate_nutrition_per_serving() as $key => $value ) {
			$current[ $key ] = (float) $value;
		}

		return Mixmymeals_Product::impact_groups( $weights, $current, 1.0 );
	}

	private function _calculate_total_nutrition(float $scale = 1.0): array {

		$ingredients = $this->get_ingredients_scaled($scale);

		$totals = array_fill_keys(
			['calories','fat','carbs','protein','sugar','fiber','salt'],
			0.0
		);

		$total_weight = 0.0;

		foreach ($ingredients as $ing) {

			$ingredient_obj = new Mixmymeals_Ingredient($ing['id']);
			if (!$ingredient_obj->is_valid()) continue;

			$nutrition = $ingredient_obj->get_nutrition();
			$weight = floatval($ing['weight_g']);

			if ($weight <= 0) continue;

			$total_weight += $weight;

			// Zweiter Gürtel: get_nutrition() liefert bereits float|null, aber
			// diese Rechnung darf unter keinen Umständen fatal werden — sie
			// hängt an der Rezeptliste im Admin und an jeder Rezeptseite.
			$totals['calories'] += (float) ( $nutrition['calories_100'] ?? 0 ) * $weight / 100;
			$totals['fat']      += (float) ( $nutrition['fat'] ?? 0 ) * $weight / 100;
			$totals['carbs']    += (float) ( $nutrition['carbs'] ?? 0 ) * $weight / 100;
			$totals['protein']  += (float) ( $nutrition['protein'] ?? 0 ) * $weight / 100;
			$totals['sugar']    += (float) ( $nutrition['sugar'] ?? 0 ) * $weight / 100;
			$totals['fiber']    += (float) ( $nutrition['fiber'] ?? 0 ) * $weight / 100;
			$totals['salt']     += (float) ( $nutrition['salt'] ?? 0 ) * $weight / 100;
		}

		return [$totals, $total_weight];
	}

	/** @return array<int, array<string, mixed>> */
	public function get_steps(): array {

		if ( ! $this->is_valid() ) {
			return [];
		}

		$steps = get_post_meta( $this->post_id, '_rmm_steps', true );

		return is_array( $steps ) ? $steps : array();
	}

	public function get_servings(): float {

		if ( ! $this->is_valid() ) {
			return 1;
		}

		$servings = get_post_meta( $this->post_id, '_mmm_servings', true );

		return $servings !== '' ? floatval( $servings ) : 1;
	}

	/**
	 * Calculate nutrition values per 100 g for this recipe.
	 *
	 * @return array Nutrition values per 100 g (keys: calories, fat, carbs, protein, sugar, fiber, salt)
	 */
	public function calculate_nutrition_per_100g(): array {

		if ( ! $this->is_valid() ) {
			return [];
		}

		[ $totals, $total_weight ] = $this->_calculate_total_nutrition();

		if ($total_weight > 0) {
			foreach ($totals as $key => $val) {
				$totals[$key] = ($val / $total_weight) * 100;
			}
		}

		return $totals;
	}

	public function calculate_cost_per_serving(): float {
		if ( ! $this->is_valid() ) {
			return 0.0;
		}

		$servings    = $this->get_servings();
		$scale       = $servings > 0 ? 1.0 / $servings : 1.0;
		$ingredients = $this->get_ingredients_scaled( $scale );
		$total_cost  = 0.0;

		foreach ( $ingredients as $ing ) {
			$ingredient_obj = new Mixmymeals_Ingredient( $ing['id'] );
			if ( ! $ingredient_obj->is_valid() ) {
				continue;
			}

			$total_cost += $ingredient_obj->calculate_cost_for_weight( $ing['weight_g'] );
		}

		return $total_cost;
	}

	public function set_servings( float $servings ): bool {
		if ( ! $this->is_valid() ) {
			return false;
		}
		$servings = max( 1, abs( $servings ) ); // Mindestens 1 Portion
		update_post_meta( $this->post_id, '_mmm_servings', $servings );

		return true;
	}

	/**
	 * Get a scaled and normalized list of ingredients for this recipe.
	 * (To substitute get_ingredients_normalized).
	 *
	 * This method applies a scaling factor to all ingredient amounts and
	 * returns a unified structure that can be used for display, nutrition
	 * calculation, and shopping lists.
	 *
	 * Special handling:
	 * - The scaled amount is snapped to what the unit can be split into: whole
	 *   ones when it is marked indivisible, halves when it carries a divisor
	 *   of 2, and so on. A unit without either takes any value.
	 * - The resulting weight (in grams) is adjusted accordingly to ensure
	 *   correct nutrition calculations.
	 *
	 * Each returned item contains:
	 * - id (int)            Ingredient post ID
	 * - name (string)       Ingredient name
	 * - amount (float)      Scaled amount, snapped to the unit's divisor
	 * - unit (string)       Unit label (e.g. "g", "pcs", "can")
	 * - weight_g (float)    Total weight in grams after scaling
	 * - step_divisor (int)  1 = whole units only, n = 1/n steps, 0 = any value
	 * - amount_id (int)     Unit/amount post ID (if applicable)
	 *
	 * @param float $scale Scaling factor (e.g. 0.5 for half, 2 for double).
	 *
	 * @return array<int, array{
	 *     id: int,
	 *     name: string,
	 *     amount: float,
	 *     unit: string,
	 *     weight_g: float,
	 *     amount_id: int
	 * }>
	 */
	public function get_ingredients_normalized(): array {

		if ( ! $this->is_valid() ) {
			return [];
		}

		$steps       = $this->get_steps();
		$ingredients = array();

		foreach ( $steps as $step ) {
			foreach ( ( $step['ingredients'] ?? [] ) as $ing ) {

				$id         = intval( $ing['id'] ?? 0 );
				$amount     = floatval( $ing['amount'] ?? 0 );
				$amount_id  = intval( $ing['amount_id'] ?? 0 );
				$product_id = intval( $ing['product_id'] ?? 0 );

				if ( $id <= 0 || $amount <= 0 ) {
					continue;
				}

				// Validierung
				$ingredient_obj = new Mixmymeals_Ingredient( $id );
				if ( ! $ingredient_obj->is_valid() ) {
					continue;
				}

				$unit_title      = '';
				$weight_per_unit = 0.0;

				// Einheit existiert → nutze sie
				if ( $amount_id > 0 ) {
					$unit_obj        = new Mixmymeals_Ingredient_Amount( $amount_id );
					$unit_title      = $unit_obj->get_title();
					$weight_per_unit = floatval( $unit_obj->get_weight() );
				}

				// Gewicht berechnen
				if ( $weight_per_unit > 0 ) {
					$total_weight = $amount * $weight_per_unit;
				} else {
					// Keine Einheit → amount ist vermutlich selbst in Gramm
					$unit_title   = 'g';
					$total_weight = $amount;
				}

				$ingredients[] = array(
					'id'       => $id,
					'name'     => get_the_title( $id ), // bewusst WP-Funktion
					'amount'   => round( $amount, 1 ),
					'unit'     => $unit_title,
					'weight_g' => round( $total_weight ),
					'optional'   => ! empty( $ing['optional'] ),
					'product_id' => $product_id,
				);
			}
		}

		return $ingredients;
	}

	/*
	 *
	 */
	public function get_ingredients_scaled( float $scale ): array {

		if ( ! $this->is_valid() ) {
			return [];
		}

		$steps       = $this->get_steps();
		$ingredients = array();

		foreach ( $steps as $step_index => $step) {
			foreach ( ( $step['ingredients'] ?? [] ) as $ing ) {

				$id         = intval( $ing['id'] ?? 0 );
				$amount     = floatval( $ing['amount'] ?? 0 );
				$amount_id  = intval( $ing['amount_id'] ?? 0 );
				$product_id = intval( $ing['product_id'] ?? 0 );

				if ( $id <= 0 || $amount <= 0 ) {
					continue;
				}

				$ingredient_obj = new Mixmymeals_Ingredient( $id );
				if ( ! $ingredient_obj->is_valid() ) {
					continue;
				}

				// Skalieren
				$amount *= $scale;
				$raw_amount = $amount; // Bedarf vor Unteilbarkeits-Rundung

				$unit_title      = '';
				$weight_per_unit = 0.0;
				$is_indivisible  = false;
				$step_divisor    = 0;

				if ( $amount_id > 0 ) {
					$unit_obj        = new Mixmymeals_Ingredient_Amount( $amount_id );
					$unit_title      = $unit_obj->get_title();
					$weight_per_unit = floatval( $unit_obj->get_weight() );
					$is_indivisible  = $unit_obj->is_indivisible();
					$step_divisor    = $unit_obj->get_step_divisor();
				}

				/*
				 * Snap the scaled amount to what the unit can actually be split
				 * into: whole ones for an indivisible unit (divisor 1), halves
				 * for a tin that can be halved (divisor 2), and so on. A unit
				 * without a divisor takes any value.
				 *
				 * Rounded, not rounded up: this is what the recipe uses, and
				 * scaling four servings down to three should not silently add
				 * a tin. The shopping list rounds up instead — see
				 * Mixmymeals_Meal_Plan::calc_shopping_list().
				 *
				 * The arithmetic runs on the integer multiple rather than on a
				 * 1/divisor step, so thirds do not drift into 0.33333.
				 */
				if ( $step_divisor > 0 ) {
					$amount = max( 1, round( $amount * $step_divisor ) ) / $step_divisor;
				}

				// Gewicht berechnen
				if ( $weight_per_unit > 0 ) {
					$total_weight = $amount * $weight_per_unit;
				} else {
					$unit_title   = 'g';
					$total_weight = $amount;
				}

				$ingredients[] = array(
					'id'              => $id,
					'name'            => get_the_title( $id ),
					'amount'          => $amount,
					'raw_amount'      => $raw_amount,
					'unit'            => $unit_title,
					'weight_g'        => $total_weight,
					'weight_per_unit' => $weight_per_unit,
					'is_indivisible'  => $is_indivisible,
					'step_divisor'    => $step_divisor,
					'amount_id'       => $amount_id,
					'optional'        => ! empty( $ing['optional'] ),
					'product_id'      => $product_id,
					'step'            => $step_index,
				);
			}
		}

		return $ingredients;
	}

	/**
	 * Liefert alle Kategorien (Terms) für dieses Rezept.
	 *
	 * @param   array $args  Zusätzliche Argumente für get_the_terms (optional).
	 *
	 * @return WP_Term[] Array von Term-Objekten oder leeres Array.
	 */
	/** @return WP_Term[] */
	public function get_categories( array $args = array() ): array {

		if ( ! $this->is_valid() ) {
			return [];
		}

		$terms = get_the_terms( $this->post_id, 'mmm_recipe_category' );


		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		// Optional: sortieren nach Name, falls gewünscht
		if ( ! empty( $args['orderby'] ) && $args['orderby'] === 'name' ) {
			usort(
				$terms,
				function ( $a, $b ) {
					return strcasecmp( $a->name, $b->name );
				}
			);
		}

		return $terms;
	}

	/** @return int[] */
	public function get_equipment(): array {

		if ( ! $this->is_valid() ) {
			return [];
		}

		$ids = get_post_meta( $this->post_id, '_mmm_equipment', true );
		return is_array( $ids ) ? $ids : [];
	}

	/** @param int[] $ids */
	public function set_equipment( array $ids ): bool {

		if ( ! $this->is_valid() ) {
			return false;
		}

		$clean = array_filter( array_map( 'intval', $ids ), fn( $id ) => $id > 0 );

		update_post_meta( $this->post_id, '_mmm_equipment', $clean );

		return true;
	}

	/**
	 * Recipes this recipe is paired with ("Dazu passt" / goes well with).
	 *
	 * @return int[]
	 */
	public function get_related_recipes(): array {

		if ( ! $this->is_valid() ) {
			return [];
		}

		$ids = get_post_meta( $this->post_id, '_mmm_related_recipes', true );
		return is_array( $ids ) ? array_values( array_filter( array_map( 'intval', $ids ), fn( $id ) => $id > 0 && $id !== $this->post_id ) ) : [];
	}

	/**
	 * Stores the paired recipes ("Dazu passt"). IDs are cleaned and the recipe
	 * itself is never allowed to reference itself.
	 *
	 * @param int[] $ids Recipe post IDs.
	 * @return bool
	 */
	public function set_related_recipes( array $ids ): bool {

		if ( ! $this->is_valid() ) {
			return false;
		}

		$clean = array_values( array_unique( array_filter(
			array_map( 'intval', $ids ),
			fn( $id ) => $id > 0 && $id !== $this->post_id
		) ) );

		update_post_meta( $this->post_id, '_mmm_related_recipes', $clean );

		return true;
	}

	/**
	 * Recipes that list THIS recipe as a pairing ("Passend zu" / fits with).
	 * This is the computed reverse side of get_related_recipes(); it is not
	 * stored separately.
	 *
	 * @param string $status Post status to include (default 'publish').
	 * @return int[]
	 */
	public function get_reverse_related_recipes( string $status = 'publish' ): array {

		if ( ! $this->is_valid() ) {
			return [];
		}

		$candidates = get_posts( [
			'post_type'      => 'mmm_recipe',
			'posts_per_page' => -1,
			'post_status'    => $status,
			'fields'         => 'ids',
			'post__not_in'   => [ $this->post_id ], // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- excludes the single current post, not a growing list.
			'orderby'        => 'title',
			'order'          => 'ASC',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_key'       => '_mmm_related_recipes', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- targeted lookup on an indexed key, not a scan.
		] );

		$out = [];
		foreach ( $candidates as $rid ) {
			$rel = get_post_meta( $rid, '_mmm_related_recipes', true );
			if ( is_array( $rel ) && in_array( $this->post_id, array_map( 'intval', $rel ), true ) ) {
				$out[] = (int) $rid;
			}
		}

		return $out;
	}

	/**
	 * Published meal plans that contain this recipe (via an mmm_meal child whose
	 * recipe_id points here), newest first.
	 *
	 * @return int[] Meal plan post IDs.
	 */
	public function get_meal_plans(): array {

		if ( ! $this->is_valid() ) {
			return [];
		}

		// Meals referencing this recipe; their post_parent is the meal plan.
		$parents = get_posts( [
			'post_type'      => 'mmm_meal',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'id=>parent',
			'no_found_rows'  => true,
			'meta_key'       => 'recipe_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- targeted lookup on an indexed key, not a scan.
			'meta_value'     => (string) $this->post_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- exact-match lookup; the alternative would be fetching everything and filtering in PHP.
		] );

		$plan_ids = array_values( array_unique( array_filter( array_map( 'intval', $parents ) ) ) );
		if ( empty( $plan_ids ) ) {
			return [];
		}

		return get_posts(
			Mixmymeals_Post_Types::exclude_unlisted( [
				'post_type'      => 'mmm_meal_plan',
				'post_status'    => 'publish',
				'post__in'       => $plan_ids,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			] )
		);
	}

	/*
	----------------------------------------------------------
	COOK COUNTER

	How often a recipe has been cooked, measured as the number of meals
	(mmm_meal) that reference it and sit in a published meal plan. Both the meal
	and its parent plan must be published: meals are created as 'publish' even
	inside a draft plan, so it is the plan's status that decides.

	The counter is never incremented or decremented, it is recomputed from the
	database for every affected recipe. A meal's recipe_id can be changed, meals
	get moved between plans, trashed and restored, and a plan flipping back to
	draft invalidates all of its meals at once — delta counting drifts under all
	of these, a recount does not.

	Recipes with a count of zero carry no meta at all. That keeps both consuming
	queries trivial: "never cooked" is a NOT EXISTS lookup and "cooked most
	often" an ordered meta_value_num lookup without a threshold clause.
	----------------------------------------------------------
	*/

	/**
	 * The stored cook count of this recipe.
	 *
	 * @return int
	 */
	public function get_cook_count(): int {

		if ( ! $this->is_valid() ) {
			return 0;
		}

		return max( 0, (int) get_post_meta( $this->post_id, self::META_COOK_COUNT, true ) );
	}

	/**
	 * The hand-set correction (0 when none was set, may be negative).
	 *
	 * @return int
	 */
	public function get_cook_adjustment(): int {

		if ( ! $this->is_valid() ) {
			return 0;
		}

		return (int) get_post_meta( $this->post_id, self::META_COOK_ADJUST, true );
	}

	/**
	 * Sets the counter to a given total by deriving the correction from it. Any
	 * total from zero upwards is honoured exactly, including totals below the
	 * measured plannings; real plannings keep counting on from there.
	 *
	 * @param int $total Desired counter value (negative is treated as zero).
	 * @return void
	 */
	public function set_cook_total( int $total ): void {

		if ( ! $this->is_valid() ) {
			return;
		}

		$this->store_cook_adjustment( max( 0, $total ) - $this->count_cooked() );
	}

	/**
	 * Writes the correction, keeping the "zero carries no meta" convention.
	 *
	 * @param int $adjust Correction, may be negative.
	 * @return void
	 */
	private function store_cook_adjustment( int $adjust ): void {

		if ( 0 !== $adjust ) {
			update_post_meta( $this->post_id, self::META_COOK_ADJUST, $adjust );
		} else {
			delete_post_meta( $this->post_id, self::META_COOK_ADJUST );
		}
	}

	/**
	 * Counts, straight from the database, how often this recipe is cooked: meals
	 * referencing it that are published and whose parent plan is published too.
	 *
	 * @return int
	 */
	public function count_cooked(): int {

		if ( ! $this->is_valid() ) {
			return 0;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} m ON m.ID = pm.post_id
				 INNER JOIN {$wpdb->posts} p ON p.ID = m.post_parent
				 WHERE pm.meta_key = %s
				   AND pm.meta_value = %s
				   AND m.post_type = %s
				   AND m.post_status = 'publish'
				   AND p.post_type = %s
				   AND p.post_status = 'publish'",
				self::MEAL_RECIPE_META,
				(string) $this->post_id,
				self::MEAL_TYPE,
				self::PLAN_TYPE
			)
		);
	}

	/**
	 * Recomputes and stores the counter: the hand-set correction plus the
	 * measured plannings, never below zero. A resulting count of zero removes the
	 * meta entirely, so "never cooked" stays a plain NOT EXISTS query — setting a
	 * recipe to 0 by hand therefore returns it to that list.
	 *
	 * @return void
	 */
	public function recount_cook_count(): void {

		if ( ! $this->is_valid() ) {
			return;
		}

		$measured = $this->count_cooked();
		$count    = $this->get_cook_adjustment() + $measured;

		/*
		 * A correction may not outlive what it was subtracting from. Without
		 * this, setting a recipe with five plannings to 0 and then deleting
		 * those plans would leave a -5 debt that silently swallows the next five
		 * real plannings. Trim it back to exactly cancel the measurement.
		 */
		if ( $count < 0 ) {
			$count = 0;
			$this->store_cook_adjustment( -$measured );
		}

		if ( $count > 0 ) {
			update_post_meta( $this->post_id, self::META_COOK_COUNT, $count );
		} else {
			delete_post_meta( $this->post_id, self::META_COOK_COUNT );
		}
	}

	/**
	 * Registers the cook counter. Static because recipes are instantiated per
	 * post, while the hooks have to be attached once per request — same pattern
	 * as Mixmymeals_Cart::register_ajax().
	 *
	 * Note that most of these watch meals and plans rather than recipes: the
	 * counter is a property of a recipe, but every event that changes it happens
	 * elsewhere.
	 *
	 * @return void
	 */
	public static function register_cook_count(): void {

		// Meal written: creation, recipe change, moved to another plan.
		add_action( 'wp_after_insert_post', array( __CLASS__, 'cook_count_on_post_saved' ), 10, 2 );

		// Status flips: a meal being trashed or restored, a plan going
		// draft/publish/trash (which revalues all of its meals at once).
		add_action( 'transition_post_status', array( __CLASS__, 'cook_count_on_status_change' ), 10, 3 );

		// recipe_id written: recount the new target …
		add_action( 'added_post_meta', array( __CLASS__, 'cook_count_on_meta_written' ), 10, 4 );
		add_action( 'updated_post_meta', array( __CLASS__, 'cook_count_on_meta_written' ), 10, 4 );

		// … and the previous one, read before it is overwritten or removed.
		add_action( 'update_post_meta', array( __CLASS__, 'cook_count_on_meta_replaced' ), 10, 3 );
		add_action( 'delete_post_meta', array( __CLASS__, 'cook_count_on_meta_replaced' ), 10, 3 );

		// The hand-set correction changed: fold it into the counter at once.
		add_action( 'added_post_meta', array( __CLASS__, 'cook_count_on_adjustment_changed' ), 10, 3 );
		add_action( 'updated_post_meta', array( __CLASS__, 'cook_count_on_adjustment_changed' ), 10, 3 );
		add_action( 'deleted_post_meta', array( __CLASS__, 'cook_count_on_adjustment_changed' ), 10, 3 );

		// Deletion: read the relations while the posts still exist. The recount
		// itself runs on shutdown, by which time the rows are gone.
		add_action( 'before_delete_post', array( __CLASS__, 'cook_count_on_before_delete' ), 10, 2 );

		add_action( 'admin_init', array( __CLASS__, 'maybe_backfill_cook_counts' ) );
	}

	/**
	 * Rebuilds the counter for every recipe from a single grouped query. Used by
	 * the backfill and available as a repair routine when the meta is ever
	 * suspected to be out of sync.
	 *
	 * @return void
	 */
	public static function recount_all_cook_counts(): void {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.meta_value AS recipe_id, COUNT(*) AS total
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} m ON m.ID = pm.post_id
				 INNER JOIN {$wpdb->posts} p ON p.ID = m.post_parent
				 WHERE pm.meta_key = %s
				   AND m.post_type = %s
				   AND m.post_status = 'publish'
				   AND p.post_type = %s
				   AND p.post_status = 'publish'
				 GROUP BY pm.meta_value",
				self::MEAL_RECIPE_META,
				self::MEAL_TYPE,
				self::PLAN_TYPE
			)
		);

		$counts = array();
		foreach ( (array) $rows as $row ) {
			$counts[ (int) $row->recipe_id ] = (int) $row->total;
		}

		$recipe_ids = get_posts(
			array(
				'post_type'      => 'mmm_recipe',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		$recipe_ids = array_map( 'intval', (array) $recipe_ids );

		if ( empty( $recipe_ids ) ) {
			return;
		}

		// One query for all existing meta, so the loop below only writes where
		// the stored value actually differs.
		update_meta_cache( 'post', $recipe_ids );

		foreach ( $recipe_ids as $recipe_id ) {

			$recipe = new self( $recipe_id );
			$stored = get_post_meta( $recipe_id, self::META_COOK_COUNT, true );

			// The correction is applied here too — otherwise the backfill would
			// silently drop every hand-set value. Same trimming as
			// recount_cook_count(): a correction never outlives its measurement.
			$measured = isset( $counts[ $recipe_id ] ) ? $counts[ $recipe_id ] : 0;
			$total    = $recipe->get_cook_adjustment() + $measured;

			if ( $total < 0 ) {
				$total = 0;
				$recipe->store_cook_adjustment( -$measured );
			}

			if ( $total > 0 ) {
				if ( (int) $stored !== $total ) {
					update_post_meta( $recipe_id, self::META_COOK_COUNT, $total );
				}
			} elseif ( '' !== $stored ) {
				delete_post_meta( $recipe_id, self::META_COOK_COUNT );
			}
		}
	}

	/**
	 * Runs the full recount once per backfill version, so existing content gets
	 * its counters without a manual step.
	 *
	 * @return void
	 */
	public static function maybe_backfill_cook_counts(): void {

		if ( self::COOK_BACKFILL_VERSION === get_option( self::COOK_BACKFILL_OPTION ) ) {
			return;
		}

		self::recount_all_cook_counts();
		update_option( self::COOK_BACKFILL_OPTION, self::COOK_BACKFILL_VERSION, false );
	}

	/**
	 * Queues a recipe for recomputation at the end of the request.
	 *
	 * @param int $recipe_id Recipe post ID.
	 * @return void
	 */
	private static function mark_cook_dirty( int $recipe_id ): void {

		if ( $recipe_id <= 0 ) {
			return;
		}

		self::$cook_dirty[ $recipe_id ] = true;

		if ( ! self::$cook_flush_hooked ) {
			self::$cook_flush_hooked = true;
			add_action( 'shutdown', array( __CLASS__, 'flush_cook_counts' ) );
		}
	}

	/**
	 * Queues every recipe used by the meals of a plan; a plan-wide status change
	 * or deletion revalues all of them at once.
	 *
	 * @param int $plan_id Meal plan post ID.
	 * @return void
	 */
	private static function mark_plan_cook_dirty( int $plan_id ): void {

		$meal_ids = get_posts(
			array(
				'post_type'      => self::MEAL_TYPE,
				'post_status'    => 'any',
				'post_parent'    => $plan_id,
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		foreach ( (array) $meal_ids as $meal_id ) {
			self::mark_cook_dirty( (int) get_post_meta( (int) $meal_id, self::MEAL_RECIPE_META, true ) );
		}
	}

	/**
	 * Recomputes every queued recipe once. Writing the counter meta does not
	 * re-enter the tracking hooks, which only watch recipe_id on meals.
	 *
	 * @return void
	 */
	public static function flush_cook_counts(): void {

		$recipe_ids       = array_keys( self::$cook_dirty );
		self::$cook_dirty = array();

		foreach ( $recipe_ids as $recipe_id ) {
			( new self( (int) $recipe_id ) )->recount_cook_count();
		}
	}

	/**
	 * A meal was inserted or updated (admin, REST or the cart): its current
	 * recipe may have gained or lost a plan.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public static function cook_count_on_post_saved( $post_id, $post ): void {

		if ( ! $post instanceof WP_Post || self::MEAL_TYPE !== $post->post_type ) {
			return;
		}

		self::mark_cook_dirty( (int) get_post_meta( (int) $post_id, self::MEAL_RECIPE_META, true ) );
	}

	/**
	 * A meal or a whole plan changed its publication status.
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Previous status.
	 * @param WP_Post $post       Post object.
	 * @return void
	 */
	public static function cook_count_on_status_change( $new_status, $old_status, $post ): void {

		if ( ! $post instanceof WP_Post || $new_status === $old_status ) {
			return;
		}

		if ( self::MEAL_TYPE === $post->post_type ) {
			self::mark_cook_dirty( (int) get_post_meta( (int) $post->ID, self::MEAL_RECIPE_META, true ) );
			return;
		}

		if ( self::PLAN_TYPE === $post->post_type ) {
			self::mark_plan_cook_dirty( (int) $post->ID );
		}
	}

	/**
	 * A meal's recipe_id was written: the recipe now referenced needs a recount.
	 *
	 * @param int    $meta_id    Meta row ID (unused).
	 * @param int    $object_id  Post ID the meta belongs to.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value New meta value.
	 * @return void
	 */
	public static function cook_count_on_meta_written( $meta_id, $object_id, $meta_key, $meta_value ): void {

		if ( self::MEAL_RECIPE_META !== $meta_key || get_post_type( (int) $object_id ) !== self::MEAL_TYPE ) {
			return;
		}

		self::mark_cook_dirty( (int) $meta_value );
	}

	/**
	 * A meal's recipe_id is about to be overwritten or removed. Fires before the
	 * write, so the stored value is still the recipe that is losing the meal.
	 *
	 * @param int|array $meta_id   Meta row ID(s) (unused).
	 * @param int       $object_id Post ID the meta belongs to.
	 * @param string    $meta_key  Meta key.
	 * @return void
	 */
	public static function cook_count_on_meta_replaced( $meta_id, $object_id, $meta_key ): void {

		if ( self::MEAL_RECIPE_META !== $meta_key || get_post_type( (int) $object_id ) !== self::MEAL_TYPE ) {
			return;
		}

		self::mark_cook_dirty( (int) get_post_meta( (int) $object_id, self::MEAL_RECIPE_META, true ) );
	}

	/**
	 * A recipe's correction was written or removed, so its stored counter needs
	 * to follow.
	 *
	 * @param int|array $meta_id   Meta row ID(s) (unused).
	 * @param int       $object_id Post ID the meta belongs to.
	 * @param string    $meta_key  Meta key.
	 * @return void
	 */
	public static function cook_count_on_adjustment_changed( $meta_id, $object_id, $meta_key ): void {

		if ( self::META_COOK_ADJUST !== $meta_key ) {
			return;
		}

		self::mark_cook_dirty( (int) $object_id );
	}

	/**
	 * A meal or plan is about to be deleted permanently.
	 *
	 * @param int          $post_id Post ID.
	 * @param WP_Post|null $post    Post object (passed since WP 5.5).
	 * @return void
	 */
	public static function cook_count_on_before_delete( $post_id, $post = null ): void {

		$post = $post instanceof WP_Post ? $post : get_post( (int) $post_id );

		if ( ! $post instanceof WP_Post ) {
			return;
		}

		if ( self::MEAL_TYPE === $post->post_type ) {
			self::mark_cook_dirty( (int) get_post_meta( (int) $post->ID, self::MEAL_RECIPE_META, true ) );
			return;
		}

		if ( self::PLAN_TYPE === $post->post_type ) {
			self::mark_plan_cook_dirty( (int) $post->ID );
		}
	}

	/*
	----------------------------------------------------------
	EDIT COUNTER
	----------------------------------------------------------
	*/

	/**
	 * How often this recipe has been edited since the counter exists.
	 *
	 * Post revisions look like the obvious source for this but are the wrong
	 * one: WordPress only versions post_title, post_content and post_excerpt
	 * (see _wp_post_revision_fields()), and wp_save_post_revision() bails out
	 * when none of those changed. A recipe's substance — steps, ingredients,
	 * servings, preparation time — lives in post meta, so reworking a recipe
	 * usually creates no revision at all. Hence a counter of our own, fed by
	 * count_edit_on_update() below, which catches every save.
	 *
	 * @return int
	 */
	public function get_edit_count(): int {

		if ( ! $this->is_valid() ) {
			return 0;
		}

		return max( 0, (int) get_post_meta( $this->post_id, self::META_EDIT_COUNT, true ) );
	}

	/**
	 * Sets the edit counter, so a wrong number can be corrected.
	 *
	 * @param int $count New value; zero removes the meta.
	 * @return void
	 */
	public function set_edit_count( int $count ): void {

		if ( ! $this->is_valid() ) {
			return;
		}

		$count = max( 0, $count );

		if ( $count > 0 ) {
			update_post_meta( $this->post_id, self::META_EDIT_COUNT, $count );
		} else {
			delete_post_meta( $this->post_id, self::META_EDIT_COUNT );
		}
	}

	/**
	 * Registers the edit counter. Static because recipes are instantiated per
	 * post, while the hook has to be attached once per request — same pattern
	 * as Mixmymeals_Cart::register_ajax().
	 *
	 * @return void
	 */
	public static function register_edit_count(): void {

		// post_updated instead of save_post: it does not fire when a post is
		// first created, so creating a recipe is not counted as editing it.
		add_action( 'post_updated', array( __CLASS__, 'count_edit_on_update' ), 10, 3 );
	}

	/**
	 * Increments the edit counter when a recipe is updated.
	 *
	 * @param int     $post_id     Post ID.
	 * @param WP_Post $post_after  Post after the update.
	 * @param WP_Post $post_before Post before the update.
	 * @return void
	 */
	public static function count_edit_on_update( $post_id, $post_after, $post_before ): void {

		if ( ! $post_after instanceof WP_Post || 'mmm_recipe' !== $post_after->post_type ) {
			return;
		}

		// Autosaves and revisions are not edits by the author's reckoning.
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		/*
		 * Publishing a brand-new recipe runs through post_updated as well,
		 * because the editor starts from an auto-draft. That is the creation,
		 * not an edit — otherwise every recipe would start at one.
		 */
		if ( $post_before instanceof WP_Post && 'auto-draft' === $post_before->post_status ) {
			return;
		}

		$recipe = new self( (int) $post_id );
		$recipe->set_edit_count( $recipe->get_edit_count() + 1 );
	}

	public function set_preparation_time( $minutes ): void {

		if ( ! $this->is_valid() ) {
			return;
		}

		$minutes = max( 0, absint( $minutes ) );
		update_post_meta( $this->post_id, '_mmm_preparation_time', $minutes );
	}

	/**
	 * How the **prepared dish** is kept is stored.
	 *
	 * A separate field from the ingredients' storage, exactly like the shelf life beside it,
	 * and for the same reason: flour lives in a cupboard, a flour-based sauce lives in the fridge. Neither
	 * condition follows from the other.
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
	 * Days the **prepared dish** keeps.
	 *
	 * Deliberately not the same quantity as an ingredient's shelf life, and
	 * stored separately: a stew of week-old vegetables keeps three days once
	 * cooked, a salad of the same vegetables keeps one. Neither number can be
	 * derived from the other, so neither may fall back to the other.
	 *
	 * @var string
	 */
	const META_SHELF_LIFE = '_mmm_shelf_life_days';

	/**
	 * How many days the prepared dish keeps.
	 *
	 * Returns **null** when nobody has said, which is a different claim from 0
	 * — zero means "eat it the day it is made", and a dish that must be eaten
	 * at once is exactly the case worth recording. Callers that want a number
	 * regardless have to decide what silence means themselves; the getter will
	 * not decide it for them.
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
	 * null clears the field; 0 is stored, because "keeps zero days" is an
	 * answer and not the absence of one. The same rule the product nutrition
	 * fields keep, and for the same reason.
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

	public function get_preparation_time(): int {

		if ( ! $this->is_valid() ) {
			return 0;
		}

		$time = get_post_meta( $this->post_id, '_mmm_preparation_time', true );
		return $time !== '' ? max( 0, intval( $time ) ) : 0;
	}

	public function get_priority(): int {
		if ( ! $this->is_valid() ) {
			return 0;
		}
		return (int) get_post_meta( $this->post_id, '_mmm_priority', true );
	}

	public function set_priority( int $priority ): void {
		if ( ! $this->is_valid() ) {
			return;
		}
		update_post_meta( $this->post_id, '_mmm_priority', max( 0, $priority ) );
	}

	/**
	 * The two diet classifications with their labels.
	 *
	 * Note there are older inline copies of this map in
	 * Mixmymeals_Post_Meta (the ingredient and product meta boxes) and in
	 * Mixmymeals_Shortcodes. They are not wrong, only duplicated; new readers
	 * should come here.
	 *
	 * @return array<string,string>
	 */
	public static function diet_labels(): array {

		$labels = array();

		foreach ( array_keys( Mixmymeals_Diet::types() ) as $slug ) {
			$labels[ $slug ] = Mixmymeals_Diet::label( $slug );
		}

		return $labels;
	}

	/**
	 * The label of a diet key, or '' when nothing is classified.
	 *
	 * @param string $diet Diet key.
	 * @return string
	 */
	public static function diet_label( string $diet ): string {
		return self::diet_labels()[ $diet ] ?? '';
	}

	/**
	 * The same two classifications, worded as what a dish is *suitable* for.
	 *
	 * Kept apart from diet_labels() on purpose. That pair states a fact about
	 * a thing — an ingredient is vegan, a product is vegan — and is what the
	 * schema, the product comparison and the admin fields need. A recipe or a
	 * plan cannot make that claim: whether the finished dish is vegan is
	 * decided at the shop, by which product lands in the basket. It can only
	 * be suitable to be cooked that way.
	 *
	 * @return array<string,string>
	 */
	public static function diet_suitability_labels(): array {

		$labels = array();

		foreach ( array_keys( Mixmymeals_Diet::types() ) as $slug ) {
			$labels[ $slug ] = Mixmymeals_Diet::suitability_label( $slug );
		}

		return $labels;
	}

	/**
	 * The suitability label of a diet key, or '' when nothing is classified.
	 *
	 * @param string $diet Diet key.
	 * @return string
	 */
	public static function diet_suitability_label( string $diet ): string {
		return self::diet_suitability_labels()[ $diet ] ?? '';
	}

	/**
	 * Reduces the diets of the parts to the diet of the whole.
	 *
	 * All vegan makes it vegan, all vegan or vegetarian makes it vegetarian,
	 * and one part with nothing stated leaves the question open for everything
	 * built from it. The rule a recipe applies to its ingredients and a plan to
	 * its dishes is the same rule, so it is written once.
	 *
	 * @param string[] $diets Diet of each part.
	 * @return string 'vegan', 'vegetarian', or ''.
	 */
	public static function combine_diets( array $diets ): string {

		$sets = array();

		foreach ( $diets as $diet ) {
			$sets[] = is_array( $diet ) ? $diet : array( (string) $diet );
		}

		return Mixmymeals_Diet::strongest( Mixmymeals_Diet::combine( $sets ), 'diet' );
	}

	/**
	 * Derives the diet type of the recipe from its ingredients.
	 *
	 * Returns 'vegan' if all ingredients are vegan, 'vegetarian' if all are
	 * vegan or vegetarian, and '' if any ingredient has no diet restriction
	 * or the recipe has no ingredients.
	 *
	 * @return string 'vegan', 'vegetarian', or ''.
	 */
	public function get_diet_type(): string {
		return Mixmymeals_Diet::strongest( $this->get_diets(), 'diet' );
	}

	/**
	 * Every diet this recipe is in force as, given the products currently
	 * chosen for its ingredients.
	 *
	 * @return string[] Known diet slugs.
	 */
	public function get_diets(): array {

		if ( ! $this->is_valid() ) {
			return array();
		}

		$sets = array();

		foreach ( $this->get_ingredients_normalized() as $ing ) {

			$product_id = (int) ( $ing['product_id'] ?? 0 );

			/*
			 * A product pinned in the step decides for this recipe, over
			 * whatever the ingredient resolves to on its own. That is the
			 * point of pinning one: "this dish is made with Alpro", not "with
			 * some soy yoghurt". Where nothing is pinned the ingredient
			 * answers as before, through its reference product.
			 *
			 * A pinned product silent on a question falls back to the
			 * ingredient for that axis — apply_product() merges per axis, so
			 * choosing a packet that says "vegan" does not erase what the
			 * ingredient said about gluten.
			 */
			if ( $product_id > 0 ) {
				$sets[] = Mixmymeals_Diet::apply_product(
					( new Mixmymeals_Ingredient( (int) $ing['id'] ) )->get_diets(),
					( new Mixmymeals_Product( $product_id ) )->get_diets()
				);
				continue;
			}

			$sets[] = ( new Mixmymeals_Ingredient( (int) $ing['id'] ) )->get_diets();
		}

		return Mixmymeals_Diet::combine( $sets );
	}

	/**
	 * What diet this recipe can be cooked for, if every ingredient is bought
	 * accordingly.
	 *
	 * The counterpart to get_diet_type(), which reports what the chosen
	 * reference products actually produce. This is what the chips on recipes,
	 * meals and plans show — see
	 * Mixmymeals_Ingredient::get_diet_suitability() for why.
	 *
	 * @return string 'vegan', 'vegetarian', or ''.
	 */
	public function get_diet_suitability(): string {
		return Mixmymeals_Diet::strongest( $this->get_diet_suitabilities(), 'diet' );
	}

	/**
	 * Every diet this recipe can be cooked for, if each ingredient is bought
	 * accordingly. What the chips show.
	 *
	 * @return string[] Known diet slugs.
	 */
	public function get_diet_suitabilities(): array {

		if ( ! $this->is_valid() ) {
			return array();
		}

		$sets = array();

		foreach ( $this->get_ingredients_normalized() as $ing ) {
			$sets[] = ( new Mixmymeals_Ingredient( (int) $ing['id'] ) )->get_diet_suitabilities();
		}

		return Mixmymeals_Diet::combine( $sets );
	}


	/**
	 * Author box accessors. Thin delegates to Mixmymeals_Post_Meta, which
	 * holds the storage and admin fields shared with meal plans; the card itself
	 * is rendered by the author-box shortcodes.
	 * An empty name falls the box back to the WordPress author.
	 *
	 * @return string
	 */
	public function get_author_name(): string {
		return Mixmymeals_Post_Meta::get_author_name( $this->post_id );
	}

	/**
	 * Free-form description shown in the author box (limited HTML allowed).
	 *
	 * @return string
	 */
	public function get_author_text(): string {
		return Mixmymeals_Post_Meta::get_author_text( $this->post_id );
	}

	/**
	 * Link target of the author box, or '' when none is set.
	 *
	 * @return string
	 */
	public function get_author_url(): string {
		return Mixmymeals_Post_Meta::get_author_url( $this->post_id );
	}

	/**
	 * Label for the author link, defaulting to the link's host.
	 *
	 * @return string
	 */
	public function get_author_link_text(): string {
		return Mixmymeals_Post_Meta::get_author_link_text( $this->post_id );
	}

	/**
	 * Whether the author link should carry rel="nofollow".
	 *
	 * @return bool
	 */
	public function get_author_nofollow(): bool {
		return Mixmymeals_Post_Meta::get_author_nofollow( $this->post_id );
	}

	/**
	 * Social/contact addresses of the external author.
	 *
	 * @return string[]
	 */
	public function get_author_social(): array {
		return Mixmymeals_Post_Meta::get_author_social( $this->post_id );
	}

	/**
	 * Attachment ID of the external author's profile picture, 0 when none.
	 *
	 * @return int
	 */
	public function get_author_image_id(): int {
		return Mixmymeals_Post_Meta::get_author_image_id( $this->post_id );
	}

	/**
	 * URL of the external author's profile picture, '' when none.
	 *
	 * @return string
	 */
	public function get_author_image_url(): string {
		return Mixmymeals_Post_Meta::get_author_image_url( $this->post_id );
	}

	/**
	 * All stored author-box fields, raw as saved.
	 *
	 * @return array
	 */
	public function get_author_box(): array {
		return Mixmymeals_Post_Meta::get_author_data( $this->post_id );
	}

	/**
	 * Stores the author box fields.
	 *
	 * @param array $data name, text, url, link_text, nofollow, social.
	 * @return void
	 */
	public function set_author_box( array $data ): void {
		Mixmymeals_Post_Meta::save_author_data( $this->post_id, $data );
	}

	/* ---------------------------------------------------------------------
	 * Structured data
	 * ------------------------------------------------------------------ */

	/**
	 * The schema.org/Recipe structure for this recipe — ingredients,
	 * instructions, nutrition, rating and diet, the shape Google reads for
	 * recipe rich results. Printed by Mixmymeals_Post_Types::output_schema().
	 *
	 * @return array Empty array when the recipe is invalid.
	 */
	public function get_schema(): array {

		if ( ! $this->is_valid() ) {
			return array();
		}

		$recipe_id = $this->post_id;

		$schema = array(
			'@context'      => 'https://schema.org',
			'@type'         => 'Recipe',
			'name'          => get_the_title( $recipe_id ),
			'url'           => get_permalink( $recipe_id ),
			'datePublished' => get_the_date( 'c', $recipe_id ),
			'dateModified'  => get_the_modified_date( 'c', $recipe_id ),
			'author'        => array(
				'@type' => 'Organization',
				'name'  => get_bloginfo( 'name' ),
			),
		);

		$description = Mixmymeals_Post_Types::meta_description( $recipe_id );
		if ( '' !== $description ) {
			$schema['description'] = $description;
		}

		// Image in several ratios (Google prefers 1:1, 4:3 and 16:9 variants).
		$images = Mixmymeals_Post_Types::schema_image_urls( $recipe_id );
		if ( $images ) {
			$schema['image'] = $images;
		}

		$prep = (int) $this->get_preparation_time();
		if ( $prep > 0 ) {
			$schema['prepTime']  = self::iso8601_minutes( $prep );
			$schema['totalTime'] = self::iso8601_minutes( $prep );
		}

		$servings = (float) $this->get_servings();
		if ( $servings > 0 ) {
			$schema['recipeYield'] = (string) ( (int) round( $servings ) );
		}

		$categories = array_map( static fn( $t ) => $t->name, $this->get_categories() );
		if ( $categories ) {
			$schema['recipeCategory'] = implode( ', ', $categories );
		}

		$tags = wp_get_post_terms( $recipe_id, 'mmm_recipe_tag', array( 'fields' => 'names' ) );
		if ( is_array( $tags ) && $tags ) {
			$schema['keywords'] = implode( ', ', $tags );
		}

		/*
		 * Diets → schema.org RestrictedDiet. Every axis, not just the strongest
		 * one: suitableForDiet takes a list, and a dish that is vegan and
		 * gluten-free is both. What is in force here, not what is reachable —
		 * structured data describes the dish as the recipe computes it.
		 */
		$diet_urls = array();

		foreach ( Mixmymeals_Diet::headline( $this->get_diets() ) as $diet ) {
			$url = Mixmymeals_Diet::schema_url( $diet );
			if ( '' !== $url ) {
				$diet_urls[] = $url;
			}
		}

		if ( $diet_urls ) {
			$schema['suitableForDiet'] = 1 === count( $diet_urls ) ? $diet_urls[0] : $diet_urls;
		}

		// Ingredients, aggregated across steps at the recipe's own servings.
		$ingredients = $this->schema_ingredient_lines();
		if ( $ingredients ) {
			$schema['recipeIngredient'] = $ingredients;
		}

		// Instructions as HowToStep list.
		$steps = array();
		foreach ( (array) $this->get_steps() as $step ) {
			$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) ( $step['description'] ?? '' ) ) ) );
			$name = trim( wp_strip_all_tags( (string) ( $step['title'] ?? '' ) ) );
			if ( '' === $text && '' === $name ) {
				continue;
			}
			$how_to = array( '@type' => 'HowToStep' );
			if ( '' !== $name ) {
				$how_to['name'] = $name;
			}
			$how_to['text'] = '' !== $text ? $text : $name;
			$steps[]        = $how_to;
		}
		if ( $steps ) {
			$schema['recipeInstructions'] = $steps;
		}

		// Nutrition per serving.
		$nutrition = $this->calculate_nutrition_per_serving();
		$info      = array( '@type' => 'NutritionInformation' );
		$mapping   = array(
			'calories' => array( 'calories', ' kcal' ),
			'protein'  => array( 'proteinContent', ' g' ),
			'carbs'    => array( 'carbohydrateContent', ' g' ),
			'fat'      => array( 'fatContent', ' g' ),
			'sugar'    => array( 'sugarContent', ' g' ),
			'fiber'    => array( 'fiberContent', ' g' ),
			'salt'     => array( 'sodiumContent', ' g' ),
		);
		foreach ( $mapping as $key => list( $prop, $unit ) ) {
			if ( ! empty( $nutrition[ $key ] ) ) {
				$info[ $prop ] = round( (float) $nutrition[ $key ], 1 ) . $unit;
			}
		}
		if ( count( $info ) > 1 ) {
			$schema['nutrition'] = $info;
		}

		// Aggregate rating from the ratings module.
		$avg   = (float) get_post_meta( $recipe_id, '_mmm_rating_avg', true );
		$count = (int) get_post_meta( $recipe_id, '_mmm_rating_count', true );
		if ( $avg > 0 && $count > 0 ) {
			$schema['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => $avg,
				'ratingCount' => $count,
				'bestRating'  => 5,
				'worstRating' => 1,
			);
		}

		/**
		 * Filters the finished Recipe schema before output.
		 *
		 * @param array $schema    Schema.org Recipe structure.
		 * @param int   $recipe_id Recipe post ID.
		 */
		return apply_filters( 'mixmymeals_recipe_schema', $schema, $recipe_id );
	}

	/**
	 * Ingredient lines ("250 g Mehl") aggregated across the recipe's steps.
	 *
	 * @return string[]
	 */
	private function schema_ingredient_lines(): array {

		$totals = array();
		foreach ( $this->get_ingredients_scaled( 1.0 ) as $ingredient ) {
			$key = $ingredient['id'] . '|' . $ingredient['unit'];
			if ( ! isset( $totals[ $key ] ) ) {
				$totals[ $key ] = array(
					'name'   => trim( wp_strip_all_tags( (string) $ingredient['name'] ) ),
					'unit'   => (string) $ingredient['unit'],
					'amount' => 0.0,
				);
			}
			$totals[ $key ]['amount'] += (float) $ingredient['amount'];
		}

		$lines = array();
		foreach ( $totals as $item ) {
			$lines[] = trim(
				Mixmymeals_Ingredient::format_amount( (float) $item['amount'] )
				. ' ' . $item['unit'] . ' ' . $item['name']
			);
		}

		return $lines;
	}

	/**
	 * Minutes → ISO 8601 duration (25 → PT25M, 90 → PT1H30M).
	 *
	 * @param int $minutes Minutes.
	 * @return string
	 */
	private static function iso8601_minutes( int $minutes ): string {

		$hours = intdiv( $minutes, 60 );
		$rest  = $minutes % 60;
		$out   = 'PT';
		if ( $hours > 0 ) {
			$out .= $hours . 'H';
		}
		if ( $rest > 0 || 0 === $hours ) {
			$out .= $rest . 'M';
		}

		return $out;
	}
}
