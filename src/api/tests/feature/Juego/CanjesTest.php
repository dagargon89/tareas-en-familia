<?php

declare(strict_types=1);

namespace Tests\Feature\Juego;

use Tests\Support\ApiTestCase;

/**
 * T-S4-01 · Recompensas y canjes (RF-09): E-14 a E-16 y E-37 a E-41. CP-060 a CP-063.
 *
 * @internal
 */
final class CanjesTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarSesionDemo();
    }

    private function darPuntos(string $apodo, int $puntos): void
    {
        $this->db->table('movimientos_puntos')->insert([
            'perfil_id' => $this->perfilId($apodo), 'origen' => 'tarea', 'origen_id' => 900_000 + $this->db->table('movimientos_puntos')->countAllResults(), 'puntos' => $puntos,
            'descripcion' => 'Saldo de prueba', 'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recompensa(string $titulo): int
    {
        return (int) $this->db->table('recompensas')->where('titulo', $titulo)->get()->getRowArray()['id'];
    }

    private function disponible(): int
    {
        return $this->cuerpo($this->api('get', 'yo/progreso'))['disponible'];
    }

    /**
     * Sol pide un canje y se devuelve su id.
     */
    private function solicitar(string $titulo): int
    {
        return $this->cuerpo($this->conEstado($this->api('post', 'yo/canjes', ['recompensa_id' => $this->recompensa($titulo)]), 201))['id'];
    }

    private function revisar(int $id, string $decision): \CodeIgniter\Test\TestResponse
    {
        return $this->api('post', "admin/canjes/{$id}/revision", ['decision' => $decision]);
    }

    public function testCatalogoParaElPerfilConAlcanzaYFaltan(): void
    {
        $this->darPuntos('Sol', 90);
        $this->db->table('recompensas')->where('titulo', 'Un helado')->update(['activa' => 0]);
        $this->entrarPerfil('Sol');

        $catalogo = $this->cuerpo($this->conEstado($this->api('get', 'yo/recompensas'), 200));

        $this->assertSame(['Elegir la película del viernes', '30 minutos extra de pantalla', 'Tarde en el parque'], array_column($catalogo, 'titulo'));
        $this->assertSame(['id', 'titulo', 'icono', 'costo', 'activa', 'alcanza', 'faltan'], array_keys($catalogo[0]));
        $this->assertSame([true, true, false], array_column($catalogo, 'alcanza'));
        $this->assertSame([0, 0, 110], array_column($catalogo, 'faltan'));
    }

    /**
     * CP-060 · Con saldo suficiente: 201 `solicitado` y `disponible` reducido.
     */
    public function testSolicitarConSaldoSuficiente(): void
    {
        $this->darPuntos('Sol', 100);
        $this->entrarPerfil('Sol');

        $r = $this->api('post', 'yo/canjes', ['recompensa_id' => $this->recompensa('30 minutos extra de pantalla'), 'costo' => 1]);

        $r->assertStatus(201);
        $canje = $this->cuerpo($r);
        $this->assertSame(['id', 'perfil_id', 'recompensa', 'costo', 'estado', 'created_at'], array_keys($canje));
        $this->assertSame('solicitado', $canje['estado']);
        $this->assertSame(80, $canje['costo'], 'El costo lo copia el servidor.');
        $this->assertSame($this->perfilId('Sol'), $canje['perfil_id']);
        $this->assertSame('30 minutos extra de pantalla', $canje['recompensa']['titulo']);
        $this->assertSame(['id', 'titulo', 'icono', 'costo', 'activa'], array_keys($canje['recompensa']));
        $this->assertMatchesRegularExpression('/T\d{2}:\d{2}:\d{2}-0[67]:00$/', $canje['created_at']);
        $this->assertSame(20, $this->disponible());
        $this->assertSame(0, $this->db->table('movimientos_puntos')->where('origen', 'canje')->countAllResults(), 'Solicitar no mueve el saldo.');

        $this->assertSame([$canje['id']], array_column($this->cuerpo($this->api('get', 'yo/canjes')), 'id'));
    }

    /**
     * CP-061 · Sin saldo, contando los reservados: 409 `SALDO_INSUFICIENTE`.
     */
    public function testSolicitarSinSaldoContandoReservados(): void
    {
        $this->darPuntos('Sol', 100);
        $this->entrarPerfil('Sol');
        $this->solicitar('30 minutos extra de pantalla');

        $this->assertError($this->api('post', 'yo/canjes', ['recompensa_id' => $this->recompensa('Elegir la película del viernes')]), 409, 'SALDO_INSUFICIENTE');
        $this->assertSame(1, $this->db->table('canjes')->countAllResults());
    }

    public function testSolicitarValidaLaRecompensa(): void
    {
        $this->darPuntos('Sol', 500);
        $this->db->table('recompensas')->where('titulo', 'Un helado')->update(['activa' => 0]);
        $this->entrarPerfil('Sol');

        $this->assertError($this->api('post', 'yo/canjes', ['recompensa_id' => $this->recompensa('Un helado')]), 404, 'NO_ENCONTRADO');
        $this->assertError($this->api('post', 'yo/canjes', ['recompensa_id' => 99999]), 404, 'NO_ENCONTRADO');
        $r = $this->api('post', 'yo/canjes', ['recompensa_id' => 'helado']);
        $this->assertError($r, 400, 'VALIDACION');
        $this->assertArrayHasKey('recompensa_id', $this->cuerpo($r)['error']['campos']);
    }

    /**
     * CP-062 · Aprobar canje: movimiento negativo igual al costo.
     */
    public function testAprobarCanje(): void
    {
        $this->darPuntos('Sol', 100);
        $this->entrarPerfil('Sol');
        $id = $this->solicitar('30 minutos extra de pantalla');
        $this->entrarModoPadre();

        $canje = $this->cuerpo($this->conEstado($this->revisar($id, 'aprobado'), 200));

        $this->assertSame('aprobado', $canje['estado']);
        $mov = $this->db->table('movimientos_puntos')->where(['origen' => 'canje', 'origen_id' => $id])->get()->getResultArray();
        $this->assertCount(1, $mov);
        $this->assertSame('-80', (string) $mov[0]['puntos']);
        $usuario = (string) $this->db->table('usuarios')->get()->getRowArray()['id'];
        $this->assertSame($usuario, (string) $this->fila('canjes', $id)['revisado_por']);
        $this->assertSame(20, $this->disponible());
        $p = $this->cuerpo($this->api('get', 'yo/progreso'));
        $this->assertSame([100, 20], [$p['xp'], $p['saldo']], 'RN-05: el XP no baja.');
    }

    /**
     * CP-063 · Rechazar canje: sin movimiento y `disponible` restaurado.
     */
    public function testRechazarCanje(): void
    {
        $this->darPuntos('Sol', 100);
        $this->entrarPerfil('Sol');
        $id = $this->solicitar('30 minutos extra de pantalla');
        $this->assertSame(20, $this->disponible());
        $this->entrarModoPadre();

        $this->assertSame('rechazado', $this->cuerpo($this->conEstado($this->revisar($id, 'rechazado'), 200))['estado']);

        $this->assertSame(0, $this->db->table('movimientos_puntos')->where('origen', 'canje')->countAllResults());
        $this->assertSame(100, $this->disponible());
    }

    public function testTransiciones(): void
    {
        $this->darPuntos('Sol', 500);
        $this->entrarPerfil('Sol');
        $a = $this->solicitar('Elegir la película del viernes');
        $b = $this->solicitar('Elegir la película del viernes');
        $this->entrarModoPadre();

        $this->assertError($this->revisar($a, 'entregado'), 409, 'ESTADO_INVALIDO');
        $this->revisar($a, 'aprobado')->assertStatus(200);
        $this->assertError($this->revisar($a, 'aprobado'), 409, 'ESTADO_INVALIDO');
        $this->assertError($this->revisar($a, 'rechazado'), 409, 'ESTADO_INVALIDO');
        $this->assertSame('entregado', $this->cuerpo($this->conEstado($this->revisar($a, 'entregado'), 200))['estado']);
        $this->assertError($this->revisar($a, 'entregado'), 409, 'ESTADO_INVALIDO');

        $this->revisar($b, 'rechazado')->assertStatus(200);
        $this->assertError($this->revisar($b, 'aprobado'), 409, 'ESTADO_INVALIDO');

        $this->assertSame(1, $this->db->table('movimientos_puntos')->where('origen', 'canje')->countAllResults());
        $r = $this->revisar($b, 'cancelado');
        $this->assertError($r, 400, 'VALIDACION');
        $this->assertArrayHasKey('decision', $this->cuerpo($r)['error']['campos']);
        $this->assertError($this->revisar(99999, 'aprobado'), 404, 'NO_ENCONTRADO');
    }

    public function testCanjesDelPadreConFiltroOpcional(): void
    {
        $this->darPuntos('Sol', 500);
        $this->entrarPerfil('Sol');
        $a = $this->solicitar('Elegir la película del viernes');
        $b = $this->solicitar('Un helado');
        $this->entrarModoPadre();
        $this->revisar($a, 'aprobado')->assertStatus(200);

        $this->assertEqualsCanonicalizing([$a, $b], array_column($this->cuerpo($this->api('get', 'admin/canjes')), 'id'));
        $this->assertSame([$b], array_column($this->cuerpo($this->api('get', 'admin/canjes?estado=solicitado')), 'id'));
        $this->assertSame([$a], array_column($this->cuerpo($this->api('get', 'admin/canjes?estado=aprobado')), 'id'));
        $r = $this->api('get', 'admin/canjes?estado=perdido');
        $this->assertError($r, 400, 'VALIDACION');
    }

    public function testCatalogoDelPadre(): void
    {
        $this->entrarModoPadre();

        $r = $this->api('post', 'admin/recompensas', ['titulo' => 'Ir al cine', 'icono' => 'cine', 'costo' => 300, 'activa' => true]);
        $r->assertStatus(201);
        $nueva = $this->cuerpo($r);
        $this->assertSame(['id', 'titulo', 'icono', 'costo', 'activa'], array_keys($nueva));
        $this->assertSame(300, $nueva['costo']);

        $editada = $this->cuerpo($this->conEstado($this->api('put', "admin/recompensas/{$nueva['id']}", ['titulo' => 'Ir al cine', 'icono' => 'palomitas', 'costo' => 250, 'activa' => false]), 200));
        $this->assertSame(['palomitas', 250, false], [$editada['icono'], $editada['costo'], $editada['activa']]);

        $todas = $this->cuerpo($this->api('get', 'admin/recompensas'));
        $this->assertCount(5, $todas, 'El padre ve también las inactivas.');

        $r = $this->api('post', 'admin/recompensas', ['titulo' => '', 'icono' => 'bed', 'costo' => 0, 'activa' => 'si']);
        $this->assertError($r, 400, 'VALIDACION');
        $this->assertEqualsCanonicalizing(['titulo', 'icono', 'costo', 'activa'], array_keys($this->cuerpo($r)['error']['campos']));
        $this->assertError($this->api('put', 'admin/recompensas/99999', ['titulo' => 'X', 'icono' => 'cine', 'costo' => 5, 'activa' => true]), 404, 'NO_ENCONTRADO');
    }

    public function testRutasAdminRequierenModoPadre(): void
    {
        $this->assertError($this->api('get', 'admin/canjes'), 403, 'MODO_PADRE_REQUERIDO');
        $this->assertError($this->api('get', 'admin/recompensas'), 403, 'MODO_PADRE_REQUERIDO');
        $this->assertError($this->api('get', 'yo/recompensas'), 403, 'SIN_PERFIL');
    }
}
