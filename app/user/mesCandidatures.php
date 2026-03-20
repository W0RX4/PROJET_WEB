<?php
    require_once '../../includes/header.php';
    require_once __DIR__ . '/../../vendor/autoload.php';

    use Dotenv\Dotenv;
    use Supabase\Client\Functions;

    $dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
    $dotenv->safeLoad();

    if($_SESSION['type'] !== 'etudiant'){
        header('Location: ../../connection/login.php');
        exit;
    }

    $client = new Functions($_ENV['SUPABASE_URL'] ?? '', $_ENV['SUPABASE_KEY'] ?? '');
    $userId = $_SESSION['user_id'] ?? null;
    
    // Fetch all candidatures and filter manually
    $allCandidatures = $client->getAllData('candidatures') ?: [];
    $mesCandidatures = [];
    if ($userId) {
        foreach ($allCandidatures as $c) {
            if (($c['student_id'] ?? null) == $userId) {
                $mesCandidatures[] = $c;
            }
        }
    }

    // Fetch all stages to map stage_id to title and company
    $allStages = $client->getAllData('stages') ?: [];
    $stagesMap = [];
    foreach ($allStages as $s) {
        $stagesMap[$s['id']] = $s;
    }
?>

<div class="card mes-offres-hero">
    <h2>Mes candidatures</h2>
    <p>Suivez l'état d'avancement de vos différentes requêtes.</p>
</div>

<?php if (empty($mesCandidatures)): ?>
    <div class='card'><p>Vous n'avez pas encore postulé à un stage.</p></div>
<?php else: ?>
    <div class="grid-container">
        <?php foreach ($mesCandidatures as $candidature): ?>
            <?php 
                $stageId = $candidature['stage_id'] ?? null;
                $stage = $stageId ? ($stagesMap[$stageId] ?? null) : null;
                $stageTitle = $stage ? $stage['title'] : 'Stage inconnu';
                $stageCompany = $stage ? $stage['company'] : 'Entreprise inconnue';
                
                $cvUrl = !empty($candidature['cv_url']) ? rtrim($_ENV['SUPABASE_URL'], '/') . '/storage/v1/object/public/candidatures/' . $candidature['cv_url'] : null;
                $lmUrl = !empty($candidature['cover_letter_url']) ? rtrim($_ENV['SUPABASE_URL'], '/') . '/storage/v1/object/public/candidatures/' . $candidature['cover_letter_url'] : null;
            ?>
            <div class="card">
                <h3 style="color: var(--primary-color); margin-bottom: 0.5rem;">
                    <?php echo htmlspecialchars($stageTitle); ?>
                </h3>
                <p style="font-weight: 500; color: var(--text-primary); margin-bottom: 1rem;">
                    🏢 <?php echo htmlspecialchars($stageCompany); ?>
                </p>
                <p style="font-size: 0.875rem; margin-bottom: 1rem;">
                    <strong>Statut :</strong> <?php echo htmlspecialchars($candidature['status'] ?? 'En attente'); ?>
                </p>

                <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                    <?php if ($cvUrl): ?>
                        <a href="<?php echo htmlspecialchars($cvUrl); ?>" target="_blank" class="btn btn-secondary" style="padding: 0.5rem 1rem;">Mon CV</a>
                    <?php endif; ?>

                    <?php if ($lmUrl): ?>
                        <a href="<?php echo htmlspecialchars($lmUrl); ?>" target="_blank" class="btn btn-secondary" style="padding: 0.5rem 1rem;">Ma lettre</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="mes-offres-actions">
    <a class="btn btn-secondary mt-4" href="accueilUser.php">Retour à l'accueil</a>
</div>

<?php require_once '../../includes/footer.php'; ?>
