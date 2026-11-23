<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Database\Seeds\DemoSeeder;
use App\Libraries\Hash;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * T-S0-02 · `CatalogoSeeder` (doc 03 §5, §6 y RF-11) y `DemoSeeder` (doc 03 §7).
 * También aporta evidencia de CP-SEG-06 sobre los hashes guardados.
 *
 * @internal
 */
final class SemillasTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = true;
    protected $namespace   = 'App';
    protected $seed        = ['App\Database\Seeds\CatalogoSeeder', 'App\Database\Seeds\DemoSeeder'];
    protected $seedOnce    = true;

    public function testCatalogoDeCategorias(): void
    {
        $nombres = array_column($this->db->table('categorias')->orderBy('id')->get()->getResultArray(), 'nombre');

        $this->assertSame(['Casa', 'Escuela', 'Actividad', 'Hábito', 'Cuidado personal'], $nombres);
    }

    public function testOchoInsigniasDelRf11ConReglaValida(): void
    {
        $insignias = $this->db->table('insignias')->orderBy('orden')->get()->getResultArray();

        $this->assertSame(
            ['Primera misión', '10 misiones', '50 misiones', '100 misiones', 'Racha de 2 semanas',
                'Racha de 4 semanas', '10 tareas escolares', '10 tareas de casa'],
            array_column($insignias, 'nombre'),
        );

        foreach ($insignias as $insignia) {
            $this->assertSame('20', (string) $insignia['xp'], 'RF-11: cada insignia da 20 XP.');
            $regla = json_decode($insignia['regla'], true, 512, JSON_THROW_ON_ERROR);
            $this->assertContains($regla['tipo'], ['tareas_aprobadas', 'racha_semanas', 'tareas_por_tipo']);
            $this->assertIsInt($regla['min']);

            if ($regla['tipo'] === 'tareas_por_tipo') {
                $this->assertContains($regla['tipo_tarea'], ['casa', 'escolar', 'actividad']);
            }
        }

        $this->assertSame(
            ['tipo' => 'tareas_por_tipo', 'tipo_tarea' => 'escolar', 'min' => 10],
            json_decode($insignias[6]['regla'], true),
        );
    }

    public function testConfiguracionInicialDelDoc03(): void
    {
        $filas = $this->db->table('configuracion')->get()->getResultArray();
        $valores = array_map(static fn (string $v): mixed => json_decode($v, true), array_column($filas, 'valor', 'clave'));
        ksort($valores);

        $this->assertSame([
            'dias_generacion'          => 7,
            'evidencia_dias_retencion' => 30,
            'puntos_tarea_propia'      => 5,
            'racha_umbral'             => 80,
            'sonidos_default'          => true,
        ], $valores);
    }

    public function testDemoTieneLasCantidadesDelDoc03(): void
    {
        $this->assertSame(1, $this->db->table('usuarios')->countAllResults());
        $this->assertSame(3, $this->db->table('perfiles')->countAllResults());
        $this->assertSame(12, $this->db->table('tareas')->countAllResults());
        $this->assertSame(12, $this->db->table('tarea_recurrencias')->countAllResults());
        $this->assertSame(4, $this->db->table('recompensas')->countAllResults());
        $this->assertSame(1, $this->db->table('metas_familiares')->where('estado', 'activa')->countAllResults());

        $niveles = array_column($this->db->table('perfiles')->orderBy('orden')->get()->getResultArray(), 'nivel_ux');
        $this->assertSame(['exploradoras', 'aventureras', 'pro'], $niveles);
    }

    public function testFechasDeLaDemoSonRelativasAHoy(): void
    {
        $hoy  = Time::today()->toDateString();
        $meta = $this->db->table('metas_familiares')->get()->getRowArray();

        $this->assertLessThanOrEqual($hoy, $meta['fecha_inicio']);
        $this->assertGreaterThan($hoy, $meta['fecha_limite']);

        $escolar = $this->db->table('tareas')->where('tipo', 'escolar')->get()->getRowArray();
        $this->assertGreaterThanOrEqual($hoy, $escolar['fecha_entrega']);
    }

    /**
     * CP-SEG-06 · Contraseña y PIN guardados comienzan con `$argon2id$` o `$2y$`.
     */
    public function testHashesGuardadosSonArgon2idOBcrypt(): void
    {
        $usuario = $this->db->table('usuarios')->get()->getRowArray();
        $hashes  = [$usuario['password_hash'], $usuario['pin_hash'],
            ...array_column($this->db->table('perfiles')->get()->getResultArray(), 'pin_hash')];

        $this->assertCount(5, $hashes);

        foreach ($hashes as $hash) {
            $this->assertMatchesRegularExpression('/^(\$argon2id\$|\$2y\$)/', $hash);
        }
    }

    public function testPinsDeLaDemoNoSonTrivialesYCoincidenConSuHash(): void
    {
        $triviales = ['0000', '1234', '4321', '0123', '9876'];

        for ($d = 0; $d <= 9; $d++) {
            $triviales[] = str_repeat((string) $d, 4);
        }

        $pins = [DemoSeeder::PIN => $this->db->table('usuarios')->get()->getRowArray()['pin_hash']];

        foreach (DemoSeeder::PERFILES as $perfil) {
            $pins[$perfil['pin']] = $this->db->table('perfiles')->where('apodo', $perfil['apodo'])->get()->getRowArray()['pin_hash'];
        }

        foreach ($pins as $pin => $hash) {
            $pin = (string) $pin;
            $this->assertMatchesRegularExpression('/^\d{4}$/', $pin);
            $this->assertNotContains($pin, $triviales, "PIN trivial {$pin} en la demo.");
            $this->assertTrue(Hash::verificar($pin, $hash));
        }

        $usuario = $this->db->table('usuarios')->get()->getRowArray();
        $this->assertGreaterThanOrEqual(10, strlen(DemoSeeder::PASSWORD), 'Doc 04 §3.1: mínimo 10 caracteres.');
        $this->assertTrue(Hash::verificar(DemoSeeder::PASSWORD, $usuario['password_hash']));
    }
}
