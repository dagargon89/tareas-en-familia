<?php

declare(strict_types=1);

namespace Tests\Feature\Juego;

use App\Domain\SemanaIso;
use App\Services\InsigniasService;
use App\Services\RachasService;
use Tests\Support\ApiTestCase;

/**
 * T-S4-02 · Rachas e insignias (RF-10, RF-11). CP-068, CP-069, CP-070 y CP-086.
 *
 * @internal
 */
final class InsigniasRachasTest extends ApiTestCase
{
    private SemanaIso $anterior;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sinTareasDemo();
        $this->anterior = SemanaIso::desdeFecha($this->hoy())->anterior();
        $this->iniciarSesionDemo();
        $this->entrarModoPadre();
    }

    /**
     * Instancias del perfil en la semana anterior: `$aprobadas` aprobadas de `$asignadas`.
     */
    private function semanaAnterior(string $apodo, int $asignadas, int $aprobadas, ?SemanaIso $semana = null): void
    {
        $dias    = ($semana ?? $this->anterior)->dias();
        $fechas  = [];
        $estados = [];

        for ($i = 0; $i < $asignadas; $i++) {
            $fechas[]  = $dias[$i % 7];
            $estados[] = $i < $aprobadas ? 'aprobada' : 'pendiente';
        }

        $this->insertarInstancias($apodo, $fechas, $estados);
    }

    private function cerrar(?SemanaIso $semana = null): void
    {
        (new RachasService(db_connect()))->cerrarSemana($semana ?? $this->anterior);
    }

    /**
     * @return array<string, mixed>
     */
    private function racha(string $apodo, ?SemanaIso $semana = null): array
    {
        return $this->db->table('rachas')->where(['perfil_id' => $this->perfilId($apodo), 'semana_iso' => ($semana ?? $this->anterior)->codigo()])->get()->getRowArray() ?? [];
    }

    public function testCierreSemanalInsertaFilaPorPerfilConInstancias(): void
    {
        $this->semanaAnterior('Luna', 10, 8);
        $this->semanaAnterior('Sol', 4, 1);

        $this->cerrar();

        $luna = $this->racha('Luna');
        $this->assertSame(['10', '8', '80.00', '1'], [(string) $luna['asignadas'], (string) $luna['aprobadas'], (string) $luna['porcentaje'], (string) $luna['cumplida']]);
        $this->assertSame('0', (string) $this->racha('Sol')['cumplida']);
        $this->assertSame([], $this->racha('Estrella'), 'Sin instancias no hay fila (doc 03 §4.1).');

        // Idempotente: cerrar otra vez no duplica.
        $this->cerrar();
        $this->assertSame(2, $this->db->table('rachas')->countAllResults());
    }

    public function testCuentaActividadesDeCeroPuntosYEscolares(): void
    {
        $dias = $this->anterior->dias();
        $ids  = $this->insertarInstancias('Sol', [$dias[0], $dias[1]], 'aprobada', 0);
        $this->db->table('tareas')->whereIn('id', array_column($this->db->table('tarea_instancias')->whereIn('id', $ids)->get()->getResultArray(), 'tarea_id'))->update(['tipo' => 'actividad']);
        $this->insertarInstancias('Sol', [$dias[2]], 'pendiente');

        $this->cerrar();

        $this->assertSame(['3', '2'], [(string) $this->racha('Sol')['asignadas'], (string) $this->racha('Sol')['aprobadas']]);
    }

    /**
     * CP-068 · Romper la racha no crea movimiento negativo (RN-01).
     */
    public function testRomperRachaNoCreaMovimientoNegativo(): void
    {
        $this->semanaAnterior('Luna', 5, 5, $this->anterior->anterior());
        $this->cerrar($this->anterior->anterior());
        $this->semanaAnterior('Luna', 5, 1);

        $this->cerrar();

        $this->assertSame('0', (string) $this->racha('Luna')['cumplida']);
        $this->assertSame(0, $this->db->table('movimientos_puntos')->where('puntos <', 0)->countAllResults());
        $this->assertSame(0, $this->db->table('movimientos_puntos')->whereNotIn('origen', ['insignia'])->countAllResults());
        $this->entrarPerfil('Luna');
        $this->assertSame(0, $this->cuerpo($this->api('get', 'yo/progreso'))['racha_semanas']);
    }

    /**
     * CP-086 · Cambiar `racha_umbral` a 60: el siguiente cierre semanal usa 60.
     */
    public function testCambiarUmbralA60LoUsaElSiguienteCierre(): void
    {
        $this->semanaAnterior('Luna', 10, 6);
        $this->api('put', 'admin/configuracion', ['racha_umbral' => 60])->assertStatus(200);

        $this->cerrar();

        $this->assertSame('1', (string) $this->racha('Luna')['cumplida']);
    }

    public function testElCronCierraLaSemanaAnteriorConForzar(): void
    {
        $this->semanaAnterior('Luna', 2, 2);

        command('tareas:programadas --forzar');

        $this->assertNotSame([], $this->racha('Luna'));
    }

    /**
     * CP-069 · La primera aprobación otorga "Primera misión" y 20 XP.
     */
    public function testPrimeraAprobacionOtorgaPrimeraMision(): void
    {
        [$id] = $this->insertarInstancias('Luna', [$this->hoy()], 'hecha', 4);

        $resultado = $this->cuerpo($this->conEstado($this->api('post', "admin/instancias/{$id}/revision", ['decision' => 'aprobada']), 200));

        $this->assertSame(['primera_mision'], array_column($resultado['insignias_nuevas'], 'codigo'));
        $insignia = $resultado['insignias_nuevas'][0];
        $this->assertSame(['codigo', 'nombre', 'descripcion', 'icono', 'color', 'obtenida_en'], array_keys($insignia));
        $this->assertNotNull($insignia['obtenida_en']);

        $pi  = $this->db->table('perfil_insignias')->where('perfil_id', $this->perfilId('Luna'))->get()->getResultArray();
        $this->assertCount(1, $pi);
        $mov = $this->db->table('movimientos_puntos')->where(['origen' => 'insignia', 'origen_id' => $pi[0]['id']])->get()->getRowArray();
        $this->assertSame('20', (string) $mov['puntos']);

        $this->entrarPerfil('Luna');
        $this->assertSame(24, $this->cuerpo($this->api('get', 'yo/progreso'))['xp'], '4 de la tarea + 20 de la insignia.');
    }

    /**
     * CP-070 · Evaluar insignias dos veces: una sola fila en `perfil_insignias`.
     */
    public function testEvaluarDosVecesNoDuplica(): void
    {
        $this->insertarInstancias('Luna', [$this->hoy()], 'aprobada');
        $servicio = new InsigniasService(db_connect());

        $this->assertCount(1, $servicio->evaluar($this->perfilId('Luna')));
        $this->assertSame([], $servicio->evaluar($this->perfilId('Luna')));

        $this->assertSame(1, $this->db->table('perfil_insignias')->countAllResults());
        $this->assertSame(1, $this->db->table('movimientos_puntos')->where('origen', 'insignia')->countAllResults());
    }

    public function testInsigniasPorCantidadYTipo(): void
    {
        $fechas = array_fill(0, 10, $this->hoy());
        $ids    = $this->insertarInstancias('Sol', $fechas, 'aprobada');
        $tareas = array_column($this->db->table('tarea_instancias')->whereIn('id', array_slice($ids, 0, 9))->get()->getResultArray(), 'tarea_id');
        $this->db->table('tareas')->whereIn('id', $tareas)->update(['tipo' => 'escolar']);

        $nuevas = array_column((new InsigniasService(db_connect()))->evaluar($this->perfilId('Sol')), 'codigo');

        $this->assertEqualsCanonicalizing(['primera_mision', 'misiones_10'], $nuevas, '9 escolares no alcanzan las 10.');
    }

    public function testInsigniaDeRachaAlCerrarLaSemana(): void
    {
        $dos = $this->anterior->anterior();
        $this->semanaAnterior('Estrella', 3, 3, $dos);
        $this->cerrar($dos);
        $this->assertSame(0, $this->db->table('perfil_insignias')->join('insignias', 'insignias.id = perfil_insignias.insignia_id')->where('codigo', 'racha_2')->countAllResults());

        $this->semanaAnterior('Estrella', 3, 3);
        $this->cerrar();

        $codigos = array_column($this->db->table('perfil_insignias pi')->select('i.codigo')->join('insignias i', 'i.id = pi.insignia_id')->where('pi.perfil_id', $this->perfilId('Estrella'))->get()->getResultArray(), 'codigo');
        $this->assertContains('racha_2', $codigos);
        $this->assertNotContains('racha_4', $codigos);
    }
}
