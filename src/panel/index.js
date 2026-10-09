import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { useSelect } from '@wordpress/data';
import { useEntityProp } from '@wordpress/core-data';
import { TextControl, Button, Notice, Spinner } from '@wordpress/components';
import { useState, useEffect, useRef, useCallback } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { speak } from '@wordpress/a11y';
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

const LAT_KEY = '_geo_tagr_lat';
const LNG_KEY = '_geo_tagr_lng';
const PLACE_KEY = '_geo_tagr_place';
const ADDRESS_KEY = '_geo_tagr_address';

/** Stored precision: 5 decimals ≈ 1 m, more than the taxonomy's 4 needs. */
const COORD_PRECISION = 1e5;

function toNumber(value) {
	const n = typeof value === 'number' ? value : parseFloat(value);
	return Number.isFinite(n) ? n : null;
}

function roundCoord(value) {
	return Math.round(value * COORD_PRECISION) / COORD_PRECISION;
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

function GeoTagrPanel() {
	const postType = useSelect(
		(select) => select('core/editor').getCurrentPostType(),
		[]
	);

	// Registered meta lives under the post record's `meta` property.
	const [meta, setMeta] = useEntityProp('postType', postType, 'meta');
	const stored = meta ?? {};

	// Keep the latest meta in a ref so async callbacks (geolocation, fetch)
	// patch on top of the current edits rather than a stale closure.
	const metaRef = useRef(stored);
	metaRef.current = stored;

	const numLat = toNumber(stored[LAT_KEY]);
	const numLng = toNumber(stored[LNG_KEY]);
	// Core returns 0 for an unset number meta, so 0,0 means "no location".
	const isPlaceholder = numLat === 0 && numLng === 0;
	const hasCoords = numLat !== null && numLng !== null && !isPlaceholder;

	const place = stored[PLACE_KEY] ?? '';
	const address = stored[ADDRESS_KEY] ?? '';

	/**
	 * Merge a partial change into the post's meta edits.
	 *
	 * Omits the 0,0 placeholder for unset coordinates so that editing an
	 * unrelated field never persists Null Island as a real location.
	 *
	 * @param {Object} patch Meta keys to change. Use null to delete a key.
	 */
	const updateMeta = useCallback(
		(patch) => {
			const current = metaRef.current;
			const next = { ...current, ...patch };
			const currentIsPlaceholder =
				toNumber(current[LAT_KEY]) === 0 &&
				toNumber(current[LNG_KEY]) === 0;
			if (currentIsPlaceholder) {
				if (!(LAT_KEY in patch)) {
					delete next[LAT_KEY];
				}
				if (!(LNG_KEY in patch)) {
					delete next[LNG_KEY];
				}
			}
			setMeta(next);
		},
		[setMeta]
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
			hasCoords ? [numLat, numLng] : [0, 0],
			hasCoords ? 12 : 2
		);
		L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
			attribution:
				'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
			maxZoom: 19,
		}).addTo(map);

		if (hasCoords) {
			markerRef.current = L.marker([numLat, numLng]).addTo(map);
		}

		mapInstanceRef.current = map;
	}, []); // eslint-disable-line react-hooks/exhaustive-deps

	useEffect(() => {
		const map = mapInstanceRef.current;
		if (!map || !hasCoords) {
			return;
		}
		map.setView([numLat, numLng], 12);
		if (markerRef.current) {
			markerRef.current.setLatLng([numLat, numLng]);
		} else {
			markerRef.current = L.marker([numLat, numLng]).addTo(map);
		}
	}, [numLat, numLng, hasCoords]);

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
				const latitude = roundCoord(position.coords.latitude);
				const longitude = roundCoord(position.coords.longitude);
				updateMeta({ [LAT_KEY]: latitude, [LNG_KEY]: longitude });
				geocodeReverse(latitude, longitude)
					.then((result) => {
						if (result) {
							updateMeta({
								[PLACE_KEY]: result.name,
								[ADDRESS_KEY]: result.address,
							});
							announceResult(result);
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
				updateMeta({
					[LAT_KEY]: result.lat,
					[LNG_KEY]: result.lng,
					[PLACE_KEY]: result.name,
					[ADDRESS_KEY]: result.address,
				});
				announceResult(result);
			})
			.catch(() =>
				setError(
					__('Address lookup failed. Please try again.', 'geotagr')
				)
			)
			.finally(() => setLoading(false));
	}

	/**
	 * Build the onChange handler for a coordinate field.
	 *
	 * An empty field sends null, which deletes the key (an empty string
	 * would fail REST schema validation for a number and block the save).
	 *
	 * @param {string} key Meta key.
	 * @return {Function} Change handler.
	 */
	function onCoordChange(key) {
		return (value) => {
			const n = value === '' ? null : toNumber(value);
			updateMeta({ [key]: n });
		};
	}

	const displayCoord = (n) => (n === null || isPlaceholder ? '' : n);

	return (
		<PluginDocumentSettingPanel
			name="geo-tagr-panel"
			className="geo-tagr-panel"
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
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={__('Full address', 'geotagr')}
				value={address}
				onChange={(v) => updateMeta({ [ADDRESS_KEY]: v })}
				placeholder={__('Enter an address…', 'geotagr')}
			/>

			<div className="geo-tagr-actions">
				<Button
					__next40pxDefaultSize
					variant="secondary"
					onClick={handleUseMyLocation}
					disabled={loading}
					accessibleWhenDisabled
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
					__next40pxDefaultSize
					variant="secondary"
					onClick={handleSearchOnAddress}
					disabled={loading}
					accessibleWhenDisabled
				>
					{__('Search on Address', 'geotagr')}
				</Button>
			</div>

			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={__('Latitude', 'geotagr')}
				value={displayCoord(numLat)}
				onChange={onCoordChange(LAT_KEY)}
				type="number"
				step="any"
			/>
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={__('Longitude', 'geotagr')}
				value={displayCoord(numLng)}
				onChange={onCoordChange(LNG_KEY)}
				type="number"
				step="any"
			/>
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={__('Place name', 'geotagr')}
				value={place}
				onChange={(v) => updateMeta({ [PLACE_KEY]: v })}
			/>

			<div
				ref={mapContainerRef}
				className="geo-tagr-map"
				role="region"
				aria-label={__('Location map preview', 'geotagr')}
			/>
			<p className="screen-reader-text">
				{__(
					'The map is a visual preview only. Use the Latitude and Longitude fields above to set the location.',
					'geotagr'
				)}
			</p>
		</PluginDocumentSettingPanel>
	);
}

registerPlugin('geo-tagr', { render: GeoTagrPanel });
