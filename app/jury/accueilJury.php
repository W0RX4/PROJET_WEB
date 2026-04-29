<?php
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['type']) || $_SESSION['type'] !== 'jury') {
        header('Location: /login');
        exit;
    }

    require_once __DIR__ . '/../../includes/header.php';
?>

<div class="card">
    <h2>Espace Jury</h2>
    <p>Bienvenue, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Jury'); ?>. Vous pouvez consulter les évaluations ici.</p>
</div>

<div class="card">
    <h3>Soutenances à évaluer</h3>
    <p>Liste des soutenances à venir...</p>
</div>

<?php require_once '../../includes/footer.php'; ?>
