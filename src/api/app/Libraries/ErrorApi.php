<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Filters\SecurityHeadersFilter;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Respuesta de error uniforme del contrato (doc 05 §1):
 * `{ "error": { "codigo": "...", "mensaje": "...", "campos": { ... } } }`.
 *
 * `campos` solo aparece cuando hay detalle por campo. `extra` permite datos
 * propios del código (p. ej. `intentos_restantes`, `bloqueado_hasta`).
 */
final class ErrorApi
{
    /**
     * @param array<string, string>|null $campos
     * @param array<string, mixed>       $extra
     *
     * @return array{error: array<string, mixed>}
     */
    public static function cuerpo(string $codigo, string $mensaje, ?array $campos = null, array $extra = []): array
    {
        $error = ['codigo' => $codigo, 'mensaje' => $mensaje];

        if ($campos !== null && $campos !== []) {
            $error['campos'] = $campos;
        }

        return ['error' => array_merge($error, $extra)];
    }

    /**
     * Construye la respuesta JSON de error sobre la respuesta compartida (o la dada).
     *
     * @param array<string, string>|null $campos
     * @param array<string, mixed>       $extra
     */
    public static function respuesta(
        int $estado,
        string $codigo,
        string $mensaje,
        ?array $campos = null,
        array $extra = [],
        ?ResponseInterface $respuesta = null,
    ): ResponseInterface {
        $respuesta ??= service('response');

        $respuesta->setStatusCode($estado)
            ->setJSON(self::cuerpo($codigo, $mensaje, $campos, $extra));

        return SecurityHeadersFilter::aplicar($respuesta);
    }
}
