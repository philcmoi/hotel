<?php
// admin-reservations.php - VERSION COMPLÈTE AVEC API ET INTERFACE

// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Inclure la configuration
require_once 'config.php';

// Vérifier l'authentification pour l'interface (sauf pour les appels API)
if (!isset($_GET['api']) && (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true)) {
    header('Location: login.php');
    exit;
}

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
        
        $query = "SELECT 
                    r.idReservation,
                    r.date_arrivee,
                    r.date_depart,
                    r.nombre_personnes,
                    r.prix_total,
                    r.etat_reservation,
                    r.date_reservation,
                    r.commentaire,
                    c.nom,
                    c.prenom,
                    c.email,
                    c.telephone,
                    GROUP_CONCAT(DISTINCT ch.numeroChambre) as chambres,
                    GROUP_CONCAT(DISTINCT ch.type_chambre) as types_chambres
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
            'total' => $total,
            'page' => $page,
            'totalPages' => ceil($total / $itemsPerPage)
        ];
    }

    // Mettre à jour le statut d'une réservation
    public function updateReservationStatus($reservationId, $status) {
        $allowedStatus = ['confirme', 'en attente', 'annulee'];
        if (!in_array($status, $allowedStatus)) {
            throw new Exception("Statut invalide: " . $status);
        }
        
        $query = "UPDATE reservations SET etat_reservation = :status WHERE idReservation = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $reservationId, PDO::PARAM_INT);
        
        $result = $stmt->execute();
        
        if ($result && $stmt->rowCount() > 0) {
            return [
                'success' => true,
                'message' => 'Statut mis à jour avec succès',
                'reservation_id' => $reservationId,
                'new_status' => $status
            ];
        } else {
            throw new Exception("Aucune réservation trouvée avec cet ID ou statut identique");
        }
    }

    // Récupérer les détails d'une réservation spécifique
    public function getReservationDetails($reservationId) {
        $query = "SELECT 
                    r.*,
                    c.nom,
                    c.prenom,
                    c.email,
                    c.telephone,
                    c.adresse,
                    GROUP_CONCAT(DISTINCT ch.numeroChambre) as chambres,
                    GROUP_CONCAT(DISTINCT ch.type_chambre) as types_chambres,
                    GROUP_CONCAT(DISTINCT ch.idChambre) as id_chambres
                  FROM reservations r
                  LEFT JOIN clients c ON r.idClient = c.idClient
                  LEFT JOIN reservation_chambres rc ON r.idReservation = rc.idReservation
                  LEFT JOIN chambres ch ON rc.idChambre = ch.idChambre
                  WHERE r.idReservation = :id
                  GROUP BY r.idReservation";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $reservationId, PDO::PARAM_INT);
        $stmt->execute();
        
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$reservation) {
            throw new Exception("Réservation non trouvée");
        }
        
        return $reservation;
    }
}

// =============================================
// TRAITEMENT DES REQUÊTES API
// =============================================
if (isset($_GET['api']) || $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    // Gérer les requêtes preflight OPTIONS pour CORS
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        exit(0);
    }

    try {
        $admin = new AdminReservations();
    } catch (Exception $e) {
        echo json_encode([
            'success' => false, 
            'error' => 'Erreur de connexion à la base de données: ' . $e->getMessage()
        ]);
        exit;
    }

    // Déterminer l'action
    $action = '';

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
        $action = $_GET['action'];
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
    }

    // Si aucune action n'est spécifiée
    if (empty($action)) {
        echo json_encode([
            'success' => false, 
            'error' => 'Action non reconnue ou manquante',
            'available_actions' => [
                'GET' => [
                    '?api=1&action=get_stats' => 'Statistiques',
                    '?api=1&action=get_reservations&page=1' => 'Réservations paginées',
                    '?api=1&action=get_reservation&id=1' => 'Détails réservation'
                ],
                'POST' => [
                    'update_status' => 'Mettre à jour le statut'
                ]
            ]
        ]);
        exit;
    }

    // Traiter l'action demandée
    try {
        $response = ['success' => false, 'error' => 'Action non traitée'];

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            switch ($action) {
                case 'get_stats':
                    $stats = $admin->getReservationStats();
                    $response = [
                        'success' => true, 
                        'data' => $stats
                    ];
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
                        'pagination' => [
                            'total' => $result['total'],
                            'page' => $result['page'],
                            'totalPages' => $result['totalPages']
                        ]
                    ];
                    break;

                case 'get_reservation':
                    if (isset($_GET['id'])) {
                        $reservationId = intval($_GET['id']);
                        $reservation = $admin->getReservationDetails($reservationId);
                        $response = [
                            'success' => true,
                            'data' => $reservation
                        ];
                    } else {
                        $response = [
                            'success' => false,
                            'error' => 'Paramètre id manquant'
                        ];
                    }
                    break;
                    
                default:
                    $response = [
                        'success' => false, 
                        'error' => 'Action GET inconnue: ' . $action
                    ];
                    break;
            }
        } 
        elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            
            switch ($action) {
                case 'update_status':
                    if (isset($input['reservation_id']) && isset($input['status'])) {
                        $reservationId = intval($input['reservation_id']);
                        $status = $input['status'];
                        
                        $result = $admin->updateReservationStatus($reservationId, $status);
                        $response = [
                            'success' => true,
                            'message' => $result['message'],
                            'reservation_id' => $result['reservation_id'],
                            'new_status' => $result['new_status']
                        ];
                    } else {
                        $response = [
                            'success' => false,
                            'error' => 'Paramètres manquants: reservation_id et status requis'
                        ];
                    }
                    break;
                    
                default:
                    $response = [
                        'success' => false, 
                        'error' => 'Action POST inconnue: ' . $action
                    ];
                    break;
            }
        }
    } catch (Exception $e) {
        error_log("Erreur AdminReservations: " . $e->getMessage());
        $response = [
            'success' => false, 
            'error' => $e->getMessage()
        ];
    }

    echo json_encode($response);
    exit;
}

// =============================================
// INTERFACE HTML (affichée si pas d'appel API)
// =============================================

// Récupérer les données pour l'interface
try {
    $adminSystem = new AdminReservations();
    $stats = $adminSystem->getReservationStats();
    
    // Récupérer les réservations pour la page courante
    $page = max(1, intval($_GET['page'] ?? 1));
    $status = $_GET['status'] ?? 'all';
    $search = $_GET['search'] ?? '';
    
    $filters = ['status' => $status, 'search' => $search];
    $reservationsData = $adminSystem->getAllReservations($page, 10, $filters);
    $reservations = $reservationsData['reservations'];
    $totalReservations = $reservationsData['total'];
    $totalPages = $reservationsData['totalPages'];
    
} catch (Exception $e) {
    $error = "Erreur: " . $e->getMessage();
    $stats = ['total' => 0, 'confirmed' => 0, 'pending' => 0, 'cancelled' => 0];
    $reservations = [];
    $totalReservations = 0;
    $totalPages = 1;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Réservations - <?php echo APP_NAME; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            line-height: 1.6;
        }

        .admin-container {
            min-height: 100vh;
        }

        .admin-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .btn-logout {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            transition: background 0.3s;
            font-size: 14px;
        }

        .btn-logout:hover {
            background: rgba(255,255,255,0.3);
        }

        .admin-main {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Cartes de statistiques */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid #667eea;
        }

        .stat-card.total { border-left-color: #667eea; }
        .stat-card.confirmed { border-left-color: #28a745; }
        .stat-card.pending { border-left-color: #ffc107; }
        .stat-card.cancelled { border-left-color: #dc3545; }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }

        /* Filtres et recherche */
        .filters-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .filter-form {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-input, .status-select {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
        }

        .search-input {
            flex: 1;
            min-width: 300px;
        }

        .btn-primary {
            background: #667eea;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
        }

        .btn-primary:hover {
            background: #5a6fd8;
        }

        /* Tableau des réservations */
        .table-container {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .reservations-table {
            width: 100%;
            border-collapse: collapse;
        }

        .reservations-table th,
        .reservations-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .reservations-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }

        .reservations-table tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-confirme { background: #d4edda; color: #155724; }
        .status-en-attente { background: #fff3cd; color: #856404; }
        .status-annulee { background: #f8d7da; color: #721c24; }

        /* Menu déroulant pour le statut */
        .status-select {
            padding: 6px 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            background: white;
            cursor: pointer;
            min-width: 140px;
        }

        .status-select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
        }

        /* Bouton d'action */
        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            margin-left: 5px;
        }

        .btn-update {
            background: #28a745;
            color: white;
        }

        .btn-update:hover {
            background: #218838;
        }

        .btn-cancel {
            background: #dc3545;
            color: white;
        }

        .btn-cancel:hover {
            background: #c82333;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
            padding: 20px;
        }

        .page-btn {
            padding: 8px 12px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            color: #333;
        }

        .page-btn.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .page-btn:hover:not(.active) {
            background: #f8f9fa;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .admin-main {
                padding: 1rem;
            }
            
            .header-content {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-input {
                min-width: auto;
            }
            
            .reservations-table {
                font-size: 14px;
            }
            
            .status-select {
                min-width: 120px;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <div class="header-content">
                <h1>📋 Gestion des Réservations - <?php echo APP_NAME; ?></h1>
                <div class="user-info">
                    <span>Connecté en tant que <strong><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Administrateur'); ?></strong></span>
                    <a href="admin-interface.php" class="btn-logout">📊 Tableau de bord</a>
                    <a href="logout.php" class="btn-logout">🚪 Déconnexion</a>
                </div>
            </div>
        </header>

        <main class="admin-main">
            <?php if (isset($error)): ?>
                <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Cartes de statistiques -->
            <div class="stats-grid">
                <div class="stat-card total">
                    <div class="stat-number"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total Réservations</div>
                </div>
                <div class="stat-card confirmed">
                    <div class="stat-number"><?php echo $stats['confirmed']; ?></div>
                    <div class="stat-label">Confirmées</div>
                </div>
                <div class="stat-card pending">
                    <div class="stat-number"><?php echo $stats['pending']; ?></div>
                    <div class="stat-label">En Attente</div>
                </div>
                <div class="stat-card cancelled">
                    <div class="stat-number"><?php echo $stats['cancelled']; ?></div>
                    <div class="stat-label">Annulées</div>
                </div>
            </div>

            <!-- Filtres et recherche -->
            <div class="filters-container">
                <form method="GET" class="filter-form">
                    <input 
                        type="text" 
                        name="search" 
                        class="search-input" 
                        placeholder="🔍 Rechercher par nom, email, ID réservation..."
                        value="<?php echo htmlspecialchars($search); ?>"
                    >
                    <select name="status" class="status-select">
                        <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>Tous les statuts</option>
                        <option value="confirme" <?php echo $status === 'confirme' ? 'selected' : ''; ?>>Confirmées</option>
                        <option value="en attente" <?php echo $status === 'en attente' ? 'selected' : ''; ?>>En attente</option>
                        <option value="annulee" <?php echo $status === 'annulee' ? 'selected' : ''; ?>>Annulées</option>
                    </select>
                    <button type="submit" class="btn-primary">🔍 Appliquer les filtres</button>
                    <?php if (!empty($search) || $status !== 'all'): ?>
                        <a href="admin-reservations.php" class="btn-primary" style="background: #6c757d;">❌ Effacer</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Tableau des réservations -->
            <div class="table-container">
                <table class="reservations-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Client</th>
                            <th>Dates</th>
                            <th>Chambres</th>
                            <th>Personnes</th>
                            <th>Prix</th>
                            <th>Statut</th>
                            <th>Actions</th>
                            <th>Date Réservation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reservations)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 40px; color: #6c757d;">
                                    <div style="font-size: 48px; margin-bottom: 15px;">📭</div>
                                    <p>Aucune réservation trouvée</p>
                                    <?php if (!empty($search) || $status !== 'all'): ?>
                                        <small>Essayez de modifier vos critères de recherche</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($reservations as $reservation): ?>
                                <tr data-reservation-id="<?php echo $reservation['idReservation']; ?>">
                                    <td><strong>#<?php echo $reservation['idReservation']; ?></strong></td>
                                    <td>
                                        <div><strong><?php echo htmlspecialchars($reservation['prenom'] . ' ' . $reservation['nom']); ?></strong></div>
                                        <div style="font-size: 12px; color: #666;"><?php echo htmlspecialchars($reservation['email']); ?></div>
                                    </td>
                                    <td>
                                        <div><strong><?php echo date('d/m/Y', strtotime($reservation['date_arrivee'])); ?></strong></div>
                                        <div style="font-size: 12px; color: #666;">au <?php echo date('d/m/Y', strtotime($reservation['date_depart'])); ?></div>
                                    </td>
                                    <td>
                                        <?php if (!empty($reservation['chambres'])): ?>
                                            <div><strong><?php echo $reservation['chambres']; ?></strong></div>
                                            <div style="font-size: 12px; color: #666;"><?php echo $reservation['types_chambres']; ?></div>
                                        <?php else: ?>
                                            <span style="color: #999;">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $reservation['nombre_personnes']; ?></td>
                                    <td><strong><?php echo $reservation['prix_total']; ?>€</strong></td>
                                    <td>
                                        <span class="status-badge status-<?php echo str_replace(' ', '-', $reservation['etat_reservation']); ?>" id="status-badge-<?php echo $reservation['idReservation']; ?>">
                                            <?php echo $reservation['etat_reservation']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <select class="status-select" id="status-select-<?php echo $reservation['idReservation']; ?>" data-current-status="<?php echo $reservation['etat_reservation']; ?>">
                                            <option value="confirme" <?php echo $reservation['etat_reservation'] === 'confirme' ? 'selected' : ''; ?>>Confirmée</option>
                                            <option value="en attente" <?php echo $reservation['etat_reservation'] === 'en attente' ? 'selected' : ''; ?>>En attente</option>
                                            <option value="annulee" <?php echo $reservation['etat_reservation'] === 'annulee' ? 'selected' : ''; ?>>Annulée</option>
                                        </select>
                                        <button class="action-btn btn-update" onclick="updateReservationStatus(<?php echo $reservation['idReservation']; ?>)">
                                            💾
                                        </button>
                                    </td>
                                    <td>
                                        <div><?php echo date('d/m/Y', strtotime($reservation['date_reservation'])); ?></div>
                                        <div style="font-size: 12px; color: #666;"><?php echo date('H:i', strtotime($reservation['date_reservation'])); ?></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>" class="page-btn">‹ Précédent</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>" class="page-btn <?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>" class="page-btn">Suivant ›</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // Fonction pour mettre à jour le statut d'une réservation
        async function updateReservationStatus(reservationId) {
            const selectElement = document.getElementById('status-select-' + reservationId);
            const newStatus = selectElement.value;
            const currentStatus = selectElement.dataset.currentStatus;
            
            if (newStatus === currentStatus) {
                alert('Le statut est déjà défini sur "' + newStatus + '"');
                return;
            }

            if (!confirm(`Êtes-vous sûr de vouloir changer le statut de la réservation #${reservationId} de "${currentStatus}" à "${newStatus}" ?`)) {
                // Remettre l'ancienne valeur si annulation
                selectElement.value = currentStatus;
                return;
            }

            try {
                const response = await fetch('admin-reservations.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'update_status',
                        reservation_id: reservationId,
                        status: newStatus
                    })
                });

                const result = await response.json();

                if (result.success) {
                    // Mettre à jour l'affichage du statut
                    const statusBadge = document.getElementById('status-badge-' + reservationId);
                    statusBadge.textContent = newStatus;
                    statusBadge.className = 'status-badge status-' + newStatus.replace(' ', '-');
                    
                    // Mettre à jour le statut courant
                    selectElement.dataset.currentStatus = newStatus;
                    
                    // Afficher un message de succès
                    alert(result.message);
                    
                    // Recharger les statistiques (optionnel)
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    alert('Erreur: ' + result.error);
                    // Remettre l'ancien statut en cas d'erreur
                    selectElement.value = currentStatus;
                }
            } catch (error) {
                console.error('Erreur:', error);
                alert('Erreur lors de la mise à jour du statut');
                // Remettre l'ancien statut en cas d'erreur
                selectElement.value = currentStatus;
            }
        }

        // Mettre à jour automatiquement le statut lors du changement (optionnel)
        document.addEventListener('DOMContentLoaded', function() {
            // Si vous voulez que le changement soit automatique sans bouton, décommentez ce code
            /*
            const statusSelects = document.querySelectorAll('.status-select');
            statusSelects.forEach(select => {
                select.addEventListener('change', function() {
                    const reservationId = this.id.replace('status-select-', '');
                    updateReservationStatus(reservationId);
                });
            });
            */
        });
    </script>
</body>
</html>
