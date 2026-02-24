<?php
/**
 * Pagina Profilo Farmacia
 * Assistente Farmacia Panel
 */

// Carica le configurazioni
require_once 'config/database.php';
require_once 'includes/functions.php';

// Avvia la sessione
session_start();

// Controlla se l'utente è autenticato
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Controlla se è un farmacista
if (!isPharmacist()) {
    header('Location: dashboard.php');
    exit;
}

// Ottieni i dati della farmacia corrente
$pharmacy = getCurrentPharmacy();
if (!$pharmacy) {
    setAlert('Errore nel caricamento dei dati della farmacia', 'danger');
    header('Location: dashboard.php');
    exit;
}

// Imposta il titolo della pagina e la pagina corrente per il menu
$page_title = "Modifica Profilo";
$current_page = 'profilo';
$additional_css = ['assets/css/sidebars.css', 'assets/css/navbar.css', 'assets/css/dashboard.css', 'assets/css/maps.css'];
$additional_js = ['assets/js/sidebars.js', 'assets/js/navbar.js', 'assets/js/profile.js'];

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
            <h1>Modifica profilo</h1>

            <form class="form" method="post" enctype="multipart/form-data" id="profileForm">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="form-label" for="email">Email:</label>
                            <input class="form-control form_element" type="email" id="email" name="email" 
                                   value="<?= h($pharmacy['email']) ?>" required>
                        </div>
                    
                        <div class="form-group mb-3">
                            <label class="form-label" for="phone_number">Telefono:</label>
                            <input class="form-control form_element" type="tel" id="phone_number" name="phone_number" 
                                   value="<?= h($pharmacy['phone_number']) ?>">
                        </div>

                        <div class="form-group mb-3">
                            <label class="form-label" for="business_name">Ragione sociale:</label>
                            <input class="form-control form_element" type="text" id="business_name" name="business_name" 
                                   value="<?= h($pharmacy['business_name']) ?>" required>
                        </div>

                        <div class="form-group mb-3">
                            <label class="form-label" for="nice_name">Nome farmacia:</label>
                            <input class="form-control form_element" type="text" id="nice_name" name="nice_name" 
                                   value="<?= h($pharmacy['nice_name']) ?>" required>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="form-label" for="city">Città:</label>
                            <input class="form-control form_element" type="text" id="city" name="city" 
                                   value="<?= h($pharmacy['city']) ?>" required>
                        </div>

                        <div class="form-group mb-3">
                            <label class="form-label" for="address">Indirizzo:</label>
                            <input class="form-control form_element" type="text" id="address" name="address" 
                                   value="<?= h($pharmacy['address']) ?>" required>
                        </div>

                        <div class="form-group my-3">
                            <label for="latlng">Posizione:</label>
                            <div>
                                <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#mapModal">
                                    <i class="fas fa-map-marker-alt me-2"></i>
                                    Posizione Farmacia
                                </button>
                            </div>
                            <input type="hidden" id="latlng" name="latlng" value="<?= h($pharmacy['latlng']) ?>">
                        </div>

                        <div class="form-group mb-3">
                            <label class="form-label" for="description">Descrizione:</label>
                            <textarea class="form-control form_element" maxlength="500" rows="5" id="description" 
                                      name="description"><?= h($pharmacy['description']) ?></textarea>
                            <small class="form-text text-muted">Massimo 500 caratteri</small>
                        </div>
                    </div>
                </div>

                <!-- Sezione Logo -->
                <div class="row mt-5">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-image me-2"></i>Logo Farmacia
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-3 text-center">
                                        <div class="logo-preview mb-3">
                                            <?php if (!empty($pharmacy['logo'])): ?>
                                                <img src="<?= h($pharmacy['logo']) ?>" alt="Logo Farmacia" 
                                                     class="img-fluid rounded" style="max-height: 150px; max-width: 200px;">
                                            <?php else: ?>
                                                <div class="placeholder-logo bg-light border rounded d-flex align-items-center justify-content-center" 
                                                     style="height: 150px; width: 200px;">
                                                    <i class="fas fa-image fa-3x text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-9">
                                        <div class="form-group">
                                            <label class="form-label" for="logo">Carica nuovo logo:</label>
                                            <input class="form-control" type="file" id="logo" name="logo" 
                                                   accept="image/jpeg,image/jpg,image/png,image/gif">
                                            <small class="form-text text-muted">
                                                Formati supportati: JPG, PNG, GIF. Dimensione massima: 5MB
                                            </small>
                                        </div>
                                        <button type="button" class="btn btn-primary mt-2" id="uploadLogoBtn">
                                            <i class="fas fa-upload me-2"></i>Carica Logo
                                        </button>
                                        <?php if (!empty($pharmacy['logo'])): ?>
                                            <button type="button" class="btn btn-danger mt-2" id="removeLogoBtn">
                                                <i class="fas fa-trash me-2"></i>Rimuovi Logo
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <button type="submit" class="btn btn-success mt-3 px-4">
                            <i class="fas fa-save me-2"></i>Salva Modifiche
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODALE MAPPA -->
<div class="modal fade" id="mapModal" tabindex="-1" aria-labelledby="mapModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="mapModalLabel">Posizione - Farmacia</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
            </div>
            <div class="modal-body p-0">
                <!-- Google Maps embedded -->
                <iframe src="https://www.google.com/maps?q=<?= urlencode($pharmacy['address'] . ', ' . $pharmacy['city']) ?>&output=embed" 
                        width="100%" height="450" style="border:0;" allowfullscreen="" loading="lazy" 
                        referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
        </div>
    </div>
</div>

<?php
// Include il footer
include 'includes/footer.php';
?> 