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

    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $client = new Functions($_ENV['SUPABASE_URL'] ?? '', $_ENV['SUPABASE_KEY'] ?? '');
    $users = $client->getAllData('users');

    $user = null;
    foreach ($users as $item) {
        if (($item['email'] ?? '') === $email) {
            $user = $item;
            break;
        }
    }

    if ($user && password_verify($password, $user['password'])) {
        $_POST['email'] = $email; // Passer l'email à la page d'accueil
        $_POST['username'] = $user['username'] ?? '';
        header('Location: ../app/accueil.php');
        exit;
    }

    echo 'Email ou mot de passe incorrect.';
?>