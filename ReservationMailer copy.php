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
    }
    
    public function sendConfirmationEmail($reservationData) {
        try {
            // Destinataire
            $this->mail->addAddress($reservationData['email']);
            
            // Sujet
            $this->mail->Subject = 'Confirmation de votre réservation - ' . APP_NAME;
            
            // Corps du message
            $this->mail->Body = $this->getEmailTemplate($reservationData);
            $this->mail->AltBody = $this->getPlainTextTemplate($reservationData);
            
            // Envoi
            if ($this->mail->send()) {
                error_log("Email de confirmation envoyé à: " . $reservationData['email']);
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
    
    private function getEmailTemplate($data) {
        $nights = $this->calculateNights($data['date_arrivee'], $data['date_depart']);
        $totalPrice = $data['prix_total'] ?? ($data['prix_nuit'] * $nights);
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { padding: 30px; background: #f9f9f9; border: 1px solid #ddd; }
                .reservation-details { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                .detail-row { display: flex; justify-content: space-between; margin-bottom: 10px; padding: 8px 0; border-bottom: 1px solid #eee; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 14px; }
                .status-confirmed { color: #28a745; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎉 Confirmation de Réservation</h1>
                    <p>Hôtel Premium - Votre séjour est confirmé</p>
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

        // Ajouter les détails des chambres si disponibles
        if (isset($data['chambres_details'])) {
            $template .= "<div class='detail-row'><span><strong>Chambres :</strong></span><span>";
            foreach ($data['chambres_details'] as $chambre) {
                $template .= "Chambre {$chambre['numeroChambre']} ({$chambre['type_chambre']})<br>";
            }
            $template .= "</span></div>";
        }

        $template .= "
                        <div class='detail-row'>
                            <span><strong>Total :</strong></span>
                            <span>{$totalPrice} €</span>
                        </div>
                        
                        <div class='detail-row'>
                            <span><strong>Statut :</strong></span>
                            <span class='status-confirmed'>✅ Confirmée</span>
                        </div>
                    </div>
                    
                    <p><strong>Informations importantes :</strong></p>
                    <ul>
                        <li>Check-in : À partir de 14h00</li>
                        <li>Check-out : Avant 12h00</li>
                        <li>Annulation : Consultez nos conditions générales</li>
                    </ul>
                    
                    <p>Nous sommes impatients de vous accueillir et vous souhaitons un excellent séjour !</p>
                    
                    <p>Cordialement,<br>
                    <strong>L'équipe de " . APP_NAME . "</strong><br>
                    📞 Votre numéro de contact<br>
                    📧 Votre email de contact</p>
                </div>
                
                <div class='footer'>
                    <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    private function getPlainTextTemplate($data) {
        $nights = $this->calculateNights($data['date_arrivee'], $data['date_depart']);
        $totalPrice = $data['prix_total'] ?? ($data['prix_nuit'] * $nights);
        
        return "
CONFIRMATION DE RÉSERVATION - " . APP_NAME . "

Bonjour {$data['prenom']} {$data['nom']},

Votre réservation a été confirmée avec succès.

DÉTAILS DE LA RÉSERVATION :
────────────────────────────
Référence : #RES-{$data['idReservation']}
Dates : Du " . date('d/m/Y', strtotime($data['date_arrivee'])) . " au " . date('d/m/Y', strtotime($data['date_depart'])) . "
Nombre de nuits : {$nights} nuit(s)
Nombre de personnes : {$data['nombre_personnes']}
Total : {$totalPrice} €
Statut : Confirmée

INFORMATIONS IMPORTANTES :
• Check-in : À partir de 14h00
• Check-out : Avant 12h00
• Annulation : Consultez nos conditions générales

Nous sommes impatients de vous accueillir et vous souhaitons un excellent séjour !

Cordialement,
L'équipe de " . APP_NAME . "

Cet email a été envoyé automatiquement, merci de ne pas y répondre.
        ";
    }
    
    private function calculateNights($arrivee, $depart) {
        $arriveeDate = new DateTime($arrivee);
        $departDate = new DateTime($depart);
        $interval = $arriveeDate->diff($departDate);
        return $interval->days;
    }
}
?>