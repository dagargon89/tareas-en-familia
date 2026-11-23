<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Niveles;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * T-S3-02 · Niveles por XP (RF-08). CP-050.
 *
 * @internal
 */
#[CoversClass(Niveles::class)]
final class NivelesTest extends TestCase
{
    /**
     * @return iterable<string, array{0: int, 1: string}>
     */
    public static function tramos(): iterable
    {
        yield '0'    => [0, 'Semilla'];
        yield '99'   => [99, 'Semilla'];
        yield '100'  => [100, 'Brote'];
        yield '299'  => [299, 'Brote'];
        yield '300'  => [300, 'Raíz'];
        yield '3000' => [3000, 'Selva'];
    }

    /**
     * CP-050 · Nivel con 0, 99, 100, 299, 300 y 3000 XP.
     */
    #[DataProvider('tramos')]
    public function testNivelPorXp(int $xp, string $nombre): void
    {
        $this->assertSame($nombre, Niveles::para($xp)['nombre']);
    }

    public function testFormaDeNivelYSiguiente(): void
    {
        $this->assertSame(['nombre' => 'Raíz', 'xp_desde' => 300, 'xp_hasta' => 699], Niveles::para(612));
        $this->assertSame(['nombre' => 'Árbol', 'xp_desde' => 700, 'xp_hasta' => 1499], Niveles::siguiente(612));
        $this->assertSame(['nombre' => 'Selva', 'xp_desde' => 3000, 'xp_hasta' => null], Niveles::para(99_999));
        $this->assertNull(Niveles::siguiente(3000));
        $this->assertSame('Semilla', Niveles::para(-5)['nombre'], 'Un XP negativo no existe; cae en el primer tramo.');
    }

    public function testTramosDelSrs(): void
    {
        $this->assertSame(
            ['Semilla' => 0, 'Brote' => 100, 'Raíz' => 300, 'Árbol' => 700, 'Bosque' => 1500, 'Selva' => 3000],
            Niveles::TRAMOS,
        );
    }
}
