<?php
require_once 'config.php';

// Démarrer la session et vérifier la connexion
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier l'authentification
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Connexion à la base de données
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Récupérer les données pour le graphique
$reservationsData = [];
$chambres = [];

try {
    // Récupérer toutes les chambres
    $stmtChambres = $pdo->query("
        SELECT idChambre, numeroChambre, type_chambre, description 
        FROM chambres 
        ORDER BY numeroChambre
    ");
    $chambres = $stmtChambres->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer les réservations avec les informations des chambres
    $stmtReservations = $pdo->query("
        SELECT 
            r.idReservation,
            r.date_arrivee,
            r.date_depart,
            r.etat_reservation,
            c.nom,
            c.prenom,
            ch.idChambre,
            ch.numeroChambre,
            ch.type_chambre,
            ch.description as chambre_description
        FROM reservations r
        INNER JOIN clients c ON r.idClient = c.idClient
        INNER JOIN reservation_chambres rc ON r.idReservation = rc.idReservation
        INNER JOIN chambres ch ON rc.idChambre = ch.idChambre
        WHERE r.date_depart >= CURDATE() - INTERVAL 30 DAY
        ORDER BY ch.numeroChambre, r.date_arrivee
    ");
    
    $reservationsData = $stmtReservations->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error = "Erreur lors de la récupération des données : " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Graphique des Réservations - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script>
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

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 15px;
        }

        .section-header h2 {
            color: #2c3e50;
            font-size: 1.8rem;
        }

        .controls {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .control-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .control-group label {
            font-size: 0.85rem;
            color: #6c757d;
            font-weight: 500;
        }

        .control-select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.9rem;
            background: white;
            min-width: 150px;
        }

        .btn-primary {
            background: #667eea;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            align-self: flex-end;
        }

        .btn-primary:hover {
            background: #5a6fd8;
        }

        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            height: 600px;
            position: relative;
        }

        #reservationsChart {
            width: 100%;
            height: 100%;
        }

        .stats-info {
            background: white;
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .stat-item {
            text-align: center;
            padding: 10px;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #667eea;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 400px;
            color: #6c757d;
            font-size: 1.1rem;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }

        .legend {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
        }

        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
        }

        .tooltip-custom {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            max-width: 300px;
        }

        .tooltip-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }

        .tooltip-detail {
            margin-bottom: 4px;
            font-size: 0.85rem;
        }

        .tooltip-status {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-top: 5px;
        }

        @media (max-width: 768px) {
            .admin-main {
                padding: 1rem;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .controls {
                flex-direction: column;
                align-items: stretch;
            }
            
            .chart-container {
                height: 400px;
            }
            
            .stats-info {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <div class="header-content">
                <h1>📊 Graphique des Réservations - <?php echo APP_NAME; ?></h1>
                <div class="user-info">
                    <span>Connecté en tant que <strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'Administrateur'); ?></strong></span>
                    <a href="admin-interface.php" class="btn-logout">📊 Tableau de bord</a>
                    <a href="admin-clients.php" class="btn-logout">👥 Clients</a>
                    <a href="logout.php" class="btn-logout">🚪 Déconnexion</a>
                </div>
            </div>
        </header>

        <main class="admin-main">
            <section id="graphique">
                <div class="section-header">
                    <h2>Diagramme de Gantt des Réservations</h2>
                    <div>
                        <button class="btn-primary" onclick="refreshChart()">
                            🔄 Actualiser
                        </button>
                    </div>
                </div>

                <!-- Statistiques -->
                <div class="stats-info">
                    <div class="stat-item">
                        <div class="stat-value" id="stat-chambres">0</div>
                        <div class="stat-label">Chambres</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" id="stat-reservations">0</div>
                        <div class="stat-label">Réservations actives</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" id="stat-periode">30j</div>
                        <div class="stat-label">Période affichée</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" id="stat-occupation">0%</div>
                        <div class="stat-label">Taux d'occupation</div>
                    </div>
                </div>

                <!-- Contrôles -->
                <div class="controls">
                    <div class="control-group">
                        <label for="typeFilter">Type de chambre</label>
                        <select id="typeFilter" class="control-select" onchange="updateChart()">
                            <option value="all">Tous les types</option>
                            <option value="Standard">Standard</option>
                            <option value="Suite Familiale">Suite Familiale</option>
                            <option value="Deluxe Vue Mer">Deluxe Vue Mer</option>
                        </select>
                    </div>
                    <div class="control-group">
                        <label for="statusFilter">Statut réservation</label>
                        <select id="statusFilter" class="control-select" onchange="updateChart()">
                            <option value="all">Tous les statuts</option>
                            <option value="confirme">Confirmé</option>
                            <option value="en attente">En attente</option>
                            <option value="annulee">Annulée</option>
                        </select>
                    </div>
                    <div class="control-group">
                        <label for="periodFilter">Période</label>
                        <select id="periodFilter" class="control-select" onchange="updateChart()">
                            <option value="30">30 derniers jours</option>
                            <option value="60">60 derniers jours</option>
                            <option value="90">90 derniers jours</option>
                            <option value="180">6 derniers mois</option>
                        </select>
                    </div>
                </div>

                <!-- Messages d'erreur -->
                <?php if (isset($error)): ?>
                    <div class="error-message"><?php echo $error; ?></div>
                <?php endif; ?>

                <!-- Graphique -->
                <div class="chart-container">
                    <canvas id="reservationsChart"></canvas>
                </div>

                <!-- Légende -->
                <div class="legend">
                    <div class="legend-item">
                        <div class="legend-color" style="background-color: #4CAF50;"></div>
                        <span>Confirmé</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background-color: #FF9800;"></div>
                        <span>En attente</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background-color: #F44336;"></div>
                        <span>Annulée</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background-color: #9E9E9E;"></div>
                        <span>Terminée</span>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script>
        // Données PHP converties en JavaScript
        const reservationsData = <?php echo json_encode($reservationsData); ?>;
        const chambres = <?php echo json_encode($chambres); ?>;
        
        let chart;

        // Fonction pour formater les dates
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('fr-FR');
        }

        // Fonction pour obtenir la couleur selon le statut
        function getStatusColor(status, dateDepart) {
            const aujourdhui = new Date();
            const depart = new Date(dateDepart);
            
            if (status === 'annulee') return '#F44336';
            if (status === 'en attente') return '#FF9800';
            if (status === 'confirme') {
                // Si la réservation est terminée
                if (depart < aujourdhui) return '#9E9E9E';
                return '#4CAF50';
            }
            return '#2196F3';
        }

        // Fonction pour préparer les données du graphique
        function prepareChartData(filteredData) {
            const datasets = [];
            const chambresMap = new Map();
            
            // Organiser les données par chambre
            filteredData.forEach(reservation => {
                const chambreId = reservation.idChambre;
                if (!chambresMap.has(chambreId)) {
                    chambresMap.set(chambreId, {
                        numero: reservation.numeroChambre,
                        type: reservation.type_chambre,
                        description: reservation.chambre_description,
                        reservations: []
                    });
                }
                
                chambresMap.get(chambreId).reservations.push({
                    id: reservation.idReservation,
                    arrivee: reservation.date_arrivee,
                    depart: reservation.date_depart,
                    statut: reservation.etat_reservation,
                    client: `${reservation.prenom} ${reservation.nom}`,
                    backgroundColor: getStatusColor(reservation.etat_reservation, reservation.date_depart)
                });
            });
            
            // Créer les datasets pour Chart.js
            let datasetIndex = 0;
            chambresMap.forEach((chambre, chambreId) => {
                chambre.reservations.forEach(reservation => {
                    datasets.push({
                        label: `Chambre ${chambre.numero} - ${reservation.client}`,
                        data: [{
                            x: [reservation.arrivee, reservation.depart],
                            y: `Chambre ${chambre.numero}`
                        }],
                        backgroundColor: reservation.backgroundColor,
                        borderColor: reservation.backgroundColor,
                        borderWidth: 1,
                        borderRadius: 4,
                        borderSkipped: false,
                    });
                    datasetIndex++;
                });
            });
            
            return datasets;
        }

        // Fonction pour filtrer les données
        function filterData() {
            const typeFilter = document.getElementById('typeFilter').value;
            const statusFilter = document.getElementById('statusFilter').value;
            const periodFilter = parseInt(document.getElementById('periodFilter').value);
            
            let filtered = reservationsData.filter(reservation => {
                // Filtre par type de chambre
                if (typeFilter !== 'all' && reservation.type_chambre !== typeFilter) {
                    return false;
                }
                
                // Filtre par statut
                if (statusFilter !== 'all' && reservation.etat_reservation !== statusFilter) {
                    return false;
                }
                
                // Filtre par période
                const dateArrivee = new Date(reservation.date_arrivee);
                const dateLimite = new Date();
                dateLimite.setDate(dateLimite.getDate() - periodFilter);
                
                return dateArrivee >= dateLimite;
            });
            
            return filtered;
        }

        // Fonction pour mettre à jour les statistiques
        function updateStats(filteredData) {
            const chambresUniques = new Set(filteredData.map(r => r.idChambre)).size;
            const reservationsCount = filteredData.length;
            
            document.getElementById('stat-chambres').textContent = chambresUniques;
            document.getElementById('stat-reservations').textContent = reservationsCount;
            document.getElementById('stat-periode').textContent = document.getElementById('periodFilter').value + 'j';
            
            // Calcul du taux d'occupation (simplifié)
            const tauxOccupation = chambres.length > 0 ? Math.round((chambresUniques / chambres.length) * 100) : 0;
            document.getElementById('stat-occupation').textContent = tauxOccupation + '%';
        }

        // Fonction pour créer/mettre à jour le graphique
        function updateChart() {
            const filteredData = filterData();
            updateStats(filteredData);
            
            const datasets = prepareChartData(filteredData);
            
            const ctx = document.getElementById('reservationsChart').getContext('2d');
            
            if (chart) {
                chart.destroy();
            }
            
            chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    datasets: datasets
                },
                options: {
                    indexAxis: 'y',
                    scales: {
                        x: {
                            type: 'time',
                            time: {
                                unit: 'day',
                                displayFormats: {
                                    day: 'dd/MM/yyyy'
                                }
                            },
                            title: {
                                display: true,
                                text: 'Dates'
                            },
                            min: new Date(Date.now() - parseInt(document.getElementById('periodFilter').value) * 24 * 60 * 60 * 1000).toISOString()
                        },
                        y: {
                            title: {
                                display: true,
                                text: 'Chambres'
                            },
                            ticks: {
                                autoSkip: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                title: (context) => {
                                    const data = context[0].dataset.data[context[0].dataIndex];
                                    return `Chambre ${data.y.split(' ')[1]}`;
                                },
                                label: (context) => {
                                    const dataset = context.dataset;
                                    const reservation = dataset.data[context.dataIndex];
                                    const dates = reservation.x;
                                    return `Du ${formatDate(dates[0])} au ${formatDate(dates[1])}`;
                                },
                                afterLabel: (context) => {
                                    const dataset = context.dataset;
                                    return dataset.label.split(' - ')[1];
                                }
                            }
                        }
                    },
                    responsive: true,
                    maintainAspectRatio: false,
                    barThickness: 20
                }
            });
        }

        // Fonction pour rafraîchir le graphique
        function refreshChart() {
            // Dans une vraie application, on rechargerait les données depuis le serveur
            // Pour l'instant, on met juste à jour avec les données existantes
            updateChart();
            showNotification('Graphique actualisé');
        }

        // Fonction pour afficher une notification
        function showNotification(message) {
            // Créer une notification temporaire
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #4CAF50;
                color: white;
                padding: 12px 20px;
                border-radius: 5px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 1000;
                font-size: 14px;
            `;
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }

        // Initialisation au chargement de la page
        document.addEventListener('DOMContentLoaded', function() {
            updateChart();
            
            // Redimensionnement responsive
            window.addEventListener('resize', function() {
                if (chart) {
                    chart.resize();
                }
            });
        });
    </script>
</body>
</html>