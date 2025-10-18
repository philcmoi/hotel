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

// S'assurer que le username est défini
if (!isset($_SESSION['username'])) {
    $_SESSION['username'] = 'Administrateur';
}

// Connexion à la base de données
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Traitement de la recherche
$search = $_GET['search'] ?? '';
$clients = [];
$totalClients = 0;

try {
    if (!empty($search)) {
        // Recherche dans plusieurs champs
        $stmt = $pdo->prepare("
            SELECT * FROM clients 
            WHERE nom LIKE :search 
               OR prenom LIKE :search 
               OR email LIKE :search 
               OR telephone LIKE :search 
               OR adresse LIKE :search
            ORDER BY nom, prenom
        ");
        $stmt->execute([':search' => "%$search%"]);
    } else {
        // Tous les clients
        $stmt = $pdo->query("SELECT * FROM clients ORDER BY nom, prenom");
    }
    
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalClients = count($clients);
    
} catch(PDOException $e) {
    $error = "Erreur lors de la récupération des clients : " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Clients - <?php echo APP_NAME; ?></title>
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
            max-width: 1200px;
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
            max-width: 1200px;
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

        /* Styles pour la recherche */
        .search-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .search-form {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-input {
            flex: 1;
            min-width: 300px;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        .search-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn-primary {
            background: #667eea;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            background: #5a6fd8;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        /* Styles pour le tableau */
        .table-container {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .clients-table {
            width: 100%;
            border-collapse: collapse;
        }

        .clients-table th,
        .clients-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .clients-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
            font-size: 14px;
            position: sticky;
            top: 0;
        }

        .clients-table tr:hover {
            background: #f8f9fa;
        }

        .client-name {
            font-weight: 600;
            color: #2c3e50;
        }

        .client-contact {
            color: #6c757d;
            font-size: 14px;
        }

        .client-address {
            color: #6c757d;
            font-size: 14px;
            max-width: 200px;
        }

        .client-id {
            color: #667eea;
            font-weight: 600;
        }

        .date-creation {
            color: #6c757d;
            font-size: 14px;
        }

        .stats-bar {
            background: white;
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .stats-info {
            color: #6c757d;
            font-size: 14px;
        }

        .stats-info strong {
            color: #2c3e50;
        }

        .no-results {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }

        .no-results i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .action-btn {
            padding: 8px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-view {
            background: #17a2b8;
            color: white;
        }

        .btn-view:hover {
            background: #138496;
        }

        .btn-edit {
            background: #ffc107;
            color: #212529;
        }

        .btn-edit:hover {
            background: #e0a800;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
        }

        .btn-delete:hover {
            background: #c82333;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 10px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8f9fa;
            border-radius: 10px 10px 0 0;
        }

        .modal-header h3 {
            margin: 0;
            color: #2c3e50;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #6c757d;
            padding: 5px;
        }

        .close-btn:hover {
            color: #dc3545;
        }

        .modal-body {
            padding: 20px;
        }

        .reservations-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .reservation-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid #667eea;
        }

        .reservation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .reservation-id {
            font-weight: 600;
            color: #667eea;
        }

        .reservation-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-confirme { background: #d4edda; color: #155724; }
        .status-en-attente { background: #fff3cd; color: #856404; }
        .status-annulee { background: #f8d7da; color: #721c24; }

        .reservation-dates {
            margin-bottom: 8px;
        }

        .reservation-detail {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }

        .reservation-detail strong {
            color: #2c3e50;
        }

        .no-reservations {
            text-align: center;
            padding: 30px;
            color: #6c757d;
        }

        .loading {
            text-align: center;
            padding: 20px;
            color: #6c757d;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .admin-main {
                padding: 1rem;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .search-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-input {
                min-width: auto;
            }
            
            .clients-table {
                font-size: 14px;
            }
            
            .clients-table th,
            .clients-table td {
                padding: 10px 8px;
            }
            
            .action-buttons {
                flex-direction: column;
            }

            .modal-content {
                width: 95%;
                margin: 10px;
            }

            .reservation-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }

        @media (max-width: 480px) {
            .clients-table {
                display: block;
                overflow-x: auto;
            }
            
            .stats-bar {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <div class="header-content">
                <h1>👥 Gestion des Clients - <?php echo APP_NAME; ?></h1>
                <div class="user-info">
                    <span>Connecté en tant que <strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'Administrateur'); ?></strong></span>
                    <a href="admin-interface.php" class="btn-logout">📊 Tableau de bord</a>
                    <a href="logout.php" class="btn-logout">🚪 Déconnexion</a>
                </div>
            </div>
        </header>

        <main class="admin-main">
            <section id="clients">
                <div class="section-header">
                    <h2>Liste des Clients</h2>
                    <!--<div>
                        <button class="btn-primary" onclick="showAddClientModal()">
                            ➕ Ajouter un Client
                        </button>
                    </div>-->
                </div>

                <!-- Barre de statistiques -->
                <div class="stats-bar">
                    <div class="stats-info">
                        <strong><?php echo $totalClients; ?></strong> client(s) trouvé(s)
                        <?php if (!empty($search)): ?>
                            pour la recherche : "<strong><?php echo htmlspecialchars($search); ?></strong>"
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($search)): ?>
                        <a href="admin-clients.php" class="btn-secondary">
                            🔄 Afficher tous les clients
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Messages d'erreur/succès -->
                <?php if (isset($error)): ?>
                    <div class="error-message"><?php echo $error; ?></div>
                <?php endif; ?>

                <!-- Formulaire de recherche -->
                <div class="search-container">
                    <form method="GET" action="" class="search-form">
                        <input 
                            type="text" 
                            name="search" 
                            class="search-input" 
                            placeholder="🔍 Rechercher un client par nom, prénom, email, téléphone ou adresse..."
                            value="<?php echo htmlspecialchars($search); ?>"
                        >
                        <button type="submit" class="btn-primary">
                            🔍 Rechercher
                        </button>
                        <?php if (!empty($search)): ?>
                            <a href="admin-clients.php" class="btn-secondary">
                                ❌ Effacer
                            </a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Tableau des clients -->
                <div class="table-container">
                    <table class="clients-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nom & Prénom</th>
                                <th>Contact</th>
                                <th>Adresse</th>
                                <th>Date d'inscription</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($clients)): ?>
                                <tr>
                                    <td colspan="6" class="no-results">
                                        <div>👤</div>
                                        <p>
                                            <?php if (!empty($search)): ?>
                                                Aucun client trouvé pour votre recherche.<br>
                                                <small>Essayez avec d'autres termes.</small>
                                            <?php else: ?>
                                                Aucun client enregistré pour le moment.
                                            <?php endif; ?>
                                        </p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($clients as $client): ?>
                                    <tr>
                                        <td class="client-id">#<?php echo $client['idClient']; ?></td>
                                        <td>
                                            <div class="client-name">
                                                <?php echo htmlspecialchars($client['prenom'] . ' ' . $client['nom']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="client-contact">
                                                <strong>📧 Email:</strong> <?php echo htmlspecialchars($client['email'] ?? 'Non renseigné'); ?><br>
                                                <strong>📞 Tél:</strong> <?php echo htmlspecialchars($client['telephone'] ?? 'Non renseigné'); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="client-address">
                                                <?php echo htmlspecialchars($client['adresse']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="date-creation">
                                                <?php echo date('d/m/Y H:i', strtotime($client['date_creation'])); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="action-btn btn-view" onclick="viewClientReservations(<?php echo $client['idClient']; ?>, '<?php echo htmlspecialchars($client['prenom'] . ' ' . $client['nom']); ?>')">
                                                    👁️ Voir Réservations
                                                </button>
                                                <button class="action-btn btn-edit" onclick="editClient(<?php echo $client['idClient']; ?>)">
                                                    ✏️ Modifier
                                                </button>
                                                <button class="action-btn btn-delete" onclick="deleteClient(<?php echo $client['idClient']; ?>)">
                                                    🗑️ Supprimer
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <!-- Modal pour afficher les réservations -->
    <div class="modal" id="reservationsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalClientName">Réservations du client</h3>
                <button class="close-btn" onclick="closeReservationsModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="reservationsList" class="reservations-list">
                    <!-- Le contenu des réservations sera chargé ici -->
                </div>
            </div>
        </div>
    </div>

    <script>
        // Fonction pour afficher les réservations d'un client
        async function viewClientReservations(clientId, clientName) {
            // Mettre à jour le titre du modal
            document.getElementById('modalClientName').textContent = `Réservations de ${clientName}`;
            
            // Afficher le modal
            document.getElementById('reservationsModal').style.display = 'flex';
            
            // Afficher un message de chargement
            document.getElementById('reservationsList').innerHTML = '<div class="loading">Chargement des réservations...</div>';
            
            try {
                // Appel AJAX pour récupérer les réservations
                const response = await fetch(`get-client-reservations.php?client_id=${clientId}`);
                const data = await response.json();
                
                if (data.success) {
                    displayReservations(data.reservations);
                } else {
                    document.getElementById('reservationsList').innerHTML = 
                        '<div class="error-message">Erreur: ' + data.error + '</div>';
                }
            } catch (error) {
                console.error('Erreur:', error);
                document.getElementById('reservationsList').innerHTML = 
                    '<div class="error-message">Erreur de chargement des réservations</div>';
            }
        }

        // Fonction pour afficher les réservations dans le modal
        function displayReservations(reservations) {
            const container = document.getElementById('reservationsList');
            
            if (reservations.length === 0) {
                container.innerHTML = `
                    <div class="no-reservations">
                        <div style="font-size: 48px; margin-bottom: 15px;">📭</div>
                        <p>Aucune réservation trouvée pour ce client.</p>
                    </div>
                `;
                return;
            }
            
            let html = '';
            reservations.forEach(reservation => {
                const statusClass = `status-${reservation.etat_reservation.replace(' ', '-')}`;
                const arrivee = formatDate(reservation.date_arrivee);
                const depart = formatDate(reservation.date_depart);
                const dateReservation = formatDateTime(reservation.date_reservation);
                
                html += `
                    <div class="reservation-item">
                        <div class="reservation-header">
                            <div class="reservation-id">Réservation #${reservation.idReservation}</div>
                            <div class="reservation-status ${statusClass}">
                                ${reservation.etat_reservation}
                            </div>
                        </div>
                        <div class="reservation-dates">
                            <strong>📅 ${arrivee} - ${depart}</strong>
                        </div>
                        <div class="reservation-detail">
                            <span><strong>👥 Personnes:</strong> ${reservation.nombre_personnes}</span>
                            <span><strong>💰 Prix total:</strong> ${reservation.prix_total}€</span>
                        </div>
                        <div class="reservation-detail">
                            <span><strong>📋 Chambres:</strong> ${reservation.chambres || 'N/A'}</span>
                        </div>
                        <div class="reservation-detail">
                            <span><strong>📅 Date réservation:</strong> ${dateReservation}</span>
                        </div>
                        ${reservation.commentaire ? `
                        <div class="reservation-detail">
                            <span><strong>💬 Commentaire:</strong> ${reservation.commentaire}</span>
                        </div>
                        ` : ''}
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }

        // Fonction pour fermer le modal
        function closeReservationsModal() {
            document.getElementById('reservationsModal').style.display = 'none';
        }

        // Fermer le modal en cliquant en dehors
        window.onclick = function(event) {
            const modal = document.getElementById('reservationsModal');
            if (event.target === modal) {
                closeReservationsModal();
            }
        }

        // Fonctions utilitaires
        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('fr-FR');
        }

        function formatDateTime(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleString('fr-FR');
        }

        // Autres fonctions existantes
        function editClient(clientId) {
            alert(`✏️ Modification du client #${clientId}\n\nCette fonctionnalité ouvrira un formulaire de modification des informations du client.`);
        }

        function deleteClient(clientId) {
            if (confirm(`⚠️ Êtes-vous sûr de vouloir supprimer le client #${clientId} ?\n\nCette action est irréversible et supprimera également toutes ses réservations associées.`)) {
                alert(`🗑️ Suppression du client #${clientId}\n\nEn développement : Cette action enverrait une requête de suppression au serveur.`);
            }
        }

        function showAddClientModal() {
            alert(`➕ Ajout d'un nouveau client\n\nCette fonctionnalité ouvrira un formulaire pour ajouter un nouveau client à la base de données.`);
        }

        // Recherche en temps réel (optionnel)
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('.search-input');
            let searchTimeout;
            
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                if (this.value.length >= 3 || this.value.length === 0) {
                    searchTimeout = setTimeout(() => {
                        this.form.submit();
                    }, 800);
                }
            });
        });
    </script>
</body>
</html>