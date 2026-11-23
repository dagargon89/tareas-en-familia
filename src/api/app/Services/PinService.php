<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Libraries\Fechas;
use App\Libraries\Hash;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;
use InvalidArgumentException;

/**
 * PIN de perfiles y de modo padre (doc 04 §3.1): formato, PIN trivial y
 * bloqueo de 15 minutos tras 5 intentos fallidos.
 */
final class PinService
{
    public const MAX_INTENTOS    = 5;
    public const MINUTOS_BLOQUEO = 15;

    /**
     * Tablas con columnas `pin_hash`, `pin_intentos` y `bloqueado_hasta`.
     */
    private const TABLAS = ['usuarios', 'perfiles'];

    public function __construct(private readonly BaseConnection $db)
    {
    }

    public static function formatoValido(mixed $pin): bool
    {
        return is_string($pin) && preg_match('/^\d{4}$/', $pin) === 1;
    }

    /**
     * Repetidos (`0000`…`9999`) y secuencias ascendentes o descendentes (`1234`, `9876`).
     */
    public static function esTrivial(string $pin): bool
    {
        $digitos = array_map('intval', str_split($pin));
        $pasos   = [];

        for ($i = 1, $n = count($digitos); $i < $n; $i++) {
            $pasos[] = $digitos[$i] - $digitos[$i - 1];
        }

        $unicos = array_unique($pasos);

        return count($unicos) === 1 && in_array($pasos[0], [0, 1, -1], true);
    }

    /**
     * Mensaje de error para un PIN nuevo, o `null` si es válido.
     */
    public static function errorDePinNuevo(mixed $pin): ?string
    {
        if (! self::formatoValido($pin)) {
            return 'El PIN debe tener 4 dígitos.';
        }

        if (self::esTrivial($pin)) {
            return 'Elige un PIN menos fácil de adivinar (sin dígitos repetidos ni seguidos).';
        }

        return null;
    }

    /**
     * Verifica el PIN de la fila `$id` de `$tabla` y aplica intentos y bloqueo.
     *
     * @throws ApiException 423 `BLOQUEADO` o 401 `PIN_INCORRECTO`
     */
    public function verificar(string $tabla, int $id, string $pin): void
    {
        if (! in_array($tabla, self::TABLAS, true)) {
            throw new InvalidArgumentException("Tabla sin PIN: {$tabla}");
        }

        $ahora = Time::now();
        $error = null;

        $this->db->transBegin();

        try {
            $fila = $this->db->query(
                "SELECT pin_hash, pin_intentos, bloqueado_hasta FROM `{$tabla}` WHERE id = ? FOR UPDATE",
                [$id],
            )->getRowArray();

            if ($fila === null) {
                throw ApiException::noEncontrado();
            }

            $bloqueo = Fechas::bloqueoVigente($fila['bloqueado_hasta']);

            if ($bloqueo !== null) {
                $error = ApiException::bloqueado($bloqueo);
            } elseif (Hash::verificar($pin, $fila['pin_hash'])) {
                $cambios = ['pin_intentos' => 0, 'bloqueado_hasta' => null];

                if (Hash::necesitaRehash($fila['pin_hash'])) {
                    $cambios['pin_hash'] = Hash::crear($pin);
                }

                $this->db->table($tabla)->where('id', $id)->update($cambios);
            } else {
                // Un bloqueo vencido empieza la cuenta de cero.
                $intentos = ($fila['bloqueado_hasta'] === null ? (int) $fila['pin_intentos'] : 0) + 1;

                if ($intentos >= self::MAX_INTENTOS) {
                    $hasta = $ahora->addMinutes(self::MINUTOS_BLOQUEO)->toDateTimeString();
                    $this->db->table($tabla)->where('id', $id)->update(['pin_intentos' => 0, 'bloqueado_hasta' => $hasta]);
                    $error = ApiException::bloqueado((string) Fechas::iso($hasta));
                } else {
                    $this->db->table($tabla)->where('id', $id)->update(['pin_intentos' => $intentos, 'bloqueado_hasta' => null]);
                    $error = ApiException::pinIncorrecto(self::MAX_INTENTOS - $intentos);
                }
            }

            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();

            throw $e;
        }

        if ($error !== null) {
            throw $error;
        }
    }

    /**
     * Quita el bloqueo y reinicia los intentos (RF-17).
     */
    public function desbloquear(string $tabla, int $id): void
    {
        if (! in_array($tabla, self::TABLAS, true)) {
            throw new InvalidArgumentException("Tabla sin PIN: {$tabla}");
        }

        $this->db->table($tabla)->where('id', $id)->update(['pin_intentos' => 0, 'bloqueado_hasta' => null]);
    }
}
