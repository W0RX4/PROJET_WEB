<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Supabase\Client\Functions;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

function addUserSupabase($email, $username, $password, $type) {
    $client = new Functions($_ENV['SUPABASE_URL'] ?? '', $_ENV['SUPABASE_KEY'] ?? '');
    $user = [
        'email' => $email,
        'username' => $username,
        // On vérifie que le mot de passe n'est pas nul avant le hash
        'password' => password_hash($password ?? '', PASSWORD_BCRYPT),
        'type' => $type,
    ];

    return $client->postData('user', $user);
}

// Exemple d'appel :
// addUserSupabase($client, 'test@example.com', 'Pseudo', '123456', 'admin');
?>