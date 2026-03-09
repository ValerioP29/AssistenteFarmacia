/**
 * page-prenotazioni.js — ReservationForm
 *
 * Flusso suggerimenti prodotti:
 *  - Alla init: carica suggerimenti di default per la modalità attiva
 *  - Selezione prodotto (TomSelect): aggiorna i suggerimenti in base ai tag del prodotto
 *  - Deselect / clear TomSelect: ricarica suggerimenti di default (non svuota la lista)
 *  - Aggiunta al carrello: ricarica i suggerimenti escludendo i prodotti già aggiunti
 *  - Cambio modalità (con/senza ricetta): mostra il blocco corretto e carica i suggerimenti
 *  - Filtro per tag (select): ricarica con il tag selezionato
 */

// Fallback: se escapeHtml non è definita (ordine script / reload), non deve mai crashare TomSelect
if (typeof window.escapeHtml !== 'function') {
	window.escapeHtml = function (value) {
		const s = String(value ?? '');
		return s
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	};
}

const ReservationForm = {
	form: null,
	picker: null,
	cart: {},
	ts: null,
	table: null,
	currProd: null,
	selectedProduct: null,
	sending: false,

	get relatedTagsPreset() {
		return window.ProductTagsTaxonomy?.getUiPreset() ?? [
			{ value: '', label: 'Seleziona una categoria' },
		];
	},

	/** Stato per i due blocchi suggerimenti: 0 = senza ricetta, 1 = con ricetta */
	relatedState: {
		0: { lastTag: '', lastSeed: '', products: [], didInit: false },
		1: { lastTag: '', lastSeed: '', products: [], didInit: false },
	},

	/** Flag per evitare che onClear azzeri i suggerimenti durante un addProduct */
	_suppressRelatedClear: false,

	relatedProductAddedListenerAttached: false,
	isInitialized: false,
	suborderListenersBoundForm: null,
	_warnedNoPharmaId: false,
	_tsElRef: null,

	// ─────────────────────────────────────────────
	// INIT & DOM
	// ─────────────────────────────────────────────

	ensureDomRefs() {
		const form = document.querySelector('#form-reservation');
		if (!form) return { ok: false, formChanged: false };
		const formChanged = !!(this.form && this.form !== form);
		this.form  = form;
		this.picker = document.querySelector('#pickup');
		this.table  = document.querySelector('#product-summary');
		return { ok: true, formChanged };
	},

		getPharmaId() {
		const toInt = (v) => {
			if (v === null || v === undefined) return 0;
			const n = parseInt(String(v), 10);
			return Number.isFinite(n) && n > 0 ? n : 0;
		};

		const candidates = [
			dataStore?.pharma?.id,
			dataStore?.pharmacy?.id,
			dataStore?.pharmacy_id,
			dataStore?.pharma_id,
			window?.dataStore?.pharma?.id,
			window?.dataStore?.pharmacy?.id,
			window?.pharma_id,
			window?.pharmacy_id,
			document.querySelector('meta[name="pharma-id"]')?.content,
			document.querySelector('[data-pharma-id]')?.dataset?.pharmaId,
			localStorage.getItem('pharma_id'),
			localStorage.getItem('pharmacy_id'),
		];

		for (const c of candidates) {
			const id = toInt(c);
			if (id) return id;
		}
		return 0;
	},
	

	buildApiUrl(pathOrUrl) {
		const raw = (pathOrUrl ?? '').toString();
		if (!raw) return null;

		// 1) prova come URL assoluta
		try { return new URL(raw); } catch (e) {}

		// 2) prova relativa rispetto all'origine corrente
		try { return new URL(raw, window.location.origin); } catch (e) {}

		// 3) ultima spiaggia: rispetto alla pagina
		try { return new URL(raw, window.location.href); } catch (e) {}

		return null;
	},

	destroyTomSelect() {
		try { this.ts?.destroy?.(); } catch (e) { console.warn('destroyTomSelect fallito', e); }
		this.ts = null;
		this.currProd = null;
		this.selectedProduct = null;
		this._tsElRef = null;
	},

	canonicalizeTagValue(rawTag = '') {
		// Normalizza formato slug (lowercase, underscore, caratteri ammessi)
		const normalized = String(rawTag || '')
			.trim()
			.toLowerCase()
			.replace(/[-\s]+/g, '_')
			.replace(/_+/g, '_')
			.replace(/[^a-z0-9_]/g, '')
			.replace(/^_+|_+$/g, '');
		// Risolve alias legacy → slug canonico (es. "dermocosmetica" → "dermocosmesi")
		return window.ProductTagsTaxonomy?.canonicalize(normalized) ?? normalized;
	},

	isAllowedRelatedTag(tag = '') {
		const canonical = this.canonicalizeTagValue(tag);
		if (!canonical) return false;
		return window.ProductTagsTaxonomy?.isKnown(canonical) ?? false;
	},

	populateRelatedTagSelect(selectEl) {
		if (!selectEl) return;
		selectEl.innerHTML = '';
		this.relatedTagsPreset.forEach(({ value, label }) => {
			const opt = document.createElement('option');
			opt.value = value;
			opt.textContent = label;
			selectEl.appendChild(opt);
		});
	},


	init() {
		const { ok, formChanged } = this.ensureDomRefs();
		if (!ok) return;

		if (formChanged) {
			this.destroyTomSelect();
			this.isInitialized = false;
			this.suborderListenersBoundForm = null;
			this.selectedProduct = null;
			this.relatedState[0] = { lastTag: '', lastSeed: '', products: [], didInit: false };
			this.relatedState[1] = { lastTag: '', lastSeed: '', products: [], didInit: false };
			this._warnedNoPharmaId = false;
		}

		if (this.isInitialized && !formChanged) return;
		this.isInitialized = true;

		if (this.suborderListenersBoundForm !== this.form) {
			this.form.querySelectorAll('input[name="suborder-type"]').forEach((radio) => {
				radio.addEventListener('change', () => this.updateSubOrderView());
			});
			this.suborderListenersBoundForm = this.form;
		}

		this.initRelatedProducts();
		this.attachReservationProductAddedListener();
		this.initTomSelect();
		this.resetForm();

		document.dispatchEvent(new CustomEvent('reservationFormLoaded'));
	},

	openCalendarPicker() {
		const picker = document.querySelector('#pickup');
		picker?.showPicker?.();
		picker?.focus?.();
	},

	// ─────────────────────────────────────────────
	// FORM STATE
	// ─────────────────────────────────────────────

	togglePrescription() {
		const toggle      = document.querySelector('#res-prod-toggle-prescription');
		const uploadBlock = document.querySelector('#upload-prescription-block');
		const codeBlock   = document.querySelector('.prescription-method-nre');
		if (!toggle) return;
		if (toggle.checked) {
			uploadBlock?.classList.add('d-none');
			codeBlock?.classList.remove('d-none');
		} else {
			codeBlock?.classList.add('d-none');
			uploadBlock?.classList.remove('d-none');
		}
	},

	disableForm() {
		this.sending = true;
		document.querySelector('#form-reservation button[type="submit"]').disabled = true;
	},

	enableForm() {
		this.sending = false;
		document.querySelector('#form-reservation button[type="submit"]').disabled = false;
	},

	resetForm() {
		this.resetSubFormProduct();
		this.resetProductsTable();
		this.hideProductsTable();
		document.querySelector('#form-reservation').reset();
		this.resetCartData();
		this.togglePrescription();
		this.updateSubOrderView();
		document.dispatchEvent(new CustomEvent('reservationFormReset'));
	},

	resetSubFormProduct() {
		const form = document.querySelector('#form-reservation');
		if (!form) return;

		this.ts?.clear?.();
		form.querySelector('#res-prod-qty').value = '1';
		this.clearFileSelected(false);

		const checkboxPresc = form.querySelector('#res-prod-toggle-prescription');
		if (checkboxPresc) {
			checkboxPresc.checked = false;
			checkboxPresc.dispatchEvent(new Event('change', { bubbles: true }));
		}
		form.querySelector('#res-prod-cf').value  = '';
		form.querySelector('#res-prod-nre').value = '';

		form.querySelector('.form-group--ts .ts-container')?.classList.remove('d-none');
		const detailsDiv = document.querySelector('#product-details');
		if (detailsDiv) detailsDiv.innerHTML = '';
	},

	clearFileSelected(showError = false) {
		const input = document.querySelector('#res-prod-prescription');
		if (!input) return;
		input.value = '';
		input.focus();
		if (showError) showToast?.('File ricetta rimosso', 'info');
	},

	// ─────────────────────────────────────────────
	// SUBORDER TYPE (con / senza ricetta)
	// ─────────────────────────────────────────────

	getSubOrderChecked() {
		if (!this.form) return 0;
		const checked = this.form.querySelector('input[name="suborder-type"]:checked');
		return checked ? parseInt(checked.value, 10) : 0;
	},

	setSubOrderType(type) {
		this.form?.querySelector('#suborder-type--' + type)?.click();
	},

	toggleSubOrderType() {
		this.form?.querySelector('input[name="suborder-type"]:not(:checked)')?.click();
	},

	updateSubOrderView() {
		const { ok } = this.ensureDomRefs();
		if (!ok) return;
		const val = this.getSubOrderChecked();

		// Mostra / nasconde i gruppi UI
		this.form.querySelectorAll('.suborder-group').forEach((el) => {
			el.classList.toggle('d-none', !el.classList.contains('suborder-group--' + val));
		});

		// Nasconde entrambi i blocchi related, poi mostra quello corretto
		document.querySelector('#related-products-block')?.classList.add('d-none');
		document.querySelector('#related-products-block-rx')?.classList.add('d-none');

		this.showRelatedSection(val);

		// Se c'è già un prodotto selezionato nella TomSelect (mode 0), aggiorna i suggerimenti
		if (this.selectedProduct && val === 0) {
			this.refreshSuggestionsFromSelectedProduct(this.selectedProduct, 0);
			return;
		}

		// Prima apertura della modalità: carica suggerimenti di default
		if (!this.relatedState[val].didInit) {
			this.relatedState[val].didInit = true;
			this.loadRelatedProducts({ tag: '', seedName: '' }, val);
		}
	},

	// ─────────────────────────────────────────────
	// SUGGERIMENTI PRODOTTI (related)
	// ─────────────────────────────────────────────

	initRelatedProducts() {
		const selectNoRx = document.querySelector('#related-tag-select');
		const selectRx = document.querySelector('#related-seed-tag-prescription');

		this.populateRelatedTagSelect(selectNoRx);
		this.populateRelatedTagSelect(selectRx);

		selectNoRx?.addEventListener('change', () => {
			const selectedTag = this.canonicalizeTagValue(selectNoRx.value || '');
			this.showRelatedSection(0);
			this.loadRelatedProducts({ tag: selectedTag, seedName: '' }, 0);
		});

		selectRx?.addEventListener('change', () => {
			const selectedTag = this.canonicalizeTagValue(selectRx.value || '');
			this.showRelatedSection(1);
			this.loadRelatedProducts({ tag: selectedTag, seedName: '' }, 1);
		});
	},

	attachReservationProductAddedListener() {
		if (this.relatedProductAddedListenerAttached) return;
		this.relatedProductAddedListenerAttached = true;

		document.addEventListener('reservationProductAdded', (e) => {
			const p = e?.detail || {};
			const mode = Number.isInteger(p?.mode) ? p.mode : ReservationForm.getSubOrderChecked();

			// Se l'aggiunta arriva da una card suggerita, rimuove solo il prodotto cliccato
			// dalla lista visibile e prova a rimpiazzarlo senza alterare le altre card.
			if (p?.source === 'related-card' && p?.id) {
				ReservationForm.replaceAddedRelatedProduct(p.id, mode, {
					tag: ReservationForm.pickRelatedTagFromProduct(p),
					seedName: p.name || '',
				});
				return;
			}

			// Ricarica i suggerimenti basandosi sul prodotto appena aggiunto,
			// escludendo automaticamente i prodotti già nel carrello.
			ReservationForm.showRelatedSection(mode);
			ReservationForm.loadRelatedProducts(
				{
					tag: ReservationForm.pickRelatedTagFromProduct(p),
					seedName: p.name || '',
				},
				mode
			);
		});
	},

	isProductAlreadyInCart(productId) {
		const parsedId = parseInt(productId, 10);
		if (Number.isNaN(parsedId) || parsedId <= 0) return false;
		return (this.cart?.products || []).some((entry) => {
			const id = parseInt(entry?.product?.id, 10);
			return !Number.isNaN(id) && id === parsedId;
		});
	},

	replaceAddedRelatedProduct(productId, mode = null, fallbackCriteria = {}) {
		const parsedId = parseInt(productId, 10);
		const effectiveMode = [0, 1].includes(mode) ? mode : this.getSubOrderChecked();
		if (Number.isNaN(parsedId) || parsedId <= 0) return;

		const currentVisible = Array.isArray(this.relatedState[effectiveMode]?.products)
			? this.relatedState[effectiveMode].products
			: [];
		const visibleWithoutAdded = this.filterUniqueRelatedProducts(
			currentVisible.filter((item) => parseInt(item?.id, 10) !== parsedId)
		);

		const preservedVisible = visibleWithoutAdded.filter((item) => !this.isProductAlreadyInCart(item?.id));
		const missingCount = Math.max(0, 3 - preservedVisible.length);
		if (missingCount === 0) {
			this.renderRelatedProducts(preservedVisible, effectiveMode);
			return;
		}

		const state = this.relatedState[effectiveMode] || {};
		const fallbackTag = this.canonicalizeTagValue(
			typeof fallbackCriteria === 'object' ? (fallbackCriteria?.tag || '') : ''
		);
		const fallbackSeed = typeof fallbackCriteria === 'object' ? String(fallbackCriteria?.seedName || '').trim() : '';
		const criteria = {
			tag: state.lastTag || fallbackTag || '',
			seedName: state.lastSeed || fallbackSeed || '',
		};
		if (!state.lastTag && criteria.tag) this.relatedState[effectiveMode].lastTag = criteria.tag;
		if (!state.lastSeed && criteria.seedName) this.relatedState[effectiveMode].lastSeed = criteria.seedName;

		const extraExcludeIds = [
			parsedId,
			...preservedVisible.map((item) => parseInt(item?.id, 10)),
		].filter((id) => !Number.isNaN(id) && id > 0);

		this.fetchRelatedProducts(
			criteria,
			effectiveMode,
			missingCount,
			extraExcludeIds
		)
			.then((replacements) => {
				const merged = this.filterUniqueRelatedProducts([
					...preservedVisible,
					...replacements,
				]).slice(0, 3);
				this.renderRelatedProducts(merged, effectiveMode);
			})
			.catch(() => {
				this.renderRelatedProducts(preservedVisible, effectiveMode);
			});
	},

	showRelatedSection(mode = 0) {
		this.getRelatedElementsByMode(mode).rootEl?.classList.remove('d-none');
	},

	/** Avvia il refresh dei suggerimenti partendo dal prodotto selezionato */
	refreshSuggestionsFromSelectedProduct(product = null, mode = null) {
		const effectiveMode = mode === null ? this.getSubOrderChecked() : mode;

		if (!product) {
			// Nessun prodotto: ricarica i default (non svuota la lista)
			this.loadRelatedProducts({ tag: '', seedName: '' }, effectiveMode);
			return;
		}

		const norm = this.normalizeProductForSelection(product);
		this.showRelatedSection(effectiveMode);
		this.loadRelatedProducts({ tag: norm.tag || '', seedName: norm.name || '' }, effectiveMode);
	},

	getRelatedElementsByMode(mode = 0) {
		if (mode === 1) {
			return {
				listEl:  document.querySelector('#related-products-list-rx'),
				emptyEl: document.querySelector('#related-products-empty-rx'),
				blockEl: document.querySelector('#related-products-block-rx'),
				rootEl:  document.querySelector('#related-products-block-rx'),
			};
		}
		return {
			listEl:  document.querySelector('#related-products-list'),
			emptyEl: document.querySelector('#related-products-empty'),
			blockEl: document.querySelector('#related-products-block'),
			rootEl:  document.querySelector('#related-products-block'),
		};
	},

	setRelatedLoading(mode = 0, isLoading = false) {
		const { blockEl, listEl, emptyEl } = this.getRelatedElementsByMode(mode);
		if (!blockEl || !listEl || !emptyEl) return;
		blockEl.classList.toggle('is-loading', !!isLoading);
		if (isLoading) {
			emptyEl.classList.add('d-none');
			listEl.innerHTML = '';
		}
	},

	getSelectedProductIdsForRelated() {
		return (this.cart?.products || [])
			.map((p) => p?.product?.id)
			.filter((id) => {
				const n = parseInt(id, 10);
				return !Number.isNaN(n) && n > 0;
			})
			.map((id) => parseInt(id, 10));
	},

	filterUniqueRelatedProducts(products = []) {
		const seen = new Set();
		return (Array.isArray(products) ? products : []).filter((item) => {
			const id = parseInt(item?.id, 10);
			if (Number.isNaN(id) || id <= 0 || seen.has(id)) return false;
			seen.add(id);
			return true;
		});
	},

	buildRelatedSuggestionsUrl(criteria = {}, mode = 0, limit = 3, extraExcludeIds = []) {
		const pharmaId = this.getPharmaId();
		if (!pharmaId) return '';

		const rawTag = typeof criteria === 'string' ? criteria : (criteria?.tag || '');
		const rawSeed = typeof criteria === 'object' ? (criteria?.seedName || '') : '';
		const tag = rawTag.trim().toLowerCase();
		const seed = rawSeed.trim();

		const url = this.buildApiUrl(AppURLs?.api?.productSuggestions?.());
		if (!url) return '';

		url.searchParams.set('pharma_id', pharmaId);
		url.searchParams.set('related_mode', '1');
		url.searchParams.set('limit', String(Math.max(1, parseInt(limit, 10) || 3)));
		if (tag) url.searchParams.set('related_tag', tag);
		else if (seed) url.searchParams.set('related_seed', seed);

		const excludeIds = new Set(this.getSelectedProductIdsForRelated());
		extraExcludeIds.forEach((id) => {
			const parsed = parseInt(id, 10);
			if (!Number.isNaN(parsed) && parsed > 0) excludeIds.add(parsed);
		});
		if (excludeIds.size) url.searchParams.set('exclude_ids', Array.from(excludeIds).join(','));

		return url.toString();
	},

	fetchRelatedProducts(criteria = {}, mode = null, limit = 3, extraExcludeIds = []) {
		const effectiveMode = mode === null ? this.getSubOrderChecked() : mode;
		if (![0, 1].includes(effectiveMode)) return Promise.resolve([]);

		const rawTag = typeof criteria === 'string' ? criteria : (criteria?.tag || '');
		const rawSeed = typeof criteria === 'object' ? (criteria?.seedName || '') : '';
		const tag = rawTag.trim().toLowerCase();
		const seedName = rawSeed.trim();

		const normalize = (data) => this.filterUniqueRelatedProducts(
			this.extractProductsList(data)
				.map((item) => this.normalizeRelatedProduct(item))
				.filter((item) => item.id && item.name)
		).slice(0, Math.max(1, parseInt(limit, 10) || 3));

		const urlPrimary = this.buildRelatedSuggestionsUrl({ tag, seedName }, effectiveMode, limit, extraExcludeIds);
		if (!urlPrimary) {
			this.relatedState[effectiveMode].didInit = false;
			this._schedulePharmaRetry(effectiveMode);
			return Promise.resolve([]);
		}

		return appFetchWithToken(urlPrimary, { method: 'GET' })
			.then((data) => {
				const products = normalize(data);
				if (products.length > 0 || !tag) return products;

				const fallbackUrl = this.buildRelatedSuggestionsUrl({ tag: '', seedName }, effectiveMode, limit, extraExcludeIds);
				if (!fallbackUrl) return [];
				return appFetchWithToken(fallbackUrl, { method: 'GET' })
					.then((fallbackData) => normalize(fallbackData))
					.catch(() => []);
			});
	},

	loadRelatedProducts(criteria = {}, mode = null) {
		if (mode === null) mode = this.getSubOrderChecked();
		if (![0, 1].includes(mode)) return;

		const rawTag  = typeof criteria === 'string' ? criteria : (criteria?.tag || '');
		const rawSeed = typeof criteria === 'object'  ? (criteria?.seedName || '') : '';
		const tag  = rawTag.trim().toLowerCase();
		const seed = rawSeed.trim();

		// Aggiorna lo stato locale
		this.relatedState[mode].lastTag  = tag;
		this.relatedState[mode].lastSeed = seed;
		this.setRelatedLoading(mode, true);

		const pharmaId = this.getPharmaId();
		if (!pharmaId) {
			// pharmaId non ancora disponibile (race condition con app.js):
			// rimette didInit = false e schedula un retry tramite polling.
			this.relatedState[mode].didInit = false;
			this.setRelatedLoading(mode, false);
			this._schedulePharmaRetry(mode);
			return;
		}

		this.fetchRelatedProducts({ tag, seedName: seed }, mode, 3)
			.then((products) => {
				this.setRelatedLoading(mode, false);
				this.renderRelatedProducts(products, mode);
			})
			.catch(() => {
				this.setRelatedLoading(mode, false);
				this.renderRelatedProducts([], mode);
			});
	},

	extractProductsList(data) {
		if (Array.isArray(data?.data?.products)) return data.data.products;
		if (Array.isArray(data?.data?.items))    return data.data.items;
		if (Array.isArray(data?.data?.data))     return data.data.data;
		if (Array.isArray(data?.products))        return data.products;
		if (Array.isArray(data?.items))           return data.items;
		if (Array.isArray(data?.data))            return data.data;
		return [];
	},

	normalizeRelatedProduct(item = {}) {
		const productId = item?.id ?? item?.product_id ?? item?.prod_id ?? null;
		const parsedId  = parseInt(productId, 10);
		const priceOrig = item?.price_original ?? item?.price ?? null;
		const priceDisc = item?.price_discounted ?? item?.sale_price ?? item?.discounted_price ?? null;
		const rawDisc   = item?.has_discount ?? item?.related_has_discount ?? 0;
		const hasDiscountFlag  = rawDisc === true || rawDisc === 1 || rawDisc === '1';
		const hasDiscountPrice = priceDisc !== null && priceDisc !== undefined && String(priceDisc).trim() !== '';
		return {
			...item,
			id:              Number.isNaN(parsedId) ? null : parsedId,
			name:            String(item?.name || item?.product_name || item?.label || '').trim(),
			sku:             item?.sku || item?.code || '',
			image:           item?.image || item?.thumbnail || '',
			price_original:  priceOrig,
			price_discounted: priceDisc,
			has_discount:    hasDiscountFlag || hasDiscountPrice,
		};
	},

	normalizeProductForSelection(product = {}) {
		const firstTag = Array.isArray(product?.tags) && product.tags.length
			? String(product.tags[0] || '').trim()
			: (typeof product?.tags === 'string' ? product.tags.trim().split(',')[0] : '');
		const tag = String(product?.tag || firstTag || '').trim().toLowerCase();
		return {
			id:       product?.id ?? product?.product_id ?? product?.prod_id ?? null,
			name:     String(product?.name || product?.product_name || product?.label || '').trim(),
			tag:      tag || this.inferRelatedTagFromName(String(product?.name || '').trim()),
			tags:     product?.tags || null,
			category: product?.category || product?.category_name || null,
			sku:      product?.code || product?.sku || null,
		};
	},

	pickRelatedTagFromProduct(product = {}) {
		if (Array.isArray(product?.tags) && product.tags.length)
			return String(product.tags[0] || '').trim().toLowerCase();
		if (typeof product?.tags === 'string' && product.tags.trim())
			return product.tags.trim().toLowerCase();
		return this.inferRelatedTagFromName(String(product?.name || '').trim());
	},

	inferRelatedTagFromName(name = '') {
		return window.ProductTagsTaxonomy?.inferTag(name) ?? '';
	},

	// ─────────────────────────────────────────────
	// RENDERING SUGGERIMENTI
	// ─────────────────────────────────────────────

	renderRelatedProducts(products = [], mode = 0) {
		const { listEl, emptyEl } = this.getRelatedElementsByMode(mode);
		if (!listEl || !emptyEl) return;

		const safe = Array.isArray(products) ? products.slice(0, 3) : [];
		this.relatedState[mode].products = safe;

		listEl.innerHTML = '';
		if (safe.length === 0) {
			emptyEl.classList.remove('d-none');
			return;
		}
		emptyEl.classList.add('d-none');
		safe.forEach((item) => listEl.appendChild(this.createRelatedProductCard(item)));
	},

	formatCurrency(value) {
		const v = parseFloat(value);
		if (Number.isNaN(v) || v <= 0) return '';
		return new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' }).format(v);
	},

	formatRelatedPrice(item) {
		const orig = item?.price_original ?? item?.price;
		const disc = item?.price_discounted ?? item?.sale_price;
		const hasDis = !!item?.has_discount && disc !== null && disc !== undefined && disc !== '';
		const fo = this.formatCurrency(orig);
		const fd = this.formatCurrency(disc);
		if (hasDis && fo && fd) return { hasDiscount: true, original: fo, discounted: fd };
		return { hasDiscount: false, single: fd || fo };
	},

	getRelatedPlaceholderImage() {
		return AppURLs.api.base + '/uploads/images/placeholder-product.jpg';
	},

	resolveRelatedProductImage(imagePath) {
		if (!imagePath) return this.getRelatedPlaceholderImage();
		if (imagePath.startsWith('http') || imagePath.startsWith('/')) return imagePath;
		if (imagePath.startsWith('api/')) return `../${imagePath}`;
			try {
				return new URL(imagePath, `${AppURLs?.panel?.base || window.location.origin}/`).toString();
			} catch (e) {
				return this.getRelatedPlaceholderImage();
			}	
		},

	createRelatedProductCard(item) {
		const card = document.createElement('article');
		card.className = 'related-product-card';
		card.dataset.relatedProductId = String(item?.id ?? '');
		const fp  = this.formatRelatedPrice(item);
		const src = this.resolveRelatedProductImage(item?.image || '');
		const isAdded = this.isProductAlreadyInCart(item?.id);
		const itemName = item?.name || 'Prodotto';

		card.innerHTML = `
			<div class="related-product-card__image-wrap">
				<img class="related-product-card__image"
					src="${escapeHtml(src)}"
					alt="${escapeHtml(item.name || 'Prodotto suggerito')}"
					loading="lazy" />
			</div>
			<div class="related-product-card__content">
				<div class="related-product-card__name">${escapeHtml(itemName)}</div>
				<div class="related-product-card__meta">
				${fp?.hasDiscount
					? `<div class="related-product-card__price-row">
						<span class="related-product-card__price--original">${escapeHtml(fp.original)}</span>
						<span class="related-product-card__price--discounted">${escapeHtml(fp.discounted)}</span>
					   </div>`
					: (fp?.single ? `<div class="related-product-card__price">${escapeHtml(fp.single)}</div>` : '')}
				</div>
				<button class="btn btn-primary related-product-card__cta" type="button" aria-label="${escapeHtml((isAdded ? 'Prodotto già aggiunto: ' : 'Aggiungi prodotto: ') + itemName)}" ${isAdded ? 'disabled' : ''}>${isAdded ? 'Aggiunto' : 'Aggiungi'}</button>
			</div>`;

		// Fallback immagine
		const imgEl = card.querySelector('.related-product-card__image');
		imgEl?.addEventListener('error', () => {
			if (imgEl.dataset.fallbackApplied === '1') return;
			imgEl.dataset.fallbackApplied = '1';
			imgEl.onerror = null;
			imgEl.src = this.getRelatedPlaceholderImage();
		});

		// CTA — aggiunge il prodotto suggerito al carrello
		card.querySelector('button')?.addEventListener('click', () => {
			if (this.isProductAlreadyInCart(item?.id)) {
				this.replaceAddedRelatedProduct(item?.id, this.getSubOrderChecked());
				return;
			}

			const basePrice = item.price_original ?? item.price;
			const hasPrice  = basePrice !== null && basePrice !== undefined && basePrice !== '';
			const hasDiscP  = item.has_discount && item.price_discounted !== null && item.price_discounted !== undefined && item.price_discounted !== '';

			const relatedData = {
				type:    0,
				product: {
					id:        item.id,
					name:      item.name,
					code:      item.sku || 'N/A',
					price:     item.price_original ?? item.price ?? null,
					sale_price: item.price_discounted ?? item.sale_price ?? null,
					thumbnail: src,
					tags:      item.tags ?? null,
					category:  item.category ?? null,
				},
				name:  item.name,
				code:  item.sku || 'N/A',
				price: hasDiscP ? item.price_discounted : (hasPrice ? basePrice : null),
				qty:   1,
			};

			if (!this.currProductIsValid(relatedData, true)) return;

			// Sopprime il clear dei suggerimenti: addProduct chiama resetSubFormProduct
			// che a sua volta chiama ts.clear() → onClear → renderRelatedProducts([]).
			// Il flag evita quel reset; i suggerimenti verranno aggiornati dall'evento.
			this._suppressRelatedClear = true;
			this.addProduct(relatedData, {
				source: 'related-card',
				mode: this.getSubOrderChecked(),
			});
			this._suppressRelatedClear = false;

			showToast?.('Suggerimento aggiunto', 'success');
		});

		return card;
	},

	// ─────────────────────────────────────────────
	// TOM SELECT
	// ─────────────────────────────────────────────

	// ─────────────────────────────────────────────
	// RETRY quando pharmaId non è ancora disponibile
	// ─────────────────────────────────────────────

	_pharmaRetryTimer: null,
	_pharmaRetryCount: 0,
	_pharmaRetryMax: 20,   // 20 × 500ms = 10 secondi massimo
	_pharmaRetryMs: 500,

	/**
	 * Schedula un retry per loadRelatedProducts e initTomSelect.
	 * Usa polling con backoff lineare. Si ferma non appena pharmaId è disponibile.
	 */
	_schedulePharmaRetry(mode) {
		if (this._pharmaRetryTimer) return; // già in attesa

		this._pharmaRetryCount = 0;
		const attempt = () => {
			this._pharmaRetryTimer = null;
			const pharmaId = this.getPharmaId();

			if (pharmaId) {
				console.info('ReservationForm: pharmaId disponibile (' + pharmaId + '), carico i suggerimenti.');
				this._pharmaRetryCount = 0;

				// Carica i suggerimenti per la modalità corrente
				const currentMode = this.getSubOrderChecked();
				const { lastTag, lastSeed } = this.relatedState[currentMode];
				this.loadRelatedProducts({ tag: lastTag || '', seedName: lastSeed || '' }, currentMode);

				// Ripristina la TomSelect se l'elemento è pronto
				this.initTomSelect();
				return;
			}

			this._pharmaRetryCount++;
			if (this._pharmaRetryCount >= this._pharmaRetryMax) {
				console.warn('ReservationForm: pharmaId non disponibile dopo ' + this._pharmaRetryMax + ' tentativi. Abort.');
				return;
			}
			this._pharmaRetryTimer = setTimeout(attempt, this._pharmaRetryMs);
		};

		this._pharmaRetryTimer = setTimeout(attempt, this._pharmaRetryMs);
	},

	getTs() { return this.ts; },

	deselectProductSelected() {
		this.ts?.clear?.();
		document.querySelector('#form-reservation .form-group--ts .ts-container')?.classList.remove('d-none');
		const detailsDiv = document.querySelector('#product-details');
		if (detailsDiv) detailsDiv.innerHTML = '';
	},

	initTomSelect() {
    this.ensureDomRefs();
    if (!window.TomSelect) return;

    const el = document.querySelector('#product-name');
    if (!el) return;

    if (el.tomselect) {
        try { el.tomselect.destroy(); } catch(e) {}
    }
    this.ts = null;
    this._tsElRef = null;

    this.ts = new TomSelect('#product-name', {
        maxItems:       1,
        dropdownParent: 'body',
        valueField:     'id',
        labelField:     'name',
        searchField:    ['name', 'code'],
        options:        [],
        items:          [],
        persist:        false,
        loadThrottle:   300,

        load: (query, callback) => {
			if (!query || query.length < 3) return callback([]);

			const pharmaId = this.getPharmaId();
			if (!pharmaId) {
				this._schedulePharmaRetry(this.getSubOrderChecked());
				return callback([]);
			}

			// Costruisci la URL come stringa diretta invece di usare searchParams
			const base = AppURLs.api.productSuggestions();
			const fullUrl = `${base}?search=${encodeURIComponent(query)}&pharma_id=${pharmaId}&limit=20`;
			
			console.log('URL:', fullUrl); // DEBUG

			appFetchWithToken(fullUrl, { method: 'GET' })
				.then((data) => {
					console.log('API risposta:', data);
					const rawList = this.extractProductsList(data);
					if (!Array.isArray(rawList) || !rawList.length) return callback([]);
					callback(rawList.map((p) => ({
						id:         p.id || p.product_id || p.prod_id,
						name:       p.name || p.label || p.product_name || '',
						code:       p.sku || p.code || 'N/A',
						tag:        p.tag || '',
						tags:       p.tags || p.related_tags || p.tag || null,
						category:   p.category || p.category_name || null,
						price:      p.price ?? 'N/A',
						sale_price: p.sale_price ?? null,
						quantity:   p.num_items || '',
						thumbnail:  this.resolveRelatedProductImage(p.image || ''),
					})));
				})
				.catch((err) => { console.error('fetch fallita:', err); callback([]); });
		},
        create: (input) => {
            const prod = {
                id:         input,
                name:       input,
                code:       'NUOVO',
                thumbnail:  AppURLs.api.base + '/uploads/images/placeholder-product.jpg',
                price:      null,
                quantity:   null,
                sale_price: null,
            };
            document.dispatchEvent(new CustomEvent('productSuggestionCreated', { detail: prod }));
            return prod;
        },

        render: {
            option(item, escape) {
                if (item.code === 'NUOVO') {
                    return `<div class="option" style="display:flex;align-items:center;gap:10px;">
                        <div style="display:flex;flex-direction:column;">
                            <span><strong>${escape(item.name)}</strong> <span class="badge bg-warning text-dark">Nuovo</span></span>
                            <small style="color:#999;">Prodotto inserito manualmente</small>
                        </div></div>`;
                }
                const priceHtml = item.sale_price
                    ? `<span style="text-decoration:line-through;color:#999;">€${escape(String(item.price))}</span> <strong style="color:green;">€${escape(String(item.sale_price))}</strong>`
                    : `€${escape(String(item.price ?? ''))}`;
                const meta = [];
                if (item.code) meta.push(`Codice: ${escapeHtml(item.code)}`);
                if (parseFloat(item.price)) meta.push(`Prezzo: ${priceHtml}`);
                return `<div class="option" style="display:flex;align-items:center;gap:10px;">
                    ${item.thumbnail ? `<img src="${item.thumbnail}" style="width:24px;height:24px;margin-right:8px;" />` : ''}
                    <div style="display:flex;flex-direction:column;">
                        <span><strong>${escape(item.name)}</strong></span>
                        <small style="color:#666;">${meta.join(' | ')}</small>
                    </div></div>`;
            },
            option_create(data, escape) {
                return `<div class="create">
                    <div class="label-not-found">Non è presente?</div>
                    <div class="badge rounded-pill text-bg-success">Aggiungi lo stesso</div>
                </div>`;
            },
            item(item, escape) {
                if (item.code === 'NUOVO') {
                    return `<div><strong>${escape(item.name)}</strong> <span class="badge bg-warning text-dark">Nuovo</span></div>`;
                }
                const priceHtml = item.sale_price
                    ? `<span style="text-decoration:line-through;color:#999;">€${item.price}</span> <strong style="color:green;">€${item.sale_price}</strong>`
                    : `<strong>€${item.price || 'N/A'}</strong>`;
                return `<div><strong>${escape(item.name)}</strong> — <small>Cod: ${escape(item.code)} | ${priceHtml}</small></div>`;
            },
        },

        onChange: (value) => {
            const ts = this.getTs();
            const item = ts?.options?.[value];
            const detailsDiv = document.querySelector('#product-details');
            const tsContainer = ts?.control_input?.closest('.form-group--ts')?.querySelector('.ts-container');

            if (detailsDiv) detailsDiv.innerHTML = '';

            if (item) {
                if (!item.thumbnail)
                    item.thumbnail = AppURLs.api.base + '/uploads/images/placeholder-product.jpg';

                this.currProd        = item;
                this.selectedProduct = this.normalizeProductForSelection(item);
                this.refreshSuggestionsFromSelectedProduct(this.selectedProduct, this.getSubOrderChecked());
                tsContainer?.classList.add('d-none');

                const isCustom = item.code === 'NUOVO';
                if (isCustom) {
                    detailsDiv.innerHTML = `
                        <div class="selected-product-preview">
                            <div class="product-info">
                                <p><strong>${item.name}</strong> <span class="badge bg-warning text-dark">Nuovo</span></p>
                                <p><small>Prodotto inserito manualmente. Prezzo e codice non disponibili.</small></p>
                            </div>
                            <button class="btn btn-outline-danger btn-remove" type="button"
                                onclick="ReservationForm.deselectProductSelected();">✕</button>
                        </div>`;
                } else {
                    const priceHtml = item.sale_price
                        ? `<span style="text-decoration:line-through;color:#999;">€${item.price}</span> <strong style="color:green;">€${item.sale_price}</strong>`
                        : `<strong>€${item.price || 'N/A'}</strong>`;
                    const meta = [];
                    if (item.name)  meta.push(escapeHtml(item.name));
                    if (item.code)  meta.push(`Codice: ${escapeHtml(item.code)}`);
                    if (parseFloat(item.price)) meta.push(`Prezzo: ${priceHtml}`);
                    detailsDiv.innerHTML = `
                        <div class="selected-product-preview">
                            ${item.thumbnail ? `<img src="${item.thumbnail}" alt="immagine prodotto" />` : ''}
                            <div class="product-info">${meta.map((m) => `<p>${m}</p>`).join('')}</div>
                            <button class="btn btn-outline-danger btn-remove" type="button"
                                onclick="ReservationForm.deselectProductSelected();">✕</button>
                        </div>`;
                }
            } else {
                this.currProd        = null;
                this.selectedProduct = null;
                tsContainer?.classList.remove('d-none');
                if (!this._suppressRelatedClear) {
                    this.loadRelatedProducts({ tag: '', seedName: '' }, this.getSubOrderChecked());
                }
            }
        },

        onItemAdd: (value) => {
            const item = this.getTs()?.options?.[value];
            if (!item) return;
            this.selectedProduct = this.normalizeProductForSelection(item);
        },

        onClear: () => {
            this.selectedProduct = null;
            if (!this._suppressRelatedClear) {
                this.loadRelatedProducts({ tag: '', seedName: '' }, this.getSubOrderChecked());
            }
        },

        onItemRemove: () => {
            if ((this.getTs()?.items?.length || 0) > 0) return;
            this.selectedProduct = null;
            if (!this._suppressRelatedClear) {
                this.loadRelatedProducts({ tag: '', seedName: '' }, this.getSubOrderChecked());
            }
        },
    });

    this._tsElRef = el;
},
	// ─────────────────────────────────────────────
	// CARRELLO
	// ─────────────────────────────────────────────

	getCartData()   { return this.cart; },
	getCartItems()  { return this.cart.products; },
	countCartItems() { return this.getCartItems().length; },

	getCartTotal() {
		const items = this.getCartItems();
		return items
			.filter((el) => el.price)
			.reduce((acc, el) => acc + parseFloat(el.price) * parseInt(el.qty, 10), 0);
	},

	resetCartData() {
		this.cart = { products: [], note: '', pickup: null, urgent: false, salta_fila: false, delivery: false };
	},

	addProductToCart(data) { this.cart.products.push(data); },

	removeProductFromCart(uuid) {
		this.cart.products = this.cart.products.filter((el) => el.uuid !== uuid);
	},

	currProductIsValid(data, showError) {
		if (!data) {
			if (showError) showToast?.('Inserisci un prodotto', 'warning');
			return false;
		}
		if (data.type === 1 && data.prescription_type === 'nre') {
			if (data.prescription_cf && !data.prescription_nre) {
				if (showError) showToast?.('Hai inserito il codice fiscale ma non il codice NRE', 'warning', 5000);
				return false;
			}
			if (!data.prescription_cf && data.prescription_nre) {
				if (showError) showToast?.('Hai inserito il codice NRE ma non il codice fiscale', 'warning', 5000);
				return false;
			}
			return true;
		}
		if (data.type === 1 && data.prescription_type === 'file' && data.prescription_file) {
			const file = data.prescription_file;
			const allowed = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
			const ext = file.name.split('.').pop().toLowerCase();
			if (!allowed.includes(file.type) || !['jpg', 'jpeg', 'png', 'gif', 'pdf'].includes(ext)) {
				if (showError) showToast?.('Puoi allegare solo file PDF, JPG, PNG e GIF', 'warning', 5000);
				return false;
			}
			return true;
		}
		if (data.type === 0) return true;
		return false;
	},

	addProduct(data, options = {}) {
		if (!data.uuid) data.uuid = generateUUID();
		this.addProductToCart(data);
		this.addProductToTable(data);
		this.setVisProductsTable(this.countCartItems() > 0);
		this.resetSubFormProduct();

		// Notifica il resto dell'app — il listener in attachReservationProductAddedListener
		// aggiornerà i suggerimenti basandosi sul prodotto appena aggiunto
		if (data?.product?.id) {
			document.dispatchEvent(new CustomEvent('reservationProductAdded', {
				detail: {
					id:       data.product.id,
					name:     data.product.name,
					sku:      data.product.code,
					tags:     data.product.tags || null,
					category: data.product.category || null,
					type:     data.type,
					source:   options?.source || null,
					mode:     Number.isInteger(options?.mode) ? options.mode : this.getSubOrderChecked(),
				},
			}));
		}
	},

	removeProduct(uuid) {
		this.removeProductFromCart(uuid);
		this.removeProductFromTable(uuid);
		this.setVisProductsTable(this.countCartItems() > 0);
		// Dopo la rimozione, ricarica i suggerimenti (ora exclude_ids è diminuito)
		const mode = this.getSubOrderChecked();
		const { lastTag, lastSeed } = this.relatedState[mode];
		this.loadRelatedProducts({ tag: lastTag, seedName: lastSeed }, mode);
	},

	// ─────────────────────────────────────────────
	// TABELLA PRODOTTI
	// ─────────────────────────────────────────────

	showProductsTable()  { this.table.classList.remove('d-none'); },
	hideProductsTable()  { this.table.classList.add('d-none'); },
	resetProductsTable() { this.table.querySelector('tbody').innerHTML = ''; },
	setVisProductsTable(bool) { return bool ? this.showProductsTable() : this.hideProductsTable(); },

	addProductToTable(product) {
		const tbody = this.table.querySelector('tbody');
		const row   = document.createElement('tr');
		row.classList.add('row-product');
		row.setAttribute('data-uuid', product.uuid || '');
		const shortName = product.name.split(' ').slice(0, 2).join(' ');
		row.innerHTML = `
			<td>
				<button class="btn btn-outline-danger btn-remove" type="button"
					onclick="ReservationForm.removeProduct('${product.uuid}');">−</button>
				${shortName}
			</td>
			<td>${product.qty}</td>`;
		tbody.appendChild(row);
	},

	removeProductFromTable(uuid) {
		this.table.querySelector(`tbody tr[data-uuid="${uuid}"]`)?.remove();
	},

	// ─────────────────────────────────────────────
	// INSERT PRODUCT (da form manuale)
	// ─────────────────────────────────────────────

	insertProduct() {
		const subOrderType = this.getSubOrderChecked();
		let data = {};

		if (subOrderType === 0) {
			if (!this.currProd) { showToast?.('Inserisci un prodotto', 'warning'); return false; }
			data = {
				type:    0,
				product: this.currProd,
				name:    this.currProd.name,
				code:    this.currProd.code,
				price:   this.currProd.sale_price ? this.currProd.sale_price : this.currProd.price,
				qty:     parseInt(document.querySelector('#res-prod-qty').value, 10),
			};
		} else if (subOrderType === 1) {
			const prescription = {
				type: document.querySelector('#res-prod-toggle-prescription').checked ? 'nre' : 'file',
				cf:   document.querySelector('#res-prod-cf').value,
				nre:  document.querySelector('#res-prod-nre').value,
				file: document.querySelector('#res-prod-prescription').files[0],
			};
			data = {
				type:              1,
				product:           null,
				name:              'Ricetta ' + (prescription.type === 'nre' ? prescription.nre : '[file]'),
				code:              'RICETTA',
				price:             null,
				qty:               1,
				prescription_type: prescription.type,
				prescription_cf:   prescription.cf,
				prescription_nre:  prescription.nre,
				prescription_file: prescription.file,
			};
		} else {
			showToast?.('Errore. Riprova.', 'warning');
			return false;
		}

		if (!this.currProductIsValid(data, true)) return;
		this.addProduct(data);
		showToast?.('Prodotto aggiunto', 'success');
	},

	// ─────────────────────────────────────────────
	// SUBMIT / SEND
	// ─────────────────────────────────────────────

	prepareCart() {
		const data = {
			products:   this.cart.products,
			note:       document.querySelector('#note').value,
			delivery:   !!document.querySelector('#delivery')?.checked,
			urgent:     !!document.querySelector('#urgent')?.checked,
			salta_fila: !!document.querySelector('#salta-fila')?.checked,
			pickup:     document.querySelector('#pickup').value.trim(),
		};
		data.products = data.products.map((el) => { delete el.product; return el; });
		this.cart = data;
	},

	cartIsValid(showError) {
		if (this.countCartItems() < 1) {
			if (showError) showToast?.('Inserisci almeno un prodotto', 'warning');
			return false;
		}
		if (this.cart.pickup) {
			const now = new Date();
			now.setMinutes(now.getMinutes() - 1);
			if (new Date(this.cart.pickup.replace('T', ' ')) < now) {
				if (showError) showToast?.('Seleziona una data futura valida', 'warning');
				return false;
			}
		}
		return true;
	},

	submit() {
		if (this.sending) return;
		this.prepareCart();
		if (!this.cartIsValid(true)) return;
		this.disableForm();
		this.send(this.cart);
	},

	send(cart) {
		const formData = new FormData();
		const cartMeta = {
			...cart,
			products: cart.products.map(({ prescription_file, ...rest }) => rest),
		};

		formData.append('products',   JSON.stringify(cartMeta.products));
		formData.append('pickup',     cartMeta.pickup);
		formData.append('note',       cartMeta.note);
		formData.append('urgent',     cartMeta.urgent);
		formData.append('salta_fila', cartMeta.salta_fila);
		formData.append('delivery',   cartMeta.delivery);

		cart.products.forEach((p) => {
			if (p.prescription_type === 'file' && p.prescription_file instanceof File) {
				formData.append(`file_${p.uuid}`, p.prescription_file);
			}
		});

		appFetchWithToken(AppURLs.api.sendReservation(), { method: 'POST', body: formData })
			.then((data) => {
				if (data.status) {
					document.dispatchEvent(new CustomEvent('reservationSuccess', { detail: data }));
				} else {
					document.dispatchEvent(new CustomEvent('reservationError', { detail: data }));
					handleError?.(data.message || 'Errore dal server');
				}
			})
			.catch((err) => {
				handleError?.(err, 'Errore rete o fetch');
				document.dispatchEvent(new CustomEvent('reservationError', { detail: { message: err } }));
			})
			.finally(() => this.enableForm());
	},
};

// ─────────────────────────────────────────────────────────────
// BOOTSTRAP
// ─────────────────────────────────────────────────────────────

let reservationBootstrapped = false;
let reservationGlobalListenersAttached = false;

function attachReservationGlobalListeners() {
	if (reservationGlobalListenersAttached) return;
	reservationGlobalListenersAttached = true;

	document.addEventListener('reservationSuccess', (e) => {
		ReservationForm.resetForm();
		const msg = e.detail?.message;
		if (msg) showToast?.(msg, 'success');
	});

	document.addEventListener('reservationError', (e) => {
		const msg = e.detail?.message;
		if (msg) showToast?.(msg, 'danger');
	});
}

function bootReservationForm() {
	const formEl = document.querySelector('#form-reservation');
	if (!formEl) return;
	// Aspetta che AppURLs e appFetchWithToken esistano davvero (evita init random a reload)

	if (!reservationBootstrapped || ReservationForm.form !== formEl) {
		reservationBootstrapped = true;
		if (formEl.dataset.rfSubmitBound !== '1') {
			formEl.addEventListener('submit', (e) => { e.preventDefault(); ReservationForm.submit(); });
			formEl.dataset.rfSubmitBound = '1';
		}
	}

	attachReservationGlobalListeners();
	ReservationForm.init();
}

// Una sola chiamata, quando l'app è pronta
document.addEventListener('appLoaded', () => bootReservationForm());
/**
 * Listener per eventi che segnalano che app.js ha terminato di caricare
 * i dati della farmacia. Nomi comuni — uno di questi sarà quello giusto.
 * Quando scatta, se il retry è in corso viene annullato e il caricamento
 * parte immediatamente senza aspettare il prossimo tick del polling.
 */
(function attachPharmaReadyListeners() {
	const triggerReady = () => {
		if (!ReservationForm.getPharmaId()) return;

		// Cancella il polling se attivo
		if (ReservationForm._pharmaRetryTimer) {
			clearTimeout(ReservationForm._pharmaRetryTimer);
			ReservationForm._pharmaRetryTimer = null;
		}

		// Ricarica suggerimenti per la modalità attiva
		const mode = ReservationForm.getSubOrderChecked();
		const { lastTag, lastSeed } = ReservationForm.relatedState[mode];
		ReservationForm.loadRelatedProducts(
			{ tag: lastTag || '', seedName: lastSeed || '' },
			mode
		);

		// Assicura che TomSelect sia inizializzato
		ReservationForm.initTomSelect();
	};

	// Aggiunti tutti i nomi di evento plausibili — solo uno scatterà
	['pharmaLoaded', 'userLoaded', 'dataStoreReady', 'appDataLoaded', 'storeReady'].forEach((evName) => {
		document.addEventListener(evName, triggerReady);
	});
})();

// ─────────────────────────────────────────────────────────────
// URL PARAMS
// ─────────────────────────────────────────────────────────────

function setSubOrderTypeByUrl() {
	const params = new URLSearchParams(window.location.search);
	if (params.has('tipo')) {
		const type = params.get('tipo');
		if (type === 'ricetta')        ReservationForm.setSubOrderType(1);
		if (type === 'senza-ricetta')  ReservationForm.setSubOrderType(0);
	}

	const canonicalTag = ReservationForm.canonicalizeTagValue(params.get('tag') || '');
	if (!canonicalTag) return;

	if (!ReservationForm.isAllowedRelatedTag(canonicalTag)) {
		if (typeof showToast === 'function') {
			showToast('Tag non valido: seleziona una categoria disponibile.', 'warning');
		} else {
			console.warn('Tag non valido in URL:', canonicalTag);
		}
		return;
	}

	const mode = ReservationForm.getSubOrderChecked();
	const selectId = mode === 1 ? '#related-seed-tag-prescription' : '#related-tag-select';
	const select = document.querySelector(selectId);
	if (select) {
		select.value = canonicalTag;
	}

	ReservationForm.showRelatedSection(mode);
	ReservationForm.loadRelatedProducts({ tag: canonicalTag, seedName: '' }, mode);
}

document.addEventListener('reservationFormReset', setSubOrderTypeByUrl);

window.ReservationForm = ReservationForm;