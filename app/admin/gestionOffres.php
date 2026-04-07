<?php
require_once '../../includes/header.php';
 
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
 
// ── SUPPRESSION ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action  = $_POST['action'];
    $stageId = (int) ($_POST['stage_id'] ?? 0);
 
    if ($action === 'delete' && $stageId) {
        $ch = curl_init("$baseUrl/stages?id=eq.$stageId");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_HTTPHEADER     => [
                'apikey: ' . $apiKey,
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $code >= 200 && $code < 300
            ? $successMsg = 'Offre supprimée avec succès.'
            : $errorMsg   = 'Erreur lors de la suppression.';
 
    // ── MODIFICATION DU STATUT ────────────────────────────────────────────────
    } elseif ($action === 'update_status' && $stageId) {
        $newStatus = $_POST['status'] ?? '';
        $allowed   = ['ouverte', 'fermée', 'pourvue'];
        if (!in_array($newStatus, $allowed)) {
            $errorMsg = 'Statut invalide.';
        } else {
            $ch = curl_init("$baseUrl/stages?id=eq.$stageId");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST  => 'PATCH',
                CURLOPT_POSTFIELDS     => json_encode(['status' => $newStatus]),
                CURLOPT_HTTPHEADER     => [
                    'apikey: ' . $apiKey,
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json',
                    'Prefer: return=minimal',
                ],
            ]);
            curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $code >= 200 && $code < 300
                ? $successMsg = 'Statut mis à jour.'
                : $errorMsg   = 'Erreur lors de la mise à jour.';
        }
    }
}
 
// ── RÉCUPÉRATION DES OFFRES ───────────────────────────────────────────────────
$ch = curl_init("$baseUrl/stages?select=id,title,company,filiere,location,start_date,end_date,status,duration_weeks&order=created_at.desc");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'apikey: ' . $apiKey,
        'Authorization: Bearer ' . $apiKey,
        'Accept: application/json',
    ],
]);
$stages = json_decode(curl_exec($ch), true) ?? [];
curl_close($ch);
 
$statusLabels = [
    'ouverte'  => 'Ouverte',
    'fermée'   => 'Fermée',
    'pourvue'  => 'Pourvue',
];
?>
 
<div class="card">
    <h2>Gestion des offres de stage</h2>
    <p><?php echo count($stages); ?> offre(s) au total</p>
 
    <?php if ($successMsg): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($successMsg); ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($errorMsg); ?></div>
    <?php endif; ?>
 
    <?php if (empty($stages)): ?>
        <p>Aucune offre de stage trouvée.</p>
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
                        <th style="padding:0.75rem;">Durée</th>
                        <th style="padding:0.75rem;">Dates</th>
                        <th style="padding:0.75rem;">Statut</th>
                        <th style="padding:0.75rem;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stages as $stage): ?>
                        <tr style="border-bottom:1px solid var(--border-color);">
                            <td style="padding:0.75rem; color:var(--text-secondary); font-size:0.85rem;"><?php echo $stage['id']; ?></td>
                            <td style="padding:0.75rem;"><strong><?php echo htmlspecialchars($stage['title'] ?? '—'); ?></strong></td>
                            <td style="padding:0.75rem;"><?php echo htmlspecialchars($stage['company'] ?? '—'); ?></td>
                            <td style="padding:0.75rem;"><?php echo htmlspecialchars($stage['filiere'] ?? '—'); ?></td>
                            <td style="padding:0.75rem;"><?php echo htmlspecialchars($stage['location'] ?? '—'); ?></td>
                            <td style="padding:0.75rem;">
                                <?php echo $stage['duration_weeks'] ? $stage['duration_weeks'] . ' sem.' : '—'; ?>
                            </td>
                            <td style="padding:0.75rem; font-size:0.85rem; color:var(--text-secondary);">
                                <?php
                                $start = $stage['start_date'] ? date('d/m/Y', strtotime($stage['start_date'])) : '—';
                                $end   = $stage['end_date']   ? date('d/m/Y', strtotime($stage['end_date']))   : '—';
                                echo "$start → $end";
                                ?>
                            </td>
                            <td style="padding:0.75rem;">
                                <?php echo $statusLabels[$stage['status']] ?? ($stage['status'] ?? '—'); ?>
                            </td>
                            <td style="padding:0.75rem;">
                                <div style="display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">
                                    <!-- Modifier le statut -->
                                    <form method="POST" action="/app/admin/gestionOffres.php" style="display:flex; gap:0.4rem; align-items:center;">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="stage_id" value="<?php echo $stage['id']; ?>">
                                        <select name="status" class="form-control" style="width:auto; padding:0.3rem 0.5rem;">
                                            <?php foreach ($statusLabels as $val => $label): ?>
                                                <option value="<?php echo $val; ?>" <?php echo ($stage['status'] === $val) ? 'selected' : ''; ?>>
                                                    <?php echo $label; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-secondary" style="padding:0.3rem 0.75rem; font-size:0.85rem;">Modifier</button>
                                    </form>
                                    <!-- Supprimer -->
                                    <form method="POST" action="/app/admin/gestionOffres.php" onsubmit="return confirm('Supprimer cette offre ?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="stage_id" value="<?php echo $stage['id']; ?>">
                                        <button type="submit" class="btn" style="padding:0.3rem 0.75rem; font-size:0.85rem; background:var(--danger-color); color:white;">Supprimer</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
 
<?php require_once '../../includes/footer.php'; ?>
 