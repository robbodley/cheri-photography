/* Cheri Gallery — shared admin bits: copy-to-clipboard buttons, the session types list. */
(function () {
	'use strict';

	// Settings → Session types: "+ Add another" copies the empty row; ↑ ↓ reorder. Field names
	// are renumbered so the saved order matches what's on screen.
	var types = document.querySelector('[data-cg-types]');
	function renumber() {
		Array.prototype.forEach.call(types.querySelectorAll('[data-cg-type-row]'), function (tr, i) {
			Array.prototype.forEach.call(tr.querySelectorAll('[name^="types["]'), function (f) {
				f.name = f.name.replace(/^types\[\d+\]/, 'types[' + i + ']');
			});
		});
	}
	if (types) {
		var rowsAtLoad = types.querySelectorAll('[data-cg-type-row]');
		var blank = rowsAtLoad[rowsAtLoad.length - 1].cloneNode(true); // the empty "new" row
		document.addEventListener('click', function (e) {
			var add = e.target.closest('[data-cg-type-add]');
			var move = e.target.closest('[data-cg-type-move]');
			if (add) {
				var copy = blank.cloneNode(true);
				types.appendChild(copy);
				renumber();
				copy.querySelector('input').focus();
			} else if (move) {
				var tr = move.closest('[data-cg-type-row]');
				if (move.getAttribute('data-cg-type-move') === '-1' && tr.previousElementSibling) {
					types.insertBefore(tr, tr.previousElementSibling);
				} else if (move.getAttribute('data-cg-type-move') === '1' && tr.nextElementSibling) {
					types.insertBefore(tr.nextElementSibling, tr);
				}
				renumber();
				move.focus();
			}
		});
	}

	var copied = (window.CG_ADMIN_COMMON && window.CG_ADMIN_COMMON.copied) || 'Copied';
	document.addEventListener('click', function (e) {
		var b = e.target.closest('[data-cg-copy]');
		if (!b) { return; }
		e.preventDefault();
		var text = b.getAttribute('data-cg-copy');
		var done = function () {
			var o = b.textContent;
			b.textContent = copied;
			setTimeout(function () { b.textContent = o; }, 1500);
		};
		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(text).then(done, function () { window.prompt('', text); });
		} else {
			window.prompt('', text);
		}
	});
})();
