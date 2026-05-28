<?php
/**
 * Plugin Name: GeoTagr
 * Plugin URI:  https://github.com/philhoyt/geotagr
 * Description: A WordPress plugin for geotagging content.
 * Version:     0.1.0
 * Requires at least: 6.7
 * Requires PHP: 8.2
 * Author:      Phil Hoyt
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: geotagr
 * Domain Path: /languages
 *
 * @package GeoTagr
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GEOTAGR_VERSION', '0.1.0' );
define( 'GEOTAGR_PLUGIN_FILE', __FILE__ );
define( 'GEOTAGR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GEOTAGR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
