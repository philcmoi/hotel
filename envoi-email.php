<?php
/**
 * Fichier séparé pour l'envoi d'emails avec PHPMailer
 */

// Inclure PHPMailer manuellement
require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailSender {
    // Configuration SMTP - À MODIFIER AVEC VOS PARAMÈTRES
    private $smtp_host = 'smtp.gmail.com';
    private $smtp_username = 'lhpp.philippe@gmail.com';
    private $smtp_password = 'l@99339RWFH546542052372';
    private $smtp_port = 587;
    private $from_email = 'lhpp.philippe@gmail.com';
    private $from_name = 'Votre Hôtel';

    /**
     * Envoie un email de confirmation de réservation
     */
    public function sendReservationConfirmation($reservation_id, $client_email, $db_conn) {
        try {
            // Récupérer les détails de la réservation
            $reservation_details = $this->getReservationDetails($reservation_id, $db_conn);
            
            if (!$reservation_details) {
                error_log("Détails de réservation non trouvés pour l'email: " . $reservation_id);
                return false;
            }

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
            $mail->addAddress($client_email, $reservation_details['client']['prenom'] . ' ' . $reservation_details['client']['nom']);
            $mail->addReplyTo($this->from_email, $this->from_name);

            // Contenu
            $mail->isHTML(true);
            $mail->Subject = "Confirmation de votre réservation - Hôtel";
            $mail->Body = $this->buildEmailContent($reservation_id, $reservation_details);
            $mail->AltBody = $this->buildTextEmailContent($reservation_id, $reservation_details);

            $mail->send();
            error_log("Email envoyé avec PHPMailer pour la réservation: " . $reservation_id);
            return true;
            
        } catch (Exception $e) {
            error_log("Erreur PHPMailer: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère les détails d'une réservation depuis la base de données
     */
    private function getReservationDetails($reservation_id, $conn) {
        try {
            // Récupérer les informations de base de la réservation et du client
            $query = "SELECT r.*, cl.nom, cl.prenom, cl.email, cl.telephone 
                      FROM reservations r
                      INNER JOIN clients cl ON r.idClient = cl.idClient
                      WHERE r.idReservation = :reservation_id";
            
            $stmt = $conn->prepare($query);
            $stmt->execute([':reservation_id' => $reservation_id]);
            $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reservation) {
                return null;
            }

            // Récupérer les chambres de la réservation
            $chambres_query = "SELECT c.* 
                              FROM chambres c
                              INNER JOIN reservation_chambres rc ON c.idChambre = rc.idChambre
                              WHERE rc.idReservation = :reservation_id";
            
            $chambres_stmt = $conn->prepare($chambres_query);
            $chambres_stmt->execute([':reservation_id' => $reservation_id]);
            $chambres = $chambres_stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'reservation' => $reservation,
                'client' => [
                    'nom' => $reservation['nom'],
                    'prenom' => $reservation['prenom'],
                    'email' => $reservation['email'],
                    'telephone' => $reservation['telephone']
                ],
                'chambres' => $chambres
            ];
            
        } catch (Exception $e) {
            error_log("Erreur getReservationDetails: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Construit le contenu HTML de l'email
     */
    private function buildEmailContent($reservation_id, $reservation_details) {
        $arrivee = new DateTime($reservation_details['reservation']['date_arrivee']);
        $depart = new DateTime($reservation_details['reservation']['date_depart']);
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
                .status-pending { color: #e67e22; font-weight: bold; }
                .total-price { font-size: 1.2em; font-weight: bold; color: #2c3e50; border-top: 2px solid #2c3e50; padding-top: 10px; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>Confirmation de Réservation</h1>
            </div>
            <div class='content'>
                <p>Bonjour <strong>" . htmlspecialchars($reservation_details['client']['prenom'] . " " . $reservation_details['client']['nom']) . "</strong>,</p>
                <p>Votre réservation a bien été enregistrée. Voici le détail :</p>
                
                <div class='reservation-details'>
                    <div class='detail-item'><span class='detail-label'>Numéro de réservation :</span> #" . $reservation_id . "</div>
                    <div class='detail-item'><span class='detail-label'>Date d'arrivée :</span> " . $arrivee->format('d/m/Y') . "</div>
                    <div class='detail-item'><span class='detail-label'>Date de départ :</span> " . $depart->format('d/m/Y') . "</div>
                    <div class='detail-item'><span class='detail-label'>Nombre de nuits :</span> " . $nights . "</div>
                    <div class='detail-item'><span class='detail-label'>Nombre de personnes :</span> " . $reservation_details['reservation']['nombre_personnes'] . "</div>
                    <div class='detail-item total-price'><span class='detail-label'>Prix total :</span> " . number_format($reservation_details['reservation']['prix_total'], 2, ',', ' ') . " €</div>
                </div>
                
                <h3>Chambres réservées :</h3>
                <ul class='chambre-list'>";
        
        foreach ($reservation_details['chambres'] as $chambre) {
            $content .= "<li class='chambre-item'>Chambre " . $chambre['numeroChambre'] . " - " . $chambre['type_chambre'] . " (" . number_format($chambre['prix_nuit'], 2, ',', ' ') . " €/nuit)</li>";
        }
        
        $content .= "
                </ul>
                
                <div class='detail-item'>
                    <span class='detail-label'>Statut :</span> 
                    <span class='status-pending'>En attente de confirmation</span>
                </div>
                
                <p>Nous vous contacterons prochainement pour confirmer définitivement votre réservation.</p>
                <p>Pour toute question, n'hésitez pas à nous contacter.</p>
                <p>Merci de votre confiance !</p>
            </div>
            <div class='footer'>
                <p><strong>Votre Hôtel</strong></p>
                <p>Tél: +33 1 23 45 67 89 | Email: contact@votrehotel.com</p>
                <p>Adresse: 123 Avenue de l'Hôtel, 75000 Paris</p>
            </div>
        </body>
        </html>";

        return $content;
    }

    /**
     * Construit le contenu texte de l'email
     */
    private function buildTextEmailContent($reservation_id, $reservation_details) {
        $arrivee = new DateTime($reservation_details['reservation']['date_arrivee']);
        $depart = new DateTime($reservation_details['reservation']['date_depart']);
        $nights = $depart->diff($arrivee)->days;
        
        $text = "CONFIRMATION DE RÉSERVATION\n\n";
        $text .= "Bonjour " . $reservation_details['client']['prenom'] . " " . $reservation_details['client']['nom'] . ",\n\n";
        $text .= "Votre réservation a bien été enregistrée. Voici le détail :\n\n";
        $text .= "Numéro de réservation: #" . $reservation_id . "\n";
        $text .= "Date d'arrivée: " . $arrivee->format('d/m/Y') . "\n";
        $text .= "Date de départ: " . $depart->format('d/m/Y') . "\n";
        $text .= "Nombre de nuits: " . $nights . "\n";
        $text .= "Nombre de personnes: " . $reservation_details['reservation']['nombre_personnes'] . "\n";
        $text .= "Prix total: " . number_format($reservation_details['reservation']['prix_total'], 2, ',', ' ') . " €\n\n";
        
        $text .= "Chambres réservées:\n";
        foreach ($reservation_details['chambres'] as $chambre) {
            $text .= "- Chambre " . $chambre['numeroChambre'] . " (" . $chambre['type_chambre'] . ") - " . number_format($chambre['prix_nuit'], 2, ',', ' ') . " €/nuit\n";
        }
        
        $text .= "\nStatut: En attente de confirmation\n\n";
        $text .= "Nous vous contacterons prochainement pour confirmer définitivement votre réservation.\n\n";
        $text .= "Merci de votre confiance !\n\n";
        $text .= "Votre Hôtel\n";
        $text .= "Tél: +33 1 23 45 67 89\n";
        $text .= "Email: contact@votrehotel.com\n";
        
        return $text;
    }
}
?>