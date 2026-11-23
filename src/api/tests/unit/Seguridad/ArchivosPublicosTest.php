<?php

declare(strict_types=1);

namespace Tests\Unit\Seguridad;

use App\Filters\SecurityHeadersFilter;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * `public/.htaccess` y `public/robots.txt` (T-S0-03). La prueba en servidor
 * real de archivos ocultos es CP-SEG-08 (manual, T-S0-05).
 *
 * @internal
 */
final class ArchivosPublicosTest extends CIUnitTestCase
{
    private function htaccess(): string
    {
        return (string) file_get_contents(FCPATH . '.htaccess');
    }

    public function testRobotsNiegaTodo(): void
    {
        $this->assertMatchesRegularExpression('/^Disallow: \/$/m', (string) file_get_contents(FCPATH . 'robots.txt'));
    }

    public function testHtaccessNiegaOcultosYReescribe(): void
    {
        $htaccess = $this->htaccess();

        $this->assertStringContainsString('Options -Indexes', $htaccess);
        $this->assertStringContainsString('RewriteRule (^|/)\.(?!well-known/) - [F,L]', $htaccess);
        $this->assertStringContainsString('RewriteRule ^app/ app/index.html [L]', $htaccess);
        $this->assertStringContainsString('RewriteRule ^([\s\S]*)$ index.php/$1 [L,NC,QSA]', $htaccess);
    }

    public function testHtaccessEnviaLosMismosHeadersQueElFiltro(): void
    {
        $htaccess = $this->htaccess();

        foreach (SecurityHeadersFilter::HEADERS as $nombre => $valor) {
            $this->assertStringContainsString("Header always set {$nombre} \"{$valor}\"", $htaccess);
        }

        $this->assertStringContainsString(
            'Header always set Strict-Transport-Security "' . SecurityHeadersFilter::HSTS . '"',
            $htaccess,
        );
    }
}
