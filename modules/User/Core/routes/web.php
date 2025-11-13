<?php

use Modules\User\Core\Http\Controllers\HomeController;
use Ivi\Http\JsonResponse;
use Modules\User\Core\Http\Controllers\AuthController;

/** @var \Ivi\Core\Router\Router $router */
$router->get('/user', [HomeController::class, 'index']);
$router->get('/user/login', [AuthController::class, 'login']);
$router->post('/user/login', [AuthController::class, 'postLogin']);
$router->get('/user/login/email', [AuthController::class, 'loginEmail']);
$router->get('/user/loginPost', [AuthController::class, 'google']);
$router->get('/user/register', [AuthController::class, 'register']);
$router->post('/user/register', [AuthController::class, 'postRegister']);
$router->get('/user/finalize-registration', [AuthController::class, 'finalizeRegistration']);
$router->post('/user/finalize-registration', [AuthController::class, 'finalizeRegistrationPOST']);
$router->get('/userlogout', [AuthController::class, 'logout']);
$router->get('/user/api/auth/me', [AuthController::class, 'me']);
$router->get('/user/google-login-url', [AuthController::class, 'getGoogleLoginUrl']);

$router->get('/user/ping', fn() => new JsonResponse([
    'ok' => true,
    'module' => 'User/Core'
]));
