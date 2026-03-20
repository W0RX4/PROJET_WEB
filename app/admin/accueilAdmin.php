<?php
    require_once '../../includes/header.php';
    if($_SESSION['type'] !== 'admin'){
        header('Location: ../../connection/login.php');
        exit;
    }
?>

<div class="card">
    <h2>Tableau de bord Administrateur</h2>
    <p>Bienvenue, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>.</p>
</div>

<div class="grid-container">
    <div class="card">
        <h3>Gestion des Comptes</h3>
        <p>Gérez les accès des étudiants, tuteurs, entreprises et jurys.</p>
        <button class="btn btn-primary mt-4">Gérer les comptes</button>
    </div>
    <div class="card">
        <h3>Offres de Stage</h3>
        <p>Consultez et validez les offres déposées par les entreprises.</p>
        <button class="btn btn-primary mt-4">Gérer les offres</button>
    </div>
    <div class="card">
        <h3>Configurations</h3>
        <p>Paramètres de la plateforme et cycles d'archivage.</p>
        <button class="btn btn-primary mt-4">Paramètres</button>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
