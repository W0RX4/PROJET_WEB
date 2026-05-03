<?php
// Fichier qui verifie le code MFA envoye par l utilisateur.
require_once __DIR__ . '/../supabaseQuery/authClient.php';
require_once __DIR__ . '/authSession.php';

// On ouvre la session de l application.
stageArchiveStartSession();

// On refuse les acces qui ne viennent pas du formulaire.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /verify-2fa');
    exit;
}

$pending = $_SESSION['pending_supabase_auth'] ?? null;
// On verifie cette condition.
if (!is_array($pending)) {
    $_SESSION['error'] = 'Session MFA expiree. Veuillez vous reconnecter.';
    header('Location: /login');
    exit;
}

$code = preg_replace('/\D/', '', (string) ($_POST['code'] ?? ''));
// On verifie cette condition.
if (strlen($code) !== 6) {
    $_SESSION['error'] = 'Le code d authentification doit contenir 6 chiffres.';
    header('Location: /verify-2fa');
    exit;
}

$factorId = (string) ($pending['factor_id'] ?? '');
$authSession = is_array($pending['auth_session'] ?? null) ? $pending['auth_session'] : [];
$accessToken = (string) ($authSession['access_token'] ?? '');
$profile = is_array($pending['profile'] ?? null) ? $pending['profile'] : [];

// On gere le cas ou la valeur attendue est vide.
if ($factorId === '' || $accessToken === '' || $profile === []) {
    unset($_SESSION['pending_supabase_auth']);
    $_SESSION['error'] = 'Session MFA invalide. Veuillez vous reconnecter.';
    header('Location: /login');
    exit;
}

// On appelle Supabase Auth pour gerer l authentification.
$verifyResult = supabaseAuthChallengeAndVerifyFactor($accessToken, $factorId, $code);
// On controle cette condition avant de continuer.
if (!$verifyResult['ok']) {
    // On appelle Supabase Auth pour gerer l authentification.
    $_SESSION['error'] = supabaseAuthErrorMessage($verifyResult, 'Code invalide ou expire.');
    header('Location: /verify-2fa');
    exit;
}

stageArchiveSetAuthenticatedSession($profile, is_array($verifyResult['data'] ?? null) ? $verifyResult['data'] : $authSession);
header('Location: ' . stageArchiveDestinationForType((string) ($profile['type'] ?? '')));
exit;
