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

	/**
	 * Meta key of the package flag: the unit is something bought whole
	 * (tin, jar, pack, bunch) rather than a measure (tbsp, pinch). No row =
	 * not a package.
	 *
	 * @var string
	 */
	const META_PACKAGE = '_mmm_unit_package';

	/**
	 * Whether this unit is a package — what a shopping list may round up to
	 * for an ingredient without a fixed product (HG-115).
	 *
	 * @return bool
	 */
	public function is_package(): bool {

		return $this->is_valid() && '1' === (string) get_post_meta( $this->post_id, self::META_PACKAGE, true );
	}

	/**
	 * Marks the unit as a package, or clears the mark (deletes the row).
	 *
	 * @param bool $is_package Whether it is bought whole.
	 * @return void
	 */
	public function set_package( bool $is_package ): void {

		if ( ! $this->is_valid() ) {
			return;
		}

		if ( $is_package ) {
			update_post_meta( $this->post_id, self::META_PACKAGE, '1' );
		} else {
			delete_post_meta( $this->post_id, self::META_PACKAGE );
		}
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
	 * A volume: millilitres below 1000 ("59 ml", "2,5 ml" below 10), litres
	 * with at most one decimal from 1000 ml ("1,2 l", "2 l") — the weight
	 * rule, for ingredients bought by volume (4.19.0).
	 *
	 * @param float $ml Volume in millilitres.
	 * @return string
	 */
	public static function format_volume( float $ml ): string {

		if ( round( $ml ) >= 1000 ) {
			return self::format_decimal( $ml / 1000 ) . ' l';
		}

		if ( $ml < 10 ) {
			return self::format_decimal( $ml ) . ' ml';
		}

		return number_format_i18n( round( $ml ) ) . ' ml';
	}

	/**
	 * A quantity of an ingredient: its weight, or — where it states a
	 * density (Mixmymeals_Ingredient::get_density()) — the volume that
	 * weight takes up.
	 *
	 * @param float $grams   Weight in grams.
	 * @param float $density Grams per millilitre; 0 = weigh.
	 * @return string
	 */
	public static function format_quantity( float $grams, float $density = 0.0 ): string {

		return $density > 0 ? self::format_volume( $grams / $density ) : self::format_weight( $grams );
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
	 * Unit titles that are kitchen measures — how a recipe measures, not how
	 * anything is bought (4.19.0). Compared in lower case on the title up to
	 * its first comma or bracket, so "EL, gehackt" and "Schuss (2 cl)" count.
	 *
	 * @var string[]
	 */
	const MEASURE_UNITS = array( 'el', 'tl', 'prise', 'msp.', 'msp', 'messerspitze', 'tasse', 'schuss', 'spritzer', 'handvoll', 'tbsp', 'tsp', 'pinch', 'cup', 'dash', 'handful' );

	/**
	 * Option: whether shopping lists count measures or weigh them.
	 *
	 * @var string
	 */
	const MEASURES_OPTION = 'mixmymeals_amount_measures';

	/**
	 * Measures are counted, "1× TL + 5× EL (≈ 54 g)" (default, as before).
	 *
	 * @var string
	 */
	const MEASURES_COUNT = 'count';

	/**
	 * Measures are summed into the line's weight, "54 g".
	 *
	 * @var string
	 */
	const MEASURES_WEIGHT = 'weight';

	/**
	 * Whether a unit title is a kitchen measure (EL, TL, Prise …).
	 * Filter `mixmymeals_measure_units` adds or removes titles.
	 *
	 * @param string $unit Unit title.
	 * @return bool
	 */
	public static function is_measure_unit( string $unit ): bool {

		$base     = strtolower( trim( (string) preg_split( '/[,(]/', $unit )[0] ) );
		$measures = array_map( 'strtolower', (array) apply_filters( 'mixmymeals_measure_units', self::MEASURE_UNITS ) );

		return '' !== $base && in_array( $base, $measures, true );
	}

	/**
	 * The measure modes, worded for the settings.
	 *
	 * @return array<string,string> Mode => label.
	 */
	public static function measure_modes(): array {

		return array(
			self::MEASURES_COUNT  => __( 'As counts — "1× tsp + 5× tbsp (≈ 54 g)"', 'mixmymeals' ),
			self::MEASURES_WEIGHT => __( 'As weight — "54 g"', 'mixmymeals' ),
		);
	}

	/**
	 * The measure mode in force; unknown → MEASURES_COUNT.
	 *
	 * @return string
	 */
	public static function measure_mode(): string {

		$mode = (string) get_option( self::MEASURES_OPTION, self::MEASURES_COUNT );

		return isset( self::measure_modes()[ $mode ] ) ? $mode : self::MEASURES_COUNT;
	}

	/**
	 * Sanitises the measure mode.
	 *
	 * @param mixed $value Posted value.
	 * @return string
	 */
	public static function sanitize_measure_mode( $value ): string {

		$value = sanitize_key( (string) $value );

		return isset( self::measure_modes()[ $value ] ) ? $value : self::MEASURES_COUNT;
	}

	/**
	 * Whether a shopping-list unit is printed as a count: counted, and not a
	 * measure weighed by the setting. A unit bought whole always counts,
	 * whatever its title.
	 *
	 * @param array $unit One entry of a line's 'units'.
	 * @return bool
	 */
	public static function counts_on_list( array $unit ): bool {

		if ( ! self::unit_is_counted( $unit ) ) {
			return false;
		}

		return ! empty( $unit['package'] ) || ! empty( $unit['is_package'] )
			|| self::MEASURES_WEIGHT !== self::measure_mode()
			|| ! self::is_measure_unit( (string) ( $unit['unit_title'] ?? '' ) );
	}

	/**
	 * One shopping-list line worded from its units, through format_text().
	 *
	 * Counted units are listed as counts, everything else is summed into
	 * grams — measures (EL, Prise) too where the site weighs them
	 * (measure_mode(), 4.19.0). The level says how much follows a count (HG-115):
	 *
	 * 1. count and unit — "2× Dose";
	 * 2. and, for a package, its weight — "2× Dose à 400 g";
	 * 3. and the line's total weight — "2× Dose à 400 g (≈ 800 g)"; not
	 *    behind a single package, whose "à" says it already.
	 *
	 * "à" only for a package (an entry carrying `package`: the product's or
	 * a unit bought whole), never for a measure (EL, Prise). A line without
	 * counts is its weight at every level. The short form commits to one
	 * figure for a teaser: the count where every unit is counted, else the
	 * total weight.
	 *
	 * @param array      $units        A line's 'units' from calc_shopping_list().
	 * @param float      $total_weight The line's 'total_weight'.
	 * @param string|int $form         1, 2, 3 (also as strings), 'full' (= 3,
	 *                                 the default) or 'short'.
	 * @param float      $density      The ingredient's density (g/ml); every
	 *                                 weight of the line is then printed as
	 *                                 a volume. 0 = grams (4.19.0).
	 * @return string
	 */
	public static function format_units( array $units, float $total_weight, $form = 'full', float $density = 0.0 ): string {

		if ( 'short' === $form ) {
			$counted = array_filter( $units, array( self::class, 'counts_on_list' ) );

			if ( ! empty( $units ) && count( $counted ) === count( $units ) ) {
				$count = 0.0;
				$title = '';

				foreach ( $units as $unit ) {
					$count += (float) ( $unit['amount'] ?? 0 );
					$title  = (string) ( $unit['unit_title'] ?? '' );
				}

				return self::format_text( $count, $title );
			}

			return self::format_quantity( max( 0.0, $total_weight ), $density );
		}

		$level  = 'full' === $form ? 3 : max( 1, min( 3, (int) $form ) );
		$pieces = array();
		$loose  = 0.0;
		$single = false;

		foreach ( $units as $unit ) {
			$title = trim( (string) ( $unit['unit_title'] ?? '' ) );

			if ( self::counts_on_list( $unit ) && '' !== $title && 'g' !== strtolower( $title ) ) {
				$text = self::format_text( (float) ( $unit['amount'] ?? 0 ), $title );
				$each = (float) ( $unit['weight_per_unit'] ?? 0 );

				if ( '' !== $text && $level >= 2 && ! empty( $unit['package'] ) && $each > 0 ) {
					/* translators: 1: a count with its package, "2× Dose"; 2: the weight of one, "400 g". */
					$text = sprintf( __( '%1$s à %2$s', 'mixmymeals' ), $text, self::format_quantity( $each, $density ) );

					// One package "à 500 g" already says the total.
					$single = abs( (float) ( $unit['amount'] ?? 0 ) - 1 ) < 0.001;
				}

				if ( '' !== $text ) {
					$pieces[] = $text;
				}
			} else {
				$loose += (float) ( $unit['weight_g'] ?? 0 );
			}
		}

		$parts = $pieces;

		if ( $loose > 0 || empty( $parts ) ) {
			$parts[] = self::format_quantity( max( 0.0, $loose ), $density );
		}

		$text = implode( ' + ', $parts );

		if ( $level >= 3 && ! empty( $pieces ) && $total_weight > 0 && ! ( $single && 1 === count( $pieces ) && $loose <= 0 ) ) {
			$text .= ' (≈ ' . self::format_quantity( $total_weight, $density ) . ')';
		}

		return $text;
	}

	/**
	 * One amount of an ingredient list at a level (HG-115): 1 the amount,
	 * 2 and for a unit bought whole its weight ("1× Dose à 400 g"), 3 and the
	 * weight of the amount ("3× EL (≈ 30 g)"). An ingredient list never rounds
	 * to packages: this is the recipe's amount.
	 *
	 * @param float  $count    Quantity in the unit.
	 * @param string $unit     Unit title.
	 * @param float  $weight_g Weight of the amount in grams.
	 * @param float  $each     Weight of one unit, 0 when unknown.
	 * @param bool   $package  Whether the unit is bought whole.
	 * @param int    $level    1, 2 or 3.
	 * @return string
	 */
	public static function format_text_at_level( float $count, string $unit, float $weight_g, float $each, bool $package, int $level ): string {

		$text = self::format_text( $count, $unit, $weight_g );

		if ( '' === $text || '' === trim( $unit ) || 'g' === strtolower( trim( $unit ) ) || self::is_metric_unit( $unit ) ) {
			return $text;
		}

		$single = false;

		if ( $level >= 2 && $package && $each > 0 ) {
			/* translators: 1: a count with its package, "2× Dose"; 2: the weight of one, "400 g". */
			$text = sprintf( __( '%1$s à %2$s', 'mixmymeals' ), $text, self::format_weight( $each ) );

			// One package "à 400 g" already says the weight.
			$single = abs( $count - 1 ) < 0.001;
		}

		if ( $level >= 3 && $weight_g > 0 && ! $single ) {
			$text .= ' (≈ ' . self::format_weight( $weight_g ) . ')';
		}

		return $text;
	}

	/*
	 * -------------------------------------------------------------------------
	 * Where amounts are shown, and how much of them (HG-115)
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Option prefix of the level per place: mixmymeals_amount_level_<place>.
	 *
	 * @var string
	 */
	const LEVEL_OPTION_PREFIX = 'mixmymeals_amount_level_';

	/**
	 * Places and the level each shows while nobody chose one — what they
	 * showed before the setting existed.
	 *
	 * @var array<string,string>
	 */
	const LEVEL_DEFAULTS = array(
		'ingredient_list' => '1',
		'shopping_list'   => '3',
		'teaser'          => 'short',
		'mail'            => '3',
		'pdf'             => '3',
	);

	/**
	 * Option: what a shopping-list line that mixes a package with grams does.
	 *
	 * @var string
	 */
	const MIXED_OPTION = 'mixmymeals_amount_mixed';

	/**
	 * Mixed lines list both, "2× Dose + 100 g" (default).
	 *
	 * @var string
	 */
	const MIXED_PLUS = 'plus';

	/**
	 * Mixed lines are rounded up into whole packages, "3× Dose".
	 *
	 * @var string
	 */
	const MIXED_ROUND_UP = 'round_up';

	/**
	 * The places with a level of their own, worded for the settings.
	 *
	 * @return array<string,string> Key => label.
	 */
	public static function level_places(): array {

		return array(
			'ingredient_list' => __( 'Ingredient lists (recipe, meal)', 'mixmymeals' ),
			'shopping_list'   => __( 'Shopping list (plan page, planner)', 'mixmymeals' ),
			'teaser'          => __( 'Shopping list teaser', 'mixmymeals' ),
			'mail'            => __( 'Subscription mails', 'mixmymeals' ),
			'pdf'             => __( 'PDF', 'mixmymeals' ),
		);
	}

	/**
	 * The levels a place can show.
	 *
	 * @param string $place A key of level_places().
	 * @return array<string,string> Level => label.
	 */
	public static function level_choices( string $place ): array {

		$choices = array(
			'1' => __( 'Count and unit — "2× tin"', 'mixmymeals' ),
			'2' => __( 'With the weight of a package — "2× tin à 400 g"', 'mixmymeals' ),
			'3' => __( 'With the total weight — "2× tin à 400 g (≈ 800 g)"', 'mixmymeals' ),
		);

		if ( 'teaser' === $place ) {
			$choices = array( 'short' => __( 'One figure — "2× tin" or "1.2 kg"', 'mixmymeals' ) ) + $choices;
		}

		return $choices;
	}

	/**
	 * The level a place shows: the stored choice if valid, else its default.
	 * Validated on read, so an unknown stored value is no level at all.
	 *
	 * @param string $place A key of level_places().
	 * @return string '1', '2', '3' or 'short'.
	 */
	public static function level( string $place ): string {

		$default = self::LEVEL_DEFAULTS[ $place ] ?? '3';
		$stored  = (string) get_option( self::LEVEL_OPTION_PREFIX . $place, '' );

		return isset( self::level_choices( $place )[ $stored ] ) ? $stored : $default;
	}

	/**
	 * Sanitises a level for a place; unknown → '' (the default applies).
	 *
	 * @param string $place A key of level_places().
	 * @param mixed  $value Posted value.
	 * @return string
	 */
	public static function sanitize_level( string $place, $value ): string {

		$value = sanitize_key( (string) $value );

		return isset( self::level_choices( $place )[ $value ] ) ? $value : '';
	}

	/**
	 * What a line mixing a package with grams does.
	 *
	 * @return array<string,string> Mode => label.
	 */
	public static function mixed_modes(): array {

		return array(
			self::MIXED_PLUS     => __( 'List both — "2× tin + 100 g"', 'mixmymeals' ),
			self::MIXED_ROUND_UP => __( 'Round up into packages — "3× tin"', 'mixmymeals' ),
		);
	}

	/**
	 * The mixed-line mode in force; unknown → MIXED_PLUS.
	 *
	 * @return string
	 */
	public static function mixed_mode(): string {

		$mode = (string) get_option( self::MIXED_OPTION, self::MIXED_PLUS );

		return isset( self::mixed_modes()[ $mode ] ) ? $mode : self::MIXED_PLUS;
	}

	/**
	 * Sanitises the mixed-line mode.
	 *
	 * @param mixed $value Posted value.
	 * @return string
	 */
	public static function sanitize_mixed_mode( $value ): string {

		$value = sanitize_key( (string) $value );

		return isset( self::mixed_modes()[ $value ] ) ? $value : self::MIXED_PLUS;
	}

	/**
	 * A shopping-list line worded for a place, at that place's level.
	 *
	 * @param array  $line  One line of calc_shopping_list().
	 * @param string $place A key of level_places().
	 * @return string
	 */
	public static function format_line( array $line, string $place ): string {

		return self::format_units(
			(array) ( $line['units'] ?? array() ),
			(float) ( $line['total_weight'] ?? 0 ),
			self::level( $place ),
			Mixmymeals_Ingredient::density_of( (int) ( $line['ingredient_id'] ?? 0 ) )
		);
	}
}
