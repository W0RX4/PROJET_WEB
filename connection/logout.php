<?php
require_once __DIR__ . '/../supabaseQuery/authClient.php';

session_start();

$accessToken = (string) ($_SESSION['supabase_access_token'] ?? '');
if ($accessToken !== '') {
    supabaseAuthLogout($accessToken);
}

session_destroy();
header('Location: /login');
exit;
?>
