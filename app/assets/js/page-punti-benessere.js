/* ================================================================
   page-punti-benessere.js  —  Punti Benessere + Voucher
   ================================================================ */


document.addEventListener('appLoaded', () => {
	fetchWellnessData();

	const modalEl = document.getElementById('pointsLegendModal');
	if (modalEl) {
		modalEl.addEventListener('show.bs.modal', () => {
			fillPointsLegend(lastWellnessData);
		});
	}
});

// ── Fetch dati punti ─────────────────────────────────────────────
function fetchWellnessData() {
	setWellnessLoading(true);
	appFetchWithToken(AppURLs.api.getWellnessPoints(), {method: 'GET'})
		.then((data) => {
			if (data?.status) {
				document.dispatchEvent(new CustomEvent('wellnessSuccess', {detail: data.data}));
			} else {
				document.dispatchEvent(
					new CustomEvent('wellnessError', {
						detail: {error: data?.error || {message: 'Errore generico'}},
					})
				);
			}
		})
		.catch((err) => {
			document.dispatchEvent(new CustomEvent('wellnessError', {detail: {error: err}}));
		})
		.finally(() => setWellnessLoading(false));
}

// ── Render principale ────────────────────────────────────────────
let lastWellnessData = null;

document.addEventListener('wellnessSuccess', function (event) {
	lastWellnessData = event.detail || {};
	const data       = lastWellnessData;
	const container  = document.getElementById('wellnessContent');

	// Aggiorna badge punti nell'header (funzione globale app)
	gestisciWellnessPoints(String(data.points ?? 0));

	if (!container) return;

	const total      = Number(data.points_total ?? data.points ?? 0);
	const display    = Number(data.points       ?? 0);
	const goal       = Number(data.goal         ?? 100);
	const remaining  = Number(data.remaining    ?? Math.max(goal - display, 0));
	const canRedeem  = Boolean(data.can_redeem  ?? false);
	const valueEur   = Number(data.voucher_value ?? 10);
	const vouchers   = Array.isArray(data.vouchers) ? data.vouchers : [];
	const pct        = Math.min(Math.round((display / goal) * 100), 100);

	container.innerHTML = `
		${renderScoreBox(display, goal, pct, remaining, canRedeem, valueEur)}
		${renderRedeemSection(canRedeem, valueEur)}
		${renderVoucherList(vouchers)}
		${renderLegendToggle()}
	`;

	// QR: carica libreria e inizializza
	ensureQRLib(() => {
		bindRedeemButton(container, goal, valueEur);
		bindVoucherQR(container, vouchers);
	});

	ensureVoucherModal();
	bindLegendToggle(container);
});

// ── Score box + progress bar ─────────────────────────────────────
function renderScoreBox(display, goal, pct, remaining, canRedeem, valueEur) {
	const hintText = canRedeem
		? `🎉 Hai raggiunto i ${goal} punti! Genera il tuo voucher da ${valueEur} EUR.`
		: `Ti mancano <strong>${remaining}</strong> punti per sbloccare il voucher da ${valueEur} EUR.`;

	return `
	<div class="wb-score-card ${canRedeem ? 'is-unlocked' : ''}">
		<div class="wb-score-card__top">
			<div class="wb-score-card__points">
				<span class="wb-score-card__number" id="wb-anim-points">${display}</span>
				<span class="wb-score-card__label">punti</span>
			</div>
			<div class="wb-score-card__goal-badge">
				<span>Obiettivo</span>
				<strong>${goal} pt</strong>
			</div>
		</div>
		<div class="wb-progress" role="progressbar" aria-valuenow="${display}" aria-valuemin="0" aria-valuemax="${goal}" aria-label="Progressione punti">
			<div class="wb-progress__fill ${canRedeem ? 'is-full' : ''}" style="width:${Math.max(pct, pct > 0 ? 3 : 0)}%"></div>
		</div>
		<p class="wb-score-card__hint">${hintText}</p>
	</div>`;
}

// ── Sezione riscatto voucher ─────────────────────────────────────
function renderRedeemSection(canRedeem, valueEur) {
	if (!canRedeem) return '';

	return `
	<div class="wb-redeem-banner">
		<div class="wb-redeem-banner__icon">🎁</div>
		<div class="wb-redeem-banner__body">
			<span class="wb-redeem-banner__title">Voucher da ${valueEur} EUR sbloccato!</span>
			<span class="wb-redeem-banner__desc">Genera il codice e mostral-o in farmacia.</span>
		</div>
		<button id="wb-generate-btn" class="wb-redeem-banner__btn" aria-label="Genera voucher">
			Genera
		</button>
	</div>`;
}

// ── Lista voucher esistenti ──────────────────────────────────────
function renderVoucherList(vouchers) {
	if (!vouchers.length) {
		return `<p class="wb-empty-vouchers">I tuoi voucher appariranno qui quando ne generi uno.</p>`;
	}

	return `
	<div class="wb-voucher-section">
		<h3 class="wb-voucher-section__title">I tuoi voucher</h3>
		<div class="wb-voucher-list">
			${vouchers.map(renderVoucherItem).join('')}
		</div>
	</div>`;
}

function renderVoucherItem(v) {
	const id     = v.id ?? v.voucher_id;
	const starts = v.date_start  ? new Date(v.date_start)  : null;
	const ends   = v.date_end    ? new Date(v.date_end)    : null;
	const now    = new Date();

	let statusKey = 'active';
	if (v.status === 1 || v.status === 'redeemed') statusKey = 'used';
	if (statusKey === 'active' && ends && now > ends) statusKey = 'expired';
	if (statusKey === 'active' && starts && now < starts) statusKey = 'pending';

	const statusLabel = {active: 'Attivo', used: 'Usato', expired: 'Scaduto', pending: 'Non ancora valido'};
	const isActive    = statusKey === 'active';
	const valueStr    = v.value_eur ? `${parseFloat(v.value_eur).toFixed(2)} EUR` : '10.00 EUR';

	return `
	<div class="wb-voucher-item wb-voucher-item--${statusKey}" data-voucher-id="${id}">
		<div class="wb-voucher-item__left">
			<span class="wb-voucher-item__value">${valueStr}</span>
			<code class="wb-voucher-item__code">${v.code || 'n/d'}</code>
			<span class="wb-voucher-item__status">${statusLabel[statusKey]}</span>
			${ends ? `<span class="wb-voucher-item__exp">Scade: ${formatDate(v.date_end)}</span>` : ''}
		</div>
		${isActive ? `
		<div class="wb-voucher-item__actions">
			<button class="wb-qr-btn js-show-qr" data-code="${v.code || ''}" data-id="${id}" aria-label="Mostra QR del voucher">
				<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
					<path d="M2 2h2v2H2V2zm-1-1v4h4V1H1zm7 1h2v2H8V2zm-1-1v4h4V1H7zm1 8h2v2H8v-2zm-1-1v4h4V8H7zM2 9h2v2H2V9zm-1-1v4h4V8H1zm11 1h1v1h-1V9zm-1-1v4h4V8h-4zm1 5h2v2h-2v-2zm-1-1v4h4v-4h-4zm-9 1h1v1H2v-1zm-1-1v3h3v-3H1zm13-9h1v1h-1V2zm-1-1v3h3V1h-3z"/>
				</svg>
				QR
			</button>
		</div>` : ''}
	</div>`;
}

// ── Legend toggle ────────────────────────────────────────────────
function renderLegendToggle() {
	return `
	<button class="wb-legend-toggle" id="wb-legend-toggle" aria-expanded="false" aria-controls="wb-legend-body">
		<span>Come guadagnare punti</span>
		<svg class="wb-legend-toggle__chevron" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
			<path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"/>
		</svg>
	</button>
	<div class="wb-legend-body" id="wb-legend-body" hidden>
		<div id="legendContainer"></div>
	</div>`;
}

// ── Bind: genera voucher ─────────────────────────────────────────
function bindRedeemButton(container, goal, valueEur) {
	const btn = container.querySelector('#wb-generate-btn');
	if (!btn) return;

	btn.addEventListener('click', async () => {
		btn.disabled = true;
		btn.textContent = 'Generazione...';

		try {
			const res = await appFetchWithToken(AppURLs.api.generateVoucher(), {method: 'POST'});

			if (res?.status && res.data?.voucher) {
				const v = res.data.voucher;
				openVoucherModal({
					code:     v.code,
					dateEnd:  v.date_end,
					valueEur: v.value_eur ?? valueEur,
					isNew:    true,
				});
				// Ricarica i dati aggiornati
				setTimeout(fetchWellnessData, 600);
			} else {
				showToast(res?.message || 'Errore nella generazione del voucher.', 'error');
				btn.disabled    = false;
				btn.textContent = 'Genera';
			}
		} catch (e) {
			showToast('Errore di rete. Riprova.', 'error');
			btn.disabled    = false;
			btn.textContent = 'Genera';
		}
	});
}

// ── Bind: pulsanti QR sui voucher esistenti ──────────────────────
function bindVoucherQR(container, vouchers) {
	container.querySelectorAll('.js-show-qr').forEach((btn) => {
		btn.addEventListener('click', () => {
			const id   = btn.getAttribute('data-id');
			const code = btn.getAttribute('data-code');
			const v    = vouchers.find((x) => String(x.id ?? x.voucher_id) === String(id));
			openVoucherModal({
				code,
				dateEnd:  v?.date_end,
				valueEur: v?.value_eur ?? 10,
				isNew:    false,
			});
		});
	});
}

// ── Bind: legend accordion ───────────────────────────────────────
function bindLegendToggle(container) {
	const btn  = container.querySelector('#wb-legend-toggle');
	const body = container.querySelector('#wb-legend-body');
	if (!btn || !body) return;

	btn.addEventListener('click', () => {
		const open = body.hidden;
		body.hidden = !open;
		btn.setAttribute('aria-expanded', String(open));
		btn.classList.toggle('is-open', open);
		if (open) fillPointsLegend(lastWellnessData);
	});
}

// ── Modale voucher (con QR generato lato client) ─────────────────
function ensureVoucherModal() {
	if (document.getElementById('voucherModal')) return;

	const modal = document.createElement('div');
	modal.id        = 'voucherModal';
	modal.className = 'wb-modal is-hidden';
	modal.setAttribute('role', 'dialog');
	modal.setAttribute('aria-modal', 'true');
	modal.setAttribute('aria-label', 'Dettaglio voucher');
	modal.innerHTML = `
		<div class="wb-modal__backdrop"></div>
		<div class="wb-modal__dialog">
			<button class="wb-modal__close" aria-label="Chiudi">&times;</button>
			<div class="wb-modal__header">
				<span id="vm-badge" class="wb-modal__badge"></span>
				<h4 class="wb-modal__title">Il tuo voucher</h4>
				<p class="wb-modal__subtitle">Mostralo al banco oppure condividi il codice.</p>
			</div>
			<div class="wb-modal__body">
				<div id="vm-qr-container" class="wb-modal__qr"></div>
				<code id="vm-code" class="wb-modal__code"></code>
				<p id="vm-expiry" class="wb-modal__expiry"></p>
			</div>
			<div class="wb-modal__footer">
				<button id="vm-share-btn" class="wb-modal__share-btn">
					<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
						<path d="M13.5 1a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zM11 2.5a2.5 2.5 0 1 1 .603 1.628l-6.718 3.12a2.499 2.499 0 0 1 0 1.504l6.718 3.12a2.5 2.5 0 1 1-.488.876l-6.718-3.12a2.5 2.5 0 1 1 0-3.256l6.718-3.12A2.5 2.5 0 0 1 11 2.5z"/>
					</svg>
					Condividi codice
				</button>
			</div>
		</div>`;

	document.body.appendChild(modal);

	modal.querySelector('.wb-modal__backdrop').addEventListener('click', closeVoucherModal);
	modal.querySelector('.wb-modal__close').addEventListener('click', closeVoucherModal);
	document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeVoucherModal(); });
}

function openVoucherModal({code, dateEnd, valueEur, isNew}) {
	const modal = document.getElementById('voucherModal');
	if (!modal) return;

	const badge   = modal.querySelector('#vm-badge');
	const codeEl  = modal.querySelector('#vm-code');
	const expEl   = modal.querySelector('#vm-expiry');
	const qrCont  = modal.querySelector('#vm-qr-container');
	const shareBtn = modal.querySelector('#vm-share-btn');

	badge.textContent   = `${parseFloat(valueEur || 10).toFixed(2)} EUR`;
	codeEl.textContent  = code || '—';
	expEl.textContent   = dateEnd ? `Valido fino al: ${formatDate(dateEnd)}` : '';

	if (isNew) modal.querySelector('.wb-modal__title').textContent = '🎉 Voucher generato!';
	else       modal.querySelector('.wb-modal__title').textContent = 'Il tuo voucher';

	// Genera QR client-side
	qrCont.innerHTML = '';
	if (typeof QRCode !== 'undefined' && code) {
		new QRCode(qrCont, {
			text:         code,
			width:        200,
			height:       200,
			colorDark:    '#6a2892',
			colorLight:   '#ffffff',
			correctLevel: QRCode.CorrectLevel.H,
		});
	} else {
		qrCont.innerHTML = `<p class="wb-modal__no-qr">Codice: <strong>${code}</strong></p>`;
	}

	// Share / Copy
	shareBtn.onclick = () => {
		if (navigator.share) {
			navigator.share({title: 'Voucher Farmacia', text: `Il mio codice voucher: ${code}`}).catch(() => {});
		} else {
			navigator.clipboard?.writeText(code).then(() => showToast('Codice copiato!', 'success'));
		}
	};

	modal.classList.remove('is-hidden');
	document.body.classList.add('no-scroll');
}

function closeVoucherModal() {
	const modal = document.getElementById('voucherModal');
	if (!modal) return;
	modal.classList.add('is-hidden');
	document.body.classList.remove('no-scroll');
}

// ── Carica QR lib da CDN (una volta sola) ────────────────────────
function ensureQRLib(cb) {
	if (typeof QRCode !== 'undefined') { cb(); return; }
	const s    = document.createElement('script');
	s.src      = 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js';
	s.onload   = cb;
	s.onerror  = cb; // fallback senza QR
	document.head.appendChild(s);
}

// ── Errore dati ──────────────────────────────────────────────────
document.addEventListener('wellnessError', function (event) {
	const {error}   = event.detail || {};
	const container = document.getElementById('wellnessContent');
	if (!container) return;
	container.innerHTML = `
		<div class="alert alert-danger" role="alert">
			Errore nel caricamento: ${error?.message || 'riprova più tardi.'}
		</div>`;
});

// ── Loading state ────────────────────────────────────────────────
function setWellnessLoading(on) {
	const container = document.getElementById('wellnessContent');
	if (!container) return;
	if (on) {
		container.innerHTML = `
			<div class="wb-skeleton">
				<div class="wb-skeleton__bar wb-skeleton__bar--tall"></div>
				<div class="wb-skeleton__bar"></div>
				<div class="wb-skeleton__bar wb-skeleton__bar--short"></div>
			</div>`;
	}
}

// ── Helpers ──────────────────────────────────────────────────────
function formatDate(s) {
	try {
		const d = new Date(s);
		if (Number.isNaN(d.getTime())) return '';
		return d.toLocaleDateString('it-IT', {day: '2-digit', month: '2-digit', year: 'numeric'});
	} catch { return ''; }
}

function showToast(msg, type = 'info') {
	const t = document.createElement('div');
	t.className = `wb-toast wb-toast--${type}`;
	t.textContent = msg;
	document.body.appendChild(t);
	requestAnimationFrame(() => t.classList.add('is-visible'));
	setTimeout(() => { t.classList.remove('is-visible'); setTimeout(() => t.remove(), 300); }, 3000);
}

// ── Dashboard: listener wellnessSuccess per aggiornare widget home ─
document.addEventListener('wellnessSuccess', function (event) {
	const data = event.detail || {};

	const display  = Number(data.points      ?? 0);
	const goal     = Number(data.goal        ?? 100);
	const remaining = Number(data.remaining  ?? Math.max(goal - display, 0));
	const canRedeem = Boolean(data.can_redeem ?? false);
	const pct      = Math.min(Math.round((display / goal) * 100), 100);

	// Barra nella card benessere dashboard
	const fill   = document.getElementById('wellness-progress-fill');
	const label  = document.getElementById('wellness-progress-label');
	const hint   = document.getElementById('wellness-progress-hint');
	const barEl  = fill?.parentElement;

	if (fill)  fill.style.width = Math.max(pct, pct > 0 ? 2 : 0) + '%';
	if (label) label.textContent = `${display}/${goal}`;
	if (barEl) { barEl.setAttribute('aria-valuenow', display); barEl.setAttribute('aria-valuemax', goal); }
	if (hint) {
		hint.textContent = canRedeem
			? '🎉 Hai sbloccato un voucher! Vai su Premi e Voucher.'
			: `Ti mancano ${remaining} punti per sbloccare ${data.voucher_value ?? 10} EUR.`;
	}
});

// ── Legend ───────────────────────────────────────────────────────
function fillPointsLegend(data) {
	if (!data) return;
	const legendWrap = document.getElementById('legendContainer');
	if (!legendWrap) return;

	const list    = Array.isArray(data.points_legend) ? data.points_legend : [];
	const visible = list.filter((x) => x && x.hidden !== true);

	if (!visible.length) {
		legendWrap.innerHTML = `<div class="alert alert-info mb-0">Nessuna regola disponibile.</div>`;
		return;
	}

	legendWrap.innerHTML = visible.map((item) => {
		const pts = item?.value_label
			? item.value_label
			: `+${Number(item.value ?? 0)} pt`;

		return `
		<div class="wb-legend-item">
			<div class="wb-legend-item__text">
				<div class="wb-legend-item__title">${item.title ?? 'Attività'}</div>
				<div class="wb-legend-item__desc">${(item.desc ?? '').replace(/\n/g, '<br>')}</div>
			</div>
			<span class="wb-legend-item__pts">${pts}</span>
		</div>`;
	}).join('');
}