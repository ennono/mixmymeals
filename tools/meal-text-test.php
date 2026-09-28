<?php
/**
 * The meal's own short text: storage, the admin round trip, REST, the two
 * renderings — and the one thing that must NOT happen, a visitor setting it.
 */

$fails = 0;
$ok    = function ( $label, $cond ) use ( &$fails ) {
	if ( ! $cond ) {
		$fails++;
	}
	echo ( $cond ? 'ok   ' : 'FAIL ' ) . $label . PHP_EOL;
};

wp_set_current_user( 1 );

$recipe_id = wp_insert_post( array( 'post_type' => 'mmm_recipe', 'post_title' => 'ZZ Linseneintopf', 'post_status' => 'publish', 'post_excerpt' => 'Der Text des REZEPTS.' ) );
$plan_id   = wp_insert_post( array( 'post_type' => 'mmm_meal_plan', 'post_title' => 'ZZ Textplan', 'post_status' => 'publish' ) );
update_post_meta( $plan_id, 'start_day', 1 );
update_post_meta( $plan_id, 'end_day', 7 );

$meal = Mixmymeals_Meal::create( 'ZZ Linseneintopf', $plan_id );
$meal->set_recipe_id( $recipe_id );
$meal->set_servings( 4 );
$meal->set_type( 'dinner' );
$meal->set_day( 2 );

echo '-- 1. Storage' . PHP_EOL;
$ok( 'empty until somebody says something', '' === $meal->get_text() );
$meal->set_text( 'Doppelte Menge kochen, Hälfte einfrieren.' );
$ok( 'stored', 'Doppelte Menge kochen, Hälfte einfrieren.' === $meal->get_text() );
$ok( 'and it is the post excerpt', 'Doppelte Menge kochen, Hälfte einfrieren.' === get_post( $meal->get_id() )->post_excerpt );

echo '-- 2. It never borrows the recipe\'s excerpt' . PHP_EOL;
$bare = Mixmymeals_Meal::create( 'ZZ Linseneintopf', $plan_id );
$bare->set_recipe_id( $recipe_id );
$ok( 'recipe has one', 'Der Text des REZEPTS.' === get_post( $recipe_id )->post_excerpt );
$ok( 'the meal stays empty', '' === $bare->get_text() );

echo '-- 3. An empty string clears it' . PHP_EOL;
$bare->set_text( 'Weg damit.' );
$bare->set_text( '' );
$ok( 'cleared', '' === $bare->get_text() );

echo '-- 4. mmm_meal does not support excerpt' . PHP_EOL;
$ok(
	'so core adds no second box beside the field',
	! post_type_supports( 'mmm_meal', 'excerpt' )
);

echo '-- 5. Round trip through the admin save' . PHP_EOL;
$meta                            = new Mixmymeals_Post_Meta();
$_POST                           = array();
$_POST['mmm_meal_meta_nonce']    = wp_create_nonce( 'mmm_meal_meta_box' );
$_POST['mmm_meal_text']          = "  Am Abend vor dem Einkauf.\n\n ";
$meta->save_meal_meta( $meal->get_id() );
$_POST = array();
$ok( 'saved and trimmed', 'Am Abend vor dem Einkauf.' === $meal->get_text() );

ob_start();
$meta->render_meal_metabox( get_post( $meal->get_id() ) );
$html = ob_get_clean();
$ok( 'the field is on the meal screen', false !== strpos( $html, 'name="mmm_meal_text"' ) );
$ok( 'carrying the stored text', false !== strpos( $html, 'Am Abend vor dem Einkauf.' ) );

echo '-- 6. REST' . PHP_EOL;
$api = new Mixmymeals_REST_API();

$req = new WP_REST_Request( 'POST', '/mixmymeals/v1/meals/' . $meal->get_id() );
$req->set_param( 'id', $meal->get_id() );
$req->set_param( 'text', 'Per REST gesetzt.' );
$res = $api->update_meal( $req );
$ok( 'update writes it', 'Per REST gesetzt.' === $meal->get_text() );
$ok( 'and the answer carries it', 'Per REST gesetzt.' === ( $res->get_data()['text'] ?? null ) );

$req = new WP_REST_Request( 'POST', '/mixmymeals/v1/meals' );
$req->set_param( 'plan_id', $plan_id );
$req->set_param( 'recipe_id', $recipe_id );
$req->set_param( 'text', 'Beim Anlegen gesetzt.' );
$created = $api->create_meal( $req );
$new_id  = (int) ( $created->get_data()['id'] ?? 0 );
$ok( 'create writes it', 'Beim Anlegen gesetzt.' === ( new Mixmymeals_Meal( $new_id ) )->get_text() );

echo '-- 7. The bulk builder, which is how MCP makes meals' . PHP_EOL;
$req = new WP_REST_Request( 'POST', '/mixmymeals/v1/meal-plans/' . $plan_id . '/meals/bulk' );
$req->set_param( 'id', $plan_id );
$req->set_param( 'meals', array( array( 'recipe_id' => $recipe_id, 'day' => 5, 'type' => 'lunch', 'text' => 'Aus dem Builder.' ) ) );
$api->add_meals_bulk( $req );
$built = get_posts( array( 'post_type' => 'mmm_meal', 'post_parent' => $plan_id, 'posts_per_page' => -1, 'post_status' => 'any', 'fields' => 'ids' ) );
$texts = array_map( fn( $id ) => ( new Mixmymeals_Meal( (int) $id ) )->get_text(), $built );
$ok( 'the builder writes it', in_array( 'Aus dem Builder.', $texts, true ) );

echo '-- 8. Visitors cannot set one through the planner' . PHP_EOL;
/*
 * The cart carries a note per item and always has; transferring it to the
 * meal is commented out in the AJAX that visitors reach. This asserts the
 * hole stays shut — it is the one requirement of this feature that is about
 * something NOT happening.
 */
/*
 * Read with the comments stripped. The transfer *is* in the file — as a
 * commented-out line — so a plain search finds it and proves nothing. Only
 * code counts.
 */
$strip_comments = static function ( string $file ): string {
	$out = '';

	foreach ( token_get_all( (string) file_get_contents( $file ) ) as $token ) {
		if ( is_array( $token ) ) {
			if ( in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			$out .= $token[1];
		} else {
			$out .= $token;
		}
	}

	return $out;
};

$code = $strip_comments( dirname( __DIR__ ) . '/includes/lib/class-mixmymeals-shortcodes.php' );

$ok(
	'the cart note is still not transferred to the meal',
	false === strpos( $code, "\$item['note']" )
);
$ok(
	'and the visitor plan builder reads no text from the request',
	false === strpos( $code, "\$item['text']" )
);
$ok(
	'nothing on the visitor path writes a meal text',
	false === strpos( $code, 'set_text(' )
);

// The stripper has to work, or the three above pass on an empty haystack.
$ok( 'the comment stripper kept the code', false !== strpos( $code, 'mmm_create_meal_plan' ) );
$ok( 'and dropped the comments', false === strpos( $code, 'ab hier nur noch' ) );

echo '-- 9. Both renderings' . PHP_EOL;
$meal->set_text( 'Reste am Mittwoch.' );

$GLOBALS['post'] = get_post( $meal->get_id() );
setup_postdata( $GLOBALS['post'] );
$shortcodes = new Mixmymeals_Shortcodes();
$hero       = $shortcodes->mmm_meal_hero_callback();
wp_reset_postdata();
$ok( 'the meal hero prints it', false !== strpos( $hero, 'Reste am Mittwoch.' ) );
$ok( 'in the same markup the recipe uses', (bool) preg_match( '/mmm-plan-hero__lead">Reste am Mittwoch\./', $hero ) );
$ok( 'and not the recipe\'s own excerpt', false === strpos( $hero, 'Der Text des REZEPTS.' ) );

$cards = $shortcodes->mmm_meals_in_a_single_meal_plan_callback( array( 'meal_plan_id' => $plan_id ) );
$ok( 'the plan shortcode prints it', false !== strpos( $cards, 'Reste am Mittwoch.' ) );
$ok( 'as the card note', false !== strpos( $cards, 'mmm-recipe-card__note">Reste am Mittwoch.' ) );
$ok( 'a meal without one prints no empty note', substr_count( $cards, 'mmm-recipe-card__note' ) < substr_count( $cards, 'mmm-recipe-card__title' ) );

echo '-- 10. The one-off move of leftover note meta' . PHP_EOL;
$legacy = Mixmymeals_Meal::create( 'ZZ Altlast', $plan_id );
update_post_meta( $legacy->get_id(), 'note', 'Alte Notiz.' );
$taken = Mixmymeals_Meal::create( 'ZZ Altlast belegt', $plan_id );
update_post_meta( $taken->get_id(), 'note', 'Alte Notiz.' );
$taken->set_text( 'Schon gesagt.' );

delete_option( Mixmymeals_Meal::TEXT_MIGRATION_OPTION );
$moved = Mixmymeals_Meal::migrate_notes();
$ok( 'the orphan note became the text', 'Alte Notiz.' === $legacy->get_text() );
$ok( 'an existing text is not overwritten', 'Schon gesagt.' === $taken->get_text() );
$ok( 'and the old row is left in place', 'Alte Notiz.' === get_post_meta( $legacy->get_id(), 'note', true ) );
$ok( 'it reports what it moved', 1 === $moved );
$ok( 'and does nothing on the second run', 0 === Mixmymeals_Meal::migrate_notes() );

// Clean up.
foreach ( get_posts( array( 'post_type' => 'mmm_meal', 'post_parent' => $plan_id, 'posts_per_page' => -1, 'post_status' => 'any', 'fields' => 'ids' ) ) as $id ) {
	wp_delete_post( (int) $id, true );
}
wp_delete_post( $plan_id, true );
wp_delete_post( $recipe_id, true );

echo PHP_EOL . ( $fails ? "$fails FAILURES" : 'all assertions passed' ) . PHP_EOL;
