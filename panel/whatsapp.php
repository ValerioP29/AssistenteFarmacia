<?php
/**
 * Pagina WhatsApp
 * Assistente Farmacia Panel
 */

// Carica le configurazioni
require_once 'config/database.php';

// Avvia la sessione
session_start();

// Controlla se l'utente è autenticato
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Ottieni i dati dell'utente
$user_id = $_SESSION['user_id'];

// Imposta il titolo della pagina e la pagina corrente per il menu
$page_title = "WhatsApp";
$current_page = 'whatsapp';
$additional_css = ['assets/css/sidebars.css', 'assets/css/navbar.css', 'assets/css/dashboard.css'];
$additional_js = ['assets/js/core/pharma_wa.js'];


// Include l'header
include 'includes/header.php';
?>

<!-- Layout generale -->
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar desktop -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="whatsapp-container">
                <h1 class="whatsapp-title">WhatsApp QR Code</h1>
                
                <div class="qr-container">
                    <div class="wa-image"></div>
                    <div class="wa-status"></div>
                </div>
                
                <div class="wa-action"></div>
            </div>
        </div>
      </main>
    </div>
</div>

<?php
// Include il footer
include 'includes/footer.php';
?> 