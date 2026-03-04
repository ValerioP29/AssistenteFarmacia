document.addEventListener('appLoaded', () => {
	const container = document.getElementById('panel');
	if (!container) return;

	const isBaselinePharma = dataStore?.pharma?.id === 1;

	const parseServices = (description) => {
			if (!description || typeof description !== 'string') return [];
			return description
					.split(/\r?\n/)
					.map((service) => service.trim())
					.filter(Boolean);
	};

	appFetchWithToken(AppURLs.api.getServices())
		.then(({status, data: servizi}) => {
			if (!status) throw new Error('Errore API nel caricamento servizi');

			container.innerHTML = '';

			servizi.forEach((servizio) => {
				const btn = document.createElement('button');
				btn.classList.add('accordion');
				btn.dataset.id = servizio?.id ?? 'servizio-generico';
				btn.dataset.item = 'service';
				btn.innerHTML = `
					<i class="${servizio.iconClass ?? ''}"></i>
					<span class="title">${servizio.title ?? ''}</span>
				`;

				const div = document.createElement('div');
				div.classList.add('panel');
				div.style.display = 'none';

				const servicesList = parseServices(servizio.description);

				const servicesDescription = isBaselinePharma || !servicesList.length
					? `<p>${nl2br(servizio.description ?? '')}</p>`
					: `
					<div class="service-description">
						<ul class="service-description__list">
							${servicesList.map((service) => `<li>${service}</li>`).join('')}
						</ul>
					</div>
				`;

				const servicesSelect = !isBaselinePharma && servicesList.length
					? `
					<div class="service-select">
						<label class="form-label fw-semibold" for="service-${servizio.id}">Scegli il servizio</label>
						<select id="service-${servizio.id}" class="form-select service-select__control">
							<option value="">Seleziona il servizio</option>
							${servicesList.map((service) => `<option value="${escapeAttr(service)}">${service}</option>`).join('')}
						</select>
					</div>
					`
					: '';

				div.innerHTML = `
					${servicesDescription}
					<div class="d-flex justify-content-end btn-ai-wrapper">
						<button class="btn btn-ai btn-ai--service" aria-label="Chiedi all'AI" data-nome="${escapeAttr(servizio.title)}"
							data-id="${servizio.id}">
							<img src="${dataStore.pharma.image_bot}" width="32" height="32" alt="" />
						</button>
					</div>
					${servicesSelect}
					<div class="booking-form-wrapper">
						<label for="datetime-${servizio.id}" class="text-center">Seleziona la data:</label>
						<i class="fas fa-calendar-alt calendar-icon"></i>
						<input id="datetime-${servizio.id}" type="datetime-local" name="pickup" class="booking-form form-control" required />
					</div>
					<p class="small text-muted mt-2">
						Le date e gli orari scelti devono rispettare l'orario di apertura della farmacia.
						<a role="button" class="see-hours">Vedi orari</a>
					</p>
					<a href="#" class="cta">Prenota ora</a>
					<p class="small text-muted mt-2 mb-0">** Le richieste inviate dopo le ore 13:00 potrebbero essere gestite il giorno successivo.</p>
				`;

				btn.addEventListener('click', function () {
					this.classList.toggle('active');
					const panel = this.nextElementSibling;
					panel.style.display = panel.style.display === 'block' ? 'none' : 'block';
				});

				// Append
				container.appendChild(btn);
				container.appendChild(div);
			});

			document.dispatchEvent(new CustomEvent('servicesLoaded'));
		})
		.catch((err) => handleError(err, 'Errore nel caricamento dei servizi'));

	function getServiceButtonFromPanel(panel) {
		if (!panel) return null;
		const btn = panel.previousElementSibling;
		if (btn && btn.dataset.item === 'service') return btn;
		return null;
	}

	document.addEventListener('click', async (e) => {
		if (e.target.classList.contains('calendar-icon')) {
			const input = e.target.closest('.booking-form-wrapper')?.querySelector("input[type='datetime-local']");
			input?.showPicker?.();
			input?.focus();
			return;
		}

		const btnHours = e.target.closest('.see-hours');
		if (btnHours) {
			e.preventDefault();
			showClockModal();
			return;
		}

		if (e.target.matches('.panel .cta')) {
			e.preventDefault();
			const panel = e.target.closest('.panel');
			const input = panel?.querySelector("input[type='datetime-local']");
			const datetime = input?.value ?? '';

			const selectedService = panel?.querySelector('.service-select__control')?.value?.trim() ?? '';

			if (!datetime) {
				showToast('Inserisci data e orario prima di prenotare', 'warning');
				return;
			}

			if (new Date(datetime) < new Date()) {
				showToast('Seleziona una data futura valida', 'warning');
				return;
			}

			if (!isBaselinePharma) {
				const hasOptions = !!panel?.querySelector('.service-select__control');
				if (hasOptions && !selectedService) {
					showToast('Seleziona un servizio prima di prenotare', 'warning');
					return;
				}
			}

			const btn = getServiceButtonFromPanel(panel);
			const service = btn?.querySelector('.title')?.textContent.trim() ?? 'Servizio';

			try {
				const id = btn?.dataset?.id ?? '';
				const success = await prepareAndSendBooking({
					id,
					type: 'service',
					datetime,
					message: selectedService ? `Servizio selezionato: ${selectedService}` : undefined,
				});

				if (success) {
					input.value = '';
					showToast(success.message, 'warning');
				}
			} catch (error) {
				handleError(error, 'Errore di rete. Riprova più tardi.');
			}
		}
	});

	async function submitServiceRequest(request) {
		if (!request || typeof request !== 'string' || !request.trim()) {
			const errMsg = 'La richiesta non è valida.';
			handleError(errMsg);
			document.dispatchEvent(new CustomEvent('serviceRequestError', {detail: {error: errMsg}}));
			return;
		}

		try {
			const res = await appFetchWithToken(AppURLs.api.bookService(), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify({
					type: 'service',
					request: request.trim(),
				}),
			});

			if (res.message) showToast(res.message, res.status ? 'success' : 'warning');

			if (res.status === true) {
				document.dispatchEvent(new CustomEvent('points:refreshRequested', {
					detail: { source: 'service_booking' },
				}));
				document.dispatchEvent(new CustomEvent('serviceRequestSuccess', {detail: res}));
			} else {
				throw new Error(res.message || 'Errore dal server');
			}
		} catch (error) {
			handleError(error, 'Errore durante l’invio. Riprova.');
			document.dispatchEvent(new CustomEvent('serviceRequestError', {detail: {error}}));
		}
	}

	document.querySelectorAll('.cta-submit').forEach((btn) => {
		btn.addEventListener('click', async () => {
			const textarea = document.querySelector('[name="custom_request"]');
			const request = textarea?.value.trim();
			if (!request) {
				showToast('Inserisci una richiesta valida', 'warning');
				return;
			}
			await submitServiceRequest(request);
		});
	});

	document.addEventListener('serviceRequestSuccess', () => {
		const textarea = document.querySelector('[name="custom_request"]');
		if (textarea) textarea.value = '';
	});

	document.addEventListener('serviceRequestError', (e) => {
		console.warn('❌ Errore nella richiesta di servizio:', e.detail.error);
		showToast('Errore durante la richiesta di servizio', 'error');
	});
});

document.addEventListener('servicesLoaded', showServiceByUrl );
function showServiceByUrl() {
	const params = new URLSearchParams(window.location.search);

	if (params.has('id')) {
		const serviceId = params.get('id');
		console.log(serviceId);
		const elService = document.querySelector('[data-item="service"][data-id="'+serviceId+'"]')
		if( ! elService ) return;
		elService.click();

		document.body.classList.add('page-service');
	}
}

function getDayShortLabel(key) {
	const order = ['lun', 'mar', 'mer', 'gio', 'ven', 'sab', 'dom'];
	const idx = order.indexOf(key);
	if (idx === -1) return key;

	const refDate = new Date(2024, 0, 1 + idx);
	return new Intl.DateTimeFormat('it-IT', {weekday: 'short'}).format(refDate).replace(/^./, (m) => m.toUpperCase());
}

function fmtRange(open, close) {
	if (!open || !close) return '—';
	return `${open}–${close}`;
}

// Mostra il modale e popola tabella
function showClockModal() {
	const info = dataStore?.pharma?.working_info;
	const raw = info?.data || null;

	const tbody = document.querySelector('#pharmaHoursTable tbody');
	if (tbody) {
		tbody.innerHTML = '';

		if (raw && typeof raw === 'object') {
			const order = ['lun', 'mar', 'mer', 'gio', 'ven', 'sab', 'dom'];
			order.forEach((k) => {
				const d = raw[k];
				const tr = document.createElement('tr');

				if (!d) {
					tr.innerHTML = `
							<td>${getDayShortLabel(k)}</td>
							<td colspan="2"><span class="text-muted">Dati non disponibili</span></td>
						`;
					tbody.appendChild(tr);
					return;
				}

				const isClosed = d.closed === true;
				const morning = isClosed ? 'Chiuso' : fmtRange(d.morning_open, d.morning_close);
				const afternoon = isClosed ? 'Chiuso' : fmtRange(d.afternoon_open, d.afternoon_close);

				tr.innerHTML = `
						<td>${getDayShortLabel(k)}</td>
						<td>${morning}</td>
						<td>${afternoon}</td>
						`;
				tbody.appendChild(tr);
			});
		} else {
			const tr = document.createElement('tr');
			tr.innerHTML = `<td colspan="3"><span class="text-muted">Orari non disponibili</span></td>`;
			tbody.appendChild(tr);
		}
	}

	const modalEl = document.getElementById('pharmaHoursModal');
	if (!modalEl) return;
	const instance = bootstrap.Modal.getOrCreateInstance(modalEl);
	instance.show();
}
