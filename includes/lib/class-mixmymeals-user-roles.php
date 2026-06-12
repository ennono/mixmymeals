<?php
/**
 * User Roles.
 *
 * @package MixMyMeals/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Taxonomy functions class.
 */
class Mixmymeals_User_Roles {

	/**
	 * User Roles constructor.
	 */
	public function __construct() {

		// Add user roles:
		add_action(
			'init',
			array( $this, 'add_user_role_meal_mixer' )
		);
		add_action(
			'init',
			array( $this, 'add_user_role_pending_meal_mixer' )
		);

		// Hijack default role for new users:
		add_filter(
			'pre_option_default_role',
			array( $this, 'set_default_role' )
		);

		// Globally disble password reset
		add_filter( 'allow_password_reset', '__return_false' );

	}

	/**
	 * Add member user role
	 *
	 * @return void
	 */
	public function add_user_role_meal_mixer(): void {

		$capabilities = array(
			// 'read'               => true,
			// 'read_private_posts' => true,
		);

		if ( get_option( 'mixmymeals_meal_mixer_role_set' ) < 1 ) {
			add_role(
				'mixmymeals_meal_mixer',
				__( 'Meal Mixer', 'mixmymeals' ),
				$capabilities
			);
			update_option( 'mixmymeals_meal_mixer_role_set', 1 );
		}
	}

	/**
	 * Add pending user role
	 *
	 * @return void
	 */
	public function add_user_role_pending_meal_mixer(): void {

		// No capabilities for pending users:
		$capabilities = array(
			'',
		);

		if ( get_option( 'mixmymeals_meal_mixer_pending_role_set' ) < 1 ) {
			add_role(
				'mixmymeals_pending_meal_mixer',
				__( 'Pending Meal Mixer', 'mixmymeals' ),
				$capabilities
			);
			update_option( 'mixmymeals_meal_mixer_pending_role_set', 1 );
		}
	}

	/**
	 * Set default role
	 *
	 * @return string 'mixmymeals_pending_meal_mixer'
	 */
	public function set_default_role( string $default_role ): string {

		// $default_role is what admin sets in admin dashboard.
		// We ignore this and return pending role to make sure
		// that new users allway get pending role first.

		return 'mixmymeals_pending_meal_mixer';
	}
}
