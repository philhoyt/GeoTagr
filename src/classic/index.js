/* Classic editor metabox — geolocation + geocoding. */

import { geocodeForward, geocodeReverse } from '../geocoding';

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

					geocodeReverse(latitude, longitude)
						.then((result) => {
							if (result) {
								if (placeInput) {
									placeInput.value = result.name;
								}
								if (addressInput) {
									addressInput.value = result.address;
								}
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

			geocodeForward(query)
				.then((result) => {
					if (!result) {
						setError('No results found for that address.');
						return;
					}
					if (latInput) {
						latInput.value = result.lat;
					}
					if (lngInput) {
						lngInput.value = result.lng;
					}
					if (placeInput) {
						placeInput.value = result.name;
					}
					if (addressInput) {
						addressInput.value = result.address;
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
