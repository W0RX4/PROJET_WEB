<?php
require_once '../../includes/header.php';

if ($_SESSION['type'] !== 'etudiant') {
    header('Location: ../../connection/login.php');
    exit;
}

require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;
use Supabase\Client\Functions;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->safeLoad();

$client = new Functions($_ENV['SUPABASE_URL'] ?? '', $_ENV['SUPABASE_KEY'] ?? '');
$stageId = (int) ($_GET['stage_id'] ?? 0);

$stageRows = $stageId > 0 ? ($client->filter('stages', $stageId) ?: []) : [];
$stage = $stageRows[0] ?? null;

if (!$stage) {
    echo "<div class='card'><p>Offre de stage introuvable.</p></div>";
    require_once '../../includes/footer.php';
    exit;
}

$allMissions = $client->getAllData('missions') ?: [];
$stageMissions = [];

foreach ($allMissions as $mission) {
    if ((int) ($mission['stage_id'] ?? 0) === $stageId) {
        $stageMissions[] = $mission;
    }
}
?>

<div class="card mes-offres-hero">
    <h2><?php echo htmlspecialchars($stage['title'] ?? 'Offre de stage'); ?></h2>
    <p><?php echo nl2br(htmlspecialchars($stage['description'] ?? '')); ?></p>
</div>

<div class="card">
    <h3>Récapitulatif de l’offre</h3>
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
        <?php if (empty($stageMissions)): ?>
            <p style="color: var(--text-secondary);">Aucune mission détaillée n'a encore été renseignée pour cette offre.</p>
        <?php else: ?>
            <ul style="padding-left: 1.2rem; color: var(--text-secondary);">
                <?php foreach ($stageMissions as $mission): ?>
                    <li style="margin-bottom: 0.45rem;">
                        <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($mission['title'] ?? 'Mission'); ?></strong>
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
    <form action="enregistrerCandidature.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="stage_id" value="<?php echo $stageId; ?>">

        <div class="form-group">
            <label for="student_name" class="form-label">Votre nom</label>
            <input type="text" id="student_name" name="student_name" class="form-control" required>
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
</div>

<?php require_once '../../includes/footer.php'; ?>
