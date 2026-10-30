<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mixmymeals_Search
 *
 * The site search over recipes, plans, ingredients and equipment, grouped by
 * post type and ranked in stages:
 *
 *   1. Title matches. Somebody typing "Lasagne" means the recipe called that.
 *   2. Matches by substance. Recipes through their ingredients (the
 *      ingredient index on the recipe), plans through the recipes they plan.
 *   3. Full text, as WordPress understands it, as a straggler.
 *
 * The stages decide the order within a group; within a stage the closer match
 * leads (equal, then "starts with", then "contains"). A post appears once, in
 * the highest stage that found it.
 *
 * It lived in the child theme until 3.72.0, which therefore had to know the
 * meal's `recipe_id` meta, the meal → plan relation and the ingredient index.
 * The theme now only words the groups and prints the results.
 */
class Mixmymeals_Search {

	/**
	 * How many posts a single stage returns at most.
	 */
	const STAGE_LIMIT = 200;

	/**
	 * The groups, keyed as the theme addresses them, in the order they are
	 * shown: recipes first, then plans, then the two reference indexes.
	 *
	 * @return array<string, string> Group key → post type.
	 */
	public static function groups(): array {

		return array(
			'recipe'     => 'mmm_recipe',
			'plan'       => 'mmm_meal_plan',
			'ingredient' => 'mmm_ingredient',
			'equipment'  => 'mmm_equipment',
		);
	}

	/**
	 * The matches of one group, ranked and without duplicates.
	 *
	 * @param string $group Group key from groups().
	 * @param string $term  Search term.
	 * @return int[]
	 */
	public static function ids( string $group, string $term ): array {

		$term   = trim( $term );
		$groups = self::groups();

		if ( '' === $term || ! isset( $groups[ $group ] ) ) {
			return array();
		}

		$post_type = $groups[ $group ];

		$stages = array( self::title_ids( $post_type, $term ) );

		if ( 'recipe' === $group ) {
			$stages[] = self::recipe_ids_by_ingredient( $term );
		}

		if ( 'plan' === $group ) {
			$stages[] = self::plan_ids_by_recipe(
				array_merge(
					self::title_ids( 'mmm_recipe', $term ),
					self::recipe_ids_by_ingredient( $term )
				)
			);
		}

		$stages[] = self::fulltext_ids( $post_type, $term );

		$found = array();

		foreach ( $stages as $stage ) {
			foreach ( self::sort_stage( $stage, $term ) as $id ) {
				$found[ (int) $id ] = (int) $id;
			}
		}

		return array_values( $found );
	}

	/**
	 * Where to continue searching one group.
	 *
	 * The recipe and plan archives have a search of their own (?mmm_s=) with
	 * filters, which is the better place to go on. Ingredients and equipment
	 * have none; their archive is an A–Z index anyway.
	 *
	 * @param string $group Group key from groups().
	 * @param string $term  Search term.
	 * @return string
	 */
	public static function archive_link( string $group, string $term ): string {

		$groups = self::groups();

		if ( ! isset( $groups[ $group ] ) ) {
			return '';
		}

		$archive = (string) get_post_type_archive_link( $groups[ $group ] );

		if ( '' === $archive ) {
			return '';
		}

		return in_array( $group, array( 'recipe', 'plan' ), true )
			? add_query_arg( 'mmm_s', rawurlencode( $term ), $archive )
			: $archive;
	}

	/**
	 * Arguments every stage shares.
	 *
	 * exclude_unlisted() keeps unlisted plans and recipes out: a search is no
	 * way around a visibility setting.
	 *
	 * Extra arguments go in *before* that call, since it merges into an
	 * existing meta_query: setting one afterwards replaced the unlisted
	 * clause, and the ingredient stage found unlisted recipes until 3.72.0.
	 *
	 * @param string $post_type Post type.
	 * @param array  $extra     Further query arguments.
	 * @return array<string, mixed>
	 */
	private static function base_args( string $post_type, array $extra = array() ): array {

		return Mixmymeals_Post_Types::exclude_unlisted(
			$extra + array(
				'post_type'        => $post_type,
				'post_status'      => 'publish',
				'posts_per_page'   => self::STAGE_LIMIT,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'orderby'          => 'title',
				'order'            => 'ASC',

				/*
				 * Required, not taste: get_posts() sets suppress_filters to
				 * true and so switches posts_where off — the title condition
				 * would be dropped and the stage return every published post
				 * of the type instead of the matches.
				 */
				'suppress_filters' => false,
			)
		);
	}

	/**
	 * Stage 1: matches in the title.
	 *
	 * Through a posts_where filter rather than 's', which also searches
	 * content and excerpt — the stage would no longer be a stage.
	 *
	 * @param string $post_type Post type.
	 * @param string $term      Search term.
	 * @return int[]
	 */
	private static function title_ids( string $post_type, string $term ): array {

		$args                     = self::base_args( $post_type );
		$args['mmm_search_title'] = $term;

		add_filter( 'posts_where', array( __CLASS__, 'title_where' ), 10, 2 );
		$ids = get_posts( $args );
		remove_filter( 'posts_where', array( __CLASS__, 'title_where' ), 10 );

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Adds the title condition — only to queries that asked for it.
	 *
	 * @param string   $where Condition so far.
	 * @param WP_Query $query Query.
	 * @return string
	 */
	public static function title_where( $where, $query ) {

		if ( ! $query instanceof WP_Query ) {
			return $where;
		}

		$term = $query->get( 'mmm_search_title' );

		if ( ! is_string( $term ) || '' === $term ) {
			return $where;
		}

		global $wpdb;

		return $where . $wpdb->prepare(
			" AND {$wpdb->posts}.post_title LIKE %s",
			'%' . $wpdb->esc_like( $term ) . '%'
		);
	}

	/**
	 * Stage 3: full text, the way WordPress reads it.
	 *
	 * @param string $post_type Post type.
	 * @param string $term      Search term.
	 * @return int[]
	 */
	private static function fulltext_ids( string $post_type, string $term ): array {

		$args            = self::base_args( $post_type );
		$args['s']       = $term;
		$args['orderby'] = 'relevance';

		return array_map( 'intval', (array) get_posts( $args ) );
	}

	/**
	 * Stage 2 for recipes: recipes listing a matching ingredient, through the
	 * ingredient index (one meta row per ingredient on the recipe).
	 *
	 * @param string $term Search term.
	 * @return int[]
	 */
	private static function recipe_ids_by_ingredient( string $term ): array {

		$ingredient_ids = self::title_ids( 'mmm_ingredient', $term );

		if ( empty( $ingredient_ids ) ) {
			return array();
		}

		$args = self::base_args(
			'mmm_recipe',
			array(
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => Mixmymeals_Recipe::META_INGREDIENT_INDEX,
						'value'   => array_map( 'strval', $ingredient_ids ),
						'compare' => 'IN',
					),
				),
			)
		);

		return array_map( 'intval', (array) get_posts( $args ) );
	}

	/**
	 * Stage 2 for plans: plans in which a matching recipe is planned.
	 *
	 * A meal is a child of its plan and carries the recipe as meta, so the way
	 * leads through the meals to their parents — read as `id=>parent`, which
	 * needs no second query per meal and so no cap on the number of meals.
	 *
	 * @param int[] $recipe_ids Recipes already found.
	 * @return int[]
	 */
	private static function plan_ids_by_recipe( array $recipe_ids ): array {

		if ( empty( $recipe_ids ) ) {
			return array();
		}

		$meals = get_posts(
			array(
				'post_type'      => 'mmm_meal',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'id=>parent',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => 'recipe_id',
						'value'   => array_map( 'strval', array_unique( $recipe_ids ) ),
						'compare' => 'IN',
					),
				),
			)
		);

		$plans = array();

		foreach ( (array) $meals as $meal ) {
			$plan_id = (int) ( is_object( $meal ) ? $meal->post_parent : 0 );

			if ( $plan_id ) {
				$plans[ $plan_id ] = $plan_id;
			}
		}

		if ( empty( $plans ) ) {
			return array();
		}

		// Through the same visibility check as everything else, which also
		// drops plans that are not published.
		$args             = self::base_args( 'mmm_meal_plan' );
		$args['post__in'] = array_values( $plans );

		return array_map( 'intval', (array) get_posts( $args ) );
	}

	/**
	 * Orders one stage: equal title first, then "starts with", then the rest,
	 * alphabetically within each.
	 *
	 * @param int[]  $ids  IDs of this stage.
	 * @param string $term Search term.
	 * @return int[]
	 */
	private static function sort_stage( array $ids, string $term ): array {

		$term = mb_strtolower( $term );

		$rank = static function ( int $id ) use ( $term ): array {

			$title = mb_strtolower( get_the_title( $id ) );

			if ( $title === $term ) {
				$level = 0;
			} elseif ( '' !== $term && 0 === mb_strpos( $title, $term ) ) {
				$level = 1;
			} else {
				$level = 2;
			}

			return array( $level, $title );
		};

		usort(
			$ids,
			static function ( $a, $b ) use ( $rank ) {
				return $rank( (int) $a ) <=> $rank( (int) $b );
			}
		);

		return $ids;
	}
}
