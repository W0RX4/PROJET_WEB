<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userType = $_SESSION['type'] ?? '';
$username = $_SESSION['username'] ?? '';

// Determine home link based on type
$homeLink = '#';
if ($userType === 'etudiant') $homeLink = '/app/user/accueilUser.php';
elseif ($userType === 'entreprise') $homeLink = '/app/entreprise/accueilEntreprise.php';
elseif ($userType === 'tuteur') $homeLink = '/app/tuteur/accueilTuteur.php';
elseif ($userType === 'jury') $homeLink = '/app/jury/accueilJury.php';
elseif ($userType === 'admin') $homeLink = '/app/admin/accueilAdmin.php';

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archivage de Stages - <?php echo ucfirst($userType); ?></title>
    <!-- On suppose que le projet est à la racine, on utilise un chemin absolu vers CSS ou relatif -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../public/css/style.css">
    <!-- Pour gérer le cas de test_db.php etc qui n'est pas dans app/ on rajoute un fallback simple ou on assume que les pages sont dans /app/ ou /connection/ -->
</head>
<body>

<div class="app-container">
    <?php if ($userType): ?>
    <header class="navbar">
        <a href="<?php echo $homeLink; ?>" class="navbar-brand">StageArchive</a>

        <button class="hamburger" id="hamburger" aria-label="Menu" aria-expanded="false">
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
        </button>

        <nav class="nav-menu" id="nav-menu">
            <ul class="navbar-nav">
                <li><a href="<?php echo $homeLink; ?>" class="nav-link">Accueil</a></li>
                
                <?php if ($userType === 'etudiant'): ?>
                    <li><a href="../user/mesCandidatures.php" class="nav-link">Mes Candidatures</a></li>
                    <li><a href="#" class="nav-link">Cahier de stage</a></li>
                <?php elseif ($userType === 'entreprise'): ?>
                    <li><a href="../entreprise/ajouterStage.php" class="nav-link">Déposer une offre</a></li>
                    <li><a href="../entreprise/mesOffres.php" class="nav-link">Mes Offres</a></li>
                <?php elseif ($userType === 'tuteur'): ?>
                    <li><a href="#" class="nav-link">Suivi Etudiants</a></li>
                    <li><a href="#" class="nav-link">Conventions</a></li>
                <?php elseif ($userType === 'jury'): ?>
                    <li><a href="#" class="nav-link">Dossiers à évaluer</a></li>
                <?php elseif ($userType === 'admin'): ?>
                    <li><a href="#" class="nav-link">Comptes</a></li>
                    <li><a href="#" class="nav-link">Toutes les offres</a></li>
                <?php endif; ?>
                
                <li><span style="color:var(--text-secondary); margin-left: 1rem;">👤 <?php echo htmlspecialchars($username); ?></span></li>
                <li><a href="../../connection/logout.php" class="btn-logout">Déconnexion</a></li>
            </ul>
        </nav>
    </header>
    <?php endif; ?>

    <script>
        const hamburger = document.getElementById('hamburger');
        const navMenu = document.getElementById('nav-menu');
        if (hamburger && navMenu) {
            hamburger.addEventListener('click', function () {
                const isOpen = navMenu.classList.toggle('open');
                hamburger.classList.toggle('open');
                hamburger.setAttribute('aria-expanded', isOpen);
            });
            // Close menu when clicking a link
            navMenu.querySelectorAll('.nav-link, .btn-logout').forEach(function (link) {
                link.addEventListener('click', function () {
                    navMenu.classList.remove('open');
                    hamburger.classList.remove('open');
                    hamburger.setAttribute('aria-expanded', 'false');
                });
            });
        }
    </script>

    <main class="main-content">
