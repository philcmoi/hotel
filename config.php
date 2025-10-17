<?php
// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'hotel');
define('DB_USER', 'root');
define('DB_PASS', '');
define('APP_NAME', 'Hôtel Premium');

// Configuration SMTP pour emails
define('SMTP_HOST', 'smtp.hostinger.com'); // ou smtp.ovh.net pour OVH
define('SMTP_USER', 'noreply@votredomaine.com');
define('SMTP_PASS', 'votre_mot_de_passe_smtp');
define('SMTP_SECURE', 'tls'); // ou 'ssl' pour OVH
define('SMTP_PORT', 587); // ou 465 pour OVH avec SSL
define('EMAIL_FROM', 'noreply@votredomaine.com');
define('EMAIL_FROM_NAME', 'Hôtel Premium');

// Configuration de l'application
define('SITE_URL', 'http://localhost/votre-site'); // URL de votre site
?>