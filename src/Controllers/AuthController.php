<?php

namespace App\Controllers;

use App\Services\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController
{
    /**
     * Handle login request
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function login(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';
        
        if (AuthService::login($username, $password)) {
            $response->getBody()->write(json_encode(['success' => true]));
            return $response->withHeader('Content-Type', 'application/json');
        } else {
            $response->getBody()->write(json_encode(['error' => 'Invalid credentials']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }
    }
    
    /**
     * Handle logout request
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function logout(Request $request, Response $response): Response
    {
        AuthService::logout();
        $response->getBody()->write(json_encode(['success' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
