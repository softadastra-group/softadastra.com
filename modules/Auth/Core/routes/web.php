<?php

use Modules\Auth\Core\Http\Controllers\HomeController;
use Ivi\Http\JsonResponse;
use Modules\Auth\Core\Http\Controllers\AuthController;

/** @var \Ivi\Core\Router\Router $router */
// $router->get('/user', [HomeController::class, 'index']);
// $router->get('/user/login', [AuthController::class, 'index']);
// $router->post('/user/login', [AuthController::class, 'postLogin']);
// $router->get('/user/login/email', [AuthController::class, 'loginEmail']);
// $router->get('/user/loginPost', [AuthController::class, 'google']);
// $router->get('/user/register', [AuthController::class, 'register']);
// $router->post('/user/register', [AuthController::class, 'postRegister']);
// $router->get('/user/finalize-registration', [AuthController::class, 'finalizeRegistration']);
// $router->post('/user/finalize-registration', [AuthController::class, 'finalizeRegistrationPOST']);
// $router->get('/userlogout', [AuthController::class, 'logout']);
// $router->get('/user/api/auth/me', [AuthController::class, 'me']);
// $router->get('/user/google-login-url', [AuthController::class, 'getGoogleLoginUrl']);

$router->get('/auth', [HomeController::class, 'index']);

// Login
$router->get('/auth/login', [AuthController::class, 'showLoginForm']);
$router->post('/auth/login', [AuthController::class, 'handleLogin']);

// Email login
$router->get('/auth/login/email', [AuthController::class, 'showEmailLoginForm']);

// Google OAuth callback
$router->get('/auth/login/google/callback', [AuthController::class, 'handleGoogleCallback']);

// Registration
$router->get('/auth/register', [AuthController::class, 'showRegistrationForm']);
$router->post('/auth/register', [AuthController::class, 'handleRegistration']);

// Finalize registration (e.g., email verification / extra info)
$router->get('/auth/finalize-registration', [AuthController::class, 'showFinalizeRegistrationForm']);
$router->post('/auth/finalize-registration', [AuthController::class, 'handleFinalizeRegistration']);

// Logout
$router->get('/auth/logout', [AuthController::class, 'logout']);

// API routes
$router->get('/auth/api/me', [AuthController::class, 'currentUser']);
$router->get('/auth/google-login-url', [AuthController::class, 'generateGoogleLoginUrl']);


$router->get('/auth/ping', fn() => new JsonResponse([
    'ok' => true,
    'module' => 'auth/Core'
]));
