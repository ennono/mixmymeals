<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles ingredient nutrition data.
 */
class Mixmymeals_Ingredient {

	/**
	 * @var int
	 */
	private $post_id;

	/**
	 * Constructor.
	 *
	 * @param int $post_id
	 */
	public function __construct( $post_id ) {
		$this->post_id = intval( $post_id );
	}

	/**
	 * Sets nutrition data for an ingredient.
	 *
	 * @param array $data
	 */
	public function set_nutrition( $data ) {
		$fields = [
			'calories_100' => '_rmm_calories_100',
			'weight_item'  => '_rmm_weight_item',
			'fat'          => '_rmm_fat',
			'carbs'        => '_rmm_carbs',
			'protein'      => '_rmm_protein',
			'sugar'        => '_rmm_sugar',
			'fiber'        => '_rmm_fiber',
			'salt'         => '_rmm_salt',
			//'unit'         => '_rmm_unit',
		];

		foreach ( $fields as $key => $meta_key ) {
			if ( isset( $data[ $key ] ) ) {
				update_post_meta( $this->post_id, $meta_key, sanitize_text_field( $data[ $key ] ) );
			}
		}
	}

	/**
	 * Gets all nutrition data for an ingredient.
	 *
	 * @return array
	 */
	public function get_nutrition() {
		$fields = [
			'calories_100' => '_rmm_calories_100',
			'weight_item'  => '_rmm_weight_item',
			'fat'          => '_rmm_fat',
			'carbs'        => '_rmm_carbs',
			'protein'      => '_rmm_protein',
			'sugar'        => '_rmm_sugar',
			'fiber'        => '_rmm_fiber',
			'salt'         => '_rmm_salt',
			//'unit'         => '_rmm_unit',
		];

		$data = [];

		foreach ( $fields as $key => $meta_key ) {
			$value = $this->get_meta_value( $meta_key );
			$data[ $key ] = $value;
		}

		return $data;
	}

	/**
	 * Helper: Returns a single meta value safely.
	 *
	 * @param string $meta_key
	 * @return string
	 */
	private function get_meta_value( $meta_key ) {
		if ( ! $this->post_id ) {
			return '';
		}

		$value = get_post_meta( $this->post_id, $meta_key, true );

		if ( is_array( $value ) ) {
			$value = implode( ', ', $value );
		}

		return sanitize_text_field( $value );
	}

	/**
	 * Deletes all nutrition data.
	 */
	public function purge_nutrition() {
		$fields = [
			'_rmm_calories_100',
			'_rmm_weight_item',
			'_rmm_fat',
			'_rmm_carbs',
			'_rmm_protein',
			'_rmm_sugar',
			'_rmm_fiber',
			'_rmm_salt',
			//'_rmm_unit',
		];

		foreach ( $fields as $meta_key ) {
			delete_post_meta( $this->post_id, $meta_key );
		}
	}

	public static function get_all_ingredients() {
		static $cache = null;

		if ( $cache === null ) {
			$cache = get_posts([
				'post_type'      => 'mmm_ingredient',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			]);
		}

		return $cache;
	}

	/**
	 * Gibt alle Mengen (Units) der Zutat zurück.
	 *
	 * @param array $args Zusätzliche WP_Query-Argumente (optional).
	 * @return WP_Post[] Array von WP_Post-Objekten (mmm_ingr_unit).
	 */
	public function get_units( array $args = [] ): array {

		if ( ! $this->post_id ) {
			return [];
		}

		$defaults = [
			'post_type'   => 'mmm_ingr_unit',
			'post_status' => 'any',
			'post_parent' => $this->post_id,
			'numberposts' => -1,
			'orderby'     => 'title',
			'order'       => 'ASC',
		];

		$query_args = wp_parse_args( $args, $defaults );

		return get_posts( $query_args );

	}

}
