<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mixmymeals_Ingredient_Amount
 *
 * Handles ingredient unit amounts, including creation, retrieval,
 * updates, and deletion of ingredient unit posts.
 */
class Mixmymeals_Ingredient_Amount {

	/**
	 * Post ID of the ingredient unit.
	 *
	 * @var int
	 */
	private int $post_id;

	/**
	 * Constructor.
	 *
	 * Initializes the ingredient amount object with a given post ID.
	 *
	 * @param int $post_id The post ID of the ingredient unit.
	 */
	public function __construct( int $post_id ) {
		$this->post_id = $post_id;
	}

	public function get_id(): int {
		return $this->post_id;
	}

	public function is_valid(): bool {
		if ( $this->post_id <= 0 ) {
			return false;
		}

		// Schneller als get_post()
		if ( get_post_type( $this->post_id ) !== 'mmm_ingr_unit' ) {
			return false;
		}

		$parent_id = (int) wp_get_post_parent_id( $this->post_id );

		if ( $parent_id <= 0 ) {
			return false;
		}

		// Parent validieren
		$parent = new Mixmymeals_Ingredient( $parent_id );

		return $parent->is_valid();
	}

	/**
	 * Factory method: Creates a new ingredient unit.
	 *
	 * @param int    $parent_id The parent ingredient ID.
	 * @param string $title     The title of the unit (default: "New Amount").
	 *
	 * @return self Returns a new instance of Mixmymeals_Ingredient_Amount.
	 *
	 * @throws \Exception If post creation fails.
	 */
	public static function create( int $parent_id, string $title = 'New Amount' ): self {
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'mmm_ingr_unit',
				'post_status' => 'publish',
				'post_title'  => sanitize_text_field( $title ),
				'post_parent' => $parent_id,
			)
		);

		if ( is_wp_error( $post_id ) ) {
			// esc_html suggested by Plugin Check.
			throw new \Exception( esc_html( $post_id->get_error_message() ) );
		}

		return new self( $post_id );
	}

	/**
	 * Getter: Returns the weight of the unit.
	 *
	 * @return float The weight of the unit.
	 */
	public function get_weight(): float {
		if ( ! $this->is_valid() ) {
			return 0.0;
		}

		return floatval(
			get_post_meta( $this->post_id, '_mmm_ingr_unit_weight', true )
		);
	}

	/**
	 * Setter: Sets the weight of the unit.
	 *
	 * @param float $weight The weight to set.
	 *
	 * @return void
	 */
	public function set_weight( float $weight ): void {
		if ( ! $this->is_valid() ) {
			return;
		}

		update_post_meta( $this->post_id, '_mmm_ingr_unit_weight', $weight );
	}

	/**
	 * Getter: Returns the parent ingredient ID.
	 *
	 * @return int The parent ingredient ID.
	 */
	public function get_parent_ingredient_id(): int {
		return wp_get_post_parent_id( $this->post_id );
	}

	/**
	 * Setter: Sets the parent ingredient ID.
	 *
	 * @param int $ingredient_id The parent ingredient ID to set.
	 *
	 * @return void
	 */
	public function set_parent_ingredient_id( int $ingredient_id ): void {
		wp_update_post(
			array(
				'ID'          => $this->post_id,
				'post_parent' => $ingredient_id,
			)
		);
	}

	/**
	 * Getter: Returns the title of the unit (e.g., "Teaspoon", "Cup").
	 *
	 * @return string The title of the unit.
	 */
	public function get_title(): string {
		if ( ! $this->is_valid() ) {
			return '';
		}

		return get_the_title( $this->post_id );
	}

	/**
	 * Setter: Sets the title of the unit.
	 *
	 * @param string $title The title to set.
	 *
	 * @return void
	 */
	public function set_title( string $title ): void {
		if ( ! $this->is_valid() ) {
			return;
		}

		wp_update_post(
			array(
				'ID'         => $this->post_id,
				'post_title' => sanitize_text_field( $title ),
			)
		);
	}

	/**
	 * Deletes the ingredient unit.
	 *
	 * @return void
	 */
	public function delete(): void {
		wp_delete_post( $this->post_id, true );
	}

	/**
	 * Setter: Marks the unit as indivisible or removes the flag.
	 *
	 * @param bool $is_indivisible Whether the unit is indivisible.
	 *
	 * @return void
	 */
	public function set_indivisible( bool $is_indivisible ): void {
		if ( ! $this->is_valid() ) {
			return;
		}

		if ( $is_indivisible ) {
			update_post_meta( $this->post_id, '_mmm_is_indivisible', 1 );
		} else {
			delete_post_meta( $this->post_id, '_mmm_is_indivisible' );
		}
	}

	/**
	 * Getter: Checks whether the unit is indivisible.
	 *
	 * @return bool True if indivisible, false otherwise.
	 */
	public function is_indivisible(): bool {
		if ( ! $this->is_valid() ) {
			return false;
		}

		return (bool) get_post_meta(
			$this->post_id,
			'_mmm_is_indivisible',
			true
		);
	}

	/**
	 * Setter: How finely this unit can be split. 2 means halves, 4 quarters.
	 * Anything below 2 removes the restriction, so the unit is freely
	 * divisible again.
	 *
	 * Ignored while the unit is marked indivisible — that flag already says
	 * "whole ones only", which is the same statement as a divisor of 1.
	 *
	 * @param int $divisor Smallest usable fraction is 1/$divisor.
	 * @return void
	 */
	public function set_divisor( int $divisor ): void {
		if ( ! $this->is_valid() ) {
			return;
		}

		if ( $divisor >= 2 ) {
			update_post_meta( $this->post_id, '_mmm_unit_divisor', $divisor );
		} else {
			delete_post_meta( $this->post_id, '_mmm_unit_divisor' );
		}
	}

	/**
	 * Getter: The stored divisor, or 0 when the unit may be split freely.
	 *
	 * @return int
	 */
	public function get_divisor(): int {
		if ( ! $this->is_valid() ) {
			return 0;
		}

		$divisor = (int) get_post_meta( $this->post_id, '_mmm_unit_divisor', true );

		return $divisor >= 2 ? $divisor : 0;
	}

	/**
	 * The divisor every calculation should work with: 1 for an indivisible
	 * unit, the stored divisor for one that splits into fractions, 0 when the
	 * unit may take any value.
	 *
	 * This is the single place that reconciles the checkbox with the number.
	 * "Indivisible" and "divisor 1" are the same statement, and the checkbox
	 * wins — it is the coarser, more deliberate setting, and a leftover
	 * divisor from before someone ticked it must not quietly reintroduce
	 * halves.
	 *
	 * A quantity is then always a multiple of 1/divisor: round for a recipe,
	 * round up for a shopping list.
	 *
	 * @return int
	 */
	public function get_step_divisor(): int {

		if ( $this->is_indivisible() ) {
			return 1;
		}

		return $this->get_divisor();
	}

	public function get_amount_usage_count(): int {

		$amount_id = $this->post_id;

		$recipes = get_posts([
			'post_type'      => 'mmm_recipe',
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'fields'         => 'ids',
		]);

		$count = 0;

		foreach ( $recipes as $recipe_id ) {

			$recipe = new Mixmymeals_Recipe( $recipe_id );
			$steps  = $recipe->get_steps();

			foreach ( $steps as $step ) {
				foreach ( $step['ingredients'] ?? [] as $ing ) {

					if ( intval( $ing['amount_id'] ?? 0 ) === $amount_id ) {
						$count++;
					}
				}
			}
		}

		return $count;
	}

}