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
    $routes->get('instalacion', 'InstalacionController::estado');     // E-49
    $routes->post('instalacion', 'InstalacionController::instalar');  // E-50 (con CSRF)

    // Sesión de dispositivo (filtro `sesion`)
    $routes->group('', $opciones + ['filter' => 'sesion'], static function (RouteCollection $routes): void {
        $routes->post('auth/logout', 'AuthController::logout');                 // E-04
        $routes->get('sesion', 'AuthController::sesion');                       // E-05
        $routes->get('perfiles', 'PerfilesController::index');                  // E-06
        $routes->post('perfiles/salir', 'PerfilesController::salir');           // E-08
        $routes->post('perfiles/(:num)/entrar', 'PerfilesController::entrar/$1'); // E-07
        $routes->post('modo-padre', 'ModoPadreController::entrar');             // E-09
        $routes->delete('modo-padre', 'ModoPadreController::salir');            // E-10
        $routes->get('evidencias/(:num)', 'EvidenciasController::ver/$1');      // E-36 (dueña o modo padre)
    });

    // Hija con perfil activo (filtros `sesion` + `perfilActivo`)
    $routes->group('yo', $opciones + ['filter' => ['sesion', 'perfilActivo']], static function (RouteCollection $routes): void {
        $routes->get('dia', 'YoController::dia');                                  // E-11
        $routes->post('instancias/(:num)/hecha', 'YoController::marcarHecha/$1');  // E-12
        $routes->get('progreso', 'YoController::progreso');                        // E-13
        $routes->get('tareas', 'YoController::tareas');                            // E-18
        $routes->post('tareas', 'YoController::crearTarea');                       // E-19
        $routes->put('tareas/(:num)', 'YoController::editarTarea/$1');             // E-20
        $routes->delete('tareas/(:num)', 'YoController::desactivarTarea/$1');      // E-21
    });

    // Administración (filtros `sesion` + `modoPadre`). Las rutas nuevas van ANTES del comodín final.
    $routes->group('admin', $opciones + ['filter' => ['sesion', 'modoPadre']], static function (RouteCollection $routes): void {
        $routes->get('perfiles', 'PerfilesController::adminIndex');                        // E-23
        $routes->post('perfiles', 'PerfilesController::crear');                            // E-24
        $routes->put('perfiles/(:num)', 'PerfilesController::editar/$1');                  // E-25
        $routes->post('perfiles/(:num)/desbloquear', 'PerfilesController::desbloquear/$1'); // E-26
        $routes->delete('perfiles/(:num)', 'PerfilesController::borrar/$1');               // E-27
        $routes->get('tareas', 'TareasController::index');                                 // E-28
        $routes->post('tareas', 'TareasController::crear');                                // E-29
        $routes->put('tareas/(:num)', 'TareasController::editar/$1');                      // E-30
        $routes->delete('tareas/(:num)', 'TareasController::desactivar/$1');               // E-31
        $routes->get('categorias', 'TareasController::categorias');                        // E-32
        $routes->get('aprobaciones', 'AprobacionesController::index');                     // E-33
        $routes->post('instancias/revision-lote', 'AprobacionesController::lote');         // E-35
        $routes->post('instancias/(:num)/revision', 'AprobacionesController::revisar/$1'); // E-34

        // Comodín: cualquier otra ruta admin/* exige sesión y modo padre (403) antes de responder 404.
        $routes->match(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], '(:any)', '\App\Controllers\Errores::noEncontrado');
    });
});
