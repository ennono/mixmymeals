<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Mixmymeals_Post_Meta {

	// Flags to prevent endless loops:
	private static bool $updating_meal_meta = false;
	private static bool $updating_ingr_amount_meta = false;


	public function __construct() {

		//add_action( 'add_meta_boxes', [ $this, 'register_plan_entry_metabox' ] );
		//add_action( 'save_post_' . 'mmm_entry', [ $this, 'save_plan_entry_meta' ] );

		add_action( 'add_meta_boxes_mixmymeals_recipe', [ $this, 'add_recipe_steps_meta_box' ] );
		add_action( 'save_post_mixmymeals_recipe', [ $this, 'save_recipe_steps_meta_box' ] );

		add_action( 'add_meta_boxes_mmm_ingredient', [ $this, 'add_ingredient_meta_box' ] );
		add_action( 'save_post_mmm_ingredient', [ $this,
		                                          'save_ingredient_meta'
		] );

		add_action( 'add_meta_boxes_mmm_ingr_unit', [ $this, 'add_ingr_amount_meta_box' ] );
		add_action( 'save_post_mmm_ingr_unit', [ $this, 'save_ingr_amount_meta' ] );
		add_action( 'add_meta_boxes_mmm_ingr_unit', [ $this, 'add_ingr_amount_parent_meta_box' ] );
		add_action( 'add_meta_boxes_mmm_ingredient', [ $this, 'add_ingredient_amounts_meta_box' ] );
		add_action( 'wp_ajax_mixmymeals_get_ingredient_units',
			array( $this, 'ajax_mixmymeals_get_ingredient_units_handler' ) );

		add_action( 'add_meta_boxes_mixmymeals_recipe', [ $this, 'add_recipe_shopping_list_meta_box' ] );

		add_action( 'wp_ajax_mixmymeals_update_shopping_list', [ $this, 'ajax_update_shopping_list' ] );

		add_action( 'add_meta_boxes_mmm_meal_plan', [ $this, 'register_meal_plan_metabox' ] );
		add_action( 'save_post_mmm_meal_plan', [ $this, 'save_meal_plan_meta' ] );

		add_action( 'add_meta_boxes_mmm_meal', [ $this, 'register_meal_metabox' ] );
		add_action( 'save_post_mmm_meal', [ $this, 'save_meal_meta' ] );
		add_action( 'add_meta_boxes_mmm_meal_plan', [ $this, 'add_meal_plan_overview_meta_box' ] );


	}

	/* ======================================================
	 *  RECIPE META BOX
	 * ====================================================== */

	public function add_recipe_steps_meta_box() {
		add_meta_box(
			'mixmymeals_recipe_steps_meta_box',
			__( 'Recipe Steps', 'mixmymeals' ),
			[ $this, 'render_recipe_steps_meta_box_content' ],
			'mixmymeals_recipe',
			'normal',
			'default'
		);
	}

	public function render_recipe_steps_meta_box_content( $post ) {
		$recipe_obj  = new Mixmymeals_Recipe( $post->ID );
		$steps       = $recipe_obj->get_steps() ?: [];
		$ingredients = Mixmymeals_Ingredient::get_all_ingredients();

		// Portionen-Wert laden oder Default = 1
		//$servings = get_post_meta( $post->ID, '_mmm_servings', true );
		$servings = $recipe_obj->get_servings();
		if ( empty( $servings ) ) {
			$servings = 1;
		}

		echo '<div class="mixmymeals-recipe-meta">';

		// --- Feld für Anzahl der Portionen ---
		echo '<div class="mixmymeals-servings-field" style="margin-bottom:1em;">';
		echo '<label for="mmm_servings" style="font-weight:bold;">' . esc_html__( 'Servings (Portionen):', 'mixmymeals' ) . '</label><br />';
		echo '<input type="number" min="1" step="1" name="mmm_servings" id="mmm_servings" value="' . esc_attr( $servings ) . '" style="width:80px; text-align:right;" />';
		echo '</div>';

		// --- Schritte-Editor ---
		echo '<div class="mixmymeals-recipe-steps">';
		if ( empty( $steps ) ) $steps[] = [];

		foreach ( $steps as $index => $step ) {
			$this->render_step_row( $index, $step, $ingredients );
		}
		echo '</div>';

		echo '<p><button type="button" id="mixmymeals-add-step" class="button button-secondary">+ ' . esc_html__( 'Add Step', 'mixmymeals' ) . '</button></p>';

		// --- Template für neue Zutaten (JS) ---
		echo '<div id="mixmymeals-ingredient-template" style="display:none;">';
		echo '<div class="mixmymeals-step-ingredient">';
		echo '<select class="widefat">';
		echo '<option value="">-- ' . esc_html__( 'Select Ingredient', 'mixmymeals' ) . ' --</option>';
		foreach ( $ingredients as $ing ) {
			echo '<option value="' . esc_attr( $ing->ID ) . '">' . esc_html( $ing->post_title ) . '</option>';
		}
		echo '</select> ';
		echo '<input type="number" step="0.1" min="0" class="small-text" placeholder="Amount">';
		echo ' <button type="button" class="button mixmymeals-remove-ingredient">-</button>';
		echo '</div></div>';

		echo '</div>';
	}


	private function render_step_row( $index, $step, $ingredients ) {
		$title            = $step['title'] ?? '';
		$description      = $step['description'] ?? '';
		$image_id         = isset( $step['image'] ) ? intval( $step['image'] ) : 0;
		$image_url        = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';
		$step_ingredients = $step['ingredients'] ?? [ ['id' => '', 'amount' => '', 'amount_id' => ''] ];

		echo '<div class="mixmymeals-step-row" data-step="' . esc_attr( $index ) . '">';
		echo '<h3>' . esc_html( sprintf( __( 'Step %d', 'mixmymeals' ), $index + 1 ) ) . '</h3>';

		// Title
		echo '<p><label>' . esc_html__( 'Title', 'mixmymeals' ) . '</label><br>';
		echo '<input type="text" class="regular-text" name="mixmymeals_recipe_steps[' . $index . '][title]" value="' . esc_attr( $title ) . '"></p>';

		// Description
		echo '<p><label>' . esc_html__( 'Description', 'mixmymeals' ) . '</label><br>';
		echo '<textarea rows="3" class="large-text" name="mixmymeals_recipe_steps[' . $index . '][description]">' . esc_textarea( $description ) . '</textarea></p>';

		// Ingredients
		echo '<div class="mixmymeals-step-ingredients-wrapper">';
		echo '<div class="mixmymeals-step-ingredients">';

		foreach ( $step_ingredients as $i => $ing ) {
			$ing_id = $ing['id'] ?? '';
			$amount = $ing['amount'] ?? '';
			$selected_amount_id = $ing['amount_id'] ?? '';

			echo '<div class="mixmymeals-step-ingredient">';

			// Ingredient select
			echo '<select name="mixmymeals_recipe_steps[' . $index . '][ingredients][' . $i . '][id]" class="widefat ingredient-select">';
			echo '<option value="">-- ' . esc_html__( 'Select Ingredient', 'mixmymeals' ) . ' --</option>';

			foreach ( $ingredients as $post_ing ) {
				$ingredient_obj = new Mixmymeals_Ingredient( $post_ing->ID );
				$nutrition      = $ingredient_obj->get_nutrition();
				$weight_item    = floatval( $nutrition['weight_item'] ?? 0 );
				$weight_label   = $weight_item > 0 ? ' (' . $weight_item . ' g)' : '';
				$sel = selected( $ing_id, $post_ing->ID, false );

				echo '<option value="' . esc_attr( $post_ing->ID ) . '" ' . $sel . '>' .
				     esc_html( $post_ing->post_title . $weight_label ) .
				     '</option>';
			}
			echo '</select> ';

			// Amount input
			echo '<input type="number" step="1" min="0" name="mixmymeals_recipe_steps[' . $index . '][ingredients][' . $i . '][amount]" value="' . esc_attr( $amount ) . '" class="small-text" placeholder="Amount"> ';

			// Amount/Unit select
			echo '<select name="mixmymeals_recipe_steps[' . $index . '][ingredients][' . $i . '][amount_id]" class="small-text ingredient-unit-select">';
			$sel_gramm = selected( $selected_amount_id, '', false );
			echo '<option value="" ' . $sel_gramm . '>' . esc_html__( 'Gramm (g)', 'mixmymeals' ) . '</option>';

			if ( $ing_id ) {
				$amounts = get_posts([
					'post_type'   => 'mmm_ingr_unit',
					'post_status' => 'any',
					'post_parent' => intval( $ing_id ),
					'numberposts' => -1,
					'orderby'     => 'title',
					'order'       => 'ASC',
				]);

				foreach ( $amounts as $amt_post ) {
					$amount_obj = new Mixmymeals_Ingredient_Amount( $amt_post->ID );
					$weight     = $amount_obj->get_weight();
					$sel_amt    = selected( $selected_amount_id, $amt_post->ID, false );

					echo '<option value="' . esc_attr( $amt_post->ID ) . '" ' . $sel_amt . '>' .
					     esc_html( $amount_obj->get_title() . ( $weight ? ' (' . $weight . ' g)' : '' ) ) .
					     '</option>';
				}
			}
			echo '</select> ';

			// Remove button
			echo '<button type="button" class="button mixmymeals-remove-ingredient">-</button>';
			echo '</div>';
		}

		echo '</div>';
		echo '<button type="button" class="button mixmymeals-add-ingredient">+ ' . esc_html__( 'Add Ingredient', 'mixmymeals' ) . '</button>';
		echo '</div>';

		// Image
		echo '<div class="mixmymeals-step-image">';
		echo '<label>' . esc_html__( 'Image', 'mixmymeals' ) . '</label><br>';
		echo '<img class="mixmymeals-image-preview" src="' . esc_url( $image_url ) . '" style="max-width:100px;' . ( $image_url ? '' : 'display:none;' ) . '">';
		echo '<input type="hidden" name="mixmymeals_recipe_steps[' . $index . '][image]" class="mixmymeals-image-id" value="' . esc_attr( $image_id ) . '"><br>';
		echo '<button type="button" class="button mixmymeals-select-image">' . esc_html__( 'Select Image', 'mixmymeals' ) . '</button> ';
		echo '<button type="button" class="button mixmymeals-remove-image" ' . ( $image_url ? '' : 'style="display:none;"' ) . '>' . esc_html__( 'Remove', 'mixmymeals' ) . '</button>';
		echo '</div>';

		echo '<p class="mixmymeals-step-actions"><a href="#" class="mixmymeals-delete-step">' . esc_html__( 'Delete Step', 'mixmymeals' ) . '</a></p>';
		echo '<hr></div>';
	}




	public function save_recipe_steps_meta_box( $post_ID ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( ! current_user_can( 'edit_post', $post_ID ) ) return;

		$recipe_obj = new Mixmymeals_Recipe( $post_ID );

		// 🟢 Portionen speichern
		if ( isset( $_POST['mmm_servings'] ) ) {
			$recipe_obj->set_servings( floatval( $_POST['mmm_servings'] ) );
		}

		// 🟢 Schritte speichern (wie gehabt)
		if ( isset( $_POST['mixmymeals_recipe_steps'] ) ) {
			$steps = array_filter( $_POST['mixmymeals_recipe_steps'], function( $step ) {
				$title = trim( $step['title'] ?? '' );
				$desc  = trim( $step['description'] ?? '' );
				$ingredients = $step['ingredients'] ?? [];
				return !( empty( $title ) && empty( $desc ) && empty( array_filter( $ingredients, fn($i) => !empty($i['id']) && !empty($i['amount']) ) ) );
			});
			$recipe_obj->set_steps( $steps );
		} else {
			$recipe_obj->purge_steps();
		}
	}


	/* ======================================================
 *  MEAL PLAN META BOX (Overview of linked meals)
 * ====================================================== */

	public function add_meal_plan_overview_meta_box() {
		add_meta_box(
			'mixmymeals_plan_overview_box',
			__( 'Meal Overview', 'mixmymeals' ),
			[ $this, 'render_meal_plan_overview_meta_box' ],
			'mmm_meal_plan',
			'normal',
			'default'
		);
	}

	public function render_meal_plan_overview_meta_box( $post ) {
		$meals = get_children( [
			'post_parent' => $post->ID,
			'post_type'   => 'mmm_meal',
			'post_status' => 'any',
			'orderby'     => 'menu_order',
			'order'       => 'ASC',
		] );

		if ( empty( $meals ) ) {
			echo '<p>' . esc_html__( 'No meals linked to this plan yet.', 'mixmymeals' ) . '</p>';
			return;
		}

		echo '<table class="widefat" style="width:100%; border-collapse:collapse;">';
		echo '<thead><tr>
		<th>' . esc_html__( 'Meal', 'mixmymeals' ) . '</th>
		<th>' . esc_html__( 'Recipe', 'mixmymeals' ) . '</th>
		<th style="text-align:right;">' . esc_html__( 'Servings', 'mixmymeals' ) . '</th>
	</tr></thead><tbody>';

		foreach ( $meals as $meal ) {
			$meal_obj  = new Mixmymeals_Meal( $meal->ID );
			$recipe_id = $meal_obj->get_recipe_id();

			if ( ! $recipe_id ) {
				continue;
			}

			$servings    = max( 1, intval( $meal_obj->get_servings() ) );
			$recipe_link = get_edit_post_link( $recipe_id );

			echo '<tr>';
			echo '<td>' . esc_html( $meal->post_title ?: sprintf( __( 'Meal #%d', 'mixmymeals' ), $meal->ID ) ) . '</td>';

			echo '<td>';
			if ( $recipe_link ) {
				echo '<a href="' . esc_url( $recipe_link ) . '" target="_blank"><strong>' . esc_html( get_the_title( $recipe_id ) ) . '</strong></a>';
			} else {
				echo esc_html( get_the_title( $recipe_id ) );
			}
			echo '</td>';

			echo '<td style="text-align:right;">' . esc_html( $servings ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}



	/**
	 * Helper to sum nutrition values for a recipe's steps × servings.
	 */
	/*
	private function calculate_nutrition_totals( $steps, $servings = 1 ) {
		$totals = array_fill_keys( ['calories','fat','carbs','protein','sugar','fiber','salt'], 0 );

		foreach ( $steps as $step ) {
			foreach ( $step['ingredients'] ?? [] as $ing ) {
				if ( empty( $ing['id'] ) || empty( $ing['amount'] ) ) continue;

				$ing_obj = new Mixmymeals_Ingredient( $ing['id'] );
				$nutrition = $ing_obj->get_nutrition();
				$weight_item  = floatval( $nutrition['weight_item'] ?? 0 );
				$factor = floatval( $ing['amount'] ) * $servings * $weight_item / 100;

				$totals['calories'] += $factor * floatval( $nutrition['calories_100'] ?? 0 );
				$totals['fat']      += $factor * floatval( $nutrition['fat'] ?? 0 );
				$totals['carbs']    += $factor * floatval( $nutrition['carbs'] ?? 0 );
				$totals['protein']  += $factor * floatval( $nutrition['protein'] ?? 0 );
				$totals['sugar']    += $factor * floatval( $nutrition['sugar'] ?? 0 );
				$totals['fiber']    += $factor * floatval( $nutrition['fiber'] ?? 0 );
				$totals['salt']     += $factor * floatval( $nutrition['salt'] ?? 0 );
			}
		}

		return $totals;
	}
	*/


	/* ======================================================
	 *  INGREDIENT META BOX
	 * ====================================================== */

	public function add_ingredient_meta_box() {
		add_meta_box(
			'mixmymeals_ingredient_meta_box',
			__( 'Nutrition Facts', 'mixmymeals' ),
			[ $this, 'render_ingredient_meta_box_content' ],
			'mmm_ingredient',
			'side',
			'default'
		);
	}

	public function render_ingredient_meta_box_content( $post ) {
		$ingredient_obj = new Mixmymeals_Ingredient( $post->ID );
		$values = $ingredient_obj->get_nutrition();

		echo '<p><label><strong>' . esc_html__( 'Calories per 100 g', 'mixmymeals' ) . '</strong></label><br>';
		echo '<input type="number" step="1" min="0" name="rmm_calories_100" value="' . esc_attr( $values['calories_100'] ?? '' ) . '" class="small-text"> kcal</p>';

		echo '<hr><h4>' . esc_html__( 'Nutrition values (per 100 g)', 'mixmymeals' ) . '</h4>';
		$nutrition = [
			'fat'     => __( 'Fat (g)', 'mixmymeals' ),
			'carbs'   => __( 'Carbohydrates (g)', 'mixmymeals' ),
			'protein' => __( 'Protein (g)', 'mixmymeals' ),
			'sugar'   => __( 'Sugar (g)', 'mixmymeals' ),
			'fiber'   => __( 'Fiber (g)', 'mixmymeals' ),
			'salt'    => __( 'Salt (g)', 'mixmymeals' ),
		];
		foreach ( $nutrition as $key => $label ) {
			echo '<p><label><strong>' . esc_html( $label ) . '</strong></label><br>';
			echo '<input type="number" step="0.1" min="0" name="rmm_' . esc_attr( $key ) . '" value="' . esc_attr( $values[$key] ?? '' ) . '" class="small-text"></p>';
		}
	}

	public function save_ingredient_meta( $post_ID ) {

		// Sicherheitsprüfungen
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( ! current_user_can( 'edit_post', $post_ID ) ) return;
		if ( ! isset( $_POST['mmm_ingredient_amounts_meta_nonce'] ) || ! wp_verify_nonce( $_POST['mmm_ingredient_amounts_meta_nonce'], 'mmm_ingredient_amounts_meta_box' ) ) {
			return;
		}

		// ----------------- Nutrition -----------------
		$data = [
			'calories_100' => $_POST['rmm_calories_100'] ?? '',
			//'weight_item'  => $_POST['rmm_weight_item'] ?? '',
			'fat'          => $_POST['rmm_fat'] ?? '',
			'carbs'        => $_POST['rmm_carbs'] ?? '',
			'protein'      => $_POST['rmm_protein'] ?? '',
			'sugar'        => $_POST['rmm_sugar'] ?? '',
			'fiber'        => $_POST['rmm_fiber'] ?? '',
			'salt'         => $_POST['rmm_salt'] ?? '',
		];

		$ingredient_obj = new Mixmymeals_Ingredient( $post_ID );
		$ingredient_obj->set_nutrition( $data );

		// ----------------- Ingredient Amounts -----------------
		if ( isset( $_POST['mmm_ingredient_amounts'] ) && is_array( $_POST['mmm_ingredient_amounts'] ) ) {
			foreach ( $_POST['mmm_ingredient_amounts'] as $id => $amount_data ) {

				// Bestehende Menge
				if ( $id !== 'new' ) {
					$amount_obj = new Mixmymeals_Ingredient_Amount( intval( $id ) );

					// Löschen
					if ( ! empty( $amount_data['delete'] ) ) {
						$amount_obj->delete();
						continue;
					}

					// Gewicht aktualisieren
					if ( isset( $amount_data['weight'] ) ) {
						$amount_obj->set_weight( floatval( $amount_data['weight'] ) );
					}

				} else {
					// Neue Menge anlegen
					if ( ! empty( $amount_data['title'] ) ) {
						$amount_obj = Mixmymeals_Ingredient_Amount::create( $post_ID, $amount_data['title'] );

						if ( isset( $amount_data['weight'] ) ) {
							$amount_obj->set_weight( floatval( $amount_data['weight'] ) );
						}
					}
				}
			}
		}
	}


	/* ======================================================
	 *  SHOPPING LIST META BOX (AJAX-fähig)
	 * ====================================================== */

	public function add_recipe_shopping_list_meta_box() {
		add_meta_box(
			'mixmymeals_recipe_shopping_list',
			__( 'Shopping List', 'mixmymeals' ),
			[ $this, 'render_recipe_shopping_list_meta_box' ],
			'mixmymeals_recipe',
			'normal',
			'default'
		);
	}

	public function render_recipe_shopping_list_meta_box( $post ) {
		$recipe_obj = new Mixmymeals_Recipe( $post->ID );
		$steps = $recipe_obj->get_steps() ?: [];

		echo '<div id="mixmymeals-shopping-list-container">';
		$this->render_recipe_shopping_list_table( $steps );
		echo '</div>';
	}

	private function render_recipe_shopping_list_table( $steps ) {

		$ingredient_totals = [];
		$ingredient_units  = [];

		$recipe_obj = new Mixmymeals_Recipe( get_the_ID() );
		$servings   = max( 1, $recipe_obj->get_servings() );

		foreach ( $steps as $step ) {
			foreach ( $step['ingredients'] ?? [] as $ing ) {
				if ( empty( $ing['id'] ) || ! isset( $ing['amount'] ) ) continue;

				$ing_id    = intval( $ing['id'] );
				$amount    = floatval( $ing['amount'] );
				$amount_id = ! empty( $ing['amount_id'] ) ? intval( $ing['amount_id'] ) : 0;

				$unit_weight = 1;
				if ( $amount_id ) {
					$amount_obj  = new Mixmymeals_Ingredient_Amount( $amount_id );
					$unit_weight = max( 0.0001, floatval( $amount_obj->get_weight() ) );
				}

				$total_weight = $amount * $unit_weight;

				$ingredient_totals[ $ing_id ] = ( $ingredient_totals[ $ing_id ] ?? 0 ) + $total_weight;
				$ingredient_units[ $ing_id ]  = [
					'amount'    => $amount,
					'amount_id' => $amount_id,
				];
			}
		}

		if ( empty( $ingredient_totals ) ) {
			echo '<p>' . esc_html__( 'No ingredient found.', 'mixmymeals' ) . '</p>';
			return;
		}

		$totals = array_fill_keys( [ 'calories','fat','carbs','protein','sugar','fiber','salt' ], 0 );
		$rows   = [];
		$total_portion_weight = 0;

		foreach ( $ingredient_totals as $ing_id => $total_weight ) {
			$ing_obj   = new Mixmymeals_Ingredient( $ing_id );
			$nutrition = $ing_obj->get_nutrition();
			if ( empty( $nutrition ) ) continue;

			$factor = ( $total_weight / 100 ) / max( 1, $servings );

			$amount_id = $ingredient_units[ $ing_id ]['amount_id'] ?? 0;
			$display_unit = 'g';
			if ( $amount_id ) {
				$amount_obj   = new Mixmymeals_Ingredient_Amount( $amount_id );
				$unit_title   = $amount_obj->get_title();
				$display_unit = $unit_title ?: 'g';
			}

			$display_amount_count = $ingredient_units[ $ing_id ]['amount'] ?? 0;
			$portion_weight        = $total_weight / max( 1, $servings );

			$total_portion_weight += $portion_weight;

			$rows[] = [
				'name'         => get_the_title( $ing_id ),
				'unit'         => $display_unit,
				'amount_count' => $display_amount_count,
				'amount'       => round( $portion_weight, 1 ) . ' g',
				'calories'     => $factor * floatval( $nutrition['calories_100'] ?? 0 ),
				'fat'          => $factor * floatval( $nutrition['fat'] ?? 0 ),
				'carbs'        => $factor * floatval( $nutrition['carbs'] ?? 0 ),
				'protein'      => $factor * floatval( $nutrition['protein'] ?? 0 ),
				'sugar'        => $factor * floatval( $nutrition['sugar'] ?? 0 ),
				'fiber'        => $factor * floatval( $nutrition['fiber'] ?? 0 ),
				'salt'         => $factor * floatval( $nutrition['salt'] ?? 0 ),
			];

			foreach ( $totals as $key => $_ ) {
				$totals[ $key ] += $rows[ count( $rows ) - 1 ][ $key ];
			}
		}

		// ---------------------------
		// Einkaufsliste
		// ---------------------------
		echo '<h3>' . esc_html__( 'Shopping List', 'mixmymeals' ) . '</h3>';
		echo '<ul class="mixmymeals-shopping-list" style="list-style:none; padding-left:0; margin:0;">';

		foreach ( $rows as $row ) {
			echo '<li style="padding:4px 0; border-bottom:1px solid #eee;">';
			echo '<strong>' . esc_html( $row['name'] ) . '</strong> – ';
			echo esc_html( $row['amount_count'] . '× ' . $row['unit'] . ' (' . $row['amount'] . ')' );
			echo '</li>';
		}

		echo '</ul>';

		// ---------------------------
		// Nährwertangaben (vertikal)
		// ---------------------------
		echo '<h3>' . esc_html__( 'Nutrition Details (per Serving)', 'mixmymeals' ) . '</h3>';
		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<tbody>';

		$labels = [
			'portion_weight' => esc_html__( 'Portion Weight (g)', 'mixmymeals' ),
			'calories'       => esc_html__( 'Calories (kcal)', 'mixmymeals' ),
			'fat'            => esc_html__( 'Fat (g)', 'mixmymeals' ),
			'carbs'          => esc_html__( 'Carbohydrates (g)', 'mixmymeals' ),
			'protein'        => esc_html__( 'Protein (g)', 'mixmymeals' ),
			'sugar'          => esc_html__( 'Sugar (g)', 'mixmymeals' ),
			'fiber'          => esc_html__( 'Fiber (g)', 'mixmymeals' ),
			'salt'           => esc_html__( 'Salt (g)', 'mixmymeals' ),
		];

		// Portion weight
		echo '<tr>';
		echo '<th>' . esc_html( $labels['portion_weight'] ) . '</th>';
		echo '<td>' . esc_html( round( $total_portion_weight, 1 ) ) . '</td>';
		echo '</tr>';

		// Alle anderen Nährwerte
		foreach ( $totals as $key => $value ) {
			echo '<tr>';
			echo '<th>' . esc_html( $labels[ $key ] ) . '</th>';
			echo '<td>' . esc_html( round( $value, 1 ) ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}








	/* ======================================================
	 *  AJAX UPDATE HANDLER
	 * ====================================================== */
	public function ajax_update_shopping_list() {
		check_ajax_referer( 'mixmymeals_admin_nonce', 'nonce' );

		$steps = isset( $_POST['steps'] ) ? wp_unslash( $_POST['steps'] ) : [];
		if ( empty( $steps ) || ! is_array( $steps ) ) {
			wp_send_json_error( 'No steps data.' );
		}

		ob_start();
		$this->render_recipe_shopping_list_table( $steps );
		$html = ob_get_clean();

		wp_send_json_success( [ 'html' => $html ] );
	}


	/* ======================================================
    *  MEAL PLAN & MEAL META BOXES
    * ====================================================== */

	/**
	 * Meal Plan Metabox: start date for the week.
	 */
	public function register_meal_plan_metabox() {
		add_meta_box(
			'mmm_meal_plan_meta_box',
			__( 'Meal Plan Settings', 'mixmymeals' ),
			[ $this, 'render_meal_plan_start_date_metabox' ],
			'mmm_meal_plan',
			'side',
			'default'
		);
	}

	public function render_meal_plan_start_date_metabox( $post ) {

		wp_nonce_field( 'mmm_meal_plan_meta_box', 'mmm_meal_plan_meta_nonce' );

		$meal_plan_obj = new Mixmymeals_Meal_Plan( $post->ID );
		$start_date = $meal_plan_obj->get_start_date();

		echo '<p><label><strong>' . esc_html__( 'Start Date (e.g., Monday)', 'mixmymeals' ) . '</strong></label><br>';
		echo '<input type="date" name="start_date" value="' . esc_attr( $start_date ) . '" style="width:100%;"></p>';

		$meal_count = $meal_plan_obj->count_meals();
		if ( $meal_count > 0 ) {
			echo '<p>' . esc_html__( 'Meals reach up to', 'mixmymeals' ) . ' ' . date( get_option( 'date_format' ), strtotime($start_date. ' + ' . $meal_count . ' days')) . '.</p>';
		} else {
			echo '<p>' . esc_html__( 'No meals attached yet.', 'mixmymeals' ) . '</p>';
		}
	}

	public function save_meal_plan_meta( $post_id ) {
		if (
			! isset( $_POST['mmm_meal_plan_meta_nonce'] ) ||
			! wp_verify_nonce( $_POST['mmm_meal_plan_meta_nonce'], 'mmm_meal_plan_meta_box' )
		) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

		if ( isset( $_POST['start_date'] ) ) {
			update_post_meta( $post_id, 'start_date', sanitize_text_field( $_POST['start_date'] ) );
		}
	}

	/**
	 * Meal Metabox: select parent plan, recipe, servings and note.
	 */
	public function register_meal_metabox() {
		add_meta_box(
			'mmm_meal_meta_box',
			__( 'Meal Details', 'mixmymeals' ),
			[ $this, 'render_meal_metabox' ],
			'mmm_meal',
			'normal',
			'default'
		);
	}

	public function render_meal_metabox( $post ) {
		wp_nonce_field( 'mmm_meal_meta_box', 'mmm_meal_meta_nonce' );

		$meal_obj = new Mixmymeals_Meal( $post->ID );

		$recipe_id = $meal_obj->get_recipe_id();
		$servings = $meal_obj->get_servings();
		$note = $meal_obj->get_note();
		$parent_id = $meal_obj->get_parent_plan_id();
		$weekday = $meal_obj->get_weekday();

		$plans = Mixmymeals_Meal_Plan::get_all_meal_plans();
		$recipes = Mixmymeals_Recipe::get_all_recipes();

		// --- Meal Plan Auswahl ---
		echo '<p><label><strong>' . esc_html__( 'Meal Plan (Parent)', 'mixmymeals' ) . '</strong></label><br>';
		echo '<select name="mmm_parent_id" style="width:100%;">';
		echo '<option value="">' . esc_html__( '— Select Meal Plan —', 'mixmymeals' ) . '</option>';
		foreach ( $plans as $plan ) {
			printf(
				'<option value="%d" %s>%s</option>',
				$plan->ID,
				selected( $parent_id, $plan->ID, false ),
				esc_html( $plan->post_title )
			);
		}
		echo '</select></p>';

		// --- Rezept Auswahl ---
		echo '<p><label><strong>' . esc_html__( 'Recipe', 'mixmymeals' ) . '</strong></label><br>';
		echo '<select name="recipe_id" style="width:100%;">';
		echo '<option value="">' . esc_html__( '— Select Recipe —', 'mixmymeals' ) . '</option>';
		foreach ( $recipes as $recipe ) {
			printf(
				'<option value="%d" %s>%s</option>',
				$recipe->ID,
				selected( $recipe_id, $recipe->ID, false ),
				esc_html( $recipe->post_title )
			);
		}
		echo '</select></p>';

		// --- NEU: Wochentag Auswahl ---
		$weekdays = [
			''        => __( '— No Day —', 'mixmymeals' ),
			'monday'    => __( 'Monday', 'mixmymeals' ),
			'tuesday'   => __( 'Tuesday', 'mixmymeals' ),
			'wednesday' => __( 'Wednesday', 'mixmymeals' ),
			'thursday'  => __( 'Thursday', 'mixmymeals' ),
			'friday'    => __( 'Friday', 'mixmymeals' ),
			'saturday'  => __( 'Saturday', 'mixmymeals' ),
			'sunday'    => __( 'Sunday', 'mixmymeals' ),
		];

		echo '<p><label><strong>' . esc_html__( 'Weekday (optional)', 'mixmymeals' ) . '</strong></label><br>';
		echo '<select name="weekday" style="width:100%;">';
		foreach ( $weekdays as $key => $label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $key ),
				selected( $weekday, $key, false ),
				esc_html( $label )
			);
		}
		echo '</select></p>';

		// --- Portionen ---
		echo '<p><label><strong>' . esc_html__( 'Servings', 'mixmymeals' ) . '</strong></label><br>';
		echo '<input type="number" name="servings" value="' . esc_attr( $servings ?: 1 ) . '" min="1" step="1" style="width:100px;"></p>';

		// --- Notiz ---
		echo '<p><label><strong>' . esc_html__( 'Note (optional)', 'mixmymeals' ) . '</strong></label><br>';
		echo '<textarea name="note" rows="2" style="width:100%;">' . esc_textarea( $note ) . '</textarea></p>';
	}

	public function save_meal_meta( $post_id ) {

		if ( self::$updating_meal_meta ) return;
		self::$updating_meal_meta = true;

		if (
			! isset( $_POST['mmm_meal_meta_nonce'] ) ||
			! wp_verify_nonce( $_POST['mmm_meal_meta_nonce'], 'mmm_meal_meta_box' )
		) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		$meal_obj = new Mixmymeals_Meal( $post_id );

		if ( isset( $_POST['mmm_parent_id'] ) ) {
			$meal_obj->set_parent_plan_id( $_POST['mmm_parent_id'] );
		}

		if ( isset( $_POST['recipe_id'] ) ) {
			$meal_obj->set_recipe_id( $_POST['recipe_id'] );
		}

		if ( isset( $_POST['servings'] ) ) {
			$meal_obj->set_servings( $_POST['servings'] );
		}

		if ( isset( $_POST['note'] ) ) {
			$meal_obj->set_note( $_POST['note'] );
		}

		if ( isset( $_POST['weekday'] ) ) {
			$meal_obj->set_weekday( sanitize_text_field( $_POST['weekday'] ) );
		}

		self::$updating_meal_meta = false;
	}

	/* ======================================================
 *  INGREDIENT AMOUNT META BOX
 * ====================================================== */

	public function add_ingr_amount_meta_box() {
		add_meta_box(
			'mmm_ingr_unit_meta_box',
			__( 'Weight per Unit', 'mixmymeals' ),
			[ $this, 'render_ingr_amount_meta_box_content' ],
			'mmm_ingr_unit',
			'side',
			'default'
		);
	}

	public function render_ingr_amount_meta_box_content( $post ) {
		wp_nonce_field( 'mmm_ingr_unit_meta_box', 'mmm_ingr_unit_meta_nonce' );

		// Handler-Objekt erstellen
		$amount_obj = new Mixmymeals_Ingredient_Amount( $post->ID );

		// Gewicht über Getter holen
		$weight = $amount_obj->get_weight();

		echo '<p><label for="mmm_ingr_unit_weight"><strong>' . esc_html__( 'Weight per Unit (g)', 'mixmymeals' ) . '</strong></label></p>';
		echo '<input type="number" step="0.1" min="0" name="mmm_ingr_unit_weight" id="mmm_ingr_unit_weight" value="' . esc_attr( $weight ) . '" style="width:100%;" />';
		echo '<p class="description">' . esc_html__( 'e.g. 1 TL = 5 g, 1 EL = 15 g, 1 Pcs. = 120 g.', 'mixmymeals' ) . '</p>';
	}


	public function save_ingr_amount_meta( $post_id ) {

		// 🛑 Endlosschleife verhindern
		if ( self::$updating_ingr_amount_meta ) return;
		self::$updating_ingr_amount_meta = true;

		// 🧩 Sicherheits-Checks
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			self::$updating_ingr_amount_meta = false;
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			self::$updating_ingr_amount_meta = false;
			return;
		}

		if (
			! isset( $_POST['mmm_ingr_unit_meta_nonce'] ) ||
			! wp_verify_nonce( $_POST['mmm_ingr_unit_meta_nonce'], 'mmm_ingr_unit_meta_box' )
		) {
			self::$updating_ingr_amount_meta = false;
			return;
		}

		// 🟢 Objekt laden
		$amount_obj = new Mixmymeals_Ingredient_Amount( $post_id );

		// Gewicht speichern
		if ( isset( $_POST['mmm_ingr_unit_weight'] ) ) {
			$amount_obj->set_weight( floatval( $_POST['mmm_ingr_unit_weight'] ) );
		}

		// Parent Ingredient speichern (zweite Meta Box)
		if (
			isset( $_POST['mmm_ingr_unit_parent_meta_nonce'] ) &&
			wp_verify_nonce( $_POST['mmm_ingr_unit_parent_meta_nonce'], 'mmm_ingr_unit_parent_meta_box' ) &&
			isset( $_POST['mmm_ingr_parent_id'] )
		) {
			$amount_obj->set_parent_ingredient_id( intval( $_POST['mmm_ingr_parent_id'] ) );
		}

		// 🔚 Flag wieder zurücksetzen
		self::$updating_ingr_amount_meta = false;
	}


	public function add_ingr_amount_parent_meta_box() {
		add_meta_box(
			'mmm_ingr_unit_parent_meta_box',
			__( 'Linked Ingredient', 'mixmymeals' ),
			[ $this, 'render_ingr_amount_parent_meta_box_content' ],
			'mmm_ingr_unit',
			'side',
			'default'
		);
	}

	public function render_ingr_amount_parent_meta_box_content( $post ) {
		wp_nonce_field( 'mmm_ingr_unit_parent_meta_box', 'mmm_ingr_unit_parent_meta_nonce' );

		$amount_obj = new Mixmymeals_Ingredient_Amount( $post->ID );
		$parent_id  = $amount_obj->get_parent_ingredient_id();

		// Liste aller Zutaten laden
		$ingredients = Mixmymeals_Ingredient::get_all_ingredients();

		echo '<p><label for="mmm_ingr_parent_id"><strong>' . esc_html__( 'Linked Ingredient', 'mixmymeals' ) . ':</strong></label></p>';
		echo '<select name="mmm_ingr_parent_id" id="mmm_ingr_parent_id" style="width:100%;">';
		echo '<option value="">- ' . esc_html__( 'Select', 'mixmymeals' ) . ' -</option>';

		foreach ( $ingredients as $ing ) {
			printf(
				'<option value="%d" %s>%s</option>',
				$ing->ID,
				selected( $parent_id, $ing->ID, false ),
				esc_html( $ing->post_title )
			);
		}

		echo '</select>';

		if ( $parent_id ) {
			$parent_link = get_edit_post_link( $parent_id );
			if ( $parent_link ) {
				echo '<p><a href="' . esc_url( $parent_link ) . '" target="_blank">' .
				     esc_html__( 'Open Ingredient', 'mixmymeals' ) . ' ↗</a></p>';
			}
		}
	}

	public function add_ingredient_amounts_meta_box() {
		add_meta_box(
			'mmm_ingredient_amounts_meta_box',
			__( 'Units', 'mixmymeals' ),
			[ $this, 'render_ingredient_amounts_meta_box_content' ],
			'mmm_ingredient',
			'normal',
			'default'
		);
	}

	public function render_ingredient_amounts_meta_box_content( $post ) {

		wp_nonce_field( 'mmm_ingredient_amounts_meta_box', 'mmm_ingredient_amounts_meta_nonce' );

		// Alle verknüpften Mengen-Posts abrufen
		$amounts = get_posts( [
			'post_type'   => 'mmm_ingr_unit',
			'post_status' => 'any',
			'post_parent' => $post->ID,
			'numberposts' => -1,
			'orderby'     => 'title',
			'order'       => 'ASC',
		] );

		echo '<div id="mmm-ingredient-amounts-list">';

		echo '<table class="widefat striped">';
		echo '<thead>';
		echo '<tr>';
		echo '<th>' . esc_html__( 'Title', 'mixmymeals' ) . '</th>';
		echo '<th>' . esc_html__( 'Weight', 'mixmymeals' ) . '</th>';
		echo '<th>' . esc_html__( 'Delete', 'mixmymeals' ) . '</th>';
		echo '</tr>';
		echo '</thead>';
		echo '<tbody>';

		// Bestehende Mengen
		if ( ! empty( $amounts ) ) {
			foreach ( $amounts as $amount_post ) {
				$amount = new Mixmymeals_Ingredient_Amount( $amount_post->ID );
				echo '<tr data-id="' . esc_attr( $amount_post->ID ) . '">';

				// Titel
				echo '<td>' . esc_html( $amount->get_title() ) . '</td>';

				// Gewicht als Input-Feld
				echo '<td>';
				echo '<input type="number" step="0.01" min="0" name="mmm_ingredient_amounts[' . esc_attr( $amount_post->ID ) . '][weight]" value="' . esc_attr( $amount->get_weight() ) . '" class="small-text">';
				echo '</td>';

				// Löschen-Checkbox
				echo '<td>';
				echo '<label>';
				echo '<input type="checkbox" name="mmm_ingredient_amounts[' . esc_attr( $amount_post->ID ) . '][delete]" value="1"> ';
				echo esc_html__( 'Delete', 'mixmymeals' );
				echo '</label>';
				echo '</td>';

				echo '</tr>';
			}
		}

		// Neue Menge Zeile
		echo '<tr class="new-ingredient-amount">';
		echo '<td>';
		echo '<input type="text" name="mmm_ingredient_amounts[new][title]" placeholder="' . esc_attr__( 'New Amount', 'mixmymeals' ) . '" class="regular-text">';
		echo '</td>';
		echo '<td>';
		echo '<input type="number" step="0.01" min="0" name="mmm_ingredient_amounts[new][weight]" value="" class="small-text">';
		echo '</td>';
		echo '<td>';
		echo '<em>' . esc_html__( '–', 'mixmymeals' ) . '</em>';
		echo '</td>';
		echo '</tr>';

		echo '</tbody>';
		echo '</table>';
		echo '</div>';
	}



	function ajax_mixmymeals_get_ingredient_units_handler() {

		check_ajax_referer( 'mixmymeals_admin_nonce', 'nonce' );

		$ingredient_id = intval( $_POST['ingredient_id'] ?? 0 );
		if ( ! $ingredient_id ) {
			wp_send_json_error( [ 'message' => 'Invalid ingredient ID' ] );
		}

		// Hole alle Mengen-Einheiten zu dieser Zutat
		$amounts = get_posts([
			'post_type'   => 'mmm_ingr_unit',
			'post_status' => 'any',
			'post_parent' => $ingredient_id,
			'numberposts' => -1,
			'orderby'     => 'title',
			'order'       => 'ASC',
		]);

		// Baue Dropdown
		ob_start();
		echo '<select class="small-text ingredient-unit-select" name="">';
		echo '<option value="">' . esc_html__( 'Gramm (Standard)', 'mixmymeals' ) . '</option>';

		foreach ( $amounts as $amt_post ) {
			$amount_obj = new Mixmymeals_Ingredient_Amount( $amt_post->ID );
			$weight     = $amount_obj->get_weight();
			$title      = $amount_obj->get_title();

			printf(
				'<option value="%d">%s%s</option>',
				esc_attr( $amt_post->ID ),
				esc_html( $title ),
				$weight ? esc_html( " ({$weight} g)" ) : ''
			);
		}

		echo '</select>';
		$html = ob_get_clean();

		wp_send_json_success( [ 'html' => $html ] );
	}

}
