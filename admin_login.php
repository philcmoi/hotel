<?php
// admin_login.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// SIMULATION - Remplacez par votre vraie logique d'authentification
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

// Exemple simple - À REMPLACER par votre vérification en base de données
$valid_username = 'admin';
$valid_password = 'admin123';

if ($username === $valid_username && $password === $valid_password) {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_username'] = $username;
    $_SESSION['login_time'] = time();
    
    // Régénérer l'ID de session pour la sécurité
    session_regenerate_id(true);
    
    error_log("=== LOGIN SUCCESS ===");
    error_log("Session ID: " . session_id());
    error_log("Session data: " . print_r($_SESSION, true));
    
    echo json_encode([
        'success' => true,
        'message' => 'Connexion réussie',
        'redirect' => 'dashboard.html'
    ]);
} else {
    error_log("=== LOGIN FAILED ===");
    echo json_encode([
        'success' => false,
        'error' => 'Identifiants incorrects'
    ]);
}
?>