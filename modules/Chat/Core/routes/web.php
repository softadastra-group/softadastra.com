<?php
use Modules\Chat\Core\Http\Controllers\HomeController;
use Ivi\Http\JsonResponse;

/** @var \Ivi\Core\Router\Router $router */
$router->get('/chat', [HomeController::class, 'index']);
$router->get('/chat/ping', fn() => new JsonResponse([
    'ok' => true,
    'module' => 'Chat/Core'
]));