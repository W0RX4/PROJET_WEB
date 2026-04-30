<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['type']) || $_SESSION['type'] !== 'etudiant') {
    header('Location: /login');
    exit;
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../supabaseQuery/restClient.php';
require_once __DIR__ . '/../../supabaseQuery/getSupabaseSignedUrl.php';
require_once __DIR__ . '/../../supabaseQuery/storageClient.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->safeLoad();

$apiKey = $_ENV['SUPABASE_KEY'] ?? '';
$baseUrl = rtrim($_ENV['SUPABASE_URL'] ?? '', '/') . '/rest/v1';
$supabaseUrl = $_ENV['SUPABASE_URL'] ?? '';
$userId = (int) ($_SESSION['user_id'] ?? 0);
$userEmail = (string) ($_SESSION['email'] ?? '');

function redirectToMesCandidatures(): void
{
    header('Location: mesCandidatures.php');
    exit;
}

function studentBadgeClass(string $status): string
{
    $normalized = strtolower(trim($status));

    if (in_array($normalized, ['proposition envoyée', 'acceptée par l\'étudiant', 'convention envoyée', 'en cours'], true)) {
        return 'badge badge-valid';
    }

    if (in_array($normalized, ['refusée par l\'étudiant', 'refusee par l\'etudiant', 'refusée', 'refusee'], true)) {
        return 'badge badge-progress';
    }

    return 'badge badge-pending';
}

function studentStatusLabel(string $status): string
{
    $normalized = strtolower(trim($status));

    if ($normalized === 'proposition envoyée') {
        return 'Accepté(e) par l\'entreprise';
    }
    if ($normalized === 'acceptée par l\'étudiant') {
        return 'Stage confirmé';
    }
    if ($normalized === 'convention envoyée') {
        return 'Convention déposée';
    }
    if (in_array($normalized, ['refusée par l\'étudiant', 'refusee par l\'etudiant'], true)) {
        return 'Proposition refusée';
    }

    return $status;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = (string) ($_POST['action'] ?? '');
    $candidatureId = (int) ($_POST['candidature_id'] ?? 0);
    $stageId = (int) ($_POST['stage_id'] ?? 0);

    $candidatureResult = supabaseRestRequest(
        'GET',
        "$baseUrl/candidatures?id=eq.$candidatureId&student_id=eq.$userId&select=*",
        $apiKey
    );
    $candidature = is_array($candidatureResult['data']) && isset($candidatureResult['data'][0]) ? $candidatureResult['data'][0] : null;

    if (!$candidatureResult['ok'] || !$candidature) {
        $_SESSION['error'] = 'Candidature introuvable.';
        redirectToMesCandidatures();
    }

    if ($action === 'accept_stage') {
        if (($candidature['status'] ?? '') !== 'proposition envoyée') {
            $_SESSION['error'] = 'Cette proposition ne peut plus être acceptée.';
            redirectToMesCandidatures();
        }

        $stageResult = supabaseRestRequest(
            'GET',
            "$baseUrl/stages?id=eq.$stageId&select=id,student_id,status",
            $apiKey
        );
        $stage = is_array($stageResult['data']) && isset($stageResult['data'][0]) ? $stageResult['data'][0] : null;

        if (!$stageResult['ok'] || !$stage) {
            $_SESSION['error'] = 'Stage introuvable.';
            redirectToMesCandidatures();
        }

        if ((int) ($stage['student_id'] ?? 0) !== 0 && (int) ($stage['student_id'] ?? 0) !== $userId) {
            $_SESSION['error'] = 'Ce stage a déjà été attribué à un autre étudiant.';
            redirectToMesCandidatures();
        }

        $stageUpdateResult = supabaseRestRequest(
            'PATCH',
            "$baseUrl/stages?id=eq.$stageId",
            $apiKey,
            [
                'student_id' => $userId,
                'status' => 'en cours',
            ]
        );

        if (!$stageUpdateResult['ok']) {
            $_SESSION['error'] = supabaseRestErrorMessage($stageUpdateResult, 'Impossible de confirmer ce stage.');
            redirectToMesCandidatures();
        }

        $conventionLookup = supabaseRestRequest(
            'GET',
            "$baseUrl/conventions?stage_id=eq.$stageId&student_id=eq.$userId&select=id",
            $apiKey
        );
        $existingConvention = is_array($conventionLookup['data']) && isset($conventionLookup['data'][0]) ? $conventionLookup['data'][0] : null;

        if (!$conventionLookup['ok']) {
            $_SESSION['error'] = supabaseRestErrorMessage($conventionLookup, 'Stage confirmé, mais la convention n’a pas pu être préparée.');
            redirectToMesCandidatures();
        }

        if (!$existingConvention) {
            $createConvention = supabaseRestRequest(
                'POST',
                "$baseUrl/conventions",
                $apiKey,
                [
                    'stage_id' => $stageId,
                    'student_id' => $userId,
                    'company_validated' => false,
                ]
            );

            if (!$createConvention['ok']) {
                $_SESSION['error'] = supabaseRestErrorMessage($createConvention, 'Stage confirmé, mais la convention n’a pas pu être préparée.');
                redirectToMesCandidatures();
            }
        }

        $candidatureUpdateResult = supabaseRestRequest(
            'PATCH',
            "$baseUrl/candidatures?id=eq.$candidatureId",
            $apiKey,
            ['status' => 'acceptée par l\'étudiant']
        );

        if (!$candidatureUpdateResult['ok']) {
            $_SESSION['error'] = supabaseRestErrorMessage($candidatureUpdateResult, 'Le stage est confirmé, mais le statut n’a pas pu être mis à jour.');
            redirectToMesCandidatures();
        }

        $_SESSION['result'] = 'Stage accepté. Vous pouvez maintenant téléverser votre convention.';
        redirectToMesCandidatures();
    }

    if ($action === 'refuse_stage') {
        if (($candidature['status'] ?? '') !== 'proposition envoyée') {
            $_SESSION['error'] = 'Cette proposition ne peut plus être refusée.';
            redirectToMesCandidatures();
        }

        $candidatureUpdateResult = supabaseRestRequest(
            'PATCH',
            "$baseUrl/candidatures?id=eq.$candidatureId",
            $apiKey,
            ['status' => 'refusée par l\'étudiant']
        );

        if (!$candidatureUpdateResult['ok']) {
            $_SESSION['error'] = supabaseRestErrorMessage($candidatureUpdateResult, 'Impossible d’enregistrer votre refus.');
            redirectToMesCandidatures();
        }

        supabaseRestRequest(
            'DELETE',
            "$baseUrl/conventions?stage_id=eq.$stageId&student_id=eq.$userId",
            $apiKey
        );

        $_SESSION['result'] = 'Vous avez refusé cette proposition de stage.';
        redirectToMesCandidatures();
    }

    if ($action === 'upload_convention') {
        $currentStatus = (string) ($candidature['status'] ?? '');
        if (!in_array($currentStatus, ['acceptée par l\'étudiant', 'convention envoyée'], true)) {
            $_SESSION['error'] = 'Vous devez d’abord accepter le stage avant de déposer la convention.';
            redirectToMesCandidatures();
        }

        if (!isset($_FILES['convention'])) {
            $_SESSION['error'] = 'Aucun fichier de convention reçu.';
            redirectToMesCandidatures();
        }

        $upload = uploadFileToSupabaseBucket($_FILES['convention'], $userEmail, 'CONVENTION', $supabaseUrl, $apiKey);
        if ($upload === null) {
            $_SESSION['error'] = 'Erreur lors de l’upload de la convention.';
            redirectToMesCandidatures();
        }

        $documentLookup = supabaseRestRequest(
            'GET',
            "$baseUrl/documents?stage_id=eq.$stageId&user_id=eq.$userId&type=eq.convention&select=id",
            $apiKey
        );
        $existingDocument = is_array($documentLookup['data']) && isset($documentLookup['data'][0]) ? $documentLookup['data'][0] : null;

        if (!$documentLookup['ok']) {
            $_SESSION['error'] = supabaseRestErrorMessage($documentLookup, 'Convention envoyée, mais son enregistrement en base a échoué.');
            redirectToMesCandidatures();
        }

        $documentPayload = [
            'stage_id' => $stageId,
            'user_id' => $userId,
            'type' => 'convention',
            'file_path' => $upload['path'],
            'file_name' => $upload['file_name'],
        ];

        if ($existingDocument) {
            $documentResult = supabaseRestRequest(
                'PATCH',
                "$baseUrl/documents?id=eq." . (int) $existingDocument['id'],
                $apiKey,
                $documentPayload
            );
        } else {
            $documentResult = supabaseRestRequest(
                'POST',
                "$baseUrl/documents",
                $apiKey,
                $documentPayload
            );
        }

        if (!$documentResult['ok']) {
            $_SESSION['error'] = supabaseRestErrorMessage($documentResult, 'Convention envoyée, mais son enregistrement en base a échoué.');
            redirectToMesCandidatures();
        }

        $candidatureUpdateResult = supabaseRestRequest(
            'PATCH',
            "$baseUrl/candidatures?id=eq.$candidatureId",
            $apiKey,
            ['status' => 'convention envoyée']
        );

        if (!$candidatureUpdateResult['ok']) {
            $_SESSION['error'] = supabaseRestErrorMessage($candidatureUpdateResult, 'Convention envoyée, mais le statut n’a pas pu être mis à jour.');
            redirectToMesCandidatures();
        }

        $_SESSION['result'] = 'Convention envoyée avec succès.';
        redirectToMesCandidatures();
    }
}

$candidaturesResult = supabaseRestRequest(
    'GET',
    "$baseUrl/candidatures?student_id=eq.$userId&select=*&order=created_at.desc",
    $apiKey
);
$mesCandidatures = is_array($candidaturesResult['data']) ? $candidaturesResult['data'] : [];

$stagesResult = supabaseRestRequest(
    'GET',
    "$baseUrl/stages?select=*",
    $apiKey
);
$allStages = is_array($stagesResult['data']) ? $stagesResult['data'] : [];
$stagesMap = [];
foreach ($allStages as $stage) {
    $stagesMap[(int) ($stage['id'] ?? 0)] = $stage;
}

$documentsResult = supabaseRestRequest(
    'GET',
    "$baseUrl/documents?user_id=eq.$userId&select=id,stage_id,type,file_path,file_name,uploaded_at&order=uploaded_at.desc",
    $apiKey
);
$documents = is_array($documentsResult['data']) ? $documentsResult['data'] : [];
$documentsByStage = [];
foreach ($documents as $document) {
    $documentsByStage[(int) ($document['stage_id'] ?? 0)][] = $document;
}

$conventionsResult = supabaseRestRequest(
    'GET',
    "$baseUrl/conventions?student_id=eq.$userId&select=*",
    $apiKey
);
$conventionsByStage = [];
foreach ((is_array($conventionsResult['data']) ? $conventionsResult['data'] : []) as $conv) {
    $conventionsByStage[(int) ($conv['stage_id'] ?? 0)] = $conv;
}

require_once '../../includes/header.php';
?>

<div class="card mes-offres-hero">
    <h2>Mes candidatures</h2>
    <p>Suivez vos réponses de stage, confirmez une proposition et envoyez votre convention après acceptation.</p>
</div>

<?php if (isset($_SESSION['result'])): ?>
    <div class="alert alert-success">
        <?php echo htmlspecialchars($_SESSION['result']); ?>
    </div>
    <?php unset($_SESSION['result']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-error">
        <?php echo htmlspecialchars($_SESSION['error']); ?>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<?php if (empty($mesCandidatures)): ?>
    <div class="card"><p>Vous n'avez pas encore postulé à un stage.</p></div>
<?php else: ?>
    <div class="grid-container">
        <?php foreach ($mesCandidatures as $candidature): ?>
            <?php
                $stageId = (int) ($candidature['stage_id'] ?? 0);
                $stage = $stagesMap[$stageId] ?? null;
                $stageTitle = $stage['title'] ?? 'Stage inconnu';
                $stageCompany = $stage['company'] ?? 'Entreprise inconnue';
                $status = (string) ($candidature['status'] ?? 'en attente');
                $cvUrl = !empty($candidature['cv_url']) ? getSupabaseSignedUrl($candidature['cv_url'], $_ENV['SUPABASE_URL'], $_ENV['SUPABASE_KEY']) : null;
                $lmUrl = !empty($candidature['cover_letter_url']) ? getSupabaseSignedUrl($candidature['cover_letter_url'], $_ENV['SUPABASE_URL'], $_ENV['SUPABASE_KEY']) : null;
                $stageDocuments = $documentsByStage[$stageId] ?? [];
                $conventionDocument = null;
                foreach ($stageDocuments as $document) {
                    if (($document['type'] ?? '') === 'convention') {
                        $conventionDocument = $document;
                        break;
                    }
                }
                $conventionUrl = $conventionDocument ? getSupabaseSignedUrl($conventionDocument['file_path'] ?? '', $_ENV['SUPABASE_URL'], $_ENV['SUPABASE_KEY']) : null;
                $convention = $conventionsByStage[$stageId] ?? null;
                $companyValid = $convention ? !empty($convention['company_validated']) : false;
                $tutorValid = $convention ? !empty($convention['tutor_validated']) : false;
                $adminValid = $convention ? !empty($convention['admin_validated']) : false;
            ?>
            <div class="card offre-card">
                <h3 class="offre-title"><?php echo htmlspecialchars($stageTitle); ?></h3>
                <p class="offre-company"><?php echo htmlspecialchars($stageCompany); ?></p>
                <div class="offre-meta" style="margin-top: 0;">
                    <p><strong>Statut :</strong> <span class="<?php echo htmlspecialchars(studentBadgeClass($status)); ?>"><?php echo htmlspecialchars(studentStatusLabel($status)); ?></span></p>
                </div>

                <?php if ($convention): ?>
                    <div style="margin-top: 1rem; padding: 0.75rem 1rem; background: var(--gradient-subtle); border-radius: var(--border-radius-sm); border: 1px solid rgba(139, 92, 246, 0.1);">
                        <p style="font-weight: 600; margin-bottom: 0.5rem; color: var(--text-primary);">Validation de la convention</p>
                        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                            <span class="badge <?php echo $companyValid ? 'badge-valid' : 'badge-pending'; ?>">Entreprise : <?php echo $companyValid ? 'validée' : 'en attente'; ?></span>
                            <span class="badge <?php echo $tutorValid ? 'badge-valid' : 'badge-pending'; ?>">Tuteur : <?php echo $tutorValid ? 'validée' : 'en attente'; ?></span>
                            <span class="badge <?php echo $adminValid ? 'badge-valid' : 'badge-pending'; ?>">Admin : <?php echo $adminValid ? 'validée' : 'en attente'; ?></span>
                        </div>
                        <?php if ($companyValid && $tutorValid && $adminValid): ?>
                            <p style="margin-top: 0.6rem; margin-bottom: 0; color: var(--success-color); font-weight: 600;">Votre convention est entièrement validée !</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($status === 'proposition envoyée'): ?>
                    <div class="alert alert-success" style="margin-top: 1rem;">
                        <strong>Bonne nouvelle !</strong> L'entreprise vous a accepté(e) pour ce stage. Confirmez votre acceptation ci-dessous pour lancer la convention.
                    </div>
                <?php endif; ?>

                <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; margin-top: 1rem;">
                    <?php if ($cvUrl): ?>
                        <a href="<?php echo htmlspecialchars($cvUrl); ?>" target="_blank" class="btn btn-secondary" style="padding: 0.5rem 1rem;">Mon CV</a>
                    <?php endif; ?>
                    <?php if ($lmUrl): ?>
                        <a href="<?php echo htmlspecialchars($lmUrl); ?>" target="_blank" class="btn btn-secondary" style="padding: 0.5rem 1rem;">Ma lettre</a>
                    <?php endif; ?>
                    <?php if ($conventionUrl): ?>
                        <a href="<?php echo htmlspecialchars($conventionUrl); ?>" target="_blank" class="btn btn-secondary" style="padding: 0.5rem 1rem;">Ma convention</a>
                    <?php endif; ?>
                </div>

                <?php if ($status === 'proposition envoyée'): ?>
                    <div style="margin-top: 1.25rem;">
                        <p style="margin-bottom: 0.75rem;">Voulez-vous accepter cette proposition de stage ?</p>
                        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                            <form method="POST">
                                <input type="hidden" name="action" value="accept_stage">
                                <input type="hidden" name="candidature_id" value="<?php echo (int) ($candidature['id'] ?? 0); ?>">
                                <input type="hidden" name="stage_id" value="<?php echo $stageId; ?>">
                                <button type="submit" class="btn btn-primary">Accepter le stage</button>
                            </form>
                            <form method="POST" onsubmit="return confirm('Refuser cette proposition de stage ?');">
                                <input type="hidden" name="action" value="refuse_stage">
                                <input type="hidden" name="candidature_id" value="<?php echo (int) ($candidature['id'] ?? 0); ?>">
                                <input type="hidden" name="stage_id" value="<?php echo $stageId; ?>">
                                <button type="submit" class="btn btn-secondary">Refuser le stage</button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (in_array($status, ['acceptée par l\'étudiant', 'convention envoyée'], true)): ?>
                    <div style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                        <p style="margin-bottom: 0.75rem;">
                            <?php if ($conventionDocument): ?>
                                La convention est déjà déposée. Vous pouvez en téléverser une nouvelle version si besoin.
                            <?php else: ?>
                                Le stage est confirmé. Vous pouvez maintenant téléverser votre convention.
                            <?php endif; ?>
                        </p>

                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="upload_convention">
                            <input type="hidden" name="candidature_id" value="<?php echo (int) ($candidature['id'] ?? 0); ?>">
                            <input type="hidden" name="stage_id" value="<?php echo $stageId; ?>">

                            <div class="form-group">
                                <label class="form-label" for="convention_<?php echo (int) ($candidature['id'] ?? 0); ?>">Convention de stage (PDF)</label>
                                <input
                                    type="file"
                                    id="convention_<?php echo (int) ($candidature['id'] ?? 0); ?>"
                                    name="convention"
                                    class="form-control"
                                    accept=".pdf"
                                    required
                                >
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <?php echo $conventionDocument ? 'Mettre à jour la convention' : 'Envoyer la convention'; ?>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="mes-offres-actions">
    <a class="btn btn-secondary mt-4" href="accueilUser.php">Retour à l'accueil</a>
</div>

<?php require_once '../../includes/footer.php'; ?>
