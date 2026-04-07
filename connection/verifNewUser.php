<?php

    require_once __DIR__ . '/../vendor/autoload.php';

    use Dotenv\Dotenv;
    use Supabase\Client\Functions;

    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: create_user.php');
        exit;
    }

    $client = new Functions($_ENV['SUPABASE_URL'] ?? '', $_ENV['SUPABASE_KEY'] ?? '');
    $users = [
        'email' => $_POST['email'] ?? '',
        'username' => $_POST['username'] ?? '',
        'password' => password_hash($_POST['password'] ?? '', PASSWORD_BCRYPT),
        'type' => $_POST['type'] ?? '',
    ];

    $result = $client->postData('users', $users);

    if (is_string($result) && str_contains($result, '"code"')) {
        $_SESSION['error'] = 'Erreur lors de la création du compte.';
        header('Location: /register');
        exit;
    }

    $_SESSION['success'] = 'Compte créé avec succès. Vous pouvez maintenant vous connecter.';
    header('Location: /login');
    exit;

?>