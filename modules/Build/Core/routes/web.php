<?php
use Modules\Build\Core\Http\Controllers\HomeController;
use Ivi\Http\JsonResponse;

/** @var \Ivi\Core\Router\Router $router */
$router->get('/build', [HomeController::class, 'index']);
$router->get('/build/ping', fn() => new JsonResponse([
    'ok' => true,
    'module' => 'Build/Core'
]));