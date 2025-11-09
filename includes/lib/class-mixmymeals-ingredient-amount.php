<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Mixmymeals_Ingredient_Amount {

	private int $post_id;

	public function __construct( int $post_id ) {
		$this->post_id = $post_id;
	}

	/** Getter: Gewicht einer Einheit */
	public function get_weight(): float {
		return floatval( get_post_meta( $this->post_id, '_mmm_ingr_unit_weight', true ) );
	}

	/** Setter: Gewicht einer Einheit */
	public function set_weight( float $weight ): void {
		update_post_meta( $this->post_id, '_mmm_ingr_unit_weight', $weight );
	}

	/** Getter: Zugehörige Zutat (Parent ID) */
	public function get_parent_ingredient_id(): int {
		return wp_get_post_parent_id( $this->post_id );
	}

	/** Setter: Parent-Zutat festlegen */
	public function set_parent_ingredient_id( int $ingredient_id ): void {
		wp_update_post( [
			'ID'          => $this->post_id,
			'post_parent' => $ingredient_id,
		] );
	}

	/** Titel = Name der Einheit (z. B. "Teelöffel", "Tasse") */
	public function get_title(): string {
		return get_the_title( $this->post_id );
	}

	public function set_title( string $title ): void {
		wp_update_post( [
			'ID'         => $this->post_id,
			'post_title' => sanitize_text_field( $title ),
		] );
	}

	/** Factory-Methode: Neue Menge erstellen */
	public static function create( int $parent_id, string $title = 'New Amount' ): self {
		$post_id = wp_insert_post( [
			'post_type'   => 'mmm_ingr_unit',
			'post_status' => 'publish',
			'post_title'  => sanitize_text_field( $title ),
			'post_parent' => $parent_id,
		] );

		if ( is_wp_error( $post_id ) ) {
			throw new \Exception( $post_id->get_error_message() );
		}

		return new self( $post_id );
	}

	/** Menge löschen */
	public function delete(): void {
		wp_delete_post( $this->post_id, true );
	}

	public function set_sale_unit( bool $is_sale_unit ): void {
		if ( $is_sale_unit ) {
			update_post_meta( $this->post_id, '_mmm_is_sale_unit', 1 );
		} else {
			delete_post_meta( $this->post_id, '_mmm_is_sale_unit' );
		}
	}

	public function is_sale_unit(): bool {
		return (bool) get_post_meta( $this->post_id, '_mmm_is_sale_unit', true );
	}


}

