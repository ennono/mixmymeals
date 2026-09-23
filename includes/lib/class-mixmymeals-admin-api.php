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

		/**
		 * Lets a plugin render a settings field this class does not know.
		 *
		 * The switch below dispatches on the field id, which add-ons cannot
		 * extend. Returning anything other than null here short-circuits it, so
		 * a plugin that contributes fields through `mixmymeals_settings_fields`
		 * can also bring the markup for them instead of having to get its field
		 * types merged into this file.
		 *
		 * @param string|null $rendered    Markup, or null to let the switch handle it.
		 * @param array       $field       Field definition.
		 * @param mixed       $data        Current value.
		 * @param string      $option_name Option (or post meta) name.
		 */
		$rendered = apply_filters( 'mixmymeals_render_settings_field', null, $field, $data, $option_name );

		if ( null !== $rendered ) {
			if ( ! $echo ) {
				return (string) $rendered;
			}

			echo $rendered; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the renderer owns its escaping.

			return true;
		}

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
			case 'all_equipment_description':
			case 'all_meal_plans_description':
			case 'individual_meal_plans_description':
			case 'recipe_steps_notice':
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
			case 'ingredient_slug':
			case 'equipment_slug':
			case 'currency':
			case 'diet_capable_format':
			case 'plan_undated_meals_label':
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
			case 'affiliate_disclosure':
			case 'notice_rating':
			case 'notice_photo':
			case 'notice_subscribe':
			case 'notice_plan':
				$html .= '<textarea id="' . esc_attr( $field['id'] )
				         . '" rows="4" cols="60" name="'
				         . esc_attr( $option_name ) . '" placeholder="'
				         . esc_attr( $field['placeholder'] ) . '">'
				         . esc_html( $data )
				         . '</textarea>';
				if ( ! empty( $field['description'] ) ) {
					$html .= '<p class="description">' . esc_html( $field['description'] ) . '</p>';
				}
				break;

			case 'subscription_append_text':
				$html .= '<textarea id="' . esc_attr( $field['id'] )
						. '" rows="6" name="' . esc_attr( $option_name )
						. '" class="large-text code" placeholder="'
						. esc_attr( $field['placeholder'] ) . '">'
						. esc_textarea( (string) $data )
						. '</textarea><p class="description">'
						. esc_html( $field['description'] ) . '</p>';
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
			case 'photos_auto_approve':
			case 'public_mode':
			case 'show_recipe_steps':
			case 'delete_data_on_uninstall':
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

			case 'plan_undated_meals':
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

			case 'page_id_to_redirect_after_plan_created':
			case 'page_id_to_redirect_if_new_plan_is_draft':
			case 'subscription_subscribe_page':
			case 'subscription_confirm_page':
			case 'subscription_unsubscribe_page':
			case 'subscription_rate_page':
			case 'subscription_upload_page':

				$current = (int) get_option( $option_name, 0 );

				$pages = get_posts( [
					'post_type'      => 'page',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'orderby'        => 'title',
					'order'          => 'ASC',
				] );

				$html .= '<select id="' . esc_attr( $field['id'] ) . '" name="' . esc_attr( $option_name ) . '">';
				/*
				 * What picking nothing means differs per field: for most of
				 * these it is "do not redirect", for the success page it is
				 * "open the plan itself". The placeholder carries the wording
				 * where the default one would be wrong.
				 */
				$zero_label = '' !== (string) ( $field['placeholder'] ?? '' )
					? (string) $field['placeholder']
					: __( '— No Redirection —', 'mixmymeals' );

				$html .= '<option value="0">' . esc_html( $zero_label ) . '</option>';

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

			case 'plan_thumbs_mode':
				$current = '' !== (string) $data ? (string) $data : Mixmymeals_Meal_Plan::THUMBS_MODE_DEFAULT;

				$html .= '<select id="' . esc_attr( $field['id'] ) . '" name="' . esc_attr( $option_name ) . '">';

				foreach ( Mixmymeals_Meal_Plan::thumbs_modes() as $mode => $label ) {
					$html .= sprintf(
						'<option value="%s"%s>%s</option>',
						esc_attr( $mode ),
						selected( $current, $mode, false ),
						esc_html( $label )
					);
				}

				$html .= '</select>';
				$html .= '<p class="description">' . esc_html( $field['description'] ) . '</p>';
				break;

			case 'plan_thumbs_max':
				$html .= '<input min="0" max="50" type="number" name="'
				         . esc_attr( $option_name ) . '" '
				         . 'id="' . esc_attr( $field['id'] )
				         . '" placeholder="'
				         . esc_attr( $field['placeholder'] ) . '" value="'
				         . esc_attr( $data ) . '" />'
				         . '<p class="description">'
				         . esc_html( $field['description'] )
				         . '</p>';
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
	 * Renders the repeatable subscription sending-rule rows (trigger + time +
	 * subject + template) plus an "Add row" control and a clone prototype.
	 *
	 * @param string $option_name Base option name.
	 * @param array  $rows        Existing rows.
	 * @param string $description Field description.
	 *
	 * @return string
	 */
	private function render_subscription_schedule_field( string $option_name, array $rows, string $description ): string {

		$triggers = class_exists( 'Mixmymeals_Subscriptions' )
			? Mixmymeals_Subscriptions::trigger_labels()
			: array(
				'each_day'       => __( 'Each day in the plan', 'mixmymeals' ),
				'on_start'       => __( 'On the first plan day', 'mixmymeals' ),
				'before_start'   => __( 'The day before the plan starts', 'mixmymeals' ),
				'evening_before' => __( 'The day before each plan day', 'mixmymeals' ),
				'on_end'         => __( 'On the last plan day', 'mixmymeals' ),
				'after_end'      => __( 'The day after the last plan day', 'mixmymeals' ),
				'on_confirm'     => __( 'On confirmation (immediately)', 'mixmymeals' ),
			);

		// Templates: every *.html from the theme's mixmymeals/emails/ directory and
		// the plugin's own (except the shared layout), grouped by subfolder so the
		// dropdown shows which folder each template comes from. The chosen template
		// decides what the mail shows; the option value stays the full relative path.
		$tpl_groups = array();

		if ( class_exists( 'Mixmymeals_Mailer' ) ) {
			foreach ( Mixmymeals_Mailer::list_templates() as $path ) {
				$dir = dirname( $path );
				$key = ( '.' === $dir || '' === $dir ) ? '' : $dir;
				$tpl_groups[ $key ][ $path ] = basename( $path );
			}
			ksort( $tpl_groups ); // Base folder ('') first, then subfolders A→Z.
		}

		$test_nonce = wp_create_nonce( 'mmm_sub_test_rule' );

		$build_row = static function ( $index, array $row ) use ( $option_name, $triggers, $tpl_groups, $test_nonce ): string {

			$cur_trigger = isset( $row['trigger'] ) ? (string) $row['trigger'] : 'each_day';
			$cur_time    = isset( $row['time'] ) ? (string) $row['time'] : '08:00';
			$cur_subject = isset( $row['subject'] ) ? (string) $row['subject'] : '';
			$cur_tpl     = isset( $row['template'] ) ? (string) $row['template'] : '';
			$cur_append  = ! empty( $row['append'] );

			$name = esc_attr( $option_name . '[' . $index . ']' );

			$trigger_opts = '';
			foreach ( $triggers as $id => $label ) {
				$trigger_opts .= '<option value="' . esc_attr( $id ) . '"' . selected( $cur_trigger, $id, false ) . '>' . esc_html( $label ) . '</option>';
			}

			$tpl_opts = '';
			foreach ( $tpl_groups as $group => $opts ) {
				if ( '' !== $group ) {
					$tpl_opts .= '<optgroup label="' . esc_attr( $group ) . '">';
				}
				foreach ( $opts as $id => $fname ) {
					$tpl_opts .= '<option value="' . esc_attr( $id ) . '"' . selected( $cur_tpl, $id, false ) . '>' . esc_html( $fname ) . '</option>';
				}
				if ( '' !== $group ) {
					$tpl_opts .= '</optgroup>';
				}
			}

			return '<tr class="mmm-schedule-row">'
				. '<td><select name="' . $name . '[trigger]">' . $trigger_opts . '</select></td>'
				. '<td><input type="time" name="' . $name . '[time]" value="' . esc_attr( '' !== $cur_time ? $cur_time : '08:00' ) . '"></td>'
				. '<td><input type="text" name="' . $name . '[subject]" value="' . esc_attr( $cur_subject ) . '" class="regular-text" placeholder="' . esc_attr__( 'e.g. Your meals: {{ plan }}', 'mixmymeals' ) . '"></td>'
				. '<td><select name="' . $name . '[template]">' . $tpl_opts . '</select></td>'
				. '<td style="text-align:center;"><input type="checkbox" name="' . $name . '[append]" value="on"' . checked( $cur_append, true, false ) . '></td>'
				. '<td>'
					. '<button type="button" class="button mmm-schedule-test" data-nonce="' . esc_attr( $test_nonce ) . '">' . esc_html__( 'Test', 'mixmymeals' ) . '</button> '
					. '<button type="button" class="button mmm-schedule-remove" aria-label="' . esc_attr__( 'Remove row', 'mixmymeals' ) . '">×</button>'
				. '</td>'
				. '</tr>';
		};

		$body = '';
		foreach ( array_values( $rows ) as $i => $row ) {

			$row = is_array( $row ) ? $row : array();

			// Show legacy rows in the new subject/template shape.
			if ( class_exists( 'Mixmymeals_Subscriptions' ) ) {
				$normalized = Mixmymeals_Subscriptions::normalize_rule( $row );
				if ( null !== $normalized ) {
					$row = $normalized;
				}
			}

			$body .= $build_row( $i, $row );
		}

		$html  = '<table class="widefat" id="mmm-schedule-table" style="max-width:980px;"><thead><tr>'
			. '<th>' . esc_html__( 'Trigger', 'mixmymeals' ) . '</th>'
			. '<th>' . esc_html__( 'Time', 'mixmymeals' ) . '</th>'
			. '<th>' . esc_html__( 'Subject', 'mixmymeals' ) . '</th>'
			. '<th>' . esc_html__( 'Template', 'mixmymeals' ) . '</th>'
			. '<th>' . esc_html__( 'Append', 'mixmymeals' ) . '</th>'
			. '<th></th></tr></thead><tbody>' . $body . '</tbody></table>';
		$html .= '<p><button type="button" class="button" id="mmm-schedule-add">'
			. esc_html__( 'Add row', 'mixmymeals' ) . '</button></p>';
		$html .= '<p><label for="mmm-schedule-test-to">' . esc_html__( 'Send test to:', 'mixmymeals' ) . '</label> '
			. '<input type="email" id="mmm-schedule-test-to" class="regular-text" value="' . esc_attr( (string) get_option( 'admin_email' ) ) . '"> '
			. '<span class="description">' . esc_html__( 'Tests render against your latest published meal plan.', 'mixmymeals' ) . '</span></p>';
		$html .= '<p id="mmm-schedule-test-result" aria-live="polite"></p>';
		$html .= '<script type="text/html" id="mmm-schedule-row-tpl">' . $build_row( '__INDEX__', array() ) . '</script>';

		$html .= '<p class="description" style="max-width:980px;">' . esc_html( $description ) . '</p>';
		$html .= $this->render_subscription_schedule_help( $triggers );

		return $html;
	}

	/**
	 * Renders the collapsible help block beneath the sending-rule table:
	 * an explanation of triggers, the placeholders every template can use, and
	 * where the template files live.
	 *
	 * @param array<string,string> $triggers Trigger id => human label.
	 * @return string
	 */
	private function render_subscription_schedule_help( array $triggers ): string {

		$placeholders = array(
			'{{ greeting }}'             => __( 'Salutation, including the subscriber name when given.', 'mixmymeals' ),
			'{{ weekday }}'              => __( 'Name of the weekday the mail is about.', 'mixmymeals' ),
			'{{ plan }}'                 => __( 'Title of the meal plan.', 'mixmymeals' ),
			'{{ plan_url }}'             => __( 'Link to the meal plan.', 'mixmymeals' ),
			'{{ unsubscribe_url }}'      => __( 'Unsubscribe link (the layout already adds one in the footer).', 'mixmymeals' ),
			'{{ meal_list_html }}'       => __( 'The day’s meals as a ready-made list, each with a thumbnail and “Cook now” link.', 'mixmymeals' ),
			'{{ meal_images_html }}'     => __( 'A row of the day’s meal images, each linked to its meal (empty when none have an image).', 'mixmymeals' ),
			'{{ rating_links_html }}'    => __( 'Per-recipe “rate this recipe” links for the day (empty unless a rating page is set).', 'mixmymeals' ),
			'{{ day_summary_html }}'     => __( 'The day’s calorie/cost total as a ready paragraph (empty when none).', 'mixmymeals' ),
			'{{ day_kcal }}'             => __( 'The day’s total calories (number only).', 'mixmymeals' ),
			'{{ day_cost }}'             => __( 'The day’s estimated cost (number only).', 'mixmymeals' ),
			'{{ currency }}'             => __( 'Currency symbol.', 'mixmymeals' ),
			'{{ day_ingredients_html }}' => __( 'Ingredients needed for this day’s meals, grouped by category.', 'mixmymeals' ),
			'{{ shopping_list_html }}'   => __( 'The whole plan’s shopping list, grouped by category.', 'mixmymeals' ),
			'{{ site_name }}'            => __( 'Site name.', 'mixmymeals' ),
			'{{ site_url }}'             => __( 'Site URL.', 'mixmymeals' ),
			'{{ year }}'                 => __( 'Current year.', 'mixmymeals' ),
		);

		$out  = '<details class="mmm-schedule-help" style="margin-top:6px;max-width:980px;">';
		$out .= '<summary style="cursor:pointer;font-weight:600;">' . esc_html__( 'How sending rules, placeholders and templates work', 'mixmymeals' ) . '</summary>';
		$out .= '<div style="margin-top:10px;">';

		// A rule in one line.
		$out .= '<p class="description">' . esc_html__( 'A rule has three parts: a trigger (when to send), a subject and a template (the .html file that decides what the mail shows). The data always comes from the subscribed plan.', 'mixmymeals' ) . '</p>';

		// Triggers.
		$out .= '<h4 style="margin:14px 0 4px;">' . esc_html__( 'Triggers — when a mail is sent', 'mixmymeals' ) . '</h4>';
		$out .= '<ul style="margin:0 0 4px 18px;list-style:disc;">';
		foreach ( $triggers as $label ) {
			$out .= '<li>' . esc_html( $label ) . '</li>';
		}
		$out .= '</ul>';
		$out .= '<p class="description">' . esc_html__( 'All triggers except “On confirmation” send at the chosen time and are relative to the subscribed plan. “On confirmation” sends immediately after opt-in and ignores the time.', 'mixmymeals' ) . '</p>';

		// Placeholders.
		$out .= '<h4 style="margin:14px 0 4px;">' . esc_html__( 'Placeholders — available in every template and in the subject', 'mixmymeals' ) . '</h4>';
		$out .= '<table class="widefat striped" style="max-width:680px;"><tbody>';
		foreach ( $placeholders as $token => $desc ) {
			$out .= '<tr><td style="width:210px;"><code>' . esc_html( $token ) . '</code></td><td>' . esc_html( $desc ) . '</td></tr>';
		}
		$out .= '</tbody></table>';
		$out .= '<p class="description">' . esc_html__( 'Names ending in “_html” already contain formatted HTML — drop them in as-is. A meals template uses meal_list_html, a shopping template uses shopping_list_html; every placeholder is available to every template.', 'mixmymeals' ) . '</p>';

		// Templates.
		$out .= '<h4 style="margin:14px 0 4px;">' . esc_html__( 'Templates — what the mail shows', 'mixmymeals' ) . '</h4>';
		$out .= '<p class="description">' . esc_html__( 'Each trigger has a matching template, named after it (each-day.html, on-start.html, before-start.html, evening-before.html, on-confirm.html). Pick the one that fits the rule’s trigger.', 'mixmymeals' ) . '</p>';
		$out .= '<p class="description">' . sprintf(
			/* translators: %s: theme templates path. */
			esc_html__( 'Templates live in your theme at %s; the plugin only ships an example.html as a starter. Add a .html file there to get a new entry in the Template dropdown. The shared frame (header, footer, unsubscribe link) lives in layout.html.', 'mixmymeals' ),
			'<code>your-theme/mixmymeals/emails/</code>'
		) . '</p>';
		$out .= '<p class="description">' . esc_html__( 'Use the Test button in a row to send that rule to yourself, rendered against your latest published meal plan.', 'mixmymeals' ) . '</p>';

		$out .= '</div></details>';

		return $out;
	}
}
