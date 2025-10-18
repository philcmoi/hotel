<?php
// downloads.php - Interface de téléchargement
require_once __DIR__ . '/../src/DatabaseConnection.php';
require_once __DIR__ . '/../src/CsvExporter.php';
require_once __DIR__ . '/../src/ExportManager.php';

$config = require __DIR__ . '/../config/database.php';

try {
    $pdo = DatabaseConnection::getConnection($config['database']);
    $csvExporter = new CsvExporter($config['export']['directory']);
    $exportManager = new ExportManager($pdo, $csvExporter);
    
    // Récupérer les fichiers existants
    $exportedFiles = $csvExporter->getExportedFiles();
    $filesInfo = [];
    
    foreach ($exportedFiles as $filepath) {
        $filesInfo[] = [
            'filename' => basename($filepath),
            'filepath' => $filepath,
            'size' => filesize($filepath),
            'modified' => date('Y-m-d H:i:s', filemtime($filepath))
        ];
    }
    
    // Tables disponibles
    $tables = $exportManager->getAvailableTables();
    
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Téléchargement des exports CSV - Hôtel</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        .section { margin: 30px 0; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        .table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .table th { background-color: #007bff; color: white; }
        .btn { display: inline-block; padding: 10px 15px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin: 5px; }
        .btn:hover { background: #0056b3; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #1e7e34; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .file-size { color: #666; font-size: 0.9em; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 Exports CSV - Gestion Hôtelière</h1>
        
        <?php if (isset($error)): ?>
            <div class="error">Erreur: <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <!-- Section : Export rapide -->
        <div class="section">
            <h2>🚀 Export rapide</h2>
            <p>Exportez immédiatement une table complète :</p>
            <div class="export-buttons">
                <?php foreach ($tables as $table): ?>
                    <a href="download.php?table=<?= urlencode($table) ?>" class="btn">
                        📥 Exporter <?= htmlspecialchars($table) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Section : Fichiers existants -->
        <div class="section">
            <h2>📁 Fichiers exportés disponibles</h2>
            
            <?php if (empty($filesInfo)): ?>
                <p>Aucun fichier exporté disponible.</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nom du fichier</th>
                            <th>Taille</th>
                            <th>Date de modification</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filesInfo as $file): ?>
                            <tr>
                                <td><?= htmlspecialchars($file['filename']) ?></td>
                                <td class="file-size"><?= $this->formatFileSize($file['size']) ?></td>
                                <td><?= $file['modified'] ?></td>
                                <td>
                                    <a href="download.php?filename=<?= urlencode($file['filename']) ?>" class="btn btn-success">
                                        📥 Télécharger
                                    </a>
                                    <a href="preview.php?filename=<?= urlencode($file['filename']) ?>" target="_blank" class="btn">
                                        👁️ Aperçu
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <a href="cleanup.php" class="btn btn-danger" onclick="return confirm('Supprimer les anciens exports (garder les 10 derniers) ?')">
                    🗑️ Nettoyer les anciens exports
                </a>
            <?php endif; ?>
        </div>
        
        <!-- Section : Export avec filtres -->
        <div class="section">
            <h2>🎛️ Export avec filtres</h2>
            <form action="custom_export.php" method="POST">
                <div style="margin: 10px 0;">
                    <label for="table">Table :</label>
                    <select name="table" id="table" required>
                        <option value="">Choisir une table</option>
                        <?php foreach ($tables as $table): ?>
                            <option value="<?= htmlspecialchars($table) ?>"><?= htmlspecialchars($table) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="margin: 10px 0;">
                    <label for="columns">Colonnes (optionnel) :</label>
                    <input type="text" name="columns" id="columns" placeholder="col1,col2,col3 ou * pour toutes">
                </div>
                
                <div style="margin: 10px 0;">
                    <label for="where">Condition WHERE (optionnel) :</label>
                    <input type="text" name="where" id="where" placeholder="etat = 'actif'">
                </div>
                
                <button type="submit" class="btn">🚀 Exporter avec filtres</button>
            </form>
        </div>
    </div>
</body>
</html>

<?php
// Fonction helper pour formater la taille des fichiers
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>