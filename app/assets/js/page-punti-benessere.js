document.addEventListener('appLoaded', () => {
	fetchWellnessData();
	const modalEl = document.getElementById('pointsLegendModal');
	if (!modalEl) return;

	modalEl.addEventListener('show.bs.modal', () => {
		fillPointsLegend(lastWellnessData);
	});
});

function fetchWellnessData() {
	appFetchWithToken(AppURLs.api.getWellnessPoints(), {method: 'GET'})
		.then((data) => {
			if (data?.status) {
				document.dispatchEvent(new CustomEvent('wellnessSuccess', {detail: data.data}));
			} else {
				document.dispatchEvent(new CustomEvent('wellnessError', {detail: {error: data?.error || {message: 'Errore generico'}}}));
			}
		})
		.catch((err) => {
			document.dispatchEvent(new CustomEvent('wellnessError', {detail: {error: err}}));
		});
}

document.addEventListener('wellnessSuccess', function (event) {
	const data = event.detail || {};
	const container = document.getElementById('wellnessContent');

	gestisciWellnessPoints(String(data.points ?? 0));

	if (!container) return;

	const total = Number(data.points ?? 0);
	const goal = Number(data.goal ?? 500);
	const vouchers = Array.isArray(data.vouchers) ? data.vouchers : [];

	const cycle = total % goal;

	const atThreshold = total > 0 && cycle === 0;

	const remain = atThreshold ? goal : Math.max(goal - cycle, 0);

	const displayPoints = atThreshold ? 0 : cycle;

	const pct = Math.round((displayPoints / goal) * 100);

	const unlocked = Math.floor(total / goal);

	const content = `
    <div class="score-box">
      <div class="points">${displayPoints}</div>
      <div class="label">Punti accumulati</div>
    </div>

    <div class="goal-box">
      <h2>🎯 Obiettivo</h2>
      <p>Raggiungi <strong>${goal}</strong> punti per ottenere un buono sconto del valore di 10 euro.</p>
      <div class="progress">
        <div class="progress-bar" role="progressbar" style="width:${pct}%;" aria-valuenow="${pct}" aria-valuemin="0" aria-valuemax="100"></div>
      </div>
      <p class="muted mt-3">
       ${atThreshold ? 'Hai appena sbloccato un buono. Si riparte da 0.' : `Ti mancano <strong>${remain}</strong> punti al prossimo buono.`}
      </p>

    </div>

    ${renderVoucherSection(vouchers, unlocked)}
  `;

	container.innerHTML = content;

	bindVoucherButtons(container, vouchers);
	ensureVoucherModal(); // crea modale una volta se manca
});

function renderVoucherSection(vouchers, unlockedCount) {
	// Se arrivano oggetti, li mostriamo. Fine.
	const hasList = Array.isArray(vouchers) && vouchers.length > 0;

	return `
    ${
		!hasList
			? `<div class="alert alert-info mt-4">Nessun buono disponibile al momento.</div>`
			: `
      <div class="voucher-list">
        ${vouchers.map(renderVoucherItem).join('')}
      </div>
    `
	}
  `;
}

function renderVoucherItem(v) {
	const id = v.id ?? v.voucher_id;
	const starts = v.date_start ? new Date(v.date_start) : null;
	const ends = v.date_end ? new Date(v.date_end) : null;
	const now = new Date();

	let statusText = 'Attivo';
	if (typeof v.status !== 'undefined') {
		if (v.status === 2 || v.status === 'redeemed') statusText = 'Usato';
	}
	if (statusText === 'Attivo') {
		if (starts && now < starts) statusText = 'Non ancora valido';
		if (ends && now > ends) statusText = 'Scaduto';
	}

	const validity = (starts ? `<div class="voucher-exp"><span class="tag">Dal</span> ${formatDateTime(v.date_start)}</div>` : '') + (ends ? `<div class="voucher-exp"><span class="tag">Al</span> ${formatDateTime(v.date_end)}</div>` : '');

	return `
    <div class="voucher-item" data-voucher-id="${id}">
      <div class="voucher-meta">
        <div class="voucher-code"><span class="tag">Codice</span> <code>${v.code || 'n/d'}</code></div>
        <div class="voucher-status"><span class="tag">Stato</span> ${statusText}</div>
        ${validity}
      </div>
      ${
			statusText === 'Attivo'
				? `
        <div class="voucher-actions">
          <button class="btn btn-primary btn-sm js-show-qr" data-code="${v.code || ''}" data-id="${id}">Mostra QR</button>
        </div>`
				: ''
		}
    </div>
  `;
}

function bindVoucherButtons(root, vouchers) {
	root.querySelectorAll('.js-show-qr').forEach((btn) => {
		btn.addEventListener('click', () => {
			const id = btn.getAttribute('data-id');
			const code = btn.getAttribute('data-code');
			const v = vouchers.find((x) => String(x.id ?? x.voucher_id) === String(id));

			// Usa 'qr' se presente, altrimenti fallback a 'qr_base64'
			const qr = v?.qr || v?.qr_base64 || null;

			openVoucherModal({
				id,
				code,
				qr,
			});
		});
	});
}

function ensureVoucherModal() {
	if (document.getElementById('voucherModal')) return;
	const modal = document.createElement('div');
	modal.id = 'voucherModal';
	modal.className = 'voucher-modal is-hidden';
	modal.innerHTML = `
    <div class="vm-backdrop"></div>
    <div class="vm-dialog">
      <button class="vm-close" aria-label="Chiudi">&times;</button>
      <h4>Buono sconto</h4>
      <div class="vm-body">
        <p class="muted">Mostra questo QR al banco oppure il codice qui sotto.</p>
        <div class="vm-qr">
          <img id="vm-qr-img" alt="QR del buono" />
        </div>
        <div class="vm-code"><code id="vm-code"></code></div>
      </div>
    </div>
  `;
	document.body.appendChild(modal);

	modal.querySelector('.vm-backdrop').addEventListener('click', closeVoucherModal);
	modal.querySelector('.vm-close').addEventListener('click', closeVoucherModal);
	document.addEventListener('keydown', (e) => {
		if (e.key === 'Escape') closeVoucherModal();
	});
}

function openVoucherModal({id, code, qr}) {
	const modal = document.getElementById('voucherModal');
	if (!modal) return;

	const img = modal.querySelector('#vm-qr-img');
	const codeEl = modal.querySelector('#vm-code');

	if (qr) {
		img.src = qr.startsWith('data:image') ? qr : `data:image/png;base64,${qr}`;
		img.classList.remove('is-hidden');
	} else {
		img.classList.add('is-hidden');
	}
	codeEl.textContent = code || '—';

	modal.classList.remove('is-hidden');
	document.body.classList.add('no-scroll');
}

function closeVoucherModal() {
	const modal = document.getElementById('voucherModal');
	if (!modal) return;
	modal.classList.add('is-hidden');
	document.body.classList.remove('no-scroll');
}

document.addEventListener('wellnessError', function (event) {
	const {error} = event.detail || {};
	const container = document.getElementById('wellnessContent');
	if (!container) return;

	container.innerHTML = `
    <div class="alert alert-danger" role="alert">
      Errore nel caricamento dei dati: ${error?.message || 'riprovare più tardi.'}
    </div>
  `;
});

function formatDateTime(s) {
	try {
		const d = new Date(s);
		if (Number.isNaN(d.getTime())) return '';
		return d.toLocaleString('it-IT', {day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'});
	} catch {
		return '';
	}
}

let lastWellnessData = null;

document.addEventListener('wellnessSuccess', function (event) {
	lastWellnessData = event.detail || null;
});

function fillPointsLegend(data) {
	if (!data) return;

	const legendWrap = document.getElementById('legendContainer');
	if (!legendWrap) return;

	const list = Array.isArray(data.points_legend) ? data.points_legend : [];
	const visible = list.filter((x) => x && x.hidden !== true);

	if (visible.length === 0) {
		legendWrap.innerHTML = `
        <div class="alert alert-info mb-0">
          Nessuna regola disponibile al momento.
        </div>
      `;
		return;
	}

	const frag = document.createDocumentFragment();

	const head = document.createElement('div');
	head.className = 'd-flex justify-content-between align-items-center mb-2';
	head.innerHTML = `<!--<h6 class="m-0">Azioni che danno punti</h6>-->
                    <!--  <span class="text-muted small">${visible.length} regole</span> -->`;
	frag.appendChild(head);

	visible.forEach((item) => frag.appendChild(createLegendItem(item)));

	legendWrap.innerHTML = '';
	legendWrap.appendChild(frag);
}

function createLegendItem(item) {
	const wrap = document.createElement('div');
	wrap.className = 'border rounded p-2 p-sm-3 mb-2';
	wrap.innerHTML = `
      <div class="d-flex justify-content-between align-items-start gap-3">
        <div>
          <div class="fw-semibold">${item.title ?? 'Attività'}</div>
          <div class="text-muted small">${nl2br(item.desc ?? '')}</div>
        </div>
        <span class="badge text-bg-primary flex-shrink-0" aria-label="Punti">+${Number(item.value ?? 0)} pt</span>
      </div>
    `;
	return wrap;
}
