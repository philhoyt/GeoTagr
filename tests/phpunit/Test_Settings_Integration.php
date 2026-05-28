<?php
/**
 * Integration tests for Settings → GeoTagger wiring.
 *
 * @package GeoTagr
 */

declare(strict_types=1);

use GeoTagr\LocationTaxonomy;

/**
 * Tests that saved settings propagate correctly through filters and registration.
 */
class Test_Settings_Integration extends WP_UnitTestCase {

	/**
	 * Clean option between tests.
	 */
	public function tear_down(): void {
		delete_option( 'geotagr_settings' );
		parent::tear_down();
	}

	/**
	 * The geo_tagr_allowed_post_types filter returns the saved post types.
	 */
	public function test_allowed_post_types_filter_returns_option_value(): void {
		update_option(
			'geotagr_settings',
			array(
				'allowed_post_types' => array( 'post', 'page' ),
				'taxonomy_public'    => false,
			)
		);

		$result = apply_filters( 'geo_tagr_allowed_post_types', array( 'post' ) );

		$this->assertContains( 'page', $result );
	}

	/**
	 * LocationTaxonomy registers as public when is_public=true is passed.
	 */
	public function test_location_taxonomy_public_when_is_public_true(): void {
		// Unregister if already registered from the main plugin boot.
		if ( taxonomy_exists( LocationTaxonomy::TAXONOMY ) ) {
			unregister_taxonomy( LocationTaxonomy::TAXONOMY );
		}

		$location = new LocationTaxonomy();
		$location->register( true );

		$taxonomy = get_taxonomy( LocationTaxonomy::TAXONOMY );

		$this->assertInstanceOf( \WP_Taxonomy::class, $taxonomy );
		$this->assertTrue( $taxonomy->public );
		$this->assertTrue( $taxonomy->show_ui );
	}

	/**
	 * LocationTaxonomy registers as hidden when is_public=false (the default).
	 */
	public function test_location_taxonomy_hidden_when_is_public_false(): void {
		if ( taxonomy_exists( LocationTaxonomy::TAXONOMY ) ) {
			unregister_taxonomy( LocationTaxonomy::TAXONOMY );
		}

		$location = new LocationTaxonomy();
		$location->register( false );

		$taxonomy = get_taxonomy( LocationTaxonomy::TAXONOMY );

		$this->assertInstanceOf( \WP_Taxonomy::class, $taxonomy );
		$this->assertFalse( $taxonomy->public );
		$this->assertFalse( $taxonomy->show_ui );
	}
}
