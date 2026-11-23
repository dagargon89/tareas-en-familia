<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Regla de repetición de una tarea (`tarea_recurrencias`, RF-03).
 * Objeto de valor sin dependencias: calcula en qué fechas (`Y-m-d`) aplica.
 */
final class ReglaRecurrencia
{
    public const FRECUENCIAS = ['una_vez', 'diaria', 'semanal'];

    /**
     * Día → número ISO-8601 (`N`: lunes 1 … domingo 7).
     */
    public const DIAS = ['lu' => 1, 'ma' => 2, 'mi' => 3, 'ju' => 4, 'vi' => 5, 'sa' => 6, 'do' => 7];

    /**
     * @var list<string>
     */
    private readonly array $dias;

    /**
     * @param list<string> $diasSemana Solo cuenta para `semanal` (obligatorio y no vacío)
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        private readonly string $frecuencia,
        array $diasSemana,
        private readonly string $fechaInicio,
        private readonly ?string $fechaFin,
    ) {
        if (! in_array($frecuencia, self::FRECUENCIAS, true)) {
            throw new InvalidArgumentException("Frecuencia desconocida: {$frecuencia}");
        }

        if (! self::esFechaValida($fechaInicio)) {
            throw new InvalidArgumentException("Fecha de inicio inválida: {$fechaInicio}");
        }

        if ($fechaFin !== null && (! self::esFechaValida($fechaFin) || $fechaFin < $fechaInicio)) {
            throw new InvalidArgumentException("Fecha de fin inválida: {$fechaFin}");
        }

        $dias = [];

        if ($frecuencia === 'semanal') {
            foreach ($diasSemana as $dia) {
                if (! isset(self::DIAS[$dia])) {
                    throw new InvalidArgumentException("Día inválido: {$dia}");
                }

                $dias[$dia] = self::DIAS[$dia];
            }

            if ($dias === []) {
                throw new InvalidArgumentException('La recurrencia semanal necesita al menos un día.');
            }

            asort($dias);
        }

        $this->dias = array_keys($dias);
    }

    /**
     * Desde una fila de `tarea_recurrencias` (`dias_semana` llega como SET: `lu,mi`).
     *
     * @param array<string, mixed> $fila
     */
    public static function desdeFila(array $fila): self
    {
        $dias = is_string($fila['dias_semana'] ?? null) && $fila['dias_semana'] !== ''
            ? explode(',', $fila['dias_semana'])
            : [];
        $fin = is_string($fila['fecha_fin'] ?? null) && $fila['fecha_fin'] !== '' ? $fila['fecha_fin'] : null;

        return new self((string) $fila['frecuencia'], $dias, (string) $fila['fecha_inicio'], $fin);
    }

    public static function esFechaValida(string $fecha): bool
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha, new DateTimeZone('UTC'));

        return $d !== false && $d->format('Y-m-d') === $fecha;
    }

    public function frecuencia(): string
    {
        return $this->frecuencia;
    }

    /**
     * @return list<string> Ordenados de lunes a domingo; vacío si no es semanal
     */
    public function diasSemana(): array
    {
        return $this->dias;
    }

    /**
     * Valor para la columna SET (`lu,mi`), o `null` si no es semanal.
     */
    public function diasComoSet(): ?string
    {
        return $this->dias === [] ? null : implode(',', $this->dias);
    }

    public function fechaInicio(): string
    {
        return $this->fechaInicio;
    }

    public function fechaFin(): ?string
    {
        return $this->fechaFin;
    }

    public function aplicaEn(string $fecha): bool
    {
        return $this->fechasEntre($fecha, $fecha) !== [];
    }

    /**
     * Fechas en que aplica la regla dentro de `[desde, hasta]`, en orden.
     *
     * @return list<string>
     *
     * @throws InvalidArgumentException
     */
    public function fechasEntre(string $desde, string $hasta): array
    {
        if (! self::esFechaValida($desde) || ! self::esFechaValida($hasta)) {
            throw new InvalidArgumentException('Rango de fechas inválido.');
        }

        // `una_vez` solo aplica en su fecha de inicio; las demás, de inicio a fin (o sin fin).
        $ultimo = $this->frecuencia === 'una_vez' ? $this->fechaInicio : ($this->fechaFin ?? $hasta);
        $inicio = max($desde, $this->fechaInicio);
        $fin    = min($hasta, $ultimo);

        $fechas = [];
        $zona   = new DateTimeZone('UTC');
        $dia    = new DateTimeImmutable($inicio, $zona);
        $limite = new DateTimeImmutable($fin, $zona);

        while ($dia <= $limite) {
            if ($this->frecuencia !== 'semanal' || in_array((int) $dia->format('N'), $this->numerosDeDia(), true)) {
                $fechas[] = $dia->format('Y-m-d');
            }

            $dia = $dia->modify('+1 day');
        }

        return $fechas;
    }

    /**
     * @return list<int>
     */
    private function numerosDeDia(): array
    {
        return array_map(static fn (string $d): int => self::DIAS[$d], $this->dias);
    }
}
