<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * Construye el contexto vivo (filtrado estrictamente por permisos) para el asistente IA (ADR-012).
 *
 * - Si el actor es una hija: SOLO incluye sus propias tareas, progreso, premios, canjes y la meta familiar.
 *   Nunca expone datos de hermanas, correos, contraseñas ni PINs.
 * - Si el actor es un padre en modo padre: incluye el resumen del panel familiar (solo apodos) y la guía de uso.
 */
final class ContextoAsistenteService
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    /**
     * @param array{actor_id: string, tipo: 'padre'|'hija', id: int, apodo: string, avatar: string, color: string, nivel_ux: string|null} $actor
     *
     * @return array{resumen_texto: string, datos: array<string, mixed>, sugerencias: list<string>}
     */
    public function construir(array $actor): array
    {
        if ($actor['tipo'] === 'padre') {
            return $this->paraPadre($actor);
        }

        return $this->paraHija($actor);
    }

    /**
     * @param array{actor_id: string, tipo: 'hija', id: int, apodo: string, nivel_ux: string|null} $actor
     *
     * @return array{resumen_texto: string, datos: array<string, mixed>, sugerencias: list<string>}
     */
    private function paraHija(array $actor): array
    {
        $perfilId = $actor['id'];
        $nivelUx  = $actor['nivel_ux'] ?? 'aventureras';

        $dia         = (new InstanciasService($this->db))->dia($perfilId, null);
        $progreso    = (new ProgresoService($this->db))->progreso($perfilId);
        $recompensas = (new CanjesService($this->db))->paraPerfil($perfilId);
        $canjes      = $this->canjesRecientes($perfilId);

        $lineas = [
            "Usuario actual: {$actor['apodo']} (rol: hija, nivel UX: {$nivelUx}).",
            "Fecha de hoy: {$dia['fecha']}.",
            sprintf(
                'Progreso: %d XP (nivel %s%s), saldo disponible: %d puntos, racha: %d semanas, semana actual: %d/%d aprobadas (%s%%).',
                $progreso['xp'],
                $progreso['nivel']['nombre'],
                $progreso['siguiente_nivel'] !== null
                    ? ', faltan ' . max(0, $progreso['siguiente_nivel']['xp_desde'] - $progreso['xp']) . ' XP para ' . $progreso['siguiente_nivel']['nombre']
                    : ' (nivel máximo)',
                $progreso['disponible'],
                $progreso['racha_semanas'],
                $progreso['semana_actual']['aprobadas'],
                $progreso['semana_actual']['asignadas'],
                $progreso['semana_actual']['porcentaje'],
            ),
        ];

        if ($dia['instancias'] === []) {
            $lineas[] = 'Tareas de hoy: ninguna asignada hoy.';
        } else {
            $lineas[] = 'Tareas de hoy:';

            foreach ($dia['instancias'] as $inst) {
                $extra = [];

                if ($inst['puntos'] > 0) {
                    $extra[] = $inst['puntos'] . ' pts';
                }

                if ($inst['requiere_foto']) {
                    $extra[] = 'requiere foto';
                }

                if (! empty($inst['hora'])) {
                    $extra[] = 'hora ' . substr((string) $inst['hora'], 0, 5);
                }

                if (! empty($inst['mensaje_devolucion'])) {
                    $extra[] = 'nota de papá/mamá: ' . $inst['mensaje_devolucion'];
                }

                $sufijo   = $extra === [] ? '' : ' (' . implode(', ', $extra) . ')';
                $lineas[] = "- {$inst['titulo']} [{$inst['estado']}]{$sufijo}";
            }
        }

        if ($recompensas !== []) {
            $lineas[] = 'Premios disponibles en el catálogo:';

            foreach ($recompensas as $r) {
                $estado   = $r['alcanza'] ? 'YA LE ALCANZA' : "faltan {$r['faltan']} pts";
                $lineas[] = "- {$r['titulo']}: {$r['costo']} pts ({$estado})";
            }
        }

        if ($canjes !== []) {
            $lineas[] = 'Canjes recientes:';

            foreach ($canjes as $c) {
                $lineas[] = "- {$c['titulo']} ({$c['costo']} pts): estado {$c['estado']}";
            }
        }

        $meta = $progreso['meta_familiar'];

        if (is_array($meta)) {
            $lineas[] = sprintf(
                'Meta familiar activa: "%s" (premio: %s) — progreso %d / %d puntos.',
                $meta['titulo'],
                $meta['premio'],
                $meta['progreso_puntos'],
                $meta['objetivo_puntos'],
            );
        }

        $obtenidas = array_values(array_filter($progreso['insignias'], static fn (array $i): bool => $i['obtenida_en'] !== null));
        $lineas[]  = sprintf('Insignias obtenidas: %d de %d.', count($obtenidas), count($progreso['insignias']));

        $sugerencias = match ($nivelUx) {
            'exploradoras' => [
                '¿Qué misiones me faltan hoy?',
                '¿Para qué premio me alcanzan mis estrellas?',
                '¿Cómo gano una insignia?',
                '¿Cómo va la meta de la familia?',
            ],
            'pro' => [
                '¿Qué tareas tengo pendientes hoy?',
                '¿Cuántos puntos me faltan para un premio?',
                '¿Cómo funciona la racha semanal?',
                '¿Cómo creo una tarea propia?',
            ],
            default => [
                '¿Qué misiones me faltan hoy?',
                '¿Para qué premio me alcanza?',
                '¿Cuánto me falta para subir de nivel?',
                '¿Cómo va la meta familiar?',
            ],
        };

        return [
            'resumen_texto' => implode("\n", $lineas),
            'datos'         => [
                'actor'       => $actor,
                'dia'         => $dia,
                'progreso'    => $progreso,
                'recompensas' => $recompensas,
                'canjes'      => $canjes,
            ],
            'sugerencias'   => $sugerencias,
        ];
    }

    /**
     * @param array{actor_id: string, tipo: 'padre', id: int, apodo: string, nivel_ux: null} $actor
     *
     * @return array{resumen_texto: string, datos: array<string, mixed>, sugerencias: list<string>}
     */
    private function paraPadre(array $actor): array
    {
        $panel       = (new PanelService($this->db))->panel(null);
        $recompensas = (new CanjesService($this->db))->recompensas();

        $lineas = [
            "Usuario actual: {$actor['apodo']} (rol: padre en Modo Padre).",
            "Semana ISO en curso: {$panel['semana_iso']}.",
            'Resumen por hija:',
        ];

        foreach ($panel['hijas'] as $h) {
            $lineas[] = sprintf(
                '- %s (nivel %s): %s%% esta semana, %d tareas por aprobar, racha %d semanas, %d pts disponibles.',
                $h['perfil']['apodo'],
                $h['perfil']['nivel_ux'],
                $h['porcentaje_semana'],
                $h['por_aprobar'],
                $h['racha_semanas'],
                $h['disponible'],
            );
        }

        $lineas[] = 'Canjes pendientes de aprobar: ' . count($panel['canjes_pendientes']) . '.';

        if (is_array($panel['meta_familiar'])) {
            $m        = $panel['meta_familiar'];
            $lineas[] = sprintf(
                'Meta familiar activa: "%s" (premio: %s) — %d / %d puntos.',
                $m['titulo'],
                $m['premio'],
                $m['progreso_puntos'],
                $m['objetivo_puntos'],
            );
        }

        $activas  = array_values(array_filter($recompensas, static fn (array $r): bool => $r['activa']));
        $lineas[] = 'Premios activos en catálogo: ' . count($activas) . '.';

        return [
            'resumen_texto' => implode("\n", $lineas),
            'datos'         => [
                'actor'       => $actor,
                'panel'       => $panel,
                'recompensas' => $activas,
            ],
            'sugerencias'   => [
                '¿Qué tengo pendiente por aprobar?',
                '¿Cómo cambio o desbloqueo el PIN de una hija?',
                '¿Cómo va la meta familiar?',
                '¿Cómo agrego al otro papá o mamá?',
            ],
        ];
    }

    /**
     * @return list<array{titulo: string, costo: int, estado: string}>
     */
    private function canjesRecientes(int $perfilId): array
    {
        $filas = $this->db->table('canjes c')
            ->select('r.titulo, c.costo, c.estado')
            ->join('recompensas r', 'r.id = c.recompensa_id')
            ->where('c.perfil_id', $perfilId)
            ->orderBy('c.id', 'DESC')
            ->limit(5)
            ->get()->getResultArray();

        return array_map(static fn (array $f): array => [
            'titulo' => (string) $f['titulo'],
            'costo'  => (int) $f['costo'],
            'estado' => (string) $f['estado'],
        ], $filas);
    }
}
