<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionBase;

/**
 * `revisiones`: historial de aprobaciones y devoluciones.
 */
class CrearRevisiones extends MigracionBase
{
    public function up(): void
    {
        $this->forge->addField([
            'id'           => $this->id(),
            'instancia_id' => $this->enteroSinSigno(),
            'usuario_id'   => $this->enteroSinSigno(),
            'decision'     => $this->enum(['aprobada', 'devuelta']),
            'mensaje'      => $this->varchar(140, true),
            'created_at'   => $this->fechaHora(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('instancia_id', false, false, 'ix_revisiones_instancia');
        $this->forge->addKey('usuario_id', false, false, 'ix_revisiones_usuario');
        $this->forge->addForeignKey('instancia_id', 'tarea_instancias', 'id', 'CASCADE', 'CASCADE', 'fk_revisiones_instancia');
        $this->forge->addForeignKey('usuario_id', 'usuarios', 'id', 'CASCADE', 'RESTRICT', 'fk_revisiones_usuario');
        $this->crearTabla('revisiones');
    }

    public function down(): void
    {
        $this->borrarTabla('revisiones');
    }
}
