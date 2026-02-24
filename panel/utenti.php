<?php
/**
 * Gestione Utenti
 * Assistente Farmacia Panel
 */

// Controlli di accesso
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth_middleware.php';

// Richiedi accesso admin
requireAdmin();

// Recupera lista utenti
try {
    $users = db_fetch_all("
        SELECT u.*, p.nice_name as pharmacy_name 
        FROM jta_users u 
        LEFT JOIN jta_pharmas p ON u.starred_pharma = p.id 
        WHERE u.role != 'admin' AND u.status != 'deleted'
        ORDER BY u.created_at DESC
    ");
} catch (Exception $e) {
    error_log("Errore recupero utenti: " . $e->getMessage());
    $users = [];
}

// Recupera lista farmacie per il dropdown
try {
    $pharmacies = db_fetch_all("SELECT id, nice_name FROM jta_pharmas WHERE status != 'deleted' ORDER BY nice_name");
} catch (Exception $e) {
    error_log("Errore recupero farmacie: " . $e->getMessage());
    $pharmacies = [];
}

// Imposta variabili per il template
$current_page = 'utenti';
$page_title = 'Gestione Utenti';

require_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require_once 'includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Gestione Utenti</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="fas fa-plus"></i> Nuovo Utente
                    </button>
                </div>
            </div>

            <!-- Filtri -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <label for="searchUser" class="form-label">Cerca utente</label>
                            <input type="text" class="form-control" id="searchUser" placeholder="Nome, cognome, email...">
                        </div>
                        <div class="col-md-3">
                            <label for="roleFilter" class="form-label">Ruolo</label>
                            <select class="form-select" id="roleFilter">
                                <option value="">Tutti i ruoli</option>
                                <option value="pharmacist">Farmacista</option>
                                <option value="user">Utente</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="pharmacyFilter" class="form-label">Farmacia</label>
                            <select class="form-select" id="pharmacyFilter">
                                <option value="">Tutte le farmacie</option>
                                <?php foreach ($pharmacies as $pharmacy): ?>
                                    <option value="<?= $pharmacy['id'] ?>"><?= htmlspecialchars($pharmacy['nice_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <button type="button" class="btn btn-outline-secondary d-block w-100" onclick="resetFilters()">
                                <i class="fas fa-undo"></i> Reset
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabella Utenti -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="usersTable">
                            <thead>
                                <tr>
                                    <th>Utente</th>
                                    <th>Email</th>
                                    <th>Telefono</th>
                                    <th>Ruolo</th>
                                    <th>Farmacia</th>
                                    <th>Stato</th>
                                    <th>Data Creazione</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr data-user-id="<?= $user['id'] ?>" 
                                        data-role="<?= $user['role'] ?>" 
                                        data-pharmacy="<?= $user['starred_pharma'] ?>" 
                                        data-status="<?= $user['status'] ?>">
                                        <td>
                                            <strong><?= h($user['name']) . ' ' . h($user['surname']) ?></strong>
                                            <br><small class="text-muted">@<?= h($user['slug_name']) ?></small>
                                        </td>
                                        <td><?= h($user['email']) ?></td>
                                        <td><?= htmlspecialchars($user['phone_number'] ?? 'Non specificato') ?></td>
                                        <td>
                                            <span class="badge bg-<?= $user['role'] === 'pharmacist' ? 'primary' : 'secondary' ?>">
                                                <?= $user['role'] === 'pharmacist' ? 'Farmacista' : 'Utente' ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($user['pharmacy_name'] ?? 'Non assegnata') ?></td>
                                        <td>
                                            <span class="badge bg-<?= $user['status'] === 'active' ? 'success' : 'warning' ?>">
                                                <?= $user['status'] === 'active' ? 'Attivo' : 'Inattivo' ?>
                                            </span>
                                        </td>
                                        <td><?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <?php if ($user['role'] === 'pharmacist'): ?>
                                                    <button class="btn btn-sm btn-outline-success" 
                                                            onclick="loginAsUser(<?= $user['id'] ?>)"
                                                            title="Accedi come Farmacia">
                                                        <i class="fas fa-sign-in-alt"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-outline-warning" 
                                                        onclick="editUser(<?= $user['id'] ?>)"
                                                        title="Modifica">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" 
                                                        onclick="deleteUser(<?= $user['id'] ?>, '<?= h($user['name']) . ' ' . h($user['surname']) ?>')"
                                                        title="Elimina">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Modal Aggiungi Utente -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nuovo Utente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addUserForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="firstName" class="form-label">Nome *</label>
                                <input type="text" class="form-control" id="firstName" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="lastName" class="form-label">Cognome *</label>
                                <input type="text" class="form-control" id="lastName" name="surname" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Username *</label>
                        <input type="text" class="form-control" id="username" name="slug_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email">
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone_number" class="form-label">Telefono</label>
                        <input type="tel" class="form-control" id="phone_number" name="phone_number">
                    </div>
                    
                    <div class="mb-3">
                        <label for="role" class="form-label">Ruolo *</label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="">Seleziona ruolo...</option>
                            <option value="pharmacist">Farmacista</option>
                            <option value="user">Utente</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="pharmacySelectGroup">
                        <label for="pharmacy" class="form-label">Farmacia (opzionale)</label>
                        <select class="form-select" id="pharmacy" name="starred_pharma">
                            <option value="">Seleziona farmacia...</option>
                            <?php foreach ($pharmacies as $pharmacy): ?>
                                <option value="<?= $pharmacy['id'] ?>"><?= htmlspecialchars($pharmacy['nice_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password *</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-success">Crea Utente</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Modifica Utente -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifica Utente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editUserForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="user_id" id="editUserId">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="editFirstName" class="form-label">Nome *</label>
                                <input type="text" class="form-control" id="editFirstName" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="editLastName" class="form-label">Cognome *</label>
                                <input type="text" class="form-control" id="editLastName" name="surname" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editUsername" class="form-label">Username *</label>
                        <input type="text" class="form-control" id="editUsername" name="slug_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editEmail" class="form-label">Email</label>
                        <input type="email" class="form-control" id="editEmail" name="email">
                    </div>
                    
                    <div class="mb-3">
                        <label for="editPhone" class="form-label">Telefono</label>
                        <input type="tel" class="form-control" id="editPhone" name="phone_number">
                    </div>
                    
                    <div class="mb-3">
                        <label for="editRole" class="form-label">Ruolo *</label>
                        <select class="form-select" id="editRole" name="role" required>
                            <option value="">Seleziona ruolo...</option>
                            <option value="pharmacist">Farmacista</option>
                            <option value="user">Utente</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="editPharmacySelectGroup">
                        <label for="editPharmacy" class="form-label">Farmacia (opzionale)</label>
                        <select class="form-select" id="editPharmacy" name="starred_pharma">
                            <option value="">Seleziona farmacia...</option>
                            <?php foreach ($pharmacies as $pharmacy): ?>
                                <option value="<?= $pharmacy['id'] ?>"><?= htmlspecialchars($pharmacy['nice_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editStatus" class="form-label">Stato *</label>
                        <select class="form-select" id="editStatus" name="status" required>
                            <option value="active">Attivo</option>
                            <option value="inactive">Inattivo</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editPassword" class="form-label">Password (lasciare vuoto per non modificare)</label>
                        <input type="password" class="form-control" id="editPassword" name="password">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-primary">Salva Modifiche</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
$additional_js = ['assets/js/utenti.js'];
require_once 'includes/footer.php'; 
?>
