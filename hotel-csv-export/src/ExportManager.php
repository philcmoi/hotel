<?php

class ExportManager
{
    private PDO $pdo;
    private CsvExporter $csvExporter;
    private array $tableQueries;
    
    public function __construct(PDO $pdo, CsvExporter $csvExporter)
    {
        $this->pdo = $pdo;
        $this->csvExporter = $csvExporter;
        
        $this->initializeTableQueries();
    }
    
    private function initializeTableQueries(): void
    {
        $this->tableQueries = [
            'clients' => [
                'columns' => ['*'],
                'where' => '',
                'params' => []
            ],
            'chambres' => [
                'columns' => ['*'],
                'where' => '',
                'params' => []
            ],
            'reservations' => [
                'columns' => ['*'],
                'where' => '',
                'params' => []
            ],
            'reservation_chambres' => [
                'columns' => ['*'],
                'where' => '',
                'params' => []
            ],
            'paiements' => [
                'columns' => ['*'],
                'where' => '',
                'params' => []
            ]
        ];
    }
    
    public function exportAllTables(): array
    {
        $results = [];
        $timestamp = date('Y-m-d H:i:s');
        
        foreach (array_keys($this->tableQueries) as $tableName) {
            try {
                $filepath = $this->exportTable($tableName);
                $results[$tableName] = [
                    'status' => 'success',
                    'filepath' => $filepath,
                    'filename' => basename($filepath),
                    'exported_at' => $timestamp
                ];
            } catch (Exception $e) {
                $results[$tableName] = [
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'exported_at' => $timestamp
                ];
            }
        }
        
        return $results;
    }
    
    public function exportTable(string $tableName, string $customFilename = null): string
    {
        if (!isset($this->tableQueries[$tableName])) {
            throw new InvalidArgumentException("Table non gérée: {$tableName}");
        }
        
        $query = $this->tableQueries[$tableName];
        
        return $this->csvExporter->exportTableToCsv(
            $this->pdo,
            $tableName,
            $customFilename,
            $query['columns'],
            $query['where'],
            $query['params']
        );
    }
    
    public function getAvailableTables(): array
    {
        $stmt = $this->pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return $tables;
    }
    
    public function getTableInfo(string $tableName): array
    {
        $stmt = $this->pdo->query("DESCRIBE {$tableName}");
        return $stmt->fetchAll();
    }
    
    public function addCustomTableQuery(string $tableName, array $columns = ['*'], string $where = '', array $params = []): void
    {
        $this->tableQueries[$tableName] = [
            'columns' => $columns,
            'where' => $where,
            'params' => $params
        ];
    }
}
?>