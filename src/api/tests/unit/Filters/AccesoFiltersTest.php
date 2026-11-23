<?php

declare(strict_types=1);

namespace Tests\Unit\Filters;

use App\Filters\ModoPadreFilter;
use App\Filters\PerfilActivoFilter;
use App\Filters\SesionFilter;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Filtros de acceso (doc 04 §3.2). CP-008: expiración del modo padre.
 *
 * @internal
 */
final class AccesoFiltersTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = true;
    protected $namespace   = 'App';
    protected $seed        = ['App\Database\Seeds\CatalogoSeeder', 'App\Database\Seeds\DemoSeeder'];
    protected $seedOnce    = true;

    protected function tearDown(): void
    {
        Time::setTestNow();
        parent::tearDown();
    }

    private function codigo(mixed $respuesta): ?string
    {
        $this->assertInstanceOf(ResponseInterface::class, $respuesta);

        return json_decode((string) $respuesta->getBody(), true)['error']['codigo'] ?? null;
    }

    /**
     * CP-008 · Tras 10 min sin actividad, `ModoPadreFilter` devuelve 403 `MODO_PADRE_REQUERIDO`.
     */
    public function testModoPadreExpiraTrasDiezMinutosSinActividad(): void
    {
        Time::setTestNow('2026-12-02 18:30:00');
        $_SESSION = ['usuario_id' => 1, 'modo_padre_hasta' => Time::now()->addMinutes(10)->getTimestamp()];
        $filtro   = new ModoPadreFilter();

        Time::setTestNow('2026-12-02 18:39:59');
        $this->assertNull($filtro->before(service('request')));
        $this->assertSame(Time::now()->addMinutes(10)->getTimestamp(), $_SESSION['modo_padre_hasta'], 'Renueva 10 min.');

        Time::setTestNow('2026-12-02 18:50:00');
        $respuesta = $filtro->before(service('request'));

        $this->assertSame('MODO_PADRE_REQUERIDO', $this->codigo($respuesta));
        $this->assertSame(403, $respuesta->getStatusCode());
        $this->assertArrayNotHasKey('modo_padre_hasta', $_SESSION);
    }

    public function testModoPadreSinActivarEs403(): void
    {
        $_SESSION = ['usuario_id' => 1];

        $this->assertSame('MODO_PADRE_REQUERIDO', $this->codigo((new ModoPadreFilter())->before(service('request'))));
    }

    public function testSesionFilterExigeUsuario(): void
    {
        $_SESSION = [];
        $respuesta = (new SesionFilter())->before(service('request'));
        $this->assertSame('SIN_SESION', $this->codigo($respuesta));
        $this->assertSame(401, $respuesta->getStatusCode());

        $_SESSION = ['usuario_id' => (int) $this->db->table('usuarios')->get()->getRowArray()['id']];
        $this->assertNull((new SesionFilter())->before(service('request')));
    }

    public function testSesionFilterRechazaUsuarioQueYaNoExiste(): void
    {
        $_SESSION = ['usuario_id' => 9999];

        $this->assertSame('SIN_SESION', $this->codigo((new SesionFilter())->before(service('request'))));
    }

    public function testPerfilActivoFilterExigePerfilActivo(): void
    {
        $perfil = $this->db->table('perfiles')->where('apodo', 'Luna')->get()->getRowArray();
        $filtro = new PerfilActivoFilter();

        $_SESSION = ['usuario_id' => 1];
        $respuesta = $filtro->before(service('request'));
        $this->assertSame('SIN_PERFIL', $this->codigo($respuesta));
        $this->assertSame(403, $respuesta->getStatusCode());

        $_SESSION = ['usuario_id' => 1, 'perfil_activo_id' => (int) $perfil['id']];
        $this->assertNull($filtro->before(service('request')));

        $this->db->table('perfiles')->where('id', $perfil['id'])->update(['activo' => 0]);
        $this->assertSame('SIN_PERFIL', $this->codigo($filtro->before(service('request'))));
        $this->db->table('perfiles')->where('id', $perfil['id'])->update(['activo' => 1]);
    }
}
