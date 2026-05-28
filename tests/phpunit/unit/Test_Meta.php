<?php
/**
 * Unit tests for GeoTagr\Meta.
 *
 * @package GeoTagr
 */

declare(strict_types=1);

use GeoTagr\Meta;

/**
 * Tests for GeoTagr\Meta.
 */
class Test_Meta extends WP_UnitTestCase {

	/**
	 * Test that get() returns null for a post with no geo meta.
	 */
	public function test_get_returns_null_for_post_with_no_meta(): void {
		$post_id = self::factory()->post->create();

		$this->assertNull( Meta::get( $post_id ) );
	}

	/**
	 * Test that get() returns correct array after meta is written.
	 */
	public function test_get_returns_correct_array_after_update(): void {
		$post_id = self::factory()->post->create();

		update_post_meta( $post_id, '_geo_tagr_lat', 41.4993 );
		update_post_meta( $post_id, '_geo_tagr_lng', -81.6944 );
		update_post_meta( $post_id, '_geo_tagr_place', 'Cleveland, OH' );
		update_post_meta( $post_id, '_geo_tagr_address', 'Cleveland, Cuyahoga County, Ohio, United States' );

		$result = Meta::get( $post_id );

		$this->assertIsArray( $result );
		$this->assertEqualsWithDelta( 41.4993, $result['lat'], 0.0001 );
		$this->assertEqualsWithDelta( -81.6944, $result['lng'], 0.0001 );
		$this->assertSame( 'Cleveland, OH', $result['place'] );
		$this->assertSame( 'Cleveland, Cuyahoga County, Ohio, United States', $result['address'] );
	}

	/**
	 * Test that get() respects the geo_tagr_meta filter.
	 */
	public function test_get_applies_geo_tagr_meta_filter(): void {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_geo_tagr_lat', 41.4993 );
		update_post_meta( $post_id, '_geo_tagr_lng', -81.6944 );
		update_post_meta( $post_id, '_geo_tagr_place', 'Cleveland, OH' );
		update_post_meta( $post_id, '_geo_tagr_address', 'Cleveland, OH' );

		add_filter(
			'geo_tagr_meta',
			static function ( array $meta ): array {
				$meta['place'] = 'Filtered';
				return $meta;
			}
		);

		$result = Meta::get( $post_id );

		remove_all_filters( 'geo_tagr_meta' );

		$this->assertSame( 'Filtered', $result['place'] );
	}
}
