<?php
    session_start();
    if($_SESSION['type'] !== 'tuteur'){
        header('Location: ../connection/login.php');
        exit;
    }
    
?>