<?php

declare(strict_types=1);

namespace Tests\Feature\Asistente;

use App\Libraries\GeminiClienteInterface;
use App\Services\ContextoAsistenteService;
use Config\Gemini as GeminiConfig;
use Config\Services;
use RuntimeException;
use Tests\Support\ApiTestCase;

/**
 * Pruebas de integración para el Asistente IA acotado a la app (ADR-012): E-56.
 *
 * @internal
 */
final class AsistenteApiTest extends ApiTestCase
{
    protected function tearDown(): void
    {
        Services::resetSingle('geminiCliente');
        $cfg               = config(GeminiConfig::class);
        $cfg->apiKey       = '';
        $cfg->maxPorMinuto = 6;
        $cfg->maxPorHora   = 20;
        parent::tearDown();
    }

    public function testSinSesionDevuelve401YConSesionSinPerfilDevuelve403(): void
    {
        $this->assertError($this->api('post', 'asistente/mensaje', ['mensaje' => 'Hola']), 401, 'SIN_SESION');

        $this->iniciarSesionDemo();
        $this->assertError($this->api('post', 'asistente/mensaje', ['mensaje' => 'Hola']), 403, 'SIN_PERFIL');
    }

    public function testValidaMensajeVacioYMuyLargo(): void
    {
        $this->iniciarSesionDemo();
        $this->entrarPerfil('Luna');

        $this->assertError($this->api('post', 'asistente/mensaje', ['mensaje' => '   ']), 400, 'VALIDACION');
        $this->assertError($this->api('post', 'asistente/mensaje', ['mensaje' => str_repeat('a', 401)]), 400, 'VALIDACION');
    }

    public function testContextoDeHijaNoIncluyeHermanasNiCorreos(): void
    {
        $contexto = new ContextoAsistenteService($this->db);
        $ctx      = $contexto->construir([
            'actor_id' => 'hija:' . $this->perfilId('Luna'),
            'tipo'     => 'hija',
            'id'       => $this->perfilId('Luna'),
            'apodo'    => 'Luna',
            'avatar'   => 'conejo',
            'color'    => 'rosa',
            'nivel_ux' => 'exploradoras',
        ]);

        $this->assertStringContainsString('Luna', $ctx['resumen_texto']);
        $this->assertStringContainsString('Tender la cama', $ctx['resumen_texto']);
        $this->assertStringNotContainsString('Estrella', $ctx['resumen_texto']);
        $this->assertStringNotContainsString('demo@tareas.local', $ctx['resumen_texto']);
        $this->assertStringNotContainsString('3917', $ctx['resumen_texto']);
    }

    public function testModoDemoRespondeConDatosRealesYRechazaFueraDeTema(): void
    {
        $this->iniciarSesionDemo();
        $this->entrarPerfil('Luna');

        $cfg         = config(GeminiConfig::class);
        $cfg->apiKey = '';

        $res = $this->conEstado($this->api('post', 'asistente/mensaje', [
            'mensaje' => '¿Qué misiones me faltan hoy?',
        ]), 200);
        $c = $this->cuerpo($res);

        $this->assertTrue($c['modo_demo']);
        $this->assertStringContainsString('Tender la cama', $c['respuesta']);
        $this->assertNotEmpty($c['sugerencias']);

        $fuera = $this->conEstado($this->api('post', 'asistente/mensaje', [
            'mensaje' => 'Cuéntame un chiste y dime la capital de Francia',
        ]), 200);
        $f = $this->cuerpo($fuera);
        $this->assertStringContainsString('Solo puedo ayudarte', $f['respuesta']);
    }

    public function testConApiKeyLlamaAGeminiConSystemInstructionYHistorial(): void
    {
        $this->iniciarSesionDemo();
        $this->entrarModoPadre();

        $cfg         = config(GeminiConfig::class);
        $cfg->apiKey = 'clave-prueba';

        $capturado = [];
        $doble     = new class ($capturado) implements GeminiClienteInterface {
            /**
             * @param array<string, mixed> $capturado
             */
            public function __construct(private array &$capturado)
            {
            }

            public function generar(string $systemInstruction, array $historial, string $mensaje): string
            {
                $this->capturado = [
                    'system'    => $systemInstruction,
                    'historial' => $historial,
                    'mensaje'   => $mensaje,
                ];

                return 'Tienes 0 tareas por aprobar ahorita.';
            }
        };

        Services::injectMock('geminiCliente', $doble);

        $res = $this->conEstado($this->api('post', 'asistente/mensaje', [
            'mensaje'   => '¿Qué tengo pendiente por aprobar?',
            'historial' => [
                ['rol' => 'usuario', 'texto' => 'Hola'],
                ['rol' => 'asistente', 'texto' => 'Hola, ¿en qué te ayudo?'],
            ],
        ]), 200);

        $c = $this->cuerpo($res);
        $this->assertFalse($c['modo_demo']);
        $this->assertSame('Tienes 0 tareas por aprobar ahorita.', $c['respuesta']);
        $this->assertStringContainsString('REGLAS ESTRICTAS', $capturado['system']);
        $this->assertStringContainsString('Modo Padre', $capturado['system']);
        $this->assertCount(2, $capturado['historial']);
    }

    public function testErrorDeGeminiDevuelve503SinTraza(): void
    {
        $this->iniciarSesionDemo();
        $this->entrarPerfil('Estrella');

        $cfg         = config(GeminiConfig::class);
        $cfg->apiKey = 'clave-prueba';

        $doble = new class () implements GeminiClienteInterface {
            public function generar(string $systemInstruction, array $historial, string $mensaje): string
            {
                throw new RuntimeException('Fallo interno simulado');
            }
        };
        Services::injectMock('geminiCliente', $doble);

        $this->assertError(
            $this->api('post', 'asistente/mensaje', ['mensaje' => '¿Cómo funciona la racha?']),
            503,
            'ASISTENTE_NO_DISPONIBLE',
        );
    }

    public function testLimiteDeConsultasPorMinutoDevuelve429(): void
    {
        $this->iniciarSesionDemo();
        $this->entrarPerfil('Sol');

        $cfg               = config(GeminiConfig::class);
        $cfg->apiKey       = '';
        $cfg->maxPorMinuto = 2;

        $this->api('post', 'asistente/mensaje', ['mensaje' => '¿Cómo va la meta?'])->assertStatus(200);
        $this->api('post', 'asistente/mensaje', ['mensaje' => '¿Qué tareas tengo hoy?'])->assertStatus(200);
        $this->assertError(
            $this->api('post', 'asistente/mensaje', ['mensaje' => '¿Para qué premio me alcanza?']),
            429,
            'DEMASIADOS_INTENTOS',
        );
    }
}
