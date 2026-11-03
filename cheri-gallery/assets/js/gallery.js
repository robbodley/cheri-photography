/* Cheri Gallery — gallery page: set tabs, lightbox, slideshow, share, favourites. No dependencies. */
(function () {
	'use strict';

	var CFG = window.CG_GALLERY;
	if (!CFG) { return; }
	var T = CFG.i18n || {};

	function $(sel, root) { return (root || document).querySelector(sel); }
	function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
	function el(tag, attrs, children) {
		var n = document.createElement(tag);
		Object.keys(attrs || {}).forEach(function (k) {
			if (k === 'text') { n.textContent = attrs[k]; }
			else if (k === 'html') { n.innerHTML = attrs[k]; } // only used with our own icon SVG strings
			else if (k.indexOf('on') === 0) { n.addEventListener(k.slice(2), attrs[k]); }
			else { n.setAttribute(k, attrs[k]); }
		});
		(children || []).forEach(function (c) { if (c) { n.appendChild(c); } });
		return n;
	}

	var ICON = {
		close: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>',
		prev: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>',
		next: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>',
		heart: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z"/></svg>',
		download: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v11M7 10l5 5 5-5M5 20h14"/></svg>',
		play: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5l11 7-11 7z"/></svg>',
		pause: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14M16 5v14"/></svg>'
	};

	/* ---------------------------------------------------------------- */
	/* Toast                                                             */
	/* ---------------------------------------------------------------- */
	var toastEl = $('[data-cg-toast]');
	var toastTimer;
	function toast(msg) {
		if (!toastEl) { return; }
		toastEl.textContent = msg;
		toastEl.hidden = false;
		clearTimeout(toastTimer);
		toastTimer = setTimeout(function () { toastEl.hidden = true; }, 3200);
	}

	/* ---------------------------------------------------------------- */
	/* Set tabs                                                          */
	/* ---------------------------------------------------------------- */
	var tabs = $$('[data-cg-set]');
	var panels = $$('[data-cg-set-panel]');
	function showSet(i, scroll) {
		tabs.forEach(function (t) { t.setAttribute('aria-current', t.getAttribute('data-cg-set') === String(i) ? 'true' : 'false'); });
		panels.forEach(function (p) { p.hidden = p.getAttribute('data-cg-set-panel') !== String(i); });
		if (scroll) {
			var head = $('#cg-photos');
			if (head) { window.scrollTo({ top: head.getBoundingClientRect().top + window.pageYOffset, behavior: 'smooth' }); }
		}
	}
	if (tabs.length > 1) {
		document.body.classList.add('js-cg-tabs');
		tabs.forEach(function (t) {
			t.addEventListener('click', function (e) {
				e.preventDefault();
				showSet(t.getAttribute('data-cg-set'), true);
			});
		});
		var start = (location.hash.match(/^#set-(\d+)$/) || [])[1] || '0';
		showSet(start, false);
	}

	function visibleItems() {
		return $$('.ch-grid__item').filter(function (f) {
			var panel = f.closest('[data-cg-set-panel]');
			return (!panel || !panel.hidden) && !f.hidden;
		});
	}

	/* ---------------------------------------------------------------- */
	/* Protection: discourage right-click / drag saving                  */
	/* ---------------------------------------------------------------- */
	if (CFG.protect) {
		document.addEventListener('contextmenu', function (e) { if (e.target.tagName === 'IMG') { e.preventDefault(); } });
		document.addEventListener('dragstart', function (e) { if (e.target.tagName === 'IMG') { e.preventDefault(); } });
	}

	/* ---------------------------------------------------------------- */
	/* Share                                                             */
	/* ---------------------------------------------------------------- */
	var shareBtn = $('[data-cg-share]');
	if (shareBtn) {
		shareBtn.addEventListener('click', function () {
			var url = CFG.shareUrl;
			if (navigator.share) {
				navigator.share({ title: document.title, url: url }).catch(function () {});
			} else if (navigator.clipboard) {
				navigator.clipboard.writeText(url).then(function () { toast(T.linkCopied); });
			} else {
				window.prompt('', url);
			}
		});
	}

	/* ---------------------------------------------------------------- */
	/* Favourites state                                                  */
	/* ---------------------------------------------------------------- */
	var state = CFG.state || { lists: [], current: 0 };
	function currentList() {
		var id = state.current || (state.lists[0] && state.lists[0].id) || 0;
		for (var i = 0; i < state.lists.length; i++) { if (state.lists[i].id === id) { return state.lists[i]; } }
		return null;
	}
	function inCurrent(photoId) {
		var l = currentList();
		return !!(l && l.photos.indexOf(photoId) !== -1);
	}

	function api(op, data) {
		var body = new FormData();
		body.append('action', 'cg_fav');
		body.append('op', op);
		body.append('gallery', CFG.id);
		body.append('nonce', CFG.nonce);
		Object.keys(data || {}).forEach(function (k) { body.append(k, data[k]); });
		return fetch(CFG.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (!res || !res.success) { throw new Error((res && res.data && res.data.message) || T.error); }
				var keep = state.current;
				state = res.data;
				if (!state.current) { state.current = keep && state.lists.some(function (l) { return l.id === keep; }) ? keep : 0; }
				render();
				return state;
			})
			.catch(function (err) { toast(err.message || T.error); throw err; });
	}

	function render() {
		$$('.ch-grid__item').forEach(function (f) {
			var id = parseInt(f.getAttribute('data-cg-photo'), 10);
			var on = inCurrent(id);
			f.classList.toggle('is-faved', on);
			var h = $('[data-cg-heart]', f);
			if (h) { h.setAttribute('aria-pressed', on ? 'true' : 'false'); }
		});
		var count = $('[data-cg-fav-count]');
		var l = currentList();
		if (count) {
			count.hidden = !(l && l.photos.length);
			count.textContent = l ? l.photos.length : '';
		}
		if (lb && !lb.hidden) { lbSync(); }
		if (drawer && !drawer.hidden) { drawerRender(); }
		applyFilter();
	}

	function toggleHeart(photoId) {
		var l = currentList();
		return api('toggle', { list: l ? l.id : 0, photo: photoId });
	}

	if (CFG.canFavourite) {
		$$('[data-cg-heart]').forEach(function (b) {
			b.addEventListener('click', function (e) {
				e.preventDefault();
				var id = parseInt(b.closest('[data-cg-photo]').getAttribute('data-cg-photo'), 10);
				toggleHeart(id);
			});
		});
	}

	/* ---------------------------------------------------------------- */
	/* Favourites drawer                                                 */
	/* ---------------------------------------------------------------- */
	var drawer, drawerBack, filterOn = false;

	function plural(n) { return n + ' ' + (n === 1 ? T.photo : T.photos); }

	function thumbsFor(list) {
		var wrap = el('span', { 'class': 'ch-list__thumbs' });
		list.photos.slice(0, 4).forEach(function (pid) {
			var fig = $('[data-cg-photo="' + pid + '"] img');
			if (fig) { wrap.appendChild(el('img', { src: fig.currentSrc || fig.src, alt: '' })); }
		});
		return wrap;
	}

	function drawerRender() {
		var body = $('.ch-drawer__body', drawer);
		var foot = $('.ch-drawer__foot', drawer);
		body.textContent = '';
		foot.textContent = '';
		var cur = currentList();

		body.appendChild(el('p', { 'class': 'ch-drawer__intro', text: T.intro }));
		var lists = el('div', { 'class': 'ch-lists' });
		state.lists.forEach(function (l) {
			var meta = plural(l.photos.length) + ' · ' + (l.status === 'sent' ? T.sent + ' ' + l.sent : T.notSent);
			lists.appendChild(el('button', {
				type: 'button',
				'class': 'ch-list',
				'aria-current': cur && cur.id === l.id ? 'true' : 'false',
				onclick: function () { state.current = l.id; render(); }
			}, [
				thumbsFor(l),
				el('span', { 'class': 'ch-list__main' }, [
					el('span', { 'class': 'ch-list__name', text: l.name }),
					el('span', { 'class': 'ch-list__meta', text: meta })
				]),
				el('span', { 'class': 'ch-heart', html: ICON.heart })
			]));
		});
		body.appendChild(lists);

		body.appendChild(el('div', { 'class': 'ch-drawer__links' }, [
			el('button', { type: 'button', 'class': 'ch-textlink', text: '+ ' + (T.new), onclick: function () {
				var name = window.prompt(T.newList, '');
				if (name) { api('create', { name: name }).then(function (s) { state.current = s.current; render(); }); }
			} }),
			cur ? el('button', { type: 'button', 'class': 'ch-textlink', text: T.renameLink, onclick: function () {
				var name = window.prompt(T.rename, cur.name);
				if (name) { api('update', { list: cur.id, name: name }); }
			} }) : null,
			cur ? el('button', { type: 'button', 'class': 'ch-textlink', text: T.delete, onclick: function () {
				if (window.confirm(T.confirmDel)) { api('delete', { list: cur.id }).then(function () { state.current = 0; render(); }); }
			} }) : null
		]));

		if (cur) {
			var filter = el('label', { 'class': 'ch-toggle' }, [
				el('span', { 'class': 'ch-toggle__text' }, [ el('span', { 'class': 'ch-toggle__label', text: T.filter }) ]),
				el('input', { type: 'checkbox' }),
				el('span', { 'class': 'ch-toggle__track' })
			]);
			var box = $('input', filter);
			box.checked = filterOn;
			box.addEventListener('change', function () { filterOn = box.checked; applyFilter(); });
			body.appendChild(filter);

			var note = el('textarea', { 'aria-label': T.note, placeholder: T.notePh });
			note.value = cur.note || '';
			note.addEventListener('change', function () { api('update', { list: cur.id, note: note.value }); });
			body.appendChild(el('label', { 'class': 'ch-field' }, [ el('span', { 'class': 'ch-field__label', text: T.note }), note ]));

			var send = el('button', { type: 'button', 'class': 'ch-btn', text: T.send, onclick: function () {
				send.disabled = true;
				var pending = note.value !== (cur.note || '') ? api('update', { list: cur.id, note: note.value }) : Promise.resolve();
				pending.then(function () { return api('send', { list: cur.id }); })
					.then(function () { toast(T.thanks); })
					.catch(function () {})
					.then(function () { send.disabled = false; });
			} });
			if (!cur.photos.length) { send.disabled = true; }
			foot.appendChild(send);
		}
	}

	function applyFilter() {
		var l = currentList();
		$$('.ch-grid__item').forEach(function (f) {
			var id = parseInt(f.getAttribute('data-cg-photo'), 10);
			f.hidden = !!(filterOn && l && l.photos.indexOf(id) === -1);
		});
	}

	function openDrawer() {
		if (!drawer) {
			drawerBack = el('div', { 'class': 'ch-drawer-back', hidden: '', onclick: closeDrawer });
			drawer = el('aside', { 'class': 'ch-drawer', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'cg-drawer-title', hidden: '' }, [
				el('div', { 'class': 'ch-drawer__head' }, [
					el('h2', { 'class': 'ch-drawer__title', id: 'cg-drawer-title', text: T.favs }),
					el('button', { type: 'button', 'class': 'ch-btn ch-btn--icon', 'aria-label': T.close, html: ICON.close, onclick: closeDrawer })
				]),
				el('div', { 'class': 'ch-drawer__body' }),
				el('div', { 'class': 'ch-drawer__foot' })
			]);
			document.body.appendChild(drawerBack);
			document.body.appendChild(drawer);
		}
		drawerRender();
		drawerBack.hidden = false;
		drawer.hidden = false;
		var focus = $('button, textarea', drawer);
		if (focus) { focus.focus(); }
	}
	function closeDrawer() {
		if (!drawer) { return; }
		drawer.hidden = true;
		drawerBack.hidden = true;
		var btn = $('[data-cg-open-favs]');
		if (btn) { btn.focus(); }
	}
	var favBtn = $('[data-cg-open-favs]');
	if (favBtn) { favBtn.addEventListener('click', openDrawer); }
	if (CFG.canFavourite) {
		var want = parseInt((location.search.match(/[?&]favs=(\d+)/) || [])[1] || '0', 10);
		if (want && state.lists.some(function (l) { return l.id === want; })) {
			state.current = want;
			openDrawer();
		}
	}

	/* ---------------------------------------------------------------- */
	/* Lightbox and slideshow                                            */
	/* ---------------------------------------------------------------- */
	var lb, lbImg, lbCount, lbName, lbHeart, lbDl, lbPlay, lbItems = [], lbIndex = 0, lbTimer = null, lastFocus;

	function buildLightbox() {
		lbImg = el('img', { 'class': 'ch-lb__img', alt: '' });
		lbCount = el('span', { 'class': 'ch-lb__count' });
		lbName = el('div', { 'class': 'ch-lb__name' });
		lbHeart = CFG.canFavourite ? el('button', { type: 'button', 'class': 'ch-lb__btn', 'aria-label': T.favourite, 'aria-pressed': 'false', html: ICON.heart, onclick: function () {
			toggleHeart(parseInt(lbItems[lbIndex].getAttribute('data-cg-photo'), 10));
		} }) : null;
		lbDl = el('a', { 'class': 'ch-lb__btn', 'aria-label': T.download, html: ICON.download });
		lbPlay = el('button', { type: 'button', 'class': 'ch-lb__btn', 'aria-label': T.slideshow, html: ICON.play, onclick: function () { lbTimer ? stopShow() : startShow(); } });

		var stage = el('div', { 'class': 'ch-lb__stage' }, [
			lbImg,
			el('button', { type: 'button', 'class': 'ch-lb__btn ch-lb__nav ch-lb__nav--prev', 'aria-label': T.previous, html: ICON.prev, onclick: function () { lbGo(-1); } }),
			el('button', { type: 'button', 'class': 'ch-lb__btn ch-lb__nav ch-lb__nav--next', 'aria-label': T.next, html: ICON.next, onclick: function () { lbGo(1); } })
		]);

		lb = el('div', { 'class': 'ch-lb', role: 'dialog', 'aria-modal': 'true', 'aria-label': document.title, hidden: '' }, [
			el('div', { 'class': 'ch-lb__bar' }, [
				lbCount,
				el('div', { 'class': 'ch-lb__tools' }, [
					lbHeart, lbDl, lbPlay,
					el('button', { type: 'button', 'class': 'ch-lb__btn', 'aria-label': T.close, html: ICON.close, onclick: closeLightbox })
				])
			]),
			stage,
			lbName
		]);
		document.body.appendChild(lb);

		// Swipe on phones.
		var x0 = null;
		stage.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
		stage.addEventListener('touchend', function (e) {
			if (x0 === null) { return; }
			var dx = e.changedTouches[0].clientX - x0;
			if (Math.abs(dx) > 40) { lbGo(dx < 0 ? 1 : -1); }
			x0 = null;
		});
		stage.addEventListener('click', function (e) { if (e.target === stage) { closeLightbox(); } });
	}

	function lbSync() {
		var f = lbItems[lbIndex];
		if (!f) { return; }
		var id = parseInt(f.getAttribute('data-cg-photo'), 10);
		lbImg.src = f.getAttribute('data-web');
		lbCount.textContent = (lbIndex + 1) + ' ' + T.of + ' ' + lbItems.length;
		lbName.textContent = f.getAttribute('data-name') || '';
		var dl = f.getAttribute('data-dl');
		lbDl.hidden = !dl;
		if (dl) { lbDl.href = dl; }
		if (lbHeart) { lbHeart.setAttribute('aria-pressed', inCurrent(id) ? 'true' : 'false'); }
		// Preload the next photo.
		var next = lbItems[(lbIndex + 1) % lbItems.length];
		if (next) { (new Image()).src = next.getAttribute('data-web'); }
	}

	function openLightbox(index) {
		if (!lb) { buildLightbox(); }
		lbItems = visibleItems();
		if (!lbItems.length) { return; }
		lbIndex = Math.max(0, Math.min(index, lbItems.length - 1));
		lastFocus = document.activeElement;
		lb.hidden = false;
		document.documentElement.style.overflow = 'hidden';
		lbSync();
		lbPlay.focus();
	}
	function closeLightbox() {
		stopShow();
		lb.hidden = true;
		document.documentElement.style.overflow = '';
		if (lastFocus) { lastFocus.focus(); }
	}
	function lbGo(step) {
		lbIndex = (lbIndex + step + lbItems.length) % lbItems.length;
		lbSync();
	}
	function startShow() {
		lbPlay.innerHTML = ICON.pause;
		lbTimer = setInterval(function () { lbGo(1); }, 4000);
	}
	function stopShow() {
		if (lbTimer) { clearInterval(lbTimer); }
		lbTimer = null;
		if (lbPlay) { lbPlay.innerHTML = ICON.play; }
	}

	$$('.ch-grid__open').forEach(function (a) {
		a.addEventListener('click', function (e) {
			e.preventDefault();
			openLightbox(visibleItems().indexOf(a.closest('.ch-grid__item')));
		});
	});
	var showBtn = $('[data-cg-slideshow]');
	if (showBtn) {
		showBtn.addEventListener('click', function () { openLightbox(0); startShow(); });
	}

	document.addEventListener('keydown', function (e) {
		if (lb && !lb.hidden) {
			if (e.key === 'Escape') { closeLightbox(); }
			else if (e.key === 'ArrowRight') { lbGo(1); }
			else if (e.key === 'ArrowLeft') { lbGo(-1); }
			return;
		}
		if (drawer && !drawer.hidden && e.key === 'Escape') { closeDrawer(); }
	});

	render();
})();
