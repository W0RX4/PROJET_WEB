<?php
// Fichier qui controle l acces aux pages protegees.
    use Psr\Http\Message\ServerRequestInterface as Request;
    // On importe les classes utilisees dans ce fichier.
    use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
    // On importe les classes utilisees dans ce fichier.
    use Slim\Psr7\Response;

    $authMiddleware = function(Request $request, RequestHandler $handler) {

        // On demarre la session si elle n existe pas encore.
        if(session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // On verifie que l utilisateur a le droit d acceder a cette page.
        if (!isset($_SESSION['user_id'])) {
            $response = new Response();
            $response->getBody()->write('Connexion non autorisée. Veuillez vous connecter.');
            return $response->withHeader('Location', '/login')->withStatus(403);
        }
        return $handler->handle($request);
    };

?>