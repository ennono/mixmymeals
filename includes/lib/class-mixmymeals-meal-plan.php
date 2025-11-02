<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Mixmymeals_Meal_Plan {

	private $post_id;

	public function __construct( $post_id ) {
		$this->post_id = $post_id;
	}

	// ---------------------------
	// Getter / Setter
	// ---------------------------

	public function get_start_date() {
		return get_post_meta( $this->post_id, 'start_date', true );
	}

	public function set_start_date( $date ) {
		update_post_meta( $this->post_id, 'start_date', sanitize_text_field( $date ) );
	}

	public function get_end_date() {
		return get_post_meta( $this->post_id, 'end_date', true );
	}

	public function set_end_date( $date ) {
		update_post_meta( $this->post_id, 'end_date', sanitize_text_field( $date ) );
	}

	// ---------------------------
	// Static helper methods
	// ---------------------------

	public static function get_all_meal_plans() {
		return get_posts([
			'post_type'      => 'mmm_meal_plan',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'date',
			'order'          => 'DESC',
		]);
	}

	// ---------------------------
	// Meal-Handling
	// ---------------------------

	public function count_meals() {
		$meal_count = get_posts([
			'post_type'      => 'mmm_meal',
			'post_parent'    => $this->post_id,
			'post_status'    => 'any',
			'fields'         => 'ids',
			'posts_per_page' => -1,
		]);
		return is_array( $meal_count ) ? count( $meal_count ) : 0;
	}

	public function get_meals() {
		return get_posts([
			'post_type'      => 'mmm_meal',
			'post_parent'    => $this->post_id,
			'post_status'    => 'any',
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
			'posts_per_page' => -1,
		]);
	}
}
