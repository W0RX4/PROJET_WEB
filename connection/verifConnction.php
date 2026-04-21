<?php
    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/sendMail.php';

    use Dotenv\Dotenv;
    use Supabase\Client\Functions;

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

    $client = new Functions($_ENV['SUPABASE_URL'] ?? '', $_ENV['SUPABASE_KEY'] ?? '');
    $users = $client->getAllData('users');

    $user = null;
    foreach ($users as $item) {
        if (($item['email'] ?? '') === $email) {
            $user = $item;
            break;
        }
    }

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
