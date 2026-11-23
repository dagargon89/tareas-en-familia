<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Libraries\Hash;
use App\Models\ConfiguracionModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;

/**
 * Configuración familiar (RF-18): E-47 y E-48. Solo `racha_umbral`, `sonidos_default`
 * y el PIN del padre de la sesión se pueden cambiar; lo demás es de solo lectura.
 */
final class ConfiguracionService
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    /**
     * @return array{racha_umbral: int, sonidos_default: bool, dias_generacion: int, evidencia_dias_retencion: int, puntos_tarea_propia: int}
     */
    public function obtener(): array
    {
        $config = model(ConfiguracionModel::class);

        return [
            'racha_umbral'             => $config->entero('racha_umbral', RachasService::UMBRAL_POR_OMISION),
            'sonidos_default'          => (bool) $config->valor('sonidos_default', true),
            'dias_generacion'          => $config->entero('dias_generacion', GeneradorInstancias::DIAS_POR_OMISION),
            'evidencia_dias_retencion' => $config->entero('evidencia_dias_retencion', EvidenciasService::DIAS_RETENCION_POR_OMISION),
            'puntos_tarea_propia'      => $config->entero('puntos_tarea_propia', TareasService::PUNTOS_PROPIA_POR_OMISION),
        ];
    }

    /**
     * @param array<string, mixed> $entrada `ConfiguracionEntrada`
     *
     * @return array<string, bool|int>
     */
    public function guardar(array $entrada, int $usuarioId): array
    {
        $campos = [];

        if (array_key_exists('racha_umbral', $entrada) && (! is_int($entrada['racha_umbral']) || $entrada['racha_umbral'] < 1 || $entrada['racha_umbral'] > 100)) {
            $campos['racha_umbral'] = 'Entre 1 y 100 %.';
        }

        if (array_key_exists('sonidos_default', $entrada) && ! is_bool($entrada['sonidos_default'])) {
            $campos['sonidos_default'] = 'Debe ser verdadero o falso.';
        }

        $hayPin = array_key_exists('pin_padre', $entrada) && $entrada['pin_padre'] !== null;

        if ($hayPin && ($error = PinService::errorDePinNuevo($entrada['pin_padre'])) !== null) {
            $campos['pin_padre'] = $error;
        }

        if ($campos !== []) {
            throw ApiException::validacion($campos);
        }

        $ahora = Time::now()->toDateTimeString();
        $this->db->transException(true)->transStart();

        foreach (['racha_umbral', 'sonidos_default'] as $clave) {
            if (array_key_exists($clave, $entrada)) {
                $this->db->table('configuracion')->replace([
                    'clave' => $clave, 'valor' => json_encode($entrada[$clave], JSON_THROW_ON_ERROR), 'updated_at' => $ahora,
                ]);
            }
        }

        if ($hayPin) {
            $this->db->table('usuarios')->where('id', $usuarioId)->update([
                'pin_hash' => Hash::crear((string) $entrada['pin_padre']), 'pin_intentos' => 0, 'bloqueado_hasta' => null, 'updated_at' => $ahora,
            ]);
        }

        $this->db->transComplete();

        return $this->obtener();
    }
}
