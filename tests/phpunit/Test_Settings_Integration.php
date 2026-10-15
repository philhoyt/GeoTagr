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
	 * Administrators see a Settings link before Deactivate on the Plugins screen.
	 */
	public function test_plugin_action_links_prepend_settings_link_for_admins(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$links = apply_filters(
			'plugin_action_links_' . plugin_basename( GEOTAGR_PLUGIN_FILE ),
			array( 'deactivate' => '<a href="#">Deactivate</a>' )
		);

		$this->assertCount( 2, $links );
		$this->assertStringContainsString( 'options-general.php?page=geotagr-settings', reset( $links ) );
		$this->assertSame( 'deactivate', array_key_last( $links ) );
	}

	/**
	 * Users without manage_options do not get the Settings link.
	 */
	public function test_plugin_action_links_unchanged_without_manage_options(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$links = apply_filters(
			'plugin_action_links_' . plugin_basename( GEOTAGR_PLUGIN_FILE ),
			array( 'deactivate' => '<a href="#">Deactivate</a>' )
		);

		$this->assertSame( array( 'deactivate' ), array_keys( $links ) );
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
