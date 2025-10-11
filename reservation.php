<?php
header('Content-Type: application/json');

// Gestion des erreurs
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Gestion CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Répondre immédiatement aux requêtes OPTIONS pour CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

class Database {
    private $host = "localhost";
    private $db_name = "hotel";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8",
                $this->username, 
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            error_log("Connection error: " . $exception->getMessage());
            return null;
        }
        return $this->conn;
    }
}

class ReservationSystem {
    private $conn;
    private $table_reservations = 'reservations';
    private $table_chambres = 'chambres';
    private $table_reservation_chambres = 'reservation_chambres';
    private $table_clients = 'clients';

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
        
        if (!$this->conn) {
            $this->sendError("Impossible de se connecter à la base de données");
        }
    }

    private function sendError($message, $code = 500) {
        http_response_code($code);
        echo json_encode(['error' => $message]);
        exit;
    }

    // Récupérer les réservations pour le calendrier
    public function getReservationsForCalendar($month, $year) {
        try {
            // Formater le mois sur 2 chiffres
            $month = str_pad($month, 2, '0', STR_PAD_LEFT);
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
            
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $result ?: [];
            
        } catch (Exception $e) {
            error_log("Erreur getReservationsForCalendar: " . $e->getMessage());
            return [];
        }
    }

    // Vérifier la disponibilité des chambres
    public function checkAvailability($date_arrivee, $date_depart) {
        try {
            // Vérifier d'abord si les dates sont valides
            if (!$this->validateDates($date_arrivee, $date_depart)) {
                return [];
            }

            // Vérifier s'il y a des réservations actives
            $checkQuery = "SELECT COUNT(*) as count_reservations FROM " . $this->table_reservations . " 
                          WHERE etat_reservation IN ('en attente', 'confirme')";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->execute();
            $reservationCount = $checkStmt->fetch(PDO::FETCH_ASSOC)['count_reservations'];
            
            if ($reservationCount == 0) {
                // Aucune réservation, toutes les chambres disponibles sont libres
                $query = "SELECT c.idChambre, c.numeroChambre, c.type_chambre, c.prix_nuit, c.capacite
                          FROM " . $this->table_chambres . " c
                          WHERE c.disponible = 1";
            } else {
                // Vérifier les chambres non réservées pour la période
                $query = "SELECT c.idChambre, c.numeroChambre, c.type_chambre, c.prix_nuit, c.capacite
                          FROM " . $this->table_chambres . " c
                          WHERE c.disponible = 1 
                          AND c.idChambre NOT IN (
                              SELECT DISTINCT rc.idChambre 
                              FROM " . $this->table_reservation_chambres . " rc
                              INNER JOIN " . $this->table_reservations . " r ON rc.idReservation = r.idReservation
                              WHERE r.etat_reservation IN ('en attente', 'confirme')
                              AND (
                                  (r.date_arrivee < :date_depart AND r.date_depart > :date_arrivee)
                              )
                          )";
            }
            
            $stmt = $this->conn->prepare($query);
            if ($reservationCount > 0) {
                $stmt->bindParam(':date_arrivee', $date_arrivee);
                $stmt->bindParam(':date_depart', $date_depart);
            }
            $stmt->execute();
            
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $result ?: [];
            
        } catch (Exception $e) {
            error_log("Erreur checkAvailability: " . $e->getMessage());
            return [];
        }
    }

    private function validateDates($date_arrivee, $date_depart) {
        if (empty($date_arrivee) || empty($date_depart)) {
            return false;
        }
        
        $arrivee = DateTime::createFromFormat('Y-m-d', $date_arrivee);
        $depart = DateTime::createFromFormat('Y-m-d', $date_depart);
        
        if (!$arrivee || !$depart) {
            return false;
        }
        
        if ($arrivee >= $depart) {
            return false;
        }
        
        return true;
    }

    public function createReservation($data) {
        try {
            $this->conn->beginTransaction();

            // Vérifier si le client existe déjà par email
            $clientResult = $this->findOrCreateClient($data);
            $client_id = $clientResult['client_id'];

            // Calculer le prix total
            $arrivee = new DateTime($data['date_arrivee']);
            $depart = new DateTime($data['date_depart']);
            $nights = $depart->diff($arrivee)->days;
            
            $prix_total = 0;
            foreach ($data['chambres'] as $chambre_id) {
                $chambre = $this->getChambreById($chambre_id);
                if ($chambre) {
                    $prix_total += $chambre['prix_nuit'] * $nights;
                }
            }

            // Créer la réservation
            $reservation_query = "INSERT INTO " . $this->table_reservations . " 
                                (date_arrivee, date_depart, nombre_personnes, prix_total, 
                                 etat_reservation, date_reservation, commentaire, idClient) 
                                VALUES (:date_arrivee, :date_depart, :nombre_personnes, :prix_total, 
                                        'en attente', NOW(), :commentaire, :idClient)";
            $reservation_stmt = $this->conn->prepare($reservation_query);
            $reservation_stmt->execute([
                ':date_arrivee' => $data['date_arrivee'],
                ':date_depart' => $data['date_depart'],
                ':nombre_personnes' => $data['nombre_personnes'],
                ':prix_total' => $prix_total,
                ':commentaire' => $data['commentaire'] ?? '',
                ':idClient' => $client_id
            ]);
            
            $reservation_id = $this->conn->lastInsertId();

            // Lier les chambres à la réservation
            foreach ($data['chambres'] as $chambre_id) {
                $this->linkChambreToReservation($reservation_id, $chambre_id);
            }

            $this->conn->commit();
            return ['success' => true, 'reservation_id' => $reservation_id];
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Erreur createReservation: " . $e->getMessage());
            return ['success' => false, 'error' => 'Erreur lors de la création de la réservation'];
        }
    }

    private function findOrCreateClient($data) {
        // Vérifier si le client existe par email
        $query = "SELECT idClient FROM " . $this->table_clients . " WHERE email = :email";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':email' => $data['email']]);
        
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($client) {
            // Mettre à jour le client existant
            $this->updateClient($client['idClient'], $data);
            return ['client_id' => $client['idClient']];
        } else {
            // Créer un nouveau client
            $client_id = $this->createNewClient($data);
            return ['client_id' => $client_id];
        }
    }

    private function createNewClient($data) {
        $client_query = "INSERT INTO " . $this->table_clients . " 
                        (nom, prenom, email, telephone, adresse, date_creation) 
                        VALUES (:nom, :prenom, :email, :telephone, :adresse, NOW())";
        $client_stmt = $this->conn->prepare($client_query);
        $client_stmt->execute([
            ':nom' => $data['nom'],
            ':prenom' => $data['prenom'],
            ':email' => $data['email'],
            ':telephone' => $data['telephone'] ?? '',
            ':adresse' => $data['adresse'] ?? ''
        ]);
        return $this->conn->lastInsertId();
    }

    private function updateClient($client_id, $data) {
        $update_query = "UPDATE " . $this->table_clients . " 
                        SET nom = :nom, prenom = :prenom, telephone = :telephone, adresse = :adresse 
                        WHERE idClient = :idClient";
        
        $stmt = $this->conn->prepare($update_query);
        $stmt->execute([
            ':nom' => $data['nom'],
            ':prenom' => $data['prenom'],
            ':telephone' => $data['telephone'] ?? '',
            ':adresse' => $data['adresse'] ?? '',
            ':idClient' => $client_id
        ]);
    }

    private function getChambreById($id) {
        try {
            $query = "SELECT * FROM " . $this->table_chambres . " WHERE idChambre = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':id' => $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur getChambreById: " . $e->getMessage());
            return null;
        }
    }

    private function linkChambreToReservation($reservation_id, $chambre_id) {
        $query = "INSERT INTO " . $this->table_reservation_chambres . " (idReservation, idChambre) VALUES (:reservation_id, :chambre_id)";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':reservation_id' => $reservation_id,
            ':chambre_id' => $chambre_id
        ]);
    }

    // Récupérer toutes les chambres
    public function getAllChambres() {
        try {
            $query = "SELECT * FROM " . $this->table_chambres . " WHERE disponible = 1 ORDER BY numeroChambre";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $result ?: [];
            
        } catch (Exception $e) {
            error_log("Erreur getAllChambres: " . $e->getMessage());
            return [];
        }
    }

    // Méthode de debug
    public function debugState() {
        try {
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
        } catch (Exception $e) {
            error_log("Erreur debugState: " . $e->getMessage());
            return ['error' => 'Erreur debug'];
        }
    }
}

// Gestion des requêtes
try {
    $reservationSystem = new ReservationSystem();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['action'])) {
            switch ($_GET['action']) {
                case 'get_reservations':
                    $month = $_GET['month'] ?? date('m');
                    $year = $_GET['year'] ?? date('Y');
                    $result = $reservationSystem->getReservationsForCalendar($month, $year);
                    echo json_encode($result);
                    break;
                    
                case 'check_availability':
                    if (!isset($_GET['date_arrivee']) || !isset($_GET['date_depart'])) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Dates manquantes']);
                        break;
                    }
                    $result = $reservationSystem->checkAvailability($_GET['date_arrivee'], $_GET['date_depart']);
                    echo json_encode($result);
                    break;
                    
                case 'get_chambres':
                    $result = $reservationSystem->getAllChambres();
                    echo json_encode($result);
                    break;
                    
                case 'debug':
                    $result = $reservationSystem->debugState();
                    echo json_encode($result);
                    break;
                    
                default:
                    http_response_code(400);
                    echo json_encode(['error' => 'Action non reconnue']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Paramètre action manquant']);
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input && isset($input['action'])) {
            if ($input['action'] === 'create_reservation') {
                $result = $reservationSystem->createReservation($input['data']);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Action POST non reconnue']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Données POST invalides']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Méthode non autorisée']);
    }
    
} catch (Exception $e) {
    error_log("Erreur globale: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur interne du serveur']);
}
?>