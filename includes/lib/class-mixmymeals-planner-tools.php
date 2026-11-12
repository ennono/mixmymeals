<?php
/**
 * Planner tools: the ways into a plan that do not start from a recipe page.
 *
 * The planner could only be filled one recipe at a time, each from its own
 * page or card. That makes the recipe archive the product and the planner an
 * appendix to it. These are the three pieces that turn it round:
 *
 * - `mmm_suggest_plan` answers "for how many, how many days, what do you not
 *   eat" with a filled planner ([mmm_plan_start] asks the question).
 * - `mmm_recipe_picker` is the search behind "+ recipe" on a day of the
 *   planner: titles and ingredients, through Mixmymeals_Search.
 * - `mmm_mini_plan` is the week at a glance, for a side column or a sheet.
 *
 * All three work on the cart, so they need no account — like everything else
 * around the planner.
 *
 * @package MixMyMeals
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Suggesting a plan, picking a recipe for a day, the week at a glance.
 */
class Mixmymeals_Planner_Tools {

	/**
	 * How many recipes the picker lists at most.
	 */
	const PICKER_LIMIT = 24;

	/**
	 * How long a picker search term may be. Each term runs through three
	 * LIKE stages of Mixmymeals_Search; nobody types a longer dish.
	 */
	const PICKER_TERM_MAX = 100;

	/**
	 * How many candidates one suggestion checks at most. Each check cooks the
	 * recipe for the diets asked; the index has already narrowed them, so
	 * nearly every check succeeds and this only bounds a guest's request.
	 */
	const SUGGEST_CHECK_MAX = 60;

	/**
	 * Hooks the AJAX actions and the shortcode. Static, as the cart's are:
	 * nothing here keeps state between requests.
	 *
	 * @return void
	 */
	public static function register(): void {

		$actions = array(
			'mmm_suggest_plan'  => 'suggest_plan_ajax',
			'mmm_recipe_picker' => 'recipe_picker_ajax',
			'mmm_mini_plan'     => 'mini_plan_ajax',
		);

		foreach ( $actions as $action => $method ) {
			add_action( 'wp_ajax_' . $action, array( __CLASS__, $method ) );
			add_action( 'wp_ajax_nopriv_' . $action, array( __CLASS__, $method ) );
		}

		add_shortcode( 'mmm_plan_start', array( __CLASS__, 'plan_start_shortcode' ) );
	}

	/**
	 * What a visitor can rule out, as the question puts it, mapped to the
	 * diet a recipe must then be in force as.
	 *
	 * No diet is defined in code: the four questions are tied to the former
	 * built-in keys and reach whatever term took each over
	 * (Mixmymeals_Diet::resolve()), under its slug of today. A question
	 * whose diet does not exist on this site is not asked.
	 *
	 * @return array<string, string> Diet slug → what is left out.
	 */
	public static function exclusions(): array {

		$questions = array(
			'vegetarian'   => __( 'Meat and fish', 'mixmymeals' ),
			'vegan'        => __( 'Anything animal', 'mixmymeals' ),
			'gluten_free'  => __( 'Gluten', 'mixmymeals' ),
			'lactose_free' => __( 'Lactose', 'mixmymeals' ),
		);

		$out = array();

		foreach ( $questions as $key => $label ) {
			$slug = Mixmymeals_Diet::resolve( $key );

			if ( '' !== $slug && ! isset( $out[ $slug ] ) ) {
				$out[ $slug ] = $label;
			}
		}

		return $out;
	}

	/**
	 * The diets a visitor asked for, as this site names them: unknown values
	 * dropped, and a diet another one already implies left out (vegan is
	 * vegetarian — the tree says so, not this method).
	 *
	 * @param array $asked Raw values (slugs or former keys).
	 * @return string[]
	 */
	public static function clean_diets( array $asked ): array {

		$offered = self::exclusions();
		$diets   = array();

		foreach ( $asked as $value ) {
			$slug = is_scalar( $value ) ? Mixmymeals_Diet::resolve( sanitize_key( (string) $value ) ) : '';

			if ( isset( $offered[ $slug ] ) ) {
				$diets[ $slug ] = true;
			}
		}

		$diets = array_keys( $diets );
		$types = Mixmymeals_Diet::types();

		return array_values(
			array_filter(
				$diets,
				static function ( $slug ) use ( $diets, $types ) {
					foreach ( $diets as $other ) {
						if ( $other !== $slug && in_array( $slug, (array) ( $types[ $other ]['implies'] ?? array() ), true ) ) {
							return false;
						}
					}
					return true;
				}
			)
		);
	}

	/**
	 * How a suggested recipe is planned so that every diet asked for is in
	 * force, or null when it cannot be.
	 *
	 * The index says what a recipe *could* be cooked as; a meal is cooked as
	 * one choice (Mixmymeals_Cart::clean_diet()), and without a diet that is
	 * the recipe as written — the beef of a dish that is only
	 * vegetarian-capable. So the suggestion settles the meal on the
	 * strongest diet asked and checks the dish as the planner will read it
	 * (Mixmymeals_Meal_Plan::row_recipe()). Diets of other axes cannot be
	 * chosen per meal and must hold as cooked.
	 *
	 * @param int      $recipe_id Recipe ID.
	 * @param string[] $diets     Diets from clean_diets().
	 * @return string|null Diet to plan with ('' = no choice), or null.
	 */
	public static function planned_diet( int $recipe_id, array $diets ): ?string {

		$diet = Mixmymeals_Cart::clean_diet( $recipe_id, Mixmymeals_Diet::strongest( $diets, 'diet' ) );

		if ( ! $diets ) {
			return $diet;
		}

		$cooked = Mixmymeals_Meal_Plan::row_recipe(
			array(
				'recipe_id' => $recipe_id,
				'diet'      => $diet,
			)
		);

		$in_force = Mixmymeals_Diet::expand( $cooked->get_diets() );

		return array_diff( $diets, $in_force ) ? null : $diet;
	}

	/**
	 * Recipe IDs that can stand as the main meal of a day and are in force
	 * as every diet asked for.
	 *
	 * The categories are site content, so they are a filter and not a
	 * constant; a slug that does not exist is ignored, and with none left
	 * every recipe counts.
	 *
	 * @param string[] $diets      Diet slugs, all of which must hold.
	 * @param bool     $main_only  Only recipes of the main-meal categories.
	 * @return int[]
	 */
	public static function candidate_ids( array $diets, bool $main_only = true ): array {

		$args = array(
			'post_type'              => 'mmm_recipe',
			'post_status'            => 'publish',
			'fields'                 => 'ids',
			'posts_per_page'         => 400, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- IDs only, shuffled; a sample, not a page.
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'orderby'                => 'ID',
		);

		if ( $main_only ) {

			/**
			 * Recipe categories (slugs) a suggested plan draws its meals from.
			 *
			 * @param string[] $slugs Category slugs.
			 */
			$slugs = (array) apply_filters( 'mixmymeals_suggest_categories', array( 'hauptgerichte' ) );
			$slugs = array_values(
				array_filter(
					array_map( 'sanitize_title', $slugs ),
					static function ( $slug ) {
						return (bool) term_exists( $slug, 'mmm_recipe_category' );
					}
				)
			);

			if ( $slugs ) {
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- a few hundred recipes, asked once per suggestion.
				$args['tax_query'] = array(
					array(
						'taxonomy' => 'mmm_recipe_category',
						'field'    => 'slug',
						'terms'    => $slugs,
					),
				);
			}
		}

		if ( $diets ) {
			$meta = array( 'relation' => 'AND' );

			foreach ( $diets as $slug ) {
				$meta[] = array(
					'key'   => Mixmymeals_Recipe::META_DIET_INDEX,
					'value' => $slug,
				);
			}

			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- see above.
			$args['meta_query'] = $meta;
		}

		// A suggestion is a listing: unlisted and password-protected recipes
		// stay out, as they do of the archive and the search. Last, because
		// it merges into the meta_query above.
		$args = Mixmymeals_Post_Types::exclude_unlisted( $args );

		return array_map( 'intval', ( new WP_Query( $args ) )->posts );
	}

	/**
	 * AJAX: fills the planner with a suggestion.
	 *
	 * Replaces what is in the planner — the page asks before it sends this
	 * when there is something to lose. One recipe per day as dinner, from
	 * today onwards, each for as many servings as there are people.
	 *
	 * @return void
	 */
	public static function suggest_plan_ajax(): void {

		check_ajax_referer( 'mmm_frontend_nonce', 'nonce' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above.
		$people = isset( $_POST['people'] ) ? (int) $_POST['people'] : 2;
		$people = max( 1, min( 12, $people ) );

		$days = isset( $_POST['days'] ) ? (int) $_POST['days'] : 5;
		$days = max( 1, min( 7, $days ) );

		$asked = isset( $_POST['diets'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['diets'] ) ) : array();
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$diets = self::clean_diets( $asked );

		// Recipe → diet it is planned with; only dishes that hold every diet
		// asked for as they will be cooked. Main dishes first; too few of
		// them for the days asked, and the rest of the recipes top up rather
		// than hand back half a week.
		$picked = array();
		$tried  = 0;

		foreach ( array( true, false ) as $main_only ) {

			$ids = array_diff( self::candidate_ids( $diets, $main_only ), array_keys( $picked ) );
			shuffle( $ids );

			foreach ( $ids as $recipe_id ) {

				if ( count( $picked ) >= $days || $tried >= self::SUGGEST_CHECK_MAX ) {
					break 2;
				}

				++$tried;
				$diet = self::planned_diet( (int) $recipe_id, $diets );

				if ( null !== $diet ) {
					$picked[ (int) $recipe_id ] = $diet;
				}
			}

			if ( count( $picked ) >= $days ) {
				break;
			}
		}

		if ( ! $picked ) {
			wp_send_json_error(
				array(
					'code'    => 'no_recipes',
					'message' => __( 'No recipes fit all of that. Try leaving out less.', 'mixmymeals' ),
				),
				404
			);
		}

		$cart = new Mixmymeals_Cart();
		$cart->clear();

		// The people asked for are the planner's people: without them the
		// planner says "1 person" above recipes planned for several.
		// One person is no list, which save_eaters() stores as none.
		$cart->save_eaters(
			array_fill(
				0,
				min( $people, Mixmymeals_Meal_Plan::EATERS_MAX ),
				array( 'profile' => Mixmymeals_Meal_Plan::DEFAULT_PROFILE )
			)
		);

		$today = (int) wp_date( 'N' );

		$i = 0;

		foreach ( $picked as $recipe_id => $diet ) {
			$cart->add( $recipe_id, $people, 'dinner', ( ( $today - 1 + $i ) % 7 ) + 1, $diet );
			++$i;
		}

		$count = count( ( new Mixmymeals_Cart() )->get_items() );

		wp_send_json_success(
			array(
				'count'   => $count,
				'message' => sprintf(
					/* translators: %s: number of recipes. */
					_n( '%s recipe planned.', '%s recipes planned.', $count, 'mixmymeals' ),
					number_format_i18n( $count )
				),
			)
		);
	}

	/**
	 * What the picker and the mini plan show of a recipe.
	 *
	 * @param int $recipe_id Recipe ID.
	 * @return array{id:int,title:string,url:string,thumb:string,servings:int,time:int}
	 */
	private static function recipe_row( int $recipe_id ): array {

		$recipe = new Mixmymeals_Recipe( $recipe_id );

		return array(
			'id'       => $recipe_id,
			'title'    => html_entity_decode( get_the_title( $recipe_id ), ENT_QUOTES, 'UTF-8' ),
			'url'      => (string) get_permalink( $recipe_id ),
			'thumb'    => (string) get_the_post_thumbnail_url( $recipe_id, 'thumbnail' ),
			'servings' => max( 1, (int) round( $recipe->get_servings() ) ),
			'time'     => $recipe->get_preparation_time(),
		);
	}

	/**
	 * AJAX: recipes for "+ recipe" on a day of the planner. With a term,
	 * the matches by title and ingredient; without, the newest.
	 *
	 * @return void
	 */
	public static function recipe_picker_ajax(): void {

		check_ajax_referer( 'mmm_frontend_nonce', 'nonce' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$term = isset( $_POST['term'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['term'] ) ) ) : '';
		$term = trim( mb_substr( $term, 0, self::PICKER_TERM_MAX ) );

		if ( '' !== $term ) {
			$ids = Mixmymeals_Search::ids( 'recipe', $term );
		} else {
			// The newest, as the archive lists them: without unlisted and
			// password-protected recipes (Mixmymeals_Search does the same).
			$ids = get_posts(
				Mixmymeals_Post_Types::exclude_unlisted(
					array(
						'post_type'      => 'mmm_recipe',
						'post_status'    => 'publish',
						'fields'         => 'ids',
						'posts_per_page' => self::PICKER_LIMIT,
						'orderby'        => 'date',
						'order'          => 'DESC',
						'no_found_rows'  => true,
					)
				)
			);
		}

		$rows = array();

		foreach ( array_slice( array_map( 'intval', $ids ), 0, self::PICKER_LIMIT ) as $recipe_id ) {
			if ( Mixmymeals_Cart::recipe_is_plannable( $recipe_id ) ) {
				$rows[] = self::recipe_row( $recipe_id );
			}
		}

		wp_send_json_success(
			array(
				'term'    => $term,
				'recipes' => $rows,
			)
		);
	}

	/**
	 * The planner as seven short lines: what is cooked on which day.
	 *
	 * @return array{count:int,days:array<int,array{label:string,today:bool,items:array}>,unassigned:array}
	 */
	public static function mini_plan(): array {

		$labels = array(
			1 => __( 'Monday', 'mixmymeals' ),
			2 => __( 'Tuesday', 'mixmymeals' ),
			3 => __( 'Wednesday', 'mixmymeals' ),
			4 => __( 'Thursday', 'mixmymeals' ),
			5 => __( 'Friday', 'mixmymeals' ),
			6 => __( 'Saturday', 'mixmymeals' ),
			7 => __( 'Sunday', 'mixmymeals' ),
		);

		$today = (int) wp_date( 'N' );
		$days  = array();

		// From today onwards: the week that matters starts now, not on Monday.
		for ( $i = 0; $i < 7; $i++ ) {
			$key          = ( ( $today - 1 + $i ) % 7 ) + 1;
			$days[ $key ] = array(
				'label' => $labels[ $key ],
				'today' => 0 === $i,
				'items' => array(),
			);
		}

		$unassigned = array();
		$items      = ( new Mixmymeals_Cart() )->get_items();

		foreach ( $items as $item ) {

			$recipe_id = (int) ( $item['recipe_id'] ?? 0 );

			if ( ! Mixmymeals_Cart::recipe_is_plannable( $recipe_id ) ) {
				continue;
			}

			$row = array(
				'title' => html_entity_decode( get_the_title( $recipe_id ), ENT_QUOTES, 'UTF-8' ),
				'url'   => (string) get_permalink( $recipe_id ),
				'thumb' => (string) get_the_post_thumbnail_url( $recipe_id, 'thumbnail' ),
			);

			$day = (int) ( $item['day'] ?? 0 );

			if ( isset( $days[ $day ] ) ) {
				$days[ $day ]['items'][] = $row;
			} else {
				$unassigned[] = $row;
			}
		}

		return array(
			'count'      => count( $items ),
			'days'       => $days,
			'unassigned' => $unassigned,
		);
	}

	/**
	 * AJAX: the mini plan as data; the page draws it.
	 *
	 * @return void
	 */
	public static function mini_plan_ajax(): void {

		check_ajax_referer( 'mmm_frontend_nonce', 'nonce' );

		$plan = self::mini_plan();

		// A list, in order: a JSON object would lose the order from today on.
		$days = array();
		foreach ( $plan['days'] as $key => $day ) {
			$day['day'] = $key;
			$days[]     = $day;
		}
		$plan['days'] = $days;

		wp_send_json_success( $plan );
	}

	/**
	 * Shortcode [mmm_plan_start]: the question that leads to a suggested
	 * plan. Three answers, each with a default, so that one tap on the
	 * button is enough.
	 *
	 * @param array|string $atts people, days: the defaults shown.
	 * @return string
	 */
	public static function plan_start_shortcode( $atts ): string {

		$atts = shortcode_atts(
			array(
				'people' => 2,
				'days'   => 5,
			),
			$atts,
			'mmm_plan_start'
		);

		$people = max( 1, min( 12, (int) $atts['people'] ) );
		$days   = max( 1, min( 7, (int) $atts['days'] ) );

		ob_start();
		?>
		<form class="mmm-plan-start" data-plan-start novalidate>

			<div class="mmm-plan-start__field">
				<span class="mmm-plan-start__label" id="mmm-plan-start-people"><?php esc_html_e( 'For how many?', 'mixmymeals' ); ?></span>
				<div class="mmm-plan-start__stepper" role="group" aria-labelledby="mmm-plan-start-people">
					<button type="button" class="mmm-plan-start__step" data-step="-1" aria-label="<?php esc_attr_e( 'One person fewer', 'mixmymeals' ); ?>">&minus;</button>
					<input type="number" class="mmm-plan-start__people" name="people" min="1" max="12" inputmode="numeric" value="<?php echo esc_attr( (string) $people ); ?>" aria-labelledby="mmm-plan-start-people">
					<button type="button" class="mmm-plan-start__step" data-step="1" aria-label="<?php esc_attr_e( 'One person more', 'mixmymeals' ); ?>">+</button>
				</div>
			</div>

			<fieldset class="mmm-plan-start__field">
				<legend class="mmm-plan-start__label"><?php esc_html_e( 'How many days?', 'mixmymeals' ); ?></legend>
				<div class="mmm-plan-start__chips">
					<?php foreach ( array( 3, 5, 7 ) as $n ) : ?>
						<label class="mmm-plan-start__chip">
							<input type="radio" name="days" value="<?php echo esc_attr( (string) $n ); ?>"<?php checked( $days, $n ); ?>>
							<span><?php echo esc_html( (string) $n ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</fieldset>

			<fieldset class="mmm-plan-start__field mmm-plan-start__field--wide">
				<legend class="mmm-plan-start__label"><?php esc_html_e( 'What do you not eat?', 'mixmymeals' ); ?></legend>
				<div class="mmm-plan-start__chips">
					<?php foreach ( self::exclusions() as $slug => $label ) : ?>
						<label class="mmm-plan-start__chip">
							<input type="checkbox" name="diets[]" value="<?php echo esc_attr( $slug ); ?>">
							<span><?php echo esc_html( $label ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</fieldset>

			<div class="mmm-plan-start__actions">
				<button type="submit" class="mmm-plan-start__submit"><?php esc_html_e( 'Suggest a plan', 'mixmymeals' ); ?></button>
				<span class="mmm-plan-start__note"><?php esc_html_e( 'Free, no account. You can swap every recipe.', 'mixmymeals' ); ?></span>
			</div>

			<div class="mmm-msg mmm-plan-start__msg" role="alert"></div>

		</form>
		<?php
		return (string) ob_get_clean();
	}
}
