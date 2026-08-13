<?php
/**
 * Plugin Name: MixMyMeals
 * Version: 3.2.0
 * Plugin URI:
 * Description: Build recipes, plan weekly meals and generate shopping lists — with a frontend planner, nutrition data and PDF export.
 * Author: Timo Fricke
 * Requires PHP: 8.0
 * Requires at least: 6.0
 * Tested up to: 7.0
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
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
require_once 'includes/lib/class-mixmymeals-author.php';
require_once 'includes/lib/class-mixmymeals-recipe.php';
require_once 'includes/lib/class-mixmymeals-ingredient.php';
require_once 'includes/lib/class-mixmymeals-ingredient-amount.php';
require_once 'includes/lib/class-mixmymeals-meal.php';
require_once 'includes/lib/class-mixmymeals-meal-plan.php';
require_once 'includes/lib/class-mixmymeals-cart.php';
require_once 'includes/lib/class-mixmymeals-equipment.php';

require_once 'includes/lib/class-mixmymeals-pdf-builder.php';
require_once 'includes/lib/class-mixmymeals-meal-plan-pdf.php';
require_once 'includes/lib/class-mixmymeals-rest-api.php';
require_once 'includes/lib/class-mixmymeals-cleanup.php';
require_once 'includes/lib/class-mixmymeals-mail-log.php';
require_once 'includes/lib/class-mixmymeals-mailer.php';
require_once 'includes/lib/class-mixmymeals-subscriptions.php';
require_once 'includes/lib/class-mixmymeals-importer.php';
require_once 'includes/lib/class-mixmymeals-ratings.php';
require_once 'includes/lib/class-mixmymeals-photos.php';
require_once 'includes/lib/class-mixmymeals-privacy.php';


/**
 * Writes a message to the PHP error log when WP_DEBUG and WP_DEBUG_LOG are both enabled.
 *
 * @param mixed $message String, array, or object to log.
 * @return void
 */
function mixmymeals_write_to_log( $message ) {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true && defined( 'WP_DEBUG_LOG' )
		&& WP_DEBUG_LOG === true ) {
		if ( is_array( $message ) || is_object( $message ) ) {
			error_log( print_r( $message, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.PHP.DevelopmentFunctions.error_log_print_r
		} else {
			error_log( $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}


/**
 * Returns the main instance of MixMyMeals to prevent the need to use globals.
 *
 * @return object MixMyMeals
 */
function mixmymeals() {

	// Must match the Version header above: this string is the cache buster on
	// every enqueued script and stylesheet, so a stale value serves old assets
	// to everyone who already has them cached.
	$instance = Mixmymeals::instance( __FILE__, '3.2.0' );

	if ( is_null( $instance->settings ) ) {
		$instance->settings = Mixmymeals_Settings::instance( $instance );
	}

	return $instance;
}

mixmymeals();
mixmymeals()->add_post_types();
mixmymeals()->add_taxonomies();
mixmymeals()->add_shortcodes();
// Warenkorb-AJAX-Endpunkte: statisch registriert, weil die Klasse pro
// Verwendung neu instanziiert wird.
Mixmymeals_Cart::register_ajax();
Mixmymeals_Meal_Plan_PDF::register_ajax();
// Bearbeitungszähler: ebenfalls statisch, weil Rezepte pro Post instanziiert
// werden, der Hook aber einmal pro Request hängen muss.
Mixmymeals_Recipe::register_edit_count();
Mixmymeals_Recipe::register_cook_count();
// Bring!-Importansicht: ebenso statisch, Pläne werden pro Post instanziiert,
// Query-Var und template_redirect brauchen aber einen Hook pro Request.
Mixmymeals_Meal_Plan::register_bring();
// Autoren-Taxonomie: Term-Felder und die einmalige Migration der alten
// Post-Meta-Felder, ebenfalls ein Hook-Satz pro Request. Die Taxonomie selbst
// registriert Mixmymeals_Taxonomies wie alle anderen auch.
Mixmymeals_Author::register_term_admin();
// Einmalige Überführung des alten Unteilbar-Flags in den Teiler der Einheit.
add_action( 'admin_init', array( 'Mixmymeals_Ingredient_Amount', 'migrate_indivisible' ) );
mixmymeals()->add_post_meta();
mixmymeals()->add_rest_api();
mixmymeals()->add_cleanup();
mixmymeals()->add_mail_log();
mixmymeals()->add_subscriptions();
mixmymeals()->add_importer();
mixmymeals()->add_ratings();
mixmymeals()->add_photos();
mixmymeals()->add_privacy();
