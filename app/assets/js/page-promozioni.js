let prodotti = [];

function isFeatured(item) {
	return Number(item?.is_featured) === 1 || item?.is_featured === true;
}


function hasValidProductImage(item) {
	if (!item || !item.image) return false;
	const img = item.image;

	// se is_default true → placeholder
	if (img.is_default === true) return false;

	// se non c'è src valido → no img
	if (!img.src || !String(img.src).trim()) return false;

	return true;
}

function discountPct(item) {
	const reg = Number(item?.price_regular);
	const sale = Number(item?.price_sale);
	if (!item?.is_on_sale || !isFinite(reg) || reg <= 0 || !isFinite(sale) || sale < 0) return 0;
	return Math.max(0, Math.min(1, (reg - sale) / reg));
}

function sortPromosByImageAndDiscount(arr) {
	const featured = [];
	const normal = [];

	for (const item of arr) {
		(isFeatured(item) ? featured : normal).push(item);
	}

	const cmp = (a, b) => {
		const imgDiff = (hasValidProductImage(b) ? 1 : 0) - (hasValidProductImage(a) ? 1 : 0);
		if (imgDiff !== 0) return imgDiff;

		const dDiff = discountPct(b) - discountPct(a);
		if (dDiff !== 0) return dDiff;

		const aFinal = a.is_on_sale ? a.price_sale : a.price_regular;
		const bFinal = b.is_on_sale ? b.price_sale : b.price_regular;
		if (isFinite(aFinal) && isFinite(bFinal) && aFinal !== bFinal) return aFinal - bFinal;

		return String(a.name || '').localeCompare(String(b.name || ''));
	};

	featured.sort(cmp);
	normal.sort(cmp);

	return featured.concat(normal);
}

function createPromoCardFull(item) {
	const card = document.createElement('div');
	card.classList.add('product');
	card.classList.add('product-'+item.id);
	card.id = 'product-'+item.id;

	const img = createResponsiveImage(item.image, {class: 'promo-img'});

	const hasDescription = !!(item.description && item.description.trim());

	const showDiscount = item.is_on_sale && Number(item.price_sale) < Number(item.price_regular);
	const hidePrice = item.price_hidden === true;

	card.innerHTML = `
		<div class="product-name">${item.name}</div>
		${item.has_low_threshold ? '<div class="limited">Pochi pezzi disponibili</div>' : ''}
		${hidePrice ? '' : `
		<div class="price">
			${
				showDiscount
					? `<span class="old-price">${item.price_regular.toFixed(2)}€</span><span class="promo-price">${item.price_sale.toFixed(2)}€</span>`
					: `<span class="promo-price">${(item.is_on_sale ? item.price_sale : item.price_regular).toFixed(2)}€</span>`
			}
		</div>`}
		<div class="d-flex justify-content-end btn-ai-wrapper">
			<button type="button"
				class="btn btn-ai btn-ai--product"
				data-nome="${escapeAttr(item.name)}"
				data-id="${item.id}"
				aria-label="Chiedi all'AI"> 
				<img src="${dataStore.pharma.image_bot}" width="40" height="40" alt="" />		
			</button>
		</div>
		${hasDescription ? '<button class="accordion">Dettagli prodotto</button>' : ''}
		${hasDescription ? `<div class="panel" style="max-height:0; overflow:hidden;">${nl2br(item.description)}</div>` : ''}

		<button type="button"
			class="cta add-to-cart btn btn-primary w-100"
			data-add-to-cart
			data-id="${item.id}"
			aria-label="Aggiungi ${item.name} al carrello">
				Aggiungi al carrello
		</button>
	`;

	card.insertBefore(img, card.firstChild);
	return card;
}

function createPromoCardDashboard(item) {
	const div = document.createElement('div');
	div.className = 'grid-item product-card';

	const imgObj = typeof item.image === 'string' ? {src: item.image, alt: item.name} : item.image;
	const img = createResponsiveImage(imgObj, {class: 'promo-img'});

	const showDiscount = item.is_on_sale && Number(item.price_sale) < Number(item.price_regular);
	const hidePrice = item.price_hidden === true;

	div.innerHTML += `
		<a class="product-name" href="${AppURLs.page.promotions() + '?id=' + item.id}">${img.outerHTML}</a>
		<a class="product-name" href="${AppURLs.page.promotions() + '?id=' + item.id}">${item.name}</a>
		${hidePrice ? '' : `
		<div class="product-prices">
			${showDiscount ? `<s>€${item.price_regular.toFixed(2)}</s>` : ''}
			<span class="price">€${(item.is_on_sale ? item.price_sale : item.price_regular).toFixed(2)}€</span>
		</div>`}

		<button type="button"
			class="btn btn-primary"
			data-add-to-cart
			data-id="${item.id}"
			aria-label="Aggiungi ${item.name} al carrello"
		>
			Aggiungi
		</button>
	`;

	return div;
}

function setupAccordion() {
	document.querySelectorAll('.accordion').forEach((btn) => {
		btn.addEventListener('click', () => {
			btn.classList.toggle('active');
			const panel = btn.nextElementSibling;
			if (!panel) return;
			const currentMaxHeight = window.getComputedStyle(panel).maxHeight;
			panel.style.maxHeight = currentMaxHeight !== '0px' && currentMaxHeight !== 'none' ? '0' : panel.scrollHeight + 'px';
		});
	});
}

async function loadPromotions(options = {}) {
	const {limit = null, containerId = 'promo-list'} = options;

	try {
		const paramsUrl = new URLSearchParams(window.location.search);
		const tipo = paramsUrl.get('tipo');
		const params = new URLSearchParams();

		const isDashboard = containerId === 'promo-list';
		if (!isDashboard && typeof limit === 'number') {
			params.append('limit', limit);
		} else if (isDashboard) {
			params.append('limit', '100');
		}
		if (tipo) params.append('tipo', tipo);
		if (isDashboard) params.append('ref', 'home');

		const url = AppURLs.api.getPromos() + (params.toString() ? `?${params.toString()}` : '');
		const json = await appFetchWithToken(url);

		const prodottiData = json?.data?.products ?? [];

		const isFiltered = !!json?.data?.filtered;

		if (isFiltered) {
			document.body.classList.add('filtered-promos');
		}  else {
			document.body.classList.remove('filtered-promos');
		}

		if (!json.status || !Array.isArray(prodottiData)) {
			throw new Error(json.message || 'Formato dati promozioni non valido');
		}

		const container = document.getElementById(containerId);
		if (!container) {
			console.warn(`Contenitore #${containerId} non trovato`);
			return;
		}
		container.innerHTML = '';

		const prodottiLocali = [...prodottiData];
		prodotti = [...prodottiData];

		if (prodottiLocali.length === 0) {
			const noPromoBox = document.createElement('div');
			noPromoBox.className = 'no-promos';
			noPromoBox.innerHTML = `<p class="text-center">🎁 Nessuna promozione disponibile al momento</p>`;
			if (isDashboard) {
				noPromoBox.style.gridColumn = '1 / -1';
				noPromoBox.style.textAlign = 'center';
				noPromoBox.style.padding = '2rem';
				noPromoBox.style.fontSize = '1.1rem';
				noPromoBox.style.color = '#555';
			}
			container.appendChild(noPromoBox);
			document.dispatchEvent(new CustomEvent('loadPromotionsSuccess', {detail: json}));
			return json;
		}

		const ordinati = sortPromosByImageAndDiscount(prodottiLocali);
		const itemsToRender = typeof limit === 'number' ? ordinati.slice(0, limit) : ordinati;

		itemsToRender.forEach((item) => {
			const card = isDashboard ? createPromoCardDashboard(item) : createPromoCardFull(item);
			if(tipo && isFiltered) card.classList.add('selected');
			container.appendChild(card);
		});

		setupAddToCartDelegation();
		if (!isDashboard) setupAccordion();

		document.dispatchEvent(new CustomEvent('loadPromotionsSuccess', {detail: json}));
		return json;
	} catch (err) {
		handleError(err, 'Errore nel caricamento delle promozioni');
		document.dispatchEvent(new CustomEvent('loadPromotionsError', {detail: {error: err}}));
		throw err;
	}
}

document.addEventListener('appLoaded', () => {
	if (document.getElementById('promo-list')) {
		loadPromotions({limit: 6, containerId: 'promo-list'}).catch((err) => showToast(err.message || 'Errore promozioni', 'error'));
	}

	if (document.getElementById('promo-list-full')) {
		loadPromotions({containerId: 'promo-list-full'}).catch((err) => showToast(err.message || 'Errore promozioni', 'error'));
	}
});

async function submitSupportRequest(request) {
	if (!request || typeof request !== 'string' || !request.trim()) {
		const errMsg = 'Richiesta non valida';
		handleError(errMsg);
		document.dispatchEvent(new CustomEvent('supportRequestError', {detail: {error: errMsg}}));
		return {status: false, message: errMsg};
	}

	try {
		const res = await appFetchWithToken(AppURLs.api.requestPost(), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify({
				type: 'promo',
				request: request.trim(),
			}),
		});

		if (res.status === true) {
			document.dispatchEvent(new CustomEvent('supportRequestSuccess', {detail: res}));
			const input = document.querySelector('textarea');
			if (input) input.value = '';
			return res;
		} else {
			throw new Error(res.message || 'Errore dal server');
		}
	} catch (error) {
		handleError(error, 'Errore durante l’invio della richiesta.');
		document.dispatchEvent(new CustomEvent('supportRequestError', {detail: {error}}));
		return {status: false, message: error.message || 'Errore durante l’invio della richiesta.'};
	}
}

document.addEventListener('appLoaded', () => {
	aggiornaMese('promo-title', 'promo-subtitle');
	const submitBtn = document.querySelector('.cta-submit');
	if (submitBtn) {
		submitBtn.addEventListener('click', async () => {
			const textarea = document.querySelector('[name="custom_request"]');
			const request = textarea?.value.trim();
			if (!request) {
				showToast('Inserisci una richiesta valida', 'warning');
				return;
			}
			try {
				const json = await submitSupportRequest(request);
				if (json?.message) showToast(json.message, json.status ? 'warning' : 'error');
				if (json.status) {
					document.dispatchEvent(new CustomEvent('supportRequestSuccess', {detail: json}));
				} else {
					throw new Error(json.message || 'Errore generico');
				}
			} catch (err) {
				showToast(err.message || 'Si è verificato un errore. Riprova più tardi.', 'error');
				document.dispatchEvent(new CustomEvent('supportRequestError', {detail: {error: err}}));
			}
		});
	}
});

function setupAddToCartDelegation() {
	const roots = [document.getElementById('promo-list'), document.getElementById('promo-list-full')].filter(Boolean);
	if (roots.length === 0) return;

	roots.forEach((root) => {
		if (root.__addToCartBound) return;
		root.__addToCartBound = true;

		root.addEventListener(
			'click',
			(e) => {
				const btn = e.target.closest('[data-add-to-cart]');
				if (!btn || !root.contains(btn)) return;

				e.preventDefault();
				if (btn.disabled) return;
				btn.disabled = true;

				const id = btn.dataset.id;
				const prodotto = prodotti?.find?.((p) => String(p.id) === String(id));
				if (!prodotto) {
					console.warn('Prodotto non trovato per id:', id);
					btn.disabled = false;
					return;
				}

				const item = {
					id: prodotto.id,
					name: prodotto.name,
					description: prodotto.description,
					image: prodotto.image,
					price: prodotto.price,
					price_sale: prodotto.price_sale,
					price_regular: prodotto.price_regular,
				};

				CartUtils.addToCart(item);
				document.body.classList.add('has-cart');
				showToast('Aggiunto al carrello', 'success');

				setTimeout(() => {
					btn.disabled = false;
				}, 250);
			},
			{passive: false}
		);
	});
}

/* function openPromoByUrl(){
	const params = new URLSearchParams(window.location.search);

	if (params.has('id')) {
		const promoId = params.get('id');
		if( ! promoId ) return;
		const elPromo = document.querySelector('#product-'+promoId+'')
		if( ! elPromo ) return;
		elPromo.scrollIntoView({ behavior: "smooth" });
		const accordion = elPromo.querySelector('.accordion');
		if (accordion) accordion.click();
	}
} */

//document.addEventListener('loadPromotionsSuccess', openPromoByUrl );

function showPromoByUrl() {
	const params = new URLSearchParams(window.location.search);
	if (!params.has('id')) return;

	const promoId = params.get('id');
	if (!promoId) return;

	const container = document.getElementById('promo-list-full');
	if (!container) return;

	const allPromos = container.querySelectorAll('.product[id^="product-"]');
	const selected = container.querySelector('#product-' + promoId);
	if (!selected) return;

	document.body.classList.add('filtered-promos');
	selected.classList.add('selected');

	const acc = selected.querySelector('.accordion');
	if( acc ) acc.click();
}


document.addEventListener('loadPromotionsSuccess', showPromoByUrl);
