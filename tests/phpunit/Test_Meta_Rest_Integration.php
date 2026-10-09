<?php
/**
 * REST read-gate tests for geo post meta.
 *
 * @package GeoTagr
 */

declare(strict_types=1);

/**
 * Anonymous and authenticated REST reads under each rest_location_visibility mode.
 */
class Test_Meta_Rest_Integration extends WP_UnitTestCase {

	private const LAT     = 41.4993;
	private const LNG     = -81.6944;
	private const PLACE   = 'West Side Market';
	private const ADDRESS = '1979 W 25th St, Cleveland, OH';

	/**
	 * Fresh REST server per test, with the plugin's meta re-registered: the
	 * test framework unregisters all non-core meta keys in tear_down().
	 */
	public function set_up(): void {
		parent::set_up();
		( new \GeoTagr\Meta() )->register();
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );
	}

	/**
	 * Clean up option and server.
	 */
	public function tear_down(): void {
		delete_option( 'geotagr_settings' );
		remove_all_filters( 'geo_tagr_rest_location_visibility' );
		global $wp_rest_server;
		$wp_rest_server = null;
		parent::tear_down();
	}

	/**
	 * Create a published, geotagged post.
	 *
	 * @return int Post ID.
	 */
	private function tagged_post(): int {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		update_post_meta( $post_id, '_geo_tagr_lat', self::LAT );
		update_post_meta( $post_id, '_geo_tagr_lng', self::LNG );
		update_post_meta( $post_id, '_geo_tagr_place', self::PLACE );
		update_post_meta( $post_id, '_geo_tagr_address', self::ADDRESS );
		return $post_id;
	}

	/**
	 * Store the visibility setting.
	 *
	 * @param string $mode private|rounded|exact.
	 */
	private function set_visibility( string $mode ): void {
		update_option( 'geotagr_settings', array( 'rest_location_visibility' => $mode ) );
	}

	/**
	 * GET a single post's meta via REST.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed> Meta array.
	 */
	private function get_single_meta( int $post_id ): array {
		$response = rest_do_request( new WP_REST_Request( 'GET', "/wp/v2/posts/{$post_id}" ) );
		$this->assertSame( 200, $response->get_status() );
		return $response->get_data()['meta'];
	}

	/**
	 * GET the posts collection and return the meta for one post.
	 *
	 * @param int $post_id Post ID to find.
	 * @return array<string, mixed> Meta array.
	 */
	private function get_collection_meta( int $post_id ): array {
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'per_page', 100 );
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		foreach ( $response->get_data() as $item ) {
			if ( $item['id'] === $post_id ) {
				return $item['meta'];
			}
		}
		$this->fail( 'Post not found in collection response.' );
	}

	/**
	 * Assert the private shape: zeroed coordinates, empty address, intact place.
	 *
	 * @param array<string, mixed> $meta Meta from the response.
	 */
	private function assert_private( array $meta ): void {
		$this->assertSame( 0.0, (float) $meta['_geo_tagr_lat'] );
		$this->assertSame( 0.0, (float) $meta['_geo_tagr_lng'] );
		$this->assertSame( '', $meta['_geo_tagr_address'] );
		$this->assertSame( self::PLACE, $meta['_geo_tagr_place'] );
	}

	/**
	 * Default (private): anonymous readers get no coordinates or address, on both routes.
	 */
	public function test_private_default_redacts_for_anonymous(): void {
		wp_set_current_user( 0 );
		$post_id = $this->tagged_post();

		$this->assert_private( $this->get_single_meta( $post_id ) );
		$this->assert_private( $this->get_collection_meta( $post_id ) );
	}

	/**
	 * Editors receive every stored value unmodified in every mode.
	 */
	public function test_editor_receives_full_values_in_every_mode(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$post_id = $this->tagged_post();

		foreach ( array( 'private', 'rounded', 'exact' ) as $mode ) {
			$this->set_visibility( $mode );
			foreach ( array( $this->get_single_meta( $post_id ), $this->get_collection_meta( $post_id ) ) as $meta ) {
				$this->assertEqualsWithDelta( self::LAT, $meta['_geo_tagr_lat'], 0.000001, "lat in {$mode}" );
				$this->assertEqualsWithDelta( self::LNG, $meta['_geo_tagr_lng'], 0.000001, "lng in {$mode}" );
				$this->assertSame( self::ADDRESS, $meta['_geo_tagr_address'], "address in {$mode}" );
				$this->assertSame( self::PLACE, $meta['_geo_tagr_place'], "place in {$mode}" );
			}
		}
	}

	/**
	 * Rounded: coordinates to 3 decimals, address still empty.
	 */
	public function test_rounded_rounds_coordinates_and_hides_address(): void {
		wp_set_current_user( 0 );
		$this->set_visibility( 'rounded' );
		$post_id = $this->tagged_post();

		foreach ( array( $this->get_single_meta( $post_id ), $this->get_collection_meta( $post_id ) ) as $meta ) {
			$this->assertSame( round( self::LAT, 3 ), $meta['_geo_tagr_lat'] );
			$this->assertSame( round( self::LNG, 3 ), $meta['_geo_tagr_lng'] );
			$this->assertNotEquals( self::LAT, $meta['_geo_tagr_lat'] );
			$this->assertSame( '', $meta['_geo_tagr_address'] );
			$this->assertSame( self::PLACE, $meta['_geo_tagr_place'] );
		}
	}

	/**
	 * Exact: everything passes through, matching stored values.
	 */
	public function test_exact_returns_stored_values(): void {
		wp_set_current_user( 0 );
		$this->set_visibility( 'exact' );
		$post_id = $this->tagged_post();

		foreach ( array( $this->get_single_meta( $post_id ), $this->get_collection_meta( $post_id ) ) as $meta ) {
			$this->assertEqualsWithDelta( self::LAT, $meta['_geo_tagr_lat'], 0.000001 );
			$this->assertEqualsWithDelta( self::LNG, $meta['_geo_tagr_lng'], 0.000001 );
			$this->assertSame( self::ADDRESS, $meta['_geo_tagr_address'] );
			$this->assertSame( self::PLACE, $meta['_geo_tagr_place'] );
		}
	}

	/**
	 * A filter returning an unknown mode fails closed to private.
	 */
	public function test_filter_with_unknown_value_fails_closed(): void {
		wp_set_current_user( 0 );
		$this->set_visibility( 'exact' );
		add_filter( 'geo_tagr_rest_location_visibility', static fn() => 'public' );
		$post_id = $this->tagged_post();

		$this->assert_private( $this->get_single_meta( $post_id ) );
		$this->assert_private( $this->get_collection_meta( $post_id ) );
	}

	/**
	 * The filter can open or close the gate per request with a valid mode.
	 */
	public function test_filter_can_override_with_valid_mode(): void {
		wp_set_current_user( 0 );
		$this->set_visibility( 'private' );
		add_filter( 'geo_tagr_rest_location_visibility', static fn() => 'exact' );
		$post_id = $this->tagged_post();

		$meta = $this->get_single_meta( $post_id );
		$this->assertEqualsWithDelta( self::LAT, $meta['_geo_tagr_lat'], 0.000001 );
		$this->assertSame( self::ADDRESS, $meta['_geo_tagr_address'] );
	}

	/**
	 * An untagged post looks the same as a gated one — no existence oracle.
	 */
	public function test_untagged_post_is_indistinguishable_from_gated(): void {
		wp_set_current_user( 0 );
		$tagged   = $this->tagged_post();
		$untagged = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$gated = $this->get_single_meta( $tagged );
		$empty = $this->get_single_meta( $untagged );

		foreach ( array( '_geo_tagr_lat', '_geo_tagr_lng', '_geo_tagr_address' ) as $key ) {
			$this->assertSame( $empty[ $key ], $gated[ $key ], $key );
		}
	}
}
