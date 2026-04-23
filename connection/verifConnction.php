<?php
    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/sendMail.php';
    require_once __DIR__ . '/../supabaseQuery/restClient.php';

    use Dotenv\Dotenv;

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

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

    if (!$user || !password_verify($password, $user['password'])) {
        $_SESSION['error'] = 'Email ou mot de passe incorrect.';
        session_write_close();
        header('Location: /login');
        exit;
    }

    // Generate 6-digit code with 10-minute expiration
    $code = generateVerificationCode();
    $_SESSION['pending_2fa'] = [
        'user_id'  => $user['id'] ?? null,
        'email'    => $email,
        'username' => $user['username'] ?? '',
        'type'     => $user['type'] ?? '',
        'code_hash'=> password_hash($code, PASSWORD_BCRYPT),
        'expires'  => time() + 600,
        'attempts' => 0,
    ];

    $sent = sendVerificationEmail($email, $user['username'] ?? '', $code);

    if (!$sent) {
        unset($_SESSION['pending_2fa']);
        $_SESSION['error'] = "Impossible d'envoyer l'email de verification. Reessayez plus tard.";
        session_write_close();
        header('Location: /login');
        exit;
    }

    $_SESSION['success'] = 'Un code de verification vient de vous etre envoye par email.';
    session_write_close();
    header('Location: /verify-2fa');
    exit;
?>
