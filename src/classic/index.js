/* Classic editor metabox — geolocation + Nominatim reverse geocode. */

const NOMINATIM_REVERSE =
	'https://nominatim.openstreetmap.org/reverse?format=jsonv2';
const USER_AGENT = `GeoTagr/${window.geoTagrData?.version ?? '1.0.0'}`;

document.addEventListener('DOMContentLoaded', () => {
	const btn = document.getElementById('geo-tagr-use-location');
	if (!btn) {
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

	btn.addEventListener('click', () => {
		setError('');

		if (!navigator.geolocation) {
			setError('Geolocation is not supported by your browser.');
			return;
		}

		btn.disabled = true;
		btn.textContent = 'Detecting…';

		navigator.geolocation.getCurrentPosition(
			(position) => {
				const { latitude, longitude } = position.coords;

				if (latInput) {
					latInput.value = latitude;
				}
				if (lngInput) {
					lngInput.value = longitude;
				}

				fetch(`${NOMINATIM_REVERSE}&lat=${latitude}&lon=${longitude}`, {
					headers: { 'User-Agent': USER_AGENT },
				})
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
						btn.disabled = false;
						btn.textContent = 'Use my location';
					});
			},
			(err) => {
				btn.disabled = false;
				btn.textContent = 'Use my location';
				setError(
					err.message ||
						'Could not retrieve your location. Please enter it manually.'
				);
			}
		);
	});
});
