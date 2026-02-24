document.addEventListener('appLoaded', function () {

	async function getPharmaProfileById(id) {
		try {
			const url = AppURLs.api.getPharmacyProfile(id ?? '')
			const res = await appFetchWithToken(url);

			if (!res || !res.status || !res.data) {
				const error = (res?.message || 'Errore caricamento sondaggio.');
				document.dispatchEvent(new CustomEvent('pharmaProfile:fetch:error', {detail: { pharma_id: id, error: error }}));
				return;
			}

			const data = res.data;
			document.dispatchEvent(new CustomEvent('pharmaProfile:fetch:success', {detail: { pharma_id: id, profile: data }}));
		} catch (err) {
			const error = 'Errore di rete.';
			document.dispatchEvent(new CustomEvent('pharmaProfile:fetch:error', {detail: { pharma_id: id, error: error }}));
		}
	}

	function initAccordion(){
		const acc = document.getElementsByClassName('accordion');
		for (let i = 0; i < acc.length; i++) {
			acc[i].addEventListener('click', function () {
				this.classList.toggle('active');
				const panel = this.nextElementSibling;
				panel.style.display = panel.style.display === 'block' ? 'none' : 'block';
			});
		}
	}

	// Helpers
	const capitalize = (s) => (s ? s.charAt(0).toUpperCase() + s.slice(1) : s);
	const MESE_LABEL_IT = ['gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];

	function parseISO(iso) {
		const [y, m, d] = iso.split('-').map(Number);
		return new Date(y, m - 1, d);
	}

	function itWeekday(d) {
		return d.toLocaleDateString('it-IT', {weekday: 'long'});
	}

	function itMonth(d) {
		return MESE_LABEL_IT[d.getMonth()];
	}

	function renderTurniCalendar(list) {
		const container = document.getElementById('turni-calendar');
		if (!container) return;

		const items = list
			.map((t) => {
				const d = parseISO(t);
				return {
					weekday: itWeekday(d).slice(0, 3),
					monthLabel: itMonth(d),
					dayNum: d.getDate(),
					time: d.getTime(),
				};
			})
			.sort((a, b) => a.time - b.time);

		const byMonth = {};
		for (const it of items) {
			if (!byMonth[it.monthLabel]) byMonth[it.monthLabel] = [];
			byMonth[it.monthLabel].push(it);
		}

		let html = `<table class="table table-bordered table-striped text-center"><tbody>`;

			for (const month in byMonth) {
				const dates = byMonth[month].map((it) => `${it.dayNum} ${capitalize(it.weekday)}`);

				// intestazione mese
				html += `
					<tr class="table-light">
						<td colspan="3" class="text-capitalize fw-bold">${capitalize(month)}</td>
					</tr>
				`;

				// riga date
				html += `<tr>${dates.map((d) => `<td>${d}</td>`).join('')}</tr>`;
			}

		html += `</tbody></table>`;
		container.innerHTML = html;
	}

	function buildPharmaProfileHTML(pharmaData) {
		const main = document.querySelector('#app main');
		if( ! main ) return;
		main.innerHTML = pharmaData.profile;

		initAccordion();
		renderTurniCalendar(pharmaData.turni);
	}


	document.addEventListener('pharmaProfile:fetch:success', function(e){
		buildPharmaProfileHTML(e.detail.profile);
	});

	document.addEventListener('pharmaProfile:fetch:error', function(e){
		showSurveyError(e.detail.error);
	});

	getPharmaProfileById(dataStore.pharma.id);
});
