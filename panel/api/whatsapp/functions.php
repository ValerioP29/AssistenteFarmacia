<?php
/**
 * Funzioni WhatsApp
 * Assistente Farmacia Panel
 */

// Carica le configurazioni
require_once __DIR__ . '/../../config/database.php';

/**
 * Ottiene l'URL base del servizio WhatsApp dalla configurazione
 */
function whatsapp_base_url() {
    return defined('WHATSAPP_BASE_URL') ? WHATSAPP_BASE_URL : 'https://waservice-pharma1.jungleteam.it';
}

/**
 * Ottiene l'URL del servizio WhatsApp per le operazioni principali
 */
function whatsapp_service_url() {
    return whatsapp_base_url();
}

/**
 * Ottiene l'URL per il QR code dalla configurazione
 */
function whatsapp_qr_url() {
    return whatsapp_base_url() . '/qr';
}

/**
 * Controlla se WhatsApp è connesso
 */
function whatsapp_is_connected() {
    $ch = curl_init(whatsapp_service_url() . '/status');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_POST, FALSE);
    
    // Disabilita verifica SSL per localhost
    if ($_SERVER['REMOTE_ADDR'] === '127.0.0.1' || $_SERVER['REMOTE_ADDR'] === '::1') {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return [
            'success' => FALSE,
            'message' => $error,
        ];
    }

    if ($response) {
        $response = json_decode($response);

        if (isset($response->success) && $response->success == TRUE) {
            return [
                'success' => TRUE,
                'message' => 'Connesso',
                'data' => $response,
            ];
        }
        return [
            'success' => FALSE,
            'message' => 'Non connesso',
        ];
    }

    return [
        'success' => FALSE,
        'message' => 'Errore imprevisto.',
    ];
}

/**
 * Disconnette WhatsApp
 */
function whatsapp_disconnect() {
    $ch = curl_init(whatsapp_service_url() . '/disconnect');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_POST, TRUE);
    
    // Disabilita verifica SSL per localhost
    if ($_SERVER['REMOTE_ADDR'] === '127.0.0.1' || $_SERVER['REMOTE_ADDR'] === '::1') {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return [
            'success' => FALSE,
            'message' => $error,
        ];
    }

    if ($response) {
        $response = json_decode($response);

        if ($response->success == TRUE) {
            return [
                'success' => TRUE,
                'message' => 'Disconnesso',
                'data' => $response,
            ];
        }
    }

    return [
        'success' => FALSE,
        'message' => 'Errore imprevisto.',
    ];
}

/**
 * Ottiene il QR code per la connessione WhatsApp
 */
function whatsapp_get_qr() {
    $ch = curl_init(whatsapp_qr_url());
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_POST, FALSE);
    
    // Disabilita verifica SSL per localhost
    if ($_SERVER['REMOTE_ADDR'] === '127.0.0.1' || $_SERVER['REMOTE_ADDR'] === '::1') {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return [
            'success' => FALSE,
            'message' => $error,
        ];
    }

    if ($response) {
        $response = json_decode($response);

        if ($response->success == TRUE) {
            return $response;
        }
    }

    return [
        'success' => FALSE,
        'message' => 'Errore imprevisto.',
    ];
} 