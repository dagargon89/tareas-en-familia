<?php

declare(strict_types=1);

/**
 * RNF-09 / CP-NF-06: cobertura de líneas mínima en app/Services y app/Domain a partir de un reporte Clover.
 *
 * Uso: php scripts/verificar_cobertura.php <clover.xml> [mínimo=85]
 * Sale con 1 si la cobertura conjunta queda por debajo del mínimo.
 */
if ($argc < 2 || ! is_file($argv[1])) {
    fwrite(STDERR, "Uso: php {$argv[0]} <clover.xml> [mínimo=85]\n");
    exit(2);
}

$minimo   = isset($argv[2]) ? (float) $argv[2] : 85.0;
$xml      = simplexml_load_file($argv[1]);
$carpetas = ['app/Services/' => [0, 0], 'app/Domain/' => [0, 0]];
$bajos    = [];

foreach ($xml->xpath('//file') as $archivo) {
    $ruta = str_replace('\\', '/', (string) $archivo['name']);

    foreach (array_keys($carpetas) as $carpeta) {
        if (! str_contains($ruta, '/' . $carpeta)) {
            continue;
        }

        $lineas   = (int) $archivo->metrics['statements'];
        $cubiertas = (int) $archivo->metrics['coveredstatements'];
        $carpetas[$carpeta][0] += $lineas;
        $carpetas[$carpeta][1] += $cubiertas;

        if ($lineas > 0 && $cubiertas / $lineas * 100 < $minimo) {
            $bajos[] = sprintf('  %s: %.1f %%', substr($ruta, strpos($ruta, $carpeta)), $cubiertas / $lineas * 100);
        }
    }
}

$total = $cubiertasTotal = 0;

foreach ($carpetas as $carpeta => [$lineas, $cubiertas]) {
    printf("%-14s %d/%d líneas = %.2f %%\n", $carpeta, $cubiertas, $lineas, $lineas > 0 ? $cubiertas / $lineas * 100 : 0);
    $total          += $lineas;
    $cubiertasTotal += $cubiertas;
}

$porcentaje = $total > 0 ? $cubiertasTotal / $total * 100 : 0;
printf("Services+Domain %d/%d líneas = %.2f %% (mínimo %.0f %%)\n", $cubiertasTotal, $total, $porcentaje, $minimo);

if ($bajos !== []) {
    echo "Archivos por debajo del mínimo (aviso):\n" . implode("\n", $bajos) . "\n";
}

if ($total === 0) {
    fwrite(STDERR, "El reporte no trae archivos de app/Services ni app/Domain.\n");
    exit(1);
}

exit($porcentaje >= $minimo ? 0 : 1);
