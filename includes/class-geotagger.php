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
		$settings     = new Settings();
		$meta         = new Meta();
		$location     = new LocationTaxonomy();
		$block_editor = new BlockEditor();
		$metabox      = new Metabox();

		add_action( 'admin_menu', array( $settings, 'register' ) );

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
		add_action( 'enqueue_block_editor_assets', array( $block_editor, 'enqueue' ) );
		add_action( 'admin_enqueue_scripts', array( $block_editor, 'enqueue_classic' ) );
		add_action( 'add_meta_boxes', array( $metabox, 'register' ) );
		add_action( 'save_post', array( $metabox, 'save' ) );
	}
}
