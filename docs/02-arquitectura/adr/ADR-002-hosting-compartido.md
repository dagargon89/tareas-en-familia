# ADR-002 · Hosting compartido sin nginx ni Docker en producción

**Estado:** Aceptada · **Fecha:** 2026-11-22 · **Decide:** David García

## Contexto

Se propuso nginx para facilitar el desarrollo. El plan disponible es Hostinger Business Web Hosting (compartido): LiteSpeed administrado, sin root, sin Docker, sin Redis y sin procesos permanentes. Recursos: 3 GB de RAM, 2 núcleos, 60 PHP workers.

## Decisión

Desplegar en el plan compartido existente. Reglas de servidor en `.htaccess`. Sesiones y caché en archivo. Tareas programadas con cron. Desarrollo local con Laragon o Laravel Herd (Apache o nginx de Herd solo para servir PHP), sin depender de reglas de nginx.

## Alternativas descartadas

| Alternativa | Motivo |
|---|---|
| VPS Hostinger con nginx + Docker | Costo mensual adicional y administración de servidor para una app familiar |
| PowerEdge en casa | Depende de luz, internet e IP; requiere túnel |
| nginx en local | Diferencias de reescritura y headers con LiteSpeed que solo aparecen en producción |

## Consecuencias

- (+) Costo cero adicional y mantenimiento mínimo.
- (−) Sin push en tiempo real ni workers; se aceptan latencias de hasta 1 minuto en tareas programadas.
- Migrar a VPS más adelante no requiere cambiar código, solo el despliegue.
