<?php
// Fichier qui cree les comptes applicatifs et Supabase.

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/authClient.php';
require_once __DIR__ . '/userProfileClient.php';

// On importe les classes utilisees dans ce fichier.
use Dotenv\Dotenv;

// On verifie cette condition.
if (!isset($_ENV['SUPABASE_URL'])) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();
}

// Cette fonction cree un utilisateur dans l application.
function addUserSupabase($email, $username, $password, $type) {
    // On recupere et nettoie une valeur envoyee par l utilisateur.
    $email = trim((string) $email);
    // On recupere et nettoie une valeur envoyee par l utilisateur.
    $username = trim((string) $username);
    $type = stageArchiveNormalizeUserType((string) $type);

    // On gere le cas ou la valeur attendue est vide.
    if ($email === '' || $username === '' || trim((string) $password) === '') {
        return [
            'code' => 400,
            'message' => 'Tous les champs sont obligatoires.',
        ];
    }

    $existingProfile = stageArchiveFindProfileByEmail($email);
    // On controle cette condition avant de continuer.
    if ($existingProfile) {
        return [
            'code' => 409,
            'message' => 'Un compte existe deja avec cet email.',
        ];
    }

    // On appelle Supabase Auth pour gerer l authentification.
    $existingAuthUser = supabaseAuthAdminFindUserByEmail($email);
    // On controle cette condition avant de continuer.
    if ($existingAuthUser) {
        return [
            'code' => 409,
            'message' => 'Un utilisateur Supabase Auth existe deja avec cet email.',
        ];
    }

    // On appelle Supabase Auth pour gerer l authentification.
    $authCreate = supabaseAuthAdminCreateUser(
        $email,
        (string) $password,
        [
            'username' => $username,
            'type' => $type,
        ]
    );

    $authUserId = (string) ($authCreate['data']['id'] ?? '');
    // On gere le cas ou la valeur attendue est vide.
    if (!$authCreate['ok'] || $authUserId === '') {
        return [
            'code' => $authCreate['code'],
            // On appelle Supabase Auth pour gerer l authentification.
            'message' => supabaseAuthErrorMessage($authCreate, 'Erreur lors de la creation du compte Supabase Auth'),
        ];
    }

    $result = stageArchiveCreateProfile($email, $username, $type);

    $createdProfile = is_array($result['data']) && isset($result['data'][0]) ? $result['data'][0] : null;
    // On controle cette condition avant de continuer.
    if (!$result['ok'] || !$createdProfile) {
        // On appelle Supabase Auth pour gerer l authentification.
        supabaseAuthAdminDeleteUser($authUserId);
        return [
            'code'    => $result['code'],
            'message' => supabaseRestErrorMessage($result, "Erreur lors de la creation du compte"),
        ];
    }

    return $createdProfile;
}
