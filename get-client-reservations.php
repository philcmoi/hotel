<?php
require_once 'config.php';

// Démarrer la session et vérifier la connexion
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

// Connexion à la base de données
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur de connexion à la base de données']);
    exit;
}

// Récupérer l'ID du client
$clientId = $_GET['client_id'] ?? 0;

if (!$clientId) {
    echo json_encode(['success' => false, 'error' => 'ID client manquant']);
    exit;
}

try {
    // Requête pour récupérer les réservations du client avec les chambres
    $stmt = $pdo->prepare("
        SELECT 
            r.idReservation,
            r.date_arrivee,
            r.date_depart,
            r.nombre_personnes,
            r.prix_total,
            r.etat_reservation,
            r.date_reservation,
            r.commentaire,
            GROUP_CONCAT(DISTINCT c.numeroChambre) as chambres,
            GROUP_CONCAT(DISTINCT c.type_chambre) as types_chambres
        FROM reservations r
        LEFT JOIN reservation_chambres rc ON r.idReservation = rc.idReservation
        LEFT JOIN chambres c ON rc.idChambre = c.idChambre
        WHERE r.idClient = ?
        GROUP BY r.idReservation
        ORDER BY r.date_arrivee DESC
    ");
    
    $stmt->execute([$clientId]);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'reservations' => $reservations
    ]);
    
} catch(PDOException $e) {
    echo json_encode([
        'success' => false, 
        'error' => 'Erreur lors de la récupération des réservations: ' . $e->getMessage()
    ]);
}
?>