<?php

declare(strict_types=1);

namespace Tests\Feature\Tareas;

use App\Services\GeneradorInstancias;
use CodeIgniter\Test\StreamFilterTrait;
use Tests\Support\ApiTestCase;

/**
 * T-S2-02 · Generador de instancias y subtarea "generar" de `tareas:programadas`. CP-023.
 *
 * @internal
 */
final class GeneradorTest extends ApiTestCase
{
    use StreamFilterTrait;

    private function generador(): GeneradorInstancias
    {
        return new GeneradorInstancias(db_connect());
    }

    private function total(): int
    {
        return $this->db->table('tarea_instancias')->countAllResults();
    }

    /**
     * CP-023 · Generar dos veces el mismo día: sin duplicados.
     */
    public function testGenerarDosVecesNoDuplica(): void
    {
        $creadas = $this->generador()->generar($this->hoy(), $this->hoy());
        $total   = $this->total();

        $this->assertGreaterThan(0, $creadas);
        $this->assertSame($creadas, $total);
        $this->assertSame(0, $this->generador()->generar($this->hoy(), $this->hoy()));
        $this->assertSame($total, $this->total());

        $duplicados = $this->db->query(
            'SELECT tarea_id, fecha, COUNT(*) n FROM tarea_instancias GROUP BY tarea_id, fecha HAVING n > 1',
        )->getResultArray();
        $this->assertSame([], $duplicados);
    }

    public function testCopiaPuntosHoraYEntregaDeLaTarea(): void
    {
        $this->generador()->generar($this->hoy(), $this->hoy(7));

        $escolar = $this->db->table('tareas')->where('titulo', 'Tarea de matemáticas')->get()->getRowArray();
        $inst    = $this->db->table('tarea_instancias')->where('tarea_id', $escolar['id'])->get()->getResultArray();
        $this->assertCount(1, $inst, 'Una escolar (una_vez) genera una sola instancia.');
        $this->assertSame($this->hoy(), $inst[0]['fecha']);
        $this->assertSame($escolar['fecha_entrega'], $inst[0]['fecha_entrega']);
        $this->assertSame($escolar['puntos'], $inst[0]['puntos']);
        $this->assertSame($escolar['perfil_id'], $inst[0]['perfil_id']);

        $natacion = $this->db->table('tareas')->where('titulo', 'Clase de natación')->get()->getRowArray();
        foreach ($this->db->table('tarea_instancias')->where('tarea_id', $natacion['id'])->get()->getResultArray() as $i) {
            $this->assertSame('17:00:00', $i['hora']);
            $this->assertContains((int) date('N', strtotime($i['fecha'])), [2, 4]);
        }
    }

    public function testNoGeneraParaTareasNiPerfilesInactivos(): void
    {
        $this->db->table('perfiles')->where('apodo', 'Luna')->update(['activo' => 0]);
        $this->db->table('tareas')->where('titulo', 'Leer 20 minutos')->update(['activa' => 0]);

        $this->generador()->generar($this->hoy(), $this->hoy(7));

        $this->assertSame(0, $this->db->table('tarea_instancias')->where('perfil_id', $this->perfilId('Luna'))->countAllResults());
        $leer = $this->db->table('tareas')->where('titulo', 'Leer 20 minutos')->get()->getRowArray();
        $this->assertSame(0, $this->db->table('tarea_instancias')->where('tarea_id', $leer['id'])->countAllResults());
    }

    public function testComandoGeneraHoyMasDiasDeGeneracionSinDuplicar(): void
    {
        $this->db->table('configuracion')->where('clave', 'dias_generacion')->update(['valor' => '3']);

        command('tareas:programadas --forzar');
        $total = $this->total();

        $this->assertSame($this->hoy(3), $this->db->table('tarea_instancias')->selectMax('fecha')->get()->getRowArray()['fecha']);
        $this->assertSame($this->hoy(), $this->db->table('tarea_instancias')->selectMin('fecha')->get()->getRowArray()['fecha']);

        command('tareas:programadas --forzar');
        $this->assertSame($total, $this->total());
        $this->assertStringContainsString('generar', $this->getStreamFilterBuffer());
    }

    public function testComandoGeneraAlArrancarSiFaltaYNoRepiteElMismoDia(): void
    {
        @unlink(WRITEPATH . 'cache/generacion.ultima');

        command('tareas:programadas');
        $this->assertGreaterThan(0, $this->total());

        $this->db->table('tarea_instancias')->emptyTable();
        command('tareas:programadas');
        $this->assertSame(0, $this->total(), 'Ya generó hoy: no vuelve a correr hasta las 00:05 o mañana.');

        @unlink(WRITEPATH . 'cache/generacion.ultima');
    }

    public function testComandoRespetaElCandado(): void
    {
        $candado = fopen(WRITEPATH . 'cache/cron.lock', 'c');
        $this->assertNotFalse($candado);
        flock($candado, LOCK_EX);

        try {
            command('tareas:programadas --forzar');
            $this->assertSame(0, $this->total());
            $this->assertStringContainsString('en curso', $this->getStreamFilterBuffer());
        } finally {
            flock($candado, LOCK_UN);
            fclose($candado);
        }
    }

    public function testAsegurarGeneraLaFechaPedida(): void
    {
        $this->generador()->asegurar($this->hoy(2));

        $fechas = array_unique(array_column($this->db->table('tarea_instancias')->get()->getResultArray(), 'fecha'));
        $this->assertSame([$this->hoy(2)], array_values($fechas));
    }
}
