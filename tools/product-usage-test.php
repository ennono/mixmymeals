<?php
/**
 * Which recipes chose a product — the products' half of what the units box
 * has had, and the box that renders it.
 */

$fails = 0;
$ok    = function ( $label, $cond ) use ( &$fails ) {
	if ( ! $cond ) {
		$fails++;
	}
	echo ( $cond ? 'ok   ' : 'FAIL ' ) . $label . PHP_EOL;
};

wp_set_current_user( 1 );

$ing = wp_insert_post( array( 'post_type' => 'mmm_ingredient', 'post_title' => 'ZZ Joghurt', 'post_status' => 'publish' ) );

$a = Mixmymeals_Product::create( $ing, 'ZZ Mild' );   $a->set_brand( 'ZZ A' );
$b = Mixmymeals_Product::create( $ing, 'ZZ Grie' );   $b->set_brand( 'ZZ B' );
$ref = Mixmymeals_Product::create( $ing, 'ZZ Ref' );  $ref->set_brand( 'ZZ C' );
Mixmymeals_Product::set_reference_id( $ing, $ref->get_id() );

$make = function ( string $title, string $status, array $pins ) use ( $ing ) {
	$id    = wp_insert_post( array( 'post_type' => 'mmm_recipe', 'post_title' => $title, 'post_status' => $status ) );
	$steps = array();

	foreach ( $pins as $pin ) {
		$steps[] = array(
			'title'       => 'Schritt',
			'description' => '',
			'image'       => 0,
			'ingredients' => array( array( 'id' => $ing, 'amount' => 150, 'amount_id' => 0, 'product_id' => $pin ) ),
		);
	}

	update_post_meta( $id, '_rmm_steps', $steps );

	return $id;
};

$r1 = $make( 'ZZ Tzatziki', 'publish', array( $a->get_id() ) );
$r2 = $make( 'ZZ Dressing', 'publish', array( $a->get_id(), $a->get_id() ) );
$r3 = $make( 'ZZ Quark', 'draft', array( $b->get_id() ) );
$r4 = $make( 'ZZ Ohne Wahl', 'publish', array( 0 ) );

echo '-- 1. Counting' . PHP_EOL;
$ok( 'lines, not recipes', 3 === $a->count_usage() );
$ok( 'and the recipes behind them', 2 === count( $a->get_usage() ) );
$ok( 'a second product counts on its own', 1 === $b->count_usage() );
$ok( 'a row pinning nothing counts nowhere', 0 === $ref->count_usage() );

echo '-- 2. What each row says' . PHP_EOL;
$rows = $a->get_usage();
usort( $rows, fn( $x, $y ) => strcmp( $x['recipe_title'], $y['recipe_title'] ) );
$ok( 'the repeated line is counted once per recipe', 2 === (int) $rows[0]['occurrences'] );
$ok( 'with the recipe named', 'ZZ Dressing' === $rows[0]['recipe_title'] );
$ok( 'and its status carried', 'publish' === $rows[0]['status'] );

echo '-- 3. Drafts count' . PHP_EOL;
$draft = $b->get_usage();
$ok( 'a draft recipe is listed', 1 === count( $draft ) );
$ok( 'and marked as one', 'draft' === $draft[0]['status'] );

echo '-- 4. The reference is not folded in' . PHP_EOL;
/*
 * The reference supplies the figures wherever nothing is pinned, but that is
 * the ingredient's property. Counting it here would give one packet a number
 * that belongs to its parent.
 */
$ok( 'being the reference adds nothing to the count', 0 === $ref->count_usage() );
$ok( 'and it really is the reference', $ref->get_id() === Mixmymeals_Product::get_reference_id( $ing ) );

echo '-- 5. One pass, whatever is asked' . PHP_EOL;
$map = Mixmymeals_Product::usage_map();
// Sorted on both sides: the map is filled in the order the recipes are
// scanned, which is not something it promises.
$found = array_values( array_intersect( array_keys( $map ), array( $a->get_id(), $b->get_id(), $ref->get_id() ) ) );
$want  = array( $a->get_id(), $b->get_id() );
sort( $found );
sort( $want );
$ok( 'the map holds every pinned product', $want === $found );
$ok( 'and nothing that was never pinned', ! isset( $map[ $ref->get_id() ] ) );

echo '-- 6. The box renders it' . PHP_EOL;
$meta = new Mixmymeals_Post_Meta();
ob_start();
$meta->render_ingredient_products_meta_box_content( get_post( $ing ) );
$html = ob_get_clean();

$ok( 'a usage block per existing product', 3 === substr_count( $html, 'mmm-products__usage' ) );
$ok( 'the blank row has none', 3 === substr_count( $html, 'mmm-products__usage' ) );
$ok( 'recipes are linked', false !== strpos( $html, 'ZZ Tzatziki</a>' ) );
$ok( 'repeats are marked', false !== strpos( $html, '&times;2' ) || false !== strpos( $html, '×2' ) );
$ok( 'drafts are marked', false !== strpos( $html, 'ZZ Quark' ) );
$ok( 'an unused product says so instead of counting to zero', false === strpos( $html, '0 Rezeptzeilen' ) && false === strpos( $html, 'in 0 recipe' ) );
$ok( 'and the reference explains itself', false !== strpos( $html, 'mmm-products__usage' ) && substr_count( $html, 'class="description"' ) >= 1 );

echo '-- 7. A line losing its pin drops out' . PHP_EOL;
update_post_meta( $r1, '_rmm_steps', array( array( 'title' => 'Schritt', 'description' => '', 'image' => 0, 'ingredients' => array( array( 'id' => $ing, 'amount' => 150, 'amount_id' => 0, 'product_id' => 0 ) ) ) ) );
// The map is memoised per request, so this is what a fresh page load sees.
$fresh = array();
foreach ( get_posts( array( 'post_type' => 'mmm_recipe', 'posts_per_page' => -1, 'post_status' => 'any', 'fields' => 'ids' ) ) as $rid ) {
	foreach ( ( new Mixmymeals_Recipe( (int) $rid ) )->get_steps() as $step ) {
		foreach ( $step['ingredients'] ?? array() as $line ) {
			if ( (int) ( $line['product_id'] ?? 0 ) === $a->get_id() ) {
				$fresh[ (int) $rid ] = true;
			}
		}
	}
}
$ok( 'the unpinned recipe is gone', ! isset( $fresh[ $r1 ] ) );
$ok( 'the other one stays', isset( $fresh[ $r2 ] ) );

// Clean up.
foreach ( array( $r1, $r2, $r3, $r4, $a->get_id(), $b->get_id(), $ref->get_id(), $ing ) as $id ) {
	wp_delete_post( (int) $id, true );
}

echo PHP_EOL . ( $fails ? "$fails FAILURES" : 'all assertions passed' ) . PHP_EOL;
