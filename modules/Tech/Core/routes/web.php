<?php
use Modules\Tech\Core\Http\Controllers\HomeController;
use Ivi\Http\JsonResponse;

/** @var \Ivi\Core\Router\Router $router */
$router->get('/tech', [HomeController::class, 'index']);
$router->get('/tech/ping', fn() => new JsonResponse([
    'ok' => true,
    'module' => 'Tech/Core'
]));