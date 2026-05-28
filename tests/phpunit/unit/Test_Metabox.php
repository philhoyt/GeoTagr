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
