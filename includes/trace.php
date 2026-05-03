<?php
// Fichier qui enregistre et compte les traces d activite.

require_once __DIR__ . '/../supabaseQuery/restClient.php';
require_once __DIR__ . '/../vendor/autoload.php';

// On verifie cette condition.
if (!function_exists('stageArchiveTraceEnsureEnvLoaded')) {
    // Cette fonction regroupe une action reutilisable.
    function stageArchiveTraceEnsureEnvLoaded(): void
    {
        // On verifie cette condition.
        if (!empty($_ENV['SUPABASE_URL']) && !empty($_ENV['SUPABASE_KEY'])) {
            return;
        }

        // On verifie cette condition.
        if (class_exists('Dotenv\\Dotenv')) {
            $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
            $dotenv->safeLoad();
        }
    }
}

// On verifie cette condition.
if (!function_exists('stageArchiveTraceBaseUrl')) {
    // Cette fonction regroupe une action reutilisable.
    function stageArchiveTraceBaseUrl(): string
    {
        stageArchiveTraceEnsureEnvLoaded();
        return rtrim((string) ($_ENV['SUPABASE_URL'] ?? ''), '/') . '/rest/v1';
    }
}

// On verifie cette condition.
if (!function_exists('stageArchiveTraceApiKey')) {
    // Cette fonction regroupe une action reutilisable.
    function stageArchiveTraceApiKey(): string
    {
        stageArchiveTraceEnsureEnvLoaded();
        return (string) ($_ENV['SUPABASE_KEY'] ?? '');
    }
}

// On verifie cette condition.
if (!function_exists('stageArchiveLogTrace')) {
    // Cette fonction regroupe une action reutilisable.
    function stageArchiveLogTrace(string $action, ?string $details = null, ?int $userId = null): void
    {
        // On demarre la session si elle n existe pas encore.
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $apiKey = stageArchiveTraceApiKey();
        // On gere le cas ou la valeur attendue est vide.
        if ($apiKey === '') {
            return;
        }

        $resolvedUserId = $userId ?? (int) ($_SESSION['user_id'] ?? 0);
        // On prepare les donnees utilisees dans ce bloc.
        $payload = [
            'action' => $action,
            'details' => $details,
        ];

        // On verifie cette condition.
        if ($resolvedUserId > 0) {
            $payload['user_id'] = $resolvedUserId;
        }

        // On appelle Supabase pour lire ou modifier les donnees.
        supabaseRestRequest(
            'POST',
            stageArchiveTraceBaseUrl() . '/traces',
            $apiKey,
            $payload
        );
    }
}

// On verifie cette condition.
if (!function_exists('stageArchiveLogPageAccess')) {
    // Cette fonction regroupe une action reutilisable.
    function stageArchiveLogPageAccess(string $page): void
    {
        stageArchiveLogTrace('access', $page);
    }
}

// On verifie cette condition.
if (!function_exists('stageArchiveCountTracesForUser')) {
    // Cette fonction regroupe une action reutilisable.
    function stageArchiveCountTracesForUser(int $userId, ?string $action = null): int
    {
        $apiKey = stageArchiveTraceApiKey();
        // On gere le cas ou la valeur attendue est vide.
        if ($apiKey === '' || $userId <= 0) {
            return 0;
        }

        $url = stageArchiveTraceBaseUrl() . '/traces?user_id=eq.' . $userId . '&select=id';
        // On verifie cette condition.
        if ($action !== null && $action !== '') {
            $url .= '&action=eq.' . rawurlencode($action);
        }

        // On appelle Supabase pour lire ou modifier les donnees.
        $result = supabaseRestRequest(
            'GET',
            $url,
            $apiKey,
            null,
            ['Prefer: count=exact', 'Range: 0-0']
        );

        return is_array($result['data']) ? count($result['data']) : 0;
    }
}
