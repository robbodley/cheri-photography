/* Cheri Gallery — gallery edit screen: photo uploads, sets, per-photo switches, bulk actions. */
(function () {
	'use strict';

	var CFG = window.CG_ADMIN;
	if (!CFG) { return; }
	var T = CFG.i18n;

	function $(sel, root) { return (root || document).querySelector(sel); }
	function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
	function fmt(s) { var a = arguments, i = 0; return s.replace(/%(\d\$)?[ds]/g, function (m, pos) { return a[pos ? parseInt(pos, 10) : ++i]; }); }

	/* Visibility hints */
	$$('[data-cg-vis]').forEach(function (r) {
		r.addEventListener('change', function () {
			$$('[data-cg-vis-hint]').forEach(function (h) { h.hidden = h.getAttribute('data-cg-vis-hint') !== r.value; });
		});
	});

	var pm = $('[data-cg-pm]');
	if (!pm) { return; }
	var grid = $('[data-cg-grid]', pm);
	var status = $('[data-cg-status]', pm);
	var fileInput = $('[data-cg-file]', pm);
	var drop = $('[data-cg-drop]', pm);
	var uploading = false;

	function post(data, file) {
		var body = new FormData();
		body.append('action', 'cg_admin_photos');
		body.append('gallery', CFG.gallery);
		body.append('nonce', CFG.nonce);
		Object.keys(data).forEach(function (k) {
			var v = data[k];
			if (Array.isArray(v)) { v.forEach(function (x) { body.append(k + '[]', x); }); } else { body.append(k, v); }
		});
		if (file) { body.append('file', file); }
		return fetch(CFG.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (r) { return r.json().catch(function () { throw new Error('Server error (' + r.status + ')'); }); })
			.then(function (res) {
				if (!res.success) { throw new Error((res.data && res.data.message) || 'Error'); }
				return res.data;
			});
	}

	function fromHTML(html) {
		var t = document.createElement('template');
		t.innerHTML = html.trim();
		return t.content.firstElementChild;
	}

	function replaceTiles(tiles) {
		Object.keys(tiles || {}).forEach(function (id) {
			var old = $('[data-cg-tile="' + id + '"]', grid);
			var fresh = fromHTML(tiles[id]);
			if (old) {
				var wasChecked = $('[data-cg-select]', old).checked;
				old.replaceWith(fresh);
				$('[data-cg-select]', fresh).checked = wasChecked;
			}
		});
		applySetFilter();
		updateBulk();
	}

	/* ---------------- Sets ---------------- */
	var currentSet = ($('[data-cg-set][aria-current="true"]', pm) || {}).getAttribute ? $('[data-cg-set][aria-current="true"]', pm).getAttribute('data-cg-set') : '';

	function setNames() { return $$('[data-cg-set]', pm).map(function (t) { return t.getAttribute('data-cg-set'); }); }

	function applySetFilter() {
		var counts = {};
		$$('[data-cg-tile]', grid).forEach(function (tile) {
			var s = tile.getAttribute('data-set');
			counts[s] = (counts[s] || 0) + 1;
			tile.hidden = s !== currentSet;
		});
		$$('[data-cg-set]', pm).forEach(function (t) {
			var n = $('[data-cg-set-count]', t);
			if (n) { n.textContent = counts[t.getAttribute('data-cg-set')] || 0; }
			t.setAttribute('aria-current', t.getAttribute('data-cg-set') === currentSet ? 'true' : 'false');
		});
	}

	pm.addEventListener('click', function (e) {
		var tab = e.target.closest('[data-cg-set]');
		if (tab) { e.preventDefault(); currentSet = tab.getAttribute('data-cg-set'); applySetFilter(); }
	});

	function saveSets(sets, renames) {
		return post({ op: 'sets', sets: sets, renames: JSON.stringify(renames || {}) }).then(function () { window.location.reload(); })
			.catch(function (err) { window.alert(err.message); });
	}
	$('[data-cg-set-add]', pm).addEventListener('click', function (e) {
		e.preventDefault();
		var name = (window.prompt(T.newSet, '') || '').trim();
		if (name) { saveSets(setNames().concat([name])); }
	});
	$('[data-cg-set-rename]', pm).addEventListener('click', function () {
		var name = (window.prompt(T.renameSet, currentSet) || '').trim();
		if (!name || name === currentSet) { return; }
		var renames = {}; renames[currentSet] = name;
		saveSets(setNames().map(function (s) { return s === currentSet ? name : s; }), renames);
	});
	$('[data-cg-set-delete]', pm).addEventListener('click', function () {
		var sets = setNames();
		if (sets.length < 2 || !window.confirm(T.deleteSet)) { return; }
		saveSets(sets.filter(function (s) { return s !== currentSet; }));
	});

	/* ---------------- Upload ---------------- */
	function uploadFiles(files) {
		files = Array.prototype.slice.call(files).filter(function (f) { return /^image\/(jpeg|png|webp)$/.test(f.type); });
		if (!files.length) { return; }
		uploading = true;
		var failed = [];
		var i = 0;
		var set = currentSet;
		function next() {
			if (i >= files.length) {
				uploading = false;
				status.textContent = failed.length ? fmt(T.failed, failed.length) + ' ' + failed.join('; ') : T.done;
				applySetFilter();
				return;
			}
			var f = files[i++];
			status.textContent = fmt(T.uploading, i, files.length);
			if (CFG.maxBytes && f.size > CFG.maxBytes) {
				failed.push(f.name + ' (' + fmt(T.tooBig, Math.round(CFG.maxBytes / 1048576) + ' MB') + ')');
				next();
				return;
			}
			post({ op: 'upload', set: set }, f).then(function (data) {
				grid.appendChild(fromHTML(data.html));
				applySetFilter();
			}).catch(function (err) {
				failed.push(f.name + ' (' + err.message + ')');
			}).then(next);
		}
		next();
	}
	fileInput.addEventListener('change', function () { uploadFiles(fileInput.files); fileInput.value = ''; });
	['dragenter', 'dragover'].forEach(function (ev) {
		drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('is-over'); });
	});
	['dragleave', 'drop'].forEach(function (ev) {
		drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('is-over'); });
	});
	drop.addEventListener('drop', function (e) { uploadFiles(e.dataTransfer.files); });
	window.addEventListener('beforeunload', function (e) {
		if (uploading) { e.preventDefault(); e.returnValue = T.leaveUpload; return T.leaveUpload; }
	});

	/* ---------------- Per-photo buttons ---------------- */
	grid.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-cg-op]');
		if (!btn) { return; }
		e.preventDefault();
		var tile = btn.closest('[data-cg-tile]');
		var id = tile.getAttribute('data-cg-tile');
		var op = btn.getAttribute('data-cg-op');
		btn.disabled = true;
		var req;
		if (op === 'cover') {
			req = post({ op: 'cover', ids: [id] }).then(function (d) {
				var input = $('[data-cg-cover-input]', pm);
				if (input) { input.value = id; }
				replaceTiles(d.tiles);
			});
		} else {
			var on = btn.getAttribute('aria-pressed') !== 'true';
			req = post({ op: 'set_flag', field: op, value: on ? 1 : 0, ids: [id] }).then(function (d) { replaceTiles(d.tiles); });
		}
		req.catch(function (err) { window.alert(err.message); btn.disabled = false; });
	});

	/* ---------------- Selection and bulk actions ---------------- */
	var bulk = $('[data-cg-bulk]', pm);
	function selectedIds() {
		return $$('[data-cg-select]:checked', grid).map(function (c) { return c.closest('[data-cg-tile]').getAttribute('data-cg-tile'); });
	}
	function updateBulk() {
		var ids = selectedIds();
		bulk.hidden = !ids.length;
		$('[data-cg-bulk-count]', bulk).textContent = fmt(T.selected, ids.length);
	}
	grid.addEventListener('change', function (e) { if (e.target.matches('[data-cg-select]')) { updateBulk(); } });

	bulk.addEventListener('click', function (e) {
		var b = e.target.closest('[data-cg-bulk-op]');
		if (e.target.closest('[data-cg-bulk-clear]')) {
			$$('[data-cg-select]', grid).forEach(function (c) { c.checked = false; });
			updateBulk();
			return;
		}
		if (!b) { return; }
		var ids = selectedIds();
		var op = b.getAttribute('data-cg-bulk-op');
		var req;
		if (op === 'delete') {
			if (!window.confirm(T.confirmDel)) { return; }
			req = post({ op: 'delete', ids: ids }).then(function () {
				ids.forEach(function (id) { var t = $('[data-cg-tile="' + id + '"]', grid); if (t) { t.remove(); } });
				applySetFilter();
				updateBulk();
			});
		} else {
			var map = { hide: ['hidden', 1], show: ['hidden', 0], block: ['no_download', 1], allow: ['no_download', 0] };
			req = post({ op: 'set_flag', field: map[op][0], value: map[op][1], ids: ids }).then(function (d) { replaceTiles(d.tiles); });
		}
		req.catch(function (err) { window.alert(err.message); });
	});
	$('[data-cg-bulk-move]', bulk).addEventListener('change', function (e) {
		var set = e.target.value;
		if (!set) { return; }
		post({ op: 'move', set: set, ids: selectedIds() }).then(function (d) { replaceTiles(d.tiles); e.target.value = ''; })
			.catch(function (err) { window.alert(err.message); });
	});

	applySetFilter();
})();
