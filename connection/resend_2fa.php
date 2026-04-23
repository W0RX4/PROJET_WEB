<?php
    require_once __DIR__ . '/sendMail.php';

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['pending_2fa'])) {
        header('Location: /login');
        exit;
    }

    // Prevent spamming: min 30s between resends
    $lastSent = $_SESSION['pending_2fa']['last_sent'] ?? 0;
    if (time() - $lastSent < 30) {
        $_SESSION['error'] = 'Merci de patienter avant de redemander un code.';
        header('Location: /verify-2fa');
        exit;
    }

    $code = generateVerificationCode();
    $_SESSION['pending_2fa']['code_hash'] = password_hash($code, PASSWORD_BCRYPT);
    $_SESSION['pending_2fa']['expires']   = time() + 600;
    $_SESSION['pending_2fa']['attempts']  = 0;
    $_SESSION['pending_2fa']['last_sent'] = time();

    $sent = sendVerificationEmail(
        $_SESSION['pending_2fa']['email'],
        $_SESSION['pending_2fa']['username'] ?? '',
        $code
    );

    $_SESSION[$sent ? 'success' : 'error'] = $sent
        ? 'Un nouveau code vient de vous etre envoye.'
        : "Impossible d'envoyer l'email. Reessayez plus tard.";

    session_write_close();
    header('Location: /verify-2fa');
    exit;
?>
