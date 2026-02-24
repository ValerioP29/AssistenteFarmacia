# Sistema di Notifiche - Assistente Farmacia

## Panoramica

Il sistema di notifiche implementato fornisce notifiche in tempo reale per le nuove richieste che arrivano nel sistema. Utilizza un approccio di polling ogni 5 minuti per controllare nuove richieste senza richiedere WebSocket o server aggiuntivi.

## Funzionalità

### ✅ Notifiche Desktop
- Popup del browser quando arrivano nuove richieste
- Richiede permesso dell'utente (richiesto automaticamente)
- Cliccabile per navigare alla sezione richieste
- Si chiude automaticamente dopo 5 secondi

### ✅ Notifiche In-App
- Toast di notifica nell'angolo superiore destro
- Mostra dettagli delle nuove richieste
- Si chiude automaticamente dopo 10 secondi
- Responsive per dispositivi mobili

### ✅ Badge Contatore
- Badge rosso con numero di nuove richieste
- Posizionato sul link "Richieste" nella navbar
- Animazione pulsante per attirare l'attenzione
- Si aggiorna automaticamente

### ✅ Avviso Sonoro
- Suono di notifica quando arrivano nuove richieste
- File audio generato automaticamente (beep doppio)
- Riproduzione automatica (se permesso dal browser)

### ✅ Gestione Stato
- Traccia l'ID dell'ultima richiesta vista
- Salva in localStorage per persistenza
- Si aggiorna quando l'utente visita la pagina richieste
- Evita notifiche duplicate

## Architettura

### Backend
```
api/notifications/check-new.php
├── Controlla autenticazione
├── Verifica nuove richieste dal database
├── Restituisce conteggio e dettagli
└── Gestisce errori e sicurezza
```

### Frontend
```
assets/js/notifications.js
├── Classe NotificationSystem
├── Polling ogni 5 minuti
├── Gestione notifiche browser
├── Gestione audio
└── Aggiornamento badge
```

### Stili
```
assets/css/notifications.css
├── Stili per badge
├── Stili per toast notifiche
├── Animazioni
└── Responsive design
```

## File Creati/Modificati

### Nuovi File
- `api/notifications/check-new.php` - API per controllare nuove richieste
- `assets/js/notifications.js` - Sistema JavaScript notifiche
- `assets/css/notifications.css` - Stili per notifiche
- `assets/sounds/notification.mp3` - File audio notifica
- `test-notifications.php` - Pagina di test sistema
- `NOTIFICHE_README.md` - Questa documentazione

### File Modificati
- `includes/header.php` - Aggiunto CSS notifiche
- `includes/footer.php` - Aggiunto JS notifiche
- `richieste.php` - Aggiornamento ID ultima richiesta vista

## Come Funziona

### 1. Inizializzazione
```javascript
// Il sistema si avvia automaticamente quando la pagina si carica
window.notificationSystem = new NotificationSystem();
```

### 2. Polling
```javascript
// Controlla ogni 5 minuti (300000 ms)
setInterval(() => {
    this.checkNewRequests();
}, 300000);
```

### 3. Controllo Nuove Richieste
```javascript
// Chiama l'API con l'ID dell'ultima richiesta vista
fetch(`api/notifications/check-new.php?last_seen=${this.lastSeenId}`)
```

### 4. Gestione Risposta
```javascript
// Se ci sono nuove richieste:
if (data.success && data.new_count > 0) {
    this.handleNewRequests(data);
}
```

### 5. Mostra Notifiche
- **Desktop**: Solo se la pagina non è attiva
- **In-App**: Sempre
- **Badge**: Aggiorna contatore
- **Audio**: Riproduce suono

## Configurazione

### Intervallo di Controllo
Modifica in `assets/js/notifications.js`:
```javascript
// Controlla ogni 5 minuti (300000 ms)
this.checkInterval = setInterval(() => {
    this.checkNewRequests();
}, 300000); // Cambia questo valore
```

### Suono di Notifica
Sostituisci `assets/sounds/notification.mp3` con il tuo file audio preferito.

### Permessi Browser
Il sistema richiede automaticamente i permessi per le notifiche desktop. Se negati, funzioneranno solo le notifiche in-app.

## Test del Sistema

### Pagina di Test
Accedi a `test-notifications.php` per:
- Verificare lo stato del sistema
- Testare manualmente le notifiche
- Controllare i permessi browser
- Verificare l'API
- Testare audio e badge

### Test Manuali
```javascript
// Test notifica desktop
testNotification();

// Test suono
testSound();

// Test notifica in-app
testInAppNotification();

// Test badge
testBadge();

// Reset ultimo ID
resetLastSeen();
```

## Risoluzione Problemi

### Notifiche Non Funzionano
1. Verifica permessi browser in `chrome://settings/content/notifications`
2. Controlla console browser per errori JavaScript
3. Verifica che l'API `check-new.php` funzioni
4. Controlla che l'utente sia autenticato

### Audio Non Funziona
1. Verifica che il file `notification.mp3` esista
2. Controlla permessi audio del browser
3. Verifica che il browser supporti l'API Audio

### Badge Non Si Aggiorna
1. Verifica che il link "Richieste" abbia `href="richieste.php"`
2. Controlla che il CSS sia caricato
3. Verifica che non ci siano errori JavaScript

### Performance
- Il polling ogni 5 minuti è ottimizzato per non sovraccaricare il server
- Le notifiche si mostrano solo quando necessario
- Il sistema si ferma automaticamente quando la pagina non è attiva

## Sicurezza

### Autenticazione
- Tutte le chiamate API richiedono autenticazione
- Verifica sessione PHP
- Controllo CSRF token

### Dati
- Sanitizzazione input/output
- Prepared statements per database
- Validazione lato server

## Compatibilità

### Browser Supportati
- ✅ Chrome/Chromium (tutte le funzionalità)
- ✅ Firefox (tutte le funzionalità)
- ✅ Safari (notifiche desktop limitate)
- ✅ Edge (tutte le funzionalità)

### Dispositivi
- ✅ Desktop (tutte le funzionalità)
- ✅ Tablet (notifiche in-app e badge)
- ✅ Mobile (notifiche in-app e badge)

## Manutenzione

### Log
Il sistema registra errori nella console del browser per il debug.

### Aggiornamenti
Per aggiornare il sistema:
1. Modifica i file JavaScript/CSS
2. Pulisci cache browser
3. Testa con `test-notifications.php`

### Monitoraggio
Controlla periodicamente:
- Permessi browser
- Funzionamento API
- File audio
- Performance sistema 