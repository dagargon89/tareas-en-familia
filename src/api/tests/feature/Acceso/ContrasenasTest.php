<?php

declare(strict_types=1);

namespace Tests\Feature\Acceso;

use App\Database\Seeds\DemoSeeder;
use CodeIgniter\Test\TestResponse;
use Tests\Support\ApiTestCase;

/**
 * ADR-010 · Cambio y restablecimiento de contraseña de los padres (RF-02): E-53. CP-088 a CP-090.
 *
 * @internal
 */
final class ContrasenasTest extends ApiTestCase
{
    private const NUEVA = 'NuevaContrasena2026';

    private function papaId(): int
    {
        return (int) $this->db->table('usuarios')->where('email', DemoSeeder::EMAIL)->get()->getRowArray()['id'];
    }

    private function cambiar(int $id, array $cuerpo): TestResponse
    {
        return $this->api('put', "admin/usuarios/{$id}/password", $cuerpo);
    }

    private function login(string $email, string $password): TestResponse
    {
        return $this->api('post', 'auth/login', ['email' => $email, 'password' => $password]);
    }

    private function hashDe(int $id): string
    {
        return (string) $this->fila('usuarios', $id)['password_hash'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarSesionDemo();
        $this->entrarModoPadre();
    }

    /**
     * CP-088 · Cambio propio con la actual correcta: 200 con el `UsuarioAdmin`; entra con la nueva y ya no con la anterior.
     */
    public function testCambioPropioConLaActualCorrecta(): void
    {
        $padre = $this->cuerpo($this->conEstado($this->cambiar($this->papaId(), ['password_actual' => DemoSeeder::PASSWORD, 'password' => self::NUEVA]), 200));

        $this->assertSame(['id' => $this->papaId(), 'nombre' => 'Papá Demo', 'email' => DemoSeeder::EMAIL, 'es_actual' => true], $padre);

        $this->assertMatchesRegularExpression('/^\$(argon2id|2y)\$/', $this->hashDe($this->papaId()));
        $this->api('post', 'auth/logout')->assertStatus(204);
        $this->assertError($this->login(DemoSeeder::EMAIL, DemoSeeder::PASSWORD), 401, 'CREDENCIALES');
        $this->login(DemoSeeder::EMAIL, self::NUEVA)->assertStatus(200);
    }

    /**
     * CP-089 · Actual incorrecta o ausente: 400 en `password_actual`, sin cambio; con el límite del login.
     */
    public function testCambioPropioConLaActualIncorrecta(): void
    {
        $antes = $this->hashDe($this->papaId());

        $r = $this->cambiar($this->papaId(), ['password_actual' => 'NoEsLaActual123', 'password' => self::NUEVA]);
        $this->assertError($r, 400, 'VALIDACION');
        $this->assertSame(['password_actual'], array_keys($this->cuerpo($r)['error']['campos']));

        $sinActual = $this->cambiar($this->papaId(), ['password' => self::NUEVA]);
        $this->assertError($sinActual, 400, 'VALIDACION');
        $this->assertArrayHasKey('password_actual', $this->cuerpo($sinActual)['error']['campos']);
        $this->assertSame($antes, $this->hashDe($this->papaId()));

        // El login de setUp ya gastó un intento: los siguientes agotan los 5 por minuto.
        $estados = [];

        for ($i = 0; $i < 5; $i++) {
            $estados[] = $this->cambiar($this->papaId(), ['password_actual' => 'NoEsLaActual123', 'password' => self::NUEVA])->response()->getStatusCode();
        }

        $this->assertContains(429, $estados, 'Comparte el límite de 5 por minuto del login');
        $this->assertSame($antes, $this->hashDe($this->papaId()));
    }

    /**
     * CP-090 · Restablecer la del otro padre sin la actual: 200; el otro entra con la nueva.
     */
    public function testRestablecerLaDelOtroPadre(): void
    {
        $mama = $this->cuerpo($this->conEstado($this->api('post', 'admin/usuarios', [
            'nombre' => 'Mamá', 'email' => 'mama@tareas.local', 'password' => 'LaQueOlvido2026', 'pin' => '7351',
        ]), 201));

        $this->assertSame([$mama['id'], false], array_values(array_intersect_key($this->cuerpo($this->conEstado($this->cambiar($mama['id'], ['password' => self::NUEVA]), 200)), ['id' => 0, 'es_actual' => 0])));

        $this->api('post', 'auth/logout')->assertStatus(204);
        $this->assertError($this->login('mama@tareas.local', 'LaQueOlvido2026'), 401, 'CREDENCIALES');
        $this->login('mama@tareas.local', self::NUEVA)->assertStatus(200);
    }

    public function testValidacionYPermisos(): void
    {
        $corta = $this->cambiar($this->papaId(), ['password_actual' => DemoSeeder::PASSWORD, 'password' => 'corta']);
        $this->assertError($corta, 400, 'VALIDACION');
        $this->assertSame(['password'], array_keys($this->cuerpo($corta)['error']['campos']));

        $this->assertError($this->cambiar(999999, ['password' => self::NUEVA]), 404, 'NO_ENCONTRADO');

        $this->api('delete', 'modo-padre')->assertStatus(200);
        $this->assertError($this->cambiar($this->papaId(), ['password_actual' => DemoSeeder::PASSWORD, 'password' => self::NUEVA]), 403, 'MODO_PADRE_REQUERIDO');
    }
}
