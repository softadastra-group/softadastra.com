<?php

use Modules\User\Core\Http\Controllers\HomeController;
use Ivi\Http\JsonResponse;
use Modules\User\Core\Http\Controllers\AuthController;

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

$router->get('/user', [HomeController::class, 'index']);

$router->get('/user/login', [AuthController::class, 'showLoginForm']);
$router->post('/user/login', [AuthController::class, 'handleLogin']);

$router->get('/user/login/email', [AuthController::class, 'showEmailLoginForm']);
$router->get('/user/login/google/callback', [AuthController::class, 'handleGoogleCallback']);

$router->get('/user/register', [AuthController::class, 'showRegistrationForm']);
$router->post('/user/register', [AuthController::class, 'handleRegistration']);

$router->get('/user/finalize-registration', [AuthController::class, 'showFinalizeRegistrationForm']);
$router->post('/user/finalize-registration', [AuthController::class, 'handleFinalizeRegistration']);

$router->get('/user/logout', [AuthController::class, 'logout']);

$router->get('/user/api/auth/me', [AuthController::class, 'currentUser']);
$router->get('/user/google-login-url', [AuthController::class, 'generateGoogleLoginUrl']);


$router->get('/user/ping', fn() => new JsonResponse([
    'ok' => true,
    'module' => 'User/Core'
]));
