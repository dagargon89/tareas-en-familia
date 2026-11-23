<?php

declare(strict_types=1);

namespace Tests\Feature\Padre;

use App\Domain\SemanaIso;
use Tests\Support\ApiTestCase;

/**
 * T-S5-03 · Panel del padre (RF-16): E-45 y CP-085.
 *
 * @internal
 */
final class PanelTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->sinTareasDemo();
        $this->iniciarSesionDemo();
        $this->entrarModoPadre();
    }

    private function semana(): SemanaIso
    {
        return SemanaIso::desdeFecha($this->hoy());
    }

    /**
     * Tarea sin instancias generadas (empieza en un mes) para colgarle instancias a mano.
     */
    private function tareaVacia(string $apodo, string $tipo = 'casa'): int
    {
        $tarea = $this->crearTarea($apodo, ['titulo' => "Apoyo {$apodo} {$tipo}"], ['frecuencia' => 'una_vez', 'fecha_inicio' => $this->hoy(30)]);
        $this->db->table('tareas')->where('id', $tarea['id'])->update(['tipo' => $tipo]);

        return (int) $tarea['id'];
    }

    private function instanciaEn(int $tareaId, string $apodo, string $fecha, string $estado, ?string $entrega = null): void
    {
        $ahora = date('Y-m-d H:i:s');
        $this->db->table('tarea_instancias')->insert([
            'tarea_id' => $tareaId, 'perfil_id' => $this->perfilId($apodo), 'fecha' => $fecha, 'fecha_entrega' => $entrega,
            'puntos' => 5, 'estado' => $estado, 'completada_en' => $estado === 'pendiente' ? null : $ahora,
            'created_at' => $ahora, 'updated_at' => $ahora,
        ]);
    }

    private function racha(string $apodo, SemanaIso $semana): void
    {
        $this->db->table('rachas')->insert([
            'perfil_id' => $this->perfilId($apodo), 'semana_iso' => $semana->codigo(), 'asignadas' => 4, 'aprobadas' => 4,
            'porcentaje' => 100, 'cumplida' => 1, 'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function panel(string $consulta = ''): array
    {
        return $this->cuerpo($this->conEstado($this->api('get', 'admin/panel' . $consulta), 200));
    }

    /**
     * CP-085 · Con datos de demo: porcentaje de la semana, pendientes por aprobar y racha correctos por hija.
     */
    public function testPanelConDatosDeDemo(): void
    {
        $dias = $this->semana()->dias();

        // Luna: 4 instancias esta semana (3 aprobadas, 1 hecha), otra hecha de la semana pasada y racha de 2.
        $luna = $this->tareaVacia('Luna');
        $this->instanciaEn($luna, 'Luna', $dias[0], 'aprobada');
        $this->instanciaEn($luna, 'Luna', $dias[1], 'aprobada');
        $this->instanciaEn($luna, 'Luna', $dias[2], 'aprobada');
        $this->instanciaEn($luna, 'Luna', $dias[3], 'hecha');
        $this->instanciaEn($luna, 'Luna', $this->semana()->anterior()->dias()[4], 'hecha');
        $this->racha('Luna', $this->semana()->anterior());
        $this->racha('Luna', $this->semana()->anterior()->anterior());

        // Sol: sin instancias; 100 puntos y un canje pedido de 30.
        $this->db->table('movimientos_puntos')->insert([
            'perfil_id' => $this->perfilId('Sol'), 'origen' => 'tarea', 'origen_id' => 9001, 'puntos' => 100, 'descripcion' => 'Prueba', 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $recompensa = (int) $this->db->table('recompensas')->get()->getRowArray()['id'];
        $this->db->table('canjes')->insert([
            'perfil_id' => $this->perfilId('Sol'), 'recompensa_id' => $recompensa, 'costo' => 30, 'estado' => 'solicitado',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Estrella: escolares con entrega (de semanas pasadas para no contar en su porcentaje).
        $escolar = $this->tareaVacia('Estrella', 'escolar');
        $this->instanciaEn($escolar, 'Estrella', $this->hoy(-20), 'pendiente', $this->hoy(5));
        $this->instanciaEn($escolar, 'Estrella', $this->hoy(-21), 'hecha', $this->hoy(2));
        $this->instanciaEn($escolar, 'Estrella', $this->hoy(-22), 'aprobada', $this->hoy(1));
        $this->instanciaEn($escolar, 'Estrella', $this->hoy(-23), 'pendiente', $this->hoy(-1));

        $panel = $this->panel();

        $this->assertSame(['semana_iso', 'hijas', 'meta_familiar', 'canjes_pendientes'], array_keys($panel));
        $this->assertSame($this->semana()->codigo(), $panel['semana_iso']);
        $this->assertSame(['Luna', 'Sol', 'Estrella'], array_column(array_column($panel['hijas'], 'perfil'), 'apodo'), 'En el orden de los perfiles, nunca por desempeño (RN-02)');
        $this->assertSame(['perfil', 'porcentaje_semana', 'por_aprobar', 'racha_semanas', 'disponible', 'proximas_entregas'], array_keys($panel['hijas'][0]));

        [$l, $s, $e] = $panel['hijas'];
        $this->assertSame([75, 2, 2, 0], [$l['porcentaje_semana'], $l['por_aprobar'], $l['racha_semanas'], $l['disponible']]);
        $this->assertSame([0, 0, 0, 70], [$s['porcentaje_semana'], $s['por_aprobar'], $s['racha_semanas'], $s['disponible']]);
        $this->assertSame([0, 1], [$e['porcentaje_semana'], $e['por_aprobar']]);
        $this->assertSame([$this->hoy(2), $this->hoy(5)], array_column($e['proximas_entregas'], 'fecha_entrega'), 'Sin la aprobada ni la vencida, por fecha de entrega');
        $this->assertSame('escolar', $e['proximas_entregas'][0]['tipo']);

        $this->assertSame('activa', $panel['meta_familiar']['estado']);
        $this->assertSame([30], array_column($panel['canjes_pendientes'], 'costo'));
        $this->assertSame('Sol', $this->db->table('perfiles')->where('id', $panel['canjes_pendientes'][0]['perfil_id'])->get()->getRowArray()['apodo']);
    }

    public function testPorcentajeConDecimalesYSemanaPedida(): void
    {
        $luna = $this->tareaVacia('Luna');
        $pasada = $this->semana()->anterior();
        $this->instanciaEn($luna, 'Luna', $pasada->dias()[0], 'aprobada');
        $this->instanciaEn($luna, 'Luna', $pasada->dias()[1], 'aprobada');
        $this->instanciaEn($luna, 'Luna', $pasada->dias()[2], 'pendiente');

        $panel = $this->panel('?semana=' . $pasada->codigo());

        $this->assertSame($pasada->codigo(), $panel['semana_iso']);
        $this->assertSame(66.67, $panel['hijas'][0]['porcentaje_semana']);
        $this->assertSame(0, $this->panel()['hijas'][0]['porcentaje_semana'], 'Sin `semana` es la semana en curso');
        $this->assertError($this->api('get', 'admin/panel?semana=2026-W99'), 400, 'VALIDACION');
    }

    public function testSoloHijasActivasYSinMeta(): void
    {
        $this->db->table('perfiles')->where('apodo', 'Sol')->update(['activo' => 0]);
        $this->db->table('metas_familiares')->update(['estado' => 'cancelada']);

        $panel = $this->panel();

        $this->assertSame(['Luna', 'Estrella'], array_column(array_column($panel['hijas'], 'perfil'), 'apodo'));
        $this->assertNull($panel['meta_familiar']);
        $this->assertSame([], $panel['canjes_pendientes']);
    }

    public function testRequiereModoPadre(): void
    {
        $this->api('delete', 'modo-padre')->assertStatus(200);

        $this->assertError($this->api('get', 'admin/panel'), 403, 'MODO_PADRE_REQUERIDO');
    }
}
