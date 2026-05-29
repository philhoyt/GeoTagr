<?php
/**
 * Server-side geocoding proxy — required for APIs that block CORS (Google Places).
 *
 * @package GeoTagr
 */

declare(strict_types=1);

namespace GeoTagr;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers a REST endpoint that proxies Google Places API calls server-side,
 * keeping the API key out of the browser and sidestepping CORS restrictions.
 *
 * Nominatim and Mapbox support CORS and are called directly from JS.
 */
class GeocodeProxy {

	private const ROUTE     = '/geocode';
	private const NAMESPACE = 'geotagr/v1';

	private const GOOGLE_PLACES_SEARCH = 'https://places.googleapis.com/v1/places:searchText';
	private const GOOGLE_NEARBYSEARCH  = 'https://maps.googleapis.com/maps/api/place/nearbysearch/json';
	private const GOOGLE_GEOCODE       = 'https://maps.googleapis.com/maps/api/geocode/json';

	/**
	 * Register the REST route.
	 */
	public function register(): void {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => static fn(): bool => current_user_can( 'edit_posts' ),
				'args'                => array(
					'type'  => array(
						'required'          => true,
						'type'              => 'string',
						'enum'              => array( 'forward', 'reverse' ),
						'sanitize_callback' => 'sanitize_key',
					),
					'query' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'lat'   => array(
						'type'              => 'number',
						'validate_callback' => static fn( $v ): bool => is_numeric( $v ) && abs( (float) $v ) <= 90,
					),
					'lng'   => array(
						'type'              => 'number',
						'validate_callback' => static fn( $v ): bool => is_numeric( $v ) && abs( (float) $v ) <= 180,
					),
				),
			)
		);
	}

	/**
	 * Route the request to the correct provider handler.
	 *
	 * @param \WP_REST_Request $request Incoming REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$provider = Settings::get( 'geocoding_provider', 'nominatim' );
		$api_key  = Settings::get( 'geocoding_api_key', '' );

		if ( 'google' !== $provider || empty( $api_key ) ) {
			return new \WP_Error(
				'geotagr_no_proxy_needed',
				__( 'This provider does not require a server-side proxy.', 'geotagr' ),
				array( 'status' => 400 )
			);
		}

		$type = $request->get_param( 'type' );

		if ( 'forward' === $type ) {
			$query = $request->get_param( 'query' );
			if ( empty( $query ) ) {
				return new \WP_Error( 'geotagr_missing_query', __( 'query is required for forward geocoding.', 'geotagr' ), array( 'status' => 400 ) );
			}
			$bias_lat = $request->get_param( 'lat' );
			$bias_lng = $request->get_param( 'lng' );
			$bias     = ( null !== $bias_lat && null !== $bias_lng )
				? array(
					'lat' => (float) $bias_lat,
					'lng' => (float) $bias_lng,
				)
				: null;
			return $this->google_forward( $query, $api_key, $bias );
		}

		$lat = $request->get_param( 'lat' );
		$lng = $request->get_param( 'lng' );

		if ( null === $lat || null === $lng ) {
			return new \WP_Error( 'geotagr_missing_coords', __( 'lat and lng are required for reverse geocoding.', 'geotagr' ), array( 'status' => 400 ) );
		}

		return $this->google_reverse( (float) $lat, (float) $lng, $api_key );
	}

	/**
	 * Forward geocode via Google Places Text Search.
	 *
	 * @param string     $query   User-supplied search string.
	 * @param string     $api_key Google API key.
	 * @param array|null $bias    Optional { lat, lng } to pass as a 50 km locationBias circle.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function google_forward( string $query, string $api_key, ?array $bias = null ): \WP_REST_Response|\WP_Error {
		$body = array(
			'textQuery' => $query,
			'pageSize'  => 5,
		);

		if ( $bias ) {
			$body['locationBias'] = array(
				'circle' => array(
					'center' => array(
						'latitude'  => $bias['lat'],
						'longitude' => $bias['lng'],
					),
					'radius' => 50000.0,
				),
			);
		}

		$response = wp_remote_post(
			self::GOOGLE_PLACES_SEARCH,
			array(
				'headers' => array(
					'Content-Type'     => 'application/json',
					'X-Goog-Api-Key'   => $api_key,
					'X-Goog-FieldMask' => 'places.displayName,places.formattedAddress,places.location',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$raw  = $data['places'] ?? array();

		if ( empty( $raw ) ) {
			return new \WP_REST_Response(
				array(
					'results'       => array(),
					'google_status' => isset( $data['error'] ) ? $data['error']['message'] : 'ZERO_RESULTS',
				),
				200
			);
		}

		return new \WP_REST_Response(
			array_map(
				static function ( array $r ): array {
					return array(
						'lat'     => $r['location']['latitude'],
						'lng'     => $r['location']['longitude'],
						'name'    => $r['displayName']['text'] ?? '',
						'address' => $r['formattedAddress'] ?? '',
					);
				},
				$raw
			),
			200
		);
	}

	/**
	 * Reverse geocode via Google Places Nearby Search + Geocoding API.
	 *
	 * Nearby Search finds the POI name; Geocoding gives the formatted address.
	 *
	 * @param float  $lat     Latitude.
	 * @param float  $lng     Longitude.
	 * @param string $api_key Google API key.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function google_reverse( float $lat, float $lng, string $api_key ): \WP_REST_Response|\WP_Error {
		$nearby = wp_remote_get(
			add_query_arg(
				array(
					'location' => "{$lat},{$lng}",
					'radius'   => 100,
					'key'      => $api_key,
				),
				self::GOOGLE_NEARBYSEARCH
			)
		);

		$geocode = wp_remote_get(
			add_query_arg(
				array(
					'latlng' => "{$lat},{$lng}",
					'key'    => $api_key,
				),
				self::GOOGLE_GEOCODE
			)
		);

		$nearby_data  = is_wp_error( $nearby ) ? array() : json_decode( wp_remote_retrieve_body( $nearby ), true );
		$geocode_data = is_wp_error( $geocode ) ? array() : json_decode( wp_remote_retrieve_body( $geocode ), true );

		return new \WP_REST_Response(
			array(
				'lat'     => $lat,
				'lng'     => $lng,
				'name'    => $nearby_data['results'][0]['name'] ?? '',
				'address' => $geocode_data['results'][0]['formatted_address'] ?? '',
			),
			200
		);
	}
}
