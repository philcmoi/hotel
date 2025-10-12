<?php
// Démarrer la session une seule fois
/*if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION['admin_logged_in'] = true; // TEMPORAIRE - à retirer après test


// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'hotel');
define('DB_USER', 'root');
define('DB_PASS', '');

// Configuration de l'application
define('APP_NAME', 'Hotel Administration');
define('SESSION_TIMEOUT', 3600); // 1 heure

// Fonction de redirection
function redirect($url) {
    header("Location: $url");
    exit;
}

// Vérifier si l'utilisateur est connecté
function isLoggedIn() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['last_activity'])) {
        return false;
    }
    
    // Vérifier le timeout de session
    if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        return false;
    }
    
    $_SESSION['last_activity'] = time();
    return true;
}

// Forcer l'authentification avec gestion AJAX
function requireAuth() {
    if (!isLoggedIn()) {
        if (isAjaxRequest()) {
            // Pour les requêtes AJAX, retourner une erreur JSON
            header('HTTP/1.1 401 Unauthorized');
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Authentification requise', 'redirect' => 'login.php']);
            exit;
        } else {
            // Pour les requêtes normales, rediriger
            redirect('login.php');
        }
    }
}

// Vérifier si c'est une requête AJAX
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// Vérifier l'authentification pour l'API
function checkAPIAuth() {
    if (!isLoggedIn()) {
        if (isAjaxRequest()) {
            header('HTTP/1.1 401 Unauthorized');
            echo json_encode(['success' => false, 'error' => 'Non authentifié', 'redirect' => 'login.php']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Authentification requise']);
        }
        exit;
    }
    return true;
}*/


// config.php - Remplacez avec vos vraies informations
// config.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'hotel');        // Votre base de données
define('DB_USER', 'root');         // Votre utilisateur MySQL
define('DB_PASS', '');   
?>