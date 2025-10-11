<?php
session_start();

// Test du mot de passe
$password = "L099339R";
$hash = '$2y$10$8d9zZcLmJQ7W6qYhKpNV3uY5rSxwA2bC1dF3gH4jK5lM6nV7B8tX9y';

echo "Mot de passe testé: " . $password . "<br>";
echo "Hash stocké: " . $hash . "<br>";

if (password_verify($password, $hash)) {
    echo "✅ Le mot de passe correspond!";
} else {
    echo "❌ Le mot de passe ne correspond pas!";
    
    // Générer un nouveau hash
    $new_hash = password_hash($password, PASSWORD_DEFAULT);
    echo "<br>Nouveau hash à utiliser: " . $new_hash;
}
?>