<?php

declare(strict_types=1);

namespace Tests\Unit\Libraries;

use App\Exceptions\ApiException;
use App\Libraries\ErrorApi;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Formato de error del doc 05 §1: `{ "error": { "codigo", "mensaje", "campos?" } }`.
 *
 * @internal
 */
final class ErrorApiTest extends CIUnitTestCase
{
    public function testCuerpoSinCamposNoIncluyeLaLlave(): void
    {
        $cuerpo = ErrorApi::cuerpo('NO_ENCONTRADO', 'No existe.');

        $this->assertSame(['error' => ['codigo' => 'NO_ENCONTRADO', 'mensaje' => 'No existe.']], $cuerpo);
    }

    public function testCuerpoConCamposYExtras(): void
    {
        $cuerpo = ErrorApi::cuerpo('PIN_INCORRECTO', 'PIN incorrecto.', null, ['intentos_restantes' => 3]);
        $this->assertSame(3, $cuerpo['error']['intentos_restantes']);

        $cuerpo = ErrorApi::cuerpo('VALIDACION', 'Revisa los campos.', ['titulo' => 'Es obligatorio.']);
        $this->assertSame(['titulo' => 'Es obligatorio.'], $cuerpo['error']['campos']);
    }

    public function testRespuestaJsonConEstado(): void
    {
        $respuesta = ErrorApi::respuesta(409, 'ESTADO_INVALIDO', 'No se puede.');

        $this->assertSame(409, $respuesta->getStatusCode());
        $this->assertStringStartsWith('application/json', $respuesta->getHeaderLine('Content-Type'));
        $this->assertSame(
            ['error' => ['codigo' => 'ESTADO_INVALIDO', 'mensaje' => 'No se puede.']],
            json_decode((string) $respuesta->getBody(), true),
        );
    }

    public function testApiExceptionProduceLaRespuestaDeError(): void
    {
        $excepcion = ApiException::validacion(['email' => 'Correo inválido.']);
        $respuesta = $excepcion->getResponse();

        $this->assertSame(400, $respuesta->getStatusCode());
        $cuerpo = json_decode((string) $respuesta->getBody(), true);
        $this->assertSame('VALIDACION', $cuerpo['error']['codigo']);
        $this->assertSame(['email' => 'Correo inválido.'], $cuerpo['error']['campos']);
    }
}
