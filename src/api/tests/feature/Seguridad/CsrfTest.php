<?php

declare(strict_types=1);

namespace Tests\Feature\Seguridad;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * CSRF por header `X-CSRF-TOKEN` (doc 05 §1) con 403 `CSRF` en JSON.
 *
 * @internal
 */
final class CsrfTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withRoutes([
            ['post', 'api/v1/prueba-csrf', static fn () => service('response')->setJSON(['ok' => true])],
            ['get', 'api/v1/csrf', '\App\Controllers\Api\V1\CsrfController::index'],
        ]);
    }

    public function testPostSinTokenResponde403Csrf(): void
    {
        $resultado = $this->post('api/v1/prueba-csrf');

        $resultado->assertStatus(403);
        $cuerpo = json_decode($resultado->getJSON(), true);
        $this->assertSame('CSRF', $cuerpo['error']['codigo']);
        $this->assertIsString($cuerpo['error']['mensaje']);
    }

    public function testPostConTokenInvalidoResponde403Csrf(): void
    {
        $resultado = $this->withHeaders(['X-CSRF-TOKEN' => 'token-falso'])->post('api/v1/prueba-csrf');

        $resultado->assertStatus(403);
        $this->assertSame('CSRF', json_decode($resultado->getJSON(), true)['error']['codigo']);
    }

    public function testPostConElTokenDeGetCsrfPasaYRenuevaElToken(): void
    {
        $token = json_decode($this->get('api/v1/csrf')->getJSON(), true)['token'];

        $resultado = $this->withHeaders(['X-CSRF-TOKEN' => $token])->post('api/v1/prueba-csrf');

        $resultado->assertStatus(200);
        $nuevo = $resultado->response()->getHeaderLine('X-CSRF-TOKEN');
        $this->assertNotSame('', $nuevo);
        $this->assertNotSame($token, $nuevo, 'El token se regenera tras una petición mutante.');
    }
}
