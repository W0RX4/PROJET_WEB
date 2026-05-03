<?php
// Fichier qui deconnecte l utilisateur et ferme sa session.
require_once __DIR__ . '/../supabaseQuery/authClient.php';

session_start();

$accessToken = (string) ($_SESSION['supabase_access_token'] ?? '');
// On verifie cette condition.
if ($accessToken !== '') {
    // On appelle Supabase Auth pour gerer l authentification.
    supabaseAuthLogout($accessToken);
}

session_destroy();
header('Location: /login');
exit;
?>
