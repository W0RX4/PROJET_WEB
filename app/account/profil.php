<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../supabaseQuery/restClient.php';
require_once __DIR__ . '/../../includes/trace.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

use Dotenv\Dotenv;

if (!isset($_ENV['SUPABASE_URL'])) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
    $dotenv->safeLoad();
}

$apiKey = (string) ($_ENV['SUPABASE_KEY'] ?? '');
$baseUrl = rtrim((string) ($_ENV['SUPABASE_URL'] ?? ''), '/') . '/rest/v1';
$userId = (int) ($_SESSION['user_id'] ?? 0);

function redirectToProfil(): void
{
    header('Location: /app/account/profil.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newUsername = trim((string) ($_POST['username'] ?? ''));

    if ($newUsername === '' || mb_strlen($newUsername) > 255) {
        $_SESSION['error'] = "Le nom d'utilisateur est obligatoire (255 caractères max).";
        redirectToProfil();
    }

    $duplicateResult = supabaseRestRequest(
        'GET',
        "$baseUrl/users?username=eq." . rawurlencode($newUsername) . "&id=neq.$userId&select=id&limit=1",
        $apiKey
    );

    if (is_array($duplicateResult['data']) && !empty($duplicateResult['data'])) {
        $_SESSION['error'] = "Ce nom d'utilisateur est déjà pris.";
        redirectToProfil();
    }

    $updateResult = supabaseRestRequest(
        'PATCH',
        "$baseUrl/users?id=eq.$userId",
        $apiKey,
        ['username' => $newUsername]
    );

    if (!$updateResult['ok']) {
        $_SESSION['error'] = supabaseRestErrorMessage($updateResult, 'Mise à jour du profil impossible.');
        redirectToProfil();
    }

    $_SESSION['username'] = $newUsername;
    stageArchiveLogTrace('profile_update', "username -> $newUsername");
    $_SESSION['result'] = 'Profil mis à jour.';
    redirectToProfil();
}

stageArchiveLogPageAccess('/app/account/profil.php');

$profileResult = supabaseRestRequest(
    'GET',
    "$baseUrl/users?id=eq.$userId&select=id,username,email,type,created_at&limit=1",
    $apiKey
);
$profile = is_array($profileResult['data']) && isset($profileResult['data'][0]) ? $profileResult['data'][0] : null;

$accessCount = stageArchiveCountTracesForUser($userId, 'login');
$actionsCount = stageArchiveCountTracesForUser($userId);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="card mes-offres-hero">
    <h2>Mon profil</h2>
    <p>Consultez et mettez à jour vos informations personnelles.</p>
</div>

<?php if (isset($_SESSION['result'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['result']); unset($_SESSION['result']); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
<?php endif; ?>

<?php if (!$profile): ?>
    <div class="card"><p>Profil introuvable.</p></div>
<?php else: ?>
    <div class="grid-container">
        <div class="card">
            <h3>Informations</h3>
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" value="<?php echo htmlspecialchars($profile['email'] ?? ''); ?>" disabled>
                </div>
                <div class="form-group">
                    <label class="form-label">Type de profil</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars(ucfirst((string) ($profile['type'] ?? ''))); ?>" disabled>
                </div>
                <div class="form-group">
                    <label class="form-label" for="username">Nom d'utilisateur</label>
                    <input type="text" id="username" name="username" class="form-control" value="<?php echo htmlspecialchars($profile['username'] ?? ''); ?>" required>
                </div>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </form>
        </div>

        <div class="card">
            <h3>Activité</h3>
            <p><strong>Compte créé le :</strong> <?php echo htmlspecialchars((string) ($profile['created_at'] ?? 'N/A')); ?></p>
            <p><strong>Connexions enregistrées :</strong> <?php echo (int) $accessCount; ?></p>
            <p><strong>Actions tracées :</strong> <?php echo (int) $actionsCount; ?></p>
            <p style="margin-top: 1rem;">
                <a href="/app/account/security.php" class="btn btn-secondary">Sécurité &amp; MFA</a>
            </p>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
