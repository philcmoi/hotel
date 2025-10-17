<?php
// test_email.php - Test local de l'email de confirmation

// Inclure la classe EmailSender
require_once 'envoi-email.php'; // Assurez-vous que le chemin est correct

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "hotel";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
    
    // Récupération des données (exemple avec idReservation = 1)
    $reservationId = 1;

    // Créer l'instance d'EmailSender en mode test
    $emailSender = new EmailSender(true); // true pour mode test

    // Récupérer l'email du client à partir de la réservation
    $sql = "SELECT c.email 
            FROM reservations r
            JOIN clients c ON r.idClient = c.idClient
            WHERE r.idReservation = :idReservation";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['idReservation' => $reservationId]);
    $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($reservation) {
        $client_email = $reservation['email'];

        // "Envoyer" l'email (affichage à l'écran)
        $emailSender->sendReservationConfirmation($reservationId, $client_email, $pdo);

        // Mise à jour de la base de données (comme dans le script original)
        $updateSql = "UPDATE reservations SET etat_reservation = 'confirme' WHERE idReservation = :idReservation";
        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute(['idReservation' => $reservationId]);
        
        echo "<p style='color:green; margin-top:20px;'>✅ Réservation marquée comme confirmée dans la base de données</p>";
        
    } else {
        echo "❌ Réservation non trouvée";
    }
    
} catch(PDOException $e) {
    echo "Erreur: " . $e->getMessage();
}