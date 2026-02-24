<?php
/**
 * Index - Reindirizzamento
 * Assistente Farmacia Panel
 */

require_once 'config/database.php';
require_once 'includes/functions.php';
session_start();

if (isLoggedIn()) {
    $user_role = $_SESSION['user_role'] ?? 'user';
    
    if ($user_role === 'admin') {
        header('Location: utenti.php');
    } else {
        header('Location: dashboard.php');
    }
    exit;
} else {
    header('Location: login.php');
    exit;
}
?> 