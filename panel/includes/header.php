<?php
/**
 * Header comune per tutte le pagine
 * Assistente Farmacia Panel
 */

// Avvia sessione solo se non è già attiva
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Carica configurazione
require_once __DIR__ . '/../config/database.php';

// Funzioni di utilità
require_once __DIR__ . '/../includes/functions.php';

// Middleware di autenticazione
require_once __DIR__ . '/../includes/auth_middleware.php';

// NOTA: Il controllo di autenticazione deve essere fatto PRIMA di includere questo file
// nei file che richiedono autenticazione, utilizzare:
// requireAdmin(); // Solo admin
// requirePharmacistOrAdmin(); // Admin o farmacista  
// requireLogin(); // Qualsiasi utente autenticato

// Imposta variabili di default
$page_title = $page_title ?? APP_NAME;
$current_page = $current_page ?? 'dashboard';

// Ora possiamo iniziare l'output HTML
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= $page_description ?? 'Pannello di amministrazione per farmacie' ?>">
    <meta name="author" content="Assistente Farmacia">
    
    <title><?= htmlspecialchars($page_title) ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="https://web.assistentefarmacia.it/assets/favicon/favicon-96x96.png" sizes="96x96">
    <link rel="icon" type="image/svg+xml" href="https://web.assistentefarmacia.it/assets/favicon/favicon.svg">
    <link rel="shortcut icon" href="https://web.assistentefarmacia.it/assets/favicon/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="https://web.assistentefarmacia.it/assets/favicon/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-title" content="Assistente">
    <link rel="manifest" href="https://web.assistentefarmacia.it/assets/favicon/site.webmanifest">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <!-- Fallback Font Awesome -->
    <script>
        // Fallback per Font Awesome se il CDN non funziona
        window.addEventListener('load', function() {
            setTimeout(function() {
                const icons = document.querySelectorAll('.fas, .far, .fab');
                if (icons.length > 0 && getComputedStyle(icons[0]).fontFamily.indexOf('Font Awesome') === -1) {
                    // Se Font Awesome non è caricato, usa un CDN alternativo
                    const link = document.createElement('link');
                    link.rel = 'stylesheet';
                    link.href = 'https://use.fontawesome.com/releases/v6.5.1/css/all.css';
                    document.head.appendChild(link);
                }
            }, 2000);
        });
    </script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/sidebars.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/navbar.css">
    <link rel="stylesheet" href="assets/css/notifications.css">
    

    <?php if (isset($additional_css)): ?>
        <?php foreach ($additional_css as $css): ?>
            <link rel="stylesheet" href="<?= $css ?>">
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Meta tags per sicurezza -->
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">
    
    <!-- CSRF Token -->
    <meta name="csrf-token" content="<?= generateCSRFToken() ?>">
</head>
<body class="<?= $body_class ?? '' ?>">
    <!-- Template Icons (nascosto) -->
    <div class="tmpl-icons hidden">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-list d-none" viewBox="0 0 16 16">
            <symbol id="burgher" viewBox="0 0 16 16"> 
                <path fill-rule="evenodd" d="M2.5 12a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5"/>
            </symbol>
        </svg>

        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-stack d-none" viewBox="0 0 16 16">
            <symbol id="stack" viewBox="0 0 16 16">
                <path d="M6 4.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0m-1 0a.5.5 0 1 0-1 0 .5.5 0 0 0 1 0" />
                <path d="M2 1h4.586a1 1 0 0 1 .707.293l7 7a1 1 0 0 1 0 1.414l-4.586 4.586a1 1 0 0 1-1.414 0l-7-7A1 1 0 0 1 1 6.586V2a1 1 0 0 1 1-1m0 5.586 7 7L13.586 9l-7-7H2z" />
            </symbol>
        </svg>

        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-receipt d-none" viewBox="0 0 16 16">
            <symbol id="receipt" viewBox="0 0 16 16"> 
                <path d="M1.92.506a.5.5 0 0 1 .434.14L3 1.293l.646-.647a.5.5 0 0 1 .708 0L5 1.293l.646-.647a.5.5 0 0 1 .708 0L7 1.293l.646-.647a.5.5 0 0 1 .708 0L9 1.293l.646-.647a.5.5 0 0 1 .708 0l.646.647.646-.647a.5.5 0 0 1 .708 0l.646.647.646-.647a.5.5 0 0 1 .801.13l.5 1A.5.5 0 0 1 15 2v12a.5.5 0 0 1-.053.224l-.5 1a.5.5 0 0 1-.8.13L13 14.707l-.646.647a.5.5 0 0 1-.708 0L11 14.707l-.646.647a.5.5 0 0 1-.708 0L9 14.707l-.646.647a.5.5 0 0 1-.708 0L7 14.707l-.646.647a.5.5 0 0 1-.708 0L5 14.707l-.646.647a.5.5 0 0 1-.801-.13l-.5-1A.5.5 0 0 1 1 14V2a.5.5 0 0 1 .053-.224l.5-1a.5.5 0 0 1 .367-.27m.217 1.338L2 2.118v11.764l.137.274.51-.51a.5.5 0 0 1 .707 0l.646.647.646-.646a.5.5 0 0 1 .708 0l.646.646.646-.646a.5.5 0 0 1 .708 0l.646.646.646-.646a.5.5 0 0 1 .708 0l.509.509.137-.274V2.118l-.137-.274-.51.51a.5.5 0 0 1-.707 0L12 1.707l-.646.647a.5.5 0 0 1-.708 0L10 1.707l-.646.647a.5.5 0 0 1-.708 0L8 1.707l-.646.647a.5.5 0 0 1-.708 0L6 1.707l-.646.647a.5.5 0 0 1-.708 0L4 1.707l-.646.647a.5.5 0 0 1-.708 0z"/>
                <path d="M3 4.5a.5.5 0 0 1 .5-.5h6a.5.5 0 1 1 0 1h-6a.5.5 0 0 1-.5-.5m0 2a.5.5 0 0 1 .5-.5h6a.5.5 0 1 1 0 1h-6a.5.5 0 0 1-.5-.5m0 2a.5.5 0 0 1 .5-.5h6a.5.5 0 1 1 0 1h-6a.5.5 0 0 1-.5-.5m0 2a.5.5 0 0 1 .5-.5h6a.5.5 0 0 1 0 1h-6a.5.5 0 0 1-.5-.5m8-6a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 0 1h-1a.5.5 0 0 1-.5-.5m0 2a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 0 1h-1a.5.5 0 0 1-.5-.5m0 2a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 0 1h-1a.5.5 0 0 1-.5-.5m0 2a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 0 1h-1a.5.5 0 0 1-.5-.5"/>
            </symbol>
        </svg>

        <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="bi bi-people d-none" viewBox="0 0 16 16">
            <symbol id="people" viewBox="0 0 16 16">
                <path d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1zm-7.978-1L7 12.996c.001-.264.167-1.03.76-1.72C8.312 10.629 9.282 10 11 10c1.717 0 2.687.63 3.24 1.276.593.69.758 1.457.76 1.72l-.008.002-.014.002zM11 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4m3-2a3 3 0 1 1-6 0 3 3 0 0 1 6 0M6.936 9.28a6 6 0 0 0-1.23-.247A7 7 0 0 0 5 9c-4 0-5 3-5 4q0 1 1 1h4.216A2.24 2.24 0 0 1 5 13c0-1.01.377-2.042 1.09-2.904.243-.294.526-.569.846-.816M4.92 10A5.5 5.5 0 0 0 4 13H1c0-.26.164-1.03.76-1.724.545-.636 1.492-1.256 3.16-1.275ZM1.5 5.5a3 3 0 1 1 6 0 3 3 0 0 1-6 0m3-2a2 2 0 1 0 0 4 2 2 0 0 0 0-4" />
            </symbol>
        </svg>

        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-list d-none" viewBox="0 0 16 16">
            <symbol id="list" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M2.5 12a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5" />
            </symbol>
        </svg>

        <svg xmlns="" fill="currentColor" class="bi bi-house d-none" viewBox="0 0 16 16">
            <symbol id="house" viewBox="0 0 16 16">
                <path d="M8.707 1.5a1 1 0 0 0-1.414 0L.646 8.146a.5.5 0 0 0 .708.708L2 8.207V13.5A1.5 1.5 0 0 0 3.5 15h9a1.5 1.5 0 0 0 1.5-1.5V8.207l.646.647a.5.5 0 0 0 .708-.708L13 5.793V2.5a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1.293zM13 7.207V13.5a.5.5 0 0 1-.5.5h-9a.5.5 0 0 1-.5-.5V7.207l5-5z" />
            </symbol>
        </svg>

        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-gear d-none" viewBox="0 0 16 16">
            <symbol id="setting" viewBox="0 0 16 16">
                <path d="M11 5a3 3 0 1 1-6 0 3 3 0 0 1 6 0M8 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4m.256 7a4.5 4.5 0 0 1-.229-1.004H3c.001-.246.154-.986.832-1.664C4.484 10.68 5.711 10 8 10q.39 0 .74.025c.226-.341.496-.65.804-.918Q8.844 9.002 8 9c-5 0-6 3-6 4s1 1 1 1zm3.63-4.54c.18-.613 1.048-.613 1.229 0l.043.148a.64.64 0 0 0 .921.382l.136-.074c.561-.306 1.175.308.87.869l-.075.136a.64.64 0 0 0 .382.92l.149.045c.612.18.612 1.048 0 1.229l-.15.043a.64.64 0 0 0-.38.921l.074.136c.305.561-.309 1.175-.87.87l-.136-.075a.64.64 0 0 0-.92.382l-.045.149c-.18.612-1.048.612-1.229 0l-.043-.15a.64.64 0 0 0-.921-.38l-.136.074c-.561.305-1.175-.309-.87-.87l.075-.136a.64.64 0 0 0-.382-.92l-.148-.045c-.613-.18-.613-1.048 0-1.229l.148-.043a.64.64 0 0 0 .382-.921l-.074-.136c-.306-.561.308-1.175.869-.87l.136.075a.64.64 0 0 0 .92-.382zM14 12.5a1.5 1.5 0 1 0-3 0 1.5 1.5 0 0 0 3 0" />
            </symbol>
        </svg>

        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-clock-history d-none" viewBox="0 0 16 16">
            <symbol id="clock" viewBox="0 0 16 16">
                <path d="M8.515 1.019A7 7 0 0 0 8 1V0a8 8 0 0 1 .589.022zm2.004.45a7 7 0 0 0-.985-.299l.219-.976q.576.129 1.126.342zm1.37.71a7 7 0 0 0-.439-.27l.493-.87a8 8 0 0 1 .979.654l-.615.789a7 7 0 0 0-.418-.302zm1.834 1.79a7 7 0 0 0-.653-.796l.724-.69q.406.429.747.91zm.744 1.352a7 7 0 0 0-.214-.468l.893-.45a8 8 0 0 1 .45 1.088l-.95.313a7 7 0 0 0-.179-.483m.53 2.507a7 7 0 0 0-.1-1.025l.985-.17q.1.58.116 1.17zm-.131 1.538q.05-.254.081-.51l.993.123a8 8 0 0 1-.23 1.155l-.964-.267q.069-.247.12-.501m-.952 2.379q.276-.436.486-.908l.914.405q-.24.54-.555 1.038zm-.964 1.205q.183-.183.35-.378l.758.653a8 8 0 0 1-.401.432z" />
                <path d="M8 1a7 7 0 1 0 4.95 11.95l.707.707A8.001 8.001 0 1 1 8 0z" />
                <path d="M7.5 3a.5.5 0 0 1 .5.5v5.21l3.248 1.856a.5.5 0 0 1-.496.868l-3.5-2A.5.5 0 0 1 7 9V3.5a.5.5 0 0 1 .5-.5" />
            </symbol>
        </svg>

        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-house-dash d-none" viewBox="0 0 16 16">
            <symbol id="exit" viewBox="0 0 16 16">
                <path d="M12.5 16a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7M11 12h3a.5.5 0 0 1 0 1h-3a.5.5 0 1 1 0-1" />
                <path d="M7.293 1.5a1 1 0 0 1 1.414 0L11 3.793V2.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v3.293l2.354 2.353a.5.5 0 0 1-.708.708L8 2.207l-5 5V13.5a.5.5 0 0 0 .5.5h4a.5.5 0 0 1 0 1h-4A1.5 1.5 0 0 1 2 13.5V8.207l-.646.647a.5.5 0 1 1-.708-.708z" />
            </symbol>
        </svg>

        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-calendar-check d-none" viewBox="0 0 16 16">
            <symbol id="calendar" viewBox="0 0 16 16">
                <path d="M10.854 7.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 9.793l2.646-2.647a.5.5 0 0 1 .708 0"/>
                <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5M1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4z"/>
            </symbol>  
        </svg>

        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="fa fa-map-pointer d-none" viewBox="0 0 384 512">
            <symbol id="map-pointer" viewBox="0 0 384 512">
                <path d="M215.7 499.2C267 435 384 279.4 384 192C384 86 298 0 192 0S0 86 0 192c0 87.4 117 243 168.3 307.2c12.3 15.3 35.1 15.3 47.4 0zM192 128a64 64 0 1 1 0 128 64 64 0 1 1 0-128z"/>
            </symbol>  
        </svg>
    </div>

    <!-- Alert per messaggi di sistema -->
    <?php if (isset($_SESSION['alert'])): ?>
        <div class="alert alert-<?= $_SESSION['alert']['type'] ?> alert-dismissible fade show system-alert" role="alert">
            <div class="alert-body">
                <?= htmlspecialchars($_SESSION['alert']['message']) ?>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['alert']); ?>
    <?php endif; ?> 

        <!-- Navbar -->
        <nav class="navbar navbar-expand-lg">
            <div class="container-fluid">
                <!-- Pulsante hamburger per mobile -->
                <button class="navbar-toggler d-lg-none" 
                        type="button"
                        data-bs-toggle="offcanvas"
                        data-bs-target="#sidebarOffcanvas"
                        aria-controls="sidebarOffcanvas"
                        aria-expanded="false"
                        aria-label="Toggle navigation"
                        style="border: none; background: transparent; color: white;">
                    <i class="fas fa-bars" style="color: white !important; font-size: 1.5rem;"></i>
                </button>
                
                <div class="navbar-center mx-auto text-light text-center">
                    <?php if (isset($_SESSION['login_as']) && $_SESSION['login_as']): ?>
                        <div class="d-flex align-items-center">
                            <span class="me-2">👤</span>
                            <span>Accesso come: <?= htmlspecialchars($_SESSION['user_name']) ?></span>
                            <button class="btn btn-sm btn-outline-light ms-3" onclick="returnToAdmin()">
                                <i class="fas fa-arrow-left me-1"></i>Torna Admin
                            </button>
                        </div>
                    <?php else: ?>
                        <?= $page_title ?>
                    <?php endif; ?>
                </div>
                
                <div class="d-none d-lg-block ms-auto">
                    <!-- Pulsante logout rimosso - ora solo nel sidebar -->
                </div>
            </div>
        </nav>

        <!-- Offcanvas per mobile -->
        <div class="offcanvas offcanvas-start" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarOffcanvasLabel">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title" id="sidebarOffcanvasLabel">Menu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body">
                <div class="sidebar-mobile">
                    <ul class="nav nav-pills flex-column mb-auto">
                        <?php if ($_SESSION['user_role'] === 'admin'): ?>
                            <!-- Menu Admin -->
                            <li>
                                <a href="dashboard.php" class="nav-link <?= $current_page === 'dashboard' ? 'selected' : '' ?>">
                                    <i class="fas fa-tachometer-alt me-2"></i>
                                    Dashboard
                                </a>
                            </li>
                            <li>
                                <a href="utenti.php" class="nav-link <?= $current_page === 'utenti' ? 'selected' : '' ?>">
                                    <i class="fas fa-users me-2"></i>
                                    Gestione Utenti
                                </a>
                            </li>
                            <li>
                                <a href="farmacie.php" class="nav-link <?= $current_page === 'farmacie' ? 'selected' : '' ?>">
                                    <i class="fas fa-clinic-medical me-2"></i>
                                    Gestione Farmacie
                                </a>
                            </li>
                            <li>
                                <a href="prodotti_globali.php" class="nav-link <?= $current_page === 'prodotti_globali' ? 'selected' : '' ?>">
                                    <i class="fas fa-boxes me-2"></i>
                                    Gestione Prodotti Globali
                                </a>
                            </li>
                        <?php elseif ($_SESSION['user_role'] === 'pharmacist'): ?>
                            <!-- Menu Pharmacist -->
                            <li>
                                <a href="dashboard.php" class="nav-link <?= $current_page === 'dashboard' ? 'selected' : '' ?>">
                                    <i class="fas fa-tachometer-alt me-2"></i>
                                    Dashboard
                                </a>
                            </li>
                            <li>
                                <a href="richieste.php" class="nav-link <?= $current_page === 'richieste' ? 'selected' : '' ?>">
                                    <i class="fas fa-calendar-check me-2"></i>
                                    Richieste
                                </a>
                            </li>
                            <li>
                                <a href="prodotti.php" class="nav-link <?= $current_page === 'prodotti' ? 'selected' : '' ?>">
                                    <i class="fas fa-boxes me-2"></i>
                                    Gestione Prodotti
                                </a>
                            </li>
                            <li>
                                <a href="promozioni.php" class="nav-link <?= $current_page === 'promozioni' ? 'selected' : '' ?>">
                                    <i class="fas fa-tags me-2"></i>
                                    Gestione Promozioni
                                </a>
                            </li>
                            <li>
                                <a href="orari.php" class="nav-link <?= $current_page === 'orari' ? 'selected' : '' ?>">
                                    <i class="fas fa-clock me-2"></i>
                                    Modifica Orari
                                </a>
                            </li>
                            <li>
                                <a href="whatsapp.php" class="nav-link <?= $current_page === 'whatsapp' ? 'selected' : '' ?>">
                                    <i class="fab fa-whatsapp me-2"></i>
                                    WhatsApp
                                </a>
                            </li>
                            <li>
                                <a href="profilo.php" class="nav-link <?= $current_page === 'profilo' ? 'selected' : '' ?>">
                                    <i class="fas fa-user-cog me-2"></i>
                                    Profilo
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <li><hr class="border-light"></li>
                        
                        <?php if (isset($_SESSION['login_as']) && $_SESSION['login_as']): ?>
                            <li>
                                <a href="#" class="nav-link return-admin-link" onclick="returnToAdmin()">
                                    <i class="fas fa-arrow-left me-2"></i> Torna Admin
                                </a>
                            </li>
                            <li><hr class="border-light"></li>
                        <?php endif; ?>
                        
                        <li>
                            <a href="logout.php" class="nav-link exit logout-link">
                                <i class="fas fa-sign-out-alt me-2"></i> Esci
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

    <!-- Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>