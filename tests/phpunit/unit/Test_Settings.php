<?php
/**
 * Unit tests for GeoTagr\Settings — option reading and sanitization.
 *
 * @package GeoTagr
 */

declare(strict_types=1);

use GeoTagr\Settings;

/**
 * Tests for Settings::get() and Settings::sanitize().
 */
class Test_Settings extends WP_UnitTestCase {

	/**
	 * Removes the option between tests.
	 */
	public function tear_down(): void {
		delete_option( 'geotagr_settings' );
		parent::tear_down();
	}

	/**
	 * Built-in defaults are returned when no option is stored.
	 */
	public function test_get_returns_default_when_option_missing(): void {
		$this->assertSame( array( 'post' ), Settings::get( 'allowed_post_types' ) );
		$this->assertFalse( Settings::get( 'taxonomy_public' ) );
	}

	/**
	 * Caller-supplied fallback is returned for unknown keys.
	 */
	public function test_get_returns_fallback_for_unknown_key(): void {
		$this->assertSame( 'my-fallback', Settings::get( 'nonexistent_key', 'my-fallback' ) );
	}

	/**
	 * Saved value is returned when the option has been stored.
	 */
	public function test_get_returns_saved_value(): void {
		update_option(
			'geotagr_settings',
			array(
				'allowed_post_types' => array( 'post', 'page' ),
				'taxonomy_public'    => true,
			)
		);

		$this->assertSame( array( 'post', 'page' ), Settings::get( 'allowed_post_types' ) );
		$this->assertTrue( Settings::get( 'taxonomy_public' ) );
	}

	/**
	 * Invalid post type names are stripped by sanitize().
	 */
	public function test_sanitize_filters_out_invalid_post_types(): void {
		$settings = new Settings();

		$result = $settings->sanitize( array( 'allowed_post_types' => array( 'post', 'fake_type_xyz' ) ) );

		$this->assertContains( 'post', $result['allowed_post_types'] );
		$this->assertNotContains( 'fake_type_xyz', $result['allowed_post_types'] );
	}

	/**
	 * Truthy value is cast to true for taxonomy_public by sanitize().
	 */
	public function test_sanitize_casts_taxonomy_public_to_bool(): void {
		$settings = new Settings();

		$result = $settings->sanitize(
			array(
				'allowed_post_types' => array( 'post' ),
				'taxonomy_public'    => '1',
			)
		);

		$this->assertTrue( $result['taxonomy_public'] );
	}

	/**
	 * Absent taxonomy_public key (unchecked checkbox) is sanitized to false.
	 */
	public function test_sanitize_defaults_taxonomy_public_false_when_absent(): void {
		$settings = new Settings();

		$result = $settings->sanitize( array( 'allowed_post_types' => array( 'post' ) ) );

		$this->assertFalse( $result['taxonomy_public'] );
	}
}
