<?php
/**
 * Integration test for uninstall.php.
 *
 * @package GeoTagr
 */

declare(strict_types=1);

use GeoTagr\LocationTaxonomy;
use GeoTagr\Meta;

/**
 * Running uninstall.php removes every trace of GeoTagr data.
 */
class Test_Uninstall_Integration extends WP_UnitTestCase {

	/**
	 * Post meta, location terms, term meta and options are all removed.
	 */
	public function test_uninstall_removes_meta_terms_and_options(): void {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_geo_tagr_lat', 41.4993 );
		update_post_meta( $post_id, '_geo_tagr_lng', -81.6944 );
		update_post_meta( $post_id, '_geo_tagr_place', 'Cleveland' );
		update_post_meta( $post_id, '_geo_tagr_address', 'Cleveland, OH' );
		Meta::fire_saved_action( $post_id );

		update_option( 'geotagr_settings', array( 'taxonomy_public' => true ) );
		update_option( 'external_updates-geotagr', array( 'x' => 1 ) );

		$term = LocationTaxonomy::get_term_for_post( $post_id );
		$this->assertInstanceOf( WP_Term::class, $term, 'Fixture: a location term should exist before uninstall.' );
		$term_id = $term->term_id;

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', true );
		}
		require_once dirname( __DIR__, 2 ) . '/uninstall.php';

		$this->assertSame( '', get_post_meta( $post_id, '_geo_tagr_lat', true ) );
		$this->assertSame( '', get_post_meta( $post_id, '_geo_tagr_place', true ) );
		$this->assertNull( get_term( $term_id, LocationTaxonomy::TAXONOMY ) );
		$this->assertSame( '', get_term_meta( $term_id, '_geo_tagr_lat', true ) );
		$this->assertFalse( get_option( 'geotagr_settings' ) );
		$this->assertFalse( get_option( 'external_updates-geotagr' ) );
	}
}
