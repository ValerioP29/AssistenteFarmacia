const AppURLs = {
	page: {
		root: () => window.location.origin,
		login: () => AppURLs.page.root() + '/index.html',
		dashboard: () => AppURLs.page.root() + '/dashboard.html',
		profile: () => AppURLs.page.root() + '/profilo.html',
		chatbot: () => AppURLs.page.root() + '/chatbot.html',
		promotions: () => AppURLs.page.root() + '/promozioni.html',
		events: () => AppURLs.page.root() + '/eventi.html',
		services: () => AppURLs.page.root() + '/servizi.html',
		reminders: () => AppURLs.page.root() + '/promemoria.html',
		reservation: () => AppURLs.page.root() + '/prenotazioni.html',
		archiveOrder: () => AppURLs.page.root() + '/archivio-ordini.html',
		checkUpPage: () => AppURLs.page.root() + '/check-up-summary.html',
		checkUpHand: () => AppURLs.page.root() + '/check-up-mani.html',
		checkUpHair: () => AppURLs.page.root() + '/check-up-capelli.html',
		checkUpEyes: () => AppURLs.page.root() + '/check-up-occhi.html',
		checkUpFace: () => AppURLs.page.root() + '/check-up-viso.html',
		checkUpLip: () => AppURLs.page.root() + '/check-up-labbra.html',
		checkUpChrome: () => AppURLs.page.root() + '/check-up-armocromia.html',
		preferPharma: () => AppURLs.page.root() + '/farmacia-preferita.html',
		pharmacy: () => AppURLs.page.root() + '/farmacia.html',
		pillDay: (day) => AppURLs.page.root() + `/pillola-del-benessere.html?giorno=${day ?? ''}`,
		pill: (id) => AppURLs.page.root() + `/pillola-del-benessere.html?id=${id ?? ''}`,
		pillArchive: () => AppURLs.page.root() + '/pillole-archivio.html',
		wellnessPoints: () => AppURLs.page.root() + '/punti-benessere.html',
		wasteDrugs: () => AppURLs.page.root() + '/smaltimento-farmaci.html',
		wellnessChallenge: () => AppURLs.page.root() + '/sfida-benessere.html',
		quiz: () => AppURLs.page.root() + '/quiz.html',
		questionary: () => AppURLs.page.root() + '/questionario.html',
		survey: (id) => AppURLs.page.root() + `/sondaggio.html?id=${id ?? ''}`,
	},
	api: {
		base: 'https://api.assistentefarmacia.it',
		auth: () => AppURLs.api.base + '/auth-check.php',
		authPassword: () => AppURLs.api.base + '/auth-password.php',
		login: () => AppURLs.api.base + '/auth-login.php',
		registration: () => AppURLs.api.base + '/auth-registration.php',
		refreshToken: () => AppURLs.api.base + '/auth-refresh.php',
		initChatBot: () => AppURLs.api.base + '/chatbot-init.php',
		sendToBot: () => AppURLs.api.base + '/chatbot-send.php',
		quickAction: () => AppURLs.api.base + '/chatbot-send.php',
		getDrug: (id) => AppURLs.api.base + `/product-get.php?id=${id ?? ''}`,
		getPharmaFav: (id) => AppURLs.api.base + `/pharma-fav-get.php?id=${id ?? ''}`,
		getPharma: (id) => AppURLs.api.base + `/pharma-get.php?id=${id ?? ''}`,
		getProfile: () => AppURLs.api.base + '/user-get.php',
		putProfile: () => AppURLs.api.base + '/user-put.php',
		getPromos: () => AppURLs.api.base + '/promos-list.php',
		getPromo: (id) => AppURLs.api.base + `/promo-get.php?id=${id ?? ''}`,
		sendOrder: () => AppURLs.api.base + '/order-post.php',
		//syncCart: () => AppURLs.api.base + '/placeholder-cart-get.php',
		getEvents: () => AppURLs.api.base + '/events-list.php',
		getEvent: (id) => AppURLs.api.base + `/event-get.php?id=${id ?? ''}`,
		bookEvent: () => AppURLs.api.base + '/event-post.php',
		getServices: () => AppURLs.api.base + '/services-list.php',
		getService: (id) => AppURLs.api.base + `/service-get.php?id=${id ?? ''}`,
		bookService: () => AppURLs.api.base + '/service-post.php',
		requestPost: () => AppURLs.api.base + '/custom-request-post.php',
		productSuggestions: () => AppURLs.api.base + '/product-search.php',
		sendReservation: () => AppURLs.api.base + '/reservation-post.php',
		getArchivedOrders: () => AppURLs.api.base + '/orders-list.php',
		cancelBooking: () => `${AppURLs.api.base}/order-delete.php`,
		getPharmacies: () => AppURLs.api.base + '/user-pharmas-get.php',
		deletePharmacy: () => AppURLs.api.base + '/user-pharma-delete.php',
		setPreferredPharmacy: () => AppURLs.api.base + '/user-pharma-set-fav.php',
		getPharmacyProfile: (id) => AppURLs.api.base + `/pharma-profile-get.php${id ? '?id='+id : ''}`,
		uploadCheckUp: () => AppURLs.api.base + '/checkup-post.php',
		getPillContent: (day) => AppURLs.api.base + `/pill-get.php?giorno=${day ?? ''}`,
		getPill: (id) => AppURLs.api.base + `/pill-get.php?id=${id ?? ''}`,
		getAllPills: () => AppURLs.api.base + '/pills-list.php',
		getTherapyReminders: () => AppURLs.api.base + '/reminders-therapy-list.php',
		addTherapyReminder: () => AppURLs.api.base + '/reminder-therapy-post.php',
		updateTherapyReminder: () => AppURLs.api.base + '/reminder-therapy-put.php',
		deleteTherapyReminder: () => AppURLs.api.base + '/reminder-therapy-delete.php',
		completeTherapyReminder: () => AppURLs.api.base + '/reminder-therapy-complete.php',
		getExpiryReminders: () => AppURLs.api.base + '/reminders-expiry-list.php',
		addExpiryReminder: () => AppURLs.api.base + '/reminder-expiry-post.php',
		updateExpiryReminder: () => AppURLs.api.base + '/reminder-expiry-put.php',
		deleteExpiryReminder: () => AppURLs.api.base + '/reminder-expiry-delete.php',
		getWellnessChallenge: () => AppURLs.api.base + '/weekly-challenge-get.php',
		postWellnessDay: () => AppURLs.api.base + '/weekly-challenge-post.php',
		getWellnessPoints: () => AppURLs.api.base + '/points-get.php',
		getQuiz: () => AppURLs.api.base + '/quiz-get.php',
		sendQuiz: () => AppURLs.api.base + '/quiz-post.php',
		saveQuestionary: () => AppURLs.api.base + '/questionary-post.php',
		getQuestionaryStatus: () => AppURLs.api.base + '/questionary-status.php',
		saveSurvey: () => AppURLs.api.base + '/survey-post.php',
		getSurvey: (id) => AppURLs.api.base + `/survey-get.php?id=${id ?? ''}`,
	},
	panel: {
		base: 'https://panel.assistentefarmacia.it',
	},
};

const dataStore = {};

const JWT = {
	auth: {
		key: () => 'jta-app-jwt',
		get: () => localStorage.getItem(JWT.auth.key()),
		set: (token) => localStorage.setItem(JWT.auth.key(), token),
		remove: () => localStorage.removeItem(JWT.auth.key()),
	},
	refresh: {
		key: () => 'jta-app-refresh_token',
		get: () => localStorage.getItem(JWT.refresh.key()),
		set: (token) => localStorage.setItem(JWT.refresh.key(), token),
		remove: () => localStorage.removeItem(JWT.refresh.key()),
	},
};

let refreshing = null; // Promise condivisa

function appLogout() {
	saveAndRedirectAfterLogin();
	JWT.auth.remove();
	JWT.refresh.remove();
	goTo(AppURLs.page.login());
}

function appLogin(username, password) {
	fetch(AppURLs.api.login(), {
		method: 'POST',
		headers: {'Content-Type': 'application/json'},
		body: JSON.stringify({
			username: username,
			password: password,
		}),
	})
		.then((res) => res.json())
		.then((data) => {
			if (data.access_token) {
				JWT.auth.set(data.access_token);
				JWT.refresh.set(data.refresh_token);

				document.dispatchEvent(new CustomEvent('login:success', {detail: data}));
			} else {
				document.dispatchEvent(new CustomEvent('login:error', {detail: data}));
			}
		})
		.catch((e) => {
			document.dispatchEvent(new CustomEvent('login:error', {detail: {message: 'Errore imprevisto, riprova'}}));
		});
}

function saveAndRedirectAfterLogin() {
	localStorage.setItem('jta_login_redirect', window.location.href);
}

function redirectAfterLogin() {
	const storedUrl = localStorage.getItem('jta_login_redirect');
	if (storedUrl && location.hostname === new URL(storedUrl).hostname) {
		localStorage.removeItem('jta_login_redirect');
		location.href = storedUrl;
		return;
	}
	localStorage.removeItem('jta_login_redirect');
	goTo(AppURLs.page.dashboard());
}

document.addEventListener('login:success', async (e) => redirectAfterLogin());

document.addEventListener('login:error', cbToastifyListenerError);

async function appRefreshToken() {
	if (!refreshing) {
		const refresh_token = JWT.refresh.get();
		if (!refresh_token) return Promise.resolve(null);

		refreshing = fetch(AppURLs.api.refreshToken(), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify({refresh_token}),
		})
			.then((res) => (res.ok ? res.json() : null))
			.then((data) => {
				if (!data.status) {
					// alert('Login ');
				}

				if (!data?.access_token) return null;
				JWT.auth.set(data.access_token);
				JWT.refresh.set(data.refresh_token);
				return data.access_token;
			})
			.catch(() => null)
			.finally(() => {
				refreshing = null;
			});
	}
	return refreshing;
}

async function appFetchWithToken(url, options = {}) {
	options.headers = {
		...(options.headers || {}),
		Authorization: 'Bearer ' + JWT.auth.get(),
	};

	const res = await fetch(url, options);
	let data = await res.json();

	if (data.code === 401) {
		const newToken = await appRefreshToken();
		if (!newToken) {
			appLogout();
			throw new Error('Sessione scaduta');
		}

		options.headers['Authorization'] = 'Bearer ' + newToken;
		const retryRes = await fetch(url, options);
		return retryRes.json();
	}

	return data;
}

function appCheckAuth() {
	appFetchWithToken(AppURLs.api.auth())
		.then((data) => {
			if (data.status) {
				dataStore.user = data.user;
				dataStore.pharma = data.pharma;
				document.dispatchEvent(new CustomEvent('appLoggedin', {detail: data}));
				return;
			}
			document.dispatchEvent(new CustomEvent('appLoggedout'));
		})
		.catch(() => {
			document.dispatchEvent(new CustomEvent('appLoggedout'));
		});
}

function appToggleMenu() {
	const menuCmp = document.querySelector('app-menu');
	if (menuCmp?.toggle) {
		menuCmp.toggle();
		return;
	}
	document.getElementById('appMenu')?.classList.toggle('open'); // fallback
}

function goTo(url) {
	window.location.href = url;
}

function normalizeUrl(url) {
	try {
		let parsedUrl = new URL(url, window.location.origin);

		// Rimuove il trailing slash
		let pathname = parsedUrl.pathname.replace(/\/+$/, '');

		// Rimuove "index.html" alla fine, se presente
		pathname = pathname.replace(/\/?index\.html$/i, '');

		// Ricostruisce la URL normalizzata senza query e hash
		return `${parsedUrl.origin}${pathname}`;
	} catch (e) {
		console.error('URL non valida:', url);
		return null;
	}
}

function currPageIs(page) {
	const curr = normalizeUrl(window.location.href);
	page = normalizeUrl(page);
	return curr === page;
}

function showPrivatePageContent() {
	document.body.classList.remove('loading');
}

document.addEventListener('appLoggedin', showPrivatePageContent);
document.addEventListener('appLoggedout', appLogout);

document.addEventListener('appLoggedin', function (event) {
	const data = event.detail;

	dataStore.user = data.user;
	dataStore.pharma = data.pharma;

	document.dispatchEvent(new CustomEvent('user:dataReady', {detail: data.user}));
	document.dispatchEvent(new CustomEvent('pharma:dataReady', {detail: data.pharma}));
});

document.addEventListener('DOMContentLoaded', function () {
	if (currPageIs(AppURLs.page.login()) && JWT.auth.get()) {
		goTo(AppURLs.page.dashboard());
	} else if (!currPageIs(AppURLs.page.login()) && !currPageIs(AppURLs.page.root())) {
		appCheckAuth();
	}
});

document.addEventListener('appLoggedin', () => {
	if (noProfilingGoToQuestionary()) {
		document.dispatchEvent(new CustomEvent('appLoaded'));
	}
});

document.addEventListener('appLoggedin', function () {
	if (dataStore.user?.is_tester === true) {
		document.body.classList.add('is_tester');
	} else {
		document.body.classList.remove('is_tester');
	}
});

document.addEventListener('appLoggedin', function () {
	if (dataStore.user) {
		document.body.classList.add('user--'+dataStore.user.id);
		document.body.classList.add('user--'+dataStore.user.username);
	}
	if (dataStore.pharma) {
		document.body.classList.add('pharma--'+dataStore.pharma.id);
	}
});

document.addEventListener('appLoggedin', function () {
	const els = document.querySelectorAll('body [class^="show-on-pharma--"], body [class*=" show-on-pharma--"]');
	els.forEach(function(_el){
		const _class = Array.from(_el.classList).find(_class_name => _class_name.startsWith('show-on-pharma--'));
		if( ! _class ) return;
		const _pharma_id = parseInt(_class.replace('show-on-pharma--', ''));

		if( _pharma_id != dataStore.pharma.id ){
			_el.remove();
		}else{
			_el.classList.remove('d-none');
		}
	});
});

async function prepareAndSendBooking(params) {
	const id = params.id ?? '';
	const type = params.type;
	const datetime = params.datetime ?? '';
	const message = params.message;
	const payload = {id, type, datetime, message};

	const endpoint = type === 'event' ? AppURLs.api.bookEvent() : AppURLs.api.bookService();

	try {
		const res = await appFetchWithToken(endpoint, {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(payload),
		});

		const json = (await res.json?.()) ?? res;

		//if (json?.message) showToast(json.message);

		if (json?.success || json?.status) {
			document.dispatchEvent(
				new CustomEvent('bookServiceSuccess', {
					detail: json,
				})
			);
			return json;
		} else {
			document.dispatchEvent(
				new CustomEvent('bookServiceError', {
					detail: json,
				})
			);
			return json;
		}
	} catch (error) {
		showToast('Errore di rete. Riprova più tardi.');
		console.error('❌ Errore di rete nella prenotazione:', error);

		document.dispatchEvent(
			new CustomEvent('bookServiceError', {
				detail: {error},
			})
		);

		return {status: false, error};
	}
}

function createResponsiveImage(image = {}, options = {}) {
	const img = document.createElement('img');

	const fixUrl = (url) => url?.replace(/([^:]\/)\/+/g, '$1');
	const placeholder = 'https://via.placeholder.com/150';

	img.src = fixUrl(image?.src) || placeholder;
	img.alt = image?.alt || 'Immagine non disponibile';
	img.loading = 'lazy';

	img.classList.add('img-fluid', 'w-100');

	if (image?.width) img.setAttribute('width', image.width);
	if (image?.height) img.setAttribute('height', image.height);

	img.classList.add('responsive-img');
	if (options.class) img.classList.add(...options.class.split(' '));

	return img;
}

// funzioni per la stringa
function limitConsecutiveNewlines(str) {
	const max = 2;
	if (typeof str !== 'string') return str;
	const pattern = new RegExp(`(\n\s*){${max + 1},}`, 'g');
	return str.replace(pattern, '\n'.repeat(max));
}

// Convert newline to <br> html
function nl2br(str) {
	if (typeof str !== 'string') return str;
	return str.replace(/\n/g, '<br>');
}

function escapeHtml(str) {
	return String(str || '')
		.replaceAll('&', '&amp;')
		.replaceAll('<', '&lt;')
		.replaceAll('>', '&gt;')
		.replaceAll('"', '&quot;')
		.replaceAll("'", '&#39;');
}

function escapeAttr(str) {
	if (typeof str !== 'string') return str;
	return str
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;')
		.replace(/'/g, '&#039;');
}

// Verifica se Toastify è disponibile
function isToastifyAvailable() {
	return typeof Toastify !== 'undefined' && typeof Toastify === 'function';
}

// Gestione errore tramite Toastify
function showToast(message, type, ms, avatarUrl) {
	// Verifica se Toastify è disponibile
	if (!isToastifyAvailable()) {
		// Fallback: mostra un alert se Toastify non è disponibile
		alert(message);
		return;
	}

	const colors = {
		error: '#e74c3c',
		success: '#2ecc71',
		info: '#3498db',
		warning: '#f39c12',
	};

	try {
		const avatarImg = document.createElement('img');
		avatarImg.src = avatarUrl || dataStore.pharma.image_bot;
		avatarImg.alt = "Immagine dell'Assistente Farmacia";
		avatarImg.width = 48;
		avatarImg.height = 48;
		avatarImg.classList.add('toast-avatar');

		const msgWrapper = document.createElement('div');
		msgWrapper.classList.add('toast-message');
		msgWrapper.innerText = message;

		const wrapper = document.createElement('div');
		wrapper.classList.add('toast-body');
		wrapper.appendChild(avatarImg);
		wrapper.appendChild(msgWrapper);

		Toastify({
			node: wrapper,
			className: 'my-toast',
			duration: ms ?? 2000,
			close: true,
			gravity: 'bottom',
			position: 'center',
			style: {
				background: colors[type] || '#333',
			},
			stopOnFocus: true,
		}).showToast();
	} catch (error) {
		console.error('Errore Toastify:', error);
		// Fallback: mostra un alert
		alert(message);
	}
}

// Funzione per impostare cookie
function setCookie(name, value, days = 365, options = {}) {
	const expires = new Date();
	expires.setTime(expires.getTime() + days * 24 * 60 * 60 * 1000);

	// Opzioni di sicurezza per i cookie
	const secure = options.secure || window.location.protocol === 'https:';
	const sameSite = options.sameSite || 'Lax';

	let cookieString = `${name}=${value}; expires=${expires.toUTCString()}; path=/; SameSite=${sameSite}`;

	// Aggiungi Secure solo se siamo in HTTPS
	if (secure) {
		cookieString += '; Secure';
	}

	document.cookie = cookieString;
}

// Funzione per ottenere cookie
function getCookie(name) {
	const nameEQ = name + '=';
	const ca = document.cookie.split(';');
	for (let i = 0; i < ca.length; i++) {
		let c = ca[i];
		while (c.charAt(0) === ' ') c = c.substring(1, c.length);
		if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
	}
	return null;
}

// Funzione per estrarre user_id dal token JWT
function extractUserIdFromToken() {
	try {
		const token = JWT.auth.get();
		if (!token) return null;

		// Decodifica il payload del token JWT (seconda parte)
		const payload = token.split('.')[1];
		if (!payload) return null;

		// Decodifica base64
		const decodedPayload = JSON.parse(atob(payload));
		return decodedPayload.user_id || decodedPayload.sub || null;
	} catch (error) {
		return null;
	}
}

// Funzione per verificare lo status del questionario
async function checkQuestionaryStatus() {
	try {
		// Verifica se il cookie è già impostato a true (dal salvataggio del questionario)
		const existingCookie = getCookie('profiling_done');

		// SEMPRE chiama l'endpoint per verificare lo stato reale dal database
		// Non basarsi solo sul cookie che potrebbe essere obsoleto
		// (es. se init_profiling è NULL nel database, il cookie potrebbe essere true ma il server restituisce false)

		// Ottieni user_id dal dataStore o dal token
		let user_id = null;
		if (dataStore.user && dataStore.user.id) {
			user_id = dataStore.user.id;
		} else {
			// Estrai user_id dal token JWT come fallback
			user_id = extractUserIdFromToken();
			if (!user_id) {
				// Fallback: mantieni il cookie esistente
				if (existingCookie !== null) {
					return existingCookie === 'true';
				}
				setCookie('profiling_done', 'false', 365, {sameSite: 'Lax'});
				return false;
			}
		}

		// Chiama l'endpoint con user_id
		try {
			const url = `${AppURLs.api.getQuestionaryStatus()}?user_id=${user_id}`;

			const response = await appFetchWithToken(url);

			if (response.status && response.code === 200) {
				const hasProfiling = response.data?.has_profiling || false;

				// Imposta il cookie con il valore dal server
				setCookie('profiling_done', hasProfiling.toString(), 365, {sameSite: 'Lax'});

				return hasProfiling;
			} else {
				// In caso di errore, mantieni il valore esistente o imposta a false
				if (existingCookie === null) {
					setCookie('profiling_done', 'false', 365, {sameSite: 'Lax'});
					return false;
				}
				return existingCookie === 'true';
			}
		} catch (apiError) {
			// Se il cookie esiste già, mantienilo
			if (existingCookie !== null) {
				return existingCookie === 'true';
			}

			// Altrimenti imposta a false
			setCookie('profiling_done', 'false', 365, {sameSite: 'Lax'});
			return false;
		}
	} catch (error) {
		// In caso di errore generale, mantieni il valore esistente
		const existingCookie = getCookie('profiling_done');
		if (existingCookie === null) {
			setCookie('profiling_done', 'false', 365, {sameSite: 'Lax'});
			return false;
		}
		return existingCookie === 'true';
	}
}

// Versione sicura globale di showToast
window.safeShowToast = function (message, type = 'info') {
	try {
		if (isToastifyAvailable()) {
			showToast(message, type);
		} else {
			alert(message);
		}
	} catch (error) {
		console.error('Errore showToast:', error);
		alert(message);
	}
};

// Esponi le funzioni globalmente
window.appUtils = {
	checkQuestionaryStatus,
	setCookie,
	getCookie,
	isToastifyAvailable,
	extractUserIdFromToken,
	// Funzione di debug per verificare lo stato
	debugProfilingStatus: function () {
		const cookie = getCookie('profiling_done');
		const userId = dataStore.user?.id || extractUserIdFromToken();
		return {
			cookie: cookie,
			isCompleted: cookie === 'true',
			type: typeof cookie,
			userId: userId,
		};
	},
	// Funzione per testare la chiamata all'endpoint
	testQuestionaryStatus: async function () {
		try {
			const result = await checkQuestionaryStatus();
			return result;
		} catch (error) {
			return false;
		}
	},
	// Funzione per forzare il cookie a false (per test)
	forceCookieFalse: function () {
		setCookie('profiling_done', 'false', 365, {sameSite: 'Lax'});
	},
	// Funzione per verificare il cookie in tempo reale
	checkCookieNow: function () {
		const cookie = getCookie('profiling_done');
		return cookie;
	},
	// Funzione per forzare la chiamata all'endpoint (ignora cookie)
	forceEndpointCall: async function () {
		// Rimuovi temporaneamente il cookie per forzare la chiamata
		const originalCookie = getCookie('profiling_done');
		setCookie('profiling_done', '', 0, {sameSite: 'Lax'}); // Rimuovi cookie

		try {
			const result = await checkQuestionaryStatus();
			return result;
		} finally {
			// Ripristina il cookie originale se necessario
			if (originalCookie) {
				setCookie('profiling_done', originalCookie, 365, {sameSite: 'Lax'});
			}
		}
	},
};

function handleError(err, fallbackMessage) {
	fallbackMessage = 'Si è verificato un errore';

	let message = fallbackMessage;

	if (err instanceof Error && err.message) {
		message = err.message;
	} else if (typeof err === 'string') {
		message = err;
	} else if (err?.response?.data?.message) {
		message = err.response.data.message;
	}
}

function cbToastifyListenerError(evt) {
	const data = evt.detail;
	let message = data.message ?? 'Errore imprevisto, riprova.';

	// Usa showToast in modo sicuro
	try {
		showToast(message, 'error');
	} catch (error) {
		alert(message);
	}
}

(function injectToastify() {
	if (!document.getElementById('toastify-css')) {
		const link = document.createElement('link');
		link.id = 'toastify-css';
		link.rel = 'stylesheet';
		link.href = 'https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css';
		document.head.appendChild(link);
	}

	if (!document.getElementById('toastify-js')) {
		const script = document.createElement('script');
		script.id = 'toastify-js';
		script.src = 'https://cdn.jsdelivr.net/npm/toastify-js';
		script.onload = () => {};
		script.onerror = () => {};
		document.body.appendChild(script);
	}
})();

function aggiornaMese(titoloId, sottotitoloId) {
	const mesi = ['Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];

	const oggi = new Date();
	let mese = oggi.getMonth();
	const giorno = oggi.getDate();
	const anno = oggi.getFullYear();

	if (giorno > 25) {
		mese++;
		if (mese > 11) {
			mese = 0;
		}
	}

	const ultimoGiorno = new Date(anno, mese + 1, 0).getDate();

	const titoloElem = document.getElementById(titoloId);
	if (titoloElem) {
		titoloElem.textContent = `Offerte Esclusive di ${mesi[mese]}`;
	}

	const sottotitoloElem = document.getElementById(sottotitoloId);
	if (sottotitoloElem) {
		sottotitoloElem.textContent = `Valide fino al ${ultimoGiorno}/${(mese + 1).toString().padStart(2, '0')} o ad esaurimento scorte.`;
	}
}

function noProfilingGoToQuestionary() {
	if (dataStore?.user) {
		if (dataStore?.user?.has_profiling) {
			return true;
		}
	}

	if (currPageIs(AppURLs.page.login()) && JWT.auth.get()) return true;
	if (currPageIs(AppURLs.page.questionary())) return true;
	goTo(AppURLs.page.questionary());

	return false;
}

const PILL_CATEGORY_CLASSES = {
	'Alimentazione e Nutrizione': 'pill-bg-food',
	'Benessere Fisico e Movimento': 'pill-bg-move',
	'Gestione dello Stress e del Sonno': 'pill-bg-stress',
	'Salute e Prevenzione': 'pill-bg-health',
	'Cura della Pelle e Beauty Routine': 'pill-bg-beauty',
	'Supporto Cognitivo e Memoria': 'pill-bg-brain',
	'Benessere Naturale': 'pill-bg-nature',
	'Mamma e Bambino': 'pill-bg-mom',
};

function gestisciWellnessPoints(puntiBase) {
	const wp = document.getElementById('wellness-points');
	if (!wp) return;

	let i = 0;
	const varianti = [puntiBase, 'Scopri Premi'];

	let label = wp.querySelector('.points-label');
	if (!label) {
		label = document.createElement('span');
		label.className = 'points-label';
		wp.prepend(label);
	}

	label.textContent = puntiBase;

	// Cambio testo ogni 3 secondi
	//setInterval(() => {
	//	i = (i + 1) % varianti.length;
	//	label.textContent = varianti[i];
	//}, 3000);
}

// Gestione globale dei bottoni "Chiedi all'AI"
document.addEventListener('click', (e) => {
	let aiBtn = null;

	if( e.target.tagName == 'BUTTON' ){
		aiBtn = e.target;
	}else if( e.target.parentElement.tagName == 'BUTTON' ){
		aiBtn = e.target.parentElement;
	}

	if( ! aiBtn ) return;
	if( ! aiBtn.classList.contains('btn-ai') ) return;

	const nome = aiBtn.dataset.nome || 'Richiesta generica';
	const id = aiBtn.dataset.id || null;

	let tipo = undefined;
	if (document.body.classList.contains('page-events')) tipo = 'evento';
	else if (document.body.classList.contains('page-promotions')) tipo = 'promozione';
	else if (document.body.classList.contains('page-dashboard')) tipo = 'dashboard';
	else if (document.body.classList.contains('page-services')) tipo = 'servizio';

	const data = { nome, id };
	if( ! tipo ) data.prompt = nome;
	SmartAIContext.startChat(tipo, data);
});


function generateUUID() {
	return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
		const r = Math.random() * 16 | 0;
		const v = c === 'x' ? r : (r & 0x3 | 0x8);
		return v.toString(16);
	});
}

function encodeBase64(str) {
	let encoder = new TextEncoder();
	let bytes = encoder.encode(str);
	let binary = Array.from(bytes).map(b => String.fromCharCode(b)).join("");
	return btoa(binary);
}

function decodeBase64(base64) {
	let binary = atob(base64);
	let bytes = new Uint8Array(binary.length);
	for (let i = 0; i < binary.length; i++) {
		bytes[i] = binary.charCodeAt(i);
	}
	let decoder = new TextDecoder();
	return decoder.decode(bytes);
}

window.isLocalEnv = function isLocalEnv() {
	const hn = location.hostname;
	if (location.protocol === 'file:') return true;
	return hn === 'localhost' || hn === '127.0.0.1' || hn === '0.0.0.0' || hn === '::1' || hn.endsWith('.local');
};

//------------------------------
// Configurazione automatica per ambiente di sviluppo
if( isLocalEnv() ){
	AppURLs.page.root = () => 'http://127.0.0.1:8000';
	AppURLs.api.base = 'http://127.0.0.1:8002';
	AppURLs.panel.base = 'http://127.0.0.1:8001';

	if( window.location.pathname.includes(decodeBase64('anRhZi1yZXBv')) ){
		AppURLs.page.root = () => decodeBase64('aHR0cDovLzEyNy4wLjAuMS9qdW5nbGV0ZWFtL2FsdmluL2p0YWYtcmVwby9hc3Npc3RlbnRlX2Zhcm1hY2lhX2FwcA==');
		AppURLs.api.base = decodeBase64('aHR0cDovLzEyNy4wLjAuMS9qdW5nbGV0ZWFtL2FsdmluL2p0YWYtcmVwby9hc3Npc3RlbnRlX2Zhcm1hY2lhX2FwaQ==');
		AppURLs.panel.base = decodeBase64('aHR0cDovLzEyNy4wLjAuMS9qdW5nbGV0ZWFtL2FsdmluL2p0YWYtcmVwby9hc3Npc3RlbnRlX2Zhcm1hY2lhX3BhbmVs');
	}
}
