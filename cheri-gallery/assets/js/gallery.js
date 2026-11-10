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
	// Where the lightbox and drawer live: the gallery page's wrapper, or (for embeds on other pages)
	// a styled container added to the end of the page.
	var root = document.getElementById('cg-root');
	if (!root) {
		root = document.createElement('div');
		root.className = 'cg-page cg-overlay';
		document.body.appendChild(root);
	}

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
		if (grids) { grids.forEach(evenGrid); }
		if (scroll) {
			var head = $('#cg-photos');
			if (head) { window.scrollTo({ top: head.getBoundingClientRect().top + window.pageYOffset, behavior: 'smooth' }); }
		}
	}
	if (tabs.length > 1) {
		root.classList.add('js-cg-tabs');
		tabs.forEach(function (t) {
			t.addEventListener('click', function (e) {
				e.preventDefault();
				showSet(t.getAttribute('data-cg-set'), true);
			});
		});
		var start = (location.hash.match(/^#set-(\d+)$/) || [])[1] || '0';
		showSet(start, false);
	}

	/* ---------------------------------------------------------------- */
	/* Photo grid: masonry that reads left to right                      */
	/* ---------------------------------------------------------------- */
	// The CSS lays photos out in columns (top to bottom, then the next column). Here we build
	// the same columns ourselves and hand each photo, in order, to the shortest column, so
	// photos 1-4 sit across the first row and the order reads left to right. The number of
	// columns and the gap still come from the CSS, so the look and breakpoints are unchanged.
	var grids = $$('.ch-grid');
	grids.forEach(function (g) {
		$$('.ch-grid__item', g).forEach(function (f, i) { f.setAttribute('data-cg-i', i); });
	});
	function byOrder(a, b) { return (+a.getAttribute('data-cg-i')) - (+b.getAttribute('data-cg-i')); }
	function itemsInOrder(scope) { return $$('.ch-grid__item', scope).sort(byOrder); }
	function ratio(f) {
		var img = f.querySelector('img');
		var w = img && parseFloat(img.getAttribute('width'));
		var h = img && parseFloat(img.getAttribute('height'));
		return w > 0 && h > 0 ? h / w : 1;
	}
	function layoutGrid(g, force) {
		var n = parseInt(window.getComputedStyle(g).columnCount, 10) || 1;
		if (!force && g._cgCols === n) { return; }
		g._cgCols = n;
		var items = itemsInOrder(g);
		var cols = [], heights = [];
		for (var c = 0; c < n; c++) {
			cols.push(el('div', { 'class': 'ch-grid__col' }));
			heights.push(0);
		}
		items.forEach(function (f) {
			var k = 0;
			for (var j = 1; j < n; j++) { if (heights[j] < heights[k] - 0.001) { k = j; } }
			cols[k].appendChild(f);
			if (!f.hidden) { heights[k] += ratio(f) + 0.02; } // 0.02: roughly the gap under each photo
		});
		$$('.ch-grid__col', g).forEach(function (old) { g.removeChild(old); });
		cols.forEach(function (col) { g.appendChild(col); });
		g.classList.add('is-cols');
	}
	// Line up the bottom edge: the last few photos in each column get a little taller or
	// shorter (trimmed to fill, like the cover) so every column ends at the same height.
	// Only those photos change; the enlarged view always shows the whole photo.
	var EVEN_LAST = 4; // how many photos at the foot of each column share the adjustment
	function evenGrid(g) {
		$$('img[data-cg-even]', g).forEach(function (img) {
			img.style.aspectRatio = '';
			img.style.objectFit = '';
			img.style.objectPosition = '';
			img.removeAttribute('data-cg-even');
		});
		var cols = $$(':scope > .ch-grid__col', g);
		var w = cols.length ? cols[0].clientWidth : 0;
		if (cols.length < 2 || !w) { return; } // one column, or not on screen (hidden set)
		var gap = parseFloat(window.getComputedStyle(g).columnGap) || 0;
		var data = cols.map(function (col) {
			var items = $$('.ch-grid__item', col).filter(function (f) { return !f.hidden; });
			var h = items.reduce(function (t, f) { return t + w * ratio(f) + gap; }, 0);
			return { items: items, h: h };
		});
		// Needs at least two rows everywhere, or a short gallery would be cropped heavily.
		if (data.some(function (d) { return d.items.length < 2; })) { return; }
		var target = data.reduce(function (t, d) { return t + d.h; }, 0) / data.length;
		data.forEach(function (d) {
			var last = d.items.slice(-EVEN_LAST);
			var lastH = last.reduce(function (t, f) { return t + w * ratio(f); }, 0);
			var scale = (lastH + target - d.h) / lastH;
			last.forEach(function (f) {
				var img = f.querySelector('img');
				if (!img) { return; }
				img.style.aspectRatio = (1 / (ratio(f) * scale)).toFixed(4);
				img.style.objectFit = 'cover';
				img.style.objectPosition = '50% 30%'; // trim more from the bottom than the top, to keep faces
				img.setAttribute('data-cg-even', scale.toFixed(3));
			});
		});
	}
	function layoutGrids(force) { grids.forEach(function (g) { layoutGrid(g, force); evenGrid(g); }); }
	layoutGrids(true);
	var resizeTimer;
	window.addEventListener('resize', function () {
		clearTimeout(resizeTimer);
		resizeTimer = setTimeout(function () { layoutGrids(false); }, 120);
	});

	function visibleItems(scope) {
		return itemsInOrder(scope).filter(function (f) {
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
		layoutGrids(true);
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
			root.appendChild(drawerBack);
			root.appendChild(drawer);
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
	var lb, lbImg, lbCount, lbName, lbHeart, lbHeartLabel, lbDl, lbPlay, lbPlayLabel;
	var lbItems = [], lbIndex = 0, lbTimer = null, lastFocus, lbPushed = false;

	// A control with an icon and a visible label.
	function lbButton(icon, label, onclick, extraClass) {
		var text = el('span', { text: label });
		var b = el('button', { type: 'button', 'class': 'ch-lb__btn' + (extraClass ? ' ' + extraClass : ''), html: ICON[icon], onclick: onclick }, [text]);
		b.cgLabel = text;
		return b;
	}

	function buildLightbox() {
		lbImg = el('img', { 'class': 'ch-lb__img', alt: '' });
		lbCount = el('span', { 'class': 'ch-lb__count' });
		lbName = el('span', { 'class': 'ch-lb__name' });

		if (CFG.canFavourite) {
			lbHeart = lbButton('heart', T.favourite, function () {
				toggleHeart(parseInt(lbItems[lbIndex].getAttribute('data-cg-photo'), 10));
			});
			lbHeart.setAttribute('aria-pressed', 'false');
			lbHeartLabel = lbHeart.cgLabel;
		} else if (CFG.signInForFav) {
			lbHeart = el('a', { 'class': 'ch-lb__btn', href: CFG.loginUrl, html: ICON.heart }, [el('span', { text: T.signInFav })]);
		}
		lbDl = el('a', { 'class': 'ch-lb__btn', html: ICON.download }, [el('span', { text: T.download })]);
		lbPlay = lbButton('play', T.slideshow, function () { if (lbTimer) { stopShow(); } else { startShow(); } });
		lbPlayLabel = lbPlay.cgLabel;

		var stage = el('div', { 'class': 'ch-lb__stage' }, [
			lbImg,
			el('button', { type: 'button', 'class': 'ch-lb__btn ch-lb__nav ch-lb__nav--prev', 'aria-label': T.previous, html: ICON.prev, onclick: function () { stopShow(); lbGo(-1); } }),
			el('button', { type: 'button', 'class': 'ch-lb__btn ch-lb__nav ch-lb__nav--next', 'aria-label': T.next, html: ICON.next, onclick: function () { stopShow(); lbGo(1); } })
		]);

		lb = el('div', { 'class': 'ch-lb', role: 'dialog', 'aria-modal': 'true', 'aria-label': document.title, hidden: '' }, [
			el('div', { 'class': 'ch-lb__bar' }, [
				lbCount,
				lbName,
				lbButton('close', T.back, closeLightbox, 'ch-lb__back')
			]),
			stage,
			el('div', { 'class': 'ch-lb__actions' }, [
				lbButton('prev', T.prevShort, function () { stopShow(); lbGo(-1); }),
				el('span', { 'class': 'ch-lb__spacer' }),
				lbHeart,
				lbDl,
				lbPlay,
				el('span', { 'class': 'ch-lb__spacer' }),
				lbButton('next', T.nextShort, function () { stopShow(); lbGo(1); })
			])
		]);
		root.appendChild(lb);

		// Swipe on phones.
		var x0 = null;
		stage.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
		stage.addEventListener('touchend', function (e) {
			if (x0 === null) { return; }
			var dx = e.changedTouches[0].clientX - x0;
			if (Math.abs(dx) > 40) { stopShow(); lbGo(dx < 0 ? 1 : -1); }
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
		if (lbHeartLabel) {
			var on = inCurrent(id);
			lbHeart.setAttribute('aria-pressed', on ? 'true' : 'false');
			lbHeartLabel.textContent = on ? T.favourited : T.favourite;
		}
		// Preload the next photo.
		var next = lbItems[(lbIndex + 1) % lbItems.length];
		if (next) { (new Image()).src = next.getAttribute('data-web'); }
	}

	function openLightbox(index, scope) {
		if (!lb) { buildLightbox(); }
		lbItems = visibleItems(scope);
		if (!lbItems.length) { return; }
		lbIndex = Math.max(0, Math.min(index, lbItems.length - 1));
		lastFocus = document.activeElement;
		lb.hidden = false;
		document.documentElement.style.overflow = 'hidden';
		// The phone's / browser's Back button returns to the gallery instead of leaving the page.
		if (window.history && history.pushState) {
			history.pushState({ cgLightbox: true }, '');
			lbPushed = true;
		}
		lbSync();
		lbPlay.focus();
	}
	function hideLightbox() {
		stopShow();
		lb.hidden = true;
		document.documentElement.style.overflow = '';
		if (lastFocus) { lastFocus.focus(); }
	}
	function closeLightbox() {
		if (lbPushed) {
			history.back(); // the popstate handler below hides it
		} else {
			hideLightbox();
		}
	}
	window.addEventListener('popstate', function () {
		if (lb && !lb.hidden) {
			lbPushed = false;
			hideLightbox();
		}
	});
	function lbGo(step) {
		lbIndex = (lbIndex + step + lbItems.length) % lbItems.length;
		lbSync();
	}
	function setPlayIcon(name) {
		var old = lbPlay.querySelector('svg');
		var tmp = document.createElement('span');
		tmp.innerHTML = ICON[name];
		if (old) { lbPlay.replaceChild(tmp.firstChild, old); }
	}
	function startShow() {
		setPlayIcon('pause');
		lbPlayLabel.textContent = T.pause;
		lbTimer = setInterval(function () { lbGo(1); }, 4000);
	}
	function stopShow() {
		if (lbTimer) { clearInterval(lbTimer); }
		lbTimer = null;
		if (lbPlay) {
			setPlayIcon('play');
			lbPlayLabel.textContent = T.slideshow;
		}
	}

	$$('.ch-grid__open').forEach(function (a) {
		a.addEventListener('click', function (e) {
			e.preventDefault();
			// Each embedded gallery on a page pages through its own photos only.
			var scope = a.closest('.cg-embed') || document;
			openLightbox(visibleItems(scope).indexOf(a.closest('.ch-grid__item')), scope);
		});
	});
	var showBtn = $('[data-cg-slideshow]');
	if (showBtn) {
		showBtn.addEventListener('click', function () { openLightbox(0); startShow(); });
	}

	document.addEventListener('keydown', function (e) {
		if (lb && !lb.hidden) {
			if (e.key === 'Escape') { closeLightbox(); }
			else if (e.key === 'ArrowRight') { stopShow(); lbGo(1); }
			else if (e.key === 'ArrowLeft') { stopShow(); lbGo(-1); }
			return;
		}
		if (drawer && !drawer.hidden && e.key === 'Escape') { closeDrawer(); }
	});

	render();
})();
