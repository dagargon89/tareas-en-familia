<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Hash de contraseñas y PIN (doc 04 §3.1): Argon2id; si el PHP no lo
 * soporta, BCRYPT costo 12. Úsese para `usuarios.password_hash`,
 * `usuarios.pin_hash` y `perfiles.pin_hash`.
 */
final class Hash
{
    public static function crear(string $secreto): string
    {
        [$algoritmo, $opciones] = self::configuracion();

        return password_hash($secreto, $algoritmo, $opciones);
    }

    public static function verificar(string $secreto, string $hash): bool
    {
        return password_verify($secreto, $hash);
    }

    public static function necesitaRehash(string $hash): bool
    {
        [$algoritmo, $opciones] = self::configuracion();

        return password_needs_rehash($hash, $algoritmo, $opciones);
    }

    /**
     * Algoritmo y opciones vigentes.
     *
     * @param bool|null $argon2Disponible `null` detecta el soporte del PHP actual.
     *
     * @return array{0: string, 1: array<string, int>}
     */
    public static function configuracion(?bool $argon2Disponible = null): array
    {
        $argon2Disponible ??= defined('PASSWORD_ARGON2ID');

        if ($argon2Disponible) {
            return [PASSWORD_ARGON2ID, []];
        }

        return [PASSWORD_BCRYPT, ['cost' => 12]];
    }
}
