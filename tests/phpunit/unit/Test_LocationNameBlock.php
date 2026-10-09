<?php
/**
 * Unit tests for GeoTagr\LocationNameBlock.
 *
 * @package GeoTagr
 */

declare(strict_types=1);

use GeoTagr\LocationNameBlock;

/**
 * Tests for the LocationNameBlock render callback.
 */
class Test_LocationNameBlock extends WP_UnitTestCase {

	/**
	 * Block instance under test.
	 *
	 * @var LocationNameBlock
	 */
	private LocationNameBlock $block;

	/**
	 * Set up a fresh block instance before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->block = new LocationNameBlock();
	}

	/**
	 * Helper: return a WP_Block stub with the given postId in its context.
	 *
	 * @param int $post_id Post ID to inject into context.
	 * @return WP_Block
	 */
	private function make_block( int $post_id ): \WP_Block {
		$stub          = $this->getMockBuilder( \WP_Block::class )
			->disableOriginalConstructor()
			->getMock();
		$stub->context = array( 'postId' => $post_id );
		return $stub;
	}

	/**
	 * Render returns empty string when _geo_tagr_place is not set.
	 */
	public function test_render_returns_empty_string_when_place_is_not_set(): void {
		$post_id = self::factory()->post->create();

		$output = $this->block->render( array(), '', $this->make_block( $post_id ) );

		$this->assertSame( '', $output );
	}

	/**
	 * Render returns empty string when _geo_tagr_place is an empty string.
	 */
	public function test_render_returns_empty_string_when_place_is_empty(): void {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_geo_tagr_place', '' );

		$output = $this->block->render( array(), '', $this->make_block( $post_id ) );

		$this->assertSame( '', $output );
	}

	/**
	 * Render returns a <p> containing the place name when it is set.
	 */
	public function test_render_returns_place_name_when_set(): void {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_geo_tagr_place', 'Cleveland, OH' );

		$output = $this->block->render( array(), '', $this->make_block( $post_id ) );

		$this->assertStringContainsString( 'Cleveland, OH', $output );
		$this->assertStringStartsWith( '<p', $output );
	}

	/**
	 * Render escapes HTML special characters in the place name.
	 *
	 * Tags are already stripped by the registered sanitize_callback on write,
	 * so the fixture uses characters that survive sanitization but must still
	 * be escaped on output.
	 */
	public function test_render_escapes_place_name(): void {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_geo_tagr_place', 'Tom & Jerry\'s "Diner"' );

		$output = $this->block->render( array(), '', $this->make_block( $post_id ) );

		$this->assertStringContainsString( 'Tom &amp; Jerry&#039;s &quot;Diner&quot;', $output );
		$this->assertStringNotContainsString( '"Diner"', $output );
	}

	/**
	 * Script tags never reach the output: the sanitizer strips them on write.
	 */
	public function test_script_in_place_name_is_stripped_on_write(): void {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_geo_tagr_place', '<script>alert("xss")</script>' );

		$this->assertStringNotContainsString( '<script', (string) get_post_meta( $post_id, '_geo_tagr_place', true ) );
		$this->assertStringNotContainsString( '<script', $this->block->render( array(), '', $this->make_block( $post_id ) ) );
	}
}
