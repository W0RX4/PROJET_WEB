<?php
    session_start();
    if($_SESSION['type'] !== 'entreprise'){
        header('Location: ../connection/login.php');
        exit;
    }
    
?>