
async function pharma_disconnect() {
	var status = document.querySelector('.wa-status');
	var image  = document.querySelector('.wa-image');
	var action = document.querySelector('.wa-action');

	try {
		const response = await fetch('./api/whatsapp/disconnect.php', {
			method: 'POST',
			credentials: 'include',
			headers: {
				'Accept': 'application/json',
				'Content-Type': 'application/json'
			},
		});
		
		if (!response.ok) {
			throw new Error(`HTTP error! status: ${response.status}`);
		}
		
		const data = await response.json();

		if (data.success) {
			status.innerHTML = '';
			action.innerHTML = '';
			image.innerHTML = '';

			window.location.reload();
		} else {
			status.innerHTML = 'Errore durante la disconnessione.';
			action.innerHTML = '<button class="btn btn-outline-primary" onclick="checkWAstatus();">Riprova</button>';
			image.innerHTML = '';
		}

	} catch (error) {
		console.error('Error disconnecting:', error);
		// document.getElementById('status').innerHTML = 'Errore durante la disconnessione: ' + error.message;
	}
}

async function getWAstatus() {
	try {
		const response = await fetch('./api/whatsapp/check-status.php', {
			credentials: 'include',
			headers: {
				'Accept': 'application/json',
				'Content-Type': 'application/json'
			},
		});
		
		if (!response.ok) {
			throw new Error(`HTTP error! status: ${response.status}`);
		}

		const data = await response.json();
		return data;
		return data.success ? true : false;

	} catch (error) {
		console.error('Error disconnecting:', error);
	}
}

async function checkWAstatus() {
	var status = document.querySelector('.wa-status');
	var image  = document.querySelector('.wa-image');
	var action = document.querySelector('.wa-action');

	try {
		const data = await getWAstatus();
		
		if (data.success) {
			status.innerHTML = '<div class="status-connected"><div class="icon">✓</div><div class="h4">WhatsApp è già connesso!</div></div><div class="status-message">WhatsApp è collegato e pronto per l\'uso.</div>';
			action.innerHTML = '<button class="btn btn-outline-danger" onclick="pharma_disconnect();">Disconnetti WhatsApp</button>';
			image.innerHTML = '';
		} else {
			status.innerHTML = 'WhatsApp disconnesso.';
			action.innerHTML = '<button class="btn btn-outline-primary" onclick="checkWAstatus();">Aggiorna QR Code</button>';
			image.innerHTML = '';

			pharma_get_qr();
		}

	} catch (error) {
		console.error('Error disconnecting:', error);
	}
}

async function pharma_get_qr() {
	var status = document.querySelector('.wa-status');
	var image  = document.querySelector('.wa-image');
	var action = document.querySelector('.wa-action');

	try {
		const response = await fetch('./api/whatsapp/get-qr.php', {
			method: 'POST',
			credentials: 'include',
			headers: {
				'Accept': 'application/json',
				'Content-Type': 'application/json'
			},
		});
		
		if (!response.ok) {
			throw new Error(`HTTP error! status: ${response.status}`);
		}

		const data = await response.json();

		if (data.success) {
			image.innerHTML  = '<img class="img-fluid mx-auto" src="'+data.qrCode+'" width="276" height="276" alt="Inquadra il QR" />';
			status.innerHTML = '<div class="mt-2 h4">'+data.message+'</div>';
			status.innerHTML += '<div class="small">Connetti il tuo account WhatsApp per cominciare a gestire la tua Farmacia.</div>';
			status.innerHTML += '<div class="small"><a href="https://faq.whatsapp.com/378279804439436/" target="_blank">Scopri come connettere il tuo account</a></div>';
			action.innerHTML = '<button class="btn btn-outline-primary" onclick="checkWAstatus();">Aggiorna QR Code</button>';

			setInterval( async function(){
				let dataConnect = await getWAstatus();
				console.log(dataConnect);
				if( dataConnect.success == true ){
					window.location.reload();
				}
			}, 20000);

		} else {
			image.innerHTML  = '';
			status.innerHTML = data.message;
			action.innerHTML = '<button class="btn btn-outline-primary" onclick="checkWAstatus();">Riprova</button>';
		}

	} catch (error) {
		console.error('Error disconnecting:', error);
	}
}



document.addEventListener('DOMContentLoaded', () => {

	checkWAstatus();

});
