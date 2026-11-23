<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Database\Seeds\CatalogoSeeder;
use App\Database\Seeds\DemoSeeder;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use Config\Services;

/**
 * Base de las pruebas de integración de la API.
 *
 * - Migra una vez por clase y, antes de cada prueba, vacía las tablas y
 *   siembra `CatalogoSeeder` + `DemoSeeder` (datos aislados por prueba).
 * - `api()` conserva la sesión entre peticiones de una misma prueba (el
 *   `FeatureTestTrait` la reinicia en cada llamada) y envía el token CSRF.
 *
 * @internal
 */
abstract class ApiTestCase extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = true;
    protected $namespace   = 'App';
    protected $seed        = '';

    /**
     * Sesión que se arrastra entre peticiones de la prueba.
     *
     * @var array<string, mixed>
     */
    protected array $sesion = [];

    private const TABLAS = [
        'evidencias', 'revisiones', 'movimientos_puntos', 'canjes', 'perfil_insignias', 'rachas',
        'tarea_instancias', 'tarea_recurrencias', 'tareas', 'recompensas', 'metas_familiares',
        'perfiles', 'usuarios', 'insignias', 'categorias', 'configuracion',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');

        foreach (self::TABLAS as $tabla) {
            $this->db->table($tabla)->truncate();
        }

        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');

        $this->seed(CatalogoSeeder::class);
        $this->seed(DemoSeeder::class);

        // El Throttler compartido conserva la caché de una prueba anterior: se recrea con la caché simulada nueva.
        Services::resetSingle('throttler');
        cache()->clean();
        $this->sesion = [];
    }

    protected function tearDown(): void
    {
        Time::setTestNow();
        parent::tearDown();
    }

    /**
     * Petición a `/api/v1/<ruta>` con cuerpo JSON opcional, sesión arrastrada y CSRF.
     *
     * @param array<string, mixed>|null $cuerpo
     */
    protected function api(string $metodo, string $ruta, ?array $cuerpo = null, bool $conCsrf = true): TestResponse
    {
        $headers = ['Accept' => 'application/json'];

        if ($conCsrf) {
            $headers['X-CSRF-TOKEN'] = csrf_hash();
        }

        $peticion = $this->withSession($this->sesion)->withHeaders($headers);

        if ($cuerpo !== null) {
            $peticion = $peticion->withBodyFormat('json');
        }

        $resultado = $peticion->call(strtolower($metodo), 'api/v1/' . ltrim($ruta, '/'), $cuerpo);

        $this->sesion = $_SESSION ?? [];
        $this->bodyFormat = '';

        return $resultado;
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function cuerpo(TestResponse $resultado): array
    {
        return json_decode($resultado->getJSON() ?: 'null', true) ?? [];
    }

    protected function conEstado(TestResponse $resultado, int $estado): TestResponse
    {
        $resultado->assertStatus($estado);

        return $resultado;
    }

    protected function assertError(TestResponse $resultado, int $estado, string $codigo): void
    {
        $resultado->assertStatus($estado);
        $this->assertSame($codigo, $this->cuerpo($resultado)['error']['codigo'] ?? null);
    }

    protected function iniciarSesionDemo(): void
    {
        $this->api('post', 'auth/login', ['email' => DemoSeeder::EMAIL, 'password' => DemoSeeder::PASSWORD])
            ->assertStatus(200);
    }

    protected function entrarModoPadre(): void
    {
        $this->api('post', 'modo-padre', ['pin' => DemoSeeder::PIN])->assertStatus(200);
    }

    protected function perfilId(string $apodo): int
    {
        return (int) $this->db->table('perfiles')->where('apodo', $apodo)->get()->getRowArray()['id'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function fila(string $tabla, int $id): array
    {
        return $this->db->table($tabla)->where('id', $id)->get()->getRowArray() ?? [];
    }
}
