# GeoTagr

Attaches coordinates, a place name and an address to WordPress posts, with a map in the editor and a Location Name block.

GeoTagr stores latitude, longitude, a place name and a formatted address as post meta on the post types you enable. Editors set the location from a sidebar panel in the block editor or a metabox in the classic editor, both with a Leaflet map. Address and coordinate lookups go through OpenStreetMap Nominatim, Google Maps Platform or Mapbox. Each located post also gets a term in a `geo_tagr_location` taxonomy, and a `geotagr/location-name` block prints the place name on the front end.

## Requirements

- WordPress 6.7 or later
- PHP 8.2 or later
- An API key for Google or Mapbox if you choose either provider. Nominatim needs no key.

## Installation

1. Download `geotagr.zip` from the [Releases](https://github.com/philhoyt/GeoTagr/releases) page.
2. Upload it under Plugins > Add New > Upload Plugin and activate it.
3. Go to Settings > GeoTagr.

Updates are delivered from GitHub releases through the bundled Plugin Update Checker.

## Configuration

Settings > GeoTagr has five fields:

- Enable GeoTagr on: the public post types that get the panel, metabox and meta keys. Default: posts.
- Location taxonomy: make `geo_tagr_location` public. Public terms expose coordinates to about 11 m through the term slug, and the term name is the street address when a post has no place name. The visibility setting below does not cover terms.
- Public API visibility: what anonymous REST readers get for latitude, longitude and address. Private (default) returns 0, 0 and an empty address; Rounded returns coordinates to 3 decimals (about 110 m) and no address; Exact returns stored values. The place name is always public.
- Geocoding provider: Nominatim (default), Google Maps Geocoding API or Mapbox Geocoding API.
- API key: masked, with a Show button. Google lookups run through a server-side proxy so the key never reaches the browser. Mapbox lookups run in the browser with the public token.

The settings page also adds suggested text to Settings > Privacy > Policy Guide that lists which services receive data. See the External services section in `readme.txt`.

## Usage

In the block editor, open the GeoTagr panel in the post sidebar. Search for an address, click the map or type coordinates; the other fields are filled in by geocoding. The classic editor shows the same controls in a metabox.

Add the Location Name block anywhere inside a post or in a Query Loop. It reads the place name from the post context and renders nothing when the post has no location. Color, typography, spacing and border supports are enabled.

### Post meta

| Key | Type | Notes |
|-----|------|-------|
| `_geo_tagr_lat` | number | -90 to 90 |
| `_geo_tagr_lng` | number | -180 to 180 |
| `_geo_tagr_place` | string | Always public in the REST API |
| `_geo_tagr_address` | string | Gated by Public API visibility |

Writing any of them requires permission to edit that post. Writes made outside the editor (REST, WP-CLI, other plugins) also sync the location term.

`geo_tagr_get_post_meta( int $post_id )` returns `['lat', 'lng', 'place', 'address']` or `null`. Wrap it in `function_exists()` in theme code.

### REST API

Location meta is exposed on the post endpoints of enabled post types. Anonymous readers get the shape set by Public API visibility; users who can edit the post get stored values. The gate applies to collections, `_fields` responses and create/update responses.

`GET /wp-json/geotagr/v1/geocode?type=forward&query=...` and `?type=reverse&lat=...&lng=...` proxy Google lookups from the server. Results are cached for a day. Access is decided by the `geo_tagr_can_geocode` filter; by default anyone who can edit posts of an enabled post type.

### Hooks

| Hook | Type | Purpose |
|------|------|---------|
| `geo_tagr_allowed_post_types` | filter | Post types that get the meta keys, panel and taxonomy |
| `geo_tagr_meta` | filter | The meta array returned for a post |
| `geo_tagr_meta_saved` | action | Fires after location meta is saved for a post |
| `geo_tagr_location_term` | filter | The resolved location term after sync |
| `geo_tagr_rest_location_visibility` | filter | Visibility mode per request; receives the post as the fourth argument |
| `geo_tagr_can_geocode` | filter | Who may call the geocoding proxy |
| `geo_tagr_geocode_request_args` | filter | Arguments for the outbound Google request |
| `geo_tagr_geocode_result` | filter | The proxy result before it is cached |
| `geo_tagr_geocode_cache_ttl` | filter | Cache lifetime in seconds, default one day |

## Development

```bash
composer install
npm install
npm run build
npm run env:start
```

`composer install` also copies Plugin Update Checker into `lib/`.

- `npm start` runs the webpack watch.
- `npm run build` builds the editor, classic, settings and block assets into `build/`.
- `npm run lint:js` and `npm run lint:css` run ESLint and Stylelint; `composer lint` runs PHP CodeSniffer.
- `composer analyse` runs PHPStan.
- `npm run test:unit` runs the Jest tests in `src/`.
- `npm run test:php` runs PHPUnit inside wp-env.
- `npm run test:e2e` runs the Playwright tests in `tests/e2e/` against wp-env.

CI runs `composer audit`, the linters, PHPStan, Jest, Playwright and PHPUnit on every pull request.

## Releases

Bump the version in `geotagr.php` (header and `GEOTAGR_VERSION`), `package.json`, `src/blocks/location-name/block.json` and `readme.txt`, add a changelog entry, commit, then push a `v`-prefixed tag:

```bash
git tag v0.10.2 && git push origin v0.10.2
```

The release workflow builds the assets, runs `wp-scripts plugin-zip`, and creates a GitHub release with the matching changelog section as its body and `geotagr.zip` and `readme.txt` attached.

## Limitations

- Nominatim and Mapbox lookups run in the editor's browser and send the browser's IP address to those services. Only the Google provider is proxied through the server.
- Mapbox Geocoding v6 does not return place names, so only the address is filled in with that provider.
- With a public location taxonomy, term slugs expose coordinates to four decimals regardless of the Public API visibility setting.
- Private visibility returns 0, 0 for coordinates. Front-end code must treat 0, 0 as "no location", not a point in the Gulf of Guinea.
- Uninstalling deletes all location meta, terms and options on every site in the network.
