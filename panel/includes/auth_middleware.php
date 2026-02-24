<?php
/**
 * Middleware per l'autenticazione e controllo accessi
 * Assistente Farmacia Panel
 */

// Avvia sessione se non già avviata
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Carica configurazione e funzioni se non già caricate
if (!function_exists('isLoggedIn')) {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/functions.php';
}

/**
 * Middleware per proteggere le pagine web
 * @param string|array $required_roles Ruoli richiesti per accedere
 * @param bool $redirect Se true, reindirizza alla login in caso di accesso negato
 * @return bool True se l'utente ha i permessi
 */
function requireAuth($required_roles = ['admin'], $redirect = true) {
    return checkAccess($required_roles, $redirect);
}

/**
 * Middleware per proteggere le API
 * @param string|array $required_roles Ruoli richiesti per accedere
 * @return bool True se l'utente ha i permessi
 */
function requireApiAuth($required_roles = ['admin']) {
    return checkApiAccess($required_roles);
}

/**
 * Middleware per pagine che richiedono solo autenticazione (qualsiasi ruolo)
 */
function requireLogin() {
    return checkAccess(['admin', 'pharmacist', 'user'], true);
}

/**
 * Middleware per pagine admin-only
 */
function requireAdmin() {
    return checkAccess(['admin'], true);
}

/**
 * Middleware per pagine farmacista o admin
 */
function requirePharmacistOrAdmin() {
    return checkAccess(['admin', 'pharmacist'], true);
}

/**
 * Middleware per pagine admin-only (alias per requireAdmin)
 */
function checkAdminAccess() {
    return checkAccess(['admin'], true);
}



/**
 * Controlla se l'utente può accedere a una risorsa specifica
 * @param string $resource Nome della risorsa
 * @param int $resource_id ID della risorsa (opzionale)
 * @return bool True se l'utente ha accesso
 */
function canAccessResource($resource, $resource_id = null) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $user_role = $_SESSION['user_role'] ?? 'user';
    $user_id = $_SESSION['user_id'] ?? null;
    
    // Admin può accedere a tutto
    if ($user_role === 'admin') {
        return true;
    }
    
    // Controlli specifici per risorsa
    switch ($resource) {
        case 'pharmacy':
            // Farmacisti possono accedere solo alla loro farmacia
            if ($user_role === 'pharmacist') {
                $user_pharmacy = $_SESSION['pharmacy_id'] ?? null;
                return $resource_id ? ($user_pharmacy == $resource_id) : true;
            }
            break;
            
        case 'user':
            // Utenti possono accedere solo ai propri dati
            if ($user_role === 'user') {
                return $resource_id ? ($user_id == $resource_id) : false;
            }
            // Farmacisti possono vedere utenti della loro farmacia
            if ($user_role === 'pharmacist') {
                // Implementazione specifica per farmacisti
                return true; // Per ora permette tutto ai farmacisti
            }
            break;
            
        case 'product':
            // Farmacisti possono gestire prodotti della loro farmacia
            if ($user_role === 'pharmacist') {
                return true; // Per ora permette tutto ai farmacisti
            }
            break;
            
        case 'booking':
            // Farmacisti possono vedere prenotazioni della loro farmacia
            if ($user_role === 'pharmacist') {
                return true; // Per ora permette tutto ai farmacisti
            }
            // Utenti possono vedere solo le proprie prenotazioni
            if ($user_role === 'user') {
                return $resource_id ? true : false; // Implementazione specifica
            }
            break;
    }
    
    return false;
}

/**
 * Logga tentativo di accesso non autorizzato
 * @param string $resource Risorsa richiesta
 * @param string $action Azione tentata
 */
function logUnauthorizedAccess($resource, $action = 'access') {
    $user_id = $_SESSION['user_id'] ?? 'guest';
    $user_role = $_SESSION['user_role'] ?? 'none';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $request_uri = $_SERVER['REQUEST_URI'] ?? 'unknown';
    
    $details = [
        'resource' => $resource,
        'action' => $action,
        'user_role' => $user_role,
        'ip' => $ip,
        'user_agent' => $user_agent,
        'request_uri' => $request_uri
    ];
    
    logActivity('unauthorized_access', $details, $user_id);
}

/**
 * Gestisce accesso negato per pagine web
 * @param string $resource Nome della risorsa richiesta
 * @param string $action Azione tentata
 */
function handleUnauthorizedAccess($resource = 'pagina', $action = 'accesso') {
    logUnauthorizedAccess($resource, $action);
    
    if (isLoggedIn()) {
        setAlert("Non hai i permessi per {$action} a questa {$resource}. Contatta l'amministratore se ritieni che questo sia un errore.", 'danger');
        redirect('dashboard.php'); // Reindirizza alla dashboard se già loggato
    } else {
        // Salva la pagina corrente per il redirect dopo il login
        saveReturnUrl();
        
        setAlert("Devi effettuare il login per {$action} a questa {$resource}.", 'warning');
        redirect('login.php');
    }
}

/**
 * Gestisce accesso negato per API
 * @param string $resource Nome della risorsa richiesta
 * @param string $action Azione tentata
 */
function handleUnauthorizedApiAccess($resource = 'risorsa', $action = 'accedere') {
    logUnauthorizedAccess($resource, $action);
    
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode([
            'success' => false, 
            'message' => "Autenticazione richiesta per {$action} a questa {$resource}"
        ]);
    } else {
        http_response_code(403);
        echo json_encode([
            'success' => false, 
            'message' => "Non hai i permessi per {$action} a questa {$resource}"
        ]);
    }
    exit;
}
?> 