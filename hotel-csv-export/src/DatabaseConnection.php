<?php

class DatabaseConnection
{
    private static ?PDO $instance = null;
    
    public static function getConnection(array $config): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $config['host'],
                $config['port'],
                $config['dbname'],
                $config['charset']
            );
            
            self::$instance = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                $config['options']
            );
        }
        
        return self::$instance;
    }
    
    public static function closeConnection(): void
    {
        self::$instance = null;
    }
}
?>