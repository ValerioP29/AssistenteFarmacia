// === EVENTI: pagina (lista), modale dettaglio, evidenza dashboard ===
(function () {
	const DIALOG_IDS = {detail: 'event-detail-dialog'};

	// ---------- Utils
	const fmtDate = (d) => {
		if (!d) return 'Prossimamente';
		const dt = new Date(d);
		return dt.toLocaleDateString('it-IT', {day: '2-digit', month: '2-digit', year: 'numeric'});
	};
	const cleanText = (str) =>
		String(str ?? '')
			.replace(/\r\n/g, '\n')
			.replace(/\n{3,}/g, '\n\n')
			.split('\n')
			.map((s) => s.trim())
			.join('\n')
			.replace(/\n/g, '<br>');

	const byDateThenNoDate = (a, b) => {
		const da = a?.dates?.[0] ? new Date(a.dates[0]).getTime() : Infinity;
		const db = b?.dates?.[0] ? new Date(b.dates[0]).getTime() : Infinity;
		return da - db;
	};

	// Cover: "reale" = non default
	const hasRealCover = (ev) => !!(ev?.cover_image && ev.cover_image.is_default === false && ev.cover_image.src);
	// Banner/home: mostra l'immagine se c'è la src, anche se è placeholder
	const getBannerSrc = (ev) => {
		const src = ev?.cover_image?.src;
		return (src && String(src).trim()) || 'https://placehold.co/800x400/eeeeee/cccccc?text=Evento';
	};
	const getBannerAlt = (ev) => (ev?.cover_image?.alt || ev?.title || 'Evento').toString();

	async function fetchEvents() {
		const res = await appFetchWithToken(AppURLs.api.getEvents(), {method: 'GET'});
		if (!res?.status || !Array.isArray(res.data)) throw new Error(res?.error || 'Errore nel recupero eventi');
		return res.data;
	}

	function ensureDetailDialog() {
		if (document.getElementById(DIALOG_IDS.detail)) return;
		const wrap = document.createElement('div');
		wrap.id = DIALOG_IDS.detail;
		wrap.className = 'events-dialog hidden';
		wrap.innerHTML = `
      <div class="events-backdrop" data-dismiss></div>
      <div class="events-panel" role="dialog" aria-modal="true">
        <div class="events-header">
          <button type="button" class="btn-close" aria-label="Chiudi" data-dismiss>&times;</button>
        </div>
        <div class="events-body" id="event-detail-body"></div>
      </div>
    `;
		document.body.appendChild(wrap);
	}
	function openDialog(id) {
		const el = document.getElementById(id);
		if (!el) return;
		el.classList.remove('hidden');
		document.body.style.overflow = 'hidden';
	}
	function closeDialog(id) {
		const el = document.getElementById(id);
		if (!el) return;
		el.classList.add('hidden');
		const allHidden = Array.from(document.querySelectorAll('.events-dialog')).every((d) => d.classList.contains('hidden'));
		if (allHidden) document.body.style.overflow = '';
	}

	function renderDetailModal(ev) {
		ensureDetailDialog();
		const body = document.getElementById('event-detail-body');
		if (!body) return;

		const imgHtml = hasRealCover(ev) ? (typeof createResponsiveImage === 'function' ? createResponsiveImage(ev.cover_image, {class: 'event-cover'}).outerHTML : `<img class="event-cover" src="${ev.cover_image.src}" alt="${getBannerAlt(ev)}" />`) : '';

		const total = ev?.subscriptions?.total;
		const badgeHtml = typeof total === 'number' ? `<div class="limited" data-total="${total}">Solo ${total} posti</div>` : '';

		const dateLabel = ev?.dates?.[0] ? fmtDate(ev.dates[0]) : 'In programmazione';

		body.innerHTML = `
			${badgeHtml}
			${imgHtml}
			<div class="event-content">
				<div class="event-title">${ev?.title ?? ''}</div>
				<div class="event-date mb-2">${dateLabel}</div>
				<div class="event-description mb-3">${cleanText(ev?.description)}</div>
                 <div class="d-flex justify-content-end mt-2 btn-ai-wrapper">
					<button class="btn btn-ai btn-ai--event"
					data-nome="${escapeAttr(ev.title)}"
					data-id="${ev.id}"
					aria-label="Chiedi all'AI">
					<img src="${dataStore.pharma.image_bot}" width="32" height="32" alt="" />
					</button>
				</div>
				${
					ev?.has_availability !== false
						? `
							<div class="booking-form form-group">
								<label for="Orario" class="text-center">Seleziona orario*:</label>
								<i class="fa-solid fa-clock clock-icon"></i>
								<input type="time" class="form-control time-input stylish-time" required />
							</div>
							<p class="small text-muted">* L'orario scelto deve rientrare tra quelli disponibili dell'evento.</p>
							<a href="#" class="cta" data-book>Prenota ora</a>
							<p class="small text-muted">** Le richieste inviate dopo le ore 13:00 potrebbero essere gestite il giorno successivo.</p>
						`
						: `<div class="alert alert-secondary py-1 px-2 mt-2 mb-0 small">Ancora non prenotabile</div>`
				}
			</div>
		`;

		const bookBtn = body.querySelector('[data-book]');
		if (bookBtn) {
			bookBtn.addEventListener('click', async (e) => {
				e.preventDefault();
				const timeInput = body.querySelector('.time-input');
				const time = timeInput?.value?.trim();
				if (!time || time === '--:--') {
					showToast('Seleziona un orario prima di prenotare.', 'warning');
					return;
				}
				try {
					const success = await prepareAndSendBooking({id: ev.id, type: 'event', datetime: time});
					if (success) {
						timeInput.value = '';
						showToast(success.message, 'success');
						closeDialog(DIALOG_IDS.detail);
					}
				} catch (error) {
					handleError(error, 'Errore di rete. Controlla la connessione.');
				}
			});
		}

		openDialog(DIALOG_IDS.detail);
	}

	function openEventDialogByUrl(){
		const params = new URLSearchParams(window.location.search);

		if (params.has('id')) {
			const eventId = params.get('id');
			if( ! eventId ) return;
			const elEvent = document.querySelector('.events-row[data-id="'+eventId+'"]')
			if( ! elEvent ) return;
			elEvent.click();
		}
	}

	// ---------- PAGINA EVENTI: lista sulla pagina (NON modale)
	async function renderPageEventsList() {
		const container = document.getElementById('events-list');
		if (!container) return;

		try {
			const events = await fetchEvents();
			const attivi = events.filter((ev) => ev?.is_expired === false);
			const featured = attivi.filter((ev) => ev?.is_featured === true).sort(byDateThenNoDate);
			const nonFeatured = attivi.filter((ev) => !ev?.is_featured).sort(byDateThenNoDate);
			const elenco = [...featured, ...nonFeatured];

			container.innerHTML = '';

			if (elenco.length === 0) {
				const noEventBox = document.createElement('div');
				noEventBox.className = 'no-events';
				noEventBox.innerHTML = `<p class="text-center">🎉 Nessun evento in programma al momento</p>`;
				container.appendChild(noEventBox);
				return;
			}

			const ul = document.createElement('ul');
			ul.className = 'events-list';

			elenco.forEach((ev) => {
				const li = document.createElement('li');
				li.className = 'events-row';
				li.tabIndex = 0;
				li.role = 'button';
				li.dataset.id = ev.id;

				const date = fmtDate(ev?.dates?.[0]);

				let imgHtml = '';
				if (ev?.cover_image && ev.cover_image.is_default === false) {
					if (typeof createResponsiveImage === 'function') {
						imgHtml = createResponsiveImage(ev.cover_image, {class: 'event-thumb'}).outerHTML;
					} else {
						imgHtml = `<img class="event-thumb" src="${ev.cover_image.src}" alt="${ev.cover_image.alt || ev.title || 'Evento'}" />`;
					}
				}

				li.innerHTML = `
					${imgHtml}
					<div class="events-info">
						<span class="events-title">${ev?.title ?? 'Evento'}</span>
						<span class="events-date">${date}</span>
					</div>
				`;

				// Se c'è immagine → nascondo il titolo dentro questa card
				if (imgHtml) {
					li.querySelector('.events-title')?.classList.add('d-none');
				}

				ul.appendChild(li);
			});

			container.appendChild(ul);

			// delega click
			ul.addEventListener('click', (e) => {
				const row = e.target.closest('.events-row');
				if (!row) return;
				const id = row.dataset.id;
				const ev = elenco.find((x) => String(x.id) === String(id));
				if (!ev) return;
				renderDetailModal(ev);
			});

			document.dispatchEvent(new CustomEvent('eventsLoaded'));
		} catch (err) {
			handleError(err, 'Errore nel caricamento degli eventi');
			// opzionale: container.innerHTML = `<p class="text-danger">Errore durante il caricamento degli eventi</p>`;
		}
	}

	// ---------- DASHBOARD: evento in evidenza (banner)
	async function renderHighlightedEvent() {
		const wrapper = document.getElementById('highlighted-event-wrapper');
		const eventCard = document.getElementById('highlighted-event-card');
		if (!wrapper) return;

		try {
			const events = await fetchEvents();

			const inHome = events.filter((ev) => ev?.is_featured === true && ev?.is_expired === false).sort(byDateThenNoDate);

			const candidates = inHome.length > 0 ? inHome : events.filter((ev) => ev?.is_expired === false).sort(byDateThenNoDate);

			if (candidates.length === 0) {
				eventCard.classList.add('d-none');
				return;
			}

			const now = Date.now();
			const prossimo = candidates.find((ev) => ev?.dates?.[0] && new Date(ev.dates[0]).getTime() >= now) || candidates[0];

			const bannerImg = getBannerSrc(prossimo);
			const bannerDate = prossimo?.dates?.[0] ? fmtDate(prossimo.dates[0]) : 'Prossimamente';
			const alt = getBannerAlt(prossimo);

			wrapper.innerHTML = `
        <div class="event-image-container">
          <a role="button" class="event-link" onclick="goTo(AppURLs.page.events()+'?id=${prossimo.id}')">
            <img class="event-banner img-fluid w-100" src="${bannerImg}" alt="${alt}" />
          <!--    <div class="event-title-container">
            <span>${prossimo.title || 'Evento in evidenza'}</span>
              <span class="event-date">${bannerDate}</span> 
            </div> -->
          </a>
        </div>
      `;
		} catch (err) {
			handleError(err, 'Impossibile caricare l’evento in evidenza.');
			wrapper.innerHTML = `<p style="color:red;">Impossibile caricare l’evento in evidenza.</p>`;
		}
	}

	// ---------- Close handlers (solo modale dettaglio)
	document.addEventListener('click', (e) => {
		const inDetail = e.target.closest(`#${DIALOG_IDS.detail}`);
		if (e.target.matches('[data-dismiss]') || e.target.closest('.btn-close')) {
			if (inDetail) closeDialog(DIALOG_IDS.detail);
		}
	});

	// ---------- Boot
	document.addEventListener('appLoaded', () => {
		// Dashboard: banner evidenza
		renderHighlightedEvent();

		// Page eventi: render lista sulla pagina (non modale)
		if (document.body?.classList?.contains('page-events')) {
			renderPageEventsList();
		}
	});

	document.addEventListener('eventsLoaded', openEventDialogByUrl );
})();
