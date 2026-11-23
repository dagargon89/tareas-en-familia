<?php

declare(strict_types=1);

namespace Tests\Feature\Juego;

use CodeIgniter\Test\TestResponse;
use Tests\Support\ApiTestCase;

/**
 * T-S4-01 · Recompensas y canjes (RF-09): E-14 a E-16 y E-37 a E-41.
 * CP-060 a CP-063 (CP-064 es de la web).
 *
 * @internal
 */
final class CanjesTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->sinTareasDemo();
        $this->iniciarSesionDemo();
    }

    /**
     * Abona puntos de tarea al perfil directamente en el libro (la prueba no depende de aprobar).
     */
    private function abonar(string $apodo, int $puntos): void
    {
        static $origenId = 1000;

        $this->db->table('movimientos_puntos')->insert([
            'perfil_id' => $this->perfilId($apodo), 'origen' => 'tarea', 'origen_id' => $origenId++, 'puntos' => $puntos,
            'descripcion' => 'Prueba', 'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recompensaId(int $costo): int
    {
        return (int) $this->db->table('recompensas')->where('costo', $costo)->get()->getRowArray()['id'];
    }

    private function solicitar(int $recompensaId): TestResponse
    {
        return $this->api('post', 'yo/canjes', ['recompensa_id' => $recompensaId]);
    }

    /**
     * @return array<string, mixed>
     */
    private function progreso(): array
    {
        return $this->cuerpo($this->conEstado($this->api('get', 'yo/progreso'), 200));
    }

    /**
     * Canje `solicitado` de Sol por la recompensa de 50 puntos, con 100 de saldo.
     */
    private function canjeSolicitado(): int
    {
        $this->abonar('Sol', 100);
        $this->entrarPerfil('Sol');
        $id = (int) $this->cuerpo($this->conEstado($this->solicitar($this->recompensaId(50)), 201))['id'];
        $this->entrarModoPadre();

        return $id;
    }

    private function revisarCanje(int $id, string $decision): TestResponse
    {
        return $this->api('post', "admin/canjes/{$id}/revision", ['decision' => $decision]);
    }

    private function movimientosDeCanje(int $canjeId): array
    {
        return $this->db->table('movimientos_puntos')->where(['origen' => 'canje', 'origen_id' => $canjeId])->get()->getResultArray();
    }

    // ===== Hija: E-14 a E-16 =====

    public function testCatalogoDeLaHijaMarcaLoQueAlcanza(): void
    {
        $this->abonar('Sol', 90);
        $this->db->table('recompensas')->where('costo', 200)->update(['activa' => 0]);
        $this->entrarPerfil('Sol');

        $catalogo = $this->cuerpo($this->conEstado($this->api('get', 'yo/recompensas'), 200));

        $this->assertSame([50, 80, 120], array_column($catalogo, 'costo'));
        $this->assertSame(['id', 'titulo', 'icono', 'costo', 'activa', 'alcanza', 'faltan'], array_keys($catalogo[0]));
        $this->assertSame([true, true, false], array_column($catalogo, 'alcanza'));
        $this->assertSame([0, 0, 30], array_column($catalogo, 'faltan'));
        $this->assertTrue($catalogo[0]['activa']);
    }

    /**
     * CP-060 · Canje con saldo suficiente: 201 `solicitado` y `disponible` reducido.
     */
    public function testSolicitarCanjeConSaldoSuficiente(): void
    {
        $this->abonar('Sol', 100);
        $this->entrarPerfil('Sol');

        $canje = $this->cuerpo($this->conEstado($this->solicitar($this->recompensaId(80)), 201));

        $this->assertSame(['id', 'perfil_id', 'recompensa', 'costo', 'estado', 'created_at'], array_keys($canje));
        $this->assertSame('solicitado', $canje['estado']);
        $this->assertSame(80, $canje['costo']);
        $this->assertSame($this->perfilId('Sol'), $canje['perfil_id']);
        $this->assertSame(['id', 'titulo', 'icono', 'costo', 'activa'], array_keys($canje['recompensa']));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $canje['created_at']);

        $p = $this->progreso();
        $this->assertSame([100, 100, 20], [$p['xp'], $p['saldo'], $p['disponible']]);
        $this->assertSame(0, $this->db->table('movimientos_puntos')->where('origen', 'canje')->countAllResults());

        $mios = $this->cuerpo($this->conEstado($this->api('get', 'yo/canjes'), 200));
        $this->assertSame([$canje['id']], array_column($mios, 'id'));

        $catalogo = $this->cuerpo($this->api('get', 'yo/recompensas'));
        $this->assertSame([false, false, false, false], array_column($catalogo, 'alcanza'), 'Con 20 disponibles nada alcanza');
    }

    /**
     * CP-061 · Sin saldo contando lo reservado por canjes `solicitado`: 409 `SALDO_INSUFICIENTE`.
     */
    public function testSolicitarCanjeSinSaldoContandoReservados(): void
    {
        $this->abonar('Sol', 100);
        $this->entrarPerfil('Sol');
        $this->conEstado($this->solicitar($this->recompensaId(80)), 201);

        $this->assertError($this->solicitar($this->recompensaId(50)), 409, 'SALDO_INSUFICIENTE');
        $this->assertSame(1, $this->db->table('canjes')->countAllResults());
    }

    public function testSolicitarCanjeSinPuntosNiRecompensaValida(): void
    {
        $this->entrarPerfil('Luna');

        $this->assertError($this->solicitar($this->recompensaId(50)), 409, 'SALDO_INSUFICIENTE');
        $this->assertError($this->solicitar(999999), 404, 'NO_ENCONTRADO');
        $this->assertError($this->api('post', 'yo/canjes', ['recompensa_id' => 'uno']), 400, 'VALIDACION');

        $this->abonar('Luna', 500);
        $this->db->table('recompensas')->where('costo', 50)->update(['activa' => 0]);
        $this->assertError($this->solicitar($this->recompensaId(50)), 404, 'NO_ENCONTRADO');
    }

    public function testCanjesDeLaHijaSoloLosSuyosYSinPerfil(): void
    {
        $this->canjeSolicitado();
        $this->api('post', 'perfiles/salir');
        $this->assertError($this->api('get', 'yo/canjes'), 403, 'SIN_PERFIL');

        $this->entrarPerfil('Luna');
        $this->assertSame([], $this->cuerpo($this->conEstado($this->api('get', 'yo/canjes'), 200)));
    }

    // ===== Padre: E-40 y E-41 =====

    /**
     * CP-062 · Aprobar: movimiento negativo igual al costo; el XP no baja.
     */
    public function testAprobarCanjeInsertaMovimientoNegativo(): void
    {
        $id = $this->canjeSolicitado();

        $canje = $this->cuerpo($this->conEstado($this->revisarCanje($id, 'aprobado'), 200));

        $this->assertSame('aprobado', $canje['estado']);
        $movimientos = $this->movimientosDeCanje($id);
        $this->assertCount(1, $movimientos);
        $this->assertSame(-50, (int) $movimientos[0]['puntos']);
        $this->assertSame($this->perfilId('Sol'), (int) $movimientos[0]['perfil_id']);
        $this->assertNotNull($this->fila('canjes', $id)['revisado_por']);

        $this->entrarPerfil('Sol');
        $p = $this->progreso();
        $this->assertSame([100, 50, 50], [$p['xp'], $p['saldo'], $p['disponible']]);
    }

    /**
     * CP-063 · Rechazar: sin movimiento y `disponible` restaurado.
     */
    public function testRechazarCanjeLiberaElSaldo(): void
    {
        $id = $this->canjeSolicitado();

        $canje = $this->cuerpo($this->conEstado($this->revisarCanje($id, 'rechazado'), 200));

        $this->assertSame('rechazado', $canje['estado']);
        $this->assertSame([], $this->movimientosDeCanje($id));

        $this->entrarPerfil('Sol');
        $p = $this->progreso();
        $this->assertSame([100, 100], [$p['saldo'], $p['disponible']]);
    }

    public function testTransicionesDelCanje(): void
    {
        $id = $this->canjeSolicitado();

        $this->assertError($this->revisarCanje($id, 'entregado'), 409, 'ESTADO_INVALIDO');
        $this->assertError($this->revisarCanje($id, 'cancelado'), 400, 'VALIDACION');
        $this->conEstado($this->revisarCanje($id, 'aprobado'), 200);
        $this->assertError($this->revisarCanje($id, 'aprobado'), 409, 'ESTADO_INVALIDO');
        $this->assertError($this->revisarCanje($id, 'rechazado'), 409, 'ESTADO_INVALIDO');
        $this->assertCount(1, $this->movimientosDeCanje($id), 'Aprobar dos veces no descuenta dos veces');

        $entregado = $this->cuerpo($this->conEstado($this->revisarCanje($id, 'entregado'), 200));
        $this->assertSame('entregado', $entregado['estado']);
        $this->assertError($this->revisarCanje($id, 'entregado'), 409, 'ESTADO_INVALIDO');
        $this->assertError($this->revisarCanje(999999, 'aprobado'), 404, 'NO_ENCONTRADO');
    }

    public function testListaDeCanjesDelPadreConFiltro(): void
    {
        $primero = $this->canjeSolicitado();
        $this->entrarPerfil('Sol');
        $segundo = (int) $this->cuerpo($this->conEstado($this->solicitar($this->recompensaId(50)), 201))['id'];
        $this->entrarModoPadre();
        $this->conEstado($this->revisarCanje($segundo, 'rechazado'), 200);

        $todos = $this->cuerpo($this->conEstado($this->api('get', 'admin/canjes'), 200));
        $this->assertSame([$primero, $segundo], array_column($todos, 'id'));

        $pendientes = $this->cuerpo($this->conEstado($this->api('get', 'admin/canjes?estado=solicitado'), 200));
        $this->assertSame([$primero], array_column($pendientes, 'id'));

        $this->assertError($this->api('get', 'admin/canjes?estado=otro'), 400, 'VALIDACION');
    }

    public function testRutasDelPadreRequierenModoPadre(): void
    {
        $this->assertError($this->api('get', 'admin/canjes'), 403, 'MODO_PADRE_REQUERIDO');
        $this->assertError($this->api('get', 'admin/recompensas'), 403, 'MODO_PADRE_REQUERIDO');
    }

    // ===== Padre: catálogo E-37 a E-39 =====

    public function testCatalogoDelPadreCrearYEditar(): void
    {
        $this->entrarModoPadre();

        $catalogo = $this->cuerpo($this->conEstado($this->api('get', 'admin/recompensas'), 200));
        $this->assertCount(4, $catalogo);
        $this->assertSame(['id', 'titulo', 'icono', 'costo', 'activa'], array_keys($catalogo[0]));

        $nueva = $this->cuerpo($this->conEstado($this->api('post', 'admin/recompensas', [
            'titulo' => '  Ir al cine  ', 'icono' => 'cine', 'costo' => 300, 'activa' => true,
        ]), 201));
        $this->assertSame(['titulo' => 'Ir al cine', 'icono' => 'cine', 'costo' => 300, 'activa' => true], array_diff_key($nueva, ['id' => 0]));

        $editada = $this->cuerpo($this->conEstado($this->api('put', "admin/recompensas/{$nueva['id']}", [
            'titulo' => 'Ir al cine', 'icono' => 'palomitas', 'costo' => 250, 'activa' => false,
        ]), 200));
        $this->assertSame(['id' => $nueva['id'], 'titulo' => 'Ir al cine', 'icono' => 'palomitas', 'costo' => 250, 'activa' => false], $editada);

        $this->assertCount(5, $this->cuerpo($this->api('get', 'admin/recompensas')), 'El padre ve también las inactivas');
        $this->assertError($this->api('put', 'admin/recompensas/999999', ['titulo' => 'X', 'icono' => 'cine', 'costo' => 1, 'activa' => true]), 404, 'NO_ENCONTRADO');
    }

    public function testValidacionDeRecompensa(): void
    {
        $this->entrarModoPadre();

        $resultado = $this->api('post', 'admin/recompensas', ['titulo' => str_repeat('a', 61), 'icono' => 'nave', 'costo' => 0, 'activa' => 'si']);

        $this->assertError($resultado, 400, 'VALIDACION');
        $this->assertSame(['titulo', 'icono', 'costo', 'activa'], array_keys($this->cuerpo($resultado)['error']['campos']));
        $this->assertError($this->api('post', 'admin/recompensas', ['titulo' => 'Caro', 'icono' => 'regalo', 'costo' => 10000, 'activa' => true]), 400, 'VALIDACION');
    }

    public function testEditarElCostoNoCambiaCanjesYaSolicitados(): void
    {
        $id = $this->canjeSolicitado();
        $recompensa = $this->recompensaId(50);

        $this->conEstado($this->api('put', "admin/recompensas/{$recompensa}", ['titulo' => 'Película', 'icono' => 'cine', 'costo' => 70, 'activa' => true]), 200);
        $this->conEstado($this->revisarCanje($id, 'aprobado'), 200);

        $this->assertSame(-50, (int) $this->movimientosDeCanje($id)[0]['puntos']);
    }
}
