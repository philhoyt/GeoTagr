<?php
/**
 * Integration tests for GeoTagr\LocationTaxonomy — requires WP bootstrap via wp-env.
 *
 * @package GeoTagr
 */

declare(strict_types=1);

use GeoTagr\LocationTaxonomy;

/**
 * Integration tests for LocationTaxonomy::sync() and related helpers.
 */
class Test_Location_Taxonomy_Integration extends WP_UnitTestCase {

	private const LAT = 41.4993;
	private const LNG = -81.6944;

	/**
	 * Build a minimal meta array for a known location.
	 *
	 * @param float $lat Latitude.
	 * @param float $lng Longitude.
	 * @return array{lat: float, lng: float, place: string, address: string}
	 */
	private function make_meta( float $lat = self::LAT, float $lng = self::LNG ): array {
		return array(
			'lat'     => $lat,
			'lng'     => $lng,
			'place'   => 'Cleveland, OH',
			'address' => 'Cleveland, Cuyahoga County, Ohio, United States',
		);
	}

	/**
	 * Syncing creates the term, assigns it, and populates term meta.
	 */
	public function test_sync_creates_term_and_assigns_post(): void {
		$post_id  = self::factory()->post->create();
		$location = new LocationTaxonomy();

		$location->sync( $post_id, $this->make_meta() );

		$term = LocationTaxonomy::get_term_for_post( $post_id );

		$this->assertInstanceOf( \WP_Term::class, $term );
		$this->assertSame( LocationTaxonomy::slug_for( self::LAT, self::LNG ), $term->slug );
		$this->assertEqualsWithDelta( self::LAT, (float) get_term_meta( $term->term_id, '_geo_tagr_lat', true ), 0.0001 );
		$this->assertEqualsWithDelta( self::LNG, (float) get_term_meta( $term->term_id, '_geo_tagr_lng', true ), 0.0001 );
		$this->assertSame( 'Cleveland, OH', get_term_meta( $term->term_id, '_geo_tagr_place', true ) );
	}

	/**
	 * Two posts at the same location share one term — no duplicates.
	 */
	public function test_sync_reuses_existing_term_for_same_location(): void {
		$post_a   = self::factory()->post->create();
		$post_b   = self::factory()->post->create();
		$location = new LocationTaxonomy();

		$location->sync( $post_a, $this->make_meta() );
		$location->sync( $post_b, $this->make_meta() );

		$term_a = LocationTaxonomy::get_term_for_post( $post_a );
		$term_b = LocationTaxonomy::get_term_for_post( $post_b );

		$this->assertInstanceOf( \WP_Term::class, $term_a );
		$this->assertInstanceOf( \WP_Term::class, $term_b );
		$this->assertSame( $term_a->term_id, $term_b->term_id );

		$all_terms = get_terms(
			array(
				'taxonomy'   => LocationTaxonomy::TAXONOMY,
				'hide_empty' => false,
			)
		);
		$this->assertCount( 1, $all_terms );
	}

	/**
	 * Re-syncing the same post with updated place/address updates term meta.
	 */
	public function test_sync_updates_term_meta_on_place_change(): void {
		$post_id  = self::factory()->post->create();
		$location = new LocationTaxonomy();

		$location->sync( $post_id, $this->make_meta() );
		$location->sync(
			$post_id,
			array(
				'lat'     => self::LAT,
				'lng'     => self::LNG,
				'place'   => 'Updated Place',
				'address' => 'Updated Address',
			)
		);

		$term = LocationTaxonomy::get_term_for_post( $post_id );
		$this->assertSame( 'Updated Place', get_term_meta( $term->term_id, '_geo_tagr_place', true ) );
	}

	/**
	 * Passing null meta removes the term assignment but keeps the term itself.
	 */
	public function test_sync_removes_assignment_when_meta_cleared(): void {
		$post_id  = self::factory()->post->create();
		$location = new LocationTaxonomy();

		$location->sync( $post_id, $this->make_meta() );
		$this->assertInstanceOf( \WP_Term::class, LocationTaxonomy::get_term_for_post( $post_id ) );

		$location->sync( $post_id, null );
		$this->assertNull( LocationTaxonomy::get_term_for_post( $post_id ) );

		// Term itself is retained for other posts that may reference it.
		$term = get_term_by( 'slug', LocationTaxonomy::slug_for( self::LAT, self::LNG ), LocationTaxonomy::TAXONOMY );
		$this->assertInstanceOf( \WP_Term::class, $term );
	}

	/**
	 * A post with no geo data returns null.
	 */
	public function test_get_term_for_post_returns_null_when_untagged(): void {
		$post_id = self::factory()->post->create();

		$this->assertNull( LocationTaxonomy::get_term_for_post( $post_id ) );
	}
}
