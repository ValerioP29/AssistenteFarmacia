<?php if( ! defined('JTA') ){ header('HTTP/1.0 403 Forbidden'); exit('Direct access is not permitted.'); }

?>

	<app-card>
		<div class="content">
			<div class="text-center my-4">
				<h1 class="fw-bold mb-2 fs-2">Farmacia Demo</h1>
				<p class="lead text-muted">Da oltre 50 anni al cuore di Terracina</p>
			</div>

			<h2>Una storia di famiglia</h2>
			<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Quisque aliquam posuere purus id luctus. Nunc aliquam ante tortor, ac molestie erat egestas sit amet. Ut non dolor tellus. Nulla viverra orci vitae dignissim placerat. Mauris viverra, ante tristique cursus blandit, felis justo lobortis nulla, a ultrices tellus enim ut felis. Donec laoreet nulla a felis cursus elementum. Vivamus tristique sapien vitae erat tempor, ac blandit purus sollicitudin.</p>

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

		</div>

	</app-card>
