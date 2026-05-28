<?php
/**
 * Hidden location taxonomy — query/index layer over geo post meta.
 *
 * @package GeoTagr
 */

declare(strict_types=1);

namespace GeoTagr;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the geo_tagr_location taxonomy and keeps its terms in sync
 * with post meta whenever geo data is saved.
 */
class LocationTaxonomy {

	public const TAXONOMY = 'geo_tagr_location';

	/**
	 * Register the taxonomy and its term meta keys.
	 *
	 * @param bool $is_public Whether to register the taxonomy as publicly visible.
	 *                        When true, shows in admin UI, nav menus, and REST API.
	 */
	public function register( bool $is_public = false ): void {
		$post_types = apply_filters( 'geo_tagr_allowed_post_types', array( 'post' ) );

		register_taxonomy(
			self::TAXONOMY,
			(array) $post_types,
			array(
				'public'             => $is_public,
				'publicly_queryable' => $is_public,
				'hierarchical'       => false,
				'show_ui'            => $is_public,
				'show_in_menu'       => $is_public,
				'show_in_nav_menus'  => $is_public,
				'show_tagcloud'      => $is_public,
				'show_in_quick_edit' => $is_public,
				'show_admin_column'  => $is_public,
				'show_in_rest'       => $is_public,
				'rewrite'            => $is_public,
				'query_var'          => $is_public,
				'capabilities'       => array(
					'manage_terms' => 'manage_categories',
					'edit_terms'   => 'manage_categories',
					'delete_terms' => 'manage_categories',
					'assign_terms' => 'edit_posts',
				),
			)
		);

		foreach ( array( '_geo_tagr_lat', '_geo_tagr_lng' ) as $key ) {
			register_term_meta(
				self::TAXONOMY,
				$key,
				array(
					'type'              => 'number',
					'single'            => true,
					'sanitize_callback' => static fn( $v ): float => (float) $v,
				)
			);
		}

		foreach ( array( '_geo_tagr_place', '_geo_tagr_address' ) as $key ) {
			register_term_meta(
				self::TAXONOMY,
				$key,
				array(
					'type'              => 'string',
					'single'            => true,
					'sanitize_callback' => 'sanitize_text_field',
				)
			);
		}
	}

	/**
	 * Build a deterministic, collision-free slug from lat/lng.
	 *
	 * Format: {lat_int}{N|S}-{lng_int}{E|W}
	 * e.g. 41.4993 N, -81.6944 W → "414993n-816944w"
	 *
	 * Integers are abs(round(coord, 4)) * 10000 cast to int, so two coordinates
	 * that differ only past the 4th decimal place produce the same slug.
	 *
	 * @param float $lat Latitude.
	 * @param float $lng Longitude.
	 * @return string Term slug.
	 */
	public static function slug_for( float $lat, float $lng ): string {
		$lat_int = (int) round( abs( round( $lat, 4 ) ) * 10000 );
		$lng_int = (int) round( abs( round( $lng, 4 ) ) * 10000 );
		$lat_dir = $lat >= 0 ? 'n' : 's';
		$lng_dir = $lng >= 0 ? 'e' : 'w';

		return "{$lat_int}{$lat_dir}-{$lng_int}{$lng_dir}";
	}

	/**
	 * Sync the location term for a post whenever geo meta is saved.
	 *
	 * Hooked to `geo_tagr_meta_saved`. When meta is null (geo data cleared),
	 * the term assignment is removed but the term itself is preserved for
	 * other posts that may share it.
	 *
	 * @param int                                                                          $post_id Post ID.
	 * @param array{lat: float|null, lng: float|null, place: string, address: string}|null $meta    Saved meta, or null when cleared.
	 */
	public function sync( int $post_id, ?array $meta ): void {
		if ( null === $meta || null === $meta['lat'] || null === $meta['lng'] ) {
			wp_remove_object_terms( $post_id, wp_get_object_terms( $post_id, self::TAXONOMY, array( 'fields' => 'ids' ) ), self::TAXONOMY );
			return;
		}

		$slug = self::slug_for( $meta['lat'], $meta['lng'] );
		$term = get_term_by( 'slug', $slug, self::TAXONOMY );

		if ( ! $term instanceof \WP_Term ) {
			$result = wp_insert_term(
				$slug,
				self::TAXONOMY,
				array( 'slug' => $slug )
			);

			if ( is_wp_error( $result ) ) {
				return;
			}

			$term = get_term( $result['term_id'], self::TAXONOMY );

			if ( ! $term instanceof \WP_Term ) {
				return;
			}
		}

		update_term_meta( $term->term_id, '_geo_tagr_lat', $meta['lat'] );
		update_term_meta( $term->term_id, '_geo_tagr_lng', $meta['lng'] );
		update_term_meta( $term->term_id, '_geo_tagr_place', $meta['place'] );
		update_term_meta( $term->term_id, '_geo_tagr_address', $meta['address'] );

		wp_set_object_terms( $post_id, $term->term_id, self::TAXONOMY );

		/**
		 * Filters the location term after it has been resolved and updated.
		 *
		 * @param \WP_Term $term    The resolved location term.
		 * @param int      $post_id Post ID.
		 */
		$term = apply_filters( 'geo_tagr_location_term', $term, $post_id );

		/**
		 * Fires after a location term is assigned to a post.
		 *
		 * @param int      $post_id Post ID.
		 * @param \WP_Term $term    The assigned location term.
		 */
		do_action( 'geo_tagr_location_term_assigned', $post_id, $term );
	}

	/**
	 * Return the location term assigned to a post, or null if none.
	 *
	 * @param int $post_id Post ID.
	 * @return \WP_Term|null
	 */
	public static function get_term_for_post( int $post_id ): ?\WP_Term {
		$terms = wp_get_object_terms( $post_id, self::TAXONOMY, array( 'number' => 1 ) );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return null;
		}

		return $terms[0] instanceof \WP_Term ? $terms[0] : null;
	}
}
