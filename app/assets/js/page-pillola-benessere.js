// === PILL CONTENT BY ID ===
document.addEventListener('appLoaded', () => {
	const params = new URLSearchParams(window.location.search);
	const pillId = params.get('id');
	if (pillId) {
		fetchPillContent(pillId);
	} else {
		showToast('Nessuna pillola selezionata', 'warning');
	}
});

function fetchPillContent(id) {
	appFetchWithToken(AppURLs.api.getPill(id), {method: 'GET'})
		.then((data) => {
			if (data.status) {
				document.dispatchEvent(new CustomEvent('pillContentSuccess', {detail: data.data}));
			} else {
				document.dispatchEvent(new CustomEvent('pillContentError', {detail: data}));
			}
		})
		.catch((err) => {
			document.dispatchEvent(new CustomEvent('pillContentError', {detail: {message: err}}));
		});
}

document.addEventListener('pillContentSuccess', (event) => {
	const data = event.detail;
	const container = document.querySelector('.pill-content');
	if (!container) return;
	container.innerHTML = renderPillContent(data);
});

document.addEventListener('pillContentError', (event) => {
	const data = event.detail || {};
	showToast(data.message || 'Errore nel caricamento della pillola', 'danger');
});

function renderPillContent(data) {
	const {title, content, day, category} = data;
	const colorClass = PILL_CATEGORY_CLASSES?.[category] || 'pill-bg-unknown';

	const formattedDate = day
		? new Date(day).toLocaleDateString('it-IT', {
				day: '2-digit',
				month: 'long',
				year: 'numeric',
		  })
		: '';

	let html = `<div class="pill ${colorClass}">`;

	if (formattedDate) {
		html += `<div class="text-center text-muted pill-meta pill-date">${formattedDate}</div>`;
	}

	if (category) {
		const safeCategory = category.toLowerCase().replace(/\s+/g, '-');
		html += `<div class="text-center fw-bold pill-category pill-category-${safeCategory}">${category}</div>`;
	}

	if (title) {
		html += `<h1 class="pill-title mb-2">${title}</h1>`;
	}

	if (content) {
		html += `<div class="pill-body">${nl2br(limitConsecutiveNewlines(content))}</div>`;
	}

	html += `</div>`;
	return html;
}
