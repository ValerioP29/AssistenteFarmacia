<?php
/**
 * Gestione Promozioni Farmacia
 * Assistente Farmacia Panel
 */

require_once 'config/database.php';
require_once 'includes/auth_middleware.php';
require_once 'includes/functions.php';

// Verifica accesso farmacista
checkAccess(['pharmacist']);

$current_page = 'promozioni';
$page_title = 'Gestione Promozioni';

// Gestione messaggi
$message = $_GET['message'] ?? '';
$message_type = $_GET['type'] ?? 'info';

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="promo-header d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-tags me-2"></i>
                    Gestione Promozioni
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportPromotions()">
                            <i class="fas fa-download me-1"></i> Esporta
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="showImportModal()">
                            <i class="fas fa-upload me-1"></i> Importa
                        </button>
                    </div>
                    <button type="button" class="btn btn-sm btn-primary" onclick="showAddPromotionModal()">
                        <i class="fas fa-plus me-1"></i> Nuova Promozione
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
            <div class="row mb-4 filters-section">
                <div class="col-md-3 mb-3">
                    <input type="text" id="searchInput" class="form-control" placeholder="Cerca promozioni...">
                </div>
                <div class="col-md-2 mb-3">
                    <select id="statusFilter" class="form-select">
                        <option value="">Tutti gli stati</option>
                        <option value="active" selected>Attive</option>
                        <option value="inactive">Inattive</option>
                        <option value="expired">Scadute</option>
                    </select>
                </div>
                <div class="col-md-2 mb-3">
                    <select id="categoryFilter" class="form-select">
                        <option value="">Tutte le categorie</option>
                    </select>
                </div>
                <div class="col-md-2 mb-3">
                    <select id="discountFilter" class="form-select">
                        <option value="">Tutti gli sconti</option>
                        <option value="0-10">0-10%</option>
                        <option value="10-25">10-25%</option>
                        <option value="25-50">25-50%</option>
                        <option value="50+">50%+</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="button" class="btn btn-outline-secondary" onclick="clearFilters()">
                        <i class="fas fa-times me-1"></i> Pulisci Filtri
                    </button>
                </div>
            </div>

            <!-- Statistiche Promozioni -->
            <div class="row mb-4">
                <div class="col-md-3 mb-2">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Promozioni Attive</h6>
                                    <h3 id="activePromotionsCount">0</h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-tag fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-2">
                    <div class="card bg-secondary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Inattive</h6>
                                    <h3 id="inactivePromotionsCount">0</h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-pause fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-2">
                    <div class="card bg-danger text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Scadute</h6>
                                    <h3 id="expiredPromotionsCount">0</h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-times-circle fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-2">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Sconto Medio</h6>
                                    <h3 id="averageDiscount">0%</h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-percentage fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Layout a Schede Promozioni -->
            <div class="row" id="promotionsGrid">
                <!-- Le promozioni verranno caricate via JavaScript -->
            </div>

            <!-- Paginazione -->
            <nav aria-label="Paginazione promozioni" class="mt-4">
                <ul class="pagination justify-content-center" id="pagination">
                    <!-- La paginazione verrà generata via JavaScript -->
                </ul>
            </nav>
        </main>
    </div>
</div>

<!-- Modal Aggiungi/Modifica Promozione -->
<div class="modal fade" id="promotionModal" tabindex="-1" aria-labelledby="promotionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="promotionModalLabel">
                    <i class="fas fa-plus me-1"></i> Nuova Promozione
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="promotionForm">
                <div class="modal-body">
                    <input type="hidden" id="promotionId" name="id">
                    
                    <!-- Selezione Prodotto -->
                    <div class="mb-3">
                        <label for="productSearch" class="form-label">Cerca Prodotto *</label>
                        <div class="position-relative">
                            <input type="text" class="form-control" id="productSearch" placeholder="Cerca un prodotto della tua farmacia..." autocomplete="off">
                            <input type="hidden" id="productSelect" name="product_id" required>
                            <div id="productSearchResults" class="position-absolute w-100 bg-white border rounded shadow-sm" style="top: 100%; left: 0; z-index: 1050; max-height: 300px; overflow-y: auto; display: none;"></div>
                        </div>
                        <!-- <div class="form-text">Digita per cercare un prodotto della tua farmacia da mettere in promozione</div>-->                    
                        </div>

                    <!-- Informazioni Prodotto -->
                    <div id="productInfo" class="mb-3" style="display: none;">
                        <div class="card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <img id="productImage" src="" alt="Prodotto" class="img-fluid rounded">
                                    </div>
                                    <div class="col-md-9">
                                        <h6 id="productName"></h6>
                                        <p id="productDescription" class="text-muted"></p>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <small class="text-muted">Prezzo attuale:</small>
                                                <div class="fw-bold text-primary" id="currentPrice"></div>
                                            </div>
                                            <div class="col-md-4">
                                                <small class="text-muted">Categoria:</small>
                                                <div id="productCategory"></div>
                                            </div>
                                            <div class="col-md-4">
                                                <small class="text-muted">Brand:</small>
                                                <div id="productBrand"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="salePrice" class="form-label">Prezzo Scontato *</label>
                                <div class="input-group">
                                    <span class="input-group-text" id="priceLabel">€</span>
                                    <input type="number" class="form-control" id="salePrice" name="sale_price" step="0.01" min="0" required>
                                </div>

                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="discountType" class="form-label">Tipo di Sconto</label>
                                <select class="form-select" id="discountType" name="discount_type">
                                    <option value="percentage">Percentuale</option>
                                    <option value="amount" selected>Importo Fisso</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="saleStartDate" class="form-label">Data Inizio Promozione *</label>
                                <input type="date" class="form-control" id="saleStartDate" name="sale_start_date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="saleEndDate" class="form-label">Data Fine Promozione *</label>
                                <input type="date" class="form-control" id="saleEndDate" name="sale_end_date" required>
                            </div>
                        </div>
                    </div>



                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="isOnSale" name="is_on_sale" value="1" checked>
                            <label class="form-check-label" for="isOnSale">
                                Promozione Attiva
                            </label>
                        </div>
                        <div class="mb-3"></div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="isFeatured" name="is_featured" value="1">
                            <label class="form-check-label" for="isFeatured">
                            Metti in evidenza
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Salva Promozione
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
                <h5 class="modal-title" id="importModalLabel">Importa Promozioni</h5>
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
                                Aggiorna promozioni esistenti
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
                <p>Sei sicuro di voler eliminare questa promozione?</p>
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

<link rel="stylesheet" href="assets/css/promozioni.css">
<script src="assets/js/promozioni.js"></script>

<?php include 'includes/footer.php'; ?> 