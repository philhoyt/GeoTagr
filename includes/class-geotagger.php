<?php
/**
 * Plugin bootstrap.
 *
 * @package GeoTagr
 */

declare(strict_types=1);

namespace GeoTagr;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class — instantiated once from the main plugin file.
 */
class GeoTagger {

	/**
	 * Bind all hooks.
	 */
	public function init(): void {
		$settings            = new Settings();
		$proxy               = new GeocodeProxy();
		$meta                = new Meta();
		$location            = new LocationTaxonomy();
		$block_editor        = new BlockEditor();
		$metabox             = new Metabox();
		$location_name_block = new LocationNameBlock();

		add_action( 'admin_menu', array( $settings, 'register' ) );
		add_action( 'rest_api_init', array( $proxy, 'register' ) );

		add_filter(
			'geo_tagr_allowed_post_types',
			static fn( array $types ): array => Settings::get( 'allowed_post_types', $types )
		);

		add_action( 'init', array( $meta, 'register' ) );
		add_action(
			'init',
			static function () use ( $location ): void {
				$location->register( (bool) Settings::get( 'taxonomy_public', false ) );
			}
		);
		add_action( 'geo_tagr_meta_saved', array( $location, 'sync' ), 10, 2 );
		add_action( 'init', array( $location_name_block, 'register' ) );
		add_action( 'enqueue_block_editor_assets', array( $block_editor, 'enqueue' ) );
		add_action( 'admin_enqueue_scripts', array( $block_editor, 'enqueue_classic' ) );
		add_action( 'add_meta_boxes', array( $metabox, 'register' ) );
		add_action( 'save_post', array( $metabox, 'save' ) );

		// Sync taxonomy terms when geo meta is written by any external caller
		// (e.g. QuickPostr's REST endpoint). Uses shutdown so all four keys are
		// guaranteed to be saved before the sync runs.
		$geo_keys = array( '_geo_tagr_lat', '_geo_tagr_lng', '_geo_tagr_place', '_geo_tagr_address' );
		$pending  = array();

		$queue = static function ( $meta_id, $post_id, $meta_key ) use ( $geo_keys, &$pending ): void {
			if ( in_array( $meta_key, $geo_keys, true ) ) {
				$pending[ $post_id ] = true;
			}
		};

		add_action( 'added_post_meta', $queue, 10, 3 );
		add_action( 'updated_post_meta', $queue, 10, 3 );

		// Dequeue posts already synced by the explicit save path (metabox / block editor)
		// so the shutdown handler doesn't duplicate the work.
		add_action(
			'geo_tagr_meta_saved',
			static function ( int $post_id ) use ( &$pending ): void {
				unset( $pending[ $post_id ] );
			}
		);

		add_action(
			'shutdown',
			static function () use ( &$pending ): void {
				foreach ( array_keys( $pending ) as $post_id ) {
					Meta::fire_saved_action( $post_id );
				}
			}
		);
	}
}
