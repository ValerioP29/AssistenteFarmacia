// Configurazione dei prompt per ogni tipo di checkup
const CHECKUP_PROMPTS = {
	mani: "Non devi valutare condizioni cliniche né fornire diagnosi mediche basandoti sull'immagine. Limitati ad osservare l’immagine da un punto di vista cosmetico ed estetico e descrivi lo stato apparente delle mani, inclusa la pelle e le unghie (es. pelle secca, screpolata, arrossata, liscia, con macchie, unghie lucide, fragili, curate, ecc.), senza entrare in valutazioni cliniche o patologiche. Fornisci semplici consigli cosmetici e di benessere per migliorare l’aspetto e il comfort delle mani (come idratazione, protezione, esfoliazione delicata), e suggerisci 3 prodotti da banco acquistabili in farmacia o parafarmacia utili alla cura quotidiana di mani e unghie. Concludi precisando che per esigenze specifiche o persistenti è consigliabile rivolgersi a un dermatologo o a un’estetista qualificata.",
	occhi: "Non devi valutare condizioni cliniche o oftalmologiche né fornire diagnosi mediche basandoti sull'immagine. Limitati ad osservare l'immagine da un punto di vista cosmetico e descrivere lo stato apparente della zona del contorno occhi (es. presenta occhiaie scure, gonfiore, pelle secca, rughette sottili, pelle distesa, ecc.), senza entrare in valutazioni cliniche o patologiche. Fornisci semplici consigli cosmetici e di benessere, non medici, e suggerisci 3 prodotti da banco acquistabili in farmacia o parafarmacia che potrebbero aiutare a migliorare l'aspetto della zona perioculare. Alla fine, puoi aggiungere che per esigenze specifiche è consigliabile rivolgersi a un dermatologo o a uno specialista.",
	viso: "Rispondi SEMPRE in italiano. Analizza l'immagine fornita per un'analisi cosmetica della pelle. Osserva le caratteristiche visibili della pelle nell'immagine (es. presenza di secchezza, lucidità, pori dilatati, rossori, discromie, grana irregolare, pelle uniforme, ecc.) e descrivi lo stato apparente da un punto di vista cosmetico ed estetico. Fornisci consigli cosmetici e di benessere per migliorare l'aspetto della pelle (detersione, idratazione, trattamenti o ingredienti attivi) e suggerisci 3 prodotti da banco acquistabili in farmacia o parafarmacia utili nella skincare quotidiana. Questa è un'analisi puramente cosmetica, non medica. Concludi suggerendo di rivolgersi a un dermatologo o a un'estetista qualificata per un'analisi più approfondita.",
	capelli: "Non devi valutare condizioni cliniche né fornire diagnosi mediche basandoti sull'immagine. Limitati ad osservare l'immagine da un punto di vista cosmetico ed estetico e descrivi lo stato apparente dei capelli (es. appaiono secchi, lucidi, crespi, spenti, con doppie punte, radici grasse, cute secca, ecc.), senza entrare in valutazioni cliniche o patologiche. Fornisci semplici consigli cosmetici e di benessere per migliorare l’aspetto generale dei capelli, come pratiche di cura, suggerimenti di routine o ingredienti utili. Indica anche 3 prodotti da banco acquistabili in farmacia o parafarmacia (come shampoo, maschere, oli o trattamenti leave-in) che potrebbero aiutare a migliorarne l’aspetto. Infine, specifica che per problematiche più complesse o persistenti è consigliabile rivolgersi a un dermatologo o a un tricologo.",
	labbra: "Non devi valutare condizioni cliniche né fornire diagnosi mediche basandoti sull'immagine. Limitati ad osservare l'immagine da un punto di vista cosmetico e descrivere lo stato apparente delle labbra (es. appaiono secche, screpolate, idratate, lisce, con pellicine, ecc.), senza entrare in valutazioni cliniche. Fornisci poi semplici consigli cosmetici di cura delle labbra (idratazione, esfoliazione delicata, protezione), e suggerisci 3 prodotti da banco acquistabili in farmacia o parafarmacia utili a migliorarne l'aspetto e il comfort. Infine, osserva il tono e sottotono della pelle visibile (se chiara, media o scura, e se tende al caldo, freddo o neutro) e suggerisci il colore di rossetto più adatto, specificando anche la finitura consigliata (opaco, lucido, cremoso). Concludi dicendo che per consulenze più approfondite è possibile rivolgersi a un farmacista esperto o a un make-up artist professionista.",
	armocromia: "Rispondi SEMPRE in italiano. Analizza l'immagine fornita per un'analisi armocromatica cosmetica e stilistica. Osserva i colori presenti nell'immagine (pelle, occhi, capelli) e determina: 1) Il tono della pelle (chiaro, medio, scuro), 2) Il sottotono (caldo, freddo o neutro), 3) La stagione armocromatica (Primavera, Estate, Autunno, Inverno). Suggerisci 3 colori di make-up o abbigliamento che valorizzerebbero la persona in base alla stagione identificata, specificando anche la finitura ideale (opaca, brillante, satinata). Questa è un'analisi puramente cosmetica e stilistica, non medica. Concludi suggerendo di rivolgersi a un consulente d'immagine professionista per un'analisi più approfondita."
};

// Funzione per convertire file in base64
function fileToBase64(file) {
	return new Promise((resolve, reject) => {
		const reader = new FileReader();
		reader.readAsDataURL(file);
		reader.onload = () => {
			const base64Data = reader.result;
			console.log('Base64 generato:', base64Data.substring(0, 50) + '...');
			resolve(base64Data);
		};
		reader.onerror = error => reject(error);
	});
}

// Funzione per validare l'immagine
function validateImage(file) {
	const maxSize = 20 * 1024 * 1024; // 20MB
	const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
	
	if (file.size > maxSize) {
		throw new Error('L\'immagine deve essere inferiore a 20MB');
	}
	
	if (!allowedTypes.includes(file.type)) {
		throw new Error('Formato immagine non supportato. Usa JPEG, PNG, GIF o WEBP');
	}
	
	return true;
}

// Funzione per chiamare l'endpoint di checkup
    async function callCheckupEndpoint(prompt, imageBase64 = null, imageFormat = null, checkupType = 'unknown') {
	const payload = {
		prompt: prompt,
		checkupType: checkupType
	};

	if (imageBase64) {
		payload.image = imageBase64;
		payload.imageFormat = imageFormat;
	}

	console.log('Invio richiesta checkup a:', AppURLs.api.uploadCheckUp());
	console.log('Payload:', { 
		prompt: payload.prompt, 
		checkupType: payload.checkupType,
		imageFormat: payload.imageFormat, 
		hasImage: !!payload.image,
		imageLength: payload.image ? payload.image.length : 0
	});

	try {
		const response = await appFetchWithToken(AppURLs.api.uploadCheckUp(), {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json'
			},
			body: JSON.stringify(payload)
		});

		if (!response.status) {
			throw new Error(response.message || response.error || 'Errore durante l\'analisi');
		}

		 return { ...response, ...response.data };
	} catch (error) {
		console.error('Errore chiamata endpoint checkup:', error);
		throw error;
	}
}

// Funzione per mostrare l'animazione di caricamento
function playTemporaryLoadingAnimation(container) {
	container.innerHTML = `
		<div class="text-center">
			<div class="spinner-border text-success" role="status" style="width: 4rem; height: 4rem;"></div>
			<p>Analisi in corso...</p>
		</div>
	`;
}

// Funzione per visualizzare i risultati del checkup
function displayCheckupResults(data, container) {
	const {analysis} = data;

	let html = `
		<h3 class="fw-bold">Risultato del Check-Up</h3>
		<div class="analysis-content">
			${nl2br(limitConsecutiveNewlines(analysis))}
		</div>
		<div class="mt-4 text-center">
			<button type="button" class="btn btn-primary" id="newPhotoBtn">
				<i class="fas fa-camera"></i> Invia un'altra foto
			</button>
		</div>
	`;

	// Nascondi il form dopo l'analisi
	const form = document.getElementById('checkupForm');
	if (form) {
		form.style.display = 'none';
	}

	container.style.display = 'block';
	container.innerHTML = html;

	// Aggiungi event listener per il pulsante "Invia un'altra foto"
	const newPhotoBtn = document.getElementById('newPhotoBtn');
	if (newPhotoBtn) {
		newPhotoBtn.addEventListener('click', () => {
			resetCheckupForm();
		});
	}
}

// Funzione per resettare il form e permettere una nuova foto
function resetCheckupForm() {
	const form = document.getElementById('checkupForm');
	const resultDiv = document.getElementById('checkupResult');
	const fileInput = document.getElementById('checkupImage');
	const fileInfo = document.getElementById('fileInfo');
	const notes = document.getElementById('notes');

	// Reset del form
	if (form) {
		form.style.display = 'block';
		form.reset();
	}

	// Pulisci l'input file
	if (fileInput) {
		fileInput.value = '';
	}

	// Pulisci le informazioni del file
	if (fileInfo) {
		fileInfo.innerHTML = '';
	}

	// Pulisci le note
	if (notes) {
		notes.value = '';
	}

	// Nascondi i risultati
	if (resultDiv) {
		resultDiv.style.display = 'none';
		resultDiv.innerHTML = '';
	}

	// Scroll verso l'alto per mostrare il form
	window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Inizializzazione della pagina checkup
document.addEventListener('appLoaded', () => {
	const form = document.getElementById('checkupForm');
	const fileInput = document.getElementById('checkupImage');
	const resultDiv = document.getElementById('checkupResult');
	const fileInfo = document.getElementById('fileInfo');

	if (!form || !fileInput || !resultDiv) {
		console.error('Elementi del form checkup non trovati');
		return;
	}

	// Gestione submit del form
	form.addEventListener('submit', async (e) => {
		e.preventDefault();

		const file = fileInput.files[0];
		if (!file) {
			showToast('Carica una foto per l\'analisi.', 'warning');
			return;
		}

		const notes = document.getElementById('notes')?.value || '';
		const checkupType = form.dataset.checkupType || 'unknown';

		// Validazione immagine
		try {
			validateImage(file);
		} catch (error) {
			showToast(error.message, 'error');
			return;
		}

		// Mostra animazione di caricamento
		resultDiv.innerHTML = '<div class="loading">Analisi in corso... 🧬</div>';
		resultDiv.style.display = 'block';
		playTemporaryLoadingAnimation(resultDiv);

		try {
			// Ottieni il prompt specifico per il tipo di checkup
			const basePrompt = CHECKUP_PROMPTS[checkupType] || 'Analizza questa immagine e fornisci consigli di benessere.';
			
			// Aggiungi note se presenti
			const finalPrompt = notes.trim() 
				? `${basePrompt}\n\nNote aggiuntive: ${notes}`
				: basePrompt;

			// Converti immagine in base64
			const imageBase64 = await fileToBase64(file);
			const imageFormat = file.name.split('.').pop().toLowerCase();

			// Chiama l'endpoint
            const result = await callCheckupEndpoint(finalPrompt, imageBase64, imageFormat, checkupType);
			
			if (result?.message) {
             showToast(result.message, 'success'); // oppure 'info' se vuoi più neutro
            }
			
			// Mostra i risultati
			displayCheckupResults(result, resultDiv);

		} catch (err) {
			console.error('Errore durante l\'analisi:', err);
			resultDiv.innerHTML = `<div class="alert alert-danger">${err.message}</div>`;
		}
	});

	// Gestione selezione file
	fileInput.addEventListener('change', () => {
		const file = fileInput.files[0];

		if (file) {
			// Validazione immediata
			try {
				validateImage(file);
				
				fileInfo.innerHTML = `
					<div class="alert alert-secondary d-flex justify-content-between align-items-center">
						<span><i class="fas fa-file-image"></i> ${file.name}</span>
						<button type="button" class="btn btn-sm btn-primary" id="removeFile">
							<i class="fas fa-times"></i> Rimuovi
						</button>
					</div>
				`;

				document.getElementById('removeFile').addEventListener('click', () => {
					fileInput.value = '';
					fileInfo.innerHTML = '';
				});
			} catch (error) {
				showToast(error.message, 'error');
				fileInput.value = '';
				fileInfo.innerHTML = '';
			}
		} else {
			fileInfo.innerHTML = '';
		}
	});
});
