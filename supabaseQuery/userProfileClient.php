<?php
// Fichier qui gere les profils utilisateurs applicatifs.

require_once __DIR__ . '/restClient.php';
require_once __DIR__ . '/authClient.php';

// On verifie cette condition.
if (!function_exists('stageArchiveProfilesBaseUrl')) {
    // Cette fonction regroupe une action reutilisable.
    function stageArchiveProfilesBaseUrl(): string
    {
        // On charge la configuration Supabase Auth.
        supabaseAuthEnsureEnvLoaded();
        return rtrim($_ENV['SUPABASE_URL'] ?? '', '/') . '/rest/v1';
    }
}

// On verifie cette condition.
if (!function_exists('stageArchiveProfilePlaceholderHash')) {
    // Cette fonction regroupe une action reutilisable.
    function stageArchiveProfilePlaceholderHash(): string
    {
        return password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT);
    }
}

// On verifie cette condition.
if (!function_exists('stageArchiveDefaultUsernameFromEmail')) {
    // Cette fonction regroupe une action reutilisable.
    function stageArchiveDefaultUsernameFromEmail(string $email): string
    {
        $localPart = explode('@', $email)[0] ?? 'utilisateur';
        $localPart = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $localPart) ?: 'utilisateur';
        return trim($localPart, '-_.') !== '' ? trim($localPart, '-_.') : 'utilisateur';
    }
}

// On verifie cette condition.
if (!function_exists('stageArchiveNormalizeUserType')) {
    // Cette fonction regroupe une action reutilisable.
    function stageArchiveNormalizeUserType(?string $type): string
    {
        // On prepare les donnees utilisees dans ce bloc.
        $allowed = ['admin', 'etudiant', 'entreprise', 'tuteur', 'jury'];
        $candidate = strtolower(trim((string) $type));
        return in_array($candidate, $allowed, true) ? $candidate : 'etudiant';
    }
}

// On verifie cette condition.
if (!function_exists('stageArchiveFindProfileByEmail')) {
    // Cette fonction regroupe une action reutilisable.
    function stageArchiveFindProfileByEmail(string $email): ?array
    {
        // On appelle Supabase pour lire ou modifier les donnees.
        $result = supabaseRestRequest(
            'GET',
            stageArchiveProfilesBaseUrl() . '/users?email=eq.' . rawurlencode($email) . '&select=*&limit=1',
            // On appelle Supabase Auth pour gerer l authentification.
            supabaseAuthApiKey()
        );

        $profiles = is_array($result['data']) ? $result['data'] : [];
        return $profiles[0] ?? null;
    }
}

// On verifie cette condition.
if (!function_exists('stageArchiveCreateProfile')) {
    // Cette fonction regroupe une action reutilisable.
    function stageArchiveCreateProfile(string $email, string $username, string $type, ?string $passwordHash = null): array
    {
        // On appelle Supabase pour lire ou modifier les donnees.
        return supabaseRestRequest(
            'POST',
            stageArchiveProfilesBaseUrl() . '/users',
            // On appelle Supabase Auth pour gerer l authentification.
            supabaseAuthApiKey(),
            [
                'email' => $email,
                'username' => $username,
                'password' => $passwordHash ?: stageArchiveProfilePlaceholderHash(),
                'type' => stageArchiveNormalizeUserType($type),
            ],
            ['Prefer: return=representation']
        );
    }
}

// On verifie cette condition.
if (!function_exists('stageArchiveEnsureProfileForAuthUser')) {
    // Cette fonction regroupe une action reutilisable.
    function stageArchiveEnsureProfileForAuthUser(array $authUser): array
    {
        $email = (string) ($authUser['email'] ?? '');
        $userMetadata = is_array($authUser['user_metadata'] ?? null) ? $authUser['user_metadata'] : [];
        $appMetadata = is_array($authUser['app_metadata'] ?? null) ? $authUser['app_metadata'] : [];

        // On gere le cas ou la valeur attendue est vide.
        if ($email === '') {
            return [
                'ok' => false,
                'message' => 'Le compte Supabase ne contient pas d email exploitable.',
            ];
        }

        $existingProfile = stageArchiveFindProfileByEmail($email);
        // On controle cette condition avant de continuer.
        if ($existingProfile) {
            return [
                'ok' => true,
                'profile' => $existingProfile,
                'created' => false,
            ];
        }

        $username = (string) ($userMetadata['username'] ?? $appMetadata['username'] ?? stageArchiveDefaultUsernameFromEmail($email));
        $type = stageArchiveNormalizeUserType($userMetadata['type'] ?? $appMetadata['type'] ?? null);

        $createResult = stageArchiveCreateProfile($email, $username, $type);
        $profile = is_array($createResult['data']) && isset($createResult['data'][0]) ? $createResult['data'][0] : null;

        // On controle cette condition avant de continuer.
        if (!$createResult['ok'] || !$profile) {
            return [
                'ok' => false,
                'message' => supabaseRestErrorMessage($createResult, 'Impossible de creer le profil applicatif lie a ce compte Supabase.'),
            ];
        }

        return [
            'ok' => true,
            'profile' => $profile,
            'created' => true,
        ];
    }
}
