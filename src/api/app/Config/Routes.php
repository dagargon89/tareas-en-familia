<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

// 404 uniforme: JSON `NO_ENCONTRADO` en /api/*, HTML fuera de la API.
$routes->set404Override('App\Controllers\Errores::noEncontrado');

$opciones = ['namespace' => 'App\Controllers\Api\V1'];

$routes->group('api/v1', $opciones, static function (RouteCollection $routes) use ($opciones): void {
    // Públicas
    $routes->get('salud', 'SaludController::index');        // E-01
    $routes->get('csrf', 'CsrfController::index');          // E-02
    $routes->post('auth/login', 'AuthController::login');   // E-03

    // Sesión de dispositivo (filtro `sesion`)
    $routes->group('', $opciones + ['filter' => 'sesion'], static function (RouteCollection $routes): void {
        $routes->post('auth/logout', 'AuthController::logout');                 // E-04
        $routes->get('sesion', 'AuthController::sesion');                       // E-05
        $routes->get('perfiles', 'PerfilesController::index');                  // E-06
        $routes->post('perfiles/salir', 'PerfilesController::salir');           // E-08
        $routes->post('perfiles/(:num)/entrar', 'PerfilesController::entrar/$1'); // E-07
        $routes->post('modo-padre', 'ModoPadreController::entrar');             // E-09
        $routes->delete('modo-padre', 'ModoPadreController::salir');            // E-10
    });

    // Administración (filtros `sesion` + `modoPadre`). Las rutas nuevas van ANTES del comodín final.
    $routes->group('admin', $opciones + ['filter' => ['sesion', 'modoPadre']], static function (RouteCollection $routes): void {
        $routes->get('perfiles', 'PerfilesController::adminIndex');                        // E-23
        $routes->post('perfiles', 'PerfilesController::crear');                            // E-24
        $routes->put('perfiles/(:num)', 'PerfilesController::editar/$1');                  // E-25
        $routes->post('perfiles/(:num)/desbloquear', 'PerfilesController::desbloquear/$1'); // E-26
        $routes->delete('perfiles/(:num)', 'PerfilesController::borrar/$1');               // E-27

        // Comodín: cualquier otra ruta admin/* exige sesión y modo padre (403) antes de responder 404.
        $routes->match(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], '(:any)', '\App\Controllers\Errores::noEncontrado');
    });
});
