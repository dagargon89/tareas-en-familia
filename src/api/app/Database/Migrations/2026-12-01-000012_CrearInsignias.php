<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionBase;

/**
 * Catálogo `insignias`. `regla` es JSON (doc 03 §5).
 */
class CrearInsignias extends MigracionBase
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => $this->id(),
            'codigo'      => $this->varchar(40),
            'nombre'      => $this->varchar(40),
            'descripcion' => $this->varchar(120),
            'icono'       => $this->varchar(40),
            'color'       => $this->enum(self::COLORES),
            'regla'       => $this->json(),
            'xp'          => $this->smallSinSigno(20),
            'orden'       => $this->tinySinSigno(0),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('codigo', 'uq_insignias_codigo');
        $this->crearTabla('insignias');
    }

    public function down(): void
    {
        $this->borrarTabla('insignias');
    }
}
