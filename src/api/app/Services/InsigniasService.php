<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\Fechas;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;

/**
 * Insignias (RF-11, doc 03 §5). `evaluar()` revisa las reglas que el perfil aún no tiene
 * después de cada aprobación y de cada cierre semanal. Cada insignia se otorga una sola vez
 * (`uq_perfil_insignia`) y su XP entra al libro por `PuntosService`.
 */
final class InsigniasService
{
    private readonly PuntosService $puntos;

    public function __construct(private readonly BaseConnection $db)
    {
        $this->puntos = new PuntosService($db);
    }

    /**
     * Otorga las insignias cuya regla ya se cumple. Si se llama dentro de otra transacción
     * (aprobar, cerrar semana) se integra a ella.
     *
     * @return list<array{codigo: string, nombre: string, descripcion: string, icono: string, color: string, obtenida_en: string|null}> Solo las nuevas
     */
    public function evaluar(int $perfilId): array
    {
        $obtenidas = array_map('intval', array_column(
            $this->db->table('perfil_insignias')->select('insignia_id')->where('perfil_id', $perfilId)->get()->getResultArray(),
            'insignia_id',
        ));
        $consulta = $this->db->table('insignias')->orderBy('orden')->orderBy('id');

        if ($obtenidas !== []) {
            $consulta->whereNotIn('id', $obtenidas);
        }

        $pendientes = $consulta->get()->getResultArray();

        if ($pendientes === []) {
            return [];
        }

        $metricas = new MetricasPerfil($this->db, $perfilId);
        $nuevas   = [];

        $this->db->transBegin();

        try {
            foreach ($pendientes as $insignia) {
                $regla = json_decode((string) $insignia['regla'], true);

                if (! is_array($regla) || ! $metricas->cumple($regla)) {
                    continue;
                }

                $obtenidaEn = $this->otorgar($perfilId, $insignia);

                if ($obtenidaEn !== null) {
                    $nuevas[] = self::aInsignia($insignia, $obtenidaEn);
                }
            }

            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();

            throw $e;
        }

        return $nuevas;
    }

    /**
     * Inserta la fila de `perfil_insignias` y acredita el XP. Si otra transacción la otorgó
     * primero (índice único), no hace nada y devuelve null.
     *
     * @param array<string, mixed> $insignia
     */
    private function otorgar(int $perfilId, array $insignia): ?string
    {
        $ahora = Time::now()->toDateTimeString();

        $this->db->table('perfil_insignias')->ignore(true)->insert([
            'perfil_id'   => $perfilId,
            'insignia_id' => (int) $insignia['id'],
            'obtenida_en' => $ahora,
        ]);

        if ($this->db->affectedRows() !== 1) {
            return null;
        }

        $this->puntos->acreditarInsignia($perfilId, (int) $this->db->insertID(), (int) $insignia['xp'], (string) $insignia['nombre']);

        return $ahora;
    }

    /**
     * Fila de `insignias` → `Insignia` del contrato.
     *
     * @param array<string, mixed> $fila
     *
     * @return array{codigo: string, nombre: string, descripcion: string, icono: string, color: string, obtenida_en: string|null}
     */
    public static function aInsignia(array $fila, ?string $obtenidaEn): array
    {
        return [
            'codigo'      => (string) $fila['codigo'],
            'nombre'      => (string) $fila['nombre'],
            'descripcion' => (string) $fila['descripcion'],
            'icono'       => (string) $fila['icono'],
            'color'       => (string) $fila['color'],
            'obtenida_en' => Fechas::iso($obtenidaEn),
        ];
    }
}
