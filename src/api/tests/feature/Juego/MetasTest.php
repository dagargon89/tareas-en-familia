<?php

declare(strict_types=1);

namespace Tests\Feature\Juego;

use CodeIgniter\I18n\Time;
use CodeIgniter\Test\TestResponse;
use Tests\Support\ApiTestCase;

/**
 * T-S4-03 · Meta familiar (RF-12): E-42 a E-44, CP-071 y CP-072.
 *
 * @internal
 */
final class MetasTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->sinTareasDemo();
        $this->iniciarSesionDemo();
        $this->entrarModoPadre();
    }

    /**
     * @param array<string, mixed> $cambios
     *
     * @return array<string, mixed>
     */
    private function metaEntrada(array $cambios = []): array
    {
        return array_merge([
            'titulo' => 'Paseo al zoológico', 'premio' => 'Ir al zoológico', 'objetivo_puntos' => 30,
            'fecha_inicio' => $this->hoy(), 'fecha_limite' => $this->hoy(30),
        ], $cambios);
    }

    private function metaDemoId(): int
    {
        return (int) $this->db->table('metas_familiares')->get()->getRowArray()['id'];
    }

    private function cancelarDemo(): void
    {
        $this->db->table('metas_familiares')->update(['estado' => 'cancelada']);
    }

    private function aprobar(string $apodo, int $puntos): TestResponse
    {
        $tarea = $this->crearTarea($apodo, ['puntos' => $puntos, 'titulo' => "Meta {$apodo} {$puntos}"]);
        $id    = (int) $this->instancia($tarea['id'], $this->hoy())['id'];
        $this->db->table('tarea_instancias')->where('id', $id)->update(['estado' => 'hecha', 'completada_en' => date('Y-m-d H:i:s')]);

        return $this->api('post', "admin/instancias/{$id}/revision", ['decision' => 'aprobada']);
    }

    public function testListaDeMetasConProgreso(): void
    {
        $metas = $this->cuerpo($this->conEstado($this->api('get', 'admin/metas'), 200));

        $this->assertCount(1, $metas);
        $this->assertSame(['id', 'titulo', 'premio', 'objetivo_puntos', 'progreso_puntos', 'fecha_inicio', 'fecha_limite', 'estado'], array_keys($metas[0]));
        $this->assertSame([500, 0, 'activa'], [$metas[0]['objetivo_puntos'], $metas[0]['progreso_puntos'], $metas[0]['estado']]);
    }

    /**
     * CP-071 · Crear una segunda meta activa: 409 `ESTADO_INVALIDO`.
     */
    public function testSegundaMetaActivaEs409(): void
    {
        $this->assertError($this->api('post', 'admin/metas', $this->metaEntrada()), 409, 'ESTADO_INVALIDO');
        $this->assertSame(1, $this->db->table('metas_familiares')->countAllResults());
    }

    public function testCrearMetaCuandoNoHayActiva(): void
    {
        $this->cancelarDemo();

        $meta = $this->cuerpo($this->conEstado($this->api('post', 'admin/metas', $this->metaEntrada(['titulo' => '  Paseo  ', 'fecha_limite' => null])), 201));

        $this->assertSame(['Paseo', 'activa', 0, null], [$meta['titulo'], $meta['estado'], $meta['progreso_puntos'], $meta['fecha_limite']]);
        $this->assertSame([$meta['id'], $this->metaDemoId()], array_column($this->cuerpo($this->api('get', 'admin/metas')), 'id'), 'La más reciente primero');
    }

    public function testValidacionDeMeta(): void
    {
        $this->cancelarDemo();

        $r = $this->api('post', 'admin/metas', [
            'titulo' => '', 'premio' => str_repeat('p', 81), 'objetivo_puntos' => 0, 'fecha_inicio' => '2026-02-30', 'fecha_limite' => 'mañana',
        ]);

        $this->assertError($r, 400, 'VALIDACION');
        $this->assertSame(['titulo', 'premio', 'objetivo_puntos', 'fecha_inicio', 'fecha_limite'], array_keys($this->cuerpo($r)['error']['campos']));
        $this->assertError($this->api('post', 'admin/metas', $this->metaEntrada(['fecha_limite' => $this->hoy(-1)])), 400, 'VALIDACION');
    }

    /**
     * CP-072 · Aprobaciones de dos hijas llegan al objetivo: meta `cumplida` y `meta_cumplida = true`.
     */
    public function testDosHijasLleganAlObjetivo(): void
    {
        $this->conEstado($this->api('put', 'admin/metas/' . $this->metaDemoId(), $this->metaEntrada(['objetivo_puntos' => 30, 'fecha_inicio' => $this->hoy(-7)])), 200);

        $primera = $this->cuerpo($this->conEstado($this->aprobar('Luna', 20), 200));
        $this->assertFalse($primera['meta_cumplida']);
        $this->assertSame('activa', $this->fila('metas_familiares', $this->metaDemoId())['estado']);

        $segunda = $this->cuerpo($this->conEstado($this->aprobar('Sol', 10), 200));
        $this->assertTrue($segunda['meta_cumplida']);

        $meta = $this->fila('metas_familiares', $this->metaDemoId());
        $this->assertSame('cumplida', $meta['estado']);
        $this->assertNotNull($meta['cumplida_en']);

        Time::setTestNow(Time::now()->addMinutes(1)); // la siguiente aprobación ocurre después de cumplirse
        $tercera = $this->cuerpo($this->conEstado($this->aprobar('Estrella', 5), 200));
        $this->assertFalse($tercera['meta_cumplida'], 'Una meta ya cumplida no se vuelve a cumplir');

        $listada = $this->cuerpo($this->api('get', 'admin/metas'))[0];
        $this->assertSame(['cumplida', 30], [$listada['estado'], $listada['progreso_puntos']], 'El progreso de una cumplida se congela al cumplirse');

        $this->entrarPerfil('Luna');
        $this->assertNull($this->cuerpo($this->api('get', 'yo/progreso'))['meta_familiar']);
    }

    public function testLasInsigniasNoSumanALaMeta(): void
    {
        $this->conEstado($this->api('put', 'admin/metas/' . $this->metaDemoId(), $this->metaEntrada(['objetivo_puntos' => 25])), 200);

        $resultado = $this->cuerpo($this->conEstado($this->aprobar('Luna', 10), 200));

        $this->assertFalse($resultado['meta_cumplida'], '10 de la tarea; los 20 de "Primera misión" no cuentan');
        $this->assertSame(10, $this->cuerpo($this->api('get', 'admin/metas'))[0]['progreso_puntos']);
    }

    public function testEditarYCancelarMeta(): void
    {
        $id = $this->metaDemoId();

        $editada = $this->cuerpo($this->conEstado($this->api('put', "admin/metas/{$id}", $this->metaEntrada(['premio' => 'Pizza'])), 200));
        $this->assertSame(['Pizza', 'activa'], [$editada['premio'], $editada['estado']]);

        $cancelada = $this->cuerpo($this->conEstado($this->api('put', "admin/metas/{$id}", $this->metaEntrada() + ['estado' => 'cancelada']), 200));
        $this->assertSame('cancelada', $cancelada['estado']);

        $this->assertError($this->api('put', "admin/metas/{$id}", $this->metaEntrada() + ['estado' => 'activa']), 409, 'ESTADO_INVALIDO');
        $this->assertError($this->api('put', "admin/metas/{$id}", $this->metaEntrada()), 409, 'ESTADO_INVALIDO');
        $this->assertError($this->api('put', 'admin/metas/999999', $this->metaEntrada()), 404, 'NO_ENCONTRADO');

        $this->conEstado($this->api('post', 'admin/metas', $this->metaEntrada()), 201);
        $this->assertError($this->api('put', "admin/metas/{$id}", $this->metaEntrada() + ['estado' => 'cumplida']), 409, 'ESTADO_INVALIDO');
    }

    public function testEstadoDesconocidoEs400(): void
    {
        $this->assertError($this->api('put', 'admin/metas/' . $this->metaDemoId(), $this->metaEntrada() + ['estado' => 'pausada']), 400, 'VALIDACION');
    }

    public function testElCronCancelaLaMetaVencida(): void
    {
        $this->db->table('metas_familiares')->update(['fecha_inicio' => $this->hoy(-10), 'fecha_limite' => $this->hoy(-1)]);

        command('tareas:programadas --forzar');

        $this->assertSame('cancelada', $this->fila('metas_familiares', $this->metaDemoId())['estado']);
    }

    public function testMetaQueVenceHoySigueActiva(): void
    {
        $this->db->table('metas_familiares')->update(['fecha_limite' => $this->hoy()]);

        command('tareas:programadas --forzar');

        $this->assertSame('activa', $this->fila('metas_familiares', $this->metaDemoId())['estado']);
    }
}
