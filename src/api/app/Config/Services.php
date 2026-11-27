<?php

namespace Config;

use App\Libraries\GeminiCliente;
use App\Libraries\GeminiClienteInterface;
use App\Services\EvidenciasService;
use CodeIgniter\Config\BaseService;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    /**
     * Fotos de evidencia (RF-06). Servicio para poder sustituir en pruebas la comprobación
     * `is_uploaded_file()`, que en CLI siempre es falsa.
     */
    public static function evidencias(bool $getShared = true): EvidenciasService
    {
        if ($getShared) {
            return static::getSharedInstance('evidencias');
        }

        return new EvidenciasService(db_connect());
    }

    /**
     * Cliente de Gemini Developer API (ADR-012). Registrado como servicio para sustituirlo en PHPUnit.
     */
    public static function geminiCliente(bool $getShared = true): GeminiClienteInterface
    {
        if ($getShared) {
            return static::getSharedInstance('geminiCliente');
        }

        return new GeminiCliente();
    }

    /*
     * public static function example($getShared = true)
     * {
     *     if ($getShared) {
     *         return static::getSharedInstance('example');
     *     }
     *
     *     return new \CodeIgniter\Example();
     * }
     */
}
