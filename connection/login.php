<?php
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - StageArchive</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../public/css/style.css">
</head>
<body style="background-color: var(--background-color);">

    <div class="auth-wrapper">
        <div class="auth-card">
            <h1 class="auth-title">StageArchive</h1>
            <h2 class="text-center mb-4">Connexion</h2>
            
            <?php
                if (isset($_SESSION['error'])) {
                    echo "<div class='alert alert-error'>" . $_SESSION['error'] . "</div>";
                    unset($_SESSION['error']);
                }
                if (isset($_SESSION['success'])) {
                    echo "<div class='alert alert-success'>" . $_SESSION['success'] . "</div>";
                    unset($_SESSION['success']);
                }
            ?>

            <form action="/login" method="post">
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" placeholder="votre@email.com" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Mot de passe</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block mt-4">Se connecter</button>
            </form>
            <div class="text-center mt-4">
                <a href="/register" style="color: var(--primary-color); text-decoration: none;">Pas encore de compte ? S'inscrire</a>
            </div>
        </div>
    </div>

</body>
</html>