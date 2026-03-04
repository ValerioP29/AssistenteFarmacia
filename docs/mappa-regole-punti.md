# Mappa regole punti (regole aggiornate)

## Dove sono salvati i punti

- Totale mese corrente utente: `jta_users.points_current_month`.
- Ledger movimenti: `jta_user_points_log` (`user_id`, `pharma_id`, `date`, `points`, `source`).
- Summary mensile aggregata: `jta_user_points_summary` (`year`, `month`, `total_points`).

## Funzioni backend coinvolte

- Inserimento movimento punti: `UserPointsModel::addPoints(...)` (`api/helpers/_model_points.php`).
- Aggiornamento totale utente: `PointsSummaryModel::updateCurrentMonthPoints(...)`.
- Rigenerazione summary mensile: `PointsSummaryModel::regenerateByUser(...)`.

## Regole attive (azioni → punti)

> Valori configurati con `get_option(..., 10)` dove indicato (fallback 10).

- `quiz_daily` → punti quiz del giorno (valore da quiz corrente)  
  Endpoint: `api/quiz-post.php`.
- `reservation_request` → 10 punti (fallback) per richiesta prodotti/ricette inviata  
  Endpoint: `api/reservation-post.php`, chiave config `point--request_reservation`.
- `event_booking` → 10 punti (fallback) per prenotazione evento inviata  
  Endpoint: `api/event-post.php`, chiave config `point--event_booking`.
- `service_booking` → 10 punti (fallback) per prenotazione servizio inviata  
  Endpoint: `api/service-post.php`, chiave config `point--service_booking`.
- `order_checkout--<hash>` → 10 punti (fallback) per invio ordine dal carrello (incluse promo)  
  Endpoint: `api/order-post.php`, chiave config `point--order_checkout`.

## Regole rimosse

- Checkup: nessuna assegnazione punti (`api/checkup-post.php`).
- Sfida settimanale/challenge: nessuna assegnazione punti (`api/weekly-challenge-post.php`).

## Idempotenza checkout (`order-post`)

Senza cambiare schema DB, l’idempotenza è gestita con fingerprint giornaliero del carrello:

- normalizzazione item (`id`, `quantity`, `price_sale`) ordinati per `id`
- hash SHA1 su `{user_id, pharma_id, items}`
- source salvato come `order_checkout--<hash>`
- prima di assegnare punti: controllo `UserPointsModel::hasEntryForDate(user, pharma, source)`

Effetto: stesso carrello inviato più volte nello stesso giorno non genera punti duplicati.
