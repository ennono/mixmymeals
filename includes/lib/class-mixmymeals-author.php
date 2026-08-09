<?php
/**
 * External authors as a taxonomy.
 *
 * @package MixMyMeals/Author
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Storage and resolution for the external author of a recipe or meal plan.
 *
 * An author used to be seven meta fields repeated on every single post. That
 * works for a one-off credit but not for a guest-author programme: the same
 * person had to be re-typed for every contribution, a typo silently created a
 * second person, and nobody had a page collecting what they wrote. As a term
 * an author exists once and gets an archive for free — and that archive is the
 * thing a contributor links to from their own site.
 *
 * The term itself carries the name (term name) and the bio (term description,
 * the core field made for exactly this). Everything else is term meta under the
 * same keys the post meta used, so nothing in the reading code had to learn new
 * names.
 *
 * Post meta stays readable as a fallback (see get_post_data()): an install that
 * has not run the migration yet, or a post that arrived afterwards from a
 * backup, must not silently lose its credit.
 */
class Mixmymeals_Author {

	/**
	 * Taxonomy key.
	 *
	 * @var string
	 */
	const TAXONOMY = 'mmm_author';

	/**
	 * Term meta keys. Deliberately identical to the post meta keys in
	 * Mixmymeals_Post_Meta — same field, new home.
	 *
	 * @var string
	 */
	const URL       = '_mmm_author_url';
	const LINK_TEXT = '_mmm_author_link_text';
	const NOFOLLOW  = '_mmm_author_nofollow';
	const SOCIAL    = '_mmm_author_social';
	const IMAGE     = '_mmm_author_image';

	/**
	 * Bumped when the migration has to run again.
	 *
	 * @var string
	 */
	const MIGRATION_OPTION = 'mixmymeals_author_taxonomy_version';

	/**
	 * Current migration version.
	 *
	 * @var int
	 */
	const MIGRATION_VERSION = 1;

	/**
	 * Attaches the hooks once per request. Called from mixmymeals.php, the same
	 * pattern as Mixmymeals_Recipe::register_cook_count().
	 *
	 * @return void
	 */
	public static function register(): void {

		static $done = false;

		if ( $done ) {
			return;
		}

		$done = true;

		// Term form fields, add and edit screen.
		add_action( self::TAXONOMY . '_add_form_fields', array( __CLASS__, 'render_add_fields' ) );
		add_action( self::TAXONOMY . '_edit_form_fields', array( __CLASS__, 'render_edit_fields' ) );
		add_action( 'created_' . self::TAXONOMY, array( __CLASS__, 'save_term_fields' ) );
		add_action( 'edited_' . self::TAXONOMY, array( __CLASS__, 'save_term_fields' ) );

		// One-off move of the legacy per-post fields into terms.
		add_action( 'admin_init', array( __CLASS__, 'maybe_migrate' ) );
	}

	/* ---------------------------------------------------------------------
	 * Reading
	 * ------------------------------------------------------------------ */

	/**
	 * The author term of a post, or null when it has none.
	 *
	 * A post can only sensibly have one author, but a taxonomy allows several,
	 * so the first one wins rather than the call failing — a second term is a
	 * data glitch, not a reason to drop the credit entirely.
	 *
	 * @param int $post_id Post ID.
	 * @return WP_Term|null
	 */
	public static function get_post_term( int $post_id ): ?WP_Term {

		if ( ! $post_id ) {
			return null;
		}

		$terms = get_the_terms( $post_id, self::TAXONOMY );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return null;
		}

		return $terms[0] instanceof WP_Term ? $terms[0] : null;
	}

	/**
	 * All stored fields of one author term, in the shape the author box and the
	 * REST API already expect from Mixmymeals_Post_Meta::get_author_data().
	 *
	 * Returns the *raw* link text, so a read-modify-write round trip never
	 * persists the derived host fallback as if it had been typed in.
	 *
	 * @param int $term_id Term ID.
	 * @return array
	 */
	public static function get_term_data( int $term_id ): array {

		$term = get_term( $term_id, self::TAXONOMY );

		if ( ! $term instanceof WP_Term ) {
			return array();
		}

		return array(
			'name'      => $term->name,
			'text'      => trim( (string) $term->description ),
			'url'       => trim( (string) get_term_meta( $term_id, self::URL, true ) ),
			'link_text' => trim( (string) get_term_meta( $term_id, self::LINK_TEXT, true ) ),
			'nofollow'  => '1' === (string) get_term_meta( $term_id, self::NOFOLLOW, true ),
			'social'    => array_values( (array) get_term_meta( $term_id, self::SOCIAL, true ) ),
			'image'     => (int) get_term_meta( $term_id, self::IMAGE, true ),
		);
	}

	/**
	 * The author fields that apply to a post: the assigned term, or an empty
	 * array when the post has no external author.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public static function get_post_data( int $post_id ): array {

		$term = self::get_post_term( $post_id );

		return $term ? self::get_term_data( $term->term_id ) : array();
	}

	/**
	 * Public archive URL of an author, or '' when the term is gone.
	 *
	 * @param int $term_id Term ID.
	 * @return string
	 */
	public static function get_archive_url( int $term_id ): string {

		$link = get_term_link( $term_id, self::TAXONOMY );

		return is_wp_error( $link ) ? '' : (string) $link;
	}

	/* ---------------------------------------------------------------------
	 * Writing
	 * ------------------------------------------------------------------ */

	/**
	 * Finds the author term with this name, creating it when it does not exist.
	 *
	 * Matching is by name rather than slug so that clients writing the author
	 * over REST — where the name is all they send — hit the existing person
	 * instead of piling up near-duplicates.
	 *
	 * @param string $name Author name.
	 * @return int Term ID, 0 when the name was empty or the term could not be made.
	 */
	public static function find_or_create( string $name ): int {

		$name = trim( sanitize_text_field( $name ) );

		if ( '' === $name ) {
			return 0;
		}

		$existing = get_term_by( 'name', $name, self::TAXONOMY );

		if ( $existing instanceof WP_Term ) {
			return (int) $existing->term_id;
		}

		$created = wp_insert_term( $name, self::TAXONOMY );

		if ( is_wp_error( $created ) ) {

			// A parallel request may have created it in the meantime; the error
			// carries the winning term, so use that rather than losing the write.
			$term_id = $created->get_error_data( 'term_exists' );

			return $term_id ? (int) $term_id : 0;
		}

		return (int) $created['term_id'];
	}

	/**
	 * Makes this term the post's author, replacing whatever was assigned before.
	 * A term ID of 0 removes the credit, so the post falls back to its
	 * WordPress author.
	 *
	 * @param int $post_id Post ID.
	 * @param int $term_id Term ID, or 0 to clear.
	 * @return void
	 */
	public static function assign( int $post_id, int $term_id ): void {

		if ( ! $post_id ) {
			return;
		}

		if ( ! $term_id ) {
			wp_set_object_terms( $post_id, array(), self::TAXONOMY, false );
			return;
		}

		wp_set_object_terms( $post_id, array( $term_id ), self::TAXONOMY, false );
	}

	/**
	 * Writes the author fields onto a term.
	 *
	 * Destructive by design, exactly like Mixmymeals_Post_Meta::save_author_data():
	 * every supplied key is written, every missing one is cleared. Callers that
	 * want merge semantics (the REST API does) read get_term_data() first and
	 * hand back the merged array.
	 *
	 * @param int   $term_id Term ID.
	 * @param array $data    name, text, url, link_text, nofollow, social, image.
	 * @return void
	 */
	public static function save_term_data( int $term_id, array $data ): void {

		$term = get_term( $term_id, self::TAXONOMY );

		if ( ! $term instanceof WP_Term ) {
			return;
		}

		$core = array();

		if ( isset( $data['name'] ) && '' !== trim( (string) $data['name'] ) ) {
			$core['name'] = sanitize_text_field( $data['name'] );
		}

		if ( array_key_exists( 'text', $data ) ) {
			$core['description'] = wp_kses_post( (string) $data['text'] );
		}

		if ( ! empty( $core ) ) {
			wp_update_term( $term_id, self::TAXONOMY, $core );
		}

		$map = array(
			self::URL       => isset( $data['url'] ) ? esc_url_raw( $data['url'] ) : '',
			self::LINK_TEXT => isset( $data['link_text'] ) ? sanitize_text_field( $data['link_text'] ) : '',
			self::NOFOLLOW  => ! empty( $data['nofollow'] ) ? '1' : '',
		);

		foreach ( $map as $key => $value ) {

			if ( '' === trim( (string) $value ) ) {
				delete_term_meta( $term_id, $key );
				continue;
			}

			update_term_meta( $term_id, $key, $value );
		}

		$image = isset( $data['image'] ) ? absint( $data['image'] ) : 0;

		if ( $image ) {
			update_term_meta( $term_id, self::IMAGE, $image );
		} else {
			delete_term_meta( $term_id, self::IMAGE );
		}

		$social = isset( $data['social'] )
			? Mixmymeals_Post_Meta::parse_author_social( $data['social'] )
			: array();

		if ( empty( $social ) ) {
			delete_term_meta( $term_id, self::SOCIAL );
			return;
		}

		update_term_meta( $term_id, self::SOCIAL, $social );
	}

	/* ---------------------------------------------------------------------
	 * Migration
	 * ------------------------------------------------------------------ */

	/**
	 * Runs the one-off move of the legacy per-post author fields into terms.
	 *
	 * @return void
	 */
	public static function maybe_migrate(): void {

		if ( (int) get_option( self::MIGRATION_OPTION ) >= self::MIGRATION_VERSION ) {
			return;
		}

		self::migrate();

		update_option( self::MIGRATION_OPTION, self::MIGRATION_VERSION, false );
	}

	/**
	 * Moves every post that carries the legacy author meta onto a term.
	 *
	 * Posts sharing a name end up on one term, which is the entire point of the
	 * move — but it also means the first post to be processed decides the bio,
	 * link and picture. Later posts only fill fields the term does not have yet,
	 * so a fuller record never loses to a sparser one.
	 *
	 * The legacy meta is deliberately left in place. It costs a few rows and is
	 * the only way back if a name collision merged two people who happened to
	 * share one; get_post_data() reads the term, so the stale rows are inert.
	 *
	 * @return array Counts: posts, terms, skipped.
	 */
	public static function migrate(): array {

		$stats = array(
			'posts'   => 0,
			'terms'   => 0,
			'skipped' => 0,
		);

		$post_ids = get_posts(
			array(
				'post_type'        => array( 'mmm_recipe', 'mmm_meal_plan' ),
				'post_status'      => 'any',
				'numberposts'      => -1,
				'fields'           => 'ids',
				'suppress_filters' => false,
				'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one-off migration.
					array(
						'key'     => Mixmymeals_Post_Meta::AUTHOR_NAME,
						'value'   => '',
						'compare' => '!=',
					),
				),
			)
		);

		foreach ( $post_ids as $post_id ) {

			$post_id = (int) $post_id;

			// Already moved: never overwrite a term assignment that exists.
			if ( self::get_post_term( $post_id ) ) {
				++$stats['skipped'];
				continue;
			}

			$legacy = Mixmymeals_Post_Meta::get_author_data( $post_id );

			if ( '' === trim( (string) $legacy['name'] ) ) {
				++$stats['skipped'];
				continue;
			}

			$known   = (bool) get_term_by( 'name', $legacy['name'], self::TAXONOMY );
			$term_id = self::find_or_create( $legacy['name'] );

			if ( ! $term_id ) {
				++$stats['skipped'];
				continue;
			}

			if ( ! $known ) {
				++$stats['terms'];
			}

			self::save_term_data( $term_id, self::merge_into_term( $term_id, $legacy ) );
			self::assign( $post_id, $term_id );

			++$stats['posts'];
		}

		return $stats;
	}

	/**
	 * Fills the gaps of an existing term from a legacy record without
	 * overwriting anything already stored on it.
	 *
	 * @param int   $term_id Term ID.
	 * @param array $legacy  Legacy author data of one post.
	 * @return array
	 */
	private static function merge_into_term( int $term_id, array $legacy ): array {

		$current = self::get_term_data( $term_id );

		if ( empty( $current ) ) {
			return $legacy;
		}

		foreach ( array( 'text', 'url', 'link_text' ) as $key ) {
			if ( '' === trim( (string) $current[ $key ] ) ) {
				$current[ $key ] = $legacy[ $key ];
			}
		}

		if ( empty( $current['social'] ) ) {
			$current['social'] = $legacy['social'];
		}

		if ( ! $current['image'] ) {
			$current['image'] = $legacy['image'];
		}

		// nofollow is a deliberate choice, not a gap: once any contribution asked
		// for it, it stays on for the author.
		$current['nofollow'] = $current['nofollow'] || ! empty( $legacy['nofollow'] );

		return $current;
	}

	/* ---------------------------------------------------------------------
	 * Term admin screen
	 * ------------------------------------------------------------------ */

	/**
	 * Extra fields on the "add author" form.
	 *
	 * @return void
	 */
	public static function render_add_fields(): void {

		wp_nonce_field( 'mmm_author_term', 'mmm_author_term_nonce' );

		echo '<div class="form-field">'
			. '<label for="mmm_author_url">' . esc_html__( 'Website', 'mixmymeals' ) . '</label>'
			. '<input type="url" name="mmm_author_url" id="mmm_author_url" value="" />'
			. '<p>' . esc_html__( 'Where the author box links to.', 'mixmymeals' ) . '</p>'
			. '</div>';

		echo '<div class="form-field">'
			. '<label for="mmm_author_link_text">' . esc_html__( 'Link text', 'mixmymeals' ) . '</label>'
			. '<input type="text" name="mmm_author_link_text" id="mmm_author_link_text" value="" />'
			. '<p>' . esc_html__( 'Leave empty to use the website\'s domain.', 'mixmymeals' ) . '</p>'
			. '</div>';

		echo '<div class="form-field">'
			. '<label for="mmm_author_social">' . esc_html__( 'Social profiles and contact', 'mixmymeals' ) . '</label>'
			. '<textarea name="mmm_author_social" id="mmm_author_social" rows="4"></textarea>'
			. '<p>' . esc_html__( 'One URL or e-mail address per line.', 'mixmymeals' ) . '</p>'
			. '</div>';

		echo '<div class="form-field">'
			. '<label for="mmm_author_image">' . esc_html__( 'Profile picture', 'mixmymeals' ) . '</label>'
			. '<input type="number" name="mmm_author_image" id="mmm_author_image" value="" min="0" step="1" />'
			. '<p>' . esc_html__( 'Attachment ID of the picture. 0 or empty uses no picture.', 'mixmymeals' ) . '</p>'
			. '</div>';

		echo '<div class="form-field">'
			. '<label><input type="checkbox" name="mmm_author_nofollow" value="1" /> '
			. esc_html__( 'Mark link as nofollow', 'mixmymeals' ) . '</label>'
			. '<p>' . esc_html__( 'Off: the link passes ranking signals (follow). On: rel="nofollow" is added.', 'mixmymeals' ) . '</p>'
			. '</div>';
	}

	/**
	 * Extra fields on the "edit author" form.
	 *
	 * @param WP_Term $term Term being edited.
	 * @return void
	 */
	public static function render_edit_fields( $term ): void {

		if ( ! $term instanceof WP_Term ) {
			return;
		}

		$data = self::get_term_data( (int) $term->term_id );

		wp_nonce_field( 'mmm_author_term', 'mmm_author_term_nonce' );

		echo '<tr class="form-field"><th scope="row">'
			. '<label for="mmm_author_url">' . esc_html__( 'Website', 'mixmymeals' ) . '</label></th>'
			. '<td><input type="url" name="mmm_author_url" id="mmm_author_url" value="' . esc_attr( $data['url'] ) . '" />'
			. '<p class="description">' . esc_html__( 'Where the author box links to.', 'mixmymeals' ) . '</p></td></tr>';

		echo '<tr class="form-field"><th scope="row">'
			. '<label for="mmm_author_link_text">' . esc_html__( 'Link text', 'mixmymeals' ) . '</label></th>'
			. '<td><input type="text" name="mmm_author_link_text" id="mmm_author_link_text" value="' . esc_attr( $data['link_text'] ) . '" />'
			. '<p class="description">' . esc_html__( 'Leave empty to use the website\'s domain.', 'mixmymeals' ) . '</p></td></tr>';

		echo '<tr class="form-field"><th scope="row">'
			. '<label for="mmm_author_social">' . esc_html__( 'Social profiles and contact', 'mixmymeals' ) . '</label></th>'
			. '<td><textarea name="mmm_author_social" id="mmm_author_social" rows="4" cols="50">'
			. esc_textarea( implode( "\n", $data['social'] ) ) . '</textarea>'
			. '<p class="description">' . esc_html__( 'One URL or e-mail address per line.', 'mixmymeals' ) . '</p></td></tr>';

		echo '<tr class="form-field"><th scope="row">'
			. '<label for="mmm_author_image">' . esc_html__( 'Profile picture', 'mixmymeals' ) . '</label></th>'
			. '<td><input type="number" name="mmm_author_image" id="mmm_author_image" value="' . esc_attr( (string) $data['image'] ) . '" min="0" step="1" />';

		if ( $data['image'] ) {
			echo wp_get_attachment_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core markup.
				$data['image'],
				array( 96, 96 ),
				false,
				array( 'style' => 'display:block;margin-top:8px;border-radius:50%;' )
			);
		}

		echo '<p class="description">' . esc_html__( 'Attachment ID of the picture. 0 or empty uses no picture.', 'mixmymeals' ) . '</p></td></tr>';

		echo '<tr class="form-field"><th scope="row">'
			. esc_html__( 'Link attribute', 'mixmymeals' ) . '</th>'
			. '<td><label><input type="checkbox" name="mmm_author_nofollow" value="1" '
			. checked( $data['nofollow'], true, false ) . ' /> '
			. esc_html__( 'Mark link as nofollow', 'mixmymeals' ) . '</label>'
			. '<p class="description">' . esc_html__( 'Off: the link passes ranking signals (follow). On: rel="nofollow" is added.', 'mixmymeals' ) . '</p></td></tr>';
	}

	/**
	 * Persists the extra fields of the term forms.
	 *
	 * Name and bio are not touched here — core has already written them from
	 * its own fields by the time this runs.
	 *
	 * @param int $term_id Term ID.
	 * @return void
	 */
	public static function save_term_fields( $term_id ): void {

		$term_id = (int) $term_id;

		if ( ! isset( $_POST['mmm_author_term_nonce'] )
			|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['mmm_author_term_nonce'] ) ), 'mmm_author_term' ) ) {
			return;
		}

		$taxonomy = get_taxonomy( self::TAXONOMY );

		if ( ! $taxonomy || ! current_user_can( $taxonomy->cap->edit_terms ) ) {
			return;
		}

		$data = self::get_term_data( $term_id );

		if ( empty( $data ) ) {
			return;
		}

		// Only the fields this form owns; name and description stay as core saved them.
		$data['url']       = isset( $_POST['mmm_author_url'] ) ? esc_url_raw( wp_unslash( $_POST['mmm_author_url'] ) ) : '';
		$data['link_text'] = isset( $_POST['mmm_author_link_text'] ) ? sanitize_text_field( wp_unslash( $_POST['mmm_author_link_text'] ) ) : '';
		$data['nofollow']  = ! empty( $_POST['mmm_author_nofollow'] );
		$data['image']     = isset( $_POST['mmm_author_image'] ) ? absint( wp_unslash( $_POST['mmm_author_image'] ) ) : 0;
		$data['social']    = isset( $_POST['mmm_author_social'] )
			? sanitize_textarea_field( wp_unslash( $_POST['mmm_author_social'] ) )
			: '';

		// Leave name and bio to core: passing them back would re-save what it
		// just wrote, and a stale read here would undo the edit.
		unset( $data['name'], $data['text'] );

		self::save_term_data( $term_id, $data );
	}
}
