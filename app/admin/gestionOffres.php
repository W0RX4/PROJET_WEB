<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['type']) || $_SESSION['type'] !== 'admin') {
    header('Location: /login');
    exit;
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../supabaseQuery/restClient.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->safeLoad();

$apiKey = $_ENV['SUPABASE_KEY'] ?? '';
$baseUrl = rtrim($_ENV['SUPABASE_URL'] ?? '', '/') . '/rest/v1';

function redirectGestionOffres(): void
{
    header('Location: gestionOffres.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = (string) $_POST['action'];
    $stageId = (int) ($_POST['stage_id'] ?? 0);

    if ($stageId <= 0) {
        $_SESSION['error'] = 'Offre invalide.';
        redirectGestionOffres();
    }

    if ($action === 'delete') {
        supabaseRestRequest('DELETE', "$baseUrl/missions?stage_id=eq.$stageId", $apiKey);
        supabaseRestRequest('DELETE', "$baseUrl/remarques?stage_id=eq.$stageId", $apiKey);
        supabaseRestRequest('DELETE', "$baseUrl/cahier_stage?stage_id=eq.$stageId", $apiKey);
        supabaseRestRequest('DELETE', "$baseUrl/conventions?stage_id=eq.$stageId", $apiKey);
        supabaseRestRequest('DELETE', "$baseUrl/candidatures?stage_id=eq.$stageId", $apiKey);
        supabaseRestRequest('PATCH', "$baseUrl/users?stage_id=eq.$stageId", $apiKey, ['stage_id' => null]);
        supabaseRestRequest('DELETE', "$baseUrl/documents?stage_id=eq.$stageId", $apiKey);

        $deleteResult = supabaseRestRequest('DELETE', "$baseUrl/stages?id=eq.$stageId", $apiKey);
        if (!$deleteResult['ok']) {
            $_SESSION['error'] = supabaseRestErrorMessage($deleteResult, 'Suppression impossible.');
            redirectGestionOffres();
        }
        $_SESSION['result'] = 'Offre supprimée.';
        redirectGestionOffres();
    }

    if ($action === 'publish') {
        $statusValue = (string) ($_POST['status'] ?? 'ouverte');
        $allowed = ['ouverte', 'fermée', 'archivée', 'en cours'];
        if (!in_array($statusValue, $allowed, true)) {
            $_SESSION['error'] = 'Statut invalide.';
            redirectGestionOffres();
        }

        $update = supabaseRestRequest(
            'PATCH',
            "$baseUrl/stages?id=eq.$stageId",
            $apiKey,
            ['status' => $statusValue]
        );
        if (!$update['ok']) {
            $_SESSION['error'] = supabaseRestErrorMessage($update, 'Mise à jour du statut impossible.');
            redirectGestionOffres();
        }
        $_SESSION['result'] = 'Statut de l\'offre mis à jour.';
        redirectGestionOffres();
    }
}

$filiereFilter = trim((string) ($_GET['filiere'] ?? ''));
$durationMin = (int) ($_GET['duration_min'] ?? 0);
$durationMax = (int) ($_GET['duration_max'] ?? 0);
$missionFilter = trim((string) ($_GET['mission'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$keyword = trim((string) ($_GET['q'] ?? ''));

$stagesResult = supabaseRestRequest('GET', "$baseUrl/stages?select=*&order=created_at.desc", $apiKey);
$allStages = is_array($stagesResult['data']) ? $stagesResult['data'] : [];

$missionsResult = supabaseRestRequest('GET', "$baseUrl/missions?select=*", $apiKey);
$allMissions = is_array($missionsResult['data']) ? $missionsResult['data'] : [];
$missionsByStage = [];
foreach ($allMissions as $mission) {
    $missionsByStage[(int) ($mission['stage_id'] ?? 0)][] = $mission;
}

$candidaturesResult = supabaseRestRequest('GET', "$baseUrl/candidatures?select=stage_id", $apiKey);
$candidatureCounts = [];
foreach ((is_array($candidaturesResult['data']) ? $candidaturesResult['data'] : []) as $c) {
    $sid = (int) ($c['stage_id'] ?? 0);
    $candidatureCounts[$sid] = ($candidatureCounts[$sid] ?? 0) + 1;
}

$filieres = [];
foreach ($allStages as $stage) {
    $f = (string) ($stage['filiere'] ?? '');
    if ($f !== '' && !in_array($f, $filieres, true)) {
        $filieres[] = $f;
    }
}
sort($filieres);

$filteredStages = [];
foreach ($allStages as $stage) {
    if ($filiereFilter !== '' && (string) ($stage['filiere'] ?? '') !== $filiereFilter) {
        continue;
    }
    $weeks = (int) ($stage['duration_weeks'] ?? 0);
    if ($durationMin > 0 && $weeks < $durationMin) {
        continue;
    }
    if ($durationMax > 0 && $weeks > $durationMax) {
        continue;
    }
    if ($statusFilter !== '' && (string) ($stage['status'] ?? '') !== $statusFilter) {
        continue;
    }
    if ($keyword !== '') {
        $haystack = strtolower(($stage['title'] ?? '') . ' ' . ($stage['description'] ?? '') . ' ' . ($stage['company'] ?? '') . ' ' . ($stage['location'] ?? ''));
        if (strpos($haystack, strtolower($keyword)) === false) {
            continue;
        }
    }
    if ($missionFilter !== '') {
        $stageMissions = $missionsByStage[(int) ($stage['id'] ?? 0)] ?? [];
        $found = false;
        foreach ($stageMissions as $m) {
            $combined = strtolower(($m['title'] ?? '') . ' ' . ($m['description'] ?? ''));
            if (strpos($combined, strtolower($missionFilter)) !== false) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            continue;
        }
    }

    $filteredStages[] = $stage;
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="card mes-offres-hero">
    <h2>Diffusion des offres de stages</h2>
    <p>Filtrez les offres par filière, durée, missions ou mots-clés. Mettez à jour leur statut pour favoriser leur diffusion.</p>
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
    <h3>Recherche d'offres</h3>
    <form method="GET" class="grid-container" style="margin-top: 1rem;">
        <div class="form-group">
            <label class="form-label" for="q">Mot-clé</label>
            <input class="form-control" type="text" id="q" name="q" value="<?php echo htmlspecialchars($keyword); ?>" placeholder="Titre, description, entreprise...">
        </div>
        <div class="form-group">
            <label class="form-label" for="filiere">Filière</label>
            <select class="form-control" id="filiere" name="filiere">
                <option value="">Toutes</option>
                <?php foreach ($filieres as $f): ?>
                    <option value="<?php echo htmlspecialchars($f); ?>" <?php echo $filiereFilter === $f ? 'selected' : ''; ?>><?php echo htmlspecialchars($f); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label" for="duration_min">Durée min. (semaines)</label>
            <input class="form-control" type="number" id="duration_min" name="duration_min" min="0" value="<?php echo $durationMin > 0 ? $durationMin : ''; ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="duration_max">Durée max. (semaines)</label>
            <input class="form-control" type="number" id="duration_max" name="duration_max" min="0" value="<?php echo $durationMax > 0 ? $durationMax : ''; ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="mission">Mission contient</label>
            <input class="form-control" type="text" id="mission" name="mission" value="<?php echo htmlspecialchars($missionFilter); ?>" placeholder="Ex: tableau de bord">
        </div>
        <div class="form-group">
            <label class="form-label" for="status">Statut</label>
            <select class="form-control" id="status" name="status">
                <option value="">Tous</option>
                <option value="ouverte" <?php echo $statusFilter === 'ouverte' ? 'selected' : ''; ?>>Ouverte</option>
                <option value="en cours" <?php echo $statusFilter === 'en cours' ? 'selected' : ''; ?>>En cours</option>
                <option value="fermée" <?php echo $statusFilter === 'fermée' ? 'selected' : ''; ?>>Fermée</option>
                <option value="archivée" <?php echo $statusFilter === 'archivée' ? 'selected' : ''; ?>>Archivée</option>
            </select>
        </div>
        <div class="form-group" style="display: flex; gap: 0.6rem; align-items: flex-end;">
            <button type="submit" class="btn btn-primary">Filtrer</button>
            <a href="gestionOffres.php" class="btn btn-secondary">Réinitialiser</a>
        </div>
    </form>
</div>

<div class="card">
    <h3>Résultats : <?php echo count($filteredStages); ?> offre(s)</h3>
</div>

<?php if (empty($filteredStages)): ?>
    <div class="card"><p>Aucune offre ne correspond aux filtres.</p></div>
<?php else: ?>
    <div class="grid-container">
        <?php foreach ($filteredStages as $stage): ?>
            <?php
                $sid = (int) ($stage['id'] ?? 0);
                $status = (string) ($stage['status'] ?? 'ouverte');
                $stageMissions = $missionsByStage[$sid] ?? [];
                $candidatureCount = $candidatureCounts[$sid] ?? 0;
            ?>
            <div class="card offre-card">
                <h3 class="offre-title"><?php echo htmlspecialchars($stage['title'] ?? 'Stage'); ?></h3>
                <p class="offre-company"><?php echo htmlspecialchars($stage['company'] ?? 'Entreprise'); ?></p>
                <p class="offre-desc"><?php echo nl2br(htmlspecialchars($stage['description'] ?? '')); ?></p>

                <div class="offre-meta">
                    <p><strong>Filière :</strong> <?php echo htmlspecialchars($stage['filiere'] ?? '—'); ?></p>
                    <p><strong>Lieu :</strong> <?php echo htmlspecialchars($stage['location'] ?? '—'); ?></p>
                    <p><strong>Durée :</strong> <?php echo (int) ($stage['duration_weeks'] ?? 0); ?> semaine(s)</p>
                    <p><strong>Du</strong> <?php echo htmlspecialchars($stage['start_date'] ?? '—'); ?> <strong>au</strong> <?php echo htmlspecialchars($stage['end_date'] ?? '—'); ?></p>
                    <p><strong>Statut :</strong> <span class="badge <?php echo $status === 'archivée' ? 'badge-pending' : ($status === 'fermée' ? 'badge-progress' : 'badge-valid'); ?>"><?php echo htmlspecialchars($status); ?></span></p>
                    <p><strong>Candidatures :</strong> <?php echo $candidatureCount; ?></p>
                </div>

                <?php if (!empty($stageMissions)): ?>
                    <div style="margin-top: 0.75rem;">
                        <strong>Missions :</strong>
                        <ul style="margin-top: 0.4rem; padding-left: 1.2rem; color: var(--text-secondary);">
                            <?php foreach ($stageMissions as $m): ?>
                                <li><?php echo htmlspecialchars($m['title'] ?? ''); ?><?php if (!empty($m['description'])): ?> — <?php echo htmlspecialchars($m['description']); ?><?php endif; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div style="display: flex; gap: 0.6rem; flex-wrap: wrap; margin-top: 1rem;">
                    <form method="POST" style="display: flex; gap: 0.4rem; align-items: center;">
                        <input type="hidden" name="action" value="publish">
                        <input type="hidden" name="stage_id" value="<?php echo $sid; ?>">
                        <select name="status" class="form-control" style="width:auto; padding:0.3rem 0.5rem;">
                            <option value="ouverte" <?php echo $status === 'ouverte' ? 'selected' : ''; ?>>Ouverte</option>
                            <option value="en cours" <?php echo $status === 'en cours' ? 'selected' : ''; ?>>En cours</option>
                            <option value="fermée" <?php echo $status === 'fermée' ? 'selected' : ''; ?>>Fermée</option>
                            <option value="archivée" <?php echo $status === 'archivée' ? 'selected' : ''; ?>>Archivée</option>
                        </select>
                        <button type="submit" class="btn btn-secondary" style="padding:0.3rem 0.75rem; font-size:0.85rem;">Mettre à jour</button>
                    </form>
                    <form method="POST" onsubmit="return confirm('Supprimer définitivement cette offre et toutes ses données associées ?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="stage_id" value="<?php echo $sid; ?>">
                        <button type="submit" class="btn" style="padding:0.3rem 0.75rem; font-size:0.85rem; background:var(--danger-color); color:white;">Supprimer</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="mes-offres-actions">
    <a class="btn btn-secondary mt-4" href="accueilAdmin.php">Retour à l'accueil</a>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
