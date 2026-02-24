document.addEventListener('appLoaded', function () {
	fetchWellnessData();
});

function fetchWellnessData() {
	appFetchWithToken(AppURLs.api.getWellnessChallenge(), {
		method: 'GET',
	})
		.then((data) => {
			if (data.status) {
				document.dispatchEvent(new CustomEvent('wellnessChallengeSuccess', {detail: data.data}));
			} else {
				document.dispatchEvent(new CustomEvent('wellnessChallengeError', {detail: {error: data.error}}));
			}
		})
		.catch((err) => {
			document.dispatchEvent(new CustomEvent('wellnessChallengeError', {detail: {error: err.message}}));
		});
}

document.addEventListener('wellnessChallengeSuccess', function (event) {
	const challengeCard = document.querySelector('app-card');
	if (challengeCard) {
		challengeCard.style.display = '';
	}

	const data = event.detail;

	const container = document.querySelector('.challenge-box');
	if (!container) return;

	const isDoneToday = data.today_is_done;
	const isCompleted = data.is_completed;

	const buttonLabel = isCompleted ? 'SFIDA COMPLETATA ✅' : isDoneToday ? 'GIORNATA COMPLETATA' : 'FATTO ANCHE OGGI';

	container.innerHTML = `
        <h2><i class="fa ${data.icon}"></i> ${data.title}</h2>
        <p>
            ${data.description}
            <br /><br />
            <em>${data.instructions.join('<br />')}</em>
        </p>
     <!--   <div style="margin: 16px 0; font-size:13px; color:#6D2A93;">
            ⏳ Sfida di 7 giorni • ${data.reward}
        </div> -->
        <div class="progress-tracker" id="progressTracker"></div>
        <button class="submit-btn" id="completeDayBtn">${buttonLabel}</button>
    `;

	const btn = document.getElementById('completeDayBtn');

	if (isDoneToday || isCompleted) {
		btn.disabled = true;
		btn.classList.add('disabled'); // stile opzionale
	} else {
		btn.addEventListener('click', completeDay);
	}

	setProgressFromServer(data.progress);
});

// -------- TRACKER & GIORNATA --------
let progress = [];

function renderProgress() {
	const container = document.getElementById('progressTracker');
	if (!container) return;

	container.innerHTML = '';
	const days = ['L', 'M', 'M', 'G', 'V', 'S', 'D'];

	progress.forEach((done, index) => {
		const circle = document.createElement('div');
		circle.className = 'day' + (done ? ' completed' : '');
		circle.textContent = days[index];
		container.appendChild(circle);
	});
}

function completeDay() {
	const today = new Date().getDay();
	const index = today === 0 ? 6 : today - 1;

	appFetchWithToken(AppURLs.api.postWellnessDay(), {
		method: 'POST',
		headers: {'Content-Type': 'application/json'},
		body: JSON.stringify({
			day: index,
			date: new Date().toISOString(),
		}),
	})
		.then((data) => {
			if (data.status) {
				showToast(data.message || 'Ben fatto!', 'success');
				fetchWellnessData(); // refresh
			} else {
				showToast(data.message || 'Errore nel salvataggio.', 'error');
			}
		})
		.catch((err) => {
			showToast('Errore di rete, riprova più tardi', 'error');
			console.error(err);
		});
}

function setProgressFromServer(serverProgress) {
	progress = serverProgress.map((val) => !!val);
	renderProgress();
}

document.addEventListener('wellnessChallengeError', function (event) {
	console.error('Errore sfida benessere:', event.detail.error);

	const challengeCard = document.querySelector('app-card');
	if (challengeCard) {
		const h1 = challengeCard.querySelector('h1');

		challengeCard.innerHTML = '';
		if (h1) challengeCard.appendChild(h1);

		const errorDiv = document.createElement('div');
		errorDiv.className = 'challenge-error';
		errorDiv.innerHTML = `
		<h2>😢 Oops!</h2>
		<p class="mb-0">${event.detail.error || 'Non è stato possibile caricare la sfida benessere. Riprova più tardi.'}</p>
	`;

		challengeCard.appendChild(errorDiv);
	}
});
