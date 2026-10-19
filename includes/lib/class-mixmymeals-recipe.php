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

	/**
	 * How long the dish takes, in minutes. **Absent when nobody has said** —
	 * see set_preparation_time() for why this field, unlike the shelf life,
	 * needs no separate "stated as zero" case.
	 *
	 * @var string
	 */
	const META_PREP_TIME = '_mmm_preparation_time';

	/**
	 * The thresholds the archive offers as "under N minutes", ascending.
	 *
	 * A short, fixed scale rather than free input: these are links with paths
	 * of their own, and a path per arbitrary number is a page per number.
	 *
	 * @var int[]
	 */
	const PREP_TIME_STEPS = array( 20, 30, 60 );

	/**
	 * Per-request nutrition per serving, keyed by recipe ID.
	 *
	 * @var array<int, array<string,float>>
	 */
	private static $per_serving_cache = array();

	/**
	 * Per-request names of the ingredients whose cooking factor changed the
	 * weight, keyed by recipe ID. Filled by the same pass that sums the weight,
	 * so the note under a table cannot name a different set than the one the
	 * figures were computed from.
	 *
	 * @var array<int, array<int, array{name:string, factor:float}>>
	 */
	private static $cooked_cache = array();

	/**
	 * Per-request cost per serving, keyed by recipe ID.
	 *
	 * @var array<int, float>
	 */
	private static $cost_cache = array();

	/**
	 * Per-request nutrition coverage, keyed by recipe ID.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private static $coverage_cache = array();

	/**
	 * The share of a dish's weight that may lack nutrition data before the
	 * figures stop being called complete.
	 *
	 * Two per cent, because the gaps that actually occur are a gram of oregano
	 * and a pinch of baking powder, and calling a recipe's nutrition
	 * questionable over 1 g in 800 would put a warning on nearly every recipe
	 * — which is the same as putting it on none. Anything above this moves
	 * energy or a macro by more than the rounding the figures are printed
	 * with, and then it belongs on screen.
	 *
	 * @var float
	 */
	const NUTRITION_TOLERANCE = 0.02;

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
		add_action( 'init', array( __CLASS__, 'add_archive_view_rules' ), 20 );
		add_filter( 'query_vars', array( __CLASS__, 'add_browse_view_query_var' ) );
		add_filter( 'wp_unique_post_slug', array( __CLASS__, 'reserve_browse_view_slugs' ), 10, 4 );
	}

	/**
	 * The rewrite rules that give every view a path of its own:
	 * /recipes/frequently-cooked/, /recipes/vegan/, /recipes/under-30-minutes/
	 * and each of their page/2/.
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
	public static function add_archive_view_rules(): void {

		$base = self::archive_rewrite_base();

		if ( '' === $base ) {
			return;
		}

		$slugs = self::archive_view_slugs();

		foreach ( $slugs as $slug => $entry ) {

			$target = 'index.php?post_type=mmm_recipe&'
				. self::facet_query_var( (string) $entry[0] ) . '=' . rawurlencode( (string) $entry[1] );

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
		$vars[] = self::DIET_VIEW_VAR;
		$vars[] = self::TIME_VIEW_VAR;

		return $vars;
	}

	/**
	 * Keeps recipes out of the paths the archive's own views occupy.
	 *
	 * Without this a recipe called "Frequently cooked" would take the slug
	 * haeufig-gekocht, and its page would be unreachable ever after — the view
	 * rule sits above the post type's own and answers first. Renaming the
	 * recipe on the way in is the lesser surprise: it happens while somebody is
	 * looking at the editor, rather than silently months later.
	 *
	 * Every slug in the registry is reserved: the browse views, the diets, the
	 * time steps, and for each of them both the internal key and the translated
	 * word, because both resolve to a view.
	 *
	 * Note what this cannot do: a recipe that already holds one of these slugs
	 * keeps it and becomes unreachable the moment the rule appears. New paths
	 * therefore need a look at the existing slugs before they go live.
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

		$taken = array_keys( self::archive_view_slugs() );

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
	 * Query variable carrying a diet facet, filled by the rewrite rule.
	 *
	 * Deliberately not `mmm_diet`: that is the taxonomy's name. One word for
	 * two things is the sort of trap somebody walks into a year later.
	 *
	 * @var string
	 */
	const DIET_VIEW_VAR = 'mmm_diet_view';

	/**
	 * Query variable carrying a time facet.
	 *
	 * @var string
	 */
	const TIME_VIEW_VAR = 'mmm_time_view';

	/**
	 * Every path the recipe archive answers under, as slug => [kind, value].
	 *
	 * Three families share one namespace — the browse views that were here
	 * first, the diets and the time steps — so they share one registry, one
	 * reservation against recipe slugs, one fingerprint and one flush. Three
	 * parallel slug managers under /recipes/ would be the actual bug.
	 *
	 * Both vocabularies are registered for each entry: the internal key
	 * (`gluten_free`) as well as the translated slug (`glutenfrei`), so a
	 * shared link survives a change of wording. Where two families want the
	 * same slug the first one keeps it, which makes the outcome depend on the
	 * order here and not on the order a hash happens to have.
	 *
	 * @return array<string, array{0:string,1:mixed}>
	 */
	public static function archive_view_slugs(): array {

		$map = array();

		$claim = static function ( $slug, $kind, $value ) use ( &$map ) {

			$slug = sanitize_title( (string) $slug );

			if ( '' !== $slug && ! isset( $map[ $slug ] ) ) {
				$map[ $slug ] = array( $kind, $value );
			}
		};

		// Browse views first: they are the oldest addresses on this archive.
		foreach ( self::browse_view_slugs() as $view => $slug ) {
			$claim( $view, 'view', $view );
			$claim( $slug, 'view', $view );
		}

		foreach ( array_keys( Mixmymeals_Diet::types() ) as $diet ) {
			$claim( $diet, 'diet', $diet );
			$claim( Mixmymeals_Diet::label( $diet ), 'diet', $diet );
		}

		// The keys of the former built-ins, so /rezepte/vegetarian/ keeps
		// answering after the term's slug became "vegetarisch".
		foreach ( Mixmymeals_Diet::former_terms() as $key => $diet ) {
			if ( Mixmymeals_Diet::is_known( $diet ) ) {
				$claim( $key, 'diet', $diet );
			}
		}

		foreach ( self::prep_time_steps() as $minutes ) {

			// The untranslated literal first, as the stable key: it is what the
			// diets get from their internal slug, and without it a translated
			// time path would break every link shared before the translation.
			// In an untranslated install the two are the same word and the
			// second claim simply finds the slug taken.
			$claim( sprintf( 'under-%d-minutes', $minutes ), 'time', $minutes );
			$claim( self::time_view_slug( $minutes ), 'time', $minutes );
		}

		/**
		 * Filters the paths the recipe archive answers under.
		 *
		 * @param array<string, array{0:string,1:mixed}> $map Slug => [kind, value].
		 */
		return (array) apply_filters( 'mixmymeals_archive_view_slugs', $map );
	}

	/**
	 * The translatable slug of one time step.
	 *
	 * Literal _x() calls per step rather than a built string, for the reason
	 * translated_view_slug() already gives: a slug assembled at runtime never
	 * reaches the .pot file. A step a filter added falls back to a built slug,
	 * which is untranslated but reachable — better than no path at all.
	 *
	 * @param int $minutes The step.
	 * @return string
	 */
	private static function time_view_slug( int $minutes ): string {

		switch ( $minutes ) {

			case 20:
				return _x( 'under-20-minutes', 'archive view URL slug', 'mixmymeals' );

			case 30:
				return _x( 'under-30-minutes', 'archive view URL slug', 'mixmymeals' );

			case 60:
				return _x( 'under-60-minutes', 'archive view URL slug', 'mixmymeals' );

			default:
				return sprintf(
					/* translators: %d: number of minutes. Slug for an archive path, lowercase and hyphenated. */
					_x( 'under-%d-minutes', 'archive view URL slug', 'mixmymeals' ),
					$minutes
				);
		}
	}

	/**
	 * What a URL slug names, or an empty array when it names nothing.
	 *
	 * @param string $slug Raw slug from the URL.
	 * @return array{kind:string, value:mixed}|array{}
	 */
	public static function resolve_archive_view( string $slug ): array {

		$slug = sanitize_title( $slug );

		if ( '' === $slug ) {
			return array();
		}

		$entry = self::archive_view_slugs()[ $slug ] ?? null;

		if ( null === $entry ) {
			return array();
		}

		return array(
			'kind'  => (string) $entry[0],
			'value' => 'time' === $entry[0] ? (int) $entry[1] : (string) $entry[1],
		);
	}

	/**
	 * The archive URL for one facet, with any other filters as parameters.
	 *
	 * @param string               $kind   'view', 'diet' or 'time'.
	 * @param string|int           $value  The facet's value.
	 * @param array<string,string> $params Further filters to carry along.
	 * @return string '' when there is no such facet or no archive.
	 */
	public static function archive_view_url( string $kind, $value, array $params = array() ): string {

		$base = get_post_type_archive_link( 'mmm_recipe' );

		if ( ! $base ) {
			return '';
		}

		/*
		 * add_query_arg() urlencodes the arguments a URL already carries and
		 * passes new ones through untouched, so a value with a space or a comma
		 * has to arrive encoded. esc_url() papers over the space at print time,
		 * which is exactly why an unencoded URL survives a visual check and
		 * breaks wherever it is used raw.
		 */
		$params = array_map( 'rawurlencode', array_map( 'strval', $params ) );

		$slug = '';

		foreach ( self::archive_view_slugs() as $candidate => $entry ) {
			if ( $entry[0] === $kind && (string) $entry[1] === (string) $value ) {
				$slug = $candidate;
				// The last match is the translated one, which is the vocabulary
				// a link should be built in; the internal key stays as a way in.
			}
		}

		if ( '' === $slug ) {
			return $params ? add_query_arg( $params, $base ) : $base;
		}

		/*
		 * With pretty permalinks the rewrite rule answers this path; without
		 * them there is no rule and the parameter is all there is. Both arrive
		 * at the same query.
		 */
		if ( get_option( 'permalink_structure' ) ) {
			$url = user_trailingslashit( trailingslashit( $base ) . $slug );

			return $params ? add_query_arg( $params, $url ) : $url;
		}

		$var = self::facet_query_var( $kind );

		return add_query_arg( array_merge( array( $var => (string) $value ), $params ), $base );
	}

	/**
	 * The query variable a facet arrives in.
	 *
	 * @param string $kind 'view', 'diet' or 'time'.
	 * @return string
	 */
	public static function facet_query_var( string $kind ): string {

		switch ( $kind ) {

			case 'diet':
				return self::DIET_VIEW_VAR;

			case 'time':
				return self::TIME_VIEW_VAR;

			default:
				return self::BROWSE_VIEW_VAR;
		}
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

		/*
		 * Both figures are derived from the steps, and both are held for the
		 * request. The REST API updates a recipe and serialises it in the same
		 * call, so without this it answered with the nutrition from before the
		 * write — found by the test suite, not in production.
		 */
		unset(
			self::$per_serving_cache[ $this->post_id ],
			self::$cooked_cache[ $this->post_id ],
			self::$cost_cache[ $this->post_id ],
			self::$coverage_cache[ $this->post_id ]
		);

		if ( ! $this->is_valid() ) {
			return false;
		}

		$cleaned = array();

		foreach ( $steps as $step ) {

			// HTML zentral säubern, damit REST und Admin denselben
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

			$prep = self::normalise_step_prep( $step['prep_amount'] ?? 0, $step['prep_unit'] ?? '' );

			$new_step = array(
				'title'       => $title,
				'description' => $desc,
				'image'       => $image,
				'prep_amount' => $prep ? $prep['amount'] : 0,
				'prep_unit'   => $prep ? $prep['unit'] : '',
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

					/*
					 * Pakete, die für dieses Gericht nicht in Frage kommen.
					 *
					 * Geprüft wie der Pin daneben: nur Produkte dieser Zutat
					 * — ein Ausschluss, der auf fremde Ware zeigt, würde
					 * stillschweigend nichts tun und später als Fehler
					 * auftauchen.
					 */
					$blocked = array();

					foreach ( (array) ( $ing['excluded_products'] ?? array() ) as $blocked_id ) {

						$blocked_id = intval( $blocked_id );

						if ( $blocked_id <= 0 || (int) wp_get_post_parent_id( $blocked_id ) !== $ing_id ) {
							continue;
						}

						/*
						 * Das Paket, mit dem das Gericht gekocht wird, kann
						 * nicht zugleich ausgeschlossen sein. Der Pin ist die
						 * ausdrücklichere Aussage und gewinnt; der Ausschluss
						 * würde sonst die Diät-Aussage um genau das Paket
						 * kürzen, das im Topf ist.
						 */
						if ( $product > 0 && $blocked_id === $product ) {
							continue;
						}

						$blocked[] = $blocked_id;
					}

					$new_step['ingredients'][] = array(
						'id'                => $ing_id,
						'amount'            => $amount,
						'amount_id'         => $amount_id,
						'optional'          => ! empty( $ing['optional'] ),
						'product_id'        => $product,
						'excluded_products' => array_values( array_unique( $blocked ) ),
					);
				}
			}

			$cleaned[] = $new_step;
		}

		update_post_meta( $this->post_id, '_rmm_steps', self::sort_steps( $cleaned ) );

		return true;
	}

	/**
	 * Units a preparation step may be timed in: "X days before", "X hours
	 * before" the meal.
	 */
	const PREP_UNITS = array( 'days', 'hours' );

	/**
	 * Where a step's timing is not stated, a meal is assumed to be eaten at
	 * these hours. Only "hours before" needs it: twelve hours before dinner
	 * is the same morning, twelve hours before breakfast the evening before.
	 * Filter `mixmymeals_meal_type_hours`.
	 *
	 * @return array<string, int> Meal type => hour of the day (0-23).
	 */
	public static function meal_type_hours(): array {

		$hours = (array) apply_filters(
			'mixmymeals_meal_type_hours',
			array(
				'breakfast' => 8,
				'lunch'     => 12,
				'snack'     => 15,
				'dinner'    => 18,
				'other'     => 12,
			)
		);

		return array_map( static fn( $h ) => max( 0, min( 23, (int) $h ) ), $hours );
	}

	/**
	 * The one rule for what makes a step preparation: a whole number of at
	 * least one and a known unit. Anything else is an ordinary step — "0 hours
	 * before" is the cooking itself.
	 *
	 * @param mixed $amount Posted or stored amount.
	 * @param mixed $unit   'days' or 'hours'.
	 * @return array{amount:int, unit:string, hours:int}|null
	 */
	public static function normalise_step_prep( $amount, $unit ): ?array {

		$unit   = is_string( $unit ) ? $unit : '';
		$amount = is_numeric( $amount ) ? (int) $amount : 0;

		if ( $amount < 1 || ! in_array( $unit, self::PREP_UNITS, true ) ) {
			return null;
		}

		// A year is the limit of anything worth planning ahead.
		$amount = min( $amount, 'days' === $unit ? 365 : 24 * 365 );

		return array(
			'amount' => $amount,
			'unit'   => $unit,
			'hours'  => 'days' === $unit ? $amount * 24 : $amount,
		);
	}

	/**
	 * A stored step's preparation timing, or null for an ordinary step.
	 *
	 * @param array $step One entry of _rmm_steps.
	 * @return array{amount:int, unit:string, hours:int}|null
	 */
	public static function step_prep( array $step ): ?array {
		return self::normalise_step_prep( $step['prep_amount'] ?? 0, $step['prep_unit'] ?? '' );
	}

	/**
	 * Preparation first, in the order it has to be done — the longest lead
	 * time first — then the ordinary steps in their own order. Stable, so
	 * two steps with the same lead time keep the order the author gave them.
	 *
	 * @param array $steps Steps as stored.
	 * @return array Reindexed.
	 */
	public static function sort_steps( array $steps ): array {

		$prep  = array();
		$other = array();

		foreach ( array_values( $steps ) as $i => $step ) {

			$timing = is_array( $step ) ? self::step_prep( $step ) : null;

			if ( $timing ) {
				$prep[] = array( $timing['hours'], $i, $step );
			} else {
				$other[] = $step;
			}
		}

		usort( $prep, static fn( $a, $b ) => array( $b[0], $a[1] ) <=> array( $a[0], $b[1] ) );

		return array_merge( array_column( $prep, 2 ), $other );
	}

	/**
	 * "2 days before", "1 hour before" — the label a preparation step
	 * carries wherever it is shown.
	 *
	 * @param array $prep From step_prep().
	 * @return string
	 */
	public static function prep_label( array $prep ): string {

		$amount = (int) $prep['amount'];

		if ( 'days' === $prep['unit'] ) {
			/* translators: %s: number of days a preparation step is done before the meal. */
			return sprintf( _n( '%s day before', '%s days before', $amount, 'mixmymeals' ), number_format_i18n( $amount ) );
		}

		/* translators: %s: number of hours a preparation step is done before the meal. */
		return sprintf( _n( '%s hour before', '%s hours before', $amount, 'mixmymeals' ), number_format_i18n( $amount ) );
	}

	/**
	 * The recipe's preparation steps, each with its position in get_steps()
	 * — which is also the anchor (`#mmm-step-<n>`, counted from 1) the step
	 * carries on the recipe and the meal page.
	 *
	 * @return array<int, array{index:int, title:string, prep:array}>
	 */
	public function get_prep_steps(): array {

		$out = array();

		foreach ( $this->get_steps() as $index => $step ) {

			$prep = self::step_prep( (array) $step );

			if ( ! $prep ) {
				continue;
			}

			$out[] = array(
				'index' => (int) $index,
				'title' => (string) ( $step['title'] ?? '' ),
				'prep'  => $prep,
			);
		}

		return $out;
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

		/*
		 * Held for the request. A plan asks this once per meal per day — seven
		 * times for the same recipe on a weekly plan — and every archive card
		 * asks again. set_steps() clears it, because a write during the same
		 * request must not read back the figures from before it.
		 */
		if ( isset( self::$per_serving_cache[ $this->post_id ] ) ) {
			return self::$per_serving_cache[ $this->post_id ];
		}

		if ( ! $this->is_valid() ) {
			self::$cooked_cache[ $this->post_id ] = array();
			return self::$per_serving_cache[ $this->post_id ] = [];
		}

		[ $totals, $total_weight, $cooked ] = $this->_calculate_total_nutrition( 1.0 );

		self::$cooked_cache[ $this->post_id ] = $cooked;

		$servings = max(1.0, floatval($this->get_servings()));

		foreach ($totals as $key => $val) {
			$totals[$key] = $val / $servings;
		}

		$totals['weight_per_serving'] = $total_weight / $servings;

		return self::$per_serving_cache[ $this->post_id ] = $totals;
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
					'grams'    => 0.0,
					'meals'    => 0,
					'products' => array(),
					// Grams whose row left the choice open. Nothing open means
					// the recipe has already decided and the comparison has
					// nothing left to ask — see impact_groups().
					'open'     => 0.0,
				);
			}

			Mixmymeals_Product::add_row_weight(
				$weights[ $id ],
				$id,
				(int) ( $ing['product_id'] ?? 0 ),
				$weight / $servings,
				'grams',
				array_map( 'intval', (array) ( $ing['excluded_products'] ?? array() ) )
			);
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

		// From the plan's key list, not a copy of it: these totals are what
		// calc_nutrition() adds up per day, and a key in one and not the other
		// would be a figure that exists on a recipe and vanishes on a plan.
		$totals = array_fill_keys( Mixmymeals_Meal_Plan::NUTRITION_KEYS, 0.0 );

		$total_weight = 0.0;
		$cooked       = array();

		foreach ($ingredients as $ing) {

			$ingredient_obj = new Mixmymeals_Ingredient($ing['id']);
			if (!$ingredient_obj->is_valid()) continue;

			/*
			 * Through the product this row names, where it names one: the
			 * recipe says which packet goes into this dish, and its figures
			 * are the ones the dish actually has. Nothing pinned resolves to
			 * the ingredient's reference exactly as before.
			 */
			$pinned    = (int) ( $ing['product_id'] ?? 0 );
			$nutrition = $ingredient_obj->get_nutrition_pinned( $pinned );
			$weight = floatval($ing['weight_g']);

			if ($weight <= 0) continue;

			/*
			 * The nutrients come from the raw weight, the dish's weight from the
			 * cooked one: water taken up or boiled off carries no nutrients, but
			 * it is on the plate, and everything per 100 g divides by it.
			 */
			$cooked_weight = $ingredient_obj->cooked_weight( $weight, $pinned );
			$total_weight += $cooked_weight;

			// Only a factor that moved the weight: a stated 1.0 corrects nothing.
			if ( abs( $cooked_weight - $weight ) > 0.0001 ) {
				$mmm_factor = (float) $ingredient_obj->get_cooking_factor_pinned( $pinned );

				// By ingredient and factor: one ingredient pinned to two
				// packets in two steps can carry two factors, and each is named.
				$cooked[ (int) $ing['id'] . '|' . $mmm_factor ] = array(
					'name'   => get_the_title( (int) $ing['id'] ),
					'factor' => $mmm_factor,
				);
			}

			// Zweiter Gürtel: get_nutrition() liefert bereits float|null, aber
			// diese Rechnung darf unter keinen Umständen fatal werden — sie
			// hängt an der Rezeptliste im Admin und an jeder Rezeptseite.
			foreach ( Mixmymeals_Ingredient::NUTRITION_KEY_MAP as $sum_key => $own_key ) {
				$totals[ $sum_key ] += (float) ( $nutrition[ $own_key ] ?? 0 ) * $weight / 100;
			}
		}

		return [ $totals, $total_weight, array_values( $cooked ) ];
	}

	/**
	 * The ingredients whose cooking factor changed this recipe's weight, with
	 * the factor that did it, in recipe order; empty when the weight is the
	 * raw sum.
	 *
	 * @return array<int, array{name:string, factor:float}>
	 */
	public function get_cooked_ingredients(): array {

		if ( ! isset( self::$cooked_cache[ $this->post_id ] ) ) {
			$this->calculate_nutrition_per_serving();
		}

		return self::$cooked_cache[ $this->post_id ] ?? array();
	}

	/**
	 * Whether a row of this dish states 0 g saturates beside more than 3 g
	 * fat — through the row's product, as the totals read it. See
	 * Mixmymeals_Ingredient::SATURATES_GAP_FAT.
	 *
	 * A gap that nutrition_coverage() cannot see, because it treats only an
	 * absent figure as one; there a stored 0 is an answer, and must stay one
	 * for /audit and the recipe list. The traffic light and the day rating
	 * are stricter, because a zero that is really a blank is exactly what
	 * would turn them green.
	 *
	 * @return bool
	 */
	public function has_saturates_gap(): bool {

		if ( ! $this->is_valid() ) {
			return false;
		}

		foreach ( $this->get_ingredients_scaled( 1.0 ) as $ing ) {

			if ( (float) ( $ing['weight_g'] ?? 0 ) <= 0 ) {
				continue;
			}

			$ingredient = new Mixmymeals_Ingredient( (int) $ing['id'] );

			if (
				$ingredient->is_valid()
				&& Mixmymeals_Ingredient::saturates_zero_is_gap(
					$ingredient->get_nutrition_pinned( (int) ( $ing['product_id'] ?? 0 ) )
				)
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The figures (sum keys) that some of these recipes cannot be trusted
	 * for: short of data beyond NUTRITION_TOLERANCE, or — for saturates — a
	 * stored 0 that is really a blank (has_saturates_gap()).
	 *
	 * The one answer to "may this sum be judged", asked by the traffic light
	 * and by the plan's "too little": a sum that is low because data is
	 * missing must get neither a green light nor a shortfall.
	 *
	 * @param int[] $recipe_ids Recipe IDs; duplicates and non-recipes are fine.
	 * @return string[] Keys of Mixmymeals_Meal_Plan::NUTRITION_KEYS.
	 */
	public static function unreliable_nutrition_keys( array $recipe_ids ): array {

		$out = array();

		foreach ( array_unique( array_filter( array_map( 'intval', $recipe_ids ) ) ) as $recipe_id ) {

			$recipe = new self( $recipe_id );

			if ( ! $recipe->is_valid() ) {
				continue;
			}

			$coverage = $recipe->nutrition_coverage();

			foreach ( Mixmymeals_Ingredient::NUTRITION_KEY_MAP as $sum_key => $own_key ) {
				if ( empty( $coverage['keys'][ $own_key ]['complete'] ) ) {
					$out[ $sum_key ] = true;
				}
			}

			if ( ! isset( $out['saturates'] ) && $recipe->has_saturates_gap() ) {
				$out['saturates'] = true;
			}
		}

		return array_keys( $out );
	}

	/**
	 * How much of this dish the nutrition figures actually describe.
	 *
	 * _calculate_total_nutrition() reads a missing value as 0 — it has to, the
	 * recipe list and every recipe page hang on that sum not going fatal — so
	 * an ingredient nobody has filled in lowers the total silently and the
	 * result looks exactly like a genuinely light dish. This says how much of
	 * the weight the sum is built from, over the same ingredient rows and in
	 * the same order, so the share always describes the figure printed beside
	 * it rather than a second, differently assembled one.
	 *
	 * Optional ingredients count. They are in the sum (get_ingredients_scaled()
	 * returns them and the totals add them), and a coverage figure that
	 * measured a different set than the total it qualifies would be worse than
	 * none.
	 *
	 * **Per figure, not per ingredient.** An ingredient that states energy and
	 * fat and leaves fibre empty is documented enough to be published — see
	 * Mixmymeals_Ingredient::has_nutrition() — and its blank fibre still goes
	 * into this recipe's fibre total as 0. Judging coverage by the ingredient
	 * would print a tick over exactly that, so each of the seven figures is
	 * counted on its own and `share` reports the worst of them: the one number
	 * that cannot promise more than the table delivers.
	 *
	 * `complete` is not `share === 1.0`: see NUTRITION_TOLERANCE.
	 *
	 * @return array{
	 *     weight:float,
	 *     share:float,
	 *     complete:bool,
	 *     worst:string,
	 *     keys:array<string, array{covered:float, share:float, complete:bool}>,
	 *     missing:array<int, array{id:int, name:string, weight:float, keys:string[]}>
	 * } `share` is 1.0 and `worst` empty for a recipe with no weight at all —
	 *   nothing is undocumented there, there is simply nothing to document,
	 *   and a recipe without ingredients must not be reported as a data gap.
	 *   `missing` lists every ingredient short of at least one figure, with
	 *   the keys it is short of.
	 */
	public function nutrition_coverage(): array {

		$keys = Mixmymeals_Ingredient::NUTRITION_FIGURE_KEYS;

		if ( ! $this->is_valid() ) {
			return array(
				'weight'   => 0.0,
				'share'    => 1.0,
				'complete' => true,
				'worst'    => '',
				'keys'     => array_fill_keys(
					$keys,
					array(
						'covered'  => 0.0,
						'share'    => 1.0,
						'complete' => true,
					)
				),
				'missing'  => array(),
			);
		}

		if ( isset( self::$coverage_cache[ $this->post_id ] ) ) {
			return self::$coverage_cache[ $this->post_id ];
		}

		$weight  = 0.0;
		$covered = array_fill_keys( $keys, 0.0 );
		$missing = array();

		foreach ( $this->get_ingredients_scaled( 1.0 ) as $ing ) {

			$row_weight = (float) $ing['weight_g'];

			if ( $row_weight <= 0 ) {
				continue;
			}

			$ingredient = new Mixmymeals_Ingredient( (int) $ing['id'] );

			if ( ! $ingredient->is_valid() ) {
				continue;
			}

			$weight += $row_weight;

			// Through the row's product, exactly as the totals are: a row
			// naming a packet that states a figure has that figure, whatever
			// the ingredient behind it does or does not store.
			$unstated = $ingredient->unstated_nutrition_keys( (int) ( $ing['product_id'] ?? 0 ) );

			foreach ( $keys as $key ) {
				if ( ! in_array( $key, $unstated, true ) ) {
					$covered[ $key ] += $row_weight;
				}
			}

			if ( ! $unstated ) {
				continue;
			}

			/*
			 * Keyed by ingredient, not appended per row: an onion used in
			 * three steps is one gap to fill, and a list naming it three times
			 * reads as three different problems. The keys are merged rather
			 * than overwritten, because two rows of the same ingredient may
			 * name different products and so be short of different figures.
			 */
			$id = (int) $ing['id'];

			if ( isset( $missing[ $id ] ) ) {
				$missing[ $id ]['weight'] += $row_weight;
				$missing[ $id ]['keys']    = array_values(
					array_intersect( $keys, array_unique( array_merge( $missing[ $id ]['keys'], $unstated ) ) )
				);
				continue;
			}

			$missing[ $id ] = array(
				'id'     => $id,
				'name'   => (string) $ing['name'],
				'weight' => $row_weight,
				'keys'   => $unstated,
			);
		}

		$per_key = array();
		$worst   = '';

		foreach ( $keys as $key ) {

			$key_share = $weight > 0 ? $covered[ $key ] / $weight : 1.0;

			$per_key[ $key ] = array(
				'covered'  => $covered[ $key ],
				'share'    => $key_share,
				'complete' => ( 1.0 - $key_share ) <= self::NUTRITION_TOLERANCE,
			);

			/*
			 * The worst figure, and on a tie the one a food label lists first
			 * — energy before fat before fibre. An arbitrary pick would make
			 * the column's wording jump between two equally bad rows on every
			 * reload, which reads as a changing recipe.
			 */
			if ( '' === $worst || $key_share < $per_key[ $worst ]['share'] ) {
				$worst = $key;
			}
		}

		$complete = true;

		foreach ( $per_key as $figures ) {
			$complete = $complete && $figures['complete'];
		}

		return self::$coverage_cache[ $this->post_id ] = array(
			'weight'   => $weight,
			'share'    => '' === $worst ? 1.0 : $per_key[ $worst ]['share'],
			'complete' => $complete,
			'worst'    => $complete ? '' : $worst,
			'keys'     => $per_key,
			'missing'  => array_values( $missing ),
		);
	}

	/** @return array<int, array<string, mixed>> */
	public function get_steps(): array {

		if ( ! $this->is_valid() ) {
			return [];
		}

		$steps = get_post_meta( $this->post_id, '_rmm_steps', true );

		/*
		 * Sorted on read as well as on write: the core meta route can store
		 * steps without passing set_steps(), and preparation standing after
		 * the cooking would be read too late to be of any use.
		 */
		return is_array( $steps ) ? self::sort_steps( $steps ) : array();
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

		/*
		 * Held for the request, like the nutrition beside it, and cleared by
		 * set_steps() for the same reason. An archive card walks the whole
		 * ingredient list four times — nutrition, cost and the two diet
		 * answers — and the cost pass repeats what the nutrition pass just did.
		 */
		if ( isset( self::$cost_cache[ $this->post_id ] ) ) {
			return self::$cost_cache[ $this->post_id ];
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

			$total_cost += $ingredient_obj->calculate_cost_for_weight(
				$ing['weight_g'],
				(int) ( $ing['product_id'] ?? 0 )
			);
		}

		return self::$cost_cache[ $this->post_id ] = $total_cost;
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

		/*
		 * Every row below instantiates its ingredient and its unit, and each of
		 * those reads a post row and its meta. One pass over the steps first,
		 * so the whole recipe costs two queries instead of two per row — which
		 * on an archive of ten cards was the difference between tens and
		 * hundreds.
		 */
		$prime = array();

		foreach ( $steps as $step ) {
			foreach ( ( $step['ingredients'] ?? array() ) as $ing ) {

				$prime[] = intval( $ing['id'] ?? 0 );
				$prime[] = intval( $ing['amount_id'] ?? 0 );
				$prime[] = intval( $ing['product_id'] ?? 0 );
			}
		}

		$prime = array_values( array_unique( array_filter( $prime ) ) );

		if ( $prime && function_exists( '_prime_post_caches' ) ) {
			_prime_post_caches( $prime, false, true );
		}

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
					'id'                => $id,
					'name'              => get_the_title( $id ), // bewusst WP-Funktion
					'amount'            => round( $amount, 1 ),
					'unit'              => $unit_title,
					'weight_g'          => round( $total_weight ),
					'optional'          => ! empty( $ing['optional'] ),
					'product_id'        => $product_id,
					'excluded_products' => array_map( 'intval', (array) ( $ing['excluded_products'] ?? array() ) ),
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
					'excluded_products' => array_map( 'intval', (array) ( $ing['excluded_products'] ?? array() ) ),
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

		$before = $this->get_equipment();
		$clean  = array_values(
			array_unique( array_filter( array_map( 'intval', $ids ), fn( $id ) => $id > 0 ) )
		);

		update_post_meta( $this->post_id, '_mmm_equipment', $clean );

		/*
		 * Both sides of the change: a piece dropped here loses a recipe just
		 * as surely as one added gains one, and each caches its own list.
		 */
		Mixmymeals_Equipment::flush_recipe_cache( array_merge( $before, $clean ) );

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
	ARCHIVE INDEX
	----------------------------------------------------------
	*/

	/**
	 * The diets this recipe can be cooked for, one meta row per slug.
	 *
	 * **This index answers queries. It never states anything.** The chips, the
	 * schema and the REST output all keep computing through get_diets() and
	 * get_diet_suitabilities(), because a diet is a claim people act on and a
	 * stale row must not be able to put "vegan" on a page. What a stale row can
	 * do is leave a recipe out of a filtered list — a visible, harmless kind of
	 * wrong, and the direction this whole mechanism is built to fail in.
	 *
	 * Meta rather than terms on purpose: an mmm_diet term means "somebody said
	 * so" everywhere else in this plugin, and Mixmymeals_Diet::for_post() would
	 * start answering for recipes the moment the taxonomy knew them. Meta
	 * cannot be mistaken for a statement, has no admin column, no metabox and
	 * no REST route anybody could set by hand.
	 *
	 * Absent when the recipe is classified nowhere — the META_COOK_COUNT
	 * convention, so "no diet at all" stays a cheap NOT EXISTS.
	 *
	 * @var string
	 */
	const META_DIET_INDEX = '_mmm_diet_index';

	/**
	 * The ingredients of this recipe, one meta row per ingredient ID.
	 *
	 * Exists so that "which recipes use this ingredient" is an indexed query
	 * instead of an unanchored LIKE over serialized step meta. It also carries
	 * this index's own invalidation: when an ingredient's diet changes, this is
	 * how the recipes that have to be recomputed are found.
	 *
	 * @var string
	 */
	const META_INGREDIENT_INDEX = '_mmm_ingredient_index';

	/**
	 * Gate for the one-off backfill; bump to rebuild every index.
	 *
	 * @var string
	 */
	const INDEX_BACKFILL_OPTION = 'mixmymeals_recipe_index_version';

	/**
	 * The version the gate compares against.
	 *
	 * @var string
	 */
	const INDEX_BACKFILL_VERSION = '1';

	/**
	 * Recipes queued for reindexing at the end of the request.
	 *
	 * @var array<int, bool>
	 */
	private static $index_dirty = array();

	/**
	 * Whether the shutdown flush is already attached.
	 *
	 * @var bool
	 */
	private static $index_flush_hooked = false;

	/**
	 * Guards against re-entering the flush from the writes it performs.
	 *
	 * @var bool
	 */
	private static $index_flushing = false;

	/**
	 * How many recipes one request will reindex before handing the rest to
	 * cron.
	 *
	 * Saving an ingredient that appears in a hundred dishes queues a hundred
	 * recipes, and each of them walks its whole ingredient list through
	 * products and terms. Inline that is a save that appears to hang. The
	 * counter has the same batching problem and answers it with `shutdown`;
	 * here `shutdown` is not far enough.
	 *
	 * @var int
	 */
	const INDEX_FLUSH_MAX = 40;

	/**
	 * The event the overflow is handed to.
	 *
	 * @var string
	 */
	const INDEX_CRON_HOOK = 'mixmymeals_reindex_recipes';

	/**
	 * Rebuilds this recipe's index rows from the recipe itself.
	 *
	 * Recomputed, never patched — the same rule recount_cook_count() keeps, and
	 * for the same reason: a value that is only ever derived cannot drift if
	 * nothing is allowed to nudge it.
	 *
	 * Writes only on difference, so an unchanged recipe costs a read and
	 * nothing else, and the meta cache of every other recipe stays warm.
	 *
	 * @return bool Whether anything changed.
	 */
	public function recount_indexes(): bool {

		if ( ! $this->is_valid() ) {
			return false;
		}

		$ingredients = array();

		foreach ( $this->get_ingredients_normalized() as $row ) {
			$id = (int) ( $row['id'] ?? 0 );

			if ( $id > 0 ) {
				$ingredients[ $id ] = $id;
			}
		}

		$changed = self::sync_index_rows( $this->post_id, self::META_INGREDIENT_INDEX, array_values( $ingredients ) );

		// The capable set, not the one in force: the filter answers "what can I
		// cook", and the chip beside it keeps the distinction between "Vegan"
		// and "Vegan-capable".
		$changed = self::sync_index_rows(
			$this->post_id,
			self::META_DIET_INDEX,
			Mixmymeals_Diet::expand( $this->get_diet_suitabilities() )
		) || $changed;

		/*
		 * A zero left behind by the core meta route, which sanitises with
		 * absint() and never sees set_preparation_time(). Cleaning it here is
		 * what keeps such a recipe out of the fastest time bucket — one write
		 * late, but without a filter on update_post_metadata.
		 */
		$stored = get_post_meta( $this->post_id, self::META_PREP_TIME, true );

		if ( '' !== $stored && (int) $stored <= 0 ) {
			delete_post_meta( $this->post_id, self::META_PREP_TIME );
			$changed = true;
		}

		return $changed;
	}

	/**
	 * Brings one repeated meta key in line with a list of values.
	 *
	 * @param int                    $post_id Post to write to.
	 * @param string                 $key     Meta key.
	 * @param array<int, string|int> $values  What it should hold; empty deletes the key.
	 * @return bool Whether anything changed.
	 */
	private static function sync_index_rows( int $post_id, string $key, array $values ): bool {

		$want = array_values( array_unique( array_map( 'strval', $values ) ) );
		$have = array_map( 'strval', (array) get_post_meta( $post_id, $key, false ) );

		sort( $want );
		sort( $have );

		if ( $want === $have ) {
			return false;
		}

		delete_post_meta( $post_id, $key );

		foreach ( $want as $value ) {
			add_post_meta( $post_id, $key, $value );
		}

		return true;
	}

	/**
	 * Rebuilds every recipe's index. Backfill and repair in one.
	 *
	 * Warms the whole library once before walking it: the diets of one recipe
	 * reach through its ingredients into their products and those products'
	 * terms, and priming per recipe would ask for the same ingredient again for
	 * every dish it appears in.
	 *
	 * @return int How many recipes changed.
	 */
	public static function recount_all_indexes(): int {

		$recipe_ids = get_posts(
			array(
				'post_type'      => 'mmm_recipe',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		if ( empty( $recipe_ids ) ) {
			return 0;
		}

		$recipe_ids = array_map( 'intval', (array) $recipe_ids );

		update_meta_cache( 'post', $recipe_ids );

		// Everything the diet answer reaches through, primed in two queries
		// rather than two per recipe.
		$ingredient_ids = array();

		foreach ( $recipe_ids as $recipe_id ) {

			$steps = get_post_meta( $recipe_id, '_rmm_steps', true );

			if ( ! is_array( $steps ) ) {
				continue;
			}

			foreach ( $steps as $step ) {
				foreach ( (array) ( $step['ingredients'] ?? array() ) as $entry ) {
					$id = (int) ( $entry['id'] ?? 0 );

					if ( $id > 0 ) {
						$ingredient_ids[ $id ] = $id;
					}
				}
			}
		}

		if ( $ingredient_ids ) {
			Mixmymeals_Product::prime_for_ingredients( array_values( $ingredient_ids ) );
			Mixmymeals_Diet::prime( array_values( $ingredient_ids ) );
		}

		$changed = 0;

		foreach ( $recipe_ids as $recipe_id ) {
			if ( ( new self( $recipe_id ) )->recount_indexes() ) {
				++$changed;
			}
		}

		delete_transient( 'mmm_archive_diet_counts' );
		delete_transient( 'mmm_archive_time_counts' );

		return $changed;
	}

	/**
	 * Builds the index once, on the first admin request after a version bump.
	 *
	 * @return void
	 */
	public static function maybe_backfill_indexes(): void {

		if ( self::INDEX_BACKFILL_VERSION === get_option( self::INDEX_BACKFILL_OPTION ) ) {
			return;
		}

		self::recount_all_indexes();
		update_option( self::INDEX_BACKFILL_OPTION, self::INDEX_BACKFILL_VERSION, false );
	}

	/**
	 * Queues one recipe for reindexing at the end of the request.
	 *
	 * @param int $recipe_id Recipe ID.
	 * @return void
	 */
	public static function mark_index_dirty( $recipe_id ): void {

		$recipe_id = (int) $recipe_id;

		if ( $recipe_id <= 0 || self::$index_flushing ) {
			return;
		}

		self::$index_dirty[ $recipe_id ] = true;

		if ( ! self::$index_flush_hooked ) {
			self::$index_flush_hooked = true;
			add_action( 'shutdown', array( __CLASS__, 'flush_recipe_indexes' ) );
		}
	}

	/**
	 * Queues every recipe that uses an ingredient.
	 *
	 * The ingredient index is what makes this a query rather than a scan — and
	 * before the backfill has run it is empty, which is why the caller of last
	 * resort is the backfill itself rather than a fallback scan here.
	 *
	 * @param int $ingredient_id Ingredient ID.
	 * @return void
	 */
	public static function mark_ingredient_dirty( $ingredient_id ): void {

		foreach ( self::ids_using_ingredient( (int) $ingredient_id, array( 'post_status' => 'any' ) ) as $recipe_id ) {
			self::mark_index_dirty( $recipe_id );
		}
	}

	/**
	 * Queues every recipe. For changes to the vocabulary itself.
	 *
	 * @return void
	 */
	public static function mark_all_indexes_dirty(): void {

		$recipe_ids = get_posts(
			array(
				'post_type'      => 'mmm_recipe',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		foreach ( (array) $recipe_ids as $recipe_id ) {
			self::mark_index_dirty( (int) $recipe_id );
		}
	}

	/**
	 * Reindexes everything queued this request.
	 *
	 * Public because a test — and a repair routine — has to be able to reach
	 * the end of the request without ending it, exactly like
	 * flush_cook_counts().
	 *
	 * @return void
	 */
	public static function flush_recipe_indexes(): void {

		$recipe_ids        = array_keys( self::$index_dirty );
		self::$index_dirty = array();

		if ( empty( $recipe_ids ) ) {
			return;
		}

		/*
		 * Too many for one request: reindex nothing here and let a single cron
		 * event a minute from now rebuild the lot. Doing the first forty and
		 * dropping the rest would be worse than doing none — it leaves a set
		 * nobody can name half correct, while this leaves it wholly stale for a
		 * minute and then wholly right.
		 */
		if ( count( $recipe_ids ) > self::INDEX_FLUSH_MAX ) {

			if ( ! wp_next_scheduled( self::INDEX_CRON_HOOK ) ) {
				wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::INDEX_CRON_HOOK );
			}

			return;
		}

		/*
		 * The flush writes meta, and meta writes are among the events that
		 * queue a recipe. Without this guard a single save would keep
		 * rediscovering itself.
		 */
		self::$index_flushing = true;

		foreach ( $recipe_ids as $recipe_id ) {
			( new self( (int) $recipe_id ) )->recount_indexes();
		}

		self::$index_flushing = false;

		// The pill row counts from the same index; a cache of it that nothing
		// clears is the trap this plugin has already fallen into once, with the
		// ingredient-page transient.
		delete_transient( 'mmm_archive_diet_counts' );
		delete_transient( 'mmm_archive_time_counts' );
	}

	/**
	 * The recipes that use an ingredient, from the index.
	 *
	 * @param int                  $ingredient_id Ingredient ID.
	 * @param array<string, mixed> $args          Overrides for the query.
	 * @return int[] Recipe IDs.
	 */
	public static function ids_using_ingredient( int $ingredient_id, array $args = array() ): array {

		if ( $ingredient_id <= 0 ) {
			return array();
		}

		$query = wp_parse_args(
			$args,
			array(
				'post_type'      => 'mmm_recipe',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		$query['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'   => self::META_INGREDIENT_INDEX,
				'value' => (string) $ingredient_id,
			),
		);

		return array_map( 'intval', (array) get_posts( Mixmymeals_Post_Types::exclude_unlisted( $query ) ) );
	}


	/**
	 * Watches everything that can change a recipe's index.
	 *
	 * Attached once per request from mixmymeals.php, beside
	 * register_cook_count(). The shape is the same and so is the reason: the
	 * value belongs to a recipe, but almost every event that changes it happens
	 * somewhere else — here three hops away, on an ingredient's terms or on a
	 * product of that ingredient. That distance is why this one also gets a
	 * repair run (Mixmymeals_Cleanup) rather than trusting the hooks alone.
	 *
	 * @return void
	 */
	public static function register_recipe_index(): void {

		static $done = false;

		if ( $done ) {
			return;
		}

		$done = true;

		// The recipe itself: saved, or its steps and time written directly.
		add_action( 'wp_after_insert_post', array( __CLASS__, 'index_on_post_saved' ), 10, 2 );
		add_action( 'added_post_meta', array( __CLASS__, 'index_on_meta_written' ), 10, 4 );
		add_action( 'updated_post_meta', array( __CLASS__, 'index_on_meta_written' ), 10, 4 );
		add_action( 'deleted_post_meta', array( __CLASS__, 'index_on_meta_written' ), 10, 4 );

		// Diets are terms on ingredients and products, so the event that
		// changes a recipe's answer never touches the recipe.
		add_action( 'set_object_terms', array( __CLASS__, 'index_on_terms_set' ), 10, 6 );
		add_action( 'delete_term', array( __CLASS__, 'index_on_term_deleted' ), 10, 3 );
		// A diet moved in the tree implies something else than before.
		add_action( 'edited_term', array( __CLASS__, 'index_on_term_deleted' ), 10, 3 );

		// An ingredient or product leaving, changing status or being rehung.
		// The relations are read before the row disappears; the recount runs on
		// shutdown, when it no longer could.
		add_action( 'before_delete_post', array( __CLASS__, 'index_on_before_delete' ), 10, 2 );
		add_action( 'trashed_post', array( __CLASS__, 'index_on_status_event' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'index_on_status_event' ) );

		add_action( 'admin_init', array( __CLASS__, 'maybe_backfill_indexes' ) );

		// The overflow from a change that touched more recipes than one request
		// should carry; see flush_recipe_indexes().
		add_action( self::INDEX_CRON_HOOK, array( __CLASS__, 'recount_all_indexes' ) );
	}

	/**
	 * A post was saved: the recipe itself, or something a recipe reads through.
	 *
	 * @param int          $post_id Post ID.
	 * @param WP_Post|null $post    The post.
	 * @return void
	 */
	public static function index_on_post_saved( $post_id, $post = null ): void {

		if ( ! $post instanceof WP_Post ) {
			$post = get_post( (int) $post_id );
		}

		if ( ! $post instanceof WP_Post ) {
			return;
		}

		self::mark_index_source_dirty( $post );
	}

	/**
	 * A trash or untrash: a product leaving the shelf changes what its
	 * ingredient can be bought as, and for_ingredient() lists published
	 * products only.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function index_on_status_event( $post_id ): void {

		$post = get_post( (int) $post_id );

		if ( $post instanceof WP_Post ) {
			self::mark_index_source_dirty( $post );
		}
	}

	/**
	 * A post is about to be deleted for good. Read the relations now.
	 *
	 * @param int          $post_id Post ID.
	 * @param WP_Post|null $post    The post.
	 * @return void
	 */
	public static function index_on_before_delete( $post_id, $post = null ): void {

		if ( ! $post instanceof WP_Post ) {
			$post = get_post( (int) $post_id );
		}

		if ( $post instanceof WP_Post ) {
			self::mark_index_source_dirty( $post );
		}
	}

	/**
	 * Queues whatever a post is relevant to: itself if it is a recipe, its
	 * recipes if it is an ingredient, its parent's recipes if it is a product.
	 *
	 * @param WP_Post $post The post.
	 * @return void
	 */
	private static function mark_index_source_dirty( WP_Post $post ): void {

		if ( 'mmm_recipe' === $post->post_type ) {
			self::mark_index_dirty( (int) $post->ID );
			return;
		}

		if ( 'mmm_ingredient' === $post->post_type ) {
			self::mark_ingredient_dirty( (int) $post->ID );
			return;
		}

		if ( 'mmm_product' === $post->post_type ) {
			self::mark_ingredient_dirty( (int) $post->post_parent );
		}
	}

	/**
	 * Meta that an index reads through was written.
	 *
	 * @param int    $meta_id  Meta row ID.
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Meta key.
	 * @param mixed  $value    Value; part of the hook signature, unused here —
	 *                         what changed matters, not what it changed to.
	 * @return void
	 */
	public static function index_on_meta_written( $meta_id, $post_id, $meta_key, $value = null ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed

		$post_id = (int) $post_id;

		/*
		 * The index's own rows, written by the flush. Ignored explicitly rather
		 * than left to the re-entry guard, because a later change of key names
		 * would otherwise turn into an endless queue nobody can see.
		 */
		if ( in_array( $meta_key, array( self::META_DIET_INDEX, self::META_INGREDIENT_INDEX ), true ) ) {
			return;
		}

		if ( in_array( $meta_key, array( '_rmm_steps', self::META_PREP_TIME ), true ) ) {
			self::mark_index_dirty( $post_id );
			return;
		}

		// The legacy diet fields Mixmymeals_Diet::for_post() still falls back
		// to, and the ingredient's pointer at the product its figures come from.
		if ( in_array( $meta_key, array( Mixmymeals_Diet::LEGACY_META_INGREDIENT, Mixmymeals_Product::META_REFERENCE ), true ) ) {
			self::mark_ingredient_dirty( $post_id );
			return;
		}

		if ( Mixmymeals_Diet::LEGACY_META_PRODUCT === $meta_key ) {
			self::mark_ingredient_dirty( (int) wp_get_post_parent_id( $post_id ) );
		}
	}

	/**
	 * Diet terms were set on an ingredient or a product.
	 *
	 * @param int    $object_id Object ID.
	 * @param array  $terms     Terms passed in.
	 * @param array  $tt_ids    Term taxonomy IDs.
	 * @param string $taxonomy  Taxonomy.
	 * @return void
	 */
	public static function index_on_terms_set( $object_id, $terms, $tt_ids, $taxonomy ): void {

		if ( Mixmymeals_Diet::TAXONOMY !== $taxonomy ) {
			return;
		}

		$post = get_post( (int) $object_id );

		if ( $post instanceof WP_Post ) {
			self::mark_index_source_dirty( $post );
		}
	}

	/**
	 * A diet term was deleted or edited (renamed, moved in the tree): every
	 * recipe's answer may have changed.
	 *
	 * @param int    $term     Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy.
	 * @return void
	 */
	public static function index_on_term_deleted( $term, $tt_id, $taxonomy ): void {

		if ( Mixmymeals_Diet::TAXONOMY === $taxonomy ) {
			self::mark_all_indexes_dirty();
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

	/**
	 * Stores how long the dish takes, or clears the field.
	 *
	 * **0 deletes the row rather than storing a zero.** A dish that takes no
	 * time is not a thing anyone means; a zero here has only ever meant "the
	 * field was left empty". Note the deliberate difference to
	 * set_shelf_life(): there 0 is a real answer ("eat it the day it is made")
	 * and had to be told apart from silence with a ?int, which is why that
	 * setter deletes on null and stores on 0. Here silence and zero are the
	 * same claim, so the absence of the row carries it — the convention
	 * META_COOK_COUNT keeps, and the one the archive's "under N minutes" filter
	 * depends on: its meta_query joins on the key, so a recipe nobody has timed
	 * drops out by itself instead of counting as the fastest dish on the site.
	 *
	 * @param int|string $minutes Minutes, 0 or '' to clear.
	 * @return void
	 */
	public function set_preparation_time( $minutes ): void {

		if ( ! $this->is_valid() ) {
			return;
		}

		$minutes = max( 0, absint( $minutes ) );

		if ( 0 === $minutes ) {
			delete_post_meta( $this->post_id, self::META_PREP_TIME );
			return;
		}

		update_post_meta( $this->post_id, self::META_PREP_TIME, $minutes );
	}

	/**
	 * The thresholds the archive filters by, ascending and positive.
	 *
	 * Filter `mixmymeals_prep_time_steps` so a site can offer its own scale;
	 * anything that is not a positive integer is dropped rather than turned
	 * into a path nobody can reach.
	 *
	 * @return int[]
	 */
	public static function prep_time_steps(): array {

		$steps = (array) apply_filters( 'mixmymeals_prep_time_steps', self::PREP_TIME_STEPS );

		/*
		 * intval() and then a positivity test, not absint(): absint( -5 ) is 5,
		 * so a site that passed a negative by mistake would silently gain an
		 * "under 5 minutes" filter it never asked for. A step nobody can mean
		 * is dropped.
		 */
		$steps = array_filter(
			array_map( 'intval', $steps ),
			static fn( $minutes ) => $minutes > 0
		);

		sort( $steps );

		return array_values( array_unique( $steps ) );
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

	/**
	 * How long the dish takes, in minutes; 0 when nobody has said.
	 *
	 * Deliberately not ?int: with 0 no longer storable (see
	 * set_preparation_time()), 0 and "not stated" are one and the same, and the
	 * eight places that print this all test `> 0` already.
	 *
	 * @return int
	 */
	public function get_preparation_time(): int {

		if ( ! $this->is_valid() ) {
			return 0;
		}

		$time = get_post_meta( $this->post_id, self::META_PREP_TIME, true );
		return $time !== '' ? max( 0, intval( $time ) ) : 0;
	}

	/**
	 * Whether anybody has timed this dish.
	 *
	 * @return bool
	 */
	public function has_preparation_time(): bool {
		return $this->get_preparation_time() > 0;
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
	 * Warms everything a diet read touches, for a whole list of posts at once.
	 *
	 * Without it each ingredient and each product costs a post row, its meta
	 * and its terms one at a time — on a recipe archive that was hundreds of
	 * queries before a single chip appeared.
	 *
	 * @param int[] $post_ids Ingredient and product IDs.
	 * @return void
	 */
	private static function prime_diet_reads( array $post_ids ): void {

		$post_ids = array_values( array_unique( array_filter( array_map( 'intval', $post_ids ) ) ) );

		if ( empty( $post_ids ) ) {
			return;
		}

		if ( function_exists( '_prime_post_caches' ) ) {
			_prime_post_caches( $post_ids, false, true );
		}

		Mixmymeals_Diet::prime( $post_ids );
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

		$rows = $this->get_ingredients_normalized();

		// One term query for the whole recipe instead of one per ingredient
		// and per pinned product.
		$prime = array();

		foreach ( $rows as $ing ) {
			$prime[] = (int) $ing['id'];

			if ( ! empty( $ing['product_id'] ) ) {
				$prime[] = (int) $ing['product_id'];
			}
		}

		self::prime_diet_reads( $prime );

		$sets = array();

		foreach ( $rows as $ing ) {

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
			$sets[] = ( new Mixmymeals_Ingredient( (int) $ing['id'] ) )->get_diets_pinned( $product_id );
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
	 * accordingly — and counting only the ones that have to be in it. What the
	 * chips show as "capable".
	 *
	 * @return string[] Known diet slugs.
	 */
	public function get_diet_suitabilities(): array {

		if ( ! $this->is_valid() ) {
			return array();
		}

		$rows = $this->get_ingredients_normalized();

		/*
		 * Optional ingredients do not constrain what a dish can be cooked for,
		 * because they can be left out. A recipe whose required ingredients are
		 * all vegan and that offers cheese on the side is vegan-capable — leave
		 * the cheese off and it is vegan. Counting the cheese here would take
		 * the claim off a dish that only ever *might* contain it.
		 *
		 * The counterpart is get_diets(), which counts everything: as written,
		 * with the cheese, the dish is not vegan. That is the difference
		 * between "Vegan" and "Vegan-capable".
		 *
		 * A recipe of nothing but optional ingredients would leave nothing to
		 * judge, so it falls back to all of them rather than claiming anything
		 * it likes.
		 */
		$required = array_values( array_filter( $rows, static fn( $ing ) => empty( $ing['optional'] ) ) );

		if ( ! empty( $required ) ) {
			$rows = $required;
		}

		$prime = array();

		foreach ( $rows as $ing ) {
			$prime[] = (int) $ing['id'];
		}

		// Products first, in one query, then the diets of ingredients and
		// products together.
		Mixmymeals_Product::prime_for_ingredients( $prime );

		foreach ( $prime as $ingredient_id ) {
			foreach ( Mixmymeals_Product::for_ingredient( $ingredient_id ) as $product ) {
				$prime[] = $product->get_id();
			}
		}

		self::prime_diet_reads( $prime );

		$sets = array();

		/*
		 * Je Zeile ihre eigene Ausschlussliste. Dieselbe Zutat kann in zwei
		 * Schritten stehen und dort verschieden ausgeschlossen sein; combine()
		 * schneidet die Mengen, also setzt sich der strengere Stand durch. Wer
		 * ein Paket in einem Schritt ausschliesst und es im anderen vergisst,
		 * bekommt trotzdem den Ausschluss — das ist die Richtung, in der ein
		 * Versehen nichts behauptet, was niemand behaupten wollte.
		 */
		foreach ( $rows as $ing ) {
			$sets[] = ( new Mixmymeals_Ingredient( (int) $ing['id'] ) )
				->get_diet_suitabilities( array_map( 'intval', (array) ( $ing['excluded_products'] ?? array() ) ) );
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
			'calories'  => array( 'calories', ' kcal' ),
			'protein'   => array( 'proteinContent', ' g' ),
			'carbs'     => array( 'carbohydrateContent', ' g' ),
			'fat'       => array( 'fatContent', ' g' ),
			'saturates' => array( 'saturatedFatContent', ' g' ),
			'sugar'     => array( 'sugarContent', ' g' ),
			'fiber'     => array( 'fiberContent', ' g' ),
			'salt'      => array( 'sodiumContent', ' g' ),
		);
		foreach ( $mapping as $key => list( $prop, $unit ) ) {

			if ( empty( $nutrition[ $key ] ) ) {
				continue;
			}

			$value = (float) $nutrition[ $key ];

			/*
			 * schema.org's sodiumContent is sodium, and this plugin stores
			 * salt — the figure a packet prints. They differ by a factor of
			 * 2.5 (NaCl is 39.3 % sodium by mass), so until 3.52.0 every
			 * recipe published a sodium figure two and a half times the real
			 * one, which is what Google's rich result and anything else
			 * reading the markup repeated.
			 */
			if ( 'salt' === $key ) {
				$value /= 2.5;
			}

			$info[ $prop ] = round( $value, 1 ) . $unit;
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
