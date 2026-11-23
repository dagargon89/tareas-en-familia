<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Database\Seeds\CatalogoSeeder;
use App\Database\Seeds\DemoSeeder;
use App\Domain\Calendario;
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
        Services::resetSingle('response'); // la respuesta compartida conservaría headers de la prueba anterior
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

    /**
     * Entra al perfil de la demo con su PIN.
     */
    protected function entrarPerfil(string $apodo): void
    {
        $pin = array_column(DemoSeeder::PERFILES, 'pin', 'apodo')[$apodo];
        $this->api('post', 'perfiles/' . $this->perfilId($apodo) . '/entrar', ['pin' => $pin])->assertStatus(200);
    }

    /**
     * "Hoy" en America/Ciudad_Juarez (respeta `Time::setTestNow()`).
     */
    protected function hoy(int $masDias = 0): string
    {
        return Calendario::sumarDias(Calendario::hoy(Time::now()), $masDias);
    }

    /**
     * Desactiva las tareas de la demo para que cada prueba controle sus instancias.
     */
    protected function sinTareasDemo(): void
    {
        $this->db->table('tareas')->update(['activa' => 0]);
    }

    /**
     * `TareaEntrada` válida (casa, diaria desde hoy) con cambios.
     *
     * @param array<string, mixed> $cambios
     * @param array<string, mixed> $recurrencia
     *
     * @return array<string, mixed>
     */
    protected function tareaEntrada(string $apodo, array $cambios = [], array $recurrencia = []): array
    {
        $casa = (int) $this->db->table('categorias')->where('nombre', 'Casa')->get()->getRowArray()['id'];

        return array_merge([
            'perfil_id'     => $this->perfilId($apodo),
            'categoria_id'  => $casa,
            'titulo'        => 'Regar las plantas',
            'icono'         => 'planta',
            'puntos'        => 5,
            'tipo'          => 'casa',
            'materia'       => null,
            'fecha_entrega' => null,
            'requiere_foto' => false,
            'recurrencia'   => array_merge([
                'frecuencia' => 'diaria', 'dias_semana' => null, 'hora' => null,
                'fecha_inicio' => $this->hoy(), 'fecha_fin' => null,
            ], $recurrencia),
        ], $cambios);
    }

    /**
     * Crea una tarea por E-29 (requiere modo padre en la sesión) y devuelve la `Tarea`.
     *
     * @param array<string, mixed> $cambios
     * @param array<string, mixed> $recurrencia
     *
     * @return array<string, mixed>
     */
    protected function crearTarea(string $apodo, array $cambios = [], array $recurrencia = []): array
    {
        return $this->cuerpo($this->conEstado($this->api('post', 'admin/tareas', $this->tareaEntrada($apodo, $cambios, $recurrencia)), 201));
    }

    /**
     * Instancia de la tarea en la fecha dada.
     *
     * @return array<string, mixed>
     */
    protected function instancia(int $tareaId, string $fecha): array
    {
        return $this->db->table('tarea_instancias')->where(['tarea_id' => $tareaId, 'fecha' => $fecha])->get()->getRowArray() ?? [];
    }

    /**
     * Inserta instancias del perfil (sobre una tarea suya de la demo) con los estados dados,
     * una por fecha. Devuelve los ids.
     *
     * @param list<string>          $fechas
     * @param list<string>|string   $estados Uno por fecha, o el mismo para todas
     *
     * @return list<int>
     */
    protected function insertarInstancias(string $apodo, array $fechas, array|string $estados, int $puntos = 5): array
    {
        $perfil = $this->perfilId($apodo);
        $ahora  = date('Y-m-d H:i:s');
        $ids    = [];

        foreach (array_values($fechas) as $i => $fecha) {
            $this->db->table('tareas')->insert([
                'perfil_id' => $perfil, 'categoria_id' => (int) $this->db->table('categorias')->get()->getRowArray()['id'],
                'titulo' => "Tarea {$fecha} {$i}", 'icono' => 'casa', 'puntos' => $puntos, 'tipo' => 'casa',
                'creada_por' => 'padre', 'activa' => 0, 'created_at' => $ahora, 'updated_at' => $ahora,
            ]);
            $tarea  = (int) $this->db->insertID();
            $estado = is_array($estados) ? $estados[$i] : $estados;
            $this->db->table('tarea_instancias')->insert([
                'tarea_id' => $tarea, 'perfil_id' => $perfil, 'fecha' => $fecha, 'puntos' => $puntos, 'estado' => $estado,
                'completada_en' => $estado === 'pendiente' ? null : $ahora, 'created_at' => $ahora, 'updated_at' => $ahora,
            ]);
            $ids[] = (int) $this->db->insertID();
        }

        return $ids;
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
