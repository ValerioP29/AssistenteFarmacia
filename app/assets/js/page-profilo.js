document.addEventListener('appLoaded', function () {
	let profileData = null;
	// toggleBetaCards();

	// Carica profilo
	appFetchWithToken(AppURLs.api.getProfile(), {
		method: 'GET',
		headers: {'Content-Type': 'application/json'},
	})
		.then((res) => {
			if (res.status) {
				profileData = res.data;

				const numero_str = profileData.phone_number?.toString() || '';
				const numero_nuovo = numero_str.replace(/^39/, '');

				document.querySelector('#username').textContent = profileData.slug_name || '-';
				document.querySelector('#phone').textContent = numero_nuovo || '-';
				document.querySelector('#name').textContent = profileData.name || '';
				document.querySelector('#surname').textContent = profileData.surname || '';
				document.querySelector('#email').textContent = profileData.email || '';


				document.querySelector('#genereInput').value = profileData.init_profiling?.genere || '';
				document.querySelector('#fasciaEtaInput').value = profileData.init_profiling?.fascia_eta || '';
				document.querySelector('#lifestyleInput').value = profileData.init_profiling?.lifestyle || '';

				document.getElementById('consent_marketing').checked = !!profileData.consents?.accept_marketing;

				const selectedArgs = profileData.init_profiling?.argomenti || [];
				document.querySelectorAll('.argomento-checkbox').forEach((checkbox) => {
					checkbox.checked = selectedArgs.includes(checkbox.value);
				});

				setupBasicForm(profileData);
			} else {
				showToast('Errore nel recupero del profilo.', 'error');
			}
		})
		.catch((err) => {
			handleError(err, 'Errore durante il recupero dei dati profilo');
		});

	// Limita a massimo 3 checkbox selezionati
	document.querySelectorAll('.argomento-checkbox').forEach((checkbox) => {
		checkbox.addEventListener('change', function () {
			const checkedBoxes = document.querySelectorAll('.argomento-checkbox:checked');
			if (checkedBoxes.length > 3) {
				this.checked = false;
				showToast('Puoi selezionare massimo 3 argomenti.', 'warning');
			}
		});
	});

	const profilingForm = document.querySelector('#profilingForm');
	if (profilingForm) {
		profilingForm.addEventListener('submit', function (e) {
			e.preventDefault();

			const genere = document.querySelector('#genereInput').value;
			const fasciaEta = document.querySelector('#fasciaEtaInput').value;
			const lifestyle = document.querySelector('#lifestyleInput').value;
			const argomenti = Array.from(document.querySelectorAll('.argomento-checkbox:checked')).map((cb) => cb.value);

			const previous = profileData?.init_profiling || {};

			const isSame =
			genere === (previous.genere || '') &&
			fasciaEta === (previous.fascia_eta || '') &&
			lifestyle === (previous.lifestyle || '') &&
			JSON.stringify(argomenti.sort()) === JSON.stringify((previous.argomenti || []).sort());

			if (isSame) {
				showToast('Non hai apportato modifiche.', 'info');
				return;
			}

			if (argomenti.length === 0) {
				showToast('Seleziona almeno un argomento di interesse.', 'warning');
				return;
			}

			const profilingData = {
				init_profiling: {
					genere: genere || previous.genere || '',
					fascia_eta: fasciaEta || previous.fascia_eta || '',
					lifestyle: lifestyle || previous.lifestyle || '',
					argomenti,
				},
			};

			appFetchWithToken(AppURLs.api.putProfile(), {
				method: 'PUT',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify(profilingData),
			})
				.then((res) => {
					if (res.status) {
						showToast('Profilazione aggiornata con successo!', 'success');
						profileData.init_profiling = profilingData.init_profiling;
						document.querySelector('#genereInput').value = profilingData.init_profiling.genere;
						document.querySelector('#fasciaEtaInput').value = profilingData.init_profiling.fascia_eta;
						document.querySelector('#lifestyleInput').value = profilingData.init_profiling.lifestyle;

						document.querySelectorAll('.argomento-checkbox').forEach((cb) => {
							cb.checked = profilingData.init_profiling.argomenti.includes(cb.value);
						});
					} else {
						showToast('Errore nel salvataggio della profilazione.', 'error');
					}
				})
				.catch((err) => {
					handleError(err, 'Errore aggiornamento profilazione');
				});
		});
	}

	// Password
	const form = document.querySelector('#passwordForm');
	form.addEventListener('submit', (e) => {
		e.preventDefault();

		const newPassword = document.querySelector('#newPassword').value;
		const confirmPassword = document.querySelector('#confirmPassword').value;
		const MIN_LEN = 6;
		const allowedSet = /^[A-Za-z0-9!"#$%&'()*+,\-./:;<=>?@[\\\]\^_`{|}~]+$/; // no spazi, no emoji
		const composition = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!"#$%&'()*+,\-./:;<=>?@[\\\]\^_`{|}~]).+$/;

		if (!newPassword || !confirmPassword) {
			showToast('Compila entrambi i campi', 'warning');
			return;
		}
		if (newPassword !== confirmPassword) {
			showToast('Le password non coincidono', 'warning');
			return;
		}
		if (newPassword.length < MIN_LEN) {
			showToast('La password deve avere almeno 6 caratteri', 'warning');
			return;
		}
		if (!allowedSet.test(newPassword)) {
			showToast('Sono ammessi solo lettere, numeri e simboli standard (niente spazi o emoji)', 'warning');
			return;
		}
		if (!composition.test(newPassword)) {
			showToast('Serve almeno 1 maiuscola, 1 minuscola, 1 numero e 1 simbolo', 'warning');
			return;
		}

		const bodyData = { password: newPassword };

		const btn = form.querySelector('.cta-submit');
		btn.disabled = true;

		appFetchWithToken(AppURLs.api.putProfile(), {
			method: 'PUT',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(bodyData),
		})
			.then((res) => {
				if (res.status) {
					showToast(res.message || 'Password aggiornata con successo!', 'success');
					form.reset();
				} else {
					showToast(res.message || "Errore durante l'aggiornamento della password.", 'error');
				}
			})
			.catch((err) => {
				handleError(err, 'Errore aggiornamento password');
			})
			.finally(() => {
				btn.disabled = false;
			});
	});

	function toggleBetaCards(){
		userId = dataStore.user.id;
		const show = Number(userId) >= 1 && Number(userId) <= 10;
		const privacyAccordion = document.querySelector('.privacy-accordion');

		if (privacyAccordion) privacyAccordion.style.display = show ? '' : 'none';
	}


	function setupBasicForm(profile) {
		const form = document.getElementById('basicForm');
		if (!form) return;

		const cardEl = form.closest('app-card'); 
		const nameInput    = document.getElementById('basicName');
		const surnameInput = document.getElementById('basicSurname');
		const emailInput   = document.getElementById('basicEmail');

		const nameHint    = document.getElementById('basicNameHint');
		const surnameHint = document.getElementById('basicSurnameHint');
		const emailHint   = document.getElementById('basicEmailHint');

		const empty = v => v == null || String(v).trim() === '';

		nameInput.value    = empty(profile?.name)    ? '' : String(profile.name);
		surnameInput.value = empty(profile?.surname) ? '' : String(profile.surname);
		emailInput.value   = empty(profile?.email)   ? '' : String(profile.email);

		nameInput.disabled    = !empty(profile?.name);
		surnameInput.disabled = !empty(profile?.surname);
		emailInput.disabled   = !empty(profile?.email);

		if (nameHint)    nameHint.textContent    = nameInput.disabled    ? 'Già presente in archivio' : 'Dato mancante. Puoi inserirlo ora.';
		if (surnameHint) surnameHint.textContent = surnameInput.disabled ? 'Già presente in archivio' : 'Dato mancante. Puoi inserirlo ora.';
		if (emailHint)   emailHint.textContent   = emailInput.disabled   ? 'Già presente in archivio' : 'Dato mancante. Puoi inserirlo ora.';

		function updateCardVisibility() {
			const hasMissing = empty(profileData?.name ?? profile?.name)
							|| empty(profileData?.surname ?? profile?.surname)
							|| empty(profileData?.email ?? profile?.email);
			if (cardEl) {
				cardEl.classList.toggle('d-none', !hasMissing);
			}
		}
		updateCardVisibility();

		form.addEventListener('submit', async (e) => {
			e.preventDefault();

			const basic = {};
			if (!nameInput.disabled && nameInput.value.trim()) {
				basic.name = nameInput.value.trim();
			}
			if (!surnameInput.disabled && surnameInput.value.trim()) {
				basic.surname = surnameInput.value.trim();
			}
			if (!emailInput.disabled && emailInput.value.trim()) {
				basic.email = nameInput.value.trim();
				basic.email = emailInput.value.trim().toLowerCase();
			}

			if (Object.keys(basic).length === 0) {
				showToast('Non c’è nulla da aggiornare.', 'info');
				return;
			}

			if (basic.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(basic.email)) {
				showToast('Email non valida.', 'warning');
				return;
			}

			try {
				const res = await appFetchWithToken(AppURLs.api.putProfile(), {
					method: 'PUT',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({ basic }),
				});

				if (res?.status) {
					showToast('Dati anagrafici aggiornati', 'success');

					if (basic.name) {
						profileData.name = basic.name;
						nameInput.value = basic.name;
						nameInput.disabled = true;
						if (nameHint) nameHint.textContent = 'Già presente in archivio';
					}
					if (basic.surname) {
						profileData.surname = basic.surname;
						surnameInput.value = basic.surname;
						surnameInput.disabled = true;
						if (surnameHint) surnameHint.textContent = 'Già presente in archivio';
					}
					if (basic.email) {
						profileData.email = basic.email;
						emailInput.value = basic.email;
						emailInput.disabled = true;
						if (emailHint) emailHint.textContent = 'Già presente in archivio';
					}

					updateCardVisibility();
				} else {
					showToast(res?.message || 'Errore aggiornamento dati anagrafici', 'error');
				}
			} catch (err) {
				handleError(err, 'Errore aggiornamento dati anagrafici');
			}
		});
	}

	// Consensi
	const btnConsents = document.getElementById("btnConfirmConsents");
	if (btnConsents) {
		btnConsents.addEventListener("click", () => {
			const acceptMarketing = document.getElementById("consent_marketing").checked;

			const bodyData = {
				consents: {
					accept_marketing: acceptMarketing
				}
			};

			appFetchWithToken(AppURLs.api.putProfile(), {
				method: "PUT",
				headers: { "Content-Type": "application/json" },
				body: JSON.stringify(bodyData),
			})
				.then((res) => {
					if (res.status) {
						showToast("Consensi aggiornati con successo!", "success");
						if (!profileData.consents) profileData.consents = {};
						profileData.consents.accept_marketing = acceptMarketing;
					} else {
						showToast(res.message || "Errore durante l'aggiornamento dei consensi", "error");
					}
				})
				.catch((err) => {
					handleError(err, "Errore aggiornamento consensi");
				});
		});
	}

	const btnDelete = document.getElementById("btnDeleteAccount");
	if (btnDelete) {
		btnDelete.addEventListener("click", () => {
			const conferma = confirm("Sei sicuro di voler eliminare il tuo account?");
			if (!conferma) return;

			const modalEl = document.getElementById("deleteAccountModal");
			const modal = new bootstrap.Modal(modalEl);
			modal.show();

			modalEl.addEventListener("hidden.bs.modal", () => {
				appLogout();
			}, { once: true }); 
		});
	}

});
