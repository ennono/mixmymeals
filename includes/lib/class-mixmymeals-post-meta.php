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
		/*
		 * No diet meta box any more: diets became terms of mmm_diet, and that
		 * taxonomy brings its own box with room for more than one answer. A
		 * second control writing the same thing would be a race, and the
		 * single-value select could not express "vegan and gluten-free"
		 * anyway. The save handler is gone with it; the old rows stay where
		 * they are until the migration moves them.
		 */
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

		/*
		 * Priority 20: after every save handler of this screen has written its
		 * meta, because what it checks is the result of them.
		 */
		add_action(
			'save_post_mmm_ingredient',
			array( $this, 'require_ingredient_nutrition' ),
			20
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

		add_action(
			'add_meta_boxes_mmm_ingredient',
			array( $this, 'add_ingredient_amounts_meta_box' )
		);
		add_action(
			'add_meta_boxes_mmm_ingredient',
			array( $this, 'add_ingredient_products_meta_box' )
		);
		add_action( 'save_post_mmm_ingredient', array( $this, 'save_ingredient_products' ) );
		add_action( 'wp_ajax_mmm_add_equipment', array( $this, 'ajax_add_equipment' ) );
		add_action( 'wp_ajax_mmm_rename_equipment', array( $this, 'ajax_rename_equipment' ) );
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
		// Its own hook, like the products box on the ingredient: a plan screen
		// that does not render this table must not wipe the meals by not
		// posting them.
		add_action( 'save_post_mmm_meal_plan', array( $this, 'save_plan_meals' ) );
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
		/*
		 * The class is on the template, not added by admin.js afterwards: the
		 * row now holds two selects, and "the select" is no longer a way to
		 * point at this one.
		 */
		echo '<select class="widefat ingredient-select">';
		echo '<option value="">-- ' . esc_html__(
			'Select Ingredient',
			'mixmymeals'
		) . ' --</option>';
		foreach ( $ingredients as $ing ) {
			echo '<option value="' . esc_attr( $ing->ID ) . '">'
				. esc_html( $ing->post_title ) . '</option>';
		}
		echo '</select> ';
		/*
		 * ingredient-amount-input is not optional here: admin.js gives a cloned
		 * row its field names by selecting exactly this class, and without it
		 * the input goes into the form nameless — the browser then omits it and
		 * a freshly added ingredient loses its amount on save. The class was
		 * missing until 3.25.2.
		 */
		echo '<input type="number" step="0.1" min="0" class="small-text ingredient-amount-input" placeholder="Amount">';
		/*
		 * Same class as the rendered row, and for the same reason the amount
		 * input carries one: admin.js names a cloned field by selecting it,
		 * and a select without the class goes into the form nameless — the
		 * browser then omits it and the pin is lost on save. Its options are
		 * filled from MixMyMealsProducts once an ingredient is chosen.
		 */
		echo '<select class="ingredient-product-select">'
			. '<option value="0">' . esc_html__( '— any product —', 'mixmymeals' )
			. '</option></select> ';
		echo ' <label class="mixmymeals-ingredient-optional">'
			. '<input type="checkbox" class="ingredient-optional-input" value="1"> '
			. esc_html__( 'optional', 'mixmymeals' ) . '</label>';
		echo ' <button type="button" class="button mixmymeals-remove-ingredient">-</button>';
		/*
		 * Leer in der Vorlage: welche Pakete zur Auswahl stehen, hängt an der
		 * Zutat, die hier noch nicht gewählt ist. admin.js füllt den Behälter,
		 * sobald eine Zutat feststeht — aus derselben lokalisierten Karte, aus
		 * der auch das Produkt-Select seine Optionen bekommt.
		 */
		echo '<div class="mixmymeals-ingredient-excludes"></div>';
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
				/* translators: %d: position of the step, starting at 1. */
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

		/*
		 * Preparation: a step done ahead of the meal ("soak the beans", two
		 * days before). The box switches the timing on; while it is off the
		 * timing fields are disabled and post nothing, which is the ordinary
		 * step. Set, the step moves to the front on save, sorted by how far
		 * ahead it is. The classes are what admin.js reads.
		 */
		$prep        = Mixmymeals_Recipe::step_prep( $step );
		$prep_amount = $prep ? (string) $prep['amount'] : '1';
		$prep_unit   = $prep ? $prep['unit'] : 'days';
		$disabled    = $prep ? '' : ' disabled';

		echo '<p class="mixmymeals-step-prep' . ( $prep ? ' is-prep' : '' ) . '">';
		echo '<label class="mixmymeals-step-prep-switch"><input type="checkbox" class="mixmymeals-step-prep-toggle" name="mmm_recipe_steps['
			. absint( $index ) . '][prep]" value="1"' . checked( (bool) $prep, true, false ) . '> '
			. esc_html__( 'Preparation ahead', 'mixmymeals' ) . '</label> ';
		echo '<span class="mixmymeals-step-prep-timing">';
		echo '<input type="number" min="1" step="1" class="small-text mixmymeals-step-prep-amount" name="mmm_recipe_steps['
			. absint( $index ) . '][prep_amount]" value="' . esc_attr( $prep_amount ) . '"' . $disabled . '> ';
		echo '<select class="mixmymeals-step-prep-unit" name="mmm_recipe_steps[' . absint( $index ) . '][prep_unit]"' . $disabled . '>';
		foreach ( array(
			'days'  => __( 'days before', 'mixmymeals' ),
			'hours' => __( 'hours before', 'mixmymeals' ),
		) as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $prep_unit, $value, false ) . '>'
				. esc_html( $label ) . '</option>';
		}
		echo '</select></span></p>';

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
			$selected_product   = (int) ( $ing['product_id'] ?? 0 );

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

			/*
			 * Amount. Both classes matter and neither is decoration:
			 * ingredient-amount-input is how admin.js finds the field in a
			 * cloned row, small-text is the anchor the unit-select AJAX inserts
			 * after. The template below must carry the same pair.
			 */
			echo '<input type="number" step="0.1" min="0" name="mmm_recipe_steps['
				. absint( $index ) . '][ingredients][' . absint( $i )
				. '][amount]" value="' . esc_attr( $amount )
				. '" class="small-text ingredient-amount-input" placeholder="Amount"> ';

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

			/*
			 * Which concrete article this dish is made with. Empty means the
			 * ingredient decides as it always did — through its reference
			 * product, or through nothing at all.
			 *
			 * It changes what the recipe may claim: with every ingredient's
			 * question settled the chip reads "Vegan" instead of
			 * "Vegan-capable". The options are filled client-side from
			 * MixMyMealsProducts when the ingredient changes, so a row picked
			 * just now offers the right articles without a save.
			 */
			echo '<select name="mmm_recipe_steps[' . absint( $index )
				. '][ingredients][' . absint( $i )
				. '][product_id]" class="ingredient-product-select">';
			echo '<option value="0">'
				. esc_html__( '— any product —', 'mixmymeals' ) . '</option>';

			if ( $ing_id && Mixmymeals_Ingredient::exists_by_id( $ing_id ) ) {
				foreach ( Mixmymeals_Product::for_ingredient( (int) $ing_id ) as $mmm_product ) {
					echo '<option value="' . absint( $mmm_product->get_id() ) . '" '
						. selected( $selected_product, $mmm_product->get_id(), false ) . '>'
						. esc_html( $mmm_product->label() ) . '</option>';
				}
			}

			echo '</select> ';

			/*
			 * Optional flag. An unchecked box submits nothing, which is exactly
			 * what set_steps() expects — it reads the key with ! empty(), so
			 * absence means "required" without a hidden companion field.
			 */
			echo '<label class="mixmymeals-ingredient-optional">'
				. '<input type="checkbox" class="ingredient-optional-input" name="mmm_recipe_steps['
				. absint( $index ) . '][ingredients][' . absint( $i )
				. '][optional]" value="1" '
				. checked( ! empty( $ing['optional'] ), true, false ) . '> '
				. esc_html__( 'optional', 'mixmymeals' ) . '</label> ';

			// Remove button
			echo '<button type="button" class="button mixmymeals-remove-ingredient">-</button>';

			/*
			 * Pakete, die für dieses Gericht nicht in Frage kommen.
			 *
			 * Eine Zutat gilt als „vegan möglich“, sobald *irgendeines* ihrer
			 * Produkte vegan ist — das ist der Sinn der „möglich“-Pille. Wo
			 * ein Paket für dieses Gericht nicht in Frage kommt (gesüßter
			 * Joghurt in einem herzhaften Gericht), soll es die Aussage auch
			 * nicht tragen und im Produktvergleich nicht als Spalte stehen.
			 *
			 * Nur gerendert, wenn es überhaupt Pakete gibt: eine leere
			 * Überschrift wäre eine Frage ohne Antwortmöglichkeit.
			 */
			$row_products = $ing_id > 0 ? Mixmymeals_Product::for_ingredient( (int) $ing_id ) : array();

			echo '<div class="mixmymeals-ingredient-excludes">';

			if ( $row_products ) {

				$blocked = array_map( 'intval', (array) ( $ing['excluded_products'] ?? array() ) );

				echo '<span class="mixmymeals-ingredient-excludes__label">'
					. esc_html__( 'Not an option here:', 'mixmymeals' ) . '</span> ';

				foreach ( $row_products as $row_product ) {
					// The product chosen in the select beside it cannot also be
					// ruled out; admin.js keeps this in step when the pin changes.
					$mmm_is_pin = (int) $selected_product === (int) $row_product->get_id();

					echo '<label' . ( $mmm_is_pin ? ' class="is-pinned"' : '' ) . '><input type="checkbox" name="mmm_recipe_steps['
						. absint( $index ) . '][ingredients][' . absint( $i )
						. '][excluded_products][]" value="'
						. absint( $row_product->get_id() ) . '" '
						. checked( ! $mmm_is_pin && in_array( $row_product->get_id(), $blocked, true ), true, false )
						. disabled( $mmm_is_pin, true, false )
						. '> ' . esc_html( $row_product->label() ) . '</label> ';
				}
			}

			echo '</div>';
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

		$recipe = new Mixmymeals_Recipe( $post->ID );

		// Empty rather than 0 when nobody has timed the dish: the field is the
		// one place an editor would read a zero as a statement, and the archive
		// filter now treats the absence of the row as "not stated".
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
				value="<?php echo esc_attr( $preparation_time > 0 ? (string) $preparation_time : '' ); ?>"
				min="1"
				step="1"
				placeholder="<?php esc_attr_e( 'not stated', 'mixmymeals' ); ?>"
				style="width:100%;"
			/>
		</p>
		<p>
			<label for="mmm_recipe_shelf_life">
				<?php esc_html_e( 'Shelf life of the prepared dish (days)', 'mixmymeals' ); ?>
			</label>
		</p>
		<p>
			<?php
			/*
			 * Empty means nobody has said; a typed 0 means "eat it the day it
			 * is made". Only a real number is printed back, or every untouched
			 * field would save itself as a zero.
			 */
			$mmm_shelf_life = $recipe->get_shelf_life();
			?>
			<input
				type="number"
				id="mmm_recipe_shelf_life"
				name="mmm_recipe_shelf_life"
				value="<?php echo null === $mmm_shelf_life ? '' : esc_attr( (string) $mmm_shelf_life ); ?>"
				min="0"
				step="1"
				style="width:100%;"
			/>
		</p>
		<p class="description">
			<?php esc_html_e( 'How long the finished dish keeps — not the same as the shelf life of its ingredients. Leave empty if unknown; 0 means it has to be eaten the day it is made.', 'mixmymeals' ); ?>
		</p>
		<p>
			<label for="mmm_recipe_storage">
				<?php esc_html_e( 'Storage of the prepared dish', 'mixmymeals' ); ?>
			</label>
		</p>
		<p>
			<select id="mmm_recipe_storage" name="mmm_recipe_storage" style="width:100%;">
				<option value=""><?php esc_html_e( '— not specified —', 'mixmymeals' ); ?></option>
				<?php foreach ( Mixmymeals_Post_Types::storage_types() as $mmm_value => $mmm_label ) : ?>
					<option value="<?php echo esc_attr( $mmm_value ); ?>"
						<?php selected( $recipe->get_storage_type(), $mmm_value ); ?>>
						<?php echo esc_html( $mmm_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="mmm_is_favorite">
				<input
					type="checkbox"
					id="mmm_is_favorite"
					name="mmm_is_favorite"
					value="1"
					<?php checked( '1', (string) get_post_meta( $post->ID, Mixmymeals_Recipe::META_IS_FAVORITE, true ) ); ?>
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
					absint( $recipe->count_cooked() )
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
				sanitize_key( wp_unslash( $_POST['mmm_recipe_details_nonce_field'] ) ),
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

		// Hoisted: every field below needs it, and tying its existence to the
		// presence of one particular input makes the next field a fatal error.
		$recipe = new Mixmymeals_Recipe( $post_ID );

		if ( isset( $_POST['mmm_preparation_time'] ) ) {
			$recipe->set_preparation_time( absint( wp_unslash( $_POST['mmm_preparation_time'] ) ) );
		}

		/*
		 * Empty clears, "0" stores zero. absint() on its own maps both to 0,
		 * which would silently claim every unanswered recipe keeps no days.
		 */
		if ( isset( $_POST['mmm_recipe_shelf_life'] ) ) {
			$raw = trim( sanitize_text_field( wp_unslash( $_POST['mmm_recipe_shelf_life'] ) ) );
			$recipe->set_shelf_life( '' === $raw ? null : absint( $raw ) );
		}

		// The setter discards anything outside the known list, so an unexpected
		// value clears the field rather than reaching the page.
		if ( isset( $_POST['mmm_recipe_storage'] ) ) {
			$recipe->set_storage_type( sanitize_text_field( wp_unslash( $_POST['mmm_recipe_storage'] ) ) );
		}

		// Favorite flag (featured in the recipe archive).
		if ( ! empty( $_POST['mmm_is_favorite'] ) ) {
			update_post_meta( $post_ID, Mixmymeals_Recipe::META_IS_FAVORITE, '1' );
		} else {
			delete_post_meta( $post_ID, Mixmymeals_Recipe::META_IS_FAVORITE );
		}

		// Cook counter. Only written when the submitted total actually differs
		// from the stored one, so re-saving a recipe never rewrites the starting
		// point behind the editor's back.
		if ( isset( $_POST['mmm_cook_count'] ) ) {
			$mmm_total  = sanitize_text_field( wp_unslash( $_POST['mmm_cook_count'] ) );
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
						/*
						 * The step's image. This array replaces the posted one
						 * wholesale, so a key that is not rebuilt here is not
						 * merely unsanitised — it is gone by the time
						 * set_steps() runs. `image` was missing, which silently
						 * cleared every step image on save.
						 */
						'image'       => isset( $step['image'] )
							? intval( wp_unslash( $step['image'] ) )
							: 0,
						// Preparation timing, only while the step's box is
						// ticked; set_steps() decides whether the pair makes
						// the step a preparation step.
						'prep_amount' => ! empty( $step['prep'] ) && isset( $step['prep_amount'] )
							? intval( wp_unslash( $step['prep_amount'] ) )
							: 0,
						'prep_unit'   => ! empty( $step['prep'] ) && isset( $step['prep_unit'] )
							? sanitize_key( wp_unslash( $step['prep_unit'] ) )
							: '',
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
									// An unchecked box posts nothing, so absence
									// is the "required" case and needs no
									// companion hidden field.
									'optional'  => ! empty( $ingredient['optional'] ),
									// This callback rebuilds the array key by
									// key, so one it does not name is gone
									// before set_steps() sees it — the way step
									// images and 'optional' were lost before.
									'product_id' => isset( $ingredient['product_id'] )
										? intval( wp_unslash( $ingredient['product_id'] ) )
										: 0,
									// Packets this dish rules out. Unticked
									// boxes post nothing, so an absent key is
									// an empty list; set_steps() drops ids that
									// do not belong to the ingredient.
									'excluded_products' => isset( $ingredient['excluded_products'] )
										? array_map( 'intval', (array) wp_unslash( $ingredient['excluded_products'] ) )
										: array(),
								);
							},
							$step['ingredients'] ?? array()
						),
					);
				},
				/*
				 * Every field of every step is sanitised inside the callback
				 * above — intval/floatval on the ingredient parts, the text
				 * fields through their own filters. The sniff cannot follow
				 * into a closure, so it only sees the raw array here.
				 */
				wp_unslash( $_POST['mmm_recipe_steps'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each element is sanitised in the callback above.
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
	 *  MEAL PLAN META BOX (the plan's meals, editable)
	 * ====================================================== */

	/**
	 * Registers the meals box on the plan screen.
	 *
	 * @return void
	 */
	public function add_meal_plan_overview_meta_box(): void {
		add_meta_box(
			'mixmymeals_plan_overview_box',
			__( 'Meals', 'mixmymeals' ),
			array( $this, 'render_meal_plan_overview_meta_box' ),
			'mmm_meal_plan',
			'normal'
		);
	}

	/**
	 * The plan's meals, editable in place.
	 *
	 * The same shape as the units table on an ingredient: a meal is a detail of
	 * its plan, and moving one to another day should not mean opening a screen
	 * of its own. The meal keeps that screen for everything this table leaves
	 * out — its note, its status, its own title.
	 *
	 * @param WP_Post $post The meal plan.
	 * @return void
	 */
	public function render_meal_plan_overview_meta_box( WP_Post $post ): void {

		wp_nonce_field( 'mmm_plan_meals_meta_box', 'mmm_plan_meals_nonce' );

		$plan  = new Mixmymeals_Meal_Plan( $post->ID );
		$meals = get_children(
			array(
				'post_parent' => $post->ID,
				'post_type'   => 'mmm_meal',
				'post_status' => 'any',
				'orderby'     => 'menu_order',
				'order'       => 'ASC',
			)
		);

		$recipes = Mixmymeals_Recipe::get_all_recipes();
		$days    = $plan->get_day_range();

		/*
		 * Sorted the way the plan reads, not by menu_order: every meal here
		 * carries a day, and a plan spanning Sunday has its own order of them
		 * (6, 7, 1, 2) that a numeric sort would scramble. Meals without a day
		 * go last, and equal days keep their insertion order.
		 */
		$order = array_flip( $days );
		$meals = (array) $meals;

		usort(
			$meals,
			static function ( $a, $b ) use ( $order ) {

				$rank = static function ( $meal ) use ( $order ) {
					$day = (int) get_post_meta( $meal->ID, '_mmm_day', true );
					return $order[ $day ] ?? PHP_INT_MAX;
				};

				$by_day = $rank( $a ) <=> $rank( $b );

				return 0 !== $by_day ? $by_day : $a->ID <=> $b->ID;
			}
		);

		echo '<table class="widefat striped mmm-plan-meals">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Recipe', 'mixmymeals' ) . '</th>';
		echo '<th>' . esc_html__( 'Type', 'mixmymeals' ) . '</th>';
		echo '<th>' . esc_html__( 'Day', 'mixmymeals' ) . '</th>';
		echo '<th>' . esc_html__( 'Servings', 'mixmymeals' ) . '</th>';
		echo '<th>' . esc_html__( 'Diet', 'mixmymeals' ) . '</th>';
		echo '<th>' . esc_html__( 'Meal', 'mixmymeals' ) . '</th>';
		echo '<th>' . esc_html__( 'Delete', 'mixmymeals' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( (array) $meals as $meal ) {

			$meal_obj = new Mixmymeals_Meal( $meal->ID );
			$field    = 'mmm_plan_meals[' . (int) $meal->ID . ']';

			echo '<tr data-id="' . esc_attr( (string) $meal->ID ) . '">';

			echo '<td>' . self::meal_recipe_select( $field . '[recipe_id]', $recipes, $meal_obj->get_recipe_id() ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
			echo '<td>' . self::meal_type_select( $field . '[type]', $meal_obj->get_type() ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
			echo '<td>' . self::meal_day_select( $field . '[day]', $days, (int) $meal_obj->get_day() ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.

			printf(
				'<td><input type="number" min="1" step="1" name="%s" value="%s" class="small-text"></td>',
				esc_attr( $field . '[servings]' ),
				esc_attr( (string) max( 1, (int) $meal_obj->get_servings() ) )
			);

			echo '<td>' . self::meal_diet_select( $field . '[diet]', new Mixmymeals_Recipe( $meal_obj->get_recipe_id() ), $meal_obj->get_diet() ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.

			// The meal keeps its own screen: notes, status and everything else
			// this table deliberately does not carry live there.
			$link = get_edit_post_link( $meal->ID );

			printf(
				'<td>%s</td>',
				$link
					? sprintf(
						'<a href="%s" target="_blank" rel="noopener">%s</a>',
						esc_url( $link ),
						esc_html( self::meal_row_title( $meal ) )
					)
					: esc_html( self::meal_row_title( $meal ) )
			);

			printf(
				'<td><label><input type="checkbox" name="%s" value="1"><span class="screen-reader-text">%s</span></label></td>',
				esc_attr( $field . '[delete]' ),
				esc_html(
					sprintf(
						/* translators: %s: name of the meal. */
						__( 'Delete %s', 'mixmymeals' ),
						self::meal_row_title( $meal )
					)
				)
			);

			echo '</tr>';
		}

		// The empty row at the foot, like the units box: adding a meal is the
		// thing one comes here to do, and a separate screen for it was the
		// reason this box was read-only for so long.
		echo '<tr class="mmm-plan-meals__new">';
		echo '<td>' . self::meal_recipe_select( 'mmm_plan_meals[new][recipe_id]', $recipes, 0 ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
		echo '<td>' . self::meal_type_select( 'mmm_plan_meals[new][type]', 'dinner' ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
		echo '<td>' . self::meal_day_select( 'mmm_plan_meals[new][day]', $days, 0 ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
		echo '<td><input type="number" min="1" step="1" name="mmm_plan_meals[new][servings]" value="" class="small-text" placeholder="1"></td>';
		echo '<td>' . self::meal_diet_select( 'mmm_plan_meals[new][diet]', null, '' ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
		echo '<td colspan="2"><span class="description">' . esc_html__( 'Pick a recipe to add a meal.', 'mixmymeals' ) . '</span></td>';
		/*
		 * The block editor posts the meta boxes without re-rendering them, so
		 * a second Save sends the filled-in new row a second time and would
		 * plan the same meal twice. The token is spent on the save that uses
		 * it; every later post of the same row finds it gone.
		 */
		printf(
			'<input type="hidden" name="mmm_plan_meals[new][token]" value="%s">',
			esc_attr( wp_generate_uuid4() )
		);
		echo '</tr>';

		echo '</tbody></table>';

		if ( ! $days ) {
			echo '<p class="description">' . esc_html__( 'This plan has no days yet — set its first and last day in the plan settings.', 'mixmymeals' ) . '</p>';
		}
	}

	/**
	 * How a meal names itself in the table.
	 *
	 * @param WP_Post $meal The meal.
	 * @return string
	 */
	private static function meal_row_title( WP_Post $meal ): string {

		if ( '' !== trim( (string) $meal->post_title ) ) {
			return (string) $meal->post_title;
		}

		/* translators: %d: ID of the meal, used when it has no title. */
		return sprintf( __( 'Meal #%d', 'mixmymeals' ), $meal->ID );
	}

	/**
	 * The recipe chooser of one meal row.
	 *
	 * @param string    $name    Field name.
	 * @param WP_Post[] $recipes All recipes.
	 * @param int       $current Chosen recipe.
	 * @return string
	 */
	private static function meal_recipe_select( string $name, array $recipes, int $current ): string {

		$out  = '<select name="' . esc_attr( $name ) . '" class="mmm-plan-meals__recipe">';
		$out .= '<option value="">' . esc_html__( '— Select Recipe —', 'mixmymeals' ) . '</option>';

		foreach ( $recipes as $recipe ) {
			$out .= sprintf(
				'<option value="%d" %s>%s</option>',
				absint( $recipe->ID ),
				selected( $current, $recipe->ID, false ),
				esc_html( $recipe->post_title )
			);
		}

		return $out . '</select>';
	}

	/**
	 * The meal-type chooser of one row.
	 *
	 * @param string $name    Field name.
	 * @param string $current Chosen type.
	 * @return string
	 */
	private static function meal_type_select( string $name, string $current ): string {

		$out = '<select name="' . esc_attr( $name ) . '">';

		foreach ( Mixmymeals_Meal::get_plaintext_meal_type_map() as $value => $label ) {
			$out .= sprintf(
				'<option value="%s" %s>%s</option>',
				esc_attr( (string) $value ),
				selected( $current, $value, false ),
				esc_html( (string) $label )
			);
		}

		return $out . '</select>';
	}

	/**
	 * The diet chooser of one row: the recipe as written, or a diet it can be
	 * cooked as (Mixmymeals_Recipe::get_diet_options()).
	 *
	 * Without a recipe — the empty row — every diet is offered and
	 * Mixmymeals_Meal::set_diet() decides on save whether the chosen recipe
	 * can hold it. A stored diet the recipe no longer offers stays listed, so
	 * saving the table does not silently drop it.
	 *
	 * @param string                 $name    Field name.
	 * @param Mixmymeals_Recipe|null $recipe  The row's recipe, null for the new row.
	 * @param string                 $current Stored diet.
	 * @return string
	 */
	public static function meal_diet_select( string $name, ?Mixmymeals_Recipe $recipe, string $current ): string {

		$slugs = null === $recipe
			? array_keys( Mixmymeals_Diet::types() )
			: $recipe->get_diet_options();

		if ( '' !== $current && ! in_array( $current, $slugs, true ) ) {
			$slugs[] = $current;
		}

		$out = '<select name="' . esc_attr( $name ) . '">'
			. '<option value="">' . esc_html__( 'As in the recipe', 'mixmymeals' ) . '</option>';

		foreach ( $slugs as $slug ) {
			$out .= sprintf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $slug ),
				selected( $current, $slug, false ),
				esc_html( Mixmymeals_Diet::label( $slug ) )
			);
		}

		return $out . '</select>';
	}

	/**
	 * The day chooser of one row.
	 *
	 * Offers the plan's own days, not all seven: a meal on a day the plan does
	 * not span is a meal nothing renders, and the day list is the one thing
	 * this table knows better than the meal's own screen — which reads the
	 * range from the parent and shows whatever it finds.
	 *
	 * @param string $name    Field name.
	 * @param int[]  $days    The plan's days, in its own order.
	 * @param int    $current Chosen day.
	 * @return string
	 */
	private static function meal_day_select( string $name, array $days, int $current ): string {

		$out  = '<select name="' . esc_attr( $name ) . '">';
		$out .= '<option value="">' . esc_html__( '— No day —', 'mixmymeals' ) . '</option>';

		// A meal already sitting outside the plan's range keeps its day in the
		// list, or saving this table would silently move it.
		$offer = $days;

		if ( $current > 0 && ! in_array( $current, $offer, true ) ) {
			$offer[] = $current;
		}

		foreach ( $offer as $day ) {
			$out .= sprintf(
				'<option value="%d" %s>%s</option>',
				absint( $day ),
				selected( $current, $day, false ),
				esc_html( Mixmymeals_Meal_Plan::weekday_name( (int) $day ) )
			);
		}

		return $out . '</select>';
	}

	/**
	 * Saves the meals edited in the plan's own screen.
	 *
	 * Its own nonce and its own hook, like the products box on the ingredient:
	 * a screen that does not render this table must not be able to wipe a
	 * plan's meals by not posting them.
	 *
	 * @param int $post_id Meal plan ID.
	 * @return void
	 */
	public function save_plan_meals( int $post_id ): void {

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if (
			! isset( $_POST['mmm_plan_meals_nonce'] )
			|| ! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['mmm_plan_meals_nonce'] ) ),
				'mmm_plan_meals_meta_box'
			)
		) {
			return;
		}

		if ( ! isset( $_POST['mmm_plan_meals'] ) || ! is_array( $_POST['mmm_plan_meals'] ) ) {
			return;
		}

		// Sanitised element by element below; the sniff only sees the array as
		// it arrives.
		$rows = wp_unslash( $_POST['mmm_plan_meals'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per element below.

		foreach ( $rows as $id => $row ) {

			if ( 'new' === $id ) {
				continue;
			}

			$meal_id = absint( $id );
			$meal    = get_post( $meal_id );

			if ( ! $meal || 'mmm_meal' !== $meal->post_type || (int) $meal->post_parent !== $post_id ) {
				continue;
			}

			/*
			 * Deleted for good, not trashed: a meal is a planning of a recipe
			 * inside one plan and has no life of its own — a trashed one would
			 * sit in the bin forever, and the cleanup run deletes orphans
			 * outright for the same reason.
			 */
			if ( ! empty( $row['delete'] ) ) {
				wp_delete_post( $meal_id, true );
				continue;
			}

			$this->apply_meal_row( new Mixmymeals_Meal( $meal_id ), (array) $row );
		}

		// The new row last, so its day and type see a table that is already in
		// the state the editor just described.
		$new = isset( $rows['new'] ) && is_array( $rows['new'] ) ? $rows['new'] : array();

		if ( ! empty( $new['recipe_id'] ) && $this->spend_new_row_token( 'plan_meal', $new['token'] ?? '' ) ) {

			$recipe_id = absint( $new['recipe_id'] );
			$recipe    = get_post( $recipe_id );

			if ( $recipe && 'mmm_recipe' === $recipe->post_type ) {

				// create() throws, and this runs on save_post: an uncaught
				// exception here would replace the editor with a blank page
				// and lose the rest of the save.
				try {
					$meal = Mixmymeals_Meal::create( (string) $recipe->post_title, $post_id );

					$this->apply_meal_row( $meal, $new );
				} catch ( \Exception $e ) {
					// Nothing to report to: this is a redirecting POST.
					unset( $e );
				}
			}
		}
	}

	/**
	 * Uses up a new row's one-shot token.
	 *
	 * The block editor posts the meta boxes without re-rendering them, so a
	 * second Save sends a filled-in "new" row again and creates the same
	 * thing twice. Every box here that creates a post from an empty row
	 * needs this; the scope keeps two boxes on one screen apart.
	 *
	 * @param string $scope Which box the token was rendered for.
	 * @param mixed  $token Raw token from the request.
	 * @return bool True when this save may create the row.
	 */
	private function spend_new_row_token( string $scope, $token ): bool {

		$token = sanitize_key( (string) $token );

		if ( '' === $token ) {
			return false;
		}

		$key = 'mmm_new_row_' . md5( $scope . '|' . $token );

		if ( get_transient( $key ) ) {
			return false;
		}

		// A day is far longer than an editing session and short enough that
		// nothing accumulates.
		set_transient( $key, 1, DAY_IN_SECONDS );

		return true;
	}

	/**
	 * Writes one row of the table onto a meal.
	 *
	 * @param Mixmymeals_Meal      $meal The meal.
	 * @param array<string, mixed> $row  Raw row.
	 * @return void
	 */
	private function apply_meal_row( Mixmymeals_Meal $meal, array $row ): void {
		/*
		 * An empty recipe keeps the stored one rather than clearing it: a meal
		 * without a recipe is a meal nothing can render, and the way to get rid
		 * of one is the delete box in the same row.
		 */
		if ( ! empty( $row['recipe_id'] ) ) {

			$recipe = get_post( absint( $row['recipe_id'] ) );

			if ( $recipe && 'mmm_recipe' === $recipe->post_type ) {
				$meal->set_recipe_id( (int) $recipe->ID );
			}
		}

		// set_type() stores whatever it is given, so the vocabulary is checked
		// here — the select can only post a known key, a forged POST cannot.
		if ( isset( $row['type'] ) ) {

			$type = sanitize_key( (string) $row['type'] );

			if ( array_key_exists( $type, Mixmymeals_Meal::get_plaintext_meal_type_map() ) ) {
				$meal->set_type( $type );
			}
		}

		if ( isset( $row['day'] ) ) {
			$meal->set_day( absint( $row['day'] ) );
		}

		if ( isset( $row['servings'] ) && '' !== $row['servings'] ) {
			$meal->set_servings( max( 1, absint( $row['servings'] ) ) );
		}

		// After the recipe, because set_diet() checks the diet against it: a
		// recipe changed in the same row must be cookable that way, or the
		// meal goes back to the recipe as written.
		if ( isset( $row['diet'] ) ) {
			$meal->set_diet( (string) $row['diet'] );
		}
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

	/**
	 * Guards against the demotion below re-entering through its own write.
	 *
	 * @var bool
	 */
	private static bool $forcing_ingredient_draft = false;

	/**
	 * An ingredient without nutrition does not stay published.
	 *
	 * The figures are the point of an ingredient: a published one with none
	 * silently subtracts from every recipe that uses it, because
	 * Mixmymeals_Recipe::_calculate_total_nutrition() has to read a missing
	 * value as 0. A draft is the honest state for that — and it is not a
	 * cosmetic one: every picker queries `post_status => 'publish'`, so a draft
	 * cannot be added to a recipe, while Mixmymeals_Ingredient::is_valid()
	 * ignores status on purpose and the recipes already using it keep working
	 * unchanged. Fill the seven fields in, publish again, done.
	 *
	 * **Only where the seven fields were on screen.** The guard is the
	 * ingredient screen's own nonce, the one save_ingredient_meta() uses, so a
	 * quick edit, a bulk edit and a REST write pass untouched. That is
	 * deliberate for REST: the MCP server creates an ingredient first and
	 * POSTs its nutrition in a second call, and a gate there would reject
	 * every one of them. `/audit` is what reports those — see
	 * `ingredients_without_nutrition`.
	 *
	 * It fires on every save of the screen, not only on the one that
	 * publishes: an ingredient that has been published without figures since
	 * long before this existed is in exactly the state this prevents, and
	 * saving it is the moment somebody is looking at it. The nutrition box
	 * says so before it happens and again afterwards.
	 *
	 * @param int $post_ID The ingredient being saved.
	 * @return void
	 */
	public function require_ingredient_nutrition( int $post_ID ): void {

		if ( self::$forcing_ingredient_draft ) {
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

		if (
			! isset( $_POST['mmm_ingredient_amounts_meta_nonce'] )
			|| ! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['mmm_ingredient_amounts_meta_nonce'] ) ),
				'mmm_ingredient_amounts_meta_box'
			)
		) {
			return;
		}

		if ( 'publish' !== get_post_status( $post_ID ) ) {
			return;
		}

		if ( ( new Mixmymeals_Ingredient( $post_ID ) )->has_nutrition() ) {
			return;
		}

		self::$forcing_ingredient_draft = true;

		wp_update_post(
			array(
				'ID'          => $post_ID,
				'post_status' => 'draft',
			)
		);

		self::$forcing_ingredient_draft = false;
	}

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

		/*
		 * What require_ingredient_nutrition() does, said where the empty
		 * fields are. In this box rather than in an admin notice because the
		 * ingredient screen is the block editor: it re-renders the meta box
		 * area after a save and drops admin notices, so this is the one place
		 * a message reaches the editor in both editors and immediately after
		 * the demotion rather than on the next page load.
		 *
		 * Two wordings for two states, and the difference is not cosmetic: by
		 * the time this re-renders, a published ingredient without figures has
		 * already been set back to a draft, and telling somebody what will
		 * happen when it just did is how a notice loses its credibility.
		 */
		if ( ! $ingredient_obj->has_nutrition() ) {

			echo '<div class="notice notice-warning inline"><p>';

			echo 'publish' === get_post_status( $post->ID )
				? esc_html__( 'No nutrition data. This ingredient will be set back to a draft the next time it is saved — a published ingredient without figures subtracts from every recipe that uses it.', 'mixmymeals' )
				: esc_html__( 'Without nutrition data this ingredient stays a draft, and a draft cannot be added to a recipe. Figures stated by its reference product count too.', 'mixmymeals' );

			echo '</p></div>';

		} else {
			/*
			 * Some figures stated and others not: the publish gate lets that
			 * through on purpose — a packet stating energy and fat and nothing
			 * else is a real packet — but each empty field still enters every
			 * recipe's total for that row as a 0, which is the same mistake
			 * one figure down. Named rather than counted, because which field
			 * it is decides whether it can be filled in at all.
			 */
			$unstated = $ingredient_obj->unstated_nutrition_keys();

			if ( $unstated ) {

				echo '<div class="notice notice-warning inline"><p>';

				printf(
					/* translators: %s: comma-separated list of nutrition figures, e.g. "Fibre, of which sugars". */
					esc_html__( 'Empty: %s. An empty field counts as 0 in every recipe total — a figure the packet does not state is better left to the reference product than guessed.', 'mixmymeals' ),
					esc_html(
						implode(
							', ',
							array_map(
								array( 'Mixmymeals_Ingredient', 'nutrition_label' ),
								$unstated
							)
						)
					)
				);

				echo '</p></div>';
			}
		}

		/*
		 * Contradictions in the figures themselves, on the resolved values —
		 * the ones recipes are built from, product override included — because
		 * a combination that cannot be true is worth seeing even when neither
		 * half is wrong on its own. The panel below shows which packet
		 * supplies what.
		 */
		foreach ( Mixmymeals_Ingredient::nutrition_warnings( $values ) as $mmm_warning ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html( $mmm_warning ) . '</p></div>';
		}

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

		/*
		 * Labels and order from the one source every nutrition table uses, so
		 * the fields read like the packet they are copied from: fat, of which
		 * saturates, carbohydrate, of which sugars, fibre, protein, salt. They
		 * used to be six hand-written strings in a different order ("Fat (g)",
		 * "Carbohydrates (g)", …), which is how the eighth figure could be
		 * added to the data model in 3.52.0 and still have nowhere to be
		 * typed in.
		 */
		$nutrition = array();

		foreach ( Mixmymeals_Ingredient::NUTRITION_FIGURE_KEYS as $mmm_key ) {

			// Energy has its own field above, in kcal rather than grams.
			if ( 'calories_100' === $mmm_key ) {
				continue;
			}

			$nutrition[ $mmm_key ] = sprintf(
				/* translators: %s: name of a nutrition figure, e.g. "Fat". The unit is grams. */
				__( '%s (g)', 'mixmymeals' ),
				Mixmymeals_Ingredient::nutrition_label( $mmm_key )
			);
		}

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

		/*
		 * The ingredient's own factor, not the resolved one: this field writes
		 * back, and showing a product's factor here would copy it into the
		 * ingredient on the next save — the rule the price field keeps.
		 */
		$mmm_factor = $ingredient_obj->get_own_cooking_factor();

		echo '<tr>';
		echo '<th scope="row"><label for="mmm_cooking_factor">'
			. esc_html__( 'Cooking factor', 'mixmymeals' ) . '</label></th>';
		echo '<td><input type="number" step="0.01" min="0.01" max="'
			. esc_attr( (string) Mixmymeals_Ingredient::COOKING_FACTOR_MAX )
			. '" name="mmm_cooking_factor" id="mmm_cooking_factor" value="'
			. esc_attr( null === $mmm_factor ? '' : (string) $mmm_factor )
			. '" class="small-text" placeholder="1"></td>';
		echo '</tr>';

		echo '</tbody></table>';

		echo '<p class="description">'
			. esc_html__( 'Cooked weight per gram raw, e.g. 2.4 for dry pasta, 0.75 for minced meat. Used only for figures per 100 g and the serving weight; empty means no correction. A product with its own factor overrides this one.', 'mixmymeals' )
			. '</p>';

		$mmm_resolved = $ingredient_obj->get_cooking_factor();

		if ( null !== $mmm_resolved && $mmm_resolved !== $mmm_factor ) {
			echo '<p class="description">' . esc_html(
				sprintf(
					/* translators: 1: product name, 2: cooking factor */
					__( 'Currently set by the product %1$s: %2$s.', 'mixmymeals' ),
					Mixmymeals_Product::reference_label( $post->ID ),
					number_format_i18n( $mmm_resolved, 2 )
				)
			) . '</p>';
		}
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

		/*
		 * An empty field is passed on as an empty string, and the setter
		 * deletes the row for it. It used to be floatval( '' ), which wrote a
		 * hard 0 — so a value nobody had ever stated was indistinguishable
		 * from one measured at zero, and a recipe total counted the ingredient
		 * as water. The product panel beside this one has always said "an
		 * empty field is not the same as zero"; now this one means it too.
		 *
		 * A field that is not posted at all is left out entirely rather than
		 * cleared, the way save_plan_meals() leaves meals alone on a screen
		 * that does not render them: a form without these inputs must not wipe
		 * figures it never showed.
		 */
		$data = array();

		foreach ( Mixmymeals_Ingredient::NUTRITION_FIGURE_KEYS as $mmm_field ) {

			$mmm_key = 'rmm_' . $mmm_field;

			if ( ! isset( $_POST[ $mmm_key ] ) ) {
				continue;
			}

			$mmm_value = trim( sanitize_text_field( wp_unslash( $_POST[ $mmm_key ] ) ) );

			$data[ $mmm_field ] = '' === $mmm_value ? '' : floatval( $mmm_value );
		}

		$ingredient_obj = new Mixmymeals_Ingredient( $post_ID );
		$ingredient_obj->set_nutrition( $data );

		// Not posted means not rendered: leave it alone, like the figures above.
		if ( isset( $_POST['mmm_cooking_factor'] ) ) {
			$ingredient_obj->set_cooking_factor(
				Mixmymeals_Ingredient::normalise_cooking_factor( sanitize_text_field( wp_unslash( $_POST['mmm_cooking_factor'] ) ) )
			);
		}

		// ----------------- Ingredient Amounts -----------------
		if ( isset( $_POST['mmm_ingredient_amounts'] )
			&& is_array( $_POST['mmm_ingredient_amounts'] ) ) {
			// Keys and values are sanitised element-wise in the loop below;
			// the sniff only sees the array as it arrives.
			$amounts = wp_unslash( $_POST['mmm_ingredient_amounts'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per element below.

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

					$amount_obj->set_divisor( absint( $amount_data['divisor'] ?? 0 ) );

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
						$amount_obj->set_divisor( absint( $amount_data['divisor'] ?? 0 ) );
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

		// Rows from the shared label list, so a figure added to the data model
		// appears here without this table having to be found first.
		foreach ( Mixmymeals_Meal_Plan::nutrition_labels() as $key => $label ) {
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
			'post__not_in'   => [ $post_id ], // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- excludes the single current post, not a growing list.
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
		$plan_end = $parent_id ? intval(
			get_post_meta(
				$parent_id,
				'end_day',
				true
			)
		) : 7;

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

		/*
		 * How the dish is cooked for this planning. The options are the
		 * diets the recipe can be cooked as and is not already; a recipe
		 * changed on this screen is checked against on save.
		 */
		echo '<p><label><strong>' . esc_html__( 'Diet', 'mixmymeals' ) . '</strong></label><br>';
		echo self::meal_diet_select( 'mmm_meal_diet', new Mixmymeals_Recipe( $meal_obj->get_recipe_id() ), $meal_obj->get_diet() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
		echo '<br><span class="description">'
			. esc_html__( 'Cooks the recipe with the products this diet needs — on the plan, in its nutrition and on the shopping list.', 'mixmymeals' )
			. '</span></p>';

		/*
		 * The meal's own text. Not core's Excerpt box — mmm_meal does not
		 * support `excerpt` on purpose, or there would be two controls on this
		 * screen writing one value. The label says what it is for; "Excerpt"
		 * would not.
		 */
		echo '<p><label for="mmm_meal_text"><strong>'
			. esc_html__( 'Note on this planning (optional)', 'mixmymeals' )
			. '</strong></label><br>';
		echo '<textarea id="mmm_meal_text" name="mmm_meal_text" rows="2" style="width:100%;" placeholder="'
			. esc_attr__( 'e.g. Cook double and freeze half.', 'mixmymeals' ) . '">'
			. esc_textarea( $meal_obj->get_text() ) . '</textarea>';
		echo '<span class="description">'
			. esc_html__( 'Belongs to this planning, not to the recipe — it is shown on the plan and on the meal page.', 'mixmymeals' )
			. '</span></p>';
	}

	public function save_meal_meta( int $post_id ): void {

		if ( self::$updating_meal_meta ) {
			return;
		}

		if (
			! isset( $_POST['mmm_meal_meta_nonce'] )
			||
			! wp_verify_nonce(
				sanitize_key( wp_unslash( $_POST['mmm_meal_meta_nonce'] ) ),
				'mmm_meal_meta_box'
			)
		) {
			// mixmymeals_write_to_log( sanitize_text_field( wp_unslash( $_POST['mmm_meal_meta_nonce']  ) ) );
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		/*
		 * Raised here and not above the guards: the setters below write through
		 * wp_update_post, which fires this hook again, and the flag is what
		 * stops that. Set before the checks it was never lowered again on the
		 * three early returns, so the *next* meal saved in the same request was
		 * skipped in silence — which is how creating a meal and then saving it
		 * in one pass lost everything the second save carried.
		 */
		self::$updating_meal_meta = true;

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

		if ( isset( $_POST['mmm_meal_text'] ) ) {
			$meal_obj->set_text( sanitize_textarea_field( wp_unslash( $_POST['mmm_meal_text'] ) ) );
		}

		// After the recipe: set_diet() checks the diet against it.
		if ( isset( $_POST['mmm_meal_diet'] ) ) {
			$meal_obj->set_diet( sanitize_key( wp_unslash( $_POST['mmm_meal_diet'] ) ) );
		}

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
		// The own figure, never the resolved one: saving this form writes the
		// field back, and a product's price must not end up in the ingredient.
		$price      = $ingredient->get_own_price_per_100g();
		$currency   = get_option( 'mixmymeals_currency', '€' );
		$reference  = Mixmymeals_Product::get_reference_id( $post->ID );
		$ref_price  = $reference ? ( new Mixmymeals_Product( $reference ) )->get_price_per_100g() : null;
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
		<?php if ( null !== $ref_price ) : ?>
			<p class="description">
				<?php
				printf(
					/* translators: 1: product name, 2: price per 100 g, 3: currency symbol */
					esc_html__( 'Currently priced by the product %1$s: %2$s %3$s / 100 g. This field applies only without it.', 'mixmymeals' ),
					esc_html( Mixmymeals_Product::reference_label( $post->ID ) ),
					esc_html( number_format_i18n( $ref_price, 2 ) ),
					esc_html( $currency )
				);
				?>
			</p>
		<?php endif; ?>
		<p>
			<label for="mmm_ingredient_shelf_life">
				<?php esc_html_e( 'Shelf life of the ingredient (days)', 'mixmymeals' ); ?>
			</label>
		</p>
		<p>
			<?php
			/*
			 * An empty field is "nobody has said", a typed 0 is "the day it is
			 * bought". The two must stay apart, so the value is only printed
			 * when the getter actually returned a number — printing 0 for null
			 * would turn every unanswered field into a claim on first save.
			 */
			$mmm_shelf_life = $ingredient->get_shelf_life();
			?>
			<input
				type="number"
				id="mmm_ingredient_shelf_life"
				name="mmm_ingredient_shelf_life"
				value="<?php echo null === $mmm_shelf_life ? '' : esc_attr( (string) $mmm_shelf_life ); ?>"
				min="0"
				step="1"
				style="width:100%;"
			/>
		</p>
		<p class="description">
			<?php esc_html_e( 'How long the ingredient itself keeps. Leave empty if unknown; 0 means it has to be used the day it is bought.', 'mixmymeals' ); ?>
		</p>
		<p>
			<label for="mmm_ingredient_storage">
				<?php esc_html_e( 'Storage of the ingredient', 'mixmymeals' ); ?>
			</label>
		</p>
		<p>
			<select id="mmm_ingredient_storage" name="mmm_ingredient_storage" style="width:100%;">
				<option value=""><?php esc_html_e( '— not specified —', 'mixmymeals' ); ?></option>
				<?php foreach ( Mixmymeals_Post_Types::storage_types() as $mmm_value => $mmm_label ) : ?>
					<option value="<?php echo esc_attr( $mmm_value ); ?>"
						<?php selected( $ingredient->get_storage_type(), $mmm_value ); ?>>
						<?php echo esc_html( $mmm_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
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

		/*
		 * An empty field clears the value; "0" stores a zero. absint() alone
		 * cannot tell those apart, so the raw string is tested first.
		 */
		if ( isset( $_POST['mmm_ingredient_shelf_life'] ) ) {
			$raw = trim( sanitize_text_field( wp_unslash( $_POST['mmm_ingredient_shelf_life'] ) ) );
			$ingredient->set_shelf_life( '' === $raw ? null : absint( $raw ) );
		}

		if ( isset( $_POST['mmm_ingredient_storage'] ) ) {
			$ingredient->set_storage_type( sanitize_text_field( wp_unslash( $_POST['mmm_ingredient_storage'] ) ) );
		}
	}


	/**
	 * Meta box listing the concrete products this ingredient can be bought as.
	 *
	 * @return void
	 */
	/**
	 * Persists the product table.
	 *
	 * Hooked separately from the ingredient's other meta and guarded by its own
	 * nonce, so a screen that does not render this box cannot wipe the products
	 * by simply not posting them.
	 *
	 * @param int $post_id Ingredient post ID.
	 * @return void
	 */
	public function save_ingredient_products( int $post_id ): void {

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified right here.
		if ( ! isset( $_POST['mmm_ingredient_products_nonce'] )
			|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['mmm_ingredient_products_nonce'] ) ), 'mmm_ingredient_products_meta_box' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$rows = isset( $_POST['mmm_products'] ) && is_array( $_POST['mmm_products'] )
			? wp_unslash( $_POST['mmm_products'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per field below.
			: array();

		foreach ( $rows as $key => $row ) {

			if ( ! is_array( $row ) ) {
				continue;
			}

			$title = isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '';

			if ( 'new' === $key ) {

				// An empty name means the row was left blank, not that someone
				// wants a nameless product. A brand is required too, and left
				// out it means the same thing — a half-filled row is not a
				// product anybody could find in a shop.
				if ( '' === $title || '' === trim( (string) ( $row['brand'] ?? '' ) ) ) {
					continue;
				}

				try {
					$product = Mixmymeals_Product::create( $post_id, $title );
				} catch ( \Exception $e ) {
					continue;
				}
			} else {

				$product = new Mixmymeals_Product( (int) $key );

				if ( ! $product->is_valid() || $product->get_ingredient_id() !== $post_id ) {
					continue;
				}

				if ( ! empty( $row['delete'] ) ) {
					// Clears the reference too, if it pointed here.
					$product->delete();
					continue;
				}

				if ( '' !== $title ) {
					$product->set_title( $title );
				}
			}

			/*
			 * An empty brand on an existing product is not stored. The form
			 * marks the field required, so this only happens when something
			 * bypassed it — and blanking a brand that is there is a loss, not
			 * an edit.
			 */
			$brand = isset( $row['brand'] ) ? trim( (string) $row['brand'] ) : '';

			if ( '' !== $brand ) {
				$product->set_brand( $brand );
			}
			// An unticked box posts nothing, so an absent key means "none of
			// them" here — unlike the text fields beside it, where absence
			// means the row was never rendered.
			$product->set_diets( isset( $row['diets'] ) ? (array) $row['diets'] : array() );
			$product->set_source( isset( $row['source'] ) ? (string) $row['source'] : '' );
			$product->set_package_weight( isset( $row['package_weight'] ) ? (float) $row['package_weight'] : 0.0 );
			$product->set_price(
				isset( $row['price'] ) && '' !== trim( (string) $row['price'] ) ? (float) $row['price'] : null
			);
			$product->set_cooking_factor(
				Mixmymeals_Ingredient::normalise_cooking_factor( $row['cooking_factor'] ?? null )
			);

			/*
			 * Every key is passed through, including the empty ones: an emptied
			 * field has to delete the stored value, or a figure corrected off a
			 * new packet could never be taken back out.
			 */
			$nutrition = array();

			foreach ( array_keys( Mixmymeals_Product::NUTRITION_FIELDS ) as $field ) {
				$nutrition[ $field ] = isset( $row[ $field ] ) && '' !== trim( (string) $row[ $field ] )
					? (float) $row[ $field ]
					: null;
			}

			$product->set_nutrition( $nutrition );
		}

		// The reference last: a product created or deleted in this same request
		// has to be settled before it can be pointed at.
		Mixmymeals_Product::set_reference_id(
			$post_id,
			isset( $_POST['mmm_products_reference'] ) ? (int) $_POST['mmm_products_reference'] : 0
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	public function add_ingredient_products_meta_box(): void {
		add_meta_box(
			'mmm_ingredient_products_meta_box',
			__( 'Products', 'mixmymeals' ),
			array( $this, 'render_ingredient_products_meta_box_content' ),
			'mmm_ingredient',
			'normal'
		);
	}

	/**
	 * Renders the product table.
	 *
	 * Two rows per product: identity and package on the first, the seven
	 * nutrition values on the second. Seven number fields alongside name,
	 * brand, source and two checkboxes would run off the screen, and the
	 * nutrition belongs together anyway — it is read off one panel on one
	 * packet.
	 *
	 * @param WP_Post $post Ingredient post.
	 * @return void
	 */
	public function render_ingredient_products_meta_box_content( WP_Post $post ): void {

		wp_nonce_field( 'mmm_ingredient_products_meta_box', 'mmm_ingredient_products_nonce' );

		$products  = Mixmymeals_Product::for_ingredient( $post->ID );
		$reference = Mixmymeals_Product::get_reference_id( $post->ID );

		echo '<p class="description">'
			. esc_html__( 'A product is one specific article of this ingredient. Its nutrition replaces the ingredient\'s — value by value; anything it does not state stays with the ingredient. Its price replaces the ingredient\'s once both price and package weight are filled in.', 'mixmymeals' )
			. '</p>';

		/*
		 * The explicit way back to "no product decides". Each product row
		 * below carries its own radio in the same mmm_products_reference
		 * group — native HTML radios, which cannot be unchecked by clicking
		 * them again. Once a site had picked a reference product there was no
		 * control that submitted 0, so the ingredient's own values became
		 * unreachable the moment any product was marked reference. The save
		 * side has always understood an absent or zero value as "ingredient's
		 * own" (Mixmymeals_Product::set_reference_id( $post_id, 0 )); only
		 * the form never offered it.
		 */
		echo '<p class="mmm-products__own"><label><input type="radio" name="mmm_products_reference" value="0" '
			. checked( 0, $reference, false ) . '> '
			. esc_html__( "Use the ingredient's own nutrition and price", 'mixmymeals' )
			. '</label></p>';

		echo '<table class="widefat mmm-products"><tbody>';

		/*
		 * Alternated here rather than with :nth-child in CSS: a product is two
		 * rows, so the arithmetic would have to know that, and would go wrong
		 * the moment the row count per product changes.
		 */
		$row = 0;

		foreach ( $products as $product ) {
			$this->render_product_rows( (string) $product->get_id(), $product, $reference, $row++ );
		}

		$this->render_product_rows( 'new', null, $reference, $row );

		echo '</tbody></table>';

		echo '<p class="description">'
			. esc_html__( 'Leave the nutrition fields empty for anything the packet does not state — an empty field is not the same as zero.', 'mixmymeals' )
			. '</p>';
	}

	/**
	 * The recipes using one thing, as a list of links.
	 *
	 * Units and products alike: both are rows an editor may remove from an
	 * ingredient, and the question before removing either is the same one.
	 * They deliver the same shape from their own usage_map(), so this stays a
	 * single builder rather than two lists that would slowly stop matching.
	 *
	 * This column held a number, which answered "may I delete this?" with a
	 * yes or a no and nothing else. The names answer the question behind it —
	 * *which* recipes would start reading their amounts as grams — and that is
	 * the one worth seeing before ticking Delete.
	 *
	 * The links go to the editor, not to the public page: this is an edit
	 * screen, half the recipes in here may be drafts, and an editor following
	 * one of these wants to fix it. They open in a new tab because the
	 * ingredient being edited usually has unsaved changes, and a column of
	 * links that quietly discard them would be a trap.
	 *
	 * @param array<int, array{recipe_id:int,recipe_title:string,status:string,occurrences:int}> $usage From get_usage().
	 * @return string Escaped markup.
	 */
	private static function usage_list( array $usage ): string {

		if ( ! $usage ) {
			return '<span class="mmm-usage__none">' . esc_html__( 'not used', 'mixmymeals' ) . '</span>';
		}

		$items = array();

		foreach ( $usage as $row ) {

			$recipe_id = (int) ( $row['recipe_id'] ?? 0 );

			if ( $recipe_id <= 0 ) {
				continue;
			}

			$title = self::shorten_title( (string) ( $row['recipe_title'] ?? '' ) );
			$link  = get_edit_post_link( $recipe_id );

			$label = $link
				? sprintf(
					'<a href="%s" target="_blank" rel="noopener">%s</a>',
					esc_url( $link ),
					esc_html( $title )
				)
				: esc_html( $title );

			// A unit used three times in one recipe is three lines that would
			// change meaning, and that is what the deletion warning counts.
			$occurrences = (int) ( $row['occurrences'] ?? 1 );

			if ( $occurrences > 1 ) {
				$label .= ' <span class="mmm-usage__times">' . esc_html( sprintf( '×%d', $occurrences ) ) . '</span>';
			}

			// A draft explains why a unit is in use although nothing public
			// shows it — without the note the list looks wrong.
			if ( 'publish' !== (string) ( $row['status'] ?? '' ) ) {
				$label .= ' <span class="mmm-usage__status">' . esc_html__( '(draft)', 'mixmymeals' ) . '</span>';
			}

			$items[] = '<li>' . $label . '</li>';
		}

		return $items ? '<ul class="mmm-usage__list">' . implode( '', $items ) . '</ul>' : '';
	}

	/**
	 * A recipe title cut to something a narrow column can carry.
	 *
	 * Cut on a word boundary and marked with "(…)", so it reads as a shortened
	 * name rather than as a name that happens to end oddly.
	 *
	 * @param string $title The full title.
	 * @param int    $max   Longest title kept whole.
	 * @return string
	 */
	private static function shorten_title( string $title, int $max = 30 ): string {

		$title = trim( wp_strip_all_tags( $title ) );

		if ( '' === $title ) {
			return __( '(no title)', 'mixmymeals' );
		}

		if ( mb_strlen( $title ) <= $max ) {
			return $title;
		}

		$cut   = mb_substr( $title, 0, $max );
		$space = mb_strrpos( $cut, ' ' );

		// Only back to the last space when that leaves something worth reading;
		// one very long word would otherwise shrink the title to nothing.
		if ( false !== $space && $space > $max / 2 ) {
			$cut = mb_substr( $cut, 0, $space );
		}

		return rtrim( $cut, ' ,;:-–' ) . ' (…)';
	}

	/**
	 * One product as two table rows, or the empty "add" pair when $product is
	 * null.
	 *
	 * @param string                 $index     Row key: product ID, or 'new'.
	 * @param Mixmymeals_Product|null $product  Product, or null for the new row.
	 * @param int                    $reference Currently referenced product ID.
	 * @return void
	 */
	private function render_product_rows( string $index, ?Mixmymeals_Product $product, int $reference, int $row = 0 ): void {

		$name  = 'mmm_products[' . $index . ']';
		$zebra = 0 === $row % 2 ? '' : ' mmm-products--alt';
		$is_new = null === $product;
		$id     = $is_new ? 0 : $product->get_id();

		$field = static function ( string $key, string $label, $value ) use ( $name ): void {
			echo '<label class="mmm-products__field">'
				. '<span>' . esc_html( $label ) . '</span>'
				. '<input type="number" step="0.01" min="0" name="'
				. esc_attr( $name ) . '[' . esc_attr( $key ) . ']" value="'
				. esc_attr( null === $value || '' === $value ? '' : (string) $value ) . '">'
				. '</label>';
		};

		$nutrition = $is_new
			? array_fill_keys( array_keys( Mixmymeals_Product::NUTRITION_FIELDS ), null )
			: $product->get_nutrition();

		// --- Row 1: what it is, and how big the packet is ---
		echo '<tr class="mmm-products__head' . esc_attr( $zebra ) . '">';
		echo '<td class="mmm-products__title">';
		echo '<label class="mmm-products__field">'
			. '<span>' . esc_html__( 'Product name', 'mixmymeals' ) . '</span>'
			. '<input type="text" class="regular-text" name="' . esc_attr( $name ) . '[title]" value="'
			. esc_attr( $is_new ? '' : $product->get_title() ) . '"></label>';
		echo '</td>';

		/*
		 * Required — but never on the blank row at the bottom, which is always
		 * rendered and would otherwise make the whole ingredient unsaveable
		 * until somebody invented a product. That row is enforced on save
		 * instead: no brand, no product, exactly as with a missing name.
		 */
		echo '<td><label class="mmm-products__field">'
			. '<span>' . esc_html__( 'Brand', 'mixmymeals' ) . ' *</span>'
			. '<input type="text" name="' . esc_attr( $name ) . '[brand]" value="'
			. esc_attr( $is_new ? '' : $product->get_brand() ) . '"'
			. ( $is_new ? '' : ' required' ) . '></label></td>';

		echo '<td><label class="mmm-products__field">'
			. '<span>' . esc_html__( 'Package (g)', 'mixmymeals' ) . '</span>'
			. '<input type="number" step="0.01" min="0" class="mmm-products__pack" name="'
			. esc_attr( $name ) . '[package_weight]" value="'
			. esc_attr( $is_new || ! $product->get_package_weight() ? '' : (string) $product->get_package_weight() )
			. '"></label></td>';

		$price = $is_new ? null : $product->get_price();
		echo '<td><label class="mmm-products__field">'
			. '<span>' . esc_html(
				sprintf(
					/* translators: %s: currency symbol */
					__( 'Price per package (%s)', 'mixmymeals' ),
					get_option( 'mixmymeals_currency', '€' )
				)
			) . '</span>'
			. '<input type="number" step="0.01" min="0" class="mmm-products__pack" name="'
			. esc_attr( $name ) . '[price]" value="'
			. esc_attr( null === $price ? '' : (string) $price )
			. '"></label></td>';

		/*
		 * Checkboxes, not a select: diets became terms and a product can carry
		 * several — vegan and gluten-free are different questions. Products
		 * have no edit screen of their own (show_ui is false; they are a
		 * detail of their ingredient), so the taxonomy's own box never
		 * appears and the assignment has to live here.
		 *
		 * Nothing ticked means the ingredient's own classification stands,
		 * which is what the note says. "—" would read as "no diet information
		 * anywhere".
		 */
		$mmm_product_diets = $is_new ? array() : $product->get_diets();

		echo '<td><fieldset class="mmm-products__field mmm-products__diets">'
			. '<legend>' . esc_html__( 'Diet', 'mixmymeals' ) . '</legend>';

		foreach ( Mixmymeals_Diet::types() as $mmm_slug => $mmm_type ) {
			echo '<label><input type="checkbox" name="' . esc_attr( $name ) . '[diets][]" value="'
				. esc_attr( $mmm_slug ) . '"'
				. checked( in_array( $mmm_slug, $mmm_product_diets, true ), true, false ) . '> '
				. esc_html( $mmm_type['label'] ) . '</label>';
		}

		echo '<span class="description">' . esc_html__( 'Nothing ticked: same as ingredient.', 'mixmymeals' )
			. '</span></fieldset></td>';

		echo '<td class="mmm-products__actions">';
		if ( ! $is_new ) {
			echo '<label class="mmm-products__use"><input type="radio" name="mmm_products_reference" value="'
				. esc_attr( (string) $id ) . '" ' . checked( $reference, $id, false ) . '> '
				. esc_html__( 'Use this product\'s nutrition and price', 'mixmymeals' ) . '</label>';
			echo '<label class="mmm-products__delete"><input type="checkbox" name="' . esc_attr( $name )
				. '[delete]" value="1"> ' . esc_html__( 'Delete', 'mixmymeals' ) . '</label>';
		} else {
			echo '<span class="description">' . esc_html__( 'Save to add this product.', 'mixmymeals' ) . '</span>';
		}
		echo '</td>';
		echo '</tr>';

		// --- Row 2: the panel off the packet ---
		echo '<tr class="mmm-products__detail' . esc_attr( $zebra ) . '"><td colspan="6">';
		// The flex container is a div, not the cell: display:flex on a <td>
		// takes it out of the table layout and it collapses to the width of
		// the first column.
		echo '<div class="mmm-products__panel">';
		echo '<span class="mmm-products__panel-label">'
			. esc_html__( 'Nutrition per 100 g, as stated on the packet', 'mixmymeals' ) . '</span>';
		echo '<div class="mmm-products__figures">';
		$field( 'calories_100', __( 'kcal/100 g', 'mixmymeals' ), $nutrition['calories_100'] ?? null );
		$field( 'fat', __( 'Fat', 'mixmymeals' ), $nutrition['fat'] ?? null );
		$field( 'saturates', __( 'of which saturates', 'mixmymeals' ), $nutrition['saturates'] ?? null );
		$field( 'carbs', __( 'Carbohydrate', 'mixmymeals' ), $nutrition['carbs'] ?? null );
		$field( 'sugar', __( 'of which sugars', 'mixmymeals' ), $nutrition['sugar'] ?? null );
		$field( 'fiber', __( 'Fibre', 'mixmymeals' ), $nutrition['fiber'] ?? null );
		$field( 'protein', __( 'Protein', 'mixmymeals' ), $nutrition['protein'] ?? null );
		$field( 'salt', __( 'Salt', 'mixmymeals' ), $nutrition['salt'] ?? null );

		echo '</div>'; // .mmm-products__figures

		// On the packet's own figures, not the resolved ones: this row is where
		// they are typed, and a warning has to be fixable where it appears.
		foreach ( Mixmymeals_Ingredient::nutrition_warnings( $nutrition ) as $mmm_warning ) {
			echo '<p class="mmm-products__warning">' . esc_html( $mmm_warning ) . '</p>';
		}

		$mmm_product_factor = $is_new ? null : $product->get_cooking_factor();

		echo '<label class="mmm-products__field">'
			. '<span>' . esc_html__( 'Cooking factor', 'mixmymeals' ) . '</span>'
			. '<input type="number" step="0.01" min="0.01" max="'
			. esc_attr( (string) Mixmymeals_Ingredient::COOKING_FACTOR_MAX ) . '" name="'
			. esc_attr( $name ) . '[cooking_factor]" value="'
			. esc_attr( null === $mmm_product_factor ? '' : (string) $mmm_product_factor ) . '" placeholder="'
			. esc_attr__( 'as ingredient', 'mixmymeals' ) . '">'
			. '</label>';

		echo '<label class="mmm-products__field mmm-products__field--source">'
			. '<span>'
			. esc_html__( 'Source of these figures', 'mixmymeals' ) . '</span>'
			. '<input type="text" class="regular-text" name="' . esc_attr( $name ) . '[source]" value="'
			. esc_attr( $is_new ? '' : $product->get_source() ) . '" placeholder="'
			. esc_attr__( 'e.g. packet, 08/2026', 'mixmymeals' ) . '">'
			. '</label>';

		/*
		 * Which recipes chose this packet. In the panel rather than in the
		 * identity row above: a list of links is taller than an input and
		 * would drag the whole flex row down, and this is a fact about the
		 * product in the same way the source is.
		 *
		 * The blank row has nothing to be used by yet.
		 */
		if ( ! $is_new ) {

			$usage = $product->get_usage();
			$lines = $product->count_usage();

			echo '<div class="mmm-products__usage">';

			// Only where there is something to count: the list says "not used"
			// on its own, and a heading reading "chosen in 0 recipe lines"
			// above it is the same nothing said twice.
			if ( $lines > 0 ) {
				echo '<span class="mmm-products__panel-label">'
					. esc_html(
						sprintf(
							/* translators: %d: number of recipe lines. */
							_n( 'Chosen in %d recipe line', 'Chosen in %d recipe lines', $lines, 'mixmymeals' ),
							$lines
						)
					)
					. '</span>';
			}

			echo self::usage_list( $usage ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped per link in the builder.

			/*
			 * Said out loud, because otherwise "0 recipes" sits next to the
			 * packet that in fact supplies every recipe using this ingredient.
			 * The number counts choices made in a recipe; being the reference
			 * is a property of the ingredient, not of this packet.
			 */
			if ( $id === $reference ) {
				echo '<p class="description">'
					. esc_html__( 'Beside this, it supplies the figures wherever a recipe has not chosen a product of its own.', 'mixmymeals' )
					. '</p>';
			}

			echo '</div>';
		}

		echo '</div>'; // .mmm-products__panel
		echo '</td></tr>';
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
		echo '<th>' . esc_html__( 'Used in', 'mixmymeals' ) . '</th>';
		echo '<th>' . esc_html__( 'Weight', 'mixmymeals' ) . '</th>';
		echo '<th>' . esc_html__( 'Divisibility', 'mixmymeals' ) . '</th>';
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

				$usage = $amount->get_usage();

				echo '<tr data-id="' . esc_attr( $unit->ID ) . '">';

				// Titel
				//echo '<td>' . esc_html( $amount->get_title() ) . '</td>';
				echo '<td>';
				echo '<input type="text" 
                    name="mmm_ingredient_amounts[' . esc_attr( $unit->ID ) . '][title]" 
                    value="' . esc_attr( $amount->get_title() ) . '" 
                    class="regular-text">';
				echo '</td>';

				// Nutzung: welche Rezepte, nicht wie viele.
				echo '<td class="mmm-unit-usage">' . self::usage_list( $usage ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped per link below.

				// Gewicht als Input-Feld
				echo '<td>';
				echo '<input type="number" step="0.01" min="0" name="mmm_ingredient_amounts['
					. esc_attr( $unit->ID ) . '][weight]" value="'
					. esc_attr( $amount->get_weight() )
					. '" class="small-text">';
				echo '</td>';

				// Teilbarkeit: eine Zahl, eine Aussage.
				echo '<td>';
				echo '<input type="number" step="1" min="0" name="mmm_ingredient_amounts['
					. esc_attr( $unit->ID ) . '][divisor]" value="'
					. esc_attr( (string) ( $amount->get_divisor() ?: '' ) )
					. '" class="small-text" placeholder="—">';
				echo '<p class="description">'
					. esc_html__( '1 = whole ones only, 2 = can be halved, 4 = quarters. Empty means any amount.', 'mixmymeals' )
					. '</p>';
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
		echo '<input type="number" step="1" min="0" name="mmm_ingredient_amounts[new][divisor]" value="" class="small-text" placeholder="—">';
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

	/**
	 * Registers the equipment box on the recipe screen.
	 *
	 * @return void
	 */
	public function add_equipment_meta_box(): void {
		/*
		 * Full width, not the sidebar: the box stopped being a list of ticks
		 * the day the names became editable, and a column of text inputs in a
		 * 280px sidebar is the squeeze the products box was just cured of.
		 */
		add_meta_box(
			'mmm_equipment_box',
			__( 'Required Equipment', 'mixmymeals' ),
			array( $this, 'render_equipment_meta_box_content' ),
			'mmm_recipe',
			'normal',
			'default'
		);
	}

	/**
	 * The equipment this recipe needs, tickable and renameable in place.
	 *
	 * Equipment is *shared*, unlike the units of an ingredient or the meals of
	 * a plan: it has no parent, and every recipe that needs a frying pan
	 * points at the same post. So this box ticks, renames and adds — and
	 * deliberately does not delete. Deleting from here would take the piece
	 * out of every other recipe silently, and nothing on this screen says
	 * which those are.
	 *
	 * The same reason the count is shown at all: a rename is global, and the
	 * number is the only thing on the screen that says how far it reaches.
	 *
	 * @param WP_Post $post The recipe.
	 * @return void
	 */
	public function render_equipment_meta_box_content( WP_Post $post ): void {

		wp_nonce_field( 'mmm_equipment_save', 'mmm_equipment_nonce' );

		$recipe    = new Mixmymeals_Recipe( $post->ID );
		$selected  = array_map( 'intval', $recipe->get_equipment() );
		$equipment = Mixmymeals_Equipment::get_all();
		$counts    = Mixmymeals_Equipment::usage_counts();

		echo '<table class="widefat striped mmm-equipment">';
		echo '<thead><tr>';
		echo '<th class="mmm-equipment__pick">' . esc_html__( 'Needed', 'mixmymeals' ) . '</th>';
		echo '<th>' . esc_html__( 'Name', 'mixmymeals' ) . '</th>';
		echo '<th class="mmm-equipment__count">' . esc_html__( 'Used in', 'mixmymeals' ) . '</th>';
		echo '<th class="mmm-equipment__open">' . esc_html__( 'Edit', 'mixmymeals' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $equipment as $piece ) {
			// One builder for the rendered row and the one AJAX appends, or
			// a piece added without a reload would differ from its neighbours.
			echo self::equipment_row( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the builder.
				(int) $piece->ID,
				(string) $piece->post_title,
				in_array( (int) $piece->ID, $selected, true ),
				(int) ( $counts[ (int) $piece->ID ] ?? 0 )
			);
		}

		// The empty row: a piece of equipment that does not exist yet is the
		// one reason an author had to leave the recipe, losing whatever was
		// unsaved on the way.
		echo '<tr class="mmm-equipment__new">';
		echo '<td class="mmm-equipment__pick" aria-hidden="true">+</td>';
		printf(
			'<td><input type="text" class="mmm-equipment__title" name="mmm_equipment_new" value="" placeholder="%s"></td>',
			esc_attr__( 'e.g. 26 cm springform tin', 'mixmymeals' )
		);
		echo '<td colspan="2"><span class="description mmm-equipment__hint">'
			. esc_html__( 'Created and ticked when you save.', 'mixmymeals' )
			. '</span></td>';
		echo '</tr>';

		echo '</tbody></table>';

		echo '<p class="description">'
			. esc_html__( 'A piece of equipment is shared: renaming one here renames it in every recipe that needs it. Nothing is deleted from this screen.', 'mixmymeals' )
			. '</p>';
	}

	/**
	 * Creates (or finds) a piece of equipment without saving the recipe.
	 *
	 * Adding one used to cost a full post save, after which the box did not
	 * re-render and the new row simply did not appear — so the one thing this
	 * box exists for gave no sign that it had worked. The piece is created
	 * here; whether *this* recipe needs it stays recipe data and travels with
	 * the recipe's own save, which is why the row comes back ticked but not
	 * yet stored on the post.
	 *
	 * @return void
	 */
	public function ajax_add_equipment(): void {

		check_ajax_referer( 'mmm_equipment_ajax', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'mixmymeals' ) ), 403 );
		}

		$title = isset( $_POST['title'] )
			? sanitize_text_field( wp_unslash( $_POST['title'] ) )
			: '';

		if ( '' === $title ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a name.', 'mixmymeals' ) ), 400 );
		}

		$id = $this->find_or_create_equipment( $title );

		if ( $id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Could not be created.', 'mixmymeals' ) ), 500 );
		}

		$piece = new Mixmymeals_Equipment( $id );

		wp_send_json_success(
			array(
				'id'    => $id,
				'title' => $piece->get_title(),
				// find_or_create may have returned one that already exists and
				// is already in the table; the client decides on this.
				'html'  => self::equipment_row( $id, $piece->get_title(), true, $piece->count_recipes() ),
			)
		);
	}

	/**
	 * Renames a piece of equipment without saving the recipe.
	 *
	 * @return void
	 */
	public function ajax_rename_equipment(): void {

		check_ajax_referer( 'mmm_equipment_ajax', 'nonce' );

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		if ( ! current_user_can( 'edit_post', $id ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'mixmymeals' ) ), 403 );
		}

		$piece = new Mixmymeals_Equipment( $id );

		if ( ! $piece->is_valid() ) {
			wp_send_json_error( array( 'message' => __( 'Not found.', 'mixmymeals' ) ), 404 );
		}

		$title = isset( $_POST['title'] )
			? sanitize_text_field( wp_unslash( $_POST['title'] ) )
			: '';

		// Same rule as the posted form: an empty name keeps the stored one,
		// because blanking is a loss and it would reach every recipe.
		if ( '' === $title ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please enter a name.', 'mixmymeals' ),
					'title'   => $piece->get_title(),
				),
				400
			);
		}

		if ( $title !== $piece->get_title() ) {
			$piece->set_title( $title );
		}

		wp_send_json_success(
			array(
				'id'    => $id,
				'title' => $piece->get_title(),
			)
		);
	}

	/**
	 * One row of the equipment table.
	 *
	 * Shared by the renderer and the AJAX that appends a freshly created
	 * piece: two builders would drift, and the row added without a reload is
	 * exactly the one nobody checks against its neighbours.
	 *
	 * @param int    $id      Equipment ID.
	 * @param string $title   Its name.
	 * @param bool   $checked Whether this recipe needs it.
	 * @param int    $count   Recipes needing it.
	 * @return string Escaped markup.
	 */
	private static function equipment_row( int $id, string $title, bool $checked, int $count ): string {

		$link = get_edit_post_link( $id );

		return sprintf(
			'<tr>'
				. '<td class="mmm-equipment__pick"><label><input type="checkbox" name="mmm_equipment[]" value="%1$d"%2$s><span class="screen-reader-text">%3$s</span></label></td>'
				. '<td><input type="text" class="mmm-equipment__title" name="mmm_equipment_titles[%1$d]" value="%4$s" data-mmm-title="%4$s"></td>'
				. '<td class="mmm-equipment__count">%5$s</td>'
				. '<td class="mmm-equipment__open">%6$s</td>'
			. '</tr>',
			$id,
			checked( $checked, true, false ),
			esc_html(
				sprintf(
					/* translators: %s: name of a piece of equipment. */
					__( 'This recipe needs %s', 'mixmymeals' ),
					$title
				)
			),
			esc_attr( $title ),
			esc_html(
				sprintf(
					/* translators: %d: number of recipes. */
					_n( '%d recipe', '%d recipes', $count, 'mixmymeals' ),
					$count
				)
			),
			$link
				// New tab, like the unit usage list: the recipe usually has
				// unsaved changes and a link that discards them is a trap.
				? sprintf(
					'<a href="%s" target="_blank" rel="noopener">%s</a>',
					esc_url( $link ),
					esc_html__( 'Open', 'mixmymeals' )
				)
				: ''
		);
	}

	/**
	 * Saves the equipment picked, renamed and added on the recipe screen.
	 *
	 * @param int $post_id Recipe ID.
	 * @return void
	 */
	public function save_equipment_metabox( int $post_id ): void {

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if (
			! isset( $_POST['mmm_equipment_nonce'] )
			|| ! wp_verify_nonce(
				sanitize_key( wp_unslash( $_POST['mmm_equipment_nonce'] ) ),
				'mmm_equipment_save'
			)
		) {
			return;
		}

		$picked = array_map(
			'absint',
			(array) wp_unslash( $_POST['mmm_equipment'] ?? array() ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- absint per element.
		);

		// Renames first, so a piece created below cannot be renamed by a
		// stale row in the same post.
		$titles = (array) wp_unslash( $_POST['mmm_equipment_titles'] ?? array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per element.

		foreach ( $titles as $id => $title ) {

			$piece = new Mixmymeals_Equipment( absint( $id ) );

			if ( ! $piece->is_valid() ) {
				continue;
			}

			$title = sanitize_text_field( (string) $title );

			/*
			 * An empty name keeps the stored one — blanking is a loss, not an
			 * edit, and here it would leave a nameless row in every recipe
			 * that uses the piece. Written only on a real change, so a save
			 * that touched nothing does not bump every piece of equipment.
			 */
			if ( '' !== $title && $title !== $piece->get_title() ) {
				$piece->set_title( $title );
			}
		}

		/*
		 * Resolved on every save, not only the first. The block editor posts
		 * the meta boxes without re-rendering them, so a second Save carries
		 * the *old* checkbox list — one that cannot contain the piece the
		 * first Save just created. set_equipment() writes the list whole, so
		 * gating this on a one-shot token silently unticked the new piece on
		 * the very next save.
		 *
		 * No token is needed instead: unlike a meal, a piece of equipment has
		 * a natural key, and find_or_create_equipment() reuses the one with
		 * that name rather than making a second.
		 */
		if ( ! empty( $_POST['mmm_equipment_new'] ) ) {

			$new = sanitize_text_field( wp_unslash( $_POST['mmm_equipment_new'] ) );

			if ( '' !== $new ) {
				$picked[] = $this->find_or_create_equipment( $new );
			}
		}

		$recipe = new Mixmymeals_Recipe( $post_id );
		$recipe->set_equipment( $picked );
	}

	/**
	 * The piece of equipment with that name, creating it when there is none.
	 *
	 * Find-or-create rather than create, as the author term does: two people
	 * typing "Springform" a week apart should not end up with two springform
	 * tins, each on half the recipes.
	 *
	 * @param string $title Name typed into the empty row.
	 * @return int Equipment ID, 0 when it could not be created.
	 */
	private function find_or_create_equipment( string $title ): int {

		$found = get_posts(
			array(
				'post_type'        => 'mmm_equipment',
				'post_status'      => 'any',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'title'            => $title,
				'suppress_filters' => false,
			)
		);

		if ( $found ) {
			return (int) $found[0];
		}

		// create() throws, and this runs on save_post: an uncaught exception
		// would replace the editor with a blank page and lose the save.
		try {
			return Mixmymeals_Equipment::create( $title )->get_id();
		} catch ( \Exception $e ) {
			unset( $e );
			return 0;
		}
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
	 * Rendered size of the author's profile picture in pixels. The value is the
	 * one WordPress avatars use, so the theme's existing avatar rules fit the
	 * box without a second set of sizes — the box itself no longer asks
	 * get_avatar() for anything.
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

		/*
		 * Picking beats typing: the author is a term now, so a re-typed name is
		 * not the same person but a second one. The select is therefore the
		 * primary control and the name field only exists for creating someone
		 * who is not in the list yet — while an existing author is selected it
		 * is read-only, because a changed name there is far more likely to mean
		 * "credit somebody else" than "rename this person". Renaming belongs on
		 * the term screen, which the link next to the select opens.
		 */
		$current_term = Mixmymeals_Author::get_post_term( $post->ID );
		$current_id   = $current_term ? (int) $current_term->term_id : 0;

		$authors = get_terms(
			array(
				'taxonomy'   => Mixmymeals_Author::TAXONOMY,
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);

		$authors = is_wp_error( $authors ) ? array() : $authors;

		// Every author's fields travel to the browser so switching the select
		// fills the form immediately instead of needing a save first.
		$records = array();

		foreach ( $authors as $author ) {
			$record = Mixmymeals_Author::get_term_data( (int) $author->term_id );

			$record['image_url'] = $record['image']
				? (string) wp_get_attachment_image_url( $record['image'], 'thumbnail' )
				: '';
			$record['edit_url']  = (string) get_edit_term_link( (int) $author->term_id, Mixmymeals_Author::TAXONOMY );

			$records[ (int) $author->term_id ] = $record;
		}

		echo '<table class="form-table mixmymeals-meta-table"><tbody>';

		echo '<tr><th scope="row"><label for="mmm_author_term">'
			. esc_html__( 'Author', 'mixmymeals' ) . '</label></th><td>';

		echo '<select id="mmm_author_term" name="mmm_author_term">';
		echo '<option value="0"' . selected( $current_id, 0, false ) . '>'
			. esc_html__( '— WordPress author —', 'mixmymeals' ) . '</option>';

		foreach ( $authors as $author ) {
			echo '<option value="' . esc_attr( (string) $author->term_id ) . '"'
				. selected( $current_id, (int) $author->term_id, false ) . '>'
				. esc_html( $author->name ) . '</option>';
		}

		echo '<option value="-1">' . esc_html__( '— New author —', 'mixmymeals' ) . '</option>';
		echo '</select> ';

		echo '<a href="' . esc_url( (string) get_edit_term_link( $current_id, Mixmymeals_Author::TAXONOMY ) )
			. '" class="mmm-author-edit-link"' . ( $current_id ? '' : ' style="display:none;"' ) . '>'
			. esc_html__( 'Edit this author', 'mixmymeals' ) . '</a>';

		echo '<p class="description">'
			. esc_html__( 'The fields below belong to the author, not to this post — changing them updates every recipe and plan they are credited on.', 'mixmymeals' )
			. '</p></td></tr>';

		echo '<tr><th scope="row"><label for="mmm_author_name">'
			. esc_html__( 'Author Name', 'mixmymeals' ) . '</label></th>'
			. '<td><input type="text" class="regular-text" id="mmm_author_name" name="mmm_author_name" value="'
			. esc_attr( self::get_author_name( $post->ID ) ) . '"'
			. ( $current_id ? ' readonly="readonly"' : '' ) . ' />'
			. '<p class="description">'
			. esc_html__( 'Only editable for a new author. Rename an existing one on their own screen.', 'mixmymeals' )
			. '</p></td></tr>';

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

		/*
		 * Inline rather than in admin.js: this behaviour is meaningless outside
		 * this one box, and admin.js ships as a .js/.min.js pair that would have
		 * to be kept in sync for it.
		 */
		?>
		<script>
		( function () {
			var records = <?php echo wp_json_encode( (object) $records ); ?>;
			var select  = document.getElementById( 'mmm_author_term' );

			if ( ! select ) {
				return;
			}

			var name    = document.getElementById( 'mmm_author_name' );
			var link    = document.querySelector( '.mmm-author-edit-link' );
			var rows    = select.closest( 'tbody' ).querySelectorAll( 'tr' );
			var preview = document.querySelector( '.mmm-author-image-preview' );

			function field( id ) {
				return document.getElementById( id );
			}

			function fill( record ) {
				var text = field( 'mmm_author_text' );

				// The bio may have been upgraded to TinyMCE; writing only the
				// textarea would leave the visible editor showing the previous
				// author's text.
				if ( text ) {
					text.value = record.text || '';

					if ( window.tinymce ) {
						var editor = window.tinymce.get( 'mmm_author_text' );

						if ( editor ) {
							editor.setContent( record.text || '' );
						}
					}
				}

				field( 'mmm_author_url' ).value       = record.url || '';
				field( 'mmm_author_link_text' ).value = record.link_text || '';
				field( 'mmm_author_social' ).value    = ( record.social || [] ).join( '\n' );
				field( 'mmm_author_nofollow' ).checked = !! record.nofollow;
				field( 'mmm_author_image' ).value     = record.image || '';

				if ( preview ) {
					preview.src = record.image_url || '';
					preview.style.display = record.image_url ? '' : 'none';
				}

				var remove = document.querySelector( '.mmm-author-image-remove' );

				if ( remove ) {
					remove.style.display = record.image_url ? '' : 'none';
				}
			}

			var empty = { text: '', url: '', link_text: '', social: [], nofollow: false, image: '', image_url: '' };

			select.addEventListener( 'change', function () {
				var value  = select.value;
				var chosen = records[ value ];

				// "WordPress author" hides everything: there is no external
				// author to describe, so the fields would only invite input
				// that is then thrown away on save.
				rows.forEach( function ( row, index ) {
					if ( index > 0 ) {
						row.style.display = '0' === value ? 'none' : '';
					}
				} );

				if ( link ) {
					link.style.display = chosen ? '' : 'none';

					if ( chosen ) {
						link.href = chosen.edit_url;
					}
				}

				if ( '0' === value ) {
					name.value = '';
					name.readOnly = false;
					fill( empty );
					return;
				}

				if ( chosen ) {
					name.value = select.options[ select.selectedIndex ].text;
					name.readOnly = true;
					fill( chosen );
					return;
				}

				// New author.
				name.value = '';
				name.readOnly = false;
				name.focus();
				fill( empty );
			} );

			if ( '0' === select.value ) {
				rows.forEach( function ( row, index ) {
					if ( index > 0 ) {
						row.style.display = 'none';
					}
				} );
			}
		}() );
		</script>
		<?php
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

		/*
		 * Sanitised here as well as in the save methods further down. The
		 * duplication is deliberate: these functions are idempotent, and a
		 * reader (or a static analyser) should not have to follow two more
		 * calls to establish that nothing raw enters the database.
		 */
		$fields = array(
			'text'      => wp_kses_post( wp_unslash( $_POST['mmm_author_text'] ?? '' ) ),
			'url'       => esc_url_raw( wp_unslash( $_POST['mmm_author_url'] ?? '' ) ),
			'link_text' => sanitize_text_field( wp_unslash( $_POST['mmm_author_link_text'] ?? '' ) ),
			'social'    => sanitize_textarea_field( wp_unslash( $_POST['mmm_author_social'] ?? '' ) ),
			'image'     => absint( $_POST['mmm_author_image'] ?? 0 ),
			'nofollow'  => ! empty( $_POST['mmm_author_nofollow'] ),
		);

		$choice = isset( $_POST['mmm_author_term'] ) ? (int) $_POST['mmm_author_term'] : 0;

		/*
		 * An existing author is addressed by term, never by the name field: it
		 * is read-only in that case, and honouring a tampered value would
		 * quietly create a second person instead of failing.
		 */
		if ( $choice > 0 && term_exists( $choice, Mixmymeals_Author::TAXONOMY ) ) {
			self::clear_legacy_author_meta( $post_id );
			Mixmymeals_Author::save_term_data( $choice, $fields );
			Mixmymeals_Author::assign( $post_id, $choice );
			// phpcs:enable WordPress.Security.NonceVerification.Missing
			return;
		}

		// "New author", or the credit being removed — both are decided by the
		// name, which is what save_author_data() keys off.
		$fields['name'] = 0 === $choice
			? ''
			: sanitize_text_field( wp_unslash( $_POST['mmm_author_name'] ?? '' ) );

		self::save_author_data( $post_id, $fields );
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
			// The URL field needs esc_url_raw, the three text fields plain text;
			// save_image_credit() applies the same again, harmlessly.
			$data[ $key ] = 'url' === $key
				? esc_url_raw( wp_unslash( $_POST[ 'mmm_image_credit_url' ] ?? '' ) )
				: sanitize_text_field( wp_unslash( $_POST[ 'mmm_image_credit_' . $key ] ?? '' ) );
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
				'type'    => sanitize_key( wp_unslash( $_POST['mmm_disclosure_type'] ?? '' ) ),
				'sponsor' => sanitize_text_field( wp_unslash( $_POST['mmm_disclosure_sponsor'] ?? '' ) ),
			)
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * The stored disclosure type, or '' for editorial content. An unknown value
	 * reads as editorial rather than being trusted through — the type decides
	 * whether a disclosure label is printed at all, so it must never be a
	 * value the rendering side does not know.
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
	 * Whether the post is to show a visible disclosure label. **Every feature
	 * that renders a recipe or plan publicly must honour this** — for
	 * commercial content the label is the point of the setting, not a display
	 * preference to weigh against the layout.
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
			'post__not_in'   => [ $post->ID ], // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- excludes the single current post, not a growing list.
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
