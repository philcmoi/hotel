<?php
// admin-interface.php - TABLEAU DE BORD ADMINISTRATEUR
session_start();

// Vérifier l'authentification
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Inclure la configuration
require_once 'config.php';

// Connexion à la base de données
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Récupérer les statistiques
try {
    // Statistiques des réservations
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_reservations,
            SUM(CASE WHEN etat_reservation = 'confirme' THEN 1 ELSE 0 END) as reservations_confirmees,
            SUM(CASE WHEN etat_reservation = 'en attente' THEN 1 ELSE 0 END) as reservations_attente,
            SUM(CASE WHEN etat_reservation = 'annulee' THEN 1 ELSE 0 END) as reservations_annulees
        FROM reservations
    ");
    $stats_reservations = $stmt->fetch(PDO::FETCH_ASSOC);

    // Statistiques des clients
    $stmt = $pdo->query("SELECT COUNT(*) as total_clients FROM clients");
    $stats_clients = $stmt->fetch(PDO::FETCH_ASSOC);

    // Statistiques des chambres
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_chambres,
            SUM(CASE WHEN disponible = 1 THEN 1 ELSE 0 END) as chambres_disponibles
        FROM chambres
    ");
    $stats_chambres = $stmt->fetch(PDO::FETCH_ASSOC);

    // Revenus totaux (réservations confirmées uniquement)
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(prix_total), 0) as revenus_totaux 
        FROM reservations 
        WHERE etat_reservation = 'confirme'
    ");
    $revenus = $stmt->fetch(PDO::FETCH_ASSOC);

    // Dernières réservations
    $stmt = $pdo->query("
        SELECT r.*, c.nom, c.prenom 
        FROM reservations r 
        LEFT JOIN clients c ON r.idClient = c.idClient 
        ORDER BY r.date_reservation DESC 
        LIMIT 5
    ");
    $dernieres_reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    $error = "Erreur lors de la récupération des données : " . $e->getMessage();
    // Initialiser les variables pour éviter les erreurs
    $stats_reservations = ['total_reservations' => 0, 'reservations_confirmees' => 0, 'reservations_attente' => 0, 'reservations_annulees' => 0];
    $stats_clients = ['total_clients' => 0];
    $stats_chambres = ['total_chambres' => 0, 'chambres_disponibles' => 0];
    $revenus = ['revenus_totaux' => 0];
    $dernieres_reservations = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord - <?php echo APP_NAME; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }

        .dashboard-container {
            min-height: 100vh;
            background: #f5f5f5;
        }

        .admin-header {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-bottom: 3px solid #667eea;
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
            background: #667eea;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            transition: background 0.3s;
            font-size: 14px;
        }

        .btn-logout:hover {
            background: #5a6fd8;
        }

        .dashboard-main {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .welcome-section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
        }

        .welcome-section h1 {
            color: #2c3e50;
            margin-bottom: 0.5rem;
            font-size: 2.5rem;
        }

        .welcome-section p {
            color: #7f8c8d;
            font-size: 1.1rem;
        }

        /* Grille des statistiques */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
            border-left: 5px solid;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .stat-card.reservations { border-left-color: #e74c3c; }
        .stat-card.clients { border-left-color: #3498db; }
        .stat-card.chambres { border-left-color: #2ecc71; }
        .stat-card.revenus { border-left-color: #f39c12; }

        .stat-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            color: #2c3e50;
        }

        .stat-label {
            color: #7f8c8d;
            font-size: 1.1rem;
            margin-bottom: 1rem;
        }

        .stat-details {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 10px;
            margin-top: 1rem;
        }

        .stat-detail {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        /* Grille des actions rapides */
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 3rem;
        }

        .action-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .action-card:hover {
            border-color: #667eea;
            transform: translateY(-3px);
        }

        .action-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #667eea;
        }

        .action-title {
            font-size: 1.3rem;
            margin-bottom: 1rem;
            color: #2c3e50;
        }

        .action-description {
            color: #7f8c8d;
            margin-bottom: 1.5rem;
            line-height: 1.5;
        }

        .btn-action {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
            transition: all 0.3s;
            cursor: pointer;
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        /* Section des dernières réservations */
        .recent-section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .section-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f8f9fa;
        }

        .section-header h2 {
            color: #2c3e50;
            font-size: 1.5rem;
        }

        .view-all {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .reservations-list {
            display: grid;
            gap: 1rem;
        }

        .reservation-item {
            display: grid;
            grid-template-columns: auto 1fr auto auto;
            gap: 1rem;
            align-items: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
            transition: background 0.3s;
        }

        .reservation-item:hover {
            background: #e9ecef;
        }

        .reservation-id {
            font-weight: bold;
            color: #667eea;
            font-size: 1.1rem;
        }

        .reservation-client {
            font-weight: 600;
            color: #2c3e50;
        }

        .reservation-dates {
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        .reservation-status {
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-confirme { background: #d4edda; color: #155724; }
        .status-attente { background: #fff3cd; color: #856404; }
        .status-annulee { background: #f8d7da; color: #721c24; }

        /* Messages d'erreur */
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            text-align: center;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-main {
                padding: 1rem;
            }
            
            .header-content {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .stats-grid,
            .actions-grid {
                grid-template-columns: 1fr;
            }
            
            .reservation-item {
                grid-template-columns: 1fr;
                text-align: center;
                gap: 0.5rem;
            }
            
            .welcome-section h1 {
                font-size: 2rem;
            }
        }

        @media (max-width: 480px) {
            .stat-card,
            .action-card {
                padding: 1.5rem;
            }
            
            .stat-number {
                font-size: 2rem;
            }
            
            .btn-action {
                padding: 10px 20px;
                font-size: 14px;
            }
        }

        /* Animation de chargement */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <header class="admin-header">
            <div class="header-content">
                <h1>🎯 Tableau de Bord - <?php echo APP_NAME; ?></h1>
                <div class="user-info">
                    <span>Connecté en tant que <strong><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Administrateur'); ?></strong></span>
                    <span>Dernière connexion : <?php echo date('d/m/Y H:i', $_SESSION['login_time'] ?? time()); ?></span>
                    <a href="logout.php" class="btn-logout">🚪 Déconnexion</a>
                </div>
            </div>
        </header>

        <main class="dashboard-main">
            <!-- Message d'erreur -->
            <?php if (isset($error)): ?>
                <div class="error-message">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Section de bienvenue -->
            <section class="welcome-section">
                <h1>Bienvenue dans votre espace d'administration</h1>
                <p>Gérez efficacement votre hôtel grâce à ce tableau de bord complet</p>
            </section>

            <!-- Grille des statistiques -->
            <section class="stats-grid">
                <!-- Carte Réservations -->
                <div class="stat-card reservations">
                    <div class="stat-icon">📅</div>
                    <div class="stat-number"><?php echo $stats_reservations['total_reservations']; ?></div>
                    <div class="stat-label">Réservations Total</div>
                    <div class="stat-details">
                        <div class="stat-detail">
                            <span>Confirmées :</span>
                            <span style="color: #27ae60;"><?php echo $stats_reservations['reservations_confirmees']; ?></span>
                        </div>
                        <div class="stat-detail">
                            <span>En attente :</span>
                            <span style="color: #f39c12;"><?php echo $stats_reservations['reservations_attente']; ?></span>
                        </div>
                        <div class="stat-detail">
                            <span>Annulées :</span>
                            <span style="color: #e74c3c;"><?php echo $stats_reservations['reservations_annulees']; ?></span>
                        </div>
                    </div>
                </div>

                <!-- Carte Clients -->
                <div class="stat-card clients">
                    <div class="stat-icon">👥</div>
                    <div class="stat-number"><?php echo $stats_clients['total_clients']; ?></div>
                    <div class="stat-label">Clients Inscrits</div>
                    <div class="stat-details">
                        <div class="stat-detail">
                            <span>Base de données clients</span>
                        </div>
                    </div>
                </div>

                <!-- Carte Chambres -->
                <div class="stat-card chambres">
                    <div class="stat-icon">🏨</div>
                    <div class="stat-number"><?php echo $stats_chambres['total_chambres']; ?></div>
                    <div class="stat-label">Chambres Total</div>
                    <div class="stat-details">
                        <div class="stat-detail">
                            <span>Disponibles :</span>
                            <span style="color: #27ae60;"><?php echo $stats_chambres['chambres_disponibles']; ?></span>
                        </div>
                        <div class="stat-detail">
                            <span>Occupées :</span>
                            <span style="color: #e74c3c;"><?php echo $stats_chambres['total_chambres'] - $stats_chambres['chambres_disponibles']; ?></span>
                        </div>
                    </div>
                </div>

                <!-- Carte Revenus -->
                <div class="stat-card revenus">
                    <div class="stat-icon">💰</div>
                    <div class="stat-number"><?php echo number_format($revenus['revenus_totaux'], 0, ',', ' '); ?>€</div>
                    <div class="stat-label">Revenus Totaux</div>
                    <div class="stat-details">
                        <div class="stat-detail">
                            <span>Réservations confirmées</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Grille des actions rapides -->
            <section class="actions-grid">
                <div class="action-card">
                    <div class="action-icon">📋</div>
                    <div class="action-title">Gestion des Réservations</div>
                    <div class="action-description">
                        Consultez, modifiez et gérez toutes les réservations de votre hôtel
                    </div>
                    <a href="admin-reservations.php" class="btn-action">Accéder aux Réservations</a>
                </div>

                <div class="action-card">
                    <div class="action-icon">👥</div>
                    <div class="action-title">Gestion des Clients</div>
                    <div class="action-description">
                        Gérez votre base de données clients et consultez leurs historiques
                    </div>
                    <a href="admin-clients.php" class="btn-action">Voir les Clients</a>
                </div>

                <div class="action-card">
                    <div class="action-icon">🏨</div>
                    <div class="action-title">Gestion des Chambres</div>
                    <div class="action-description">
                        Configurez les chambres, leurs disponibilités et leurs tarifs
                    </div>
                    <a href="admin-chambres.html" class="btn-action">Gérer les Chambres</a>
                </div>

                <div class="action-card">
                    <div class="action-icon">📊</div>
                    <div class="action-title">Rapports et Statistiques</div>
                    <div class="action-description">
                        Analysez les performances et générez des rapports détaillés
                    </div>
                    <a href="graphique-reservations.php" class="btn-action">Voir les Rapports</a>
                </div>
            </section>

            <!-- Dernières réservations -->
            <section class="recent-section">
                <div class="section-header">
                    <h2>📝 Dernières Réservations</h2>
                    <a href="admin-reservations.php" class="view-all">Voir tout →</a>
                </div>

                <div class="reservations-list">
                    <?php if (empty($dernieres_reservations)): ?>
                        <div style="text-align: center; padding: 2rem; color: #7f8c8d;">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">📭</div>
                            <p>Aucune réservation récente</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($dernieres_reservations as $reservation): ?>
                            <div class="reservation-item">
                                <div class="reservation-id">#<?php echo $reservation['idReservation']; ?></div>
                                <div class="reservation-client">
                                    <?php echo htmlspecialchars($reservation['prenom'] . ' ' . $reservation['nom']); ?>
                                </div>
                                <div class="reservation-dates">
                                    <?php echo date('d/m/Y', strtotime($reservation['date_arrivee'])); ?> - 
                                    <?php echo date('d/m/Y', strtotime($reservation['date_depart'])); ?>
                                </div>
                                <div class="reservation-status status-<?php echo str_replace(' ', '-', $reservation['etat_reservation']); ?>">
                                    <?php echo $reservation['etat_reservation']; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>

    <script>
        // Animation simple au chargement
        document.addEventListener('DOMContentLoaded', function() {
            const statCards = document.querySelectorAll('.stat-card');
            const actionCards = document.querySelectorAll('.action-card');
            
            // Animation des cartes de statistiques
            statCards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 200);
            });

            // Animation des cartes d'action
            actionCards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, (index * 100) + 600);
            });
        });

        // Initialisation des styles pour l'animation
        document.querySelectorAll('.stat-card, .action-card').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        });
    </script>

<!-- Ajoutez cette section dans votre admin-interface.php -->

<!-- Styles pour le modal d'export -->
<style>
.export-modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}
.export-modal-content {
    background-color: white;
    margin: 5% auto;
    padding: 30px;
    border-radius: 10px;
    width: 80%;
    max-width: 800px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    position: relative;
}
.export-close {
    position: absolute;
    top: 15px;
    right: 20px;
    font-size: 28px;
    cursor: pointer;
    color: #aaa;
    background: none;
    border: none;
}
.export-close:hover {
    color: #000;
}
.export-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 15px;
    margin: 25px 0;
}
.export-card {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
    border: 2px solid #e9ecef;
    transition: all 0.3s ease;
}
.export-card:hover {
    border-color: #007bff;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.export-card-btn {
    display: block;
    width: 100%;
    padding: 12px;
    background: #28a745;
    color: white;
    text-decoration: none;
    border-radius: 5px;
    border: none;
    cursor: pointer;
    font-size: 14px;
    margin-top: 10px;
    transition: background 0.3s ease;
}
.export-card-btn:hover {
    background: #218838;
}
.export-info {
    background: #e7f3ff;
    padding: 15px;
    border-radius: 5px;
    margin-top: 20px;
    border-left: 4px solid #007bff;
}
.export-section {
    margin: 20px 0;
    padding: 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
}
.export-main-btn {
    background: white;
    color: #667eea;
    border: none;
    padding: 12px 24px;
    border-radius: 5px;
    margin-top: 10px;
    cursor: pointer;
    font-weight: bold;
    font-size: 16px;
    transition: transform 0.2s ease;
}
.export-main-btn:hover {
    transform: scale(1.05);
}
</style>

<!-- Section d'export dans l'interface admin -->
<div class="export-section">
    <h3 style="margin: 0; font-size: 24px;">📊 Export des données CSV</h3>
    <p style="margin: 10px 0 0 0; opacity: 0.9; font-size: 16px;">Exportez toutes les données de l'hôtel en format CSV</p>
    <button onclick="openExportModal()" class="export-main-btn">
        📥 Ouvrir l'export CSV
    </button>
</div>

<!-- Modal d'export -->
<div id="exportModal" class="export-modal">
    <div class="export-modal-content">
        <button class="export-close" onclick="closeExportModal()">&times;</button>
        <h2 style="color: #333; margin-bottom: 10px; text-align: center;">📊 Export des données CSV</h2>
        <p style="text-align: center; color: #666; margin-bottom: 20px;">Sélectionnez les données à exporter</p>
        
        <div class="export-grid">
            <div class="export-card">
                <h4 style="margin: 0 0 10px 0; color: #333;">👥 Clients</h4>
                <p style="margin: 0; color: #666; font-size: 14px;">Liste de tous les clients</p>
                <button class="export-card-btn" onclick="exportTable('clients')">
                    📥 Télécharger
                </button>
            </div>
            
            <div class="export-card">
                <h4 style="margin: 0 0 10px 0; color: #333;">🛏️ Chambres</h4>
                <p style="margin: 0; color: #666; font-size: 14px;">Inventaire des chambres</p>
                <button class="export-card-btn" onclick="exportTable('chambres')">
                    📥 Télécharger
                </button>
            </div>
            
            <div class="export-card">
                <h4 style="margin: 0 0 10px 0; color: #333;">📅 Réservations</h4>
                <p style="margin: 0; color: #666; font-size: 14px;">Historique des réservations</p>
                <button class="export-card-btn" onclick="exportTable('reservations')">
                    📥 Télécharger
                </button>
            </div>
            
            <div class="export-card">
                <h4 style="margin: 0 0 10px 0; color: #333;">💰 Paiements</h4>
                <p style="margin: 0; color: #666; font-size: 14px;">Transactions et paiements</p>
                <button class="export-card-btn" onclick="exportTable('paiements')">
                    📥 Télécharger
                </button>
            </div>
            
            <div class="export-card">
                <h4 style="margin: 0 0 10px 0; color: #333;">🔗 Réservation Chambres</h4>
                <p style="margin: 0; color: #666; font-size: 14px;">Liens réservations-chambres</p>
                <button class="export-card-btn" onclick="exportTable('reservation_chambres')">
                    📥 Télécharger
                </button>
            </div>
        </div>
        
        <div class="export-info">
            <h4 style="margin: 0 0 10px 0; color: #007bff;">💡 Comment utiliser</h4>
            <p style="margin: 5px 0; font-size: 14px;">• Cliquez sur un bouton pour télécharger le CSV</p>
            <p style="margin: 5px 0; font-size: 14px;">• Le fichier sera sauvegardé dans votre dossier <strong>Téléchargements</strong></p>
            <p style="margin: 5px 0; font-size: 14px;">• Le fichier s'ouvre directement dans Excel/LibreOffice</p>
        </div>
        
        <div style="text-align: center; margin-top: 20px;">
            <button onclick="closeExportModal()" style="background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">
                Fermer
            </button>
        </div>
    </div>
</div>

<script>
// Fonctions pour gérer le modal d'export
function openExportModal() {
    document.getElementById('exportModal').style.display = 'block';
}

function closeExportModal() {
    document.getElementById('exportModal').style.display = 'none';
}

function exportTable(tableName) {
    // Ouvrir le téléchargement direct dans un nouvel onglet
    const url = `../hotel/hotel-csv-export/public/download.php?table=${tableName}`;
    window.open(url, '_blank');
    
    // Afficher un message de confirmation
    showExportMessage(`📥 Téléchargement de ${tableName} en cours...`);
    
    // Fermer le modal après un court délai
    setTimeout(() => {
        closeExportModal();
    }, 1500);
}

function showExportMessage(message) {
    // Créer un message temporaire
    const messageDiv = document.createElement('div');
    messageDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #28a745;
        color: white;
        padding: 15px 20px;
        border-radius: 5px;
        z-index: 1001;
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        font-size: 14px;
    `;
    messageDiv.textContent = message;
    document.body.appendChild(messageDiv);
    
    // Supprimer après 3 secondes
    setTimeout(() => {
        if (document.body.contains(messageDiv)) {
            document.body.removeChild(messageDiv);
        }
    }, 3000);
}

// Fermer le modal en cliquant à l'extérieur
window.onclick = function(event) {
    const modal = document.getElementById('exportModal');
    if (event.target === modal) {
        closeExportModal();
    }
}

// Fermer avec la touche Echap
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeExportModal();
    }
});
</script>


</body>
</html>