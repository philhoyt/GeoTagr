/**
 * Provider-aware geocoding module.
 *
 * Exports geocodeForward(query) and geocodeReverse(lat, lng).
 * Both return a normalised { lat, lng, name, address } object.
 *
 * Provider and API key are read from window.geoTagrData at call time so
 * they reflect whatever was set server-side via wp_add_inline_script.
 *
 * Google is always proxied server-side, so its key never reaches the
 * browser; the server reports whether a key is saved via geocodingHasKey.
 * Mapbox is called directly, so it needs its key client-side. Either way,
 * falls back to Nominatim when the selected provider has no key saved.
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

export function config() {
	const data = window.geoTagrData ?? {};
	const provider = data.geocodingProvider ?? 'nominatim';
	const apiKey = data.geocodingApiKey ?? '';
	// Google is proxied: the key stays on the server, so check the flag
	// rather than the (intentionally empty) browser key.
	const hasKey = provider === 'google' ? !!data.geocodingHasKey : !!apiKey;
	// Fall back to Nominatim when a keyed provider has no key configured.
	const effective =
		provider !== 'nominatim' && !hasKey ? 'nominatim' : provider;
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

// ─── Google (server-side proxy) ──────────────────────────────────────────────
// Google Places API blocks CORS, so all Google geocoding goes through a
// WordPress REST endpoint that makes the request server-side.

function googleProxy(params) {
	const data = window.geoTagrData ?? {};
	const url = new URL(
		data.proxyUrl ?? '/wp-json/geotagr/v1/geocode',
		window.location.origin
	);
	Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));
	return fetch(url.toString(), {
		headers: { 'X-WP-Nonce': data.nonce ?? '' },
	})
		.then((r) => r.json())
		.then((result) => result ?? null);
}

function googleForward(query) {
	return googleProxy({ type: 'forward', query }).then((result) => {
		if (!result || !Array.isArray(result) || result.length === 0) {
			return null;
		}
		return result[0];
	});
}

function googleReverse(lat, lng) {
	return googleProxy({ type: 'reverse', lat, lng });
}

// ─── Mapbox Geocoding API (v6) ────────────────────────────────────────────────
// Geocoding v6 no longer returns POI data (that moved to the Search Box API),
// so `name` is always empty for Mapbox; only the formatted address is set.

const MAPBOX_BASE = 'https://api.mapbox.com/search/geocode/v6';

function mapboxFeatureToResult(feature, fallbackLat, fallbackLng) {
	const props = feature.properties ?? {};
	const [geoLng, geoLat] = feature.geometry?.coordinates ?? [];
	return {
		lat: props.coordinates?.latitude ?? geoLat ?? fallbackLat,
		lng: props.coordinates?.longitude ?? geoLng ?? fallbackLng,
		name: '',
		address: props.full_address ?? props.place_formatted ?? '',
	};
}

function mapboxForward(query, apiKey) {
	const url = new URL(`${MAPBOX_BASE}/forward`);
	url.searchParams.set('q', query);
	url.searchParams.set('access_token', apiKey);
	url.searchParams.set('limit', '1');
	return fetch(url.toString())
		.then((r) => r.json())
		.then((data) => {
			const feature = data.features?.[0];
			return feature ? mapboxFeatureToResult(feature) : null;
		});
}

function mapboxReverse(lat, lng, apiKey) {
	const url = new URL(`${MAPBOX_BASE}/reverse`);
	url.searchParams.set('longitude', String(lng));
	url.searchParams.set('latitude', String(lat));
	url.searchParams.set('access_token', apiKey);
	url.searchParams.set('limit', '1');
	return fetch(url.toString())
		.then((r) => r.json())
		.then((data) => {
			const feature = data.features?.[0];
			return feature ? mapboxFeatureToResult(feature, lat, lng) : null;
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
	const normalised = query.replace(/\s{2,}/g, ' ').trim();
	const { provider, apiKey } = config();
	switch (provider) {
		case 'google':
			return googleForward(normalised);
		case 'mapbox':
			return mapboxForward(normalised, apiKey);
		default:
			return nominatimForward(normalised);
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
			return googleReverse(lat, lng);
		case 'mapbox':
			return mapboxReverse(lat, lng, apiKey);
		default:
			return nominatimReverse(lat, lng);
	}
}
