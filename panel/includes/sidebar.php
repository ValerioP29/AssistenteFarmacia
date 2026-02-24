<?php
/**
 * Sidebar di Navigazione
 * Assistente Farmacia Panel
 */

$current_page = $current_page ?? 'dashboard';
$user_role = $_SESSION['user_role'] ?? 'user';
$is_login_as = isset($_SESSION['login_as']) && $_SESSION['login_as'];

$menu_items = [
    'dashboard' => ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard', 'url' => 'dashboard.php', 'roles' => ['admin', 'pharmacist']],
    'utenti' => ['icon' => 'fas fa-users', 'label' => 'Gestione Utenti', 'url' => 'utenti.php', 'roles' => ['admin']],
    'farmacie' => ['icon' => 'fas fa-clinic-medical', 'label' => 'Gestione Farmacie', 'url' => 'farmacie.php', 'roles' => ['admin']],
    'prodotti_globali' => ['icon' => 'fas fa-boxes', 'label' => 'Gestione Prodotti', 'url' => 'prodotti_globali.php', 'roles' => ['admin']],
    'richieste' => ['icon' => 'fas fa-calendar-check', 'label' => 'Richieste', 'url' => 'richieste.php', 'roles' => ['pharmacist']],
    'prodotti' => ['icon' => 'fas fa-boxes', 'label' => 'Gestione Prodotti', 'url' => 'prodotti.php', 'roles' => ['pharmacist']],
    'promozioni' => ['icon' => 'fas fa-tags', 'label' => 'Gestione Promozioni', 'url' => 'promozioni.php', 'roles' => ['pharmacist']],
    'orari' => ['icon' => 'fas fa-clock', 'label' => 'Modifica Orari', 'url' => 'orari.php', 'roles' => ['pharmacist']],
    'whatsapp' => ['icon' => 'fab fa-whatsapp', 'label' => 'WhatsApp', 'url' => 'whatsapp.php', 'roles' => ['pharmacist']],
    'profilo' => ['icon' => 'fas fa-user-cog', 'label' => 'Profilo', 'url' => 'profilo.php', 'roles' => ['pharmacist']],
];

// Filtra menu items in base al ruolo
$visible_menu_items = array_filter($menu_items, function($item) use ($user_role) {
    return in_array($user_role, $item['roles']);
});
?>


<div class="sidebar-left">
    <?php if ($is_login_as): ?>
        <!-- Indicatore modalità accesso come -->
        <div class="login-as-indicator">
            <div class="alert alert-info mb-3 mx-2">
                <i class="fas fa-user-secret me-2"></i>
                <strong>Accesso come Farmacia</strong>
                <br><small>Stai operando come: <?= htmlspecialchars($_SESSION['user_name'] ?? 'Farmacia') ?></small>
            </div>
        </div>
    <?php elseif (isPharmacist() && !isAdmin()): ?>
        <!-- Logo e nome farmacia -->
        <?php 
        $pharmacy = getCurrentPharmacy();
        if ($pharmacy): 
        ?>
            <div class="pharmacy-info mb-3 mx-2">
                <div class="card border-0 bg-transparent">
                    <div class="card-body text-center p-3">
                        <?php if (!empty($pharmacy['logo'])): ?>
                            <img src="<?= h($pharmacy['logo']) ?>" alt="Logo <?= h($pharmacy['nice_name']) ?>" 
                                 class="pharmacy-logo mb-2" style="height: 60px; width: auto; max-width: 150px; object-fit: contain; border-radius: 8px;">
                        <?php else: ?>
                            <div class="pharmacy-logo-placeholder mb-2 d-flex align-items-center justify-content-center" 
                                 style="height: 60px; width: 150px; background: rgba(255,255,255,0.1); border-radius: 8px;">
                                <i class="fas fa-clinic-medical fa-2x text-light"></i>
                            </div>
                        <?php endif; ?>
                        <h6 class="text-light mb-1"><?= h($pharmacy['nice_name']) ?></h6>
                        <small class="text-light opacity-75"><?= h($pharmacy['city'] ?? '') ?></small>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    
    <ul class="nav nav-pills flex-column mb-auto">
        <?php foreach ($visible_menu_items as $page => $item): ?>
            <li>
                <a href="<?= $item['url'] ?>" class="nav-link <?= $current_page === $page ? 'selected' : '' ?>">
                    <i class="<?= $item['icon'] ?> me-2"></i>
                    <?= $item['label'] ?>
                </a>
            </li>
        <?php endforeach; ?>
        <li><hr class="border-light"></li>
        
        <?php if ($is_login_as): ?>
            <!-- Pulsante per tornare admin -->
            <li>
                <a href="#" class="nav-link return-admin-link" onclick="returnToAdmin()">
                    <i class="fas fa-arrow-left me-2"></i> Torna Admin
                </a>
            </li>
            <li><hr class="border-light"></li>
        <?php endif; ?>
        
        <li><a href="logout.php" class="nav-link exit logout-link"><i class="fas fa-sign-out-alt me-2"></i> Esci</a></li>
    </ul>
</div>



 