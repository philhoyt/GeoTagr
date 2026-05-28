<?php
/**
 * Uninstall: remove all GeoTagr post meta from the database.
 *
 * @package GeoTagr
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$meta_keys = array(
	'_geo_tagr_lat',
	'_geo_tagr_lng',
	'_geo_tagr_place',
	'_geo_tagr_address',
);

foreach ( $meta_keys as $key ) {
	delete_post_meta_by_key( $key );
}
