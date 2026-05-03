<?php
// Fichier qui affiche les offres deposees par l entreprise.
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // On verifie que l utilisateur a le droit d acceder a cette page.
    if (!isset($_SESSION['type']) || $_SESSION['type'] !== 'entreprise') {
        header('Location: /login');
        exit;
    }

    // On charge les fichiers necessaires.
    require_once __DIR__ . '/../../vendor/autoload.php';
    require_once __DIR__ . '/../../supabaseQuery/restClient.php';

    // On importe les classes utilisees dans ce fichier.
    use Dotenv\Dotenv;

    $dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
    $dotenv->safeLoad();

    $apiKey = $_ENV['SUPABASE_KEY'] ?? '';
    $baseUrl = rtrim($_ENV['SUPABASE_URL'] ?? '', '/') . '/rest/v1';
    $companyId = (int) ($_SESSION['user_id'] ?? 0);
    $companyName = (string) ($_SESSION['username'] ?? '');

    // On appelle Supabase pour lire ou modifier les donnees.
    $stagesResult = supabaseRestRequest('GET', "$baseUrl/stages?select=*&order=created_at.desc", $apiKey);
    // On prepare les donnees utilisees dans ce bloc.
    $mesStages = [];

    // On verifie cette condition.
    if (is_array($stagesResult['data'])) {
        // On parcourt chaque element de la liste.
        foreach ($stagesResult['data'] as $stage) {
            $matchesCompanyId = $companyId > 0 && (int) ($stage['company_id'] ?? 0) === $companyId;
            $matchesCompanyName = isset($stage['company']) && (string) $stage['company'] === $companyName;

            // On verifie cette condition.
            if ($matchesCompanyId || $matchesCompanyName) {
                $mesStages[] = $stage;
            }
        }
    }

    // On charge les fichiers necessaires.
    require_once __DIR__ . '/../../includes/header.php';
?>

<div class="card mes-offres-hero">
    <h2>Mes offres de stage disponibles</h2>
    <p>Retrouvez ici toutes les offres publiées par votre entreprise.</p>
</div>

<?php // On controle cette condition avant de continuer. ?>
<?php if (!$stagesResult['ok']): ?>
    <div class="alert alert-error">
        <?php echo htmlspecialchars(supabaseRestErrorMessage($stagesResult, 'Impossible de charger vos offres.')); ?>
    </div>
<?php endif; ?>

<?php // On gere le cas ou la valeur attendue est vide. ?>
<?php if (empty($mesStages)): ?>
    <div class="card mes-offres-empty">
        <h3>Aucune offre publiée</h3>
        <p>Vous n'avez pas encore publié d'offre de stage.</p>
    </div>
<?php else: ?>
    <div class="grid-container">
        <?php // On parcourt chaque element de la liste. ?>
        <?php foreach ($mesStages as $stage): ?>
            <div class="card offre-card">
                <h3 class="offre-title"><?php echo htmlspecialchars($stage['title'] ?? 'Titre non disponible'); ?></h3>
                <p class="offre-company"><?php echo htmlspecialchars($stage['company'] ?? 'Entreprise non disponible'); ?></p>
                <p class="offre-desc"><?php echo nl2br(htmlspecialchars($stage['description'] ?? 'Description non disponible')); ?></p>

                <div class="offre-meta">
                    <p><strong>Filière :</strong> <?php echo htmlspecialchars($stage['filiere'] ?? 'Non renseignée'); ?></p>
                    <p><?php echo htmlspecialchars($stage['location'] ?? 'Lieu non disponible'); ?></p>
                    <p>Du <?php echo htmlspecialchars($stage['start_date'] ?? 'N/A'); ?> au <?php echo htmlspecialchars($stage['end_date'] ?? 'N/A'); ?></p>
                    <p><strong>Durée :</strong> <?php echo htmlspecialchars((string) ($stage['duration_weeks'] ?? 'N/A')); ?> semaine(s)</p>
                    <p><strong>Statut :</strong> <?php echo htmlspecialchars($stage['status'] ?? 'ouverte'); ?></p>
                </div>
                <a href="candidatures.php?id=<?php echo urlencode($stage['id'] ?? ''); ?>" class="btn btn-primary mt-4">Voir les candidatures</a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="mes-offres-actions">
    <a class="btn btn-secondary mt-4" href="accueilEntreprise.php">Retour à l'accueil</a>
</div>

<?php // On charge les fichiers necessaires. ?>
<?php require_once '../../includes/footer.php'; ?>
