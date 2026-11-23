<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Racha;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * T-S4-02 · Rachas semanales (RF-10). CP-065, CP-066, CP-067.
 *
 * @internal
 */
#[CoversClass(Racha::class)]
final class RachaTest extends TestCase
{
    /**
     * CP-065 · 10 asignadas y 8 aprobadas con umbral 80: cumplida.
     */
    public function testDiezAsignadasOchoAprobadasUmbral80EsCumplida(): void
    {
        $this->assertTrue(Racha::semanaCumplida(10, 8, 80));
        $this->assertFalse(Racha::semanaCumplida(10, 7, 80));
        $this->assertTrue(Racha::semanaCumplida(10, 6, 60));
        $this->assertFalse(Racha::semanaCumplida(0, 0, 80), 'Sin asignadas no hay semana que cumplir.');
        $this->assertTrue(Racha::semanaCumplida(3, 3, 100));
    }

    public function testPorcentaje(): void
    {
        $this->assertSame(80.0, Racha::porcentaje(10, 8));
        $this->assertSame(66.67, Racha::porcentaje(3, 2));
        $this->assertSame(0.0, Racha::porcentaje(0, 0));
    }

    /**
     * CP-066 · Cumplida, cumplida, no, cumplida (de la más antigua a la más reciente): racha = 1.
     */
    public function testUnaSemanaNoCumplidaReiniciaLaRacha(): void
    {
        $this->assertSame(1, Racha::actual([true, true, false, true]));
    }

    /**
     * CP-067 · Una semana sin instancias entre dos cumplidas no rompe: racha = 2.
     */
    public function testSemanaSinInstanciasNoRompeNiSuma(): void
    {
        $this->assertSame(2, Racha::actual([true, null, true]));
    }

    public function testCasosBorde(): void
    {
        $this->assertSame(0, Racha::actual([]));
        $this->assertSame(0, Racha::actual([true, false]));
        $this->assertSame(0, Racha::actual([null, null]));
        $this->assertSame(3, Racha::actual([false, true, true, null, true, null]));
    }
}
