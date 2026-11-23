<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Rachas;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * T-S4-02 · Reglas de racha semanal (RF-10): CP-065 a CP-067.
 *
 * @internal
 */
final class RachasTest extends CIUnitTestCase
{
    /**
     * CP-065 · 10 asignadas, 8 aprobadas, umbral 80: cumplida.
     */
    public function testSemanaConOchoDeDiezYUmbralOchentaEsCumplida(): void
    {
        $this->assertTrue(Rachas::cumplida(10, 8, 80));
        $this->assertFalse(Rachas::cumplida(10, 7, 80));
        $this->assertTrue(Rachas::cumplida(10, 7, 70));
        $this->assertTrue(Rachas::cumplida(3, 3, 100));
    }

    public function testSemanaSinInstanciasNoCuenta(): void
    {
        $this->assertNull(Rachas::cumplida(0, 0, 80));
    }

    public function testPorcentajeConDosDecimales(): void
    {
        $this->assertSame(80.0, Rachas::porcentaje(10, 8));
        $this->assertSame(66.67, Rachas::porcentaje(3, 2));
        $this->assertSame(0.0, Rachas::porcentaje(0, 0));
    }

    /**
     * El umbral se compara con el porcentaje redondeado que se guarda en `rachas.porcentaje`.
     */
    public function testUmbralContraElPorcentajeGuardado(): void
    {
        $this->assertFalse(Rachas::cumplida(3, 2, 67));
        $this->assertTrue(Rachas::cumplida(3, 2, 66));
    }

    /**
     * CP-066 · Cumplida, cumplida, no, cumplida (de la más antigua a la más reciente): racha 1.
     */
    public function testRachaSeCuentaDesdeLaUltimaSemanaHaciaAtras(): void
    {
        $this->assertSame(1, Rachas::contar([true, true, false, true]));
        $this->assertSame(0, Rachas::contar([true, true, false]));
        $this->assertSame(3, Rachas::contar([true, true, true]));
        $this->assertSame(0, Rachas::contar([]));
    }

    /**
     * CP-067 · Una semana sin instancias entre dos cumplidas no rompe: racha 2.
     */
    public function testSemanaSinInstanciasNoRompeNiSuma(): void
    {
        $this->assertSame(2, Rachas::contar([true, null, true]));
        $this->assertSame(1, Rachas::contar([false, null, true, null]));
        $this->assertSame(0, Rachas::contar([null, null]));
    }
}
