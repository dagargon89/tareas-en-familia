<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

// 404 uniforme: JSON `NO_ENCONTRADO` en /api/*, HTML fuera de la API.
$routes->set404Override('App\Controllers\Errores::noEncontrado');

$routes->group('api/v1', ['namespace' => 'App\Controllers\Api\V1'], static function (RouteCollection $routes): void {
    $routes->get('salud', 'SaludController::index');   // E-01
    $routes->get('csrf', 'CsrfController::index');     // E-02
});
