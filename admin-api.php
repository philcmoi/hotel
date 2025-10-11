<?php
require_once 'config.php';

class Database {
    private $host = "localhost";
    private $db_name = "hotel";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            error_log("Erreur de connexion: " . $exception->getMessage());
            return null;
        }
        return $this->conn;
    }
}

class AdminReservationSystem {
    private $conn;
    private $table_reservations = 'reservations';
    private $table_chambres = 'chambres';
    private $table_reservation_chambres = 'reservation_chambres';
    private $table_clients = 'clients';

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // Récupérer toutes les réservations avec filtres
    public function getAllReservations($filters = []) {
        try {
            $query = "SELECT r.idReservation, r.date_arrivee, r.date_depart, r.nombre_personnes, 
                             r.prix_total, r.etat_reservation, r.date_reservation, r.commentaire,
                             c.idClient, c.nom, c.prenom, c.email, c.telephone,
                             GROUP_CONCAT(DISTINCT ch.numeroChambre) as chambres,
                             GROUP_CONCAT(DISTINCT ch.type_chambre) as types_chambres
                      FROM " . $this->table_reservations . " r
                      INNER JOIN " . $this->table_clients . " c ON r.idClient = c.idClient
                      LEFT JOIN " . $this->table_reservation_chambres . " rc ON r.idReservation = rc.idReservation
                      LEFT JOIN " . $this->table_chambres . " ch ON rc.idChambre = ch.idChambre
                      WHERE 1=1";

            $params = [];

            // Filtre par statut
            if (!empty($filters['status'])) {
                $query .= " AND r.etat_reservation = :status";
                $params[':status'] = $filters['status'];
            }

            // Filtre par date
            if (!empty($filters['date'])) {
                $query .= " AND (r.date_arrivee = :date OR r.date_depart = :date)";
                $params[':date'] = $filters['date'];
            }

            // Filtre par recherche
            if (!empty($filters['search'])) {
                $query .= " AND (c.nom LIKE :search OR c.prenom LIKE :search OR c.email LIKE :search)";
                $params[':search'] = '%' . $filters['search'] . '%';
            }

            $query .= " GROUP BY r.idReservation ORDER BY r.date_reservation DESC";

            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            
            return ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Récupérer une réservation spécifique
    public function getReservationById($id) {
        try {
            $query = "SELECT r.idReservation, r.date_arrivee, r.date_depart, r.nombre_personnes, 
                             r.prix_total, r.etat_reservation, r.date_reservation, r.commentaire,
                             c.idClient, c.nom, c.prenom, c.email, c.telephone, c.adresse,
                             ch.idChambre, ch.numeroChambre, ch.type_chambre, ch.prix_nuit
                      FROM " . $this->table_reservations . " r
                      INNER JOIN " . $this->table_clients . " c ON r.idClient = c.idClient
                      LEFT JOIN " . $this->table_reservation_chambres . " rc ON r.idReservation = rc.idReservation
                      LEFT JOIN " . $this->table_chambres . " ch ON rc.idChambre = ch.idChambre
                      WHERE r.idReservation = :id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            $reservation = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($reservation)) {
                return ['success' => false, 'error' => 'Réservation non trouvée'];
            }
            
            // Organiser les données
            $result = [
                'reservation' => [
                    'idReservation' => $reservation[0]['idReservation'],
                    'date_arrivee' => $reservation[0]['date_arrivee'],
                    'date_depart' => $reservation[0]['date_depart'],
                    'nombre_personnes' => $reservation[0]['nombre_personnes'],
                    'prix_total' => $reservation[0]['prix_total'],
                    'etat_reservation' => $reservation[0]['etat_reservation'],
                    'date_reservation' => $reservation[0]['date_reservation'],
                    'commentaire' => $reservation[0]['commentaire']
                ],
                'client' => [
                    'idClient' => $reservation[0]['idClient'],
                    'nom' => $reservation[0]['nom'],
                    'prenom' => $reservation[0]['prenom'],
                    'email' => $reservation[0]['email'],
                    'telephone' => $reservation[0]['telephone'],
                    'adresse' => $reservation[0]['adresse']
                ],
                'chambres' => []
            ];
            
            foreach ($reservation as $row) {
                if ($row['idChambre']) {
                    $result['chambres'][] = [
                        'idChambre' => $row['idChambre'],
                        'numeroChambre' => $row['numeroChambre'],
                        'type_chambre' => $row['type_chambre'],
                        'prix_nuit' => $row['prix_nuit']
                    ];
                }
            }
            
            return ['success' => true, 'data' => $result];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Créer une réservation
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

    // Mettre à jour une réservation
    public function updateReservation($id, $data) {
        try {
            $this->conn->beginTransaction();

            // Vérifier que la réservation existe
            $existingReservation = $this->getReservationById($id);
            if (!$existingReservation['success']) {
                return $existingReservation;
            }

            // Mettre à jour la réservation
            $query = "UPDATE " . $this->table_reservations . " 
                      SET date_arrivee = :date_arrivee, 
                          date_depart = :date_depart, 
                          nombre_personnes = :nombre_personnes,
                          commentaire = :commentaire,
                          etat_reservation = :etat_reservation
                      WHERE idReservation = :id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':date_arrivee', $data['date_arrivee']);
            $stmt->bindParam(':date_depart', $data['date_depart']);
            $stmt->bindParam(':nombre_personnes', $data['nombre_personnes']);
            $stmt->bindParam(':commentaire', $data['commentaire']);
            $stmt->bindParam(':etat_reservation', $data['etat_reservation']);
            $stmt->bindParam(':id', $id);
            $stmt->execute();

            $this->conn->commit();
            return ['success' => true, 'message' => 'Réservation mise à jour avec succès'];
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Changer le statut d'une réservation
    public function updateReservationStatus($id, $status) {
        try {
            $query = "UPDATE " . $this->table_reservations . " 
                      SET etat_reservation = :status 
                      WHERE idReservation = :id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':id', $id);
            $stmt->execute();

            return ['success' => true, 'message' => 'Statut mis à jour avec succès'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Supprimer une réservation
    public function deleteReservation($id) {
        try {
            $this->conn->beginTransaction();

            // Vérifier que la réservation existe
            $existingReservation = $this->getReservationById($id);
            if (!$existingReservation['success']) {
                return $existingReservation;
            }

            // Supprimer les liaisons chambres-réservation
            $deleteLinksQuery = "DELETE FROM " . $this->table_reservation_chambres . " WHERE idReservation = :id";
            $deleteLinksStmt = $this->conn->prepare($deleteLinksQuery);
            $deleteLinksStmt->bindParam(':id', $id);
            $deleteLinksStmt->execute();

            // Supprimer la réservation
            $deleteReservationQuery = "DELETE FROM " . $this->table_reservations . " WHERE idReservation = :id";
            $deleteReservationStmt = $this->conn->prepare($deleteReservationQuery);
            $deleteReservationStmt->bindParam(':id', $id);
            $deleteReservationStmt->execute();

            $this->conn->commit();
            return ['success' => true, 'message' => 'Réservation supprimée avec succès'];
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Vérifier la disponibilité
    public function checkAvailability($date_arrivee, $date_depart) {
        try {
            $checkQuery = "SELECT COUNT(*) as count_reservations FROM " . $this->table_reservations . " 
                          WHERE etat_reservation IN ('en attente', 'confirme')";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->execute();
            $reservationCount = $checkStmt->fetch(PDO::FETCH_ASSOC)['count_reservations'];
            
            if ($reservationCount == 0) {
                $query = "SELECT c.idChambre, c.numeroChambre, c.type_chambre, c.prix_nuit, c.capacite
                          FROM " . $this->table_chambres . " c
                          WHERE c.disponible = 1";
            } else {
                $query = "SELECT c.idChambre, c.numeroChambre, c.type_chambre, c.prix_nuit, c.capacite
                          FROM " . $this->table_chambres . " c
                          WHERE c.disponible = 1 
                          AND c.idChambre NOT IN (
                              SELECT DISTINCT rc.idChambre 
                              FROM " . $this->table_reservation_chambres . " rc
                              INNER JOIN " . $this->table_reservations . " r ON rc.idReservation = r.idReservation
                              WHERE r.etat_reservation IN ('en attente', 'confirme')
                              AND (
                                  (r.date_arrivee <= :date_depart AND r.date_depart >= :date_arrivee)
                              )
                          )";
            }
            
            $stmt = $this->conn->prepare($query);
            if ($reservationCount > 0) {
                $stmt->bindParam(':date_arrivee', $date_arrivee);
                $stmt->bindParam(':date_depart', $date_depart);
            }
            $stmt->execute();
            
            return ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Méthodes utilitaires
    private function findOrCreateClient($data) {
        $query = "SELECT idClient FROM " . $this->table_clients . " WHERE email = :email";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $data['email']);
        $stmt->execute();
        
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($client) {
            $this->updateClientIfNeeded($client['idClient'], $data);
            return ['client_id' => $client['idClient'], 'client_existait' => true];
        } else {
            $client_id = $this->createNewClient($data);
            return ['client_id' => $client_id, 'client_existait' => false];
        }
    }

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

    private function updateClientIfNeeded($client_id, $data) {
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
}

// Traitement des requêtes API SÉCURISÉ
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $system = new AdminReservationSystem();

    // Actions publiques (ne nécessitent pas d'authentification)
    $publicActions = ['check_availability'];
    
    // Vérifier l'authentification pour les actions privées
    if (!in_array($action, $publicActions)) {
        requireAuth();
    }

    switch ($action) {
        case 'get_all':
            $filters = [
                'status' => $_GET['status'] ?? '',
                'date' => $_GET['date'] ?? '',
                'search' => $_GET['search'] ?? ''
            ];
            echo json_encode($system->getAllReservations($filters));
            break;

        case 'get':
            $id = $_GET['id'] ?? '';
            echo json_encode($system->getReservationById($id));
            break;

        case 'check_availability':
            $date_arrivee = $_GET['date_arrivee'] ?? '';
            $date_depart = $_GET['date_depart'] ?? '';
            echo json_encode($system->checkAvailability($date_arrivee, $date_depart));
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Action non reconnue']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Toutes les actions POST nécessitent une authentification
    requireAuth();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $_GET['action'] ?? '';
    $system = new AdminReservationSystem();

    switch ($action) {
        case 'create':
            echo json_encode($system->createReservation($input));
            break;

        case 'update':
            $id = $_GET['id'] ?? '';
            echo json_encode($system->updateReservation($id, $input));
            break;

        case 'update_status':
            $id = $_GET['id'] ?? '';
            $status = $input['status'] ?? '';
            
            // Validation
            if (empty($id) || empty($status)) {
                echo json_encode(['success' => false, 'error' => 'ID ou statut manquant']);
                break;
            }
            
            // Statuts autorisés
            $allowedStatuses = ['en attente', 'confirme', 'en cours', 'termine', 'annule'];
            if (!in_array($status, $allowedStatuses)) {
                echo json_encode(['success' => false, 'error' => 'Statut non autorisé']);
                break;
            }
            
            echo json_encode($system->updateReservationStatus($id, $status));
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Action non reconnue']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // Toutes les actions DELETE nécessitent une authentification
    requireAuth();
    
    $action = $_GET['action'] ?? '';
    $system = new AdminReservationSystem();

    if ($action === 'delete') {
        $id = $_GET['id'] ?? '';
        echo json_encode($system->deleteReservation($id));
    } else {
        echo json_encode(['success' => false, 'error' => 'Action non reconnue']);
    }
}
?>