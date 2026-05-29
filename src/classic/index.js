/* Classic editor metabox — geolocation + geocoding. */

import { __ } from '@wordpress/i18n';
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
				setError(
					__(
						'Geolocation is not supported by your browser.',
						'geotagr'
					)
				);
				return;
			}

			setBusy(true);
			useLocationBtn.textContent = __('Detecting…', 'geotagr');

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
							useLocationBtn.textContent = __(
								'Use my location',
								'geotagr'
							);
						});
				},
				(err) => {
					setBusy(false);
					useLocationBtn.textContent = __(
						'Use my location',
						'geotagr'
					);
					setError(
						err.message ||
							__('Could not retrieve your location.', 'geotagr')
					);
				}
			);
		});
	}

	if (searchAddressBtn) {
		searchAddressBtn.addEventListener('click', () => {
			const query = addressInput?.value.trim();
			if (!query) {
				setError(__('Enter an address to search.', 'geotagr'));
				return;
			}

			setError('');
			setBusy(true);
			searchAddressBtn.textContent = __('Searching…', 'geotagr');

			geocodeForward(query)
				.then((result) => {
					if (!result) {
						setError(
							__('No results found for that address.', 'geotagr')
						);
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
					setError(
						__(
							'Address lookup failed. Please try again.',
							'geotagr'
						)
					)
				)
				.finally(() => {
					setBusy(false);
					searchAddressBtn.textContent = __(
						'Search on Address',
						'geotagr'
					);
				});
		});
	}
});
