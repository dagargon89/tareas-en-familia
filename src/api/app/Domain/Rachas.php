<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Reglas de la racha semanal (RF-10, doc 03 §4). Sin acceso a datos: las usa `RachasService`
 * al cerrar la semana y `ProgresoService` al contar la racha.
 */
final class Rachas
{
    /**
     * Porcentaje de instancias aprobadas con 2 decimales (lo que se guarda en `rachas.porcentaje`).
     */
    public static function porcentaje(int $asignadas, int $aprobadas): float
    {
        return $asignadas <= 0 ? 0.0 : round($aprobadas * 100 / $asignadas, 2);
    }

    /**
     * Si la semana cuenta para la racha con el umbral dado (porcentaje entero, 1 a 100).
     * Una semana sin instancias asignadas no cuenta ni rompe: devuelve null y no se guarda fila.
     */
    public static function cumplida(int $asignadas, int $aprobadas, int $umbral): ?bool
    {
        if ($asignadas <= 0) {
            return null;
        }

        return self::porcentaje($asignadas, $aprobadas) >= $umbral;
    }

    /**
     * Semanas cumplidas consecutivas contando desde la más reciente hacia atrás.
     *
     * @param list<bool|null> $semanas De la más antigua a la más reciente; null = semana sin instancias (se salta).
     */
    public static function contar(array $semanas): int
    {
        $racha = 0;

        foreach (array_reverse($semanas) as $cumplida) {
            if ($cumplida === null) {
                continue;
            }

            if (! $cumplida) {
                break;
            }

            $racha++;
        }

        return $racha;
    }
}
