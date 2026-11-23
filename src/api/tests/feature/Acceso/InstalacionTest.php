<?php

declare(strict_types=1);

namespace Tests\Feature\Acceso;

use CodeIgniter\Config\Factories;
use CodeIgniter\Test\Mock\MockSession;
use Config\Instalacion;
use Tests\Support\ApiTestCase;

/**
 * T-S1-05 · Instalación inicial (RF-20): E-49 y E-50. CP-013 a CP-016.
 *
 * @internal
 */
final class InstalacionTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Base recién desplegada: sin usuarios (la demo trae uno).
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');
        $this->db->table('usuarios')->truncate();
        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * Simula producción: token exigido y, opcionalmente, configurado.
     */
    private function produccion(string $token): void
    {
        $config              = new Instalacion();
        $config->token       = $token;
        $config->exigirToken = true;
        Factories::injectMock('config', Instalacion::class, $config);
    }

    /**
     * @return array<string, string>
     */
    private function entrada(array $cambios = []): array
    {
        return array_merge([
            'nombre' => 'Papá', 'email' => 'Papa@Familia.Mx', 'password' => 'UnaContrasenaLarga', 'pin' => '4826',
        ], $cambios);
    }

    private function usuarios(): int
    {
        return $this->db->table('usuarios')->countAllResults();
    }

    /**
     * CP-013 · `GET /instalacion` con 0 usuarios y con 1 usuario.
     */
    public function testEstadoConCeroYConUnUsuario(): void
    {
        $this->assertSame(
            ['requerida' => true, 'requiere_token' => false],
            $this->cuerpo($this->conEstado($this->api('get', 'instalacion'), 200)),
        );

        $this->api('post', 'instalacion', $this->entrada())->assertStatus(201);

        $this->assertFalse($this->cuerpo($this->conEstado($this->api('get', 'instalacion'), 200))['requerida']);
    }

    public function testEnProduccionSiempreRequiereToken(): void
    {
        $this->produccion('');

        $this->assertTrue($this->cuerpo($this->api('get', 'instalacion'))['requiere_token']);
    }

    /**
     * CP-014 · Instalación válida: 201, hash Argon2id/bcrypt, `Sesion.usuario` lleno, sesión abierta.
     */
    public function testInstalacionValidaCreaElUsuarioYAbreLaSesion(): void
    {
        $resultado = $this->api('post', 'instalacion', $this->entrada());

        $resultado->assertStatus(201);
        $usuario = $this->db->table('usuarios')->get()->getRowArray();
        $this->assertSame('papa@familia.mx', $usuario['email']);
        $this->assertMatchesRegularExpression('/^(\$argon2id\$|\$2y\$)/', $usuario['password_hash']);
        $this->assertMatchesRegularExpression('/^(\$argon2id\$|\$2y\$)/', $usuario['pin_hash']);
        $this->assertSame([
            'usuario'          => ['id' => (int) $usuario['id'], 'nombre' => 'Papá'],
            'perfil_activo'    => null,
            'modo_padre_hasta' => null,
        ], $this->cuerpo($resultado));
        $this->assertSame((int) $usuario['id'], $this->sesion['usuario_id']);

        $sesion = service('session');
        $this->assertInstanceOf(MockSession::class, $sesion);
        $this->assertTrue($sesion->didRegenerate);

        // La sesión abierta sirve para el resto de la API.
        $this->api('get', 'sesion')->assertStatus(200);
    }

    public function testInstalacionEnProduccionConTokenCorrecto(): void
    {
        $this->produccion('token-de-instalacion-123');

        $this->api('post', 'instalacion', $this->entrada(['token' => 'token-de-instalacion-123']))->assertStatus(201);
        $this->assertSame(1, $this->usuarios());
    }

    /**
     * CP-015 · Con un usuario existente: 409 `ESTADO_INVALIDO` y no se crea nada.
     */
    public function testConUsuarioExistenteEs409(): void
    {
        $this->api('post', 'instalacion', $this->entrada())->assertStatus(201);
        $this->sesion = [];

        $resultado = $this->api('post', 'instalacion', $this->entrada(['email' => 'otro@familia.mx']));

        $this->assertError($resultado, 409, 'ESTADO_INVALIDO');
        $this->assertSame(1, $this->usuarios());
        $this->assertArrayNotHasKey('usuario_id', $this->sesion);
    }

    /**
     * CP-016 · Producción sin token o con token incorrecto: 403 `PROHIBIDO` y no se crea nada.
     */
    public function testProduccionSinTokenOConTokenIncorrecto(): void
    {
        $this->produccion('token-de-instalacion-123');

        $this->assertError($this->api('post', 'instalacion', $this->entrada()), 403, 'PROHIBIDO');
        $this->assertError($this->api('post', 'instalacion', $this->entrada(['token' => 'otro'])), 403, 'PROHIBIDO');
        $this->assertSame(0, $this->usuarios());
        $this->assertArrayNotHasKey('usuario_id', $this->sesion);
    }

    public function testProduccionConTokenSinConfigurarNoQuedaAbierta(): void
    {
        $this->produccion('');

        $this->assertError($this->api('post', 'instalacion', $this->entrada(['token' => ''])), 403, 'PROHIBIDO');
        $this->assertSame(0, $this->usuarios());
    }

    public function testValidacion(): void
    {
        $resultado = $this->api('post', 'instalacion', ['nombre' => '', 'email' => 'x', 'password' => 'corta', 'pin' => '1234']);

        $this->assertError($resultado, 400, 'VALIDACION');
        $this->assertEqualsCanonicalizing(
            ['nombre', 'email', 'password', 'pin'],
            array_keys($this->cuerpo($resultado)['error']['campos']),
        );
        $this->assertSame(0, $this->usuarios());
    }

    public function testRequiereCsrf(): void
    {
        $this->assertError($this->api('post', 'instalacion', $this->entrada(), conCsrf: false), 403, 'CSRF');
        $this->assertSame(0, $this->usuarios());
    }

    public function testMismoThrottlingQueElLogin(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->assertError($this->api('post', 'instalacion', $this->entrada(['pin' => '1111'])), 400, 'VALIDACION');
        }

        $this->assertError($this->api('post', 'instalacion', $this->entrada()), 429, 'DEMASIADOS_INTENTOS');
        $this->assertSame(0, $this->usuarios());
    }
}
