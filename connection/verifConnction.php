<?php
// Fichier qui traite la connexion avec Supabase Auth.
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../supabaseQuery/authClient.php';
require_once __DIR__ . '/../supabaseQuery/userProfileClient.php';
require_once __DIR__ . '/authSession.php';

// On ouvre la session de l application.
stageArchiveStartSession();

// On refuse les acces qui ne viennent pas du formulaire.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /login');
    exit;
}

// Cette fonction termine la connexion apres la reponse Supabase.
function stageArchiveFinalizeLoginFromAuthResult(array $authResult): void
{
    $authUser = is_array($authResult['data']['user'] ?? null) ? $authResult['data']['user'] : [];

    // On verifie cette condition.
    if ($authUser === []) {
        $_SESSION['error'] = 'La session Supabase est incomplete.';
        header('Location: /login');
        exit;
    }

    $profileResult = stageArchiveEnsureProfileForAuthUser($authUser);
    // On verifie cette condition.
    if (!($profileResult['ok'] ?? false)) {
        $_SESSION['error'] = (string) ($profileResult['message'] ?? 'Impossible de preparer votre profil applicatif.');
        header('Location: /login');
        exit;
    }

    $profile = $profileResult['profile'];

    // On verifie cette condition.
    if (!empty($profile['admin_pending'])) {
        $_SESSION['error'] = 'Votre compte administrateur est en attente de validation. Un administrateur existant doit l\'approuver avant que vous puissiez vous connecter.';
        // On verifie cette condition.
        if (!empty($authResult['data']['access_token'])) {
            // On appelle Supabase Auth pour gerer l authentification.
            supabaseAuthLogout((string) $authResult['data']['access_token']);
        }
        header('Location: /login');
        exit;
    }

    $authUserId = (string) ($authUser['id'] ?? '');
    // On appelle Supabase Auth pour gerer l authentification.
    $factorsResult = $authUserId !== '' ? supabaseAuthAdminListUserFactors($authUserId) : ['ok' => true, 'data' => []];

    // On controle cette condition avant de continuer.
    if (!$factorsResult['ok']) {
        // On appelle Supabase Auth pour gerer l authentification.
        $_SESSION['error'] = supabaseAuthErrorMessage($factorsResult, 'Impossible de verifier les facteurs MFA Supabase pour ce compte.');
        header('Location: /login');
        exit;
    }

    $factors = is_array($factorsResult['data']) ? $factorsResult['data'] : [];
    $verifiedFactor = null;

    // On parcourt chaque element de la liste.
    foreach ($factors as $factor) {
        $status = strtolower((string) ($factor['status'] ?? ''));
        // On verifie cette condition.
        if ($status === 'verified') {
            $verifiedFactor = $factor;
            break;
        }
    }

    // On controle cette condition avant de continuer.
    if ($verifiedFactor) {
        // On gere le cas ou la valeur attendue est vide.
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

// Cette fonction tente de migrer un ancien compte vers Supabase Auth.
function stageArchiveTryLegacyMigration(string $email, string $password): ?array
{
    $legacyProfile = stageArchiveFindProfileByEmail($email);
    // On controle cette condition avant de continuer.
    if (!$legacyProfile || !password_verify($password, (string) ($legacyProfile['password'] ?? ''))) {
        return null;
    }

    // On appelle Supabase Auth pour gerer l authentification.
    $existingAuthUser = supabaseAuthAdminFindUserByEmail($email);
    // On controle cette condition avant de continuer.
    if ($existingAuthUser) {
        return null;
    }

    // On appelle Supabase Auth pour gerer l authentification.
    $authCreate = supabaseAuthAdminCreateUser(
        $email,
        $password,
        [
            'username' => (string) ($legacyProfile['username'] ?? stageArchiveDefaultUsernameFromEmail($email)),
            'type' => stageArchiveNormalizeUserType((string) ($legacyProfile['type'] ?? 'etudiant')),
        ]
    );

    // On controle cette condition avant de continuer.
    if (!$authCreate['ok']) {
        // On appelle Supabase Auth pour gerer l authentification.
        $_SESSION['error'] = supabaseAuthErrorMessage($authCreate, 'Impossible de migrer ce compte vers Supabase Auth.');
        return null;
    }

    // On appelle Supabase Auth pour gerer l authentification.
    $signInAfterMigration = supabaseAuthSignInWithPassword($email, $password);
    return $signInAfterMigration['ok'] ? $signInAfterMigration : null;
}

// On recupere et nettoie une valeur envoyee par l utilisateur.
$email = trim((string) ($_POST['email'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

// On gere le cas ou la valeur attendue est vide.
if ($email === '' || $password === '') {
    $_SESSION['error'] = 'Email et mot de passe requis.';
    header('Location: /login');
    exit;
}

// On appelle Supabase Auth pour gerer l authentification.
$signInResult = supabaseAuthSignInWithPassword($email, $password);

// On controle cette condition avant de continuer.
if (!$signInResult['ok']) {
    $migratedSignIn = stageArchiveTryLegacyMigration($email, $password);
    // On verifie cette condition.
    if ($migratedSignIn !== null) {
        $signInResult = $migratedSignIn;
    }
}

// On controle cette condition avant de continuer.
if (!$signInResult['ok']) {
    // On appelle Supabase Auth pour gerer l authentification.
    $_SESSION['error'] = supabaseAuthErrorMessage($signInResult, 'Email ou mot de passe incorrect.');
    header('Location: /login');
    exit;
}

stageArchiveFinalizeLoginFromAuthResult($signInResult);
