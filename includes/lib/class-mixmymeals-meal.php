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
	 * Returns the note associated with the meal.
	 *
	 * @return string The note text.
	 */
	/*
	public function get_note(): string {
		return get_post_meta( $this->post_id, 'note', true );
	}
	*/

	/**
	 * Sets the note for the meal.
	 *
	 * @param string $note The note text to set.
	 *
	 * @return void
	 */
	/*
	public function set_note( $note ): void {
		update_post_meta(
			$this->post_id,
			'note',
			sanitize_textarea_field( $note )
		);
	}
	*/

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