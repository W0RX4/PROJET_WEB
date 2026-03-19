<?php
    session_start();
    if($_SESSION['type'] !== 'etudiant'){
        header('Location: ../connection/login.php');
        exit;
    }
    

    $email = $_SESSION['email'] ?? '';
    $username = $_SESSION['username'] ?? '';
    echo "Bienvenue sur la page d'accueil " . $username . " !";

    
?>