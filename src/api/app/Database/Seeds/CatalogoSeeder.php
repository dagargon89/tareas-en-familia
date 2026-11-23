<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use CodeIgniter\I18n\Time;

/**
 * Catálogos base (doc 03 §7): 5 categorías, 8 insignias del RF-11 con su
 * `regla` (doc 03 §5) y la configuración inicial (doc 03 §6).
 *
 * Idempotente: usa `INSERT IGNORE` sobre las llaves únicas, así que no
 * duplica ni sobrescribe valores ya ajustados por la familia.
 */
class CatalogoSeeder extends Seeder
{
    /**
     * @var list<array{nombre: string, icono: string, color: string}>
     */
    public const CATEGORIAS = [
        ['nombre' => 'Casa', 'icono' => 'casa', 'color' => 'ambar'],
        ['nombre' => 'Escuela', 'icono' => 'libro', 'color' => 'menta'],
        ['nombre' => 'Actividad', 'icono' => 'trofeo', 'color' => 'lavanda'],
        ['nombre' => 'Hábito', 'icono' => 'repetir', 'color' => 'indigo'],
        ['nombre' => 'Cuidado personal', 'icono' => 'corazon', 'color' => 'rosa'],
    ];

    /**
     * Valores de `configuracion` (se guardan como JSON).
     *
     * @var array<string, bool|int>
     */
    public const CONFIGURACION = [
        'racha_umbral'             => 80,
        'sonidos_default'          => true,
        'dias_generacion'          => 7,
        'evidencia_dias_retencion' => 30,
        'puntos_tarea_propia'      => 5,
    ];

    public function run(): void
    {
        $ahora = Time::now()->toDateTimeString();

        foreach (self::CATEGORIAS as $categoria) {
            $this->db->table('categorias')->ignore(true)->insert($categoria + ['activa' => 1]);
        }

        foreach (self::insignias() as $orden => $insignia) {
            $insignia['regla'] = json_encode($insignia['regla'], JSON_THROW_ON_ERROR);
            $this->db->table('insignias')->ignore(true)->insert($insignia + ['xp' => 20, 'orden' => $orden + 1]);
        }

        foreach (self::CONFIGURACION as $clave => $valor) {
            $this->db->table('configuracion')->ignore(true)->insert([
                'clave'      => $clave,
                'valor'      => json_encode($valor, JSON_THROW_ON_ERROR),
                'updated_at' => $ahora,
            ]);
        }
    }

    /**
     * Las 8 insignias del RF-11, en el orden en que se muestran.
     *
     * @return list<array{codigo: string, nombre: string, descripcion: string, icono: string, color: string, regla: array<string, int|string>}>
     */
    public static function insignias(): array
    {
        return [
            [
                'codigo' => 'primera_mision', 'nombre' => 'Primera misión',
                'descripcion' => 'Completa tu primera misión aprobada.', 'icono' => 'estrella', 'color' => 'ambar',
                'regla' => ['tipo' => 'tareas_aprobadas', 'min' => 1],
            ],
            [
                'codigo' => 'misiones_10', 'nombre' => '10 misiones',
                'descripcion' => 'Completa 10 misiones aprobadas.', 'icono' => 'medalla', 'color' => 'indigo',
                'regla' => ['tipo' => 'tareas_aprobadas', 'min' => 10],
            ],
            [
                'codigo' => 'misiones_50', 'nombre' => '50 misiones',
                'descripcion' => 'Completa 50 misiones aprobadas.', 'icono' => 'trofeo', 'color' => 'menta',
                'regla' => ['tipo' => 'tareas_aprobadas', 'min' => 50],
            ],
            [
                'codigo' => 'misiones_100', 'nombre' => '100 misiones',
                'descripcion' => 'Completa 100 misiones aprobadas.', 'icono' => 'corona', 'color' => 'lavanda',
                'regla' => ['tipo' => 'tareas_aprobadas', 'min' => 100],
            ],
            [
                'codigo' => 'racha_2', 'nombre' => 'Racha de 2 semanas',
                'descripcion' => 'Cumple tu racha 2 semanas seguidas.', 'icono' => 'fuego', 'color' => 'rosa',
                'regla' => ['tipo' => 'racha_semanas', 'min' => 2],
            ],
            [
                'codigo' => 'racha_4', 'nombre' => 'Racha de 4 semanas',
                'descripcion' => 'Cumple tu racha 4 semanas seguidas.', 'icono' => 'brillo', 'color' => 'ambar',
                'regla' => ['tipo' => 'racha_semanas', 'min' => 4],
            ],
            [
                'codigo' => 'escolares_10', 'nombre' => '10 tareas escolares',
                'descripcion' => 'Termina 10 tareas de la escuela.', 'icono' => 'libro', 'color' => 'menta',
                'regla' => ['tipo' => 'tareas_por_tipo', 'tipo_tarea' => 'escolar', 'min' => 10],
            ],
            [
                'codigo' => 'casa_10', 'nombre' => '10 tareas de casa',
                'descripcion' => 'Termina 10 tareas de la casa.', 'icono' => 'casa', 'color' => 'indigo',
                'regla' => ['tipo' => 'tareas_por_tipo', 'tipo_tarea' => 'casa', 'min' => 10],
            ],
        ];
    }
}
