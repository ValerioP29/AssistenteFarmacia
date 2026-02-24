document.addEventListener('appLoaded', () => {
	fetchPillsArchive();
});

function fetchPillsArchive(limit) {
	const url = AppURLs.api.getAllPills() + (typeof limit === 'number' ? `?limit=${limit}` : '');

	appFetchWithToken(url, {method: 'GET'})
		.then((data) => {
			if (data.status) {
				document.dispatchEvent(new CustomEvent('pillsArchiveSuccess', {detail: data.data}));
			} else {
				document.dispatchEvent(new CustomEvent('pillsArchiveError', {detail: {error: data.error}}));
			}
		})
		.catch((err) => {
			document.dispatchEvent(new CustomEvent('pillsArchiveError', {detail: {error: err}}));
		});
}

document.addEventListener('pillsArchiveSuccess', (event) => {
	const pills = event.detail;

	const latestContainer = document.getElementById('pillole-latest');
	const section = document.querySelector('.pill-section');

	if (!pills || pills.length === 0) {
		if (section) {
			section.innerHTML = `<div class="alert alert-warning text-center">📭 Nessuna pillola trovata.</div>`;
		}
		if (latestContainer) {
			latestContainer.innerHTML = `<div class="text-muted">Nessuna pillola disponibile</div>`;
		}
		return;
	}

	// ---- Ultime 3 pillole
	if (latestContainer) {
		const ultime = pills.slice(0, 3);
		latestContainer.innerHTML = ultime
			.map((pill) => {
				const category = pill.category || 'Benessere';
				const colorClass = PILL_CATEGORY_CLASSES[category] || 'pill-bg-unknown';
				const formattedDate = new Date(pill.day).toLocaleDateString('it-IT', {
					day: '2-digit',
					month: 'short',
					year: 'numeric',
				});
				return `<a href="${AppURLs.page.pill(pill.id)}" class="pillola-item pill ${colorClass} border-0">
					<div class="pill-meta pill-date">${formattedDate}</div>
					<div class="pill-category">${category}</div>
					<div class="pill-title">${pill.title}</div>
					<div class="pill-excerpt">${pill.excerpt || ''}</div>
				</a>`;
			})
			.join('');
	}

	if (!section) return;

	// ---- Archivio completo
	section.innerHTML = pills
		.map((pill) => {
			const formattedDate = new Date(pill.day).toLocaleDateString('it-IT', {
				day: '2-digit',
				month: 'long',
				year: 'numeric',
			});
			const category = pill.category || 'Sconosciuta';
			const colorClass = PILL_CATEGORY_CLASSES[category] || 'pill-bg-unknown';

			return `<div class="pill ${colorClass}" data-id="${pill.id}">
				<div class="pill-meta pill-date">${formattedDate}</div>
				<div class="pill-category">${category}</div>
				<div class="pill-title">${pill.title}</div>
				<div class="pill-excerpt">${pill.excerpt || ''}</div>
				<button type="button" 
						class="btn btn-primary mt-2"
						aria-label="Leggi tutta la pillola del ${formattedDate}"
						onclick="goTo('${AppURLs.page.pill(pill.id)}')">
					Leggi di più
				</button>
			</div>`;
		})
		.join('');
});

document.addEventListener('pillsArchiveError', (event) => {
	console.error('Errore nel caricamento pillole:', event.detail.error);
	const section = document.querySelector('.pill-section');
	if (section) {
		section.innerHTML = `<div class="alert alert-danger">Errore nel caricamento delle pillole. Riprova più tardi.</div>`;
	}
});
