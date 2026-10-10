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
						'note'  => __( 'Add {weight} to the heading to print the weight of a serving in grams; without it the default heading carries the weight in brackets.', 'mixmymeals' ),
					),
					'ri'       => array(
						'title' => __( 'of RI*', 'mixmymeals' ),
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
						'title' => __( 'of RI*', 'mixmymeals' ),
						'on'    => true,
						'note'  => __( 'Share of the reference intake that the daily average covers.', 'mixmymeals' ),
					),
					'meal'     => array(
						'title' => __( 'Avg. per meal / person', 'mixmymeals' ),
						'on'    => true,
						'note'  => '',
					),
					'per_100g' => $per_100g,
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
	 *                                      ({weight}); a heading without the
	 *                                      placeholder is printed as typed.
	 * @return array{label:string,columns:array<string,string>,light:bool}
	 */
	public static function config( string $context, array $tokens = array() ): array {

		$available = self::available_columns( $context );
		$stored    = get_option( self::OPTION_PREFIX . $context, false );
		$stored    = is_array( $stored ) && isset( $stored['columns'] ) && is_array( $stored['columns'] ) ? $stored : array();

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
					$title .= ' (' . $tokens['weight'] . ' g)';
				}
			}

			foreach ( $tokens as $token => $value ) {
				$title = str_replace( '{' . $token . '}', $value, $title );
			}

			$columns[ $key ] = $title;
		}

		$label = trim( (string) ( $stored['label'] ?? '' ) );

		return array(
			'label'   => '' === $label ? self::default_label() : $label,
			'columns' => $columns,
			'light'   => isset( $stored['columns'] ) ? ! empty( $stored['light'] ) : true,
		);
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
