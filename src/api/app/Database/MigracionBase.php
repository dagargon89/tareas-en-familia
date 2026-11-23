<?php

declare(strict_types=1);

namespace App\Database;

use CodeIgniter\Database\Migration;

/**
 * Base de las migraciones del Sprint 0. Las definiciones reproducen
 * `docs/03-datos/03_esquema.sql` columna por columna; `EsquemaTest` lo verifica.
 */
abstract class MigracionBase extends Migration
{
    /**
     * @var array<string, string>
     */
    protected const OPCIONES_TABLA = [
        'ENGINE'          => 'InnoDB',
        'DEFAULT CHARSET' => 'utf8mb4',
        'COLLATE'         => 'utf8mb4_unicode_ci',
    ];

    /**
     * @var list<string>
     */
    protected const COLORES = ['indigo', 'ambar', 'menta', 'rosa', 'lavanda'];

    /**
     * Crea la tabla con los campos, llaves y FKs ya agregados al Forge.
     */
    protected function crearTabla(string $tabla): void
    {
        $this->forge->createTable($tabla, false, self::OPCIONES_TABLA);
    }

    protected function borrarTabla(string $tabla): void
    {
        $this->forge->dropTable($tabla, true);
    }

    /**
     * `INT UNSIGNED NOT NULL AUTO_INCREMENT`.
     *
     * @return array<string, mixed>
     */
    protected function id(): array
    {
        return ['type' => 'INT', 'unsigned' => true, 'null' => false, 'auto_increment' => true];
    }

    /**
     * `INT UNSIGNED`.
     *
     * @return array<string, mixed>
     */
    protected function enteroSinSigno(bool $nulo = false): array
    {
        return ['type' => 'INT', 'unsigned' => true, 'null' => $nulo];
    }

    /**
     * `SMALLINT UNSIGNED`.
     *
     * @return array<string, mixed>
     */
    protected function smallSinSigno(?int $default = null): array
    {
        return $this->conDefault(['type' => 'SMALLINT', 'unsigned' => true, 'null' => false], $default);
    }

    /**
     * `TINYINT UNSIGNED`.
     *
     * @return array<string, mixed>
     */
    protected function tinySinSigno(?int $default = null): array
    {
        return $this->conDefault(['type' => 'TINYINT', 'unsigned' => true, 'null' => false], $default);
    }

    /**
     * `TINYINT(1)` usado como booleano.
     *
     * @return array<string, mixed>
     */
    protected function booleano(?int $default = null): array
    {
        return $this->conDefault(['type' => 'TINYINT', 'constraint' => 1, 'null' => false], $default);
    }

    /**
     * @return array<string, mixed>
     */
    protected function varchar(int $largo, bool $nulo = false): array
    {
        return ['type' => 'VARCHAR', 'constraint' => $largo, 'null' => $nulo];
    }

    /**
     * @return array<string, mixed>
     */
    protected function fechaHora(bool $nulo = false): array
    {
        return ['type' => 'DATETIME', 'null' => $nulo];
    }

    /**
     * @return array<string, mixed>
     */
    protected function fecha(bool $nulo = false): array
    {
        return ['type' => 'DATE', 'null' => $nulo];
    }

    /**
     * @return array<string, mixed>
     */
    protected function hora(bool $nulo = true): array
    {
        return ['type' => 'TIME', 'null' => $nulo];
    }

    /**
     * MariaDB guarda `JSON` como `LONGTEXT` con `CHECK (json_valid(...))`.
     *
     * @return array<string, mixed>
     */
    protected function json(): array
    {
        return ['type' => 'JSON', 'null' => false];
    }

    /**
     * @param list<string> $valores
     *
     * @return array<string, mixed>
     */
    protected function enum(array $valores, ?string $default = null): array
    {
        return $this->conDefault(['type' => 'ENUM', 'constraint' => $valores, 'null' => false], $default);
    }

    /**
     * @param array<string, mixed> $campo
     *
     * @return array<string, mixed>
     */
    private function conDefault(array $campo, int|string|null $default): array
    {
        if ($default !== null) {
            $campo['default'] = $default;
        }

        return $campo;
    }
}
