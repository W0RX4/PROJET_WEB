<?php
    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/../supabaseQuery/restClient.php';

    use Dotenv\Dotenv;

    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: /login');
        exit;
    }

    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $apiKey = $_ENV['SUPABASE_KEY'] ?? '';
    $baseUrl = rtrim($_ENV['SUPABASE_URL'] ?? '', '/') . '/rest/v1';

    $usersResult = supabaseRestRequest(
        'GET',
        $baseUrl . '/users?email=eq.' . rawurlencode($email) . '&select=id,email,username,password,type&limit=1',
        $apiKey
    );

    if (!$usersResult['ok']) {
        $_SESSION['error'] = supabaseRestErrorMessage($usersResult, 'Impossible de vérifier vos identifiants pour le moment.');
        session_write_close();
        header('Location: /login');
        exit;
    }

    $users = is_array($usersResult['data']) ? $usersResult['data'] : [];
    $user = $users[0] ?? null;

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
