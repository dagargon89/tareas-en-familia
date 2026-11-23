<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionBase;

/**
 * `movimientos_puntos`: libro mayor (ADR-004). Nunca se edita ni se borra.
 */
class CrearMovimientosPuntos extends MigracionBase
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => $this->id(),
            'perfil_id'   => $this->enteroSinSigno(),
            'origen'      => $this->enum(['tarea', 'insignia', 'canje', 'ajuste']),
            'origen_id'   => $this->enteroSinSigno(true),
            'puntos'      => ['type' => 'INT', 'null' => false],
            'descripcion' => $this->varchar(120),
            'created_at'  => $this->fechaHora(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['origen', 'origen_id'], 'uq_movimientos_origen');
        $this->forge->addKey(['perfil_id', 'created_at'], false, false, 'ix_movimientos_perfil_fecha');
        $this->forge->addForeignKey('perfil_id', 'perfiles', 'id', 'CASCADE', 'RESTRICT', 'fk_movimientos_perfil');
        $this->crearTabla('movimientos_puntos');
    }

    public function down(): void
    {
        $this->borrarTabla('movimientos_puntos');
    }
}
