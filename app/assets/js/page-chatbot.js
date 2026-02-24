document.addEventListener('appLoaded', () => {
	currentSessionId = null;
	initInputListeners();
	initImageHandlers();
	fetchInitialBotMessage();
});

let isBotResponding = false;
let selectedImageFile = null;
let currentSessionId = null;

function setInputState(disabled) {
	const input = document.getElementById('userInput');
	const sendBtn = document.getElementById('sendBtn');
	const imageBtn = document.getElementById('imageBtn');

	if (input) input.disabled = disabled;
	if (sendBtn) sendBtn.disabled = disabled;
	if (imageBtn) imageBtn.disabled = disabled;
}

function initInputListeners() {
	const input = document.getElementById('userInput');
	const sendBtn = document.getElementById('sendBtn');

	if (!input || !sendBtn) {
		console.warn('input o sendBtn non trovati nel DOM');
		return;
	}

	input.addEventListener('keydown', (e) => {
		if (e.key === 'Enter') sendMessage();
	});
	sendBtn.addEventListener('click', sendMessage);
}

function initImageHandlers() {
	const imageBtn = document.getElementById('imageBtn');
	const imageInput = document.getElementById('imageInput');
	const removeImageBtn = document.getElementById('removeImageBtn');

	if (!imageBtn || !imageInput || !removeImageBtn) {
		console.warn('Elementi immagine non trovati nel DOM');
		return;
	}

	imageBtn.addEventListener('click', () => {
		imageInput.click();
	});

	imageInput.addEventListener('change', handleImageSelection);
	removeImageBtn.addEventListener('click', removeSelectedImage);
}

function handleImageSelection(event) {
	const file = event.target.files[0];
	
	if (!file) return;

	try {
		validateImage(file);
	} catch (error) {
		if (typeof showToast === 'function') {
			showToast(error.message, 'error');
		} else {
			alert(error.message);
		}
		clearImageInput();
		return;
	}

	selectedImageFile = file;
	showImagePreview(file);
}

function validateImage(file) {
	const maxSize = 20 * 1024 * 1024; // 20MB
	const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

	if (file.size > maxSize) {
		throw new Error("L'immagine deve essere inferiore a 20MB");
	}

	if (!allowedTypes.includes(file.type)) {
		throw new Error('Formato immagine non supportato. Usa JPEG, PNG, GIF o WEBP');
	}

	return true;
}

function showImagePreview(file) {
	const preview = document.getElementById('imagePreview');
	const previewImg = document.getElementById('previewImg');

	if (preview && previewImg) {
		const reader = new FileReader();
		reader.onload = function (e) {
			previewImg.src = e.target.result;
			preview.style.display = 'flex';
		};
		reader.readAsDataURL(file);
	}
}

function removeSelectedImage() {
	selectedImageFile = null;
	hideImagePreview();
	clearImageInput();
}

function hideImagePreview() {
	const preview = document.getElementById('imagePreview');
	if (preview) {
		preview.style.display = 'none';
	}
}

function resetChatSession() {
	currentSessionId = null;
}

function clearImageInput() {
	const imageInput = document.getElementById('imageInput');
	if (imageInput) {
		imageInput.value = '';
	}
}

function fileToBase64(file) {
	return new Promise((resolve, reject) => {
		const reader = new FileReader();
		reader.readAsDataURL(file);
		reader.onload = () => resolve(reader.result);
		reader.onerror = error => reject(error);
	});
}

function appendMessage(htmlOrNode, type, messageId) {
	type = type || 'bot';
	messageId = messageId || '';

	const messages = document.getElementById('messages');
	const msg = document.createElement('div');
	msg.className = 'message ' + type;

	if (messageId !== '') {
		msg.id = messageId;
	}

	if (typeof htmlOrNode === 'string') {
		msg.innerHTML = htmlOrNode;
	} else {
		msg.appendChild(htmlOrNode);
	}

	messages.appendChild(msg);

	if (messageId !== '') {
		setTimeout(function () {
			const target = document.getElementById(messageId);
			if (target) {
				target.scrollIntoView({behavior: 'smooth', block: 'start'});
			}
		}, 50);
	} else {
		setTimeout(function () {
			messages.scrollTop = messages.scrollHeight;
		}, 50);
	}
}

function renderQuickActionsFromJson(actions = []) {
	const container = document.createElement('div');
	container.classList.add('quick-actions-group');
	container.style.marginTop = '10px';

	actions.forEach((act) => {
		const el = document.createElement('quick-action');
		el.setAttribute('label', act.label);
		el.setAttribute('action', act.action);
		el.setAttribute('type', act.type);

		if (act.target) el.setAttribute('target', act.target);
		if (act.value !== undefined) el.setAttribute('value', JSON.stringify(act.value));

		const btn = document.createElement('button');
		btn.className = 'quick-action';
		btn.textContent = act.label;

		btn.onclick = () => {
			handleQuickAction(act.type, act.action, act.target || '', act.value !== undefined ? act.value : null, act.label);
		};

		el.appendChild(btn);
		container.appendChild(el);
	});

	return container;
}

function renderBotReply(message, hint, actions) {
	message = typeof message === 'string' ? message : '';
	hint = typeof hint === 'string' ? hint : '';
	actions = Array.isArray(actions) ? actions : [];

	const wrapper = document.createElement('div');

	message = chatbotParseMessage(message);

	if (message.trim()) {
		const div = document.createElement('div');
		div.innerHTML = message;
		wrapper.appendChild(div);
	}

	if (hint.trim()) {
		const em = document.createElement('p');
		em.innerHTML = `<em>${hint}</em>`;
		wrapper.appendChild(em);
	}

	if (actions.length) {
		const actionsDOM = renderQuickActionsFromJson(actions);
		wrapper.appendChild(actionsDOM);
	}

	return wrapper;
}

function chatbotParseMessage(message){
	message = message.replaceAll(
		"%%page_reservation%%",
		'<div class="disclaimer disclaimer--prescription my-2">Se vuoi prenotare i prodotti di una ricetta medica usa la sezione dedicata: <button class="quick-action btn btn-primary" type="button" onclick="goTo(AppURLs.page.reservation());">pagina prenotazioni</button></div>'
	);

	return message;
}

function fetchInitialBotMessage() {
	appFetchWithToken(AppURLs.api.initChatBot(), {
		method: 'GET',
		headers: {'Content-Type': 'application/json'},
	})
		.then((data) => {
			if (data?.data?.sessionId) {
				currentSessionId = data.data.sessionId;
			}
			document.dispatchEvent(new CustomEvent('appAnswerBot', {detail: data?.data}));
		})
		.catch((err) => {
			handleError(err, 'Errore inizializzazione bot');
		});
}

function fetchBotSend(request) {
	if (!request || typeof request !== 'object') return;

	if (isBotResponding) return;

	isBotResponding = true;
	setInputState(true);

	const hasMessage = typeof request.message === 'string' && request.message.trim() !== '';
	const hasQuickAction = typeof request.quickAction === 'object' && request.quickAction !== null;
	const hasImage = typeof request.image === 'string' && request.image.trim() !== '';

	if (!hasMessage && !hasQuickAction && !hasImage) {
		handleError('Payload non valido: nessun message, quickAction o image.');
		isBotResponding = false;
		setInputState(false);
		return;
	}

	if (currentSessionId) {
		request.sessionId = currentSessionId;
	}

	const typingId = `typing-${Date.now()}`;
	appendMessage(
		`<div class="typing-indicator">
		<span></span>
		<span></span>
		<span></span>
	</div>`,
		'bot',
		typingId
	);
	
	appFetchWithToken(AppURLs.api.sendToBot(), {
		method: 'POST',
		headers: {'Content-Type': 'application/json'},
		body: JSON.stringify(request),
	})
		.then((data) => {
			if (data.status) {
				if (data?.data?.sessionId) {
					currentSessionId = data.data.sessionId;
				}
				document.dispatchEvent(new CustomEvent('appAnswerBot', {detail: data?.data}));
				return;
			}
			handleError(data.error || 'Errore nel rispondere dal bot');
			document.dispatchEvent(new CustomEvent('appAnswerBotError', {detail: {error: data.error}}));
		})
		.catch((err) => {
			handleError(err, 'Errore fetch bot');
			document.dispatchEvent(new CustomEvent('appAnswerBotError', {detail: {error: err}}));
		})
		.finally(() => {
			isBotResponding = false;
			setInputState(false);

			const typingEl = document.getElementById(typingId);
			if (typingEl) typingEl.remove();
		});
}

function sendMessage() {
	const input = document.getElementById('userInput');
	const text = input.value.trim();

	if (!text.length && !selectedImageFile) {
		if (typeof showToast === 'function') {
			showToast("Inserisci un messaggio o seleziona un'immagine", 'warning');
		} else {
			alert("Inserisci un messaggio o seleziona un'immagine");
		}
		return;
	}

	let messageContent = '';
	if (text.length) {
		messageContent += `<p>${text}</p>`;
	}
	
	if (selectedImageFile) {
		const imageUrl = URL.createObjectURL(selectedImageFile);
		messageContent += `<div class="message-image"><img src="${imageUrl}" alt="Immagine allegata" /></div>`;
	}

	appendMessage(messageContent, 'user');
	input.value = '';

	const payload = {
		message: text || 'Immagine allegata',
	};

	if (selectedImageFile) {
		const imageFileToSend = selectedImageFile;
		
		fileToBase64(imageFileToSend).then(base64Image => {
			payload.image = base64Image;
			payload.imageFormat = imageFileToSend.name.split('.').pop().toLowerCase();
			fetchBotSend(payload);
		}).catch(error => {
			if (typeof showToast === 'function') {
				showToast('Errore nell\'elaborazione dell\'immagine', 'error');
			} else {
				alert('Errore nell\'elaborazione dell\'immagine');
			}
		}).finally(() => {
			removeSelectedImage();
		});
	} else {
		fetchBotSend(payload);
	}
}

function sendQuickAction(actionObj) {
	if (!actionObj || typeof actionObj !== 'object') return;
	fetchBotSend({quickAction: actionObj});
}

function handleQuickAction(type, action, target, value, label) {
	type = typeof type === 'string' ? type : '';
	action = typeof action === 'string' ? action : '';
	target = typeof target === 'string' ? target : '';
	value = value !== undefined ? value : null;
	label = typeof label === 'string' ? label : '';

	if (label) {
		appendMessage(`<p>${label}</p>`, 'user');
	}

	switch (type) {
		case 'navigation':
			if (action === 'linkPage' && target) {
				window.open(target, '_blank');
			} else if (action === 'appPage' && target) {
				goTo(AppURLs.page[target]());
			}
			break;

		case 'request':
			if (label) {
				fetchBotSend({message: label});
			} else {
				sendQuickAction({type, action});
			}
			break;

		case 'action':
			sendQuickAction({type, action, value});
			break;

		default:
			if (label) {
				fetchBotSend({message: label});
			}
	}
}

document.addEventListener('appAnswerBot', function (event) {
	const data = event.detail;
	const quick = data?.quickAction || {};
	if (quick?.action === 'appPage' && quick?.target) {
		goTo(`./${quick.target}.html`);
		return;
	}

	const dom = renderBotReply(data.message, quick.hint, quick.actions || []);
	const msgId = `bot-msg-${Date.now()}`;
	appendMessage(dom, 'bot', msgId);

	document.getElementById('userInput').value = '';
});

document.addEventListener('appAnswerBot', function (event) {
	if (!window.__contextAlreadyInjected) {
		window.__contextAlreadyInjected = true;

		const waitForIdle = setInterval(() => {
			if (!isBotResponding) {
				clearInterval(waitForIdle);
				document.getElementById('userInput').value = '';
				SmartAIContext.injectContextIfPresent();
			}
		}, 150);
	}
});

document.addEventListener('appAnswerBotError', function (event) {
	const {retryData} = event.detail;

	const retryBtn = document.createElement('button');
	retryBtn.textContent = 'Riprova';
	retryBtn.className = 'btn btn-sm btn-primary mt-2';
	retryBtn.onclick = () => {
		retryBtn.disabled = true;
		fetchBotSend(retryData);
	};

	const errorMsg = document.createElement('div');
	errorMsg.innerHTML = `<p>Errore nell'elaborazione. Prova a riprovare.</p>`;
	errorMsg.appendChild(retryBtn);

	appendMessage(errorMsg, 'bot');
});
