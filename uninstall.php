<?php
/**
 * Uninstall: remove all GeoTagr data from the database.
 *
 * Runs without the plugin loaded, so the taxonomy is registered here
 * just long enough for the term API to recognise it.
 *
 * @package GeoTagr
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete GeoTagr post meta, location terms, and options for the current site.
 */
function geotagr_uninstall_site(): void {
	$meta_keys = array(
		'_geo_tagr_lat',
		'_geo_tagr_lng',
		'_geo_tagr_place',
		'_geo_tagr_address',
	);

	foreach ( $meta_keys as $key ) {
		delete_post_meta_by_key( $key );
	}

	// get_terms() and wp_delete_term() refuse unregistered taxonomies.
	if ( ! taxonomy_exists( 'geo_tagr_location' ) ) {
		register_taxonomy( 'geo_tagr_location', array(), array( 'public' => false ) );
	}

	$terms = get_terms(
		array(
			'taxonomy'   => 'geo_tagr_location',
			'hide_empty' => false,
			'fields'     => 'ids',
		)
	);

	if ( is_array( $terms ) ) {
		foreach ( $terms as $term_id ) {
			wp_delete_term( (int) $term_id, 'geo_tagr_location' );
		}
	}

	delete_option( 'geotagr_settings' );
	delete_option( 'geotagr_flush_rewrite' );
	delete_option( 'external_updates-geotagr' ); // Plugin Update Checker state.
}

if ( is_multisite() ) {
	$geotagr_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $geotagr_site_ids as $geotagr_site_id ) {
		switch_to_blog( (int) $geotagr_site_id );
		geotagr_uninstall_site();
		restore_current_blog();
	}
} else {
	geotagr_uninstall_site();
}
