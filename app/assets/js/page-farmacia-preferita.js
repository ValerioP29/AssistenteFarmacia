document.addEventListener('appLoaded', () => {
	if (currPageIs(AppURLs.page.preferPharma())) {
		fetchPharmacies();
	}
});

function fetchPharmacies() {
	appFetchWithToken(AppURLs.api.getPharmacies(), {
		method: 'GET',
	})
		.then((data) => {
			if (data.status) {
				document.dispatchEvent(new CustomEvent('pharmaciesLoaded', {detail: data.data}));
			} else {
				document.dispatchEvent(new CustomEvent('pharmaciesError', {detail: {error: data.error}}));
			}
		})
		.catch((err) => {
			document.dispatchEvent(new CustomEvent('pharmaciesError', {detail: {error: err}}));
		});
}

document.addEventListener('pharmaciesLoaded', function (event) {
	const {preferred, followed} = event.detail;
	const preferredContainer = document.getElementById('preferredPharmacy');

	preferredContainer.innerHTML = `
  <div class="pharmacy preferred-pharmacy">
    <img class="pharma-logo" src="${preferred.image_cover}" alt="${preferred.business_name}" class="pharmacy-cover" />
    <div class="pharmacy-info">
      <h4 class="name">${preferred.business_name}</h4>
      <p class="address">${preferred.address || ''}</p>

      <p class="contact mb-0">
        <i class="fa-brands fa-whatsapp mx-2"></i> 
        WhatsApp: 
        <a href="https://wa.me/${preferred.wa_number}" class="text-white" target="_blank" rel="noopener">
          ${preferred.wa_number}
        </a>
      </p>
    </div>
  </div>
`;

	// Altre farmacie seguite
	const list = document.getElementById('pharmacyList');
	list.innerHTML = '';

	if (!followed || followed.length === 0) {
		list.innerHTML = `
				<div class="pharmacy-list-empty">Non ci sono altre farmacie disponibili. Aggiungine una o seleziona quella preferita.</div>
		`;
		return;
	}

	followed.forEach((pharmacy) => {
		const wrapper = document.createElement('div');
		wrapper.className = 'pharmacy';
		wrapper.dataset.id = pharmacy.id;

		wrapper.innerHTML = `
			<img class="pharma-logo" src="${pharmacy.image_avatar}" alt="${pharmacy.business_name}" class="pharmacy-avatar" />
			<div class="pharmacy-info">
				<div class="name">${pharmacy.business_name}</div>
			<!--	<p class="description">${pharmacy.description}</p> -->
			<!--	<p class="contact"><i class="fa-solid fa-phone mx-2"></i> Telefono: ${pharmacy.phone_number} </p> -->
           <p class="contact">
            <i class="fa-brands fa-whatsapp mx-2"></i> 
             WhatsApp: 
             <a href="https://wa.me/${pharmacy.wa_number}"  target="_blank" rel="noopener">
             ${pharmacy.wa_number}
             </a>
          </p>
				<div class="actions">
					<button class="btn btn-sm btn-primary set-default">Imposta come preferita</button>
					<button class="btn btn-sm btn-primary delete">Elimina</button>
				</div>
			</div>
		`;

		wrapper.querySelector('.set-default').addEventListener('click', () => {
			setPreferredPharmacy(pharmacy.id);
		});

		wrapper.querySelector('.delete').addEventListener('click', () => {
			deletePharmacy(pharmacy.id);
		});

		list.appendChild(wrapper);
	});
});

document.addEventListener('pharmaciesError', function (event) {
	const {error} = event.detail;

	if (!document.getElementById('pharmacyList')) return;

	document.getElementById('pharmacyList').innerHTML = `
		<div class="alert alert-danger" role="alert">
			Errore nel caricamento delle farmacie: ${error?.message || 'riprovare più tardi.'}
		</div>
	`;
});

function setPreferredPharmacy(pharmacyId) {
	appFetchWithToken(AppURLs.api.setPreferredPharmacy(), {
		method: 'POST',
		headers: {'Content-Type': 'application/json'},
		body: JSON.stringify({id: pharmacyId}),
	})
		.then((data) => {
			if (data.status) {
				showToast('Nuova farmacia preferita aggiornata', 'success');
				fetchPharmacies();
			} else {
				handleError('Errore: non è stato possibile impostare la farmacia preferita.');
			}
		})
		.catch(() => {
			handleError('Errore di rete. Riprova.');
		});
}

function deletePharmacy(pharmacyId) {
	if (!confirm('Sei sicuro di voler eliminare questa farmacia?')) return;

	appFetchWithToken(AppURLs.api.deletePharmacy(), {
		method: 'DELETE',
		headers: {'Content-Type': 'application/json'},
		body: JSON.stringify({id: pharmacyId}),
	})
		.then((data) => {
			if (data.status) {
				showToast(data.message || 'Farmacia rimossa con successo.', 'success');
				fetchPharmacies();
			} else {
				showToast('Errore: impossibile eliminare la farmacia.', 'error');
			}
		})
		.catch(() => {
			handleError('Errore di rete. Riprova.');
		});
}
