<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Mixmymeals_Meal {

	private $post_id;

	public function __construct( $post_id ) {
		$this->post_id = $post_id;
	}

	/* ---------------------------------------------------------- */
	/* BASIC META HANDLING                                        */
	/* ---------------------------------------------------------- */

	public function get_recipe_id(): int {
		return (int) get_post_meta( $this->post_id, 'recipe_id', true );
	}

	public function set_recipe_id( $recipe_id ): void {
		update_post_meta( $this->post_id, 'recipe_id', intval( $recipe_id ) );
	}

	public function get_servings(): int {
		return max( 1, intval( get_post_meta( $this->post_id, 'servings', true ) ) );
	}

	public function set_servings( $servings ): void {
		update_post_meta( $this->post_id, 'servings', max( 1, intval( $servings ) ) );
	}

	public function get_note(): string {
		return get_post_meta( $this->post_id, 'note', true );
	}

	public function set_note( $note ): void {
		update_post_meta( $this->post_id, 'note', sanitize_textarea_field( $note ) );
	}

	public function get_parent_plan_id(): int {
		return wp_get_post_parent_id( $this->post_id );
	}

	public function set_parent_plan_id( $parent_id ) {

		write_to_log( 'Start setting parent ID...' );

		$new_parent = intval( $parent_id );

		write_to_log( '... New Parent: ' . $new_parent );

		/*
		global $wpdb;

		$wpdb->update(
			$wpdb->posts,
			['post_parent' => $new_parent ?: 0],
			['ID' => $this->post_id],
			['%d'],
			['%d']
		);
		*/

		wp_update_post([
			'ID'          => $this->post_id,
			'post_parent' => $new_parent ?: 0,
		]);

		write_to_log( '... Done.' );

		clean_post_cache( $this->post_id );
	}


	/* ---------------------------------------------------------- */
	/* SAVE META FROM POST                                        */
	/* ---------------------------------------------------------- */

	/**
	 * Speichert Metadaten aus $_POST.
	 */
	public function save_meta_from_post_wrapper( $post_id ): void {
		if ( $post_id !== $this->post_id ) return;

		$post_data = $_POST;

		$this->save_meta_from_post( $post_data );
	}

	public function save_meta_from_post( $post_data ): void {

		if ( isset( $post_data['recipe_id'] ) ) {
			write_to_log( 'Setting Recipe ID.' );
			$this->set_recipe_id( $post_data['recipe_id'] );
		}

		if ( isset( $post_data['servings'] ) ) {
			write_to_log( 'Setting Servings.' );
			$this->set_servings( $post_data['servings'] );
		}

		if ( isset( $post_data['note'] ) ) {
			write_to_log( 'Setting Note.' );
			$this->set_note( $post_data['note'] );
		}

		if ( isset( $post_data['mmm_parent_id'] ) ) {
			write_to_log( 'Setting Parent ID.' );
			$this->set_parent_plan_id( $post_data['mmm_parent_id'] );
		}
	}

	/* ---------------------------------------------------------- */
	/* STATIC HELPERS                                             */
	/* ---------------------------------------------------------- */

	/**
	 * Alle Meals eines Meal Plans abrufen.
	 *
	 * @param int $plan_id
	 * @return WP_Post[]
	 */
	public static function get_meals_for_plan( $plan_id ): array {
		return get_children([
			'post_parent' => intval( $plan_id ),
			'post_type'   => 'mmm_meal',
			'post_status' => 'any',
			'orderby'     => 'menu_order',
			'order'       => 'ASC',
		]);
	}
}
