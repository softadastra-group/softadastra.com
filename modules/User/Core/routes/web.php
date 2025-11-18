<?php
use Modules\User\Core\Http\Controllers\HomeController;
use Ivi\Http\JsonResponse;

/** @var \Ivi\Core\Router\Router $router */
$router->get('/user', [HomeController::class, 'index']);
$router->get('/user/ping', fn() => new JsonResponse([
    'ok' => true,
    'module' => 'User/Core'
]));