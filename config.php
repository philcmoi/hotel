<?php
// config.php - FICHIER DE CONFIGURATION SEULEMENT

// Configuration de la session - AVANT session_start()
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 0);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Lax');
}

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'hotel');
define('DB_USER', 'root');
define('DB_PASS', '');
define('APP_NAME', 'Hôtel Premium');
// NE PAS METTRE DE CODE DE CONNEXION OU AUTHENTIFICATION ICI
?>