<?php
declare(strict_types=1);

$wp_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';

if ( ! file_exists( $wp_tests_dir . '/includes/functions.php' ) ) {
	// Unit test suite — Brain Monkey handles WordPress function mocks.
	return;
}

require_once $wp_tests_dir . '/includes/functions.php';

function _manually_load_plugin(): void {
	$plugin_file = glob( dirname( __DIR__, 2 ) . '/*.php' )[0] ?? '';
	if ( $plugin_file ) {
		require $plugin_file;
	}
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $wp_tests_dir . '/includes/bootstrap.php';
