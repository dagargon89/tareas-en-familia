/**
 * Puente ultraligero para cerrar la sesión de Firebase al cambiar de perfil o cerrar sesión,
 * sin forzar la carga del bundle de Firebase en la pantalla inicial.
 */
let limpiador: (() => Promise<void>) | null = null;

export function registrarLimpiadorChat(fn: () => Promise<void>): void {
  limpiador = fn;
}

export async function cerrarSesionChat(): Promise<void> {
  if (limpiador) {
    try {
      await limpiador();
    } catch {
      // No bloquear la salida del perfil si el emulador o red no responde.
    }
  }
}
