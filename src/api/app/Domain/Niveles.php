<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Niveles por XP (RF-08, doc 03 §4): Semilla 0, Brote 100, Raíz 300,
 * Árbol 700, Bosque 1500, Selva 3000. Forma `Nivel` del contrato.
 */
final class Niveles
{
    /**
     * @var array<string, int>
     */
    public const TRAMOS = ['Semilla' => 0, 'Brote' => 100, 'Raíz' => 300, 'Árbol' => 700, 'Bosque' => 1500, 'Selva' => 3000];

    /**
     * @return array{nombre: string, xp_desde: int, xp_hasta: int|null}
     */
    public static function para(int $xp): array
    {
        return self::nivel(self::indice($xp));
    }

    /**
     * @return array{nombre: string, xp_desde: int, xp_hasta: int|null}|null
     */
    public static function siguiente(int $xp): ?array
    {
        $indice = self::indice($xp) + 1;

        return $indice < count(self::TRAMOS) ? self::nivel($indice) : null;
    }

    private static function indice(int $xp): int
    {
        $indice = 0;

        foreach (array_values(self::TRAMOS) as $i => $desde) {
            if ($xp >= $desde) {
                $indice = $i;
            }
        }

        return $indice;
    }

    /**
     * @return array{nombre: string, xp_desde: int, xp_hasta: int|null}
     */
    private static function nivel(int $indice): array
    {
        $nombres = array_keys(self::TRAMOS);
        $desdes  = array_values(self::TRAMOS);

        return [
            'nombre'   => $nombres[$indice],
            'xp_desde' => $desdes[$indice],
            'xp_hasta' => isset($desdes[$indice + 1]) ? $desdes[$indice + 1] - 1 : null,
        ];
    }
}
