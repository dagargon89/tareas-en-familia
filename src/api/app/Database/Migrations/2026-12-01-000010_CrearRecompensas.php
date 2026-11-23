<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionBase;

/**
 * Catálogo `recompensas`.
 */
class CrearRecompensas extends MigracionBase
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => $this->id(),
            'titulo'     => $this->varchar(60),
            'icono'      => $this->varchar(40),
            'costo'      => $this->smallSinSigno(),
            'activa'     => $this->booleano(1),
            'created_at' => $this->fechaHora(),
            'updated_at' => $this->fechaHora(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->crearTabla('recompensas');
    }

    public function down(): void
    {
        $this->borrarTabla('recompensas');
    }
}
