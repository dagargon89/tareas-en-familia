<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionBase;

/**
 * `tarea_recurrencias`, 1:1 con la tarea. `dias_semana` es SET.
 */
class CrearTareaRecurrencias extends MigracionBase
{
    public function up(): void
    {
        $this->forge->addField([
            'id'           => $this->id(),
            'tarea_id'     => $this->enteroSinSigno(),
            'frecuencia'   => $this->enum(['una_vez', 'diaria', 'semanal']),
            'dias_semana'  => ['type' => 'SET', 'constraint' => ['lu', 'ma', 'mi', 'ju', 'vi', 'sa', 'do'], 'null' => true],
            'hora'         => $this->hora(),
            'fecha_inicio' => $this->fecha(),
            'fecha_fin'    => $this->fecha(true),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('tarea_id', 'uq_recurrencias_tarea');
        $this->forge->addForeignKey('tarea_id', 'tareas', 'id', 'CASCADE', 'CASCADE', 'fk_recurrencias_tarea');
        $this->crearTabla('tarea_recurrencias');
    }

    public function down(): void
    {
        $this->borrarTabla('tarea_recurrencias');
    }
}
