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
	 * Returns the number of servings for the meal.
	 *
	 * @return int The number of servings (minimum 1).
	 */
	public function get_servings(): int {
		return max(
			1,
			intval( get_post_meta( $this->post_id, 'servings', true ) )
		);
	}

	/**
	 * Sets the number of servings for the meal.
	 *
	 * @param int $servings The number of servings to set.
	 *
	 * @return void
	 */
	public function set_servings( int $servings ): void {
		update_post_meta(
			$this->post_id,
			'servings',
			max( 1, $servings )
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