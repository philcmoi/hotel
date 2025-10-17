<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

class ReservationMailer {
    private $mail;
    
    public function __construct() {
        $this->mail = new PHPMailer(true);
        $this->configureMailer();
    }
    
    private function configureMailer() {
        // Configuration SMTP
        $this->mail->isSMTP();
        $this->mail->Host = SMTP_HOST;
        $this->mail->SMTPAuth = true;
        $this->mail->Username = SMTP_USER;
        $this->mail->Password = SMTP_PASS;
        $this->mail->SMTPSecure = SMTP_SECURE;
        $this->mail->Port = SMTP_PORT;
        
        // Expéditeur
        $this->mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
        $this->mail->isHTML(true);
        
        // Encodage
        $this->mail->CharSet = 'UTF-8';
    }
    
    public function sendConfirmationEmail($reservationData, $conn = null) {
        try {
            // Destinataire
            $this->mail->addAddress($reservationData['email']);
            
            // Sujet
            $this->mail->Subject = 'Confirmation de votre réservation - ' . APP_NAME;
            
            // Corps du message
            $this->mail->Body = $this->getEmailTemplate($reservationData, $conn);
            $this->mail->AltBody = $this->getPlainTextTemplate($reservationData, $conn);
            
            // Envoi
            if ($this->mail->send()) {
                error_log("Email de confirmation envoyé à: " . $reservationData['email'] . " pour réservation #" . $reservationData['idReservation']);
                return true;
            } else {
                error_log("Erreur envoi email: " . $this->mail->ErrorInfo);
                return false;
            }
            
        } catch (Exception $e) {
            error_log("Erreur PHPMailer: " . $e->getMessage());
            return false;
        }
    }
    
    private function getEmailTemplate($data, $conn = null) {
        $nights = $this->calculateNights($data['date_arrivee'], $data['date_depart']);
        $totalPrice = $data['prix_total'] ?? 0;
        
        // Récupérer les détails des chambres si possible
        $chambres_details = [];
        if ($conn && isset($data['idReservation'])) {
            $chambres_details = $this->getChambresDetails($data['idReservation'], $conn);
        } elseif (isset($data['chambres_details'])) {
            $chambres_details = $data['chambres_details'];
        }
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { padding: 30px; background: #f9f9f9; border: 1px solid #ddd; }
                .reservation-details { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                .detail-row { display: flex; justify-content: space-between; margin-bottom: 10px; padding: 8px 0; border-bottom: 1px solid #eee; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 14px; background: #f5f5f5; border-radius: 0 0 10px 10px; }
                .status-confirmed { color: #28a745; font-weight: bold; }
                .chambre-list { margin-top: 10px; }
                .chambre-item { background: #f8f9fa; padding: 10px; margin: 5px 0; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎉 Confirmation de Réservation</h1>
                    <p>" . APP_NAME . " - Votre séjour est confirmé</p>
                </div>
                
                <div class='content'>
                    <p>Bonjour <strong>{$data['prenom']} {$data['nom']}</strong>,</p>
                    
                    <p>Votre réservation a été confirmée avec succès. Voici le récapitulatif :</p>
                    
                    <div class='reservation-details'>
                        <h3>📋 Détails de votre réservation</h3>
                        
                        <div class='detail-row'>
                            <span><strong>Référence :</strong></span>
                            <span>#RES-{$data['idReservation']}</span>
                        </div>
                        
                        <div class='detail-row'>
                            <span><strong>Dates :</strong></span>
                            <span>Du " . date('d/m/Y', strtotime($data['date_arrivee'])) . " au " . date('d/m/Y', strtotime($data['date_depart'])) . "</span>
                        </div>
                        
                        <div class='detail-row'>
                            <span><strong>Nombre de nuits :</strong></span>
                            <span>{$nights} nuit(s)</span>
                        </div>
                        
                        <div class='detail-row'>
                            <span><strong>Nombre de personnes :</strong></span>
                            <span>{$data['nombre_personnes']}</span>
                        </div>";
        
        // Ajouter les détails des chambres
        if (!empty($chambres_details)) {
            $template .= "<div class='detail-row'>
                            <span><strong>Chambres réservées :</strong></span>
                            <span></span>
                        </div>
                        <div class='chambre-list'>";
            
            foreach ($chambres_details as $chambre) {
                $template .= "<div class='chambre-item'>
                                <strong>Chambre {$chambre['numeroChambre']}</strong> - {$chambre['type_chambre']}<br>
                                Capacité: {$chambre['capacite']} personne(s) - Prix: {$chambre['prix_nuit']}€/nuit
                              </div>";
            }
            $template .= "</div>";
        }

        $template .= "
                        <div class='detail-row'>
                            <span><strong>Total :</strong></span>
                            <span><strong>{$totalPrice} €</strong></span>
                        </div>
                        
                        <div class='detail-row'>
                            <span><strong>Statut :</strong></span>
                            <span class='status-confirmed'>✅ Confirmée</span>
                        </div>
                    </div>
                    
                    <p><strong>Informations importantes :</strong></p>
                    <ul>
                        <li>🕐 Check-in : À partir de 14h00</li>
                        <li>🕛 Check-out : Avant 12h00</li>
                        <li>📞 Contact : +33 X XX XX XX XX</li>
                        <li>📧 Email : contact@hotel-premium.com</li>
                    </ul>
                    
                    <p>Nous sommes impatients de vous accueillir et vous souhaitons un excellent séjour !</p>
                    
                    <p>Cordialement,<br>
                    <strong>L'équipe de " . APP_NAME . "</strong></p>
                </div>
                
                <div class='footer'>
                    <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
                    <p>&copy; " . date('Y') . " " . APP_NAME . ". Tous droits réservés.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    private function getPlainTextTemplate($data, $conn = null) {
        $nights = $this->calculateNights($data['date_arrivee'], $data['date_depart']);
        $totalPrice = $data['prix_total'] ?? 0;
        
        // Récupérer les détails des chambres si possible
        $chambres_details = [];
        if ($conn && isset($data['idReservation'])) {
            $chambres_details = $this->getChambresDetails($data['idReservation'], $conn);
        } elseif (isset($data['chambres_details'])) {
            $chambres_details = $data['chambres_details'];
        }
        
        $text = "
CONFIRMATION DE RÉSERVATION - " . APP_NAME . "
=============================================

Bonjour {$data['prenom']} {$data['nom']},

Votre réservation a été confirmée avec succès.

DÉTAILS DE LA RÉSERVATION :
────────────────────────────
Référence : #RES-{$data['idReservation']}
Dates : Du " . date('d/m/Y', strtotime($data['date_arrivee'])) . " au " . date('d/m/Y', strtotime($data['date_depart'])) . "
Nombre de nuits : {$nights} nuit(s)
Nombre de personnes : {$data['nombre_personnes']}
";

        // Ajouter les détails des chambres
        if (!empty($chambres_details)) {
            $text .= "Chambres réservées :\n";
            foreach ($chambres_details as $chambre) {
                $text .= "- Chambre {$chambre['numeroChambre']} ({$chambre['type_chambre']})\n";
                $text .= "  Capacité: {$chambre['capacite']} personne(s) - Prix: {$chambre['prix_nuit']}€/nuit\n";
            }
        }

        $text .= "
Total : {$totalPrice} €
Statut : Confirmée

INFORMATIONS IMPORTANTES :
• Check-in : À partir de 14h00
• Check-out : Avant 12h00  
• Contact : +33 X XX XX XX XX
• Email : contact@hotel-premium.com

Nous sommes impatients de vous accueillir et vous souhaitons un excellent séjour !

Cordialement,
L'équipe de " . APP_NAME . "

--
Cet email a été envoyé automatiquement, merci de ne pas y répondre.
© " . date('Y') . " " . APP_NAME . ". Tous droits réservés.
        ";
        
        return $text;
    }
    
    private function calculateNights($arrivee, $depart) {
        $arriveeDate = new DateTime($arrivee);
        $departDate = new DateTime($depart);
        $interval = $arriveeDate->diff($departDate);
        return $interval->days;
    }
    
    private function getChambresDetails($reservationId, $conn) {
        try {
            $query = "SELECT c.numeroChambre, c.type_chambre, c.capacite, c.prix_nuit 
                      FROM chambres c
                      INNER JOIN reservation_chambres rc ON c.idChambre = rc.idChambre
                      WHERE rc.idReservation = :reservation_id";
            
            $stmt = $conn->prepare($query);
            $stmt->execute([':reservation_id' => $reservationId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Erreur getChambresDetails: " . $e->getMessage());
            return [];
        }
    }
}
?>