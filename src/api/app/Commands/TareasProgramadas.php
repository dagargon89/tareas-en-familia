<?php

declare(strict_types=1);

namespace App\Commands;

use App\Domain\Calendario;
use App\Domain\SemanaIso;
use App\Services\GeneradorInstancias;
use App\Services\MetasService;
use App\Services\RachasService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\I18n\Time;

/**
 * Cron de cada minuto (doc 02 §3.4, ADR-005). Decide qué subtareas tocan
 * según la hora local y usa un candado de archivo para no superponerse.
 *
 * Subtareas: "generar" (S2), "purgar" (S3), "cerrar semana" y "vencer metas" (S4). Cada una con su hora;
 * "generar" y "cerrar semana" se ponen al día si el cron no corrió a su hora.
 */
class TareasProgramadas extends BaseCommand
{
    protected $group       = 'Tareas';
    protected $name        = 'tareas:programadas';
    protected $description = 'Tareas programadas (cada minuto): genera instancias y, en sprints siguientes, cierres y purgas.';
    protected $usage       = 'tareas:programadas [--forzar]';

    /**
     * @var array<string, string>
     */
    protected $options = [
        '--forzar' => 'Ejecuta todas las subtareas aunque no sea su hora.',
    ];

    private const CANDADO          = 'cache/cron.lock';
    private const ULTIMA_GENERACION = 'cache/generacion.ultima';
    private const HORA_GENERAR     = '00:05';
    private const HORA_PURGAR      = '03:00';
    private const ULTIMO_CIERRE    = 'cache/cierre.ultimo';

    /**
     * @param array<int|string, string|null> $params
     */
    public function run(array $params): int
    {
        $candado = fopen(WRITEPATH . self::CANDADO, 'c');

        if ($candado === false || ! flock($candado, LOCK_EX | LOCK_NB)) {
            CLI::write('Otra ejecución está en curso; se omite.', 'yellow');

            return EXIT_SUCCESS;
        }

        try {
            $ahora  = Time::now();
            $hoy    = Calendario::hoy($ahora);
            $forzar = array_key_exists('forzar', $params) || CLI::getOption('forzar') !== null;

            if ($forzar || $ahora->format('H:i') === self::HORA_GENERAR || $this->leer(self::ULTIMA_GENERACION) !== $hoy) {
                $generador = new GeneradorInstancias(db_connect());
                $nuevas    = $generador->generarProximos($hoy);
                file_put_contents(WRITEPATH . self::ULTIMA_GENERACION, $hoy);
                $this->registrar("generar: {$nuevas} instancias nuevas de {$hoy} a " . Calendario::sumarDias($hoy, $generador->diasGeneracion()));
            }

            // Cerrar semana: la anterior a la de hoy, una vez (lunes 00:05 o la primera corrida después).
            $anterior = SemanaIso::desdeFecha($hoy)->anterior();

            if ($forzar || $this->leer(self::ULTIMO_CIERRE) !== $anterior->codigo()) {
                $filas = (new RachasService(db_connect()))->cerrarSemana($anterior);
                file_put_contents(WRITEPATH . self::ULTIMO_CIERRE, $anterior->codigo());
                $this->registrar("cerrar semana {$anterior}: {$filas} rachas nuevas");
            }

            // Vencer metas: consulta mínima, corre en cada ejecución; solo registra si canceló alguna.
            $vencidas = (new MetasService(db_connect()))->vencer($hoy);

            if ($vencidas > 0) {
                $this->registrar("vencer metas: {$vencidas} meta cancelada por fecha límite");
            }

            if ($forzar || $ahora->format('H:i') === self::HORA_PURGAR) {
                $borradas = service('evidencias')->purgar($hoy);
                $this->registrar("purgar: {$borradas} evidencias vencidas borradas");
            }
        } finally {
            flock($candado, LOCK_UN);
            fclose($candado);
        }

        return EXIT_SUCCESS;
    }

    /**
     * Contenido de una marca en `writable/` (última generación o último cierre), o null.
     */
    private function leer(string $marca): ?string
    {
        $archivo = WRITEPATH . $marca;

        return is_file($archivo) ? trim((string) file_get_contents($archivo)) : null;
    }

    /**
     * Escribe en la consola y en `writable/logs/cron.log`.
     */
    private function registrar(string $mensaje): void
    {
        CLI::write($mensaje);
        file_put_contents(WRITEPATH . 'logs/cron.log', Time::now()->format(DATE_ATOM) . " {$mensaje}\n", FILE_APPEND | LOCK_EX);
    }
}
