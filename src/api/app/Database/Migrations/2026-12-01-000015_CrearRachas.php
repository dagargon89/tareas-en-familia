<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionBase;

/**
 * `rachas`: cierre semanal por perfil (`semana_iso` = `2026-W47`).
 */
class CrearRachas extends MigracionBase
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => $this->id(),
            'perfil_id'  => $this->enteroSinSigno(),
            'semana_iso' => ['type' => 'CHAR', 'constraint' => 8, 'null' => false],
            'asignadas'  => $this->smallSinSigno(),
            'aprobadas'  => $this->smallSinSigno(),
            'porcentaje' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => false],
            'cumplida'   => $this->booleano(),
            'created_at' => $this->fechaHora(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['perfil_id', 'semana_iso'], 'uq_rachas_perfil_semana');
        $this->forge->addForeignKey('perfil_id', 'perfiles', 'id', 'CASCADE', 'CASCADE', 'fk_rachas_perfil');
        $this->crearTabla('rachas');
    }

    public function down(): void
    {
        $this->borrarTabla('rachas');
    }
}
