<?php

declare(strict_types=1);

namespace Tests\Feature\Acceso;

use App\Database\Seeds\DemoSeeder;
use CodeIgniter\Config\Factories;
use CodeIgniter\Test\Mock\MockSession;
use Config\Cookie;
use Config\Services;
use Config\Session;
use Tests\Support\ApiTestCase;

/**
 * T-S1-01 · Login y sesión del padre (RF-02): E-03, E-04, E-05.
 * CP-001, CP-002, CP-SEG-03, CP-SEG-07 y CP-SEG-01 (CSRF en una ruta real).
 *
 * @internal
 */
final class AuthTest extends ApiTestCase
{
    /**
     * CP-001 · Login válido: 200, cookie `tf_sesion` y `Sesion.usuario` lleno.
     */
    public function testLoginValidoAbreLaSesionDelDispositivo(): void
    {
        $resultado = $this->api('post', 'auth/login', ['email' => DemoSeeder::EMAIL, 'password' => DemoSeeder::PASSWORD]);

        $resultado->assertStatus(200);
        $usuario = $this->db->table('usuarios')->get()->getRowArray();
        $this->assertSame([
            'usuario'          => ['id' => (int) $usuario['id'], 'nombre' => 'Papá Demo'],
            'perfil_activo'    => null,
            'modo_padre_hasta' => null,
        ], $this->cuerpo($resultado));
        $this->assertSame((int) $usuario['id'], $this->sesion['usuario_id']);
        $this->assertNotNull($usuario['ultimo_acceso']);

        $sesion = service('session');
        $this->assertInstanceOf(MockSession::class, $sesion);
        $this->assertTrue($sesion->didRegenerate, 'Se regenera el id de sesión al iniciar sesión.');
        $this->assertSame('tf_sesion', config(Session::class)->cookieName);
    }

    public function testLoginIgnoraMayusculasYEspaciosDelCorreo(): void
    {
        $this->api('post', 'auth/login', ['email' => '  DEMO@Tareas.Local ', 'password' => DemoSeeder::PASSWORD])
            ->assertStatus(200);
    }

    /**
     * CP-002 · Contraseña incorrecta: 401 `CREDENCIALES`.
     */
    public function testLoginConContrasenaIncorrecta(): void
    {
        $resultado = $this->api('post', 'auth/login', ['email' => DemoSeeder::EMAIL, 'password' => 'NoEsLaContrasena']);

        $this->assertError($resultado, 401, 'CREDENCIALES');
        $this->assertArrayNotHasKey('usuario_id', $this->sesion);
    }

    public function testLoginConCorreoInexistenteDaLaMismaRespuesta(): void
    {
        $this->assertError(
            $this->api('post', 'auth/login', ['email' => 'nadie@tareas.local', 'password' => DemoSeeder::PASSWORD]),
            401,
            'CREDENCIALES',
        );
    }

    public function testLoginSinCamposEsValidacion(): void
    {
        $resultado = $this->api('post', 'auth/login', ['email' => 'no-es-correo']);

        $this->assertError($resultado, 400, 'VALIDACION');
        $campos = $this->cuerpo($resultado)['error']['campos'];
        $this->assertArrayHasKey('email', $campos);
        $this->assertArrayHasKey('password', $campos);
    }

    /**
     * CP-SEG-03 · 6 logins fallidos en un minuto: el sexto responde 429 `DEMASIADOS_INTENTOS`.
     */
    public function testSextoLoginFallidoEnUnMinutoEsThrottled(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->assertError(
                $this->api('post', 'auth/login', ['email' => DemoSeeder::EMAIL, 'password' => 'Incorrecta' . $i]),
                401,
                'CREDENCIALES',
            );
        }

        $resultado = $this->api('post', 'auth/login', ['email' => DemoSeeder::EMAIL, 'password' => DemoSeeder::PASSWORD]);

        $this->assertError($resultado, 429, 'DEMASIADOS_INTENTOS');
        $this->assertNotSame('', $resultado->response()->getHeaderLine('Retry-After'));
    }

    /**
     * CP-SEG-07 · Cookie de sesión en producción: `HttpOnly`, `Secure`, `SameSite=Lax`.
     *
     * `Config\Cookie::$secure` vale `ENVIRONMENT === 'production'`; aquí se
     * inyecta el valor de producción y se inspecciona la cookie que emite la sesión.
     */
    public function testCookieDeSesionEnProduccion(): void
    {
        $fuente = (string) file_get_contents(APPPATH . 'Config/Cookie.php');
        $this->assertMatchesRegularExpression("/public bool \\\$secure = ENVIRONMENT === 'production';/", $fuente);

        $cookieProduccion         = new Cookie();
        $cookieProduccion->secure = true;
        Factories::injectMock('config', Cookie::class, $cookieProduccion);

        $sesion = new MockSession(new \CodeIgniter\Session\Handlers\ArrayHandler(config(Session::class), '0.0.0.0'), config(Session::class));
        $sesion->setLogger(service('logger'));
        Services::injectMock('session', $sesion);
        $sesion->start();

        $this->iniciarSesionDemo();

        $cookie = end($sesion->cookies);
        $this->assertNotFalse($cookie);
        $this->assertSame('tf_sesion', $cookie->getName());
        $this->assertTrue($cookie->isHTTPOnly());
        $this->assertTrue($cookie->isSecure());
        $this->assertSame('Lax', $cookie->getSameSite());
        $this->assertGreaterThanOrEqual(time() + 2_592_000 - 5, $cookie->getExpiresTimestamp(), '30 días.');
    }

    public function testSesionSinIniciarResponde401SinSesion(): void
    {
        $this->assertError($this->api('get', 'sesion'), 401, 'SIN_SESION');
    }

    public function testSesionDevuelveElEstadoActual(): void
    {
        $this->iniciarSesionDemo();

        $cuerpo = $this->cuerpo($this->conEstado($this->api('get', 'sesion'), 200));

        $this->assertSame('Papá Demo', $cuerpo['usuario']['nombre']);
        $this->assertNull($cuerpo['perfil_activo']);
        $this->assertNull($cuerpo['modo_padre_hasta']);
    }

    public function testLogoutResponde204YCierraLaSesion(): void
    {
        $this->iniciarSesionDemo();
        $this->entrarModoPadre();

        $resultado = $this->api('post', 'auth/logout');

        $resultado->assertStatus(204);
        $this->assertSame('', (string) $resultado->response()->getBody());
        $this->assertArrayNotHasKey('usuario_id', $this->sesion);
        $this->assertArrayNotHasKey('modo_padre_hasta', $this->sesion);
        $this->assertError($this->api('get', 'sesion'), 401, 'SIN_SESION');
    }

    public function testLogoutSinSesionEs401(): void
    {
        $this->assertError($this->api('post', 'auth/logout'), 401, 'SIN_SESION');
    }

    /**
     * CP-SEG-01 · `POST` sin `X-CSRF-TOKEN` en una ruta real: 403 `CSRF`.
     */
    public function testPostSinTokenCsrfEs403(): void
    {
        $resultado = $this->api(
            'post',
            'auth/login',
            ['email' => DemoSeeder::EMAIL, 'password' => DemoSeeder::PASSWORD],
            conCsrf: false,
        );

        $this->assertError($resultado, 403, 'CSRF');
        $this->assertArrayNotHasKey('usuario_id', $this->sesion);
    }

    /**
     * Las respuestas de error también renuevan el token: tras un 400 el cliente
     * usa el `X-CSRF-TOKEN` recibido y la siguiente petición mutante pasa.
     */
    public function testRespuestaDeErrorTraeElTokenRenovado(): void
    {
        $anterior = csrf_hash();
        $error    = $this->api('post', 'auth/login', ['email' => 'no-es-correo']);

        $this->assertError($error, 400, 'VALIDACION');
        $nuevo = $error->response()->getHeaderLine('X-CSRF-TOKEN');
        $this->assertNotSame('', $nuevo);
        $this->assertNotSame($anterior, $nuevo);
        $this->assertSame(csrf_hash(), $nuevo);
    }

    public function testSaludNoLlevaTokenNiAbreSesion(): void
    {
        $this->assertSame('', $this->api('get', 'salud')->response()->getHeaderLine('X-CSRF-TOKEN'));
    }

    public function testNoExisteEndpointDeRegistro(): void
    {
        $this->assertError($this->api('post', 'auth/registro', ['email' => 'x@y.z']), 404, 'NO_ENCONTRADO');
    }
}
