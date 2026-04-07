<?php
    session_start();
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    use Slim\Factory\AppFactory;
    use Slim\Views\PhpRenderer;
    use Psr\Http\Message\ResponseInterface as Response;
    use Psr\Http\Message\ServerRequestInterface as Request;

    require __DIR__ . '/vendor/autoload.php';
    require __DIR__ . '/middleware.php';

    // 1. Initialisation de l'application Slim
    $app = AppFactory::create();

    // 2. Configuration du moteur de rendu PHP (PhpRenderer)
    // Cela permet à Slim de chercher vos vues dans le dossier parent (racine)
    $renderer = new PhpRenderer(__DIR__);

    // --- ROUTES PUBLIQUES (Connexion & Inscription) ---

    $app->get('/', function (Request $request, Response $response, $args) {
        return $response->withHeader('Location', '/connection/login.php')->withStatus(302);
    });

    $app->get('/login', function (Request $request, Response $response, $args) use ($renderer) {
        return $renderer->render($response, '/connection/login.php', $args);
    });

    $app->post('/login', function (Request $request, Response $response, $args) {
        // Cette route traitera les données POST.
        // Pour l'instant on fait juste un include du fichier qui gère la logique
        require __DIR__ . '/connection/verifConnction.php';
        return $response;
    });

    $app->get('/register', function (Request $request, Response $response, $args) use ($renderer) {
        return $renderer->render($response, '/connection/create_user.php', $args);
    });

    $app->post('/register', function (Request $request, Response $response, $args) {
        // Fait appel au fichier contenant la logique (ex: verifNewUser.php)
        require __DIR__ . '/connection/verifNewUser.php';
        return $response;
    });

    $app->get('/logout', function (Request $request, Response $response, $args) {
        require __DIR__ . '/connection/logout.php';
        return $response;
    });

    // --- ROUTES PROTEGEES (App) ---

    // Route pour l'admin
    $app->get('/app/admin/accueilAdmin.php', function (Request $request, Response $response, $args) use ($renderer) {
        return $renderer->render($response, '/app/admin/accueilAdmin.php', $args);
    })->add($authMiddleware);

    // Routes pour l'entreprise
    $app->get('/app/entreprise/accueilEntreprise.php', function (Request $request, Response $response, $args) use ($renderer) {
        return $renderer->render($response, '/app/entreprise/accueilEntreprise.php', $args);
    })->add($authMiddleware);
    $app->get('/app/entreprise/ajouterStage.php', function (Request $request, Response $response, $args) use ($renderer) {
        return $renderer->render($response, '/app/entreprise/ajouterStage.php', $args);
    })->add($authMiddleware);

    // Routes pour l'utilisateur (étudiant)
    $app->get('/app/user/accueilUser.php', function (Request $request, Response $response, $args) use ($renderer) {
        return $renderer->render($response, '/app/user/accueilUser.php', $args);
    })->add($authMiddleware);

    //Routes pour l'admin (gestion des comptes)
    $app->get('/app/admin/gestionComptes.php', function (Request $request, Response $response, $args) use ($renderer) {
    return $renderer->render($response, '/app/admin/gestionComptes.php', $args);
    })->add($authMiddleware);

    $app->post('/app/admin/gestionComptes.php', function (Request $request, Response $response, $args) use ($renderer) {
    return $renderer->render($response, '/app/admin/gestionComptes.php', $args);
    })->add($authMiddleware);

    // Routes pour l'admin (gestion des offres)
    $app->get('/app/admin/gestionOffres.php', function (Request $request, Response $response, $args) use ($renderer) {
    return $renderer->render($response, '/app/admin/gestionOffres.php', $args);
    })->add($authMiddleware);

    $app->post('/app/admin/gestionOffres.php', function (Request $request, Response $response, $args) use ($renderer) {
    return $renderer->render($response, '/app/admin/gestionOffres.php', $args);

})->add($authMiddleware);
    // 3. Exécution de l'application
    $app->run();
?>