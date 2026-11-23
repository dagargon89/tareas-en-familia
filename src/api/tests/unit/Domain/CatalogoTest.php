<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Catalogo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * El catálogo del backend es exactamente el del frontend (doc 08 §5).
 *
 * @internal
 */
#[CoversClass(Catalogo::class)]
final class CatalogoTest extends TestCase
{
    /**
     * Claves del objeto `export const <NOMBRE> = { clave: Icono, ... }` de un archivo TS del frontend.
     *
     * @return list<string>
     */
    private static function clavesTs(string $archivo, string $constante): array
    {
        $fuente = (string) file_get_contents(dirname(__DIR__, 5) . '/src/web/src/lib/' . $archivo);

        if (preg_match('/export const ' . $constante . ' = \{(.*?)\n\}/s', $fuente, $bloque) !== 1) {
            self::fail("No se encontró {$constante} en {$archivo}");
        }

        preg_match_all('/^\s+(\w+): \w+,/m', $bloque[1], $claves);

        return $claves[1];
    }

    public function testIconosIgualesAlFrontend(): void
    {
        $this->assertSame(self::clavesTs('iconos.ts', 'ICONOS'), Catalogo::ICONOS);
        $this->assertCount(52, Catalogo::ICONOS);
    }

    public function testAvataresIgualesAlFrontend(): void
    {
        $this->assertSame(self::clavesTs('avatares.ts', 'AVATARES'), Catalogo::AVATARES);
        $this->assertCount(16, Catalogo::AVATARES);
    }

    public function testValidadores(): void
    {
        $this->assertTrue(Catalogo::esIcono('cama'));
        $this->assertFalse(Catalogo::esIcono('bed'));
        $this->assertFalse(Catalogo::esIcono(null));
        $this->assertTrue(Catalogo::esAvatar('conejo'));
        $this->assertFalse(Catalogo::esAvatar('zorro'));
        $this->assertFalse(Catalogo::esAvatar(7));
    }
}
