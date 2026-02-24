document.addEventListener('DOMContentLoaded', () => {
  const params = new URLSearchParams(window.location.search);
  const id_pre = Number(params.get('id'));
  if (!id_pre) {
  console.error('ID prenotazione mancante nell\'URL');
  return; // esce e non fa fetch inutili
  }

  let datiGlobali = [];

  // Funzione per generare le righe prezzo (con o senza sconto)
  function checkprezzo(info) {
    if (info.prodotto.prezzo_scontato) {
      return `
        <tr>
          <td><strong>Prezzo:</strong><s class="h4">${info.prodotto.prezzo.toFixed(2)}€</s></td>
          <td><strong>Prezzo scontato:</strong> <span class="h4">€${info.prodotto.prezzo_scontato.toFixed(2)}€</td>
        </tr>
      `;
    } else {
      return `
        <tr>
          <td colspan="2"><strong>Prezzo:</strong><span class="h4"> ${info.prodotto.prezzo.toFixed(2)}€</span></td>
        </tr>
      `;
    }
  }

  async function info_data() {
    try {
      const response = await fetch('../data/prenotazioni.json');
      if (!response.ok) throw new Error('Errore HTTP: ' + response.status);

      datiGlobali = await response.json();
      const info = datiGlobali.find(o => o.id_prenotazione === id_pre);
      if (!info) throw new Error('Prenotazione non trovata.');

     

      const info_prenotazione = document.getElementById("info_prenotazione");
      info_prenotazione.innerHTML = `
      <table>
        <tbody>
          <tr><td><strong>Id prenotazione:</strong></td><td>${info.id_prenotazione}</td></tr>
          <tr><td><strong>Nome cliente:</strong></td><td>${info.username}</td></tr>
          <tr><td><strong>Data ordine:</strong></td><td>${info.data.split("-").reverse().join("-")}</td></tr>
          <tr><td><strong>Urgente:</strong></td><td>${info.urgente ? 'Sì' : 'No'}</td></tr>
          <tr><td><strong>Stato prenotazione:</strong></td><td>
            <select id="selectStatus">
              <option value="completato">completato</option>
              <option value="in elaborazione">in elaborazione</option>
              <option value="da elaborare">da elaborare</option>
            </select>
          </td></tr>
          <tr><td><strong>Note cliente:</strong></td><td>${info.note_utente}</td></tr>
        </tbody>
      </table>
      <div>
        <img id="img_medium" src="./images/ricetta_medica.png" alt="immagine del prodotto" class="img-thumbnail"
            onerror="this.onerror=null; this.src='../img/default.png';"
            style="max-width: 200px; cursor: pointer;">
      </div>
      `;

// Ora selezioniamo il select appena inserito
const select = document.getElementById('selectStatus');
const statusDaSelezionare = info.status.toLowerCase();

// Cicliamo sulle opzioni e selezioniamo quella corrispondente
for (const option of select.options) {
  if (option.text.toLowerCase() === statusDaSelezionare) {
    option.selected = true;
    break;
  }
}


      const info_prodotto = document.getElementById("info_prodotto");
      info_prodotto.innerHTML = `
      <table>
            <tbody >
        <tr>
          <td colspan="2">
            <img src="${info.prodotto.img}" alt="${info.prodotto.nome}" style="max-width: 120px; border-radius: 6px;">
          </td>
        </tr>
        <tr><td><strong>ID:</strong> ${info.prodotto.id}</td><td><strong>Nome:</strong> ${info.prodotto.nome}</td></tr>
        ${checkprezzo(info)}
            </tbody>
      </table> 
      
      `;

    const chatContainer = document.getElementById("chatmemory");

      info.storico_messaggi.forEach(msg => {
      const div = document.createElement("div");
      if(msg.id === 'farmacista' )
      {
        div.classList.add("farmacista");
      }else{
        div.classList.add("cliente");
      }
      div.classList.add("messaggio");

      // Formatta data ISO in modo più leggibile
      const dateObj = new Date(msg.data);
      const formattedDate = dateObj.toLocaleString('it-IT', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });


      div.innerHTML = `
        <div>${msg.messaggio}</div>
        <div class="timestamp">${formattedDate}</div>
      `;
      chatContainer.appendChild(div);
    });

    } catch (error) {
      console.error('Errore:', error);
    }
  }

  function showImageInModal(img) {
    const modalImg = document.getElementById('modalImage');
    modalImg.src = img.src;

    const modal = new bootstrap.Modal(document.getElementById('imgModal'));
    modal.show();
  }

  info_data();

  // Event delegation per immagine modale
  document.getElementById('info_prenotazione').addEventListener('click', (e) => {
    if (e.target && e.target.id === 'img_medium') {
      showImageInModal(e.target);
    }
  });
});
