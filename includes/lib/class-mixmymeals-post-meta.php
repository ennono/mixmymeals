<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mixmymeals_Post_Meta {

	// Flags to prevent endless loops:
	private static bool $updating_meal_meta        = false;
	private static bool $updating_ingr_amount_meta = false;


	public function __construct() {

		// add_action( 'add_meta_boxes', [ $this, 'register_plan_entry_metabox' ] );
		// add_action( 'save_post_' . 'mmm_entry', [ $this, 'save_plan_entry_meta' ] );

		add_action(
			'add_meta_boxes_mmm_recipe',
			array( $this, 'add_recipe_steps_meta_box' )
		);
		add_action(
			'save_post_mmm_recipe',
			array( $this, 'save_recipe_steps_meta_box' )
		);
		add_action(
			'add_meta_boxes_mmm_recipe',
			array( $this, 'add_recipe_details_meta_box' )
		);
		add_action(
			'save_post_mmm_recipe',
			array( $this, 'save_recipe_details_meta_box' )
		);
		add_action(
			'add_meta_boxes_mmm_recipe',
			array( $this, 'add_recipe_priority_meta_box' )
		);
		add_action(
			'save_post_mmm_recipe',
			array( $this, 'save_recipe_priority_meta_box' )
		);
		add_action(
			'add_meta_boxes_mmm_recipe',
			array( $this, 'add_recipe_shopping_list_meta_box' )
		);
		add_action(
			'add_meta_boxes_mmm_recipe',
			array( $this, 'add_equipment_meta_box' )
		);
		add_action(
			'add_meta_boxes_mmm_recipe',
			array( $this, 'add_related_recipes_meta_box' )
		);
		add_action(
			'save_post_mmm_recipe',
			array( $this, 'save_related_recipes_metabox' )
		);

		add_action(
			'add_meta_boxes_mmm_ingredient',
			array( $this, 'add_ingredient_meta_box' )
		);
		add_action(
			'add_meta_boxes_mmm_ingredient',
			array( $this, 'add_ingredient_price_meta_box' )
		);
		add_action(
			'add_meta_boxes_mmm_ingredient',
			array( $this, 'add_ingredient_diet_meta_box' )
		);
		add_action(
			'save_post_mmm_ingredient',
			array(
				$this,
				'save_ingredient_meta',
			)
		);
		add_action(
			'save_post_mmm_ingredient',
			array( $this, 'save_ingredient_price_meta' )
		);
		add_action(
			'save_post_mmm_ingredient',
			array( $this, 'save_ingredient_diet_meta' )
		);
		add_action(
			'save_post_mmm_recipe',
			array(
				$this,
				'save_equipment_metabox',
			)
		);

		add_action(
			'add_meta_boxes_mmm_equipment',
			array( $this, 'add_equipment_affiliate_meta_box' )
		);
		add_action(
			'save_post_mmm_equipment',
			array( $this, 'save_equipment_affiliate_meta' )
		);

        /*
		add_action(
			'add_meta_boxes_mmm_ingr_unit',
			array( $this, 'add_ingr_amount_meta_box' )
		);
        */
        /*
		add_action(
			'save_post_mmm_ingr_unit',
			array( $this, 'save_ingr_amount_meta' )
		);
        */
        /*
		add_action(
			'add_meta_boxes_mmm_ingr_unit',
			array( $this, 'add_ingr_amount_parent_meta_box' )
		);
        */
		add_action(
			'add_meta_boxes_mmm_ingredient',
			array( $this, 'add_ingredient_amounts_meta_box' )
		);
		add_action(
			'wp_ajax_mixmymeals_get_ingredient_units',
			array( $this, 'ajax_mixmymeals_get_ingredient_units_handler' )
		);

		add_action(
			'wp_ajax_mixmymeals_update_shopping_list',
			array( $this, 'ajax_update_shopping_list' )
		);

		add_action(
			'add_meta_boxes_mmm_meal_plan',
			array( $this, 'register_meal_plan_days_metabox' )
		);
		add_action(
			'save_post_mmm_meal_plan',
			array( $this, 'save_meal_plan_days_meta' )
		);

		// Author box: same fields, storage and rendering for both post types.
		add_action(
			'add_meta_boxes_mmm_meal_plan',
			array( $this, 'register_meal_plan_author_metabox' )
		);
		add_action(
			'add_meta_boxes_mmm_recipe',
			array( $this, 'register_recipe_author_metabox' )
		);
		add_action( 'save_post_mmm_meal_plan', array( $this, 'save_author_meta' ) );
		add_action( 'save_post_mmm_recipe', array( $this, 'save_author_meta' ) );

		// Image credit: same fields, storage and rendering for both post types.
		foreach ( array( 'mmm_meal_plan', 'mmm_recipe' ) as $mmm_credit_type ) {
			add_action(
				'add_meta_boxes_' . $mmm_credit_type,
				array( $this, 'register_image_credit_metabox' )
			);
			add_action( 'save_post_' . $mmm_credit_type, array( $this, 'save_image_credit_meta' ) );
		}

		// Disclosure: how the content came about commercially. Separate from the
		// author box on purpose — external authorship and paid placement are not
		// the same thing (see DISCLOSURE_TYPE).
		foreach ( array( 'mmm_meal_plan', 'mmm_recipe' ) as $mmm_disclosure_post_type ) {
			add_action(
				'add_meta_boxes_' . $mmm_disclosure_post_type,
				array( $this, 'register_disclosure_metabox' )
			);
			add_action(
				'save_post_' . $mmm_disclosure_post_type,
				array( $this, 'save_disclosure_meta' )
			);
		}

		// Visibility: "unlisted" flag. Query side lives in Mixmymeals_Post_Types.
		foreach ( Mixmymeals_Post_Types::UNLISTED_POST_TYPES as $mmm_visibility_type ) {
			add_action(
				'add_meta_boxes_' . $mmm_visibility_type,
				array( $this, 'register_visibility_metabox' )
			);
			add_action(
				'save_post_' . $mmm_visibility_type,
				array( $this, 'save_visibility_meta' )
			);
		}
		/*
		add_action( 'save_post_mmm_meal_plan', [ $this, 'prevent_overlapping_meal_plans' ], 10, 3 );
		add_action( 'admin_notices', function() {
			if ( isset( $_GET['mmm_overlap'] ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $_GET['mmm_overlap'] ) . '</p></div>';
			}
		});
		*/

		add_action(
			'add_meta_boxes_mmm_meal',
			array( $this, 'register_meal_metabox' )
		);
		add_action( 'save_post_mmm_meal', array( $this, 'save_meal_meta' ) );
		add_action(
			'add_meta_boxes_mmm_meal_plan',
			array( $this, 'add_meal_plan_overview_meta_box' )
		);
	}

	/*
	======================================================
	 *  RECIPE META BOX
	 * ====================================================== */

	public function add_recipe_steps_meta_box(): void {
		add_meta_box(
			'mmm_recipe_steps_meta_box',
			__( 'Recipe Steps', 'mixmymeals' ),
			array( $this, 'render_recipe_steps_meta_box_content' ),
			'mmm_recipe',
			'normal'
		);
	}

	public function render_recipe_steps_meta_box_content( WP_Post $post ): void {
		$recipe_obj  = new Mixmymeals_Recipe( $post->ID );
		$steps       = $recipe_obj->get_steps() ?: array();
		$ingredients = Mixmymeals_Ingredient::get_all_ingredients();

		// Portionen-Wert laden oder Default = 1
		$servings = $recipe_obj->get_servings();
		if ( empty( $servings ) ) {
			$servings = 1;
		}

		echo '<div class="mixmymeals-recipe-meta">';

		// --- Nonce-Feld für diese Meta-Box ---
		wp_nonce_field(
			'mmm_recipe_steps_meta_box_action',
			'mmm_recipe_steps_meta_box_nonce'
		);

		// --- Feld für Anzahl der Portionen ---
		echo '<div class="mixmymeals-servings-field" style="margin-bottom:1em;">';
		echo '<label for="mmm_servings" style="font-weight:bold;">'
			. esc_html__( 'Servings', 'mixmymeals' )
			. ':</label><br />';
		echo '<input type="number" min="1" step="1" name="mmm_servings" id="mmm_servings" value="'
			. esc_attr( $servings )
			. '" style="width:80px; text-align:right;" />';
		echo '</div>';

		// --- Schritte-Editor ---
		echo '<div class="mixmymeals-recipe-steps">';
		if ( empty( $steps ) ) {
			$steps[] = array();
		}

		foreach ( $steps as $index => $step ) {
			$this->render_step_row( $index, $step, $ingredients );
		}
		echo '</div>';

		echo '<p><button type="button" id="mixmymeals-add-step" class="button button-secondary">+ '
			. esc_html__( 'Add Step', 'mixmymeals' ) . '</button></p>';

		// --- Template für neue Zutaten (JS) ---
		echo '<div id="mixmymeals-ingredient-template" style="display:none;">';
		echo '<div class="mixmymeals-step-ingredient">';
		echo '<select class="widefat">';
		echo '<option value="">-- ' . esc_html__(
			'Select Ingredient',
			'mixmymeals'
		) . ' --</option>';
		foreach ( $ingredients as $ing ) {
			echo '<option value="' . esc_attr( $ing->ID ) . '">'
				. esc_html( $ing->post_title ) . '</option>';
		}
		echo '</select> ';
		echo '<input type="number" step="0.1" min="0" class="small-text" placeholder="Amount">';
		echo ' <button type="button" class="button mixmymeals-remove-ingredient">-</button>';
		echo '</div></div>';

		echo '</div>';
	}

	private function render_step_row( int $index, array $step, array $ingredients ): void {
		$title            = $step['title'] ?? '';
		$description      = $step['description'] ?? '';
		$image_id         = isset( $step['image'] ) ? intval( $step['image'] )
			: 0;
		$image_url        = $image_id ? wp_get_attachment_image_url( $image_id ) : '';
		$step_ingredients = $step['ingredients'] ?? array(
			array(
				'id'        => '',
				'amount'    => '',
				'amount_id' => '',
			),
		);

		echo '<div class="mixmymeals-step-row" data-step="' . esc_attr( $index )
			. '">';
		// translators: Number of step
		echo '<h3>' . esc_html(
			sprintf(
				__( 'Step %d', 'mixmymeals' ),
				$index + 1
			)
		) . '</h3>';

		// Title
		echo '<p><label>' . esc_html__( 'Title', 'mixmymeals' )
			. '</label><br>';
		echo '<input type="text" class="regular-text" name="mmm_recipe_steps['
			. absint( $index ) . '][title]" value="' . esc_attr( $title )
			. '"></p>';

		// Description. A slim TinyMCE editor is attached to this textarea in
		// admin.js via the wp.editor API. The id must be unique (one editor per
		// step) and the marker class lets the JS find the step editors.
		echo '<p><label>' . esc_html__( 'Description', 'mixmymeals' )
			. '</label><br>';
		echo '<textarea rows="3" class="large-text mmm-step-description" id="mmm_step_desc_'
			. absint( $index ) . '" name="mmm_recipe_steps['
			. absint( $index ) . '][description]">'
			. esc_textarea( $description ) . '</textarea></p>';

		// Ingredients
		echo '<div class="mixmymeals-step-ingredients-wrapper">';
		echo '<div class="mixmymeals-step-ingredients">';

		foreach ( $step_ingredients as $i => $ing ) {
			$ing_id             = $ing['id'] ?? '';
			$amount             = $ing['amount'] ?? '';
			$selected_amount_id = $ing['amount_id'] ?? '';

			echo '<div class="mixmymeals-step-ingredient">';

			// Ingredient select
			echo '<select name="mmm_recipe_steps[' . absint( $index )
				. '][ingredients][' . absint( $i )
				. '][id]" class="widefat ingredient-select">';
			echo '<option value="">-- ' . esc_html__(
				'Select Ingredient',
				'mixmymeals'
			) . ' --</option>';

			foreach ( $ingredients as $post_ing ) {
				//$ingredient_obj = new Mixmymeals_Ingredient( $post_ing->ID );

				$ingredient_obj = new Mixmymeals_Ingredient( $post_ing->ID );

				if ( ! $ingredient_obj->is_valid() ) {
					continue;
				}

				$nutrition      = $ingredient_obj->get_nutrition();
				$weight_item    = floatval( $nutrition['weight_item'] ?? 0 );
				$weight_label   = $weight_item > 0 ? ' (' . $weight_item . ' g)'
					: '';
				$sel            = selected( $ing_id, $post_ing->ID, false );

				echo '<option value="' . absint( $post_ing->ID ) . '" '
					. esc_attr( $sel ) . '>' .
					esc_html( $post_ing->post_title . $weight_label ) .
					'</option>';
			}
			echo '</select> ';

			// Amount input
			echo '<input type="number" step="0.1" min="0" name="mmm_recipe_steps['
				. absint( $index ) . '][ingredients][' . absint( $i )
				. '][amount]" value="' . esc_attr( $amount )
				. '" class="small-text" placeholder="Amount"> ';

			// Amount/Unit select
			echo '<select name="mmm_recipe_steps[' . absint( $index )
				. '][ingredients][' . absint( $i )
				. '][amount_id]" class="small-text ingredient-unit-select">';
			$sel_gramm = selected( $selected_amount_id, '', false );
			echo '<option value="" ' . esc_attr( $sel_gramm ) . '>'
				. esc_html__( 'Gramm (g)', 'mixmymeals' ) . '</option>';

			//if ( $ing_id ) {
			if ( $ing_id && Mixmymeals_Ingredient::exists_by_id( $ing_id ) ) {
				$amounts = get_posts(
					array(
						'post_type'   => 'mmm_ingr_unit',
						'post_status' => 'any',
						'post_parent' => intval( $ing_id ),
						'numberposts' => - 1,
						'orderby'     => 'title',
						'order'       => 'ASC',
					)
				);

				foreach ( $amounts as $amt_post ) {
					$amount_obj
							= new Mixmymeals_Ingredient_Amount( $amt_post->ID );
					$weight     = $amount_obj->get_weight();
					$sel_amt    = selected(
						$selected_amount_id,
						$amt_post->ID,
						false
					);

					echo '<option value="' . absint( $amt_post->ID ) . '" '
						. esc_attr( $sel_amt ) . '>' .
						esc_html(
							$amount_obj->get_title() . ( $weight ? ' ('
																			. $weight
																			. ' g)'
								: '' )
						) .
						'</option>';
				}
			}
			echo '</select> ';

			// Remove button
			echo '<button type="button" class="button mixmymeals-remove-ingredient">-</button>';
			echo '</div>';
		}

		echo '</div>';
		echo '<button type="button" class="button mixmymeals-add-ingredient">+ '
			. esc_html__( 'Add Ingredient', 'mixmymeals' ) . '</button>';
		echo '</div>';

		// Image
		echo '<div class="mixmymeals-step-image">';
		echo '<label>' . esc_html__( 'Image', 'mixmymeals' ) . '</label><br>';
		echo '<img class="mixmymeals-image-preview" src="'
			. esc_url( $image_url ) . '" style="max-width:100px;'
			. ( $image_url ? '' : 'display:none;' ) . '">';
		echo '<input type="hidden" name="mmm_recipe_steps[' . absint( $index )
			. '][image]" class="mixmymeals-image-id" value="'
			. esc_attr( $image_id ) . '"><br>';
		echo '<button type="button" class="button mixmymeals-select-image">'
			. esc_html__( 'Select Image', 'mixmymeals' ) . '</button> ';
		echo '<button type="button" class="button mixmymeals-remove-image" '
			. ( $image_url ? '' : 'style="display:none;"' ) . '>'
			. esc_html__( 'Remove', 'mixmymeals' ) . '</button>';
		echo '</div>';

		echo '<p class="mixmymeals-step-actions"><a href="#" class="mixmymeals-delete-step">'
			. esc_html__( 'Delete Step', 'mixmymeals' ) . '</a></p>';
		echo '<hr></div>';
	}

	public function add_recipe_details_meta_box(): void {
		add_meta_box(
			'mmm_recipe_deatils_meta_box',
			__( 'Details', 'mixmymeals' ),
			array( $this, 'render_recipe_details_meta_box_content' ),
			'mmm_recipe',
			'side'
		);
	}

	public function render_recipe_details_meta_box_content( WP_Post $post ): void {

		// Nonce für Sicherheit
		wp_nonce_field( 'mmm_recipe_details_nonce', 'mmm_recipe_details_nonce_field' );

		$recipe           = new Mixmymeals_Recipe( $post->ID );
		$preparation_time = $recipe->get_preparation_time();
		?>
		<p>
			<label for="mmm_preparation_time">
				<?php esc_html_e( 'Preparation Time (Minutes)', 'mixmymeals' ); ?>
			</label>
		</p>
		<p>
			<input
				type="number"
				id="mmm_preparation_time"
				name="mmm_preparation_time"
				value="<?php echo esc_attr( $preparation_time ); ?>"
				min="0"
				step="1"
				style="width:100%;"
			/>
		</p>
		<p>
			<label for="mmm_is_favorite">
				<input
					type="checkbox"
					id="mmm_is_favorite"
					name="mmm_is_favorite"
					value="1"
					<?php checked( '1', (string) get_post_meta( $post->ID, '_mmm_is_favorite', true ) ); ?>
				/>
				<?php esc_html_e( 'Mark as favorite (featured in the recipe archive)', 'mixmymeals' ); ?>
			</label>
		</p>
		<p>
			<label for="mmm_cook_count">
				<?php esc_html_e( 'Cook counter (times cooked)', 'mixmymeals' ); ?>
			</label>
		</p>
		<p>
			<input
				type="number"
				id="mmm_cook_count"
				name="mmm_cook_count"
				value="<?php echo esc_attr( (string) $recipe->get_cook_count() ); ?>"
				min="0"
				step="1"
				style="width:100%;"
			/>
			<span class="description">
				<?php
				printf(
					/* translators: %d: how often the recipe sits on a published meal plan. */
					esc_html__( 'Freely editable, zero included. The counter keeps counting on from the value you enter whenever the recipe lands on a published meal plan. Currently planned: %d times.', 'mixmymeals' ),
					$recipe->count_cooked()
				);
				?>
			</span>
		</p>
		<?php
	}

	public function save_recipe_details_meta_box( int $post_ID ): void {

		// Nonce prüfen
		if (
			! isset( $_POST['mmm_recipe_details_nonce_field'] ) ||
			! wp_verify_nonce(
				$_POST['mmm_recipe_details_nonce_field'],
				'mmm_recipe_details_nonce'
			)
		) {
			return;
		}

		// Autosave / Revision / Rechte prüfen
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_ID ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_ID ) ) {
			return;
		}

		// Wert speichern
		if ( isset( $_POST['mmm_preparation_time'] ) ) {
			$recipe = new Mixmymeals_Recipe( $post_ID );
			$recipe->set_preparation_time( $_POST['mmm_preparation_time'] );
		}

		// Favorite flag (featured in the recipe archive).
		if ( ! empty( $_POST['mmm_is_favorite'] ) ) {
			update_post_meta( $post_ID, '_mmm_is_favorite', '1' );
		} else {
			delete_post_meta( $post_ID, '_mmm_is_favorite' );
		}

		// Cook counter. Only written when the submitted total actually differs
		// from the stored one, so re-saving a recipe never rewrites the starting
		// point behind the editor's back.
		if ( isset( $_POST['mmm_cook_count'] ) ) {
			$mmm_total  = trim( (string) wp_unslash( $_POST['mmm_cook_count'] ) );
			$mmm_recipe = new Mixmymeals_Recipe( $post_ID );

			if ( '' !== $mmm_total && absint( $mmm_total ) !== $mmm_recipe->get_cook_count() ) {
				$mmm_recipe->set_cook_total( absint( $mmm_total ) );
			}
		}
	}

	public function add_recipe_priority_meta_box(): void {
		add_meta_box(
			'mmm_recipe_priority_meta_box',
			__( 'Priority', 'mixmymeals' ),
			array( $this, 'render_recipe_priority_meta_box_content' ),
			'mmm_recipe',
			'side'
		);
	}

	public function render_recipe_priority_meta_box_content( WP_Post $post ): void {
		wp_nonce_field( 'mmm_recipe_priority_nonce', 'mmm_recipe_priority_nonce_field' );
		$recipe   = new Mixmymeals_Recipe( $post->ID );
		$priority = $recipe->get_priority();
		?>
		<p>
			<input
				type="number"
				id="mmm_priority"
				name="mmm_priority"
				value="<?php echo esc_attr( $priority ); ?>"
				min="0"
				step="1"
				style="width:80px;"
			/>
		</p>
		<p><small><?php esc_html_e( 'Lower value = image shown first per day.', 'mixmymeals' ); ?></small></p>
		<?php
	}

	public function save_recipe_priority_meta_box( int $post_ID ): void {
		if (
			! isset( $_POST['mmm_recipe_priority_nonce_field'] ) ||
			! wp_verify_nonce(
				sanitize_key( wp_unslash( $_POST['mmm_recipe_priority_nonce_field'] ) ),
				'mmm_recipe_priority_nonce'
			)
		) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_ID ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_ID ) ) {
			return;
		}
		if ( isset( $_POST['mmm_priority'] ) ) {
			$recipe = new Mixmymeals_Recipe( $post_ID );
			$recipe->set_priority( absint( wp_unslash( $_POST['mmm_priority'] ) ) );
		}
	}

	public function save_recipe_steps_meta_box( int $post_ID ): void {

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_ID ) ) {
			return;
		}

		// Nonce prüfen
		if ( ! isset( $_POST['mmm_recipe_steps_meta_box_nonce'] ) ) {
			return;
		}

		$nonce
			= sanitize_text_field( wp_unslash( $_POST['mmm_recipe_steps_meta_box_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'mmm_recipe_steps_meta_box_action' ) ) {
			return;
		}

		$recipe_obj = new Mixmymeals_Recipe( $post_ID );

		// Portionen speichern
		if ( isset( $_POST['mmm_servings'] ) ) {
			$recipe_obj->set_servings( floatval( wp_unslash( $_POST['mmm_servings'] ) ) );
		}

		if ( isset( $_POST['mmm_recipe_steps'] )
			&& is_array( $_POST['mmm_recipe_steps'] ) ) {

			$sanitized_post_array = array_map(
				function ( $step ) {
					return array(
						'title'       => sanitize_text_field(
							wp_unslash(
								$step['title']
																			??
																			''
							)
						),
						'description' => wp_kses_post(
							wp_unslash(
								$step['description']
																			??
																			''
							)
						),
						'ingredients' => array_map(
							function ( $ingredient ) {
								return array(
									'id'     => isset( $ingredient['id'] )
										? intval( wp_unslash( $ingredient['id'] ) )
										: 0,
									'amount' => isset( $ingredient['amount'] )
										? floatval( wp_unslash( $ingredient['amount'] ) )
										: 0,
									'amount_id' => isset( $ingredient['amount_id'] )
										? intval( wp_unslash( $ingredient['amount_id'] ) )
										: 0,
								);
							},
							$step['ingredients'] ?? array()
						),
					);
				},
				// THIS IS PERFECTLY SANITIZED ABOVE!
				wp_unslash( $_POST['mmm_recipe_steps'] )
			);

			// Leere Schritte herausfiltern
			$steps = array_filter(
				$sanitized_post_array,
				function ( $step ) {
					$hasTitle       = ! empty( $step['title'] );
					$hasDescription = ! empty( $step['description'] );
					$hasIngredients = ! empty(
						array_filter(
							$step['ingredients'],
							fn( $i ) => ! empty( $i['id'] )
										&& ! empty( $i['amount'] )
						)
					);

					return $hasTitle || $hasDescription || $hasIngredients;
				}
			);

			$recipe_obj->set_steps( $steps );
		} else {
			$recipe_obj->purge_steps();
		}

	}


	/*
	======================================================
	*  MEAL PLAN META BOX (Overview of linked meals)
	* ====================================================== */

	public function add_meal_plan_overview_meta_box(): void {
		add_meta_box(
			'mixmymeals_plan_overview_box',
			__( 'Meal Overview', 'mixmymeals' ),
			array( $this, 'render_meal_plan_overview_meta_box' ),
			'mmm_meal_plan',
			'normal'
		);
	}

	public function render_meal_plan_overview_meta_box( WP_Post $post ): void {
		$meals = get_children(
			array(
				'post_parent' => $post->ID,
				'post_type'   => 'mmm_meal',
				'post_status' => 'any',
				'orderby'     => 'menu_order',
				'order'       => 'ASC',
			)
		);

		if ( empty( $meals ) ) {
			echo '<p>' . esc_html__(
				'No meals linked to this plan yet.',
				'mixmymeals'
			) . '</p>';

			return;
		}

		echo '<table class="widefat" style="width:100%; border-collapse:collapse;">';
		echo '<thead><tr>
		<th>' . esc_html__( 'Meal', 'mixmymeals' ) . '</th>
		<th>' . esc_html__( 'Recipe', 'mixmymeals' ) . '</th>
		<th style="text-align:right;">' . esc_html__( 'Servings', 'mixmymeals' )
			. '</th>
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
			// translators: Meal ID
			echo '<td>' . esc_html(
				$meal->post_title
				?: sprintf( __( 'Meal #%d', 'mixmymeals' ), $meal->ID )
			)
				. '</td>';

			echo '<td>';
			if ( $recipe_link ) {
				echo '<a href="' . esc_url( $recipe_link )
					. '" target="_blank"><strong>'
					. esc_html( get_the_title( $recipe_id ) )
					. '</strong></a>';
			} else {
				echo esc_html( get_the_title( $recipe_id ) );
			}
			echo '</td>';

			echo '<td style="text-align:right;">' . esc_html( $servings )
				. '</td>';
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


	/*
	======================================================
	 *  INGREDIENT META BOX
	 * ====================================================== */

	public function add_ingredient_meta_box(): void {
		add_meta_box(
			'mixmymeals_ingredient_meta_box',
			__( 'Nutrition Facts', 'mixmymeals' ),
			array( $this, 'render_ingredient_meta_box_content' ),
			'mmm_ingredient',
			'side'
		);
	}

	public function render_ingredient_meta_box_content( WP_Post $post ): void {
		$ingredient_obj = new Mixmymeals_Ingredient( $post->ID );
		$values         = $ingredient_obj->get_nutrition();

		wp_nonce_field(
			'rmm_ingredient_meta_box',
			'rmm_ingredient_meta_nonce'
		);

		echo '<table class="form-table mixmymeals-meta-table"><tbody>';

		// --- Calories ---
		echo '<tr>';
		echo '<th scope="row"><label for="rmm_calories_100">'
			. esc_html__( 'Calories (per 100 g)', 'mixmymeals' )
			. '</label></th>';
		echo '<td><input type="number" step="1" min="0" name="rmm_calories_100" id="rmm_calories_100" value="'
			. esc_attr( $values['calories_100'] ?? '' )
			. '" class="small-text"> <span class="unit">kcal</span></td>';
		echo '</tr>';

		echo '</tbody></table>';

		echo '<h4 class="mixmymeals-section-title">'
			. esc_html__( 'Macronutrients (per 100 g)', 'mixmymeals' )
			. '</h4>';

		echo '<table class="form-table mixmymeals-meta-table"><tbody>';

		$nutrition = array(
			'fat'     => __( 'Fat (g)', 'mixmymeals' ),
			'carbs'   => __( 'Carbohydrates (g)', 'mixmymeals' ),
			'protein' => __( 'Protein (g)', 'mixmymeals' ),
			'sugar'   => __( 'Sugar (g)', 'mixmymeals' ),
			'fiber'   => __( 'Fiber (g)', 'mixmymeals' ),
			'salt'    => __( 'Salt (g)', 'mixmymeals' ),
		);

		foreach ( $nutrition as $key => $label ) {
			echo '<tr>';
			echo '<th scope="row"><label for="rmm_' . esc_attr( $key ) . '">'
				. esc_html( $label ) . '</label></th>';
			echo '<td><input type="number" step="0.01" min="0" name="rmm_'
				. esc_attr( $key ) . '" id="rmm_' . esc_attr( $key )
				. '" value="' . esc_attr( $values[ $key ] ?? '' )
				. '" class="small-text"></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	public function save_ingredient_meta( int $post_ID ): void {

		// Sicherheitsprüfungen
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_ID ) ) {
			return;
		}
		if (
			! isset( $_POST['mmm_ingredient_amounts_meta_nonce'] )
			|| ! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['mmm_ingredient_amounts_meta_nonce'] ) ),
				'mmm_ingredient_amounts_meta_box'
			)
		) {
			return;
		}

		// ----------------- Nutrition -----------------
		$data = array(
			'calories_100' => isset( $_POST['rmm_calories_100'] )
				? floatval( wp_unslash( $_POST['rmm_calories_100'] ) ) : '',
			'fat'          => isset( $_POST['rmm_fat'] )
				? floatval( wp_unslash( $_POST['rmm_fat'] ) ) : '',
			'carbs'        => isset( $_POST['rmm_carbs'] )
				? floatval( wp_unslash( $_POST['rmm_carbs'] ) ) : '',
			'protein'      => isset( $_POST['rmm_protein'] )
				? floatval( wp_unslash( $_POST['rmm_protein'] ) ) : '',
			'sugar'        => isset( $_POST['rmm_sugar'] )
				? floatval( wp_unslash( $_POST['rmm_sugar'] ) ) : '',
			'fiber'        => isset( $_POST['rmm_fiber'] )
				? floatval( wp_unslash( $_POST['rmm_fiber'] ) ) : '',
			'salt'         => isset( $_POST['rmm_salt'] )
				? floatval( wp_unslash( $_POST['rmm_salt'] ) ) : '',
		);

		$ingredient_obj = new Mixmymeals_Ingredient( $post_ID );
		$ingredient_obj->set_nutrition( $data );

		// ----------------- Ingredient Amounts -----------------
		if ( isset( $_POST['mmm_ingredient_amounts'] )
			&& is_array( $_POST['mmm_ingredient_amounts'] ) ) {
			$amounts
				= wp_unslash( $_POST['mmm_ingredient_amounts'] ); // 🟢 Array unslashen

			foreach ( $amounts as $id => $amount_data ) {
				$id = sanitize_key( $id ); // ID absichern

				// Bestehende Menge
				if ( $id !== 'new' ) {
					$amount_obj
						= new Mixmymeals_Ingredient_Amount( intval( $id ) );

					// Löschen
					if ( ! empty( $amount_data['delete'] ) ) {
						$amount_obj->delete();
						continue;
					}

					// Titel aktualisieren
					if ( isset( $amount_data['title'] ) ) {
						$title = sanitize_text_field( $amount_data['title'] );
						$amount_obj->set_title( $title );
					}

					// Gewicht aktualisieren
					if ( isset( $amount_data['weight'] ) ) {
						$amount_obj->set_weight( floatval( $amount_data['weight'] ) );
					}

					// Unteilbarkeit setzen
					$is_indivisible = ! empty( $amount_data['indivisible'] );
					$amount_obj->set_indivisible( $is_indivisible );

				} else {
					// Neue Menge anlegen
					if ( ! empty( $amount_data['title'] ) ) {
						$title      = sanitize_text_field( $amount_data['title'] );
						$amount_obj
								= Mixmymeals_Ingredient_Amount::create(
									$post_ID,
									$title
								);

						if ( isset( $amount_data['weight'] ) ) {
							$amount_obj->set_weight( floatval( $amount_data['weight'] ) );
						}

						// Unteilbarkeit setzen
						$amount_obj->set_indivisible( ! empty( $amount_data['indivisible'] ) );
					}
				}
			}
		}
	}

	/*
	======================================================
	 *  SHOPPING LIST META BOX
	 * ====================================================== */

	public function add_recipe_shopping_list_meta_box(): void {
		add_meta_box(
			'mmm_recipe_shopping_list',
			__( 'Shopping List', 'mixmymeals' ),
			array( $this, 'render_recipe_shopping_list_meta_box' ),
			'mmm_recipe',
			'normal'
		);
	}

	public function render_recipe_shopping_list_meta_box( WP_Post $post ): void {
		echo '<div id="mixmymeals-shopping-list-container">';
		$this->render_recipe_shopping_list_table( $post->ID );
		echo '</div>';
	}

	/**
	 * Render the recipe shopping list and nutrition tables (Admin meta / vergleichbares).
	 *
	 * - Aggregiert mehrfach vorkommende Zutaten und summiert Gewicht.
	 * - Gibt den Gesamtbedarf je Zutat aus:
	 *   "Zutatname, 750 g"
	 *   - Untereinträge: "2 × EL (30 g)" etc. (Unit fallback: "g")
	 *
	 * @param   int $post_id  Recipe post ID
	 *
	 * @return void (echo)
	 */
	private function render_recipe_shopping_list_table( int $post_id ): void {
		$recipe      = new Mixmymeals_Recipe( $post_id );
		$ingredients = $recipe->get_ingredients_normalized();

		// ---------------------------------
		// Falls keine Zutaten vorhanden
		// ---------------------------------
		if ( empty( $ingredients ) ) {
			echo '<p>'
				. esc_html__(
					'Please save the recipe to update the shopping list.',
					'mixmymeals'
				) . '</p>';

			return;
		}

		// ---------------------------------
		// Zutaten aggregieren (mehrfache Vorkommen summieren)
		// ---------------------------------
		$ingredient_totals = array();

		foreach ( $ingredients as $ing ) {

            /*
			if ( empty( $ing['id'] ) ) {
				continue;
			}
            */
			$ing_id = intval( $ing['id'] );
			if ( ! $ing_id || ! Mixmymeals_Ingredient::exists_by_id( $ing_id ) ) {
				continue;
			}

			$ing_id          = intval( $ing['id'] );
			$weight_g        = floatval(
				$ing['weight_g'] ??
										0
			); // Gewicht dieses Auftretens in g
			$amount          = floatval(
				$ing['amount'] ??
										0
			);   // Menge (z.B. 2, 0.5)
			$unit_title_raw  = trim(
				strval(
					$ing['unit'] ??
											''
				)
			); // z.B. "EL", kann leer sein
			$ingredient_name = $ing['name'] ?? get_the_title( $ing_id );

			// Fallback: wenn Einheit leer, später "g" verwenden — für Eintrag anzeigen wir Einheit "g"
			$unit_title = $unit_title_raw;

			if ( ! isset( $ingredient_totals[ $ing_id ] ) ) {
				$ingredient_totals[ $ing_id ] = array(
					'name'         => $ingredient_name,
					'total_weight' => 0.0,
					'entries'      => array(),
					// einzelne Vorkommen: amount, unit, weight
				);
			}

			$ingredient_totals[ $ing_id ]['total_weight'] += $weight_g;
			$ingredient_totals[ $ing_id ]['entries'][]     = array(
				'amount' => $amount,
				'unit'   => $unit_title,   // raw, eventuell ''
				'weight' => $weight_g,     // in g
			);
		}

		// ---------------------------------
		// Einkaufsliste ausgeben
		// ---------------------------------
		echo '<h3>' . esc_html__( 'Shopping List', 'mixmymeals' ) . '</h3>';
		echo '<ul class="mixmymeals-shopping-list" style="list-style:none; padding-left:0; margin:0;">';

		foreach ( $ingredient_totals as $ing_id => $data ) {

			$total_weight    = (float) $data['total_weight']; // in g
			$ingredient_name = $data['name'];

			// Hauptzeile: Name + Gesamtbedarf in Gramm
			$main_line = '<a href="' . get_edit_post_link( $ing_id ) . '">'
						. esc_html( $ingredient_name ) . '</a>';
			$main_line .= sprintf(
				', %s g',
				esc_html( number_format_i18n( $total_weight, 2 ) )
			);

			// Ausgabe Hauptzeile + Unterliste öffnen
			printf(
				'<li style="padding:6px 0; border-bottom:1px solid #eee;"><strong>%s</strong>',
				wp_kses_post( $main_line )
			);

			// Untereinträge (mehrfache Vorkommen)
			echo '<ul style="margin:6px 0 0 18px; list-style:disc;">';

			foreach ( $data['entries'] as $entry ) {
				$entry_amount = floatval( $entry['amount'] );
				// Formatierte Anzeige der Menge (keine unnötigen Nachkommastellen)
				$decimals       = ( floor( $entry_amount ) != $entry_amount )
					? 2 : 0;
				$display_amount = $entry_amount > 0
					? number_format_i18n( $entry_amount, $decimals ) : '';

				// Einheit mit Fallback
				$unit = trim( strval( $entry['unit'] ?? '' ) );
				if ( $unit === '' ) {
					$unit = 'g';
				}

				$entry_weight = floatval( $entry['weight'] ?? 0 );

				// Gewicht formatieren
				$display_weight = number_format_i18n(
					round( $entry_weight, 2 ),
					2
				);

				if ( $unit != 'g' ) {
					printf(
						'<li>%s × %s (%s g)</li>',
						esc_html( $display_amount ?: '0' ),
						esc_html( $unit ),
						esc_html( $display_weight )
					);
				} else {
					printf(
						'<li>%s g (%s g)</li>',
						esc_html( $display_amount ?: '0' ),
						esc_html( $display_amount ?: '0' )
					);
				}
			}

			echo '</ul></li>';
		}

		echo '</ul>';

		// ---------------------------------
		// Nährwerttabelle (unchanged)
		// ---------------------------------
		$nutrition_per_serving = $recipe->calculate_nutrition_per_serving();
		$nutrition_per_100g    = $recipe->calculate_nutrition_per_100g();

		echo '<h3>' . esc_html__( 'Nutrition Facts', 'mixmymeals' ) . '</h3>';
		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Nutrient', 'mixmymeals' ) . '</th>';
		echo '<th>' . esc_html__( 'per Serving', 'mixmymeals' ) . '</th>';
		echo '<th>' . esc_html__( 'per 100 g', 'mixmymeals' ) . '</th>';
		echo '</tr></thead><tbody>';

		$labels = array(
			'calories' => __( 'Calories (kcal)', 'mixmymeals' ),
			'protein'  => __( 'Protein (g)', 'mixmymeals' ),
			'carbs'    => __( 'Carbohydrates (g)', 'mixmymeals' ),
			'fat'      => __( 'Fat (g)', 'mixmymeals' ),
			'sugar'    => __( 'Sugar (g)', 'mixmymeals' ),
			'fiber'    => __( 'Fiber (g)', 'mixmymeals' ),
			'salt'     => __( 'Salt (g)', 'mixmymeals' ),
		);

		foreach ( $labels as $key => $label ) {
			printf(
				'<tr><th>%s</th><td>%s</td><td>%s</td></tr>',
				esc_html( $label ),
				esc_html( round( $nutrition_per_serving[ $key ] ?? 0, 2 ) ),
				esc_html( round( $nutrition_per_100g[ $key ] ?? 0, 2 ) )
			);
		}

		echo '</tbody></table>';
	}


	/*
	======================================================
	 *  AJAX UPDATE HANDLER
	 * ====================================================== */
	public function ajax_update_shopping_list(): void {
		check_ajax_referer( 'mixmymeals_admin_nonce', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $post_id ) {
			wp_send_json_error( 'Missing post_id.' );
		}

		ob_start();
		$this->render_recipe_shopping_list_table( $post_id );
		$html = ob_get_clean();

		wp_send_json_success( array( 'html' => $html ) );
	}





	/*
	======================================================
	*  MEAL PLAN & MEAL META BOXES
	* ====================================================== */

	/**
	 * Meal Plan Metabox: start date for the week.
	 */
	public function register_meal_plan_days_metabox(): void {
		add_meta_box(
			'mmm_meal_plan_days_meta_box',
			__( 'Meal Plan Settings', 'mixmymeals' ),
			array( $this, 'render_meal_plan_days_metabox' ),
			'mmm_meal_plan',
			'side'
		);
	}

	public function render_meal_plan_days_metabox( WP_Post $post ): void {

		wp_nonce_field( 'mmm_meal_plan_meta_box', 'mmm_meal_plan_meta_nonce' );

		$meal_plan_obj = new Mixmymeals_Meal_Plan( $post->ID );
		$start_day     = $meal_plan_obj->get_start_day();
		$end_day       = $meal_plan_obj->get_end_day();

		// Übersetzbare Wochentage
		$weekdays = array(
			1 => __( 'Monday', 'mixmymeals' ),
			2 => __( 'Tuesday', 'mixmymeals' ),
			3 => __( 'Wednesday', 'mixmymeals' ),
			4 => __( 'Thursday', 'mixmymeals' ),
			5 => __( 'Friday', 'mixmymeals' ),
			6 => __( 'Saturday', 'mixmymeals' ),
			7 => __( 'Sunday', 'mixmymeals' ),
		);

		echo '<table class="form-table mixmymeals-meta-table"><tbody>';

		// --- Start Day ---
		echo '<tr>';
		echo '<th scope="row"><label for="start_day">'
			. esc_html__( 'Start Weekday', 'mixmymeals' ) . '</label></th>';
		echo '<td><select name="start_day" id="start_day" class="regular-text">';

		foreach ( $weekdays as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" '
				. selected( $start_day, $value, false ) . '>'
				. esc_html( $label ) . '</option>';
		}

		echo '</select></td>';
		echo '</tr>';

		// --- End Day ---
		echo '<tr>';
		echo '<th scope="row"><label for="end_day">'
			. esc_html__( 'End Weekday', 'mixmymeals' ) . '</label></th>';
		echo '<td><select name="end_day" id="end_day" class="regular-text">';

		foreach ( $weekdays as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" '
				. selected( $end_day, $value, false ) . '>'
				. esc_html( $label ) . '</option>';
		}

		echo '</select></td>';
		echo '</tr>';

		echo '</tbody></table>';
	}


	public function save_meal_plan_days_meta( int $post_id ): void {

		if (
			! isset( $_POST['mmm_meal_plan_meta_nonce'] )
			||
			! wp_verify_nonce(
				sanitize_key( wp_unslash( $_POST['mmm_meal_plan_meta_nonce'] ) ),
				'mmm_meal_plan_meta_box'
			)
		) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$meal_plan = new Mixmymeals_Meal_Plan( $post_id );

		// --- Datum speichern (falls weiterhin vorhanden) ---
		if ( isset( $_POST['start_date'] ) ) {
			$meal_plan->set_start_date( absint( wp_unslash( $_POST['start_date'] ) ) );
		}

		if ( isset( $_POST['end_date'] ) ) {
			$meal_plan->set_end_date( absint( wp_unslash( $_POST['end_date'] ) ) );
		}

		// --- Start-Wochentag speichern ---
		if ( isset( $_POST['start_day'] ) ) {
			$meal_plan->set_start_day( intval( $_POST['start_day'] ) );
		}

		// --- End-Wochentag speichern ---
		if ( isset( $_POST['end_day'] ) ) {
			$meal_plan->set_end_day( intval( $_POST['end_day'] ) );
		}
	}

	/*
	public function prevent_overlapping_meal_plans( $post_id, $post, $update ) {

		// Nur im Admin, kein Autosave oder Revision
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( wp_is_post_revision( $post_id ) ) return;
		if ( get_post_type( $post_id ) !== 'mmm_meal_plan' ) return;

		$meal_plan_obj = new Mixmymeals_Meal_Plan( $post_id );

		$start_date = $meal_plan_obj->get_start_date();
		$end_date   = $meal_plan_obj->get_end_date();

		if ( ! $start_date || ! $end_date ) return;

		// Alle anderen Meal Plans holen
		$args = [
			'post_type'      => 'mmm_meal_plan',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'post__not_in'   => [ $post_id ],
		];
		$plans = get_posts( $args );

		foreach ( $plans as $plan ) {
			$other_meal_plan_obj = new Mixmymeals_Meal_Plan( $plan->ID );
			$other_start = $other_meal_plan_obj->get_start_date();
			$other_end   = $other_meal_plan_obj->get_end_date();

			if ( ! $other_start || ! $other_end ) continue;

			// Prüfen auf Überschneidung
			if ( ( $start_date <= $other_end ) && ( $end_date >= $other_start ) ) {

				// Beitrag löschen, um doppelte Pläne zu vermeiden
				//wp_delete_post( $post_id, true );

				// Meal Plan auf Entwurf setzen:
				$other_meal_plan_obj->set_to_draft();

				// Zurück zum Übersichtsbildschirm mit Warnung
				wp_safe_redirect(
					add_query_arg(
						'mmm_overlap',
						urlencode( __( 'This meal plan overlaps with another existing plan and was not saved.', 'mixmymeals' ) ),
						admin_url( 'edit.php?post_type=mmm_meal_plan' )
					)
				);
				exit;
			}
		}
	}
	*/

	/**
	 * Meal Metabox: select parent plan, recipe and servings.
	 */
	public function register_meal_metabox(): void {
		add_meta_box(
			'mmm_meal_meta_box',
			__( 'Meal Details', 'mixmymeals' ),
			array( $this, 'render_meal_metabox' ),
			'mmm_meal',
			'normal'
		);
	}

	public function render_meal_metabox( WP_Post $post ): void {

		wp_nonce_field( 'mmm_meal_meta_box', 'mmm_meal_meta_nonce' );

		$meal_obj = new Mixmymeals_Meal( $post->ID );

		$recipe_id = $meal_obj->get_recipe_id();
		$servings  = $meal_obj->get_servings();
		//$note      = $meal_obj->get_note();
		$parent_id = $meal_obj->get_parent_plan_id();
		$meal_day  = $meal_obj->get_day(); // NEU
		$type      = $meal_obj->get_type();

		$plans   = Mixmymeals_Meal_Plan::get_all_meal_plans();
		$recipes = Mixmymeals_Recipe::get_all_recipes();

		// -------------------------------------
		// Meal-Plan Auswahl
		// -------------------------------------
		echo '<p><label><strong>' . esc_html__(
			'Meal Plan (Parent)',
			'mixmymeals'
		) . '</strong></label><br>';
		echo '<select name="mmm_parent_id" id="mmm_parent_id" style="width:100%;">';
		echo '<option value="">' . esc_html__(
			'— Select Meal Plan —',
			'mixmymeals'
		) . '</option>';

		foreach ( $plans as $plan ) {

			$plan_obj  = new Mixmymeals_Meal_Plan( $plan->ID );
			$start_day = $plan_obj->get_start_day();
			$end_day   = $plan_obj->get_end_day();

			printf(
				'<option value="%d" data-start="%d" data-end="%d" %s>%s</option>',
				absint( $plan->ID ),
				absint( $start_day ),
				absint( $end_day ),
				selected( $parent_id, $plan->ID, false ),
				esc_html( $plan->post_title )
			);
		}

		echo '</select></p>';

		// -------------------------------------
		// Rezept-Auswahl
		// -------------------------------------
		echo '<p><label><strong>' . esc_html__( 'Recipe', 'mixmymeals' )
			. '</strong></label><br>';
		echo '<select name="recipe_id" style="width:100%;">';
		echo '<option value="">' . esc_html__(
			'— Select Recipe —',
			'mixmymeals'
		) . '</option>';

		foreach ( $recipes as $recipe ) {
			printf(
				'<option value="%d" %s>%s</option>',
				absint( $recipe->ID ),
				selected( $recipe_id, $recipe->ID, false ),
				esc_html( $recipe->post_title )
			);
		}

		echo '</select></p>';

		// -------------------------------------
		// Meal Type
		// -------------------------------------
		echo '<p><label><strong>' . esc_html__( 'Type', 'mixmymeals' )
			. '</strong></label><br>';
		echo '<select name="meal_type" style="width:100%;">';

		echo '<option value="">— ' . esc_html__( 'Select Type', 'mixmymeals' )
			. ' —</option>';

		$types = array(
			'breakfast' => esc_html__( 'Breakfast', 'mixmymeals' ),
			'lunch'     => esc_html__( 'Lunch', 'mixmymeals' ),
			'dinner'    => esc_html__( 'Dinner', 'mixmymeals' ),
			'snack'     => esc_html__( 'Snack', 'mixmymeals' ),
			'other'     => esc_html__( 'Other', 'mixmymeals' ),
		);

		foreach ( $types as $value => $label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $value ),
				selected( $type, $value, false ),
				esc_html( $label )
			);
		}

		echo '</select></p>';

		// -------------------------------------
		// Wochentag statt Datum
		// -------------------------------------

		$days = array(
			1 => esc_html__( 'Monday', 'mixmymeals' ),
			2 => esc_html__( 'Tuesday', 'mixmymeals' ),
			3 => esc_html__( 'Wednesday', 'mixmymeals' ),
			4 => esc_html__( 'Thursday', 'mixmymeals' ),
			5 => esc_html__( 'Friday', 'mixmymeals' ),
			6 => esc_html__( 'Saturday', 'mixmymeals' ),
			7 => esc_html__( 'Sunday', 'mixmymeals' ),
		);

		$plan_start = $parent_id ? intval(
			get_post_meta(
				$parent_id,
				'start_day',
				true
			)
		) : 1;
		mmm_write_to_log( $plan_start );
		$plan_end = $parent_id ? intval(
			get_post_meta(
				$parent_id,
				'end_day',
				true
			)
		) : 7;
		mmm_write_to_log( $plan_end );

		echo '<p><label><strong>' . esc_html__(
			'Day (within Meal Plan)',
			'mixmymeals'
		) . '</strong></label><br>';
		echo '<select name="meal_day" id="meal_day" style="width:100%;">';

		echo '<option value="">— ' . esc_html__( 'Select Day', 'mixmymeals' )
			. ' —</option>';

		foreach ( $days as $num => $label ) {

			$in_range = false;

			if ( $plan_start <= $plan_end ) {
				// Normaler Bereich
				$in_range = ( $num >= $plan_start && $num <= $plan_end );
			} else {
				// Überlauf-Bereich (z.B. Freitag bis Dienstag)
				$in_range = ( $num >= $plan_start || $num <= $plan_end );
			}

			if ( ! $in_range ) {
				continue;
			}

			printf(
				'<option value="%d" %s>%s</option>',
				absint( $num ),
				selected( $meal_day, $num, false ),
				esc_html( $label )
			);
		}

		echo '</select></p>';

		// -------------------------------------
		// Portionen
		// -------------------------------------
		echo '<p><label><strong>' . esc_html__( 'Servings', 'mixmymeals' )
			. '</strong></label><br>';
		echo '<input type="number" name="servings" value="'
			. esc_attr( $servings ?: 1 )
			. '" min="1" step="1" style="width:100px;"></p>';

		// -------------------------------------
		// Notiz
		// -------------------------------------
        /*
		echo '<p><label><strong>' . esc_html__(
			'Note (optional)',
			'mixmymeals'
		) . '</strong></label><br>';
		echo '<textarea name="note" rows="2" style="width:100%;">'
			. esc_textarea( $note ) . '</textarea></p>';
        */
	}

	public function save_meal_meta( int $post_id ): void {

		if ( self::$updating_meal_meta ) {
			return;
		}
		self::$updating_meal_meta = true;

		if (
			! isset( $_POST['mmm_meal_meta_nonce'] )
			||
			! wp_verify_nonce(
				sanitize_key( wp_unslash( $_POST['mmm_meal_meta_nonce'] ) ),
				'mmm_meal_meta_box'
			)
		) {
			// mmm_write_to_log( sanitize_text_field( wp_unslash( $_POST['mmm_meal_meta_nonce']  ) ) );
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$meal_obj = new Mixmymeals_Meal( $post_id );

		if ( isset( $_POST['mmm_parent_id'] ) ) {
			$meal_obj->set_parent_plan_id( absint( wp_unslash( $_POST['mmm_parent_id'] ) ) );
		}

		if ( isset( $_POST['recipe_id'] ) ) {
			$meal_obj->set_recipe_id( absint( wp_unslash( $_POST['recipe_id'] ) ) );
		}

		if ( isset( $_POST['servings'] ) ) {
			$meal_obj->set_servings( floatval( wp_unslash( $_POST['servings'] ) ) );
		}

        /*
		if ( isset( $_POST['note'] ) ) {
			$meal_obj->set_note( sanitize_text_field( wp_unslash( $_POST['note'] ) ) );
		}
        */

		// -----------------------
		// NEU: Wochentag speichern
		// -----------------------
		if ( isset( $_POST['meal_day'] ) ) {
			$meal_obj->set_day( absint( wp_unslash( $_POST['meal_day'] ) ) );
		}

		if ( isset( $_POST['meal_type'] ) ) {
			$meal_obj->set_type( sanitize_text_field( wp_unslash( $_POST['meal_type'] ) ) );
		}


		/*
		if ( isset( $_POST['meal_date'] ) ) {
			$meal_obj->set_date( $_POST['meal_date'] );
		}
		*/

		self::$updating_meal_meta = false;
	}


	/*
	======================================================
	*  INGREDIENT AMOUNT META BOX
	* ====================================================== */

    /*
	public function add_ingr_amount_meta_box() {
		add_meta_box(
			'mmm_ingr_unit_meta_box',
			__( 'Weight per Unit', 'mixmymeals' ),
			array( $this, 'render_ingr_amount_meta_box_content' ),
			'mmm_ingr_unit',
			'side'
		);
	}
    */

    /*
	public function render_ingr_amount_meta_box_content( $post ) {
		wp_nonce_field( 'mmm_ingr_unit_meta_box', 'mmm_ingr_unit_meta_nonce' );

		// Handler-Objekt erstellen
		$amount_obj = new Mixmymeals_Ingredient_Amount( $post->ID );

		// Gewicht über Getter holen
		$weight = $amount_obj->get_weight();

		echo '<p><label for="mmm_ingr_unit_weight"><strong>'
			. esc_html__( 'Weight per Unit (g)', 'mixmymeals' )
			. '</strong></label></p>';
		echo '<input type="number" step="0.1" min="0" name="mmm_ingr_unit_weight" id="mmm_ingr_unit_weight" value="'
			. esc_attr( $weight ) . '" style="width:100%;" />';
		echo '<p class="description">'
			. esc_html__(
				'e.g. 1 TL = 5 g, 1 EL = 15 g, 1 Pcs. = 120 g.',
				'mixmymeals'
			) . '</p>';
	}
    */

    /*
	public function save_ingr_amount_meta( $post_id ) {

		// Endlosschleife verhindern
		if ( self::$updating_ingr_amount_meta ) {
			return;
		}
		self::$updating_ingr_amount_meta = true;

		// Sicherheits-Checks
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			self::$updating_ingr_amount_meta = false;

			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			self::$updating_ingr_amount_meta = false;

			return;
		}

		if (
			! isset( $_POST['mmm_ingr_unit_meta_nonce'] )
			||
			! wp_verify_nonce(
				sanitize_key( wp_unslash( $_POST['mmm_ingr_unit_meta_nonce'] ) ),
				'mmm_ingr_unit_meta_box'
			)
		) {
			self::$updating_ingr_amount_meta = false;

			return;
		}

		// Objekt laden
		$amount_obj = new Mixmymeals_Ingredient_Amount( $post_id );

		// Gewicht speichern
		if ( isset( $_POST['mmm_ingr_unit_weight'] ) ) {
			$amount_obj->set_weight( floatval( $_POST['mmm_ingr_unit_weight'] ) );
		}

		// Parent Ingredient speichern (zweite Meta Box)
		if (
			isset( $_POST['mmm_ingr_unit_parent_meta_nonce'] )
			&& wp_verify_nonce(
				sanitize_key( wp_unslash( $_POST['mmm_ingr_unit_parent_meta_nonce'] ) ),
				'mmm_ingr_unit_parent_meta_box'
			)
			&& isset( $_POST['mmm_ingr_parent_id'] )
		) {
			$amount_obj->set_parent_ingredient_id( intval( $_POST['mmm_ingr_parent_id'] ) );
		}

		// Flag wieder zurücksetzen
		self::$updating_ingr_amount_meta = false;
	}
    */

    /*
	public function add_ingr_amount_parent_meta_box() {
		add_meta_box(
			'mmm_ingr_unit_parent_meta_box',
			__( 'Linked Ingredient', 'mixmymeals' ),
			array( $this, 'render_ingr_amount_parent_meta_box_content' ),
			'mmm_ingr_unit',
			'side'
		);
	}
    */

    /*
	public function render_ingr_amount_parent_meta_box_content( $post ) {
		wp_nonce_field(
			'mmm_ingr_unit_parent_meta_box',
			'mmm_ingr_unit_parent_meta_nonce'
		);

		$amount_obj = new Mixmymeals_Ingredient_Amount( $post->ID );
		$parent_id  = $amount_obj->get_parent_ingredient_id();

		// Liste aller Zutaten laden
		$ingredients = Mixmymeals_Ingredient::get_all_ingredients();

		echo '<p><label for="mmm_ingr_parent_id"><strong>'
			. esc_html__( 'Linked Ingredient', 'mixmymeals' )
			. ':</strong></label></p>';
		echo '<select name="mmm_ingr_parent_id" id="mmm_ingr_parent_id" style="width:100%;">';
		echo '<option value="">- ' . esc_html__( 'Select', 'mixmymeals' )
			. ' -</option>';

		foreach ( $ingredients as $ing ) {
			printf(
				'<option value="%d" %s>%s</option>',
				absint( $ing->ID ),
				selected( $parent_id, $ing->ID, false ),
				esc_html( $ing->post_title )
			);
		}

		echo '</select>';

		if ( $parent_id ) {
			$parent_link = get_edit_post_link( $parent_id );
			if ( $parent_link ) {
				echo '<p><a href="' . esc_url( $parent_link )
					. '" target="_blank">' .
					esc_html__( 'Open Ingredient', 'mixmymeals' )
					. ' ↗</a></p>';
			}
		}
	}
    */

	public function add_ingredient_price_meta_box(): void {
		add_meta_box(
			'mmm_ingredient_price_meta_box',
			__( 'Price', 'mixmymeals' ),
			array( $this, 'render_ingredient_price_meta_box_content' ),
			'mmm_ingredient',
			'side'
		);
	}

	public function render_ingredient_price_meta_box_content( WP_Post $post ): void {
		wp_nonce_field( 'mmm_ingredient_price_meta_box', 'mmm_ingredient_price_meta_nonce' );

		$ingredient = new Mixmymeals_Ingredient( $post->ID );
		$price      = $ingredient->get_price_per_100g();
		$currency   = get_option( 'mixmymeals_currency', '€' );
		?>
		<p>
			<label for="mmm_price_per_100g">
				<?php
				// translators: %s = currency symbol
				printf( esc_html__( 'Price per 100 g (%s)', 'mixmymeals' ), esc_html( $currency ) );
				?>
			</label>
		</p>
		<p>
			<input
				type="number"
				id="mmm_price_per_100g"
				name="mmm_price_per_100g"
				value="<?php echo esc_attr( $price > 0 ? $price : '' ); ?>"
				step="0.001"
				min="0"
				placeholder="0.00"
				style="width:100%;"
			/>
		</p>
		<?php
	}

	public function save_ingredient_price_meta( int $post_ID ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_ID ) ) {
			return;
		}
		if (
			! isset( $_POST['mmm_ingredient_price_meta_nonce'] )
			|| ! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['mmm_ingredient_price_meta_nonce'] ) ),
				'mmm_ingredient_price_meta_box'
			)
		) {
			return;
		}

		$ingredient = new Mixmymeals_Ingredient( $post_ID );
		$price      = isset( $_POST['mmm_price_per_100g'] )
			? floatval( wp_unslash( $_POST['mmm_price_per_100g'] ) )
			: 0.0;
		$ingredient->set_price_per_100g( $price );
	}

	/**
	 * Registers the "Diet Type" meta box for ingredients.
	 */
	public function add_ingredient_diet_meta_box(): void {
		add_meta_box(
			'mmm_ingredient_diet_meta_box',
			__( 'Diet Type', 'mixmymeals' ),
			array( $this, 'render_ingredient_diet_meta_box_content' ),
			'mmm_ingredient',
			'side'
		);
	}

	/**
	 * Renders the "Diet Type" meta box for ingredients.
	 *
	 * @param WP_Post $post The current post object.
	 */
	public function render_ingredient_diet_meta_box_content( WP_Post $post ): void {
		wp_nonce_field( 'mmm_ingredient_diet_meta_box', 'mmm_ingredient_diet_meta_nonce' );

		$ingredient = new Mixmymeals_Ingredient( $post->ID );
		$diet_type  = $ingredient->get_diet_type();

		$options = [
			''             => __( 'Not specified', 'mixmymeals' ),
			'vegetarian'   => __( 'Vegetarian', 'mixmymeals' ),
			'vegan'        => __( 'Vegan', 'mixmymeals' ),
		];
		?>
		<p>
			<label for="mmm_diet_type"><?php esc_html_e( 'Diet Type', 'mixmymeals' ); ?></label>
		</p>
		<p>
			<select id="mmm_diet_type" name="mmm_diet_type" style="width:90%;">
				<?php foreach ( $options as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>"
						<?php selected( $diet_type, $value ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	/**
	 * Saves the diet type meta value for an ingredient.
	 *
	 * @param int $post_ID The post ID.
	 */
	public function save_ingredient_diet_meta( int $post_ID ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_ID ) ) {
			return;
		}
		if (
			! isset( $_POST['mmm_ingredient_diet_meta_nonce'] )
			|| ! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['mmm_ingredient_diet_meta_nonce'] ) ),
				'mmm_ingredient_diet_meta_box'
			)
		) {
			return;
		}

		$allowed   = [ '', 'vegetarian', 'vegan' ];
		$diet_type = sanitize_text_field( wp_unslash( $_POST['mmm_diet_type'] ?? '' ) );

		if ( ! in_array( $diet_type, $allowed, true ) ) {
			$diet_type = '';
		}

		if ( $diet_type === '' ) {
			delete_post_meta( $post_ID, '_mmm_diet_type' );
		} else {
			update_post_meta( $post_ID, '_mmm_diet_type', $diet_type );
		}
	}

	public function add_ingredient_amounts_meta_box(): void {
		add_meta_box(
			'mmm_ingredient_amounts_meta_box',
			__( 'Units', 'mixmymeals' ),
			array( $this, 'render_ingredient_amounts_meta_box_content' ),
			'mmm_ingredient',
			'normal'
		);
	}

	public function render_ingredient_amounts_meta_box_content( WP_Post $post ): void {

		wp_nonce_field(
			'mmm_ingredient_amounts_meta_box',
			'mmm_ingredient_amounts_meta_nonce'
		);

		$ingredient = new Mixmymeals_Ingredient( $post->ID );
		$units      = $ingredient->get_units();

		echo '<div id="mmm-ingredient-amounts-list">';

		echo '<table class="widefat striped">';
		echo '<thead>';
		echo '<tr>';
		echo '<th>' . esc_html__( 'Title', 'mixmymeals' ) . '</th>';
		echo '<th>' . esc_html__( 'Used', 'mixmymeals' ) . '</th>';
		echo '<th>' . esc_html__( 'Weight', 'mixmymeals' ) . '</th>';
		echo '<th>' . esc_html__( 'Indivisible', 'mixmymeals' ) . '</th>';
		echo '<th>' . esc_html__( 'Delete', 'mixmymeals' ) . '</th>';
		echo '</tr>';
		echo '</thead>';
		echo '<tbody>';

		// Bestehende Mengen
		if ( ! empty( $units ) ) {
			foreach ( $units as $unit ) {

				$amount  = new Mixmymeals_Ingredient_Amount( $unit->ID );

				if ( ! $amount->is_valid() ) {
					continue; // Skip if unit is invalid.
				}

				$is_indivisible = $amount->is_indivisible();
				$usage_count = $amount->get_amount_usage_count();

				echo '<tr data-id="' . esc_attr( $unit->ID ) . '">';

				// Titel
				//echo '<td>' . esc_html( $amount->get_title() ) . '</td>';
				echo '<td>';
				echo '<input type="text" 
                    name="mmm_ingredient_amounts[' . esc_attr( $unit->ID ) . '][title]" 
                    value="' . esc_attr( $amount->get_title() ) . '" 
                    class="regular-text">';
				echo '</td>';

                // Nutzung
				echo '<td>' . esc_html( $usage_count ) . '</td>';

				// Gewicht als Input-Feld
				echo '<td>';
				echo '<input type="number" step="0.01" min="0" name="mmm_ingredient_amounts['
					. esc_attr( $unit->ID ) . '][weight]" value="'
					. esc_attr( $amount->get_weight() )
					. '" class="small-text">';
				echo '</td>';

                // Chekbox für Teilbarkeit
				echo '<td>';
				echo '<label>';
				echo '<input type="checkbox" name="mmm_ingredient_amounts['
				     . esc_attr( $unit->ID ) . '][indivisible]" value="1" '
				     . checked( $is_indivisible, true, false ) . '> ';
				echo esc_html__( 'Yes', 'mixmymeals' );
				echo '</label>';
				echo '</td>';

				// Löschen-Checkbox
				echo '<td>';
				echo '<label>';
				echo '<input type="checkbox" name="mmm_ingredient_amounts['
					. esc_attr( $unit->ID ) . '][delete]" value="1"> ';
				echo esc_html__( 'Delete', 'mixmymeals' );
				echo '</label>';
				echo '</td>';

				echo '</tr>';
			}
		}

		// Neue Menge Zeile
		echo '<tr class="new-ingredient-amount">';
		echo '<td>';
		echo '<input type="text" name="mmm_ingredient_amounts[new][title]" placeholder="'
			. esc_attr__( 'New Amount', 'mixmymeals' )
			. '" class="regular-text">';
		echo '</td>';
        echo '<td>';
		echo '<em>' . esc_html__( '–', 'mixmymeals' ) . '</em>';
        echo '</td>';
		echo '<td>';
		echo '<input type="number" step="0.01" min="0" name="mmm_ingredient_amounts[new][weight]" value="" class="small-text">';
		echo '</td>';
		echo '<td>';
		echo '<input type="checkbox" name="mmm_ingredient_amounts[new][indivisible]" value="1"> ';
		echo esc_html__( 'Yes', 'mixmymeals' );
		echo '</td>';
		echo '<td>';
		echo '<em>' . esc_html__( '–', 'mixmymeals' ) . '</em>';
		echo '</td>';
		echo '</tr>';

		echo '</tbody>';
		echo '</table>';
		echo '</div>';
	}


	public function ajax_mixmymeals_get_ingredient_units_handler(): void {

		check_ajax_referer( 'mixmymeals_admin_nonce', 'nonce' );

		$ingredient_id = intval( $_POST['ingredient_id'] ?? 0 );
		if ( ! $ingredient_id ) {
			wp_send_json_error( array( 'message' => 'Invalid ingredient ID' ) );
		}

		// Hole alle Mengen-Einheiten zu dieser Zutat
		$amounts = get_posts(
			array(
				'post_type'   => 'mmm_ingr_unit',
				'post_status' => 'any',
				'post_parent' => $ingredient_id,
				'numberposts' => - 1,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);

		// Baue Dropdown
		ob_start();
		echo '<select class="small-text ingredient-unit-select" name="">';
		echo '<option value="">' . esc_html__(
			'Gramm (Standard)',
			'mixmymeals'
		) . '</option>';

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

		wp_send_json_success( array( 'html' => $html ) );
	}

	public function add_equipment_meta_box(): void {
		add_meta_box(
			'mmm_equipment_box',
			__('Required Equipment', 'mixmymeals'),
			[$this, 'render_equipment_meta_box_content'], // oder 'mmm_render_equipment_metabox'
			'mmm_recipe', // dein CPT für Rezepte
			'side',
			'default'
		);
	}

	public function save_equipment_metabox( int $post_id ): void {
		if (!isset($_POST['mmm_equipment_nonce']) ||
		    !wp_verify_nonce($_POST['mmm_equipment_nonce'], 'mmm_equipment_save')) {
			return;
		}
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}
		if (!current_user_can('edit_post', $post_id)) {
			return;
		}

		$equipment = isset($_POST['mmm_equipment']) ? array_map('intval', $_POST['mmm_equipment']) : [];
		//update_post_meta($post_id, '_mmm_equipment', $equipment);
		$recipe = new Mixmymeals_Recipe( $post_id );
		$recipe->set_equipment( $_POST['mmm_equipment'] ?? [] );
	}

	public function render_equipment_meta_box_content( WP_Post $post ): void {

		// Sicherheits-Nonce
		wp_nonce_field( 'mmm_equipment_save', 'mmm_equipment_nonce' );

		// Bisher gespeicherte IDs abrufen
		//$saved_equipment = get_post_meta( $post->ID, '_mmm_equipment', true );
		$recipe = new Mixmymeals_Recipe( $post->ID );
		//$saved_equipment = get_post_meta( $post->ID, '_mmm_equipment', true );
		$saved_equipment = $recipe->get_equipment();
		if ( ! is_array( $saved_equipment ) ) {
			$saved_equipment = [];
		}

		// Alle Equipment-Posts laden
		$equipment = get_posts( [
			'post_type'      => 'mmm_equipment',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );

		echo '<p>' . esc_html__( 'Select equipment needed for this recipe:', 'mixmymeals' ) . '</p>';

		// Wenn keine Equipment-Posts existieren → Hinweis mit Link
		if ( empty( $equipment ) ) {
			$link = admin_url( 'post-new.php?post_type=mmm_equipment' );
			echo '<p>'
			     . esc_html__( 'No equipment available yet.', 'mixmymeals' )
			     . ' <a href="' . esc_url( $link ) . '">'
			     . esc_html__( 'Create new equipment', 'mixmymeals' )
			     . '</a></p>';
			return;
		}

		echo '<ul style="max-height:200px; overflow:auto;">';

		foreach ( $equipment as $piece_of_equipment ) {
			$checked = in_array( $piece_of_equipment->ID, $saved_equipment ) ? 'checked' : '';
			printf(
				'<li><label><input type="checkbox" name="mmm_equipment[]" value="%d" %s> %s</label></li>',
				esc_attr( $piece_of_equipment->ID ),
				$checked,
				esc_html( get_the_title( $piece_of_equipment ) )
			);
		}

		echo '</ul>';
	}

	/**
	 * Nonce action/field for the author meta box, shared by both post types.
	 *
	 * @var string
	 */
	const AUTHOR_NONCE_ACTION = 'mmm_author_meta_box';
	const AUTHOR_NONCE_FIELD  = 'mmm_author_nonce';

	/**
	 * The freely editable author credit that recipes and meal plans share, so
	 * both post types behave identically instead of drifting apart.
	 *
	 * Two sources, in this order:
	 *  1. the post's own author fields (name, text, link, picture) — a guest
	 *     author, an external site, a magazine credit;
	 *  2. when no name is set there, the WordPress user who created the post,
	 *     with avatar and profile bio.
	 *
	 * Not to be confused with the image credit below, which credits the
	 * featured image rather than the post.
	 *
	 * @var string
	 */
	const AUTHOR_NAME      = '_mmm_author_name';
	const AUTHOR_TEXT      = '_mmm_author_text';
	const AUTHOR_URL       = '_mmm_author_url';
	const AUTHOR_LINK_TEXT = '_mmm_author_link_text';
	const AUTHOR_NOFOLLOW  = '_mmm_author_nofollow';
	const AUTHOR_SOCIAL    = '_mmm_author_social';
	const AUTHOR_IMAGE     = '_mmm_author_image';

	/**
	 * Rendered size of the author's profile picture in pixels. Matches the size
	 * the box asks get_avatar() for, so both sources hand the theme an image of
	 * the same dimensions.
	 *
	 * @var int
	 */
	const AUTHOR_AVATAR_SIZE = 96;

	const IMAGE_CREDIT_NONCE_ACTION = 'mmm_image_credit_meta_box';
	const IMAGE_CREDIT_NONCE_FIELD  = 'mmm_image_credit_nonce';

	/**
	 * Credit for the featured image of recipes and meal plans. Kept structured
	 * rather than free text on purpose — licences like CC BY ask for creator,
	 * source and licence separately, and only a structured field can still be
	 * reformatted later without re-editing every post.
	 *
	 * Field key => meta key. The order is also the order of the admin fields.
	 *
	 * @var array<string,string>
	 */
	const IMAGE_CREDIT_FIELDS = array(
		'creator' => '_mmm_image_credit_creator',
		'source'  => '_mmm_image_credit_source',
		'license' => '_mmm_image_credit_license',
		'url'     => '_mmm_image_credit_url',
	);

	/**
	 * Meal Plan Metabox: freely editable author box (external author).
	 */
	public function register_meal_plan_author_metabox(): void {
		add_meta_box(
			'mmm_meal_plan_author_meta_box',
			__( 'Plan Author', 'mixmymeals' ),
			array( $this, 'render_author_metabox' ),
			'mmm_meal_plan',
			'normal',
			'low'
		);
	}

	/**
	 * Recipe Metabox: freely editable author box (external author).
	 */
	public function register_recipe_author_metabox(): void {
		add_meta_box(
			'mmm_recipe_author_meta_box',
			__( 'Recipe Author', 'mixmymeals' ),
			array( $this, 'render_author_metabox' ),
			'mmm_recipe',
			'normal',
			'low'
		);
	}

	/**
	 * Renders the author-box fields for recipes and meal plans alike. Leaving
	 * the name empty falls back to the WordPress user who created the post.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public function render_author_metabox( WP_Post $post ): void {

		wp_nonce_field( self::AUTHOR_NONCE_ACTION, self::AUTHOR_NONCE_FIELD );

		$wp_author = trim(
			(string) get_the_author_meta( 'display_name', (int) $post->post_author )
		);

		echo '<p class="description" style="margin:0 0 12px;">'
			. esc_html(
				sprintf(
					/* translators: %s: display name of the WordPress user who created the post. */
					__( 'Leave the name empty to credit the WordPress author (%s) with their profile picture and bio.', 'mixmymeals' ),
					'' !== $wp_author ? $wp_author : '—'
				)
			)
			. '</p>';

		echo '<table class="form-table mixmymeals-meta-table"><tbody>';

		echo '<tr><th scope="row"><label for="mmm_author_name">'
			. esc_html__( 'Author Name', 'mixmymeals' ) . '</label></th>'
			. '<td><input type="text" class="regular-text" id="mmm_author_name" name="mmm_author_name" value="'
			. esc_attr( self::get_author_name( $post->ID ) ) . '" /></td></tr>';

		// Plain textarea, upgraded to a slim TinyMCE by admin.js. Deliberately
		// NOT wp_editor(): in the block editor classic meta boxes are moved into
		// the Gutenberg meta-box area after load, and a TinyMCE initialised
		// before that move ends up focusable but not typable. admin.js waits for
		// the meta boxes to settle, exactly like the recipe step editors.
		echo '<tr><th scope="row"><label for="mmm_author_text">'
			. esc_html__( 'Author Text', 'mixmymeals' ) . '</label></th>'
			. '<td><textarea class="large-text mmm-author-editor" id="mmm_author_text" name="mmm_author_text" rows="5">'
			. esc_textarea( self::get_author_text( $post->ID ) )
			. '</textarea></td></tr>';

		$image_id  = self::get_author_image_id( $post->ID );
		$image_url = self::get_author_image_url( $post->ID );

		echo '<tr><th scope="row"><label for="mmm_author_image_button">'
			. esc_html__( 'Profile Picture', 'mixmymeals' ) . '</label></th>'
			. '<td><div class="mmm-author-image-field">'
			. '<input type="hidden" class="mmm-author-image-id" id="mmm_author_image" name="mmm_author_image" value="'
			. esc_attr( (string) $image_id ) . '" />'
			. '<img src="' . esc_url( $image_url ) . '" class="mmm-author-image-preview" alt=""'
			. ( '' === $image_url ? ' style="display:none;"' : '' ) . ' />'
			. '<p>'
			. '<button type="button" class="button mmm-author-image-select" id="mmm_author_image_button">'
			. esc_html__( 'Select Image', 'mixmymeals' ) . '</button> '
			. '<button type="button" class="button-link mmm-author-image-remove"'
			. ( '' === $image_url ? ' style="display:none;"' : '' ) . '>'
			. esc_html__( 'Remove', 'mixmymeals' ) . '</button>'
			. '</p><p class="description">'
			. esc_html__( 'Shown like a WordPress author’s profile picture. Only used for an external author; without a name the WordPress author’s own avatar is shown.', 'mixmymeals' )
			. '</p></div></td></tr>';

		echo '<tr><th scope="row"><label for="mmm_author_url">'
			. esc_html__( 'Link URL', 'mixmymeals' ) . '</label></th>'
			. '<td><input type="url" class="regular-text" id="mmm_author_url" name="mmm_author_url" placeholder="https://" value="'
			. esc_attr( self::get_author_url( $post->ID ) ) . '" /></td></tr>';

		echo '<tr><th scope="row"><label for="mmm_author_link_text">'
			. esc_html__( 'Link Text', 'mixmymeals' ) . '</label></th>'
			. '<td><input type="text" class="regular-text" id="mmm_author_link_text" name="mmm_author_link_text" value="'
			. esc_attr( (string) get_post_meta( $post->ID, self::AUTHOR_LINK_TEXT, true ) ) . '" />'
			. '<p class="description">'
			. esc_html__( 'Optional. Defaults to the domain of the link.', 'mixmymeals' )
			. '</p></td></tr>';

		echo '<tr><th scope="row"><label for="mmm_author_social">'
			. esc_html__( 'Social Links', 'mixmymeals' ) . '</label></th>'
			. '<td><textarea class="large-text code" id="mmm_author_social" name="mmm_author_social" rows="4" placeholder="https://instagram.com/…">'
			. esc_textarea( implode( "\n", self::get_author_social( $post->ID ) ) )
			. '</textarea><p class="description">'
			. esc_html__( 'One URL or e-mail address per line. The icon is derived from the address; unknown sites get a globe icon. Only used for an external author — the WordPress author’s links come from their profile.', 'mixmymeals' )
			. '</p></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Link Type', 'mixmymeals' ) . '</th>'
			. '<td><label><input type="checkbox" id="mmm_author_nofollow" name="mmm_author_nofollow" value="1" '
			. checked( self::get_author_nofollow( $post->ID ), true, false ) . ' /> '
			. esc_html__( 'Mark link as nofollow', 'mixmymeals' )
			. '</label><p class="description">'
			. esc_html__( 'Off: the link passes ranking signals (follow). On: rel="nofollow" is added.', 'mixmymeals' )
			. '</p></td></tr>';

		echo '</tbody></table>';
	}

	/**
	 * Saves the author-box fields for recipes and meal plans alike, after
	 * verifying nonce, autosave state and caps.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_author_meta( int $post_id ): void {

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified right here.
		if (
			! isset( $_POST[ self::AUTHOR_NONCE_FIELD ] )
			|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::AUTHOR_NONCE_FIELD ] ) ), self::AUTHOR_NONCE_ACTION )
		) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		self::save_author_data(
			$post_id,
			array(
				'name'      => wp_unslash( $_POST['mmm_author_name'] ?? '' ),
				'text'      => wp_unslash( $_POST['mmm_author_text'] ?? '' ),
				'url'       => wp_unslash( $_POST['mmm_author_url'] ?? '' ),
				'link_text' => wp_unslash( $_POST['mmm_author_link_text'] ?? '' ),
				'social'    => wp_unslash( $_POST['mmm_author_social'] ?? '' ),
				'image'     => absint( $_POST['mmm_author_image'] ?? 0 ),
				'nofollow'  => ! empty( $_POST['mmm_author_nofollow'] ),
			)
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Name of the external author, or '' when none is set.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function get_author_name( int $post_id ): string {
		return trim( (string) self::get_author_data( $post_id )['name'] );
	}

	/**
	 * Free-form description shown in the box (limited HTML allowed).
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function get_author_text( int $post_id ): string {
		return trim( (string) self::get_author_data( $post_id )['text'] );
	}

	/**
	 * Link target of the author box, or '' when none is set.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function get_author_url( int $post_id ): string {
		return trim( (string) self::get_author_data( $post_id )['url'] );
	}

	/**
	 * Label for the author link. Falls back to the link's host (without "www.")
	 * so the field can stay empty in the common case.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function get_author_link_text( int $post_id ): string {

		$text = trim( (string) self::get_author_data( $post_id )['link_text'] );

		if ( '' !== $text ) {
			return $text;
		}

		$host = (string) wp_parse_url( self::get_author_url( $post_id ), PHP_URL_HOST );

		return '' !== $host ? (string) preg_replace( '/^www\./', '', $host ) : '';
	}

	/**
	 * Whether the author link should carry rel="nofollow".
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function get_author_nofollow( int $post_id ): bool {
		return (bool) self::get_author_data( $post_id )['nofollow'];
	}

	/**
	 * Social/contact entries of the external author: URLs and e-mail addresses,
	 * one per line in the admin. The platform (and therefore the icon) is
	 * derived from the address when rendering, so no per-network fields are
	 * needed and unknown sites still work.
	 *
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	public static function get_author_social( int $post_id ): array {

		$stored = self::get_author_data( $post_id )['social'];

		return is_array( $stored ) ? array_values( array_filter( $stored ) ) : array();
	}

	/**
	 * Attachment ID of the external author's profile picture, or 0 when none is
	 * set. Only meaningful together with a name — without one the box shows the
	 * WordPress author and their own avatar.
	 *
	 * @param int $post_id Post ID.
	 * @return int
	 */
	public static function get_author_image_id( int $post_id ): int {
		return (int) self::get_author_data( $post_id )['image'];
	}

	/**
	 * URL of the profile picture at avatar size, or '' when none is set or the
	 * attachment has meanwhile been deleted.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function get_author_image_url( int $post_id ): string {

		$image_id = self::get_author_image_id( $post_id );

		if ( ! $image_id ) {
			return '';
		}

		return (string) wp_get_attachment_image_url( $image_id, 'thumbnail' );
	}

	/**
	 * Whether the post credits an external author. False means the box falls
	 * back to the WordPress user — the state the theme needs to know before it
	 * appends profile-based extras such as social links.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function has_external_author( int $post_id ): bool {
		return '' !== self::get_author_name( $post_id );
	}

	/**
	 * All stored author fields as they sit in the database. Unlike
	 * get_author_link_text() this returns the *raw* link text, so callers that
	 * read-modify-write the box (the REST API merges partial updates) never
	 * persist the derived host fallback as if it had been typed in.
	 *
	 * The keys match what save_author_data() expects, so read + override + save
	 * is a lossless round trip.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public static function get_author_data( int $post_id ): array {

		/*
		 * The author lives on a term since the taxonomy was introduced, so that
		 * one person exists once and gets an archive of their contributions.
		 * This method is the only place that decides where the fields come from
		 * — every get_author_*() reads through here, so they cannot disagree.
		 *
		 * The legacy per-post meta stays readable as a fallback: an install that
		 * has not run the migration yet, or a post restored from an older
		 * backup, must show its credit rather than silently drop it.
		 */
		$term = Mixmymeals_Author::get_post_data( $post_id );

		if ( ! empty( $term ) ) {
			return $term;
		}

		$social = get_post_meta( $post_id, self::AUTHOR_SOCIAL, true );

		return array(
			'name'      => trim( (string) get_post_meta( $post_id, self::AUTHOR_NAME, true ) ),
			'text'      => trim( (string) get_post_meta( $post_id, self::AUTHOR_TEXT, true ) ),
			'url'       => trim( (string) get_post_meta( $post_id, self::AUTHOR_URL, true ) ),
			'link_text' => trim( (string) get_post_meta( $post_id, self::AUTHOR_LINK_TEXT, true ) ),
			'nofollow'  => '1' === (string) get_post_meta( $post_id, self::AUTHOR_NOFOLLOW, true ),
			'social'    => is_array( $social ) ? array_values( array_filter( $social ) ) : array(),
			'image'     => (int) get_post_meta( $post_id, self::AUTHOR_IMAGE, true ),
		);
	}

	/**
	 * Stores the author fields. An empty name removes the credit so the post
	 * cleanly falls back to its WordPress author.
	 *
	 * The fields live on the author term, not on the post: the name identifies
	 * the person, and everything else describes them rather than this one
	 * contribution. Editing the bio here therefore updates it everywhere that
	 * author appears — which is the point of the taxonomy, and the reason a
	 * typo in the name creates a second person rather than renaming the first.
	 *
	 * Legacy post meta is cleared for this post on the way, so a half-migrated
	 * install cannot end up with the term saying one thing and the old fallback
	 * another.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $data    name, text, url, link_text, nofollow, social, image.
	 * @return void
	 */
	public static function save_author_data( int $post_id, array $data ): void {

		$name = isset( $data['name'] ) ? trim( sanitize_text_field( $data['name'] ) ) : '';

		self::clear_legacy_author_meta( $post_id );

		if ( '' === $name ) {
			Mixmymeals_Author::assign( $post_id, 0 );
			return;
		}

		$term_id = Mixmymeals_Author::find_or_create( $name );

		if ( ! $term_id ) {
			return;
		}

		Mixmymeals_Author::save_term_data( $term_id, $data );
		Mixmymeals_Author::assign( $post_id, $term_id );
	}

	/**
	 * Drops the pre-taxonomy author meta of one post.
	 *
	 * Only ever called when that post's credit is being rewritten, so the
	 * fallback in get_author_data() keeps covering posts nobody has touched yet.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private static function clear_legacy_author_meta( int $post_id ): void {

		foreach ( array(
			self::AUTHOR_NAME,
			self::AUTHOR_TEXT,
			self::AUTHOR_URL,
			self::AUTHOR_LINK_TEXT,
			self::AUTHOR_NOFOLLOW,
			self::AUTHOR_SOCIAL,
			self::AUTHOR_IMAGE,
		) as $key ) {
			delete_post_meta( $post_id, $key );
		}
	}

	/**
	 * Parses the input into a clean list of URLs/e-mail addresses. Accepts the
	 * admin textarea (one entry per line) as well as an array, which is what
	 * REST clients send.
	 *
	 * @param string|array $raw One entry per line, or a list of entries.
	 * @return string[]
	 */
	public static function parse_author_social( $raw ): array {

		$lines = is_array( $raw )
			? array_map( 'strval', $raw )
			: preg_split( '/\r\n|\r|\n/', (string) $raw );

		$out = array();

		foreach ( $lines as $line ) {

			$line = trim( $line );

			if ( '' === $line ) {
				continue;
			}

			if ( is_email( $line ) ) {
				$out[] = sanitize_email( $line );
				continue;
			}

			$url = esc_url_raw( $line );

			if ( '' !== $url ) {
				$out[] = $url;
			}
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * Metabox for the featured image's credit, identical on recipes and plans.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public function register_image_credit_metabox( WP_Post $post ): void {
		add_meta_box(
			'mmm_image_credit_meta_box',
			__( 'Image Source', 'mixmymeals' ),
			array( $this, 'render_image_credit_metabox' ),
			$post->post_type,
			'normal',
			'low'
		);
	}

	/**
	 * Renders the image-credit fields.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public function render_image_credit_metabox( WP_Post $post ): void {

		wp_nonce_field( self::IMAGE_CREDIT_NONCE_ACTION, self::IMAGE_CREDIT_NONCE_FIELD );

		$data = self::get_image_credit( $post->ID );
		$text = self::get_image_credit_text( $post->ID );

		echo '<p class="description" style="margin:0 0 12px;">'
			. esc_html__( 'Credit for the featured image. Every field is optional; the line is only shown when at least one of creator, source or licence is filled in.', 'mixmymeals' )
			. '</p>';

		echo '<table class="form-table mixmymeals-meta-table"><tbody>';

		echo '<tr><th scope="row"><label for="mmm_image_credit_creator">'
			. esc_html__( 'Creator', 'mixmymeals' ) . '</label></th>'
			. '<td><input type="text" class="regular-text" id="mmm_image_credit_creator" name="mmm_image_credit_creator" value="'
			. esc_attr( $data['creator'] ) . '" />'
			. '<p class="description">' . esc_html__( 'Who took or made the picture.', 'mixmymeals' ) . '</p></td></tr>';

		echo '<tr><th scope="row"><label for="mmm_image_credit_source">'
			. esc_html__( 'Source', 'mixmymeals' ) . '</label></th>'
			. '<td><input type="text" class="regular-text" id="mmm_image_credit_source" name="mmm_image_credit_source" value="'
			. esc_attr( $data['source'] ) . '" />'
			. '<p class="description">' . esc_html__( 'Where it came from, e.g. the stock library or publication.', 'mixmymeals' ) . '</p></td></tr>';

		echo '<tr><th scope="row"><label for="mmm_image_credit_license">'
			. esc_html__( 'Licence', 'mixmymeals' ) . '</label></th>'
			. '<td><input type="text" class="regular-text" id="mmm_image_credit_license" name="mmm_image_credit_license" value="'
			. esc_attr( $data['license'] ) . '" placeholder="CC BY 4.0" /></td></tr>';

		echo '<tr><th scope="row"><label for="mmm_image_credit_url">'
			. esc_html__( 'Link URL', 'mixmymeals' ) . '</label></th>'
			. '<td><input type="url" class="regular-text" id="mmm_image_credit_url" name="mmm_image_credit_url" placeholder="https://" value="'
			. esc_attr( $data['url'] ) . '" />'
			. '<p class="description">' . esc_html__( 'Optional. Links creator and source; the licence stays unlinked.', 'mixmymeals' ) . '</p></td></tr>';

		echo '</tbody></table>';

		echo '<p class="description" style="margin:12px 0 0;">'
			. esc_html__( 'Preview:', 'mixmymeals' ) . ' <strong>'
			. esc_html( '' !== $text ? $text : '—' )
			. '</strong></p>';
	}

	/**
	 * Saves the image-credit fields after verifying nonce, autosave and caps.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_image_credit_meta( int $post_id ): void {

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified right here.
		if (
			! isset( $_POST[ self::IMAGE_CREDIT_NONCE_FIELD ] )
			|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::IMAGE_CREDIT_NONCE_FIELD ] ) ), self::IMAGE_CREDIT_NONCE_ACTION )
		) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$data = array();

		foreach ( array_keys( self::IMAGE_CREDIT_FIELDS ) as $key ) {
			$data[ $key ] = wp_unslash( $_POST[ 'mmm_image_credit_' . $key ] ?? '' );
		}

		self::save_image_credit( $post_id, $data );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * All stored image-credit fields, keyed as save_image_credit() expects
	 * them, so read + override + save is a lossless round trip.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string,string>
	 */
	public static function get_image_credit( int $post_id ): array {

		$out = array();

		foreach ( self::IMAGE_CREDIT_FIELDS as $key => $meta_key ) {
			$out[ $key ] = trim( (string) get_post_meta( $post_id, $meta_key, true ) );
		}

		return $out;
	}

	/**
	 * Stores the image credit. Empty values delete their meta, so a cleared
	 * field leaves no trace behind.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $data    creator, source, license, url.
	 * @return void
	 */
	public static function save_image_credit( int $post_id, array $data ): void {

		foreach ( self::IMAGE_CREDIT_FIELDS as $key => $meta_key ) {

			$value = isset( $data[ $key ] ) ? (string) $data[ $key ] : '';

			$value = 'url' === $key
				? esc_url_raw( trim( $value ) )
				: sanitize_text_field( $value );

			if ( '' === trim( $value ) ) {
				delete_post_meta( $post_id, $meta_key );
				continue;
			}

			update_post_meta( $post_id, $meta_key, $value );
		}
	}

	/**
	 * Whether the post has anything to credit at all. A URL on its own does
	 * not count — a bare link with nothing to name is not an attribution.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function has_image_credit( int $post_id ): bool {

		$data = self::get_image_credit( $post_id );

		return '' !== $data['creator'] || '' !== $data['source'] || '' !== $data['license'];
	}

	/**
	 * The credit as a plain string: "Creator / Source (Licence)". Every part
	 * is optional, so the separators only appear where there is something on
	 * both sides of them.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function get_image_credit_text( int $post_id ): string {

		$data = self::get_image_credit( $post_id );
		$name = self::get_image_credit_name( $post_id );

		if ( '' === $data['license'] ) {
			return $name;
		}

		// Without a name the licence stands on its own, otherwise it trails
		// the name in brackets.
		return '' === $name ? $data['license'] : $name . ' (' . $data['license'] . ')';
	}

	/**
	 * The linkable part of the credit: creator and source, without the
	 * licence — a licence is not a place the image came from, and linking it
	 * would misattribute it.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function get_image_credit_name( int $post_id ): string {

		$data = self::get_image_credit( $post_id );

		return implode( ' / ', array_filter( array( $data['creator'], $data['source'] ) ) );
	}

	/**
	 * Nonce action/field for the disclosure meta box.
	 *
	 * @var string
	 */
	const DISCLOSURE_NONCE_ACTION = 'mmm_disclosure_meta_box';
	const DISCLOSURE_NONCE_FIELD  = 'mmm_disclosure_nonce';

	/**
	 * How a recipe or plan came about commercially. Editorial (our own, unpaid)
	 * is the default and stores no meta at all — same convention as the unlisted
	 * flag, so existing posts need no migration and "is this editorial?" stays a
	 * cheap NOT EXISTS lookup.
	 *
	 * Deliberately **not** the same thing as the author box: a guest recipe has
	 * an external author but is not advertising, while a sponsored recipe may
	 * well be written in-house. Collapsing both into one "external" badge is
	 * exactly what makes a disclosure legally worthless.
	 *
	 * @var string
	 */
	const DISCLOSURE_TYPE = '_mmm_disclosure_type';

	/**
	 * Who paid or provided. Only meaningful for the two commercial types, and
	 * cleared together with the type so a name from a previous classification
	 * can never surface in a label or in structured data.
	 *
	 * @var string
	 */
	const DISCLOSURE_SPONSOR = '_mmm_disclosure_sponsor';

	/** Unpaid guest contribution — credited, but not advertising. */
	const DISCLOSURE_GUEST = 'guest';

	/** Goods or services provided, no money. Still needs a visible label. */
	const DISCLOSURE_COOPERATION = 'cooperation';

	/** Paid placement. Needs "Anzeige"/"Werbung" in German-language markets. */
	const DISCLOSURE_SPONSORED = 'sponsored';

	/**
	 * The storable types, in the order the admin dropdown lists them. Editorial
	 * is the absence of a value and therefore not part of this list.
	 *
	 * @var string[]
	 */
	const DISCLOSURE_TYPES = array(
		self::DISCLOSURE_GUEST,
		self::DISCLOSURE_COOPERATION,
		self::DISCLOSURE_SPONSORED,
	);

	/**
	 * Types that must carry a visible label on the frontend. A guest
	 * contribution is attribution and handled by the author box; the other two
	 * are commercial and legally require disclosure.
	 *
	 * @var string[]
	 */
	const DISCLOSURE_LABELLED_TYPES = array(
		self::DISCLOSURE_COOPERATION,
		self::DISCLOSURE_SPONSORED,
	);

	/**
	 * Human-readable names of the disclosure types for the admin dropdown.
	 * A method rather than a constant because the strings are translated.
	 *
	 * @return array<string,string> Type => label, editorial (empty value) first.
	 */
	public static function disclosure_type_labels(): array {
		return array(
			''                           => __( 'Editorial (own content)', 'mixmymeals' ),
			self::DISCLOSURE_GUEST       => __( 'Guest contribution (unpaid)', 'mixmymeals' ),
			self::DISCLOSURE_COOPERATION => __( 'Cooperation (product provided)', 'mixmymeals' ),
			self::DISCLOSURE_SPONSORED   => __( 'Sponsored (paid placement)', 'mixmymeals' ),
		);
	}

	/**
	 * Disclosure Metabox: how a recipe or plan came about commercially.
	 *
	 * @param WP_Post $post Post being edited.
	 * @return void
	 */
	public function register_disclosure_metabox( WP_Post $post ): void {
		add_meta_box(
			'mmm_disclosure_meta_box',
			__( 'Disclosure', 'mixmymeals' ),
			array( $this, 'render_disclosure_metabox' ),
			$post->post_type,
			'side'
		);
	}

	/**
	 * Renders the disclosure type dropdown and the sponsor field.
	 *
	 * @param WP_Post $post Post being edited.
	 * @return void
	 */
	public function render_disclosure_metabox( WP_Post $post ): void {

		wp_nonce_field( self::DISCLOSURE_NONCE_ACTION, self::DISCLOSURE_NONCE_FIELD );

		$data = self::get_disclosure( $post->ID );

		echo '<p><label for="mmm_disclosure_type">'
			. esc_html__( 'How this content came about', 'mixmymeals' )
			. '</label></p>';

		echo '<select id="mmm_disclosure_type" name="mmm_disclosure_type" style="width:100%;">';
		foreach ( self::disclosure_type_labels() as $mmm_value => $mmm_label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $mmm_value ),
				selected( $data['type'], $mmm_value, false ),
				esc_html( $mmm_label )
			);
		}
		echo '</select>';

		echo '<p style="margin:12px 0 0;"><label for="mmm_disclosure_sponsor">'
			. esc_html__( 'Sponsor / partner', 'mixmymeals' )
			. '</label></p>';

		echo '<input type="text" id="mmm_disclosure_sponsor" name="mmm_disclosure_sponsor" style="width:100%;" value="'
			. esc_attr( $data['sponsor'] ) . '" />';

		echo '<p class="description">'
			. esc_html__( 'Only stored for cooperation and sponsored content — cleared automatically for the other types.', 'mixmymeals' )
			. '</p>';

		echo '<p class="description">'
			. esc_html__( 'Cooperation and sponsored content must carry a visible label on the page. A guest contribution is credited through the author box and is not advertising.', 'mixmymeals' )
			. '</p>';
	}

	/**
	 * Saves the disclosure fields after verifying nonce, autosave and caps.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_disclosure_meta( int $post_id ): void {

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified right here.
		if (
			! isset( $_POST[ self::DISCLOSURE_NONCE_FIELD ] )
			|| ! wp_verify_nonce(
				sanitize_key( wp_unslash( $_POST[ self::DISCLOSURE_NONCE_FIELD ] ) ),
				self::DISCLOSURE_NONCE_ACTION
			)
		) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		self::save_disclosure(
			$post_id,
			array(
				'type'    => wp_unslash( $_POST['mmm_disclosure_type'] ?? '' ),
				'sponsor' => wp_unslash( $_POST['mmm_disclosure_sponsor'] ?? '' ),
			)
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * The stored disclosure type, or '' for editorial content. An unknown value
	 * reads as editorial rather than being trusted through — the type decides
	 * whether a legally required label is printed, so it must never be a value
	 * the rendering side does not know.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function get_disclosure_type( int $post_id ): string {

		$type = (string) get_post_meta( $post_id, self::DISCLOSURE_TYPE, true );

		return in_array( $type, self::DISCLOSURE_TYPES, true ) ? $type : '';
	}

	/**
	 * The stored sponsor / partner name, or ''.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function get_disclosure_sponsor( int $post_id ): string {
		return trim( (string) get_post_meta( $post_id, self::DISCLOSURE_SPONSOR, true ) );
	}

	/**
	 * Both disclosure fields, keyed as save_disclosure() expects them, so read +
	 * override + save is a lossless round trip.
	 *
	 * @param int $post_id Post ID.
	 * @return array{type:string,sponsor:string}
	 */
	public static function get_disclosure( int $post_id ): array {
		return array(
			'type'    => self::get_disclosure_type( $post_id ),
			'sponsor' => self::get_disclosure_sponsor( $post_id ),
		);
	}

	/**
	 * Stores the disclosure. An unknown or empty type falls back to editorial
	 * and deletes both metas, so editorial content leaves no trace behind.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $data    type, sponsor.
	 * @return void
	 */
	public static function save_disclosure( int $post_id, array $data ): void {

		$type = isset( $data['type'] ) ? sanitize_key( (string) $data['type'] ) : '';

		if ( ! in_array( $type, self::DISCLOSURE_TYPES, true ) ) {
			$type = '';
		}

		if ( '' === $type ) {
			delete_post_meta( $post_id, self::DISCLOSURE_TYPE );
		} else {
			update_post_meta( $post_id, self::DISCLOSURE_TYPE, $type );
		}

		// The sponsor belongs to the commercial types only. Clearing it along
		// with the type keeps a name from an earlier classification out of both
		// the frontend label and the structured data.
		$sponsor = in_array( $type, self::DISCLOSURE_LABELLED_TYPES, true )
			? sanitize_text_field( (string) ( $data['sponsor'] ?? '' ) )
			: '';

		if ( '' === trim( $sponsor ) ) {
			delete_post_meta( $post_id, self::DISCLOSURE_SPONSOR );

			return;
		}

		update_post_meta( $post_id, self::DISCLOSURE_SPONSOR, $sponsor );
	}

	/**
	 * Whether the post carries any non-editorial classification at all.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_disclosed( int $post_id ): bool {
		return '' !== self::get_disclosure_type( $post_id );
	}

	/**
	 * Whether the post must show a visible disclosure label. **Every feature
	 * that renders a recipe or plan publicly must honour this** — the label is a
	 * legal requirement for commercial content, not a display preference.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function requires_disclosure_label( int $post_id ): bool {
		return in_array(
			self::get_disclosure_type( $post_id ),
			self::DISCLOSURE_LABELLED_TYPES,
			true
		);
	}

	/**
	 * Nonce action/field for the visibility meta box.
	 *
	 * @var string
	 */
	const VISIBILITY_NONCE_ACTION = 'mmm_visibility_meta_box';
	const VISIBILITY_NONCE_FIELD  = 'mmm_visibility_nonce';

	/**
	 * Visibility Metabox: keep a single recipe or plan out of all listings.
	 *
	 * @param WP_Post $post Post being edited.
	 * @return void
	 */
	public function register_visibility_metabox( $post ): void {
		add_meta_box(
			'mmm_visibility_meta_box',
			__( 'Visibility', 'mixmymeals' ),
			array( $this, 'render_visibility_metabox' ),
			$post->post_type,
			'side'
		);
	}

	/**
	 * Renders the "hide from listings" checkbox.
	 *
	 * @param WP_Post $post Post being edited.
	 * @return void
	 */
	public function render_visibility_metabox( WP_Post $post ): void {

		wp_nonce_field( self::VISIBILITY_NONCE_ACTION, self::VISIBILITY_NONCE_FIELD );

		echo '<label><input type="checkbox" name="mmm_unlisted" value="1" '
			. checked( Mixmymeals_Post_Types::is_unlisted( $post->ID ), true, false ) . ' /> '
			. esc_html__( 'Hide from listings', 'mixmymeals' )
			. '</label>'
			. '<p class="description">'
			. esc_html__( 'Keeps the page reachable under its own URL but removes it from archives, category pages, the search and the browse rows.', 'mixmymeals' )
			. '</p>';
	}

	/**
	 * Saves the visibility checkbox.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_visibility_meta( int $post_id ): void {

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified right here.
		if (
			! isset( $_POST[ self::VISIBILITY_NONCE_FIELD ] )
			|| ! wp_verify_nonce(
				sanitize_key( wp_unslash( $_POST[ self::VISIBILITY_NONCE_FIELD ] ) ),
				self::VISIBILITY_NONCE_ACTION
			)
		) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		Mixmymeals_Post_Types::set_unlisted( $post_id, ! empty( $_POST['mmm_unlisted'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Registers the affiliate links meta box for equipment.
	 */
	public function add_equipment_affiliate_meta_box(): void {
		add_meta_box(
			'mmm_equipment_affiliate_meta_box',
			__( 'Affiliate links', 'mixmymeals' ),
			array( $this, 'render_equipment_affiliate_meta_box_content' ),
			'mmm_equipment',
			'normal',
			'default'
		);
	}

	/**
	 * Renders the affiliate links meta box.
	 *
	 * Deliberately without JavaScript: the plugin has no build step, so every
	 * script would have to be kept in sync with its minified twin by hand.
	 * Instead the box shows the saved rows plus two empty spares — save, and
	 * two fresh spares appear. Emptying the URL deletes the row.
	 *
	 * @param WP_Post $post Equipment post.
	 */
	public function render_equipment_affiliate_meta_box_content( WP_Post $post ): void {

		wp_nonce_field( 'mmm_equipment_affiliate_meta_box', 'mmm_equipment_affiliate_nonce' );

		$equipment = new Mixmymeals_Equipment( $post->ID );
		$rows      = $equipment->get_affiliate_links();
		$spares    = max( 0, Mixmymeals_Equipment::MAX_AFFILIATE_LINKS - count( $rows ) );
		$spares    = min( 2, $spares );

		for ( $i = 0; $i < $spares; $i++ ) {
			$rows[] = array(
				'merchant' => '',
				'url'      => '',
				'note'     => '',
			);
		}
		?>
		<p class="description">
			<?php
			esc_html_e(
				'Shops where this piece of equipment can be bought. Links open in a new tab and carry rel="sponsored nofollow". Clear the URL to remove a row; save to get fresh empty rows.',
				'mixmymeals'
			);
			?>
		</p>

		<table class="widefat striped">
			<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Shop', 'mixmymeals' ); ?></th>
				<th scope="col"><?php esc_html_e( 'URL', 'mixmymeals' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Note', 'mixmymeals' ); ?></th>
			</tr>
			</thead>
			<tbody>
			<?php foreach ( $rows as $index => $row ) : ?>
				<tr>
					<td>
						<input type="text"
							name="mmm_affiliate[<?php echo esc_attr( (string) $index ); ?>][merchant]"
							value="<?php echo esc_attr( $row['merchant'] ); ?>"
							class="regular-text"
							placeholder="<?php esc_attr_e( 'e.g. Amazon', 'mixmymeals' ); ?>">
					</td>
					<td>
						<input type="url"
							name="mmm_affiliate[<?php echo esc_attr( (string) $index ); ?>][url]"
							value="<?php echo esc_attr( $row['url'] ); ?>"
							class="large-text code"
							placeholder="https://">
					</td>
					<td>
						<input type="text"
							name="mmm_affiliate[<?php echo esc_attr( (string) $index ); ?>][note]"
							value="<?php echo esc_attr( $row['note'] ); ?>"
							class="regular-text"
							placeholder="<?php esc_attr_e( 'e.g. around 30 €', 'mixmymeals' ); ?>">
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Saves the affiliate links. Sanitising happens in
	 * Mixmymeals_Equipment::set_affiliate_links().
	 *
	 * @param int $post_ID Equipment post ID.
	 */
	public function save_equipment_affiliate_meta( int $post_ID ): void {

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_ID ) ) {
			return;
		}
		if (
			! isset( $_POST['mmm_equipment_affiliate_nonce'] )
			|| ! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['mmm_equipment_affiliate_nonce'] ) ),
				'mmm_equipment_affiliate_meta_box'
			)
		) {
			return;
		}

		$rows = isset( $_POST['mmm_affiliate'] ) && is_array( $_POST['mmm_affiliate'] )
			? wp_unslash( $_POST['mmm_affiliate'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: array();

		$equipment = new Mixmymeals_Equipment( $post_ID );
		$equipment->set_affiliate_links( $rows );
	}
	/**
	 * Registers the "Goes well with" (Dazu passt) meta box on recipes.
	 */
	public function add_related_recipes_meta_box(): void {
		add_meta_box(
			'mmm_related_recipes_box',
			__( 'Goes well with', 'mixmymeals' ),
			[ $this, 'render_related_recipes_meta_box_content' ],
			'mmm_recipe',
			'side',
			'default'
		);
	}

	/**
	 * Persists the "Goes well with" recipe links.
	 *
	 * @param int $post_id Recipe post ID.
	 */
	public function save_related_recipes_metabox( int $post_id ): void {
		if ( ! isset( $_POST['mmm_related_recipes_nonce'] ) ||
			! wp_verify_nonce( sanitize_key( $_POST['mmm_related_recipes_nonce'] ), 'mmm_related_recipes_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$ids = isset( $_POST['mmm_related_recipes'] ) && is_array( $_POST['mmm_related_recipes'] )
			? array_map( 'intval', wp_unslash( $_POST['mmm_related_recipes'] ) )
			: [];

		$recipe = new Mixmymeals_Recipe( $post_id );
		$recipe->set_related_recipes( $ids );
	}

	/**
	 * Renders the "Goes well with" picker plus a read-only list of the recipes
	 * this one is a pairing for ("Fits with" / Passend zu).
	 *
	 * @param WP_Post $post Current recipe.
	 */
	public function render_related_recipes_meta_box_content( WP_Post $post ): void {

		wp_nonce_field( 'mmm_related_recipes_save', 'mmm_related_recipes_nonce' );

		$recipe   = new Mixmymeals_Recipe( $post->ID );
		$selected = $recipe->get_related_recipes();

		$recipes = get_posts( [
			'post_type'      => 'mmm_recipe',
			'posts_per_page' => -1,
			'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
			'post__not_in'   => [ $post->ID ],
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );

		echo '<p>' . esc_html__( 'Select recipes that go well with this one:', 'mixmymeals' ) . '</p>';

		if ( empty( $recipes ) ) {
			echo '<p>' . esc_html__( 'No other recipes available yet.', 'mixmymeals' ) . '</p>';
		} else {
			$field_id = 'mmm-related-filter-' . (int) $post->ID;
			printf(
				'<input type="search" id="%s" placeholder="%s" style="width:100%%;margin-bottom:6px;" />',
				esc_attr( $field_id ),
				esc_attr__( 'Filter recipes…', 'mixmymeals' )
			);

			echo '<ul class="mmm-related-recipes-list" style="max-height:220px;overflow:auto;margin:0;">';
			foreach ( $recipes as $r ) {
				printf(
					'<li><label><input type="checkbox" name="mmm_related_recipes[]" value="%d" %s> %s</label></li>',
					esc_attr( $r->ID ),
					in_array( $r->ID, $selected, true ) ? 'checked' : '',
					esc_html( get_the_title( $r ) )
				);
			}
			echo '</ul>';

			// Lightweight client-side filter for the checkbox list.
			printf(
				'<script>(function(){var f=document.getElementById(%1$s),l=f&&f.nextElementSibling;if(!f||!l)return;f.addEventListener("input",function(){var q=f.value.toLowerCase();l.querySelectorAll("li").forEach(function(li){li.style.display=li.textContent.toLowerCase().indexOf(q)>-1?"":"none";});});})();</script>',
				wp_json_encode( $field_id )
			);
		}

		// Read-only reverse links: recipes that list THIS recipe.
		$reverse = $recipe->get_reverse_related_recipes( 'any' );
		if ( ! empty( $reverse ) ) {
			echo '<hr />';
			echo '<p style="margin-bottom:4px;"><strong>' . esc_html__( 'Fits with', 'mixmymeals' ) . '</strong></p>';
			echo '<ul style="margin:0;">';
			foreach ( $reverse as $rev_id ) {
				printf(
					'<li><a href="%s">%s</a></li>',
					esc_url( (string) get_edit_post_link( $rev_id ) ),
					esc_html( get_the_title( $rev_id ) )
				);
			}
			echo '</ul>';
			echo '<p class="description">' . esc_html__( 'These recipes list this one under “Goes well with”.', 'mixmymeals' ) . '</p>';
		}
	}


}
