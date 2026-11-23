<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Calendario;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * "Hoy" en America/Ciudad_Juarez (RN-06). CP-034.
 *
 * @internal
 */
#[CoversClass(Calendario::class)]
final class CalendarioTest extends TestCase
{
    /**
     * CP-034 · A las 23:30 en Ciudad Juárez ya es el día siguiente en UTC; "hoy" es la fecha local.
     */
    public function testHoyALas2330EnCiudadJuarezUsaLaFechaLocal(): void
    {
        $local = new DateTimeImmutable('2026-12-01 23:30:00', new DateTimeZone('America/Ciudad_Juarez'));
        $utc   = $local->setTimezone(new DateTimeZone('UTC'));

        $this->assertSame('2026-12-02', $utc->format('Y-m-d'), 'En UTC ya es 2 de diciembre.');
        $this->assertSame('2026-12-01', Calendario::hoy($utc));
        $this->assertSame('2026-12-01', Calendario::hoy($local));
    }

    public function testHorarioDeVerano(): void
    {
        // 23:30 del 1 de julio (UTC-6) = 05:30 UTC del 2 de julio.
        $this->assertSame('2026-07-01', Calendario::hoy(new DateTimeImmutable('2026-07-02 05:30:00', new DateTimeZone('UTC'))));
        $this->assertSame('2026-07-02', Calendario::hoy(new DateTimeImmutable('2026-07-02 06:00:00', new DateTimeZone('UTC'))));
    }

    public function testSumarDiasYDiferencia(): void
    {
        $this->assertSame('2026-12-08', Calendario::sumarDias('2026-12-01', 7));
        $this->assertSame('2026-11-24', Calendario::sumarDias('2026-12-01', -7));
        $this->assertSame(7, Calendario::diasEntre('2026-12-01', '2026-12-08'));
        $this->assertSame(-3, Calendario::diasEntre('2026-12-04', '2026-12-01'));
        $this->assertSame('America/Ciudad_Juarez', Calendario::ZONA);
    }
}
