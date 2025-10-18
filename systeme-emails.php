<?php
/**
 * SYSTEME D'ENVOI D'EMAILS PROFESSIONNEL
 * Fichier : systeme-emails.php
 * Fonction : Envoi d'emails de confirmation avec mode test et mode production
 * Auteur : Assistant IA
 * Date : " . date('d/m/Y') . "
 */

// Inclure PHPMailer
require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailSystem {
    private $pdo;
    private $test_mode;
    
    // Configuration SMTP (À MODIFIER AVEC VOS PARAMÈTRES)
    private $smtp_config = [
        'host' => 'smtp.gmail.com',
        'username' => 'lhpp.philippe@gmail.com',
        'password' => 'l@99339RWFH546542052372', // Mot de passe d'application Gmail
        'port' => 587,
        'from_email' => 'lhpp.philippe@gmail.com',
        'from_name' => 'Votre Hôtel'
    ];

    public function __construct($pdo, $test_mode = true) {
        $this->pdo = $pdo;
        $this->test_mode = $test_mode;
    }

    /**
     * Envoie ou affiche l'email de confirmation
     */
    public function sendConfirmationEmail($reservation_id) {
        try {
            // Récupération des données complètes
            $reservation_data = $this->getReservationData($reservation_id);
            
            if (!$reservation_data) {
                throw new Exception("Réservation non trouvée: " . $reservation_id);
            }

            // Construction du lien de confirmation
            $lienConfirmation = $this->buildConfirmationLink($reservation_data, $reservation_id);
            
            if ($this->test_mode) {
                // MODE TEST : Affichage à l'écran
                return $this->displayTestEmail($reservation_data, $lienConfirmation);
            } else {
                // MODE PRODUCTION : Envoi réel par email
                return $this->sendRealEmail($reservation_data, $lienConfirmation);
            }
            
        } catch (Exception $e) {
            error_log("Erreur EmailSystem: " . $e->getMessage());
            return "❌ Erreur: " . $e->getMessage();
        }
    }

    /**
     * Récupère les données complètes de la réservation
     */
    private function getReservationData($reservation_id) {
        $sql = "SELECT 
                    c.nom, c.prenom, c.email, c.telephone,
                    r.idReservation, r.date_arrivee, r.date_depart, 
                    r.nombre_personnes, r.prix_total, r.etat_reservation,
                    ch.type_chambre, ch.numeroChambre, ch.prix_nuit, ch.capacite,
                    r.commentaire
                FROM reservations r
                JOIN clients c ON r.idClient = c.idClient
                JOIN reservation_chambres rc ON r.idReservation = rc.idReservation
                JOIN chambres ch ON rc.idChambre = ch.idChambre
                WHERE r.idReservation = :idReservation";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['idReservation' => $reservation_id]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($results)) {
            return null;
        }

        // Structurer les données
        $first_row = $results[0];
        $chambres = [];
        foreach ($results as $row) {
            $chambres[] = [
                'numeroChambre' => $row['numeroChambre'],
                'type_chambre' => $row['type_chambre'],
                'prix_nuit' => $row['prix_nuit'],
                'capacite' => $row['capacite']
            ];
        }

        return [
            'client' => [
                'nom' => $first_row['nom'],
                'prenom' => $first_row['prenom'],
                'email' => $first_row['email'],
                'telephone' => $first_row['telephone']
            ],
            'reservation' => [
                'idReservation' => $first_row['idReservation'],
                'date_arrivee' => $first_row['date_arrivee'],
                'date_depart' => $first_row['date_depart'],
                'nombre_personnes' => $first_row['nombre_personnes'],
                'prix_total' => $first_row['prix_total'],
                'etat_reservation' => $first_row['etat_reservation'],
                'commentaire' => $first_row['commentaire']
            ],
            'chambres' => $chambres
        ];
    }

    /**
     * Construit le lien de confirmation
     */
    private function buildConfirmationLink($reservation_data, $reservation_id) {
        $queryParams = http_build_query([
            'nom' => $reservation_data['client']['nom'],
            'prenom' => $reservation_data['client']['prenom'],
            'email' => $reservation_data['client']['email'],
            'idReservation' => $reservation_id,
            'confirme' => 'true'
        ]);
        
        return "http://localhost/hotel/confirmation.php?$queryParams";
    }

    /**
     * Affiche l'email en mode test
     */
    private function displayTestEmail($reservation_data, $lienConfirmation) {
        $output = "<h2>📧 EMAIL DE CONFIRMATION (MODE TEST)</h2>";
        $output .= "<p><strong>Destinataire :</strong> {$reservation_data['client']['email']}</p>";
        $output .= "<p><strong>Client :</strong> {$reservation_data['client']['prenom']} {$reservation_data['client']['nom']}</p>";
        $output .= "<p><strong>Lien de confirmation :</strong> <a href='$lienConfirmation' target='_blank'>$lienConfirmation</a></p>";
        $output .= "<hr>";
        
        // Contenu HTML de l'email
        $emailContent = $this->buildEmailContent($reservation_data, $lienConfirmation);
        $output .= "<h3>Contenu HTML de l'email :</h3>";
        $output .= "<div style='border:1px solid #ccc; padding:20px; margin:10px 0;'>";
        $output .= $emailContent;
        $output .= "</div>";
        
        // Contenu texte de l'email
        $textContent = $this->buildTextEmailContent($reservation_data, $lienConfirmation);
        $output .= "<h3>Contenu texte de l'email :</h3>";
        $output .= "<pre style='border:1px solid #ccc; padding:20px; margin:10px 0; white-space: pre-wrap;'>";
        $output .= htmlspecialchars($textContent);
        $output .= "</pre>";
        
        // Mettre à jour le statut en mode test aussi
        $this->updateReservationStatus($reservation_data['reservation']['idReservation']);
        $output .= "<p style='color:green; margin-top:20px;'>✅ Réservation marquée comme confirmée dans la base de données</p>";
        
        return $output;
    }

    /**
     * Envoie l'email en mode production
     */
    private function sendRealEmail($reservation_data, $lienConfirmation) {
        try {
            $mail = new PHPMailer(true);

            // Configuration SMTP
            $mail->isSMTP();
            $mail->Host = $this->smtp_config['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtp_config['username'];
            $mail->Password = $this->smtp_config['password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $this->smtp_config['port'];
            $mail->CharSet = 'UTF-8';

            // Destinataires
            $mail->setFrom($this->smtp_config['from_email'], $this->smtp_config['from_name']);
            $mail->addAddress($reservation_data['client']['email'], $reservation_data['client']['prenom'] . ' ' . $reservation_data['client']['nom']);
            $mail->addReplyTo($this->smtp_config['from_email'], $this->smtp_config['from_name']);

            // Contenu
            $mail->isHTML(true);
            $mail->Subject = "Confirmation de votre réservation - Hôtel";
            $mail->Body = $this->buildEmailContent($reservation_data, $lienConfirmation);
            $mail->AltBody = $this->buildTextEmailContent($reservation_data, $lienConfirmation);

            $mail->send();
            
            // Mettre à jour le statut
            $this->updateReservationStatus($reservation_data['reservation']['idReservation']);
            
            return "✅ Email envoyé avec succès à {$reservation_data['client']['email']}";
            
        } catch (Exception $e) {
            throw new Exception("Erreur d'envoi email: " . $e->getMessage());
        }
    }

    /**
     * Construit le contenu HTML de l'email
     */
    private function buildEmailContent($reservation_data, $lienConfirmation) {
        $arrivee = new DateTime($reservation_data['reservation']['date_arrivee']);
        $depart = new DateTime($reservation_data['reservation']['date_depart']);
        $nights = $depart->diff($arrivee)->days;
        
        $content = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Confirmation de réservation</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #2c3e50; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { background: #f9f9f9; padding: 20px; }
                .footer { background: #34495e; color: white; padding: 15px; text-align: center; font-size: 12px; border-radius: 0 0 5px 5px; }
                .reservation-details { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #2c3e50; }
                .detail-item { margin: 10px 0; padding: 5px 0; }
                .detail-label { font-weight: bold; color: #2c3e50; min-width: 150px; display: inline-block; }
                .chambre-list { list-style: none; padding: 0; }
                .chambre-item { background: #ecf0f1; margin: 5px 0; padding: 10px; border-radius: 3px; }
                .confirmation-btn { background: #27ae60; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 15px 0; }
                .total-price { font-size: 1.2em; font-weight: bold; color: #2c3e50; border-top: 2px solid #2c3e50; padding-top: 10px; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>Confirmation de Réservation</h1>
            </div>
            <div class='content'>
                <p>Bonjour <strong>" . htmlspecialchars($reservation_data['client']['prenom'] . " " . $reservation_data['client']['nom']) . "</strong>,</p>
                <p>Votre réservation a bien été enregistrée. Voici le détail :</p>
                
                <div class='reservation-details'>
                    <div class='detail-item'><span class='detail-label'>Numéro de réservation :</span> #" . $reservation_data['reservation']['idReservation'] . "</div>
                    <div class='detail-item'><span class='detail-label'>Date d'arrivée :</span> " . $arrivee->format('d/m/Y') . "</div>
                    <div class='detail-item'><span class='detail-label'>Date de départ :</span> " . $depart->format('d/m/Y') . "</div>
                    <div class='detail-item'><span class='detail-label'>Nombre de nuits :</span> " . $nights . "</div>
                    <div class='detail-item'><span class='detail-label'>Nombre de personnes :</span> " . $reservation_data['reservation']['nombre_personnes'] . "</div>
                    <div class='detail-item total-price'><span class='detail-label'>Prix total :</span> " . number_format($reservation_data['reservation']['prix_total'], 2, ',', ' ') . " €</div>
                </div>
                
                <h3>Chambres réservées :</h3>
                <ul class='chambre-list'>";
        
        foreach ($reservation_data['chambres'] as $chambre) {
            $content .= "<li class='chambre-item'>Chambre " . $chambre['numeroChambre'] . " - " . $chambre['type_chambre'] . " (" . number_format($chambre['prix_nuit'], 2, ',', ' ') . " €/nuit) - Capacité: " . $chambre['capacite'] . " personnes</li>";
        }
        
        $content .= "
                </ul>
                
                <div style='text-align: center; margin: 25px 0;'>
                    <a href='$lienConfirmation' class='confirmation-btn'>✅ Confirmer ma réservation</a>
                </div>
                
                <p><strong>Instructions :</strong> Veuillez cliquer sur le bouton ci-dessus pour confirmer définitivement votre réservation.</p>
                
                <p>Pour toute question, n'hésitez pas à nous contacter.</p>
                <p>Merci de votre confiance !</p>
            </div>
            <div class='footer'>
                <p><strong>Votre Hôtel</strong></p>
                <p>Tél: +33 1 23 45 67 89 | Email: " . $this->smtp_config['from_email'] . "</p>
                <p>Adresse: 123 Avenue de l'Hôtel, 75000 Paris</p>
            </div>
        </body>
        </html>";

        return $content;
    }

    /**
     * Construit le contenu texte de l'email
     */
    private function buildTextEmailContent($reservation_data, $lienConfirmation) {
        $arrivee = new DateTime($reservation_data['reservation']['date_arrivee']);
        $depart = new DateTime($reservation_data['reservation']['date_depart']);
        $nights = $depart->diff($arrivee)->days;
        
        $text = "CONFIRMATION DE RÉSERVATION\n\n";
        $text .= "Bonjour " . $reservation_data['client']['prenom'] . " " . $reservation_data['client']['nom'] . ",\n\n";
        $text .= "Votre réservation a bien été enregistrée. Voici le détail :\n\n";
        $text .= "Numéro de réservation: #" . $reservation_data['reservation']['idReservation'] . "\n";
        $text .= "Date d'arrivée: " . $arrivee->format('d/m/Y') . "\n";
        $text .= "Date de départ: " . $depart->format('d/m/Y') . "\n";
        $text .= "Nombre de nuits: " . $nights . "\n";
        $text .= "Nombre de personnes: " . $reservation_data['reservation']['nombre_personnes'] . "\n";
        $text .= "Prix total: " . number_format($reservation_data['reservation']['prix_total'], 2, ',', ' ') . " €\n\n";
        
        $text .= "Chambres réservées:\n";
        foreach ($reservation_data['chambres'] as $chambre) {
            $text .= "- Chambre " . $chambre['numeroChambre'] . " (" . $chambre['type_chambre'] . ") - " . number_format($chambre['prix_nuit'], 2, ',', ' ') . " €/nuit - Capacité: " . $chambre['capacite'] . " personnes\n";
        }
        
        $text .= "\nLien de confirmation: " . $lienConfirmation . "\n\n";
        $text .= "Instructions: Veuillez cliquer sur le lien ci-dessus pour confirmer définitivement votre réservation.\n\n";
        $text .= "Pour toute question, n'hésitez pas à nous contacter.\n\n";
        $text .= "Merci de votre confiance !\n\n";
        $text .= "Votre Hôtel\n";
        $text .= "Tél: +33 1 23 45 67 89\n";
        $text .= "Email: " . $this->smtp_config['from_email'] . "\n";
        
        return $text;
    }

    /**
     * Met à jour le statut de la réservation
     */
    private function updateReservationStatus($reservation_id) {
        $updateSql = "UPDATE reservations SET etat_reservation = 'confirme' WHERE idReservation = :idReservation";
        $updateStmt = $this->pdo->prepare($updateSql);
        $updateStmt->execute(['idReservation' => $reservation_id]);
    }
}

// =========================================================
// PARTIE PRINCIPALE - EXÉCUTION DU SCRIPT
// =========================================================

echo "<!DOCTYPE html>
<html>
<head>
    <title>Système d'emails - Hôtel</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .header { background: #2c3e50; color: white; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .info-box { background: #e8f4fd; border-left: 4px solid #2196F3; padding: 15px; margin: 15px 0; }
        .success { background: #e8f5e8; border-left: 4px solid #4CAF50; padding: 15px; margin: 15px 0; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>🏨 Système d'emails de confirmation</h1>
            <p>Envoi d'emails de confirmation de réservation</p>
        </div>";

try {
    // =========================================================
    // CONFIGURATION - À MODIFIER SELON VOTRE ENVIRONNEMENT
    // =========================================================
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "hotel";
    
    // MODE D'EXÉCUTION (true = test, false = production)
    $test_mode = true;
    
    // ID DE RÉSERVATION À TRAITER
    $reservation_id = 1;

    // =========================================================
    // EXÉCUTION DU SYSTÈME
    // =========================================================
    
    // Connexion à la base de données
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ATTR_ERRMODE_EXCEPTION);
    
    // Information sur le mode
    if ($test_mode) {
        echo "<div class='warning'>
                <h3>🔧 MODE TEST ACTIVÉ</h3>
                <p>Les emails sont affichés à l'écran mais aucun email réel n'est envoyé.</p>
                <p>Pour passer en mode production, changez <strong>\$test_mode = false;</strong></p>
              </div>";
    } else {
        echo "<div class='info-box'>
                <h3>🚀 MODE PRODUCTION ACTIVÉ</h3>
                <p>Les emails sont envoyés pour de vrai aux clients.</p>
                <p><strong>Assurez-vous que la configuration SMTP est correcte !</strong></p>
              </div>";
    }
    
    echo "<div class='info-box'>
            <h3>📋 Détails de l'exécution</h3>
            <p><strong>Réservation ID :</strong> $reservation_id</p>
            <p><strong>Base de données :</strong> $dbname</p>
            <p><strong>Serveur :</strong> $servername</p>
          </div>";

    // Création et utilisation du système d'email
    $emailSystem = new EmailSystem($pdo, $test_mode);
    $result = $emailSystem->sendConfirmationEmail($reservation_id);
    
    // Affichage du résultat
    echo $result;
    
} catch(PDOException $e) {
    echo "<div style='color: red; padding: 15px; background: #ffe6e6; border-radius: 5px;'>
            <h3>❌ Erreur de connexion à la base de données</h3>
            <p><strong>Message :</strong> " . $e->getMessage() . "</p>
            <p>Vérifiez les paramètres de connexion dans le fichier.</p>
          </div>";
} catch(Exception $e) {
    echo "<div style='color: red; padding: 15px; background: #ffe6e6; border-radius: 5px;'>
            <h3>❌ Erreur générale</h3>
            <p><strong>Message :</strong> " . $e->getMessage() . "</p>
          </div>";
}

echo "</div></body></html>";
?>