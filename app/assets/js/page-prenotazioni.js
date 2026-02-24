const ReservationForm = {
	form: null,
	picker: null,
	cart: {},
	ts: null,
	table: null,
	currProd: null,
	sending: false,

	init() {
		this.form = document.querySelector('#form-reservation');
		this.picker = document.querySelector('#pickup');
		this.table = document.querySelector('#product-summary');

		this.initTomSelect();
		// this.resetCartData();
		this.resetForm();

		document.dispatchEvent(new CustomEvent('reservationFormLoaded'));
	},

	openCalendarPicker() {
		const picker = document.querySelector('#pickup');
		picker?.showPicker?.(); // Chromium
		picker?.focus?.(); // Safari fallback
	},

	updateTotal() {
		const total = this.getCartTotal();
		totalPrice = Math.round(total * 100) / 100;

		const totalDisplay = document.querySelector('#total-price');
		if (totalDisplay) {
			if (totalPrice > 0) {
				totalDisplay.textContent = `Totale: €${totalPrice.toFixed(2)}`;
				totalDisplay.style.display = 'block';
			} else {
				totalDisplay.style.display = 'none';
			}
		}
	},

	togglePrescription() {
		const toggle = document.querySelector('#res-prod-toggle-prescription');
		const uploadBlock = document.querySelector('#upload-prescription-block');
		const codeBlock = document.querySelector('.prescription-method-nre');

		if (!toggle) return;

		if (toggle.checked) {
			if (uploadBlock) uploadBlock.classList.add('d-none');
			if (codeBlock) codeBlock.classList.remove('d-none');
		} else {
			if (codeBlock) codeBlock.classList.add('d-none');
			if (uploadBlock) uploadBlock.classList.remove('d-none');
		}
	},

	disableForm() {
		ReservationForm.sending = true;
		document.querySelector('#form-reservation button[type="submit"]').disabled = true;
	},

	enableForm() {
		ReservationForm.sending = false;
		document.querySelector('#form-reservation button[type="submit"]').disabled = false;
	},

	resetForm() {
		ReservationForm.resetSubFormProduct();
		ReservationForm.resetProductsTable();
		ReservationForm.hideProductsTable();
		document.querySelector('#form-reservation').reset();
		this.resetCartData();
		this.togglePrescription();
		this.updateSubOrderView();
		// this.updateTotal();

		document.dispatchEvent(new CustomEvent('reservationFormReset'));
	},

	resetSubFormProduct() {
		const form = document.querySelector('#form-reservation');
		const checboxPresc = form.querySelector('#res-prod-toggle-prescription');

		ReservationForm.ts.clear();
		form.querySelector('#res-prod-qty').value = '1';
		ReservationForm.clearFileSelected(false);
		checboxPresc.checked = false;
		checboxPresc.dispatchEvent(new Event('change', {bubbles: true}));
		form.querySelector('#res-prod-cf').value = '';
		form.querySelector('#res-prod-nre').value = '';
	},

	// Rimozione file ricetta
	clearFileSelected(showError) {
		const prescriptionInput = document.querySelector('#res-prod-prescription');
		if (prescriptionInput) {
			prescriptionInput.value = '';
			prescriptionInput.focus();

			if (showError) showToast?.('File ricetta rimosso', 'info');
		}
	},

	getSubOrderChecked() {
		const checked = this.form.querySelector('input[name="suborder-type"]:checked');
		const val = checked ? parseInt(checked.value) : 0;
		return val;
	},
	updateSubOrderView() {
		const groups = this.form.querySelectorAll('.suborder-group');
		const val = this.getSubOrderChecked();

		groups.forEach((el) => {
			if (el.classList.contains('suborder-group--' + val)) {
				el.classList.remove('d-none');
			} else {
				el.classList.add('d-none');
			}
		});
	},
	setSubOrderType(type) {
		const input = this.form.querySelector('#suborder-type--' + type);
		if (input) input.click();
	},
	toggleSubOrderType() {
		const notChecked = this.form.querySelector('input[name="suborder-type"]:not(:checked)');
		if (notChecked) notChecked.click();
	},

	showProductsTable() {
		this.table.classList.remove('d-none');
	},
	hideProductsTable() {
		this.table.classList.add('d-none');
	},
	toggleProductsTable() {
		this.table.classList.toggle('d-none');
	},
	setVisProductsTable(bool) {
		return bool ? this.showProductsTable() : this.hideProductsTable();
	},
	resetProductsTable() {
		this.table.querySelector('tbody').innerHTML = '';
	},

	addProductToTable(product) {
		const tbody = this.table.querySelector('tbody');
		const row = document.createElement('tr');

		row.classList.add('row-product');
		row.setAttribute('data-uuid', product.uuid || '');

		const shortName = product.name.split(' ').slice(0, 2).join(' ');

		var btnRemove = `<button class="btn btn-outline-danger btn-remove" type="button" onclick="ReservationForm.removeProduct('${product.uuid}');">-</button>`; // ✕

		let innerRow = '';
		innerRow = `
				<td>${btnRemove}${shortName}</td>
				<td>${product.qty}</td>
			`;
		// <td>${(product.prescription_cf && product.prescription_nre) || product.prescription_file ? '✅' : '❌'}</td>

		row.innerHTML = innerRow;
		tbody.appendChild(row);
	},

	removeProductFromTable(uuid) {
		const table = ReservationForm.table;
		const row = table.querySelector('tbody tr[data-uuid="' + uuid + '"]');
		if (row) row.remove();
	},

	getTs() {
		return this.ts;
	},
	deselectProductSelected() {
		this.ts.clear();
	},
	initTomSelect() {
		if (!window.TomSelect) return;
		const el = document.querySelector('#product-name');
		if (!el || el.tomselect) return;

		this.ts = new TomSelect('#product-name', {
			maxItems: 1,
			valueField: 'id',
			labelField: 'name',
			searchField: ['name', 'code'],
			create(input) {
				const nuovoProdotto = {
					id: input,
					name: input,
					code: 'NUOVO',
					thumbnail: AppURLs.api.base + '/uploads/images/placeholder-product.jpg',
					price: null,
					quantity: null,
					sale_price: null,
				};
				document.dispatchEvent(new CustomEvent('productSuggestionCreated', {detail: nuovoProdotto}));
				return nuovoProdotto;
			},
			load(query, callback) {
				if (query.length < 3) return callback();
				const pharmaId = dataStore.pharma.id;
				if (!pharmaId) {
					console.warn('Abort. Nessuna ID Farmacia trovato.');
					return callback([]);
				}

				const url = new URL(AppURLs.api.productSuggestions());
				url.searchParams.set('search', query);
				url.searchParams.set('pharma_id', pharmaId);

				appFetchWithToken(url.toString(), {method: 'GET'})
					.then((data) => {
						if (data.status) {
							if (!Array.isArray(data.data.products)) return callback([]);
							const results = data.data.products.map((p) => ({
								id: p.id,
								name: p.name,
								code: p.sku || 'N/A',
								price: p.price || 'N/A',
								sale_price: p.sale_price || null,
								quantity: p.num_items || '',
								thumbnail: p.image ? (p.image.startsWith('http') ? p.image : `https://app.assistentefarmacia.it/panel/${p.image}`) : null,
							}));
							callback(results);
						} else {
							console.warn('⚠️ Nessun prodotto trovato:', data.message || data.error);
							callback([]);
						}
					})
					.catch((err) => {
						console.error('❌ Errore nella ricerca prodotti:', err);
						callback([]);
					});
			},
			render: {
				option(item, escape) {
					const isCustom = item.code === 'NUOVO';
					if (isCustom) {
						return `
								<div class="option" style="display:flex;align-items:center;gap:10px;">
									<div style="display:flex;flex-direction:column;">
										<span><strong>${escape(item.name)}</strong> <span class="badge bg-warning text-dark">Nuovo</span></span>
										<small style="color:#999;">Prodotto inserito manualmente</small>
									</div>
								</div>`;
					}
					const priceHtml = item.sale_price ? `<span style="text-decoration:line-through;color:#999;">€${escape(item.price)}</span> <strong style="color:green;">€${escape(item.sale_price)}</strong>` : `€${escape(item.price)}`;

					const meta = [];
					if (item.code) meta.push(`Codice: ${escapeHtml(item.code)}`);
					if (parseFloat(item.price)) meta.push(`Prezzo: ${priceHtml}`);

					return `
							<div class="option" style="display:flex;align-items:center;gap:10px;">
								${item.thumbnail ? `<img src="${item.thumbnail}" style="width:24px;height:24px;margin-right:8px;" />` : ''}
								<div style="display:flex;flex-direction:column;">
									<span><strong>${escapeHtml(item.name)}</strong></span>
									<small style="color:#666;">${meta.join(' | ')}</small>
								</div>
							</div>`;
				},
				option_create: function (data, escape) {
					return `<div class="create">
								<div class="label-not-found">Non è presente?</div>
								<div class="badge rounded-pill text-bg-success">Aggiungi lo stesso</div>
							</div>`;
					// <div class="badge rounded-pill text-bg-success">Aggiungi "<strong>${escape(data.input)}</strong>"</div>
				},
				// no_results: function (data, escape) {
				// 	return '<div class="no-results">Nessun risultato trovato per "' + escape(data.input) + '"</div>';
				// },
				item(item, escape) {
					const isCustom = item.code === 'NUOVO';
					if (isCustom) {
						return `<div><strong>${escape(item.name)}</strong> <span class="badge bg-warning text-dark">Nuovo</span></div>`;
					}
					const priceHtml = item.sale_price ? `<span style="text-decoration:line-through;color:#999;">€${item.price}</span> <strong style="color:green;">€${item.sale_price}</strong>` : `<strong>€${item.price || 'N/A'}</strong>`;

					return `<div><strong>${escape(item.name)}</strong> — <small>Cod: ${escape(item.code)} | ${priceHtml}</small></div>`;
				},
			},
			onChange: (value) => {
				// const ts = document.querySelector('#product-name').tomselect;
				const ts = this.getTs();
				const item = ts.options[value];
				const detailsDiv = document.querySelector('#product-details');
				detailsDiv.innerHTML = '';

				const group = ts.control_input.closest('.form-group--ts');
				const input = group.querySelector('.ts-container');

				if (item) {
					if (!item.thumbnail) item.thumbnail = AppURLs.api.base + '/uploads/images/placeholder-product.jpg';

					this.currProd = item;

					input.classList.add('d-none');

					const isCustom = item.code === 'NUOVO';
					if (isCustom) {
						detailsDiv.innerHTML = `<div class="selected-product-preview">
														<div class="product-info">
															<p><strong>${item.name}</strong> <span class="badge bg-warning text-dark">Nuovo</span></p>
															<p><small>Prodotto inserito manualmente. Prezzo e codice non disponibili.</small></p>
														</div>
														<button class="btn btn-outline-danger btn-remove" type="button" onclick="ReservationForm.deselectProductSelected();">✕</button>
													</div>`;
					} else {
						const imgHtml = item.thumbnail ? `<img src="${item.thumbnail}" alt="immagine prodotto" />` : '';
						const priceHtml = item.sale_price ? `<span style="text-decoration:line-through;color:#999;">€${item.price}</span> <strong style="color:green;">€${item.sale_price}</strong>` : `<strong>€${item.price || 'N/A'}</strong>`;

						const meta = [];
						if (item.name) meta.push(escapeHtml(item.name));
						if (item.code) meta.push(`Codice: ${escapeHtml(item.code)}`);
						if (parseFloat(item.price)) meta.push(`Prezzo: ${priceHtml}`);

						detailsDiv.innerHTML = `<div class="selected-product-preview">
														${imgHtml}
														<div class="product-info">${meta.map((item) => '<p>' + item + '</p>').join('')}</div>
														<button class="btn btn-outline-danger btn-remove" type="button" onclick="ReservationForm.deselectProductSelected();">✕</button>
													</div>`;
					}
				} else {
					this.currProd = null;

					input.classList.remove('d-none');
				}
			},
		});
	},

	getCartData() {
		return this.cart;
	},
	getCartItems() {
		return this.cart.products;
	},
	countCartItems() {
		return this.getCartItems().length;
	},
	getCartTotal() {
		const items = this.getCartItems();
		const prices = items.filter((el) => el.price).map((el) => parseFloat(el.price) * parseInt(el.qty));
		const total = prices.reduce((acc, val) => acc + val, 0);

		return total;
	},

	resetCartData() {
		this.cart = {
			products: [],
			note: '',
			pickup: null,
			urgent: false,
			delivery: false,
		};
	},

	addProductToCart(data) {
		this.cart.products.push(data);
	},

	removeProductFromCart(uuid) {
		this.cart.products = this.cart.products.filter((el) => el.uuid != uuid);
	},

	currProductIsValid(currProductData, showError) {
		if (!currProductData) {
			if (showError) showToast?.('Inserisci un prodotto', 'warning');
			return false;
		}

		if (currProductData.type === 1 && currProductData.prescription_type == 'nre') {
			if (currProductData.prescription_cf && !currProductData.prescription_nre) {
				if (showError) showToast?.('Per la ricetta hai inserito il codice fiscale, ma non il codice NRE', 'warning', 5000);
				return false;
			}
			if (!currProductData.prescription_cf && currProductData.prescription_nre) {
				if (showError) showToast?.('Per la ricetta hai inserito il codice NRE, ma non il codice fiscale', 'warning', 5000);
				return false;
			}
			return true;
		} else if (currProductData.type === 1 && currProductData.prescription_type == 'file' && currProductData.prescription_file) {
			const file = currProductData.prescription_file;

			const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
			const allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];

			const mimeOk = allowedTypes.includes(file.type);
			const ext = file.name.split('.').pop().toLowerCase();
			const extOk = allowedExt.includes(ext);

			if (!mimeOk || !extOk) {
				if (showError) showToast?.('Per la ricetta puoi allegare solo file PDF, JPG, PNG e GIF', 'warning', 5000);
				return false;
			}
			return true;
		} else if (currProductData.type === 0) {
			return true;
		}

		return false;
	},

	addProduct(data) {
		if (!data.uuid) data.uuid = generateUUID();

		this.addProductToCart(data);
		this.addProductToTable(data);
		this.setVisProductsTable(this.countCartItems() > 0);
		this.resetSubFormProduct();
	},

	removeProduct(uuid) {
		this.removeProductFromCart(uuid);
		this.removeProductFromTable(uuid);
		this.setVisProductsTable(this.countCartItems() > 0);
	},

	insertProduct() {
		const subOrderType = this.getSubOrderChecked();
		let data = {};

		// Senza ricetta
		if (subOrderType === 0) {
			if (!this.currProd) {
				showToast?.('Inserisci un prodotto', 'warning');
				return false;
			}

			data = {
				type: 0,
				product: this.currProd,
				name: this.currProd.name,
				code: this.currProd.code,
				price: this.currProd.sale_price ? this.currProd.sale_price : this.currProd.price,
				qty: parseInt(document.querySelector('#res-prod-qty').value, 10),
			};
		}
		// Con ricetta
		else if (subOrderType === 1) {
			const prescription = {
				type: document.querySelector('#res-prod-toggle-prescription').checked ? 'nre' : 'file',
				cf: document.querySelector('#res-prod-cf').value,
				nre: document.querySelector('#res-prod-nre').value,
				file: document.querySelector('#res-prod-prescription').files[0],
			};

			data = {
				type: 1,
				product: null,
				name: 'Ricetta ' + (prescription.type == 'nre' ? prescription.nre : '[file]'),
				code: 'RICETTA',
				price: null,
				qty: 1,
				prescription_type: prescription.type,
				prescription_cf: prescription.cf,
				prescription_nre: prescription.nre,
				prescription_file: prescription.file,
			};
		} else {
			showToast?.('Errore. Riprova.', 'warning');
			return false;
		}

		if (!this.currProductIsValid(data, true)) {
			return;
		}

		this.addProduct(data);

		showToast?.('Prodotto aggiunto', 'success');
	},

	prepareCart() {
		data = {
			products: this.cart.products,
			note: document.querySelector('#note').value,
			delivery: !!document.querySelector('#delivery')?.checked,
			urgent: !!document.querySelector('#urgent')?.checked,
			pickup: document.querySelector('#pickup').value.trim(),
		};

		data.products = data.products.map((el) => {
			delete el.product;
			return el;
		});

		this.cart = data;
	},

	cartIsValid(showError) {
		const data = this.cart;
		showError = !!showError;

		// Prodotti
		if (this.countCartItems() < 1) {
			if (showError) showToast?.('Inserisci almeno un prodotto', 'warning');
			return false;
		}

		// Data/ora ritiro
		if (data.pickup) {
			const now = new Date();
			now.setMinutes(now.getMinutes() - 1);
			const dt = new Date(data.pickup.replace('T', ' '));
			if (dt < now) {
				if (showError) showToast?.('Seleziona una data futura valida', 'warning');
				return false;
			}
		} else {
			// if (showError) showToast?.('Inserisci data e ora del ritiro', 'warning');
			// return false;
		}
		// -----------

		return true;
	},

	submit() {
		if (ReservationForm.sending) return;

		this.prepareCart();
		if (!this.cartIsValid(true)) return;

		ReservationForm.disableForm();

		this.send(this.cart);
	},

	send(cart) {
		const formData = new FormData();

		const cartMeta = {
			...cart,
			products: cart.products.map((p) => {
				const {prescription_file, ...rest} = p;
				return rest;
			}),
		};

		formData.append('products', JSON.stringify(cartMeta.products));
		formData.append('pickup', cartMeta.pickup);
		formData.append('note', cartMeta.note);
		formData.append('urgent', cartMeta.urgent);
		formData.append('delivery', cartMeta.delivery);

		cart.products.forEach((p, idx) => {
			if (p.prescription_type === 'file' && p.prescription_file instanceof File) {
				formData.append(`file_${p.uuid}`, p.prescription_file);
			}
		});

		appFetchWithToken(AppURLs.api.sendReservation(), {
			method: 'POST',
			body: formData,
		})
			.then((data) => {
				if (data.status) {
					document.dispatchEvent(new CustomEvent('reservationSuccess', {detail: data}));
				} else {
					document.dispatchEvent(new CustomEvent('reservationError', {detail: data}));
					handleError?.(data.message || 'Errore dal server');
				}
			})
			.catch((err) => {
				handleError?.(err, 'Errore rete o fetch');
				document.dispatchEvent(new CustomEvent('reservationError', {detail: {message: err}}));
			})
			.finally(() => {
				ReservationForm.enableForm();
			});
	},
};

document.addEventListener('appLoaded', () => {
	const formEl = document.querySelector('#form-reservation');
	if (formEl) {
		formEl.addEventListener('submit', function (e) {
			e.preventDefault();
			ReservationForm.submit();
		});
	}

	document.addEventListener('reservationSuccess', function (e) {
		const data = e.detail;
		ReservationForm.resetForm();
		if (data.message) showToast?.(data.message || 'Prenotazione inviata!', 'success');
	});

	document.addEventListener('reservationError', function (e) {
		const data = e.detail;
		if (data.message) showToast?.(data.message || 'Prenotazione fallita!', 'danger');
	});

	ReservationForm.init();
});

function setSubOrderTypeByUrl() {
	const params = new URLSearchParams(window.location.search);

	if (params.has('tipo')) {
		const type = params.get('tipo');
		if (!type) return;

		if (type == 'ricetta') ReservationForm.setSubOrderType(1);
		if (type == 'senza-ricetta') ReservationForm.setSubOrderType(0);
	}
}

document.addEventListener('reservationFormReset', setSubOrderTypeByUrl);

/*
function setProductInCartByUrl(data){
	const params = new URLSearchParams(window.location.search);

	if (params.has('id')) {
		const id = params.get('id');
		if( ! id ) return;
		if( id !== 'vaccino' ) return;

		data = getDataProductForVaccino();

		const isValid = ReservationForm.currProductIsValid(data, false);
		if( ! isValid ) return;

		ReservationForm.addProduct(data);
	}
}

function getDataProductForVaccino(){
	return {
		"type": 0,
		"product": {
			"id": 7093,
			"name": "Vaccino Anti Influenzale",
			"code": "VAC01",
			"price": null,
			"sale_price": null,
			"thumbnail": null,
			"$order": 1,
			"$id": "product-name-opt-1",
			"$div": {},
			"$option": {}
		},
		"name": "Vaccino Anti Influenzale",
		"code": "VAC01",
		"price": null,
		"qty": 1,
	};
}

document.addEventListener('reservationFormReset', setProductInCartByUrl );
*/
