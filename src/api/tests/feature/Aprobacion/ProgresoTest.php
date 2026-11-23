<?php

declare(strict_types=1);

namespace Tests\Feature\Aprobacion;

use App\Domain\SemanaIso;
use App\Services\GeneradorInstancias;
use Tests\Support\ApiTestCase;

/**
 * T-S3-02 · `GET /yo/progreso` (E-13) con la forma completa de `Progreso`.
 *
 * @internal
 */
final class ProgresoTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->sinTareasDemo();
        $this->iniciarSesionDemo();
    }

    /**
     * @return array<string, mixed>
     */
    private function progreso(): array
    {
        return $this->cuerpo($this->conEstado($this->api('get', 'yo/progreso'), 200));
    }

    private function movimiento(string $apodo, string $origen, int $puntos, ?int $origenId, string $fecha): void
    {
        $this->db->table('movimientos_puntos')->insert([
            'perfil_id' => $this->perfilId($apodo), 'origen' => $origen, 'origen_id' => $origenId, 'puntos' => $puntos,
            'descripcion' => 'Prueba', 'created_at' => $fecha,
        ]);
    }

    public function testFormaInicial(): void
    {
        $this->entrarPerfil('Luna');

        $p = $this->progreso();

        $this->assertSame(
            ['xp', 'saldo', 'disponible', 'nivel', 'siguiente_nivel', 'racha_semanas', 'semana_actual', 'insignias', 'meta_familiar'],
            array_keys($p),
        );
        $this->assertSame([0, 0, 0, 0], [$p['xp'], $p['saldo'], $p['disponible'], $p['racha_semanas']]);
        $this->assertSame(['nombre' => 'Semilla', 'xp_desde' => 0, 'xp_hasta' => 99], $p['nivel']);
        $this->assertSame(['nombre' => 'Brote', 'xp_desde' => 100, 'xp_hasta' => 299], $p['siguiente_nivel']);
        $this->assertSame(['asignadas' => 0, 'aprobadas' => 0, 'porcentaje' => 0], $p['semana_actual']);
        $this->assertCount(8, $p['insignias']);
        $this->assertSame(['codigo', 'nombre', 'descripcion', 'icono', 'color', 'obtenida_en'], array_keys($p['insignias'][0]));
        $this->assertSame('primera_mision', $p['insignias'][0]['codigo']);
        $this->assertNull($p['insignias'][0]['obtenida_en']);

        $meta = $this->db->table('metas_familiares')->get()->getRowArray();
        $this->assertSame([
            'id' => (int) $meta['id'], 'titulo' => $meta['titulo'], 'premio' => $meta['premio'], 'objetivo_puntos' => 500,
            'progreso_puntos' => 0, 'fecha_inicio' => $meta['fecha_inicio'], 'fecha_limite' => $meta['fecha_limite'], 'estado' => 'activa',
        ], $p['meta_familiar']);
    }

    public function testXpSaldoDisponibleYNivel(): void
    {
        $ahora = date('Y-m-d H:i:s');
        $this->movimiento('Sol', 'tarea', 250, 1, $ahora);
        $this->movimiento('Sol', 'insignia', 20, 1, $ahora);
        $this->movimiento('Sol', 'ajuste', 30, null, $ahora);
        $this->movimiento('Sol', 'canje', -100, 1, $ahora);
        $this->movimiento('Luna', 'tarea', 999, 2, $ahora);
        $recompensa = (int) $this->db->table('recompensas')->get()->getRowArray()['id'];
        $this->db->table('canjes')->insert(['perfil_id' => $this->perfilId('Sol'), 'recompensa_id' => $recompensa, 'costo' => 50, 'estado' => 'solicitado', 'created_at' => $ahora, 'updated_at' => $ahora]);
        $this->entrarPerfil('Sol');

        $p = $this->progreso();

        $this->assertSame(270, $p['xp'], 'XP = positivos de tarea e insignia.');
        $this->assertSame(200, $p['saldo']);
        $this->assertSame(150, $p['disponible'], 'Saldo menos canjes solicitados.');
        $this->assertSame('Brote', $p['nivel']['nombre']);
        $this->assertSame('Raíz', $p['siguiente_nivel']['nombre']);
    }

    public function testSemanaActual(): void
    {
        $semana = SemanaIso::desdeFecha($this->hoy());
        $this->iniciarSesionDemo();
        $this->entrarModoPadre();
        $tarea = $this->crearTarea('Luna', [], ['fecha_inicio' => $semana->lunes()]);
        (new GeneradorInstancias(db_connect()))->generar($semana->lunes(), $semana->domingo());
        $this->db->table('tarea_instancias')->where('tarea_id', $tarea['id'])->whereIn('fecha', [$semana->lunes(), $semana->domingo()])->update(['estado' => 'aprobada']);
        $this->db->table('tarea_instancias')->where('tarea_id', $tarea['id'])->where('fecha', $semana->siguiente()->lunes())->update(['estado' => 'aprobada']);
        $this->entrarPerfil('Luna');

        $this->assertSame(['asignadas' => 7, 'aprobadas' => 2, 'porcentaje' => 28.57], $this->progreso()['semana_actual']);
    }

    public function testInsigniaObtenida(): void
    {
        $insignia = $this->db->table('insignias')->where('codigo', 'misiones_10')->get()->getRowArray();
        $this->db->table('perfil_insignias')->insert(['perfil_id' => $this->perfilId('Luna'), 'insignia_id' => $insignia['id'], 'obtenida_en' => '2026-12-01 18:30:00']);
        $this->entrarPerfil('Luna');

        $insignias = array_column($this->progreso()['insignias'], 'obtenida_en', 'codigo');

        $this->assertSame('2026-12-01T18:30:00-07:00', $insignias['misiones_10']);
        $this->assertNull($insignias['primera_mision']);
    }

    public function testProgresoDeLaMetaFamiliar(): void
    {
        $meta = $this->db->table('metas_familiares')->get()->getRowArray();
        $this->movimiento('Luna', 'tarea', 40, 10, $meta['fecha_inicio'] . ' 00:00:00');
        $this->movimiento('Sol', 'tarea', 25, 11, date('Y-m-d H:i:s'));
        $this->movimiento('Sol', 'insignia', 20, 12, date('Y-m-d H:i:s'));
        $this->movimiento('Estrella', 'tarea', 70, 13, date('Y-m-d H:i:s', strtotime($meta['fecha_inicio'] . ' -1 second')));
        $this->entrarPerfil('Estrella');

        $this->assertSame(65, $this->progreso()['meta_familiar']['progreso_puntos'], 'Solo origen tarea, de todas, desde el inicio de la meta.');

        $this->db->table('metas_familiares')->update(['estado' => 'cumplida']);
        $this->assertNull($this->progreso()['meta_familiar']);
    }

    public function testRachaDeSemanasCumplidas(): void
    {
        $actual = SemanaIso::desdeFecha($this->hoy());
        $filas  = [
            [$actual, 1],                                       // semana en curso: no cuenta
            [$actual->anterior(), 1],
            [$actual->anterior()->anterior(), 1],
            // dos semanas atrás sin registro: se salta
            [$actual->anterior()->anterior()->anterior()->anterior(), 1],
            [$actual->anterior()->anterior()->anterior()->anterior()->anterior(), 0],
            [$actual->anterior()->anterior()->anterior()->anterior()->anterior()->anterior(), 1],
        ];

        foreach ($filas as [$semana, $cumplida]) {
            $this->db->table('rachas')->insert([
                'perfil_id' => $this->perfilId('Sol'), 'semana_iso' => $semana->codigo(), 'asignadas' => 10,
                'aprobadas' => $cumplida ? 9 : 2, 'porcentaje' => $cumplida ? 90 : 20, 'cumplida' => $cumplida, 'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $this->entrarPerfil('Sol');

        $this->assertSame(3, $this->progreso()['racha_semanas']);
    }

    public function testSinPerfilEs403(): void
    {
        $this->assertError($this->api('get', 'yo/progreso'), 403, 'SIN_PERFIL');
    }
}
