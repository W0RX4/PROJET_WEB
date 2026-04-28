<?php

require_once __DIR__ . '/../supabaseQuery/restClient.php';
require_once __DIR__ . '/../vendor/autoload.php';

if (!function_exists('stageArchiveTraceEnsureEnvLoaded')) {
    function stageArchiveTraceEnsureEnvLoaded(): void
    {
        if (!empty($_ENV['SUPABASE_URL']) && !empty($_ENV['SUPABASE_KEY'])) {
            return;
        }

        if (class_exists('Dotenv\\Dotenv')) {
            $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
            $dotenv->safeLoad();
        }
    }
}

if (!function_exists('stageArchiveTraceBaseUrl')) {
    function stageArchiveTraceBaseUrl(): string
    {
        stageArchiveTraceEnsureEnvLoaded();
        return rtrim((string) ($_ENV['SUPABASE_URL'] ?? ''), '/') . '/rest/v1';
    }
}

if (!function_exists('stageArchiveTraceApiKey')) {
    function stageArchiveTraceApiKey(): string
    {
        stageArchiveTraceEnsureEnvLoaded();
        return (string) ($_ENV['SUPABASE_KEY'] ?? '');
    }
}

if (!function_exists('stageArchiveLogTrace')) {
    function stageArchiveLogTrace(string $action, ?string $details = null, ?int $userId = null): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $apiKey = stageArchiveTraceApiKey();
        if ($apiKey === '') {
            return;
        }

        $resolvedUserId = $userId ?? (int) ($_SESSION['user_id'] ?? 0);
        $payload = [
            'action' => $action,
            'details' => $details,
        ];

        if ($resolvedUserId > 0) {
            $payload['user_id'] = $resolvedUserId;
        }

        supabaseRestRequest(
            'POST',
            stageArchiveTraceBaseUrl() . '/traces',
            $apiKey,
            $payload
        );
    }
}

if (!function_exists('stageArchiveLogPageAccess')) {
    function stageArchiveLogPageAccess(string $page): void
    {
        stageArchiveLogTrace('access', $page);
    }
}

if (!function_exists('stageArchiveCountTracesForUser')) {
    function stageArchiveCountTracesForUser(int $userId, ?string $action = null): int
    {
        $apiKey = stageArchiveTraceApiKey();
        if ($apiKey === '' || $userId <= 0) {
            return 0;
        }

        $url = stageArchiveTraceBaseUrl() . '/traces?user_id=eq.' . $userId . '&select=id';
        if ($action !== null && $action !== '') {
            $url .= '&action=eq.' . rawurlencode($action);
        }

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
