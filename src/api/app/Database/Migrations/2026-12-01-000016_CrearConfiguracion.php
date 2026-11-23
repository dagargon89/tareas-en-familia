<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionBase;

/**
 * `configuracion`: clave → valor JSON (doc 03 §6).
 */
class CrearConfiguracion extends MigracionBase
{
    public function up(): void
    {
        $this->forge->addField([
            'clave'      => $this->varchar(50),
            'valor'      => $this->json(),
            'updated_at' => $this->fechaHora(),
        ]);
        $this->forge->addPrimaryKey('clave');
        $this->crearTabla('configuracion');
    }

    public function down(): void
    {
        $this->borrarTabla('configuracion');
    }
}
