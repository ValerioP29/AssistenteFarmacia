<?php
/**
 * Configurazione Punteggi Richieste
 * Assistente Farmacia Panel
 * 
 * Questo file contiene la configurazione dei punteggi attribuiti
 * agli utenti per ogni tipologia di richiesta completata.
 * 
 * Per modificare i punteggi, cambia semplicemente i valori qui sotto.
 */

// Configurazione punteggi per tipologia di richiesta
$REQUEST_POINTS_CONFIG = [
    'event' => 10,        // Punteggio per richieste di evento
    'service' => 10,      // Punteggio per richieste di servizio
    'promos' => 10,       // Punteggio per richieste di promozione
    'reservation' => 10   // Punteggio per richieste di prenotazione
];

// Configurazione etichette source per il log
$REQUEST_SOURCE_LABELS = [
    'event' => 'Event request Completed',
    'service' => 'Service request Completed',
    'promos' => 'Promotion request Completed',
    'reservation' => 'Reservation request Completed'
];

/**
 * Funzione per ottenere il punteggio per una tipologia di richiesta
 * @param string $requestType Tipologia di richiesta
 * @return int Punteggio da attribuire
 */
function getRequestPoints($requestType) {
    $config = [
        'event' => 10,        // Punteggio per richieste di evento
        'service' => 10,      // Punteggio per richieste di servizio
        'promos' => 10,       // Punteggio per richieste di promozione
        'reservation' => 10   // Punteggio per richieste di prenotazione
    ];
    return $config[$requestType] ?? 10;
}

/**
 * Funzione per ottenere l'etichetta source per una tipologia di richiesta
 * @param string $requestType Tipologia di richiesta
 * @return string Etichetta source
 */
function getRequestSourceLabel($requestType) {
    $labels = [
        'event' => 'Event request Completed',
        'service' => 'Service request Completed',
        'promos' => 'Promotion request Completed',
        'reservation' => 'Reservation request Completed'
    ];
    return $labels[$requestType] ?? 'Request Completed';
}
?> 