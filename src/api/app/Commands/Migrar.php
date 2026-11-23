<?php

declare(strict_types=1);

namespace App\Commands;

use App\Database\Seeds\CatalogoSeeder;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use Throwable;

/**
 * Migraciones y catálogos para el despliegue (T-S0-05). A diferencia de `spark migrate`, que muestra
 * el error pero sale con código 0, este comando sale con código 1 si algo falla, para que
 * `scripts/deploy_remoto.sh` no active un release con la base a medias.
 */
class Migrar extends BaseCommand
{
    protected $group       = 'Tareas';
    protected $name        = 'tareas:migrar';
    protected $description = 'Aplica todas las migraciones pendientes y los catálogos (CatalogoSeeder); sale con 1 si algo falla.';
    protected $usage       = 'tareas:migrar [-g grupo]';

    /**
     * @var array<string, string>
     */
    protected $options = [
        '-g' => 'Grupo de base de datos (por omisión, el de este entorno).',
    ];

    /**
     * @param array<int|string, string|null> $params
     */
    public function run(array $params): int
    {
        $grupo = $params['g'] ?? CLI::getOption('g');
        $grupo = is_string($grupo) && $grupo !== '' ? $grupo : null;

        try {
            $migraciones = service('migrations');
            $migraciones->setNamespace(null);

            if (! $migraciones->latest($grupo)) {
                CLI::error('Las migraciones no se completaron.');

                return EXIT_ERROR;
            }

            foreach ($migraciones->getCliMessages() as $mensaje) {
                CLI::write($mensaje);
            }

            Database::seeder($grupo)->call(CatalogoSeeder::class);
        } catch (Throwable $e) {
            CLI::error('Falló la migración: ' . $e->getMessage());

            return EXIT_ERROR;
        }

        CLI::write('Migraciones y catálogos al día.', 'green');

        return EXIT_SUCCESS;
    }
}
