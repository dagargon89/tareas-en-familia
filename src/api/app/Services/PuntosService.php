<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;

/**
 * Libro mayor de puntos (ADR-004). Es el ÚNICO que escribe en
 * `movimientos_puntos` (prohibición 4 de CLAUDE.md). Nunca edita ni borra.
 */
final class PuntosService
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    /**
     * Acredita los puntos de una instancia aprobada (`origen = tarea`, `origen_id = instancia`).
     * El índice único (`origen`, `origen_id`) impide acreditar dos veces (RN-04).
     *
     * @return int Puntos acreditados (0 si la tarea vale 0)
     */
    public function acreditarTarea(int $perfilId, int $instanciaId, int $puntos, string $titulo): int
    {
        if ($puntos <= 0) {
            return 0;
        }

        $this->registrar($perfilId, 'tarea', $instanciaId, $puntos, 'Tarea aprobada: ' . mb_substr($titulo, 0, 90));

        return $puntos;
    }

    /**
     * Débito de un canje aprobado (`origen = canje`, `origen_id = canje`).
     */
    public function debitarCanje(int $perfilId, int $canjeId, int $costo, string $titulo): void
    {
        $this->registrar($perfilId, 'canje', $canjeId, -abs($costo), 'Canje: ' . mb_substr($titulo, 0, 100));
    }

    /**
     * XP de una insignia obtenida (`origen = insignia`, `origen_id = perfil_insignias.id`).
     */
    public function acreditarInsignia(int $perfilId, int $perfilInsigniaId, int $xp, string $nombre): void
    {
        if ($xp > 0) {
            $this->registrar($perfilId, 'insignia', $perfilInsigniaId, $xp, 'Insignia: ' . mb_substr($nombre, 0, 100));
        }
    }

    /**
     * Inserta un movimiento. Prefiere los métodos con nombre (tarea, canje, insignia).
     */
    public function registrar(int $perfilId, string $origen, ?int $origenId, int $puntos, string $descripcion): void
    {
        $this->db->table('movimientos_puntos')->insert([
            'perfil_id'   => $perfilId,
            'origen'      => $origen,
            'origen_id'   => $origenId,
            'puntos'      => $puntos,
            'descripcion' => mb_substr($descripcion, 0, 120),
            'created_at'  => Time::now()->toDateTimeString(),
        ]);
    }

    /**
     * XP, saldo, reservado y disponible del perfil desde `v_saldos_perfil` (doc 03 §4).
     *
     * @return array{xp: int, saldo: int, reservado: int, disponible: int}
     */
    public function saldos(int $perfilId): array
    {
        $fila = $this->db->table('v_saldos_perfil')->where('perfil_id', $perfilId)->get()->getRowArray();

        $xp        = (int) ($fila['xp'] ?? 0);
        $saldo     = (int) ($fila['saldo'] ?? 0);
        $reservado = (int) ($fila['reservado'] ?? 0);

        return ['xp' => $xp, 'saldo' => $saldo, 'reservado' => $reservado, 'disponible' => max(0, $saldo - $reservado)];
    }
}
