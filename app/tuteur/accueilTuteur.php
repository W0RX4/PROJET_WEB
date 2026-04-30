<?php
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['type']) || $_SESSION['type'] !== 'tuteur') {
        header('Location: /login');
        exit;
    }

    require_once __DIR__ . '/../../includes/header.php';
?>

<div class="card">
    <h2>Bienvenue sur votre espace Tuteur, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Tuteur'); ?> !</h2>
    <p>Suivez l'avancement des étudiants dont vous avez la charge.</p>
</div>

<div class="grid-container">
    <!-- Placeholder for student items -->
    <div class="card">
        <h3>Aucun étudiant assigné</h3>
        <p>Vous n'avez pas encore d'étudiants assignés à suivre.</p>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>