<?php
/**
 * Post type Admin API file.
 *
 * Provides functionality for rendering and handling admin fields
 * related to custom post types.
 *
 * @package Mixmymeals/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mixmymeals_Admin_API
 *
 * Admin API class for handling custom post type fields and options.
 */
class Mixmymeals_Admin_API {

	/**
	 * Example property for demonstration.
	 *
	 * @var string
	 */
	protected string $example_property = '';

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
	}

	/**
	 * Renders or returns the HTML for a settings field.
	 *
	 * @param array       $data Field configuration array (with 'field', optional 'prefix').
	 * @param object|null $post Post object when used in a meta box context; null for options.
	 * @param bool        $echo Whether to echo the HTML (true) or return it (false).
	 * @return string|bool Generated HTML string when $echo is false, true after echoing.
	 */
	public function display_field(
		array $data = array(), ?object $post = null, bool $echo = true
	): string|bool {

		$field = $data['field'] ?? $data;

		$option_name = '';
		if ( isset( $data['prefix'] ) ) {
			$option_name = $data['prefix'];
		}

		$data        = '';
		$option_name .= $field['id'];
		if ( $post ) {
			$option = get_post_meta( $post->ID, $field['id'], true );
		} else {
			$option = get_option( $option_name );
		}

		if ( isset( $option ) ) {
			$data = $option;
		}

		if ( false === $data && isset( $field['default'] ) ) {
			$data = $field['default'];
		} elseif ( false === $data ) {
			$data = '';
		}

		$html = '';

		switch ( $field['id'] ) {

			case 'placeholder_image_id':
				$html .= '<div class="media-field-wrapper">';

				$html .= '<input type="hidden" name="' . esc_attr( $option_name ) . '" 
            id="' . esc_attr( $field['id'] ) . '" 
            value="' . esc_attr( $data ) . '" />';

				if ( $data ) {
					$image_url = wp_get_attachment_image_url( $data, 'thumbnail' );
					$html .= '<img src="' . esc_url( $image_url ) . '" 
                class="media-preview" 
                style="max-width:120px; display:block; margin-bottom:10px;" />';
				}

				$html .= '<button class="button select-media-button" 
            data-target="' . esc_attr( $field['id'] ) . '">' . esc_html( __( 'Select Image', 'mixmymeals' ) ) . '</button>';

				$html .= '<p class="description">' . esc_html( $field['description'] ) . '</p>';
				$html .= '</div>';
				break;

			case 'all_recipes_description':
			case 'all_ingredients_description':
			case 'all_meal_plans_description':
			case 'individual_meal_plans_description':
			case 'mail_footer':

				$editor_id    = esc_attr( $field['id'] );
				$editor_name  = esc_attr( $option_name );
				$editor_value = $data;

				$settings = [
					'textarea_name' => $editor_name,
					'textarea_rows' => 8,
					'media_buttons' => false,
					'teeny'         => true,
					'quicktags'     => true,
				];

				ob_start();
				wp_editor( $editor_value, $editor_id, $settings );
				$html .= ob_get_clean();

				$html .= '<p class="description">' . esc_html( $field['description'] ) . '</p>';

				break;

			case 'recipe_category_slug':
			case 'meal_plan_slug':
			case 'meal_plan_category_slug':
			case 'meal_slug':
			case 'recipe_slug':
			case 'currency':
			case 'individual_tab_label':
			case 'mail_from_name':
			case 'subscription_privacy_url':
				$html .= '<input id="' . esc_attr( $field['id'] )
				         . '" type="text" name="' . esc_attr( $option_name )
				         . '" placeholder="'
				         . esc_attr( $field['placeholder'] ) . '" value="'
				         . esc_attr( $data ) . '" /><p class="description">'
				         . esc_html( $field['description'] ) . '</p>';
				break;

			case 'recipe_disclaimer_text':
				$html .= '<textarea id="' . esc_attr( $field['id'] )
				         . '" rows="10" cols="60" name="'
				         . esc_attr( $option_name ) . '" placeholder="'
				         . esc_attr( $field['placeholder'] ) . '">'
				         . esc_html( $data )
				         . '</textarea>';
				break;

			case 'max_items_in_cart':
				$html .= '<input min="1" max="100" type="number" name="'
				         . esc_attr( $option_name ) . '" '
				         . 'id="' . esc_attr( $field['id'] )
				         . '" placeholder="'
				         . esc_attr( $field['placeholder'] ) . '" value="'
				         . esc_attr( $data ) . '" />'
				         . '<p class="description">'
				         . esc_html( $field['description'] )
				         . '</p>';
				break;

			case 'enable_cleanup':
			case 'cleanup_report_enabled':
			case 'mail_log_enabled':
			case 'subscriptions_enabled':
			case 'subscription_log_ip':
			case 'public_mode':
				$checked = '';
				if ( $data && 'on' === $data ) {
					$checked = 'checked="checked"';
				}
				$html .= '<label><input id="' . esc_attr( $field['id'] )
				         . '" type="checkbox" name="' . esc_attr( $option_name )
				         . '" ' . $checked . '/>'
				         . esc_html( $field['description'] )
				         . '</label>';
				break;

			case 'cleanup_min_age_days':
				$html .= '<input min="1" max="3650" type="number" name="'
				         . esc_attr( $option_name ) . '" '
				         . 'id="' . esc_attr( $field['id'] )
				         . '" placeholder="'
				         . esc_attr( $field['placeholder'] ) . '" value="'
				         . esc_attr( $data ) . '" />'
				         . '<p class="description">'
				         . esc_html( $field['description'] )
				         . '</p>';
				break;

			case 'published_meal_plans_per_hour_limit':
				$html .= '<input min="1" max="10" type="number" name="'
				         . esc_attr( $option_name ) . '" '
				         . 'id="' . esc_attr( $field['id'] )
				         . '" placeholder="'
				         . esc_attr( $field['placeholder'] ) . '" value="'
				         . esc_attr( $data ) . '" />'
				         . '<p class="description">'
				         . esc_html( $field['description'] )
				         . '</p>';
				break;

			case 'save_meal_plans_published_by_visitors_as_draft':
				$checked = '';
				if ( $data && 'on' === $data ) {
					$checked = 'checked="checked"';
				}
				$html .= '<label><input id="' . esc_attr( $field['id'] )
				         . '" type="checkbox" name="' . esc_attr( $option_name )
				         . '" ' . $checked . '/>'
				         . esc_html( $field['description'] )
				         . '</label>';
				break;

			case 'page_id_to_redirect_if_new_plan_is_draft':

				$current = (int) get_option( $option_name, 0 );

				$pages = get_posts( [
					'post_type'      => 'page',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'orderby'        => 'title',
					'order'          => 'ASC',
				] );

				$html .= '<select id="' . esc_attr( $field['id'] ) . '" name="' . esc_attr( $option_name ) . '">';
				$html .= '<option value="0">' . esc_html__( '— No Redirection —', 'mixmymeals' ) . '</option>';

				foreach ( $pages as $page ) {
					$html .= sprintf(
						'<option value="%d"%s>%s</option>',
						(int) $page->ID,
						selected( $current, (int) $page->ID, false ),
						esc_html( $page->post_title )
					);
				}

				$html .= '</select>';

				$html .= '<p><label for="' . esc_attr( $field['id'] ) . '">'
				         . esc_html( $field['description'] )
				         . '</label></p>';

				break;

			case 'mail_from_email':
			case 'cleanup_report_recipient':
				$html .= '<input id="' . esc_attr( $field['id'] )
				         . '" type="email" name="' . esc_attr( $option_name )
				         . '" placeholder="'
				         . esc_attr( $field['placeholder'] ) . '" value="'
				         . esc_attr( $data ) . '" class="regular-text" /><p class="description">'
				         . esc_html( $field['description'] ) . '</p>';
				break;

			case 'mail_suppression_list':
				$list  = is_array( $data ) ? $data : array();
				$html .= '<textarea id="' . esc_attr( $field['id'] )
				         . '" rows="6" cols="60" name="'
				         . esc_attr( $option_name ) . '" placeholder="'
				         . esc_attr( $field['placeholder'] ) . '">'
				         . esc_textarea( implode( "\n", $list ) )
				         . '</textarea><p class="description">'
				         . esc_html( $field['description'] ) . '</p>';
				break;

			case 'subscription_pending_hours':
			case 'subscription_retention_days':
				$html .= '<input type="number" min="1" name="' . esc_attr( $option_name )
				         . '" id="' . esc_attr( $field['id'] ) . '" placeholder="'
				         . esc_attr( $field['placeholder'] ) . '" value="' . esc_attr( $data )
				         . '" /><p class="description">' . esc_html( $field['description'] ) . '</p>';
				break;

			case 'subscription_schedule':
				$rows  = is_array( $data ) ? $data : array();
				$html .= $this->render_subscription_schedule_field( $option_name, $rows, (string) $field['description'] );
				break;


		}

		if ( ! $echo ) {
			return $html;
		}

		// Nothing to escape here.
		// All values have been properly escaped before.
		echo $html; //phpcs:ignore

		return true;
	}

	/**
	 * Renders the repeatable subscription sending-schedule rows (weekday + time
	 * + template) plus an "Add row" control and a clone prototype.
	 *
	 * @param string $option_name Base option name.
	 * @param array  $rows        Existing rows.
	 * @param string $description Field description.
	 *
	 * @return string
	 */
	private function render_subscription_schedule_field( string $option_name, array $rows, string $description ): string {

		$days = array(
			1 => __( 'Monday', 'mixmymeals' ),
			2 => __( 'Tuesday', 'mixmymeals' ),
			3 => __( 'Wednesday', 'mixmymeals' ),
			4 => __( 'Thursday', 'mixmymeals' ),
			5 => __( 'Friday', 'mixmymeals' ),
			6 => __( 'Saturday', 'mixmymeals' ),
			7 => __( 'Sunday', 'mixmymeals' ),
		);

		$templates = class_exists( 'Mixmymeals_Subscriptions' )
			? Mixmymeals_Subscriptions::builtin_templates()
			: array();

		if ( class_exists( 'Mixmymeals_Mailer' ) ) {
			foreach ( (array) glob( Mixmymeals_Mailer::templates_dir() . '*.html' ) as $file ) {
				$base = basename( (string) $file );
				if ( 'layout.html' === $base ) {
					continue;
				}
				$templates[ 'file:' . $base ] = $base;
			}
		}

		$build_row = static function ( $index, array $row ) use ( $option_name, $days, $templates ): string {

			$cur_day  = isset( $row['day'] ) ? (int) $row['day'] : 1;
			$cur_time = isset( $row['time'] ) ? (string) $row['time'] : '08:00';
			$cur_tpl  = isset( $row['template'] ) ? (string) $row['template'] : '';

			$name = esc_attr( $option_name . '[' . $index . ']' );

			$day_opts = '';
			foreach ( $days as $d => $label ) {
				$day_opts .= '<option value="' . esc_attr( $d ) . '"' . selected( $cur_day, $d, false ) . '>' . esc_html( $label ) . '</option>';
			}

			$tpl_opts = '';
			foreach ( $templates as $id => $label ) {
				$tpl_opts .= '<option value="' . esc_attr( $id ) . '"' . selected( $cur_tpl, $id, false ) . '>' . esc_html( $label ) . '</option>';
			}

			return '<tr class="mmm-schedule-row">'
				. '<td><select name="' . $name . '[day]">' . $day_opts . '</select></td>'
				. '<td><input type="time" name="' . $name . '[time]" value="' . esc_attr( $cur_time ) . '"></td>'
				. '<td><select name="' . $name . '[template]">' . $tpl_opts . '</select></td>'
				. '<td><button type="button" class="button mmm-schedule-remove" aria-label="' . esc_attr__( 'Remove row', 'mixmymeals' ) . '">×</button></td>'
				. '</tr>';
		};

		$body = '';
		foreach ( array_values( $rows ) as $i => $row ) {
			$body .= $build_row( $i, is_array( $row ) ? $row : array() );
		}

		$html  = '<table class="widefat" id="mmm-schedule-table" style="max-width:680px;"><thead><tr>'
			. '<th>' . esc_html__( 'Weekday', 'mixmymeals' ) . '</th>'
			. '<th>' . esc_html__( 'Time', 'mixmymeals' ) . '</th>'
			. '<th>' . esc_html__( 'Template', 'mixmymeals' ) . '</th>'
			. '<th></th></tr></thead><tbody>' . $body . '</tbody></table>';
		$html .= '<p><button type="button" class="button" id="mmm-schedule-add">'
			. esc_html__( 'Add row', 'mixmymeals' ) . '</button></p>';
		$html .= '<script type="text/html" id="mmm-schedule-row-tpl">' . $build_row( '__INDEX__', array() ) . '</script>';
		$html .= '<p class="description">' . esc_html( $description ) . '</p>';

		return $html;
	}
}