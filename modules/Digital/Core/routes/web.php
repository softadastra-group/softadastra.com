<?php
use Modules\Digital\Core\Http\Controllers\HomeController;
use Ivi\Http\JsonResponse;

/** @var \Ivi\Core\Router\Router $router */
$router->get('/digital', [HomeController::class, 'index']);
$router->get('/digital/ping', fn() => new JsonResponse([
    'ok' => true,
    'module' => 'Digital/Core'
]));