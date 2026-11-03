/* Cheri Gallery — shared admin bits: copy-to-clipboard buttons. */
(function () {
	'use strict';
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
