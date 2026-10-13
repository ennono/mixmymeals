<?php
/**
 * Preparation steps: a recipe step marked "X days/hours before" moves to the
 * front, survives the admin round trip and REST, and appears on the plan page
 * and in the planner on the day it is due, linked to its step.
 *
 * Run against a real install:
 *   wp eval-file wp-content/plugins/mixmymeals/tools/prep-steps-test.php
 */

$fails = 0;
$ok    = function ( $label, $cond ) use ( &$fails ) {
	if ( ! $cond ) {
		$fails++;
	}
	echo ( $cond ? 'ok   ' : 'FAIL ' ) . $label . PHP_EOL;
};

wp_set_current_user( 1 );

$recipe_id = wp_insert_post( array( 'post_type' => 'mmm_recipe', 'post_title' => 'ZZ T Bohnenchili', 'post_status' => 'publish' ) );
$bread_id  = wp_insert_post( array( 'post_type' => 'mmm_recipe', 'post_title' => 'ZZ T Übernachtbrot', 'post_status' => 'publish' ) );
$plan_id   = wp_insert_post( array( 'post_type' => 'mmm_meal_plan', 'post_title' => 'ZZ T Vorarbeitsplan', 'post_status' => 'publish' ) );
update_post_meta( $plan_id, 'start_day', 1 );
update_post_meta( $plan_id, 'end_day', 5 );

$recipe = new Mixmymeals_Recipe( $recipe_id );
$bread  = new Mixmymeals_Recipe( $bread_id );

echo '-- 1. Marking and order' . PHP_EOL;
$recipe->set_steps(
	array(
		array( 'title' => 'Kochen', 'description' => 'Alles zusammen kochen.' ),
		array( 'title' => 'Marinieren', 'prep_amount' => 8, 'prep_unit' => 'hours' ),
		array( 'title' => 'Bohnen einweichen', 'prep_amount' => 2, 'prep_unit' => 'days' ),
		array( 'title' => 'Kein Vorlauf', 'prep_amount' => 0, 'prep_unit' => 'days' ),
		array( 'title' => 'Unbekannte Einheit', 'prep_amount' => 3, 'prep_unit' => 'weeks' ),
	)
);
$titles = array_column( $recipe->get_steps(), 'title' );
$ok( 'preparation first, longest lead time first', array( 'Bohnen einweichen', 'Marinieren', 'Kochen', 'Kein Vorlauf', 'Unbekannte Einheit' ) === $titles );
$ok( 'stored sorted, not only read sorted', 'Bohnen einweichen' === get_post_meta( $recipe_id, '_rmm_steps', true )[0]['title'] );
$ok( 'zero is an ordinary step', null === Mixmymeals_Recipe::step_prep( $recipe->get_steps()[3] ) );
$ok( 'an unknown unit is cleared', '' === $recipe->get_steps()[4]['prep_unit'] );
$ok( 'two preparation steps reported', 2 === count( $recipe->get_prep_steps() ) );

update_post_meta( $recipe_id, '_rmm_steps', array( array( 'title' => 'Später' ), array( 'title' => 'Früher', 'prep_amount' => 1, 'prep_unit' => 'days' ) ) );
$ok( 'a write past set_steps() is still read in order', 'Früher' === $recipe->get_steps()[0]['title'] );

echo '-- 2. Round trip through the admin save' . PHP_EOL;
$meta  = new Mixmymeals_Post_Meta();
$_POST = array(
	'mmm_recipe_steps_meta_box_nonce' => wp_create_nonce( 'mmm_recipe_steps_meta_box_action' ),
	'mmm_servings'                    => '4',
	'mmm_recipe_steps'                => array(
		array( 'title' => 'Kochen', 'description' => 'Alles zusammen kochen.' ),
		array( 'title' => 'Bohnen einweichen', 'prep' => '1', 'prep_amount' => '2', 'prep_unit' => 'days' ),
		// Box not ticked: the timing still posted must not count.
		array( 'title' => 'Abschmecken', 'prep_amount' => '5', 'prep_unit' => 'hours' ),
	),
);
$meta->save_recipe_steps_meta_box( $recipe_id );
$_POST = array();
$steps = $recipe->get_steps();
$ok( 'the callback keeps the marking', 2 === (int) $steps[0]['prep_amount'] && 'days' === $steps[0]['prep_unit'] );
$ok( 'and the step moved to the front', 'Bohnen einweichen' === $steps[0]['title'] );
$ok( 'without the box ticked a step stays ordinary', null === Mixmymeals_Recipe::step_prep( $steps[2] ) );

ob_start();
$meta->render_recipe_steps_meta_box_content( get_post( $recipe_id ) );
$html = ob_get_clean();
$ok( 'the editor shows the box', false !== strpos( $html, 'name="mmm_recipe_steps[0][prep]"' ) );
$ok( 'ticked for the preparation step', (bool) preg_match( '/name="mmm_recipe_steps\[0\]\[prep\]" value="1"\s+checked/', $html ) );
$ok( 'and its timing enabled', (bool) preg_match( '/name="mmm_recipe_steps\[0\]\[prep_amount\]" value="2">/', $html ) );
$ok( 'an ordinary step has its timing disabled', (bool) preg_match( '/name="mmm_recipe_steps\[1\]\[prep_amount\]" value="1" disabled/', $html ) );

echo '-- 3. REST' . PHP_EOL;
$api = new Mixmymeals_REST_API();
$req = new WP_REST_Request( 'POST', '/mixmymeals/v1/recipes/' . $bread_id );
$req->set_param( 'id', $bread_id );
$req->set_param(
	'steps',
	array(
		array( 'title' => 'Backen' ),
		array( 'title' => 'Teig ansetzen', 'prep_amount' => 12, 'prep_unit' => 'hours' ),
	)
);
$res = $api->update_recipe( $req );
$ok( 'REST writes and sorts', 'Teig ansetzen' === ( $res->get_data()['steps'][0]['title'] ?? null ) );
$ok( 'and answers with the timing', 'hours' === ( $res->get_data()['steps'][0]['prep_unit'] ?? null ) );

echo '-- 4. The plan: on the day the work is due' . PHP_EOL;
$chili = Mixmymeals_Meal::create( 'ZZ T Bohnenchili', $plan_id );
$chili->set_recipe_id( $recipe_id );
$chili->set_type( 'dinner' );
$chili->set_day( 3 );   // Wednesday dinner, soak on Monday.

$early = Mixmymeals_Meal::create( 'ZZ T Bohnenchili', $plan_id );
$early->set_recipe_id( $recipe_id );
$early->set_type( 'lunch' );
$early->set_day( 1 );   // Monday lunch, soak on Saturday — before the plan.

$toast = Mixmymeals_Meal::create( 'ZZ T Übernachtbrot', $plan_id );
$toast->set_recipe_id( $bread_id );
$toast->set_type( 'breakfast' );
$toast->set_day( 4 );   // Thursday 8:00, twelve hours before is Wednesday evening.

Mixmymeals_Meal_Plan::flush_meals_cache( $plan_id );
$plan  = new Mixmymeals_Meal_Plan( $plan_id );
$tasks = $plan->calc_prep_tasks();
$by    = array();
foreach ( $tasks as $task ) {
	$by[ $task['meal_id'] ] = $task;
}
$ok( 'three tasks', 3 === count( $tasks ) );
$ok( 'days keep counting back: Wednesday − 2 = Monday', 1 === $by[ $chili->get_id() ]['day'] );
$ok( 'Monday − 2 days is Saturday', 6 === $by[ $early->get_id() ]['day'] && $by[ $early->get_id() ]['before_start'] );
$ok( 'hours before breakfast reach the evening before', 3 === $by[ $toast->get_id() ]['day'] );

$shortcodes = new Mixmymeals_Shortcodes();
$html       = $shortcodes->mmm_meals_in_a_single_meal_plan_callback( array( 'meal_plan_id' => $plan_id ) );
$ok( 'the plan page carries the notes', 3 === substr_count( $html, 'class="mmm-prep-note"' ) );
$ok( 'in the site owner\'s wording', false !== strpos( $html, esc_html( sprintf( __( 'Preparation for %s:', 'mixmymeals' ), get_the_title( $chili->get_id() ) ) ) ) );
$ok( 'no section of its own', false === strpos( $html, 'mmm-rail-day--before' ) );
$sat = strpos( $html, esc_html( Mixmymeals_Meal_Plan::weekday_name( 6 ) ) );
$mon = strpos( $html, esc_html( Mixmymeals_Meal_Plan::weekday_name( 1 ) ) );
$ok( 'Saturday, outside a Mon–Fri plan, stands ahead of the week', false !== $sat && false !== $mon && $sat < $mon );
$ok( 'each linked to its meal and step', false !== strpos( $html, esc_url( get_permalink( $chili->get_id() ) . '#mmm-step-1' ) ) );

echo '-- 5. The planner: on the weekday, whatever the start' . PHP_EOL;
$rows  = array(
	array( 'recipe_id' => $recipe_id, 'servings' => 2, 'day' => 1, 'type' => 'dinner' ),
	array( 'recipe_id' => $bread_id, 'servings' => 2, 'day' => 4, 'type' => 'breakfast' ),
);
$tasks = Mixmymeals_Meal_Plan::from_meal_rows( $rows )->calc_prep_tasks();
$ok( 'Monday dinner, two days ahead: Saturday', 6 === $tasks[0]['day'] );
$ok( 'no meal post: the link goes to the recipe', 0 === $tasks[0]['meal_id'] );

// Clean up.
foreach ( get_posts( array( 'post_type' => 'mmm_meal', 'post_parent' => $plan_id, 'posts_per_page' => -1, 'post_status' => 'any', 'fields' => 'ids' ) ) as $id ) {
	wp_delete_post( (int) $id, true );
}
wp_delete_post( $plan_id, true );
wp_delete_post( $recipe_id, true );
wp_delete_post( $bread_id, true );

echo PHP_EOL . ( $fails ? "$fails FAILURES" : 'all assertions passed' ) . PHP_EOL;
