<?php
/**
 * Uninstall: remove all GeoTagr post meta from the database.
 *
 * @package GeoTagr
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$geotagr_meta_keys = array(
	'_geo_tagr_lat',
	'_geo_tagr_lng',
	'_geo_tagr_place',
	'_geo_tagr_address',
);

foreach ( $geotagr_meta_keys as $geotagr_key ) {
	delete_post_meta_by_key( $geotagr_key );
}

// Remove all geo_tagr_location taxonomy terms and their term meta.
$geotagr_terms = get_terms(
	array(
		'taxonomy'   => 'geo_tagr_location',
		'hide_empty' => false,
		'fields'     => 'ids',
	)
);

if ( is_array( $geotagr_terms ) ) {
	foreach ( $geotagr_terms as $geotagr_term_id ) {
		wp_delete_term( (int) $geotagr_term_id, 'geo_tagr_location' );
	}
}

delete_option( 'geotagr_settings' );
