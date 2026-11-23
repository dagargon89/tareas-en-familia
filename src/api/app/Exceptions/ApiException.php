<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Libraries\ErrorApi;
use CodeIgniter\HTTP\ResponsableInterface;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 * Error de negocio que se traduce a la respuesta uniforme del doc 05.
 *
 * Los Services la lanzan; CodeIgniter la captura (`ResponsableInterface`)
 * y envía `getResponse()`, pasando por los filtros `required.after`.
 */
class ApiException extends RuntimeException implements ResponsableInterface
{
    /**
     * @param array<string, string>|null $campos
     * @param array<string, mixed>       $extra
     */
    public function __construct(
        private readonly int $estado,
        private readonly string $codigo,
        string $mensaje,
        private readonly ?array $campos = null,
        private readonly array $extra = [],
    ) {
        parent::__construct($mensaje, $estado);
    }

    /**
     * @param array<string, string> $campos
     */
    public static function validacion(array $campos, string $mensaje = 'Revisa los datos marcados.'): self
    {
        return new self(400, 'VALIDACION', $mensaje, $campos);
    }

    public static function noEncontrado(string $mensaje = 'No encontramos lo que buscas.'): self
    {
        return new self(404, 'NO_ENCONTRADO', $mensaje);
    }

    public static function prohibido(string $mensaje = 'No tienes permiso para esto.'): self
    {
        return new self(403, 'PROHIBIDO', $mensaje);
    }

    public static function estadoInvalido(string $mensaje): self
    {
        return new self(409, 'ESTADO_INVALIDO', $mensaje);
    }

    public function getCodigo(): string
    {
        return $this->codigo;
    }

    public function getEstado(): int
    {
        return $this->estado;
    }

    public function getResponse(): ResponseInterface
    {
        return ErrorApi::respuesta($this->estado, $this->codigo, $this->getMessage(), $this->campos, $this->extra);
    }
}
