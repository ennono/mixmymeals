<?php
/**
 * Planer-Werkzeuge (Mixmymeals_Planner_Tools): Plan vorschlagen, Rezeptwahl
 * je Tag, die Woche auf einen Blick.
 *
 * Läuft gegen eine echte Installation, legt eigene Posts, Terme und eine
 * Kategorie an und löscht alles wieder. Posts heißen „R<n> …“ oder „T …“,
 * Produkte tragen die Marke „ZZ“, Terme „zz-…“.
 *
 * Damit die Inhalte der Seite die Auswahl nicht stören, sieht jede
 * Rezeptabfrage während eines Aufrufs nur die Testrezepte (pre_get_posts).
 * Die Zuordnung der vier früheren Diät-Schlüssel wird für die Dauer des
 * Laufs auf die Testterme gelegt und danach zurückgesetzt.
 *
 * wp --require=<dbhost.php> eval-file \
 *    wp-content/plugins/mixmymeals/tools/planner-tools-test.php
 *
 * @package MixMyMeals
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$GLOBALS['MMM_PT_FAIL']  = 0;
$GLOBALS['MMM_PT_PASS']  = 0;
$GLOBALS['MMM_PT_TRASH'] = array();
$GLOBALS['MMM_PT_TERMS'] = array();
$GLOBALS['MMM_PT_SCOPE'] = array();

$ok = static function ( string $label, bool $cond ): void {
	if ( $cond ) {
		++$GLOBALS['MMM_PT_PASS'];
	} else {
		++$GLOBALS['MMM_PT_FAIL'];
	}
	echo esc_html( ( $cond ? 'ok   ' : 'FAIL ' ) . $label ) . PHP_EOL;
};

echo '-- 0. Richtige Seite?' . PHP_EOL;
echo '     home_url() = ' . esc_url( home_url() ) . PHP_EOL;
$mmm_host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
if ( preg_match( '/(^|\.)(speisengeplant|stopfnicht)\.de$/', $mmm_host ) ) {
	echo 'Abbruch: das ist keine lokale Seite.' . PHP_EOL;
	return;
}

/**
 * Legt einen Post an und merkt ihn zum Aufräumen.
 *
 * @param string $type   Post-Typ.
 * @param string $title  Titel.
 * @param array  $extra  Weitere Felder für wp_insert_post().
 * @return int
 */
function mmm_pt_post( string $type, string $title, array $extra = array() ): int {
	$id = (int) wp_insert_post(
		$extra + array(
			'post_type'   => $type,
			'post_title'  => $title,
			'post_status' => 'publish',
		)
	);

	$GLOBALS['MMM_PT_TRASH'][] = $id;

	return $id;
}

/**
 * Ein Diät-Term, für die Dauer des Tests.
 *
 * @param string $slug   Slug.
 * @param string $name   Name.
 * @param int    $parent_id Eltern-Term.
 * @param string $axis   Achse.
 * @return int
 */
function mmm_pt_diet( string $slug, string $name, int $parent_id, string $axis ): int {
	$term = get_term_by( 'slug', $slug, Mixmymeals_Diet::TAXONOMY );

	if ( $term ) {
		$id = (int) $term->term_id;
	} else {
		$id                        = (int) wp_insert_term(
			$name,
			Mixmymeals_Diet::TAXONOMY,
			array(
				'slug'   => $slug,
				'parent' => $parent_id,
			)
		)['term_id'];
		$GLOBALS['MMM_PT_TERMS'][] = array( $id, Mixmymeals_Diet::TAXONOMY );
	}

	update_term_meta( $id, Mixmymeals_Diet::META_AXIS, $axis );

	return $id;
}

/**
 * Eine Zutat mit Energie und Ernährungsformen.
 *
 * @param string   $title Titel.
 * @param string[] $diets Diät-Slugs.
 * @return int
 */
function mmm_pt_ingredient( string $title, array $diets ): int {
	$id = mmm_pt_post( 'mmm_ingredient', $title );
	( new Mixmymeals_Ingredient( $id ) )->set_nutrition( array( 'calories_100' => 100 ) );
	Mixmymeals_Diet::set_for_post( $id, $diets );
	return $id;
}

/**
 * Ein Produkt einer Zutat.
 *
 * @param int      $ingredient Zutat.
 * @param string   $title      Titel.
 * @param string[] $diets      Diät-Slugs.
 * @return int
 */
function mmm_pt_product( int $ingredient, string $title, array $diets ): int {
	$id  = mmm_pt_post( 'mmm_product', $title, array( 'post_parent' => $ingredient ) );
	$obj = new Mixmymeals_Product( $id );
	$obj->set_brand( 'ZZ' );
	$obj->set_nutrition( array( 'calories_100' => 100 ) );
	Mixmymeals_Diet::set_for_post( $id, $diets );
	return $id;
}

/**
 * Ein Rezept aus Zutaten, im Index.
 *
 * @param string $title       Titel.
 * @param int[]  $ingredients Zutaten-IDs.
 * @param int    $category    Kategorie (0 = keine).
 * @param array  $extra       Weitere Felder für wp_insert_post().
 * @return int
 */
function mmm_pt_recipe( string $title, array $ingredients, int $category, array $extra = array() ): int {
	$id     = mmm_pt_post( 'mmm_recipe', $title, $extra );
	$recipe = new Mixmymeals_Recipe( $id );
	$recipe->set_servings( 2 );

	$rows = array();
	foreach ( $ingredients as $ingredient ) {
		$rows[] = array(
			'id'     => $ingredient,
			'amount' => 200,
		);
	}

	$recipe->set_steps(
		array(
			array(
				'title'       => 'Kochen',
				'description' => 'Alles zusammen.',
				'ingredients' => $rows,
			),
		)
	);

	if ( $category ) {
		wp_set_object_terms( $id, array( $category ), 'mmm_recipe_category' );
	}

	( new Mixmymeals_Recipe( $id ) )->recount_indexes();
	$GLOBALS['MMM_PT_SCOPE'][] = $id;

	return $id;
}

/*
 * wp_send_json_* beendet den Prozess; der die-Handler wirft stattdessen.
 * Während des Aufrufs sehen Rezeptabfragen nur die Testrezepte.
 */
$call = static function ( string $method, array $post ) {
	$thrower = static function () {
		return static function () {
			throw new \RuntimeException( 'wp_die' );
		};
	};
	$scope   = static function ( $query ) {
		if ( 'mmm_recipe' === $query->get( 'post_type' ) ) {
			$in = (array) $query->get( 'post__in' );
			$query->set( 'post__in', $in ? array_values( array_intersect( $in, $GLOBALS['MMM_PT_SCOPE'] ) ) + array( 0 ) : $GLOBALS['MMM_PT_SCOPE'] );
		}
	};

	$_POST          = $post;
	$_POST['nonce'] = $post['nonce'] ?? wp_create_nonce( 'mmm_frontend_nonce' );
	$_REQUEST       = $_POST;

	add_action( 'pre_get_posts', $scope );
	add_filter( 'wp_doing_ajax', '__return_true' );
	add_filter( 'wp_die_ajax_handler', $thrower );
	add_filter( 'wp_die_handler', $thrower );
	ob_start();

	try {
		Mixmymeals_Planner_Tools::$method();
	} catch ( \Throwable $e ) {
		unset( $e );
	}

	$raw = ob_get_clean();
	remove_filter( 'wp_die_handler', $thrower );
	remove_filter( 'wp_die_ajax_handler', $thrower );
	remove_filter( 'wp_doing_ajax', '__return_true' );
	remove_action( 'pre_get_posts', $scope );
	$_POST    = array();
	$_REQUEST = array();

	$out = json_decode( $raw, true );

	return is_array( $out ) ? $out : array( 'raw' => $raw );
};

/**
 * Rezept-IDs der Planer-Einträge.
 *
 * @param Mixmymeals_Cart $cart Planer.
 * @return int[]
 */
function mmm_pt_ids( Mixmymeals_Cart $cart ): array {
	$ids = array_map( 'intval', array_column( $cart->get_items(), 'recipe_id' ) );
	sort( $ids );
	return $ids;
}

wp_set_current_user( 1 );

echo '-- 1. Testdaten' . PHP_EOL;

// Diäten: vegetarisch › vegan auf der Achse "diet", glutenfrei und
// laktosefrei je auf ihrer eigenen. Die früheren Schlüssel zeigen für den
// Lauf auf diese Terme.
$mmm_former_bak = get_option( Mixmymeals_Diet::FORMER_OPTION, null );
$veggi_id       = mmm_pt_diet( 'zz-vegetarisch', 'ZZ Vegetarisch', 0, 'diet' );
$vegan_id       = mmm_pt_diet( 'zz-vegan', 'ZZ Vegan', $veggi_id, 'diet' );
$gluten_id      = mmm_pt_diet( 'zz-glutenfrei', 'ZZ Glutenfrei', 0, 'zz-glutenfrei' );
$lactose_id     = mmm_pt_diet( 'zz-lactosefrei', 'ZZ Laktosefrei', 0, 'zz-lactosefrei' );
update_option(
	Mixmymeals_Diet::FORMER_OPTION,
	array(
		'vegetarian'   => $veggi_id,
		'vegan'        => $vegan_id,
		'gluten_free'  => $gluten_id,
		'lactose_free' => $lactose_id,
	)
);
Mixmymeals_Diet::reset_types();

$veggi   = 'zz-vegetarisch';
$vegan   = 'zz-vegan';
$gluten  = 'zz-glutenfrei';
$lactose = 'zz-lactosefrei';

$ok( 'die Fragen erreichen die Terme über die früheren Schlüssel', array( $veggi, $vegan, $gluten, $lactose ) === array_keys( Mixmymeals_Planner_Tools::exclusions() ) );

$main_cat                  = wp_insert_term( 'T Hauptgerichte', 'mmm_recipe_category', array( 'slug' => 'zz-hauptgerichte' ) );
$main_cat                  = (int) $main_cat['term_id'];
$GLOBALS['MMM_PT_TERMS'][] = array( $main_cat, 'mmm_recipe_category' );
$main_only                 = static fn() => array( 'zz-hauptgerichte' );
add_filter( 'mixmymeals_suggest_categories', $main_only );

$gemuese = mmm_pt_ingredient( 'T Gemüse', array( $vegan, $gluten ) );
$hack    = mmm_pt_ingredient( 'T Hackfleisch', array() );
$rind    = mmm_pt_product( $hack, 'T Rinderhack', array() );
$soja    = mmm_pt_product( $hack, 'T Sojahack', array( $vegan ) );
Mixmymeals_Product::set_reference_id( $hack, $rind );
$kaese   = mmm_pt_ingredient( 'T Käse', array( $veggi ) );
$fleisch = mmm_pt_ingredient( 'T Schnitzelfleisch', array() );
$nudeln  = mmm_pt_ingredient( 'T Nudeln', array( $vegan ) );
$gfnud   = mmm_pt_product( $nudeln, 'T Glutenfreie Nudeln', array( $vegan, $gluten ) );

$r1 = mmm_pt_recipe( 'R1 Gemüsecurry', array( $gemuese ), $main_cat );
$r2 = mmm_pt_recipe( 'R2 Chili', array( $hack, $gemuese ), $main_cat );
$r3 = mmm_pt_recipe( 'R3 Käsespätzle', array( $kaese ), $main_cat );
$r4 = mmm_pt_recipe( 'R4 Schnitzel', array( $fleisch ), $main_cat );
$r5 = mmm_pt_recipe( 'R5 Nudelsalat', array( $nudeln ), 0 );
$r6 = mmm_pt_recipe( 'R6 Geheimes Curry', array( $gemuese ), $main_cat, array( 'post_password' => 'geheim' ) );
$r7 = mmm_pt_recipe( 'R7 Ungelistetes Curry', array( $gemuese ), $main_cat );
Mixmymeals_Post_Types::set_unlisted( $r7, true );
$r8 = mmm_pt_recipe( 'R8 Entwurfscurry', array( $gemuese ), $main_cat, array( 'post_status' => 'draft' ) );

$ok( 'R2 ist wie geschrieben nicht vegetarisch, aber vegan kochbar', ! in_array( $veggi, Mixmymeals_Diet::expand( ( new Mixmymeals_Recipe( $r2 ) )->get_diets() ), true ) && ( new Mixmymeals_Recipe( $r2 ) )->can_be_cooked_as( $vegan ) );
$ok( 'R5 ist nur glutenfrei kochbar, nicht glutenfrei wie geschrieben', in_array( $gluten, get_post_meta( $r5, Mixmymeals_Recipe::META_DIET_INDEX, false ), true ) && ! in_array( $gluten, ( new Mixmymeals_Recipe( $r5 ) )->get_diets(), true ) );

$cart  = new Mixmymeals_Cart();
$today = (int) wp_date( 'N' );

echo '-- 2. So viele Rezepte wie Tage, ab heute' . PHP_EOL;
$cart->clear();
$res   = $call(
	'suggest_plan_ajax',
	array(
		'people' => 1,
		'days'   => 3,
	)
);
$items = $cart->get_items();
$ok( 'antwortet mit Erfolg', ! empty( $res['success'] ) );
$ok( 'drei Rezepte für drei Tage', 3 === count( $items ) && 3 === (int) ( $res['data']['count'] ?? 0 ) );
$ok( 'jeder Tag einmal, beginnend heute', array( $today, $today % 7 + 1, ( $today + 1 ) % 7 + 1 ) === array_map( 'intval', array_column( $items, 'day' ) ) );
$ok( 'als Abendessen', array( 'dinner' ) === array_values( array_unique( array_column( $items, 'type' ) ) ) );
$ok( 'drei verschiedene Hauptgerichte', 3 === count( array_unique( mmm_pt_ids( $cart ) ) ) && ! array_diff( mmm_pt_ids( $cart ), array( $r1, $r2, $r3, $r4 ) ) );

$res = $call(
	'suggest_plan_ajax',
	array(
		'people' => 1,
		'days'   => 7,
	)
);
$ok( 'zu wenige Hauptgerichte: der Rest stockt auf (vier + R5)', array( $r1, $r2, $r3, $r4, $r5 ) === mmm_pt_ids( $cart ) );
$ok( 'nie ein geschütztes, ungelistetes oder unveröffentlichtes Rezept', ! array_intersect( mmm_pt_ids( $cart ), array( $r6, $r7, $r8 ) ) );

$res = $call(
	'suggest_plan_ajax',
	array(
		'people' => 99,
		'days'   => 99,
	)
);
$ok( 'Tage und Personen werden begrenzt (höchstens 7 Tage, 12 Personen)', ! empty( $res['success'] ) && count( $cart->get_items() ) <= 7 && 12 === count( $cart->get_eaters() ) );

echo '-- 3. Die Personenzahl kommt als Esser an' . PHP_EOL;
$call(
	'suggest_plan_ajax',
	array(
		'people' => 2,
		'days'   => 3,
	)
);
$ok( 'zwei Personen ergeben zwei Esser', 2 === count( $cart->get_eaters() ) );
$ok( 'und je Rezept 2 Portionen', array( 2 ) === array_values( array_unique( array_map( 'intval', array_column( $cart->get_items(), 'servings' ) ) ) ) );
$ok( 'gegessen von allen (keine Einzelauswahl)', array() === array_filter( array_column( $cart->get_items(), 'eaters' ) ) );
$ok( 'der Planer rechnet für zwei', 2 === count( $cart->as_plan()->get_eaters() ) );

$call(
	'suggest_plan_ajax',
	array(
		'people' => 1,
		'days'   => 3,
	)
);
$ok( 'eine Person ergibt keine Esserliste', '' === get_user_meta( 1, Mixmymeals_Cart::EATERS_META_KEY, true ) && count( $cart->get_eaters() ) <= 1 );
$ok( 'und je Rezept 1 Portion', array( 1 ) === array_values( array_unique( array_map( 'intval', array_column( $cart->get_items(), 'servings' ) ) ) ) );

echo '-- 4. Ausschlüsse greifen' . PHP_EOL;
$res = $call(
	'suggest_plan_ajax',
	array(
		'people' => 1,
		'days'   => 7,
		'diets'  => array( $veggi ),
	)
);
$ok( 'ohne Fleisch: Curry, Chili, Spätzle und Nudelsalat — kein Schnitzel', array( $r1, $r2, $r3, $r5 ) === mmm_pt_ids( $cart ) );
$chili = array_values( array_filter( $cart->get_items(), static fn( $i ) => (int) $i['recipe_id'] === $r2 ) )[0] ?? array();
$ok( 'das Chili ist vegetarisch eingeplant, nicht als Originalrezept', $veggi === ( $chili['diet'] ?? '' ) );
$cooked = $chili ? Mixmymeals_Meal_Plan::row_recipe( $chili ) : null;
$ok( 'und so gekocht ist es vegetarisch', $cooked && in_array( $veggi, Mixmymeals_Diet::expand( $cooked->get_diets() ), true ) );
$hack_rows = $cooked ? array_values( array_filter( $cooked->get_ingredients_scaled( 1.0 ), static fn( $r ) => (int) $r['id'] === $hack ) ) : array();
$ok( 'mit dem Sojahack, nicht dem Rinderhack', $hack_rows && $soja === (int) $hack_rows[0]['product_id'] );

$call(
	'suggest_plan_ajax',
	array(
		'people' => 1,
		'days'   => 7,
		'diets'  => array( $vegan ),
	)
);
$vegan_ids = mmm_pt_ids( $cart );
$ok( 'vegan: Curry, Chili und Nudelsalat', array( $r1, $r2, $r5 ) === $vegan_ids );

$ok( '„vegan“ und „vegetarisch“ zusammen sind „vegan“', array( $vegan ) === Mixmymeals_Planner_Tools::clean_diets( array( $vegan, $veggi ) ) );
$call(
	'suggest_plan_ajax',
	array(
		'people' => 1,
		'days'   => 7,
		'diets'  => array( $veggi, $vegan ),
	)
);
$ok( 'und verengen nicht doppelt: dieselben Rezepte wie vegan allein', $vegan_ids === mmm_pt_ids( $cart ) );

$call(
	'suggest_plan_ajax',
	array(
		'people' => 1,
		'days'   => 7,
		'diets'  => array( 'vegetarian' ),
	)
);
$ok( 'ein früherer Schlüssel („vegetarian“) wirkt wie der Slug', array( $r1, $r2, $r3, $r5 ) === mmm_pt_ids( $cart ) );

$call(
	'suggest_plan_ajax',
	array(
		'people' => 1,
		'days'   => 7,
		'diets'  => array( 'erfunden', array( 'x' ) ),
	)
);
$ok( 'Unbekanntes wird übergangen', array( $r1, $r2, $r3, $r4, $r5 ) === mmm_pt_ids( $cart ) );

$call(
	'suggest_plan_ajax',
	array(
		'people' => 1,
		'days'   => 7,
		'diets'  => array( $gluten ),
	)
);
$ok( 'glutenfrei: nur was so gekocht glutenfrei ist (R1, nicht der nur kochbare R5)', array( $r1 ) === mmm_pt_ids( $cart ) );

echo '-- 5. Ohne passende Rezepte: no_recipes' . PHP_EOL;
$cart->save_items(
	array(
		array(
			'recipe_id' => $r4,
			'servings'  => 3,
			'type'      => '',
			'day'       => 1,
		),
	)
);
$res = $call(
	'suggest_plan_ajax',
	array(
		'people' => 2,
		'days'   => 5,
		'diets'  => array( $lactose ),
	)
);
$ok( 'antwortet mit Fehler', isset( $res['success'] ) && false === $res['success'] );
$ok( 'Code no_recipes', 'no_recipes' === ( $res['data']['code'] ?? '' ) );
$ok( 'der Planer bleibt, wie er war', array( $r4 ) === mmm_pt_ids( $cart ) && 3 === (int) $cart->get_items()[0]['servings'] );

$res = $call(
	'suggest_plan_ajax',
	array(
		'people' => 2,
		'days'   => 5,
		'nonce'  => 'falsch',
	)
);
$ok( 'ohne gültige Nonce geschieht nichts', empty( $res['success'] ) && array( $r4 ) === mmm_pt_ids( $cart ) );

echo '-- 6. Rezeptwahl und Woche für Gäste' . PHP_EOL;
$cart->clear();
wp_set_current_user( 0 );
$_COOKIE[ Mixmymeals_Cart::COOKIE_NAME ] = wp_generate_uuid4();
$guest                                   = new Mixmymeals_Cart();

$res    = $call( 'recipe_picker_ajax', array() );
$picked = array_map( 'intval', array_column( (array) ( $res['data']['recipes'] ?? array() ), 'id' ) );
sort( $picked );
$ok( 'Rezeptwahl antwortet einem Gast', ! empty( $res['success'] ) );
$ok( 'ohne Suchwort: die neuesten, ohne geschützte, ungelistete und Entwürfe', array( $r1, $r2, $r3, $r4, $r5 ) === $picked );

$res    = $call( 'recipe_picker_ajax', array( 'term' => 'Gemüse' ) );
$picked = array_map( 'intval', array_column( (array) ( $res['data']['recipes'] ?? array() ), 'id' ) );
sort( $picked );
$ok( 'Suche nach einer Zutat findet die Rezepte damit', array( $r1, $r2 ) === $picked );
$row = (array) ( $res['data']['recipes'][0] ?? array() );
$ok(
	'jede Zeile hat id, title, url, thumb, servings, time',
	array( 'id', 'servings', 'thumb', 'time', 'title', 'url' ) === ( static function ( $k ) {
		sort( $k );
		return $k;
	} )( array_keys( $row ) )
);

$res = $call( 'recipe_picker_ajax', array( 'term' => 'Geheimes' ) );
$ok( 'ein geschütztes Rezept findet auch die Suche nicht', ! empty( $res['success'] ) && array() === $res['data']['recipes'] );

$res = $call( 'recipe_picker_ajax', array( 'term' => str_repeat( 'Gemüse ', 40 ) ) );
$ok( 'ein überlanges Suchwort wird gekürzt', ! empty( $res['success'] ) && mb_strlen( (string) $res['data']['term'] ) <= Mixmymeals_Planner_Tools::PICKER_TERM_MAX );

$res = $call( 'recipe_picker_ajax', array( 'nonce' => 'falsch' ) );
$ok( 'ohne gültige Nonce keine Liste', empty( $res['success'] ) );

$guest->save_items(
	array(
		array(
			'recipe_id' => $r1,
			'servings'  => 2,
			'type'      => 'dinner',
			'day'       => $today,
		),
		array(
			'recipe_id' => $r6,
			'servings'  => 2,
			'type'      => 'dinner',
			'day'       => $today,
		),
		array(
			'recipe_id' => $r3,
			'servings'  => 2,
			'type'      => '',
			'day'       => null,
		),
	)
);
$res  = $call( 'mini_plan_ajax', array() );
$days = (array) ( $res['data']['days'] ?? array() );
$ok( 'Wochenübersicht antwortet einem Gast', ! empty( $res['success'] ) );
$ok( 'sieben Tage als Liste, ab heute', 7 === count( $days ) && $today === (int) ( $days[0]['day'] ?? 0 ) && ! empty( $days[0]['today'] ) );
$titles = array_column( (array) ( $days[0]['items'] ?? array() ), 'title' );
$ok( 'heute: das Curry, nicht das geschützte Rezept', array( 'R1 Gemüsecurry' ) === $titles );
$ok( 'ohne Tag: die Spätzle', array( 'R3 Käsespätzle' ) === array_column( (array) ( $res['data']['unassigned'] ?? array() ), 'title' ) );

// Die Überschrift kommt mit den Daten, durch den Filter der Planer-Boxen:
// Ein Theme, das den Kasten selbst benennt, gibt '' zurück und bekommt keine
// — sonst bliebe sie stehen und wäre nur per CSS zu verstecken.
$ok(
	'Wochenübersicht bringt ihre Überschrift mit',
	is_string( $res['data']['heading'] ?? null )
		&& false !== strpos( (string) $res['data']['heading'], 'mmm-mini-plan__title' )
);

$mmm_mini_heading = static function ( $text, $key ) {
	return 'mini' === $key ? '' : $text;
};
add_filter( 'mixmymeals_planner_heading', $mmm_mini_heading, 10, 2 );
$res = $call( 'mini_plan_ajax', array() );
$ok( 'ein Theme kann sie abbestellen', '' === ( $res['data']['heading'] ?? null ) );
remove_filter( 'mixmymeals_planner_heading', $mmm_mini_heading, 10 );

$res = $call(
	'suggest_plan_ajax',
	array(
		'people' => 2,
		'days'   => 2,
	)
);
$ok( 'ein Gast kann sich einen Plan vorschlagen lassen', ! empty( $res['success'] ) && 2 === count( $guest->get_items() ) && 2 === count( $guest->get_eaters() ) );

echo '-- 7. Ein geschütztes Rezept lässt sich nicht einplanen' . PHP_EOL;
$ok( 'recipe_is_plannable() sagt nein', ! Mixmymeals_Cart::recipe_is_plannable( $r6 ) );
$ok( 'ein offenes schon', Mixmymeals_Cart::recipe_is_plannable( $r1 ) );

// Aufräumen.
$guest->clear();
unset( $_COOKIE[ Mixmymeals_Cart::COOKIE_NAME ] );
wp_set_current_user( 1 );
$cart->clear();
remove_filter( 'mixmymeals_suggest_categories', $main_only );

echo PHP_EOL . '=== Aufräumen ===' . PHP_EOL;
foreach ( array_reverse( $GLOBALS['MMM_PT_TRASH'] ) as $trash_id ) {
	wp_delete_post( $trash_id, true );
}
foreach ( array_reverse( $GLOBALS['MMM_PT_TERMS'] ) as $mmm_term ) {
	wp_delete_term( $mmm_term[0], $mmm_term[1] );
}
if ( null === $mmm_former_bak ) {
	delete_option( Mixmymeals_Diet::FORMER_OPTION );
} else {
	update_option( Mixmymeals_Diet::FORMER_OPTION, $mmm_former_bak );
}
Mixmymeals_Diet::reset_types();
echo 'entfernt: ' . count( $GLOBALS['MMM_PT_TRASH'] ) . ' Posts, ' . count( $GLOBALS['MMM_PT_TERMS'] ) . ' Terme; Diät-Zuordnung zurückgesetzt' . PHP_EOL;

echo PHP_EOL . '=== Ergebnis ===' . PHP_EOL;
printf( "  %d bestanden, %d fehlgeschlagen\n", (int) $GLOBALS['MMM_PT_PASS'], (int) $GLOBALS['MMM_PT_FAIL'] );
