import { ErrorApi } from '../api/HttpApiClient.ts';

/** Extrae el detalle de un ErrorApi; cualquier otro error se trata como falla de red. */
export function detalleError(e: unknown) {
  if (e instanceof ErrorApi) return { status: e.status, ...e.detalle };
  return { status: 0, codigo: 'RED', mensaje: 'No hay conexión. Revisa el internet e intenta de nuevo.' };
}
