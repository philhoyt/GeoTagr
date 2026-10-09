/**
 * Unit tests for the provider-aware geocoding module.
 */

import { config, geocodeForward, geocodeReverse } from './geocoding';

function mockFetch(responses) {
	const calls = [];
	global.fetch = jest.fn((url) => {
		calls.push(String(url));
		const body = responses.shift();
		return Promise.resolve({ ok: true, json: () => Promise.resolve(body) });
	});
	return calls;
}

describe('config()', () => {
	afterEach(() => {
		delete window.geoTagrData;
	});

	it('defaults to nominatim when nothing is configured', () => {
		expect(config().provider).toBe('nominatim');
	});

	it('uses google when the server reports a saved key, even with no browser key', () => {
		window.geoTagrData = {
			geocodingProvider: 'google',
			geocodingApiKey: '',
			geocodingHasKey: true,
		};
		expect(config().provider).toBe('google');
	});

	it('falls back to nominatim for google without a saved key', () => {
		window.geoTagrData = {
			geocodingProvider: 'google',
			geocodingApiKey: '',
			geocodingHasKey: false,
		};
		expect(config().provider).toBe('nominatim');
	});

	it('uses mapbox only when a browser key is present', () => {
		window.geoTagrData = {
			geocodingProvider: 'mapbox',
			geocodingApiKey: 'pk.test',
			geocodingHasKey: true,
		};
		expect(config()).toEqual({ provider: 'mapbox', apiKey: 'pk.test' });

		window.geoTagrData = {
			geocodingProvider: 'mapbox',
			geocodingApiKey: '',
			geocodingHasKey: true,
		};
		expect(config().provider).toBe('nominatim');
	});
});

describe('google proxy routing', () => {
	beforeEach(() => {
		window.geoTagrData = {
			geocodingProvider: 'google',
			geocodingApiKey: '',
			geocodingHasKey: true,
			proxyUrl: 'https://example.test/wp-json/geotagr/v1/geocode',
			nonce: 'abc',
		};
	});

	afterEach(() => {
		delete window.geoTagrData;
		delete global.fetch;
	});

	it('forward: calls the proxy and unwraps the first result', async () => {
		const calls = mockFetch([
			[
				{ lat: 41.5, lng: -81.7, name: 'Place', address: 'Addr' },
				{ lat: 1, lng: 2, name: 'Other', address: 'Other' },
			],
		]);
		const result = await geocodeForward('  Cleveland   Ohio ');
		expect(calls[0]).toContain('/wp-json/geotagr/v1/geocode');
		expect(calls[0]).toContain('type=forward');
		expect(calls[0]).toContain('query=Cleveland+Ohio');
		expect(result).toEqual({
			lat: 41.5,
			lng: -81.7,
			name: 'Place',
			address: 'Addr',
		});
	});

	it('forward: returns null for an empty result set', async () => {
		mockFetch([{ results: [], google_status: 'ZERO_RESULTS' }]);
		expect(await geocodeForward('nowhere')).toBeNull();
	});

	it('reverse: passes lat/lng to the proxy', async () => {
		const calls = mockFetch([
			{ lat: 41.5, lng: -81.7, name: 'POI', address: 'Addr' },
		]);
		const result = await geocodeReverse(41.5, -81.7);
		expect(calls[0]).toContain('type=reverse');
		expect(calls[0]).toContain('lat=41.5');
		expect(result.name).toBe('POI');
	});
});

describe('nominatim', () => {
	afterEach(() => {
		delete window.geoTagrData;
		delete global.fetch;
	});

	it('retries without a unit number when the first search is empty', async () => {
		const calls = mockFetch([
			[],
			[
				{
					lat: '41.1',
					lon: '-81.1',
					name: 'Shop',
					display_name: '1 Main St',
				},
			],
		]);
		const result = await geocodeForward('1 Main St Suite 200');
		expect(calls).toHaveLength(2);
		expect(calls[1]).toContain('q=1%20Main%20St');
		expect(calls[1]).not.toContain('Suite');
		expect(result).toEqual({
			lat: 41.1,
			lng: -81.1,
			name: 'Shop',
			address: '1 Main St',
		});
	});

	it('reverse: drops highway names', async () => {
		mockFetch([
			{ name: 'I-90', category: 'highway', display_name: 'I-90, Ohio' },
		]);
		const result = await geocodeReverse(41, -81);
		expect(result).toEqual({
			lat: 41,
			lng: -81,
			name: '',
			address: 'I-90, Ohio',
		});
	});
});

describe('mapbox v6', () => {
	beforeEach(() => {
		window.geoTagrData = {
			geocodingProvider: 'mapbox',
			geocodingApiKey: 'pk.test',
		};
	});

	afterEach(() => {
		delete window.geoTagrData;
		delete global.fetch;
	});

	it('forward: uses the v6 endpoint and reads full_address', async () => {
		const calls = mockFetch([
			{
				features: [
					{
						geometry: { coordinates: [-81.7, 41.5] },
						properties: {
							coordinates: { longitude: -81.7, latitude: 41.5 },
							full_address: '1 Main St, Cleveland, OH',
							feature_type: 'address',
						},
					},
				],
			},
		]);
		const result = await geocodeForward('1 Main St');
		expect(calls[0]).toContain('/search/geocode/v6/forward?');
		expect(calls[0]).toContain('access_token=pk.test');
		expect(result).toEqual({
			lat: 41.5,
			lng: -81.7,
			name: '',
			address: '1 Main St, Cleveland, OH',
		});
	});

	it('reverse: returns null when there are no features', async () => {
		const calls = mockFetch([{ features: [] }]);
		expect(await geocodeReverse(41.5, -81.7)).toBeNull();
		expect(calls[0]).toContain('/search/geocode/v6/reverse?');
		expect(calls[0]).toContain('longitude=-81.7');
	});
});
