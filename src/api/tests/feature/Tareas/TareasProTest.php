<?php

declare(strict_types=1);

namespace Tests\Feature\Tareas;

use CodeIgniter\Test\TestResponse;
use Tests\Support\ApiTestCase;

/**
 * T-S5-02 · Autonomía del nivel Pro (RF-15): E-18 a E-21 con cualquier tipo. CP-082 a CP-084.
 *
 * @internal
 */
final class TareasProTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->sinTareasDemo();
        $this->iniciarSesionDemo();
    }

    /**
     * `TareaPropiaEntrada` de una rutina de casa, diaria desde hoy (como la manda la web).
     *
     * @param array<string, mixed> $cambios
     * @param array<string, mixed> $recurrencia
     *
     * @return array<string, mixed>
     */
    private function rutina(array $cambios = [], array $recurrencia = []): array
    {
        return array_merge([
            'titulo' => 'Tender mi cama', 'icono' => 'cama', 'tipo' => 'casa', 'materia' => null, 'fecha_entrega' => null,
            'requiere_foto' => false,
            'recurrencia' => array_merge(['frecuencia' => 'diaria', 'dias_semana' => null, 'hora' => null, 'fecha_inicio' => $this->hoy(), 'fecha_fin' => null], $recurrencia),
        ], $cambios);
    }

    private function crear(array $entrada): TestResponse
    {
        return $this->api('post', 'yo/tareas', $entrada);
    }

    private function categoria(string $nombre): int
    {
        return (int) $this->db->table('categorias')->where('nombre', $nombre)->get()->getRowArray()['id'];
    }

    /**
     * @return list<string>
     */
    private function fechasPendientes(int $tareaId): array
    {
        return array_column($this->db->table('tarea_instancias')->where(['tarea_id' => $tareaId, 'estado' => 'pendiente'])->orderBy('fecha')->get()->getResultArray(), 'fecha');
    }

    /**
     * CP-082 · Pro crea una tarea propia: `creada_por = perfil` y `puntos = 5`, aunque mande otros.
     */
    public function testProCreaTareaPropiaDeCasa(): void
    {
        $this->entrarPerfil('Estrella');

        $tarea = $this->cuerpo($this->conEstado($this->crear($this->rutina([
            'puntos' => 80, 'perfil_id' => $this->perfilId('Luna'), 'creada_por' => 'padre',
        ])), 201));

        $this->assertSame(['perfil', 5, $this->perfilId('Estrella'), 'casa', $this->categoria('Casa')], [
            $tarea['creada_por'], $tarea['puntos'], $tarea['perfil_id'], $tarea['tipo'], $tarea['categoria_id'],
        ]);
        $this->assertSame('diaria', $tarea['recurrencia']['frecuencia']);
        $this->assertNull($tarea['materia']);
        $this->assertCount(8, $this->fechasPendientes($tarea['id']), 'Hoy y los 7 días siguientes');
        $this->assertSame([$tarea['id']], array_column($this->cuerpo($this->api('get', 'yo/tareas')), 'id'));
    }

    public function testActividadYCategoriaElegida(): void
    {
        $this->db->table('configuracion')->where('clave', 'puntos_tarea_propia')->update(['valor' => '7']);
        $this->entrarPerfil('Estrella');

        $actividad = $this->cuerpo($this->conEstado($this->crear($this->rutina(['tipo' => 'actividad', 'titulo' => 'Guitarra', 'icono' => 'musica'], [
            'frecuencia' => 'semanal', 'dias_semana' => ['ma', 'ju'], 'hora' => '17:30',
        ])), 201));
        $this->assertSame([$this->categoria('Actividad'), 7, ['ma', 'ju'], '17:30'], [
            $actividad['categoria_id'], $actividad['puntos'], $actividad['recurrencia']['dias_semana'], $actividad['recurrencia']['hora'],
        ]);

        $habito = $this->cuerpo($this->conEstado($this->crear($this->rutina(['categoria_id' => $this->categoria('Hábito')])), 201));
        $this->assertSame($this->categoria('Hábito'), $habito['categoria_id']);

        $escolar = $this->cuerpo($this->conEstado($this->crear([
            'titulo' => 'Ensayo', 'icono' => 'lapiz', 'tipo' => 'escolar', 'materia' => 'Español', 'fecha_entrega' => $this->hoy(2),
            'categoria_id' => $this->categoria('Casa'), 'requiere_foto' => false,
            'recurrencia' => ['frecuencia' => 'diaria', 'dias_semana' => null, 'hora' => null, 'fecha_inicio' => $this->hoy(), 'fecha_fin' => null],
        ]), 201));
        $this->assertSame([$this->categoria('Escuela'), 'una_vez'], [$escolar['categoria_id'], $escolar['recurrencia']['frecuencia']], 'La escolar siempre es "Escuela" y de una vez');
    }

    public function testValidacionDeLaRutina(): void
    {
        $this->entrarPerfil('Estrella');

        $r = $this->crear($this->rutina(['titulo' => '', 'icono' => 'nave', 'categoria_id' => 999999], ['frecuencia' => 'semanal', 'dias_semana' => [], 'fecha_inicio' => $this->hoy(-1)]));

        $this->assertError($r, 400, 'VALIDACION');
        $this->assertEqualsCanonicalizing(['titulo', 'icono', 'categoria_id', 'dias_semana', 'fecha_inicio'], array_keys($this->cuerpo($r)['error']['campos']));
        $this->assertError($this->crear($this->rutina(['tipo' => 'otro'])), 400, 'VALIDACION');
    }

    /**
     * CP-083 · Aventurera intenta crear una `actividad`: 403 `PROHIBIDO` (solo Pro, RF-15 y RF-19).
     */
    public function testAventureraNoCreaActividad(): void
    {
        $this->entrarPerfil('Sol');

        $this->assertError($this->crear($this->rutina(['tipo' => 'actividad'])), 403, 'PROHIBIDO');
        $this->assertSame(0, $this->db->table('tareas')->where('creada_por', 'perfil')->countAllResults());
    }

    /**
     * CP-084 · Pro edita (o desactiva) una tarea creada por el padre: 403 `PROHIBIDO`.
     */
    public function testProNoEditaTareaDelPadre(): void
    {
        $this->entrarModoPadre();
        $delPadre = $this->crearTarea('Estrella', ['titulo' => 'Del padre']);
        $this->entrarPerfil('Estrella');

        $this->assertError($this->api('put', "yo/tareas/{$delPadre['id']}", $this->rutina()), 403, 'PROHIBIDO');
        $this->assertError($this->api('delete', "yo/tareas/{$delPadre['id']}"), 403, 'PROHIBIDO');
        $this->assertSame('Del padre', $this->fila('tareas', $delPadre['id'])['titulo']);
        $this->assertNotContains($delPadre['id'], array_column($this->cuerpo($this->api('get', 'yo/tareas')), 'id'));
    }

    public function testEditarRutinaConAprobadasSoloCambiaLasPendientesFuturas(): void
    {
        $this->entrarPerfil('Estrella');
        $tarea = $this->cuerpo($this->conEstado($this->crear($this->rutina()), 201));
        $this->db->table('tarea_instancias')->where(['tarea_id' => $tarea['id'], 'fecha' => $this->hoy()])->update(['estado' => 'aprobada']);

        $editada = $this->cuerpo($this->conEstado($this->api('put', "yo/tareas/{$tarea['id']}", $this->rutina(['titulo' => 'Tender y ordenar', 'tipo' => 'actividad'], [
            'frecuencia' => 'semanal', 'dias_semana' => ['sa'],
        ])), 200));

        $this->assertSame(['Tender y ordenar', 'actividad', $this->categoria('Actividad')], [$editada['titulo'], $editada['tipo'], $editada['categoria_id']]);
        $this->assertSame('aprobada', $this->instancia($tarea['id'], $this->hoy())['estado'], 'Lo aprobado no cambia');
        foreach ($this->fechasPendientes($tarea['id']) as $fecha) {
            $this->assertSame('6', date('N', strtotime($fecha)), "Solo sábados pendientes: {$fecha}");
        }

        $this->assertSame(204, $this->api('delete', "yo/tareas/{$tarea['id']}")->response()->getStatusCode());
        $this->assertSame([], $this->fechasPendientes($tarea['id']));
        $this->assertSame('aprobada', $this->instancia($tarea['id'], $this->hoy())['estado'], 'Desactivar conserva el historial');
    }

    public function testUnaVezAprobadaYCambioDeEscolarNoSePermiten(): void
    {
        $this->entrarPerfil('Estrella');
        $unaVez = $this->cuerpo($this->conEstado($this->crear($this->rutina([], ['frecuencia' => 'una_vez'])), 201));
        $this->db->table('tarea_instancias')->where('tarea_id', $unaVez['id'])->update(['estado' => 'aprobada']);

        $this->assertError($this->api('put', "yo/tareas/{$unaVez['id']}", $this->rutina(['titulo' => 'Otro'], ['frecuencia' => 'una_vez'])), 409, 'ESTADO_INVALIDO');
        $this->assertError($this->api('delete', "yo/tareas/{$unaVez['id']}"), 409, 'ESTADO_INVALIDO');

        $rutina = $this->cuerpo($this->conEstado($this->crear($this->rutina()), 201));
        $r      = $this->api('put', "yo/tareas/{$rutina['id']}", [
            'titulo' => 'Ensayo', 'icono' => 'lapiz', 'tipo' => 'escolar', 'materia' => 'Español', 'fecha_entrega' => $this->hoy(2),
            'requiere_foto' => false, 'recurrencia' => ['frecuencia' => 'una_vez', 'dias_semana' => null, 'hora' => null, 'fecha_inicio' => $this->hoy(), 'fecha_fin' => null],
        ]);
        $this->assertError($r, 400, 'VALIDACION');
        $this->assertArrayHasKey('tipo', $this->cuerpo($r)['error']['campos']);
    }

    public function testElPadreVeLaRutinaComoCreadaPorElla(): void
    {
        $this->entrarPerfil('Estrella');
        $tarea = $this->cuerpo($this->conEstado($this->crear($this->rutina()), 201));
        $this->entrarModoPadre();

        $propias = $this->cuerpo($this->conEstado($this->api('get', 'admin/tareas?perfil_id=' . $this->perfilId('Estrella')), 200));

        $this->assertSame(['perfil'], array_column(array_filter($propias, static fn (array $t): bool => $t['id'] === $tarea['id']), 'creada_por'));
    }
}
