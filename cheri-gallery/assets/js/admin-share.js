/* Cheri Gallery — Share screen: live email preview and templates. */
(function () {
	'use strict';

	var CFG = window.CG_SHARE;
	var form = document.querySelector('[data-cg-share-form]');
	if (!CFG || !form) { return; }

	var frame = form.querySelector('[data-cg-preview]');
	var subject = form.querySelector('[data-cg-subject]');
	var message = form.querySelector('[data-cg-message]');
	var picker = form.querySelector('[data-cg-template]');
	var timer;

	function fd(extra) {
		var body = new FormData();
		body.append('gallery', CFG.gallery);
		body.append('nonce', CFG.nonce);
		Object.keys(extra).forEach(function (k) { body.append(k, extra[k]); });
		return body;
	}

	function refresh() {
		var data = { action: 'cg_share_preview', message: message.value };
		var pw = form.querySelector('input[type=checkbox][name=include_password]');
		var si = form.querySelector('input[type=checkbox][name=include_signin]');
		var cover = form.querySelector('select[name=cover_photo]');
		if (pw && pw.checked) { data.include_password = 1; }
		if (si && si.checked) { data.include_signin = 1; }
		if (cover) { data.cover_photo = cover.value; }
		fetch(CFG.ajaxUrl, { method: 'POST', body: fd(data), credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (res) { if (res.success) { frame.srcdoc = res.data.html; } });
	}
	function soon() { clearTimeout(timer); timer = setTimeout(refresh, 400); }

	message.addEventListener('input', soon);
	form.addEventListener('change', function (e) { if (e.target.closest('[data-cg-preview-field]') || e.target.hasAttribute('data-cg-preview-field')) { refresh(); } });

	function fill(text) {
		var map = CFG.fill || {};
		return Object.keys(map).reduce(function (out, key) { return out.split(key).join(map[key]); }, text || '');
	}

	if (picker) {
		picker.addEventListener('change', function () {
			var t = CFG.templates[picker.value];
			if (!t) { return; }
			subject.value = fill(t.subject);
			message.value = fill(t.body);
			refresh();
		});
	}

	var save = form.querySelector('[data-cg-template-save]');
	if (save) {
		save.addEventListener('click', function () {
			var name = window.prompt(CFG.i18n.templateName, picker ? picker.options[picker.selectedIndex].text : '');
			if (!name) { return; }
			fetch(CFG.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd({ action: 'cg_template_save', name: name, subject: subject.value, body: message.value }) })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (!res.success) { window.alert(res.data.message); return; }
					CFG.templates = res.data.templates;
					if (picker && !picker.querySelector('option[value="' + res.data.key + '"]')) {
						var o = document.createElement('option');
						o.value = res.data.key;
						o.textContent = name;
						picker.appendChild(o);
					}
					if (picker) { picker.value = res.data.key; }
					save.textContent = CFG.i18n.saved;
				});
		});
	}

	var addTo = form.querySelector('[data-cg-add-to]');
	if (addTo) {
		addTo.addEventListener('click', function () {
			var to = form.querySelector('[data-cg-to]');
			var extra = addTo.getAttribute('data-cg-add-to');
			to.value = to.value.trim() ? to.value.trim().replace(/,?\s*$/, '') + ', ' + extra : extra;
			addTo.hidden = true;
		});
	}

	refresh();
})();
