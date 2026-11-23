<?php

declare(strict_types=1);

namespace Tests\Feature\Acceso;

use App\Libraries\Hash;
use CodeIgniter\I18n\Time;
use Tests\Support\ApiTestCase;

/**
 * T-S1-02 · Gestión de perfiles (RF-17): E-23 a E-27. CP-010, CP-011, CP-012.
 *
 * @internal
 */
final class AdminPerfilesTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarSesionDemo();
        $this->entrarModoPadre();
    }

    /**
     * @return array<string, mixed>
     */
    private function entrada(array $cambios = []): array
    {
        return array_merge([
            'apodo' => 'Nube', 'avatar' => 'conejo', 'color' => 'lavanda', 'nivel_ux' => 'aventureras', 'pin' => '6281',
        ], $cambios);
    }

    public function testListaTodosLosPerfilesComoPerfilAdmin(): void
    {
        $this->db->table('perfiles')->where('apodo', 'Sol')->update(['activo' => 0, 'pin_intentos' => 2]);

        $perfiles = $this->cuerpo($this->conEstado($this->api('get', 'admin/perfiles'), 200));

        $this->assertSame(['Luna', 'Sol', 'Estrella'], array_column($perfiles, 'apodo'));
        $this->assertSame([
            'id' => $this->perfilId('Sol'), 'apodo' => 'Sol', 'avatar' => 'pajaro', 'color' => 'ambar',
            'nivel_ux' => 'aventureras', 'sonidos' => true, 'tema' => 'claro', 'bloqueado_hasta' => null,
            'activo' => false, 'orden' => 1, 'pin_intentos' => 2,
        ], $perfiles[1]);
    }

    public function testCrearPerfil(): void
    {
        $resultado = $this->api('post', 'admin/perfiles', $this->entrada(['orden' => 4]));

        $resultado->assertStatus(201);
        $perfil = $this->cuerpo($resultado);
        $this->assertIsInt($perfil['id']);
        $this->assertSame('Nube', $perfil['apodo']);
        $this->assertSame(4, $perfil['orden']);
        $this->assertTrue($perfil['activo']);
        $this->assertTrue($perfil['sonidos'], 'Toma `sonidos_default` de la configuración.');
        $this->assertSame(0, $perfil['pin_intentos']);
        $this->assertArrayNotHasKey('pin_hash', $perfil);
        $this->assertTrue(Hash::verificar('6281', $this->fila('perfiles', $perfil['id'])['pin_hash']));
    }

    /**
     * CP-010 · Crear perfil con PIN trivial `1234`: 400 `VALIDACION` en `pin`.
     */
    public function testCrearPerfilConPinTrivial(): void
    {
        $resultado = $this->api('post', 'admin/perfiles', $this->entrada(['pin' => '1234']));

        $this->assertError($resultado, 400, 'VALIDACION');
        $this->assertArrayHasKey('pin', $this->cuerpo($resultado)['error']['campos']);
        $this->assertSame(3, $this->db->table('perfiles')->countAllResults());
    }

    public function testCrearPerfilValidaCadaCampo(): void
    {
        $resultado = $this->api('post', 'admin/perfiles', [
            'apodo' => str_repeat('a', 31), 'avatar' => '', 'color' => 'rojo', 'nivel_ux' => 'experta',
            'orden' => -1, 'activo' => 'si',
        ]);

        $this->assertError($resultado, 400, 'VALIDACION');
        $this->assertEqualsCanonicalizing(
            ['apodo', 'avatar', 'color', 'nivel_ux', 'pin', 'orden', 'activo'],
            array_keys($this->cuerpo($resultado)['error']['campos']),
        );
    }

    public function testEditarSinPinNoLoCambia(): void
    {
        $id = $this->perfilId('Luna');

        $perfil = $this->cuerpo($this->conEstado($this->api('put', "admin/perfiles/{$id}", [
            'apodo' => 'Lunita', 'avatar' => 'conejo', 'color' => 'indigo', 'nivel_ux' => 'aventureras', 'activo' => false,
        ]), 200));

        $this->assertSame('Lunita', $perfil['apodo']);
        $this->assertSame('aventureras', $perfil['nivel_ux']);
        $this->assertFalse($perfil['activo']);
        $this->assertTrue(Hash::verificar('3917', $this->fila('perfiles', $id)['pin_hash']));
    }

    public function testEditarConPinNuevoLoCambiaYConTrivialEsValidacion(): void
    {
        $id = $this->perfilId('Luna');

        $this->api('put', "admin/perfiles/{$id}", $this->entrada(['pin' => '7042']))->assertStatus(200);
        $this->assertTrue(Hash::verificar('7042', $this->fila('perfiles', $id)['pin_hash']));

        $resultado = $this->api('put', "admin/perfiles/{$id}", $this->entrada(['pin' => '9999']));
        $this->assertError($resultado, 400, 'VALIDACION');
        $this->assertArrayHasKey('pin', $this->cuerpo($resultado)['error']['campos']);
    }

    public function testEditarPerfilInexistente(): void
    {
        $this->assertError($this->api('put', 'admin/perfiles/9999', $this->entrada()), 404, 'NO_ENCONTRADO');
    }

    /**
     * CP-011 · Desbloquear perfil bloqueado: `bloqueado_hasta` null, intentos en 0.
     */
    public function testDesbloquearPerfil(): void
    {
        $id = $this->perfilId('Sol');
        $this->db->table('perfiles')->where('id', $id)->update([
            'pin_intentos' => 4, 'bloqueado_hasta' => Time::now()->addMinutes(10)->toDateTimeString(),
        ]);

        $perfil = $this->cuerpo($this->conEstado($this->api('post', "admin/perfiles/{$id}/desbloquear"), 200));

        $this->assertNull($perfil['bloqueado_hasta']);
        $this->assertSame(0, $perfil['pin_intentos']);
        $fila = $this->fila('perfiles', $id);
        $this->assertNull($fila['bloqueado_hasta']);
        $this->assertSame('0', (string) $fila['pin_intentos']);
    }

    public function testDesactivarPerfilNoDefinitivo(): void
    {
        $id = $this->perfilId('Luna');

        $this->api('delete', "admin/perfiles/{$id}?definitivo=false")->assertStatus(204);

        $this->assertSame('0', (string) $this->fila('perfiles', $id)['activo']);
        $this->assertSame(1, $this->db->table('tareas')->where('perfil_id', $id)->countAllResults() > 0 ? 1 : 0);
    }

    /**
     * CP-012 · Borrado definitivo: sin filas del perfil en ninguna tabla y fotos borradas.
     */
    public function testBorradoDefinitivo(): void
    {
        $id    = $this->perfilId('Sol');
        $otra  = $this->perfilId('Luna');
        $ahora = Time::now()->toDateTimeString();
        $hoy   = Time::today()->toDateString();

        // Datos del perfil en todas las tablas relacionadas.
        $tarea = (int) $this->db->table('tareas')->where('perfil_id', $id)->get()->getRowArray()['id'];
        $this->db->table('tarea_instancias')->insert([
            'tarea_id' => $tarea, 'perfil_id' => $id, 'fecha' => $hoy, 'puntos' => 5, 'estado' => 'aprobada',
            'created_at' => $ahora, 'updated_at' => $ahora,
        ]);
        $instancia = (int) $this->db->insertID();
        $usuario   = (int) $this->db->table('usuarios')->get()->getRowArray()['id'];
        $this->db->table('revisiones')->insert(['instancia_id' => $instancia, 'usuario_id' => $usuario, 'decision' => 'aprobada', 'created_at' => $ahora]);
        $this->db->table('evidencias')->insert([
            'instancia_id' => $instancia, 'ruta' => "{$id}/foto.webp", 'mime' => 'image/webp', 'bytes' => 4, 'created_at' => $ahora,
        ]);
        $this->db->table('movimientos_puntos')->insert([
            'perfil_id' => $id, 'origen' => 'tarea', 'origen_id' => $instancia, 'puntos' => 5, 'descripcion' => 'Tarea', 'created_at' => $ahora,
        ]);
        $recompensa = (int) $this->db->table('recompensas')->get()->getRowArray()['id'];
        $this->db->table('canjes')->insert([
            'perfil_id' => $id, 'recompensa_id' => $recompensa, 'costo' => 50, 'created_at' => $ahora, 'updated_at' => $ahora,
        ]);
        $insignia = (int) $this->db->table('insignias')->get()->getRowArray()['id'];
        $this->db->table('perfil_insignias')->insert(['perfil_id' => $id, 'insignia_id' => $insignia, 'obtenida_en' => $ahora]);
        $this->db->table('rachas')->insert([
            'perfil_id' => $id, 'semana_iso' => '2026-W48', 'asignadas' => 1, 'aprobadas' => 1, 'porcentaje' => 100, 'cumplida' => 1, 'created_at' => $ahora,
        ]);

        $carpeta = WRITEPATH . "evidencias/{$id}";
        $carpetaOtra = WRITEPATH . "evidencias/{$otra}";
        @mkdir($carpeta, 0o775, true);
        @mkdir($carpetaOtra, 0o775, true);
        file_put_contents("{$carpeta}/foto.webp", 'webp');
        file_put_contents("{$carpetaOtra}/otra.webp", 'webp');

        try {
            $this->api('delete', "admin/perfiles/{$id}?definitivo=true")->assertStatus(204);

            foreach ([
                'perfiles' => 'id', 'tareas' => 'perfil_id', 'tarea_instancias' => 'perfil_id', 'movimientos_puntos' => 'perfil_id',
                'canjes' => 'perfil_id', 'perfil_insignias' => 'perfil_id', 'rachas' => 'perfil_id',
            ] as $tabla => $columna) {
                $this->assertSame(0, $this->db->table($tabla)->where($columna, $id)->countAllResults(), $tabla);
            }

            $this->assertSame(0, $this->db->table('evidencias')->countAllResults());
            $this->assertSame(0, $this->db->table('revisiones')->countAllResults());
            $this->assertSame(8, $this->db->table('tarea_recurrencias')->countAllResults(), 'Solo se borran las recurrencias de Sol.');
            $this->assertDirectoryDoesNotExist($carpeta);
            $this->assertFileExists("{$carpetaOtra}/otra.webp", 'No toca fotos de otros perfiles.');
            $this->assertSame(2, $this->db->table('perfiles')->countAllResults());
        } finally {
            @unlink("{$carpetaOtra}/otra.webp");
            @rmdir($carpetaOtra);
            @unlink("{$carpeta}/foto.webp");
            @rmdir($carpeta);
        }
    }

    public function testBorrarPerfilInexistente(): void
    {
        $this->assertError($this->api('delete', 'admin/perfiles/9999?definitivo=true'), 404, 'NO_ENCONTRADO');
    }
}
