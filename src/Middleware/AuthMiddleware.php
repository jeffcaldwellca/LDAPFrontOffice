<?php

namespace App\Middleware;

use App\Services\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response as SlimResponse;

class AuthMiddleware
{
    /**
     * Invoke middleware
     *
     * @param Request $request
     * @param RequestHandlerInterface $handler
     * @return Response
     */
    public function __invoke(Request $request, RequestHandlerInterface $handler): Response
    {
        if (!AuthService::isLoggedIn()) {
            $response = new SlimResponse();
            $response->getBody()->write(json_encode(['error' => 'Not authenticated']));
            return $response
                ->withStatus(401)
                ->withHeader('Content-Type', 'application/json');
        }

        return $handler->handle($request);
    }
}
