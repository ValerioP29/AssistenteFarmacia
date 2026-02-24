// Funzione per caricare la configurazione GTM
function loadGTMConfig() {
	// Controlla se la configurazione è già caricata
	if (window.GTM_CONFIG) return Promise.resolve();

	// Controlla se lo script è già stato caricato
	if (document.querySelector('script[src*="gtm-config.js"]')) {
		return new Promise((resolve) => {
			const checkConfig = () => {
				if (window.GTM_CONFIG) {
					resolve();
				} else {
					setTimeout(checkConfig, 50);
				}
			};
			checkConfig();
		});
	}

	// Carica il file di configurazione
	return new Promise((resolve, reject) => {
		const script = document.createElement('script');
		script.src = './assets/js/gtm-config.js';
		script.async = true;

		script.onload = () => {
			resolve();
		};

		script.onerror = () => {
			console.error('❌ Errore nel caricamento di gtm-config.js');
			reject();
		};

		document.head.appendChild(script);
	});
}

// Funzione per iniettare GTM nell'head
function injectGTMHead() {
	// Se la configurazione non è ancora caricata, caricala e riprova
	if (!window.GTM_CONFIG) {
		loadGTMConfig().then(() => {
			setTimeout(injectGTMHead, 50);
		});
		return;
	}

	if (!window.GTM_CONFIG.enabled) return;

	// Controlla se GTM è già stato iniettato
	if (document.querySelector('script[data-gtm="head"]')) return;

	const gtmScript = document.createElement('script');
	gtmScript.setAttribute('data-gtm', 'head');
	gtmScript.innerHTML = `
		(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
		new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
		j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
		'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
		})(window,document,'script','dataLayer','${window.GTM_CONFIG.containerId}');
	`;

	// Inserisci il primo script nell'head
	document.head.insertBefore(gtmScript, document.head.firstChild);

	if (window.GTM_CONFIG.debug) {
		console.log('✅ GTM Head script iniettato');
	}
}

// Funzione per iniettare GTM noscript nel body
function injectGTMBody() {
	// Se la configurazione non è ancora caricata, caricala e riprova
	if (!window.GTM_CONFIG) {
		loadGTMConfig().then(() => {
			setTimeout(injectGTMBody, 50);
		});
		return;
	}

	if (!window.GTM_CONFIG.enabled) return;

	// Controlla se GTM noscript è già stato iniettato
	if (document.querySelector('noscript[data-gtm="body"]')) return;

	const gtmNoscript = document.createElement('noscript');
	gtmNoscript.setAttribute('data-gtm', 'body');
	gtmNoscript.innerHTML = `
		<iframe src="https://www.googletagmanager.com/ns.html?id=${window.GTM_CONFIG.containerId}"
		height="0" width="0" style="display:none;visibility:hidden"></iframe>
	`;

	// Inserisci il noscript subito dopo l'apertura del body
	document.body.insertBefore(gtmNoscript, document.body.firstChild);

	if (window.GTM_CONFIG.debug) {
		console.log('✅ GTM Body noscript iniettato');
	}
}

class AppHeader extends HTMLElement {
	// static observedAttributes = ["color", "size"];

	constructor() {
		super();
	}

	connectedCallback() {
		// Inietta GTM nell'head quando il componente header viene caricato
		if (!isLocalEnv()) injectGTMHead();

		this.innerHTML = `
			<header class="header top-bar">
				<div class="header-inner">
				<a class="logo" role="button" onclick="goTo(AppURLs.page.dashboard()); return false;">
					<img class="img-fluid" src="./assets/images/assistente-farmacia-logo.png" width="240" height="33" alt="AssistenteFarmacia.it" />
				</a>

				<div class="toolbar">
					<app-cart-icon></app-cart-icon>
					<button class="btn btn-trigger-menu" title="Apri il menù">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" width="1em" height="1em" fill="currentColor">
						<path d="M0 96C0 78.3 14.3 64 32 64l384 0c17.7 0 32 14.3 32 32s-14.3 32-32 32L32 128C14.3 128 0 113.7 0 96zM0 256c0-17.7 14.3-32 32-32l384 0c17.7 0 32 14.3 32 32s-14.3 32-32 32L32 288c-17.7 0-32-14.3-32-32zM448 416c0 17.7-14.3 32-32 32L32 448c-17.7 0-32-14.3-32-32s14.3-32 32-32l384 0c17.7 0 32 14.3 32 32z"/>
					</svg>
					</button>
					<app-menu></app-menu> 
				</div>
				</div>
			</header>
		`;

		// Carica automaticamente il componente Add to Home Banner
		this.loadAddToHomeScript();

		const hamburger = this.querySelector('.btn-trigger-menu');
		const appMenu = this.querySelector('app-menu');

		hamburger.addEventListener('click', (e) => {
			e.stopPropagation();
			appMenu.toggle();
		});
	}

	loadAddToHomeScript() {
		// Controlla se lo script è già stato caricato
		if (document.querySelector('script[src*="add-to-home.js"]')) {
			return;
		}

		// Crea e aggiungi lo script
		const script = document.createElement('script');
		script.src = './assets/js/add-to-home.js';
		script.async = true;

		// Aggiungi listener per quando lo script è caricato
		script.onload = () => {
			// Script caricato silenziosamente
		};

		script.onerror = () => {
			console.error('❌ Errore nel caricamento di add-to-home.js');
		};

		document.head.appendChild(script);
	}

	// disconnectedCallback() { console.log("Custom element removed from page."); }
	// connectedMoveCallback() { console.log("Custom element moved with moveBefore()"); }
	// adoptedCallback() { console.log("Custom element moved to new page."); }
	// attributeChangedCallback(name, oldValue, newValue) { console.log(`Attribute ${name} has changed.`); }
}

class AppBody extends HTMLElement {
	constructor() {
		super();
	}
	connectedCallback() {
		// Inietta GTM noscript nel body quando il componente body viene caricato
		if (!isLocalEnv()) {
			injectGTMBody();
		}

		const content = this.innerHTML;
		this.innerHTML = `<main>
			${content}
		</main>`;
	}
}

class AppFooter extends HTMLElement {
	constructor() {
		super();
		this.classList.add('site-footer');
		this._onClick = this._onClick.bind(this);
		this._onHashChange = this._onHashChange.bind(this);
		this._onPopState = this._onPopState.bind(this);
	}

	connectedCallback() {
		this.render();
		document.body.classList.add('has-bottom-nav');
		this.addEventListener('click', this._onClick);

		window.addEventListener('hashchange', this._onHashChange);
		window.addEventListener('popstate', this._onPopState);
	}

	disconnectedCallback() {
		this.removeEventListener('click', this._onClick);
		document.body.classList.remove('has-bottom-nav');
		window.removeEventListener('hashchange', this._onHashChange);
		window.removeEventListener('popstate', this._onPopState);
	}

	render() {
		this.innerHTML = '';
		this.innerHTML += this.#template();
		this.innerHTML += `<app-cart-icon></app-cart-icon>`;
		this.innerHTML += `<app-cart></app-cart>`;
		this._syncActive();
	}

	_onClick(e) {
		const btn = e.target.closest('.bn-item');
		if (!btn) return;
		this._setActive(btn);
	}

	_onHashChange() {
		this._syncActive();
	}

	_onPopState() {
		this._syncActive();
	}

	_onAppLoaded() {
		this._syncActive();
	}

	_setActive(btn) {
		this.querySelectorAll('.bn-item').forEach((a) => {
			a.classList.remove('active');
			a.removeAttribute('aria-current');
		});
		btn.classList.add('active');
		btn.setAttribute('aria-current', 'page');
	}

	_syncActive() {
		const url = window.location.href;
		let active = null;

		if (url.includes('#.farmacia')) {
			active = this.querySelector('.bn-item.farmacia');
		} else if (url.includes('#promo')) {
			active = this.querySelector('.bn-item.promo');
		} else if (url.includes('#fidelity')) {
			active = this.querySelector('.bn-item.fidelity');
		} else if (url.includes('#assistente')) {
			active = this.querySelector('.bn-item.assistente');
		} else if (url.includes('#.home')) {
			active = this.querySelector('.bn-item.home');
		} else {
			active = this.querySelector('.bn-item.home');
		}

		if (active) this._setActive(active);
	}

	#template() {
		return `
      <nav class="bottom-nav v6" role="navigation" aria-label="Navigazione principale">
        <div class="rail">
          <a class="bn-item home" onclick="goTo(AppURLs.page.dashboard() + '#.home')" data-default-active="true">
            ${this.#iconHome()}<span>Home</span>
          </a>
          <a class="bn-item farmacia" onclick="goTo(AppURLs.page.dashboard() + '#.farmacia')">
            ${this.#iconFarmacia()}<span>Farmacia</span>
          </a>
          <a class="bn-item promo" onclick="goTo(AppURLs.page.promotions() + '#promo')">
            ${this.#iconPromo()}<span>Promo</span>
          </a>
          <a class="bn-item fidelity" onclick="goTo(AppURLs.page.wellnessPoints() + '#fidelity')">
            ${this.#iconFidelity()}<span>Fidelity</span>
          </a>
          <a class="bn-item assistente" onclick="goTo(AppURLs.page.chatbot() + '#assistente')">
            ${this.#iconAssistente()}<span>Assistente</span>
          </a>
        </div>
      </nav>
    `;
	}
	/*if (version === 'v5') {
			return `
          <nav class="bottom-nav v5" role="navigation" aria-label="Navigazione principale">
            <div class="rail">
              <a class="bn-item" href="#home">
                ${this.#iconHome()}<span>Home</span>
              </a>
              <a class="bn-item" href="#farmacia">
                ${this.#iconFarmacia()}<span>Farmacia</span>
              </a>
              <a class="bn-item" href="#promo">
                ${this.#iconPromo()}<span>Promo</span>
              </a>
              <a class="bn-item" href="#fidelity">
                <span class="points" aria-label="Punti disponibili">
                  ${this.#iconStarMini()}<span class="points-value">34</span>
                </span>
                ${this.#iconFidelity()}<span>Fidelity</span>
              </a>
              <a class="bn-item" href="#assistente">
                ${this.#iconAssistente()}<span>Assistente</span>
              </a>
            </div>
          </nav>
        `;
		}

		// v4
		return `
        <nav class="bottom-nav v4" role="navigation" aria-label="Navigazione principale">
          <div class="rail">
            <a class="bn-item" href="#home" data-default-active="true">
              ${this.#iconHome()}<span>Home</span>
            </a>
            <a class="bn-item" href="#farmacia">
              ${this.#iconFarmacia()}<span>Farmacia</span>
            </a>
            <a class="bn-item" href="#promo">
              ${this.#iconPromo()}<span>Promo</span>
            </a>
            <a class="bn-item" href="#assistente">
              ${this.#iconAssistente()}<span>Assistente</span>
            </a>
          </div>
        </nav>
      `;*/

	// --- ICONS ---
	#iconHome() {
		return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M3 11l9-8 9 8"/><path d="M5 10v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V10"/>
      </svg>`;
	}
	#iconFarmacia() {
		return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <rect x="3" y="3" width="18" height="14" rx="3"/><path d="M8 21h8"/><path d="M12 14v-4M10 12h4"/>
      </svg>`;
	}
	#iconPromo() {
		return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M3 12l8-8 10 10-8 8L3 12z"/><path d="M7.5 7.5l9 9"/>
      </svg>`;
	}
	#iconAssistente() {
		return `<svg xmlns="http://www.w3.org/2000/svg" 
    viewBox="0 0 24 24" 
    fill="none" 
    stroke="currentColor" 
    stroke-width="1.8" 
    stroke-linecap="round" 
    stroke-linejoin="round" 
    aria-hidden="true">
      <path d="M21 11.5a8.38 8.38 0 0 1-9 8.5 
               8.5 8.5 0 0 1-4-1l-5 2 
               1.7-4.5a8.5 8.5 0 1 1 16.3-5z"/>
  </svg>`;
	}

	#iconFidelity() {
		return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M4 7h16v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7z"/><path d="M16 7V5a4 4 0 0 0-8 0v2"/><path d="M8 12h8M8 15h5"/>
      </svg>`;
	}
}

document.addEventListener('appLoaded', () => {
	setTimeout(() => {
		const footer = document.querySelector('app-footer');
		if (footer && typeof footer._syncActive === 'function') {
			footer._syncActive();
		}
	}, 300);
});

class AppMenu extends HTMLElement {
	constructor() {
		super();
		this.handleOutsideClick = this.handleOutsideClick.bind(this);
	}

	connectedCallback() {
		if (!this.querySelector('#appMenu')) {
			this.innerHTML = `
        <div class="menu app-menu" id="appMenu">
          <nav>
            <a role="button" onclick="goTo(AppURLs.page.profile())"><i class="fa-solid fa-user mx-2"></i> Gestione profilo</a>
            <a role="button" onclick="goTo(AppURLs.page.pillArchive())"><i class="fas fa-info-circle mx-2"></i> Archivio pillole informative</a>
            <a role="button" onclick="goTo(AppURLs.page.archiveOrder())"><i class="fas fa-box mx-2"></i> Archivio ordini</a>
            <a role="button" onclick="goTo(AppURLs.page.preferPharma())"><i class="fas fa-map-marker-alt mx-2"></i> Gestione farmacia preferita</a>
            <a role="button" id="add-to-home-menu-btn" onclick="showAddToHomeBanner(); return false;"><i class="fas fa-home mx-2"></i> Installa App</a>
            <a role="button" onclick="appLogout(); return false;"><i class="fas fa-sign-out-alt mx-2"></i> Logout</a>
          </nav>
        </div>
      `;
		}

		this.close();

		document.addEventListener('click', this.handleOutsideClick);
	}

	disconnectedCallback() {
		document.removeEventListener('click', this.handleOutsideClick);
	}

	get menuEl() {
		return this.querySelector('#appMenu');
	}

	get headerEl() {
		return this.closest('.header, app-header') || null;
	}

	get hamburgerEl() {
		return this.headerEl?.querySelector('.btn-trigger-menu') || null;
	}

	open() {
		document.body.classList.add('show-menu');
	}

	close() {
		document.body.classList.remove('show-menu');
	}

	toggle() {
		document.body.classList.toggle('show-menu');
	}

	handleOutsideClick(event) {
		const isOpen = document.body.classList.contains('show-menu');
		if (!isOpen) return;

		const clickedInsideMenu = this.contains(event.target);
		const clickedOnHamburger = this.hamburgerEl && this.hamburgerEl.contains(event.target);

		if (!clickedInsideMenu && !clickedOnHamburger) this.close();
	}
}

class AppGoBack extends HTMLElement {
	constructor() {
		super();
	}
	connectedCallback() {
		if (currPageIs(AppURLs.page.dashboard())) return;
		var page = this.getAttribute('page') ?? 'AppURLs.page.dashboard()';
		this.innerHTML = `<a class="btn-goback" role="button" onclick="goTo(${page});">
			← Torna alla home
		</a>`;
	}
}

class Card extends HTMLElement {
	constructor() {
		super();
	}

	connectedCallback() {
		var type = this.getAttribute('type');

		const content = this.innerHTML;
		this.innerHTML = `<div class="card">
			${content}
		</div>`;

		if (type) this.children[0].classList.add('card--' + type);
	}
}

class QuickAction extends HTMLElement {
	constructor() {
		super();
	}

	connectedCallback() {
		const label = this.getAttribute('label');
		const action = this.getAttribute('action');
		const type = this.getAttribute('type');
		const target = this.getAttribute('target');
		const valueAttr = this.getAttribute('value');

		const button = document.createElement('button');
		button.className = 'quick-action btn btn-primary me-2';
		button.textContent = label;

		button.addEventListener('click', (e) => {
			e.preventDefault();

			let value = '';
			if (valueAttr) {
				try {
					value = JSON.parse(valueAttr);
				} catch (err) {
					console.warn('Valore JSON malformato:', valueAttr);
				}
			}

			handleQuickAction(type, action, target, value);
		});

		this.innerHTML = '';
		this.appendChild(button);
	}
}

window.appCartModal = null;

class AppCart extends HTMLElement {
	constructor() {
		super();
		this.handleCartUpdate = this.handleCartUpdate.bind(this);
	}

	connectedCallback() {
		this.render();
		this.setupModalEvents();
		document.addEventListener('cartUpdated', this.handleCartUpdate);
		//CartUtils.fetchCartSync();
	}

	disconnectedCallback() {
		document.removeEventListener('cartUpdated', this.handleCartUpdate);
	}

	render() {
		// salvo la modale globale per aprirla da AppCartIcon
		window.appCartModal = this;

		this.innerHTML = `
			<div class="cart-overlay hidden">
				<div class="cart-modal">
				<button class="cart-close" aria-label="Chiudi carrello">
					<i class="fas fa-times"></i>
				</button>
				<h3>Riepilogo Carrello</h3>
				<ul class="cart-items"></ul>
				<div class="cart-total">Totale: € 0</div>
				<button class="cart-cta btn btn-primary w-100">Conferma prenotazione</button>
				<div class="cart-clear-wrapper">
					<button id="clearCartBtn" class="btn btn-link text-danger small mt-2 text-decoration-none">Svuota carrello</button>
				</div>
				</div>
			</div>
		`;

		this.updateUI(CartUtils.getCartItems());

		this.querySelector('#clearCartBtn')?.addEventListener('click', () => {
			const item = localStorage.getItem('jta_app_cart');
			if (!item || item === '[]') {
				showToast('Carrello già svuotato', 'warning', 2000);
				return;
			}
			const confirmClear = confirm('Sei sicuro di voler svuotare il carrello?');
			if (confirmClear) {
				CartUtils.clearCart();
				showToast('Carrello svuotato!', 'success');
			} else {
				showToast('Operazione annullata', 'info');
			}
		});
	}

	setupModalEvents() {
		const overlay = this.querySelector('.cart-overlay');
		const modal = this.querySelector('.cart-modal');
		if (!overlay || !modal) return;

		// Chiudi cliccando sull'overlay
		overlay.addEventListener('click', () => this.closeModal());

		// Impedisci la chiusura se clicchi DENTRO la modale
		modal.addEventListener('click', (e) => e.stopPropagation());

		modal.querySelector('.cart-close')?.addEventListener('click', (e) => {
			e.stopPropagation();
			this.closeModal();
		});

		modal.querySelector('.cart-cta')?.addEventListener('click', () => {
			const items = CartUtils.getCartItems();
			if (items.length === 0) {
				showToast('Carrello vuoto. Aggiungi almeno un prodotto', 'info');
				return;
			}
			CartUtils.fetchSendOrder({items});
		});

		modal.addEventListener('click', (e) => {
			const target = e.target;

			if (target.classList.contains('remove')) {
				const id = parseInt(target.dataset.id);
				CartUtils.removeFromCart(id);
				showToast('Prodotto rimosso dal carrello', 'warning');
			}

			if (target.classList.contains('qty')) {
				const id = parseInt(target.dataset.id);
				const delta = parseInt(target.dataset.delta);
				CartUtils.updateQuantity(id, delta);
			}
		});
	}

	handleCartUpdate(e) {
		this.updateUI(e.detail);
	}

	updateUI(items) {
		const list = this.querySelector('.cart-items');
		const total = this.querySelector('.cart-total');
		const ctaBtn = this.querySelector('.cart-cta');
		if (!list || !total || !ctaBtn) return;

		list.innerHTML = items
			.map(
				(item) => `
				<li>
					<img src="${item.image?.src || ''}" alt="${item.image?.alt || ''}" />
					<div class="info">
						<strong>${item.name}</strong>
						<div class="quantity">
							<button class="qty" data-id="${item.id}" data-delta="-1">−</button>
							<span>${item.quantity}</span>
							<button class="qty" data-id="${item.id}" data-delta="1">+</button>
						</div>
						<span class="price ${item.price ? '' : ' d-none ' }">€ ${(item.price * item.quantity).toFixed(2)}</span>
					</div>
					<button class="remove" data-id="${item.id}">
						<i class="fas fa-trash-alt delete-item"></i>
					</button>
				</li>
			`
			)
			.join('');

		total.textContent = `Totale: € ${CartUtils.getTotal()}`;

		if (items.length === 0) {
			ctaBtn.setAttribute('disabled', 'disabled');
			ctaBtn.classList.add('disabled');
		} else {
			ctaBtn.removeAttribute('disabled');
			ctaBtn.classList.remove('disabled');
		}
	}

	openModal() {
		this.querySelector('.cart-overlay')?.classList.remove('hidden');
		document.body.classList.add('cart-modal-visible');
	}

	closeModal() {
		this.querySelector('.cart-overlay')?.classList.add('hidden');
		document.body.classList.remove('cart-modal-visible');
	}
}

class AppCartIcon extends HTMLElement {
	constructor() {
		super();
		this.handleCartUpdate = this.handleCartUpdate.bind(this);
	}

	connectedCallback() {
		this.render();
		document.addEventListener('cartUpdated', this.handleCartUpdate);

		// click sul bottone, apre la modale
		this.querySelector('.btn-cart')?.addEventListener('click', () => {
			window.appCartModal?.openModal();
		});
	}

	disconnectedCallback() {
		document.removeEventListener('cartUpdated', this.handleCartUpdate);
	}

	render() {
		this.innerHTML = `
			<button class="btn-cart">
				<i class="fas fa-shopping-cart"></i>
				<span class="cart-badge">0</span>
			</button>
		`;
		this.updateUI(CartUtils.getCartItems());
	}

	handleCartUpdate() {
		this.updateUI(CartUtils.getCartItems());
	}

	updateUI(items) {
		const button = this.querySelector('.btn-cart');
		const badge = this.querySelector('.cart-badge');
		if (!badge) return;

		// somma quantità dei prodotti
		const totalQuantity = items.reduce((sum, item) => sum + item.quantity, 0);
		badge.textContent = totalQuantity;

		if (totalQuantity === 0) {
			button.style.display = 'none';
		} else {
			button.style.display = 'inline-flex';
		}
	}
}

customElements.define('app-header', AppHeader);
customElements.define('app-body', AppBody);
customElements.define('app-footer', AppFooter);
customElements.define('app-menu', AppMenu);
customElements.define('app-goback', AppGoBack);
customElements.define('app-card', Card);
customElements.define('quick-action', QuickAction);
customElements.define('app-cart', AppCart);
customElements.define('app-cart-icon', AppCartIcon);
