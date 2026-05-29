const wpPlugin = require( '@wordpress/eslint-plugin' );

module.exports = [
	{
		ignores: [ 'lib/**', 'build/**', 'node_modules/**' ],
	},
	...wpPlugin.configs.recommended,
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
