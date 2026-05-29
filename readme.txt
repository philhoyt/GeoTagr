== GeoTagr ==

Contributors: philhoyt
Tags: geolocation, geocoding, map, metadata, location
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.2
Stable tag: 0.6.3
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Attach geographic location metadata to any post.

== Description ==

GeoTagr lets you attach geographic coordinates, a place name, and a formatted address to any post. It provides a block editor sidebar panel and a classic editor metabox, both with an interactive Leaflet map. Geocoding is handled by your choice of Nominatim (free, no key required), Google Places, or Mapbox.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/geotagr`.
2. Activate the plugin through the Plugins screen in WordPress.
3. Go to Settings → GeoTagr to configure post types, taxonomy visibility, and your geocoding provider.

== Changelog ==

= 0.6.3 =
* Security: Google API key no longer sent to the browser when using the Google geocoding provider.
* Fix: Google forward geocoding now returns results correctly (broken since v0.6.0 array response change).
* Fix: Classic editor strings are now translatable via `__()`.
* Fix: ESLint configuration migrated to flat config required by `@wordpress/scripts` 32.x.
* Fix: Classic editor metabox saves lat/lng as float, matching the REST API save path.
* Add: Geo meta changes made by external callers (e.g. REST API writes) now sync the location taxonomy automatically.

= 0.6.2 =
* Fix: Location Name block now loads correctly on live sites — block.json path updated to the compiled build directory.

= 0.6.1 =
* Fix: Forward geocode now uses the Places API (New) with an optional user-supplied location bias, preventing server IP from skewing results on hosted environments.

= 0.6.0 =
* Change: Geocode proxy now returns up to 5 candidate results instead of a single result.

= 0.5.0 =
* Add: Location Name block (`geotagr/location-name`) — displays the place name attached to a post; renders nothing when no location is set.

= 0.4.0 =
* Add: Nominatim, Google Places, and Mapbox geocoding providers — configurable from Settings.
* Add: Per-provider API key instructions with visibility toggle in the Settings page.
* Add: Server-side REST proxy for Google Places, keeping the API key out of the browser.
* Fix: Mapbox POI subtype handling (`poi.landmark` and similar) now correctly populates the place name.
* Fix: Whitespace normalisation applied before sending any geocoding query.

= 0.3.0 =
* Add: Settings page — configure which post types show the metabox and whether the Geo Tags taxonomy is public.
* Add: Geo Tags taxonomy synced automatically from post geo coordinates.

= 0.2.0 =
* Add: Location taxonomy (`geo_tagr_location`) with automatic term sync on save.

= 0.1.0 =
* Add: Block editor sidebar panel and classic editor metabox with interactive Leaflet map.
* Add: Nominatim forward and reverse geocoding.
* Add: `geo_tagr_get_post_meta()` public helper function.
