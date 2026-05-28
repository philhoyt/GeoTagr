<?php
/**
 * Location Name block — dynamic render.
 *
 * @package GeoTagr
 */

declare(strict_types=1);

namespace GeoTagr;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders the geotagr/location-name dynamic block.
 */
class LocationNameBlock {

	/**
	 * Register the block type using block.json metadata.
	 *
	 * The editor script is registered manually from the compiled build output
	 * because the webpack entry produces build/location-name.js (flat), while
	 * block.json's "file:./index.js" would resolve to the raw JSX source.
	 */
	public function register(): void {
		$asset_file = GEOTAGR_PLUGIN_DIR . 'build/location-name.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_register_script(
			'geotagr-location-name-editor',
			GEOTAGR_PLUGIN_URL . 'build/location-name.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		register_block_type_from_metadata(
			GEOTAGR_PLUGIN_DIR . 'src/blocks/location-name',
			array(
				'editor_script_handles' => array( 'geotagr-location-name-editor' ),
				'render_callback'       => array( $this, 'render' ),
			)
		);
	}

	/**
	 * Render the block on the front end.
	 *
	 * Returns an empty string when the post has no place name set, so the
	 * block leaves no footprint in the markup for untagged posts.
	 *
	 * @param array<string, mixed> $attributes Block attributes (unused).
	 * @param string               $content    Inner block content (unused).
	 * @param \WP_Block            $block      Block instance used to resolve post context.
	 * @return string HTML output, or empty string when no place name is set.
	 */
	public function render( array $attributes, string $content, \WP_Block $block ): string {
		$post_id = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : get_the_ID();
		$place   = (string) get_post_meta( (int) $post_id, '_geo_tagr_place', true );

		if ( '' === $place ) {
			return '';
		}

		return sprintf(
			'<p %s>%s</p>',
			get_block_wrapper_attributes(),
			esc_html( $place )
		);
	}
}
