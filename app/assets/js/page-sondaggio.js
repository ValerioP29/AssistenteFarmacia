async function getSurveyById() {
	const params = new URLSearchParams(window.location.search);
	const survey_id = params.get('id') ? params.get('id') : null;

	const surveyContainer = document.querySelector('.survey__wrapper');
	if (surveyContainer) surveyContainer.classList.remove('d-none');

	try {
		const url = AppURLs.api.getSurvey(survey_id ?? '')
		const res = await appFetchWithToken(url);

		if (!res || !res.status || !res.data) {
			const error = (res?.message || 'Errore caricamento sondaggio.');
			document.dispatchEvent(new CustomEvent('survey:fetch:error', {detail: { survey_id: survey_id, error: error }}));
			return;
		}

		const data = res.data;
		document.dispatchEvent(new CustomEvent('survey:fetch:success', {detail: { survey_id: survey_id, survey: data }}));
	} catch (err) {
		const error = 'Errore di rete.';
		document.dispatchEvent(new CustomEvent('survey:fetch:error', {detail: { survey_id: survey_id, error: error }}));
	}
}

function showSurveyError(message) {
	const surveyWrapper = document.querySelector('.survey__wrapper');
	surveyWrapper.classList.remove('d-none');
	surveyWrapper.innerHTML = `
		<div class="alert alert-warning text-center p-3">
			${message}
		</div>
	`;
}

function buildSurveyHTML(survey) {
	document.body.dataset.surveyId = survey.id;

	const titleEl = document.querySelector('.survey-title');
	if(titleEl) titleEl.textContent = survey.title;

	const subtitleEl = document.querySelector('.survey-subtitle');
	if(subtitleEl) subtitleEl.textContent = survey.subtitle;

	survey.questions.forEach((q, index) => {
		const stepEl = document.querySelectorAll('.step')[index];
		if (!stepEl) return;

		const title = stepEl.querySelector('.step-title');
		if (title) title.textContent = q.question;

		const answersWrapper = stepEl.querySelector('.answer-wrapper');
		if (answersWrapper) {
			answersWrapper.innerHTML = q.answers
				.map( (a, i) => `
					<div class="form-check">
						<input class="form-check-input" type="radio" name="q${index + 1}" id="q${index + 1}${i}" value="${a.value}">
						<label class="form-check-label" for="q${index + 1}${i}">${a.label}</label>
					</div>
				` )
				.join('')
			;
		}
	});

	rebindSliderLogic(survey);
}

function rebindSliderLogic(survey) {
	const slider = document.querySelector('.slider');
	const steps = document.querySelectorAll('.step');

	let current = 0;
	let answers = {};

	const update = () => {
		slider.style.transform = `translateX(${-100 * current}%)`;
		slider.style.transition = 'transform 0.4s ease';
		window.scrollTo({top: 0, behavior: 'smooth'});
	};

	steps.forEach((step, index) => {
		const radios = step.querySelectorAll("input[type='radio']");
		const nextBtn = step.querySelector('.next-btn');
		const backBtn = step.querySelector('.back-link');
		const submitBtn = step.querySelector('.btn-submit');

		if (nextBtn) {
			radios.forEach((r) => r.addEventListener('change', () => (nextBtn.disabled = false)));
			nextBtn.addEventListener('click', () => {
				current++;
				update();
			});
		}

		if (backBtn) {
			backBtn.addEventListener('click', () => {
				current--;
				update();
			});
		}

		if (submitBtn) {
			radios.forEach((r) => r.addEventListener('change', () => (submitBtn.disabled = false)));

			submitBtn.addEventListener('click', () => {
				answers = {};
				steps.forEach((s, i) => {
					const sel = s.querySelector('input[type="radio"]:checked');
					if (sel) answers[`q${i + 1}`] = sel.value;
				});

				const counts = {A: 0, B: 0, C: 0, D: 0};
				Object.values(answers).forEach((v) => counts[v]++);

				const max = Math.max(...Object.values(counts));
				const profile = Object.keys(counts).find((k) => counts[k] === max);

				current = steps.length - 1;
				update();

				const survey_id = document.body.dataset.surveyId ?? null;

				submitSurveyResults({
					survey_id: survey_id,
					profile: profile,
					counts: counts,
				});

				showProfileResult(profile, survey);
			});
		}
	});

	update();
}

function showProfileResult(profile, survey) {
	const resultBox = document.querySelector('#results .result-block');
	if (!resultBox) return;
	const data = survey.profiles[profile];

	const productMap = {};
	(survey.products || []).forEach((p) => (productMap[p.id] = p));

	let html = `
		<h3>${data.title}</h3>
		<p>${data.text}</p>
	`;

	if (data.products && data.products.length > 0) {
		if (data.advice && data.advice.trim() !== '') {
			const lines = data.advice
				.split('\n')
				.map(l => l.trim())
				.filter(l => l !== '');

			let adviceTitle = '';
			let adviceItems = [];

			lines.forEach(line => {
				if (line.toLowerCase() === 'consigli') {
					adviceTitle = line;
				} else if (line.startsWith('•')) {
					adviceItems.push(line.replace(/^•\s*/, ''));
				}
			});

			html += `<div class="survey__advice mt-3">`;

			if (adviceTitle) {
				html += `<p class="fw-bold mb-2">${adviceTitle}</p>`;
			}

			if (adviceItems.length) {
				html += `<ul class="ps-3">`;
				adviceItems.forEach(item => {
					html += `<li class='text-decoration-none'>${item}</li>`;
				});
				html += `</ul>`;
			}

			html += `</div>`;
		}


		html += `<ul>`;
			data.products.forEach((pid) => {
				const p = productMap[pid];
				if (!p) return;

				let pHtml = [];
				pHtml.push(p.name || "Prodotto");
				if(p.price && !p.price_hidden){
					if(p.price_sale && p.price_sale != p.price_regular) {
						pHtml.push(`<del>€${p.price_regular}</del> <ins><b>€${p.price_sale}</b></ins>`);
					}else {
						pHtml.push(`<ins><b>€${p.price}</b></ins>`);
					}
				}

				html += `
					<li class="result-li">
						<a href="#" onclick="goTo(AppURLs.page.promotions()+'?id=${p.id}'); return false;">
							${pHtml.join(' - ')}
						</a>
						${p.description ? `<p>- ${p.description}</p>` : ''}
					</li>
				`;
			});
		html += `</ul>`;
	}

	if (data.pediatric && data.pediatric.id) {
		const pid = data.pediatric.id;
		const p = productMap[pid];

		if (p) {
			let priceHtml = "";

			if (p.price && !p.price_hidden) {
				if (p.price_sale && p.price_sale != p.price_regular) {
					priceHtml = `<del>€${p.price_regular}</del> <ins><b>€${p.price_sale}</b></ins>`;
				} else {
					priceHtml = `<ins><b>€${p.price}</b></ins>`;
				}
			}

			html += `
				<div class="mt-3">
					<p class="fw-bold">${data.pediatric.label}</p>
					<ul>
						<li class="result-li">
							<a href="#" onclick="goTo(AppURLs.page.promotions()+'?id=${p.id}'); return false;">
								${p.name}
								${priceHtml ? ' - ' + priceHtml : ''}
							</a>
							${p.description ? `<p>- ${p.description}</p>` : ''}
						</li>
					</ul>
				</div>
			`;
		}
	}

	if (survey.pharmacist_tip && survey.pharmacist_tip.text) {
		const tip = survey.pharmacist_tip.text;

		html += `
			<div class="mt-4">
				<p class="fw-bold">${survey.pharmacist_tip.title || "Consiglio del farmacista"}</p>
				<p>${tip.intro}</p>
				<ul class="text-start">
					${tip.items.map(i => `<li> ${i}</li>`).join('')}
				</ul>
			</div>
		`;
	}

	if (survey.cta && survey.cta.url && survey.cta.text) {
		html += `
			<div class="text-center mt-3">
				<p class="fw-bold mb-3">${survey.summary || ""}</p>
				<a href="#" onclick="goTo('${survey.cta.url}')" class="mt-2 btn btn-primary btn-lg" style="background:#e5d2ff;color:#7937ab;">
					${survey.cta.text}
				</a>
			</div>
		`;
	}

	resultBox.innerHTML = html;
}

async function submitSurveyResults(payload) {
	try {
		const res = await appFetchWithToken(AppURLs.api.saveSurvey(), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(payload),
		});

		if (res?.message) {
			showToast(res.message, res.status ? 'success' : 'warning');
		}

		if(res.status){
			document.dispatchEvent(new CustomEvent('survey:sent:success', {detail: { survey_id: payload.survey_id, counts: payload.counts }}));
		}else{
			document.dispatchEvent(new CustomEvent('survey:sent:error', {detail: { survey_id: payload.survey_id, error: res?.message }}));
		}
	} catch (error) {
		showToast('Errore durante il salvataggio del sondaggio.', 'error');
		document.dispatchEvent(new CustomEvent('survey:sent:error', {detail: { survey_id: payload.survey_id, error: 'Errore durante il salvataggio del sondaggio.' }}));
	}
}

document.addEventListener('survey:fetch:success', function(e){
	buildSurveyHTML(e.detail.survey);
});

document.addEventListener('survey:fetch:error', function(e){
	showSurveyError(e.detail.error);
});

document.addEventListener('appLoaded', function () {
	getSurveyById();
});
