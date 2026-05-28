import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { useSelect } from '@wordpress/data';
import { useEntityProp } from '@wordpress/core-data';
import { TextControl, Button, Notice, Spinner } from '@wordpress/components';
import { useState, useEffect, useRef, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

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

const NOMINATIM_REVERSE =
	'https://nominatim.openstreetmap.org/reverse?format=jsonv2';
const NOMINATIM_SEARCH =
	'https://nominatim.openstreetmap.org/search?format=jsonv2&limit=5';
const USER_AGENT = `GeoTagr/${window.geoTagrData?.version ?? '1.0.0'}`;
const DEBOUNCE_MS = 300;

function useDebounce(value, delay) {
	const [debounced, setDebounced] = useState(value);
	useEffect(() => {
		const id = setTimeout(() => setDebounced(value), delay);
		return () => clearTimeout(id);
	}, [value, delay]);
	return debounced;
}

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

	const [geoError, setGeoError] = useState('');
	const [geoLoading, setGeoLoading] = useState(false);
	const [searchQuery, setSearchQuery] = useState('');
	const [searchResults, setSearchResults] = useState([]);
	const [searchLoading, setSearchLoading] = useState(false);

	const mapRef = useRef(null);
	const mapInstanceRef = useRef(null);
	const markerRef = useRef(null);

	// Initialise Leaflet map once the container div mounts.
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
		mapRef.current = node;
	}, []); // eslint-disable-line react-hooks/exhaustive-deps

	// Update map marker when coordinates change.
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

	// Destroy map on unmount.
	useEffect(() => {
		return () => {
			if (mapInstanceRef.current) {
				mapInstanceRef.current.remove();
				mapInstanceRef.current = null;
				markerRef.current = null;
			}
		};
	}, []);

	// Debounced address search.
	const debouncedQuery = useDebounce(searchQuery, DEBOUNCE_MS);
	useEffect(() => {
		if (!debouncedQuery.trim()) {
			setSearchResults([]);
			return;
		}
		setSearchLoading(true);
		fetch(`${NOMINATIM_SEARCH}&q=${encodeURIComponent(debouncedQuery)}`, {
			headers: { 'User-Agent': USER_AGENT },
		})
			.then((r) => r.json())
			.then((results) => setSearchResults(results))
			.catch(() => setSearchResults([]))
			.finally(() => setSearchLoading(false));
	}, [debouncedQuery]);

	function handleUseMyLocation() {
		if (!navigator.geolocation) {
			setGeoError(
				__('Geolocation is not supported by your browser.', 'geotagr')
			);
			return;
		}
		setGeoLoading(true);
		setGeoError('');
		navigator.geolocation.getCurrentPosition(
			(position) => {
				const { latitude, longitude } = position.coords;
				setLat(latitude);
				setLng(longitude);
				fetch(`${NOMINATIM_REVERSE}&lat=${latitude}&lon=${longitude}`, {
					headers: { 'User-Agent': USER_AGENT },
				})
					.then((r) => r.json())
					.then((data) => {
						setPlace(data.name ?? data.display_name ?? '');
						setAddress(data.display_name ?? '');
					})
					.catch(() => {})
					.finally(() => setGeoLoading(false));
			},
			(err) => {
				setGeoLoading(false);
				setGeoError(
					err.message ||
						__(
							'Could not retrieve your location. Please enter it manually.',
							'geotagr'
						)
				);
			}
		);
	}

	function handleSelectResult(result) {
		setLat(parseFloat(result.lat));
		setLng(parseFloat(result.lon));
		setPlace(result.name ?? result.display_name ?? '');
		setAddress(result.display_name ?? '');
		setSearchQuery('');
		setSearchResults([]);
	}

	return (
		<PluginDocumentSettingPanel
			name="geo-tagr-panel"
			title={__('GeoTagr', 'geotagr')}
		>
			{geoError && (
				<Notice
					status="error"
					isDismissible={true}
					onRemove={() => setGeoError('')}
				>
					{geoError}
				</Notice>
			)}

			<Button
				variant="secondary"
				onClick={handleUseMyLocation}
				disabled={geoLoading}
				className="geo-tagr-location-btn"
			>
				{geoLoading ? (
					<>
						<Spinner />
						{__('Detecting…', 'geotagr')}
					</>
				) : (
					__('Use my location', 'geotagr')
				)}
			</Button>

			<div className="geo-tagr-search">
				<TextControl
					label={__('Search address', 'geotagr')}
					value={searchQuery}
					onChange={setSearchQuery}
					placeholder={__('Start typing an address…', 'geotagr')}
				/>
				{searchLoading && <Spinner />}
				{searchResults.length > 0 && (
					<ul className="geo-tagr-results">
						{searchResults.map((result) => (
							<li key={result.place_id}>
								<button
									type="button"
									className="geo-tagr-result-btn"
									onClick={() => handleSelectResult(result)}
								>
									{result.display_name}
								</button>
							</li>
						))}
					</ul>
				)}
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
			<TextControl
				label={__('Full address', 'geotagr')}
				value={address ?? ''}
				onChange={setAddress}
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
