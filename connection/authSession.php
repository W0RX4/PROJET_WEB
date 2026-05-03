<?php
// Fichier qui centralise la creation de session et la redirection apres connexion.

require_once __DIR__ . '/../includes/trace.php';

// On verifie cette condition.
if (!function_exists('stageArchiveStartSession')) {
    // On ouvre la session de l application.
    function stageArchiveStartSession(): void
    {
        // On demarre la session si elle n existe pas encore.
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}

// On verifie cette condition.
if (!function_exists('stageArchiveDestinationForType')) {
    // Cette fonction choisit la page d accueil selon le role.
    function stageArchiveDestinationForType(string $type): string
    {
        // On choisit le traitement selon la valeur recue.
        switch ($type) {
            // On gere ce cas precis.
            case 'etudiant':
                return '/app/user/accueilUser.php';
            // On gere ce cas precis.
            case 'entreprise':
                return '/app/entreprise/accueilEntreprise.php';
            // On gere ce cas precis.
            case 'tuteur':
                return '/app/tuteur/accueilTuteur.php';
            // On gere ce cas precis.
            case 'jury':
                return '/app/jury/accueilJury.php';
            // On gere ce cas precis.
            case 'admin':
                return '/app/admin/accueilAdmin.php';
            default:
                return '/login';
        }
    }
}

// On verifie cette condition.
if (!function_exists('stageArchiveSetAuthenticatedSession')) {
    // Cette fonction enregistre les informations de connexion en session.
    function stageArchiveSetAuthenticatedSession(array $profile, array $authSession): void
    {
        // On ouvre la session de l application.
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

        stageArchiveLogTrace('login', 'Connexion reussie');
    }
}
