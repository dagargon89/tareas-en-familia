<?php

declare(strict_types=1);

namespace Tests\Unit\Seguridad;

use CodeIgniter\Session\Handlers\FileHandler;
use CodeIgniter\Test\CIUnitTestCase;
use Config\App;
use Config\Cookie;
use Config\Security;
use Config\Session;

/**
 * Sesión y cookie del doc 04 §3.1; CSRF por header (doc 05 §1); zona y
 * locale de T-S0-01. La verificación HTTP de la cookie (CP-SEG-07) es de S1.
 *
 * @internal
 */
final class SesionConfigTest extends CIUnitTestCase
{
    public function testSesionTfSesionFileHandlerTreintaDias(): void
    {
        $sesion = config(Session::class);

        $this->assertSame('tf_sesion', $sesion->cookieName);
        $this->assertSame(FileHandler::class, $sesion->driver);
        $this->assertSame(2_592_000, $sesion->expiration);
        $this->assertTrue($sesion->regenerateDestroy);
    }

    public function testCookieHttpOnlyLaxYSecureSoloEnProduccion(): void
    {
        $cookie = config(Cookie::class);

        $this->assertTrue($cookie->httponly);
        $this->assertSame('Lax', $cookie->samesite);
        $this->assertFalse($cookie->secure, 'En testing no hay HTTPS; en producción vale true.');
    }

    public function testCsrfPorHeaderSinRedireccion(): void
    {
        $seguridad = config(Security::class);

        $this->assertSame('X-CSRF-TOKEN', $seguridad->headerName);
        $this->assertSame('session', $seguridad->csrfProtection);
        $this->assertFalse($seguridad->redirect);
    }

    public function testZonaHorariaYLocale(): void
    {
        $app = config(App::class);

        $this->assertSame('America/Ciudad_Juarez', $app->appTimezone);
        $this->assertSame('es-MX', $app->defaultLocale);
        $this->assertSame('', $app->indexPage);
    }
}
