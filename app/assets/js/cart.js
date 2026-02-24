function getCartItems() {
	try {
		const data = localStorage.getItem('jta_app_cart');
		return data ? JSON.parse(data) : [];
	} catch (e) {
		console.warn('❌ Errore parsing jta_app_cart, resetto:', e);
		localStorage.removeItem('jta_app_cart');
		return [];
	}
}

function setCartItems(items) {
	localStorage.setItem('jta_app_cart', JSON.stringify(items));
	dispatchCartUpdate();
}

function dispatchCartUpdate() {
	document.dispatchEvent(new CustomEvent('cartUpdated', {detail: getCartItems()}));
}

function addToCart(item) {
	const items = getCartItems();
	const existing = items.find((i) => i.id === item.id);
	if (existing) {
		existing.quantity += 1;
	} else {
		item.quantity = 1;
		items.push(item);
	}
	setCartItems(items);
}

function updateQuantity(id, delta) {
	const items = getCartItems();
	const item = items.find((i) => i.id === id);
	if (item) {
		item.quantity += delta;
		if (item.quantity <= 0) {
			return removeFromCart(id);
		}
		setCartItems(items);
	}
}

function removeFromCart(id) {
	const items = getCartItems().filter((i) => i.id !== id);
	setCartItems(items);
}

function clearCart() {
	setCartItems([]);
}

function getTotal() {
	return getCartItems()
		.reduce((sum, i) => sum + i.price * i.quantity, 0)
		.toFixed(2);
}

/* sync e ordine
function fetchCartSync() {
	const body = {cart: getCartItems()};

	appFetchWithToken(AppURLs.api.syncCart(), {
		method: 'POST',
		headers: {'Content-Type': 'application/json'},
		body: JSON.stringify(body),
	})
		.then((data) => {
			if (data.status) {
				document.dispatchEvent(new CustomEvent('cartSyncSuccess', {detail: data?.data}));
			} else {
				document.dispatchEvent(new CustomEvent('cartSyncError', {detail: {error: data.error}}));
			}
		})
		.catch((err) => {
			document.dispatchEvent(new CustomEvent('cartSyncError', {detail: {error: err}}));
		});
}
*/
function fetchSendOrder(orderPayload) {
	const items = orderPayload.items ?? [];

	if (!Array.isArray(items) || items.length === 0) {
		document.dispatchEvent(
			new CustomEvent('orderSentError', {
				detail: {error: 'Carrello vuoto'},
			})
		);
	}
	appFetchWithToken(AppURLs.api.sendOrder(), {
		method: 'POST',
		headers: {'Content-Type': 'application/json'},
		body: JSON.stringify(orderPayload),
	})
		.then((data) => {
			if (data.status) {
				document.dispatchEvent(new CustomEvent('orderSentSuccess', {detail: data?.data}));
				showToast(data.message, 'warning');
			} else {
				document.dispatchEvent(new CustomEvent('orderSentError', {detail: {error: data.error}}));
				showToast(data.message, 'error');
			}
		})
		.catch((err) => {
			document.dispatchEvent(new CustomEvent('orderSentError', {detail: {error: err}}));
			handleError('Errore di rete o server.');
		});
}

// Success/Fail order
document.addEventListener('orderSentSuccess', () => {
	clearCart();
	document.querySelector('app-cart')?.closeModal();
});

// Eventi storage
window.addEventListener('storage', (e) => {
	if (e.key === 'jta_app_cart') dispatchCartUpdate();
});

// Ri-ordina da archivio
function reorderBooking(items) {
	if (!Array.isArray(items) || items.length === 0) {
		showToast('Nessun prodotto da riordinare', 'warning');
		return;
	}

	items.forEach((item) => {
		CartUtils.addToCart({
			id: item.id,
			quantity: item.quantity,
			name: item.name,
			price: item.price,
			price_sale: item.price_sale,
			price_regular: item.price_regular,
			image: item.image,
		});
	});

	showToast('Prodotti aggiunti al carrello dal tuo ordine precedente!', 'success');

	if (window.appCartModal) {
		window.appCartModal.openModal();
	}
}

// Export impliciti globali
window.CartUtils = {
	getCartItems,
	addToCart,
	updateQuantity,
	removeFromCart,
	clearCart,
	getTotal,
	//fetchCartSync,
	fetchSendOrder,
};
