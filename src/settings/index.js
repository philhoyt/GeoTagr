/* Settings page — show the API key instructions for the selected provider. */

import './admin.scss';

document.addEventListener('DOMContentLoaded', () => {
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
});
