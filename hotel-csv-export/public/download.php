<?php
// download.php - Téléchargement direct vers l'ordinateur client
require_once __DIR__ . '/../src/DatabaseConnection.php';
require_once __DIR__ . '/../src/CsvExporter.php';
require_once __DIR__ . '/../src/ExportManager.php';

// Activer l'affichage des erreurs pour le débogage
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Récupérer les paramètres
    $table = $_GET['table'] ?? '';
    
    if (empty($table)) {
        throw new Exception("Aucune table spécifiée. Utilisation: download.php?table=nom_table");
    }
    
    // Charger la configuration
    $config = require __DIR__ . '/../config/database.php';
    
    // Initialiser les composants
    $pdo = DatabaseConnection::getConnection($config['database']);
    $csvExporter = new CsvExporter($config['export']['directory']);
    $exportManager = new ExportManager($pdo, $csvExporter);
    
    // Vérifier que la table existe
    $tables = $exportManager->getAvailableTables();
    if (!in_array($table, $tables)) {
        throw new Exception("Table '$table' non trouvée. Tables disponibles: " . implode(', ', $tables));
    }
    
    // Générer le nom de fichier pour le téléchargement
    $timestamp = date('Y-m-d_H-i-s');
    $filename = $table . '_export_' . $timestamp . '.csv';
    
    // Créer un fichier temporaire en mémoire
    $tempFile = 'php://output';
    
    // Configurer les headers pour le téléchargement
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    
    // Ouvrir le flux de sortie
    $output = fopen($tempFile, 'w');
    
    // Exporter les données directement dans le flux de sortie
    exportTableToOutput($pdo, $table, $output);
    
    fclose($output);
    exit;
    
} catch (Exception $e) {
    // Afficher l'erreur de manière claire
    http_response_code(500);
    echo "<h2>Erreur d'export CSV</h2>";
    echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Fichier:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p><strong>Ligne:</strong> " . htmlspecialchars($e->getLine()) . "</p>";
    
    // Lien de retour
    echo '<p><a href="javascript:history.back()">← Retour</a></p>';
}

/**
 * Exporte une table directement vers le flux de sortie
 */
function exportTableToOutput(PDO $pdo, string $tableName, $output): void
{
    // Construction de la requête
    $sql = "SELECT * FROM `{$tableName}`";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    // Écriture des en-têtes
    $columnCount = $stmt->columnCount();
    $headers = [];
    
    for ($i = 0; $i < $columnCount; $i++) {
        $meta = $stmt->getColumnMeta($i);
        $headers[] = $meta['name'];
    }
    
    fputcsv($output, $headers);
    
    // Écriture des données
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
}
?>