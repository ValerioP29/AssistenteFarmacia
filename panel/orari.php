<?php
/**
 * Pagina Orari Farmacia
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

// Ottieni gli orari attuali
$working_hours = getPharmacyHours($pharmacy['id']);

// Imposta il titolo della pagina e la pagina corrente per il menu
$page_title = "Modifica Orari";
$current_page = 'orari';
$additional_css = ['assets/css/sidebars.css', 'assets/css/navbar.css', 'assets/css/dashboard.css', 'assets/css/orari.css'];
$additional_js = ['assets/js/sidebars.js', 'assets/js/navbar.js', 'assets/js/time.js', 'assets/js/orari.js'];

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
            <h1 class= "text-center">Modifica orari di apertura</h1>
            <p class="text-muted">Qui puoi modificare gli orari di apertura della tua farmacia.</p>

            <form id="orariForm" method="post">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                
                <div>
                    <div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Giorno</th>
                                        <th>Mattina</th>
                                        <th>Pomeriggio</th>
                                        <th>Chiuso</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $days = [
                                        'lun' => 'Lunedì',
                                        'mar' => 'Martedì',
                                        'mer' => 'Mercoledì',
                                        'gio' => 'Giovedì',
                                        'ven' => 'Venerdì',
                                        'sab' => 'Sabato',
                                        'dom' => 'Domenica'
                                    ];
                                    
                                    foreach ($days as $day_code => $day_name):
                                        $day_hours = $working_hours[$day_code] ?? [];
                                        $is_closed = isset($day_hours['closed']) && $day_hours['closed'];
                                    ?>
                                    <tr>
                                        <td><strong><?= $day_name ?></strong></td>
                                        <td>
                                            <div class="row g-2">
                                                <div class="col-6">
                                                    <input type="time" class="form-control" 
                                                           name="<?= $day_code ?>_mattina_apertura" 
                                                           value="<?= h($day_hours['morning_open'] ?? '08:00') ?>"
                                                           <?= $is_closed ? 'disabled' : '' ?>>
                                                </div>
                                                <div class="col-6">
                                                    <input type="time" class="form-control" 
                                                           name="<?= $day_code ?>_mattina_chiusura" 
                                                           value="<?= h($day_hours['morning_close'] ?? '12:00') ?>"
                                                           <?= $is_closed ? 'disabled' : '' ?>>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="row g-2">
                                                <div class="col-6">
                                                    <input type="time" class="form-control" 
                                                           name="<?= $day_code ?>_pomeriggio_apertura" 
                                                           value="<?= h($day_hours['afternoon_open'] ?? '15:00') ?>"
                                                           <?= $is_closed ? 'disabled' : '' ?>>
                                                </div>
                                                <div class="col-6">
                                                    <input type="time" class="form-control" 
                                                           name="<?= $day_code ?>_pomeriggio_chiusura" 
                                                           value="<?= h($day_hours['afternoon_close'] ?? '19:00') ?>"
                                                           <?= $is_closed ? 'disabled' : '' ?>>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div class="form-check">
                                                <input class="form-check-input day-closed-checkbox" type="checkbox" 
                                                       name="<?= $day_code ?>_chiuso" value="1" 
                                                       <?= $is_closed ? 'checked' : '' ?>
                                                       data-day="<?= $day_code ?>">
                                                <label class="form-check-label" for="<?= $day_code ?>_chiuso">
                                                    Chiuso
                                                </label>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Salva Orari
                    </button>
                    <button type="button" class="btn btn-secondary ms-2" onclick="resetForm()">
                        <i class="fas fa-undo me-2"></i>Ripristina
                    </button>
                </div>
            </form>

            <hr class="my-4">

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Giorno di Turno</h5>
                </div>
                <div class="card-body">
                    <form id="turnoForm">
                        <div class="row">
                            <div class="col-md-6">
                                <label for="giorno_turno" class="form-label">Scegli il giorno di turno:</label>
                                <select id="giorno_turno" name="giorno_turno" class="form-select">
                                    <option value="">Nessuno</option>
                                    <option value="lun" <?= ($pharmacy['turno_giorno'] ?? '') === 'lun' ? 'selected' : '' ?>>Lunedì</option>
                                    <option value="mar" <?= ($pharmacy['turno_giorno'] ?? '') === 'mar' ? 'selected' : '' ?>>Martedì</option>
                                    <option value="mer" <?= ($pharmacy['turno_giorno'] ?? '') === 'mer' ? 'selected' : '' ?>>Mercoledì</option>
                                    <option value="gio" <?= ($pharmacy['turno_giorno'] ?? '') === 'gio' ? 'selected' : '' ?>>Giovedì</option>
                                    <option value="ven" <?= ($pharmacy['turno_giorno'] ?? '') === 'ven' ? 'selected' : '' ?>>Venerdì</option>
                                    <option value="sab" <?= ($pharmacy['turno_giorno'] ?? '') === 'sab' ? 'selected' : '' ?>>Sabato</option>
                                    <option value="dom" <?= ($pharmacy['turno_giorno'] ?? '') === 'dom' ? 'selected' : '' ?>>Domenica</option>
                                </select>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-clock me-2"></i>Salva Turno
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
      </main>
    </div>
</div>

<?php
// Include il footer
include 'includes/footer.php';
?> 