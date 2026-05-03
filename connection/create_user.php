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
    <title>Inscription - Portfolium</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../public/css/style.css">
</head>
<body>

    <div class="auth-wrapper">
        <div class="auth-card">
            <h1 class="auth-title">Portfolium</h1>
            <h2 class="text-center mb-4" style="color: var(--text-secondary); font-weight: 500; font-size: 1rem;">Creez votre compte</h2>

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

            <form method="post" action="/register">
                <div class="form-group">
                    <label class="form-label">Nom d'utilisateur</label>
                    <input type="text" name="username" class="form-control" placeholder="Entrez votre nom" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" placeholder="votre@email.com" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Mot de passe</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Je suis un(e)</label>
                    <select name="type" class="form-control">
                        <option value="etudiant">Etudiant</option>
                        <option value="tuteur">Tuteur</option>
                        <option value="entreprise">Entreprise</option>
                        <option value="jury">Jury</option>
                        <option value="admin">Administrateur</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-block mt-4">Creer mon compte</button>
            </form>
            <p class="text-center mt-4" style="color: var(--text-secondary); font-size: 0.9rem;">
                La connexion est geree par Supabase Auth. La double authentification pourra ensuite etre activee dans l'espace <strong>Securite</strong>.
            </p>
            <p class="text-center mt-4" style="color: var(--text-secondary); font-size: 0.85rem;">
                Les comptes <strong>administrateur</strong> doivent etre valides par un administrateur existant avant de pouvoir se connecter.
            </p>
            <div class="text-center mt-4">
                <a href="/login" style="color: var(--accent-color); text-decoration: none; font-weight: 500; font-size: 0.9rem;">Deja un compte ? Se connecter</a>
            </div>
        </div>
    </div>

</body>
</html>
