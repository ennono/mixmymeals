<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mixmymeals_Elementor_Integration {

	public function __construct() {

		add_action( 'elementor/query/all_meals_in_a_plan', array( $this, 'all_meals_in_a_plan' ) );

	}

	public function all_meals_in_a_plan( $query ) {

		$plan_id = get_the_ID();
		if ( ! $plan_id ) return;
		write_to_log( 'ok' );

		$query->set( 'post_type', 'mmm_meal' );
		$query->set( 'post_status', 'publish' );
		$query->set( 'posts_per_page', -1 );
		$query->set( 'orderby', 'menu_order' );
		$query->set( 'order', 'ASC' );
		$query->set( 'post_parent', $plan_id );
	}

}
