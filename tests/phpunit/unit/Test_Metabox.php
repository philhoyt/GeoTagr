<?php
/**
 * Unit tests for GeoTagr\Metabox.
 *
 * @package GeoTagr
 */

declare(strict_types=1);

use GeoTagr\Metabox;

/**
 * Tests for GeoTagr\Metabox.
 */
class Test_Metabox extends WP_UnitTestCase {

	/**
	 * Metabox under test.
	 *
	 * @var Metabox
	 */
	private Metabox $metabox;

	/**
	 * Set up the test case.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->metabox = new Metabox();
	}

	/**
	 * Test that save() rejects a request without a valid nonce.
	 */
	public function test_save_rejects_missing_nonce(): void {
		$post_id = self::factory()->post->create();

		// Ensure no nonce is set in $_POST.
		unset( $_POST['_geo_tagr_nonce'] );
		$_POST['geo_tagr_lat'] = '41.4993';

		$this->metabox->save( $post_id );

		$this->assertSame( '', get_post_meta( $post_id, '_geo_tagr_lat', true ) );
	}

	/**
	 * Test that save() rejects a request with an invalid nonce.
	 */
	public function test_save_rejects_invalid_nonce(): void {
		$post_id = self::factory()->post->create();

		$_POST['_geo_tagr_nonce'] = 'invalid-nonce-value';
		$_POST['geo_tagr_lat']    = '41.4993';

		$this->metabox->save( $post_id );

		$this->assertSame( '', get_post_meta( $post_id, '_geo_tagr_lat', true ) );
	}

	/**
	 * Post a valid metabox submission as the given user.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string,string> $fields  Field values.
	 */
	private function submit( int $post_id, array $fields ): void {
		$_POST = array_merge( array( '_geo_tagr_nonce' => wp_create_nonce( 'geo_tagr_metabox' ) ), $fields );
		$this->metabox->save( $post_id );
	}

	/**
	 * A valid submission writes all four keys, with coordinates stored as floats.
	 */
	public function test_save_writes_all_fields(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$post_id = self::factory()->post->create();
		$fired   = did_action( 'geo_tagr_meta_saved' );

		$this->submit(
			$post_id,
			array(
				'geo_tagr_lat'     => ' 41.4993 ',
				'geo_tagr_lng'     => '-81.6944',
				'geo_tagr_place'   => '<b>Cleveland</b>',
				'geo_tagr_address' => 'Cleveland, OH',
			)
		);

		$this->assertEqualsWithDelta( 41.4993, (float) get_post_meta( $post_id, '_geo_tagr_lat', true ), 0.00001 );
		$this->assertEqualsWithDelta( -81.6944, (float) get_post_meta( $post_id, '_geo_tagr_lng', true ), 0.00001 );
		$this->assertSame( 'Cleveland', get_post_meta( $post_id, '_geo_tagr_place', true ) );
		$this->assertSame( 'Cleveland, OH', get_post_meta( $post_id, '_geo_tagr_address', true ) );
		$this->assertSame( $fired + 1, did_action( 'geo_tagr_meta_saved' ) );
	}

	/**
	 * An empty field deletes the stored key.
	 */
	public function test_save_deletes_cleared_field(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_geo_tagr_place', 'Old' );

		$this->submit( $post_id, array( 'geo_tagr_place' => '' ) );

		$this->assertSame( '', get_post_meta( $post_id, '_geo_tagr_place', true ) );
		$this->assertEmpty( get_post_meta( $post_id, '_geo_tagr_place', false ) );
	}

	/**
	 * Out-of-range or non-numeric coordinates are ignored.
	 */
	public function test_save_rejects_out_of_range_coordinates(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_geo_tagr_lat', 10.0 );

		$this->submit(
			$post_id,
			array(
				'geo_tagr_lat' => '95',
				'geo_tagr_lng' => 'north',
			)
		);

		$this->assertEqualsWithDelta( 10.0, (float) get_post_meta( $post_id, '_geo_tagr_lat', true ), 0.00001 );
		$this->assertSame( '', get_post_meta( $post_id, '_geo_tagr_lng', true ) );
	}

	/**
	 * A user who cannot edit the post writes nothing, even with a valid nonce.
	 */
	public function test_save_requires_edit_post(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$post_id = self::factory()->post->create();

		$this->submit( $post_id, array( 'geo_tagr_lat' => '41.4993' ) );

		$this->assertSame( '', get_post_meta( $post_id, '_geo_tagr_lat', true ) );
	}

	/**
	 * Post types GeoTagr is not enabled for are ignored.
	 */
	public function test_save_skips_disallowed_post_type(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$this->submit( $page_id, array( 'geo_tagr_lat' => '41.4993' ) );

		$this->assertSame( '', get_post_meta( $page_id, '_geo_tagr_lat', true ) );
	}

	/**
	 * Revisions are skipped.
	 */
	public function test_save_skips_revisions(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$post_id     = self::factory()->post->create();
		$revision_id = wp_save_post_revision( $post_id );
		$this->assertIsInt( $revision_id );
		$fired = did_action( 'geo_tagr_meta_saved' );

		$this->submit( $revision_id, array( 'geo_tagr_lat' => '41.4993' ) );

		$this->assertSame( $fired, did_action( 'geo_tagr_meta_saved' ) );
		$this->assertSame( '', get_post_meta( $post_id, '_geo_tagr_lat', true ) );
	}

	/**
	 * Test that geo_tagr_allowed_post_types filter controls metabox registration.
	 */
	public function test_allowed_post_types_filter_is_respected(): void {
		add_filter(
			'geo_tagr_allowed_post_types',
			static function (): array {
				return array( 'page' );
			}
		);

		$registered = array();
		add_filter(
			'add_meta_boxes',
			static function () use ( &$registered ): void {
				// Captured by checking $wp_meta_boxes global below.
			}
		);

		do_action( 'add_meta_boxes' );
		$this->metabox->register();

		global $wp_meta_boxes;
		remove_all_filters( 'geo_tagr_allowed_post_types' );

		// Metabox should be registered on 'page', not on 'post'.
		$this->assertArrayHasKey( 'geo-tagr', $wp_meta_boxes['page']['normal']['default'] ?? array() );
		$this->assertArrayNotHasKey( 'geo-tagr', $wp_meta_boxes['post']['normal']['default'] ?? array() );
	}
}
