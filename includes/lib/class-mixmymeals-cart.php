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
	 * Where the planner keeps its people: user meta for a logged-in visitor,
	 * a transient beside the cart's (same key, this suffix) for a guest.
	 *
	 * Kept apart from the items because the items are a list that is
	 * counted, sliced and merged as it is. The planner names people by
	 * number only ("Person 2"): no name is ever asked for or stored here,
	 * so a shared planner and a plan saved from it carry none either.
	 */
	const EATERS_META_KEY   = 'mixmymeals_cart_eaters';
	const EATERS_KEY_SUFFIX = '_eaters';

	/** Meals a planner holds while the setting says nothing. */
	const DEFAULT_MAX_ITEMS = 30;

	/** The most the setting's field accepts. */
	const MAX_ITEMS_CEILING = 100;

	/** Shared copies one address may create per hour (share_rate_limit()). */
	const SHARE_RATE_LIMIT = 20;

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

		// The cookie is the visitor's to edit, and its value becomes part of
		// a storage key: anything that is not a UUID of ours is no key.
		if ( ! wp_is_uuid( $uuid, 4 ) ) {
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

	/**
	 * The planner's people, as Mixmymeals_Meal_Plan::sanitize_eaters()
	 * gives them, without names. Empty while the planner lists nobody —
	 * which is one person eating everything.
	 *
	 * @return array<int, array{id:string, name:string, profile:string}>
	 */
	public function get_eaters(): array {

		$data = $this->is_logged_in()
			? get_user_meta( get_current_user_id(), self::EATERS_META_KEY, true )
			: get_transient( $this->get_anon_key() . self::EATERS_KEY_SUFFIX );

		return self::strip_eater_names( Mixmymeals_Meal_Plan::sanitize_eaters( $data ) );
	}

	/**
	 * Stores the planner's people, names dropped. A list of one is no list.
	 *
	 * @param array $eaters People.
	 * @return void
	 */
	public function save_eaters( array $eaters ): void {

		$eaters = self::strip_eater_names( Mixmymeals_Meal_Plan::sanitize_eaters( $eaters ) );

		if ( count( $eaters ) <= 1 ) {
			$this->clear_eaters();
			return;
		}

		if ( $this->is_logged_in() ) {
			update_user_meta( get_current_user_id(), self::EATERS_META_KEY, $eaters );
			return;
		}

		set_transient( $this->get_anon_key() . self::EATERS_KEY_SUFFIX, $eaters, self::EXPIRATION );
	}

	/**
	 * The planner as a plan: its items as meal rows, with its people.
	 * What every live figure, list and note of the planner is read from.
	 *
	 * @return Mixmymeals_Meal_Plan
	 */
	public function as_plan(): Mixmymeals_Meal_Plan {

		return Mixmymeals_Meal_Plan::from_meal_rows( $this->get_items(), $this->get_eaters() );
	}

	/**
	 * Forgets the planner's people.
	 *
	 * @return void
	 */
	private function clear_eaters(): void {

		if ( $this->is_logged_in() ) {
			delete_user_meta( get_current_user_id(), self::EATERS_META_KEY );
			return;
		}

		delete_transient( $this->get_anon_key() . self::EATERS_KEY_SUFFIX );
	}

	/**
	 * The ids of the planner's people, in order — one, DEFAULT_EATER, while
	 * it lists nobody.
	 *
	 * @return string[]
	 */
	public function eater_ids(): array {

		return Mixmymeals_Meal_Plan::from_meal_rows( array(), $this->get_eaters() )->eater_ids();
	}

	/**
	 * A people list without names — the planner's, a shared snapshot's, and
	 * a plan's on its way into a planner.
	 *
	 * @param array $eaters Sanitised people.
	 * @return array
	 */
	public static function strip_eater_names( array $eaters ): array {

		foreach ( $eaters as $i => $person ) {
			$eaters[ $i ]['name'] = '';
		}

		return $eaters;
	}

	/**
	 * Who eats a planner item, as it is stored on the item: the ids, or an
	 * empty list for everybody — so an item from before the field and an
	 * item eaten by all are the same thing.
	 *
	 * @param mixed    $eaters Raw ids (array, or a comma-separated string from the planner).
	 * @param string[] $ids    The planner's ids.
	 * @return string[]
	 */
	public static function clean_item_eaters( $eaters, array $ids ): array {

		if ( is_string( $eaters ) ) {
			$eaters = '' === $eaters ? array() : explode( ',', $eaters );
		}

		$clean = Mixmymeals_Meal_Plan::clean_eaters( $eaters, $ids );

		return array_values( $ids ) === $clean ? array() : $clean;
	}

	/**
	 * How much each eater eats of a planner item, as it is stored on the
	 * item: only valid figures for people who eat it, only where they
	 * differ from what that person usually eats
	 * (Mixmymeals_Meal_Plan::clean_portion_overrides()). Empty is everybody
	 * at their usual amount.
	 *
	 * @param mixed $portions Raw id => portions, or a JSON object string.
	 * @param mixed $eaters   The item's eaters as stored (empty = all).
	 * @return array<string,float>
	 */
	public function clean_item_portions( $portions, $eaters ): array {

		$plan = Mixmymeals_Meal_Plan::from_meal_rows( array(), $this->get_eaters() );

		return $plan->clean_portion_overrides( $portions, Mixmymeals_Meal_Plan::clean_eaters( (array) $eaters, $plan->eater_ids() ) );
	}

	/**
	 * A planner item's servings and what they say about its eaters: the
	 * floor (Mixmymeals_Meal_Plan::min_servings()), the servings kept to
	 * it, and the surplus beyond what is eaten (servings_surplus()).
	 *
	 * @param array $item Cart item.
	 * @return array{min:int, servings:int, surplus:float}
	 */
	public function item_servings( array $item ): array {

		$plan     = Mixmymeals_Meal_Plan::from_meal_rows( array(), $this->get_eaters() );
		$eaters   = Mixmymeals_Meal_Plan::clean_eaters( (array) ( $item['eaters'] ?? array() ), $plan->eater_ids() );
		$portions = $plan->resolve_portions( (array) ( $item['portions'] ?? array() ), $eaters );
		$min      = $plan->min_servings( $eaters, (array) ( $item['portions'] ?? array() ) );
		$servings = max( $min, (int) ( $item['servings'] ?? 1 ) );

		return array(
			'min'      => $min,
			'servings' => $servings,
			'surplus'  => $plan->servings_surplus( $servings, $portions ),
		);
	}

	/**
	 * How many meals a planner, and a plan saved from it, may hold.
	 *
	 * The one reader of the setting. It used to be read in three places with
	 * three defaults — 30 here, "no limit" a few lines further down and "no
	 * limit" again where a plan is saved — so a stored 0 meant one meal in
	 * the planner and any number in a forged save. Empty, zero and anything
	 * else that is no count mean the default; the field's own maximum caps it.
	 *
	 * @return int
	 */
	public static function max_items(): int {

		$max = (int) get_option( 'mixmymeals_max_items_in_cart', 0 );

		if ( $max < 1 ) {
			return self::DEFAULT_MAX_ITEMS;
		}

		return min( $max, self::MAX_ITEMS_CEILING );
	}

	/**
	 * Whether a visitor may plan this recipe: a published recipe, nothing
	 * else. Planning shows a recipe's title, ingredients and figures, and a
	 * saved plan shows its steps — a draft or private recipe planned by its
	 * ID would be published that way.
	 *
	 * @param int $recipe_id Post ID.
	 * @return bool
	 */
	public static function recipe_is_plannable( int $recipe_id ): bool {

		return $recipe_id > 0
			&& 'mmm_recipe' === get_post_type( $recipe_id )
			&& 'publish' === get_post_status( $recipe_id );
	}

	/**
	 * Servings as a visitor may set them: at least one, at most
	 * Mixmymeals_Meal_Plan::SERVINGS_MEAL_MAX — or what the item already
	 * has, where a plan loaded into the planner came with more.
	 *
	 * @param mixed $servings Raw value.
	 * @param int   $current  Servings the item has now (0 = new).
	 * @return int
	 */
	public static function clean_servings( $servings, int $current = 0 ): int {

		$cap = max( Mixmymeals_Meal_Plan::SERVINGS_MEAL_MAX, $current );

		return max( 1, min( $cap, (int) $servings ) );
	}

	/**
	 * A meal type as a visitor may set it: one of the known types, or none.
	 *
	 * @param mixed $type Raw value.
	 * @return string
	 */
	public static function clean_type( $type ): string {

		$type = is_string( $type ) ? sanitize_key( $type ) : '';

		return array_key_exists( $type, Mixmymeals_Meal::get_plaintext_meal_type_map() ) ? $type : '';
	}

	/**
	 * A weekday as a visitor may set it: 1–7, or none.
	 *
	 * @param mixed $day Raw value.
	 * @return int|null
	 */
	public static function clean_day( $day ): ?int {

		$day = is_scalar( $day ) ? (int) $day : 0;

		return ( $day >= 1 && $day <= 7 ) ? $day : null;
	}

	/**
	 * Counts one shared copy against the caller's address and says whether
	 * it is still within the hourly limit (filter
	 * `mixmymeals_share_rate_limit_per_hour`, 0 = no limit). Every copy is a
	 * row kept for SHARE_EXPIRATION, and the nonce a guest needs is on every
	 * page. The address is kept as a keyed hash only.
	 *
	 * @return bool
	 */
	private static function share_within_rate_limit(): bool {

		$limit = (int) apply_filters( 'mixmymeals_share_rate_limit_per_hour', self::SHARE_RATE_LIMIT );

		if ( $limit <= 0 ) {
			return true;
		}

		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key   = 'mmm_rl_share_ip_' . wp_hash( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= $limit ) {
			return false;
		}

		set_transient( $key, $count + 1, HOUR_IN_SECONDS );

		return true;
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
		?string $diet = null,
		?array $eaters = null,
		?array $portions = null
	): bool {

		if ( $recipe_id <= 0 ) {
			return false;
		}

		$items = $this->get_items();

		if ( count( $items ) >= self::max_items() ) {
			return false;
		}

		$item_eaters = self::clean_item_eaters( $eaters ?? array(), $this->eater_ids() );

		$items[] = array(
			'recipe_id' => $recipe_id,
			'servings'  => $servings ?? 1,
			'type'      => $type ?? '',
			'day'       => $day,
			//'note'      => $note ?? '',
			'diet'      => self::clean_diet( $recipe_id, (string) $diet ),
			// Empty is everybody: a recipe planned from its page is eaten
			// by all until somebody says otherwise in the planner.
			'eaters'    => $item_eaters,
			// Only what differs from each person's usual amount.
			'portions'  => $this->clean_item_portions( $portions ?? array(), $item_eaters ),
		);

		// Never fewer servings than its eaters eat.
		$last                       = array_key_last( $items );
		$items[ $last ]['servings'] = $this->item_servings( $items[ $last ] )['servings'];

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

		// Clearing the planner starts over, people included.
		$this->clear_eaters();

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

		if ( ! wp_is_uuid( $uuid, 4 ) ) {
			return;
		}

		$key = self::TRANSIENT_PREFIX . $uuid;

		$guest_items = get_transient( $key );
		if ( ! is_array( $guest_items ) || empty( $guest_items ) ) {
			return;
		}

		$user_items = get_user_meta( $user_id, self::META_KEY, true );
		$user_items = is_array( $user_items ) ? $user_items : array();

		$merged_items = array_merge( $user_items, $guest_items );

		// Limit auch beim Merge erzwingen
		$max = self::max_items();
		if ( count( $merged_items ) > $max ) {
			$merged_items = array_slice( $merged_items, 0, $max );
		}

		update_user_meta( $user_id, self::META_KEY, $merged_items );

		delete_transient( $key );

		// The guest's people come along where the account lists none; where
		// it does, the guest's items keep only the ids it knows, and the
		// rest read as "everybody".
		$guest_eaters = get_transient( $key . self::EATERS_KEY_SUFFIX );
		$user_eaters  = get_user_meta( $user_id, self::EATERS_META_KEY, true );

		if ( is_array( $guest_eaters ) && $guest_eaters && empty( $user_eaters ) ) {
			update_user_meta( $user_id, self::EATERS_META_KEY, self::strip_eater_names( Mixmymeals_Meal_Plan::sanitize_eaters( $guest_eaters ) ) );
		}

		delete_transient( $key . self::EATERS_KEY_SUFFIX );
	}

	public function update_item_by_index( int $index, array $data ): bool {

		$items = $this->get_items();

		if ( ! isset( $items[ $index ] ) ) {
			return false;
		}

		$allowed_keys = array( 'servings', 'type', 'day', 'diet', 'eaters', 'portions' );

		foreach ( $allowed_keys as $key ) {
			if ( array_key_exists( $key, $data ) ) {
				$items[ $index ][ $key ] = $data[ $key ];
			}
		}

		if ( array_key_exists( 'diet', $data ) ) {
			$items[ $index ]['diet'] = self::clean_diet( (int) ( $items[ $index ]['recipe_id'] ?? 0 ), (string) $data['diet'] );
		}

		if ( array_key_exists( 'eaters', $data ) ) {
			$items[ $index ]['eaters'] = self::clean_item_eaters( $data['eaters'], $this->eater_ids() );
		}

		// After the eaters: a figure for somebody who no longer eats it goes.
		$items[ $index ]['portions'] = $this->clean_item_portions( $items[ $index ]['portions'] ?? array(), $items[ $index ]['eaters'] ?? array() );

		// And the servings never below what the eaters now eat.
		$items[ $index ]['servings'] = $this->item_servings( $items[ $index ] )['servings'];

		$this->save_items( $items );
		return true;
	}

	/**
	 * The diet of a planner item — the same rule Mixmymeals_Meal::set_diet()
	 * keeps, so the planner cannot hold a state the saved plan would not:
	 * where the recipe offers a choice the item always holds one (the
	 * requested one if valid, else the recipe's first), where it offers none
	 * it holds ''.
	 *
	 * @param int    $recipe_id The item's recipe.
	 * @param string $diet      Requested choice.
	 * @return string
	 */
	public static function clean_diet( int $recipe_id, string $diet ): string {

		$choices = ( new Mixmymeals_Recipe( $recipe_id ) )->get_diet_choices();

		if ( ! $choices ) {
			return '';
		}

		$diet = sanitize_key( $diet );
		$diet = Mixmymeals_Diet::NONE === $diet ? $diet : Mixmymeals_Diet::resolve( $diet );

		return in_array( $diet, $choices, true ) ? $diet : $choices[0];
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
			'mmm_cart_eaters'         => 'mmm_cart_eaters_ajax_callback',
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

		if ( ! self::recipe_is_plannable( $recipe_id ) ) {
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
			? self::clean_servings( sanitize_text_field( wp_unslash( $_POST['servings'] ) ) )
			: 1;

		$type = isset( $_POST['type'] )
			? self::clean_type( sanitize_text_field( wp_unslash( $_POST['type'] ) ) )
			: '';

		$day = isset( $_POST['day'] )
			? self::clean_day( sanitize_text_field( wp_unslash( $_POST['day'] ) ) )
			: null;

		// Optional; add() drops a diet the recipe cannot be cooked as.
		$diet = isset( $_POST['diet'] ) ? sanitize_key( wp_unslash( $_POST['diet'] ) ) : '';

		$cart = new Mixmymeals_Cart();
		$ok   = $cart->add( $recipe_id, $servings, $type, $day, $diet );

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

		/*
		 * Checked like mmm_add_to_cart checks them. This handler used to
		 * store any servings, any text as a meal type and any number as a
		 * day, and a `note` nothing reads: a visitor cannot give a meal a
		 * text (Mixmymeals_Meal::set_text() is an editor's), so none is
		 * kept here, and one stored by an earlier version is dropped.
		 */
		$item = $items[ $index ];
		unset( $item['note'] );

		$item['servings'] = isset( $_POST['servings'] )
			? self::clean_servings( sanitize_text_field( wp_unslash( $_POST['servings'] ) ), (int) ( $item['servings'] ?? 0 ) )
			: $item['servings'] ?? 1;
		$item['type']     = isset( $_POST['type'] )
			? self::clean_type( sanitize_text_field( wp_unslash( $_POST['type'] ) ) )
			: $item['type'] ?? '';
		$item['day']      = isset( $_POST['day'] )
			? self::clean_day( sanitize_text_field( wp_unslash( $_POST['day'] ) ) )
			: $item['day'] ?? null;
		$item['diet']     = isset( $_POST['diet'] )
			? self::clean_diet( (int) ( $item['recipe_id'] ?? 0 ), sanitize_key( wp_unslash( $_POST['diet'] ) ) )
			: $item['diet'] ?? '';
		// "p1,p3" from the planner; checked against the planner's people.
		$item['eaters'] = isset( $_POST['eaters'] )
			? self::clean_item_eaters( sanitize_text_field( wp_unslash( (string) $_POST['eaters'] ) ), $cart->eater_ids() )
			: (array) ( $item['eaters'] ?? array() );
		// {"p1":1.5} from the planner; checked after the eaters, since a
		// figure only counts for somebody who eats the item.
		$item['portions'] = $cart->clean_item_portions(
			isset( $_POST['portions'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['portions'] ) ) : (array) ( $item['portions'] ?? array() ),
			$item['eaters']
		);

		// Never fewer servings than its eaters eat — ticking somebody more
		// raises them — and the answer says so, with the note for a
		// surplus, so the page can set both without computing anything.
		$mmm_servings     = $cart->item_servings( $item );
		$item['servings'] = $mmm_servings['servings'];

		// Item zurück in den Warenkorb setzen
		$items[ $index ] = $item;
		$cart->save_items( $items );

		wp_send_json_success( [
			'code'         => 'updated',
			'message'      => __( 'Item updated.', 'mixmymeals' ),
			'item'         => $item,
			'min_servings' => $mmm_servings['min'],
			'surplus'      => Mixmymeals_Meal_Plan::servings_surplus_text( $mmm_servings['surplus'] ),
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

		if ( ! self::share_within_rate_limit() ) {
			wp_send_json_error(
				array(
					'code'    => 'rate_limit',
					'message' => __( 'Too many requests. Please try again later.', 'mixmymeals' ),
				),
				429
			);
		}

		$token = wp_generate_uuid4();

		set_transient(
			self::SHARE_PREFIX . $token,
			array(
				'items'   => $items,
				// Numbers only, as in the planner itself: a shared link
				// carries no names because the planner holds none.
				'eaters'  => self::strip_eater_names( ( new self() )->get_eaters() ),
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
		$cart->save_eaters( (array) ( $shared['eaters'] ?? array() ) );

		foreach ( $shared['items'] as $item ) {

			$recipe_id = intval( $item['recipe_id'] ?? 0 );

			// The recipes are re-checked rather than trusted: one of them may
			// have been unpublished since the link was made.
			if ( ! self::recipe_is_plannable( $recipe_id ) ) {
				continue;
			}

			$cart->add(
				$recipe_id,
				max( 1, intval( $item['servings'] ?? 1 ) ),
				self::clean_type( $item['type'] ?? '' ),
				self::clean_day( $item['day'] ?? null ),
				(string) ( $item['diet'] ?? '' ),
				(array) ( $item['eaters'] ?? array() ),
				(array) ( $item['portions'] ?? array() )
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

		/*
		 * The plan's people come along as numbers: the names an editor gave
		 * them are wp-admin's, and the planner holds none. The ids stay, so
		 * every meal keeps the people it had.
		 */
		$cart->save_eaters(
			array_map(
				static fn( $person ) => array(
					'id'       => $person['id'],
					'profile'  => $person['profile'],
					'portions' => $person['portions'],
				),
				$plan->get_eaters()
			)
		);

		foreach ( $plan->with_servings_offset( $offset )->get_meal_rows() as $row ) {
			$cart->add(
				(int) $row['recipe_id'],
				(int) $row['servings'],
				(string) $row['type'],
				$row['day'],
				(string) ( $row['diet'] ?? '' ),
				(array) ( $row['eaters'] ?? array() ),
				(array) ( $row['portions'] ?? array() )
			);
		}

		wp_send_json_success( array(
			'message' => __( 'Meal plan loaded into the planner.', 'mixmymeals' ),
		) );
	}

	/**
	 * AJAX handler for wp_ajax_mmm_cart_eaters: adds a person to the planner
	 * (`op=add`), removes one (`op=remove`, `id`) or sets how much one
	 * usually eats (`op=portions`, `id`, `portions`).
	 *
	 * A new person gets the next free id and the default profile; the
	 * planner names them by number. Removing one drops them from every
	 * item, and an item left with nobody is eaten by all again. Answers
	 * with the people as the planner shows them.
	 *
	 * @return void
	 */
	public static function mmm_cart_eaters_ajax_callback(): void {

		check_ajax_referer( 'mmm_frontend_nonce', 'nonce' );

		$op     = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';
		$cart   = new self();
		$eaters = $cart->get_eaters();

		if ( ! $eaters ) {
			$eaters = array(
				array(
					'id'      => Mixmymeals_Meal_Plan::DEFAULT_EATER,
					'name'    => '',
					'profile' => Mixmymeals_Meal_Plan::DEFAULT_PROFILE,
				),
			);
		}

		if ( 'add' === $op ) {

			if ( count( $eaters ) >= Mixmymeals_Meal_Plan::EATERS_MAX ) {
				wp_send_json_error(
					array(
						'code'    => 'eaters_max',
						'message' => __( 'No more people can be added to this planner.', 'mixmymeals' ),
					),
					409
				);
			}

			// No id: sanitize_eaters() hands out the next free one.
			$eaters[] = array( 'profile' => Mixmymeals_Meal_Plan::DEFAULT_PROFILE );

		} elseif ( 'remove' === $op ) {

			$id     = isset( $_POST['id'] ) ? sanitize_key( wp_unslash( $_POST['id'] ) ) : '';
			$eaters = array_values( array_filter( $eaters, static fn( $p ) => $p['id'] !== $id ) );

		} elseif ( 'portions' === $op ) {

			$id    = isset( $_POST['id'] ) ? sanitize_key( wp_unslash( $_POST['id'] ) ) : '';
			$value = Mixmymeals_Meal_Plan::normalise_portion( isset( $_POST['portions'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['portions'] ) ) : '' );

			foreach ( $eaters as $i => $person ) {
				if ( $person['id'] === $id ) {
					$eaters[ $i ]['portions'] = $value ?? Mixmymeals_Meal_Plan::DEFAULT_PORTION;
				}
			}
		} else {
			wp_send_json_error( array( 'code' => 'invalid_op' ), 400 );
		}

		$cart->save_eaters( $eaters );

		// Every item keeps only people who are still listed.
		$ids   = $cart->eater_ids();
		$items = $cart->get_items();

		foreach ( $items as $i => $item ) {
			$items[ $i ]['eaters']   = self::clean_item_eaters( (array) ( $item['eaters'] ?? array() ), $ids );
			$items[ $i ]['portions'] = $cart->clean_item_portions( (array) ( $item['portions'] ?? array() ), $items[ $i ]['eaters'] );
			$items[ $i ]['servings'] = $cart->item_servings( $items[ $i ] )['servings'];
		}

		$cart->save_items( $items );

		wp_send_json_success(
			array(
				'eaters' => Mixmymeals_Meal_Plan::from_meal_rows( array(), $cart->get_eaters() )->get_eaters(),
			)
		);
	}

}
