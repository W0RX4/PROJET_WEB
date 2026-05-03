<?php
// Fichier qui affiche le detail d une offre avant candidature.
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../supabaseQuery/restClient.php';
require_once __DIR__ . '/../../includes/trace.php';

// On demarre la session si elle n existe pas encore.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// On verifie que l utilisateur a le droit d acceder a cette page.
if (!isset($_SESSION['type']) || $_SESSION['type'] !== 'etudiant') {
    header('Location: /login');
    exit;
}

// On importe les classes utilisees dans ce fichier.
use Dotenv\Dotenv;

// On verifie cette condition.
if (!isset($_ENV['SUPABASE_URL'])) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
    $dotenv->safeLoad();
}

$apiKey = (string) ($_ENV['SUPABASE_KEY'] ?? '');
$baseUrl = rtrim((string) ($_ENV['SUPABASE_URL'] ?? ''), '/') . '/rest/v1';
$stageId = (int) ($_GET['stage_id'] ?? 0);

stageArchiveLogPageAccess('/app/user/postuler.php?stage_id=' . $stageId);

$stage = null;
// On verifie cette condition.
if ($stageId > 0) {
    // On appelle Supabase pour lire ou modifier les donnees.
    $stageResult = supabaseRestRequest('GET', "$baseUrl/stages?id=eq.$stageId&select=*&limit=1", $apiKey);
    $stage = is_array($stageResult['data']) && isset($stageResult['data'][0]) ? $stageResult['data'][0] : null;
}

// On prepare les donnees utilisees dans ce bloc.
$stageMissions = [];
// On controle cette condition avant de continuer.
if ($stage) {
    // On appelle Supabase pour lire ou modifier les donnees.
    $missionsResult = supabaseRestRequest(
        'GET',
        "$baseUrl/missions?stage_id=eq.$stageId&select=*&order=created_at.asc",
        $apiKey
    );
    $stageMissions = is_array($missionsResult['data']) ? $missionsResult['data'] : [];
}

$alreadyApplied = false;
// On controle cette condition avant de continuer.
if ($stage) {
    $studentId = (int) ($_SESSION['user_id'] ?? 0);
    // On appelle Supabase pour lire ou modifier les donnees.
    $existingResult = supabaseRestRequest(
        'GET',
        "$baseUrl/candidatures?stage_id=eq.$stageId&student_id=eq.$studentId&select=id&limit=1",
        $apiKey
    );
    $alreadyApplied = is_array($existingResult['data']) && !empty($existingResult['data']);
}

// On charge les fichiers necessaires.
require_once __DIR__ . '/../../includes/header.php';
?>

<?php // On affiche le message d erreur si besoin. ?>
<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
<?php endif; ?>

<?php // On controle cette condition avant de continuer. ?>
<?php if (!$stage): ?>
    <div class="card"><p>Offre de stage introuvable.</p></div>
    <a href="accueilUser.php" class="btn btn-secondary">Retour aux offres</a>
<?php else: ?>
    <div class="card mes-offres-hero">
        <h2><?php echo htmlspecialchars($stage['title'] ?? 'Offre de stage'); ?></h2>
        <p><?php echo nl2br(htmlspecialchars($stage['description'] ?? '')); ?></p>
    </div>

    <div class="card">
        <h3>Récapitulatif de l'offre</h3>
        <div class="grid-container" style="margin-top: 1rem;">
            <div>
                <p><strong>Entreprise :</strong> <?php echo htmlspecialchars($stage['company'] ?? 'Entreprise non disponible'); ?></p>
                <p><strong>Lieu :</strong> <?php echo htmlspecialchars($stage['location'] ?? 'Lieu non disponible'); ?></p>
            </div>
            <div>
                <p><strong>Filière :</strong> <?php echo htmlspecialchars($stage['filiere'] ?? 'Non renseignée'); ?></p>
                <p><strong>Période :</strong> du <?php echo htmlspecialchars($stage['start_date'] ?? 'N/A'); ?> au <?php echo htmlspecialchars($stage['end_date'] ?? 'N/A'); ?></p>
            </div>
        </div>

        <div style="margin-top: 1.25rem;">
            <p style="font-weight: 600; margin-bottom: 0.65rem;">Missions prévues</p>
            <?php // On gere le cas ou la valeur attendue est vide. ?>
            <?php if (empty($stageMissions)): ?>
                <p style="color: var(--text-secondary);">Aucune mission détaillée n'a encore été renseignée pour cette offre.</p>
            <?php else: ?>
                <ul style="padding-left: 1.2rem; color: var(--text-secondary);">
                    <?php // On parcourt chaque element de la liste. ?>
                    <?php foreach ($stageMissions as $mission): ?>
                        <li style="margin-bottom: 0.45rem;">
                            <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($mission['title'] ?? 'Mission'); ?></strong>
                            <?php // On verifie cette condition. ?>
                            <?php if (!empty($mission['description'])): ?>
                                : <?php echo htmlspecialchars($mission['description']); ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <h3>Postuler à cette offre</h3>

        <?php // On controle cette condition avant de continuer. ?>
        <?php if ($alreadyApplied): ?>
            <div class="alert alert-success">Vous avez déjà postulé à cette offre.</div>
            <a href="mesCandidatures.php" class="btn btn-primary">Voir mes candidatures</a>
        <?php else: ?>
            <form action="enregistrerCandidature.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="stage_id" value="<?php echo $stageId; ?>">

                <div class="form-group">
                    <label for="student_name" class="form-label">Votre nom</label>
                    <input type="text" id="student_name" name="student_name" class="form-control" value="<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="CV" class="form-label">Votre CV (PDF)</label>
                    <input type="file" id="CV" name="CV" class="form-control" accept=".pdf" required>
                </div>

                <div class="form-group">
                    <label for="cover_letter" class="form-label">Votre lettre de motivation (PDF)</label>
                    <input type="file" id="cover_letter" name="cover_letter" class="form-control" accept=".pdf" required>
                </div>

                <button type="submit" class="btn btn-primary mt-4">Soumettre ma candidature</button>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php // On charge les fichiers necessaires. ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
