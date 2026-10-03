<?php
/**
 * Importer: admin tools for importing ingredients and recipes from JSON.
 *
 * Integrated from the former standalone "MixMyMeals — Importer" plugin.
 *
 * @package MixMyMeals/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared UI helpers used by both importer pages.
 */
class Mmm_Importer_UI {

	private static bool $assets_printed = false;

	/**
	 * Prints the one script this screen needs.
	 *
	 * The styles used to sit beside it in an inline <style> block. They live in
	 * assets/css/admin.css now — admin CSS in two places is how the two end up
	 * disagreeing, and that stylesheet is loaded on every admin screen anyway.
	 *
	 * @return void
	 */
	public static function print_assets(): void {
		if ( self::$assets_printed ) {
			return;
		}
		self::$assets_printed = true;
		?>
		<script>
			document.addEventListener( 'DOMContentLoaded', function () {
				document.querySelectorAll( '.mmm-copy-btn' ).forEach( function ( btn ) {
					btn.addEventListener( 'click', function () {
						var ta = document.getElementById( btn.dataset.target );
						if ( ! ta ) { return; }
						navigator.clipboard.writeText( ta.value ).then( function () {
							var orig = btn.textContent;
							btn.textContent = '✓';
							setTimeout( function () { btn.textContent = orig; }, 1500 );
						} );
					} );
				} );
			} );
		</script>
		<?php
	}

	public static function notice( string $message, array $errors = array() ): void {
		$type = $errors ? 'warning' : 'success';
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>'
			. esc_html( $message ) . '</p>';
		if ( $errors ) {
			echo '<ul>';
			foreach ( $errors as $err ) {
				echo '<li>' . esc_html( $err ) . '</li>';
			}
			echo '</ul>';
		}
		echo '</div>';
	}

	public static function card_open( string $title ): void {
		echo '<div class="card mmm-card"><h2>' . esc_html( $title ) . '</h2>';
	}

	public static function card_close(): void {
		echo '</div>';
	}

	public static function copyable_textarea( string $id, string $value, int $rows = 20 ): void {
		echo '<div class="mmm-textarea-wrap">';
		echo '<button type="button" class="button mmm-copy-btn" data-target="' . esc_attr( $id ) . '">'
			. esc_html__( 'Copy', 'mixmymeals' ) . '</button>';
		echo '<textarea id="' . esc_attr( $id ) . '" rows="' . (int) $rows . '" readonly>'
			. esc_textarea( $value ) . '</textarea>';
		echo '</div>';
	}

	/**
	 * The import screen's sections, in the order they were registered.
	 *
	 * @var array<string, array{label: string, callback: callable}>
	 */
	private static array $tabs = array();

	/**
	 * The page slug. Both importers used to have one of their own, hanging in
	 * two different menus under the same word "Import".
	 */
	public const PAGE_SLUG = 'mixmymeals_import';

	/**
	 * Registers one section of the import screen.
	 *
	 * Each importer calls this for itself, so the screen knows nothing about
	 * what can be imported and a third kind would not have to be added here.
	 *
	 * @param string   $slug     Tab slug, used in the URL.
	 * @param string   $label    Tab label.
	 * @param callable $callback Prints the section's body.
	 * @return void
	 */
	public static function add_tab( string $slug, string $label, callable $callback ): void {
		self::$tabs[ $slug ] = array(
			'label'    => $label,
			'callback' => $callback,
		);
	}

	/**
	 * Hangs the one import page under the MixMyMeals menu.
	 *
	 * Attached once per request from Mixmymeals::add_importer(), after both
	 * importers have registered their section — the screen is drawn from
	 * what they registered, so it has to come second.
	 *
	 * @return void
	 */
	public static function register_menu(): void {
		add_submenu_page(
			'mixmymeals_settings',
			__( 'Import', 'mixmymeals' ),
			__( 'Import', 'mixmymeals' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' ),
			20
		);
	}

	/**
	 * Which section is open.
	 *
	 * Falls back to the first registered one, so a stale bookmark or a typed
	 * `tab` lands on a working screen rather than on an empty page.
	 *
	 * @return string
	 */
	private static function current_tab(): string {

		$slugs = array_keys( self::$tabs );

		if ( ! $slugs ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab switch.
		$wanted = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

		return in_array( $wanted, $slugs, true ) ? $wanted : $slugs[0];
	}

	/**
	 * Draws the import screen: one heading, one tab row, one section.
	 *
	 * @return void
	 */
	public static function render_page(): void {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No permission.', 'mixmymeals' ) );
		}

		$current = self::current_tab();

		if ( '' === $current ) {
			return;
		}

		self::print_assets();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'MixMyMeals — Import', 'mixmymeals' ) . '</h1>';

		echo '<nav class="nav-tab-wrapper">';

		foreach ( self::$tabs as $slug => $tab ) {
			printf(
				'<a href="%1$s" class="nav-tab%2$s">%3$s</a>',
				esc_url( add_query_arg( array( 'page' => self::PAGE_SLUG, 'tab' => $slug ), admin_url( 'admin.php' ) ) ),
				$slug === $current ? ' nav-tab-active' : '',
				esc_html( $tab['label'] )
			);
		}

		echo '</nav>';

		call_user_func( self::$tabs[ $current ]['callback'] );

		echo '</div>';
	}
}

/**
 * Ingredient Importer
 */
class Mixmymeals_Ingredient_Importer {

	public function __construct() {
		Mmm_Importer_UI::add_tab(
			'ingredients',
			__( 'Ingredients', 'mixmymeals' ),
			array( $this, 'render_section' )
		);
		add_action( 'admin_post_mixmymeals_import_ingredients', array( $this, 'handle_import' ) );
	}

	/**
	 * The ingredient section of the shared import screen.
	 *
	 * Heading, tab row and permission check belong to Mmm_Importer_UI now —
	 * this prints the body only. It used to be a page of its own, hanging in
	 * the Ingredients menu beside a second one called "Import" under Recipes.
	 *
	 * @return void
	 */
	public function render_section(): void {

		$result = get_transient( 'mmm_ingredient_import_result_' . get_current_user_id() );
		if ( $result ) {
			delete_transient( 'mmm_ingredient_import_result_' . get_current_user_id() );
		}
		?>
			<?php
			if ( $result ) :
				$msg = sprintf(
					/* translators: %d: number of imported ingredients. */
					_n( '%d ingredient imported.', '%d ingredients imported.', (int) $result['imported'], 'mixmymeals' ),
					(int) $result['imported']
				);
				Mmm_Importer_UI::notice( $msg );
			endif;
			?>

			<?php Mmm_Importer_UI::card_open( __( 'Import', 'mixmymeals' ) ); ?>
			<div class="mmm-import-form">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="mixmymeals_import_ingredients">
					<?php wp_nonce_field( 'mixmymeals_import_nonce', 'mixmymeals_import_nonce_field' ); ?>
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="mmm_json_text"><?php esc_html_e( 'JSON Data', 'mixmymeals' ); ?></label>
							</th>
							<td>
								<textarea name="json_text" id="mmm_json_text" rows="20" required></textarea>
							</td>
						</tr>
					</table>
					<p>
						<input type="submit" class="button button-primary"
							value="<?php esc_attr_e( 'Import Ingredients', 'mixmymeals' ); ?>">
					</p>
				</form>
			</div>
			<?php Mmm_Importer_UI::card_close(); ?>

			<?php Mmm_Importer_UI::card_open( __( 'AI Prompt', 'mixmymeals' ) ); ?>
			<p><?php esc_html_e( 'Use this prompt together with an image or URL to generate importable JSON.', 'mixmymeals' ); ?></p>
			<?php Mmm_Importer_UI::copyable_textarea( 'mmm-prompt-ingr', $this->get_prompt(), 16 ); ?>
			<?php Mmm_Importer_UI::card_close(); ?>

			<?php Mmm_Importer_UI::card_open( __( 'Example JSON', 'mixmymeals' ) ); ?>
			<?php Mmm_Importer_UI::copyable_textarea( 'mmm-example-ingr', $this->get_example_json(), 22 ); ?>
			<?php Mmm_Importer_UI::card_close(); ?>

			<?php Mmm_Importer_UI::card_open( __( 'Completion Prompt', 'mixmymeals' ) ); ?>
			<p><?php esc_html_e( 'Copy this prompt and give it to the AI. It contains all existing ingredients and a complete example. The AI will fill in all missing fields. Re-import the result.', 'mixmymeals' ); ?></p>
			<?php Mmm_Importer_UI::copyable_textarea( 'mmm-completion-prompt', $this->get_completion_prompt(), 22 ); ?>
			<?php Mmm_Importer_UI::card_close(); ?>
		<?php
	}

	private function get_example_json(): string {
		return wp_json_encode(
			array(
				array(
					'title'          => 'Tomato',
					'nutrition'      => array(
						'calories_100' => 18,
						'fat'          => 0.2,
						'carbs'        => 3.9,
						'protein'      => 0.9,
						'sugar'        => 2.6,
						'fiber'        => 1.2,
						'salt'         => 0.01,
					),
					'price_per_100g' => 0.35,
					'units'          => array(
						array(
							'title'  => 'piece',
							'weight' => 80,
						),
						array(
							'title'  => 'pack',
							'weight' => 500,
						),
					),
				),
			),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
		);
	}

	private function get_completion_prompt(): string {
		$lines = array(
			__( 'The following JSON contains all ingredients of the database. Some fields are null or missing. Fill in all null values with realistic data (German supermarket context). Leave existing non-null values unchanged. Use the complete example below as a reference for the expected format and field meanings. Output only the complete JSON array, no additional text.', 'mixmymeals' ),
			'',
			__( 'Complete example:', 'mixmymeals' ),
			$this->get_example_json(),
			'',
			__( 'Ingredients to complete:', 'mixmymeals' ),
			$this->get_export_json(),
		);

		return implode( "\n", $lines );
	}

	private function get_export_json(): string {
		$posts = get_posts(
			array(
				'post_type'      => 'mmm_ingredient',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$result = array();
		foreach ( $posts as $post ) {
			$ingredient = new Mixmymeals_Ingredient( $post->ID );
			$nutrition  = $ingredient->get_nutrition();
			// Own, not resolved: re-importing this file writes the field back,
			// and a product's price must not turn into the ingredient's.
			$price      = $ingredient->get_own_price_per_100g();

			$unit_list = array();
			foreach ( $ingredient->get_units() as $unit_post ) {
				$amount      = new Mixmymeals_Ingredient_Amount( $unit_post->ID );
				$unit_list[] = array(
					'id'     => $unit_post->ID,
					'title'  => $amount->get_title(),
					'weight' => $amount->get_weight(),
				);
			}

			$result[] = array(
				'id'             => $post->ID,
				'title'          => $post->post_title,
				'nutrition'      => array(
					'calories_100' => (float) ( $nutrition['calories_100'] ?? 0 ),
					'fat'          => (float) ( $nutrition['fat'] ?? 0 ),
					'carbs'        => (float) ( $nutrition['carbs'] ?? 0 ),
					'protein'      => (float) ( $nutrition['protein'] ?? 0 ),
					'sugar'        => (float) ( $nutrition['sugar'] ?? 0 ),
					'fiber'        => (float) ( $nutrition['fiber'] ?? 0 ),
					'salt'         => (float) ( $nutrition['salt'] ?? 0 ),
				),
				'price_per_100g' => $price > 0 ? $price : null,
				'units'          => $unit_list,
			);
		}

		return wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
	}

	private function get_prompt(): string {
		$lines = array(
			__( 'Create valid JSON for the MixMyMeals ingredient importer for the ingredients listed below.', 'mixmymeals' ),
			'',
			__( 'Rules:', 'mixmymeals' ),
			'- ' . __( 'One object per ingredient', 'mixmymeals' ),
			'- ' . __( 'Include nutritional values per 100g if available: calories, fat, carbs, protein, sugar, fiber, salt — use 0 if unknown', 'mixmymeals' ),
			'- ' . __( 'Include price_per_100g if a typical retail price is known; omit the field if unknown', 'mixmymeals' ),
			'- ' . __( 'List all common units with their weight in grams (e.g. piece, package, can)', 'mixmymeals' ),
			'- ' . __( 'Output: only the JSON array, no additional text', 'mixmymeals' ),
			'',
			__( 'Format:', 'mixmymeals' ),
			$this->get_example_json(),
		);

		return implode( "\n", $lines );
	}

	public function handle_import(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No permission.', 'mixmymeals' ) );
		}
		if ( ! isset( $_POST['mixmymeals_import_nonce_field'] ) ||
			! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['mixmymeals_import_nonce_field'] ) ), 'mixmymeals_import_nonce' ) ) {
			wp_die( esc_html__( 'Invalid request.', 'mixmymeals' ) );
		}
		if ( empty( $_POST['json_text'] ) ) {
			wp_die( esc_html__( 'No JSON data entered.', 'mixmymeals' ) );
		}

		/*
		 * Not run through a sanitiser: this is a JSON document, and
		 * sanitize_text_field() would strip the very characters that make it
		 * parseable. json_decode() rejects anything malformed, and every field
		 * taken out of the result below goes through its own filter.
		 */
		$json = wp_unslash( $_POST['json_text'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON payload, validated by json_decode() and sanitised field by field below.
		$data = json_decode( $json, true );

		if ( ! is_array( $data ) ) {
			wp_die( esc_html__( 'Invalid JSON format.', 'mixmymeals' ) );
		}

		$count = 0;

		foreach ( $data as $entry ) {
			$title = sanitize_text_field( $entry['title'] ?? '' );
			if ( empty( $title ) ) {
				continue;
			}

			// Prefer provided ID over creating a new post.
			$post_id = 0;
			if ( ! empty( $entry['id'] ) ) {
				$candidate = (int) $entry['id'];
				if ( get_post_type( $candidate ) === 'mmm_ingredient' ) {
					$post_id = $candidate;
				}
			}

			if ( ! $post_id ) {
				$post_id = wp_insert_post(
					array(
						'post_title'  => $title,
						'post_type'   => 'mmm_ingredient',
						'post_status' => 'publish',
					)
				);
			}

			if ( ! $post_id || is_wp_error( $post_id ) ) {
				continue;
			}

			$ingredient = new Mixmymeals_Ingredient( $post_id );

			if ( isset( $entry['nutrition'] ) && is_array( $entry['nutrition'] ) ) {
				$ingredient->set_nutrition( $entry['nutrition'] );
			}

			if ( isset( $entry['price_per_100g'] ) ) {
				$ingredient->set_price_per_100g( floatval( $entry['price_per_100g'] ) );
			}

			if ( isset( $entry['units'] ) && is_array( $entry['units'] ) ) {
				foreach ( $entry['units'] as $unit ) {
					$unit_title  = sanitize_text_field( $unit['title'] ?? '' );
					$unit_weight = floatval( $unit['weight'] ?? 0 );

					if ( empty( $unit_title ) || $unit_weight <= 0 ) {
						continue;
					}

					// Prefer provided unit ID if it belongs to this ingredient.
					$amount = null;
					if ( ! empty( $unit['id'] ) ) {
						$unit_id = (int) $unit['id'];
						if ( get_post_type( $unit_id ) === 'mmm_ingr_unit'
							&& (int) wp_get_post_parent_id( $unit_id ) === $post_id ) {
							$amount = new Mixmymeals_Ingredient_Amount( $unit_id );
						}
					}

					if ( ! $amount ) {
						$amount = Mixmymeals_Ingredient_Amount::create( $post_id, $unit_title );
					}

					$amount->set_weight( $unit_weight );
				}
			}

			++$count;
		}

		set_transient(
			'mmm_ingredient_import_result_' . get_current_user_id(),
			array(
				'imported' => $count,
			),
			60
		);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'mixmymeals_import_ingredients',
				),
				admin_url( 'edit.php?post_type=mmm_ingredient' )
			)
		);
		exit;
	}
}

/**
 * Recipe Importer
 */
class Mixmymeals_Recipe_Importer {

	public function __construct() {
		Mmm_Importer_UI::add_tab(
			'recipes',
			__( 'Recipes', 'mixmymeals' ),
			array( $this, 'render_section' )
		);
		add_action( 'admin_post_mixmymeals_import_recipes', array( $this, 'handle_import' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	public function register_rest_routes(): void {
		register_rest_route(
			'mixmymeals/v1',
			'/import-reference',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_reference' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function rest_reference(): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'ingredients' => $this->get_reference_data(),
				'categories'  => $this->get_categories_reference(),
				'tags'        => $this->get_tags_reference(),
				'equipment'   => $this->get_equipment_reference(),
			)
		);
	}

	/**
	 * The recipe section of the shared import screen.
	 *
	 * @return void
	 */
	public function render_section(): void {

		$result = get_transient( 'mmm_recipe_import_result_' . get_current_user_id() );
		if ( $result ) {
			delete_transient( 'mmm_recipe_import_result_' . get_current_user_id() );
		}
		?>
			<div class="notice notice-warning inline">
				<p><?php esc_html_e( 'Copyright notice: Only import recipes for which you hold the rights or that are explicitly licensed for reuse. Importing third-party recipes without permission may constitute a copyright infringement.', 'mixmymeals' ); ?></p>
			</div>

			<?php
			if ( $result ) :
				$msg = sprintf(
					/* translators: %d: number of imported recipes. */
					_n( '%d recipe imported (as draft).', '%d recipes imported (as draft).', (int) $result['imported'], 'mixmymeals' ),
					(int) $result['imported']
				);
				Mmm_Importer_UI::notice( $msg, $result['errors'] ?? array() );
			endif;
			?>

			<?php Mmm_Importer_UI::card_open( __( 'Import', 'mixmymeals' ) ); ?>
			<div class="mmm-import-form">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="mixmymeals_import_recipes">
					<?php wp_nonce_field( 'mixmymeals_import_recipes_nonce', 'mmm_recipes_nonce' ); ?>
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="mmm_recipe_json"><?php esc_html_e( 'JSON Data', 'mixmymeals' ); ?></label>
							</th>
							<td>
								<textarea name="json_text" id="mmm_recipe_json" rows="20" required></textarea>
							</td>
						</tr>
					</table>
					<p>
						<input type="submit" class="button button-primary"
							value="<?php esc_attr_e( 'Import Recipes', 'mixmymeals' ); ?>">
					</p>
				</form>
			</div>
			<?php Mmm_Importer_UI::card_close(); ?>

			<?php Mmm_Importer_UI::card_open( __( 'AI Prompt', 'mixmymeals' ) ); ?>
			<p><?php esc_html_e( 'Use this prompt together with an image or URL to generate importable JSON.', 'mixmymeals' ); ?></p>
			<?php Mmm_Importer_UI::copyable_textarea( 'mmm-prompt-recipe', $this->get_prompt(), 22 ); ?>
			<?php Mmm_Importer_UI::card_close(); ?>

			<?php Mmm_Importer_UI::card_open( __( 'Example JSON', 'mixmymeals' ) ); ?>
			<?php Mmm_Importer_UI::copyable_textarea( 'mmm-example-recipe', $this->get_example_json(), 24 ); ?>
			<?php Mmm_Importer_UI::card_close(); ?>

			<?php Mmm_Importer_UI::card_open( __( 'Reference URL', 'mixmymeals' ) ); ?>
			<p><?php esc_html_e( 'This URL returns all reference data as JSON.', 'mixmymeals' ); ?></p>
			<?php Mmm_Importer_UI::copyable_textarea( 'mmm-reference-url', $this->get_reference_url(), 1 ); ?>
			<?php Mmm_Importer_UI::card_close(); ?>
		<?php
	}

	private function get_reference_url(): string {
		return get_rest_url( null, 'mixmymeals/v1/import-reference' );
	}

	private function get_example_json(): string {
		return wp_json_encode(
			array(
				array(
					'title'         => 'Tomato salad',
					'servings'      => 2,
					'prep_time'     => 15,
					'category_ids'  => array( 5 ),
					'tag_ids'       => array( 8 ),
					'equipment_ids' => array( 3 ),
					'steps'        => array(
						array(
							'title'       => 'Preparation',
							'description' => 'Wash and slice the tomatoes.',
							'ingredients' => array(
								array(
									'ingredient_id' => 42,
									'unit_id'       => 17,
									'amount'        => 3,
								),
								array(
									'ingredient_id' => 55,
									'unit_id'       => 0,
									'amount'        => 2,
									'optional'      => true,
								),
							),
						),
					),
				),
			),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
		);
	}

	private function get_prompt(): string {
		$lines = array(
			__( 'Extract the recipe from the attached image, linked page, or description and create valid JSON for the MixMyMeals recipe importer.', 'mixmymeals' ),
			'',
			__( 'Fetch the reference data (ingredients, categories, tags, equipment with their IDs) from:', 'mixmymeals' ),
			$this->get_reference_url(),
			'',
			__( 'Rules:', 'mixmymeals' ),
			'- ' . __( 'Use only ingredient_id and unit_id from the ingredients reference', 'mixmymeals' ),
			'- ' . __( 'If an ingredient is missing: omit it from the JSON and list it after the JSON under "Missing ingredients:"', 'mixmymeals' ),
			'- ' . __( 'If no matching unit exists: unit_id = 0', 'mixmymeals' ),
			'- ' . __( 'Mark an ingredient as optional with "optional": true (e.g. garnishes or "to taste" items); omit the field or use false for required ingredients', 'mixmymeals' ),
			'- ' . __( 'Use only category_ids and tag_ids from the references; omit if nothing matches', 'mixmymeals' ),
			'- ' . __( 'Use only equipment_ids from the equipment reference for special equipment the recipe requires; omit if nothing matches', 'mixmymeals' ),
			'- ' . __( 'Take servings and prep time from the source; estimate if not stated', 'mixmymeals' ),
			'- ' . __( 'Group steps sensibly, write descriptions in German', 'mixmymeals' ),
			'- ' . __( 'Always rephrase the recipe in your own words — never copy the source text verbatim. Vary wording, phrasing and step grouping (and you may slightly adapt seasoning or amounts) so the result is an independent adaptation, not a copy.', 'mixmymeals' ),
			'- ' . __( 'Write all texts in natural German and match the tone, wording and level of detail of the existing recipes under "Style reference" below.', 'mixmymeals' ),
			'- ' . __( 'Output: first the JSON array, then (if needed) missing ingredients — no other text', 'mixmymeals' ),
		);

		$samples = $this->get_recipe_style_samples();
		if ( ! empty( $samples ) ) {
			$lines[] = '';
			$lines[] = __( 'Style reference (existing recipes — match their tone and wording, do not reuse their content):', 'mixmymeals' );
			foreach ( $samples as $sample ) {
				$lines[] = $sample;
			}
		}

		$lines[] = '';
		$lines[] = __( 'Format:', 'mixmymeals' );
		$lines[] = $this->get_example_json();

		return implode( "\n", $lines );
	}

	/**
	 * Returns a few short step-description samples from existing published
	 * recipes, used in the prompt as a writing-style reference. Empty when there
	 * are no recipes yet.
	 *
	 * @param int $recipes Max number of recipes to sample.
	 * @return string[] One line per recipe ("Title: step text …").
	 */
	private function get_recipe_style_samples( int $recipes = 4 ): array {

		$ids = get_posts(
			array(
				'post_type'      => 'mmm_recipe',
				'post_status'    => 'publish',
				'posts_per_page' => $recipes,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		$samples = array();

		foreach ( $ids as $id ) {

			if ( ! class_exists( 'Mixmymeals_Recipe' ) ) {
				break;
			}

			$recipe = new Mixmymeals_Recipe( (int) $id );
			if ( ! $recipe->is_valid() ) {
				continue;
			}

			$texts = array();
			foreach ( $recipe->get_steps() as $step ) {
				$desc = isset( $step['description'] ) ? trim( wp_strip_all_tags( (string) $step['description'] ) ) : '';
				if ( '' !== $desc ) {
					$texts[] = $desc;
				}
				if ( count( $texts ) >= 2 ) {
					break; // A couple of steps are enough to convey the style.
				}
			}

			if ( empty( $texts ) ) {
				continue;
			}

			$sample = get_the_title( (int) $id ) . ': ' . implode( ' ', $texts );
			$sample = trim( preg_replace( '/\s+/', ' ', $sample ) );

			if ( function_exists( 'mb_substr' ) && mb_strlen( $sample ) > 300 ) {
				$sample = mb_substr( $sample, 0, 300 ) . '…';
			}

			$samples[] = '• ' . $sample;
		}

		return $samples;
	}

	private function get_categories_reference(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => 'mmm_recipe_category',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		return array_map(
			fn( $t ) => array(
				'id'   => $t->term_id,
				'name' => $t->name,
				'slug' => $t->slug,
			),
			$terms
		);
	}

	private function get_tags_reference(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => 'mmm_recipe_tag',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		return array_map(
			fn( $t ) => array(
				'id'   => $t->term_id,
				'name' => $t->name,
			),
			$terms
		);
	}

	private function get_equipment_reference(): array {
		return array_map(
			fn( $p ) => array(
				'id'   => $p->ID,
				'name' => get_the_title( $p ),
			),
			Mixmymeals_Equipment::get_all()
		);
	}

	private function get_reference_data(): array {
		$ingredients = get_posts(
			array(
				'post_type'              => 'mmm_ingredient',
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'update_post_term_cache' => false,
			)
		);

		$result = array();
		foreach ( $ingredients as $ingredient ) {
			$units = get_posts(
				array(
					'post_type'              => 'mmm_ingr_unit',
					'post_parent'            => $ingredient->ID,
					'post_status'            => 'publish',
					'posts_per_page'         => -1,
					'orderby'                => 'title',
					'order'                  => 'ASC',
					'update_post_term_cache' => false,
				)
			);

			$unit_list = array();
			foreach ( $units as $unit ) {
				$unit_list[] = array(
					'id'       => $unit->ID,
					'title'    => $unit->post_title,
					'weight_g' => (float) get_post_meta( $unit->ID, '_mmm_ingr_unit_weight', true ),
				);
			}

			$result[] = array(
				'id'    => $ingredient->ID,
				'title' => $ingredient->post_title,
				'units' => $unit_list,
			);
		}

		return $result;
	}

	public function handle_import(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No permission.', 'mixmymeals' ) );
		}
		if ( ! isset( $_POST['mmm_recipes_nonce'] ) ||
			! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['mmm_recipes_nonce'] ) ), 'mixmymeals_import_recipes_nonce' ) ) {
			wp_die( esc_html__( 'Invalid request.', 'mixmymeals' ) );
		}
		if ( empty( $_POST['json_text'] ) ) {
			wp_die( esc_html__( 'No JSON data entered.', 'mixmymeals' ) );
		}

		/*
		 * Not run through a sanitiser: this is a JSON document, and
		 * sanitize_text_field() would strip the very characters that make it
		 * parseable. json_decode() rejects anything malformed, and every field
		 * taken out of the result below goes through its own filter.
		 */
		$json = wp_unslash( $_POST['json_text'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON payload, validated by json_decode() and sanitised field by field below.
		$data = json_decode( $json, true );

		if ( ! is_array( $data ) || JSON_ERROR_NONE !== json_last_error() ) {
			wp_die( esc_html__( 'Invalid JSON format.', 'mixmymeals' ) );
		}

		$imported = 0;
		$errors   = array();

		foreach ( $data as $index => $recipe_data ) {
			if ( ! is_array( $recipe_data ) ) {
				/* translators: %d: position of the entry in the JSON array. */
				$errors[] = sprintf( __( 'Entry %d is not an object.', 'mixmymeals' ), $index + 1 );
				continue;
			}
			$result = $this->import_single_recipe( $recipe_data, $index );
			if ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_message();
			} else {
				++$imported;
			}
		}

		set_transient(
			'mmm_recipe_import_result_' . get_current_user_id(),
			array(
				'imported' => $imported,
				'errors'   => $errors,
			),
			60
		);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'mixmymeals_import_recipes',
				),
				admin_url( 'edit.php?post_type=mmm_recipe' )
			)
		);
		exit;
	}

	private function import_single_recipe( array $recipe_data, int $index ): int|WP_Error {
		$title = sanitize_text_field( $recipe_data['title'] ?? '' );
		if ( empty( $title ) ) {
			return new WP_Error(
				'missing_title',
				/* translators: %d: position of the entry in the JSON array. */
				sprintf( __( 'Entry %d: No title given.', 'mixmymeals' ), $index + 1 )
			);
		}

		$servings  = max( 1, (int) ( $recipe_data['servings'] ?? 1 ) );
		$prep_time = max( 0, (int) ( $recipe_data['prep_time'] ?? 0 ) );

		$post_id = wp_insert_post(
			array(
				'post_title'  => $title,
				'post_type'   => 'mmm_recipe',
				'post_status' => 'draft',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$recipe = new Mixmymeals_Recipe( $post_id );
		$recipe->set_servings( $servings );
		$recipe->set_preparation_time( $prep_time );

		foreach ( array(
			'category_ids' => 'mmm_recipe_category',
			'tag_ids'      => 'mmm_recipe_tag',
		) as $field => $taxonomy ) {
			if ( empty( $recipe_data[ $field ] ) || ! is_array( $recipe_data[ $field ] ) ) {
				continue;
			}
			$valid_ids = array();
			foreach ( $recipe_data[ $field ] as $raw_id ) {
				$term_id = (int) $raw_id;
				if ( ! $term_id ) {
					continue;
				}
				$term = get_term( $term_id, $taxonomy );
				if ( $term && ! is_wp_error( $term ) ) {
					$valid_ids[] = $term_id;
				}
			}
			if ( $valid_ids ) {
				wp_set_post_terms( $post_id, $valid_ids, $taxonomy );
			}
		}

		if ( ! empty( $recipe_data['equipment_ids'] ) && is_array( $recipe_data['equipment_ids'] ) ) {
			$valid_equipment = array();
			foreach ( $recipe_data['equipment_ids'] as $raw_id ) {
				$equipment_id = (int) $raw_id;
				if ( $equipment_id && Mixmymeals_Equipment::exists_by_id( $equipment_id ) ) {
					$valid_equipment[] = $equipment_id;
				}
			}
			if ( $valid_equipment ) {
				$recipe->set_equipment( $valid_equipment );
			}
		}

		$steps       = array();
		$step_errors = array();
		$step_index  = 0;

		if ( ! empty( $recipe_data['steps'] ) && is_array( $recipe_data['steps'] ) ) {
			foreach ( $recipe_data['steps'] as $step_data ) {
				++$step_index;
				if ( ! is_array( $step_data ) ) {
					continue;
				}

				$ingredients = array();
				if ( ! empty( $step_data['ingredients'] ) && is_array( $step_data['ingredients'] ) ) {
					foreach ( $step_data['ingredients'] as $ingr ) {
						if ( ! is_array( $ingr ) ) {
							continue;
						}
						$resolved = $this->resolve_ingredient( $ingr );
						if ( null === $resolved ) {
							$step_errors[] = sprintf(
								/* translators: 1: recipe title, 2: step number, 3: ingredient ID. */
								__( '"%1$s" step %2$d: ingredient_id %3$d not found.', 'mixmymeals' ),
								$title,
								$step_index,
								(int) ( $ingr['ingredient_id'] ?? 0 )
							);
						} else {
							$ingredients[] = $resolved;
						}
					}
				}

				$steps[] = array(
					'title'       => sanitize_text_field( $step_data['title'] ?? '' ),
					'description' => wp_kses_post( $step_data['description'] ?? '' ),
					'image'       => '',
					'ingredients' => $ingredients,
				);
			}
		}

		$recipe->set_steps( $steps );

		if ( $step_errors ) {
			update_post_meta( $post_id, '_mmm_import_errors', $step_errors );
		}

		return $post_id;
	}

	private function resolve_ingredient( array $ingr ): ?array {

		$ingredient_id = (int) ( $ingr['ingredient_id'] ?? 0 );
		$unit_id       = (int) ( $ingr['unit_id'] ?? 0 );
		$amount        = (float) ( $ingr['amount'] ?? 0 );

		if ( ! $ingredient_id || $amount <= 0 ) {
			return null;
		}

		/*
		 * get_post() rather than a hand-written SELECT: it answers exactly the
		 * same question, goes through the object cache, and an importer run
		 * asks it once per ingredient of every recipe in the file.
		 */
		$ingredient_post = get_post( $ingredient_id );

		if ( ! $ingredient_post
			|| 'mmm_ingredient' !== $ingredient_post->post_type
			|| 'publish' !== $ingredient_post->post_status ) {
			return null;
		}

		if ( $unit_id ) {
			$unit_post = get_post( $unit_id );

			if ( ! $unit_post
				|| 'mmm_ingr_unit' !== $unit_post->post_type
				|| 'publish' !== $unit_post->post_status
				|| (int) $unit_post->post_parent !== $ingredient_id ) {
				$unit_id = 0;
			}
		}

		return array(
			'id'        => $ingredient_id,
			'amount'    => $amount,
			'amount_id' => $unit_id,
			'optional'  => ! empty( $ingr['optional'] ),
		);
	}
}
