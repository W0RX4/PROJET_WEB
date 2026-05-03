<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../supabaseQuery/authClient.php';
require_once __DIR__ . '/../supabaseQuery/userProfileClient.php';
require_once __DIR__ . '/authSession.php';

stageArchiveStartSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /login');
    exit;
}

function stageArchiveFinalizeLoginFromAuthResult(array $authResult): void
{
    $authUser = is_array($authResult['data']['user'] ?? null) ? $authResult['data']['user'] : [];

    if ($authUser === []) {
        $_SESSION['error'] = 'La session Supabase est incomplete.';
        header('Location: /login');
        exit;
    }

    $profileResult = stageArchiveEnsureProfileForAuthUser($authUser);
    if (!($profileResult['ok'] ?? false)) {
        $_SESSION['error'] = (string) ($profileResult['message'] ?? 'Impossible de preparer votre profil applicatif.');
        header('Location: /login');
        exit;
    }

    $profile = $profileResult['profile'];

    if (!empty($profile['admin_pending'])) {
        $_SESSION['error'] = 'Votre compte administrateur est en attente de validation. Un administrateur existant doit l\'approuver avant que vous puissiez vous connecter.';
        if (!empty($authResult['data']['access_token'])) {
            supabaseAuthLogout((string) $authResult['data']['access_token']);
        }
        header('Location: /login');
        exit;
    }

    $authUserId = (string) ($authUser['id'] ?? '');
    $factorsResult = $authUserId !== '' ? supabaseAuthAdminListUserFactors($authUserId) : ['ok' => true, 'data' => []];

    if (!$factorsResult['ok']) {
        $_SESSION['error'] = supabaseAuthErrorMessage($factorsResult, 'Impossible de verifier les facteurs MFA Supabase pour ce compte.');
        header('Location: /login');
        exit;
    }

    $factors = is_array($factorsResult['data']) ? $factorsResult['data'] : [];
    $verifiedFactor = null;

    foreach ($factors as $factor) {
        $status = strtolower((string) ($factor['status'] ?? ''));
        if ($status === 'verified') {
            $verifiedFactor = $factor;
            break;
        }
    }

    if ($verifiedFactor) {
        if (empty($verifiedFactor['id'])) {
            $_SESSION['error'] = 'Un facteur MFA Supabase est present, mais son identifiant est manquant.';
            header('Location: /login');
            exit;
        }

        $_SESSION['pending_supabase_auth'] = [
            'profile' => $profile,
            'auth_session' => $authResult['data'],
            'factor_id' => (string) ($verifiedFactor['id'] ?? ''),
            'factor_type' => (string) ($verifiedFactor['factor_type'] ?? $verifiedFactor['type'] ?? 'totp'),
            'friendly_name' => (string) ($verifiedFactor['friendly_name'] ?? $verifiedFactor['name'] ?? ''),
        ];
        $_SESSION['success'] = 'Verification a deux facteurs requise par Supabase.';
        header('Location: /verify-2fa');
        exit;
    }

    stageArchiveSetAuthenticatedSession($profile, $authResult['data']);
    header('Location: ' . stageArchiveDestinationForType((string) ($profile['type'] ?? '')));
    exit;
}

function stageArchiveTryLegacyMigration(string $email, string $password): ?array
{
    $legacyProfile = stageArchiveFindProfileByEmail($email);
    if (!$legacyProfile || !password_verify($password, (string) ($legacyProfile['password'] ?? ''))) {
        return null;
    }

    $existingAuthUser = supabaseAuthAdminFindUserByEmail($email);
    if ($existingAuthUser) {
        return null;
    }

    $authCreate = supabaseAuthAdminCreateUser(
        $email,
        $password,
        [
            'username' => (string) ($legacyProfile['username'] ?? stageArchiveDefaultUsernameFromEmail($email)),
            'type' => stageArchiveNormalizeUserType((string) ($legacyProfile['type'] ?? 'etudiant')),
        ]
    );

    if (!$authCreate['ok']) {
        $_SESSION['error'] = supabaseAuthErrorMessage($authCreate, 'Impossible de migrer ce compte vers Supabase Auth.');
        return null;
    }

    $signInAfterMigration = supabaseAuthSignInWithPassword($email, $password);
    return $signInAfterMigration['ok'] ? $signInAfterMigration : null;
}

$email = trim((string) ($_POST['email'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

if ($email === '' || $password === '') {
    $_SESSION['error'] = 'Email et mot de passe requis.';
    header('Location: /login');
    exit;
}

$signInResult = supabaseAuthSignInWithPassword($email, $password);

if (!$signInResult['ok']) {
    $migratedSignIn = stageArchiveTryLegacyMigration($email, $password);
    if ($migratedSignIn !== null) {
        $signInResult = $migratedSignIn;
    }
}

if (!$signInResult['ok']) {
    $_SESSION['error'] = supabaseAuthErrorMessage($signInResult, 'Email ou mot de passe incorrect.');
    header('Location: /login');
    exit;
}

stageArchiveFinalizeLoginFromAuthResult($signInResult);
