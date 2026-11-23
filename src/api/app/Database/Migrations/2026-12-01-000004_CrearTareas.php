<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionBase;

/**
 * Plantillas de `tareas`.
 */
class CrearTareas extends MigracionBase
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                    => $this->id(),
            'perfil_id'             => $this->enteroSinSigno(),
            'categoria_id'          => $this->enteroSinSigno(),
            'titulo'                => $this->varchar(80),
            'icono'                 => $this->varchar(40),
            'puntos'                => $this->smallSinSigno(),
            'tipo'                  => $this->enum(['casa', 'escolar', 'actividad']),
            'materia'               => $this->varchar(40, true),
            'fecha_entrega'         => $this->fecha(true),
            'requiere_foto'         => $this->booleano(0),
            'creada_por'            => $this->enum(['padre', 'perfil']),
            'creada_por_usuario_id' => $this->enteroSinSigno(true),
            'activa'                => $this->booleano(1),
            'created_at'            => $this->fechaHora(),
            'updated_at'            => $this->fechaHora(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['perfil_id', 'activa'], false, false, 'ix_tareas_perfil_activa');
        $this->forge->addKey('categoria_id', false, false, 'ix_tareas_categoria');
        $this->forge->addKey('creada_por_usuario_id', false, false, 'ix_tareas_usuario');
        $this->forge->addForeignKey('perfil_id', 'perfiles', 'id', 'CASCADE', 'RESTRICT', 'fk_tareas_perfil');
        $this->forge->addForeignKey('categoria_id', 'categorias', 'id', 'CASCADE', 'RESTRICT', 'fk_tareas_categoria');
        $this->forge->addForeignKey('creada_por_usuario_id', 'usuarios', 'id', 'CASCADE', 'SET NULL', 'fk_tareas_usuario');
        $this->crearTabla('tareas');
    }

    public function down(): void
    {
        $this->borrarTabla('tareas');
    }
}
