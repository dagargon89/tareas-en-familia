<?php

declare(strict_types=1);

namespace Tests\Feature\Tareas;

use Tests\Support\ApiTestCase;

/**
 * T-S2-02 · CRUD de tareas del padre (RF-03, RF-13): E-28 a E-32.
 * CP-024, CP-025, CP-026 y CP-SEG-05.
 *
 * @internal
 */
final class AdminTareasTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarSesionDemo();
        $this->entrarModoPadre();
    }

    public function testListaTareasConRecurrenciaAnidada(): void
    {
        $tareas = $this->cuerpo($this->conEstado($this->api('get', 'admin/tareas'), 200));

        $this->assertCount(12, $tareas);
        $mesa = array_values(array_filter($tareas, static fn (array $t): bool => $t['titulo'] === 'Poner la mesa'))[0];
        $this->assertSame([
            'id', 'perfil_id', 'categoria_id', 'titulo', 'icono', 'puntos', 'tipo', 'materia', 'fecha_entrega',
            'requiere_foto', 'creada_por', 'activa', 'recurrencia',
        ], array_keys($mesa));
        $this->assertSame($this->perfilId('Sol'), $mesa['perfil_id']);
        $this->assertSame(5, $mesa['puntos']);
        $this->assertFalse($mesa['requiere_foto']);
        $this->assertTrue($mesa['activa']);
        $this->assertSame('padre', $mesa['creada_por']);
        $this->assertSame([
            'frecuencia' => 'semanal', 'dias_semana' => ['lu', 'ma', 'mi', 'ju', 'vi'], 'hora' => null,
            'fecha_inicio' => $this->hoy(), 'fecha_fin' => null,
        ], $mesa['recurrencia']);

        $natacion = array_values(array_filter($tareas, static fn (array $t): bool => $t['titulo'] === 'Clase de natación'))[0];
        $this->assertSame('17:00', $natacion['recurrencia']['hora']);

        $escolar = array_values(array_filter($tareas, static fn (array $t): bool => $t['titulo'] === 'Tarea de matemáticas'))[0];
        $this->assertSame('Matemáticas', $escolar['materia']);
        $this->assertSame($this->hoy(3), $escolar['fecha_entrega']);
        $this->assertNull($escolar['recurrencia']['dias_semana']);
    }

    public function testFiltrosPorPerfilYActiva(): void
    {
        $sol = $this->perfilId('Sol');
        $this->db->table('tareas')->where('titulo', 'Poner la mesa')->update(['activa' => 0]);

        $deSol = $this->cuerpo($this->api('get', "admin/tareas?perfil_id={$sol}"));
        $this->assertCount(4, $deSol);
        $this->assertSame([$sol], array_values(array_unique(array_column($deSol, 'perfil_id'))));

        $inactivas = $this->cuerpo($this->api('get', 'admin/tareas?activa=false'));
        $this->assertSame(['Poner la mesa'], array_column($inactivas, 'titulo'));

        $this->assertCount(3, $this->cuerpo($this->api('get', "admin/tareas?perfil_id={$sol}&activa=true")));
    }

    public function testCrearTareaGeneraInstanciasDeHoyMasSieteDias(): void
    {
        $this->sinTareasDemo();

        $tarea = $this->crearTarea('Luna', ['puntos' => 7, 'requiere_foto' => true], ['hora' => '07:30']);

        $this->assertIsInt($tarea['id']);
        $this->assertSame('casa', $tarea['tipo']);
        $this->assertTrue($tarea['requiere_foto']);
        $this->assertSame('07:30', $tarea['recurrencia']['hora']);
        $this->assertSame('padre', $tarea['creada_por']);
        $fila = $this->fila('tareas', $tarea['id']);
        $this->assertNotNull($fila['creada_por_usuario_id']);

        $instancias = $this->db->table('tarea_instancias')->where('tarea_id', $tarea['id'])->orderBy('fecha')->get()->getResultArray();
        $this->assertSame($this->hoy(), $instancias[0]['fecha']);
        $this->assertSame($this->hoy(7), end($instancias)['fecha']);
        $this->assertCount(8, $instancias);
        $this->assertSame('07:30:00', $instancias[0]['hora']);
        $this->assertSame('7', (string) $instancias[0]['puntos']);
        $this->assertSame('pendiente', $instancias[0]['estado']);
    }

    /**
     * CP-026 · Casa con 0 puntos y cualquier tipo con 101: 400 `VALIDACION`; actividad con 0: 201.
     */
    public function testRangoDePuntosPorTipo(): void
    {
        $r = $this->api('post', 'admin/tareas', $this->tareaEntrada('Luna', ['puntos' => 0]));
        $this->assertError($r, 400, 'VALIDACION');
        $this->assertArrayHasKey('puntos', $this->cuerpo($r)['error']['campos']);

        foreach (['casa', 'actividad'] as $tipo) {
            $r = $this->api('post', 'admin/tareas', $this->tareaEntrada('Luna', ['puntos' => 101, 'tipo' => $tipo]));
            $this->assertError($r, 400, 'VALIDACION');
            $this->assertArrayHasKey('puntos', $this->cuerpo($r)['error']['campos']);
        }

        $r = $this->api('post', 'admin/tareas', $this->tareaEntrada('Luna', ['puntos' => 101, 'tipo' => 'escolar', 'materia' => 'Arte', 'fecha_entrega' => $this->hoy(2)], ['frecuencia' => 'una_vez']));
        $this->assertArrayHasKey('puntos', $this->cuerpo($r)['error']['campos']);

        $this->api('post', 'admin/tareas', $this->tareaEntrada('Luna', ['puntos' => 0, 'tipo' => 'actividad']))->assertStatus(201);
    }

    public function testValidacionDeCampos(): void
    {
        $r = $this->api('post', 'admin/tareas', ['titulo' => str_repeat('x', 81), 'puntos' => 'cinco', 'tipo' => 'juego', 'recurrencia' => []]);

        $this->assertError($r, 400, 'VALIDACION');
        $this->assertEqualsCanonicalizing(
            ['perfil_id', 'categoria_id', 'titulo', 'icono', 'puntos', 'tipo', 'frecuencia', 'fecha_inicio'],
            array_keys($this->cuerpo($r)['error']['campos']),
        );

        $r = $this->api('post', 'admin/tareas', $this->tareaEntrada('Luna', ['perfil_id' => 9999, 'categoria_id' => 9999, 'requiere_foto' => 'si', 'icono' => 'bed'], [
            'frecuencia' => 'semanal', 'dias_semana' => [], 'hora' => '25:00', 'fecha_fin' => $this->hoy(-1),
        ]));
        $this->assertEqualsCanonicalizing(
            ['perfil_id', 'categoria_id', 'requiere_foto', 'icono', 'dias_semana', 'hora', 'fecha_fin'],
            array_keys($this->cuerpo($r)['error']['campos']),
        );
    }

    public function testEscolarExigeMateriaEntregaYUnaVez(): void
    {
        $r = $this->api('post', 'admin/tareas', $this->tareaEntrada('Sol', ['tipo' => 'escolar', 'materia' => '  ', 'fecha_entrega' => $this->hoy(-1)]));

        $this->assertError($r, 400, 'VALIDACION');
        $this->assertEqualsCanonicalizing(['materia', 'fecha_entrega', 'frecuencia'], array_keys($this->cuerpo($r)['error']['campos']));

        $tarea = $this->crearTarea('Sol', ['tipo' => 'escolar', 'materia' => 'Historia', 'fecha_entrega' => $this->hoy(4)], ['frecuencia' => 'una_vez', 'dias_semana' => ['lu']]);
        $this->assertSame('Historia', $tarea['materia']);
        $this->assertNull($tarea['recurrencia']['dias_semana']);
        $this->assertSame(1, $this->db->table('tarea_instancias')->where('tarea_id', $tarea['id'])->countAllResults());
        $this->assertSame($this->hoy(4), $this->instancia($tarea['id'], $this->hoy())['fecha_entrega']);
    }

    public function testCamposQueNoAplicanSeGuardanComoNull(): void
    {
        $tarea = $this->crearTarea('Luna', ['materia' => 'Ignorada', 'fecha_entrega' => $this->hoy(2)], ['dias_semana' => ['lu']]);

        $this->assertNull($tarea['materia']);
        $this->assertNull($tarea['fecha_entrega']);
        $this->assertNull($tarea['recurrencia']['dias_semana']);
    }

    /**
     * CP-024 · Editar puntos con instancias hecha y pendiente: solo las pendientes futuras cambian.
     */
    public function testEditarSoloCambiaPendientesFuturas(): void
    {
        $this->sinTareasDemo();
        $entrada = $this->tareaEntrada('Luna', [], ['fecha_inicio' => $this->hoy(-2)]);
        $tarea   = $this->cuerpo($this->conEstado($this->api('post', 'admin/tareas', $entrada), 201));
        (new \App\Services\GeneradorInstancias(db_connect()))->generar($this->hoy(-2), $this->hoy(-1));

        $this->db->table('tarea_instancias')->where(['tarea_id' => $tarea['id'], 'fecha' => $this->hoy(-1)])->update(['estado' => 'hecha']);
        $this->db->table('tarea_instancias')->where(['tarea_id' => $tarea['id'], 'fecha' => $this->hoy(1)])->update(['estado' => 'hecha']);

        $entrada['puntos'] = 9;
        $editada = $this->cuerpo($this->conEstado($this->api('put', "admin/tareas/{$tarea['id']}", $entrada), 200));

        $this->assertSame(9, $editada['puntos']);
        $this->assertSame('5', (string) $this->instancia($tarea['id'], $this->hoy(-2))['puntos'], 'Pendiente pasada intacta.');
        $this->assertSame('5', (string) $this->instancia($tarea['id'], $this->hoy(-1))['puntos'], 'Hecha intacta.');
        $this->assertSame('5', (string) $this->instancia($tarea['id'], $this->hoy(1))['puntos'], 'Hecha futura intacta.');
        $this->assertSame('9', (string) $this->instancia($tarea['id'], $this->hoy())['puntos']);
        $this->assertSame('9', (string) $this->instancia($tarea['id'], $this->hoy(7))['puntos']);
    }

    public function testEditarRecurrenciaRegeneraPendientesFuturas(): void
    {
        $this->sinTareasDemo();
        $entrada = $this->tareaEntrada('Luna');
        $tarea   = $this->crearTarea('Luna');

        $entrada['recurrencia'] = ['frecuencia' => 'una_vez', 'dias_semana' => null, 'hora' => '18:00', 'fecha_inicio' => $this->hoy(2), 'fecha_fin' => null];
        $this->api('put', "admin/tareas/{$tarea['id']}", $entrada)->assertStatus(200);

        $fechas = array_column($this->db->table('tarea_instancias')->where('tarea_id', $tarea['id'])->get()->getResultArray(), 'fecha');
        $this->assertSame([$this->hoy(2)], $fechas);
        $this->assertSame('18:00:00', $this->instancia($tarea['id'], $this->hoy(2))['hora']);
    }

    /**
     * CP-025 · Desactivar: pendientes futuras eliminadas, aprobadas intactas.
     */
    public function testDesactivarTarea(): void
    {
        $this->sinTareasDemo();
        $tarea = $this->crearTarea('Luna', [], ['fecha_inicio' => $this->hoy(-1)]);
        (new \App\Services\GeneradorInstancias(db_connect()))->generar($this->hoy(-1), $this->hoy(-1));
        $this->db->table('tarea_instancias')->where(['tarea_id' => $tarea['id'], 'fecha' => $this->hoy(-1)])->update(['estado' => 'aprobada']);
        $this->db->table('tarea_instancias')->where(['tarea_id' => $tarea['id'], 'fecha' => $this->hoy(2)])->update(['estado' => 'hecha']);

        $this->api('delete', "admin/tareas/{$tarea['id']}")->assertStatus(204);

        $estados = array_column(
            $this->db->table('tarea_instancias')->where('tarea_id', $tarea['id'])->orderBy('fecha')->get()->getResultArray(),
            'estado',
            'fecha',
        );
        $this->assertSame([$this->hoy(-1) => 'aprobada', $this->hoy(2) => 'hecha'], $estados);
        $this->assertSame('0', (string) $this->fila('tareas', $tarea['id'])['activa']);

        // Una tarea inactiva no vuelve a generar instancias.
        (new \App\Services\GeneradorInstancias(db_connect()))->generar($this->hoy(), $this->hoy(7));
        $this->assertSame(2, $this->db->table('tarea_instancias')->where('tarea_id', $tarea['id'])->countAllResults());
    }

    public function testTareaInexistente(): void
    {
        $this->assertError($this->api('put', 'admin/tareas/9999', $this->tareaEntrada('Luna')), 404, 'NO_ENCONTRADO');
        $this->assertError($this->api('delete', 'admin/tareas/9999'), 404, 'NO_ENCONTRADO');
    }

    /**
     * CP-SEG-05 · `titulo` con inyección SQL: se guarda literal y la tabla sigue intacta.
     */
    public function testTituloConInyeccionSqlSeGuardaLiteral(): void
    {
        $titulo = "'; DROP TABLE tareas; --";

        $tarea = $this->crearTarea('Luna', ['titulo' => $titulo]);

        $this->assertSame($titulo, $tarea['titulo']);
        $this->assertSame($titulo, $this->fila('tareas', $tarea['id'])['titulo']);
        $this->assertSame(13, $this->db->table('tareas')->countAllResults());
        $this->assertTrue($this->db->tableExists('tareas', false));
    }

    public function testCategorias(): void
    {
        $this->db->table('categorias')->where('nombre', 'Hábito')->update(['activa' => 0]);

        $categorias = $this->cuerpo($this->conEstado($this->api('get', 'admin/categorias'), 200));

        $this->assertSame(['Casa', 'Escuela', 'Actividad', 'Cuidado personal'], array_column($categorias, 'nombre'));
        $this->assertSame(['id', 'nombre', 'icono', 'color'], array_keys($categorias[0]));
        $this->assertIsInt($categorias[0]['id']);
    }

    public function testSinModoPadreEs403(): void
    {
        $this->api('delete', 'modo-padre')->assertStatus(200);

        $this->assertError($this->api('get', 'admin/tareas'), 403, 'MODO_PADRE_REQUERIDO');
        $this->assertError($this->api('get', 'admin/categorias'), 403, 'MODO_PADRE_REQUERIDO');
    }
}
