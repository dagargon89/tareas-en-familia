<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\SemanaIso;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * T-S2-01 · Semana ISO (`rachas.semana_iso` = `2026-W47`).
 *
 * @internal
 */
#[CoversClass(SemanaIso::class)]
final class SemanaIsoTest extends TestCase
{
    public function testDesdeFecha(): void
    {
        $semana = SemanaIso::desdeFecha('2026-11-22'); // domingo

        $this->assertSame('2026-W47', $semana->codigo());
        $this->assertSame('2026-11-16', $semana->lunes());
        $this->assertSame('2026-11-22', $semana->domingo());
        $this->assertSame(['2026-11-16', '2026-11-17', '2026-11-18', '2026-11-19', '2026-11-20', '2026-11-21', '2026-11-22'], $semana->dias());
        $this->assertSame('2026-W47', (string) $semana);
    }

    public function testCambioDeAnio(): void
    {
        $this->assertSame('2026-W53', SemanaIso::desdeFecha('2027-01-01')->codigo());
        $this->assertSame('2026-W01', SemanaIso::desdeFecha('2025-12-29')->codigo());
        $this->assertSame('2027-W01', SemanaIso::desdeFecha('2027-01-04')->codigo());
    }

    public function testDesdeCodigoYNavegacion(): void
    {
        $semana = SemanaIso::desdeCodigo('2026-W01');

        $this->assertSame('2025-12-29', $semana->lunes());
        $this->assertSame('2025-W52', $semana->anterior()->codigo());
        $this->assertSame('2026-W02', $semana->siguiente()->codigo());
        $this->assertSame(2026, $semana->anio());
        $this->assertSame(1, $semana->numero());
        $this->assertTrue($semana->contiene('2026-01-04'));
        $this->assertFalse($semana->contiene('2026-01-05'));
    }

    public function testCodigosInvalidos(): void
    {
        foreach (['2026-47', '2026-W00', '2026-W54', '2025-W53', 'W47'] as $codigo) {
            try {
                SemanaIso::desdeCodigo($codigo);
                $this->fail("Aceptó {$codigo}");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testFechaInvalida(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SemanaIso::desdeFecha('2026-02-30');
    }
}
