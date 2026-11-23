<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Catalogo;
use App\Entities\Perfil;
use App\Exceptions\ApiException;
use App\Libraries\Hash;
use App\Models\PerfilModel;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/**
 * Perfiles de las hijas: selector con PIN (RF-01) y gestión del padre (RF-17).
 */
final class PerfilesService
{
    private readonly PerfilModel $perfiles;
    private readonly PinService $pines;

    public function __construct(
        private readonly BaseConnection $db,
        private readonly SesionService $sesion = new SesionService(),
        ?PerfilModel $perfiles = null,
    ) {
        $this->perfiles = $perfiles ?? model(PerfilModel::class);
        $this->pines    = new PinService($db);
    }

    /**
     * E-06 · Perfiles activos ordenados (`PerfilPublico[]`).
     *
     * @return list<array<string, mixed>>
     */
    public function publicos(): array
    {
        return array_map(static fn (Perfil $p): array => $p->publico(), $this->perfiles->activosOrdenados());
    }

    /**
     * E-07 · Entrar al perfil con su PIN.
     *
     * @param array<string, mixed> $entrada `{ pin }`
     */
    public function entrar(int $id, array $entrada): void
    {
        $pin = $entrada['pin'] ?? null;

        if (! PinService::formatoValido($pin)) {
            throw ApiException::validacion(['pin' => 'El PIN debe tener 4 dígitos.']);
        }

        if ($this->perfiles->activo($id) === null) {
            throw ApiException::noEncontrado();
        }

        $this->pines->verificar('perfiles', $id, $pin);
        $this->sesion->entrarPerfil($id);
    }

    public function salir(): void
    {
        $this->sesion->salirPerfil();
    }

    /**
     * E-23 · Todos los perfiles (`PerfilAdmin[]`).
     *
     * @return list<array<string, mixed>>
     */
    public function admin(): array
    {
        return array_map(static fn (Perfil $p): array => $p->admin(), $this->perfiles->todosOrdenados());
    }

    /**
     * E-24 · Crear perfil (`PerfilEntrada`, PIN obligatorio).
     *
     * @param array<string, mixed> $entrada
     *
     * @return array<string, mixed>
     */
    public function crear(array $entrada): array
    {
        $datos = $this->validar($entrada, true);
        $datos += ['sonidos' => $this->sonidosPorOmision() ? 1 : 0];

        $id = $this->perfiles->insert($datos, true);

        if ($id === false) {
            throw new RuntimeException('No se pudo crear el perfil.');
        }

        return $this->buscar((int) $id)->admin();
    }

    /**
     * E-25 · Editar perfil; sin `pin` el PIN no cambia.
     *
     * @param array<string, mixed> $entrada
     *
     * @return array<string, mixed>
     */
    public function editar(int $id, array $entrada): array
    {
        $this->buscar($id);
        $this->perfiles->update($id, $this->validar($entrada, false));

        if (array_key_exists('activo', $entrada) && $entrada['activo'] === false) {
            $this->olvidarSiEsElActivo($id);
        }

        return $this->buscar($id)->admin();
    }

    /**
     * E-26 · Desbloquear (intentos en 0, sin bloqueo).
     *
     * @return array<string, mixed>
     */
    public function desbloquear(int $id): array
    {
        $this->buscar($id);
        $this->pines->desbloquear('perfiles', $id);

        return $this->buscar($id)->admin();
    }

    /**
     * E-27 · `definitivo = false` desactiva; `true` borra todo (doc 03 §4.1).
     */
    public function borrar(int $id, bool $definitivo): void
    {
        $this->buscar($id);

        if (! $definitivo) {
            $this->perfiles->update($id, ['activo' => 0]);
            $this->olvidarSiEsElActivo($id);

            return;
        }

        $rutas = array_column(
            $this->db->table('evidencias e')
                ->select('e.ruta')
                ->join('tarea_instancias i', 'i.id = e.instancia_id')
                ->where('i.perfil_id', $id)
                ->get()->getResultArray(),
            'ruta',
        );

        // Orden del doc 03 §4.1 por los ON DELETE RESTRICT.
        $this->db->transException(true)->transStart();
        $this->db->table('movimientos_puntos')->where('perfil_id', $id)->delete();
        $this->db->table('canjes')->where('perfil_id', $id)->delete();
        $this->db->table('tarea_instancias')->where('perfil_id', $id)->delete(); // arrastra evidencias y revisiones
        $this->db->table('tareas')->where('perfil_id', $id)->delete();           // arrastra recurrencias
        $this->db->table('perfil_insignias')->where('perfil_id', $id)->delete();
        $this->db->table('rachas')->where('perfil_id', $id)->delete();
        $this->db->table('perfiles')->where('id', $id)->delete();
        $this->db->transComplete();

        // Los archivos se borran tras confirmar la transacción: si la base fallara, no se pierden fotos de filas vivas.
        $this->borrarArchivos($id, $rutas);
        $this->olvidarSiEsElActivo($id);
    }

    private function buscar(int $id): Perfil
    {
        return $this->perfiles->find($id) ?? throw ApiException::noEncontrado();
    }

    private function olvidarSiEsElActivo(int $id): void
    {
        if ($this->sesion->perfilActivoId() === $id) {
            $this->sesion->salirPerfil();
        }
    }

    private function sonidosPorOmision(): bool
    {
        $fila = $this->db->table('configuracion')->where('clave', 'sonidos_default')->get()->getRowArray();

        return $fila === null ? true : (bool) json_decode($fila['valor'], true);
    }

    /**
     * Valida `PerfilEntrada` y devuelve las columnas a guardar.
     *
     * @param array<string, mixed> $entrada
     *
     * @return array<string, int|string>
     */
    private function validar(array $entrada, bool $pinObligatorio): array
    {
        $campos = [];
        $apodo  = is_string($entrada['apodo'] ?? null) ? trim($entrada['apodo']) : '';
        $avatar = is_string($entrada['avatar'] ?? null) ? trim($entrada['avatar']) : '';

        if ($apodo === '' || mb_strlen($apodo) > 30) {
            $campos['apodo'] = 'Escribe un apodo de hasta 30 caracteres.';
        }

        if (! Catalogo::esAvatar($avatar)) {
            $campos['avatar'] = 'Elige un avatar de la lista.';
        }

        if (! in_array($entrada['color'] ?? null, PerfilModel::COLORES, true)) {
            $campos['color'] = 'Elige un color de la lista.';
        }

        if (! in_array($entrada['nivel_ux'] ?? null, PerfilModel::NIVELES, true)) {
            $campos['nivel_ux'] = 'Elige un nivel de la lista.';
        }

        $hayPin = array_key_exists('pin', $entrada) && $entrada['pin'] !== null;

        if ($pinObligatorio || $hayPin) {
            $error = PinService::errorDePinNuevo($entrada['pin'] ?? null);

            if ($error !== null) {
                $campos['pin'] = $error;
            }
        }

        if (array_key_exists('orden', $entrada) && (! is_int($entrada['orden']) || $entrada['orden'] < 0 || $entrada['orden'] > 255)) {
            $campos['orden'] = 'El orden debe ser un número entre 0 y 255.';
        }

        if (array_key_exists('activo', $entrada) && ! is_bool($entrada['activo'])) {
            $campos['activo'] = 'Debe ser verdadero o falso.';
        }

        if ($campos !== []) {
            throw ApiException::validacion($campos);
        }

        $datos = [
            'apodo'    => $apodo,
            'avatar'   => $avatar,
            'color'    => $entrada['color'],
            'nivel_ux' => $entrada['nivel_ux'],
        ];

        if ($hayPin) {
            $datos['pin_hash'] = Hash::crear($entrada['pin']);
        }

        if (array_key_exists('orden', $entrada)) {
            $datos['orden'] = $entrada['orden'];
        }

        if (array_key_exists('activo', $entrada)) {
            $datos['activo'] = $entrada['activo'] ? 1 : 0;
        }

        return $datos;
    }

    /**
     * Borra las fotos del perfil (`writable/evidencias/<perfil_id>/` y rutas registradas).
     *
     * @param list<string> $rutas Relativas a `writable/evidencias/`
     */
    private function borrarArchivos(int $id, array $rutas): void
    {
        $base = realpath(WRITEPATH . 'evidencias');

        if ($base === false) {
            return;
        }

        foreach ($rutas as $ruta) {
            $archivo = realpath($base . DIRECTORY_SEPARATOR . $ruta);

            if ($archivo !== false && str_starts_with($archivo, $base . DIRECTORY_SEPARATOR) && is_file($archivo)) {
                unlink($archivo);
            }
        }

        $carpeta = $base . DIRECTORY_SEPARATOR . $id;

        if (is_dir($carpeta)) {
            self::borrarCarpeta($carpeta);
        }
    }

    private static function borrarCarpeta(string $carpeta): void
    {
        foreach (scandir($carpeta) ?: [] as $nombre) {
            if ($nombre === '.' || $nombre === '..') {
                continue;
            }

            $ruta = $carpeta . DIRECTORY_SEPARATOR . $nombre;
            is_dir($ruta) && ! is_link($ruta) ? self::borrarCarpeta($ruta) : unlink($ruta);
        }

        rmdir($carpeta);
    }
}
