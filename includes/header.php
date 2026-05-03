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
    <title>Portfolium - <?php echo ucfirst($userType); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../public/css/style.css">
</head>
<body>

<div class="app-container">
    <?php if ($userType): ?>
    <header class="navbar">
        <a href="<?php echo $homeLink; ?>" class="navbar-brand">Portfolium</a>

        <button class="hamburger" id="hamburger" aria-label="Menu" aria-expanded="false">
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
        </button>

        <!-- Desktop: nav stays inside header flow -->
        <nav class="nav-menu nav-menu--desktop" id="nav-menu-desktop">
            <ul class="navbar-nav">
                <li><a href="<?php echo $homeLink; ?>" class="nav-link">Accueil</a></li>
                <?php if ($userType === 'etudiant'): ?>
                    <li><a href="/app/user/mesCandidatures.php" class="nav-link">Mes Candidatures</a></li>
                    <li><a href="/app/user/cahierStage.php" class="nav-link">Cahier de stage</a></li>
                    <li><a href="/app/user/documents.php" class="nav-link">Documents</a></li>
                <?php elseif ($userType === 'entreprise'): ?>
                    <li><a href="/app/entreprise/accueilEntreprise.php" class="nav-link">Deposer une offre</a></li>
                    <li><a href="/app/entreprise/mesOffres.php" class="nav-link">Mes Offres</a></li>
                    <li><a href="/app/entreprise/conventions.php" class="nav-link">Conventions</a></li>
                <?php elseif ($userType === 'tuteur'): ?>
                    <li><a href="#" class="nav-link">Suivi Etudiants</a></li>
                    <li><a href="#" class="nav-link">Conventions</a></li>
                <?php elseif ($userType === 'jury'): ?>
                    <li><a href="#" class="nav-link">Dossiers a evaluer</a></li>
                <?php elseif ($userType === 'admin'): ?>
                    <li><a href="/app/admin/gestionComptes.php" class="nav-link">Comptes</a></li>
                    <li><a href="/app/admin/gestionOffres.php" class="nav-link">Offres</a></li>
                    <li><a href="/app/admin/validerConventions.php" class="nav-link">Conventions</a></li>
                    <li><a href="/app/admin/archives.php" class="nav-link">Archives</a></li>
                    <li><a href="/app/admin/gestionFormations.php" class="nav-link">Formations</a></li>
                <?php endif; ?>
                <li><a href="/app/account/annuaire.php" class="nav-link">Annuaire</a></li>
                <li><a href="/app/account/profil.php" class="nav-link">Profil</a></li>
                <li><a href="/app/account/security.php" class="nav-link">Securite</a></li>
                <li><span class="nav-user"><?php echo htmlspecialchars($username); ?></span></li>
                <li><a href="/logout" class="btn-logout">Deconnexion</a></li>
            </ul>
        </nav>
    </header>

    <!-- Mobile: overlay + nav OUTSIDE header to avoid stacking context issues -->
    <div class="nav-overlay" id="nav-overlay"></div>
    <nav class="nav-menu nav-menu--mobile" id="nav-menu-mobile">
        <ul class="navbar-nav">
            <li><a href="<?php echo $homeLink; ?>" class="nav-link">Accueil</a></li>
            <?php if ($userType === 'etudiant'): ?>
                <li><a href="/app/user/mesCandidatures.php" class="nav-link">Mes Candidatures</a></li>
                <li><a href="/app/user/cahierStage.php" class="nav-link">Cahier de stage</a></li>
                <li><a href="/app/user/documents.php" class="nav-link">Documents</a></li>
            <?php elseif ($userType === 'entreprise'): ?>
                <li><a href="/app/entreprise/accueilEntreprise.php" class="nav-link">Deposer une offre</a></li>
                <li><a href="/app/entreprise/mesOffres.php" class="nav-link">Mes Offres</a></li>
                <li><a href="/app/entreprise/conventions.php" class="nav-link">Conventions</a></li>
            <?php elseif ($userType === 'tuteur'): ?>
                <li><a href="#" class="nav-link">Suivi Etudiants</a></li>
                <li><a href="#" class="nav-link">Conventions</a></li>
            <?php elseif ($userType === 'jury'): ?>
                <li><a href="#" class="nav-link">Dossiers a evaluer</a></li>
            <?php elseif ($userType === 'admin'): ?>
                <li><a href="/app/admin/gestionComptes.php" class="nav-link">Comptes</a></li>
                <li><a href="/app/admin/gestionOffres.php" class="nav-link">Offres</a></li>
                <li><a href="/app/admin/validerConventions.php" class="nav-link">Conventions</a></li>
                <li><a href="/app/admin/archives.php" class="nav-link">Archives</a></li>
                <li><a href="/app/admin/gestionFormations.php" class="nav-link">Formations</a></li>
            <?php endif; ?>
            <li><a href="/app/account/annuaire.php" class="nav-link">Annuaire</a></li>
            <li><a href="/app/account/profil.php" class="nav-link">Profil</a></li>
            <li><a href="/app/account/security.php" class="nav-link">Securite</a></li>
            <li><span class="nav-user"><?php echo htmlspecialchars($username); ?></span></li>
            <li><a href="/logout" class="btn-logout">Deconnexion</a></li>
        </ul>
    </nav>
    <?php endif; ?>

    <script>
    (function() {
        var hamburger = document.getElementById('hamburger');
        var navMobile = document.getElementById('nav-menu-mobile');
        var overlay = document.getElementById('nav-overlay');

        function closeMenu() {
            if (navMobile) navMobile.classList.remove('open');
            if (hamburger) {
                hamburger.classList.remove('open');
                hamburger.setAttribute('aria-expanded', 'false');
            }
            if (overlay) overlay.classList.remove('active');
            document.body.style.overflow = '';
        }

        function openMenu() {
            if (navMobile) navMobile.classList.add('open');
            if (hamburger) {
                hamburger.classList.add('open');
                hamburger.setAttribute('aria-expanded', 'true');
            }
            if (overlay) overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        if (hamburger) {
            hamburger.addEventListener('click', function() {
                var isOpen = navMobile && navMobile.classList.contains('open');
                isOpen ? closeMenu() : openMenu();
            });
        }

        if (overlay) {
            overlay.addEventListener('click', closeMenu);
        }

        if (navMobile) {
            navMobile.querySelectorAll('a').forEach(function(link) {
                link.addEventListener('click', closeMenu);
            });
        }

        // Navbar shadow on scroll
        var navbar = document.querySelector('.navbar');
        if (navbar) {
            window.addEventListener('scroll', function() {
                if (window.scrollY > 10) {
                    navbar.style.boxShadow = '0 4px 20px rgba(0,0,0,0.06)';
                } else {
                    navbar.style.boxShadow = 'none';
                }
            }, { passive: true });
        }
    })();
    </script>

    <main class="main-content">
