<?php
	require_once('../_api_bootstrap.php');
	$default_test_phone = '';
	$pharma = get_pharma_by_id(1);
	if( $pharma ){
		$default_test_phone = $pharma['phone_number'];
	}

?><!doctype html>
<html lang="it">
<head>
	<meta charset="utf-8">
	<title>Test WhatsApp Link</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<style>
		body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; padding: 24px; line-height: 1.4; }
		code, pre { background: #f5f5f7; padding: 4px 6px; border-radius: 6px; }
		.row { display: flex; gap: 24px; align-items: flex-start; flex-wrap: wrap; }
		.card { border: 1px solid #e6e6ea; border-radius: 10px; padding: 16px; max-width: 700px; }
		.btn { padding: 10px 14px; border: 1px solid #222; border-radius: 8px; background: #fff; cursor: pointer; }
		.btn:hover { background: #f0f0f0; }
		.muted { color: #666; font-size: 13px; }
		img { max-width: 240px; height: auto; border: 1px solid #e6e6ea; border-radius: 8px; }
		label { display:block; margin-top: 8px; font-weight: 600; }
		input, textarea { width:100%; padding:10px; border:1px solid #ccc; border-radius:8px; font: inherit; }
		textarea { min-height: 130px; }
	</style>
</head>
<body>
	<h1>Test invio WhatsApp in locale</h1>
	<p class="muted">Funziona senza console. Ti do link, bottone e QR.</p>

	<div class="row">
		<div class="card" style="flex:2 1 420px">
			<label for="phone">Numero destinatario (senza +, es: 39xxxxxxxxxx)</label>
			<input id="phone" value="<?php echo esc_attr($default_test_phone); ?>" />

			<label for="msg">Messaggio di test</label>
			<textarea id="msg"></textarea>

			<div style="display:flex; gap:12px; margin-top:12px;">
				<button id="makeLink" class="btn">Genera link</button>
				<button id="openNow" class="btn">Apri WhatsApp ora</button>
			</div>

			<p style="margin-top:14px;">URL generato:</p>
			<p><a id="waLink" style="word-break: break-all;" href="#" target="_blank" rel="noopener noreferrer">—</a></p>
			<p class="muted">Sugli smartphone, apri questo link. Oppure scansiona il QR qui accanto.</p>
		</div>

		<div class="card" style="flex:1 1 260px">
			<p style="margin:0 0 8px 0;"><strong>QR per aprire sul telefono</strong></p>
				<img id="qr" alt="QR code" />
			<p class="muted">Se il QR non si carica, sei offline. Usa il link sopra.</p>
		</div>
	</div>

	<script>
		function buildWhatsAppUrl(phone, text) {
			if (typeof phone !== "string" || !phone.trim()) {
				throw new Error("Telefono mancante o non valido.");
			}

			// normalizzazione base
			let raw = phone.trim();
			if (raw.startsWith("00")) raw = raw.slice(2);
			raw = raw.replace(/[^\d+]/g, "");
			if (raw.startsWith("+")) {
				raw = raw.slice(1); 
			}
			if (!/^\d{6,15}$/.test(raw)) {
				throw new Error("Numero non valido dopo la normalizzazione.");
			}

			const encoded = encodeURIComponent(text || "");
			return `https://wa.me/${raw}?text=${encoded}`;
		}

		// Messaggio di crash test pieno di roba "strana"
		const testMessage = `Ciao 👋\n`
			+`Questo è un *test grassetto*\n`
			+`e questo è _corsivo_\n`
			+`~barrato~ e anche \`\`\`monospazio\`\`\`.\n`
			+`\n`
			+`Caratteri strani: !@#$%^&*()_+-=[]{};:'"\\|,<.>/?¿¡\n`
			+`Emoji miste: 😂🔥🎉🍕💊🧩🦄🚀\n`
			+`Test di newline:\n`
			+`1️⃣ Riga uno\n`
			+`2️⃣ Riga due\n`
			+`3️⃣ Riga tre\n`
			+`\n`
			+`Fine del messaggio ✔️`;

		// Precarica il textarea
		document.getElementById("msg").value = testMessage;

		const phoneEl = document.getElementById("phone");
		const msgEl = document.getElementById("msg");
		const linkEl = document.getElementById("waLink");
		const qrEl = document.getElementById("qr");

		function refreshOutputs() {
			try {
				const url = buildWhatsAppUrl(phoneEl.value, msgEl.value, { defaultCountryCode: "39" });
				linkEl.textContent = url;
				linkEl.href = url;

				const qrApi = "https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=" + encodeURIComponent(url);
				qrEl.src = qrApi;

				return url;
			} catch (e) {
				linkEl.textContent = "Errore: " + e.message;
				linkEl.removeAttribute("href");
				qrEl.removeAttribute("src");
				return null;
			}
		}

		document.getElementById("makeLink").addEventListener("click", refreshOutputs);

		document.getElementById("openNow").addEventListener("click", () => {
			const url = refreshOutputs();
			if (url) window.location.href = url;
		});

		refreshOutputs();
	</script>
</body>
</html>