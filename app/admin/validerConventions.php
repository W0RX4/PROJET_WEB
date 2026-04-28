<?php
require_once __DIR__ . '/../../includes/header.php';

if ($_SESSION['type'] !== 'admin') {
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
$supabaseUrl = $_ENV['SUPABASE_URL'] ?? '';
$baseUrl = rtrim($supabaseUrl, '/') . '/rest/v1';

function redirectAdminConventions(): void
{
    header('Location: validerConventions.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = (string) $_POST['action'];
    $conventionId = (int) ($_POST['convention_id'] ?? 0);

    if ($conventionId <= 0) {
        $_SESSION['error'] = 'Convention invalide.';
        redirectAdminConventions();
    }

    if ($action === 'validate_admin') {
        $update = supabaseRestRequest(
            'PATCH',
            "$baseUrl/conventions?id=eq.$conventionId",
            $apiKey,
            ['admin_validated' => true]
        );
        if (!$update['ok']) {
            $_SESSION['error'] = supabaseRestErrorMessage($update, 'Validation impossible.');
            redirectAdminConventions();
        }
        $_SESSION['result'] = 'Convention validée par l\'administration.';
        redirectAdminConventions();
    }

    if ($action === 'unvalidate_admin') {
        $update = supabaseRestRequest(
            'PATCH',
            "$baseUrl/conventions?id=eq.$conventionId",
            $apiKey,
            ['admin_validated' => false]
        );
        if (!$update['ok']) {
            $_SESSION['error'] = supabaseRestErrorMessage($update, 'Mise à jour impossible.');
            redirectAdminConventions();
        }
        $_SESSION['result'] = 'Validation administrative retirée.';
        redirectAdminConventions();
    }
}

$conventionsResult = supabaseRestRequest('GET', "$baseUrl/conventions?select=*&order=created_at.desc", $apiKey);
$conventions = is_array($conventionsResult['data']) ? $conventionsResult['data'] : [];

$stagesResult = supabaseRestRequest('GET', "$baseUrl/stages?select=id,title,company,filiere,start_date,end_date", $apiKey);
$stagesMap = [];
foreach ((is_array($stagesResult['data']) ? $stagesResult['data'] : []) as $stage) {
    $stagesMap[(int) ($stage['id'] ?? 0)] = $stage;
}

$usersResult = supabaseRestRequest('GET', "$baseUrl/users?select=id,username,email,type", $apiKey);
$usersMap = [];
foreach ((is_array($usersResult['data']) ? $usersResult['data'] : []) as $user) {
    $usersMap[(int) ($user['id'] ?? 0)] = $user;
}

$documentsResult = supabaseRestRequest('GET', "$baseUrl/documents?type=eq.convention&select=*&order=uploaded_at.desc", $apiKey);
$documentsByKey = [];
foreach ((is_array($documentsResult['data']) ? $documentsResult['data'] : []) as $doc) {
    $key = (int) ($doc['stage_id'] ?? 0) . '_' . (int) ($doc['user_id'] ?? 0);
    $documentsByKey[$key] = $doc;
}

$filterStatus = trim((string) ($_GET['status'] ?? 'pending'));
?>

<div class="card mes-offres-hero">
    <h2>Validation administrative des conventions</h2>
    <p>Vérifiez les conventions signées par l'entreprise et le tuteur, puis apportez la validation finale.</p>
</div>

<?php if (isset($_SESSION['result'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['result']); ?></div>
    <?php unset($_SESSION['result']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); ?></div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<div class="card">
    <form method="GET" style="display: flex; gap: 0.6rem; align-items: flex-end; flex-wrap: wrap;">
        <div class="form-group" style="flex: 1; min-width: 220px;">
            <label class="form-label" for="status">Filtrer par état</label>
            <select class="form-control" id="status" name="status">
                <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>Toutes</option>
                <option value="pending" <?php echo $filterStatus === 'pending' ? 'selected' : ''; ?>>En attente de validation admin</option>
                <option value="validated" <?php echo $filterStatus === 'validated' ? 'selected' : ''; ?>>Validées par l'admin</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Filtrer</button>
    </form>
</div>

<?php
$filtered = [];
foreach ($conventions as $convention) {
    $adminValidated = !empty($convention['admin_validated']);
    if ($filterStatus === 'pending' && $adminValidated) {
        continue;
    }
    if ($filterStatus === 'validated' && !$adminValidated) {
        continue;
    }
    $filtered[] = $convention;
}
?>

<?php if (empty($filtered)): ?>
    <div class="card"><p>Aucune convention ne correspond à ce filtre.</p></div>
<?php else: ?>
    <div class="grid-container">
        <?php foreach ($filtered as $convention): ?>
            <?php
                $stageId = (int) ($convention['stage_id'] ?? 0);
                $studentId = (int) ($convention['student_id'] ?? 0);
                $stage = $stagesMap[$stageId] ?? null;
                $student = $usersMap[$studentId] ?? null;
                $doc = $documentsByKey[$stageId . '_' . $studentId] ?? null;
                $documentUrl = $doc ? getSupabaseSignedUrl($doc['file_path'] ?? '', $supabaseUrl, $apiKey) : null;
                $companyValidated = !empty($convention['company_validated']);
                $tutorValidated = !empty($convention['tutor_validated']);
                $adminValidated = !empty($convention['admin_validated']);
            ?>
            <div class="card offre-card">
                <h3 class="offre-title"><?php echo htmlspecialchars($stage['title'] ?? 'Stage'); ?></h3>
                <p class="offre-company"><?php echo htmlspecialchars($stage['company'] ?? ''); ?></p>
                <div class="offre-meta" style="margin-top: 0;">
                    <p><strong>Étudiant :</strong> <?php echo htmlspecialchars($student['username'] ?? '—'); ?> (<?php echo htmlspecialchars($student['email'] ?? ''); ?>)</p>
                    <p><strong>Filière :</strong> <?php echo htmlspecialchars($stage['filiere'] ?? '—'); ?></p>
                    <p><strong>Période :</strong> <?php echo htmlspecialchars($stage['start_date'] ?? ''); ?> → <?php echo htmlspecialchars($stage['end_date'] ?? ''); ?></p>
                    <p><strong>Entreprise :</strong> <span class="badge <?php echo $companyValidated ? 'badge-valid' : 'badge-pending'; ?>"><?php echo $companyValidated ? 'validée' : 'en attente'; ?></span></p>
                    <p><strong>Tuteur :</strong> <span class="badge <?php echo $tutorValidated ? 'badge-valid' : 'badge-pending'; ?>"><?php echo $tutorValidated ? 'validée' : 'en attente'; ?></span></p>
                    <p><strong>Admin :</strong> <span class="badge <?php echo $adminValidated ? 'badge-valid' : 'badge-pending'; ?>"><?php echo $adminValidated ? 'validée' : 'en attente'; ?></span></p>
                </div>

                <div style="display: flex; gap: 0.6rem; flex-wrap: wrap; margin-top: 1rem;">
                    <?php if ($documentUrl): ?>
                        <a href="<?php echo htmlspecialchars($documentUrl); ?>" target="_blank" class="btn btn-secondary" style="padding: 0.5rem 1rem;">Voir la convention</a>
                    <?php else: ?>
                        <span class="btn btn-secondary" style="padding: 0.5rem 1rem; opacity: 0.6;">Aucun fichier déposé</span>
                    <?php endif; ?>
                </div>

                <div style="margin-top: 1.25rem; display: flex; gap: 0.6rem; flex-wrap: wrap;">
                    <?php if (!$adminValidated): ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="validate_admin">
                            <input type="hidden" name="convention_id" value="<?php echo (int) ($convention['id'] ?? 0); ?>">
                            <button type="submit" class="btn btn-primary" <?php echo $documentUrl && $companyValidated ? '' : 'disabled style="opacity:0.6;cursor:not-allowed;"'; ?>>Valider en tant qu'admin</button>
                        </form>
                    <?php else: ?>
                        <form method="POST" onsubmit="return confirm('Retirer la validation administrative ?');">
                            <input type="hidden" name="action" value="unvalidate_admin">
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
    <a class="btn btn-secondary mt-4" href="accueilAdmin.php">Retour à l'accueil</a>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
