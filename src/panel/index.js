import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { useSelect } from '@wordpress/data';
import { useEntityProp } from '@wordpress/core-data';
import { TextControl, Button, Notice, Spinner } from '@wordpress/components';
import { useState, useEffect, useRef, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { geocodeForward, geocodeReverse } from '../geocoding';

// Fix Leaflet's broken default icon path when bundled with webpack.
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
	iconRetinaUrl: new URL(
		'leaflet/dist/images/marker-icon-2x.png',
		import.meta.url
	).href,
	iconUrl: new URL('leaflet/dist/images/marker-icon.png', import.meta.url)
		.href,
	shadowUrl: new URL('leaflet/dist/images/marker-shadow.png', import.meta.url)
		.href,
});

function GeoTagrPanel() {
	const postType = useSelect(
		(select) => select('core/editor').getCurrentPostType(),
		[]
	);

	const [lat, setLat] = useEntityProp('postType', postType, '_geo_tagr_lat');
	const [lng, setLng] = useEntityProp('postType', postType, '_geo_tagr_lng');
	const [place, setPlace] = useEntityProp(
		'postType',
		postType,
		'_geo_tagr_place'
	);
	const [address, setAddress] = useEntityProp(
		'postType',
		postType,
		'_geo_tagr_address'
	);

	const [error, setError] = useState('');
	const [loading, setLoading] = useState(false);

	const mapInstanceRef = useRef(null);
	const markerRef = useRef(null);

	const mapContainerRef = useCallback((node) => {
		if (!node || mapInstanceRef.current) {
			return;
		}
		const map = L.map(node, { zoomControl: true }).setView(
			[lat || 0, lng || 0],
			lat ? 12 : 2
		);
		L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
			attribution:
				'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
			maxZoom: 19,
		}).addTo(map);

		if (lat && lng) {
			markerRef.current = L.marker([lat, lng]).addTo(map);
		}

		mapInstanceRef.current = map;
	}, []); // eslint-disable-line react-hooks/exhaustive-deps

	useEffect(() => {
		const map = mapInstanceRef.current;
		if (!map) {
			return;
		}
		const numLat = parseFloat(lat);
		const numLng = parseFloat(lng);
		if (isNaN(numLat) || isNaN(numLng)) {
			return;
		}
		map.setView([numLat, numLng], 12);
		if (markerRef.current) {
			markerRef.current.setLatLng([numLat, numLng]);
		} else {
			markerRef.current = L.marker([numLat, numLng]).addTo(map);
		}
	}, [lat, lng]);

	useEffect(() => {
		return () => {
			if (mapInstanceRef.current) {
				mapInstanceRef.current.remove();
				mapInstanceRef.current = null;
				markerRef.current = null;
			}
		};
	}, []);

	function handleUseMyLocation() {
		if (!navigator.geolocation) {
			setError(
				__('Geolocation is not supported by your browser.', 'geotagr')
			);
			return;
		}
		setLoading(true);
		setError('');
		navigator.geolocation.getCurrentPosition(
			(position) => {
				const { latitude, longitude } = position.coords;
				setLat(latitude);
				setLng(longitude);
				geocodeReverse(latitude, longitude)
					.then((result) => {
						if (result) {
							setPlace(result.name);
							setAddress(result.address);
						}
					})
					.catch(() => {})
					.finally(() => setLoading(false));
			},
			(err) => {
				setLoading(false);
				setError(
					err.message ||
						__('Could not retrieve your location.', 'geotagr')
				);
			}
		);
	}

	function handleSearchOnAddress() {
		if (!address?.trim()) {
			setError(__('Enter an address to search.', 'geotagr'));
			return;
		}
		setLoading(true);
		setError('');
		geocodeForward(address)
			.then((result) => {
				if (!result) {
					setError(
						__('No results found for that address.', 'geotagr')
					);
					return;
				}
				setLat(result.lat);
				setLng(result.lng);
				setPlace(result.name);
				setAddress(result.address);
			})
			.catch(() =>
				setError(
					__('Address lookup failed. Please try again.', 'geotagr')
				)
			)
			.finally(() => setLoading(false));
	}

	return (
		<PluginDocumentSettingPanel
			name="geo-tagr-panel"
			title={__('GeoTagr', 'geotagr')}
		>
			{error && (
				<Notice
					status="error"
					isDismissible={true}
					onRemove={() => setError('')}
				>
					{error}
				</Notice>
			)}

			<TextControl
				label={__('Full address', 'geotagr')}
				value={address ?? ''}
				onChange={setAddress}
				placeholder={__('Enter an address…', 'geotagr')}
			/>

			<div className="geo-tagr-actions">
				<Button
					variant="secondary"
					onClick={handleUseMyLocation}
					disabled={loading}
				>
					{loading ? (
						<>
							<Spinner />
							{__('Working…', 'geotagr')}
						</>
					) : (
						__('Use my location', 'geotagr')
					)}
				</Button>
				<Button
					variant="secondary"
					onClick={handleSearchOnAddress}
					disabled={loading}
				>
					{__('Search on Address', 'geotagr')}
				</Button>
			</div>

			<TextControl
				label={__('Latitude', 'geotagr')}
				value={lat ?? ''}
				onChange={(v) => setLat(v === '' ? '' : parseFloat(v))}
				type="number"
				step="any"
			/>
			<TextControl
				label={__('Longitude', 'geotagr')}
				value={lng ?? ''}
				onChange={(v) => setLng(v === '' ? '' : parseFloat(v))}
				type="number"
				step="any"
			/>
			<TextControl
				label={__('Place name', 'geotagr')}
				value={place ?? ''}
				onChange={setPlace}
			/>

			<div
				ref={mapContainerRef}
				className="geo-tagr-map"
				aria-label={__('Location map preview', 'geotagr')}
			/>
		</PluginDocumentSettingPanel>
	);
}

registerPlugin('geo-tagr', { render: GeoTagrPanel });
