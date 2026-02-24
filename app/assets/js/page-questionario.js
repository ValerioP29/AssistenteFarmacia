/*
 * QUESTIONARIO - GESTIONE AUTENTICAZIONE
 * 
 * ATTUALMENTE: Bypass autenticazione attivo - user_id fisso = 3
 * 
 * PER RIPRISTINARE L'AUTENTICAZIONE:
 * 1. Rimuovere il bypass in collectQuestionaryData()
 * 2. Decommentare il codice di verifica utente
 * 3. Decommentare la validazione user_id in validateQuestionaryData()
 * 4. Decommentare la gestione errori di autenticazione
 * 5. Aggiornare il backend per richiedere JWT token
 * 
 * TODO: Rimuovere tutti i commenti TODO quando si ripristina l'autenticazione
 */

document.addEventListener('appLoggedin', function () {
	if( dataStore.user.has_profiling ){
		goTo(AppURLs.page.dashboard());
		return;
	}
});

document.addEventListener('appLoaded', function () {
	// Reset selezioni
	document.querySelectorAll('input[type=radio]').forEach((el) => (el.checked = false));

	const slider = document.querySelector('.slider');
	const steps = document.querySelectorAll('.step');
	let currentStep = 0;

	steps.forEach((step, index) => {
		const inputs = step.querySelectorAll("input[type='radio']");
		const nextBtn = step.querySelector('.next-btn');
		const backBtn = step.querySelector('.back-link');

		// Gestione pulsante "Avanti"
		if (nextBtn) {
			nextBtn.disabled = true;

			inputs.forEach((input) => {
				input.addEventListener('change', () => {
					nextBtn.disabled = false;
				});
			});

			nextBtn.addEventListener('click', () => {
				if (currentStep < steps.length - 1) {
					currentStep++;
					updateSlider();
				}
			});
		}

		// Gestione pulsante "Indietro"
		if (backBtn) {
			backBtn.addEventListener('click', (e) => {
				e.preventDefault();
				if (currentStep > 0) {
					currentStep--;
					updateSlider();
				}
			});
		}
	});

	function updateSlider() {
		const offset = currentStep * -100;
		slider.style.transform = `translateX(${offset}%)`;
		window.scrollTo({top: 0, behavior: 'smooth'});
	}
});

document.addEventListener('appLoaded', function () {
	// Mostra messaggio di benvenuto per nuovo utente
	setTimeout(function(){
	safeShowToast('Benvenuto! Completa il tuo profilo per iniziare', 'info');
	}, 500);

	const checkboxes = document.querySelectorAll('input[name="q_argument[]"]');
	const submitButton = document.querySelector('.btn-register');

	if (checkboxes.length && submitButton) {
		checkboxes.forEach(function (checkbox) {
			checkbox.addEventListener('change', function () {
				const atLeastOneChecked = Array.from(checkboxes).some((cb) => cb.checked);
				submitButton.disabled = !atLeastOneChecked;
			});
		});

		submitButton.addEventListener('click', async function (e) {
			e.preventDefault();

			let questionaryData;
			
			try {
				// Raccogli i dati del questionario
				questionaryData = collectQuestionaryData();
				
				// Valida i dati
				if (!validateQuestionaryData(questionaryData)) {
					return;
				}
			} catch (error) {
				if (error.message === 'Utente non autenticato') {
					safeShowToast('Errore durante la raccolta dei dati', 'error');
				} else {
					safeShowToast('Errore durante la raccolta dei dati', 'error');
				}
				return;
			}

			// Disabilita il pulsante durante l'invio
			submitButton.disabled = true;
			submitButton.textContent = 'Invio in corso...';

			try {
				// Invia i dati all'API
				const response = await saveQuestionaryData(questionaryData);

				if (response.status) {
					safeShowToast(response.message || 'Questionario completato con successo!', 'success');
					document.dispatchEvent(new CustomEvent('questionarySuccess', { detail: response }));
				} else {
					document.dispatchEvent(new CustomEvent('questionaryError', { detail: { response } }));
					throw new Error(response.message || 'Errore durante il salvataggio');
				}
			} catch (error) {
				console.error('❌ Errore nel salvataggio del questionario:', error);
				safeShowToast('Errore durante il salvataggio. Riprova più tardi.', 'error');
				document.dispatchEvent(new CustomEvent('questionaryError', { detail: { error } }));
			} finally {
				// Riabilita il pulsante
				submitButton.disabled = false;
				submitButton.textContent = 'Completa Registrazione';
			}
		});
	}

	// Seleziona tutti i bottoni 'Prosegui' e 'Torna indietro'
	const nextButtons = document.querySelectorAll('.next-btn');
	const backLinks = document.querySelectorAll('.back-link');
	const slider = document.querySelector('.slider');
	let currentStep = 0;

	function goToStep(stepIndex) {
		currentStep = stepIndex;
		slider.style.transform = `translateX(-${currentStep * 100}%)`;

		// Se lo step corrente è il 3, attiva la logica del contatore
		if (currentStep === 3) {
			setupStep3Logic();
		}
	}

	// Aggiunge gli event listener ai bottoni 'Prosegui'
	nextButtons.forEach((button) => {
		button.addEventListener('click', function () {
			goToStep(currentStep + 1);
		});
	});

	// Aggiunge gli event listener ai bottoni 'Torna indietro'
	backLinks.forEach((link) => {
		link.addEventListener('click', function () {
			goToStep(currentStep - 1);
		});
	});

	function setupStep3Logic() {
		const step3 = document.querySelector('.step:nth-of-type(4)');
		if (!step3) return;

		if (!step3.querySelector('.counter-display')) {
			const checkboxes = step3.querySelectorAll('input[name="q_argument[]"]');
			const submitButton = step3.querySelector('.btn-register');
			const counterDisplay = document.createElement('p');

			counterDisplay.textContent = `Selezionati: 0 di 3`;
			counterDisplay.classList.add('text-center', 'mb-3', 'fw-bold', 'counter-display');
			step3.querySelector('.justify-content-center.align-items-center').prepend(counterDisplay);

			checkboxes.forEach(function (checkbox) {
				checkbox.addEventListener('change', function () {
					const selectedCount = step3.querySelectorAll('input[name="q_argument[]"]:checked').length;
					counterDisplay.textContent = `Selezionati: ${selectedCount} di 3`;

					submitButton.disabled = selectedCount === 0;

					if (selectedCount >= 3) {
						checkboxes.forEach(function (cb) {
							if (!cb.checked) {
								cb.disabled = true;
							}
						});
					} else {
						checkboxes.forEach(function (cb) {
							cb.disabled = false;
						});
					}
				});
			});
		}
	}
});

// Funzioni per la gestione dei dati del questionario
function collectQuestionaryData() {
	// Verifica che l'utente sia loggato
	if (!dataStore.user || !dataStore.user.id) {
		throw new Error('Utente non autenticato');
	}

	const data = {
		user_id: dataStore.user.id, // ID dell'utente loggato
		genere: document.querySelector('input[name="q_genere"]:checked')?.value || '',
		fascia_eta: document.querySelector('input[name="q_fascia_eta"]:checked')?.value || '',
		lifestyle: document.querySelector('input[name="q_lifestyle"]:checked')?.value || '',
		argomenti: []
	};

	// Raccogli gli argomenti selezionati
	document.querySelectorAll('input[name="q_argument[]"]:checked').forEach(checkbox => {
		data.argomenti.push(checkbox.value);
	});

	return data;
}

function validateQuestionaryData(data) {
	// Verifica che l'utente sia autenticato
	if (!data.user_id) {
		safeShowToast('Utente non autenticato. Effettua il login.', 'error');
		return false;
	}

	if (!data.genere) {
		safeShowToast('Seleziona il tuo genere', 'warning');
		return false;
	}
	
	if (!data.fascia_eta) {
		safeShowToast('Seleziona la tua fascia d\'età', 'warning');
		return false;
	}
	
	if (!data.lifestyle) {
		safeShowToast('Seleziona il tuo stile di vita', 'warning');
		return false;
	}
	
	if (data.argomenti.length === 0) {
		safeShowToast('Seleziona almeno un argomento di interesse', 'warning');
		return false;
	}
	
	if (data.argomenti.length > 3) {
		safeShowToast('Puoi selezionare al massimo 3 argomenti', 'warning');
		return false;
	}

	return true;
}

async function saveQuestionaryData(data) {
	try {
		const response = await appFetchWithToken(AppURLs.api.saveQuestionary(), {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json'
			},
			body: JSON.stringify(data)
		});


		return response;
	} catch (error) {
		console.error('❌ Errore nella chiamata API:', error);
		throw error;
	}
}

// Funzione helper per impostare cookie
function setCookie(name, value, days = 365) {
	const expires = new Date();
	expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
	document.cookie = `${name}=${value}; expires=${expires.toUTCString()}; path=/`;
}

// Funzione helper per ottenere il valore di un cookie
function getCookie(name) {
	const nameEQ = name + "=";
	const ca = document.cookie.split(';');
	for(let i = 0; i < ca.length; i++) {
		let c = ca[i];
		while (c.charAt(0) === ' ') c = c.substring(1, c.length);
		if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
	}
	return null;
}

// Event listeners per gestione success/error
document.addEventListener('questionarySuccess', (event) => {
	setTimeout(() => {
		goTo(AppURLs.page.dashboard());
	}, 2000);
});

document.addEventListener('questionaryError', (event) => {
	console.error('❌ Errore nel salvataggio del questionario:', event.detail.error);
});
