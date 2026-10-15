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
	 * Decimal places kept in `rounded` mode for REST readers who cannot edit the post (≈110 m).
	 */
	public const PUBLIC_PRECISION = 3;

	/**
	 * Valid values for the rest_location_visibility setting.
	 *
	 * @var string[]
	 */
	public const VISIBILITY_MODES = array( 'private', 'rounded', 'exact' );

	/**
	 * Coordinate keys that are redacted or rounded for readers who cannot edit the post.
	 *
	 * @var string[]
	 */
	private const GATED_COORDINATES = array( '_geo_tagr_lat', '_geo_tagr_lng' );

	/**
	 * Address key that is redacted for readers who cannot edit the post in every mode but `exact`.
	 */
	private const GATED_ADDRESS = '_geo_tagr_address';

	/**
	 * Meta key definitions: key => sanitize callback.
	 *
	 * @var array<string, array{type: string, sanitize: string, max?: int}>
	 */
	private const KEYS = array(
		'_geo_tagr_lat'     => array(
			'type'     => 'number',
			'sanitize' => 'floatval',
			'max'      => 90,
		),
		'_geo_tagr_lng'     => array(
			'type'     => 'number',
			'sanitize' => 'floatval',
			'max'      => 180,
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
	 * Register all four post meta keys, and the REST read gate for each
	 * allowed post type.
	 */
	public function register(): void {
		$post_types = apply_filters( 'geo_tagr_allowed_post_types', array( 'post' ) );

		foreach ( (array) $post_types as $post_type ) {
			foreach ( self::KEYS as $key => $config ) {
				$show_in_rest = true;
				if ( isset( $config['max'] ) ) {
					$show_in_rest = array(
						'schema' => array(
							'minimum' => -$config['max'],
							'maximum' => $config['max'],
						),
					);
				}

				register_post_meta(
					$post_type,
					$key,
					array(
						'type'              => $config['type'],
						'single'            => true,
						'show_in_rest'      => $show_in_rest,
						'sanitize_callback' => 'number' === $config['type']
								? static fn( $value ): float => (float) $value
								: $config['sanitize'],
						// Per post, not `edit_posts`: post types with their own
						// capabilities work, and editing one post never grants
						// writing location data onto another.
						'auth_callback'     => static fn( $allowed, $meta_key, $object_id ): bool => current_user_can( 'edit_post', (int) $object_id ),
					)
				);
			}

			// The meta prepare_callback cannot know which post it serves in a
			// collection, so the gate runs once the post is known.
			add_filter( "rest_prepare_{$post_type}", array( self::class, 'gate_rest_response' ), 10, 3 );
		}
	}

	/**
	 * Gate location meta in a post's REST response.
	 *
	 * Users who can edit the post receive the stored values. Everyone else
	 * gets them shaped by the rest_location_visibility setting: coordinates
	 * are zeroed (`private`) or rounded (`rounded`), the address is emptied
	 * in both, and only an explicit `exact` passes everything through. The
	 * place name is never gated. Stored values are not altered.
	 *
	 * Runs on `rest_prepare_{$post_type}`, where the post is known for single
	 * items, collections and create/update responses alike. Keys left out of
	 * the response (e.g. by `_fields`) are skipped.
	 *
	 * @since 0.10.0 Replaces the per-key prepare_callback, which checked `edit_posts`.
	 *
	 * @param \WP_REST_Response $response Response for one post.
	 * @param \WP_Post          $post     Post being prepared.
	 * @param \WP_REST_Request  $request  Current REST request.
	 * @return \WP_REST_Response
	 */
	public static function gate_rest_response( \WP_REST_Response $response, \WP_Post $post, \WP_REST_Request $request ): \WP_REST_Response {
		$data = $response->get_data();
		if ( ! is_array( $data ) || ! isset( $data['meta'] ) || ! is_array( $data['meta'] ) ) {
			return $response;
		}

		if ( current_user_can( 'edit_post', $post->ID ) ) {
			return $response;
		}

		$setting = (string) Settings::get( 'rest_location_visibility', 'private' );

		foreach ( array_merge( self::GATED_COORDINATES, array( self::GATED_ADDRESS ) ) as $key ) {
			if ( ! array_key_exists( $key, $data['meta'] ) ) {
				continue;
			}

			/**
			 * Filters how location meta is exposed to REST readers who cannot edit the post.
			 *
			 * Must return one of 'private', 'rounded' or 'exact'; anything else is
			 * treated as 'private'.
			 *
			 * @since 0.10.0 Added `$post`; readers who can edit the post bypass this filter.
			 *
			 * @param string           $visibility Visibility mode from settings.
			 * @param string           $key        Meta key being prepared.
			 * @param \WP_REST_Request $request    Current REST request.
			 * @param \WP_Post         $post       Post the value belongs to.
			 */
			$visibility = apply_filters( 'geo_tagr_rest_location_visibility', $setting, $key, $request, $post );

			if ( ! in_array( $visibility, self::VISIBILITY_MODES, true ) ) {
				$visibility = 'private';
			}

			// Only an explicit 'exact' reaches the pass-through; every other value redacts.
			if ( 'exact' === $visibility ) {
				continue;
			}

			$value = $data['meta'][ $key ];
			if ( self::GATED_ADDRESS === $key ) {
				$data['meta'][ $key ] = '';
			} else {
				// 0.0 is what core returns for an unset number, so a gated post
				// looks the same as an untagged one.
				$data['meta'][ $key ] = ( 'rounded' === $visibility && is_numeric( $value ) )
					? round( (float) $value, self::PUBLIC_PRECISION )
					: 0.0;
			}
		}

		$response->set_data( $data );
		return $response;
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
