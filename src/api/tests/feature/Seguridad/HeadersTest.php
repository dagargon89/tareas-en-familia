<?php

declare(strict_types=1);

namespace Tests\Feature\Seguridad;

use App\Filters\SecurityHeadersFilter;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use Config\Services;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * CP-SEG-04 · Headers de seguridad en cualquier respuesta (doc 04 §3.4).
 *
 * Los valores esperados se leen del propio doc 04 para que el código no
 * pueda divergir de la fuente.
 *
 * @internal
 */
final class HeadersTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    /**
     * Headers del bloque de código de doc 04 §3.4.
     *
     * @return array<string, string>
     */
    private static function headersDelDoc04(): array
    {
        $ruta = dirname(__DIR__, 5) . '/docs/04-seguridad/04_seguridad.md';
        $doc  = (string) file_get_contents($ruta);

        if (preg_match('/### 3\.4 Headers.*?```text\n(.*?)```/s', $doc, $m) !== 1) {
            self::fail('No se encontró el bloque de headers en ' . $ruta);
        }

        $headers = [];

        foreach (array_filter(explode("\n", trim($m[1]))) as $linea) {
            [$nombre, $valor]         = explode(':', $linea, 2);
            $headers[trim($nombre)] = trim($valor);
        }

        return $headers;
    }

    private function assertHeadersDeSeguridad(TestResponse $resultado, bool $conHsts = false): void
    {
        $respuesta = $resultado->response();

        foreach (self::headersDelDoc04() as $nombre => $valor) {
            if ($nombre === 'Strict-Transport-Security') {
                if ($conHsts) {
                    $this->assertSame($valor, $respuesta->getHeaderLine($nombre));
                } else {
                    $this->assertFalse($respuesta->hasHeader($nombre), 'HSTS solo debe enviarse en producción.');
                }

                continue;
            }

            $this->assertSame($valor, $respuesta->getHeaderLine($nombre), "Header {$nombre}");
        }
    }

    public function testElDoc04DefineLosSieteHeaders(): void
    {
        $this->assertCount(7, self::headersDelDoc04());
        $this->assertArrayHasKey('Strict-Transport-Security', self::headersDelDoc04());
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: int}>
     */
    public static function respuestas(): iterable
    {
        yield 'salud (200 JSON)'        => ['get', 'api/v1/salud', 200];
        yield 'csrf (200 JSON)'         => ['get', 'api/v1/csrf', 200];
        yield 'API inexistente (404)'   => ['get', 'api/v1/no-existe', 404];
        yield 'método no registrado'    => ['post', 'api/v1/salud', 404];
        yield 'fuera de la API (404)'   => ['get', 'no-existe', 404];
        yield 'raíz (redirección 3xx)'  => ['get', '/', 302];
    }

    #[DataProvider('respuestas')]
    public function testCualquierRespuestaTraeLosHeaders(string $metodo, string $ruta, int $estado): void
    {
        $resultado = $this->call($metodo, $ruta);

        $resultado->assertStatus($estado);
        $this->assertHeadersDeSeguridad($resultado);
    }

    public function testRespuestaCortadaPorCsrfTraeLosHeaders(): void
    {
        $this->withRoutes([
            ['post', 'api/v1/prueba-csrf', static fn () => service('response')->setJSON(['ok' => true])],
        ]);

        $resultado = $this->post('api/v1/prueba-csrf');

        $resultado->assertStatus(403);
        $this->assertHeadersDeSeguridad($resultado);
    }

    public function testHstsSoloEnProduccion(): void
    {
        $doc = self::headersDelDoc04();

        $produccion = SecurityHeadersFilter::aplicar(Services::response(null, false), true);
        $this->assertSame($doc['Strict-Transport-Security'], $produccion->getHeaderLine('Strict-Transport-Security'));

        $desarrollo = SecurityHeadersFilter::aplicar(Services::response(null, false), false);
        $this->assertFalse($desarrollo->hasHeader('Strict-Transport-Security'));
    }

    public function testElFiltroUsaExactamenteLosValoresDelDoc04(): void
    {
        $doc = self::headersDelDoc04();

        $this->assertSame($doc['Strict-Transport-Security'], SecurityHeadersFilter::HSTS);
        unset($doc['Strict-Transport-Security']);
        $this->assertEqualsCanonicalizing($doc, SecurityHeadersFilter::HEADERS);
    }
}
