/**
 * Gestione Promozioni JavaScript
 * Assistente Farmacia Panel
 */

let currentPage = 1;
let totalPages = 1;
let currentFilters = {};
let productsList = [];
let selectedPromotionId = null;

// Inizializzazione
document.addEventListener('DOMContentLoaded', function() {
    loadPromotions();
    setupProductSearch();
    setupEventListeners();
    loadStatistics();
    applyFilters();
});

// Setup event listeners
function setupEventListeners() {
    // Filtri
    document.getElementById('searchInput').addEventListener('input', debounce(applyFilters, 300));
    document.getElementById('statusFilter').addEventListener('change', applyFilters);
    document.getElementById('categoryFilter').addEventListener('change', applyFilters);
    document.getElementById('discountFilter').addEventListener('change', applyFilters);
    
    // Form promozione
    document.getElementById('promotionForm').addEventListener('submit', handlePromotionSubmit);
    document.getElementById('saleStartDate').addEventListener('change', validateDates);
    document.getElementById('saleEndDate').addEventListener('change', validateDates);
    
    // Gestione tipo di sconto
    document.getElementById('discountType').addEventListener('change', handleDiscountTypeChange);
    document.getElementById('salePrice').addEventListener('input', handleSalePriceChange);
    

    
    // Import form
    document.getElementById('importForm').addEventListener('submit', handleImportSubmit);
}

// Caricamento promozioni
function loadPromotions(page = 1) {
    currentPage = page;
    showLoading();
    
    const params = new URLSearchParams({
        page: page,
        ...currentFilters
    });
    
    fetch(`api/pharma-products/promotions.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayPromotions(data.promotions || []);
                updatePagination(data.total_pages || 1, page);
                totalPages = data.total_pages || 1;
            } else {
                showError('Errore nel caricamento delle promozioni: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Errore:', error);
            showError('Errore di connessione');
        })
        .finally(() => {
            hideLoading();
        });
}

// Visualizzazione promozioni in layout a schede
function displayPromotions(promotions) {
    const grid = document.getElementById('promotionsGrid');
    
    if (!promotions || promotions.length === 0) {
        grid.innerHTML = `
            <div class="col-12">
                <div class="empty-state">
                    <i class="fas fa-tags"></i>
                    <h4>Nessuna promozione trovata</h4>
                    <p>Non ci sono promozioni attive al momento. Crea la tua prima promozione!</p>
                    <button class="btn btn-primary" onclick="showAddPromotionModal()">
                        <i class="fas fa-plus me-1"></i> Crea Promozione
                    </button>
                </div>
            </div>
        `;
        return;
    }
    
    grid.innerHTML = promotions.map(promotion => createPromotionCard(promotion)).join('');
}

// Creazione scheda promozione
function createPromotionCard(promotion) {
    const now = new Date();
    const _s = promotion.sale_start_date ? String(promotion.sale_start_date).trim() : '';
    const startDate = _s ? new Date((_s.includes('T') ? _s : _s.replace(' ', 'T')).slice(0,19)) : null;

    const _e = promotion.sale_end_date ? String(promotion.sale_end_date).trim() : '';
    const _eIso = _e ? (_e.includes('T') ? _e : _e.replace(' ', 'T')) : '';
    const endDate = _eIso
  ? new Date((_eIso.length === 10 ? (_eIso + 'T23:59:59') : _eIso).slice(0,19))
  : null;

    
    // Calcolo stato promozione
    let status = 'inactive';
    let statusClass = 'status-inactive';
    let statusText = 'Inattiva';
    
    if (promotion.is_on_sale == 1) {
        if (now < startDate) {
            status = 'upcoming';
            statusClass = 'status-upcoming';
            statusText = 'In arrivo';
        } else if (now >= startDate && now <= endDate) {
            status = 'active';
            statusClass = 'status-active';
            statusText = 'Attiva';
        } else {
            status = 'expired';
            statusClass = 'status-expired';
            statusText = 'Scaduta';
        }
    }
    
    // Calcolo sconto
    const originalPrice = parseFloat(promotion.price);
    const salePrice = parseFloat(promotion.sale_price || 0);
    const discount = salePrice > 0 ? Math.round(((originalPrice - salePrice) / originalPrice) * 100) : 0;
    
    // Calcolo tempo rimanente (solo giorni)
    const timeRemaining = calculateTimeRemaining(endDate);
    const progressPercentage = calculateProgressPercentage(startDate, endDate);
    
    // Immagine prodotto con gestione errori migliorata
    const productImage = promotion.image || promotion.global_image || 'images/default-product.png';
    
    return `
        <div class="col-lg-4 col-md-6 col-sm-12">
            <div class="card promotion-card">
                <div class="card-header">
                    <h5>
                        <i class="fas fa-tag me-2"></i>
                        ${promotion.name}
                    </h5>
                    <span class="status-badge ${statusClass}">${statusText}</span>
                </div>
                <div class="card-body">
                    <div class="product-image-container">
                        <img src="${productImage}" alt="${promotion.name}" class="product-image" 
                             onerror="this.onerror=null; this.src='images/default-product.png'; this.style.opacity='0.7';"
                             onload="this.style.opacity='1';">
                    </div>
                    
                    <div class="product-info">
                        <div class="product-name">${promotion.name}</div>
                        <div class="product-category">
                            <i class="fas fa-layer-group me-1"></i>
                            ${promotion.category || 'generico'}
                        </div>
                        <div class="product-brand">
                            <i class="fas fa-building me-1"></i>
                            ${promotion.brand || 'generico'}
                        </div>
                    </div>
                    
                    <div class="price-section">
                        <span class="original-price">€${originalPrice.toFixed(2)}</span>
                        <span class="sale-price">€${salePrice.toFixed(2)}</span>
                        <span class="discount-badge">-${discount}%</span>
                    </div>
                    
                    <div class="promotion-dates">
                        <div class="date-item">
                            <span class="date-label">Inizio:</span>
                            <span class="date-value">${formatDate(startDate)}</span>
                        </div>
                        <div class="date-item">
                            <span class="date-label">Fine:</span>
                            <span class="date-value">${formatDate(endDate)}</span>
                        </div>
                    </div>
                    
                    ${status === 'active' ? `
                        <div class="time-remaining">
                            <small class="text-muted">Tempo rimanente: ${timeRemaining}</small>
                            <div class="progress mt-1">
                                <div class="progress-bar ${progressPercentage > 70 ? 'danger' : progressPercentage > 30 ? 'warning' : 'success'}" 
                                     style="width: ${progressPercentage}%"></div>
                            </div>
                        </div>
                    ` : ''}
                    
                    <div class="card-actions">
                        <button class="btn btn-action btn-edit" onclick="editPromotion(${promotion.id})">
                            <i class="fas fa-edit"></i> Modifica
                        </button>
                        <button class="btn btn-action btn-toggle" onclick="togglePromotion(${promotion.id}, ${promotion.is_on_sale})">
                            <i class="fas fa-${promotion.is_on_sale == 1 ? 'pause' : 'play'}"></i> 
                            ${promotion.is_on_sale == 1 ? 'Disattiva' : 'Attiva'}
                        </button>
                        <button class="btn btn-action btn-delete" onclick="deletePromotion(${promotion.id})">
                            <i class="fas fa-trash"></i> Elimina
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
}

// Calcolo tempo rimanente (solo giorni)
function calculateTimeRemaining(endDate) {
    const now = new Date();
    const end = new Date(endDate);
    const diff = end - now;
    
    if (diff <= 0) return 'Scaduta';
    
    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    
    if (days > 0) {
        return `${days} giorni`;
    } else {
        return 'Scade oggi';
    }
}

// Calcolo percentuale progresso (solo giorni)
function calculateProgressPercentage(startDate, endDate) {
    const now = new Date();
    const start = new Date(startDate);
    const end = new Date(endDate);
    
    if (now < start) return 0;
    if (now > end) return 100;
    
    const total = end - start;
    const elapsed = now - start;
    return Math.round((elapsed / total) * 100);
}

// Formattazione data
function formatDate(date) {
    return new Date(date).toLocaleDateString('it-IT', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
}

// Setup ricerca prodotti con autocompletamento
function setupProductSearch() {
    const searchInput = document.getElementById('productSearch');
    const resultsContainer = document.getElementById('productSearchResults');
    const hiddenInput = document.getElementById('productSelect');
    searchInput.addEventListener('focus', function (e) {
        if (searchInput.readOnly) {
            e.preventDefault();
            this.blur();
            return;
        }
    });
    let searchTimeout;
    let selectedIndex = -1;
    let searchResults = [];
    
    // Event listener per input
    searchInput.addEventListener('input', function() {
        const query = this.value.trim();
        
        // Nascondi risultati se query troppo corta
        if (query.length < 2) {
            resultsContainer.style.display = 'none';
            hiddenInput.value = '';
            return;
        }
        
        // Debounce della ricerca
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            searchProducts(query);
        }, 300);
    });
    
    // Event listener per focus
    searchInput.addEventListener('focus', function() {
        const query = this.value.trim();
        if (query.length >= 2) {
            resultsContainer.style.display = 'block';
        }
    });
    
    // Event listener per click fuori
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !resultsContainer.contains(e.target)) {
            resultsContainer.style.display = 'none';
        }
    });
    
    // Event listener per tastiera
    searchInput.addEventListener('keydown', function(e) {
        if (resultsContainer.style.display === 'none') return;
        
        switch(e.key) {
            case 'ArrowDown':
                e.preventDefault();
                selectedIndex = Math.min(selectedIndex + 1, searchResults.length - 1);
                updateSelection();
                break;
            case 'ArrowUp':
                e.preventDefault();
                selectedIndex = Math.max(selectedIndex - 1, -1);
                updateSelection();
                break;
            case 'Enter':
                e.preventDefault();
                if (selectedIndex >= 0 && searchResults[selectedIndex]) {
                    selectProduct(searchResults[selectedIndex]);
                }
                break;
            case 'Escape':
                resultsContainer.style.display = 'none';
                selectedIndex = -1;
                break;
        }
    });
    
    // Funzione per cercare prodotti
    function searchProducts(query) {
        fetch(`api/pharma-products/search.php?q=${encodeURIComponent(query)}&limit=10`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    searchResults = data.products || [];
                    displaySearchResults();
                }
            })
            .catch(error => {
                console.error('Errore ricerca prodotti:', error);
                searchResults = [];
                displaySearchResults();
            });
    }
    
    // Funzione per visualizzare risultati
    function displaySearchResults() {
        if (searchResults.length === 0) {
            resultsContainer.innerHTML = '<div class="product-search-item">Nessun prodotto trovato</div>';
        } else {
            resultsContainer.innerHTML = searchResults.map((product, index) => `
                <div class="product-search-item ${index === selectedIndex ? 'selected' : ''}" 
                     data-product='${JSON.stringify(product)}' 
                     onclick="selectProduct(${JSON.stringify(product).replace(/"/g, '&quot;')})">
                    <div class="product-name">${product.name}</div>
                    <div class="product-details">${product.brand} - ${product.category}</div>
                    <div class="product-price">€ ${product.price}</div>
                </div>
            `).join('');
        }
        resultsContainer.style.display = 'block';
    }
    
    // Funzione per aggiornare selezione
    function updateSelection() {
        const items = resultsContainer.querySelectorAll('.product-search-item');
        items.forEach((item, index) => {
            item.classList.toggle('selected', index === selectedIndex);
        });
    }
    
    // Funzione per selezionare prodotto
    window.selectProduct = function (product) {
        const searchInput = document.getElementById('productSearch');
        const hiddenInput = document.getElementById('productSelect');
        const resultsContainer = document.getElementById('productSearchResults');

        const isEditMode = hiddenInput.dataset.lockProduct === '1';
        if (isEditMode) return;

        const displayText = `${product.name} - ${product.brand || ''} (${product.category || ''})`.trim();
        searchInput.value = displayText;
        hiddenInput.value = product.id;
        
        resultsContainer.style.display = 'none';
        selectedIndex = -1;
        
        // Mostra informazioni prodotto
        showProductInfo(product);
    };
    
    // Funzione per mostrare info prodotto
    function showProductInfo(product) {
        const productInfo = document.getElementById('productInfo');
        const productImage = document.getElementById('productImage');
        const productName = document.getElementById('productName');
        const productDescription = document.getElementById('productDescription');
        const currentPrice = document.getElementById('currentPrice');
        const productCategory = document.getElementById('productCategory');
        const productBrand = document.getElementById('productBrand');
        
        // Imposta immagine
        if (product.image) {
            productImage.src = product.image;
            productImage.onerror = function() {
                this.src = 'assets/images/default-product.png';
            };
        } else {
            productImage.src = 'assets/images/default-product.png';
        }
        
        // Imposta informazioni
        productName.textContent = product.name;
        productDescription.textContent = product.description || '';
        currentPrice.textContent = `€ ${product.price}`;
        productCategory.textContent = product.category || '';
        productBrand.textContent = product.brand || '';
        
        // Mostra sezione info
        productInfo.style.display = 'block';
        
        // Imposta prezzo scontato se vuoto e aggiorna l'icona
        const salePriceInput = document.getElementById('salePrice');
        if (!salePriceInput.value) {
            salePriceInput.value = product.price;
        }
        
        // Assicurati che l'icona sia corretta
        handleDiscountTypeChange();
    }
}





// Gestione cambio tipo di sconto
function handleDiscountTypeChange() {
    const discountType = document.getElementById('discountType').value;
    const priceLabel = document.getElementById('priceLabel');
    const salePriceInput = document.getElementById('salePrice');
    const currentPriceElement = document.getElementById('currentPrice');
    
    // Cambia icona e placeholder
    if (discountType === 'percentage') {
        priceLabel.innerHTML = '<i class="fas fa-percentage"></i>';
        salePriceInput.placeholder = 'Es: 20 per 20% di sconto';
        salePriceInput.step = '0.01';
        salePriceInput.min = '0';
        salePriceInput.max = '100';
    } else {
        priceLabel.innerHTML = '<i class="fas fa-euro-sign"></i>';
        salePriceInput.placeholder = 'Es: 15.50 per €15.50';
        salePriceInput.step = '0.01';
        salePriceInput.min = '0';
        salePriceInput.max = '';
    }
    
    // Ricalcola prezzo scontato se c'è un valore
    if (salePriceInput.value && currentPriceElement.textContent) {
        calculateSalePrice();
    }
}

// Gestione cambio prezzo scontato
function handleSalePriceChange() {
    calculateSalePrice();
}

// Calcolo automatico del prezzo scontato
function calculateSalePrice() {
    const discountType = document.getElementById('discountType').value;
    const salePriceInput = document.getElementById('salePrice');
    const currentPriceElement = document.getElementById('currentPrice');
    
    if (!currentPriceElement.textContent) return;
    
    const currentPrice = parseFloat(currentPriceElement.textContent.replace('€', '').trim());
    const salePriceValue = parseFloat(salePriceInput.value);
    
    if (isNaN(currentPrice) || isNaN(salePriceValue)) return;
    
    let calculatedPrice;
    
    if (discountType === 'percentage') {
        // Calcola prezzo scontato da percentuale
        if (salePriceValue >= 0 && salePriceValue <= 100) {
            calculatedPrice = currentPrice * (1 - salePriceValue / 100);
            // Aggiorna il campo con il prezzo calcolato (solo per display, non per salvataggio)
            salePriceInput.setAttribute('data-calculated-price', calculatedPrice.toFixed(2));
        }
    } else {
        // Importo fisso - il valore inserito è già il prezzo scontato
        calculatedPrice = salePriceValue;
        salePriceInput.removeAttribute('data-calculated-price');
    }
}

// Validazione date
function validateDates() {
    const startDate = new Date(document.getElementById('saleStartDate').value);
    const endDate = new Date(document.getElementById('saleEndDate').value);
    
    if (startDate && endDate && startDate > endDate) {
        document.getElementById('saleEndDate').setCustomValidity('La data di fine deve essere successiva o uguale alla data di inizio');
    } else {
        document.getElementById('saleEndDate').setCustomValidity('');
    }
}

// Applicazione filtri
function applyFilters() {
    currentFilters = {
        search: document.getElementById('searchInput').value,
        promotion_status: document.getElementById('statusFilter').value,
        category: document.getElementById('categoryFilter').value,
        discount_range: document.getElementById('discountFilter').value
    };
    
    loadPromotions(1);
}

// Pulizia filtri
function clearFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('statusFilter').value = '';
    document.getElementById('categoryFilter').value = '';
    document.getElementById('discountFilter').value = '';
    
    currentFilters = {};
    loadPromotions(1);
}

// Caricamento statistiche
function loadStatistics() {
    fetch('api/pharma-products/stats.php')
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text().then(text => {
                if (!text) {
                    throw new Error('Risposta vuota dal server');
                }
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Risposta non valida dal server:', text);
                    throw new Error('Risposta non valida dal server');
                }
            });
        })
        .then(data => {
            if (data.success) {
                document.getElementById('activePromotionsCount').textContent = data.active_promotions || 0;
                document.getElementById('inactivePromotionsCount').textContent = data.inactive_promotions || 0;
                document.getElementById('expiredPromotionsCount').textContent = data.expired_promotions || 0;
                document.getElementById('averageDiscount').textContent = `${data.average_discount || 0}%`;
            } else {
                console.error('Errore API statistiche:', data.message);
                // Imposta valori di default in caso di errore
                document.getElementById('activePromotionsCount').textContent = '0';
                document.getElementById('inactivePromotionsCount').textContent = '0';
                document.getElementById('expiredPromotionsCount').textContent = '0';
                document.getElementById('averageDiscount').textContent = '0%';
            }
        })
        .catch(error => {
            console.error('Errore caricamento statistiche:', error);
            // Imposta valori di default in caso di errore
            document.getElementById('activePromotionsCount').textContent = '0';
            document.getElementById('inactivePromotionsCount').textContent = '0';
            document.getElementById('expiredPromotionsCount').textContent = '0';
            document.getElementById('averageDiscount').textContent = '0%';
        });
}

// Modal promozione
function showAddPromotionModal() {
    document.getElementById('promotionModalLabel').innerHTML = '<i class="fas fa-plus me-1"></i> Nuova Promozione';
    document.getElementById('promotionForm').reset();
    document.getElementById('promotionId').value = '';
    document.getElementById('productSearch').value = '';
    document.getElementById('productSelect').value = '';
    document.getElementById('productInfo').style.display = 'none';
    const searchInput = document.getElementById('productSearch');
    const hiddenInput = document.getElementById('productSelect');
    searchInput.readOnly = false;
    searchInput.classList.remove('is-readonly');
    searchInput.removeAttribute('tabindex');
    hiddenInput.dataset.lockProduct = '0';
    
    // Imposta di default la select a "Importo Fisso" e inizializza l'icona
    document.getElementById('discountType').value = 'amount';
    handleDiscountTypeChange(); // Inizializza l'icona
    
    const modal = new bootstrap.Modal(document.getElementById('promotionModal'));
    modal.show();
}

// Modifica promozione
function editPromotion(id) {
    fetch(`api/pharma-products/get.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const promotion = data.product;
                selectedPromotionId = id;
                
                document.getElementById('promotionModalLabel').innerHTML = '<i class="fas fa-edit me-1"></i> Modifica Promozione';
                document.getElementById('promotionId').value = promotion.id;
                document.getElementById('productSelect').value = promotion.product_id || '';
                
                let discountType = promotion.discount_type || 'amount';
                
                // Imposta il tipo di sconto e aggiorna l'icona
                document.getElementById('discountType').value = discountType;
                handleDiscountTypeChange();
                
                if (discountType === 'percentage') {
                    document.getElementById('salePrice').value = promotion.percentage_discount ?? '';
                } else {
                    document.getElementById('salePrice').value = promotion.sale_price || '';
                }
                
                // Gestione date
                //const startDate = formatDateForInput(promotion.sale_start_date);
                //const endDate = formatDateForInput(promotion.sale_end_date);
                
                document.getElementById('saleStartDate').value = (promotion.sale_start_date || '').slice(0, 10);
                document.getElementById('saleEndDate').value   = (promotion.sale_end_date   || '').slice(0, 10);

                document.getElementById('isOnSale').checked = promotion.is_on_sale == 1;

                const featEl = document.getElementById('isFeatured');
                if (featEl) featEl.checked = (promotion.is_featured == 1);

                
                // Imposta il testo di ricerca con i dati del prodotto
                const productText = `${promotion.name} - ${promotion.brand || ''} (${promotion.category || ''})`.trim();
                document.getElementById('productSearch').value = productText;
                const searchInput = document.getElementById('productSearch');
                const hiddenInput = document.getElementById('productSelect');
                searchInput.readOnly = true;
                searchInput.classList.add('is-readonly');
                searchInput.setAttribute('tabindex', '-1');
                hiddenInput.dataset.lockProduct = '1';

                
                // Mostra le informazioni del prodotto
                showProductInfoInEdit(promotion);
                
                const modal = new bootstrap.Modal(document.getElementById('promotionModal'));
                modal.show();
            } else {
                showError('Errore nel caricamento della promozione');
            }
        })
        .catch(error => {
            console.error('Errore:', error);
            showError('Errore di connessione');
        });
}

// Funzione per mostrare info prodotto in modalità modifica
function showProductInfoInEdit(product) {
    const productInfo = document.getElementById('productInfo');
    const productImage = document.getElementById('productImage');
    const productName = document.getElementById('productName');
    const productDescription = document.getElementById('productDescription');
    const currentPrice = document.getElementById('currentPrice');
    const productCategory = document.getElementById('productCategory');
    const productBrand = document.getElementById('productBrand');
    
    // Imposta immagine
    if (product.image) {
        productImage.src = product.image;
        productImage.onerror = function() {
            this.src = 'assets/images/default-product.png';
        };
    } else {
        productImage.src = 'assets/images/default-product.png';
    }
    
    // Imposta informazioni
    productName.textContent = product.name;
    productDescription.textContent = product.description || '';
    currentPrice.textContent = `€ ${product.price}`;
    productCategory.textContent = product.category || '';
    productBrand.textContent = product.brand || '';
    
    // Mostra sezione info
    productInfo.style.display = 'block';
}

// Formattazione date per input HTML (formato ISO)
function formatDateForInput(dateString) {
    if (!dateString || dateString === 'null' || dateString === 'NULL') {
        return '';
    }
    
    try {
        let date;
        
        // Se la data è nel formato italiano "DD/MM/YYYY, HH:MM"
        if (typeof dateString === 'string' && dateString.includes('/')) {
            const parts = dateString.split(',')[0].split('/');
            
            if (parts.length === 3) {
                // Formato: DD/MM/YYYY
                const day = parseInt(parts[0]);
                const month = parseInt(parts[1]) - 1; // month - 1 perché i mesi in JS sono 0-based
                const year = parseInt(parts[2]);
                date = new Date(year, month, day);
            } else {
                date = new Date(dateString);
            }
        } else {
            date = new Date(dateString);
        }
        
        if (isNaN(date.getTime())) {
            return '';
        }
        
        return date.toISOString().slice(0, 10);
    } catch (error) {
        return '';
    }
}

function readDateInput(id) {
  const el = document.getElementById(id);
  if (!el) return '';
  const v = (el.value || '').trim();
  return /^\d{4}-\d{2}-\d{2}$/.test(v) ? v : '';
}


// Gestione submit form
function handlePromotionSubmit(e) {
  e.preventDefault();

  const formData = new FormData(e.target); 
  const promotionId = formData.get('id');
  const discountType = formData.get('discount_type');

  const sd = readDateInput('saleStartDate');
  const ed = readDateInput('saleEndDate');

  const sdDT = sd ? `${sd} 00:00:00` : '';
  const edDT = ed ? `${ed} 23:59:59` : '';

  if (sdDT) formData.set('sale_start_date', sdDT); else formData.delete('sale_start_date');
  if (edDT) formData.set('sale_end_date', edDT);   else formData.delete('sale_end_date');

  if (discountType === 'percentage') {
    const currentPriceEl = document.getElementById('currentPrice');
    const currentPrice = currentPriceEl ? parseFloat(currentPriceEl.textContent.replace('€','').trim()) : NaN;
    let perc = parseFloat(document.getElementById('salePrice').value);

    if (!isNaN(currentPrice) && !isNaN(perc) && perc >= 0 && perc <= 100) {
      perc = parseFloat(perc.toFixed(2));
      const calculated = parseFloat((currentPrice * (1 - perc / 100)).toFixed(2));

      formData.set('sale_price', calculated.toFixed(2));
      formData.set('percentage_discount', perc);
    }
  } else {
    formData.set('percentage_discount', '');
  }

  formData.set('is_on_sale', document.getElementById('isOnSale')?.checked ? '1' : '0');
  formData.set('is_featured', document.getElementById('isFeatured')?.checked ? '1' : '0');

   const url = promotionId ? 
        `api/pharma-products/update.php` : 
        `api/pharma-products/create-promotion.php`;

  fetch(url, { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showSuccess(promotionId ? 'Promozione aggiornata con successo!' : 'Promozione creata con successo!');
        bootstrap.Modal.getInstance(document.getElementById('promotionModal')).hide();
        loadPromotions(currentPage);
        loadStatistics();
      } else {
        showError('Errore: ' + data.message);
      }
    })
    .catch(err => {
      console.error('Errore:', err);
      showError('Errore di connessione');
    });
}

// Toggle promozione
function togglePromotion(id, currentStatus) {
    const newStatus = currentStatus == 1 ? 0 : 1;
    const action = newStatus == 1 ? 'attivare' : 'disattivare';
    
    if (confirm(`Sei sicuro di voler ${action} questa promozione?`)) {
        const formData = new FormData();
        formData.append('id', id);
        formData.append('is_on_sale', newStatus);
        
        fetch('api/pharma-products/update.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showSuccess(`Promozione ${newStatus == 1 ? 'attivata' : 'disattivata'} con successo!`);
                loadPromotions(currentPage);
                loadStatistics();
            } else {
                showError('Errore: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Errore:', error);
            showError('Errore di connessione');
        });
    }
}

// Eliminazione promozione
function deletePromotion(id) {
    selectedPromotionId = id;
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}

// Conferma eliminazione
function confirmDelete() {
    if (!selectedPromotionId) return;
    
    fetch('api/pharma-products/delete-promotion.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ id: selectedPromotionId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess('Promozione eliminata con successo!');
            bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
            loadPromotions(currentPage);
            loadStatistics();
        } else {
            showError('Errore: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Errore:', error);
        showError('Errore di connessione');
    });
}

// Export promozioni
function exportPromotions() {
    const params = new URLSearchParams(currentFilters);
    window.open(`api/pharma-products/export-promotions.php?${params}`, '_blank');
}

// Import promozioni
function showImportModal() {
    const modal = new bootstrap.Modal(document.getElementById('importModal'));
    modal.show();
}

// Gestione import
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
            showSuccess(`Import completato! ${data.imported || 0} promozioni importate.`);
            bootstrap.Modal.getInstance(document.getElementById('importModal')).hide();
            loadPromotions(currentPage);
            loadStatistics();
        } else {
            showError('Errore import: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Errore:', error);
        showError('Errore di connessione');
    });
}

// Download template
function downloadTemplate() {
    window.open('api/pharma-products/template.php', '_blank');
}

// Paginazione
function updatePagination(totalPages, currentPage) {
    const pagination = document.getElementById('pagination');
    pagination.innerHTML = '';
    
    if (totalPages <= 1) return;
    
    // Pagina precedente
    if (currentPage > 1) {
        pagination.innerHTML += `
            <li class="page-item">
                <a class="page-link" href="#" onclick="loadPromotions(${currentPage - 1})">
                    <i class="fas fa-chevron-left"></i>
                </a>
            </li>
        `;
    }
    
    // Pagine
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
            pagination.innerHTML += `
                <li class="page-item ${i === currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="loadPromotions(${i})">${i}</a>
                </li>
            `;
        } else if (i === currentPage - 3 || i === currentPage + 3) {
            pagination.innerHTML += `
                <li class="page-item disabled">
                    <span class="page-link">...</span>
                </li>
            `;
        }
    }
    
    // Pagina successiva
    if (currentPage < totalPages) {
        pagination.innerHTML += `
            <li class="page-item">
                <a class="page-link" href="#" onclick="loadPromotions(${currentPage + 1})">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </li>
        `;
    }
}

// Utility functions
function showLoading() {
    const grid = document.getElementById('promotionsGrid');
    grid.innerHTML = `
        <div class="col-12">
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Caricamento...</span>
                </div>
                <p class="mt-2">Caricamento promozioni...</p>
            </div>
        </div>
    `;
}

function hideLoading() {
    // Loading viene gestito dal displayPromotions
}

function showSuccess(message) {
    showAlert(message, 'success');
}

function showError(message) {
    showAlert(message, 'danger');
}

function showAlert(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    const container = document.querySelector('main');
    container.insertBefore(alertDiv, container.firstChild);
    
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
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