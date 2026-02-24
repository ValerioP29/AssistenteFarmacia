<?php
/**
 * Script per creare/modificare utente admin
 * Assistente Farmacia Panel
 */

// Carica configurazione
require_once 'config/database.php';

// Password dell'admin (MODIFICA QUESTA PASSWORD)
$admin_password = 'admin123'; // Cambia questa password con quella desiderata

// Dati dell'utente admin
$admin_data = [
    'slug_name' => 'admin',
    'password' => password_hash($admin_password, PASSWORD_DEFAULT),
    'name' => 'Amministratore',
    'surname' => 'Sistema',
    'email' => 'admin@assistentefarmacia.it',
    'phone_number' => '',
    'role' => 'admin',
    'status' => 'active',
    'starred_pharma' => 1, // farmacia di default
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s')
];

try {
    // Verifica se l'utente admin esiste già
    $existing_admin = db_fetch_one("SELECT id FROM jta_users WHERE slug_name = ? AND status != 'deleted'", ['admin']);
    
    if ($existing_admin) {
        // Aggiorna la password e i dati dell'admin esistente
        $result = db()->update('jta_users', 
            [
                'password' => $admin_data['password'],
                'email' => $admin_data['email'],
                'phone_number' => $admin_data['phone_number'],
                'role' => $admin_data['role'],
                'status' => $admin_data['status'],
                'updated_at' => $admin_data['updated_at']
            ], 
            'id = ?', 
            [$existing_admin['id']]
        );
        
        if ($result > 0) {
            echo "✅ Utente admin aggiornato con successo!\n";
            echo "Username: admin\n";
            echo "Password: {$admin_password}\n";
            echo "Email: {$admin_data['email']}\n";
            echo "Telefono: " . ($admin_data['phone_number'] ?: 'Non impostato') . "\n";
            echo "Ruolo: {$admin_data['role']}\n";
        } else {
            echo "❌ Errore nell'aggiornamento dell'utente admin\n";
        }
    } else {
        // Crea nuovo utente admin
        $admin_id = db()->insert('jta_users', $admin_data);
        
        if ($admin_id) {
            echo "✅ Utente admin creato con successo!\n";
            echo "ID: {$admin_id}\n";
            echo "Username: admin\n";
            echo "Password: {$admin_password}\n";
            echo "Email: {$admin_data['email']}\n";
            echo "Telefono: " . ($admin_data['phone_number'] ?: 'Non impostato') . "\n";
            echo "Nome: {$admin_data['name']} {$admin_data['surname']}\n";
            echo "Ruolo: {$admin_data['role']}\n";
        } else {
            echo "❌ Errore nella creazione dell'utente admin\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Errore: " . $e->getMessage() . "\n";
    echo "Verifica che:\n";
    echo "1. Il database sia configurato correttamente\n";
    echo "2. La tabella jta_users esista\n";
    echo "3. Le credenziali del database siano corrette\n";
}

echo "\n🔐 Per sicurezza, ricorda di:\n";
echo "1. Cambiare la password dopo il primo accesso\n";
echo "2. Rimuovere o proteggere questo script dopo l'uso\n";
echo "3. Utilizzare una password forte in produzione\n";
?> 