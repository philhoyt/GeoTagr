<?php
/**
 * Block editor asset enqueue.
 *
 * @package GeoTagr
 */

declare(strict_types=1);

namespace GeoTagr;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues the sidebar panel script and Leaflet CSS for the block editor.
 */
class BlockEditor {

	/**
	 * Enqueue the classic metabox script on classic editor post screens.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_classic( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$post_type     = get_post_type();
		$allowed_types = apply_filters( 'geo_tagr_allowed_post_types', array( 'post' ) );
		if ( $post_type && ! in_array( $post_type, (array) $allowed_types, true ) ) {
			return;
		}

		$asset_file = GEOTAGR_PLUGIN_DIR . 'build/classic.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'geo-tagr-classic',
			GEOTAGR_PLUGIN_URL . 'build/classic.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_localize_script(
			'geo-tagr-classic',
			'geoTagrData',
			array(
				'version'           => GEOTAGR_VERSION,
				'geocodingProvider' => Settings::get( 'geocoding_provider', 'nominatim' ),
				'geocodingApiKey'   => Settings::get( 'geocoding_api_key', '' ),
			)
		);
	}

	/**
	 * Enqueue block editor assets.
	 */
	public function enqueue(): void {
		$asset_file = GEOTAGR_PLUGIN_DIR . 'build/panel.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'geo-tagr-panel',
			GEOTAGR_PLUGIN_URL . 'build/panel.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_enqueue_style(
			'geo-tagr-panel',
			GEOTAGR_PLUGIN_URL . 'build/panel.css',
			array(),
			$asset['version']
		);

		wp_localize_script(
			'geo-tagr-panel',
			'geoTagrData',
			array(
				'version'           => GEOTAGR_VERSION,
				'geocodingProvider' => Settings::get( 'geocoding_provider', 'nominatim' ),
				'geocodingApiKey'   => Settings::get( 'geocoding_api_key', '' ),
			)
		);
	}
}
