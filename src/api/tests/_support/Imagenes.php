<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Archivos de prueba para evidencias: JPEG con EXIF GPS, relleno hasta un
 * tamaño dado y un PHP disfrazado de `.jpg`.
 *
 * @internal
 */
final class Imagenes
{
    /**
     * JPEG de `ancho × alto` con un segmento EXIF que incluye GPS, relleno con
     * comentarios hasta al menos `$bytes`.
     */
    public static function jpegConGps(string $carpeta, int $bytes, int $ancho = 2000, int $alto = 1500): string
    {
        $img = imagecreatetruecolor($ancho, $alto);

        for ($x = 0; $x < $ancho; $x += 50) {
            imagefilledrectangle($img, $x, 0, $x + 49, $alto, (int) imagecolorallocate($img, intdiv($x, 8) % 256, 120, 200));
        }

        ob_start();
        imagejpeg($img, null, 85);
        $jpeg = (string) ob_get_clean();

        // TIFF little-endian: IFD0 con GPSInfo (0x8825) → IFD GPS con GPSLatitudeRef = "N".
        $tiff = 'II*' . "\0" . pack('V', 8)
            . pack('v', 1) . pack('vvVV', 0x8825, 4, 1, 26) . pack('V', 0)
            . pack('v', 1) . pack('vvV', 0x0001, 2, 2) . "N\0\0\0" . pack('V', 0);
        $exif = "Exif\0\0" . $tiff;
        $app1 = "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif;

        // Inserta APP1 después de APP0 (JFIF) y rellena con segmentos COM.
        $finApp0 = 4 + unpack('n', substr($jpeg, 4, 2))[1];
        $relleno = '';
        $faltan  = $bytes - strlen($jpeg) - strlen($app1);

        while ($faltan > 0) {
            $trozo   = min(65_000, $faltan);
            $relleno .= "\xFF\xFE" . pack('n', $trozo + 2) . str_repeat('x', $trozo);
            $faltan -= $trozo + 4;
        }

        $ruta = $carpeta . '/foto_' . bin2hex(random_bytes(4)) . '.jpg';
        file_put_contents($ruta, substr($jpeg, 0, $finApp0) . $app1 . $relleno . substr($jpeg, $finApp0));

        return $ruta;
    }

    public static function phpDisfrazado(string $carpeta): string
    {
        $ruta = $carpeta . '/foto_' . bin2hex(random_bytes(4)) . '.jpg';
        file_put_contents($ruta, "<?php echo 'hola'; system(\$_GET['c'] ?? ''); ?>\n");

        return $ruta;
    }

    public static function png(string $carpeta, int $ancho = 300, int $alto = 200): string
    {
        $img  = imagecreatetruecolor($ancho, $alto);
        $ruta = $carpeta . '/foto_' . bin2hex(random_bytes(4)) . '.png';
        imagepng($img, $ruta);

        return $ruta;
    }
}
