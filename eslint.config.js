const wpPlugin = require('@wordpress/eslint-plugin');
const jestPlugin = require('eslint-plugin-jest');

/**
 * Scope a config array to a file glob.
 *
 * @param {Array}    configs Flat config array.
 * @param {string[]} files   File globs.
 * @return {Array} Scoped configs.
 */
const scoped = (configs, files) =>
	configs.map((config) => ({ ...config, files }));

module.exports = [
	{
		ignores: ['lib/**', 'build/**', 'node_modules/**', 'artifacts/**'],
	},
	...wpPlugin.configs.recommended,
	// Playwright globals for E2E specs.
	...scoped(wpPlugin.configs['test-playwright'], ['tests/e2e/**/*.js']),
	{
		rules: {
			'import/no-unresolved': 'off',
			'import/no-extraneous-dependencies': [
				'error',
				{ devDependencies: true },
			],
		},
	},
	// The unit suite stays on Jest (wp-scripts test-unit-jest); the default
	// test override in @wordpress/eslint-plugin 27+ targets Vitest instead.
	{
		...jestPlugin.configs['flat/recommended'],
		files: ['src/**/*.test.js'],
	},
];
