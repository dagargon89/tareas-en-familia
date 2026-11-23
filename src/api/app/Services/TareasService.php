<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Calendario;
use App\Domain\Catalogo;
use App\Domain\ReglaRecurrencia;
use App\Entities\Perfil;
use App\Exceptions\ApiException;
use App\Models\ConfiguracionModel;
use App\Models\TareaModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\I18n\Time;
use InvalidArgumentException;

/**
 * Tareas del padre (RF-03, RF-13: E-28 a E-32) y escolares propias de las
 * hijas (RF-19: E-18 a E-21). Toda escritura regenera las instancias en una transacción.
 */
final class TareasService
{
    public const PUNTOS_PROPIA_POR_OMISION = 5;

    private readonly GeneradorInstancias $generador;

    public function __construct(private readonly BaseConnection $db)
    {
        $this->generador = new GeneradorInstancias($db);
    }

    private function hoy(): string
    {
        return Calendario::hoy(Time::now());
    }

    // ===== Padre =====

    /**
     * E-28 · `Tarea[]`, opcionalmente por perfil y por `activa`.
     *
     * @return list<array<string, mixed>>
     */
    public function listar(?int $perfilId = null, ?bool $activa = null, ?string $creadaPor = null): array
    {
        $consulta = $this->consulta()->orderBy('t.perfil_id')->orderBy('t.id');

        if ($perfilId !== null) {
            $consulta->where('t.perfil_id', $perfilId);
        }

        if ($activa !== null) {
            $consulta->where('t.activa', $activa ? 1 : 0);
        }

        if ($creadaPor !== null) {
            $consulta->where('t.creada_por', $creadaPor);
        }

        return array_map(self::aTarea(...), $consulta->get()->getResultArray());
    }

    /**
     * E-29 · Crear tarea del padre (`TareaEntrada`).
     *
     * @param array<string, mixed> $entrada
     *
     * @return array<string, mixed>
     */
    public function crear(array $entrada, int $usuarioId): array
    {
        [$tarea, $recurrencia] = $this->validar($entrada, null);

        return $this->guardarNueva($tarea + ['creada_por' => 'padre', 'creada_por_usuario_id' => $usuarioId], $recurrencia);
    }

    /**
     * E-30 · Editar; regenera solo las instancias futuras pendientes.
     *
     * @param array<string, mixed> $entrada
     *
     * @return array<string, mixed>
     */
    public function editar(int $id, array $entrada): array
    {
        $actual                = $this->buscar($id);
        [$tarea, $recurrencia] = $this->validar($entrada, $actual);

        return $this->guardarCambios($id, $tarea, $recurrencia);
    }

    /**
     * E-31 · Desactivar: borra las instancias futuras pendientes y conserva el historial.
     */
    public function desactivar(int $id): void
    {
        $actual = $this->buscar($id);
        $hoy    = $this->hoy();

        $this->db->transException(true)->transStart();
        $this->db->table('tareas')->where('id', $id)->update(['activa' => 0, 'updated_at' => Time::now()->toDateTimeString()]);
        $borrar = $this->db->table('tarea_instancias')->where('tarea_id', $id)->where('estado', 'pendiente');

        // La escolar vive en una sola instancia (la del día de creación), aunque su fecha ya haya pasado.
        if ($actual['tipo'] !== 'escolar') {
            $borrar->where('fecha >=', $hoy);
        }

        $borrar->delete();
        $this->db->transComplete();
    }

    /**
     * E-32 · Categorías activas.
     *
     * @return list<array{id: int, nombre: string, icono: string, color: string}>
     */
    public function categorias(): array
    {
        $filas = $this->db->table('categorias')->select('id, nombre, icono, color')->where('activa', 1)->orderBy('id')->get()->getResultArray();

        return array_map(static fn (array $c): array => [
            'id' => (int) $c['id'], 'nombre' => $c['nombre'], 'icono' => $c['icono'], 'color' => $c['color'],
        ], $filas);
    }

    // ===== Escolares propias (RF-19) =====

    /**
     * E-18 · Tareas propias activas del perfil.
     *
     * @return list<array<string, mixed>>
     */
    public function propias(Perfil $perfil): array
    {
        $this->exigirAutonomia($perfil);

        return $this->listar($perfil->id, true, 'perfil');
    }

    /**
     * E-19 · El servidor fija perfil, `creada_por`, puntos, categoría "Escuela" y `una_vez` desde hoy.
     *
     * @param array<string, mixed> $entrada `TareaPropiaEntrada`
     *
     * @return array<string, mixed>
     */
    public function crearPropia(Perfil $perfil, array $entrada): array
    {
        $this->exigirAutonomia($perfil);
        $this->exigirEscolar($entrada);
        [$tarea, $recurrencia] = $this->validarPropia($entrada, $this->hoy());

        return $this->guardarNueva($tarea + [
            'perfil_id'             => $perfil->id,
            'categoria_id'          => $this->categoriaEscuela(),
            'puntos'                => model(ConfiguracionModel::class)->entero('puntos_tarea_propia', self::PUNTOS_PROPIA_POR_OMISION),
            'tipo'                  => 'escolar',
            'creada_por'            => 'perfil',
            'creada_por_usuario_id' => null,
        ], $recurrencia);
    }

    /**
     * E-20 · Editar una propia no aprobada (conserva puntos y fecha de inicio).
     *
     * @param array<string, mixed> $entrada
     *
     * @return array<string, mixed>
     */
    public function editarPropia(Perfil $perfil, int $id, array $entrada): array
    {
        $actual = $this->propiaEditable($perfil, $id);
        $this->exigirEscolar($entrada);
        [$tarea, $recurrencia] = $this->validarPropia($entrada, (string) $actual['fecha_inicio']);

        return $this->guardarCambios($id, $tarea, $recurrencia);
    }

    /**
     * E-21 · Desactivar una propia no aprobada.
     */
    public function desactivarPropia(Perfil $perfil, int $id): void
    {
        $this->propiaEditable($perfil, $id);
        $this->desactivar($id);
    }

    // ===== Internos =====

    private function consulta(): BaseBuilder
    {
        return $this->db->table('tareas t')
            ->select('t.*, r.frecuencia, r.dias_semana, r.hora, r.fecha_inicio, r.fecha_fin')
            ->join('tarea_recurrencias r', 'r.tarea_id = t.id');
    }

    /**
     * @return array<string, mixed>
     */
    private function buscar(int $id): array
    {
        return $this->consulta()->where('t.id', $id)->get()->getRowArray() ?? throw ApiException::noEncontrado();
    }

    /**
     * Forma `Tarea` del contrato con `recurrencia` anidada.
     *
     * @param array<string, mixed> $fila
     *
     * @return array<string, mixed>
     */
    public static function aTarea(array $fila): array
    {
        $dias = is_string($fila['dias_semana']) && $fila['dias_semana'] !== '' ? explode(',', $fila['dias_semana']) : null;

        return [
            'id'            => (int) $fila['id'],
            'perfil_id'     => (int) $fila['perfil_id'],
            'categoria_id'  => (int) $fila['categoria_id'],
            'titulo'        => $fila['titulo'],
            'icono'         => $fila['icono'],
            'puntos'        => (int) $fila['puntos'],
            'tipo'          => $fila['tipo'],
            'materia'       => $fila['materia'],
            'fecha_entrega' => $fila['fecha_entrega'],
            'requiere_foto' => (bool) (int) $fila['requiere_foto'],
            'creada_por'    => $fila['creada_por'],
            'activa'        => (bool) (int) $fila['activa'],
            'recurrencia'   => [
                'frecuencia'   => $fila['frecuencia'],
                'dias_semana'  => $dias,
                'hora'         => $fila['hora'] === null ? null : substr((string) $fila['hora'], 0, 5),
                'fecha_inicio' => $fila['fecha_inicio'],
                'fecha_fin'    => $fila['fecha_fin'],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $tarea
     * @param array<string, mixed> $recurrencia
     *
     * @return array<string, mixed>
     */
    private function guardarNueva(array $tarea, array $recurrencia): array
    {
        $ahora = Time::now()->toDateTimeString();

        $this->db->transException(true)->transStart();
        $this->db->table('tareas')->insert($tarea + ['activa' => 1, 'created_at' => $ahora, 'updated_at' => $ahora]);
        $id = (int) $this->db->insertID();
        $this->db->table('tarea_recurrencias')->insert($recurrencia + ['tarea_id' => $id]);
        $this->generador->generarProximos($this->hoy(), $id);
        $this->db->transComplete();

        return self::aTarea($this->buscar($id));
    }

    /**
     * Guarda cambios y regenera: borra las pendientes desde hoy, vuelve a generar y, en una
     * escolar, actualiza su instancia pendiente aunque sea de un día anterior (sigue vigente hasta la entrega).
     *
     * @param array<string, mixed> $tarea
     * @param array<string, mixed> $recurrencia
     *
     * @return array<string, mixed>
     */
    private function guardarCambios(int $id, array $tarea, array $recurrencia): array
    {
        $hoy = $this->hoy();

        $this->db->transException(true)->transStart();
        $this->db->table('tareas')->where('id', $id)->update($tarea + ['updated_at' => Time::now()->toDateTimeString()]);
        $this->db->table('tarea_recurrencias')->where('tarea_id', $id)->update($recurrencia);
        $this->db->table('tarea_instancias')->where('tarea_id', $id)->where('estado', 'pendiente')->where('fecha >=', $hoy)->delete();
        $this->generador->generarProximos($hoy, $id);

        $guardada = $this->buscar($id);

        if ($guardada['tipo'] === 'escolar') {
            $this->db->table('tarea_instancias')
                ->where('tarea_id', $id)->where('estado', 'pendiente')->where('fecha <', $hoy)
                ->update([
                    'puntos'        => (int) $guardada['puntos'],
                    'hora'          => $guardada['hora'],
                    'fecha_entrega' => $guardada['fecha_entrega'],
                    'updated_at'    => Time::now()->toDateTimeString(),
                ]);
        }

        $this->db->transComplete();

        return self::aTarea($guardada);
    }

    private function categoriaEscuela(): int
    {
        $fila = $this->db->table('categorias')->select('id')->where('nombre', 'Escuela')->get()->getRowArray();

        if ($fila === null) {
            throw new \RuntimeException('Falta la categoría "Escuela" (CatalogoSeeder).');
        }

        return (int) $fila['id'];
    }

    /**
     * RF-19: las Exploradoras no tienen tareas propias.
     */
    private function exigirAutonomia(Perfil $perfil): void
    {
        if ($perfil->nivel_ux === 'exploradoras') {
            throw ApiException::prohibido('Tu nivel todavía no crea tareas propias.');
        }
    }

    /**
     * RF-19: en el S2 Aventureras y Pro solo crean escolares (Pro amplía a otros tipos en el S5).
     *
     * @param array<string, mixed> $entrada
     */
    private function exigirEscolar(array $entrada): void
    {
        if (($entrada['tipo'] ?? 'escolar') !== 'escolar') {
            throw ApiException::prohibido('Por ahora solo puedes crear tareas de la escuela.');
        }
    }

    /**
     * Propia del perfil, activa y sin instancias aprobadas.
     *
     * @return array<string, mixed>
     */
    private function propiaEditable(Perfil $perfil, int $id): array
    {
        $this->exigirAutonomia($perfil);
        $actual = $this->buscar($id);

        if ((int) $actual['perfil_id'] !== $perfil->id || $actual['creada_por'] !== 'perfil') {
            throw ApiException::prohibido('Esta tarea no es tuya.');
        }

        if ((int) $actual['activa'] !== 1) {
            throw ApiException::noEncontrado();
        }

        $aprobadas = $this->db->table('tarea_instancias')->where(['tarea_id' => $id, 'estado' => 'aprobada'])->countAllResults();

        if ($aprobadas > 0) {
            throw ApiException::estadoInvalido('Esta tarea ya se aprobó; ya no se puede cambiar.');
        }

        return $actual;
    }

    /**
     * Valida `TareaPropiaEntrada` (solo escolar). Puntos, perfil, categoría y `creada_por` no se leen del cliente.
     *
     * @param array<string, mixed> $entrada
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function validarPropia(array $entrada, string $fechaInicio): array
    {
        $campos = [];
        $hoy    = $this->hoy();
        $tarea  = $this->validarComunes($entrada, $campos);

        $materia = $this->validarMateria($entrada, $campos);
        $entrega = $entrada['fecha_entrega'] ?? null;

        if (! is_string($entrega) || ! ReglaRecurrencia::esFechaValida($entrega) || $entrega < $hoy) {
            $campos['fecha_entrega'] = 'Elige una fecha de entrega desde hoy.';
        }

        $hora = $this->validarHora($entrada['recurrencia']['hora'] ?? null, $campos);

        if ($campos !== []) {
            throw ApiException::validacion($campos);
        }

        return [
            $tarea + ['materia' => $materia, 'fecha_entrega' => $entrega],
            ['frecuencia' => 'una_vez', 'dias_semana' => null, 'hora' => $hora, 'fecha_inicio' => $fechaInicio, 'fecha_fin' => null],
        ];
    }

    /**
     * Valida `TareaEntrada` del padre. Campos de error planos (`titulo`, `dias_semana`, …) como los usa el formulario.
     *
     * @param array<string, mixed>      $entrada
     * @param array<string, mixed>|null $actual  Tarea existente (edición) o null (alta)
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function validar(array $entrada, ?array $actual): array
    {
        $campos = [];
        $tarea  = $this->validarComunes($entrada, $campos);
        $tipo   = $entrada['tipo'] ?? null;

        if (! in_array($tipo, TareaModel::TIPOS, true)) {
            $campos['tipo'] = 'Elige un tipo de la lista.';
            $tipo           = null;
        }

        $perfilId = $entrada['perfil_id'] ?? null;

        if (! is_int($perfilId) || $this->db->table('perfiles')->where('id', $perfilId)->countAllResults() === 0) {
            $campos['perfil_id'] = 'Elige a quién se asigna.';
        }

        $categoriaId = $entrada['categoria_id'] ?? null;

        if (! is_int($categoriaId) || $this->db->table('categorias')->where(['id' => $categoriaId, 'activa' => 1])->countAllResults() === 0) {
            $campos['categoria_id'] = 'Elige una categoría.';
        }

        $puntos = $entrada['puntos'] ?? null;
        $minimo = $tipo === 'actividad' ? 0 : 1;

        if (! is_int($puntos) || $puntos < $minimo || $puntos > 100) {
            $campos['puntos'] = $tipo === 'actividad' ? 'Entre 0 y 100 puntos.' : 'Entre 1 y 100 puntos.';
        }

        $r          = is_array($entrada['recurrencia'] ?? null) ? $entrada['recurrencia'] : [];
        $frecuencia = $r['frecuencia'] ?? null;

        if (! in_array($frecuencia, ReglaRecurrencia::FRECUENCIAS, true)) {
            $campos['frecuencia'] = 'Elige cada cuándo se repite.';
            $frecuencia           = null;
        } elseif ($tipo === 'escolar' && $frecuencia !== 'una_vez') {
            $campos['frecuencia'] = 'Las tareas escolares son de una sola vez.';
        }

        $inicio = $r['fecha_inicio'] ?? null;

        if (! is_string($inicio) || ! ReglaRecurrencia::esFechaValida($inicio)) {
            $campos['fecha_inicio'] = 'Elige la fecha.';
            $inicio                 = null;
        }

        $fin = $frecuencia === 'una_vez' ? null : ($r['fecha_fin'] ?? null);

        if ($fin !== null && (! is_string($fin) || ! ReglaRecurrencia::esFechaValida($fin) || ($inicio !== null && $fin < $inicio))) {
            $campos['fecha_fin'] = 'El fin no puede ser antes del inicio.';
        }

        $dias = null;

        if ($frecuencia === 'semanal') {
            $lista = $r['dias_semana'] ?? null;
            $dias  = is_array($lista) ? array_values(array_unique($lista)) : [];

            if ($dias === [] || array_diff($dias, array_keys(ReglaRecurrencia::DIAS)) !== []) {
                $campos['dias_semana'] = 'Elige al menos un día.';
            }
        }

        $hora = $this->validarHora($r['hora'] ?? null, $campos);

        $materia = null;
        $entrega = null;

        if ($tipo === 'escolar') {
            $materia = $this->validarMateria($entrada, $campos);
            $entrega = $entrada['fecha_entrega'] ?? null;
            $minimo  = $actual === null ? max($this->hoy(), (string) $inicio) : (string) $inicio;

            if (! is_string($entrega) || ! ReglaRecurrencia::esFechaValida($entrega) || $entrega < $minimo) {
                $campos['fecha_entrega'] = 'La entrega no puede ser antes de hoy ni del inicio.';
            }
        }

        if ($campos !== []) {
            throw ApiException::validacion($campos);
        }

        try {
            $regla = new ReglaRecurrencia((string) $frecuencia, $dias ?? [], (string) $inicio, $fin);
        } catch (InvalidArgumentException) {
            throw ApiException::validacion(['frecuencia' => 'La repetición no es válida.']);
        }

        return [
            $tarea + [
                'perfil_id' => $perfilId, 'categoria_id' => $categoriaId, 'puntos' => $puntos, 'tipo' => $tipo,
                'materia' => $materia, 'fecha_entrega' => $entrega,
            ],
            [
                'frecuencia'   => $regla->frecuencia(),
                'dias_semana'  => $regla->diasComoSet(),
                'hora'         => $hora,
                'fecha_inicio' => $regla->fechaInicio(),
                'fecha_fin'    => $regla->fechaFin(),
            ],
        ];
    }

    /**
     * `titulo`, `icono` y `requiere_foto`, comunes a tareas del padre y propias.
     *
     * @param array<string, mixed>  $entrada
     * @param array<string, string> $campos
     *
     * @return array<string, mixed>
     */
    private function validarComunes(array $entrada, array &$campos): array
    {
        $titulo = is_string($entrada['titulo'] ?? null) ? trim($entrada['titulo']) : '';

        if ($titulo === '' || mb_strlen($titulo) > 80) {
            $campos['titulo'] = 'Escribe un título de hasta 80 caracteres.';
        }

        if (! Catalogo::esIcono($entrada['icono'] ?? null)) {
            $campos['icono'] = 'Elige un ícono de la lista.';
        }

        $foto = $entrada['requiere_foto'] ?? false;

        if (! is_bool($foto)) {
            $campos['requiere_foto'] = 'Debe ser verdadero o falso.';
        }

        return ['titulo' => $titulo, 'icono' => $entrada['icono'] ?? '', 'requiere_foto' => $foto === true ? 1 : 0];
    }

    /**
     * @param array<string, mixed>  $entrada
     * @param array<string, string> $campos
     */
    private function validarMateria(array $entrada, array &$campos): string
    {
        $materia = is_string($entrada['materia'] ?? null) ? trim($entrada['materia']) : '';

        if ($materia === '' || mb_strlen($materia) > 40) {
            $campos['materia'] = 'Escribe la materia (máximo 40 caracteres).';
        }

        return $materia;
    }

    /**
     * `HH:MM` → `HH:MM:00`, o null.
     *
     * @param array<string, string> $campos
     */
    private function validarHora(mixed $hora, array &$campos): ?string
    {
        if ($hora === null || $hora === '') {
            return null;
        }

        if (! is_string($hora) || preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora) !== 1) {
            $campos['hora'] = 'Escribe la hora como HH:MM.';

            return null;
        }

        return $hora . ':00';
    }
}
