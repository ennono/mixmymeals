<?php
/**
 * Plugin Name: MixMyMeals
 * Version: 1.4.3
 * Plugin URI:
 * Description: Plan your meals.
 * Author: Timo Fricke
 * Requires PHP: 8.0
 * Requires at least: 6.0
 * Tested up to: 6.9
 * License: GPLv2
 *
 * Text Domain: mixmymeals
 * Domain Path: /lang/
 *
 * @package MixMyMeals
 * @author  Timo Fricke
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load plugin class files.
require_once 'includes/class-mixmymeals.php';
require_once 'includes/class-mixmymeals-settings.php';

// Load plugin libraries.
require_once 'includes/lib/class-mixmymeals-admin-api.php';
require_once 'includes/lib/class-mixmymeals-post-types.php';
require_once 'includes/lib/class-mixmymeals-taxonomies.php';
require_once 'includes/lib/class-mixmymeals-shortcodes.php';
require_once 'includes/lib/class-mixmymeals-post-meta.php';
require_once 'includes/lib/class-mixmymeals-recipe.php';
require_once 'includes/lib/class-mixmymeals-ingredient.php';
require_once 'includes/lib/class-mixmymeals-ingredient-amount.php';
require_once 'includes/lib/class-mixmymeals-meal.php';
require_once 'includes/lib/class-mixmymeals-meal-plan.php';
require_once 'includes/lib/class-mixmymeals-cart.php';
require_once 'includes/lib/class-mixmymeals-equipment.php';

require_once 'includes/lib/class-mixmymeals-pdf-builder.php';
require_once 'includes/lib/class-mixmymeals-rest-api.php';
require_once 'includes/lib/class-mixmymeals-cleanup.php';
require_once 'includes/lib/class-mixmymeals-mail-log.php';
require_once 'includes/lib/class-mixmymeals-mailer.php';
require_once 'includes/lib/class-mixmymeals-importer.php';


/**
 * Writes a message to the PHP error log when WP_DEBUG and WP_DEBUG_LOG are both enabled.
 *
 * @param mixed $message String, array, or object to log.
 * @return void
 */
function write_to_log( $message ) {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true && defined( 'WP_DEBUG_LOG' )
		&& WP_DEBUG_LOG === true ) {
		if ( is_array( $message ) || is_object( $message ) ) {
			error_log( print_r( $message, true ) );
		} else {
			error_log( $message );
		}
	}
}


/**
 * Returns the main instance of MixMyMeals to prevent the need to use globals.
 *
 * @return object MixMyMeals
 */
function mixmymeals() {

	$instance = Mixmymeals::instance( __FILE__, '1.4.3' );

	if ( is_null( $instance->settings ) ) {
		$instance->settings = Mixmymeals_Settings::instance( $instance );
	}

	return $instance;
}

mixmymeals();
mixmymeals()->add_post_types();
mixmymeals()->add_taxonomies();
mixmymeals()->add_shortcodes();
mixmymeals()->add_post_meta();
mixmymeals()->add_rest_api();
mixmymeals()->add_cleanup();
mixmymeals()->add_importer();
