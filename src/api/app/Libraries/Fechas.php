<?php

declare(strict_types=1);

namespace App\Libraries;

use CodeIgniter\I18n\Time;

/**
 * Fechas en `America/Ciudad_Juarez` (zona de la app) y su forma en la API:
 * ISO 8601 con offset, p. ej. `2026-11-22T18:30:00-07:00` (doc 05 §1).
 */
final class Fechas
{
    /**
     * `DATETIME` local de la base → ISO 8601 con offset.
     */
    public static function iso(?string $fechaHora): ?string
    {
        if ($fechaHora === null || $fechaHora === '') {
            return null;
        }

        return Time::parse($fechaHora)->format(DATE_ATOM);
    }

    public static function isoDesdeTimestamp(int $timestamp): string
    {
        return Time::createFromTimestamp($timestamp, app_timezone())->format(DATE_ATOM);
    }

    /**
     * ISO del bloqueo solo si sigue vigente; un bloqueo vencido se expone como `null`.
     */
    public static function bloqueoVigente(?string $bloqueadoHasta): ?string
    {
        if ($bloqueadoHasta === null || $bloqueadoHasta === '') {
            return null;
        }

        return Time::parse($bloqueadoHasta)->isAfter(Time::now()) ? self::iso($bloqueadoHasta) : null;
    }
}
