<?php

declare(strict_types=1);

namespace Tests\Feature\Evidencias;

use App\Services\EvidenciasService;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Test\TestResponse;
use Config\Services;
use Tests\Support\ApiTestCase;
use Tests\Support\Imagenes;

/**
 * T-S3-01 · Evidencias (RF-06, doc 04 §3.5): E-12 con foto, E-36 y purga. CP-040 a CP-044.
 *
 * `is_uploaded_file()` siempre es falso en CLI; aquí se sustituye solo esa
 * comprobación (`subidaValida`). Tamaño, MIME real, re-codificación y guardado son los reales.
 *
 * @internal
 */
final class EvidenciasTest extends ApiTestCase
{
    private string $temporal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sinTareasDemo();
        $this->limpiarEvidencias();
        $this->temporal = WRITEPATH . 'uploads/pruebas';
        @mkdir($this->temporal, 0o775, true);

        Services::injectMock('evidencias', new class (db_connect()) extends EvidenciasService {
            protected function subidaValida(UploadedFile $archivo): bool
            {
                return $archivo->getError() === UPLOAD_ERR_OK && is_file($archivo->getTempName());
            }
        });

        $this->iniciarSesionDemo();
        $this->entrarModoPadre();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->temporal . '/*') ?: [] as $archivo) {
            unlink($archivo);
        }

        $this->limpiarEvidencias();
        parent::tearDown();
    }

    private function limpiarEvidencias(): void
    {
        foreach (glob(WRITEPATH . 'evidencias/*', GLOB_ONLYDIR) ?: [] as $carpeta) {
            array_map('unlink', glob($carpeta . '/*') ?: []);
            rmdir($carpeta);
        }
    }

    private function marcarConFoto(int $instanciaId, ?string $ruta, string $nombre = 'foto.jpg', string $tipo = 'image/jpeg'): TestResponse
    {
        service('superglobals')->setFilesArray($ruta === null ? [] : [
            'foto' => ['name' => $nombre, 'type' => $tipo, 'tmp_name' => $ruta, 'error' => UPLOAD_ERR_OK, 'size' => filesize($ruta)],
        ]);

        $resultado = $this->withSession($this->sesion)->withHeaders(['X-CSRF-TOKEN' => csrf_hash(), 'Accept' => 'application/json'])
            ->post("api/v1/yo/instancias/{$instanciaId}/hecha", []);
        $this->sesion = $_SESSION ?? [];
        service('superglobals')->setFilesArray([]);

        return $resultado;
    }

    /**
     * Tarea de hoy de Luna; devuelve el id de la instancia.
     */
    private function instanciaDeLuna(bool $requiereFoto = true): int
    {
        $tarea = $this->crearTarea('Luna', ['requiere_foto' => $requiereFoto]);

        return (int) $this->instancia($tarea['id'], $this->hoy())['id'];
    }

    /**
     * @return array<string, mixed>
     */
    private function evidencia(int $instanciaId): array
    {
        return $this->db->table('evidencias')->where('instancia_id', $instanciaId)->get()->getRowArray() ?? [];
    }

    /**
     * CP-040 · JPEG de 2 MB con EXIF GPS: se guarda como WebP sin EXIF (y a máximo 1600 px).
     */
    public function testJpegConGpsSeGuardaComoWebpSinExif(): void
    {
        $id = $this->instanciaDeLuna();
        $this->entrarPerfil('Luna');
        $jpeg = Imagenes::jpegConGps($this->temporal, 2_100_000);
        $this->assertGreaterThanOrEqual(2_000_000, filesize($jpeg));
        $this->assertSame('N', exif_read_data($jpeg, 'GPS', true)['GPS']['GPSLatitudeRef'], 'El original sí trae GPS.');

        $instancia = $this->cuerpo($this->conEstado($this->marcarConFoto($id, $jpeg), 200));

        $this->assertSame('hecha', $instancia['estado']);
        $this->assertTrue($instancia['tiene_evidencia']);
        $evidencia = $this->evidencia($id);
        $this->assertSame((int) $evidencia['id'], $instancia['evidencia_id']);
        $this->assertSame('image/webp', $evidencia['mime']);
        $this->assertMatchesRegularExpression('#^' . $this->perfilId('Luna') . '/[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\.webp$#', $evidencia['ruta']);
        $this->assertNull($evidencia['borrar_despues']);

        $archivo = WRITEPATH . 'evidencias/' . $evidencia['ruta'];
        $binario = (string) file_get_contents($archivo);
        $this->assertSame('RIFF', substr($binario, 0, 4));
        $this->assertSame('WEBP', substr($binario, 8, 4));
        $this->assertSame(filesize($archivo), (int) $evidencia['bytes']);
        $this->assertStringNotContainsString('EXIF', $binario);
        $this->assertStringNotContainsString('Exif', $binario);
        $this->assertSame([1600, 1200], array_slice((array) getimagesize($archivo), 0, 2));
        $this->assertStringNotContainsString(realpath(FCPATH) ?: FCPATH, realpath($archivo) ?: $archivo, 'Fuera de public/.');
    }

    public function testRequiereFotoSinFotoEs400(): void
    {
        $id = $this->instanciaDeLuna();
        $this->entrarPerfil('Luna');

        $r = $this->marcarConFoto($id, null);

        $this->assertError($r, 400, 'VALIDACION');
        $this->assertArrayHasKey('foto', $this->cuerpo($r)['error']['campos']);
        $this->assertSame('pendiente', $this->fila('tarea_instancias', $id)['estado']);
    }

    /**
     * CP-041 · Archivo de 4 MB: 400 `VALIDACION`.
     */
    public function testArchivoDeCuatroMegasEs400(): void
    {
        $id = $this->instanciaDeLuna();
        $this->entrarPerfil('Luna');

        $r = $this->marcarConFoto($id, Imagenes::jpegConGps($this->temporal, 4_000_000));

        $this->assertError($r, 400, 'VALIDACION');
        $this->assertArrayHasKey('foto', $this->cuerpo($r)['error']['campos']);
        $this->assertSame([], $this->evidencia($id));
        $this->assertSame('pendiente', $this->fila('tarea_instancias', $id)['estado']);
    }

    public function testSubidaCortadaPorElLimiteDePhpDiceElTamano(): void
    {
        $id = $this->instanciaDeLuna();
        $this->entrarPerfil('Luna');
        service('superglobals')->setFilesArray(['foto' => ['name' => 'f.jpg', 'type' => 'image/jpeg', 'tmp_name' => '', 'error' => UPLOAD_ERR_INI_SIZE, 'size' => 0]]);

        $r = $this->withSession($this->sesion)->withHeaders(['X-CSRF-TOKEN' => csrf_hash()])->post("api/v1/yo/instancias/{$id}/hecha", []);
        service('superglobals')->setFilesArray([]);

        $this->assertError($r, 400, 'VALIDACION');
        $this->assertStringContainsString('3 MB', $this->cuerpo($r)['error']['campos']['foto']);
    }

    /**
     * CP-042 · PHP renombrado a `.jpg`: 400 `VALIDACION` por el MIME real.
     */
    public function testPhpRenombradoAJpgEs400(): void
    {
        $id = $this->instanciaDeLuna();
        $this->entrarPerfil('Luna');

        $r = $this->marcarConFoto($id, Imagenes::phpDisfrazado($this->temporal));

        $this->assertError($r, 400, 'VALIDACION');
        $this->assertArrayHasKey('foto', $this->cuerpo($r)['error']['campos']);
        $this->assertSame([], $this->evidencia($id));
        $this->assertSame([], glob(WRITEPATH . 'evidencias/*/*') ?: []);
    }

    public function testFotoOpcionalEnTareaQueNoLaPide(): void
    {
        $id = $this->instanciaDeLuna(false);
        $this->entrarPerfil('Luna');

        $this->assertTrue($this->cuerpo($this->conEstado($this->marcarConFoto($id, Imagenes::png($this->temporal), 'foto.png', 'image/png'), 200))['tiene_evidencia']);
        $this->assertSame('image/webp', $this->evidencia($id)['mime']);
    }

    public function testRemarcarUnaDevueltaReemplazaLaFoto(): void
    {
        $id = $this->instanciaDeLuna();
        $this->entrarPerfil('Luna');
        $this->marcarConFoto($id, Imagenes::png($this->temporal), 'a.png', 'image/png')->assertStatus(200);
        $vieja = WRITEPATH . 'evidencias/' . $this->evidencia($id)['ruta'];
        $this->db->table('tarea_instancias')->where('id', $id)->update(['estado' => 'devuelta', 'mensaje_devolucion' => 'Se ve borrosa']);

        $this->marcarConFoto($id, Imagenes::png($this->temporal, 500, 400), 'b.png', 'image/png')->assertStatus(200);

        $this->assertSame(1, $this->db->table('evidencias')->where('instancia_id', $id)->countAllResults());
        $nueva = WRITEPATH . 'evidencias/' . $this->evidencia($id)['ruta'];
        $this->assertNotSame($vieja, $nueva);
        $this->assertFileDoesNotExist($vieja);
        $this->assertFileExists($nueva);
        $this->assertSame([500, 400], array_slice((array) getimagesize($nueva), 0, 2));
    }

    /**
     * CP-043 · Otra hija pide `GET /evidencias/{id}`: 403 `PROHIBIDO`. La dueña y el padre en modo padre sí.
     */
    public function testSoloLaDuenaOElPadreVenLaFoto(): void
    {
        $id = $this->instanciaDeLuna();
        $this->entrarPerfil('Luna');
        $this->marcarConFoto($id, Imagenes::png($this->temporal), 'a.png', 'image/png')->assertStatus(200);
        $evidenciaId = (int) $this->evidencia($id)['id'];

        $duena = $this->api('get', "evidencias/{$evidenciaId}");
        $duena->assertStatus(200);
        $this->assertSame('image/webp', $duena->response()->getHeaderLine('Content-Type'));
        $this->assertSame('private, max-age=300', $duena->response()->getHeaderLine('Cache-Control'));
        $this->assertSame('RIFF', substr((string) $duena->response()->getBody(), 0, 4));

        $this->api('post', 'perfiles/salir')->assertStatus(200);
        $this->entrarPerfil('Sol');
        $this->api('delete', 'modo-padre')->assertStatus(200);
        $this->assertError($this->api('get', "evidencias/{$evidenciaId}"), 403, 'PROHIBIDO');

        $this->api('post', 'perfiles/salir')->assertStatus(200);
        $this->assertError($this->api('get', "evidencias/{$evidenciaId}"), 403, 'PROHIBIDO');

        $this->entrarModoPadre();
        $this->api('get', "evidencias/{$evidenciaId}")->assertStatus(200);
        $this->assertError($this->api('get', 'evidencias/999999'), 404, 'NO_ENCONTRADO');

        $this->sesion = [];
        $this->assertError($this->api('get', "evidencias/{$evidenciaId}"), 401, 'SIN_SESION');
    }

    /**
     * CP-044 · Purga de evidencias vencidas: archivo y fila eliminados.
     */
    public function testPurgaDeEvidenciasVencidas(): void
    {
        $instancias = [$this->instanciaDeLuna(), $this->instanciaDeLuna(), $this->instanciaDeLuna()];
        $perfil     = $this->perfilId('Luna');
        @mkdir(WRITEPATH . "evidencias/{$perfil}", 0o775, true);
        $ahora = date('Y-m-d H:i:s');

        foreach ([$this->hoy(-1), $this->hoy(), null] as $i => $borrar) {
            file_put_contents(WRITEPATH . "evidencias/{$perfil}/{$i}.webp", 'webp');
            $this->db->table('evidencias')->insert([
                'instancia_id' => $instancias[$i], 'ruta' => "{$perfil}/{$i}.webp", 'mime' => 'image/webp', 'bytes' => 4,
                'borrar_despues' => $borrar, 'created_at' => $ahora,
            ]);
        }

        $borradas = service('evidencias')->purgar($this->hoy());

        $this->assertSame(1, $borradas);
        $this->assertFileDoesNotExist(WRITEPATH . "evidencias/{$perfil}/0.webp");
        $this->assertFileExists(WRITEPATH . "evidencias/{$perfil}/1.webp");
        $this->assertFileExists(WRITEPATH . "evidencias/{$perfil}/2.webp");
        $this->assertSame([$instancias[1], $instancias[2]], array_map('intval', array_column(
            $this->db->table('evidencias')->orderBy('instancia_id')->get()->getResultArray(),
            'instancia_id',
        )));
    }

    public function testElCronPurgaConForzar(): void
    {
        $instancia = $this->instanciaDeLuna();
        $perfil    = $this->perfilId('Luna');
        @mkdir(WRITEPATH . "evidencias/{$perfil}", 0o775, true);
        file_put_contents(WRITEPATH . "evidencias/{$perfil}/vieja.webp", 'webp');
        $this->db->table('evidencias')->insert([
            'instancia_id' => $instancia, 'ruta' => "{$perfil}/vieja.webp", 'mime' => 'image/webp', 'bytes' => 4,
            'borrar_despues' => $this->hoy(-3), 'created_at' => date('Y-m-d H:i:s'),
        ]);

        command('tareas:programadas --forzar');

        $this->assertSame(0, $this->db->table('evidencias')->countAllResults());
        $this->assertFileDoesNotExist(WRITEPATH . "evidencias/{$perfil}/vieja.webp");
    }

    public function testAprobarFijaBorrarDespues(): void
    {
        $id = $this->instanciaDeLuna();
        $this->entrarPerfil('Luna');
        $this->marcarConFoto($id, Imagenes::png($this->temporal), 'a.png', 'image/png')->assertStatus(200);
        $this->db->table('configuracion')->where('clave', 'evidencia_dias_retencion')->update(['valor' => '30']);

        $this->api('post', "admin/instancias/{$id}/revision", ['decision' => 'aprobada'])->assertStatus(200);

        $this->assertSame($this->hoy(30), $this->evidencia($id)['borrar_despues']);
    }
}
