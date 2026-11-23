<?php

declare(strict_types=1);

namespace Tests\Feature\Juego;

use App\Domain\SemanaIso;
use App\Services\InsigniasService;
use App\Services\RachasService;
use Tests\Support\ApiTestCase;

/**
 * T-S4-02 · Rachas e insignias (RF-10, RF-11): cierre semanal, CP-068 a CP-070.
 *
 * @internal
 */
final class RachasInsigniasTest extends ApiTestCase
{
    /**
     * Tareas de apoyo por perfil (una por cada 7 instancias que se insertan en una semana).
     *
     * @var array<string, list<int>>
     */
    private array $tareas = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->sinTareasDemo();
        $this->iniciarSesionDemo();
        $this->entrarModoPadre();
    }

    private function semanaPasada(int $atras = 1): SemanaIso
    {
        $semana = SemanaIso::desdeFecha($this->hoy());

        for ($i = 0; $i < $atras; $i++) {
            $semana = $semana->anterior();
        }

        return $semana;
    }

    private function tareaDeApoyo(string $apodo, int $indice, string $tipo = 'casa'): int
    {
        $clave = "{$apodo}:{$tipo}";

        while (count($this->tareas[$clave] ?? []) <= $indice) {
            $tarea = $this->crearTarea($apodo, ['titulo' => "Apoyo {$tipo} " . count($this->tareas[$clave] ?? [])]);
            $this->db->table('tareas')->where('id', $tarea['id'])->update(['tipo' => $tipo]);
            $this->tareas[$clave][] = (int) $tarea['id'];
        }

        return $this->tareas[$clave][$indice];
    }

    /**
     * Inserta `$asignadas` instancias del perfil en la semana; las primeras `$aprobadas` quedan `aprobada`.
     */
    private function semana(string $apodo, SemanaIso $semana, int $asignadas, int $aprobadas, string $tipo = 'casa'): void
    {
        $ahora = date('Y-m-d H:i:s');

        for ($i = 0; $i < $asignadas; $i++) {
            $this->db->table('tarea_instancias')->insert([
                'tarea_id'   => $this->tareaDeApoyo($apodo, intdiv($i, 7), $tipo),
                'perfil_id'  => $this->perfilId($apodo),
                'fecha'      => $semana->dias()[$i % 7],
                'puntos'     => 5,
                'estado'     => $i < $aprobadas ? 'aprobada' : 'pendiente',
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    private function filaRacha(string $apodo, SemanaIso $semana, bool $cumplida): void
    {
        $this->db->table('rachas')->insert([
            'perfil_id' => $this->perfilId($apodo), 'semana_iso' => $semana->codigo(), 'asignadas' => 5,
            'aprobadas' => $cumplida ? 5 : 0, 'porcentaje' => $cumplida ? 100 : 0, 'cumplida' => $cumplida ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function cerrar(SemanaIso $semana): void
    {
        (new RachasService(db_connect()))->cerrarSemana($semana);
    }

    private function racha(string $apodo): int
    {
        $this->entrarPerfil($apodo);

        return $this->cuerpo($this->conEstado($this->api('get', 'yo/progreso'), 200))['racha_semanas'];
    }

    /**
     * @return list<string>
     */
    private function insigniasDe(string $apodo): array
    {
        return array_column(
            $this->db->table('perfil_insignias pi')->select('i.codigo')->join('insignias i', 'i.id = pi.insignia_id')
                ->where('pi.perfil_id', $this->perfilId($apodo))->orderBy('i.orden')->get()->getResultArray(),
            'codigo',
        );
    }

    // ===== Cierre semanal (RF-10) =====

    public function testCierreGuardaUnaFilaPorPerfilConInstancias(): void
    {
        $semana = $this->semanaPasada();
        $this->semana('Luna', $semana, 10, 8);
        $this->semana('Sol', $semana, 4, 1);

        $this->cerrar($semana);
        $this->cerrar($semana);

        $luna = $this->db->table('rachas')->where('perfil_id', $this->perfilId('Luna'))->get()->getResultArray();
        $this->assertCount(1, $luna, 'Cerrar dos veces no duplica');
        $this->assertSame([$semana->codigo(), 10, 8, '80.00', 1], [
            $luna[0]['semana_iso'], (int) $luna[0]['asignadas'], (int) $luna[0]['aprobadas'], $luna[0]['porcentaje'], (int) $luna[0]['cumplida'],
        ]);
        $this->assertSame(0, (int) $this->db->table('rachas')->where('perfil_id', $this->perfilId('Sol'))->get()->getRowArray()['cumplida']);
        $this->assertSame(0, $this->db->table('rachas')->where('perfil_id', $this->perfilId('Estrella'))->countAllResults(), 'Sin instancias no hay fila');
        $this->assertSame(1, $this->racha('Luna'));
    }

    /**
     * CP-068 · Romper la racha la deja en 0 y no crea movimientos negativos (RN-01).
     */
    public function testRomperRachaNoCreaMovimientoNegativo(): void
    {
        $this->filaRacha('Luna', $this->semanaPasada(3), true);
        $this->filaRacha('Luna', $this->semanaPasada(2), true);
        $this->semana('Luna', $this->semanaPasada(), 10, 0);
        $antes = $this->db->table('movimientos_puntos')->countAllResults();

        $this->cerrar($this->semanaPasada());

        $this->assertSame(0, $this->racha('Luna'));
        $this->assertSame($antes, $this->db->table('movimientos_puntos')->countAllResults());
        $this->assertSame(0, $this->db->table('movimientos_puntos')->where('puntos <', 0)->countAllResults());
    }

    public function testCierreQueLlegaADosSemanasOtorgaLaInsigniaDeRacha(): void
    {
        $this->filaRacha('Luna', $this->semanaPasada(3), true);
        $this->filaRacha('Luna', $this->semanaPasada(2), false);
        $this->filaRacha('Luna', $this->semanaPasada(4), false);
        $this->filaRacha('Sol', $this->semanaPasada(2), true);
        $this->semana('Luna', $this->semanaPasada(), 5, 5);

        $this->cerrar($this->semanaPasada());

        $this->assertSame(1, $this->racha('Luna'));
        $this->assertNotContains('racha_2', $this->insigniasDe('Luna'));

        $this->db->table('rachas')->where('perfil_id', $this->perfilId('Luna'))->where('semana_iso', $this->semanaPasada(2)->codigo())->update(['cumplida' => 1]);
        $this->db->table('rachas')->where('semana_iso', $this->semanaPasada()->codigo())->delete();
        $this->cerrar($this->semanaPasada());

        $this->assertSame(3, $this->racha('Luna'));
        $this->assertContains('racha_2', $this->insigniasDe('Luna'));
        $this->assertNotContains('racha_4', $this->insigniasDe('Luna'));
        $this->assertSame([], $this->insigniasDe('Sol'), 'Sol no tuvo instancias la semana pasada: no se evalúa');
    }

    public function testElCronCierraLaSemanaAnterior(): void
    {
        $this->semana('Luna', $this->semanaPasada(), 5, 5);

        command('tareas:programadas --forzar');

        $fila = $this->db->table('rachas')->where('perfil_id', $this->perfilId('Luna'))->get()->getRowArray();
        $this->assertSame($this->semanaPasada()->codigo(), $fila['semana_iso'] ?? null);
    }

    // ===== Insignias (RF-11) =====

    /**
     * CP-069 · La primera aprobación otorga "Primera misión" y 20 XP.
     */
    public function testPrimeraAprobacionOtorgaPrimeraMision(): void
    {
        $tarea = $this->crearTarea('Luna', ['puntos' => 7]);
        $id    = (int) $this->instancia($tarea['id'], $this->hoy())['id'];
        $this->db->table('tarea_instancias')->where('id', $id)->update(['estado' => 'hecha', 'completada_en' => date('Y-m-d H:i:s')]);

        $resultado = $this->cuerpo($this->conEstado($this->api('post', "admin/instancias/{$id}/revision", ['decision' => 'aprobada']), 200));

        $this->assertSame(7, $resultado['puntos_acreditados']);
        $this->assertCount(1, $resultado['insignias_nuevas']);
        $insignia = $resultado['insignias_nuevas'][0];
        $this->assertSame(['codigo', 'nombre', 'descripcion', 'icono', 'color', 'obtenida_en'], array_keys($insignia));
        $this->assertSame(['primera_mision', 'Primera misión'], [$insignia['codigo'], $insignia['nombre']]);
        $this->assertNotNull($insignia['obtenida_en']);

        $fila = $this->db->table('perfil_insignias')->where('perfil_id', $this->perfilId('Luna'))->get()->getRowArray();
        $movimiento = $this->db->table('movimientos_puntos')->where(['origen' => 'insignia', 'origen_id' => $fila['id']])->get()->getRowArray();
        $this->assertSame(20, (int) $movimiento['puntos']);
        $this->assertSame($this->perfilId('Luna'), (int) $movimiento['perfil_id']);

        $this->entrarPerfil('Luna');
        $progreso = $this->cuerpo($this->api('get', 'yo/progreso'));
        $this->assertSame(27, $progreso['xp']);
        $this->assertNotNull($progreso['insignias'][0]['obtenida_en']);
    }

    /**
     * CP-070 · Evaluar dos veces deja una sola fila y un solo movimiento.
     */
    public function testEvaluarInsigniasDosVecesUnaSolaFila(): void
    {
        $this->semana('Sol', $this->semanaPasada(), 1, 1);
        $insignias = new InsigniasService(db_connect());

        $primera  = $insignias->evaluar($this->perfilId('Sol'));
        $segunda  = $insignias->evaluar($this->perfilId('Sol'));

        $this->assertSame(['primera_mision'], array_column($primera, 'codigo'));
        $this->assertSame([], $segunda);
        $this->assertSame(1, $this->db->table('perfil_insignias')->countAllResults());
        $this->assertSame(1, $this->db->table('movimientos_puntos')->where('origen', 'insignia')->countAllResults());
    }

    public function testInsigniasPorCantidadYPorTipo(): void
    {
        $this->semana('Estrella', $this->semanaPasada(), 10, 10, 'escolar');
        $this->semana('Estrella', $this->semanaPasada(2), 9, 9, 'casa');

        $nuevas = (new InsigniasService(db_connect()))->evaluar($this->perfilId('Estrella'));

        $this->assertSame(['primera_mision', 'misiones_10', 'escolares_10'], array_column($nuevas, 'codigo'));
        $this->assertSame(60, (int) $this->db->table('movimientos_puntos')->selectSum('puntos')->where('origen', 'insignia')->get()->getRowArray()['puntos']);
    }
}
