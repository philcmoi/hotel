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

// Messages de succès/erreur
$success_message = '';
$error_message = '';

// TRAITEMENT DE LA SUPPRESSION D'UN CLIENT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_client'])) {
    $clientId = $_POST['client_id'] ?? '';
    
    if (!empty($clientId)) {
        try {
            // Vérifier d'abord si le client a des réservations
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE idClient = ?");
            $stmt->execute([$clientId]);
            $hasReservations = $stmt->fetchColumn();
            
            if ($hasReservations > 0) {
                $error_message = "Impossible de supprimer ce client car il a des réservations associées.";
            } else {
                // Supprimer le client
                $stmt = $pdo->prepare("DELETE FROM clients WHERE idClient = ?");
                $stmt->execute([$clientId]);
                
                if ($stmt->rowCount() > 0) {
                    $success_message = "Client supprimé avec succès.";
                } else {
                    $error_message = "Erreur lors de la suppression du client.";
                }
            }
        } catch(PDOException $e) {
            $error_message = "Erreur lors de la suppression : " . $e->getMessage();
        }
    }
}

// TRAITEMENT DE LA MODIFICATION D'UN CLIENT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_client'])) {
    $clientId = $_POST['client_id'] ?? '';
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');
    
    // Validation des données
    if (empty($nom) || empty($prenom) || empty($email)) {
        $error_message = "Les champs nom, prénom et email sont obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "L'adresse email n'est pas valide.";
    } else {
        try {
            // Vérifier si l'email existe déjà pour un autre client
            $stmt = $pdo->prepare("SELECT idClient FROM clients WHERE email = ? AND idClient != ?");
            $stmt->execute([$email, $clientId]);
            
            if ($stmt->fetch()) {
                $error_message = "Cette adresse email est déjà utilisée par un autre client.";
            } else {
                // Mettre à jour le client
                $stmt = $pdo->prepare("
                    UPDATE clients 
                    SET nom = ?, prenom = ?, email = ?, telephone = ?, adresse = ?
                    WHERE idClient = ?
                ");
                $stmt->execute([$nom, $prenom, $email, $telephone, $adresse, $clientId]);
                
                if ($stmt->rowCount() > 0) {
                    $success_message = "Client modifié avec succès.";
                } else {
                    $error_message = "Aucune modification effectuée.";
                }
            }
        } catch(PDOException $e) {
            $error_message = "Erreur lors de la modification : " . $e->getMessage();
        }
    }
}

// RÉCUPÉRATION DES DONNÉES D'UN CLIENT POUR LA MODIFICATION
$clientToEdit = null;
if (isset($_GET['edit_client'])) {
    $clientId = $_GET['edit_client'];
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM clients WHERE idClient = ?");
        $stmt->execute([$clientId]);
        $clientToEdit = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$clientToEdit) {
            $error_message = "Client non trouvé.";
        }
    } catch(PDOException $e) {
        $error_message = "Erreur lors de la récupération du client : " . $e->getMessage();
    }
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
        /* Styles précédents conservés... */
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
            max-width: 600px;
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

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s;
        }

        .btn-danger:hover {
            background: #c82333;
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

            .form-actions {
                flex-direction: column;
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
                </div>

                <!-- Messages de succès/erreur -->
                <?php if (!empty($success_message)): ?>
                    <div class="success-message"><?php echo $success_message; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($error_message)): ?>
                    <div class="error-message"><?php echo $error_message; ?></div>
                <?php endif; ?>

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
                                                <a href="?edit_client=<?php echo $client['idClient']; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="action-btn btn-edit">
                                                    ✏️ Modifier
                                                </a>
                                                <button class="action-btn btn-delete" onclick="confirmDelete(<?php echo $client['idClient']; ?>, '<?php echo htmlspecialchars($client['prenom'] . ' ' . $client['nom']); ?>')">
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

    <!-- Modal pour modifier un client -->
    <?php if ($clientToEdit): ?>
    <div class="modal" id="editClientModal" style="display: flex;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>✏️ Modifier le Client #<?php echo $clientToEdit['idClient']; ?></h3>
                <button class="close-btn" onclick="closeEditModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <input type="hidden" name="client_id" value="<?php echo $clientToEdit['idClient']; ?>">
                    
                    <div class="form-group">
                        <label for="nom">Nom *</label>
                        <input type="text" id="nom" name="nom" class="form-control" 
                               value="<?php echo htmlspecialchars($clientToEdit['nom']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="prenom">Prénom *</label>
                        <input type="text" id="prenom" name="prenom" class="form-control" 
                               value="<?php echo htmlspecialchars($clientToEdit['prenom']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               value="<?php echo htmlspecialchars($clientToEdit['email']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="telephone">Téléphone</label>
                        <input type="tel" id="telephone" name="telephone" class="form-control" 
                               value="<?php echo htmlspecialchars($clientToEdit['telephone'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="adresse">Adresse</label>
                        <textarea id="adresse" name="adresse" class="form-control" rows="3"><?php echo htmlspecialchars($clientToEdit['adresse'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn-secondary" onclick="closeEditModal()">Annuler</button>
                        <button type="submit" name="update_client" class="btn-primary">Enregistrer les modifications</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal de confirmation de suppression -->
    <div class="modal" id="deleteConfirmModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>🗑️ Confirmation de suppression</h3>
                <button class="close-btn" onclick="closeDeleteModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p id="deleteConfirmMessage">Êtes-vous sûr de vouloir supprimer ce client ?</p>
                <form method="POST" action="" id="deleteForm">
                    <input type="hidden" name="client_id" id="deleteClientId">
                    <input type="hidden" name="delete_client" value="1">
                    
                    <div class="form-actions">
                        <button type="button" class="btn-secondary" onclick="closeDeleteModal()">Annuler</button>
                        <button type="submit" class="btn-danger">Confirmer la suppression</button>
                    </div>
                </form>
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

        // Fonction pour confirmer la suppression
        function confirmDelete(clientId, clientName) {
            document.getElementById('deleteConfirmMessage').textContent = 
                `Êtes-vous sûr de vouloir supprimer le client "${clientName}" (ID: #${clientId}) ? Cette action est irréversible.`;
            document.getElementById('deleteClientId').value = clientId;
            document.getElementById('deleteConfirmModal').style.display = 'flex';
        }

        // Fonctions pour fermer les modals
        function closeReservationsModal() {
            document.getElementById('reservationsModal').style.display = 'none';
        }

        function closeEditModal() {
            window.location.href = 'admin-clients.php<?php echo !empty($search) ? '?search=' . urlencode($search) : ''; ?>';
        }

        function closeDeleteModal() {
            document.getElementById('deleteConfirmModal').style.display = 'none';
        }

        // Fermer les modals en cliquant en dehors
        window.onclick = function(event) {
            const modals = ['reservationsModal', 'deleteConfirmModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
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

            // Fermer le modal d'édition avec la touche Échap
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    closeEditModal();
                    closeDeleteModal();
                    closeReservationsModal();
                }
            });
        });
    </script>
</body>
</html>