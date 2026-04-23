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
$stageId = (int) ($_POST['stage_id'] ?? $_GET['id'] ?? 0);
$companyId = (int) ($_SESSION['user_id'] ?? 0);
$companyName = (string) ($_SESSION['username'] ?? '');

function redirectToStage(int $stageId): void
{
    header('Location: candidatures.php?id=' . $stageId);
    exit;
}

function fetchStageById(int $stageId, string $baseUrl, string $apiKey): array
{
    $result = supabaseRestRequest('GET', "$baseUrl/stages?id=eq.$stageId&select=*", $apiKey);
    $stage = is_array($result['data']) && isset($result['data'][0]) ? $result['data'][0] : null;

    return [$result, $stage];
}

function isOwnedByCompany(array $stage, int $companyId, string $companyName): bool
{
    $matchesCompanyId = $companyId > 0 && (int) ($stage['company_id'] ?? 0) === $companyId;
    $matchesCompanyName = isset($stage['company']) && (string) $stage['company'] === $companyName;

    return $matchesCompanyId || $matchesCompanyName;
}

function stageStatusBadgeClass(string $status): string
{
    $normalized = strtolower(trim($status));

    if (in_array($normalized, ['retenue', 'validée', 'validee', 'en cours', 'acceptée par l\'étudiant', 'convention envoyée'], true)) {
        return 'badge badge-valid';
    }

    if (in_array($normalized, ['refusée', 'refusee', 'annulée', 'annulee', 'refusée par l\'étudiant', 'refusee par l\'etudiant'], true)) {
        return 'badge badge-progress';
    }

    return 'badge badge-pending';
}

if ($stageId <= 0) {
    $_SESSION['error'] = 'Offre introuvable.';
    header('Location: mesOffres.php');
    exit;
}

[$stageFetchResult, $stage] = fetchStageById($stageId, $baseUrl, $apiKey);

if (!$stageFetchResult['ok'] || !$stage || !isOwnedByCompany($stage, $companyId, $companyName)) {
    $_SESSION['error'] = 'Cette offre est introuvable ou ne vous appartient pas.';
    header('Location: mesOffres.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = (string) $_POST['action'];

    if ($action === 'accept_candidate') {
        $studentId = (int) ($_POST['student_id'] ?? 0);

        if ($studentId <= 0) {
            $_SESSION['error'] = 'Étudiant invalide.';
            redirectToStage($stageId);
        }

        $stageCandidatesResult = supabaseRestRequest(
            'GET',
            "$baseUrl/candidatures?stage_id=eq.$stageId&select=id,student_id,status",
            $apiKey
        );
        $stageCandidates = is_array($stageCandidatesResult['data']) ? $stageCandidatesResult['data'] : [];
        $candidate = null;
        $hasConflictingCandidate = false;

        foreach ($stageCandidates as $stageCandidate) {
            if ((int) ($stageCandidate['student_id'] ?? 0) === $studentId) {
                $candidate = $stageCandidate;
            } elseif (in_array((string) ($stageCandidate['status'] ?? ''), ['proposition envoyée', 'acceptée par l\'étudiant', 'convention envoyée'], true)) {
                $hasConflictingCandidate = true;
            }
        }

        if (!$stageCandidatesResult['ok'] || !$candidate) {
            $_SESSION['error'] = 'Impossible d’accepter un étudiant non candidat.';
            redirectToStage($stageId);
        }

        if ((int) ($stage['student_id'] ?? 0) !== 0 && (int) ($stage['student_id'] ?? 0) !== $studentId) {
            $_SESSION['error'] = 'Un autre étudiant a déjà accepté cette offre.';
            redirectToStage($stageId);
        }

        if ($hasConflictingCandidate) {
            $_SESSION['error'] = 'Une autre candidature est déjà en attente de réponse ou a déjà été confirmée pour cette offre.';
            redirectToStage($stageId);
        }

        $conventionLookup = supabaseRestRequest(
            'GET',
            "$baseUrl/conventions?stage_id=eq.$stageId&student_id=eq.$studentId&select=id",
            $apiKey
        );
        $existingConvention = is_array($conventionLookup['data']) && isset($conventionLookup['data'][0]) ? $conventionLookup['data'][0] : null;

        if (!$conventionLookup['ok']) {
            $_SESSION['error'] = supabaseRestErrorMessage($conventionLookup, 'Impossible de préparer la convention.');
            redirectToStage($stageId);
        }

        if ($existingConvention) {
            $conventionSave = supabaseRestRequest(
                'PATCH',
                "$baseUrl/conventions?id=eq." . (int) $existingConvention['id'],
                $apiKey,
                ['company_validated' => true]
            );
        } else {
            $conventionSave = supabaseRestRequest(
                'POST',
                "$baseUrl/conventions",
                $apiKey,
                [
                    'stage_id' => $stageId,
                    'student_id' => $studentId,
                    'company_validated' => true,
                ]
            );
        }

        if (!$conventionSave['ok']) {
            $_SESSION['error'] = supabaseRestErrorMessage($conventionSave, 'Impossible de préparer la convention.');
            redirectToStage($stageId);
        }

        $candidateUpdate = supabaseRestRequest(
            'PATCH',
            "$baseUrl/candidatures?id=eq." . (int) $candidate['id'],
            $apiKey,
            ['status' => 'proposition envoyée']
        );

        if (!$candidateUpdate['ok']) {
            $_SESSION['error'] = supabaseRestErrorMessage($candidateUpdate, 'Proposition envoyée, mais le statut de candidature n’a pas pu être mis à jour.');
            redirectToStage($stageId);
        }

        $_SESSION['result'] = 'Proposition envoyée à l’étudiant. Il doit maintenant accepter ou refuser le stage.';
        redirectToStage($stageId);
    }

    if ($action === 'add_mission') {
        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));

        if ((int) ($stage['student_id'] ?? 0) === 0) {
            $_SESSION['error'] = 'L’étudiant doit d’abord accepter le stage avant l’ajout de missions complémentaires.';
            redirectToStage($stageId);
        }

        if ($title === '') {
            $_SESSION['error'] = 'Le titre de mission est obligatoire.';
            redirectToStage($stageId);
        }

        $missionResult = supabaseRestRequest(
            'POST',
            "$baseUrl/missions",
            $apiKey,
            [
                'stage_id' => $stageId,
                'company_id' => $companyId,
                'title' => $title,
                'description' => $description,
            ]
        );

        if (!$missionResult['ok']) {
            $_SESSION['error'] = supabaseRestErrorMessage($missionResult, 'Impossible d’ajouter la mission.');
            redirectToStage($stageId);
        }

        $_SESSION['result'] = 'Mission ajoutée avec succès.';
        redirectToStage($stageId);
    }

    if ($action === 'add_remark') {
        $content = trim((string) ($_POST['content'] ?? ''));

        if ($content === '') {
            $_SESSION['error'] = 'La remarque ne peut pas être vide.';
            redirectToStage($stageId);
        }

        $remarkResult = supabaseRestRequest(
            'POST',
            "$baseUrl/remarques",
            $apiKey,
            [
                'stage_id' => $stageId,
                'author_id' => $companyId,
                'content' => $content,
            ]
        );

        if (!$remarkResult['ok']) {
            $_SESSION['error'] = supabaseRestErrorMessage($remarkResult, 'Impossible d’ajouter la remarque.');
            redirectToStage($stageId);
        }

        $_SESSION['result'] = 'Remarque enregistrée.';
        redirectToStage($stageId);
    }
}

[$stageFetchResult, $stage] = fetchStageById($stageId, $baseUrl, $apiKey);

$candidaturesResult = supabaseRestRequest(
    'GET',
    "$baseUrl/candidatures?stage_id=eq.$stageId&select=*&order=created_at.desc",
    $apiKey
);
$candidatures = is_array($candidaturesResult['data']) ? $candidaturesResult['data'] : [];

$conventionsResult = supabaseRestRequest(
    'GET',
    "$baseUrl/conventions?stage_id=eq.$stageId&select=*&order=created_at.desc",
    $apiKey
);
$conventions = is_array($conventionsResult['data']) ? $conventionsResult['data'] : [];

$missionsResult = supabaseRestRequest(
    'GET',
    "$baseUrl/missions?stage_id=eq.$stageId&select=*&order=created_at.desc",
    $apiKey
);
$missions = is_array($missionsResult['data']) ? $missionsResult['data'] : [];

$remarksResult = supabaseRestRequest(
    'GET',
    "$baseUrl/remarques?stage_id=eq.$stageId&select=*&order=created_at.desc",
    $apiKey
);
$remarks = is_array($remarksResult['data']) ? $remarksResult['data'] : [];

$documentsResult = supabaseRestRequest(
    'GET',
    "$baseUrl/documents?stage_id=eq.$stageId&select=id,user_id,type,file_path,file_name,uploaded_at&order=uploaded_at.desc",
    $apiKey
);
$documents = is_array($documentsResult['data']) ? $documentsResult['data'] : [];

$usersResult = supabaseRestRequest(
    'GET',
    "$baseUrl/users?select=id,username,email",
    $apiKey
);
$users = is_array($usersResult['data']) ? $usersResult['data'] : [];
$usersMap = [];
foreach ($users as $user) {
    $usersMap[(int) ($user['id'] ?? 0)] = $user;
}

$conventionsByStudent = [];
foreach ($conventions as $convention) {
    $conventionsByStudent[(int) ($convention['student_id'] ?? 0)] = $convention;
}

$documentsByUser = [];
foreach ($documents as $document) {
    $userId = (int) ($document['user_id'] ?? 0);
    if ($userId > 0) {
        $documentsByUser[$userId][] = $document;
    }
}

$selectedStudentId = (int) ($stage['student_id'] ?? 0);
$selectedStudent = $selectedStudentId > 0 ? ($usersMap[$selectedStudentId] ?? null) : null;
$selectedConvention = $selectedStudentId > 0 ? ($conventionsByStudent[$selectedStudentId] ?? null) : null;
$pendingProposal = null;
foreach ($candidatures as $currentCandidature) {
    if (($currentCandidature['status'] ?? '') === 'proposition envoyée') {
        $pendingProposal = $currentCandidature;
        break;
    }
}
$pendingProposalStudent = $pendingProposal ? ($usersMap[(int) ($pendingProposal['student_id'] ?? 0)] ?? null) : null;

require_once '../../includes/header.php';
?>

<div class="card mes-offres-hero">
    <h2><?php echo htmlspecialchars($stage['title'] ?? 'Offre de stage'); ?></h2>
    <p><?php echo nl2br(htmlspecialchars($stage['description'] ?? '')); ?></p>
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

<?php if (!$stageFetchResult['ok']): ?>
    <div class="alert alert-error">
        <?php echo htmlspecialchars(supabaseRestErrorMessage($stageFetchResult, 'Impossible de charger cette offre.')); ?>
    </div>
<?php endif; ?>

<div class="card">
    <h3>Suivi de l’offre</h3>
    <div class="grid-container" style="margin-top: 1rem;">
        <div>
            <p><strong>Filière :</strong> <?php echo htmlspecialchars($stage['filiere'] ?? 'Non renseignée'); ?></p>
            <p><strong>Lieu :</strong> <?php echo htmlspecialchars($stage['location'] ?? 'Non renseigné'); ?></p>
            <p><strong>Période :</strong> du <?php echo htmlspecialchars($stage['start_date'] ?? 'N/A'); ?> au <?php echo htmlspecialchars($stage['end_date'] ?? 'N/A'); ?></p>
        </div>
        <div>
            <p><strong>Durée :</strong> <?php echo htmlspecialchars((string) ($stage['duration_weeks'] ?? 'N/A')); ?> semaine(s)</p>
            <p><strong>Statut du stage :</strong> <span class="<?php echo htmlspecialchars(stageStatusBadgeClass((string) ($stage['status'] ?? 'ouverte'))); ?>"><?php echo htmlspecialchars($stage['status'] ?? 'ouverte'); ?></span></p>
            <p><strong>Étudiant confirmé :</strong> <?php echo htmlspecialchars($selectedStudent['username'] ?? 'Aucun pour le moment'); ?></p>
            <p><strong>Proposition en attente :</strong> <?php echo htmlspecialchars($pendingProposalStudent['username'] ?? 'Aucune'); ?></p>
        </div>
    </div>

    <?php if ($selectedConvention): ?>
        <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
            <p><strong>Convention côté entreprise :</strong> <?php echo !empty($selectedConvention['company_validated']) ? 'préparée' : 'en attente'; ?></p>
            <p><strong>Validation tuteur :</strong> <?php echo !empty($selectedConvention['tutor_validated']) ? 'validée' : 'en attente'; ?></p>
            <p><strong>Validation admin :</strong> <?php echo !empty($selectedConvention['admin_validated']) ? 'validée' : 'en attente'; ?></p>
        </div>
    <?php endif; ?>
</div>

<div class="grid-container">
    <div class="card">
        <h3>Attribuer des missions</h3>
        <p style="margin-bottom: 1rem;">
            <?php if ($selectedStudent): ?>
                Les missions ajoutées ici sont associées au stage de <?php echo htmlspecialchars($selectedStudent['username']); ?>.
            <?php elseif ($pendingProposalStudent): ?>
                L’étudiant <?php echo htmlspecialchars($pendingProposalStudent['username']); ?> doit d’abord accepter la proposition avant l’affectation définitive.
            <?php else: ?>
                Sélectionnez d’abord un candidat pour cette offre. Les missions complémentaires pourront être ajoutées après confirmation de l’étudiant.
            <?php endif; ?>
        </p>

        <?php if (empty($missions)): ?>
            <p style="margin-bottom: 1rem;">Aucune mission n’a encore été créée.</p>
        <?php else: ?>
            <?php foreach ($missions as $mission): ?>
                <div style="padding: 0.9rem 0; border-top: 1px solid var(--border-color);">
                    <strong><?php echo htmlspecialchars($mission['title'] ?? 'Mission'); ?></strong>
                    <p style="margin-top: 0.4rem;"><?php echo nl2br(htmlspecialchars($mission['description'] ?? '')); ?></p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <form method="POST" style="margin-top: 1rem;">
            <input type="hidden" name="action" value="add_mission">
            <input type="hidden" name="stage_id" value="<?php echo $stageId; ?>">

            <div class="form-group">
                <label class="form-label" for="mission_title">Titre de la mission</label>
                <input class="form-control" type="text" id="mission_title" name="title" placeholder="Ex: Développer le tableau de bord" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="mission_description">Description</label>
                <textarea class="form-control" id="mission_description" name="description" placeholder="Décrivez la mission confiée au stagiaire"></textarea>
            </div>

            <button type="submit" class="btn btn-primary">Ajouter la mission</button>
        </form>
    </div>

    <div class="card">
        <h3>Remarques sur le stage</h3>
        <p style="margin-bottom: 1rem;">Centralisez vos observations et points de suivi pour cette offre.</p>

        <?php if (empty($remarks)): ?>
            <p style="margin-bottom: 1rem;">Aucune remarque enregistrée pour le moment.</p>
        <?php else: ?>
            <?php foreach ($remarks as $remark): ?>
                <?php $author = $usersMap[(int) ($remark['author_id'] ?? 0)] ?? null; ?>
                <div style="padding: 0.9rem 0; border-top: 1px solid var(--border-color);">
                    <p style="font-weight: 600;">
                        <?php echo htmlspecialchars($author['username'] ?? 'Auteur inconnu'); ?>
                        <span style="font-weight: 400; color: var(--text-secondary);">
                            le <?php echo htmlspecialchars(substr((string) ($remark['created_at'] ?? ''), 0, 10)); ?>
                        </span>
                    </p>
                    <p style="margin-top: 0.4rem;"><?php echo nl2br(htmlspecialchars($remark['content'] ?? '')); ?></p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <form method="POST" style="margin-top: 1rem;">
            <input type="hidden" name="action" value="add_remark">
            <input type="hidden" name="stage_id" value="<?php echo $stageId; ?>">

            <div class="form-group">
                <label class="form-label" for="remark_content">Nouvelle remarque</label>
                <textarea class="form-control" id="remark_content" name="content" placeholder="Ex: Étudiant très autonome, bon contact client..." required></textarea>
            </div>

            <button type="submit" class="btn btn-secondary">Enregistrer la remarque</button>
        </form>
    </div>
</div>

<div class="card" style="margin-top: 1.5rem;">
    <h3>Candidatures reçues</h3>
    <p>Consultez les documents des étudiants puis envoyez une proposition au profil retenu. L’étudiant confirmera ensuite le stage depuis son espace.</p>
</div>

<?php if (!$candidaturesResult['ok']): ?>
    <div class="alert alert-error">
        <?php echo htmlspecialchars(supabaseRestErrorMessage($candidaturesResult, 'Impossible de charger les candidatures.')); ?>
    </div>
<?php endif; ?>

<?php if (empty($candidatures)): ?>
    <div class="card"><p>Aucune candidature n'est disponible pour ce stage.</p></div>
<?php else: ?>
    <div class="grid-container">
        <?php foreach ($candidatures as $candidature): ?>
            <?php
                $studentId = (int) ($candidature['student_id'] ?? 0);
                $student = $usersMap[$studentId] ?? null;
                $studentName = $student['username'] ?? 'Nom non disponible';
                $studentEmail = $student['email'] ?? 'Email non disponible';
                $status = (string) ($candidature['status'] ?? 'en attente');
                $convention = $conventionsByStudent[$studentId] ?? null;
                $studentDocuments = $documentsByUser[$studentId] ?? [];
                $cvUrl = !empty($candidature['cv_url']) ? getSupabaseSignedUrl($candidature['cv_url'], $_ENV['SUPABASE_URL'], $_ENV['SUPABASE_KEY']) : null;
                $lmUrl = !empty($candidature['cover_letter_url']) ? getSupabaseSignedUrl($candidature['cover_letter_url'], $_ENV['SUPABASE_URL'], $_ENV['SUPABASE_KEY']) : null;
                $isSelectedStudent = $selectedStudentId !== 0 && $selectedStudentId === $studentId;
                $isPendingProposal = $pendingProposal && (int) ($pendingProposal['student_id'] ?? 0) === $studentId;
                $hasAcceptedStudent = $selectedStudentId !== 0;
                $canSelectThisStudent = !$hasAcceptedStudent && (!$pendingProposal || $isPendingProposal);
                $isConventionPrepared = !empty($convention['company_validated']);
            ?>
            <div class="card offre-card">
                <h3 class="offre-title"><?php echo htmlspecialchars($studentName); ?></h3>
                <p class="offre-company"><?php echo htmlspecialchars($studentEmail); ?></p>

                <div class="offre-meta" style="margin-top: 0;">
                    <p><strong>Statut candidature :</strong> <span class="<?php echo htmlspecialchars(stageStatusBadgeClass($status)); ?>"><?php echo htmlspecialchars($status); ?></span></p>
                    <p><strong>Convention entreprise :</strong> <?php echo $isConventionPrepared ? 'préparée' : 'non préparée'; ?></p>
                    <p><strong>Étudiant confirmé :</strong> <?php echo $isSelectedStudent ? 'oui' : 'non'; ?></p>
                </div>

                <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; margin-top: 1rem;">
                    <?php if ($cvUrl): ?>
                        <a href="<?php echo htmlspecialchars($cvUrl); ?>" target="_blank" class="btn btn-primary" style="padding: 0.5rem 1rem;">Voir le CV</a>
                    <?php endif; ?>
                    <?php if ($lmUrl): ?>
                        <a href="<?php echo htmlspecialchars($lmUrl); ?>" target="_blank" class="btn btn-primary" style="padding: 0.5rem 1rem;">Voir la LM</a>
                    <?php endif; ?>
                    <?php if (!$cvUrl && !$lmUrl): ?>
                        <span class="btn btn-secondary" style="padding: 0.5rem 1rem; opacity: 0.65;">Documents de candidature indisponibles</span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($studentDocuments)): ?>
                    <div style="margin-top: 1rem;">
                        <p style="font-weight: 600; margin-bottom: 0.5rem;">Documents supplémentaires</p>
                        <?php foreach ($studentDocuments as $document): ?>
                            <?php $documentUrl = !empty($document['file_path']) ? getSupabaseSignedUrl($document['file_path'], $_ENV['SUPABASE_URL'], $_ENV['SUPABASE_KEY']) : null; ?>
                            <div style="margin-bottom: 0.4rem;">
                                <?php if ($documentUrl): ?>
                                    <a href="<?php echo htmlspecialchars($documentUrl); ?>" target="_blank">
                                        <?php echo htmlspecialchars(($document['type'] ?? 'document') . ' - ' . ($document['file_name'] ?? 'fichier')); ?>
                                    </a>
                                <?php else: ?>
                                    <span><?php echo htmlspecialchars(($document['type'] ?? 'document') . ' - ' . ($document['file_name'] ?? 'fichier')); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div style="margin-top: 1.25rem;">
                    <?php if ($status === 'proposition envoyée'): ?>
                        <p style="color: var(--text-secondary);">Proposition envoyée. En attente de la réponse de l’étudiant.</p>
                    <?php elseif (in_array($status, ['acceptée par l\'étudiant', 'convention envoyée'], true)): ?>
                        <p style="color: var(--text-secondary);">Étudiant confirmé. Il peut maintenant déposer sa convention depuis son espace.</p>
                    <?php elseif ($status === 'refusée par l\'étudiant'): ?>
                        <p style="color: var(--text-secondary);">Cet étudiant a refusé la proposition. Vous pouvez choisir un autre candidat.</p>
                    <?php elseif ($canSelectThisStudent): ?>
                        <form method="POST" onsubmit="return confirm('Envoyer une proposition de stage à cet étudiant ?');">
                            <input type="hidden" name="action" value="accept_candidate">
                            <input type="hidden" name="stage_id" value="<?php echo $stageId; ?>">
                            <input type="hidden" name="student_id" value="<?php echo $studentId; ?>">
                            <button type="submit" class="btn btn-primary">
                                Envoyer la proposition
                            </button>
                        </form>
                    <?php else: ?>
                        <p style="color: var(--text-secondary);">Une autre candidature est déjà en attente de réponse ou a déjà été confirmée pour cette offre.</p>
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
