<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * `tarea_instancias`: la tarea en una fecha. `puntos`, `hora` y `fecha_entrega`
 * se copian de la tarea al generarse.
 */
class InstanciaModel extends Model
{
    public const ESTADOS = ['pendiente', 'hecha', 'aprobada', 'devuelta'];

    protected $table         = 'tarea_instancias';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'tarea_id', 'perfil_id', 'fecha', 'hora', 'fecha_entrega', 'puntos', 'estado', 'mensaje_devolucion',
        'completada_en', 'aprobada_en',
    ];
}
