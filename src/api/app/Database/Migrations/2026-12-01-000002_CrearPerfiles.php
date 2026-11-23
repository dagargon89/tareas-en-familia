<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionBase;

/**
 * Tabla `perfiles` (cada hija). Sin datos personales fuera del doc 03.
 */
class CrearPerfiles extends MigracionBase
{
    public function up(): void
    {
        $this->forge->addField([
            'id'              => $this->id(),
            'apodo'           => $this->varchar(30),
            'avatar'          => $this->varchar(40),
            'color'           => $this->enum(self::COLORES, 'indigo'),
            'nivel_ux'        => $this->enum(['exploradoras', 'aventureras', 'pro']),
            'pin_hash'        => $this->varchar(255),
            'pin_intentos'    => $this->tinySinSigno(0),
            'bloqueado_hasta' => $this->fechaHora(true),
            'sonidos'         => $this->booleano(1),
            'tema'            => $this->enum(['claro', 'oscuro', 'sistema'], 'claro'),
            'orden'           => $this->tinySinSigno(0),
            'activo'          => $this->booleano(1),
            'created_at'      => $this->fechaHora(),
            'updated_at'      => $this->fechaHora(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->crearTabla('perfiles');
    }

    public function down(): void
    {
        $this->borrarTabla('perfiles');
    }
}
