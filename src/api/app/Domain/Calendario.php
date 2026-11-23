<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Fechas de calendario (`Y-m-d`) en la zona de la app. "Hoy" siempre se
 * calcula en America/Ciudad_Juarez (RN-06), no en UTC ni en la zona del servidor.
 */
final class Calendario
{
    public const ZONA = 'America/Ciudad_Juarez';

    public static function hoy(DateTimeInterface $instante): string
    {
        return DateTimeImmutable::createFromInterface($instante)
            ->setTimezone(new DateTimeZone(self::ZONA))
            ->format('Y-m-d');
    }

    public static function sumarDias(string $fecha, int $dias): string
    {
        return self::fecha($fecha)->modify(sprintf('%+d days', $dias))->format('Y-m-d');
    }

    public static function diasEntre(string $desde, string $hasta): int
    {
        return (int) self::fecha($desde)->diff(self::fecha($hasta))->format('%r%a');
    }

    private static function fecha(string $fecha): DateTimeImmutable
    {
        return new DateTimeImmutable($fecha . ' 00:00:00', new DateTimeZone('UTC'));
    }
}
