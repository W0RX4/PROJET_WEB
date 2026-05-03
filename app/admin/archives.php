<?php
// Fichier qui affiche et filtre les stages archives.
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

// Cette fonction regroupe une action reutilisable.
function redirectArchives(): void
{
    header('Location: archives.php');
    exit;
}

// On traite les donnees envoyees par le formulaire.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = (string) $_POST['action'];
    $stageId = (int) ($_POST['stage_id'] ?? 0);

    // On verifie cette condition.
    if ($stageId <= 0) {
        $_SESSION['error'] = 'Stage invalide.';
        redirectArchives();
    }

    // On execute l action demandee par le formulaire.
    if ($action === 'archive') {
        // On appelle Supabase pour lire ou modifier les donnees.
        $update = supabaseRestRequest('PATCH', "$baseUrl/stages?id=eq.$stageId", $apiKey, ['status' => 'archivée']);
        // On controle cette condition avant de continuer.
        if (!$update['ok']) {
            $_SESSION['error'] = supabaseRestErrorMessage($update, 'Archivage impossible.');
            redirectArchives();
        }
        $_SESSION['result'] = 'Dossier archivé avec succès.';
        redirectArchives();
    }

    // On execute l action demandee par le formulaire.
    if ($action === 'unarchive') {
        // On appelle Supabase pour lire ou modifier les donnees.
        $update = supabaseRestRequest('PATCH', "$baseUrl/stages?id=eq.$stageId", $apiKey, ['status' => 'fermée']);
        // On controle cette condition avant de continuer.
        if (!$update['ok']) {
            $_SESSION['error'] = supabaseRestErrorMessage($update, 'Désarchivage impossible.');
            redirectArchives();
        }
        $_SESSION['result'] = 'Dossier sorti des archives.';
        redirectArchives();
    }
}

// On recupere et nettoie une valeur envoyee par l utilisateur.
$viewMode = trim((string) ($_GET['view'] ?? 'finished'));
// On recupere et nettoie une valeur envoyee par l utilisateur.
$keyword = trim((string) ($_GET['q'] ?? ''));
// On recupere et nettoie une valeur envoyee par l utilisateur.
$filiereFilter = trim((string) ($_GET['filiere'] ?? ''));

// On appelle Supabase pour lire ou modifier les donnees.
$stagesResult = supabaseRestRequest('GET', "$baseUrl/stages?select=*&order=end_date.desc", $apiKey);
$allStages = is_array($stagesResult['data']) ? $stagesResult['data'] : [];

// On appelle Supabase pour lire ou modifier les donnees.
$usersResult = supabaseRestRequest('GET', "$baseUrl/users?select=id,username,email,type", $apiKey);
// On prepare les donnees utilisees dans ce bloc.
$usersMap = [];
// On parcourt chaque element de la liste.
foreach ((is_array($usersResult['data']) ? $usersResult['data'] : []) as $user) {
    $usersMap[(int) ($user['id'] ?? 0)] = $user;
}

// On appelle Supabase pour lire ou modifier les donnees.
$documentsResult = supabaseRestRequest('GET', "$baseUrl/documents?select=*", $apiKey);
// On prepare les donnees utilisees dans ce bloc.
$documentsByStage = [];
// On parcourt chaque element de la liste.
foreach ((is_array($documentsResult['data']) ? $documentsResult['data'] : []) as $doc) {
    $documentsByStage[(int) ($doc['stage_id'] ?? 0)][] = $doc;
}

$today = date('Y-m-d');
// On prepare les donnees utilisees dans ce bloc.
$filieres = [];
// On parcourt chaque element de la liste.
foreach ($allStages as $stage) {
    $f = (string) ($stage['filiere'] ?? '');
    // On verifie cette condition.
    if ($f !== '' && !in_array($f, $filieres, true)) {
        $filieres[] = $f;
    }
}
sort($filieres);

// On prepare les donnees utilisees dans ce bloc.
$displayed = [];
// On parcourt chaque element de la liste.
foreach ($allStages as $stage) {
    $status = (string) ($stage['status'] ?? '');
    $endDate = (string) ($stage['end_date'] ?? '');
    $hasStudent = (int) ($stage['student_id'] ?? 0) > 0;
    $isFinished = $hasStudent && (($endDate !== '' && $endDate < $today) || $status === 'fermée');

    // On verifie cette condition.
    if ($viewMode === 'archived' && $status !== 'archivée') {
        continue;
    }
    // On verifie cette condition.
    if ($viewMode === 'finished' && (!$isFinished || $status === 'archivée')) {
        continue;
    }

    // On verifie cette condition.
    if ($filiereFilter !== '' && (string) ($stage['filiere'] ?? '') !== $filiereFilter) {
        continue;
    }

    // On verifie cette condition.
    if ($keyword !== '') {
        $student = $usersMap[(int) ($stage['student_id'] ?? 0)] ?? null;
        $haystack = strtolower(($stage['title'] ?? '') . ' ' . ($stage['company'] ?? '') . ' ' . ($student['username'] ?? '') . ' ' . ($student['email'] ?? ''));
        // On verifie cette condition.
        if (strpos($haystack, strtolower($keyword)) === false) {
            continue;
        }
    }

    $displayed[] = $stage;
}

// On charge les fichiers necessaires.
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="card mes-offres-hero">
    <h2>Archivage des dossiers de stage</h2>
    <p>Centralisez les dossiers de stage des étudiants et archivez ceux qui sont arrivés à terme.</p>
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
    <form method="GET" class="grid-container" style="margin-top: 1rem;">
        <div class="form-group">
            <label class="form-label" for="view">Vue</label>
            <select class="form-control" id="view" name="view">
                <option value="finished" <?php echo $viewMode === 'finished' ? 'selected' : ''; ?>>Stages terminés à archiver</option>
                <option value="archived" <?php echo $viewMode === 'archived' ? 'selected' : ''; ?>>Dossiers déjà archivés</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label" for="filiere">Filière</label>
            <select class="form-control" id="filiere" name="filiere">
                <option value="">Toutes</option>
                <?php // On parcourt chaque element de la liste. ?>
                <?php foreach ($filieres as $f): ?>
                    <option value="<?php echo htmlspecialchars($f); ?>" <?php echo $filiereFilter === $f ? 'selected' : ''; ?>><?php echo htmlspecialchars($f); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label" for="q">Recherche</label>
            <input class="form-control" type="text" id="q" name="q" value="<?php echo htmlspecialchars($keyword); ?>" placeholder="Étudiant, entreprise, titre...">
        </div>
        <div class="form-group" style="display: flex; gap: 0.6rem; align-items: flex-end;">
            <button type="submit" class="btn btn-primary">Filtrer</button>
            <a href="archives.php" class="btn btn-secondary">Réinitialiser</a>
        </div>
    </form>
</div>

<?php // On gere le cas ou la valeur attendue est vide. ?>
<?php if (empty($displayed)): ?>
    <div class="card"><p>Aucun dossier ne correspond à cette vue.</p></div>
<?php else: ?>
    <div class="grid-container">
        <?php // On parcourt chaque element de la liste. ?>
        <?php foreach ($displayed as $stage): ?>
            <?php
                $sid = (int) ($stage['id'] ?? 0);
                $studentId = (int) ($stage['student_id'] ?? 0);
                $student = $usersMap[$studentId] ?? null;
                $studentLabel = $student
                    ? ($student['username'] ?? '—') . ' (' . ($student['email'] ?? '') . ')'
                    : 'Compte étudiant supprimé';
                $tutor = $usersMap[(int) ($stage['tutor_id'] ?? 0)] ?? null;
                $stageDocs = $documentsByStage[$sid] ?? [];
                $status = (string) ($stage['status'] ?? '');
            ?>
            <div class="card offre-card">
                <h3 class="offre-title"><?php echo htmlspecialchars($stage['title'] ?? 'Stage'); ?></h3>
                <p class="offre-company"><?php echo htmlspecialchars($stage['company'] ?? ''); ?></p>

                <div class="offre-meta" style="margin-top: 0;">
                    <p><strong>Étudiant :</strong> <?php echo htmlspecialchars($studentLabel); ?></p>
                    <p><strong>Tuteur :</strong> <?php echo htmlspecialchars($tutor['username'] ?? '—'); ?></p>
                    <p><strong>Filière :</strong> <?php echo htmlspecialchars($stage['filiere'] ?? '—'); ?></p>
                    <p><strong>Période :</strong> <?php echo htmlspecialchars($stage['start_date'] ?? ''); ?> → <?php echo htmlspecialchars($stage['end_date'] ?? ''); ?></p>
                    <p><strong>Statut :</strong> <span class="badge <?php echo $status === 'archivée' ? 'badge-pending' : 'badge-valid'; ?>"><?php echo htmlspecialchars($status); ?></span></p>
                </div>

                <?php // On verifie cette condition. ?>
                <?php if (!empty($stageDocs)): ?>
                    <div style="margin-top: 0.75rem;">
                        <strong>Documents :</strong>
                        <ul style="margin-top: 0.4rem; padding-left: 1.2rem; color: var(--text-secondary);">
                            <?php // On parcourt chaque element de la liste. ?>
                            <?php foreach ($stageDocs as $doc): ?>
                                <?php $url = !empty($doc['file_path']) ? getSupabaseSignedUrl($doc['file_path'], $supabaseUrl, $apiKey) : null; ?>
                                <li>
                                    <?php // On controle cette condition avant de continuer. ?>
                                    <?php if ($url): ?>
                                        <a href="<?php echo htmlspecialchars($url); ?>" target="_blank">
                                            <?php echo htmlspecialchars(($doc['type'] ?? 'doc') . ' - ' . ($doc['file_name'] ?? '')); ?>
                                        </a>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars(($doc['type'] ?? 'doc') . ' - ' . ($doc['file_name'] ?? '')); ?>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php else: ?>
                    <p style="color: var(--text-secondary); margin-top: 0.6rem;">Aucun document associé.</p>
                <?php endif; ?>

                <div style="margin-top: 1.25rem; display: flex; gap: 0.6rem; flex-wrap: wrap;">
                    <?php // On verifie cette condition. ?>
                    <?php if ($status !== 'archivée'): ?>
                        <form method="POST" onsubmit="return confirm('Archiver ce dossier ?');">
                            <input type="hidden" name="action" value="archive">
                            <input type="hidden" name="stage_id" value="<?php echo $sid; ?>">
                            <button type="submit" class="btn btn-primary">Archiver</button>
                        </form>
                    <?php else: ?>
                        <form method="POST" onsubmit="return confirm('Sortir ce dossier des archives ?');">
                            <input type="hidden" name="action" value="unarchive">
                            <input type="hidden" name="stage_id" value="<?php echo $sid; ?>">
                            <button type="submit" class="btn btn-secondary">Désarchiver</button>
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
