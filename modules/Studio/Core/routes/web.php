<?php
use Modules\Studio\Core\Http\Controllers\HomeController;
use Ivi\Http\JsonResponse;

/** @var \Ivi\Core\Router\Router $router */
$router->get('/studio', [HomeController::class, 'index']);
$router->get('/studio/ping', fn() => new JsonResponse([
    'ok' => true,
    'module' => 'Studio/Core'
]));