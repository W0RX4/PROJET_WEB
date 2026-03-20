<?php
    require_once '../../includes/header.php';

    if($_SESSION['type'] !== 'entreprise'){
        header('Location: ../../connection/login.php');
        exit;
    }

    require_once __DIR__ . '/../../vendor/autoload.php';

    use Dotenv\Dotenv;
    use Supabase\Client\Functions;

    $dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
    $dotenv->safeLoad();

    $client = new Functions($_ENV['SUPABASE_URL'] ?? '', $_ENV['SUPABASE_KEY'] ?? '');

    $mesStages = $client->getAllData('stages', ['company' => $_SESSION['username'] ?? '']);
?>

<div class="card mes-offres-hero">
    <h2>Mes offres de stage disponibles</h2>
    <p>Retrouvez ici toutes les offres publiées par votre entreprise.</p>
</div>

<?php if (empty($mesStages)): ?>
    <div class="card mes-offres-empty">
        <h3>Aucune offre publiée</h3>
        <p>Vous n'avez pas encore publié d'offre de stage.</p>
    </div>
<?php else: ?>
    <div class="grid-container">
        <?php foreach ($mesStages as $stage): ?>
            <div class="card offre-card">
                <h3 class="offre-title"><?php echo htmlspecialchars($stage['title'] ?? 'Titre non disponible'); ?></h3>
                <p class="offre-company"><?php echo htmlspecialchars($stage['company'] ?? 'Entreprise non disponible'); ?></p>
                <p class="offre-desc"><?php echo nl2br(htmlspecialchars($stage['description'] ?? 'Description non disponible')); ?></p>

                <div class="offre-meta">
                    <p><?php echo htmlspecialchars($stage['location'] ?? 'Lieu non disponible'); ?></p>
                    <p>Du <?php echo htmlspecialchars($stage['start_date'] ?? 'N/A'); ?> au <?php echo htmlspecialchars($stage['end_date'] ?? 'N/A'); ?></p>
                </div>
                <a href="candidatures.php?id=<?php echo urlencode($stage['id'] ?? ''); ?>" class="btn btn-primary mt-4">Voir les candidatures</a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="mes-offres-actions">
    <a class="btn btn-secondary mt-4" href="accueilEntreprise.php">Retour à l'accueil</a>
</div>

<?php require_once '../../includes/footer.php'; ?>