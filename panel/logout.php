<?php
/**
 * Logout diretto
 * Assistente Farmacia Panel
 */

// Carica configurazione
require_once 'config/database.php';
require_once 'includes/functions.php';

// Avvia sessione se non già avviata
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Log attività
    if (isLoggedIn()) {
        logActivity('logout', ['user_id' => $_SESSION['user_id']]);
    }
    
    // Distruggi sessione
    session_destroy();
    
    // Rimuovi tutti i cookie di sessione
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    // Reindirizza al login con messaggio
    redirect('login.php', 'Logout effettuato con successo', 'success');
    
} catch (Exception $e) {
    error_log("Errore durante il logout: " . $e->getMessage());
    // Anche in caso di errore, reindirizza al login
    redirect('login.php', 'Logout effettuato', 'info');
}
?> 