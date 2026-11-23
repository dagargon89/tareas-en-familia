<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionBase;

/**
 * `tarea_instancias`: la tarea en una fecha.
 */
class CrearTareaInstancias extends MigracionBase
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                 => $this->id(),
            'tarea_id'           => $this->enteroSinSigno(),
            'perfil_id'          => $this->enteroSinSigno(),
            'fecha'              => $this->fecha(),
            'hora'               => $this->hora(),
            'fecha_entrega'      => $this->fecha(true),
            'puntos'             => $this->smallSinSigno(),
            'estado'             => $this->enum(['pendiente', 'hecha', 'aprobada', 'devuelta'], 'pendiente'),
            'mensaje_devolucion' => $this->varchar(140, true),
            'completada_en'      => $this->fechaHora(true),
            'aprobada_en'        => $this->fechaHora(true),
            'created_at'         => $this->fechaHora(),
            'updated_at'         => $this->fechaHora(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['tarea_id', 'fecha'], 'uq_instancias_tarea_fecha');
        $this->forge->addKey(['perfil_id', 'fecha'], false, false, 'ix_instancias_perfil_fecha');
        $this->forge->addKey(['estado', 'completada_en'], false, false, 'ix_instancias_estado');
        $this->forge->addForeignKey('tarea_id', 'tareas', 'id', 'CASCADE', 'CASCADE', 'fk_instancias_tarea');
        $this->forge->addForeignKey('perfil_id', 'perfiles', 'id', 'CASCADE', 'RESTRICT', 'fk_instancias_perfil');
        $this->crearTabla('tarea_instancias');
    }

    public function down(): void
    {
        $this->borrarTabla('tarea_instancias');
    }
}
