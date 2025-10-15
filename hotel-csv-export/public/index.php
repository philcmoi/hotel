<?php
// Inclusion manuelle des classes - CHEMINS CORRIGÉS
require_once __DIR__ . '/../src/DatabaseConnection.php';
require_once __DIR__ . '/../src/CsvExporter.php';
require_once __DIR__ . '/../src/ExportManager.php';

// Chargement de la configuration
$config = require __DIR__ . '/../config/database.php';

try {
    // Initialisation des composants
    $pdo = DatabaseConnection::getConnection($config['database']);
    $csvExporter = new CsvExporter(
        $config['export']['directory'],
        $config['export']['delimiter'],
        $config['export']['enclosure'],
        $config['export']['escape']
    );
    $exportManager = new ExportManager($pdo, $csvExporter);
    
    // Nettoyage des anciens exports (garder les 10 derniers)
    $deletedFiles = $csvExporter->cleanupOldExports(10);
    
    // Export de toutes les tables
    $results = $exportManager->exportAllTables();
    
    // Affichage des résultats
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'exported_at' => date('Y-m-d H:i:s'),
        'results' => $results,
        'deleted_old_files' => $deletedFiles,
        'export_directory' => $csvExporter->getExportDirectory()
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'exported_at' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
}

// Fichier de débogage pour vérifier les chemins
/*echo "<h3>Débogage des chemins :</h3>";

$files_to_check = [
    'DatabaseConnection' => __DIR__ . '/../src/DatabaseConnection.php',
    'CsvExporter' => __DIR__ . '/../src/CsvExporter.php',
    'ExportManager' => __DIR__ . '/../src/ExportManager.php',
    'Config' => __DIR__ . '/../config/database.php'
];

foreach ($files_to_check as $name => $path) {
    if (file_exists($path)) {
        echo "✓ $name existe : " . $path . "<br>";
    } else {
        echo "✗ $name INTROUVABLE : " . $path . "<br>";
    }
}

// Arrêter l'exécution ici pour voir le débogage
die("Débogage terminé - Vérifiez les chemins ci-dessus");
*/