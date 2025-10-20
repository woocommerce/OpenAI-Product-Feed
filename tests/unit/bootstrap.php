<?php
/**
 * PHPUnit bootstrap file for the plugin.
 */

// Ensure errors are visible during tests.
ini_set( 'display_errors', '1' );
error_reporting( E_ALL );

// Let wp-phpunit tell us where the WP test suite lives.
$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
    $_tests_dir = __DIR__ . '/../../vendor/wp-phpunit/wp-phpunit';
}


if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
    fwrite( STDERR, "Could not find WordPress tests in {$_tests_dir}\n" );
    exit( 1 );
}

// Load WordPress test functions so we can register hooks before WP boots.
require $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin under test.
 * Adjust the path to your main plugin file.
 */
tests_add_filter( 'muplugins_loaded', function () {
    // Load the WooCommerce plugin so we can use its classes in our plugin and tests.
	require_once WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';

    // If your plugin main file is your-plugin.php at repo root:
    require dirname( __DIR__, 2 ) . '/openai-product-feed-for-woo.php';
} );

// Boot the WordPress testing environment.
require $_tests_dir . '/includes/bootstrap.php';

// Load WooCommerce test framework
require_once __DIR__ . '/../framework/wp-http-testcase.php';
require_once __DIR__ . '/../framework/class-wc-unit-test-factory.php';
require_once __DIR__ . '/../framework/class-wc-unit-test-case.php';