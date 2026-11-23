<?php

declare(strict_types=1);

namespace Tests\Feature\Tareas;

use Tests\Support\ApiTestCase;

/**
 * T-S2-05 · Escolares propias (RF-19): E-18 a E-21. CP-035 a CP-038.
 *
 * @internal
 */
final class TareasPropiasTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarSesionDemo();
    }

    /**
     * @return array<string, mixed>
     */
    private function propia(array $cambios = []): array
    {
        return array_merge([
            'titulo' => 'Resolver la página 45', 'icono' => 'libro', 'tipo' => 'escolar', 'materia' => 'Matemáticas',
            'fecha_entrega' => $this->hoy(2), 'requiere_foto' => false,
            'recurrencia' => ['frecuencia' => 'una_vez', 'dias_semana' => null, 'hora' => '16:00', 'fecha_inicio' => $this->hoy(), 'fecha_fin' => null],
        ], $cambios);
    }

    private function escuelaId(): int
    {
        return (int) $this->db->table('categorias')->where('nombre', 'Escuela')->get()->getRowArray()['id'];
    }

    /**
     * CP-035 · Aventurera crea una escolar propia con `puntos: 50` y otro `perfil_id`: el servidor fija los suyos.
     */
    public function testAventureraCreaEscolarPropiaYElServidorFijaLosCampos(): void
    {
        $this->entrarPerfil('Sol');

        $r = $this->api('post', 'yo/tareas', $this->propia([
            'puntos' => 50, 'perfil_id' => $this->perfilId('Luna'), 'creada_por' => 'padre',
            'categoria_id' => 1, 'activa' => false,
            'recurrencia' => ['frecuencia' => 'diaria', 'dias_semana' => ['lu'], 'hora' => '16:00', 'fecha_inicio' => $this->hoy(-5), 'fecha_fin' => $this->hoy(9)],
        ]));

        $r->assertStatus(201);
        $tarea = $this->cuerpo($r);
        $this->assertSame('perfil', $tarea['creada_por']);
        $this->assertSame($this->perfilId('Sol'), $tarea['perfil_id']);
        $this->assertSame(5, $tarea['puntos']);
        $this->assertSame($this->escuelaId(), $tarea['categoria_id']);
        $this->assertSame('escolar', $tarea['tipo']);
        $this->assertTrue($tarea['activa']);
        $this->assertSame([
            'frecuencia' => 'una_vez', 'dias_semana' => null, 'hora' => '16:00', 'fecha_inicio' => $this->hoy(), 'fecha_fin' => null,
        ], $tarea['recurrencia']);
        $this->assertNull($this->fila('tareas', $tarea['id'])['creada_por_usuario_id']);

        $instancia = $this->instancia($tarea['id'], $this->hoy());
        $this->assertSame('5', (string) $instancia['puntos']);
        $this->assertSame($this->hoy(2), $instancia['fecha_entrega']);
        $this->assertSame(0, $this->db->table('movimientos_puntos')->countAllResults(), 'Crearla no da puntos.');
    }

    public function testUsaPuntosTareaPropiaDeLaConfiguracion(): void
    {
        $this->db->table('configuracion')->where('clave', 'puntos_tarea_propia')->update(['valor' => '8']);
        $this->entrarPerfil('Sol');

        $this->assertSame(8, $this->cuerpo($this->conEstado($this->api('post', 'yo/tareas', $this->propia()), 201))['puntos']);
    }

    /**
     * CP-036 · Aventurera intenta crear una tarea de tipo `casa`: 403 `PROHIBIDO`.
     */
    public function testAventureraNoCreaTareaDeCasa(): void
    {
        $this->entrarPerfil('Sol');

        $this->assertError($this->api('post', 'yo/tareas', $this->propia(['tipo' => 'casa'])), 403, 'PROHIBIDO');
        $this->assertSame(0, $this->db->table('tareas')->where('creada_por', 'perfil')->countAllResults());
    }

    /**
     * CP-037 · Exploradora llama `POST /yo/tareas`: 403 `PROHIBIDO` (y en las otras tres rutas).
     */
    public function testExploradoraNoTieneTareasPropias(): void
    {
        $this->entrarPerfil('Luna');
        $ajena = (int) $this->db->table('tareas')->where('perfil_id', $this->perfilId('Luna'))->get()->getRowArray()['id'];

        $this->assertError($this->api('post', 'yo/tareas', $this->propia()), 403, 'PROHIBIDO');
        $this->assertError($this->api('get', 'yo/tareas'), 403, 'PROHIBIDO');
        $this->assertError($this->api('put', "yo/tareas/{$ajena}", $this->propia()), 403, 'PROHIBIDO');
        $this->assertError($this->api('delete', "yo/tareas/{$ajena}"), 403, 'PROHIBIDO');
        $this->assertSame(0, $this->db->table('tareas')->where('creada_por', 'perfil')->countAllResults());
    }

    public function testValidacion(): void
    {
        $this->entrarPerfil('Sol');

        $r = $this->api('post', 'yo/tareas', $this->propia(['titulo' => '', 'icono' => '', 'materia' => '', 'fecha_entrega' => $this->hoy(-1)]));

        $this->assertError($r, 400, 'VALIDACION');
        $this->assertEqualsCanonicalizing(['titulo', 'icono', 'materia', 'fecha_entrega'], array_keys($this->cuerpo($r)['error']['campos']));

        $r = $this->api('post', 'yo/tareas', $this->propia(['recurrencia' => ['hora' => '7pm']]));
        $this->assertSame(['hora'], array_keys($this->cuerpo($r)['error']['campos']));
    }

    public function testListaSoloSusPropiasActivas(): void
    {
        $this->entrarPerfil('Sol');
        $mia = $this->cuerpo($this->conEstado($this->api('post', 'yo/tareas', $this->propia()), 201));
        $desactivada = $this->cuerpo($this->conEstado($this->api('post', 'yo/tareas', $this->propia(['titulo' => 'Vieja'])), 201));
        $this->api('delete', "yo/tareas/{$desactivada['id']}")->assertStatus(204);
        $this->api('post', 'perfiles/salir')->assertStatus(200);
        $this->entrarPerfil('Estrella');
        $this->api('post', 'yo/tareas', $this->propia(['titulo' => 'De Estrella']))->assertStatus(201);
        $this->api('post', 'perfiles/salir')->assertStatus(200);
        $this->entrarPerfil('Sol');

        $tareas = $this->cuerpo($this->conEstado($this->api('get', 'yo/tareas'), 200));

        $this->assertSame([$mia['id']], array_column($tareas, 'id'));
        $this->assertSame($mia, $tareas[0]);
    }

    public function testEditarPropia(): void
    {
        $this->entrarPerfil('Sol');
        $tarea = $this->cuerpo($this->conEstado($this->api('post', 'yo/tareas', $this->propia()), 201));

        $editada = $this->cuerpo($this->conEstado($this->api('put', "yo/tareas/{$tarea['id']}", $this->propia([
            'titulo' => 'Página 46', 'materia' => 'Español', 'fecha_entrega' => $this->hoy(5), 'puntos' => 99,
            'recurrencia' => ['frecuencia' => 'una_vez', 'dias_semana' => null, 'hora' => null, 'fecha_inicio' => $this->hoy(), 'fecha_fin' => null],
        ])), 200));

        $this->assertSame('Página 46', $editada['titulo']);
        $this->assertSame('Español', $editada['materia']);
        $this->assertSame(5, $editada['puntos']);
        $this->assertNull($editada['recurrencia']['hora']);
        $instancia = $this->instancia($tarea['id'], $this->hoy());
        $this->assertSame($this->hoy(5), $instancia['fecha_entrega']);
        $this->assertNull($instancia['hora']);
    }

    public function testEditarDesdeUnDiaPosteriorActualizaSuInstancia(): void
    {
        $this->entrarPerfil('Sol');
        $tarea = $this->cuerpo($this->conEstado($this->api('post', 'yo/tareas', $this->propia()), 201));
        // La escolar se creó ayer: su instancia es de una fecha pasada pero sigue vigente.
        $this->db->table('tarea_recurrencias')->where('tarea_id', $tarea['id'])->update(['fecha_inicio' => $this->hoy(-1)]);
        $this->db->table('tarea_instancias')->where('tarea_id', $tarea['id'])->update(['fecha' => $this->hoy(-1)]);

        $this->api('put', "yo/tareas/{$tarea['id']}", $this->propia(['fecha_entrega' => $this->hoy(6)]))->assertStatus(200);

        $instancias = $this->db->table('tarea_instancias')->where('tarea_id', $tarea['id'])->get()->getResultArray();
        $this->assertCount(1, $instancias);
        $this->assertSame($this->hoy(-1), $instancias[0]['fecha']);
        $this->assertSame($this->hoy(6), $instancias[0]['fecha_entrega']);
        $this->assertSame($this->hoy(-1), $this->db->table('tarea_recurrencias')->where('tarea_id', $tarea['id'])->get()->getRowArray()['fecha_inicio']);
    }

    public function testNoEditaNiDesactivaTareasAjenas(): void
    {
        $this->entrarPerfil('Sol');
        $delPadre = (int) $this->db->table('tareas')->where(['perfil_id' => $this->perfilId('Sol'), 'creada_por' => 'padre'])->get()->getRowArray()['id'];
        $this->api('post', 'perfiles/salir')->assertStatus(200);
        $this->entrarPerfil('Estrella');
        $deEstrella = $this->cuerpo($this->conEstado($this->api('post', 'yo/tareas', $this->propia()), 201));
        $this->api('post', 'perfiles/salir')->assertStatus(200);
        $this->entrarPerfil('Sol');

        foreach ([$delPadre, $deEstrella['id']] as $id) {
            $this->assertError($this->api('put', "yo/tareas/{$id}", $this->propia()), 403, 'PROHIBIDO');
            $this->assertError($this->api('delete', "yo/tareas/{$id}"), 403, 'PROHIBIDO');
        }

        $this->assertError($this->api('put', 'yo/tareas/999999', $this->propia()), 404, 'NO_ENCONTRADO');
    }

    public function testPropiaAprobadaNoSeEditaNiDesactiva(): void
    {
        $this->entrarPerfil('Sol');
        $tarea = $this->cuerpo($this->conEstado($this->api('post', 'yo/tareas', $this->propia()), 201));
        $this->db->table('tarea_instancias')->where('tarea_id', $tarea['id'])->update(['estado' => 'aprobada']);

        $this->assertError($this->api('put', "yo/tareas/{$tarea['id']}", $this->propia(['titulo' => 'Otro'])), 409, 'ESTADO_INVALIDO');
        $this->assertError($this->api('delete', "yo/tareas/{$tarea['id']}"), 409, 'ESTADO_INVALIDO');
        $this->assertSame('1', (string) $this->fila('tareas', $tarea['id'])['activa']);
    }

    public function testDesactivarPropiaBorraSuInstanciaPendiente(): void
    {
        $this->entrarPerfil('Sol');
        $tarea = $this->cuerpo($this->conEstado($this->api('post', 'yo/tareas', $this->propia()), 201));

        $this->api('delete', "yo/tareas/{$tarea['id']}")->assertStatus(204);

        $this->assertSame('0', (string) $this->fila('tareas', $tarea['id'])['activa']);
        $this->assertSame(0, $this->db->table('tarea_instancias')->where('tarea_id', $tarea['id'])->countAllResults());
    }

    /**
     * CP-038 (parte del S2) · La escolar propia aparece en `GET /admin/tareas` con `creada_por = perfil`
     * y, al marcarla hecha, queda esperando aprobación con 5 puntos y sin movimiento.
     */
    public function testPropiaApareceAlPadreYSeMarcaHecha(): void
    {
        $this->entrarPerfil('Sol');
        $tarea = $this->cuerpo($this->conEstado($this->api('post', 'yo/tareas', $this->propia()), 201));
        $instanciaId = (int) $this->instancia($tarea['id'], $this->hoy())['id'];

        $hecha = $this->cuerpo($this->conEstado($this->api('post', "yo/instancias/{$instanciaId}/hecha"), 200));
        $this->assertSame('hecha', $hecha['estado']);
        $this->assertSame(5, $hecha['puntos']);
        $this->assertSame(0, $this->db->table('movimientos_puntos')->countAllResults());

        $this->entrarModoPadre();
        $propias = array_values(array_filter(
            $this->cuerpo($this->api('get', 'admin/tareas?perfil_id=' . $this->perfilId('Sol'))),
            static fn (array $t): bool => $t['creada_por'] === 'perfil',
        ));
        $this->assertSame([$tarea['id']], array_column($propias, 'id'));

        // El padre puede editarla (RF-19) y conserva `creada_por = perfil`.
        $entrada = $this->tareaEntrada('Sol', ['tipo' => 'escolar', 'materia' => 'Matemáticas', 'fecha_entrega' => $this->hoy(2), 'categoria_id' => $this->escuelaId()], ['frecuencia' => 'una_vez']);
        $this->assertSame('perfil', $this->cuerpo($this->conEstado($this->api('put', "admin/tareas/{$tarea['id']}", $entrada), 200))['creada_por']);
    }
}
