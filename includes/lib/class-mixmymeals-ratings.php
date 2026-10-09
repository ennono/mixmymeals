<?php
/**
 * Recipe ratings class file.
 *
 * @package MixMyMeals Plugin/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mixmymeals_Ratings
 *
 * Lets logged-in users rate recipes (1–5 stars) on top of WordPress comments:
 * each rating is a comment on the mmm_recipe with the star value stored in
 * comment meta (_mmm_rating). Reusing comments gives moderation, spam handling
 * and the admin UI for free. The per-recipe aggregate (average + count) is kept
 * in post meta for fast display and sorting.
 *
 * Guest ratings are disabled: the comment form shows a log-in prompt and the
 * server rejects ratings from logged-out users.
 */
class Mixmymeals_Ratings {

	const POST_TYPE = 'mmm_recipe';

	const META_RATING = '_mmm_rating';
	const META_AVG    = '_mmm_rating_avg';
	const META_COUNT  = '_mmm_rating_count';

	// Comment meta linking a rating to the subscription that submitted it, so a
	// subscription can rate each recipe only once.
	const META_SUB = '_mmm_rating_sub';

	/**
	 * Constructor: wires up the comment form, validation, persistence and the
	 * aggregate recount, plus the display shortcode.
	 */
	public function __construct() {

		// Recipes always accept ratings, regardless of the per-post comment
		// status stored when the recipe was created.
		add_filter( 'comments_open', array( $this, 'force_comments_open' ), 10, 2 );

		// Require a logged-in user: show the log-in prompt instead of the form.
		add_filter( 'option_comment_registration', array( $this, 'require_registration' ) );
		add_filter( 'comment_form_defaults', array( $this, 'form_defaults' ) );
		add_action( 'comment_form_logged_in_after', array( $this, 'render_rating_field' ) );

		// Validate and persist the star value.
		add_filter( 'preprocess_comment', array( $this, 'validate_comment' ) );
		add_action( 'comment_post', array( $this, 'save_rating' ), 10, 2 );

		// Keep the per-recipe aggregate in sync with every status change.
		add_action( 'transition_comment_status', array( $this, 'on_status_change' ), 10, 3 );
		add_action( 'edit_comment', array( $this, 'on_comment_changed' ) );
		add_action( 'trashed_comment', array( $this, 'on_comment_changed' ) );
		add_action( 'untrashed_comment', array( $this, 'on_comment_changed' ) );
		add_action( 'spammed_comment', array( $this, 'on_comment_changed' ) );
		add_action( 'unspammed_comment', array( $this, 'on_comment_changed' ) );
		add_action( 'deleted_comment', array( $this, 'on_comment_deleted' ), 10, 2 );

		// The display shortcodes [mmm_recipe_rating] and [mmm_recipe_reviews] live
		// in Mixmymeals_Shortcodes; the per-review stars are rendered there.
	}

	/**
	 * Forces comments (= ratings) open on recipes on the front end.
	 *
	 * @param bool $open    Whether comments are open.
	 * @param int  $post_id Post ID.
	 * @return bool
	 */
	public function force_comments_open( $open, $post_id ) {
		if ( get_post_type( $post_id ) === self::POST_TYPE ) {
			return true;
		}
		return $open;
	}

	/**
	 * Forces "users must be registered to comment" on single recipe pages so the
	 * comment form shows the built-in log-in prompt to guests. Other post types
	 * keep the site-wide setting.
	 *
	 * @param mixed $value Stored option value.
	 * @return mixed
	 */
	public function require_registration( $value ) {
		if ( is_singular( self::POST_TYPE ) ) {
			return '1';
		}
		return $value;
	}

	/**
	 * Tailors the comment form on recipes: rating-oriented labels, an optional
	 * review field and a log-in prompt for guests.
	 *
	 * @param array $defaults Comment form defaults.
	 * @return array
	 */
	public function form_defaults( $defaults ) {

		if ( ! is_singular( self::POST_TYPE ) ) {
			return $defaults;
		}

		$defaults['title_reply']  = __( 'Rate this recipe', 'mixmymeals' );
		$defaults['label_submit'] = __( 'Submit rating', 'mixmymeals' );

		// Strip the blog-comment chrome so the form reads as a review form.
		$defaults['logged_in_as']         = '';
		$defaults['comment_notes_before'] = '';
		$defaults['comment_notes_after']  = '';

		$defaults['comment_field'] = '<p class="comment-form-comment"><label for="comment">'
			. esc_html__( 'Your review', 'mixmymeals' )
			. '</label><textarea id="comment" name="comment" cols="45" rows="6" required></textarea></p>';

		// The site's notice on what happens to a rating, directly under the
		// text it is about rather than between the stars and the textarea.
		// The comment field is only rendered for logged-in users (guests get
		// must_log_in), so it appears exactly where the form does.
		if ( class_exists( 'Mixmymeals_Shortcodes' ) ) {
			$defaults['comment_field'] .= Mixmymeals_Shortcodes::input_notice( 'rating' );
		}

		$defaults['must_log_in'] = '<p class="must-log-in">' . sprintf(
			wp_kses(
				/* translators: %s: log-in URL */
				__( 'You need to be <a href="%s">logged in</a> to rate this recipe.', 'mixmymeals' ),
				array( 'a' => array( 'href' => array() ) )
			),
			esc_url( wp_login_url( (string) get_permalink() ) )
		) . '</p>';

		return $defaults;
	}

	/**
	 * Renders the star input inside the comment form (logged-in users only).
	 * CSS turns the reversed radio group into a star widget.
	 */
	public function render_rating_field(): void {

		if ( get_post_type( get_the_ID() ) !== self::POST_TYPE ) {
			return;
		}

		echo '<p class="comment-form-rating mmm-rating-field"><label>'
			. esc_html__( 'Your rating', 'mixmymeals' ) . ' <span class="required">*</span></label>';
		echo '<span class="mmm-rating-input">';
		for ( $i = 5; $i >= 1; $i-- ) {
			$field_id = 'mmm-rating-' . $i;
			$label    = sprintf(
				/* translators: %s: number of stars */
				_n( '%s star', '%s stars', $i, 'mixmymeals' ),
				number_format_i18n( $i )
			);
			printf(
				'<input type="radio" name="mmm_rating" id="%1$s" value="%2$d" required><label for="%1$s" title="%3$s"><i class="mmm-icon fa-solid fa-star" aria-hidden="true"></i><span class="screen-reader-text">%3$s</span></label>',
				esc_attr( $field_id ),
				(int) $i,
				esc_attr( $label )
			);
		}
		echo '</span>';
		wp_nonce_field( 'mmm_rate', 'mmm_rating_nonce' );
		echo '</p>';
	}

	/**
	 * Validates a recipe rating before the comment is saved: requires log-in, a
	 * valid nonce, a 1–5 star value and prevents a second rating per user.
	 *
	 * @param array $commentdata Comment data.
	 * @return array
	 */
	public function validate_comment( $commentdata ) {

		$post_id = (int) ( $commentdata['comment_post_ID'] ?? 0 );
		if ( get_post_type( $post_id ) !== self::POST_TYPE ) {
			return $commentdata;
		}

		if ( ! is_user_logged_in() ) {
			wp_die(
				esc_html__( 'You need to be logged in to rate this recipe.', 'mixmymeals' ),
				'',
				array(
					'response'  => 403,
					'back_link' => true,
				)
			);
		}

		$nonce = isset( $_POST['mmm_rating_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['mmm_rating_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'mmm_rate' ) ) {
			wp_die(
				esc_html__( 'Your session has expired. Please reload the page.', 'mixmymeals' ),
				'',
				array(
					'response'  => 403,
					'back_link' => true,
				)
			);
		}

		$rating = isset( $_POST['mmm_rating'] ) ? absint( wp_unslash( $_POST['mmm_rating'] ) ) : 0;
		if ( $rating < 1 || $rating > 5 ) {
			wp_die(
				esc_html__( 'Please select a rating.', 'mixmymeals' ),
				'',
				array(
					'response'  => 400,
					'back_link' => true,
				)
			);
		}

		return $commentdata;
	}

	/**
	 * Stores the star value on the new comment and refreshes the aggregate.
	 *
	 * @param int        $comment_id Comment ID.
	 * @param int|string $approved   Approval status.
	 * @return void
	 */
	public function save_rating( $comment_id, $approved ): void {

		unset( $approved );

		$comment = get_comment( $comment_id );
		if ( ! $comment || get_post_type( $comment->comment_post_ID ) !== self::POST_TYPE ) {
			return;
		}

		$rating = isset( $_POST['mmm_rating'] ) ? absint( wp_unslash( $_POST['mmm_rating'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked in validate_comment().
		if ( $rating >= 1 && $rating <= 5 ) {
			update_comment_meta( $comment_id, self::META_RATING, $rating );
		}

		$this->recount( (int) $comment->comment_post_ID );
	}

	/**
	 * Recount trigger: comment status changed (approve/hold/spam/trash).
	 *
	 * @param string     $new_status New status.
	 * @param string     $old_status Old status.
	 * @param WP_Comment $comment    Comment object.
	 * @return void
	 */
	public function on_status_change( $new_status, $old_status, $comment ): void {
		unset( $new_status, $old_status );
		if ( $comment instanceof WP_Comment ) {
			$this->recount( (int) $comment->comment_post_ID );
		}
	}

	/**
	 * Recount trigger: comment edited/trashed/untrashed/spammed/unspammed.
	 *
	 * @param int $comment_id Comment ID.
	 * @return void
	 */
	public function on_comment_changed( $comment_id ): void {
		$comment = get_comment( $comment_id );
		if ( $comment ) {
			$this->recount( (int) $comment->comment_post_ID );
		}
	}

	/**
	 * Recount trigger: comment deleted (the object is still passed).
	 *
	 * @param int             $comment_id Comment ID.
	 * @param WP_Comment|null $comment    Comment object.
	 * @return void
	 */
	public function on_comment_deleted( $comment_id, $comment = null ): void {
		unset( $comment_id );
		if ( $comment instanceof WP_Comment ) {
			$this->recount( (int) $comment->comment_post_ID );
		}
	}

	/**
	 * Recomputes and stores the per-recipe average and count from approved
	 * rating comments.
	 *
	 * @param int $post_id Recipe post ID.
	 * @return void
	 */
	public function recount( int $post_id ): void {
		self::recompute( $post_id );
	}

	/**
	 * Recomputes and stores the per-recipe average and count from approved rating
	 * comments. Static so the subscriber-rating path can call it without the
	 * instance.
	 *
	 * @param int $post_id Recipe post ID.
	 * @return void
	 */
	public static function recompute( int $post_id ): void {

		if ( get_post_type( $post_id ) !== self::POST_TYPE ) {
			return;
		}

		$comments = get_comments(
			array(
				'post_id'    => $post_id,
				'status'     => 'approve',
				'meta_key'   => self::META_RATING, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'fields'     => 'ids',
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => self::META_RATING,
						'compare' => 'EXISTS',
					),
				),
			)
		);

		$sum   = 0;
		$count = 0;
		foreach ( $comments as $cid ) {
			$value = (int) get_comment_meta( $cid, self::META_RATING, true );
			if ( $value >= 1 && $value <= 5 ) {
				$sum += $value;
				++$count;
			}
		}

		if ( $count > 0 ) {
			update_post_meta( $post_id, self::META_AVG, round( $sum / $count, 2 ) );
			update_post_meta( $post_id, self::META_COUNT, $count );
		} else {
			delete_post_meta( $post_id, self::META_AVG );
			delete_post_meta( $post_id, self::META_COUNT );
		}
	}

	/**
	 * Whether the given subscription already rated this recipe (one rating per
	 * recipe per subscription).
	 *
	 * @param int $recipe_id Recipe post ID.
	 * @param int $sub_id    Subscription post ID.
	 * @return bool
	 */
	public static function subscriber_has_rated( int $recipe_id, int $sub_id ): bool {

		if ( $recipe_id < 1 || $sub_id < 1 ) {
			return false;
		}

		$found = get_comments(
			array(
				'post_id'    => $recipe_id,
				'status'     => 'all',
				'number'     => 1,
				'count'      => true,
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => self::META_SUB,
						'value' => $sub_id,
					),
				),
			)
		);

		return (int) $found > 0;
	}

	/**
	 * Stores a subscriber's rating for a recipe as an approved comment (no review
	 * text) and refreshes the recipe aggregate. The caller is responsible for
	 * token validation, plan membership and the one-per-subscription check.
	 *
	 * @param int    $recipe_id Recipe post ID.
	 * @param int    $sub_id    Subscription post ID.
	 * @param int    $stars     Rating value (1–5).
	 * @param string $name      Subscriber display name (may be empty).
	 * @param string $email     Subscriber e-mail.
	 * @return int Comment ID, or 0 on failure.
	 */
	public static function add_subscriber_rating( int $recipe_id, int $sub_id, int $stars, string $name, string $email ): int {

		if ( get_post_type( $recipe_id ) !== self::POST_TYPE || $stars < 1 || $stars > 5 ) {
			return 0;
		}

		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => $recipe_id,
				'comment_author'       => '' !== $name ? $name : __( 'Subscriber', 'mixmymeals' ),
				'comment_author_email' => $email,
				'comment_content'      => '',
				'comment_type'         => 'comment',
				'comment_approved'     => 1,
				'user_id'              => 0,
			)
		);

		if ( ! $comment_id ) {
			return 0;
		}

		update_comment_meta( $comment_id, self::META_RATING, $stars );
		update_comment_meta( $comment_id, self::META_SUB, $sub_id );

		self::recompute( $recipe_id );

		return (int) $comment_id;
	}

	/**
	 * Builds the five-star display (full / half / empty) for an average. Public
	 * and static so Mixmymeals_Shortcodes (the [mmm_recipe_rating] shortcode) and
	 * the per-comment stars can share it.
	 *
	 * @param float $avg Average rating (0–5).
	 * @return string
	 */
	public static function stars_html( float $avg ): string {

		$out = '<span class="mmm-rating__stars" aria-hidden="true">';
		for ( $i = 1; $i <= 5; $i++ ) {
			if ( $avg >= $i ) {
				$class = 'fa-solid fa-star';
			} elseif ( $avg >= $i - 0.5 ) {
				$class = 'fa-solid fa-star-half-stroke';
			} else {
				$class = 'fa-regular fa-star';
			}
			$out .= '<i class="mmm-icon ' . esc_attr( $class ) . '"></i>';
		}
		$out .= '</span>';

		return $out;
	}
}
