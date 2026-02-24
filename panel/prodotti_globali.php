<?php
/**
 * Gestione Prodotti Globali
 * Assistente Farmacia Panel
 */

require_once 'config/database.php';
require_once 'includes/auth_middleware.php';
require_once 'includes/functions.php';

// Verifica accesso admin
checkAdminAccess();

$current_page = 'prodotti_globali';
$page_title = 'Gestione Prodotti Globali';

// Gestione messaggi
$message = $_GET['message'] ?? '';
$message_type = $_GET['type'] ?? 'info';

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-boxes me-2"></i>
                    Gestione Prodotti Globali
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportProducts()">
                            <i class="fas fa-download me-1"></i> Esporta
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="showImportModal()">
                            <i class="fas fa-upload me-1"></i> Importa
                        </button>
                    </div>
                    <button type="button" class="btn btn-sm btn-primary" onclick="showAddProductModal()">
                        <i class="fas fa-plus me-1"></i> Nuovo Prodotto
                    </button>
                </div>
            </div>

            <!-- Messaggi -->
            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Filtri e Ricerca -->
            <div class="row mb-3 filters-section">
                <div class="col-md-3">
                    <input type="text" id="searchInput" class="form-control" placeholder="Cerca prodotti...">
                </div>
                <div class="col-md-2">
                    <select id="categoryFilter" class="form-select">
                        <option value="">Tutte le categorie</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select id="brandFilter" class="form-select">
                        <option value="">Tutti i brand</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select id="statusFilter" class="form-select">
                        <option value="">Tutti gli stati</option>
                        <option value="active">Attivo</option>
                        <option value="inactive">Inattivo</option>
                        <option value="pending_approval">Da Approvare</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="button" class="btn btn-outline-secondary" onclick="clearFilters()">
                        <i class="fas fa-times me-1"></i> Pulisci Filtri
                    </button>
                </div>
            </div>

            <!-- Tabella Prodotti -->
            <div class="table-responsive products-table">
                <table class="table table-striped table-hover" id="productsTable">
                    <thead class="table-dark">
                        <tr>
                            <th>SKU</th>
                            <th>Nome</th>
                            <th>Categoria</th>
                            <th>Brand</th>
                            <th>Principio Attivo</th>
                            <th>Forma</th>
                            <th>Dosaggio</th>
                            <th>Ricetta</th>
                            <th>Stato</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody id="productsTableBody">
                        <!-- I dati verranno caricati via AJAX -->
                    </tbody>
                </table>
            </div>

            <!-- Paginazione -->
            <nav aria-label="Paginazione prodotti">
                <ul class="pagination justify-content-center" id="pagination">
                    <!-- La paginazione verrà generata via JavaScript -->
                </ul>
            </nav>
        </main>
    </div>
</div>

<!-- Modal Aggiungi/Modifica Prodotto -->
<div class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="productModalLabel">Nuovo Prodotto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="productForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" id="productId" name="id">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="sku" class="form-label">SKU *</label>
                                <input type="text" class="form-control" id="sku" name="sku" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Nome Prodotto *</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Descrizione</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="category" class="form-label">Categoria</label>
                                <input type="text" class="form-control" id="category" name="category">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="brand" class="form-label">Brand</label>
                                <input type="text" class="form-control" id="brand" name="brand">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="active_ingredient" class="form-label">Principio Attivo</label>
                                <input type="text" class="form-control" id="active_ingredient" name="active_ingredient">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="dosage_form" class="form-label">Forma Farmaceutica</label>
                                <select class="form-select" id="dosage_form" name="dosage_form">
                                    <option value="">Seleziona...</option>
                                    <option value="Compresse">Compresse</option>
                                    <option value="Capsule">Capsule</option>
                                    <option value="Sciroppo">Sciroppo</option>
                                    <option value="Pomata">Pomata</option>
                                    <option value="Gocce">Gocce</option>
                                    <option value="Supposte">Supposte</option>
                                    <option value="Iniezione">Iniezione</option>
                                    <option value="Aerosol">Aerosol</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="strength" class="form-label">Dosaggio</label>
                                <input type="text" class="form-control" id="strength" name="strength" placeholder="es. 500mg">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="package_size" class="form-label">Confezione</label>
                                <input type="text" class="form-control" id="package_size" name="package_size" placeholder="es. 20 compresse">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="productImage" class="form-label">Immagine Prodotto</label>
                                <input type="file" class="form-control" id="productImage" name="image" accept="image/*">
                                <div class="form-text">Formati supportati: JPG, PNG, GIF. Max 5MB</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="requires_prescription" name="requires_prescription" value="1">
                                    <label class="form-check-label" for="requires_prescription">
                                        Richiede Ricetta Medica
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" checked>
                                    <label class="form-check-label" for="is_active">
                                        Prodotto Attivo
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="imagePreview" class="mb-3" style="display: none;">
                        <label class="form-label">Anteprima Immagine</label>
                        <div>
                            <img id="previewImg" src="" alt="Anteprima" class="img-thumbnail" style="max-width: 200px;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Salva Prodotto
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Import -->
<div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importModalLabel">Importa Prodotti</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="importForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="importFile" class="form-label">File CSV/Excel</label>
                        <input type="file" class="form-control" id="importFile" name="file" accept=".csv,.xlsx,.xls" required>
                        <div class="form-text">
                            Formati supportati: CSV, Excel. 
                            <a href="#" onclick="downloadTemplate()">Scarica template</a>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="updateExisting" name="update_existing" value="1">
                            <label class="form-check-label" for="updateExisting">
                                Aggiorna prodotti esistenti (basato su SKU)
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload me-1"></i> Importa
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Conferma Eliminazione -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Conferma Eliminazione</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Sei sicuro di voler eliminare questo prodotto globale?</p>
                <p class="text-warning"><strong>ATTENZIONE:</strong> Verranno eliminati anche tutti i prodotti farmacia collegati a questo prodotto globale.</p>
                <p class="text-danger"><strong>Questa azione non può essere annullata.</strong></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                    <i class="fas fa-trash me-1"></i> Elimina
                </button>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="assets/css/prodotti_globali.css">

<script src="assets/js/prodotti_globali.js"></script>

<?php include 'includes/footer.php'; ?> 