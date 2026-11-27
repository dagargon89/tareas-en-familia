# ADR-011 · Chat familiar en tiempo real con Firebase Cloud Firestore (cambio de contrato 1.2 → 1.3)

**Estado:** Aceptada · **Fecha:** 2026-11-26 · **Decide:** David García

## Contexto

La familia necesita un espacio de comunicación interno dentro de **Tareas en Familia** para conversar entre padres e hijas (en sala grupal y en chats individuales 1 a 1) con emojis y reacciones rápidas.

El hosting de producción es compartido (Hostinger LiteSpeed, ADR-002), sin WebSockets ni procesos persistentes. Usar polling HTTP contra MariaDB degradaría la inmediatez del chat y multiplicaría peticiones al hosting compartido.

El contrato de `ApiClient` está congelado (ADR-008); agregar endpoints, una dependencia de producción en web (`firebase`) y permisos acotados de `connect-src` en la CSP requiere este ADR.

## Decisión

1. **Almacenamiento y tiempo real:** Se adopta **Firebase Cloud Firestore** para las salas (`salas/{salaId}`) y mensajes (`salas/{salaId}/mensajes/{mensajeId}`) en tiempo real mediante `onSnapshot`.
2. **Autenticación puente sin cuentas extra (Custom Tokens):**
   - La app conserva su autenticación de dispositivo (`tf_sesion`, ADR-003). Ninguna hija necesita cuenta de Firebase.
   - Se agregan dos endpoints protegidos por filtro `sesion` (requieren perfil activo o modo padre vigente):
     - **E-54** `GET /api/v1/chat/participantes`: devuelve el actor activo (`padre:<id>` o `hija:<id>`) y la lista de integrantes activos de la familia (sin correos, contraseñas ni PINs).
     - **E-55** `POST /api/v1/chat/token`: emite un **Firebase Custom Token** (JWT RS256 firmado en PHP con `openssl_sign`, o JWT sin firma únicamente en el emulador local en `development`), con `uid = "<rol>:<id>"` y claims `{ familia: true, rol: "padre" | "hija", apodo }`.
   - Al cambiar de perfil, salir de modo padre o cerrar sesión, el cliente ejecuta `signOut(auth)` de inmediato.
3. **Alcance y supervisión parental:**
   - Todos los niveles UX (incluyendo `exploradoras`, con teclado, selector local de emojis y frases rápidas de un toque) pueden usar chat grupal e individual.
   - Las hijas solo leen la sala grupal (`familia`) y sus propios chats 1 a 1 (`dm_*`).
   - Los padres en **Modo Padre** pueden leer y moderar (`eliminado = true`) **todas** las salas de la familia, incluidos los chats 1 a 1 entre hermanas, reforzado por `firebase/firestore.rules`.
4. **Impacto en bundle y CSP:**
   - Se agrega `firebase` a `src/web/package.json` cargado exclusivamente de forma diferida (*lazy chunk*), sin crecer el bundle inicial de "Mi día" (RNF-01).
   - El selector de emojis es 100 % local (Unicode nativo, cero CDNs).
   - `SecurityHeadersFilter` amplía `connect-src` estrictamente a los endpoints de Firebase Auth/Firestore (y a los puertos del emulador `9099` y `8088` solo fuera de producción).
5. **Desarrollo local:** Se provee `firebase/` con **Firebase Emulator Suite en Docker** (Auth `:9099`, Firestore `:8088`, UI `:4000`) para probar todo sin credenciales de producción.

## Consecuencias

- (+) Entrega instantánea de mensajes y reacciones sin cargar al servidor de Hostinger.
- (+) Cero contraseñas nuevas para la familia; la identidad la gobierna la sesión de CodeIgniter 4.
- (+) Supervisión parental integrada desde las reglas de seguridad de Firestore.
- (−) Se introduce una dependencia externa controlada en producción (requiere configurar proyecto Firebase y Service Account en `.env` al pasar a producción).
- (−) Solo se almacenan identificadores internos, apodos, avatares/colores y texto del chat en Firestore (nunca correos, PINs ni fotos de evidencia).
