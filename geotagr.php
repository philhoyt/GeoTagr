<?php
/**
 * Plugin Name: GeoTagr
 * Plugin URI:  https://github.com/philhoyt/geotagr
 * Description: Attach geographic location metadata to any post.
 * Version:     0.6.3
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

define( 'GEOTAGR_VERSION', '0.6.3' );
define( 'GEOTAGR_PLUGIN_FILE', __FILE__ );
define( 'GEOTAGR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GEOTAGR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once GEOTAGR_PLUGIN_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$geotagr_update_checker = PucFactory::buildUpdateChecker(
	'https://github.com/philhoyt/GeoTagr/',
	__FILE__,
	'geotagr'
);
$geotagr_update_checker->getVcsApi()->enableReleaseAssets();

require_once GEOTAGR_PLUGIN_DIR . 'includes/class-locationnameblock.php';
require_once GEOTAGR_PLUGIN_DIR . 'includes/class-meta.php';
require_once GEOTAGR_PLUGIN_DIR . 'includes/class-settings.php';
require_once GEOTAGR_PLUGIN_DIR . 'includes/class-geocodeproxy.php';
require_once GEOTAGR_PLUGIN_DIR . 'includes/class-locationtaxonomy.php';
require_once GEOTAGR_PLUGIN_DIR . 'includes/class-metabox.php';
require_once GEOTAGR_PLUGIN_DIR . 'includes/class-blockeditor.php';
require_once GEOTAGR_PLUGIN_DIR . 'includes/class-geotagger.php';

/**
 * Returns geo meta for a post. Safe to call without knowing if GeoTagr is active
 * — callers should wrap in function_exists( 'geo_tagr_get_post_meta' ).
 *
 * @param int $post_id Post ID.
 * @return array{lat: float|null, lng: float|null, place: string, address: string}|null
 */
function geo_tagr_get_post_meta( int $post_id ): ?array {
	return \GeoTagr\Meta::get( $post_id );
}

( new \GeoTagr\GeoTagger() )->init();
