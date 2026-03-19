<?php
    session_start();

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
        $_SESSION['email'] = $email;
        $_SESSION['username'] = $user['username'] ?? '';
        $_SESSION['type'] = $user['type'] ?? '';
        if ($_SESSION['type'] === 'etudiant') {
            header('Location: ../app/accueilUser.php');
        } else {
            if($_SESSION['type'] === 'entreprise'){
                header('Location: ../app/accueilEntreprise.php');
            } else {
                header('Location: ../app/accueilJury.php');
            }
        }
        exit;
    }
    $_SESSION['error'] = 'Invalid email or password';
    header('Location: login.php');
?>