<?php

return [
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'dbname' => 'hotel',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    ],
    'export' => [
        'directory' => __DIR__ . '/../exports/',
        'date_format' => 'Y-m-d_H-i-s',
        'include_headers' => true,
        'delimiter' => ',',
        'enclosure' => '"',
        'escape' => '\\'
    ]
];
?>