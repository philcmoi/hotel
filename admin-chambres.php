<?php
// admin-chambres.php
header('Content-Type: application/json');
require_once 'config.php';

// Classe Database intégrée
class Database {
    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8", $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            error_log("Erreur de connexion BD: " . $exception->getMessage());
            throw new Exception("Erreur de connexion à la base de données");
        }
        return $this->conn;
    }
}

class AdminChambres {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // Récupérer les statistiques des chambres
    public function getChambreStats() {
        $query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN disponible = 1 THEN 1 ELSE 0 END) as available,
                    SUM(CASE WHEN disponible = 0 THEN 1 ELSE 0 END) as maintenance,
                    (SELECT COUNT(DISTINCT rc.idChambre) 
                     FROM reservation_chambres rc 
                     INNER JOIN reservations r ON rc.idReservation = r.idReservation 
                     WHERE r.date_depart >= CURDATE() 
                     AND r.etat_reservation = 'confirme') as occupied
                  FROM chambres";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Récupérer les chambres avec leurs réservations
    public function getAllChambres($page = 1, $itemsPerPage = 10, $filters = []) {
        $offset = ($page - 1) * $itemsPerPage;
        
        // Requête principale pour les chambres
        $query = "SELECT 
                    c.idChambre,
                    c.numeroChambre,
                    c.type_chambre,
                    c.capacite,
                    c.prix_nuit,
                    c.description,
                    c.disponible,
                    CASE 
                        WHEN c.disponible = 0 THEN 'maintenance'
                        WHEN EXISTS (
                            SELECT 1 FROM reservation_chambres rc 
                            INNER JOIN reservations r ON rc.idReservation = r.idReservation 
                            WHERE rc.idChambre = c.idChambre 
                            AND r.date_depart >= CURDATE() 
                            AND r.etat_reservation = 'confirme'
                        ) THEN 'occupee'
                        ELSE 'disponible'
                    END as statut
                  FROM chambres c
                  WHERE 1=1";
        
        $countQuery = "SELECT COUNT(*) as total FROM chambres c WHERE 1=1";
        
        $params = [];
        
        // Appliquer les filtres
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            if ($filters['status'] === 'disponible') {
                $query .= " AND c.disponible = 1 AND NOT EXISTS (
                    SELECT 1 FROM reservation_chambres rc 
                    INNER JOIN reservations r ON rc.idReservation = r.idReservation 
                    WHERE rc.idChambre = c.idChambre 
                    AND r.date_depart >= CURDATE() 
                    AND r.etat_reservation = 'confirme'
                )";
                $countQuery .= " AND c.disponible = 1 AND NOT EXISTS (
                    SELECT 1 FROM reservation_chambres rc 
                    INNER JOIN reservations r ON rc.idReservation = r.idReservation 
                    WHERE rc.idChambre = c.idChambre 
                    AND r.date_depart >= CURDATE() 
                    AND r.etat_reservation = 'confirme'
                )";
            } elseif ($filters['status'] === 'occupee') {
                $query .= " AND EXISTS (
                    SELECT 1 FROM reservation_chambres rc 
                    INNER JOIN reservations r ON rc.idReservation = r.idReservation 
                    WHERE rc.idChambre = c.idChambre 
                    AND r.date_depart >= CURDATE() 
                    AND r.etat_reservation = 'confirme'
                )";
                $countQuery .= " AND EXISTS (
                    SELECT 1 FROM reservation_chambres rc 
                    INNER JOIN reservations r ON rc.idReservation = r.idReservation 
                    WHERE rc.idChambre = c.idChambre 
                    AND r.date_depart >= CURDATE() 
                    AND r.etat_reservation = 'confirme'
                )";
            } elseif ($filters['status'] === 'maintenance') {
                $query .= " AND c.disponible = 0";
                $countQuery .= " AND c.disponible = 0";
            }
        }
        
        if (!empty($filters['type']) && $filters['type'] !== 'all') {
            $query .= " AND c.type_chambre = :type";
            $countQuery .= " AND c.type_chambre = :type";
            $params[':type'] = $filters['type'];
        }
        
        if (!empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $query .= " AND (c.numeroChambre LIKE :search OR c.type_chambre LIKE :search OR c.description LIKE :search)";
            $countQuery .= " AND (c.numeroChambre LIKE :search OR c.type_chambre LIKE :search OR c.description LIKE :search)";
            $params[':search'] = $searchTerm;
        }
        
        $query .= " ORDER BY c.numeroChambre ASC LIMIT :offset, :limit";
        
        // Compter le total
        $countStmt = $this->conn->prepare($countQuery);
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        $countStmt->execute();
        $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Récupérer les données des chambres
        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
        $stmt->execute();
        $chambres = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Pour chaque chambre, récupérer les réservations
        foreach ($chambres as &$chambre) {
            $chambre['reservations'] = $this->getReservationsForChambre($chambre['idChambre']);
        }
        
        return [
            'chambres' => $chambres,
            'total' => $total
        ];
    }

    // Récupérer les réservations pour une chambre spécifique
    private function getReservationsForChambre($idChambre) {
        $query = "SELECT 
                    r.idReservation,
                    r.date_arrivee,
                    r.date_depart,
                    r.etat_reservation,
                    c.nom,
                    c.prenom
                  FROM reservations r
                  INNER JOIN reservation_chambres rc ON r.idReservation = rc.idReservation
                  INNER JOIN clients c ON r.idClient = c.idClient
                  WHERE rc.idChambre = :idChambre
                  AND r.date_depart >= CURDATE()
                  AND r.etat_reservation = 'confirme'
                  ORDER BY r.date_arrivee ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':idChambre', $idChambre);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Mettre à jour le statut d'une chambre (disponible/maintenance)
    public function updateChambreStatus($chambreId, $disponible) {
        $query = "UPDATE chambres SET disponible = :disponible WHERE idChambre = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':disponible', $disponible, PDO::PARAM_INT);
        $stmt->bindParam(':id', $chambreId);
        return $stmt->execute();
    }

    // Ajouter une nouvelle chambre
    public function addChambre($data) {
        $query = "INSERT INTO chambres 
                  (numeroChambre, type_chambre, capacite, prix_nuit, description, disponible) 
                  VALUES (:numero, :type, :capacite, :prix, :description, :disponible)";
        
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            ':numero' => $data['numero'],
            ':type' => $data['type'],
            ':capacite' => $data['capacite'],
            ':prix' => $data['prix'],
            ':description' => $data['description'],
            ':disponible' => $data['disponible']
        ]);
    }

    // Modifier une chambre
    public function updateChambre($chambreId, $data) {
        $query = "UPDATE chambres 
                  SET numeroChambre = :numero, type_chambre = :type, capacite = :capacite, 
                      prix_nuit = :prix, description = :description, disponible = :disponible
                  WHERE idChambre = :id";
        
        $stmt = $this->conn->prepare($query);
        $data[':id'] = $chambreId;
        return $stmt->execute($data);
    }

    // Récupérer une chambre par son ID
    public function getChambreById($chambreId) {
        $query = "SELECT * FROM chambres WHERE idChambre = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $chambreId);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Vérifier l'authentification
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

// Traitement des requêtes
$admin = new AdminChambres();
$response = ['success' => false, 'error' => 'Action non reconnue'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
        switch ($_GET['action']) {
            case 'get_stats':
                $stats = $admin->getChambreStats();
                $response = ['success' => true, 'data' => $stats];
                break;
                
            case 'get_chambres':
                $page = $_GET['page'] ?? 1;
                $status = $_GET['status'] ?? 'all';
                $type = $_GET['type'] ?? 'all';
                $search = $_GET['search'] ?? '';
                
                $filters = [
                    'status' => $status,
                    'type' => $type,
                    'search' => $search
                ];
                
                $result = $admin->getAllChambres($page, 10, $filters);
                $response = [
                    'success' => true, 
                    'data' => $result['chambres'],
                    'total' => $result['total']
                ];
                break;

            case 'get_chambre':
                if (isset($_GET['id'])) {
                    $chambre = $admin->getChambreById($_GET['id']);
                    $response = ['success' => true, 'data' => $chambre];
                }
                break;
        }
    } 
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (isset($input['action'])) {
            switch ($input['action']) {
                case 'update_status':
                    if (isset($input['chambre_id']) && isset($input['status'])) {
                        $disponible = ($input['status'] === 'disponible') ? 1 : 0;
                        $success = $admin->updateChambreStatus($input['chambre_id'], $disponible);
                        $response = ['success' => $success];
                        if (!$success) {
                            $response['error'] = 'Erreur lors de la mise à jour';
                        }
                    }
                    break;

                case 'add_chambre':
                    if (isset($input['numero']) && isset($input['type']) && isset($input['capacite']) && isset($input['prix'])) {
                        $success = $admin->addChambre([
                            'numero' => $input['numero'],
                            'type' => $input['type'],
                            'capacite' => $input['capacite'],
                            'prix' => $input['prix'],
                            'description' => $input['description'] ?? '',
                            'disponible' => $input['disponible'] ?? 1
                        ]);
                        $response = ['success' => $success];
                        if (!$success) {
                            $response['error'] = 'Erreur lors de l\'ajout de la chambre';
                        }
                    }
                    break;

                case 'update_chambre':
                    if (isset($input['chambre_id']) && isset($input['numero']) && isset($input['type']) && isset($input['capacite']) && isset($input['prix'])) {
                        $success = $admin->updateChambre($input['chambre_id'], [
                            ':numero' => $input['numero'],
                            ':type' => $input['type'],
                            ':capacite' => $input['capacite'],
                            ':prix' => $input['prix'],
                            ':description' => $input['description'] ?? '',
                            ':disponible' => $input['disponible'] ?? 1
                        ]);
                        $response = ['success' => $success];
                        if (!$success) {
                            $response['error'] = 'Erreur lors de la modification de la chambre';
                        }
                    }
                    break;
            }
        }
    }
} catch (Exception $e) {
    $response = ['success' => false, 'error' => $e->getMessage()];
}

echo json_encode($response);
?>