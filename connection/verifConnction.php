<?php
    require_once __DIR__ . '/../vendor/autoload.php';

    use Dotenv\Dotenv;
    use Supabase\Client\Functions;

    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: /login');
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
        $_SESSION['user_id'] = $user['id'] ?? null;
        $_SESSION['email'] = $email;
        $_SESSION['username'] = $user['username'] ?? '';
        $_SESSION['type'] = $user['type'] ?? '';
        session_write_close();
        if ($_SESSION['type'] === 'etudiant') {
            header('Location: /app/user/accueilUser.php');
        } elseif ($_SESSION['type'] === 'entreprise') {
            header('Location: /app/entreprise/accueilEntreprise.php');
        } elseif ($_SESSION['type'] === 'tuteur') {
            header('Location: /app/tuteur/accueilTuteur.php');
        } elseif ($_SESSION['type'] === 'jury') {
            header('Location: /app/jury/accueilJury.php');
        } elseif ($_SESSION['type'] === 'admin') {
            header('Location: /app/admin/accueilAdmin.php');
        } else {
            header('Location: /login');
        }
        exit;
    }
    $_SESSION['error'] = 'Invalid email or password';
    session_write_close();
    header('Location: /login');
    exit;
?>