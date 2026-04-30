<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['type']) || $_SESSION['type'] !== 'admin') {
    header('Location: /login');
    exit;
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../supabaseQuery/authClient.php';
require_once __DIR__ . '/../../supabaseQuery/addUserSupabase.php';

use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->safeLoad();
 
$apiKey  = $_ENV['SUPABASE_KEY'] ?? '';
$baseUrl = rtrim($_ENV['SUPABASE_URL'], '/') . '/rest/v1';
 
$successMsg = '';
$errorMsg   = '';
 
function callSupabase(string $method, string $endpoint, string $apiKey, ?array $payload = null): array
{
    $ch = curl_init($endpoint);
    $headers = [
        'apikey: ' . $apiKey,
        'Authorization: Bearer ' . $apiKey,
        'Accept: application/json',
    ];
 
    if ($payload !== null) {
        $headers[] = 'Content-Type: application/json';
    }
 
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
 
    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }
 
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
 
    $data = json_decode($response ?: '', true);
 
    return [
        'ok' => $curlError === '' && $code >= 200 && $code < 300,
        'code' => $code,
        'error' => $curlError,
        'data' => $data,
        'raw' => $response ?: '',
    ];
}
 
function getSupabaseErrorMessage(array $result, string $fallback): string
{
    if (!empty($result['error'])) {
        return $fallback . ' (' . $result['error'] . ')';
    }
 
    if (is_array($result['data'])) {
        $details = $result['data']['message']
            ?? $result['data']['details']
            ?? $result['data']['hint']
            ?? null;
 
        if (is_string($details) && $details !== '') {
            return $fallback . ' (' . $details . ')';
        }
    }
 
    return $fallback;
}
 
function fetchPlatformUserById(int $userId, string $baseUrl, string $apiKey): ?array
{
    $result = callSupabase('GET', "$baseUrl/users?id=eq.$userId&select=id,email,username,type&limit=1", $apiKey);
    $users = is_array($result['data']) ? $result['data'] : [];
    return $users[0] ?? null;
}
 
// ── SUPPRESSION ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $userId = (int) ($_POST['user_id'] ?? 0);
 
    if ($action === 'delete' && $userId) {
        $currentUserId = (int) ($_SESSION['user_id'] ?? 0);
 
        if ($userId === $currentUserId) {
            $errorMsg = 'Vous ne pouvez pas supprimer votre propre compte.';
        } else {
            $targetUser = fetchPlatformUserById($userId, $baseUrl, $apiKey);
 
            if (!$targetUser) {
                $errorMsg = 'Compte introuvable.';
            }
 
            // 🔒 Bloquer la suppression d'un autre administrateur
            if ($errorMsg === '' && ($targetUser['type'] ?? '') === 'admin') {
                $errorMsg = 'Vous ne pouvez pas supprimer un autre administrateur.';
            }
 
            // Nettoie d'abord les relations qui bloquent la suppression SQL.
            $cleanupSteps = $errorMsg === '' ? [
                [
                    'method' => 'DELETE',
                    'url' => "$baseUrl/candidatures?student_id=eq.$userId",
                    'payload' => null,
                    'message' => 'Erreur lors de la suppression des candidatures associées.',
                ],
                [
                    'method' => 'PATCH',
                    'url' => "$baseUrl/stages?student_id=eq.$userId",
                    'payload' => ['student_id' => null],
                    'message' => 'Erreur lors du détachement des stages de l\'étudiant.',
                ],
                [
                    'method' => 'PATCH',
                    'url' => "$baseUrl/stages?tutor_id=eq.$userId",
                    'payload' => ['tutor_id' => null],
                    'message' => 'Erreur lors du détachement des stages du tuteur.',
                ],
                [
                    'method' => 'PATCH',
                    'url' => "$baseUrl/users?id=eq.$userId",
                    'payload' => ['stage_id' => null],
                    'message' => 'Erreur lors du nettoyage du compte.',
                ],
            ] : [];
 
            foreach ($cleanupSteps as $step) {
                $result = callSupabase($step['method'], $step['url'], $apiKey, $step['payload']);
                if (!$result['ok']) {
                    $errorMsg = getSupabaseErrorMessage($result, $step['message']);
                    break;
                }
            }
 
            if ($errorMsg === '') {
                $deleteResult = callSupabase('DELETE', "$baseUrl/users?id=eq.$userId", $apiKey);
                if ($deleteResult['ok']) {
                    $authUser = supabaseAuthAdminFindUserByEmail((string) ($targetUser['email'] ?? ''));
                    if ($authUser && !empty($authUser['id'])) {
                        $authDelete = supabaseAuthAdminDeleteUser((string) $authUser['id']);
                        if (!$authDelete['ok']) {
                            $errorMsg = supabaseAuthErrorMessage($authDelete, 'Le profil applicatif a ete supprime, mais pas le compte Supabase Auth.');
                        }
                    }
 
                    if ($errorMsg === '') {
                        $successMsg = 'Compte supprime dans le profil applicatif et dans Supabase Auth.';
                    }
                } else {
                    $errorMsg = getSupabaseErrorMessage($deleteResult, 'Erreur lors de la suppression du compte.');
                }
            }
        }
 
    } elseif ($action === 'create') {
        $newEmail = trim((string) ($_POST['new_email'] ?? ''));
        $newUsername = trim((string) ($_POST['new_username'] ?? ''));
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $newType = (string) ($_POST['new_type'] ?? 'etudiant');

        $createResult = addUserSupabase($newEmail, $newUsername, $newPassword, $newType);

        if (is_array($createResult) && isset($createResult['code']) && !isset($createResult['id'])) {
            $errorMsg = (string) ($createResult['message'] ?? 'Création impossible.');
        } else {
            $successMsg = 'Compte créé avec succès dans Supabase Auth et le profil applicatif.';
        }

    } elseif ($action === 'update_type' && $userId) {
        $newType = $_POST['type'] ?? '';
        $allowed = ['etudiant', 'entreprise', 'tuteur', 'jury', 'admin'];
        if (!in_array($newType, $allowed)) {
            $errorMsg = 'Type invalide.';
        } else {
            $targetUser = fetchPlatformUserById($userId, $baseUrl, $apiKey);
            if (!$targetUser) {
                $errorMsg = 'Compte introuvable.';
            }
 
            // 🔒 Bloquer la modification du type d'un autre administrateur
            if ($errorMsg === '' && ($targetUser['type'] ?? '') === 'admin' && $userId !== (int) ($_SESSION['user_id'] ?? 0)) {
                $errorMsg = 'Vous ne pouvez pas modifier le type d\'un autre administrateur.';
            }
 
            if ($errorMsg === '') {
                $updateResult = callSupabase('PATCH', "$baseUrl/users?id=eq.$userId", $apiKey, ['type' => $newType]);
 
                if (!$updateResult['ok']) {
                    $errorMsg = getSupabaseErrorMessage($updateResult, 'Erreur lors de la mise à jour.');
                }
            }
 
            if ($errorMsg === '' && $targetUser) {
                $authUser = supabaseAuthAdminFindUserByEmail((string) ($targetUser['email'] ?? ''));
 
                if ($authUser && !empty($authUser['id'])) {
                    $authUpdate = supabaseAuthAdminUpdateUser((string) $authUser['id'], [
                        'user_metadata' => [
                            'username' => (string) ($targetUser['username'] ?? ''),
                            'type' => $newType,
                        ],
                        'app_metadata' => [
                            'type' => $newType,
                            'username' => (string) ($targetUser['username'] ?? ''),
                        ],
                    ]);
 
                    if (!$authUpdate['ok']) {
                        $errorMsg = supabaseAuthErrorMessage($authUpdate, 'Le type a ete mis a jour dans la table users, mais pas dans Supabase Auth.');
                    }
                }
 
                if ($errorMsg === '') {
                    $successMsg = 'Type mis a jour dans le profil applicatif et dans Supabase Auth.';
                }
            }
        }
    }
}
 
// ── RÉCUPÉRATION DES USERS ───────────────────────────────────────────────────
$usersResult = callSupabase('GET', "$baseUrl/users?select=id,email,username,type,created_at&order=created_at.desc", $apiKey);
$users = is_array($usersResult['data']) ? $usersResult['data'] : [];
 
if (!$usersResult['ok'] && $errorMsg === '') {
    $errorMsg = getSupabaseErrorMessage($usersResult, 'Impossible de charger la liste des comptes.');
}
 
$typesLabels = [
    'etudiant'   => 'Étudiant',
    'entreprise' => 'Entreprise',
    'tuteur'     => 'Tuteur',
    'jury'       => 'Jury',
    'admin'      => 'Admin',
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="card">
    <h2>Gestion des comptes</h2>

    <?php if ($successMsg): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($successMsg); ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($errorMsg); ?></div>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Ajouter un compte</h3>
    <form method="POST" class="grid-container">
        <input type="hidden" name="action" value="create">
        <div class="form-group">
            <label class="form-label" for="new_username">Nom d'utilisateur</label>
            <input class="form-control" type="text" id="new_username" name="new_username" required>
        </div>
        <div class="form-group">
            <label class="form-label" for="new_email">Email</label>
            <input class="form-control" type="email" id="new_email" name="new_email" required>
        </div>
        <div class="form-group">
            <label class="form-label" for="new_password">Mot de passe initial</label>
            <input class="form-control" type="text" id="new_password" name="new_password" required minlength="6">
        </div>
        <div class="form-group">
            <label class="form-label" for="new_type">Niveau d'accès</label>
            <select class="form-control" id="new_type" name="new_type">
                <option value="etudiant">Étudiant</option>
                <option value="entreprise">Entreprise</option>
                <option value="tuteur">Tuteur</option>
                <option value="jury">Jury</option>
                <option value="admin">Administrateur</option>
            </select>
        </div>
        <div class="form-group" style="display: flex; align-items: flex-end;">
            <button type="submit" class="btn btn-primary">Créer le compte</button>
        </div>
    </form>
</div>

<div class="card">
    <h3>Comptes existants</h3>

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
                    <?php
                        $isSelf = (int) ($user['id'] ?? 0) === (int) ($_SESSION['user_id'] ?? 0);
                        $isOtherAdmin = ($user['type'] ?? '') === 'admin' && !$isSelf;
                    ?>
                    <tr style="border-bottom:1px solid var(--border-color);">
                        <td style="padding:0.75rem;"><?php echo $user['id']; ?></td>
                        <td style="padding:0.75rem;"><?php echo htmlspecialchars($user['username'] ?? '—'); ?></td>
                        <td style="padding:0.75rem;"><?php echo htmlspecialchars($user['email'] ?? '—'); ?></td>
                        <td style="padding:0.75rem;"><?php echo $typesLabels[$user['type']] ?? $user['type']; ?></td>
                        <td style="padding:0.75rem; display:flex; gap:0.5rem; align-items:center;">
                            <?php if (!$isOtherAdmin): ?>
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
                            <?php else: ?>
                                <span style="color: var(--text-secondary); font-size:0.85rem; font-style:italic;">Administrateur</span>
                            <?php endif; ?>
                            <?php if (!$isSelf && !$isOtherAdmin): ?>
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