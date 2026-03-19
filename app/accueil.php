<?php
    session_start();

    if (!isset($_SESSION['username'])) {
        header('Location: ../connection/login.php');
        exit;
    }

    $email = $_SESSION['email'] ?? '';
    $username = $_SESSION['username'] ?? '';
    echo "Bienvenue sur la page d'accueil " . $username . " !";

    if (isset($_SESSION['type']) && $_SESSION['type'] === 'entreprise') {
        echo "Vous êtes connecté en tant qu'entreprise.";

        
        
    }
?>