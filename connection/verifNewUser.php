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

    require_once __DIR__ . '/../supabaseQuery/restClient.php';

    use Dotenv\Dotenv;

    if (!isset($_ENV['SUPABASE_URL'])) {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->safeLoad();
    }

    $requestedType = strtolower(trim((string) ($_POST['type'] ?? '')));
    $publicAllowed = ['etudiant', 'tuteur', 'entreprise', 'jury', 'admin'];
    if (!in_array($requestedType, $publicAllowed, true)) {
        $_SESSION['error'] = 'Type de compte invalide.';
        header('Location: /register');
        exit;
    }

    $result = addUserSupabase(
        $_POST['email']    ?? '',
        $_POST['username'] ?? '',
        $_POST['password'] ?? '',
        $requestedType
    );

    if (is_array($result) && isset($result['code']) && !isset($result[0])) {
        $_SESSION['error'] = 'Erreur lors de la creation du compte : ' . ($result['message'] ?? 'inconnue');
        header('Location: /register');
        exit;
    }

    if ($requestedType === 'admin') {
        $apiKey = (string) ($_ENV['SUPABASE_KEY'] ?? '');
        $baseUrl = rtrim((string) ($_ENV['SUPABASE_URL'] ?? ''), '/') . '/rest/v1';
        $createdId = (int) ($result['id'] ?? 0);

        if ($createdId > 0) {
            supabaseRestRequest(
                'PATCH',
                "$baseUrl/users?id=eq.$createdId",
                $apiKey,
                ['admin_pending' => true]
            );
        }

        $_SESSION['success'] = 'Demande de compte administrateur enregistree. Un administrateur doit valider votre compte avant que vous puissiez vous connecter.';
        header('Location: /login');
        exit;
    }

    $_SESSION['success'] = 'Compte cree avec succes. Vous pouvez maintenant vous connecter.';
    header('Location: /login');
    exit;
?>
