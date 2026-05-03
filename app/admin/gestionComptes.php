<?php
// Fichier qui gere les comptes utilisateurs.
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
require_once __DIR__ . '/../../supabaseQuery/authClient.php';
require_once __DIR__ . '/../../supabaseQuery/addUserSupabase.php';

// On importe les classes utilisees dans ce fichier.
use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->safeLoad();
 
$apiKey  = $_ENV['SUPABASE_KEY'] ?? '';
$baseUrl = rtrim($_ENV['SUPABASE_URL'], '/') . '/rest/v1';
 
$successMsg = '';
$errorMsg   = '';
 
// Cette fonction regroupe une action reutilisable.
function callSupabase(string $method, string $endpoint, string $apiKey, ?array $payload = null): array
{
    // On prepare ou lance la requete HTTP.
    $ch = curl_init($endpoint);
    // On prepare les donnees utilisees dans ce bloc.
    $headers = [
        'apikey: ' . $apiKey,
        'Authorization: Bearer ' . $apiKey,
        'Accept: application/json',
    ];
 
    // On verifie cette condition.
    if ($payload !== null) {
        $headers[] = 'Content-Type: application/json';
    }
 
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
 
    // On verifie cette condition.
    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }
 
    // On prepare ou lance la requete HTTP.
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
 
// Cette fonction regroupe une action reutilisable.
function getSupabaseErrorMessage(array $result, string $fallback): string
{
    // On verifie cette condition.
    if (!empty($result['error'])) {
        return $fallback . ' (' . $result['error'] . ')';
    }
 
    // On verifie cette condition.
    if (is_array($result['data'])) {
        $details = $result['data']['message']
            ?? $result['data']['details']
            ?? $result['data']['hint']
            ?? null;
 
        // On verifie cette condition.
        if (is_string($details) && $details !== '') {
            return $fallback . ' (' . $details . ')';
        }
    }
 
    return $fallback;
}
 
// Cette fonction regroupe une action reutilisable.
function fetchPlatformUserById(int $userId, string $baseUrl, string $apiKey): ?array
{
    $result = callSupabase('GET', "$baseUrl/users?id=eq.$userId&select=id,email,username,type&limit=1", $apiKey);
    $users = is_array($result['data']) ? $result['data'] : [];
    return $users[0] ?? null;
}
 
// On traite les actions sur les comptes.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $userId = (int) ($_POST['user_id'] ?? 0);
 
    // On execute l action demandee par le formulaire.
    if ($action === 'delete' && $userId) {
        $currentUserId = (int) ($_SESSION['user_id'] ?? 0);
 
        // On verifie cette condition.
        if ($userId === $currentUserId) {
            $errorMsg = 'Vous ne pouvez pas supprimer votre propre compte.';
        } else {
            $targetUser = fetchPlatformUserById($userId, $baseUrl, $apiKey);
 
            // On controle cette condition avant de continuer.
            if (!$targetUser) {
                $errorMsg = 'Compte introuvable.';
            }
 
            // On bloque la suppression d un autre administrateur.
            if ($errorMsg === '' && ($targetUser['type'] ?? '') === 'admin') {
                $errorMsg = 'Vous ne pouvez pas supprimer un autre administrateur.';
            }
 
            // Nettoie d'abord les relations qui bloquent la suppression SQL.
            // Pour un etudiant, les stages attribues sont conserves et ranges dans les archives.
            $cleanupSteps = [];
            // On gere le cas ou la valeur attendue est vide.
            if ($errorMsg === '') {
                $isStudent = ($targetUser['type'] ?? '') === 'etudiant';
                $handoverUserId = $currentUserId > 0 ? $currentUserId : null;

                $cleanupSteps[] = [
                    'method' => 'PATCH',
                    'url' => "$baseUrl/stages?student_id=eq.$userId",
                    'payload' => $isStudent ? ['student_id' => null, 'status' => 'archivée'] : ['student_id' => null],
                    'message' => $isStudent
                        ? 'Erreur lors de l\'archivage des stages de l\'étudiant.'
                        : 'Erreur lors du détachement des stages de l\'utilisateur.',
                ];

                $cleanupSteps = array_merge($cleanupSteps, [
                    [
                        'method' => 'DELETE',
                        'url' => "$baseUrl/candidatures?student_id=eq.$userId",
                        'payload' => null,
                        'message' => 'Erreur lors de la suppression des candidatures associées.',
                    ],
                    [
                        'method' => 'DELETE',
                        'url' => "$baseUrl/conventions?student_id=eq.$userId",
                        'payload' => null,
                        'message' => 'Erreur lors de la suppression des conventions associées.',
                    ],
                    [
                        'method' => 'DELETE',
                        'url' => "$baseUrl/formation_requests?student_id=eq.$userId",
                        'payload' => null,
                        'message' => 'Erreur lors de la suppression des demandes de formation associées.',
                    ],
                    [
                        'method' => 'DELETE',
                        'url' => "$baseUrl/cahier_stage?student_id=eq.$userId",
                        'payload' => null,
                        'message' => 'Erreur lors de la suppression du cahier de stage associé.',
                    ],
                    [
                        'method' => 'DELETE',
                        'url' => "$baseUrl/two_factor_codes?user_id=eq.$userId",
                        'payload' => null,
                        'message' => 'Erreur lors de la suppression des codes de double authentification.',
                    ],
                    [
                        'method' => $handoverUserId ? 'PATCH' : 'DELETE',
                        'url' => "$baseUrl/documents?user_id=eq.$userId",
                        'payload' => $handoverUserId ? ['user_id' => $handoverUserId] : null,
                        'message' => 'Erreur lors du rattachement des documents associés.',
                    ],
                    [
                        'method' => $handoverUserId ? 'PATCH' : 'DELETE',
                        'url' => "$baseUrl/remarques?author_id=eq.$userId",
                        'payload' => $handoverUserId ? ['author_id' => $handoverUserId] : null,
                        'message' => 'Erreur lors du rattachement des remarques associées.',
                    ],
                    [
                        'method' => $handoverUserId ? 'PATCH' : 'DELETE',
                        'url' => "$baseUrl/missions?company_id=eq.$userId",
                        'payload' => $handoverUserId ? ['company_id' => $handoverUserId] : null,
                        'message' => 'Erreur lors du rattachement des missions associées.',
                    ],
                    [
                        'method' => 'PATCH',
                        'url' => "$baseUrl/traces?user_id=eq.$userId",
                        'payload' => ['user_id' => null],
                        'message' => 'Erreur lors du nettoyage des traces associées.',
                    ],
                    [
                        'method' => 'PATCH',
                        'url' => "$baseUrl/stages?tutor_id=eq.$userId",
                        'payload' => ['tutor_id' => null],
                        'message' => 'Erreur lors du détachement des stages du tuteur.',
                    ],
                    [
                        'method' => 'PATCH',
                        'url' => "$baseUrl/stages?company_id=eq.$userId",
                        'payload' => ['company_id' => null],
                        'message' => 'Erreur lors du détachement des stages de l\'entreprise.',
                    ],
                    [
                        'method' => 'PATCH',
                        'url' => "$baseUrl/users?id=eq.$userId",
                        'payload' => ['stage_id' => null],
                        'message' => 'Erreur lors du nettoyage du compte.',
                    ],
                ]);
            }
 
            // On parcourt chaque element de la liste.
            foreach ($cleanupSteps as $step) {
                $result = callSupabase($step['method'], $step['url'], $apiKey, $step['payload']);
                // On controle cette condition avant de continuer.
                if (!$result['ok']) {
                    $errorMsg = getSupabaseErrorMessage($result, $step['message']);
                    break;
                }
            }
 
            // On gere le cas ou la valeur attendue est vide.
            if ($errorMsg === '') {
                $deleteResult = callSupabase('DELETE', "$baseUrl/users?id=eq.$userId", $apiKey);
                // On verifie cette condition.
                if ($deleteResult['ok']) {
                    // On appelle Supabase Auth pour gerer l authentification.
                    $authUser = supabaseAuthAdminFindUserByEmail((string) ($targetUser['email'] ?? ''));
                    // On verifie cette condition.
                    if ($authUser && !empty($authUser['id'])) {
                        // On appelle Supabase Auth pour gerer l authentification.
                        $authDelete = supabaseAuthAdminDeleteUser((string) $authUser['id']);
                        // On controle cette condition avant de continuer.
                        if (!$authDelete['ok']) {
                            // On appelle Supabase Auth pour gerer l authentification.
                            $errorMsg = supabaseAuthErrorMessage($authDelete, 'Le profil applicatif a ete supprime, mais pas le compte Supabase Auth.');
                        }
                    }
 
                    // On gere le cas ou la valeur attendue est vide.
                    if ($errorMsg === '') {
                        $successMsg = ($targetUser['type'] ?? '') === 'etudiant'
                            ? 'Compte supprime. Les stages attribues a cet etudiant ont ete conserves dans les archives.'
                            : 'Compte supprime dans le profil applicatif et dans Supabase Auth.';
                    }
                } else {
                    $errorMsg = getSupabaseErrorMessage($deleteResult, 'Erreur lors de la suppression du compte.');
                }
            }
        }
 
    } elseif ($action === 'approve_admin' && $userId) {
        $targetUser = fetchPlatformUserById($userId, $baseUrl, $apiKey);
        // On controle cette condition avant de continuer.
        if (!$targetUser) {
            $errorMsg = 'Compte introuvable.';
        } elseif (($targetUser['type'] ?? '') !== 'admin') {
            $errorMsg = 'Ce compte n\'est pas une demande administrateur.';
        } else {
            $updateResult = callSupabase('PATCH', "$baseUrl/users?id=eq.$userId", $apiKey, ['admin_pending' => false]);
            // On controle cette condition avant de continuer.
            if (!$updateResult['ok']) {
                $errorMsg = getSupabaseErrorMessage($updateResult, 'Erreur lors de la validation du compte administrateur.');
            } else {
                $successMsg = 'Compte administrateur valide. L\'utilisateur peut maintenant se connecter.';
            }
        }

    } elseif ($action === 'reject_admin' && $userId) {
        $targetUser = fetchPlatformUserById($userId, $baseUrl, $apiKey);
        // On controle cette condition avant de continuer.
        if (!$targetUser) {
            $errorMsg = 'Compte introuvable.';
        } elseif (($targetUser['type'] ?? '') !== 'admin') {
            $errorMsg = 'Ce compte n\'est pas une demande administrateur.';
        } else {
            $deleteResult = callSupabase('DELETE', "$baseUrl/users?id=eq.$userId", $apiKey);
            // On controle cette condition avant de continuer.
            if (!$deleteResult['ok']) {
                $errorMsg = getSupabaseErrorMessage($deleteResult, 'Erreur lors du rejet de la demande.');
            } else {
                // On appelle Supabase Auth pour gerer l authentification.
                $authUser = supabaseAuthAdminFindUserByEmail((string) ($targetUser['email'] ?? ''));
                // On verifie cette condition.
                if ($authUser && !empty($authUser['id'])) {
                    // On appelle Supabase Auth pour gerer l authentification.
                    supabaseAuthAdminDeleteUser((string) $authUser['id']);
                }
                $successMsg = 'Demande de compte administrateur rejetee et supprimee.';
            }
        }

    } elseif ($action === 'create') {
        // On recupere et nettoie une valeur envoyee par l utilisateur.
        $newEmail = trim((string) ($_POST['new_email'] ?? ''));
        // On recupere et nettoie une valeur envoyee par l utilisateur.
        $newUsername = trim((string) ($_POST['new_username'] ?? ''));
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $newType = (string) ($_POST['new_type'] ?? 'etudiant');

        $createResult = addUserSupabase($newEmail, $newUsername, $newPassword, $newType);

        // On verifie cette condition.
        if (is_array($createResult) && isset($createResult['code']) && !isset($createResult['id'])) {
            $errorMsg = (string) ($createResult['message'] ?? 'Création impossible.');
        } else {
            $successMsg = 'Compte créé avec succès dans Supabase Auth et le profil applicatif.';
        }

    } elseif ($action === 'update_type' && $userId) {
        $newType = $_POST['type'] ?? '';
        // On prepare les donnees utilisees dans ce bloc.
        $allowed = ['etudiant', 'entreprise', 'tuteur', 'jury', 'admin'];
        // On verifie cette condition.
        if (!in_array($newType, $allowed)) {
            $errorMsg = 'Type invalide.';
        } else {
            $targetUser = fetchPlatformUserById($userId, $baseUrl, $apiKey);
            // On controle cette condition avant de continuer.
            if (!$targetUser) {
                $errorMsg = 'Compte introuvable.';
            }
 
            // On bloque la modification du type d un autre administrateur.
            if ($errorMsg === '' && ($targetUser['type'] ?? '') === 'admin' && $userId !== (int) ($_SESSION['user_id'] ?? 0)) {
                $errorMsg = 'Vous ne pouvez pas modifier le type d\'un autre administrateur.';
            }
 
            // On gere le cas ou la valeur attendue est vide.
            if ($errorMsg === '') {
                $updateResult = callSupabase('PATCH', "$baseUrl/users?id=eq.$userId", $apiKey, ['type' => $newType]);
 
                // On controle cette condition avant de continuer.
                if (!$updateResult['ok']) {
                    $errorMsg = getSupabaseErrorMessage($updateResult, 'Erreur lors de la mise à jour.');
                }
            }
 
            // On gere le cas ou la valeur attendue est vide.
            if ($errorMsg === '' && $targetUser) {
                // On appelle Supabase Auth pour gerer l authentification.
                $authUser = supabaseAuthAdminFindUserByEmail((string) ($targetUser['email'] ?? ''));
 
                // On verifie cette condition.
                if ($authUser && !empty($authUser['id'])) {
                    // On appelle Supabase Auth pour gerer l authentification.
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
 
                    // On controle cette condition avant de continuer.
                    if (!$authUpdate['ok']) {
                        // On appelle Supabase Auth pour gerer l authentification.
                        $errorMsg = supabaseAuthErrorMessage($authUpdate, 'Le type a ete mis a jour dans la table users, mais pas dans Supabase Auth.');
                    }
                }
 
                // On gere le cas ou la valeur attendue est vide.
                if ($errorMsg === '') {
                    $successMsg = 'Type mis a jour dans le profil applicatif et dans Supabase Auth.';
                }
            }
        }
    }
}
 
// On recupere les utilisateurs a afficher.
$usersResult = callSupabase('GET', "$baseUrl/users?select=id,email,username,type,created_at,admin_pending&order=created_at.desc", $apiKey);
$users = is_array($usersResult['data']) ? $usersResult['data'] : [];

// On prepare les donnees utilisees dans ce bloc.
$pendingAdmins = [];
// On prepare les donnees utilisees dans ce bloc.
$activeUsers = [];
// On parcourt chaque element de la liste.
foreach ($users as $u) {
    // On verifie cette condition.
    if (!empty($u['admin_pending'])) {
        $pendingAdmins[] = $u;
    } else {
        $activeUsers[] = $u;
    }
}
 
// On gere le cas ou la valeur attendue est vide.
if (!$usersResult['ok'] && $errorMsg === '') {
    $errorMsg = getSupabaseErrorMessage($usersResult, 'Impossible de charger la liste des comptes.');
}
 
// On prepare les donnees utilisees dans ce bloc.
$typesLabels = [
    'etudiant'   => 'Étudiant',
    'entreprise' => 'Entreprise',
    'tuteur'     => 'Tuteur',
    'jury'       => 'Jury',
    'admin'      => 'Admin',
];

// On charge les fichiers necessaires.
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="card">
    <h2>Gestion des comptes</h2>

    <?php // On controle cette condition avant de continuer. ?>
    <?php if ($successMsg): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($successMsg); ?></div>
    <?php endif; ?>
    <?php // On controle cette condition avant de continuer. ?>
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

<?php // On verifie cette condition. ?>
<?php if (!empty($pendingAdmins)): ?>
<div class="card" style="border: 2px solid var(--accent-color);">
    <h3>Demandes de compte administrateur (<?php echo count($pendingAdmins); ?>)</h3>
    <p style="color: var(--text-secondary);">Ces utilisateurs se sont inscrits en tant qu'administrateur. Validez ou rejetez leur demande.</p>
    <table style="width:100%; border-collapse:collapse; margin-top:1rem;">
        <thead>
            <tr style="border-bottom:2px solid var(--border-color); text-align:left;">
                <th style="padding:0.75rem;">Nom d'utilisateur</th>
                <th style="padding:0.75rem;">Email</th>
                <th style="padding:0.75rem;">Demande le</th>
                <th style="padding:0.75rem;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php // On parcourt chaque element de la liste. ?>
            <?php foreach ($pendingAdmins as $pending): ?>
                <tr style="border-bottom:1px solid var(--border-color);">
                    <td style="padding:0.75rem;"><?php echo htmlspecialchars($pending['username'] ?? '—'); ?></td>
                    <td style="padding:0.75rem;"><?php echo htmlspecialchars($pending['email'] ?? '—'); ?></td>
                    <td style="padding:0.75rem;"><?php echo htmlspecialchars(substr((string) ($pending['created_at'] ?? ''), 0, 10)); ?></td>
                    <td style="padding:0.75rem; display:flex; gap:0.5rem;">
                        <form method="POST" onsubmit="return confirm('Valider ce compte administrateur ?');">
                            <input type="hidden" name="action" value="approve_admin">
                            <input type="hidden" name="user_id" value="<?php echo (int) ($pending['id'] ?? 0); ?>">
                            <button type="submit" class="btn btn-primary" style="padding:0.3rem 0.75rem; font-size:0.85rem;">Valider</button>
                        </form>
                        <form method="POST" onsubmit="return confirm('Rejeter et supprimer cette demande ?');">
                            <input type="hidden" name="action" value="reject_admin">
                            <input type="hidden" name="user_id" value="<?php echo (int) ($pending['id'] ?? 0); ?>">
                            <button type="submit" class="btn" style="padding:0.3rem 0.75rem; font-size:0.85rem; background:var(--danger-color); color:white;">Rejeter</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<div class="card">
    <h3>Comptes existants</h3>

    <?php // On gere le cas ou la valeur attendue est vide. ?>
    <?php if (empty($activeUsers)): ?>
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
                <?php // On parcourt chaque element de la liste. ?>
                <?php foreach ($activeUsers as $user): ?>
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
                            <?php // On controle cette condition avant de continuer. ?>
                            <?php if (!$isOtherAdmin): ?>
                                <form method="POST" style="display:flex; gap:0.4rem; align-items:center;">
                                    <input type="hidden" name="action" value="update_type">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <select name="type" class="form-control" style="width:auto; padding:0.3rem 0.5rem;">
                                        <?php // On parcourt chaque element de la liste. ?>
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
                            <?php // On controle cette condition avant de continuer. ?>
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
 
<?php // On charge les fichiers necessaires. ?>
<?php require_once '../../includes/footer.php'; ?>
