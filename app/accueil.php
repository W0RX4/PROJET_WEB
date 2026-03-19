<?php
    $email = $_POST['email'] ?? '';
    $username = $_POST['username'] ?? '';
    echo "Bienvenue sur la page d'accueil " . $username . " !";
?>