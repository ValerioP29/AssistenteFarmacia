document.addEventListener('DOMContentLoaded', () => {

  let datiGlobali = []; // per salvare i dati caricati

  async function crea_tabella() {
    try {
      const response = await fetch('../data/prenotazioni.json');
      if (!response.ok) throw new Error('Errore HTTP: ' + response.status);
      const datiGlobali = await response.json();

      const ordineStatus = {
        'completato': 3,
        'in elaborazione': 2,
        'da elaborare': 1
      };

      datiGlobali.sort((a, b) => {
        // Ordina per stato usando ordineStatus
        if (ordineStatus[a.status] > ordineStatus[b.status]) return 1;
        if (ordineStatus[a.status] < ordineStatus[b.status]) return -1;

        // Se status uguale, ordina per urgenza: "si" prima di "no"
        if (a.urgente === 'si' && b.urgente !== 'si') return -1;
        if (a.urgente !== 'si' && b.urgente === 'si') return 1;

        return 0;
      });



      const contenitore = document.getElementById("contenitoreCard");
      contenitore.innerHTML = ''; // pulisci contenitore

      datiGlobali.forEach((item,index) => {
        const col = document.createElement("div");
        col.className = "col";

      col.innerHTML = `
      <div class="card h-100 shadow-sm">

        
        <div class="card-body d-flex flex-column">

          <p class="text-muted mb-1">Prenotazione #${item.id_prenotazione}</p>
          <p class="text-muted mb-1">Utente: ${item.username}</p>
          <p class="text-muted mb-1">Data: ${item.data.split("-").reverse().join("-")}</p>
          <p class="text-muted mb-1">
            Urgente: 
            <img src="./images/${iconurgenza(item.urgente)}" class="small"> 
            <span>${item.urgente}</span>
          </p>
          <p class="text-muted mb-3">
            Stato: 
            <img src="./images/${iconstato(item.status)}" class="small"> 
            <span>${item.status}</span>
          </p>

        <div class="card-footer bg-white border-top-0 d-flex justify-content-center">
          <a href="info_prenotazione.html?id=${item.id_prenotazione}" target="_blank"> <button class="btn btn-sm btn-info"  data-index="${index}">Dati prenotazione</button></a>
        </div>
      </div>
      `;


        contenitore.appendChild(col);
      });
    } catch (error) {
      console.error("Errore nel caricamento dei dati:", error);
    }
  }


  // Creo la modale una sola volta e la aggiungo al body
  function creaInfomodal() {
  const modalHtml = `
    <div class="modal fade" id="infomodal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-body text-center" id="modal">
            <table class='show_table' cellpadding="5" cellspacing="0" style="width:100%" >
              <tbody>
                <tr>
                  <td colspan="2" style="text-align:center;">
                    <img id="ricetta_medica" src="./images/ricetta_medica.png" alt="Ricetta Medica" class="img-fluid border">
                  </td>
                </tr>
                <tr>
                  <td colspan="2">
                    <label class="form-label" for="scelta" >Stato prenotazione</label>
                    <select id="scelta" name="scelta" class="form-select form-select" >
                      <option value="completato">Completato</option>
                      <option value="in_elaborazione">In elaborazione</option>
                      <option value="da_elaborare">Da elaborare</option>
                    </select>
                  </td>
                </tr>
                <tr>
                  <td class="pre-td">
                    Urgente:
                  </td>
                  <td >
                    <span id="info_urgente" class="form-label"></span>
                  </td>
                </tr>
                <tr>
                  <td class="pre-td">
                    Data prenotazione:
                  </td>
                  <td >
                    <span id="info_data" class="form-label"></span>
                  </td>
                </tr>
                <tr>
                  <td class="pre-td">
                    Nome utente:
                  </td>
                  <td >
                    <span id="info_nome" class="form-label"></span>  
                  </td>
                </tr>
                <tr>
                  <td class="pre-td">
                    Prodotto:
                  </td>
                  <td >
                    <span id="info_prodotto" class="form-label"></span>
                  </td>
                </tr>
                <tr >
                  <td colspan="2" style="text-align:center;">
                    <button type="button" id="modal-send" class="btn btn-secondary mt-3" data-bs-dismiss="modal">Salva</button>
                  </td>
                </tr>
              </tbody>  
            </table>
          </div>
        </div>
      </div>
    </div>
  `;

  document.body.insertAdjacentHTML('beforeend', modalHtml);
  return new bootstrap.Modal(document.getElementById('infomodal'));
}


  const infomodal = creaInfomodal();


  // Mappa testo status in valore select
  function statoValoreDaTesto(status) {
    switch(status.toLowerCase()) {
      case 'completato': return 'completato';
      case 'in elaborazione': return 'in_elaborazione';
      case 'da elaborare': return 'da_elaborare';
      default: return 'da_elaborare';
    }
  }

  function creachatmodal() {
    const modalHtml = `
    
  <div class="modal fade" id="chatmodal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-body text-center" id="modal">

          <form>
            <label for="description">stai scrivendo a: <span id="chat_nome"></span></label>
            <textarea maxlength="500" rows="5" cols="40" id="description" name="description" class="form_element"></textarea>

            <br>
            <button type="submit" class="btn btn-secondary mt-3">Invia</button>
          </form>

        </div>
      </div>
    </div>
  </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHtml);
    return new bootstrap.Modal(document.getElementById('chatmodal'));
  }

  const chatmodal = creachatmodal();

  // Funzione per aggiornare e mostrare la modale con i dati della riga selezionata
  function apriModalConDati(index) {
    const item = datiGlobali[index];
    if (!item) return;

    indiceSelezionato = index;
    datiOriginali = { ...item }; // copia superficiale dei dati

    document.getElementById('scelta').value = statoValoreDaTesto(item.status);
    document.getElementById('info_urgente').textContent =  item.urgente;
    document.getElementById('info_data').textContent =  item.data.split("-").reverse().join("-");
    document.getElementById('info_nome').textContent = item.username;
    document.getElementById('info_prodotto').textContent = item.prodotto;

    infomodal.show();
  }


function iconstato(status) {
  switch (status.toLowerCase()) {
    case 'completato':
      return 'check.png';
    case 'in elaborazione':
      return 'loading.png';
    case 'da elaborare':
      return 'delete.png';
    default:
      return 'greyball.png';
  }
}

function iconurgenza(status) {
  switch (status.toLowerCase()) {
    case 'no':
      return 'delete.png';
    case 'si':
      return 'check.png';
    default:
      return 'greyball.png';
  }
}



  crea_tabella();


document.getElementById('modal-send').addEventListener('click', async () => {
  if (indiceSelezionato === null) return;

  const nuovoStato = document.getElementById('scelta').value;
  const statoFinale = statoTestoDaValore(nuovoStato);



  // Aggiorna localmente lo status
  datiGlobali[indiceSelezionato].status = statoFinale;

  // Prepara dati da inviare: solo l'elemento modificato
  const datiDaInviare = {
    id: datiGlobali[indiceSelezionato].id,
    status: statoFinale
  };

  console.log(datiDaInviare)

  try {
    const response = await fetch('../api/farma_modificaprenotazioni.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(datiDaInviare)
    });

    if (!response.ok) throw new Error('Errore salvataggio dati');

    const result = await response.json();
    if (!result.success) {
      throw new Error(result.message || 'Errore server');
    }

    console.log('Dati salvati con successo');

    // Aggiorna la tabella con i nuovi dati
    crea_tabella();

  } catch (err) {
    console.error('Errore durante il salvataggio:', err);
  }

  infomodal.hide();
});



function statoTestoDaValore(value) {
  switch (value) {
    case 'completato': return 'completato';
    case 'in_elaborazione': return 'in elaborazione';
    case 'da_elaborare': return 'da elaborare';
    default: return 'da elaborare';
  }
}




});
