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

	// CREO L'HEADER
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

	// CREO IL FORM
	const form = document.createElement('form');
	form.id = 'quizForm';
	form.style.display = 'none';
	container.appendChild(form);

	// GENERO LE DOMANDE
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
					.map(
						([letter, ans]) => `
                            <label>
                                <input type="radio" name="${q.id}" value="${letter}" />
                                ${letter}. ${ans}
                            </label>`
					)
					.join('')}
            </div>
            ${index === quiz.questions.length - 1 ? `<button type="submit" class="submit-btn btn btn-primary mt-3" disabled>Scopri il tuo profilo</button>` : `<button type="button" class="next-btn btn btn-primary mt-3" disabled>Avanti</button>`}
        `;

		form.appendChild(block);
	});

	// GESTIONE BOTTONE INIZIA
	const startBtn = headerDiv.querySelector('.start-btn');
	startBtn.addEventListener('click', () => {
		headerDiv.style.display = 'none';
		form.style.display = 'block';

		const firstQuestion = form.querySelector('.question-block');
		if (firstQuestion) {
			firstQuestion.style.display = 'block';
			firstQuestion.classList.add('active');
		}
	});

	// Abilita i pulsanti quando selezioni una risposta
	form.querySelectorAll('.question-block').forEach((block) => {
		const radios = block.querySelectorAll('input[type="radio"]');
		const btn = block.querySelector('.next-btn, .submit-btn');

		if (btn) {
			radios.forEach((radio) => {
				radio.addEventListener('change', () => {
					btn.disabled = false;
				});
			});
		}
	});

	// Gestione pulsanti "Avanti"
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
			</div>
		`;
	}
});

function quizHandleSubmit(e, quiz) {
	e.preventDefault();

	const formData = new FormData(e.target);
	const answers = {};

	formData.forEach((value, key) => {
		answers[key] = value;
	});

	const payload = {
		quiz_id: quiz.id,
		answers: answers,
	};

	sendQuizAnswers(payload);

	const letters = Object.values(answers);
	const profileKey = quizCalculateProfile(letters);

	showQuizVerdict(profileKey, quiz);
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
				const msg = data.message || 'Errore nell’invio delle risposte';
				showToast(msg, 'error');
				document.dispatchEvent(new CustomEvent('quizResultError', {detail: {error: msg}}));
			}
		})
		.catch((err) => {
			showToast('Errore di rete durante l’invio del quiz', 'error');
			document.dispatchEvent(new CustomEvent('quizResultError', {detail: {error: err.message || err}}));
		});
}

document.addEventListener('quizResultSuccess', (event) => {
	// const data = event.detail;
	// showToast('Quiz completato', 'success');
});

function quizCalculateProfile(letters) {
	const tally = {};
	letters.forEach((l) => (tally[l] = (tally[l] || 0) + 1));
	return Object.keys(tally).reduce((a, b) => (tally[a] > tally[b] ? a : b)); // 🔥 ritorna "A", "B", "C" o "D"
}

function showQuizVerdict(profileKey, quiz) {
	const container = document.getElementById('quiz-container');
	container.innerHTML = '';

	const profile = quiz.profiles[profileKey];

	const miniGuide = quiz.mini_guide;

	const products = quiz.recommended_products[profileKey];

	const verdictDiv = document.createElement('div');
	verdictDiv.classList.add('quiz-verdict');

	verdictDiv.innerHTML = `
        <h2>${profile.title}</h2>
        <p>${profile.description}</p>
        ${profile.image ? `<img src="${profile.image.src}" alt="${profile.image.alt}" style="max-width:100%;margin:10px 0;" />` : ''}

        <div class="mini-guide">
            <h3>✨ Mini guida per te</h3>
            <p>${miniGuide.introduction}</p>
            <ul>
                ${miniGuide.advise.map((tip) => `<li>✅ ${tip}</li>`).join('')}
            </ul>
            <p><strong>${miniGuide.conclusion}</strong></p>
        </div>

        <div class="recommended-products">
            <h3>🛍️ Prodotti consigliati</h3>
            <ul>
                ${products.map((prod) => `<li>⭐ ${prod}</li>`).join('')}
            </ul>
        </div>
    `;

	container.appendChild(verdictDiv);
}
