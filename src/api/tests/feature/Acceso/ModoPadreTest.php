<?php

declare(strict_types=1);

namespace Tests\Feature\Acceso;

use App\Database\Seeds\DemoSeeder;
use App\Libraries\Hash;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\Mock\MockSession;
use Tests\Support\ApiTestCase;

/**
 * T-S1-03 · Modo padre (RF-02): E-09, E-10 y el grupo `admin/*`.
 * CP-007, CP-SEG-02.
 *
 * @internal
 */
final class ModoPadreTest extends ApiTestCase
{
    /**
     * CP-007 · PIN del padre: 200 y `modo_padre_hasta` a 10 min.
     */
    public function testEntrarConPinDelPadre(): void
    {
        Time::setTestNow('2026-12-02 18:30:00');
        $this->iniciarSesionDemo();

        $sesion = $this->cuerpo($this->conEstado($this->api('post', 'modo-padre', ['pin' => DemoSeeder::PIN]), 200));

        $this->assertSame('2026-12-02T18:40:00-07:00', $sesion['modo_padre_hasta']);
        $this->assertSame('Papá Demo', $sesion['usuario']['nombre']);

        $mock = service('session');
        $this->assertInstanceOf(MockSession::class, $mock);
        $this->assertTrue($mock->didRegenerate, 'Se regenera el id de sesión al entrar a modo padre.');
    }

    public function testElPinEsElDelUsuarioDeLaSesion(): void
    {
        // Un segundo padre con otro PIN: su PIN no abre el modo padre de esta sesión.
        $ahora = Time::now()->toDateTimeString();
        $this->db->table('usuarios')->insert([
            'nombre' => 'Mamá Demo', 'email' => 'mama@tareas.local',
            'password_hash' => Hash::crear('OtraContrasena1'), 'pin_hash' => Hash::crear('7351'),
            'created_at' => $ahora, 'updated_at' => $ahora,
        ]);
        $this->iniciarSesionDemo();

        $this->assertError($this->api('post', 'modo-padre', ['pin' => '7351']), 401, 'PIN_INCORRECTO');
        $this->api('post', 'modo-padre', ['pin' => DemoSeeder::PIN])->assertStatus(200);
    }

    public function testPinIncorrectoYBloqueoUsanLosCamposDelUsuario(): void
    {
        Time::setTestNow('2026-12-02 18:30:00');
        $this->iniciarSesionDemo();

        for ($i = 1; $i <= 4; $i++) {
            $resultado = $this->api('post', 'modo-padre', ['pin' => '9090']);
            $this->assertError($resultado, 401, 'PIN_INCORRECTO');
            $this->assertSame(5 - $i, $this->cuerpo($resultado)['error']['intentos_restantes']);
        }

        $resultado = $this->api('post', 'modo-padre', ['pin' => '9090']);
        $this->assertError($resultado, 423, 'BLOQUEADO');
        $this->assertSame('2026-12-02T18:45:00-07:00', $this->cuerpo($resultado)['error']['bloqueado_hasta']);

        $usuario = $this->db->table('usuarios')->where('email', DemoSeeder::EMAIL)->get()->getRowArray();
        $this->assertSame('2026-12-02 18:45:00', $usuario['bloqueado_hasta']);

        $this->assertError($this->api('post', 'modo-padre', ['pin' => DemoSeeder::PIN]), 423, 'BLOQUEADO');
        $this->assertArrayNotHasKey('modo_padre_hasta', $this->sesion);
    }

    public function testSalirDelModoPadre(): void
    {
        $this->iniciarSesionDemo();
        $this->entrarModoPadre();

        $sesion = $this->cuerpo($this->conEstado($this->api('delete', 'modo-padre'), 200));

        $this->assertNull($sesion['modo_padre_hasta']);
        $this->assertError($this->api('get', 'admin/perfiles'), 403, 'MODO_PADRE_REQUERIDO');
    }

    /**
     * CP-SEG-02 · Hija con perfil activo llama `GET /admin/aprobaciones`: 403 `MODO_PADRE_REQUERIDO`.
     */
    public function testHijaConPerfilActivoNoEntraAAdmin(): void
    {
        $this->iniciarSesionDemo();
        $id = $this->perfilId('Luna');
        $this->api('post', "perfiles/{$id}/entrar", ['pin' => '3917'])->assertStatus(200);

        $this->assertError($this->api('get', 'admin/aprobaciones'), 403, 'MODO_PADRE_REQUERIDO');
        $this->assertError($this->api('get', 'admin/perfiles'), 403, 'MODO_PADRE_REQUERIDO');
        $this->assertError($this->api('post', 'admin/perfiles', ['apodo' => 'X']), 403, 'MODO_PADRE_REQUERIDO');
    }

    public function testAdminSinSesionEs401(): void
    {
        $this->assertError($this->api('get', 'admin/aprobaciones'), 401, 'SIN_SESION');
    }

    public function testRutaAdminInexistenteConModoPadreEs404(): void
    {
        $this->iniciarSesionDemo();
        $this->entrarModoPadre();

        $this->assertError($this->api('get', 'admin/no-existe/1'), 404, 'NO_ENCONTRADO');
    }

    public function testCadaPeticionAdminRenuevaDiezMinutosYSesionLoRefleja(): void
    {
        Time::setTestNow('2026-12-02 18:30:00');
        $this->iniciarSesionDemo();
        $this->entrarModoPadre();

        Time::setTestNow('2026-12-02 18:38:00');
        $this->api('get', 'admin/perfiles')->assertStatus(200);
        $this->assertSame('2026-12-02T18:48:00-07:00', $this->cuerpo($this->api('get', 'sesion'))['modo_padre_hasta']);

        // GET /sesion no renueva.
        Time::setTestNow('2026-12-02 18:45:00');
        $this->assertSame('2026-12-02T18:48:00-07:00', $this->cuerpo($this->api('get', 'sesion'))['modo_padre_hasta']);

        // Pasados 10 min sin actividad admin, el modo padre se cierra solo.
        Time::setTestNow('2026-12-02 18:48:01');
        $this->assertNull($this->cuerpo($this->api('get', 'sesion'))['modo_padre_hasta']);
        $this->assertError($this->api('get', 'admin/perfiles'), 403, 'MODO_PADRE_REQUERIDO');
    }
}
