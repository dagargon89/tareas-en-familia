<?php

declare(strict_types=1);

namespace Tests\Feature\Tareas;

use Tests\Support\ApiTestCase;

/**
 * T-S2-03 · Mi día y marcar hecha (RF-04, RF-05, RF-13): E-11, E-12.
 * CP-027 a CP-031 y CP-033.
 *
 * @internal
 */
final class MiDiaTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->sinTareasDemo();
        $this->iniciarSesionDemo();
        $this->entrarModoPadre();
    }

    /**
     * @return array<string, mixed>
     */
    private function dia(?string $fecha = null): array
    {
        $ruta = 'yo/dia' . ($fecha === null ? '' : "?fecha={$fecha}");

        return $this->cuerpo($this->conEstado($this->api('get', $ruta), 200));
    }

    private function marcar(int $instanciaId): \CodeIgniter\Test\TestResponse
    {
        return $this->api('post', "yo/instancias/{$instanciaId}/hecha");
    }

    /**
     * CP-027 · Ordena por hora y deja las de sin hora al final.
     */
    public function testOrdenaPorHoraYSinHoraAlFinal(): void
    {
        $sin   = $this->crearTarea('Luna', ['titulo' => 'Sin hora']);
        $tarde = $this->crearTarea('Luna', ['titulo' => 'Tarde'], ['hora' => '17:00']);
        $temp  = $this->crearTarea('Luna', ['titulo' => 'Temprano'], ['hora' => '08:00']);
        $this->entrarPerfil('Luna');

        $dia = $this->dia();

        $this->assertSame(['Temprano', 'Tarde', 'Sin hora'], array_column($dia['instancias'], 'titulo'));
        $this->assertSame(['08:00', '17:00', null], array_column($dia['instancias'], 'hora'));
        $this->assertSame($this->hoy(), $dia['fecha']);
        $this->assertTrue($dia['es_hoy']);
        $this->assertSame(['total' => 3, 'hechas' => 0, 'aprobadas' => 0], $dia['resumen']);
        unset($sin, $tarde, $temp);
    }

    public function testFormaDeLaInstancia(): void
    {
        $tarea = $this->crearTarea('Luna', ['titulo' => 'Regar', 'icono' => 'planta', 'puntos' => 4, 'requiere_foto' => true], ['hora' => '09:15']);
        $this->entrarPerfil('Luna');

        $instancia = $this->dia()['instancias'][0];

        $this->assertSame([
            'id'                 => (int) $this->instancia($tarea['id'], $this->hoy())['id'],
            'tarea_id'           => $tarea['id'],
            'perfil_id'          => $this->perfilId('Luna'),
            'fecha'              => $this->hoy(),
            'hora'               => '09:15',
            'fecha_entrega'      => null,
            'titulo'             => 'Regar',
            'icono'              => 'planta',
            'color'              => 'ambar', // color de la categoría "Casa"
            'tipo'               => 'casa',
            'materia'            => null,
            'puntos'             => 4,
            'estado'             => 'pendiente',
            'requiere_foto'      => true,
            'tiene_evidencia'    => false,
            'evidencia_id'       => null,
            'mensaje_devolucion' => null,
            'completada_en'      => null,
        ], $instancia);
    }

    /**
     * CP-028 · Solo instancias del perfil activo.
     */
    public function testNoVeInstanciasDeOtraHija(): void
    {
        $this->crearTarea('Luna', ['titulo' => 'De Luna']);
        $this->crearTarea('Sol', ['titulo' => 'De Sol']);
        $this->entrarPerfil('Luna');

        $this->assertSame(['De Luna'], array_column($this->dia()['instancias'], 'titulo'));
        $this->assertSame(['De Luna'], array_column(
            $this->cuerpo($this->api('get', 'yo/dia?perfil_id=' . $this->perfilId('Sol')))['instancias'],
            'titulo',
        ));
    }

    public function testRangoDeFechasPermitido(): void
    {
        $this->crearTarea('Luna');
        $this->entrarPerfil('Luna');

        $this->assertFalse($this->dia($this->hoy(7))['es_hoy']);
        $this->assertSame([], $this->dia($this->hoy(-7))['instancias']);

        foreach ([$this->hoy(8), $this->hoy(-8), '2026-02-30', 'mañana'] as $fecha) {
            $r = $this->api('get', 'yo/dia?fecha=' . urlencode($fecha));
            $this->assertError($r, 400, 'VALIDACION');
            $this->assertArrayHasKey('fecha', $this->cuerpo($r)['error']['campos']);
        }
    }

    public function testAsegurarGeneraSiElCronNoCorrio(): void
    {
        $tarea = $this->crearTarea('Luna');
        $this->db->table('tarea_instancias')->emptyTable();
        $this->entrarPerfil('Luna');

        $this->assertCount(1, $this->dia($this->hoy(3))['instancias']);
        $this->assertNotSame([], $this->instancia($tarea['id'], $this->hoy(3)));
    }

    public function testSinPerfilActivoEs403(): void
    {
        $this->assertError($this->api('get', 'yo/dia'), 403, 'SIN_PERFIL');
    }

    /**
     * CP-029 · Marcar hecha una instancia de hoy: 200, `hecha`, `completada_en` lleno, sin puntos.
     */
    public function testMarcarHechaHoy(): void
    {
        $tarea = $this->crearTarea('Luna', ['puntos' => 8]);
        $this->entrarPerfil('Luna');
        $id = (int) $this->instancia($tarea['id'], $this->hoy())['id'];

        $instancia = $this->cuerpo($this->conEstado($this->marcar($id), 200));

        $this->assertSame('hecha', $instancia['estado']);
        $this->assertSame($id, $instancia['id']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}-0[67]:00$/', $instancia['completada_en']);
        $this->assertSame(0, $this->db->table('movimientos_puntos')->countAllResults(), 'RN-03: marcar no acredita.');
        $this->assertSame(['total' => 1, 'hechas' => 1, 'aprobadas' => 0], $this->dia()['resumen']);
    }

    public function testMarcarHechaUnDiaPasadoYUnaDevuelta(): void
    {
        $tarea = $this->crearTarea('Luna', [], ['fecha_inicio' => $this->hoy(-1)]);
        (new \App\Services\GeneradorInstancias(db_connect()))->generar($this->hoy(-1), $this->hoy(-1));
        $ayer = $this->instancia($tarea['id'], $this->hoy(-1));
        $this->db->table('tarea_instancias')->where('id', $ayer['id'])->update(['estado' => 'devuelta', 'mensaje_devolucion' => 'Falta un poco']);
        $this->entrarPerfil('Luna');

        $this->assertSame('hecha', $this->cuerpo($this->conEstado($this->marcar((int) $ayer['id']), 200))['estado']);
        $this->assertError($this->marcar((int) $ayer['id']), 409, 'ESTADO_INVALIDO');
    }

    /**
     * CP-030 · Marcar hecha una instancia de mañana: 409 `ESTADO_INVALIDO`.
     */
    public function testMarcarHechaManana(): void
    {
        $tarea = $this->crearTarea('Luna');
        $this->entrarPerfil('Luna');

        $this->assertError($this->marcar((int) $this->instancia($tarea['id'], $this->hoy(1))['id']), 409, 'ESTADO_INVALIDO');
        $this->assertSame('pendiente', $this->instancia($tarea['id'], $this->hoy(1))['estado']);
    }

    /**
     * CP-031 · Marcar hecha una instancia de otro perfil: 403 `PROHIBIDO`.
     */
    public function testMarcarHechaDeOtroPerfil(): void
    {
        $tarea = $this->crearTarea('Sol');
        $this->entrarPerfil('Luna');

        $this->assertError($this->marcar((int) $this->instancia($tarea['id'], $this->hoy())['id']), 403, 'PROHIBIDO');
        $this->assertSame('pendiente', $this->instancia($tarea['id'], $this->hoy())['estado']);
        $this->assertError($this->marcar(999999), 404, 'NO_ENCONTRADO');
    }

    public function testActividadDeCeroPuntosQuedaAprobada(): void
    {
        $tarea = $this->crearTarea('Luna', ['tipo' => 'actividad', 'puntos' => 0], ['hora' => '17:00']);
        $this->entrarPerfil('Luna');

        $instancia = $this->cuerpo($this->conEstado($this->marcar((int) $this->instancia($tarea['id'], $this->hoy())['id']), 200));

        $this->assertSame('aprobada', $instancia['estado']);
        $this->assertNotNull($this->instancia($tarea['id'], $this->hoy())['aprobada_en']);
        // Desde el S5 la aprobación automática evalúa insignias ("Primera misión"), pero la tarea no acredita puntos.
        $this->assertSame(0, $this->db->table('movimientos_puntos')->where('origen', 'tarea')->countAllResults());
        $this->assertSame(['total' => 1, 'hechas' => 1, 'aprobadas' => 1], $this->dia()['resumen']);
    }

    /**
     * Desde el S3 la foto es obligatoria si la tarea la pide: un campo `foto` de texto no cuenta.
     */
    public function testCampoFotoDeTextoNoCuentaComoFoto(): void
    {
        $tarea = $this->crearTarea('Luna', ['requiere_foto' => true]);
        $this->entrarPerfil('Luna');
        $id = (int) $this->instancia($tarea['id'], $this->hoy())['id'];

        $r = $this->withSession($this->sesion)->withHeaders(['X-CSRF-TOKEN' => csrf_hash()])
            ->post("api/v1/yo/instancias/{$id}/hecha", ['foto' => 'no-es-un-archivo']);

        $this->assertError($r, 400, 'VALIDACION');
        $this->assertArrayHasKey('foto', $this->cuerpo($r)['error']['campos']);
        $this->assertSame(0, $this->db->table('evidencias')->countAllResults());
        $this->assertSame('pendiente', $this->instancia($tarea['id'], $this->hoy())['estado']);
    }

    /**
     * CP-033 · Escolar creada hoy con entrega en 3 días: aparece hoy, mañana y pasado; desaparece al aprobarse.
     */
    public function testEscolarAparecHastaLaEntregaODesapareceAlAprobarse(): void
    {
        $tarea = $this->crearTarea('Sol', ['tipo' => 'escolar', 'materia' => 'Ciencias', 'fecha_entrega' => $this->hoy(3), 'puntos' => 10], ['frecuencia' => 'una_vez']);
        $this->entrarPerfil('Sol');

        foreach ([0, 1, 2, 3] as $dias) {
            $instancias = $this->dia($this->hoy($dias))['instancias'];
            $this->assertSame([$tarea['id']], array_column($instancias, 'tarea_id'), "Día +{$dias}");
            $this->assertSame('Ciencias', $instancias[0]['materia']);
            $this->assertSame($this->hoy(3), $instancias[0]['fecha_entrega']);
            $this->assertSame($this->hoy(), $instancias[0]['fecha'], 'Es la misma instancia del día de creación.');
        }

        $this->assertSame([], $this->dia($this->hoy(4))['instancias'], 'Después de la entrega ya no aparece.');

        // Se puede marcar desde un día posterior (la instancia es de hoy, no futura).
        $this->db->table('tarea_instancias')->where('tarea_id', $tarea['id'])->update(['estado' => 'aprobada']);

        $this->assertSame([], $this->dia($this->hoy(1))['instancias']);
        $this->assertSame([], $this->dia($this->hoy(2))['instancias']);
        $this->assertSame('aprobada', $this->dia($this->hoy())['instancias'][0]['estado'], 'El día de creación conserva su historial.');
    }

    public function testEscolarDesactivadaNoSeArrastra(): void
    {
        $tarea = $this->crearTarea('Sol', ['tipo' => 'escolar', 'materia' => 'Arte', 'fecha_entrega' => $this->hoy(3)], ['frecuencia' => 'una_vez']);
        $this->db->table('tareas')->where('id', $tarea['id'])->update(['activa' => 0]);
        $this->entrarPerfil('Sol');

        $this->assertSame([], $this->dia($this->hoy(1))['instancias']);
    }
}
