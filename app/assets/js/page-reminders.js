document.addEventListener('appLoaded', () => {
	// Inizializzazione della pagina
	initializeRemindersPage();
});

// Funzione di inizializzazione
function initializeRemindersPage() {
	// Inizializza tab Terapia
	initializeTherapyTab();

	// Inizializza tab Scadenza
	initializeExpiryTab();

	// Carica dati iniziali
	loadReminders();
	loadExpiryReminders();
}

// Inizializzazione tab Terapia
function initializeTherapyTab() {
	const form = document.getElementById('reminderForm');
	const frequencySelect = document.getElementById('frequency');
	const customTimesDiv = document.querySelector('.custom-times');
	const timeSlotsContainer = document.getElementById('timeSlots');

	// Nascondi la sezione orari all'inizializzazione
	customTimesDiv.style.display = 'none';

	// Gestione frequenza
	frequencySelect.addEventListener('change', function () {
		if (this.value) {
			customTimesDiv.style.display = 'block';
			// Imposta orari predefiniti in base alla frequenza
			setDefaultTimes(this.value);
		} else {
			customTimesDiv.style.display = 'none';
		}
	});

	// Gestione submit form terapia
	form.addEventListener('submit', function (e) {
		e.preventDefault();
		handleTherapyFormSubmit();
	});
}

// Inizializzazione tab Scadenza
function initializeExpiryTab() {
	const form = document.getElementById('expiryForm');

	// Gestione submit form scadenza
	form.addEventListener('submit', function (e) {
		e.preventDefault();
		handleExpiryFormSubmit();
	});
}

// Funzione per impostare orari predefiniti
function setDefaultTimes(frequency) {
	const timeSlotsContainer = document.getElementById('timeSlots');
	timeSlotsContainer.innerHTML = '';

	let times = [];
	switch (frequency) {
		case 'daily':
			times = ['08:00'];
			break;
		case 'twice_daily':
			times = ['08:00', '20:00'];
			break;
		case 'three_times':
			times = ['08:00', '14:00', '20:00'];
			break;
		default:
			times = ['08:00'];
	}

	times.forEach((time) => {
		const slot = document.createElement('div');
		slot.className = 'time-slot';
		slot.innerHTML = `
			<input type="time" class="form-control time-input" value="${time}" required>
		`;
		timeSlotsContainer.appendChild(slot);
	});

	// Assicurati che la sezione orari sia visibile
	document.querySelector('.custom-times').style.display = 'block';
}

// Funzione per gestire il submit del form terapia
async function handleTherapyFormSubmit() {
	const form = document.getElementById('reminderForm');

	// Raccogli dati dal form
	const reminderData = {
		medicationName: document.getElementById('medicationName').value,
		dosage: document.getElementById('dosage').value,
		startDate: document.getElementById('startDate').value,
		endDate: document.getElementById('endDate').value,
		frequency: document.getElementById('frequency').value,
		notes: document.getElementById('notes').value,
		times: [],
		productPhoto: null,
	};

	// Raccogli orari (sia per frequenze predefinite che personalizzate)
	const timeInputs = document.querySelectorAll('.time-input');
	timeInputs.forEach((input) => {
		if (input.value) {
			reminderData.times.push(input.value);
		}
	});

	// Gestione foto del farmaco
	const photoInput = document.getElementById('prescriptionFile');
	if (photoInput.files.length > 0) {
		const file = photoInput.files[0];
		// Converti il file in base64 per l'invio
		reminderData.productPhoto = await convertFileToBase64(file);
	}

	// Validazione
	if (!validateTherapyData(reminderData)) {
		return;
	}

	try {
		// Simula chiamata API (sostituire con chiamata reale)
		const success = await saveReminder(reminderData);

		if (success) {
			// Reset form
			form.reset();
			document.querySelector('.custom-times').style.display = 'none';
			document.getElementById('timeSlots').innerHTML = `
				<div class="time-slot">
					<input type="time" class="form-control time-input" required>
				</div>
			`;

			// Ricarica lista
			loadReminders();

			// Mostra messaggio di successo
			showToast('Promemoria terapia aggiunto con successo!', 'success');
		}
	} catch (error) {
		handleError(error, 'Errore durante il salvataggio. Riprova.');
	}
}

// Funzione per gestire il submit del form scadenza
async function handleExpiryFormSubmit() {
	const form = document.getElementById('expiryForm');

	// Raccogli dati dal form
	const expiryData = {
		productName: document.getElementById('productName').value,
		expiryDate: document.getElementById('expiryDate').value,
		reminderAlerts: document.getElementById('reminderAlerts').value,
		notes: document.getElementById('expiryNotes').value,
		productPhoto: null,
	};

	// Gestione upload foto
	const photoInput = document.getElementById('productPhoto');
	if (photoInput.files.length > 0) {
		const file = photoInput.files[0];
		// Converti il file in base64 per l'invio
		expiryData.productPhoto = await convertFileToBase64(file);
	}

	// Validazione
	if (!validateExpiryData(expiryData)) {
		return;
	}

	try {
		// Simula chiamata API (sostituire con chiamata reale)
		const success = await saveExpiryReminder(expiryData);

		if (success) {
			// Reset form
			form.reset();

			// Ricarica lista
			loadExpiryReminders();

			// Mostra messaggio di successo
			showToast('Promemoria scadenza aggiunto con successo!', 'success');
		}
	} catch (error) {
		handleError(error, 'Errore durante il salvataggio. Riprova.');
	}
}

// Funzione di validazione terapia
function validateTherapyData(data) {
	if (!data.medicationName.trim()) {
		showToast('Inserisci il nome del farmaco', 'error');
		return false;
	}

	if (!data.dosage.trim()) {
		showToast('Inserisci il dosaggio', 'error');
		return false;
	}

	if (!data.startDate) {
		showToast('Seleziona la data di inizio', 'error');
		return false;
	}

	if (!data.endDate) {
		showToast('Seleziona la data di fine', 'error');
		return false;
	}

	if (new Date(data.startDate) > new Date(data.endDate)) {
		showToast('La data di fine deve essere successiva alla data di inizio', 'error');
		return false;
	}

	if (!data.frequency) {
		showToast('Seleziona la frequenza', 'error');
		return false;
	}

	if (data.times.length === 0) {
		showToast('Inserisci almeno un orario di assunzione', 'error');
		return false;
	}

	return true;
}

// Funzione di validazione scadenza
function validateExpiryData(data) {
	if (!data.productName.trim()) {
		showToast('Inserisci il nome del prodotto', 'error');
		return false;
	}

	if (!data.expiryDate) {
		showToast('Seleziona la data di scadenza', 'error');
		return false;
	}

	if (new Date(data.expiryDate) <= new Date()) {
		showToast('La data di scadenza deve essere futura', 'error');
		return false;
	}

	// Verifica che sia selezionato un avviso di promemoria
	if (!data.reminderAlerts) {
		showToast("Seleziona quando ricevere l'avviso di promemoria", 'error');
		return false;
	}

	return true;
}

// Funzione per salvare promemoria terapia
async function saveReminder(data) {
	try {
		// Crea FormData per multipart/form-data
		const formData = new FormData();
		formData.append('medicationName', data.medicationName);
		formData.append('dosage', data.dosage);
		formData.append('startDate', data.startDate);
		formData.append('endDate', data.endDate);
		formData.append('frequency', data.frequency);
		formData.append('times', JSON.stringify(data.times));
		formData.append('notes', data.notes || '');

		// Aggiungi foto se presente
		if (data.productPhoto) {
			// Converti base64 in file
			const photoFile = await base64ToFile(data.productPhoto, 'medication-photo.jpg');
			formData.append('file', photoFile);
		}

		const response = await appFetchWithToken(AppURLs.api.addTherapyReminder(), {
			method: 'POST',
			body: formData,
		});

		if (response.status && response.data) {
			return true;
		} else {
			throw new Error(response.message || 'Errore durante il salvataggio');
		}
	} catch (error) {
		console.error('Errore API saveReminder:', error);
		throw error;
	}
}

// Funzione per salvare promemoria scadenza
async function saveExpiryReminder(data) {
	try {
		// Crea FormData per multipart/form-data
		const formData = new FormData();
		formData.append('productName', data.productName);
		formData.append('expiryDate', data.expiryDate);
		// Converti il valore della select nel formato JSON per il backend
		const alerts = {
			alert30: data.reminderAlerts === '30',
			alert15: data.reminderAlerts === '15',
			alert7: data.reminderAlerts === '7',
			alert1: data.reminderAlerts === '1',
		};
		formData.append('alerts', JSON.stringify(alerts));
		formData.append('notes', data.notes || '');

		// Aggiungi foto se presente
		if (data.productPhoto) {
			// Converti base64 in file
			const photoFile = await base64ToFile(data.productPhoto, 'product-photo.jpg');
			formData.append('file', photoFile);
		}

		const response = await appFetchWithToken(AppURLs.api.addExpiryReminder(), {
			method: 'POST',
			body: formData,
		});

		if (response.status && response.data) {
			return true;
		} else {
			throw new Error(response.message || 'Errore durante il salvataggio');
		}
	} catch (error) {
		console.error('Errore API saveExpiryReminder:', error);
		throw error;
	}
}

// Funzione per caricare promemoria terapia
async function loadReminders() {
	try {
		// Simula chiamata API
		const reminders = await fetchReminders();
		// Normalizza i dati
		const normalizedReminders = reminders.map(normalizeReminderData);
		renderRemindersList(normalizedReminders);
	} catch (error) {
		handleError(error, 'Errore durante il salvataggio. Riprova.');
	}
}

// Funzione per caricare promemoria scadenza
async function loadExpiryReminders() {
	try {
		// Simula chiamata API
		const expiryReminders = await fetchExpiryReminders();
		// Normalizza i dati
		const normalizedExpiryReminders = expiryReminders.map(normalizeExpiryData);
		renderExpiryList(normalizedExpiryReminders);
	} catch (error) {
		handleError(error, 'Errore durante il salvataggio. Riprova.');
	}
}

// Funzione per recuperare promemoria terapia
async function fetchReminders() {
	try {
		const response = await appFetchWithToken(AppURLs.api.getTherapyReminders());

		if (response.status && response.data) {
			return response.data;
		} else {
			console.warn("Nessun dato ricevuto dall'API, uso array vuoto");
			return [];
		}
	} catch (error) {
		console.error('Errore API fetchReminders:', error);
		// In caso di errore, prova a caricare dati di fallback
		try {
			const fallbackResponse = await fetch('./data/promemoria.json');
			const fallbackData = await fallbackResponse.json();
			return fallbackData;
		} catch (fallbackError) {
			console.error('Errore anche nel caricamento fallback:', fallbackError);
			return [];
		}
	}
}

// Funzione per recuperare promemoria scadenza
async function fetchExpiryReminders() {
	try {
		const response = await appFetchWithToken(AppURLs.api.getExpiryReminders());

		if (response.status && response.data) {
			return response.data;
		} else {
			console.warn("Nessun dato ricevuto dall'API, uso array vuoto");
			return [];
		}
	} catch (error) {
		console.error('Errore API fetchExpiryReminders:', error);
		// In caso di errore, prova a caricare dati di fallback
		try {
			const fallbackResponse = await fetch('./data/promemoria-scadenza.json');
			const fallbackData = await fallbackResponse.json();
			return fallbackData;
		} catch (fallbackError) {
			console.error('Errore anche nel caricamento fallback:', fallbackError);
			return [];
		}
	}
}

// Funzione per renderizzare la lista promemoria terapia
function renderRemindersList(reminders) {
	const container = document.getElementById('remindersList');

	if (reminders.length === 0) {
		container.innerHTML = `
			<div class="empty-state">
				<div class="empty-icon">
					<i class="fas fa-bell-slash"></i>
				</div>
				<div class="empty-title">Nessun promemoria terapia</div>
				<div class="empty-description">
					Aggiungi il tuo primo promemoria per iniziare a gestire la tua terapia
				</div>
			</div>
		`;
		return;
	}

	container.innerHTML = reminders.map((reminder) => createReminderHTML(reminder)).join('');

	// Aggiungi event listeners per le azioni
	addReminderEventListeners();
}

// Funzione per renderizzare la lista promemoria scadenza
function renderExpiryList(expiryReminders) {
	const container = document.getElementById('expiryList');

	if (expiryReminders.length === 0) {
		container.innerHTML = `
			<div class="empty-state">
				<div class="empty-icon">
					<i class="fas fa-calendar-times"></i>
				</div>
				<div class="empty-title">Nessun promemoria scadenza</div>
				<div class="empty-description">
					Aggiungi il tuo primo promemoria scadenza per monitorare i tuoi prodotti
				</div>
			</div>
		`;
		return;
	}

	container.innerHTML = expiryReminders.map((expiry) => createExpiryHTML(expiry)).join('');

	// Aggiungi event listeners per le azioni
	addExpiryEventListeners();
}

// Funzione per creare HTML del promemoria terapia
function createReminderHTML(reminder) {
	const statusClass = '';

	// Gestisci diversi possibili nomi del campo foto
	const photoUrl = reminder.viewUrl || reminder.productPhoto || reminder.photo_url || reminder.photoUrl || null;

	return `
		<div class="reminder-item ${statusClass}" data-id="${reminder.id}">
			${
				photoUrl
					? `
				<div class="reminder-photo">
					<img src="${photoUrl}" alt="Foto farmaco" class="medication-image" 
						onerror="this.style.display='none'; this.nextElementSibling.style.display='block';"
						onload="this.style.display='block'; this.nextElementSibling.style.display='none';">
					<div class="image-placeholder" style="display: none; text-align: center; padding: 20px; background: #f8f9fa; border-radius: 8px; color: #6c757d;">
						<i class="fas fa-image" style="font-size: 24px; margin-bottom: 10px; display: block;"></i>
						<span style="font-size: 12px;">Immagine non disponibile</span>
					</div>
				</div>
			`
					: ''
			}
			
			<div class="reminder-header">
				<h4 class="reminder-title">${reminder.medicationName}</h4>
				<div class="reminder-actions">
					<button class="btn btn-danger delete-reminder" title="Elimina">
						<i class="fas fa-trash-alt"></i>
					</button>
				</div>
			</div>
			
			<div class="reminder-details">
				<div class="detail-item">
					<div class="detail-label">Dosaggio</div>
					<div class="detail-value">${reminder.dosage}</div>
				</div>
				<div class="detail-item">
					<div class="detail-label">Frequenza</div>
					<div class="detail-value">${getFrequencyLabel(reminder.frequency)}</div>
				</div>
				<div class="detail-item">
					<div class="detail-label">Data Inizio</div>
					<div class="detail-value">${formatDate(reminder.startDate)}</div>
				</div>
				<div class="detail-item">
					<div class="detail-label">Data Fine</div>
					<div class="detail-value">${formatDate(reminder.endDate)}</div>
				</div>
			</div>
			
			<div class="reminder-times">
				<div class="times-title">Orari Assunzione</div>
				<div class="time-list">
					${reminder.times
						.map(
							(time) => `
						<span class="time-badge">${time}</span>
					`
						)
						.join('')}
				</div>
			</div>
			
			${
				reminder.notes
					? `
				<div class="reminder-notes">
					${reminder.notes}
				</div>
			`
					: ''
			}
		</div>
	`;
}

// Funzione per creare HTML del promemoria scadenza
function createExpiryHTML(expiry) {
	const daysUntilExpiry = calculateDaysUntilExpiry(expiry.expiryDate);
	const statusClass = getExpiryStatusClass(daysUntilExpiry);
	const statusText = getExpiryStatusText(daysUntilExpiry);

	// Gestisci diversi possibili nomi del campo foto
	const photoUrl = expiry.viewUrl || expiry.productPhoto || expiry.photo_url || expiry.photoUrl || null;

	return `
		<div class="expiry-item ${statusClass}" data-id="${expiry.id}">
			<div class="expiry-header">
				<h4 class="expiry-title">${expiry.productName}</h4>
				<div class="expiry-actions">
					<button class="btn btn-danger delete-expiry" title="Elimina">
						<i class="fas fa-trash-alt"></i>
					</button>
				</div>
			</div>
			
			${
				photoUrl
					? `
				<div class="expiry-photo">
					<img src="${photoUrl}" alt="Foto prodotto" class="product-image" 
						onerror="this.style.display='none'; this.nextElementSibling.style.display='block';"
						onload="this.style.display='block'; this.nextElementSibling.style.display='none';">
					<div class="image-placeholder" style="display: none; text-align: center; padding: 20px; background: #f8f9fa; border-radius: 8px; color: #6c757d;">
						<i class="fas fa-image" style="font-size: 24px; margin-bottom: 10px; display: block;"></i>
						<span style="font-size: 12px;">Immagine non disponibile</span>
					</div>
				</div>
			`
					: ''
			}
			
			<div class="expiry-details">
				<div class="detail-item">
					<div class="detail-label">Data Scadenza</div>
					<div class="detail-value">${formatDate(expiry.expiryDate)}</div>
				</div>
				<div class="detail-item">
					<div class="detail-label">Giorni Rimanenti</div>
					<div class="detail-value">${daysUntilExpiry > 0 ? daysUntilExpiry : 'Scaduto'}</div>
				</div>
			</div>
			
			${
				expiry.notes
					? `
				<div class="expiry-notes">
					${expiry.notes}
				</div>
			`
					: ''
			}
			
			<div class="expiry-status ${statusClass}">
				${statusText}
			</div>
		</div>
	`;
}

// Funzione per aggiungere event listeners ai promemoria terapia
function addReminderEventListeners() {
	// Elimina promemoria
	document.querySelectorAll('.delete-reminder').forEach((btn) => {
		btn.addEventListener('click', function () {
			const reminderId = this.closest('.reminder-item').dataset.id;
			deleteReminder(reminderId);
		});
	});

	// Gestione errori immagini
	document.querySelectorAll('.medication-image').forEach((img) => {
		img.addEventListener('error', function () {
			// Prova ad aggiornare il token e ricaricare l'immagine
			const currentSrc = this.src;
			const updatedSrc = updateImageToken(currentSrc);

			if (updatedSrc !== currentSrc) {
				this.src = updatedSrc;
			} else {
				// Se non è possibile aggiornare il token, mostra il placeholder
				this.style.display = 'none';
				const placeholder = this.nextElementSibling;
				if (placeholder && placeholder.classList.contains('image-placeholder')) {
					placeholder.style.display = 'block';
				}
			}
		});
	});
}

// Funzione per aggiungere event listeners ai promemoria scadenza
function addExpiryEventListeners() {
	// Elimina promemoria scadenza
	document.querySelectorAll('.delete-expiry').forEach((btn) => {
		btn.addEventListener('click', function () {
			const expiryId = this.closest('.expiry-item').dataset.id;
			deleteExpiry(expiryId);
		});
	});

	// Gestione errori immagini
	document.querySelectorAll('.product-image').forEach((img) => {
		img.addEventListener('error', function () {
			// Prova ad aggiornare il token e ricaricare l'immagine
			const currentSrc = this.src;
			const updatedSrc = updateImageToken(currentSrc);

			if (updatedSrc !== currentSrc) {
				this.src = updatedSrc;
			} else {
				// Se non è possibile aggiornare il token, mostra il placeholder
				this.style.display = 'none';
				const placeholder = this.nextElementSibling;
				if (placeholder && placeholder.classList.contains('image-placeholder')) {
					placeholder.style.display = 'block';
				}
			}
		});
	});
}

// Funzione per eliminare promemoria terapia
async function deleteReminder(id) {
	if (confirm('Sei sicuro di voler eliminare questo promemoria?')) {
		try {
			const response = await appFetchWithToken(AppURLs.api.deleteTherapyReminder(), {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
				},
				body: JSON.stringify({id: id}),
			});

			if (response.status) {
				loadReminders();
				showToast('Promemoria eliminato!', 'success');
			} else {
				throw new Error(response.message || "Errore durante l'eliminazione");
			}
		} catch (error) {
			handleError(error, 'Errore durante il salvataggio. Riprova.');
		}
	}
}

// Funzione per eliminare promemoria scadenza
async function deleteExpiry(id) {
	if (confirm('Sei sicuro di voler eliminare questo promemoria scadenza?')) {
		try {
			const response = await appFetchWithToken(AppURLs.api.deleteExpiryReminder(), {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
				},
				body: JSON.stringify({id: id}),
			});

			if (response.status) {
				loadExpiryReminders();
				showToast('Promemoria scadenza eliminato!', 'success');
			} else {
				throw new Error(response.message || "Errore durante l'eliminazione");
			}
		} catch (error) {
			handleError(error, 'Errore durante il salvataggio. Riprova.');
		}
	}
}

// Funzione per aggiornare il token nelle URL delle immagini
function updateImageToken(imageUrl) {
	if (!imageUrl || !imageUrl.includes('view-file.php')) {
		return imageUrl;
	}

	try {
		const url = new URL(imageUrl);
		const currentToken = url.searchParams.get('token');
		const newToken = localStorage.getItem('jta-app-jwt');

		if (newToken && newToken !== currentToken) {
			url.searchParams.set('token', newToken);
			return url.toString();
		}

		return imageUrl;
	} catch (e) {
		console.warn("Errore nell'aggiornamento del token immagine:", e);
		return imageUrl;
	}
}

// Funzione per normalizzare i dati dei promemoria
function normalizeReminderData(reminder) {
	// Normalizza il campo times
	if (typeof reminder.times === 'string') {
		try {
			reminder.times = JSON.parse(reminder.times);
		} catch (e) {
			console.warn('Errore nel parsing di times:', e);
			reminder.times = [];
		}
	}

	// Assicurati che times sia sempre un array
	if (!Array.isArray(reminder.times)) {
		reminder.times = [];
	}

	// Aggiorna il token nelle URL delle immagini
	if (reminder.viewUrl || reminder.productPhoto || reminder.photo_url || reminder.photoUrl) {
		const photoUrl = reminder.viewUrl || reminder.productPhoto || reminder.photo_url || reminder.photoUrl;
		const updatedUrl = updateImageToken(photoUrl);
		reminder.viewUrl = updatedUrl;
		reminder.productPhoto = updatedUrl;
		reminder.photo_url = updatedUrl;
		reminder.photoUrl = updatedUrl;
	}

	return reminder;
}

// Funzione per normalizzare i dati dei promemoria scadenza
function normalizeExpiryData(expiry) {
	// Normalizza il campo alerts (formato JSON dal backend)
	if (typeof expiry.alerts === 'string') {
		try {
			expiry.alerts = JSON.parse(expiry.alerts);
		} catch (e) {
			console.warn('Errore nel parsing di alerts:', e);
			expiry.alerts = {};
		}
	}

	// Assicurati che alerts sia sempre un oggetto
	if (typeof expiry.alerts !== 'object' || expiry.alerts === null) {
		expiry.alerts = {};
	}

	// Mantieni compatibilità con il vecchio formato reminderAlerts se presente
	if (expiry.reminderAlerts && typeof expiry.reminderAlerts === 'string') {
		// Converti il valore singolo in formato alerts per compatibilità
		const alertValue = expiry.reminderAlerts;
		expiry.alerts = {
			alert30: alertValue === '30',
			alert15: alertValue === '15',
			alert7: alertValue === '7',
			alert1: alertValue === '1',
		};
	}

	// Aggiorna il token nelle URL delle immagini
	if (expiry.viewUrl || expiry.productPhoto || expiry.photo_url || expiry.photoUrl) {
		const photoUrl = expiry.viewUrl || expiry.productPhoto || expiry.photo_url || expiry.photoUrl;
		const updatedUrl = updateImageToken(photoUrl);
		expiry.viewUrl = updatedUrl;
		expiry.productPhoto = updatedUrl;
		expiry.photo_url = updatedUrl;
		expiry.photoUrl = updatedUrl;
	}

	return expiry;
}

// Funzione per convertire file in base64
function convertFileToBase64(file) {
	return new Promise((resolve, reject) => {
		const reader = new FileReader();
		reader.onload = () => resolve(reader.result);
		reader.onerror = reject;
		reader.readAsDataURL(file);
	});
}

// Funzione per convertire base64 in file
function base64ToFile(base64String, filename) {
	return new Promise((resolve, reject) => {
		try {
			// Estrai il tipo MIME e i dati base64
			const matches = base64String.match(/^data:([A-Za-z-+\/]+);base64,(.+)$/);
			if (!matches || matches.length !== 3) {
				throw new Error('Formato base64 non valido');
			}

			const mimeType = matches[1];
			const base64Data = matches[2];
			const byteCharacters = atob(base64Data);
			const byteNumbers = new Array(byteCharacters.length);

			for (let i = 0; i < byteCharacters.length; i++) {
				byteNumbers[i] = byteCharacters.charCodeAt(i);
			}

			const byteArray = new Uint8Array(byteNumbers);
			const file = new File([byteArray], filename, {type: mimeType});
			resolve(file);
		} catch (error) {
			reject(error);
		}
	});
}

// Funzioni helper
function getFrequencyLabel(frequency) {
	const labels = {
		daily: 'Una volta al giorno',
		twice_daily: 'Due volte al giorno',
		three_times: 'Tre volte al giorno',
	};
	return labels[frequency] || frequency;
}

function formatDate(dateString) {
	const date = new Date(dateString);
	return date.toLocaleDateString('it-IT');
}

function calculateDaysUntilExpiry(expiryDate) {
	const today = new Date();
	const expiry = new Date(expiryDate);
	const diffTime = expiry - today;
	const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
	return diffDays;
}

function getExpiryStatusClass(daysUntilExpiry) {
	if (daysUntilExpiry < 0) return 'expired';
	if (daysUntilExpiry <= 7) return 'warning';
	return 'safe';
}

function getExpiryStatusText(daysUntilExpiry) {
	if (daysUntilExpiry < 0) return 'PRODOTTO SCADUTO';
	if (daysUntilExpiry === 0) return 'SCADE OGGI';
	if (daysUntilExpiry <= 7) return `SCADE TRA ${daysUntilExpiry} GIORNI`;
	return 'PRODOTTO VALIDO';
}

// Event listeners per messaggi
document.addEventListener('reminderAdded', function (event) {
	showToast('Promemoria aggiunto con successo!', 'success');
});

document.addEventListener('reminderError', function (event) {
	showToast(event.detail.error, 'error');
});
// event per il calendar
document.addEventListener('click', async (e) => {
	if (e.target.classList.contains('calendar-icon') || e.target.classList.contains('calendar-iconExp')) {
		const input = e.target.closest('.form-group')?.querySelector("input[type='date']");
		input?.showPicker?.();
		input?.focus();
		return;
	}
});

const query = window.location.search;
let tabTarget = null;

if (query.includes('scadenza')) {
	tabTarget = 'scadenza';
} else if (query.includes('terapia')) {
	tabTarget = 'terapia';
}

if (tabTarget) {
	const triggerEl = document.querySelector(`#${tabTarget}-tab`);

	if (triggerEl) {
		bootstrap.Tab.getOrCreateInstance(triggerEl).show();

		const tabContent = document.querySelector(triggerEl.getAttribute('data-bs-target'));
		if (tabContent) {
			tabContent.classList.add('active', 'show');
		}
	}
}
