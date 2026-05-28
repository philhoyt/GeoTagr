/* Classic editor metabox — geolocation + Nominatim geocoding. */

const NOMINATIM_REVERSE =
	'https://nominatim.openstreetmap.org/reverse?format=jsonv2';
const NOMINATIM_SEARCH =
	'https://nominatim.openstreetmap.org/search?format=jsonv2';
const USER_AGENT = `GeoTagr/${window.geoTagrData?.version ?? '1.0.0'}`;

const UNIT_PATTERN = /,?\s*(ste|suite|apt|apartment|unit|#)\s*[\w-]+/gi;

function stripUnit(address) {
	return address
		.replace(UNIT_PATTERN, '')
		.replace(/\s{2,}/g, ' ')
		.trim();
}

function nominatimSearch(query) {
	const url = (q) => `${NOMINATIM_SEARCH}&limit=1&q=${encodeURIComponent(q)}`;
	const opts = { headers: { 'User-Agent': USER_AGENT } };

	return fetch(url(query), opts)
		.then((r) => r.json())
		.then((results) => {
			if (results.length) {
				return results;
			}
			const stripped = stripUnit(query);
			if (stripped === query) {
				return [];
			}
			return fetch(url(stripped), opts).then((r) => r.json());
		});
}

document.addEventListener('DOMContentLoaded', () => {
	const useLocationBtn = document.getElementById('geo-tagr-use-location');
	const searchAddressBtn = document.getElementById('geo-tagr-search-address');

	if (!useLocationBtn && !searchAddressBtn) {
		return;
	}

	const errorEl = document.getElementById('geo-tagr-location-error');
	const latInput = document.getElementById('geo_tagr_lat');
	const lngInput = document.getElementById('geo_tagr_lng');
	const placeInput = document.getElementById('geo_tagr_place');
	const addressInput = document.getElementById('geo_tagr_address');

	function setError(msg) {
		if (errorEl) {
			errorEl.textContent = msg;
			errorEl.style.display = msg ? 'inline' : 'none';
		}
	}

	function setBusy(busy) {
		if (useLocationBtn) {
			useLocationBtn.disabled = busy;
		}
		if (searchAddressBtn) {
			searchAddressBtn.disabled = busy;
		}
	}

	if (useLocationBtn) {
		useLocationBtn.addEventListener('click', () => {
			setError('');

			if (!navigator.geolocation) {
				setError('Geolocation is not supported by your browser.');
				return;
			}

			setBusy(true);
			useLocationBtn.textContent = 'Detecting…';

			navigator.geolocation.getCurrentPosition(
				(position) => {
					const { latitude, longitude } = position.coords;

					if (latInput) {
						latInput.value = latitude;
					}
					if (lngInput) {
						lngInput.value = longitude;
					}

					fetch(
						`${NOMINATIM_REVERSE}&lat=${latitude}&lon=${longitude}`,
						{ headers: { 'User-Agent': USER_AGENT } }
					)
						.then((r) => r.json())
						.then((data) => {
							if (placeInput) {
								placeInput.value =
									data.name ?? data.display_name ?? '';
							}
							if (addressInput) {
								addressInput.value = data.display_name ?? '';
							}
						})
						.catch(() => {})
						.finally(() => {
							setBusy(false);
							useLocationBtn.textContent = 'Use my location';
						});
				},
				(err) => {
					setBusy(false);
					useLocationBtn.textContent = 'Use my location';
					setError(
						err.message || 'Could not retrieve your location.'
					);
				}
			);
		});
	}

	if (searchAddressBtn) {
		searchAddressBtn.addEventListener('click', () => {
			const query = addressInput?.value.trim();
			if (!query) {
				setError('Enter an address to search.');
				return;
			}

			setError('');
			setBusy(true);
			searchAddressBtn.textContent = 'Searching…';

			nominatimSearch(query)
				.then((results) => {
					if (!results.length) {
						setError('No results found for that address.');
						return;
					}
					const result = results[0];
					if (latInput) {
						latInput.value = result.lat;
					}
					if (lngInput) {
						lngInput.value = result.lon;
					}
					if (placeInput) {
						placeInput.value =
							result.name ?? result.display_name ?? '';
					}
					if (addressInput) {
						addressInput.value = result.display_name ?? '';
					}
				})
				.catch(() =>
					setError('Address lookup failed. Please try again.')
				)
				.finally(() => {
					setBusy(false);
					searchAddressBtn.textContent = 'Search on Address';
				});
		});
	}
});
