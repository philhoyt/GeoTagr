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
		$meta         = new Meta();
		$block_editor = new BlockEditor();
		$metabox      = new Metabox();

		add_action( 'init', array( $meta, 'register' ) );
		add_action( 'enqueue_block_editor_assets', array( $block_editor, 'enqueue' ) );
		add_action( 'admin_enqueue_scripts', array( $block_editor, 'enqueue_classic' ) );
		add_action( 'add_meta_boxes', array( $metabox, 'register' ) );
		add_action( 'save_post', array( $metabox, 'save' ) );
	}
}
