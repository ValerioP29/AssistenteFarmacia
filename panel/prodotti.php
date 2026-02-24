<?php
/**
 * Gestione Prodotti Farmacia
 * Assistente Farmacia Panel
 */

require_once 'config/database.php';
require_once 'includes/auth_middleware.php';
require_once 'includes/functions.php';

// Verifica accesso farmacista
checkAccess(['pharmacist']);

$current_page = 'prodotti';
$page_title = 'Gestione Prodotti';

// Gestione messaggi
$message = $_GET['message'] ?? '';
$message_type = $_GET['type'] ?? 'info';

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-center flex-column flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-boxes me-2"></i>
                    Gestione Prodotti
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
                    <input type="text" id="searchInput" class="form-control" placeholder="Cerca prodotti (nome, SKU, ecc..)">
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
                        <option value="1">Attivo</option>
                        <option value="0">Inattivo</option>
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
                            <th>Immagine</th>
                            <th>SKU</th>
                            <th>Nome</th>
                            <th>Categoria</th>
                            <th>Brand</th>
                            <th>Prezzo</th>
                            <th>Stato</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody id="productsTableBody">
                        <!-- I prodotti verranno caricati via JavaScript -->
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
                <h5 class="modal-title" id="productModalLabel">
                    <i class="fas fa-plus me-1"></i> Nuovo Prodotto
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="productForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" id="productId" name="id">
                    <input type="hidden" id="selectedGlobalProductId" name="product_id">
                    
                    <!-- Ricerca Prodotto Globale -->
                    <div id="productSearchSection" class="mb-3">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-1"></i>
                            <strong>Importante:</strong> Ogni prodotto farmacia deve essere associato a un prodotto del catalogo globale. 
                            Cerca un prodotto esistente o creane uno nuovo nel catalogo.
                        </div>
                        <label for="globalProductSearch" class="form-label">Cerca Prodotto nel Catalogo Globale *</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="globalProductSearch" placeholder="Inizia a digitare per cercare...">
                            <button class="btn btn-outline-secondary" type="button" onclick="searchGlobalProducts()">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                        <div id="globalProductResults" class="mt-2" style="display: none;">
                            <!-- I risultati della ricerca verranno mostrati qui -->
                        </div>
                        <div class="form-text">
                            Se non trovi il prodotto, puoi crearlo nuovo nel catalogo globale. Clicca su "Crea Nuovo Prodotto" sotto.
                        </div>
                        <div class="mt-2">
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="createNewGlobalProduct()">
                                <i class="fas fa-plus me-1"></i> Crea Nuovo Prodotto nel Catalogo
                            </button>
                        </div>
                    </div>

                    <!-- Sezione Crea Nuovo Prodotto Globale -->
                    <div id="newGlobalProductSection" class="mb-3" style="display: none;">
                        <hr>
                        <h6 class="text-primary">
                            <i class="fas fa-plus-circle me-1"></i> Crea Nuovo Prodotto nel Catalogo Globale
                        </h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="globalProductSku" class="form-label">SKU Globale *</label>
                                    <input type="text" class="form-control" id="globalProductSku" name="global_sku" placeholder="Es: PAR001">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="globalProductCategory" class="form-label">Categoria</label>
                                    <select class="form-select" id="globalProductCategory" name="global_category">
                                        <option value="">Seleziona categoria</option>
                                        <option value="antidolorifici">Antidolorifici</option>
                                        <option value="antinfiammatori">Antinfiammatori</option>
                                        <option value="integratori">Integratori</option>
                                        <option value="antibiotici">Antibiotici</option>
                                        <option value="cardiovascolari">Cardiovascolari</option>
                                        <option value="dermatologici">Dermatologici</option>
                                        <option value="gastroenterologici">Gastroenterologici</option>
                                        <option value="respiratori">Respiratori</option>
                                        <option value="vitamine">Vitamine</option>
                                        <option value="generico">Generico</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="globalProductBrand" class="form-label">Brand</label>
                                    <input type="text" class="form-control" id="globalProductBrand" name="global_brand" placeholder="Es: Bayer">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="globalProductActiveIngredient" class="form-label">Principio Attivo</label>
                                    <input type="text" class="form-control" id="globalProductActiveIngredient" name="global_active_ingredient" placeholder="Es: Paracetamolo">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="globalProductDosageForm" class="form-label">Forma Farmaceutica</label>
                                    <input type="text" class="form-control" id="globalProductDosageForm" name="global_dosage_form" placeholder="Es: Compresse">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="globalProductStrength" class="form-label">Concentrazione</label>
                                    <input type="text" class="form-control" id="globalProductStrength" name="global_strength" placeholder="Es: 500mg">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="globalProductPackageSize" class="form-label">Confezione</label>
                                    <input type="text" class="form-control" id="globalProductPackageSize" name="global_package_size" placeholder="Es: 20 compresse">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="globalProductRequiresPrescription" name="global_requires_prescription" value="1">
                                <label class="form-check-label" for="globalProductRequiresPrescription">
                                    Richiede Prescrizione Medica
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="productName" class="form-label">Nome Prodotto *</label>
                                <input type="text" class="form-control" id="productName" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="productSku" class="form-label">SKU</label>
                                <input type="text" class="form-control" id="productSku" name="sku">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="productImage" class="form-label">Immagine Prodotto</label>
                                <input type="file" class="form-control" id="productImage" name="image" accept="image/*">
                                <div class="form-text">Lascia vuoto per usare l'immagine del catalogo globale</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div id="imagePreview" class="mt-2" style="display: none;">
                                    <img id="previewImg" src="" alt="Anteprima" class="img-thumbnail" style="max-width: 150px; max-height: 150px;">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="productDescription" class="form-label">Descrizione</label>
                        <textarea class="form-control" id="productDescription" name="description" rows="3"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="productPrice" class="form-label">Prezzo *</label>
                                <div class="input-group">
                                    <span class="input-group-text">€</span>
                                    <input type="number" class="form-control" id="productPrice" name="price" step="0.01" min="0" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="isActive" name="is_active" value="1" checked>
                            <label class="form-check-label" for="isActive">
                                Prodotto Attivo
                            </label>
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
                <p>Sei sicuro di voler eliminare questo prodotto?</p>
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

<link rel="stylesheet" href="assets/css/prodotti.css">
<script src="assets/js/prodotti.js"></script>

<?php include 'includes/footer.php'; ?> 