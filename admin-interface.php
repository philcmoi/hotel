<?php
session_start();

// Vérifier l'authentification
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

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
        } catch(PDOException $exception) {
            error_log("Erreur de connexion BD: " . $exception->getMessage());
            throw new Exception("Erreur de connexion à la base de données");
        }
        return $this->conn;
    }
}

class AdminDashboard {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // Récupérer les statistiques générales
    public function getDashboardStats() {
        $stats = [];
        
        // Total des réservations
        $query = "SELECT COUNT(*) as total FROM reservations";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['total_reservations'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Réservations du mois
        $query = "SELECT COUNT(*) as total FROM reservations WHERE MONTH(date_reservation) = MONTH(CURRENT_DATE()) AND YEAR(date_reservation) = YEAR(CURRENT_DATE())";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['month_reservations'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Chiffre d'affaires du mois
        $query = "SELECT COALESCE(SUM(prix_total), 0) as total FROM reservations WHERE MONTH(date_reservation) = MONTH(CURRENT_DATE()) AND YEAR(date_reservation) = YEAR(CURRENT_DATE()) AND etat_reservation = 'confirme'";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['month_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Total des clients
        $query = "SELECT COUNT(*) as total FROM clients";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['total_clients'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        return $stats;
    }

    // Récupérer les réservations récentes
    public function getRecentReservations($limit = 5) {
        $query = "SELECT r.idReservation, r.date_arrivee, r.date_depart, r.etat_reservation, 
                         c.nom, c.prenom, c.email,
                         GROUP_CONCAT(ch.numeroChambre) as chambres
                  FROM reservations r
                  LEFT JOIN clients c ON r.idClient = c.idClient
                  LEFT JOIN reservation_chambres rc ON r.idReservation = rc.idReservation
                  LEFT JOIN chambres ch ON rc.idChambre = ch.idChambre
                  GROUP BY r.idReservation
                  ORDER BY r.date_reservation DESC 
                  LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Exporter les données en CSV
    public function exportData($type, $startDate = null, $endDate = null) {
        switch($type) {
            case 'reservations':
                $query = "SELECT r.*, c.nom, c.prenom, c.email, c.telephone,
                                 GROUP_CONCAT(ch.numeroChambre) as chambres
                          FROM reservations r
                          LEFT JOIN clients c ON r.idClient = c.idClient
                          LEFT JOIN reservation_chambres rc ON r.idReservation = rc.idReservation
                          LEFT JOIN chambres ch ON rc.idChambre = ch.idChambre";
                
                if ($startDate && $endDate) {
                    $query .= " WHERE r.date_reservation BETWEEN :start_date AND :end_date";
                }
                
                $query .= " GROUP BY r.idReservation ORDER BY r.date_reservation DESC";
                break;
                
            case 'clients':
                $query = "SELECT * FROM clients ORDER BY date_creation DESC";
                break;
                
            case 'chambres':
                $query = "SELECT * FROM chambres ORDER BY numeroChambre ASC";
                break;
                
            default:
                throw new Exception("Type d'export non valide");
        }
        
        $stmt = $this->conn->prepare($query);
        
        if ($startDate && $endDate) {
            $stmt->bindParam(':start_date', $startDate);
            $stmt->bindParam(':end_date', $endDate);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

try {
    $dashboard = new AdminDashboard();
    $stats = $dashboard->getDashboardStats();
    $recentReservations = $dashboard->getRecentReservations();
} catch (Exception $e) {
    $error = $e->getMessage();
    $stats = ['total_reservations' => 0, 'month_reservations' => 0, 'month_revenue' => 0, 'total_clients' => 0];
    $recentReservations = [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --secondary: #7209b7;
            --success: #4cc9f0;
            --warning: #f72585;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --border: #dee2e6;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f7fb;
            color: var(--dark);
            line-height: 1.6;
        }

        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            z-index: 100;
        }

        .logo {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
        }

        .logo h1 {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .logo span {
            color: var(--success);
        }

        .nav-links {
            list-style: none;
        }

        .nav-links li {
            padding: 12px 20px;
            transition: all 0.3s ease;
        }

        .nav-links li:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .nav-links li.active {
            background-color: rgba(255, 255, 255, 0.2);
            border-left: 4px solid var(--success);
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-links i {
            font-size: 1.2rem;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border);
        }

        .header h2 {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--dark);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card.reservations {
            border-top: 4px solid var(--primary);
        }

        .stat-card.month-reservations {
            border-top: 4px solid var(--success);
        }

        .stat-card.revenue {
            border-top: 4px solid var(--warning);
        }

        .stat-card.clients {
            border-top: 4px solid #6c757d;
        }

        .stat-icon {
            font-size: 2rem;
            margin-bottom: 15px;
            opacity: 0.8;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin: 5px 0;
        }

        .stat-label {
            color: var(--gray);
            font-size: 0.9rem;
        }

        /* Quick Actions */
        .quick-actions {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .quick-actions h3 {
            margin-bottom: 20px;
            font-size: 1.3rem;
            font-weight: 600;
        }

        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            font-size: 0.95rem;
            text-align: left;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
        }

        .btn-success {
            background-color: var(--success);
            color: white;
        }

        .btn-success:hover {
            background-color: #3ab8dd;
        }

        .btn-warning {
            background-color: var(--warning);
            color: white;
        }

        .btn-warning:hover {
            background-color: #e51677;
        }

        /* Recent Reservations */
        .recent-reservations {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid var(--border);
        }

        .section-header h3 {
            font-size: 1.3rem;
            font-weight: 600;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background-color: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--dark);
            border-bottom: 1px solid var(--border);
        }

        td {
            padding: 15px;
            border-bottom: 1px solid var(--border);
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover {
            background-color: #f8f9fa;
        }

        .status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            text-align: center;
            display: inline-block;
        }

        .status.confirme {
            background-color: rgba(76, 201, 240, 0.1);
            color: #4cc9f0;
        }

        .status.en-attente {
            background-color: rgba(247, 37, 133, 0.1);
            color: #f72585;
        }

        .status.annulee {
            background-color: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow-y: auto;
        }

        .modal-content {
            background: white;
            border-radius: 10px;
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.3rem;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 20px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            position: sticky;
            bottom: 0;
            background: white;
            z-index: 10;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray);
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(67, 97, 238, 0.2);
        }

        .form-select {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 1rem;
            background: white;
            cursor: pointer;
        }

        .form-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(67, 97, 238, 0.2);
        }

        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--border);
            color: var(--dark);
        }

        .btn-outline:hover {
            background-color: #f8f9fa;
        }

        /* Messages */
        .error-message {
            background-color: #ff4757;
            color: white;
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: none;
        }

        .success-message {
            background-color: #2ed573;
            color: white;
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: none;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .admin-container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                height: auto;
            }

            .nav-links {
                display: flex;
                overflow-x: auto;
            }

            .nav-links li {
                white-space: nowrap;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 15px;
            }

            .header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }

            .stats-container {
                grid-template-columns: 1fr 1fr;
            }

            .action-buttons {
                grid-template-columns: 1fr;
            }

            .modal {
                padding: 10px;
                align-items: flex-start;
            }

            .modal-content {
                max-height: 95vh;
                margin-top: 20px;
                margin-bottom: 20px;
            }

            .modal-body {
                padding: 15px;
            }

            .modal-footer {
                padding: 15px;
                flex-direction: column;
            }

            .modal-footer .btn {
                width: 100%;
                justify-content: center;
            }

            table {
                font-size: 14px;
            }

            th, td {
                padding: 10px 8px;
            }
        }

        @media (max-width: 480px) {
            .stats-container {
                grid-template-columns: 1fr;
            }

            .main-content {
                padding: 10px;
            }

            .header h2 {
                font-size: 1.5rem;
            }

            .stat-card {
                padding: 20px;
            }

            .stat-value {
                font-size: 1.8rem;
            }

            .modal-content {
                border-radius: 8px;
            }

            .modal-header, .modal-body, .modal-footer {
                padding: 15px;
            }

            .form-control, .form-select {
                padding: 10px;
                font-size: 16px; /* Empêche le zoom sur iOS */
            }
        }

        /* Styles spécifiques pour les très petits écrans */
        @media (max-width: 360px) {
            .modal {
                padding: 5px;
            }

            .modal-content {
                max-width: 100%;
                margin: 10px;
            }

            .stat-value {
                font-size: 1.6rem;
            }

            .btn {
                padding: 10px 15px;
                font-size: 0.9rem;
            }
        }

        /* Empêcher le body de scroll quand le modal est ouvert */
        body.modal-open {
            overflow: hidden;
        }

        /* Animation d'ouverture du modal */
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-content {
            animation: modalSlideIn 0.3s ease-out;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">
                <h1>Hôtel<span>Premium</span></h1>
            </div>
            <ul class="nav-links">
                <li class="active">
                    <a href="#"><i class="fas fa-tachometer-alt"></i> Tableau de bord</a>
                </li>
                <li>
                    <a href="admin-chambres.html"><i class="fas fa-bed"></i> Chambres</a>
                </li>
                <li>
                    <a href="admin-reservations.php"><i class="fas fa-calendar-check"></i> Réservations</a>
                </li>
                <li>
                    <a href="#"><i class="fas fa-users"></i> Clients</a>
                </li>
                <li>
                    <a href="#"><i class="fas fa-chart-bar"></i> Statistiques</a>
                </li>
                <li>
                    <a href="#"><i class="fas fa-cog"></i> Paramètres</a>
                </li>
                <li>
                    <a href="#"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
                </li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h2>Tableau de Bord Administrateur</h2>
                <div class="user-info">
                    <div class="user-avatar">AD</div>
                    <span>Admin</span>
                </div>
            </div>

            <!-- Messages -->
            <div class="error-message" id="error-message"></div>
            <div class="success-message" id="success-message"></div>

            <?php if (isset($error)): ?>
                <div class="error-message" style="display: block;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="stats-container">
                <div class="stat-card reservations">
                    <div class="stat-icon">📊</div>
                    <div class="stat-value"><?php echo $stats['total_reservations']; ?></div>
                    <div class="stat-label">Total Réservations</div>
                </div>
                <div class="stat-card month-reservations">
                    <div class="stat-icon">📅</div>
                    <div class="stat-value"><?php echo $stats['month_reservations']; ?></div>
                    <div class="stat-label">Réservations ce mois</div>
                </div>
                <div class="stat-card revenue">
                    <div class="stat-icon">💰</div>
                    <div class="stat-value"><?php echo number_format($stats['month_revenue'], 0, ',', ' '); ?> €</div>
                    <div class="stat-label">Chiffre d'affaires mensuel</div>
                </div>
                <div class="stat-card clients">
                    <div class="stat-icon">👥</div>
                    <div class="stat-value"><?php echo $stats['total_clients']; ?></div>
                    <div class="stat-label">Clients inscrits</div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <h3>Actions Rapides</h3>
                <div class="action-buttons">
                    <button class="btn btn-primary" onclick="showExportModal()">
                        <i class="fas fa-download"></i>
                        <span>Exporter les données</span>
                    </button>
                    <a href="admin-reservations.php" class="btn btn-success">
                        <i class="fas fa-calendar-plus"></i>
                        <span>Gérer les réservations</span>
                    </a>
                    <a href="admin-chambres.html" class="btn btn-warning">
                        <i class="fas fa-bed"></i>
                        <span>Gérer les chambres</span>
                    </a>
                </div>
            </div>

            <!-- Recent Reservations -->
            <div class="recent-reservations">
                <div class="section-header">
                    <h3>Réservations Récentes</h3>
                    <a href="admin-reservations.php" class="btn btn-outline" style="text-decoration: none;">
                        Voir tout
                    </a>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Client</th>
                            <th>Dates</th>
                            <th>Chambres</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentReservations)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 40px; color: var(--gray);">
                                    Aucune réservation récente
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentReservations as $reservation): ?>
                                <tr>
                                    <td><strong>#<?php echo $reservation['idReservation']; ?></strong></td>
                                    <td>
                                        <div><?php echo htmlspecialchars($reservation['prenom'] . ' ' . $reservation['nom']); ?></div>
                                        <div style="font-size: 0.8rem; color: var(--gray);"><?php echo htmlspecialchars($reservation['email']); ?></div>
                                    </td>
                                    <td>
                                        <div><?php echo date('d/m/Y', strtotime($reservation['date_arrivee'])); ?></div>
                                        <div style="font-size: 0.8rem; color: var(--gray);">au <?php echo date('d/m/Y', strtotime($reservation['date_depart'])); ?></div>
                                    </td>
                                    <td>
                                        <?php echo !empty($reservation['chambres']) ? $reservation['chambres'] : 'N/A'; ?>
                                    </td>
                                    <td>
                                        <span class="status <?php echo str_replace(' ', '-', $reservation['etat_reservation']); ?>">
                                            <?php echo $reservation['etat_reservation']; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal d'export -->
    <div class="modal" id="export-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Exporter les données</h3>
                <button class="close-btn" onclick="hideExportModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="export-form">
                    <div class="form-group">
                        <label for="export-type">Type de données à exporter</label>
                        <select id="export-type" class="form-select" required>
                            <option value="">Sélectionnez un type</option>
                            <option value="reservations">Réservations</option>
                            <option value="clients">Clients</option>
                            <option value="chambres">Chambres</option>
                        </select>
                    </div>
                    <div class="form-group" id="date-range-group" style="display: none;">
                        <label for="start-date">Période (optionnel)</label>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <input type="date" id="start-date" class="form-control" placeholder="Date de début">
                            <input type="date" id="end-date" class="form-control" placeholder="Date de fin">
                        </div>
                        <small style="color: var(--gray); margin-top: 5px; display: block;">
                            Laisser vide pour exporter toutes les données
                        </small>
                    </div>
                    <div class="form-group">
                        <label for="export-format">Format d'export</label>
                        <select id="export-format" class="form-select" required>
                            <option value="csv">CSV (Excel)</option>
                            <option value="json">JSON</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="hideExportModal()">Annuler</button>
                <button class="btn btn-primary" onclick="exportData()">
                    <i class="fas fa-download"></i> Exporter
                </button>
            </div>
        </div>
    </div>

    <script>
        // Gestion du modal d'export
        function showExportModal() {
            const modal = document.getElementById('export-modal');
            modal.style.display = 'flex';
            document.body.classList.add('modal-open');
            
            // Reset du formulaire
            document.getElementById('export-form').reset();
            document.getElementById('date-range-group').style.display = 'none';
        }

        function hideExportModal() {
            const modal = document.getElementById('export-modal');
            modal.style.display = 'none';
            document.body.classList.remove('modal-open');
        }

        // Afficher/masquer la période selon le type d'export
        document.getElementById('export-type').addEventListener('change', function() {
            const dateRangeGroup = document.getElementById('date-range-group');
            if (this.value === 'reservations') {
                dateRangeGroup.style.display = 'block';
            } else {
                dateRangeGroup.style.display = 'none';
            }
        });

        // Fonction d'export
        function exportData() {
            const exportType = document.getElementById('export-type').value;
            const exportFormat = document.getElementById('export-format').value;
            const startDate = document.getElementById('start-date').value;
            const endDate = document.getElementById('end-date').value;

            if (!exportType) {
                showError('Veuillez sélectionner un type de données à exporter');
                return;
            }

            // Construction de l'URL
            let url = `export-data.php?type=${exportType}&format=${exportFormat}`;
            if (startDate && endDate) {
                url += `&start_date=${startDate}&end_date=${endDate}`;
            }

            // Ouvrir dans un nouvel onglet pour le téléchargement
            window.open(url, '_blank');
            hideExportModal();
            showSuccess('Export lancé avec succès');
        }

        // Fonctions utilitaires pour les messages
        function showError(message) {
            const errorDiv = document.getElementById('error-message');
            errorDiv.textContent = message;
            errorDiv.style.display = 'block';
            setTimeout(() => {
                errorDiv.style.display = 'none';
            }, 5000);
        }

        function showSuccess(message) {
            const successDiv = document.getElementById('success-message');
            successDiv.textContent = message;
            successDiv.style.display = 'block';
            setTimeout(() => {
                successDiv.style.display = 'none';
            }, 3000);
        }

        // Fermer le modal en cliquant à l'extérieur
        document.getElementById('export-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideExportModal();
            }
        });

        // Fermer le modal avec la touche Échap
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideExportModal();
            }
        });

        // Empêcher la fermeture accidentelle sur mobile
        document.addEventListener('touchstart', function(e) {
            const modal = document.getElementById('export-modal');
            if (modal.style.display === 'flex' && !modal.contains(e.target)) {
                e.preventDefault();
            }
        }, { passive: false });
    </script>
</body>
</html>