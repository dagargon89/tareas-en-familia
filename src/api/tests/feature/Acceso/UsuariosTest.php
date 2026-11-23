<?php

declare(strict_types=1);

namespace Tests\Feature\Acceso;

use Tests\Support\ApiTestCase;

/**
 * ADR-009 · Alta del segundo padre desde la app (RF-02): E-51 y E-52. CP-017 a CP-019.
 *
 * @internal
 */
final class UsuariosTest extends ApiTestCase
{
    /**
     * @param array<string, mixed> $cambios
     *
     * @return array<string, mixed>
     */
    private function nuevoPadre(array $cambios = []): array
    {
        return array_merge([
            'nombre' => 'Mamá Demo', 'email' => '  Mama@Tareas.Local ', 'password' => 'OtraContrasena2026', 'pin' => '7351',
        ], $cambios);
    }

    private function enModoPadre(): void
    {
        $this->iniciarSesionDemo();
        $this->entrarModoPadre();
    }

    public function testListaLosPadresYMarcaAlDeLaSesion(): void
    {
        $this->enModoPadre();

        $padres = $this->cuerpo($this->conEstado($this->api('get', 'admin/usuarios'), 200));

        $this->assertCount(1, $padres);
        $this->assertSame(['id', 'nombre', 'email', 'es_actual'], array_keys($padres[0]));
        $this->assertSame(['Papá Demo', 'demo@tareas.local', true], [$padres[0]['nombre'], $padres[0]['email'], $padres[0]['es_actual']]);
    }

    /**
     * CP-017 · Alta válida en modo padre: 201 sin hashes; el nuevo padre entra con su correo y su PIN.
     */
    public function testAltaDelSegundoPadre(): void
    {
        $this->enModoPadre();

        $padre = $this->cuerpo($this->conEstado($this->api('post', 'admin/usuarios', $this->nuevoPadre()), 201));

        $this->assertSame(['id', 'nombre', 'email', 'es_actual'], array_keys($padre));
        $this->assertSame(['Mamá Demo', 'mama@tareas.local', false], [$padre['nombre'], $padre['email'], $padre['es_actual']]);

        $fila = $this->db->table('usuarios')->where('id', $padre['id'])->get()->getRowArray();
        $this->assertMatchesRegularExpression('/^\$(argon2id|2y)\$/', $fila['password_hash']);
        $this->assertMatchesRegularExpression('/^\$(argon2id|2y)\$/', $fila['pin_hash']);
        $this->assertCount(2, $this->cuerpo($this->api('get', 'admin/usuarios')));

        $this->api('post', 'auth/logout')->assertStatus(204);
        $this->conEstado($this->api('post', 'auth/login', ['email' => 'mama@tareas.local', 'password' => 'OtraContrasena2026']), 200);
        $this->conEstado($this->api('post', 'modo-padre', ['pin' => '7351']), 200);

        $suyos = $this->cuerpo($this->api('get', 'admin/usuarios'));
        $this->assertSame([false, true], array_column($suyos, 'es_actual'), 'Ahora la sesión es de la mamá');
    }

    /**
     * CP-018 · Con 2 padres registrados: 409 `ESTADO_INVALIDO` y no se crea nada.
     */
    public function testNoMasDeDosPadres(): void
    {
        $this->enModoPadre();
        $this->conEstado($this->api('post', 'admin/usuarios', $this->nuevoPadre()), 201);

        $this->assertError($this->api('post', 'admin/usuarios', $this->nuevoPadre(['email' => 'tercero@tareas.local'])), 409, 'ESTADO_INVALIDO');
        $this->assertSame(2, $this->db->table('usuarios')->countAllResults());
    }

    /**
     * CP-019 · Sin modo padre: 403 `MODO_PADRE_REQUERIDO`; sin sesión: 401 `SIN_SESION`.
     */
    public function testRequiereModoPadreYSesion(): void
    {
        $this->assertError($this->api('post', 'admin/usuarios', $this->nuevoPadre()), 401, 'SIN_SESION');

        $this->iniciarSesionDemo();
        $this->assertError($this->api('post', 'admin/usuarios', $this->nuevoPadre()), 403, 'MODO_PADRE_REQUERIDO');
        $this->assertError($this->api('get', 'admin/usuarios'), 403, 'MODO_PADRE_REQUERIDO');
        $this->assertSame(1, $this->db->table('usuarios')->countAllResults());
    }

    public function testValidacion(): void
    {
        $this->enModoPadre();

        $r = $this->api('post', 'admin/usuarios', ['nombre' => str_repeat('a', 61), 'email' => 'no-es-correo', 'password' => 'corta', 'pin' => '1234']);

        $this->assertError($r, 400, 'VALIDACION');
        $this->assertSame(['nombre', 'email', 'password', 'pin'], array_keys($this->cuerpo($r)['error']['campos']));

        $repetido = $this->api('post', 'admin/usuarios', $this->nuevoPadre(['email' => 'DEMO@tareas.local']));
        $this->assertError($repetido, 400, 'VALIDACION');
        $this->assertSame(['email'], array_keys($this->cuerpo($repetido)['error']['campos']));
        $this->assertSame(1, $this->db->table('usuarios')->countAllResults());
    }
}
