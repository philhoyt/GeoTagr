<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package GeoTagr
 */

declare(strict_types=1);

$wp_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( false === $wp_tests_dir ) {
	$wp_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $wp_tests_dir . '/includes/functions.php' ) ) {
	// Unit test suite — Brain Monkey handles WordPress function mocks.
	return;
}

define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills' );

require_once $wp_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin for integration tests.
 */
function _manually_load_plugin(): void {
	$files = glob( dirname( __DIR__, 2 ) . '/*.php' );
	if ( ! empty( $files ) ) {
		require $files[0];
	}
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $wp_tests_dir . '/includes/bootstrap.php';
