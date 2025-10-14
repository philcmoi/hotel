<?php
// Créer l'utilisateur admin avec le bon hash
session_start();

// Configuration de la base de données
$host = 'localhost';
$dbname = 'hotel';
$username = 'root';
$password = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Vérifier si la table users existe
    $checkTable = $conn->query("SHOW TABLES LIKE 'users'");
    if ($checkTable->rowCount() == 0) {
        // Créer la table users
        $createTable = "
            CREATE TABLE users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                email VARCHAR(100),
                role ENUM('admin', 'manager') DEFAULT 'admin',
                active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        $conn->exec($createTable);
        echo "✅ Table users créée<br>";
    }
    
    // Supprimer l'admin existant s'il existe
    $conn->exec("DELETE FROM users WHERE username = 'admin'");
    
    // Créer le mot de passe hashé
    $password_hash = password_hash('L099339R', PASSWORD_DEFAULT);
    
    // Insérer le nouvel admin
    $stmt = $conn->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)");
    $stmt->execute(['admin', $password_hash, 'admin@hotel.com', 'admin']);
    
    echo "✅ Utilisateur admin créé avec succès!<br>";
    echo "Nom d'utilisateur: <strong></strong><br>";
    echo "Mot de passe: <strong></strong><br>";
    echo "Hash utilisé: " . $password_hash . "<br>";
    
    // Vérifier que ça fonctionne
    $verify = $conn->prepare("SELECT * FROM users WHERE username = 'admin'");
    $verify->execute();
    $user = $verify->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify('L099339R', $user['password'])) {
        echo "✅ Vérification: Le mot de passe fonctionne!";
    } else {
        echo "❌ Vérification: Le mot de passe ne fonctionne pas";
    }
    
} catch(PDOException $e) {
    echo "❌ Erreur: " . $e->getMessage();
}
?>