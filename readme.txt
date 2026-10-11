== GeoTagr ==

Contributors: philhoyt
Tags: geolocation, geocoding, map, metadata, location
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 0.8.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Attach geographic location metadata to any post.

== Description ==

GeoTagr lets you attach geographic coordinates, a place name, and a formatted address to any post. It provides a block editor sidebar panel and a classic editor metabox, both with an interactive Leaflet map. Geocoding is handled by your choice of Nominatim (free, no key required), Google Places, or Mapbox.

Coordinates and addresses are private to editors by default: visitors and other sites reading the REST API receive only the place name unless you choose otherwise under Settings → GeoTagr → Public API visibility.

== External services ==

GeoTagr sends data to third-party services only when an editor looks up a location while writing a post. No visitor data is sent anywhere.

* **OpenStreetMap Nominatim** (default provider) — the address or coordinates being looked up are sent from the editor's browser to nominatim.openstreetmap.org, which also receives the browser's IP address. [Usage policy](https://operations.osmfoundation.org/policies/nominatim/) · [Privacy policy](https://wiki.osmfoundation.org/wiki/Privacy_Policy)
* **OpenStreetMap tiles** — the editor's map preview loads tiles from tile.openstreetmap.org, which receives the browser's IP address. [Tile usage policy](https://operations.osmfoundation.org/policies/tiles/)
* **Google Maps Platform** (optional) — lookups are sent from your server to places.googleapis.com and maps.googleapis.com with your API key; the editor's browser never contacts Google. [Terms](https://cloud.google.com/maps-platform/terms) · [Privacy policy](https://policies.google.com/privacy)
* **Mapbox** (optional) — lookups are sent from the editor's browser to api.mapbox.com with your public access token. [Terms](https://www.mapbox.com/legal/tos) · [Privacy policy](https://www.mapbox.com/legal/privacy)

Suggested privacy-policy text is added to Settings → Privacy → Policy Guide.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/geotagr`.
2. Activate the plugin through the Plugins screen in WordPress.
3. Go to Settings → GeoTagr to configure post types, taxonomy visibility, and your geocoding provider.

== Upgrade Notice ==

= 0.8.0 =
Anonymous REST API reads of latitude, longitude, and address now return 0, 0 and an empty address by default. A value of 0, 0 means redacted or absent, not a real coordinate; do not plot it. Logged-in users who can edit posts, including application-password clients, still receive stored values; the gate is role-level, so any contributor-and-above account reads coordinates on every post. Headless front ends, feeds, or map widgets that read coordinates anonymously need Settings → GeoTagr → Public API visibility set to "Rounded" or "Exact" (Exact restores the previous output). The place name stays public in every mode and can be a precise business or building name. Purge page and object caches after upgrading.

== Changelog ==

= 0.8.0 =
* Change: Latitude, longitude, and address are hidden from REST API readers who cannot edit posts. See the upgrade notice.
* Add: Public API visibility setting with Private (default), Rounded (coordinates to about 110 m, no address), and Exact modes.
* Add: `geo_tagr_rest_location_visibility` filter to override the mode per request; unknown values fall back to Private.
* Change: The location taxonomy setting now explains that public terms expose coordinates through the term slug and the street address through the term name when a post has no place name.

= 0.7.0 =
* Fix: The block editor panel and the Location Name block now read and save location data themselves. Before, saving only worked because the classic metabox was also rendered inside the block editor.
* Fix: The Google provider is used again when selected. Since 0.6.3, lookups silently fell back to Nominatim.
* Fix: Clearing a coordinate in the block editor no longer blocks saving the post.
* Change: Google reverse lookup uses Places API (New) Nearby Search. Enable "Places API (New)" on your Google Cloud project; the legacy Places API is no longer available to new projects.
* Change: Mapbox lookups use Geocoding v6. Mapbox no longer returns place names, so only the address is filled in.
* Add: Geocoding results from the proxy are cached for a day. New filters: `geo_tagr_geocode_request_args`, `geo_tagr_geocode_result`, `geo_tagr_geocode_cache_ttl`.
* Fix: Uninstall now removes location terms, and runs on every site of a multisite network.
* Fix: Making the location taxonomy public no longer requires re-saving permalinks.
* Fix: Editor assets load only on enabled post types, and the classic metabox no longer appears inside the block editor.
* Fix: Clearing location data through the REST API removes the post's location term.
* Fix: Coordinates outside the valid range are rejected on save.
* Add: Bundled translations are loaded, and editor scripts receive translations.
* Add: Suggested privacy policy text under Settings → Privacy, and an External services section in this readme.
* Fix: Accessibility of the settings page and editor controls: labelled fields, announced errors and results, and focus kept during lookups.

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
