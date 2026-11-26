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

	/**
	 * Which recipes reference this unit, and how often each does.
	 *
	 * A unit is referenced by `amount_id` inside a recipe's steps, so there is
	 * nothing to query against — every recipe has to be opened. Counted per
	 * occurrence rather than per recipe: a unit used in three steps of one
	 * recipe is three lines that would change meaning if it went.
	 *
	 * @return array<int, array{recipe_id:int, recipe_title:string, status:string, occurrences:int}>
	 */
	public function get_usage(): array {

		if ( ! $this->is_valid() ) {
			return array();
		}

		return self::usage_map()[ $this->post_id ] ?? array();
	}

	/**
	 * Every unit that any recipe references, from a single pass.
	 *
	 * Asking each unit separately means opening every recipe once per unit,
	 * which is a cross product — fine for the one unit an editor is looking at,
	 * ruinous for the audit, which asks about all of them. So the scan happens
	 * once and both callers read from the result.
	 *
	 * Cached for the request. Anything that deletes or rewrites recipes within
	 * one request and then asks again would see a stale map; nothing does, and
	 * the alternative is re-reading every recipe for each question.
	 *
	 * @return array<int, array<int, array{recipe_id:int, recipe_title:string, status:string, occurrences:int}>>
	 *         Keyed by unit ID. Units nobody references are absent, not empty.
	 */
	public static function usage_map(): array {

		static $map = null;

		if ( null !== $map ) {
			return $map;
		}

		$map = array();

		$recipes = get_posts(
			array(
				'post_type'      => 'mmm_recipe',
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		foreach ( $recipes as $recipe_id ) {

			$recipe = new Mixmymeals_Recipe( (int) $recipe_id );
			$counts = array();

			foreach ( $recipe->get_steps() as $step ) {
				foreach ( $step['ingredients'] ?? array() as $ing ) {

					$amount_id = intval( $ing['amount_id'] ?? 0 );

					if ( $amount_id > 0 ) {
						$counts[ $amount_id ] = ( $counts[ $amount_id ] ?? 0 ) + 1;
					}
				}
			}

			if ( ! $counts ) {
				continue;
			}

			$post = get_post( (int) $recipe_id );

			foreach ( $counts as $amount_id => $occurrences ) {
				$map[ $amount_id ][] = array(
					'recipe_id'    => (int) $recipe_id,
					'recipe_title' => get_the_title( (int) $recipe_id ),
					'status'       => $post ? $post->post_status : '',
					'occurrences'  => $occurrences,
				);
			}
		}

		return $map;
	}

	/**
	 * How many recipe lines use this unit.
	 *
	 * Derived from get_usage() rather than counted separately: the admin box
	 * and the REST endpoints answer the same question, and two scans would
	 * eventually disagree about it.
	 *
	 * @return int
	 */
	public function get_amount_usage_count(): int {

		return (int) array_sum( array_column( $this->get_usage(), 'occurrences' ) );
	}

	/*
	 * =========================================================================
	 * Amounts for people (HG-114)
	 * =========================================================================
	 *
	 * Every amount a person reads — ingredient lists, shopping lists, their
	 * breakdowns, teasers, mails, the backend, the PDF — is worded here, so
	 * the same quantity reads the same everywhere:
	 *
	 * - a count as "2× Dose", never a plural of the unit;
	 * - halves and quarters as fractions (½, ¼, ¾, "1½× Dose"), anything
	 *   else with a decimal comma and at most one decimal;
	 * - grams as "200 g", from 1000 g as "1,2 kg";
	 * - no unit (or a metric one) as the quantity itself, never "200× g".
	 *
	 * Machine-read output (Bring!, schema.org, REST) does not come here: it
	 * keeps Mixmymeals_Ingredient::format_amount(), without fractions and
	 * without thousands separators.
	 */

	/**
	 * Unit titles that are measures of mass or volume: their amount is a
	 * quantity, "250 ml", not a count, "250× ml". Compared in lower case.
	 *
	 * @var string[]
	 */
	const METRIC_UNITS = array( 'g', 'kg', 'mg', 'ml', 'cl', 'dl', 'l' );

	/**
	 * Fractions printed as such, keyed by their value in quarters.
	 *
	 * @var array<int,string>
	 */
	const FRACTIONS = array(
		1 => '¼',
		2 => '½',
		3 => '¾',
	);

	/**
	 * The one wording of an amount for people.
	 *
	 * @param float  $count    Quantity in the unit (for a gram row: the grams).
	 * @param string $unit     Unit title; '' or "g" for an amount in grams.
	 * @param float  $weight_g Weight in grams; preferred over $count for a
	 *                         gram row, ignored for every other unit.
	 * @return string "2× EL", "1½× Dose", "250 ml", "200 g", "1,2 kg";
	 *                '' when there is nothing above zero.
	 */
	public static function format_text( float $count, string $unit = '', float $weight_g = 0.0 ): string {

		$unit = trim( $unit );

		if ( '' === $unit || 'g' === strtolower( $unit ) ) {
			$grams = $weight_g > 0 ? $weight_g : $count;

			return $grams > 0 ? self::format_weight( $grams ) : '';
		}

		if ( $count <= 0 ) {
			return '';
		}

		if ( self::is_metric_unit( $unit ) ) {
			return self::format_decimal( $count ) . ' ' . $unit;
		}

		return self::format_count( $count ) . '× ' . $unit;
	}

	/**
	 * Whether a unit title is a measure (g, ml, l …) rather than a thing
	 * counted (Dose, EL, Stück).
	 *
	 * @param string $unit Unit title.
	 * @return bool
	 */
	public static function is_metric_unit( string $unit ): bool {

		$metric = (array) apply_filters( 'mixmymeals_metric_units', self::METRIC_UNITS );

		return in_array( strtolower( trim( $unit ) ), array_map( 'strtolower', $metric ), true );
	}

	/**
	 * A count: ¼, ½ and ¾ as fractions ("1½"), otherwise at most one decimal
	 * with the locale's separator ("0,3", "2"). Never shown as zero: a count
	 * too small for one decimal reads as the smallest one.
	 *
	 * @param float $count Quantity.
	 * @return string
	 */
	public static function format_count( float $count ): string {

		if ( $count <= 0 ) {
			return '0';
		}

		$quarters = $count * 4;

		if ( abs( $quarters - round( $quarters ) ) < 0.02 ) {
			$quarters = (int) round( $quarters );
			$whole    = intdiv( $quarters, 4 );
			$rest     = $quarters % 4;

			if ( 0 === $rest ) {
				return number_format_i18n( $whole );
			}

			return ( $whole > 0 ? number_format_i18n( $whole ) : '' ) . self::FRACTIONS[ $rest ];
		}

		return self::format_decimal( $count );
	}

	/**
	 * A weight: whole grams below 10 g with at most one decimal ("2,5 g"),
	 * whole grams up to 999 g, kilograms with at most one decimal from 1000 g
	 * ("1,2 kg", "2 kg").
	 *
	 * @param float $grams Weight in grams.
	 * @return string
	 */
	public static function format_weight( float $grams ): string {

		if ( round( $grams ) >= 1000 ) {
			return self::format_decimal( $grams / 1000 ) . ' kg';
		}

		if ( $grams < 10 ) {
			return self::format_decimal( $grams ) . ' g';
		}

		return number_format_i18n( round( $grams ) ) . ' g';
	}

	/**
	 * A number with at most one decimal, none where it is whole after
	 * rounding; above zero never printed as "0".
	 *
	 * @param float $value Value.
	 * @return string
	 */
	private static function format_decimal( float $value ): string {

		$rounded = round( $value, 1 );

		if ( $value > 0 && $rounded <= 0 ) {
			$rounded = 0.1;
		}

		$decimals = abs( $rounded - round( $rounded ) ) < 0.001 ? 0 : 1;

		return number_format_i18n( $rounded, $decimals );
	}

	/**
	 * Whether a shopping-list unit is counted rather than weighed: anything
	 * taken in fixed steps (whole ones, halves …) goes into the basket as
	 * "1½× Dose"; a unit without a step reads better as its weight.
	 *
	 * @param array $unit One entry of a line's 'units' from calc_shopping_list().
	 * @return bool
	 */
	public static function unit_is_counted( array $unit ): bool {

		return ! empty( $unit['is_indivisible'] ) || (int) ( $unit['step_divisor'] ?? 0 ) > 0;
	}

	/**
	 * One shopping-list line worded from its units, through format_text().
	 *
	 * Counted units are listed as counts, everything else is summed into
	 * grams; where counts are shown the total weight follows:
	 * "2× Dose + 100 g (≈ 900 g)". The short form commits to one figure for a
	 * teaser: the count where every unit is counted, else the total weight.
	 *
	 * @param array  $units        A line's 'units' from calc_shopping_list().
	 * @param float  $total_weight The line's 'total_weight'.
	 * @param string $form         'full' (default) or 'short'.
	 * @return string
	 */
	public static function format_units( array $units, float $total_weight, string $form = 'full' ): string {

		if ( 'short' === $form ) {
			$counted = array_filter( $units, array( self::class, 'unit_is_counted' ) );

			if ( ! empty( $units ) && count( $counted ) === count( $units ) ) {
				$count = 0.0;
				$title = '';

				foreach ( $units as $unit ) {
					$count += (float) ( $unit['amount'] ?? 0 );
					$title  = (string) ( $unit['unit_title'] ?? '' );
				}

				return self::format_text( $count, $title );
			}

			return self::format_weight( max( 0.0, $total_weight ) );
		}

		$pieces = array();
		$loose  = 0.0;

		foreach ( $units as $unit ) {
			$title = (string) ( $unit['unit_title'] ?? '' );

			if ( self::unit_is_counted( $unit ) && '' !== trim( $title ) && 'g' !== strtolower( trim( $title ) ) ) {
				$pieces[] = self::format_text( (float) ( $unit['amount'] ?? 0 ), $title );
			} else {
				$loose += (float) ( $unit['weight_g'] ?? 0 );
			}
		}

		$parts = array_values( array_filter( $pieces, 'strlen' ) );

		if ( $loose > 0 || empty( $parts ) ) {
			$parts[] = self::format_weight( max( 0.0, $loose ) );
		}

		$text = implode( ' + ', $parts );

		if ( ! empty( $pieces ) && $total_weight > 0 ) {
			$text .= ' (≈ ' . self::format_weight( $total_weight ) . ')';
		}

		return $text;
	}
}
