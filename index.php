<?php

/**
 * LDAP Front-Office User Management System
 * 
 * Bootstrap and route configuration
 */

require_once 'vendor/autoload.php';

use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use App\Controllers\HomeController;
use App\Controllers\AuthController;
use App\Controllers\UserController;
use App\Middleware\AuthMiddleware;

// Start session
session_start();

// Create Slim app
$app = AppFactory::create();
$app->addBodyParsingMiddleware();

// Add error middleware
$app->addErrorMiddleware(true, true, true);

// Create Twig view
$twig = Twig::create('templates', ['cache' => false]);
$app->add(TwigMiddleware::create($app, $twig));

// Create middleware instance
$authMiddleware = new AuthMiddleware();

// Routes - Public
$app->get('/', [HomeController::class, 'index']);
$app->post('/login', [AuthController::class, 'login']);
$app->post('/logout', [AuthController::class, 'logout']);

// Routes - Protected (require authentication)
$app->get('/search', [UserController::class, 'search'])->add($authMiddleware);
$app->get('/user/{username}', [UserController::class, 'show'])->add($authMiddleware);
$app->post('/user/{username}', [UserController::class, 'update'])->add($authMiddleware);
$app->get('/search-managers', [UserController::class, 'searchManagers'])->add($authMiddleware);
$app->get('/api/user/{username}', [UserController::class, 'get'])->add($authMiddleware);

// Run application
$app->run();
