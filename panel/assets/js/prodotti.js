/**
 * Gestione Prodotti Farmacia
 * Assistente Farmacia Panel
 */

// Variabili globali
let currentPage = 1;
let productsPerPage = 20;
let totalProducts = 0;
let currentFilters = {};
let productToDelete = null;
let selectedGlobalProduct = null;

// Inizializzazione
document.addEventListener('DOMContentLoaded', function() {
    loadProducts();
    setupEventListeners();
    loadFilters();
});

// Setup event listeners
function setupEventListeners() {
    // Ricerca
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(function(event) {
            currentFilters.search = event.target.value || '';
            currentPage = 1;
            loadProducts();
        }, 300));
    }

    // Filtri
    const categoryFilter = document.getElementById('categoryFilter');
    if (categoryFilter) {
        categoryFilter.addEventListener('change', function() {
            currentFilters.category = this.value;
            currentPage = 1;
            loadProducts();
        });
    }

    const brandFilter = document.getElementById('brandFilter');
    if (brandFilter) {
        brandFilter.addEventListener('change', function() {
            currentFilters.brand = this.value;
            currentPage = 1;
            loadProducts();
        });
    }

    const statusFilter = document.getElementById('statusFilter');
    if (statusFilter) {
        statusFilter.addEventListener('change', function() {
            currentFilters.status = this.value;
            currentPage = 1;
            loadProducts();
        });
    }

    // Form prodotto
    const productForm = document.getElementById('productForm');
    if (productForm) {
        productForm.addEventListener('submit', handleProductSubmit);
    }
    
    // Preview immagine
    const productImage = document.getElementById('productImage');
    if (productImage) {
        productImage.addEventListener('change', handleImagePreview);
    }
    
    // Form import
    const importForm = document.getElementById('importForm');
    if (importForm) {
        importForm.addEventListener('submit', handleImportSubmit);
    }
    
    // Modal event listeners
    const productModal = document.getElementById('productModal');
    if (productModal) {
        productModal.addEventListener('hidden.bs.modal', function() {
            // Reset della sezione di ricerca quando si chiude il modal
            const productSearchSection = document.getElementById('productSearchSection');
            if (productSearchSection) {
                productSearchSection.style.display = 'block';
            }
            hideGlobalProductResults();
        });
    }

    // Ricerca prodotti globali
    const globalProductSearch = document.getElementById('globalProductSearch');
    if (globalProductSearch) {
        globalProductSearch.addEventListener('input', debounce(function(event) {
            if (event.target.value.length >= 2) {
                searchGlobalProducts(event.target.value);
            } else {
                hideGlobalProductResults();
            }
        }, 300));
    }
}

// Carica i prodotti
function loadProducts() {
    // Pulisci i filtri undefined
    const cleanFilters = {};
    Object.keys(currentFilters).forEach(key => {
        if (currentFilters[key] !== undefined && currentFilters[key] !== '') {
            cleanFilters[key] = currentFilters[key];
        }
    });

    const params = new URLSearchParams({
        page: currentPage,
        limit: productsPerPage,
        ...cleanFilters
    });

    fetch(`api/pharma-products/list.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderProducts(data.products);
                renderPagination(data.total, data.current_page, data.total_pages);
                totalProducts = data.total;
            } else {
                showAlert('Errore nel caricamento dei prodotti: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Errore:', error);
            showAlert('Errore di connessione', 'danger');
        });
}

// Renderizza i prodotti nella tabella
function renderProducts(products) {
    const tbody = document.getElementById('productsTableBody');
    tbody.innerHTML = '';

    if (products.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center text-muted py-4">
                    <i class="fas fa-box-open fa-2x mb-2"></i>
                    <br>Nessun prodotto trovato
                </td>
            </tr>
        `;
        return;
    }

    products.forEach(product => {
        const row = document.createElement('tr');

        // Formatta prezzi
        const price = parseFloat(product.price).toFixed(2);
        
        row.innerHTML = `
            <td>
                ${product.image ? 
                    `<img src="${product.image}" alt="${escapeHtml(product.name)}" class="img-thumbnail" width="50" height="50" style="width: 50px; height: 50px; object-fit: cover; overflow:hidden; font-size:1px;" onerror="this.src='assets/images/default-product-thumb.png'">`
                    : `<div class="bg-light d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; border-radius: 4px;">
                        <i class="fas fa-image text-muted"></i>
                    </div>`
                }
                    <div class="bg-light d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; border-radius: 4px;">
                       <i class="fas fa-image text-muted"></i>
                    </div>
            </td>
            <td><strong>${escapeHtml(product.sku || '-')}</strong></td>
            <td data-sku="${escapeHtml(product.sku || '-')}" data-stato="${product.is_active ? 'Attivo' : 'Inattivo'}">
                <div class="fw-bold">${escapeHtml(product.name)}</div>
                ${product.description ? `<small class="text-muted">${escapeHtml(product.description.substring(0, 50))}${product.description.length > 50 ? '...' : ''}</small>` : ''}
            </td>
            <td>${escapeHtml(product.category || '-')}</td>
            <td>${escapeHtml(product.brand || '-')}</td>
            <td>
                <span class="price">€${price}</span>
            </td>
            <td>
                <span class="badge ${product.is_active ? 'bg-success' : 'bg-secondary'}">
                    ${product.is_active ? 'Attivo' : 'Inattivo'}
                </span>
            </td>
            <td>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-primary" onclick="editProduct(${product.id})" title="Modifica">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" class="btn btn-outline-danger" onclick="deleteProduct(${product.id})" title="Elimina">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(row);
    });
}

// Renderizza la paginazione
function renderPagination(total, current, totalPages) {
    const pagination = document.getElementById('pagination');
    pagination.innerHTML = '';

    if (totalPages <= 1) return;

    // Pulsante precedente
    if (current > 1) {
        pagination.innerHTML += `
            <li class="page-item">
                <a class="page-link" href="#" onclick="goToPage(${current - 1})">
                    <i class="fas fa-chevron-left"></i>
                </a>
            </li>
        `;
    }

    // Pagine
    for (let i = Math.max(1, current - 2); i <= Math.min(totalPages, current + 2); i++) {
        pagination.innerHTML += `
            <li class="page-item ${i === current ? 'active' : ''}">
                <a class="page-link" href="#" onclick="goToPage(${i})">${i}</a>
            </li>
        `;
    }

    // Pulsante successivo
    if (current < totalPages) {
        pagination.innerHTML += `
            <li class="page-item">
                <a class="page-link" href="#" onclick="goToPage(${current + 1})">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </li>
        `;
    }
}

// Vai alla pagina
function goToPage(page) {
    currentPage = page;
    loadProducts();
}

// Carica filtri
function loadFilters() {
    fetch('api/pharma-products/filters.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                populateFilter('categoryFilter', data.categories);
                populateFilter('brandFilter', data.brands);
            }
        })
        .catch(error => {
            console.error('Errore caricamento filtri:', error);
        });
}

// Popola filtro
function populateFilter(filterId, options) {
    const filter = document.getElementById(filterId);
    if (filter && options) {
        options.forEach(option => {
            const optionElement = document.createElement('option');
            optionElement.value = option;
            optionElement.textContent = option;
            filter.appendChild(optionElement);
        });
    }
}

// Pulisci filtri
function clearFilters() {
    currentFilters = {};
    currentPage = 1;
    
    // Reset form fields
    document.getElementById('searchInput').value = '';
    document.getElementById('categoryFilter').value = '';
    document.getElementById('brandFilter').value = '';
    document.getElementById('statusFilter').value = '';
    
    loadProducts();
}

// Mostra modal aggiungi prodotto
function showAddProductModal() {
    document.getElementById('productModalLabel').innerHTML = '<i class="fas fa-plus me-1"></i> Nuovo Prodotto';
    document.getElementById('productForm').reset();
    document.getElementById('productId').value = '';
    document.getElementById('selectedGlobalProductId').value = '';
    selectedGlobalProduct = null;
    hideGlobalProductResults();
    hideImagePreview();
    
    // Nascondi sezione creazione nuovo prodotto globale
    document.getElementById('newGlobalProductSection').style.display = 'none';
    
    // Mostra la sezione di ricerca prodotti per nuovi prodotti
    const productSearchSection = document.getElementById('productSearchSection');
    if (productSearchSection) {
        productSearchSection.style.display = 'block';
    }
    
    const modal = new bootstrap.Modal(document.getElementById('productModal'));
    modal.show();
}

// Modifica prodotto
function editProduct(id) {
    fetch(`api/pharma-products/get.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const product = data.product;
                
                document.getElementById('productModalLabel').innerHTML = '<i class="fas fa-edit me-1"></i> Modifica Prodotto';
                document.getElementById('productId').value = product.id;
                document.getElementById('selectedGlobalProductId').value = product.product_id || '';
                document.getElementById('productName').value = product.name;
                

                document.getElementById('productSku').value = product.sku || '';
                document.getElementById('productDescription').value = product.description || '';
                document.getElementById('productPrice').value = product.price;
                document.getElementById('isActive').checked = product.is_active;
                
                // Mostra immagine se disponibile
                if (product.image) {
                    showImagePreview(product.image);
                } else {
                    hideImagePreview();
                }
                
                // Nascondi la sezione di ricerca prodotti quando si modifica
                const productSearchSection = document.getElementById('productSearchSection');
                if (productSearchSection) {
                    productSearchSection.style.display = 'none';
                }
                
                const modal = new bootstrap.Modal(document.getElementById('productModal'));
                modal.show();
            } else {
                showAlert('Errore nel caricamento del prodotto: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Errore:', error);
            showAlert('Errore di connessione', 'danger');
        });
}

// Gestione submit form prodotto
function handleProductSubmit(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    
    // Verifica se stiamo creando un nuovo prodotto globale
    const isCreatingGlobalProduct = document.getElementById('newGlobalProductSection').style.display !== 'none';
    
    // Verifica se è stato selezionato un prodotto globale
    const selectedGlobalProductId = document.getElementById('selectedGlobalProductId').value;
    const isEditing = document.getElementById('productId').value > 0;
    
    // Per nuovi prodotti (non in modifica), è necessario selezionare un prodotto globale o crearne uno nuovo
    if (!isEditing && !isCreatingGlobalProduct && !selectedGlobalProductId) {
        showAlert('Devi selezionare un prodotto dal catalogo globale o creare un nuovo prodotto nel catalogo', 'danger');
        return;
    }
    
    // Se stiamo modificando un prodotto, assicurati che il product_id sia presente
    if (isEditing && !selectedGlobalProductId) {
        showAlert('Errore: ID prodotto globale mancante. Ricarica la pagina e riprova.', 'danger');
        return;
    }
    
    if (isCreatingGlobalProduct) {
        // Validazione campi obbligatori per prodotto globale
        const globalSku = document.getElementById('globalProductSku').value.trim();
        if (!globalSku) {
            showAlert('SKU Globale è obbligatorio per creare un nuovo prodotto nel catalogo', 'danger');
            return;
        }
        
        // Aggiungi flag per indicare che stiamo creando un prodotto globale
        formData.append('create_global_product', '1');
    }
    
    fetch('api/pharma-products/add.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('productModal')).hide();
            loadProducts();
        } else {
            showAlert('Errore nel salvataggio: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Errore:', error);
        showAlert('Errore di connessione', 'danger');
    });
}

// Elimina prodotto
function deleteProduct(id) {
    productToDelete = id;
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}

// Conferma eliminazione
function confirmDelete() {
    if (!productToDelete) return;
    
    fetch('api/pharma-products/delete.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ id: productToDelete })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Prodotto eliminato con successo', 'success');
            bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
            loadProducts();
        } else {
            showAlert('Errore nell\'eliminazione: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Errore:', error);
        showAlert('Errore di connessione', 'danger');
    });
    
    productToDelete = null;
}

// Mostra modal import
function showImportModal() {
    const modal = new bootstrap.Modal(document.getElementById('importModal'));
    modal.show();
}

// Gestione submit form import
function handleImportSubmit(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    
    fetch('api/pharma-products/import.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(`Import completato: ${data.imported} importati, ${data.updated} aggiornati`, 'success');
            bootstrap.Modal.getInstance(document.getElementById('importModal')).hide();
            loadProducts();
        } else {
            showAlert('Errore nell\'import: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Errore:', error);
        showAlert('Errore di connessione', 'danger');
    });
}

// Esporta prodotti
function exportProducts() {
    const params = new URLSearchParams(currentFilters);
    window.open(`api/pharma-products/export.php?${params}`, '_blank');
}

// Scarica template
function downloadTemplate() {
    window.open('api/pharma-products/template.php', '_blank');
}

// Ricerca prodotti globali
function searchGlobalProducts(query = null) {
    const searchTerm = query || document.getElementById('globalProductSearch').value;
    
    if (searchTerm.length < 2) {
        hideGlobalProductResults();
        return;
    }
    
    fetch(`api/pharma-products/search-global.php?q=${encodeURIComponent(searchTerm)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderGlobalProductResults(data.products);
            }
        })
        .catch(error => {
            console.error('Errore ricerca prodotti globali:', error);
        });
}

// Renderizza risultati ricerca prodotti globali
function renderGlobalProductResults(products) {
    const resultsContainer = document.getElementById('globalProductResults');
    
    if (products.length === 0) {
        resultsContainer.innerHTML = '<div class="p-3 text-muted">Nessun prodotto trovato</div>';
    } else {
        resultsContainer.innerHTML = products.map(product => `
            <div class="global-product-item" onclick="selectGlobalProduct(${JSON.stringify(product).replace(/"/g, '&quot;')})">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        ${product.image ? 
                            `<img src="${product.image}" alt="${escapeHtml(product.name)}" class="img-thumbnail" width="40" height="40" style="width: 40px; height: 40px; object-fit: cover; overflow:hidden; font-size:1px;" onerror="this.src='assets/images/default-product-thumb.png'">` 
                            : `<div class="bg-light d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; border-radius: 4px;">
                                <i class="fas fa-image text-muted"></i>
                            </div>`
                        }
                    </div>
                    <div class="flex-grow-1">
                        <div class="global-product-name">${escapeHtml(product.name)}</div>
                        <div class="global-product-category">${escapeHtml(product.category || '')}</div>
                        <div class="global-product-sku">${escapeHtml(product.sku)}</div>
                    </div>
              </div>
            </div>
        `).join('');
    }
    
    resultsContainer.style.display = 'block';
}

// Nascondi risultati ricerca prodotti globali
function hideGlobalProductResults() {
    const resultsContainer = document.getElementById('globalProductResults');
    resultsContainer.style.display = 'none';
}

// Seleziona prodotto globale
function selectGlobalProduct(product) {
    selectedGlobalProduct = product;
    
    // Popola i campi del form
    document.getElementById('productName').value = product.name;
    document.getElementById('productSku').value = product.sku;
    document.getElementById('productDescription').value = product.description || '';
    
    // Imposta il product_id per l'associazione
    document.getElementById('selectedGlobalProductId').value = product.id;
    
    // Mostra immagine del prodotto globale se disponibile
    if (product.image) {
        showImagePreview(product.image);
    } else {
        hideImagePreview();
    }
    
    // Nascondi risultati
    hideGlobalProductResults();
    
    // Pulisci campo di ricerca
    document.getElementById('globalProductSearch').value = '';
    
    showAlert(`Prodotto "${product.name}" selezionato dal catalogo globale`, 'success');
}

// Gestione preview immagine
function handleImagePreview(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            showImagePreview(e.target.result);
        };
        reader.readAsDataURL(file);
    } else {
        hideImagePreview();
    }
}

// Mostra preview immagine
function showImagePreview(src) {
    const preview = document.getElementById('imagePreview');
    const img = document.getElementById('previewImg');
    img.src = src;
    preview.style.display = 'block';
}

// Nascondi preview immagine
function hideImagePreview() {
    const preview = document.getElementById('imagePreview');
    preview.style.display = 'none';
}

// Mostra sezione creazione nuovo prodotto globale
function createNewGlobalProduct() {
    const section = document.getElementById('newGlobalProductSection');
    section.style.display = 'block';
    
    // Nascondi risultati ricerca
    hideGlobalProductResults();
    
    // Pulisci campi ricerca
    document.getElementById('globalProductSearch').value = '';
    
    // Genera SKU automatico se vuoto
    const globalSku = document.getElementById('globalProductSku');
    if (!globalSku.value) {
        const productName = document.getElementById('productName').value;
        if (productName) {
            const sku = productName.substring(0, 3).toUpperCase() + '001';
            globalSku.value = sku;
        }
    }
    
    showAlert('Sezione creazione nuovo prodotto globale attivata', 'info');
}

// Mostra alert
function showAlert(message, type = 'info') {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3" role="alert" style="z-index: 9999; min-width: 300px; max-width: 500px;">
            <div style="padding-right: 30px;">
                ${message}
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%);"></button>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', alertHtml);
    
    // Auto-remove dopo 5 secondi
    setTimeout(() => {
        const alert = document.querySelector('.alert');
        if (alert) {
            alert.remove();
        }
    }, 5000);
}

// Escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Debounce function
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}
