document.addEventListener('appLoaded', () => {
	if (!currPageIs(AppURLs.page.quiz())) return;
	fetchQuiz();
});

function fetchQuiz() {
	appFetchWithToken(AppURLs.api.getQuiz(), {
		method: 'GET',
		headers: {'Content-Type': 'application/json'},
	})
		.then((data) => {
			if (data.status) {
				document.dispatchEvent(new CustomEvent('quizLoaded', {detail: data.data}));
			} else {
				const msg = data.message || 'Errore nel caricamento del quiz';
				document.dispatchEvent(new CustomEvent('quizError', {detail: {error: msg}}));
			}
		})
		.catch((err) => {
			document.dispatchEvent(new CustomEvent('quizError', {detail: {error: err.message || err}}));
		});
}

document.addEventListener('quizLoaded', (event) => {
	const quiz = event.detail;
	const container = document.getElementById('quiz-container');
	if (!container) {
		console.error('⚠️ Nessun container #quiz-container');
		return;
	}

	const headerDiv = document.createElement('div');
	headerDiv.classList.add('quiz-header');
	headerDiv.innerHTML = `
        <h2><i class="fas fa-sun"></i> ${quiz.header.title}</h2>
        <p>${quiz.header.description}</p>
        <div class="step-indicator">📋 ${quiz.header.steps} domande in totale</div>
        <button class="start-btn btn btn-primary">INIZIA</button>
    `;

	container.innerHTML = '';
	container.appendChild(headerDiv);

	const form = document.createElement('form');
	form.id = 'quizForm';
	form.style.display = 'none';
	container.appendChild(form);

	quiz.questions.forEach((q, index) => {
		const block = document.createElement('div');
		block.classList.add('question-block');
		block.id = `${q.id}block`;
		block.style.display = 'none';

		block.innerHTML = `
            <div class="step-indicator">Domanda ${index + 1} di ${quiz.questions.length}</div>
            <div class="question">${index + 1}. ${q.text}</div>
            <div class="answers">
                ${Object.entries(q.answers)
					.map(([letter, ans]) => `
                        <label>
                            <input type="radio" name="${q.id}" value="${letter}" />
                            ${letter}. ${ans}
                        </label>`)
					.join('')}
            </div>
            ${index === quiz.questions.length - 1
				? `<button type="submit" class="submit-btn btn btn-primary mt-3" disabled>Scopri il tuo profilo</button>`
				: `<button type="button" class="next-btn btn btn-primary mt-3" disabled>Avanti</button>`}
        `;

		form.appendChild(block);
	});

	headerDiv.querySelector('.start-btn').addEventListener('click', () => {
		headerDiv.style.display = 'none';
		form.style.display = 'block';
		const firstQuestion = form.querySelector('.question-block');
		if (firstQuestion) {
			firstQuestion.style.display = 'block';
			firstQuestion.classList.add('active');
		}
	});

	form.querySelectorAll('.question-block').forEach((block) => {
		const radios = block.querySelectorAll('input[type="radio"]');
		const btn = block.querySelector('.next-btn, .submit-btn');
		if (btn) {
			radios.forEach((radio) => radio.addEventListener('change', () => { btn.disabled = false; }));
		}
	});

	form.querySelectorAll('.next-btn').forEach((btn) => {
		btn.addEventListener('click', () => {
			const current = btn.closest('.question-block');
			const currentId = parseInt(current.id.replace('q', '').replace('block', ''));
			const next = form.querySelector('#q' + (currentId + 1) + 'block');
			current.classList.remove('active');
			setTimeout(() => {
				current.style.display = 'none';
				if (next) {
					next.style.display = 'block';
					setTimeout(() => next.classList.add('active'), 50);
				}
			}, 300);
		});
	});

	form.addEventListener('submit', (e) => quizHandleSubmit(e, quiz));
});

document.addEventListener('quizError', (event) => {
	console.error('Errore quiz:', event.detail.error);
	const container = document.getElementById('quiz-container');
	if (container) {
		container.innerHTML = `
			<div class="quiz-error">
				<h2>😢 Oops!</h2>
				<p class="mb-0">${event.detail.error || 'Non è stato possibile caricare il quiz. Riprova più tardi.'}</p>
			</div>`;
	}
});

function quizHandleSubmit(e, quiz) {
	e.preventDefault();
	const formData = new FormData(e.target);
	const answers = {};
	formData.forEach((value, key) => { answers[key] = value; });
	sendQuizAnswers({ quiz_id: quiz.id, answers });
	showQuizVerdict(quizCalculateProfile(Object.values(answers)), quiz);
}

function sendQuizAnswers(payload) {
	appFetchWithToken(AppURLs.api.sendQuiz(), {
		method: 'POST',
		headers: {'Content-Type': 'application/json'},
		body: JSON.stringify(payload),
	})
		.then((data) => {
			if (data.status) {
				document.dispatchEvent(new CustomEvent('quizResultSuccess', {detail: data.data}));
			} else {
				const msg = data.message || 'Errore nell\'invio delle risposte';
				showToast(msg, 'error');
				document.dispatchEvent(new CustomEvent('quizResultError', {detail: {error: msg}}));
			}
		})
		.catch((err) => {
			showToast('Errore di rete durante l\'invio del quiz', 'error');
			document.dispatchEvent(new CustomEvent('quizResultError', {detail: {error: err.message || err}}));
		});
}

document.addEventListener('quizResultSuccess', (event) => {});

function quizCalculateProfile(letters) {
	const tally = {};
	letters.forEach((l) => (tally[l] = (tally[l] || 0) + 1));
	return Object.keys(tally).reduce((a, b) => (tally[a] > tally[b] ? a : b));
}

function showQuizVerdict(profileKey, quiz) {
	const container = document.getElementById('quiz-container');
	container.innerHTML = '';

	const profile  = quiz.profiles[profileKey];
	const miniGuide = quiz.mini_guide;
	const products  = quiz.recommended_products[profileKey];

	const verdictDiv = document.createElement('div');
	verdictDiv.classList.add('quiz-verdict');
	verdictDiv.innerHTML = `
        <h2>${profile.title}</h2>
        <p>${profile.description}</p>
        ${profile.image ? `<img src="${profile.image.src}" alt="${profile.image.alt}" style="max-width:100%;margin:10px 0;" />` : ''}

        <div class="mini-guide">
            <h3>✨ Mini guida per te</h3>
            <p>${miniGuide.introduction}</p>
            <ul>${miniGuide.advise.map((tip) => `<li>✅ ${tip}</li>`).join('')}</ul>
            <p><strong>${miniGuide.conclusion}</strong></p>
        </div>

        <div class="recommended-products">
            <h3>🛍️ Prodotti consigliati</h3>
            <ul>${products.map((prod) => `<li>⭐ ${prod}</li>`).join('')}</ul>
        </div>

        <div id="quiz-related-products" class="quiz-related-products mt-4">
            <h3>🏪 Disponibili in farmacia</h3>
            <div id="quiz-related-list" class="related-products-list">
                <div class="related-loading text-muted">
					<span class="spinner-border spinner-border-sm me-2" role="status"></span>
					Stiamo cercando i prodotti più adatti per te...
				</div>
            </div>
        </div>
    `;

	container.appendChild(verdictDiv);

	// Carica i prodotti reali dopo aver mostrato il verdetto
	loadQuizRelatedProducts(quiz.quiz_tag ?? '', dataStore?.pharma?.id ?? 0);
}

// ── Prodotti reali correlati al quiz ─────────────────────────────────────────

async function loadQuizRelatedProducts(quizTag, pharmaId) {
    const list = document.getElementById('quiz-related-list');
    if (!list || !pharmaId) {
        document.getElementById('quiz-related-products')?.remove();
        return;
    }

    list.innerHTML = `
        <div class="related-loading text-muted py-2">
            <span class="spinner-border spinner-border-sm me-2" role="status"></span>
            Stiamo generando i tuoi consigli personalizzati...
        </div>`;

    try {
        const url = new URL(AppURLs.api.getPromos());
        url.searchParams.set('limit', '3');
        if (quizTag) url.searchParams.set('tag', quizTag);

        const data = await appFetchWithToken(url.toString(), { method: 'GET' });
        const products = data?.data?.products ?? [];

        if (!products.length) {
            document.getElementById('quiz-related-products')?.remove();
            return;
        }

        list.innerHTML = '';
        products.forEach((p) => list.appendChild(buildQuizProductCard(p)));

    } catch (e) {
        document.getElementById('quiz-related-products')?.remove();
    }
}

function buildQuizProductCard(item) {
    const card = document.createElement('article');
    card.className = 'related-product-card';

    const imgObj  = item.image?.src ? item.image.src : (item.image || '');
    const src     = imgObj || (AppURLs.api.base + '/uploads/images/placeholder-product.jpg');
    const promoUrl = AppURLs.page.promotions() + '?id=' + item.id;

    const _sale = parseFloat(item.price_sale);
    const _reg  = parseFloat(item.price_regular);
    const showDiscount = item.is_on_sale && isFinite(_sale) && _sale > 0 && isFinite(_reg) && _sale < _reg;
    const _display = showDiscount ? _sale : (isFinite(_reg) && _reg > 0 ? _reg : null);

    const priceHtml = _display
        ? (showDiscount
            ? `<span class="related-product-card__price--original">${quizFormatCurrency(_reg)}</span>
               <span class="related-product-card__price--discounted">${quizFormatCurrency(_sale)}</span>`
            : `<span class="related-product-card__price">${quizFormatCurrency(_display)}</span>`)
        : '';

    card.innerHTML = `
        <a href="${escapeHtml(promoUrl)}" class="related-product-card__link">
            <div class="related-product-card__image-wrap">
                <img class="related-product-card__image" src="${escapeHtml(src)}"
                    alt="${escapeHtml(item.name)}" loading="lazy" />
            </div>
            <div class="related-product-card__content">
                <div class="related-product-card__name">${escapeHtml(item.name)}</div>
                <div class="related-product-card__meta">${priceHtml}</div>
            </div>
        </a>`;

    const img = card.querySelector('img');
    img?.addEventListener('error', () => {
        if (img.dataset.fb === '1') return;
        img.dataset.fb = '1';
        img.src = AppURLs.api.base + '/uploads/images/placeholder-product.jpg';
    });

    return card;
}

function quizFormatCurrency(v) {
	const n = parseFloat(v);
	if (!n || n <= 0) return '';
	return new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' }).format(n);
}