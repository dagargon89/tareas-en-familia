<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\ReglaRecurrencia;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * T-S2-01 · Regla de recurrencia (RF-03). CP-020, CP-021, CP-022.
 *
 * @internal
 */
#[CoversClass(ReglaRecurrencia::class)]
final class ReglaRecurrenciaTest extends TestCase
{
    /**
     * CP-020 · Diaria del 1 al 7: 7 fechas.
     */
    public function testDiariaDelUnoAlSiete(): void
    {
        $regla = new ReglaRecurrencia('diaria', [], '2026-12-01', null);

        $this->assertSame(
            ['2026-12-01', '2026-12-02', '2026-12-03', '2026-12-04', '2026-12-05', '2026-12-06', '2026-12-07'],
            $regla->fechasEntre('2026-12-01', '2026-12-07'),
        );
    }

    /**
     * CP-021 · Semanal `lu,mi,vi` en 2 semanas: 6 fechas, todas lunes, miércoles o viernes.
     */
    public function testSemanalLunesMiercolesViernesEnDosSemanas(): void
    {
        $regla  = new ReglaRecurrencia('semanal', ['vi', 'lu', 'mi'], '2026-11-30', null);
        $fechas = $regla->fechasEntre('2026-11-30', '2026-12-13');

        $this->assertSame(
            ['2026-11-30', '2026-12-02', '2026-12-04', '2026-12-07', '2026-12-09', '2026-12-11'],
            $fechas,
        );

        foreach ($fechas as $fecha) {
            $this->assertContains((int) date('N', strtotime($fecha)), [1, 3, 5]);
        }
    }

    /**
     * CP-022 · `fecha_fin` antes del rango: 0 fechas.
     */
    public function testFechaFinAntesDelRango(): void
    {
        $regla = new ReglaRecurrencia('diaria', [], '2026-11-01', '2026-11-20');

        $this->assertSame([], $regla->fechasEntre('2026-12-01', '2026-12-07'));
    }

    public function testRangoSeRecortaAInicioYFin(): void
    {
        $regla = new ReglaRecurrencia('diaria', [], '2026-12-03', '2026-12-05');

        $this->assertSame(['2026-12-03', '2026-12-04', '2026-12-05'], $regla->fechasEntre('2026-12-01', '2026-12-31'));
        $this->assertSame([], $regla->fechasEntre('2026-11-01', '2026-11-30'), 'Rango antes del inicio.');
        $this->assertSame([], $regla->fechasEntre('2026-12-10', '2026-12-01'), 'Rango invertido.');
    }

    public function testUnaVez(): void
    {
        $regla = new ReglaRecurrencia('una_vez', ['lu'], '2026-12-04', null);

        $this->assertSame(['2026-12-04'], $regla->fechasEntre('2026-12-01', '2026-12-07'));
        $this->assertSame([], $regla->fechasEntre('2026-12-05', '2026-12-07'));
        $this->assertSame([], $regla->diasSemana(), 'Los días solo aplican a la semanal.');
        $this->assertTrue($regla->aplicaEn('2026-12-04'));
        $this->assertFalse($regla->aplicaEn('2026-12-05'));
    }

    public function testDomingoYDiasRepetidos(): void
    {
        $regla = new ReglaRecurrencia('semanal', ['do', 'do', 'sa'], '2026-12-01', null);

        $this->assertSame(['sa', 'do'], $regla->diasSemana());
        $this->assertSame(['2026-12-05', '2026-12-06'], $regla->fechasEntre('2026-12-01', '2026-12-07'));
    }

    public function testAccesores(): void
    {
        $regla = new ReglaRecurrencia('semanal', ['ma'], '2026-12-01', '2026-12-31');

        $this->assertSame('semanal', $regla->frecuencia());
        $this->assertSame('2026-12-01', $regla->fechaInicio());
        $this->assertSame('2026-12-31', $regla->fechaFin());
        $this->assertSame('ma', $regla->diasComoSet());
    }

    public function testDesdeFilaConSetDeMariaDb(): void
    {
        $regla = ReglaRecurrencia::desdeFila([
            'frecuencia' => 'semanal', 'dias_semana' => 'lu,mi', 'fecha_inicio' => '2026-12-01', 'fecha_fin' => null,
        ]);

        $this->assertSame(['lu', 'mi'], $regla->diasSemana());

        $diaria = ReglaRecurrencia::desdeFila([
            'frecuencia' => 'diaria', 'dias_semana' => null, 'fecha_inicio' => '2026-12-01', 'fecha_fin' => '',
        ]);
        $this->assertNull($diaria->fechaFin());
        $this->assertNull($diaria->diasComoSet());
    }

    /**
     * @return iterable<string, array{0: string, 1: list<string>, 2: string, 3: string|null}>
     */
    public static function invalidas(): iterable
    {
        yield 'frecuencia desconocida' => ['mensual', [], '2026-12-01', null];
        yield 'semanal sin días'       => ['semanal', [], '2026-12-01', null];
        yield 'día inválido'           => ['semanal', ['xx'], '2026-12-01', null];
        yield 'inicio inválido'        => ['diaria', [], '2026-02-30', null];
        yield 'inicio con formato'     => ['diaria', [], '01/12/2026', null];
        yield 'fin inválido'           => ['diaria', [], '2026-12-01', '2026-13-01'];
        yield 'fin antes del inicio'   => ['diaria', [], '2026-12-05', '2026-12-01'];
    }

    /**
     * @param list<string> $dias
     */
    #[DataProvider('invalidas')]
    public function testReglasInvalidas(string $frecuencia, array $dias, string $inicio, ?string $fin): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ReglaRecurrencia($frecuencia, $dias, $inicio, $fin);
    }

    public function testFechasEntreConFechasInvalidas(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ReglaRecurrencia('diaria', [], '2026-12-01', null))->fechasEntre('ayer', '2026-12-07');
    }

    public function testEsFechaValida(): void
    {
        $this->assertTrue(ReglaRecurrencia::esFechaValida('2028-02-29'));
        $this->assertFalse(ReglaRecurrencia::esFechaValida('2027-02-29'));
        $this->assertFalse(ReglaRecurrencia::esFechaValida('2026-1-01'));
    }
}
