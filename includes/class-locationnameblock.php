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
	 */
	public function register(): void {
		register_block_type_from_metadata(
			GEOTAGR_PLUGIN_DIR . 'src/blocks/location-name',
			array(
				'render_callback' => array( $this, 'render' ),
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
