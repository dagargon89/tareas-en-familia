<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Configuración del asistente IA con Gemini Developer API (ADR-012).
 *
 * La clave `gemini.apiKey` vive solo en `.env` del servidor; nunca se expone al navegador.
 */
class Gemini extends BaseConfig
{
    public string $apiKey = '';

    public string $model = 'gemini-3.5-flash-lite';

    public int $timeout = 15;

    public int $maxPorMinuto = 6;

    public int $maxPorHora = 20;

    public string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models';
}
