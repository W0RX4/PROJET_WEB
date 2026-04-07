<?php

    session_start();

    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/../supabaseQuery/addUserSupabase.php';


    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: create_user.php');
        exit;
    }


    $result = addUserSupabase($_POST['email'], $_POST['username'], $_POST['password'], $_POST['type']);
    $resultData = is_string($result) && $result !== '' ? json_decode($result, true) : null;

    if (is_array($resultData) && isset($resultData['code'])) {
        $_SESSION['error'] = 'Erreur lors de la création du compte : ' . ($resultData['message'] ?? 'inconnue');
        header('Location: /register');
        exit;
    }

    $_SESSION['success'] = 'Compte créé avec succès. Vous pouvez maintenant vous connecter.';
    header('Location: /login');
    exit;

?>