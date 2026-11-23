# ADR-006 · Tres niveles de experiencia sobre una misma lógica

**Estado:** Aceptada · **Fecha:** 2026-11-22 · **Decide:** David García

## Contexto

Las usuarias van de 1° de primaria a secundaria. Una interfaz infantil molesta a una adolescente y una sobria aburre a una niña de 7 años.

## Decisión

Un atributo `perfiles.nivel_ux` (`exploradoras`, `aventureras`, `pro`) que cambia tamaños, vocabulario, navegación y nivel de animación mediante `data-nivel` en `<html>` y un objeto de tema por nivel. Los componentes, la API y las reglas de negocio son las mismas. Las capacidades extra por nivel son: Aventureras y Pro crean sus tareas escolares (RF-19), y Pro crea además tareas propias de cualquier tipo (RF-15).

## Consecuencias

- (+) Un solo código; el padre cambia el nivel cuando la niña crece.
- (−) Cada componente se prueba en los tres niveles (pruebas de componente parametrizadas y capturas en Playwright).
