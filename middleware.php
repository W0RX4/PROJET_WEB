<?php
    use Psr\Http\Message\ServerRequestInterface as Request;
    use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
    use Slim\Psr7\Response;

    $authMiddleware = function(Request $request, RequestHandler $handler) {

        if(session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            $response = new Response();
            $response->getBody()->write('Connexion non autorisée. Veuillez vous connecter.');
            return $response->withHeader('Location', '/login')->withStatus(403);
        }
        return $handler->handle($request);
    };

?>