const { defineConfig } = require('@playwright/test');
const baseConfig = require('@wordpress/scripts/config/playwright.config');

/**
 * E2E runs against the wp-env *tests* site (port 8889 by default). Override
 * with WP_BASE_URL when wp-env was started on other ports, e.g.
 * WP_BASE_URL=http://localhost:8891 npm run test:e2e
 */
module.exports = defineConfig({
	...baseConfig,
	testDir: './tests/e2e',
	use: {
		...baseConfig.use,
		baseURL: process.env.WP_BASE_URL || 'http://localhost:8889',
	},
});
