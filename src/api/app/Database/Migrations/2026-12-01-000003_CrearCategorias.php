<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionBase;

/**
 * Catálogo `categorias`.
 */
class CrearCategorias extends MigracionBase
{
    public function up(): void
    {
        $this->forge->addField([
            'id'     => $this->id(),
            'nombre' => $this->varchar(40),
            'icono'  => $this->varchar(40),
            'color'  => $this->enum(self::COLORES),
            'activa' => $this->booleano(1),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('nombre', 'uq_categorias_nombre');
        $this->crearTabla('categorias');
    }

    public function down(): void
    {
        $this->borrarTabla('categorias');
    }
}
