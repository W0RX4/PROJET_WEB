<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../supabaseQuery/restClient.php';
require_once __DIR__ . '/../../supabaseQuery/storageClient.php';
require_once __DIR__ . '/../../supabaseQuery/getSupabaseSignedUrl.php';
require_once __DIR__ . '/../../includes/trace.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['type']) || $_SESSION['type'] !== 'etudiant') {
    header('Location: /login');
    exit;
}

use Dotenv\Dotenv;

if (!isset($_ENV['SUPABASE_URL'])) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
    $dotenv->safeLoad();
}

$apiKey = (string) ($_ENV['SUPABASE_KEY'] ?? '');
$supabaseUrl = (string) ($_ENV['SUPABASE_URL'] ?? '');
$baseUrl = rtrim($supabaseUrl, '/') . '/rest/v1';
$userId = (int) ($_SESSION['user_id'] ?? 0);
$userEmail = (string) ($_SESSION['email'] ?? '');

$documentTypes = [
    'rapport' => ['label' => 'Rapport de stage', 'prefix' => 'RAPPORT'],
    'resume' => ['label' => 'Résumé de stage', 'prefix' => 'RESUME'],
    'fiche_evaluation' => ['label' => "Fiche d'évaluation", 'prefix' => 'EVAL'],
];

function redirectToDocs(): void
{
    header('Location: documents.php');
    exit;
}

$stagesResult = supabaseRestRequest(
    'GET',
    "$baseUrl/stages?student_id=eq.$userId&select=id,title,company&order=created_at.desc",
    $apiKey
);
$myStages = is_array($stagesResult['data']) ? $stagesResult['data'] : [];
$activeStage = $myStages[0] ?? null;
$activeStageId = $activeStage ? (int) ($activeStage['id'] ?? 0) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'upload_document') {
        $type = (string) ($_POST['document_type'] ?? '');
        if (!isset($documentTypes[$type])) {
            $_SESSION['error'] = 'Type de document inconnu.';
            redirectToDocs();
        }

        if (!$activeStageId) {
            $_SESSION['error'] = "Vous devez avoir un stage actif pour déposer un document de restitution.";
            redirectToDocs();
        }

        if (!isset($_FILES['document']) || ($_FILES['document']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = 'Aucun fichier reçu ou erreur d\'upload.';
            redirectToDocs();
        }

        $upload = uploadFileToSupabaseBucket(
            $_FILES['document'],
            $userEmail,
            $documentTypes[$type]['prefix'],
            $supabaseUrl,
            $apiKey
        );

        if ($upload === null) {
            $_SESSION['error'] = 'Erreur lors de l\'upload du document.';
            redirectToDocs();
        }

        $existingResult = supabaseRestRequest(
            'GET',
            "$baseUrl/documents?stage_id=eq.$activeStageId&user_id=eq.$userId&type=eq." . rawurlencode($type) . '&select=id',
            $apiKey
        );
        $existing = is_array($existingResult['data']) && isset($existingResult['data'][0]) ? $existingResult['data'][0] : null;

        $payload = [
            'stage_id' => $activeStageId,
            'user_id' => $userId,
            'type' => $type,
            'file_path' => $upload['path'],
            'file_name' => $upload['file_name'],
        ];

        if ($existing) {
            $saveResult = supabaseRestRequest(
                'PATCH',
                "$baseUrl/documents?id=eq." . (int) $existing['id'],
                $apiKey,
                $payload
            );
        } else {
            $saveResult = supabaseRestRequest(
                'POST',
                "$baseUrl/documents",
                $apiKey,
                $payload
            );
        }

        if (!$saveResult['ok']) {
            $_SESSION['error'] = supabaseRestErrorMessage($saveResult, "Document envoyé, mais l'enregistrement a échoué.");
            redirectToDocs();
        }

        stageArchiveLogTrace('document_upload', "type=$type, stage=$activeStageId");
        $_SESSION['result'] = $documentTypes[$type]['label'] . ' enregistré avec succès.';
        redirectToDocs();
    }
}

stageArchiveLogPageAccess('/app/user/documents.php');

$existingDocs = [];
if ($activeStageId) {
    $docsResult = supabaseRestRequest(
        'GET',
        "$baseUrl/documents?user_id=eq.$userId&stage_id=eq.$activeStageId&select=*&order=uploaded_at.desc",
        $apiKey
    );
    $allDocs = is_array($docsResult['data']) ? $docsResult['data'] : [];
    foreach ($allDocs as $doc) {
        $type = (string) ($doc['type'] ?? '');
        if (isset($documentTypes[$type]) && !isset($existingDocs[$type])) {
            $existingDocs[$type] = $doc;
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="card mes-offres-hero">
    <h2>Documents de restitution</h2>
    <p>Déposez ici les documents finaux de votre stage : rapport, résumé et fiche d'évaluation.</p>
</div>

<?php if (isset($_SESSION['result'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['result']); unset($_SESSION['result']); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
<?php endif; ?>

<?php if (!$activeStage): ?>
    <div class="card">
        <p>Vous n'avez pas encore de stage confirmé. Le dépôt des documents de restitution sera disponible une fois votre stage validé.</p>
        <a href="mesCandidatures.php" class="btn btn-primary mt-4">Voir mes candidatures</a>
    </div>
<?php else: ?>
    <div class="card">
        <h3><?php echo htmlspecialchars($activeStage['title'] ?? 'Stage'); ?></h3>
        <p style="font-weight: 600; color: var(--text-primary);"><?php echo htmlspecialchars($activeStage['company'] ?? ''); ?></p>
    </div>

    <div class="grid-container">
        <?php foreach ($documentTypes as $typeKey => $typeMeta): ?>
            <?php
                $existing = $existingDocs[$typeKey] ?? null;
                $signedUrl = $existing ? getSupabaseSignedUrl($existing['file_path'] ?? '', $supabaseUrl, $apiKey) : null;
            ?>
            <div class="card">
                <h3><?php echo htmlspecialchars($typeMeta['label']); ?></h3>
                <?php if ($existing): ?>
                    <p style="margin-bottom: 0.5rem;">
                        <span class="badge badge-valid">Déposé</span>
                    </p>
                    <p style="color: var(--text-secondary); font-size: 0.875rem;">
                        <?php echo htmlspecialchars($existing['file_name'] ?? ''); ?><br>
                        <?php echo htmlspecialchars($existing['uploaded_at'] ?? ''); ?>
                    </p>
                    <?php if ($signedUrl): ?>
                        <a href="<?php echo htmlspecialchars($signedUrl); ?>" target="_blank" class="btn btn-secondary mt-4">Télécharger</a>
                    <?php endif; ?>
                <?php else: ?>
                    <p><span class="badge badge-pending">Non déposé</span></p>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" style="margin-top: 1.25rem;">
                    <input type="hidden" name="action" value="upload_document">
                    <input type="hidden" name="document_type" value="<?php echo htmlspecialchars($typeKey); ?>">
                    <div class="form-group">
                        <label class="form-label" for="document_<?php echo $typeKey; ?>">Fichier (PDF)</label>
                        <input type="file" id="document_<?php echo $typeKey; ?>" name="document" class="form-control" accept=".pdf" required>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <?php echo $existing ? 'Remplacer' : 'Déposer'; ?>
                    </button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
