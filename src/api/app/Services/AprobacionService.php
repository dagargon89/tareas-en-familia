<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Calendario;
use App\Entities\Perfil;
use App\Exceptions\ApiException;
use App\Models\PerfilModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;

/**
 * Bandeja y revisión del padre (RF-07, RF-08): E-33 a E-35. Aprobar es una sola
 * transacción (doc 03 §9) y solo `PuntosService` toca el libro de puntos.
 */
final class AprobacionService
{
    public const MAX_MENSAJE = 140;
    public const MAX_LOTE    = 100;

    private readonly PuntosService $puntos;
    private readonly EvidenciasService $evidencias;
    private readonly InstanciasService $instancias;

    public function __construct(private readonly BaseConnection $db)
    {
        $this->puntos     = new PuntosService($db);
        $this->evidencias = service('evidencias');
        $this->instancias = new InstanciasService($db);
    }

    /**
     * E-33 · Instancias `hecha` de todas las hijas, la más antigua primero (`InstanciaRevision[]`).
     *
     * @return list<array<string, mixed>>
     */
    public function bandeja(): array
    {
        $filas = $this->instancias->consulta()
            ->where('i.estado', 'hecha')
            ->orderBy('i.completada_en')
            ->orderBy('i.id')
            ->get()->getResultArray();

        $perfiles = [];

        foreach (model(PerfilModel::class)->findAll() as $perfil) {
            /** @var Perfil $perfil */
            $perfiles[$perfil->id] = $perfil->publico();
        }

        return array_map(
            static fn (array $fila): array => InstanciasService::aInstancia($fila) + ['perfil' => $perfiles[(int) $fila['perfil_id']] ?? null],
            $filas,
        );
    }

    /**
     * E-34 · `{ decision: "aprobada" | "devuelta", mensaje? }` → `ResultadoRevision`.
     *
     * @param array<string, mixed> $entrada
     *
     * @return array{instancia: array<string, mixed>, puntos_acreditados: int, insignias_nuevas: list<array<string, mixed>>, meta_cumplida: bool}
     */
    public function revisar(int $instanciaId, array $entrada, int $usuarioId): array
    {
        $decision = $entrada['decision'] ?? null;

        if ($decision === 'aprobada') {
            return $this->aprobar($instanciaId, $usuarioId);
        }

        if ($decision !== 'devuelta') {
            throw ApiException::validacion(['decision' => 'Elige aprobar o devolver.']);
        }

        $mensaje = is_string($entrada['mensaje'] ?? null) ? trim($entrada['mensaje']) : '';

        if ($mensaje === '' || mb_strlen($mensaje) > self::MAX_MENSAJE) {
            throw ApiException::validacion(['mensaje' => 'Escribe un mensaje amable de hasta 140 caracteres.']);
        }

        return $this->devolver($instanciaId, $usuarioId, $mensaje);
    }

    /**
     * E-35 · Aprueba cada instancia `hecha` del lote, cada una en su propia transacción.
     * Las que no estén `hecha` (ya aprobadas, devueltas, pendientes, inexistentes o repetidas)
     * se omiten sin abortar el lote; la respuesta trae solo las aprobadas, en el orden recibido.
     *
     * @param array<string, mixed> $entrada `{ ids: number[] }`
     *
     * @return list<array<string, mixed>>
     */
    public function aprobarLote(array $entrada, int $usuarioId): array
    {
        $ids = $entrada['ids'] ?? null;

        if (! is_array($ids) || $ids === [] || count($ids) > self::MAX_LOTE || array_filter($ids, static fn (mixed $id): bool => ! is_int($id)) !== []) {
            throw ApiException::validacion(['ids' => 'Elige entre 1 y 100 tareas.']);
        }

        $resultados = [];

        foreach (array_unique($ids) as $id) {
            try {
                $resultados[] = $this->aprobar($id, $usuarioId);
            } catch (ApiException $e) {
                if (! in_array($e->getEstado(), [404, 409], true)) {
                    throw $e;
                }
            }
        }

        return $resultados;
    }

    /**
     * Doc 03 §9: bloquear la fila, validar `hecha`, pasar a `aprobada`, insertar revisión y
     * movimiento, fijar `borrar_despues` de la evidencia y evaluar insignias y meta (S4).
     *
     * @return array{instancia: array<string, mixed>, puntos_acreditados: int, insignias_nuevas: list<array<string, mixed>>, meta_cumplida: bool}
     */
    private function aprobar(int $instanciaId, int $usuarioId): array
    {
        $ahora = Time::now();

        $this->db->transBegin();

        try {
            $fila = $this->bloquearHecha($instanciaId);

            $this->db->table('tarea_instancias')->where('id', $instanciaId)->update([
                'estado' => 'aprobada', 'aprobada_en' => $ahora->toDateTimeString(), 'updated_at' => $ahora->toDateTimeString(),
            ]);
            $this->insertarRevision($instanciaId, $usuarioId, 'aprobada', null);
            $acreditados = $this->puntos->acreditarTarea((int) $fila['perfil_id'], $instanciaId, (int) $fila['puntos'], (string) $fila['titulo']);
            $this->evidencias->programarBorrado($instanciaId, Calendario::hoy($ahora));
            [$insignias, $metaCumplida] = $this->alAprobar((int) $fila['perfil_id']);

            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();

            throw $e;
        }

        return [
            'instancia'          => $this->instancias->instancia($instanciaId),
            'puntos_acreditados' => $acreditados,
            'insignias_nuevas'   => $insignias,
            'meta_cumplida'      => $metaCumplida,
        ];
    }

    /**
     * @return array{instancia: array<string, mixed>, puntos_acreditados: int, insignias_nuevas: list<array<string, mixed>>, meta_cumplida: bool}
     */
    private function devolver(int $instanciaId, int $usuarioId, string $mensaje): array
    {
        $ahora = Time::now()->toDateTimeString();

        $this->db->transBegin();

        try {
            $this->bloquearHecha($instanciaId);
            $this->db->table('tarea_instancias')->where('id', $instanciaId)->update([
                'estado' => 'devuelta', 'mensaje_devolucion' => $mensaje, 'updated_at' => $ahora,
            ]);
            $this->insertarRevision($instanciaId, $usuarioId, 'devuelta', $mensaje);
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();

            throw $e;
        }

        return [
            'instancia'          => $this->instancias->instancia($instanciaId),
            'puntos_acreditados' => 0,
            'insignias_nuevas'   => [],
            'meta_cumplida'      => false,
        ];
    }

    /**
     * Dentro de la transacción de aprobar: evalúa las insignias del perfil y si la meta
     * familiar activa alcanzó su objetivo (doc 03 §9).
     *
     * @return array{0: list<array<string, mixed>>, 1: bool} Insignias nuevas y si se cumplió la meta
     */
    private function alAprobar(int $perfilId): array
    {
        return [
            (new InsigniasService($this->db))->evaluar($perfilId),
            (new MetasService($this->db))->evaluarCumplimiento(),
        ];
    }

    /**
     * `SELECT … FOR UPDATE` y validación de estado `hecha`.
     *
     * @return array<string, mixed>
     */
    private function bloquearHecha(int $instanciaId): array
    {
        $fila = $this->db->query(
            'SELECT i.id, i.perfil_id, i.puntos, i.estado, t.titulo
               FROM tarea_instancias i JOIN tareas t ON t.id = i.tarea_id
              WHERE i.id = ? FOR UPDATE',
            [$instanciaId],
        )->getRowArray();

        if ($fila === null) {
            throw ApiException::noEncontrado();
        }

        if ($fila['estado'] !== 'hecha') {
            throw ApiException::estadoInvalido('Solo se revisan tareas marcadas como hechas.');
        }

        return $fila;
    }

    private function insertarRevision(int $instanciaId, int $usuarioId, string $decision, ?string $mensaje): void
    {
        $this->db->table('revisiones')->insert([
            'instancia_id' => $instanciaId,
            'usuario_id'   => $usuarioId,
            'decision'     => $decision,
            'mensaje'      => $mensaje,
            'created_at'   => Time::now()->toDateTimeString(),
        ]);
    }
}
