<?php if( ! defined('JTA') ){ header('HTTP/1.0 403 Forbidden'); exit('Direct access is not permitted.'); }

?>

	<img src="<?php echo esc_url(site_url().'/uploads/pharmacies/1/farmacia-giovinazzi-cover.jpg'); ?>" alt="Ingresso Farmacia Giovinazzi" class="cover-image" width="1200" height="800" />

	<app-card>
		<div class="content">
			<div class="text-center my-4">
				<h1 class="fw-bold mb-2 fs-2">Farmacia Giovinazzi</h1>
				<p class="lead text-muted">Da oltre 50 anni al cuore di Terracina</p>
			</div>

			<h2>Una storia di famiglia</h2>
			<p>La Farmacia Giovinazzi è presente nel cuore di Terracina da più di cinquant'anni. Nata grazie all'impegno e alla dedizione di Sandro Giovinazzi e di sua moglie Carla, la farmacia ha sempre rappresentato un punto di riferimento per la salute e il benessere della comunità.</p>
			<p>Oggi sono le figlie Paola, Emanuela e Raffaella a portare avanti questa tradizione, trasformando la farmacia in un luogo moderno, accogliente e innovativo, senza mai perdere il calore umano e i valori tramandati dai fondatori.</p>

			<h2>Un team al servizio della comunità</h2>
			<ul>
				<li>Giovani colleghe farmaciste, esperte in nutrizione e dermocosmesi, sempre disponibili ad ascoltare e consigliare.</li>
				<li>Tecnici responsabili della logistica, sicurezza e sistemi informatici, che garantiscono un'organizzazione efficiente, la gestione del magazzino e il corretto funzionamento di software e hardware.</li>
				<li>Collaboratori al banco e in laboratorio, che ogni giorno si prendono cura dei nostri clienti con professionalità e attenzione.</li>
			</ul>
			<p>Insieme formiamo una squadra completa, dove ognuno ha un ruolo fondamentale per offrire un servizio di qualità.</p>

			<button class="accordion" id="pharmaOrari">Orari di apertura</button>
			<div class="panel">
				<table>
					<thead>
						<tr style="background-color: #f2f2f2;">
							<th>Giorno</th>
							<th>Mattina</th>
							<th>Pomeriggio</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td>Lunedì</td>
							<td>08:30 – 13:00</td>
							<td>16:00 – 19:30</td>
						</tr>
						<tr>
							<td>Martedì</td>
							<td>08:30 – 13:00</td>
							<td>16:00 – 19:30</td>
						</tr>
						<tr>
							<td>Mercoledì</td>
							<td>08:30 – 13:00</td>
							<td>16:00 – 19:30</td>
						</tr>
						<tr>
							<td>Giovedì</td>
							<td>08:30 – 13:00</td>
							<td>16:00 – 19:30</td>
						</tr>
						<tr>
							<td>Venerdì</td>
							<td>08:30 – 13:00</td>
							<td>16:00 – 19:30</td>
						</tr>
						<tr>
							<td>Sabato</td>
							<td>08:30 – 13:00</td>
							<td>16:00 – 19:30</td>
						</tr>
						<tr>
							<td>Domenica</td>
							<td>10:00 – 13:00</td>
							<td>–</td>
						</tr>
					</tbody>
				</table>
			</div>

			<button class="accordion" id="pharmaTurni">Calendario turni</button>
			<div class="panel">
				<div id="turni-calendar" class="mt-2"></div>
			</div>

			<button class="accordion" id="pharmaService">I nostri servizi principali</button>
			<div class="panel">
				<div class="row row-cols-1 g-3">
					<div class="col">
						<div class="card h-100 shadow-sm border-0">
							<div class="card-body d-flex align-items-start">
								<i class="bi bi-capsule me-3 fs-3 text-primary"></i>
								<div>
									<h6 class="fw-bold mb-1">Preparazioni galeniche</h6>
									<p class="mb-0 small text-muted">Personalizzate secondo le esigenze del paziente.</p>
								</div>
							</div>
						</div>
					</div>
					<div class="col">
						<div class="card h-100 shadow-sm border-0">
							<div class="card-body d-flex align-items-start">
								<i class="bi bi-journal-medical me-3 fs-3 text-success"></i>
								<div>
									<h6 class="fw-bold mb-1">Ricognizione terapeutica</h6>
									<p class="mb-0 small text-muted">Per pazienti cronici e fragili, con riconciliazione delle terapie.</p>
								</div>
							</div>
						</div>
					</div>
					<div class="col">
						<div class="card h-100 shadow-sm border-0">
							<div class="card-body d-flex align-items-start">
								<i class="bi bi-heart-pulse me-3 fs-3 text-danger"></i>
								<div>
									<h6 class="fw-bold mb-1">Telemedicina cardiologica</h6>
									<p class="mb-0 small text-muted">ECG, Holter pressorio e cardiaco in farmacia e a domicilio.</p>
								</div>
							</div>
						</div>
					</div>
					<div class="col">
						<div class="card h-100 shadow-sm border-0">
							<div class="card-body d-flex align-items-start">
								<i class="bi bi-people me-3 fs-3 text-info"></i>
								<div>
									<h6 class="fw-bold mb-1">Consulenze specialistiche</h6>
									<p class="mb-0 small text-muted">Psicologo, nutrizionista, tricologo, posturologo, fisioterapista.</p>
								</div>
							</div>
						</div>
					</div>
					<div class="col">
						<div class="card h-100 shadow-sm border-0">
							<div class="card-body d-flex align-items-start">
								<i class="bi bi-hospital me-3 fs-3 text-warning"></i>
								<div>
									<h6 class="fw-bold mb-1">Infermiere a domicilio</h6>
									<p class="mb-0 small text-muted">Iniezioni, medicazioni, gestione catetere e stomie, parametri.</p>
								</div>
							</div>
						</div>
					</div>
					<div class="col">
						<div class="card h-100 shadow-sm border-0">
							<div class="card-body d-flex align-items-start">
								<i class="bi bi-file-earmark-text me-3 fs-3 text-secondary"></i>
								<div>
									<h6 class="fw-bold mb-1">Ritiro ricette</h6>
									<p class="mb-0 small text-muted">Dal medico curante, su delega firmata.</p>
								</div>
							</div>
						</div>
					</div>
					<div class="col">
						<div class="card h-100 shadow-sm border-0">
							<div class="card-body d-flex align-items-start">
								<i class="bi bi-truck me-3 fs-3 text-primary"></i>
								<div>
									<h6 class="fw-bold mb-1">Consegna farmaci</h6>
									<p class="mb-0 small text-muted">Direttamente a domicilio.</p>
								</div>
							</div>
						</div>
					</div>
					<div class="col">
						<div class="card h-100 shadow-sm border-0">
							<div class="card-body d-flex align-items-start">
								<i class="bi bi-droplet-half me-3 fs-3 text-danger"></i>
								<div>
									<h6 class="fw-bold mb-1">Autoanalisi e screening</h6>
									<p class="mb-0 small text-muted">Glicemia, colesterolo, tamponi COVID e altro.</p>
								</div>
							</div>
						</div>
					</div>
					<div class="col">
						<div class="card h-100 shadow-sm border-0">
							<div class="card-body d-flex align-items-start">
								<i class="bi bi-calendar-check me-3 fs-3 text-success"></i>
								<div>
									<h6 class="fw-bold mb-1">Prenotazioni CUP</h6>
									<p class="mb-0 small text-muted">Visite ed esami in pochi click.</p>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<p class="mt-3 fw-semibold">Farmacia Giovinazzi: benvenuti nella nostra farmacia, benvenuti nella nostra famiglia.</p>

			<h2>Guarda il nostro video di presentazione</h2>
			<video controls>
				<source src="https://assistentefarmacia.it/app-cliente-farmacia/img/video_farmacia.mp4" type="video/mp4" />
				Il tuo browser non supporta la riproduzione video.
			</video>
		</div>

		<div class="contact">
			<h2>Dove ci troviamo</h2>
			<p>
				📍
				<a href="https://maps.app.goo.gl/CPr3kkWyjAoUthHA6" target="_blank">Piazza Cavalieri di Vittorio Veneto 6 – 04019 Terracina (LT)</a>
			</p>
			<p>
				📧
				<a href="mailto:farmaciagiovinazzi@alice.it">farmaciagiovinazzi@alice.it</a>
			</p>
			<p>
				📞
				<a href="tel:0773700264">0773 700264</a>
			</p>

			<!-- Social -->
			<div class="social-icons" style="margin-top: 1rem; display:flex; justify-content: center; gap:1rem;">
				<a href="https://www.facebook.com/p/Farmacia-Giovinazzi-100063672513586/" target="_blank" rel="noopener noreferrer" aria-label="Visita la nostra pagina Facebook">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="1.8em" height="1.8em" fill="#1877f2">
						<path d="M512 256C512 114.6 397.4 0 256 0S0 114.6 0 256C0 376 82.7 476.8 194.2 504.5V334.2H141.4V256h52.8V222.3c0-87.1 39.4-127.5 125-127.5c16.2 0 44.2 3.2 55.7 6.4V172c-6-.6-16.5-1-29.6-1c-42 0-58.2 15.9-58.2 57.2V256h83.6l-14.4 78.2H287V510.1C413.8 494.8 512 386.9 512 256h0z" />
					</svg>
				</a>
				<a href="https://www.instagram.com/farmaciagiovinazzi/" target="_blank" rel="noopener noreferrer" aria-label="Visita il nostro profilo Instagram">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" width="1.8em" height="1.8em" fill="#E4405F">
						<path d="M224.1 141c-63.6 0-114.9 51.3-114.9 114.9s51.3 114.9 114.9 114.9S339 319.5 339 255.9 287.7 141 224.1 141zm0 189.6c-41.1 0-74.7-33.5-74.7-74.7s33.5-74.7 74.7-74.7 74.7 33.5 74.7 74.7-33.6 74.7-74.7 74.7zm146.4-194.3c0 14.9-12 26.8-26.8 26.8-14.9 0-26.8-12-26.8-26.8s12-26.8 26.8-26.8 26.8 12 26.8 26.8zm76.1 27.2c-1.7-35.9-9.9-67.7-36.2-93.9-26.2-26.2-58-34.4-93.9-36.2-37-2.1-147.9-2.1-184.9 0-35.8 1.7-67.6 9.9-93.9 36.1s-34.4 58-36.2 93.9c-2.1 37-2.1 147.9 0 184.9 1.7 35.9 9.9 67.7 36.2 93.9s58 34.4 93.9 36.2c37 2.1 147.9 2.1 184.9 0 35.9-1.7 67.7-9.9 93.9-36.2 26.2-26.2 34.4-58 36.2-93.9 2.1-37 2.1-147.8 0-184.8zM398.8 388c-7.8 19.6-22.9 34.7-42.6 42.6-29.5 11.7-99.5 9-132.1 9s-102.7 2.6-132.1-9c-19.6-7.8-34.7-22.9-42.6-42.6-11.7-29.5-9-99.5-9-132.1s-2.6-102.7 9-132.1c7.8-19.6 22.9-34.7 42.6-42.6 29.5-11.7 99.5-9 132.1-9s102.7-2.6 132.1 9c19.6 7.8 34.7 22.9 42.6 42.6 11.7 29.5 9 99.5 9 132.1s2.7 102.7-9 132.1z" />
					</svg>
				</a>
			</div>
		</div>
	</app-card>
