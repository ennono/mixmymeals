<?php
/**
 * Which columns the nutrition tables show, and what they are called.
 *
 * Four tables carry nutrition figures — ingredient, recipe, meal and plan —
 * and each used to hard-code its columns and headings. This class is the one
 * place that knows what a table *may* show (the registry) and what the site
 * chose (the option), so the settings screen and the renderer cannot disagree.
 *
 * Only columns that mean something for their subject are on offer. A plan's
 * "total per person" was dropped for that reason: summed over the plan's whole
 * length it is a number nobody eats, and "average per day" and "average per
 * meal" already say what a person gets.
 *
 * @package MixMyMeals
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Column registry and per-subject configuration of the nutrition tables.
 */
final class Mixmymeals_Nutrition_Table {

	/**
	 * Option name prefix; the full name is this plus the context.
	 */
	const OPTION_PREFIX = 'mixmymeals_nutrition_table_';

	/**
	 * Settings field id prefix; the settings option base is added in front.
	 */
	const FIELD_PREFIX = 'nutrition_table_';

	/**
	 * Post type → context.
	 */
	const POST_TYPE_CONTEXT = array(
		'mmm_ingredient' => 'ingredient',
		'mmm_recipe'     => 'recipe',
		'mmm_meal'       => 'meal',
		'mmm_meal_plan'  => 'plan',
	);

	/**
	 * The subjects whose figures can rest on a cooking factor. An ingredient's
	 * table states the ingredient as bought, so it never does.
	 */
	const COOKING_CONTEXTS = array( 'recipe', 'meal', 'plan' );

	/**
	 * The subjects that have a table, with their settings label.
	 *
	 * @return array<string,string>
	 */
	public static function contexts(): array {

		return array(
			'ingredient' => __( 'Ingredient nutrition table', 'mixmymeals' ),
			'recipe'     => __( 'Recipe nutrition table', 'mixmymeals' ),
			'meal'       => __( 'Meal nutrition table', 'mixmymeals' ),
			'plan'       => __( 'Meal plan nutrition table', 'mixmymeals' ),
		);
	}

	/**
	 * The context of a post, '' for anything without a nutrition table.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function context_for_post( int $post_id ): string {

		return self::POST_TYPE_CONTEXT[ get_post_type( $post_id ) ] ?? '';
	}

	/**
	 * Every column a subject can show, in table order.
	 *
	 * `title` is the default heading (translated, so an untouched site gets what
	 * the translation says), `on` whether it shows until someone decides, and
	 * `note` what it means, for the settings screen. Filter
	 * `mixmymeals_nutrition_table_columns`.
	 *
	 * @param string $context ingredient, recipe, meal or plan.
	 * @return array<string,array{title:string,on:bool,note:string}>
	 */
	public static function available_columns( string $context ): array {

		$per_100g = array(
			'title' => __( 'per 100 g', 'mixmymeals' ),
			'on'    => true,
			'note'  => __( 'The only column the traffic light can be read against.', 'mixmymeals' ),
		);

		switch ( $context ) {

			case 'ingredient':
				$columns = array( 'per_100g' => $per_100g );
				break;

			case 'recipe':
			case 'meal':
				$columns = array(
					'serving'  => array(
						'title' => __( 'per serving', 'mixmymeals' ),
						'on'    => true,
						'note'  => __( 'Add {weight} to the heading to print the weight of a serving in grams, and {cooked} for the word marking it as cooked; without them the default heading carries both in brackets.', 'mixmymeals' ),
					),
					'ri'       => array(
						'title' => __( 'of RI', 'mixmymeals' ),
						'on'    => false,
						'note'  => __( 'Share of the reference intake that one serving covers.', 'mixmymeals' ),
					),
					'per_100g' => $per_100g,
				);
				break;

			case 'plan':
				$columns = array(
					'day'      => array(
						'title' => __( 'Avg. per day / person', 'mixmymeals' ),
						'on'    => true,
						'note'  => __( 'Averaged over the days that carry meals.', 'mixmymeals' ),
					),
					'ri'       => array(
						'title' => __( 'of RI', 'mixmymeals' ),
						'on'    => true,
						'note'  => __( 'Share of the reference intake that the daily average covers.', 'mixmymeals' ),
					),
					'meal'     => array(
						'title' => __( 'Avg. per meal / person', 'mixmymeals' ),
						'on'    => true,
						'note'  => '',
					),
					'per_100g' => array(
						// A weighted mix of every portion on the plan, not an exact
						// figure of one dish as on the other three tables.
						'title' => __( 'Avg. per 100 g', 'mixmymeals' ),
					) + $per_100g,
				);
				break;

			default:
				return array();
		}

		return (array) apply_filters( 'mixmymeals_nutrition_table_columns', $columns, $context );
	}

	/**
	 * The heading of the first column, which carries the nutrient names.
	 *
	 * @return string
	 */
	public static function default_label(): string {

		return __( 'Nutrition', 'mixmymeals' );
	}

	/**
	 * What the table is configured to be: the columns that show, with their
	 * headings, in table order.
	 *
	 * A never-saved option means the defaults; a saved one is read as it stands,
	 * apart from a column the registry has meanwhile gained, which takes its
	 * default. An empty heading is the default heading.
	 *
	 * @param string               $context ingredient, recipe, meal or plan.
	 * @param array<string,string> $tokens  Values for placeholders in headings
	 *                                      ({weight}, {cooked}); a heading
	 *                                      without the placeholder is printed
	 *                                      as typed.
	 * @return array{label:string,columns:array<string,string>,light:bool,cooked_note:bool,ri_note:bool}
	 */
	public static function config( string $context, array $tokens = array() ): array {

		$available = self::available_columns( $context );
		$stored    = get_option( self::OPTION_PREFIX . $context, false );
		$stored    = is_array( $stored ) && isset( $stored['columns'] ) && is_array( $stored['columns'] ) ? $stored : array();

		/*
		 * By key, not by "was it saved": an option saved before this switch
		 * existed carries no such key, and reading that as "off" would hide
		 * the note on every site that had touched its table.
		 */
		$cooked_note = in_array( $context, self::COOKING_CONTEXTS, true )
			&& ( array_key_exists( 'cooked_note', $stored ) ? ! empty( $stored['cooked_note'] ) : true );

		// Switched off, the marker goes with the note: one decision.
		$tokens['cooked'] = $cooked_note ? (string) ( $tokens['cooked'] ?? '' ) : '';

		// Read by key for the same reason. Only where the table can have the
		// column the note explains.
		$ri_note = isset( $available['ri'] )
			&& ( array_key_exists( 'ri_note', $stored ) ? ! empty( $stored['ri_note'] ) : true );

		$columns = array();

		foreach ( $available as $key => $def ) {

			$saved = $stored['columns'][ $key ] ?? null;
			$show  = is_array( $saved ) ? ! empty( $saved['show'] ) : $def['on'];

			if ( ! $show ) {
				continue;
			}

			$title = is_array( $saved ) ? trim( (string) ( $saved['title'] ?? '' ) ) : '';

			if ( '' === $title ) {
				$title = $def['title'];

				// The default heading names the weight itself, so a serving's
				// size is stated without every site having to ask for it.
				if ( isset( $tokens['weight'] ) && 'serving' === $key ) {
					$title .= ' (' . $tokens['weight'] . ' g' . ( '' !== $tokens['cooked'] ? ' ' . $tokens['cooked'] : '' ) . ')';
				}
			}

			foreach ( $tokens as $token => $value ) {
				$title = str_replace( '{' . $token . '}', (string) $value, $title );
			}

			// An empty {cooked} must not leave "(450 g )" behind.
			$title = trim( (string) preg_replace( array( '/\s+/', '/\s+\)/' ), array( ' ', ')' ), $title ) );

			/*
			 * Footnote marks are assigned by footnotes(), never typed: a
			 * heading carrying its own "*" (one a site wrote, or the "der RM*"
			 * the default was until 3.62.1 and a stored option may still
			 * hold) would point at whichever note happens to come first.
			 */
			$title = rtrim( rtrim( $title, '*' ) );

			$columns[ $key ] = $title;
		}

		$label = trim( (string) ( $stored['label'] ?? '' ) );

		return array(
			'label'   => '' === $label ? self::default_label() : $label,
			'columns' => $columns,
			'light'       => isset( $stored['columns'] ) ? ! empty( $stored['light'] ) : true,
			'cooked_note' => $cooked_note,
			'ri_note'     => $ri_note,
		);
	}

	/**
	 * The notes under a table and the marks that tie them to its headings.
	 *
	 * Marks are handed out in the order the notes print — "*", "**", … — and
	 * only to notes that actually print, so whichever switches are on, each
	 * mark in a heading has exactly one note below and each note at least one
	 * heading above. A note whose columns are all switched off is not printed:
	 * a footnote nothing refers to explains nothing.
	 *
	 * - Reference intake: the `ri` column, when shown and its note is on —
	 *   or, where that column carries day ratings, the rating note instead.
	 * - Cooking factor: the `serving` column (its weight) and `per_100g` (its
	 *   figures), when a factor moved the weight and that note is on.
	 * - Traffic light: the `per_100g` column, where the lights stand.
	 * - Open packets: the label heading, since every figure rests on them.
	 *
	 * The rating and light notes are not switchable: they say what a
	 * judgement printed on the page rests on, and a verdict without its
	 * grounds is the thing a nutritionist reviewing the site objected to.
	 *
	 * @param array                $config  From config().
	 * @param string[]             $cooked  Ingredient names whose cooking factor moved the weight.
	 * @param array<string,string> $explain `rating`, `light` and/or `open` note texts,
	 *                                      given by the caller only where the
	 *                                      table carries ratings or lights.
	 * @return array{notes:array<int,array{mark:string,kind:string,text:string}>, marks:array<string,string>}
	 */
	public static function footnotes( array $config, array $cooked = array(), array $explain = array() ): array {

		$columns = (array) ( $config['columns'] ?? array() );
		$wanted  = array();

		$rating = isset( $columns['ri'] ) ? (string) ( $explain['rating'] ?? '' ) : '';

		/*
		 * Where the column carries ratings, their note says everything the
		 * reference-intake note does (an average adult, 2 000 kcal, own needs
		 * differ) and stands in its place: two notes on one column, the second
		 * repeating the first, is what made readers skip both.
		 */
		if ( '' !== $rating ) {
			$wanted[] = array( 'rating', $rating, array( 'ri' ) );
		} elseif ( ! empty( $config['ri_note'] ) && isset( $columns['ri'] ) ) {
			$wanted[] = array( 'ri', Mixmymeals_Meal_Plan::reference_intake_note(), array( 'ri' ) );
		}

		$cooked_columns = array_values( array_intersect( array( 'serving', 'per_100g' ), array_keys( $columns ) ) );
		$cooked_text    = ! empty( $config['cooked_note'] ) ? self::cooked_note( $cooked ) : '';

		if ( '' !== $cooked_text && $cooked_columns ) {
			$wanted[] = array( 'cooked', $cooked_text, $cooked_columns );
		}

		// After the older two, so the marks they carry stay where they were.
		if ( '' !== (string) ( $explain['light'] ?? '' ) && isset( $columns['per_100g'] ) ) {
			$wanted[] = array( 'light', (string) $explain['light'], array( 'per_100g' ) );
		}

		/*
		 * Which packet the figures were taken from where a meal's diet left
		 * the purchase open. It concerns every figure of the table, so its
		 * mark goes on the label heading rather than on one column ('label'
		 * in `marks`, which the renderer appends to the label).
		 */
		if ( '' !== (string) ( $explain['open'] ?? '' ) ) {
			$wanted[] = array( 'open', (string) $explain['open'], array( 'label' ) );
		}

		$notes = array();
		$marks = array();

		foreach ( $wanted as $i => list( $kind, $text, $cols ) ) {

			$mark    = str_repeat( '*', $i + 1 );
			$notes[] = array(
				'mark' => $mark,
				'kind' => $kind,
				'text' => $text,
			);

			foreach ( $cols as $col ) {
				$marks[ $col ] = ( $marks[ $col ] ?? '' ) . ( isset( $marks[ $col ] ) ? ' ' : '' ) . $mark;
			}
		}

		return array(
			'notes' => $notes,
			'marks' => $marks,
		);
	}

	/**
	 * The note under a table whose figures rest on a packet nobody chose, or ''.
	 *
	 * Where a meal's diet leaves several packets of an ingredient possible,
	 * the figures are those of the least favourable one and the shopping list
	 * names none (Mixmymeals_Ingredient::diet_product()). Without saying so
	 * the table would print figures that match no packet the reader can see —
	 * so this names the ingredient, the diet and the packet the figures came
	 * from. Here and nowhere else: on the shopping list a brand would read as
	 * a recommendation to buy it.
	 *
	 * @param array $items array{name:string, diet:string, product:string} per
	 *                     ingredient (Mixmymeals_Recipe::get_open_products()).
	 * @return string Plain text.
	 */
	public static function open_note( array $items ): string {

		$parts = array();

		foreach ( $items as $item ) {

			$name    = (string) ( $item['name'] ?? '' );
			$product = (string) ( $item['product'] ?? '' );

			if ( '' === $name || '' === $product ) {
				continue;
			}

			$diet = Mixmymeals_Shortcodes::shopping_item_diet_text( array( 'diet' => (string) ( $item['diet'] ?? '' ) ) );
			$part = sprintf(
				/* translators: 1: ingredient with its diet, e.g. "Yoghurt (Vegan)", 2: the product the figures come from. */
				_x( '%1$s: %2$s', 'ingredient with the product its figures come from', 'mixmymeals' ),
				trim( $name . ' ' . $diet ),
				$product
			);

			$parts[ $part ] = $part;
		}

		if ( ! $parts ) {
			return '';
		}

		$count = count( $parts );
		$list  = implode( '; ', array_slice( array_values( $parts ), 0, 6 ) ) . ( $count > 6 ? '; …' : '' );

		return sprintf(
			/* translators: %s: semicolon-separated ingredients with the product the figures use, e.g. "Yoghurt (Vegan): Alpro Vanilla". */
			_n(
				'Several products are possible for one ingredient; the figures use the least favourable (%s).',
				'Several products are possible for some ingredients; the figures use the least favourable of each (%s).',
				$count,
				'mixmymeals'
			),
			$list
		);
	}

	/**
	 * The note under a table whose weight rests on cooking factors, or ''.
	 *
	 * Names each ingredient with its factor, because "cooked" alone does not
	 * say which part of the dish was corrected or by how much — and a reader
	 * comparing two recipes needs to see why the same pasta weighs differently
	 * in each, and to check the number if it looks wrong.
	 *
	 * Items are separated by semicolons: a factor carries a decimal comma in
	 * German, and "Makkaroni: 2,5, Hack: 0,75" no longer reads as two items.
	 *
	 * @param array $cooked Items as array{name:string, factor:float}; a bare
	 *                      string is read as a name without a factor.
	 * @return string Plain text.
	 */
	public static function cooked_note( array $cooked ): string {

		$items = array();

		foreach ( $cooked as $entry ) {

			$name   = is_array( $entry ) ? (string) ( $entry['name'] ?? '' ) : (string) $entry;
			$factor = is_array( $entry ) && isset( $entry['factor'] ) ? (float) $entry['factor'] : null;

			if ( '' === $name ) {
				continue;
			}

			$item = null === $factor
				? $name
				: sprintf(
					/* translators: 1: ingredient name, 2: cooking factor, e.g. "Macaroni: 2.5". */
					_x( '%1$s: %2$s', 'ingredient with its cooking factor', 'mixmymeals' ),
					$name,
					self::format_factor( $factor )
				);

			$items[ $item ] = $item;
		}

		if ( ! $items ) {
			return '';
		}

		// A plan can name dozens; the first few say what kind of correction it is.
		$count = count( $items );
		$more  = $count > 6;
		$list  = implode( '; ', array_slice( array_values( $items ), 0, 6 ) ) . ( $more ? '; …' : '' );

		return sprintf(
			/* translators: %s: semicolon-separated ingredients with their cooking factors, e.g. "Macaroni: 2.5; Minced meat: 0.75". */
			_n(
				'Weight and figures per 100 g refer to the cooked dish (cooking factor for %s).',
				'Weight and figures per 100 g refer to the cooked dish (cooking factors for %s).',
				$count,
				'mixmymeals'
			),
			$list
		);
	}

	/**
	 * A cooking factor as a reader expects it: localised decimal mark, no
	 * trailing zeros ("2,5", "0,75", "3").
	 *
	 * @param float $factor Factor.
	 * @return string
	 */
	public static function format_factor( float $factor ): string {

		$decimals = 0;

		while ( $decimals < 3 && abs( round( $factor, $decimals ) - $factor ) > 0.0001 ) {
			++$decimals;
		}

		return function_exists( 'number_format_i18n' )
			? number_format_i18n( $factor, $decimals )
			: number_format( $factor, $decimals );
	}

	/**
	 * Whether this subject's tables print the traffic light.
	 *
	 * @param string $context ingredient, recipe, meal or plan.
	 * @return bool
	 */
	public static function light_enabled( string $context ): bool {

		return self::config( $context )['light'];
	}

	/**
	 * Sanitiser for one subject's option.
	 *
	 * Stored as `label`, `light` ('1' or '') and `columns` → key → `show`
	 * ('1' or '') and `title`. At least one column has to stay on: a table
	 * of nutrient names with nothing beside them says nothing, so the first
	 * column is switched back on and the editor told.
	 *
	 * @param string $context ingredient, recipe, meal or plan.
	 * @param mixed  $input   Posted value.
	 * @return array<string,mixed>
	 */
	public static function sanitize( string $context, $input ): array {

		$available = self::available_columns( $context );

		if ( ! is_array( $input ) ) {
			// Not posted at all (a form that did not carry the field): keep
			// what is stored rather than reading silence as "everything off".
			$kept = get_option( self::OPTION_PREFIX . $context, array() );

			return is_array( $kept ) ? $kept : array();
		}

		$out = array(
			'label'   => mb_substr( sanitize_text_field( (string) ( $input['label'] ?? '' ) ), 0, 80 ),
			'light'   => empty( $input['light'] ) ? '' : '1',
			'columns' => array(),
		);

		if ( in_array( $context, self::COOKING_CONTEXTS, true ) ) {
			$out['cooked_note'] = empty( $input['cooked_note'] ) ? '' : '1';
		}

		if ( isset( $available['ri'] ) ) {
			$out['ri_note'] = empty( $input['ri_note'] ) ? '' : '1';
		}

		$any = false;

		foreach ( array_keys( $available ) as $key ) {

			$posted = is_array( $input['columns'][ $key ] ?? null ) ? $input['columns'][ $key ] : array();
			$show   = ! empty( $posted['show'] );
			$any    = $any || $show;

			$out['columns'][ $key ] = array(
				'show'  => $show ? '1' : '',
				'title' => mb_substr( sanitize_text_field( (string) ( $posted['title'] ?? '' ) ), 0, 80 ),
			);
		}

		if ( ! $any && $available ) {

			$first                                 = array_key_first( $available );
			$out['columns'][ $first ]['show'] = '1';

			add_settings_error(
				'mixmymeals_nutrition_table',
				'mixmymeals_nutrition_table_empty_' . $context,
				sprintf(
					/* translators: %s: name of the table, e.g. "Recipe nutrition table". */
					__( '%s needs at least one column; the first one was switched back on.', 'mixmymeals' ),
					self::contexts()[ $context ] ?? $context
				)
			);
		}

		return $out;
	}
}
