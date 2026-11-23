<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * `tareas`: plantilla de tarea (RF-03, RF-13, RF-19).
 */
class TareaModel extends Model
{
    public const TIPOS = ['casa', 'escolar', 'actividad'];

    protected $table         = 'tareas';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'perfil_id', 'categoria_id', 'titulo', 'icono', 'puntos', 'tipo', 'materia', 'fecha_entrega',
        'requiere_foto', 'creada_por', 'creada_por_usuario_id', 'activa',
    ];
}
