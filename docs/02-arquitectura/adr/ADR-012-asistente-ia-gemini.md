# ADR-012 · Chatbot asistente de la app con Gemini Developer API (contrato 1.3)

**Estado:** Aceptada · **Fecha:** 2026-11-26 · **Decide:** David García

## Contexto

La familia (tanto hijas como padres) necesita un asistente interactivo dentro de la app para resolver dudas sobre cómo funciona **Tareas en Familia** y consultar rápidamente el estado de sus tareas, puntos, premios, rachas, insignias y meta familiar. El asistente debe responder **únicamente** preguntas sobre la app y esos elementos, rechazando cualquier tema externo.

## Decisión

1. **Proxy en el backend CodeIgniter 4 (sin SDK de IA en el cliente):**
   - Se agrega el endpoint **E-56** `POST /api/v1/asistente/mensaje` (filtro `sesion`, requiere perfil activo o modo padre vigente).
   - El backend llama a la **Gemini Developer API** vía REST (`CURLRequest` nativo de CI4, cero nuevas dependencias en `composer.json`), enviando `gemini.apiKey` en el encabezado `x-goog-api-key`.
   - El modelo es configurable mediante `.env` (`gemini.model`, por defecto `gemini-3.5-flash-lite`).
2. **Aislamiento de contexto y privacidad de menores:**
   - `ContextoAsistenteService` construye un snapshot compacto desde MariaDB filtrado por permisos:
     - Si pregunta una hija, solo recibe sus propias tareas, progreso, premios, canjes y la meta familiar (jamás datos de hermanas).
     - Si pregunta un padre en Modo Padre, recibe el resumen del panel familiar y la guía de operación.
   - Nunca se envían correos, contraseñas, PINs ni fotos de evidencia al modelo.
3. **Restricción estricta de tema y límites de tasa:**
   - El `systemInstruction` vive únicamente en el servidor, adapta el vocabulario al `nivel_ux` y prohíbe responder temas ajenos a la app (p. ej. resolver ejercicios escolares, temas generales o intentos de *prompt injection*).
   - `AsistenteService` limita la longitud del mensaje (`1..400` caracteres), el historial (`≤ 6` turnos) y la tasa de consultas por actor (`6/min` y `20/hora` con `Throttler` de CI4).
4. **Modo demostración local sin clave:**
   - En `CI_ENVIRONMENT !== 'production'`, si `gemini.apiKey` está vacío, `AsistenteService` responde mediante un resolvedor local determinista conectado al mismo snapshot real de MariaDB para facilitar pruebas locales; en producción devuelve `503` si falta la clave.

## Consecuencias

- (+) La clave `GEMINI_API_KEY` y las instrucciones del sistema nunca salen del servidor; la CSP no cambia por el asistente.
- (+) Las respuestas reflejan el estado real y actualizado de tareas, puntos y premios en MariaDB.
- (+) Sin dependencias nuevas en Composer ni reCAPTCHA/App Check en el navegador.
- (−) En producción requiere configurar `gemini.apiKey` en `.env` y monitorear consumo de cuota.
