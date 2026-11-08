/**
 * See Notes: keep the final delete button disabled until the reason's name
 * is typed exactly. The server checks the name again, so this only helps.
 */
(function () {
	'use strict';

	var input = document.getElementById('confirm_name');
	var button = document.getElementById('seenotes_delete_final');

	if (!input || !button) {
		return;
	}

	var name = input.getAttribute('data-seenotes-name');

	function update() {
		button.disabled = input.value.trim() !== name;
	}

	input.addEventListener('input', update);
	update();
})();
