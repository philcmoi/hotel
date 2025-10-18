<?php
session_start();

// Vérifier l'authentification
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('HTTP/1.1 403 Forbidden');
    exit('Accès non autorisé');
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

class DataExporter {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // Récupérer les données selon le type
    public function getData($type, $startDate = null, $endDate = null) {
        switch($type) {
            case 'reservations':
                $query = "SELECT 
                            r.idReservation,
                            r.date_reservation,
                            r.date_arrivee,
                            r.date_depart,
                            r.nombre_personnes,
                            r.prix_total,
                            r.etat_reservation,
                            r.commentaire,
                            c.nom as client_nom,
                            c.prenom as client_prenom,
                            c.email as client_email,
                            c.telephone as client_telephone,
                            GROUP_CONCAT(ch.numeroChambre) as chambres,
                            GROUP_CONCAT(ch.type_chambre) as types_chambres
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
                // Vérifier d'abord la structure de la table clients
                $checkTable = $this->conn->query("DESCRIBE clients")->fetchAll(PDO::FETCH_COLUMN);
                
                // Construire la requête dynamiquement en fonction des colonnes existantes
                $columns = [];
                if (in_array('idClient', $checkTable)) $columns[] = 'idClient';
                if (in_array('nom', $checkTable)) $columns[] = 'nom';
                if (in_array('prenom', $checkTable)) $columns[] = 'prenom';
                if (in_array('email', $checkTable)) $columns[] = 'email';
                if (in_array('telephone', $checkTable)) $columns[] = 'telephone';
                if (in_array('adresse', $checkTable)) $columns[] = 'adresse';
                if (in_array('ville', $checkTable)) $columns[] = 'ville';
                if (in_array('code_postal', $checkTable)) $columns[] = 'code_postal';
                if (in_array('pays', $checkTable)) $columns[] = 'pays';
                if (in_array('date_creation', $checkTable)) $columns[] = 'date_creation';
                if (in_array('date_inscription', $checkTable)) $columns[] = 'date_inscription';
                if (in_array('created_at', $checkTable)) $columns[] = 'created_at';
                
                if (empty($columns)) {
                    throw new Exception("Aucune colonne valide trouvée dans la table clients");
                }
                
                $query = "SELECT " . implode(', ', $columns) . " FROM clients ORDER BY ";
                
                // Déterminer la colonne de tri par date si elle existe
                if (in_array('date_creation', $columns)) {
                    $query .= "date_creation DESC";
                } elseif (in_array('date_inscription', $columns)) {
                    $query .= "date_inscription DESC";
                } elseif (in_array('created_at', $columns)) {
                    $query .= "created_at DESC";
                } else {
                    $query .= "idClient DESC";
                }
                break;
                
            case 'chambres':
                $query = "SELECT 
                            idChambre,
                            numeroChambre,
                            type_chambre,
                            capacite,
                            prix_nuit,
                            description,
                            disponible,
                            CASE 
                                WHEN disponible = 0 THEN 'maintenance'
                                WHEN EXISTS (
                                    SELECT 1 FROM reservation_chambres rc 
                                    INNER JOIN reservations r ON rc.idReservation = r.idReservation 
                                    WHERE rc.idChambre = chambres.idChambre 
                                    AND r.date_depart >= CURDATE() 
                                    AND r.etat_reservation = 'confirme'
                                ) THEN 'occupee'
                                ELSE 'disponible'
                            END as statut
                          FROM chambres 
                          ORDER BY numeroChambre ASC";
                break;
                
            case 'paiements':
                // Vérifier d'abord la structure de la table paiements
                $checkTable = $this->conn->query("DESCRIBE paiements")->fetchAll(PDO::FETCH_COLUMN);
                
                $query = "SELECT 
                            p.idPaiement,
                            p.montant,
                            p.methode_paiement,
                            p.date_paiement,
                            p.etat_paiement,
                            p.idReservation,
                            r.date_reservation,
                            c.nom as client_nom,
                            c.prenom as client_prenom,
                            c.email as client_email";
                
                // Ajouter les colonnes optionnelles si elles existent
                if (in_array('reference_transaction', $checkTable)) {
                    $query .= ", p.reference_transaction";
                }
                if (in_array('date_creation', $checkTable)) {
                    $query .= ", p.date_creation";
                }
                if (in_array('created_at', $checkTable)) {
                    $query .= ", p.created_at";
                }
                
                $query .= " FROM paiements p
                          LEFT JOIN reservations r ON p.idReservation = r.idReservation
                          LEFT JOIN clients c ON r.idClient = c.idClient";
                
                if ($startDate && $endDate) {
                    $query .= " WHERE p.date_paiement BETWEEN :start_date AND :end_date";
                }
                
                $query .= " ORDER BY p.date_paiement DESC";
                break;
                
            default:
                throw new Exception("Type d'export non valide");
        }
        
        $stmt = $this->conn->prepare($query);
        
        if ($startDate && $endDate && ($type === 'reservations' || $type === 'paiements')) {
            $stmt->bindParam(':start_date', $startDate);
            $stmt->bindParam(':end_date', $endDate);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Générer le contenu CSV
    public function generateCSVContent($data) {
        if (empty($data)) {
            throw new Exception("Aucune donnée à exporter");
        }

        $output = fopen('php://temp', 'r+');
        
        // Ajouter BOM UTF-8 pour Excel
        fwrite($output, "\xEF\xBB\xBF");
        
        // Écrire l'en-tête
        $headers = array_keys($data[0]);
        fputcsv($output, $headers, ';');
        
        // Écrire les données
        foreach ($data as $row) {
            fputcsv($output, $row, ';');
        }
        
        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);
        
        return $csvContent;
    }

    // Générer le contenu JSON
    public function generateJSONContent($data) {
        if (empty($data)) {
            throw new Exception("Aucune donnée à exporter");
        }

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    // Méthode pour déboguer la structure de la table
    public function debugTableStructure($tableName) {
        try {
            $stmt = $this->conn->query("DESCRIBE " . $tableName);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception("Impossible de récupérer la structure de la table: " . $e->getMessage());
        }
    }

    // Méthode pour vérifier les tables disponibles
    public function getAvailableTables() {
        $stmt = $this->conn->query("SHOW TABLES");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

try {
    // Récupérer les paramètres (POST au lieu de GET)
    $type = $_POST['type'] ?? '';
    $format = $_POST['format'] ?? 'csv';
    $startDate = $_POST['start_date'] ?? null;
    $endDate = $_POST['end_date'] ?? null;

    if (empty($type)) {
        throw new Exception("Type d'export non spécifié");
    }

    $exporter = new DataExporter();
    
    // Debug: vérifier la structure de la table si c'est pour les paiements
    if ($type === 'paiements') {
        error_log("Structure de la table paiements: " . print_r($exporter->debugTableStructure('paiements'), true));
        error_log("Tables disponibles: " . print_r($exporter->getAvailableTables(), true));
    }
    
    // Récupérer les données
    $data = $exporter->getData($type, $startDate, $endDate);
    
    if (empty($data)) {
        throw new Exception("Aucune donnée trouvée pour l'export");
    }
    
    // Générer le nom de fichier
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "{$type}_export_{$timestamp}.{$format}";
    
    // Générer le contenu
    if ($format === 'json') {
        $content = $exporter->generateJSONContent($data);
        $mimeType = 'application/json';
    } else {
        $content = $exporter->generateCSVContent($data);
        $mimeType = 'text/csv; charset=utf-8';
    }

    // Envoyer les headers pour le téléchargement
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . strlen($content));
    
    // Output du contenu
    echo $content;
    exit;

} catch (Exception $e) {
    error_log("Erreur d'export: " . $e->getMessage());
    
    // Rediriger vers le tableau de bord avec un message d'erreur
    if (isset($_SERVER['HTTP_REFERER'])) {
        header('Location: ' . $_SERVER['HTTP_REFERER'] . '?export_error=' . urlencode($e->getMessage()));
    } else {
        header('Location: admin-interface.php?export_error=' . urlencode($e->getMessage()));
    }
    exit;
}
?>