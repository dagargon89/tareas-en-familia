<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\ConfiguracionModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\I18n\Time;
use finfo;
use Throwable;

/**
 * Fotos de evidencia (RF-06, doc 04 §3.5).
 *
 * 1. Subida válida, máximo 3 MB y MIME real (`finfo`) JPEG, PNG o WebP.
 * 2. Re-codificación con GD a WebP calidad 80, máximo 1600 px por lado (elimina EXIF y GPS).
 * 3. Guardado en `writable/evidencias/<perfil_id>/<uuid>.webp` (fuera de `public/`).
 */
class EvidenciasService
{
    public const MAX_BYTES     = 3 * 1024 * 1024;
    public const MAX_LADO      = 1600;
    public const CALIDAD_WEBP  = 80;
    public const MIMES         = ['image/jpeg', 'image/png', 'image/webp'];
    public const DIAS_RETENCION_POR_OMISION = 30;

    public function __construct(protected readonly BaseConnection $db)
    {
    }

    public static function carpeta(): string
    {
        return WRITEPATH . 'evidencias' . DIRECTORY_SEPARATOR;
    }

    /**
     * Comprobación de subida HTTP real. Se separa para poder sustituirla en pruebas (CLI no tiene subidas).
     */
    protected function subidaValida(UploadedFile $archivo): bool
    {
        return $archivo->isValid();
    }

    /**
     * Valida y re-codifica la foto; devuelve la ruta relativa del WebP ya guardado.
     *
     * @return array{ruta: string, mime: string, bytes: int}
     *
     * @throws ApiException 400 `VALIDACION` en `campos.foto`
     */
    public function preparar(int $perfilId, UploadedFile $archivo): array
    {
        $invalida = static fn (string $mensaje): ApiException => ApiException::validacion(['foto' => $mensaje]);

        // El límite de PHP (`upload_max_filesize`) puede cortar la subida antes que nuestra validación.
        if (in_array($archivo->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            throw $invalida('La foto debe pesar máximo 3 MB.');
        }

        if (! $this->subidaValida($archivo)) {
            throw $invalida('No pudimos recibir la foto. Inténtalo otra vez.');
        }

        $origen = $archivo->getTempName();
        $bytes  = (int) filesize($origen);

        if ($bytes <= 0 || $bytes > self::MAX_BYTES) {
            throw $invalida('La foto debe pesar máximo 3 MB.');
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($origen);

        if (! in_array($mime, self::MIMES, true)) {
            throw $invalida('La foto debe ser JPEG, PNG o WebP.');
        }

        $imagen = @imagecreatefromstring((string) file_get_contents($origen));

        if ($imagen === false) {
            throw $invalida('No pudimos leer la foto.');
        }

        $imagen = $this->orientar($imagen, $origen, $mime);
        $imagen = $this->reducir($imagen);

        $relativa = $perfilId . '/' . self::uuid() . '.webp';
        $destino  = self::carpeta() . $relativa;

        if (! is_dir(dirname($destino)) && ! mkdir(dirname($destino), 0o775, true) && ! is_dir(dirname($destino))) {
            throw new \RuntimeException('No se pudo crear la carpeta de evidencias.');
        }

        if (! imagewebp($imagen, $destino, self::CALIDAD_WEBP)) {
            throw new \RuntimeException('No se pudo guardar la evidencia.');
        }

        return ['ruta' => $relativa, 'mime' => 'image/webp', 'bytes' => (int) filesize($destino)];
    }

    /**
     * Guarda (o reemplaza) la fila de la evidencia de la instancia. Debe llamarse dentro de la
     * transacción de E-12; devuelve la ruta del archivo anterior para borrarlo tras el commit.
     *
     * @param array{ruta: string, mime: string, bytes: int} $foto
     */
    public function registrar(int $instanciaId, array $foto): ?string
    {
        $anterior = $this->db->table('evidencias')->where('instancia_id', $instanciaId)->get()->getRowArray();
        $datos    = $foto + ['borrar_despues' => null, 'created_at' => Time::now()->toDateTimeString()];

        if ($anterior === null) {
            $this->db->table('evidencias')->insert($datos + ['instancia_id' => $instanciaId]);

            return null;
        }

        $this->db->table('evidencias')->where('id', $anterior['id'])->update($datos);

        return $anterior['ruta'];
    }

    /**
     * Al aprobar: `borrar_despues` = hoy + `evidencia_dias_retencion` (RF-06).
     */
    public function programarBorrado(int $instanciaId, string $hoy): void
    {
        $dias = model(ConfiguracionModel::class)->entero('evidencia_dias_retencion', self::DIAS_RETENCION_POR_OMISION);

        $this->db->table('evidencias')->where('instancia_id', $instanciaId)->update([
            'borrar_despues' => date('Y-m-d', (int) strtotime($hoy . ' +' . max(0, $dias) . ' days')),
        ]);
    }

    public function borrarArchivo(?string $relativa): void
    {
        if ($relativa === null || $relativa === '') {
            return;
        }

        $base    = realpath(self::carpeta());
        $archivo = realpath(self::carpeta() . $relativa);

        if ($base !== false && $archivo !== false && str_starts_with($archivo, $base . DIRECTORY_SEPARATOR) && is_file($archivo)) {
            unlink($archivo);
        }
    }

    /**
     * Subtarea "purgar": borra archivo y fila de las evidencias con `borrar_despues` vencido.
     *
     * @return int Evidencias borradas
     */
    public function purgar(string $hoy): int
    {
        $vencidas = $this->db->table('evidencias')->where('borrar_despues <', $hoy)->get()->getResultArray();

        foreach ($vencidas as $evidencia) {
            $this->db->table('evidencias')->where('id', $evidencia['id'])->delete();
            $this->borrarArchivo($evidencia['ruta']);
        }

        return count($vencidas);
    }

    /**
     * E-36 · Archivo de la evidencia para la dueña o el padre en modo padre.
     *
     * @return string Ruta absoluta del archivo
     *
     * @throws ApiException 404 o 403
     */
    public function archivoPara(int $evidenciaId, ?int $perfilActivoId, bool $modoPadre): string
    {
        $fila = $this->db->table('evidencias e')
            ->select('e.ruta, i.perfil_id')
            ->join('tarea_instancias i', 'i.id = e.instancia_id')
            ->where('e.id', $evidenciaId)
            ->get()->getRowArray();

        if ($fila === null) {
            throw ApiException::noEncontrado();
        }

        if (! $modoPadre && $perfilActivoId !== (int) $fila['perfil_id']) {
            throw ApiException::prohibido('Esta foto es de otra persona.');
        }

        $archivo = self::carpeta() . $fila['ruta'];

        if (! is_file($archivo)) {
            throw ApiException::noEncontrado();
        }

        return $archivo;
    }

    /**
     * Respeta la orientación EXIF de las fotos de celular antes de descartar los metadatos.
     */
    private function orientar(\GdImage $imagen, string $origen, string $mime): \GdImage
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $imagen;
        }

        try {
            $exif = @exif_read_data($origen);
        } catch (Throwable) {
            $exif = false;
        }

        $angulo = match ((int) (is_array($exif) ? ($exif['Orientation'] ?? 1) : 1)) {
            3       => 180,
            6       => -90,
            8       => 90,
            default => 0,
        };

        if ($angulo === 0) {
            return $imagen;
        }

        $rotada = imagerotate($imagen, $angulo, 0);

        return $rotada === false ? $imagen : $rotada;
    }

    private function reducir(\GdImage $imagen): \GdImage
    {
        $ancho = imagesx($imagen);
        $alto  = imagesy($imagen);
        $mayor = max($ancho, $alto);

        if ($mayor <= self::MAX_LADO) {
            return $imagen;
        }

        $factor = self::MAX_LADO / $mayor;
        $nueva  = imagescale($imagen, max(1, (int) round($ancho * $factor)), max(1, (int) round($alto * $factor)));

        return $nueva === false ? $imagen : $nueva;
    }

    private static function uuid(): string
    {
        $b    = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0F) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
