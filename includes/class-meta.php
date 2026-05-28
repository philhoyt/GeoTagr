<?php
/**
 * Post meta registration and public helper.
 *
 * @package GeoTagr
 */

declare(strict_types=1);

namespace GeoTagr;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers geo meta keys and exposes the public helper function.
 */
class Meta {

	/**
	 * Meta key definitions: key => sanitize callback.
	 *
	 * @var array<string, array{type: string, sanitize: string}>
	 */
	private const KEYS = array(
		'_geo_tagr_lat'     => array(
			'type'     => 'number',
			'sanitize' => 'floatval',
		),
		'_geo_tagr_lng'     => array(
			'type'     => 'number',
			'sanitize' => 'floatval',
		),
		'_geo_tagr_place'   => array(
			'type'     => 'string',
			'sanitize' => 'sanitize_text_field',
		),
		'_geo_tagr_address' => array(
			'type'     => 'string',
			'sanitize' => 'sanitize_text_field',
		),
	);

	/**
	 * Register all four post meta keys.
	 */
	public function register(): void {
		$post_types = apply_filters( 'geo_tagr_allowed_post_types', array( 'post' ) );

		foreach ( (array) $post_types as $post_type ) {
			foreach ( self::KEYS as $key => $config ) {
				register_post_meta(
					$post_type,
					$key,
					array(
						'type'              => $config['type'],
						'single'            => true,
						'show_in_rest'      => true,
						'sanitize_callback' => $config['sanitize'],
						'auth_callback'     => static function (): bool {
							return current_user_can( 'edit_posts' );
						},
					)
				);
			}
		}
	}

	/**
	 * Read all four geo meta values for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array{lat: float|null, lng: float|null, place: string, address: string}|null
	 *   Null when the post has no geo data at all.
	 */
	public static function get( int $post_id ): ?array {
		$lat     = get_post_meta( $post_id, '_geo_tagr_lat', true );
		$lng     = get_post_meta( $post_id, '_geo_tagr_lng', true );
		$place   = get_post_meta( $post_id, '_geo_tagr_place', true );
		$address = get_post_meta( $post_id, '_geo_tagr_address', true );

		if ( '' === $lat && '' === $lng && '' === $place && '' === $address ) {
			return null;
		}

		$meta = array(
			'lat'     => '' !== $lat ? (float) $lat : null,
			'lng'     => '' !== $lng ? (float) $lng : null,
			'place'   => (string) $place,
			'address' => (string) $address,
		);

		/**
		 * Filters the geo meta array returned for a post.
		 *
		 * @param array{lat: float|null, lng: float|null, place: string, address: string} $meta    Meta array.
		 * @param int                                                                      $post_id Post ID.
		 */
		return apply_filters( 'geo_tagr_meta', $meta, $post_id );
	}

	/**
	 * Fire the geo_tagr_meta_saved action after meta is written.
	 *
	 * Called by Metabox::save() after all four keys are updated.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function fire_saved_action( int $post_id ): void {
		$meta = self::get( $post_id );

		/**
		 * Fires after GeoTagr meta is saved for a post.
		 *
		 * @param int                                                                                $post_id Post ID.
		 * @param array{lat: float|null, lng: float|null, place: string, address: string}|null $meta    Saved meta, or null.
		 */
		do_action( 'geo_tagr_meta_saved', $post_id, $meta );
	}
}
