# ADR-005 · Cron cada minuto en lugar de colas

**Estado:** Aceptada · **Fecha:** 2026-11-22 · **Decide:** David García

## Contexto

El hosting compartido no permite workers permanentes ni Redis. La app necesita generar instancias diarias, cerrar semanas, purgar fotos y respaldar.

## Decisión

Un solo comando `php spark tareas:programadas` ejecutado por el cron de hPanel cada minuto. Decide qué subtareas tocan según la hora local, usa un candado de archivo para no superponerse y registra cada ejecución en `writable/logs/cron.log`. La generación de instancias también se asegura al vuelo en `GET /yo/dia`.

## Consecuencias

- (+) Sin infraestructura adicional.
- (−) Las subtareas deben ser idempotentes y terminar en menos de 50 segundos.
- (−) Si Hostinger retrasa el cron, la generación al vuelo cubre "Mi día".
