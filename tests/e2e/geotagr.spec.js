/**
 * E2E: block editor panel, Location Name block, and asset gating.
 */

const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

/**
 * Expand the GeoTagr document panel if it is collapsed.
 *
 * @param {import('@playwright/test').Page} page
 */
async function openGeoTagrPanel(page) {
	const toggle = page
		.getByRole('region', { name: 'Editor settings' })
		.getByRole('button', { name: 'GeoTagr' });
	if ((await toggle.getAttribute('aria-expanded')) === 'false') {
		await toggle.click();
	}
}

test.describe('GeoTagr', () => {
	test.beforeAll(async ({ requestUtils }) => {
		// The PHPUnit bootstrap reinstalls the tests site with no active
		// plugin or theme, so set both before each E2E run.
		await requestUtils.activatePlugin('geotagr');
		await requestUtils.activateTheme('twentytwentyfive');
	});

	test.afterAll(async ({ requestUtils }) => {
		await requestUtils.deleteAllPosts();
	});

	test('panel saves location meta and the Location Name block renders it', async ({
		admin,
		editor,
		page,
		requestUtils,
	}) => {
		await admin.createNewPost({ title: 'Geotagged post' });
		await editor.openDocumentSettingsSidebar();
		await openGeoTagrPanel(page);

		const sidebar = page.getByRole('region', { name: 'Editor settings' });
		await sidebar
			.getByRole('spinbutton', { name: 'Latitude' })
			.fill('41.4993');
		await sidebar
			.getByRole('spinbutton', { name: 'Longitude' })
			.fill('-81.6944');
		await sidebar
			.getByRole('textbox', { name: 'Place name' })
			.fill('West Side Market');

		await editor.insertBlock({ name: 'geotagr/location-name' });
		await expect(
			editor.canvas.locator('[data-type="geotagr/location-name"]')
		).toHaveText('West Side Market');

		const postId = await editor.publishPost();

		const saved = await requestUtils.rest({
			path: `/wp/v2/posts/${postId}`,
			params: { context: 'edit' },
		});
		expect(saved.meta._geo_tagr_lat).toBeCloseTo(41.4993, 4);
		expect(saved.meta._geo_tagr_lng).toBeCloseTo(-81.6944, 4);
		expect(saved.meta._geo_tagr_place).toBe('West Side Market');

		await page.goto(`/?p=${postId}`);
		await expect(
			page.locator('.wp-block-geotagr-location-name')
		).toHaveText('West Side Market');
	});

	test('clearing a coordinate saves and removes the key', async ({
		admin,
		editor,
		page,
		requestUtils,
	}) => {
		const post = await requestUtils.createPost({
			title: 'Clear me',
			status: 'draft',
			meta: {
				_geo_tagr_lat: 10.5,
				_geo_tagr_lng: 20.5,
				_geo_tagr_place: 'Somewhere',
			},
		});

		await admin.editPost(post.id);
		await editor.openDocumentSettingsSidebar();
		await openGeoTagrPanel(page);

		const sidebar = page.getByRole('region', { name: 'Editor settings' });
		const latitude = sidebar.getByRole('spinbutton', { name: 'Latitude' });
		await expect(latitude).toHaveValue('10.5');
		await latitude.fill('');

		await editor.saveDraft();

		const saved = await requestUtils.rest({
			path: `/wp/v2/posts/${post.id}`,
			params: { context: 'edit' },
		});
		// Core returns 0 for an unset number meta.
		expect(saved.meta._geo_tagr_lat).toBe(0);
		expect(saved.meta._geo_tagr_lng).toBeCloseTo(20.5, 4);
	});

	test('Location Name block renders nothing when the post has no place', async ({
		admin,
		editor,
		page,
	}) => {
		await admin.createNewPost({ title: 'Untagged post' });
		await editor.insertBlock({ name: 'geotagr/location-name' });
		await expect(
			editor.canvas.locator('[data-type="geotagr/location-name"]')
		).toHaveText('No location set');

		const postId = await editor.publishPost();

		await page.goto(`/?p=${postId}`);
		await expect(
			page.locator('.wp-block-geotagr-location-name')
		).toHaveCount(0);
	});

	test('panel is absent on post types GeoTagr is not enabled for', async ({
		admin,
		editor,
		page,
	}) => {
		await admin.createNewPost({ postType: 'page', title: 'A page' });
		await editor.openDocumentSettingsSidebar();

		const sidebar = page.getByRole('region', { name: 'Editor settings' });
		await expect(
			sidebar.getByRole('button', { name: 'GeoTagr' })
		).toHaveCount(0);
		await expect(page.locator('script[src*="build/panel.js"]')).toHaveCount(
			0
		);
	});

	test('classic metabox does not render inside the block editor', async ({
		admin,
		page,
	}) => {
		await admin.createNewPost({ title: 'No duplicate UI' });
		await expect(page.locator('#geo-tagr')).toHaveCount(0);
		await expect(
			page.locator('script[src*="build/classic.js"]')
		).toHaveCount(0);
	});
});
