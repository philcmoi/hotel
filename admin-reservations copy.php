<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

header('Content-Type: application/json');

try {
    // Connexion directe à la base de données
    $pdo = new PDO("mysql:host=localhost;dbname=hotel", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("set names utf8");
    
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';

    if ($method === 'GET') {
        switch ($action) {
            case 'get_all':
                $filters = [
                    'status' => $_GET['status'] ?? '',
                    'date' => $_GET['date'] ?? '',
                    'search' => $_GET['search'] ?? ''
                ];
                
                $query = "SELECT r.idReservation, r.date_arrivee, r.date_depart, r.nombre_personnes, 
                                 r.prix_total, r.etat_reservation, r.date_reservation, r.commentaire,
                                 c.idClient, c.nom, c.prenom, c.email, c.telephone,
                                 GROUP_CONCAT(DISTINCT ch.numeroChambre SEPARATOR ', ') as chambres
                          FROM reservations r
                          INNER JOIN clients c ON r.idClient = c.idClient
                          LEFT JOIN reservation_chambres rc ON r.idReservation = rc.idReservation
                          LEFT JOIN chambres ch ON rc.idChambre = ch.idChambre
                          WHERE 1=1";
                
                $params = [];
                
                if (!empty($filters['status'])) {
                    $query .= " AND r.etat_reservation = :status";
                    $params[':status'] = $filters['status'];
                }
                
                if (!empty($filters['search'])) {
                    $query .= " AND (c.nom LIKE :search OR c.prenom LIKE :search OR c.email LIKE :search)";
                    $params[':search'] = '%' . $filters['search'] . '%';
                }
                
                $query .= " GROUP BY r.idReservation ORDER BY r.date_reservation DESC";
                
                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
                $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'data' => $reservations]);
                break;

            case 'get':
                $id = $_GET['id'] ?? '';
                if (empty($id)) {
                    echo json_encode(['success' => false, 'error' => 'ID manquant']);
                    break;
                }
                
                $query = "SELECT r.*, c.nom, c.prenom, c.email, c.telephone, c.adresse
                          FROM reservations r
                          INNER JOIN clients c ON r.idClient = c.idClient
                          WHERE r.idReservation = :id";
                
                $stmt = $pdo->prepare($query);
                $stmt->bindParam(':id', $id);
                $stmt->execute();
                $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$reservation) {
                    echo json_encode(['success' => false, 'error' => 'Réservation non trouvée']);
                    break;
                }
                
                echo json_encode(['success' => true, 'data' => $reservation]);
                break;

            case 'check_availability':
                $date_arrivee = $_GET['date_arrivee'] ?? '';
                $date_depart = $_GET['date_depart'] ?? '';
                
                if (empty($date_arrivee) || empty($date_depart)) {
                    echo json_encode(['success' => false, 'error' => 'Dates manquantes']);
                    break;
                }
                
                $query = "SELECT c.idChambre, c.numeroChambre, c.type_chambre, c.prix_nuit, c.capacite
                          FROM chambres c
                          WHERE c.disponible = 1 
                          AND c.idChambre NOT IN (
                              SELECT DISTINCT rc.idChambre 
                              FROM reservation_chambres rc
                              INNER JOIN reservations r ON rc.idReservation = r.idReservation
                              WHERE r.etat_reservation IN ('en attente', 'confirme', 'en cours')
                              AND (
                                  (r.date_arrivee < :date_depart AND r.date_depart > :date_arrivee)
                              )
                          )";
                
                $stmt = $pdo->prepare($query);
                $stmt->bindParam(':date_arrivee', $date_arrivee);
                $stmt->bindParam(':date_depart', $date_depart);
                $stmt->execute();
                $chambres = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'data' => $chambres]);
                break;

            default:
                echo json_encode(['success' => false, 'error' => 'Action non reconnue']);
        }
    } 
    
    elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input === null) {
            echo json_encode(['success' => false, 'error' => 'Données JSON invalides']);
            exit;
        }
        
        $action = $_GET['action'] ?? '';
        
        switch ($action) {
            case 'create':
                if (empty($input['nom']) || empty($input['prenom']) || empty($input['email']) || 
                    empty($input['date_arrivee']) || empty($input['date_depart'])) {
                    echo json_encode(['success' => false, 'error' => 'Données manquantes']);
                    break;
                }
                
                $pdo->beginTransaction();
                
                try {
                    // Vérifier ou créer le client
                    $clientQuery = "SELECT idClient FROM clients WHERE email = :email";
                    $clientStmt = $pdo->prepare($clientQuery);
                    $clientStmt->bindParam(':email', $input['email']);
                    $clientStmt->execute();
                    $client = $clientStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($client) {
                        $clientId = $client['idClient'];
                        // Mettre à jour le client
                        $updateClient = "UPDATE clients SET nom = :nom, prenom = :prenom, telephone = :telephone, adresse = :adresse WHERE idClient = :id";
                        $updateStmt = $pdo->prepare($updateClient);
                        $updateStmt->execute([
                            ':nom' => $input['nom'],
                            ':prenom' => $input['prenom'],
                            ':telephone' => $input['telephone'] ?? '',
                            ':adresse' => $input['adresse'] ?? '',
                            ':id' => $clientId
                        ]);
                    } else {
                        // Créer le client
                        $insertClient = "INSERT INTO clients (nom, prenom, email, telephone, adresse, date_creation) 
                                        VALUES (:nom, :prenom, :email, :telephone, :adresse, NOW())";
                        $insertStmt = $pdo->prepare($insertClient);
                        $insertStmt->execute([
                            ':nom' => $input['nom'],
                            ':prenom' => $input['prenom'],
                            ':email' => $input['email'],
                            ':telephone' => $input['telephone'] ?? '',
                            ':adresse' => $input['adresse'] ?? ''
                        ]);
                        $clientId = $pdo->lastInsertId();
                    }
                    
                    // Calculer le prix total
                    $prixTotal = 0;
                    if (!empty($input['chambres'])) {
                        $nights = (strtotime($input['date_depart']) - strtotime($input['date_arrivee'])) / (60 * 60 * 24);
                        foreach ($input['chambres'] as $chambreId) {
                            $chambreQuery = "SELECT prix_nuit FROM chambres WHERE idChambre = :id";
                            $chambreStmt = $pdo->prepare($chambreQuery);
                            $chambreStmt->bindParam(':id', $chambreId);
                            $chambreStmt->execute();
                            $chambre = $chambreStmt->fetch(PDO::FETCH_ASSOC);
                            if ($chambre) {
                                $prixTotal += $chambre['prix_nuit'] * $nights;
                            }
                        }
                    }
                    
                    // Créer la réservation
                    $insertReservation = "INSERT INTO reservations (date_arrivee, date_depart, nombre_personnes, prix_total, 
                                        etat_reservation, date_reservation, commentaire, idClient) 
                                        VALUES (:date_arrivee, :date_depart, :nombre_personnes, :prix_total, 
                                                'en attente', NOW(), :commentaire, :idClient)";
                    $reservationStmt = $pdo->prepare($insertReservation);
                    $reservationStmt->execute([
                        ':date_arrivee' => $input['date_arrivee'],
                        ':date_depart' => $input['date_depart'],
                        ':nombre_personnes' => $input['nombre_personnes'] ?? 1,
                        ':prix_total' => $prixTotal,
                        ':commentaire' => $input['commentaire'] ?? '',
                        ':idClient' => $clientId
                    ]);
                    $reservationId = $pdo->lastInsertId();
                    
                    // Lier les chambres
                    if (!empty($input['chambres'])) {
                        foreach ($input['chambres'] as $chambreId) {
                            $linkQuery = "INSERT INTO reservation_chambres (idReservation, idChambre) VALUES (:reservation_id, :chambre_id)";
                            $linkStmt = $pdo->prepare($linkQuery);
                            $linkStmt->execute([
                                ':reservation_id' => $reservationId,
                                ':chambre_id' => $chambreId
                            ]);
                        }
                    }
                    
                    $pdo->commit();
                    echo json_encode(['success' => true, 'reservation_id' => $reservationId]);
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'error' => 'Erreur création: ' . $e->getMessage()]);
                }
                break;

            case 'update':
                $id = $_GET['id'] ?? '';
                if (empty($id)) {
                    echo json_encode(['success' => false, 'error' => 'ID manquant']);
                    break;
                }
                
                $query = "UPDATE reservations 
                          SET date_arrivee = :date_arrivee, 
                              date_depart = :date_depart, 
                              nombre_personnes = :nombre_personnes,
                              commentaire = :commentaire,
                              etat_reservation = :etat_reservation
                          WHERE idReservation = :id";
                
                $stmt = $pdo->prepare($query);
                $stmt->execute([
                    ':date_arrivee' => $input['date_arrivee'],
                    ':date_depart' => $input['date_depart'],
                    ':nombre_personnes' => $input['nombre_personnes'],
                    ':commentaire' => $input['commentaire'] ?? '',
                    ':etat_reservation' => $input['etat_reservation'],
                    ':id' => $id
                ]);
                
                echo json_encode(['success' => true, 'message' => 'Réservation mise à jour']);
                break;

            case 'update_status':
                $id = $_GET['id'] ?? '';
                $status = $input['status'] ?? '';
                
                if (empty($id) || empty($status)) {
                    echo json_encode(['success' => false, 'error' => 'ID ou statut manquant']);
                    break;
                }
                
                $allowedStatuses = ['en attente', 'confirme', 'en cours', 'termine', 'annule'];
                if (!in_array($status, $allowedStatuses)) {
                    echo json_encode(['success' => false, 'error' => 'Statut non autorisé']);
                    break;
                }
                
                $query = "UPDATE reservations SET etat_reservation = :status WHERE idReservation = :id";
                $stmt = $pdo->prepare($query);
                $stmt->execute([':status' => $status, ':id' => $id]);
                
                echo json_encode(['success' => true, 'message' => 'Statut mis à jour']);
                break;

            default:
                echo json_encode(['success' => false, 'error' => 'Action non reconnue']);
        }
    }
    
    elseif ($method === 'DELETE') {
        $action = $_GET['action'] ?? '';
        $id = $_GET['id'] ?? '';
        
        if ($action === 'delete') {
            if (empty($id)) {
                echo json_encode(['success' => false, 'error' => 'ID manquant']);
                break;
            }
            
            $pdo->beginTransaction();
            
            try {
                // Supprimer les liaisons chambres
                $deleteLinks = "DELETE FROM reservation_chambres WHERE idReservation = :id";
                $linksStmt = $pdo->prepare($deleteLinks);
                $linksStmt->execute([':id' => $id]);
                
                // Supprimer la réservation
                $deleteReservation = "DELETE FROM reservations WHERE idReservation = :id";
                $reservationStmt = $pdo->prepare($deleteReservation);
                $reservationStmt->execute([':id' => $id]);
                
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Réservation supprimée']);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'error' => 'Erreur suppression: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Action non reconnue']);
        }
    }
    
    else {
        echo json_encode(['success' => false, 'error' => 'Méthode non supportée']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur base de données: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur: ' . $e->getMessage()]);
}
?>