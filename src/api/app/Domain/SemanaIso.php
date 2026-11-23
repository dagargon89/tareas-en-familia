<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Stringable;

/**
 * Semana ISO-8601 de lunes a domingo, con código `AAAA-Www` (`rachas.semana_iso`).
 */
final class SemanaIso implements Stringable
{
    private function __construct(private readonly DateTimeImmutable $lunes)
    {
    }

    public static function desdeFecha(string $fecha): self
    {
        if (! ReglaRecurrencia::esFechaValida($fecha)) {
            throw new InvalidArgumentException("Fecha inválida: {$fecha}");
        }

        $dia = new DateTimeImmutable($fecha, new DateTimeZone('UTC'));

        return new self($dia->modify('-' . ((int) $dia->format('N') - 1) . ' days'));
    }

    public static function desdeCodigo(string $codigo): self
    {
        if (preg_match('/^(\d{4})-W(\d{2})$/', $codigo, $m) !== 1) {
            throw new InvalidArgumentException("Código de semana inválido: {$codigo}");
        }

        $lunes = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->setISODate((int) $m[1], (int) $m[2])->setTime(0, 0);
        $semana = new self($lunes);

        if ($semana->codigo() !== $codigo) {
            throw new InvalidArgumentException("La semana no existe: {$codigo}");
        }

        return $semana;
    }

    public function codigo(): string
    {
        return $this->lunes->format('o-\WW');
    }

    public function anio(): int
    {
        return (int) $this->lunes->format('o');
    }

    public function numero(): int
    {
        return (int) $this->lunes->format('W');
    }

    public function lunes(): string
    {
        return $this->lunes->format('Y-m-d');
    }

    public function domingo(): string
    {
        return $this->lunes->modify('+6 days')->format('Y-m-d');
    }

    /**
     * @return list<string>
     */
    public function dias(): array
    {
        $dias = [];

        for ($i = 0; $i < 7; $i++) {
            $dias[] = $this->lunes->modify("+{$i} days")->format('Y-m-d');
        }

        return $dias;
    }

    public function contiene(string $fecha): bool
    {
        return $fecha >= $this->lunes() && $fecha <= $this->domingo();
    }

    public function anterior(): self
    {
        return new self($this->lunes->modify('-7 days'));
    }

    public function siguiente(): self
    {
        return new self($this->lunes->modify('+7 days'));
    }

    public function __toString(): string
    {
        return $this->codigo();
    }
}
