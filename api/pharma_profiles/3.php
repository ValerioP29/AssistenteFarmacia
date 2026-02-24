<?php if( ! defined('JTA') ){ header('HTTP/1.0 403 Forbidden'); exit('Direct access is not permitted.'); } ?>

<img
	src="<?php echo esc_url(site_url().'/uploads/pharmacies/3/farmacia-gemelli-cover.jpg'); ?>"
	alt="Ingresso Farmacia Ai Gemelli"
	class="cover-image"
	width="1200"
	height="900"
/>

<app-card>
	<div class="content">

		<div class="text-center my-4">
			<h1 class="fw-bold mb-2 fs-2">Farmacia AI Gemelli</h1>
			<p class="lead text-muted">Via Bartolomeo D'Alviano 23 – Trieste</p>
		</div>

		<h2>Chi siamo</h2>
		<p>
			Benvenuti alla Farmacia AI Gemelli, una realtà moderna e dinamica nel cuore di Trieste.
			Il nostro team si dedica ogni giorno alla salute e al benessere con competenza e umanità.
			Offriamo un punto di riferimento affidabile, unendo consulenza professionale e servizi innovativi.
			Crediamo nella prevenzione e nell’ascolto, con telemedicina e autoanalisi rapide per risposte semplici,
			sicure e vicine a casa.
		</p>

		<button class="accordion" id="pharmaOrari">Orari di apertura</button>
		<div class="panel">
			<table>
				<thead>
					<tr style="background-color:#f2f2f2;">
						<th>Giorno</th>
						<th>Orario</th>
					</tr>
				</thead>
				<tbody>
					<tr><td>Lunedì</td><td>08:30 – 19:30</td></tr>
					<tr><td>Martedì</td><td>08:30 – 19:30</td></tr>
					<tr><td>Mercoledì</td><td>08:30 – 19:30</td></tr>
					<tr><td>Giovedì</td><td>08:30 – 19:30</td></tr>
					<tr><td>Venerdì</td><td>08:30 – 19:30</td></tr>
					<tr><td>Sabato</td><td>08:30 – 19:30</td></tr>
					<tr><td>Domenica</td><td>10:00 – 19:30</td></tr>
				</tbody>
			</table>
		</div>

		<button class="accordion" id="pharmaTurni">Calendario turni</button>
		<div class="panel">
			<div id="turni-calendar" class="mt-2"></div>
		</div>

		<button class="accordion" id="pharmaService">I nostri servizi</button>
		<div class="panel">

			<h3>TELEMEDICINA</h3>
			<ul class="service-description__list">
				<li>ECG</li>
				<li>Teledermatologia</li>
				<li>Holter Pressorio</li>
				<li>Holter Cardiaco</li>
				<li>Spirometria</li>
				<li>Screening Venoso</li>
				<li>Polisonno</li>
				<li>HIGO</li>
			</ul>

			<h3>Prelievi Capillari e Analisi</h3>
			<ul class="service-description__list">
				<li>Glicemia</li>
				<li>INR</li>
				<li>Pacchetto profilo lipidico</li>
				<li>Pacchetto creatinina</li>
				<li>Funzionalità renale</li>
				<li>Pacchetto transaminasi + funzionalità fegato</li>
				<li>Kit prelievo completo</li>
				<li>Celiachia</li>
				<li>Test allergie</li>
				<li>Intolleranze alimentari</li>
				<li>Tampone per streptococco</li>
				<li>Analisi urine</li>
			</ul>

			<h3>Professionisti</h3>
			<ul class="service-description__list">
				<li>Infermiere</li>
				<li>Nutrizionista</li>
				<li>Fisioterapista</li>
				<li>Logopedista</li>
				<li>Ostetricia</li>
				<li>Ottico</li>
			</ul>

			<h3>Servizi a domicilio</h3>
			<ul class="service-description__list">
				<li>Telemedicina a domicilio</li>
				<li>Servizi infermieristici a domicilio</li>
				<li>Manicure e pedicure a domicilio</li>
				<li>Fisioterapia a domicilio</li>
				<li>Consegna a domicilio</li>
			</ul>

		</div>

	</div>

	<div class="contact">
		<h2>Dove ci troviamo</h2>

		<p>
			📍
			<a href="https://maps.google.com/?q=Via+Bartolomeo+D'Alviano+23+Trieste" target="_blank">
				Via Bartolomeo D'Alviano 23 – Trieste
			</a>
		</p>

		<p>
			📧
			<a href="mailto:farmaciagemelli@aol.it">farmaciagemelli@aol.it</a>
		</p>

		<p>
			📞
			<a href="tel:0403409851">040 3409851</a>
			· WhatsApp
			<a href="https://wa.me/393200958357">320 0958357</a>
		</p>
	</div>
</app-card>
