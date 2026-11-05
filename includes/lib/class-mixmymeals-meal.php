<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mixmymeals_Meal
 *
 * Handles meal data including recipe references, servings,
 * notes, parent plan relationships, and meta fields.
 */
class Mixmymeals_Meal {

	/**
	 * Post ID of the meal.
	 *
	 * @var int
	 */
	private int $post_id;

	/** @param int $post_id The post ID of the meal. */
	public function __construct( int $post_id ) {
		$this->post_id = $post_id;
	}

	/**
	 * The diet the meal's recipe is cooked as — one of the recipe's
	 * get_diet_choices(), absent where it offers none. See get_diet().
	 */
	const META_DIET = '_mmm_diet';

	/**
	 * Who eats the meal: ids of the parent plan's people
	 * (Mixmymeals_Meal_Plan::get_eaters()). No row means everybody — every
	 * meal from before the field, and every meal eaten by all.
	 */
	const META_EATERS = '_mmm_eaters';

	/**
	 * How much each eater eats of this meal, where it differs from what the
	 * person usually eats: id => portions. No row, or no entry for a person,
	 * is their usual amount (Mixmymeals_Meal_Plan::resolve_portions()).
	 */
	const META_EATER_PORTIONS = '_mmm_eater_portions';

	/** Option guarding the one-off move of `note` meta into the text. */
	const TEXT_MIGRATION_OPTION  = 'mixmymeals_meal_text_version';
	const TEXT_MIGRATION_VERSION = '1';

	public function get_id(): int {
		return $this->post_id;
	}

	/**
	 * @throws \Exception On post creation failure.
	 */
	public static function create( string $title, int $plan_id, string $status = 'publish' ): self {
		$post_id = wp_insert_post( array(
			'post_type'   => 'mmm_meal',
			'post_title'  => sanitize_text_field( $title ),
			'post_status' => $status,
			'post_parent' => $plan_id,
		), true );

		if ( is_wp_error( $post_id ) ) {
			throw new \Exception( esc_html( $post_id->get_error_message() ) );
		}

		return new self( $post_id );
	}

	/**
	 * The post a page should read its credits from.
	 *
	 * A meal is a planned serving of a recipe: it shows that recipe's title,
	 * steps and featured image, so it shows the recipe's credits too — author
	 * box and image source alike. Everything else stands for itself.
	 *
	 * Every feature that credits a post has to resolve through here. Reading
	 * the meal directly instead would, on a meal page, attribute the recipe's
	 * picture and author to whoever happened to create the meal.
	 *
	 * @param int $post_id Post ID.
	 * @return int Post ID to read credits from, 0 when a meal has no recipe.
	 */
	public static function resolve_credit_source( int $post_id ): int {

		if ( 'mmm_meal' !== get_post_type( $post_id ) ) {
			return $post_id;
		}

		$recipe_id = (int) ( new self( $post_id ) )->get_recipe_id();

		// Meals can outlive their recipe (deleted, or never set on a stub).
		return Mixmymeals_Recipe::exists_by_id( $recipe_id ) ? $recipe_id : 0;
	}

	/*
	----------------------------------------------------------
	BASIC META HANDLING
	----------------------------------------------------------
	*/

	/**
	 * Returns the recipe ID associated with the meal.
	 *
	 * @return int The recipe ID.
	 */
	public function get_recipe_id(): int {
		return (int) get_post_meta( $this->post_id, 'recipe_id', true );
	}

	/**
	 * Sets the recipe ID for the meal.
	 *
	 * @param int $recipe_id The recipe ID to set.
	 *
	 * @return void
	 */
	public function set_recipe_id( int $recipe_id ): void {
		update_post_meta( $this->post_id, 'recipe_id', $recipe_id );
	}

	/**
	 * The diet this meal cooks its recipe as — one of the recipe's
	 * get_diet_choices(), or '' where the recipe offers no choice.
	 *
	 * A recipe can be "vegan-capable" — every ingredient has a vegan packet —
	 * and still be planned with mince. Which of the two lands on the table is
	 * a fact about the planning, not about the dish, so it lives here, beside
	 * the servings, and **it is always settled**: there is no "as in the
	 * recipe" for a recipe that offers two ways, because that is exactly the
	 * state in which nobody knows what to buy. A meal that never said — one
	 * from before this field, or one whose recipe changed — reads the
	 * recipe's first choice, which is the recipe as written under its own
	 * name ("Original recipe", "Vegetarian").
	 *
	 * Everything that computes from the meal reads the recipe through
	 * get_recipe(), which applies it.
	 *
	 * @return string A choice slug (possibly Mixmymeals_Diet::NONE), or ''.
	 */
	public function get_diet(): string {

		$choices = ( new Mixmymeals_Recipe( $this->get_recipe_id() ) )->get_diet_choices();

		if ( ! $choices ) {
			return '';
		}

		$stored = (string) get_post_meta( $this->post_id, self::META_DIET, true );
		$stored = Mixmymeals_Diet::NONE === $stored ? $stored : Mixmymeals_Diet::resolve( $stored );

		return in_array( $stored, $choices, true ) ? $stored : $choices[0];
	}

	/**
	 * Settles the diet. Checked against the meal's recipe, so set the recipe
	 * first.
	 *
	 * Where the recipe offers a choice something is always stored: the
	 * requested choice if it is one, else the recipe's first. Where it offers
	 * none the field is cleared — the recipe has settled it itself.
	 *
	 * @param string $diet A choice slug (or a former key); '' for the default.
	 * @return bool Whether the requested choice is the one stored.
	 */
	public function set_diet( string $diet ): bool {

		$choices = ( new Mixmymeals_Recipe( $this->get_recipe_id() ) )->get_diet_choices();

		if ( ! $choices ) {
			delete_post_meta( $this->post_id, self::META_DIET );
			return '' === $diet;
		}

		$diet  = sanitize_key( $diet );
		$diet  = Mixmymeals_Diet::NONE === $diet ? $diet : Mixmymeals_Diet::resolve( $diet );
		$valid = in_array( $diet, $choices, true );

		update_post_meta( $this->post_id, self::META_DIET, $valid ? $diet : $choices[0] );

		return $valid;
	}

	/**
	 * The meal's recipe as this meal cooks it.
	 *
	 * **Every read of a meal's dish goes through here**, not through
	 * `new Mixmymeals_Recipe( get_recipe_id() )`: that instance is the recipe
	 * as written, and reading it would put the mince on the shopping list of a
	 * meal that is cooked vegan.
	 *
	 * @return Mixmymeals_Recipe
	 */
	public function get_recipe(): Mixmymeals_Recipe {
		return ( new Mixmymeals_Recipe( $this->get_recipe_id() ) )->for_diet( $this->get_diet() );
	}

	/**
	 * Who eats the meal as stored, unchecked. For
	 * Mixmymeals_Meal_Plan::get_meal_rows(), which checks it against the
	 * plan's people it has already read; everything else reads get_eaters().
	 *
	 * @return string[]
	 */
	public function get_eaters_raw(): array {

		$stored = get_post_meta( $this->post_id, self::META_EATERS, true );

		return is_array( $stored ) ? array_map( 'strval', $stored ) : array();
	}

	/**
	 * Who eats the meal: ids of the parent plan's people, in the plan's
	 * order, never empty (Mixmymeals_Meal_Plan::clean_eaters()).
	 *
	 * @return string[]
	 */
	public function get_eaters(): array {

		$plan = new Mixmymeals_Meal_Plan( (int) $this->get_parent_plan_id() );

		return Mixmymeals_Meal_Plan::clean_eaters( $this->get_eaters_raw(), $plan->eater_ids() );
	}

	/**
	 * Settles who eats the meal. Checked against the parent plan's people,
	 * so set the plan first; an unknown id is dropped, and a list that ends
	 * up naming everybody — or nobody — is stored as no row, which is
	 * "everybody": a person added to the plan later then eats it too.
	 *
	 * @param array $eaters Ids of the plan's people.
	 * @return void
	 */
	public function set_eaters( array $eaters ): void {

		$ids   = ( new Mixmymeals_Meal_Plan( (int) $this->get_parent_plan_id() ) )->eater_ids();
		$clean = Mixmymeals_Meal_Plan::clean_eaters( $eaters, $ids );

		if ( array_values( $ids ) === $clean ) {
			delete_post_meta( $this->post_id, self::META_EATERS );
		} else {
			update_post_meta( $this->post_id, self::META_EATERS, $clean );
		}

		$this->raise_servings_to_floor();
	}

	/**
	 * Raises the stored servings to what the eaters now eat, where they
	 * fell below it: somebody ticked more, or a larger portion, is more
	 * food cooked. Never lowers them — a surplus is the planner's to keep.
	 *
	 * @return void
	 */
	private function raise_servings_to_floor(): void {

		$min = $this->min_servings();

		if ( $this->get_stored_servings() < $min ) {
			update_post_meta( $this->post_id, 'servings', $min );
		}
	}

	/**
	 * The per-meal portions as stored, unchecked. For
	 * Mixmymeals_Meal_Plan::get_meal_rows(); everything else reads
	 * get_eater_portions().
	 *
	 * @return array<string,mixed>
	 */
	public function get_eater_portions_raw(): array {

		$stored = get_post_meta( $this->post_id, self::META_EATER_PORTIONS, true );

		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * How many portions each eater eats of this meal, id => portions, for
	 * everybody who eats it: their figure for this meal, else their usual.
	 *
	 * @return array<string,float>
	 */
	public function get_eater_portions(): array {

		$plan = new Mixmymeals_Meal_Plan( (int) $this->get_parent_plan_id() );

		return $plan->resolve_portions( $this->get_eater_portions_raw(), $this->get_eaters() );
	}

	/**
	 * Stores how much each eater eats of this meal. Checked against the
	 * parent plan and who eats the meal, so set the plan and the eaters
	 * first; a figure equal to the person's usual amount is not stored, and
	 * neither is an invalid one (Mixmymeals_Meal_Plan::clean_portion_overrides()).
	 *
	 * @param array|string $portions id => portions (or a JSON object); empty
	 *                               returns everybody to their usual amount.
	 * @return void
	 */
	public function set_eater_portions( $portions ): void {

		$plan  = new Mixmymeals_Meal_Plan( (int) $this->get_parent_plan_id() );
		$clean = $plan->clean_portion_overrides( $portions, $this->get_eaters() );

		if ( ! $clean ) {
			delete_post_meta( $this->post_id, self::META_EATER_PORTIONS );
		} else {
			update_post_meta( $this->post_id, self::META_EATER_PORTIONS, $clean );
		}

		$this->raise_servings_to_floor();
	}

	/**
	 * Returns the number of servings for the meal.
	 *
	 * @return int The number of servings (minimum 1).
	 */
	public function get_servings(): int {
		return max( $this->min_servings(), $this->get_stored_servings() );
	}

	/**
	 * The servings as stored, at least one — without the floor of the
	 * eaters. For Mixmymeals_Meal_Plan::get_meal_rows(), which applies the
	 * floor itself from the eaters it has already read.
	 *
	 * @return int
	 */
	public function get_stored_servings(): int {
		return max(
			1,
			intval( get_post_meta( $this->post_id, 'servings', true ) )
		);
	}

	/**
	 * The fewest servings this meal may have: what its eaters eat of it
	 * together, rounded up (Mixmymeals_Meal_Plan::min_servings()). One on a
	 * plan without people.
	 *
	 * @return int
	 */
	public function min_servings(): int {

		$plan = new Mixmymeals_Meal_Plan( (int) $this->get_parent_plan_id() );

		return $plan->min_servings( $this->get_eaters(), $this->get_eater_portions_raw() );
	}

	/**
	 * Servings beyond what the eaters eat (Mixmymeals_Meal_Plan::servings_surplus()).
	 *
	 * @return float
	 */
	public function servings_surplus(): float {

		$plan = new Mixmymeals_Meal_Plan( (int) $this->get_parent_plan_id() );

		return $plan->servings_surplus( $this->get_servings(), $this->get_eater_portions() );
	}

	/**
	 * The servings the meal page shows: the stored ones, or — under
	 * ?mmm_persons_to_add, carried over from the plan page — those of the same meal in
	 * the plan with that difference applied.
	 *
	 * Asks the plan rather than adding the number itself, because the rule
	 * (at least one, the cap on additions, which values are valid on which
	 * plan) is Mixmymeals_Meal_Plan::with_servings_offset() and
	 * parse_servings_value(), and a meal page that applied it on its own
	 * would sooner or later show another amount than the shopping list.
	 *
	 * @return int
	 */
	public function get_shown_servings(): int {

		$plan_id = (int) $this->get_parent_plan_id();

		if ( $plan_id <= 0 || ! Mixmymeals_Meal_Plan::has_servings_param() ) {
			return $this->get_servings();
		}

		return $this->servings_with_offset( ( new Mixmymeals_Meal_Plan( $plan_id ) )->requested_servings_offset() );
	}

	/**
	 * The servings of this meal once a difference is applied to its plan —
	 * the plan's own row of with_servings_offset(), so the floor (its
	 * eaters) and the cap on additions are the plan's rule and nobody
	 * else's. The meal page's servings field asks it for every value it
	 * offers.
	 *
	 * @param int $offset Difference, as ?mmm_persons_to_add carries it.
	 * @return int
	 */
	public function servings_with_offset( int $offset ): int {

		$plan_id = (int) $this->get_parent_plan_id();

		if ( 0 === $offset || $plan_id <= 0 ) {
			return $this->get_servings();
		}

		foreach ( ( new Mixmymeals_Meal_Plan( $plan_id ) )->with_servings_offset( $offset )->get_meal_rows() as $row ) {
			if ( (int) $row['meal_id'] === $this->post_id ) {
				return (int) $row['servings'];
			}
		}

		return $this->get_servings();
	}

	/**
	 * Sets the number of servings for the meal.
	 *
	 * @param int $servings The number of servings to set.
	 *
	 * @return void
	 */
	public function set_servings( int $servings ): void {
		// Never below what its eaters eat; set the eaters and their portions
		// first where both change, or this floors against the old ones (the
		// read floors again either way).
		update_post_meta(
			$this->post_id,
			'servings',
			max( $this->min_servings(), $servings )
		);
	}

	/**
	 * The meal's own short text.
	 *
	 * A meal is a planning of a recipe, and the same dish gets planned for
	 * different reasons — "cook double, freeze half", "the evening before the
	 * shopping trip". That belongs to the planning, not to the dish, which is
	 * why it lives here and **never falls back to the recipe's excerpt**: an
	 * empty one means nobody said anything about *this* planning, and
	 * borrowing the recipe's would put the same sentence on every planning of
	 * it and read as if somebody had.
	 *
	 * Stored in post_excerpt rather than in meta: it is a short piece of prose
	 * about the post, which is exactly what that column is for, and it comes
	 * with WordPress's own storage, export and revisions. Note that
	 * `supports` deliberately does **not** list `excerpt` — that would add
	 * core's own Excerpt box beside the field on the meal screen, two controls
	 * writing one value, which is the race the diet box was removed over.
	 *
	 * This replaces a `note` meta whose getter, setter, admin field and save
	 * branch were *all* commented out — one field short of existing. Whatever
	 * rows it left behind are moved here once by migrate_notes().
	 *
	 * @return string Empty when nothing was said.
	 */
	public function get_text(): string {

		$post = get_post( $this->post_id );

		return $post ? (string) $post->post_excerpt : '';
	}

	/**
	 * Sets the meal's own short text. An empty string clears it.
	 *
	 * @param string $text The text.
	 * @return void
	 */
	public function set_text( string $text ): void {

		// This class has no is_valid(); the post type is the check, and it
		// keeps a stray ID from writing an excerpt onto some other post.
		if ( 'mmm_meal' !== get_post_type( $this->post_id ) ) {
			return;
		}

		wp_update_post(
			array(
				'ID'           => $this->post_id,
				'post_excerpt' => sanitize_textarea_field( $text ),
			)
		);
	}

	/**
	 * Moves any leftover `note` meta into the text, once.
	 *
	 * Nothing has written that meta for a long time — every one of its call
	 * sites is commented out — but a meal from before then may still carry a
	 * sentence somebody typed, and it has been invisible ever since. Only
	 * fills an empty text, and leaves the meta where it is: it is inert once
	 * the text is set, and it is the way back if this ever picked the wrong
	 * one.
	 *
	 * @return int How many meals were given a text.
	 */
	public static function migrate_notes(): int {

		if ( get_option( self::TEXT_MIGRATION_OPTION ) === self::TEXT_MIGRATION_VERSION ) {
			return 0;
		}

		$meal_ids = get_posts(
			array(
				'post_type'      => 'mmm_meal',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'     => array(
					array(
						'key'     => 'note',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		$moved = 0;

		foreach ( $meal_ids as $meal_id ) {

			$note = trim( (string) get_post_meta( (int) $meal_id, 'note', true ) );
			$post = get_post( (int) $meal_id );

			if ( '' === $note || ! $post || '' !== trim( (string) $post->post_excerpt ) ) {
				continue;
			}

			( new self( (int) $meal_id ) )->set_text( $note );
			++$moved;
		}

		update_option( self::TEXT_MIGRATION_OPTION, self::TEXT_MIGRATION_VERSION );

		return $moved;
	}

	/**
	 * Returns the parent plan ID of the meal.
	 *
	 * @return int The parent plan ID.
	 */
	public function get_parent_plan_id(): int {
		return wp_get_post_parent_id( $this->post_id );
	}

	/**
	 * Sets the parent plan ID for the meal.
	 *
	 * @param int $parent_id The parent plan ID to set.
	 *
	 * @return void
	 */
	public function set_parent_plan_id( int $parent_id ): void {

		mixmymeals_write_to_log( 'Start setting parent ID...' );

		$new_parent = intval( $parent_id );

		mixmymeals_write_to_log( '... New Parent: ' . $new_parent );

		/*
		// Example of direct database update (commented out).
		global $wpdb;

		$wpdb->update(
			$wpdb->posts,
			['post_parent' => $new_parent ?: 0],
			['ID' => $this->post_id],
			['%d'],
			['%d']
		);
		*/

		wp_update_post(
			array(
				'ID'          => $this->post_id,
				'post_parent' => $new_parent ?: 0,
			)
		);

		mixmymeals_write_to_log( '... Done.' );

		clean_post_cache( $this->post_id );
	}

	/*
	----------------------------------------------------------
	SAVE META FROM POST
	----------------------------------------------------------
	*/

	/**
	 * Example: Save meta data from $_POST (commented out).
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return void
	 */
	/*
	public function save_meta_from_post_wrapper( $post_id ): void {
		if ( $post_id !== $this->post_id ) return;

		$post_data = $_POST;

		$this->save_meta_from_post( $post_data );
	}
	*/

	/*
	public function save_meta_from_post( $post_data ): void {

		if ( isset( $post_data['recipe_id'] ) ) {
			mixmymeals_write_to_log( 'Setting Recipe ID.' );
			$this->set_recipe_id( $post_data['recipe_id'] );
		}

		if ( isset( $post_data['servings'] ) ) {
			mixmymeals_write_to_log( 'Setting Servings.' );
			$this->set_servings( $post_data['servings'] );
		}

		if ( isset( $post_data['note'] ) ) {
			mixmymeals_write_to_log( 'Setting Note.' );
			$this->set_note( $post_data['note'] );
		}

		if ( isset( $post_data['mmm_parent_id'] ) ) {
			mixmymeals_write_to_log( 'Setting Parent ID.' );
			$this->set_parent_plan_id( $post_data['mmm_parent_id'] );
		}
	}
	*/

	/*
	----------------------------------------------------------
	STATIC HELPERS
	----------------------------------------------------------
	*/

	/**
	 * Retrieves all meals for a given meal plan (commented out).
	 *
	 * @param int $plan_id The meal plan ID.
	 *
	 * @return WP_Post[] Array of meal posts.
	 */
	/*
	public static function get_meals_for_plan( $plan_id ): array {
		return get_children([
			'post_parent' => intval( $plan_id ),
			'post_type'   => 'mmm_meal',
			'post_status' => 'publish',
			'orderby'     => 'menu_order',
			'order'       => 'ASC',
		]);
	}
	*/

	/*
	public function get_weekday() {
		return get_post_meta( $this->post_id, '_mmm_weekday', true );
	}
	*/

	/*
	public function set_weekday( $weekday ) {
		update_post_meta( $this->post_id, '_mmm_weekday', $weekday );
	}
	*/

	/*
	public function get_date() {
		return get_post_meta( $this->post_id, 'meal_date', true ); // e.g. field 'meal_date'
	}
	*/

	/*
	public function set_date( $date ): void {

		if ( ! empty( $date ) ) {
			$timestamp = strtotime( $date );
			if ( $timestamp ) {
				update_post_meta( $this->post_id, 'meal_date', date( 'Y-m-d', $timestamp ) );
			}
		} else {
			delete_post_meta( $this->post_id, 'meal_date' );
		}
	}
	*/

	/**
	 * Returns the type of the meal.
	 *
	 * @return string The meal type.
	 */
	public function get_type(): string {
		return get_post_meta( $this->post_id, 'mmm_meal_type', true );
	}

	/**
	 * Sets the type of the meal.
	 *
	 * @param string $type The meal type to set.
	 *
	 * @return void
	 */
	public function set_type( string $type ): void {
		// $allowed = [ 'breakfast', 'lunch', 'dinner', 'snack', 'other' ];

		$type = sanitize_text_field( $type );

		// if ( in_array( $type, $allowed, true ) ) {
		update_post_meta( $this->post_id, 'mmm_meal_type', $type );
		// } else {
		// delete_post_meta( $this->post_id, 'mmm_meal_type' );
		// }
	}

	/**
	 * Returns the day of the week for the meal.
	 *
	 * @return int|null The day (1–7) or null if not set.
	 */
	public function get_day(): ?int {
		$day = get_post_meta( $this->post_id, '_mmm_day', true );

		return $day ? intval( $day ) : null;
	}


	/**
	 * Sets the day of the week for the meal.
	 *
	 * @param int $day The day (1–7).
	 *
	 * @return void
	 */
	public function set_day( int $day ): void {

		$day = intval( $day );

		// Only store values between 1–7.
		if ( $day >= 1 && $day <= 7 ) {
			update_post_meta( $this->post_id, '_mmm_day', $day );
		} else {
			delete_post_meta( $this->post_id, '_mmm_day' );
		}
	}

	/*
	 * Returns an array to map meal type code to plaintext.
	 */
	/** @return array<string, string> */
	public static function get_plaintext_meal_type_map(): array {
		return array(
			'breakfast' => __( 'Breakfast', 'mixmymeals' ),
			'lunch'     => __( 'Lunch', 'mixmymeals' ),
			'dinner'    => __( 'Dinner', 'mixmymeals' ),
			'snack'     => __( 'Snack', 'mixmymeals' ),
			'other'     => __( 'Other', 'mixmymeals' ),
		);
	}

}