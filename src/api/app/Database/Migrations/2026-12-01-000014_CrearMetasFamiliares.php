<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionBase;

/**
 * `metas_familiares`. La columna generada `activa_unica` (1 si la meta está
 * activa, NULL si no) con índice único garantiza una sola meta activa.
 * Forge no soporta columnas generadas: se declara como definición literal y
 * su índice único se agrega con SQL.
 */
class CrearMetasFamiliares extends MigracionBase
{
    public function up(): void
    {
        $this->forge->addField([
            'id'              => $this->id(),
            'titulo'          => $this->varchar(60),
            'premio'          => $this->varchar(80),
            'objetivo_puntos' => $this->enteroSinSigno(),
            'fecha_inicio'    => $this->fecha(),
            'fecha_limite'    => $this->fecha(true),
            'estado'          => $this->enum(['activa', 'cumplida', 'cancelada'], 'activa'),
            "`activa_unica` TINYINT GENERATED ALWAYS AS (IF(`estado` = 'activa', 1, NULL)) STORED",
            'cumplida_en'     => $this->fechaHora(true),
            'created_at'      => $this->fechaHora(),
            'updated_at'      => $this->fechaHora(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->crearTabla('metas_familiares');

        $this->db->query('ALTER TABLE `metas_familiares` ADD UNIQUE KEY `uq_metas_activa_unica` (`activa_unica`)');
    }

    public function down(): void
    {
        $this->borrarTabla('metas_familiares');
    }
}
