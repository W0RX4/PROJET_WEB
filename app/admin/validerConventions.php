<?php
// Fichier qui permet a l administrateur de valider les conventions.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// On verifie que l utilisateur a le droit d acceder a cette page.
if (!isset($_SESSION['type']) || $_SESSION['type'] !== 'admin') {
    header('Location: /login');
    exit;
}

// On charge les fichiers necessaires.
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../supabaseQuery/restClient.php';
require_once __DIR__ . '/../../supabaseQuery/getSupabaseSignedUrl.php';

// On importe les classes utilisees dans ce fichier.
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->safeLoad();

$apiKey = $_ENV['SUPABASE_KEY'] ?? '';
$supabaseUrl = $_ENV['SUPABASE_URL'] ?? '';
$baseUrl = rtrim($supabaseUrl, '/') . '/rest/v1';

// Cette fonction renvoie vers la validation des conventions.
function redirectAdminConventions(): void
{
    header('Location: validerConventions.php');
    exit;
}

// On traite les donnees envoyees par le formulaire.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = (string) $_POST['action'];
    $conventionId = (int) ($_POST['convention_id'] ?? 0);

    // On verifie cette condition.
    if ($conventionId <= 0) {
        $_SESSION['error'] = 'Convention invalide.';
        redirectAdminConventions();
    }

    // On execute l action demandee par le formulaire.
    if ($action === 'validate_admin') {
        // On appelle Supabase pour lire ou modifier les donnees.
        $update = supabaseRestRequest(
            'PATCH',
            "$baseUrl/conventions?id=eq.$conventionId",
            $apiKey,
            ['admin_validated' => true]
        );
        // On controle cette condition avant de continuer.
        if (!$update['ok']) {
            $_SESSION['error'] = supabaseRestErrorMessage($update, 'Validation impossible.');
            redirectAdminConventions();
        }
        $_SESSION['result'] = 'Convention validée par l\'administration.';
        redirectAdminConventions();
    }

    // On execute l action demandee par le formulaire.
    if ($action === 'unvalidate_admin') {
        // On appelle Supabase pour lire ou modifier les donnees.
        $update = supabaseRestRequest(
            'PATCH',
            "$baseUrl/conventions?id=eq.$conventionId",
            $apiKey,
            ['admin_validated' => false]
        );
        // On controle cette condition avant de continuer.
        if (!$update['ok']) {
            $_SESSION['error'] = supabaseRestErrorMessage($update, 'Mise à jour impossible.');
            redirectAdminConventions();
        }
        $_SESSION['result'] = 'Validation administrative retirée.';
        redirectAdminConventions();
    }
}

// On appelle Supabase pour lire ou modifier les donnees.
$conventionsResult = supabaseRestRequest('GET', "$baseUrl/conventions?select=*&order=created_at.desc", $apiKey);
$conventions = is_array($conventionsResult['data']) ? $conventionsResult['data'] : [];

// On appelle Supabase pour lire ou modifier les donnees.
$stagesResult = supabaseRestRequest('GET', "$baseUrl/stages?select=id,title,company,filiere,start_date,end_date", $apiKey);
// On prepare les donnees utilisees dans ce bloc.
$stagesMap = [];
// On parcourt chaque element de la liste.
foreach ((is_array($stagesResult['data']) ? $stagesResult['data'] : []) as $stage) {
    $stagesMap[(int) ($stage['id'] ?? 0)] = $stage;
}

// On appelle Supabase pour lire ou modifier les donnees.
$usersResult = supabaseRestRequest('GET', "$baseUrl/users?select=id,username,email,type", $apiKey);
// On prepare les donnees utilisees dans ce bloc.
$usersMap = [];
// On parcourt chaque element de la liste.
foreach ((is_array($usersResult['data']) ? $usersResult['data'] : []) as $user) {
    $usersMap[(int) ($user['id'] ?? 0)] = $user;
}

// On appelle Supabase pour lire ou modifier les donnees.
$documentsResult = supabaseRestRequest('GET', "$baseUrl/documents?type=eq.convention&select=*&order=uploaded_at.desc", $apiKey);
// On prepare les donnees utilisees dans ce bloc.
$documentsByKey = [];
// On parcourt chaque element de la liste.
foreach ((is_array($documentsResult['data']) ? $documentsResult['data'] : []) as $doc) {
    $key = (int) ($doc['stage_id'] ?? 0) . '_' . (int) ($doc['user_id'] ?? 0);
    $documentsByKey[$key] = $doc;
}

// On recupere et nettoie une valeur envoyee par l utilisateur.
$filterStatus = trim((string) ($_GET['status'] ?? 'pending'));

// On charge les fichiers necessaires.
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="card mes-offres-hero">
    <h2>Validation administrative des conventions</h2>
    <p>Vérifiez les conventions signées par l'entreprise et le tuteur, puis apportez la validation finale.</p>
</div>

<?php // On affiche le message de confirmation si besoin. ?>
<?php if (isset($_SESSION['result'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['result']); ?></div>
    <?php unset($_SESSION['result']); ?>
<?php endif; ?>

<?php // On affiche le message d erreur si besoin. ?>
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
// On prepare les donnees utilisees dans ce bloc.
$filtered = [];
// On parcourt chaque element de la liste.
foreach ($conventions as $convention) {
    $adminValidated = !empty($convention['admin_validated']);
    // On verifie cette condition.
    if ($filterStatus === 'pending' && $adminValidated) {
        continue;
    }
    // On verifie cette condition.
    if ($filterStatus === 'validated' && !$adminValidated) {
        continue;
    }
    $filtered[] = $convention;
}
?>

<?php // On gere le cas ou la valeur attendue est vide. ?>
<?php if (empty($filtered)): ?>
    <div class="card"><p>Aucune convention ne correspond à ce filtre.</p></div>
<?php else: ?>
    <div class="grid-container">
        <?php // On parcourt chaque element de la liste. ?>
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
                    <?php // On controle cette condition avant de continuer. ?>
                    <?php if ($documentUrl): ?>
                        <a href="<?php echo htmlspecialchars($documentUrl); ?>" target="_blank" class="btn btn-secondary" style="padding: 0.5rem 1rem;">Voir la convention</a>
                    <?php else: ?>
                        <span class="btn btn-secondary" style="padding: 0.5rem 1rem; opacity: 0.6;">Aucun fichier déposé</span>
                    <?php endif; ?>
                </div>

                <div style="margin-top: 1.25rem; display: flex; gap: 0.6rem; flex-wrap: wrap; align-items: center;">
                    <?php // On controle cette condition avant de continuer. ?>
                    <?php if (!$adminValidated): ?>
                        <?php $canValidate = $doc !== null && $companyValidated; ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="validate_admin">
                            <input type="hidden" name="convention_id" value="<?php echo (int) ($convention['id'] ?? 0); ?>">
                            <button type="submit" class="btn btn-primary" <?php echo $canValidate ? '' : 'disabled style="opacity:0.6;cursor:not-allowed;"'; ?>>Valider en tant qu'admin</button>
                        </form>
                        <?php // On controle cette condition avant de continuer. ?>
                        <?php if (!$doc): ?>
                            <span style="color: var(--text-secondary); font-size: 0.85rem;">L'étudiant doit d'abord déposer la convention.</span>
                        <?php elseif (!$companyValidated): ?>
                            <span style="color: var(--text-secondary); font-size: 0.85rem;">L'entreprise doit d'abord valider.</span>
                        <?php endif; ?>
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

<?php // On charge les fichiers necessaires. ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
