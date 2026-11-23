<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Libraries\Hash;
use CodeIgniter\Database\Seeder;
use CodeIgniter\I18n\Time;
use RuntimeException;

/**
 * Datos de demostración (doc 03 §7): 1 usuario, 3 perfiles (uno por nivel),
 * 12 tareas con su recurrencia, 4 recompensas y 1 meta activa.
 * Fechas relativas a hoy (`America/Ciudad_Juarez`). Solo desarrollo y pruebas.
 *
 * Credenciales de la demo (no son datos reales):
 * - Padre: `demo@tareas.local` / `FamiliaDemo2026`, PIN de modo padre `4826`.
 * - Perfiles: Luna `3917`, Sol `5203`, Estrella `8462`.
 */
class DemoSeeder extends Seeder
{
    public const EMAIL    = 'demo@tareas.local';
    public const PASSWORD = 'FamiliaDemo2026';
    public const PIN      = '4826';

    /**
     * @var list<array{apodo: string, avatar: string, color: string, nivel_ux: string, pin: string}>
     */
    public const PERFILES = [
        ['apodo' => 'Luna', 'avatar' => 'zorro', 'color' => 'rosa', 'nivel_ux' => 'exploradoras', 'pin' => '3917'],
        ['apodo' => 'Sol', 'avatar' => 'buho', 'color' => 'ambar', 'nivel_ux' => 'aventureras', 'pin' => '5203'],
        ['apodo' => 'Estrella', 'avatar' => 'gato', 'color' => 'menta', 'nivel_ux' => 'pro', 'pin' => '8462'],
    ];

    public function run(): void
    {
        if (ENVIRONMENT === 'production') {
            throw new RuntimeException('DemoSeeder no se ejecuta en producción.');
        }

        if ($this->db->table('categorias')->countAllResults() === 0) {
            $this->call(CatalogoSeeder::class);
        }

        $ahora = Time::now()->toDateTimeString();
        $hoy   = Time::today();

        $this->db->transStart();

        $this->db->table('usuarios')->insert([
            'nombre'        => 'Papá Demo',
            'email'         => self::EMAIL,
            'password_hash' => Hash::crear(self::PASSWORD),
            'pin_hash'      => Hash::crear(self::PIN),
            'created_at'    => $ahora,
            'updated_at'    => $ahora,
        ]);
        $usuarioId = (int) $this->db->insertID();

        $perfiles = [];

        foreach (self::PERFILES as $orden => $perfil) {
            $this->db->table('perfiles')->insert([
                'apodo'      => $perfil['apodo'],
                'avatar'     => $perfil['avatar'],
                'color'      => $perfil['color'],
                'nivel_ux'   => $perfil['nivel_ux'],
                'pin_hash'   => Hash::crear($perfil['pin']),
                'orden'      => $orden,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
            $perfiles[$perfil['apodo']] = (int) $this->db->insertID();
        }

        $categorias = array_column(
            $this->db->table('categorias')->select('id, nombre')->get()->getResultArray(),
            'id',
            'nombre',
        );

        foreach ($this->tareas($hoy) as $t) {
            $this->db->table('tareas')->insert([
                'perfil_id'             => $perfiles[$t['perfil']],
                'categoria_id'          => (int) $categorias[$t['categoria']],
                'titulo'                => $t['titulo'],
                'icono'                 => $t['icono'],
                'puntos'                => $t['puntos'],
                'tipo'                  => $t['tipo'],
                'materia'               => $t['materia'] ?? null,
                'fecha_entrega'         => $t['fecha_entrega'] ?? null,
                'requiere_foto'         => $t['requiere_foto'] ?? 0,
                'creada_por'            => 'padre',
                'creada_por_usuario_id' => $usuarioId,
                'created_at'            => $ahora,
                'updated_at'            => $ahora,
            ]);

            $this->db->table('tarea_recurrencias')->insert([
                'tarea_id'     => (int) $this->db->insertID(),
                'frecuencia'   => $t['frecuencia'],
                'dias_semana'  => $t['dias'] ?? null,
                'hora'         => $t['hora'] ?? null,
                'fecha_inicio' => $hoy->toDateString(),
            ]);
        }

        foreach ([
            ['titulo' => 'Elegir la película del viernes', 'icono' => 'clapperboard', 'costo' => 50],
            ['titulo' => '30 minutos extra de pantalla', 'icono' => 'tablet', 'costo' => 80],
            ['titulo' => 'Un helado', 'icono' => 'ice-cream-cone', 'costo' => 120],
            ['titulo' => 'Tarde en el parque', 'icono' => 'trees', 'costo' => 200],
        ] as $recompensa) {
            $this->db->table('recompensas')->insert($recompensa + ['created_at' => $ahora, 'updated_at' => $ahora]);
        }

        $this->db->table('metas_familiares')->insert([
            'titulo'          => 'Noche de juegos en familia',
            'premio'          => 'Pizza y juegos de mesa',
            'objetivo_puntos' => 500,
            'fecha_inicio'    => $hoy->subDays(7)->toDateString(),
            'fecha_limite'    => $hoy->addDays(21)->toDateString(),
            'created_at'      => $ahora,
            'updated_at'      => $ahora,
        ]);

        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            throw new RuntimeException('No se pudo sembrar la demo.');
        }
    }

    /**
     * 12 tareas: 4 por perfil.
     *
     * @return list<array<string, int|string>>
     */
    private function tareas(Time $hoy): array
    {
        return [
            // Luna · exploradoras
            ['perfil' => 'Luna', 'categoria' => 'Casa', 'titulo' => 'Tender la cama', 'icono' => 'bed', 'puntos' => 5, 'tipo' => 'casa', 'frecuencia' => 'diaria'],
            ['perfil' => 'Luna', 'categoria' => 'Cuidado personal', 'titulo' => 'Lavarme los dientes', 'icono' => 'smile', 'puntos' => 3, 'tipo' => 'casa', 'frecuencia' => 'diaria'],
            ['perfil' => 'Luna', 'categoria' => 'Casa', 'titulo' => 'Guardar mis juguetes', 'icono' => 'toy-brick', 'puntos' => 5, 'tipo' => 'casa', 'frecuencia' => 'semanal', 'dias' => 'lu,mi,vi'],
            ['perfil' => 'Luna', 'categoria' => 'Actividad', 'titulo' => 'Clase de natación', 'icono' => 'waves', 'puntos' => 0, 'tipo' => 'actividad', 'frecuencia' => 'semanal', 'dias' => 'ma,ju', 'hora' => '17:00:00'],
            // Sol · aventureras
            ['perfil' => 'Sol', 'categoria' => 'Casa', 'titulo' => 'Tender la cama', 'icono' => 'bed', 'puntos' => 5, 'tipo' => 'casa', 'frecuencia' => 'diaria'],
            ['perfil' => 'Sol', 'categoria' => 'Hábito', 'titulo' => 'Leer 20 minutos', 'icono' => 'book', 'puntos' => 10, 'tipo' => 'casa', 'frecuencia' => 'diaria'],
            ['perfil' => 'Sol', 'categoria' => 'Escuela', 'titulo' => 'Tarea de matemáticas', 'icono' => 'calculator', 'puntos' => 15, 'tipo' => 'escolar', 'frecuencia' => 'una_vez', 'materia' => 'Matemáticas', 'fecha_entrega' => $hoy->addDays(3)->toDateString(), 'requiere_foto' => 1],
            ['perfil' => 'Sol', 'categoria' => 'Casa', 'titulo' => 'Poner la mesa', 'icono' => 'utensils', 'puntos' => 5, 'tipo' => 'casa', 'frecuencia' => 'semanal', 'dias' => 'lu,ma,mi,ju,vi'],
            // Estrella · pro
            ['perfil' => 'Estrella', 'categoria' => 'Casa', 'titulo' => 'Lavar los trastes', 'icono' => 'droplets', 'puntos' => 10, 'tipo' => 'casa', 'frecuencia' => 'diaria', 'requiere_foto' => 1],
            ['perfil' => 'Estrella', 'categoria' => 'Escuela', 'titulo' => 'Proyecto de ciencias', 'icono' => 'flask-conical', 'puntos' => 20, 'tipo' => 'escolar', 'frecuencia' => 'una_vez', 'materia' => 'Ciencias', 'fecha_entrega' => $hoy->addDays(7)->toDateString()],
            ['perfil' => 'Estrella', 'categoria' => 'Actividad', 'titulo' => 'Practicar piano', 'icono' => 'music', 'puntos' => 5, 'tipo' => 'actividad', 'frecuencia' => 'semanal', 'dias' => 'lu,mi', 'hora' => '18:00:00'],
            ['perfil' => 'Estrella', 'categoria' => 'Hábito', 'titulo' => 'Hacer ejercicio', 'icono' => 'dumbbell', 'puntos' => 10, 'tipo' => 'casa', 'frecuencia' => 'semanal', 'dias' => 'sa'],
        ];
    }
}
