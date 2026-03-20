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

    $stageId = $_GET['id'] ?? null;
    $client = new Functions($_ENV['SUPABASE_URL'] ?? '', $_ENV['SUPABASE_KEY'] ?? '');
    
    // Fetch all candidatures and filter manually since getAllData doesn't filter
    $allCandidatures = $client->getAllData('candidatures') ?: [];
    $candidatures = [];
    if ($stageId) {
        foreach ($allCandidatures as $c) {
            if (($c['stage_id'] ?? null) == $stageId) {
                $candidatures[] = $c;
            }
        }
    }

    // Fetch all users to map student details
    $allUsers = $client->getAllData('users') ?: [];
    $usersMap = [];
    foreach ($allUsers as $u) {
        $usersMap[$u['id']] = $u;
    }
?>
<div class="card mes-offres-hero">
    <h2>Candidatures</h2>
    <p>Consultez les différents profils ayant postulé à votre offre.</p>
</div>

<?php if (empty($candidatures)): ?>
    <div class='card'><p>Aucune candidature n'est disponible pour ce stage.</p></div>
<?php else: ?>
    <div class="grid-container">
        <?php foreach ($candidatures as $candidature): ?>
            <?php 
                $studentId = $candidature['student_id'] ?? null;
                $student = $studentId ? ($usersMap[$studentId] ?? null) : null;
                $studentName = $student ? $student['username'] : 'Nom non disponible';
                $studentEmail = $student ? $student['email'] : 'Email non disponible';
                
                $cvUrl = !empty($candidature['cv_url']) ? rtrim($_ENV['SUPABASE_URL'], '/') . '/storage/v1/object/public/candidatures/' . $candidature['cv_url'] : null;
                $lmUrl = !empty($candidature['cover_letter_url']) ? rtrim($_ENV['SUPABASE_URL'], '/') . '/storage/v1/object/public/candidatures/' . $candidature['cover_letter_url'] : null;
            ?>
            <div class="card">
                <h3 style="color: var(--primary-color); margin-bottom: 0.5rem;">
                    <?php echo htmlspecialchars($studentName); ?>
                </h3>
                <p style="font-weight: 500; color: var(--text-primary); margin-bottom: 1rem;">
                    <?php echo htmlspecialchars($studentEmail); ?>
                </p>
                <p style="font-size: 0.875rem; margin-bottom: 1rem;">
                    <strong>Statut :</strong> <?php echo htmlspecialchars($candidature['status'] ?? 'En attente'); ?>
                </p>
                
                <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                    <?php if ($cvUrl): ?>
                        <a href="<?php echo htmlspecialchars($cvUrl); ?>" target="_blank" class="btn btn-primary" style="padding: 0.5rem 1rem;">Voir le CV</a>
                    <?php else: ?>
                        <span class="btn btn-secondary" style="padding: 0.5rem 1rem; opacity: 0.5;">CV non fourni</span>
                    <?php endif; ?>

                    <?php if ($lmUrl): ?>
                        <a href="<?php echo htmlspecialchars($lmUrl); ?>" target="_blank" class="btn btn-primary" style="padding: 0.5rem 1rem;">Voir la LM</a>
                    <?php else: ?>
                        <span class="btn btn-secondary" style="padding: 0.5rem 1rem; opacity: 0.5;">LM non fournie</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="mes-offres-actions">
    <a class="btn btn-secondary mt-4" href="mesOffres.php">Retour à mes offres</a>
</div>

<?php require_once '../../includes/footer.php'; ?>
