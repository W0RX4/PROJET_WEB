<?php
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: /verify-2fa');
        exit;
    }

    if (!isset($_SESSION['pending_2fa'])) {
        $_SESSION['error'] = 'Session expiree. Veuillez vous reconnecter.';
        header('Location: /login');
        exit;
    }

    $pending = $_SESSION['pending_2fa'];
    $submittedCode = preg_replace('/\D/', '', $_POST['code'] ?? '');

    // Expired ?
    if (time() > ($pending['expires'] ?? 0)) {
        unset($_SESSION['pending_2fa']);
        $_SESSION['error'] = 'Le code a expire. Veuillez vous reconnecter.';
        header('Location: /login');
        exit;
    }

    // Too many attempts ?
    if (($pending['attempts'] ?? 0) >= 5) {
        unset($_SESSION['pending_2fa']);
        $_SESSION['error'] = 'Trop de tentatives. Veuillez vous reconnecter.';
        header('Location: /login');
        exit;
    }

    if (strlen($submittedCode) !== 6 || !password_verify($submittedCode, $pending['code_hash'] ?? '')) {
        $_SESSION['pending_2fa']['attempts'] = ($pending['attempts'] ?? 0) + 1;
        $remaining = 5 - $_SESSION['pending_2fa']['attempts'];
        $_SESSION['error'] = 'Code incorrect. ' . ($remaining > 0 ? $remaining . ' tentative(s) restante(s).' : '');
        header('Location: /verify-2fa');
        exit;
    }

    // Success - complete login
    $_SESSION['user_id']  = $pending['user_id'];
    $_SESSION['email']    = $pending['email'];
    $_SESSION['username'] = $pending['username'];
    $_SESSION['type']     = $pending['type'];
    unset($_SESSION['pending_2fa']);

    // Regenerate session id to prevent fixation
    session_regenerate_id(true);

    switch ($_SESSION['type']) {
        case 'etudiant':    $dest = '/app/user/accueilUser.php'; break;
        case 'entreprise':  $dest = '/app/entreprise/accueilEntreprise.php'; break;
        case 'tuteur':      $dest = '/app/tuteur/accueilTuteur.php'; break;
        case 'jury':        $dest = '/app/jury/accueilJury.php'; break;
        case 'admin':       $dest = '/app/admin/accueilAdmin.php'; break;
        default:            $dest = '/login';
    }

    session_write_close();
    header('Location: ' . $dest);
    exit;
?>
