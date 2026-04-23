<?php

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/../supabaseQuery/addUserSupabase.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: /register');
        exit;
    }

    $result = addUserSupabase(
        $_POST['email']    ?? '',
        $_POST['username'] ?? '',
        $_POST['password'] ?? '',
        $_POST['type']     ?? ''
    );

    if (is_array($result) && isset($result['code']) && !isset($result[0])) {
        $_SESSION['error'] = 'Erreur lors de la creation du compte : ' . ($result['message'] ?? 'inconnue');
        header('Location: /register');
        exit;
    }

    $_SESSION['success'] = 'Compte cree avec succes. Vous pouvez maintenant vous connecter.';
    header('Location: /login');
    exit;
?>
