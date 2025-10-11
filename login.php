<?php
require_once 'config.php';

// Debug: Vérifier si auth.php existe
if (!file_exists('auth.php')) {
    die("❌ Le fichier auth.php n'existe pas");
}

require_once 'auth.php';

// Debug: Vérifier si la classe Auth existe
if (!class_exists('Auth')) {
    die("❌ La classe Auth n'existe pas dans auth.php");
}

$auth = new Auth();
$error = '';

// Rediriger si déjà connecté
if (isLoggedIn()) {
    redirect('admin-interface.php');
}

// Traitement du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = "Veuillez remplir tous les champs";
    } else {
        $result = $auth->login($username, $password);
if ($result['success']) {
    redirect('admin-interface.php');  // Au lieu de admin-reservation.html    } else {
            $error = $result['message'];
        }
    }
}

// Créer l'utilisateur admin si demandé
if (isset($_GET['init']) && $_GET['init'] === '1') {
    $message = $auth->createAdminUser();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - <?php echo APP_NAME; ?></title>
    <style>
        /* Votre CSS existant */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-container { background: white; padding: 40px; border-radius: 15px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        .login-header { text-align: center; margin-bottom: 30px; }
        .login-header h1 { color: #333; margin-bottom: 10px; font-size: 24px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; color: #333; font-weight: 500; }
        .form-group input { width: 100%; padding: 12px 15px; border: 2px solid #e1e1e1; border-radius: 8px; font-size: 14px; transition: border-color 0.3s; }
        .form-group input:focus { outline: none; border-color: #667eea; }
        .btn-login { width: 100%; padding: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: transform 0.2s; }
        .btn-login:hover { transform: translateY(-2px); }
        .error-message { background: #fee; color: #c33; padding: 10px; border-radius: 5px; margin-bottom: 20px; text-align: center; border: 1px solid #fcc; }
        .success-message { background: #efe; color: #363; padding: 10px; border-radius: 5px; margin-bottom: 20px; text-align: center; border: 1px solid #cfc; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>🔐 Connexion</h1>
            <p>Administration Hôtel</p>
        </div>

        <?php if (isset($message)): ?>
            <div class="success-message">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="error-message">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="login" value="1">
            
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

        <div style="text-align: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee;">
            <a href="login.php?init=1" style="color: #667eea; text-decoration: none; font-size: 12px;">
                Première installation ? Créer l'utilisateur admin
            </a>
        </div>
    </div>
</body>
</html>