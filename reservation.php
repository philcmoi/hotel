<?php
header('Content-Type: application/json');
require_once 'config.php';

class ReservationSystem {
    private $conn;
    private $table_reservations = 'reservations';
    private $table_chambres = 'chambres';
    private $table_reservation_chambres = 'reservation_chambres';
    private $table_clients = 'clients';

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // Récupérer les réservations pour le calendrier
    public function getReservationsForCalendar($month, $year) {
        $start_date = "$year-$month-01";
        $end_date = date('Y-m-t', strtotime($start_date));
        
        $query = "SELECT r.idReservation, r.date_arrivee, r.date_depart, 
                         c.idChambre, c.numeroChambre, c.type_chambre,
                         cl.nom, cl.prenom
                  FROM " . $this->table_reservations . " r
                  INNER JOIN " . $this->table_reservation_chambres . " rc ON r.idReservation = rc.idReservation
                  INNER JOIN " . $this->table_chambres . " c ON rc.idChambre = c.idChambre
                  INNER JOIN " . $this->table_clients . " cl ON r.idClient = cl.idClient
                  WHERE r.etat_reservation IN ('en attente', 'confirme')
                  AND (
                      (r.date_arrivee BETWEEN :start_date AND :end_date) 
                      OR (r.date_depart BETWEEN :start_date AND :end_date)
                      OR (r.date_arrivee <= :start_date AND r.date_depart >= :end_date)
                  )";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Vérifier la disponibilité des chambres - CORRIGÉ
    public function checkAvailability($date_arrivee, $date_depart, $chambre_id = null) {
        // D'abord, vérifier s'il y a des réservations actives
        $checkQuery = "SELECT COUNT(*) as count_reservations FROM " . $this->table_reservations . " 
                      WHERE etat_reservation IN ('en attente', 'confirme')";
        $checkStmt = $this->conn->prepare($checkQuery);
        $checkStmt->execute();
        $reservationCount = $checkStmt->fetch(PDO::FETCH_ASSOC)['count_reservations'];
        
        // Si aucune réservation, retourner toutes les chambres disponibles
        if ($reservationCount == 0) {
            $query = "SELECT c.idChambre, c.numeroChambre, c.type_chambre, c.prix_nuit, c.capacite
                      FROM " . $this->table_chambres . " c
                      WHERE c.disponible = 1";
            
            if ($chambre_id) {
                $query .= " AND c.idChambre = :chambre_id";
            }
            
            $stmt = $this->conn->prepare($query);
            
            if ($chambre_id) {
                $stmt->bindParam(':chambre_id', $chambre_id);
            }
        } else {
            // S'il y a des réservations, vérifier la disponibilité
            $query = "SELECT c.idChambre, c.numeroChambre, c.type_chambre, c.prix_nuit, c.capacite
                      FROM " . $this->table_chambres . " c
                      WHERE c.disponible = 1 
                      AND c.idChambre NOT IN (
                          SELECT DISTINCT rc.idChambre 
                          FROM " . $this->table_reservation_chambres . " rc
                          INNER JOIN " . $this->table_reservations . " r ON rc.idReservation = r.idReservation
                          WHERE r.etat_reservation IN ('en attente', 'confirme')
                          AND (
                              -- Vérifier les chevauchements de dates CORRECTEMENT
                              (r.date_arrivee <= :date_depart AND r.date_depart >= :date_arrivee)
                          )
                      )";
            
            if ($chambre_id) {
                $query .= " AND c.idChambre = :chambre_id";
            }
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':date_arrivee', $date_arrivee);
            $stmt->bindParam(':date_depart', $date_depart);
            
            if ($chambre_id) {
                $stmt->bindParam(':chambre_id', $chambre_id);
            }
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

   public function createReservation($data) {
    try {
        $this->conn->beginTransaction();

        // Vérifier si le client existe déjà par email
        $clientResult = $this->findOrCreateClient($data);
        $client_id = $clientResult['client_id'];
        $client_existait = $clientResult['client_existait'];

        // Calculer le prix total
        $nights = (strtotime($data['date_depart']) - strtotime($data['date_arrivee'])) / (60 * 60 * 24);
        $prix_total = 0;
        foreach ($data['chambres'] as $chambre_id) {
            $chambre = $this->getChambreById($chambre_id);
            $prix_total += $chambre['prix_nuit'] * $nights;
        }

        // Créer la réservation
        $reservation_query = "INSERT INTO " . $this->table_reservations . " 
                            (date_arrivee, date_depart, nombre_personnes, prix_total, 
                             etat_reservation, date_reservation, commentaire, idClient) 
                            VALUES (:date_arrivee, :date_depart, :nombre_personnes, :prix_total, 
                                    'en attente', NOW(), :commentaire, :idClient)";
        $reservation_stmt = $this->conn->prepare($reservation_query);
        $reservation_stmt->bindParam(':date_arrivee', $data['date_arrivee']);
        $reservation_stmt->bindParam(':date_depart', $data['date_depart']);
        $reservation_stmt->bindParam(':nombre_personnes', $data['nombre_personnes']);
        $reservation_stmt->bindParam(':prix_total', $prix_total);
        $reservation_stmt->bindParam(':commentaire', $data['commentaire']);
        $reservation_stmt->bindParam(':idClient', $client_id);
        $reservation_stmt->execute();
        $reservation_id = $this->conn->lastInsertId();

        // Lier les chambres à la réservation
        foreach ($data['chambres'] as $chambre_id) {
            $this->linkChambreToReservation($reservation_id, $chambre_id);
        }

        $this->conn->commit();
        return ['success' => true, 'reservation_id' => $reservation_id, 'client_existait' => $client_existait];
        
    } catch (Exception $e) {
        $this->conn->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Nouvelle méthode pour trouver ou créer un client
private function findOrCreateClient($data) {
    // Vérifier si le client existe par email
    $query = "SELECT idClient FROM " . $this->table_clients . " WHERE email = :email";
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(':email', $data['email']);
    $stmt->execute();
    
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($client) {
        // Client existe déjà, mettre à jour ses informations si nécessaire
        $this->updateClientIfNeeded($client['idClient'], $data);
        return [
            'client_id' => $client['idClient'],
            'client_existait' => true
        ];
    } else {
        // Créer un nouveau client
        $client_id = $this->createNewClient($data);
        return [
            'client_id' => $client_id,
            'client_existait' => false
        ];
    }
}

// Méthode pour créer un nouveau client
private function createNewClient($data) {
    $client_query = "INSERT INTO " . $this->table_clients . " 
                    (nom, prenom, email, telephone, adresse, date_creation) 
                    VALUES (:nom, :prenom, :email, :telephone, :adresse, NOW())";
    $client_stmt = $this->conn->prepare($client_query);
    $client_stmt->bindParam(':nom', $data['nom']);
    $client_stmt->bindParam(':prenom', $data['prenom']);
    $client_stmt->bindParam(':email', $data['email']);
    $client_stmt->bindParam(':telephone', $data['telephone']);
    $client_stmt->bindParam(':adresse', $data['adresse']);
    $client_stmt->execute();
    return $this->conn->lastInsertId();
}

// Méthode pour mettre à jour les informations du client existant
private function updateClientIfNeeded($client_id, $data) {
    // Mettre à jour les informations du client
    $update_query = "UPDATE " . $this->table_clients . " 
                    SET nom = :nom, prenom = :prenom, telephone = :telephone, adresse = :adresse 
                    WHERE idClient = :idClient";
    
    $stmt = $this->conn->prepare($update_query);
    $stmt->bindParam(':nom', $data['nom']);
    $stmt->bindParam(':prenom', $data['prenom']);
    $stmt->bindParam(':telephone', $data['telephone']);
    $stmt->bindParam(':adresse', $data['adresse']);
    $stmt->bindParam(':idClient', $client_id);
    $stmt->execute();
}

    private function getChambreById($id) {
        $query = "SELECT * FROM " . $this->table_chambres . " WHERE idChambre = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function linkChambreToReservation($reservation_id, $chambre_id) {
        $query = "INSERT INTO " . $this->table_reservation_chambres . " (idReservation, idChambre) VALUES (:reservation_id, :chambre_id)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':reservation_id', $reservation_id);
        $stmt->bindParam(':chambre_id', $chambre_id);
        $stmt->execute();
    }

    // Récupérer toutes les chambres
    public function getAllChambres() {
        $query = "SELECT * FROM " . $this->table_chambres . " ORDER BY numeroChambre";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Méthode de debug pour vérifier l'état de la base
    public function debugState() {
        $debug = [];
        
        // Compter les chambres
        $query = "SELECT COUNT(*) as total, SUM(disponible) as disponibles FROM " . $this->table_chambres;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $debug['chambres'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Compter les réservations
        $query = "SELECT COUNT(*) as total FROM " . $this->table_reservations . " WHERE etat_reservation IN ('en attente', 'confirme')";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $debug['reservations'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $debug;
    }
}

// Gestion des requêtes AJAX
$reservationSystem = new ReservationSystem();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['action'])) {
        switch ($_GET['action']) {
            case 'get_reservations':
                $month = $_GET['month'] ?? date('m');
                $year = $_GET['year'] ?? date('Y');
                echo json_encode($reservationSystem->getReservationsForCalendar($month, $year));
                break;
                
            case 'check_availability':
                $date_arrivee = $_GET['date_arrivee'];
                $date_depart = $_GET['date_depart'];
                echo json_encode($reservationSystem->checkAvailability($date_arrivee, $date_depart));
                break;
                
            case 'get_chambres':
                echo json_encode($reservationSystem->getAllChambres());
                break;
                
            case 'debug': // Pour debug
                echo json_encode($reservationSystem->debugState());
                break;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input['action'] === 'create_reservation') {
        echo json_encode($reservationSystem->createReservation($input['data']));
    }
}
?>