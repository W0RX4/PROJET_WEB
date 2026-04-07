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
 

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $userId = (int) ($_POST['user_id'] ?? 0);
 
    if ($action === 'delete' && $userId) {
        $ch = curl_init("$baseUrl/users?id=eq.$userId");
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
        $code >= 200 && $code < 300 ? $successMsg = 'Compte supprimé.' : $errorMsg = 'Erreur lors de la suppression.';
 
    } elseif ($action === 'update_type' && $userId) {
        $newType = $_POST['type'] ?? '';
        $allowed = ['etudiant', 'entreprise', 'tuteur', 'jury', 'admin'];
        if (!in_array($newType, $allowed)) {
            $errorMsg = 'Type invalide.';
        } else {
            $ch = curl_init("$baseUrl/users?id=eq.$userId");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST  => 'PATCH',
                CURLOPT_POSTFIELDS     => json_encode(['type' => $newType]),
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
            $code >= 200 && $code < 300 ? $successMsg = 'Type mis à jour.' : $errorMsg = 'Erreur lors de la mise à jour.';
        }
    }
}
 
// ── RÉCUPÉRATION DES USERS ───────────────────────────────────────────────────
$ch = curl_init("$baseUrl/users?select=id,email,username,type,created_at&order=created_at.desc");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'apikey: ' . $apiKey,
        'Authorization: Bearer ' . $apiKey,
        'Accept: application/json',
    ],
]);
$users = json_decode(curl_exec($ch), true) ?? [];
curl_close($ch);
 
$typesLabels = [
    'etudiant'   => 'Étudiant',
    'entreprise' => 'Entreprise',
    'tuteur'     => 'Tuteur',
    'jury'       => 'Jury',
    'admin'      => 'Admin',
];
?>
 
<div class="card">
    <h2>Gestion des comptes</h2>
 
    <?php if ($successMsg): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($successMsg); ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($errorMsg); ?></div>
    <?php endif; ?>
 
    <?php if (empty($users)): ?>
        <p>Aucun compte trouvé.</p>
    <?php else: ?>
        <table style="width:100%; border-collapse:collapse; margin-top:1rem;">
            <thead>
                <tr style="border-bottom:2px solid var(--border-color); text-align:left;">
                    <th style="padding:0.75rem;">ID</th>
                    <th style="padding:0.75rem;">Nom d'utilisateur</th>
                    <th style="padding:0.75rem;">Email</th>
                    <th style="padding:0.75rem;">Type</th>
                    <th style="padding:0.75rem;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <?php $isSelf = ($user['id'] === ($_SESSION['user_id'] ?? null)); ?>
                    <tr style="border-bottom:1px solid var(--border-color);">
                        <td style="padding:0.75rem;"><?php echo $user['id']; ?></td>
                        <td style="padding:0.75rem;"><?php echo htmlspecialchars($user['username'] ?? '—'); ?></td>
                        <td style="padding:0.75rem;"><?php echo htmlspecialchars($user['email'] ?? '—'); ?></td>
                        <td style="padding:0.75rem;"><?php echo $typesLabels[$user['type']] ?? $user['type']; ?></td>
                        <td style="padding:0.75rem; display:flex; gap:0.5rem; align-items:center;">
                            <form method="POST" style="display:flex; gap:0.4rem; align-items:center;">
                                <input type="hidden" name="action" value="update_type">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <select name="type" class="form-control" style="width:auto; padding:0.3rem 0.5rem;">
                                    <?php foreach ($typesLabels as $val => $label): ?>
                                        <option value="<?php echo $val; ?>" <?php echo ($user['type'] === $val) ? 'selected' : ''; ?>>
                                            <?php echo $label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-secondary" style="padding:0.3rem 0.75rem; font-size:0.85rem;">Modifier</button>
                            </form>
                            <?php if (!$isSelf): ?>
                                <form method="POST" onsubmit="return confirm('Supprimer ce compte ?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" class="btn" style="padding:0.3rem 0.75rem; font-size:0.85rem; background:var(--danger-color); color:white;">Supprimer</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
 
<?php require_once '../../includes/footer.php'; ?>