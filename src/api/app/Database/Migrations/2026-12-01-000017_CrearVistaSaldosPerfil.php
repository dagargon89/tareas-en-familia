<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\MigracionBase;

/**
 * Vista `v_saldos_perfil`: XP de por vida, saldo y reservado por canjes
 * solicitados (doc 03 §4). Forge no crea vistas: SQL literal del doc 03.
 */
class CrearVistaSaldosPerfil extends MigracionBase
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
            CREATE VIEW v_saldos_perfil AS
            SELECT p.id AS perfil_id,
                   COALESCE((SELECT SUM(m.puntos) FROM movimientos_puntos m
                              WHERE m.perfil_id = p.id AND m.origen IN ('tarea','insignia') AND m.puntos > 0), 0) AS xp,
                   COALESCE((SELECT SUM(m.puntos) FROM movimientos_puntos m WHERE m.perfil_id = p.id), 0) AS saldo,
                   COALESCE((SELECT SUM(c.costo) FROM canjes c
                              WHERE c.perfil_id = p.id AND c.estado = 'solicitado'), 0) AS reservado
            FROM perfiles p
            SQL);
    }

    public function down(): void
    {
        $this->db->query('DROP VIEW IF EXISTS v_saldos_perfil');
    }
}
