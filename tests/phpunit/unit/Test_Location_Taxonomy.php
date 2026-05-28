<?php
/**
 * Unit tests for GeoTagr\LocationTaxonomy — slug generation only (no WP bootstrap needed).
 *
 * @package GeoTagr
 */

declare(strict_types=1);

use GeoTagr\LocationTaxonomy;

/**
 * Tests for LocationTaxonomy::slug_for().
 */
class Test_Location_Taxonomy extends \PHPUnit\Framework\TestCase {

	/**
	 * Positive lat, negative lng (most common US case).
	 */
	public function test_slug_for_positive_coordinates(): void {
		$this->assertSame( '414993n-816944w', LocationTaxonomy::slug_for( 41.4993, -81.6944 ) );
	}

	/**
	 * Negative latitude produces S suffix.
	 */
	public function test_slug_for_negative_latitude(): void {
		$this->assertSame( '336s-706e', LocationTaxonomy::slug_for( -0.0336, 0.0706 ) );
	}

	/**
	 * Both coordinates positive; small values have no leading zeros.
	 */
	public function test_slug_for_both_positive(): void {
		// 0.0001 * 10000 = 1, so lng part is "1e" with no leading zero.
		$this->assertSame( '516n-1e', LocationTaxonomy::slug_for( 0.0516, 0.0001 ) );
	}

	/**
	 * Coords with the same 4-dp representation produce the same slug.
	 */
	public function test_slug_rounds_to_four_decimal_places(): void {
		// Both values have the same 4-dp representation (5th decimal < 5, rounds down).
		$slug_a = LocationTaxonomy::slug_for( 41.49930, -81.69440 );
		$slug_b = LocationTaxonomy::slug_for( 41.49934, -81.69444 );

		$this->assertSame( $slug_a, $slug_b );
		$this->assertSame( '414993n-816944w', $slug_a );
	}

	/**
	 * Coords differing at the 4th decimal place produce different slugs.
	 */
	public function test_slug_differs_for_distinct_locations(): void {
		$slug_a = LocationTaxonomy::slug_for( 41.4993, -81.6944 );
		$slug_b = LocationTaxonomy::slug_for( 41.4994, -81.6944 );

		$this->assertNotSame( $slug_a, $slug_b );
	}
}
