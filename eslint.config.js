const wpPlugin = require('@wordpress/eslint-plugin');

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
	// Jest globals for unit tests; Playwright globals for E2E specs.
	...scoped(wpPlugin.configs['test-unit'], ['**/*.test.js']),
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
];
