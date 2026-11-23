<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * T-S0-01 · E-01 (`GET /salud`), E-02 (`GET /csrf`) y formato uniforme de error.
 *
 * @internal
 */
final class SistemaTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    public function testSaludRespondeOkConVersion(): void
    {
        $resultado = $this->get('api/v1/salud');

        $resultado->assertStatus(200);
        $resultado->assertHeader('Content-Type', 'application/json; charset=UTF-8');

        $cuerpo = json_decode($resultado->getJSON(), true);
        $this->assertSame('ok', $cuerpo['estado']);
        $this->assertIsString($cuerpo['version']);
        $this->assertNotSame('', $cuerpo['version']);
    }

    public function testCsrfDevuelveElTokenDeLaSesion(): void
    {
        $resultado = $this->get('api/v1/csrf');

        $resultado->assertStatus(200);
        $cuerpo = json_decode($resultado->getJSON(), true);
        $this->assertIsString($cuerpo['token']);
        $this->assertSame(csrf_hash(), $cuerpo['token']);
        $this->assertSame(csrf_hash(), $resultado->response()->getHeaderLine('X-CSRF-TOKEN'));
    }

    public function testRutaApiInexistenteDevuelveNoEncontradoEnJson(): void
    {
        $resultado = $this->get('api/v1/no-existe');

        $resultado->assertStatus(404);
        $resultado->assertHeader('Content-Type', 'application/json; charset=UTF-8');
        $cuerpo = json_decode($resultado->getJSON(), true);
        $this->assertSame('NO_ENCONTRADO', $cuerpo['error']['codigo']);
        $this->assertIsString($cuerpo['error']['mensaje']);
        $this->assertArrayNotHasKey('campos', $cuerpo['error']);
    }

    public function testMetodoNoRegistradoEnApiTambienEsNoEncontrado(): void
    {
        $resultado = $this->post('api/v1/salud');

        $resultado->assertStatus(404);
        $this->assertSame('NO_ENCONTRADO', json_decode($resultado->getJSON(), true)['error']['codigo']);
    }
}
