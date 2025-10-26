<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Mixmymeals_Meal_Plan {

	private $post_id;

	public function __construct( $post_id ) {
		$this->post_id = $post_id;
	}

	public function get_start_date() {
		return get_post_meta( $this->post_id, 'start_date', true );
	}

	public static function get_all_meal_plans() {
		return get_posts([
			'post_type'      => 'mmm_meal_plan',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'date',
			'order'          => 'DESC',
		]);
	}

	public function count_meals() {
		// Alle Meals zählen, die diesem Plan als Parent zugeordnet sind
		$meal_count = get_posts([
			'post_type'      => 'mmm_meal',
			'post_parent'    => $this->post_id,
			'post_status'    => 'any',
			'fields'         => 'ids', // Nur IDs holen – schneller
			'posts_per_page' => -1,
		]);

		return is_array($meal_count) ? count($meal_count) : 0;
	}


}
