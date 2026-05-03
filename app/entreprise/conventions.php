<?php
// Fichier qui permet a une entreprise de valider les conventions.
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
require_once __DIR__ . '/../../supabaseQuery/getSupabaseSignedUrl.php';

// On importe les classes utilisees dans ce fichier.
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->safeLoad();

$apiKey = $_ENV['SUPABASE_KEY'] ?? '';
$baseUrl = rtrim($_ENV['SUPABASE_URL'] ?? '', '/') . '/rest/v1';
$supabaseUrl = $_ENV['SUPABASE_URL'] ?? '';
$companyId = (int) ($_SESSION['user_id'] ?? 0);
$companyName = (string) ($_SESSION['username'] ?? '');

// Cette fonction regroupe une action reutilisable.
function redirectToConventions(): void
{
    header('Location: conventions.php');
    exit;
}

// On traite les donnees envoyees par le formulaire.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = (string) $_POST['action'];
    $conventionId = (int) ($_POST['convention_id'] ?? 0);

    // On verifie cette condition.
    if ($conventionId <= 0) {
        $_SESSION['error'] = 'Convention invalide.';
        redirectToConventions();
    }

    // On appelle Supabase pour lire ou modifier les donnees.
    $conventionLookup = supabaseRestRequest(
        'GET',
        "$baseUrl/conventions?id=eq.$conventionId&select=*",
        $apiKey
    );
    $convention = is_array($conventionLookup['data']) && isset($conventionLookup['data'][0]) ? $conventionLookup['data'][0] : null;

    // On controle cette condition avant de continuer.
    if (!$conventionLookup['ok'] || !$convention) {
        $_SESSION['error'] = 'Convention introuvable.';
        redirectToConventions();
    }

    $stageId = (int) ($convention['stage_id'] ?? 0);
    // On appelle Supabase pour lire ou modifier les donnees.
    $stageLookup = supabaseRestRequest(
        'GET',
        "$baseUrl/stages?id=eq.$stageId&select=id,company,company_id,title",
        $apiKey
    );
    $stage = is_array($stageLookup['data']) && isset($stageLookup['data'][0]) ? $stageLookup['data'][0] : null;

    $matchesCompanyId = $companyId > 0 && (int) ($stage['company_id'] ?? 0) === $companyId;
    $matchesCompanyName = isset($stage['company']) && (string) $stage['company'] === $companyName;

    // On controle cette condition avant de continuer.
    if (!$stage || (!$matchesCompanyId && !$matchesCompanyName)) {
        $_SESSION['error'] = 'Cette convention ne concerne pas vos offres.';
        redirectToConventions();
    }

    // On execute l action demandee par le formulaire.
    if ($action === 'validate') {
        // On appelle Supabase pour lire ou modifier les donnees.
        $update = supabaseRestRequest(
            'PATCH',
            "$baseUrl/conventions?id=eq.$conventionId",
            $apiKey,
            ['company_validated' => true]
        );
        // On controle cette condition avant de continuer.
        if (!$update['ok']) {
            $_SESSION['error'] = supabaseRestErrorMessage($update, 'Validation impossible.');
            redirectToConventions();
        }
        $_SESSION['result'] = 'Convention validée par l\'entreprise.';
        redirectToConventions();
    }

}

// On appelle Supabase pour lire ou modifier les donnees.
$stagesResult = supabaseRestRequest('GET', "$baseUrl/stages?select=*", $apiKey);
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
            $mesStages[(int) ($stage['id'] ?? 0)] = $stage;
        }
    }
}

$stageIds = array_keys($mesStages);
// On prepare les donnees utilisees dans ce bloc.
$conventions = [];
// On verifie cette condition.
if (!empty($stageIds)) {
    $idList = implode(',', array_map('intval', $stageIds));
    // On appelle Supabase pour lire ou modifier les donnees.
    $conventionsResult = supabaseRestRequest(
        'GET',
        "$baseUrl/conventions?stage_id=in.($idList)&select=*&order=created_at.desc",
        $apiKey
    );
    $conventions = is_array($conventionsResult['data']) ? $conventionsResult['data'] : [];
}

// On prepare les donnees utilisees dans ce bloc.
$documents = [];
// On verifie cette condition.
if (!empty($stageIds)) {
    $idList = implode(',', array_map('intval', $stageIds));
    // On appelle Supabase pour lire ou modifier les donnees.
    $documentsResult = supabaseRestRequest(
        'GET',
        "$baseUrl/documents?stage_id=in.($idList)&type=eq.convention&select=*&order=uploaded_at.desc",
        $apiKey
    );
    $documents = is_array($documentsResult['data']) ? $documentsResult['data'] : [];
}

// On prepare les donnees utilisees dans ce bloc.
$documentsByStudentStage = [];
// On parcourt chaque element de la liste.
foreach ($documents as $doc) {
    $key = (int) ($doc['stage_id'] ?? 0) . '_' . (int) ($doc['user_id'] ?? 0);
    $documentsByStudentStage[$key] = $doc;
}

// On appelle Supabase pour lire ou modifier les donnees.
$usersResult = supabaseRestRequest('GET', "$baseUrl/users?select=id,username,email", $apiKey);
$users = is_array($usersResult['data']) ? $usersResult['data'] : [];
// On prepare les donnees utilisees dans ce bloc.
$usersMap = [];
// On parcourt chaque element de la liste.
foreach ($users as $user) {
    $usersMap[(int) ($user['id'] ?? 0)] = $user;
}

// On charge les fichiers necessaires.
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="card mes-offres-hero">
    <h2>Conventions de stage</h2>
    <p>Validez en ligne les conventions déposées par vos stagiaires.</p>
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

<?php // On gere le cas ou la valeur attendue est vide. ?>
<?php if (empty($conventions)): ?>
    <div class="card"><p>Aucune convention en cours pour vos offres.</p></div>
<?php else: ?>
    <div class="grid-container">
        <?php // On parcourt chaque element de la liste. ?>
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
                    <?php // On controle cette condition avant de continuer. ?>
                    <?php if ($documentUrl): ?>
                        <a href="<?php echo htmlspecialchars($documentUrl); ?>" target="_blank" class="btn btn-secondary" style="padding: 0.5rem 1rem;">Voir la convention</a>
                    <?php else: ?>
                        <span class="btn btn-secondary" style="padding: 0.5rem 1rem; opacity: 0.65;">Aucun fichier déposé</span>
                    <?php endif; ?>
                </div>

                <div style="margin-top: 1.25rem; display: flex; gap: 0.6rem; flex-wrap: wrap; align-items: center;">
                    <?php // On controle cette condition avant de continuer. ?>
                    <?php if (!$companyValidated): ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="validate">
                            <input type="hidden" name="convention_id" value="<?php echo (int) ($convention['id'] ?? 0); ?>">
                            <button type="submit" class="btn btn-primary" <?php echo $document ? '' : 'disabled style="opacity:0.6;cursor:not-allowed;"'; ?>>Valider la convention</button>
                        </form>
                        <?php // On controle cette condition avant de continuer. ?>
                        <?php if (!$document): ?>
                            <span style="color: var(--text-secondary); font-size: 0.85rem;">L'étudiant doit d'abord déposer la convention.</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <p style="color: var(--text-secondary); font-size: 0.875rem; margin: 0;">
                            Convention validée par votre entreprise. La décision est définitive ; le tuteur et l'administration prennent maintenant le relais.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="mes-offres-actions">
    <a class="btn btn-secondary mt-4" href="accueilEntreprise.php">Retour à l'accueil</a>
</div>

<?php // On charge les fichiers necessaires. ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
