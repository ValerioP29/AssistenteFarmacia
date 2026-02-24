/**
 * Gestione Prodotti Globali
 * Assistente Farmacia Panel
 */

// Variabili globali
let currentPage = 1;
let productsPerPage = 20;
let totalProducts = 0;
let currentFilters = {};
let productToDelete = null;

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
    } else {
        console.error('Elemento searchInput non trovato');
    }

    // Filtri
    document.getElementById('categoryFilter').addEventListener('change', function() {
        currentFilters.category = this.value;
        currentPage = 1;
        loadProducts();
    });

    document.getElementById('brandFilter').addEventListener('change', function() {
        currentFilters.brand = this.value;
        currentPage = 1;
        loadProducts();
    });

    document.getElementById('statusFilter').addEventListener('change', function() {
        currentFilters.status = this.value;
        currentPage = 1;
        loadProducts();
    });

    // Form prodotto
    document.getElementById('productForm').addEventListener('submit', handleProductSubmit);
    
    // Form import
    document.getElementById('importForm').addEventListener('submit', handleImportSubmit);
    
    // Preview immagine
    document.getElementById('productImage').addEventListener('change', handleImagePreview);
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

    fetch(`api/products/list.php?${params}`)
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
                <td colspan="10" class="text-center text-muted py-4">
                    <i class="fas fa-box-open fa-2x mb-2"></i>
                    <br>Nessun prodotto trovato
                </td>
            </tr>
        `;
        return;
    }

    products.forEach(product => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td><strong>${escapeHtml(product.sku)}</strong></td>
            <td>
                <div class="d-flex align-items-center">
                    ${product.image ? `<img src="${product.image}" alt="${escapeHtml(product.name)}" class="me-2 product-image" onerror="this.src='assets/images/default-product-thumb.png'">` : '<div class="product-image-placeholder me-2"><i class="fas fa-pills"></i></div>'}
                    <div>
                        <div class="fw-bold">${escapeHtml(product.name)}</div>
                        ${product.description ? `<small class="text-muted">${escapeHtml(product.description.substring(0, 50))}${product.description.length > 50 ? '...' : ''}</small>` : ''}
                    </div>
                </div>
            </td>
            <td>${escapeHtml(product.category || '-')}</td>
            <td>${escapeHtml(product.brand || '-')}</td>
            <td>${escapeHtml(product.active_ingredient || '-')}</td>
            <td>${escapeHtml(product.dosage_form || '-')}</td>
            <td>${escapeHtml(product.strength || '-')}</td>
            <td>
                <span class="badge ${product.requires_prescription ? 'bg-warning' : 'bg-success'}">
                    ${product.requires_prescription ? 'Sì' : 'No'}
                </span>
            </td>
            <td>
                <span class="badge ${getStatusBadgeClass(product.is_active)}">
                    ${getStatusText(product.is_active)}
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

    // Pagina precedente
    if (current > 1) {
        pagination.innerHTML += `
            <li class="page-item">
                <a class="page-link" href="#" onclick="goToPage(${current - 1})">
                    <i class="fas fa-chevron-left"></i>
                </a>
            </li>
        `;
    }

    // Pagine numeriche
    const start = Math.max(1, current - 2);
    const end = Math.min(totalPages, current + 2);

    for (let i = start; i <= end; i++) {
        pagination.innerHTML += `
            <li class="page-item ${i === current ? 'active' : ''}">
                <a class="page-link" href="#" onclick="goToPage(${i})">${i}</a>
            </li>
        `;
    }

    // Pagina successiva
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

// Vai a una pagina specifica
function goToPage(page) {
    currentPage = page;
    loadProducts();
}

// Carica i filtri
function loadFilters() {
    fetch('api/products/filters.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                populateFilter('categoryFilter', data.categories);
                populateFilter('brandFilter', data.brands);
            }
        })
        .catch(error => console.error('Errore caricamento filtri:', error));
}

// Popola un filtro
function populateFilter(filterId, options) {
    const select = document.getElementById(filterId);
    options.forEach(option => {
        const optionElement = document.createElement('option');
        optionElement.value = option;
        optionElement.textContent = option;
        select.appendChild(optionElement);
    });
}

// Pulisci i filtri
function clearFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('categoryFilter').value = '';
    document.getElementById('brandFilter').value = '';
    document.getElementById('statusFilter').value = '';
    
    currentFilters = {};
    currentPage = 1;
    loadProducts();
}

// Mostra modal per aggiungere prodotto
function showAddProductModal() {
    document.getElementById('productModalLabel').textContent = 'Nuovo Prodotto';
    document.getElementById('productForm').reset();
    document.getElementById('productId').value = '';
    document.getElementById('imagePreview').style.display = 'none';
    
    const modal = new bootstrap.Modal(document.getElementById('productModal'));
    modal.show();
}

// Modifica prodotto
function editProduct(id) {
    fetch(`api/products/get.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const product = data.product;
                
                document.getElementById('productModalLabel').textContent = 'Modifica Prodotto';
                document.getElementById('productId').value = product.id;
                document.getElementById('sku').value = product.sku;
                document.getElementById('name').value = product.name;
                document.getElementById('description').value = product.description || '';
                document.getElementById('category').value = product.category || '';
                document.getElementById('brand').value = product.brand || '';
                document.getElementById('active_ingredient').value = product.active_ingredient || '';
                document.getElementById('dosage_form').value = product.dosage_form || '';
                document.getElementById('strength').value = product.strength || '';
                document.getElementById('package_size').value = product.package_size || '';
                document.getElementById('requires_prescription').checked = product.requires_prescription == 1;
                document.getElementById('is_active').checked = product.is_active === 'active';
                
                // Mostra preview immagine se presente
                if (product.image) {
                    document.getElementById('previewImg').src = product.image;
                    document.getElementById('imagePreview').style.display = 'block';
                } else {
                    document.getElementById('imagePreview').style.display = 'none';
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

// Gestisce il submit del form prodotto
function handleProductSubmit(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const productId = formData.get('id');
    const url = productId ? 'api/products/update.php' : 'api/products/add.php';
    
    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        // Controlla se la risposta è JSON
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            return response.json();
        } else {
            // Se non è JSON, leggi il testo per il debug
            return response.text().then(text => {
                console.error('Risposta non JSON ricevuta:', text);
                throw new Error('Risposta non JSON ricevuta dal server');
            });
        }
    })
    .then(data => {
        if (data.success) {
            showAlert(productId ? 'Prodotto aggiornato con successo!' : 'Prodotto creato con successo!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('productModal')).hide();
            loadProducts();
        } else {
            showAlert('Errore: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Errore:', error);
        showAlert('Errore di connessione: ' + error.message, 'danger');
    });
}

// Elimina prodotto
function deleteProduct(id) {
    productToDelete = id;
    
    // Mostra conferma con avviso sui prodotti farmacia collegati
    if (confirm('ATTENZIONE: Eliminando questo prodotto globale verranno eliminati anche tutti i prodotti farmacia collegati. Sei sicuro di voler procedere?')) {
        const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
        modal.show();
    }
}

// Conferma eliminazione
function confirmDelete() {
    if (!productToDelete) return;
    
    fetch('api/products/delete.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ id: productToDelete })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
            loadProducts();
        } else {
            showAlert('Errore: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Errore:', error);
        showAlert('Errore di connessione', 'danger');
    })
    .finally(() => {
        productToDelete = null;
    });
}

// Mostra modal import
function showImportModal() {
    const modal = new bootstrap.Modal(document.getElementById('importModal'));
    modal.show();
}

// Gestisce il submit del form import
function handleImportSubmit(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    
    fetch('api/products/import.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(`Import completato! ${data.imported} prodotti importati, ${data.updated} aggiornati.`, 'success');
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
    window.open(`api/products/export.php?${params}`, '_blank');
}

// Download template
function downloadTemplate() {
    window.open('api/products/template.php', '_blank');
}

// Gestisce preview immagine
function handleImagePreview(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('previewImg').src = e.target.result;
            document.getElementById('imagePreview').style.display = 'block';
        };
        reader.readAsDataURL(file);
    } else {
        document.getElementById('imagePreview').style.display = 'none';
    }
}

// Utility functions
function showAlert(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    const container = document.querySelector('main');
    container.insertBefore(alertDiv, container.firstChild);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Funzioni helper per gestire i status
function getStatusText(status) {
    switch (status) {
        case 'active': return 'Attivo';
        case 'inactive': return 'Inattivo';
        case 'pending_approval': return 'Da Approvare';
        case 'deleted': return 'Eliminato';
        default: return 'Sconosciuto';
    }
}

function getStatusBadgeClass(status) {
    switch (status) {
        case 'active': return 'bg-success';
        case 'inactive': return 'bg-secondary';
        case 'pending_approval': return 'bg-warning';
        case 'deleted': return 'bg-danger';
        default: return 'bg-secondary';
    }
}

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