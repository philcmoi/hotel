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
                $query = "SELECT 
                            idClient,
                            nom,
                            prenom,
                            email,
                            telephone,
                            adresse,
                            ville,
                            code_postal,
                            pays,
                            date_creation
                          FROM clients 
                          ORDER BY date_creation DESC";
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

    // Générer le CSV
    public function generateCSV($data, $filename) {
        if (empty($data)) {
            throw new Exception("Aucune donnée à exporter");
        }

        // Créer le dossier d'export s'il n'existe pas
        $exportDir = __DIR__ . '/hotel-csv-export';
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        $filepath = $exportDir . '/' . $filename;
        
        // Ouvrir le fichier en écriture
        $file = fopen($filepath, 'w');
        
        if (!$file) {
            throw new Exception("Impossible de créer le fichier d'export");
        }

        // Ajouter BOM UTF-8 pour Excel
        fwrite($file, "\xEF\xBB\xBF");
        
        // Écrire l'en-tête
        $headers = array_keys($data[0]);
        fputcsv($file, $headers, ';');
        
        // Écrire les données
        foreach ($data as $row) {
            fputcsv($file, $row, ';');
        }
        
        fclose($file);
        
        return $filepath;
    }

    // Générer le JSON
    public function generateJSON($data, $filename) {
        if (empty($data)) {
            throw new Exception("Aucune donnée à exporter");
        }

        // Créer le dossier d'export s'il n'existe pas
        $exportDir = __DIR__ . '/hotel-csv-export';
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        $filepath = $exportDir . '/' . $filename;
        
        // Écrire les données JSON
        $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if (file_put_contents($filepath, $jsonData) === false) {
            throw new Exception("Impossible de créer le fichier d'export");
        }
        
        return $filepath;
    }
}

try {
    // Récupérer les paramètres
    $type = $_GET['type'] ?? '';
    $format = $_GET['format'] ?? 'csv';
    $startDate = $_GET['start_date'] ?? null;
    $endDate = $_GET['end_date'] ?? null;

    if (empty($type)) {
        throw new Exception("Type d'export non spécifié");
    }

    $exporter = new DataExporter();
    
    // Récupérer les données
    $data = $exporter->getData($type, $startDate, $endDate);
    
    // Générer le nom de fichier
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "{$type}_export_{$timestamp}.{$format}";
    
    // Générer le fichier
    if ($format === 'json') {
        $filepath = $exporter->generateJSON($data, $filename);
        $mimeType = 'application/json';
    } else {
        $filepath = $exporter->generateCSV($data, $filename);
        $mimeType = 'text/csv';
    }

    // Télécharger le fichier
    if (file_exists($filepath)) {
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filepath));
        
        readfile($filepath);
        
        // Supprimer le fichier après téléchargement (optionnel)
        unlink($filepath);
        
        exit;
    } else {
        throw new Exception("Fichier d'export non trouvé");
    }

} catch (Exception $e) {
    // Rediriger vers le tableau de bord avec un message d'erreur
    header('Location: admin-interface.php?export_error=' . urlencode($e->getMessage()));
    exit;
}