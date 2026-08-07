<?php
/**
 * Per-user meta on the WordPress profile screen.
 *
 * Currently that is the social accounts section: every user (who can edit
 * posts) stores their own Instagram and Pinterest credentials on their
 * profile and posts/schedules to THEIR accounts. There is deliberately no
 * site-wide fallback — without own credentials, posting is not possible.
 *
 * @package MixMyMeals/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders and saves the plugin's fields on the user profile.
 */
class Mixmymeals_User_Meta {

	/**
	 * Nonce action prefix for the social accounts section (the user ID is
	 * appended, so a nonce never travels from one profile to another).
	 *
	 * @var string
	 */
	const SOCIAL_NONCE_ACTION = 'mmm_social_accounts_';

	/**
	 * Nonce field name of the social accounts section.
	 *
	 * @var string
	 */
	const SOCIAL_NONCE_FIELD = 'mmm_social_accounts_nonce';

	public function __construct() {
		add_action( 'show_user_profile', array( $this, 'render_social_fields' ) );
		add_action( 'edit_user_profile', array( $this, 'render_social_fields' ) );
		add_action( 'personal_options_update', array( $this, 'save_social_fields' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_social_fields' ) );
	}

	/* ---------------------------------------------------------------------
	 * Social accounts
	 * ------------------------------------------------------------------ */

	/**
	 * Whether the profile owner is eligible for own social credentials.
	 *
	 * @param WP_User $user Profile owner.
	 * @return bool
	 */
	private function social_eligible( WP_User $user ): bool {
		return user_can( $user, 'edit_posts' )
			&& class_exists( 'Mixmymeals_Instagram' )
			&& class_exists( 'Mixmymeals_Pinterest' );
	}

	/**
	 * Renders the “Connect with Pinterest” row.
	 *
	 * Nur im eigenen Profil: Bei Pinterest autorisiert immer die Person, die
	 * klickt — den dabei entstehenden Token in ein fremdes Profil zu schreiben,
	 * wäre schlicht falsch. Für andere Benutzer bleiben die manuellen Felder.
	 *
	 * @param WP_User $user Profile owner.
	 * @return void
	 */
	private function render_pinterest_connect( WP_User $user ): void {

		if ( (int) $user->ID !== (int) get_current_user_id() ) {
			printf(
				'<tr><th></th><td><p class="description">%s</p></td></tr>',
				esc_html__( 'Connecting with Pinterest only works in your own profile — the authorization always belongs to whoever is logged in.', 'mixmymeals' )
			);
			return;
		}

		$redirect = Mixmymeals_Pinterest::oauth_redirect_uri();
		$url      = Mixmymeals_Pinterest::oauth_authorize_url( (int) $user->ID );

		echo '<tr><th>' . esc_html__( 'Connect Pinterest', 'mixmymeals' ) . '</th><td>';

		if ( '' === $url ) {
			// Ohne App-ID gibt es nichts zu autorisieren.
			printf(
				'<p class="description">%s</p>',
				esc_html__( 'Enter App ID and App Secret above and save the profile — then the connect button appears here.', 'mixmymeals' )
			);
		} else {
			printf(
				'<p><a class="button button-secondary" href="%s">%s</a></p>',
				esc_url( $url ),
				esc_html__( 'Connect with Pinterest', 'mixmymeals' )
			);
			printf(
				'<p class="description">%s</p>',
				esc_html__( 'Asks Pinterest for the write permissions that pinning needs (pins:write, boards:write) and stores both tokens automatically. The button on the Pinterest developer platform cannot do this — it only issues read permissions.', 'mixmymeals' )
			);
		}

		printf(
			'<p class="description">%s<br /><code>%s</code></p>',
			esc_html__( 'This exact redirect URI must be registered in your Pinterest app:', 'mixmymeals' ),
			esc_html( $redirect )
		);

		echo '</td></tr>';
	}

	/**
	 * Renders the Instagram/Pinterest credential fields on the profile.
	 *
	 * @param WP_User $user Profile owner.
	 * @return void
	 */
	public function render_social_fields( WP_User $user ): void {

		if ( ! $this->social_eligible( $user ) ) {
			return;
		}

		wp_nonce_field( self::SOCIAL_NONCE_ACTION . $user->ID, self::SOCIAL_NONCE_FIELD );

		echo '<h2>' . esc_html__( 'MixMyMeals: Social accounts', 'mixmymeals' ) . '</h2>';
		echo '<p class="description">'
			. esc_html__( 'Posting and scheduling always uses YOUR credentials below — without them, posting is not possible.', 'mixmymeals' )
			. '</p>';

		// ── Instagram ────────────────────────────────────────────────────
		$ig_token = (string) get_user_meta( $user->ID, Mixmymeals_Instagram::USER_META_PREFIX . 'access_token', true );

		echo '<table class="form-table" role="presentation">';
		printf(
			'<tr><th><label for="mmm_ig_token">%s</label></th><td>'
			. '<input type="password" autocomplete="off" class="regular-text" id="mmm_ig_token" name="mmm_ig_token" value="%s" />',
			esc_html__( 'Instagram Access Token', 'mixmymeals' ),
			esc_attr( $ig_token )
		);

		if ( '' !== trim( $ig_token ) ) {
			Mixmymeals_Instagram::set_acting_user( (int) $user->ID );
			$status = Mixmymeals_Instagram::token_status();
			Mixmymeals_Instagram::set_acting_user( null );

			if ( $status['ok'] ) {
				printf(
					'<p class="description" style="color:#008a20;">%s</p>',
					esc_html(
						sprintf(
							/* translators: %s: Instagram user name. */
							__( 'Connected as @%s.', 'mixmymeals' ),
							$status['username']
						)
					)
				);
			} else {
				printf(
					'<p class="description" style="color:#d63638;">%s</p>',
					esc_html(
						sprintf(
							/* translators: %s: error message. */
							__( 'Instagram connection error: %s', 'mixmymeals' ),
							$status['error']
						)
					)
				);
			}
		}

		printf(
			'<p class="description">%s</p></td></tr>',
			esc_html__( 'Long-lived token of your own Meta app (“Instagram Login” variant). Renewed automatically once a week.', 'mixmymeals' )
		);

		// ── Pinterest ────────────────────────────────────────────────────
		$pin_token = (string) get_user_meta( $user->ID, Mixmymeals_Pinterest::USER_META_PREFIX . 'access_token', true );

		printf(
			'<tr><th><label for="mmm_pin_token">%s</label></th><td>'
			. '<input type="password" autocomplete="off" class="regular-text" id="mmm_pin_token" name="mmm_pin_token" value="%s" />',
			esc_html__( 'Pinterest Access Token', 'mixmymeals' ),
			esc_attr( $pin_token )
		);

		if ( '' !== trim( $pin_token ) ) {
			Mixmymeals_Pinterest::set_acting_user( (int) $user->ID );
			$pstatus = Mixmymeals_Pinterest::token_status();

			if ( $pstatus['ok'] ) {
				printf(
					'<p class="description" style="color:#008a20;">%s</p>',
					esc_html(
						sprintf(
							/* translators: %s: Pinterest user name. */
							__( 'Connected as @%s.', 'mixmymeals' ),
							$pstatus['username']
						)
					)
				);

				$boards = Mixmymeals_Pinterest::boards();
				if ( $boards ) {
					echo '<p class="description"><strong>' . esc_html__( 'Your boards:', 'mixmymeals' ) . '</strong><br />';
					foreach ( $boards as $board ) {
						echo esc_html( $board['name'] ) . ' — <code>' . esc_html( $board['id'] ) . '</code><br />';
					}
					echo '</p>';
				}
			} else {
				printf(
					'<p class="description" style="color:#d63638;">%s</p>',
					esc_html(
						sprintf(
							/* translators: %s: error message. */
							__( 'Pinterest connection error: %s', 'mixmymeals' ),
							$pstatus['error']
						)
					)
				);
			}
			Mixmymeals_Pinterest::set_acting_user( null );
		}

		echo '</td></tr>';

		$fields = array(
			'mmm_pin_board_id'      => array( __( 'Pinterest Board ID', 'mixmymeals' ), 'board_id', 'text' ),
			'mmm_pin_app_id'        => array( __( 'Pinterest App ID', 'mixmymeals' ), 'app_id', 'text' ),
			'mmm_pin_app_secret'    => array( __( 'Pinterest App Secret', 'mixmymeals' ), 'app_secret', 'password' ),
			'mmm_pin_refresh_token' => array( __( 'Pinterest Refresh Token', 'mixmymeals' ), 'refresh_token', 'password' ),
		);

		foreach ( $fields as $field_id => list( $label, $meta_key, $type ) ) {
			printf(
				'<tr><th><label for="%1$s">%2$s</label></th><td>'
				. '<input type="%3$s" autocomplete="off" class="regular-text" id="%1$s" name="%1$s" value="%4$s" /></td></tr>',
				esc_attr( $field_id ),
				esc_html( $label ),
				esc_attr( $type ),
				esc_attr( (string) get_user_meta( $user->ID, Mixmymeals_Pinterest::USER_META_PREFIX . $meta_key, true ) )
			);
		}

		$this->render_pinterest_connect( $user );

		printf(
			'<tr><th></th><td><p class="description">%s</p></td></tr>',
			esc_html__( 'App ID and App Secret are needed for connecting and for the automatic weekly token renewal. With the connect button above, the access and refresh token are filled in for you.', 'mixmymeals' )
		);

		echo '</table>';
	}

	/**
	 * Saves the credential fields (own profile or admin editing a user).
	 *
	 * @param int $user_id Profile owner ID.
	 * @return void
	 */
	public function save_social_fields( int $user_id ): void {

		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user || ! $this->social_eligible( $user ) ) {
			return;
		}

		if ( ! isset( $_POST[ self::SOCIAL_NONCE_FIELD ] )
			|| ! wp_verify_nonce( sanitize_key( $_POST[ self::SOCIAL_NONCE_FIELD ] ), self::SOCIAL_NONCE_ACTION . $user_id )
		) {
			return;
		}

		// Instagram token: on change reset the bookkeeping of the user's set.
		$ig_prefix = Mixmymeals_Instagram::USER_META_PREFIX;
		$ig_old    = trim( (string) get_user_meta( $user_id, $ig_prefix . 'access_token', true ) );
		$ig_new    = isset( $_POST['mmm_ig_token'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['mmm_ig_token'] ) ) ) : '';

		$this->store( $user_id, $ig_prefix . 'access_token', $ig_new );

		if ( $ig_new !== $ig_old ) {
			foreach ( array( 'ig_user_id', 'ig_username', 'token_error' ) as $key ) {
				delete_user_meta( $user_id, $ig_prefix . $key );
			}
			if ( '' !== $ig_new ) {
				update_user_meta( $user_id, $ig_prefix . 'token_refreshed', time() );
				update_user_meta( $user_id, $ig_prefix . 'token_expires', time() + 60 * DAY_IN_SECONDS );
			} else {
				delete_user_meta( $user_id, $ig_prefix . 'token_refreshed' );
				delete_user_meta( $user_id, $ig_prefix . 'token_expires' );
			}
		}

		// Pinterest credential set.
		$pin_prefix = Mixmymeals_Pinterest::USER_META_PREFIX;
		$pin_old    = trim( (string) get_user_meta( $user_id, $pin_prefix . 'access_token', true ) );
		$pin_new    = isset( $_POST['mmm_pin_token'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['mmm_pin_token'] ) ) ) : '';

		$this->store( $user_id, $pin_prefix . 'access_token', $pin_new );

		if ( $pin_new !== $pin_old ) {
			delete_user_meta( $user_id, $pin_prefix . 'token_error' );
			if ( '' !== $pin_new ) {
				update_user_meta( $user_id, $pin_prefix . 'token_refreshed', time() );
			} else {
				delete_user_meta( $user_id, $pin_prefix . 'token_refreshed' );
			}
		}

		$pin_fields = array(
			'mmm_pin_board_id'      => 'board_id',
			'mmm_pin_app_id'        => 'app_id',
			'mmm_pin_app_secret'    => 'app_secret',
			'mmm_pin_refresh_token' => 'refresh_token',
		);
		foreach ( $pin_fields as $field_id => $meta_key ) {
			$value = isset( $_POST[ $field_id ] ) ? trim( sanitize_text_field( wp_unslash( $_POST[ $field_id ] ) ) ) : '';
			$this->store( $user_id, $pin_prefix . $meta_key, $value );
		}
	}

	/* ---------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Stores a value as user meta, deleting the meta when empty (so a missing
	 * credential really is missing rather than an empty string).
	 *
	 * @param int    $user_id User ID.
	 * @param string $key     Full meta key.
	 * @param string $value   Value ('' deletes).
	 * @return void
	 */
	private function store( int $user_id, string $key, string $value ): void {
		if ( '' === $value ) {
			delete_user_meta( $user_id, $key );
			return;
		}
		update_user_meta( $user_id, $key, $value );
	}
}
