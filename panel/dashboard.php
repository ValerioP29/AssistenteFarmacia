<?php
/**
 * Dashboard Principale
 * Assistente Farmacia Panel
 */

// Carica configurazione e middleware PRIMA di qualsiasi output
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth_middleware.php';

// Controllo accesso: solo admin e farmacisti (DEVE essere prima di qualsiasi output)
requirePharmacistOrAdmin();

// Include header (che carica la configurazione)
require_once 'includes/header.php';

// Imposta variabili per il template
$current_page = 'dashboard';

// Imposta titolo dinamico basato sul ruolo
if (isAdmin()) {
    $page_title = 'Pannello Amministrativo';
    $page_description = 'Gestione Sistema Multi-Farmacia';
} else {
    $page_title = htmlspecialchars($pharmacy['nice_name'] ?? 'Dashboard Farmacia');
    $page_description = 'Pannello di controllo farmacia';
}

// Ottieni statistiche
$stats = getDashboardStats();
$chartData = getChartData(30);

// Ottieni farmacia corrente
$pharmacy = getCurrentPharmacy();
$isOpen = isPharmacyOpen();
$nextOpening = getNextOpeningTime();
?>


<div class="container-fluid">
    <div class="row">
        <?php require_once 'includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <?php if (isset($_SESSION['login_as']) && $_SESSION['login_as']): ?>
                <!-- Indicatore modalità accesso come -->
                <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-user-secret me-2"></i>
                        <div>
                            <strong>Modalità Accesso come Farmacia</strong>
                            <br><small>Stai operando come: <?= htmlspecialchars($_SESSION['user_name'] ?? 'Farmacia') ?></small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Header Dashboard -->
            <div class="d-flex justify-content-between flex-column align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0 d-flex gap-2 align-items-center">
                        <i class="fas fa-tachometer-alt"></i> 
                        <?php if (isAdmin()): ?>
                            Dashboard Amministrativa
                        <?php else: ?>
                            Dashboard
                        <?php endif; ?>
                    </h1>
                    <p class="text-muted mb-0 text-center">
                        Benvenuto, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Utente') ?>!
                        <?php if (isAdmin()): ?>
                            <br><small>Panoramica completa di tutte le farmacie del sistema</small>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="text-center">
                    <small class="text-muted">
                        <i class="fas fa-clock"></i> 
                        Ultimo aggiornamento: <?= date('d/m/Y H:i') ?>
                    </small>
                </div>
            </div>



            <!-- Statistiche Cards -->
            <div id="card_container" class="d-flex flex-wrap justify-content-center align-items-center gap-3 mb-4">
                <?php if (isAdmin()): ?>
                    <!-- KPI per Admin -->
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-boxes mb-2 text-primary"></i>
                            <h5 class="card-title mt-2"><?= number_format($stats['global_products']) ?></h5>
                            <p class="card-text">Prodotti Globali</p>
                        </div>
                    </div>

                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-check-circle mb-2 text-success"></i>
                            <h5 class="card-title mt-2"><?= number_format($stats['completed_requests']) ?></h5>
                            <p class="card-text">Richieste Completate</p>
                        </div>
                    </div>

                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-clock mb-2 text-warning"></i>
                            <h5 class="card-title mt-2"><?= number_format($stats['pending_requests']) ?></h5>
                            <p class="card-text">Richieste in Corso</p>
                        </div>
                    </div>

                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-users mb-2 text-info"></i>
                            <h5 class="card-title mt-2"><?= number_format($stats['total_users']) ?></h5>
                            <p class="card-text">Utenti Totali</p>
                        </div>
                    </div>

                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-clinic-medical mb-2 text-secondary"></i>
                            <h5 class="card-title mt-2"><?= number_format($stats['total_pharmacies']) ?></h5>
                            <p class="card-text">Farmacie Totali</p>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- KPI per Farmacisti -->
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-users mb-2 text-primary"></i>
                            <h5 class="card-title mt-2"><?= number_format($stats['customers']) ?></h5>
                            <p class="card-text">Numero di clienti</p>
                        </div>
                    </div>

                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-clock mb-2 text-warning"></i>
                            <h5 class="card-title mt-2"><?= number_format($stats['pending_requests']) ?></h5>
                            <p class="card-text">Richieste in corso</p>
                        </div>
                    </div>

                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-check-circle mb-2 text-success"></i>
                            <h5 class="card-title mt-2"><?= number_format($stats['completed_requests']) ?></h5>
                            <p class="card-text">Richieste completate</p>
                        </div>
                    </div>

                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-pills mb-2 text-info"></i>
                            <h5 class="card-title mt-2"><?= number_format($stats['products']) ?></h5>
                            <p class="card-text">Prodotti in catalogo</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Grafico Prenotazioni -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0 text-center">
                        <i class="fas fa-chart-line d-block m-2"></i> 
                        <?php if (isAdmin()): ?>
                            Prenotazioni Sistema (Ultimi 30 Giorni)
                        <?php else: ?>
                            Prenotazioni <br> Ultimi 30 Giorni
                        <?php endif; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="myChart" height="100"></canvas>
                </div>
            </div>

            <!-- Informazioni Rapide - Solo per farmacisti -->
            <?php if (!isAdmin()): ?>
            <div class="mt-4">
                <div class=" mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="card-title mb-0 text-center p-1">
                                <i class="fas fa-info-circle"></i> Informazioni Farmacia
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($pharmacy['logo'])): ?>
                                <div class="text-center mb-3">
                                    <img src="<?= h($pharmacy['logo']) ?>" alt="Logo <?= h($pharmacy['nice_name']) ?>" 
                                         class="pharmacy-logo" style="height: 80px; width: auto; max-width: 200px; object-fit: contain; border-radius: 8px;">
                                </div>
                            <?php endif; ?>
                            <ul>
                                <li class="mb-2">
                                    <strong>Nome:</strong> <?= htmlspecialchars($pharmacy['nice_name'] ?? 'N/A') ?>
                                </li>
                                <li class="mb-2">
                                    <strong>Indirizzo:</strong> <?= htmlspecialchars($pharmacy['address'] ?? 'N/A') ?>
                                </li>
                                <li class="mb-2">
                                    <strong>Città:</strong> <?= htmlspecialchars($pharmacy['city'] ?? 'N/A') ?>
                                </li>
                                <li class="mb-2">
                                    <strong>Telefono:</strong> 
                                    <?php if ($pharmacy['phone_number']): ?>
                                        <a href="tel:<?= $pharmacy['phone_number'] ?>">
                                            <?= htmlspecialchars($pharmacy['phone_number']) ?>
                                        </a>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </li>
                                <li class="mb-2">
                                    <strong>Email:</strong> 
                                    <?php if ($pharmacy['email']): ?>
                                        <a href="mailto:<?= $pharmacy['email'] ?>">
                                            <?= htmlspecialchars($pharmacy['email']) ?>
                                        </a>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div>
                    <div class="card">
                        <div class="card-header">
                            <h6 class="card-title text-center p-1">
                                <i class="fas fa-clock"></i> Orari di Apertura
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php 
                            $hours = getPharmacyHours();
                            $formattedHours = formatWorkingHours($hours);
                            ?>
                            <?php if ($formattedHours): ?>
                                <ul>
                                    <?php foreach ($formattedHours as $day => $time): ?>
                                        <li class="mb-1 d-flex gap-2 align-items-center mb-2">
                                            <span><?= $day ?>:</span>
                                            <strong><?= $time ?></strong>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted mb-0">Orari non disponibili</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<link rel="stylesheet" href="assets/css/dashboard.css">

<!-- JavaScript per il grafico -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Dati del grafico
    const chartData = <?= json_encode($chartData) ?>;
    
    // Configurazione grafico
    const ctx = document.getElementById('myChart').getContext('2d');
    const myChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartData.labels,
            datasets: [{
                label: 'Richieste Totali',
                data: chartData.total_requests || chartData.values,
                borderColor: 'rgb(52, 152, 219)',
                backgroundColor: 'rgba(52, 152, 219, 0.1)',
                borderWidth: 2,
                fill: false,
                tension: 0.4,
                pointBackgroundColor: 'rgb(52, 152, 219)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            },
            {
                label: 'Richieste Completate',
                data: chartData.completed_requests || [],
                borderColor: 'rgb(46, 204, 113)',
                backgroundColor: 'rgba(46, 204, 113, 0.1)',
                borderWidth: 2,
                fill: false,
                tension: 0.4,
                pointBackgroundColor: 'rgb(46, 204, 113)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20,
                        font: {
                            size: 12
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    borderColor: 'rgb(52, 152, 219)',
                    borderWidth: 1,
                    cornerRadius: 6,
                    displayColors: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)'
                    },
                    ticks: {
                        stepSize: 1
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            }
        }
    });

    // Aggiorna grafico ogni 5 minuti
    setInterval(function() {
        fetch('api/dashboard/chart-data.php')
            .then(response => response.json())
            .then(data => {
                myChart.data.labels = data.labels;
                myChart.data.datasets[0].data = data.total_requests || data.values;
                myChart.data.datasets[1].data = data.completed_requests || [];
                myChart.update('none');
            })
            .catch(error => {
                console.error('Errore aggiornamento grafico:', error);
            });
    }, 300000); // 5 minuti

    // Aggiorna statistiche ogni minuto
    setInterval(function() {
        fetch('api/dashboard/stats.php')
            .then(response => response.json())
            .then(data => {
                // Aggiorna i numeri nelle card
                const cardTitles = document.querySelectorAll('#card_container .card-title');
                cardTitles.forEach((title, index) => {
                    let value;
                    if (data.global_products !== undefined) {
                        // Admin dashboard
                        const adminValues = [data.global_products, data.completed_requests, data.pending_requests, data.total_users, data.total_pharmacies];
                        value = adminValues[index];
                    } else {
                        // Farmacista dashboard
                        const farmacistValues = [data.customers, data.pending_requests, data.completed_requests, data.products];
                        value = farmacistValues[index];
                    }
                    
                    if (value !== undefined) {
                        title.textContent = new Intl.NumberFormat('it-IT').format(value);
                    }
                });
            })
            .catch(error => {
                console.error('Errore aggiornamento statistiche:', error);
            });
    }, 60000); // 1 minuto
});
</script>

<?php
// Include footer
require_once 'includes/footer.php';
?> 