<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionBase;

/**
 * Tabla `usuarios` (padre o madre). Doc 03 §3.
 */
class CrearUsuarios extends MigracionBase
{
    public function up(): void
    {
        $this->forge->addField([
            'id'              => $this->id(),
            'nombre'          => $this->varchar(60),
            'email'           => $this->varchar(120),
            'password_hash'   => $this->varchar(255),
            'pin_hash'        => $this->varchar(255),
            'pin_intentos'    => $this->tinySinSigno(0),
            'bloqueado_hasta' => $this->fechaHora(true),
            'ultimo_acceso'   => $this->fechaHora(true),
            'created_at'      => $this->fechaHora(),
            'updated_at'      => $this->fechaHora(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('email', 'uq_usuarios_email');
        $this->crearTabla('usuarios');
    }

    public function down(): void
    {
        $this->borrarTabla('usuarios');
    }
}
