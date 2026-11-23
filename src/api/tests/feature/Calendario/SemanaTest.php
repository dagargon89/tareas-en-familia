<?php

declare(strict_types=1);

namespace Tests\Feature\Calendario;

use App\Domain\SemanaIso;
use Tests\Support\ApiTestCase;

/**
 * T-S5-01 · Calendario semanal (RF-14): E-17 y E-46, CP-080 y CP-081.
 *
 * @internal
 */
final class SemanaTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->sinTareasDemo();
        $this->iniciarSesionDemo();
        $this->entrarModoPadre();
    }

    private function lunes(int $semanas = 0): string
    {
        $semana = SemanaIso::desdeFecha($this->hoy());

        for ($i = 0; $i < abs($semanas); $i++) {
            $semana = $semanas > 0 ? $semana->siguiente() : $semana->anterior();
        }

        return $semana->lunes();
    }

    /**
     * @return array<string, mixed>
     */
    private function semana(string $ruta): array
    {
        return $this->cuerpo($this->conEstado($this->api('get', $ruta), 200));
    }

    /**
     * Instancias de la semana por fecha: `[fecha => [titulos]]`.
     *
     * @param array<string, mixed> $semana
     *
     * @return array<string, list<string>>
     */
    private function porDia(array $semana): array
    {
        $dias = [];

        foreach ($semana['dias'] as $dia) {
            $dias[$dia['fecha']] = array_column($dia['instancias'], 'titulo');
        }

        return $dias;
    }

    /**
     * CP-080 · `GET /yo/semana`: 7 días, de lunes a domingo, con sus instancias.
     */
    public function testSemanaDeLaHijaConSieteDias(): void
    {
        $this->crearTarea('Sol', ['titulo' => 'Tarde'], ['hora' => '18:00']);
        $this->crearTarea('Sol', ['titulo' => 'Temprano'], ['hora' => '07:30']);
        $this->crearTarea('Sol', ['titulo' => 'Sin hora']);
        $this->crearTarea('Luna', ['titulo' => 'De Luna']);
        $this->entrarPerfil('Sol');

        $semana = $this->semana('yo/semana?desde=' . $this->lunes());

        $this->assertSame(['desde', 'hasta', 'dias'], array_keys($semana));
        $this->assertSame([$this->lunes(), SemanaIso::desdeFecha($this->hoy())->domingo()], [$semana['desde'], $semana['hasta']]);
        $this->assertCount(7, $semana['dias']);
        $this->assertSame(SemanaIso::desdeFecha($this->hoy())->dias(), array_column($semana['dias'], 'fecha'));
        $this->assertSame(['fecha', 'es_hoy', 'instancias', 'resumen'], array_keys($semana['dias'][0]));

        foreach ($semana['dias'] as $dia) {
            $this->assertSame($dia['fecha'] === $this->hoy(), $dia['es_hoy']);
            $esperadas = $dia['fecha'] >= $this->hoy() ? ['Temprano', 'Tarde', 'Sin hora'] : [];
            $this->assertSame($esperadas, array_column($dia['instancias'], 'titulo'), "Día {$dia['fecha']}");
            $this->assertSame(count($esperadas), $dia['resumen']['total']);
        }
    }

    public function testDesdeSinValorOEnMitadDeSemana(): void
    {
        $this->entrarPerfil('Sol');

        $this->assertSame($this->lunes(), $this->semana('yo/semana')['desde']);
        $this->assertSame($this->lunes(-1), $this->semana('yo/semana?desde=' . SemanaIso::desdeFecha($this->lunes(-1))->dias()[3])['desde']);
        $this->assertError($this->api('get', 'yo/semana?desde=2026-02-30'), 400, 'VALIDACION');
    }

    public function testEscolarSoloAparecesEnSuDiaConSuEntrega(): void
    {
        $this->entrarPerfil('Sol');
        $this->conEstado($this->api('post', 'yo/tareas', [
            'titulo' => 'Maqueta', 'icono' => 'ciencia', 'tipo' => 'escolar', 'materia' => 'Ciencias',
            'fecha_entrega' => $this->hoy(3), 'requiere_foto' => false,
            'recurrencia' => ['frecuencia' => 'una_vez', 'dias_semana' => null, 'hora' => null, 'fecha_inicio' => $this->hoy(), 'fecha_fin' => null],
        ]), 201);

        $semana = $this->semana('yo/semana?desde=' . $this->lunes());
        $todas  = array_merge(...array_column($semana['dias'], 'instancias'));

        $this->assertCount(1, $todas, 'La escolar no se repite en cada día hasta su entrega');
        $this->assertSame([$this->hoy(), $this->hoy(3)], [$todas[0]['fecha'], $todas[0]['fecha_entrega']]);
    }

    public function testSemanaSiguienteGeneraLasInstanciasQueFaltan(): void
    {
        $this->crearTarea('Sol', ['titulo' => 'Diaria']);
        $this->db->table('tarea_instancias')->where('fecha >', $this->hoy())->delete();
        $this->entrarPerfil('Sol');

        $dias = $this->porDia($this->semana('yo/semana?desde=' . $this->lunes(1)));

        foreach ($dias as $fecha => $titulos) {
            $esperado = $fecha <= $this->hoy(7) ? ['Diaria'] : [];
            $this->assertSame($esperado, $titulos, "Día {$fecha}: se generan hasta 7 días adelante, como en Mi día");
        }
    }

    /**
     * CP-081 · Actividad de 0 puntos marcada hecha: pasa a `aprobada` sin revisión del padre.
     */
    public function testActividadSinPuntosSeApruebaSola(): void
    {
        $tarea = $this->crearTarea('Luna', ['titulo' => 'Natación', 'tipo' => 'actividad', 'puntos' => 0, 'icono' => 'nadar'], ['hora' => '17:00']);
        $id    = (int) $this->instancia($tarea['id'], $this->hoy())['id'];
        $this->entrarPerfil('Luna');

        $instancia = $this->cuerpo($this->conEstado($this->api('post', "yo/instancias/{$id}/hecha"), 200));

        $this->assertSame('aprobada', $instancia['estado']);
        $this->assertSame(0, $this->db->table('revisiones')->where('instancia_id', $id)->countAllResults());
        $this->assertSame(0, $this->db->table('movimientos_puntos')->where('origen', 'tarea')->countAllResults());

        $hoy = array_values(array_filter($this->semana('yo/semana')['dias'], fn (array $d): bool => $d['fecha'] === $this->hoy()))[0];
        $this->assertSame(['Natación', 'aprobada', '17:00'], [$hoy['instancias'][0]['titulo'], $hoy['instancias'][0]['estado'], $hoy['instancias'][0]['hora']]);

        $this->entrarModoPadre();
        $this->assertSame([], $this->cuerpo($this->api('get', 'admin/aprobaciones')), 'No pasa por la bandeja');
    }

    public function testAprobacionAutomaticaTambienEvaluaInsignias(): void
    {
        $tarea = $this->crearTarea('Luna', ['tipo' => 'actividad', 'puntos' => 0]);
        $id    = (int) $this->instancia($tarea['id'], $this->hoy())['id'];
        $this->entrarPerfil('Luna');

        $this->conEstado($this->api('post', "yo/instancias/{$id}/hecha"), 200);

        $this->assertSame(1, $this->db->table('perfil_insignias')->where('perfil_id', $this->perfilId('Luna'))->countAllResults(), '"Primera misión"');
    }

    // ===== E-46 =====

    public function testSemanaDelPadreDeTodasODeUna(): void
    {
        $this->crearTarea('Sol', ['titulo' => 'De Sol'], ['hora' => '08:00']);
        $this->crearTarea('Luna', ['titulo' => 'De Luna'], ['hora' => '07:00']);
        $this->crearTarea('Estrella', ['titulo' => 'De Estrella']);
        $this->db->table('perfiles')->where('apodo', 'Estrella')->update(['activo' => 0]);

        $todas = $this->porDia($this->semana('admin/semana?desde=' . $this->lunes()));
        $this->assertSame(['De Luna', 'De Sol'], $todas[$this->hoy()], 'Todas las hijas activas, por hora');

        $sol = $this->semana('admin/semana?desde=' . $this->lunes() . '&perfil_id=' . $this->perfilId('Sol'));
        $this->assertSame(['De Sol'], $this->porDia($sol)[$this->hoy()]);
        $this->assertSame([$this->perfilId('Sol')], array_values(array_unique(array_column(array_merge(...array_column($sol['dias'], 'instancias')), 'perfil_id'))));

        $this->assertError($this->api('get', 'admin/semana?perfil_id=999999'), 404, 'NO_ENCONTRADO');
        $this->assertError($this->api('get', 'admin/semana?perfil_id=uno'), 400, 'VALIDACION');
    }

    public function testSemanaDelPadreRequiereModoPadre(): void
    {
        $this->api('delete', 'modo-padre')->assertStatus(200);

        $this->assertError($this->api('get', 'admin/semana'), 403, 'MODO_PADRE_REQUERIDO');
    }
}
