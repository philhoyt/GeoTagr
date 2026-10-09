<?php
/**
 * REST tests for the geotagr/v1/geocode proxy.
 *
 * @package GeoTagr
 */

declare(strict_types=1);

/**
 * Permission, validation, mapping and caching behaviour of the proxy.
 */
class Test_GeocodeProxy_Integration extends WP_UnitTestCase {

	private const ROUTE = '/geotagr/v1/geocode';

	/**
	 * Upstream requests captured by the pre_http_request mock.
	 *
	 * @var array<int, array{url: string, args: array}>
	 */
	private array $http_calls = array();

	/**
	 * Boot a fresh REST server and mock upstream HTTP.
	 */
	public function set_up(): void {
		parent::set_up();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );

		$this->http_calls = array();
		add_filter( 'pre_http_request', array( $this, 'mock_http' ), 10, 3 );
	}

	/**
	 * Clean up.
	 */
	public function tear_down(): void {
		remove_filter( 'pre_http_request', array( $this, 'mock_http' ) );
		delete_option( 'geotagr_settings' );
		global $wp_rest_server;
		$wp_rest_server = null;
		parent::tear_down();
	}

	/**
	 * Short-circuit upstream Google calls with canned bodies.
	 *
	 * @param false|array|WP_Error $preempt Existing preempt value.
	 * @param array                $args    Request args.
	 * @param string               $url     Request URL.
	 * @return array
	 */
	public function mock_http( $preempt, array $args, string $url ): array {
		$this->http_calls[] = array(
			'url'  => $url,
			'args' => $args,
		);

		if ( str_contains( $url, 'places:searchText' ) ) {
			$body = array(
				'places' => array(
					array(
						'displayName'      => array( 'text' => 'West Side Market' ),
						'formattedAddress' => '1979 W 25th St, Cleveland, OH',
						'location'         => array(
							'latitude'  => 41.4846,
							'longitude' => -81.7036,
						),
					),
				),
			);
		} elseif ( str_contains( $url, 'places:searchNearby' ) ) {
			$body = array( 'places' => array( array( 'displayName' => array( 'text' => 'Nearby POI' ) ) ) );
		} else {
			$body = array( 'results' => array( array( 'formatted_address' => 'Reverse Address' ) ) );
		}

		return array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode( $body ),
		);
	}

	/**
	 * Configure the Google provider with a key.
	 */
	private function use_google(): void {
		update_option(
			'geotagr_settings',
			array(
				'geocoding_provider' => 'google',
				'geocoding_api_key'  => 'test-key',
			)
		);
	}

	/**
	 * Dispatch a GET to the proxy.
	 *
	 * @param array $params Query params.
	 * @return WP_REST_Response
	 */
	private function get( array $params ): WP_REST_Response {
		$request = new WP_REST_Request( 'GET', self::ROUTE );
		foreach ( $params as $k => $v ) {
			$request->set_param( $k, $v );
		}
		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Anonymous callers are rejected.
	 */
	public function test_anonymous_is_rejected(): void {
		wp_set_current_user( 0 );
		$this->use_google();
		$this->assertSame(
			401,
			$this->get(
				array(
					'type'  => 'forward',
					'query' => 'x',
				)
			)->get_status()
		);
	}

	/**
	 * Subscribers (no edit_posts) are rejected.
	 */
	public function test_subscriber_is_rejected(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->use_google();
		$this->assertSame(
			403,
			$this->get(
				array(
					'type'  => 'forward',
					'query' => 'x',
				)
			)->get_status()
		);
	}

	/**
	 * Non-Google providers get a 400, and no upstream call is made.
	 */
	public function test_non_google_provider_is_400(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		update_option( 'geotagr_settings', array( 'geocoding_provider' => 'nominatim' ) );

		$response = $this->get(
			array(
				'type'  => 'forward',
				'query' => 'x',
			)
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'geotagr_no_proxy_needed', $response->get_data()['code'] );
		$this->assertCount( 0, $this->http_calls );
	}

	/**
	 * Arg validation: bad type enum and out-of-range coordinates.
	 */
	public function test_invalid_params_are_400(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->use_google();

		$this->assertSame( 400, $this->get( array( 'type' => 'sideways' ) )->get_status() );
		$this->assertSame(
			400,
			$this->get(
				array(
					'type' => 'reverse',
					'lat'  => 95,
					'lng'  => 0,
				)
			)->get_status()
		);
		$this->assertSame(
			400,
			$this->get(
				array(
					'type' => 'reverse',
					'lat'  => 0,
					'lng'  => 181,
				)
			)->get_status()
		);
		$this->assertSame( 'geotagr_missing_query', $this->get( array( 'type' => 'forward' ) )->get_data()['code'] );
		$this->assertSame( 'geotagr_missing_coords', $this->get( array( 'type' => 'reverse' ) )->get_data()['code'] );
		$this->assertCount( 0, $this->http_calls );
	}

	/**
	 * Forward: maps Places API (New) results, sends the key in a header, caches.
	 */
	public function test_forward_maps_results_and_caches(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->use_google();

		$response = $this->get(
			array(
				'type'  => 'forward',
				'query' => 'West Side Market',
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			array(
				array(
					'lat'     => 41.4846,
					'lng'     => -81.7036,
					'name'    => 'West Side Market',
					'address' => '1979 W 25th St, Cleveland, OH',
				),
			),
			$response->get_data()
		);
		$this->assertCount( 1, $this->http_calls );
		$this->assertStringContainsString( 'places:searchText', $this->http_calls[0]['url'] );
		$this->assertSame( 'test-key', $this->http_calls[0]['args']['headers']['X-Goog-Api-Key'] );
		$this->assertSame( 10, $this->http_calls[0]['args']['timeout'] );
		$this->assertStringStartsWith( 'GeoTagr/', $this->http_calls[0]['args']['user-agent'] );

		// Same query again is served from the transient cache.
		$again = $this->get(
			array(
				'type'  => 'forward',
				'query' => 'West Side Market',
			)
		);
		$this->assertSame( $response->get_data(), $again->get_data() );
		$this->assertCount( 1, $this->http_calls );
	}

	/**
	 * Reverse: uses Nearby Search (New) for the name and Geocoding for the address.
	 */
	public function test_reverse_uses_nearby_search_new(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->use_google();

		$response = $this->get(
			array(
				'type' => 'reverse',
				'lat'  => 41.4846,
				'lng'  => -81.7036,
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'Nearby POI', $response->get_data()['name'] );
		$this->assertSame( 'Reverse Address', $response->get_data()['address'] );
		$this->assertCount( 2, $this->http_calls );
		$this->assertStringContainsString( 'places:searchNearby', $this->http_calls[0]['url'] );
		$this->assertStringNotContainsString( 'nearbysearch/json', $this->http_calls[0]['url'] );
		$body = json_decode( $this->http_calls[0]['args']['body'], true );
		$this->assertSame( 41.4846, $body['locationRestriction']['circle']['center']['latitude'] );
	}

	/**
	 * When every upstream call fails the proxy returns an error, not a 200.
	 */
	public function test_upstream_failure_is_an_error(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->use_google();
		remove_filter( 'pre_http_request', array( $this, 'mock_http' ) );
		add_filter( 'pre_http_request', static fn() => new WP_Error( 'http_request_failed', 'down' ) );

		$response = $this->get(
			array(
				'type' => 'reverse',
				'lat'  => 1.0,
				'lng'  => 2.0,
			)
		);

		$this->assertSame( 'http_request_failed', $response->get_data()['code'] );
	}
}
