<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionBase;

/**
 * `canjes`: solicitudes de premio.
 */
class CrearCanjes extends MigracionBase
{
    public function up(): void
    {
        $this->forge->addField([
            'id'            => $this->id(),
            'perfil_id'     => $this->enteroSinSigno(),
            'recompensa_id' => $this->enteroSinSigno(),
            'costo'         => $this->smallSinSigno(),
            'estado'        => $this->enum(['solicitado', 'aprobado', 'entregado', 'rechazado'], 'solicitado'),
            'revisado_por'  => $this->enteroSinSigno(true),
            'created_at'    => $this->fechaHora(),
            'updated_at'    => $this->fechaHora(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['perfil_id', 'estado'], false, false, 'ix_canjes_perfil_estado');
        $this->forge->addKey('recompensa_id', false, false, 'ix_canjes_recompensa');
        $this->forge->addKey('revisado_por', false, false, 'ix_canjes_revisor');
        $this->forge->addForeignKey('perfil_id', 'perfiles', 'id', 'CASCADE', 'RESTRICT', 'fk_canjes_perfil');
        $this->forge->addForeignKey('recompensa_id', 'recompensas', 'id', 'CASCADE', 'RESTRICT', 'fk_canjes_recompensa');
        $this->forge->addForeignKey('revisado_por', 'usuarios', 'id', 'CASCADE', 'SET NULL', 'fk_canjes_revisor');
        $this->crearTabla('canjes');
    }

    public function down(): void
    {
        $this->borrarTabla('canjes');
    }
}
