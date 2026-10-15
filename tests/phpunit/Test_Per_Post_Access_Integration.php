<?php
/**
 * Per-post access to geo meta and the geocoding proxy.
 *
 * Uses an `event` post type with its own capabilities, and an
 * `event_author` role that can edit only its own events and holds no
 * `edit_posts`, to cover sites whose GeoTagr post types do not use the
 * plain post capabilities.
 *
 * @package GeoTagr
 */

declare(strict_types=1);

/**
 * Who may read exact locations, write location meta and use the proxy.
 */
class Test_Per_Post_Access_Integration extends WP_UnitTestCase {

	private const LAT     = 41.4993;
	private const LNG     = -81.6944;
	private const PLACE   = 'West Side Market';
	private const ADDRESS = '1979 W 25th St, Cleveland, OH';

	/**
	 * Owner of the geotagged posts.
	 *
	 * @var int
	 */
	private int $owner;

	/**
	 * An event_author who owns other events, not the tagged one.
	 *
	 * @var int
	 */
	private int $peer;

	/**
	 * An Author: holds edit_posts but does not own the tagged posts.
	 *
	 * @var int
	 */
	private int $author;

	/**
	 * Allow `post` and `event`, register an event type with its own caps.
	 */
	public function set_up(): void {
		parent::set_up();

		register_post_type(
			'event',
			array(
				'public'          => true,
				'show_in_rest'    => true,
				'rest_base'       => 'events',
				'supports'        => array( 'title', 'editor', 'author', 'custom-fields' ),
				'capability_type' => array( 'event', 'events' ),
				'map_meta_cap'    => true,
			)
		);
		if ( ! get_role( 'event_author' ) ) {
			add_role(
				'event_author',
				'Event Author',
				array(
					'read'                  => true,
					'edit_events'           => true,
					'edit_published_events' => true,
					'publish_events'        => true,
				)
			);
		}
		get_role( 'administrator' )->add_cap( 'edit_others_events' );

		add_filter( 'geo_tagr_allowed_post_types', array( $this, 'allowed_post_types' ), 99 );
		( new \GeoTagr\Meta() )->register();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );

		$this->owner  = self::factory()->user->create( array( 'role' => 'event_author' ) );
		$this->peer   = self::factory()->user->create( array( 'role' => 'event_author' ) );
		$this->author = self::factory()->user->create( array( 'role' => 'author' ) );
	}

	/**
	 * Undo the post type, role, filters and server.
	 */
	public function tear_down(): void {
		remove_filter( 'geo_tagr_allowed_post_types', array( $this, 'allowed_post_types' ), 99 );
		remove_all_filters( 'geo_tagr_rest_location_visibility' );
		remove_all_filters( 'geo_tagr_can_geocode' );
		remove_filter( 'rest_prepare_event', array( \GeoTagr\Meta::class, 'gate_rest_response' ) );
		get_role( 'administrator' )->remove_cap( 'edit_others_events' );
		unregister_post_type( 'event' );
		remove_role( 'event_author' );
		delete_option( 'geotagr_settings' );
		global $wp_rest_server;
		$wp_rest_server = null;
		parent::tear_down();
	}

	/**
	 * Post types GeoTagr is enabled for in these tests.
	 *
	 * @return string[]
	 */
	public function allowed_post_types(): array {
		return array( 'post', 'event' );
	}

	/**
	 * Create a published, geotagged post.
	 *
	 * @param string $type      Post type.
	 * @param int    $author_id Post author.
	 * @return int Post ID.
	 */
	private function tagged( string $type, int $author_id ): int {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => $type,
				'post_status' => 'publish',
				'post_author' => $author_id,
			)
		);
		update_post_meta( $post_id, '_geo_tagr_lat', self::LAT );
		update_post_meta( $post_id, '_geo_tagr_lng', self::LNG );
		update_post_meta( $post_id, '_geo_tagr_place', self::PLACE );
		update_post_meta( $post_id, '_geo_tagr_address', self::ADDRESS );
		return $post_id;
	}

	/**
	 * REST base for a post type.
	 *
	 * @param string $type Post type.
	 * @return string
	 */
	private function base( string $type ): string {
		return 'post' === $type ? 'posts' : 'events';
	}

	/**
	 * Meta for one post, as each response shape returns it.
	 *
	 * @param string $type    Post type.
	 * @param int    $post_id Post ID.
	 * @return array<string, array<string, mixed>> Shape name => meta.
	 */
	private function meta_in_every_shape( string $type, int $post_id ): array {
		$base = $this->base( $type );

		$single = rest_do_request( new WP_REST_Request( 'GET', "/wp/v2/{$base}/{$post_id}" ) );
		$this->assertSame( 200, $single->get_status() );

		$request = new WP_REST_Request( 'GET', "/wp/v2/{$base}" );
		$request->set_param( 'per_page', 100 );
		// A spoofed id must not steer the gate for the other items.
		$request->set_param( 'id', $post_id );
		$collection = null;
		foreach ( rest_do_request( $request )->get_data() as $item ) {
			if ( $item['id'] === $post_id ) {
				$collection = $item['meta'];
			}
		}
		$this->assertNotNull( $collection, 'Post found in the collection.' );

		$request = new WP_REST_Request( 'GET', "/wp/v2/{$base}/{$post_id}" );
		$request->set_param( '_fields', 'id,meta._geo_tagr_lat,meta._geo_tagr_address' );
		// rest_do_request() skips rest_post_dispatch, where the server trims to _fields.
		$fields = rest_filter_response_fields( rest_do_request( $request ), rest_get_server(), $request )->get_data()['meta'];
		$this->assertSame( array( '_geo_tagr_lat', '_geo_tagr_address' ), array_keys( $fields ), '_fields keeps only the requested keys.' );

		return array(
			'single'     => $single->get_data()['meta'],
			'collection' => $collection,
			'_fields'    => $fields,
		);
	}

	/**
	 * Assert one meta array matches a visibility mode.
	 *
	 * @param string               $mode  exact|rounded|private.
	 * @param array<string, mixed> $meta  Meta from a response (possibly partial).
	 * @param string               $label Assertion context.
	 */
	private function assert_shape( string $mode, array $meta, string $label ): void {
		$expected = array(
			'exact'   => array( self::LAT, self::LNG, self::ADDRESS ),
			'rounded' => array( round( self::LAT, 3 ), round( self::LNG, 3 ), '' ),
			'private' => array( 0.0, 0.0, '' ),
		)[ $mode ];

		if ( array_key_exists( '_geo_tagr_lat', $meta ) ) {
			$this->assertEqualsWithDelta( $expected[0], (float) $meta['_geo_tagr_lat'], 0.000001, "lat: $label" );
		}
		if ( array_key_exists( '_geo_tagr_lng', $meta ) ) {
			$this->assertEqualsWithDelta( $expected[1], (float) $meta['_geo_tagr_lng'], 0.000001, "lng: $label" );
		}
		if ( array_key_exists( '_geo_tagr_address', $meta ) ) {
			$this->assertSame( $expected[2], $meta['_geo_tagr_address'], "address: $label" );
		}
		if ( array_key_exists( '_geo_tagr_place', $meta ) ) {
			$this->assertSame( self::PLACE, $meta['_geo_tagr_place'], "place is never gated: $label" );
		}
	}

	/**
	 * The stored values never change, whatever was served.
	 *
	 * @param int $post_id Post ID.
	 */
	private function assert_stored_untouched( int $post_id ): void {
		$this->assertEqualsWithDelta( self::LAT, (float) get_post_meta( $post_id, '_geo_tagr_lat', true ), 0.000001 );
		$this->assertEqualsWithDelta( self::LNG, (float) get_post_meta( $post_id, '_geo_tagr_lng', true ), 0.000001 );
		$this->assertSame( self::ADDRESS, get_post_meta( $post_id, '_geo_tagr_address', true ) );
	}

	/**
	 * Requester × response shape × visibility mode, on both post types.
	 */
	public function test_exact_values_only_for_users_who_can_edit_the_post(): void {
		foreach ( array( 'post', 'event' ) as $type ) {
			$owner   = 'post' === $type ? self::factory()->user->create( array( 'role' => 'author' ) ) : $this->owner;
			$post_id = $this->tagged( $type, $owner );

			$requesters = array(
				'anonymous'               => 0,
				'owner'                   => $owner,
				'author not owning it'    => $this->author,
				'event_author not owning' => $this->peer,
			);

			foreach ( array( 'private', 'rounded', 'exact' ) as $mode ) {
				update_option( 'geotagr_settings', array( 'rest_location_visibility' => $mode ) );

				foreach ( $requesters as $who => $user_id ) {
					wp_set_current_user( $user_id );
					$expected = current_user_can( 'edit_post', $post_id ) ? 'exact' : $mode;
					if ( $owner === $user_id ) {
						$this->assertSame( 'exact', $expected, "The owner can edit their $type." );
					}

					foreach ( $this->meta_in_every_shape( $type, $post_id ) as $shape => $meta ) {
						$this->assert_shape( $expected, $meta, "$type / $mode / $who / $shape" );
					}
				}
			}

			$this->assert_stored_untouched( $post_id );
		}
	}

	/**
	 * Editing posts in general no longer reveals every post's location.
	 */
	public function test_edit_posts_alone_does_not_reveal_other_posts(): void {
		$post_id = $this->tagged( 'post', self::factory()->user->create( array( 'role' => 'editor' ) ) );
		wp_set_current_user( $this->author );

		$this->assertTrue( current_user_can( 'edit_posts' ) );
		$this->assert_shape( 'private', $this->meta_in_every_shape( 'post', $post_id )['single'], 'author on an editor post' );
	}

	/**
	 * The create response carries exact values for the creator.
	 */
	public function test_create_response_is_exact_for_the_creator(): void {
		wp_set_current_user( $this->owner );

		$request = new WP_REST_Request( 'POST', '/wp/v2/events' );
		$request->set_body_params(
			array(
				'title'  => 'Opening',
				'status' => 'publish',
				'meta'   => array(
					'_geo_tagr_lat'     => self::LAT,
					'_geo_tagr_lng'     => self::LNG,
					'_geo_tagr_place'   => self::PLACE,
					'_geo_tagr_address' => self::ADDRESS,
				),
			)
		);
		$response = rest_do_request( $request );

		$this->assertSame( 201, $response->get_status() );
		$this->assert_shape( 'exact', $response->get_data()['meta'], 'create response' );
		$this->assert_stored_untouched( $response->get_data()['id'] );
	}

	/**
	 * The visibility filter receives the post and still fails closed.
	 */
	public function test_visibility_filter_receives_the_post(): void {
		$post_id = $this->tagged( 'event', $this->owner );
		$seen    = array();
		add_filter(
			'geo_tagr_rest_location_visibility',
			static function ( $mode, $key, $request, $post ) use ( &$seen ) {
				$seen[] = $post->ID;
				return 'exact';
			},
			10,
			4
		);
		wp_set_current_user( 0 );

		$this->assert_shape( 'exact', $this->meta_in_every_shape( 'event', $post_id )['single'], 'filter opened it' );
		$this->assertContains( $post_id, $seen );

		remove_all_filters( 'geo_tagr_rest_location_visibility' );
		add_filter( 'geo_tagr_rest_location_visibility', static fn() => 'public' );
		$this->assert_shape( 'private', $this->meta_in_every_shape( 'event', $post_id )['single'], 'unknown mode fails closed' );
	}

	/**
	 * Meta writes follow edit_post on the post, not edit_posts.
	 */
	public function test_meta_writes_are_allowed_on_own_posts_only(): void {
		$own    = $this->tagged( 'event', $this->owner );
		$others = $this->tagged( 'event', $this->peer );
		wp_set_current_user( $this->owner );

		foreach ( array( '_geo_tagr_lat', '_geo_tagr_lng', '_geo_tagr_place', '_geo_tagr_address' ) as $key ) {
			$this->assertTrue( current_user_can( 'edit_post_meta', $own, $key ), "$key on own event" );
			$this->assertFalse( current_user_can( 'edit_post_meta', $others, $key ), "$key on another's event" );
		}

		$request = new WP_REST_Request( 'POST', "/wp/v2/events/{$own}" );
		$request->set_body_params( array( 'meta' => array( '_geo_tagr_lat' => 45.0 ) ) );
		$this->assertSame( 200, rest_do_request( $request )->get_status() );
		$this->assertEqualsWithDelta( 45.0, (float) get_post_meta( $own, '_geo_tagr_lat', true ), 0.000001 );

		$request = new WP_REST_Request( 'POST', "/wp/v2/events/{$others}" );
		$request->set_body_params( array( 'meta' => array( '_geo_tagr_lat' => 45.0 ) ) );
		$this->assertContains( rest_do_request( $request )->get_status(), array( 401, 403 ) );
		$this->assert_stored_untouched( $others );
	}

	/**
	 * The meta permission answers for the user asked about, whoever is logged in.
	 */
	public function test_meta_permission_is_checked_for_the_user_asked_about(): void {
		$own = $this->tagged( 'event', $this->owner );

		foreach ( array( 0, $this->peer, $this->owner ) as $current ) {
			wp_set_current_user( $current );
			$this->assertTrue( user_can( $this->owner, 'edit_post_meta', $own, '_geo_tagr_lat' ), "owner, current user $current" );
			$this->assertFalse( user_can( $this->peer, 'edit_post_meta', $own, '_geo_tagr_lat' ), "peer, current user $current" );
		}
	}

	/**
	 * The proxy admits anyone who can edit an allowed post type.
	 */
	public function test_proxy_allows_users_who_can_edit_an_allowed_post_type(): void {
		// Nominatim makes the proxy answer 400 after the permission check, with no HTTP call.
		update_option( 'geotagr_settings', array( 'geocoding_provider' => 'nominatim' ) );

		$status = function ( int $user_id ): int {
			wp_set_current_user( $user_id );
			$request = new WP_REST_Request( 'GET', '/geotagr/v1/geocode' );
			$request->set_param( 'type', 'forward' );
			$request->set_param( 'query', 'x' );
			return rest_do_request( $request )->get_status();
		};

		$this->assertSame( 400, $status( $this->owner ), 'event_author, no edit_posts' );
		$this->assertSame( 400, $status( $this->author ), 'edit_posts still suffices' );
		$this->assertSame( 403, $status( self::factory()->user->create( array( 'role' => 'subscriber' ) ) ) );

		remove_filter( 'geo_tagr_allowed_post_types', array( $this, 'allowed_post_types' ), 99 );
		$this->assertSame( 403, $status( $this->owner ), 'Not when events are not GeoTagr-enabled.' );

		add_filter( 'geo_tagr_can_geocode', '__return_false' );
		$this->assertSame( 403, $status( self::factory()->user->create( array( 'role' => 'editor' ) ) ), 'The filter can deny.' );
	}
}
