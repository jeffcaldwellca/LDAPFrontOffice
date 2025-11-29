<?php

namespace App\Controllers;

use App\Services\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class HomeController
{
    /**
     * Show home page
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function index(Request $request, Response $response): Response
    {
        $view = Twig::fromRequest($request);
        $data = [];
        
        if (AuthService::isLoggedIn()) {
            $data['authenticated'] = true;
            $data['currentUser'] = AuthService::getCurrentUser();
        } else {
            $data['authenticated'] = false;
        }
        
        return $view->render($response, 'index.html', $data);
    }
}
