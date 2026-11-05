=== GeoTagr ===
Contributors: philhoyt
Tags: geolocation, geocoding, map, metadata, location
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 0.10.2
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Attaches coordinates, a place name and an address to posts, with a map in the editor and a Location Name block

== Description ==

GeoTagr stores latitude, longitude, a place name and a formatted address on posts of the post types you enable. Editors set the location from a sidebar panel in the block editor or a metabox in the classic editor. Both show a Leaflet map; you can search for an address, click the map, or type coordinates, and the plugin fills in the rest by geocoding.

Geocoding uses one of three providers, chosen under Settings > GeoTagr: OpenStreetMap Nominatim (default, no key needed), Google Maps Platform or Mapbox. Google and Mapbox need an API key and return better place names for businesses and landmarks.

Each post with a location also gets a term in a `geo_tagr_location` taxonomy, kept in sync on save. The taxonomy is hidden by default; a setting makes it public so you can build location archives.

The Location Name block (`geotagr/location-name`) prints the place name of the current post and renders nothing when the post has no location.

Coordinates and the address are private by default. Visitors and other sites reading the REST API get the place name only, unless you change Public API visibility to Rounded (coordinates to about 110 m, no address) or Exact. Users who can edit a post always get its stored values.

Theme and plugin code can read a post's location with `geo_tagr_get_post_meta( $post_id )`.

== External services ==

GeoTagr sends data to third-party services only when an editor looks up a location while writing a post. No visitor data is sent anywhere.

* OpenStreetMap Nominatim (default provider): the address or coordinates being looked up are sent from the editor's browser to nominatim.openstreetmap.org, which also receives the browser's IP address. [Usage policy](https://operations.osmfoundation.org/policies/nominatim/), [privacy policy](https://wiki.osmfoundation.org/wiki/Privacy_Policy).
* OpenStreetMap tiles: the editor's map preview loads tiles from tile.openstreetmap.org, which receives the browser's IP address. [Tile usage policy](https://operations.osmfoundation.org/policies/tiles/).
* Google Maps Platform (optional): lookups are sent from your server to places.googleapis.com and maps.googleapis.com with your API key. The editor's browser never contacts Google. [Terms](https://cloud.google.com/maps-platform/terms), [privacy policy](https://policies.google.com/privacy).
* Mapbox (optional): lookups are sent from the editor's browser to api.mapbox.com with your public access token. [Terms](https://www.mapbox.com/legal/tos), [privacy policy](https://www.mapbox.com/legal/privacy).

Suggested privacy policy text is added under Settings > Privacy > Policy Guide.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/geotagr`, or upload the release zip under Plugins > Add New > Upload Plugin.
2. Activate the plugin through the Plugins screen in WordPress.
3. Go to Settings > GeoTagr to choose the post types, taxonomy visibility, Public API visibility and geocoding provider.

Updates are delivered from GitHub releases through the bundled Plugin Update Checker.

== Frequently Asked Questions ==

= Where is the location stored? =

In four post meta keys: `_geo_tagr_lat`, `_geo_tagr_lng`, `_geo_tagr_place` and `_geo_tagr_address`. Writing them requires permission to edit that post.

= What does the REST API return to anonymous readers? =

With the default Private setting, latitude and longitude come back as 0 and the address as an empty string. A 0, 0 value means redacted or absent, not a real coordinate. The place name is always public and can be a precise business or building name.

= Does a public location taxonomy expose coordinates? =

Yes. Term slugs encode coordinates to four decimals (about 11 m), and a term is named after the street address when the post has no place name. The Public API visibility setting does not cover terms.

= What does uninstalling remove? =

All four meta keys on every post, the location terms, and the plugin's options, on every site of a multisite network.

== Upgrade Notice ==

= 0.10.0 =
Location access is now decided per post. Users who can edit posts but not a particular post (Authors, Contributors, application-password clients with those roles) now get that post's location in the Public API visibility shape instead of exact values, and can no longer write location meta to it. Sites that rely on the old behaviour can restore it with the geo_tagr_rest_location_visibility filter, which now receives the post. Purge page and object caches after upgrading.

= 0.8.0 =
Anonymous REST API reads of latitude, longitude, and address now return 0, 0 and an empty address by default. A value of 0, 0 means redacted or absent, not a real coordinate; do not plot it. Logged-in users who can edit posts, including application-password clients, still receive stored values; the gate is role-level, so any contributor-and-above account reads coordinates on every post. Headless front ends, feeds, or map widgets that read coordinates anonymously need Settings > GeoTagr > Public API visibility set to "Rounded" or "Exact" (Exact restores the previous output). The place name stays public in every mode and can be a precise business or building name. Purge page and object caches after upgrading.

== Changelog ==

= 0.10.2 =
* Tested against WordPress 7.1 and rebuilt with the current block editor toolchain.

= 0.10.1 =
* Fixed the location meta permission check to use the user being asked about, not the logged-in user, so `user_can( $user, 'edit_post_meta', ... )` from WP-CLI, cron or other plugins gives the right answer. Saves from the editor and REST were already correct.

= 0.10.0 =
* Restricted writing location meta (`_geo_tagr_lat`, `_geo_tagr_lng`, `_geo_tagr_place`, `_geo_tagr_address`) to users who can edit that post, not just anyone with `edit_posts`.
* Restricted exact coordinates and the address in the REST API to users who can edit that post. Everyone else, including users who can edit other posts, gets the Public API visibility shape. Collections, `_fields` responses and create/update responses are gated the same way.
* Added support for post types with their own capabilities (for example a custom `edit_events`) for users without `edit_posts`: their location meta saves, and they can use the Google address search.
* Added the `geo_tagr_can_geocode` filter, which decides who may use the Google geocoding proxy. By default, anyone who can edit posts or the posts of a GeoTagr-enabled post type.
* Changed `geo_tagr_rest_location_visibility` to receive the post as a fourth argument. It is no longer called for users who can edit the post.
* Left the location taxonomy unchanged: with "Public taxonomy" on, its term slugs still encode coordinates to four decimals, regardless of the per-post gate.

= 0.9.0 =
* Added a Settings link to the GeoTagr row on the Plugins screen.
* Masked the geocoding API key field, with a Show/Hide button to reveal it while editing.

= 0.8.0 =
* Hid latitude, longitude and address from REST API readers who cannot edit posts. See the upgrade notice.
* Added the Public API visibility setting with Private (default), Rounded (coordinates to about 110 m, no address) and Exact modes.
* Added the `geo_tagr_rest_location_visibility` filter to override the mode per request. Unknown values fall back to Private.
* Changed the location taxonomy setting text to explain that public terms expose coordinates through the term slug and the street address through the term name when a post has no place name.

= 0.7.0 =
* Fixed the block editor panel and the Location Name block to read and save location data themselves. Before, saving only worked because the classic metabox was also rendered inside the block editor.
* Fixed the Google provider being ignored when selected. Since 0.6.3, lookups silently fell back to Nominatim.
* Fixed clearing a coordinate in the block editor blocking the post from saving.
* Changed Google reverse lookup to Places API (New) Nearby Search. Enable "Places API (New)" on your Google Cloud project; the legacy Places API is no longer available to new projects.
* Changed Mapbox lookups to Geocoding v6. Mapbox no longer returns place names, so only the address is filled in.
* Added a one-day cache for geocoding results from the proxy, and the `geo_tagr_geocode_request_args`, `geo_tagr_geocode_result` and `geo_tagr_geocode_cache_ttl` filters.
* Fixed uninstall to remove location terms and to run on every site of a multisite network.
* Fixed making the location taxonomy public so it no longer requires re-saving permalinks.
* Fixed editor assets to load only on enabled post types, and stopped the classic metabox appearing inside the block editor.
* Fixed clearing location data through the REST API so it removes the post's location term.
* Fixed coordinates outside the valid range being accepted on save.
* Added loading of bundled translations, including for editor scripts.
* Added suggested privacy policy text under Settings > Privacy, and an External services section to this readme.
* Fixed accessibility of the settings page and editor controls: labelled fields, announced errors and results, and focus kept during lookups.

= 0.6.3 =
* Fixed the Google API key being sent to the browser when using the Google geocoding provider.
* Fixed Google forward geocoding returning no results (broken since the 0.6.0 array response change).
* Fixed classic editor strings so they are translatable via `__()`.
* Migrated the ESLint configuration to the flat config required by `@wordpress/scripts` 32.
* Fixed the classic editor metabox to save lat/lng as floats, matching the REST API save path.
* Added automatic location taxonomy sync for geo meta changes made by external callers such as REST API writes.

= 0.6.2 =
* Fixed the Location Name block failing to load on live sites. The block.json path now points at the compiled build directory.

= 0.6.1 =
* Changed forward geocoding to use the Places API (New) with an optional location bias, so the server's IP address no longer skews results on hosted environments.

= 0.6.0 =
* Changed the geocode proxy to return up to 5 candidate results instead of one.

= 0.5.0 =
* Added the Location Name block (`geotagr/location-name`). It displays the place name attached to a post and renders nothing when no location is set.

= 0.4.0 =
* Added Nominatim, Google Places and Mapbox geocoding providers, selectable from Settings.
* Added per-provider API key instructions with a visibility toggle on the Settings page.
* Added a server-side REST proxy for Google Places, keeping the API key out of the browser.
* Fixed Mapbox POI subtype handling (`poi.landmark` and similar) so the place name is filled in.
* Added whitespace normalisation before sending any geocoding query.

= 0.3.0 =
* Added a Settings page to choose which post types show the metabox and whether the Geo Tags taxonomy is public.
* Added automatic sync of the Geo Tags taxonomy from post coordinates.

= 0.2.0 =
* Added the location taxonomy (`geo_tagr_location`) with automatic term sync on save.

= 0.1.0 =
* Added a block editor sidebar panel and a classic editor metabox with a Leaflet map.
* Added Nominatim forward and reverse geocoding.
* Added the `geo_tagr_get_post_meta()` helper function.
