<?php
// Fichier qui traite la creation d un nouveau compte.

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // On charge les fichiers necessaires.
    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/../supabaseQuery/addUserSupabase.php';

    // On refuse les acces qui ne viennent pas du formulaire.
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: /register');
        exit;
    }

    // On charge les fichiers necessaires.
    require_once __DIR__ . '/../supabaseQuery/restClient.php';

    // On importe les classes utilisees dans ce fichier.
    use Dotenv\Dotenv;

    // On verifie cette condition.
    if (!isset($_ENV['SUPABASE_URL'])) {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->safeLoad();
    }

    $requestedType = strtolower(trim((string) ($_POST['type'] ?? '')));
    // On prepare les donnees utilisees dans ce bloc.
    $publicAllowed = ['etudiant', 'tuteur', 'entreprise', 'jury', 'admin'];
    // On verifie cette condition.
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

    // On verifie cette condition.
    if (is_array($result) && isset($result['code']) && !isset($result[0])) {
        $_SESSION['error'] = 'Erreur lors de la creation du compte : ' . ($result['message'] ?? 'inconnue');
        header('Location: /register');
        exit;
    }

    // On verifie cette condition.
    if ($requestedType === 'admin') {
        $apiKey = (string) ($_ENV['SUPABASE_KEY'] ?? '');
        $baseUrl = rtrim((string) ($_ENV['SUPABASE_URL'] ?? ''), '/') . '/rest/v1';
        $createdId = (int) ($result['id'] ?? 0);

        // On verifie cette condition.
        if ($createdId > 0) {
            // On appelle Supabase pour lire ou modifier les donnees.
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
