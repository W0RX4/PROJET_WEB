<?php
require_once __DIR__ . '/../supabaseQuery/authClient.php';
require_once __DIR__ . '/authSession.php';

stageArchiveStartSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /verify-2fa');
    exit;
}

$pending = $_SESSION['pending_supabase_auth'] ?? null;
if (!is_array($pending)) {
    $_SESSION['error'] = 'Session MFA expiree. Veuillez vous reconnecter.';
    header('Location: /login');
    exit;
}

$code = preg_replace('/\D/', '', (string) ($_POST['code'] ?? ''));
if (strlen($code) !== 6) {
    $_SESSION['error'] = 'Le code d authentification doit contenir 6 chiffres.';
    header('Location: /verify-2fa');
    exit;
}

$factorId = (string) ($pending['factor_id'] ?? '');
$authSession = is_array($pending['auth_session'] ?? null) ? $pending['auth_session'] : [];
$accessToken = (string) ($authSession['access_token'] ?? '');
$profile = is_array($pending['profile'] ?? null) ? $pending['profile'] : [];

if ($factorId === '' || $accessToken === '' || $profile === []) {
    unset($_SESSION['pending_supabase_auth']);
    $_SESSION['error'] = 'Session MFA invalide. Veuillez vous reconnecter.';
    header('Location: /login');
    exit;
}

$verifyResult = supabaseAuthChallengeAndVerifyFactor($accessToken, $factorId, $code);
if (!$verifyResult['ok']) {
    $_SESSION['error'] = supabaseAuthErrorMessage($verifyResult, 'Code invalide ou expire.');
    header('Location: /verify-2fa');
    exit;
}

stageArchiveSetAuthenticatedSession($profile, is_array($verifyResult['data'] ?? null) ? $verifyResult['data'] : $authSession);
header('Location: ' . stageArchiveDestinationForType((string) ($profile['type'] ?? '')));
exit;
