<?php

declare(strict_types=1);

namespace Tests\Feature\Acceso;

use CodeIgniter\I18n\Time;
use Tests\Support\ApiTestCase;

/**
 * T-S1-02 · Selector de perfil con PIN (RF-01): E-06, E-07, E-08.
 * CP-003, CP-004, CP-005, CP-006.
 *
 * @internal
 */
final class PerfilesTest extends ApiTestCase
{
    /**
     * CP-003 · `GET /perfiles` sin sesión: 401 `SIN_SESION`.
     */
    public function testPerfilesSinSesion(): void
    {
        $this->assertError($this->api('get', 'perfiles'), 401, 'SIN_SESION');
    }

    public function testPerfilesDevuelveSoloActivosOrdenadosComoPerfilPublico(): void
    {
        $this->db->table('perfiles')->where('apodo', 'Sol')->update(['activo' => 0]);
        $this->db->table('perfiles')->where('apodo', 'Luna')->update(['orden' => 9]);
        $this->iniciarSesionDemo();

        $perfiles = $this->cuerpo($this->conEstado($this->api('get', 'perfiles'), 200));

        $this->assertSame(['Estrella', 'Luna'], array_column($perfiles, 'apodo'));
        $this->assertSame([
            'id'              => $this->perfilId('Estrella'),
            'apodo'           => 'Estrella',
            'avatar'          => 'gato',
            'color'           => 'menta',
            'nivel_ux'        => 'pro',
            'sonidos'         => true,
            'tema'            => 'claro',
            'bloqueado_hasta' => null,
        ], $perfiles[0]);
    }

    /**
     * CP-004 · PIN correcto: 200, `perfil_activo` lleno e intentos en 0.
     */
    public function testEntrarConPinCorrecto(): void
    {
        $id = $this->perfilId('Luna');
        $this->db->table('perfiles')->where('id', $id)->update(['pin_intentos' => 3]);
        $this->iniciarSesionDemo();

        $sesion = $this->cuerpo($this->conEstado($this->api('post', "perfiles/{$id}/entrar", ['pin' => '3917']), 200));

        $this->assertSame($id, $sesion['perfil_activo']['id']);
        $this->assertSame('Luna', $sesion['perfil_activo']['apodo']);
        $this->assertSame('Papá Demo', $sesion['usuario']['nombre']);
        $this->assertSame(0, (int) $this->fila('perfiles', $id)['pin_intentos']);
        $this->assertSame($id, $this->sesion['perfil_activo_id']);

        $this->assertSame($id, $this->cuerpo($this->api('get', 'sesion'))['perfil_activo']['id']);
    }

    public function testPinIncorrectoDaIntentosRestantes(): void
    {
        $id = $this->perfilId('Luna');
        $this->iniciarSesionDemo();

        $resultado = $this->api('post', "perfiles/{$id}/entrar", ['pin' => '0000']);

        $this->assertError($resultado, 401, 'PIN_INCORRECTO');
        $this->assertSame(4, $this->cuerpo($resultado)['error']['intentos_restantes']);
        $this->assertSame(1, (int) $this->fila('perfiles', $id)['pin_intentos']);
        $this->assertArrayNotHasKey('perfil_activo_id', $this->sesion);
    }

    /**
     * CP-005 · 5 PIN incorrectos seguidos: el quinto responde 423 `BLOQUEADO` con `bloqueado_hasta` a 15 min.
     */
    public function testCincoPinIncorrectosBloquean15Minutos(): void
    {
        Time::setTestNow('2026-12-02 16:00:00');
        $id = $this->perfilId('Sol');
        $this->iniciarSesionDemo();

        for ($i = 1; $i <= 4; $i++) {
            $resultado = $this->api('post', "perfiles/{$id}/entrar", ['pin' => '1111']);
            $this->assertError($resultado, 401, 'PIN_INCORRECTO');
            $this->assertSame(5 - $i, $this->cuerpo($resultado)['error']['intentos_restantes']);
        }

        $resultado = $this->api('post', "perfiles/{$id}/entrar", ['pin' => '1111']);

        $this->assertError($resultado, 423, 'BLOQUEADO');
        $this->assertSame('2026-12-02T16:15:00-07:00', $this->cuerpo($resultado)['error']['bloqueado_hasta']);
        $this->assertSame('2026-12-02 16:15:00', $this->fila('perfiles', $id)['bloqueado_hasta']);

        $perfiles = $this->cuerpo($this->api('get', 'perfiles'));
        $sol      = array_values(array_filter($perfiles, static fn (array $p): bool => $p['apodo'] === 'Sol'))[0];
        $this->assertSame('2026-12-02T16:15:00-07:00', $sol['bloqueado_hasta']);
    }

    /**
     * CP-006 · PIN correcto durante el bloqueo: 423 `BLOQUEADO`; al vencer, entra.
     */
    public function testPinCorrectoDuranteElBloqueo(): void
    {
        Time::setTestNow('2026-12-02 16:00:00');
        $id = $this->perfilId('Sol');
        $this->db->table('perfiles')->where('id', $id)->update(['bloqueado_hasta' => '2026-12-02 16:15:00']);
        $this->iniciarSesionDemo();

        $resultado = $this->api('post', "perfiles/{$id}/entrar", ['pin' => '5203']);

        $this->assertError($resultado, 423, 'BLOQUEADO');
        $this->assertSame('2026-12-02T16:15:00-07:00', $this->cuerpo($resultado)['error']['bloqueado_hasta']);
        $this->assertArrayNotHasKey('perfil_activo_id', $this->sesion);

        Time::setTestNow('2026-12-02 16:15:01');
        $this->api('post', "perfiles/{$id}/entrar", ['pin' => '5203'])->assertStatus(200);
        $this->assertNull($this->fila('perfiles', $id)['bloqueado_hasta']);
    }

    public function testEntrarAPerfilInactivoOInexistenteEsNoEncontrado(): void
    {
        $id = $this->perfilId('Luna');
        $this->db->table('perfiles')->where('id', $id)->update(['activo' => 0]);
        $this->iniciarSesionDemo();

        $this->assertError($this->api('post', "perfiles/{$id}/entrar", ['pin' => '3917']), 404, 'NO_ENCONTRADO');
        $this->assertError($this->api('post', 'perfiles/9999/entrar', ['pin' => '3917']), 404, 'NO_ENCONTRADO');
    }

    public function testPinConFormatoInvalidoEsValidacionYNoCuentaIntento(): void
    {
        $id = $this->perfilId('Luna');
        $this->iniciarSesionDemo();

        $resultado = $this->api('post', "perfiles/{$id}/entrar", ['pin' => '12a']);

        $this->assertError($resultado, 400, 'VALIDACION');
        $this->assertArrayHasKey('pin', $this->cuerpo($resultado)['error']['campos']);
        $this->assertSame(0, (int) $this->fila('perfiles', $id)['pin_intentos']);
    }

    public function testSalirDelPerfil(): void
    {
        $id = $this->perfilId('Luna');
        $this->iniciarSesionDemo();
        $this->api('post', "perfiles/{$id}/entrar", ['pin' => '3917'])->assertStatus(200);

        $sesion = $this->cuerpo($this->conEstado($this->api('post', 'perfiles/salir'), 200));

        $this->assertNull($sesion['perfil_activo']);
        $this->assertSame('Papá Demo', $sesion['usuario']['nombre']);
    }

    public function testPerfilActivoDesactivadoDesapareceDeLaSesion(): void
    {
        $id = $this->perfilId('Luna');
        $this->iniciarSesionDemo();
        $this->api('post', "perfiles/{$id}/entrar", ['pin' => '3917'])->assertStatus(200);
        $this->db->table('perfiles')->where('id', $id)->update(['activo' => 0]);

        $this->assertNull($this->cuerpo($this->api('get', 'sesion'))['perfil_activo']);
    }
}
