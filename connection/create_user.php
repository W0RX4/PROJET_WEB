<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Création d'un compte - StageArchive</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../public/css/style.css">
</head>
<body style="background-color: var(--background-color);">
    <div class="auth-wrapper">
        <div class="auth-card">
            <h1 class="auth-title">StageArchive</h1>
            <h2 class="text-center mb-4">Créer un compte</h2>
            
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
                        <option value="etudiant">Étudiant</option>
                        <option value="tuteur">Tuteur</option>
                        <option value="entreprise">Entreprise</option>
                        <option value="jury">Jury</option>
                        <option value="admin">Administrateur</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-block mt-4">Créer mon compte</button>
            </form>
            <div class="text-center mt-4">
                <a href="/login" style="color: var(--primary-color); text-decoration: none;">Déjà un compte ? Se connecter</a>
            </div>
        </div>
    </div>
</body>
</html>