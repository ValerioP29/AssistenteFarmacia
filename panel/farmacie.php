<?php
/**
 * Gestione Farmacie
 * Assistente Farmacia Panel
 */

// Controlli di accesso
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth_middleware.php';

// Richiedi accesso admin
requireAdmin();

// Recupera lista farmacie
try {
    $pharmacies = db_fetch_all("
        SELECT * FROM jta_pharmas 
        WHERE status != 'deleted' 
        ORDER BY nice_name ASC
    ");
} catch (Exception $e) {
    error_log("Errore recupero farmacie: " . $e->getMessage());
    $pharmacies = [];
}

// Imposta variabili per il template
$current_page = 'farmacie';
$page_title = 'Gestione Farmacie';

require_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require_once 'includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Gestione Farmacie</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addPharmacyModal">
                        <i class="fas fa-plus"></i> Nuova Farmacia
                    </button>
                </div>
            </div>

            <!-- Filtri -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <label for="searchPharmacy" class="form-label">Cerca farmacia</label>
                            <input type="text" class="form-control" id="searchPharmacy" placeholder="Nome, indirizzo, telefono...">
                        </div>
                        <div class="col-md-3">
                            <label for="statusFilter" class="form-label">Stato</label>
                            <select class="form-select" id="statusFilter">
                                <option value="">Tutti gli stati</option>
                                <option value="active">Attive</option>
                                <option value="inactive">Inattive</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <button type="button" class="btn btn-outline-secondary d-block w-100" onclick="resetFilters()">
                                <i class="fas fa-undo"></i> Reset Filtri
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabella Farmacie -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="pharmaciesTable">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>URL</th>
                                    <th>Indirizzo</th>
                                    <th>Telefono</th>
                                    <th>Email</th>
                                    <th>Stato</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pharmacies as $pharmacy): ?>
                                    <tr data-pharmacy-id="<?= $pharmacy['id'] ?>" data-status="<?= $pharmacy['status'] ?>">
                                        <td>
                                            <strong><?= htmlspecialchars($pharmacy['nice_name'] ?? '') ?></strong>
                                            <?php if ($pharmacy['business_name']): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($pharmacy['business_name']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($pharmacy['slug_url']): ?>
                                                <code class="text-primary"><?= htmlspecialchars($pharmacy['slug_url']) ?></code>
                                            <?php else: ?>
                                                <span class="text-muted">Non impostato</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($pharmacy['address'] ?? 'Non specificato') ?></td>
                                        <td><?= htmlspecialchars($pharmacy['phone_number'] ?? 'Non specificato') ?></td>
                                        <td><?= htmlspecialchars($pharmacy['email'] ?? 'Non specificato') ?></td>
                                        <td>
                                            <span class="badge bg-<?= $pharmacy['status'] === 'active' ? 'success' : 'secondary' ?>">
                                                <?= $pharmacy['status'] === 'active' ? 'Attiva' : 'Inattiva' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-warning" 
                                                        onclick="editPharmacy(<?= $pharmacy['id'] ?>)"
                                                        title="Modifica">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" 
                                                        onclick="deletePharmacy(<?= $pharmacy['id'] ?>, '<?= htmlspecialchars($pharmacy['nice_name']) ?>')"
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

<!-- Modal Aggiungi Farmacia -->
<div class="modal fade" id="addPharmacyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nuova Farmacia</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addPharmacyForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="pharmacyName" class="form-label">Nome Farmacia *</label>
                                <input type="text" class="form-control" id="pharmacyName" name="nice_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="pharmacyBusinessName" class="form-label">Ragione Sociale</label>
                                <input type="text" class="form-control" id="pharmacyBusinessName" name="business_name">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="pharmacySlugUrl" class="form-label">URL Personalizzato</label>
                        <input type="text" class="form-control" id="pharmacySlugUrl" name="slug_url" placeholder="es: farmacia-rossi-milano">
                        <div class="form-text">Lascia vuoto per generare automaticamente dall'URL del nome farmacia</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="pharmacyAddress" class="form-label">Indirizzo</label>
                        <input type="text" class="form-control" id="pharmacyAddress" name="address">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="pharmacyCity" class="form-label">Città</label>
                                <input type="text" class="form-control" id="pharmacyCity" name="city">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="pharmacyPhone" class="form-label">Telefono</label>
                                <input type="tel" class="form-control" id="pharmacyPhone" name="phone_number">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="pharmacyEmail" class="form-label">Email</label>
                                <input type="email" class="form-control" id="pharmacyEmail" name="email">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="pharmacyStatus" class="form-label">Stato *</label>
                        <select class="form-select" id="pharmacyStatus" name="status" required>
                            <option value="active">Attiva</option>
                            <option value="inactive">Inattiva</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="pharmacyLatLng" class="form-label">Coordinate GPS (lat,lng)</label>
                        <input type="text" class="form-control" id="pharmacyLatLng" name="latlng" placeholder="45.4642,9.1900">
                    </div>
                    
                    <div class="mb-3">
                        <label for="pharmacyDescription" class="form-label">Descrizione</label>
                        <textarea class="form-control" id="pharmacyDescription" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="pharmacyWorkingInfo" class="form-label">Orari di Lavoro</label>
                        <textarea class="form-control" id="pharmacyWorkingInfo" name="working_info" rows="4" placeholder="Lunedì: 8:00-20:00&#10;Martedì: 8:00-20:00&#10;..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="pharmacyPrompt" class="form-label">Prompt Personalizzato</label>
                        <textarea class="form-control" id="pharmacyPrompt" name="prompt" rows="4" placeholder="Istruzioni specifiche per l'assistente..."></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="pharmacyImgAvatar" class="form-label">Avatar</label>
                                <div class="input-group">
                                    <input type="file" class="form-control" id="pharmacyImgAvatarFile" accept="image/*" data-image-type="avatar">
                                    <button type="button" class="btn btn-outline-secondary" onclick="uploadImage('avatar')">Carica</button>
                                </div>
                                <input type="hidden" id="pharmacyImgAvatar" name="img_avatar">
                                <div id="pharmacyImgAvatarPreview" class="mt-2"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="pharmacyImgCover" class="form-label">Immagine Copertina</label>
                                <div class="input-group">
                                    <input type="file" class="form-control" id="pharmacyImgCoverFile" accept="image/*" data-image-type="cover">
                                    <button type="button" class="btn btn-outline-secondary" onclick="uploadImage('cover')">Carica</button>
                                </div>
                                <input type="hidden" id="pharmacyImgCover" name="img_cover">
                                <div id="pharmacyImgCoverPreview" class="mt-2"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="pharmacyImgBot" class="form-label">Immagine Bot</label>
                                <div class="input-group">
                                    <input type="file" class="form-control" id="pharmacyImgBotFile" accept="image/*" data-image-type="bot">
                                    <button type="button" class="btn btn-outline-secondary" onclick="uploadImage('bot')">Carica</button>
                                </div>
                                <input type="hidden" id="pharmacyImgBot" name="img_bot">
                                <div id="pharmacyImgBotPreview" class="mt-2"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-success">Crea Farmacia</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Modifica Farmacia -->
<div class="modal fade" id="editPharmacyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifica Farmacia</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editPharmacyForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="pharmacy_id" id="editPharmacyId">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="editPharmacyName" class="form-label">Nome Farmacia *</label>
                                <input type="text" class="form-control" id="editPharmacyName" name="nice_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="editPharmacyBusinessName" class="form-label">Ragione Sociale</label>
                                <input type="text" class="form-control" id="editPharmacyBusinessName" name="business_name">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editPharmacySlugUrl" class="form-label">URL Personalizzato</label>
                        <input type="text" class="form-control" id="editPharmacySlugUrl" name="slug_url" placeholder="es: farmacia-rossi-milano">
                        <div class="form-text">Lascia vuoto per generare automaticamente dall'URL del nome farmacia</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editPharmacyAddress" class="form-label">Indirizzo</label>
                        <input type="text" class="form-control" id="editPharmacyAddress" name="address">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="editPharmacyCity" class="form-label">Città</label>
                                <input type="text" class="form-control" id="editPharmacyCity" name="city">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="editPharmacyPhone" class="form-label">Telefono</label>
                                <input type="tel" class="form-control" id="editPharmacyPhone" name="phone_number">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="editPharmacyEmail" class="form-label">Email</label>
                                <input type="email" class="form-control" id="editPharmacyEmail" name="email">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editPharmacyStatus" class="form-label">Stato *</label>
                        <select class="form-select" id="editPharmacyStatus" name="status" required>
                            <option value="active">Attiva</option>
                            <option value="inactive">Inattiva</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editPharmacyLatLng" class="form-label">Coordinate GPS (lat,lng)</label>
                        <input type="text" class="form-control" id="editPharmacyLatLng" name="latlng" placeholder="45.4642,9.1900">
                    </div>
                    
                    <div class="mb-3">
                        <label for="editPharmacyDescription" class="form-label">Descrizione</label>
                        <textarea class="form-control" id="editPharmacyDescription" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editPharmacyWorkingInfo" class="form-label">Orari di Lavoro</label>
                        <textarea class="form-control" id="editPharmacyWorkingInfo" name="working_info" rows="4" placeholder="Lunedì: 8:00-20:00&#10;Martedì: 8:00-20:00&#10;..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editPharmacyPrompt" class="form-label">Prompt Personalizzato</label>
                        <textarea class="form-control" id="editPharmacyPrompt" name="prompt" rows="4" placeholder="Istruzioni specifiche per l'assistente..."></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="editPharmacyImgAvatar" class="form-label">Avatar</label>
                                <div class="input-group">
                                    <input type="file" class="form-control" id="editPharmacyImgAvatarFile" accept="image/*" data-image-type="avatar">
                                    <button type="button" class="btn btn-outline-secondary" onclick="uploadImage('avatar', true)">Carica</button>
                                </div>
                                <input type="hidden" id="editPharmacyImgAvatar" name="img_avatar">
                                <div id="editPharmacyImgAvatarPreview" class="mt-2"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="editPharmacyImgCover" class="form-label">Immagine Copertina</label>
                                <div class="input-group">
                                    <input type="file" class="form-control" id="editPharmacyImgCoverFile" accept="image/*" data-image-type="cover">
                                    <button type="button" class="btn btn-outline-secondary" onclick="uploadImage('cover', true)">Carica</button>
                                </div>
                                <input type="hidden" id="editPharmacyImgCover" name="img_cover">
                                <div id="editPharmacyImgCoverPreview" class="mt-2"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="editPharmacyImgBot" class="form-label">Immagine Bot</label>
                                <div class="input-group">
                                    <input type="file" class="form-control" id="editPharmacyImgBotFile" accept="image/*" data-image-type="bot">
                                    <button type="button" class="btn btn-outline-secondary" onclick="uploadImage('bot', true)">Carica</button>
                                </div>
                                <input type="hidden" id="editPharmacyImgBot" name="img_bot">
                                <div id="editPharmacyImgBotPreview" class="mt-2"></div>
                            </div>
                        </div>
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
$additional_js = ['assets/js/farmacie.js'];
require_once 'includes/footer.php'; 
?>
