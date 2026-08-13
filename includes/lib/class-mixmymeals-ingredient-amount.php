<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mixmymeals_Ingredient_Amount
 *
 * Handles ingredient unit amounts, including creation, retrieval,
 * updates, and deletion of ingredient unit posts.
 */
class Mixmymeals_Ingredient_Amount {

	/**
	 * Post ID of the ingredient unit.
	 *
	 * @var int
	 */
	private int $post_id;

	/**
	 * Constructor.
	 *
	 * Initializes the ingredient amount object with a given post ID.
	 *
	 * @param int $post_id The post ID of the ingredient unit.
	 */
	public function __construct( int $post_id ) {
		$this->post_id = $post_id;
	}

	public function get_id(): int {
		return $this->post_id;
	}

	public function is_valid(): bool {
		if ( $this->post_id <= 0 ) {
			return false;
		}

		// Schneller als get_post()
		if ( get_post_type( $this->post_id ) !== 'mmm_ingr_unit' ) {
			return false;
		}

		$parent_id = (int) wp_get_post_parent_id( $this->post_id );

		if ( $parent_id <= 0 ) {
			return false;
		}

		// Parent validieren
		$parent = new Mixmymeals_Ingredient( $parent_id );

		return $parent->is_valid();
	}

	/**
	 * Factory method: Creates a new ingredient unit.
	 *
	 * @param int    $parent_id The parent ingredient ID.
	 * @param string $title     The title of the unit (default: "New Amount").
	 *
	 * @return self Returns a new instance of Mixmymeals_Ingredient_Amount.
	 *
	 * @throws \Exception If post creation fails.
	 */
	public static function create( int $parent_id, string $title = 'New Amount' ): self {
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'mmm_ingr_unit',
				'post_status' => 'publish',
				'post_title'  => sanitize_text_field( $title ),
				'post_parent' => $parent_id,
			)
		);

		if ( is_wp_error( $post_id ) ) {
			// esc_html suggested by Plugin Check.
			throw new \Exception( esc_html( $post_id->get_error_message() ) );
		}

		return new self( $post_id );
	}

	/**
	 * Getter: Returns the weight of the unit.
	 *
	 * @return float The weight of the unit.
	 */
	public function get_weight(): float {
		if ( ! $this->is_valid() ) {
			return 0.0;
		}

		return floatval(
			get_post_meta( $this->post_id, '_mmm_ingr_unit_weight', true )
		);
	}

	/**
	 * Setter: Sets the weight of the unit.
	 *
	 * @param float $weight The weight to set.
	 *
	 * @return void
	 */
	public function set_weight( float $weight ): void {
		if ( ! $this->is_valid() ) {
			return;
		}

		update_post_meta( $this->post_id, '_mmm_ingr_unit_weight', $weight );
	}

	/**
	 * Getter: Returns the parent ingredient ID.
	 *
	 * @return int The parent ingredient ID.
	 */
	public function get_parent_ingredient_id(): int {
		return wp_get_post_parent_id( $this->post_id );
	}

	/**
	 * Setter: Sets the parent ingredient ID.
	 *
	 * @param int $ingredient_id The parent ingredient ID to set.
	 *
	 * @return void
	 */
	public function set_parent_ingredient_id( int $ingredient_id ): void {
		wp_update_post(
			array(
				'ID'          => $this->post_id,
				'post_parent' => $ingredient_id,
			)
		);
	}

	/**
	 * Getter: Returns the title of the unit (e.g., "Teaspoon", "Cup").
	 *
	 * @return string The title of the unit.
	 */
	public function get_title(): string {
		if ( ! $this->is_valid() ) {
			return '';
		}

		return get_the_title( $this->post_id );
	}

	/**
	 * Setter: Sets the title of the unit.
	 *
	 * @param string $title The title to set.
	 *
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
	 * Deletes the ingredient unit.
	 *
	 * @return void
	 */
	public function delete(): void {
		wp_delete_post( $this->post_id, true );
	}

	/**
	 * Meta key holding how finely the unit may be split.
	 *
	 * @var string
	 */
	const META_DIVISOR = '_mmm_unit_divisor';

	/**
	 * The pre-3.2 boolean flag. Only read, and only until migrate_indivisible()
	 * has run — see get_divisor().
	 *
	 * @var string
	 */
	const META_LEGACY_INDIVISIBLE = '_mmm_is_indivisible';

	/**
	 * Option gating the one-off move of the flag into the divisor.
	 *
	 * @var string
	 */
	const MIGRATION_OPTION = 'mixmymeals_unit_divisor_version';

	/**
	 * Setter: How finely this unit may be split. 1 = whole ones only, 2 =
	 * halves, 4 = quarters. 0 (or anything below) removes the restriction, so
	 * the unit takes any amount.
	 *
	 * One number, one statement. Divisibility used to be two settings — a
	 * boolean flag and this divisor — which meant "whole ones only" had two
	 * representations and every writer had to reconcile them. It also meant
	 * entering 1 did the opposite of what it reads like, because the divisor
	 * only started counting at 2.
	 *
	 * @param int $divisor Smallest usable portion is 1/$divisor; 0 = any amount.
	 * @return void
	 */
	public function set_divisor( int $divisor ): void {
		if ( ! $this->is_valid() ) {
			return;
		}

		if ( $divisor >= 1 ) {
			update_post_meta( $this->post_id, self::META_DIVISOR, $divisor );
		} else {
			delete_post_meta( $this->post_id, self::META_DIVISOR );
		}

		// Never leave the superseded flag behind to be picked up as a
		// fallback below.
		delete_post_meta( $this->post_id, self::META_LEGACY_INDIVISIBLE );
	}

	/**
	 * Getter: How finely the unit may be split, 0 when it takes any amount.
	 *
	 * Falls back to the old boolean flag for units the migration has not
	 * reached yet — an install restoring a backup mid-upgrade must not
	 * suddenly treat its eggs as divisible.
	 *
	 * @return int
	 */
	public function get_divisor(): int {
		if ( ! $this->is_valid() ) {
			return 0;
		}

		$divisor = (int) get_post_meta( $this->post_id, self::META_DIVISOR, true );

		if ( $divisor >= 1 ) {
			return $divisor;
		}

		return get_post_meta( $this->post_id, self::META_LEGACY_INDIVISIBLE, true ) ? 1 : 0;
	}

	/**
	 * The divisor every calculation works with. Identical to get_divisor() —
	 * kept as the name calling code uses, because it says what the number is
	 * for: a quantity is always a multiple of 1/divisor, rounded for a recipe
	 * and rounded up for a shopping list.
	 *
	 * @return int
	 */
	public function get_step_divisor(): int {
		return $this->get_divisor();
	}

	/**
	 * Whether the unit only comes in whole ones.
	 *
	 * Derived rather than stored since 3.2. Kept because it reads better at
	 * the call sites that only care about this one case, and because the REST
	 * API and the MCP server still expose it.
	 *
	 * @return bool
	 */
	public function is_indivisible(): bool {
		return 1 === $this->get_divisor();
	}

	/**
	 * Setter: Convenience wrapper for the two divisor values that answer
	 * "whole ones only?".
	 *
	 * Switching it off clears the restriction entirely rather than only the
	 * "whole ones" part — false means "may be split", and leaving a divisor of
	 * 4 in place would contradict the caller. A unit that should go from whole
	 * ones to quarters is set with set_divisor( 4 ).
	 *
	 * @param bool $is_indivisible Whether the unit only comes in whole ones.
	 * @return void
	 */
	public function set_indivisible( bool $is_indivisible ): void {
		$this->set_divisor( $is_indivisible ? 1 : 0 );
	}

	/**
	 * Moves the pre-3.2 boolean flag into the divisor, once per install.
	 *
	 * Attached from mixmymeals.php. The fallback in get_divisor() means an
	 * un-migrated unit already behaves correctly; this only tidies the rows so
	 * the fallback can be dropped later.
	 *
	 * @return int Number of units migrated.
	 */
	public static function migrate_indivisible(): int {

		if ( (int) get_option( self::MIGRATION_OPTION ) >= 1 ) {
			return 0;
		}

		global $wpdb;

		/*
		 * Every row carrying the flag, whatever its value — the falsy ones have
		 * to be deleted too, or the fallback in get_divisor() keeps reading
		 * them forever.
		 */
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off migration over a meta key with no API equivalent.
			$wpdb->prepare(
				"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
				self::META_LEGACY_INDIVISIBLE
			)
		);

		$migrated = 0;

		foreach ( $rows as $row ) {
			$post_id = (int) $row->post_id;

			/*
			 * Only a truthy flag becomes a divisor. "0" and "" mean the unit was
			 * explicitly divisible, and SQL does not agree with PHP about that:
			 * `meta_value != ''` matches the string "0", so filtering in the
			 * query turned "may be split" into "whole ones only" — the exact
			 * opposite — for any row stored that way.
			 */
			if ( $row->meta_value && ! get_post_meta( $post_id, self::META_DIVISOR, true ) ) {
				update_post_meta( $post_id, self::META_DIVISOR, 1 );
				++$migrated;
			}

			delete_post_meta( $post_id, self::META_LEGACY_INDIVISIBLE );
		}

		update_option( self::MIGRATION_OPTION, 1, false );

		return $migrated;
	}

	public function get_amount_usage_count(): int {

		$amount_id = $this->post_id;

		$recipes = get_posts([
			'post_type'      => 'mmm_recipe',
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'fields'         => 'ids',
		]);

		$count = 0;

		foreach ( $recipes as $recipe_id ) {

			$recipe = new Mixmymeals_Recipe( $recipe_id );
			$steps  = $recipe->get_steps();

			foreach ( $steps as $step ) {
				foreach ( $step['ingredients'] ?? [] as $ing ) {

					if ( intval( $ing['amount_id'] ?? 0 ) === $amount_id ) {
						$count++;
					}
				}
			}
		}

		return $count;
	}

}