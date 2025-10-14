<?php
session_start();
$error = ''; // Initialisation de la variable error

// Si déjà connecté, rediriger vers l'interface admin
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: admin-interface.php');
    exit;
}

// Connexion à la base de données (à adapter selon votre configuration)
include 'db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // 1. Vérifier d'abord si l'utilisateur existe
    $sql = "SELECT password FROM users WHERE username = :username";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':username', $username);
    
    if ($stmt->execute()) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // 2. Vérifier le mot de passe avec password_verify
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $username;
            $_SESSION['login_time'] = time();
            
            header('Location: admin-interface.php');
            exit;
        } else {
            $error = 'Identifiants incorrects';
        }
    } else {
        $error = 'Erreur lors de la vérification';
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Administrateur</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #4361ee 0%, #7209b7 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .login-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo h1 {
            color: #4361ee;
            font-size: 1.8rem;
        }

        .logo span {
            color: #7209b7;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5ee;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #4361ee;
        }

        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #4361ee 0%, #7209b7 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .btn-login:hover {
            transform: translateY(-2px);
        }

        .error {
            background: #ff4757;
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }

        .demo-credentials {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #4361ee;
        }

        .demo-credentials h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 1rem;
        }

        .demo-credentials p {
            color: #666;
            font-size: 0.9rem;
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>Hôtel<span>Premium</span></h1>
            <p>Espace Administrateur</p>
        </div>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Nom d'utilisateur</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn-login">Se connecter</button>
        </form>

        <div class="demo-credentials">
            <h3>Identifiants de démonstration :</h3>
            <p><strong>Utilisateur :</strong> admin</p>
            <p><strong>Mot de passe :</strong> admin123</p>
            <p style="margin-top: 10px; font-size: 0.8rem; color: #ff4757;">
                ⚠️ À changer impérativement en production !
            </p>
        </div>
    </div>
</body>
</html>