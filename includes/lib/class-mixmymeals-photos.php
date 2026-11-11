<?php
/**
 * Meal photos class file.
 *
 * @package MixMyMeals Plugin/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mixmymeals_Photos
 *
 * Lets visitors upload photos of meals they cooked. Each photo is a regular
 * media-library attachment parented to the recipe (post_parent), marked with
 * _mmm_meal_photo meta so it can be told apart from editorial images. Photos
 * start unapproved (_mmm_photo_approved = 0) and only appear in the recipe
 * gallery after an admin approves them — unless the blanket auto-approve
 * option is on.
 *
 * Uploads come in via the [mmm_photo_upload_form] shortcode (in
 * Mixmymeals_Shortcodes): either through a tokenised link from a subscription
 * drip mail, or from a logged-in user. The uploader is recorded per photo
 * (subscription ID or user ID plus a display name).
 *
 * Moderation happens on a dedicated "Meal Photos" submenu under Meal Plans.
 */
class Mixmymeals_Photos {

	const RECIPE_POST_TYPE = 'mmm_recipe';

	// Attachment meta: marks an attachment as a visitor meal photo.
	const META_FLAG = '_mmm_meal_photo';

	// Attachment meta: '1' once approved, '0' while pending.
	const META_APPROVED = '_mmm_photo_approved';

	// Attachment meta: subscription that uploaded the photo (0 for user uploads).
	const META_SUB = '_mmm_photo_sub';

	// Attachment meta: uploader display name shown in the admin list.
	const META_BY = '_mmm_photo_by';

	const OPT_AUTO_APPROVE = 'mixmymeals_photos_auto_approve';

	const ADMIN_SLUG = 'mixmymeals_photos';

	/**
	 * The admin URL of the moderation screen.
	 *
	 * One place, because the screen has moved twice — from Recipes to the
	 * MixMyMeals menu to Meal Plans — and both redirects back to it still
	 * pointed at the Recipes menu after the first move.
	 *
	 * @return string
	 */
	public static function admin_page_url(): string {
		return admin_url( 'edit.php?post_type=mmm_meal_plan&page=' . self::ADMIN_SLUG );
	}

	/**
	 * Constructor: wires up the moderation page and its actions.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
		add_action( 'admin_post_mmm_photo_action', array( $this, 'handle_admin_action' ) );
		add_action( 'admin_post_mmm_photo_admin_upload', array( $this, 'handle_admin_upload' ) );
	}

	/**
	 * Whether new photos are approved automatically (blanket approval option).
	 *
	 * @return bool
	 */
	public static function is_auto_approved(): bool {
		return 'on' === get_option( self::OPT_AUTO_APPROVE, '' );
	}

	/**
	 * Maximum number of photos one uploader may add per recipe.
	 *
	 * @return int
	 */
	public static function upload_limit(): int {
		return max( 1, (int) apply_filters( 'mixmymeals_photos_per_recipe_limit', 3 ) );
	}

	/**
	 * Maximum accepted file size in bytes (capped by the server upload limit).
	 *
	 * @return int
	 */
	public static function max_bytes(): int {
		$cap = (int) apply_filters( 'mixmymeals_photo_max_bytes', 10 * MB_IN_BYTES );
		return min( $cap, (int) wp_max_upload_size() );
	}

	/**
	 * Stores an uploaded meal photo as an attachment of the recipe.
	 *
	 * The caller is responsible for authorisation (token or logged-in user) and
	 * for the per-uploader limit; this method validates the file itself.
	 *
	 * @param int    $recipe_id Recipe post ID.
	 * @param string $file_key  Key in $_FILES holding the upload.
	 * @param string $by_name   Uploader display name (may be empty).
	 * @param int    $sub_id    Subscription post ID (0 for user uploads).
	 * @param int    $user_id   User ID (0 for subscription uploads).
	 * @return int|WP_Error Attachment ID or error.
	 */
	public static function add_photo( int $recipe_id, string $file_key, string $by_name, int $sub_id = 0, int $user_id = 0 ) {

		if ( get_post_type( $recipe_id ) !== self::RECIPE_POST_TYPE ) {
			return new WP_Error( 'mmm_photo_recipe', __( 'This recipe does not exist.', 'mixmymeals' ) );
		}

		$file = isset( $_FILES[ $file_key ] ) ? $_FILES[ $file_key ] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification.Missing -- handled by media_handle_upload(); nonce/token checked by the shortcode caller.
		if ( ! is_array( $file ) || empty( $file['name'] ) || ! empty( $file['error'] ) ) {
			return new WP_Error( 'mmm_photo_missing', __( 'Please choose a photo to upload.', 'mixmymeals' ) );
		}

		if ( isset( $file['size'] ) && (int) $file['size'] > self::max_bytes() ) {
			return new WP_Error(
				'mmm_photo_size',
				sprintf(
					/* translators: %s: maximum file size, e.g. "10 MB" */
					__( 'This photo is too large. The maximum size is %s.', 'mixmymeals' ),
					size_format( self::max_bytes() )
				)
			);
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = media_handle_upload(
			$file_key,
			$recipe_id,
			array(
				'post_title'  => sprintf(
					/* translators: %s: recipe title */
					__( 'Meal photo: %s', 'mixmymeals' ),
					get_the_title( $recipe_id )
				),
				'post_author' => $user_id,
			),
			array(
				'test_form' => false,
				'mimes'     => array(
					'jpg|jpeg|jpe' => 'image/jpeg',
					'png'          => 'image/png',
					'webp'         => 'image/webp',
				),
			)
		);

		if ( is_wp_error( $attachment_id ) ) {
			return new WP_Error( 'mmm_photo_upload', __( 'This file could not be uploaded. Please use a JPG, PNG or WebP image.', 'mixmymeals' ) );
		}

		self::strip_exif( (int) $attachment_id );

		update_post_meta( $attachment_id, self::META_FLAG, 1 );
		update_post_meta( $attachment_id, self::META_APPROVED, self::is_auto_approved() ? '1' : '0' );
		update_post_meta( $attachment_id, self::META_SUB, $sub_id );
		update_post_meta( $attachment_id, self::META_BY, sanitize_text_field( $by_name ) );

		return (int) $attachment_id;
	}

	/**
	 * Re-saves the uploaded original through the WP image editor, which drops
	 * embedded metadata (EXIF/GPS location) that a visitor photo may carry.
	 * Best-effort: on any failure the original is left as uploaded.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	private static function strip_exif( int $attachment_id ): void {

		$path = get_attached_file( $attachment_id );
		if ( ! $path || ! file_exists( $path ) ) {
			return;
		}

		$editor = wp_get_image_editor( $path );
		if ( is_wp_error( $editor ) ) {
			return;
		}

		// Keep quality high to minimise re-encode loss; the save drops EXIF.
		$editor->set_quality( 90 );
		$editor->save( $path );
	}

	/**
	 * Returns meal-photo attachment IDs, newest first.
	 *
	 * @param int       $recipe_id Recipe post ID, or 0 for all recipes.
	 * @param bool|null $approved  true = approved only, false = pending only, null = all.
	 * @param int       $limit     Maximum number of IDs (-1 for all).
	 * @return int[]
	 */
	public static function get_photos( int $recipe_id = 0, ?bool $approved = true, int $limit = -1 ): array {

		$meta_query = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'   => self::META_FLAG,
				'value' => 1,
			),
		);

		if ( null !== $approved ) {
			$meta_query[] = array(
				'key'   => self::META_APPROVED,
				'value' => $approved ? '1' : '0',
			);
		}

		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
			'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		);

		if ( $recipe_id > 0 ) {
			$args['post_parent'] = $recipe_id;
		}

		return array_map( 'intval', get_posts( $args ) );
	}

	/**
	 * How many photos the given uploader already added to a recipe (any status).
	 *
	 * @param int $recipe_id Recipe post ID.
	 * @param int $sub_id    Subscription post ID (0 if none).
	 * @param int $user_id   User ID (0 if none).
	 * @return int
	 */
	public static function count_by_uploader( int $recipe_id, int $sub_id, int $user_id ): int {

		if ( $sub_id < 1 && $user_id < 1 ) {
			return 0;
		}

		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_parent'    => $recipe_id,
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => self::META_FLAG,
					'value' => 1,
				),
			),
		);

		if ( $sub_id > 0 ) {
			$args['meta_query'][] = array(
				'key'   => self::META_SUB,
				'value' => $sub_id,
			);
		} else {
			$args['author'] = $user_id;
		}

		return count( get_posts( $args ) );
	}

	/**
	 * Number of photos awaiting approval (for the admin menu bubble).
	 *
	 * @return int
	 */
	public static function pending_count(): int {
		return count( self::get_photos( 0, false ) );
	}

	/*
	----------------------------------------------------------
	 * ADMIN: MODERATION PAGE
	 * ---------------------------------------------------------- */

	/**
	 * Adds the "Meal Photos" moderation page under the Recipes menu, with a
	 * pending-count bubble like the comments menu.
	 *
	 * @return void
	 */
	public function add_admin_page(): void {

		$pending = self::pending_count();
		$label   = __( 'Meal Photos', 'mixmymeals' );

		if ( $pending > 0 ) {
			$label .= sprintf(
				' <span class="awaiting-mod count-%1$d"><span class="pending-count">%1$d</span></span>',
				$pending
			);
		}

		/*
		 * Unter Essenspläne. Die Fotos hängen zwar an Rezepten, kommen aber
		 * meist über den Link in den Abo-Mails eines Plans, und ihre Freigabe
		 * steht im Reiter Essenspläne — die Moderation steht damit neben
		 * ihrer Einstellung. Vorher hing sie unter Rezepte, danach im Menü
		 * MixMyMeals.
		 */
		add_submenu_page(
			'edit.php?post_type=mmm_meal_plan',
			__( 'Meal Photos', 'mixmymeals' ),
			$label,
			'edit_others_posts',
			self::ADMIN_SLUG,
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Builds a nonce-protected admin action URL for one photo.
	 *
	 * @param string $do       approve|unapprove|delete.
	 * @param int    $photo_id Attachment ID.
	 * @return string
	 */
	private function action_url( string $do, int $photo_id ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'mmm_photo_action',
					'do'     => $do,
					'photo'  => $photo_id,
				),
				admin_url( 'admin-post.php' )
			),
			'mmm_photo_action_' . $photo_id
		);
	}

	/**
	 * Handles approve/unapprove/delete from the moderation page.
	 *
	 * @return void
	 */
	public function handle_admin_action(): void {

		if ( ! current_user_can( 'edit_others_posts' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'mixmymeals' ) );
		}

		$photo_id = isset( $_GET['photo'] ) ? absint( wp_unslash( $_GET['photo'] ) ) : 0;
		$do       = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : '';
		$nonce    = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'mmm_photo_action_' . $photo_id )
			|| ! get_post_meta( $photo_id, self::META_FLAG, true ) ) {
			wp_die( esc_html__( 'This link is invalid or has expired.', 'mixmymeals' ) );
		}

		switch ( $do ) {
			case 'approve':
				update_post_meta( $photo_id, self::META_APPROVED, '1' );
				break;
			case 'unapprove':
				update_post_meta( $photo_id, self::META_APPROVED, '0' );
				break;
			case 'delete':
				wp_delete_attachment( $photo_id, true );
				break;
		}

		wp_safe_redirect(
			self::admin_page_url()
		);
		exit;
	}

	/**
	 * Handles the admin upload form: stores each file as a photo of the chosen
	 * recipe and approves it immediately (the uploader is the moderator).
	 *
	 * @return void
	 */
	public function handle_admin_upload(): void {

		if ( ! current_user_can( 'edit_others_posts' ) || ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'mixmymeals' ) );
		}

		check_admin_referer( 'mmm_photo_admin_upload', 'mmm_photo_admin_nonce' );

		$recipe_id = isset( $_POST['mmm_recipe_id'] ) ? absint( wp_unslash( $_POST['mmm_recipe_id'] ) ) : 0;
		$by_name   = isset( $_POST['mmm_photo_by'] ) ? sanitize_text_field( wp_unslash( $_POST['mmm_photo_by'] ) ) : '';

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- file arrays are validated per entry in add_photo().
		$files = isset( $_FILES['mmm_photos'] ) && is_array( $_FILES['mmm_photos']['name'] ?? null ) ? $_FILES['mmm_photos'] : null;

		$errors   = array();
		$uploaded = 0;

		if ( get_post_type( $recipe_id ) !== self::RECIPE_POST_TYPE ) {
			$errors[] = __( 'Please choose a recipe.', 'mixmymeals' );
		} elseif ( null === $files || '' === implode( '', $files['name'] ) ) {
			$errors[] = __( 'Please choose at least one photo.', 'mixmymeals' );
		} else {
			foreach ( array_keys( $files['name'] ) as $i ) {
				if ( '' === $files['name'][ $i ] ) {
					continue;
				}

				// Reshape the multi-file input into the single-file entry add_photo() expects.
				$_FILES['mmm_photo_single'] = array(
					'name'     => $files['name'][ $i ],
					'type'     => $files['type'][ $i ],
					'tmp_name' => $files['tmp_name'][ $i ],
					'error'    => $files['error'][ $i ],
					'size'     => $files['size'][ $i ],
				);

				$result = self::add_photo( $recipe_id, 'mmm_photo_single', $by_name, 0, get_current_user_id() );

				if ( is_wp_error( $result ) ) {
					$errors[] = sprintf( '%s: %s', sanitize_file_name( $files['name'][ $i ] ), $result->get_error_message() );
				} else {
					// Admin uploads skip moderation regardless of the auto-approve option.
					update_post_meta( $result, self::META_APPROVED, '1' );
					++$uploaded;
				}
			}
		}

		if ( ! empty( $errors ) ) {
			set_transient( 'mmm_photo_admin_errors_' . get_current_user_id(), $errors, MINUTE_IN_SECONDS );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'uploaded' => $uploaded,
					'recipe'   => $recipe_id,
				),
				self::admin_page_url()
			)
		);
		exit;
	}

	/**
	 * Renders the moderation page: pending photos first, then the latest
	 * approved ones (so an approval can be revoked).
	 *
	 * @return void
	 */
	public function render_admin_page(): void {

		$pending  = self::get_photos( 0, false );
		$approved = self::get_photos( 0, true, 100 );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Meal Photos', 'mixmymeals' ); ?></h1>

			<?php $this->render_upload_section(); ?>

			<h2><?php esc_html_e( 'Awaiting approval', 'mixmymeals' ); ?></h2>
			<?php if ( empty( $pending ) ) : ?>
				<p><?php esc_html_e( 'No photos are waiting for approval.', 'mixmymeals' ); ?></p>
			<?php else : ?>
				<?php $this->render_photo_table( $pending, false ); ?>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Approved', 'mixmymeals' ); ?></h2>
			<?php if ( empty( $approved ) ) : ?>
				<p><?php esc_html_e( 'No photos have been approved yet.', 'mixmymeals' ); ?></p>
			<?php else : ?>
				<?php $this->render_photo_table( $approved, true ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renders the admin upload form plus the result notices of the previous
	 * upload (success count from the URL, error details from a transient).
	 *
	 * @return void
	 */
	private function render_upload_section(): void {

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display-only feedback params set by our own redirect.
		$uploaded        = isset( $_GET['uploaded'] ) ? absint( wp_unslash( $_GET['uploaded'] ) ) : 0;
		$selected_recipe = isset( $_GET['recipe'] ) ? absint( wp_unslash( $_GET['recipe'] ) ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$errors = get_transient( 'mmm_photo_admin_errors_' . get_current_user_id() );
		if ( is_array( $errors ) ) {
			delete_transient( 'mmm_photo_admin_errors_' . get_current_user_id() );
		} else {
			$errors = array();
		}

		if ( $uploaded > 0 ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of uploaded photos */
							_n( '%d photo uploaded and approved.', '%d photos uploaded and approved.', $uploaded, 'mixmymeals' ),
							$uploaded
						)
					);
					?>
				</p>
			</div>
			<?php
		}

		foreach ( $errors as $error ) {
			?>
			<div class="notice notice-error is-dismissible">
				<p><?php echo esc_html( $error ); ?></p>
			</div>
			<?php
		}

		$recipes = get_posts(
			array(
				'post_type'      => self::RECIPE_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		?>
		<h2><?php esc_html_e( 'Upload photos', 'mixmymeals' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Photos uploaded here are approved immediately and appear in the recipe gallery.', 'mixmymeals' ); ?></p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
			<input type="hidden" name="action" value="mmm_photo_admin_upload" />
			<?php wp_nonce_field( 'mmm_photo_admin_upload', 'mmm_photo_admin_nonce' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="mmm_recipe_id"><?php esc_html_e( 'Recipe', 'mixmymeals' ); ?></label>
					</th>
					<td>
						<select name="mmm_recipe_id" id="mmm_recipe_id" required>
							<option value=""><?php esc_html_e( '— Select recipe —', 'mixmymeals' ); ?></option>
							<?php foreach ( $recipes as $recipe ) : ?>
								<option value="<?php echo esc_attr( (string) $recipe->ID ); ?>" <?php selected( $selected_recipe, $recipe->ID ); ?>>
									<?php echo esc_html( get_the_title( $recipe ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="mmm_photos"><?php esc_html_e( 'Photos', 'mixmymeals' ); ?></label>
					</th>
					<td>
						<input type="file" name="mmm_photos[]" id="mmm_photos" multiple accept="image/jpeg,image/png,image/webp" required />
						<p class="description">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: maximum file size, e.g. "10 MB" */
									__( 'JPG, PNG or WebP. Maximum size per photo: %s.', 'mixmymeals' ),
									size_format( self::max_bytes() )
								)
							);
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="mmm_photo_by"><?php esc_html_e( 'Displayed name (optional)', 'mixmymeals' ); ?></label>
					</th>
					<td>
						<input type="text" name="mmm_photo_by" id="mmm_photo_by" class="regular-text" />
						<p class="description"><?php esc_html_e( 'Shown as the uploader of these photos.', 'mixmymeals' ); ?></p>
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Upload photos', 'mixmymeals' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Renders one moderation table.
	 *
	 * @param int[] $photo_ids   Attachment IDs.
	 * @param bool  $is_approved Whether these photos are approved.
	 * @return void
	 */
	private function render_photo_table( array $photo_ids, bool $is_approved ): void {
		?>
		<table class="widefat striped mmm-photo-moderation">
			<thead>
			<tr>
				<th><?php esc_html_e( 'Photo', 'mixmymeals' ); ?></th>
				<th><?php esc_html_e( 'Recipe', 'mixmymeals' ); ?></th>
				<th><?php esc_html_e( 'Uploaded by', 'mixmymeals' ); ?></th>
				<th><?php esc_html_e( 'Date', 'mixmymeals' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'mixmymeals' ); ?></th>
			</tr>
			</thead>
			<tbody>
			<?php foreach ( $photo_ids as $photo_id ) : ?>
				<?php
				$recipe_id = (int) get_post_field( 'post_parent', $photo_id );
				$by        = (string) get_post_meta( $photo_id, self::META_BY, true );
				$full_url  = (string) wp_get_attachment_url( $photo_id );
				?>
				<tr>
					<td>
						<a href="<?php echo esc_url( $full_url ); ?>" target="_blank" rel="noopener">
							<?php echo wp_get_attachment_image( $photo_id, array( 120, 120 ) ); ?>
						</a>
					</td>
					<td>
						<?php if ( $recipe_id ) : ?>
							<a href="<?php echo esc_url( (string) get_edit_post_link( $recipe_id ) ); ?>">
								<?php echo esc_html( get_the_title( $recipe_id ) ); ?>
							</a>
						<?php else : ?>
							&mdash;
						<?php endif; ?>
					</td>
					<td><?php echo '' !== $by ? esc_html( $by ) : '&mdash;'; ?></td>
					<td><?php echo esc_html( get_the_date( '', $photo_id ) ); ?></td>
					<td>
						<?php if ( $is_approved ) : ?>
							<a class="button" href="<?php echo esc_url( $this->action_url( 'unapprove', $photo_id ) ); ?>">
								<?php esc_html_e( 'Unapprove', 'mixmymeals' ); ?>
							</a>
						<?php else : ?>
							<a class="button button-primary" href="<?php echo esc_url( $this->action_url( 'approve', $photo_id ) ); ?>">
								<?php esc_html_e( 'Approve', 'mixmymeals' ); ?>
							</a>
						<?php endif; ?>
						<a class="button button-link-delete" href="<?php echo esc_url( $this->action_url( 'delete', $photo_id ) ); ?>" onclick="return window.confirm('<?php echo esc_js( __( 'Delete this photo permanently?', 'mixmymeals' ) ); ?>');">
							<?php esc_html_e( 'Delete', 'mixmymeals' ); ?>
						</a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
