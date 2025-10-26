<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Mixmymeals_Post_Meta {

	// Flag to prevent endless loops:
	private static bool $updating_meal_meta = false;

	public function __construct() {

		//add_action( 'add_meta_boxes', [ $this, 'register_plan_entry_metabox' ] );
		//add_action( 'save_post_' . 'mmm_entry', [ $this, 'save_plan_entry_meta' ] );

		add_action( 'add_meta_boxes_mixmymeals_recipe', [ $this, 'add_recipe_steps_meta_box' ] );
		add_action( 'save_post_mixmymeals_recipe', [ $this, 'save_recipe_steps_meta_box' ] );

		add_action( 'add_meta_boxes_mmm_ingredient', [ $this, 'add_ingredient_meta_box' ] );
		add_action( 'save_post_mmm_ingredient', [ $this,
		                                          'save_ingredient_meta'
		] );

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

		echo '<div class="mixmymeals-recipe-steps">';
		if ( empty( $steps ) ) $steps[] = [];

		foreach ( $steps as $index => $step ) {
			$this->render_step_row( $index, $step, $ingredients );
		}
		echo '</div>';

		echo '<p><button type="button" id="mixmymeals-add-step" class="button button-secondary">+ ' . esc_html__( 'Add Step', 'mixmymeals' ) . '</button></p>';

		// Template für JS
		echo '<div id="mixmymeals-ingredient-template" style="display:none;">';
		echo '<div class="mixmymeals-step-ingredient">';
		echo '<select class="widefat">';
		echo '<option value="">-- ' . esc_html__( 'Select Ingredient', 'mixmymeals' ) . ' --</option>';
		foreach ( $ingredients as $ing ) {
			echo '<option value="' . esc_attr( $ing->ID ) . '">' . esc_html( $ing->post_title ) . '</option>';
		}
		echo '</select> ';
		echo '<input type="number" step="1" min="0" class="small-text" placeholder="Amount">';
		echo ' <button type="button" class="button mixmymeals-remove-ingredient">-</button>';
		echo '</div></div>';
	}

	private function render_step_row( $index, $step, $ingredients ) {
		$title            = $step['title'] ?? '';
		$description      = $step['description'] ?? '';
		$image_id         = isset( $step['image'] ) ? intval( $step['image'] ) : 0;
		$image_url        = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';
		$step_ingredients = $step['ingredients'] ?? [ ['id' => '', 'amount' => ''] ];

		echo '<div class="mixmymeals-step-row" data-step="' . esc_attr( $index ) . '">';
		echo '<h3>' . esc_html( sprintf( __( 'Step %d', 'mixmymeals' ), $index + 1 ) ) . '</h3>';

		echo '<p><label>' . esc_html__( 'Title', 'mixmymeals' ) . '</label><br>';
		echo '<input type="text" class="regular-text" name="mixmymeals_recipe_steps[' . $index . '][title]" value="' . esc_attr( $title ) . '"></p>';

		echo '<p><label>' . esc_html__( 'Description', 'mixmymeals' ) . '</label><br>';
		echo '<textarea rows="3" class="large-text" name="mixmymeals_recipe_steps[' . $index . '][description]">' . esc_textarea( $description ) . '</textarea></p>';

		echo '<div class="mixmymeals-step-ingredients-wrapper">';
		echo '<div class="mixmymeals-step-ingredients">';

		foreach ( $step_ingredients as $i => $ing ) {
			$ing_id = $ing['id'] ?? '';
			$amount = $ing['amount'] ?? '';

			echo '<div class="mixmymeals-step-ingredient">';
			echo '<select name="mixmymeals_recipe_steps[' . $index . '][ingredients][' . $i . '][id]" class="widefat">';
			echo '<option value="">-- ' . esc_html__( 'Select Ingredient', 'mixmymeals' ) . ' --</option>';
			foreach ( $ingredients as $post_ing ) {
				$selected = selected( $ing_id, $post_ing->ID, false );
				echo '<option value="' . esc_attr( $post_ing->ID ) . '" ' . $selected . '>' . esc_html( $post_ing->post_title ) . '</option>';
			}
			echo '</select> ';
			echo '<input type="number" step="1" min="0" name="mixmymeals_recipe_steps[' . $index . '][ingredients][' . $i . '][amount]" value="' . esc_attr( $amount ) . '" class="small-text" placeholder="Amount">';
			echo ' <button type="button" class="button mixmymeals-remove-ingredient">-</button>';
			echo '</div>';
		}

		echo '</div>';
		echo '<button type="button" class="button mixmymeals-add-ingredient">+ ' . esc_html__( 'Add Ingredient', 'mixmymeals' ) . '</button>';
		echo '</div>';

		// Bild
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

		$totals = array_fill_keys( ['calories','fat','carbs','protein','sugar','fiber','salt'], 0 );

		echo '<table class="widefat" style="width:100%; border-collapse:collapse;">';
		echo '<thead><tr>
		<th>' . esc_html__( 'Meal', 'mixmymeals' ) . '</th>
		<th>' . esc_html__( 'Recipe', 'mixmymeals' ) . '</th>
		<th style="text-align:right;">' . esc_html__( 'Servings', 'mixmymeals' ) . '</th>
		<th style="text-align:right;">kcal</th>
		<th style="text-align:right;">Fat</th>
		<th style="text-align:right;">Carbs</th>
		<th style="text-align:right;">Protein</th>
		<th style="text-align:right;">Sugar</th>
		<th style="text-align:right;">Fiber</th>
		<th style="text-align:right;">Salt</th>
	</tr></thead><tbody>';

		foreach ( $meals as $meal ) {
			$meal_obj = new Mixmymeals_Meal( $meal->ID );
			$recipe_id = $meal_obj->get_recipe_id();

			$servings  = max( 1, intval( $meal_obj->get_servings() ) );
			$recipe_obj = new Mixmymeals_Recipe( $recipe_id );
			$steps = $recipe_obj->get_steps();

			// Summiere Zutatenwerte
			// Nutze zentrale Recipe-Methode und übergebe die Portionen
			$nutrition = $recipe_obj->calculate_nutrition_totals( $servings );

			foreach ( $nutrition as $key => $val ) {
				$totals[$key] += $val;
			}

			echo '<tr>';
			echo '<td>' . esc_html( $meal->post_title ?: sprintf( __( 'Meal #%d', 'mixmymeals' ), $meal->ID ) ) . '</td>';
			echo '<td>' . esc_html( get_the_title( $recipe_id ) ) . '</td>';
			echo '<td style="text-align:right;">' . esc_html( $servings ) . '</td>';
			echo '<td style="text-align:right;">' . esc_html( round( $nutrition['calories'] ) ) . '</td>';
			echo '<td style="text-align:right;">' . esc_html( round( $nutrition['fat'], 1 ) ) . '</td>';
			echo '<td style="text-align:right;">' . esc_html( round( $nutrition['carbs'], 1 ) ) . '</td>';
			echo '<td style="text-align:right;">' . esc_html( round( $nutrition['protein'], 1 ) ) . '</td>';
			echo '<td style="text-align:right;">' . esc_html( round( $nutrition['sugar'], 1 ) ) . '</td>';
			echo '<td style="text-align:right;">' . esc_html( round( $nutrition['fiber'], 1 ) ) . '</td>';
			echo '<td style="text-align:right;">' . esc_html( round( $nutrition['salt'], 1 ) ) . '</td>';
			echo '</tr>';
		}

		echo '<tr style="font-weight:bold; border-top:1px solid #ccc;">
		<td colspan="3">' . esc_html__( 'Total', 'mixmymeals' ) . '</td>
		<td style="text-align:right;">' . esc_html( round( $totals['calories'] ) ) . '</td>
		<td style="text-align:right;">' . esc_html( round( $totals['fat'], 1 ) ) . '</td>
		<td style="text-align:right;">' . esc_html( round( $totals['carbs'], 1 ) ) . '</td>
		<td style="text-align:right;">' . esc_html( round( $totals['protein'], 1 ) ) . '</td>
		<td style="text-align:right;">' . esc_html( round( $totals['sugar'], 1 ) ) . '</td>
		<td style="text-align:right;">' . esc_html( round( $totals['fiber'], 1 ) ) . '</td>
		<td style="text-align:right;">' . esc_html( round( $totals['salt'], 1 ) ) . '</td>
	</tr>';

		echo '</tbody></table>';
	}

	/**
	 * Helper to sum nutrition values for a recipe's steps × servings.
	 */
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

		echo '<h4>' . esc_html__( 'Calories', 'mixmymeals' ) . '</h4>';
		echo '<p><label><strong>' . esc_html__( 'Calories per 100 g', 'mixmymeals' ) . '</strong></label><br>';
		echo '<input type="number" step="1" min="0" name="rmm_calories_100" value="' . esc_attr( $values['calories_100'] ?? '' ) . '" class="small-text"> kcal</p>';

		echo '<p><label><strong>' . esc_html__( 'Average weight per item', 'mixmymeals' ) . '</strong></label><br>';
		echo '<input type="number" step="1" min="0" name="rmm_weight_item" value="' . esc_attr( $values['weight_item'] ?? '' ) . '" class="small-text"> g</p>';

		echo '<p><strong>' . esc_html__( 'Calories per item', 'mixmymeals' ) . ': ' . esc_html__( 'ca.', 'mixmymeals' ) . ' <span class="rmm_calories_item">—</span> kcal</strong></p>';

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
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( ! current_user_can( 'edit_post', $post_ID ) ) return;

		$data = [
			'calories_100' => $_POST['rmm_calories_100'] ?? '',
			'weight_item'  => $_POST['rmm_weight_item'] ?? '',
			'fat'          => $_POST['rmm_fat'] ?? '',
			'carbs'        => $_POST['rmm_carbs'] ?? '',
			'protein'      => $_POST['rmm_protein'] ?? '',
			'sugar'        => $_POST['rmm_sugar'] ?? '',
			'fiber'        => $_POST['rmm_fiber'] ?? '',
			'salt'         => $_POST['rmm_salt'] ?? '',
		];

		$ingredient_obj = new Mixmymeals_Ingredient( $post_ID );
		$ingredient_obj->set_nutrition( $data );
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

		// Zutaten aus allen Steps summieren
		foreach ( $steps as $step ) {
			foreach ( $step['ingredients'] ?? [] as $ing ) {
				if ( empty( $ing['id'] ) || empty( $ing['amount'] ) ) continue;
				$ing_id = intval( $ing['id'] );
				$amount = floatval( $ing['amount'] );
				$ingredient_totals[ $ing_id ] = ( $ingredient_totals[ $ing_id ] ?? 0 ) + $amount;
			}
		}

		if ( empty( $ingredient_totals ) ) {
			echo '<p>' . esc_html__( 'No ingredient selected', 'mixmymeals' ) . '</p>';
			return;
		}

		// Totals initialisieren
		$totals = array_fill_keys( ['calories','fat','carbs','protein','sugar','fiber','salt'], 0 );
		$rows = [];

		foreach ( $ingredient_totals as $ing_id => $amount ) {
			$ing_obj    = new Mixmymeals_Ingredient( $ing_id );
			$nutrition  = $ing_obj->get_nutrition();

			if ( empty( $nutrition ) ) continue;

			$weight_item  = floatval( $nutrition['weight_item'] ?? 0 );
			if ( $weight_item <= 0 ) $weight_item = 100; // fallback, damit keine Division 0 passiert

			$factor = floatval( $amount ) * $weight_item / 100;

			$row = [
				'name'     => get_the_title( $ing_id ),
				'amount'   => $amount,
				'calories' => $factor * floatval( $nutrition['calories_100'] ?? 0 ),
				'fat'      => $factor * floatval( $nutrition['fat'] ?? 0 ),
				'carbs'    => $factor * floatval( $nutrition['carbs'] ?? 0 ),
				'protein'  => $factor * floatval( $nutrition['protein'] ?? 0 ),
				'sugar'    => $factor * floatval( $nutrition['sugar'] ?? 0 ),
				'fiber'    => $factor * floatval( $nutrition['fiber'] ?? 0 ),
				'salt'     => $factor * floatval( $nutrition['salt'] ?? 0 ),
			];

			foreach ( $totals as $key => $_ ) {
				$totals[$key] += $row[$key];
			}

			$rows[] = $row;
		}

		// Tabelle ausgeben
		echo '<table class="mixmymeals-shopping-list-table" style="width:100%; border-collapse:collapse;">';
		echo '<thead><tr>
		<th>' . esc_html__( 'Ingredient', 'mixmymeals' ) . '</th>
		<th style="text-align:right;">' . esc_html__( 'Quantity', 'mixmymeals' ) . '</th>
		<th style="text-align:right;">kcal</th>
		<th style="text-align:right;">Fat</th>
		<th style="text-align:right;">Carbs</th>
		<th style="text-align:right;">Protein</th>
		<th style="text-align:right;">Sugar</th>
		<th style="text-align:right;">Fiber</th>
		<th style="text-align:right;">Salt</th>
	</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			echo '<tr>';
			echo '<td>' . esc_html( $row['name'] ) . '</td>';
			echo '<td style="text-align:right;">' . esc_html( $row['amount'] ) . '</td>';
			echo '<td style="text-align:right;">' . esc_html( round( $row['calories'] ) ) . '</td>';
			echo '<td style="text-align:right;">' . esc_html( round( $row['fat'], 1 ) ) . '</td>';
			echo '<td style="text-align:right;">' . esc_html( round( $row['carbs'], 1 ) ) . '</td>';
			echo '<td style="text-align:right;">' . esc_html( round( $row['protein'], 1 ) ) . '</td>';
			echo '<td style="text-align:right;">' . esc_html( round( $row['sugar'], 1 ) ) . '</td>';
			echo '<td style="text-align:right;">' . esc_html( round( $row['fiber'], 1 ) ) . '</td>';
			echo '<td style="text-align:right;">' . esc_html( round( $row['salt'], 1 ) ) . '</td>';
			echo '</tr>';
		}

		echo '<tr style="font-weight:bold; border-top:1px solid #ccc;">
		<td>' . esc_html__( 'Total', 'mixmymeals' ) . '</td>
		<td></td>
		<td style="text-align:right;">' . esc_html( round( $totals['calories'] ) ) . '</td>
		<td style="text-align:right;">' . esc_html( round( $totals['fat'], 1 ) ) . '</td>
		<td style="text-align:right;">' . esc_html( round( $totals['carbs'], 1 ) ) . '</td>
		<td style="text-align:right;">' . esc_html( round( $totals['protein'], 1 ) ) . '</td>
		<td style="text-align:right;">' . esc_html( round( $totals['sugar'], 1 ) ) . '</td>
		<td style="text-align:right;">' . esc_html( round( $totals['fiber'], 1 ) ) . '</td>
		<td style="text-align:right;">' . esc_html( round( $totals['salt'], 1 ) ) . '</td>
	</tr></tbody></table>';
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

		// Alle verfügbaren Meal-Pläne abrufen
		/*
		$plans = get_posts([
			'post_type'      => 'mmm_meal_plan',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'date',
			'order'          => 'DESC',
		]);
		*/
		$plans = Mixmymeals_Meal_Plan::get_all_meal_plans();

		// Alle verfügbaren Rezepte abrufen
		/*
		$recipes = get_posts([
			'post_type'      => 'mixmymeals_recipe',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
		]);
		*/
		$recipes = Mixmymeals_Recipe::get_all_recipes();

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

		echo '<p><label><strong>' . esc_html__( 'Servings', 'mixmymeals' ) . '</strong></label><br>';
		echo '<input type="number" name="servings" value="' . esc_attr( $servings ?: 1 ) . '" min="1" step="1" style="width:100px;"></p>';

		echo '<p><label><strong>' . esc_html__( 'Note (optional)', 'mixmymeals' ) . '</strong></label><br>';
		echo '<textarea name="note" rows="2" style="width:100%;">' . esc_textarea( $note ) . '</textarea></p>';
	}

	public function save_meal_meta( $post_id ) {

		// Setting flag to prevent endless loop due to recursive hook callings:
		if ( self::$updating_meal_meta ) return;
		self::$updating_meal_meta = true;

		// Nonce prüfen
		if (
			! isset( $_POST['mmm_meal_meta_nonce'] ) ||
			! wp_verify_nonce( $_POST['mmm_meal_meta_nonce'], 'mmm_meal_meta_box' )
		) {
			return;
		}

		// Autosave und Berechtigung prüfen
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		// Handler-Objekt erstellen
		$meal_obj = new Mixmymeals_Meal( $post_id );

		// Parent ID speichern
		if ( isset( $_POST['mmm_parent_id'] ) ) {
			$meal_obj->set_parent_plan_id( $_POST['mmm_parent_id'] );
		}

		// Recipe ID speichern
		if ( isset( $_POST['recipe_id'] ) ) {
			$meal_obj->set_recipe_id( $_POST['recipe_id'] );
		}

		// Servings speichern
		if ( isset( $_POST['servings'] ) ) {
			$meal_obj->set_servings( $_POST['servings'] );
		}

		// Note speichern
		if ( isset( $_POST['note'] ) ) {
			$meal_obj->set_note( $_POST['note'] );
		}

		self::$updating_meal_meta = false;
	}


}
