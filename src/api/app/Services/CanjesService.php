<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Libraries\Fechas;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;

/**
 * Canjes de recompensas (RF-09): E-14 a E-16, E-40 y E-41 (doc 03 §9, doc 05 §2.4).
 */
final class CanjesService
{
    public const ESTADOS = ['solicitado', 'aprobado', 'entregado', 'rechazado'];

    /**
     * Decisión → estado de origen permitido.
     */
    private const TRANSICIONES = ['aprobado' => 'solicitado', 'rechazado' => 'solicitado', 'entregado' => 'aprobado'];

    private readonly PuntosService $puntos;

    public function __construct(private readonly BaseConnection $db)
    {
        $this->puntos = new PuntosService($db);
    }

    /**
     * E-14 · Recompensas activas con `alcanza` y `faltan` según el disponible.
     *
     * @return list<array<string, mixed>>
     */
    public function recompensasPara(int $perfilId): array
    {
        $disponible = $this->puntos->saldos($perfilId)['disponible'];

        return array_map(static fn (array $r): array => $r + [
            'alcanza' => $disponible >= $r['costo'],
            'faltan'  => max(0, $r['costo'] - $disponible),
        ], (new RecompensasService($this->db))->listar(true));
    }

    /**
     * E-15 · Bloquea el perfil, calcula el disponible e inserta el canje `solicitado`.
     *
     * @param array<string, mixed> $entrada `{ recompensa_id }`
     *
     * @return array<string, mixed>
     */
    public function solicitar(int $perfilId, array $entrada): array
    {
        $recompensaId = $entrada['recompensa_id'] ?? null;

        if (! is_int($recompensaId)) {
            throw ApiException::validacion(['recompensa_id' => 'Elige un premio.']);
        }

        $recompensa = $this->db->table('recompensas')->where(['id' => $recompensaId, 'activa' => 1])->get()->getRowArray()
            ?? throw ApiException::noEncontrado('Ese premio ya no está disponible.');

        $this->db->transBegin();

        try {
            $this->db->query('SELECT id FROM perfiles WHERE id = ? FOR UPDATE', [$perfilId]);

            if ($this->puntos->saldos($perfilId)['disponible'] < (int) $recompensa['costo']) {
                throw ApiException::saldoInsuficiente();
            }

            $ahora = Time::now()->toDateTimeString();
            $this->db->table('canjes')->insert([
                'perfil_id' => $perfilId, 'recompensa_id' => $recompensaId, 'costo' => (int) $recompensa['costo'],
                'estado' => 'solicitado', 'created_at' => $ahora, 'updated_at' => $ahora,
            ]);
            $id = (int) $this->db->insertID();
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();

            throw $e;
        }

        return $this->buscar($id);
    }

    /**
     * E-16 (con perfil) y E-40 (padre, `?estado=` opcional). Más recientes primero.
     *
     * @return list<array<string, mixed>>
     */
    public function listar(?int $perfilId = null, ?string $estado = null): array
    {
        if ($estado !== null && ! in_array($estado, self::ESTADOS, true)) {
            throw ApiException::validacion(['estado' => 'Estado de canje desconocido.']);
        }

        $q = $this->consulta()->orderBy('c.created_at', 'DESC')->orderBy('c.id', 'DESC');

        if ($perfilId !== null) {
            $q->where('c.perfil_id', $perfilId);
        }

        if ($estado !== null) {
            $q->where('c.estado', $estado);
        }

        return array_map(self::aCanje(...), $q->get()->getResultArray());
    }

    /**
     * E-41 · `aprobado` (desde `solicitado`, con movimiento negativo), `rechazado` (desde
     * `solicitado`, sin movimiento) o `entregado` (desde `aprobado`). Cualquier otra: 409.
     *
     * @param array<string, mixed> $entrada `{ decision }`
     *
     * @return array<string, mixed>
     */
    public function revisar(int $id, array $entrada, int $usuarioId): array
    {
        $decision = $entrada['decision'] ?? null;

        if (! is_string($decision) || ! isset(self::TRANSICIONES[$decision])) {
            throw ApiException::validacion(['decision' => 'Elige aprobar, rechazar o entregar.']);
        }

        $this->db->transBegin();

        try {
            $canje = $this->db->query(
                'SELECT c.id, c.perfil_id, c.costo, c.estado, r.titulo FROM canjes c JOIN recompensas r ON r.id = c.recompensa_id WHERE c.id = ? FOR UPDATE',
                [$id],
            )->getRowArray() ?? throw ApiException::noEncontrado();

            if ($canje['estado'] !== self::TRANSICIONES[$decision]) {
                throw ApiException::estadoInvalido('Ese cambio no aplica al estado actual del canje.');
            }

            $this->db->table('canjes')->where('id', $id)->update([
                'estado' => $decision, 'revisado_por' => $usuarioId, 'updated_at' => Time::now()->toDateTimeString(),
            ]);

            if ($decision === 'aprobado') {
                $this->puntos->debitarCanje((int) $canje['perfil_id'], $id, (int) $canje['costo'], (string) $canje['titulo']);
            }

            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();

            throw $e;
        }

        return $this->buscar($id);
    }

    /**
     * @return array<string, mixed>
     */
    private function buscar(int $id): array
    {
        return self::aCanje($this->consulta()->where('c.id', $id)->get()->getRowArray() ?? throw ApiException::noEncontrado());
    }

    private function consulta(): \CodeIgniter\Database\BaseBuilder
    {
        return $this->db->table('canjes c')
            ->select('c.id, c.perfil_id, c.costo, c.estado, c.created_at, r.id AS r_id, r.titulo AS r_titulo, r.icono AS r_icono, r.costo AS r_costo, r.activa AS r_activa')
            ->join('recompensas r', 'r.id = c.recompensa_id');
    }

    /**
     * Forma `Canje` del contrato.
     *
     * @param array<string, mixed> $fila
     *
     * @return array<string, mixed>
     */
    public static function aCanje(array $fila): array
    {
        return [
            'id'         => (int) $fila['id'],
            'perfil_id'  => (int) $fila['perfil_id'],
            'recompensa' => RecompensasService::aRecompensa([
                'id' => $fila['r_id'], 'titulo' => $fila['r_titulo'], 'icono' => $fila['r_icono'], 'costo' => $fila['r_costo'], 'activa' => $fila['r_activa'],
            ]),
            'costo'      => (int) $fila['costo'],
            'estado'     => $fila['estado'],
            'created_at' => Fechas::iso($fila['created_at']),
        ];
    }
}
