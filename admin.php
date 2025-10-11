
<?php
require_once 'config.php';

// Vérifier l'authentification
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Afficher l'interface d'administration
readfile('_admin.html');
?>