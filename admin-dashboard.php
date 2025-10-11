<?php
require_once 'config.php';

// Vérifier l'authentification - REDIRECTION si non connecté
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Administration - <?php echo APP_NAME; ?></title>
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

        .filters {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filters input, .filters select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            min-width: 150px;
        }

        .btn-primary, .btn-secondary {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
            font-weight: 500;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a6fd8;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .table-container {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-top: 20px;
        }

        .reservations-table {
            width: 100%;
            border-collapse: collapse;
        }

        .reservations-table th,
        .reservations-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .reservations-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
            font-size: 14px;
        }

        .reservations-table tr:hover {
            background: #f8f9fa;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 6px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s;
            min-width: 30px;
            text-align: center;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-warning:hover {
            background: #e0a800;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            text-align: center;
            display: inline-block;
            min-width: 80px;
        }

        .status-en-attente { background: #fff3cd; color: #856404; }
        .status-confirme { background: #d1ecf1; color: #0c5460; }
        .status-en-cours { background: #d4edda; color: #155724; }
        .status-termine { background: #e2e3e5; color: #383d41; }
        .status-annule { background: #f8d7da; color: #721c24; }

        .loading {
            text-align: center;
            padding: 20px;
            color: #6c757d;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            text-align: center;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            text-align: center;
        }

        .auth-error {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            text-align: center;
        }

        @media (max-width: 768px) {
            .admin-main {
                padding: 1rem;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .filters {
                flex-direction: column;
                align-items: stretch;
            }
            
            .reservations-table {
                font-size: 12px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <div class="header-content">
                <h1>🏨 Tableau de Bord Administration</h1>
                <div class="user-info">
                    <span>Connecté en tant que <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></span>
                    <a href="logout.php" class="btn-logout">🚪 Déconnexion</a>
                </div>
            </div>
        </header>

        <main class="admin-main">
            <section id="reservations">
                <div class="section-header">
                    <h2>📋 Gestion des Réservations</h2>
                    <button class="btn-primary" onclick="showAddReservationModal()">
                        ➕ Nouvelle Réservation
                    </button>
                </div>
                
                <div class="filters">
                    <input type="text" id="searchInput" placeholder="🔍 Rechercher par nom, email..." style="flex: 1;">
                    <select id="statusFilter">
                        <option value="">📊 Tous les statuts</option>
                        <option value="en attente">⏳ En attente</option>
                        <option value="confirme">✅ Confirmé</option>
                        <option value="en cours">🏁 En cours</option>
                        <option value="termine">🎯 Terminé</option>
                        <option value="annule">❌ Annulé</option>
                    </select>
                    <input type="date" id="dateFilter">
                    <button class="btn-secondary" onclick="loadReservations()">🔍 Appliquer</button>
                    <button class="btn-secondary" onclick="clearFilters()">🔄 Réinitialiser</button>
                </div>

                <div id="messageContainer"></div>

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
                                <th>Date Réservation</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="reservations-table-body">
                            <tr>
                                <td colspan="9" class="loading">Chargement des réservations...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <script>
        // Gestion des erreurs d'authentification
        function handleAuthError() {
            const messageContainer = document.getElementById('messageContainer');
            messageContainer.innerHTML = `
                <div class="auth-error">
                    <strong>Session expirée</strong><br>
                    Vous allez être redirigé vers la page de connexion...
                </div>
            `;
            
            setTimeout(() => {
                window.location.href = 'login.php';
            }, 2000);
        }

        // Afficher un message à l'utilisateur
        function showMessage(message, type = 'success') {
            const container = document.getElementById('messageContainer');
            container.innerHTML = `<div class="${type}-message">${message}</div>`;
            
            setTimeout(() => {
                container.innerHTML = '';
            }, 5000);
        }

        // Formater une date
        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('fr-FR');
        }

        // Formater une date et heure
        function formatDateTime(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleString('fr-FR');
        }

        // Charger les réservations
        async function loadReservations() {
            const tbody = document.getElementById("reservations-table-body");
            tbody.innerHTML = '<tr><td colspan="9" class="loading">Chargement en cours...</td></tr>';

            try {
                const search = document.getElementById('searchInput').value;
                const status = document.getElementById('statusFilter').value;
                const date = document.getElementById('dateFilter').value;

                const url = new URL('admin-reservations.php', window.location.origin);
                url.searchParams.set('action', 'get_all');
                if (search) url.searchParams.set('search', search);
                if (status) url.searchParams.set('status', status);
                if (date) url.searchParams.set('date', date);

                const response = await fetch(url);
                const data = await response.json();

                if (data.success) {
                    displayReservations(data.data);
                } else {
                    if (data.error.includes('Authentification') || data.error.includes('Non authentifié') || data.redirect) {
                        handleAuthError();
                    } else {
                        throw new Error(data.error);
                    }
                }
            } catch (error) {
                console.error('Erreur:', error);
                tbody.innerHTML = `<tr><td colspan="9" class="error-message">Erreur de chargement: ${error.message}</td></tr>`;
            }
        }

        // Afficher les réservations dans le tableau
        function displayReservations(reservations) {
            const tbody = document.getElementById("reservations-table-body");
            
            if (reservations.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" class="loading">Aucune réservation trouvée</td></tr>';
                return;
            }

            tbody.innerHTML = '';

            reservations.forEach((reservation) => {
                const tr = document.createElement("tr");
                const statusClass = `status-${reservation.etat_reservation.replace(" ", "-")}`;

                // Déterminer les boutons d'action selon le statut
                let statusButtons = '';
                if (reservation.etat_reservation === 'en attente') {
                    statusButtons = `
                        <button class="action-btn btn-success" onclick="changeStatus(${reservation.idReservation}, 'confirme')" title="Confirmer">✅</button>
                        <button class="action-btn btn-danger" onclick="changeStatus(${reservation.idReservation}, 'annule')" title="Annuler">❌</button>
                    `;
                } else if (reservation.etat_reservation === 'confirme') {
                    statusButtons = `
                        <button class="action-btn btn-success" onclick="changeStatus(${reservation.idReservation}, 'en cours')" title="Débuter séjour">🏁</button>
                        <button class="action-btn btn-danger" onclick="changeStatus(${reservation.idReservation}, 'annule')" title="Annuler">❌</button>
                    `;
                } else if (reservation.etat_reservation === 'en cours') {
                    statusButtons = `
                        <button class="action-btn btn-success" onclick="changeStatus(${reservation.idReservation}, 'termine')" title="Terminer séjour">🎯</button>
                    `;
                } else {
                    statusButtons = `
                        <button class="action-btn btn-warning" onclick="changeStatus(${reservation.idReservation}, 'en attente')" title="Remettre en attente">↩️</button>
                    `;
                }

                tr.innerHTML = `
                    <td><strong>#${reservation.idReservation}</strong></td>
                    <td>
                        <strong>${reservation.prenom} ${reservation.nom}</strong><br>
                        <small>📧 ${reservation.email}</small><br>
                        <small>📞 ${reservation.telephone || 'N/A'}</small>
                    </td>
                    <td>
                        <strong>${formatDate(reservation.date_arrivee)}</strong><br>
                        au<br>
                        <strong>${formatDate(reservation.date_depart)}</strong>
                    </td>
                    <td>
                        <strong>${reservation.chambres || "N/A"}</strong><br>
                        <small>${reservation.types_chambres || ""}</small>
                    </td>
                    <td>👥 ${reservation.nombre_personnes}</td>
                    <td>💰 ${reservation.prix_total}€</td>
                    <td>
                        <span class="status-badge ${statusClass}">
                            ${reservation.etat_reservation}
                        </span>
                    </td>
                    <td>${formatDateTime(reservation.date_reservation)}</td>
                    <td>
                        <div class="action-buttons">
                            <button class="action-btn btn-primary" onclick="editReservation(${reservation.idReservation})" title="Modifier">✏️</button>
                            ${statusButtons}
                            <button class="action-btn btn-danger" onclick="deleteReservation(${reservation.idReservation})" title="Supprimer">🗑️</button>
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        // Changer le statut d'une réservation
        async function changeStatus(reservationId, newStatus) {
            const statusLabels = {
                'en attente': 'en attente',
                'confirme': 'confirmée',
                'en cours': 'en cours',
                'termine': 'terminée',
                'annule': 'annulée'
            };

            if (!confirm(`Êtes-vous sûr de vouloir changer le statut de la réservation #${reservationId} en "${statusLabels[newStatus]}" ?`)) {
                return;
            }

            try {
                const response = await fetch(`admin-reservations.php?action=update_status&id=${reservationId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        status: newStatus
                    })
                });

                const data = await response.json();
                
                if (data.success) {
                    showMessage('✅ Statut mis à jour avec succès');
                    loadReservations();
                } else {
                    if (data.error.includes('Authentification') || data.redirect) {
                        handleAuthError();
                    } else {
                        showMessage('❌ Erreur: ' + data.error, 'error');
                    }
                }
            } catch (error) {
                console.error('Erreur:', error);
                showMessage('❌ Erreur lors de la mise à jour du statut', 'error');
            }
        }

        // Supprimer une réservation
        async function deleteReservation(id) {
            if (!confirm(`⚠️ Êtes-vous sûr de vouloir supprimer définitivement la réservation #${id} ? Cette action est irréversible.`)) {
                return;
            }

            try {
                const response = await fetch(`admin-reservations.php?action=delete&id=${id}`, {
                    method: 'DELETE'
                });

                const data = await response.json();
                
                if (data.success) {
                    showMessage('✅ Réservation supprimée avec succès');
                    loadReservations();
                } else {
                    if (data.error.includes('Authentification') || data.redirect) {
                        handleAuthError();
                    } else {
                        showMessage('❌ Erreur: ' + data.error, 'error');
                    }
                }
            } catch (error) {
                console.error('Erreur:', error);
                showMessage('❌ Erreur lors de la suppression', 'error');
            }
        }

        // Réinitialiser les filtres
        function clearFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('statusFilter').value = '';
            document.getElementById('dateFilter').value = '';
            loadReservations();
        }

        // Modal pour nouvelle réservation
        function showAddReservationModal() {
            alert('🚧 Fonctionnalité en cours de développement\n\nCette fonction permettra de créer une nouvelle réservation avec sélection des chambres, dates et informations client.');
        }

        // Édition d'une réservation
        function editReservation(id) {
            alert(`🚧 Édition de la réservation #${id}\n\nCette fonction permettra de modifier les détails de la réservation.`);
        }

        // Charger les réservations au démarrage
        document.addEventListener('DOMContentLoaded', function() {
            loadReservations();
            
            // Rafraîchir automatiquement toutes les 30 secondes
            setInterval(loadReservations, 30000);
        });

        // Recherche en temps réel
        let searchTimeout;
        document.getElementById('searchInput').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(loadReservations, 500);
        });

        // Filtrer quand la date ou le statut change
        document.getElementById('statusFilter').addEventListener('change', loadReservations);
        document.getElementById('dateFilter').addEventListener('change', loadReservations);
    </script>
</body>
</html>