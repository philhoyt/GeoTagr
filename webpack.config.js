const defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = {
	...defaultConfig,
	entry: {
		panel: './src/panel/index.js',
		classic: './src/classic/index.js',
		'location-name': './src/blocks/location-name/index.js',
	},
};
