document.addEventListener('appLoaded', () => {
	fetchArchiveOrders();
});

function fetchArchiveOrders() {
	appFetchWithToken(AppURLs.api.getArchivedOrders(), {method: 'GET'})
		.then((data) => {
			if (data.status) {
				document.dispatchEvent(new CustomEvent('archiveSuccess', {detail: data.data}));
			} else {
				document.dispatchEvent(new CustomEvent('archiveError', {detail: {error: data.message || 'Errore nel recupero archivi'}}));
			}
		})
		.catch((err) => {
			document.dispatchEvent(new CustomEvent('archiveError', {detail: {error: err}}));
		});
}

document.addEventListener('archiveSuccess', (event) => {
	const orders = event.detail;
	const wrapper = document.getElementById('archiveContent');

	if (!Array.isArray(orders) || orders.length === 0) {
		wrapper.innerHTML = `<p class="text-center">Nessun ordine trovato.</p>`;
		return;
	}

	const container = document.createElement('div');
	container.id = 'bookingList';

	// Set dinamico per filtri
	const statusMap = new Map();
	statusMap.set('Tutti', 'Tutti');

	orders.forEach((order) => {
		const card = document.createElement('div');
		card.className = 'booking-card';
		card.dataset.status = order.status_id ?? 'unknown';
		card.dataset.statusLabel = order.status_label || 'Sconosciuto';

		statusMap.set(order.status_id, order.status_label || 'Sconosciuto');

		card.innerHTML = `
			<div class="booking-description">
				<pre>${order.description}</pre>
			</div>
			<div class="booking-meta">
				📅 Creato il: <strong>${formatDate(order.created_at)}</strong>
			</div>
			<div class="booking-status">
				🛈 Stato: <strong>${order.status_label ?? 'N/A'}</strong>
			</div>
			<div class="container-btn mt-2">
				<!-- <a role="button" class="btn btn-primary reorder-btn" data-id="${order.id}">
					Acquista di nuovo
				</a> -->
				${ order.status_id === 0 ?
					`<a role="button" class="btn btn-outline-danger cancel-btn mt-3" data-id="${order.id}">
						Annulla
					</a>`
					: '' }
				</div>
		`;

		// Event per reorder
		/*card.querySelector('.reorder-btn').addEventListener('click', () => {
			reorderFromDescription(order.description);
		});*/

		// Event per cancel
		if (order.status_id === 0) {
			card.querySelector('.cancel-btn').addEventListener('click', () => {
				cancelBooking(order.id, card);
			});
		}

		container.appendChild(card);
	});

	wrapper.innerHTML = '';
	wrapper.appendChild(container);

	populateStatusFilter(statusMap);
	setupBookingFilter();
});

document.addEventListener('archiveError', (event) => {
	const error = event.detail.error;
	const message = handleError(error, 'Errore nel caricamento delle prenotazioni');

	const container = document.getElementById('archiveContent');
	container.innerHTML = `<p class="text-center">${message}. Riprova più tardi.</p>`;
});

function setupBookingFilter() {
	const filterSelect = document.getElementById('status-filter');
	if (!filterSelect) return;

	filterSelect.addEventListener('change', () => {
		const selected = String(filterSelect.value);
		const cards = document.querySelectorAll('.booking-card');

		cards.forEach((card) => {
			const status = String(card.dataset.status);
			card.style.display = selected === 'Tutti' || status === selected ? 'block' : 'none';
		});
	});
}

function populateStatusFilter(statusMap) {
	const filterSelect = document.getElementById('status-filter');
	if (!filterSelect) return;

	filterSelect.innerHTML = '';
	// Prima voce "Tutti"
	const optAll = document.createElement('option');
	optAll.value = 'Tutti';
	optAll.textContent = 'Tutti';
	filterSelect.appendChild(optAll);

	statusMap.forEach((label, id) => {
		if (id === 'Tutti') return;
		const option = document.createElement('option');
		option.value = String(id);
		option.textContent = label;
		filterSelect.appendChild(option);
	});
}

function formatDate(dateStr) {
	const date = new Date(dateStr);
	return date.toLocaleDateString('it-IT', {
		year: 'numeric',
		month: '2-digit',
		day: '2-digit',
		hour: '2-digit',
		minute: '2-digit',
	});
}

function cancelBooking(bookingId, cardElement) {
	if (!confirm('Sei sicuro di voler annullare questa prenotazione?')) {
		return;
	}

	appFetchWithToken(AppURLs.api.cancelBooking(), {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
		},
		body: JSON.stringify({id: bookingId}),
	})
		.then((data) => {
			if (data?.status) {
				const statusEl = cardElement.querySelector('.booking-status');
				if (statusEl) {
					statusEl.innerHTML = '🛈 Stato: <strong>Annullato</strong>';
					statusEl.style.color = '#999';
				}
				cardElement.dataset.status = String(data.data?.status_id ?? 'cancelled');
				cardElement.querySelector('.cancel-btn')?.remove();
				showToast('Prenotazione annullata con successo!', 'success');
			} else {
				handleError(data?.message || 'Errore durante l’annullamento della prenotazione.');
			}
		})
		.catch((err) => {
			handleError('Errore di connessione: ' + err);
		});
}
