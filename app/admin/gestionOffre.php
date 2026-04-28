<?php
require_once '../../includes/header.php';
require_once __DIR__ . '/../../supabaseQuery/restClient.php';
 
if ($_SESSION['type'] !== 'admin') {
    header('Location: /login');
    exit;
}
 
require_once __DIR__ . '/../../vendor/autoload.php';
use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->safeLoad();
 
$apiKey  = $_ENV['SUPABASE_KEY'] ?? '';
$baseUrl = rtrim($_ENV['SUPABASE_URL'], '/') . '/rest/v1';
 
$successMsg = '';
$errorMsg   = '';
 
// ── ACTIONS POST ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['stage_id'])) {
    $action  = $_POST['action'];
    $stageId = (int) $_POST['stage_id'];
 
    if ($stageId <= 0) {
        $errorMsg = 'Identifiant d\'offre invalide.';
    } elseif ($action === 'validate') {
        $result = supabaseRestRequest(
            'PATCH',
            "$baseUrl/stages?id=eq.$stageId",
            $apiKey,
            ['status' => 'ouverte']
        );
        if ($result['ok']) {
            $successMsg = 'Offre validée avec succès.';
        } else {
            $errorMsg = supabaseRestErrorMessage($result, 'Erreur lors de la validation de l\'offre.');
        }
 
    } elseif ($action === 'reject') {
        $result = supabaseRestRequest(
            'PATCH',
            "$baseUrl/stages?id=eq.$stageId",
            $apiKey,
            ['status' => 'rejetée']
        );
        if ($result['ok']) {
            $successMsg = 'Offre rejetée.';
        } else {
            $errorMsg = supabaseRestErrorMessage($result, 'Erreur lors du rejet de l\'offre.');
        }
 
    } elseif ($action === 'delete') {
        // Supprimer les candidatures liées
        $delCandidatures = supabaseRestRequest('DELETE', "$baseUrl/candidatures?stage_id=eq.$stageId", $apiKey);
        if (!$delCandidatures['ok']) {
            $errorMsg = supabaseRestErrorMessage($delCandidatures, 'Erreur lors de la suppression des candidatures liées.');
        }
 
        // Supprimer les missions liées
        if ($errorMsg === '') {
            $delMissions = supabaseRestRequest('DELETE', "$baseUrl/missions?stage_id=eq.$stageId", $apiKey);
            if (!$delMissions['ok']) {
                $errorMsg = supabaseRestErrorMessage($delMissions, 'Erreur lors de la suppression des missions liées.');
            }
        }
 
        // Supprimer les documents liés
        if ($errorMsg === '') {
            $delDocuments = supabaseRestRequest('DELETE', "$baseUrl/documents?stage_id=eq.$stageId", $apiKey);
            if (!$delDocuments['ok']) {
                $errorMsg = supabaseRestErrorMessage($delDocuments, 'Erreur lors de la suppression des documents liés.');
            }
        }
        if ($errorMsg === '') {
                $delConventions = supabaseRestRequest('DELETE', "$baseUrl/conventions?stage_id=eq.$stageId", $apiKey);
                if (!$delConventions['ok']) {
                    $errorMsg = supabaseRestErrorMessage($delConventions, 'Erreur lors de la suppression des conventions liées.');
                }
            }
        // Supprimer le stage
        if ($errorMsg === '') {
            $result = supabaseRestRequest('DELETE', "$baseUrl/stages?id=eq.$stageId", $apiKey);
            if ($result['ok']) {
                $successMsg = 'Offre supprimée avec succès.';
            } else {
                $errorMsg = supabaseRestErrorMessage($result, 'Erreur lors de la suppression de l\'offre.');
            }
        }
 
    } else {
        $errorMsg = 'Action non reconnue.';
    }
}
 
// ── FILTRE PAR STATUT ─────────────────────────────────────────────────────────
$filterStatus = $_GET['status'] ?? 'all';
$allowedFilters = ['all', 'en attente', 'ouverte', 'rejetée', 'fermée'];
if (!in_array($filterStatus, $allowedFilters)) {
    $filterStatus = 'all';
}
 
$url = "$baseUrl/stages?select=id,title,company,company_id,location,filiere,start_date,end_date,duration_weeks,status,created_at&order=created_at.desc";
if ($filterStatus !== 'all') {
    $url .= '&status=eq.' . urlencode($filterStatus);
}
 
$stagesResult = supabaseRestRequest('GET', $url, $apiKey);
$stages = is_array($stagesResult['data']) ? $stagesResult['data'] : [];
 
if (!$stagesResult['ok'] && $errorMsg === '') {
    $errorMsg = supabaseRestErrorMessage($stagesResult, 'Impossible de charger les offres.');
}
 
$statusLabels = [
    'en attente' => ['label' => 'En attente', 'color' => '#f59e0b'],
    'ouverte'    => ['label' => 'Validée',     'color' => '#10b981'],
    'rejetée'    => ['label' => 'Rejetée',     'color' => '#ef4444'],
    'fermée'     => ['label' => 'Fermée',      'color' => '#6b7280'],
];
?>
 
<div class="card">
    <h2>Gestion des offres de stage</h2>
    <p style="color: var(--text-secondary);">Validez, rejetez ou supprimez les offres déposées par les entreprises.</p>
</div>
 
<?php if ($successMsg): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($successMsg); ?></div>
<?php endif; ?>
<?php if ($errorMsg): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($errorMsg); ?></div>
<?php endif; ?>
 
<!-- Filtres -->
<div class="card" style="display:flex; gap:0.5rem; flex-wrap:wrap; align-items:center;">
    <span style="color:var(--text-secondary); font-size:0.9rem; font-weight:500;">Filtrer :</span>
    <?php
    $filterOptions = [
        'all'        => 'Toutes',
        'en attente' => 'En attente',
        'ouverte'    => 'Validées',
        'rejetée'    => 'Rejetées',
        'fermée'     => 'Fermées',
    ];
    foreach ($filterOptions as $val => $label):
        $isActive = $filterStatus === $val;
    ?>
        <a
            href="?status=<?php echo urlencode($val); ?>"
            class="btn <?php echo $isActive ? 'btn-primary' : 'btn-secondary'; ?>"
            style="padding:0.3rem 0.9rem; font-size:0.85rem;"
        ><?php echo $label; ?></a>
    <?php endforeach; ?>
</div>
 
<?php if (empty($stages)): ?>
    <div class="card" style="text-align:center; color:var(--text-secondary);">
        <p>Aucune offre trouvée<?php echo $filterStatus !== 'all' ? ' pour ce filtre' : ''; ?>.</p>
    </div>
<?php else: ?>
    <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; margin-top:1rem;">
            <thead>
                <tr style="border-bottom:2px solid var(--border-color); text-align:left;">
                    <th style="padding:0.75rem;">ID</th>
                    <th style="padding:0.75rem;">Titre</th>
                    <th style="padding:0.75rem;">Entreprise</th>
                    <th style="padding:0.75rem;">Filière</th>
                    <th style="padding:0.75rem;">Lieu</th>
                    <th style="padding:0.75rem;">Dates</th>
                    <th style="padding:0.75rem;">Durée</th>
                    <th style="padding:0.75rem;">Statut</th>
                    <th style="padding:0.75rem;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stages as $stage):
                    $statusInfo = $statusLabels[$stage['status'] ?? ''] ?? ['label' => $stage['status'] ?? '—', 'color' => '#6b7280'];
                ?>
                    <tr style="border-bottom:1px solid var(--border-color);">
                        <td style="padding:0.75rem;"><?php echo (int) $stage['id']; ?></td>
                        <td style="padding:0.75rem; font-weight:500;"><?php echo htmlspecialchars($stage['title'] ?? '—'); ?></td>
                        <td style="padding:0.75rem;"><?php echo htmlspecialchars($stage['company'] ?? '—'); ?></td>
                        <td style="padding:0.75rem;"><?php echo htmlspecialchars($stage['filiere'] ?? '—'); ?></td>
                        <td style="padding:0.75rem;"><?php echo htmlspecialchars($stage['location'] ?? '—'); ?></td>
                        <td style="padding:0.75rem; white-space:nowrap;">
                            <?php echo htmlspecialchars($stage['start_date'] ?? 'N/A'); ?>
                            →
                            <?php echo htmlspecialchars($stage['end_date'] ?? 'N/A'); ?>
                        </td>
                        <td style="padding:0.75rem; text-align:center;"><?php echo (int) ($stage['duration_weeks'] ?? 0); ?> sem.</td>
                        <td style="padding:0.75rem;">
                            <span style="
                                background: <?php echo $statusInfo['color']; ?>22;
                                color: <?php echo $statusInfo['color']; ?>;
                                padding: 0.2rem 0.6rem;
                                border-radius: 999px;
                                font-size: 0.8rem;
                                font-weight: 600;
                                white-space: nowrap;
                            ">
                                <?php echo htmlspecialchars($statusInfo['label']); ?>
                            </span>
                        </td>
                        <td style="padding:0.75rem;">
                            <div style="display:flex; gap:0.4rem; flex-wrap:wrap;">
 
                                <?php if (($stage['status'] ?? '') !== 'ouverte'): ?>
                                    <form method="POST" style="margin:0;">
                                        <input type="hidden" name="action" value="validate">
                                        <input type="hidden" name="stage_id" value="<?php echo (int) $stage['id']; ?>">
                                        <button type="submit" class="btn btn-primary" style="padding:0.3rem 0.75rem; font-size:0.8rem;">
                                        Valider
                                        </button>
                                    </form>
                                <?php endif; ?>
 
                                <?php if (($stage['status'] ?? '') !== 'rejetée'): ?>
                                    <form method="POST" style="margin:0;">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="stage_id" value="<?php echo (int) $stage['id']; ?>">
                                        <button type="submit" class="btn btn-secondary" style="padding:0.3rem 0.75rem; font-size:0.8rem;">
                                        Rejeter
                                        </button>
                                    </form>
                                <?php endif; ?>
 
                                <form method="POST" style="margin:0;" onsubmit="return confirm('Supprimer définitivement cette offre et ses candidatures ?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="stage_id" value="<?php echo (int) $stage['id']; ?>">
                                    <button type="submit" class="btn" style="padding:0.3rem 0.75rem; font-size:0.8rem; background:var(--danger-color); color:white;">
                                        Supprimer
                                    </button>
                                </form>
 
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
 
<?php require_once '../../includes/footer.php'; ?>