<?php
/**
 * Fichier de confirmation d'envoi d'email
 * Ce fichier peut être utilisé pour tester l'envoi d'email indépendamment
 */

// Inclure PHPMailer manuellement
require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailTester {
    private $smtp_host;
    private $smtp_username;
    private $smtp_password;
    private $smtp_port;
    private $from_email;
    private $from_name;

    public function __construct($config) {
        $this->smtp_host = $config['smtp_host'];
        $this->smtp_username = $config['smtp_username'];
        $this->smtp_password = $config['smtp_password'];
        $this->smtp_port = $config['smtp_port'];
        $this->from_email = $config['from_email'];
        $this->from_name = $config['from_name'];
    }

    public function testEmail($to_email, $to_name = '') {
        try {
            $mail = new PHPMailer(true);

            // Configuration du serveur SMTP
            $mail->isSMTP();
            $mail->Host = $this->smtp_host;
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtp_username;
            $mail->Password = $this->smtp_password;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $this->smtp_port;

            // Encodage
            $mail->CharSet = 'UTF-8';

            // Destinataires
            $mail->setFrom($this->from_email, $this->from_name);
            $mail->addAddress($to_email, $to_name);
            $mail->addReplyTo($this->from_email, $this->from_name);

            // Contenu
            $mail->isHTML(true);
            $mail->Subject = "Test d'envoi d'email - Votre Hôtel";
            $mail->Body = $this->buildTestEmailContent();
            $mail->AltBody = $this->buildTestTextEmailContent();

            $mail->send();
            return ['success' => true, 'message' => 'Email de test envoyé avec succès'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Erreur PHPMailer: ' . $e->getMessage()];
        }
    }

    private function buildTestEmailContent() {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Test d'envoi d'email</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #2c3e50; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { background: #f9f9f9; padding: 20px; }
                .footer { background: #34495e; color: white; padding: 15px; text-align: center; font-size: 12px; border-radius: 0 0 5px 5px; }
                .success { color: #27ae60; font-weight: bold; text-align: center; font-size: 1.2em; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>Test d'Envoi d'Email</h1>
            </div>
            <div class='content'>
                <p class='success'>✅ Test réussi !</p>
                <p>Félicitations ! Votre configuration d'envoi d'email fonctionne correctement.</p>
                <p>Les emails de confirmation de réservation seront maintenant envoyés automatiquement à vos clients.</p>
                <p><strong>Détails de la configuration :</strong></p>
                <ul>
                    <li>Serveur SMTP: " . $this->smtp_host . "</li>
                    <li>Port: " . $this->smtp_port . "</li>
                    <li>Expéditeur: " . $this->from_email . "</li>
                </ul>
            </div>
            <div class='footer'>
                <p><strong>Votre Hôtel - Système de Réservation</strong></p>
                <p>Email automatique - " . date('d/m/Y H:i:s') . "</p>
            </div>
        </body>
        </html>";
    }

    private function buildTestTextEmailContent() {
        return "TEST D'ENVOI D'EMAIL\n\n" .
               "Félicitations ! Votre configuration d'envoi d'email fonctionne correctement.\n\n" .
               "Les emails de confirmation de réservation seront maintenant envoyés automatiquement à vos clients.\n\n" .
               "Détails de la configuration :\n" .
               "- Serveur SMTP: " . $this->smtp_host . "\n" .
               "- Port: " . $this->smtp_port . "\n" .
               "- Expéditeur: " . $this->from_email . "\n\n" .
               "Votre Hôtel - " . date('d/m/Y H:i:s');
    }
}

// Configuration SMTP - À MODIFIER AVEC VOS PARAMÈTRES
$config = [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_username' => 'votre.email@gmail.com',
    'smtp_password' => 'votre_mot_de_passe_app',
    'smtp_port' => 587,
    'from_email' => 'noreply@votrehotel.com',
    'from_name' => 'Votre Hôtel'
];

// Si le formulaire est soumis pour tester l'email
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    if (!$email) {
        echo json_encode(['success' => false, 'error' => 'Adresse email invalide']);
        exit;
    }

    $tester = new EmailTester($config);
    $result = $tester->testEmail($email);
    echo json_encode($result);
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test d'envoi d'email - Votre Hôtel</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 20px; background: #f4f4f4; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; text-align: center; margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #2c3e50; }
        input[type="email"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 16px; }
        button { background: #2c3e50; color: white; padding: 12px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; width: 100%; }
        button:hover { background: #34495e; }
        .result { margin-top: 20px; padding: 15px; border-radius: 5px; display: none; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .config { background: #e9ecef; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .config h3 { margin-top: 0; color: #2c3e50; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Test d'envoi d'email</h1>
        
        <div class="config">
            <h3>Configuration SMTP actuelle :</h3>
            <p><strong>Serveur :</strong> <?php echo htmlspecialchars($config['smtp_host']); ?></p>
            <p><strong>Port :</strong> <?php echo htmlspecialchars($config['smtp_port']); ?></p>
            <p><strong>Expéditeur :</strong> <?php echo htmlspecialchars($config['from_email']); ?></p>
        </div>

        <form id="testEmailForm">
            <div class="form-group">
                <label for="email">Adresse email de test :</label>
                <input type="email" id="email" name="email" required placeholder="votre.email@exemple.com">
            </div>
            <button type="submit">Tester l'envoi d'email</button>
        </form>

        <div id="result" class="result"></div>
    </div>

    <script>
        document.getElementById('testEmailForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const resultDiv = document.getElementById('result');
            
            fetch('confirmation.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.className = 'result success';
                    resultDiv.innerHTML = '✅ ' + data.message;
                } else {
                    resultDiv.className = 'result error';
                    resultDiv.innerHTML = '❌ ' + data.error;
                }
                resultDiv.style.display = 'block';
            })
            .catch(error => {
                resultDiv.className = 'result error';
                resultDiv.innerHTML = '❌ Erreur: ' + error;
                resultDiv.style.display = 'block';
            });
        });
    </script>
</body>
</html>