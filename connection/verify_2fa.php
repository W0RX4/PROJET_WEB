<?php
require_once __DIR__ . '/authSession.php';

stageArchiveStartSession();

$pending = $_SESSION['pending_supabase_auth'] ?? null;
if (!is_array($pending)) {
    header('Location: /login');
    exit;
}

$emailTarget = (string) (($pending['profile']['email'] ?? '') ?: ($pending['auth_session']['user']['email'] ?? ''));
$factorType = strtolower((string) ($pending['factor_type'] ?? 'totp'));
$factorName = trim((string) ($pending['friendly_name'] ?? ''));
$maskedEmail = '';

if ($emailTarget !== '') {
    $parts = explode('@', $emailTarget);
    $name = $parts[0] ?? '';

    if ($name !== '' && isset($parts[1])) {
        $visible = substr($name, 0, min(2, strlen($name)));
        $maskedEmail = $visible . str_repeat('*', max(1, strlen($name) - 2)) . '@' . $parts[1];
    }
}

$factorLabel = $factorType === 'phone' ? 'telephone' : 'application d authentification';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification 2FA - Portfolium</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../public/css/style.css">
</head>
<body>

    <div class="auth-wrapper">
        <div class="auth-card">
            <h1 class="auth-title">Portfolium</h1>
            <h2 class="text-center mb-4" style="color: var(--text-secondary); font-weight: 500; font-size: 1rem;">Verification MFA Supabase</h2>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); ?></div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); ?></div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <p class="text-center" style="color: var(--text-secondary); margin-bottom: 0.75rem;">
                Ce compte utilise la double authentification geree par Supabase.
            </p>
            <p class="text-center" style="color: var(--text-secondary); margin-bottom: 2rem;">
                Entrez le code genere par votre <?php echo htmlspecialchars($factorLabel); ?><?php echo $factorName !== '' ? ' (' . htmlspecialchars($factorName) . ')' : ''; ?>.
            </p>

            <?php if ($maskedEmail !== ''): ?>
                <p class="text-center" style="color: var(--text-secondary); margin-bottom: 1.5rem;">
                    Connexion en cours pour <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($maskedEmail); ?></strong>
                </p>
            <?php endif; ?>

            <form action="/verify-2fa" method="post" id="otp-form">
                <div class="form-group">
                    <label class="form-label" for="code">Code a 6 chiffres</label>
                    <input
                        type="text"
                        id="code"
                        name="code"
                        class="form-control"
                        inputmode="numeric"
                        pattern="[0-9]{6}"
                        maxlength="6"
                        placeholder="123456"
                        autocomplete="one-time-code"
                        required
                    >
                </div>

                <button type="submit" class="btn btn-primary btn-block mt-4">Verifier</button>
            </form>

            <div class="text-center mt-4">
                <a href="/login" style="color: var(--accent-color); text-decoration: none; font-weight: 500; font-size: 0.9rem;">Revenir a la connexion</a>
            </div>
        </div>
    </div>

</body>
</html>
