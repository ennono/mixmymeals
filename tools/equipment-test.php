<?php
/**
 * Round trip through save_equipment_metabox(), the shape the units and meals
 * boxes are tested in: through the callback, not through the setters.
 */

$fails = 0;
$ok    = function ( $label, $cond ) use ( &$fails ) {
	if ( ! $cond ) {
		$fails++;
	}
	echo ( $cond ? 'ok   ' : 'FAIL ' ) . $label . PHP_EOL;
};

wp_set_current_user( 1 );

$recipe_id = wp_insert_post( array( 'post_type' => 'mmm_recipe', 'post_title' => 'ZZ Equip Recipe', 'post_status' => 'publish' ) );
$other_id  = wp_insert_post( array( 'post_type' => 'mmm_recipe', 'post_title' => 'ZZ Equip Recipe 2', 'post_status' => 'publish' ) );

$pan  = Mixmymeals_Equipment::create( 'ZZ Eisenpfanne' );
$pot  = Mixmymeals_Equipment::create( 'ZZ Bräter' );
$meta = new Mixmymeals_Post_Meta();

$post_box = function ( array $fields ) use ( $recipe_id, $meta ) {
	$_POST                         = $fields;
	$_POST['mmm_equipment_nonce']  = wp_create_nonce( 'mmm_equipment_save' );
	$meta->save_equipment_metabox( $recipe_id );
	$_POST = array();
};

$equip_of = function ( $id = null ) use ( $recipe_id ) {
	return array_map( 'intval', ( new Mixmymeals_Recipe( $id ?: $recipe_id ) )->get_equipment() );
};

echo '-- 1. Ticking' . PHP_EOL;
$post_box( array( 'mmm_equipment' => array( $pan->get_id() ) ) );
$ok( 'pan stored', array( $pan->get_id() ) === $equip_of() );

echo '-- 2. Ticking a second, unticking the first' . PHP_EOL;
$post_box( array( 'mmm_equipment' => array( $pot->get_id() ) ) );
$ok( 'only the pot left', array( $pot->get_id() ) === $equip_of() );

echo '-- 3. Renaming' . PHP_EOL;
$post_box( array(
	'mmm_equipment'        => array( $pot->get_id() ),
	'mmm_equipment_titles' => array( $pot->get_id() => 'ZZ Schmortopf' ),
) );
$ok( 'renamed', 'ZZ Schmortopf' === ( new Mixmymeals_Equipment( $pot->get_id() ) )->get_title() );

echo '-- 4. An empty name keeps the stored one' . PHP_EOL;
$post_box( array(
	'mmm_equipment'        => array( $pot->get_id() ),
	'mmm_equipment_titles' => array( $pot->get_id() => '   ' ),
) );
$ok( 'name kept', 'ZZ Schmortopf' === ( new Mixmymeals_Equipment( $pot->get_id() ) )->get_title() );

echo '-- 5. A rename reaches the other recipe too' . PHP_EOL;
( new Mixmymeals_Recipe( $other_id ) )->set_equipment( array( $pot->get_id() ) );
$post_box( array(
	'mmm_equipment'        => array( $pot->get_id() ),
	'mmm_equipment_titles' => array( $pot->get_id() => 'ZZ Römertopf' ),
) );
$ok( 'one post, one name', 'ZZ Römertopf' === get_post( $pot->get_id() )->post_title );
$ok( 'other recipe still points at it', in_array( $pot->get_id(), $equip_of( $other_id ), true ) );

echo '-- 6. The empty row creates and ticks' . PHP_EOL;
$before = wp_count_posts( 'mmm_equipment' )->publish;
$post_box( array( 'mmm_equipment' => array( $pot->get_id() ), 'mmm_equipment_new' => 'ZZ Springform 26 cm' ) );
$new = get_posts( array( 'post_type' => 'mmm_equipment', 'title' => 'ZZ Springform 26 cm', 'posts_per_page' => 1, 'fields' => 'ids', 'post_status' => 'any' ) );
$ok( 'created', 1 === count( $new ) );
$ok( 'ticked on the recipe', in_array( (int) $new[0], $equip_of(), true ) );
$ok( 'the pot is still ticked', in_array( $pot->get_id(), $equip_of(), true ) );

echo '-- 7. The same form posted twice, as the block editor does' . PHP_EOL;
/*
 * The second save carries the checkbox list the first one was rendered with,
 * so it cannot contain what the first save created. That is exactly how the
 * new piece used to lose its tick.
 */
$row = array( 'mmm_equipment' => array( $pot->get_id() ), 'mmm_equipment_new' => 'ZZ Dampfgarer' );
$post_box( $row );
$post_box( $row );
$steamer = get_posts( array( 'post_type' => 'mmm_equipment', 'title' => 'ZZ Dampfgarer', 'posts_per_page' => -1, 'fields' => 'ids', 'post_status' => 'any' ) );
$ok( 'exactly one created', 1 === count( $steamer ) );
$ok( 'and it is still ticked after the second save', in_array( (int) $steamer[0], $equip_of(), true ) );

echo '-- 8. A name that already exists is reused, not duplicated' . PHP_EOL;
$post_box( array( 'mmm_equipment' => array(), 'mmm_equipment_new' => 'ZZ Römertopf' ) );
$ok( 'no second pot', 1 === count( get_posts( array( 'post_type' => 'mmm_equipment', 'title' => 'ZZ Römertopf', 'posts_per_page' => -1, 'fields' => 'ids', 'post_status' => 'any' ) ) ) );
$ok( 'the existing one is ticked', in_array( $pot->get_id(), $equip_of(), true ) );

echo '-- 9. Without the nonce nothing is written' . PHP_EOL;
$_POST = array( 'mmm_equipment' => array(), 'mmm_equipment_titles' => array( $pot->get_id() => 'ZZ Gehackt' ) );
$meta->save_equipment_metabox( $recipe_id );
$_POST = array();
$ok( 'unsigned post ignored', 'ZZ Römertopf' === ( new Mixmymeals_Equipment( $pot->get_id() ) )->get_title() );
$ok( 'selection untouched', in_array( $pot->get_id(), $equip_of(), true ) );

echo '-- 10. Counting in one pass' . PHP_EOL;
$post_box( array( 'mmm_equipment' => array( $pot->get_id(), $pan->get_id() ) ) );
$counts = Mixmymeals_Equipment::usage_counts();
$ok( 'pot counted in both recipes', 2 === (int) ( $counts[ $pot->get_id() ] ?? 0 ) );
$ok( 'pan counted in one', 1 === (int) ( $counts[ $pan->get_id() ] ?? 0 ) );
$ok( 'count_recipes agrees', 2 === ( new Mixmymeals_Equipment( $pot->get_id() ) )->count_recipes() );

echo '-- 11. The cached list is dropped when a recipe changes' . PHP_EOL;
$fresh = Mixmymeals_Equipment::create( 'ZZ Wok' );
( new Mixmymeals_Recipe( $recipe_id ) )->set_equipment( array( $fresh->get_id() ) );
$ok( 'list picks the recipe up at once', in_array( $recipe_id, $fresh->get_recipe_ids(), true ) );
( new Mixmymeals_Recipe( $recipe_id ) )->set_equipment( array() );
$ok( 'and drops it again', ! in_array( $recipe_id, ( new Mixmymeals_Equipment( $fresh->get_id() ) )->get_recipe_ids(), true ) );

echo '-- 12. The box renders what the handler reads' . PHP_EOL;
( new Mixmymeals_Recipe( $recipe_id ) )->set_equipment( array( $pot->get_id() ) );
ob_start();
$meta->render_equipment_meta_box_content( get_post( $recipe_id ) );
$html = ob_get_clean();
$ok( 'nonce present', false !== strpos( $html, 'mmm_equipment_nonce' ) );
$ok( 'rename field present', false !== strpos( $html, 'mmm_equipment_titles[' . $pot->get_id() . ']' ) );
$ok( 'new field present', false !== strpos( $html, 'mmm_equipment_new"' ) );
$ok( 'the ticked one is checked', (bool) preg_match( '/value="' . $pot->get_id() . '"\s+checked/', $html ) );
$ok( 'no delete control', false === stripos( $html, 'mmm_equipment_delete' ) );

/*
 * Clean up only what this run made. Deleting every mmm_equipment whose title
 * matches "ZZ" was how an earlier version quietly ate the fixtures another
 * check had built on the same install — a test may not decide that somebody
 * else's post looks like its own.
 */
$made = array_merge(
	array( $pan->get_id(), $pot->get_id(), $fresh->get_id() ),
	array_map( 'intval', $steamer ),
	array_map( 'intval', $new )
);

foreach ( array_unique( $made ) as $id ) {
	if ( $id > 0 && 'mmm_equipment' === get_post_type( $id ) ) {
		wp_delete_post( $id, true );
	}
}

wp_delete_post( $recipe_id, true );
wp_delete_post( $other_id, true );

echo PHP_EOL . ( $fails ? "$fails FAILURES" : 'all assertions passed' ) . PHP_EOL;
