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
				time() + self::EXPIRATION,
				COOKIEPATH,
				COOKIE_DOMAIN,
				is_ssl(),
				true
			);
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

		$allowed_keys = array( 'servings', 'type', 'day' /*, 'note'*/ );

		foreach ( $allowed_keys as $key ) {
			if ( array_key_exists( $key, $data ) ) {
				$items[ $index ][ $key ] = $data[ $key ];
			}
		}

		$this->save_items( $items );
		return true;
	}


}