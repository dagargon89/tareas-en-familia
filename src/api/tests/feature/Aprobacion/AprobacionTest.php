<?php

declare(strict_types=1);

namespace Tests\Feature\Aprobacion;

use Tests\Support\ApiTestCase;

/**
 * T-S3-02 · Aprobación y libro de puntos (RF-07, RF-08): E-33 a E-35.
 * CP-045 a CP-049, CP-051, CP-052 y CP-038.
 *
 * @internal
 */
final class AprobacionTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->sinTareasDemo();
        $this->iniciarSesionDemo();
        $this->entrarModoPadre();
    }

    /**
     * Instancia `hecha` de hoy para el perfil, con los puntos dados.
     */
    private function hecha(string $apodo, int $puntos = 7, ?string $completadaEn = null, string $titulo = 'Regar'): int
    {
        $tarea = $this->crearTarea($apodo, ['puntos' => $puntos, 'titulo' => $titulo]);
        $id    = (int) $this->instancia($tarea['id'], $this->hoy())['id'];
        $this->db->table('tarea_instancias')->where('id', $id)->update([
            'estado' => 'hecha', 'completada_en' => $completadaEn ?? date('Y-m-d H:i:s'),
        ]);

        return $id;
    }

    private function revisar(int $id, array $cuerpo): \CodeIgniter\Test\TestResponse
    {
        return $this->api('post', "admin/instancias/{$id}/revision", $cuerpo);
    }

    /**
     * Movimientos de origen `tarea` (los de insignias del S4 no cuentan aquí).
     */
    private function movimientos(?int $instanciaId = null): int
    {
        $q = $this->db->table('movimientos_puntos')->where('origen', 'tarea');

        if ($instanciaId !== null) {
            $q->where(['origen' => 'tarea', 'origen_id' => $instanciaId]);
        }

        return $q->countAllResults();
    }

    public function testBandejaConLasHechasDeTodasLaMasAntiguaPrimero(): void
    {
        $sol  = $this->hecha('Sol', 5, date('Y-m-d H:i:s', time() - 60), 'De Sol');
        $luna = $this->hecha('Luna', 5, date('Y-m-d H:i:s', time() - 3600), 'De Luna');
        $this->crearTarea('Luna', ['titulo' => 'Pendiente']);

        $bandeja = $this->cuerpo($this->conEstado($this->api('get', 'admin/aprobaciones'), 200));

        $this->assertSame([$luna, $sol], array_column($bandeja, 'id'));
        $this->assertSame('Luna', $bandeja[0]['perfil']['apodo']);
        $this->assertSame([
            'id', 'tarea_id', 'perfil_id', 'fecha', 'hora', 'fecha_entrega', 'titulo', 'icono', 'color', 'tipo', 'materia',
            'puntos', 'estado', 'requiere_foto', 'tiene_evidencia', 'evidencia_id', 'mensaje_devolucion', 'completada_en', 'perfil',
        ], array_keys($bandeja[0]));
        $this->assertSame(['id', 'apodo', 'avatar', 'color', 'nivel_ux', 'sonidos', 'tema', 'bloqueado_hasta'], array_keys($bandeja[0]['perfil']));
    }

    /**
     * CP-045 · Aprobar una `hecha`: `aprobada`, revisión creada y movimiento con los puntos.
     */
    public function testAprobarInstanciaHecha(): void
    {
        $id = $this->hecha('Luna', 7);

        $resultado = $this->cuerpo($this->conEstado($this->revisar($id, ['decision' => 'aprobada']), 200));

        $this->assertSame(['instancia', 'puntos_acreditados', 'insignias_nuevas', 'meta_cumplida'], array_keys($resultado));
        $this->assertSame('aprobada', $resultado['instancia']['estado']);
        $this->assertSame($id, $resultado['instancia']['id']);
        $this->assertSame(7, $resultado['puntos_acreditados']);
        $this->assertSame(['primera_mision'], array_column($resultado['insignias_nuevas'], 'codigo'), 'Desde el S4 (CP-069)');
        $this->assertFalse($resultado['meta_cumplida']);

        $this->assertNotNull($this->fila('tarea_instancias', $id)['aprobada_en']);
        $revision = $this->db->table('revisiones')->where('instancia_id', $id)->get()->getRowArray();
        $this->assertSame('aprobada', $revision['decision']);
        $this->assertSame((string) $this->db->table('usuarios')->get()->getRowArray()['id'], (string) $revision['usuario_id']);
        $mov = $this->db->table('movimientos_puntos')->where(['origen' => 'tarea', 'origen_id' => $id])->get()->getRowArray();
        $this->assertSame('7', (string) $mov['puntos']);
        $this->assertSame((string) $this->perfilId('Luna'), (string) $mov['perfil_id']);
        $this->assertStringContainsString('Regar', $mov['descripcion']);
    }

    /**
     * CP-046 · Aprobar dos veces: la segunda 409 y un solo movimiento (RN-04).
     */
    public function testAprobarDosVeces(): void
    {
        $id = $this->hecha('Luna');
        $this->revisar($id, ['decision' => 'aprobada'])->assertStatus(200);

        $this->assertError($this->revisar($id, ['decision' => 'aprobada']), 409, 'ESTADO_INVALIDO');
        $this->assertSame(1, $this->movimientos($id));
        $this->assertSame(1, $this->db->table('revisiones')->where('instancia_id', $id)->countAllResults());
    }

    /**
     * CP-047 · Devolver sin mensaje: 400 `VALIDACION`.
     */
    public function testDevolverSinMensaje(): void
    {
        $id = $this->hecha('Luna');

        foreach ([['decision' => 'devuelta'], ['decision' => 'devuelta', 'mensaje' => '   '], ['decision' => 'devuelta', 'mensaje' => str_repeat('a', 141)]] as $cuerpo) {
            $r = $this->revisar($id, $cuerpo);
            $this->assertError($r, 400, 'VALIDACION');
            $this->assertArrayHasKey('mensaje', $this->cuerpo($r)['error']['campos']);
        }

        $this->assertSame('hecha', $this->fila('tarea_instancias', $id)['estado']);
        $this->assertSame(0, $this->db->table('revisiones')->countAllResults());
    }

    /**
     * CP-048 · Devolver con mensaje: `devuelta` y el mensaje visible en `GET /yo/dia`.
     */
    public function testDevolverConMensaje(): void
    {
        $id = $this->hecha('Luna');

        $resultado = $this->cuerpo($this->conEstado($this->revisar($id, ['decision' => 'devuelta', 'mensaje' => ' Falta tender la almohada ']), 200));

        $this->assertSame('devuelta', $resultado['instancia']['estado']);
        $this->assertSame('Falta tender la almohada', $resultado['instancia']['mensaje_devolucion']);
        $this->assertSame(0, $resultado['puntos_acreditados']);
        $this->assertSame(0, $this->movimientos());
        $this->assertSame('devuelta', $this->db->table('revisiones')->where('instancia_id', $id)->get()->getRowArray()['decision']);

        $this->entrarPerfil('Luna');
        $dia = $this->cuerpo($this->api('get', 'yo/dia'));
        $this->assertSame('Falta tender la almohada', $dia['instancias'][0]['mensaje_devolucion']);
        $this->assertSame('devuelta', $dia['instancias'][0]['estado']);

        // Vuelve a marcarla y el padre la aprueba.
        $this->api('post', "yo/instancias/{$id}/hecha")->assertStatus(200);
        $this->revisar($id, ['decision' => 'aprobada'])->assertStatus(200);
        $this->assertSame(1, $this->movimientos($id));
        $this->assertSame(2, $this->db->table('revisiones')->where('instancia_id', $id)->countAllResults());
    }

    public function testRevisionInvalida(): void
    {
        $id = $this->hecha('Luna');
        $r  = $this->revisar($id, ['decision' => 'quizas']);
        $this->assertError($r, 400, 'VALIDACION');
        $this->assertArrayHasKey('decision', $this->cuerpo($r)['error']['campos']);

        $pendiente = $this->crearTarea('Luna', ['titulo' => 'Pendiente']);
        $this->assertError($this->revisar((int) $this->instancia($pendiente['id'], $this->hoy())['id'], ['decision' => 'aprobada']), 409, 'ESTADO_INVALIDO');
        $this->assertError($this->revisar(999999, ['decision' => 'aprobada']), 404, 'NO_ENCONTRADO');
    }

    /**
     * CP-049 · Aprobación en lote de 5: 5 resultados y 5 movimientos.
     */
    public function testAprobacionEnLoteDeCinco(): void
    {
        $ids = [];

        foreach (['Luna', 'Sol', 'Estrella', 'Luna', 'Sol'] as $i => $apodo) {
            $ids[] = $this->hecha($apodo, $i + 1, null, "Tarea {$i}");
        }

        $resultados = $this->cuerpo($this->conEstado($this->api('post', 'admin/instancias/revision-lote', ['ids' => $ids]), 200));

        $this->assertCount(5, $resultados);
        $this->assertSame($ids, array_column(array_column($resultados, 'instancia'), 'id'));
        $this->assertSame([1, 2, 3, 4, 5], array_column($resultados, 'puntos_acreditados'));
        $this->assertSame(5, $this->movimientos());
        $this->assertSame(0, $this->db->table('tarea_instancias')->where('estado', 'hecha')->countAllResults());
    }

    public function testLoteOmiteLasQueNoEstanHechaSinAbortar(): void
    {
        $a         = $this->hecha('Luna', 3, null, 'A');
        $b         = $this->hecha('Sol', 4, null, 'B');
        $pendiente = (int) $this->instancia($this->crearTarea('Luna', ['titulo' => 'P'])['id'], $this->hoy())['id'];
        $this->revisar($b, ['decision' => 'aprobada'])->assertStatus(200);

        $resultados = $this->cuerpo($this->conEstado($this->api('post', 'admin/instancias/revision-lote', ['ids' => [$a, $b, $pendiente, 999999, $a]]), 200));

        $this->assertSame([$a], array_column(array_column($resultados, 'instancia'), 'id'));
        $this->assertSame(2, $this->movimientos());
        $this->assertSame('pendiente', $this->fila('tarea_instancias', $pendiente)['estado']);
    }

    public function testLoteValidaIds(): void
    {
        foreach ([[], ['ids' => []], ['ids' => [1, 'dos']], ['ids' => 'todo']] as $cuerpo) {
            $r = $this->api('post', 'admin/instancias/revision-lote', $cuerpo);
            $this->assertError($r, 400, 'VALIDACION');
            $this->assertArrayHasKey('ids', $this->cuerpo($r)['error']['campos']);
        }
    }

    /**
     * CP-051 · Un canje aprobado no baja el XP: XP igual y saldo menor (RN-05).
     */
    public function testCanjeAprobadoNoBajaElXp(): void
    {
        $this->revisar($this->hecha('Luna', 10), ['decision' => 'aprobada'])->assertStatus(200);
        $ahora = date('Y-m-d H:i:s');
        $this->db->table('recompensas')->insert(['titulo' => 'Estampa', 'icono' => 'regalo', 'costo' => 6, 'activa' => 1, 'created_at' => $ahora, 'updated_at' => $ahora]);
        $recompensa = (int) $this->db->insertID();
        $this->entrarPerfil('Luna');
        $antes = $this->cuerpo($this->api('get', 'yo/progreso'));

        // Desde el S4 el canje sigue el flujo real: la hija lo pide (E-15) y el padre lo aprueba (E-41).
        $canje = $this->cuerpo($this->conEstado($this->api('post', 'yo/canjes', ['recompensa_id' => $recompensa]), 201));
        $this->entrarModoPadre();
        $this->conEstado($this->api('post', "admin/canjes/{$canje['id']}/revision", ['decision' => 'aprobado']), 200);
        $this->entrarPerfil('Luna');
        $despues = $this->cuerpo($this->api('get', 'yo/progreso'));

        // 10 de la tarea + 20 de "Primera misión".
        $this->assertSame([30, 30], [$antes['xp'], $antes['saldo']]);
        $this->assertSame(30, $despues['xp']);
        $this->assertSame(24, $despues['saldo']);
        $this->assertSame(24, $despues['disponible']);
    }

    /**
     * CP-052 · `puntos` en el cuerpo de `marcarHecha` se ignora: los define la tarea.
     */
    public function testPuntosEnElCuerpoDeMarcarHechaSeIgnoran(): void
    {
        $tarea = $this->crearTarea('Luna', ['puntos' => 6]);
        $id    = (int) $this->instancia($tarea['id'], $this->hoy())['id'];
        $this->entrarPerfil('Luna');

        $instancia = $this->cuerpo($this->conEstado($this->api('post', "yo/instancias/{$id}/hecha", ['puntos' => 999, 'estado' => 'aprobada']), 200));

        $this->assertSame(6, $instancia['puntos']);
        $this->assertSame('hecha', $instancia['estado']);
        $this->assertSame('6', (string) $this->fila('tarea_instancias', $id)['puntos']);
        $this->assertSame(6, $this->cuerpo($this->revisar($id, ['decision' => 'aprobada']))['puntos_acreditados']);
        $this->assertSame('6', (string) $this->db->table('movimientos_puntos')->get()->getRowArray()['puntos']);
    }

    /**
     * CP-038 · Escolar propia hecha: aparece en `GET /admin/tareas` con `creada_por = perfil` y en la
     * bandeja; al aprobar se acreditan 5 puntos una sola vez.
     */
    public function testEscolarPropiaHechaYAprobada(): void
    {
        $this->entrarPerfil('Sol');
        $tarea = $this->cuerpo($this->conEstado($this->api('post', 'yo/tareas', [
            'titulo' => 'Página 45', 'icono' => 'libro', 'tipo' => 'escolar', 'materia' => 'Español',
            'fecha_entrega' => $this->hoy(2), 'puntos' => 80,
            'recurrencia' => ['frecuencia' => 'una_vez', 'dias_semana' => null, 'hora' => null, 'fecha_inicio' => $this->hoy(), 'fecha_fin' => null],
        ]), 201));
        $id = (int) $this->instancia($tarea['id'], $this->hoy())['id'];
        $this->api('post', "yo/instancias/{$id}/hecha")->assertStatus(200);

        $admin = $this->cuerpo($this->api('get', 'admin/tareas?perfil_id=' . $this->perfilId('Sol')));
        $this->assertSame('perfil', array_values(array_filter($admin, static fn (array $t): bool => $t['id'] === $tarea['id']))[0]['creada_por']);

        $bandeja = $this->cuerpo($this->api('get', 'admin/aprobaciones'));
        $this->assertSame([$id], array_column($bandeja, 'id'));
        $this->assertSame('Sol', $bandeja[0]['perfil']['apodo']);

        $this->assertSame(5, $this->cuerpo($this->conEstado($this->revisar($id, ['decision' => 'aprobada']), 200))['puntos_acreditados']);
        $this->assertError($this->revisar($id, ['decision' => 'aprobada']), 409, 'ESTADO_INVALIDO');
        $this->assertSame(1, $this->movimientos($id));
        $this->assertSame('5', (string) $this->db->table('movimientos_puntos')->where('origen_id', $id)->get()->getRowArray()['puntos']);
    }

    public function testSinModoPadreNoHayBandeja(): void
    {
        $this->api('delete', 'modo-padre')->assertStatus(200);

        $this->assertError($this->api('get', 'admin/aprobaciones'), 403, 'MODO_PADRE_REQUERIDO');
        $this->assertError($this->api('post', 'admin/instancias/revision-lote', ['ids' => [1]]), 403, 'MODO_PADRE_REQUERIDO');
    }
}
