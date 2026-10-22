<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mixmymeals_Cart {

	const META_KEY         = 'mixmymeals_cart';
	const COOKIE_NAME      = 'mixmymeals_cart_uuid';
	const TRANSIENT_PREFIX = 'mixmymeals_cart_';
	const EXPIRATION       = WEEK_IN_SECONDS;

	/**
	 * Returns true if user is logged in.
	 */
	private function is_logged_in(): bool {
		return is_user_logged_in();
	}

	/**
	 * Returns the storage key for anonymous users.
	 */
	private function get_anon_key(): string {

		$uuid = isset( $_COOKIE[ self::COOKIE_NAME ] )
			? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) )
			: '';

		if ( empty( $uuid ) ) {
			$uuid = wp_generate_uuid4();

			setcookie(
				self::COOKIE_NAME,
				$uuid,
				array(
					'expires'  => time() + self::EXPIRATION,
					'path'     => COOKIEPATH,
					'domain'   => COOKIE_DOMAIN,
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);

			// Make the fresh UUID visible to reads within this request.
			$_COOKIE[ self::COOKIE_NAME ] = $uuid;
		}

		return self::TRANSIENT_PREFIX . $uuid;
	}

	/**
	 * Loads cart items from storage.
	 */
	public function get_items(): array {

		if ( $this->is_logged_in() ) {
			$data = get_user_meta( get_current_user_id(), self::META_KEY, true );
			return is_array( $data ) ? $data : array();
		}

		$key  = $this->get_anon_key();
		$data = get_transient( $key );

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Saves cart items to storage.
	 */
	public function save_items( array $items ): void {

		if ( $this->is_logged_in() ) {
			update_user_meta( get_current_user_id(), self::META_KEY, $items );
			return;
		}

		$key = $this->get_anon_key();
		set_transient( $key, $items, self::EXPIRATION );
	}

	private function get_max_items(): int {
		$max = (int) get_option( 'mixmymeals_max_items_in_cart', 30 );
		return max( 1, $max );
	}

	/**
	 * Adds a recipe to the cart.
	 */
	public function add(
		int $recipe_id,
		?int $servings = null,
		?string $type = null,
		?int $day = null,
		//?string $note = null
	): bool {

		if ( $recipe_id <= 0 ) {
			return false;
		}

		$items = $this->get_items();

		$max = (int) get_option('mixmymeals_max_items_in_cart', 0); // 0 = unbegrenzt

		if ($max > 0 && count($items) >= $max) {
			return false; // Limit erreicht
		}

		// Limit prüfen
		if ( count( $items ) >= $this->get_max_items() ) {
			return false;
		}

		$items[] = array(
			'recipe_id' => $recipe_id,
			'servings'  => $servings ?? 1,
			'type'      => $type ?? '',
			'day'       => $day,
			//'note'      => $note ?? '',
		);

		$this->save_items( $items );

		return true;

	}


	/**
	 * Removes an item by index.
	 */
	public function remove_by_index( int $index ): bool {

		$items = $this->get_items();

		if ( isset( $items[ $index ] ) ) {
			unset( $items[ $index ] );
			$items = array_values( $items );
			$this->save_items( $items );
			return true;
		}

		return false;
	}

	/**
	 * Removes all items with a given recipe ID.
	 */
	public function remove_by_recipe_id( int $recipe_id ): void {

		$recipe_id = intval( $recipe_id );
		$items     = $this->get_items();

		$items = array_values(
			array_filter(
				$items,
				static function( $item ) use ( $recipe_id ) {
					return (int) ( $item['recipe_id'] ?? 0 ) !== $recipe_id;
				}
			)
		);

		$this->save_items( $items );
	}

	/**
	 * Clears the entire cart.
	 */
	public function clear(): void {

		if ( $this->is_logged_in() ) {
			delete_user_meta( get_current_user_id(), self::META_KEY );
			return;
		}

		$key = $this->get_anon_key();
		delete_transient( $key );
	}

	/**
	 * Convenience: returns only recipe IDs.
	 */
	public function get_recipe_ids(): array {

		$ids = array();

		foreach ( $this->get_items() as $item ) {
			$ids[] = (int) $item['recipe_id'];
		}

		return $ids;
	}



	public function merge_guest_cart_into_user( int $user_id ): void {

		if ( $user_id <= 0 ) {
			return;
		}

		if ( empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return;
		}

		$uuid = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );
		$key  = self::TRANSIENT_PREFIX . $uuid;

		$guest_items = get_transient( $key );
		if ( ! is_array( $guest_items ) || empty( $guest_items ) ) {
			return;
		}

		$user_items = get_user_meta( $user_id, self::META_KEY, true );
		$user_items = is_array( $user_items ) ? $user_items : array();

		$merged_items = array_merge( $user_items, $guest_items );

		// Limit auch beim Merge erzwingen
		$max = $this->get_max_items();
		if ( count( $merged_items ) > $max ) {
			$merged_items = array_slice( $merged_items, 0, $max );
		}

		update_user_meta( $user_id, self::META_KEY, $merged_items );

		delete_transient( $key );
	}

	public function update_item_by_index( int $index, array $data ): bool {

		$items = $this->get_items();

		if ( ! isset( $items[ $index ] ) ) {
			return false;
		}

		$allowed_keys = array( 'servings', 'type', 'day' );

		foreach ( $allowed_keys as $key ) {
			if ( array_key_exists( $key, $data ) ) {
				$items[ $index ][ $key ] = $data[ $key ];
			}
		}

		$this->save_items( $items );
		return true;
	}

	/**
	 * Registers the cart AJAX endpoints.
	 *
	 * Deliberately static and called once from the bootstrap: this class is
	 * instantiated per use, so hooking in the constructor would register the
	 * same callbacks again on every `new Mixmymeals_Cart()`.
	 *
	 * Moved here from Mixmymeals_Shortcodes — these are write endpoints for the
	 * cart, not shortcodes. Behaviour is unchanged.
	 */
	public static function register_ajax(): void {

		$endpoints = array(
			'mmm_add_to_cart'         => 'mmm_add_recipe_to_cart_ajax_callback',
			'mmm_get_cart_count'      => 'mmm_get_cart_count_ajax_callback',
			'mmm_remove_cart_item'    => 'mmm_remove_cart_item_ajax_callback',
			'mmm_update_cart_item'    => 'mmm_update_cart_item_ajax_callback',
			'mmm_clear_cart'          => 'mmm_clear_cart_ajax_callback',
			'mmm_load_plan_into_cart' => 'mmm_load_plan_into_cart_ajax_callback',
			'mmm_share_cart'          => 'mmm_share_cart_ajax_callback',
			'mmm_load_shared_cart'    => 'mmm_load_shared_cart_ajax_callback',
		);

		foreach ( $endpoints as $action => $method ) {
			add_action( 'wp_ajax_' . $action, array( __CLASS__, $method ) );
			add_action( 'wp_ajax_nopriv_' . $action, array( __CLASS__, $method ) );
		}
	}

	/**
	 * AJAX handler for wp_ajax_mmm_add_to_cart.
	 * Adds a recipe to the current user's or guest's cart.
	 *
	 * @return void
	 */
	public static function mmm_add_recipe_to_cart_ajax_callback(): void {

		check_ajax_referer( 'mmm_frontend_nonce', 'nonce' );

		$recipe_id = isset( $_POST['recipe_id'] )
			? intval( $_POST['recipe_id'] ) : 0;

		if ( $recipe_id <= 0 || get_post_type( $recipe_id ) !== 'mmm_recipe' ) {
			wp_send_json_error( [
				'code'    => 'invalid_recipe_id',
				'message' => __( 'Invalid recipe ID.', 'mixmymeals' ),
			], 400 );
		}


		/*
		$cart = new Mixmymeals_Cart();
		$cart->add( $recipe_id );

		wp_send_json_success( array( 'message' => 'Recipe added to meal plan.' ) );
		*/

		$servings = isset( $_POST['servings'] )
			? max( 1, intval( $_POST['servings'] ) )
			: 1;

		$type = isset( $_POST['type'] )
			? sanitize_text_field( wp_unslash( $_POST['type'] ) )
			: '';
		if ( ! array_key_exists( $type, Mixmymeals_Meal::get_plaintext_meal_type_map() ) ) {
			$type = '';
		}

		$day = isset( $_POST['day'] ) ? intval( $_POST['day'] ) : 0;
		$day = ( $day >= 1 && $day <= 7 ) ? $day : null;

		$cart = new Mixmymeals_Cart();
		$ok   = $cart->add( $recipe_id, $servings, $type, $day );

		if ( ! $ok ) {
			wp_send_json_error( [
				'code'    => 'cart_limit_reached',
				'message' => __( 'Your planner is full.', 'mixmymeals' ),
			], 409 );
		}


		/*
		 * The count travels with the answer so the page can say what just
		 * happened without asking again. Adding a recipe used to change
		 * nothing a reader could see — the only trace was a badge in a header
		 * that is far out of sight on a long archive.
		 */
		wp_send_json_success( [
			'message' => __( 'Recipe added to meal plan.', 'mixmymeals' ),
			'count'   => count( ( new self() )->get_items() ),
		] );

	}

	/**
	 * AJAX: Get cart count
	 */
	public static function mmm_get_cart_count_ajax_callback(): void {

		check_ajax_referer( 'mmm_frontend_nonce', 'nonce' );

		$cart  = new Mixmymeals_Cart();
		$count = count( $cart->get_items() );

		wp_send_json_success(
			array(
				'count' => $count,
			)
		);
	}

	/**
	 * AJAX handler for wp_ajax_mmm_remove_cart_item.
	 * Removes a recipe from the cart by its index.
	 *
	 * @return void
	 */
	public static function mmm_remove_cart_item_ajax_callback(): void {

		check_ajax_referer( 'mmm_frontend_nonce', 'nonce' );

		$index = isset( $_POST['index'] ) ? intval( $_POST['index'] ) : - 1;

		if ( $index < 0 ) {
			wp_send_json_error( [
				'code'    => 'invalid_index',
				'message' => __( 'Invalid index.', 'mixmymeals' ),
			], 400 );
		}

		$cart    = new Mixmymeals_Cart();
		$success = $cart->remove_by_index( $index );

		if ( $success ) {
			wp_send_json_success( [
				'code'    => 'removed',
				'message' => __( 'Recipe removed.', 'mixmymeals' ),
			] );
		} else {
			wp_send_json_error( [
				'code'    => 'remove_failed',
				'message' => __( 'Could not remove recipe.', 'mixmymeals' ),
			], 500 );
		}

	}

	/**
	 * AJAX handler for wp_ajax_mmm_clear_cart.
	 * Clears all items from the cart.
	 *
	 * @return void
	 */
	public static function mmm_clear_cart_ajax_callback(): void {
		check_ajax_referer( 'mmm_frontend_nonce', 'nonce' );

		$cart = new Mixmymeals_Cart();
		$cart->clear();

		wp_send_json_success( array( 'message' => __( 'Planner cleared.', 'mixmymeals' ) ) );
	}

	/**
	 * AJAX handler for wp_ajax_mmm_update_cart_item.
	 * Updates the servings, type, or day of a cart item by its index.
	 *
	 * @return void
	 */
	public static function mmm_update_cart_item_ajax_callback(): void {

		check_ajax_referer( 'mmm_frontend_nonce', 'nonce' );

		$index = isset( $_POST['index'] ) ? intval( $_POST['index'] ) : - 1;
		if ( $index < 0 ) {
			wp_send_json_error( [
				'code'    => 'invalid_index',
				'message' => __( 'Invalid index.', 'mixmymeals' ),
			], 400 );
		}

		$cart  = new Mixmymeals_Cart();
		$items = $cart->get_items();

		if ( ! isset( $items[ $index ] ) ) {
			wp_send_json_error( [
				'code'    => 'item_not_found',
				'message' => __( 'Item not existing.', 'mixmymeals' ),
			], 404 );
		}

		$item             = $items[ $index ];
		$item['servings'] = isset( $_POST['servings'] )
			? intval( $_POST['servings'] ) : $item['servings'] ?? 1;
		$item['type']     = isset( $_POST['type'] )
			? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : $item['type'] ?? '';
		$item['note']     = isset( $_POST['note'] )
			? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : $item['note'] ?? '';
		$item['day']      = isset( $_POST['day'] ) ? intval( $_POST['day'] )
			: $item['day'] ?? null;

		// Item zurück in den Warenkorb setzen
		$items[ $index ] = $item;
		$cart->save_items( $items );

		wp_send_json_success( [
			'code'    => 'updated',
			'message' => __( 'Item updated.', 'mixmymeals' ),
			'item'    => $item,
		] );
	}

	/** Prefix of a shared snapshot. */
	const SHARE_PREFIX = 'mmm_cart_share_';

	/** How long a shared link stays good. */
	const SHARE_EXPIRATION = 30 * DAY_IN_SECONDS;

	/**
	 * AJAX: makes a shareable copy of the planner as it stands.
	 *
	 * **A snapshot, not the cart itself.** The obvious shortcut is to put the
	 * cart's own UUID in the link — but that cookie value is the live key: a
	 * link holder would keep reading the sharer's planner as it changed, and
	 * anything that later wrote through it would write into somebody else's.
	 * A copy under its own token can only ever say what the planner looked
	 * like at the moment it was shared, and it ages out on its own.
	 *
	 * @return void
	 */
	public static function mmm_share_cart_ajax_callback(): void {

		check_ajax_referer( 'mmm_frontend_nonce', 'nonce' );

		$items = ( new self() )->get_items();

		if ( empty( $items ) ) {
			wp_send_json_error(
				array( 'message' => __( 'There is nothing in the planner to share.', 'mixmymeals' ) ),
				400
			);
		}

		$token = wp_generate_uuid4();

		set_transient(
			self::SHARE_PREFIX . $token,
			array(
				'items'   => $items,
				'created' => time(),
			),
			self::SHARE_EXPIRATION
		);

		wp_send_json_success( array( 'token' => $token ) );
	}

	/**
	 * AJAX: copies a shared snapshot into this visitor's own planner.
	 *
	 * Copies — the visitor keeps their own cookie and their own cart. Nothing
	 * here hands out the other planner's key, and nothing written afterwards
	 * reaches it.
	 *
	 * @return void
	 */
	public static function mmm_load_shared_cart_ajax_callback(): void {

		check_ajax_referer( 'mmm_frontend_nonce', 'nonce' );

		$token = isset( $_POST['token'] )
			? sanitize_text_field( wp_unslash( $_POST['token'] ) )
			: '';

		// A uuid4 and nothing else, so a crafted token cannot reach for
		// another transient.
		if ( ! wp_is_uuid( $token, 4 ) ) {
			wp_send_json_error( array( 'message' => __( 'This link is not valid.', 'mixmymeals' ) ), 400 );
		}

		$shared = get_transient( self::SHARE_PREFIX . $token );

		if ( ! is_array( $shared ) || empty( $shared['items'] ) ) {
			wp_send_json_error(
				array( 'message' => __( 'This shared planner is no longer available.', 'mixmymeals' ) ),
				404
			);
		}

		$cart = new self();
		$cart->clear();

		foreach ( $shared['items'] as $item ) {

			$recipe_id = intval( $item['recipe_id'] ?? 0 );

			// The recipes are re-checked rather than trusted: one of them may
			// have been unpublished since the link was made.
			if ( $recipe_id <= 0 || get_post_type( $recipe_id ) !== 'mmm_recipe' ) {
				continue;
			}

			$cart->add(
				$recipe_id,
				max( 1, intval( $item['servings'] ?? 1 ) ),
				sanitize_text_field( (string) ( $item['type'] ?? '' ) ),
				isset( $item['day'] ) ? intval( $item['day'] ) : null
			);
		}

		wp_send_json_success(
			array(
				'message' => __( 'Shared planner loaded.', 'mixmymeals' ),
				'count'   => count( $cart->get_items() ),
			)
		);
	}

	/**
	 * AJAX handler for wp_ajax_mmm_load_plan_into_cart.
	 * Clears the current cart and loads all meals from the specified meal plan.
	 *
	 * @return void
	 */
	public static function mmm_load_plan_into_cart_ajax_callback(): void {

		check_ajax_referer( 'mmm_frontend_nonce', 'nonce' );

		$meal_plan_id = intval( $_POST['meal_plan_id'] ?? 0 );

		$post = get_post( $meal_plan_id );
		if ( ! $post || $post->post_type !== 'mmm_meal_plan' || $post->post_status !== 'publish' ) {
			wp_send_json_error( array( 'message' => __( 'Meal Plan not found.', 'mixmymeals' ) ) );
		}

		$plan = new Mixmymeals_Meal_Plan( $meal_plan_id );

		/*
		 * From the plan page's servings field, where the link next to it was
		 * used: the planner starts from what the reader was looking at, not
		 * from the stored plan. Checked by the plan, like everywhere else;
		 * anything invalid loads the plan as stored.
		 */
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated by parse_servings_value().
		$persons = isset( $_POST['persons_to_add'] ) ? wp_unslash( $_POST['persons_to_add'] ) : '';
		$offset  = (int) $plan->parse_servings_value( is_string( $persons ) ? substr( $persons, 0, 8 ) : '' );

		$cart = new Mixmymeals_Cart();
		$cart->clear();

		foreach ( $plan->with_servings_offset( $offset )->get_meal_rows() as $row ) {
			$cart->add(
				(int) $row['recipe_id'],
				(int) $row['servings'],
				(string) $row['type'],
				$row['day']
			);
		}

		wp_send_json_success( array(
			'message' => __( 'Meal plan loaded into the planner.', 'mixmymeals' ),
		) );
	}

}
