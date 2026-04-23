<?php

require_once __DIR__ . '/restClient.php';
require_once __DIR__ . '/authClient.php';

if (!function_exists('stageArchiveProfilesBaseUrl')) {
    function stageArchiveProfilesBaseUrl(): string
    {
        supabaseAuthEnsureEnvLoaded();
        return rtrim($_ENV['SUPABASE_URL'] ?? '', '/') . '/rest/v1';
    }
}

if (!function_exists('stageArchiveProfilePlaceholderHash')) {
    function stageArchiveProfilePlaceholderHash(): string
    {
        return password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT);
    }
}

if (!function_exists('stageArchiveDefaultUsernameFromEmail')) {
    function stageArchiveDefaultUsernameFromEmail(string $email): string
    {
        $localPart = explode('@', $email)[0] ?? 'utilisateur';
        $localPart = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $localPart) ?: 'utilisateur';
        return trim($localPart, '-_.') !== '' ? trim($localPart, '-_.') : 'utilisateur';
    }
}

if (!function_exists('stageArchiveNormalizeUserType')) {
    function stageArchiveNormalizeUserType(?string $type): string
    {
        $allowed = ['admin', 'etudiant', 'entreprise', 'tuteur', 'jury'];
        $candidate = strtolower(trim((string) $type));
        return in_array($candidate, $allowed, true) ? $candidate : 'etudiant';
    }
}

if (!function_exists('stageArchiveFindProfileByEmail')) {
    function stageArchiveFindProfileByEmail(string $email): ?array
    {
        $result = supabaseRestRequest(
            'GET',
            stageArchiveProfilesBaseUrl() . '/users?email=eq.' . rawurlencode($email) . '&select=*&limit=1',
            supabaseAuthApiKey()
        );

        $profiles = is_array($result['data']) ? $result['data'] : [];
        return $profiles[0] ?? null;
    }
}

if (!function_exists('stageArchiveCreateProfile')) {
    function stageArchiveCreateProfile(string $email, string $username, string $type, ?string $passwordHash = null): array
    {
        return supabaseRestRequest(
            'POST',
            stageArchiveProfilesBaseUrl() . '/users',
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

if (!function_exists('stageArchiveEnsureProfileForAuthUser')) {
    function stageArchiveEnsureProfileForAuthUser(array $authUser): array
    {
        $email = (string) ($authUser['email'] ?? '');
        $userMetadata = is_array($authUser['user_metadata'] ?? null) ? $authUser['user_metadata'] : [];
        $appMetadata = is_array($authUser['app_metadata'] ?? null) ? $authUser['app_metadata'] : [];

        if ($email === '') {
            return [
                'ok' => false,
                'message' => 'Le compte Supabase ne contient pas d email exploitable.',
            ];
        }

        $existingProfile = stageArchiveFindProfileByEmail($email);
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
