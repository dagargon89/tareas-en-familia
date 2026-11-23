<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Catalogo;
use App\Exceptions\ApiException;
use App\Libraries\Fechas;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;

/**
 * Recompensas y canjes (RF-09): E-14 a E-16 y E-37 a E-41.
 *
 * El canje `solicitado` reserva saldo (doc 03 §4: disponible = saldo − reservado); al aprobarlo
 * `PuntosService` inserta el movimiento negativo y al rechazarlo la reserva se libera sola.
 */
final class CanjesService
{
    public const MAX_TITULO = 60;
    public const MAX_COSTO  = 9999;
    public const ESTADOS    = ['solicitado', 'aprobado', 'entregado', 'rechazado'];

    /**
     * Transiciones permitidas por E-41 (doc 05 §2.4): estado actual → decisiones válidas.
     */
    private const TRANSICIONES = [
        'solicitado' => ['aprobado', 'rechazado'],
        'aprobado'   => ['entregado'],
    ];

    private readonly PuntosService $puntos;

    public function __construct(private readonly BaseConnection $db)
    {
        $this->puntos = new PuntosService($db);
    }

    // ===== Catálogo =====

    /**
     * E-37 · Todas las recompensas, activas e inactivas, de la más barata a la más cara.
     *
     * @return list<array{id: int, titulo: string, icono: string, costo: int, activa: bool}>
     */
    public function recompensas(): array
    {
        $filas = $this->db->table('recompensas')->orderBy('costo')->orderBy('id')->get()->getResultArray();

        return array_map(self::aRecompensa(...), $filas);
    }

    /**
     * E-14 · Recompensas activas con `alcanza` y `faltan` según el disponible del perfil.
     *
     * @return list<array<string, bool|int|string>>
     */
    public function paraPerfil(int $perfilId): array
    {
        $disponible = $this->puntos->saldos($perfilId)['disponible'];
        $filas      = $this->db->table('recompensas')->where('activa', 1)->orderBy('costo')->orderBy('id')->get()->getResultArray();

        return array_map(static function (array $fila) use ($disponible): array {
            $recompensa = self::aRecompensa($fila);

            return $recompensa + [
                'alcanza' => $disponible >= $recompensa['costo'],
                'faltan'  => max(0, $recompensa['costo'] - $disponible),
            ];
        }, $filas);
    }

    /**
     * E-38 · `RecompensaEntrada` → `Recompensa`.
     *
     * @param array<string, mixed> $entrada
     *
     * @return array{id: int, titulo: string, icono: string, costo: int, activa: bool}
     */
    public function crearRecompensa(array $entrada): array
    {
        $datos = $this->validarRecompensa($entrada);
        $ahora = Time::now()->toDateTimeString();

        $this->db->table('recompensas')->insert($datos + ['created_at' => $ahora, 'updated_at' => $ahora]);

        return $this->recompensa((int) $this->db->insertID());
    }

    /**
     * E-39 · Editar no cambia el costo de los canjes ya solicitados (`canjes.costo` es copia).
     *
     * @param array<string, mixed> $entrada
     *
     * @return array{id: int, titulo: string, icono: string, costo: int, activa: bool}
     */
    public function editarRecompensa(int $id, array $entrada): array
    {
        $this->recompensa($id);
        $datos = $this->validarRecompensa($entrada);

        $this->db->table('recompensas')->where('id', $id)->update($datos + ['updated_at' => Time::now()->toDateTimeString()]);

        return $this->recompensa($id);
    }

    // ===== Canjes =====

    /**
     * E-15 · Doc 03 §9: bloquear el perfil, calcular el disponible e insertar el canje.
     *
     * @param array<string, mixed> $entrada `{ recompensa_id }`
     *
     * @return array<string, mixed> `Canje`
     */
    public function solicitar(int $perfilId, array $entrada): array
    {
        $recompensaId = $entrada['recompensa_id'] ?? null;

        if (! is_int($recompensaId) || $recompensaId < 1) {
            throw ApiException::validacion(['recompensa_id' => 'Elige un premio.']);
        }

        $this->db->transBegin();

        try {
            // Serializa las solicitudes del mismo perfil para que dos canjes simultáneos no gasten el mismo saldo.
            $this->db->query('SELECT id FROM perfiles WHERE id = ? FOR UPDATE', [$perfilId]);

            $recompensa = $this->db->table('recompensas')->where(['id' => $recompensaId, 'activa' => 1])->get()->getRowArray();

            if ($recompensa === null) {
                throw ApiException::noEncontrado('Ese premio ya no está disponible.');
            }

            if ($this->puntos->saldos($perfilId)['disponible'] < (int) $recompensa['costo']) {
                throw ApiException::saldoInsuficiente();
            }

            $ahora = Time::now()->toDateTimeString();
            $this->db->table('canjes')->insert([
                'perfil_id'     => $perfilId,
                'recompensa_id' => $recompensaId,
                'costo'         => (int) $recompensa['costo'],
                'estado'        => 'solicitado',
                'created_at'    => $ahora,
                'updated_at'    => $ahora,
            ]);
            $id = (int) $this->db->insertID();

            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();

            throw $e;
        }

        return $this->canje($id);
    }

    /**
     * E-16 · Canjes del perfil, el más reciente primero.
     *
     * @return list<array<string, mixed>>
     */
    public function delPerfil(int $perfilId): array
    {
        return $this->listar(['c.perfil_id' => $perfilId], 'DESC');
    }

    /**
     * E-40 · Canjes de todas las hijas, el más antiguo primero, con filtro opcional por estado.
     *
     * @return list<array<string, mixed>>
     */
    public function admin(mixed $estado): array
    {
        if ($estado === null || $estado === '') {
            return $this->listar([], 'ASC');
        }

        if (! in_array($estado, self::ESTADOS, true)) {
            throw ApiException::validacion(['estado' => 'Estado de canje desconocido.']);
        }

        return $this->listar(['c.estado' => $estado], 'ASC');
    }

    /**
     * E-41 · `solicitado` → `aprobado` (movimiento negativo) o `rechazado` (libera la reserva);
     * `aprobado` → `entregado`. Bloquea la fila para que aprobar dos veces no descuente dos veces.
     *
     * @param array<string, mixed> $entrada `{ decision }`
     *
     * @return array<string, mixed> `Canje`
     */
    public function revisar(int $id, array $entrada, int $usuarioId): array
    {
        $decision = $entrada['decision'] ?? null;

        if (! in_array($decision, ['aprobado', 'rechazado', 'entregado'], true)) {
            throw ApiException::validacion(['decision' => 'Elige aprobar, rechazar o entregar.']);
        }

        $this->db->transBegin();

        try {
            $fila = $this->db->query(
                'SELECT c.id, c.perfil_id, c.costo, c.estado, r.titulo
                   FROM canjes c JOIN recompensas r ON r.id = c.recompensa_id
                  WHERE c.id = ? FOR UPDATE',
                [$id],
            )->getRowArray();

            if ($fila === null) {
                throw ApiException::noEncontrado();
            }

            if (! in_array($decision, self::TRANSICIONES[$fila['estado']] ?? [], true)) {
                throw ApiException::estadoInvalido('Ese canje ya no se puede pasar a ese estado.');
            }

            $this->db->table('canjes')->where('id', $id)->update([
                'estado'       => $decision,
                'revisado_por' => $usuarioId,
                'updated_at'   => Time::now()->toDateTimeString(),
            ]);

            if ($decision === 'aprobado') {
                $this->puntos->debitarCanje((int) $fila['perfil_id'], $id, (int) $fila['costo'], (string) $fila['titulo']);
            }

            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();

            throw $e;
        }

        return $this->canje($id);
    }

    // ===== Apoyo =====

    /**
     * @param array<string, mixed> $filtro
     *
     * @return list<array<string, mixed>>
     */
    private function listar(array $filtro, string $orden): array
    {
        $filas = $this->consultaCanjes()->where($filtro)->orderBy('c.created_at', $orden)->orderBy('c.id', $orden)->get()->getResultArray();

        return array_map(self::aCanje(...), $filas);
    }

    /**
     * @return array<string, mixed>
     */
    private function canje(int $id): array
    {
        return self::aCanje($this->consultaCanjes()->where('c.id', $id)->get()->getRowArray() ?? throw ApiException::noEncontrado());
    }

    private function consultaCanjes(): BaseBuilder
    {
        return $this->db->table('canjes c')
            ->select('c.id, c.perfil_id, c.costo, c.estado, c.created_at, r.id AS r_id, r.titulo AS r_titulo, r.icono AS r_icono, r.costo AS r_costo, r.activa AS r_activa')
            ->join('recompensas r', 'r.id = c.recompensa_id');
    }

    /**
     * @return array{id: int, titulo: string, icono: string, costo: int, activa: bool}
     */
    private function recompensa(int $id): array
    {
        $fila = $this->db->table('recompensas')->where('id', $id)->get()->getRowArray();

        return self::aRecompensa($fila ?? throw ApiException::noEncontrado());
    }

    /**
     * @param array<string, mixed> $entrada
     *
     * @return array{titulo: string, icono: string, costo: int, activa: int}
     */
    private function validarRecompensa(array $entrada): array
    {
        $campos = [];
        $titulo = is_string($entrada['titulo'] ?? null) ? trim($entrada['titulo']) : '';
        $costo  = $entrada['costo'] ?? null;
        $activa = $entrada['activa'] ?? true;

        if ($titulo === '' || mb_strlen($titulo) > self::MAX_TITULO) {
            $campos['titulo'] = 'Escribe el premio en hasta 60 caracteres.';
        }

        if (! Catalogo::esIcono($entrada['icono'] ?? null)) {
            $campos['icono'] = 'Elige un ícono de la lista.';
        }

        if (! is_int($costo) || $costo < 1 || $costo > self::MAX_COSTO) {
            $campos['costo'] = 'El costo va de 1 a 9999 puntos.';
        }

        if (! is_bool($activa)) {
            $campos['activa'] = 'Debe ser verdadero o falso.';
        }

        if ($campos !== []) {
            throw ApiException::validacion($campos);
        }

        return ['titulo' => $titulo, 'icono' => (string) $entrada['icono'], 'costo' => (int) $costo, 'activa' => $activa ? 1 : 0];
    }

    /**
     * @param array<string, mixed> $fila
     *
     * @return array{id: int, titulo: string, icono: string, costo: int, activa: bool}
     */
    public static function aRecompensa(array $fila): array
    {
        return [
            'id'     => (int) $fila['id'],
            'titulo' => (string) $fila['titulo'],
            'icono'  => (string) $fila['icono'],
            'costo'  => (int) $fila['costo'],
            'activa' => (bool) (int) $fila['activa'],
        ];
    }

    /**
     * Fila de `consultaCanjes()` → `Canje` del contrato.
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
            'recompensa' => self::aRecompensa([
                'id' => $fila['r_id'], 'titulo' => $fila['r_titulo'], 'icono' => $fila['r_icono'], 'costo' => $fila['r_costo'], 'activa' => $fila['r_activa'],
            ]),
            'costo'      => (int) $fila['costo'],
            'estado'     => (string) $fila['estado'],
            'created_at' => Fechas::iso((string) $fila['created_at']),
        ];
    }
}
