<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Configuración de Firebase para el chat familiar (ADR-011).
 *
 * En desarrollo se puede activar `firebase.emulador = true` para usar el
 * Firebase Emulator Suite sin credenciales de producción.
 */
class Firebase extends BaseConfig
{
    public bool $habilitado = true;

    public bool $emulador = false;

    public string $projectId = 'tareas-en-familia-local';

    public string $apiKey = 'demo-tareas-api-key';

    public string $clientEmail = '';

    public string $privateKey = '';

    public string $authEmulatorUrl = 'http://127.0.0.1:9099';

    public string $firestoreEmulatorHost = '127.0.0.1';

    public int $firestoreEmulatorPort = 8088;
}
