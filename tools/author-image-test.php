<?php
/**
 * Author-box picture of WordPress authors (3.76.0): profile field round trip,
 * accounts without upload_files, deleted attachments, external authors.
 *
 * Creates its own users (zz_*), posts (T …) and images and deletes them again.
 *
 *   wp --exec="define('DB_HOST','localhost:<socket>');" \
 *      eval-file wp-content/plugins/mixmymeals/tools/author-image-test.php
 *
 * @package MixMyMeals
 */
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

$fail = 0;
$ok   = function ( $cond, $label ) use ( &$fail ) {
	echo ( $cond ? 'ok   ' : 'FAIL ' ), $label, "\n";
	if ( ! $cond ) { $fail++; }
};
$box = function ( $pid ) {
	$GLOBALS['post'] = get_post( $pid );
	setup_postdata( $GLOBALS['post'] );
	return do_shortcode( '[mmm_recipe_author_box]' );
};
$make_image = function ( $name ) {
	$up   = wp_upload_dir();
	$file = $up['path'] . "/$name.png";
	$im   = imagecreatetruecolor( 200, 200 );
	imagepng( $im, $file );
	$id = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => 'T ' . $name, 'post_status' => 'inherit' ), $file );
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
	return $id;
};

$img  = $make_image( 'zz-user-img' );
$img2 = $make_image( 'zz-user-img2' );
$doc  = wp_insert_post( array( 'post_type' => 'post', 'post_title' => 'T kein Bild', 'post_status' => 'publish' ) );

$editor  = wp_insert_user( array( 'user_login' => 'zz_editor', 'user_pass' => wp_generate_password(), 'role' => 'editor', 'display_name' => 'ZZ Redakteur', 'user_email' => 'zz_editor@example.invalid' ) );
$visitor = wp_insert_user( array( 'user_login' => 'zz_visitor', 'user_pass' => wp_generate_password(), 'role' => 'subscriber', 'display_name' => 'ZZ Besucher', 'user_email' => 'zz_visitor@example.invalid' ) );
$admin   = get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0];
wp_set_current_user( $admin->ID );

$r_ed  = wp_insert_post( array( 'post_type' => 'mmm_recipe', 'post_title' => 'T Bild Redakteur', 'post_status' => 'publish', 'post_author' => $editor ) );
$r_vis = wp_insert_post( array( 'post_type' => 'mmm_recipe', 'post_title' => 'T Bild Besucher', 'post_status' => 'publish', 'post_author' => $visitor ) );

// 1. No picture yet: box without media block, never a Gravatar.
$h = $box( $r_ed );
$ok( str_contains( $h, 'ZZ Redakteur' ) && ! str_contains( $h, '<img' ), 'no picture: name, no img' );
$ok( ! str_contains( $h, 'gravatar' ), 'no gravatar' );

// 2. Round trip through the profile save callback.
$_POST = array(
	'mmm_author_user_image_nonce' => wp_create_nonce( 'mmm_author_user_image' ),
	'mmm_author_image'            => (string) $img,
);
Mixmymeals_Author::save_user_fields( $editor );
$ok( Mixmymeals_Author::get_user_image_id( $editor ) === $img, 'callback stores picture' );
$h = $box( $r_ed );
$ok( str_contains( $h, 'mmm-author-box__avatar' ) && str_contains( $h, 'zz-user-img' ) && str_contains( $h, 'alt="ZZ Redakteur"' ), 'box shows picture' );

// 3. Profile field renders with the stored picture and the shared picker classes.
ob_start();
Mixmymeals_Author::render_user_fields( get_userdata( $editor ) );
$f = ob_get_clean();
$ok( str_contains( $f, 'mmm-author-image-field' ) && str_contains( $f, 'value="' . $img . '"' ) && str_contains( $f, 'mmm_author_user_image_nonce' ), 'profile field rendered' );

// 4. Wrong nonce changes nothing.
$_POST = array( 'mmm_author_user_image_nonce' => 'x', 'mmm_author_image' => (string) $img2 );
Mixmymeals_Author::save_user_fields( $editor );
$ok( Mixmymeals_Author::get_user_image_id( $editor ) === $img, 'bad nonce ignored' );

// 5. A non-image id clears; empty clears.
$_POST = array( 'mmm_author_user_image_nonce' => wp_create_nonce( 'mmm_author_user_image' ), 'mmm_author_image' => (string) $doc );
Mixmymeals_Author::save_user_fields( $editor );
$ok( Mixmymeals_Author::get_user_image_id( $editor ) === 0 && '' === get_user_meta( $editor, '_mmm_author_image', true ), 'non-image clears' );
Mixmymeals_Author::set_user_image_id( $editor, $img );
$_POST = array( 'mmm_author_user_image_nonce' => wp_create_nonce( 'mmm_author_user_image' ), 'mmm_author_image' => '' );
Mixmymeals_Author::save_user_fields( $editor );
$ok( '' === get_user_meta( $editor, '_mmm_author_image', true ), 'empty clears row' );

// 6. Visitor account: no field, not stored, not shown even if forced into meta.
ob_start();
Mixmymeals_Author::render_user_fields( get_userdata( $visitor ) );
$ok( '' === ob_get_clean(), 'visitor: no field' );
Mixmymeals_Author::set_user_image_id( $visitor, $img );
$ok( '' === get_user_meta( $visitor, '_mmm_author_image', true ), 'visitor: not stored' );
update_user_meta( $visitor, '_mmm_author_image', $img );
$h = $box( $r_vis );
$ok( str_contains( $h, 'ZZ Besucher' ) && ! str_contains( $h, '<img' ), 'visitor: forced meta not shown' );

// 7. Deleted attachment reads as none.
Mixmymeals_Author::set_user_image_id( $editor, $img2 );
wp_delete_attachment( $img2, true );
$ok( Mixmymeals_Author::get_user_image_id( $editor ) === 0 && ! str_contains( $box( $r_ed ), '<img' ), 'deleted attachment: no img' );

// 8. External author keeps their own picture, not the account's.
Mixmymeals_Author::set_user_image_id( $editor, $img );
$ext = $make_image( 'zz-ext-img' );
Mixmymeals_Post_Meta::save_author_data( $r_ed, array( 'name' => 'ZZ Extern', 'image' => $ext ) );
$h = $box( $r_ed );
$ok( str_contains( $h, 'zz-ext-img' ) && ! str_contains( $h, 'zz-user-img' ) && str_contains( $h, 'ZZ Extern' ), 'external author: own picture' );
Mixmymeals_Post_Meta::save_author_data( $r_ed, array( 'name' => 'ZZ Extern2' ) );
$ok( ! str_contains( $box( $r_ed ), '<img' ), 'external author without picture: no fallback to account' );

// Cleanup.
$_POST = array();
foreach ( array( $r_ed, $r_vis ) as $p ) {
	foreach ( wp_get_object_terms( $p, 'mmm_author', array( 'fields' => 'ids' ) ) as $t ) { wp_delete_term( $t, 'mmm_author' ); }
	wp_delete_post( $p, true );
}
foreach ( get_terms( array( 'taxonomy' => 'mmm_author', 'hide_empty' => false, 'search' => 'ZZ ' ) ) as $t ) { wp_delete_term( $t->term_id, 'mmm_author' ); }
wp_delete_post( $doc, true );
wp_delete_attachment( $img, true );
wp_delete_attachment( $ext, true );
wp_delete_user( $editor );
wp_delete_user( $visitor );

echo $fail ? "\n$fail FAILED\n" : "\nall passed\n";
