<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Firebase as FirebaseConfig;

/**
 * Headers de seguridad del doc 04 §3.4 en toda respuesta.
 *
 * Se registra en `Filters::$required['after']`, que CI4 ejecuta también para
 * respuestas de excepciones `ResponsableInterface`, del 404 override y de
 * filtros `before` que cortan la petición (p. ej. CSRF). El manejador de
 * excepciones usa `aplicar()` para el resto de errores.
 */
final class SecurityHeadersFilter implements FilterInterface
{
    public const CSP = "default-src 'self'; img-src 'self' blob: data:; media-src 'self'; "
        . "style-src 'self' 'unsafe-inline'; script-src 'self'; "
        . "connect-src 'self' https://identitytoolkit.googleapis.com https://securetoken.googleapis.com "
        . 'https://firestore.googleapis.com https://*.googleapis.com wss://*.firebaseio.com; '
        . "frame-ancestors 'none'; base-uri 'self'; form-action 'self'";

    public const HSTS = 'max-age=31536000; includeSubDomains';

    /**
     * @var array<string, string>
     */
    public const HEADERS = [
        'Content-Security-Policy' => self::CSP,
        'X-Content-Type-Options'  => 'nosniff',
        'X-Frame-Options'         => 'DENY',
        'Referrer-Policy'         => 'same-origin',
        'Permissions-Policy'      => 'camera=(self), microphone=(), geolocation=()',
        'X-Robots-Tag'            => 'noindex, nofollow',
    ];

    /**
     * Aplica los headers. HSTS solo en producción (o si se fuerza con `$produccion`).
     */
    public static function aplicar(ResponseInterface $respuesta, ?bool $produccion = null): ResponseInterface
    {
        foreach (self::HEADERS as $nombre => $valor) {
            if ($nombre === 'Content-Security-Policy' && ENVIRONMENT === 'development' && config(FirebaseConfig::class)->emulador) {
                $valor = str_replace(
                    "connect-src 'self'",
                    "connect-src 'self' http://127.0.0.1:9099 http://localhost:9099 http://127.0.0.1:8088 http://localhost:8088",
                    $valor,
                );
            }

            $respuesta->setHeader($nombre, $valor);
        }

        if ($produccion ?? ENVIRONMENT === 'production') {
            $respuesta->setHeader('Strict-Transport-Security', self::HSTS);
        }

        return $respuesta;
    }

    /**
     * @param list<string>|null $arguments
     */
    public function before(RequestInterface $request, $arguments = null): null
    {
        return null;
    }

    /**
     * @param list<string>|null $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ResponseInterface
    {
        return self::aplicar($response);
    }
}
