<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Rachas semanales (RF-10, doc 03 §4). Sin dependencias.
 */
final class Racha
{
    /**
     * Una semana cuenta si se aprobó al menos el umbral (%) de las asignadas.
     */
    public static function semanaCumplida(int $asignadas, int $aprobadas, int $umbral): bool
    {
        return $asignadas > 0 && $aprobadas * 100 >= $umbral * $asignadas;
    }

    public static function porcentaje(int $asignadas, int $aprobadas): float
    {
        return $asignadas === 0 ? 0.0 : round($aprobadas * 100 / $asignadas, 2);
    }

    /**
     * Semanas cumplidas consecutivas hasta la más reciente.
     *
     * @param list<bool|null> $semanas De la más antigua a la más reciente; `null` = sin instancias
     *                                 asignadas (no rompe ni suma), `false` = no cumplida (reinicia)
     */
    public static function actual(array $semanas): int
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
