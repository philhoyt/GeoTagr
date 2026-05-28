/**
 * Provider-aware geocoding module.
 *
 * Exports geocodeForward(query) and geocodeReverse(lat, lng).
 * Both return a normalised { lat, lng, name, address } object.
 *
 * Provider and API key are read from window.geoTagrData at call time so
 * they reflect whatever was set server-side via wp_localize_script.
 *
 * Falls back to Nominatim when the configured provider is not 'nominatim'
 * but no API key has been saved.
 */

const NOMINATIM_SEARCH =
	'https://nominatim.openstreetmap.org/search?format=jsonv2';
const NOMINATIM_REVERSE =
	'https://nominatim.openstreetmap.org/reverse?format=jsonv2';
const NOMINATIM_UA = `GeoTagr/${window.geoTagrData?.version ?? '1.0.0'}`;

// Nominatim doesn't index suite/unit numbers — strip and retry.
const UNIT_PATTERN = /,?\s*(ste|suite|apt|apartment|unit|#)\s*[\w-]+/gi;

function stripUnit(query) {
	return query
		.replace(UNIT_PATTERN, '')
		.replace(/\s{2,}/g, ' ')
		.trim();
}

function config() {
	const data = window.geoTagrData ?? {};
	const provider = data.geocodingProvider ?? 'nominatim';
	const apiKey = data.geocodingApiKey ?? '';
	// Fall back to Nominatim when a keyed provider has no key configured.
	const effective =
		provider !== 'nominatim' && !apiKey ? 'nominatim' : provider;
	return { provider: effective, apiKey };
}

// ─── Nominatim ───────────────────────────────────────────────────────────────

function nominatimForward(query) {
	const url = (q) => `${NOMINATIM_SEARCH}&limit=1&q=${encodeURIComponent(q)}`;
	const opts = { headers: { 'User-Agent': NOMINATIM_UA } };

	return fetch(url(query), opts)
		.then((r) => r.json())
		.then((results) => {
			if (results.length) {
				return results[0];
			}
			const stripped = stripUnit(query);
			if (stripped === query) {
				return null;
			}
			return fetch(url(stripped), opts)
				.then((r) => r.json())
				.then((r2) => r2[0] ?? null);
		})
		.then((result) => {
			if (!result) {
				return null;
			}
			// If no named place from forward search, reverse to find POI.
			if (result.name) {
				return {
					lat: parseFloat(result.lat),
					lng: parseFloat(result.lon),
					name: result.name,
					address: result.display_name ?? '',
				};
			}
			return fetch(
				`${NOMINATIM_REVERSE}&lat=${result.lat}&lon=${result.lon}`,
				{ headers: { 'User-Agent': NOMINATIM_UA } }
			)
				.then((r) => r.json())
				.then((rev) => ({
					lat: parseFloat(result.lat),
					lng: parseFloat(result.lon),
					name:
						rev.name && rev.category !== 'highway' ? rev.name : '',
					address: result.display_name ?? '',
				}))
				.catch(() => ({
					lat: parseFloat(result.lat),
					lng: parseFloat(result.lon),
					name: '',
					address: result.display_name ?? '',
				}));
		});
}

function nominatimReverse(lat, lng) {
	return fetch(`${NOMINATIM_REVERSE}&lat=${lat}&lon=${lng}`, {
		headers: { 'User-Agent': NOMINATIM_UA },
	})
		.then((r) => r.json())
		.then((data) => ({
			lat,
			lng,
			name: data.name && data.category !== 'highway' ? data.name : '',
			address: data.display_name ?? '',
		}));
}

// ─── Google Maps Geocoding API ────────────────────────────────────────────────

const GOOGLE_BASE = 'https://maps.googleapis.com/maps/api/geocode/json';

function googleForward(query, apiKey) {
	return fetch(
		`${GOOGLE_BASE}?address=${encodeURIComponent(query)}&key=${apiKey}`
	)
		.then((r) => r.json())
		.then((data) => {
			const result = data.results?.[0];
			if (!result) {
				return null;
			}
			return {
				lat: result.geometry.location.lat,
				lng: result.geometry.location.lng,
				// Google Geocoding API does not return business names.
				name: '',
				address: result.formatted_address ?? '',
			};
		});
}

function googleReverse(lat, lng, apiKey) {
	return fetch(`${GOOGLE_BASE}?latlng=${lat},${lng}&key=${apiKey}`)
		.then((r) => r.json())
		.then((data) => {
			const result = data.results?.[0];
			if (!result) {
				return null;
			}
			return {
				lat,
				lng,
				name: '',
				address: result.formatted_address ?? '',
			};
		});
}

// ─── Mapbox Geocoding API (v5) ────────────────────────────────────────────────

const MAPBOX_BASE = 'https://api.mapbox.com/geocoding/v5/mapbox.places';

function mapboxForward(query, apiKey) {
	return fetch(
		`${MAPBOX_BASE}/${encodeURIComponent(query)}.json?access_token=${apiKey}&limit=1`
	)
		.then((r) => r.json())
		.then((data) => {
			const feature = data.features?.[0];
			if (!feature) {
				return null;
			}
			return {
				lat: feature.center[1],
				lng: feature.center[0],
				// Only use text as name when result is a POI, not an address.
				name: feature.place_type?.[0] === 'poi' ? feature.text : '',
				address: feature.place_name ?? '',
			};
		});
}

function mapboxReverse(lat, lng, apiKey) {
	return fetch(
		`${MAPBOX_BASE}/${lng},${lat}.json?types=poi,address&access_token=${apiKey}&limit=1`
	)
		.then((r) => r.json())
		.then((data) => {
			const feature = data.features?.[0];
			if (!feature) {
				return null;
			}
			return {
				lat,
				lng,
				name: feature.place_type?.[0] === 'poi' ? feature.text : '',
				address: feature.place_name ?? '',
			};
		});
}

// ─── Public API ───────────────────────────────────────────────────────────────

/**
 * Forward geocode a query string.
 * Returns { lat, lng, name, address } or null if no result found.
 *
 * @param {string} query Address or place name to geocode.
 * @return {Promise<{lat: number, lng: number, name: string, address: string}|null>} Normalised result or null.
 */
export function geocodeForward(query) {
	const { provider, apiKey } = config();
	switch (provider) {
		case 'google':
			return googleForward(query, apiKey);
		case 'mapbox':
			return mapboxForward(query, apiKey);
		default:
			return nominatimForward(query);
	}
}

/**
 * Reverse geocode lat/lng coordinates.
 * Returns { lat, lng, name, address } or null if no result found.
 *
 * @param {number} lat Latitude.
 * @param {number} lng Longitude.
 * @return {Promise<{lat: number, lng: number, name: string, address: string}|null>} Normalised result or null.
 */
export function geocodeReverse(lat, lng) {
	const { provider, apiKey } = config();
	switch (provider) {
		case 'google':
			return googleReverse(lat, lng, apiKey);
		case 'mapbox':
			return mapboxReverse(lat, lng, apiKey);
		default:
			return nominatimReverse(lat, lng);
	}
}
