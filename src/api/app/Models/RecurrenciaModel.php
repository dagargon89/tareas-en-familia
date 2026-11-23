<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * `tarea_recurrencias`: regla 1:1 con la tarea (`App\Domain\ReglaRecurrencia`).
 */
class RecurrenciaModel extends Model
{
    protected $table         = 'tarea_recurrencias';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['tarea_id', 'frecuencia', 'dias_semana', 'hora', 'fecha_inicio', 'fecha_fin'];
}
