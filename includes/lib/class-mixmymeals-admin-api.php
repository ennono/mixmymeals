<?php
/**
 * Post type Admin API file.
 *
 * @package Mixmymeals/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin API class.
 */
class Mixmymeals_Admin_API {

	/**
	 * Constructor function
	 */
	public function __construct() {

	}

	/**
	 * Generate HTML for displaying settings fields.
	 *
	 * @param   array        $data  Data array.
	 * @param   object|null  $post  Post object.
	 * @param   boolean      $echo  Whether to echo the field HTML or return it.
	 *
	 * @return string
	 */
	public function display_field(
		array $data = array(), object $post = null, bool $echo = true
	) {

		// Get field info.
		$field = $data['field'] ?? $data;

		// Check for prefix on option name.
		$option_name = '';
		if ( isset( $data['prefix'] ) ) {
			$option_name = $data['prefix'];
		}

		// Get saved data.
		$data        = '';
		$option_name .= $field['id'];
		if ( $post ) {

			// Get saved field data.
			$option = get_post_meta( $post->ID, $field['id'], true );

			// Get data to display in field.
		} else {

			// Get saved option.
			$option = get_option( $option_name );

			// Get data to display in field.
		}
		if ( isset( $option ) ) {
			$data = $option;
		}

		// Show default data if no option saved and default is supplied.
		if ( false === $data && isset( $field['default'] ) ) {
			$data = $field['default'];
		} elseif ( false === $data ) {
			$data = '';
		}

		$html = '';

		switch ( $field['id'] ) {

			case 'example':
				$html .= '<input type="number" name="'
				         . esc_attr( $option_name ) . '" '
				         . 'id="' . esc_attr( $field['id'] )
				         . '" placeholder="'
				         . esc_attr( $field['placeholder'] ) . '" value="'
				         . esc_attr( $data ) . '" />'
				         . '<p class="description">'
				         . esc_html( $field['description'] )
				         . '</p>';
				break;

		}

		if ( ! $echo ) {
			return $html;
		}

		// Nothing to escape here.
		// Everything was perfectly escaped before.
		echo $html; //phpcs:ignore

		return true;
	}

}
