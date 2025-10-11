<?php
require_once 'config.php';

class Database {
    // ... votre code Database existant ...
}

class AdminReservationSystem {
    // ... votre code AdminReservationSystem existant ...
}

// Traitement des requêtes API SÉCURISÉ
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $system = new AdminReservationSystem();

    // Actions publiques (ne nécessitent pas d'authentification)
    $publicActions = ['check_availability'];
    
    // Vérifier l'authentification pour les actions privées
    if (!in_array($action, $publicActions)) {
        checkAPIAuth(); // Utiliser la nouvelle fonction
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
            echo json_encode($system->checkAvailability($date_arrivee, $date_depart));
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Action non reconnue']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Toutes les actions POST nécessitent une authentification
    checkAPIAuth(); // Utiliser la nouvelle fonction
    
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $_GET['action'] ?? '';
    $system = new AdminReservationSystem();

    switch ($action) {
        case 'create':
            echo json_encode($system->createReservation($input));
            break;

        case 'update':
            $id = $_GET['id'] ?? '';
            echo json_encode($system->updateReservation($id, $input));
            break;

        case 'update_status':
            $id = $_GET['id'] ?? '';
            $status = $input['status'] ?? '';
            
            // Validation
            if (empty($id) || empty($status)) {
                echo json_encode(['success' => false, 'error' => 'ID ou statut manquant']);
                break;
            }
            
            // Statuts autorisés
            $allowedStatuses = ['en attente', 'confirme', 'en cours', 'termine', 'annule'];
            if (!in_array($status, $allowedStatuses)) {
                echo json_encode(['success' => false, 'error' => 'Statut non autorisé']);
                break;
            }
            
            echo json_encode($system->updateReservationStatus($id, $status));
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Action non reconnue']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // Toutes les actions DELETE nécessitent une authentification
    checkAPIAuth(); // Utiliser la nouvelle fonction
    
    $action = $_GET['action'] ?? '';
    $system = new AdminReservationSystem();

    if ($action === 'delete') {
        $id = $_GET['id'] ?? '';
        echo json_encode($system->deleteReservation($id));
    } else {
        echo json_encode(['success' => false, 'error' => 'Action non reconnue']);
    }
}
?>