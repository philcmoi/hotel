<?php
// config-email.php - Configuration pour l'envoi d'emails

// Configuration SMTP (à adapter selon votre hébergeur)
define('SMTP_HOST', 'smtp.hostinger.com'); // ou 'ssl0.ovh.net' pour OVH
define('SMTP_PORT', 465);
define('SMTP_USERNAME', 'contact@votredomaine.com');
define('SMTP_PASSWORD', 'votre_mot_de_passe_email');
define('SMTP_SECURE', 'ssl'); // 'ssl' ou 'tls'

// Emails de l'hôtel
define('HOTEL_EMAIL_FROM', 'reservations@votredomaine.com');
define('HOTEL_EMAIL_REPLY_TO', 'contact@votredomaine.com');
define('HOTEL_NAME', 'Hôtel Luxury');

// URLs
define('HOTEL_WEBSITE', 'https://votredomaine.com');
define('CONFIRMATION_PAGE', 'https://votredomaine.com/confirmation.php');

// Configuration pour PHPMailer
require_once 'vendor/autoload.php'; // Si vous utilisez Composer
// Ou inclure manuellement PHPMailer si pas de Composer
?>