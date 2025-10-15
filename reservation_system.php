<?php


require_once 'config.php';

// TRAITEMENT DES REQUÊTES
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? '';
        $system = new AdminReservationSystem();

        error_log("=== GET REQUEST ===");
        error_log("Action: " . $action);
        error_log("GET params: " . print_r($_GET, true));

        // Si aucune action n'est spécifiée, retourner les actions disponibles
        if (empty($action)) {
            echo json_encode([
                'success' => false, 
                'error' => 'Paramètre action manquant',
                'available_actions' => [
                    'get_all' => 'Récupérer toutes les réservations',
                    'get' => 'Récupérer une réservation spécifique (requiert id)',
                    'check_availability' => 'Vérifier la disponibilité (requiert date_arrivee et date_depart)'
                ],
                'usage_examples' => [
                    '?action=get_all',
                    '?action=check_availability&date_arrivee=2024-01-01&date_depart=2024-01-05',
                    '?action=get&id=1'
                ]
            ]);
            exit;
        }

        // Actions publiques (ne nécessitent pas d'authentification)
        $publicActions = ['check_availability'];
        
        // Vérifier l'authentification pour les actions privées
        if (!in_array($action, $publicActions)) {
            checkAPIAuth();
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
                if (empty($id)) {
                    echo json_encode([
                        'success' => false, 
                        'error' => 'Paramètre id manquant pour cette action'
                    ]);
                    break;
                }
                echo json_encode($system->getReservationById($id));
                break;

            case 'check_availability':
                $date_arrivee = $_GET['date_arrivee'] ?? '';
                $date_depart = $_GET['date_depart'] ?? '';
                
                if (empty($date_arrivee) || empty($date_depart)) {
                    echo json_encode([
                        'success' => false, 
                        'error' => 'Dates manquantes pour la vérification de disponibilité',
                        'required_parameters' => ['date_arrivee', 'date_depart']
                    ]);
                    break;
                }
                
                echo json_encode($system->checkAvailability($date_arrivee, $date_depart));
                break;

            default:
                echo json_encode([
                    'success' => false, 
                    'error' => 'Action non reconnue: ' . $action,
                    'available_actions' => ['get_all', 'get', 'check_availability']
                ]);
        }
    } else {
        echo json_encode([
            'success' => false, 
            'error' => 'Méthode non supportée: ' . $_SERVER['REQUEST_METHOD'],
            'supported_methods' => ['GET']
        ]);
    }
} catch (Exception $e) {
    error_log("Exception in reservation_system: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Debug détaillé
error_log("=== RESERVATION SYSTEM ===");
error_log("Session ID: " . session_id());
error_log("Session Data: " . print_r($_SESSION, true));
error_log("GET: " . print_r($_GET, true));


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

// Fonction d'authentification améliorée avec debug
function checkAPIAuth() {
    error_log("=== CHECK AUTH ===");
    error_log("Session admin_logged_in: " . (isset($_SESSION['admin_logged_in']) ? 'SET' : 'NOT SET'));
    error_log("Session value: " . ($_SESSION['admin_logged_in'] ? 'TRUE' : 'FALSE'));
    
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        error_log("AUTH FAILED - Redirection vers login.php");
        http_response_code(401);
        echo json_encode([
            'success' => false, 
            'error' => 'Non authentifié',
            'redirect' => 'login.php'
        ]);
        exit;
    }
    error_log("AUTH SUCCESS");
}

class AdminReservationSystem {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // [Garder toutes les méthodes existantes...]
    // Récupérer toutes les réservations avec filtres
    public function getAllReservations($filters = []) {
        try {
            $query = "
                SELECT 
                    r.*,
                    c.nom,
                    c.prenom,
                    c.email,
                    c.telephone,
                    GROUP_CONCAT(DISTINCT ch.numeroChambre) as chambres,
                    GROUP_CONCAT(DISTINCT ch.type_chambre) as types_chambres
                FROM reservations r
                INNER JOIN clients c ON r.idClient = c.idClient
                LEFT JOIN reservation_chambres rc ON r.idReservation = rc.idReservation
                LEFT JOIN chambres ch ON rc.idChambre = ch.idChambre
                WHERE 1=1
            ";
            
            $params = [];
            
            if (!empty($filters['status'])) {
                $query .= " AND r.etat_reservation = :status";
                $params[':status'] = $filters['status'];
            }
            
            if (!empty($filters['date'])) {
                $query .= " AND r.date_arrivee <= :date AND r.date_depart >= :date";
                $params[':date'] = $filters['date'];
            }
            
            if (!empty($filters['search'])) {
                $query .= " AND (c.nom LIKE :search OR c.prenom LIKE :search OR c.email LIKE :search)";
                $params[':search'] = "%" . $filters['search'] . "%";
            }
            
            $query .= " GROUP BY r.idReservation ORDER BY r.date_reservation DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            
            $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'data' => $reservations
            ];
            
        } catch (PDOException $e) {
            error_log("Erreur getAllReservations: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Erreur lors de la récupération des réservations'
            ];
        }
    }

    // Vérifier la disponibilité des chambres
    public function checkAvailability($date_arrivee, $date_depart) {
        try {
            $query = "
                SELECT 
                    c.idChambre,
                    c.numeroChambre,
                    c.type_chambre,
                    c.capacite,
                    c.prix_nuit,
                    c.description
                FROM chambres c
                WHERE c.disponible = 1
                AND c.idChambre NOT IN (
                    SELECT DISTINCT rc.idChambre
                    FROM reservation_chambres rc
                    INNER JOIN reservations r ON rc.idReservation = r.idReservation
                    WHERE r.etat_reservation IN ('confirme', 'en cours')
                    AND (
                        (r.date_arrivee BETWEEN :date_arrivee AND DATE_SUB(:date_depart, INTERVAL 1 DAY))
                        OR (r.date_depart BETWEEN DATE_ADD(:date_arrivee, INTERVAL 1 DAY) AND :date_depart)
                        OR (:date_arrivee BETWEEN r.date_arrivee AND DATE_SUB(r.date_depart, INTERVAL 1 DAY))
                    )
                )
                ORDER BY c.numeroChambre
            ";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                ':date_arrivee' => $date_arrivee,
                ':date_depart' => $date_depart
            ]);
            
            $chambres_disponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'data' => $chambres_disponibles,
                'count' => count($chambres_disponibles)
            ];
            
        } catch (PDOException $e) {
            error_log("Erreur checkAvailability: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Erreur lors de la vérification de la disponibilité'
            ];
        }
    }

    // Autres méthodes (createReservation, updateReservation, etc.)
    // ... [Vos méthodes existantes] ...
}

// TRAITEMENT DES REQUÊTES
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? '';
        $system = new AdminReservationSystem();

        error_log("=== GET REQUEST ===");
        error_log("Action: " . $action);
        error_log("GET params: " . print_r($_GET, true));

        // Actions publiques (ne nécessitent pas d'authentification)
        $publicActions = ['check_availability'];
        
        // Vérifier l'authentification pour les actions privées
        if (!in_array($action, $publicActions)) {
            checkAPIAuth();
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
                
                if (empty($date_arrivee) || empty($date_depart)) {
                    echo json_encode([
                        'success' => false, 
                        'error' => 'Dates manquantes pour la vérification de disponibilité'
                    ]);
                    break;
                }
                
                echo json_encode($system->checkAvailability($date_arrivee, $date_depart));
                break;

            default:
                echo json_encode(['success' => false, 'error' => 'Action non reconnue: ' . $action]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Méthode non supportée: ' . $_SERVER['REQUEST_METHOD']]);
    }
} catch (Exception $e) {
    error_log("Exception in reservation_system: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}
?>