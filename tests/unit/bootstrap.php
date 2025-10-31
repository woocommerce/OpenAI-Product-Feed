<?php
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
	require dirname( __DIR__, 2 ) . '/woocommerce-product-feed-for-openai.php';
} );

// Boot the WordPress testing environment.
require $_tests_dir . '/includes/bootstrap.php';

// Manually require Woo core test files. Copied from WooCommerce tests/legacy/bootstrap.php.
if ( true ) {
	// framework.
	require_once __DIR__ . '/woo/plugins/woocommerce/tests/legacy/framework/class-wc-unit-test-factory.php';
	require_once __DIR__ . '/woo/plugins/woocommerce/tests/legacy/framework/class-wc-mock-session-handler.php';
	require_once __DIR__ . '/woo/plugins/woocommerce/tests/legacy/framework/class-wc-mock-wc-data.php';
	require_once __DIR__ . '/woo/plugins/woocommerce/tests/legacy/framework/class-wc-mock-wc-object-query.php';
	require_once __DIR__ . '/woo/plugins/woocommerce/tests/legacy/framework/class-wc-mock-payment-gateway.php';
	require_once __DIR__ . '/woo/plugins/woocommerce/tests/legacy/framework/class-wc-mock-enhanced-payment-gateway.php';
	require_once __DIR__ . '/woo/plugins/woocommerce/tests/legacy/framework/class-wc-payment-token-stub.php';
	require_once __DIR__ . '/woo/plugins/woocommerce/tests/legacy/framework/vendor/class-wp-test-spy-rest-server.php';

	// test cases.
	require_once __DIR__ . '/woo/plugins/woocommerce/tests/legacy/includes/wp-http-testcase.php';
	require_once __DIR__ . '/woo/plugins/woocommerce/tests/legacy/framework/class-wc-unit-test-case.php';
	require_once __DIR__ . '/woo/plugins/woocommerce/tests/legacy/framework/class-wc-api-unit-test-case.php';
	require_once __DIR__ . '/woo/plugins/woocommerce/tests/legacy/framework/class-wc-rest-unit-test-case.php';

	// Helpers.
	require_once __DIR__ . '/woo/plugins/woocommerce/tests/legacy/framework/helpers/class-wc-helper-product.php';
	require_once __DIR__ . '/woo/plugins/woocommerce/tests/legacy/framework/helpers/class-wc-helper-coupon.php';
	require_once __DIR__ . '/woo/plugins/woocommerce/tests/legacy/framework/helpers/class-wc-helper-fee.php';
	require_once __DIR__ . '/woo/plugins/woocommerce/tests/legacy/framework/helpers/class-wc-helper-shipping.php';
	require_once __DIR__ . '/woo/plugins/woocommerce/tests/legacy/framework/helpers/class-wc-helper-customer.php';
	require_once __DIR__ . '/woo/plugins/woocommerce/tests/legacy/framework/helpers/class-wc-helper-order.php';
	require_once __DIR__ . '/woo/plugins/woocommerce/tests/legacy/framework/helpers/class-wc-helper-shipping-zones.php';
	require_once __DIR__ . '/woo/plugins/woocommerce/tests/legacy/framework/helpers/class-wc-helper-payment-token.php';
	require_once __DIR__ . '/woo/plugins/woocommerce/tests/legacy/framework/helpers/class-wc-helper-settings.php';
	require_once __DIR__ . '/woo/plugins/woocommerce/tests/legacy/framework/helpers/class-wc-helper-reports.php';
	require_once __DIR__ . '/woo/plugins/woocommerce/tests/legacy/framework/helpers/class-wc-helper-admin-notes.php';
	require_once __DIR__ . '/woo/plugins/woocommerce/tests/legacy/framework/helpers/class-wc-test-action-queue.php';
	require_once __DIR__ . '/woo/plugins/woocommerce/tests/legacy/framework/helpers/class-wc-helper-queue.php';


	// Traits.
	require_once __DIR__ . '/woo/plugins/woocommerce/tests/legacy/framework/traits/trait-wc-rest-api-complex-meta.php';
}

/**
 * Register this autoloader late, as Jetpack prepends theirs and it looks for a different path.
 */
spl_autoload_register(
	function ($class) {
		$namespace = 'Automattic\\WooCommerce\\Testing';
		if ( 0 !== strpos( $class, $namespace ) ) {
			return;
		}

		$file = str_replace( $namespace . '\\', '', $class );
		$file = str_replace( '\\', '/', $file );
		$file .= '.php';
		$file = __DIR__ . '/woo/plugins/woocommerce/tests/' . $file;
		require_once $file;
	},
	true,
	true
);

// Replace the core container with the one for tests.
function initialize_dependency_injection() {
	$inner_container_property = new \ReflectionProperty( \Automattic\WooCommerce\Container::class, 'container' );
	$inner_container_property->setAccessible( true );

	$container       = wc_get_container();
	$inner_container = $inner_container_property->getValue( $container );
	$inner_container = new \Automattic\WooCommerce\Testing\Tools\TestingContainer( $inner_container );
	$inner_container_property->setValue( $container, $inner_container );

	$GLOBALS['wc_container'] = $inner_container;
}
initialize_dependency_injection();
