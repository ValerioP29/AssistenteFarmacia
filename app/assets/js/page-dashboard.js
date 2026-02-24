document.addEventListener('appLoaded', () => {
	document.addEventListener('pharma:dataReady', function (event) {
		const pharma = event.detail;

		// Chatbot Avatar
		const avatarImg = document.querySelector('.input-row img.avatar');
		if (avatarImg) {
			if (pharma?.image_bot) {
				avatarImg.src = pharma.image_bot;
				const altText = pharma.business_name ? `Avatar di ${pharma.business_name}` : 'Avatar chatbot';
				avatarImg.alt = altText;
				avatarImg.title = altText;
				avatarImg.style.display = '';
			} else {
				avatarImg.style.display = 'none';
			}
		}

		// Logo Img
		const logoImg = document.querySelector('.logo-box img');
		if (logoImg) {
			if (pharma?.image_logo) {
				logoImg.src = pharma.image_logo;
				const altText = pharma.business_name ? `Logo ${pharma.business_name}` : 'Logo Farmacia';
				logoImg.alt = altText;
				logoImg.title = altText;
				logoImg.style.display = '';
			} else {
				logoImg.style.display = 'none';
			}
		}

		// Nome farmacia
		const nameSpan = document.getElementById('namePharmacy');
		if (nameSpan && pharma?.business_name) {
			nameSpan.textContent = pharma.business_name;
		}

		// Pulsante chiamata
		const callBtn = document.getElementById('callBtn');
		if (callBtn && pharma?.phone_number) {
			callBtn.onclick = (e) => {
				e.preventDefault();
				window.location.href = `tel:${pharma.phone_number.replace(/\D/g, '')}`;
			};
		} else if (callBtn) {
			callBtn.style.display = 'none';
		}

		// Pulsante WhatsApp
		const chatBtn = document.getElementById('chatBtn');
		const whatsappNum = pharma.wa_number;
		if (chatBtn && whatsappNum) {
			chatBtn.onclick = (e) => {
				e.preventDefault();
				window.open(`https://wa.me/${whatsappNum.replace(/\D/g, '')}`, '_blank', 'noopener');
			};
		} else if (chatBtn) {
			chatBtn.style.display = 'none';
		}
	});

	document.addEventListener('user:dataReady', function (event) {
		const user = event.detail;
		if (typeof user.wellness_points === 'number') {
			gestisciWellnessPoints(`${user.wellness_points}`);
		}
	});

	if (dataStore?.pharma) {
		document.dispatchEvent(new CustomEvent('pharma:dataReady', {detail: dataStore.pharma}));
	}
	if (dataStore?.user) {
		document.dispatchEvent(new CustomEvent('user:dataReady', {detail: dataStore.user}));
	}

	function initSimpleSlider(root) {
		if (!root) return;
		if (root._sliderCleanup) root._sliderCleanup();
		const track = root.querySelector('.slider-promo');
		const btnPrev = root.querySelector('.slider-arrow-left');
		const btnNext = root.querySelector('.slider-arrow-right');
		const dotsContainer = root.querySelector('.slider-dots'); 
		if (!track || !btnPrev || !btnNext || !dotsContainer) return;

		dotsContainer.innerHTML = "";
		track.style.transform = 'translateX(0%)';
		let autoTimer = null;

		const total = track.children.length;
		const many = total > 1;
		btnPrev.classList.toggle('d-none', !many);
		btnNext.classList.toggle('d-none', !many);

		let currentIndex = 0;

		const dots = Array.from({ length: total }).map((_, i) => {
			const dot = document.createElement('div');
			dot.className = 'slider-dot' + (i === 0 ? ' active' : '');
			dot.addEventListener('click', () => {
				currentIndex = i;
				update();
				stopAuto();
				startAuto();
			});
			dotsContainer.appendChild(dot);
			return dot;
		});

		const updateDots = () => {
			dots.forEach((d, i) => d.classList.toggle('active', i === currentIndex));
		};

		const update = () => {
			track.style.transform = `translateX(-${currentIndex * 100}%)`;
			updateDots();
		};

		const next = () => {
			if (!many) return;
			currentIndex = (currentIndex + 1) % total;
			update();
		};
		const prev = () => {
			if (!many) return;
			currentIndex = (currentIndex - 1 + total) % total;
			update();
		};

		btnPrev.addEventListener('click', () => {
			prev();
			stopAuto();
			startAuto();
		});
		btnNext.addEventListener('click', () => {
			next();
			stopAuto();
			startAuto();
		});

		let startX = 0;
		track.addEventListener(
			'touchstart',
			(e) => {
				startX = e.touches[0].clientX;
			},
			{passive: true}
		);
		track.addEventListener(
			'touchend',
			(e) => {
				const diff = e.changedTouches[0].clientX - startX;
				if (diff > 50) prev();
				else if (diff < -50) next();
				stopAuto();
				startAuto();
			},
			{passive: true}
		);

		const startAuto = () => {
			if (!many) return;
			autoTimer = setInterval(next, 4000);
		};
		const stopAuto = () => {
			if (autoTimer) clearInterval(autoTimer);
			autoTimer = null;
		};

		root.addEventListener('keydown', (e) => {
			if (e.key === 'ArrowLeft') {
				prev();
				stopAuto();
				startAuto();
			}
			if (e.key === 'ArrowRight') {
				next();
				stopAuto();
				startAuto();
			}
		});

		update();
		startAuto();

		root._sliderCleanup = () => {
		clearInterval(autoTimer);
		btnPrev.replaceWith(btnPrev.cloneNode(true));
		btnNext.replaceWith(btnNext.cloneNode(true));
	};

	}

	// ======== FEATURED SERVICES CAROUSEL ========
	(function featuredServicesModule() {
		const featuredCard = document.getElementById('featured-services-card');
		const featuredRoot = document.getElementById('featured-services');
		if (!featuredRoot || !featuredCard) return;

		initFeaturedServices(featuredRoot).catch((e) => {
			console.warn('Featured services init failed:', e);
			featuredCard?.classList?.add('is-hidden');
		});

		async function initFeaturedServices(root) {
			const track = root.querySelector('.slider-promo');
			if (!track) return;
			track.style.transform = 'translateX(0%)';

			const base = AppURLs.api.getServices();
			const url = base + (base.includes('?') ? '&' : '?') + 'featured=1&_t=' + Date.now();

			const res = await appFetchWithToken(url, {method: 'GET'});
			if (!res?.status || !Array.isArray(res.data)) {
				featuredCard?.classList?.add('is-hidden');
				return;
			}

			const items = res.data.filter((s) => s.is_featured === true || s.isFeatured === true || s.is_featured === 1 || s.is_featured === '1' || s.isFeatured === 1 || s.isFeatured === '1');

			if (items.length === 0) {
				featuredCard?.classList?.add('is-hidden');
				return;
			}

			track.innerHTML = items
				.map((s) => {
					const id = s?.id;
					const title = escapeHtml(s?.title) || 'Servizio';
					const desc = escapeHtml(s?.description || '');
					const ci = s?.cover_image || null;
					const src = ci?.src || '';
					const alt = escapeHtml(ci?.alt || title);

					const targetHash = `?id=${id}`;
					const onClick = `goTo(AppURLs.page.services() + '${targetHash}')`;

					if (!ci) {
						return `
					<div class="slider-promo-item">
						<div class="svc-media no-img" role="button" tabindex="0" onclick="${onClick}">
						<div class="svc-text">
							<h3 class="svc-title">${title}</h3>
							${desc ? `<p class="svc-desc">${desc}</p>` : ''}
						</div>
						</div>
					</div>`;
					}

					return `
					<div class="slider-promo-item">
					<div class="svc-media" role="button" tabindex="0" onclick="${onClick}">
						<img class="slider-promo-img img-fluid" src="${src}" alt="${alt}" loading="lazy" />
						<div class="svc-glass"><h3 class="svc-title">${title}</h3></div>
					</div>
					</div>`;
				})
				.join('');

			initSimpleSlider(root);
		}
	})();

	(function promoSliderModule() {
		const promoRoot = document.querySelector('.slider-promo-container:not(#featured-services)');
		if (promoRoot) initSimpleSlider(promoRoot);
	})();

	ScrollToInfoCard();

	window.addEventListener('hashchange', ScrollToInfoCard);
});

function ScrollToInfoCard(e) {
	setTimeout(() => {
		if (window.location.hash === '#.farmacia') {
			window.scrollTo({
				top: document.body.scrollHeight,
				behavior: 'smooth',
			});
		} else if (window.location.hash === '#.home') {
			window.scrollTo({
				top: 0,
				behavior: 'smooth',
			});
		}
	}, 500);
}

window.addEventListener('pageshow', function(e){
    if (e.persisted) {
		appCheckAuth();
        return;
    }

    if (performance && performance.navigation && performance.navigation.type === 2) {
		appCheckAuth();
        return;
    }
});

