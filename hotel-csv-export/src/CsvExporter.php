<?php

class CsvExporter
{
    private string $exportDirectory;
    private string $delimiter;
    private string $enclosure;
    private string $escape;
    
    public function __construct(
        string $exportDirectory,
        string $delimiter = ',',
        string $enclosure = '"',
        string $escape = '\\'
    ) {
        $this->exportDirectory = rtrim($exportDirectory, '/') . '/';
        $this->delimiter = $delimiter;
        $this->enclosure = $enclosure;
        $this->escape = $escape;
        
        $this->ensureExportDirectoryExists();
    }
    
    private function ensureExportDirectoryExists(): void
    {
        if (!is_dir($this->exportDirectory)) {
            if (!mkdir($this->exportDirectory, 0755, true)) {
                throw new RuntimeException("Impossible de créer le dossier d'export: " . $this->exportDirectory);
            }
        }
        
        if (!is_writable($this->exportDirectory)) {
            throw new RuntimeException("Le dossier d'export n'est pas accessible en écriture: " . $this->exportDirectory);
        }
    }
    
    public function exportTableToCsv(
        PDO $pdo, 
        string $tableName, 
        string $filename = null,
        array $columns = ['*'],
        string $where = '',
        array $params = []
    ): string {
        // Construction de la requête
        $columnsList = $columns === ['*'] ? '*' : implode(', ', $columns);
        $sql = "SELECT {$columnsList} FROM `{$tableName}`";
        
        if (!empty($where)) {
            $sql .= " WHERE {$where}";
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        // Génération du nom de fichier
        $filename = $filename ?? $this->generateFilename($tableName);
        $filepath = $this->exportDirectory . $filename;
        
        // Ouverture du fichier
        $file = fopen($filepath, 'w');
        if ($file === false) {
            throw new RuntimeException("Impossible d'ouvrir le fichier: {$filepath}");
        }
        
        try {
            // Écriture des en-têtes
            $this->writeHeaders($file, $stmt, $columns);
            
            // Écriture des données
            $this->writeData($file, $stmt);
            
        } finally {
            fclose($file);
        }
        
        return $filepath;
    }
    
    private function writeHeaders($file, PDOStatement $stmt, array $columns): void
    {
        if ($columns === ['*']) {
            $columnCount = $stmt->columnCount();
            $headers = [];
            
            for ($i = 0; $i < $columnCount; $i++) {
                $meta = $stmt->getColumnMeta($i);
                $headers[] = $meta['name'];
            }
        } else {
            $headers = $columns;
        }
        
        fputcsv($file, $headers, $this->delimiter, $this->enclosure, $this->escape);
    }
    
    private function writeData($file, PDOStatement $stmt): void
    {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($file, $row, $this->delimiter, $this->enclosure, $this->escape);
        }
    }
    
    private function generateFilename(string $tableName): string
    {
        $timestamp = date('Y-m-d_H-i-s');
        return sprintf('%s_export_%s.csv', $tableName, $timestamp);
    }
    
    public function getExportDirectory(): string
    {
        return $this->exportDirectory;
    }
    
    public function getExportedFiles(): array
    {
        $files = glob($this->exportDirectory . '*.csv');
        return $files ?: [];
    }
    
    public function cleanupOldExports(int $keepLast = 10): array
    {
        $files = $this->getExportedFiles();
        
        if (count($files) <= $keepLast) {
            return [];
        }
        
        // Trie par date de modification (plus récent en premier)
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        $deleted = [];
        $filesToDelete = array_slice($files, $keepLast);
        
        foreach ($filesToDelete as $file) {
            if (unlink($file)) {
                $deleted[] = $file;
            }
        }
        
        return $deleted;
    }
}
?>