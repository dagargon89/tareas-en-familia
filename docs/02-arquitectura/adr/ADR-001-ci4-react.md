# ADR-001 · CodeIgniter 4 + React en lugar de Laravel + Filament + Livewire

**Estado:** Aceptada · **Fecha:** 2026-11-22 · **Decide:** David García

## Contexto

En la misma solicitud se pidió primero Laravel + Filament + Livewire y después "cambia el stack, usa CI4 y Vite". Filament y Livewire solo existen sobre Laravel. La experiencia infantil necesita animaciones y celebraciones ricas.

## Decisión

API REST con CodeIgniter 4.7 y SPA con React 19 + TypeScript + Vite + Tailwind CSS 4. Es el mismo patrón ya usado en Panel de Acuerdos.

## Consecuencias

- (+) Animaciones y gamificación sin límites del renderizado del servidor.
- (+) CI4 es ligero y corre sin problemas en hosting compartido.
- (−) No hay panel de administración generado: las pantallas del padre se construyen en React.
- (−) Dos proyectos (API y web) que versionar juntos; se resuelve con un solo repositorio y un contrato único (doc 05).
