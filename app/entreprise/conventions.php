<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['type']) || $_SESSION['type'] !== 'entreprise') {
    header('Location: /login');
    exit;
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../supabaseQuery/restClient.php';
require_once __DIR__ . '/../../supabaseQuery/getSupabaseSignedUrl.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->safeLoad();

$apiKey = $_ENV['SUPABASE_KEY'] ?? '';
$baseUrl = rtrim($_ENV['SUPABASE_URL'] ?? '', '/') . '/rest/v1';
$supabaseUrl = $_ENV['SUPABASE_URL'] ?? '';
$companyId = (int) ($_SESSION['user_id'] ?? 0);
$companyName = (string) ($_SESSION['username'] ?? '');

function redirectToConventions(): void
{
    header('Location: conventions.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = (string) $_POST['action'];
    $conventionId = (int) ($_POST['convention_id'] ?? 0);

    if ($conventionId <= 0) {
        $_SESSION['error'] = 'Convention invalide.';
        redirectToConventions();
    }

    $conventionLookup = supabaseRestRequest(
        'GET',
        "$baseUrl/conventions?id=eq.$conventionId&select=*",
        $apiKey
    );
    $convention = is_array($conventionLookup['data']) && isset($conventionLookup['data'][0]) ? $conventionLookup['data'][0] : null;

    if (!$conventionLookup['ok'] || !$convention) {
        $_SESSION['error'] = 'Convention introuvable.';
        redirectToConventions();
    }

    $stageId = (int) ($convention['stage_id'] ?? 0);
    $stageLookup = supabaseRestRequest(
        'GET',
        "$baseUrl/stages?id=eq.$stageId&select=id,company,company_id,title",
        $apiKey
    );
    $stage = is_array($stageLookup['data']) && isset($stageLookup['data'][0]) ? $stageLookup['data'][0] : null;

    $matchesCompanyId = $companyId > 0 && (int) ($stage['company_id'] ?? 0) === $companyId;
    $matchesCompanyName = isset($stage['company']) && (string) $stage['company'] === $companyName;

    if (!$stage || (!$matchesCompanyId && !$matchesCompanyName)) {
        $_SESSION['error'] = 'Cette convention ne concerne pas vos offres.';
        redirectToConventions();
    }

    if ($action === 'validate') {
        $update = supabaseRestRequest(
            'PATCH',
            "$baseUrl/conventions?id=eq.$conventionId",
            $apiKey,
            ['company_validated' => true]
        );
        if (!$update['ok']) {
            $_SESSION['error'] = supabaseRestErrorMessage($update, 'Validation impossible.');
            redirectToConventions();
        }
        $_SESSION['result'] = 'Convention validée par l\'entreprise.';
        redirectToConventions();
    }

    if ($action === 'reject') {
        $update = supabaseRestRequest(
            'PATCH',
            "$baseUrl/conventions?id=eq.$conventionId",
            $apiKey,
            ['company_validated' => false]
        );
        if (!$update['ok']) {
            $_SESSION['error'] = supabaseRestErrorMessage($update, 'Refus impossible.');
            redirectToConventions();
        }
        $_SESSION['result'] = 'Validation entreprise retirée. L\'étudiant doit redéposer sa convention.';
        redirectToConventions();
    }
}

$stagesResult = supabaseRestRequest('GET', "$baseUrl/stages?select=*", $apiKey);
$mesStages = [];
if (is_array($stagesResult['data'])) {
    foreach ($stagesResult['data'] as $stage) {
        $matchesCompanyId = $companyId > 0 && (int) ($stage['company_id'] ?? 0) === $companyId;
        $matchesCompanyName = isset($stage['company']) && (string) $stage['company'] === $companyName;
        if ($matchesCompanyId || $matchesCompanyName) {
            $mesStages[(int) ($stage['id'] ?? 0)] = $stage;
        }
    }
}

$stageIds = array_keys($mesStages);
$conventions = [];
if (!empty($stageIds)) {
    $idList = implode(',', array_map('intval', $stageIds));
    $conventionsResult = supabaseRestRequest(
        'GET',
        "$baseUrl/conventions?stage_id=in.($idList)&select=*&order=created_at.desc",
        $apiKey
    );
    $conventions = is_array($conventionsResult['data']) ? $conventionsResult['data'] : [];
}

$documents = [];
if (!empty($stageIds)) {
    $idList = implode(',', array_map('intval', $stageIds));
    $documentsResult = supabaseRestRequest(
        'GET',
        "$baseUrl/documents?stage_id=in.($idList)&type=eq.convention&select=*&order=uploaded_at.desc",
        $apiKey
    );
    $documents = is_array($documentsResult['data']) ? $documentsResult['data'] : [];
}

$documentsByStudentStage = [];
foreach ($documents as $doc) {
    $key = (int) ($doc['stage_id'] ?? 0) . '_' . (int) ($doc['user_id'] ?? 0);
    $documentsByStudentStage[$key] = $doc;
}

$usersResult = supabaseRestRequest('GET', "$baseUrl/users?select=id,username,email", $apiKey);
$users = is_array($usersResult['data']) ? $usersResult['data'] : [];
$usersMap = [];
foreach ($users as $user) {
    $usersMap[(int) ($user['id'] ?? 0)] = $user;
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="card mes-offres-hero">
    <h2>Conventions de stage</h2>
    <p>Validez en ligne les conventions déposées par vos stagiaires.</p>
</div>

<?php if (isset($_SESSION['result'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['result']); ?></div>
    <?php unset($_SESSION['result']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); ?></div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<?php if (empty($conventions)): ?>
    <div class="card"><p>Aucune convention en cours pour vos offres.</p></div>
<?php else: ?>
    <div class="grid-container">
        <?php foreach ($conventions as $convention): ?>
            <?php
                $stageId = (int) ($convention['stage_id'] ?? 0);
                $studentId = (int) ($convention['student_id'] ?? 0);
                $stage = $mesStages[$stageId] ?? null;
                $student = $usersMap[$studentId] ?? null;
                $document = $documentsByStudentStage[$stageId . '_' . $studentId] ?? null;
                $documentUrl = $document ? getSupabaseSignedUrl($document['file_path'] ?? '', $supabaseUrl, $apiKey) : null;
                $companyValidated = !empty($convention['company_validated']);
                $tutorValidated = !empty($convention['tutor_validated']);
                $adminValidated = !empty($convention['admin_validated']);
            ?>
            <div class="card offre-card">
                <h3 class="offre-title"><?php echo htmlspecialchars($stage['title'] ?? 'Stage'); ?></h3>
                <p class="offre-company">Étudiant : <?php echo htmlspecialchars($student['username'] ?? 'inconnu'); ?> (<?php echo htmlspecialchars($student['email'] ?? ''); ?>)</p>

                <div class="offre-meta" style="margin-top: 0;">
                    <p><strong>Validation entreprise :</strong>
                        <span class="badge <?php echo $companyValidated ? 'badge-valid' : 'badge-pending'; ?>">
                            <?php echo $companyValidated ? 'validée' : 'en attente'; ?>
                        </span>
                    </p>
                    <p><strong>Validation tuteur :</strong>
                        <span class="badge <?php echo $tutorValidated ? 'badge-valid' : 'badge-pending'; ?>">
                            <?php echo $tutorValidated ? 'validée' : 'en attente'; ?>
                        </span>
                    </p>
                    <p><strong>Validation admin :</strong>
                        <span class="badge <?php echo $adminValidated ? 'badge-valid' : 'badge-pending'; ?>">
                            <?php echo $adminValidated ? 'validée' : 'en attente'; ?>
                        </span>
                    </p>
                </div>

                <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; margin-top: 1rem;">
                    <?php if ($documentUrl): ?>
                        <a href="<?php echo htmlspecialchars($documentUrl); ?>" target="_blank" class="btn btn-secondary" style="padding: 0.5rem 1rem;">Voir la convention</a>
                    <?php else: ?>
                        <span class="btn btn-secondary" style="padding: 0.5rem 1rem; opacity: 0.65;">Aucun fichier déposé</span>
                    <?php endif; ?>
                </div>

                <div style="margin-top: 1.25rem; display: flex; gap: 0.6rem; flex-wrap: wrap;">
                    <?php if (!$companyValidated): ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="validate">
                            <input type="hidden" name="convention_id" value="<?php echo (int) ($convention['id'] ?? 0); ?>">
                            <button type="submit" class="btn btn-primary" <?php echo $documentUrl ? '' : 'disabled style="opacity:0.6;cursor:not-allowed;"'; ?>>Valider la convention</button>
                        </form>
                    <?php else: ?>
                        <form method="POST" onsubmit="return confirm('Retirer la validation ? L\'étudiant devra redéposer une nouvelle version.');">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="convention_id" value="<?php echo (int) ($convention['id'] ?? 0); ?>">
                            <button type="submit" class="btn btn-secondary">Retirer la validation</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="mes-offres-actions">
    <a class="btn btn-secondary mt-4" href="accueilEntreprise.php">Retour à l'accueil</a>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
