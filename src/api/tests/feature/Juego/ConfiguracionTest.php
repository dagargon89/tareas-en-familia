<?php

declare(strict_types=1);

namespace Tests\Feature\Juego;

use App\Domain\SemanaIso;
use App\Services\RachasService;
use Tests\Support\ApiTestCase;

/**
 * T-S4-03 · Configuración familiar (RF-18): E-47, E-48 y CP-086; preferencias de la hija (E-22).
 *
 * @internal
 */
final class ConfiguracionTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->sinTareasDemo();
        $this->iniciarSesionDemo();
    }

    /**
     * @param array<string, mixed> $cuerpo
     *
     * @return array<string, mixed>
     */
    private function guardar(array $cuerpo, int $estado = 200): array
    {
        return $this->cuerpo($this->conEstado($this->api('put', 'admin/configuracion', $cuerpo), $estado));
    }

    // ===== E-47 y E-48 =====

    public function testConfiguracionInicial(): void
    {
        $this->entrarModoPadre();

        $this->assertSame([
            'racha_umbral' => 80, 'sonidos_default' => true, 'dias_generacion' => 7,
            'evidencia_dias_retencion' => 30, 'puntos_tarea_propia' => 5,
        ], $this->cuerpo($this->conEstado($this->api('get', 'admin/configuracion'), 200)));
    }

    public function testRequiereModoPadre(): void
    {
        $this->assertError($this->api('get', 'admin/configuracion'), 403, 'MODO_PADRE_REQUERIDO');
        $this->assertError($this->api('put', 'admin/configuracion', ['racha_umbral' => 60]), 403, 'MODO_PADRE_REQUERIDO');
    }

    /**
     * CP-086 · Con `racha_umbral` en 60, el siguiente cierre semanal usa 60 (7 de 10 cuenta).
     */
    public function testElSiguienteCierreUsaElUmbralNuevo(): void
    {
        $this->entrarModoPadre();
        $tarea  = $this->crearTarea('Sol');
        $semana = SemanaIso::desdeFecha($this->hoy())->anterior();
        $otra   = $this->crearTarea('Sol', ['titulo' => 'Otra']);
        $ahora  = date('Y-m-d H:i:s');

        foreach (range(0, 9) as $i) {
            $this->db->table('tarea_instancias')->insert([
                'tarea_id' => $i < 7 ? $tarea['id'] : $otra['id'], 'perfil_id' => $this->perfilId('Sol'),
                'fecha' => $semana->dias()[$i % 7], 'puntos' => 5, 'estado' => $i < 7 ? 'aprobada' : 'pendiente',
                'created_at' => $ahora, 'updated_at' => $ahora,
            ]);
        }

        $guardada = $this->guardar(['racha_umbral' => 60]);
        $this->assertSame(60, $guardada['racha_umbral']);

        (new RachasService(db_connect()))->cerrarSemana($semana);

        $fila = $this->db->table('rachas')->where('perfil_id', $this->perfilId('Sol'))->get()->getRowArray();
        $this->assertSame(['70.00', 1], [$fila['porcentaje'], (int) $fila['cumplida']], 'Con el umbral de 80 no habría contado');
    }

    public function testGuardarParcialYSonidosPorOmision(): void
    {
        $this->entrarModoPadre();

        $this->guardar(['sonidos_default' => false]);
        $config = $this->guardar(['racha_umbral' => 100]);

        $this->assertSame([100, false], [$config['racha_umbral'], $config['sonidos_default']]);

        $nuevo = $this->cuerpo($this->conEstado($this->api('post', 'admin/perfiles', [
            'apodo' => 'Nube', 'avatar' => 'nube', 'color' => 'lavanda', 'nivel_ux' => 'pro', 'pin' => '7351',
        ]), 201));
        $this->assertFalse($nuevo['sonidos'], 'El perfil nuevo toma `sonidos_default`');
        $this->assertTrue($this->cuerpo($this->api('get', 'admin/perfiles'))[0]['sonidos'], 'Los perfiles existentes no cambian');
    }

    public function testValidacionDeConfiguracion(): void
    {
        $this->entrarModoPadre();

        foreach ([['racha_umbral' => 0], ['racha_umbral' => 101], ['racha_umbral' => '80'], ['sonidos_default' => 'si'], ['pin_padre' => '1234'], ['pin_padre' => 4826]] as $cuerpo) {
            $r = $this->api('put', 'admin/configuracion', $cuerpo);
            $this->assertError($r, 400, 'VALIDACION');
            $this->assertSame(array_keys($cuerpo), array_keys($this->cuerpo($r)['error']['campos']));
        }

        $this->assertSame(80, $this->cuerpo($this->api('get', 'admin/configuracion'))['racha_umbral']);
    }

    public function testCambiarElPinDelPadreDeLaSesion(): void
    {
        $this->entrarModoPadre();

        $config = $this->guardar(['pin_padre' => '7351']);
        $this->assertArrayNotHasKey('pin_padre', $config);

        $this->api('delete', 'modo-padre')->assertStatus(200);
        $this->assertError($this->api('post', 'modo-padre', ['pin' => '4826']), 401, 'PIN_INCORRECTO');
        $this->api('post', 'modo-padre', ['pin' => '7351'])->assertStatus(200);
    }

    // ===== E-22 =====

    public function testPreferenciasDeLaHija(): void
    {
        $this->entrarPerfil('Estrella');

        $perfil = $this->cuerpo($this->conEstado($this->api('patch', 'yo/preferencias', ['sonidos' => false, 'tema' => 'oscuro']), 200));

        $this->assertSame(['id', 'apodo', 'avatar', 'color', 'nivel_ux', 'sonidos', 'tema', 'bloqueado_hasta'], array_keys($perfil));
        $this->assertSame([false, 'oscuro'], [$perfil['sonidos'], $perfil['tema']]);

        $solo = $this->cuerpo($this->conEstado($this->api('patch', 'yo/preferencias', ['sonidos' => true]), 200));
        $this->assertSame([true, 'oscuro'], [$solo['sonidos'], $solo['tema']]);
    }

    public function testExploradorasSinModoOscuro(): void
    {
        $this->entrarPerfil('Luna');

        $this->assertError($this->api('patch', 'yo/preferencias', ['tema' => 'oscuro']), 400, 'VALIDACION');
        $this->assertError($this->api('patch', 'yo/preferencias', ['tema' => 'sistema']), 400, 'VALIDACION');
        $this->assertSame('claro', $this->cuerpo($this->conEstado($this->api('patch', 'yo/preferencias', ['tema' => 'claro']), 200))['tema']);
    }

    public function testValidacionDePreferencias(): void
    {
        $this->entrarPerfil('Sol');

        $r = $this->api('patch', 'yo/preferencias', ['sonidos' => 'no', 'tema' => 'neon']);

        $this->assertError($r, 400, 'VALIDACION');
        $this->assertSame(['sonidos', 'tema'], array_keys($this->cuerpo($r)['error']['campos']));
        $this->assertSame('sistema', $this->cuerpo($this->conEstado($this->api('patch', 'yo/preferencias', ['tema' => 'sistema']), 200))['tema']);
    }
}
