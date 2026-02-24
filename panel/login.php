<?php
/**
 * Pagina di Login
 * Assistente Farmacia Panel
 */

// Avvia sessione PRIMA di tutto
session_start();

// Carica configurazione senza header
require_once 'config/database.php';
require_once 'includes/functions.php';

// Gestione login PRIMA di includere header
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // Verifica CSRF token solo se la sessione è attiva
    if (session_status() === PHP_SESSION_ACTIVE && !verifyCSRFToken($csrf_token)) {
        setAlert('Token di sicurezza non valido', 'danger');
    } elseif (empty($username) || empty($password)) {
        setAlert('Inserisci username e password', 'warning');
    } else {
        try {
            // Verifica credenziali
            $sql = "SELECT * FROM jta_users WHERE slug_name = ? AND status != 'deleted'";
            $user = db_fetch_one($sql, [$username]);
            
            if ($user && password_verify($password, $user['password'])) {
                // Login riuscito
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'] ?? 'user';
                $_SESSION['user_name'] = $user['slug_name'];
                $_SESSION['pharmacy_id'] = $user['starred_pharma'] ?? 1;
                

                
                // Log attività
                logActivity('login_success', ['user_id' => $user['id'], 'role' => $user['role']]);
                
                // Aggiorna ultimo accesso
                db()->update('jta_users', 
                    ['last_access' => date('Y-m-d H:i:s')], 
                    'id = ?', 
                    [$user['id']]
                );
                
                // Reindirizzamento dopo login riuscito
                $return_url = getReturnUrl();
                
                // Se c'è un return URL salvato, reindirizza lì
                if ($return_url && $return_url !== '/login.php') {
                    redirect($return_url, 'Login effettuato con successo', 'success');
                } else {
                    // Reindirizzamento di default in base al ruolo
                    $user_role = $user['role'] ?? 'user';
                    switch ($user_role) {
                        case 'admin':
                            redirect('utenti.php', 'Login effettuato con successo', 'success');
                            break;
                        case 'pharmacist':
                            redirect('dashboard.php', 'Login effettuato con successo', 'success');
                            break;
                        case 'user':
                        default:
                            setAlert('Accesso non autorizzato. Solo admin e farmacisti possono accedere al pannello.', 'danger');
                            break;
                    }
                }
            } else {
                // Login fallito
                logActivity('login_failed', ['username' => $username]);
                setAlert('Username o password non corretti', 'danger');
            }
        } catch (Exception $e) {
            setAlert('Errore durante il login', 'danger');
        }
    }
}

// Include header DOPO aver gestito il login
require_once 'includes/header.php';

// Imposta variabili per il template
$page_title = 'Login - ' . APP_NAME;
$page_description = 'Accedi al pannello di amministrazione';
$body_class = 'login-page';
$require_auth = false;
?>

<!-- Login Form -->
<div class="login-container">
    <div class="login-box">
        <div class="login-header">
            <h1 class="login-title">
                <i class="fas fa-pills"></i>
                <?= APP_NAME ?>
            </h1>
            <p class="login-subtitle">Accedi al pannello di amministrazione</p>
        </div>
        
        <form method="POST" class="login-form" id="loginForm">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            
            <div class="form-group">
                <label for="username" class="form-label">
                    <i class="fas fa-user"></i> Username
                </label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    class="form-control" 
                    placeholder="Inserisci il tuo username"
                    required
                    autocomplete="username"
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                >
            </div>

            <div class="form-group">
                <label for="password" class="form-label">
                    <i class="fas fa-lock"></i> Password
                </label>
                <div class="password-input-group">
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-control" 
                        placeholder="Inserisci la tua password"
                        required
                        autocomplete="current-password"
                    >
                    <button type="button" class="password-toggle" onclick="togglePassword()">
                        <i class="fas fa-eye" id="passwordIcon"></i>
                    </button>
                </div>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary btn-login">
                    <i class="fas fa-sign-in-alt"></i>
                    Accedi
                </button>
            </div>
        </form>
        
        <div class="login-footer">
            <p class="text-muted">
                <i class="fas fa-info-circle"></i>
                Accedi con le tue credenziali
            </p>
        </div>
    </div>
</div>

<!-- CSS specifico per login -->
<style>
body.login-page {
    background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
    min-height: 100vh;
    margin: 0;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.login-page .login-container {
    width: 100%;
    max-width: 400px;
    margin: 0 auto;
    padding: 20px;
}

.login-box {
    background: #ffffff;
    border-radius: 0.5rem;
    box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.175);
    padding: 3rem;
    max-width: 350px;
    margin: 0 auto;
}



.login-header {
    text-align: center;
    margin-bottom: 3rem;
}

.login-title {
    color: #2c3e50;
    font-size: 1.5rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
}

.login-title i {
    color: #3498db;
    margin-right: 0.5rem;
}

.login-subtitle {
    color: #6c757d;
    margin: 0;
}

.login-form .form-group {
    margin-bottom: 1.5rem;
}

.login-form .form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
}

.login-form .form-label i {
    margin-right: 0.5rem;
    color: #3498db;
}

.password-input-group {
    position: relative;
}

.password-toggle {
    position: absolute;
    right: 0.5rem;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #6c757d;
    cursor: pointer;
    padding: 0.25rem;
}

.password-toggle:hover {
    color: #3498db;
}

.btn-login {
    width: 100%;
    padding: 1rem;
    font-size: 1.125rem;
    font-weight: 600;
}

.btn-login i {
    margin-right: 0.5rem;
}

.login-footer {
    text-align: center;
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid #e9ecef;
}

.login-footer p {
    margin: 0;
    font-size: var(--font-size-sm);
}

/* Responsive */
@media (max-width: 480px) {
    .login-box {
        padding: var(--spacing-lg);
    }
    
    .login-title {
        font-size: var(--font-size-xl);
    }
}
</style>

<!-- JavaScript per login -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('loginForm');
    const submitBtn = form.querySelector('button[type="submit"]');
    
    form.addEventListener('submit', function(e) {
        // Mostra loading
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Accesso in corso...';
        submitBtn.disabled = true;
    });
});

function togglePassword() {
    const passwordInput = document.getElementById('password');
    const passwordIcon = document.getElementById('passwordIcon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        passwordIcon.className = 'fas fa-eye-slash';
    } else {
        passwordInput.type = 'password';
        passwordIcon.className = 'fas fa-eye';
    }
}

// Auto-focus su username
document.getElementById('username').focus();
</script>

<?php
// Include footer
require_once 'includes/footer.php';
?> 