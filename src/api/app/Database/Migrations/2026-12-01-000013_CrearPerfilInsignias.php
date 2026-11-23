<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionBase;

/**
 * `perfil_insignias`: insignias obtenidas, una vez por perfil.
 */
class CrearPerfilInsignias extends MigracionBase
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => $this->id(),
            'perfil_id'   => $this->enteroSinSigno(),
            'insignia_id' => $this->enteroSinSigno(),
            'obtenida_en' => $this->fechaHora(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['perfil_id', 'insignia_id'], 'uq_perfil_insignia');
        $this->forge->addKey('insignia_id', false, false, 'ix_perfil_insignias_insignia');
        $this->forge->addForeignKey('perfil_id', 'perfiles', 'id', 'CASCADE', 'CASCADE', 'fk_pi_perfil');
        $this->forge->addForeignKey('insignia_id', 'insignias', 'id', 'CASCADE', 'RESTRICT', 'fk_pi_insignia');
        $this->crearTabla('perfil_insignias');
    }

    public function down(): void
    {
        $this->borrarTabla('perfil_insignias');
    }
}
