<?php
/**
 * Template Loader for MixMyMeals
 *
 * Adds Elementor Theme Builder integration with fallback to theme and plugin templates.
 *
 * @package MixMyMeals/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mixmymeals_Templates {

	public function __construct() {
		// Sicherstellen, dass Elementor Pro vollständig geladen ist, bevor wir den Template Hook aktivieren.
		add_action( 'wp', [ $this, 'register_template_loader' ], 20 );
	}

	/**
	 * Registriert den Template-Loader-Filter.
	 */
	public function register_template_loader() {
		add_filter( 'template_include', [ $this, 'load_template' ] );
	}

	/**
	 * Wählt das passende Template.
	 */
	public function load_template( $template ) {

		$post_type = get_post_type();

		// ---- SINGLE ----
		if ( is_singular( [ 'mmm_meal', 'mmm_recipe', 'mmm_ingredient', 'mmm_meal_plan' ] ) ) {

			// Elementor Template Vorrang
			$elementor_template = self::get_elementor_template( $post_type, 'single' );
			if ( $elementor_template ) {
				return $elementor_template;
			}

			// Theme/Plugin Template
			$found = self::locate_template( "single-{$post_type}.php" );
			if ( $found ) {
				return $found;
			}
		}

		// ---- ARCHIVE ----
		if ( is_post_type_archive( [ 'mmm_meal', 'mmm_recipe', 'mmm_ingredient', 'mmm_meal_plan' ] ) ) {

			// Elementor Template Vorrang
			$elementor_template = self::get_elementor_template( $post_type, 'archive' );
			if ( $elementor_template ) {
				return $elementor_template;
			}

			// Theme/Plugin Template
			$found = self::locate_template( "archive-{$post_type}.php" );
			if ( $found ) {
				return $found;
			}
		}

		return $template;
	}

	/**
	 * Elementor Template Fallback — prüft, ob der Theme Builder ein Template liefert.
	 *
	 * @param string $post_type Post type slug (z.B. 'mmm_meal', 'mmm_recipe').
	 * @param string $context   'single' oder 'archive'.
	 * @return string|false     Pfad zum Template oder false.
	 */
	public static function get_elementor_template( $post_type, $context ) {

		write_to_log("DEBUG: get_elementor_template called for {$post_type} / {$context}");

		// Elementor Basis vorhanden?
		if ( ! did_action( 'elementor/loaded' ) ) {
			write_to_log('DEBUG: Elementor not loaded yet');
			return false;
		}

		// Elementor Pro Theme Builder aktiv?
		if ( ! class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ) {
			write_to_log('DEBUG: Elementor Pro ThemeBuilder not active');
			return false;
		}

		$theme_builder = \ElementorPro\Modules\ThemeBuilder\Module::instance();
		if ( ! $theme_builder ) {
			write_to_log('DEBUG: ThemeBuilder instance not found');
			return false;
		}

		$conditions_manager = $theme_builder->get_conditions_manager();
		if ( ! $conditions_manager ) {
			write_to_log('DEBUG: ThemeBuilder conditions manager not available');
			return false;
		}

		write_to_log('DEBUG: Elementor ThemeBuilder loaded correctly');

		// 🔍 Prüfen, ob für diesen Kontext ein aktives Template existiert
		$documents = $conditions_manager->get_documents_for_location( $context );

		if ( ! empty( $documents ) ) {
			write_to_log("DEBUG: Found Elementor Pro template for {$context} ({$post_type})");
			// Elementor rendert das Template automatisch – wir geben nur Dummy zurück
			return get_stylesheet_directory() . '/index.php';
		}

		write_to_log("DEBUG: No Elementor template found for {$context} ({$post_type})");
		return false;
	}


	/**
	 * Theme Override + Plugin-Fallback.
	 */
	public static function locate_template( $template_name ) {

		// 1. Theme Override (z. B. /wp-content/themes/child/mixmymeals/single-mmm_meal.php)
		$theme_template = locate_template( [ "mixmymeals/{$template_name}" ] );
		if ( $theme_template ) {
			write_to_log("DEBUG: Found theme override for {$template_name}");
			return $theme_template;
		}

		// 2. Plugin-Fallback
		$plugin_template = plugin_dir_path( __FILE__ ) . "../templates/{$template_name}";
		if ( file_exists( $plugin_template ) ) {
			write_to_log("DEBUG: Using plugin fallback template for {$template_name}");
			return $plugin_template;
		}

		write_to_log("DEBUG: No template found for {$template_name}");
		return false;
	}
}
