<?php

if (!function_exists('stageArchiveStartSession')) {
    function stageArchiveStartSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}

if (!function_exists('stageArchiveDestinationForType')) {
    function stageArchiveDestinationForType(string $type): string
    {
        switch ($type) {
            case 'etudiant':
                return '/app/user/accueilUser.php';
            case 'entreprise':
                return '/app/entreprise/accueilEntreprise.php';
            case 'tuteur':
                return '/app/tuteur/accueilTuteur.php';
            case 'jury':
                return '/app/jury/accueilJury.php';
            case 'admin':
                return '/app/admin/accueilAdmin.php';
            default:
                return '/login';
        }
    }
}

if (!function_exists('stageArchiveSetAuthenticatedSession')) {
    function stageArchiveSetAuthenticatedSession(array $profile, array $authSession): void
    {
        stageArchiveStartSession();
        session_regenerate_id(true);

        $authUser = is_array($authSession['user'] ?? null) ? $authSession['user'] : [];

        $_SESSION['user_id'] = (int) ($profile['id'] ?? 0);
        $_SESSION['auth_user_id'] = (string) ($authUser['id'] ?? '');
        $_SESSION['email'] = (string) ($profile['email'] ?? ($authUser['email'] ?? ''));
        $_SESSION['username'] = (string) ($profile['username'] ?? ($authUser['user_metadata']['username'] ?? ''));
        $_SESSION['type'] = (string) ($profile['type'] ?? ($authUser['user_metadata']['type'] ?? ''));
        $_SESSION['supabase_access_token'] = (string) ($authSession['access_token'] ?? '');
        $_SESSION['supabase_refresh_token'] = (string) ($authSession['refresh_token'] ?? '');
        $_SESSION['supabase_token_expires_at'] = time() + (int) ($authSession['expires_in'] ?? 3600);

        unset($_SESSION['pending_supabase_auth'], $_SESSION['pending_mfa_enrollment']);
    }
}
