<?php
/**
 * Equipment Handler.
 *
 * @package MixMyMeals/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mixmymeals_Equipment
 *
 * Handles equipment.
 */
class Mixmymeals_Equipment {

	/**
	 * Post ID of the piece of equipment.
	 *
	 * @var int
	 */
	private int $post_id;

	public function __construct( int $post_id ) {
		$this->post_id = $post_id;
	}

	public function get_id(): int {
		return $this->post_id;
	}

	public function is_valid(): bool {
		return $this->post_id > 0 && get_post_type( $this->post_id ) === 'mmm_equipment';
	}

	public static function exists_by_id( int $post_id ): bool {
		return $post_id > 0 && get_post_type( $post_id ) === 'mmm_equipment';
	}

	public function get_title(): string {
		if ( ! $this->is_valid() ) {
			return '';
		}
		return get_the_title( $this->post_id );
	}

	public function set_title( string $title ): void {
		if ( ! $this->is_valid() ) {
			return;
		}
		wp_update_post( array(
			'ID'         => $this->post_id,
			'post_title' => sanitize_text_field( $title ),
		) );
	}

	/** @return WP_Post[] */
	public static function get_all(): array {
		return get_posts( array(
			'post_type'      => 'mmm_equipment',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );
	}

	/**
	 * @throws \Exception On post creation failure.
	 */
	public static function create( string $title, string $status = 'publish' ): self {
		$post_id = wp_insert_post( array(
			'post_type'   => 'mmm_equipment',
			'post_title'  => sanitize_text_field( $title ),
			'post_status' => $status,
		), true );

		if ( is_wp_error( $post_id ) ) {
			throw new \Exception( esc_html( $post_id->get_error_message() ) );
		}

		return new self( $post_id );
	}

}