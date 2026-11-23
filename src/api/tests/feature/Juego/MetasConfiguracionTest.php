<?php

declare(strict_types=1);

namespace Tests\Feature\Juego;

use App\Database\Seeds\DemoSeeder;
use App\Libraries\Hash;
use App\Services\MetasService;
use Tests\Support\ApiTestCase;

/**
 * T-S4-03 · Meta familiar y configuración (RF-12, RF-18): E-22, E-42 a E-44, E-47, E-48. CP-071, CP-072.
 *
 * @internal
 */
final class MetasConfiguracionTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->sinTareasDemo();
        $this->iniciarSesionDemo();
        $this->entrarModoPadre();
    }

    /**
     * @return array<string, mixed>
     */
    private function meta(array $cambios = []): array
    {
        return array_merge(['titulo' => 'Paseo al lago', 'premio' => 'Día de campo', 'objetivo_puntos' => 30, 'fecha_inicio' => $this->hoy(), 'fecha_limite' => $this->hoy(14)], $cambios);
    }

    private function metaDemoId(): int
    {
        return (int) $this->db->table('metas_familiares')->get()->getRowArray()['id'];
    }

    public function testListaMetasConProgreso(): void
    {
        $metas = $this->cuerpo($this->conEstado($this->api('get', 'admin/metas'), 200));

        $this->assertCount(1, $metas);
        $this->assertSame(['id', 'titulo', 'premio', 'objetivo_puntos', 'progreso_puntos', 'fecha_inicio', 'fecha_limite', 'estado'], array_keys($metas[0]));
        $this->assertSame('activa', $metas[0]['estado']);
    }

    /**
     * CP-071 · Crear una segunda meta activa: 409 `ESTADO_INVALIDO`.
     */
    public function testSegundaMetaActivaEs409(): void
    {
        $this->assertError($this->api('post', 'admin/metas', $this->meta()), 409, 'ESTADO_INVALIDO');
        $this->assertSame(1, $this->db->table('metas_familiares')->countAllResults());
    }

    public function testCrearEditarYCancelar(): void
    {
        $this->api('put', 'admin/metas/' . $this->metaDemoId(), $this->meta(['estado' => 'cancelada']))->assertStatus(200);

        $r = $this->api('post', 'admin/metas', $this->meta(['fecha_limite' => null]));
        $r->assertStatus(201);
        $meta = $this->cuerpo($r);
        $this->assertSame(['activa', 30, 0, null], [$meta['estado'], $meta['objetivo_puntos'], $meta['progreso_puntos'], $meta['fecha_limite']]);

        $editada = $this->cuerpo($this->conEstado($this->api('put', "admin/metas/{$meta['id']}", $this->meta(['titulo' => 'Paseo largo', 'objetivo_puntos' => 40])), 200));
        $this->assertSame(['Paseo largo', 40, 'activa'], [$editada['titulo'], $editada['objetivo_puntos'], $editada['estado']]);

        $cancelada = $this->cuerpo($this->conEstado($this->api('put', "admin/metas/{$meta['id']}", $this->meta(['estado' => 'cancelada'])), 200));
        $this->assertSame('cancelada', $cancelada['estado']);

        $this->assertError($this->api('put', "admin/metas/{$meta['id']}", $this->meta(['estado' => 'activa'])), 409, 'ESTADO_INVALIDO');
        $this->assertError($this->api('put', "admin/metas/{$meta['id']}", $this->meta(['titulo' => 'Otra'])), 409, 'ESTADO_INVALIDO');
        $this->assertError($this->api('put', 'admin/metas/99999', $this->meta()), 404, 'NO_ENCONTRADO');
    }

    public function testValidacionDeMeta(): void
    {
        $this->api('put', 'admin/metas/' . $this->metaDemoId(), $this->meta(['estado' => 'cancelada']))->assertStatus(200);

        $r = $this->api('post', 'admin/metas', ['titulo' => '', 'premio' => str_repeat('p', 81), 'objetivo_puntos' => 0, 'fecha_inicio' => 'hoy', 'fecha_limite' => '2026-01-01']);
        $this->assertError($r, 400, 'VALIDACION');
        $this->assertEqualsCanonicalizing(['titulo', 'premio', 'objetivo_puntos', 'fecha_inicio'], array_keys($this->cuerpo($r)['error']['campos']));

        $r = $this->api('post', 'admin/metas', $this->meta(['fecha_limite' => $this->hoy(-1)]));
        $this->assertSame(['fecha_limite'], array_keys($this->cuerpo($r)['error']['campos']));

        $r = $this->api('put', 'admin/metas/' . $this->metaDemoId(), $this->meta(['estado' => 'perdida']));
        $this->assertArrayHasKey('estado', $this->cuerpo($r)['error']['campos']);
    }

    /**
     * CP-072 · Aprobaciones de dos hijas llegan al objetivo: meta `cumplida` y `meta_cumplida = true`.
     */
    public function testAprobacionesDeDosHijasCumplenLaMeta(): void
    {
        $this->api('put', 'admin/metas/' . $this->metaDemoId(), $this->meta(['estado' => 'cancelada']))->assertStatus(200);
        $meta = $this->cuerpo($this->conEstado($this->api('post', 'admin/metas', $this->meta(['objetivo_puntos' => 30])), 201));
        [$luna] = $this->insertarInstancias('Luna', [$this->hoy()], 'hecha', 20);
        [$sol]  = $this->insertarInstancias('Sol', [$this->hoy()], 'hecha', 10);

        $primera = $this->cuerpo($this->api('post', "admin/instancias/{$luna}/revision", ['decision' => 'aprobada']));
        $this->assertFalse($primera['meta_cumplida']);

        $segunda = $this->cuerpo($this->conEstado($this->api('post', "admin/instancias/{$sol}/revision", ['decision' => 'aprobada']), 200));
        $this->assertTrue($segunda['meta_cumplida']);

        $fila = $this->fila('metas_familiares', $meta['id']);
        $this->assertSame('cumplida', $fila['estado']);
        $this->assertNotNull($fila['cumplida_en']);
        $listada = array_values(array_filter($this->cuerpo($this->api('get', 'admin/metas')), static fn (array $m): bool => $m['id'] === $meta['id']))[0];
        $this->assertSame(30, $listada['progreso_puntos']);

        // Ya sin meta activa, otra aprobación no la vuelve a cumplir.
        [$otra] = $this->insertarInstancias('Sol', [$this->hoy()], 'hecha', 5);
        $this->assertFalse($this->cuerpo($this->api('post', "admin/instancias/{$otra}/revision", ['decision' => 'aprobada']))['meta_cumplida']);
    }

    public function testVencerMeta(): void
    {
        $this->db->table('metas_familiares')->update(['fecha_limite' => $this->hoy(-1)]);

        $this->assertSame(1, (new MetasService(db_connect()))->vencer($this->hoy()));
        $this->assertSame('cancelada', $this->fila('metas_familiares', $this->metaDemoId())['estado']);
        $this->assertSame(0, (new MetasService(db_connect()))->vencer($this->hoy()));
    }

    public function testElCronVenceConForzar(): void
    {
        $this->db->table('metas_familiares')->update(['fecha_limite' => $this->hoy(-2)]);

        command('tareas:programadas --forzar');

        $this->assertSame('cancelada', $this->fila('metas_familiares', $this->metaDemoId())['estado']);
    }

    public function testConfiguracion(): void
    {
        $this->assertSame([
            'racha_umbral' => 80, 'sonidos_default' => true, 'dias_generacion' => 7,
            'evidencia_dias_retencion' => 30, 'puntos_tarea_propia' => 5,
        ], $this->cuerpo($this->conEstado($this->api('get', 'admin/configuracion'), 200)));

        $guardada = $this->cuerpo($this->conEstado($this->api('put', 'admin/configuracion', [
            'racha_umbral' => 70, 'sonidos_default' => false, 'dias_generacion' => 30, 'puntos_tarea_propia' => 99,
        ]), 200));

        $this->assertSame(['racha_umbral' => 70, 'sonidos_default' => false, 'dias_generacion' => 7, 'evidencia_dias_retencion' => 30, 'puntos_tarea_propia' => 5], $guardada, 'Lo demás es de solo lectura.');

        $r = $this->api('put', 'admin/configuracion', ['racha_umbral' => 101, 'sonidos_default' => 'no', 'pin_padre' => '1234']);
        $this->assertError($r, 400, 'VALIDACION');
        $this->assertEqualsCanonicalizing(['racha_umbral', 'sonidos_default', 'pin_padre'], array_keys($this->cuerpo($r)['error']['campos']));
        $this->assertSame(70, $this->cuerpo($this->api('get', 'admin/configuracion'))['racha_umbral'], 'Un error no guarda nada.');
    }

    public function testPinPadreCambiaElPinDelUsuarioDeLaSesion(): void
    {
        $this->api('put', 'admin/configuracion', ['pin_padre' => '6159'])->assertStatus(200);

        $usuario = $this->db->table('usuarios')->where('email', DemoSeeder::EMAIL)->get()->getRowArray();
        $this->assertTrue(Hash::verificar('6159', $usuario['pin_hash']));

        $this->api('delete', 'modo-padre')->assertStatus(200);
        $this->assertError($this->api('post', 'modo-padre', ['pin' => DemoSeeder::PIN]), 401, 'PIN_INCORRECTO');
        $this->api('post', 'modo-padre', ['pin' => '6159'])->assertStatus(200);
    }

    public function testPreferenciasDelPerfilActivo(): void
    {
        $this->entrarPerfil('Luna');

        $perfil = $this->cuerpo($this->conEstado($this->api('patch', 'yo/preferencias', ['sonidos' => false, 'tema' => 'oscuro', 'apodo' => 'Hack']), 200));

        $this->assertSame(['id', 'apodo', 'avatar', 'color', 'nivel_ux', 'sonidos', 'tema', 'bloqueado_hasta'], array_keys($perfil));
        $this->assertSame([false, 'oscuro', 'Luna'], [$perfil['sonidos'], $perfil['tema'], $perfil['apodo']]);
        $this->assertSame('sistema', $this->cuerpo($this->api('patch', 'yo/preferencias', ['tema' => 'sistema']))['tema']);
        $this->assertFalse($this->cuerpo($this->api('patch', 'yo/preferencias', []))['sonidos'], 'Sin campos no cambia nada.');

        $r = $this->api('patch', 'yo/preferencias', ['sonidos' => 'si', 'tema' => 'neon']);
        $this->assertError($r, 400, 'VALIDACION');
        $this->assertEqualsCanonicalizing(['sonidos', 'tema'], array_keys($this->cuerpo($r)['error']['campos']));
    }
}
