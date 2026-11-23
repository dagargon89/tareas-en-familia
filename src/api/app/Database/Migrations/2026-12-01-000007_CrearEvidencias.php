<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionBase;

/**
 * `evidencias`: foto opcional, una por instancia.
 */
class CrearEvidencias extends MigracionBase
{
    public function up(): void
    {
        $this->forge->addField([
            'id'             => $this->id(),
            'instancia_id'   => $this->enteroSinSigno(),
            'ruta'           => $this->varchar(255),
            'mime'           => $this->varchar(30),
            'bytes'          => $this->enteroSinSigno(),
            'borrar_despues' => $this->fecha(true),
            'created_at'     => $this->fechaHora(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('instancia_id', 'uq_evidencias_instancia');
        $this->forge->addKey('borrar_despues', false, false, 'ix_evidencias_borrar');
        $this->forge->addForeignKey('instancia_id', 'tarea_instancias', 'id', 'CASCADE', 'CASCADE', 'fk_evidencias_instancia');
        $this->crearTabla('evidencias');
    }

    public function down(): void
    {
        $this->borrarTabla('evidencias');
    }
}
