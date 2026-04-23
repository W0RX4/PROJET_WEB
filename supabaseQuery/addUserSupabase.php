<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/restClient.php';

use Dotenv\Dotenv;

if (!isset($_ENV['SUPABASE_URL'])) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();
}

function addUserSupabase($email, $username, $password, $type) {
    $apiKey = $_ENV['SUPABASE_KEY'] ?? '';
    $baseUrl = rtrim($_ENV['SUPABASE_URL'] ?? '', '/') . '/rest/v1';

    $payload = [
        'email'    => $email,
        'username' => $username,
        'password' => password_hash($password ?? '', PASSWORD_BCRYPT),
        'type'     => $type,
    ];

    $result = supabaseRestRequest(
        'POST',
        $baseUrl . '/users',
        $apiKey,
        $payload,
        ['Prefer: return=representation']
    );

    if (!$result['ok']) {
        return [
            'code'    => $result['code'],
            'message' => supabaseRestErrorMessage($result, "Erreur lors de la creation du compte"),
        ];
    }

    return $result['data'];
}
