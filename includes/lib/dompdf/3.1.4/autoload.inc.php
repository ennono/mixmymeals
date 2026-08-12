<?php
/**
 * dompdf 3.1.4 autoloader entry point (vendored).
 *
 * The direct-access guard is a local addition to the upstream one-liner: the
 * file sits inside the plugin and is therefore reachable over HTTP on a stock
 * server. Re-apply it when this vendored copy is updated.
 *
 * @package MixMyMeals
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require __DIR__ . '/vendor/autoload.php';
