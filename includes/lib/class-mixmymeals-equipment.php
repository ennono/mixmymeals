<?php
/**
 * Equipment Handler.
 *
 * @package MixMyMeals/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mixmymeals_Equipment
 *
 * Handles equipment.
 */
class Mixmymeals_Equipment {

	/**
	 * Meta key holding the affiliate links.
	 */
	const META_AFFILIATE_LINKS = '_mmm_affiliate_links';

	/**
	 * Upper bound for affiliate links per piece of equipment. A shopping page
	 * with a dozen buttons helps nobody.
	 */
	const MAX_AFFILIATE_LINKS = 8;

	/**
	 * Post ID of the piece of equipment.
	 *
	 * @var int
	 */
	private int $post_id;

	public function __construct( int $post_id ) {
		$this->post_id = $post_id;
	}

	public function get_id(): int {
		return $this->post_id;
	}

	public function is_valid(): bool {
		return $this->post_id > 0 && get_post_type( $this->post_id ) === 'mmm_equipment';
	}

	public static function exists_by_id( int $post_id ): bool {
		return $post_id > 0 && get_post_type( $post_id ) === 'mmm_equipment';
	}

	public function get_title(): string {
		if ( ! $this->is_valid() ) {
			return '';
		}
		return get_the_title( $this->post_id );
	}

	public function set_title( string $title ): void {
		if ( ! $this->is_valid() ) {
			return;
		}
		wp_update_post( array(
			'ID'         => $this->post_id,
			'post_title' => sanitize_text_field( $title ),
		) );
	}

	/** @return WP_Post[] */
	public static function get_all(): array {
		return get_posts( array(
			'post_type'      => 'mmm_equipment',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );
	}

	/**
	 * @throws \Exception On post creation failure.
	 */
	public static function create( string $title, string $status = 'publish' ): self {
		$post_id = wp_insert_post( array(
			'post_type'   => 'mmm_equipment',
			'post_title'  => sanitize_text_field( $title ),
			'post_status' => $status,
		), true );

		if ( is_wp_error( $post_id ) ) {
			throw new \Exception( esc_html( $post_id->get_error_message() ) );
		}

		return new self( $post_id );
	}

	/**
	 * Returns all categories (terms) of the piece of equipment.
	 *
	 * @return WP_Term[]
	 */
	public function get_categories(): array {

		if ( ! $this->is_valid() ) {
			return array();
		}

		$terms = wp_get_post_terms( $this->post_id, 'mmm_equipment_category' );

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		return $terms;
	}

	/**
	 * Published recipes that require this piece of equipment.
	 *
	 * Pre-filters with a serialized-token LIKE on _mmm_equipment and verifies
	 * the hit in PHP — the meta holds an array of IDs, so the LIKE alone would
	 * also match a longer ID that contains this one. The full list is cached
	 * for an hour; $limit is applied afterwards, so callers with different
	 * limits share one cache entry.
	 *
	 * The pre-filter looks at at most 100 recipes, which is plenty for the
	 * "used in these recipes" list and the admin count.
	 *
	 * @param int $limit Max recipes to return; 0 = all.
	 * @return int[]
	 */
	public function get_recipe_ids( int $limit = 0 ): array {

		if ( ! $this->is_valid() ) {
			return array();
		}

		$cache_key = 'mmm_eq_recipes_' . $this->post_id;
		$cached    = get_transient( $cache_key );

		if ( ! is_array( $cached ) ) {

			$ids = get_posts(
				Mixmymeals_Post_Types::exclude_unlisted(
				array(
							'post_type'      => 'mmm_recipe',
							'post_status'    => 'publish',
							'posts_per_page' => 100,
							'fields'         => 'ids',
							'orderby'        => 'title',
							'order'          => 'ASC',
							'no_found_rows'  => true,
							// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
							'meta_query'     => array(
								array(
									'key'     => '_mmm_equipment',
									'value'   => 'i:' . $this->post_id . ';',
									'compare' => 'LIKE',
								),
							),
						)
			)
			);

			$cached = array();

			foreach ( $ids as $recipe_id ) {
				$recipe = new Mixmymeals_Recipe( (int) $recipe_id );

				if ( in_array(
					$this->post_id,
					array_map( 'intval', $recipe->get_equipment() ),
					true
				) ) {
					$cached[] = (int) $recipe_id;
				}
			}

			set_transient( $cache_key, $cached, HOUR_IN_SECONDS );
		}

		return $limit > 0 ? array_slice( $cached, 0, $limit ) : $cached;
	}

	/**
	 * How many published recipes require this piece of equipment.
	 */
	public function count_recipes(): int {
		return count( $this->get_recipe_ids() );
	}

	/**
	 * Affiliate links of this piece of equipment.
	 *
	 * Each row: merchant (button label), url, note (optional, e.g. a price).
	 * Already sanitised — set_affiliate_links() is the only way in.
	 *
	 * @return array<int, array<string, string>>
	 */
	public function get_affiliate_links(): array {

		if ( ! $this->is_valid() ) {
			return array();
		}

		$rows = get_post_meta( $this->post_id, self::META_AFFILIATE_LINKS, true );

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$clean = array();

		foreach ( $rows as $row ) {
			if ( empty( $row['url'] ) ) {
				continue;
			}

			$clean[] = array(
				'merchant' => (string) ( $row['merchant'] ?? '' ),
				'url'      => (string) $row['url'],
				'note'     => (string) ( $row['note'] ?? '' ),
			);
		}

		return $clean;
	}

	/**
	 * Replaces the affiliate links.
	 *
	 * Sanitising lives here so that meta box, REST and importer cannot drift
	 * apart. Rows without a usable http(s) URL are dropped; a missing merchant
	 * name falls back to the host, so a button never renders unlabelled.
	 *
	 * @param array $rows Raw rows with merchant, url and note.
	 */
	public function set_affiliate_links( array $rows ): void {

		if ( ! $this->is_valid() ) {
			return;
		}

		$clean = array();

		foreach ( $rows as $row ) {

			if ( ! is_array( $row ) ) {
				continue;
			}

			$url = esc_url_raw( trim( (string) ( $row['url'] ?? '' ) ), array( 'http', 'https' ) );

			if ( '' === $url ) {
				continue;
			}

			$merchant = sanitize_text_field( (string) ( $row['merchant'] ?? '' ) );

			if ( '' === $merchant ) {
				$host     = (string) wp_parse_url( $url, PHP_URL_HOST );
				$merchant = '' !== $host ? preg_replace( '/^www\./', '', $host ) : $url;
			}

			$clean[] = array(
				'merchant' => $merchant,
				'url'      => $url,
				'note'     => sanitize_text_field( (string) ( $row['note'] ?? '' ) ),
			);

			if ( count( $clean ) >= self::MAX_AFFILIATE_LINKS ) {
				break;
			}
		}

		if ( empty( $clean ) ) {
			delete_post_meta( $this->post_id, self::META_AFFILIATE_LINKS );
			return;
		}

		update_post_meta( $this->post_id, self::META_AFFILIATE_LINKS, $clean );
	}

}
