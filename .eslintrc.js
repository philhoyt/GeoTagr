module.exports = {
	extends: ['plugin:@wordpress/eslint-plugin/recommended'],
	settings: {
		// Tell the import plugin that @wordpress/* packages are WordPress externals.
		'import/resolver': {
			node: {
				moduleDirectory: ['node_modules'],
			},
		},
		'import/external-module-folders': [
			'node_modules',
			'node_modules/@wordpress',
		],
	},
	rules: {
		// @wordpress/* packages are externals provided by WordPress — not npm dependencies.
		'import/no-unresolved': 'off',
		'import/no-extraneous-dependencies': [
			'error',
			{ devDependencies: true },
		],
	},
};
