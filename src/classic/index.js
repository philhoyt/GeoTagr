/* Classic editor metabox — geolocation + geocoding. */

import { __, sprintf } from '@wordpress/i18n';
import { speak } from '@wordpress/a11y';
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

	let isBusy = false;

	// The span has role="alert" and stays in the DOM, so changing its text
	// is announced; speak() covers browsers that miss the live region.
	function setError(msg) {
		if (errorEl) {
			errorEl.textContent = msg;
		}
		if (msg) {
			speak(msg, 'assertive');
		}
	}

	function announceResult(result) {
		const label = result.name || result.address;
		if (label) {
			speak(
				sprintf(
					/* translators: %s: place name or address. */
					__('Location found: %s', 'geotagr'),
					label
				)
			);
		}
	}

	// aria-disabled keeps the buttons focusable while a lookup runs, so
	// keyboard focus is not dropped; the click handlers check isBusy.
	function setBusy(busy) {
		isBusy = busy;
		[useLocationBtn, searchAddressBtn].forEach((btn) => {
			if (btn) {
				btn.setAttribute('aria-disabled', busy ? 'true' : 'false');
				btn.setAttribute('aria-busy', busy ? 'true' : 'false');
			}
		});
	}

	if (useLocationBtn) {
		useLocationBtn.addEventListener('click', () => {
			if (isBusy) {
				return;
			}
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
								announceResult(result);
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
			if (isBusy) {
				return;
			}
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
					announceResult(result);
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
