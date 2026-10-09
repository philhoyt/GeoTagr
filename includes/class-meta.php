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
	 * Decimal places kept for anonymous REST readers in `rounded` mode (≈110 m).
	 */
	public const PUBLIC_PRECISION = 3;

	/**
	 * Valid values for the rest_location_visibility setting.
	 *
	 * @var string[]
	 */
	public const VISIBILITY_MODES = array( 'private', 'rounded', 'exact' );

	/**
	 * Coordinate keys that are redacted or rounded for anonymous readers.
	 *
	 * @var string[]
	 */
	private const GATED_COORDINATES = array( '_geo_tagr_lat', '_geo_tagr_lng' );

	/**
	 * Address key that is redacted for anonymous readers in every mode but `exact`.
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
	 * Register all four post meta keys.
	 */
	public function register(): void {
		$post_types = apply_filters( 'geo_tagr_allowed_post_types', array( 'post' ) );

		foreach ( (array) $post_types as $post_type ) {
			foreach ( self::KEYS as $key => $config ) {
				// Each key gets its own closure capturing $key, so a lookup miss
				// can never fail open (see prepare_rest_value()).
				$show_in_rest = array(
					'prepare_callback' => static fn( $value, $request, $args ) => self::prepare_rest_value( $key, $value, $request, $args ),
				);
				if ( isset( $config['max'] ) ) {
					$show_in_rest['schema'] = array(
						'minimum' => -$config['max'],
						'maximum' => $config['max'],
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
						'auth_callback'     => static function (): bool {
							return current_user_can( 'edit_posts' );
						},
					)
				);
			}
		}
	}

	/**
	 * Gate a meta value on its way out through the REST API.
	 *
	 * Users with edit_posts always receive the stored value. Everyone else gets
	 * the value shaped by the rest_location_visibility setting: coordinates are
	 * emptied (`private`) or rounded (`rounded`), the address is emptied in both,
	 * and only an explicit `exact` passes everything through. The place name is
	 * never gated. Stored values are not altered.
	 *
	 * @param string           $key     Meta key this callback was registered for.
	 * @param mixed            $value   Stored value.
	 * @param \WP_REST_Request $request Current REST request.
	 * @param array            $args    Registered field arguments (schema etc.).
	 * @return mixed Value prepared for the response.
	 */
	public static function prepare_rest_value( string $key, mixed $value, \WP_REST_Request $request, array $args ): mixed {
		if ( current_user_can( 'edit_posts' ) ) {
			return \WP_REST_Meta_Fields::prepare_value( $value, $request, $args );
		}

		$visibility = (string) Settings::get( 'rest_location_visibility', 'private' );

		/**
		 * Filters how location meta is exposed to REST readers without edit_posts.
		 *
		 * Must return one of 'private', 'rounded' or 'exact'; anything else is
		 * treated as 'private'.
		 *
		 * @param string           $visibility Visibility mode from settings.
		 * @param string           $key        Meta key being prepared.
		 * @param \WP_REST_Request $request    Current REST request.
		 */
		$visibility = apply_filters( 'geo_tagr_rest_location_visibility', $visibility, $key, $request );

		if ( ! in_array( $visibility, self::VISIBILITY_MODES, true ) ) {
			$visibility = 'private';
		}

		// Only an explicit 'exact' reaches the pass-through; every other value redacts.
		if ( 'exact' === $visibility ) {
			return \WP_REST_Meta_Fields::prepare_value( $value, $request, $args );
		}

		if ( in_array( $key, self::GATED_COORDINATES, true ) ) {
			// An unset coordinate stays '' so core's empty-value handling applies.
			$value = ( 'rounded' === $visibility && is_numeric( $value ) )
				? round( (float) $value, self::PUBLIC_PRECISION )
				: '';
		} elseif ( self::GATED_ADDRESS === $key ) {
			$value = '';
		}

		return \WP_REST_Meta_Fields::prepare_value( $value, $request, $args );
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
