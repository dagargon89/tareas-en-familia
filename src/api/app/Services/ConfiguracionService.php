<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Libraries\Hash;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;

/**
 * Configuración familiar (RF-18): E-47 y E-48. El padre ajusta `racha_umbral`, `sonidos_default`
 * y su propio PIN (`pin_padre`, solo el del usuario de la sesión). Las demás claves son de solo lectura.
 */
final class ConfiguracionService
{
    /**
     * Valores por omisión si falta la clave (los mismos que siembra `CatalogoSeeder`, doc 03 §6).
     *
     * @var array<string, bool|int>
     */
    public const POR_OMISION = [
        'racha_umbral'             => 80,
        'sonidos_default'          => true,
        'dias_generacion'          => 7,
        'evidencia_dias_retencion' => 30,
        'puntos_tarea_propia'      => 5,
    ];

    public function __construct(private readonly BaseConnection $db)
    {
    }

    /**
     * E-47 · `Configuracion` del contrato.
     *
     * @return array{racha_umbral: int, sonidos_default: bool, dias_generacion: int, evidencia_dias_retencion: int, puntos_tarea_propia: int}
     */
    public function ver(): array
    {
        $guardados = array_column($this->db->table('configuracion')->get()->getResultArray(), 'valor', 'clave');
        $valor     = static fn (string $clave): mixed => array_key_exists($clave, $guardados)
            ? json_decode((string) $guardados[$clave], true)
            : self::POR_OMISION[$clave];

        return [
            'racha_umbral'             => (int) $valor('racha_umbral'),
            'sonidos_default'          => (bool) $valor('sonidos_default'),
            'dias_generacion'          => (int) $valor('dias_generacion'),
            'evidencia_dias_retencion' => (int) $valor('evidencia_dias_retencion'),
            'puntos_tarea_propia'      => (int) $valor('puntos_tarea_propia'),
        ];
    }

    /**
     * E-48 · `ConfiguracionEntrada`: todos los campos son opcionales; nada se guarda si alguno es inválido.
     *
     * @param array<string, mixed> $entrada
     *
     * @return array{racha_umbral: int, sonidos_default: bool, dias_generacion: int, evidencia_dias_retencion: int, puntos_tarea_propia: int}
     */
    public function guardar(array $entrada, int $usuarioId): array
    {
        $campos  = [];
        $valores = [];

        if (array_key_exists('racha_umbral', $entrada)) {
            $umbral = $entrada['racha_umbral'];

            if (! is_int($umbral) || $umbral < 1 || $umbral > 100) {
                $campos['racha_umbral'] = 'El umbral va de 1 a 100 %.';
            } else {
                $valores['racha_umbral'] = $umbral;
            }
        }

        if (array_key_exists('sonidos_default', $entrada)) {
            if (! is_bool($entrada['sonidos_default'])) {
                $campos['sonidos_default'] = 'Debe ser verdadero o falso.';
            } else {
                $valores['sonidos_default'] = $entrada['sonidos_default'];
            }
        }

        $pin = $entrada['pin_padre'] ?? null;

        if ($pin !== null && ($error = PinService::errorDePinNuevo($pin)) !== null) {
            $campos['pin_padre'] = $error;
        }

        if ($campos !== []) {
            throw ApiException::validacion($campos);
        }

        $ahora = Time::now()->toDateTimeString();

        $this->db->transBegin();

        try {
            foreach ($valores as $clave => $valor) {
                $this->db->table('configuracion')->replace([
                    'clave' => $clave, 'valor' => json_encode($valor, JSON_THROW_ON_ERROR), 'updated_at' => $ahora,
                ]);
            }

            if ($pin !== null) {
                $this->db->table('usuarios')->where('id', $usuarioId)->update([
                    'pin_hash' => Hash::crear((string) $pin), 'pin_intentos' => 0, 'bloqueado_hasta' => null, 'updated_at' => $ahora,
                ]);
            }

            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();

            throw $e;
        }

        return $this->ver();
    }
}
