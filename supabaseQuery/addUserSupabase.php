<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/authClient.php';
require_once __DIR__ . '/userProfileClient.php';

use Dotenv\Dotenv;

if (!isset($_ENV['SUPABASE_URL'])) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();
}

function addUserSupabase($email, $username, $password, $type) {
    $email = trim((string) $email);
    $username = trim((string) $username);
    $type = stageArchiveNormalizeUserType((string) $type);

    if ($email === '' || $username === '' || trim((string) $password) === '') {
        return [
            'code' => 400,
            'message' => 'Tous les champs sont obligatoires.',
        ];
    }

    $existingProfile = stageArchiveFindProfileByEmail($email);
    if ($existingProfile) {
        return [
            'code' => 409,
            'message' => 'Un compte existe deja avec cet email.',
        ];
    }

    $existingAuthUser = supabaseAuthAdminFindUserByEmail($email);
    if ($existingAuthUser) {
        return [
            'code' => 409,
            'message' => 'Un utilisateur Supabase Auth existe deja avec cet email.',
        ];
    }

    $authCreate = supabaseAuthAdminCreateUser(
        $email,
        (string) $password,
        [
            'username' => $username,
            'type' => $type,
        ]
    );

    $authUserId = (string) ($authCreate['data']['id'] ?? '');
    if (!$authCreate['ok'] || $authUserId === '') {
        return [
            'code' => $authCreate['code'],
            'message' => supabaseAuthErrorMessage($authCreate, 'Erreur lors de la creation du compte Supabase Auth'),
        ];
    }

    $result = stageArchiveCreateProfile($email, $username, $type);

    $createdProfile = is_array($result['data']) && isset($result['data'][0]) ? $result['data'][0] : null;
    if (!$result['ok'] || !$createdProfile) {
        supabaseAuthAdminDeleteUser($authUserId);
        return [
            'code'    => $result['code'],
            'message' => supabaseRestErrorMessage($result, "Erreur lors de la creation du compte"),
        ];
    }

    return $createdProfile;
}
