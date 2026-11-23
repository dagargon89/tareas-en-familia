<?php

declare(strict_types=1);

namespace Tests\Feature\Despliegue;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\StreamFilterTrait;

/**
 * T-S0-05 · `tareas:migrar`: el despliegue necesita un código de salida que refleje el resultado
 * (`spark migrate` sale con 0 aunque falle).
 *
 * @internal
 */
final class MigrarTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use StreamFilterTrait;

    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = false;
    protected $namespace   = 'App';

    private function correr(array $parametros): int
    {
        return (int) service('commands')->run('tareas:migrar', $parametros);
    }

    public function testConLaBaseDisponibleSaleConCeroYSiembraLosCatalogos(): void
    {
        $this->db->table('configuracion')->where('clave', 'dias_generacion')->delete();

        $this->assertSame(EXIT_SUCCESS, $this->correr(['g' => 'tests']));
        $this->assertStringContainsString('Migraciones y catálogos al día.', $this->getStreamFilterBuffer());
        $this->assertSame(1, $this->db->table('configuracion')->where('clave', 'dias_generacion')->countAllResults(), 'CatalogoSeeder repone lo que falta');
    }

    public function testConUnGrupoQueNoConectaSaleConUno(): void
    {
        config('Database')->sinConexion = ['DBDriver' => 'MySQLi', 'hostname' => '127.0.0.1', 'port' => 1, 'database' => 'nada', 'username' => 'nadie', 'password' => 'x', 'DBPrefix' => ''];

        $this->assertSame(EXIT_ERROR, $this->correr(['g' => 'sinConexion']));
        $this->assertStringContainsString('Falló la migración', $this->getStreamFilterBuffer());
    }
}
