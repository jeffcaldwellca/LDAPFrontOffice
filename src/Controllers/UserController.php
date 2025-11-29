<?php

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\LDAPService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Exception;

class UserController
{
    /**
     * Search for users
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function search(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $searchTerm = $params['q'] ?? '';
        $users = [];
        $error = null;

        if (!empty($searchTerm)) {
            try {
                $ldap = new LDAPService();
                $users = $ldap->searchUsers($searchTerm);
            } catch (Exception $e) {
                $error = 'Server error: ' . $e->getMessage();
            }
        }

        $payload = json_encode([
            'users' => $users,
            'error' => $error
        ]);

        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
    }
    
    /**
     * Get user by username (HTML view)
     *
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $username = $args['username'];
        
        try {
            $ldap = new LDAPService();
            $user = $ldap->getUserByUsername($username);

            if (!$user) {
                $payload = ['error' => 'User not found'];
                $response->getBody()->write(json_encode($payload));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $view = Twig::fromRequest($request);
            $data = [
                'user' => $user,
                'authenticated' => true,
                'currentUser' => AuthService::getCurrentUser()
            ];
            return $view->render($response, 'index.html', $data);

        } catch (Exception $e) {
            $response->getBody()->write('Error loading user: ' . $e->getMessage());
            return $response->withStatus(500);
        }
    }
    
    /**
     * Get user by username (API/JSON)
     *
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function get(Request $request, Response $response, array $args): Response
    {
        $username = $args['username'];

        try {
            $ldap = new LDAPService();
            $user = $ldap->getUserByUsername($username);

            if (!$user) {
                $payload = ['error' => 'User not found'];
                $response->getBody()->write(json_encode($payload));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode($user));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (Exception $e) {
            error_log('API ERROR: ' . $e->getMessage());
            $payload = ['error' => $e->getMessage()];
            $response->getBody()->write(json_encode($payload));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
    
    /**
     * Update user information
     *
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $username = $args['username'];
        $data = $request->getParsedBody();
        error_log("Incoming POST for {$username}: " . json_encode($data));
        
        try {
            $ldap = new LDAPService();
            $ldap->updateUser($username, $data);
            
            $response->getBody()->write(json_encode(['success' => true]));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
    
    /**
     * Search for managers
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function searchManagers(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $searchTerm = $params['q'] ?? '';
        $users = [];
        $error = null;
        
        if (!empty($searchTerm)) {
            try {
                $ldap = new LDAPService();
                $users = $ldap->searchUsers($searchTerm, 10);
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
        
        $response->getBody()->write(json_encode([
            'users' => $users,
            'error' => $error
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }
}
