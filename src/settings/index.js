/* Settings page — provider instructions and the API key show/hide toggle. */

import { __ } from '@wordpress/i18n';
import { speak } from '@wordpress/a11y';

import './admin.scss';

/**
 * Show the API key instructions for the selected provider.
 */
function initProviderInstructions() {
	const select = document.getElementById('geotagr-provider');
	const panels = document.querySelectorAll('.geotagr-key-instructions');

	if (!select || !panels.length) {
		return;
	}

	function update() {
		panels.forEach((el) => {
			el.hidden = true;
		});
		const target = document.getElementById(
			`geotagr-key-instructions-${select.value}`
		);
		if (target) {
			target.hidden = false;
		}
	}

	select.addEventListener('change', update);
	update();
}

/**
 * Toggle the API key field between masked and plain text.
 */
function initApiKeyToggle() {
	const input = document.getElementById('geotagr-api-key');
	const button = document.querySelector('.geotagr-api-key-toggle');

	if (!input || !button) {
		return;
	}

	button.addEventListener('click', () => {
		const reveal = input.type === 'password';

		input.type = reveal ? 'text' : 'password';
		button.textContent = reveal
			? __('Hide', 'geotagr')
			: __('Show', 'geotagr');
		button.setAttribute(
			'aria-label',
			reveal
				? __('Hide API key', 'geotagr')
				: __('Show API key', 'geotagr')
		);
		speak(
			reveal
				? __('API key shown.', 'geotagr')
				: __('API key hidden.', 'geotagr')
		);
	});
}

document.addEventListener('DOMContentLoaded', () => {
	initProviderInstructions();
	initApiKeyToggle();
});
