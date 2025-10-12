<?php
// admin-reservations.php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gérer les requêtes preflight OPTIONS pour CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Inclure la configuration
require_once 'config.php';

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
            return $this->conn;
        } catch(PDOException $exception) {
            error_log("Erreur de connexion BD: " . $exception->getMessage());
            return false;
        }
    }
}

class AdminReservations {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
        if (!$this->conn) {
            throw new Exception("Impossible de se connecter à la base de données");
        }
    }

    // Récupérer les statistiques
    public function getReservationStats() {
        $query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN etat_reservation = 'confirme' THEN 1 ELSE 0 END) as confirmed,
                    SUM(CASE WHEN etat_reservation = 'en attente' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN etat_reservation = 'annulee' THEN 1 ELSE 0 END) as cancelled
                  FROM reservations";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // S'assurer que toutes les valeurs sont définies
        return [
            'total' => $result['total'] ?? 0,
            'confirmed' => $result['confirmed'] ?? 0,
            'pending' => $result['pending'] ?? 0,
            'cancelled' => $result['cancelled'] ?? 0
        ];
    }

    // Récupérer les réservations avec pagination et filtres
    public function getAllReservations($page = 1, $itemsPerPage = 10, $filters = []) {
        $offset = ($page - 1) * $itemsPerPage;
        
        // Requête corrigée avec la table de liaison reservation_chambres
        $query = "SELECT 
                    r.idReservation,
                    r.date_arrivee,
                    r.date_depart,
                    r.nombre_personnes,
                    r.prix_total,
                    r.etat_reservation,
                    r.date_reservation,
                    c.nom,
                    c.prenom,
                    c.email,
                    ch.numeroChambre,
                    ch.type_chambre
                  FROM reservations r
                  LEFT JOIN clients c ON r.idClient = c.idClient
                  LEFT JOIN reservation_chambres rc ON r.idReservation = rc.idReservation
                  LEFT JOIN chambres ch ON rc.idChambre = ch.idChambre
                  WHERE 1=1";
        
        $countQuery = "SELECT COUNT(DISTINCT r.idReservation) as total
                      FROM reservations r
                      LEFT JOIN clients c ON r.idClient = c.idClient
                      LEFT JOIN reservation_chambres rc ON r.idReservation = rc.idReservation
                      LEFT JOIN chambres ch ON rc.idChambre = ch.idChambre
                      WHERE 1=1";
        
        $params = [];
        
        // Appliquer les filtres
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query .= " AND r.etat_reservation = :status";
            $countQuery .= " AND r.etat_reservation = :status";
            $params[':status'] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $query .= " AND (c.nom LIKE :search OR c.prenom LIKE :search OR c.email LIKE :search OR r.idReservation LIKE :search OR ch.numeroChambre LIKE :search)";
            $countQuery .= " AND (c.nom LIKE :search OR c.prenom LIKE :search OR c.email LIKE :search OR r.idReservation LIKE :search OR ch.numeroChambre LIKE :search)";
            $params[':search'] = $searchTerm;
        }
        
        $query .= " GROUP BY r.idReservation ORDER BY r.date_reservation DESC LIMIT :offset, :limit";
        
        // Compter le total
        $countStmt = $this->conn->prepare($countQuery);
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        $countStmt->execute();
        $totalResult = $countStmt->fetch(PDO::FETCH_ASSOC);
        $total = $totalResult['total'] ?? 0;
        
        // Récupérer les données
        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
        $stmt->execute();
        $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'reservations' => $reservations,
            'total' => $total
        ];
    }

    // Mettre à jour le statut d'une réservation
    public function updateReservationStatus($reservationId, $status) {
        // Valider le statut
        $allowedStatus = ['confirme', 'en attente', 'annulee'];
        if (!in_array($status, $allowedStatus)) {
            throw new Exception("Statut invalide: " . $status);
        }
        
        $query = "UPDATE reservations SET etat_reservation = :status WHERE idReservation = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $reservationId);
        
        $result = $stmt->execute();
        
        if ($result && $stmt->rowCount() > 0) {
            return true;
        } else {
            throw new Exception("Aucune réservation trouvée avec cet ID ou statut identique");
        }
    }
}

// Vérifier l'authentification admin (à décommenter en production)
/*
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}
*/

// Traitement des requêtes
try {
    $admin = new AdminReservations();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur de connexion à la base de données: ' . $e->getMessage()]);
    exit;
}

$response = ['success' => false, 'error' => 'Action non reconnue'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
        switch ($_GET['action']) {
            case 'get_stats':
                $stats = $admin->getReservationStats();
                $response = ['success' => true, 'data' => $stats];
                break;
                
            case 'get_reservations':
                $page = max(1, intval($_GET['page'] ?? 1));
                $status = $_GET['status'] ?? 'all';
                $search = $_GET['search'] ?? '';
                
                $filters = [
                    'status' => $status,
                    'search' => $search
                ];
                
                $result = $admin->getAllReservations($page, 10, $filters);
                $response = [
                    'success' => true, 
                    'data' => $result['reservations'],
                    'total' => $result['total']
                ];
                break;
                
            default:
                $response = ['success' => false, 'error' => 'Action inconnue'];
                break;
        }
    } 
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (isset($input['action'])) {
            switch ($input['action']) {
                case 'update_status':
                    if (isset($input['reservation_id']) && isset($input['status'])) {
                        $reservationId = intval($input['reservation_id']);
                        $status = $input['status'];
                        
                        $success = $admin->updateReservationStatus($reservationId, $status);
                        $response = ['success' => $success];
                        if (!$success) {
                            $response['error'] = 'Erreur lors de la mise à jour du statut';
                        }
                    } else {
                        $response['error'] = 'Paramètres manquants: reservation_id et status requis';
                    }
                    break;
                    
                default:
                    $response = ['success' => false, 'error' => 'Action inconnue'];
                    break;
            }
        } else {
            $response['error'] = 'Aucune action spécifiée';
        }
    }
} catch (Exception $e) {
    error_log("Erreur AdminReservations: " . $e->getMessage());
    $response = ['success' => false, 'error' => $e->getMessage()];
}

echo json_encode($response);
?>