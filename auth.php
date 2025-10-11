<?php
require_once 'config.php';

class Auth {
    private $conn;

    public function __construct() {
        $this->conn = $this->getConnection();
    }

    private function getConnection() {
        try {
            $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $conn->exec("set names utf8");
            return $conn;
        } catch(PDOException $e) {
            die("Erreur de connexion: " . $e->getMessage());
        }
    }

    // Vérifier les identifiants
    public function login($username, $password) {
        try {
            $query = "SELECT id, username, password, role FROM users WHERE username = :username AND active = 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['last_activity'] = time();
                
                return ['success' => true, 'message' => 'Connexion réussie'];
            }
            
            return ['success' => false, 'message' => 'Identifiants incorrects'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erreur de connexion: ' . $e->getMessage()];
        }
    }

    // Déconnexion
    public function logout() {
        session_unset();
        session_destroy();
        return ['success' => true, 'message' => 'Déconnexion réussie'];
    }

    // Créer un utilisateur admin
    public function createAdminUser() {
        try {
            // Vérifier si la table users existe
            $checkTable = $this->conn->query("SHOW TABLES LIKE 'users'");
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
                $this->conn->exec($createTable);
            }

            // Vérifier si l'admin existe déjà
            $checkAdmin = $this->conn->prepare("SELECT id FROM users WHERE username = 'admin'");
            $checkAdmin->execute();
            
            if ($checkAdmin->rowCount() == 0) {
                // Créer l'utilisateur admin avec mot de passe simple
                $password = password_hash('admin123', PASSWORD_DEFAULT);
                $query = "INSERT INTO users (username, password, email, role) VALUES ('admin', :password, 'admin@hotel.com', 'admin')";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':password', $password);
                $stmt->execute();
                
                return "✅ Utilisateur admin créé avec succès.<br><strong>Identifiants:</strong><br>Nom d'utilisateur: <strong>admin</strong><br>Mot de passe: <strong>admin123</strong>";
            }
            
            return "ℹ️ L'utilisateur admin existe déjà";
            
        } catch (Exception $e) {
            return "❌ Erreur: " . $e->getMessage();
        }
    }
}
?>