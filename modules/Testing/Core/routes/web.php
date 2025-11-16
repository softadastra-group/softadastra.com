<?php
use Modules\Testing\Core\Http\Controllers\HomeController;
use Ivi\Http\JsonResponse;

/** @var \Ivi\Core\Router\Router $router */
$router->get('/testing', [HomeController::class, 'index']);
$router->get('/testing/ping', fn() => new JsonResponse([
    'ok' => true,
    'module' => 'Testing/Core'
]));